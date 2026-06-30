<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\AdService;
use app\service\system\SiteBuildService;

final class AdController extends BaseAdminController
{
    public function __construct(
        private readonly AdService $adService = new AdService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function index(): array
    {
        return $this->success($this->adService->list());
    }

    public function update(Request $request): array
    {
        $items = $request->input('items', []);
        if (!is_array($items)) {
            $items = [];
        }

        $result = $this->adService->saveAll($items);
        $this->siteBuildService->queueFullBuild('ads_updated', [], current_user());

        return $this->success($result, [], '广告位已更新');
    }
}
