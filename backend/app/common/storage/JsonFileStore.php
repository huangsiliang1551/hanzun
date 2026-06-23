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
        if (!is_file($this->filePath)) {
            return [];
        }

        $file = fopen($this->filePath, 'r');
        if (!is_resource($file) || !flock($file, LOCK_SH)) {
            if (is_resource($file)) {
                fclose($file);
            }
            throw new \RuntimeException('Unable to lock storage file.');
        }

        $json = file_get_contents($this->filePath);
        flock($file, LOCK_UN);
        fclose($file);
        if ($json === false || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function put(array $data): void
    {
        $directory = dirname($this->filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $lockPath = $this->filePath . '.lock';
        $lock = fopen($lockPath, 'c');
        if (!is_resource($lock) || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Unable to lock storage file.');
        }

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
            flock($lock, LOCK_UN);
            fclose($lock);
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }
}
