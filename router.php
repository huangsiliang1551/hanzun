<?php

declare(strict_types=1);

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = normalizeRequestPath($uri);

if ($path === '/') {
    $path = '/index.html';
}

if (isPathTraversal($path) || isSensitivePath($path)) {
    return sendNotFound();
}

if (isBackendRoute($path) || $path === '/health') {
    require __DIR__ . '/backend/public/index.php';
    return true;
}

if (str_starts_with($path, '/admin-app/')) {
    return serveAdminApp($path);
}

if ($path === '/admin' || $path === '/admin.html' || $path === '/login' || $path === '/login.html') {
    header('Location: /admin-app/#/login', true, 302);
    return true;
}

if (str_starts_with($path, '/uploads/')) {
    return serveUploads($path);
}

if (!isPublicAssetPath($path)) {
    return sendNotFound();
}

return serveStaticFile($path);

function normalizeRequestPath(string $uri): string
{
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $path = rawurldecode((string) $path);
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path);

    return $path === '' ? '/' : $path;
}

function isPathTraversal(string $path): bool
{
    return str_contains($path, '..') || str_contains($path, "\0");
}

function isSensitivePath(string $path): bool
{
    $normalized = str_replace('\\', '/', $path);
    $basename = basename($normalized);

    if (str_contains($basename, '.env')) {
        return true;
    }

    if (str_starts_with($normalized, '/backend/') && $normalized !== '/backend/public/index.php') {
        return true;
    }

    if (in_array($normalized, [
        '/query',
        '/login_response.json',
        '/token.txt',
        '/backend/.env',
        '/backend/.env.production',
        '/hanzun_cms.sql',
        '/backend/public/index.php',
    ], true)) {
        return true;
    }

    return preg_match('/\\.(log|bak|tmp|sql|sqlite|sqlite3|conf|ini|lock|key|pem|crt|p12|yml|yaml|sh|bat|cmd|ps1)$/i', $basename) === 1;
}

function isBackendRoute(string $path): bool
{
    return str_starts_with($path, '/api/')
        || str_starts_with($path, '/admin/')
        || $path === '/admin';
}

function isPublicAssetPath(string $path): bool
{
    $allowedPrefixes = [
        '/assets/',
        '/zh/',
        '/en/',
        '/storage/',
        '/admin-app/',
        '/upload',
        '/index.html',
        '/robots.txt',
        '/sitemap.xml',
        '/favicon.ico',
        '/health',
    ];

    if (str_starts_with($path, '/admin-app/')) {
        return true;
    }

    foreach ($allowedPrefixes as $prefix) {
        if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/')) {
            return true;
        }
    }

    return false;
}

function serveAdminApp(string $path): bool
{
    $adminAppRoot = __DIR__ . '/admin-app';
    $adminAppPath = $path === '/admin-app/' ? '/index.html' : substr($path, strlen('/admin-app'));
    $candidate = $adminAppRoot . $adminAppPath;

    if (is_file($candidate)) {
        return serveStaticPath($candidate);
    }

    if (pathinfo($adminAppPath, PATHINFO_EXTENSION) !== '') {
        return sendNotFound();
    }

    $spaIndex = $adminAppRoot . '/index.html';
    if (!is_file($spaIndex)) {
        return sendNotFound();
    }

    header('Content-Type: text/html');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Length: ' . filesize($spaIndex));
    readfile($spaIndex);
    return true;
}

function serveUploads(string $path): bool
{
    if (!isAllowedUploadFile($path)) {
        return sendNotFound();
    }

    $filePath = __DIR__ . '/backend/public' . $path;
    if (!is_file($filePath)) {
        return sendNotFound();
    }

    return serveStaticPath($filePath);
}

function isAllowedUploadFile(string $path): bool
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $allowed = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
        'pdf',
        'mp4',
        'webm',
        'mov',
    ];

    return in_array($ext, $allowed, true);
}

function serveStaticFile(string $path): bool
{
    $filePath = __DIR__ . $path;
    if (!is_file($filePath)) {
        return sendNotFound();
    }

    return serveStaticPath($filePath);
}

function serveStaticPath(string $filePath): bool
{
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeMap = [
        'html' => 'text/html',
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
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);

    if ($ext === 'html') {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    } else {
        header('Cache-Control: public, max-age=31536000, immutable');
    }

    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    return true;
}

function sendNotFound(): bool
{
    http_response_code(404);
    echo 'Not Found';
    return true;
}
