<?php

declare(strict_types=1);

namespace app\common\response;

final class ApiResponse
{
    public static function success(array $data = [], array $meta = [], string $message = 'ok'): array
    {
        return [
            'code' => 0,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'request_id' => self::requestId(),
            'timestamp' => time(),
        ];
    }

    public static function error(int $code, string $message, $data = null, array $meta = []): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'request_id' => self::requestId(),
            'timestamp' => time(),
        ];
    }

    private static function requestId(): string
    {
        $request = request();
        if ($request !== null) {
            return $request->requestId();
        }

        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable $exception) {
            return uniqid('', true);
        }
    }
}
