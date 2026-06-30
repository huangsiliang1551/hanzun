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

$assertFilesExist = static function (array $relativePaths) use ($projectRoot, &$issues): void {
    foreach ($relativePaths as $relativePath) {
        $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($path)) {
            $issues[] = 'missing required frontend asset: ' . $relativePath;
        }
    }
};

$assertMissing = static function (string $relativePath, array $needles) use (&$issues, $read): void {
    $markup = $read($relativePath);
    if ($markup === '') {
        return;
    }

    foreach ($needles as $needle) {
        if (str_contains($markup, $needle)) {
            $issues[] = 'generated page still exposes leaked placeholder or mixed-language text: ' . $relativePath . ' [' . $needle . ']';
        }
    }
};

$assertContains = static function (string $relativePath, array $needles) use (&$issues, $read): void {
    $markup = $read($relativePath);
    if ($markup === '') {
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($markup, $needle)) {
            $issues[] = 'generated page missing expected localized content: ' . $relativePath . ' [' . $needle . ']';
        }
    }
};

$sharedLeakStrings = [
    'Important announcement placeholder',
    'No matching content available yet.',
    'No related content available yet.',
    'Details are being organized. Use the contact entry to request the full proposal.',
    '暂无匹配到相关内容。',
    '暂无关联内容。',
    '内容整理中，欢迎通过联系入口获取完整方案。',
];

$assertFilesExist([
    'assets/images/flags/de.svg',
    'assets/images/flags/ae.svg',
    'assets/images/flags/id.svg',
    'assets/images/flags/th.svg',
    'assets/images/flags/br.svg',
    'assets/images/flags/mx.svg',
]);

$zhLeakStrings = array_merge($sharedLeakStrings, [
    '>Cake Lines<',
    '>Lead Time<',
    '>Line Quotation<',
    'data-zh-placeholder="Enter your question"',
    '>View Case<',
    '>View Line<',
    '>Read Article<',
    '>Product Details<',
    '>Delivery Cases<',
    '>Solution Library<',
    '>Latest Updates<',
    '>Requirement Review<',
    '>Production<',
    '>Commissioning &amp; Delivery<',
    '>After-Sales Support<',
    'amy@example.com',
    '+86-10000000000',
    'sales-amy-zhang.jpg',
    'name="name" placeholder="Name"',
    'name="email" placeholder="Email"',
    'name="message" rows="3" placeholder="Cake / Bread / Filling / Cutting / Food Processing"',
    'aria-label="灞曞紑产品"',
    'aria-label="灞曞紑方案"',
    'aria-label="灞曞紑新闻"',
    'aria-label="灞曞紑案例"',
]);

$enLeakStrings = array_merge($sharedLeakStrings, [
    '>全自动蛋糕生产线<',
    '>邮箱<',
    '>电话<',
    '>需求沟通<',
    '>生产制造<',
    '>调试交付<',
    '>售后支持<',
]);

$assertMissing('zh/index.html', $zhLeakStrings);
$assertMissing('zh/about.html', $zhLeakStrings);
$assertMissing('zh/products.html', $zhLeakStrings);
$assertMissing('zh/solutions.html', $zhLeakStrings);
$assertMissing('zh/news.html', $zhLeakStrings);
$assertMissing('zh/cases.html', $zhLeakStrings);
$assertMissing('zh/products/cake-depositor.html', $sharedLeakStrings);
$assertMissing('zh/solutions/cake-line.html', $sharedLeakStrings);
$assertMissing('zh/news/germany-bakery-expo.html', $sharedLeakStrings);
$assertMissing('zh/pages/cake-line-landing.html', $sharedLeakStrings);
$assertMissing('zh/pages/about-us.html', $sharedLeakStrings);

$assertMissing('en/index.html', $enLeakStrings);
$assertMissing('en/about.html', $enLeakStrings);
$assertMissing('en/products/cake-depositor.html', $sharedLeakStrings);
$assertMissing('en/solutions/cake-line.html', $sharedLeakStrings);
$assertMissing('en/news/germany-bakery-expo.html', $sharedLeakStrings);
$assertMissing('en/pages/about-us.html', $sharedLeakStrings);
$assertMissing('en/pages/cake-line-landing.html', $sharedLeakStrings);
$assertMissing('zh/pages/about-us.html', ['data-detail-related="1"></section>']);
$assertMissing('en/pages/about-us.html', ['data-detail-related="1"></section>']);
$assertMissing('zh/pages/cake-line-landing.html', ['data-detail-related="1"></section>']);
$assertMissing('en/pages/cake-line-landing.html', ['data-detail-related="1"></section>']);

$assertContains('zh/index.html', [
    '>蛋糕生产线<',
    'placeholder="请输入您的问题"',
    'placeholder="联系人"',
    'placeholder="邮箱"',
    'placeholder="蛋糕 / 面包 / 夹心 / 切割 / 食品加工"',
]);
$assertContains('zh/about.html', [
    '>需求沟通<',
    '>生产制造<',
    '>调试交付<',
    '>售后支持<',
]);
$assertContains('zh/pages/about-us.html', [
    '>公司介绍<',
]);
$assertContains('zh/products.html', ['>产品<']);
$assertContains('zh/solutions.html', ['>方案<']);
$assertContains('zh/news.html', ['>新闻<']);
$assertContains('zh/cases.html', ['>案例<']);

$assertContains('en/index.html', [
    '>Automatic Cake Lines<',
    '>Email<',
    'placeholder="Enter your question"',
    'placeholder="Contact Name"',
    'placeholder="Email"',
    'placeholder="Cake / Bread / Filling / Cutting / Food Processing"',
]);

$assertMissing('zh/pages/about-us.html', ['<meta id="meta-description" name="description" content="">']);
$assertMissing('en/pages/about-us.html', ['<meta id="meta-description" name="description" content="">']);
$assertMissing('zh/pages/cake-line-landing.html', ['<meta id="meta-description" name="description" content="">']);
$assertMissing('en/pages/cake-line-landing.html', ['<meta id="meta-description" name="description" content="">']);
$assertContains('en/about.html', [
    '>Requirement Review<',
    '>Production<',
    '>Commissioning &amp; Delivery<',
    '>After-Sales Support<',
]);

if ($issues !== []) {
    fwrite(STDERR, "Site localization validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Site localization validation passed.\n");
