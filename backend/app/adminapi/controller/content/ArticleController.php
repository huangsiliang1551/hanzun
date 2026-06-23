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
        $job = $this->siteBuildService->queueFullBuild('batch_publish_article', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success(
            $result,
            [],
            'batch publish updated'
        );
    }

    public function batchDelete(Request $request): array
    {
        $result = $this->articleService->batchRemove(
            $request->input('ids', []),
            current_user()
        );
        $job = $this->siteBuildService->queueFullBuild('batch_delete_article', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success(
            $result,
            [],
            'batch delete completed'
        );
    }

    public function store(Request $request): array
    {
        return $this->success($this->articleService->create([
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
        ], current_user()), [], 'create success');
    }

    public function update(Request $request): array
    {
        return $this->success($this->articleService->update((int) $request->routeParam('id'), [
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
        ], current_user()), [], 'update success');
    }

    public function delete(Request $request): array
    {
        $result = $this->articleService->remove((int) $request->routeParam('id'), current_user());
        $job = $this->siteBuildService->queueFullBuild('delete_article', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success(
            $result,
            [],
            'delete success'
        );
    }

    public function publish(Request $request): array
    {
        $result = $this->articleService->publish((int) $request->routeParam('id'), (string) $request->input('publish_status', 'draft'), current_user());
        if ((string) ($result['publish_status'] ?? '') === 'published') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_article',
                'article',
                (int) ($result['id'] ?? 0),
                [],
                current_user()
            );
        } else {
            $job = $this->siteBuildService->queueFullBuild('publish_article_status_changed', [], current_user());
        }
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success(
            $result,
            [],
            'publish status updated'
        );
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

        return $this->success(
            $result,
            [],
            'restore live success'
        );
    }

    public function categoryTree(): array
    {
        return $this->success($this->articleService->categoryTree());
    }

    public function storeCategory(Request $request): array
    {
        return $this->success($this->articleService->createCategory([
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'content_type_scope' => $request->input('content_type_scope'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]), [], 'create success');
    }

    public function updateCategory(Request $request): array
    {
        return $this->success($this->articleService->updateCategory((int) $request->routeParam('id'), [
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'content_type_scope' => $request->input('content_type_scope'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]), [], 'update success');
    }

    public function deleteCategory(Request $request): array
    {
        return $this->success(
            $this->articleService->deleteCategory((int) $request->routeParam('id')),
            [],
            'delete success'
        );
    }
}
