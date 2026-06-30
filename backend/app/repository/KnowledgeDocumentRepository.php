<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use PDO;

final class KnowledgeDocumentRepository
{
    public function list(array $query = []): array
    {
        $pdo = $this->pdo();
        $page = max(1, (int) ($query['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($query['page_size'] ?? 20)));
        $offset = ($page - 1) * $pageSize;

        $conditions = ['1=1'];
        $params = [];

        $keyword = trim((string) ($query['keyword'] ?? ''));
        if ($keyword !== '') {
            $conditions[] = '(title LIKE :keyword OR tags LIKE :keyword_tags)';
            $params['keyword'] = '%' . $keyword . '%';
            $params['keyword_tags'] = '%' . $keyword . '%';
        }

        $status = trim((string) ($query['status'] ?? ''));
        if ($status !== '') {
            $conditions[] = 'status = :status';
            $params['status'] = $status;
        }

        $sourceType = trim((string) ($query['source_type'] ?? ''));
        if ($sourceType !== '') {
            $conditions[] = 'source_type = :source_type';
            $params['source_type'] = $sourceType;
        }

        $languageCode = trim((string) ($query['language_code'] ?? ''));
        if ($languageCode !== '') {
            $conditions[] = 'language_code = :language_code';
            $params['language_code'] = $languageCode;
        }

        $where = implode(' AND ', $conditions);

        $countStatement = $pdo->prepare('SELECT COUNT(*) FROM knowledge_documents WHERE ' . $where);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();

        $statement = $pdo->prepare(
            'SELECT id, title, source_type, source_id, file_path, language_code, status, chunk_count, error_message, tags, content_hash, created_at, updated_at
             FROM knowledge_documents
             WHERE ' . $where . '
             ORDER BY updated_at DESC, id DESC
             LIMIT ' . (int) $pageSize . ' OFFSET ' . (int) $offset
        );
        $statement->execute($params);
        $items = $statement->fetchAll() ?: [];

        return [
            'items' => array_map(fn (array $row): array => $this->normalizeRow($row), $items),
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => (int) max(1, ceil($total / $pageSize)),
            ],
        ];
    }

    public function find(int $id): ?array
    {
        $pdo = $this->pdo();
        $statement = $pdo->prepare(
            'SELECT id, title, source_type, source_id, file_path, language_code, status, chunk_count, error_message, tags, content_hash, created_at, updated_at
             FROM knowledge_documents
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    public function findBySource(string $sourceType, int $sourceId): ?array
    {
        if ($sourceId <= 0) {
            return null;
        }

        $pdo = $this->pdo();
        $statement = $pdo->prepare(
            'SELECT id, title, source_type, source_id, file_path, language_code, status, chunk_count, error_message, tags, content_hash, created_at, updated_at
             FROM knowledge_documents
             WHERE source_type = :source_type AND source_id = :source_id
             LIMIT 1'
        );
        $statement->execute([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $this->normalizeRow($row) : null;
    }

    public function create(array $payload): array
    {
        $pdo = $this->pdo();
        $statement = $pdo->prepare(
            'INSERT INTO knowledge_documents (title, source_type, source_id, file_path, language_code, status, chunk_count, error_message, tags, content_hash, created_at, updated_at)
             VALUES (:title, :source_type, :source_id, :file_path, :language_code, :status, :chunk_count, :error_message, :tags, :content_hash, NOW(), NOW())'
        );
        $statement->execute([
            'title' => $payload['title'],
            'source_type' => $payload['source_type'],
            'source_id' => $payload['source_id'],
            'file_path' => $payload['file_path'],
            'language_code' => $payload['language_code'],
            'status' => $payload['status'],
            'chunk_count' => $payload['chunk_count'],
            'error_message' => $payload['error_message'],
            'tags' => json_encode($payload['tags'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content_hash' => $payload['content_hash'],
        ]);

        return $this->find((int) $pdo->lastInsertId()) ?? $payload;
    }

    public function update(int $id, array $payload): ?array
    {
        $existing = $this->find($id);
        if ($existing === null) {
            return null;
        }

        $pdo = $this->pdo();
        $statement = $pdo->prepare(
            'UPDATE knowledge_documents
             SET title = :title,
                 language_code = :language_code,
                 status = :status,
                 chunk_count = :chunk_count,
                 error_message = :error_message,
                 tags = :tags,
                 content_hash = :content_hash,
                 file_path = :file_path,
                 updated_at = NOW()
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'title' => $payload['title'] ?? $existing['title'],
            'language_code' => $payload['language_code'] ?? $existing['language_code'],
            'status' => $payload['status'] ?? $existing['status'],
            'chunk_count' => $payload['chunk_count'] ?? $existing['chunk_count'],
            'error_message' => $payload['error_message'] ?? $existing['error_message'],
            'tags' => json_encode($payload['tags'] ?? $existing['tags'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content_hash' => $payload['content_hash'] ?? $existing['content_hash'],
            'file_path' => $payload['file_path'] ?? $existing['file_path'],
        ]);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $pdo = $this->pdo();
        $statement = $pdo->prepare('DELETE FROM knowledge_documents WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->rowCount() > 0;
    }

    public function listIndexedForRetrieval(string $languageCode = ''): array
    {
        $pdo = $this->pdo();
        $sql = 'SELECT id, title, source_type, source_id, language_code, tags
                FROM knowledge_documents
                WHERE status = :status';
        $params = ['status' => 'indexed'];

        $languageCode = strtolower(trim($languageCode));
        if ($languageCode !== '') {
            $sql .= ' AND language_code IN (:language_code, :language_fallback)';
            $params['language_code'] = $languageCode;
            $params['language_fallback'] = $languageCode === 'zh' ? 'en' : 'zh';
        }

        $sql .= ' ORDER BY updated_at DESC, id DESC';

        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll() ?: [];

        return array_map(fn (array $row): array => $this->normalizeRow($row), $rows);
    }

    private function normalizeRow(array $row): array
    {
        $tags = $row['tags'] ?? [];
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags = is_array($decoded) ? $decoded : [];
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'source_type' => (string) ($row['source_type'] ?? 'manual'),
            'source_id' => isset($row['source_id']) ? (int) $row['source_id'] : null,
            'file_path' => (string) ($row['file_path'] ?? ''),
            'language_code' => (string) ($row['language_code'] ?? 'zh'),
            'status' => (string) ($row['status'] ?? 'pending'),
            'chunk_count' => (int) ($row['chunk_count'] ?? 0),
            'error_message' => (string) ($row['error_message'] ?? ''),
            'tags' => $tags,
            'content_hash' => (string) ($row['content_hash'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
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
