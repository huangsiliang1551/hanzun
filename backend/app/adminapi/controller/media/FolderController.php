<?php

declare(strict_types=1);

namespace app\adminapi\controller\media;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\media\MediaService;

class FolderController extends BaseAdminController
{
    public function __construct(private readonly MediaService $mediaService = new MediaService())
    {
    }

    /** GET /admin/media/folders → 递归目录树 */
    public function index(Request $request): array
    {
        return $this->success($this->mediaService->folderTree());
    }

    /** POST /admin/media/folders → 创建目录 */
    public function store(Request $request): array
    {
        return $this->success($this->mediaService->createFolder([
            'parent_id' => (int) ($request->input('parent_id', 0)),
            'name' => $request->input('name', ''),
        ]), [], 'folder created');
    }

    /** PUT /admin/media/folders/{id} → 重命名目录 */
    public function update(Request $request): array
    {
        return $this->success($this->mediaService->updateFolder(
            (int) $request->routeParam('id'),
            [
                'name' => $request->input('name', ''),
                'parent_id' => (int) ($request->input('parent_id', 0)),
                'sort_order' => (int) ($request->input('sort_order', 0)),
            ]
        ), [], 'folder updated');
    }

    /** DELETE /admin/media/folders/{id} → 删除空目录 */
    public function delete(Request $request): array
    {
        return $this->success($this->mediaService->deleteFolder(
            (int) $request->routeParam('id')
        ), [], 'folder deleted');
    }

    /** PATCH /admin/media/folders/{id}/sort → 调整目录排序 */
    public function sort(Request $request): array
    {
        return $this->success($this->mediaService->sortFolder(
            (int) $request->routeParam('id'),
            (int) ($request->input('sort_order', 0))
        ), [], 'folder sorted');
    }
}
