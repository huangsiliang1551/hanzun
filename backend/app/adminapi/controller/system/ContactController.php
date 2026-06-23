<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\system\ContactService;
use app\service\system\SiteBuildService;

class ContactController extends BaseAdminController
{
    public function __construct(
        private readonly ContactService $contactService = new ContactService(),
        private readonly SiteBuildService $siteBuildService = new SiteBuildService()
    )
    {
    }

    public function index(): array
    {
        return $this->success($this->contactService->items());
    }

    public function fieldTypes(): array
    {
        return $this->success(['items' => $this->contactService->fieldTypes()]);
    }

    public function storeFieldType(Request $request): array
    {
        $result = $this->contactService->createFieldType([
            'field_key' => $request->input('field_key'),
            'name_zh' => $request->input('name_zh'),
            'icon' => $request->input('icon'),
            'validation_rule' => $request->input('validation_rule'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $this->siteBuildService->queueFullBuild('contact_field_type_created', [], current_user());

        return $this->success($result, [], 'create success');
    }

    public function updateFieldType(Request $request): array
    {
        $result = $this->contactService->updateFieldType((int) $request->routeParam('id'), [
            'field_key' => $request->input('field_key'),
            'name_zh' => $request->input('name_zh'),
            'icon' => $request->input('icon'),
            'validation_rule' => $request->input('validation_rule'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $this->siteBuildService->queueFullBuild('contact_field_type_updated', [], current_user());

        return $this->success($result, [], 'update success');
    }

    public function deleteFieldType(Request $request): array
    {
        $result = $this->contactService->deleteFieldType((int) $request->routeParam('id'));
        $this->siteBuildService->queueFullBuild('contact_field_type_deleted', [], current_user());

        return $this->success($result, [], 'delete success');
    }

    public function show(Request $request): array
    {
        return $this->success($this->contactService->detail((int) $request->routeParam('id')));
    }

    public function store(Request $request): array
    {
        $result = $this->contactService->create([
            'field_type_id' => $request->input('field_type_id'),
            'label_zh' => $request->input('label_zh'),
            'value' => $request->input('value'),
            'description_zh' => $request->input('description_zh'),
            'display_scope' => $request->input('display_scope'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $this->siteBuildService->queueFullBuild('contact_item_created', [], current_user());

        return $this->success($result, [], 'create success');
    }

    public function update(Request $request): array
    {
        $result = $this->contactService->update((int) $request->routeParam('id'), [
            'field_type_id' => $request->input('field_type_id'),
            'label_zh' => $request->input('label_zh'),
            'value' => $request->input('value'),
            'description_zh' => $request->input('description_zh'),
            'display_scope' => $request->input('display_scope'),
            'sort' => $request->input('sort'),
            'is_enabled' => $request->input('is_enabled'),
        ]);
        $this->siteBuildService->queueFullBuild('contact_item_updated', [], current_user());

        return $this->success($result, [], 'update success');
    }

    public function delete(Request $request): array
    {
        $result = $this->contactService->delete((int) $request->routeParam('id'));
        $this->siteBuildService->queueFullBuild('contact_item_deleted', [], current_user());

        return $this->success($result, [], 'delete success');
    }
}
