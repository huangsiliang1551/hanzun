<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);

chdir($backendRoot);
require_once $backendRoot . '/tests/test-bootstrap.php';

putenv('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK=1');
$_ENV['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';
$_SERVER['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';

use app\service\content\ArticleService;
use app\service\content\PageService;
use app\service\content\ProductService;
use app\service\content\SolutionService;

$issues = [];

$assertCover = static function (string $label, array $record) use (&$issues): void {
    $path = trim((string) ($record['cover_file_path'] ?? ''));
    if ($path === '') {
        $issues[] = $label . ' must expose a non-empty cover_file_path when media gallery data is missing';
    }
};

$assertExactCover = static function (string $label, array $record, string $expectedPath) use (&$issues): void {
    $actualPath = trim((string) ($record['cover_file_path'] ?? ''));
    if ($actualPath !== $expectedPath) {
        $issues[] = sprintf('%s must expose %s, got %s', $label, $expectedPath, $actualPath === '' ? '[empty]' : $actualPath);
    }
};

try {
    $products = (new ProductService())->list([
        'page' => 1,
        'page_size' => 10,
    ]);

    $product = ($products['items'][0] ?? null);
    if (!is_array($product)) {
        $issues[] = 'product list must expose at least one record for cover fallback validation';
    } else {
        $assertExactCover('product list item', $product, '/assets/images/home/equipment-forming-module.jpg');
    }
} catch (Throwable $exception) {
    $issues[] = 'product cover fallback validation threw: ' . $exception->getMessage();
}

try {
    $solutions = (new SolutionService())->list([
        'page' => 1,
        'page_size' => 10,
    ]);

    $solution = ($solutions['items'][0] ?? null);
    if (!is_array($solution)) {
        $issues[] = 'solution list must expose at least one record for cover fallback validation';
    } else {
        $assertExactCover('solution list item', $solution, '/assets/images/home/equipment-integrated-line.jpg');
    }
} catch (Throwable $exception) {
    $issues[] = 'solution cover fallback validation threw: ' . $exception->getMessage();
}

try {
    $articles = (new ArticleService())->list([
        'page' => 1,
        'page_size' => 10,
    ]);

    $article = ($articles['items'][0] ?? null);
    if (!is_array($article)) {
        $issues[] = 'article list must expose at least one record for cover fallback validation';
    } else {
        $assertExactCover('article list item', $article, '/assets/images/home/news-real-expo-hall.jpg');
    }
} catch (Throwable $exception) {
    $issues[] = 'article cover fallback validation threw: ' . $exception->getMessage();
}

try {
    $pages = (new PageService())->list([
        'page' => 1,
        'page_size' => 10,
    ]);

    $page = ($pages['items'][0] ?? null);
    if (!is_array($page)) {
        $issues[] = 'page list must expose at least one record for cover fallback validation';
    } else {
        $assertCover('page list item', $page);
    }
} catch (Throwable $exception) {
    $issues[] = 'page cover fallback validation threw: ' . $exception->getMessage();
}

if ($issues !== []) {
    fwrite(STDERR, "Content cover fallback validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, '- ' . $issue . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Content cover fallback validation passed.\n");
