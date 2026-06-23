<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class SiteBuildRepository
{
    public function createJob(array $payload, array $items = []): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO site_build_jobs (
                    scope, trigger_source, entity_type, entity_id, language_codes_json, context_json,
                    status, total_steps, completed_steps, progress_percent, current_step, error_message,
                    output_summary_json, created_by, created_at, started_at, finished_at
                ) VALUES (
                    :scope, :trigger_source, :entity_type, :entity_id, :language_codes_json, :context_json,
                    :status, :total_steps, :completed_steps, :progress_percent, :current_step, :error_message,
                    :output_summary_json, :created_by, NOW(), :started_at, :finished_at
                )'
            );
            $statement->execute([
                'scope' => (string) ($payload['scope'] ?? 'incremental'),
                'trigger_source' => (string) ($payload['trigger_source'] ?? 'manual'),
                'entity_type' => $payload['entity_type'] ?? null,
                'entity_id' => (int) ($payload['entity_id'] ?? 0),
                'language_codes_json' => $this->encodeJson($payload['language_codes'] ?? []),
                'context_json' => $this->encodeJson($payload['context'] ?? []),
                'status' => (string) ($payload['status'] ?? 'queued'),
                'total_steps' => (int) ($payload['total_steps'] ?? 0),
                'completed_steps' => (int) ($payload['completed_steps'] ?? 0),
                'progress_percent' => (int) ($payload['progress_percent'] ?? 0),
                'current_step' => (string) ($payload['current_step'] ?? 'queued'),
                'error_message' => $payload['error_message'] ?? null,
                'output_summary_json' => $this->encodeJson($payload['output_summary'] ?? []),
                'created_by' => (string) ($payload['created_by'] ?? 'system'),
                'started_at' => $payload['started_at'] ?? null,
                'finished_at' => $payload['finished_at'] ?? null,
            ]);

            $jobId = (int) $pdo->lastInsertId();
            if ($items !== []) {
                $this->replaceJobItems($jobId, $items);
            }

            return $this->findJob($jobId) ?? [];
        }

        $jobs = $this->readRuntimeJobs();
        $itemsStore = $this->readRuntimeItems();
        $jobId = $this->nextId($jobs);
        $now = date('Y-m-d H:i:s');
        $job = [
            'id' => $jobId,
            'scope' => (string) ($payload['scope'] ?? 'incremental'),
            'trigger_source' => (string) ($payload['trigger_source'] ?? 'manual'),
            'entity_type' => $payload['entity_type'] ?? null,
            'entity_id' => (int) ($payload['entity_id'] ?? 0),
            'language_codes_json' => $payload['language_codes'] ?? [],
            'context_json' => $payload['context'] ?? [],
            'status' => (string) ($payload['status'] ?? 'queued'),
            'total_steps' => (int) ($payload['total_steps'] ?? 0),
            'completed_steps' => (int) ($payload['completed_steps'] ?? 0),
            'progress_percent' => (int) ($payload['progress_percent'] ?? 0),
            'current_step' => (string) ($payload['current_step'] ?? 'queued'),
            'error_message' => $payload['error_message'] ?? null,
            'output_summary_json' => $payload['output_summary'] ?? [],
            'created_by' => (string) ($payload['created_by'] ?? 'system'),
            'created_at' => $now,
            'started_at' => $payload['started_at'] ?? null,
            'finished_at' => $payload['finished_at'] ?? null,
        ];
        $jobs[] = $job;
        $this->writeRuntimeJobs($jobs);

        if ($items !== []) {
            $filtered = array_values(array_filter(
                $itemsStore,
                static fn (array $item): bool => (int) ($item['job_id'] ?? 0) !== $jobId
            ));
            $nextItemId = $this->nextId($filtered);
            foreach ($items as $item) {
                $filtered[] = $this->normalizeRuntimeItem($item, $jobId, $nextItemId++);
            }
            $this->writeRuntimeItems($filtered);
        }

        return $this->findJob($jobId) ?? [];
    }

    public function replaceJobItems(int $jobId, array $items): void
    {
        if ($jobId <= 0) {
            return;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $delete = $pdo->prepare('DELETE FROM site_build_job_items WHERE job_id = :job_id');
            $delete->execute(['job_id' => $jobId]);

            if ($items === []) {
                return;
            }

            $insert = $pdo->prepare(
                'INSERT INTO site_build_job_items (
                    job_id, language_code, page_type, route, output_file, status, error_message, created_at, updated_at
                ) VALUES (
                    :job_id, :language_code, :page_type, :route, :output_file, :status, :error_message, NOW(), NOW()
                )'
            );

            foreach ($items as $item) {
                $insert->execute([
                    'job_id' => $jobId,
                    'language_code' => (string) ($item['language_code'] ?? ''),
                    'page_type' => (string) ($item['page_type'] ?? ''),
                    'route' => (string) ($item['route'] ?? ''),
                    'output_file' => (string) ($item['output_file'] ?? ''),
                    'status' => (string) ($item['status'] ?? 'queued'),
                    'error_message' => $item['error_message'] ?? null,
                ]);
            }

            return;
        }

        $runtimeItems = $this->readRuntimeItems();
        $filtered = array_values(array_filter(
            $runtimeItems,
            static fn (array $item): bool => (int) ($item['job_id'] ?? 0) !== $jobId
        ));
        $nextItemId = $this->nextId($filtered);
        foreach ($items as $item) {
            $filtered[] = $this->normalizeRuntimeItem($item, $jobId, $nextItemId++);
        }
        $this->writeRuntimeItems($filtered);
    }

    public function jobs(int $limit = 100): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'SELECT id, scope, trigger_source, entity_type, entity_id, language_codes_json, context_json,
                        status, total_steps, completed_steps, progress_percent, current_step, error_message,
                        output_summary_json, created_by, created_at, started_at, finished_at
                 FROM site_build_jobs
                 ORDER BY id DESC
                 LIMIT :limit'
            );
            $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
            $statement->execute();
            $rows = $statement->fetchAll();

            return array_map(fn (array $row): array => $this->normalizeJobRow($row), is_array($rows) ? $rows : []);
        }

        $jobs = $this->readRuntimeJobs();
        usort($jobs, static fn (array $left, array $right): int => ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0)));

        return array_slice(array_map(fn (array $row): array => $this->normalizeJobRow($row), $jobs), 0, max(1, $limit));
    }

    public function findJob(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'SELECT id, scope, trigger_source, entity_type, entity_id, language_codes_json, context_json,
                        status, total_steps, completed_steps, progress_percent, current_step, error_message,
                        output_summary_json, created_by, created_at, started_at, finished_at
                 FROM site_build_jobs
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $id]);
            $row = $statement->fetch();

            return is_array($row) ? $this->normalizeJobRow($row) : null;
        }

        foreach ($this->readRuntimeJobs() as $job) {
            if ((int) ($job['id'] ?? 0) === $id) {
                return $this->normalizeJobRow($job);
            }
        }

        return null;
    }

    public function listJobItems(int $jobId): array
    {
        if ($jobId <= 0) {
            return [];
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'SELECT id, job_id, language_code, page_type, route, output_file, status, error_message, created_at, updated_at
                 FROM site_build_job_items
                 WHERE job_id = :job_id
                 ORDER BY id ASC'
            );
            $statement->execute(['job_id' => $jobId]);
            $rows = $statement->fetchAll();

            return array_map(fn (array $row): array => $this->normalizeItemRow($row), is_array($rows) ? $rows : []);
        }

        $items = array_values(array_filter(
            $this->readRuntimeItems(),
            static fn (array $item): bool => (int) ($item['job_id'] ?? 0) === $jobId
        ));
        usort($items, static fn (array $left, array $right): int => ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0)));

        return array_map(fn (array $row): array => $this->normalizeItemRow($row), $items);
    }

    public function updateJob(int $id, array $payload): ?array
    {
        $existing = $this->findJob($id);
        if ($existing === null) {
            return null;
        }

        $next = array_merge($existing, $payload);
        $next['language_codes_json'] = $payload['language_codes'] ?? $existing['language_codes'] ?? [];
        $next['context_json'] = $payload['context'] ?? $existing['context'] ?? [];
        $next['output_summary_json'] = $payload['output_summary'] ?? $existing['output_summary'] ?? [];

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'UPDATE site_build_jobs
                 SET scope = :scope,
                     trigger_source = :trigger_source,
                     entity_type = :entity_type,
                     entity_id = :entity_id,
                     language_codes_json = :language_codes_json,
                     context_json = :context_json,
                     status = :status,
                     total_steps = :total_steps,
                     completed_steps = :completed_steps,
                     progress_percent = :progress_percent,
                     current_step = :current_step,
                     error_message = :error_message,
                     output_summary_json = :output_summary_json,
                     created_by = :created_by,
                     started_at = :started_at,
                     finished_at = :finished_at
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'scope' => (string) ($next['scope'] ?? 'incremental'),
                'trigger_source' => (string) ($next['trigger_source'] ?? 'manual'),
                'entity_type' => $next['entity_type'] ?? null,
                'entity_id' => (int) ($next['entity_id'] ?? 0),
                'language_codes_json' => $this->encodeJson($next['language_codes'] ?? []),
                'context_json' => $this->encodeJson($next['context'] ?? []),
                'status' => (string) ($next['status'] ?? 'queued'),
                'total_steps' => (int) ($next['total_steps'] ?? 0),
                'completed_steps' => (int) ($next['completed_steps'] ?? 0),
                'progress_percent' => (int) ($next['progress_percent'] ?? 0),
                'current_step' => (string) ($next['current_step'] ?? 'queued'),
                'error_message' => $next['error_message'] ?? null,
                'output_summary_json' => $this->encodeJson($next['output_summary'] ?? []),
                'created_by' => (string) ($next['created_by'] ?? 'system'),
                'started_at' => $next['started_at'] ?? null,
                'finished_at' => $next['finished_at'] ?? null,
            ]);

            return $this->findJob($id);
        }

        $jobs = $this->readRuntimeJobs();
        foreach ($jobs as $index => $job) {
            if ((int) ($job['id'] ?? 0) !== $id) {
                continue;
            }

            $jobs[$index] = [
                'id' => $id,
                'scope' => (string) ($next['scope'] ?? 'incremental'),
                'trigger_source' => (string) ($next['trigger_source'] ?? 'manual'),
                'entity_type' => $next['entity_type'] ?? null,
                'entity_id' => (int) ($next['entity_id'] ?? 0),
                'language_codes_json' => $next['language_codes'] ?? [],
                'context_json' => $next['context'] ?? [],
                'status' => (string) ($next['status'] ?? 'queued'),
                'total_steps' => (int) ($next['total_steps'] ?? 0),
                'completed_steps' => (int) ($next['completed_steps'] ?? 0),
                'progress_percent' => (int) ($next['progress_percent'] ?? 0),
                'current_step' => (string) ($next['current_step'] ?? 'queued'),
                'error_message' => $next['error_message'] ?? null,
                'output_summary_json' => $next['output_summary'] ?? [],
                'created_by' => (string) ($next['created_by'] ?? 'system'),
                'created_at' => (string) ($job['created_at'] ?? date('Y-m-d H:i:s')),
                'started_at' => $next['started_at'] ?? null,
                'finished_at' => $next['finished_at'] ?? null,
            ];
            $this->writeRuntimeJobs($jobs);

            return $this->findJob($id);
        }

        return null;
    }

    public function updateJobItem(int $itemId, array $payload): ?array
    {
        if ($itemId <= 0) {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $existing = $this->findJobItem($itemId);
            if ($existing === null) {
                return null;
            }
            $next = array_merge($existing, $payload);
            $statement = $pdo->prepare(
                'UPDATE site_build_job_items
                 SET language_code = :language_code,
                     page_type = :page_type,
                     route = :route,
                     output_file = :output_file,
                     status = :status,
                     error_message = :error_message,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $itemId,
                'language_code' => (string) ($next['language_code'] ?? ''),
                'page_type' => (string) ($next['page_type'] ?? ''),
                'route' => (string) ($next['route'] ?? ''),
                'output_file' => (string) ($next['output_file'] ?? ''),
                'status' => (string) ($next['status'] ?? 'queued'),
                'error_message' => $next['error_message'] ?? null,
            ]);

            return $this->findJobItem($itemId);
        }

        $items = $this->readRuntimeItems();
        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $itemId) {
                continue;
            }

            $items[$index] = array_merge($item, $payload, [
                'id' => $itemId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->writeRuntimeItems($items);

            return $this->normalizeItemRow($items[$index]);
        }

        return null;
    }

    public function currentJob(): ?array
    {
        foreach ($this->jobs(20) as $job) {
            if ((string) ($job['status'] ?? '') === 'running') {
                return $job;
            }
        }

        foreach ($this->jobs(20) as $job) {
            if ((string) ($job['status'] ?? '') === 'queued') {
                return $job;
            }
        }

        return null;
    }

    public function summary(): array
    {
        $items = $this->jobs(200);
        $summary = [
            'queued' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? '');
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
        }

        return $summary;
    }

    private function findJobItem(int $itemId): ?array
    {
        if ($itemId <= 0) {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'SELECT id, job_id, language_code, page_type, route, output_file, status, error_message, created_at, updated_at
                 FROM site_build_job_items
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $itemId]);
            $row = $statement->fetch();

            return is_array($row) ? $this->normalizeItemRow($row) : null;
        }

        foreach ($this->readRuntimeItems() as $item) {
            if ((int) ($item['id'] ?? 0) === $itemId) {
                return $this->normalizeItemRow($item);
            }
        }

        return null;
    }

    private function normalizeJobRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'scope' => (string) ($row['scope'] ?? 'incremental'),
            'trigger_source' => (string) ($row['trigger_source'] ?? 'manual'),
            'entity_type' => $row['entity_type'] ?? null,
            'entity_id' => (int) ($row['entity_id'] ?? 0),
            'language_codes' => $this->decodeJsonField($row['language_codes_json'] ?? []),
            'context' => $this->decodeJsonField($row['context_json'] ?? []),
            'status' => (string) ($row['status'] ?? 'queued'),
            'total_steps' => (int) ($row['total_steps'] ?? 0),
            'completed_steps' => (int) ($row['completed_steps'] ?? 0),
            'progress_percent' => (int) ($row['progress_percent'] ?? 0),
            'current_step' => (string) ($row['current_step'] ?? 'queued'),
            'error_message' => $row['error_message'] ?? null,
            'output_summary' => $this->decodeJsonField($row['output_summary_json'] ?? []),
            'created_by' => (string) ($row['created_by'] ?? 'system'),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'started_at' => $row['started_at'] ?? null,
            'finished_at' => $row['finished_at'] ?? null,
        ];
    }

    private function normalizeItemRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'job_id' => (int) ($row['job_id'] ?? 0),
            'language_code' => (string) ($row['language_code'] ?? ''),
            'page_type' => (string) ($row['page_type'] ?? ''),
            'route' => (string) ($row['route'] ?? ''),
            'output_file' => (string) ($row['output_file'] ?? ''),
            'status' => (string) ($row['status'] ?? 'queued'),
            'error_message' => $row['error_message'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private function normalizeRuntimeItem(array $item, int $jobId, int $itemId): array
    {
        $now = date('Y-m-d H:i:s');

        return [
            'id' => $itemId,
            'job_id' => $jobId,
            'language_code' => (string) ($item['language_code'] ?? ''),
            'page_type' => (string) ($item['page_type'] ?? ''),
            'route' => (string) ($item['route'] ?? ''),
            'output_file' => (string) ($item['output_file'] ?? ''),
            'status' => (string) ($item['status'] ?? 'queued'),
            'error_message' => $item['error_message'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function preferRuntimeStorage(): bool
    {
        return (string) env('SITE_BUILD_PREFER_RUNTIME_STORAGE', '0') === '1'
            || (string) env('PREFER_RUNTIME_STORAGE', '0') === '1';
    }

    private function jobsPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/site_build_jobs.json';
    }

    private function itemsPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/site_build_job_items.json';
    }

    private function readRuntimeJobs(): array
    {
        return $this->readJsonFile($this->jobsPath());
    }

    private function writeRuntimeJobs(array $items): void
    {
        $this->writeJsonFile($this->jobsPath(), $items);
    }

    private function readRuntimeItems(): array
    {
        return $this->readJsonFile($this->itemsPath());
    }

    private function writeRuntimeItems(array $items): void
    {
        $this->writeJsonFile($this->itemsPath(), $items);
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

    private function nextId(array $items): int
    {
        return array_reduce($items, static function (int $carry, array $item): int {
            return max($carry, (int) ($item['id'] ?? 0));
        }, 0) + 1;
    }

    private function encodeJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
