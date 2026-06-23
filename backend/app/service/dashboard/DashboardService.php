<?php

declare(strict_types=1);

namespace app\service\dashboard;

use app\repository\DashboardRepository;
use app\repository\SeoRepository;
use app\repository\TranslationRepository;

final class DashboardService
{
    public function __construct(
        private readonly DashboardRepository $dashboardRepository = new DashboardRepository(),
        private readonly TranslationRepository $translationRepository = new TranslationRepository(),
        private readonly SeoRepository $seoRepository = new SeoRepository()
    ) {
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
        return [
            'traffic' => $this->traffic($range, $startDate, $endDate),
            'ai' => $this->aiConversations($range, $startDate, $endDate),
            'inquiries' => $this->inquiries($range, $startDate, $endDate),
            'jobs' => $this->jobs(),
        ];
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


}
