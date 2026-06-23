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
$phraseRepository = new \app\repository\SitePhraseRepository();

$originalSiteConfig = $settingsRepository->siteConfig();
$originalPhraseItems = $phraseRepository->list(['zh', 'en'])['items'] ?? [];

$findPhraseTranslations = static function (array $items, string $phraseKey): array {
    foreach ($items as $item) {
        if (!is_array($item) || (string) ($item['phrase_key'] ?? '') !== $phraseKey) {
            continue;
        }

        return is_array($item['translations'] ?? null) ? $item['translations'] : [];
    }

    return [];
};

$restorePhrase = static function (\app\repository\SitePhraseRepository $repository, string $phraseKey, array $translations): void {
    $payload = [];
    foreach ($translations as $languageCode => $textValue) {
        $payload[(string) $languageCode] = (string) $textValue;
    }

    if ($payload === []) {
        $payload = ['zh' => '', 'en' => ''];
    }

    $repository->upsertTranslations($phraseKey, $payload);
};

$originalCompanyNameTranslations = $findPhraseTranslations($originalPhraseItems, 'company_name');
$originalCompanySubtitleTranslations = $findPhraseTranslations($originalPhraseItems, 'company_subtitle');

$issues = [];

try {
    $settingsRepository->put('site', 'config', array_replace($originalSiteConfig, [
        'site_name' => 'Site Name Should Not Replace Brand',
        'site_title' => 'Site Title Should Not Replace Brand',
        'company_name' => 'Raw Company Name',
        'company_subtitle' => 'Default Subtitle',
        'footer_text' => 'Custom Footer Text 2030',
    ]));

    $phraseRepository->upsertTranslations('company_name', [
        'zh' => 'Company Name ZH Translation',
        'en' => 'Translated Company Name',
    ]);
    $phraseRepository->upsertTranslations('company_subtitle', [
        'zh' => 'Company Subtitle ZH Translation',
        'en' => 'Localized Subtitle EN',
    ]);

    $publisher = new \app\service\StaticPublisher();
    $reflection = new ReflectionClass($publisher);

    $renderHeader = $reflection->getMethod('renderHeader');
    $renderHeader->setAccessible(true);
    $renderFooter = $reflection->getMethod('renderFooter');
    $renderFooter->setAccessible(true);
    $renderHomepagePage = $reflection->getMethod('renderHomepagePage');
    $renderHomepagePage->setAccessible(true);

    $headerHtml = (string) $renderHeader->invoke($publisher, 'en', '/en/index.html');
    $footerHtml = (string) $renderFooter->invoke($publisher, 'en');
    $homepageHtml = (string) $renderHomepagePage->invoke($publisher, 'en', '/en/index.html');
    $headerZhHtml = (string) $renderHeader->invoke($publisher, 'zh', '/zh/index.html');
    $footerZhHtml = (string) $renderFooter->invoke($publisher, 'zh');
    $homepageZhHtml = (string) $renderHomepagePage->invoke($publisher, 'zh', '/zh/index.html');

    if (!str_contains($headerHtml, '<strong>Raw Company Name</strong>')) {
        $issues[] = 'header brand title must use raw company_name from site settings';
    }

    if (str_contains($headerHtml, 'Translated Company Name')) {
        $issues[] = 'header brand title must not use translated company_name phrase';
    }

    if (!str_contains($headerHtml, 'Localized Subtitle EN')) {
        $issues[] = 'header brand subtitle must use translated company_subtitle phrase';
    }

    if (!str_contains($footerHtml, 'Raw Company Name')) {
        $issues[] = 'footer brand title must use raw company_name from site settings';
    }

    if (!str_contains($footerHtml, 'Localized Subtitle EN')) {
        $issues[] = 'footer brand subtitle must use translated company_subtitle phrase';
    }

    if (!str_contains($footerHtml, 'Custom Footer Text 2030')) {
        $issues[] = 'footer copyright must render configured backend footer_text verbatim';
    }

    if (!str_contains($homepageHtml, '<title>Raw Company Name | Localized Subtitle EN</title>')) {
        $issues[] = 'homepage title must combine raw company_name with translated company_subtitle';
    }

    if (!str_contains($headerZhHtml, 'Company Subtitle ZH Translation')) {
        $issues[] = 'header brand subtitle must use translated company_subtitle phrase for zh';
    }

    if (!str_contains($footerZhHtml, 'Company Subtitle ZH Translation')) {
        $issues[] = 'footer brand subtitle must use translated company_subtitle phrase for zh';
    }

    if (!str_contains($homepageZhHtml, '<title>Raw Company Name | Company Subtitle ZH Translation</title>')) {
        $issues[] = 'zh homepage title must combine raw company_name with translated zh company_subtitle';
    }
} finally {
    $settingsRepository->put('site', 'config', $originalSiteConfig);
    $restorePhrase($phraseRepository, 'company_name', $originalCompanyNameTranslations);
    $restorePhrase($phraseRepository, 'company_subtitle', $originalCompanySubtitleTranslations);
}

if ($issues !== []) {
    fwrite(STDERR, "Public branding validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, " - {$issue}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Public branding validation passed.\n");
