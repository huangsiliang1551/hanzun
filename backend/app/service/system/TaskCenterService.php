<?php

declare(strict_types=1);

namespace app\service\system;

use app\service\seo\SeoService;
use app\service\system\SiteBuildService;
use app\service\translation\TranslationService;

final class TaskCenterService
{
    public function __construct(
        private readonly TranslationService $translationService = new TranslationService(),
        private readonly SeoService $seoService = new SeoService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    ) {
    }

    public function overview(): array
    {
        return [
            'translation' => $this->safeLoad(
                fn (): array => $this->translationService->jobs(),
                ['items' => [], 'summary' => [], 'status_options' => []]
            ),
            'seoJobs' => $this->safeLoad(
                fn (): array => $this->seoService->jobs(),
                ['items' => [], 'summary' => [], 'status_options' => []]
            ),
            'seoRoutes' => $this->safeLoad(
                fn (): array => $this->seoService->routes(),
                ['items' => [], 'summary' => []]
            ),
            'seo404Logs' => $this->safeLoad(
                fn (): array => $this->seoService->fourOhFourLogs(),
                ['items' => [], 'summary' => []]
            ),
            'siteFiles' => $this->safeLoad(
                fn (): array => $this->seoService->siteFiles(),
                [
                    'robots_content' => '',
                    'robots_updated_at' => null,
                    'sitemap_last_generated_at' => null,
                    'sitemap_route_count' => 0,
                    'sitemap_index_count' => 0,
                    'sitemap_noindex_count' => 0,
                    'pending_404_count' => 0,
                    'home_chain_status' => 'unknown',
                ]
            ),
            'siteBuild' => $this->safeLoad(
                fn (): array => $this->siteBuildService->jobs(),
                ['items' => [], 'summary' => [], 'current' => null]
            ),
        ];
    }

    private function safeLoad(callable $loader, array $fallback): array
    {
        try {
            $payload = $loader();
            return is_array($payload) ? $payload : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
