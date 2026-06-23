<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
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

$siteConfig = (new \app\repository\SystemSettingRepository())->siteConfig();
$configuredSubtitle = trim((string) ($siteConfig['company_subtitle'] ?? ''));
$issues = [];

$read = static function (string $relativePath) use ($projectRoot, &$issues): string {
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        $issues[] = 'missing generated file: ' . $relativePath;

        return '';
    }

    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') {
        $issues[] = 'failed to read generated file: ' . $relativePath;

        return '';
    }

    return $content;
};

foreach (['zh/index.html', 'en/index.html'] as $relativePath) {
    $markup = $read($relativePath);
    if ($markup === '') {
        continue;
    }

    foreach ([
        'footer-redesign-main footer-redesign-main-adaptive',
        'footer-redesign-brand footer-redesign-brand-fluid',
        'footer-brand-copy footer-brand-copy-lockup',
        'footer-brand-rows',
        'footer-brand-row footer-brand-row-email',
        'footer-brand-row footer-brand-row-phone-whatsapp',
        'footer-brand-row footer-brand-row-address',
        'footer-brand-contact-label',
        'footer-brand-contact-value',
        'icon-linkedin-color.svg',
        'icon-youtube-color.svg',
        'icon-line-color.svg',
    ] as $needle) {
        if (!str_contains($markup, $needle)) {
            $issues[] = 'generated brand/footer shell missing expected marker: ' . $relativePath . ' [' . $needle . ']';
        }
    }

    if ($configuredSubtitle !== '') {
        if (!str_contains($markup, $configuredSubtitle)) {
            $issues[] = 'generated shell must render configured company_subtitle: ' . $relativePath;
        }

        if (str_contains($markup, '烘焙与食品生产线设备专家')) {
            $issues[] = 'generated shell must not use legacy marketing subtitle fallback: ' . $relativePath;
        }
    }
}

foreach ([
    'assets/images/common/icon-linkedin-color.svg',
    'assets/images/common/icon-youtube-color.svg',
    'assets/images/common/icon-line-color.svg',
] as $relativePath) {
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        $issues[] = 'missing local social icon asset: ' . $relativePath;
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Brand footer shell validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Brand footer shell validation passed.\n");
