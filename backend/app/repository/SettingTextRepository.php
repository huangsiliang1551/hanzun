<?php

declare(strict_types=1);

namespace app\repository;

final class SettingTextRepository
{
    private const GROUP = 'localized_settings';
    private const KEY = 'texts';

    public function getText(string $scope, string $itemKey, string $languageCode, string $fallback = ''): string
    {
        $scope = $this->normalizeKey($scope);
        $itemKey = trim($itemKey);
        $languageCode = $this->normalizeKey($languageCode);
        if ($scope === '' || $itemKey === '' || $languageCode === '') {
            return $fallback;
        }

        $storage = $this->storage();
        $translations = $storage[$scope][$itemKey] ?? null;
        if (!is_array($translations)) {
            return $fallback;
        }

        $value = trim((string) ($translations[$languageCode] ?? ''));
        if ($value !== '') {
            return $value;
        }

        $zhValue = trim((string) ($translations['zh'] ?? ''));

        return $zhValue !== '' ? $zhValue : $fallback;
    }

    /**
     * @param array<string, string> $translations
     */
    public function upsertTranslations(string $scope, string $itemKey, array $translations): void
    {
        $scope = $this->normalizeKey($scope);
        $itemKey = trim($itemKey);
        if ($scope === '' || $itemKey === '') {
            return;
        }

        $normalized = [];
        foreach ($translations as $languageCode => $value) {
            $code = $this->normalizeKey((string) $languageCode);
            if ($code === '') {
                continue;
            }

            $normalized[$code] = trim((string) $value);
        }

        if ($normalized === []) {
            return;
        }

        $storage = $this->storage();
        if (!isset($storage[$scope]) || !is_array($storage[$scope])) {
            $storage[$scope] = [];
        }

        $storage[$scope][$itemKey] = $normalized;
        $this->write($storage);
    }

    public function deleteItem(string $scope, string $itemKey): void
    {
        $scope = $this->normalizeKey($scope);
        $itemKey = trim($itemKey);
        if ($scope === '' || $itemKey === '') {
            return;
        }

        $storage = $this->storage();
        if (!isset($storage[$scope]) || !is_array($storage[$scope])) {
            return;
        }

        unset($storage[$scope][$itemKey]);
        $this->write($storage);
    }

    public function deleteLanguage(string $languageCode): void
    {
        $languageCode = $this->normalizeKey($languageCode);
        if ($languageCode === '') {
            return;
        }

        $storage = $this->storage();
        foreach ($storage as $scope => $items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $itemKey => $translations) {
                if (!is_array($translations)) {
                    continue;
                }

                unset($translations[$languageCode]);
                if ($translations === []) {
                    unset($storage[$scope][$itemKey]);
                    continue;
                }

                $storage[$scope][$itemKey] = $translations;
            }
        }

        $this->write($storage);
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public function all(): array
    {
        return $this->storage();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function scope(string $scope): array
    {
        $scope = $this->normalizeKey($scope);
        $storage = $this->storage();
        $items = $storage[$scope] ?? [];

        return is_array($items) ? $items : [];
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function storage(): array
    {
        $repository = new SystemSettingRepository();
        $storage = $repository->get(self::GROUP, self::KEY, []);

        return is_array($storage) ? $storage : [];
    }

    /**
     * @param array<string, array<string, array<string, string>>> $storage
     */
    private function write(array $storage): void
    {
        $repository = new SystemSettingRepository();
        $repository->put(self::GROUP, self::KEY, $storage);
    }

    private function normalizeKey(string $value): string
    {
        return strtolower(trim($value));
    }
}
