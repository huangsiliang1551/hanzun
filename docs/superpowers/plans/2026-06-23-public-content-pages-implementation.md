# Public Content Pages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a stable, generator-driven frontend page system for products, solutions, news, and cases, with one shared list/detail skeleton and type-specific modules rendered into final static HTML.

**Architecture:** Keep the public shell and static publisher as the single rendering entry. Enrich public content payloads in `PublicSiteService`, then render unified list/detail pages in `StaticPublisher` with small content-type variants instead of four independent templates. Add dedicated public content CSS and runtime validation so every frontend change still ends with a full-site build and final HTML verification.

**Tech Stack:** PHP 8 backend services, static HTML generator, existing public runtime JS/CSS, local runtime validators, Vite-built admin app unchanged for this feature.

---

## File Structure

**Modify**

- `backend/app/service/content/PublicSiteService.php`
  - Normalize and expose stable frontend-facing fields for product, solution, news, and case records.
- `backend/app/service/StaticPublisher.php`
  - Replace ad-hoc listing/detail rendering with unified content page render helpers and per-type modules.
- `backend/tests/validate-site-build-output-runtime.php`
  - Extend generated HTML checks to cover list/detail-specific markers and content-specific modules.
- `assets/js/future.js`
  - Only if needed for list-page enhancement markers or small client-side active-state handling.

**Create**

- `assets/css/public-content-pages.css`
  - Dedicated list/detail styles for the four content types.
- `backend/tests/validate-public-content-pages-runtime.php`
  - Runtime validator for frontend-facing content payload shape and generated-detail expectations.

**Do not modify unless required by implementation findings**

- `index.template.html`
  - Only touch if the current template lacks slots needed by the shared content page structure.
- `assets/css/future.min.css`
  - Avoid editing minified legacy CSS; prefer additive override CSS.

---

### Task 1: Normalize Public Content Payloads

**Files:**
- Modify: `backend/app/service/content/PublicSiteService.php`
- Create: `backend/tests/validate-public-content-pages-runtime.php`
- Test: `backend/tests/validate-public-content-pages-runtime.php`

- [ ] **Step 1: Write the failing runtime validator**

```php
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
$issues = [];

$productList = $service->products('en', 1, 12);
$solutionList = $service->solutions('en', 1, 12);
$newsList = $service->newsList('en', 1, 12);
$caseList = $service->caseList('en', 1, 12);

$firstProduct = $productList['items'][0] ?? [];
$firstSolution = $solutionList['items'][0] ?? [];
$firstNews = $newsList['items'][0] ?? [];
$firstCase = $caseList['items'][0] ?? [];

if (!array_key_exists('cover_image_url', $firstProduct)) $issues[] = 'product card payload must expose cover_image_url';
if (!array_key_exists('sku', $firstProduct)) $issues[] = 'product payload must expose sku';
if (!array_key_exists('business_status', $firstProduct)) $issues[] = 'product payload must expose business_status';
if (!array_key_exists('flow_text_zh', $firstSolution) && !array_key_exists('flow_text', $firstSolution)) $issues[] = 'solution payload must expose flow text';
if (!array_key_exists('capacity_text_zh', $firstSolution) && !array_key_exists('capacity_text', $firstSolution)) $issues[] = 'solution payload must expose capacity text';
if (!array_key_exists('publish_time', $firstNews)) $issues[] = 'news payload must expose publish_time';
if (!array_key_exists('country_code', $firstCase)) $issues[] = 'case payload must expose country_code';
if (!array_key_exists('related_solution_ids', $firstCase)) $issues[] = 'case payload must expose related_solution_ids';
if (!array_key_exists('related_product_ids', $firstCase)) $issues[] = 'case payload must expose related_product_ids';

if ($issues !== []) {
    fwrite(STDERR, implode(PHP_EOL, $issues) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Public content payload validation passed.\n");
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
php backend/tests/validate-public-content-pages-runtime.php
```

Expected: FAIL because at least one content type is still missing a stable field used by the new frontend templates.

- [ ] **Step 3: Implement minimal payload normalization**

