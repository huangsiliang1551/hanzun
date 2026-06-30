<?php

declare(strict_types=1);

namespace app\service\translation;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\LanguageRepository;
use app\repository\SystemSettingRepository;
use app\repository\TranslationRepository;
use app\service\ai\DeepSeekClient;
use app\service\ai\PromptComposer;
use app\service\content\ContentEntityBridge;
use app\service\log\OperationLogService;

final class TranslationService
{
    public function __construct(
        private readonly TranslationRepository $translationRepository = new TranslationRepository(),
        private readonly ContentEntityBridge $contentEntityBridge = new ContentEntityBridge(),
        private readonly LanguageRepository $languageRepository = new LanguageRepository(),
        private readonly DeepSeekClient $deepSeekClient = new DeepSeekClient(),
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository(),
        private readonly OperationLogService $operationLogService = new OperationLogService()
    ) {
    }

    public function jobs(): array
    {
        $languageMap = $this->languageNameMap();
        $items = [];

        // Flatten entity-level jobs (JSON language_details) into per-language rows for the UI
        foreach ($this->translationRepository->list() as $job) {
            $details = $job['language_details'] ?? [];
            if (!is_array($details)) continue;

            foreach ($details as $code => $info) {
                $items[] = [
                    'id' => (int)($job['id'] ?? 0),
                    'entity_type' => (string)($job['entity_type'] ?? ''),
                    'entity_id' => (int)($job['entity_id'] ?? 0),
                    'language_code' => $code,
                    'status' => $info['status'] ?? 'pending',
                    'retry_count' => 0,
                    'error_message' => $info['error'] ?? null,
                    'created_at' => (string)($job['created_at'] ?? ''),
                    'updated_at' => (string)($job['updated_at'] ?? ''),
                    // Pass entity-level aggregate
                    'completed_languages' => (int)($job['completed_languages'] ?? 0),
                    'total_languages' => (int)($job['total_languages'] ?? 0),
                    'failed_languages' => (int)($job['failed_languages'] ?? 0),
                ];
            }
        }

        $decorated = array_map(
            fn(array $item): array => $this->decorateJob($item, $languageMap),
            $items
        );

        return [
            'items' => $decorated,
            'status_options' => ['pending', 'processing', 'completed', 'review_required', 'failed'],
            'summary' => $this->buildSummary($items),
        ];
    }

    public function retry(int $id): array
    {
        $job = $this->translationRepository->find($id);
        if ($job === null) {
            throw new BusinessException('翻译任务不存在', ErrorCode::NOT_FOUND);
        }

        $this->executePendingEntityJobs(
            (string)($job['entity_type'] ?? ''),
            (int)($job['entity_id'] ?? 0)
        );

        return $this->translationRepository->find($id) ?? [];
    }

    public function approve(int $id): array
    {
        $job = $this->translationRepository->find($id);
        if ($job === null) {
            throw new BusinessException('翻译任务不存在', ErrorCode::NOT_FOUND);
        }

        $entityType = (string) ($job['entity_type'] ?? '');
        $entityId = (int) ($job['entity_id'] ?? 0);
        $languageCode = (string) ($job['language_code'] ?? '');
        if ($languageCode === '' || $languageCode === 'zh') {
            throw new BusinessException('目标语言无效，不能为中文或空值', ErrorCode::INVALID_PARAMS);
        }

        $translation = $this->contentEntityBridge->translationRecord($entityType, $entityId, $languageCode);
        if ($translation === null) {
            throw new BusinessException('翻译记录不存在', ErrorCode::NOT_FOUND);
        }

        $this->contentEntityBridge->upsertTranslation($entityType, $entityId, $languageCode, $translation, 'completed');
        $updated = $this->translationRepository->updateStatus($id, 'completed', null);
        $this->syncEntityTranslationStatus($entityType, $entityId);
        $this->operationLogService->recordCurrentAction('translation', 'translation.approve', 'translation_job', $updated, '译文审核已通过');

        return $updated ?? [];
    }

