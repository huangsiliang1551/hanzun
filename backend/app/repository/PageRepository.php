<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class PageRepository
{
    public function list(array $query = []): array
    {
        $normalized = $this->normalizeListQuery($query);
        if ($this->preferRuntimeStorage()) {
            return $this->formatListResult($this->readRuntimeItems(), $normalized);
        }

        $items = [];
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $items = $this->listRowsFromDatabase($pdo, $normalized);
        }

        if ($this->runtimeOverlayEnabled()) {
            $items = $this->mergeRuntimeItems($items, $this->readRuntimeItems());
        }

        return $this->formatListResult($items, $normalized);
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

        return $this->runtimeOverlayEnabled() ? $this->findRuntime($id) : null;
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        if ($this->preferRuntimeStorage()) {
            return $this->runtimeSlugExists($slug, $excludeId);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT id
                 FROM pages
                 WHERE slug = :slug AND id <> :exclude_id
                 LIMIT 1'
            );
            $statement->execute([
                'slug' => $slug,
                'exclude_id' => $excludeId,
            ]);

            if ((bool) $statement->fetchColumn()) {
                return true;
            }
        }

        return $this->runtimeOverlayEnabled() ? $this->runtimeSlugExists($slug, $excludeId) : false;
    }

    public function create(array $payload): array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->createRuntime($payload);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO pages (page_type, title_zh, summary_zh, content_zh, publish_status, translation_status, seo_status, slug, seo_title, seo_keywords, seo_description, publish_time, created_by, updated_by, created_at, updated_at)
                 VALUES (:page_type, :title_zh, :summary_zh, :content_zh, :publish_status, :translation_status, :seo_status, :slug, :seo_title, :seo_keywords, :seo_description, :publish_time, :created_by, :updated_by, NOW(), NOW())'
            );
            $statement->execute([
                'page_type' => $payload['page_type'],
                'title_zh' => $payload['title_zh'],
                'summary_zh' => $payload['summary_zh'],
                'content_zh' => $payload['content_zh'],
                'publish_status' => $payload['publish_status'],
                'translation_status' => $payload['translation_status'],
                'seo_status' => $payload['seo_status'],
                'slug' => $payload['slug'],
                'seo_title' => $payload['seo_title'],
                'seo_keywords' => $payload['seo_keywords'],
                'seo_description' => $payload['seo_description'],
                'publish_time' => $payload['publish_time'],
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
                return $this->runtimeOverlayEnabled() ? $this->updateRuntime($id, $payload) : null;
            }

            $statement = $pdo->prepare(
                'UPDATE pages
                 SET page_type = :page_type, title_zh = :title_zh, summary_zh = :summary_zh, content_zh = :content_zh, publish_status = :publish_status, translation_status = :translation_status, seo_status = :seo_status, slug = :slug, seo_title = :seo_title, seo_keywords = :seo_keywords, seo_description = :seo_description, publish_time = :publish_time, updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'page_type' => $payload['page_type'],
                'title_zh' => $payload['title_zh'],
                'summary_zh' => $payload['summary_zh'],
                'content_zh' => $payload['content_zh'],
                'publish_status' => $payload['publish_status'],
                'translation_status' => $payload['translation_status'],
                'seo_status' => $payload['seo_status'],
                'slug' => $payload['slug'],
                'seo_title' => $payload['seo_title'],
                'seo_keywords' => $payload['seo_keywords'],
                'seo_description' => $payload['seo_description'],
                'publish_time' => $payload['publish_time'],
                'updated_by' => $payload['updated_by'],
            ]);

            return $this->findDatabase($id);
        }

        return $this->runtimeOverlayEnabled() ? $this->updateRuntime($id, $payload) : null;
    }

    public function updatePublishStatus(int $id, string $publishStatus, ?string $publishTime, ?int $updatedBy): ?array
    {
        $page = $this->find($id);
        if ($page === null) {
            return null;
        }

        $page['publish_status'] = $publishStatus;
        $page['publish_time'] = $publishTime;
        $page['updated_by'] = $updatedBy;

        return $this->update($id, $page);
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
                return $this->runtimeOverlayEnabled() ? $this->deleteRuntime($id) : null;
            }

            $statement = $pdo->prepare('DELETE FROM pages WHERE id = :id');
            $statement->execute(['id' => $id]);

            return $record;
        }

        return $this->runtimeOverlayEnabled() ? $this->deleteRuntime($id) : null;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function normalizeListQuery(array $query): array
    {
        $sortField = (string) ($query['sort_field'] ?? 'publish_time');
        if (!in_array($sortField, ['publish_time', 'id', 'title_zh', 'page_type'], true)) {
            $sortField = 'publish_time';
        }

        $sortOrder = strtolower((string) ($query['sort_order'] ?? 'desc'));
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }

        $publishStatus = (string) ($query['publish_status'] ?? '');
        if (!in_array($publishStatus, ['draft', 'published', 'offline'], true)) {
            $publishStatus = '';
        }

        $pageType = (string) ($query['page_type'] ?? '');
        if (!in_array($pageType, ['page', 'campaign', 'landing'], true)) {
            $pageType = '';
        }

        $pageSizeInput = $query['page_size'] ?? null;
        $pageSize = $pageSizeInput === null || $pageSizeInput === ''
            ? 0
            : max(1, min(100, (int) $pageSizeInput));

        return [
            'publish_status' => $publishStatus,
            'page_type' => $pageType,
            'keyword' => trim((string) ($query['keyword'] ?? '')),
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'page_size' => $pageSize,
            'sort_field' => $sortField,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    private function listRowsFromDatabase(PDO $pdo, array $query): array
    {
        $conditions = [];
        $params = [];
        if ($query['publish_status'] !== '') {
            $conditions[] = 'p.publish_status = :publish_status';
            $params['publish_status'] = $query['publish_status'];
        }
        if ($query['page_type'] !== '') {
            $conditions[] = 'p.page_type = :page_type';
            $params['page_type'] = $query['page_type'];
        }
        if ($query['keyword'] !== '') {
            $conditions[] = '(p.title_zh LIKE :keyword OR p.slug LIKE :keyword)';
            $params['keyword'] = '%' . $query['keyword'] . '%';
        }

        $whereSql = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $statement = $pdo->prepare(
            'SELECT p.id, p.page_type, p.title_zh, p.summary_zh, p.content_zh, p.publish_status, p.translation_status, p.seo_status, p.slug, p.seo_title, p.seo_keywords, p.seo_description, p.publish_time, p.created_by, p.updated_by, p.created_at, p.updated_at
             FROM pages p' . $whereSql
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->execute();
        $items = $statement->fetchAll();

        return is_array($items) ? $items : [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function formatListResult(array $items, array $query): array
    {
        $filtered = $this->filterItems($items, $query);
        $sorted = $this->sortItems($filtered, (string) $query['sort_field'], (string) $query['sort_order']);
        $total = count($sorted);
        $pageSize = (int) $query['page_size'];
        $limitEnabled = $pageSize > 0;
        $resolvedPageSize = $limitEnabled ? $pageSize : max(1, $total);
        $paged = $sorted;

        if ($limitEnabled) {
            $offset = (((int) $query['page']) - 1) * $pageSize;
            $paged = array_slice($sorted, $offset, $pageSize);
        }

        return [
            'items' => array_values($paged),
            'pagination' => [
                'page' => $limitEnabled ? (int) $query['page'] : 1,
                'page_size' => $resolvedPageSize,
                'total' => $total,
                'total_pages' => $limitEnabled ? max(1, (int) ceil($total / max(1, $pageSize))) : 1,
            ],
            'sort' => [
                'field' => (string) $query['sort_field'],
                'order' => (string) $query['sort_order'],
            ],
        ];
    }

    private function findDatabase(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return null;
        }

        $statement = $pdo->prepare('SELECT * FROM pages WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage($this->storagePath());
    }

    private function runtimeOverlayEnabled(): bool
    {
        return !env_flag('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK');
    }

    private function storagePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/pages.json';
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

    private function findRuntime(int $id): ?array
    {
        foreach ($this->readRuntimeItems() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    private function runtimeSlugExists(string $slug, int $excludeId = 0): bool
    {
        foreach ($this->readRuntimeItems() as $item) {
            if ((int) ($item['id'] ?? 0) === $excludeId) {
                continue;
            }

            if (trim((string) ($item['slug'] ?? '')) === $slug) {
                return true;
            }
        }

        return false;
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

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizeRuntimeItem(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'page_type' => (string) ($item['page_type'] ?? 'page'),
            'title_zh' => (string) ($item['title_zh'] ?? ''),
            'summary_zh' => (string) ($item['summary_zh'] ?? ''),
            'content_zh' => (string) ($item['content_zh'] ?? ''),
            'publish_status' => (string) ($item['publish_status'] ?? 'draft'),
            'translation_status' => (string) ($item['translation_status'] ?? 'pending'),
            'seo_status' => (string) ($item['seo_status'] ?? 'pending'),
            'slug' => (string) ($item['slug'] ?? ''),
            'seo_title' => (string) ($item['seo_title'] ?? ''),
            'seo_keywords' => (string) ($item['seo_keywords'] ?? ''),
            'seo_description' => (string) ($item['seo_description'] ?? ''),
            'publish_time' => $item['publish_time'] ?? null,
            'created_by' => isset($item['created_by']) ? (int) $item['created_by'] : null,
            'updated_by' => isset($item['updated_by']) ? (int) $item['updated_by'] : null,
            'created_at' => (string) ($item['created_at'] ?? ''),
            'updated_at' => (string) ($item['updated_at'] ?? ''),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $databaseItems
     * @param array<int, array<string, mixed>> $runtimeItems
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    private function filterItems(array $items, array $query): array
    {
        return array_values(array_filter($items, function (array $item) use ($query): bool {
            if ($query['publish_status'] !== '' && (string) ($item['publish_status'] ?? '') !== $query['publish_status']) {
                return false;
            }

            if ($query['page_type'] !== '' && (string) ($item['page_type'] ?? '') !== $query['page_type']) {
                return false;
            }

            $keyword = (string) ($query['keyword'] ?? '');
            if ($keyword !== '') {
                $haystack = implode(' ', [
                    (string) ($item['title_zh'] ?? ''),
                    (string) ($item['slug'] ?? ''),
                ]);

                if (mb_stripos($haystack, $keyword) === false) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function sortItems(array $items, string $sortField, string $sortOrder): array
    {
        usort($items, function (array $left, array $right) use ($sortField, $sortOrder): int {
            $result = match ($sortField) {
                'id' => ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0)),
                'title_zh' => strcmp((string) ($left['title_zh'] ?? ''), (string) ($right['title_zh'] ?? '')),
                'page_type' => strcmp((string) ($left['page_type'] ?? ''), (string) ($right['page_type'] ?? '')),
                default => strcmp((string) ($left['publish_time'] ?? ''), (string) ($right['publish_time'] ?? '')),
            };

            if ($sortOrder === 'desc') {
                $result *= -1;
            }

            return $result !== 0
                ? $result
                : (((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0)));
        });

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function nextId(array $items): int
    {
        $maxId = 0;
        foreach ($items as $item) {
            $maxId = max($maxId, (int) ($item['id'] ?? 0));
        }

        return $maxId + 1;
    }
}
