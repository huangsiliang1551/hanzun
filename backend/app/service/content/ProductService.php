<?php

declare(strict_types=1);

namespace app\service\content;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\MediaGalleryRepository;
use app\repository\ProductRepository;
use app\service\log\OperationLogService;
use app\service\translation\SharedTranslationPipelineService;

final class ProductService
{
    public function __construct(
        private readonly ProductRepository $productRepository = new ProductRepository(),
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
        $result = $this->productRepository->list($query);
        $result['items'] = $this->attachCoverAssets($result['items'] ?? [], 'product');

        return array_merge($result, [
            'filters' => ['publish_status', 'business_status', 'category_id', 'is_home_featured', 'keyword'],
        ]);
    }

    public function lookups(): array
    {
        return [
            'categories' => $this->categoryTree(),
        ];
    }

    public function detail(int $id): array
    {
        $product = $this->productRepository->find($id);
        if ($product === null) {
            throw new BusinessException('产品不存在', ErrorCode::NOT_FOUND);
        }

        $product['media_gallery'] = $this->mediaGalleryRepository->list('product', $id);
        $product = $this->attachSingleCoverAsset($product, $product['media_gallery'][0] ?? null);

        return $product;
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
        $record = $this->productRepository->create($this->normalizePayload($input, $operator, true));
        if (!empty($input['media_gallery'])) {
            $this->mediaGalleryRepository->replace('product', (int) ($record['id'] ?? 0), $input['media_gallery']);
        }
        $record = $this->contentPipelineService->sync('product', $record);
        $this->contentWorkflowService->touchDraft('product', $record, $operator, '产品草稿已创建');
        $this->operationLogService->recordCurrentAction('product', 'product.create', 'product', $record, '产品已创建');

        return $record;
    }

    public function update(int $id, array $input, ?array $operator): array
    {
        $existing = $this->productRepository->find($id);
        if ($existing === null) {
            throw new BusinessException('产品不存在', ErrorCode::NOT_FOUND);
        }

        $updated = $this->productRepository->update($id, $this->normalizePayload(array_merge($existing, $input), $operator, false));
        if ($updated === null) {
            throw new BusinessException('产品不存在', ErrorCode::NOT_FOUND);
        }

        if (array_key_exists('media_gallery', $input)) {
            $this->mediaGalleryRepository->replace('product', $id, $input['media_gallery'] ?? []);
        }
        $updated = $this->contentPipelineService->sync('product', $updated);
        $this->contentWorkflowService->touchDraft('product', $updated, $operator, '产品草稿已更新');
        $this->operationLogService->recordCurrentAction('product', 'product.update', 'product', $updated, '产品已更新');

        return $updated;
    }

    public function publish(int $id, string $publishStatus, ?array $operator): array
    {
        if (!in_array($publishStatus, ['draft', 'published', 'offline'], true)) {
            throw new BusinessException('发布状态无效', ErrorCode::INVALID_STATUS_TRANSITION);
        }

        $existing = $this->productRepository->find($id);
        if ($existing === null) {
            throw new BusinessException('产品不存在', ErrorCode::NOT_FOUND);
        }

        if ($publishStatus === 'published') {
            $updated = $this->productRepository->update(
                $id,
                $this->normalizePayload(array_merge($existing, ['publish_status' => 'published']), $operator, false)
            );
        } else {
            $updated = $this->productRepository->updatePublishStatus(
                $id,
                $publishStatus,
                null,
                isset($operator['id']) ? (int) $operator['id'] : null
            );
        }
        if ($updated === null) {
            throw new BusinessException('产品不存在', ErrorCode::NOT_FOUND);
        }

        $updated = $this->contentPipelineService->sync('product', $updated, false);
        if ($publishStatus === 'published') {
            $this->contentWorkflowService->publish('product', $updated, $operator);
        } else {
            $this->contentWorkflowService->touchDraft('product', $updated, $operator, '产品发布状态已切换为草稿或下线');
        }
        $this->operationLogService->recordCurrentAction('product', 'product.publish', 'product', $updated, '产品发布状态已更新');

        return $updated;
    }

