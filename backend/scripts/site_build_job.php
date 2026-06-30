<?php

declare(strict_types=1);

// 自动降权: 若以 root 运行 CLI, 自动切到 www 用户重新执行自己。
// 这是第二道防线 (第一道在 build_trigger.php)。正常情况下本脚本由
// PHP-FPM(www) 通过 dispatchAsync 的 exec() 派发, 本身就是 www;
// 此降权仅防护"有人 SSH 用 root 直接跑 worker"的极端场景。
if (PHP_SAPI === 'cli' && function_exists('posix_geteuid') && posix_geteuid() === 0 && !is_file('/.dockerenv')) {
    $argvEscaped = array_map('escapeshellarg', array_slice($argv, 1));
    $targetUser = null;
    foreach (['www-data', 'www'] as $candidate) {
        if (function_exists('posix_getpwnam') && posix_getpwnam($candidate) !== false) {
            $targetUser = $candidate;
            break;
        }
    }

    if ($targetUser !== null) {
        $baseCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__);
        if (!empty($argvEscaped)) {
            $baseCommand .= ' ' . implode(' ', $argvEscaped);
        }

        $runner = null;
        if (is_file('/usr/bin/sudo') && is_executable('/usr/bin/sudo')) {
            $runner = '/usr/bin/sudo -u ' . escapeshellarg($targetUser) . ' ' . $baseCommand;
        } elseif (is_file('/usr/sbin/runuser') && is_executable('/usr/sbin/runuser')) {
            $runner = '/usr/sbin/runuser -u ' . escapeshellarg($targetUser) . ' -- ' . $baseCommand;
        } elseif (is_file('/bin/su') && is_executable('/bin/su')) {
            $runner = '/bin/su -s /bin/sh ' . escapeshellarg($targetUser) . ' -c ' . escapeshellarg($baseCommand);
        } elseif (is_file('/usr/bin/su') && is_executable('/usr/bin/su')) {
            $runner = '/usr/bin/su -s /bin/sh ' . escapeshellarg($targetUser) . ' -c ' . escapeshellarg($baseCommand);
        }

        if ($runner !== null) {
            passthru($runner, $exitCode);
            exit((int) $exitCode);
        }
    }
}

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
$isChildWorker = false;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (preg_match('/^--job=(\d+)$/', (string) $arg, $matches) === 1) {
        $jobId = (int) ($matches[1] ?? 0);
        continue;
    }

    if ((string) $arg === '--child=1') {
        $isChildWorker = true;
    }
}

if ($jobId <= 0) {
    fwrite(STDERR, "missing --job argument\n");
    exit(1);
}

$service = new \app\service\system\SiteBuildService();
$repository = new \app\repository\SiteBuildRepository();

if ($isChildWorker) {
    try {
        (new \app\service\StaticPublisher())->executeJobWorker($jobId);
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . PHP_EOL);
        exit(1);
    }
}

$lockDir = $backendRoot . '/runtime/tmp';
if (!is_dir($lockDir) && !@mkdir($lockDir, 0777, true) && !is_dir($lockDir)) {
    fwrite(STDERR, "failed to create lock directory\n");
    exit(1);
}

$lockFile = $lockDir . '/site-build-job-' . $jobId . '.lock';
$lockHandle = @fopen($lockFile, 'c+');
if ($lockHandle === false) {
    fwrite(STDERR, "failed to open job lock file\n");
    exit(1);
}

if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "site build job already running: {$jobId}\n");
    fclose($lockHandle);
    exit(0);
}

ftruncate($lockHandle, 0);
fwrite($lockHandle, (string) getmypid());

try {
    $service->runJob($jobId);
    @flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
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
    @flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}
