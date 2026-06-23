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
            $issues[] = 'generated detail page missing expected presentation marker: ' . $relativePath . ' [' . $needle . ']';
        }
    }
};

$assertAnyContains = static function (string $relativePath, array $needles, string $label) use (&$issues, $read): void {
    $markup = $read($relativePath);
    if ($markup === '') {
        return;
    }

    foreach ($needles as $needle) {
        if (str_contains($markup, $needle)) {
            return;
        }
    }

    $issues[] = 'generated detail page missing any expected presentation content for ' . $label . ': ' . $relativePath;
};

$detailPages = [
    'zh/products/cake-batter-mixer.html',
    'en/products/cake-batter-mixer.html',
    'zh/solutions/bread-line.html',
    'en/solutions/bread-line.html',
    'zh/news/indonesia-bakery-demo.html',
    'en/news/indonesia-bakery-demo.html',
    'zh/cases/mexico-cupcake-project.html',
    'en/cases/mexico-cupcake-project.html',
];

foreach ($detailPages as $relativePath) {
    $assertContains($relativePath, [
        'data-detail-hero-meta="1"',
        'data-detail-actions="1"',
        'data-detail-highlights="1"',
    ]);
}

$assertContains('zh/products/cake-batter-mixer.html', ['data-detail-related="1"']);
$assertContains('en/products/cake-batter-mixer.html', ['data-detail-related="1"']);
$assertContains('zh/solutions/bread-line.html', ['data-detail-related="1"']);
$assertContains('en/solutions/bread-line.html', ['data-detail-related="1"']);

$assertAnyContains(
    'zh/products/cake-batter-mixer.html',
    ['蛋糕生产设备', '烘焙搅拌机'],
    'product keyword chips'
);
$assertAnyContains(
    'en/products/cake-batter-mixer.html',
    ['Cake production equipment', 'bakery mixer', 'Cake batter mixer'],
    'product keyword chips'
);
$assertAnyContains(
    'zh/cases/mexico-cupcake-project.html',
    ['纸杯蛋糕', '中央工厂', '出口'],
    'case tags'
);
$assertAnyContains(
    'en/cases/mexico-cupcake-project.html',
    ['cupcake', 'central factory', 'export'],
    'case tags'
);

if ($issues !== []) {
    fwrite(STDERR, "Public detail presentation validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Public detail presentation validation passed.\n");
