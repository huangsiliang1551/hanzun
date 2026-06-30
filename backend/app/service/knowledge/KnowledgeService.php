<?php

declare(strict_types=1);

namespace app\service\knowledge;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\KnowledgeChunkRepository;
use app\repository\KnowledgeDocumentRepository;
use app\service\log\OperationLogService;

final class KnowledgeService
{
    public function __construct(
        private readonly KnowledgeDocumentRepository $documentRepository = new KnowledgeDocumentRepository(),
        private readonly KnowledgeChunkRepository $chunkRepository = new KnowledgeChunkRepository(),
        private readonly KnowledgeIngestionService $ingestionService = new KnowledgeIngestionService(),
        private readonly KnowledgeSyncService $syncService = new KnowledgeSyncService(),
        private readonly OperationLogService $operationLogService = new OperationLogService()
    ) {
    }

    public function listDocuments(array $query = []): array
    {
        return $this->documentRepository->list($query);
    }

    public function documentDetail(int $id, int $chunkPreviewLimit = 20): array
    {
        $document = $this->documentRepository->find($id);
        if ($document === null) {
            throw new BusinessException('知识库文档不存在', ErrorCode::NOT_FOUND);
        }

        return [
            'document' => $document,
            'chunks' => $this->chunkRepository->listByDocumentId($id, $chunkPreviewLimit),
        ];
    }

