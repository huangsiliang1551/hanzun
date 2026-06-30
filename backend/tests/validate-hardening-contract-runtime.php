<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);
$projectRoot = dirname($backendRoot);
$issues = [];

$read = static function (string $relativePath) use ($backendRoot, $projectRoot, &$issues): string {
    $base = str_starts_with($relativePath, 'backend' . DIRECTORY_SEPARATOR)
        ? $projectRoot
        : $backendRoot;
    $path = $base . DIRECTORY_SEPARATOR . $relativePath;
    if (!is_file($path)) {
        $issues[] = 'missing file: ' . $relativePath;
        return '';
    }

    $content = file_get_contents($path);
    if (!is_string($content) || $content === '') {
        $issues[] = 'failed to read file: ' . $relativePath;
        return '';
    }

    return $content;
};

$mediaService = $read('app' . DIRECTORY_SEPARATOR . 'service' . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'MediaService.php');
$databaseManager = $read('app' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'DatabaseManager.php');
$rateLimitMiddleware = $read('app' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'middleware' . DIRECTORY_SEPARATOR . 'RateLimitMiddleware.php');
$jsonFileStore = $read('app' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'JsonFileStore.php');
$authService = $read('app' . DIRECTORY_SEPARATOR . 'service' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'AuthService.php');
$authMiddleware = $read('app' . DIRECTORY_SEPARATOR . 'adminapi' . DIRECTORY_SEPARATOR . 'middleware' . DIRECTORY_SEPARATOR . 'AuthMiddleware.php');
$sessionService = $read('app' . DIRECTORY_SEPARATOR . 'service' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'SessionService.php');
$requestFile = $read('app' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'http' . DIRECTORY_SEPARATOR . 'Request.php');
$uploadConfig = $read('config' . DIRECTORY_SEPARATOR . 'upload.php');
$bearerTokenHelper = $read('app' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'BearerToken.php');

if ($mediaService !== '') {
    if (!str_contains($mediaService, '$diskPath = $this->resolveDiskPath($deleted);')) {
        $issues[] = 'MediaService::remove must resolve delete path via resolveDiskPath().';
    }
    if (preg_match('/public function remove\(int \$id\): array[\s\S]*if \(\(int\) \(\$references\[\'reference_count\'\] \?\? 0\) > 0\)/', $mediaService) === 1) {
        $issues[] = 'MediaService::remove still contains duplicated reference_count checks.';
    }
    if (!str_contains($mediaService, '$category = $this->categoryFromFileName($fileName);')) {
        $issues[] = 'MediaService::createTempSourceFileFromBase64 must derive category from file name.';
    }
    if (!str_contains($mediaService, "config('upload.limits.' . \$category")) {
        $issues[] = 'MediaService::createTempSourceFileFromBase64 must read upload.limits.{category}.';
    }
    if (str_contains($mediaService, 'max($maxImage, $maxVideo, $maxPdf)')) {
        $issues[] = 'MediaService::createTempSourceFileFromBase64 must not use the max upload limit across categories.';
    }
    if (str_contains($mediaService, 'sanitizeSvgFile')) {
        $issues[] = 'MediaService must not retain sanitizeSvgFile references.';
    }
}

if ($uploadConfig !== '') {
    if (preg_match("/'allowed_extensions'\\s*=>\\s*\\[[\\s\\S]*?'image'\\s*=>\\s*\\[([^\\]]*'svg'[^\\]]*)\\]/i", $uploadConfig) === 1) {
        $issues[] = 'upload.allowed_extensions must not allow svg.';
    }
    if (preg_match("/'mime_types'\\s*=>\\s*\\[[\\s\\S]*?'image'\\s*=>\\s*\\[([^\\]]*svg[^\\]]*)\\]/i", $uploadConfig) === 1) {
        $issues[] = 'upload.mime_types must not allow svg.';
    }
}

if ($databaseManager !== '') {
    if (!str_contains($databaseManager, 'error_log(')) {
        $issues[] = 'DatabaseManager must error_log connection failures.';
    }
    if (!preg_match('/if\\s*\\(!\\$allowFallback\\)\\s*\\{[\\s\\S]*(throw \\$this->connectionError|throw new \\\\?RuntimeException\\()/', $databaseManager)) {
        $issues[] = 'DatabaseManager must throw RuntimeException when APP_ALLOW_RUNTIME_FALLBACK=false.';
    }
}

if ($rateLimitMiddleware !== '') {
    if (!str_contains($rateLimitMiddleware, 'error_log(')) {
        $issues[] = 'RateLimitMiddleware must log storage/lock failures.';
    }
    if (!preg_match('/flock\\([^\\)]*,\\s*LOCK_EX\\)/', $rateLimitMiddleware)) {
        $issues[] = 'RateLimitMiddleware must use an exclusive lock for read-filter-append-write.';
    }
    if (!str_contains($rateLimitMiddleware, '.lock')) {
        $issues[] = 'RateLimitMiddleware must use a dedicated lock file.';
    }
}

if ($requestFile !== '' && !str_contains($requestFile, 'Malformed JSON request body.')) {
    $issues[] = 'Request must reject malformed JSON bodies.';
}

if ($sessionService !== '') {
    if (!str_contains($sessionService, 'FOR UPDATE')) {
        $issues[] = 'SessionService database refresh flow must lock the session row.';
    }
    if (!str_contains($sessionService, 'rowCount() !== 1')) {
        $issues[] = 'SessionService refresh rotation must check rowCount() for single-use enforcement.';
    }
    if (!str_contains($sessionService, '->transaction(')) {
        $issues[] = 'SessionService runtime refresh flow must use JsonFileStore::transaction().';
    }
}

if ($bearerTokenHelper === '') {
    $issues[] = 'Shared BearerToken helper is missing.';
} else {
    if (!preg_match('/preg_match\\(\\s*[\'"]\\/\\^Bearer\\\\s\\+\\(\\.\\+\\)\\$\\/i[\'"]/', $bearerTokenHelper)) {
        $issues[] = 'BearerToken helper must use strict /^Bearer\\s+(.+)$/i parsing.';
    }
}

if ($authService !== '' && !str_contains($authService, 'BearerToken::extract(')) {
    $issues[] = 'AuthService::logout must use the shared BearerToken helper.';
}

if ($authMiddleware !== '' && !str_contains($authMiddleware, 'BearerToken::extract(')) {
    $issues[] = 'AuthMiddleware must use the shared BearerToken helper.';
}

if ($jsonFileStore !== '') {
    if (!preg_match('/public function transaction\\s*\\(/', $jsonFileStore)) {
        $issues[] = 'JsonFileStore must expose transaction(callable $callback).';
    }
    if (substr_count($jsonFileStore, ".lock") < 1) {
        $issues[] = 'JsonFileStore must lock using a .lock file.';
    }
}

if ($issues !== []) {
    fwrite(STDERR, "Hardening contract validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Hardening contract validation passed.\n");
