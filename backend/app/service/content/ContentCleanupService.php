<?php

declare(strict_types=1);

namespace app\service\content;

use app\repository\SeoRepository;
use app\repository\TranslationRepository;
use app\service\seo\PageSeoSyncService;

final class ContentCleanupService
{
    public function __construct(
        private readonly TranslationRepository $translationRepository = new TranslationRepository(),
        private readonly SeoRepository $seoRepository = new SeoRepository(),
        private readonly ContentEntityBridge $contentEntityBridge = new ContentEntityBridge(),
        private readonly PageSeoSyncService $pageSeoSyncService = new PageSeoSyncService()
    ) {
    }

    /**
     * @param array<string, mixed>|null $record
     */
    public function purgeEntity(string $entityType, int $entityId, ?array $record = null): void
    {
        if ($entityType === '' || $entityId <= 0) {
            return;
        }

        $this->translationRepository->deleteByEntity($entityType, $entityId);
        $this->contentEntityBridge->deleteTranslations($entityType, $entityId);
        $this->seoRepository->deleteByEntity($entityType, $entityId);

        $syncType = $entityType;
        if ($entityType === 'article') {
            $contentType = strtolower(trim((string) ($record['content_type'] ?? '')));
            if (in_array($contentType, ['news', 'case'], true)) {
                $syncType = $contentType;
            }
        }

        $this->pageSeoSyncService->syncByEntityType($syncType);
    }
}
