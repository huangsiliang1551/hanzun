<?php

declare(strict_types=1);

$sourcePath = dirname(__DIR__, 2) . '/admin-v2/src/pages/TasksPage.jsx';

if (!is_file($sourcePath)) {
    fwrite(STDERR, "Task center source missing: {$sourcePath}\n");
    exit(1);
}

$source = file_get_contents($sourcePath);
if (!is_string($source) || $source === '') {
    fwrite(STDERR, "Task center source is empty: {$sourcePath}\n");
    exit(1);
}

$issues = [];

if (!str_contains($source, 'const { currentJob')) {
    $issues[] = 'task center page must read currentJob from site-build context';
}

if (!str_contains($source, 'openFullBuild')) {
    $issues[] = 'task center page must read openFullBuild from site-build context';
}

if (!str_contains($source, '全站重新生成') && !str_contains($source, '\\u5168\\u7ad9\\u91cd\\u65b0\\u751f\\u6210')) {
    $issues[] = 'task center page must render a visible full-build button';
}

if ($issues !== []) {
    fwrite(STDERR, "Task center source full-build entry validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, " - {$issue}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Task center source full-build entry validation passed.\n");
