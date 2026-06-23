<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class MediaRepository
{
    public function list(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->query(
                'SELECT id, folder_name, folder_id, storage_disk, file_path, file_name, original_name, file_ext, mime_type, file_size, thumb_url, width, height, duration_seconds, alt_text_zh, description_zh, status, created_at, updated_at
                 FROM media_assets
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

    public function create(array $payload): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO media_assets (folder_name, folder_id, storage_disk, file_path, file_name, original_name, file_ext, mime_type, file_size, sha1, thumb_url, width, height, duration_seconds, alt_text_zh, description_zh, uploaded_by, status, created_at, updated_at)
                 VALUES (:folder_name, :folder_id, :storage_disk, :file_path, :file_name, :original_name, :file_ext, :mime_type, :file_size, :sha1, :thumb_url, :width, :height, :duration_seconds, :alt_text_zh, :description_zh, :uploaded_by, :status, NOW(), NOW())'
            );
            $statement->execute([
                'folder_name' => $payload['folder_name'] ?? 'misc',
                'folder_id' => (int) ($payload['folder_id'] ?? 0),
                'storage_disk' => $payload['storage_disk'],
                'file_path' => $payload['file_path'],
                'file_name' => $payload['file_name'],
                'original_name' => $payload['original_name'] ?? $payload['file_name'],
                'file_ext' => $payload['file_ext'],
                'mime_type' => $payload['mime_type'],
                'file_size' => $payload['file_size'],
                'sha1' => $payload['sha1'],
                'thumb_url' => $payload['thumb_url'] ?? null,
                'width' => $payload['width'],
                'height' => $payload['height'],
                'duration_seconds' => $payload['duration_seconds'],
                'alt_text_zh' => $payload['alt_text_zh'],
                'description_zh' => $payload['description_zh'],
                'uploaded_by' => $payload['uploaded_by'],
                'status' => $payload['status'],
            ]);

            return $this->find((int) $pdo->lastInsertId()) ?? $payload;
        }

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

    public function update(int $id, array $payload): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $sets = [];
            $params = ['id' => $id];
            foreach (['folder_name', 'folder_id', 'file_name', 'file_path', 'alt_text_zh', 'description_zh', 'status'] as $col) {
                if (array_key_exists($col, $payload)) {
                    $sets[] = "{$col} = :{$col}";
                    $params[$col] = $payload[$col];
                }
            }
            if (!empty($sets)) {
                $sets[] = 'updated_at = NOW()';
                $statement = $pdo->prepare('UPDATE media_assets SET ' . implode(', ', $sets) . ' WHERE id = :id');
                $statement->execute($params);
            }

            return $this->find($id);
        }

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

    public function updateStatus(int $id, int $status): ?array
    {
        $record = $this->find($id);
        if ($record === null) {
            return null;
        }

        $record['status'] = $status;
        $record['updated_at'] = date('Y-m-d H:i:s');

        return $this->update($id, $record);
    }

    public function delete(int $id): ?array
    {
        $record = $this->find($id);
        if ($record === null) {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare('DELETE FROM media_assets WHERE id = :id');
            $statement->execute(['id' => $id]);

            return $record;
        }

        $items = array_values(array_filter(
            $this->readRuntimeItems(),
            static fn (array $item): bool => (int) ($item['id'] ?? 0) !== $id
        ));
        $this->writeRuntimeItems($items);

        return $record;
    }

    public function listFolders(): array
    {
        $counts = $this->assetCountsPerFolder();
        $pdo = DatabaseManager::instance()->connection();

        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->query(
                'SELECT id, parent_id, name, sort_order, created_at
                 FROM media_folders
                 ORDER BY parent_id ASC, sort_order ASC, id ASC'
            );
            $rows = $statement->fetchAll();
            if (!is_array($rows)) {
                return [];
            }

            return array_map(function (array $row) use ($counts): array {
                $row['id'] = (int) ($row['id'] ?? 0);
                $row['parent_id'] = (int) ($row['parent_id'] ?? 0);
                $row['sort_order'] = (int) ($row['sort_order'] ?? 0);
                $row['asset_count'] = (int) ($counts[(int) ($row['id'] ?? 0)] ?? 0);

                return $row;
            }, $rows);
        }

        $folders = [];
        foreach ($this->readRuntimeFolders() as $folder) {
            $folder['asset_count'] = (int) ($counts[(int) ($folder['id'] ?? 0)] ?? 0);
            $folders[] = $folder;
        }

        usort($folders, static function (array $left, array $right): int {
            return ((int) ($left['parent_id'] ?? 0) <=> (int) ($right['parent_id'] ?? 0))
                ?: ((int) ($left['sort_order'] ?? 0) <=> (int) ($right['sort_order'] ?? 0))
                ?: ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });

        return $folders;
    }

    public function findFolder(int $id): ?array
    {
        foreach ($this->listFolders() as $folder) {
            if ((int) ($folder['id'] ?? 0) === $id) {
                return $folder;
            }
        }

        return null;
    }

    public function createFolder(array $data): array
    {
        $payload = [
            'parent_id' => (int) ($data['parent_id'] ?? 0),
            'name' => (string) ($data['name'] ?? ''),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO media_folders (parent_id, name, sort_order, created_at)
                 VALUES (:parent_id, :name, :sort_order, NOW())'
            );
            $statement->execute($payload);

            return $this->findFolder((int) $pdo->lastInsertId()) ?? [];
        }

        $folders = $this->readRuntimeFolders();
        $record = $this->normalizeRuntimeFolder(array_merge($payload, [
            'id' => $this->nextFolderId($folders),
            'created_at' => date('Y-m-d H:i:s'),
        ]));
        $folders[] = $record;
        $this->writeRuntimeFolders($folders);

        return $record;
    }

    public function updateFolder(int $id, array $data): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $existing = $this->findFolder($id);
            if ($existing === null) {
                return null;
            }

            $statement = $pdo->prepare(
                'UPDATE media_folders
                 SET parent_id = :parent_id, name = :name, sort_order = :sort_order
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'parent_id' => (int) ($data['parent_id'] ?? ($existing['parent_id'] ?? 0)),
                'name' => (string) ($data['name'] ?? ($existing['name'] ?? '')),
                'sort_order' => (int) ($data['sort_order'] ?? ($existing['sort_order'] ?? 0)),
            ]);

            return $this->findFolder($id);
        }

        $folders = $this->readRuntimeFolders();
        foreach ($folders as $index => $folder) {
            if ((int) ($folder['id'] ?? 0) !== $id) {
                continue;
            }

            $folders[$index] = $this->normalizeRuntimeFolder(array_merge($folder, $data, ['id' => $id]));
            $this->writeRuntimeFolders($folders);

            return $folders[$index];
        }

        return null;
    }

    public function deleteFolder(int $id): ?array
    {
        $folder = $this->findFolder($id);
        if ($folder === null) {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare('DELETE FROM media_folders WHERE id = :id');
            $statement->execute(['id' => $id]);

            return $folder;
        }

        $folders = array_values(array_filter(
            $this->readRuntimeFolders(),
            static fn (array $item): bool => (int) ($item['id'] ?? 0) !== $id
        ));
        $this->writeRuntimeFolders($folders);

        return $folder;
    }

    public function updateFolderSort(int $id, int $sortOrder): ?array
    {
        return $this->updateFolder($id, ['sort_order' => $sortOrder]);
    }

    public function assetCountsPerFolder(): array
    {
        $counts = [];
        foreach ($this->list() as $item) {
            $fid = (int) ($item['folder_id'] ?? 0);
            $counts[$fid] = ($counts[$fid] ?? 0) + 1;
        }

        return $counts;
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage($this->storagePath());
    }

    private function storagePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/media_assets.json';
    }

    private function foldersStoragePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/media_folders.json';
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

    private function readRuntimeFolders(): array
    {
        $path = $this->foldersStoragePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_map(fn (array $item): array => $this->normalizeRuntimeFolder($item), array_filter($decoded, 'is_array'));
    }

    private function writeRuntimeFolders(array $folders): void
    {
        $path = $this->foldersStoragePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($folders), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function normalizeRuntimeItem(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'folder_name' => (string) ($item['folder_name'] ?? ''),
            'folder_id' => (int) ($item['folder_id'] ?? 0),
            'storage_disk' => (string) ($item['storage_disk'] ?? 'local'),
            'file_path' => (string) ($item['file_path'] ?? ''),
            'file_name' => (string) ($item['file_name'] ?? ''),
            'original_name' => (string) ($item['original_name'] ?? ($item['file_name'] ?? '')),
            'file_ext' => (string) ($item['file_ext'] ?? ''),
            'mime_type' => (string) ($item['mime_type'] ?? ''),
            'file_size' => (int) ($item['file_size'] ?? 0),
            'sha1' => (string) ($item['sha1'] ?? ''),
            'thumb_url' => $item['thumb_url'] ?? null,
            'width' => $item['width'] ?? null,
            'height' => $item['height'] ?? null,
            'duration_seconds' => $item['duration_seconds'] ?? null,
            'alt_text_zh' => (string) ($item['alt_text_zh'] ?? ''),
            'description_zh' => (string) ($item['description_zh'] ?? ''),
            'uploaded_by' => $item['uploaded_by'] ?? null,
            'status' => (int) ($item['status'] ?? 0),
            'created_at' => (string) ($item['created_at'] ?? ''),
            'updated_at' => (string) ($item['updated_at'] ?? ''),
        ];
    }

    private function normalizeRuntimeFolder(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'parent_id' => (int) ($item['parent_id'] ?? 0),
            'name' => (string) ($item['name'] ?? ''),
            'sort_order' => (int) ($item['sort_order'] ?? 0),
            'created_at' => (string) ($item['created_at'] ?? date('Y-m-d H:i:s')),
        ];
    }

    private function nextId(array $items): int
    {
        return array_reduce($items, static function (int $carry, array $item): int {
            return max($carry, (int) ($item['id'] ?? 0));
        }, 0) + 1;
    }

    private function nextFolderId(array $folders): int
    {
        return array_reduce($folders, static function (int $carry, array $item): int {
            return max($carry, (int) ($item['id'] ?? 0));
        }, 0) + 1;
    }
}
