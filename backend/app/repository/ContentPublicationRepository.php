<?php

declare(strict_types=1);

namespace app\repository;

final class ContentPublicationRepository
{
    public function __construct(
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository()
    ) {
    }

    public function snapshot(string $entityType, int $entityId): ?array
    {
        if ($entityId <= 0) {
            return null;
        }

        $items = $this->snapshots($entityType);

        return isset($items[$entityId]) && is_array($items[$entityId]) ? $items[$entityId] : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function snapshots(string $entityType): array
    {
        $items = $this->preferRuntimeStorage($entityType)
            ? ($this->readRuntimeContentLive()[$this->snapshotKey($entityType)] ?? [])
            : $this->systemSettingRepository->get('content_live', $this->snapshotKey($entityType), []);
        if (!is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $id => $item) {
            $entityId = (int) $id;
            if ($entityId <= 0 || !is_array($item)) {
                continue;
            }

            $normalized[$entityId] = $item;
        }

        return $normalized;
    }

    public function saveSnapshot(string $entityType, int $entityId, array $record): array
    {
        $items = $this->snapshots($entityType);
        $items[$entityId] = $record;
        if ($this->preferRuntimeStorage($entityType)) {
            $contentLive = $this->readRuntimeContentLive();
            $contentLive[$this->snapshotKey($entityType)] = $items;
            $this->writeRuntimeContentLive($contentLive);
        } else {
            $this->systemSettingRepository->put('content_live', $this->snapshotKey($entityType), $items);
        }

        return $items[$entityId];
    }

    public function publishMeta(string $entityType, int $entityId): array
    {
        $items = $this->preferRuntimeStorage($entityType)
            ? ($this->readRuntimeContentLive()[$this->publishMetaKey($entityType)] ?? [])
            : $this->systemSettingRepository->get('content_live', $this->publishMetaKey($entityType), []);
        if (!is_array($items)) {
            $items = [];
        }

        $meta = $items[$entityId] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }

        return array_replace($this->metaDefaults(), $meta);
    }

    public function savePublishMeta(string $entityType, int $entityId, array $meta): array
    {
        $items = $this->preferRuntimeStorage($entityType)
            ? ($this->readRuntimeContentLive()[$this->publishMetaKey($entityType)] ?? [])
            : $this->systemSettingRepository->get('content_live', $this->publishMetaKey($entityType), []);
        if (!is_array($items)) {
            $items = [];
        }

        $items[$entityId] = array_replace($this->metaDefaults(), $meta);
        if ($this->preferRuntimeStorage($entityType)) {
            $contentLive = $this->readRuntimeContentLive();
            $contentLive[$this->publishMetaKey($entityType)] = $items;
            $this->writeRuntimeContentLive($contentLive);
        } else {
            $this->systemSettingRepository->put('content_live', $this->publishMetaKey($entityType), $items);
        }

        return $items[$entityId];
    }

    private function snapshotKey(string $entityType): string
    {
        return trim($entityType) . '_snapshots';
    }

    private function publishMetaKey(string $entityType): string
    {
        return trim($entityType) . '_publish_meta';
    }

    private function metaDefaults(): array
    {
        return [
            'draft_updated_at' => null,
            'live_updated_at' => null,
            'last_published_by' => '',
            'last_restored_by' => '',
            'publish_log' => [],
        ];
    }

    private function preferRuntimeStorage(string $entityType): bool
    {
        return should_prefer_runtime_storage($this->entityRuntimePaths($entityType));
    }

    /**
     * @return array<int, string>
     */
    private function entityRuntimePaths(string $entityType): array
    {
        $storageRoot = dirname(__DIR__, 2) . '/runtime/storage';

        return match (trim($entityType)) {
            'product' => [$storageRoot . '/products.json'],
            'solution' => [$storageRoot . '/solutions.json'],
            'article', 'news', 'case' => [$storageRoot . '/articles.json'],
            'page' => [$storageRoot . '/pages.json'],
            'team_member' => [$storageRoot . '/team_members.json'],
            'certificate' => [$storageRoot . '/certificates.json'],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function readRuntimeContentLive(): array
    {
        $storage = $this->readRuntimeStorage();
        $contentLive = $storage['content_live'] ?? [];

        return is_array($contentLive) ? $contentLive : [];
    }

    /**
     * @param array<string, mixed> $contentLive
     */
    private function writeRuntimeContentLive(array $contentLive): void
    {
        $storage = $this->readRuntimeStorage();
        $storage['content_live'] = $contentLive;
        $this->writeRuntimeStorage($storage);
    }

    private function runtimeStoragePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/system_settings.json';
    }

    /**
     * @return array<string, mixed>
     */
    private function readRuntimeStorage(): array
    {
        $path = $this->runtimeStoragePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $storage
     */
    private function writeRuntimeStorage(array $storage): void
    {
        $path = $this->runtimeStoragePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode($storage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
