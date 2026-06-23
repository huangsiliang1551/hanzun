<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\AboutService;
use app\service\system\SiteBuildService;

class AboutController extends BaseAdminController
{
    public function __construct(
        private readonly AboutService $aboutService = new AboutService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function pages(): array
    {
        return $this->success($this->aboutService->pages());
    }

    public function bootstrap(Request $request): array
    {
        return $this->success($this->aboutService->bootstrap((int) $request->input('preferred_id', 0)));
    }

    public function show(Request $request): array
    {
        return $this->success($this->aboutService->page((int) $request->routeParam('id')));
    }

    public function updateBlocks(Request $request): array
    {
        $blocks = $request->input('blocks', []);
        $result = $this->aboutService->updateBlocks((int) $request->routeParam('id'), is_array($blocks) ? $blocks : []);
        $this->siteBuildService->queueIncrementalBuild('about_blocks_updated', 'about', (int) $request->routeParam('id'), [], current_user());

        return $this->success($result, [], 'update success');
    }
}
