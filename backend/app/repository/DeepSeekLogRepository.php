<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class DeepSeekLogRepository
{
    public function list(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->query(
                'SELECT id, feature_code, feature_name, model, is_success, status_code, duration_ms, attempts, error_message, created_at
                 FROM deepseek_logs
                 ORDER BY created_at DESC, id DESC'
            );
            $rows = $statement->fetchAll();

            return is_array($rows) ? $rows : [];
        }

        return $this->readRuntimeItems();
    }

    public function append(array $payload): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO deepseek_logs (feature_code, feature_name, model, is_success, status_code, duration_ms, attempts, error_message, created_at)
                 VALUES (:feature_code, :feature_name, :model, :is_success, :status_code, :duration_ms, :attempts, :error_message, :created_at)'
            );
            $statement->execute([
                'feature_code' => $payload['feature_code'] ?? '',
                'feature_name' => $payload['feature_name'] ?? '',
                'model' => $payload['model'] ?? '',
                'is_success' => $payload['is_success'] ?? 0,
                'status_code' => $payload['status_code'] ?? 0,
                'duration_ms' => $payload['duration_ms'] ?? 0,
                'attempts' => $payload['attempts'] ?? 1,
                'error_message' => $payload['error_message'] ?? '',
                'created_at' => $payload['created_at'] ?? date('Y-m-d H:i:s'),
            ]);

            $payload['id'] = (int) $pdo->lastInsertId();

            return $payload;
        }

        $items = $this->readRuntimeItems();
        $payload['id'] = $this->nextRuntimeId($items);
        $items[] = [
            'id' => (int) ($payload['id'] ?? 0),
            'feature_code' => (string) ($payload['feature_code'] ?? ''),
            'feature_name' => (string) ($payload['feature_name'] ?? ''),
            'model' => (string) ($payload['model'] ?? ''),
            'is_success' => (int) ($payload['is_success'] ?? 0),
            'status_code' => (int) ($payload['status_code'] ?? 0),
            'duration_ms' => (int) ($payload['duration_ms'] ?? 0),
            'attempts' => (int) ($payload['attempts'] ?? 1),
            'error_message' => (string) ($payload['error_message'] ?? ''),
            'created_at' => (string) ($payload['created_at'] ?? date('Y-m-d H:i:s')),
        ];
        $this->writeRuntimeItems($items);

        return $payload;
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage($this->storagePath());
    }

    private function storagePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/deepseek_logs.json';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRuntimeItems(): array
    {
        $path = $this->storagePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function writeRuntimeItems(array $items): void
    {
        $path = $this->storagePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function nextRuntimeId(array $items): int
    {
        $maxId = 0;
        foreach ($items as $item) {
            $maxId = max($maxId, (int) ($item['id'] ?? 0));
        }

        return $maxId + 1;
    }
}
