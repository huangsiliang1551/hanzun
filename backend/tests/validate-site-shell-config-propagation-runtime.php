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

$issues = [];
$settingsRepository = new \app\repository\SystemSettingRepository();
$phraseRepository = new \app\repository\SitePhraseRepository();
$original = $settingsRepository->siteConfig();
$originalPhraseItems = $phraseRepository->list(['zh', 'en'])['items'] ?? [];
$mutated = $original;
$mutated['company_name'] = 'Config Propagation QA';
$mutated['company_subtitle'] = 'Shared Shell Subtitle QA';
$mutated['logo_url'] = '/assets/images/home/equipment-integrated-line.jpg';
$mutated['footer_text'] = 'Config Propagation Footer QA';

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

$originalCompanySubtitleTranslations = $findPhraseTranslations($originalPhraseItems, 'company_subtitle');

try {
    $settingsRepository->put('site', 'config', $mutated);
    $phraseRepository->upsertTranslations('company_subtitle', [
        'zh' => 'Shared Shell Subtitle ZH QA',
        'en' => 'Shared Shell Subtitle EN QA',
    ]);

    $publisher = new \app\service\StaticPublisher();
    $reflection = new ReflectionClass($publisher);

    $renderHeader = $reflection->getMethod('renderHeader');
    $renderHeader->setAccessible(true);
    $renderFooter = $reflection->getMethod('renderFooter');
    $renderFooter->setAccessible(true);

    $headerHtml = (string) $renderHeader->invoke($publisher, 'en', '/en/index.html');
    $footerHtml = (string) $renderFooter->invoke($publisher, 'en');
    $headerZhHtml = (string) $renderHeader->invoke($publisher, 'zh', '/zh/index.html');
    $footerZhHtml = (string) $renderFooter->invoke($publisher, 'zh');

    foreach ([
        'header company name' => [$headerHtml, 'Config Propagation QA'],
        'header logo path' => [$headerHtml, '/assets/images/home/equipment-integrated-line.jpg'],
        'footer company name' => [$footerHtml, 'Config Propagation QA'],
        'footer logo path' => [$footerHtml, '/assets/images/home/equipment-integrated-line.jpg'],
        'footer copy' => [$footerHtml, 'Config Propagation Footer QA'],
        'en header subtitle translation' => [$headerHtml, 'Shared Shell Subtitle EN QA'],
        'en footer subtitle translation' => [$footerHtml, 'Shared Shell Subtitle EN QA'],
        'zh header subtitle translation' => [$headerZhHtml, 'Shared Shell Subtitle ZH QA'],
        'zh footer subtitle translation' => [$footerZhHtml, 'Shared Shell Subtitle ZH QA'],
    ] as $label => [$markup, $needle]) {
        if (!str_contains((string) $markup, (string) $needle)) {
            $issues[] = 'site shell should reflect updated site config for ' . $label;
        }
    }
} finally {
    $settingsRepository->put('site', 'config', $original);
    $restorePhrase($phraseRepository, 'company_subtitle', $originalCompanySubtitleTranslations);
}

if ($issues !== []) {
    fwrite(STDERR, "Site shell config propagation validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Site shell config propagation validation passed.\n");
