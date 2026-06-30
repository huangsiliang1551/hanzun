<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
require __DIR__ . '/test-bootstrap.php';

use app\common\database\DatabaseManager;
use app\repository\KnowledgeDocumentRepository;
use app\service\inquiry\PublicChatService;
use app\service\knowledge\KnowledgeRetrievalService;
use app\service\knowledge\KnowledgeService;

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function ensureKnowledgeSchema(): void
{
    $pdo = DatabaseManager::instance()->connection();
    if (!$pdo instanceof PDO) {
        fail('database unavailable');
    }

    $sql = file_get_contents(dirname(__DIR__) . '/database/sql/004_knowledge_base.sql');
    if (!is_string($sql) || trim($sql) === '') {
        fail('knowledge base schema file unavailable');
    }

    $pdo->exec($sql);
}

ensureKnowledgeSchema();

$knowledgeService = new KnowledgeService();
$knowledgeService->syncCms();

$documentRepository = new KnowledgeDocumentRepository();
$documents = $documentRepository->list(['page' => 1, 'page_size' => 20]);
$indexedDocuments = array_values(array_filter(
    is_array($documents['items'] ?? null) ? $documents['items'] : [],
    static fn (array $item): bool => (string) ($item['status'] ?? '') === 'indexed'
));

if ($indexedDocuments === []) {
    fail('expected indexed knowledge documents after syncCms()');
}

$retrievalService = new KnowledgeRetrievalService();
$chunks = $retrievalService->retrieve(
    'I want to know about the cake depositor and cake production line',
    'en',
    ['source_page' => '/en/products/cake-depositor.html']
);

if ($chunks === []) {
    fail('expected english query to retrieve at least one knowledge chunk');
}

$publicChatService = new PublicChatService();
$result = $publicChatService->chat([
    'client_id' => 'language-lock-runtime-check',
    'message' => 'I want to know about the cake depositor and cake production line',
    'path' => '/en/products/cake-depositor.html',
    'title' => 'Cake Depositor',
    'referrer' => '',
    'language' => 'en',
    'utm_source' => 'runtime-test',
]);

$reply = trim((string) ($result['assistant_reply'] ?? ''));
if ($reply === '') {
    fail('assistant_reply should not be empty');
}

if ((int) count($result['sources'] ?? []) === 0) {
    fail('assistant_reply should include knowledge sources for the english query');
}

if (preg_match('/[\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}]/u', $reply) === 1) {
    fail('assistant_reply leaked CJK text for an english visitor: ' . $reply);
}

echo "Public chat language lock runtime validation passed." . PHP_EOL;
