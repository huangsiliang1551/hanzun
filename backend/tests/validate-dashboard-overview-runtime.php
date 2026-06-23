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

$service = new app\service\dashboard\DashboardService();
$overview = $service->overview('7d');

$issues = [];
if (!is_array($overview)) {
    $issues[] = 'dashboard overview must return an array payload';
} else {
    foreach (['traffic', 'ai', 'inquiries', 'jobs'] as $key) {
        if (!array_key_exists($key, $overview) || !is_array($overview[$key])) {
            $issues[] = sprintf('dashboard overview must include %s section', $key);
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
