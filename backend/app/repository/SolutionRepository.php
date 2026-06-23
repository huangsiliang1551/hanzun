<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class SolutionRepository
{
    public function list(array $query = []): array
    {
        $normalized = $this->normalizeListQuery($query);
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            return $this->listFromDatabase($pdo, $normalized);
        }

        return $this->listFromRuntime($normalized);
    }

    public function find(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare('SELECT * FROM solutions WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $row = $statement->fetch();

            return is_array($row) ? $row : null;
        }

        foreach ($this->runtimeItems() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        foreach ($this->runtimeItems() as $item) {
            if ((string) ($item['slug'] ?? '') === $slug && (int) ($item['id'] ?? 0) !== $excludeId) {
                return true;
            }
        }

        return false;
    }

    public function create(array $payload): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO solutions (category_id, name_zh, summary_zh, content_zh, flow_text_zh, capacity_text_zh, manual_asset_id, publish_status, translation_status, seo_status, is_home_featured, manual_sort, slug, seo_title, seo_keywords, seo_description, publish_time, created_by, updated_by, created_at, updated_at)
                 VALUES (:category_id, :name_zh, :summary_zh, :content_zh, :flow_text_zh, :capacity_text_zh, :manual_asset_id, :publish_status, :translation_status, :seo_status, :is_home_featured, :manual_sort, :slug, :seo_title, :seo_keywords, :seo_description, :publish_time, :created_by, :updated_by, NOW(), NOW())'
            );
            $statement->execute([
                'category_id' => $payload['category_id'],
                'name_zh' => $payload['name_zh'],
                'summary_zh' => $payload['summary_zh'],
                'content_zh' => $payload['content_zh'],
                'flow_text_zh' => $payload['flow_text_zh'],
                'capacity_text_zh' => $payload['capacity_text_zh'],
                'manual_asset_id' => $payload['manual_asset_id'],
                'publish_status' => $payload['publish_status'],
                'translation_status' => $payload['translation_status'],
                'seo_status' => $payload['seo_status'],
                'is_home_featured' => $payload['is_home_featured'],
                'manual_sort' => $payload['manual_sort'],
                'slug' => $payload['slug'],
                'seo_title' => $payload['seo_title'],
                'seo_keywords' => $payload['seo_keywords'],
                'seo_description' => $payload['seo_description'],
                'publish_time' => $payload['publish_time'],
                'created_by' => $payload['created_by'],
                'updated_by' => $payload['updated_by'],
            ]);

            return $this->find((int) $pdo->lastInsertId()) ?? $payload;
        }

        $items = $this->runtimeItems();
        $record = array_merge($payload, [
            'id' => $this->nextId($items),
            'created_at' => (string) ($payload['created_at'] ?? date('Y-m-d H:i:s')),
            'updated_at' => (string) ($payload['updated_at'] ?? date('Y-m-d H:i:s')),
        ]);
        $items[] = $record;
        $this->writeRuntimeItems($items);

        return $record;
    }

    public function update(int $id, array $payload): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'UPDATE solutions
                 SET category_id = :category_id, name_zh = :name_zh, summary_zh = :summary_zh, content_zh = :content_zh, flow_text_zh = :flow_text_zh, capacity_text_zh = :capacity_text_zh, manual_asset_id = :manual_asset_id, publish_status = :publish_status, translation_status = :translation_status, seo_status = :seo_status, is_home_featured = :is_home_featured, manual_sort = :manual_sort, slug = :slug, seo_title = :seo_title, seo_keywords = :seo_keywords, seo_description = :seo_description, publish_time = :publish_time, updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'category_id' => $payload['category_id'],
                'name_zh' => $payload['name_zh'],
                'summary_zh' => $payload['summary_zh'],
                'content_zh' => $payload['content_zh'],
                'flow_text_zh' => $payload['flow_text_zh'],
                'capacity_text_zh' => $payload['capacity_text_zh'],
                'manual_asset_id' => $payload['manual_asset_id'],
                'publish_status' => $payload['publish_status'],
                'translation_status' => $payload['translation_status'],
                'seo_status' => $payload['seo_status'],
                'is_home_featured' => $payload['is_home_featured'],
                'manual_sort' => $payload['manual_sort'],
                'slug' => $payload['slug'],
                'seo_title' => $payload['seo_title'],
                'seo_keywords' => $payload['seo_keywords'],
                'seo_description' => $payload['seo_description'],
                'publish_time' => $payload['publish_time'],
                'updated_by' => $payload['updated_by'],
            ]);

            return $this->find($id);
        }

        $items = $this->runtimeItems();
        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $id) {
                continue;
            }

            $items[$index] = array_merge($item, $payload, [
                'id' => $id,
                'updated_at' => (string) ($payload['updated_at'] ?? date('Y-m-d H:i:s')),
            ]);
            $this->writeRuntimeItems($items);

            return $items[$index];
        }

        return null;
    }

    public function updatePublishStatus(int $id, string $publishStatus, ?string $publishTime, ?int $updatedBy): ?array
    {
        $solution = $this->find($id);
        if ($solution === null) {
            return null;
        }

        $solution['publish_status'] = $publishStatus;
        $solution['publish_time'] = $publishTime;
        $solution['updated_by'] = $updatedBy;

        return $this->update($id, $solution);
    }

    public function delete(int $id): ?array
    {
        $record = $this->find($id);
        if ($record === null) {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare('DELETE FROM solutions WHERE id = :id');
            $statement->execute(['id' => $id]);

            return $record;
        }

        $items = array_values(array_filter(
            $this->runtimeItems(),
            static fn (array $item): bool => (int) ($item['id'] ?? 0) !== $id
        ));
        $this->writeRuntimeItems($items);

        return $record;
    }

    public function findCategory(int $id): ?array
    {
        foreach ($this->loadCategoryRows(false) as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }

        return null;
    }

    public function categorySlugExists(string $slug, int $excludeId = 0): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        foreach ($this->loadCategoryRows(false) as $row) {
            if ((string) ($row['slug'] ?? '') === $slug && (int) ($row['id'] ?? 0) !== $excludeId) {
                return true;
            }
        }

        return false;
    }

    public function createCategory(array $payload): array
    {
        $rows = $this->runtimeCategoryRows(false);
        $record = array_merge($payload, ['id' => $this->nextId($rows)]);
        $rows[] = $record;
        $this->writeRuntimeCategories($rows);

        return $record;
    }

    public function updateCategory(int $id, array $payload): ?array
    {
        $rows = $this->runtimeCategoryRows(false);
        foreach ($rows as $index => $row) {
            if ((int) ($row['id'] ?? 0) !== $id) {
                continue;
            }

            $rows[$index] = array_merge($row, $payload, ['id' => $id]);
            $this->writeRuntimeCategories($rows);

            return $rows[$index];
        }

        return null;
    }

    public function deleteCategory(int $id): ?array
    {
        $record = $this->findCategory($id);
        if ($record === null) {
            return null;
        }

        $rows = array_values(array_filter(
            $this->runtimeCategoryRows(false),
            static fn (array $row): bool => (int) ($row['id'] ?? 0) !== $id
        ));
        $this->writeRuntimeCategories($rows);

        return $record;
    }

    public function categoryTree(bool $enabledOnly = true, bool $withStats = false): array
    {
        $rows = $this->loadCategoryRows($enabledOnly);
        $contentCounts = $withStats ? $this->categoryContentCounts() : [];

        return $this->buildTree($rows, $contentCounts);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function normalizeListQuery(array $query): array
    {
        $sortField = (string) ($query['sort_field'] ?? 'manual_sort');
        if (!in_array($sortField, ['manual_sort', 'publish_time', 'id', 'name_zh'], true)) {
            $sortField = 'manual_sort';
        }

        $sortOrder = strtolower((string) ($query['sort_order'] ?? 'desc'));
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }

        $publishStatus = (string) ($query['publish_status'] ?? '');
        if (!in_array($publishStatus, ['draft', 'published', 'offline'], true)) {
            $publishStatus = '';
        }

        $isHomeFeatured = null;
        if (array_key_exists('is_home_featured', $query) && $query['is_home_featured'] !== '' && $query['is_home_featured'] !== null) {
            $isHomeFeatured = !empty($query['is_home_featured']) ? 1 : 0;
        }

        $pdfStatus = null;
        if (array_key_exists('pdf_status', $query) && $query['pdf_status'] !== '' && $query['pdf_status'] !== null) {
            $pdfStatus = !empty($query['pdf_status']) ? 1 : 0;
        }

        return [
            'publish_status' => $publishStatus,
            'category_id' => max(0, (int) ($query['category_id'] ?? 0)),
            'is_home_featured' => $isHomeFeatured,
            'pdf_status' => $pdfStatus,
            'keyword' => trim((string) ($query['keyword'] ?? '')),
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'page_size' => max(1, min(100, (int) ($query['page_size'] ?? 20))),
            'sort_field' => $sortField,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function listFromDatabase(PDO $pdo, array $query): array
    {
        $conditions = [];
        $params = [];
        if ($query['publish_status'] !== '') {
            $conditions[] = 's.publish_status = :publish_status';
            $params['publish_status'] = $query['publish_status'];
        }
        if ((int) $query['category_id'] > 0) {
            $conditions[] = 's.category_id = :category_id';
            $params['category_id'] = (int) $query['category_id'];
        }
        if ($query['is_home_featured'] !== null) {
            $conditions[] = 's.is_home_featured = :is_home_featured';
            $params['is_home_featured'] = (int) $query['is_home_featured'];
        }
        if ($query['pdf_status'] !== null) {
            $conditions[] = (int) $query['pdf_status'] === 1 ? 's.manual_asset_id IS NOT NULL AND s.manual_asset_id > 0' : '(s.manual_asset_id IS NULL OR s.manual_asset_id = 0)';
        }
        if ($query['keyword'] !== '') {
            $conditions[] = '(s.name_zh LIKE :keyword OR s.content_zh LIKE :keyword)';
            $params['keyword'] = '%' . $query['keyword'] . '%';
        }

        $whereSql = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $countStatement = $pdo->prepare('SELECT COUNT(*) FROM solutions s' . $whereSql);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $sortMap = [
            'manual_sort' => 's.manual_sort',
            'publish_time' => 's.publish_time',
            'id' => 's.id',
            'name_zh' => 's.name_zh',
        ];
        $offset = (((int) $query['page']) - 1) * (int) $query['page_size'];
        $statement = $pdo->prepare(
            'SELECT s.id, s.category_id, s.name_zh, s.publish_status, s.translation_status, s.seo_status, s.is_home_featured, s.manual_sort, s.slug, s.publish_time, 0 AS views_count, c.name_zh AS category_name
             FROM solutions s
             LEFT JOIN solution_categories c ON c.id = s.category_id'
            . $whereSql .
            ' ORDER BY ' . $sortMap[$query['sort_field']] . ' ' . strtoupper((string) $query['sort_order']) . ', s.id DESC
              LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', (int) $query['page_size'], PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $items = $statement->fetchAll();

        return [
            'items' => is_array($items) ? $items : [],
            'pagination' => [
                'page' => (int) $query['page'],
                'page_size' => (int) $query['page_size'],
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / max(1, (int) $query['page_size']))),
            ],
            'sort' => [
                'field' => (string) $query['sort_field'],
                'order' => (string) $query['sort_order'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function listFromRuntime(array $query): array
    {
        $categories = [];
        foreach ($this->runtimeCategoryRows(false) as $row) {
            $categories[(int) ($row['id'] ?? 0)] = $row;
        }

        $items = array_values(array_filter($this->runtimeItems(), function (array $item) use ($query): bool {
            if ($query['publish_status'] !== '' && (string) ($item['publish_status'] ?? '') !== $query['publish_status']) {
                return false;
            }
            if ((int) $query['category_id'] > 0 && (int) ($item['category_id'] ?? 0) !== (int) $query['category_id']) {
                return false;
            }
            if ($query['is_home_featured'] !== null && (int) ($item['is_home_featured'] ?? 0) !== (int) $query['is_home_featured']) {
                return false;
            }
            if ($query['pdf_status'] !== null) {
                $hasPdf = (int) ($item['manual_asset_id'] ?? 0) > 0;
                if ((int) $query['pdf_status'] === 1 && !$hasPdf) {
                    return false;
                }
                if ((int) $query['pdf_status'] === 0 && $hasPdf) {
                    return false;
                }
            }
            if ($query['keyword'] !== '') {
                $haystack = strtolower((string) (($item['name_zh'] ?? '') . ' ' . ($item['content_zh'] ?? '')));
                if (!str_contains($haystack, strtolower($query['keyword']))) {
                    return false;
                }
            }

            return true;
        }));

        $sortField = (string) $query['sort_field'];
        $sortOrder = (string) $query['sort_order'];
        usort($items, static function (array $left, array $right) use ($sortField, $sortOrder): int {
            $result = strcmp((string) ($left[$sortField] ?? ''), (string) ($right[$sortField] ?? ''));
            if ($sortField === 'id' || $sortField === 'manual_sort') {
                $result = ((int) ($left[$sortField] ?? 0)) <=> ((int) ($right[$sortField] ?? 0));
            }
            if ($sortOrder === 'desc') {
                $result *= -1;
            }

            return $result !== 0 ? $result : (((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0)));
        });

        $total = count($items);
        $offset = (((int) $query['page']) - 1) * (int) $query['page_size'];
        $pagedItems = array_slice($items, $offset, (int) $query['page_size']);
        $pagedItems = array_map(static function (array $item) use ($categories): array {
            $category = $categories[(int) ($item['category_id'] ?? 0)] ?? null;
            $item['category_name'] = (string) ($category['name_zh'] ?? '');
            $item['views_count'] = (int) ($item['views_count'] ?? 0);
            return $item;
        }, $pagedItems);

        return [
            'items' => array_values($pagedItems),
            'pagination' => [
                'page' => (int) $query['page'],
                'page_size' => (int) $query['page_size'],
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / max(1, (int) $query['page_size']))),
            ],
            'sort' => [
                'field' => (string) $query['sort_field'],
                'order' => (string) $query['sort_order'],
            ],
        ];
    }

    private function loadCategoryRows(bool $enabledOnly): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $conditions = $enabledOnly ? ' WHERE is_enabled = 1' : '';
            $statement = $pdo->query(
                'SELECT id, parent_id, name_zh, slug, sort, is_enabled
                 FROM solution_categories' . $conditions . '
                 ORDER BY sort DESC, id ASC'
            );
            $rows = $statement->fetchAll();
            if (is_array($rows) && $rows !== []) {
                return $rows;
            }
        }

        $rows = $this->runtimeCategoryRows($enabledOnly);
        usort($rows, static function (array $left, array $right): int {
            return ((int) ($right['sort'] ?? 0) <=> (int) ($left['sort'] ?? 0))
                ?: ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });

        return $rows;
    }

    /**
     * @return array<int, int>
     */
    private function categoryContentCounts(): array
    {
        $counts = [];
        foreach ($this->runtimeItems() as $row) {
            $categoryId = (int) ($row['category_id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            $counts[$categoryId] = ($counts[$categoryId] ?? 0) + 1;
        }

        return $counts;
    }

    private function buildTree(array $flat, array $contentCounts = []): array
    {
        $indexed = [];
        foreach ($flat as $item) {
            $item['children'] = [];
            $item['content_count'] = (int) ($contentCounts[(int) ($item['id'] ?? 0)] ?? 0);
            $item['content_total_count'] = $item['content_count'];
            $indexed[(int) $item['id']] = $item;
        }

        $tree = [];
        foreach ($indexed as $id => $item) {
            $parentId = (int) ($item['parent_id'] ?? 0);
            if ($parentId > 0 && isset($indexed[$parentId])) {
                $indexed[$parentId]['children'][] = &$indexed[$id];
                continue;
            }

            $tree[] = &$indexed[$id];
        }

        return array_map([$this, 'appendAggregateCount'], $tree);
    }

    private function appendAggregateCount(array $node): array
    {
        $total = (int) ($node['content_count'] ?? 0);
        $children = [];
        foreach (($node['children'] ?? []) as $child) {
            $childNode = $this->appendAggregateCount($child);
            $total += (int) ($childNode['content_total_count'] ?? 0);
            $children[] = $childNode;
        }

        $node['children'] = $children;
        $node['content_total_count'] = $total;

        return $node;
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage([$this->itemsPath(), $this->categoriesPath()]);
    }

    private function itemsPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/solutions.json';
    }

    private function categoriesPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/solution_categories.json';
    }

    private function runtimeItems(): array
    {
        return $this->readJsonList($this->itemsPath());
    }

    private function writeRuntimeItems(array $items): void
    {
        $this->writeJsonList($this->itemsPath(), $items);
    }

    private function runtimeCategoryRows(bool $enabledOnly): array
    {
        $rows = $this->readJsonList($this->categoriesPath());
        if (!$enabledOnly) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (array $row): bool => !array_key_exists('is_enabled', $row) || !empty($row['is_enabled'])));
    }

    private function writeRuntimeCategories(array $rows): void
    {
        $this->writeJsonList($this->categoriesPath(), $rows);
    }

    private function readJsonList(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    private function writeJsonList(string $path, array $items): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function nextId(array $items): int
    {
        return array_reduce($items, static function (int $carry, array $item): int {
            return max($carry, (int) ($item['id'] ?? 0));
        }, 0) + 1;
    }
}
