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

$settingsRepository = new \app\repository\SystemSettingRepository();
$publisher = new \app\service\StaticPublisher();

$config = $settingsRepository->siteConfig();
$issues = [];

$placeholderPairs = [
    'site_name' => 'Site Name Should Not Replace Brand',
    'site_title' => 'Site Title Should Not Replace Brand',
    'company_name' => 'Raw Company Name',
    'company_subtitle' => 'Default Subtitle',
    'footer_text' => 'Custom Footer Text 2030',
];

foreach ($placeholderPairs as $field => $unexpectedValue) {
    if (trim((string) ($config[$field] ?? '')) === $unexpectedValue) {
        $issues[] = 'site config contains placeholder branding value for ' . $field;
    }
}

$reflection = new ReflectionClass($publisher);
$renderHeader = $reflection->getMethod('renderHeader');
$renderHeader->setAccessible(true);
$renderFooter = $reflection->getMethod('renderFooter');
$renderFooter->setAccessible(true);

$headerHtml = (string) $renderHeader->invoke($publisher, 'zh', '/zh/index.html');
$footerHtml = (string) $renderFooter->invoke($publisher, 'zh');

foreach (array_values($placeholderPairs) as $placeholderText) {
    if (str_contains($headerHtml, $placeholderText)) {
        $issues[] = 'rendered header still exposes placeholder branding text: ' . $placeholderText;
    }
    if (str_contains($footerHtml, $placeholderText)) {
        $issues[] = 'rendered footer still exposes placeholder branding text: ' . $placeholderText;
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Site branding config validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, " - {$issue}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Site branding config validation passed.\n");
