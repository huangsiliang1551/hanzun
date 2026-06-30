<?php

declare(strict_types=1);

namespace app\service\knowledge;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\KnowledgeChunkRepository;
use app\repository\KnowledgeDocumentRepository;

final class KnowledgeIngestionService
{
    public function __construct(
        private readonly KnowledgeDocumentRepository $documentRepository = new KnowledgeDocumentRepository(),
        private readonly KnowledgeChunkRepository $chunkRepository = new KnowledgeChunkRepository()
    ) {
    }

    public function ingestDocument(int $documentId, string $rawText): array
    {
        $document = $this->documentRepository->find($documentId);
        if ($document === null) {
            throw new BusinessException('知识库文档不存在', ErrorCode::NOT_FOUND);
        }

        $text = KnowledgeTextHelper::normalizeText($rawText);
        if ($text === '') {
            $this->documentRepository->update($documentId, [
                'status' => 'failed',
                'chunk_count' => 0,
                'error_message' => 'empty content',
                'content_hash' => '',
            ]);

            throw new BusinessException('知识库文本内容不能为空', ErrorCode::INVALID_PARAMS);
        }

        $chunks = KnowledgeTextHelper::chunkText($text);
        $payload = [];
        foreach ($chunks as $index => $chunk) {
            $payload[] = [
                'chunk_index' => $index,
                'content' => $chunk,
                'token_estimate' => KnowledgeTextHelper::estimateTokens($chunk),
                'keywords' => KnowledgeTextHelper::extractKeywords($chunk),
            ];
        }

        $count = $this->chunkRepository->replaceForDocument($documentId, $payload);
        $updated = $this->documentRepository->update($documentId, [
            'status' => 'indexed',
            'chunk_count' => $count,
            'error_message' => '',
            'content_hash' => KnowledgeTextHelper::contentHash($text),
        ]);

        return $updated ?? $document;
    }

    public function readTextFromFile(string $filePath): string
    {
        $path = trim($filePath);
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, '/uploads/')) {
            $absolute = base_path('backend/public' . $path);
        } elseif (str_starts_with($path, 'uploads/')) {
            $absolute = base_path('backend/public/' . $path);
        } else {
            $absolute = base_path(ltrim($path, '/\\'));
        }

        if (!is_file($absolute)) {
            throw new BusinessException('知识库源文件不存在', ErrorCode::NOT_FOUND);
        }

        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        if (!in_array($extension, ['txt', 'md', 'markdown', 'json'], true)) {
            throw new BusinessException('仅支持 txt、md、markdown、json 文件', ErrorCode::INVALID_PARAMS);
        }

        $content = file_get_contents($absolute);
        if ($content === false) {
            throw new BusinessException('知识库文件读取失败', ErrorCode::INTERNAL_ERROR);
        }

        if ($extension === 'json') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                return KnowledgeTextHelper::normalizeText(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }

        return KnowledgeTextHelper::normalizeText($content);
    }
}
