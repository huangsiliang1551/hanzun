<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);

require_once $backendRoot . '/app/common/bootstrap/Autoloader.php';
require_once $backendRoot . '/app/common/bootstrap/EnvLoader.php';
require_once $backendRoot . '/app/common/bootstrap/helpers.php';

\app\common\bootstrap\Autoloader::register($backendRoot);
\app\common\bootstrap\EnvLoader::load($backendRoot . '/.env');
\app\common\config\ConfigRepository::instance()->load($backendRoot . '/config');
\app\common\database\DatabaseManager::instance()->configure(
    \app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);

$jobId = 0;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (preg_match('/^--job=(\d+)$/', (string) $arg, $matches) === 1) {
        $jobId = (int) ($matches[1] ?? 0);
        break;
    }
}

if ($jobId <= 0) {
    fwrite(STDERR, "missing --job argument\n");
    exit(1);
}

$service = new \app\service\system\SiteBuildService();
$repository = new \app\repository\SiteBuildRepository();

try {
    $service->runJob($jobId);
    exit(0);
} catch (Throwable $exception) {
    $job = $repository->findJob($jobId);
    if ($job !== null) {
        $repository->updateJob($jobId, [
            'status' => 'failed',
            'current_step' => 'failed',
            'error_message' => $exception->getMessage(),
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }

    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
