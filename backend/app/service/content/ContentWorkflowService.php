<?php

declare(strict_types=1);

namespace app\service\content;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\ContentPublicationRepository;

final class ContentWorkflowService
{
    public function __construct(
        private readonly ContentPublicationRepository $contentPublicationRepository = new ContentPublicationRepository()
    ) {
    }

    public function touchDraft(string $entityType, array $record, ?array $operator, string $message = '草稿已更新'): array
    {
        $entityId = (int) ($record['id'] ?? 0);
        if ($entityId <= 0) {
            return $this->workflow($entityType, $record);
        }

        $meta = $this->contentPublicationRepository->publishMeta($entityType, $entityId);
        $now = date('Y-m-d H:i:s');

        $meta['draft_updated_at'] = $now;
        $meta['publish_log'] = $this->prependLog($meta['publish_log'] ?? [], [
            'action' => 'draft_update',
            'operator' => $this->actor($operator),
            'created_at' => $now,
            'message' => $message,
        ]);

        $this->contentPublicationRepository->savePublishMeta($entityType, $entityId, $meta);

        return $this->workflow($entityType, $record);
    }

    public function publish(string $entityType, array $record, ?array $operator): array
    {
        $entityId = (int) ($record['id'] ?? 0);
        if ($entityId <= 0) {
            return $this->workflow($entityType, $record);
        }

        $meta = $this->contentPublicationRepository->publishMeta($entityType, $entityId);
        $now = date('Y-m-d H:i:s');
        $actor = $this->actor($operator);

        $meta['draft_updated_at'] = $meta['draft_updated_at'] ?? $now;
        $meta['live_updated_at'] = $now;
        $meta['last_published_by'] = $actor;
        $meta['publish_log'] = $this->prependLog($meta['publish_log'] ?? [], [
            'action' => 'publish',
            'operator' => $actor,
            'created_at' => $now,
            'message' => '内容已发布',
        ]);

        $this->contentPublicationRepository->saveSnapshot($entityType, $entityId, $record);
        $this->contentPublicationRepository->savePublishMeta($entityType, $entityId, $meta);

        return $this->workflow($entityType, $record);
    }

    /**
     * @param callable(array<string, mixed>): ?array $saveRecord
     */
    public function restoreLive(string $entityType, array $record, callable $saveRecord, ?array $operator): array
    {
        $entityId = (int) ($record['id'] ?? 0);
        $snapshot = $this->contentPublicationRepository->snapshot($entityType, $entityId);
        if ($entityId <= 0 || $snapshot === null) {
            throw new BusinessException('线上版本不存在', ErrorCode::NOT_FOUND);
        }

        $restored = array_merge($record, $snapshot, [
            'id' => $entityId,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => isset($operator['id']) ? (int) $operator['id'] : ($snapshot['updated_by'] ?? null),
        ]);
        $saved = $saveRecord($restored);
        if (!is_array($saved)) {
            throw new BusinessException('内容不存在', ErrorCode::NOT_FOUND);
        }

        $meta = $this->contentPublicationRepository->publishMeta($entityType, $entityId);
        $now = date('Y-m-d H:i:s');
        $meta['draft_updated_at'] = $now;
        $meta['last_restored_by'] = $this->actor($operator);
        $meta['publish_log'] = $this->prependLog($meta['publish_log'] ?? [], [
            'action' => 'restore_live',
            'operator' => $this->actor($operator),
            'created_at' => $now,
            'message' => '已从线上版本恢复草稿',
        ]);
        $this->contentPublicationRepository->savePublishMeta($entityType, $entityId, $meta);

        return $saved;
    }

    public function workflow(string $entityType, array $record): array
    {
        $entityId = (int) ($record['id'] ?? 0);
        $meta = $this->contentPublicationRepository->publishMeta($entityType, $entityId);
        $snapshot = $this->contentPublicationRepository->snapshot($entityType, $entityId);

        return [
            'draft_updated_at' => $meta['draft_updated_at'] ?? ($record['updated_at'] ?? null),
            'live_updated_at' => $meta['live_updated_at'] ?? null,
            'last_published_by' => (string) ($meta['last_published_by'] ?? ''),
            'last_restored_by' => (string) ($meta['last_restored_by'] ?? ''),
            'has_live_snapshot' => $snapshot !== null ? 1 : 0,
            'has_unpublished_changes' => $this->hasUnpublishedChanges($record, $snapshot) ? 1 : 0,
            'publish_log' => is_array($meta['publish_log'] ?? null) ? $meta['publish_log'] : [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function liveRecords(string $entityType): array
    {
        return array_values($this->contentPublicationRepository->snapshots($entityType));
    }

    public function liveRecord(string $entityType, int $entityId): ?array
    {
        return $this->contentPublicationRepository->snapshot($entityType, $entityId);
    }

    private function hasUnpublishedChanges(array $record, ?array $snapshot): bool
    {
        if ($snapshot === null) {
            return true;
        }

        return $this->normalizeComparable($record) !== $this->normalizeComparable($snapshot);
    }

    private function normalizeComparable(array $record): array
    {
        unset(
            $record['updated_at'],
            $record['updated_by'],
            $record['created_at'],
            $record['created_by'],
            $record['translation_status'],
            $record['seo_status']
        );

        ksort($record);

        return $record;
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     * @param array<string, mixed> $entry
     * @return array<int, array<string, mixed>>
     */
    private function prependLog(array $logs, array $entry): array
    {
        array_unshift($logs, $entry);

        return array_slice(array_values($logs), 0, 10);
    }

    private function actor(?array $operator): string
    {
        return (string) ($operator['nickname'] ?? $operator['username'] ?? 'system');
    }
}
