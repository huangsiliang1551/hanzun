<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\NewsService;
use app\service\system\SiteBuildService;

class NewsCategoryController extends BaseAdminController
{
    public function __construct(
        private readonly NewsService $newsService = new NewsService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function tree(): array
    {
        return $this->success($this->newsService->categoryTree());
    }

    public function store(Request $request): array
    {
        $result = $this->newsService->createCategory([
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $job = $this->siteBuildService->queueFullBuild('create_news_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '新闻分类已创建');
    }

    public function update(Request $request): array
    {
        $result = $this->newsService->updateCategory((int) $request->routeParam('id'), [
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $job = $this->siteBuildService->queueFullBuild('update_news_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '新闻分类已更新');
    }

    public function delete(Request $request): array
    {
        $result = $this->newsService->deleteCategory((int) $request->routeParam('id'));
        $job = $this->siteBuildService->queueFullBuild('delete_news_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '新闻分类已删除');
    }
}
