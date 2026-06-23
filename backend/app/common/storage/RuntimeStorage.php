<?php

declare(strict_types=1);

namespace app\common\storage;

final class RuntimeStorage
{
    public static function enabled(): bool
    {
        $value = getenv('PREFER_RUNTIME_STORAGE');
        if (!is_string($value)) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function store(string $fileName): JsonFileStore
    {
        return new JsonFileStore(self::storageDir() . DIRECTORY_SEPARATOR . $fileName);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public static function nextId(array $items): int
    {
        $maxId = 0;
        foreach ($items as $item) {
            $maxId = max($maxId, (int) ($item['id'] ?? 0));
        }

        return $maxId + 1;
    }

    private static function storageDir(): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'storage';
    }
}
