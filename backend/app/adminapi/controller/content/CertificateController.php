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
        $result = $this->certificateService->create([
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
            'manual_sort' => $request->input('manual_sort')
        ]);
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('create_certificate', 'certificate', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '证书已创建');
    }

    public function update(Request $request): array
    {
        $result = $this->certificateService->update((int) $request->routeParam('id'), [
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
            'manual_sort' => $request->input('manual_sort')
        ]);
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('update_certificate', 'certificate', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '证书已更新');
    }

    public function delete(Request $request): array
    {
        $result = $this->certificateService->remove((int) $request->routeParam('id'));
        $job = null;
        if (in_array((string) ($result['publish_status'] ?? ''), ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('delete_certificate', 'certificate', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '证书已删除');
    }

    public function publish(Request $request): array
    {
        $result = $this->certificateService->publish((int) $request->routeParam('id'), (string) $request->input('publish_status', 'draft'));
        $targetStatus = (string) ($result['publish_status'] ?? '');
        $job = null;
        if ($targetStatus === 'published') {
            $job = $this->siteBuildService->queueIncrementalBuild('publish_certificate', 'certificate', (int) ($result['id'] ?? 0), [], current_user());
        } elseif ($targetStatus === 'offline') {
            $job = $this->siteBuildService->queueIncrementalBuild('publish_certificate_status_changed', 'certificate', (int) ($result['id'] ?? 0), [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '发布状态已更新');
    }

    public function batchPublish(Request $request): array
    {
        $result = $this->certificateService->batchPublish(
            array_map('intval', (array) $request->input('ids', [])),
            (string) $request->input('publish_status', 'published')
        );
        $targetStatus = (string) $request->input('publish_status', 'published');
        $job = null;
        if (in_array($targetStatus, ['published', 'offline'], true)) {
            $job = $this->siteBuildService->queueIncrementalBuild('batch_publish_certificate', 'certificate', 0, [], current_user());
        }
        $result['generation_job'] = is_array($job['job'] ?? null) ? $job['job'] : null;

        return $this->success($result, [], '批量发布状态已更新');
    }

    public function batchSort(Request $request): array
    {
        $result = $this->certificateService->batchSort(
            (array) $request->input('items', [])
        );
        $job = $this->siteBuildService->queueIncrementalBuild('batch_sort_certificate', 'certificate', 0, [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result, [], '批量排序已更新');
    }

    public function batchDelete(Request $request): array
    {
        $ids = array_map('intval', (array) $request->input('ids', []));
        $result = $this->certificateService->batchRemove($ids);
        $job = $this->siteBuildService->queueIncrementalBuild('batch_delete_certificate', 'certificate', 0, [], current_user());
        $result['generation_job'] = $job['job'] ?? null;

        return $this->success($result, [], '批量删除已完成');
    }
}