Update `PublicSiteService` so localized content records expose one stable frontend contract. Use additive normalization and do not remove existing fields.

```php
private function localizeProduct(array $record, string $languageCode): array
{
    $record = $this->applyLocalizedFields(
        $record,
        $this->translationRow('product_translations', 'product_id', (int) ($record['id'] ?? 0), $languageCode, ['name', 'summary', 'content']),
        [
            'name' => ['source' => 'name_zh', 'translation' => 'name'],
            'summary' => ['source' => 'summary_zh', 'translation' => 'summary'],
            'content' => ['source' => 'content_zh', 'translation' => 'content'],
        ],
        $languageCode
    );

    $record = $this->applySeoRoute($record, 'product', $languageCode);
    $record = $this->attachContentCover($record, 'product');

    $record['card_meta'] = [
        'sku' => trim((string) ($record['sku'] ?? '')),
        'business_status' => trim((string) ($record['business_status'] ?? '')),
    ];

    return $record;
}
```

```php
private function localizeCase(array $record, string $languageCode): array
{
    $record = $this->applyLocalizedFields(
        $record,
        $this->translationRow('case_translations', 'case_id', (int) ($record['id'] ?? 0), $languageCode, ['title', 'summary', 'content']),
        [
            'title' => ['source' => 'title_zh', 'translation' => 'title'],
            'summary' => ['source' => 'summary_zh', 'translation' => 'summary'],
            'content' => ['source' => 'content_zh', 'translation' => 'content'],
        ],
        $languageCode
    );

    $record = $this->applySeoRoute($record, 'case', $languageCode);
    $record = $this->attachContentCover($record, 'case');
    $record['related_solution_ids'] = $this->decodeJsonField($record['related_solution_ids'] ?? []);
    $record['related_product_ids'] = $this->decodeJsonField($record['related_product_ids'] ?? []);

    return $record;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run:

```powershell
php backend/tests/validate-public-content-pages-runtime.php
```

Expected: PASS with `Public content payload validation passed.`

- [ ] **Step 5: Commit**

```bash
git add backend/app/service/content/PublicSiteService.php backend/tests/validate-public-content-pages-runtime.php
git commit -m "feat: normalize public content payloads for frontend templates"
```

---

### Task 2: Build One Shared Listing Renderer

**Files:**
- Modify: `backend/app/service/StaticPublisher.php`
- Create: `assets/css/public-content-pages.css`
- Test: `backend/tests/validate-site-build-output-runtime.php`

- [ ] **Step 1: Write the failing generated-HTML assertions for list pages**

Add checks to `backend/tests/validate-site-build-output-runtime.php` for these pages:

```php
$contentListingPages = [
    'zh/products.html',
    'zh/solutions.html',
    'zh/news.html',
    'zh/cases.html',
    'en/products.html',
    'en/solutions.html',
    'en/news.html',
    'en/cases.html',
];

