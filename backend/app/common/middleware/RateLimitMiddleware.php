<?php

declare(strict_types=1);

namespace app\common\middleware;

use app\common\exception\BusinessException;

final class RateLimitMiddleware
{
    private const STORAGE_DIR = '/runtime/storage/rate_limits/';

    /**
     * @var array<string, array{window_seconds: int, max_requests: int}>
     */
    private array $rules;

    /**
     * @param array<string, array{window_seconds: int, max_requests: int}> $rules Map of path pattern => rate rule
     */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    /**
     * @param array<string, mixed> $server
     */
    public function handle(string $path, string $method, array $server): void
    {
        $matchedRule = $this->matchRule($path, $method);

        if ($matchedRule === null) {
            return;
        }

        $clientIp = $this->resolveClientIp($server);
        $ruleKey = $matchedRule['key'];

        $this->checkRateLimit($clientIp, $ruleKey, $matchedRule['window_seconds'], $matchedRule['max_requests']);
    }

    /**
     * @return array{key: string, window_seconds: int, max_requests: int}|null
     */
    private function matchRule(string $path, string $method): ?array
    {
        foreach ($this->rules as $pattern => $rule) {
            if (!str_contains($pattern, $method . ' ')) {
                continue;
            }

            $routePath = explode(' ', $pattern, 2)[1] ?? '';
            if ($routePath === '' || !str_starts_with($path, $routePath)) {
                continue;
            }

            return [
                'key' => $pattern,
                'window_seconds' => (int) ($rule['window_seconds'] ?? 60),
                'max_requests' => (int) ($rule['max_requests'] ?? 30),
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $server
     */
    private function resolveClientIp(array $server): string
    {
        $candidates = [
            $server['HTTP_X_FORWARDED_FOR'] ?? null,
            $server['HTTP_X_REAL_IP'] ?? null,
            $server['REMOTE_ADDR'] ?? '127.0.0.1',
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                $ip = trim(explode(',', $candidate)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    private function checkRateLimit(string $clientIp, string $ruleKey, int $windowSeconds, int $maxRequests): void
    {
        $storageDir = $this->getStorageDir();
        if (!is_dir($storageDir) && !mkdir($storageDir, 0777, true) && !is_dir($storageDir)) {
            return; // Silently skip if storage not writable
        }

        $storageKey = $ruleKey . '_' . str_replace(':', '_', $clientIp);
        $storagePath = $storageDir . md5($storageKey) . '.json';

        $records = $this->readRecords($storagePath);
        $now = time();

        // Remove expired entries
        $cutoff = $now - $windowSeconds;
        $records = array_values(array_filter($records, static fn (int $timestamp): bool => $timestamp > $cutoff));

        // Check limit
        if (count($records) >= $maxRequests) {
            $oldest = min($records);
            $retryAfter = $windowSeconds - ($now - $oldest);
            throw new RateLimitExceededException($retryAfter > 0 ? $retryAfter : 1);
        }

        // Record current request
        $records[] = $now;
        $this->writeRecords($storagePath, $records);
    }

    private function getStorageDir(): string
    {
        return base_path(self::STORAGE_DIR);
    }

    /**
     * @return int[]
     */
    private function readRecords(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param int[] $records
     */
    private function writeRecords(string $path, array $records): void
    {
        @file_put_contents($path, json_encode($records, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}

class RateLimitExceededException extends BusinessException
{
    public function __construct(int $retryAfter = 1)
    {
        parent::__construct('请求过于频繁，请 ' . $retryAfter . ' 秒后重试', 429);
        $this->retryAfter = $retryAfter;
    }

    private int $retryAfter = 1;

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
