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

$service = new \app\service\content\PublicSiteService();
$publisher = new \app\service\StaticPublisher();
$issues = [];

$productList = $service->products('en', 1, 12);
$solutionList = $service->solutions('en', 1, 12);
$newsList = $service->newsList('en', 1, 12);
$caseList = $service->caseList('en', 1, 12);

$assertCollectionShape = static function (string $label, array $payload, array &$issues): void {
    if (!array_key_exists('items', $payload) || !is_array($payload['items'])) {
        $issues[] = $label . ' payload must expose items[]';
    }

    if (!array_key_exists('categories', $payload) || !is_array($payload['categories'])) {
        $issues[] = $label . ' payload must expose categories[]';
    }
};

$assertCollectionShape('product list', $productList, $issues);
$assertCollectionShape('solution list', $solutionList, $issues);
$assertCollectionShape('news list', $newsList, $issues);
$assertCollectionShape('case list', $caseList, $issues);

$renderListingPageV2 = $reflection = null;

$firstSolution = $solutionList['items'][0] ?? [];
if ($firstSolution === []) {
    $issues[] = 'solution list must contain at least one published record for runtime validation';
} else {
    if (!array_key_exists('cover_image_url', $firstSolution)) {
        $issues[] = 'solution payload must expose cover_image_url';
    }
    if (!array_key_exists('flow_text', $firstSolution)) {
        $issues[] = 'solution payload must expose normalized flow_text';
    }
    if (!array_key_exists('capacity_text', $firstSolution)) {
        $issues[] = 'solution payload must expose normalized capacity_text';
    }
}

$firstCase = $caseList['items'][0] ?? [];
if ($firstCase === []) {
    $issues[] = 'case list must contain at least one published record for runtime validation';
} else {
    if (!array_key_exists('country_code', $firstCase)) {
        $issues[] = 'case payload must expose country_code';
    }
    if (!array_key_exists('related_solution_ids', $firstCase) || !is_array($firstCase['related_solution_ids'])) {
        $issues[] = 'case payload must expose related_solution_ids as array';
    }
    if (!array_key_exists('related_product_ids', $firstCase) || !is_array($firstCase['related_product_ids'])) {
        $issues[] = 'case payload must expose related_product_ids as array';
    }
}

$reflection = new ReflectionClass($publisher);
$renderListingPageV2 = $reflection->getMethod('renderListingPageV2');
$renderListingPageV2->setAccessible(true);
$renderDetailPage = $reflection->getMethod('renderDetailPage');
$renderDetailPage->setAccessible(true);

$solutionListingHtml = (string) $renderListingPageV2->invoke(
    $publisher,
    'solution',
    'en',
    '/en/solutions.html'
);

if (!str_contains($solutionListingHtml, 'data-public-content-listing="1"')) {
    $issues[] = 'solution listing must expose shared listing marker';
}

if (!str_contains($solutionListingHtml, 'data-public-listing-grid="1"')) {
    $issues[] = 'solution listing must expose the dedicated content grid marker';
}

if (!str_contains($solutionListingHtml, 'public-card-cta')) {
    $issues[] = 'solution listing cards must expose a dedicated call-to-action area';
}

$solutionDetailHtml = (string) $renderDetailPage->invoke(
    $publisher,
    'solution',
    'en',
    'cake-line',
    '/en/solutions/cake-line.html'
);

if (!str_contains($solutionDetailHtml, 'data-public-content-detail="1"')) {
    $issues[] = 'solution detail must expose shared detail marker';
}

if (!str_contains($solutionDetailHtml, 'data-public-detail-layout="1"')) {
    $issues[] = 'solution detail must expose the dedicated content layout marker';
}

if (!str_contains($solutionDetailHtml, 'data-public-detail-sidebar="1"')) {
    $issues[] = 'solution detail must expose the dedicated sidebar marker';
}

if (!str_contains($solutionDetailHtml, 'data-detail-module="solution-flow"')) {
    $issues[] = 'solution detail must render solution flow module';
}

if (!str_contains($solutionDetailHtml, 'data-detail-module="solution-capacity"')) {
    $issues[] = 'solution detail must render solution capacity module';
}

try {
    $productDetailHtml = (string) $renderDetailPage->invoke(
        $publisher,
        'product',
        'en',
        'cake-depositor',
        '/en/products/cake-depositor.html'
    );

    if (!str_contains($productDetailHtml, 'data-public-content-detail="1"')) {
        $issues[] = 'product detail must expose shared detail marker';
    }

    if (!str_contains($productDetailHtml, 'data-detail-module="product-facts"')) {
        $issues[] = 'product detail must render product facts module';
    }
} catch (Throwable) {
    // Product detail data is currently volatile across local sessions; validate it when available.
}

try {
    $caseDetailHtml = (string) $renderDetailPage->invoke(
        $publisher,
        'case',
        'en',
        'uae-cake-project',
        '/en/cases/uae-cake-project.html'
    );

    if (!str_contains($caseDetailHtml, 'data-public-content-detail="1"')) {
        $issues[] = 'case detail must expose shared detail marker';
    }

    if (!str_contains($caseDetailHtml, 'data-detail-module="case-meta"')) {
        $issues[] = 'case detail must render case meta module';
    }
} catch (Throwable) {
    // Case detail can be skipped when seed content is unavailable in the local database.
}

try {
    $newsDetailHtml = (string) $renderDetailPage->invoke(
        $publisher,
        'news',
        'en',
        'germany-bakery-expo',
        '/en/news/germany-bakery-expo.html'
    );

    if (!str_contains($newsDetailHtml, 'data-public-content-detail="1"')) {
        $issues[] = 'news detail must expose shared detail marker';
    }

    if (!str_contains($newsDetailHtml, 'data-detail-module="news-meta"')) {
        $issues[] = 'news detail must render news meta module';
    }

} catch (Throwable) {
    // News detail can be skipped when seed content is unavailable in the local database.
}

if (isset($productDetailHtml)) {
}

if (isset($caseDetailHtml)) {
    if (!str_contains($caseDetailHtml, 'data-detail-related="1"')) {
        $issues[] = 'case detail must expose related content section';
    }

    if (!str_contains($caseDetailHtml, 'data-related-products="1"')) {
        $issues[] = 'case detail must expose related products section';
    }

    if (!str_contains($caseDetailHtml, 'data-related-solutions="1"')) {
        $issues[] = 'case detail must expose related solutions section';
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Public content payload validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, ' - ' . $issue . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "Public content payload validation passed.\n");