foreach ($contentListingPages as $relative) {
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $markup = is_file($path) ? (string) file_get_contents($path) : '';

    if (!str_contains($markup, 'data-public-content-listing="1"')) {
        $issues[] = 'listing page must expose shared content listing marker: ' . $relative;
    }

    if (!str_contains($markup, 'data-public-card-type=')) {
        $issues[] = 'listing page must expose typed content cards: ' . $relative;
    }
}
```

- [ ] **Step 2: Run full build validation to verify it fails**

Run:

```powershell
php .tmp-render-full.php
php backend/tests/validate-site-build-output-runtime.php
```

Expected: FAIL because the current list pages do not emit the new shared markers.

- [ ] **Step 3: Replace ad-hoc list rendering with a shared renderer**

Modify `StaticPublisher` to introduce a single listing renderer plus typed card helpers.

```php
private function renderContentListingPage(string $entityType, string $languageCode, string $route): string
{
    $payload = $this->cachedCollectionPayload($entityType, $languageCode);
    $items = $this->filterRenderableItems(is_array($payload['items'] ?? null) ? $payload['items'] : [], $entityType);
    $categories = $this->filterRenderableCategories(is_array($payload['categories'] ?? null) ? $payload['categories'] : []);

    $main = '<main class="public-content-page" data-public-content-listing="1">';
    $main .= $this->renderContentListingHero($entityType, $languageCode);
    $main .= $this->renderContentListingFilters($entityType, $languageCode, $categories);
    $main .= '<section class="section"><div class="container"><div class="public-content-grid">';

    foreach ($items as $item) {
        $main .= $this->renderContentListingCard($entityType, $item, $languageCode);
    }

    $main .= '</div></div></section>';
    $main .= $this->renderPublicContentBottomCta($languageCode);
    $main .= '</main>';

    return $this->renderShellPage($languageCode, $route, [
        'title' => $this->listingPageTitle($entityType, $languageCode),
        'description' => $this->listingPageDescription($entityType, $languageCode),
        'main' => $main,
        'extra_css' => ['/assets/css/public-content-pages.css?v=20260623-01'],
    ]);
}
```

```php
private function renderContentListingCard(string $entityType, array $item, string $languageCode): string
{
    $title = $this->escape((string) ($item['title'] ?? $item['name'] ?? ''));
    $summary = $this->escape((string) ($item['summary'] ?? ''));
    $href = $this->escape($this->detailRouteForEntity($entityType, (string) ($item['slug'] ?? ''), $languageCode));
    $image = $this->escape($this->assetUrl((string) ($item['cover_image_url'] ?? '')));

    return '<article class="public-content-card" data-public-card-type="' . $this->escape($entityType) . '">'
        . '<a class="public-content-card-link" href="' . $href . '">'
        . '<figure class="public-content-card-media"><img src="' . $image . '" alt="' . $title . '"></figure>'
        . '<div class="public-content-card-copy">'
        . $this->renderContentListingCardMeta($entityType, $item, $languageCode)
        . '<h3>' . $title . '</h3>'
        . '<p>' . $summary . '</p>'
        . '</div></a></article>';
}
```

- [ ] **Step 4: Run build and runtime validation to verify it passes**

Run:

```powershell
php .tmp-render-full.php
php backend/tests/validate-site-build-output-runtime.php
```

Expected: PASS and the generated listing pages contain `data-public-content-listing="1"` and typed card markers.

- [ ] **Step 5: Commit**

```bash
git add backend/app/service/StaticPublisher.php backend/tests/validate-site-build-output-runtime.php assets/css/public-content-pages.css
git commit -m "feat: unify public content listing pages"
```

---

### Task 3: Build One Shared Detail Renderer With Type Modules

**Files:**
- Modify: `backend/app/service/StaticPublisher.php`
- Modify: `backend/tests/validate-public-content-pages-runtime.php`
- Test: `backend/tests/validate-public-content-pages-runtime.php`

- [ ] **Step 1: Extend the validator with detail-page expectations**

Add detail assertions:

```php
$publisher = new \app\service\StaticPublisher();
$reflection = new ReflectionClass($publisher);
$method = $reflection->getMethod('renderDetailPage');
$method->setAccessible(true);

$productHtml = (string) $method->invoke($publisher, 'product', 'en', 'cake-depositor', '/en/products/cake-depositor.html');
$solutionHtml = (string) $method->invoke($publisher, 'solution', 'en', 'cake-line', '/en/solutions/cake-line.html');

