<?php

declare(strict_types=1);

namespace app\service\dashboard;

use app\common\storage\JsonFileStore;
use app\common\storage\RuntimeStorage;
use app\service\inquiry\InquiryService;
use app\repository\DashboardRepository;
use app\repository\SeoRepository;
use app\repository\SiteBuildRepository;
use app\repository\TranslationRepository;

final class DashboardService
{
    private const OVERVIEW_CACHE_TTL_SECONDS = 15;

    private const OVERVIEW_CACHE_FILE = 'dashboard-overview-cache.json';

    private const OVERVIEW_CACHE_MAX_ENTRIES = 20;

    private readonly JsonFileStore $overviewCacheStore;

    public function __construct(
        private readonly DashboardRepository $dashboardRepository = new DashboardRepository(),
        private readonly TranslationRepository $translationRepository = new TranslationRepository(),
        private readonly SeoRepository $seoRepository = new SeoRepository(),
        private readonly SiteBuildRepository $siteBuildRepository = new SiteBuildRepository(),
        private readonly InquiryService $inquiryService = new InquiryService()
    ) {
        $this->overviewCacheStore = RuntimeStorage::store(self::OVERVIEW_CACHE_FILE);
    }

    public function traffic(string $range = '7d', ?string $startDate = null, ?string $endDate = null, bool $includeSummary = true): array
    {
        $this->dashboardRepository->setCustomDateRange($startDate, $endDate);
        $payload = [
            'range' => $range,
            'series' => $this->dashboardRepository->trafficSeries($range),
            'countries' => $this->dashboardRepository->trafficCountrySummary($range),
            'top_pages' => $this->dashboardRepository->trafficTopPages($range),
        ];

        if ($includeSummary) {
            $summary = $this->dashboardRepository->trafficSummary($range);
            $payload['uv'] = (int) ($summary['uv'] ?? 0);
            $payload['pv'] = (int) ($summary['pv'] ?? 0);
            $payload['bounce_rate'] = (float) ($summary['bounce_rate'] ?? 0);
        }

        return $payload;
    }

    public function overview(string $range = '7d', ?string $startDate = null, ?string $endDate = null, bool $lite = false): array
    {
        $overview = [
            'traffic' => $this->traffic($range, $startDate, $endDate, ! $lite),
            'ai' => $this->aiConversations($range, $startDate, $endDate),
            'inquiries' => $this->inquiries($range, $startDate, $endDate),
            'jobs' => $this->jobs(),
        ];
        if (! $lite) {
            $overview['content'] = $this->content($range, $startDate, $endDate);
        }

        return $overview;
    }

    public function aiConversations(string $range = '7d', ?string $startDate = null, ?string $endDate = null): array
    {
        $this->dashboardRepository->setCustomDateRange($startDate, $endDate);
        $summary = $this->dashboardRepository->aiSummary($range);

        return [
            'range' => $range,
            'total_sessions' => (int) ($summary['total_sessions'] ?? 0),
            'valid_sessions' => (int) ($summary['valid_sessions'] ?? 0),
            'created_inquiries' => (int) ($summary['created_inquiries'] ?? 0),
            'lead_capture_rate' => (float) ($summary['lead_capture_rate'] ?? 0),
            'series' => $this->dashboardRepository->aiSeries($range),
            'topics' => $this->dashboardRepository->aiTopicSummary($range),
            'countries' => $this->dashboardRepository->aiCountrySummary($range),
        ];
    }

    public function inquiries(string $range = '7d', ?string $startDate = null, ?string $endDate = null): array
    {
        $this->dashboardRepository->setCustomDateRange($startDate, $endDate);
        $liveStats = $this->inquiryService->stats([]);
        $statusCounts = is_array($liveStats['status_counts'] ?? null) ? $liveStats['status_counts'] : [];
        $summary = [
            'new_count' => (int) ($statusCounts['new'] ?? 0),
            'quoting_count' => (int) ($statusCounts['quoting'] ?? 0),
            'won_count' => (int) ($statusCounts['won'] ?? 0),
            'closed_count' => (int) ($statusCounts['closed'] ?? 0),
        ];

        return [
            'range' => $range,
            ...$summary,
            'series' => $this->dashboardRepository->inquirySeries($range),
            'countries' => $this->dashboardRepository->inquiryCountrySummary($range),
            'avg_first_response_minutes' => $this->dashboardRepository->inquiryAvgFirstResponseMinutes($range),
        ];
    }

