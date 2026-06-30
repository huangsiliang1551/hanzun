<?php

declare(strict_types=1);

/**
 * 异步任务守护进程 — 轮询处理翻译 & SEO 待办任务
 *
 * 替代 ContentPipelineService::dispatchAsyncJobs() 的 popen/nohup 模式，
 * 完全解耦 HTTP 请求与 AI API 调用，避免 PHP-FPM 超时杀进程。
 *
 * 用法:
 *   php tools/process-jobs-daemon.php          # 前台运行
 *   php tools/process-jobs-daemon.php --daemon # 守护进程模式（后台运行）
 *   php tools/process-jobs-daemon.php --once   # 单次执行（用于 cron/手动）
 *
 * systemd 部署:
 *   [Unit]
 *   Description=Bagel CMS Async Job Processor
 *   After=network.target mysql.service
 *
 *   [Service]
 *   Type=simple
 *   User=www
 *   WorkingDirectory=/www/wwwroot/bagelsmachinery.com/backend
 *   ExecStart=/usr/bin/php tools/process-jobs-daemon.php
 *   Restart=always
 *   RestartSec=5
 *   StandardOutput=append:/var/log/bagel-jobs.log
 *   StandardError=append:/var/log/bagel-jobs-error.log
 *
 *   [Install]
 *   WantedBy=multi-user.target
 */

// ── Bootstrap ──
$basePath = dirname(__DIR__);

require_once $basePath . '/app/common/bootstrap/Autoloader.php';
require_once $basePath . '/app/common/bootstrap/EnvLoader.php';
require_once $basePath . '/app/common/bootstrap/helpers.php';

\app\common\bootstrap\Autoloader::register($basePath);
\app\common\bootstrap\EnvLoader::load($basePath . '/.env');

$configRepository = \app\common\config\ConfigRepository::instance();
$configRepository->load($basePath . '/config');
\app\common\database\DatabaseManager::instance()->configure($configRepository->get('database.connections.mysql', []));

// ── CLI 参数 ──
$runOnce  = in_array('--once',   $argv, true);
$daemon   = in_array('--daemon', $argv, true);
$pollSecs = max(2, (int) (env('JOB_DAEMON_POLL_SECONDS', '3')));

if ($runOnce && $daemon) {
    fwrite(STDERR, "Cannot use --once and --daemon together.\n");
    exit(1);
}

// ── PID 文件 (防止多实例) ──
$pidFile = sys_get_temp_dir() . '/bagel-jobs-daemon.pid';

if (!$runOnce) {
    if (file_exists($pidFile)) {
        $oldPid = (int) @file_get_contents($pidFile);
        if ($oldPid > 0 && posix_kill($oldPid, 0)) {
            fwrite(STDERR, "Daemon already running (PID: $oldPid). Use --once for single run.\n");
            exit(1);
        }
        // stale PID — clean up
        @unlink($pidFile);
    }
    file_put_contents($pidFile, (string) getmypid());
}

// ── 注册 shutdown 清理 PID ──
register_shutdown_function(function () use ($pidFile) {
    @unlink($pidFile);
});

// ── 守护进程化 ──
if ($daemon) {
    $childPid = pcntl_fork();
    if ($childPid === -1) {
        fwrite(STDERR, "Failed to fork daemon process.\n");
        exit(1);
    }
    if ($childPid > 0) {
        // Parent: exit immediately
        echo "Daemon started with PID: $childPid\n";
        exit(0);
    }
    // Child: detach from terminal
    posix_setsid();
    fclose(STDIN);
    fclose(STDOUT);
    fclose(STDERR);
    // Re-open to /dev/null
    $null = fopen('/dev/null', 'r+');
    // STDIN/STDOUT/STDERR are already closed — fine for daemon
}

// ── 信号处理 (Linux only, Windows uses --once mode) ──
$running = true;
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function () use (&$running) { $running = false; });
    pcntl_signal(SIGINT,  function () use (&$running) { $running = false; });
}

// ════════════════════════════════════════════════════════════════
// 主循环
// ════════════════════════════════════════════════════════════════

$translationService = new \app\service\translation\TranslationService();
$seoService         = new \app\service\seo\SeoService();
$siteBuildService   = new \app\service\system\SiteBuildService();

function logInfo(string $msg): void
{
    $ts = date('Y-m-d H:i:s');
    error_log("[job-daemon] [$ts] $msg");
    if (!$GLOBALS['daemon'] && !$GLOBALS['runOnce']) {
        echo "[$ts] $msg\n";
    }
}

