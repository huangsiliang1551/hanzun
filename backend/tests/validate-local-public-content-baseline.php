<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);

putenv('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK=1');
$_ENV['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';
$_SERVER['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';

require_once $backendRoot . '/app/common/bootstrap/Autoloader.php';
require_once $backendRoot . '/app/common/bootstrap/EnvLoader.php';
require_once $backendRoot . '/app/common/bootstrap/helpers.php';

\app\common\bootstrap\Autoloader::register($backendRoot);
\app\common\bootstrap\EnvLoader::load($backendRoot . '/.env');
\app\common\config\ConfigRepository::instance()->load($backendRoot . '/config');
\app\common\database\DatabaseManager::instance()->configure(
    \app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);

$pdo = \app\common\database\DatabaseManager::instance()->connection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Local public content baseline validation failed:\n- database connection unavailable\n");
    exit(1);
}

$issues = [];
$siteService = new \app\service\content\PublicSiteService();

$assertMinimumItems = static function (string $label, array $items, int $minimum, array &$issues): void {
    if (count($items) < $minimum) {
        $issues[] = sprintf('%s requires at least %d published items, got %d', $label, $minimum, count($items));
    }
};

$assertEnglishReadable = static function (string $label, array $items, string $field, array &$issues): void {
    foreach ($items as $index => $item) {
        $value = trim((string) ($item[$field] ?? ''));
        if ($value === '') {
            $issues[] = sprintf('%s item %d missing translated %s', $label, $index + 1, $field);
            continue;
        }

        if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $value) === 1) {
            $issues[] = sprintf('%s item %d %s still contains CJK text: %s', $label, $index + 1, $field, $value);
        }
    }
};

$productsZh = array_values((array) ($siteService->products('zh', 1, 20)['items'] ?? []));
$productsEn = array_values((array) ($siteService->products('en', 1, 20)['items'] ?? []));
$solutionsZh = array_values((array) ($siteService->solutions('zh', 1, 20)['items'] ?? []));
$solutionsEn = array_values((array) ($siteService->solutions('en', 1, 20)['items'] ?? []));
$newsZh = array_values((array) ($siteService->newsList('zh', 1, 20)['items'] ?? []));
$newsEn = array_values((array) ($siteService->newsList('en', 1, 20)['items'] ?? []));
$casesZh = array_values((array) ($siteService->caseList('zh', 1, 20)['items'] ?? []));
$casesEn = array_values((array) ($siteService->caseList('en', 1, 20)['items'] ?? []));

$assertMinimumItems('public products', $productsZh, 4, $issues);
$assertMinimumItems('public solutions', $solutionsZh, 4, $issues);
$assertMinimumItems('public news', $newsZh, 4, $issues);
$assertMinimumItems('public cases', $casesZh, 4, $issues);

$assertMinimumItems('public products en', $productsEn, 4, $issues);
$assertMinimumItems('public solutions en', $solutionsEn, 4, $issues);
$assertMinimumItems('public news en', $newsEn, 4, $issues);
$assertMinimumItems('public cases en', $casesEn, 4, $issues);

$assertEnglishReadable('public products en', array_slice($productsEn, 0, 4), 'name', $issues);
$assertEnglishReadable('public solutions en', array_slice($solutionsEn, 0, 4), 'name', $issues);
$assertEnglishReadable('public news en', array_slice($newsEn, 0, 4), 'title', $issues);
$assertEnglishReadable('public cases en', array_slice($casesEn, 0, 4), 'title', $issues);

$counts = [
    'products' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE publish_status = 'published'")->fetchColumn(),
    'solutions' => (int) $pdo->query("SELECT COUNT(*) FROM solutions WHERE publish_status = 'published'")->fetchColumn(),
    'articles_news' => (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE publish_status = 'published' AND content_type = 'news'")->fetchColumn(),
    'articles_cases' => (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE publish_status = 'published' AND content_type = 'case'")->fetchColumn(),
    'news' => (int) $pdo->query("SELECT COUNT(*) FROM news WHERE publish_status = 'published'")->fetchColumn(),
    'cases' => (int) $pdo->query("SELECT COUNT(*) FROM cases WHERE publish_status = 'published'")->fetchColumn(),
];

foreach (['products', 'solutions', 'articles_news', 'articles_cases', 'news', 'cases'] as $key) {
    if (($counts[$key] ?? 0) < 4) {
        $issues[] = sprintf('%s mirror requires at least 4 published rows, got %d', $key, (int) ($counts[$key] ?? 0));
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Local public content baseline validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Local public content baseline validation passed.\n");