if (!str_contains($productHtml, 'data-public-content-detail="1"')) $issues[] = 'product detail must expose shared detail marker';
if (!str_contains($productHtml, 'data-detail-module="product-facts"')) $issues[] = 'product detail must render product facts module';
if (!str_contains($solutionHtml, 'data-detail-module="solution-flow"')) $issues[] = 'solution detail must render solution flow module';
if (!str_contains($solutionHtml, 'data-detail-module="solution-capacity"')) $issues[] = 'solution detail must render solution capacity module';
```

- [ ] **Step 2: Run validator to verify it fails**

Run:

```powershell
php backend/tests/validate-public-content-pages-runtime.php
```

Expected: FAIL because the current detail renderer still uses one generic layout.

- [ ] **Step 3: Introduce a shared detail skeleton plus per-type modules**

Modify `StaticPublisher`:

```php
private function renderContentDetailPage(string $entityType, string $languageCode, string $slug, string $route): string
{
    $record = $this->cachedDetailRecord($entityType, $slug, $languageCode);

    $main = '<main class="public-content-page" data-public-content-detail="1">';
    $main .= $this->renderContentDetailHeader($entityType, $record, $languageCode);
    $main .= $this->renderContentDetailHeroMedia($entityType, $record);
    $main .= $this->renderContentDetailSummary($record);
    $main .= $this->renderContentDetailTypedModules($entityType, $record, $languageCode);
    $main .= $this->renderContentDetailBody($record);
    $main .= $this->renderContentDetailRelatedSection($entityType, $record, $languageCode);
    $main .= $this->renderPublicContentBottomCta($languageCode);
    $main .= '</main>';

    return $this->renderShellPage($languageCode, $route, [
        'title' => $this->detailPageTitle($entityType, $record, $languageCode),
        'description' => trim((string) ($record['summary'] ?? '')),
        'main' => $main,
        'extra_css' => ['/assets/css/public-content-pages.css?v=20260623-01'],
    ]);
}
```

```php
private function renderContentDetailTypedModules(string $entityType, array $record, string $languageCode): string
{
    return match ($entityType) {
        'product' => $this->renderProductFactsModule($record, $languageCode),
        'solution' => $this->renderSolutionFactsModules($record, $languageCode),
        'news' => $this->renderNewsMetaModule($record, $languageCode),
        'case' => $this->renderCaseMetaModule($record, $languageCode),
        default => '',
    };
}
```

- [ ] **Step 4: Run validator to verify it passes**

Run:

```powershell
php backend/tests/validate-public-content-pages-runtime.php
```

Expected: PASS with detail markers and typed module markers present.

- [ ] **Step 5: Commit**

```bash
git add backend/app/service/StaticPublisher.php backend/tests/validate-public-content-pages-runtime.php
git commit -m "feat: unify public content detail pages"
```

---

### Task 4: Add Related Content and Bottom Conversion Sections

**Files:**
- Modify: `backend/app/service/StaticPublisher.php`
- Modify: `backend/app/service/content/PublicSiteService.php`
- Test: `backend/tests/validate-public-content-pages-runtime.php`

- [ ] **Step 1: Add failing assertions for related sections**

```php
if (!str_contains($productHtml, 'data-detail-related="1"')) {
    $issues[] = 'product detail must expose related content section';
}

