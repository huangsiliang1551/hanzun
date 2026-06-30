<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\LanguageRepository;
use app\repository\SettingTextRepository;
use app\repository\SystemSettingRepository;
use app\service\ai\DeepSeekClient;
use app\service\ai\PromptComposer;

final class SettingTranslationSyncService
{
    public function __construct(
        private readonly LanguageRepository $languageRepository = new LanguageRepository(),
        private readonly SettingTextRepository $settingTextRepository = new SettingTextRepository(),
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository(),
        private readonly DeepSeekClient $deepSeekClient = new DeepSeekClient(),
        private readonly PromptComposer $promptComposer = new PromptComposer()
    ) {
    }

    public function syncSiteConfig(array $config): void
    {
        $fields = [
            'site_title',
            'company_name',
            'company_subtitle',
            'meta_description',
            'footer_text',
            'logo_alt',
            'hero_image_alt',
            'hero_title',
            'hero_subtitle',
            'hero_cta_primary',
            'hero_cta_secondary',
            'notice_title',
            'notice_content',
            'service_support_title',
            'service_support_line_1',
            'service_support_line_2',
            'service_support_line_3',
            'service_support_line_4',
        ];

        $source = [];
        foreach ($fields as $field) {
            $source[$field] = trim((string) ($config[$field] ?? ''));
        }

        $this->syncSourceFields($source);
    }

    /**
     * 只翻译指定的变化字段（优化：避免每次保存都翻译全部 9 个字段）。
     * key 存 setting_text_translations 表。
     *
     * @param array<string, string> $source 字段名 => 新值
     */
    public function syncSiteConfigFields(array $source): void
    {
        $this->syncSourceFields($source);
    }

    /**
     * 通用翻译+存储逻辑：翻译 source 字段到所有启用语言，写入 setting_text_translations。
     *
     * @param array<string, string> $source 字段名 => 中文原文
     */
    private function syncSourceFields(array $source): void
    {
        $translationsByLanguage = $this->translateFieldMap($source);
        foreach ($source as $field => $_) {
            $languageMap = [];
            foreach ($translationsByLanguage as $languageCode => $translatedFields) {
                $languageMap[$languageCode] = trim((string) ($translatedFields[$field] ?? ''));
            }
            $this->settingTextRepository->upsertTranslations('site_config', $field, $languageMap);
        }
    }

    public function syncPromptConfig(array $prompts): void
    {
        $source = [
            'chat.system' => trim((string) ($prompts['chat']['system'] ?? '')),
            'chat.rag.system' => trim((string) ($prompts['chat.rag']['system'] ?? '')),
            'translation.system' => trim((string) ($prompts['translation']['system'] ?? '')),
            'seo.system' => trim((string) ($prompts['seo']['system'] ?? '')),
        ];

        $translationsByLanguage = $this->translateFieldMap($source);
        foreach ($source as $field => $_) {
            $languageMap = [];
            foreach ($translationsByLanguage as $languageCode => $translatedFields) {
                $languageMap[$languageCode] = trim((string) ($translatedFields[$field] ?? ''));
            }
            $this->settingTextRepository->upsertTranslations('deepseek_prompt', $field, $languageMap);
        }
    }

    public function syncAllCurrentSettings(): void
    {
        $this->syncSiteConfig($this->systemSettingRepository->siteConfig());
        $config = $this->systemSettingRepository->deepseekConfig();
        $this->syncPromptConfig(is_array($config['prompts'] ?? null) ? $config['prompts'] : []);
    }

    public function deleteLanguage(string $languageCode): void
    {
        $this->settingTextRepository->deleteLanguage($languageCode);
    }

    /**
     * @param array<string, string> $source
     * @return array<string, array<string, string>>
     */
    private function translateFieldMap(array $source): array
    {
        $languages = $this->enabledLanguages();
        $result = ['zh' => $source];
        $nonZh = array_values(array_filter($languages, fn($c) => $c !== 'zh'));
        if ($nonZh === []) return $result;

        $nonEmpty = array_filter($source, static fn(string $v): bool => trim($v) !== '');
        if ($nonEmpty === []) return $result;

        // Batch translate ALL languages in ONE API call (was N calls before — 24×4s ≈ 96s)
        try {
            $batchResult = $this->deepSeekClient->jsonChat([
                ['role'=>'system','content'=>$this->promptComposer->composeTranslationSystemPrompt()],
                ['role'=>'user','content'=>json_encode([
                    'task'=>'batch_translate_setting_texts',
                    'target_languages'=>$nonZh,
                    'source_fields'=>$nonEmpty,
                ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)],
            ], 'translation_enabled');

            foreach ($nonZh as $languageCode) {
                $langFields = $batchResult[$languageCode] ?? null;
                if (!is_array($langFields)) {
                    $result[$languageCode] = $source;
                    continue;
                }
                $translated = $source;
                foreach ($nonEmpty as $field => $value) {
                    $candidate = trim((string)($langFields[$field]??''));
                    $translated[$field] = $candidate !== '' ? $candidate : $value;
                }
                $result[$languageCode] = $translated;
            }
        } catch (\Throwable) {
            foreach ($nonZh as $lc) $result[$lc] = $source;
        }

        return $result;
    }

    /**
     * @param array<string, string> $source
     * @return array<string, string>
     */
    private function translateFieldsForLanguage(array $source, string $languageCode): array
    {
        $nonEmpty = array_filter($source, static fn (string $value): bool => trim($value) !== '');
        if ($nonEmpty === []) {
            return $source;
        }

        try {
            $messages = [
                [
                    'role' => 'system',
                    'content' => $this->promptComposer->composeTranslationSystemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'task' => 'translate_setting_texts',
                        'target_language' => $languageCode,
                        'source_fields' => $nonEmpty,
                        'output_keys' => array_values(array_keys($nonEmpty)),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ];

            $response = $this->deepSeekClient->jsonChat($messages, 'translation_enabled');
            $translated = $source;
            foreach ($nonEmpty as $field => $value) {
                $candidate = trim((string) ($response[$field] ?? ''));
                $translated[$field] = $candidate !== '' ? $candidate : $value;
            }

            return $translated;
        } catch (\Throwable) {
            return $source;
        }
    }

    /**
     * @return array<int, string>
     */
    private function enabledLanguages(): array
    {
        $items = [];
        foreach ($this->languageRepository->list() as $language) {
            if ((int) ($language['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $code = strtolower(trim((string) ($language['code'] ?? '')));
            if ($code !== '') {
                $items[] = $code;
            }
        }

        return $items === [] ? ['zh'] : array_values(array_unique($items));
    }
}
