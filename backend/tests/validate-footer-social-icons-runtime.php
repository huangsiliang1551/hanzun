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
$renderFooter = $reflection->getMethod('renderFooter');
$renderFooter->setAccessible(true);

$footerHtml = (string) $renderFooter->invoke($publisher, 'en');
$issues = [];

if (str_contains($footerHtml, 'data-footer-sitemap-link')) {
    $issues[] = 'footer must not render a dedicated sitemap column';
}

if (str_contains($footerHtml, '/en/sitemap.html')) {
    $issues[] = 'footer must not expose sitemap link';
}

if (!str_contains($footerHtml, 'footer-brand-socials')) {
    $issues[] = 'footer must render a dedicated social icon group';
}

foreach (['linkedin', 'youtube', 'line'] as $fieldKey) {
    if (!str_contains($footerHtml, 'footer-brand-social ' . $fieldKey)) {
        $issues[] = 'footer must render ' . $fieldKey . ' as icon-only social link';
    }
}

foreach (['icon-linkedin-color.svg', 'icon-youtube-color.svg', 'icon-line-color.svg'] as $assetName) {
    if (!str_contains($footerHtml, $assetName)) {
        $issues[] = 'footer social links must use local colored icon asset: ' . $assetName;
    }
}

if (!str_contains($footerHtml, 'target="_blank" rel="noreferrer"')) {
    $issues[] = 'footer social links must open in a new window';
}

if ($issues !== []) {
    fwrite(STDERR, "Footer social icon validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Footer social icon validation passed.\n");
