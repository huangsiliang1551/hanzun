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
            $issues[] = 'generated detail page missing expected SEO/detail marker: ' . $relativePath . ' [' . $needle . ']';
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
            $issues[] = 'generated detail page still contains fallback SEO value: ' . $relativePath . ' [' . $needle . ']';
        }
    }
};

$assertContains('zh/products/cake-depositor.html', [
    'data-detail-breadcrumb="1"',
    '<meta property="og:image" content="/assets/images/home/equipment-forming-module.jpg">',
]);

$assertContains('en/solutions/cake-line.html', [
    'data-detail-breadcrumb="1"',
    '<meta property="og:image" content="/assets/images/home/equipment-integrated-line.jpg">',
]);

$assertContains('zh/news/germany-bakery-expo.html', [
    'data-detail-breadcrumb="1"',
    '<meta property="og:image" content="/assets/images/home/news-real-expo-hall.jpg">',
    '<meta property="og:type" content="article">',
]);

$assertContains('zh/cases/uae-cake-project.html', [
    'data-detail-breadcrumb="1"',
    '<meta property="og:image" content="/assets/images/home/news-real-handshake-team.jpg">',
    '<meta property="og:type" content="article">',
]);

$assertNotContains('zh/products/cake-depositor.html', [
    '<meta property="og:image" content="/assets/images/common/logo-110.png">',
]);
$assertNotContains('zh/news/germany-bakery-expo.html', [
    '<meta property="og:image" content="/assets/images/common/logo-110.png">',
]);

if ($issues !== []) {
    fwrite(STDERR, "Public detail SEO validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Public detail SEO validation passed.\n");
