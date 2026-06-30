<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
require __DIR__ . '/test-bootstrap.php';

putenv('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK=1');
$_ENV['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';
$_SERVER['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';

use app\common\database\DatabaseManager;
use app\service\content\CaseService;
use app\service\content\NewsService;
use app\service\content\ProductService;
use app\service\content\PublicSiteService;
use app\service\content\SolutionService;
function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function fetchSampleRow(PDO $pdo, string $table): array
{
    $statement = $pdo->query('SELECT id, views_count FROM `' . $table . '` ORDER BY id ASC LIMIT 1');
    $row = $statement ? $statement->fetch(PDO::FETCH_ASSOC) : false;
    if (!is_array($row) || (int) ($row['id'] ?? 0) <= 0) {
        fail(sprintf('No sample row found in %s.', $table));
    }

    return $row;
}

function fetchListSample(object $service, string $label): array
{
    $listPayload = $service->list([
        'page' => 1,
        'page_size' => 20,
    ]);
    $items = (array) ($listPayload['items'] ?? []);
    if ($items === []) {
        fail(sprintf('%s list returned no items.', $label));
    }

    $first = $items[0];
    if (!is_array($first) || (int) ($first['id'] ?? 0) <= 0) {
        fail(sprintf('%s list did not expose a valid sample item.', $label));
    }

    return [
        'id' => (int) $first['id'],
        'views_count' => (int) ($first['views_count'] ?? 0),
    ];
}

function assertListContainsViewCount(array $listPayload, int $id, int $expected, string $label): void
{
    foreach ((array) ($listPayload['items'] ?? []) as $item) {
        if ((int) ($item['id'] ?? 0) !== $id) {
            continue;
        }

        if ((int) ($item['views_count'] ?? -1) !== $expected) {
            fail(sprintf('%s list must expose real views_count for id %d.', $label, $id));
        }

        return;
    }

    fail(sprintf('%s list did not include sample id %d.', $label, $id));
}

$pdo = DatabaseManager::instance()->connection();
if (!$pdo instanceof PDO) {
    fail('Database unavailable.');
}

$publicSiteService = new PublicSiteService();
$productService = new ProductService();
$solutionService = new SolutionService();
$newsService = new NewsService();
$caseService = new CaseService();

$checks = [
    ['entity' => 'product', 'table' => 'products', 'service' => $productService, 'label' => 'product'],
    ['entity' => 'solution', 'table' => 'solutions', 'service' => $solutionService, 'label' => 'solution'],
    ['entity' => 'news', 'table' => 'news', 'service' => $newsService, 'label' => 'news'],
    ['entity' => 'case', 'table' => 'cases', 'service' => $caseService, 'label' => 'case'],
];

$backups = [];

try {
    foreach ($checks as $check) {
        $row = fetchListSample($check['service'], $check['label']);
        $id = (int) $row['id'];
        $original = (int) $row['views_count'];
        $backups[] = ['table' => $check['table'], 'id' => $id, 'views_count' => $original];

        $publicSiteService->recordPageView($check['entity'], $id);
        $publicSiteService->recordPageView($check['entity'], $id);

        $statement = $pdo->prepare('SELECT views_count FROM `' . $check['table'] . '` WHERE id = :id');
        $statement->execute(['id' => $id]);
        $updatedCount = (int) $statement->fetchColumn();
        if ($updatedCount !== $original + 2) {
            fail(sprintf('%s detail page view tracking did not increment %s.views_count.', $check['label'], $check['table']));
        }

        $listPayload = $check['service']->list([
            'page' => 1,
            'page_size' => 200,
            'sort_field' => 'id',
            'sort_order' => 'asc',
        ]);
        assertListContainsViewCount($listPayload, $id, $updatedCount, $check['label']);
    }
} finally {
    foreach ($backups as $backup) {
        $restore = $pdo->prepare('UPDATE `' . $backup['table'] . '` SET views_count = :views_count WHERE id = :id');
        $restore->execute([
            'id' => $backup['id'],
            'views_count' => $backup['views_count'],
        ]);
    }
}

fwrite(STDOUT, "Content view count runtime validation passed.\n");
