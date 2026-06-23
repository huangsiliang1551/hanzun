<?php

declare(strict_types=1);

putenv('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK=1');
$_ENV['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';
$_SERVER['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';

require_once __DIR__ . '/../app/common/bootstrap/Autoloader.php';
require_once __DIR__ . '/../app/common/bootstrap/EnvLoader.php';
require_once __DIR__ . '/../app/common/bootstrap/helpers.php';

app\common\bootstrap\Autoloader::register(dirname(__DIR__));
app\common\bootstrap\EnvLoader::load(dirname(__DIR__) . '/.env');
app\common\config\ConfigRepository::instance()->load(dirname(__DIR__) . '/config');
app\common\database\DatabaseManager::instance()->configure(
    app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);

$repository = new app\repository\SeoRepository();
$logs = $repository->fourOhFourLogs();

if (!is_array($logs)) {
    fwrite(STDERR, "SEO 404 DB query validation failed:\n- fourOhFourLogs() must return an array\n");
    exit(1);
}

fwrite(STDOUT, "SEO 404 DB query validation passed.\n");
