<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
require __DIR__ . '/test-bootstrap.php';

use app\repository\SiteBuildRepository;

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
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

$jobsPath = dirname(__DIR__) . '/runtime/storage/site_build_jobs.json';
$itemsPath = dirname(__DIR__) . '/runtime/storage/site_build_job_items.json';
$jobsBackup = is_file($jobsPath) ? file_get_contents($jobsPath) : null;
$itemsBackup = is_file($itemsPath) ? file_get_contents($itemsPath) : null;

$oldPreferRuntime = getenv('PREFER_RUNTIME_STORAGE');
$oldSiteBuildPreferRuntime = getenv('SITE_BUILD_PREFER_RUNTIME_STORAGE');

putenv('PREFER_RUNTIME_STORAGE=0');
$_ENV['PREFER_RUNTIME_STORAGE'] = '0';
$_SERVER['PREFER_RUNTIME_STORAGE'] = '0';
putenv('SITE_BUILD_PREFER_RUNTIME_STORAGE=0');
$_ENV['SITE_BUILD_PREFER_RUNTIME_STORAGE'] = '0';
$_SERVER['SITE_BUILD_PREFER_RUNTIME_STORAGE'] = '0';

try {
    if (!is_dir(dirname($jobsPath))) {
        mkdir(dirname($jobsPath), 0777, true);
    }

    file_put_contents($jobsPath, json_encode([[
        'id' => 990091,
        'scope' => 'full',
        'trigger_source' => 'runtime-shadow-job',
        'status' => 'queued',
        'total_steps' => 1,
        'completed_steps' => 0,
        'progress_percent' => 0,
        'current_step' => 'collect_pages',
        'output_summary_json' => ['queued_at' => date('Y-m-d H:i:s')],
        'created_at' => date('Y-m-d H:i:s'),
    ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    file_put_contents($itemsPath, json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    $repository = new SiteBuildRepository();
    $reflection = new ReflectionClass($repository);
    $method = $reflection->getMethod('preferRuntimeStorage');
    $method->setAccessible(true);

    $preferRuntimeStorage = (bool) $method->invoke($repository);
    if ($preferRuntimeStorage) {
        fail('site build repository must not switch to runtime storage in CLI only because runtime json files exist');
    }
} finally {
    if ($oldPreferRuntime === false) {
        putenv('PREFER_RUNTIME_STORAGE');
        unset($_ENV['PREFER_RUNTIME_STORAGE'], $_SERVER['PREFER_RUNTIME_STORAGE']);
    } else {
        putenv('PREFER_RUNTIME_STORAGE=' . $oldPreferRuntime);
        $_ENV['PREFER_RUNTIME_STORAGE'] = (string) $oldPreferRuntime;
        $_SERVER['PREFER_RUNTIME_STORAGE'] = (string) $oldPreferRuntime;
    }

    if ($oldSiteBuildPreferRuntime === false) {
        putenv('SITE_BUILD_PREFER_RUNTIME_STORAGE');
        unset($_ENV['SITE_BUILD_PREFER_RUNTIME_STORAGE'], $_SERVER['SITE_BUILD_PREFER_RUNTIME_STORAGE']);
    } else {
        putenv('SITE_BUILD_PREFER_RUNTIME_STORAGE=' . $oldSiteBuildPreferRuntime);
        $_ENV['SITE_BUILD_PREFER_RUNTIME_STORAGE'] = (string) $oldSiteBuildPreferRuntime;
        $_SERVER['SITE_BUILD_PREFER_RUNTIME_STORAGE'] = (string) $oldSiteBuildPreferRuntime;
    }

    restoreFile($jobsPath, is_string($jobsBackup) ? $jobsBackup : null);
    restoreFile($itemsPath, is_string($itemsBackup) ? $itemsBackup : null);
}

echo "Site build CLI/DB consistency validation passed." . PHP_EOL;
