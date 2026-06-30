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
            $issues[] = 'generated shell missing expected footer contact content: ' . $relativePath . ' [' . $needle . ']';
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
            $issues[] = 'generated floating contact should not expose footer-only channel: ' . $relativePath . ' [' . $needle . ']';
        }
    }
};

foreach ([
    'zh/index.html',
    'en/index.html',
] as $relativePath) {
    $assertContains($relativePath, [
        'data-footer-contact-list',
        'footer-brand-rows',
        'footer-brand-row footer-brand-row-email',
        'footer-brand-row footer-brand-row-phone-whatsapp',
        'footer-brand-row footer-brand-row-address',
        'footer-brand-socials',
        'footer-brand-social linkedin',
        'footer-brand-social youtube',
        'footer-brand-social line',
        'icon-linkedin-color.svg',
        'icon-youtube-color.svg',
        'icon-line-color.svg',
    ]);
}

foreach ([
    'zh/index.html',
    'en/index.html',
] as $relativePath) {
    $assertNotContains($relativePath, [
        'class="float-link linkedin"',
        'class="float-link youtube"',
        'class="float-link line"',
    ]);
}

if ($issues !== []) {
    fwrite(STDERR, "Footer contact shell validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Footer contact shell validation passed.\n");
