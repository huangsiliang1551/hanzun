<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);
$projectRoot = dirname($backendRoot);

putenv('SITE_BUILD_ASYNC_DISABLED=1');
putenv('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK=1');
$_ENV['SITE_BUILD_ASYNC_DISABLED'] = '1';
$_SERVER['SITE_BUILD_ASYNC_DISABLED'] = '1';
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
$databaseAvailable = $pdo instanceof PDO;
$skippedChecks = [];
if (!$databaseAvailable) {
    $skippedChecks[] = 'database-backed parity checks';
}

$issues = [];

$fetchColumnList = static function (PDO $pdo, string $sql): array {
    $statement = $pdo->query($sql);
    if ($statement === false) {
        return [];
    }

    $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

    return array_values(array_filter(
        array_map(static fn (mixed $value): string => trim((string) $value), is_array($rows) ? $rows : []),
        static fn (string $value): bool => $value !== ''
    ));
};

$fetchRows = static function (PDO $pdo, string $sql): array {
    $statement = $pdo->query($sql);
    if ($statement === false) {
        return [];
    }

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    return array_values(array_filter($rows ?: [], static fn (mixed $row): bool => is_array($row)));
};

$collectCategoryScopes = static function (array $categories) use (&$collectCategoryScopes): array {
    $scopes = [];
    foreach ($categories as $category) {
        if (!is_array($category)) {
            continue;
        }

        $scope = strtolower(trim((string) ($category['content_type_scope'] ?? '')));
        if ($scope !== '') {
            $scopes[] = $scope;
        }

        if (is_array($category['children'] ?? null)) {
            $scopes = array_merge($scopes, $collectCategoryScopes($category['children']));
        }
    }

    return $scopes;
};

$extractProductFilterSlugs = static function (string $markup): array {
    preg_match_all('/href="[^"]*\/products\.html\?category=([^"#]+)#category-[^"]*"/', $markup, $matches);

    return array_values(array_unique(array_values(array_filter(
        array_map(static fn (mixed $value): string => strtolower(trim(urldecode((string) $value))), $matches[1] ?? []),
        static fn (string $value): bool => $value !== ''
    ))));
};

$extractListingFilterSlugs = static function (string $markup, string $routeKey): array {
    $routeKey = preg_quote(trim($routeKey), '/');
    preg_match_all('/href="[^"]*\/' . $routeKey . '\.html\?category=([^"#]+)#category-[^"]*"/', $markup, $matches);

    return array_values(array_unique(array_values(array_filter(
        array_map(static fn (mixed $value): string => strtolower(trim(urldecode((string) $value))), $matches[1] ?? []),
        static fn (string $value): bool => $value !== ''
    ))));
};

$normalizeContactHref = static function (string $fieldKey, string $value, string $languageCode): ?string {
    $fieldKey = strtolower(trim($fieldKey));
    $value = trim($value);
    if ($fieldKey === '' || $value === '') {
        return null;
    }

    return match ($fieldKey) {
        'email' => 'mailto:' . $value,
        'phone' => 'tel:' . preg_replace('/[^0-9+]/', '', $value),
        'whatsapp' => preg_match('/^https?:\/\//i', $value) === 1 ? $value : 'https://wa.me/' . preg_replace('/[^0-9]/', '', $value),
        'line' => preg_match('/^https?:\/\//i', $value) === 1 ? $value : 'https://line.me/R/ti/p/~' . rawurlencode($value),
        'linkedin', 'youtube' => preg_match('/^https?:\/\//i', $value) === 1 ? $value : 'https://' . ltrim($value, '/'),
        'address' => '/' . $languageCode . '/about.html#contact',
        default => null,
    };
};

$isRenderableRecord = static function (string $entityType, string $slug, string $title = ''): bool {
    $slug = strtolower(trim($slug));
    if ($slug === '' || preg_match('/^\d+$/', $slug) === 1) {
        return false;
    }

    foreach (['runtime-', 'test-', 'demo-', 'temp-', 'draft-', 'sample-', 'example-', 'preview-'] as $prefix) {
        if (str_starts_with($slug, $prefix)) {
            return false;
        }
    }

    $title = strtolower(trim($title));
    if ($title !== '') {
        if (preg_match('/^\d+$/', $title) === 1) {
            return false;
        }

        foreach (['runtime landing page', 'runtime test', 'test page', 'demo page', 'sample page', 'preview page'] as $needle) {
            if (str_contains($title, $needle)) {
                return false;
            }
        }
    }

    if ($entityType === 'page' && in_array($slug, ['index', 'home', 'about', 'contact'], true)) {
        return false;
    }

    return true;
};

