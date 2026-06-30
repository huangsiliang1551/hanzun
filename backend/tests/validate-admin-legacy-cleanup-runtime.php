<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$issues = [];

$mustBeRemoved = [
    'assets/admin-templates',
    'assets/css/cms-admin.css',
    'assets/js/admin',
    'assets/js/auth-guard.js',
    'login.html',
    'preview.html',
];

foreach ($mustBeRemoved as $relativePath) {
    $fullPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (file_exists($fullPath)) {
        $issues[] = 'legacy admin artifact must be removed: ' . $relativePath;
    }
}

$routerPaths = [
    'backend/public/router.php',
    'router.php',
];

foreach ($routerPaths as $relativePath) {
    $fullPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($fullPath)) {
        $issues[] = 'router file missing: ' . $relativePath;
        continue;
    }

    $source = file_get_contents($fullPath);
    if (!is_string($source) || $source === '') {
        $issues[] = 'router file unreadable: ' . $relativePath;
        continue;
    }

    if (
        str_contains($source, "outputFile(\$projectRoot . '/login.html')") ||
        str_contains($source, "outputFile(\$projectRoot . '/admin.html')") ||
        str_contains($source, "__DIR__ . '/login.html'") ||
        str_contains($source, "__DIR__ . '/admin.html'")
    ) {
        $issues[] = 'router must not depend on legacy login/admin html shell: ' . $relativePath;
    }

    if (!str_contains($source, '/admin-app/#/login')) {
        $issues[] = 'router must redirect legacy admin entry to admin-app login: ' . $relativePath;
    }
}

$nginxPath = $projectRoot . DIRECTORY_SEPARATOR . 'hanzun-cms.nginx.conf';
if (is_file($nginxPath)) {
    $nginx = file_get_contents($nginxPath);
    if (!is_string($nginx) || $nginx === '') {
        $issues[] = 'nginx config unreadable';
    } else {
        if (str_contains($nginx, 'try_files /admin.html =404;') || str_contains($nginx, 'try_files /login.html =404;')) {
            $issues[] = 'nginx config must not depend on legacy admin/login html files';
        }
        if (!str_contains($nginx, 'return 302 /admin-app/#/login;')) {
            $issues[] = 'nginx config must redirect legacy admin/login entry to admin-app login';
        }
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Admin legacy cleanup validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Admin legacy cleanup validation passed.\n");
