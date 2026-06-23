<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\PageService;
use app\service\system\SiteBuildService;

class PageController extends BaseAdminController
{
    public function __construct(
        private readonly PageService $pageService = new PageService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function index(Request $request): array
    {
        return $this->success($this->pageService->list([
            'publish_status' => $request->input('publish_status'),
            'page_type' => $request->input('page_type'),
            'keyword' => $request->input('keyword'),
            'page' => $request->input('page'),
            'page_size' => $request->input('page_size'),
            'sort_field' => $request->input('sort_field'),
            'sort_order' => $request->input('sort_order'),
        ]));
    }

    public function show(Request $request): array
    {
        return $this->success($this->pageService->detail((int) $request->routeParam('id')));
    }

    public function bootstrap(Request $request): array
    {
        if ((int) $request->routeParam('id') <= 0) {
            return $this->success($this->pageService->adminBootstrap([
                'publish_status' => $request->input('publish_status'),
                'page_type' => $request->input('page_type'),
                'keyword' => $request->input('keyword'),
                'page' => $request->input('page'),
                'page_size' => $request->input('page_size'),
                'sort_field' => $request->input('sort_field'),
                'sort_order' => $request->input('sort_order'),
            ], (int) $request->input('preferred_id', 0)));
        }

        return $this->success($this->pageService->bootstrap((int) $request->routeParam('id')));
    }

    public function batchPublish(Request $request): array
    {
        $result = $this->pageService->batchPublish(
            $request->input('ids', []),
            (string) $request->input('publish_status', 'draft'),
            current_user()
        );
        $job = $this->siteBuildService->queueFullBuild('batch_publish_page', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success(
            $result,
            [],
            'batch publish updated'
        );
    }

    public function batchDelete(Request $request): array
    {
        $result = $this->pageService->batchRemove(
            $request->input('ids', []),
            current_user()
        );
        $job = $this->siteBuildService->queueFullBuild('batch_delete_page', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success(
            $result,
            [],
            'batch delete completed'
        );
    }

    public function store(Request $request): array
    {
        return $this->success($this->pageService->create([
            'page_type' => $request->input('page_type'),
            'title_zh' => $request->input('title_zh'),
            'summary_zh' => $request->input('summary_zh'),
            'content_zh' => $request->input('content_zh'),
            'publish_status' => $request->input('publish_status'),
            'translation_status' => $request->input('translation_status'),
            'seo_status' => $request->input('seo_status'),
            'slug' => $request->input('slug'),
            'seo_title' => $request->input('seo_title'),
            'seo_keywords' => $request->input('seo_keywords'),
            'seo_description' => $request->input('seo_description'),
            'media_gallery' => $request->input('media_gallery'),
        ], current_user()), [], 'create success');
    }

    public function update(Request $request): array
    {
        return $this->success($this->pageService->update((int) $request->routeParam('id'), [
            'page_type' => $request->input('page_type'),
            'title_zh' => $request->input('title_zh'),
            'summary_zh' => $request->input('summary_zh'),
            'content_zh' => $request->input('content_zh'),
            'publish_status' => $request->input('publish_status'),
            'translation_status' => $request->input('translation_status'),
            'seo_status' => $request->input('seo_status'),
            'slug' => $request->input('slug'),
            'seo_title' => $request->input('seo_title'),
            'seo_keywords' => $request->input('seo_keywords'),
            'seo_description' => $request->input('seo_description'),
            'media_gallery' => $request->input('media_gallery'),
        ], current_user()), [], 'update success');
    }

    public function delete(Request $request): array
    {
        $result = $this->pageService->remove((int) $request->routeParam('id'), current_user());
        $job = $this->siteBuildService->queueFullBuild('delete_page', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success(
            $result,
            [],
            'delete success'
        );
    }

    public function publish(Request $request): array
    {
        $result = $this->pageService->publish((int) $request->routeParam('id'), (string) $request->input('publish_status', 'draft'), current_user());
        if ((string) ($result['publish_status'] ?? '') === 'published') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_page',
                'page',
                (int) ($result['id'] ?? 0),
                [],
                current_user()
            );
        } else {
            $job = $this->siteBuildService->queueFullBuild('publish_page_status_changed', [], current_user());
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
        return $this->success($this->pageService->workflow((int) $request->routeParam('id')));
    }

    public function restoreLive(Request $request): array
    {
        $result = $this->pageService->restoreLive((int) $request->routeParam('id'), current_user());
        $job = $this->siteBuildService->queueIncrementalBuild(
            'restore_live_page',
            'page',
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
}
