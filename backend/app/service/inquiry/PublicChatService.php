<?php

declare(strict_types=1);

namespace app\service\inquiry;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\PublicChatRepository;
use app\repository\SystemSettingRepository;
use app\service\ai\DeepSeekClient;
use app\service\knowledge\KnowledgeRetrievalService;

final class PublicChatService
{
    public function __construct(
        private readonly PublicChatRepository $publicChatRepository = new PublicChatRepository(),
        private readonly DeepSeekClient $deepSeekClient = new DeepSeekClient(),
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository(),
        private readonly KnowledgeRetrievalService $knowledgeRetrievalService = new KnowledgeRetrievalService()
    ) {
    }

    public function recordVisitorEvent(array $input): array
    {
        $clientId = $this->requireClientId((string) ($input['client_id'] ?? ''));
        $page = $this->requirePage((string) ($input['path'] ?? ''));
        $sessionCode = $this->resolveSessionCode($clientId, (string) ($input['session_code'] ?? ''));

        $events = $this->publicChatRepository->appendVisitorEvent($sessionCode, [
            'page' => $page,
            'title' => (string) ($input['title'] ?? ''),
            'referrer' => (string) ($input['referrer'] ?? ''),
            'visited_at' => date('Y-m-d H:i:s'),
            'language_code' => $this->normalizeLanguage((string) ($input['language'] ?? '')),
        ]);

        $conversation = $this->publicChatRepository->findConversationByCode($sessionCode);
        if (is_array($conversation) && (int) ($conversation['inquiry_id'] ?? 0) > 0) {
            $this->publicChatRepository->syncInquiryBrowseTraces((int) $conversation['inquiry_id'], $this->publicChatRepository->listVisitorEvents($sessionCode));
        }

        return [
            'accepted' => 1,
            'session_code' => $sessionCode,
            'visit_count' => count($events),
        ];
    }

    public function chat(array $input): array
    {
        $clientId = $this->requireClientId((string) ($input['client_id'] ?? ''));
        $message = trim((string) ($input['message'] ?? ''));
        if ($message === '') {
            throw new BusinessException('message required', ErrorCode::INVALID_PARAMS);
        }

        $languageCode = $this->normalizeLanguage((string) ($input['language'] ?? ''));
        $sessionCode = $this->resolveSessionCode($clientId, (string) ($input['session_code'] ?? ''));
        $page = trim((string) ($input['path'] ?? ''));
        if ($page !== '') {
            $this->publicChatRepository->appendVisitorEvent($sessionCode, [
                'page' => $page,
                'title' => (string) ($input['title'] ?? ''),
                'referrer' => (string) ($input['referrer'] ?? ''),
                'visited_at' => date('Y-m-d H:i:s'),
                'language_code' => $languageCode,
            ]);
        }

        $conversation = $this->publicChatRepository->createOrTouchConversation([
            'session_code' => $sessionCode,
            'source_page' => $page,
            'entry_language' => $languageCode,
            'resolved_language' => $languageCode,
            'country_code' => (string) ($input['country_code'] ?? ''),
            'device_type' => $this->detectDeviceType(),
            'utm_source' => (string) ($input['utm_source'] ?? ''),
            'last_message_at' => date('Y-m-d H:i:s'),
        ]);

        $analysis = $this->analyzeMessage($message, $languageCode, $page, (int) ($conversation['session_id'] ?? 0));
        $userMessageTranslation = $this->translateMessageForStorage($message, $languageCode);
        $assistantReply = (string) ($analysis['reply'] ?? '');
        $assistantReplyTranslation = $this->translateMessageForStorage($assistantReply, $languageCode);

        $this->publicChatRepository->appendMessage((int) ($conversation['session_id'] ?? 0), [
            'role' => 'user',
            'content' => $message,
            'message_language' => $languageCode,
            'translated_text' => $userMessageTranslation,
            'intent_code' => (string) ($analysis['intent_code'] ?? 'general_inquiry'),
            'contains_contact_info' => !empty($analysis['contains_contact_info']) ? 1 : 0,
            'extracted_entities_json' => $analysis['entities'] ?? [],
        ]);

        $this->publicChatRepository->appendMessage((int) ($conversation['session_id'] ?? 0), [
            'role' => 'assistant',
            'content' => $assistantReply,
            'message_language' => $languageCode,
            'translated_text' => $assistantReplyTranslation,
            'intent_code' => (string) ($analysis['intent_code'] ?? 'general_inquiry'),
            'contains_contact_info' => 0,
            'extracted_entities_json' => [
                'sources' => $this->normalizeStoredSources($analysis['sources'] ?? []),
            ],
        ]);

        $snapshot = $this->mergeSnapshot(
            $this->publicChatRepository->latestSnapshot((int) ($conversation['session_id'] ?? 0)) ?? [],
            $analysis['entities'] ?? [],
            $message
        );
        $snapshot['confidence_score'] = $this->calculateConfidenceScore($snapshot);

        if ($this->hasSnapshotValue($snapshot)) {
            $this->publicChatRepository->appendSnapshot((int) ($conversation['session_id'] ?? 0), $snapshot);
        }

        $browseTraces = $this->publicChatRepository->listVisitorEvents($sessionCode);
        $inquiry = $this->publicChatRepository->findInquiryBySessionId((int) ($conversation['session_id'] ?? 0));
        if ($this->shouldCreateInquiry($snapshot)) {
            if ($inquiry === null) {
                $inquiry = $this->publicChatRepository->createInquiryFromSnapshot(
                    (int) ($conversation['session_id'] ?? 0),
                    $snapshot,
                    $browseTraces,
                    $languageCode,
                    $conversation
                );
            } else {
                $inquiry = $this->publicChatRepository->updateInquiryFromSnapshot(
                    (int) ($inquiry['id'] ?? 0),
                    $snapshot,
                    $browseTraces,
                    $languageCode,
                    $conversation
                );
            }

            if (is_array($inquiry) && (int) ($inquiry['id'] ?? 0) > 0) {
                $this->publicChatRepository->updateConversationInquiryLink((int) ($conversation['session_id'] ?? 0), (int) $inquiry['id'], true);
            }
        }

        return [
            'session_code' => $sessionCode,
            'assistant_reply' => (string) ($analysis['reply'] ?? ''),
            'intent_code' => (string) ($analysis['intent_code'] ?? 'general_inquiry'),
            'inquiry_id' => (int) ($inquiry['id'] ?? 0),
            'lead_snapshot' => $snapshot,
            'sources' => is_array($analysis['sources'] ?? null) ? $analysis['sources'] : [],
        ];
    }

