<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;
use PDOException;

final class MediaGalleryRepository
{
    private ?bool $galleryTableAvailable = null;

    /**
     * @param array<int, array{asset_id:int, sort?:int, is_cover?:bool}> $items
     */
    public function replace(string $entityType, int $entityId, array $items): void
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!($pdo instanceof PDO) || !$this->hasGalleryTable($pdo)) {
            return;
        }

        // Delete existing
        $delete = $pdo->prepare('DELETE FROM media_gallery WHERE entity_type = :entity_type AND entity_id = :entity_id');
        $delete->execute(['entity_type' => $entityType, 'entity_id' => $entityId]);

        if ($items === []) {
            return;
        }

        // Insert new
        $insert = $pdo->prepare(
            'INSERT INTO media_gallery (entity_type, entity_id, media_asset_id, sort_order, is_cover, created_at)
             VALUES (:entity_type, :entity_id, :media_asset_id, :sort_order, :is_cover, NOW())'
        );

        foreach ($items as $item) {
            if (empty($item['asset_id'])) {
                continue;
            }
            $insert->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'media_asset_id' => (int) $item['asset_id'],
                'sort_order' => (int) ($item['sort'] ?? 0),
                'is_cover' => !empty($item['is_cover']) ? 1 : 0,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(string $entityType, int $entityId): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!($pdo instanceof PDO) || !$this->hasGalleryTable($pdo)) {
            return [];
        }

        $statement = $pdo->prepare(
            'SELECT g.id, g.media_asset_id, g.sort_order, g.is_cover, a.file_path, a.file_name, a.mime_type, a.thumb_url, a.file_ext
             FROM media_gallery g
             LEFT JOIN media_assets a ON a.id = g.media_asset_id
             WHERE g.entity_type = :entity_type AND g.entity_id = :entity_id
             ORDER BY g.sort_order ASC, g.id ASC'
        );
        $statement->execute(['entity_type' => $entityType, 'entity_id' => $entityId]);
        $rows = $statement->fetchAll();

        if (!is_array($rows)) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'asset_id' => (int) ($row['media_asset_id'] ?? 0),
                'sort' => (int) ($row['sort_order'] ?? 0),
                'is_cover' => (int) ($row['is_cover'] ?? 0) === 1,
                'file_path' => (string) ($row['file_path'] ?? ''),
                'file_name' => (string) ($row['file_name'] ?? ''),
                'mime_type' => (string) ($row['mime_type'] ?? ''),
                'thumb_url' => (string) ($row['thumb_url'] ?? ''),
                'file_ext' => (string) ($row['file_ext'] ?? ''),
            ];
        }, $rows);
    }

    /**
     * @param array<int, int> $entityIds
     * @return array<int, array<string, mixed>>
     */
    public function coverMap(string $entityType, array $entityIds): array
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map('intval', $entityIds), static fn (int $id): bool => $id > 0)));
        if ($normalizedIds === []) {
            return [];
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!($pdo instanceof PDO) || !$this->hasGalleryTable($pdo)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
        $sql = sprintf(
            'SELECT g.entity_id, g.media_asset_id, g.sort_order, g.is_cover, a.file_path, a.file_name, a.mime_type, a.thumb_url, a.file_ext
             FROM media_gallery g
             LEFT JOIN media_assets a ON a.id = g.media_asset_id
             WHERE g.entity_type = ? AND g.entity_id IN (%s)
             ORDER BY g.entity_id ASC, g.is_cover DESC, g.sort_order ASC, g.id ASC',
            $placeholders
        );
        $statement = $pdo->prepare($sql);
        $statement->execute(array_merge([$entityType], $normalizedIds));
        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $covers = [];
        foreach ($rows as $row) {
            $entityId = (int) ($row['entity_id'] ?? 0);
            if ($entityId <= 0 || isset($covers[$entityId])) {
                continue;
            }

            $covers[$entityId] = [
                'asset_id' => (int) ($row['media_asset_id'] ?? 0),
                'sort' => (int) ($row['sort_order'] ?? 0),
                'is_cover' => (int) ($row['is_cover'] ?? 0) === 1,
                'file_path' => (string) ($row['file_path'] ?? ''),
                'file_name' => (string) ($row['file_name'] ?? ''),
                'mime_type' => (string) ($row['mime_type'] ?? ''),
                'thumb_url' => (string) ($row['thumb_url'] ?? ''),
                'file_ext' => (string) ($row['file_ext'] ?? ''),
            ];
        }

        return $covers;
    }

    public function deleteByEntity(string $entityType, int $entityId): void
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!($pdo instanceof PDO) || !$this->hasGalleryTable($pdo)) {
            return;
        }

        $delete = $pdo->prepare('DELETE FROM media_gallery WHERE entity_type = :entity_type AND entity_id = :entity_id');
        $delete->execute(['entity_type' => $entityType, 'entity_id' => $entityId]);
    }

    private function hasGalleryTable(PDO $pdo): bool
    {
        if ($this->galleryTableAvailable !== null) {
            return $this->galleryTableAvailable;
        }

        try {
            $statement = $pdo->query("SHOW TABLES LIKE 'media_gallery'");
            $this->galleryTableAvailable = $statement !== false && $statement->fetchColumn() !== false;
        } catch (PDOException) {
            $this->galleryTableAvailable = false;
        }

        return $this->galleryTableAvailable;
    }
}
