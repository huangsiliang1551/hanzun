<?php

declare(strict_types=1);

namespace app\service\content;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\ArticleRepository;
use app\repository\MediaGalleryRepository;
use app\service\log\OperationLogService;
use app\service\translation\SharedTranslationPipelineService;

final class ArticleService
{
    public function __construct(
        private readonly ArticleRepository $articleRepository = new ArticleRepository(),
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
        $result = $this->articleRepository->list($query);
        $result['items'] = $this->attachCoverAssets($result['items'] ?? [], 'article');

        return array_merge($result, [
            'filters' => ['publish_status', 'content_type', 'category_id', 'is_home_featured', 'country_code', 'keyword'],
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
        $article = $this->articleRepository->find($id);
        if ($article === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $article['media_gallery'] = $this->mediaGalleryRepository->list('article', $id);
        $article = $this->attachSingleCoverAsset($article, $article['media_gallery'][0] ?? null);

        return $article;
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
        $record = $this->articleRepository->create($this->normalizePayload($input, $operator, true));
        if (!empty($input['media_gallery'])) {
            $this->mediaGalleryRepository->replace('article', (int) ($record['id'] ?? 0), $input['media_gallery']);
        }
        $record = $this->contentPipelineService->sync('article', $record);
        $this->contentWorkflowService->touchDraft('article', $record, $operator, 'article created');
        $this->operationLogService->recordCurrentAction('article', 'article.create', 'article', $record, 'article created');

        return $record;
    }

    public function update(int $id, array $input, ?array $operator): array
    {
        $existing = $this->articleRepository->find($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $updated = $this->articleRepository->update($id, $this->normalizePayload(array_merge($existing, $input), $operator, false));
        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        if (array_key_exists('media_gallery', $input)) {
            $this->mediaGalleryRepository->replace('article', $id, $input['media_gallery'] ?? []);
        }
        $updated = $this->contentPipelineService->sync('article', $updated);
        $this->contentWorkflowService->touchDraft('article', $updated, $operator, 'article draft updated');
        $this->operationLogService->recordCurrentAction('article', 'article.update', 'article', $updated, 'article updated');

        return $updated;
    }

    public function publish(int $id, string $publishStatus, ?array $operator): array
    {
        if (!in_array($publishStatus, ['draft', 'published', 'offline'], true)) {
            throw new BusinessException('invalid status transition', ErrorCode::INVALID_STATUS_TRANSITION);
        }

        $existing = $this->articleRepository->find($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        if ($publishStatus === 'published') {
            $updated = $this->articleRepository->update(
                $id,
                $this->normalizePayload(array_merge($existing, ['publish_status' => 'published']), $operator, false)
            );
        } else {
            $updated = $this->articleRepository->updatePublishStatus(
                $id,
                $publishStatus,
                null,
                isset($operator['id']) ? (int) $operator['id'] : null
            );
        }
        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $updated = $this->contentPipelineService->sync('article', $updated, false);
        if ($publishStatus === 'published') {
            $this->contentWorkflowService->publish('article', $updated, $operator);
        } else {
            $this->contentWorkflowService->touchDraft('article', $updated, $operator, 'article publish status updated');
        }
        $this->operationLogService->recordCurrentAction('article', 'article.publish', 'article', $updated, 'article publish status updated');

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
            $existing = $this->articleRepository->find($id);
            if ($existing === null) {
                continue;
            }

            if ($publishStatus === 'published') {
                $updated = $this->articleRepository->update(
                    $id,
                    $this->normalizePayload(array_merge($existing, ['publish_status' => 'published']), $operator, false)
                );
            } else {
                $updated = $this->articleRepository->updatePublishStatus(
                    $id,
                    $publishStatus,
                    null,
                    isset($operator['id']) ? (int) $operator['id'] : null
                );
            }
            if ($updated === null) {
                continue;
            }

            $updated = $this->contentPipelineService->sync('article', $updated, false);
            if ($publishStatus === 'published') {
                $this->contentWorkflowService->publish('article', $updated, $operator);
            } else {
                $this->contentWorkflowService->touchDraft('article', $updated, $operator, 'article batch publish status updated');
            }
            $updatedItems[] = $updated;
        }

        if ($updatedItems === []) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction(
            'article',
            'article.batch_publish',
            'article_batch',
            ['id' => implode(',', array_map(static fn (array $item): string => (string) ($item['id'] ?? 0), $updatedItems))],
            'article batch publish status updated'
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
        $article = $this->articleRepository->find($id);
        if ($article === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        return $this->contentWorkflowService->workflow('article', $article);
    }

    public function restoreLive(int $id, ?array $operator): array
    {
        $article = $this->articleRepository->find($id);
        if ($article === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $restored = $this->contentWorkflowService->restoreLive(
            'article',
            $article,
            fn (array $payload): ?array => $this->articleRepository->update($id, $payload),
            $operator
        );
        $this->operationLogService->recordCurrentAction('article', 'article.restore_live', 'article', $restored, 'article restored from live');

        return $restored;
    }

    public function remove(int $id, ?array $operator): array
    {
        $this->mediaGalleryRepository->deleteByEntity('article', $id);
        $deleted = $this->articleRepository->delete($id);
        if ($deleted === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('article', 'article.delete', 'article', $deleted, 'article deleted');

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
            'article',
            'article.batch_delete',
            'article_batch',
            ['id' => implode(',', array_map(static fn (array $item): string => (string) ($item['id'] ?? 0), $deletedItems))],
            'article batch deleted'
        );

        return [
            'ids' => array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $deletedItems),
            'deleted_count' => count($deletedItems),
            'items' => $deletedItems,
        ];
    }

    public function categoryTree(): array
    {
        return $this->articleRepository->categoryTree(false, true);
    }

    public function createCategory(array $input): array
    {
        $payload = $this->normalizeCategoryPayload($input);
        $this->assertValidCategoryParent((int) $payload['parent_id']);

        $record = $this->articleRepository->createCategory($payload);
        $this->sharedTranslationPipelineService->syncEntity('article_category', (int) ($record['id'] ?? 0));
        $this->operationLogService->recordCurrentAction('article', 'article.category.create', 'article_category', $record, 'article category created');

        return $this->articleRepository->findCategory((int) ($record['id'] ?? 0)) ?? $record;
    }

    public function updateCategory(int $id, array $input): array
    {
        $existing = $this->articleRepository->findCategory($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $payload = $this->normalizeCategoryPayload(array_merge($existing, $input));
        $this->assertValidCategoryParent((int) $payload['parent_id'], $id);

        $updated = $this->articleRepository->updateCategory($id, $payload);
        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->sharedTranslationPipelineService->syncEntity('article_category', $id);
        $updated = $this->articleRepository->findCategory($id) ?? $updated;
        $this->operationLogService->recordCurrentAction('article', 'article.category.update', 'article_category', $updated, 'article category updated');

        return $updated;
    }

    public function deleteCategory(int $id): array
    {
        $existing = $this->articleRepository->findCategory($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $node = $this->findCategoryNode($this->articleRepository->categoryTree(false, true), $id);
        if ($node !== null && !empty($node['children'])) {
            throw new BusinessException('category has child categories', ErrorCode::INVALID_PARAMS);
        }

        if ((int) ($node['content_total_count'] ?? 0) > 0) {
            throw new BusinessException('category has related content', ErrorCode::INVALID_PARAMS);
        }

        $deleted = $this->articleRepository->deleteCategory($id);
        if ($deleted === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('article', 'article.category.delete', 'article_category', $deleted, 'article category deleted');

        return $deleted;
    }

    private function normalizePayload(array $input, ?array $operator, bool $isCreate): array
    {
        $title = trim((string) ($input['title_zh'] ?? ''));
        $contentType = (string) ($input['content_type'] ?? 'article');
        if ($title === '' || !in_array($contentType, ['article', 'news', 'case', 'faq'], true)) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $publishStatus = (string) ($input['publish_status'] ?? 'draft');
        $content = (string) ($input['content_zh'] ?? '');
        $tags = (string) ($input['case_tags'] ?? '');
        $recordId = (int) ($input['id'] ?? 0);
        $meta = $this->contentAutoMetaService->enrich([
            'entity_type' => 'article',
            'title' => $title,
            'category_name' => $this->buildCategoryContext((int) ($input['category_id'] ?? 0), $contentType),
            'summary' => (string) ($input['summary_zh'] ?? ''),
            'content' => $content,
            'seo_title' => (string) ($input['seo_title'] ?? ''),
            'seo_keywords' => (string) ($input['seo_keywords'] ?? ''),
            'seo_description' => (string) ($input['seo_description'] ?? ''),
            'publish_status' => $publishStatus,
        ]);

        return [
            'category_id' => (int) ($input['category_id'] ?? 0),
            'content_type' => $contentType,
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
            'slug' => $this->buildSlug((string) ($input['slug'] ?? ''), $title, $contentType, $recordId, $contentType),
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

    private function buildCategoryContext(int $categoryId, string $contentType): string
    {
        $parts = [];
        $category = $categoryId > 0 ? $this->articleRepository->findCategory($categoryId) : null;
        $categoryName = trim((string) ($category['name_zh'] ?? ''));
        if ($categoryName !== '') {
            $parts[] = $categoryName;
        }

        $typeLabel = match ($contentType) {
            'case' => '案例',
            'faq' => '常见问题',
            default => '文章',
        };
        $parts[] = $typeLabel;

        return implode(' / ', array_filter($parts));
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

    private function buildSlug(string $slug, string $fallback, string $prefix, int $excludeId = 0, ?string $contentType = null): string
    {
        $baseSlug = $this->normalizeSlug($slug, $fallback, $prefix);
        $candidate = $baseSlug;
        $suffix = 2;

        while ($this->articleRepository->slugExists($candidate, $excludeId, $contentType)) {
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
            return mb_substr($summary, 0, 120);
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
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $scope = (string) ($input['content_type_scope'] ?? 'all');
        if (!in_array($scope, ['all', 'news', 'case'], true)) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        return [
            'parent_id' => max(0, (int) ($input['parent_id'] ?? 0)),
            'name_zh' => $name,
            'content_type_scope' => $scope,
            'sort' => (int) ($input['sort'] ?? 0),
            'is_enabled' => array_key_exists('is_enabled', $input) ? (!empty($input['is_enabled']) ? 1 : 0) : 1,
        ];
    }

    private function assertValidCategoryParent(int $parentId, int $currentId = 0): void
    {
        if ($parentId <= 0) {
            return;
        }

        $parent = $this->articleRepository->findCategory($parentId);
        if ($parent === null) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        if ($currentId > 0) {
            if ($parentId === $currentId) {
                throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
            }

            $subtree = $this->findCategoryNode($this->articleRepository->categoryTree(false), $currentId);
            if ($subtree !== null && $this->findCategoryNode([$subtree], $parentId) !== null) {
                throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
            }
        }

        $parentDepth = $this->categoryDepth($parentId);
        $subtreeHeight = $currentId > 0 ? $this->categorySubtreeHeight($currentId) : 1;
        if ($parentDepth + $subtreeHeight > 3) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }
    }

    private function categoryDepth(int $categoryId): int
    {
        $depth = 0;
        $currentId = $categoryId;
        while ($currentId > 0) {
            $depth++;
            $record = $this->articleRepository->findCategory($currentId);
            if ($record === null) {
                break;
            }

            $currentId = (int) ($record['parent_id'] ?? 0);
        }

        return $depth;
    }

    private function categorySubtreeHeight(int $categoryId): int
    {
        $node = $this->findCategoryNode($this->articleRepository->categoryTree(false), $categoryId);
        if ($node === null) {
            return 1;
        }

        return $this->categoryNodeHeight($node);
    }

    private function categoryNodeHeight(array $node): int
    {
        $height = 1;
        foreach (($node['children'] ?? []) as $child) {
            $height = max($height, 1 + $this->categoryNodeHeight($child));
        }

        return $height;
    }

    private function findCategoryNode(array $tree, int $categoryId): ?array
    {
        foreach ($tree as $node) {
            if ((int) ($node['id'] ?? 0) === $categoryId) {
                return $node;
            }

            $child = $this->findCategoryNode($node['children'] ?? [], $categoryId);
            if ($child !== null) {
                return $child;
            }
        }

        return null;
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
            $fallbackCover = ContentCoverFallbackResolver::resolve('article', $item);
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
