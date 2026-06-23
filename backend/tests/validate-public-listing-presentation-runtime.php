<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$issues = [];

$read = static function (string $relativePath) use ($projectRoot, &$issues): string {
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        $issues[] = 'missing generated file: ' . $relativePath;

        return '';
    }

    $content = file_get_contents($path);
    if (!is_string($content)) {
        $issues[] = 'failed to read generated file: ' . $relativePath;

        return '';
    }

    return $content;
};

$assertContains = static function (string $relativePath, array $needles) use (&$issues, $read): void {
    $markup = $read($relativePath);
    if ($markup === '') {
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($markup, $needle)) {
            $issues[] = 'generated listing page missing expected marker: ' . $relativePath . ' [' . $needle . ']';
        }
    }
};

$assertNotContains = static function (string $relativePath, array $needles) use (&$issues, $read): void {
    $markup = $read($relativePath);
    if ($markup === '') {
        return;
    }

    foreach ($needles as $needle) {
        if (str_contains($markup, $needle)) {
            $issues[] = 'generated listing page still contains generic copy: ' . $relativePath . ' [' . $needle . ']';
        }
    }
};

$listingPages = [
    'zh/products.html',
    'zh/solutions.html',
    'zh/news.html',
    'zh/cases.html',
    'en/products.html',
    'en/solutions.html',
    'en/news.html',
    'en/cases.html',
];

foreach ($listingPages as $relativePath) {
    $assertContains($relativePath, [
        'data-public-content-listing="1"',
        'data-public-listing-grid="1"',
        'data-listing-intro="1"',
        'data-listing-summary="1"',
    ]);
}

$assertNotContains('zh/products.html', ['<p>HANZUN 产品</p>']);
$assertNotContains('zh/solutions.html', ['<p>HANZUN 方案</p>']);
$assertNotContains('zh/news.html', ['<p>HANZUN 新闻</p>']);
$assertNotContains('zh/cases.html', ['<p>HANZUN 案例</p>']);

$assertNotContains('zh/products.html', ['<title>上海涵尊实业有限公司 | 烘焙机械研发定制与整线交付</title>']);
$assertNotContains('zh/solutions.html', ['<title>上海涵尊实业有限公司 | 烘焙机械研发定制与整线交付</title>']);
$assertNotContains('zh/news.html', ['<title>上海涵尊实业有限公司 | 烘焙机械研发定制与整线交付</title>']);
$assertNotContains('zh/cases.html', ['<title>上海涵尊实业有限公司 | 烘焙机械研发定制与整线交付</title>']);

$assertContains('zh/products.html', ['<title>产品 - ']);
$assertContains('zh/solutions.html', ['<title>方案 - ']);
$assertContains('zh/news.html', ['<title>新闻 - ']);
$assertContains('zh/cases.html', ['<title>案例 - ']);
$assertContains('zh/products.html', ['<meta property="og:image" content="/assets/images/home/equipment-forming-module.jpg">']);
$assertContains('zh/solutions.html', ['<meta property="og:image" content="/assets/images/home/equipment-integrated-line.jpg">']);
$assertContains('zh/news.html', ['<meta property="og:image" content="/assets/images/home/news-real-expo-hall.jpg">']);
$assertContains('zh/cases.html', ['<meta property="og:image" content="/assets/images/home/news-real-handshake-team.jpg">']);
$assertContains('zh/index.html', ['<meta property="og:image" content="/assets/images/home/hero-enterprise-showcase.png">']);

$assertNotContains('en/products.html', ['<p>HANZUN Products</p>']);
$assertNotContains('en/solutions.html', ['<p>HANZUN Solutions</p>']);
$assertNotContains('en/news.html', ['<p>HANZUN News</p>']);
$assertNotContains('en/cases.html', ['<p>HANZUN Cases</p>']);

$assertNotContains('en/products.html', ['<title>上海涵尊实业有限公司 | 烘焙机械研发定制与整线交付</title>']);
$assertNotContains('en/solutions.html', ['<title>上海涵尊实业有限公司 | 烘焙机械研发定制与整线交付</title>']);
$assertNotContains('en/news.html', ['<title>上海涵尊实业有限公司 | 烘焙机械研发定制与整线交付</title>']);
$assertNotContains('en/cases.html', ['<title>上海涵尊实业有限公司 | 烘焙机械研发定制与整线交付</title>']);

$assertContains('en/products.html', ['<title>Products - ']);
$assertContains('en/solutions.html', ['<title>Solutions - ']);
$assertContains('en/news.html', ['<title>News - ']);
$assertContains('en/cases.html', ['<title>Cases - ']);
$assertContains('en/products.html', ['<meta property="og:image" content="/assets/images/home/equipment-forming-module.jpg">']);
$assertContains('en/solutions.html', ['<meta property="og:image" content="/assets/images/home/equipment-integrated-line.jpg">']);
$assertContains('en/news.html', ['<meta property="og:image" content="/assets/images/home/news-real-expo-hall.jpg">']);
$assertContains('en/cases.html', ['<meta property="og:image" content="/assets/images/home/news-real-handshake-team.jpg">']);
$assertContains('en/index.html', ['<meta property="og:image" content="/assets/images/home/hero-enterprise-showcase.png">']);

if ($issues !== []) {
    fwrite(STDERR, "Public listing presentation validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Public listing presentation validation passed.\n");
