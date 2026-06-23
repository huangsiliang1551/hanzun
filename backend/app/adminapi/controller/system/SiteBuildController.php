<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\system\SiteBuildService;

final class SiteBuildController extends BaseAdminController
{
    public function __construct(private readonly SiteBuildService $siteBuildService = new SiteBuildService())
    {
    }

    public function createJob(Request $request): array
    {
        return $this->success(
            $this->siteBuildService->createJob([
                'scope' => $request->input('scope', 'incremental'),
                'trigger_source' => $request->input('trigger_source', ''),
                'entity_type' => $request->input('entity_type', ''),
                'entity_id' => $request->input('entity_id', 0),
                'language_codes' => $request->input('language_codes', []),
                'context' => $request->input('context', []),
            ], current_user()),
            [],
            'site build job created'
        );
    }

    public function jobs(): array
    {
        return $this->success($this->siteBuildService->jobs());
    }

    public function current(): array
    {
        return $this->success($this->siteBuildService->current());
    }

    public function detail(Request $request): array
    {
        return $this->success($this->siteBuildService->detail((int) $request->routeParam('id')));
    }

    public function retry(Request $request): array
    {
        return $this->success(
            $this->siteBuildService->retry((int) $request->routeParam('id'), current_user()),
            [],
            'site build job retried'
        );
    }
}
