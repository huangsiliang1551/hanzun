<?php

declare(strict_types=1);

namespace app\adminapi\controller\dashboard;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\dashboard\DashboardService;

class DashboardController extends BaseAdminController
{
    public function __construct(private readonly DashboardService $dashboardService = new DashboardService())
    {
    }

    public function traffic(Request $request): array
    {
        return $this->success($this->dashboardService->traffic(
            (string) $request->input('range', '7d'),
            $request->input('start_date'),
            $request->input('end_date')
        ));
    }

    public function aiConversations(Request $request): array
    {
        return $this->success($this->dashboardService->aiConversations(
            (string) $request->input('range', '7d'),
            $request->input('start_date'),
            $request->input('end_date')
        ));
    }

    public function inquiries(Request $request): array
    {
        return $this->success($this->dashboardService->inquiries(
            (string) $request->input('range', '7d'),
            $request->input('start_date'),
            $request->input('end_date')
        ));
    }

    public function overview(Request $request): array
    {
        $liteMode = in_array((string) $request->input('lite', '0'), ['1', 'true', 'on'], true);
        return $this->success($this->dashboardService->overview(
            (string) $request->input('range', '7d'),
            $request->input('start_date'),
            $request->input('end_date'),
            $liteMode
        ));
    }

    public function jobs(): array
    {
        return $this->success($this->dashboardService->jobs());
    }
}
