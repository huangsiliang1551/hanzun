<?php

declare(strict_types=1);

namespace app\adminapi\controller\knowledge;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\knowledge\KnowledgeService;

class KnowledgeController extends BaseAdminController
{
    public function __construct(private readonly KnowledgeService $knowledgeService = new KnowledgeService())
    {
    }

    public function index(Request $request): array
    {
        return $this->success($this->knowledgeService->listDocuments([
            'keyword' => $request->input('keyword'),
            'status' => $request->input('status'),
            'source_type' => $request->input('source_type'),
            'language_code' => $request->input('language_code'),
            'page' => $request->input('page'),
            'page_size' => $request->input('page_size'),
        ]));
    }

    public function show(Request $request): array
    {
        return $this->success($this->knowledgeService->documentDetail((int) $request->routeParam('id')));
    }

    public function store(Request $request): array
    {
        $source = trim((string) $request->input('source', 'manual'));
        if ($source === 'upload') {
            return $this->success($this->knowledgeService->createFromUpload([
                'title' => $request->input('title'),
                'file_path' => $request->input('file_path'),
                'language_code' => $request->input('language_code'),
            ]), [], '知识库文档已创建');
        }

        return $this->success($this->knowledgeService->createManual([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'language_code' => $request->input('language_code'),
            'tags' => $request->input('tags'),
        ]), [], '知识库文档已创建');
    }

    public function update(Request $request): array
    {
        return $this->success($this->knowledgeService->updateDocument((int) $request->routeParam('id'), [
            'title' => $request->input('title'),
            'language_code' => $request->input('language_code'),
            'status' => $request->input('status'),
            'tags' => $request->input('tags'),
        ]), [], '知识库文档已更新');
    }

    public function delete(Request $request): array
    {
        return $this->success($this->knowledgeService->deleteDocument((int) $request->routeParam('id')), [], '知识库文档已删除');
    }

    public function reindex(Request $request): array
    {
        return $this->success($this->knowledgeService->reindexDocument(
            (int) $request->routeParam('id'),
            is_string($request->input('content')) ? $request->input('content') : null
        ), [], '知识库文档已重建索引');
    }

    public function syncCms(): array
    {
        return $this->success($this->knowledgeService->syncCms(), [], 'CMS 内容已同步到知识库');
    }

    public function reindexAll(): array
    {
        return $this->success($this->knowledgeService->reindexAll(), [], '全部知识库文档已重建索引');
    }
}
