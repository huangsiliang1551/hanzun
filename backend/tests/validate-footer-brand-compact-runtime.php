<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$issues = [];

foreach (['zh/index.html', 'en/index.html'] as $relativePath) {
    $path = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        $issues[] = 'missing generated file: ' . $relativePath;
        continue;
    }

    $markup = file_get_contents($path);
    if (!is_string($markup) || $markup === '') {
        $issues[] = 'failed to read generated file: ' . $relativePath;
        continue;
    }

    foreach ([
        'footer-brand-contacts footer-brand-contacts-compact',
        'footer-brand-contact footer-brand-contact-email',
        'footer-brand-contact footer-brand-contact-phone',
        'footer-brand-contact footer-brand-contact-address',
    ] as $needle) {
        if (!str_contains($markup, $needle)) {
            $issues[] = 'generated footer must expose compact brand-contact marker: ' . $relativePath . ' [' . $needle . ']';
        }
    }
}

$overridePath = $projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'public-shell-overrides.css';
if (!is_file($overridePath)) {
    $issues[] = 'missing public shell override stylesheet';
} else {
    $overrideCss = file_get_contents($overridePath);
    if (!is_string($overrideCss) || $overrideCss === '') {
        $issues[] = 'failed to read public shell override stylesheet';
    } else {
        foreach ([
            '--footer-contact-card-width',
            'grid-template-columns: repeat(auto-fit, var(--footer-contact-card-width))',
            'min-width: var(--footer-contact-card-width)',
            'max-width: var(--footer-contact-card-width)',
        ] as $forbidden) {
            if (str_contains($overrideCss, $forbidden)) {
                $issues[] = 'public shell overrides must not force footer contacts into fixed-width cards [' . $forbidden . ']';
            }
        }
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Footer brand compact validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Footer brand compact validation passed.\n");
