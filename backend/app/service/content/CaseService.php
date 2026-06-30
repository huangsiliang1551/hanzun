<?php

declare(strict_types=1);

namespace app\service\content;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\CaseRepository;
use app\repository\MediaGalleryRepository;
use app\service\log\OperationLogService;
use app\service\translation\SharedTranslationPipelineService;

final class CaseService
{
    private const ENTITY_TYPE = 'case';

    public function __construct(
        private readonly CaseRepository $caseRepository = new CaseRepository(),
        private readonly ContentPipelineService $contentPipelineService = new ContentPipelineService(),
        private readonly ContentAutoMetaService $contentAutoMetaService = new ContentAutoMetaService(),
        private readonly OperationLogService $operationLogService = new OperationLogService(),
        private readonly SharedTranslationPipelineService $sharedTranslationPipelineService = new SharedTranslationPipelineService(),
        private readonly ContentWorkflowService $contentWorkflowService = new ContentWorkflowService(),
        private readonly MediaGalleryRepository $mediaGalleryRepository = new MediaGalleryRepository()
    ) {
    }

    public function list(array $query = []): array
    {
        $result = $this->caseRepository->list($query);
        $result['items'] = $this->attachCoverAssets($result['items'] ?? [], self::ENTITY_TYPE);

        return array_merge($result, [
            'filters' => ['publish_status', 'category_id', 'is_home_featured', 'country_code', 'keyword'],
        ]);
    }

    public function lookups(): array
    {
        $productService = new ProductService();
        $solutionService = new SolutionService();

        return [
            'categories' => $this->categoryTree(),
            'products' => $productService->list([
                'page' => 1,
                'page_size' => 200,
                'publish_status' => 'published',
            ]),
            'solutions' => $solutionService->list([
                'page' => 1,
                'page_size' => 200,
                'publish_status' => 'published',
            ]),
        ];
    }

    public function detail(int $id): array
    {
        $case = $this->caseRepository->find($id);
        if ($case === null) {
            throw new BusinessException('案例不存在', ErrorCode::NOT_FOUND);
        }

        $case['media_gallery'] = $this->mediaGalleryRepository->list(self::ENTITY_TYPE, $id);
        $case = $this->attachSingleCoverAsset($case, $case['media_gallery'][0] ?? null);

        return $case;
    }

    public function bootstrap(int $id): array
    {
        return [
            'detail' => $this->detail($id),
            'workflow' => $this->workflow($id),
        ];
    }

    public function create(array $input, ?array $operator): array
    {
        $record = $this->caseRepository->create($this->normalizePayload($input, $operator, true));
        if (!empty($input['media_gallery'])) {
            $this->mediaGalleryRepository->replace(self::ENTITY_TYPE, (int) ($record['id'] ?? 0), $input['media_gallery']);
        }
        $record = $this->contentPipelineService->sync(self::ENTITY_TYPE, $record);
        $this->contentWorkflowService->touchDraft(self::ENTITY_TYPE, $record, $operator, '案例草稿已创建');
        $this->operationLogService->recordCurrentAction(self::ENTITY_TYPE, 'case.create', self::ENTITY_TYPE, $record, '案例已创建');

        return $record;
    }

    public function update(int $id, array $input, ?array $operator): array
    {
        $existing = $this->caseRepository->find($id);
        if ($existing === null) {
            throw new BusinessException('案例不存在', ErrorCode::NOT_FOUND);
        }

        $updated = $this->caseRepository->update($id, $this->normalizePayload(array_merge($existing, $input), $operator, false));
        if ($updated === null) {
            throw new BusinessException('案例不存在', ErrorCode::NOT_FOUND);
        }

        if (array_key_exists('media_gallery', $input)) {
            $this->mediaGalleryRepository->replace(self::ENTITY_TYPE, $id, $input['media_gallery'] ?? []);
        }
        $updated = $this->contentPipelineService->sync(self::ENTITY_TYPE, $updated);
        $this->contentWorkflowService->touchDraft(self::ENTITY_TYPE, $updated, $operator, '案例草稿已更新');
        $this->operationLogService->recordCurrentAction(self::ENTITY_TYPE, 'case.update', self::ENTITY_TYPE, $updated, '案例已更新');

        return $updated;
    }

