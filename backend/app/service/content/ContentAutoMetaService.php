<?php

declare(strict_types=1);

namespace app\service\content;

use app\repository\SystemSettingRepository;
use app\service\ai\DeepSeekClient;
use app\service\ai\PromptComposer;

final class ContentAutoMetaService
{
    public function __construct(
        private readonly DeepSeekClient $deepSeekClient = new DeepSeekClient(),
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository()
    ) {
    }

    /**
     * @param array{
     *   entity_type:string,
     *   title:string,
     *   category_name?:string,
     *   summary?:string,
     *   content?:string,
     *   seo_title?:string,
     *   seo_keywords?:string,
     *   seo_description?:string,
     *   publish_status?:string
     * } $context
     * @return array{summary:string,seo_title:string,seo_keywords:string,seo_description:string}
     */
    public function enrich(array $context): array
    {
        $title = trim((string) ($context['title'] ?? ''));
        $categoryName = trim((string) ($context['category_name'] ?? ''));
        $summary = trim((string) ($context['summary'] ?? ''));
        $content = (string) ($context['content'] ?? '');
        $seoTitle = trim((string) ($context['seo_title'] ?? ''));
        $seoKeywords = trim((string) ($context['seo_keywords'] ?? ''));
        $seoDescription = trim((string) ($context['seo_description'] ?? ''));

        // AI summary / SEO generation moved to async daemon (TranslationService + SeoService).
        // Calling DeepSeek API here blocks each save for 5-15s. Fallback to content-based logic.
        $plainText = $this->plainText($content);

        return [
            'summary' => $summary !== '' ? $summary : $this->fallbackSummary($plainText, $title),
            'seo_title' => $this->fallbackSeoTitle($seoTitle, $title, $categoryName),
            'seo_keywords' => $this->fallbackSeoKeywords($seoKeywords, $title, $categoryName),
            'seo_description' => $this->fallbackSeoDescription($seoDescription, $summary, $plainText, $title),
        ];
    }

    private function generateSummary(string $entityType, string $title, string $categoryName, string $content): string
    {
        $plainText = $this->plainText($content);
        if ($title === '' && $plainText === '') {
            return '';
        }

        try {
            $response = $this->deepSeekClient->jsonChat([
                [
                    'role' => 'system',
                    'content' => 'You generate concise Chinese summaries for industrial CMS content. Return json only with the key "summary".',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'task' => 'generate_summary',
                        'entity_type' => $entityType,
                        'title' => $title,
                        'category_name' => $categoryName,
                        'content' => $plainText,
                        'requirements' => [
                            'language' => 'zh-CN',
                            'max_chars' => 120,
                            'tone' => 'professional, concise, customer-facing',
                            'rules' => [
                                'Do not invent specifications or certifications.',
                                'Keep the summary suitable for website cards and search snippets.',
                            ],
                        ],
                        'output' => [
                            'format' => 'json',
                            'keys' => ['summary'],
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ], 'seo_enabled');

            $candidate = trim((string) ($response['summary'] ?? ''));
            if ($candidate !== '') {
                return $this->truncate($candidate, 120);
            }
        } catch (\Throwable) {
        }

        return $this->fallbackSummary($plainText, $title);
    }

    /**
     * @return array{seo_title:string,seo_keywords:string,seo_description:string}
     */
    private function generateSeo(string $entityType, string $title, string $categoryName, string $summary, string $content): array
    {
        $plainText = $this->plainText($content);
        $systemPrompt = (new PromptComposer())->composeSeoSystemPrompt();
        if ($systemPrompt === '') {
            $systemPrompt = 'You generate concise SEO metadata for industrial content. Return json only.';
        }

        try {
            $response = $this->deepSeekClient->jsonChat([
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'task' => 'generate_seo_metadata',
                        'entity_type' => $entityType,
                        'title' => $title,
                        'category_name' => $categoryName,
                        'summary' => $summary,
                        'content' => $plainText,
                        'requirements' => [
                            'language' => 'zh-CN',
                            'seo_title_max_chars' => 60,
                            'seo_description_max_chars' => 160,
                            'keyword_count' => '3-8',
                            'rules' => [
                                'Prefer title, category_name, and content as the main signals.',
                                'Do not invent facts not present in the source.',
                            ],
                        ],
                        'output' => [
                            'format' => 'json',
                            'keys' => ['seo_title', 'seo_keywords', 'seo_description'],
                        ],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ], 'seo_enabled');

            return [
                'seo_title' => $this->fallbackSeoTitle(trim((string) ($response['seo_title'] ?? '')), $title, $categoryName),
                'seo_keywords' => $this->fallbackSeoKeywords(trim((string) ($response['seo_keywords'] ?? '')), $title, $categoryName),
                'seo_description' => $this->fallbackSeoDescription(
                    trim((string) ($response['seo_description'] ?? '')),
                    $summary,
                    $plainText,
                    $title
                ),
            ];
        } catch (\Throwable) {
            return [
                'seo_title' => $this->fallbackSeoTitle('', $title, $categoryName),
                'seo_keywords' => $this->fallbackSeoKeywords('', $title, $categoryName),
                'seo_description' => $this->fallbackSeoDescription('', $summary, $plainText, $title),
            ];
        }
    }

    private function fallbackSummary(string $plainText, string $title): string
    {
        $source = trim($plainText) !== '' ? $plainText : $title;
        return $this->truncate($source, 120);
    }

    private function fallbackSeoTitle(string $value, string $title, string $categoryName): string
    {
        if ($value !== '') {
            return $this->truncate($value, 60);
        }

        $base = $categoryName !== '' && mb_strpos($title, $categoryName) === false
            ? $title . ' - ' . $categoryName
            : $title;

        return $this->truncate($base !== '' ? $base : $categoryName, 60);
    }

    private function fallbackSeoKeywords(string $value, string $title, string $categoryName): string
    {
        if ($value !== '') {
            return $value;
        }

        $parts = array_values(array_unique(array_filter([
            $title,
            $categoryName,
        ], static fn (string $item): bool => trim($item) !== '')));

        return implode(', ', $parts);
    }

    private function fallbackSeoDescription(string $value, string $summary, string $plainText, string $title): string
    {
        if ($value !== '') {
            return $this->truncate($value, 160);
        }

        if (trim($summary) !== '') {
            return $this->truncate($summary, 160);
        }

        if (trim($plainText) !== '') {
            return $this->truncate($plainText, 160);
        }

        return $this->truncate($title, 160);
    }

    private function plainText(string $content): string
    {
        $plain = strip_tags($content);
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        return trim($plain);
    }

    private function truncate(string $value, int $limit): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return mb_strlen($value) > $limit ? trim(mb_substr($value, 0, $limit)) : $value;
    }
}
