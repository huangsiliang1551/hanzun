<?php

declare(strict_types=1);

namespace app\adminapi\controller\homepage;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\homepage\HomepageService;
use app\service\system\SiteBuildService;

class HomepageController extends BaseAdminController
{
    public function __construct(
        private readonly HomepageService $homepageService = new HomepageService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function sections(): array
    {
        return $this->success($this->homepageService->list());
    }

    public function bootstrap(): array
    {
        return $this->success($this->homepageService->bootstrap());
    }

    public function store(Request $request): array
    {
        return $this->success($this->homepageService->createSection([
            'section_key' => $request->input('section_key'),
            'section_type' => $request->input('section_type'),
            'title_zh' => $request->input('title_zh'),
            'subtitle_zh' => $request->input('subtitle_zh'),
            'fetch_mode' => $request->input('fetch_mode'),
            'extra_config' => $request->input('extra_config'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]), [], '首页版块已创建');
    }

    public function sort(Request $request): array
    {
        return $this->success($this->homepageService->sortSections(
            (array) $request->input('sections', [])
        ));
    }

    public function show(Request $request): array
    {
        return $this->success($this->homepageService->sectionDetail(
            (int) $request->routeParam('id')
        ));
    }

    public function items(Request $request): array
    {
        return $this->success($this->homepageService->sectionItems(
            (int) $request->routeParam('id')
        ));
    }

    public function update(Request $request): array
    {
        return $this->success($this->homepageService->updateSection(
            (int) $request->routeParam('id'),
            [
                'section_key' => $request->input('section_key'),
                'section_type' => $request->input('section_type'),
                'title_zh' => $request->input('title_zh'),
                'subtitle_zh' => $request->input('subtitle_zh'),
                'fetch_mode' => $request->input('fetch_mode'),
                'extra_config' => $request->input('extra_config'),
                'sort' => $request->input('sort'),
                'is_enabled' => $request->input('is_enabled'),
            ]
        ));
    }

    public function updateItems(Request $request): array
    {
        return $this->success($this->homepageService->updateSectionItems(
            (int) $request->routeParam('id'),
            (array) $request->input('items', [])
        ));
    }

    public function updateStatus(Request $request): array
    {
        return $this->success($this->homepageService->updateSectionStatus(
            (int) $request->routeParam('id'),
            (int) $request->input('is_enabled')
        ));
    }

    public function preview(): array
    {
        return $this->success($this->homepageService->previewPayload());
    }

    public function workflow(): array
    {
        return $this->success($this->homepageService->workflow());
    }

    public function updateFeaturedItem(Request $request): array
    {
        $data = [
            'is_home_featured' => $request->input('is_home_featured'),
            'manual_sort' => $request->input('manual_sort'),
            'source_type' => (string) $request->routeParam('source_type'),
        ];
        return $this->success($this->homepageService->updateFeaturedItem(
            (string) $request->routeParam('source_type'),
            (int) $request->routeParam('id'),
            $data
        ));
    }

    public function publish(Request $request): array
    {
        $result = $this->homepageService->publish(current_user() ?? []);
        $job = $this->siteBuildService->queueIncrementalBuild('publish_homepage', 'homepage', 0, [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result);
    }

    public function restoreLive(Request $request): array
    {
        $result = $this->homepageService->restoreLive(current_user() ?? []);
        $job = $this->siteBuildService->queueIncrementalBuild('restore_live_homepage', 'homepage', 0, [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result);
    }
}
