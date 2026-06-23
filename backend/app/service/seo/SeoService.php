<?php

declare(strict_types=1);

namespace app\service\seo;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\LanguageRepository;
use app\repository\SeoRepository;
use app\repository\SystemSettingRepository;
use app\service\ai\DeepSeekClient;
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
        $items = array_map(
            fn (array $item): array => $this->decorateJob($item, $languageMap),
            $this->seoRepository->jobs()
        );

        return [
            'items' => $items,
            'status_options' => ['pending', 'generated', 'manual_override', 'failed'],
            'summary' => $this->buildJobSummary($items),
        ];
    }

    public function retry(int $id): array
    {
        $job = $this->seoRepository->findJob($id);
        if ($job === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        try {
            $entityType = (string) ($job['entity_type'] ?? '');
            $entityId = (int) ($job['entity_id'] ?? 0);
            $languageCode = (string) ($job['language_code'] ?? 'zh');
            if ($languageCode === '') {
                throw new BusinessException('invalid seo language', ErrorCode::INVALID_PARAMS);
            }
            if (!$this->contentEntityBridge->supports($entityType)) {
                throw new BusinessException('unsupported entity type', ErrorCode::INVALID_PARAMS);
            }

            $record = $this->contentEntityBridge->find($entityType, $entityId);
            if ($record === null) {
                throw new BusinessException('source record not found', ErrorCode::NOT_FOUND);
            }

            $this->seoRepository->updateJobStatus($id, 'pending', null, true);
            $this->contentEntityBridge->updateSeoStatus($entityType, $entityId, 'pending');

            $seoPayload = $this->generateSeoPayload($entityType, $record, $languageCode);
            if ($languageCode === 'zh' && $this->contentEntityBridge->applySeoResult($entityType, $entityId, $seoPayload) === null) {
                throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
            }

            $routePath = $this->buildRoutePath($entityType, $record, $languageCode, (string) ($seoPayload['slug'] ?? ''));
            $this->seoRepository->upsertRoute(
                $entityType,
                $entityId,
                $languageCode,
                $routePath,
                (string) ($seoPayload['slug'] ?? ''),
                (string) ($seoPayload['seo_title'] ?? ''),
                (string) ($seoPayload['seo_keywords'] ?? ''),
                (string) ($seoPayload['seo_description'] ?? ''),
                rtrim((string) env('APP_URL', 'http://127.0.0.1:8080'), '/') . $routePath,
                (string) ($record['publish_status'] ?? 'draft') === 'published' ? 'index' : 'noindex'
            );

            $updated = $this->seoRepository->updateJobStatus($id, 'generated', null);
            $this->syncEntitySeoStatus($entityType, $entityId);
            $this->operationLogService->recordCurrentAction('seo', 'seo.retry', 'seo_job', $updated, 'seo generated');

            return $updated ?? [];
        } catch (\Throwable $exception) {
            $this->seoRepository->updateJobStatus($id, 'failed', $exception->getMessage());
            $this->syncEntitySeoStatus((string) ($job['entity_type'] ?? ''), (int) ($job['entity_id'] ?? 0));

            throw $exception instanceof BusinessException
                ? $exception
                : new BusinessException($exception->getMessage(), ErrorCode::INTERNAL_ERROR);
        }
    }

    public function updateRoute(int $id, array $input): array
    {
        $route = $this->seoRepository->findRouteById($id);
        if ($route === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $entityType = (string) ($route['entity_type'] ?? '');
        $entityId = (int) ($route['entity_id'] ?? 0);
        $languageCode = (string) ($route['language_code'] ?? 'zh');
        $record = $this->contentEntityBridge->find($entityType, $entityId);
        if ($record === null) {
            throw new BusinessException('source record not found', ErrorCode::NOT_FOUND);
        }

        $slug = $this->normalizeSlug((string) ($input['slug'] ?? ($route['slug'] ?? '')), $this->seoSourceTitle($entityType, $record, $languageCode), $entityType);
        $routePathInput = trim((string) ($input['route_path'] ?? ''));
        $routePath = $routePathInput !== '' ? $routePathInput : $this->buildRoutePath($entityType, $record, $languageCode, $slug);
        $indexStatus = trim((string) ($input['index_status'] ?? ($route['index_status'] ?? 'index')));
        if (!in_array($indexStatus, ['index', 'noindex'], true)) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $updated = $this->seoRepository->updateRouteById($id, [
            'route_path' => $routePath,
            'slug' => $slug,
            'seo_title' => trim((string) ($input['seo_title'] ?? ($route['seo_title'] ?? ''))),
            'seo_keywords' => trim((string) ($input['seo_keywords'] ?? ($route['seo_keywords'] ?? ''))),
            'seo_description' => trim((string) ($input['seo_description'] ?? ($route['seo_description'] ?? ''))),
            'canonical_url' => trim((string) ($input['canonical_url'] ?? '')) !== ''
                ? trim((string) ($input['canonical_url'] ?? ''))
                : rtrim((string) env('APP_URL', 'http://127.0.0.1:8080'), '/') . $routePath,
            'index_status' => $indexStatus,
            'last_generated_at' => date('Y-m-d H:i:s'),
        ]);
        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $job = $this->seoRepository->findJobByEntity($entityType, $entityId, $languageCode);
        if ($job !== null) {
            $this->seoRepository->updateJobStatus((int) ($job['id'] ?? 0), 'manual_override', null);
        } else {
            $this->seoRepository->upsertJob($entityType, $entityId, $languageCode, 'manual_override', null);
        }

        if ($languageCode === 'zh') {
            $this->contentEntityBridge->applySeoResult($entityType, $entityId, [
                'slug' => $slug,
                'seo_title' => $updated['seo_title'] ?? '',
                'seo_keywords' => $updated['seo_keywords'] ?? '',
                'seo_description' => $updated['seo_description'] ?? '',
            ], 'manual_override');
        }

        $this->syncEntitySeoStatus($entityType, $entityId);
        $this->operationLogService->recordCurrentAction('seo', 'seo.route.update', 'seo_route', $updated, 'seo route updated');

        return $updated;
    }

    public function generate(array $input): array
    {
        $entityType = trim((string) ($input['entity_type'] ?? ''));
        $entityId = (int) ($input['entity_id'] ?? 0);
        if (!$this->supportsSeoEntity($entityType) || $entityId <= 0) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $record = $this->contentEntityBridge->find($entityType, $entityId);
        if ($record === null) {
            throw new BusinessException('source record not found', ErrorCode::NOT_FOUND);
        }

        $requestedLanguages = is_array($input['language_codes'] ?? null) ? $input['language_codes'] : [];
        $enabledLanguages = array_values(array_unique(array_map(
            static fn (array $language): string => (string) ($language['code'] ?? ''),
            array_filter($this->languageRepository->list(), static fn (array $language): bool => (int) ($language['is_enabled'] ?? 0) === 1 && trim((string) ($language['code'] ?? '')) !== '')
        )));
        if ($enabledLanguages === []) {
            $enabledLanguages = ['zh'];
        }

        $languageCodes = array_values(array_filter(
            $requestedLanguages !== [] ? array_map('strval', $requestedLanguages) : $enabledLanguages,
            static fn (string $code): bool => $code !== ''
        ));
        $languageCodes = array_values(array_unique(array_filter(
            $languageCodes,
            static fn (string $code) => in_array($code, $enabledLanguages, true)
        )));
        if ($languageCodes === []) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $jobs = [];
        foreach ($languageCodes as $languageCode) {
            $job = $this->seoRepository->upsertJob($entityType, $entityId, $languageCode, 'pending', null);
            $jobs[] = $this->retry((int) ($job['id'] ?? 0));
        }

        $this->operationLogService->recordCurrentAction('seo', 'seo.generate', 'seo_job', $entityId, 'seo generated manually');

        return [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'language_codes' => $languageCodes,
            'jobs' => $jobs,
        ];
    }

    public function routes(): array
    {
        $languageMap = $this->languageNameMap();
        $items = array_map(
            fn (array $item): array => $this->decorateRoute($item, $languageMap),
            $this->seoRepository->routes()
        );

        return [
            'items' => $items,
            'summary' => $this->buildRouteSummary($items),
        ];
    }

    public function fourOhFourLogs(): array
    {
        $items = $this->seoRepository->fourOhFourLogs();

        return [
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'pending' => $this->count404ByStatus($items, ['pending']),
                'processing' => $this->count404ByStatus($items, ['processing']),
                'resolved' => $this->count404ByStatus($items, ['resolved']),
                'ignored' => $this->count404ByStatus($items, ['ignored']),
            ],
        ];
    }

    public function update404Log(int $id, array $input): array
    {
        $existing = null;
        foreach ($this->seoRepository->fourOhFourLogs() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $existing = $item;
                break;
            }
        }

        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $fixStatus = trim((string) ($input['fix_status'] ?? ($existing['fix_status'] ?? 'pending')));
        if (!in_array($fixStatus, ['pending', 'processing', 'resolved', 'ignored'], true)) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $resolvedAt = $fixStatus === 'resolved'
            ? (string) ($existing['resolved_at'] ?? date('Y-m-d H:i:s'))
            : null;

        $updated = $this->seoRepository->update404Log($id, [
            'fix_status' => $fixStatus,
            'suggested_route' => trim((string) ($input['suggested_route'] ?? ($existing['suggested_route'] ?? ''))),
            'note' => trim((string) ($input['note'] ?? ($existing['note'] ?? ''))),
            'resolved_at' => $resolvedAt,
        ]);

        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('seo', 'seo.404.update', 'seo_404_log', $updated, 'seo 404 log updated');

        return $updated;
    }

    public function siteFiles(): array
    {
        $config = $this->systemSettingRepository->get('seo', 'site_files', $this->systemSettingRepository->seoSiteFilesDefaults());
        $config = is_array($config) ? $config : $this->systemSettingRepository->seoSiteFilesDefaults();
        $routeSummary = $this->buildRouteSummary($this->seoRepository->routes());

        return [
            'robots_content' => (string) ($config['robots_content'] ?? ''),
            'robots_updated_at' => $config['robots_updated_at'] ?? null,
            'sitemap_last_generated_at' => $config['sitemap_last_generated_at'] ?? null,
            'sitemap_route_count' => (int) ($config['sitemap_route_count'] ?? $routeSummary['route_count'] ?? 0),
            'sitemap_index_count' => (int) ($config['sitemap_index_count'] ?? $routeSummary['index_count'] ?? 0),
            'sitemap_noindex_count' => (int) ($config['sitemap_noindex_count'] ?? $routeSummary['noindex_count'] ?? 0),
            'pending_404_count' => (int) ($routeSummary['four_oh_four_count'] ?? 0),
            'home_chain_status' => 'connected',
        ];
    }

    public function updateRobots(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $current = $this->systemSettingRepository->get('seo', 'site_files', $this->systemSettingRepository->seoSiteFilesDefaults());
        $payload = is_array($current) ? $current : $this->systemSettingRepository->seoSiteFilesDefaults();
        $payload['robots_content'] = $content . "\n";
        $payload['robots_updated_at'] = date('Y-m-d H:i:s');
        $this->systemSettingRepository->put('seo', 'site_files', $payload);
        $this->operationLogService->recordCurrentAction('seo', 'seo.robots.update', 'system_setting', 'seo.site_files', 'robots updated');

        return $this->siteFiles();
    }

    public function rebuildSitemap(): array
    {
        $publisher = new StaticPublisher();
        $publisher->buildSiteMap();
        $publisher->buildRobots();

        $routes = $this->seoRepository->routes();
        $routeSummary = $this->buildRouteSummary($routes);
        $current = $this->systemSettingRepository->get('seo', 'site_files', $this->systemSettingRepository->seoSiteFilesDefaults());
        $payload = is_array($current) ? $current : $this->systemSettingRepository->seoSiteFilesDefaults();
        $payload['sitemap_last_generated_at'] = date('Y-m-d H:i:s');
        $payload['sitemap_route_count'] = (int) ($routeSummary['route_count'] ?? 0);
        $payload['sitemap_index_count'] = (int) ($routeSummary['index_count'] ?? 0);
        $payload['sitemap_noindex_count'] = (int) ($routeSummary['noindex_count'] ?? 0);
        $this->systemSettingRepository->put('seo', 'site_files', $payload);
        $this->operationLogService->recordCurrentAction('seo', 'seo.sitemap.rebuild', 'system_setting', 'seo.site_files', 'sitemap rebuilt');

        return $this->siteFiles();
    }

    public function executePendingEntityJobs(string $entityType, int $entityId): void
    {
        if ($entityType === '' || $entityId <= 0 || !$this->contentEntityBridge->supports($entityType)) {
            return;
        }

        foreach ($this->languageRepository->list() as $language) {
            if ((int) ($language['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $languageCode = (string) ($language['code'] ?? '');
            if ($languageCode === '') {
                continue;
            }

            $job = $this->seoRepository->findJobByEntity($entityType, $entityId, $languageCode);
            if ($job === null) {
                continue;
            }

            $status = (string) ($job['status'] ?? 'pending');
            if ($status !== 'pending') {
                continue;
            }

            $this->retry((int) ($job['id'] ?? 0));
        }
    }

    /**
     * AI 帮写：根据内容生成 SEO 元数据
     */
    public function aiGenerateSeo(array $input): array
    {
        $content = $input['content'];
        $entityName = $input['entity_name'];
        $lang = $input['lang'];

        $prompt = "你是一个专业的 SEO 优化顾问。请基于以下{$entityName}的描述内容，生成一组 SEO 元数据。\n\n"
            . "{$entityName}描述：\n{$content}\n\n"
            . "请严格按以下 JSON 格式返回，不要包含任何其他内容：\n"
            . '{"seo_title": "SEO 标题（30-60 个字符）", "seo_keywords": "逗号分隔的 5-8 个关键词", "seo_description": "SEO 描述（120-160 个字符）"}';

        $messages = [
            ['role' => 'system', 'content' => $this->deepseekPrompt('seo')],
            ['role' => 'user', 'content' => $prompt],
        ];

        $response = $this->deepSeekClient->jsonChat($messages, 'seo_enabled');

        return $response;
    }

    public function aiPolishContent(array $input): array
    {
        $content = (string) ($input['content'] ?? '');
        $fieldType = (string) ($input['field_type'] ?? 'summary_zh');

        $fieldLabels = [
            'summary_zh' => '产品描述',
            'content_zh' => '技术参数',
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
            : "你是一个专业的产品文案优化专家。请优化以下中文{$fieldLabel}，提升专业性和可读性。\n\n"
                . "原始{$fieldLabel}：\n{$content}\n\n"
                . "要求：\n"
                . "1. 保持核心信息和数据不变\n"
                . "2. 优化表达方式，使其更加专业、简洁、有吸引力\n"
                . "3. 保留原有HTML格式（段落、列表、表格等标签）\n"
                . "4. 请严格按以下 JSON 格式返回，不要包含任何其他内容：\n"
                . '{"polished": "优化后的完整内容"}';

        $messages = [
            [
                'role' => 'system',
                'content' => '你是中文工业网站内容编辑。你只能返回 json，不要解释，不要前言，不要 Markdown 代码块，返回对象中只允许出现 polished 字段。',
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

        $normalized = preg_replace('/^作为[^。！？]*编辑[^。！？]*[。！？]\s*/u', '', $normalized) ?? $normalized;
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
            throw new BusinessException('seo source empty', ErrorCode::INVALID_PARAMS);
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
            'seo_keywords' => trim((string) ($response['seo_keywords'] ?? '')) !== '' ? (string) $response['seo_keywords'] : $fallbackTitle,
            'seo_description' => trim((string) ($response['seo_description'] ?? '')) !== '' ? (string) $response['seo_description'] : $this->defaultDescription($fallbackSummary, $fallbackContent, $fallbackTitle),
            'slug' => $this->normalizeSlug((string) ($response['slug'] ?? ($record['slug'] ?? '')), $fallbackTitle, $entityType),
        ];
    }

    private function deepseekPrompt(string $feature): string
    {
        return $this->systemSettingRepository->deepseekPrompt($feature);
    }

    private function buildRoutePath(string $entityType, array $record, string $languageCode, string $slug): string
    {
        $prefix = '/' . $languageCode;

        return match ($entityType) {
            'product' => $prefix . '/products/' . $slug,
            'solution' => $prefix . '/solutions/' . $slug,
            'news' => $prefix . '/news/' . $slug,
            'case' => $prefix . '/cases/' . $slug,
            'article' => $prefix . '/' . ((string) ($record['content_type'] ?? 'news') === 'case' ? 'cases' : 'news') . '/' . $slug,
            'page' => $prefix . '/' . $slug,
            default => $prefix . '/' . $entityType . '/' . $slug,
        };
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
