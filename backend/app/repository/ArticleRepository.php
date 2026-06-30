<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class ArticleRepository
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
            $statement = $pdo->prepare('SELECT * FROM articles WHERE id = :id LIMIT 1');
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

    public function findBySlug(string $slug, ?string $contentType = null): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $sql = 'SELECT * FROM articles WHERE slug = :slug';
            $params = ['slug' => $slug];
            if ($contentType !== null && $contentType !== '') {
                $sql .= ' AND content_type = :content_type';
                $params['content_type'] = $contentType;
            }
            $sql .= ' LIMIT 1';
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            $row = $statement->fetch();

            return is_array($row) ? $row : null;
        }

        foreach ($this->runtimeItems() as $item) {
            if ((string) ($item['slug'] ?? '') !== $slug) {
                continue;
            }
            if ($contentType !== null && $contentType !== '' && (string) ($item['content_type'] ?? '') !== $contentType) {
                continue;
            }

            return $item;
        }

        return null;
    }

    public function slugExists(string $slug, int $excludeId = 0, ?string $contentType = null): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        foreach ($this->runtimeItems() as $item) {
            if ((string) ($item['slug'] ?? '') !== $slug || (int) ($item['id'] ?? 0) === $excludeId) {
                continue;
            }
            if ($contentType !== null && $contentType !== '' && (string) ($item['content_type'] ?? '') !== $contentType) {
                continue;
            }

            return true;
        }

        return false;
    }

    public function create(array $payload): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO articles (category_id, content_type, title_zh, summary_zh, content_zh, country_code, case_tags, related_solution_ids, related_product_ids, publish_status, translation_status, seo_status, is_home_featured, manual_sort, slug, seo_title, seo_keywords, seo_description, publish_time, created_by, updated_by, created_at, updated_at)
                 VALUES (:category_id, :content_type, :title_zh, :summary_zh, :content_zh, :country_code, :case_tags, :related_solution_ids, :related_product_ids, :publish_status, :translation_status, :seo_status, :is_home_featured, :manual_sort, :slug, :seo_title, :seo_keywords, :seo_description, :publish_time, :created_by, :updated_by, NOW(), NOW())'
            );
            $statement->execute([
                'category_id' => $payload['category_id'],
                'content_type' => $payload['content_type'],
                'title_zh' => $payload['title_zh'],
                'summary_zh' => $payload['summary_zh'],
                'content_zh' => $payload['content_zh'],
                'country_code' => $payload['country_code'],
                'case_tags' => $payload['case_tags'],
                'related_solution_ids' => $payload['related_solution_ids'],
                'related_product_ids' => $payload['related_product_ids'],
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
                'UPDATE articles
                 SET category_id = :category_id, content_type = :content_type, title_zh = :title_zh, summary_zh = :summary_zh, content_zh = :content_zh, country_code = :country_code, case_tags = :case_tags, related_solution_ids = :related_solution_ids, related_product_ids = :related_product_ids, publish_status = :publish_status, translation_status = :translation_status, seo_status = :seo_status, is_home_featured = :is_home_featured, manual_sort = :manual_sort, slug = :slug, seo_title = :seo_title, seo_keywords = :seo_keywords, seo_description = :seo_description, publish_time = :publish_time, updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'category_id' => $payload['category_id'],
                'content_type' => $payload['content_type'],
                'title_zh' => $payload['title_zh'],
                'summary_zh' => $payload['summary_zh'],
                'content_zh' => $payload['content_zh'],
                'country_code' => $payload['country_code'],
                'case_tags' => $payload['case_tags'],
                'related_solution_ids' => $payload['related_solution_ids'],
                'related_product_ids' => $payload['related_product_ids'],
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
        $article = $this->find($id);
        if ($article === null) {
            return null;
        }

        $article['publish_status'] = $publishStatus;
        $article['publish_time'] = $publishTime;
        $article['updated_by'] = $updatedBy;

        return $this->update($id, $article);
    }

    public function delete(int $id): ?array
    {
        $record = $this->find($id);
        if ($record === null) {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare('DELETE FROM articles WHERE id = :id');
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
        if (!in_array($sortField, ['manual_sort', 'publish_time', 'id', 'title_zh', 'country_code'], true)) {
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

        $contentType = (string) ($query['content_type'] ?? '');
        if (!in_array($contentType, ['news', 'case'], true)) {
            $contentType = '';
        }

        $isHomeFeatured = null;
        if (array_key_exists('is_home_featured', $query) && $query['is_home_featured'] !== '' && $query['is_home_featured'] !== null) {
            $isHomeFeatured = !empty($query['is_home_featured']) ? 1 : 0;
        }

        return [
            'publish_status' => $publishStatus,
            'content_type' => $contentType,
            'country_code' => strtoupper(trim((string) ($query['country_code'] ?? ''))),
            'category_id' => max(0, (int) ($query['category_id'] ?? 0)),
            'is_home_featured' => $isHomeFeatured,
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
            $conditions[] = 'a.publish_status = :publish_status';
            $params['publish_status'] = $query['publish_status'];
        }
        if ($query['content_type'] !== '') {
            $conditions[] = 'a.content_type = :content_type';
            $params['content_type'] = $query['content_type'];
        }
        if ($query['country_code'] !== '') {
            $conditions[] = 'a.country_code = :country_code';
            $params['country_code'] = $query['country_code'];
        }
        if ((int) $query['category_id'] > 0) {
            $conditions[] = 'a.category_id = :category_id';
            $params['category_id'] = (int) $query['category_id'];
        }
        if ($query['is_home_featured'] !== null) {
            $conditions[] = 'a.is_home_featured = :is_home_featured';
            $params['is_home_featured'] = (int) $query['is_home_featured'];
        }
        if ($query['keyword'] !== '') {
            $conditions[] = '(a.title_zh LIKE :keyword OR a.case_tags LIKE :keyword)';
            $params['keyword'] = '%' . $query['keyword'] . '%';
        }

        $whereSql = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $countStatement = $pdo->prepare('SELECT COUNT(*) FROM articles a' . $whereSql);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $sortMap = [
            'manual_sort' => 'a.manual_sort',
            'publish_time' => 'a.publish_time',
            'id' => 'a.id',
            'title_zh' => 'a.title_zh',
            'country_code' => 'a.country_code',
        ];
        $offset = (((int) $query['page']) - 1) * (int) $query['page_size'];
        $statement = $pdo->prepare(
            'SELECT a.id, a.category_id, a.content_type, a.slug, a.title_zh, a.country_code, a.case_tags, a.publish_status, a.translation_status, a.seo_status, a.is_home_featured, a.manual_sort, a.publish_time, a.views_count, c.name_zh AS category_name
             FROM articles a
             LEFT JOIN article_categories c ON c.id = a.category_id'
            . $whereSql .
            ' ORDER BY ' . $sortMap[$query['sort_field']] . ' ' . strtoupper((string) $query['sort_order']) . ', a.id DESC
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
            if ($query['content_type'] !== '' && (string) ($item['content_type'] ?? '') !== $query['content_type']) {
                return false;
            }
            if ($query['country_code'] !== '' && strtoupper((string) ($item['country_code'] ?? '')) !== $query['country_code']) {
                return false;
            }
            if ((int) $query['category_id'] > 0 && (int) ($item['category_id'] ?? 0) !== (int) $query['category_id']) {
                return false;
            }
            if ($query['is_home_featured'] !== null && (int) ($item['is_home_featured'] ?? 0) !== (int) $query['is_home_featured']) {
                return false;
            }
            if ($query['keyword'] !== '') {
                $haystack = strtolower((string) (($item['title_zh'] ?? '') . ' ' . ($item['case_tags'] ?? '')));
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
            $selectFields = $this->hasColumn($pdo, 'article_categories', 'slug')
                ? 'id, parent_id, name_zh, slug, content_type_scope, sort, is_enabled'
                : "id, parent_id, name_zh, '' AS slug, content_type_scope, sort, is_enabled";
            $statement = $pdo->query(
                'SELECT ' . $selectFields . '
                 FROM article_categories' . $conditions . '
                 ORDER BY sort DESC, id ASC'
            );
            $rows = $statement->fetchAll();
            if (is_array($rows) && $rows !== []) {
                return $this->normalizeCategoryRows($rows);
            }
        }

        $rows = $this->runtimeCategoryRows($enabledOnly);
        usort($rows, static function (array $left, array $right): int {
            return ((int) ($right['sort'] ?? 0) <=> (int) ($left['sort'] ?? 0))
                ?: ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });

        return $this->normalizeCategoryRows($rows);
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

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCategoryRows(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $slug = $this->normalizeCategorySlug(
                (string) ($row['slug'] ?? ''),
                (string) ($row['name_zh'] ?? ''),
                (string) ($row['content_type_scope'] ?? 'category'),
                (int) ($row['id'] ?? 0)
            );

            $rows[$index]['slug'] = $slug;
        }

        return $rows;
    }

    private function normalizeCategorySlug(string $slug, string $name, string $scope, int $id): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9-]+/', '-', $normalized) ?? '';
        $normalized = trim($normalized, '-');
        if ($normalized !== '') {
            return $normalized;
        }

        $fallbackFromName = strtolower(trim($name));
        $fallbackFromName = preg_replace('/[^a-z0-9-]+/', '-', $fallbackFromName) ?? '';
        $fallbackFromName = trim($fallbackFromName, '-');
        if ($fallbackFromName !== '') {
            return $fallbackFromName;
        }

        $scope = strtolower(trim($scope));
        if (!in_array($scope, ['news', 'case', 'all'], true)) {
            $scope = 'category';
        }

        return $scope . '-category-' . max(1, $id);
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage([$this->itemsPath(), $this->categoriesPath()]);
    }

    private function itemsPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/articles.json';
    }

    private function categoriesPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/article_categories.json';
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

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $statement = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE :column');
        $statement->execute(['column' => $column]);

        return (bool) $statement->fetch();
    }
}