$enabledLanguages = [];
$defaultLanguage = 'zh';
if ($databaseAvailable) {
    $languageStatement = $pdo->query('SELECT code, is_enabled, is_default FROM languages ORDER BY sort DESC, id ASC');
    $languageRows = $languageStatement !== false ? $languageStatement->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach (is_array($languageRows) ? $languageRows : [] as $row) {
        $code = strtolower(trim((string) ($row['code'] ?? '')));
        if ($code === '' || (int) ($row['is_enabled'] ?? 0) !== 1) {
            continue;
        }

        $enabledLanguages[] = $code;
        if ((int) ($row['is_default'] ?? 0) === 1) {
            $defaultLanguage = $code;
        }
    }
}

if ($enabledLanguages === []) {
    $enabledLanguages = ['zh', 'en'];
}

$expectedStaticFiles = [
    $projectRoot . DIRECTORY_SEPARATOR . 'index.html',
    $projectRoot . DIRECTORY_SEPARATOR . 'robots.txt',
    $projectRoot . DIRECTORY_SEPARATOR . 'sitemap.xml',
];

foreach ($expectedStaticFiles as $path) {
    if (!is_file($path)) {
        $issues[] = 'missing generated file: ' . $path;
    }
}

$rootIndexPath = $projectRoot . DIRECTORY_SEPARATOR . 'index.html';
if (is_file($rootIndexPath)) {
    $rootIndex = (string) file_get_contents($rootIndexPath);
    if (!str_contains($rootIndex, "window.location.replace('/' + code + '/index.html');")) {
        $issues[] = 'root index.html must include browser-language redirect script';
    }
    if (!str_contains($rootIndex, 'meta http-equiv="refresh"')) {
        $issues[] = 'root index.html must include noscript-compatible refresh fallback';
    }
    if (!str_contains($rootIndex, '/zh/index.html') && !str_contains($rootIndex, '/en/index.html')) {
        $issues[] = 'root index.html must expose at least one concrete language fallback route';
    }
    if (str_contains($rootIndex, "localStorage.getItem('hanzun-lang')")) {
        $issues[] = 'root index.html must not prioritize stored language over browser language';
    }
    if (!str_contains($rootIndex, 'navigator.language') && !str_contains($rootIndex, 'navigator.userLanguage')) {
        $issues[] = 'root index.html must use browser language detection for language redirect';
    }
}

$basePages = ['index', 'about', 'contact', 'products', 'solutions', 'news', 'cases', 'sitemap'];