    public function createManual(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));
        $languageCode = strtolower(trim((string) ($input['language_code'] ?? 'zh')));
        $tags = is_array($input['tags'] ?? null) ? $input['tags'] : [];

        if ($title === '' || $content === '') {
            throw new BusinessException('知识库文档标题不能为空', ErrorCode::INVALID_PARAMS);
        }

        if (!in_array($languageCode, ['zh', 'en'], true)) {
            $languageCode = 'zh';
        }

        $document = $this->documentRepository->create([
            'title' => $title,
            'source_type' => 'manual',
            'source_id' => null,
            'file_path' => '',
            'language_code' => $languageCode,
            'status' => 'pending',
            'chunk_count' => 0,
            'error_message' => '',
            'tags' => $tags,
            'content_hash' => KnowledgeTextHelper::contentHash($content),
        ]);

        $indexed = $this->ingestionService->ingestDocument((int) ($document['id'] ?? 0), $content);
        $this->operationLogService->recordCurrentAction('system', 'system.knowledge.create', 'knowledge_document', (string) ($document['id'] ?? ''), '知识库文档已创建');

        return $indexed;
    }

    public function createFromUpload(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $filePath = trim((string) ($input['file_path'] ?? ''));
        $languageCode = strtolower(trim((string) ($input['language_code'] ?? 'zh')));

        if ($title === '' || $filePath === '') {
            throw new BusinessException('title 或 file_path 不能为空', ErrorCode::INVALID_PARAMS);
        }

        $content = $this->ingestionService->readTextFromFile($filePath);
        $document = $this->documentRepository->create([
            'title' => $title,
            'source_type' => 'upload',
            'source_id' => null,
            'file_path' => $filePath,
            'language_code' => in_array($languageCode, ['zh', 'en'], true) ? $languageCode : 'zh',
            'status' => 'pending',
            'chunk_count' => 0,
            'error_message' => '',
            'tags' => [],
            'content_hash' => KnowledgeTextHelper::contentHash($content),
        ]);

        $indexed = $this->ingestionService->ingestDocument((int) ($document['id'] ?? 0), $content);
        $this->operationLogService->recordCurrentAction('system', 'system.knowledge.upload', 'knowledge_document', (string) ($document['id'] ?? ''), '知识库文档已上传');

        return $indexed;
    }

    public function updateDocument(int $id, array $input): array
    {
        $document = $this->documentRepository->find($id);
        if ($document === null) {
            throw new BusinessException('知识库文档不存在', ErrorCode::NOT_FOUND);
        }

        $payload = [
            'title' => trim((string) ($input['title'] ?? $document['title'])),
            'language_code' => strtolower(trim((string) ($input['language_code'] ?? $document['language_code']))),
            'status' => trim((string) ($input['status'] ?? $document['status'])),
            'tags' => is_array($input['tags'] ?? null) ? $input['tags'] : ($document['tags'] ?? []),
        ];

        if (!in_array($payload['status'], ['pending', 'indexed', 'failed', 'disabled'], true)) {
            $payload['status'] = $document['status'];
        }

        $updated = $this->documentRepository->update($id, $payload);
        $this->operationLogService->recordCurrentAction('system', 'system.knowledge.update', 'knowledge_document', (string) $id, '知识库文档已更新');

        return $updated ?? $document;
    }

    public function deleteDocument(int $id): array
    {
        $document = $this->documentRepository->find($id);
        if ($document === null) {
            throw new BusinessException('知识库文档不存在', ErrorCode::NOT_FOUND);
        }

        $this->documentRepository->delete($id);
        $this->operationLogService->recordCurrentAction('system', 'system.knowledge.delete', 'knowledge_document', (string) $id, '知识库文档已删除');

        return ['deleted' => 1, 'id' => $id];
    }

    public function reindexDocument(int $id, ?string $content = null): array
    {
        $document = $this->documentRepository->find($id);
        if ($document === null) {
            throw new BusinessException('知识库文档不存在', ErrorCode::NOT_FOUND);
        }

        $sourceType = (string) ($document['source_type'] ?? '');
        if ($content === null || trim($content) === '') {
            if (in_array($sourceType, ['product', 'solution', 'article'], true)) {
                $summary = $this->syncService->syncCmsContent(['types' => [$sourceType]]);

                return $this->documentRepository->find($id) ?? array_merge($document, ['sync_summary' => $summary]);
            }
            if ($sourceType === 'upload' && trim((string) ($document['file_path'] ?? '')) !== '') {
                $content = $this->ingestionService->readTextFromFile((string) $document['file_path']);
            } else {
                throw new BusinessException('该文档当前状态不允许重新索引', ErrorCode::INVALID_PARAMS);
            }
        }

        $indexed = $this->ingestionService->ingestDocument($id, $content);
        $this->operationLogService->recordCurrentAction('system', 'system.knowledge.reindex', 'knowledge_document', (string) $id, '知识库文档已重新索引');

        return $indexed;
    }

    public function syncCms(array $options = []): array
    {
        $summary = $this->syncService->syncCmsContent($options);
        $this->operationLogService->recordCurrentAction('system', 'system.knowledge.sync_cms', 'knowledge_document', 'cms', 'CMS 内容已同步到知识库');

        return $summary;
    }

    public function reindexAll(): array
    {
        $cmsSummary = $this->syncService->syncCmsContent();
        $result = $this->documentRepository->list(['page' => 1, 'page_size' => 500]);
        $items = is_array($result['items'] ?? null) ? $result['items'] : [];
        $summary = [
            'success' => (int) (($cmsSummary['created'] ?? 0) + ($cmsSummary['updated'] ?? 0)),
            'failed' => (int) ($cmsSummary['failed'] ?? 0),
            'skipped' => (int) ($cmsSummary['skipped'] ?? 0),
            'cms_sync' => $cmsSummary,
            'upload_reindexed' => 0,
            'manual_skipped' => 0,
            'total_documents' => count($items),
        ];

        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            try {
                if (($item['source_type'] ?? '') === 'manual') {
                    $summary['manual_skipped']++;
                    $summary['skipped']++;
                    continue;
                }
                if (($item['source_type'] ?? '') === 'upload') {
                    $this->reindexDocument($id);
                    $summary['upload_reindexed']++;
                    $summary['success']++;
                }
            } catch (\Throwable) {
                $summary['failed']++;
            }
        }

        $this->operationLogService->recordCurrentAction('system', 'system.knowledge.reindex_all', 'knowledge_document', 'all', '全部知识库文档已重新索引');

        return $summary;
    }
}
