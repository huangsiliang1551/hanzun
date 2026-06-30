<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class TeamRepository
{
    public function list(): array
    {
        $runtimeItems = $this->readRuntimeItems();
        if ($this->preferRuntimeStorage()) {
            return $this->sortItems($runtimeItems !== [] ? $runtimeItems : $this->defaultItems());
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->query(
                'SELECT id, name_zh, title_zh, department_zh, bio_zh, avatar_asset_id, email, phone, whatsapp, wechat, publish_status, translation_status, is_home_featured, manual_sort, created_by, updated_by, created_at, updated_at
                 FROM team_members
                 ORDER BY manual_sort DESC, id DESC'
            );
            $rows = $statement->fetchAll();
            $items = is_array($rows) ? $rows : [];

            return $this->sortItems($this->mergeRuntimeItems($items, $runtimeItems));
        }

        return $this->sortItems($runtimeItems !== [] ? $runtimeItems : $this->defaultItems());
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
            $statement = $pdo->prepare(
                'INSERT INTO team_members (name_zh, title_zh, department_zh, bio_zh, avatar_asset_id, email, phone, whatsapp, wechat, publish_status, translation_status, is_home_featured, manual_sort, created_by, updated_by, created_at, updated_at)
                 VALUES (:name_zh, :title_zh, :department_zh, :bio_zh, :avatar_asset_id, :email, :phone, :whatsapp, :wechat, :publish_status, :translation_status, :is_home_featured, :manual_sort, :created_by, :updated_by, NOW(), NOW())'
            );
            $statement->execute([
                'name_zh' => $payload['name_zh'],
                'title_zh' => $payload['title_zh'],
                'department_zh' => $payload['department_zh'],
                'bio_zh' => $payload['bio_zh'],
                'avatar_asset_id' => $payload['avatar_asset_id'],
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'whatsapp' => $payload['whatsapp'],
                'wechat' => $payload['wechat'],
                'publish_status' => $payload['publish_status'],
                'translation_status' => $payload['translation_status'],
                'is_home_featured' => $payload['is_home_featured'],
                'manual_sort' => $payload['manual_sort'],
                'created_by' => $payload['created_by'],
                'updated_by' => $payload['updated_by'],
            ]);

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

            $statement = $pdo->prepare(
                'UPDATE team_members
                 SET name_zh = :name_zh, title_zh = :title_zh, department_zh = :department_zh, bio_zh = :bio_zh, avatar_asset_id = :avatar_asset_id, email = :email, phone = :phone, whatsapp = :whatsapp, wechat = :wechat, publish_status = :publish_status, translation_status = :translation_status, is_home_featured = :is_home_featured, manual_sort = :manual_sort, updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'name_zh' => $payload['name_zh'],
                'title_zh' => $payload['title_zh'],
                'department_zh' => $payload['department_zh'],
                'bio_zh' => $payload['bio_zh'],
                'avatar_asset_id' => $payload['avatar_asset_id'],
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'whatsapp' => $payload['whatsapp'],
                'wechat' => $payload['wechat'],
                'publish_status' => $payload['publish_status'],
                'translation_status' => $payload['translation_status'],
                'is_home_featured' => $payload['is_home_featured'],
                'manual_sort' => $payload['manual_sort'],
                'updated_by' => $payload['updated_by'],
            ]);

            return $this->findDatabase($id);
        }

        return $this->updateRuntime($id, $payload);
    }

    public function updatePublishStatus(int $id, string $publishStatus, ?int $updatedBy): ?array
    {
        $record = $this->find($id);
        if ($record === null) {
            return null;
        }

        $record['publish_status'] = $publishStatus;
        $record['updated_by'] = $updatedBy;

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

            $statement = $pdo->prepare('DELETE FROM team_members WHERE id = :id');
            $statement->execute(['id' => $id]);

            return $record;
        }

        return $this->deleteRuntime($id);
    }

    public function batchUpdatePublishStatus(array $ids, string $publishStatus, ?int $updatedBy = null): int
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $statement = $pdo->prepare(
                'UPDATE team_members SET publish_status = ?, updated_by = ?, updated_at = NOW() WHERE id IN (' . $placeholders . ')'
            );
            $statement->execute(array_merge([$publishStatus, $updatedBy], $ids));

            return $statement->rowCount();
        }

        $count = 0;
        foreach ($ids as $id) {
            if ($this->updatePublishStatus((int) $id, $publishStatus, $updatedBy) !== null) {
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
                'UPDATE team_members SET manual_sort = :manual_sort, updated_at = NOW() WHERE id = :id'
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
        return dirname(__DIR__, 2) . '/runtime/storage/team_members.json';
    }

    private function findDatabase(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return null;
        }

        $statement = $pdo->prepare(
            'SELECT id, name_zh, title_zh, department_zh, bio_zh, avatar_asset_id, email, phone, whatsapp, wechat, publish_status, translation_status, is_home_featured, manual_sort, created_by, updated_by, created_at, updated_at
             FROM team_members
             WHERE id = :id
             LIMIT 1'
        );
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
        if ($items === []) {
            $items = $this->defaultItems();
        }
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
        if ($items === []) {
            $items = $this->defaultItems();
        }
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
        $items = $this->readRuntimeItems();
        if ($items === []) {
            $items = $this->defaultItems();
        }

        $record = null;
        $filtered = [];
        foreach ($items as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $record = $item;
                continue;
            }

            $filtered[] = $item;
        }

        if ($record === null) {
            return null;
        }

        $this->writeRuntimeItems($filtered);

        return $record;
    }

    private function normalizeRuntimeItem(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'name_zh' => (string) ($item['name_zh'] ?? ''),
            'title_zh' => (string) ($item['title_zh'] ?? ''),
            'department_zh' => (string) ($item['department_zh'] ?? ''),
            'bio_zh' => (string) ($item['bio_zh'] ?? ''),
            'avatar_asset_id' => isset($item['avatar_asset_id']) && $item['avatar_asset_id'] !== '' ? (int) $item['avatar_asset_id'] : null,
            'email' => (string) ($item['email'] ?? ''),
            'phone' => (string) ($item['phone'] ?? ''),
            'whatsapp' => (string) ($item['whatsapp'] ?? ''),
            'wechat' => (string) ($item['wechat'] ?? ''),
            'publish_status' => (string) ($item['publish_status'] ?? 'draft'),
            'translation_status' => (string) ($item['translation_status'] ?? 'pending'),
            'is_home_featured' => !empty($item['is_home_featured']) ? 1 : 0,
            'manual_sort' => (int) ($item['manual_sort'] ?? 0),
            'created_by' => isset($item['created_by']) ? (int) $item['created_by'] : null,
            'updated_by' => isset($item['updated_by']) ? (int) $item['updated_by'] : null,
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

    private function defaultItems(): array
    {
        return [
            [
                'id' => 1,
                'name_zh' => 'Amy Zhang',
                'title_zh' => '海外销售经理',
                'department_zh' => '国际销售部',
                'bio_zh' => '负责海外客户需求梳理、方案匹配、报价推进与交付协同。',
                'avatar_asset_id' => 4,
                'email' => 'amy.zhang@hanzunmachinery.com',
                'phone' => '+8615216813602',
                'whatsapp' => '+8615216813602',
                'wechat' => null,
                'publish_status' => 'published',
                'translation_status' => 'completed',
                'is_home_featured' => 1,
                'manual_sort' => 100,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => '2026-06-14 00:47:26',
                'updated_at' => '2026-06-14 00:47:26',
            ],
        ];
    }
}
