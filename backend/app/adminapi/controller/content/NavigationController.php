<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\NavigationService;
use app\service\system\SiteBuildService;

class NavigationController extends BaseAdminController
{
    public function __construct(
        private readonly NavigationService $navigationService = new NavigationService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    ) {
    }

    public function menus(): array
    {
        return $this->success($this->navigationService->list());
    }

    public function bootstrap(Request $request): array
    {
        return $this->success($this->navigationService->bootstrap((int) $request->input('preferred_id', 0)));
    }

    public function lookups(): array
    {
        return $this->success($this->navigationService->lookups());
    }

    public function show(Request $request): array
    {
        return $this->success($this->navigationService->detail((int) $request->routeParam('id')));
    }

    public function store(Request $request): array
    {
        $data = [
            'name_zh' => (string) $request->input('name_zh', ''),
            'menu_key' => (string) $request->input('menu_key', ''),
            'menu_position' => (string) $request->input('menu_position', 'header'),
            'sort' => (int) $request->input('sort', 0),
            'is_enabled' => (int) $request->input('is_enabled', 1),
        ];

        $result = $this->navigationService->createMenu($data);
        $this->siteBuildService->queueFullBuild('navigation_created', [], current_user());

        return $this->success($result, [], '导航已创建');
    }

    public function update(Request $request): array
    {
        $data = $request->all();
        if (!is_array($data)) {
            $data = [];
        }

        $result = $this->navigationService->updateMenu((int) $request->routeParam('id'), $data);
        $this->siteBuildService->queueFullBuild('navigation_updated', [], current_user());

        return $this->success($result, [], '导航已更新');
    }

    public function updateItems(Request $request): array
    {
        $items = $request->input('items', []);
        if (!is_array($items)) {
            $items = [];
        }

        $result = $this->navigationService->updateItems((int) $request->routeParam('id'), $items);
        $this->siteBuildService->queueFullBuild('navigation_items_updated', [], current_user());

        return $this->success($result, [], '导航项已更新');
    }

    public function delete(Request $request): array
    {
        $this->navigationService->delete((int) $request->routeParam('id'));
        $this->siteBuildService->queueFullBuild('navigation_deleted', [], current_user());

        return $this->success([], [], '导航已删除');
    }
}
