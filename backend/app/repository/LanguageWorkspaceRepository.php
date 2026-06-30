<?php

declare(strict_types=1);

namespace app\repository;

final class LanguageWorkspaceRepository
{
    public function __construct(
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository()
    ) {
    }

    public function listMeta(): array
    {
        $items = $this->systemSettingRepository->get('language_workspace', 'meta', []);

        return is_array($items) ? $items : [];
    }

    public function getMeta(string $languageCode): array
    {
        $languageCode = strtolower(trim($languageCode));
        if ($languageCode === '') {
            return [];
        }

        $items = $this->listMeta();

        return is_array($items[$languageCode] ?? null) ? $items[$languageCode] : [];
    }

    public function upsertMeta(string $languageCode, array $payload): array
    {
        $languageCode = strtolower(trim($languageCode));
        if ($languageCode === '') {
            return [];
        }

        $items = $this->listMeta();
        $existing = is_array($items[$languageCode] ?? null) ? $items[$languageCode] : [];

        $items[$languageCode] = array_merge($existing, $payload, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $stored = $this->systemSettingRepository->put('language_workspace', 'meta', $items);

        return is_array($stored[$languageCode] ?? null) ? $stored[$languageCode] : [];
    }

    public function removeMeta(string $languageCode): void
    {
        $languageCode = strtolower(trim($languageCode));
        if ($languageCode === '') {
            return;
        }

        $items = $this->listMeta();
        unset($items[$languageCode]);
        $this->systemSettingRepository->put('language_workspace', 'meta', $items);
    }
}
