<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class TranslationRepository
{
    /** @var array<string, bool> */
    private array $columnCache = [];

    public function list(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            if ($this->hasColumn($pdo, 'translation_jobs', 'source_content_hash')) {
                $rows = $pdo->query(
                    'SELECT id, entity_type, entity_id, status, source_content_hash,
                            total_languages, completed_languages, failed_languages,
                            language_details, error_summary, created_at, updated_at
                     FROM translation_jobs ORDER BY updated_at DESC, id DESC'
                )->fetchAll() ?: [];
            } else {
                $rows = $pdo->query(
                    'SELECT id, entity_type, entity_id, language_code, status, retry_count,
                            error_message, created_at, updated_at
                     FROM translation_jobs ORDER BY updated_at DESC, id DESC'
                )->fetchAll() ?: [];
            }
            return array_map([$this, 'decodeDetails'], $rows);
        }
        return $this->readRuntimeItems();
    }

    public function find(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $st = $pdo->prepare('SELECT * FROM translation_jobs WHERE id = :id LIMIT 1');
            $st->execute(['id' => $id]);
            $row = $st->fetch();
            return is_array($row) ? $this->decodeDetails($row) : null;
        }
        foreach ($this->list() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) return $item;
        }
        return null;
    }

    /** @deprecated — use findByEntity which returns single row */
    public function findByEntityAll(string $entityType, int $entityId): array
    {
        $row = $this->findByEntity($entityType, $entityId);
        return $row !== null ? [$row] : [];
    }

    /** @deprecated — per-language lookup not needed with JSON column */
    public function findByEntity(string $entityType, int $entityId, string $languageCode): ?array
    {
        return $this->findEntityJob($entityType, $entityId);
    }

    /**
     * Find the single job row for this entity.
     */
    public function findEntityJob(string $entityType, int $entityId): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $st = $pdo->prepare('SELECT * FROM translation_jobs WHERE entity_type = :et AND entity_id = :eid LIMIT 1');
            $st->execute(['et' => $entityType, 'eid' => $entityId]);
            $row = $st->fetch();
            return is_array($row) ? $this->decodeDetails($row) : null;
        }
        foreach ($this->list() as $item) {
            if ((string)($item['entity_type'] ?? '') === $entityType && (int)($item['entity_id'] ?? 0) === $entityId)
                return $item;
        }
        return null;
    }

    /**
     * Upsert a single-row job for this entity. Called once per save (not per language).
     * If content hash is unchanged, skips (existing job preserved).
     *
     * @param array<int,string> $languageCodes
     */
    public function upsertEntityJob(string $entityType, int $entityId, array $languageCodes, string $sourceContentHash, string $status = 'pending'): void
    {
        $total = count($languageCodes);
        if ($total === 0) return;

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            if (!$this->hasColumn($pdo, 'translation_jobs', 'source_content_hash')) {
                $pdo->prepare('DELETE FROM translation_jobs WHERE entity_type = :et AND entity_id = :eid')
                    ->execute(['et' => $entityType, 'eid' => $entityId]);
                $statement = $pdo->prepare(
                    'INSERT INTO translation_jobs (entity_type, entity_id, language_code, status, retry_count, error_message, created_at, updated_at)
                     VALUES (:et, :eid, :lc, :st, 0, NULL, NOW(), NOW())'
                );
                foreach ($languageCodes as $languageCode) {
                    $statement->execute([
                        'et' => $entityType,
                        'eid' => $entityId,
                        'lc' => strtolower(trim((string) $languageCode)),
                        'st' => $status,
                    ]);
                }
                return;
            }

            // Check if unchanged
            $st = $pdo->prepare('SELECT id, source_content_hash FROM translation_jobs WHERE entity_type = :et AND entity_id = :eid LIMIT 1');
            $st->execute(['et' => $entityType, 'eid' => $entityId]);
            $existing = $st->fetch();
            if ($existing && $sourceContentHash !== '' && $existing['source_content_hash'] === $sourceContentHash) {
                return; // content unchanged, skip
            }

            $langDetails = json_encode($this->initLanguageDetails($languageCodes, $status), JSON_UNESCAPED_UNICODE);

            if ($existing) {
                $pdo->prepare(
                    'UPDATE translation_jobs SET status = :st, source_content_hash = :h, total_languages = :tot,
                     completed_languages = 0, failed_languages = 0, language_details = :ld, error_summary = NULL,
                     updated_at = NOW() WHERE id = :id'
                )->execute(['st' => $status, 'h' => $sourceContentHash, 'tot' => $total, 'ld' => $langDetails, 'id' => $existing['id']]);
            } else {
                $pdo->prepare(
                    'INSERT INTO translation_jobs (entity_type, entity_id, status, source_content_hash, total_languages, completed_languages, failed_languages, language_details, created_at, updated_at)
                     VALUES (:et, :eid, :st, :h, :tot, 0, 0, :ld, NOW(), NOW())'
                )->execute(['et' => $entityType, 'eid' => $entityId, 'st' => $status, 'h' => $sourceContentHash, 'tot' => $total, 'ld' => $langDetails]);
            }
            return;
        }

        // Runtime fallback
        $items = $this->readRuntimeItems();
        $found = false;
        foreach ($items as &$item) {
            if ((string)($item['entity_type'] ?? '') === $entityType && (int)($item['entity_id'] ?? 0) === $entityId) {
                if ($sourceContentHash !== '' && ($item['source_content_hash'] ?? '') === $sourceContentHash) return;
                $item['status'] = $status;
                $item['source_content_hash'] = $sourceContentHash;
                $item['total_languages'] = $total;
                $item['completed_languages'] = 0;
                $item['failed_languages'] = 0;
                $item['language_details'] = $this->initLanguageDetails($languageCodes, $status);
                $item['updated_at'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        if (!$found) {
            $items[] = [
                'id' => $this->nextRuntimeId($items), 'entity_type' => $entityType, 'entity_id' => $entityId,
                'status' => $status, 'source_content_hash' => $sourceContentHash,
                'total_languages' => $total, 'completed_languages' => 0, 'failed_languages' => 0,
                'language_details' => $this->initLanguageDetails($languageCodes, $status),
                'error_summary' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ];
        }
        $this->writeRuntimeItems($items);
    }

    /** @deprecated — kept for backward compat, redirects to upsertEntityJob */
    public function upsertJob(string $entityType, int $entityId, string $languageCode, string $status = 'pending', ?string $errorMessage = null): array
    {
        $this->upsertEntityJob($entityType, $entityId, [$languageCode], '', $status);
        return [];
    }

    /** @deprecated — kept for backward compat */
    public function batchUpsertJobs(string $entityType, int $entityId, array $languageCodes, string $status = 'pending'): void
    {
        $this->upsertEntityJob($entityType, $entityId, $languageCodes, '', $status);
    }

    /**
     * Update per-language status within the JSON column and recompute counters.
     */
    public function updateLanguageStatus(int $jobId, string $languageCode, string $langStatus, ?string $errorMsg = null): void
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            if (!$this->hasColumn($pdo, 'translation_jobs', 'source_content_hash')) {
                $pdo->prepare(
                    'UPDATE translation_jobs
                     SET status = :st, error_message = :err, updated_at = NOW()
                     WHERE id = :id'
                )->execute([
                    'st' => $langStatus,
                    'err' => $errorMsg,
                    'id' => $jobId,
                ]);
                return;
            }

            $job = $this->find($jobId);
            if ($job === null) return;
            $details = $job['language_details'] ?? [];
            if (!is_array($details)) $details = [];
            $details[$languageCode] = ['status' => $langStatus, 'error' => $errorMsg];
            $completed = count(array_filter($details, fn($d) => ($d['status'] ?? '') === 'completed'));
            $failed = count(array_filter($details, fn($d) => ($d['status'] ?? '') === 'failed'));

            $pdo->prepare(
                'UPDATE translation_jobs SET language_details = :ld, completed_languages = :c, failed_languages = :f,
                 updated_at = NOW() WHERE id = :id'
            )->execute(['ld' => json_encode($details, JSON_UNESCAPED_UNICODE), 'c' => $completed, 'f' => $failed, 'id' => $jobId]);
            return;
        }
    }

    /**
     * Set overall job status (after all languages processed).
     */
    public function updateEntityStatus(int $jobId, string $status, ?string $errorSummary = null): void
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            if ($this->hasColumn($pdo, 'translation_jobs', 'source_content_hash')) {
                $pdo->prepare(
                    'UPDATE translation_jobs SET status = :st, error_summary = :es, updated_at = NOW() WHERE id = :id'
                )->execute(['st' => $status, 'es' => $errorSummary, 'id' => $jobId]);
            } else {
                $pdo->prepare(
                    'UPDATE translation_jobs SET status = :st, error_message = :es, updated_at = NOW() WHERE id = :id'
                )->execute(['st' => $status, 'es' => $errorSummary, 'id' => $jobId]);
            }
            return;
        }
    }

    /** @deprecated — use updateEntityStatus + updateLanguageStatus */
    public function updateStatus(int $id, string $status, ?string $errorMessage = null, bool $incrementRetry = false): ?array
    {
        $this->updateEntityStatus($id, $status, $errorMessage);
        return $this->find($id);
    }

    public function deleteByEntity(string $entityType, int $entityId): void
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $pdo->prepare('DELETE FROM translation_jobs WHERE entity_type = :et AND entity_id = :eid')
                ->execute(['et' => $entityType, 'eid' => $entityId]);
            return;
        }
        $items = array_filter($this->readRuntimeItems(), fn($i) => !((string)($i['entity_type']??'')===$entityType && (int)($i['entity_id']??0)===$entityId));
        $this->writeRuntimeItems(array_values($items));
    }

    public function deleteByLanguage(string $languageCode): void
    {
        $lc = strtolower(trim($languageCode));
        if ($lc === '') return;
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            if ($this->hasColumn($pdo, 'translation_jobs', 'source_content_hash')) {
                // Remove language from JSON in all rows
                $pdo->prepare(
                    'UPDATE translation_jobs SET language_details = JSON_REMOVE(language_details, :key),
                     total_languages = GREATEST(total_languages - 1, 0) WHERE JSON_CONTAINS_PATH(language_details, \'one\', :key2)'
                )->execute(['key' => '$.'.$lc, 'key2' => '$.'.$lc]);
            } else {
                $pdo->prepare('DELETE FROM translation_jobs WHERE language_code = :lc')->execute(['lc' => $lc]);
            }
            return;
        }
    }

    public function countByLanguage(): array
    {
        $result = [];
        foreach ($this->list() as $row) {
            if (isset($row['language_code'])) {
                $code = strtolower(trim((string) ($row['language_code'] ?? '')));
                if ($code === '') continue;
                $status = (string) ($row['status'] ?? 'pending');
                if (!isset($result[$code])) {
                    $result[$code] = ['completed' => 0, 'pending' => 0, 'failed' => 0, 'total' => 0];
                }
                $result[$code][$status] = ($result[$code][$status] ?? 0) + 1;
                $result[$code]['total']++;
                continue;
            }
            $details = $row['language_details'] ?? [];
            if (!is_array($details)) continue;
            foreach ($details as $code => $info) {
                $status = $info['status'] ?? 'pending';
                if (!isset($result[$code])) $result[$code] = ['completed'=>0,'pending'=>0,'failed'=>0,'total'=>0];
                $result[$code][$status] = ($result[$code][$status] ?? 0) + 1;
                $result[$code]['total']++;
            }
        }
        return $result;
    }

    public function countByStatuses(array $statuses): int
    {
        if ($statuses === []) return 0;
        $total = 0;
        foreach ($this->list() as $item) {
            if (in_array((string)($item['status'] ?? ''), $statuses, true)) $total++;
        }
        return $total;
    }

    // ── Helpers ──

    private function initLanguageDetails(array $codes, string $status): array
    {
        $d = [];
        foreach ($codes as $c) $d[$c] = ['status' => $status, 'error' => null];
        return $d;
    }

    private function decodeDetails(array $row): array
    {
        if (!isset($row['language_details']) && isset($row['language_code'])) {
            $languageCode = strtolower(trim((string) ($row['language_code'] ?? '')));
            $status = (string) ($row['status'] ?? 'pending');
            $errorMessage = $row['error_message'] ?? null;
            $row['source_content_hash'] = '';
            $row['total_languages'] = 1;
            $row['completed_languages'] = $status === 'completed' ? 1 : 0;
            $row['failed_languages'] = $status === 'failed' ? 1 : 0;
            $row['error_summary'] = $errorMessage;
            $row['language_details'] = $languageCode !== ''
                ? [$languageCode => ['status' => $status, 'error' => $errorMessage]]
                : [];
            return $row;
        }

        if (isset($row['language_details']) && is_string($row['language_details'])) {
            $decoded = json_decode($row['language_details'], true);
            $row['language_details'] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        $statement = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE :column');
        $statement->execute(['column' => $column]);
        $this->columnCache[$cacheKey] = (bool) $statement->fetch();

        return $this->columnCache[$cacheKey];
    }

    // ── Runtime storage (unchanged) ──

    private function preferRuntimeStorage(): bool
    {
        return (string) env('PREFER_RUNTIME_STORAGE', '0') === '1' || (PHP_SAPI === 'cli' && is_file($this->storagePath()));
    }
    private function storagePath(): string { return dirname(__DIR__, 2) . '/runtime/storage/translation_jobs.json'; }
    private function readRuntimeItems(): array { /* ... simplified ... */
        $path = $this->storagePath(); if (!is_file($path)) return [];
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
    private function writeRuntimeItems(array $items): void {
        $dir = dirname($this->storagePath()); if (!is_dir($dir)) mkdir($dir, 0777, true);
        file_put_contents($this->storagePath(), json_encode(array_values($items), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
    }
    private function nextRuntimeId(array $items): int { return array_reduce($items, fn($c,$i)=>max($c,(int)($i['id']??0)),0)+1; }
}