    public function jobs(): array
    {
        $failedTranslation = $this->translationRepository->countByStatuses(['failed']);
        $reviewTranslation = $this->translationRepository->countByStatuses(['review_required']);
        $pendingTranslation = $this->translationRepository->countByStatuses(['pending']);
        $pendingSeo = $this->seoRepository->countByStatuses(['pending']);
        $failedSeo = $this->seoRepository->countByStatuses(['failed']);
        $siteBuildSummary = $this->siteBuildRepository->summary();
        $failedSiteBuild = (int) ($siteBuildSummary['failed'] ?? 0);

        return [
            'pending_translation' => $pendingTranslation,
            'review_translation' => $reviewTranslation,
            'pending_seo' => $pendingSeo,
            'failed_translation' => $failedTranslation,
            'failed_seo' => $failedSeo,
            'failed_site_build' => $failedSiteBuild,
            'failed_total' => $failedTranslation + $failedSeo + $failedSiteBuild,
            'seo_route_count' => $this->seoRepository->countRoutes(),
            'seo_404_count' => $this->seoRepository->count404Logs(),
        ];
    }

    public function content(string $range = '7d', ?string $startDate = null, ?string $endDate = null): array
    {
        $this->dashboardRepository->setCustomDateRange($startDate, $endDate);
        $summary = $this->dashboardRepository->contentPvSummary();

        return [
            'range' => $range,
            'product_pv' => (int) ($summary['product_pv'] ?? 0),
            'solution_pv' => (int) ($summary['solution_pv'] ?? 0),
            'news_pv' => (int) ($summary['news_pv'] ?? 0),
            'case_pv' => (int) ($summary['case_pv'] ?? 0),
            'top_products' => $this->dashboardRepository->contentTopEntitiesByType('product', 5),
            'top_solutions' => $this->dashboardRepository->contentTopEntitiesByType('solution', 5),
            'top_news' => $this->dashboardRepository->contentTopEntitiesByType('news', 5),
            'top_cases' => $this->dashboardRepository->contentTopEntitiesByType('case', 5),
        ];
    }

    private function buildOverviewCacheKey(string $range, ?string $startDate, ?string $endDate, bool $lite): string
    {
        $normalizedRange = strtolower(trim($range));
        $start = $startDate !== null ? trim($startDate) : '';
        $end = $endDate !== null ? trim($endDate) : '';

        return $normalizedRange . '|s:' . $start . '|e:' . $end . '|l:' . ($lite ? 1 : 0);
    }

    private function loadOverviewCache(string $cacheKey): ?array
    {
        try {
            $entries = $this->overviewCacheStore->all();
            $entry = is_array($entries[$cacheKey] ?? null) ? $entries[$cacheKey] : null;
            if (!is_array($entry)) {
                return null;
            }

            $expiresAt = (int) ($entry['expires_at'] ?? 0);
            if ($expiresAt <= time()) {
                return null;
            }

            $payload = $entry['payload'] ?? null;
            if (!is_array($payload)) {
                return null;
            }

            return $payload;
        } catch (\Throwable) {
            return null;
        }
    }

    private function saveOverviewCache(string $cacheKey, array $payload): void
    {
        try {
            $now = time();
            $entries = $this->overviewCacheStore->all();
            if (!is_array($entries)) {
                $entries = [];
            }

            $entries[$cacheKey] = [
                'expires_at' => $now + self::OVERVIEW_CACHE_TTL_SECONDS,
                'updated_at' => $now,
                'payload' => $payload,
            ];

            $entries = $this->trimOverviewCacheEntries((array) $entries);
            $this->overviewCacheStore->put($entries);
        } catch (\Throwable) {
            // Ignore cache failures to keep data reads stable.
        }
    }

    private function trimOverviewCacheEntries(array $entries): array
    {
        if (count($entries) <= self::OVERVIEW_CACHE_MAX_ENTRIES) {
            return $entries;
        }

        uasort(
            $entries,
            static function (array $left, array $right): int {
                return (int) ($right['updated_at'] ?? 0) <=> (int) ($left['updated_at'] ?? 0);
            }
        );

        return array_slice($entries, 0, self::OVERVIEW_CACHE_MAX_ENTRIES, true);
    }
}
