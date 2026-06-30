<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
require __DIR__ . '/test-bootstrap.php';

use app\common\database\DatabaseManager;
use app\service\knowledge\KnowledgeService;
function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$pdo = DatabaseManager::instance()->connection();
if (!$pdo instanceof PDO) {
    fail('Database unavailable.');
}

$schema = file_get_contents(dirname(__DIR__) . '/database/sql/004_knowledge_base.sql');
if (!is_string($schema) || trim($schema) === '') {
    fail('Knowledge base schema file unavailable.');
}

foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $schema) ?: [])) as $statement) {
    $pdo->exec($statement);
}

$service = new KnowledgeService();
$service->syncCms();
$summary = $service->reindexAll();

$total = (int) ($summary['success'] ?? 0) + (int) ($summary['failed'] ?? 0) + (int) ($summary['skipped'] ?? 0);
if ($total <= 0) {
    fail('Knowledge reindex summary must not report all-zero totals when documents exist.');
}

if (!is_array($summary['cms_sync'] ?? null)) {
    fail('Knowledge reindex summary must expose cms_sync details.');
}

fwrite(STDOUT, "Knowledge reindex summary runtime validation passed.\n");
