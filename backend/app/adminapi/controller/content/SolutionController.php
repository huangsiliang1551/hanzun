<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\SolutionService;
use app\service\system\SiteBuildService;

class SolutionController extends BaseAdminController
{
    public function __construct(
        private readonly SolutionService $solutionService = new SolutionService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function index(Request $request): array
    {
        return $this->success($this->solutionService->list([
            'publish_status' => $request->input('publish_status'),
            'category_id' => $request->input('category_id'),
            'is_home_featured' => $request->input('is_home_featured'),
            'pdf_status' => $request->input('pdf_status'),
            'keyword' => $request->input('keyword'),
            'page' => $request->input('page'),
            'page_size' => $request->input('page_size'),
            'sort_field' => $request->input('sort_field'),
            'sort_order' => $request->input('sort_order'),
        ]));
    }

    public function show(Request $request): array
    {
        return $this->success($this->solutionService->detail((int) $request->routeParam('id')));
    }

    public function bootstrap(Request $request): array
    {
        return $this->success($this->solutionService->bootstrap((int) $request->routeParam('id')));
    }

    public function lookups(): array
    {
        return $this->success($this->solutionService->lookups());
    }

    public function batchPublish(Request $request): array
    {
        $result = $this->solutionService->batchPublish(
            $request->input('ids', []),
            (string) $request->input('publish_status', 'draft'),
            current_user()
        );
        $job = $this->siteBuildService->queueFullBuild('batch_publish_solution', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success(
            $result,
            [],
            'batch publish updated'
        );
    }

    public function batchDelete(Request $request): array
    {
        $result = $this->solutionService->batchRemove(
            $request->input('ids', []),
            current_user()
        );
        $job = $this->siteBuildService->queueFullBuild('batch_delete_solution', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success(
            $result,
            [],
            'batch delete completed'
        );
    }

    public function store(Request $request): array
    {
        return $this->success($this->solutionService->create([
            'category_id' => $request->input('category_id'),
            'name_zh' => $request->input('name_zh'),
            'summary_zh' => $request->input('summary_zh'),
            'content_zh' => $request->input('content_zh'),
            'manual_asset_id' => $request->input('manual_asset_id'),
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
        return $this->success($this->solutionService->update((int) $request->routeParam('id'), [
            'category_id' => $request->input('category_id'),
            'name_zh' => $request->input('name_zh'),
            'summary_zh' => $request->input('summary_zh'),
            'content_zh' => $request->input('content_zh'),
            'manual_asset_id' => $request->input('manual_asset_id'),
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
        $result = $this->solutionService->remove((int) $request->routeParam('id'), current_user());
        $job = $this->siteBuildService->queueFullBuild('delete_solution', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success(
            $result,
            [],
            'delete success'
        );
    }

    public function publish(Request $request): array
    {
        $result = $this->solutionService->publish((int) $request->routeParam('id'), (string) $request->input('publish_status', 'draft'), current_user());
        if ((string) ($result['publish_status'] ?? '') === 'published') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_solution',
                'solution',
                (int) ($result['id'] ?? 0),
                [],
                current_user()
            );
        } else {
            $job = $this->siteBuildService->queueFullBuild('publish_solution_status_changed', [], current_user());
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
        return $this->success($this->solutionService->workflow((int) $request->routeParam('id')));
    }

    public function restoreLive(Request $request): array
    {
        $result = $this->solutionService->restoreLive((int) $request->routeParam('id'), current_user());
        $job = $this->siteBuildService->queueIncrementalBuild(
            'restore_live_solution',
            'solution',
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
        return $this->success($this->solutionService->categoryTree());
    }

    public function storeCategory(Request $request): array
    {
        return $this->success($this->solutionService->createCategory([
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'slug' => $request->input('slug'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]), [], 'create success');
    }

    public function updateCategory(Request $request): array
    {
        return $this->success($this->solutionService->updateCategory((int) $request->routeParam('id'), [
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'slug' => $request->input('slug'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]), [], 'update success');
    }

    public function deleteCategory(Request $request): array
    {
        return $this->success(
            $this->solutionService->deleteCategory((int) $request->routeParam('id')),
            [],
            'delete success'
        );
    }
}
