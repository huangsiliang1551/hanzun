<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class SeoRepository
{
    // ── new single-row job API ──

    public function findEntityJob(string $entityType, int $entityId): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $st = $pdo->prepare('SELECT * FROM seo_generation_jobs WHERE entity_type = :et AND entity_id = :eid LIMIT 1');
            $st->execute(['et' => $entityType, 'eid' => $entityId]);
            $row = $st->fetch();
            return is_array($row) ? $this->decodeDetails($row) : null;
        }
        foreach ($this->jobs() as $j) {
            if ((string)($j['entity_type']??'')===$entityType && (int)($j['entity_id']??0)===$entityId) return $j;
        }
        return null;
    }

    public function upsertEntityJob(string $entityType, int $entityId, array $languageCodes, string $sourceContentHash, string $status = 'pending'): void
    {
        $total = count($languageCodes);
        if ($total === 0) return;

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $st = $pdo->prepare('SELECT id, source_content_hash FROM seo_generation_jobs WHERE entity_type = :et AND entity_id = :eid LIMIT 1');
            $st->execute(['et' => $entityType, 'eid' => $entityId]);
            $existing = $st->fetch();
            if ($existing && $sourceContentHash !== '' && $existing['source_content_hash'] === $sourceContentHash) return;

            $details = [];
            foreach ($languageCodes as $c) $details[$c] = ['status' => $status, 'error' => null];
            $ld = json_encode($details, JSON_UNESCAPED_UNICODE);

            if ($existing) {
                $pdo->prepare(
                    'UPDATE seo_generation_jobs SET status=:st, source_content_hash=:h, total_languages=:tot,
                     completed_languages=0, failed_languages=0, language_details=:ld, error_summary=NULL,
                     updated_at=NOW() WHERE id=:id'
                )->execute(['st'=>$status,'h'=>$sourceContentHash,'tot'=>$total,'ld'=>$ld,'id'=>$existing['id']]);
            } else {
                $pdo->prepare(
                    'INSERT INTO seo_generation_jobs (entity_type,entity_id,status,source_content_hash,total_languages,completed_languages,failed_languages,language_details,created_at,updated_at)
                     VALUES (:et,:eid,:st,:h,:tot,0,0,:ld,NOW(),NOW())'
                )->execute(['et'=>$entityType,'eid'=>$entityId,'st'=>$status,'h'=>$sourceContentHash,'tot'=>$total,'ld'=>$ld]);
            }
            return;
        }
    }

    public function updateLanguageStatus(int $jobId, string $languageCode, string $langStatus, ?string $errorMsg = null): void
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $st = $pdo->prepare('SELECT language_details FROM seo_generation_jobs WHERE id = :id LIMIT 1');
            $st->execute(['id' => $jobId]);
            $raw = $st->fetchColumn();
            $details = $raw ? json_decode($raw, true) : [];
            if (!is_array($details)) $details = [];
            $details[$languageCode] = ['status' => $langStatus, 'error' => $errorMsg];
            $completed = $this->countCompletedLanguages($details);
            $failed = count(array_filter($details, fn($d) => ($d['status']??'')==='failed'));
            $pdo->prepare(
                'UPDATE seo_generation_jobs SET language_details=:ld,completed_languages=:c,failed_languages=:f,updated_at=NOW() WHERE id=:id'
            )->execute(['ld'=>json_encode($details,JSON_UNESCAPED_UNICODE),'c'=>$completed,'f'=>$failed,'id'=>$jobId]);
        }
    }

    public function updateEntityStatus(int $jobId, string $status, ?string $errorSummary = null): void
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $pdo->prepare('UPDATE seo_generation_jobs SET status=:st,error_summary=:es,updated_at=NOW() WHERE id=:id')
                ->execute(['st'=>$status,'es'=>$errorSummary,'id'=>$jobId]);
        }
    }

    // ── Route API (unchanged) ──

    public function batchUpsertRoutes(string $entityType, int $entityId, array $languageRoutes, string $slug, string $indexStatus): void
    {
        if ($languageRoutes === []) return;
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $placeholders = []; $params = [];
            foreach ($languageRoutes as $i => $lr) {
                $placeholders[] = "(:et{$i},:eid{$i},:lc{$i},:rp{$i},:sl{$i},:st{$i},:sk{$i},:sd{$i},:cu{$i},:is{$i},NOW())";
                $params["et{$i}"]=$entityType; $params["eid{$i}"]=$entityId;
                $params["lc{$i}"]=$lr['language_code']; $params["rp{$i}"]=$lr['route_path'];
                $params["sl{$i}"]=$slug; $params["st{$i}"]=''; $params["sk{$i}"]=''; $params["sd{$i}"]='';
                $params["cu{$i}"]=$lr['canonical_url']; $params["is{$i}"]=$indexStatus;
            }
            $sql = 'INSERT INTO seo_routes (entity_type,entity_id,language_code,route_path,slug,seo_title,seo_keywords,seo_description,canonical_url,index_status,last_generated_at) VALUES '
                 . implode(',',$placeholders)
                 . ' ON DUPLICATE KEY UPDATE route_path=VALUES(route_path),slug=VALUES(slug),index_status=VALUES(index_status),last_generated_at=NOW()';
            $pdo->prepare($sql)->execute($params);
            return;
        }
    }

    public function upsertRoute(string $entityType, int $entityId, string $languageCode, string $routePath, string $slug, string $seoTitle, string $seoKeywords, string $seoDescription, ?string $canonicalUrl, string $indexStatus): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $pdo->prepare(
                'INSERT INTO seo_routes (entity_type,entity_id,language_code,route_path,slug,seo_title,seo_keywords,seo_description,canonical_url,index_status,last_generated_at)
                 VALUES (:et,:eid,:lc,:rp,:sl,:st,:sk,:sd,:cu,:is,NOW())
                 ON DUPLICATE KEY UPDATE route_path=VALUES(route_path),slug=VALUES(slug),seo_title=VALUES(seo_title),seo_keywords=VALUES(seo_keywords),seo_description=VALUES(seo_description),canonical_url=VALUES(canonical_url),index_status=VALUES(index_status),last_generated_at=NOW()'
            )->execute(['et'=>$entityType,'eid'=>$entityId,'lc'=>$languageCode,'rp'=>$routePath,'sl'=>$slug,'st'=>$seoTitle,'sk'=>$seoKeywords,'sd'=>$seoDescription,'cu'=>$canonicalUrl,'is'=>$indexStatus]);
            return [];
        }
        return [];
    }

    public function routes(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            return $pdo->query('SELECT * FROM seo_routes ORDER BY entity_type, entity_id, language_code')->fetchAll() ?: [];
        }
        return [];
    }

    public function fourOhFourLogs(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            return $pdo->query('SELECT * FROM seo_404_logs ORDER BY created_at DESC LIMIT 100')->fetchAll() ?: [];
        }
        return [];
    }

    public function countByStatuses(array $statuses): int
    {
        if ($statuses === []) return 0;
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $st = $pdo->prepare("SELECT COUNT(*) FROM seo_generation_jobs WHERE status IN ({$placeholders})");
            $st->execute(array_values($statuses));
            return (int) $st->fetchColumn();
        }
        return 0;
    }

    public function countRoutes(): int
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            return (int) $pdo->query('SELECT COUNT(*) FROM seo_routes')->fetchColumn();
        }
        return 0;
    }

    public function count404Logs(): int
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            return (int) $pdo->query('SELECT COUNT(*) FROM seo_404_logs')->fetchColumn();
        }
        return 0;
    }

    public function findRouteById(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $st = $pdo->prepare('SELECT * FROM seo_routes WHERE id = :id LIMIT 1');
            $st->execute(['id' => $id]);
            $row = $st->fetch();
            return is_array($row) ? $row : null;
        }
        return null;
    }

    public function updateRouteById(int $id, array $data): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $sets = []; $params = ['id' => $id];
            foreach (['route_path','slug','seo_title','seo_keywords','seo_description','canonical_url','index_status'] as $f) {
                if (array_key_exists($f, $data)) { $sets[] = "{$f}=:{$f}"; $params[$f] = $data[$f]; }
            }
            if ($sets) {
                $sets[] = 'updated_at=NOW()';
                $pdo->prepare('UPDATE seo_routes SET '.implode(',',$sets).' WHERE id=:id')->execute($params);
            }
            return $this->findRouteById($id);
        }
        return null;
    }

    public function update404Log(int $id, array $data): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $sets = []; $params = ['id' => $id];
            foreach (['resolved','notes'] as $f) {
                if (array_key_exists($f, $data)) { $sets[] = "{$f}=:{$f}"; $params[$f] = $data[$f]; }
            }
            if ($sets) {
                $pdo->prepare('UPDATE seo_404_logs SET '.implode(',',$sets).' WHERE id=:id')->execute($params);
            }
            $st = $pdo->prepare('SELECT * FROM seo_404_logs WHERE id=:id LIMIT 1');
            $st->execute(['id' => $id]);
            $row = $st->fetch();
            return is_array($row) ? $row : null;
        }
        return null;
    }

    // ── Legacy compat wrappers ──

    /** @deprecated */
    public function upsertJob(string $entityType, int $entityId, string $languageCode, string $status='pending', ?string $errorMessage=null): array
    {
        $this->upsertEntityJob($entityType, $entityId, [$languageCode], '', $status);
        return $this->findJobByEntity($entityType, $entityId, $languageCode) ?? [];
    }
    /** @deprecated */
    public function batchUpsertJobs(string $entityType, int $entityId, array $languageCodes, string $status='pending'): void
    {
        $this->upsertEntityJob($entityType, $entityId, $languageCodes, '', $status);
    }
    /** @deprecated */
    public function updateJobStatus(int $id, string $status, ?string $errorMessage=null, bool $incrementRetry=false): ?array
    {
        $this->updateEntityStatus($id, $status, $errorMessage);
        return $this->findJob($id);
    }
    /** @deprecated */
    public function findJob(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $st = $pdo->prepare('SELECT * FROM seo_generation_jobs WHERE id = :id LIMIT 1');
            $st->execute(['id' => $id]);
            $row = $st->fetch();
            return is_array($row) ? $this->decodeDetails($row) : null;
        }
        foreach ($this->jobs() as $job) {
            if ((int) ($job['id'] ?? 0) === $id) {
                return $job;
            }
        }
        return null;
    }
    /** @deprecated */
    public function findJobByEntity(string $entityType, int $entityId, string $languageCode): ?array
    {
        $job = $this->findEntityJob($entityType, $entityId);
        if ($job === null) {
            return null;
        }

        $details = $job['language_details'] ?? [];
        if (!is_array($details)) {
            $details = [];
        }

        $info = $details[$languageCode] ?? ['status' => $job['status'] ?? 'pending', 'error' => null];

        return array_merge($job, [
            'language_code' => $languageCode,
            'status' => (string) ($info['status'] ?? 'pending'),
            'error_message' => $info['error'] ?? null,
            'retry_count' => 0,
        ]);
    }

    public function findRoute(string $entityType, int $entityId, string $languageCode): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $st = $pdo->prepare('SELECT * FROM seo_routes WHERE entity_type=:et AND entity_id=:eid AND language_code=:lc LIMIT 1');
            $st->execute(['et'=>$entityType,'eid'=>$entityId,'lc'=>$languageCode]);
            $row = $st->fetch();
            return is_array($row) ? $row : null;
        }
        return null;
    }

    public function deleteByEntity(string $entityType, int $entityId): void
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $pdo->prepare('DELETE FROM seo_generation_jobs WHERE entity_type=:et AND entity_id=:eid')->execute(['et'=>$entityType,'eid'=>$entityId]);
            $pdo->prepare('DELETE FROM seo_routes WHERE entity_type=:et AND entity_id=:eid')->execute(['et'=>$entityType,'eid'=>$entityId]);
        }
    }

    public function jobs(): array {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $rows = $pdo->query('SELECT * FROM seo_generation_jobs ORDER BY updated_at DESC, id DESC')->fetchAll() ?: [];
            return array_map([$this, 'decodeDetails'], $rows);
        }
        return [];
    }

    // ── Helpers ──

    private function decodeDetails(array $row): array {
        if (isset($row['language_details']) && is_string($row['language_details'])) {
            $d = json_decode($row['language_details'], true);
            $row['language_details'] = is_array($d) ? $d : [];
        }
        return $row;
    }
    private function countCompletedLanguages(array $details): int {
        return count(array_filter(
            $details,
            static fn (array $item): bool => in_array((string) ($item['status'] ?? ''), ['completed', 'generated', 'manual_override'], true)
        ));
    }
    private function preferRuntimeStorage(): bool { return (string)env('PREFER_RUNTIME_STORAGE','0')==='1'; }
}