    public function publish(int $id, string $publishStatus, ?array $operator): array
    {
        if (!in_array($publishStatus, ['draft', 'published', 'offline'], true)) {
            throw new BusinessException('发布状态无效', ErrorCode::INVALID_STATUS_TRANSITION);
        }

        $existing = $this->caseRepository->find($id);
        if ($existing === null) {
            throw new BusinessException('案例不存在', ErrorCode::NOT_FOUND);
        }

        if ($publishStatus === 'published') {
            $updated = $this->caseRepository->update(
                $id,
                $this->normalizePayload(array_merge($existing, ['publish_status' => 'published']), $operator, false)
            );
        } else {
            $updated = $this->caseRepository->updatePublishStatus(
                $id,
                $publishStatus,
                null,
                isset($operator['id']) ? (int) $operator['id'] : null
            );
        }
        if ($updated === null) {
            throw new BusinessException('案例不存在', ErrorCode::NOT_FOUND);
        }

        $updated = $this->contentPipelineService->sync(self::ENTITY_TYPE, $updated, false);
        if ($publishStatus === 'published') {
            $this->contentWorkflowService->publish(self::ENTITY_TYPE, $updated, $operator);
        } else {
            $this->contentWorkflowService->touchDraft(self::ENTITY_TYPE, $updated, $operator, '案例发布状态已切换为草稿或下线');
        }
        $this->operationLogService->recordCurrentAction(self::ENTITY_TYPE, 'case.publish', self::ENTITY_TYPE, $updated, '案例发布状态已更新');

        return $updated;
    }

    public function batchPublish(array|string $ids, string $publishStatus, ?array $operator): array
    {
        if (!in_array($publishStatus, ['draft', 'published', 'offline'], true)) {
            throw new BusinessException('发布状态无效', ErrorCode::INVALID_STATUS_TRANSITION);
        }

        $normalizedIds = $this->normalizeBatchIds($ids);
        if ($normalizedIds === []) {
            throw new BusinessException('案例 ID 列表不能为空', ErrorCode::INVALID_PARAMS);
        }

        $updatedItems = [];
        foreach ($normalizedIds as $id) {
            $existing = $this->caseRepository->find($id);
            if ($existing === null) {
                continue;
            }

            if ($publishStatus === 'published') {
                $updated = $this->caseRepository->update(
                    $id,
                    $this->normalizePayload(array_merge($existing, ['publish_status' => 'published']), $operator, false)
                );
            } else {
                $updated = $this->caseRepository->updatePublishStatus(
                    $id,
                    $publishStatus,
                    null,
                    isset($operator['id']) ? (int) $operator['id'] : null
                );
            }
            if ($updated === null) {
                continue;
            }

            $updated = $this->contentPipelineService->sync(self::ENTITY_TYPE, $updated, false);
            if ($publishStatus === 'published') {
                $this->contentWorkflowService->publish(self::ENTITY_TYPE, $updated, $operator);
            } else {
                $this->contentWorkflowService->touchDraft(self::ENTITY_TYPE, $updated, $operator, '案例批量发布状态已切换为草稿或下线');
            }
            $updatedItems[] = $updated;
        }

        if ($updatedItems === []) {
            throw new BusinessException('案例不存在', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction(
            self::ENTITY_TYPE,
            'case.batch_publish',
            self::ENTITY_TYPE . '_batch',
            ['id' => implode(',', array_map(static fn (array $item): string => (string) ($item['id'] ?? 0), $updatedItems))],
            '案例批量发布状态已更新'
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
        $case = $this->caseRepository->find($id);
        if ($case === null) {
            throw new BusinessException('案例不存在', ErrorCode::NOT_FOUND);
        }

        return $this->contentWorkflowService->workflow(self::ENTITY_TYPE, $case);
    }

    public function restoreLive(int $id, ?array $operator): array
    {
        $case = $this->caseRepository->find($id);
        if ($case === null) {
            throw new BusinessException('案例不存在', ErrorCode::NOT_FOUND);
        }

        $restored = $this->contentWorkflowService->restoreLive(
            self::ENTITY_TYPE,
            $case,
            fn (array $payload): ?array => $this->caseRepository->update($id, $payload),
            $operator
        );
        $this->operationLogService->recordCurrentAction(self::ENTITY_TYPE, 'case.restore_live', self::ENTITY_TYPE, $restored, '案例已从线上版本恢复草稿');

        return $restored;
    }

    public function remove(int $id, ?array $operator): array
    {
        $this->mediaGalleryRepository->deleteByEntity(self::ENTITY_TYPE, $id);
        $deleted = $this->caseRepository->delete($id);
        if ($deleted === null) {
            throw new BusinessException('案例不存在', ErrorCode::NOT_FOUND);
        }

        (new ContentCleanupService())->purgeEntity(self::ENTITY_TYPE, $id, $deleted);
        $this->operationLogService->recordCurrentAction(self::ENTITY_TYPE, 'case.delete', self::ENTITY_TYPE, $deleted, '案例已删除');

        return $deleted;
    }

    public function batchRemove(array|string $ids, ?array $operator): array
    {
        $normalizedIds = is_array($ids) ? $ids : explode(',', (string) $ids);
        $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $normalizedIds), static fn (int $v): bool => $v > 0)));
        if ($normalizedIds === []) {
            throw new BusinessException('案例 ID 列表不能为空', ErrorCode::INVALID_PARAMS);
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
            throw new BusinessException('案例不存在', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction(
            self::ENTITY_TYPE,
            'case.batch_delete',
            self::ENTITY_TYPE . '_batch',
            ['id' => implode(',', array_map(static fn (array $item): string => (string) ($item['id'] ?? 0), $deletedItems))],
            '案例批量删除已完成'
        );

        return [
            'ids' => array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $deletedItems),
            'deleted_count' => count($deletedItems),
            'items' => $deletedItems,
        ];
    }

