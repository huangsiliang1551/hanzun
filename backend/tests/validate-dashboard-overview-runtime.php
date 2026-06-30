<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/common/bootstrap/Autoloader.php';
require_once __DIR__ . '/../app/common/bootstrap/EnvLoader.php';
require_once __DIR__ . '/../app/common/bootstrap/helpers.php';

app\common\bootstrap\Autoloader::register(dirname(__DIR__));
app\common\bootstrap\EnvLoader::load(dirname(__DIR__) . '/.env');
app\common\config\ConfigRepository::instance()->load(dirname(__DIR__) . '/config');
app\common\database\DatabaseManager::instance()->configure(
    app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);

/** @var PDO|null $pdo */
$pdo = app\common\database\DatabaseManager::instance()->connection();
$service = new app\service\dashboard\DashboardService();
$overview = [];
$testSessionCode = 'dashboard-contract-runtime-check';
$inserted = false;

if ($pdo instanceof PDO) {
    $statement = $pdo->prepare(
        'INSERT INTO visitor_events (session_code, page, title, referrer, visited_at, language_code)
         VALUES (:session_code, :page, :title, :referrer, :visited_at, :language_code)'
    );
    $inserted = $statement->execute([
        'session_code' => $testSessionCode,
        'page' => '/zh/products/cake-depositor.html',
        'title' => '蛋糕自动灌装机',
        'referrer' => '',
        'visited_at' => date('Y-m-d H:i:s'),
        'language_code' => 'zh',
    ]);
}

try {
    $overview = $service->overview('7d');
} finally {
    if ($inserted && $pdo instanceof PDO) {
        $cleanup = $pdo->prepare('DELETE FROM visitor_events WHERE session_code = :session_code');
        $cleanup->execute(['session_code' => $testSessionCode]);
    }
}

$issues = [];
if (!is_array($overview)) {
    $issues[] = 'dashboard overview must return an array payload';
} else {
    foreach (['traffic', 'ai', 'inquiries', 'jobs'] as $key) {
        if (!array_key_exists($key, $overview) || !is_array($overview[$key])) {
            $issues[] = sprintf('dashboard overview must include %s section', $key);
        }
    }

    $traffic = is_array($overview['traffic'] ?? null) ? $overview['traffic'] : [];
    foreach (['uv', 'pv', 'bounce_rate', 'series', 'countries', 'top_pages'] as $key) {
        if (!array_key_exists($key, $traffic)) {
            $issues[] = sprintf('dashboard traffic must include %s field', $key);
        }
    }

    if (is_array($traffic['countries'] ?? null) && $traffic['countries'] !== []) {
        $firstCountry = $traffic['countries'][0] ?? null;
        if (!is_array($firstCountry) || !array_key_exists('country_code', $firstCountry)) {
            $issues[] = 'dashboard traffic countries must expose country_code for admin dashboard rendering';
        }
        if (!is_array($firstCountry) || !array_key_exists('uv', $firstCountry)) {
            $issues[] = 'dashboard traffic countries must expose uv for admin dashboard rendering';
        }
    }

    if (is_array($traffic['top_pages'] ?? null) && $traffic['top_pages'] !== []) {
        $firstPage = $traffic['top_pages'][0] ?? null;
        if (!is_array($firstPage) || !array_key_exists('landing_page', $firstPage)) {
            $issues[] = 'dashboard traffic top_pages must expose landing_page for admin dashboard rendering';
        }
        if (!is_array($firstPage) || !array_key_exists('pv', $firstPage)) {
            $issues[] = 'dashboard traffic top_pages must expose pv for admin dashboard rendering';
        }
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Dashboard overview runtime validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Dashboard overview runtime validation passed.\n");
