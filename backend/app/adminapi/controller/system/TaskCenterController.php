<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\BaseAdminController;
use app\service\system\TaskCenterService;

final class TaskCenterController extends BaseAdminController
{
    public function __construct(private readonly TaskCenterService $taskCenterService = new TaskCenterService())
    {
    }

    public function overview(): array
    {
        return $this->success($this->taskCenterService->overview());
    }
}
