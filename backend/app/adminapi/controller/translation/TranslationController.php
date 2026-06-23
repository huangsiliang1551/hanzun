<?php

declare(strict_types=1);

namespace app\adminapi\controller\translation;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\translation\TranslationService;

class TranslationController extends BaseAdminController
{
    public function __construct(private readonly TranslationService $translationService = new TranslationService())
    {
    }

    public function jobs(): array
    {
        return $this->success($this->translationService->jobs());
    }

    public function retry(Request $request): array
    {
        return $this->success(
            $this->translationService->retry((int) $request->routeParam('id')),
            [],
            'retry queued'
        );
    }

    public function approve(Request $request): array
    {
        return $this->success(
            $this->translationService->approve((int) $request->routeParam('id')),
            [],
            'translation approved'
        );
    }

    public function update(Request $request): array
    {
        return $this->success(
            $this->translationService->update((int) $request->routeParam('id'), [
                'translated_fields' => $request->input('translated_fields'),
            ]),
            [],
            'translation updated'
        );
    }

    public function entityJobs(Request $request): array
    {
        $entityType = (string) $request->routeParam('entity_type');
        $entityId = (int) $request->routeParam('entity_id');

        return $this->success($this->translationService->entityJobs($entityType, $entityId));
    }

    public function trigger(Request $request): array
    {
        $entityType = (string) $request->routeParam('entity_type');
        $entityId = (int) $request->routeParam('entity_id');
        $this->translationService->triggerEntity($entityType, $entityId);

        return $this->success([], [], 'translation triggered');
    }
}
