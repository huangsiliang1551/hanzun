<?php

declare(strict_types=1);

namespace app\service\seo;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\LanguageRepository;
use app\repository\SeoRepository;
use app\repository\SystemSettingRepository;
use app\service\ai\DeepSeekClient;
use app\service\ai\PromptComposer;
use app\service\StaticPublisher;
use app\service\content\ContentEntityBridge;
use app\service\log\OperationLogService;

final class SeoService
{
    public function __construct(
        private readonly SeoRepository $seoRepository = new SeoRepository(),
        private readonly ContentEntityBridge $contentEntityBridge = new ContentEntityBridge(),
        private readonly LanguageRepository $languageRepository = new LanguageRepository(),
        private readonly DeepSeekClient $deepSeekClient = new DeepSeekClient(),
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository(),
        private readonly OperationLogService $operationLogService = new OperationLogService()
    ) {
    }

    public function jobs(): array
    {
        $languageMap = $this->languageNameMap();
        $items = [];

        foreach ($this->seoRepository->jobs() as $job) {
            $details = $job['language_details'] ?? [];
            if (!is_array($details)) continue;

            foreach ($details as $code => $info) {
                $items[] = [
                    'id' => (int)($job['id'] ?? 0),
                    'entity_type' => (string)($job['entity_type'] ?? ''),
                    'entity_id' => (int)($job['entity_id'] ?? 0),
                    'language_code' => $code,
                    'status' => $info['status'] ?? 'pending',
                    'retry_count' => 0,
                    'error_message' => $info['error'] ?? null,
                    'created_at' => (string)($job['created_at'] ?? ''),
                    'updated_at' => (string)($job['updated_at'] ?? ''),
                    'completed_languages' => (int)($job['completed_languages'] ?? 0),
                    'total_languages' => (int)($job['total_languages'] ?? 0),
                    'failed_languages' => (int)($job['failed_languages'] ?? 0),
                ];
            }
        }

        $decorated = array_map(
            fn(array $item): array => $this->decorateJob($item, $languageMap),
            $items
        );

        return [
            'items' => $decorated,
            'status_options' => ['pending', 'completed', 'generated', 'manual_override', 'failed'],
            'summary' => $this->buildJobSummary($items),
        ];
    }

    public function retry(int $id): array
    {
        $job = $this->seoRepository->findJob($id);
        if ($job === null) {
            throw new BusinessException('SEO 任务不存在', ErrorCode::NOT_FOUND);
        }

        $languages = array_keys(is_array($job['language_details'] ?? null) ? $job['language_details'] : []);
        if ($languages === []) {
            $languages = ['zh'];
        }

        $this->generate([
            'entity_type' => (string) ($job['entity_type'] ?? ''),
            'entity_id' => (int) ($job['entity_id'] ?? 0),
            'language_codes' => $languages,
        ]);

        return $this->seoRepository->findJob($id) ?? [];
    }

    public function routes(): array
    {
        $languageMap = $this->languageNameMap();
        $items = array_map(
            fn(array $route): array => $this->decorateRoute($route, $languageMap),
            $this->seoRepository->routes()
        );

        return [
            'items' => $items,
            'summary' => $this->buildRouteSummary($items),
        ];
    }

