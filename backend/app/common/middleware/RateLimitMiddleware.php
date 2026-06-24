<?php

declare(strict_types=1);

namespace app\common\middleware;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;

final class RateLimitMiddleware
{
    private const string STORAGE_DIR = '/runtime/storage/rate_limits';

    /**
     * @var array<string, array{window_seconds: int, max_requests: int}>
     */
    private array $rules;

    /**
     * @param array<string, array{window_seconds: int, max_requests: int}> $rules
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
        $this->checkRateLimit(
            $clientIp,
            $matchedRule['key'],
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
            $this->failClosed('Failed to create rate limit storage directory: ' . $storageDir);
        }

        $storageKey = $ruleKey . '_' . str_replace(':', '_', $clientIp);
        $storagePath = $storageDir . DIRECTORY_SEPARATOR . md5($storageKey) . '.json';

        $this->withLockedRecords($storagePath, function (array $records) use ($maxRequests, $windowSeconds): array {
            $now = time();
            $cutoff = $now - $windowSeconds;
            $records = array_values(array_filter($records, static fn (int $timestamp): bool => $timestamp > $cutoff));

            if (count($records) >= $maxRequests) {
                $oldest = min($records);
                $retryAfter = $windowSeconds - ($now - $oldest);
                throw new RateLimitExceededException($retryAfter > 0 ? $retryAfter : 1);
            }

            $records[] = $now;

            return $records;
        });
    }

    private function getStorageDir(): string
    {
        return rtrim(base_path(self::STORAGE_DIR), DIRECTORY_SEPARATOR);
    }

    /**
     * @param callable(array<int, int>): array<int, int> $callback
     */
    private function withLockedRecords(string $path, callable $callback): void
    {
        $lockPath = $path . '.lock';
        $lockHandle = fopen($lockPath, 'c+');
        if (!is_resource($lockHandle)) {
            $this->failClosed('Failed to open rate limit lock file: ' . $lockPath);
        }

        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            $this->failClosed('Failed to acquire rate limit lock: ' . $lockPath);
        }

        $tmpPath = $path . '.tmp.' . getmypid();

        try {
            $records = $this->readRecordsUnlocked($path);
            $updated = $callback($records);
            $this->writeRecordsUnlocked($path, $tmpPath, $updated);
        } catch (RateLimitExceededException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            $this->failClosed('Rate limit storage failure for ' . $path . ': ' . $exception->getMessage(), $exception);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * @return int[]
     */
    private function readRecordsUnlocked(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded)
            ? array_values(array_map(static fn (mixed $timestamp): int => (int) $timestamp, $decoded))
            : [];
    }

    /**
     * @param int[] $records
     */
    private function writeRecordsUnlocked(string $path, string $tmpPath, array $records): void
    {
        $encoded = json_encode($records, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('Unable to encode rate limit records.');
        }

        if (file_put_contents($tmpPath, $encoded) === false || !rename($tmpPath, $path)) {
            throw new \RuntimeException('Unable to persist rate limit records.');
        }
    }

    private function failClosed(string $message, ?\Throwable $exception = null): never
    {
        error_log($message . ($exception instanceof \Throwable ? ' [' . $exception::class . ']' : ''));

        throw new BusinessException('Rate limit storage unavailable.', ErrorCode::INTERNAL_ERROR);
    }
}

class RateLimitExceededException extends BusinessException
{
    private int $retryAfter = 1;

    public function __construct(int $retryAfter = 1)
    {
        parent::__construct('Too many requests. Please try again in ' . $retryAfter . ' second(s).', 429);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
