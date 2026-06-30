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

        $languages = $this->translationLanguages();
        if ($languages !== []) {
            $this->translationRepository->upsertEntityJob($entityType, $entityId, $languages, '', 'pending');
        }

        if (PHP_SAPI === 'cli') {
            try {
                $this->translationService->executePendingEntityJobs($entityType, $entityId);
            } catch (\Throwable) {
            }

            return;
        }

        // Create jobs here and execute asynchronously to keep admin request latency low.
        // This avoids blocking form save APIs on slow translation providers.
        $this->dispatchAsyncJobs($entityType, $entityId);
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

    private function dispatchAsyncJobs(string $entityType, int $entityId): void
    {
        $scriptPath = dirname(__DIR__, 3) . '/tools/process-entity-jobs.php';
        if (!is_file($scriptPath)) {
            return;
        }

        $phpBinary = trim((string) (defined('PHP_BINARY') ? PHP_BINARY : 'php'));
        $phpBinaryBase = basename((string) $phpBinary);
        if ($phpBinary === '' || str_contains($phpBinaryBase, 'php-cgi') || str_starts_with($phpBinaryBase, 'php-fpm') || !is_file($phpBinary)) {
            $phpBinary = 'php';
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $command = sprintf(
                'start /B "" "%s" "%s" "%s" %d > NUL 2>&1',
                $phpBinary,
                $scriptPath,
                $entityType,
                $entityId
            );
            $handle = @popen($command, 'r');
            if (is_resource($handle)) {
                pclose($handle);
            }
            return;
        }

        $command = sprintf(
            'nohup "%s" "%s" "%s" %d > /dev/null 2>&1 &',
            $phpBinary,
            $scriptPath,
            $entityType,
            $entityId
        );
        @popen($command, 'r');
    }
}