    public function updateRoute(int $id, array $input): array
    {
        $route = $this->seoRepository->findRouteById($id);
        if ($route === null) {
            throw new BusinessException('SEO 路由不存在', ErrorCode::NOT_FOUND);
        }

        $entityType = (string) ($route['entity_type'] ?? '');
        $entityId = (int) ($route['entity_id'] ?? 0);
        $languageCode = (string) ($route['language_code'] ?? 'zh');
        $record = $this->contentEntityBridge->find($entityType, $entityId) ?? [];

        $slug = trim((string) ($input['slug'] ?? ($route['slug'] ?? '')));
        $routePath = trim((string) ($input['route_path'] ?? ''));
        if ($slug !== '' && $routePath === '') {
            $normalizedSlug = $this->normalizeSlug($slug, $slug, $entityType);
            $routePath = $this->buildRoutePath($entityType, $record, $languageCode, $normalizedSlug);
            $slug = $normalizedSlug;
        }

        $payload = [
            'route_path' => $routePath !== '' ? $routePath : (string) ($route['route_path'] ?? ''),
            'slug' => $slug !== '' ? $slug : (string) ($route['slug'] ?? ''),
            'seo_title' => array_key_exists('seo_title', $input) ? trim((string) ($input['seo_title'] ?? '')) : (string) ($route['seo_title'] ?? ''),
            'seo_keywords' => array_key_exists('seo_keywords', $input) ? trim((string) ($input['seo_keywords'] ?? '')) : (string) ($route['seo_keywords'] ?? ''),
            'seo_description' => array_key_exists('seo_description', $input) ? trim((string) ($input['seo_description'] ?? '')) : (string) ($route['seo_description'] ?? ''),
            'canonical_url' => array_key_exists('canonical_url', $input) ? trim((string) ($input['canonical_url'] ?? '')) : (string) ($route['canonical_url'] ?? ''),
            'index_status' => trim((string) ($input['index_status'] ?? ($route['index_status'] ?? 'index'))) ?: 'index',
        ];

        $updated = $this->seoRepository->updateRouteById($id, $payload);
        if ($updated === null) {
            throw new BusinessException('SEO 路由更新失败', ErrorCode::INTERNAL_ERROR);
        }

        $job = $this->seoRepository->findEntityJob($entityType, $entityId);
        if ($job !== null) {
            $this->seoRepository->updateLanguageStatus((int) ($job['id'] ?? 0), $languageCode, 'manual_override');
            $this->seoRepository->updateEntityStatus((int) ($job['id'] ?? 0), 'manual_override');
        }

        $this->contentEntityBridge->updateSeoStatus($entityType, $entityId, 'manual_override');
        $this->operationLogService->recordCurrentAction('seo', 'seo.route.update', 'seo_route', $updated, 'SEO 路由已人工更新');

        return $this->decorateRoute($updated, $this->languageNameMap());
    }

