<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\ProductService;
use app\service\system\SiteBuildService;

class ProductController extends BaseAdminController
{
    public function __construct(
        private readonly ProductService $productService = new ProductService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function index(Request $request): array
    {
        return $this->success($this->productService->list([
            'publish_status' => $request->input('publish_status'),
            'business_status' => $request->input('business_status'),
            'category_id' => $request->input('category_id'),
            'is_home_featured' => $request->input('is_home_featured'),
            'keyword' => $request->input('keyword'),
            'page' => $request->input('page'),
            'page_size' => $request->input('page_size'),
            'sort_field' => $request->input('sort_field'),
            'sort_order' => $request->input('sort_order'),
        ]));
    }

    public function show(Request $request): array
    {
        return $this->success($this->productService->detail((int) $request->routeParam('id')));
    }

    public function bootstrap(Request $request): array
    {
        return $this->success($this->productService->bootstrap((int) $request->routeParam('id')));
    }

    public function lookups(): array
    {
        return $this->success($this->productService->lookups());
    }

    public function batchPublish(Request $request): array
    {
        $result = $this->productService->batchPublish(
            $request->input('ids', []),
            (string) $request->input('publish_status', 'draft'),
            current_user()
        );
        $targetStatus = (string) ($result['publish_status'] ?? $request->input('publish_status', 'draft'));
        $result['generation_jobs'] = in_array($targetStatus, ['published', 'offline'], true)
            ? $this->queueEntityBuildJobs('batch_publish_product', 'product', $result['ids'] ?? [])
            : [];

        return $this->success($result, [], '批量发布状态已更新');
    }

    public function batchDelete(Request $request): array
    {
        $result = $this->productService->batchRemove(
            $request->input('ids', []),
            current_user()
        );
        $buildItems = array_values(array_filter((array) ($result['items'] ?? []), static fn (array $item): bool => in_array((string) ($item['publish_status'] ?? ''), ['published', 'offline'], true)));
        $job = $buildItems !== []
            ? $this->siteBuildService->queueFullBuild('batch_delete_product', [], current_user())
            : null;
        $result['generation_jobs'] = is_array($job['job'] ?? null) ? [$job['job']] : [];

        return $this->success($result, [], '批量删除已完成');
    }

    public function store(Request $request): array
    {
        $result = $this->productService->create([
            'category_id' => $request->input('category_id'),
            'name_zh' => $request->input('name_zh'),
            'summary_zh' => $request->input('summary_zh'),
            'content_zh' => $request->input('content_zh'),
            'business_status' => $request->input('business_status'),
            'publish_status' => $request->input('publish_status'),
            'translation_status' => $request->input('translation_status'),
            'seo_status' => $request->input('seo_status'),
            'is_home_featured' => $request->input('is_home_featured'),
            'manual_sort' => $request->input('manual_sort'),
            'slug' => $request->input('slug'),
            'seo_title' => $request->input('seo_title'),
            'seo_keywords' => $request->input('seo_keywords'),
            'seo_description' => $request->input('seo_description'),
            'media_gallery' => $request->input('media_gallery'),
        ], current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('create_product', 'product', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '产品已创建');
    }

    public function update(Request $request): array
    {
        $result = $this->productService->update((int) $request->routeParam('id'), [
            'category_id' => $request->input('category_id'),
            'name_zh' => $request->input('name_zh'),
            'summary_zh' => $request->input('summary_zh'),
            'content_zh' => $request->input('content_zh'),
            'business_status' => $request->input('business_status'),
            'publish_status' => $request->input('publish_status'),
            'translation_status' => $request->input('translation_status'),
            'seo_status' => $request->input('seo_status'),
            'is_home_featured' => $request->input('is_home_featured'),
            'manual_sort' => $request->input('manual_sort'),
            'slug' => $request->input('slug'),
            'seo_title' => $request->input('seo_title'),
            'seo_keywords' => $request->input('seo_keywords'),
            'seo_description' => $request->input('seo_description'),
            'media_gallery' => $request->input('media_gallery'),
        ], current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('update_product', 'product', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '产品已更新');
    }

    public function delete(Request $request): array
    {
        $result = $this->productService->remove((int) $request->routeParam('id'), current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueFullBuild('delete_product', [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '产品已删除');
    }

    public function publish(Request $request): array
    {
        $result = $this->productService->publish((int) $request->routeParam('id'), (string) $request->input('publish_status', 'draft'), current_user());
        $targetStatus = (string) ($result['publish_status'] ?? '');
        $job = null;
        if ($targetStatus === 'published') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_product',
                'product',
                (int) ($result['id'] ?? 0),
                [],
                current_user()
            );
        } elseif ($targetStatus === 'offline') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_product_status_changed',
                'product',
                (int) ($result['id'] ?? 0),
                [],
                current_user()
            );
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '发布状态已更新');
    }

    public function workflow(Request $request): array
    {
        return $this->success($this->productService->workflow((int) $request->routeParam('id')));
    }

    public function restoreLive(Request $request): array
    {
        $result = $this->productService->restoreLive((int) $request->routeParam('id'), current_user());
        $job = $this->siteBuildService->queueIncrementalBuild(
            'restore_live_product',
            'product',
            (int) ($result['id'] ?? 0),
            [],
            current_user()
        );
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result, [], '已从线上版本恢复草稿');
    }

    public function categoryTree(): array
    {
        return $this->success($this->productService->categoryTree());
    }

    public function storeCategory(Request $request): array
    {
        $result = $this->productService->createCategory([
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'slug' => $request->input('slug'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $job = $this->siteBuildService->queueFullBuild('create_product_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '分类已创建');
    }

    public function updateCategory(Request $request): array
    {
        $result = $this->productService->updateCategory((int) $request->routeParam('id'), [
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'slug' => $request->input('slug'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $job = $this->siteBuildService->queueFullBuild('update_product_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '分类已更新');
    }

    public function deleteCategory(Request $request): array
    {
        $result = $this->productService->deleteCategory((int) $request->routeParam('id'));
        $job = $this->siteBuildService->queueFullBuild('delete_product_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '分类已删除');
    }

    private function queueEntityBuildJobs(string $triggerSource, string $entityType, array $ids): array
    {
        $jobs = [];
        foreach (array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0))) as $id) {
            $job = $this->siteBuildService->queueIncrementalBuild($triggerSource, $entityType, $id, [], current_user());
            if (is_array($job['job'] ?? null)) {
                $jobs[] = $job['job'];
            }
        }

        return $jobs;
    }
}
