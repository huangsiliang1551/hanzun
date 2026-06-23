<?php

declare(strict_types=1);

namespace app\adminapi\controller\seo;

use app\adminapi\controller\BaseAdminController;
use app\service\seo\SeoService;

class SeoOverviewController extends BaseAdminController
{
    public function __construct(private readonly SeoService $seoService = new SeoService())
    {
    }

    public function overview(): array
    {
        $jobsData = $this->seoService->jobs();
        $routesData = $this->seoService->routes();
        $fourOhFourData = $this->seoService->fourOhFourLogs();

        $jobSummary = $jobsData['summary'] ?? [];
        $routeSummary = $routesData['summary'] ?? [];

        // Recent failed jobs (top 5)
        $failedJobs = array_values(array_filter(
            $jobsData['items'] ?? [],
            static fn (array $item): bool => (string) ($item['status'] ?? '') === 'failed'
        ));
        $recentFailedJobs = array_slice($failedJobs, 0, 5);

        // Recent unresolved 404 logs (top 10)
        $unresolved404 = array_values(array_filter(
            $fourOhFourData['items'] ?? [],
            static fn (array $item): bool => (string) ($item['fix_status'] ?? 'pending') !== 'resolved'
        ));
        $recent404 = array_slice($unresolved404, 0, 10);

        return $this->success([
            'job_summary' => $jobSummary,
            'route_summary' => $routeSummary,
            'four_oh_four_summary' => $fourOhFourData['summary'] ?? [],
            'recent_failed_jobs' => $recentFailedJobs,
            'recent_404_logs' => $recent404,
        ]);
    }
}
