<?php

declare(strict_types=1);

$bundleCandidates = glob(dirname(__DIR__, 2) . '/admin-app/assets/TasksPage-*.js') ?: [];
$bundlePath = $bundleCandidates[0] ?? '';

if ($bundlePath === '' || !is_file($bundlePath)) {
    fwrite(STDERR, "Task center bundle missing under admin-app/assets/TasksPage-*.js\n");
    exit(1);
}

$bundle = file_get_contents($bundlePath);
if (!is_string($bundle) || $bundle === '') {
    fwrite(STDERR, "Task center bundle is empty: {$bundlePath}\n");
    exit(1);
}

$issues = [];

if (!str_contains($bundle, 'const{currentJob:r,openFullBuild')) {
    $issues[] = 'task center must read openFullBuild from the shared site-build context';
}

if (!str_contains($bundle, 'children:"全站重新生成"')) {
    $issues[] = 'task center static-build tab must render a visible full-build button';
}

if (!preg_match('/onClick:\(\)=>(?:\{)?void\s+[A-Za-z_$][A-Za-z0-9_$]*\(/', $bundle)) {
    $issues[] = 'task center full-build button must invoke the shared site-build action';
}

if ($issues !== []) {
    fwrite(STDERR, "Task center full-build entry validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, " - {$issue}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Task center full-build entry validation passed.\n");
