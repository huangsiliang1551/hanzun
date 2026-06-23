<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\CertificateService;
use app\service\system\SiteBuildService;

class CertificateController extends BaseAdminController
{
    public function __construct(
        private readonly CertificateService $certificateService = new CertificateService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function index(): array
    {
        return $this->success($this->certificateService->list());
    }

    public function show(Request $request): array
    {
        return $this->success($this->certificateService->detail((int) $request->routeParam('id')));
    }

    public function store(Request $request): array
    {
        return $this->success($this->certificateService->create([
            'name_zh' => $request->input('name_zh'),
            'issuer_zh' => $request->input('issuer_zh'),
            'certificate_no' => $request->input('certificate_no'),
            'certificate_type' => $request->input('certificate_type'),
            'description_zh' => $request->input('description_zh'),
            'image_asset_id' => $request->input('image_asset_id'),
            'publish_status' => $request->input('publish_status'),
            'translation_status' => $request->input('translation_status'),
            'seo_status' => $request->input('seo_status'),
            'is_home_featured' => $request->input('is_home_featured'),
            'manual_sort' => $request->input('manual_sort'),
        ]), [], 'create success');
    }

    public function update(Request $request): array
    {
        return $this->success($this->certificateService->update((int) $request->routeParam('id'), [
            'name_zh' => $request->input('name_zh'),
            'issuer_zh' => $request->input('issuer_zh'),
            'certificate_no' => $request->input('certificate_no'),
            'certificate_type' => $request->input('certificate_type'),
            'description_zh' => $request->input('description_zh'),
            'image_asset_id' => $request->input('image_asset_id'),
            'publish_status' => $request->input('publish_status'),
            'translation_status' => $request->input('translation_status'),
            'seo_status' => $request->input('seo_status'),
            'is_home_featured' => $request->input('is_home_featured'),
            'manual_sort' => $request->input('manual_sort'),
        ]), [], 'update success');
    }

    public function delete(Request $request): array
    {
        $result = $this->certificateService->remove((int) $request->routeParam('id'));
        $job = $this->siteBuildService->queueFullBuild('delete_certificate', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success(
            $result,
            [],
            'delete success'
        );
    }

    public function publish(Request $request): array
    {
        $result = $this->certificateService->publish((int) $request->routeParam('id'), (string) $request->input('publish_status', 'draft'));
        if ((string) ($result['publish_status'] ?? '') === 'published') {
            $job = $this->siteBuildService->queueIncrementalBuild('publish_certificate', 'certificate', (int) ($result['id'] ?? 0), [], current_user());
        } else {
            $job = $this->siteBuildService->queueFullBuild('publish_certificate_status_changed', [], current_user());
        }
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result, [], 'publish status updated');
    }

    public function batchPublish(Request $request): array
    {
        $result = $this->certificateService->batchPublish(
            array_map('intval', (array) $request->input('ids', [])),
            (string) $request->input('publish_status', 'published')
        );
        $job = $this->siteBuildService->queueFullBuild('batch_publish_certificate', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result, [], 'batch publish updated');
    }

    public function batchSort(Request $request): array
    {
        $result = $this->certificateService->batchSort(
            (array) $request->input('items', [])
        );
        $job = $this->siteBuildService->queueFullBuild('batch_sort_certificate', [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result, [], 'batch sort updated');
    }
}
