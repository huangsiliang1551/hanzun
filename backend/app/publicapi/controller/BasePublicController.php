<?php

declare(strict_types=1);

namespace app\publicapi\controller;

use app\common\response\ApiResponse;

abstract class BasePublicController
{
    protected function success(array $data = [], array $meta = [], string $message = 'ok'): array
    {
        return ApiResponse::success($data, $meta, $message);
    }
}
