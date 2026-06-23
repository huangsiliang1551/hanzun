<?php

/**
 * Canonical fallback URLs for development:
 * - Product: http://127.0.0.1:8080/en/products/cake-depositor
 * - Solution: http://127.0.0.1:8080/en/solutions/cake-line
 */

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class SeoRepository
{
    public function countByStatuses(array $statuses): int
    {
        if ($statuses === []) {
            return 0;
        }

        $total = 0;
        foreach ($this->jobs() as $item) {
            if (in_array((string) ($item['status'] ?? ''), $statuses, true)) {
                $total++;
            }
        }

        return $total;
    }

    public function jobs(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->query(
                'SELECT id, entity_type, entity_id, language_code, status, retry_count, error_message, created_at, updated_at
                 FROM seo_generation_jobs
                 ORDER BY updated_at DESC, id DESC'
            );
            $rows = $statement->fetchAll();

            return is_array($rows) ? $rows : [];
        }

        return $this->readRuntimeJobs();
    }

    public function findJob(int $id): ?array
    {
        foreach ($this->jobs() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    public function findJobByEntity(string $entityType, int $entityId, string $languageCode): ?array
    {
        foreach ($this->jobs() as $item) {
            if ((string) ($item['entity_type'] ?? '') === $entityType
                && (int) ($item['entity_id'] ?? 0) === $entityId
                && (string) ($item['language_code'] ?? '') === $languageCode) {
                return $item;
            }
        }

        return null;
    }

    public function upsertJob(
        string $entityType,
        int $entityId,
        string $languageCode,
        string $status = 'pending',
        ?string $errorMessage = null
    ): array {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO seo_generation_jobs (entity_type, entity_id, language_code, status, retry_count, error_message, created_at, updated_at)
                 VALUES (:entity_type, :entity_id, :language_code, :status, 0, :error_message, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE status = VALUES(status), error_message = VALUES(error_message), updated_at = NOW()'
            );
            $statement->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'language_code' => $languageCode,
                'status' => $status,
                'error_message' => $errorMessage,
            ]);

            return $this->findJobByEntity($entityType, $entityId, $languageCode) ?? [];
        }

        $items = $this->readRuntimeJobs();
        $index = $this->findJobIndex($items, $entityType, $entityId, $languageCode);
        $now = date('Y-m-d H:i:s');
        $payload = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'language_code' => $languageCode,
            'status' => $status,
            'retry_count' => 0,
            'error_message' => $errorMessage,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($index !== null) {
            $payload = array_merge($items[$index], $payload, [
                'id' => (int) ($items[$index]['id'] ?? 0),
                'created_at' => (string) ($items[$index]['created_at'] ?? $now),
            ]);
            $items[$index] = $payload;
        } else {
            $payload['id'] = $this->nextId($items);
            $items[] = $payload;
        }

        $this->writeRuntimeJobs($items);

        return $payload;
    }

    public function updateJobStatus(int $id, string $status, ?string $errorMessage = null, bool $incrementRetry = false): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $retrySql = $incrementRetry ? ', retry_count = retry_count + 1' : '';
            $statement = $pdo->prepare(
                'UPDATE seo_generation_jobs
                 SET status = :status, error_message = :error_message' . $retrySql . ', updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'status' => $status,
                'error_message' => $errorMessage,
            ]);

            return $this->findJob($id);
        }

        $items = $this->readRuntimeJobs();
        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $id) {
                continue;
            }

            $items[$index]['status'] = $status;
            $items[$index]['error_message'] = $errorMessage;
            if ($incrementRetry) {
                $items[$index]['retry_count'] = (int) ($items[$index]['retry_count'] ?? 0) + 1;
            }
            $items[$index]['updated_at'] = date('Y-m-d H:i:s');
            $this->writeRuntimeJobs($items);

            return $items[$index];
        }

        return null;
    }

    public function routes(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->query(
                'SELECT id, entity_type, entity_id, language_code, route_path, slug, seo_title, seo_keywords, seo_description, canonical_url, index_status, last_generated_at
                 FROM seo_routes
                 ORDER BY id DESC'
            );
            $rows = $statement->fetchAll();

            return is_array($rows) ? $rows : [];
        }

        return $this->readRuntimeRoutes();
    }

    public function countRoutes(): int
    {
        return count($this->routes());
    }

    public function count404Logs(): int
    {
        $count = 0;
        foreach ($this->fourOhFourLogs() as $item) {
            if (in_array((string) ($item['fix_status'] ?? 'pending'), ['pending', 'processing'], true)) {
                $count++;
            }
        }

        return $count;
    }

    public function fourOhFourLogs(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage() && $this->hasTable($pdo, 'seo_404_logs')) {
            $hasRequestPath = $this->hasColumn($pdo, 'seo_404_logs', 'request_path');
            $hasPath = $this->hasColumn($pdo, 'seo_404_logs', 'path');
            $hasHitCount = $this->hasColumn($pdo, 'seo_404_logs', 'hit_count');
            $hasFixStatus = $this->hasColumn($pdo, 'seo_404_logs', 'fix_status');
            $hasSuggestedRoute = $this->hasColumn($pdo, 'seo_404_logs', 'suggested_route');
            $hasNote = $this->hasColumn($pdo, 'seo_404_logs', 'note');
            $hasFirstSeenAt = $this->hasColumn($pdo, 'seo_404_logs', 'first_seen_at');
            $hasLastSeenAt = $this->hasColumn($pdo, 'seo_404_logs', 'last_seen_at');
            $hasResolvedAt = $this->hasColumn($pdo, 'seo_404_logs', 'resolved_at');
            $hasReferrer = $this->hasColumn($pdo, 'seo_404_logs', 'referrer');
            $hasCountryCode = $this->hasColumn($pdo, 'seo_404_logs', 'country_code');
            $hasLanguageCode = $this->hasColumn($pdo, 'seo_404_logs', 'language_code');
            $hasCreatedAt = $this->hasColumn($pdo, 'seo_404_logs', 'created_at');
            $hasUserAgent = $this->hasColumn($pdo, 'seo_404_logs', 'user_agent');
            $orderBy = $this->hasColumn($pdo, 'seo_404_logs', 'last_seen_at')
                ? 'last_seen_at DESC, id DESC'
                : ($this->hasColumn($pdo, 'seo_404_logs', 'created_at') ? 'created_at DESC, id DESC' : 'id DESC');
            $requestPathColumn = $hasRequestPath ? 'request_path' : ($hasPath ? 'path' : "''");
            $pathColumn = $hasPath ? 'path' : $requestPathColumn;
            $selectFields = [
                'id',
                "{$requestPathColumn} AS request_path",
                "{$pathColumn} AS path",
                ($hasReferrer ? 'referrer' : "''") . ' AS referrer',
                ($hasHitCount ? 'hit_count' : '0') . ' AS hit_count',
                ($hasFixStatus ? 'fix_status' : "'pending'") . ' AS fix_status',
                ($hasSuggestedRoute ? 'suggested_route' : "''") . ' AS suggested_route',
                ($hasNote ? 'note' : "''") . ' AS note',
                ($hasFirstSeenAt ? 'first_seen_at' : "''") . ' AS first_seen_at',
                ($hasLastSeenAt ? 'last_seen_at' : "''") . ' AS last_seen_at',
                ($hasResolvedAt ? 'resolved_at' : 'NULL') . ' AS resolved_at',
                ($hasCountryCode ? 'country_code' : "''") . ' AS country_code',
                ($hasLanguageCode ? 'language_code' : "''") . ' AS language_code',
                ($hasCreatedAt ? 'created_at' : "''") . ' AS created_at',
                ($hasUserAgent ? 'user_agent' : "''") . ' AS user_agent',
            ];

            $statement = $pdo->query(
                "SELECT " . implode(', ', $selectFields) . "
                 FROM seo_404_logs
                 ORDER BY {$orderBy}"
            );
            $rows = $statement->fetchAll();

            return array_map(fn (array $item): array => $this->normalize404Log($item), is_array($rows) ? $rows : []);
        }

        return $this->readRuntime404Logs();
    }

    public function update404Log(int $id, array $payload): ?array
    {
        $existing = null;
        foreach ($this->fourOhFourLogs() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $existing = $item;
                break;
            }
        }

        if ($existing === null) {
            return null;
        }

        $merged = $this->normalize404Log(array_merge($existing, $payload, ['id' => $id]));
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage() && $this->hasTable($pdo, 'seo_404_logs')) {
            $setParts = [];
            $params = ['id' => $id];
            if ($this->hasColumn($pdo, 'seo_404_logs', 'fix_status')) {
                $setParts[] = 'fix_status = :fix_status';
                $params['fix_status'] = $merged['fix_status'];
            }
            if ($this->hasColumn($pdo, 'seo_404_logs', 'suggested_route')) {
                $setParts[] = 'suggested_route = :suggested_route';
                $params['suggested_route'] = $merged['suggested_route'];
            }
            if ($this->hasColumn($pdo, 'seo_404_logs', 'note')) {
                $setParts[] = 'note = :note';
                $params['note'] = $merged['note'];
            }
            if ($this->hasColumn($pdo, 'seo_404_logs', 'resolved_at')) {
                $setParts[] = 'resolved_at = :resolved_at';
                $params['resolved_at'] = $merged['resolved_at'];
            }

            if ($setParts !== []) {
                $statement = $pdo->prepare(
                    'UPDATE seo_404_logs
                     SET ' . implode(', ', $setParts) . '
                     WHERE id = :id'
                );
                $statement->execute($params);
            }

            foreach ($this->fourOhFourLogs() as $item) {
                if ((int) ($item['id'] ?? 0) === $id) {
                    return $item;
                }
            }

            return null;
        }

        $items = $this->readRuntime404Logs();
        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $id) {
                continue;
            }

            $items[$index] = $merged;
            $this->writeRuntime404Logs($items);

            return $items[$index];
        }

        return null;
    }

    public function findRoute(string $entityType, int $entityId, string $languageCode): ?array
    {
        foreach ($this->routes() as $item) {
            if ((string) ($item['entity_type'] ?? '') === $entityType
                && (int) ($item['entity_id'] ?? 0) === $entityId
                && (string) ($item['language_code'] ?? '') === $languageCode) {
                return $item;
            }
        }

        return null;
    }

    public function findRouteById(int $id): ?array
    {
        foreach ($this->routes() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    public function upsertRoute(
        string $entityType,
        int $entityId,
        string $languageCode,
        string $routePath,
        string $slug,
        string $seoTitle,
        string $seoKeywords,
        string $seoDescription,
        ?string $canonicalUrl,
        string $indexStatus
    ): array {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO seo_routes (entity_type, entity_id, language_code, route_path, slug, seo_title, seo_keywords, seo_description, canonical_url, index_status, last_generated_at)
                 VALUES (:entity_type, :entity_id, :language_code, :route_path, :slug, :seo_title, :seo_keywords, :seo_description, :canonical_url, :index_status, NOW())
                 ON DUPLICATE KEY UPDATE route_path = VALUES(route_path), slug = VALUES(slug), seo_title = VALUES(seo_title), seo_keywords = VALUES(seo_keywords), seo_description = VALUES(seo_description), canonical_url = VALUES(canonical_url), index_status = VALUES(index_status), last_generated_at = NOW()'
            );
            $statement->execute([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'language_code' => $languageCode,
                'route_path' => $routePath,
                'slug' => $slug,
                'seo_title' => $seoTitle,
                'seo_keywords' => $seoKeywords,
                'seo_description' => $seoDescription,
                'canonical_url' => $canonicalUrl,
                'index_status' => $indexStatus,
            ]);

            return $this->findRoute($entityType, $entityId, $languageCode) ?? [];
        }

        $items = $this->readRuntimeRoutes();
        $index = $this->findRouteIndex($items, $entityType, $entityId, $languageCode);
        $payload = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'language_code' => $languageCode,
            'route_path' => $routePath,
            'slug' => $slug,
            'seo_title' => $seoTitle,
            'seo_keywords' => $seoKeywords,
            'seo_description' => $seoDescription,
            'canonical_url' => $canonicalUrl,
            'index_status' => $indexStatus,
            'last_generated_at' => date('Y-m-d H:i:s'),
        ];

        if ($index !== null) {
            $payload = array_merge($items[$index], $payload, [
                'id' => (int) ($items[$index]['id'] ?? 0),
            ]);
            $items[$index] = $payload;
        } else {
            $payload['id'] = $this->nextId($items);
            $items[] = $payload;
        }

        $this->writeRuntimeRoutes($items);

        return $payload;
    }

    public function updateRouteById(int $id, array $payload): ?array
    {
        $existing = $this->findRouteById($id);
        if ($existing === null) {
            return null;
        }

        $merged = array_merge($existing, $payload, ['id' => $id]);
        $items = $this->readRuntimeRoutes();
        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $id) {
                continue;
            }

            $items[$index] = $merged;
            $this->writeRuntimeRoutes($items);

            return $merged;
        }

        return null;
    }

    private function preferRuntimeStorage(): bool
    {
        return (string) env('PREFER_RUNTIME_STORAGE', '0') === '1'
            || (PHP_SAPI === 'cli' && (is_file($this->jobsPath()) || is_file($this->routesPath()) || is_file($this->logs404Path())));
    }

    private function jobsPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/seo_jobs.json';
    }

    private function routesPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/seo_routes.json';
    }

    private function logs404Path(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/seo_404_logs.json';
    }

    private function readRuntimeJobs(): array
    {
        $decoded = $this->readJsonFile($this->jobsPath());
        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = [
                'id' => (int) ($item['id'] ?? 0),
                'entity_type' => (string) ($item['entity_type'] ?? ''),
                'entity_id' => (int) ($item['entity_id'] ?? 0),
                'language_code' => (string) ($item['language_code'] ?? ''),
                'status' => (string) ($item['status'] ?? 'pending'),
                'retry_count' => (int) ($item['retry_count'] ?? 0),
                'error_message' => $item['error_message'] ?? null,
                'created_at' => (string) ($item['created_at'] ?? ''),
                'updated_at' => (string) ($item['updated_at'] ?? ''),
            ];
        }

        return $items;
    }

    private function writeRuntimeJobs(array $items): void
    {
        $this->writeJsonFile($this->jobsPath(), $items);
    }

    private function readRuntimeRoutes(): array
    {
        $decoded = $this->readJsonFile($this->routesPath());
        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = [
                'id' => (int) ($item['id'] ?? 0),
                'entity_type' => (string) ($item['entity_type'] ?? ''),
                'entity_id' => (int) ($item['entity_id'] ?? 0),
                'language_code' => (string) ($item['language_code'] ?? ''),
                'route_path' => (string) ($item['route_path'] ?? ''),
                'slug' => (string) ($item['slug'] ?? ''),
                'seo_title' => (string) ($item['seo_title'] ?? ''),
                'seo_keywords' => (string) ($item['seo_keywords'] ?? ''),
                'seo_description' => (string) ($item['seo_description'] ?? ''),
                'canonical_url' => $item['canonical_url'] ?? null,
                'index_status' => (string) ($item['index_status'] ?? 'index'),
                'last_generated_at' => (string) ($item['last_generated_at'] ?? ''),
            ];
        }

        usort($items, static fn (array $left, array $right): int => ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0)));

        return $items;
    }

    private function writeRuntimeRoutes(array $items): void
    {
        $this->writeJsonFile($this->routesPath(), $items);
    }

    private function readRuntime404Logs(): array
    {
        $decoded = $this->readJsonFile($this->logs404Path());
        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = $this->normalize404Log($item);
        }

        usort($items, static function (array $left, array $right): int {
            $lastSeenCompare = strcmp((string) ($right['last_seen_at'] ?? ''), (string) ($left['last_seen_at'] ?? ''));
            if ($lastSeenCompare !== 0) {
                return $lastSeenCompare;
            }

            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        return $items;
    }

    private function writeRuntime404Logs(array $items): void
    {
        $this->writeJsonFile($this->logs404Path(), $items);
    }

    private function readJsonFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeJsonFile(string $path, array $items): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function normalize404Log(array $item): array
    {
        $requestPath = (string) ($item['request_path'] ?? '');
        $referer = (string) ($item['referrer'] ?? '');

        return [
            'id' => (int) ($item['id'] ?? 0),
            'request_path' => $requestPath,
            'path' => $requestPath,
            'referrer' => $referer,
            'referer' => $referer,
            'hit_count' => (int) ($item['hit_count'] ?? 0),
            'fix_status' => (string) ($item['fix_status'] ?? 'pending'),
            'suggested_route' => (string) ($item['suggested_route'] ?? ''),
            'note' => (string) ($item['note'] ?? ''),
            'first_seen_at' => (string) ($item['first_seen_at'] ?? ''),
            'last_seen_at' => (string) ($item['last_seen_at'] ?? ''),
            'resolved_at' => $item['resolved_at'] ?? null,
        ];
    }

    private function hasTable(PDO $pdo, string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $statement = $pdo->prepare('SHOW TABLES LIKE :table');
        $statement->execute(['table' => $table]);

        return $cache[$table] = (bool) $statement->fetch();
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        static $tableCache = [];
        $cacheKey = $table . ':' . $column;

        if (array_key_exists($cacheKey, $tableCache)) {
            return $tableCache[$cacheKey];
        }

        $statement = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE :column');
        $statement->execute(['column' => $column]);

        return $tableCache[$cacheKey] = (bool) $statement->fetch();
    }

    private function findJobIndex(array $items, string $entityType, int $entityId, string $languageCode): ?int
    {
        foreach ($items as $index => $item) {
            if ((string) ($item['entity_type'] ?? '') === $entityType
                && (int) ($item['entity_id'] ?? 0) === $entityId
                && (string) ($item['language_code'] ?? '') === $languageCode) {
                return $index;
            }
        }

        return null;
    }

    private function findRouteIndex(array $items, string $entityType, int $entityId, string $languageCode): ?int
    {
        foreach ($items as $index => $item) {
            if ((string) ($item['entity_type'] ?? '') === $entityType
                && (int) ($item['entity_id'] ?? 0) === $entityId
                && (string) ($item['language_code'] ?? '') === $languageCode) {
                return $index;
            }
        }

        return null;
    }

    private function nextId(array $items): int
    {
        return array_reduce($items, static function (int $carry, array $item): int {
            return max($carry, (int) ($item['id'] ?? 0));
        }, 0) + 1;
    }
}
