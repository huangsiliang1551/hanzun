<?php

declare(strict_types=1);

$backendRoot = __DIR__ . '/backend';

require_once $backendRoot . '/app/common/bootstrap/Autoloader.php';
require_once $backendRoot . '/app/common/bootstrap/EnvLoader.php';
require_once $backendRoot . '/app/common/bootstrap/helpers.php';

\app\common\bootstrap\Autoloader::register($backendRoot);
\app\common\bootstrap\EnvLoader::load($backendRoot . '/.env');
\app\common\config\ConfigRepository::instance()->load($backendRoot . '/config');
\app\common\database\DatabaseManager::instance()->configure(
    \app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);

putenv('SITE_BUILD_ASYNC_DISABLED=1');
putenv('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK=1');
$_ENV['SITE_BUILD_ASYNC_DISABLED'] = '1';
$_SERVER['SITE_BUILD_ASYNC_DISABLED'] = '1';
$_ENV['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';
$_SERVER['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';

$service = new \app\service\system\SiteBuildService();
$created = $service->createJob([
    'scope' => 'full',
    'trigger_source' => 'manual_local_full_rebuild',
    'language_codes' => ['zh', 'en'],
    'context' => [],
], ['nickname' => 'Codex']);

$jobId = (int) ($created['job']['id'] ?? 0);
if ($jobId <= 0) {
    fwrite(STDERR, "Failed to create full site build job.\n");
    exit(1);
}

$result = $service->runJob($jobId);
$job = is_array($result['job'] ?? null) ? $result['job'] : [];
$status = (string) ($job['status'] ?? 'failed');
$rendered = (int) ($job['output_summary']['rendered_files'] ?? 0);
$failed = (int) ($job['output_summary']['failed_files'] ?? 0);

fwrite(
    $status === 'completed' ? STDOUT : STDERR,
    sprintf(
        "Full site build %s. job=%d rendered=%d failed=%d\n",
        $status,
        $jobId,
        $rendered,
        $failed
    )
);

if ($status !== 'completed') {
    $message = trim((string) ($job['error_message'] ?? ''));
    if ($message !== '') {
        fwrite(STDERR, $message . "\n");
    }
    exit(1);
}
