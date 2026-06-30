<?php

declare(strict_types=1);

namespace app\service\content;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\MediaRepository;
use app\repository\TeamRepository;
use app\service\log\OperationLogService;
use app\service\translation\SharedTranslationPipelineService;

final class TeamService
{
    public function __construct(
        private readonly TeamRepository $teamRepository = new TeamRepository(),
        private readonly MediaRepository $mediaRepository = new MediaRepository(),
        private readonly OperationLogService $operationLogService = new OperationLogService(),
        private readonly SharedTranslationPipelineService $sharedTranslationPipelineService = new SharedTranslationPipelineService()
    )
    {
    }

    public function list(): array
    {
        return [
            'items' => $this->teamRepository->list(),
        ];
    }

    public function detail(int $id): array
    {
        $record = $this->teamRepository->find($id);
        if ($record === null) {
            throw new BusinessException('团队成员不存在', ErrorCode::NOT_FOUND);
        }

        return $record;
    }

    public function create(array $input, ?array $operator): array
    {
        $name = trim((string) ($input['name_zh'] ?? ''));
        if ($name === '') {
            throw new BusinessException('团队成员姓名不能为空', ErrorCode::INVALID_PARAMS);
        }

        $record = $this->teamRepository->create([
            'name_zh' => $name,
            'title_zh' => (string) ($input['title_zh'] ?? ''),
            'department_zh' => (string) ($input['department_zh'] ?? ''),
            'bio_zh' => (string) ($input['bio_zh'] ?? ''),
            'avatar_asset_id' => $this->validateAvatarAssetId(isset($input['avatar_asset_id']) && $input['avatar_asset_id'] !== '' ? (int) $input['avatar_asset_id'] : null),
            'email' => (string) ($input['email'] ?? ''),
            'phone' => (string) ($input['phone'] ?? ''),
            'whatsapp' => (string) ($input['whatsapp'] ?? ''),
            'wechat' => (string) ($input['wechat'] ?? ''),
            'publish_status' => (string) ($input['publish_status'] ?? 'draft'),
            'translation_status' => (string) ($input['translation_status'] ?? 'pending'),
            'is_home_featured' => !empty($input['is_home_featured']) ? 1 : 0,
            'manual_sort' => (int) ($input['manual_sort'] ?? 0),
            'created_by' => isset($operator['id']) ? (int) $operator['id'] : null,
            'updated_by' => isset($operator['id']) ? (int) $operator['id'] : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->sharedTranslationPipelineService->syncEntity('team_member', (int) ($record['id'] ?? 0));
        $record = $this->detail((int) ($record['id'] ?? 0));
        $this->operationLogService->recordCurrentAction('team', 'team.create', 'team_member', $record, '团队成员已创建');

        return $record;
    }

    public function update(int $id, array $input, ?array $operator): array
    {
        $existing = $this->detail($id);
        $updated = $this->teamRepository->update($id, array_merge($existing, [
            'name_zh' => (string) ($input['name_zh'] ?? $existing['name_zh']),
            'title_zh' => (string) ($input['title_zh'] ?? $existing['title_zh'] ?? ''),
            'department_zh' => (string) ($input['department_zh'] ?? $existing['department_zh'] ?? ''),
            'bio_zh' => (string) ($input['bio_zh'] ?? $existing['bio_zh'] ?? ''),
            'avatar_asset_id' => $this->validateAvatarAssetId(isset($input['avatar_asset_id']) && $input['avatar_asset_id'] !== '' ? (int) $input['avatar_asset_id'] : ($existing['avatar_asset_id'] ?? null)),
            'email' => (string) ($input['email'] ?? $existing['email'] ?? ''),
            'phone' => (string) ($input['phone'] ?? $existing['phone'] ?? ''),
            'whatsapp' => (string) ($input['whatsapp'] ?? $existing['whatsapp'] ?? ''),
            'wechat' => (string) ($input['wechat'] ?? $existing['wechat'] ?? ''),
            'publish_status' => (string) ($input['publish_status'] ?? $existing['publish_status'] ?? 'draft'),
            'translation_status' => (string) ($input['translation_status'] ?? $existing['translation_status'] ?? 'pending'),
            'is_home_featured' => array_key_exists('is_home_featured', $input) ? (!empty($input['is_home_featured']) ? 1 : 0) : ($existing['is_home_featured'] ?? 0),
            'manual_sort' => (int) ($input['manual_sort'] ?? $existing['manual_sort'] ?? 0),
            'updated_by' => isset($operator['id']) ? (int) $operator['id'] : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]));

        if ($updated === null) {
            throw new BusinessException('团队成员不存在', ErrorCode::NOT_FOUND);
        }

        $this->sharedTranslationPipelineService->syncEntity('team_member', $id);
        $updated = $this->detail($id);
        $this->operationLogService->recordCurrentAction('team', 'team.update', 'team_member', $updated, '团队成员已更新');

        return $updated;
    }

    private function validateAvatarAssetId(?int $assetId): ?int
    {
        if ($assetId === null || $assetId <= 0) {
            return null;
        }

        $asset = $this->mediaRepository->find($assetId);
        if ($asset === null) {
            throw new BusinessException('头像资源不存在', ErrorCode::NOT_FOUND);
        }

        if ((int) ($asset['status'] ?? 0) !== 1) {
            throw new BusinessException('头像资源未启用', ErrorCode::INVALID_PARAMS);
        }

        $mimeType = strtolower((string) ($asset['mime_type'] ?? ''));
        if (!str_starts_with($mimeType, 'image/')) {
            throw new BusinessException('头像资源必须为图片', ErrorCode::UNSUPPORTED_FILE_TYPE);
        }

        return $assetId;
    }

    public function publish(int $id, string $publishStatus, ?array $operator): array
    {
        $updated = $this->teamRepository->updatePublishStatus($id, $publishStatus, isset($operator['id']) ? (int) $operator['id'] : null);
        if ($updated === null) {
            throw new BusinessException('团队成员不存在', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('team', 'team.publish', 'team_member', $updated, '团队成员发布状态已更新');

        return $updated;
    }

    public function remove(int $id, ?array $operator): array
    {
        $deleted = $this->teamRepository->delete($id);
        if ($deleted === null) {
            throw new BusinessException('团队成员不存在', ErrorCode::NOT_FOUND);
        }

        (new ContentCleanupService())->purgeEntity('team_member', $id, $deleted);
        $this->operationLogService->recordCurrentAction('team', 'team.delete', 'team_member', $deleted, '团队成员已删除');

        return $deleted;
    }

    public function batchPublish(array $ids, string $publishStatus, ?array $operator): array
    {
        if ($ids === []) {
            throw new BusinessException('团队成员 ID 列表不能为空', ErrorCode::INVALID_PARAMS);
        }

        $updatedBy = isset($operator['id']) ? (int) $operator['id'] : null;
        $count = $this->teamRepository->batchUpdatePublishStatus($ids, $publishStatus, $updatedBy);
        $this->operationLogService->recordCurrentAction('team', 'team.batch_publish', 'team_member', ['ids' => $ids, 'publish_status' => $publishStatus, 'count' => $count], '团队成员批量发布状态已更新');

        return ['affected' => $count];
    }

    public function batchSort(array $sortItems): array
    {
        if ($sortItems === []) {
            throw new BusinessException('排序数据不能为空', ErrorCode::INVALID_PARAMS);
        }

        $normalized = [];
        foreach ($sortItems as $item) {
            $normalized[] = [
                'id' => (int) ($item['id'] ?? 0),
                'manual_sort' => (int) ($item['manual_sort'] ?? 0),
            ];
        }

        $count = $this->teamRepository->batchUpdateSort($normalized);
        $this->operationLogService->recordCurrentAction('team', 'team.batch_sort', 'team_member', ['items' => $normalized, 'count' => $count], '团队成员排序已更新');

        return ['affected' => $count];
    }
}