    public function batchPublish(array|string $ids, string $publishStatus, ?array $operator): array
    {
        if (!in_array($publishStatus, ['draft', 'published', 'offline'], true)) {
            throw new BusinessException('发布状态无效', ErrorCode::INVALID_STATUS_TRANSITION);
        }

        $normalizedIds = $this->normalizeBatchIds($ids);
        if ($normalizedIds === []) {
            throw new BusinessException('产品 ID 列表不能为空', ErrorCode::INVALID_PARAMS);
        }

        $updatedItems = [];
        foreach ($normalizedIds as $id) {
            $existing = $this->productRepository->find($id);
            if ($existing === null) {
                continue;
            }

            if ($publishStatus === 'published') {
                $updated = $this->productRepository->update(
                    $id,
                    $this->normalizePayload(array_merge($existing, ['publish_status' => 'published']), $operator, false)
                );
            } else {
                $updated = $this->productRepository->updatePublishStatus(
                    $id,
                    $publishStatus,
                    null,
                    isset($operator['id']) ? (int) $operator['id'] : null
                );
            }
            if ($updated === null) {
                continue;
            }

            $updatedItems[] = $this->contentPipelineService->sync('product', $updated, false);
            if ($publishStatus === 'published') {
                $this->contentWorkflowService->publish('product', end($updatedItems), $operator);
            } else {
                $this->contentWorkflowService->touchDraft('product', end($updatedItems), $operator, '产品批量发布状态已切换为草稿或下线');
            }
        }

        if ($updatedItems === []) {
            throw new BusinessException('产品不存在', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction(
            'product',
            'product.batch_publish',
            'product_batch',
            ['id' => implode(',', array_map(static fn (array $item): string => (string) ($item['id'] ?? 0), $updatedItems))],
            '产品批量发布状态已更新'
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
        $product = $this->productRepository->find($id);
        if ($product === null) {
            throw new BusinessException('产品不存在', ErrorCode::NOT_FOUND);
        }

        return $this->contentWorkflowService->workflow('product', $product);
    }

    public function restoreLive(int $id, ?array $operator): array
    {
        $product = $this->productRepository->find($id);
        if ($product === null) {
            throw new BusinessException('产品不存在', ErrorCode::NOT_FOUND);
        }

        $restored = $this->contentWorkflowService->restoreLive(
            'product',
            $product,
            fn (array $payload): ?array => $this->productRepository->update($id, $payload),
            $operator
        );
        $this->operationLogService->recordCurrentAction('product', 'product.restore_live', 'product', $restored, '产品已从线上版本恢复草稿');

        return $restored;
    }

    public function remove(int $id, ?array $operator): array
    {
        $this->mediaGalleryRepository->deleteByEntity('product', $id);
        $deleted = $this->productRepository->delete($id);
        if ($deleted === null) {
            throw new BusinessException('产品不存在', ErrorCode::NOT_FOUND);
        }

        (new ContentCleanupService())->purgeEntity('product', $id, $deleted);
        $this->operationLogService->recordCurrentAction('product', 'product.delete', 'product', $deleted, '产品已删除');

        return $deleted;
    }

    public function batchRemove(array|string $ids, ?array $operator): array
    {
        $normalizedIds = $this->normalizeBatchIds($ids);
        if ($normalizedIds === []) {
            throw new BusinessException('产品 ID 列表不能为空', ErrorCode::INVALID_PARAMS);
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
            throw new BusinessException('产品不存在', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction(
            'product',
            'product.batch_delete',
            'product_batch',
            ['id' => implode(',', array_map(static fn (array $item): string => (string) ($item['id'] ?? 0), $deletedItems))],
            '产品批量删除已完成'
        );

        return [
            'ids' => array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $deletedItems),
            'deleted_count' => count($deletedItems),
            'items' => $deletedItems,
        ];
    }

    public function categoryTree(): array
    {
        return $this->productRepository->categoryTree(false, true);
    }

    public function createCategory(array $input): array
    {
        $payload = $this->normalizeCategoryPayload($input);
        $this->assertValidCategoryParent((int) $payload['parent_id']);

        $record = $this->productRepository->createCategory($payload);
        $this->sharedTranslationPipelineService->syncEntity('product_category', (int) ($record['id'] ?? 0));
        $this->operationLogService->recordCurrentAction('product', 'product.category.create', 'product_category', $record, '产品分类已创建');

        return $this->productRepository->findCategory((int) ($record['id'] ?? 0)) ?? $record;
    }

    public function updateCategory(int $id, array $input): array
    {
        $existing = $this->productRepository->findCategory($id);
        if ($existing === null) {
            throw new BusinessException('产品分类不存在', ErrorCode::NOT_FOUND);
        }

        $payload = $this->normalizeCategoryPayload(array_merge($existing, $input), $id);
        $this->assertValidCategoryParent((int) $payload['parent_id'], $id);

        $updated = $this->productRepository->updateCategory($id, $payload);
        if ($updated === null) {
            throw new BusinessException('产品分类不存在', ErrorCode::NOT_FOUND);
        }

        $this->sharedTranslationPipelineService->syncEntity('product_category', $id);
        $updated = $this->productRepository->findCategory($id) ?? $updated;
        $this->operationLogService->recordCurrentAction('product', 'product.category.update', 'product_category', $updated, '产品分类已更新');

        return $updated;
    }

    public function deleteCategory(int $id): array
    {
        $existing = $this->productRepository->findCategory($id);
        if ($existing === null) {
            throw new BusinessException('产品分类不存在', ErrorCode::NOT_FOUND);
        }

        $node = $this->findCategoryNode($this->productRepository->categoryTree(false, true), $id);
        if ($node !== null && !empty($node['children'])) {
            throw new BusinessException('分类下仍有子分类，不能删除', ErrorCode::INVALID_PARAMS);
        }

        if ((int) ($node['content_total_count'] ?? 0) > 0) {
            throw new BusinessException('分类下仍有产品，不能删除', ErrorCode::INVALID_PARAMS);
        }

        $deleted = $this->productRepository->deleteCategory($id);
        if ($deleted === null) {
            throw new BusinessException('产品分类不存在', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('product', 'product.category.delete', 'product_category', $deleted, '产品分类已删除');

        return $deleted;
    }

    private function normalizePayload(array $input, ?array $operator, bool $isCreate): array
    {
        $name = trim((string) ($input['name_zh'] ?? ''));
        if ($name === '') {
            throw new BusinessException('产品名称不能为空', ErrorCode::INVALID_PARAMS);
        }

        $publishStatus = (string) ($input['publish_status'] ?? 'draft');
        $businessStatus = (string) ($input['business_status'] ?? 'on_sale');
        $content = (string) ($input['content_zh'] ?? '');
        $recordId = (int) ($input['id'] ?? 0);
        $meta = $this->contentAutoMetaService->enrich([
            'entity_type' => 'product',
            'title' => $name,
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
            'sku' => (string) ($input['sku'] ?? ''),
            'name_zh' => $name,
            'summary_zh' => $meta['summary'],
            'content_zh' => $content,
            'business_status' => in_array($businessStatus, ['on_sale', 'off_sale', 'discontinued'], true) ? $businessStatus : 'on_sale',
            'publish_status' => in_array($publishStatus, ['draft', 'published', 'offline'], true) ? $publishStatus : 'draft',
            'translation_status' => (string) ($input['translation_status'] ?? 'pending'),
            'seo_status' => (string) ($input['seo_status'] ?? 'pending'),
            'is_home_featured' => !empty($input['is_home_featured']) ? 1 : 0,
            'manual_sort' => (int) ($input['manual_sort'] ?? 0),
            'slug' => $this->buildSlug((string) ($input['slug'] ?? ''), $name, 'product', $recordId),
            'seo_title' => $this->defaultSeoTitle($meta['seo_title'], $name),
            'seo_keywords' => $this->defaultSeoKeywords($meta['seo_keywords'], $name),
            'seo_description' => $this->defaultSeoDescription($meta['seo_description'], $meta['summary'], $content, $name),
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

        $category = $this->productRepository->findCategory($categoryId);
        return trim((string) ($category['name_zh'] ?? ''));
    }

    private function buildSlug(string $slug, string $fallback, string $prefix, int $excludeId = 0): string
    {
        $baseSlug = $this->normalizeSlug($slug, $fallback, $prefix);
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->productRepository->slugExists($candidate, $excludeId)) {
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

    private function normalizeCategoryPayload(array $input, int $excludeId = 0): array
    {
        $name = trim((string) ($input['name_zh'] ?? ''));
        if ($name === '') {
            throw new BusinessException('分类名称不能为空', ErrorCode::INVALID_PARAMS);
        }

        return [
            'parent_id' => max(0, (int) ($input['parent_id'] ?? 0)),
            'name_zh' => $name,
            'slug' => $this->buildCategorySlug((string) ($input['slug'] ?? ''), $name, $excludeId),
            'sort' => (int) ($input['sort'] ?? 0),
            'is_enabled' => array_key_exists('is_enabled', $input) ? (!empty($input['is_enabled']) ? 1 : 0) : 1,
        ];
    }

    private function buildCategorySlug(string $slug, string $fallback, int $excludeId = 0): string
    {
        $baseSlug = $this->normalizeSlug($slug, $fallback, 'product-category');
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->productRepository->categorySlugExists($candidate, $excludeId)) {
            $candidate = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function assertValidCategoryParent(int $parentId, int $currentId = 0): void
    {
        if ($parentId <= 0) {
            return;
        }

        $parent = $this->productRepository->findCategory($parentId);
        if ($parent === null) {
            throw new BusinessException('父级分类不存在', ErrorCode::INVALID_PARAMS);
        }

        if ($currentId > 0) {
            if ($parentId === $currentId) {
                throw new BusinessException('父级分类不能选择自己', ErrorCode::INVALID_PARAMS);
            }

            $subtree = $this->findCategoryNode($this->productRepository->categoryTree(false), $currentId);
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
            $record = $this->productRepository->findCategory($currentId);
            if ($record === null) {
                break;
            }

            $currentId = (int) ($record['parent_id'] ?? 0);
        }

        return $depth;
    }

    private function categorySubtreeHeight(int $categoryId): int
    {
        $node = $this->findCategoryNode($this->productRepository->categoryTree(false), $categoryId);
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
        $rawIds = is_array($ids) ? $ids : explode(',', (string) $ids);
        $normalized = [];

        foreach ($rawIds as $id) {
            $value = (int) $id;
            if ($value <= 0 || in_array($value, $normalized, true)) {
                continue;
            }

            $normalized[] = $value;
        }

        return $normalized;
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
            $fallbackCover = ContentCoverFallbackResolver::resolve('product', $item);
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
