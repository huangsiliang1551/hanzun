<?php

declare(strict_types=1);

namespace app\adminapi\controller\media;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\media\MediaService;

class MediaController extends BaseAdminController
{
    public function __construct(private readonly MediaService $mediaService = new MediaService())
    {
    }

    public function index(Request $request): array
    {
        return $this->success($this->mediaService->assets([
            'folder_name' => $request->input('folder_name'),
            'folder_id' => $request->input('folder_id'),
            'file_category' => $request->input('file_category'),
            'status' => $request->input('status'),
            'keyword' => $request->input('keyword'),
            'page' => $request->input('page'),
            'page_size' => $request->input('page_size'),
            'sort_field' => $request->input('sort_field'),
            'sort_order' => $request->input('sort_order'),
        ]));
    }

    public function bootstrap(Request $request): array
    {
        $query = [
            'folder_name' => $request->input('folder_name'),
            'folder_id' => $request->input('folder_id'),
            'file_category' => $request->input('file_category'),
            'status' => $request->input('status'),
            'keyword' => $request->input('keyword'),
            'page' => $request->input('page'),
            'page_size' => $request->input('page_size'),
            'sort_field' => $request->input('sort_field'),
            'sort_order' => $request->input('sort_order'),
        ];

        return $this->success($this->mediaService->bootstrap($query));
    }

    public function picker(Request $request): array
    {
        return $this->success($this->mediaService->picker([
            'folder_name' => $request->input('folder_name'),
            'file_category' => $request->input('file_category'),
            'status' => $request->input('status'),
            'keyword' => $request->input('keyword'),
            'sort_field' => $request->input('sort_field'),
            'sort_order' => $request->input('sort_order'),
        ]));
    }

    public function show(Request $request): array
    {
        return $this->success($this->mediaService->detail((int) $request->routeParam('id')));
    }

    public function store(Request $request): array
    {
        return $this->success($this->mediaService->create([
            'source_path' => $request->input('source_path'),
            'file_name' => $request->input('file_name'),
            'file_content_base64' => $request->input('file_content_base64'),
            'folder_name' => $request->input('folder_name'),
            'alt_text_zh' => $request->input('alt_text_zh'),
            'description_zh' => $request->input('description_zh'),
            'status' => $request->input('status'),
        ]), [], 'create success');
    }

    public function upload(Request $request): array
    {
        if (!$request->hasFile('file')) {
            return $this->error(422, $request->fileErrorMessage('file'));
        }

        $file = $request->file('file');
        $originalFileName = (string) ($file['name'] ?? '');
        $this->mediaService->assertUploadFileAllowed($originalFileName);

        $folderId = (int) $request->input('folder_id', 0);
        $folderName = $request->input('folder_name', 'misc');
        $altText = $request->input('alt_text_zh', '');
        $description = $request->input('description_zh', '');

        $result = $this->mediaService->storeUploadedFile($file, [
            'folder_id' => $folderId,
            'folder_name' => $folderName,
            'alt_text_zh' => $altText,
            'description_zh' => $description,
        ]);

        return $this->success($result, [], 'upload success');
    }

    public function update(Request $request): array
    {
        return $this->success($this->mediaService->update((int) $request->routeParam('id'), [
            'folder_name' => $request->input('folder_name'),
            'alt_text_zh' => $request->input('alt_text_zh'),
            'description_zh' => $request->input('description_zh'),
            'status' => $request->input('status'),
        ]), [], 'update success');
    }

    public function updateStatus(Request $request): array
    {
        return $this->success(
            $this->mediaService->updateStatus((int) $request->routeParam('id'), !empty($request->input('status')) ? 1 : 0),
            [],
            'status updated'
        );
    }

    public function references(Request $request): array
    {
        return $this->success($this->mediaService->references((int) $request->routeParam('id')));
    }

    public function delete(Request $request): array
    {
        return $this->success($this->mediaService->remove((int) $request->routeParam('id')), [], 'delete success');
    }

    public function rename(Request $request): array
    {
        return $this->success($this->mediaService->rename(
            (int) $request->routeParam('id'),
            $request->input('file_name', '')
        ), [], 'rename success');
    }

    public function batchMove(Request $request): array
    {
        return $this->success($this->mediaService->batchMove(
            array_map('intval', (array) ($request->input('ids', []))),
            (int) ($request->input('target_folder_id', 0))
        ), [], 'batch move success');
    }

    public function batchCopy(Request $request): array
    {
        return $this->success($this->mediaService->batchCopy(
            array_map('intval', (array) ($request->input('ids', []))),
            (int) ($request->input('target_folder_id', 0))
        ), [], 'batch copy success');
    }

    public function batchDelete(Request $request): array
    {
        return $this->success($this->mediaService->batchDelete(
            array_map('intval', (array) ($request->input('ids', [])))
        ), [], 'batch delete success');
    }
}