    public function categoryTree(): array
    {
        return $this->caseRepository->categoryTree(false, true);
    }

    public function createCategory(array $input): array
    {
        $payload = $this->normalizeCategoryPayload($input);
        $this->assertValidCategoryParent((int) $payload['parent_id']);

        $record = $this->caseRepository->createCategory($payload);
        $this->sharedTranslationPipelineService->syncEntity('case_category', (int) ($record['id'] ?? 0));
        $this->operationLogService->recordCurrentAction(self::ENTITY_TYPE, 'case.category.create', 'case_category', $record, '案例分类已创建');

        return $this->caseRepository->findCategory((int) ($record['id'] ?? 0)) ?? $record;
    }

    public function updateCategory(int $id, array $input): array
    {
        $existing = $this->caseRepository->findCategory($id);
        if ($existing === null) {
            throw new BusinessException('案例分类不存在', ErrorCode::NOT_FOUND);
        }

        $payload = $this->normalizeCategoryPayload(array_merge($existing, $input));
        $this->assertValidCategoryParent((int) $payload['parent_id'], $id);

        $updated = $this->caseRepository->updateCategory($id, $payload);
        if ($updated === null) {
            throw new BusinessException('案例分类不存在', ErrorCode::NOT_FOUND);
        }

        $this->sharedTranslationPipelineService->syncEntity('case_category', $id);
        $updated = $this->caseRepository->findCategory($id) ?? $updated;
        $this->operationLogService->recordCurrentAction(self::ENTITY_TYPE, 'case.category.update', 'case_category', $updated, '案例分类已更新');

        return $updated;
    }