    public function session(array $input): array
    {
        $clientId = $this->requireClientId((string) ($input['client_id'] ?? ''));
        $sessionCode = $this->requireSessionCode((string) ($input['session_code'] ?? ''));
        if (!$this->isSessionOwnedByClient($clientId, $sessionCode)) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $conversation = $this->publicChatRepository->findConversationByCode($sessionCode);
        if ($conversation === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $messages = [];
        foreach ($conversation['messages'] ?? [] as $message) {
            if (!is_array($message)) {
                continue;
            }

            $item = [
                'role' => (string) ($message['role'] ?? ''),
                'content' => (string) ($message['content'] ?? ''),
                'created_at' => (string) ($message['created_at'] ?? ''),
                'message_language' => (string) ($message['message_language'] ?? ''),
                'translated_text' => (string) ($message['translated_text'] ?? ''),
            ];

            $publicSources = $this->normalizePublicSources($message['sources'] ?? []);
            if ($publicSources !== []) {
                $item['sources'] = $publicSources;
            }

            $messages[] = $item;
        }

        $leadSnapshot = null;
        $latestSnapshot = $conversation['snapshots'][0] ?? null;
        if (is_array($latestSnapshot)) {
            $leadSnapshot = [
                'contact_name' => (string) ($latestSnapshot['contact_name'] ?? ''),
                'company_name' => (string) ($latestSnapshot['company_name'] ?? ''),
                'email' => (string) ($latestSnapshot['email'] ?? ''),
                'phone' => (string) ($latestSnapshot['phone'] ?? ''),
                'whatsapp' => (string) ($latestSnapshot['whatsapp'] ?? ''),
                'country_code' => (string) ($latestSnapshot['country_code'] ?? ''),
                'product_interest' => (string) ($latestSnapshot['product_interest'] ?? ''),
                'solution_interest' => (string) ($latestSnapshot['solution_interest'] ?? ''),
                'requirement_summary' => (string) ($latestSnapshot['requirement_summary'] ?? ''),
                'confidence_score' => (float) ($latestSnapshot['confidence_score'] ?? 0),
                'created_at' => (string) ($latestSnapshot['created_at'] ?? ''),
            ];
        }

        return [
            'session_code' => (string) ($conversation['session_code'] ?? $sessionCode),
            'inquiry_id' => (int) ($conversation['inquiry_id'] ?? 0),
            'lead_snapshot' => $leadSnapshot,
            'messages' => $messages,
        ];
    }

    private function analyzeMessage(string $message, string $languageCode, string $page, int $sessionId): array
    {
        $retrievedChunks = [];

        try {
            $config = $this->systemSettingRepository->deepseekConfig();

            try {
                $retrievedChunks = $this->knowledgeRetrievalService->retrieve($message, $languageCode, [
                    'source_page' => $page,
                    'top_k' => (int) ($config['knowledge_top_k'] ?? 5),
                    'max_chars' => (int) ($config['knowledge_max_chars'] ?? 4000),
                ]);
            } catch (\Throwable) {
                $retrievedChunks = [];
            }

            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->deepseekPrompt('chat'),
                ],
            ];

            $ragPrompt = trim($this->deepseekPrompt('chat.rag'));
            $contextPrompt = $this->knowledgeRetrievalService->buildContextPrompt($retrievedChunks);
            if ($contextPrompt !== '') {
                $messages[] = [
                    'role' => 'system',
                    'content' => ($ragPrompt !== '' ? $ragPrompt . "\n\n" : '') . $contextPrompt,
                ];
            } elseif ($ragPrompt !== '') {
                $messages[] = [
                    'role' => 'system',
                    'content' => $ragPrompt,
                ];
            }

            $historyLimit = max(0, min(20, (int) ($config['chat_max_history_messages'] ?? 6)));
            if ($sessionId > 0 && $historyLimit > 0) {
                foreach ($this->publicChatRepository->listRecentMessages($sessionId, $historyLimit) as $historyItem) {
                    $role = (string) ($historyItem['role'] ?? '');
                    $content = trim((string) ($historyItem['content'] ?? ''));
                    if ($content === '' || !in_array($role, ['user', 'assistant'], true)) {
                        continue;
                    }
                    $messages[] = [
                        'role' => $role,
                        'content' => $content,
                    ];
                }
            }

            $messages[] = [
                'role' => 'user',
                'content' => json_encode([
                    'message' => $message,
                    'language_code' => $languageCode,
                    'source_page' => $page,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $response = $this->deepSeekClient->jsonChat($messages, 'chat_enabled');

            $entities = [
                'contact_name' => $this->normalizeModelScalar($response['contact_name'] ?? ''),
                'company_name' => $this->normalizeModelScalar($response['company_name'] ?? ''),
                'email' => $this->normalizeModelScalar($response['email'] ?? ''),
                'phone' => $this->normalizeModelScalar($response['phone'] ?? ''),
                'whatsapp' => $this->normalizeModelScalar($response['whatsapp'] ?? ''),
                'country_code' => strtoupper($this->normalizeModelScalar($response['country_code'] ?? '')),
                'product_interest' => $this->normalizeModelScalar($response['product_interest'] ?? ''),
                'solution_interest' => $this->normalizeModelScalar($response['solution_interest'] ?? ''),
                'requirement_summary' => $this->normalizeModelScalar($response['requirement_summary'] ?? ''),
            ];

            return [
                'reply' => trim((string) ($response['reply'] ?? '')) !== '' ? (string) $response['reply'] : $this->buildFallbackReply($entities, $languageCode),
                'intent_code' => (string) ($response['intent_code'] ?? $this->fallbackIntentCode($message, $entities)),
                'contains_contact_info' => !empty($response['contains_contact_info']) || $this->containsContactInfo($entities),
                'entities' => $this->normalizeEntities($entities, $message, $page),
                'sources' => array_map(static fn (array $chunk): array => [
                    'title' => (string) ($chunk['title'] ?? ''),
                    'source_type' => (string) ($chunk['source_type'] ?? ''),
                    'source_id' => $chunk['source_id'] ?? null,
                    'url' => (string) ($chunk['url'] ?? ''),
                ], $retrievedChunks),
            ];
        } catch (\Throwable) {
            $entities = $this->heuristicEntities($message, $page);

            return [
                'reply' => $this->buildKnowledgeAwareFallbackReply($retrievedChunks, $entities, $languageCode),
                'intent_code' => $this->fallbackIntentCode($message, $entities),
                'contains_contact_info' => $this->containsContactInfo($entities),
                'entities' => $entities,
                'sources' => array_map(static fn (array $chunk): array => [
                    'title' => (string) ($chunk['title'] ?? ''),
                    'source_type' => (string) ($chunk['source_type'] ?? ''),
                    'source_id' => $chunk['source_id'] ?? null,
                    'url' => (string) ($chunk['url'] ?? ''),
                ], $retrievedChunks),
            ];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $chunks
     */
    private function buildKnowledgeAwareFallbackReply(array $chunks, array $entities, string $languageCode): string
    {
        if ($chunks === []) {
            return $this->buildFallbackReply($entities, $languageCode);
        }

        $isEnglish = $languageCode !== 'zh';
        $summaries = [];
        $seenTitles = [];

        foreach ($chunks as $chunk) {
            $title = trim((string) ($chunk['title'] ?? ''));
            $content = $this->extractKnowledgeExcerpt((string) ($chunk['content'] ?? ''));
            $titleKey = mb_strtolower($title);
            if ($title !== '' && isset($seenTitles[$titleKey])) {
                continue;
            }
            if ($title !== '') {
                $seenTitles[$titleKey] = true;
            }

            if ($title !== '' && $content !== '') {
                $summaries[] = $title . '：' . $content;
            } elseif ($title !== '') {
                $summaries[] = $title;
            } elseif ($content !== '') {
                $summaries[] = $content;
            }

            if (count($summaries) >= 2) {
                break;
            }
        }

        if ($summaries === []) {
            return $this->buildFallbackReply($entities, $languageCode);
        }

        if ($isEnglish) {
            return $this->buildEnglishKnowledgeFallbackReply($chunks, $entities);
        }

        return '根据现有知识库，相关设备与产线信息包括：'
            . implode('；', $summaries)
            . '。如果您需要产能配置、整线布局或报价，请继续留下邮箱或 WhatsApp。';
    }

    private function extractKnowledgeExcerpt(string $content, int $limit = 80): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $content) ?? '');
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) <= $limit) {
            return $normalized;
        }

