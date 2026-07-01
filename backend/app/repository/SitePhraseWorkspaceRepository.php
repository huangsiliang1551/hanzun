<?php

declare(strict_types=1);

namespace app\repository;

use app\service\ai\DeepSeekClient;

final class SitePhraseWorkspaceRepository
{
    public function __construct(
        private readonly SitePhraseRepository $sitePhraseRepository = new SitePhraseRepository(),
        private readonly LanguageRepository $languageRepository = new LanguageRepository(),
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository(),
        private readonly DeepSeekClient $deepSeekClient = new DeepSeekClient()
    ) {
    }

    public function overview(string $languageCode, array $filters = []): array
    {
        $languageCode = strtolower(trim($languageCode));
        $catalog = $this->catalog();
        $metaMap = $this->metaMap();
        $all = $this->sitePhraseRepository->list($this->languageRepository->list());
        $keyword = strtolower(trim((string) ($filters['keyword'] ?? '')));
        $module = trim((string) ($filters['module'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $items = [];
        foreach (($all['items'] ?? []) as $item) {
            $phraseKey = (string) ($item['phrase_key'] ?? '');
            if ($phraseKey === '') {
                continue;
            }

            $catalogItem = is_array($catalog[$phraseKey] ?? null) ? $catalog[$phraseKey] : $this->buildCatalogItem($phraseKey, (string) ($item['label'] ?? ''), (string) (($item['translations']['zh'] ?? '')));
            $translations = is_array($item['translations'] ?? null) ? $item['translations'] : [];
            $zhText = trim((string) ($translations['zh'] ?? ''));
            $targetText = trim((string) ($translations[$languageCode] ?? ''));
            $meta = is_array($metaMap[$phraseKey][$languageCode] ?? null) ? $metaMap[$phraseKey][$languageCode] : [];
            $resolvedStatus = $this->resolveStatus($languageCode, $targetText, $zhText, (string) ($meta['status'] ?? ''));
            $sourceType = trim((string) ($meta['source_type'] ?? ($targetText !== '' && $targetText !== $zhText ? 'manual' : 'fallback')));
            $row = [
                'phrase_key' => $phraseKey,
                'label' => (string) ($catalogItem['label'] ?? $phraseKey),
                'module' => (string) ($catalogItem['module'] ?? 'general'),
                'scene' => (string) ($catalogItem['scene'] ?? ''),
                'is_system' => !empty($catalogItem['is_system']) ? 1 : 0,
                'default_text_zh' => $zhText,
                'translation' => $targetText,
                'status' => $resolvedStatus,
                'source_type' => $sourceType,
                'updated_at' => (string) ($meta['updated_at'] ?? ''),
                'has_fallback' => $targetText === '' || $targetText === $zhText ? 1 : 0,
            ];

            if ($module !== '' && $row['module'] !== $module) {
                continue;
            }
            if ($status !== '' && $row['status'] !== $status) {
                continue;
            }
            if ($keyword !== '') {
                $haystack = strtolower(implode(' ', [
                    $row['phrase_key'],
                    $row['label'],
                    $row['module'],
                    $row['scene'],
                    $row['default_text_zh'],
                    $row['translation'],
                ]));
                if (!str_contains($haystack, $keyword)) {
                    continue;
                }
            }

            $items[] = $row;
        }

        usort($items, static function (array $left, array $right): int {
            return strcmp((string) ($left['module'] ?? ''), (string) ($right['module'] ?? ''))
                ?: strcmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        });

        $summary = $this->buildSummary($languageCode);

        return [
            'language_code' => $languageCode,
            'items' => $items,
            'summary' => $summary,
            'module_options' => $this->moduleOptions($catalog),
            'status_options' => ['pending', 'reviewed', 'published'],
            'missing_logs' => $this->missingLogs($languageCode),
        ];
    }

    public function buildSummary(string $languageCode): array
    {
        $languageCode = strtolower(trim($languageCode));
        $all = $this->sitePhraseRepository->list($this->languageRepository->list());
        $metaMap = $this->metaMap();
        $total = 0;
        $completed = 0;
        $pending = 0;

        foreach (($all['items'] ?? []) as $item) {
            $phraseKey = (string) ($item['phrase_key'] ?? '');
            if ($phraseKey === '') {
                continue;
            }

            $translations = is_array($item['translations'] ?? null) ? $item['translations'] : [];
            $zhText = trim((string) ($translations['zh'] ?? ''));
            $targetText = trim((string) ($translations[$languageCode] ?? ''));
            $meta = is_array($metaMap[$phraseKey][$languageCode] ?? null) ? $metaMap[$phraseKey][$languageCode] : [];
            $status = $this->resolveStatus($languageCode, $targetText, $zhText, (string) ($meta['status'] ?? ''));

            $total++;
            if (in_array($status, ['reviewed', 'published'], true)) {
                $completed++;
            } else {
                $pending++;
            }
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'completion_percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
    }

    public function initializeLanguage(string $languageCode): array
    {
        $languageCode = strtolower(trim($languageCode));
        if ($languageCode === '' || $languageCode === 'zh') {
            return $this->overview($languageCode === '' ? 'zh' : $languageCode);
        }

        $current = $this->sitePhraseRepository->list($this->languageRepository->list());
        $metaMap = $this->metaMap();
        $items = [];

        foreach (($current['items'] ?? []) as $item) {
            $phraseKey = (string) ($item['phrase_key'] ?? '');
            if ($phraseKey === '') {
                continue;
            }
            $translations = is_array($item['translations'] ?? null) ? $item['translations'] : [];
            $zhText = trim((string) ($translations['zh'] ?? ''));
            if (!array_key_exists($languageCode, $translations) || trim((string) $translations[$languageCode]) === '') {
                $translations[$languageCode] = $zhText;
            }
            $items[] = [
                'phrase_key' => $phraseKey,
                'translations' => $translations,
            ];

            $existingMeta = is_array($metaMap[$phraseKey][$languageCode] ?? null) ? $metaMap[$phraseKey][$languageCode] : [];
            $metaMap[$phraseKey][$languageCode] = array_merge($existingMeta, [
                'status' => trim((string) ($existingMeta['status'] ?? '')) !== '' ? (string) $existingMeta['status'] : 'pending',
                'source_type' => trim((string) ($existingMeta['source_type'] ?? '')) !== '' ? (string) $existingMeta['source_type'] : 'fallback',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->sitePhraseRepository->replaceAll($items);
        $this->autoTranslateLanguage($languageCode);
        $this->saveMetaMap($metaMap);

        return $this->overview($languageCode);
    }

    public function updateLanguageTranslations(string $languageCode, array $items): array
    {
        $languageCode = strtolower(trim($languageCode));
        if ($languageCode === '') {
            return $this->overview('zh');
        }

        $current = $this->sitePhraseRepository->list($this->languageRepository->list());
        $indexed = [];
        foreach (($current['items'] ?? []) as $item) {
            $phraseKey = (string) ($item['phrase_key'] ?? '');
            if ($phraseKey === '') {
                continue;
            }
            $indexed[$phraseKey] = is_array($item['translations'] ?? null) ? $item['translations'] : [];
        }

        $metaMap = $this->metaMap();
        foreach ($items as $item) {
            $phraseKey = trim((string) ($item['phrase_key'] ?? ''));
            if ($phraseKey === '') {
                continue;
            }

            if (!isset($indexed[$phraseKey])) {
                $indexed[$phraseKey] = ['zh' => trim((string) ($item['default_text_zh'] ?? ''))];
            }

            $textValue = trim((string) ($item['translation'] ?? ''));
            $indexed[$phraseKey][$languageCode] = $textValue !== '' ? $textValue : (string) ($indexed[$phraseKey]['zh'] ?? '');
            $nextStatus = trim((string) ($item['status'] ?? 'reviewed'));
            if (!in_array($nextStatus, ['pending', 'reviewed', 'published'], true)) {
                $nextStatus = 'reviewed';
            }

            if (!isset($metaMap[$phraseKey]) || !is_array($metaMap[$phraseKey])) {
                $metaMap[$phraseKey] = [];
            }
            $metaMap[$phraseKey][$languageCode] = [
                'status' => $nextStatus,
                'source_type' => 'manual',
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }

        $payload = [];
        foreach ($indexed as $phraseKey => $translations) {
            $payload[] = [
                'phrase_key' => $phraseKey,
                'translations' => $translations,
            ];
        }

        $this->sitePhraseRepository->replaceAll($payload);
        $this->saveMetaMap($metaMap);

        return $this->overview($languageCode);
    }

    public function registerPhraseUsage(string $phraseKey, string $fallbackText, string $languageCode, string $module = '', string $scene = ''): void
    {
        $phraseKey = trim($phraseKey);
        $languageCode = strtolower(trim($languageCode));
        if ($phraseKey === '') {
            return;
        }

        $catalog = $this->catalog();
        if (!isset($catalog[$phraseKey])) {
            $catalog[$phraseKey] = $this->buildCatalogItem($phraseKey, $this->sitePhraseRepository->defaultLabels()[$phraseKey] ?? $phraseKey, $fallbackText, $module, $scene);
            $this->saveCatalog($catalog);
        }

        $current = $this->sitePhraseRepository->list($this->languageRepository->list());
        $row = null;
        foreach (($current['items'] ?? []) as $item) {
            if ((string) ($item['phrase_key'] ?? '') === $phraseKey) {
                $row = $item;
                break;
            }
        }

        if ($row === null) {
            $seedTranslations = ['zh' => $fallbackText];
            if ($languageCode !== '' && $languageCode !== 'zh') {
                $seedTranslations[$languageCode] = $fallbackText;
            }
            $this->sitePhraseRepository->upsertTranslations($phraseKey, $seedTranslations);
            $row = [
                'phrase_key' => $phraseKey,
                'translations' => $seedTranslations,
            ];
        }

        $targetText = trim((string) (($row['translations'][$languageCode] ?? '')));
        $zhText = trim((string) (($row['translations']['zh'] ?? $fallbackText)));
        if ($languageCode !== '' && $languageCode !== 'zh' && ($targetText === '' || $targetText === $zhText)) {
            $this->appendMissingLog($phraseKey, $languageCode, $fallbackText, $catalog[$phraseKey]['module'] ?? $module, $catalog[$phraseKey]['scene'] ?? $scene);
            $this->autoTranslatePhraseIfNeeded($phraseKey, $languageCode, $zhText !== '' ? $zhText : $fallbackText);
        }
    }

    public function missingLogs(string $languageCode = '', int $limit = 200): array
    {
        $languageCode = strtolower(trim($languageCode));
        $items = $this->systemSettingRepository->get('site_phrase_workspace', 'missing_logs', []);
        if (!is_array($items)) {
            return [];
        }

        $rows = array_values(array_filter($items, static function (array $item) use ($languageCode): bool {
            if ($languageCode === '') {
                return true;
            }

            return strtolower(trim((string) ($item['language_code'] ?? ''))) === $languageCode;
        }));

        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) ($right['last_seen_at'] ?? ''), (string) ($left['last_seen_at'] ?? ''));
        });

        return array_slice($rows, 0, max(1, $limit));
    }

    public function catalog(): array
    {
        $stored = $this->systemSettingRepository->get('site_phrase_workspace', 'catalog', []);
        $stored = is_array($stored) ? $stored : [];
        $defaults = [];

        foreach ($this->sitePhraseRepository->defaultLabels() as $phraseKey => $label) {
            $defaults[$phraseKey] = $this->buildCatalogItem(
                (string) $phraseKey,
                (string) $label,
                (string) ($this->sitePhraseRepository->defaultTranslations()[$phraseKey]['zh'] ?? ''),
            );
        }

        foreach ($stored as $phraseKey => $item) {
            if (!is_array($item)) {
                continue;
            }
            $defaults[$phraseKey] = array_merge($defaults[$phraseKey] ?? $this->buildCatalogItem((string) $phraseKey, (string) ($item['label'] ?? $phraseKey), (string) ($item['default_text_zh'] ?? '')), $item);
        }

        ksort($defaults);

        return $defaults;
    }

    private function appendMissingLog(string $phraseKey, string $languageCode, string $fallbackText, string $module, string $scene): void
    {
        $items = $this->systemSettingRepository->get('site_phrase_workspace', 'missing_logs', []);
        $items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
        $now = date('Y-m-d H:i:s');
        $matched = false;

        foreach ($items as $index => $item) {
            if ((string) ($item['phrase_key'] ?? '') !== $phraseKey || (string) ($item['language_code'] ?? '') !== $languageCode) {
                continue;
            }

            $items[$index]['fallback_text'] = $fallbackText;
            $items[$index]['module'] = $module;
            $items[$index]['scene'] = $scene;
            $items[$index]['last_seen_at'] = $now;
            $items[$index]['hit_count'] = (int) ($item['hit_count'] ?? 0) + 1;
            $matched = true;
            break;
        }

        if (!$matched) {
            $items[] = [
                'phrase_key' => $phraseKey,
                'language_code' => $languageCode,
                'fallback_text' => $fallbackText,
                'module' => $module,
                'scene' => $scene,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'hit_count' => 1,
            ];
        }

        $this->systemSettingRepository->put('site_phrase_workspace', 'missing_logs', $items);
    }

    private function autoTranslateLanguage(string $languageCode): void
    {
        $languageCode = strtolower(trim($languageCode));
        if ($languageCode === '' || $languageCode === 'zh') {
            return;
        }

        $all = $this->sitePhraseRepository->list($this->languageRepository->list());
        foreach (($all['items'] ?? []) as $item) {
            $phraseKey = (string) ($item['phrase_key'] ?? '');
            if ($phraseKey === '') {
                continue;
            }

            $translations = is_array($item['translations'] ?? null) ? $item['translations'] : [];
            $zhText = trim((string) ($translations['zh'] ?? ''));
            $targetText = trim((string) ($translations[$languageCode] ?? ''));
            if ($zhText === '' || ($targetText !== '' && $targetText !== $zhText)) {
                continue;
            }

            $this->autoTranslatePhraseIfNeeded($phraseKey, $languageCode, $zhText);
        }
    }

    private function autoTranslatePhraseIfNeeded(string $phraseKey, string $languageCode, string $zhText): void
    {
        $phraseKey = trim($phraseKey);
        $languageCode = strtolower(trim($languageCode));
        $zhText = trim($zhText);
        if ($phraseKey === '' || $languageCode === '' || $languageCode === 'zh' || $zhText === '') {
            return;
        }

        $config = $this->systemSettingRepository->deepseekConfig();
        if ((int) ($config['translation_enabled'] ?? 1) !== 1) {
            return;
        }

        $current = $this->sitePhraseRepository->list($this->languageRepository->list());
        $row = null;
        foreach (($current['items'] ?? []) as $item) {
            if ((string) ($item['phrase_key'] ?? '') === $phraseKey) {
                $row = $item;
                break;
            }
        }

        $translations = is_array($row['translations'] ?? null) ? $row['translations'] : [];
        $existingTarget = trim((string) ($translations[$languageCode] ?? ''));
        if ($existingTarget !== '' && $existingTarget !== $zhText) {
            return;
        }

        try {
            $response = $this->deepSeekClient->jsonChat([
                [
                    'role' => 'system',
                    'content' => (new \app\service\ai\PromptComposer())->composeTranslationSystemPrompt(),
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'task' => 'translate',
                        'entity_type' => 'site_phrase',
                        'target_language' => $languageCode,
                        'source_fields' => [
                            'text_value' => $zhText,
                        ],
                        'output_keys' => ['text_value'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ], 'translation_enabled');
        } catch (\Throwable) {
            return;
        }

        $translated = trim((string) ($response['text_value'] ?? ''));
        if ($translated === '' || $translated === $zhText) {
            return;
        }

        $this->sitePhraseRepository->upsertTranslations($phraseKey, [
            'zh' => $zhText,
            $languageCode => $translated,
        ]);

        $metaMap = $this->metaMap();
        if (!isset($metaMap[$phraseKey]) || !is_array($metaMap[$phraseKey])) {
            $metaMap[$phraseKey] = [];
        }
        $metaMap[$phraseKey][$languageCode] = array_merge(
            is_array($metaMap[$phraseKey][$languageCode] ?? null) ? $metaMap[$phraseKey][$languageCode] : [],
            [
                'status' => 'reviewed',
                'source_type' => 'ai',
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
        $this->saveMetaMap($metaMap);
    }

    private function metaMap(): array
    {
        $items = $this->systemSettingRepository->get('site_phrase_workspace', 'meta', []);

        return is_array($items) ? $items : [];
    }

    private function saveMetaMap(array $metaMap): void
    {
        $this->systemSettingRepository->put('site_phrase_workspace', 'meta', $metaMap);
    }

    private function saveCatalog(array $catalog): void
    {
        $this->systemSettingRepository->put('site_phrase_workspace', 'catalog', $catalog);
    }

    private function buildCatalogItem(string $phraseKey, string $label, string $defaultTextZh, string $module = '', string $scene = ''): array
    {
        if ($module === '' || $scene === '') {
            [$resolvedModule, $resolvedScene] = $this->inferPhrasePlacement($phraseKey);
            $module = $module !== '' ? $module : $resolvedModule;
            $scene = $scene !== '' ? $scene : $resolvedScene;
        }

        return [
            'phrase_key' => $phraseKey,
            'label' => $label !== '' ? $label : $phraseKey,
            'module' => $module,
            'scene' => $scene,
            'default_text_zh' => $defaultTextZh,
            'is_system' => 1,
        ];
    }

    private function inferPhrasePlacement(string $phraseKey): array
    {
        if (str_starts_with($phraseKey, 'nav_')) {
            return ['navigation', 'header'];
        }
        if (str_starts_with($phraseKey, 'footer_') || $phraseKey === 'html_sitemap' || $phraseKey === 'copyright_suffix') {
            return ['footer', 'footer'];
        }
        if (str_starts_with($phraseKey, 'button_')) {
            return ['action', 'button'];
        }
        if (str_starts_with($phraseKey, 'form_')) {
            return ['form', 'field'];
        }
        if (str_starts_with($phraseKey, 'page_')) {
            return ['page', 'title'];
        }
        if ($phraseKey === 'company_name') {
            return ['site', 'global'];
        }
        if ($phraseKey === 'back_to_top' || str_starts_with($phraseKey, 'floating_')) {
            return ['site', 'floating_contact'];
        }

        return ['general', 'general'];
    }

    private function resolveStatus(string $languageCode, string $targetText, string $zhText, string $storedStatus): string
    {
        if ($languageCode === 'zh') {
            return 'published';
        }

        if (in_array($storedStatus, ['reviewed', 'published'], true) && $targetText !== '' && $targetText !== $zhText) {
            return $storedStatus;
        }

        return $targetText !== '' && $targetText !== $zhText ? 'reviewed' : 'pending';
    }

    private function moduleOptions(array $catalog): array
    {
        $modules = [];
        foreach ($catalog as $item) {
            $module = trim((string) ($item['module'] ?? ''));
            if ($module !== '') {
                $modules[$module] = $module;
            }
        }
        ksort($modules);

        return array_values($modules);
    }
}