    public function deleteCategory(int $id): array
    {
        $existing = $this->caseRepository->findCategory($id);
        if ($existing === null) {
            throw new BusinessException('案例分类不存在', ErrorCode::NOT_FOUND);
        }

        $node = $this->findCategoryNode($this->caseRepository->categoryTree(false, true), $id);
        if ($node !== null && !empty($node['children'])) {
            throw new BusinessException('分类下仍有子分类，不能删除', ErrorCode::INVALID_PARAMS);
        }

        if ((int) ($node['content_total_count'] ?? 0) > 0) {
            throw new BusinessException('分类下仍有案例，不能删除', ErrorCode::INVALID_PARAMS);
        }

        $deleted = $this->caseRepository->deleteCategory($id);
        if ($deleted === null) {
            throw new BusinessException('案例分类不存在', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction(self::ENTITY_TYPE, 'case.category.delete', 'case_category', $deleted, '案例分类已删除');

        return $deleted;
    }

    private function normalizePayload(array $input, ?array $operator, bool $isCreate): array
    {
        $title = trim((string) ($input['title_zh'] ?? ''));
        if ($title === '') {
            throw new BusinessException('案例标题不能为空', ErrorCode::INVALID_PARAMS);
        }

        $publishStatus = (string) ($input['publish_status'] ?? 'draft');
        $content = (string) ($input['content_zh'] ?? '');
        $tags = (string) ($input['case_tags'] ?? '');
        $meta = $this->contentAutoMetaService->enrich([
            'entity_type' => self::ENTITY_TYPE,
            'title' => $title,
            'category_name' => $this->resolveCategoryName((int) ($input['category_id'] ?? 0)),
            'summary' => (string) ($input['summary_zh'] ?? ''),
            'content' => $content,
            'seo_title' => (string) ($input['seo_title'] ?? ''),
            'seo_keywords' => (string) ($input['seo_keywords'] ?? ''),
            'seo_description' => (string) ($input['seo_description'] ?? ''),
            'publish_status' => $publishStatus,
        ]);

        return [
            'category_id' => (int) ($input['category_id'] ?? 0),
            'title_zh' => $title,
            'summary_zh' => $meta['summary'],
            'content_zh' => $content,
            'country_code' => (string) ($input['country_code'] ?? ''),
            'case_tags' => $tags,
            'related_solution_ids' => $this->normalizeJsonField($input['related_solution_ids'] ?? []),
            'related_product_ids' => $this->normalizeJsonField($input['related_product_ids'] ?? []),
            'publish_status' => in_array($publishStatus, ['draft', 'published', 'offline'], true) ? $publishStatus : 'draft',
            'translation_status' => (string) ($input['translation_status'] ?? 'pending'),
            'seo_status' => (string) ($input['seo_status'] ?? 'pending'),
            'is_home_featured' => !empty($input['is_home_featured']) ? 1 : 0,
            'manual_sort' => (int) ($input['manual_sort'] ?? 0),
            'slug' => $this->buildSlug('', $title, self::ENTITY_TYPE, (int) ($input['id'] ?? 0)),
            'seo_title' => $this->defaultSeoTitle($meta['seo_title'], $title),
            'seo_keywords' => $this->defaultSeoKeywords($meta['seo_keywords'], $tags, $title),
            'seo_description' => $this->defaultSeoDescription($meta['seo_description'], $meta['summary'], $content, $title),
            'media_gallery' => $this->normalizeMediaGallery($input['media_gallery'] ?? null),
            'publish_time' => $publishStatus === 'published' ? (string) ($input['publish_time'] ?? date('Y-m-d H:i:s')) : null,
            'created_by' => $isCreate ? (isset($operator['id']) ? (int) $operator['id'] : null) : (int) ($input['created_by'] ?? 0),
            'updated_by' => isset($operator['id']) ? (int) $operator['id'] : null,
            'created_at' => (string) ($input['created_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function resolveCategoryName(int $categoryId): string
    {
        if ($categoryId <= 0) {
            return '';
        }

        $category = $this->caseRepository->findCategory($categoryId);
        return trim((string) ($category['name_zh'] ?? ''));
    }

    private function normalizeJsonField(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return '[]';
    }

    private function buildSlug(string $slug, string $fallback, string $prefix, int $excludeId = 0): string
    {
        $baseSlug = $this->normalizeSlug($slug, $fallback, $prefix);
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->caseRepository->slugExists($candidate, $excludeId)) {
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

    private function defaultSeoKeywords(string $value, string $tagValue, string $fallback): string
    {
        $value = trim($value);
        if ($value !== '') {
            return $value;
        }

        $tagValue = trim($tagValue);
        return $tagValue !== '' ? $tagValue : $fallback;
    }

    private function defaultSeoDescription(string $value, string $summary, string $content, string $fallback): string
    {
        $value = trim($value);
        if ($value !== '') {
            return $value;
        }

        $summary = trim($summary);
        if ($summary !== '') {
            return $summary;
        }

        $plain = trim(strip_tags($content));
        if ($plain !== '') {
            return mb_substr($plain, 0, 120);
        }

        return $fallback;
    }

    private function normalizeCategoryPayload(array $input): array
    {
        $name = trim((string) ($input['name_zh'] ?? ''));
        if ($name === '') {
            throw new BusinessException('案例分类名称不能为空', ErrorCode::INVALID_PARAMS);
        }

        return [
            'parent_id' => max(0, (int) ($input['parent_id'] ?? 0)),
            'name_zh' => $name,
            'sort' => (int) ($input['sort'] ?? 0),
            'is_enabled' => array_key_exists('is_enabled', $input) ? (!empty($input['is_enabled']) ? 1 : 0) : 1,
        ];
    }

    private function assertValidCategoryParent(int $parentId, int $currentId = 0): void
    {
        if ($parentId <= 0) {
            return;
        }

        $parent = $this->caseRepository->findCategory($parentId);
        if ($parent === null) {
            throw new BusinessException('父级分类不存在', ErrorCode::INVALID_PARAMS);
        }

        if ($currentId > 0) {
            if ($parentId === $currentId) {
                throw new BusinessException('父级分类不能选择自己', ErrorCode::INVALID_PARAMS);
            }

            $subtree = $this->findCategoryNode($this->caseRepository->categoryTree(false), $currentId);
            if ($subtree !== null && $this->findCategoryNode([$subtree], $parentId) !== null) {
                throw new BusinessException('父级分类不能选择当前分类的子级', ErrorCode::INVALID_PARAMS);
            }
        }

        $parentDepth = $this->categoryDepth($parentId);
        $subtreeHeight = $currentId > 0 ? $this->categorySubtreeHeight($currentId) : 1;
        if ($parentDepth + $subtreeHeight > 3) {
            throw new BusinessException('分类层级最多支持三级', ErrorCode::INVALID_PARAMS);
        }
    }

    private function categoryDepth(int $categoryId): int
    {
        $depth = 0;
        $currentId = $categoryId;
        $visited = [];
        while ($currentId > 0) {
            if (isset($visited[$currentId])) {
                break;
            }
            $visited[$currentId] = true;
            $depth++;
            $record = $this->caseRepository->findCategory($currentId);
            if ($record === null) {
                break;
            }

            $currentId = (int) ($record['parent_id'] ?? 0);
        }

        return $depth;
    }

    private function categorySubtreeHeight(int $categoryId): int
    {
        $node = $this->findCategoryNode($this->caseRepository->categoryTree(false), $categoryId);
        if ($node === null) {
            return 1;
        }

        return $this->categoryNodeHeight($node);
    }

    private function categoryNodeHeight(array $node, array $visited = []): int
    {
        $nodeId = (int) ($node['id'] ?? 0);
        if ($nodeId > 0 && isset($visited[$nodeId])) {
            return 1;
        }

        if ($nodeId > 0) {
            $visited[$nodeId] = true;
        }

        $height = 1;
        foreach (($node['children'] ?? []) as $child) {
            $height = max($height, 1 + $this->categoryNodeHeight($child, $visited));
        }

        return $height;
    }

    private function findCategoryNode(array $tree, int $categoryId, array $visited = []): ?array
    {
        foreach ($tree as $node) {
            $nodeId = (int) ($node['id'] ?? 0);
            if ($nodeId === $categoryId) {
                return $node;
            }

            if ($nodeId > 0 && isset($visited[$nodeId])) {
                continue;
            }

            $nextVisited = $visited;
            if ($nodeId > 0) {
                $nextVisited[$nodeId] = true;
            }

            $child = $this->findCategoryNode($node['children'] ?? [], $categoryId, $nextVisited);
            if ($child !== null) {
                return $child;
            }
        }

        return null;
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
            $fallbackCover = ContentCoverFallbackResolver::resolve(self::ENTITY_TYPE, $item);
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
