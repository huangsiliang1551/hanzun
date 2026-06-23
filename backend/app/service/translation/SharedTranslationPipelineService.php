<?php

declare(strict_types=1);

namespace app\service\translation;

use app\repository\LanguageRepository;
use app\repository\SystemSettingRepository;
use app\repository\TranslationRepository;

final class SharedTranslationPipelineService
{
    public function __construct(
        private readonly LanguageRepository $languageRepository = new LanguageRepository(),
        private readonly TranslationRepository $translationRepository = new TranslationRepository(),
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository(),
        private readonly TranslationService $translationService = new TranslationService()
    ) {
    }

    public function syncEntity(string $entityType, int $entityId): void
    {
        if ($entityType === '' || $entityId <= 0) {
            return;
        }

        $config = $this->systemSettingRepository->deepseekConfig();
        if ((int) ($config['translation_enabled'] ?? 1) !== 1) {
            return;
        }

        foreach ($this->translationLanguages() as $languageCode) {
            $this->translationRepository->upsertJob($entityType, $entityId, $languageCode, 'pending', null);
        }

        try {
            $this->translationService->executePendingEntityJobs($entityType, $entityId);
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<int, int> $entityIds
     */
    public function syncEntities(string $entityType, array $entityIds): void
    {
        foreach (array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $entityIds))) as $entityId) {
            if ($entityId > 0) {
                $this->syncEntity($entityType, $entityId);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function translationLanguages(): array
    {
        $languages = [];
        foreach ($this->languageRepository->list() as $language) {
            if ((int) ($language['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $code = trim((string) ($language['code'] ?? ''));
            if ($code !== '' && $code !== 'zh') {
                $languages[] = $code;
            }
        }

        return array_values(array_unique($languages));
    }
}