    public function update(int $id, array $input): array
    {
        $job = $this->translationRepository->find($id);
        if ($job === null) {
            throw new BusinessException('翻译任务不存在', ErrorCode::NOT_FOUND);
        }

        $entityType = (string) ($job['entity_type'] ?? '');
        $entityId = (int) ($job['entity_id'] ?? 0);
        $languageCode = (string) ($job['language_code'] ?? '');
        if ($languageCode === '' || $languageCode === 'zh') {
            throw new BusinessException('目标语言无效，不能为中文或空值', ErrorCode::INVALID_PARAMS);
        }
        if (!$this->contentEntityBridge->supports($entityType)) {
            throw new BusinessException('当前内容类型不支持翻译', ErrorCode::INVALID_PARAMS);
        }

        $record = $this->contentEntityBridge->find($entityType, $entityId);
        if ($record === null) {
            throw new BusinessException('源内容不存在', ErrorCode::NOT_FOUND);
        }

        $sourceFields = $this->contentEntityBridge->translationSource($entityType, $record);
        if ($sourceFields === []) {
            throw new BusinessException('没有可翻译的源字段', ErrorCode::INVALID_PARAMS);
        }

        $existingTranslation = $this->contentEntityBridge->translationRecord($entityType, $entityId, $languageCode) ?? [];
        $incomingFields = $input['translated_fields'] ?? null;
        if (!is_array($incomingFields)) {
            throw new BusinessException('translated_fields 参数无效', ErrorCode::INVALID_PARAMS);
        }

        $payload = [];
        foreach (array_keys($sourceFields) as $field) {
            $value = $incomingFields[$field] ?? ($existingTranslation[$field] ?? '');
            $payload[$field] = trim((string) $value);
        }

        $this->contentEntityBridge->upsertTranslation($entityType, $entityId, $languageCode, $payload, 'review_required');
        $updated = $this->translationRepository->updateStatus($id, 'review_required', null);
        $this->syncEntityTranslationStatus($entityType, $entityId);
        $this->operationLogService->recordCurrentAction('translation', 'translation.update', 'translation_job', $updated, '译文内容已更新');

        return $updated ?? [];
    }

    public function entityJobs(string $entityType, int $entityId): array
    {
        $languageMap = $this->languageNameMap();
        $items = array_map(
            fn (array $item): array => $this->decorateJob($item, $languageMap),
            $this->translationRepository->findByEntityAll($entityType, $entityId)
        );

        return [
            'items' => $items,
            'summary' => $this->buildSummary($items),
        ];
    }

    public function triggerEntity(string $entityType, int $entityId): void
    {
        $pipeline = new SharedTranslationPipelineService(
            $this->languageRepository,
            $this->translationRepository,
            new \app\repository\SystemSettingRepository(),
            $this
        );
        $pipeline->syncEntity($entityType, $entityId);
    }

    public function executePendingEntityJobs(string $entityType, int $entityId): void
    {
        if ($entityType === '' || $entityId <= 0 || !$this->contentEntityBridge->supports($entityType)) {
            return;
        }

        $job = $this->translationRepository->findEntityJob($entityType, $entityId);
        if ($job === null) {
            return;
        }

        $jobStatus = (string) ($job['status'] ?? '');
        if (!in_array($jobStatus, ['pending', 'failed', 'processing'], true)) {
            return;
        }

        if ($jobStatus === 'processing' && !$this->isRecoverableStaleEntityJob($job, 'TRANSLATION_STALE_JOB_SECONDS', 900)) {
            return;
        }

        $jobId = (int)($job['id'] ?? 0);
        $details = $job['language_details'] ?? [];
        if (!is_array($details)) $details = [];

        // Collect pending languages from JSON column
        $pendingCodes = [];
        foreach ($details as $code => $info) {
            $s = $info['status'] ?? 'pending';
            if ($s === 'pending' || $s === 'failed') {
                $pendingCodes[] = $code;
            }
        }
        if ($pendingCodes === []) return;

        $record = $this->contentEntityBridge->find($entityType, $entityId);
        if ($record === null) return;

        // Mark entity job as processing
        $this->translationRepository->updateEntityStatus($jobId, 'processing');

        // Batch translate ALL languages in ONE API request
        $batchResults = $this->batchTranslateRecord($entityType, $record, $pendingCodes);

        // Write results and update per-language status in JSON
        $allCompleted = true;
        $errors = [];
        foreach ($pendingCodes as $languageCode) {
            try {
                $translated = $batchResults[$languageCode] ?? null;
                if ($translated === null || $translated === []) {
                    throw new BusinessException('AI 返回结果缺少 translated_fields 数据');
                }
                $this->contentEntityBridge->upsertTranslation($entityType, $entityId, $languageCode, $translated, 'completed');
                $this->translationRepository->updateLanguageStatus($jobId, $languageCode, 'completed');
            } catch (\Throwable $exception) {
                $this->translationRepository->updateLanguageStatus($jobId, $languageCode, 'failed', $exception->getMessage());
                $errors[] = $languageCode . ': ' . $exception->getMessage();
                $allCompleted = false;
            }
        }

        // Set final entity status
        $finalStatus = $allCompleted ? 'completed' : (count($errors) === count($pendingCodes) ? 'failed' : 'completed');
        $this->translationRepository->updateEntityStatus($jobId, $finalStatus, $errors ? implode("\n", $errors) : null);
        $this->syncEntityTranslationStatus($entityType, $entityId);
    }

