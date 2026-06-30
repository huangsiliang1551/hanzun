<?php

declare(strict_types=1);

namespace app\service\content;

use app\repository\ArticleRepository;
use app\repository\CaseRepository;
use app\repository\CertificateRepository;
use app\repository\LanguageRepository;
use app\repository\NewsRepository;
use app\repository\PageRepository;
use app\repository\ProductRepository;
use app\repository\SeoRepository;
use app\repository\SolutionRepository;
use app\repository\SystemSettingRepository;
use app\repository\TranslationRepository;
use app\service\seo\SeoService;
use app\service\translation\TranslationService;

final class ContentPipelineService
{
    public function __construct(
        private readonly LanguageRepository $languageRepository = new LanguageRepository(),
        private readonly TranslationRepository $translationRepository = new TranslationRepository(),
        private readonly SeoRepository $seoRepository = new SeoRepository(),
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository(),
        private readonly ProductRepository $productRepository = new ProductRepository(),
        private readonly SolutionRepository $solutionRepository = new SolutionRepository(),
        private readonly ArticleRepository $articleRepository = new ArticleRepository(),
        private readonly NewsRepository $newsRepository = new NewsRepository(),
        private readonly CaseRepository $caseRepository = new CaseRepository(),
        private readonly PageRepository $pageRepository = new PageRepository(),
        private readonly CertificateRepository $certificateRepository = new CertificateRepository(),
        private readonly TranslationService $translationService = new TranslationService(),
        private readonly SeoService $seoService = new SeoService()
    ) {
    }

    public function sync(string $entityType, array $record, bool $refreshJobs = true): array
    {
        $entityId = (int) ($record['id'] ?? 0);
        if ($entityId <= 0) {
            return $record;
        }

        $config = $this->systemSettingRepository->get('deepseek', 'config', [
            'translation_enabled' => 1,
            'seo_enabled' => 1,
        ]);
        $languages = $this->enabledLanguages();
        $translationLanguages = $this->translationLanguages($languages);

        $slug = $this->resolveSlug($entityType, $record);
        $published = (string) ($record['publish_status'] ?? 'draft') === 'published';
        $translationEnabled = (int) ($config['translation_enabled'] ?? 1) === 1;
        $seoEnabled = (int) ($config['seo_enabled'] ?? 1) === 1;

        // Content hash for diff detection — skip unchanged entities
        $sourceHash = sha1(
            trim((string)($record['title_zh'] ?? $record['name_zh'] ?? ''))
            . trim((string)($record['summary_zh'] ?? ''))
            . trim((string)($record['content_zh'] ?? ''))
        );

        // Create translation jobs as single-row per entity (with JSON for languages)
        if ($refreshJobs && $translationEnabled) {
            $this->translationRepository->upsertEntityJob($entityType, $entityId, $translationLanguages, $sourceHash, 'pending');
        }

        if ($refreshJobs && $seoEnabled) {
            $this->seoRepository->upsertEntityJob($entityType, $entityId, $languages, $sourceHash, 'pending');
        }

        // ── Async dispatch: jobs are created as 'pending' above ──
        // Daemon picks up pending jobs and executes them via process-jobs-daemon.php
        // (systemd service, completely decoupled from HTTP requests).
        // This prevents PHP-FPM timeout (100s) from killing long-running AI API calls.
        if ($refreshJobs && ($translationEnabled || $seoEnabled)) {
            $this->dispatchAsyncJobs($entityType, $entityId);
        }

        // Batch upsert SEO routes — 1 INSERT instead of N individual calls
        $baseUrl = rtrim((string) env('APP_URL', 'http://127.0.0.1:8080'), '/');
        $languageRoutes = [];
        foreach ($languages as $languageCode) {
            $routePath = $this->buildRoutePath($entityType, $record, $languageCode, $slug);
            $languageRoutes[] = [
                'language_code' => $languageCode,
                'route_path' => $routePath,
                'canonical_url' => $baseUrl . $routePath,
            ];
        }
        $this->seoRepository->batchUpsertRoutes(
            $entityType, $entityId, $languageRoutes, $slug,
            $published ? 'index' : 'noindex'
        );

        (new \app\service\seo\PageSeoSyncService())->syncByEntityType($entityType);

        $updatedRecord = $record;
        $updatedRecord['slug'] = $slug;
        $updatedRecord['translation_status'] = $this->resolveTranslationStatus(
            $translationEnabled,
            $translationLanguages,
            $refreshJobs,
            (string) ($record['translation_status'] ?? '')
        );
        $updatedRecord['seo_status'] = $this->resolveSeoStatus($seoEnabled, $languages, $refreshJobs, (string) ($record['seo_status'] ?? ''));

        if ($this->needsEntityUpdate($record, $updatedRecord)) {
            $saved = $this->saveEntity($entityType, $entityId, $updatedRecord);
            if (is_array($saved)) {
                $updatedRecord = $saved;
            }
        }

        // ════════════════════════════════════════════════════════════════
        // CRITICAL PERFORMANCE FIX: AI tasks are queued as 'pending' jobs
        // above but NOT executed synchronously here. Calling DeepSeek API
        // within the save request blocks the UI for 2-30+ seconds per
        // language × N languages. Jobs are processed asynchronously via
        // the task center or scheduled worker.
        //
        // Removed: executeAutomaticJobs()
        // Removed: findEntity() re-read (redundant — caller already has data)
        // ════════════════════════════════════════════════════════════════

        if ($refreshJobs) {
            // Only re-fetch if slug/status may have changed via saveEntity above
            if ($this->needsEntityUpdate($record, $updatedRecord)) {
                $refreshed = $this->findEntity($entityType, $entityId);
                if (is_array($refreshed)) {
                    return $refreshed;
                }
            }
        }

        return $updatedRecord;
    }

