<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
require __DIR__ . '/test-bootstrap.php';

use app\repository\SiteBuildRepository;
use app\service\system\SiteBuildService;

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function jobsPath(): string
{
    return dirname(__DIR__) . '/runtime/storage/site_build_jobs.json';
}

function itemsPath(): string
{
    return dirname(__DIR__) . '/runtime/storage/site_build_job_items.json';
}

function restoreFile(string $path, ?string $content): void
{
    if ($content === null) {
        if (is_file($path)) {
            unlink($path);
        }
        return;
    }

    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $content);
}

$scriptPath = dirname(__DIR__) . '/scripts/site_build_job.php';
if (!is_file($scriptPath)) {
    fail('site build worker script missing: ' . $scriptPath);
}

$jobsFile = jobsPath();
$itemsFile = itemsPath();
$jobsBackup = is_file($jobsFile) ? file_get_contents($jobsFile) : null;
$itemsBackup = is_file($itemsFile) ? file_get_contents($itemsFile) : null;

try {
    $staleQueuedAt = date('Y-m-d H:i:s', time() - 7200);
    $payload = [[
        'id' => 990001,
        'scope' => 'full',
        'trigger_source' => 'runtime_stale_queue_test',
        'entity_type' => null,
        'entity_id' => 0,
        'language_codes_json' => ['zh'],
        'context_json' => [],
        'status' => 'queued',
        'total_steps' => 3,
        'completed_steps' => 0,
        'progress_percent' => 0,
        'current_step' => 'collect_pages',
        'error_message' => null,
        'output_summary_json' => [
            'queued_at' => $staleQueuedAt,
        ],
        'created_by' => 'runtime-test',
        'created_at' => $staleQueuedAt,
        'started_at' => null,
        'finished_at' => null,
    ]];
    file_put_contents($jobsFile, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    file_put_contents($itemsFile, json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    putenv('SITE_BUILD_QUEUED_TIMEOUT=60');
    $_ENV['SITE_BUILD_QUEUED_TIMEOUT'] = '60';
    $_SERVER['SITE_BUILD_QUEUED_TIMEOUT'] = '60';
    putenv('SITE_BUILD_PREFER_RUNTIME_STORAGE=1');
    $_ENV['SITE_BUILD_PREFER_RUNTIME_STORAGE'] = '1';
    $_SERVER['SITE_BUILD_PREFER_RUNTIME_STORAGE'] = '1';

    $service = new SiteBuildService();
    $current = $service->current();
    $repository = new SiteBuildRepository();
    $job = $repository->findJob(990001);

    if (!is_array($job)) {
        fail('stale queued job should remain queryable after recovery');
    }

    if ((string) ($job['status'] ?? '') !== 'failed') {
        fail('stale queued job must be marked failed automatically');
    }

    if ((string) ($job['current_step'] ?? '') !== 'failed') {
        fail('stale queued job current_step must become failed');
    }

    $message = (string) ($job['error_message'] ?? '');
    if ($message === '' || !str_contains($message, 'timed out')) {
        fail('stale queued job must record timeout reason');
    }

    if (($current['job'] ?? null) !== null) {
        fail('current() must not expose queued job as currently executing');
    }
} finally {
    putenv('SITE_BUILD_PREFER_RUNTIME_STORAGE');
    unset($_ENV['SITE_BUILD_PREFER_RUNTIME_STORAGE'], $_SERVER['SITE_BUILD_PREFER_RUNTIME_STORAGE']);
    restoreFile($jobsFile, is_string($jobsBackup) ? $jobsBackup : null);
    restoreFile($itemsFile, is_string($itemsBackup) ? $itemsBackup : null);
}

echo "Site build queue runtime validation passed." . PHP_EOL;
