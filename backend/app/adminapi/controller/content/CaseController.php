<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\CaseService;
use app\service\system\SiteBuildService;

class CaseController extends BaseAdminController
{
    public function __construct(
        private readonly CaseService $caseService = new CaseService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function index(Request $request): array
    {
        return $this->success($this->caseService->list([
            'publish_status' => $request->input('publish_status'),
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

    public function lookups(): array
    {
        return $this->success($this->caseService->lookups());
    }

    public function show(Request $request): array
    {
        return $this->success($this->caseService->detail((int) $request->routeParam('id')));
    }

    public function bootstrap(Request $request): array
    {
        return $this->success($this->caseService->bootstrap((int) $request->routeParam('id')));
    }

    public function batchPublish(Request $request): array
    {
        $result = $this->caseService->batchPublish(
            $request->input('ids', []),
            (string) $request->input('publish_status', 'draft'),
            current_user()
        );
        $targetStatus = (string) ($result['publish_status'] ?? $request->input('publish_status', 'draft'));
        $result['generation_jobs'] = in_array($targetStatus, ['published', 'offline'], true)
            ? $this->queueEntityBuildJobs('batch_publish_case', 'case', $result['ids'] ?? [])
            : [];

        return $this->success($result, [], '批量发布状态已更新');
    }

    public function batchDelete(Request $request): array
    {
        $result = $this->caseService->batchRemove(
            $request->input('ids', []),
            current_user()
        );
        $buildItems = array_values(array_filter((array) ($result['items'] ?? []), static fn (array $item): bool => in_array((string) ($item['publish_status'] ?? ''), ['published', 'offline'], true)));
        $job = $buildItems !== []
            ? $this->siteBuildService->queueFullBuild('batch_delete_case', [], current_user())
            : null;
        $result['generation_jobs'] = is_array($job['job'] ?? null) ? [$job['job']] : [];

        return $this->success($result, [], '批量删除已完成');
    }

    public function store(Request $request): array
    {
        $result = $this->caseService->create([
            'category_id' => $request->input('category_id'),
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
            $job = $this->siteBuildService->queueIncrementalBuild('create_case', 'case', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '案例已创建');
    }

    public function update(Request $request): array
    {
        $result = $this->caseService->update((int) $request->routeParam('id'), [
            'category_id' => $request->input('category_id'),
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
            $job = $this->siteBuildService->queueIncrementalBuild('update_case', 'case', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '案例已更新');
    }

    public function delete(Request $request): array
    {
        $result = $this->caseService->remove((int) $request->routeParam('id'), current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueFullBuild('delete_case', [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '案例已删除');
    }

    public function publish(Request $request): array
    {
        $result = $this->caseService->publish((int) $request->routeParam('id'), (string) $request->input('publish_status', 'draft'), current_user());
        $targetStatus = (string) ($result['publish_status'] ?? '');
        $job = null;
        if ($targetStatus === 'published') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_case',
                'case',
                (int) ($result['id'] ?? 0),
                [],
                current_user()
            );
        } elseif ($targetStatus === 'offline') {
            $job = $this->siteBuildService->queueIncrementalBuild(
                'publish_case_status_changed',
                'case',
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
        return $this->success($this->caseService->workflow((int) $request->routeParam('id')));
    }

    public function restoreLive(Request $request): array
    {
        $result = $this->caseService->restoreLive((int) $request->routeParam('id'), current_user());
        $job = $this->siteBuildService->queueIncrementalBuild(
            'restore_live_case',
            'case',
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
