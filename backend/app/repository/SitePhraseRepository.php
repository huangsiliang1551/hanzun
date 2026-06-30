<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class SitePhraseRepository
{
    private const DEFAULT_LABELS = [
        'company_subtitle' => '公司副标题',
        'company_name' => '公司名称',
        'nav_home' => '导航-首页',
        'nav_about' => '导航-介绍',
        'nav_products' => '导航-产品',
        'nav_solutions' => '导航-方案',
        'nav_news' => '导航-新闻',
        'nav_cases' => '导航-案例',
        'nav_contact' => '导航-联系',
        'product_catalog_browse' => '产品-按分类浏览',
        'footer_contact' => '页脚-联系方式',
        'footer_sitemap' => '页脚-站点地图',
        'footer_popular_products' => '页脚-热门产品',
        'footer_popular_solutions' => '页脚-热门生产线',
        'floating_contact' => '悬浮-联系方式',
        'button_get_quote' => '按钮-获取方案',
        'button_get_solution_detail' => '按钮-点击获取详细方案',
        'button_contact_us' => '按钮-联系工厂',
        'button_chat_ai' => '按钮-咨询 AI 客服',
        'button_submit_inquiry' => '按钮-提交联系信息',
        'button_back' => '按钮-返回列表',
        'filter_all' => '筛选-全部',
        'open_navigation' => '导航-打开导航',
        'cooperation_flow_title' => '模块-合作流程',
        'service_capability_heading' => '模块-服务能力',
        'qualifications_title' => '模块-资质证书',
        'form_name' => '表单-姓名',
        'form_email' => '表单-邮箱',
        'form_phone' => '表单-电话',
        'form_message' => '表单-留言',
        'form_contact_name_placeholder' => '表单-请输入联系人',
        'form_phone_with_code' => '表单-国家区号 + 电话',
        'page_products' => '页面-产品目录',
        'page_solutions' => '页面-解决方案',
        'page_news' => '页面-企业新闻',
        'page_cases' => '页面-客户案例',
        'page_contact' => '页面-联系工厂',
        'page_articles' => '页面-新闻与案例',
        'page_pages' => '页面-单页',
        'wechat_hint' => '微信-提示文案',
        'html_sitemap' => '底部HTML站点地图',
        'copyright_suffix' => '版权后缀',
        'view_all' => '导航-查看全部',
        'listing_desc_products' => 'SEO-产品列表描述',
        'listing_desc_solutions' => 'SEO-方案列表描述',
        'listing_desc_news' => 'SEO-新闻列表描述',
        'listing_desc_cases' => 'SEO-案例列表描述',
        'service_support_title' => '模块-服务支持标题',
        'cooperation_flow_step_1' => '流程-需求沟通',
        'cooperation_flow_step_2' => '流程-生产制造',
        'cooperation_flow_step_3' => '流程-调试交付',
        'cooperation_flow_step_4' => '流程-售后支持',
        'service_capability_line_1' => '服务能力-OEM/ODM',
        'service_capability_line_2' => '服务能力-试机验证',
        'service_capability_line_3' => '服务能力-整线集成',
        'service_capability_line_4' => '服务能力-出口项目',
        'support_prompt_cake_label' => '客服快捷-蛋糕生产线',
        'support_prompt_cake_text' => '客服快捷-蛋糕生产线提问',
        'support_prompt_leadtime_label' => '客服快捷-设备交期',
        'support_prompt_leadtime_text' => '客服快捷-设备交期提问',
        'support_prompt_quotation_label' => '客服快捷-整线报价',
        'support_prompt_quotation_text' => '客服快捷-整线报价提问',
        'hero_title' => '主图-主标题',
        'hero_subtitle' => '主图-副标题',
        'hero_cta_primary' => '主图-主按钮',
        'hero_cta_secondary' => '主图-次按钮',
        'nav_toggle' => '导航-展开',
        'solution_catalog_browse' => '方案-按分类浏览',
    ];

    private const DEFAULT_TRANSLATIONS = [
        'company_subtitle' => ['zh' => '', 'en' => ''],
        'company_name' => ['zh' => '涵尊机械', 'en' => 'HANZUN'],
        'nav_home' => ['zh' => '首页', 'en' => 'Home'],
        'nav_about' => ['zh' => '介绍', 'en' => 'About'],
        'nav_products' => ['zh' => '产品', 'en' => 'Products'],
        'nav_solutions' => ['zh' => '方案', 'en' => 'Solutions'],
        'nav_news' => ['zh' => '新闻', 'en' => 'News'],
        'nav_cases' => ['zh' => '案例', 'en' => 'Cases'],
        'nav_contact' => ['zh' => '联系', 'en' => 'Contact'],
        'product_catalog_browse' => ['zh' => '按产品分类查看', 'en' => 'Browse by product categories'],
        'footer_contact' => ['zh' => '联系方式', 'en' => 'Contact'],
        'footer_sitemap' => ['zh' => '站点地图', 'en' => 'Site Map'],
        'footer_popular_products' => ['zh' => '热门产品', 'en' => 'Popular Products'],
        'footer_popular_solutions' => ['zh' => '热门生产线', 'en' => 'Popular Production Lines'],
        'floating_contact' => ['zh' => '在线联系', 'en' => 'Contact'],
        'button_get_quote' => ['zh' => '获取方案', 'en' => 'Get Solution'],
        'button_get_solution_detail' => ['zh' => '点击获取详细方案', 'en' => 'Click for Detailed Solution'],
        'button_contact_us' => ['zh' => '联系工厂', 'en' => 'Contact Factory'],
        'button_chat_ai' => ['zh' => '咨询 AI 客服', 'en' => 'Ask AI Assistant'],
        'button_submit_inquiry' => ['zh' => '提交联系信息', 'en' => 'Submit Inquiry'],
        'button_back' => ['zh' => '返回列表', 'en' => 'Back to List'],
        'filter_all' => ['zh' => '全部', 'en' => 'All'],
        'open_navigation' => ['zh' => '打开导航', 'en' => 'Open navigation'],
        'cooperation_flow_title' => ['zh' => '合作流程', 'en' => 'Cooperation Process'],
        'service_capability_heading' => ['zh' => '服务能力', 'en' => 'Service Capabilities'],
        'qualifications_title' => ['zh' => '资质证书', 'en' => 'Qualifications'],
        'form_name' => ['zh' => '姓名', 'en' => 'Name'],
        'form_email' => ['zh' => '邮箱', 'en' => 'Email'],
        'form_phone' => ['zh' => '电话', 'en' => 'Phone'],
        'form_message' => ['zh' => '留言内容', 'en' => 'Message'],
        'form_contact_name_placeholder' => ['zh' => '请输入联系人', 'en' => 'Please enter your contact name'],
        'form_phone_with_code' => ['zh' => '国家区号 + 电话', 'en' => 'Country code + phone'],
        'page_products' => ['zh' => '产品目录', 'en' => 'Products'],
        'page_solutions' => ['zh' => '解决方案', 'en' => 'Solutions'],
        'page_news' => ['zh' => '企业新闻', 'en' => 'News'],
        'page_cases' => ['zh' => '客户案例', 'en' => 'Cases'],
        'page_contact' => ['zh' => '联系工厂', 'en' => 'Contact'],
        'page_articles' => ['zh' => '新闻与案例', 'en' => 'News & Cases'],
        'page_pages' => ['zh' => '单页', 'en' => 'Pages'],
        'wechat_hint' => ['zh' => '添加微信后可直接沟通工厂团队', 'en' => 'Add WeChat to contact the factory team directly'],
        'html_sitemap' => ['zh' => '站点地图', 'en' => 'Site Map'],
        'copyright_suffix' => ['zh' => '版权所有。', 'en' => 'All rights reserved.'],
        'view_all' => ['zh' => '查看全部 →', 'en' => 'View all →'],
        'listing_desc_products' => ['zh' => '浏览设备产品列表与详细参数信息。', 'en' => 'Browse the product catalog and equipment details.'],
        'listing_desc_solutions' => ['zh' => '浏览整线方案、产能配置与应用场景。', 'en' => 'Browse production line solutions, capacity plans, and applications.'],
        'listing_desc_news' => ['zh' => '查看企业动态、展会资讯与最新新闻。', 'en' => 'Read company updates, exhibitions, and latest news.'],
        'listing_desc_cases' => ['zh' => '查看客户案例、项目成果与交付经验。', 'en' => 'Read customer cases, project results, and delivery experience.'],
        'service_support_title' => ['zh' => '服务能力', 'en' => 'Service Capability'],
        'cooperation_flow_step_1' => ['zh' => '需求沟通', 'en' => 'Requirement Review'],
        'cooperation_flow_step_2' => ['zh' => '生产制造', 'en' => 'Production'],
        'cooperation_flow_step_3' => ['zh' => '调试交付', 'en' => 'Commissioning & Delivery'],
        'cooperation_flow_step_4' => ['zh' => '售后支持', 'en' => 'After-Sales Support'],
        'service_capability_line_1' => ['zh' => 'OEM / ODM 支持', 'en' => 'OEM / ODM Support'],
        'service_capability_line_2' => ['zh' => '试机验证支持', 'en' => 'Trial Verification Support'],
        'service_capability_line_3' => ['zh' => '整线集成支持', 'en' => 'Integrated Line Support'],
        'service_capability_line_4' => ['zh' => '出口项目支持', 'en' => 'Export Project Support'],
        'support_prompt_cake_label' => ['zh' => '蛋糕生产线', 'en' => 'Cake Lines'],
        'support_prompt_cake_text' => ['zh' => '我想咨询蛋糕生产线', 'en' => 'I want to ask about cake production lines.'],
        'support_prompt_leadtime_label' => ['zh' => '设备交期', 'en' => 'Lead Time'],
        'support_prompt_leadtime_text' => ['zh' => '我想了解设备交期', 'en' => 'I want to know the equipment lead time.'],
        'support_prompt_quotation_label' => ['zh' => '整线报价', 'en' => 'Line Quotation'],
        'support_prompt_quotation_text' => ['zh' => '我需要整线报价', 'en' => 'I need a quotation for a complete line.'],
        'hero_title' => ['zh' => '全流程烘焙解决方案', 'en' => 'Full-Process Baking Solutions'],
        'hero_subtitle' => ['zh' => '从研发定制到整线交付', 'en' => 'From R&D customization to full-line delivery'],
        'hero_cta_primary' => ['zh' => '获取方案', 'en' => 'Get Solution'],
        'hero_cta_secondary' => ['zh' => '了解更多', 'en' => 'Learn More'],
        'nav_toggle' => ['zh' => '展开', 'en' => 'Toggle'],
        'solution_catalog_browse' => ['zh' => '按方案分类查看', 'en' => 'Browse by solution categories'],
    ];

    public function list(array $languages = []): array
    {
        $languageCodes = $this->normalizeLanguageCodes($languages);
        $rows = $this->loadRows();
        $grouped = [];
        foreach ($rows as $row) {
            $key = (string) ($row['phrase_key'] ?? '');
            $code = strtolower(trim((string) ($row['language_code'] ?? '')));
            if ($key === '' || $code === '') {
                continue;
            }

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'phrase_key' => $key,
                    'label' => self::DEFAULT_LABELS[$key] ?? $key,
                    'translations' => [],
                ];
            }
            $grouped[$key]['translations'][$code] = (string) ($row['text_value'] ?? '');
        }

        $items = [];
        $keys = array_values(array_unique(array_merge(array_keys(self::DEFAULT_LABELS), array_keys($grouped))));
        sort($keys);
        foreach ($keys as $key) {
            $translations = self::DEFAULT_TRANSLATIONS[$key] ?? [];
            if (isset($grouped[$key]['translations']) && is_array($grouped[$key]['translations'])) {
                $translations = array_merge($translations, $grouped[$key]['translations']);
            }

            foreach ($languageCodes as $code) {
                if (!array_key_exists($code, $translations)) {
                    $translations[$code] = $translations['zh'] ?? '';
                }
            }

            $items[] = [
                'phrase_key' => $key,
                'label' => self::DEFAULT_LABELS[$key] ?? $key,
                'translations' => $translations,
            ];
        }

        return [
            'items' => $items,
            'languages' => $languageCodes,
        ];
    }

    public function replaceAll(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $phraseKey = trim((string) ($item['phrase_key'] ?? ''));
            if ($phraseKey === '') {
                continue;
            }

            $translations = is_array($item['translations'] ?? null) ? $item['translations'] : [];
            foreach ($translations as $languageCode => $textValue) {
                $code = strtolower(trim((string) $languageCode));
                if ($code === '') {
                    continue;
                }

                $normalized[] = [
                    'phrase_key' => $phraseKey,
                    'language_code' => $code,
                    'text_value' => trim((string) $textValue),
                ];
            }
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $pdo->beginTransaction();
            try {
                $pdo->exec('DELETE FROM site_phrase_translations');
                $statement = $pdo->prepare(
                    'INSERT INTO site_phrase_translations (phrase_key, language_code, text_value, updated_at)
                     VALUES (:phrase_key, :language_code, :text_value, NOW())'
                );
                foreach ($normalized as $row) {
                    $statement->execute($row);
                }
                $pdo->commit();
            } catch (\Throwable $exception) {
                $pdo->rollBack();
                throw $exception;
            }
        } else {
            $this->writeRuntimeRows($normalized);
        }

        $languageCodes = [];
        foreach ($normalized as $row) {
            $languageCodes[] = (string) ($row['language_code'] ?? '');
        }

        return $this->list(array_values(array_unique($languageCodes)));
    }

    public function upsertTranslations(string $phraseKey, array $translations): array
    {
        $phraseKey = trim($phraseKey);
        if ($phraseKey === '') {
            return [];
        }

        $normalized = [];
        foreach ($translations as $languageCode => $textValue) {
            $code = strtolower(trim((string) $languageCode));
            if ($code === '') {
                continue;
            }

            $normalized[$code] = trim((string) $textValue);
        }

        if ($normalized === []) {
            return [];
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO site_phrase_translations (phrase_key, language_code, text_value, updated_at)
                 VALUES (:phrase_key, :language_code, :text_value, NOW())
                 ON DUPLICATE KEY UPDATE text_value = VALUES(text_value), updated_at = NOW()'
            );
            foreach ($normalized as $languageCode => $textValue) {
                $statement->execute([
                    'phrase_key' => $phraseKey,
                    'language_code' => $languageCode,
                    'text_value' => $textValue,
                ]);
            }
        } else {
            $rows = $this->readRuntimeRows();
            $kept = array_values(array_filter(
                $rows,
                static fn (array $row): bool => (string) ($row['phrase_key'] ?? '') !== $phraseKey
                    || !isset($normalized[strtolower(trim((string) ($row['language_code'] ?? '')))])
            ));

            foreach ($normalized as $languageCode => $textValue) {
                $kept[] = [
                    'phrase_key' => $phraseKey,
                    'language_code' => $languageCode,
                    'text_value' => $textValue,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }

            $this->writeRuntimeRows($kept);
        }

        return [
            'phrase_key' => $phraseKey,
            'translations' => $normalized,
        ];
    }

    public function getText(string $phraseKey, string $languageCode, string $fallback = ''): string
    {
        $phraseKey = trim($phraseKey);
        $languageCode = strtolower(trim($languageCode));
        if ($phraseKey === '') {
            return $fallback;
        }

        foreach ($this->loadRows() as $row) {
            if ((string) ($row['phrase_key'] ?? '') === $phraseKey
                && strtolower(trim((string) ($row['language_code'] ?? ''))) === $languageCode) {
                $value = trim((string) ($row['text_value'] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        $defaults = self::DEFAULT_TRANSLATIONS[$phraseKey] ?? [];
        if (trim((string) ($defaults[$languageCode] ?? '')) !== '') {
            return trim((string) $defaults[$languageCode]);
        }

        if (trim($fallback) !== '') {
            return trim($fallback);
        }

        if ($languageCode !== 'zh' && trim((string) ($defaults['en'] ?? '')) !== '') {
            return trim((string) $defaults['en']);
        }

        if ($languageCode !== 'zh' && trim((string) ($defaults['zh'] ?? '')) !== '') {
            return trim((string) $defaults['zh']);
        }

        return $fallback;
    }

    public function defaultLabels(): array
    {
        return self::DEFAULT_LABELS;
    }

    public function defaultTranslations(): array
    {
        return self::DEFAULT_TRANSLATIONS;
    }

    private function loadRows(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->query(
                'SELECT phrase_key, language_code, text_value, updated_at
                 FROM site_phrase_translations
                 ORDER BY phrase_key ASC, language_code ASC'
            );
            $rows = $statement->fetchAll();

            return is_array($rows) ? $rows : [];
        }

        return $this->readRuntimeRows();
    }

    private function normalizeLanguageCodes(array $languages): array
    {
        $codes = [];
        foreach ($languages as $language) {
            if (is_array($language)) {
                $codes[] = strtolower(trim((string) ($language['code'] ?? '')));
            } else {
                $codes[] = strtolower(trim((string) $language));
            }
        }

        $codes = array_values(array_filter(array_unique($codes), static fn (string $code): bool => $code !== ''));
        if ($codes === []) {
            $codes = ['zh', 'en'];
        }

        return $codes;
    }

    private function preferRuntimeStorage(): bool
    {
        return (string) env('PREFER_RUNTIME_STORAGE', '0') === '1'
            || (PHP_SAPI === 'cli' && is_file($this->storagePath()));
    }

    private function storagePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/site_phrases.json';
    }

    private function readRuntimeRows(): array
    {
        $path = $this->storagePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeRuntimeRows(array $rows): void
    {
        $path = $this->storagePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
