<?php

declare(strict_types=1);

namespace app\adminapi\controller;

use app\common\response\ApiResponse;
use app\common\validation\Validator;

abstract class BaseAdminController
{
    protected function success(array $data = [], array $meta = [], string $message = 'ok'): array
    {
        return ApiResponse::success($data, $meta, $message);
    }

    protected function error(int $code, string $message, $data = null, array $meta = []): array
    {
        return ApiResponse::error($code, $message, $data, $meta);
    }

    /**
     * Validate input data against rules and throw on failure.
     * FIX-24: Unified input validation.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     */
    protected function validate(array $data, array $rules): void
    {
        Validator::make($data, $rules)->validate();
    }
}
