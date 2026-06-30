<?php

declare(strict_types=1);

namespace app\service\knowledge;

use app\common\database\DatabaseManager;
use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\KnowledgeDocumentRepository;
use PDO;

final class KnowledgeSyncService
{
    public function __construct(
        private readonly KnowledgeDocumentRepository $documentRepository = new KnowledgeDocumentRepository(),
        private readonly KnowledgeIngestionService $ingestionService = new KnowledgeIngestionService()
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function syncCmsContent(array $options = []): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            throw new BusinessException('数据库连接不可用', ErrorCode::INTERNAL_ERROR);
        }

        $types = $options['types'] ?? ['product', 'solution', 'article'];
        if (!is_array($types)) {
            $types = ['product', 'solution', 'article'];
        }

        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($types as $type) {
            $type = trim((string) $type);
            if ($type === 'product') {
                $this->syncTable($pdo, 'products', 'product', 'name_zh', $summary);
            } elseif ($type === 'solution') {
                $this->syncTable($pdo, 'solutions', 'solution', 'name_zh', $summary);
            } elseif ($type === 'article') {
                $this->syncTable($pdo, 'articles', 'article', 'title_zh', $summary);
            }
        }

        return $summary;
    }

    /**
     * @param array<string, int> $summary
     */
    private function syncTable(PDO $pdo, string $table, string $sourceType, string $titleColumn, array &$summary): void
    {
        $statement = $pdo->query(
            'SELECT id, slug, ' . $titleColumn . ' AS title_value, summary_zh, content_zh, publish_status
             FROM ' . $table . '
             WHERE publish_status = \'published\'
             ORDER BY id ASC'
        );
        $rows = $statement ? ($statement->fetchAll() ?: []) : [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $sourceId = (int) ($row['id'] ?? 0);
            if ($sourceId <= 0) {
                continue;
            }

            $title = trim((string) ($row['title_value'] ?? ''));
            if ($title === '') {
                $title = trim((string) ($row['slug'] ?? ('CMS ' . $sourceType . ' #' . $sourceId)));
            }

            $text = $this->buildEntityText([
                'name_zh' => $title,
                'summary_zh' => (string) ($row['summary_zh'] ?? ''),
                'content_zh' => (string) ($row['content_zh'] ?? ''),
            ]);
            $hash = KnowledgeTextHelper::contentHash($text);
            $existing = $this->documentRepository->findBySource($sourceType, $sourceId);

            try {
                if ($existing === null) {
                    $document = $this->documentRepository->create([
                        'title' => $title,
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                        'file_path' => '',
                        'language_code' => 'zh',
                        'status' => 'pending',
                        'chunk_count' => 0,
                        'error_message' => '',
                        'tags' => [
                            'slug' => (string) ($row['slug'] ?? ''),
                            'table' => $table,
                        ],
                        'content_hash' => $hash,
                    ]);
                    $this->ingestionService->ingestDocument((int) ($document['id'] ?? 0), $text);
                    $summary['created']++;
                    continue;
                }

                if (($existing['content_hash'] ?? '') === $hash && ($existing['status'] ?? '') === 'indexed') {
                    $summary['skipped']++;
                    continue;
                }

                $this->documentRepository->update((int) $existing['id'], [
                    'title' => $title,
                    'status' => 'pending',
                    'tags' => [
                        'slug' => (string) ($row['slug'] ?? ''),
                        'table' => $table,
                    ],
                    'content_hash' => $hash,
                ]);
                $this->ingestionService->ingestDocument((int) $existing['id'], $text);
                $summary['updated']++;
            } catch (\Throwable) {
                if ($existing !== null) {
                    $this->documentRepository->update((int) $existing['id'], [
                        'status' => 'failed',
                        'error_message' => 'sync failed',
                    ]);
                }
                $summary['failed']++;
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildEntityText(array $row): string
    {
        $parts = [];
        $name = trim((string) ($row['name_zh'] ?? ''));
        $summary = trim((string) ($row['summary_zh'] ?? ''));
        $content = trim((string) ($row['content_zh'] ?? ''));

        if ($name !== '') {
            $parts[] = '标题：' . $name;
        }
        if ($summary !== '') {
            $parts[] = '摘要：' . $summary;
        }
        if ($content !== '') {
            $parts[] = '正文：' . $content;
        }

        return KnowledgeTextHelper::normalizeText(implode("\n\n", $parts));
    }
}
