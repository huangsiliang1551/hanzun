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
        return $this->success($this->teamService->create([
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
            'manual_sort' => $request->input('manual_sort'),
        ], current_user()), [], 'create success');
    }

    public function update(Request $request): array
    {
        return $this->success($this->teamService->update((int) $request->routeParam('id'), [
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
            'manual_sort' => $request->input('manual_sort'),
        ], current_user()), [], 'update success');
    }

    public function delete(Request $request): array
    {
        $result = $this->teamService->remove((int) $request->routeParam('id'), current_user());
        $job = $this->siteBuildService->queueFullBuild('delete_team', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success(
            $result,
            [],
            'delete success'
        );
    }

    public function publish(Request $request): array
    {
        $result = $this->teamService->publish((int) $request->routeParam('id'), (string) $request->input('publish_status', 'draft'), current_user());
        if ((string) ($result['publish_status'] ?? '') === 'published') {
            $job = $this->siteBuildService->queueIncrementalBuild('publish_team', 'team', (int) ($result['id'] ?? 0), [], current_user());
        } else {
            $job = $this->siteBuildService->queueFullBuild('publish_team_status_changed', [], current_user());
        }
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result, [], 'publish status updated');
    }

    public function batchPublish(Request $request): array
    {
        $result = $this->teamService->batchPublish(
            array_map('intval', (array) $request->input('ids', [])),
            (string) $request->input('publish_status', 'published'),
            current_user()
        );
        $job = $this->siteBuildService->queueFullBuild('batch_publish_team', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result, [], 'batch publish updated');
    }

    public function batchSort(Request $request): array
    {
        $result = $this->teamService->batchSort(
            (array) $request->input('items', [])
        );
        $job = $this->siteBuildService->queueFullBuild('batch_sort_team', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result, [], 'batch sort updated');
    }
}