    public function fourOhFourLogs(): array
    {
        $items = array_map(function (array $item): array {
            $resolved = (int) ($item['resolved'] ?? 0) === 1;
            return array_merge($item, [
                'fix_status' => $resolved ? 'resolved' : 'pending',
                'note' => (string) ($item['notes'] ?? ''),
            ]);
        }, $this->seoRepository->fourOhFourLogs());

        return [
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'pending' => $this->count404ByStatus($items, ['pending']),
                'resolved' => $this->count404ByStatus($items, ['resolved']),
            ],
        ];
    }

    public function update404Log(int $id, array $input): array
    {
        $updated = $this->seoRepository->update404Log($id, [
            'resolved' => (string) ($input['fix_status'] ?? 'pending') === 'resolved' ? 1 : 0,
            'notes' => trim((string) ($input['note'] ?? '')),
        ]);
        if ($updated === null) {
            throw new BusinessException('404 记录不存在', ErrorCode::NOT_FOUND);
        }

        return array_merge($updated, [
            'fix_status' => (int) ($updated['resolved'] ?? 0) === 1 ? 'resolved' : 'pending',
            'note' => (string) ($updated['notes'] ?? ''),
        ]);
    }

    public function siteFiles(): array
    {
        $defaults = $this->systemSettingRepository->seoSiteFilesDefaults();
        $config = $this->systemSettingRepository->get('seo', 'site_files', $defaults);
        $siteFiles = is_array($config) ? array_merge($defaults, $config) : $defaults;
        $routes = $this->routes()['items'] ?? [];
        $logs = $this->fourOhFourLogs()['items'] ?? [];

        $siteFiles['sitemap_route_count'] = count($routes);
        $siteFiles['sitemap_index_count'] = count(array_filter($routes, static fn(array $item): bool => (string) ($item['index_status'] ?? '') === 'index'));
        $siteFiles['sitemap_noindex_count'] = count(array_filter($routes, static fn(array $item): bool => (string) ($item['index_status'] ?? '') === 'noindex'));
        $siteFiles['pending_404_count'] = $this->count404ByStatus($logs, ['pending']);
        $siteFiles['home_chain_status'] = $siteFiles['sitemap_route_count'] > 0 ? 'connected' : 'pending';

        return $siteFiles;
    }

    public function updateRobots(string $robotsContent): array
    {
        $current = $this->siteFiles();
        $current['robots_content'] = trim($robotsContent) !== '' ? $robotsContent : $current['robots_content'];
        $current['robots_updated_at'] = date('Y-m-d H:i:s');

        $saved = $this->systemSettingRepository->put('seo', 'site_files', $current);
        $this->operationLogService->recordCurrentAction('seo', 'seo.robots.update', 'system_setting', $saved, 'robots.txt 配置已更新');

        return is_array($saved) ? $saved : $current;
    }

    public function rebuildSitemap(): array
    {
        $siteFiles = $this->siteFiles();
        $siteFiles['sitemap_last_generated_at'] = date('Y-m-d H:i:s');
        $siteFiles['sitemap_route_count'] = count($this->routes()['items'] ?? []);

        $saved = $this->systemSettingRepository->put('seo', 'site_files', $siteFiles);
        $this->operationLogService->recordCurrentAction('seo', 'seo.sitemap.rebuild', 'system_setting', $saved, '站点地图元数据已刷新');

        return is_array($saved) ? $saved : $siteFiles;
    }

    public function executePendingEntityJobs(string $entityType, int $entityId): void
    {
        if ($entityType === '' || $entityId <= 0 || !$this->supportsSeoEntity($entityType)) {
            return;
        }

        $job = $this->seoRepository->findEntityJob($entityType, $entityId);
        if ($job === null) {
            return;
        }

        $jobStatus = (string) ($job['status'] ?? '');
        if (!in_array($jobStatus, ['pending', 'failed', 'processing'], true)) {
            return;
        }

        if ($jobStatus === 'processing' && !$this->isRecoverableStaleEntityJob($job, 'SEO_STALE_JOB_SECONDS', 900)) {
            return;
        }

        $jobId = (int) ($job['id'] ?? 0);
        $details = $job['language_details'] ?? [];
        if (!is_array($details)) {
            $details = [];
        }

        $pendingCodes = [];
        foreach ($details as $code => $info) {
            $status = (string) ($info['status'] ?? 'pending');
            if ($status === 'pending' || $status === 'failed') {
                $pendingCodes[] = (string) $code;
            }
        }
        if ($pendingCodes === []) {
            return;
        }

        $record = $this->contentEntityBridge->find($entityType, $entityId);
        if ($record === null) {
            return;
        }

        $this->seoRepository->updateEntityStatus($jobId, 'processing');

        $baseUrl = rtrim((string) env('APP_URL', 'http://127.0.0.1:8080'), '/');
        $published = (string) ($record['publish_status'] ?? 'draft') === 'published';
        $generatedLanguages = 0;
        $failedMessages = [];

        try {
            $batchResults = $this->batchGenerateSeo($entityType, $record, $pendingCodes);
        } catch (\Throwable $exception) {
            $batchResults = [];
            foreach ($pendingCodes as $languageCode) {
                $this->seoRepository->updateLanguageStatus($jobId, $languageCode, 'failed', $exception->getMessage());
                $failedMessages[] = strtoupper($languageCode) . ': ' . $exception->getMessage();
            }
            $this->seoRepository->updateEntityStatus($jobId, 'failed', implode(' | ', $failedMessages));
            $this->contentEntityBridge->updateSeoStatus($entityType, $entityId, 'failed');
            return;
        }

        foreach ($pendingCodes as $languageCode) {
            try {
                $payload = $batchResults[$languageCode] ?? $this->generateSeoPayload($entityType, $record, $languageCode);
                $routePath = $this->buildRoutePath($entityType, $record, $languageCode, (string) ($payload['slug'] ?? ''));

                $this->seoRepository->upsertRoute(
                    $entityType,
                    $entityId,
                    $languageCode,
                    $routePath,
                    (string) ($payload['slug'] ?? ''),
                    (string) ($payload['seo_title'] ?? ''),
                    (string) ($payload['seo_keywords'] ?? ''),
                    (string) ($payload['seo_description'] ?? ''),
                    $baseUrl . $routePath,
                    $published ? 'index' : 'noindex'
                );
                $this->seoRepository->updateLanguageStatus($jobId, $languageCode, 'generated');
                $generatedLanguages++;
            } catch (\Throwable $exception) {
                $this->seoRepository->updateLanguageStatus($jobId, $languageCode, 'failed', $exception->getMessage());
                $failedMessages[] = strtoupper($languageCode) . ': ' . $exception->getMessage();
            }
        }

        $finalStatus = $failedMessages === [] ? 'generated' : ($generatedLanguages > 0 ? 'generated' : 'failed');
        $this->seoRepository->updateEntityStatus($jobId, $finalStatus, $failedMessages === [] ? null : implode(' | ', $failedMessages));
        $this->contentEntityBridge->updateSeoStatus($entityType, $entityId, $finalStatus);
    }

    private function isRecoverableStaleEntityJob(array $job, string $envKey, int $defaultSeconds): bool
    {
        $timeout = max(60, (int) env($envKey, (string) $defaultSeconds));
        $updatedAt = strtotime((string) ($job['updated_at'] ?? $job['created_at'] ?? ''));
        if ($updatedAt <= 0) {
            return true;
        }

        return (time() - $updatedAt) >= $timeout;
    }

    public function generate(array $input): array
    {
        $entityType = trim((string) ($input['entity_type'] ?? ''));
        $entityId = (int) ($input['entity_id'] ?? 0);
        $languageCodes = array_values(array_unique(array_filter(
            array_map(static fn ($code): string => trim((string) $code), (array) ($input['language_codes'] ?? [])),
            static fn (string $code): bool => $code !== ''
        )));

        if (!$this->supportsSeoEntity($entityType) || $entityId <= 0) {
            throw new BusinessException('SEO 任务参数无效', ErrorCode::INVALID_PARAMS);
        }

        if ($languageCodes === []) {
            $languageCodes = ['zh'];
        }

        $record = $this->contentEntityBridge->find($entityType, $entityId);
        if ($record === null) {
            throw new BusinessException('目标内容不存在', ErrorCode::NOT_FOUND);
        }

        $sourceHash = sha1(
            trim((string) ($record['title_zh'] ?? $record['name_zh'] ?? ''))
            . trim((string) ($record['summary_zh'] ?? ''))
            . trim((string) ($record['content_zh'] ?? ''))
        );
        $this->seoRepository->upsertEntityJob($entityType, $entityId, $languageCodes, $sourceHash, 'pending');
        $job = $this->seoRepository->findEntityJob($entityType, $entityId);
        if ($job === null) {
            throw new BusinessException('SEO 任务创建失败', ErrorCode::INTERNAL_ERROR);
        }

        $baseUrl = rtrim((string) env('APP_URL', 'http://127.0.0.1:8080'), '/');
        $published = (string) ($record['publish_status'] ?? 'draft') === 'published';
        $generatedLanguages = 0;
        $failedMessages = [];

        foreach ($languageCodes as $languageCode) {
            try {
                $payload = $this->generateSeoPayload($entityType, $record, $languageCode);
                $routePath = $this->buildRoutePath($entityType, $record, $languageCode, (string) ($payload['slug'] ?? ''));

                $this->seoRepository->upsertRoute(
                    $entityType,
                    $entityId,
                    $languageCode,
                    $routePath,
                    (string) ($payload['slug'] ?? ''),
                    (string) ($payload['seo_title'] ?? ''),
                    (string) ($payload['seo_keywords'] ?? ''),
                    (string) ($payload['seo_description'] ?? ''),
                    $baseUrl . $routePath,
                    $published ? 'index' : 'noindex'
                );
                $this->seoRepository->updateLanguageStatus((int) ($job['id'] ?? 0), $languageCode, 'generated');
                $generatedLanguages++;
            } catch (\Throwable $exception) {
                $failedMessages[] = strtoupper($languageCode) . ': ' . $exception->getMessage();
                $this->seoRepository->updateLanguageStatus((int) ($job['id'] ?? 0), $languageCode, 'failed', $exception->getMessage());
            }
        }

        $finalStatus = $failedMessages === [] ? 'generated' : ($generatedLanguages > 0 ? 'generated' : 'failed');
        $this->seoRepository->updateEntityStatus((int) ($job['id'] ?? 0), $finalStatus, $failedMessages === [] ? null : implode(' | ', $failedMessages));
        $this->contentEntityBridge->updateSeoStatus($entityType, $entityId, $finalStatus);
        $this->operationLogService->recordCurrentAction('seo', 'seo.generate', 'seo_job', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'language_codes' => $languageCodes,
            'status' => $finalStatus,
        ], 'SEO 生成已执行');

        return $this->seoRepository->findEntityJob($entityType, $entityId) ?? [];
    }

    private function buildRoutePath(string $entityType, array $record, string $languageCode, string $slug): string
    {
        $prefix = '/' . $languageCode;
        return match ($entityType) {
            'product'   => $prefix . '/products/' . $slug,
            'solution'  => $prefix . '/solutions/' . $slug,
            'news'      => $prefix . '/news/' . $slug,
            'case'      => $prefix . '/cases/' . $slug,
            'article'   => $prefix . '/' . ((string)($record['content_type'] ?? 'news') === 'case' ? 'cases' : 'news') . '/' . $slug,
            'page'      => $prefix . '/' . $slug,
            default     => $prefix . '/' . $entityType . '/' . $slug,
        };
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function batchGenerateSeo(string $entityType, array $record, array $languageCodes): array
    {
        $zhSource = $this->contentEntityBridge->seoSource($entityType, $record, 'zh');
        if ($zhSource['title'] === '' && $zhSource['summary'] === '' && $zhSource['content'] === '') {
            return [];
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $this->deepseekPrompt('seo') . "\n\n"
                    . '你必须一次性生成所有目标语言的 SEO 元数据。'
                    . '返回一个 JSON 对象，键为语言代码，值为对应语言的 SEO 字段对象。'
                    . '每个对象必须包含 seo_title、seo_keywords、seo_description、slug，且内容使用对应目标语言。',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'task' => 'batch_generate_seo',
                    'entity_type' => $entityType,
                    'target_languages' => $languageCodes,
                    'source_title' => $zhSource['title'],
                    'source_summary' => $zhSource['summary'],
                    'source_content' => mb_substr($zhSource['content'], 0, 2000),
                    'existing_slug' => (string) ($record['slug'] ?? ''),
                    'output_keys' => ['seo_title', 'seo_keywords', 'seo_description', 'slug'],
                    'language_count' => count($languageCodes),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];

        $response = $this->deepSeekClient->jsonChat($messages, 'seo_enabled');
        if (!is_array($response)) {
            return [];
        }

        $results = [];
        $fallbackTitle = $zhSource['title'];
        foreach ($languageCodes as $code) {
            $fields = $response[$code] ?? null;
            if (is_array($fields)) {
                $results[$code] = [
                    'seo_title' => trim((string) ($fields['seo_title'] ?? '')) !== '' ? (string) $fields['seo_title'] : ($code === 'zh' ? $fallbackTitle : ''),
                    'seo_keywords' => trim((string) ($fields['seo_keywords'] ?? '')),
                    'seo_description' => trim((string) ($fields['seo_description'] ?? '')),
                    'slug' => $this->normalizeSlug((string) ($fields['slug'] ?? ($record['slug'] ?? '')), $fallbackTitle, $entityType),
                ];
            }
        }

        return $results;
    }

    /**
     * AI 辅助：根据内容生成 SEO 预览数据
     */
    public function aiGenerateSeo(array $input): array
    {
        $content = (string) ($input['content'] ?? '');
        $entityName = (string) ($input['entity_name'] ?? '内容');
        $lang = (string) ($input['lang'] ?? 'zh');

        $messages = [
            ['role' => 'system', 'content' => $this->deepseekPrompt('seo')],
            ['role' => 'user', 'content' => json_encode([
                'task' => 'generate_seo_preview',
                'entity_name' => $entityName,
                'language_code' => $lang,
                'content' => $content,
                'output_keys' => ['seo_title', 'seo_keywords', 'seo_description'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ];

        return $this->deepSeekClient->jsonChat($messages, 'seo_enabled');
    }

    public function aiPolishContent(array $input): array
    {
        $content = (string) ($input['content'] ?? '');
        $fieldType = (string) ($input['field_type'] ?? 'summary_zh');

        $fieldLabels = [
            'summary_zh' => '产品描述',
            'content_zh' => '技术要求',
        ];
        $fieldLabel = $fieldLabels[$fieldType] ?? '内容';
        $promptKey = $fieldType === 'content_zh' ? 'polish_content' : 'polish_summary';

        $fieldPrompt = $this->systemSettingRepository->deepseekPrompt('cms_polish', $promptKey);

        $prompt = $fieldPrompt !== ''
            ? str_replace(
                ['{content}', '{field_label}'],
                [$content, $fieldLabel],
                rtrim($fieldPrompt) . "\n\n请只返回 JSON，格式：{\"polished\":\"优化后的完整内容\"}"
            )
            : "你是一个专业的工业网站内容编辑。请优化以下{$fieldLabel}，提升专业性、可读性和成交表达。\n\n"
                . "原始{$fieldLabel}：\n{$content}\n\n"
                . "要求：\n"
                . "1. 保持核心信息和数据不变\n"
                . "2. 优化表达方式，使内容更专业、简洁、清晰\n"
                . "3. 保留原有 HTML 结构，如段落、列表、表格等标签\n"
                . "4. 只返回 JSON，格式：{\"polished\":\"优化后的完整内容\"}";

        $messages = [
            [
                'role' => 'system',
                'content' => '你是中文工业网站内容编辑。你只能返回 JSON，不要解释，不要前言，不要 Markdown 代码块，返回对象中只允许出现 polished 字段。',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'task' => 'polish_content',
                    'field_type' => $fieldType,
                    'field_label' => $fieldLabel,
                    'prompt' => $prompt,
                    'content' => $content,
                    'output' => [
                        'format' => 'json',
                        'keys' => ['polished'],
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];

        $response = $this->deepSeekClient->jsonChat($messages, 'seo_enabled');
        if (isset($response['polished'])) {
            $response['polished'] = $this->sanitizePolishedContent((string) $response['polished']);
        }

        return $response;
    }

    private function sanitizePolishedContent(string $content): string
    {
        $normalized = trim($content);
        if ($normalized === '') {
            return $normalized;
        }

        $markers = [
            '以下是润色后的正文内容：',
            '以下为润色后的正文内容：',
            '以下是润色后的内容：',
            '以下为润色后的内容：',
            '润色后的正文内容：',
            '润色后的内容：',
        ];

        foreach ($markers as $marker) {
            $position = mb_strpos($normalized, $marker);
            if ($position !== false) {
                $normalized = trim(mb_substr($normalized, $position + mb_strlen($marker)));
            }
        }

        $normalized = preg_replace('/^作为[^。！？\n]*编辑[^。！？\n]*[。！？\n\s]*/u', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^优化原则[:：][^\n]*(?:\n|$)/u', '', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @return array<string, string>
     */
    private function generateSeoPayload(string $entityType, array $record, string $languageCode): array
    {
        $source = $this->contentEntityBridge->seoSource($entityType, $record, $languageCode);
        if ($source['title'] === '' && $source['summary'] === '' && $source['content'] === '') {
            throw new BusinessException('SEO 源内容为空，无法生成', ErrorCode::INVALID_PARAMS);
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $this->deepseekPrompt('seo'),
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'task' => 'generate_seo',
                    'entity_type' => $entityType,
                    'language_code' => $languageCode,
                    'title' => $source['title'],
                    'summary' => $source['summary'],
                    'content' => $source['content'],
                    'existing_slug' => (string) ($record['slug'] ?? ''),
                    'output_keys' => ['seo_title', 'seo_keywords', 'seo_description', 'slug'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ];
        $response = $this->deepSeekClient->jsonChat($messages, 'seo_enabled');

        $fallbackTitle = $source['title'];
        $fallbackSummary = $source['summary'];
        $fallbackContent = $source['content'];

        return [
            'seo_title' => trim((string) ($response['seo_title'] ?? '')) !== '' ? (string) $response['seo_title'] : $fallbackTitle,
            'seo_keywords' => trim((string) ($response['seo_keywords'] ?? '')) !== '' ? (string) $response['seo_keywords'] : '',
            'seo_description' => trim((string) ($response['seo_description'] ?? '')) !== '' ? (string) $response['seo_description'] : $this->defaultDescription($fallbackSummary, $fallbackContent, $fallbackTitle),
            'slug' => $this->normalizeSlug((string) ($response['slug'] ?? ($record['slug'] ?? '')), $fallbackTitle, $entityType),
        ];
    }

    private function deepseekPrompt(string $feature): string
    {
        if ($feature === 'seo') {
            return (new PromptComposer())->composeSeoSystemPrompt();
        }

        return $this->systemSettingRepository->deepseekPrompt($feature);
    }

    private function defaultDescription(string $summary, string $content, string $fallback): string
    {
        $summary = trim($summary);
        if ($summary !== '') {
            return $summary;
        }

        $plain = trim(strip_tags($content));
        if ($plain !== '') {
            return mb_substr($plain, 0, 120);
        }

        return $fallback;
    }

    private function normalizeSlug(string $slug, string $fallback, string $prefix): string
    {
        $slug = trim(strtolower($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ($slug !== '') {
            return $slug;
        }

        $candidate = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $fallback));
        $candidate = trim($candidate, '-');

        return $candidate !== '' ? $candidate : $prefix . '-' . substr(sha1($fallback), 0, 10);
    }

    private function seoSourceTitle(string $entityType, array $record, string $languageCode): string
    {
        $source = $this->contentEntityBridge->seoSource($entityType, $record, $languageCode);

        return (string) ($source['title'] ?? '');
    }

    private function syncEntitySeoStatus(string $entityType, int $entityId): void
    {
        if ($entityType === '' || $entityId <= 0) {
            return;
        }

        $languages = [];
        foreach ($this->languageRepository->list() as $language) {
            if ((int) ($language['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $code = (string) ($language['code'] ?? '');
            if ($code !== '') {
                $languages[] = $code;
            }
        }

        if ($languages === []) {
            $languages = ['zh'];
        }

        $hasPending = false;
        $hasFailed = false;
        $hasManualOverride = false;
        foreach (array_values(array_unique($languages)) as $languageCode) {
            $job = $this->seoRepository->findJobByEntity($entityType, $entityId, $languageCode);
            $jobStatus = (string) ($job['status'] ?? 'pending');

            $hasManualOverride = $hasManualOverride || $jobStatus === 'manual_override';
            $hasFailed = $hasFailed || $jobStatus === 'failed';
            $hasPending = $hasPending || !in_array($jobStatus, ['generated', 'manual_override', 'failed'], true);
        }

        $status = 'generated';
        if ($hasPending) {
            $status = 'pending';
        } elseif ($hasFailed) {
            $status = 'failed';
        } elseif ($hasManualOverride) {
            $status = 'manual_override';
        }

        $this->contentEntityBridge->updateSeoStatus($entityType, $entityId, $status);
    }

    private function decorateJob(array $job, array $languageMap): array
    {
        $entityType = (string) ($job['entity_type'] ?? '');
        $entityId = (int) ($job['entity_id'] ?? 0);
        $languageCode = (string) ($job['language_code'] ?? 'zh');
        $record = $this->contentEntityBridge->find($entityType, $entityId) ?? [];
        $source = $this->contentEntityBridge->seoSource($entityType, $record, $languageCode);
        $route = $this->seoRepository->findRoute($entityType, $entityId, $languageCode) ?? [];

        return array_merge($job, [
            'entity_label' => $this->entityLabel($entityType),
            'language_name' => $languageMap[$languageCode] ?? strtoupper($languageCode),
            'source_title' => trim((string) ($source['title'] ?? '')),
            'source_excerpt' => $this->excerptFromSeoSource($source),
            'route_path' => (string) ($route['route_path'] ?? ''),
            'canonical_url' => $this->resolveCanonicalUrl((string) ($route['canonical_url'] ?? ''), (string) ($route['route_path'] ?? '')),
            'index_status' => (string) ($route['index_status'] ?? ''),
            'slug' => (string) ($route['slug'] ?? ($record['slug'] ?? '')),
            'seo_title' => (string) ($route['seo_title'] ?? ($record['seo_title'] ?? '')),
            'seo_keywords' => (string) ($route['seo_keywords'] ?? ($record['seo_keywords'] ?? '')),
            'seo_description' => (string) ($route['seo_description'] ?? ($record['seo_description'] ?? '')),
            'last_generated_at' => $route['last_generated_at'] ?? null,
        ]);
    }

    private function decorateRoute(array $route, array $languageMap): array
    {
        return array_merge($route, [
            'canonical_url' => $this->resolveCanonicalUrl((string) ($route['canonical_url'] ?? ''), (string) ($route['route_path'] ?? '')),
            'entity_label' => $this->entityLabel((string) ($route['entity_type'] ?? '')),
            'language_name' => $languageMap[(string) ($route['language_code'] ?? '')] ?? strtoupper((string) ($route['language_code'] ?? '')),
        ]);
    }

    private function resolveCanonicalUrl(string $canonicalUrl, string $routePath): string
    {
        $canonicalUrl = trim($canonicalUrl);
        $routePath = trim($routePath);
        $baseUrl = rtrim((string) env('APP_URL', 'http://127.0.0.1:8080'), '/');

        if ($routePath === '') {
            return $canonicalUrl;
        }

        if ($canonicalUrl === '' || str_starts_with($canonicalUrl, '/') || str_contains($canonicalUrl, '://example.com/')) {
            $normalizedRoutePath = str_starts_with($routePath, '/') ? $routePath : '/' . $routePath;

            return $baseUrl . $normalizedRoutePath;
        }

        return $canonicalUrl;
    }

    private function buildJobSummary(array $items): array
    {
        $summary = [
            'total' => count($items),
            'pending' => 0,
            'completed' => 0,
            'generated' => 0,
            'manual_override' => 0,
            'failed' => 0,
        ];

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'pending');
            if (array_key_exists($status, $summary)) {
                $summary[$status] += 1;
            }
        }

        return $summary;
    }

    private function buildRouteSummary(array $items): array
    {
        $indexCount = 0;
        $noindexCount = 0;
        foreach ($items as $item) {
            $indexStatus = (string) ($item['index_status'] ?? '');
            if ($indexStatus === 'index') {
                $indexCount += 1;
            }
            if ($indexStatus === 'noindex') {
                $noindexCount += 1;
            }
        }

        return [
            'route_count' => count($items),
            'index_count' => $indexCount,
            'noindex_count' => $noindexCount,
            'four_oh_four_count' => $this->seoRepository->count404Logs(),
        ];
    }

    private function count404ByStatus(array $items, array $statuses): int
    {
        $count = 0;
        foreach ($items as $item) {
            if (in_array((string) ($item['fix_status'] ?? 'pending'), $statuses, true)) {
                $count++;
            }
        }

        return $count;
    }

    private function languageNameMap(): array
    {
        $map = [];
        foreach ($this->languageRepository->list() as $language) {
            $code = (string) ($language['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $map[$code] = (string) ($language['name'] ?? strtoupper($code));
        }

        return $map;
    }

    private function entityLabel(string $entityType): string
    {
        return match ($entityType) {
            'product' => '产品',
            'solution' => '方案',
            'news' => '新闻',
            'case' => '案例',
            'article' => '文章',
            'page' => '页面',
            default => $entityType,
        };
    }

    private function excerptFromSeoSource(array $source): string
    {
        $summary = trim((string) ($source['summary'] ?? ''));
        if ($summary !== '') {
            return $summary;
        }

        $content = trim(strip_tags((string) ($source['content'] ?? '')));
        if ($content !== '') {
            return mb_substr($content, 0, 120);
        }

        return '';
    }

    private function supportsSeoEntity(string $entityType): bool
    {
        return in_array($entityType, ['product', 'solution', 'news', 'case', 'article', 'page', 'certificate'], true);
    }
}