logInfo('Daemon started. PID: ' . getmypid() . ' | Poll interval: ' . $pollSecs . 's');

$iterationCount = 0;

while ($running) {
    $iterationCount++;
    if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();

    if (!$running) {
        logInfo('Received shutdown signal, stopping...');
        break;
    }

    // ── 数据库连接保活 ──
    try {
        \app\common\database\DatabaseManager::instance()->connection()->query('SELECT 1');
    } catch (\Throwable $e) {
        logInfo('DB reconnect: ' . $e->getMessage());
        \app\common\database\DatabaseManager::instance()->configure($configRepository->get('database.connections.mysql', []));
    }

    $processed = 0;

    // ═══ 处理翻译任务 ═══
    try {
        $pendingTranslationEntities = fetchPendingEntities('translation');
        foreach ($pendingTranslationEntities as $entity) {
            if (!$running) break;
            $entityType = $entity['entity_type'];
            $entityId   = (int) $entity['entity_id'];
            logInfo("Translation: processing {$entityType}#{$entityId}");
            try {
                $translationService->executePendingEntityJobs($entityType, $entityId);
                $processed++;
            } catch (\Throwable $e) {
                logInfo("Translation FAIL: {$entityType}#{$entityId} — " . $e->getMessage());
            }
        }
    } catch (\Throwable $e) {
        logInfo("Translation fetch error: " . $e->getMessage());
    }

    // ═══ 处理 SEO 任务 ═══
    try {
        $pendingSeoEntities = fetchPendingEntities('seo');
        foreach ($pendingSeoEntities as $entity) {
            if (!$running) break;
            $entityType = $entity['entity_type'];
            $entityId   = (int) $entity['entity_id'];
            logInfo("SEO: processing {$entityType}#{$entityId}");
            try {
                $seoService->executePendingEntityJobs($entityType, $entityId);
                $processed++;
            } catch (\Throwable $e) {
                logInfo("SEO FAIL: {$entityType}#{$entityId} — " . $e->getMessage());
            }
        }
    } catch (\Throwable $e) {
        logInfo("SEO fetch error: " . $e->getMessage());
    }

    // ═══ 处理站点构建任务 ═══
    try {
        $pendingBuilds = fetchPendingSiteBuilds();
        foreach ($pendingBuilds as $build) {
            if (!$running) break;
            $jobId = (int) $build['id'];
            logInfo("SiteBuild: processing job #{$jobId}");
            try {
                $siteBuildService->processOneJob();
                $processed++;
            } catch (\Throwable $e) {
                logInfo("SiteBuild FAIL: job #{$jobId} — " . $e->getMessage());
            }
        }
    } catch (\Throwable $e) {
        logInfo("SiteBuild fetch error: " . $e->getMessage());
    }

    // ── 统计 ──
    if ($processed > 0) {
        logInfo("Iteration #{$iterationCount}: processed {$processed} entities");
    }

    // 单次运行模式
    if ($runOnce) {
        logInfo("Single run complete. Exiting.");
        break;
    }

    // ── 等待 ──
    $sleepRemaining = $pollSecs;
    while ($sleepRemaining > 0 && $running) {
        sleep(1);
        $sleepRemaining--;
        if (function_exists('pcntl_signal_dispatch')) pcntl_signal_dispatch();
    }
}

logInfo('Daemon stopped.');
exit(0);

// ════════════════════════════════════════════════════════════════

/**
 * 查找有待处理 job 的实体列表（去重，按 entity 分组）
 *
 * @return array<array{entity_type: string, entity_id: int}>
 */
function fetchPendingEntities(string $jobType): array
{
    $pdo = \app\common\database\DatabaseManager::instance()->connection();

    if ($jobType === 'translation') {
        $sql = "SELECT entity_type, entity_id
                FROM translation_jobs
                WHERE status IN ('pending', 'failed', 'processing')
                ORDER BY entity_id ASC
                LIMIT 20";
    } else {
        $sql = "SELECT entity_type, entity_id
                FROM seo_generation_jobs
                WHERE status IN ('pending', 'failed', 'processing')
                ORDER BY entity_id ASC
                LIMIT 20";
    }

    return $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * 查找待处理的站点构建任务
 *
 * @return array<array{id: int}>
 */
function fetchPendingSiteBuilds(): array
{
    $pdo = \app\common\database\DatabaseManager::instance()->connection();
    return $pdo->query(
        "SELECT id FROM site_build_jobs WHERE status IN ('queued', 'running') ORDER BY id ASC LIMIT 5"
    )->fetchAll(\PDO::FETCH_ASSOC);
}
