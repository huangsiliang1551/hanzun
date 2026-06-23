<?php

declare(strict_types=1);

namespace app\service\knowledge;

final class KnowledgeTextHelper
{
    public static function normalizeText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\r\n?/', "\n", $text) ?? $text;
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<int, string>
     */
    public static function chunkText(string $text, int $chunkSize = 680, int $overlap = 80): array
    {
        $text = self::normalizeText($text);
        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) <= $chunkSize) {
            return [$text];
        }

        $paragraphs = preg_split("/\n{2,}/", $text) ?: [$text];
        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                continue;
            }

            $candidate = $buffer === '' ? $paragraph : ($buffer . "\n\n" . $paragraph);
            if (mb_strlen($candidate) <= $chunkSize) {
                $buffer = $candidate;
                continue;
            }

            if ($buffer !== '') {
                $chunks = array_merge($chunks, self::splitLongSegment($buffer, $chunkSize, $overlap));
                $buffer = '';
            }

            if (mb_strlen($paragraph) <= $chunkSize) {
                $buffer = $paragraph;
                continue;
            }

            $chunks = array_merge($chunks, self::splitLongSegment($paragraph, $chunkSize, $overlap));
        }

        if ($buffer !== '') {
            $chunks = array_merge($chunks, self::splitLongSegment($buffer, $chunkSize, $overlap));
        }

        return array_values(array_filter($chunks, static fn (string $item): bool => trim($item) !== ''));
    }

    /**
     * @return array<int, string>
     */
    private static function splitLongSegment(string $text, int $chunkSize, int $overlap): array
    {
        $segments = [];
        $length = mb_strlen($text);
        $offset = 0;

        while ($offset < $length) {
            $piece = mb_substr($text, $offset, $chunkSize);
            $segments[] = trim($piece);
            if ($offset + $chunkSize >= $length) {
                break;
            }
            $offset += max(1, $chunkSize - $overlap);
        }

        return $segments;
    }

    /**
     * @return array<int, string>
     */
    public static function extractKeywords(string $text, int $limit = 24): array
    {
        $text = mb_strtolower(self::normalizeText($text));
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = [
            'the', 'and', 'for', 'with', 'from', 'that', 'this', 'your', 'you', 'are', 'our', 'can', 'will',
            '的', '了', '和', '是', '在', '我们', '您', '你', '请', '可以', '一个', '进行', '以及', '对于', '如果',
        ];
        $scores = [];

        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || mb_strlen($token) < 2) {
                continue;
            }
            if (in_array($token, $stopWords, true)) {
                continue;
            }
            $scores[$token] = ($scores[$token] ?? 0) + 1;
        }

        arsort($scores);

        return array_slice(array_keys($scores), 0, max(1, $limit));
    }

    public static function estimateTokens(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        return (int) max(1, ceil(mb_strlen($text) / 3));
    }

    public static function contentHash(string $text): string
    {
        return hash('sha256', self::normalizeText($text));
    }
}
