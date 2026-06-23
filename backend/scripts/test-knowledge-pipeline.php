<?php

declare(strict_types=1);

/**
 * 知识库 RAG 链路自检
 *
 * 用法: php backend/scripts/test-knowledge-pipeline.php
 */

use app\common\bootstrap\Autoloader;
use app\common\bootstrap\EnvLoader;
use app\common\config\ConfigRepository;
use app\common\database\DatabaseManager;
use app\repository\KnowledgeDocumentRepository;
use app\repository\SystemSettingRepository;
use app\service\knowledge\KnowledgeRetrievalService;
use app\service\knowledge\KnowledgeService;

$basePath = dirname(__DIR__);

require_once $basePath . '/app/common/bootstrap/Autoloader.php';
require_once $basePath . '/app/common/bootstrap/EnvLoader.php';
require_once $basePath . '/app/common/bootstrap/helpers.php';

Autoloader::register($basePath);
EnvLoader::load($basePath . '/.env');

$configRepository = ConfigRepository::instance();
$configRepository->load($basePath . '/config');
DatabaseManager::instance()->configure($configRepository->get('database.connections.mysql', []));

$results = [];
$passed = 0;
$failed = 0;

function record(array &$results, int &$passed, int &$failed, string $name, bool $ok, string $detail = ''): void
{
    $results[] = [
        'name' => $name,
        'ok' => $ok,
        'detail' => $detail,
    ];
    if ($ok) {
        $passed++;
    } else {
        $failed++;
    }
}

try {
    $pdo = DatabaseManager::instance()->connection();
    record($results, $passed, $failed, 'MySQL 连接', true, 'connected');
} catch (Throwable $exception) {
    record($results, $passed, $failed, 'MySQL 连接', false, $exception->getMessage());
    printReport($results, $passed, $failed);
    exit(1);
}

$settingRepository = new SystemSettingRepository();
$config = $settingRepository->deepseekConfig();

$defaults = $settingRepository->deepseekConfigDefaults();
record(
    $results,
    $passed,
    $failed,
    '系统默认 max_chars / RAG 提示词',
    (int) ($defaults['knowledge_max_chars'] ?? 0) === 128000
        && trim((string) (($defaults['prompts']['chat.rag']['system'] ?? '') ?: '')) !== '',
    sprintf('default_max_chars=%s, rag_prompt_len=%d', (string) ($defaults['knowledge_max_chars'] ?? '-'), strlen((string) (($defaults['prompts']['chat.rag']['system'] ?? '') ?: '')))
);

record(
    $results,
    $passed,
    $failed,
    '当前运行配置可读',
    (int) ($config['knowledge_enabled'] ?? 0) === 1,
    sprintf(
        'enabled=%s, top_k=%s, max_chars=%s',
        (string) ($config['knowledge_enabled'] ?? '-'),
        (string) ($config['knowledge_top_k'] ?? '-'),
        (string) ($config['knowledge_max_chars'] ?? '-')
    )
);

$documentRepository = new KnowledgeDocumentRepository();
$documents = $documentRepository->list(['page' => 1, 'page_size' => 20]);
$indexedCount = 0;
foreach ($documents['items'] ?? [] as $document) {
    if (($document['status'] ?? '') === 'indexed') {
        $indexedCount++;
    }
}

record(
    $results,
    $passed,
    $failed,
    '文档列表可读',
    is_array($documents['items'] ?? null),
    'total=' . count($documents['items'] ?? []) . ', indexed=' . $indexedCount
);

$retrievalService = new KnowledgeRetrievalService();
$queries = [
    'Are you a manufacturer?' => 'en',
    '你们是不是生产厂家' => 'zh',
];

$retrievalHits = 0;
$zhIndexed = 0;
foreach ($documents['items'] ?? [] as $document) {
    if (($document['status'] ?? '') === 'indexed' && ($document['language_code'] ?? '') === 'zh') {
        $zhIndexed++;
    }
}

foreach ($queries as $query => $lang) {
    $chunks = $retrievalService->retrieve($query, $lang);
    if ($chunks !== []) {
        $retrievalHits++;
    }

    $expectHit = $lang !== 'zh' || $zhIndexed > 0;
    record(
        $results,
        $passed,
        $failed,
        '检索: ' . $query,
        !$expectHit || $chunks !== [],
        $chunks === []
            ? ($expectHit ? 'no chunks' : '无中文文档，跳过命中要求')
            : sprintf('hits=%d, top_title=%s, score=%.1f', count($chunks), (string) ($chunks[0]['title'] ?? '-'), (float) ($chunks[0]['score'] ?? 0))
    );
}

$sampleChunks = $retrievalService->retrieve('manufacturer', 'en');
$contextPrompt = $retrievalService->buildContextPrompt($sampleChunks);
record(
    $results,
    $passed,
    $failed,
    '上下文提示词构建',
    $sampleChunks === [] || str_contains($contextPrompt, '[Reference'),
    'chars=' . strlen($contextPrompt)
);

$knowledgeService = new KnowledgeService();
$testTitle = '__pipeline_test__ ' . date('YmdHis');
$createdId = 0;

try {
    $created = $knowledgeService->createManual([
        'title' => $testTitle,
        'content' => "Pipeline test chunk.\nAre you a manufacturer? Yes, HANZUN has 15+ years manufacturing experience.",
        'language_code' => 'en',
    ]);
    $createdId = (int) ($created['id'] ?? 0);
    record(
        $results,
        $passed,
        $failed,
        '创建测试文档并索引',
        $createdId > 0 && ($created['status'] ?? '') === 'indexed',
        'id=' . $createdId . ', chunks=' . (string) ($created['chunk_count'] ?? 0)
    );

    if ($createdId > 0) {
        $afterCreate = $retrievalService->retrieve('pipeline test manufacturer', 'en');
        $foundTestDoc = false;
        foreach ($afterCreate as $item) {
            if (str_contains((string) ($item['title'] ?? ''), $testTitle)) {
                $foundTestDoc = true;
                break;
            }
        }
        record(
            $results,
            $passed,
            $failed,
            '新建文档可检索',
            $foundTestDoc,
            'hits=' . count($afterCreate)
        );

        $knowledgeService->deleteDocument($createdId);
        record($results, $passed, $failed, '清理测试文档', true, 'deleted id=' . $createdId);
    }
} catch (Throwable $exception) {
    record($results, $passed, $failed, '创建/清理测试文档', false, $exception->getMessage());
    if ($createdId > 0) {
        try {
            $knowledgeService->deleteDocument($createdId);
        } catch (Throwable) {
        }
    }
}

$apiKey = trim((string) ($config['api_key'] ?? ''));
if ($apiKey !== '') {
    record($results, $passed, $failed, 'DeepSeek API Key', true, 'configured (chat API not invoked in this script)');
} else {
    record($results, $passed, $failed, 'DeepSeek API Key', false, '未配置，跳过在线对话测试');
}

printReport($results, $passed, $failed);
exit($failed > 0 ? 1 : 0);

function printReport(array $results, int $passed, int $failed): void
{
    echo "\n=== 知识库 RAG 链路测试报告 ===\n\n";
    foreach ($results as $item) {
        $status = $item['ok'] ? 'PASS' : 'FAIL';
        echo sprintf("[%s] %s\n", $status, $item['name']);
        if (($item['detail'] ?? '') !== '') {
            echo '       ' . $item['detail'] . "\n";
        }
    }
    echo "\n合计: {$passed} 通过, {$failed} 失败\n\n";
}
