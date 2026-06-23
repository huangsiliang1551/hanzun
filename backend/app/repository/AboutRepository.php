<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class AboutRepository
{
    public function pages(): array
    {
        $runtimePages = $this->readRuntimePages();
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->query('SELECT id, page_key, name_zh, is_enabled FROM about_pages ORDER BY id ASC');
            $pages = $statement->fetchAll();

            if (!is_array($pages)) {
                return $runtimePages;
            }

            foreach ($pages as &$page) {
                $page['blocks'] = $this->blocksFromDatabase((int) $page['id']);
            }

            return $this->mergeRuntimePages($pages, $runtimePages);
        }

        return $runtimePages;
    }

    public function page(int $id): ?array
    {
        $pages = $this->pages();
        foreach ($pages as $page) {
            if ((int) ($page['id'] ?? 0) === $id) {
                return $page;
            }
        }

        if ($id === 1) {
            foreach ($pages as $page) {
                if (trim((string) ($page['page_key'] ?? '')) === 'company-about') {
                    return $page;
                }
            }
        }

        return null;
    }

    public function blocks(int $pageId): array
    {
        $page = $this->page($pageId);

        return is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
    }

    public function updateBlocks(int $pageId, array $blocks): ?array
    {
        $targetPage = $this->page($pageId);
        if ($targetPage === null) {
            return null;
        }

        $resolvedPageId = (int) ($targetPage['id'] ?? 0);
        if ($resolvedPageId <= 0) {
            return null;
        }

        $normalizedBlocks = $this->normalizeBlocks($resolvedPageId, $blocks);

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $databasePage = $this->findDatabasePage($resolvedPageId);
            if ($databasePage !== null) {
                $pdo->beginTransaction();

                try {
                    $deleteStatement = $pdo->prepare('DELETE FROM about_blocks WHERE about_page_id = :about_page_id');
                    $deleteStatement->execute(['about_page_id' => $resolvedPageId]);

                    $insertStatement = $pdo->prepare(
                        'INSERT INTO about_blocks (about_page_id, block_type, title_zh, subtitle_zh, content_zh, extra_config, sort, is_enabled)
                         VALUES (:about_page_id, :block_type, :title_zh, :subtitle_zh, :content_zh, :extra_config, :sort, :is_enabled)'
                    );

                    foreach ($normalizedBlocks as $index => $block) {
                        $insertStatement->execute([
                            'about_page_id' => $resolvedPageId,
                            'block_type' => (string) ($block['block_type'] ?? 'text'),
                            'title_zh' => (string) ($block['title_zh'] ?? ''),
                            'subtitle_zh' => (string) ($block['subtitle_zh'] ?? ''),
                            'content_zh' => (string) ($block['content_zh'] ?? ''),
                            'extra_config' => $this->normalizeExtraConfig($block['extra_config'] ?? []),
                            'sort' => (int) ($block['sort'] ?? (100 - $index)),
                            'is_enabled' => !empty($block['is_enabled']) ? 1 : 0,
                        ]);
                    }

                    $pdo->commit();

                    return $this->page($resolvedPageId);
                } catch (\Throwable $exception) {
                    $pdo->rollBack();
                    throw $exception;
                }
            }
        }

        $pages = $this->readRuntimePages();
        foreach ($pages as $index => $page) {
            if ((int) ($page['id'] ?? 0) !== $resolvedPageId) {
                continue;
            }

            $pages[$index]['blocks'] = $normalizedBlocks;
            $this->writeRuntimePages($pages);

            return $pages[$index];
        }

        return null;
    }

    private function normalizeBlocks(int $pageId, array $blocks): array
    {
        $existingBlocks = $this->blocks($pageId);
        $nextId = array_reduce($existingBlocks, static function (int $carry, array $block): int {
            return max($carry, (int) ($block['id'] ?? 0));
        }, 0) + 1;

        $normalized = [];
        foreach (array_values($blocks) as $index => $block) {
            if (!is_array($block)) {
                continue;
            }

            $blockId = (int) ($block['id'] ?? 0);
            if ($blockId <= 0 || isset($normalized[$blockId])) {
                $blockId = $nextId;
                $nextId += 1;
            }

            $normalized[$blockId] = [
                'id' => $blockId,
                'block_type' => (string) ($block['block_type'] ?? 'text'),
                'title_zh' => (string) ($block['title_zh'] ?? ''),
                'subtitle_zh' => (string) ($block['subtitle_zh'] ?? ''),
                'content_zh' => (string) ($block['content_zh'] ?? ''),
                'extra_config' => $this->decodeJsonField($block['extra_config'] ?? []),
                'sort' => (int) ($block['sort'] ?? ((count($blocks) - $index) * 10)),
                'is_enabled' => !empty($block['is_enabled']) ? 1 : 0,
            ];
        }

        return array_values($normalized);
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage($this->storagePath());
    }

    private function storagePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/about_pages.json';
    }

    private function readRuntimePages(): array
    {
        $path = $this->storagePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $pages = [];
        foreach ($decoded as $page) {
            if (!is_array($page)) {
                continue;
            }

            $blocks = [];
            foreach (($page['blocks'] ?? []) as $block) {
                if (!is_array($block)) {
                    continue;
                }

                $blocks[] = [
                    'id' => (int) ($block['id'] ?? 0),
                    'block_type' => (string) ($block['block_type'] ?? 'text'),
                    'title_zh' => (string) ($block['title_zh'] ?? ''),
                    'subtitle_zh' => (string) ($block['subtitle_zh'] ?? ''),
                    'content_zh' => (string) ($block['content_zh'] ?? ''),
                    'extra_config' => $this->decodeJsonField($block['extra_config'] ?? []),
                    'sort' => (int) ($block['sort'] ?? 0),
                    'is_enabled' => !empty($block['is_enabled']) ? 1 : 0,
                ];
            }

            $pages[] = [
                'id' => (int) ($page['id'] ?? 0),
                'page_key' => (string) ($page['page_key'] ?? ''),
                'name_zh' => (string) ($page['name_zh'] ?? ''),
                'is_enabled' => !empty($page['is_enabled']) ? 1 : 0,
                'blocks' => $blocks,
            ];
        }

        usort($pages, static fn (array $left, array $right): int => ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0)));

        return $pages;
    }

    private function writeRuntimePages(array $pages): void
    {
        $path = $this->storagePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($pages), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function normalizeExtraConfig(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        }

        return '{}';
    }

    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function blocksFromDatabase(int $pageId): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return [];
        }

        $statement = $pdo->prepare(
            'SELECT id, block_type, title_zh, subtitle_zh, content_zh, extra_config, sort, is_enabled
             FROM about_blocks
             WHERE about_page_id = :about_page_id
             ORDER BY sort DESC, id ASC'
        );
        $statement->execute(['about_page_id' => $pageId]);
        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        return array_map(function (array $row): array {
            $row['extra_config'] = $this->decodeJsonField($row['extra_config'] ?? []);
            return $row;
        }, $rows);
    }

    private function findDatabasePage(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return null;
        }

        $statement = $pdo->prepare('SELECT id, page_key, name_zh, is_enabled FROM about_pages WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $page = $statement->fetch();
        if (!is_array($page)) {
            return null;
        }

        $page['blocks'] = $this->blocksFromDatabase($id);

        return $page;
    }

    private function mergeRuntimePages(array $databasePages, array $runtimePages): array
    {
        $seenIds = [];
        $seenKeys = [];
        foreach ($databasePages as $index => $page) {
            $pageId = (int) ($page['id'] ?? 0);
            $pageKey = trim((string) ($page['page_key'] ?? ''));
            $seenIds[$pageId] = true;
            if ($pageKey !== '') {
                $seenKeys[$pageKey] = $index;
            }
        }

        foreach ($runtimePages as $page) {
            $id = (int) ($page['id'] ?? 0);
            $pageKey = trim((string) ($page['page_key'] ?? ''));

            if ($pageKey !== '' && isset($seenKeys[$pageKey])) {
                $existingIndex = $seenKeys[$pageKey];
                $existing = $databasePages[$existingIndex];
                $page['id'] = (int) ($existing['id'] ?? $id);
                $databasePages[$existingIndex] = array_merge($existing, $page);
                continue;
            }

            if (isset($seenIds[$id])) {
                continue;
            }

            $databasePages[] = $page;
        }

        usort($databasePages, static fn (array $left, array $right): int => ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0)));

        return $databasePages;
    }
}
