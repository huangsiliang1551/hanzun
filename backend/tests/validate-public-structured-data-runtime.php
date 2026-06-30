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
            $issues[] = 'generated page missing expected structured data marker: ' . $relativePath . ' [' . $needle . ']';
        }
    }
};

$assertContains('zh/index.html', [
    '<script type="application/ld+json">',
    '"@type":"Organization"',
    '"@type":"WebSite"',
    '"url":"https://bagelsmachinery.com/zh/index.html"',
]);

$assertContains('zh/products/cake-depositor.html', [
    '<script type="application/ld+json">',
    '"@type":"BreadcrumbList"',
    '"@type":"Product"',
    '"sku":"HZ-CAKE-001"',
]);

$assertContains('zh/news/germany-bakery-expo.html', [
    '<script type="application/ld+json">',
    '"@type":"BreadcrumbList"',
    '"@type":"Article"',
    '"datePublished":"2026-06-14"',
]);

if ($issues !== []) {
    fwrite(STDERR, "Public structured data validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Public structured data validation passed.\n");
