<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\ArticleService;
use app\service\system\SiteBuildService;

class ArticleController extends BaseAdminController
{
    public function __construct(
        private readonly ArticleService $articleService = new ArticleService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function index(Request $request): array
    {
        return $this->success($this->articleService->list([
            'publish_status' => $request->input('publish_status'),
            'content_type' => $request->input('content_type'),
            'country_code' => $request->input('country_code'),
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
        return $this->success($this->articleService->detail((int) $request->routeParam('id')));
    }

    public function bootstrap(Request $request): array
    {
        return $this->success($this->articleService->bootstrap((int) $request->routeParam('id')));
    }

    public function lookups(): array
    {
        return $this->success($this->articleService->lookups());
    }

    public function batchPublish(Request $request): array
    {
        $result = $this->articleService->batchPublish(
            $request->input('ids', []),
            (string) $request->input('publish_status', 'draft'),
            current_user()
        );
        $targetStatus = (string) ($result['publish_status'] ?? $request->input('publish_status', 'draft'));
        $result['generation_jobs'] = in_array($targetStatus, ['published', 'offline'], true)
            ? $this->queueEntityBuildJobs('batch_publish_article', 'article', $result['ids'] ?? [])
            : [];

        return $this->success($result, [], '批量发布状态已更新');
    }

    public function batchDelete(Request $request): array
    {
        $result = $this->articleService->batchRemove(
            $request->input('ids', []),
            current_user()
        );
        $buildItems = array_values(array_filter((array) ($result['items'] ?? []), static fn (array $item): bool => in_array((string) ($item['publish_status'] ?? ''), ['published', 'offline'], true)));
        $result['generation_jobs'] = $this->queueEntityBuildJobs(
            'batch_delete_article',
            'article',
            array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $buildItems)
        );

        return $this->success($result, [], '批量删除已完成');
    }

    public function store(Request $request): array
    {
        $result = $this->articleService->create([
            'category_id' => $request->input('category_id'),
            'content_type' => $request->input('content_type'),
            'title_zh' => $request->input('title_zh'),
            'summary_zh' => $request->input('summary_zh'),
            'content_zh' => $request->input('content_zh'),
            'country_code' => $request->input('country_code'),
            'case_tags' => $request->input('case_tags'),
            'related_solution_ids' => $request->input('related_solution_ids'),
            'related_product_ids' => $request->input('related_product_ids'),
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
            $job = $this->siteBuildService->queueIncrementalBuild('create_article', 'article', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '内容已创建');
    }

    public function update(Request $request): array
    {
        $result = $this->articleService->update((int) $request->routeParam('id'), [
            'category_id' => $request->input('category_id'),
            'content_type' => $request->input('content_type'),
            'title_zh' => $request->input('title_zh'),
            'summary_zh' => $request->input('summary_zh'),
            'content_zh' => $request->input('content_zh'),
            'country_code' => $request->input('country_code'),
            'case_tags' => $request->input('case_tags'),
            'related_solution_ids' => $request->input('related_solution_ids'),
            'related_product_ids' => $request->input('related_product_ids'),
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
            $job = $this->siteBuildService->queueIncrementalBuild('update_article', 'article', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '内容已更新');
    }

    public function delete(Request $request): array
    {
        $result = $this->articleService->remove((int) $request->routeParam('id'), current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('delete_article', 'article', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '内容已删除');
    }

    public function publish(Request $request): array
    {
        $result = $this->articleService->publish((int) $request->routeParam('id'), (string) $request->input('publish_status', 'draft'), current_user());
        $targetStatus = (string) ($result['publish_status'] ?? '');
        $job = null;
        if ($targetStatus === 'published') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_article',
                'article',
                (int) ($result['id'] ?? 0),
                [],
                current_user()
            );
        } elseif ($targetStatus === 'offline') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_article_status_changed',
                'article',
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
        return $this->success($this->articleService->workflow((int) $request->routeParam('id')));
    }

    public function restoreLive(Request $request): array
    {
        $result = $this->articleService->restoreLive((int) $request->routeParam('id'), current_user());
        $job = $this->siteBuildService->queueIncrementalBuild(
            'restore_live_article',
            'article',
            (int) ($result['id'] ?? 0),
            [],
            current_user()
        );
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result, [], '已从线上版本恢复草稿');
    }

    public function categoryTree(): array
    {
        return $this->success($this->articleService->categoryTree());
    }

    public function storeCategory(Request $request): array
    {
        $result = $this->articleService->createCategory([
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'content_type_scope' => $request->input('content_type_scope'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $job = $this->siteBuildService->queueFullBuild('create_article_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '分类已创建');
    }

    public function updateCategory(Request $request): array
    {
        $result = $this->articleService->updateCategory((int) $request->routeParam('id'), [
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'content_type_scope' => $request->input('content_type_scope'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $job = $this->siteBuildService->queueFullBuild('update_article_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '分类已更新');
    }

    public function deleteCategory(Request $request): array
    {
        $result = $this->articleService->deleteCategory((int) $request->routeParam('id'));
        $job = $this->siteBuildService->queueFullBuild('delete_article_category', [], current_user());
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
