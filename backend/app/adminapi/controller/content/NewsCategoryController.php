<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\NewsService;

class NewsCategoryController extends BaseAdminController
{
    public function __construct(private readonly NewsService $newsService = new NewsService())
    {
    }

    public function tree(): array
    {
        return $this->success($this->newsService->categoryTree());
    }

    public function store(Request $request): array
    {
        return $this->success($this->newsService->createCategory([
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]), [], 'create success');
    }

    public function update(Request $request): array
    {
        return $this->success($this->newsService->updateCategory((int) $request->routeParam('id'), [
            'parent_id' => $request->input('parent_id'),
            'name_zh' => $request->input('name_zh'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]), [], 'update success');
    }

    public function delete(Request $request): array
    {
        return $this->success(
            $this->newsService->deleteCategory((int) $request->routeParam('id')),
            [],
            'delete success'
        );
    }
}
