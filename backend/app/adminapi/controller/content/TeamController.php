<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\TeamService;
use app\service\system\SiteBuildService;

class TeamController extends BaseAdminController
{
    public function __construct(
        private readonly TeamService $teamService = new TeamService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function index(): array
    {
        return $this->success($this->teamService->list());
    }

    public function show(Request $request): array
    {
        return $this->success($this->teamService->detail((int) $request->routeParam('id')));
    }

    public function store(Request $request): array
    {
        $result = $this->teamService->create([
            'name_zh' => $request->input('name_zh'),
            'title_zh' => $request->input('title_zh'),
            'department_zh' => $request->input('department_zh'),
            'bio_zh' => $request->input('bio_zh'),
            'avatar_asset_id' => $request->input('avatar_asset_id'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'whatsapp' => $request->input('whatsapp'),
            'wechat' => $request->input('wechat'),
            'publish_status' => $request->input('publish_status'),
            'translation_status' => $request->input('translation_status'),
            'is_home_featured' => $request->input('is_home_featured'),
            'manual_sort' => $request->input('manual_sort')
        ], current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('create_team', 'team', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '成员已创建');
    }

    public function update(Request $request): array
    {
        $result = $this->teamService->update((int) $request->routeParam('id'), [
            'name_zh' => $request->input('name_zh'),
            'title_zh' => $request->input('title_zh'),
            'department_zh' => $request->input('department_zh'),
            'bio_zh' => $request->input('bio_zh'),
            'avatar_asset_id' => $request->input('avatar_asset_id'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'whatsapp' => $request->input('whatsapp'),
            'wechat' => $request->input('wechat'),
            'publish_status' => $request->input('publish_status'),
            'translation_status' => $request->input('translation_status'),
            'is_home_featured' => $request->input('is_home_featured'),
            'manual_sort' => $request->input('manual_sort')
        ], current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('update_team', 'team', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '成员已更新');
    }

    public function delete(Request $request): array
    {
        $result = $this->teamService->remove((int) $request->routeParam('id'), current_user());
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('delete_team', 'team', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '成员已删除');
    }

    public function publish(Request $request): array
    {
        $result = $this->teamService->publish((int) $request->routeParam('id'), (string) $request->input('publish_status', 'draft'), current_user());
        $targetStatus = (string) ($result['publish_status'] ?? '');
        $job = null;
        if ($targetStatus === 'published') {
            $job = $this->siteBuildService->queueIncrementalBuild('publish_team', 'team', (int) ($result['id'] ?? 0), [], current_user());
        } elseif ($targetStatus === 'offline') {
            $job = $this->siteBuildService->queueIncrementalBuild('publish_team_status_changed', 'team', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '发布状态已更新');
    }

    public function batchPublish(Request $request): array
    {
        $result = $this->teamService->batchPublish(
            array_map('intval', (array) $request->input('ids', [])),
            (string) $request->input('publish_status', 'published'),
            current_user()
        );
        $targetStatus = (string) $request->input('publish_status', 'published');
        $job = null;
        if (in_array($targetStatus, ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('batch_publish_team', 'team', 0, [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '批量发布状态已更新');
    }

    public function batchSort(Request $request): array
    {
        $result = $this->teamService->batchSort(
            (array) $request->input('items', [])
        );
        $job = $this->siteBuildService->queueIncrementalBuild('batch_sort_team', 'team', 0, [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result, [], '批量排序已更新');
    }
}
