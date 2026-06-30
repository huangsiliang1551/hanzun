<?php

declare(strict_types=1);

$bundleMatches = glob(dirname(__DIR__, 2) . '/admin-app/assets/index-*.js') ?: [];
$bundlePath = '';
$bundleSize = -1;
foreach ($bundleMatches as $candidate) {
    if (!is_file($candidate)) {
        continue;
    }

    $size = filesize($candidate);
    if ($size === false || $size <= $bundleSize) {
        continue;
    }

    $bundlePath = (string) $candidate;
    $bundleSize = $size;
}

$taskChunkMatches = glob(dirname(__DIR__, 2) . '/admin-app/assets/TasksPage-*.js') ?: [];
$taskChunkPath = is_array($taskChunkMatches) && isset($taskChunkMatches[0]) ? (string) $taskChunkMatches[0] : '';
$issues = [];

if ($bundlePath !== '' && is_file($bundlePath) && $taskChunkPath !== '' && is_file($taskChunkPath)) {
    $bundle = file_get_contents($bundlePath);
    $taskChunk = file_get_contents($taskChunkPath);
    if (!is_string($bundle) || $bundle === '') {
        fwrite(STDERR, "Admin bundle is empty: {$bundlePath}\n");
        exit(1);
    }
    if (!is_string($taskChunk) || $taskChunk === '') {
        fwrite(STDERR, "Task chunk is empty: {$taskChunkPath}\n");
        exit(1);
    }

    if (str_contains($bundle, 'setInterval(w,4e3)')) {
        $issues[] = 'site build provider must poll faster than 4 seconds while jobs are active';
    }

    if (!str_contains($taskChunk, 'openFullBuild')) {
        $issues[] = 'task center entry must keep a visible full-build trigger';
    }

    if (str_contains($bundle, 'className:"site-build-topbar"') && str_contains($bundle, 'children:"鍏ㄧ珯閲嶆柊鐢熸垚"')) {
        $issues[] = 'site build modal must not duplicate the full-build button inside the progress dialog';
    }
} else {
    $providerPath = dirname(__DIR__, 2) . '/admin-v2/src/providers/SiteBuildProvider.jsx';
    $tasksPath = dirname(__DIR__, 2) . '/admin-v2/src/pages/TasksPage.jsx';

    if (!is_file($providerPath) || !is_file($tasksPath)) {
        fwrite(STDERR, "Admin source validation fallback missing: {$providerPath} or {$tasksPath}\n");
        exit(1);
    }

    $providerSource = file_get_contents($providerPath);
    $tasksSource = file_get_contents($tasksPath);
    if (!is_string($providerSource) || $providerSource === '') {
        fwrite(STDERR, "Provider source is empty: {$providerPath}\n");
        exit(1);
    }
    if (!is_string($tasksSource) || $tasksSource === '') {
        fwrite(STDERR, "Tasks source is empty: {$tasksPath}\n");
        exit(1);
    }

    if (!str_contains($providerSource, 'isActiveStatus(activeStatus) ? 800 : 5000')) {
        $issues[] = 'site build provider source must poll faster while jobs are active';
    }

    if (!str_contains($tasksSource, 'openFullBuild()} disabled={siteBuildBusy}')) {
        $issues[] = 'task center source must keep a visible full-build trigger and prevent duplicate clicks while busy';
    }

    if (str_contains($providerSource, 'className="site-build-topbar"') && str_contains($providerSource, 'onClick={() => void openFullBuild()}')) {
        $issues[] = 'site build modal source must not duplicate the full-build button inside the progress dialog';
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Admin bundle/site-build validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, " - {$issue}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Admin bundle/site-build validation passed.\n");
