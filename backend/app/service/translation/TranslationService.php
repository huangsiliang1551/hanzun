<?php

declare(strict_types=1);

namespace app\service\translation;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\LanguageRepository;
use app\repository\SystemSettingRepository;
use app\repository\TranslationRepository;
use app\service\ai\DeepSeekClient;
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
        $items = array_map(
            fn (array $item): array => $this->decorateJob($item, $languageMap),
            $this->translationRepository->list()
        );

        return [
            'items' => $items,
            'status_options' => ['pending', 'processing', 'completed', 'review_required', 'failed'],
            'summary' => $this->buildSummary($items),
        ];
    }

    public function retry(int $id): array
    {
        $job = $this->translationRepository->find($id);
        if ($job === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        try {
            $entityType = (string) ($job['entity_type'] ?? '');
            $entityId = (int) ($job['entity_id'] ?? 0);
            $languageCode = (string) ($job['language_code'] ?? '');
            if ($languageCode === '' || $languageCode === 'zh') {
                throw new BusinessException('invalid translation language', ErrorCode::INVALID_PARAMS);
            }
            if (!$this->contentEntityBridge->supports($entityType)) {
                throw new BusinessException('unsupported entity type', ErrorCode::INVALID_PARAMS);
            }

            $record = $this->contentEntityBridge->find($entityType, $entityId);
            if ($record === null) {
                throw new BusinessException('source record not found', ErrorCode::NOT_FOUND);
            }

            $this->translationRepository->updateStatus($id, 'processing', null, true);
            $this->contentEntityBridge->updateTranslationStatus($entityType, $entityId, 'processing');

            $payload = $this->translateRecord($entityType, $record, $languageCode);
            $this->contentEntityBridge->upsertTranslation($entityType, $entityId, $languageCode, $payload, 'completed');
            $updated = $this->translationRepository->updateStatus($id, 'completed', null);
            $this->syncEntityTranslationStatus($entityType, $entityId);

            $this->operationLogService->recordCurrentAction('translation', 'translation.retry', 'translation_job', $updated, 'translation executed');

            return $updated ?? [];
        } catch (\Throwable $exception) {
            $this->translationRepository->updateStatus($id, 'failed', $exception->getMessage());
            $this->syncEntityTranslationStatus((string) ($job['entity_type'] ?? ''), (int) ($job['entity_id'] ?? 0));

            throw $exception instanceof BusinessException
                ? $exception
                : new BusinessException($exception->getMessage(), ErrorCode::INTERNAL_ERROR);
        }
    }

    public function approve(int $id): array
    {
        $job = $this->translationRepository->find($id);
        if ($job === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $entityType = (string) ($job['entity_type'] ?? '');
        $entityId = (int) ($job['entity_id'] ?? 0);
        $languageCode = (string) ($job['language_code'] ?? '');
        if ($languageCode === '' || $languageCode === 'zh') {
            throw new BusinessException('invalid translation language', ErrorCode::INVALID_PARAMS);
        }

        $translation = $this->contentEntityBridge->translationRecord($entityType, $entityId, $languageCode);
        if ($translation === null) {
            throw new BusinessException('translation record not found', ErrorCode::NOT_FOUND);
        }

        $this->contentEntityBridge->upsertTranslation($entityType, $entityId, $languageCode, $translation, 'completed');
        $updated = $this->translationRepository->updateStatus($id, 'completed', null);
        $this->syncEntityTranslationStatus($entityType, $entityId);
        $this->operationLogService->recordCurrentAction('translation', 'translation.approve', 'translation_job', $updated, 'translation approved');

        return $updated ?? [];
    }

    public function update(int $id, array $input): array
    {
        $job = $this->translationRepository->find($id);
        if ($job === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $entityType = (string) ($job['entity_type'] ?? '');
        $entityId = (int) ($job['entity_id'] ?? 0);
        $languageCode = (string) ($job['language_code'] ?? '');
        if ($languageCode === '' || $languageCode === 'zh') {
            throw new BusinessException('invalid translation language', ErrorCode::INVALID_PARAMS);
        }
        if (!$this->contentEntityBridge->supports($entityType)) {
            throw new BusinessException('unsupported entity type', ErrorCode::INVALID_PARAMS);
        }

        $record = $this->contentEntityBridge->find($entityType, $entityId);
        if ($record === null) {
            throw new BusinessException('source record not found', ErrorCode::NOT_FOUND);
        }

        $sourceFields = $this->contentEntityBridge->translationSource($entityType, $record);
        if ($sourceFields === []) {
            throw new BusinessException('unsupported entity type', ErrorCode::INVALID_PARAMS);
        }

        $existingTranslation = $this->contentEntityBridge->translationRecord($entityType, $entityId, $languageCode) ?? [];
        $incomingFields = $input['translated_fields'] ?? null;
        if (!is_array($incomingFields)) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $payload = [];
        foreach (array_keys($sourceFields) as $field) {
            $value = $incomingFields[$field] ?? ($existingTranslation[$field] ?? '');
            $payload[$field] = trim((string) $value);
        }

        $this->contentEntityBridge->upsertTranslation($entityType, $entityId, $languageCode, $payload, 'review_required');
        $updated = $this->translationRepository->updateStatus($id, 'review_required', null);
        $this->syncEntityTranslationStatus($entityType, $entityId);
        $this->operationLogService->recordCurrentAction('translation', 'translation.update', 'translation_job', $updated, 'translation updated');

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

        foreach ($this->languageRepository->list() as $language) {
            if ((int) ($language['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $languageCode = (string) ($language['code'] ?? '');
            if ($languageCode === '' || $languageCode === 'zh') {
                continue;
            }

            $job = $this->translationRepository->findByEntity($entityType, $entityId, $languageCode);
            if ($job === null) {
                continue;
            }

            $status = (string) ($job['status'] ?? 'pending');
            if ($status !== 'pending') {
                continue;
            }

            $this->retry((int) ($job['id'] ?? 0));
        }
    }

    /**
     * @return array<string, string>
     */
    private function translateRecord(string $entityType, array $record, string $languageCode): array
    {
        $source = $this->contentEntityBridge->translationSource($entityType, $record);
        if ($source === []) {
            throw new BusinessException('unsupported entity type', ErrorCode::INVALID_PARAMS);
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
                    'output_rule' => 'Return the same keys as source_fields with translated text values only.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];
        $response = $this->deepSeekClient->jsonChat($messages, 'translation_enabled');

        $translated = [];
        foreach (array_keys($source) as $field) {
            $translated[$field] = (string) ($response[$field] ?? $source[$field] ?? '');
        }

        return $translated;
    }

    private function deepseekPrompt(string $feature): string
    {
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
