<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class TranslationRepository
{
    public function list(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->query(
                'SELECT id, entity_type, entity_id, language_code, status, retry_count, error_message, created_at, updated_at
                 FROM translation_jobs
                 ORDER BY updated_at DESC, id DESC'
            );
            $rows = $statement->fetchAll();

            return is_array($rows) ? $rows : [];
        }

        return $this->readRuntimeItems();
    }

    public function find(int $id): ?array
    {
        foreach ($this->list() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    public function findByEntityAll(string $entityType, int $entityId): array
    {
        if (!$this->preferRuntimeStorage() && DatabaseManager::instance()->connection() instanceof PDO) {
            $pdo = DatabaseManager::instance()->connection();
            $statement = $pdo->prepare(
                'SELECT id, entity_type, entity_id, language_code, status, retry_count, error_message, created_at, updated_at
                 FROM translation_jobs
                 WHERE entity_type = :entity_type AND entity_id = :entity_id
                 ORDER BY language_code ASC'
            );
            $statement->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);
            $rows = $statement->fetchAll();

            return is_array($rows) ? $rows : [];
        }

        return array_values(array_filter(
            $this->list(),
            static fn (array $item): bool => (string) ($item['entity_type'] ?? '') === $entityType
                && (int) ($item['entity_id'] ?? 0) === $entityId
        ));
    }

    public function findByEntity(string $entityType, int $entityId, string $languageCode): ?array
    {
        foreach ($this->findByEntityAll($entityType, $entityId) as $item) {
            if ((string) ($item['language_code'] ?? '') === $languageCode) {
                return $item;
            }
        }

        return null;
    }

    public function upsertJob(
        string $entityType,
        int $entityId,
        string $languageCode,
        string $status = 'pending',
        ?string $errorMessage = null
    ): array {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO translation_jobs (entity_type, entity_id, language_code, status, retry_count, error_message, created_at, updated_at)
                 VALUES (:entity_type, :entity_id, :language_code, :status, 0, :error_message, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE status = VALUES(status), error_message = VALUES(error_message), updated_at = NOW()'
            );
            $statement->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'language_code' => $languageCode,
                'status' => $status,
                'error_message' => $errorMessage,
            ]);

            return $this->findByEntity($entityType, $entityId, $languageCode) ?? [];
        }

        $items = $this->readRuntimeItems();
        $index = $this->findRuntimeIndex($items, $entityType, $entityId, $languageCode);
        $now = date('Y-m-d H:i:s');
        $payload = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'language_code' => $languageCode,
            'status' => $status,
            'retry_count' => 0,
            'error_message' => $errorMessage,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($index !== null) {
            $payload = array_merge($items[$index], $payload, [
                'id' => (int) ($items[$index]['id'] ?? 0),
                'created_at' => (string) ($items[$index]['created_at'] ?? $now),
            ]);
            $items[$index] = $payload;
        } else {
            $payload['id'] = $this->nextRuntimeId($items);
            $items[] = $payload;
        }

        $this->writeRuntimeItems($items);

        return $payload;
    }

    public function updateStatus(int $id, string $status, ?string $errorMessage = null, bool $incrementRetry = false): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $retrySql = $incrementRetry ? ', retry_count = retry_count + 1' : '';
            $statement = $pdo->prepare(
                'UPDATE translation_jobs
                 SET status = :status, error_message = :error_message' . $retrySql . ', updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'status' => $status,
                'error_message' => $errorMessage,
            ]);

            return $this->find($id);
        }

        $items = $this->readRuntimeItems();
        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $id) {
                continue;
            }

            $items[$index]['status'] = $status;
            $items[$index]['error_message'] = $errorMessage;
            if ($incrementRetry) {
                $items[$index]['retry_count'] = (int) ($items[$index]['retry_count'] ?? 0) + 1;
            }
            $items[$index]['updated_at'] = date('Y-m-d H:i:s');
            $this->writeRuntimeItems($items);

            return $items[$index];
        }

        return null;
    }

    public function deleteByEntity(string $entityType, int $entityId): void
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'DELETE FROM translation_jobs WHERE entity_type = :entity_type AND entity_id = :entity_id'
            );
            $statement->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]);

            return;
        }

        $items = array_values(array_filter(
            $this->readRuntimeItems(),
            static fn (array $item): bool => !(
                (string) ($item['entity_type'] ?? '') === $entityType
                && (int) ($item['entity_id'] ?? 0) === $entityId
            )
        ));
        $this->writeRuntimeItems($items);
    }

    public function countByLanguage(): array
    {
        $result = [];
        foreach ($this->list() as $row) {
            $code = (string) ($row['language_code'] ?? '');
            $status = (string) ($row['status'] ?? '');
            if ($code === '') {
                continue;
            }

            if (!isset($result[$code])) {
                $result[$code] = ['completed' => 0, 'pending' => 0, 'failed' => 0, 'review_required' => 0, 'translating' => 0, 'total' => 0];
            }
            if (!isset($result[$code][$status])) {
                $result[$code][$status] = 0;
            }

            $result[$code][$status] += 1;
            $result[$code]['total'] += 1;
        }

        return $result;
    }

    public function countByStatuses(array $statuses): int
    {
        if ($statuses === []) {
            return 0;
        }

        $total = 0;
        foreach ($this->list() as $item) {
            if (in_array((string) ($item['status'] ?? ''), $statuses, true)) {
                $total++;
            }
        }

        return $total;
    }

    private function preferRuntimeStorage(): bool
    {
        return (string) env('PREFER_RUNTIME_STORAGE', '0') === '1'
            || (PHP_SAPI === 'cli' && is_file($this->storagePath()));
    }

    private function storagePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/translation_jobs.json';
    }

    private function readRuntimeItems(): array
    {
        $path = $this->storagePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = [
                'id' => (int) ($item['id'] ?? 0),
                'entity_type' => (string) ($item['entity_type'] ?? ''),
                'entity_id' => (int) ($item['entity_id'] ?? 0),
                'language_code' => (string) ($item['language_code'] ?? ''),
                'status' => (string) ($item['status'] ?? 'pending'),
                'retry_count' => (int) ($item['retry_count'] ?? 0),
                'error_message' => $item['error_message'] ?? null,
                'created_at' => (string) ($item['created_at'] ?? ''),
                'updated_at' => (string) ($item['updated_at'] ?? ''),
            ];
        }

        usort($items, static function (array $left, array $right): int {
            return strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''))
                ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
        });

        return $items;
    }

    private function writeRuntimeItems(array $items): void
    {
        $path = $this->storagePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function findRuntimeIndex(array $items, string $entityType, int $entityId, string $languageCode): ?int
    {
        foreach ($items as $index => $item) {
            if ((string) ($item['entity_type'] ?? '') === $entityType
                && (int) ($item['entity_id'] ?? 0) === $entityId
                && (string) ($item['language_code'] ?? '') === $languageCode) {
                return $index;
            }
        }

        return null;
    }

    private function nextRuntimeId(array $items): int
    {
        return array_reduce($items, static function (int $carry, array $item): int {
            return max($carry, (int) ($item['id'] ?? 0));
        }, 0) + 1;
    }
}
