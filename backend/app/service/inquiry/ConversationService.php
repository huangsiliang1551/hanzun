<?php

declare(strict_types=1);

namespace app\service\inquiry;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\ConversationRepository;
use app\repository\InquiryRepository;

final class ConversationService
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository = new ConversationRepository(),
        private readonly InquiryRepository $inquiryRepository = new InquiryRepository()
    )
    {
    }

    public function list(): array
    {
        return [
            'items' => array_map(fn (array $item): array => $this->normalizeConversationListItem($item), $this->conversationRepository->listConversations()),
        ];
    }

    public function detail(int $sessionId): array
    {
        $conversation = $this->conversationRepository->findConversationBySessionId($sessionId);
        if ($conversation === null) {
            throw new BusinessException('会话不存在', ErrorCode::NOT_FOUND);
        }

        $browseTraces = $this->conversationRepository->listVisitorEvents((string) ($conversation['session_code'] ?? ''));
        $inquirySummary = null;
        $inquiryId = (int) ($conversation['inquiry_id'] ?? 0);
        if ($inquiryId > 0) {
            $inquiry = $this->inquiryRepository->findInquiry($inquiryId);
            if ($inquiry !== null) {
                $inquirySummary = $this->normalizeInquirySummary($inquiry);
            }
        }

        return [
            'summary' => [
                'session_id' => (int) ($conversation['session_id'] ?? 0),
                'session_code' => (string) ($conversation['session_code'] ?? ''),
                'source' => (string) ($conversation['source'] ?? 'ai'),
                'source_page' => (string) ($conversation['source_page'] ?? ''),
                'language' => (string) (($conversation['resolved_language'] ?? '') !== '' ? $conversation['resolved_language'] : ($conversation['entry_language'] ?? '')),
                'entry_language' => (string) ($conversation['entry_language'] ?? ''),
                'resolved_language' => (string) ($conversation['resolved_language'] ?? ''),
                'device' => (string) ($conversation['device_type'] ?? ''),
                'device_type' => (string) ($conversation['device_type'] ?? ''),
                'country' => (string) ($conversation['country_code'] ?? ''),
                'country_code' => (string) ($conversation['country_code'] ?? ''),
                'utm_source' => (string) ($conversation['utm_source'] ?? ''),
                'is_valid_conversation' => (int) ($conversation['is_valid_conversation'] ?? 0),
                'inquiry_id' => $inquiryId,
                'message_count' => (int) ($conversation['message_count'] ?? 0),
                'snapshot_count' => (int) ($conversation['snapshot_count'] ?? 0),
                'last_message_at' => $conversation['last_message_at'] ?? null,
                'created_at' => $conversation['created_at'] ?? null,
                'updated_at' => $conversation['updated_at'] ?? null,
                'archive_status' => $this->normalizeArchiveStatus($conversation['archive_status'] ?? null),
            ],
            'chat_messages' => $this->normalizeRows($conversation['messages'] ?? [], ['role', 'content', 'created_at', 'message_language', 'translated_text', 'intent_code', 'contains_contact_info', 'extracted_entities_json', 'sources']),
            'snapshots' => $this->normalizeRows($conversation['snapshots'] ?? [], ['snapshot_version', 'contact_name', 'company_name', 'email', 'phone', 'whatsapp', 'country_code', 'product_interest', 'solution_interest', 'requirement_summary', 'confidence_score', 'created_at']),
            'browse_traces' => $this->normalizeRows($browseTraces, ['page', 'title', 'referrer', 'visited_at', 'language_code']),
            'inquiry_summary' => $inquirySummary,
        ];
    }

    private function normalizeConversationListItem(array $conversation): array
    {
        $conversation['language'] = (string) (($conversation['resolved_language'] ?? '') !== '' ? $conversation['resolved_language'] : ($conversation['entry_language'] ?? ''));
        $conversation['device'] = (string) ($conversation['device_type'] ?? '');
        $conversation['country'] = (string) ($conversation['country_code'] ?? '');
        $conversation['archive_status'] = $this->normalizeArchiveStatus($conversation['archive_status'] ?? null);

        return $conversation;
    }

    private function normalizeInquirySummary(array $inquiry): array
    {
        return [
            'id' => (int) ($inquiry['id'] ?? 0),
            'session_id' => (int) ($inquiry['session_id'] ?? 0),
            'source' => (string) ($inquiry['source'] ?? ''),
            'status' => (string) ($inquiry['status'] ?? ''),
            'customer_name' => (string) ($inquiry['customer_name'] ?? ''),
            'company_name' => (string) ($inquiry['company_name'] ?? ''),
            'country_code' => (string) ($inquiry['country_code'] ?? ''),
            'language_code' => (string) ($inquiry['language_code'] ?? ''),
            'primary_contact_type' => (string) ($inquiry['primary_contact_type'] ?? ''),
            'primary_contact_value' => (string) ($inquiry['primary_contact_value'] ?? ''),
            'product_interest' => (string) ($inquiry['product_interest'] ?? ''),
            'solution_interest' => (string) ($inquiry['solution_interest'] ?? ''),
            'requirement_summary' => (string) ($inquiry['requirement_summary'] ?? ''),
            'inquiry_score' => $inquiry['inquiry_score'] ?? null,
            'assigned_to' => $inquiry['assigned_to'] ?? null,
            'first_response_at' => $inquiry['first_response_at'] ?? null,
            'created_at' => $inquiry['created_at'] ?? null,
            'updated_at' => $inquiry['updated_at'] ?? null,
            'archive_status' => $this->normalizeArchiveStatus($inquiry['archive_status'] ?? null),
        ];
    }

    private function normalizeArchiveStatus(mixed $value): string
    {
        $archiveStatus = trim((string) $value);

        return $archiveStatus !== '' ? $archiveStatus : 'active';
    }

    /**
     * @param array<int, mixed> $rows
     * @param array<int, string> $fields
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(array $rows, array $fields): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $item = [];
            foreach ($fields as $field) {
                $item[$field] = $row[$field] ?? null;
            }
            $normalized[] = $item;
        }

        return $normalized;
    }

    public function batchTranslate(int $sessionId): array
    {
        if ($sessionId <= 0) {
            throw new BusinessException('session_id ??', ErrorCode::INVALID_PARAMS);
        }

        $conversation = $this->conversationRepository->findConversationBySessionId($sessionId);
        if ($conversation === []) {
            throw new BusinessException('会话不存在', ErrorCode::NOT_FOUND);
        }

        $messages = $conversation['messages'] ?? [];
        if ($messages === []) {
            return ['translated' => 0, 'messages' => []];
        }

        // Find untranslated messages
        $untranslated = [];
        foreach ($messages as $index => $msg) {
            $translated = trim((string) ($msg['translated_text'] ?? ''));
            if ($translated === '' && trim((string) ($msg['content'] ?? '')) !== '') {
                $untranslated[$index] = $msg;
            }
        }

        if ($untranslated === []) {
            return ['translated' => 0, 'messages' => $messages];
        }

        // Batch translate via DeepSeek/dashscope
        $settingRepo = new \app\repository\SystemSettingRepository();
        $config = $settingRepo->deepseekConfig();
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://dashscope.aliyuncs.com/compatible-mode/v1'), '/');
        $model = (string) ($config['model'] ?? 'qwen-plus');
        $timeout = (int) ($config['timeout_seconds'] ?? 90);

        $textsToTranslate = array_map(
            fn (array $m): string => trim((string) ($m['content'] ?? '')),
            $untranslated
        );

        $composer = new \app\service\ai\PromptComposer();
        $combined = implode("\n---\n", $textsToTranslate);
        $systemPrompt = $composer->composeTranslationSystemPrompt();
        $userPrompt = "Translate the following messages to Chinese (Simplified). Return each translation separated by '---' with no extra text:\n\n" . $combined;

        $responseText = $this->translateViaCurl($baseUrl, $apiKey, $model, $systemPrompt, $userPrompt, $timeout);
        $translatedTexts = array_map('trim', explode('---', trim($responseText)));

        // Update database
        $indexes = array_keys($untranslated);

        foreach ($translatedTexts as $i => $translated) {
            if (!isset($indexes[$i])) break;
            $origIndex = $indexes[$i];
            $this->conversationRepository->updateMessageTranslation(
                (int) ($untranslated[$origIndex]['id'] ?? 0),
                $translated
            );
            $messages[$origIndex]['translated_text'] = $translated;
        }

        return [
            'translated' => count($indexes),
            'messages' => $this->normalizeRows($messages, ['role', 'content', 'created_at', 'message_language', 'translated_text', 'intent_code', 'contains_contact_info', 'extracted_entities_json', 'sources']),
        ];
    }

    private function translateViaCurl(string $baseUrl, string $apiKey, string $model, string $systemPrompt, string $userPrompt, int $timeout): string
    {
        $ch = curl_init($baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.2,
            ]),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            throw new BusinessException('会话消息发送失败：' . $err, ErrorCode::INTERNAL_ERROR);
        }

        $decoded = json_decode($raw, true);
        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new BusinessException('会话消息发送失败', ErrorCode::INTERNAL_ERROR);
        }

        return trim($content);
    }
}
