<?php

declare(strict_types=1);

namespace app\service\system;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\AdminUserRepository;
use app\repository\ArticleRepository;
use app\repository\CaseRepository;
use app\repository\CertificateRepository;
use app\repository\DeepSeekLogRepository;
use app\repository\LanguageRepository;
use app\repository\LanguageWorkspaceRepository;
use app\repository\NewsRepository;
use app\repository\PageRepository;
use app\repository\ProductRepository;
use app\repository\SeoRepository;
use app\repository\SitePhraseRepository;
use app\repository\SitePhraseWorkspaceRepository;
use app\repository\SolutionRepository;
use app\repository\SystemSettingRepository;
use app\repository\TranslationRepository;
use app\service\ai\DeepSeekClient;
use app\service\auth\SessionService;
use app\service\log\OperationLogService;
use app\service\seo\SeoService;

final class SettingService
{
    public function __construct(
        private readonly AdminUserRepository $adminUserRepository = new AdminUserRepository(),
        private readonly DeepSeekLogRepository $deepSeekLogRepository = new DeepSeekLogRepository(),
        private readonly LanguageRepository $languageRepository = new LanguageRepository(),
        private readonly LanguageWorkspaceRepository $languageWorkspaceRepository = new LanguageWorkspaceRepository(),
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository(),
        private readonly DeepSeekClient $deepSeekClient = new DeepSeekClient(),
        private readonly SessionService $sessionService = new SessionService(),
        private readonly OperationLogService $operationLogService = new OperationLogService(),
        private readonly TranslationRepository $translationRepository = new TranslationRepository(),
        private readonly SeoRepository $seoRepository = new SeoRepository(),
        private readonly ProductRepository $productRepository = new ProductRepository(),
        private readonly SolutionRepository $solutionRepository = new SolutionRepository(),
        private readonly ArticleRepository $articleRepository = new ArticleRepository(),
        private readonly NewsRepository $newsRepository = new NewsRepository(),
        private readonly CaseRepository $caseRepository = new CaseRepository(),
        private readonly PageRepository $pageRepository = new PageRepository(),
        private readonly CertificateRepository $certificateRepository = new CertificateRepository(),
        private readonly SeoService $seoService = new SeoService(),
        private readonly SitePhraseRepository $sitePhraseRepository = new SitePhraseRepository(),
        private readonly SitePhraseWorkspaceRepository $sitePhraseWorkspaceRepository = new SitePhraseWorkspaceRepository(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    ) {
    }

    public function accountProfile(): array
    {
        $user = current_user();
        if ($user === null) {
            throw new BusinessException('login required', ErrorCode::UNAUTHORIZED);
        }

        $userId = (int) ($user['id'] ?? 0);
        $record = $this->adminUserRepository->findById($userId);
        $profile = is_array($record) ? $record : $user;

        $roleNames = array_map(
            static fn (array $role): string => (string) ($role['name'] ?? ''),
            $this->adminUserRepository->rolesForUser($userId)
        );
        $roleNames = array_values(array_filter($roleNames, static fn (string $name): bool => trim($name) !== ''));

        return [
            'id' => $profile['id'] ?? null,
            'username' => $profile['username'] ?? '',
            'nickname' => $profile['nickname'] ?? '',
            'email' => $profile['email'] ?? '',
            'mobile' => $profile['mobile'] ?? '',
            'status' => $profile['status'] ?? 1,
            'last_login_at' => $profile['last_login_at'] ?? null,
            'last_login_ip' => $profile['last_login_ip'] ?? '',
            'role_names' => $roleNames,
        ];
    }

    public function accountBootstrap(): array
    {
        $profile = $this->accountProfile();

        return [
            'profile' => $profile,
            'login_logs' => $this->loginLogs(1, 6, [
                'username' => (string) ($profile['username'] ?? ''),
            ]),
        ];
    }

    public function updateAccountProfile(array $input): array
    {
        $user = current_user();
        if ($user === null) {
            throw new BusinessException('login required', ErrorCode::UNAUTHORIZED);
        }

        $userId = (int) ($user['id'] ?? 0);
        $existing = $this->adminUserRepository->findById($userId);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $roles = $this->adminUserRepository->rolesForUser($userId);
        $roleIds = array_values(array_filter(array_map(
            static fn (array $role): int => (int) ($role['id'] ?? 0),
            $roles
        )));

        $password = $this->validatePassword((string) ($input['password'] ?? ''));
        $updated = $this->adminUserRepository->update($userId, [
            'nickname' => $this->validateNickname((string) ($input['nickname'] ?? ($existing['nickname'] ?? '')), (string) ($existing['username'] ?? '')),
            'email' => $this->validateEmail((string) ($input['email'] ?? ($existing['email'] ?? ''))),
            'mobile' => $this->validateMobile((string) ($input['mobile'] ?? ($existing['mobile'] ?? ''))),
            'status' => (int) ($existing['status'] ?? 1),
            'password_hash' => $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : '',
        ], $roleIds);

        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $requiresRelogin = $password !== '';
        if ($requiresRelogin) {
            $this->sessionService->revokeAllForUser($userId);
        }

        $this->operationLogService->recordCurrentAction('system', 'system.account.update', 'admin_user', $updated, 'account profile updated');

        return array_merge($this->accountProfile(), [
            'require_relogin' => $requiresRelogin ? 1 : 0,
        ]);
    }

    public function languages(): array
    {
        $items = $this->languageRepository->list();
        $stats = $this->translationRepository->countByLanguage();
        $metaMap = $this->languageWorkspaceRepository->listMeta();
        $summary = [
            'total' => 0,
            'enabled' => 0,
            'paused' => 0,
            'preparing' => 0,
            'ready' => 0,
        ];

        foreach ($items as &$item) {
            $code = strtolower(trim((string) ($item['code'] ?? '')));
            $translationStats = $stats[$code] ?? [
                'completed' => 0,
                'pending' => 0,
                'failed' => 0,
                'review_required' => 0,
                'processing' => 0,
                'translating' => 0,
                'total' => 0,
            ];
            $phraseSummary = $this->sitePhraseWorkspaceRepository->buildSummary($code);
            $workspace = is_array($metaMap[$code] ?? null) ? $metaMap[$code] : [];
            $status = $this->resolveLanguageStatus($item, $workspace, $phraseSummary, $translationStats);

            $item['translation_stats'] = $translationStats;
            $item['phrase_summary'] = $phraseSummary;
            $item['status'] = $status;
            $item['native_name'] = trim((string) ($workspace['native_name'] ?? $item['name'] ?? ''));
            $item['english_name'] = trim((string) ($workspace['english_name'] ?? strtoupper($code)));
            $item['zh_name'] = trim((string) ($workspace['zh_name'] ?? $item['name'] ?? ''));
            $item['content_completion_percent'] = $translationStats['total'] > 0
                ? (int) round((((int) ($translationStats['completed'] ?? 0)) / max(1, (int) ($translationStats['total'] ?? 0))) * 100)
                : 0;
            $item['phrase_completion_percent'] = (int) ($phraseSummary['completion_percent'] ?? 0);

            $summary['total']++;
            if ((int) ($item['is_enabled'] ?? 0) === 1) {
                $summary['enabled']++;
            }
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
        }
        unset($item);

        return [
            'items' => $items,
            'summary' => $summary,
            'management_mode' => 'single_language_workspace',
            'fallback_chain' => ['target_language', 'en', 'zh'],
        ];
    }

    public function updateLanguages(array $items): array
    {
        if ($items === []) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $previousItems = $this->languageRepository->list();
        $previousEnabledCodes = $this->enabledLanguageCodes($previousItems);
        $previousCodes = array_map(
            static fn (array $item): string => strtolower(trim((string) ($item['code'] ?? ''))),
            $previousItems
        );

        $normalized = [];
        $rawMeta = [];
        $defaultCount = 0;
        $enabledCount = 0;
        foreach ($items as $index => $item) {
            $code = strtolower(trim((string) ($item['code'] ?? '')));
            $name = trim((string) ($item['name'] ?? ''));
            if ($code === '' || $name === '') {
                throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
            }

            $isDefault = !empty($item['is_default']) ? 1 : 0;
            $isEnabled = !empty($item['is_enabled']) ? 1 : 0;
            if ($isDefault === 1) {
                $defaultCount++;
            }

            if ($isEnabled === 1) {
                $enabledCount++;
            }

            if ($isDefault === 1 && $isEnabled !== 1) {
                throw new BusinessException('default language must be enabled', ErrorCode::INVALID_PARAMS);
            }

            $normalized[] = [
                'id' => (int) ($item['id'] ?? ($index + 1)),
                'code' => $code,
                'name' => $name,
                'is_default' => $isDefault,
                'is_enabled' => $isEnabled,
                'sort' => (int) ($item['sort'] ?? 0),
            ];
            $rawMeta[$code] = [
                'native_name' => $name,
                'english_name' => trim((string) ($item['english_name'] ?? '')),
                'zh_name' => trim((string) ($item['zh_name'] ?? '')),
            ];
        }

        if ($defaultCount !== 1) {
            throw new BusinessException('default language required', ErrorCode::INVALID_PARAMS);
        }

        if ($enabledCount === 0) {
            throw new BusinessException('at least one enabled language required', ErrorCode::INVALID_PARAMS);
        }

        $replacedItems = $this->languageRepository->replaceAll($normalized);
        $nextCodes = array_map(
            static fn (array $item): string => strtolower(trim((string) ($item['code'] ?? ''))),
            $replacedItems
        );
        foreach (array_values(array_diff($previousCodes, $nextCodes)) as $removedCode) {
            $this->languageWorkspaceRepository->removeMeta($removedCode);
        }

        $defaultCode = '';
        foreach ($replacedItems as $item) {
            if ((int) ($item['is_default'] ?? 0) === 1) {
                $defaultCode = strtolower(trim((string) ($item['code'] ?? '')));
                break;
            }
        }

        if ($defaultCode !== '') {
            $siteConfig = $this->systemSettingRepository->siteConfig();
            $siteConfig['default_language'] = $defaultCode;
            $this->systemSettingRepository->put('site', 'config', $siteConfig);
        }

        $nextEnabledCodes = $this->enabledLanguageCodes($replacedItems);
        $newlyEnabledCodes = array_values(array_diff($nextEnabledCodes, $previousEnabledCodes));
        if ($newlyEnabledCodes !== []) {
            foreach ($newlyEnabledCodes as $languageCode) {
                $this->sitePhraseWorkspaceRepository->initializeLanguage($languageCode);
            }
            $this->scheduleHistoricalContentJobs($newlyEnabledCodes);
        }

        foreach ($replacedItems as $item) {
            $code = strtolower(trim((string) ($item['code'] ?? '')));
            $existingMeta = $this->languageWorkspaceRepository->getMeta($code);
            $phraseSummary = $this->sitePhraseWorkspaceRepository->buildSummary($code);
            $payload = [
                'native_name' => $rawMeta[$code]['native_name'] ?? (string) ($existingMeta['native_name'] ?? $item['name'] ?? ''),
                'english_name' => $rawMeta[$code]['english_name'] !== ''
                    ? $rawMeta[$code]['english_name']
                    : (string) ($existingMeta['english_name'] ?? strtoupper($code)),
                'zh_name' => $rawMeta[$code]['zh_name'] !== ''
                    ? $rawMeta[$code]['zh_name']
                    : (string) ($existingMeta['zh_name'] ?? $item['name'] ?? ''),
                'status' => (int) ($item['is_enabled'] ?? 0) === 1
                    ? ($code === 'zh' || (int) ($phraseSummary['pending'] ?? 0) === 0 ? 'ready' : 'preparing')
                    : 'paused',
            ];
            $this->languageWorkspaceRepository->upsertMeta($code, $payload);
        }

        $this->seoService->rebuildSitemap();
        $this->siteBuildService->queueFullBuild('language_settings_updated', [
            'language_codes' => $nextEnabledCodes,
        ], current_user());

        $result = $this->languages();
        $this->operationLogService->recordCurrentAction('system', 'system.languages.update', 'language_config', 'languages', 'language settings updated');

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, string>
     */
    private function enabledLanguageCodes(array $items): array
    {
        $codes = [];
        foreach ($items as $item) {
            if ((int) ($item['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $code = strtolower(trim((string) ($item['code'] ?? '')));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    private function resolveLanguageStatus(array $item, array $workspace, array $phraseSummary, array $translationStats): string
    {
        if ((int) ($item['is_enabled'] ?? 0) !== 1) {
            return 'paused';
        }

        $stored = strtolower(trim((string) ($workspace['status'] ?? '')));
        if ($stored === 'paused') {
            return 'paused';
        }

        $code = strtolower(trim((string) ($item['code'] ?? '')));
        $pendingTranslations = (int) ($translationStats['pending'] ?? 0)
            + (int) ($translationStats['processing'] ?? 0)
            + (int) ($translationStats['translating'] ?? 0)
            + (int) ($translationStats['review_required'] ?? 0);
        if ($code !== 'zh' && ((int) ($phraseSummary['pending'] ?? 0) > 0 || $pendingTranslations > 0)) {
            return 'preparing';
        }

        return 'ready';
    }

    /**
     * @param array<int, array<string, mixed>> $languageItems
     */
    private function resolveSitePhraseLanguageCode(array $languageItems, string $requested): string
    {
        $requested = strtolower(trim($requested));
        if ($requested !== '') {
            foreach ($languageItems as $item) {
                if (strtolower(trim((string) ($item['code'] ?? ''))) === $requested) {
                    return $requested;
                }
            }
        }

        foreach ($languageItems as $item) {
            if ((int) ($item['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $code = strtolower(trim((string) ($item['code'] ?? '')));
            if ($code !== '' && $code !== 'zh') {
                return $code;
            }
        }

        foreach ($languageItems as $item) {
            if ((int) ($item['is_default'] ?? 0) === 1) {
                $code = strtolower(trim((string) ($item['code'] ?? '')));
                if ($code !== '') {
                    return $code;
                }
            }
        }

        return 'zh';
    }

    /**
     * @param array<int, string> $languageCodes
     */
    private function scheduleHistoricalContentJobs(array $languageCodes): void
    {
        $targetCodes = array_values(array_filter(
            array_map(static fn (string $code): string => strtolower(trim($code)), $languageCodes),
            static fn (string $code): bool => $code !== '' && $code !== 'zh'
        ));

        if ($targetCodes === []) {
            return;
        }

        foreach ($this->listHistoricalEntities() as [$entityType, $entityId]) {
            foreach ($targetCodes as $languageCode) {
                $this->translationRepository->upsertJob($entityType, $entityId, $languageCode, 'pending', null);
                $this->seoRepository->upsertJob($entityType, $entityId, $languageCode, 'pending', null);
            }
        }
    }

    /**
     * @return array<int, array{0:string,1:int}>
     */
    private function listHistoricalEntities(): array
    {
        $entities = [];

        $sources = [
            'product' => $this->extractItems($this->productRepository->list(['page' => 1, 'page_size' => 100000])),
            'solution' => $this->extractItems($this->solutionRepository->list(['page' => 1, 'page_size' => 100000])),
            'article' => $this->extractItems($this->articleRepository->list(['page' => 1, 'page_size' => 100000])),
            'news' => $this->extractItems($this->newsRepository->list(['page' => 1, 'page_size' => 100000])),
            'case' => $this->extractItems($this->caseRepository->list(['page' => 1, 'page_size' => 100000])),
            'page' => $this->extractItems($this->pageRepository->list(['page' => 1, 'page_size' => 100000])),
            'certificate' => $this->extractItems($this->certificateRepository->list()),
        ];

        foreach ($sources as $entityType => $items) {
            foreach ($items as $item) {
                $entityId = (int) ($item['id'] ?? 0);
                if ($entityId > 0) {
                    $entities[] = [$entityType, $entityId];
                }
            }
        }

        return $entities;
    }

    /**
     * @param mixed $result
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(mixed $result): array
    {
        if (is_array($result) && isset($result['items']) && is_array($result['items'])) {
            return array_values(array_filter($result['items'], 'is_array'));
        }

        if (is_array($result)) {
            return array_values(array_filter($result, 'is_array'));
        }

        return [];
    }

    public function deepseekConfig(): array
    {
        return ['config' => $this->sanitizeDeepseekConfig($this->rawDeepseekConfig())];
    }

    public function deepseekBootstrap(): array
    {
        return [
            'config' => $this->sanitizeDeepseekConfig($this->rawDeepseekConfig()),
            'models' => $this->deepseekModels(),
            'logs' => $this->deepseekLogs(),
            'balance' => $this->deepseekBalance(),
        ];
    }

    public function siteConfig(): array
    {
        return ['config' => $this->systemSettingRepository->siteConfig()];
    }

    public function siteBootstrap(): array
    {
        return [
            'config' => $this->systemSettingRepository->siteConfig(),
            'languages' => $this->languages(),
        ];
    }

    public function updateSiteConfig(array $input): array
    {
        $current = $this->systemSettingRepository->siteConfig();
        $config = [
            'site_name' => $this->validateText((string) ($input['site_name'] ?? $current['site_name'] ?? 'HANZUN'), 120, 'site_name'),
            'site_title' => $this->validateText((string) ($input['site_title'] ?? $current['site_title'] ?? ''), 180, 'site_title'),
            'logo_url' => $this->validateOptionalAssetPath((string) ($input['logo_url'] ?? $current['logo_url'] ?? '')),
            'logo_alt' => $this->validateText((string) ($input['logo_alt'] ?? $current['logo_alt'] ?? ''), 120, 'logo_alt'),
            'company_name' => $this->validateText((string) ($input['company_name'] ?? $current['company_name'] ?? ''), 180, 'company_name'),
            'company_subtitle' => $this->validateOptionalText((string) ($input['company_subtitle'] ?? $current['company_subtitle'] ?? ''), 180, 'company_subtitle'),
            'meta_description' => $this->validateOptionalText((string) ($input['meta_description'] ?? $current['meta_description'] ?? ''), 255, 'meta_description'),
            'footer_text' => $this->validateOptionalText((string) ($input['footer_text'] ?? $current['footer_text'] ?? ''), 255, 'footer_text'),
            'language_strategy' => $this->validateEnum((string) ($input['language_strategy'] ?? $current['language_strategy'] ?? 'ua-first'), ['ua-first', 'default-first'], 'language_strategy'),
            'default_language' => $this->validateLanguageCode((string) ($input['default_language'] ?? $current['default_language'] ?? 'zh')),
            'social_linkedin' => $this->validateOptionalUrl((string) ($input['social_linkedin'] ?? $current['social_linkedin'] ?? '')),
            'social_youtube' => $this->validateOptionalUrl((string) ($input['social_youtube'] ?? $current['social_youtube'] ?? '')),
            'enterprise_video_url' => $this->validateOptionalAssetPath((string) ($input['enterprise_video_url'] ?? $current['enterprise_video_url'] ?? '')),
        ];

        $this->syncDefaultLanguage($config['default_language']);
        $stored = $this->systemSettingRepository->put('site', 'config', $config);
        $this->operationLogService->recordCurrentAction('system', 'system.site.update', 'system_setting', 'site', 'site config updated');
        $this->siteBuildService->queueFullBuild('site_settings_updated', [], current_user());

        return ['config' => $stored];
    }

    public function sitePhrases(array $filters = []): array
    {
        $languagePayload = $this->languages();
        $languageItems = is_array($languagePayload['items'] ?? null) ? $languagePayload['items'] : [];
        $languageCode = $this->resolveSitePhraseLanguageCode($languageItems, (string) ($filters['language_code'] ?? ''));
        $workspace = $this->sitePhraseWorkspaceRepository->overview($languageCode, $filters);
        $languageMeta = null;
        foreach ($languageItems as $item) {
            if (strtolower(trim((string) ($item['code'] ?? ''))) === $languageCode) {
                $languageMeta = $item;
                break;
            }
        }

        return array_merge($workspace, [
            'languages' => $languageItems,
            'language_meta' => $languageMeta,
            'active_language_code' => $languageCode,
            'management_mode' => 'single_language_workspace',
        ]);
    }

    public function updateSitePhrases(array $items, string $languageCode = ''): array
    {
        $usesLegacyMatrix = isset($items[0]) && is_array($items[0]) && array_key_exists('translations', $items[0]);
        if ($usesLegacyMatrix) {
            $result = $this->sitePhraseRepository->replaceAll($items);
        } else {
            $result = $this->sitePhraseWorkspaceRepository->updateLanguageTranslations(
                $this->resolveSitePhraseLanguageCode(
                    is_array($this->languages()['items'] ?? null) ? $this->languages()['items'] : [],
                    $languageCode
                ),
                $items
            );
            $result['languages'] = $this->languages()['items'] ?? [];
            $result['active_language_code'] = strtolower(trim($languageCode)) !== ''
                ? strtolower(trim($languageCode))
                : ($result['language_code'] ?? 'zh');
            $result['management_mode'] = 'single_language_workspace';
        }

        $this->operationLogService->recordCurrentAction('system', 'system.site_phrases.update', 'system_setting', ['count' => count($items)], 'site phrases updated');
        $this->siteBuildService->queueFullBuild('site_phrases_updated', [], current_user());

        return $result;
    }

    public function updateDeepseekConfig(array $input): array
    {
        $current = $this->rawDeepseekConfig();
        $apiKey = trim((string) ($current['api_key'] ?? ''));
        $hasApiKeyInput = array_key_exists('api_key', $input);
        if ($hasApiKeyInput) {
            $incomingApiKey = trim((string) ($input['api_key'] ?? ''));
            if ($incomingApiKey !== '' && !str_contains($incomingApiKey, '*')) {
                $apiKey = $incomingApiKey;
            }
            if ($hasApiKeyInput && $incomingApiKey === '') {
                $apiKey = '';
            }
        }

        $config = [
            'base_url' => $this->validateBaseUrl((string) ($input['base_url'] ?? $current['base_url'] ?? 'https://dashscope.aliyuncs.com/compatible-mode/v1')),
            'model' => $this->validateModel((string) ($input['model'] ?? $current['model'] ?? 'qwen-plus')),
            'api_key' => $apiKey,
            'timeout_seconds' => $this->validateRange((int) ($input['timeout_seconds'] ?? $current['timeout_seconds'] ?? 90), 5, 180, 'timeout_seconds'),
            'retry_times' => $this->validateRange((int) ($input['retry_times'] ?? $current['retry_times'] ?? 2), 0, 10, 'retry_times'),
            'chat_enabled' => $this->normalizeFlag($input['chat_enabled'] ?? ($current['chat_enabled'] ?? 1)),
            'translation_enabled' => $this->normalizeFlag($input['translation_enabled'] ?? ($current['translation_enabled'] ?? 1)),
            'seo_enabled' => $this->normalizeFlag($input['seo_enabled'] ?? ($current['seo_enabled'] ?? 1)),
            'knowledge_enabled' => $this->normalizeFlag($input['knowledge_enabled'] ?? ($current['knowledge_enabled'] ?? 1)),
            'knowledge_top_k' => $this->validateRange((int) ($input['knowledge_top_k'] ?? $current['knowledge_top_k'] ?? 5), 1, 20, 'knowledge_top_k'),
            'knowledge_max_chars' => $this->validateRange((int) ($input['knowledge_max_chars'] ?? $current['knowledge_max_chars'] ?? 128000), 500, 128000, 'knowledge_max_chars'),
            'knowledge_auto_sync_cms' => $this->normalizeFlag($input['knowledge_auto_sync_cms'] ?? ($current['knowledge_auto_sync_cms'] ?? 1)),
            'chat_max_history_messages' => $this->validateRange((int) ($input['chat_max_history_messages'] ?? $current['chat_max_history_messages'] ?? 6), 0, 20, 'chat_max_history_messages'),
            'prompts' => $this->mergeDeepseekPrompts($current['prompts'] ?? [], $input['prompts'] ?? null),
        ];

        $stored = $this->systemSettingRepository->put('deepseek', 'config', $config);
        $this->operationLogService->recordCurrentAction('system', 'system.deepseek.update', 'system_setting', 'deepseek', 'deepseek config updated');

        return ['config' => $this->sanitizeDeepseekConfig($stored)];
    }

    public function operationLogs(int $page = 1, int $pageSize = 20, array $filters = []): array
    {
        return $this->operationLogService->listOperations($page, $pageSize, $filters);
    }

    public function logsBootstrap(
        int $operationPage = 1,
        int $operationPageSize = 10,
        array $operationFilters = [],
        int $loginPage = 1,
        int $loginPageSize = 10,
        array $loginFilters = []
    ): array {
        return [
            'action_points' => [
                'items' => (new \app\service\system\AdminManageService())->actionPoints()['items'] ?? [],
            ],
            'operation_logs' => $this->operationLogs($operationPage, $operationPageSize, $operationFilters),
            'login_logs' => $this->loginLogs($loginPage, $loginPageSize, $loginFilters),
        ];
    }

    public function deepseekLogs(): array
    {
        $items = array_map(fn (array $item): array => $this->normalizeDeepseekLogItem($item), $this->deepSeekLogRepository->list());
        $today = date('Y-m-d');
        $todayItems = array_values(array_filter(
            $items,
            static fn (array $item): bool => str_starts_with((string) ($item['created_at'] ?? ''), $today)
        ));

        return [
            'items' => $items,
            'summary' => [
                'today_total' => count($todayItems),
                'today_chat' => $this->countDeepseekLogsByFeature($todayItems, 'chat'),
                'today_seo' => $this->countDeepseekLogsByFeature($todayItems, 'seo'),
                'today_translation' => $this->countDeepseekLogsByFeature($todayItems, 'translation'),
                'failed_count' => count(array_filter(
                    $todayItems,
                    static fn (array $item): bool => (int) ($item['is_success'] ?? 0) !== 1
                )),
            ],
        ];
    }

    public function deepseekModels(): array
    {
        $config = $this->rawDeepseekConfig();
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        if ($apiKey === '') {
            return [
                'items' => $this->defaultDeepseekModels(),
                'total' => count($this->defaultDeepseekModels()),
                'source' => 'fallback',
                'fallback_reason' => 'missing_api_key',
                'message' => '尚未配置 DashScope API Key，当前展示默认模型示例。',
            ];
        }

        try {
            $client = $this->deepSeekClient;
            $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://dashscope.aliyuncs.com/compatible-mode/v1'), '/');
            $response = $client->listModels($baseUrl, $apiKey);
            $models = is_array($response['items'] ?? null) ? $response['items'] : [];
            if (empty($models)) {
                return [
                    'items' => $this->defaultDeepseekModels(),
                    'total' => count($this->defaultDeepseekModels()),
                    'source' => 'fallback',
                    'fallback_reason' => 'empty_live_models',
                    'message' => '实时接口暂未返回模型列表，当前展示默认模型示例。',
                    'request_url' => (string) ($response['request_url'] ?? $baseUrl . '/models'),
                    'raw_counts' => $response['raw_counts'] ?? [],
                ];
            }
            return [
                'items' => $models,
                'total' => count($models),
                'source' => 'live',
                'fallback_reason' => '',
                'message' => '实时模型列表获取成功。',
                'request_url' => (string) ($response['request_url'] ?? $baseUrl . '/models'),
                'raw_counts' => $response['raw_counts'] ?? [],
            ];
        } catch (\Exception $e) {
            return [
                'items' => $this->defaultDeepseekModels(),
                'total' => count($this->defaultDeepseekModels()),
                'source' => 'fallback',
                'fallback_reason' => 'request_failed',
                'message' => '实时模型列表获取失败：' . $this->normalizeDeepseekTestMessage($e->getMessage()),
                'request_url' => rtrim((string) ($config['base_url'] ?? 'https://dashscope.aliyuncs.com/compatible-mode/v1'), '/') . '/models',
                'raw_counts' => [],
            ];
        }
    }

    public function deepseekBalance(): array
    {
        $config = $this->rawDeepseekConfig();
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        if ($apiKey === '') {
            return ['balance' => null, 'message' => '尚未配置 DashScope API Key。'];
        }

        try {
            $client = $this->deepSeekClient;
            $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://dashscope.aliyuncs.com/compatible-mode/v1'), '/');
            $result = $client->checkBalance($baseUrl, $apiKey);

            if (isset($result['error'])) {
                return ['balance' => null, 'message' => '读取 DashScope 账户信息失败：' . $result['error']];
            }

            return [
                'balance' => null,
                'total_usage' => null,
                'message' => 'DashScope 账户信息已返回，当前接口未提供可直接展示的余额字段。',
            ];
        } catch (\Exception $e) {
            return ['balance' => null, 'message' => '读取 DashScope 账户信息失败：' . $e->getMessage()];
        }
    }

    private function defaultDeepseekModels(): array
    {
        return [
            ['id' => 'qwen-max', 'name' => 'Qwen Max'],
            ['id' => 'qwen-plus', 'name' => 'Qwen Plus'],
            ['id' => 'qwen-turbo', 'name' => 'Qwen Turbo'],
            ['id' => 'qwen3-32b', 'name' => 'Qwen3 32B'],
            ['id' => 'qwen3-8b', 'name' => 'Qwen3 8B'],
        ];
    }
    
    public function testDeepseekConnection(array $input = []): array
    {
        $testedAt = date('Y-m-d H:i:s');
        $current = $this->rawDeepseekConfig();
        if ($input === []) {
            $input = $this->readDeepseekTestPayload();
        }

        $testConfig = $this->buildDeepseekTestConfig($current, $input);
        $status = 'success';
        $message = 'DashScope 连接测试通过。';

        try {
            $this->runDeepseekConnectionTest($testConfig);
        } catch (BusinessException $exception) {
            $status = 'failed';
            $message = $this->normalizeDeepseekTestMessage($exception->getMessage());
        }

        $this->systemSettingRepository->put('deepseek', 'config', array_merge($current, [
            'last_tested_at' => $testedAt,
            'last_test_status' => $status,
            'last_test_message' => $message,
        ]));

        $this->operationLogService->recordCurrentAction(
            'system',
            'system.deepseek.test',
            'system_setting',
            'deepseek',
            'deepseek connection tested'
        );

        $responseConfig = array_merge($testConfig, [
            'last_tested_at' => $testedAt,
            'last_test_status' => $status,
            'last_test_message' => $message,
        ]);

        return [
            'status' => $status,
            'message' => $message,
            'tested_at' => $testedAt,
            'connection_label' => $this->buildDeepseekConnectionLabel($responseConfig),
            'config' => $this->sanitizeDeepseekConfig($responseConfig),
        ];
    }

    public function loginLogs(int $page = 1, int $pageSize = 20, array $filters = []): array
    {
        return $this->operationLogService->listLoginLogs($page, $pageSize, $filters);
    }

    private function rawDeepseekConfig(): array
    {
        return $this->systemSettingRepository->deepseekConfig();
    }

    private function sanitizeDeepseekConfig(array $config): array
    {
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        $config['api_key'] = '';
        $config['api_key_masked'] = $this->maskApiKey($apiKey);
        $config['has_api_key'] = $apiKey !== '' ? 1 : 0;
        $config['connection_label'] = $this->buildDeepseekConnectionLabel($config);

        return $config;
    }

    private function buildDeepseekConnectionLabel(array $config): string
    {
        $hasApiKey = trim((string) ($config['api_key'] ?? '')) !== ''
            || (int) ($config['has_api_key'] ?? 0) === 1
            || trim((string) ($config['api_key_masked'] ?? '')) !== '';
        $testedAt = trim((string) ($config['last_tested_at'] ?? ''));
        $status = trim((string) ($config['last_test_status'] ?? ''));
        $message = trim((string) ($config['last_test_message'] ?? ''));

        if ($testedAt !== '' && $status === 'success') {
            return 'DashScope 最近测试通过：' . $testedAt;
        }

        if ($testedAt !== '' && $status === 'failed') {
            return 'DashScope 最近测试失败：' . $testedAt . ($message !== '' ? (' / ' . $message) : '');
        }

        return $hasApiKey
            ? '已配置 DashScope API Key，尚未执行连接测试'
            : '尚未配置 DashScope API Key';
    }

    private function readDeepseekTestPayload(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function buildDeepseekTestConfig(array $current, array $input): array
    {
        $apiKey = trim((string) ($current['api_key'] ?? ''));
        $incomingApiKey = trim((string) ($input['api_key'] ?? ''));
        if ($incomingApiKey !== '' && !str_contains($incomingApiKey, '*')) {
            $apiKey = $incomingApiKey;
        }

        return [
            'base_url' => $this->validateBaseUrl((string) ($input['base_url'] ?? $current['base_url'] ?? 'https://dashscope.aliyuncs.com/compatible-mode/v1')),
            'model' => $this->validateModel((string) ($input['model'] ?? $current['model'] ?? 'qwen-plus')),
            'api_key' => $apiKey,
            'timeout_seconds' => $this->validateRange((int) ($input['timeout_seconds'] ?? $current['timeout_seconds'] ?? 90), 5, 180, 'timeout_seconds'),
            'retry_times' => $this->validateRange((int) ($input['retry_times'] ?? $current['retry_times'] ?? 2), 0, 10, 'retry_times'),
            'chat_enabled' => $this->normalizeFlag($input['chat_enabled'] ?? ($current['chat_enabled'] ?? 1)),
            'translation_enabled' => $this->normalizeFlag($input['translation_enabled'] ?? ($current['translation_enabled'] ?? 1)),
            'seo_enabled' => $this->normalizeFlag($input['seo_enabled'] ?? ($current['seo_enabled'] ?? 1)),
            'knowledge_enabled' => $this->normalizeFlag($input['knowledge_enabled'] ?? ($current['knowledge_enabled'] ?? 1)),
            'knowledge_top_k' => $this->validateRange((int) ($input['knowledge_top_k'] ?? $current['knowledge_top_k'] ?? 5), 1, 20, 'knowledge_top_k'),
            'knowledge_max_chars' => $this->validateRange((int) ($input['knowledge_max_chars'] ?? $current['knowledge_max_chars'] ?? 128000), 500, 128000, 'knowledge_max_chars'),
            'knowledge_auto_sync_cms' => $this->normalizeFlag($input['knowledge_auto_sync_cms'] ?? ($current['knowledge_auto_sync_cms'] ?? 1)),
            'chat_max_history_messages' => $this->validateRange((int) ($input['chat_max_history_messages'] ?? $current['chat_max_history_messages'] ?? 6), 0, 20, 'chat_max_history_messages'),
            'prompts' => $this->mergeDeepseekPrompts($current['prompts'] ?? [], $input['prompts'] ?? null),
        ];
    }

    private function runDeepseekConnectionTest(array $config): void
    {
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        if ($apiKey === '') {
            throw new BusinessException('dashscope api key missing', ErrorCode::INVALID_PARAMS);
        }

        $result = $this->requestDeepseekJson(
            rtrim((string) ($config['base_url'] ?? 'https://dashscope.aliyuncs.com/compatible-mode/v1'), '/') . '/chat/completions',
            $apiKey,
            [
                'model' => (string) ($config['model'] ?? 'qwen-plus'),
                'messages' => [
                    ['role' => 'system', 'content' => 'Return JSON only.'],
                    ['role' => 'user', 'content' => '{"ping":"pong"}'],
                ],
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ],
            (int) ($config['timeout_seconds'] ?? 90),
            max(1, (int) ($config['retry_times'] ?? 0) + 1)
        );

        $content = $result['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new BusinessException('dashscope empty response', ErrorCode::INTERNAL_ERROR);
        }

        $decoded = json_decode(trim($content), true);
        if (is_array($decoded)) {
            return;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $content, $matches) === 1) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded)) {
                return;
            }
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return;
            }
        }

        throw new BusinessException('dashscope json parse failed', ErrorCode::INTERNAL_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function requestDeepseekJson(string $url, string $apiKey, array $payload, int $timeoutSeconds, int $maxAttempts): array
    {
        $lastException = null;
        $requestUrls = $this->candidateCompatibleUrls($url);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            foreach ($requestUrls as $index => $requestUrl) {
                try {
                    return $this->requestDeepseekJsonOnce($requestUrl, $apiKey, $payload, $timeoutSeconds);
                } catch (BusinessException $exception) {
                    $lastException = $exception;
                    $canTryNextRegion = $index < count($requestUrls) - 1 && $this->shouldRetryAlternateCompatibleUrl($exception);
                    if ($canTryNextRegion) {
                        continue;
                    }

                    if ($attempt === $maxAttempts) {
                        throw $exception;
                    }
                }
            }
        }

        throw $lastException ?? new BusinessException('dashscope request failed', ErrorCode::INTERNAL_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function requestDeepseekJsonOnce(string $url, string $apiKey, array $payload, int $timeoutSeconds): array
    {
        $caBundle = $this->resolveCaBundlePath();

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $options = [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];
            if ($caBundle !== null) {
                $options[CURLOPT_CAINFO] = $caBundle;
            }
            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if (!is_string($response) || $response === '') {
                throw new BusinessException($error !== '' ? $error : 'dashscope request failed', ErrorCode::INTERNAL_ERROR);
            }

            $decoded = json_decode($response, true);
            if ($statusCode >= 400) {
                $message = is_array($decoded) ? (string) (($decoded['error']['message'] ?? $decoded['message'] ?? 'dashscope request failed')) : 'dashscope request failed';
                throw new BusinessException($message, ErrorCode::INTERNAL_ERROR);
            }

            return is_array($decoded) ? $decoded : [];
        }

        $contextOptions = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ]),
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'timeout' => $timeoutSeconds,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];
        if ($caBundle !== null) {
            $contextOptions['ssl']['cafile'] = $caBundle;
        }
        $context = stream_context_create($contextOptions);

        $response = @file_get_contents($url, false, $context);
        if (!is_string($response) || $response === '') {
            throw new BusinessException('dashscope request failed', ErrorCode::INTERNAL_ERROR);
        }

        $decoded = json_decode($response, true);
        $statusCode = $this->extractHttpStatusCode($http_response_header ?? []);
        if ($statusCode >= 400) {
            $message = is_array($decoded) ? (string) (($decoded['error']['message'] ?? $decoded['message'] ?? 'dashscope request failed')) : 'dashscope request failed';
            throw new BusinessException($message, ErrorCode::INTERNAL_ERROR);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int, string> $headers
     */
    private function extractHttpStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function normalizeDeepseekLogItem(array $item): array
    {
        $featureCode = (string) ($item['feature_code'] ?? 'chat');
        $normalized = $item;
        $normalized['feature_code'] = $featureCode;
        $normalized['feature_name'] = $this->deepseekFeatureName($featureCode);
        $normalized['is_success'] = (int) ($item['is_success'] ?? 0);
        $normalized['status_code'] = (int) ($item['status_code'] ?? 0);
        $normalized['duration_ms'] = (int) ($item['duration_ms'] ?? 0);
        $normalized['attempts'] = max(1, (int) ($item['attempts'] ?? 1));
        $normalized['error_message'] = (string) ($item['error_message'] ?? '');

        return $normalized;
    }

    private function resolveCaBundlePath(): ?string
    {
        $candidates = [
            trim((string) ini_get('curl.cainfo')),
            trim((string) ini_get('openssl.cafile')),
            trim((string) env('SSL_CERT_FILE', '')),
            trim((string) env('CURL_CA_BUNDLE', '')),
        ];

        $userProfile = getenv('USERPROFILE');
        if (is_string($userProfile) && $userProfile !== '') {
            $candidates[] = $userProfile . '\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\python\\Lib\\site-packages\\pip\\_vendor\\certifi\\cacert.pem';
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function mergeDeepseekPrompts(array $current, mixed $input): array
    {
        if ($input === null) {
            return $current;
        }

        if (!is_array($input)) {
            throw new BusinessException('invalid prompts', ErrorCode::INVALID_PARAMS);
        }

        $merged = $current;
        foreach ($input as $feature => $templates) {
            $feature = trim((string) $feature);
            if ($feature === '' || !is_array($templates)) {
                throw new BusinessException('invalid prompts', ErrorCode::INVALID_PARAMS);
            }

            $currentTemplates = $merged[$feature] ?? [];
            if (!is_array($currentTemplates)) {
                $currentTemplates = [];
            }

            $merged[$feature] = array_merge($currentTemplates, $this->normalizeDeepseekPromptTemplates($templates));
        }

        return $merged;
    }

    private function normalizeDeepseekPromptTemplates(array $templates): array
    {
        $normalized = [];
        foreach ($templates as $template => $value) {
            $template = trim((string) $template);
            if ($template === '') {
                throw new BusinessException('invalid prompts', ErrorCode::INVALID_PARAMS);
            }

            $normalized[$template] = trim((string) $value);
        }

        return $normalized;
    }

    private function countDeepseekLogsByFeature(array $items, string $featureCode): int
    {
        return count(array_filter(
            $items,
            static fn (array $item): bool => (string) ($item['feature_code'] ?? '') === $featureCode
        ));
    }

    private function deepseekFeatureName(string $featureCode): string
    {
        return match ($featureCode) {
            'translation' => 'AI 翻译',
            'seo' => 'SEO 生成',
            default => 'AI 对话',
        };
    }

    /**
     * @return array<int, string>
     */
    private function candidateCompatibleUrls(string $url): array
    {
        $urls = [$url];

        if (str_contains($url, 'https://dashscope.aliyuncs.com/compatible-mode/v1/')) {
            $urls[] = str_replace(
                'https://dashscope.aliyuncs.com/compatible-mode/v1/',
                'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/',
                $url
            );
        } elseif (str_contains($url, 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/')) {
            $urls[] = str_replace(
                'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/',
                'https://dashscope.aliyuncs.com/compatible-mode/v1/',
                $url
            );
        }

        return array_values(array_unique($urls));
    }

    private function shouldRetryAlternateCompatibleUrl(BusinessException $exception): bool
    {
        $message = strtolower(trim($exception->getMessage()));

        return str_contains($message, 'incorrect api key')
            || str_contains($message, 'invalid_api_key')
            || str_contains($message, 'apikey-error');
    }

    private function normalizeDeepseekTestMessage(string $message): string
    {
        $raw = trim($message);
        $normalized = strtolower($raw);

        if ($normalized === '' || $normalized === 'dashscope request failed') {
            return 'DashScope 请求失败，请检查接口地址、模型名称或网络连接。';
        }

        if (str_contains($normalized, 'dashscope api key missing')) {
            return '尚未提供 DashScope API Key。';
        }

        if (
            str_contains($normalized, 'incorrect api key')
            || str_contains($normalized, 'invalid_api_key')
            || str_contains($normalized, 'apikey-error')
        ) {
            return '当前 API Key 无效，或未开通对应的 DashScope 模型服务。';
        }

        if (str_contains($normalized, 'http 401') || str_contains($normalized, '401 unauthorized')) {
            return '接口返回 401，通常表示 API Key 无效、账号未授权或模型服务未开通。';
        }

        if (str_contains($normalized, 'http 403') || str_contains($normalized, '403 forbidden')) {
            return '接口返回 403，当前账号没有访问该模型或接口的权限。';
        }

        if (str_contains($normalized, 'ssl certificate') || str_contains($normalized, 'unable to get local issuer certificate')) {
            return '当前环境访问 DashScope 时 SSL 证书校验失败，请检查 PHP 或 cURL 证书链配置。';
        }

        if (str_contains($normalized, 'empty response')) {
            return 'AI 接口返回内容为空，请稍后重试。';
        }

        if (str_contains($normalized, 'json parse failed')) {
            return 'AI 接口返回内容格式异常，未能解析为 JSON。';
        }

        if (str_contains($normalized, 'timed out') || str_contains($normalized, 'timeout')) {
            return 'AI 请求已超时，请检查网络连通性或适当提高超时设置。';
        }

        return $raw;
    }

    private function validateBaseUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '' || filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new BusinessException('invalid base_url', ErrorCode::INVALID_PARAMS);
        }

        return rtrim($value, '/');
    }

    private function validateModel(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strlen($value) > 64) {
            throw new BusinessException('invalid model', ErrorCode::INVALID_PARAMS);
        }

        return $value;
    }

    private function validateRange(int $value, int $min, int $max, string $field): int
    {
        if ($value < $min || $value > $max) {
            throw new BusinessException('invalid ' . $field, ErrorCode::INVALID_PARAMS);
        }

        return $value;
    }

    private function validateText(string $value, int $maxLength, string $field): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new BusinessException('invalid ' . $field, ErrorCode::INVALID_PARAMS);
        }

        return $value;
    }

    private function validateOptionalText(string $value, int $maxLength, string $field): string
    {
        $value = trim($value);
        if ($value !== '' && mb_strlen($value) > $maxLength) {
            throw new BusinessException('invalid ' . $field, ErrorCode::INVALID_PARAMS);
        }

        return $value;
    }

    private function validateOptionalUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new BusinessException('invalid url', ErrorCode::INVALID_PARAMS);
        }

        return $value;
    }

    private function validateOptionalAssetPath(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new BusinessException('invalid logo_url', ErrorCode::INVALID_PARAMS);
        }

        return $value;
    }

    private function validateEnum(string $value, array $allowed, string $field): string
    {
        $value = trim($value);
        if (!in_array($value, $allowed, true)) {
            throw new BusinessException('invalid ' . $field, ErrorCode::INVALID_PARAMS);
        }

        return $value;
    }

    private function validateLanguageCode(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '' || preg_match('/^[a-z]{2}$/', $value) !== 1) {
            throw new BusinessException('invalid default_language', ErrorCode::INVALID_PARAMS);
        }

        return $value;
    }

    private function syncDefaultLanguage(string $defaultLanguage): void
    {
        $defaultLanguage = $this->validateLanguageCode($defaultLanguage);
        $items = $this->languageRepository->list();
        if ($items === []) {
            throw new BusinessException('language config missing', ErrorCode::INVALID_PARAMS);
        }

        $matched = false;
        $normalized = [];
        foreach ($items as $item) {
            $code = strtolower(trim((string) ($item['code'] ?? '')));
            $isTarget = $code === $defaultLanguage;
            if ($isTarget && (int) ($item['is_enabled'] ?? 0) !== 1) {
                throw new BusinessException('default language must be enabled', ErrorCode::INVALID_PARAMS);
            }

            if ($isTarget) {
                $matched = true;
            }

            $item['is_default'] = $isTarget ? 1 : 0;
            $normalized[] = $item;
        }

        if (!$matched) {
            throw new BusinessException('default language not found', ErrorCode::INVALID_PARAMS);
        }

        $this->languageRepository->replaceAll($normalized);
    }

    private function validateNickname(string $nickname, string $fallback): string
    {
        $nickname = trim($nickname);
        if ($nickname === '') {
            $nickname = $fallback;
        }

        if (mb_strlen($nickname) > 50) {
            throw new BusinessException('nickname too long', ErrorCode::INVALID_PARAMS);
        }

        return $nickname;
    }

    private function validateEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '') {
            return '';
        }

        if (strlen($email) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessException('invalid email', ErrorCode::INVALID_PARAMS);
        }

        return $email;
    }

    private function validateMobile(string $mobile): string
    {
        $mobile = trim($mobile);
        if ($mobile === '') {
            return '';
        }

        if (preg_match('/^[0-9+\-\s()]{6,30}$/', $mobile) !== 1) {
            throw new BusinessException('invalid mobile', ErrorCode::INVALID_PARAMS);
        }

        return $mobile;
    }

    private function validatePassword(string $password): string
    {
        $password = trim($password);
        if ($password === '') {
            return '';
        }

        if (strlen($password) < 8 || strlen($password) > 64) {
            throw new BusinessException('invalid password length', ErrorCode::INVALID_PARAMS);
        }

        return $password;
    }

    private function normalizeFlag(mixed $value): int
    {
        if ($value === 1 || $value === '1' || $value === true || $value === 'true' || $value === 'enabled') {
            return 1;
        }

        if ($value === 0 || $value === '0' || $value === false || $value === 'false' || $value === 'disabled') {
            return 0;
        }

        throw new BusinessException('invalid switch value', ErrorCode::INVALID_PARAMS);
    }

    private function maskApiKey(string $apiKey): string
    {
        if ($apiKey === '') {
            return '';
        }

        if (strlen($apiKey) <= 8) {
            return str_repeat('*', strlen($apiKey));
        }

        return substr($apiKey, 0, 4) . str_repeat('*', max(strlen($apiKey) - 8, 4)) . substr($apiKey, -4);
    }
}
