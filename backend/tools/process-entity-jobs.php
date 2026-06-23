<?php

declare(strict_types=1);

/**
 * 异步处理实体的翻译和 SEO 任务 — 后台独立进程
 *
 * 由 ContentPipelineService::sync() 通过 popen() 在后台自动启动，
 * 不阻塞主 HTTP 请求。处理结果记录到 PHP error_log。
 *
 * 用法: php tools/process-entity-jobs.php <entity_type> <entity_id>
 *
 * 示例: php tools/process-entity-jobs.php case 42
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

// ── Parse arguments ──
if ($argc < 3) {
    fwrite(STDERR, "Usage: php tools/process-entity-jobs.php <entity_type> <entity_id>\n");
    exit(1);
}

$entityType = (string) $argv[1];
$entityId   = (int) $argv[2];

if ($entityType === '' || $entityId <= 0) {
    fwrite(STDERR, sprintf("Invalid arguments: entity_type=%s, entity_id=%d\n", $entityType, $entityId));
    exit(1);
}

error_log(sprintf('[async-jobs] Start: entity=%s id=%d', $entityType, $entityId));

// ═══ Translation jobs ═══
try {
    $translationService = new \app\service\translation\TranslationService();
    $translationService->executePendingEntityJobs($entityType, $entityId);
    error_log(sprintf('[async-jobs] Translation OK: %s #%d', $entityType, $entityId));
} catch (\Throwable $e) {
    error_log(sprintf('[async-jobs] Translation FAIL: %s #%d — %s', $entityType, $entityId, $e->getMessage()));
}

// ═══ SEO jobs ═══
try {
    $seoService = new \app\service\seo\SeoService();
    $seoService->executePendingEntityJobs($entityType, $entityId);
    error_log(sprintf('[async-jobs] SEO OK: %s #%d', $entityType, $entityId));
} catch (\Throwable $e) {
    error_log(sprintf('[async-jobs] SEO FAIL: %s #%d — %s', $entityType, $entityId, $e->getMessage()));
}

error_log(sprintf('[async-jobs] Complete: %s #%d', $entityType, $entityId));
