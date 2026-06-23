<?php

declare(strict_types=1);

namespace app\common\middleware;

use app\common\exception\BusinessException;

final class RateLimitMiddleware
{
    private const string STORAGE_DIR = '/runtime/storage/rate_limits';

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

        $this->checkRateLimit(
            $clientIp,
            $ruleKey,
            $matchedRule['window_seconds'],
            $matchedRule['max_requests']
        );
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
        $remoteAddr = (string) ($server['REMOTE_ADDR'] ?? '');
        $trustedProxies = $this->trustedProxies();

        if ($remoteAddr !== '' && $this->isTrustedProxy($remoteAddr, $trustedProxies)) {
            $forwardedFor = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($forwardedFor !== '') {
                return $this->firstIp($forwardedFor);
            }

            $realIp = (string) ($server['HTTP_X_REAL_IP'] ?? '');
            if ($realIp !== '') {
                return $this->normalizeIp($realIp);
            }
        }

        return $this->normalizeIp($remoteAddr);
    }

    /** @return string[] */
    private function trustedProxies(): array
    {
        $raw = (string) getenv('TRUSTED_PROXIES');
        $items = array_filter(array_map('trim', explode(',', $raw)));

        return array_values($items);
    }

    private function isTrustedProxy(string $ip, array $trustedProxies): bool
    {
        return in_array($ip, $trustedProxies, true);
    }

    private function firstIp(string $value): string
    {
        $candidates = array_map('trim', explode(',', $value));

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $ip = $this->normalizeIp($candidate);
            if ($ip !== '0.0.0.0') {
                return $ip;
            }
        }

        return '0.0.0.0';
    }

    private function normalizeIp(string $ip): string
    {
        $trimmed = trim($ip);
        if ($trimmed === '') {
            return '0.0.0.0';
        }

        if (filter_var($trimmed, FILTER_VALIDATE_IP)) {
            return $trimmed;
        }

        return '0.0.0.0';
    }

    private function checkRateLimit(string $clientIp, string $ruleKey, int $windowSeconds, int $maxRequests): void
    {
        $storageDir = $this->getStorageDir();
        if (!is_dir($storageDir) && !mkdir($storageDir, 0777, true) && !is_dir($storageDir)) {
            return;
        }

        if ($clientIp === '0.0.0.0') {
            return;
        }

        $storageKey = $ruleKey . '_' . str_replace(':', '_', $clientIp);
        $storagePath = $storageDir . DIRECTORY_SEPARATOR . md5($storageKey) . '.json';

        $records = $this->readRecords($storagePath);
        $now = time();

        $cutoff = $now - $windowSeconds;
        $records = array_values(array_filter($records, static fn (int $timestamp): bool => $timestamp > $cutoff));

        if (count($records) >= $maxRequests) {
            $oldest = min($records);
            $retryAfter = $windowSeconds - ($now - $oldest);
            throw new RateLimitExceededException($retryAfter > 0 ? $retryAfter : 1);
        }

        $records[] = $now;
        $this->writeRecords($storagePath, $records);
    }

    private function getStorageDir(): string
    {
        return rtrim(base_path(self::STORAGE_DIR), DIRECTORY_SEPARATOR);
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
        parent::__construct('Too many requests. Please try again in ' . $retryAfter . ' second(s).', 429);
        $this->retryAfter = $retryAfter;
    }

    private int $retryAfter = 1;

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