    /**
     * @return array<int, string>
     */
    private function enabledLanguages(): array
    {
        $languages = [];
        foreach ($this->languageRepository->list() as $item) {
            if ((int) ($item['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $code = trim((string) ($item['code'] ?? ''));
            if ($code !== '') {
                $languages[] = $code;
            }
        }

        return $languages === [] ? ['zh', 'en'] : array_values(array_unique($languages));
    }

    /**
     * @param array<int, string> $languages
     * @return array<int, string>
     */
    private function translationLanguages(array $languages): array
    {
        return array_values(array_filter($languages, static fn (string $languageCode): bool => $languageCode !== 'zh'));
    }

    /**
     * @param array<int, string> $translationLanguages
     */
    private function resolveTranslationStatus(bool $translationEnabled, array $translationLanguages, bool $refreshJobs, string $currentStatus): string
    {
        if (!$translationEnabled || $translationLanguages === []) {
            return 'completed';
        }

        if ($refreshJobs) {
            return 'pending';
        }

        return $currentStatus !== '' ? $currentStatus : 'pending';
    }

    /**
     * @param array<int, string> $languages
     */
    private function resolveSeoStatus(bool $seoEnabled, array $languages, bool $refreshJobs, string $currentStatus): string
    {
        if (!$seoEnabled || $languages === []) {
            return 'generated';
        }

        if ($refreshJobs) {
            return 'pending';
        }

        return $currentStatus !== '' ? $currentStatus : 'pending';
    }

    private function needsEntityUpdate(array $record, array $updatedRecord): bool
    {
        return (string) ($record['slug'] ?? '') !== (string) ($updatedRecord['slug'] ?? '')
            || (string) ($record['translation_status'] ?? '') !== (string) ($updatedRecord['translation_status'] ?? '')
            || (string) ($record['seo_status'] ?? '') !== (string) ($updatedRecord['seo_status'] ?? '');
    }

    private function saveEntity(string $entityType, int $entityId, array $record): ?array
    {
        return match ($entityType) {
            'product' => $this->productRepository->update($entityId, $record),
            'solution' => $this->solutionRepository->update($entityId, $record),
            'news' => $this->newsRepository->update($entityId, $record),
            'case' => $this->caseRepository->update($entityId, $record),
            'article' => $this->articleRepository->update($entityId, $record),
            'page' => $this->pageRepository->update($entityId, $record),
            'certificate' => $this->certificateRepository->update($entityId, $record),
            default => null,
        };
    }

    private function findEntity(string $entityType, int $entityId): ?array
    {
        return match ($entityType) {
            'product' => $this->productRepository->find($entityId),
            'solution' => $this->solutionRepository->find($entityId),
            'news' => $this->newsRepository->find($entityId),
            'case' => $this->caseRepository->find($entityId),
            'article' => $this->articleRepository->find($entityId),
            'page' => $this->pageRepository->find($entityId),
            'certificate' => $this->certificateRepository->find($entityId),
            default => null,
        };
    }

    private function executeAutomaticJobs(string $entityType, int $entityId, bool $translationEnabled, bool $seoEnabled): void
    {
        if ($translationEnabled) {
            try {
                $this->translationService->executePendingEntityJobs($entityType, $entityId);
            } catch (\Throwable $exception) {
                unset($exception);
            }
        }

        if ($seoEnabled) {
            try {
                $this->seoService->executePendingEntityJobs($entityType, $entityId);
            } catch (\Throwable $exception) {
                unset($exception);
            }
        }
    }

    private function resolveSlug(string $entityType, array $record): string
    {
        $slug = trim((string) ($record['slug'] ?? ''));
        if ($slug !== '') {
            // Prevent purely numeric slugs — they are blocked by StaticPublisher's
            // isPublicRenderableItem filter (anti-test-content guard).
            if (preg_match('/^\d+$/', $slug)) {
                $slug = $entityType . '-' . $slug;
            }
            return $slug;
        }

        $source = trim((string) ($record['name_zh'] ?? $record['title_zh'] ?? ''));
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $source));
        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : $entityType . '-' . substr(sha1($source . ($record['id'] ?? '0')), 0, 10);
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

    /**
     * Spawn a detached PHP process to execute pending translation & SEO jobs
     * asynchronously. Returns immediately — does NOT wait for the process.
     */
    private function dispatchAsyncJobs(string $entityType, int $entityId): void
    {
        // Jobs are created as 'pending' in the DB during sync() above.
        // They are processed by the async job daemon (tools/process-jobs-daemon.php)
        // which runs as a systemd service, completely decoupled from HTTP requests.
        // This avoids PHP-FPM timeout (100s) killing long-running AI API calls.
        error_log(sprintf(
            '[async-jobs] Queued (daemon will pick up): %s #%d',
            $entityType,
            $entityId
        ));
    }

    private function preferSynchronousExecution(): bool
    {
        return (string) env('PREFER_RUNTIME_STORAGE', '0') === '1' || PHP_SAPI === 'cli';
    }
}
