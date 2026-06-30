<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\BaseAdminController;
use app\service\system\HealthService;

class HealthController extends BaseAdminController
{
    public function __construct(private readonly HealthService $healthService = new HealthService())
    {
    }

    public function check(): array
    {
        return $this->success($this->healthService->status());
    }
}
