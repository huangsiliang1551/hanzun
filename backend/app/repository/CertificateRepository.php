<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class CertificateRepository
{
    public function list(): array
    {
        $runtimeItems = $this->readRuntimeItems();
        if ($this->preferRuntimeStorage()) {
            return $this->sortItems($runtimeItems);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $selectFields = $this->hasColumn($pdo, 'certificates', 'certificate_no')
                ? 'id, name_zh, issuer_zh, certificate_no, certificate_type, description_zh, image_asset_id, publish_status, translation_status, seo_status, is_home_featured, manual_sort, created_at, updated_at'
                : "id, name_zh, issuer_zh, '' AS certificate_no, '' AS certificate_type, description_zh, image_asset_id, publish_status, translation_status, seo_status, is_home_featured, manual_sort, created_at, updated_at";
            $statement = $pdo->query(
                'SELECT ' . $selectFields . '
                 FROM certificates
                 ORDER BY manual_sort DESC, id DESC'
            );
            $rows = $statement->fetchAll();
            $items = is_array($rows) ? $rows : [];

            return $this->sortItems($this->mergeRuntimeItems($items, $runtimeItems));
        }

        return $this->sortItems($runtimeItems);
    }

    public function find(int $id): ?array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->findRuntime($id);
        }

        $record = $this->findDatabase($id);
        if ($record !== null) {
            return $record;
        }

        return $this->findRuntime($id);
    }

    public function create(array $payload): array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->createRuntime($payload);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            if ($this->hasColumn($pdo, 'certificates', 'certificate_no')) {
                $statement = $pdo->prepare(
                    'INSERT INTO certificates (name_zh, issuer_zh, certificate_no, certificate_type, description_zh, image_asset_id, publish_status, translation_status, seo_status, is_home_featured, manual_sort, created_at, updated_at)
                     VALUES (:name_zh, :issuer_zh, :certificate_no, :certificate_type, :description_zh, :image_asset_id, :publish_status, :translation_status, :seo_status, :is_home_featured, :manual_sort, NOW(), NOW())'
                );
                $statement->execute([
                    'name_zh' => $payload['name_zh'],
                    'issuer_zh' => $payload['issuer_zh'],
                    'certificate_no' => $payload['certificate_no'],
                    'certificate_type' => $payload['certificate_type'],
                    'description_zh' => $payload['description_zh'],
                    'image_asset_id' => $payload['image_asset_id'],
                    'publish_status' => $payload['publish_status'],
                    'translation_status' => $payload['translation_status'],
                    'seo_status' => $payload['seo_status'],
                    'is_home_featured' => $payload['is_home_featured'],
                    'manual_sort' => $payload['manual_sort'],
                ]);
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO certificates (name_zh, issuer_zh, description_zh, image_asset_id, publish_status, translation_status, seo_status, is_home_featured, manual_sort, created_at, updated_at)
                     VALUES (:name_zh, :issuer_zh, :description_zh, :image_asset_id, :publish_status, :translation_status, :seo_status, :is_home_featured, :manual_sort, NOW(), NOW())'
                );
                $statement->execute([
                    'name_zh' => $payload['name_zh'],
                    'issuer_zh' => $payload['issuer_zh'],
                    'description_zh' => $payload['description_zh'],
                    'image_asset_id' => $payload['image_asset_id'],
                    'publish_status' => $payload['publish_status'],
                    'translation_status' => $payload['translation_status'],
                    'seo_status' => $payload['seo_status'],
                    'is_home_featured' => $payload['is_home_featured'],
                    'manual_sort' => $payload['manual_sort'],
                ]);
            }

            return $this->findDatabase((int) $pdo->lastInsertId()) ?? $payload;
        }

        return $this->createRuntime($payload);
    }

    public function update(int $id, array $payload): ?array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->updateRuntime($id, $payload);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            if ($this->findDatabase($id) === null) {
                return $this->updateRuntime($id, $payload);
            }

            if ($this->hasColumn($pdo, 'certificates', 'certificate_no')) {
                $statement = $pdo->prepare(
                    'UPDATE certificates
                     SET name_zh = :name_zh, issuer_zh = :issuer_zh, certificate_no = :certificate_no, certificate_type = :certificate_type, description_zh = :description_zh, image_asset_id = :image_asset_id, publish_status = :publish_status, translation_status = :translation_status, seo_status = :seo_status, is_home_featured = :is_home_featured, manual_sort = :manual_sort, updated_at = NOW()
                     WHERE id = :id'
                );
                $statement->execute([
                    'id' => $id,
                    'name_zh' => $payload['name_zh'],
                    'issuer_zh' => $payload['issuer_zh'],
                    'certificate_no' => $payload['certificate_no'],
                    'certificate_type' => $payload['certificate_type'],
                    'description_zh' => $payload['description_zh'],
                    'image_asset_id' => $payload['image_asset_id'],
                    'publish_status' => $payload['publish_status'],
                    'translation_status' => $payload['translation_status'],
                    'seo_status' => $payload['seo_status'],
                    'is_home_featured' => $payload['is_home_featured'],
                    'manual_sort' => $payload['manual_sort'],
                ]);
            } else {
                $statement = $pdo->prepare(
                    'UPDATE certificates
                     SET name_zh = :name_zh, issuer_zh = :issuer_zh, description_zh = :description_zh, image_asset_id = :image_asset_id, publish_status = :publish_status, translation_status = :translation_status, seo_status = :seo_status, is_home_featured = :is_home_featured, manual_sort = :manual_sort, updated_at = NOW()
                     WHERE id = :id'
                );
                $statement->execute([
                    'id' => $id,
                    'name_zh' => $payload['name_zh'],
                    'issuer_zh' => $payload['issuer_zh'],
                    'description_zh' => $payload['description_zh'],
                    'image_asset_id' => $payload['image_asset_id'],
                    'publish_status' => $payload['publish_status'],
                    'translation_status' => $payload['translation_status'],
                    'seo_status' => $payload['seo_status'],
                    'is_home_featured' => $payload['is_home_featured'],
                    'manual_sort' => $payload['manual_sort'],
                ]);
            }

            return $this->findDatabase($id);
        }

        return $this->updateRuntime($id, $payload);
    }

    public function updatePublishStatus(int $id, string $publishStatus): ?array
    {
        $record = $this->find($id);
        if ($record === null) {
            return null;
        }

        $record['publish_status'] = $publishStatus;

        return $this->update($id, $record);
    }

    public function delete(int $id): ?array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->deleteRuntime($id);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $record = $this->findDatabase($id);
            if ($record === null) {
                return $this->deleteRuntime($id);
            }

            $statement = $pdo->prepare('DELETE FROM certificates WHERE id = :id');
            $statement->execute(['id' => $id]);

            return $record;
        }

        return $this->deleteRuntime($id);
    }

    public function batchUpdatePublishStatus(array $ids, string $publishStatus): int
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $statement = $pdo->prepare(
                'UPDATE certificates SET publish_status = ?, updated_at = NOW() WHERE id IN (' . $placeholders . ')'
            );
            $statement->execute(array_merge([$publishStatus], $ids));

            return $statement->rowCount();
        }

        $count = 0;
        foreach ($ids as $id) {
            if ($this->updatePublishStatus((int) $id, $publishStatus) !== null) {
                $count++;
            }
        }

        return $count;
    }

    public function batchUpdateSort(array $items): int
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $count = 0;
            $statement = $pdo->prepare(
                'UPDATE certificates SET manual_sort = :manual_sort, updated_at = NOW() WHERE id = :id'
            );
            foreach ($items as $item) {
                $statement->execute([
                    'manual_sort' => (int) ($item['manual_sort'] ?? 0),
                    'id' => (int) ($item['id'] ?? 0),
                ]);
                $count += $statement->rowCount();
            }

            return $count;
        }

        $count = 0;
        foreach ($items as $item) {
            if ($this->updateRuntime((int) ($item['id'] ?? 0), ['manual_sort' => (int) ($item['manual_sort'] ?? 0)]) !== null) {
                $count++;
            }
        }

        return $count;
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage($this->storagePath());
    }

    private function storagePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/certificates.json';
    }

    private function findDatabase(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return null;
        }

        $selectFields = $this->hasColumn($pdo, 'certificates', 'certificate_no')
            ? 'id, name_zh, issuer_zh, certificate_no, certificate_type, description_zh, image_asset_id, publish_status, translation_status, seo_status, is_home_featured, manual_sort, created_at, updated_at'
            : "id, name_zh, issuer_zh, '' AS certificate_no, '' AS certificate_type, description_zh, image_asset_id, publish_status, translation_status, seo_status, is_home_featured, manual_sort, created_at, updated_at";
        $statement = $pdo->prepare('SELECT ' . $selectFields . ' FROM certificates WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
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

            $items[] = $this->normalizeRuntimeItem($item);
        }

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

    private function findRuntime(int $id): ?array
    {
        foreach ($this->readRuntimeItems() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    private function createRuntime(array $payload): array
    {
        $items = $this->readRuntimeItems();
        $record = $this->normalizeRuntimeItem(array_merge($payload, [
            'id' => $this->nextId($items),
            'created_at' => $payload['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $payload['updated_at'] ?? date('Y-m-d H:i:s'),
        ]));
        $items[] = $record;
        $this->writeRuntimeItems($items);

        return $record;
    }

    private function updateRuntime(int $id, array $payload): ?array
    {
        $items = $this->readRuntimeItems();
        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $id) {
                continue;
            }

            $items[$index] = $this->normalizeRuntimeItem(array_merge($item, $payload, [
                'id' => $id,
                'updated_at' => date('Y-m-d H:i:s'),
            ]));
            $this->writeRuntimeItems($items);

            return $items[$index];
        }

        return null;
    }

    private function deleteRuntime(int $id): ?array
    {
        $record = $this->findRuntime($id);
        if ($record === null) {
            return null;
        }

        $items = array_values(array_filter(
            $this->readRuntimeItems(),
            static fn (array $item): bool => (int) ($item['id'] ?? 0) !== $id
        ));
        $this->writeRuntimeItems($items);

        return $record;
    }

    private function normalizeRuntimeItem(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'name_zh' => (string) ($item['name_zh'] ?? ''),
            'issuer_zh' => (string) ($item['issuer_zh'] ?? ''),
            'certificate_no' => (string) ($item['certificate_no'] ?? ''),
            'certificate_type' => (string) ($item['certificate_type'] ?? ''),
            'description_zh' => (string) ($item['description_zh'] ?? ''),
            'image_asset_id' => isset($item['image_asset_id']) && $item['image_asset_id'] !== '' ? (int) $item['image_asset_id'] : null,
            'publish_status' => (string) ($item['publish_status'] ?? 'draft'),
            'translation_status' => (string) ($item['translation_status'] ?? 'pending'),
            'seo_status' => (string) ($item['seo_status'] ?? 'pending'),
            'is_home_featured' => !empty($item['is_home_featured']) ? 1 : 0,
            'manual_sort' => (int) ($item['manual_sort'] ?? 0),
            'created_at' => (string) ($item['created_at'] ?? ''),
            'updated_at' => (string) ($item['updated_at'] ?? ''),
        ];
    }

    private function mergeRuntimeItems(array $databaseItems, array $runtimeItems): array
    {
        $seen = [];
        foreach ($databaseItems as $item) {
            $seen[(int) ($item['id'] ?? 0)] = true;
        }

        foreach ($runtimeItems as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (isset($seen[$id])) {
                continue;
            }

            $databaseItems[] = $item;
        }

        return $databaseItems;
    }

    private function sortItems(array $items): array
    {
        usort($items, static function (array $left, array $right): int {
            return (((int) ($right['manual_sort'] ?? 0)) <=> ((int) ($left['manual_sort'] ?? 0)))
                ?: (((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0)));
        });

        return $items;
    }

    private function nextId(array $items): int
    {
        $maxId = 0;
        foreach ($items as $item) {
            $maxId = max($maxId, (int) ($item['id'] ?? 0));
        }

        return $maxId + 1;
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $statement = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE :column');
        $statement->execute(['column' => $column]);

        return (bool) $statement->fetch();
    }
}
