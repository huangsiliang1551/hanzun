<?php

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

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

function redirectToAdminAppLogin(): void
{
    header('Location: /admin-app/#/login', true, 302);
}

if (isSensitivePath($path)) {
    http_response_code(404);
    return true;
}

if (str_starts_with($path, '/admin-app/')) {
    $adminAppRoot = __DIR__ . '/admin-app';
    $adminAppPath = $path === '/admin-app/' ? '/index.html' : substr($path, strlen('/admin-app'));
    $filePath = $adminAppRoot . $adminAppPath;

    if (is_file($filePath)) {
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

    if (pathinfo($adminAppPath, PATHINFO_EXTENSION) !== '') {
        http_response_code(404);
        return true;
    }

    $spaIndex = $adminAppRoot . '/index.html';
    if (is_file($spaIndex)) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Content-Type: text/html');
        header('Content-Length: ' . filesize($spaIndex));
        readfile($spaIndex);
        return true;
    }
}

if ($path === '/admin' || $path === '/admin.html' || $path === '/login' || $path === '/login.html') {
    redirectToAdminAppLogin();
    return true;
}

$segments = array_values(array_filter(explode('/', $path)));
if (count($segments) >= 2 && ($segments[0] === 'admin' || $segments[0] === 'api')) {
    require __DIR__ . '/backend/public/index.php';
    return true;
}

if ($path === '/health') {
    require __DIR__ . '/backend/public/index.php';
    return true;
}

if (str_starts_with($path, '/uploads/')) {
    $filePath = __DIR__ . '/backend/public' . $path;
    if (is_file($filePath)) {
        $mimeMap = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $mimeMap[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        return true;
    }
}

$filePath = __DIR__ . $path;
if (is_file($filePath)) {
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
    ];
    $mime = $mimeMap[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    return true;
}

http_response_code(404);
return true;
