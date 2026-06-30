<?php

declare(strict_types=1);

$path = dirname(__DIR__, 2) . '/backend/app/service/StaticPublisher.php';
if (!is_file($path)) {
    fwrite(STDERR, "StaticPublisher missing: {$path}\n");
    exit(1);
}

$source = file_get_contents($path);
if (!is_string($source) || $source === '') {
    fwrite(STDERR, "StaticPublisher source is empty: {$path}\n");
    exit(1);
}

$issues = [];

if (str_contains($source, "progressPercent(\$index")) {
    $issues[] = 'site build progress must not advance before a page is actually completed';
}

if (!str_contains($source, 'private function runningProgressPercent')) {
    $issues[] = 'site build progress must expose a dedicated running-progress calculation';
}

if (!str_contains($source, "'progress_percent' => 99")) {
    $issues[] = 'full build deploy phase must keep progress near completion without falling backward';
}

if ($issues !== []) {
    fwrite(STDERR, "Site build progress source validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, ' - ' . $issue . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "Site build progress source validation passed.\n");
