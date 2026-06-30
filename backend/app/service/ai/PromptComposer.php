<?php

declare(strict_types=1);

namespace app\service\ai;

use app\repository\SettingTextRepository;
use app\repository\SystemSettingRepository;

final class PromptComposer
{
    private const CHAT_JSON_PROTOCOL = '必须只返回 JSON，字段固定为：reply、intent_code、contains_contact_info、contact_name、company_name、email、phone、whatsapp、country_code、product_interest、solution_interest、requirement_summary。';
    private const TRANSLATION_JSON_PROTOCOL = '必须只返回 JSON 对象，并严格遵守用户消息中的 output、output_keys、source_fields 字段要求，不能输出任何额外文本。';
    private const SEO_JSON_PROTOCOL = '必须只返回 JSON 对象，并严格包含 seo_title、seo_keywords、seo_description、slug 字段，不能输出任何额外文本。';
    private const LEGACY_PROTOCOL_SNIPPETS = [
        '必须只返回 JSON，字段固定为：reply、intent_code、contains_contact_info、contact_name、company_name、email、phone、whatsapp、country_code、product_interest、solution_interest、requirement_summary。',
        '必须只返回 JSON 对象，并严格遵守用户消息中的 output、output_keys、source_fields 字段要求，不能输出任何额外文本。',
        '必须只返回 JSON 对象，并严格包含 seo_title、seo_keywords、seo_description、slug 字段，不能输出任何额外文本。',
        'Return JSON only',
        'Return only JSON',
        'must return JSON only',
        'reply、intent_code、contains_contact_info、contact_name、company_name、email、phone、whatsapp、country_code、product_interest、solution_interest、requirement_summary',
        'seo_title、seo_keywords、seo_description、slug',
        'output、output_keys、source_fields',
    ];

    public function __construct(
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository(),
        private readonly SettingTextRepository $settingTextRepository = new SettingTextRepository()
    ) {
    }

    public function composePublicChatSystemPrompt(string $languageCode): string
    {
        return $this->appendProtocol(
            $this->localizedPrompt('chat', 'system', $languageCode),
            self::CHAT_JSON_PROTOCOL
        );
    }

    public function composePublicChatRagPrompt(string $languageCode): string
    {
        return $this->localizedPrompt('chat.rag', 'system', $languageCode);
    }

    public function composeTranslationSystemPrompt(): string
    {
        return $this->appendProtocol(
            $this->basePrompt('translation', 'system'),
            self::TRANSLATION_JSON_PROTOCOL
        );
    }

    public function composeSeoSystemPrompt(): string
    {
        return $this->appendProtocol(
            $this->basePrompt('seo', 'system'),
            self::SEO_JSON_PROTOCOL
        );
    }

    public function composeEditablePrompt(string $feature, string $template = 'system'): string
    {
        return $this->basePrompt($feature, $template);
    }

    private function localizedPrompt(string $feature, string $template, string $languageCode): string
    {
        $storageKey = $feature . '.' . $template;
        $localized = $this->sanitizeEditablePrompt(
            $this->settingTextRepository->getText('deepseek_prompt', $storageKey, $languageCode, '')
        );
        if ($localized !== '') {
            return $localized;
        }

        return $this->basePrompt($feature, $template);
    }

    private function basePrompt(string $feature, string $template): string
    {
        return $this->sanitizeEditablePrompt($this->systemSettingRepository->deepseekPrompt($feature, $template));
    }

    private function appendProtocol(string $prompt, string $protocol): string
    {
        $prompt = trim($prompt);
        $protocol = trim($protocol);
        if ($prompt === '') {
            return $protocol;
        }

        if ($protocol === '') {
            return $prompt;
        }

        return $prompt . "\n\n" . $protocol;
    }

    private function sanitizeEditablePrompt(string $prompt): string
    {
        $normalized = trim($prompt);
        if ($normalized === '') {
            return '';
        }

        foreach (self::LEGACY_PROTOCOL_SNIPPETS as $snippet) {
            $snippet = trim($snippet);
            if ($snippet === '') {
                continue;
            }

            $normalized = str_ireplace($snippet, '', $normalized);
        }

        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }
}
