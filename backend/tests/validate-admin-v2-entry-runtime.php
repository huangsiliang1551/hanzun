<?php

declare(strict_types=1);

$path = dirname(__DIR__, 2) . '/admin-v2/index.html';
if (!is_file($path)) {
    fwrite(STDERR, "Admin v2 entry missing: {$path}\n");
    exit(1);
}

$html = file_get_contents($path);
if (!is_string($html) || $html === '') {
    fwrite(STDERR, "Admin v2 entry is empty: {$path}\n");
    exit(1);
}

$issues = [];

if (str_contains($html, 'redirectToStableAdmin')) {
    $issues[] = 'admin-v2 source entry must not be a redirect shell';
}

if (!str_contains($html, '<div id="root"></div>')) {
    $issues[] = 'admin-v2 source entry must expose the React root node';
}

if (!str_contains($html, '/src/main.jsx')) {
    $issues[] = 'admin-v2 source entry must load src/main.jsx';
}

if ($issues !== []) {
    fwrite(STDERR, "Admin v2 entry validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, " - {$issue}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Admin v2 entry validation passed.\n");
