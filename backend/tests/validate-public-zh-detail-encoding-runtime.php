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

$publisher = new \app\service\StaticPublisher();
$reflection = new ReflectionClass($publisher);
$renderDetailPage = $reflection->getMethod('renderDetailPage');
$renderDetailPage->setAccessible(true);

$issues = [];
$targets = [
    ['product', 'zh', 'cake-depositor', '/zh/products/cake-depositor.html'],
    ['solution', 'zh', 'cake-line', '/zh/solutions/cake-line.html'],
    ['news', 'zh', 'germany-bakery-expo', '/zh/news/germany-bakery-expo.html'],
    ['case', 'zh', 'uae-cake-project', '/zh/cases/uae-cake-project.html'],
];

foreach ($targets as [$entityType, $languageCode, $slug, $route]) {
    try {
        $html = (string) $renderDetailPage->invoke($publisher, $entityType, $languageCode, $slug, $route);
    } catch (Throwable) {
        continue;
    }

    if (preg_match('/鍦|闂|鏂|璇|銆|锛|鈥|妗|瀹|鎴|缁|娑|閸|閻|鐎/u', $html)) {
        $issues[] = $entityType . ' zh detail page still contains garbled text';
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Public zh detail encoding validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, ' - ' . $issue . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "Public zh detail encoding validation passed.\n");
