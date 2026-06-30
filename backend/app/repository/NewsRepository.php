<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class NewsRepository
{
    private const TABLE = 'news';
    private const CATEGORY_TABLE = 'news_categories';
    private const CATEGORY_TRANSLATION_TABLE = 'news_category_translations';

    public function list(array $query = []): array
    {
        $normalized = $this->normalizeListQuery($query);
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            return $this->listFromDatabase($pdo, $normalized);
        }

        return [];
    }

    public function find(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $row = $statement->fetch();

            return is_array($row) ? $row : null;
        }

        return null;
    }

    public function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare('SELECT * FROM ' . self::TABLE . ' WHERE slug = :slug LIMIT 1');
            $statement->execute(['slug' => $slug]);
            $row = $statement->fetch();

            return is_array($row) ? $row : null;
        }

        return null;
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $slug = trim($slug);
        if ($slug === '') {
            return false;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT id
                 FROM ' . self::TABLE . '
                 WHERE slug = :slug AND id <> :exclude_id
                 LIMIT 1'
            );
            $statement->execute([
                'slug' => $slug,
                'exclude_id' => $excludeId,
            ]);

            return (bool) $statement->fetchColumn();
        }

        return false;
    }

    public function create(array $payload): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO ' . self::TABLE . ' (category_id, title_zh, summary_zh, content_zh, publish_status, translation_status, seo_status, is_home_featured, manual_sort, slug, seo_title, seo_keywords, seo_description, publish_time, created_by, updated_by, created_at, updated_at)
                 VALUES (:category_id, :title_zh, :summary_zh, :content_zh, :publish_status, :translation_status, :seo_status, :is_home_featured, :manual_sort, :slug, :seo_title, :seo_keywords, :seo_description, :publish_time, :created_by, :updated_by, NOW(), NOW())'
            );
            $statement->execute([
                'category_id' => $payload['category_id'],
                'title_zh' => $payload['title_zh'],
                'summary_zh' => $payload['summary_zh'],
                'content_zh' => $payload['content_zh'],
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

        return $payload;
    }

    public function update(int $id, array $payload): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'UPDATE ' . self::TABLE . '
                 SET category_id = :category_id, title_zh = :title_zh, summary_zh = :summary_zh, content_zh = :content_zh, publish_status = :publish_status, translation_status = :translation_status, seo_status = :seo_status, is_home_featured = :is_home_featured, manual_sort = :manual_sort, slug = :slug, seo_title = :seo_title, seo_keywords = :seo_keywords, seo_description = :seo_description, publish_time = :publish_time, updated_by = :updated_by, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'category_id' => $payload['category_id'],
                'title_zh' => $payload['title_zh'],
                'summary_zh' => $payload['summary_zh'],
                'content_zh' => $payload['content_zh'],
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

        return null;
    }

    public function updatePublishStatus(int $id, string $publishStatus, ?string $publishTime, ?int $updatedBy): ?array
    {
        $record = $this->find($id);
        if ($record === null) {
            return null;
        }

        $record['publish_status'] = $publishStatus;
        $record['publish_time'] = $publishTime;
        $record['updated_by'] = $updatedBy;

        return $this->update($id, $record);
    }

    public function delete(int $id): ?array
    {
        $record = $this->find($id);
        if ($record === null) {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
            $statement->execute(['id' => $id]);

            return $record;
        }

        return $record;
    }

    public function findCategory(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare('SELECT id, parent_id, name_zh, sort, is_enabled FROM ' . self::CATEGORY_TABLE . ' WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $row = $statement->fetch();

            return is_array($row) ? $row : null;
        }

        return null;
    }

    public function createCategory(array $payload): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO ' . self::CATEGORY_TABLE . ' (parent_id, name_zh, sort, is_enabled)
                 VALUES (:parent_id, :name_zh, :sort, :is_enabled)'
            );
            $statement->execute([
                'parent_id' => $payload['parent_id'],
                'name_zh' => $payload['name_zh'],
                'sort' => $payload['sort'],
                'is_enabled' => $payload['is_enabled'],
            ]);

            return $this->findCategory((int) $pdo->lastInsertId()) ?? $payload;
        }

        return $payload;
    }

    public function updateCategory(int $id, array $payload): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'UPDATE ' . self::CATEGORY_TABLE . '
                 SET parent_id = :parent_id, name_zh = :name_zh, sort = :sort, is_enabled = :is_enabled
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'parent_id' => $payload['parent_id'],
                'name_zh' => $payload['name_zh'],
                'sort' => $payload['sort'],
                'is_enabled' => $payload['is_enabled'],
            ]);

            return $this->findCategory($id);
        }

        return null;
    }

    public function deleteCategory(int $id): ?array
    {
        $record = $this->findCategory($id);
        if ($record === null) {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare('DELETE FROM ' . self::CATEGORY_TABLE . ' WHERE id = :id');
            $statement->execute(['id' => $id]);

            return $record;
        }

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
        if (!in_array($sortField, ['manual_sort', 'publish_time', 'id', 'title_zh'], true)) {
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

        return [
            'publish_status' => $publishStatus,
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
            $conditions[] = 'n.publish_status = :publish_status';
            $params['publish_status'] = $query['publish_status'];
        }
        if ((int) $query['category_id'] > 0) {
            $conditions[] = 'n.category_id = :category_id';
            $params['category_id'] = (int) $query['category_id'];
        }
        if ($query['is_home_featured'] !== null) {
            $conditions[] = 'n.is_home_featured = :is_home_featured';
            $params['is_home_featured'] = (int) $query['is_home_featured'];
        }
        if ($query['keyword'] !== '') {
            $conditions[] = 'n.title_zh LIKE :keyword';
            $params['keyword'] = '%' . $query['keyword'] . '%';
        }

        $whereSql = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
        $countStatement = $pdo->prepare('SELECT COUNT(*) FROM ' . self::TABLE . ' n' . $whereSql);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $sortMap = [
            'manual_sort' => 'n.manual_sort',
            'publish_time' => 'n.publish_time',
            'id' => 'n.id',
            'title_zh' => 'n.title_zh',
            'views_count' => 'n.views_count',
        ];
        $offset = (((int) $query['page']) - 1) * (int) $query['page_size'];
        $statement = $pdo->prepare(
            'SELECT n.id, n.category_id, n.slug, n.title_zh, n.publish_status, n.translation_status, n.seo_status, n.is_home_featured, n.manual_sort, n.publish_time, n.views_count, c.name_zh AS category_name
             FROM ' . self::TABLE . ' n
             LEFT JOIN ' . self::CATEGORY_TABLE . ' c ON c.id = n.category_id'
            . $whereSql .
            ' ORDER BY ' . $sortMap[$query['sort_field']] . ' ' . strtoupper((string) $query['sort_order']) . ', n.id DESC
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

    private function loadCategoryRows(bool $enabledOnly): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $sql = 'SELECT id, parent_id, name_zh, sort, is_enabled FROM ' . self::CATEGORY_TABLE;
            if ($enabledOnly) {
                $sql .= ' WHERE is_enabled = 1';
            }
            $sql .= ' ORDER BY sort DESC, id ASC';
            $statement = $pdo->query($sql);
            $rows = $statement->fetchAll();

            return is_array($rows) ? $rows : [];
        }

        return [];
    }

    /**
     * @return array<int, int>
     */
    private function categoryContentCounts(): array
    {
        $counts = [];
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->query(
                'SELECT category_id, COUNT(*) AS aggregate_count
                 FROM ' . self::TABLE . '
                 GROUP BY category_id'
            );
            $rows = $statement->fetchAll();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $counts[(int) ($row['category_id'] ?? 0)] = (int) ($row['aggregate_count'] ?? 0);
                }
            }

            return $counts;
        }

        return [];
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
}
