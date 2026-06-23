<?php
/**
 * PHP built-in server router for local development.
 * Start with:
 * php -S 127.0.0.1:8080 -t . backend/public/router.php
 */

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$projectRoot = dirname(__DIR__, 2);
$backendPublicRoot = __DIR__;

function isSensitivePath(string $path): bool
{
    $normalized = str_replace('\\', '/', $path);
    $basename = basename($normalized);

    if (str_contains($normalized, '/.')) {
        return true;
    }

    if (str_starts_with($normalized, '/backend/') && $normalized !== '/backend/public/index.php') {
        return true;
    }

    if (in_array($basename, ['.env', 'hanzun_cms.sql', 'login_response.json', 'token.txt', 'query'], true)) {
        return true;
    }

    return preg_match('/^_.*\.(py|js|json)$/i', $basename) === 1
        || preg_match('/^tmp-.*\.html$/i', $basename) === 1
        || preg_match('/\.(log|bak|tmp|sql)$/i', $basename) === 1;
}

function outputFile(string $filePath): bool
{
    if (!is_file($filePath)) {
        return false;
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeMap = [
        'html' => 'text/html; charset=UTF-8',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ];

    header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    return true;
}

function sendNoCacheHeaders(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function sendStaticAssetCacheHeaders(): void
{
    header('Cache-Control: public, max-age=31536000, immutable');
}

function redirectToAdminAppLogin(): void
{
    header('Location: /admin-app/#/login', true, 302);
}

if (isSensitivePath($path)) {
    http_response_code(404);
    return true;
}

if (str_starts_with($path, '/admin-app/')) {
    $adminAppRoot = $projectRoot . '/admin-app';
    $adminAppPath = $path === '/admin-app/' ? '/index.html' : substr($path, strlen('/admin-app'));
    $requestedAdminFile = $adminAppRoot . $adminAppPath;
    if (is_file($requestedAdminFile)) {
        if (strtolower(pathinfo($requestedAdminFile, PATHINFO_EXTENSION)) === 'html') {
            sendNoCacheHeaders();
        } else {
            sendStaticAssetCacheHeaders();
        }

        if (outputFile($requestedAdminFile)) {
            return true;
        }
    }

    if (pathinfo($adminAppPath, PATHINFO_EXTENSION) !== '') {
        http_response_code(404);
        return true;
    }

    sendNoCacheHeaders();
    if (outputFile($adminAppRoot . '/index.html')) {
        return true;
    }
}

if ($path === '/admin' || $path === '/admin.html' || $path === '/login' || $path === '/login.html') {
    redirectToAdminAppLogin();
    return true;
}

if (str_starts_with($path, '/api/') || $path === '/health') {
    require $backendPublicRoot . '/index.php';
    return true;
}

if (str_starts_with($path, '/admin') && !str_starts_with($path, '/admin-app')) {
    $staticRootFile = $projectRoot . $path;
    if (is_file($staticRootFile)) {
        return outputFile($staticRootFile);
    }

    require $backendPublicRoot . '/index.php';
    return true;
}

if (str_starts_with($path, '/uploads/')) {
    if (outputFile($backendPublicRoot . $path)) {
        return true;
    }
}

if (outputFile($projectRoot . $path)) {
    return true;
}

if ($path === '/' && outputFile($projectRoot . '/index.html')) {
    return true;
}

http_response_code(404);
return true;
