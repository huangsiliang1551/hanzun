<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);

chdir($backendRoot);
require_once $backendRoot . '/tests/test-bootstrap.php';

use app\service\content\ProductService;
use app\service\content\SolutionService;

$issues = [];

try {
    $solutions = (new SolutionService())->list([
        'page' => 1,
        'page_size' => 10,
    ]);

    if (!is_array($solutions['items'] ?? null)) {
        $issues[] = 'solution list must still return an items array when media_gallery table is unavailable';
    }
} catch (Throwable $exception) {
    $issues[] = 'solution list must not throw when media_gallery table is unavailable: ' . $exception->getMessage();
}

try {
    $products = (new ProductService())->list([
        'page' => 1,
        'page_size' => 10,
    ]);

    if (!is_array($products['items'] ?? null)) {
        $issues[] = 'product list must still return an items array when media_gallery table is unavailable';
    }
} catch (Throwable $exception) {
    $issues[] = 'product list must not throw when media_gallery table is unavailable: ' . $exception->getMessage();
}

if ($issues !== []) {
    fwrite(STDERR, "Media gallery fallback validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Media gallery fallback validation passed.\n");
