<?php

declare(strict_types=1);

namespace app\service\content;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\MediaGalleryRepository;
use app\repository\PageRepository;
use app\service\log\OperationLogService;

final class PageService
{
    public function __construct(
        private readonly PageRepository $pageRepository = new PageRepository(),
        private readonly ContentPipelineService $contentPipelineService = new ContentPipelineService(),
        private readonly ContentAutoMetaService $contentAutoMetaService = new ContentAutoMetaService(),
        private readonly OperationLogService $operationLogService = new OperationLogService(),
        private readonly ContentWorkflowService $contentWorkflowService = new ContentWorkflowService(),
        private readonly MediaGalleryRepository $mediaGalleryRepository = new MediaGalleryRepository()
    ) {
    }

    public function list(array $query = []): array
    {
        $result = $this->pageRepository->list($query);
        $result['items'] = $this->attachCoverAssets($result['items'] ?? [], 'page');

        return array_merge($result, [
            'filters' => ['publish_status', 'page_type', 'keyword'],
        ]);
    }

    public function detail(int $id): array
    {
        $page = $this->pageRepository->find($id);
        if ($page === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $page['media_gallery'] = $this->mediaGalleryRepository->list('page', $id);
        $page = $this->attachSingleCoverAsset($page, $page['media_gallery'][0] ?? null);

        return $page;
    }

    public function bootstrap(int $id): array
    {
        return [
            'detail' => $this->detail($id),
            'workflow' => $this->workflow($id),
        ];
    }

    public function adminBootstrap(array $query = [], ?int $preferredId = null): array
    {
        $list = $this->list($query);
        $items = is_array($list['items'] ?? null) ? $list['items'] : [];

        $targetId = $preferredId && $preferredId > 0 ? $preferredId : (int) ($items[0]['id'] ?? 0);
        $detail = null;
        $workflow = null;

        if ($targetId > 0) {
            try {
                $detail = $this->detail($targetId);
                $workflow = $this->workflow($targetId);
            } catch (BusinessException) {
                $detail = null;
                $workflow = null;
            }
        }

        return [
            'list' => $list,
            'current_id' => $targetId > 0 ? $targetId : null,
            'detail' => $detail,
            'workflow' => $workflow,
        ];
    }

    public function create(array $input, ?array $operator): array
    {
        $record = $this->pageRepository->create($this->normalizePayload($input, $operator, true));
        if (!empty($input['media_gallery'])) {
            $this->mediaGalleryRepository->replace('page', (int) ($record['id'] ?? 0), $input['media_gallery']);
        }
        $record = $this->contentPipelineService->sync('page', $record);
        $this->contentWorkflowService->touchDraft('page', $record, $operator, 'page created');
        $this->operationLogService->recordCurrentAction('page', 'page.create', 'page', $record, 'page created');

        return $record;
    }

    public function update(int $id, array $input, ?array $operator): array
    {
        $existing = $this->pageRepository->find($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $updated = $this->pageRepository->update($id, $this->normalizePayload(array_merge($existing, $input), $operator, false));
        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        if (array_key_exists('media_gallery', $input)) {
            $this->mediaGalleryRepository->replace('page', $id, $input['media_gallery'] ?? []);
        }
        $updated = $this->contentPipelineService->sync('page', $updated);
        $this->contentWorkflowService->touchDraft('page', $updated, $operator, 'page draft updated');
        $this->operationLogService->recordCurrentAction('page', 'page.update', 'page', $updated, 'page updated');

        return $updated;
    }

    public function publish(int $id, string $publishStatus, ?array $operator): array
    {
        if (!in_array($publishStatus, ['draft', 'published', 'offline'], true)) {
            throw new BusinessException('invalid status transition', ErrorCode::INVALID_STATUS_TRANSITION);
        }

        $existing = $this->pageRepository->find($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        if ($publishStatus === 'published') {
            $updated = $this->pageRepository->update(
                $id,
                $this->normalizePayload(array_merge($existing, ['publish_status' => 'published']), $operator, false)
            );
        } else {
            $updated = $this->pageRepository->updatePublishStatus(
                $id,
                $publishStatus,
                null,
                isset($operator['id']) ? (int) $operator['id'] : null
            );
        }
        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $updated = $this->contentPipelineService->sync('page', $updated, false);
        if ($publishStatus === 'published') {
            $this->contentWorkflowService->publish('page', $updated, $operator);
        } else {
            $this->contentWorkflowService->touchDraft('page', $updated, $operator, 'page publish status updated');
        }
        $this->operationLogService->recordCurrentAction('page', 'page.publish', 'page', $updated, 'page publish status updated');

        return $updated;
    }

    public function batchPublish(array|string $ids, string $publishStatus, ?array $operator): array
    {
        if (!in_array($publishStatus, ['draft', 'published', 'offline'], true)) {
            throw new BusinessException('invalid status transition', ErrorCode::INVALID_STATUS_TRANSITION);
        }

        $normalizedIds = $this->normalizeBatchIds($ids);
        if ($normalizedIds === []) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $updatedItems = [];
        foreach ($normalizedIds as $id) {
            $existing = $this->pageRepository->find($id);
            if ($existing === null) {
                continue;
            }

            if ($publishStatus === 'published') {
                $updated = $this->pageRepository->update(
                    $id,
                    $this->normalizePayload(array_merge($existing, ['publish_status' => 'published']), $operator, false)
                );
            } else {
                $updated = $this->pageRepository->updatePublishStatus(
                    $id,
                    $publishStatus,
                    null,
                    isset($operator['id']) ? (int) $operator['id'] : null
                );
            }
            if ($updated === null) {
                continue;
            }

            $updated = $this->contentPipelineService->sync('page', $updated, false);
            if ($publishStatus === 'published') {
                $this->contentWorkflowService->publish('page', $updated, $operator);
            } else {
                $this->contentWorkflowService->touchDraft('page', $updated, $operator, 'page batch publish status updated');
            }
            $updatedItems[] = $updated;
        }

        if ($updatedItems === []) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction(
            'page',
            'page.batch_publish',
            'page_batch',
            ['id' => implode(',', array_map(static fn (array $item): string => (string) ($item['id'] ?? 0), $updatedItems))],
            'page batch publish status updated'
        );

        return [
            'ids' => array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $updatedItems),
            'publish_status' => $publishStatus,
            'updated_count' => count($updatedItems),
            'items' => $updatedItems,
        ];
    }

    public function workflow(int $id): array
    {
        $page = $this->pageRepository->find($id);
        if ($page === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        return $this->contentWorkflowService->workflow('page', $page);
    }

    public function restoreLive(int $id, ?array $operator): array
    {
        $page = $this->pageRepository->find($id);
        if ($page === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $restored = $this->contentWorkflowService->restoreLive(
            'page',
            $page,
            fn (array $payload): ?array => $this->pageRepository->update($id, $payload),
            $operator
        );
        $this->operationLogService->recordCurrentAction('page', 'page.restore_live', 'page', $restored, 'page restored from live');

        return $restored;
    }

    public function remove(int $id, ?array $operator): array
    {
        $this->mediaGalleryRepository->deleteByEntity('page', $id);
        $deleted = $this->pageRepository->delete($id);
        if ($deleted === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('page', 'page.delete', 'page', $deleted, 'page deleted');

        return $deleted;
    }

    public function batchRemove(array|string $ids, ?array $operator): array
    {
        $normalizedIds = is_array($ids) ? $ids : explode(',', (string) $ids);
        $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $normalizedIds), static fn (int $v): bool => $v > 0)));
        if ($normalizedIds === []) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $deletedItems = [];
        foreach ($normalizedIds as $id) {
            try {
                $deletedItems[] = $this->remove($id, $operator);
            } catch (BusinessException $e) {
                continue;
            }
        }

        if ($deletedItems === []) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction(
            'page',
            'page.batch_delete',
            'page_batch',
            ['id' => implode(',', array_map(static fn (array $item): string => (string) ($item['id'] ?? 0), $deletedItems))],
            'page batch deleted'
        );

        return [
            'ids' => array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $deletedItems),
            'deleted_count' => count($deletedItems),
            'items' => $deletedItems,
        ];
    }

