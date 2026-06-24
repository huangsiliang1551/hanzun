<?php

declare(strict_types=1);

namespace app\common\storage;

final class JsonFileStore
{
    public function __construct(private readonly string $filePath)
    {
    }

    public function exists(): bool
    {
        return is_file($this->filePath);
    }

    public function all(): array
    {
        return $this->withLock(LOCK_SH, fn (): array => $this->readUnlocked());
    }

    public function put(array $data): void
    {
        $this->withLock(LOCK_EX, function () use ($data): void {
            $this->writeUnlocked($data);
        });
    }

    public function transaction(callable $callback): array
    {
        return $this->withLock(LOCK_EX, function () use ($callback): array {
            $current = $this->readUnlocked();
            $next = $callback($current);
            if (!is_array($next)) {
                throw new \RuntimeException('JsonFileStore transaction callback must return an array payload.');
            }

            $this->writeUnlocked($next);

            return $next;
        });
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withLock(int $lockType, callable $callback): mixed
    {
        $directory = dirname($this->filePath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create runtime storage directory.');
        }

        $lockPath = $this->filePath . '.lock';
        $lock = fopen($lockPath, 'c+');
        if (!is_resource($lock) || !flock($lock, $lockType)) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            throw new \RuntimeException('Unable to lock storage file.');
        }

        try {
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function readUnlocked(): array
    {
        if (!is_file($this->filePath)) {
            return [];
        }

        $json = file_get_contents($this->filePath);
        if ($json === false || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function writeUnlocked(array $data): void
    {
        $tmpPath = $this->filePath . '.tmp.' . getmypid();

        try {
            $encoded = json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            );
            if ($encoded === false) {
                throw new \RuntimeException('Unable to encode runtime storage payload.');
            }

            if (file_put_contents($tmpPath, $encoded) === false || !rename($tmpPath, $this->filePath)) {
                throw new \RuntimeException('Unable to write runtime storage file.');
            }
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }
}
