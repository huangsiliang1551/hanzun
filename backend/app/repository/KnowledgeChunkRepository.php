<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use PDO;

final class KnowledgeChunkRepository
{
    public function listByDocumentId(int $documentId, int $limit = 200): array
    {
        $pdo = $this->pdo();
        $statement = $pdo->prepare(
            'SELECT id, document_id, chunk_index, content, token_estimate, keywords, created_at
             FROM knowledge_chunks
             WHERE document_id = :document_id
             ORDER BY chunk_index ASC
             LIMIT ' . max(1, min(500, $limit))
        );
        $statement->execute(['document_id' => $documentId]);
        $rows = $statement->fetchAll() ?: [];

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    public function replaceForDocument(int $documentId, array $chunks): int
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $deleteStatement = $pdo->prepare('DELETE FROM knowledge_chunks WHERE document_id = :document_id');
            $deleteStatement->execute(['document_id' => $documentId]);

            $insertStatement = $pdo->prepare(
                'INSERT INTO knowledge_chunks (document_id, chunk_index, content, token_estimate, keywords, created_at)
                 VALUES (:document_id, :chunk_index, :content, :token_estimate, :keywords, NOW())'
            );

            $count = 0;
            foreach ($chunks as $index => $chunk) {
                $insertStatement->execute([
                    'document_id' => $documentId,
                    'chunk_index' => (int) ($chunk['chunk_index'] ?? $index),
                    'content' => (string) ($chunk['content'] ?? ''),
                    'token_estimate' => (int) ($chunk['token_estimate'] ?? 0),
                    'keywords' => json_encode($chunk['keywords'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
                $count++;
            }

            $pdo->commit();

            return $count;
        } catch (\Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function searchCandidates(array $documentIds, int $limit = 200): array
    {
        if ($documentIds === []) {
            return [];
        }

        $pdo = $this->pdo();
        $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
        $statement = $pdo->prepare(
            'SELECT c.id, c.document_id, c.chunk_index, c.content, c.token_estimate, c.keywords,
                    d.title, d.source_type, d.source_id, d.language_code
             FROM knowledge_chunks c
             INNER JOIN knowledge_documents d ON d.id = c.document_id
             WHERE c.document_id IN (' . $placeholders . ') AND d.status = ?
             ORDER BY c.document_id ASC, c.chunk_index ASC
             LIMIT ' . max(1, min(1000, $limit))
        );

        $params = array_map('intval', $documentIds);
        $params[] = 'indexed';
        $statement->execute($params);
        $rows = $statement->fetchAll() ?: [];

        return array_map(function (array $row): array {
            $chunk = $this->normalizeRow($row);
            $chunk['document_title'] = (string) ($row['title'] ?? '');
            $chunk['source_type'] = (string) ($row['source_type'] ?? '');
            $chunk['source_id'] = isset($row['source_id']) ? (int) $row['source_id'] : null;
            $chunk['language_code'] = (string) ($row['language_code'] ?? 'zh');

            return $chunk;
        }, $rows);
    }

    private function normalizeRow(array $row): array
    {
        $keywords = $row['keywords'] ?? [];
        if (is_string($keywords)) {
            $decoded = json_decode($keywords, true);
            $keywords = is_array($decoded) ? $decoded : [];
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'document_id' => (int) ($row['document_id'] ?? 0),
            'chunk_index' => (int) ($row['chunk_index'] ?? 0),
            'content' => (string) ($row['content'] ?? ''),
            'token_estimate' => (int) ($row['token_estimate'] ?? 0),
            'keywords' => $keywords,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    private function pdo(): PDO
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            throw new BusinessException('数据库连接不可用', ErrorCode::INTERNAL_ERROR);
        }

        return $pdo;
    }
}