    private function normalizePayload(array $input, ?array $operator, bool $isCreate): array
    {
        $title = trim((string) ($input['title_zh'] ?? ''));
        $pageType = (string) ($input['page_type'] ?? 'page');
        if ($title === '' || !in_array($pageType, ['page', 'campaign', 'landing'], true)) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $publishStatus = (string) ($input['publish_status'] ?? 'draft');
        $content = (string) ($input['content_zh'] ?? '');
        $recordId = (int) ($input['id'] ?? 0);
        $meta = $this->contentAutoMetaService->enrich([
            'entity_type' => 'page',
            'title' => $title,
            'category_name' => $pageType,
            'summary' => (string) ($input['summary_zh'] ?? ''),
            'content' => $content,
            'seo_title' => (string) ($input['seo_title'] ?? ''),
            'seo_keywords' => (string) ($input['seo_keywords'] ?? ''),
            'seo_description' => (string) ($input['seo_description'] ?? ''),
            'publish_status' => $publishStatus,
        ]);

        return [
            'page_type' => $pageType,
            'title_zh' => $title,
            'summary_zh' => $meta['summary'],
            'content_zh' => $content,
            'publish_status' => in_array($publishStatus, ['draft', 'published', 'offline'], true) ? $publishStatus : 'draft',
            'translation_status' => (string) ($input['translation_status'] ?? 'pending'),
            'seo_status' => (string) ($input['seo_status'] ?? 'pending'),
            'slug' => $this->buildSlug((string) ($input['slug'] ?? ''), $title, $pageType, $recordId),
            'seo_title' => $this->defaultSeoTitle($meta['seo_title'], $title),
            'seo_keywords' => $this->defaultSeoKeywords($meta['seo_keywords'], $title),
            'seo_description' => $this->defaultSeoDescription($meta['seo_description'], $meta['summary'], $content, $title),
            'media_gallery' => $this->normalizeMediaGallery($input['media_gallery'] ?? null),
            'publish_time' => $publishStatus === 'published' ? (string) ($input['publish_time'] ?? date('Y-m-d H:i:s')) : null,
            'created_by' => $isCreate ? (isset($operator['id']) ? (int) $operator['id'] : null) : (int) ($input['created_by'] ?? 0),
            'updated_by' => isset($operator['id']) ? (int) $operator['id'] : null,
            'created_at' => (string) ($input['created_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function normalizeBatchIds(array|string $ids): array
    {
        $source = is_string($ids) ? preg_split('/\s*,\s*/', trim($ids), -1, PREG_SPLIT_NO_EMPTY) : $ids;
        if (!is_array($source)) {
            return [];
        }

        $normalized = [];
        foreach ($source as $id) {
            $value = (int) $id;
            if ($value > 0) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function buildSlug(string $slug, string $fallback, string $prefix, int $excludeId = 0): string
    {
        $baseSlug = $this->normalizeSlug($slug, $fallback, $prefix);
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->pageRepository->slugExists($candidate, $excludeId)) {
            $candidate = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function normalizeSlug(string $slug, string $fallback, string $prefix): string
    {
        $slug = trim(strtolower($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ($slug !== '') {
            return $slug;
        }

        $candidate = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $fallback));
        $candidate = trim($candidate, '-');

        return $candidate !== '' ? $candidate : $prefix . '-' . substr(sha1($fallback), 0, 10);
    }

    private function defaultSeoTitle(string $value, string $fallback): string
    {
        $value = trim($value);
        return $value !== '' ? $value : $fallback;
    }

    private function defaultSeoKeywords(string $value, string $fallback): string
    {
        $value = trim($value);
        return $value !== '' ? $value : $fallback;
    }

    private function defaultSeoDescription(string $value, string $summary, string $content, string $fallback): string
    {
        $value = trim($value);
        if ($value !== '') {
            return $value;
        }

        $summary = trim($summary);
        if ($summary !== '') {
            return mb_substr($summary, 0, 120);
        }

        $plain = trim(strip_tags($content));
        if ($plain !== '') {
            return mb_substr($plain, 0, 120);
        }

        return $fallback;
    }

    private function normalizeMediaGallery(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }

        $items = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($items) || $items === []) {
            return null;
        }

        $valid = [];
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['asset_id'])) {
                continue;
            }
            $valid[] = [
                'asset_id' => (int) $item['asset_id'],
                'file_path' => (string) ($item['file_path'] ?? ''),
                'file_name' => (string) ($item['file_name'] ?? ''),
                'sort' => (int) ($item['sort'] ?? 0),
                'is_cover' => !empty($item['is_cover']),
            ];
        }

        return $valid !== [] ? json_encode($valid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function attachCoverAssets(array $items, string $entityType): array
    {
        $entityIds = array_values(array_filter(
            array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $items),
            static fn (int $id): bool => $id > 0
        ));
        $covers = $this->mediaGalleryRepository->coverMap($entityType, $entityIds);

        return array_map(
            fn (array $item): array => $this->attachSingleCoverAsset($item, $covers[(int) ($item['id'] ?? 0)] ?? null),
            $items
        );
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed>|null $cover
     * @return array<string, mixed>
     */
    private function attachSingleCoverAsset(array $item, ?array $cover): array
    {
        if ($cover === null) {
            $fallbackCover = ContentCoverFallbackResolver::resolve('page', $item);
            if ($fallbackCover !== null) {
                $cover = $fallbackCover;
            }
        }

        if ($cover === null) {
            $item['cover_asset_id'] = (int) ($item['cover_asset_id'] ?? 0);
            $item['cover_thumb_url'] = (string) ($item['cover_thumb_url'] ?? '');
            $item['cover_file_path'] = (string) ($item['cover_file_path'] ?? '');
            $item['cover_asset'] = $item['cover_asset'] ?? null;

            return $item;
        }

        $item['cover_asset_id'] = (int) ($cover['asset_id'] ?? 0);
        $item['cover_thumb_url'] = (string) ($cover['thumb_url'] ?? '');
        $item['cover_file_path'] = (string) ($cover['file_path'] ?? '');
        $item['cover_asset'] = [
            'id' => (int) ($cover['asset_id'] ?? 0),
            'file_name' => (string) ($cover['file_name'] ?? ''),
            'file_path' => (string) ($cover['file_path'] ?? ''),
            'thumb_url' => (string) ($cover['thumb_url'] ?? ''),
            'mime_type' => (string) ($cover['mime_type'] ?? ''),
            'file_ext' => (string) ($cover['file_ext'] ?? ''),
        ];

        return $item;
    }
}