    private function isRecoverableStaleEntityJob(array $job, string $envKey, int $defaultSeconds): bool
    {
        $timeout = max(60, (int) env($envKey, (string) $defaultSeconds));
        $updatedAt = strtotime((string) ($job['updated_at'] ?? $job['created_at'] ?? ''));
        if ($updatedAt <= 0) {
            return true;
        }

        return (time() - $updatedAt) >= $timeout;
    }

    /**
     * @return array<string, string>
     */
    private function translateRecord(string $entityType, array $record, string $languageCode): array
    {
        $source = $this->contentEntityBridge->translationSource($entityType, $record);
        if ($source === []) {
            throw new BusinessException('没有可翻译的源字段', ErrorCode::INVALID_PARAMS);
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $this->deepseekPrompt('translation'),
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'task' => 'translate',
                    'entity_type' => $entityType,
                    'target_language' => $languageCode,
                    'source_fields' => $source,
                    'output_keys' => array_values(array_keys($source)),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];
        $response = $this->deepSeekClient->jsonChat($messages, 'translation_enabled');

        $translated = [];
        foreach (array_keys($source) as $field) {
            $translated[$field] = (string) ($response[$field] ?? '');
        }

        return $translated;
    }

    /**
     * Batch translate: send all target languages in ONE API request.
     * @param array<int, string> $languageCodes
     * @return array<string, array<string, string>> language_code => translated_fields
     */
    private function batchTranslateRecord(string $entityType, array $record, array $languageCodes): array
    {
        $source = $this->contentEntityBridge->translationSource($entityType, $record);
        if ($source === []) {
            return [];
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $this->deepseekPrompt('translation') . "\n\n"
                    . '你必须一次性完成所有目标语言的翻译。'
                    . '返回一个 JSON 对象，键为语言代码，值为对应语言的字段对象。'
                    . '每个字段对象必须与 source_fields 保持相同字段名。',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'task' => 'batch_translate',
                    'entity_type' => $entityType,
                    'target_languages' => $languageCodes,
                    'source_fields' => $source,
                    'output_keys' => array_values(array_keys($source)),
                    'language_count' => count($languageCodes),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];

        $response = $this->deepSeekClient->jsonChat($messages, 'translation_enabled');
        if (!is_array($response)) {
            return [];
        }

        $results = [];
        foreach ($languageCodes as $code) {
            $fields = $response[$code] ?? null;
            if (is_array($fields)) {
                $translated = [];
                foreach (array_keys($source) as $field) {
                    $translated[$field] = (string) ($fields[$field] ?? '');
                }
                $results[$code] = $translated;
            }
        }

        return $results;
    }

    private function deepseekPrompt(string $feature): string
    {
        if ($feature === 'translation') {
            return (new PromptComposer())->composeTranslationSystemPrompt();
        }

        return $this->systemSettingRepository->deepseekPrompt($feature);
    }

