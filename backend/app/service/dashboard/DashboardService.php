<?php

declare(strict_types=1);

namespace app\service\dashboard;

use app\common\storage\JsonFileStore;
use app\common\storage\RuntimeStorage;
use app\repository\DashboardRepository;
use app\repository\SeoRepository;
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
        private readonly SeoRepository $seoRepository = new SeoRepository()
    ) {
        $this->overviewCacheStore = RuntimeStorage::store(self::OVERVIEW_CACHE_FILE);
    }

    public function traffic(string $range = '7d', ?string $startDate = null, ?string $endDate = null): array
    {
        $this->dashboardRepository->setCustomDateRange($startDate, $endDate);
        $summary = $this->dashboardRepository->trafficSummary($range);

        return [
            'range' => $range,
            'uv' => (int) ($summary['uv'] ?? 0),
            'pv' => (int) ($summary['pv'] ?? 0),
            'bounce_rate' => (float) ($summary['bounce_rate'] ?? 0),
            'series' => $this->dashboardRepository->trafficSeries($range),
            'countries' => $this->dashboardRepository->trafficCountrySummary($range),
            'top_pages' => $this->dashboardRepository->trafficTopPages($range),
        ];
    }

    public function overview(string $range = '7d', ?string $startDate = null, ?string $endDate = null): array
    {
        $cacheKey = $this->buildOverviewCacheKey($range, $startDate, $endDate);
        $cached = $this->loadOverviewCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $overview = [
            'traffic' => $this->traffic($range, $startDate, $endDate),
            'ai' => $this->aiConversations($range, $startDate, $endDate),
            'inquiries' => $this->inquiries($range, $startDate, $endDate),
            'content' => $this->content($range, $startDate, $endDate),
            'jobs' => $this->jobs(),
        ];

        $this->saveOverviewCache($cacheKey, $overview);

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
        $rows = $this->dashboardRepository->inquirySummary($range);
        $summary = [
            'new_count' => 0,
            'quoting_count' => 0,
            'won_count' => 0,
            'closed_count' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $count = (int) ($row['total_count'] ?? 0);
            if ($status === 'new') {
                $summary['new_count'] += $count;
            }
            if ($status === 'quoting') {
                $summary['quoting_count'] += $count;
            }
            if ($status === 'won') {
                $summary['won_count'] += $count;
            }
            if ($status === 'closed') {
                $summary['closed_count'] += $count;
            }
        }

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
        return [
            'pending_translation' => $this->translationRepository->countByStatuses(['pending', 'processing', 'review_required']),
            'pending_seo' => $this->seoRepository->countByStatuses(['pending']),
            'failed_ai_jobs' => $this->translationRepository->countByStatuses(['failed']) + $this->seoRepository->countByStatuses(['failed']),
            'seo_route_count' => $this->seoRepository->countRoutes(),
            'seo_404_count' => $this->seoRepository->count404Logs(),
        ];
    }

    public function content(string $range = '7d', ?string $startDate = null, ?string $endDate = null): array
    {
        $this->dashboardRepository->setCustomDateRange($startDate, $endDate);
        $summary = $this->dashboardRepository->contentUvSummary($range);

        return [
            'range' => $range,
            'product_uv' => (int) ($summary['product_uv'] ?? 0),
            'solution_uv' => (int) ($summary['solution_uv'] ?? 0),
            'news_uv' => (int) ($summary['news_uv'] ?? 0),
            'case_uv' => (int) ($summary['case_uv'] ?? 0),
            'top_products' => $this->dashboardRepository->contentTopPagesByType('product', $range, 5),
            'top_solutions' => $this->dashboardRepository->contentTopPagesByType('solution', $range, 5),
            'top_news' => $this->dashboardRepository->contentTopPagesByType('news', $range, 5),
            'top_cases' => $this->dashboardRepository->contentTopPagesByType('case', $range, 5),
        ];
    }

    private function buildOverviewCacheKey(string $range, ?string $startDate, ?string $endDate): string
    {
        $normalizedRange = strtolower(trim($range));
        $start = $startDate !== null ? trim($startDate) : '';
        $end = $endDate !== null ? trim($endDate) : '';

        return $normalizedRange . '|s:' . $start . '|e:' . $end;
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
