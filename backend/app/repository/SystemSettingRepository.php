<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class SystemSettingRepository
{
    private const LEGACY_PROMPT_MAPPINGS = [
        'chat' => [
            'system' => [
                'You are a bakery equipment AI sales assistant. Return JSON only with keys: reply, intent_code, contains_contact_info, contact_name, company_name, email, phone, whatsapp, country_code, product_interest, solution_interest, requirement_summary.',
            ],
        ],
        'chat.rag' => [
            'system' => [
                "You are the HANZUN international sales assistant for bakery and food production equipment.\n\nWhen knowledge base excerpts are appended to this prompt:\n1. Answer primarily from those excerpts. Do not invent MOQ, pricing, lead time, voltage, shipping cost, certifications, or technical specs absent from the excerpts.\n2. Keep replies concise, professional, and suitable for export customers.\n3. If excerpts do not cover the question, politely say you need more details (product model, quantity, destination country) and invite contact info for sales follow-up.\n4. You must still return the required JSON object with reply, intent_code, contains_contact_info, contact fields, product_interest, solution_interest, and requirement_summary.",
            ],
        ],
        'translation' => [
            'system' => [
                'You are a website localization engine. Translate Simplified Chinese content into the target language. Keep HTML tags and line breaks. Return JSON only.',
            ],
        ],
        'cms_polish' => [
            'polish_summary' => [
                'You are a Chinese industrial website content editor. Rewrite the summary into concise, natural, customer-facing Chinese. Keep the original facts, product scope, and meaning unchanged. Do not invent data, certifications, or performance claims. Return plain text only.',
            ],
            'polish_content' => [
                'You are a Chinese industrial website content editor for bakery and food processing machinery. Improve the?? readability, wording, and structure while preserving all original facts, technical intent, and HTML structure when present. You may reorganize paragraphs and headings, but do not invent specifications, prices, lead times, certifications, or customer names. Return the polished content only.',
            ],
        ],
    ];

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'SELECT setting_value
                 FROM system_settings
                 WHERE setting_group = :setting_group AND setting_key = :setting_key
                 LIMIT 1'
            );
            $statement->execute([
                'setting_group' => $group,
                'setting_key' => $key,
            ]);
            $row = $statement->fetch();
            if (is_array($row) && array_key_exists('setting_value', $row)) {
                return $this->normalizeValue($group, $key, json_decode((string) $row['setting_value'], true), $default);
            }

            return $this->normalizeValue($group, $key, $default, $default);
        }

        $storage = $this->readRuntimeStorage();
        $value = $storage[$group][$key] ?? $default;

        return $this->normalizeValue($group, $key, $value, $default);
    }

    public function put(string $group, string $key, mixed $value): mixed
    {
        $value = $this->normalizeValue($group, $key, $value, $value);
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO system_settings (setting_group, setting_key, setting_value, updated_at)
                 VALUES (:setting_group, :setting_key, :setting_value, NOW())
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
            );
            $statement->execute([
                'setting_group' => $group,
                'setting_key' => $key,
                'setting_value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return $this->get($group, $key, $value);
        }

        $storage = $this->readRuntimeStorage();
        if (!isset($storage[$group]) || !is_array($storage[$group])) {
            $storage[$group] = [];
        }
        $storage[$group][$key] = $value;
        $this->writeRuntimeStorage($storage);

        return $this->get($group, $key, $value);
    }

    public function deepseekConfig(): array
    {
        $config = $this->get('deepseek', 'config', []);

        $resolved = is_array($config) ? $config : $this->deepseekConfigDefaults();
        $runtimeOverride = $this->readRuntimeStorage()['deepseek']['config'] ?? null;
        if ($this->shouldUseRuntimeDeepseekFallback($resolved, $runtimeOverride)) {
            $resolved = $this->normalizeValue('deepseek', 'config', $runtimeOverride, $this->deepseekConfigDefaults());
        }

        return is_array($resolved) ? $resolved : $this->deepseekConfigDefaults();
    }

    public function siteConfig(): array
    {
        $config = $this->get('site', 'config', []);

        return is_array($config) ? $config : $this->siteConfigDefaults();
    }

    public function deepseekPrompt(string $feature, string $template = 'system'): string
    {
        $config = $this->deepseekConfig();
        $templates = $config['prompts'][$feature] ?? null;
        if (!is_array($templates)) {
            return '';
        }

        return trim((string) ($templates[$template] ?? ''));
    }

    public function deepseekConfigDefaults(): array
    {
        return [
            'base_url' => 'https://dashscope.aliyuncs.com/compatible-mode/v1',
            'model' => 'qwen-plus',
            'api_key' => '',
            'timeout_seconds' => 90,
            'retry_times' => 2,
            'chat_enabled' => 1,
            'translation_enabled' => 1,
            'seo_enabled' => 1,
            'seo_generate_enabled' => 1,
            'knowledge_enabled' => 1,
            'knowledge_top_k' => 5,
            'knowledge_max_chars' => 128000,
            'knowledge_auto_sync_cms' => 1,
            'chat_max_history_messages' => 6,
            'prompts' => [
                'chat' => [
                    'system' => '你是涵尊官网的烘焙设备销售助理。请根据客户问题给出简洁、专业、利于成交的回复。',
                ],
                'chat.rag' => [
                    'system' => '当系统附带知识库片段时，请优先结合这些资料回答，并保持面向海外客户的专业销售语气。',
                ],
                'seo' => [
                    'system' => '你是工业设备网站的 SEO 编辑。请根据标题、摘要和正文生成适合搜索引擎与客户阅读的内容。',
                ],
                'translation' => [
                    'system' => '你是工业设备网站多语言翻译助手。请准确翻译内容，并保持术语统一、表达自然。',
                ],
                'cms_polish' => [
                    'polish_summary' => '你是中文工业网站内容编辑。请在不改变原意和事实的前提下，对摘要进行精炼润色，使其更自然、专业、面向客户。只返回润色后的文本。',
                    'polish_content' => '你是中文工业网站内容编辑。请在不新增事实、不改动关键信息的前提下，对正文进行结构与表达优化，保留原有 HTML 结构和技术语义。只返回润色后的正文内容。',
                ],
            ],        ];
    }

    public function seoSiteFilesDefaults(): array
    {
        return [
            'robots_content' => "User-agent: *\nAllow: /\nSitemap: https://bagelsmachinery.com/sitemap.xml\n",
            'robots_updated_at' => null,
            'sitemap_last_generated_at' => null,
            'sitemap_route_count' => 0,
            'sitemap_index_count' => 0,
            'sitemap_noindex_count' => 0,
        ];
    }

    public function siteConfigDefaults(): array
    {
        return [
            'site_name' => 'HANZUN',
            'site_title' => '涵尊机械 | HANZUN',
            'logo_url' => '/assets/images/common/logo-110.png',
            'logo_alt' => '涵尊机械',
            'company_name' => '涵尊机械',
            'company_subtitle' => '',
            'meta_description' => '涵尊机械专注烘焙与食品生产线设备，覆盖蛋糕、面包、饼干、夹心、切割、巧克力与食品加工方向。',
            'footer_text' => 'Copyright © 2026 Hanzun (Kunshan) Precision Machinery Manufacturing Co., Ltd. All rights reserved.',
            'language_strategy' => 'ua-first',
            'default_language' => 'zh',
            'social_linkedin' => '',
            'social_youtube' => '',
            'enterprise_video_url' => '',
            'hero_image_url' => '',
            'hero_image_alt' => '',
            'notice_image_url' => '',
            'notice_title' => '',
            'notice_content' => '',
        ];
    }

    private function preferRuntimeStorage(): bool
    {
        if (env_flag('PREFER_RUNTIME_STORAGE')) {
            return true;
        }

        if (env_flag('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK')) {
            return false;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            return false;
        }

        return PHP_SAPI === 'cli' && is_file($this->storagePath());
    }

    private function storagePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/system_settings.json';
    }

    private function readRuntimeStorage(): array
    {
        $path = $this->storagePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeRuntimeStorage(array $storage): void
    {
        $path = $this->storagePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode($storage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function normalizeValue(string $group, string $key, mixed $value, mixed $default): mixed
    {
        if ($group === 'seo' && $key === 'site_files') {
            $base = $this->seoSiteFilesDefaults();
            $config = is_array($value) ? $value : [];

            return array_replace($base, $config);
        }

        if ($group === 'site' && $key === 'config') {
            $base = $this->siteConfigDefaults();
            $config = is_array($value) ? $value : [];
            if (trim((string) ($config['company_subtitle'] ?? '')) === 'Default Subtitle') {
                $config['company_subtitle'] = '';
            }

            return array_replace($base, $config);
        }

        if ($group !== 'deepseek' || $key !== 'config') {
            return $value;
        }

        $base = $this->deepseekConfigDefaults();
        $config = is_array($value) ? $value : [];
        $merged = array_replace_recursive($base, $config);

        if (!isset($merged['prompts']) || !is_array($merged['prompts'])) {
            $merged['prompts'] = [];
        }

        foreach ($base['prompts'] as $feature => $templates) {
            $featureConfig = $merged['prompts'][$feature] ?? [];
            if (!is_array($featureConfig)) {
                $featureConfig = [];
            }

            $normalizedTemplates = [];
            foreach ($templates as $templateKey => $templateValue) {
                $prompt = $this->normalizeDeepseekPromptValue(
                    $feature,
                    $templateKey,
                    (string) ($featureConfig[$templateKey] ?? $templateValue),
                    (string) $templateValue
                );
                $normalizedTemplates[$templateKey] = $prompt !== '' ? $prompt : (string) $templateValue;
            }

            $merged['prompts'][$feature] = array_replace($featureConfig, $normalizedTemplates);
        }

        return $merged;
    }

    private function normalizeDeepseekPromptValue(string $feature, string $templateKey, string $value, string $default): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return $default;
        }

        $legacyValues = self::LEGACY_PROMPT_MAPPINGS[$feature][$templateKey] ?? [];
        if (in_array($normalized, $legacyValues, true)) {
            return $default;
        }

        return $normalized;
    }

    private function shouldUseRuntimeDeepseekFallback(mixed $resolvedConfig, mixed $runtimeConfig): bool
    {
        if (!is_array($resolvedConfig) || !is_array($runtimeConfig)) {
            return false;
        }

        $runtimeApiKey = trim((string) ($runtimeConfig['api_key'] ?? ''));
        if ($runtimeApiKey === '') {
            return false;
        }

        $resolvedApiKey = trim((string) ($resolvedConfig['api_key'] ?? ''));
        $resolvedHasEnabledFeature = max(
            (int) ($resolvedConfig['chat_enabled'] ?? 0),
            (int) ($resolvedConfig['translation_enabled'] ?? 0),
            (int) ($resolvedConfig['seo_enabled'] ?? 0),
            (int) ($resolvedConfig['seo_generate_enabled'] ?? 0)
        ) === 1;

        return $resolvedApiKey === '' || !$resolvedHasEnabledFeature;
    }
}