    private function syncEntityTranslationStatus(string $entityType, int $entityId): void
    {
        if ($entityType === '' || $entityId <= 0) {
            return;
        }

        $targetLanguages = [];
        foreach ($this->languageRepository->list() as $language) {
            if ((int) ($language['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $code = (string) ($language['code'] ?? '');
            if ($code !== '' && $code !== 'zh') {
                $targetLanguages[] = $code;
            }
        }

        if ($targetLanguages === []) {
            $this->contentEntityBridge->updateTranslationStatus($entityType, $entityId, 'completed');
            return;
        }

        $hasPending = false;
        $hasProcessing = false;
        $hasReviewRequired = false;
        $hasFailed = false;
        foreach ($targetLanguages as $languageCode) {
            $job = $this->translationRepository->findByEntity($entityType, $entityId, $languageCode);
            $jobStatus = (string) ($job['status'] ?? 'pending');

            $hasReviewRequired = $hasReviewRequired || $jobStatus === 'review_required';
            $hasProcessing = $hasProcessing || $jobStatus === 'processing';
            $hasFailed = $hasFailed || $jobStatus === 'failed';
            $hasPending = $hasPending || !in_array($jobStatus, ['completed', 'review_required', 'processing', 'failed'], true);
        }

        $status = 'completed';
        if ($hasReviewRequired) {
            $status = 'review_required';
        } elseif ($hasProcessing) {
            $status = 'processing';
        } elseif ($hasPending) {
            $status = 'pending';
        } elseif ($hasFailed) {
            $status = 'failed';
        }

        $this->contentEntityBridge->updateTranslationStatus($entityType, $entityId, $status);
    }

    private function decorateJob(array $job, array $languageMap): array
    {
        $entityType = (string) ($job['entity_type'] ?? '');
        $entityId = (int) ($job['entity_id'] ?? 0);
        $languageCode = (string) ($job['language_code'] ?? '');
        $record = $this->contentEntityBridge->find($entityType, $entityId) ?? [];
        $sourceFields = $this->contentEntityBridge->translationSource($entityType, $record);
        $translatedFields = $this->contentEntityBridge->translationRecord($entityType, $entityId, $languageCode) ?? [];

        // Filter translated_fields to only include content fields that match source_fields
        // (excludes metadata columns like product_id, language_code, translation_status)
        $contentFieldKeys = array_keys($sourceFields);
        $filteredTranslatedFields = [];
        foreach ($contentFieldKeys as $key) {
            if (array_key_exists($key, $translatedFields)) {
                $filteredTranslatedFields[$key] = $translatedFields[$key];
            }
        }

        return array_merge($job, [
            'entity_label' => $this->entityLabel($entityType),
            'language_name' => $languageMap[$languageCode] ?? strtoupper($languageCode),
            'source_title' => $this->titleFromFields($entityType, $sourceFields),
            'source_excerpt' => $this->excerptFromFields($sourceFields),
            'source_fields' => $sourceFields,
            'translated_fields' => $filteredTranslatedFields,
            'translated_title' => $this->titleFromFields($entityType, $filteredTranslatedFields),
            'translated_excerpt' => $this->excerptFromFields($filteredTranslatedFields),
        ]);
    }

    private function buildSummary(array $items): array
    {
        $summary = [
            'total' => count($items),
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'review_required' => 0,
            'failed' => 0,
        ];

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'pending');
            if (array_key_exists($status, $summary)) {
                $summary[$status] += 1;
            }
        }

        return $summary;
    }

    private function languageNameMap(): array
    {
        $map = [];
        foreach ($this->languageRepository->list() as $language) {
            $code = (string) ($language['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $map[$code] = (string) ($language['name'] ?? strtoupper($code));
        }

        return $map;
    }

    private function entityLabel(string $entityType): string
    {
        return match ($entityType) {
            'product' => '产品',
            'solution' => '方案',
            'news' => '新闻',
            'case' => '案例',
            'article' => '文章',
            'page' => '单页/专题页',
            'team_member' => '团队成员',
            'certificate' => '证书',
            'navigation_menu' => '导航菜单',
            'navigation_item' => '导航项',
            'contact_field_type' => '联系方式类型',
            'contact_item' => '联系方式',
            'about_page' => '企业介绍页面',
            'about_block' => '企业介绍模块',
            'homepage_section' => '首页配置模块',
            default => $entityType,
        };
    }

    private function titleFromFields(string $entityType, array $fields): string
    {
        if ($fields === []) {
            return '';
        }

        $candidates = match ($entityType) {
            'article', 'news', 'case', 'page', 'about_block', 'homepage_section' => ['title', 'name', 'label'],
            'contact_item' => ['label', 'name', 'title'],
            default => ['name', 'title', 'label'],
        };

        foreach ($candidates as $field) {
            $value = trim((string) ($fields[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function excerptFromFields(array $fields): string
    {
        $summary = trim((string) ($fields['summary'] ?? ''));
        if ($summary !== '') {
            return $summary;
        }

        $content = trim(strip_tags((string) ($fields['content'] ?? '')));
        if ($content !== '') {
            return mb_substr($content, 0, 120);
        }

        $description = trim((string) ($fields['description'] ?? ''));
        if ($description !== '') {
            return mb_substr($description, 0, 120);
        }

        return '';
    }
}

