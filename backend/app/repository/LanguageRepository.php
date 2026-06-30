<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class LanguageRepository
{
    public function list(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->query(
                'SELECT id, code, name, is_default, is_enabled, sort
                 FROM languages
                 ORDER BY sort DESC, id ASC'
            );
            $rows = $statement->fetchAll();

            return is_array($rows) ? $rows : [];
        }

        $items = $this->readRuntimeItems();

        return $items !== [] ? $items : $this->defaultItems();
    }

    public function replaceAll(array $items): array
    {
        $normalized = array_values(array_map([$this, 'normalizeItem'], $items));
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            foreach ($normalized as $item) {
                $statement = $pdo->prepare(
                    'INSERT INTO languages (code, name, is_default, is_enabled, sort)
                     VALUES (:code, :name, :is_default, :is_enabled, :sort)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), is_default = VALUES(is_default), is_enabled = VALUES(is_enabled), sort = VALUES(sort)'
                );
                $statement->execute([
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'is_default' => $item['is_default'],
                    'is_enabled' => $item['is_enabled'],
                    'sort' => $item['sort'],
                ]);
            }

            return $this->list();
        }

        $resolved = $normalized !== [] ? $normalized : $this->defaultItems();
        $this->writeRuntimeItems($resolved);

        return $resolved;
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage($this->storagePath());
    }

    private function storagePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/languages.json';
    }

    private function readRuntimeItems(): array
    {
        $path = $this->storagePath();
        if (!is_file($path)) {
            return [];
        }

        $raw = (string) file_get_contents($path);
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = $this->normalizeItem($item);
        }

        usort($items, static function (array $left, array $right): int {
            return ((int) ($right['sort'] ?? 0) <=> (int) ($left['sort'] ?? 0))
                ?: ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });

        return $items;
    }

    private function writeRuntimeItems(array $items): void
    {
        $path = $this->storagePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function normalizeItem(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'code' => trim((string) ($item['code'] ?? '')),
            'name' => trim((string) ($item['name'] ?? '')),
            'is_default' => !empty($item['is_default']) ? 1 : 0,
            'is_enabled' => array_key_exists('is_enabled', $item) ? (!empty($item['is_enabled']) ? 1 : 0) : 1,
            'sort' => (int) ($item['sort'] ?? 0),
        ];
    }

    private function defaultItems(): array
    {
        return [
            [
                'id' => 1,
                'code' => 'zh',
                'name' => 'Chinese',
                'is_default' => 1,
                'is_enabled' => 1,
                'sort' => 100,
            ],
            [
                'id' => 2,
                'code' => 'en',
                'name' => 'English',
                'is_default' => 0,
                'is_enabled' => 1,
                'sort' => 90,
            ],
        ];
    }
}