foreach ($enabledLanguages as $languageCode) {
    foreach ($basePages as $page) {
        $relative = $page === 'index'
            ? $languageCode . DIRECTORY_SEPARATOR . 'index.html'
            : $languageCode . DIRECTORY_SEPARATOR . $page . '.html';
        $path = $projectRoot . DIRECTORY_SEPARATOR . $relative;

        if (!is_file($path)) {
            $issues[] = 'missing generated page: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            continue;
        }

        $markup = (string) file_get_contents($path);
        if (!str_contains($markup, 'data-force-lang="' . $languageCode . '"')) {
            $issues[] = 'generated page missing forced language marker: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        if (!str_contains($markup, 'data-static-nav="1"')) {
            $issues[] = 'generated page must use static publisher navigation shell: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        if (substr_count($markup, '<footer class="site-footer-redesign"') !== 1) {
            $issues[] = 'generated page must contain exactly one redesigned footer shell: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        if (str_contains($markup, 'class="site-static-sitemap"')) {
            $issues[] = 'generated page footer must not inline the full sitemap block: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        if (str_contains($markup, 'data-footer-sitemap-link')) {
            $issues[] = 'generated page footer must not expose a sitemap column: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        if (str_contains($markup, 'href="/' . $languageCode . '/sitemap.html"')) {
            $issues[] = 'generated page footer must not expose sitemap link: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        if (str_contains($markup, '<footer class="site-footer">')) {
            $issues[] = 'generated page must not include legacy footer shell: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        if (str_contains($markup, 'bottom-contact-dock')) {
            $issues[] = 'generated page must not include legacy bottom contact dock: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        if (str_contains($markup, 'data-product-nav')) {
            $issues[] = 'generated page product navigation must use the same dropdown shell as other section menus: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        if (str_contains($markup, '<header class="site-header">') && substr_count($markup, '<header class="site-header">') !== 1) {
            $issues[] = 'generated page must contain exactly one header shell: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        if (in_array($page, ['products', 'solutions', 'news', 'cases'], true)) {
            if (!str_contains($markup, 'data-public-content-listing="1"')) {
                $issues[] = 'listing page must expose shared content listing marker: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            }

            if (!str_contains($markup, 'data-public-card-type=')) {
                $issues[] = 'listing page must expose typed content cards: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            }

            if (!str_contains($markup, 'data-category-filter-root="1"')) {
                $issues[] = 'listing page must expose category filter root marker: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            }

            if (!str_contains($markup, 'data-category-slug=')) {
                $issues[] = 'listing page must expose category slug metadata: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            }

            preg_match_all('/public-filter-button[^>]*data-category-slug="([^"]+)"/', $markup, $filterMatches);
            preg_match_all('/data-category-card[^>]*data-category-slug="([^"]+)"/', $markup, $cardMatches);
            $filterSlugs = array_unique(array_values(array_filter($filterMatches[1] ?? [], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')));
            $cardSlugs = array_unique(array_values(array_filter($cardMatches[1] ?? [], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')));

            foreach ($cardSlugs as $cardSlug) {
                if (!in_array($cardSlug, $filterSlugs, true)) {
                    $issues[] = 'listing page must expose a filter button for every rendered category card slug: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative) . ' [' . $cardSlug . ']';
                }
            }
        }
    }
}

foreach ($enabledLanguages as $languageCode) {
    $productsRelative = $languageCode . DIRECTORY_SEPARATOR . 'products.html';
    $productsPath = $projectRoot . DIRECTORY_SEPARATOR . $productsRelative;
    $indexRelative = $languageCode . DIRECTORY_SEPARATOR . 'index.html';
    $indexPath = $projectRoot . DIRECTORY_SEPARATOR . $indexRelative;

    if (!is_file($productsPath) || !is_file($indexPath)) {
        continue;
    }

    $productMarkup = (string) file_get_contents($productsPath);
    $indexMarkup = (string) file_get_contents($indexPath);
    $productFilterSlugs = $extractProductFilterSlugs($productMarkup);

    if (count($productFilterSlugs) >= 2) {
        foreach (array_slice($productFilterSlugs, 0, 2) as $slug) {
            if (!str_contains($indexMarkup, '/products.html?category=' . rawurlencode($slug) . '#category-' . $slug)) {
                $issues[] = 'header product nav must expose generated product category links: ' . str_replace(DIRECTORY_SEPARATOR, '/', $indexRelative) . ' [' . $slug . ']';
            }
        }
    }
}

foreach ($enabledLanguages as $languageCode) {
    $indexRelative = $languageCode . DIRECTORY_SEPARATOR . 'index.html';
    $indexPath = $projectRoot . DIRECTORY_SEPARATOR . $indexRelative;
    $indexMarkup = is_file($indexPath) ? (string) file_get_contents($indexPath) : '';

    foreach (['solutions', 'news', 'cases'] as $routeKey) {
        $listingRelative = $languageCode . DIRECTORY_SEPARATOR . $routeKey . '.html';
        $listingPath = $projectRoot . DIRECTORY_SEPARATOR . $listingRelative;
        if (!is_file($listingPath) || $indexMarkup === '') {
            continue;
        }

        $listingMarkup = (string) file_get_contents($listingPath);
        $listingSlugs = $extractListingFilterSlugs($listingMarkup, $routeKey);
        foreach (array_slice($listingSlugs, 0, 2) as $slug) {
            if (!str_contains($indexMarkup, '/' . $languageCode . '/' . $routeKey . '.html?category=' . rawurlencode($slug) . '#category-' . $slug)) {
                $issues[] = 'header nav must expose generated listing category links: ' . str_replace(DIRECTORY_SEPARATOR, '/', $indexRelative) . ' [' . $routeKey . ':' . $slug . ']';
            }
        }
    }
}

if ($databaseAvailable) {
    $siteService = new \app\service\content\PublicSiteService();
    foreach ($enabledLanguages as $languageCode) {
        $indexRelative = $languageCode . DIRECTORY_SEPARATOR . 'index.html';
        $indexPath = $projectRoot . DIRECTORY_SEPARATOR . $indexRelative;
        $indexMarkup = is_file($indexPath) ? (string) file_get_contents($indexPath) : '';
        $siteConfig = $siteService->site();
        $logoUrl = trim((string) ($siteConfig['logo_url'] ?? ''));
        if ($logoUrl !== '' && $indexMarkup !== '' && !str_contains($indexMarkup, $logoUrl)) {
            $issues[] = 'header/footer logo must use the current site settings logo_url: ' . str_replace(DIRECTORY_SEPARATOR, '/', $indexRelative) . ' [' . $logoUrl . ']';
        }

        $newsScopes = array_unique($collectCategoryScopes((array) ($siteService->newsList($languageCode, 1, 50)['categories'] ?? [])));
        foreach ($newsScopes as $scope) {
            if (!in_array($scope, ['news', 'all'], true)) {
                $issues[] = 'news list categories must only contain news/all scopes: ' . $languageCode;
                break;
            }
        }

        $caseScopes = array_unique($collectCategoryScopes((array) ($siteService->caseList($languageCode, 1, 50)['categories'] ?? [])));
        foreach ($caseScopes as $scope) {
            if (!in_array($scope, ['case', 'all'], true)) {
                $issues[] = 'case list categories must only contain case/all scopes: ' . $languageCode;
                break;
            }
        }

        $contactItems = array_values(array_filter((array) ($siteService->contact($languageCode)['items'] ?? []), static fn (mixed $item): bool => is_array($item)));
        foreach ($contactItems as $item) {
            $scope = strtolower(trim((string) ($item['display_scope'] ?? '')));
            $href = $normalizeContactHref((string) ($item['field_key'] ?? ''), (string) ($item['value'] ?? ''), $languageCode);
            if ($href === null || $indexMarkup === '') {
                continue;
            }

            if ($scope === 'footer' && !str_contains($indexMarkup, $href)) {
                $issues[] = 'footer must expose Contact Center footer item href: ' . str_replace(DIRECTORY_SEPARATOR, '/', $indexRelative) . ' [' . $href . ']';
            }

            if ($scope === 'floating_contact' && !str_contains($indexMarkup, $href)) {
                $issues[] = 'floating contact must expose Contact Center floating item href: ' . str_replace(DIRECTORY_SEPARATOR, '/', $indexRelative) . ' [' . $href . ']';
            }
        }

        $featuredProducts = array_values(array_filter((array) ($siteService->products($languageCode, 1, 5)['items'] ?? []), static fn (mixed $item): bool => is_array($item)));
        foreach (array_slice($featuredProducts, 0, 3) as $product) {
            $slug = trim((string) ($product['slug'] ?? ''));
            if ($slug === '' || $indexMarkup === '') {
                continue;
            }

            if (!str_contains($indexMarkup, '/' . $languageCode . '/products/' . $slug . '.html')) {
                $issues[] = 'footer popular products must expose published product links: ' . str_replace(DIRECTORY_SEPARATOR, '/', $indexRelative) . ' [' . $slug . ']';
            }
        }
    }
}

foreach ($enabledLanguages as $languageCode) {
    $prefix = '/' . $languageCode . '/';
    $aboutRelative = $languageCode . DIRECTORY_SEPARATOR . 'about.html';
    $aboutPath = $projectRoot . DIRECTORY_SEPARATOR . $aboutRelative;

    if (!is_file($aboutPath)) {
        continue;
    }

    $aboutMarkup = (string) file_get_contents($aboutPath);

    if (!str_contains($aboutMarkup, 'href="' . $prefix . 'about.html#about"')) {
        $issues[] = 'about page shell must expose the about anchor navigation link: ' . str_replace(DIRECTORY_SEPARATOR, '/', $aboutRelative);
    }

    if (!str_contains($aboutMarkup, 'href="' . $prefix . 'about.html#contact"')) {
        $issues[] = 'about page shell must expose the contact anchor navigation link: ' . str_replace(DIRECTORY_SEPARATOR, '/', $aboutRelative);
    }

    if (!str_contains($aboutMarkup, 'href="' . $prefix . 'cases.html"')) {
        $issues[] = 'about page shell must expose the cases navigation link: ' . str_replace(DIRECTORY_SEPARATOR, '/', $aboutRelative);
    }

    if (!str_contains($aboutMarkup, 'id="about"')) {
        $issues[] = 'about page must expose the shared about anchor target: ' . str_replace(DIRECTORY_SEPARATOR, '/', $aboutRelative);
    }

    if (!str_contains($aboutMarkup, 'id="contact"')) {
        $issues[] = 'about page must expose the shared contact anchor target: ' . str_replace(DIRECTORY_SEPARATOR, '/', $aboutRelative);
    }
}

if ($databaseAvailable) {
    $detailPages = [
        'products' => array_values(array_filter(
            $fetchRows($pdo, "SELECT slug, name_zh AS title FROM products WHERE publish_status = 'published' ORDER BY id ASC"),
            static fn (array $row): bool => $isRenderableRecord('product', (string) ($row['slug'] ?? ''), (string) ($row['title'] ?? ''))
        )),
        'solutions' => array_values(array_filter(
            $fetchRows($pdo, "SELECT slug, name_zh AS title FROM solutions WHERE publish_status = 'published' ORDER BY id ASC"),
            static fn (array $row): bool => $isRenderableRecord('solution', (string) ($row['slug'] ?? ''), (string) ($row['title'] ?? ''))
        )),
        'news' => array_values(array_filter(
            $fetchRows($pdo, "SELECT slug, title_zh AS title FROM articles WHERE publish_status = 'published' AND content_type = 'news' ORDER BY id ASC"),
            static fn (array $row): bool => $isRenderableRecord('news', (string) ($row['slug'] ?? ''), (string) ($row['title'] ?? ''))
        )),
        'cases' => array_values(array_filter(
            $fetchRows($pdo, "SELECT slug, title_zh AS title FROM articles WHERE publish_status = 'published' AND content_type = 'case' ORDER BY id ASC"),
            static fn (array $row): bool => $isRenderableRecord('case', (string) ($row['slug'] ?? ''), (string) ($row['title'] ?? ''))
        )),
        'pages' => array_values(array_filter(
            $fetchRows($pdo, "SELECT slug, title_zh AS title FROM pages WHERE publish_status = 'published' ORDER BY id ASC"),
            static fn (array $row): bool => $isRenderableRecord('page', (string) ($row['slug'] ?? ''), (string) ($row['title'] ?? ''))
        )),
    ];

    foreach ($enabledLanguages as $languageCode) {
        foreach ($detailPages as $section => $records) {
            foreach ($records as $record) {
                $slug = trim((string) ($record['slug'] ?? ''));
                if ($slug === '') {
                    continue;
                }

                $relative = $languageCode . DIRECTORY_SEPARATOR . $section . DIRECTORY_SEPARATOR . $slug . '.html';
                $path = $projectRoot . DIRECTORY_SEPARATOR . $relative;
                if (!is_file($path)) {
                    $issues[] = 'missing generated detail page: ' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
                }
            }
        }
    }
}

$detailChecks = [
    'en/products/cake-depositor.html' => ['data-public-content-detail="1"', 'data-detail-module="product-facts"'],
    'en/solutions/cake-line.html' => ['data-public-content-detail="1"', 'data-detail-module="solution-flow"', 'data-detail-module="solution-capacity"'],
    'en/news/germany-bakery-expo.html' => ['data-public-content-detail="1"', 'data-detail-module="news-meta"'],
    'en/cases/uae-cake-project.html' => ['data-public-content-detail="1"', 'data-detail-module="case-meta"', 'data-detail-related="1"', 'data-related-products="1"', 'data-related-solutions="1"'],
];

foreach ($detailChecks as $relative => $needles) {
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $markup = is_file($path) ? (string) file_get_contents($path) : '';
    foreach ($needles as $needle) {
        if (!str_contains($markup, $needle)) {
            $issues[] = 'generated detail page missing marker ' . $needle . ': ' . $relative;
        }
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Site build output validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

$message = 'Site build output validation passed.';
if ($skippedChecks !== []) {
    $message .= ' Skipped: ' . implode(', ', $skippedChecks) . '.';
}

fwrite(STDOUT, $message . "\n");