        return rtrim(mb_substr($normalized, 0, $limit), " \t\n\r\0\x0B,.;:，。；：") . '...';
    }

    /**
     * @param array<int, array<string, mixed>> $chunks
     */
    private function buildEnglishKnowledgeFallbackReply(array $chunks, array $entities): string
    {
        $focusItems = [];
        foreach (['product_interest', 'solution_interest'] as $field) {
            $value = trim((string) ($entities[$field] ?? ''));
            if ($value === '' || preg_match('/[\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}]/u', $value) === 1) {
                continue;
            }

            $focusItems[mb_strtolower($value)] = $value;
        }

        $sourceTypes = [];
        foreach ($chunks as $chunk) {
            $sourceType = trim((string) ($chunk['source_type'] ?? ''));
            if ($sourceType === '') {
                continue;
            }

            $sourceTypes[$sourceType] = true;
        }

        $scopeParts = [];
        if (isset($sourceTypes['product'])) {
            $scopeParts[] = 'product';
        }
        if (isset($sourceTypes['solution'])) {
            $scopeParts[] = 'production-line';
        }
        if (isset($sourceTypes['article'])) {
            $scopeParts[] = 'application';
        }
        if ($scopeParts === []) {
            $scopeParts = ['product', 'production-line'];
        }

        $scope = implode(' and ', $scopeParts) . ' knowledge';
        $focus = array_values($focusItems);
        $focusClause = $focus === [] ? '' : ' for ' . implode(' and ', $focus);
        $cta = $this->primaryContactValue($entities) === ''
            ? 'If you need capacity planning, layout support, or a quotation, please share your email or WhatsApp.'
            : 'If you share your capacity target or layout constraints, I can narrow the recommendation further.';

        return 'Based on our knowledge base, I found relevant ' . $scope . $focusClause . '. ' . $cta;
    }

    private function deepseekPrompt(string $feature): string
    {
        return $this->systemSettingRepository->deepseekPrompt($feature);
    }

    private function translateMessageForStorage(string $message, string $languageCode): string
    {
        $content = trim($message);
        if ($content === '' || $languageCode === 'zh') {
            return '';
        }

        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->deepseekPrompt('translation'),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'task' => 'translate_to_chinese',
                        'target_language' => 'zh',
                        'source_language' => $languageCode,
                        'source_fields' => [
                            'translated_text' => $content,
                        ],
                        'output_rule' => 'Return JSON only with the same keys as source_fields.',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ];
            $response = $this->deepSeekClient->jsonChat($messages, 'translation_enabled');
            $translated = trim((string) ($response['translated_text'] ?? ''));

            return $translated !== '' && $translated !== $content ? $translated : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function heuristicEntities(string $message, string $page): array
    {
        $entities = [
            'contact_name' => '',
            'company_name' => '',
            'email' => '',
            'phone' => '',
            'whatsapp' => '',
            'country_code' => '',
            'product_interest' => '',
            'solution_interest' => '',
            'requirement_summary' => mb_substr(trim($message), 0, 240),
        ];

        if (preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $message, $matches) === 1) {
            $entities['email'] = $matches[1];
        }

        if (preg_match('/(\+?[0-9][0-9\s\-()]{6,20}[0-9])/', $message, $matches) === 1) {
            $entities['phone'] = preg_replace('/\s+/', ' ', trim($matches[1])) ?? $matches[1];
        }

        if (preg_match('/(?:name|contact name|联系人)\s*[:：]\s*([^\n,.;]{1,80})/iu', $message, $matches) === 1) {
            $entities['contact_name'] = trim($matches[1]);
        } elseif (preg_match('/(?:my name is|i am|this is)\s+([A-Za-z][A-Za-z\s]{1,60})/i', $message, $matches) === 1) {
            $entities['contact_name'] = trim($matches[1]);
        }

        if (preg_match('/(?:company|company name|公司)\s*[:：]\s*([^\n,.;]{2,120})/iu', $message, $matches) === 1) {
            $entities['company_name'] = trim($matches[1]);
        } elseif (preg_match('/(?:company(?: name)? is|from)\s+([^.,\n]{2,80})/i', $message, $matches) === 1) {
            $entities['company_name'] = trim($matches[1]);
        } elseif (preg_match('/([A-Za-z0-9&\s]+(?:LLC|LTD|Ltd|Inc|GmbH|Company|Co\.))/i', $message, $matches) === 1) {
            $entities['company_name'] = trim($matches[1]);
        }

        $countryMap = [
            'uae' => 'AE',
            'united arab emirates' => 'AE',
            'germany' => 'DE',
            'indonesia' => 'ID',
            'brazil' => 'BR',
            'mexico' => 'MX',
            'china' => 'CN',
            'saudi' => 'SA',
        ];
        foreach ($countryMap as $keyword => $countryCode) {
            if (stripos($message, $keyword) !== false) {
                $entities['country_code'] = $countryCode;
                break;
            }
        }

        $interest = $this->detectInterest($message . ' ' . $page);
        $entities['product_interest'] = $interest['product_interest'];
        $entities['solution_interest'] = $interest['solution_interest'];

        return $entities;
    }

    private function detectInterest(string $text): array
    {
        $normalized = strtolower($text);
        $productInterest = '';
        $solutionInterest = '';

        if (str_contains($normalized, 'cake')) {
            $productInterest = 'Cake equipment';
            $solutionInterest = 'Cake production line';
        } elseif (str_contains($normalized, 'bread')) {
            $productInterest = 'Bread equipment';
            $solutionInterest = 'Bread production line';
        } elseif (str_contains($normalized, 'biscuit') || str_contains($normalized, 'cookie')) {
            $productInterest = 'Biscuit equipment';
            $solutionInterest = 'Biscuit production line';
        } elseif (str_contains($normalized, 'fry')) {
            $productInterest = 'Fried food equipment';
            $solutionInterest = 'Fried food production line';
        } elseif (str_contains($normalized, 'depositor')) {
            $productInterest = 'Cake depositor';
            $solutionInterest = 'Cake production line';
        }

        return [
            'product_interest' => $productInterest,
            'solution_interest' => $solutionInterest,
        ];
    }

    private function mergeSnapshot(array $existing, array $entities, string $message): array
    {
        $merged = [
            'contact_name' => $this->pickText($entities['contact_name'] ?? '', $existing['contact_name'] ?? ''),
            'company_name' => $this->pickText($entities['company_name'] ?? '', $existing['company_name'] ?? ''),
            'email' => $this->pickText($entities['email'] ?? '', $existing['email'] ?? ''),
            'phone' => $this->pickText($entities['phone'] ?? '', $existing['phone'] ?? ''),
            'whatsapp' => $this->pickText($entities['whatsapp'] ?? '', $existing['whatsapp'] ?? ''),
            'country_code' => strtoupper($this->pickText($entities['country_code'] ?? '', $existing['country_code'] ?? '')),
            'product_interest' => $this->pickText($entities['product_interest'] ?? '', $existing['product_interest'] ?? ''),
            'solution_interest' => $this->pickText($entities['solution_interest'] ?? '', $existing['solution_interest'] ?? ''),
            'requirement_summary' => $this->pickText($entities['requirement_summary'] ?? '', $existing['requirement_summary'] ?? ''),
        ];

        if ($merged['requirement_summary'] === '') {
            $merged['requirement_summary'] = mb_substr(trim($message), 0, 240);
        }

        return $merged;
    }

    private function calculateConfidenceScore(array $snapshot): float
    {
        $score = 35.0;
        if ($snapshot['email'] !== '') {
            $score += 25;
        }
        if ($snapshot['phone'] !== '' || $snapshot['whatsapp'] !== '') {
            $score += 15;
        }
        if ($snapshot['company_name'] !== '') {
            $score += 10;
        }
        if ($snapshot['product_interest'] !== '' || $snapshot['solution_interest'] !== '') {
            $score += 10;
        }
        if ($snapshot['country_code'] !== '') {
            $score += 5;
        }

        return min($score, 95.0);
    }

    private function shouldCreateInquiry(array $snapshot): bool
    {
        return $this->primaryContactValue($snapshot) !== ''
            && (
                trim((string) ($snapshot['company_name'] ?? '')) !== ''
                || trim((string) ($snapshot['product_interest'] ?? '')) !== ''
                || trim((string) ($snapshot['solution_interest'] ?? '')) !== ''
                || trim((string) ($snapshot['country_code'] ?? '')) !== ''
            );
    }

    private function hasSnapshotValue(array $snapshot): bool
    {
        foreach (['contact_name', 'company_name', 'email', 'phone', 'whatsapp', 'country_code', 'product_interest', 'solution_interest', 'requirement_summary'] as $field) {
            if (trim((string) ($snapshot[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function buildFallbackReply(array $entities, string $languageCode): string
    {
        $isEnglish = $languageCode !== 'zh';
        if ($this->primaryContactValue($entities) === '') {
            return $isEnglish
                ? 'Please share your email or WhatsApp, and tell me which bakery product or line you need.'
                : '请留下您的邮箱或 WhatsApp，并告诉我您需要哪类烘焙设备或整线方案。';
        }

        if (trim((string) ($entities['product_interest'] ?? '')) === '' && trim((string) ($entities['solution_interest'] ?? '')) === '') {
            return $isEnglish
                ? 'Thank you. Which product or production line are you interested in, such as cake, bread, biscuit, or frying equipment?'
                : '感谢您的信息。请再告诉我您感兴趣的是蛋糕、面包、饼干，还是油炸类设备或整线方案。';
        }

        if (trim((string) ($entities['country_code'] ?? '')) === '') {
            return $isEnglish
                ? 'Got it. Please also share your country or project location so we can prepare the right proposal.'
                : '已收到。请再提供您的国家或项目所在地，便于我们准备更准确的方案。';
        }

        return $isEnglish
            ? 'Thanks. I have recorded your request and created an inquiry. Our team will follow up with a suitable proposal soon.'
            : '好的，您的需求已记录并生成询盘，我们会尽快安排合适的方案跟进您。';
    }

    private function fallbackIntentCode(string $message, array $entities): string
    {
        if ($this->containsContactInfo($entities)) {
            return 'lead_capture';
        }

        $normalized = strtolower($message);
        if (str_contains($normalized, 'price') || str_contains($normalized, 'quote')) {
            return 'quotation';
        }
        if (str_contains($normalized, 'line') || str_contains($normalized, 'machine') || str_contains($normalized, 'equipment')) {
            return 'product_consulting';
        }

        return 'general_inquiry';
    }

    private function normalizeEntities(array $entities, string $message, string $page): array
    {
        $heuristic = $this->heuristicEntities($message, $page);
        foreach ($heuristic as $field => $value) {
            if ($this->normalizeModelScalar($entities[$field] ?? '') === '') {
                $entities[$field] = $value;
            }
        }

        foreach ($entities as $field => $value) {
            $entities[$field] = $this->normalizeModelScalar($value);
        }

        $entities['country_code'] = strtoupper((string) ($entities['country_code'] ?? ''));
        return $entities;
    }

    private function containsContactInfo(array $entities): bool
    {
        return $this->primaryContactValue($entities) !== '';
    }

    private function primaryContactValue(array $entities): string
    {
        foreach (['email', 'phone', 'whatsapp'] as $field) {
            $value = trim((string) ($entities[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveSessionCode(string $clientId, string $incomingSessionCode): string
    {
        $incomingSessionCode = trim($incomingSessionCode);
        if ($incomingSessionCode !== '') {
            return $incomingSessionCode;
        }

        return $this->generateSessionCode($clientId);
    }

    private function generateSessionCode(string $clientId): string
    {
        $clientHash = substr(sha1($clientId), 0, 8);

        try {
            $random = substr(bin2hex(random_bytes(8)), 0, 16);
        } catch (\Throwable) {
            $random = substr(sha1($clientId . '|' . microtime(true) . '|' . mt_rand()), 0, 16);
        }

        return 'web-' . $clientHash . '-' . $random;
    }

    private function requireSessionCode(string $sessionCode): string
    {
        $sessionCode = trim($sessionCode);
        if ($sessionCode === '') {
            throw new BusinessException('session_code required', ErrorCode::INVALID_PARAMS);
        }

        return $sessionCode;
    }

    private function isSessionOwnedByClient(string $clientId, string $sessionCode): bool
    {
        return str_starts_with(trim($sessionCode), 'web-' . substr(sha1($clientId), 0, 8) . '-');
    }

    private function requireClientId(string $clientId): string
    {
        $clientId = trim($clientId);
        if ($clientId === '') {
            throw new BusinessException('client_id required', ErrorCode::INVALID_PARAMS);
        }

        return $clientId;
    }

    private function requirePage(string $page): string
    {
        $page = trim($page);
        if ($page === '') {
            throw new BusinessException('path required', ErrorCode::INVALID_PARAMS);
        }

        return $page;
    }

    private function normalizeLanguage(string $languageCode): string
    {
        $languageCode = strtolower(trim($languageCode));
        if ($languageCode === '') {
            return 'en';
        }

        return match ($languageCode) {
            'zh-cn', 'zh-hans' => 'zh',
            default => substr($languageCode, 0, 2),
        };
    }

    private function detectDeviceType(): string
    {
        $userAgent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($userAgent === '') {
            return 'unknown';
        }

        if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android') || str_contains($userAgent, 'iphone')) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function pickText(mixed $preferred, mixed $fallback): string
    {
        $preferred = trim((string) $preferred);
        return $preferred !== '' ? $preferred : trim((string) $fallback);
    }

    private function normalizeModelScalar(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $normalized = $this->normalizeModelScalar($item);
                if ($normalized !== '') {
                    $parts[] = $normalized;
                }
            }

            return implode(' / ', array_values(array_unique($parts)));
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * @param mixed $sources
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStoredSources(mixed $sources): array
    {
        if (!is_array($sources)) {
            return [];
        }

        $normalized = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            $title = trim((string) ($source['title'] ?? ''));
            $sourceType = trim((string) ($source['source_type'] ?? ''));
            $sourceId = $source['source_id'] ?? null;
            $url = trim((string) ($source['url'] ?? ''));
            if ($title === '' && $sourceType === '' && $url === '' && ($sourceId === null || $sourceId === '')) {
                continue;
            }

            $normalized[] = [
                'title' => $title,
                'source_type' => $sourceType,
                'source_id' => is_numeric($sourceId) ? (int) $sourceId : null,
                'url' => $url,
            ];
        }

        return $normalized;
    }

    /**
     * @param mixed $sources
     * @return array<int, array<string, string>>
     */
    private function normalizePublicSources(mixed $sources): array
    {
        if (!is_array($sources)) {
            return [];
        }

        $normalized = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            $title = trim((string) ($source['title'] ?? ''));
            $sourceType = trim((string) ($source['source_type'] ?? ''));
            if ($title === '' && $sourceType === '') {
                continue;
            }

            $normalized[] = [
                'title' => $title,
                'source_type' => $sourceType,
            ];
        }

        return $normalized;
    }
}
