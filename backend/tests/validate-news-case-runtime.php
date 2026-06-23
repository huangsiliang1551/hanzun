<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
require __DIR__ . '/test-bootstrap.php';

use app\common\database\DatabaseManager;
use app\service\content\CaseService;
use app\service\content\NewsService;

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertTableAndColumn(string $table, string $column): void
{
    $pdo = DatabaseManager::instance()->connection();
    if (!$pdo instanceof PDO) {
        fail('database unavailable');
    }

    try {
        $rows = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll();
    } catch (Throwable $exception) {
        fail(sprintf('%s table missing or unreadable: %s', $table, $exception->getMessage()));
    }

    $columns = [];
    foreach ($rows as $row) {
        $columns[] = (string) ($row['Field'] ?? '');
    }

    if (!in_array($column, $columns, true)) {
        fail(sprintf('%s.%s column missing', $table, $column));
    }
}

function assertListResult(string $label, array $result): void
{
    if (!array_key_exists('items', $result) || !is_array($result['items'])) {
        fail($label . ' list must return items[]');
    }

    if (!array_key_exists('pagination', $result) || !is_array($result['pagination'])) {
        fail($label . ' list must return pagination');
    }
}

assertTableAndColumn('news', 'views_count');
assertTableAndColumn('cases', 'views_count');

$newsService = new NewsService();
$newsList = $newsService->list([
    'page' => 1,
    'page_size' => 10,
]);
assertListResult('news', $newsList);
foreach (($newsList['items'] ?? []) as $item) {
    if (trim((string) ($item['slug'] ?? '')) === '') {
        fail('news list items must include slug for public detail preview');
    }
}

$newsLookups = $newsService->lookups();
if (!is_array($newsLookups['categories'] ?? null)) {
    fail('news lookups must return categories');
}

$caseService = new CaseService();
$caseList = $caseService->list([
    'page' => 1,
    'page_size' => 10,
]);
assertListResult('case', $caseList);
foreach (($caseList['items'] ?? []) as $item) {
    if (trim((string) ($item['slug'] ?? '')) === '') {
        fail('case list items must include slug for public detail preview');
    }
}

$caseLookups = $caseService->lookups();
if (!is_array($caseLookups['categories'] ?? null)) {
    fail('case lookups must return categories');
}
if (!is_array($caseLookups['products']['items'] ?? null)) {
    fail('case lookups must return products.items');
}
if (!is_array($caseLookups['solutions']['items'] ?? null)) {
    fail('case lookups must return solutions.items');
}

echo "News/case runtime validation passed." . PHP_EOL;
