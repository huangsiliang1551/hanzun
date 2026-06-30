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
        $targetStatus = (string) ($result['publish_status'] ?? $request->input('publish_status', 'draft'));
        $result['generation_jobs'] = in_array($targetStatus, ['published', 'offline'], true)
            ? $this->queueEntityBuildJobs('batch_publish_solution', 'solution', $result['ids'] ?? [])
            : [];

        return $this->success($result, [], '批量发布状态已更新');
    }

    public function batchDelete(Request $request): array
    {
        $result = $this->solutionService->batchRemove(
            $request->input('ids', []),
            current_user()
        );
        $buildItems = array_values(array_filter((array) ($result['items'] ?? []), static fn (array $item): bool => in_array((string) ($item['publish_status'] ?? ''), ['published', 'offline'], true)));
        $job = $buildItems !== []
            ? $this->siteBuildService->queueFullBuild('batch_delete_solution', [], current_user())
            : null;
        $result['generation_jobs'] = is_array($job['job'] ?? null) ? [$job['job']] : [];

        return $this->success($result, [], '批量删除已完成');
    }

    public function store(Request $request): array
    {
        $result = $this->solutionService->create([
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
        ], current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('create_solution', 'solution', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '解决方案已创建');
    }

    public function update(Request $request): array
    {
        $result = $this->solutionService->update((int) $request->routeParam('id'), [
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
        ], current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('update_solution', 'solution', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '解决方案已更新');
    }

    public function delete(Request $request): array
    {
        $result = $this->solutionService->remove((int) $request->routeParam('id'), current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueFullBuild('delete_solution', [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '解决方案已删除');
    }

    public function publish(Request $request): array
    {
        $result = $this->solutionService->publish((int) $request->routeParam('id'), (string) $request->input('publish_status', 'draft'), current_user());
        $targetStatus = (string) ($result['publish_status'] ?? '');
        $job = null;
        if ($targetStatus === 'published') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_solution',
                'solution',
                (int) ($result['id'] ?? 0),
                [],
                current_user()
            );
        } elseif ($targetStatus === 'offline') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_solution_status_changed',
                'solution',
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

        return $this->success($result, [], '已从线上版本恢复草稿');
    }

    public function categoryTree(): array
    {
        return $this->success($this->solutionService->categoryTree());
    }

    public function storeCategory(Request $request): array
    {
        $result = $this->solutionService->createCategory([
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'slug' => $request->input('slug'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $job = $this->siteBuildService->queueFullBuild('create_solution_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '分类已创建');
    }

    public function updateCategory(Request $request): array
    {
        $result = $this->solutionService->updateCategory((int) $request->routeParam('id'), [
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'slug' => $request->input('slug'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $job = $this->siteBuildService->queueFullBuild('update_solution_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '分类已更新');
    }

    public function deleteCategory(Request $request): array
    {
        $result = $this->solutionService->deleteCategory((int) $request->routeParam('id'));
        $job = $this->siteBuildService->queueFullBuild('delete_solution_category', [], current_user());
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
