<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\CaseService;
use app\service\system\SiteBuildService;

class CaseCategoryController extends BaseAdminController
{
    public function __construct(
        private readonly CaseService $caseService = new CaseService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function tree(): array
    {
        return $this->success($this->caseService->categoryTree());
    }

    public function store(Request $request): array
    {
        $result = $this->caseService->createCategory([
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $job = $this->siteBuildService->queueFullBuild('create_case_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '案例分类已创建');
    }

    public function update(Request $request): array
    {
        $result = $this->caseService->updateCategory((int) $request->routeParam('id'), [
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $job = $this->siteBuildService->queueFullBuild('update_case_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '案例分类已更新');
    }

    public function delete(Request $request): array
    {
        $result = $this->caseService->deleteCategory((int) $request->routeParam('id'));
        $job = $this->siteBuildService->queueFullBuild('delete_case_category', [], current_user());
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '案例分类已删除');
    }
}