$caseHtml = (string) $method->invoke($publisher, 'case', 'en', 'sample-case-slug', '/en/cases/sample-case-slug.html');
if (!str_contains($caseHtml, 'data-related-products="1"')) {
    $issues[] = 'case detail must expose related products section';
}
if (!str_contains($caseHtml, 'data-related-solutions="1"')) {
    $issues[] = 'case detail must expose related solutions section';
}
```

- [ ] **Step 2: Run validator to verify it fails**

Run:

```powershell
php backend/tests/validate-public-content-pages-runtime.php
```

Expected: FAIL because related sections are not yet rendered as explicit frontend blocks.

- [ ] **Step 3: Implement minimal related-content rendering**

```php
private function renderContentDetailRelatedSection(string $entityType, array $record, string $languageCode): string
{
    $html = '<section class="section public-detail-related" data-detail-related="1"><div class="container">';

    if ($entityType === 'case') {
        $relatedProducts = $this->resolveRelatedProducts($record, $languageCode);
        $relatedSolutions = $this->resolveRelatedSolutions($record, $languageCode);
        $html .= $this->renderRelatedLinkGroup('product', $relatedProducts, $languageCode, 'data-related-products="1"');
        $html .= $this->renderRelatedLinkGroup('solution', $relatedSolutions, $languageCode, 'data-related-solutions="1"');
    } elseif ($entityType === 'product') {
        $html .= $this->renderSameCategoryLinks('product', $record, $languageCode);
    } elseif ($entityType === 'solution') {
        $html .= $this->renderSameCategoryLinks('solution', $record, $languageCode);
    } elseif ($entityType === 'news') {
        $html .= $this->renderSameCategoryLinks('news', $record, $languageCode);
    }

    $html .= '</div></section>';

    return $html;
}
```

- [ ] **Step 4: Run validator to verify it passes**

Run:

```powershell
php backend/tests/validate-public-content-pages-runtime.php
```

Expected: PASS with related-section markers present for supported detail types.

- [ ] **Step 5: Commit**

```bash
git add backend/app/service/StaticPublisher.php backend/app/service/content/PublicSiteService.php backend/tests/validate-public-content-pages-runtime.php
git commit -m "feat: add related content sections to public detail pages"
```

---

### Task 5: Final Build, Visual Regression Checks, and Handoff

**Files:**
- Modify: `backend/tests/validate-site-build-output-runtime.php`
- Test: `backend/tests/validate-site-build-output-runtime.php`

- [ ] **Step 1: Extend the final HTML validator with type-specific checks**

Add concrete generated-file checks:

```php
$detailChecks = [
    'en/products/cake-depositor.html' => ['data-detail-module="product-facts"', 'data-public-content-detail="1"'],
    'en/solutions/cake-line.html' => ['data-detail-module="solution-flow"', 'data-detail-module="solution-capacity"'],
    'en/news/company-news.html' => ['data-detail-module="news-meta"'],
    'en/cases/customer-case.html' => ['data-detail-module="case-meta"', 'data-related-products="1"', 'data-related-solutions="1"'],
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
```

- [ ] **Step 2: Run the full generation flow**

Run:

```powershell
php .tmp-render-full.php
```

Expected: `Full site build completed. job=<n> rendered=<n> failed=0`

- [ ] **Step 3: Run all runtime validators**

Run:

```powershell
php backend/tests/validate-public-content-pages-runtime.php
php backend/tests/validate-site-build-output-runtime.php
```

Expected: both PASS.

- [ ] **Step 4: Spot-check the final HTML and browser routes**

Check these outputs:

```powershell
Select-String -Path 'en/products.html' -Pattern 'data-public-content-listing=\"1\"'
Select-String -Path 'en/solutions/cake-line.html' -Pattern 'data-detail-module=\"solution-flow\"'
Select-String -Path 'en/cases/*.html' -Pattern 'data-related-products=\"1\"'
```

Browser regression targets:

- `http://127.0.0.1:8091/zh/products.html`
- `http://127.0.0.1:8091/en/solutions.html`
- `http://127.0.0.1:8091/en/news.html`
- `http://127.0.0.1:8091/en/cases.html`
- one product detail page
- one solution detail page
- one news detail page
- one case detail page

Expected: shared shell intact, list/detail layouts present, AI chat and floating contact still available.

- [ ] **Step 5: Commit**

```bash
git add backend/tests/validate-site-build-output-runtime.php assets/css/public-content-pages.css backend/tests/validate-public-content-pages-runtime.php backend/app/service/StaticPublisher.php backend/app/service/content/PublicSiteService.php
git commit -m "feat: deliver unified public content page system"
```

---

## Spec Coverage Check

- Unified list/detail template system: covered by Tasks 2 and 3.
- Type-specific differentiation for product, solution, news, case: covered by Tasks 1, 3, and 4.
- Reuse backend fields only: covered by Task 1 field normalization.
- Generator-only final output path: covered by Tasks 2, 3, and 5.
- Final HTML rebuild and verification: covered by Task 5.

No uncovered spec requirements remain.

## Placeholder Scan

- No `TBD`, `TODO`, or deferred “handle later” steps remain.
- Every task includes exact file paths, commands, and concrete test/implementation snippets.

## Type Consistency Check

- Shared markers use one naming scheme:
  - `data-public-content-listing="1"`
  - `data-public-content-detail="1"`
  - `data-detail-module="..."`
  - `data-detail-related="1"`
- Route names stay aligned with current static publisher paths:
  - `products`
  - `solutions`
  - `news`
  - `cases`

---

Plan complete and saved to `docs/superpowers/plans/2026-06-23-public-content-pages-implementation.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
