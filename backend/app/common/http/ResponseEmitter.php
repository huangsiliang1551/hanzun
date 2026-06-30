<?php

declare(strict_types=1);

namespace app\common\http;

final class ResponseEmitter
{
    public static function noContent(int $statusCode = 204): void
    {
        self::emitCorsHeaders();
        http_response_code($statusCode);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $statusCode = 200): void
    {
        self::emitCorsHeaders();
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        // Strip site-build job payloads from the response so the frontend
        // does not show a "generate static pages" button. Build jobs are
        // already persisted and will be processed by the task center.
        if (isset($payload['data']) && is_array($payload['data'])) {
            unset($payload['data']['generation_job'], $payload['data']['generation_jobs']);
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private static function emitCorsHeaders(): void
    {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        $appUrl = trim((string) env('APP_URL', ''));
        $configuredOrigins = array_values(array_filter(array_map(
            static fn (string $item): string => rtrim(trim($item), '/'),
            explode(',', (string) env('APP_ALLOWED_ORIGINS', ''))
        )));

        if ($appUrl !== '') {
            $configuredOrigins[] = rtrim($appUrl, '/');
        }

        $allowedOrigin = '';
        if ($origin !== '') {
            $normalizedOrigin = rtrim($origin, '/');
            if (in_array($normalizedOrigin, $configuredOrigins, true) || self::isLoopbackOrigin($normalizedOrigin)) {
                $allowedOrigin = $origin;
            }
        } elseif ($appUrl !== '') {
            $allowedOrigin = $appUrl;
        }

        if ($allowedOrigin !== '') {
            header('Access-Control-Allow-Origin: ' . $allowedOrigin);
            header('Vary: Origin');
        }
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
        header('Access-Control-Max-Age: 600');
    }

    private static function isLoopbackOrigin(string $origin): bool
    {
        $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }
}
