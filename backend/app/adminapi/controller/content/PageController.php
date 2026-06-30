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
        $targetStatus = (string) ($result['publish_status'] ?? $request->input('publish_status', 'draft'));
        $result['generation_jobs'] = in_array($targetStatus, ['published', 'offline'], true)
            ? $this->queueEntityBuildJobs('batch_publish_page', 'page', $result['ids'] ?? [])
            : [];

        return $this->success($result, [], '批量发布状态已更新');
    }

    public function batchDelete(Request $request): array
    {
        $result = $this->pageService->batchRemove(
            $request->input('ids', []),
            current_user()
        );
        $buildItems = array_values(array_filter((array) ($result['items'] ?? []), static fn (array $item): bool => in_array((string) ($item['publish_status'] ?? ''), ['published', 'offline'], true)));
        $job = $buildItems !== []
            ? $this->siteBuildService->queueFullBuild('batch_delete_page', [], current_user())
            : null;
        $result['generation_jobs'] = is_array($job['job'] ?? null) ? [$job['job']] : [];

        return $this->success($result, [], '批量删除已完成');
    }

    public function store(Request $request): array
    {
        $result = $this->pageService->create([
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
        ], current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('create_page', 'page', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '单页已创建');
    }

    public function update(Request $request): array
    {
        $result = $this->pageService->update((int) $request->routeParam('id'), [
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
        ], current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('update_page', 'page', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '单页已更新');
    }

    public function delete(Request $request): array
    {
        $result = $this->pageService->remove((int) $request->routeParam('id'), current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueFullBuild('delete_page', [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '单页已删除');
    }

    public function publish(Request $request): array
    {
        $result = $this->pageService->publish((int) $request->routeParam('id'), (string) $request->input('publish_status', 'draft'), current_user());
        $targetStatus = (string) ($result['publish_status'] ?? '');
        $job = null;
        if ($targetStatus === 'published') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_page',
                'page',
                (int) ($result['id'] ?? 0),
                [],
                current_user()
            );
        } elseif ($targetStatus === 'offline') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_page_status_changed',
                'page',
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

        return $this->success($result, [], '已从线上版本恢复草稿');
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
