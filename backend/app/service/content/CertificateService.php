<?php

declare(strict_types=1);

namespace app\service\content;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\CertificateRepository;
use app\repository\MediaRepository;
use app\service\log\OperationLogService;
use app\service\translation\SharedTranslationPipelineService;

final class CertificateService
{
    public function __construct(
        private readonly CertificateRepository $certificateRepository = new CertificateRepository(),
        private readonly MediaRepository $mediaRepository = new MediaRepository(),
        private readonly OperationLogService $operationLogService = new OperationLogService(),
        private readonly SharedTranslationPipelineService $sharedTranslationPipelineService = new SharedTranslationPipelineService()
    )
    {
    }

    public function list(): array
    {
        return [
            'items' => $this->certificateRepository->list(),
        ];
    }

    public function detail(int $id): array
    {
        $record = $this->certificateRepository->find($id);
        if ($record === null) {
            throw new BusinessException('记录不存在', ErrorCode::NOT_FOUND);
        }

        return $record;
    }

    public function create(array $input): array
    {
        $name = trim((string) ($input['name_zh'] ?? ''));
        if ($name === '') {
            throw new BusinessException('参数校验失败', ErrorCode::INVALID_PARAMS);
        }

        $record = $this->certificateRepository->create([
            'name_zh' => $name,
            'issuer_zh' => (string) ($input['issuer_zh'] ?? ''),
            'certificate_no' => (string) ($input['certificate_no'] ?? ''),
            'certificate_type' => (string) ($input['certificate_type'] ?? ''),
            'description_zh' => (string) ($input['description_zh'] ?? ''),
            'image_asset_id' => $this->validateImageAssetId(isset($input['image_asset_id']) && $input['image_asset_id'] !== '' ? (int) $input['image_asset_id'] : null),
            'publish_status' => (string) ($input['publish_status'] ?? 'draft'),
            'translation_status' => (string) ($input['translation_status'] ?? 'pending'),
            'seo_status' => (string) ($input['seo_status'] ?? 'pending'),
            'is_home_featured' => !empty($input['is_home_featured']) ? 1 : 0,
            'manual_sort' => (int) ($input['manual_sort'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->sharedTranslationPipelineService->syncEntity('certificate', (int) ($record['id'] ?? 0));
        $record = $this->detail((int) ($record['id'] ?? 0));
        $this->operationLogService->recordCurrentAction('certificate', 'certificate.create', 'certificate', $record, 'certificate created');

        return $record;
    }

    public function update(int $id, array $input): array
    {
        $existing = $this->detail($id);
        $updated = $this->certificateRepository->update($id, array_merge($existing, [
            'name_zh' => (string) ($input['name_zh'] ?? $existing['name_zh']),
            'issuer_zh' => (string) ($input['issuer_zh'] ?? $existing['issuer_zh'] ?? ''),
            'certificate_no' => (string) ($input['certificate_no'] ?? $existing['certificate_no'] ?? ''),
            'certificate_type' => (string) ($input['certificate_type'] ?? $existing['certificate_type'] ?? ''),
            'description_zh' => (string) ($input['description_zh'] ?? $existing['description_zh'] ?? ''),
            'image_asset_id' => $this->validateImageAssetId(isset($input['image_asset_id']) && $input['image_asset_id'] !== '' ? (int) $input['image_asset_id'] : ($existing['image_asset_id'] ?? null)),
            'publish_status' => (string) ($input['publish_status'] ?? $existing['publish_status'] ?? 'draft'),
            'translation_status' => (string) ($input['translation_status'] ?? $existing['translation_status'] ?? 'pending'),
            'seo_status' => (string) ($input['seo_status'] ?? $existing['seo_status'] ?? 'pending'),
            'is_home_featured' => array_key_exists('is_home_featured', $input) ? (!empty($input['is_home_featured']) ? 1 : 0) : ($existing['is_home_featured'] ?? 0),
            'manual_sort' => (int) ($input['manual_sort'] ?? $existing['manual_sort'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));

        if ($updated === null) {
            throw new BusinessException('记录不存在', ErrorCode::NOT_FOUND);
        }

        $this->sharedTranslationPipelineService->syncEntity('certificate', $id);
        $updated = $this->detail($id);
        $this->operationLogService->recordCurrentAction('certificate', 'certificate.update', 'certificate', $updated, 'certificate updated');

        return $updated;
    }

    private function validateImageAssetId(?int $assetId): ?int
    {
        if ($assetId === null || $assetId <= 0) {
            return null;
        }

        $asset = $this->mediaRepository->find($assetId);
        if ($asset === null) {
            throw new BusinessException('media asset not found', ErrorCode::NOT_FOUND);
        }

        if ((int) ($asset['status'] ?? 0) !== 1) {
            throw new BusinessException('media asset is disabled', ErrorCode::INVALID_PARAMS);
        }

        $mimeType = strtolower((string) ($asset['mime_type'] ?? ''));
        if (!str_starts_with($mimeType, 'image/')) {
            throw new BusinessException('asset must be image', ErrorCode::UNSUPPORTED_FILE_TYPE);
        }

        return $assetId;
    }

    public function publish(int $id, string $publishStatus): array
    {
        $updated = $this->certificateRepository->updatePublishStatus($id, $publishStatus);
        if ($updated === null) {
            throw new BusinessException('记录不存在', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('certificate', 'certificate.publish', 'certificate', $updated, 'certificate publish status updated');

        return $updated;
    }

    public function remove(int $id): array
    {
        $deleted = $this->certificateRepository->delete($id);
        if ($deleted === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('certificate', 'certificate.delete', 'certificate', $deleted, 'certificate deleted');

        return $deleted;
    }

    public function batchPublish(array $ids, string $publishStatus): array
    {
        if ($ids === []) {
            throw new BusinessException('参数校验失败', ErrorCode::INVALID_PARAMS);
        }

        $count = $this->certificateRepository->batchUpdatePublishStatus($ids, $publishStatus);
        $this->operationLogService->recordCurrentAction('certificate', 'certificate.batch_publish', 'certificate', ['ids' => $ids, 'publish_status' => $publishStatus, 'count' => $count], 'certificates batch publish updated');

        return ['affected' => $count];
    }

    public function batchSort(array $sortItems): array
    {
        if ($sortItems === []) {
            throw new BusinessException('参数校验失败', ErrorCode::INVALID_PARAMS);
        }

        $normalized = [];
        foreach ($sortItems as $item) {
            $normalized[] = [
                'id' => (int) ($item['id'] ?? 0),
                'manual_sort' => (int) ($item['manual_sort'] ?? 0),
            ];
        }

        $count = $this->certificateRepository->batchUpdateSort($normalized);
        $this->operationLogService->recordCurrentAction('certificate', 'certificate.batch_sort', 'certificate', ['items' => $normalized, 'count' => $count], 'certificates batch sort updated');

        return ['affected' => $count];
    }
}
