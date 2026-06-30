<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class SiteBuildRepository
{
    private ?bool $runtimeStorageUnavailable = null;

    public function createJob(array $payload, array $items = []): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $startedTransaction = false;
            try {
                if (!$pdo->inTransaction()) {
                    $pdo->beginTransaction();
                    $startedTransaction = true;
                }

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

                if ($startedTransaction && $pdo->inTransaction()) {
                    $pdo->commit();
                }
            } catch (\Throwable $exception) {
                if ($startedTransaction && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }

            return isset($jobId) ? ($this->findJob($jobId) ?? []) : [];
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

    public function claimNextQueuedJobItem(int $jobId): ?array
    {
        if ($jobId <= 0) {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $startedTransaction = false;

                try {
                    if (!$pdo->inTransaction()) {
                        $pdo->beginTransaction();
                        $startedTransaction = true;
                    }

                    $statement = $pdo->prepare(
                        'SELECT id, job_id, language_code, page_type, route, output_file, status, error_message, created_at, updated_at
                         FROM site_build_job_items
                         WHERE job_id = :job_id
                           AND status = :status
                         ORDER BY id ASC
                         LIMIT 1
                         FOR UPDATE'
                    );
                    $statement->execute([
                        'job_id' => $jobId,
                        'status' => 'queued',
                    ]);
                    $row = $statement->fetch();

                    if (!is_array($row)) {
                        if ($startedTransaction && $pdo->inTransaction()) {
                            $pdo->commit();
                        }

                        return null;
                    }

                    $update = $pdo->prepare(
                        'UPDATE site_build_job_items
                         SET status = :next_status,
                             error_message = NULL,
                             updated_at = NOW()
                         WHERE id = :id
                           AND status = :expected_status'
                    );
                    $update->execute([
                        'next_status' => 'running',
                        'id' => (int) ($row['id'] ?? 0),
                        'expected_status' => 'queued',
                    ]);

                    if ($update->rowCount() !== 1) {
                        if ($startedTransaction && $pdo->inTransaction()) {
                            $pdo->commit();
                        }
                        continue;
                    }

                    if ($startedTransaction && $pdo->inTransaction()) {
                        $pdo->commit();
                    }

                    $row['status'] = 'running';
                    $row['error_message'] = null;

                    return $this->normalizeItemRow($row);
                } catch (\Throwable $exception) {
                    if ($startedTransaction && $pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }

            return null;
        }

        $items = $this->readRuntimeItems();
        foreach ($items as $index => $item) {
            if ((int) ($item['job_id'] ?? 0) !== $jobId || (string) ($item['status'] ?? '') !== 'queued') {
                continue;
            }

            $items[$index]['status'] = 'running';
            $items[$index]['error_message'] = null;
            $items[$index]['updated_at'] = date('Y-m-d H:i:s');
            $this->writeRuntimeItems($items);

            return $this->normalizeItemRow($items[$index]);
        }

        return null;
    }

    public function jobItemCounts(int $jobId): array
    {
        if ($jobId <= 0) {
            return [
                'queued' => 0,
                'running' => 0,
                'completed' => 0,
                'failed' => 0,
                'processed' => 0,
                'total' => 0,
            ];
        }

        $counts = [
            'queued' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'processed' => 0,
            'total' => 0,
        ];

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'SELECT status, COUNT(*) AS count_value
                 FROM site_build_job_items
                 WHERE job_id = :job_id
                 GROUP BY status'
            );
            $statement->execute(['job_id' => $jobId]);
            foreach ($statement->fetchAll() ?: [] as $row) {
                $status = (string) ($row['status'] ?? '');
                $countValue = (int) ($row['count_value'] ?? 0);
                if (isset($counts[$status])) {
                    $counts[$status] = $countValue;
                }
                $counts['total'] += $countValue;
            }
            $counts['processed'] = $counts['completed'] + $counts['failed'];

            return $counts;
        }

        foreach ($this->readRuntimeItems() as $item) {
            if ((int) ($item['job_id'] ?? 0) !== $jobId) {
                continue;
            }

            $status = (string) ($item['status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            $counts['total']++;
        }
        $counts['processed'] = $counts['completed'] + $counts['failed'];

        return $counts;
    }

    public function resetJobItems(int $jobId): void
    {
        if ($jobId <= 0) {
            return;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'UPDATE site_build_job_items
                 SET status = :status,
                     error_message = NULL,
                     updated_at = NOW()
                 WHERE job_id = :job_id'
            );
            $statement->execute([
                'job_id' => $jobId,
                'status' => 'queued',
            ]);
            return;
        }

        $items = $this->readRuntimeItems();
        foreach ($items as $index => $item) {
            if ((int) ($item['job_id'] ?? 0) !== $jobId) {
                continue;
            }

            $items[$index]['status'] = 'queued';
            $items[$index]['error_message'] = null;
            $items[$index]['updated_at'] = date('Y-m-d H:i:s');
        }
        $this->writeRuntimeItems($items);
    }

    public function updateJob(int $id, array $payload): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $assignments = [];
            $params = ['id' => $id];
            $fieldMap = [
                'scope' => ['column' => 'scope', 'transform' => static fn (mixed $value): string => (string) $value],
                'trigger_source' => ['column' => 'trigger_source', 'transform' => static fn (mixed $value): string => (string) $value],
                'entity_type' => ['column' => 'entity_type', 'transform' => static fn (mixed $value): mixed => $value],
                'entity_id' => ['column' => 'entity_id', 'transform' => static fn (mixed $value): int => (int) $value],
                'language_codes' => ['column' => 'language_codes_json', 'transform' => fn (mixed $value): string => $this->encodeJson($value ?? [])],
                'context' => ['column' => 'context_json', 'transform' => fn (mixed $value): string => $this->encodeJson($value ?? [])],
                'status' => ['column' => 'status', 'transform' => static fn (mixed $value): string => (string) $value],
                'total_steps' => ['column' => 'total_steps', 'transform' => static fn (mixed $value): int => (int) $value],
                'completed_steps' => ['column' => 'completed_steps', 'transform' => static fn (mixed $value): int => (int) $value],
                'progress_percent' => ['column' => 'progress_percent', 'transform' => static fn (mixed $value): int => (int) $value],
                'current_step' => ['column' => 'current_step', 'transform' => static fn (mixed $value): string => (string) $value],
                'error_message' => ['column' => 'error_message', 'transform' => static fn (mixed $value): mixed => $value],
                'output_summary' => ['column' => 'output_summary_json', 'transform' => fn (mixed $value): string => $this->encodeJson($value ?? [])],
                'created_by' => ['column' => 'created_by', 'transform' => static fn (mixed $value): string => (string) $value],
                'started_at' => ['column' => 'started_at', 'transform' => static fn (mixed $value): mixed => $value],
                'finished_at' => ['column' => 'finished_at', 'transform' => static fn (mixed $value): mixed => $value],
            ];

            foreach ($fieldMap as $key => $config) {
                if (!array_key_exists($key, $payload)) {
                    continue;
                }

                $param = 'p_' . $key;
                $assignments[] = $config['column'] . ' = :' . $param;
                $params[$param] = $config['transform']($payload[$key]);
            }

            if ($assignments === []) {
                return $this->findJob($id);
            }

            $statement = $pdo->prepare(
                'UPDATE site_build_jobs
                 SET ' . implode(', ', $assignments) . '
                 WHERE id = :id'
            );
            $statement->execute($params);

            return $this->findJob($id);
        }

        $existing = $this->findJob($id);
        if ($existing === null) {
            return null;
        }

        $next = array_merge($existing, $payload);
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
            $assignments = [];
            $params = ['id' => $itemId];
            $fieldMap = [
                'language_code' => static fn (mixed $value): string => (string) $value,
                'page_type' => static fn (mixed $value): string => (string) $value,
                'route' => static fn (mixed $value): string => (string) $value,
                'output_file' => static fn (mixed $value): string => (string) $value,
                'status' => static fn (mixed $value): string => (string) $value,
                'error_message' => static fn (mixed $value): mixed => $value,
            ];

            foreach ($fieldMap as $key => $transform) {
                if (!array_key_exists($key, $payload)) {
                    continue;
                }

                $param = 'p_' . $key;
                $assignments[] = $key . ' = :' . $param;
                $params[$param] = $transform($payload[$key]);
            }

            if ($assignments === []) {
                return $this->findJobItem($itemId);
            }

            $statement = $pdo->prepare(
                'UPDATE site_build_job_items
                 SET ' . implode(', ', $assignments) . ',
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute($params);

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
            || (string) env('PREFER_RUNTIME_STORAGE', '0') === '1'
            || !$this->hasSiteBuildTables();
    }

    private function hasSiteBuildTables(): bool
    {
        if ($this->runtimeStorageUnavailable === null) {
            $this->runtimeStorageUnavailable = $this->detectMissingTables();
        }

        return !$this->runtimeStorageUnavailable;
    }

    private function detectMissingTables(): bool
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return true;
        }

        try {
            $jobsStatement = $pdo->query('SHOW TABLES LIKE \'site_build_jobs\'');
            $itemsStatement = $pdo->query('SHOW TABLES LIKE \'site_build_job_items\'');

            return !(
                is_object($jobsStatement) && is_string((string) $jobsStatement->fetchColumn())
                && is_object($itemsStatement) && is_string((string) $itemsStatement->fetchColumn())
            );
        } catch (\Throwable) {
            return true;
        }
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
