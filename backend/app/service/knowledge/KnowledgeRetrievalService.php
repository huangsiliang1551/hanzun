<?php

declare(strict_types=1);

namespace app\service\knowledge;

use app\repository\KnowledgeChunkRepository;
use app\repository\KnowledgeDocumentRepository;
use app\repository\SystemSettingRepository;

final class KnowledgeRetrievalService
{
    public function __construct(
        private readonly KnowledgeDocumentRepository $documentRepository = new KnowledgeDocumentRepository(),
        private readonly KnowledgeChunkRepository $chunkRepository = new KnowledgeChunkRepository(),
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository()
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function retrieve(string $query, string $languageCode = '', array $options = []): array
    {
        $config = $this->systemSettingRepository->deepseekConfig();
        if ((int) ($config['knowledge_enabled'] ?? 0) !== 1) {
            return [];
        }

        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $topK = max(1, min(20, (int) ($options['top_k'] ?? $config['knowledge_top_k'] ?? 5)));
        $maxChars = max(500, min(128000, (int) ($options['max_chars'] ?? $config['knowledge_max_chars'] ?? 128000)));
        $sourcePage = trim((string) ($options['source_page'] ?? ''));

        $documents = $this->documentRepository->listIndexedForRetrieval($languageCode);
        if ($documents === []) {
            return [];
        }

        $boostDocumentIds = $this->boostDocumentIdsFromPage($documents, $sourcePage);
        $documentIds = array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $documents);
        $candidates = $this->chunkRepository->searchCandidates($documentIds, 500);
        if ($candidates === []) {
            return [];
        }

        $terms = $this->tokenizeQuery($query);
        $scored = [];

        foreach ($candidates as $candidate) {
            $score = $this->scoreChunk($candidate, $terms);
            $documentId = (int) ($candidate['document_id'] ?? 0);
            if (in_array($documentId, $boostDocumentIds, true)) {
                $score += 8;
            }
            if ($score <= 0) {
                continue;
            }
            $candidate['score'] = $score;
            $scored[] = $candidate;
        }

        if ($scored === []) {
            return [];
        }

        usort($scored, static function (array $left, array $right): int {
            return ((float) ($right['score'] ?? 0)) <=> ((float) ($left['score'] ?? 0));
        });

        $selected = [];
        $usedChars = 0;
        $seenDocuments = [];

        foreach ($scored as $item) {
            if (count($selected) >= $topK) {
                break;
            }

            $content = (string) ($item['content'] ?? '');
            $contentLength = mb_strlen($content);
            if ($usedChars + $contentLength > $maxChars && $selected !== []) {
                continue;
            }

            $documentId = (int) ($item['document_id'] ?? 0);
            if (isset($seenDocuments[$documentId]) && count($selected) >= 2) {
                continue;
            }

            $selected[] = [
                'chunk_id' => (int) ($item['id'] ?? 0),
                'document_id' => $documentId,
                'title' => (string) ($item['document_title'] ?? ''),
                'source_type' => (string) ($item['source_type'] ?? ''),
                'source_id' => $item['source_id'] ?? null,
                'language_code' => (string) ($item['language_code'] ?? 'zh'),
                'content' => $content,
                'score' => (float) ($item['score'] ?? 0),
                'url' => $this->buildSourceUrl((string) ($item['source_type'] ?? ''), $item['source_id'] ?? null),
            ];
            $usedChars += $contentLength;
            $seenDocuments[$documentId] = true;
        }

        return $selected;
    }

    /**
     * @param array<int, array<string, mixed>> $chunks
     */
    public function buildContextPrompt(array $chunks): string
    {
        if ($chunks === []) {
            return '';
        }

        $lines = [
            '以下是从企业知识库检索到的参考内容，请优先基于这些内容回答。若内容不足以回答，请明确说明并引导客户留下联系方式，不要编造价格、交期或技术参数。',
        ];

        foreach ($chunks as $index => $chunk) {
            $title = trim((string) ($chunk['title'] ?? 'Knowledge'));
            $content = trim((string) ($chunk['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $lines[] = '[Reference ' . ($index + 1) . ': ' . $title . "]\n" . $content;
        }

        return implode("\n\n", $lines);
    }

    /**
     * @return array<int, string>
     */
    private function tokenizeQuery(string $query): array
    {
        $query = mb_strtolower(KnowledgeTextHelper::normalizeText($query));
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $cjkTerms = [];

        if (preg_match_all('/[\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}]+/u', $query, $matches)) {
            foreach ($matches[0] as $segment) {
                $segment = trim((string) $segment);
                $length = mb_strlen($segment);
                if ($length < 2) {
                    continue;
                }

                $cjkTerms[] = $segment;
                foreach ([2, 3, 4] as $window) {
                    if ($length < $window) {
                        continue;
                    }
                    for ($index = 0; $index <= $length - $window; $index++) {
                        $cjkTerms[] = mb_substr($segment, $index, $window);
                    }
                }
            }
        }

        $tokens = array_merge($tokens, $cjkTerms);

        return array_values(array_unique(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen(trim($token)) >= 2
        )));
    }

    /**
     * @param array<string, mixed> $chunk
     * @param array<int, string> $terms
     */
    private function scoreChunk(array $chunk, array $terms): float
    {
        if ($terms === []) {
            return 0;
        }

        $haystack = mb_strtolower((string) ($chunk['content'] ?? ''));
        $keywords = array_map('strval', is_array($chunk['keywords'] ?? null) ? $chunk['keywords'] : []);
        $keywordHaystack = mb_strtolower(implode(' ', $keywords));
        $titleHaystack = mb_strtolower((string) ($chunk['document_title'] ?? ''));

        $score = 0.0;
        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }
            if ($titleHaystack !== '' && str_contains($titleHaystack, $term)) {
                $score += 4;
            }
            if ($keywordHaystack !== '' && str_contains($keywordHaystack, $term)) {
                $score += 3;
            }
            if ($haystack !== '' && str_contains($haystack, $term)) {
                $score += 2;
            }
        }

        return $score;
    }

    /**
     * @param array<int, array<string, mixed>> $documents
     * @return array<int, int>
     */
    private function boostDocumentIdsFromPage(array $documents, string $sourcePage): array
    {
        $sourcePage = trim($sourcePage);
        if ($sourcePage === '') {
            return [];
        }

        $slug = trim(basename(parse_url($sourcePage, PHP_URL_PATH) ?: ''), '/');
        if ($slug === '') {
            return [];
        }

        $boost = [];
        foreach ($documents as $document) {
            $sourceType = (string) ($document['source_type'] ?? '');
            if (!in_array($sourceType, ['product', 'solution', 'article'], true)) {
                continue;
            }
            $tags = is_array($document['tags'] ?? null) ? $document['tags'] : [];
            $tagSlug = (string) ($tags['slug'] ?? '');
            if ($tagSlug !== '' && ($tagSlug === $slug || str_contains($sourcePage, $tagSlug))) {
                $boost[] = (int) ($document['id'] ?? 0);
            }
        }

        return $boost;
    }

    private function buildSourceUrl(string $sourceType, mixed $sourceId): string
    {
        $sourceId = (int) $sourceId;
        if ($sourceId <= 0) {
            return '';
        }

        return match ($sourceType) {
            'product' => '/products/' . $sourceId,
            'solution' => '/solutions/' . $sourceId,
            'article' => '/news/' . $sourceId,
            default => '',
        };
    }
}
