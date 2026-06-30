<?php

declare(strict_types=1);

namespace app\adminapi\controller\seo;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\seo\SeoService;

class SeoController extends BaseAdminController
{
    public function __construct(private readonly SeoService $seoService = new SeoService())
    {
    }

    public function aiGenerate(Request $request): array
    {
        $content = (string) $request->input('content', '');
        $entityName = (string) $request->input('entity_name', '产品');
        $lang = (string) $request->input('lang', 'zh');

        if (mb_strlen($content) < 20) {
            return $this->error(400, '内容过短，至少需要 20 个字符');
        }

        $result = $this->seoService->aiGenerateSeo([
            'content' => $content,
            'entity_name' => $entityName,
            'lang' => $lang,
        ]);

        return $this->success($result);
    }

    public function aiPolish(Request $request): array
    {
        $content = (string) $request->input('content', '');
        $fieldType = (string) $request->input('field_type', 'summary_zh');

        $plainText = trim(strip_tags($content));
        if (mb_strlen($plainText) < 10) {
            return $this->error(400, '内容过短，请填写更多内容');
        }

        $result = $this->seoService->aiPolishContent([
            'content' => $content,
            'field_type' => $fieldType,
        ]);

        return $this->success($result);
    }

    public function jobs(): array
    {
        return $this->success($this->seoService->jobs());
    }

    public function routes(): array
    {
        return $this->success($this->seoService->routes());
    }

    public function updateRoute(Request $request): array
    {
        return $this->success(
            $this->seoService->updateRoute((int) $request->routeParam('id'), [
                'route_path' => $request->input('route_path'),
                'slug' => $request->input('slug'),
                'seo_title' => $request->input('seo_title'),
                'seo_keywords' => $request->input('seo_keywords'),
                'seo_description' => $request->input('seo_description'),
                'canonical_url' => $request->input('canonical_url'),
                'index_status' => $request->input('index_status'),
            ]),
            [],
            'SEO 路由已更新'
        );
    }

    public function fourOhFourLogs(): array
    {
        return $this->success($this->seoService->fourOhFourLogs());
    }

    public function update404Log(Request $request): array
    {
        return $this->success(
            $this->seoService->update404Log((int) $request->routeParam('id'), [
                'fix_status' => $request->input('fix_status'),
                'suggested_route' => $request->input('suggested_route'),
                'note' => $request->input('note'),
            ]),
            [],
            '404 记录已更新'
        );
    }

    public function siteFiles(): array
    {
        return $this->success($this->seoService->siteFiles());
    }

    public function updateRobots(Request $request): array
    {
        return $this->success(
            $this->seoService->updateRobots((string) $request->input('robots_content', '')),
            [],
            'robots.txt 已更新'
        );
    }

    public function rebuildSitemap(): array
    {
        return $this->success($this->seoService->rebuildSitemap(), [], '站点地图已重建');
    }

    public function generate(Request $request): array
    {
        return $this->success(
            $this->seoService->generate([
                'entity_type' => $request->input('entity_type'),
                'entity_id' => $request->input('entity_id'),
                'language_codes' => $request->input('language_codes'),
            ]),
            [],
            'SEO 已生成'
        );
    }

    public function retry(Request $request): array
    {
        return $this->success(
            $this->seoService->retry((int) $request->routeParam('id')),
            [],
            '已提交重试'
        );
    }
}
