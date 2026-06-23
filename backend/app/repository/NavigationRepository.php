<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class NavigationRepository
{
    public function menus(): array
    {
        $runtimeMenus = $this->readRuntimeMenus();
        if ($this->preferRuntimeStorage()) {
            return $this->sortMenus($runtimeMenus);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->query(
                'SELECT id, name_zh, menu_key, menu_position, sort, is_enabled
                 FROM navigation_menus
                 ORDER BY sort DESC, id ASC'
            );
            $menus = $statement->fetchAll();
            if (!is_array($menus)) {
                return $this->sortMenus($runtimeMenus);
            }

            foreach ($menus as &$menu) {
                $menu = $this->normalizeMenu(array_merge($menu, [
                    'items' => $this->menuItems((int) ($menu['id'] ?? 0)),
                ]));
            }
            unset($menu);

            return $this->sortMenus($this->mergeRuntimeMenus($menus, $runtimeMenus));
        }

        return $this->sortMenus($runtimeMenus);
    }

    public function findMenu(int $id): ?array
    {
        foreach ($this->menus() as $menu) {
            if ((int) ($menu['id'] ?? 0) === $id) {
                return $menu;
            }
        }

        return null;
    }

    public function menuKeyExists(string $menuKey, int $excludeId = 0): bool
    {
        $menuKey = trim($menuKey);
        if ($menuKey === '') {
            return false;
        }

        if ($this->preferRuntimeStorage()) {
            return $this->runtimeMenuKeyExists($menuKey, $excludeId);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT id FROM navigation_menus WHERE menu_key = :menu_key AND id <> :exclude_id LIMIT 1'
            );
            $statement->execute([
                'menu_key' => $menuKey,
                'exclude_id' => $excludeId,
            ]);
            if ((bool) $statement->fetchColumn()) {
                return true;
            }
        }

        return $this->runtimeMenuKeyExists($menuKey, $excludeId);
    }

    public function createMenu(array $payload): array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->createRuntimeMenu($payload);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO navigation_menus (name_zh, menu_key, menu_position, sort, is_enabled)
                 VALUES (:name_zh, :menu_key, :menu_position, :sort, :is_enabled)'
            );
            $statement->execute([
                'name_zh' => (string) ($payload['name_zh'] ?? ''),
                'menu_key' => (string) ($payload['menu_key'] ?? ''),
                'menu_position' => (string) ($payload['menu_position'] ?? 'header'),
                'sort' => (int) ($payload['sort'] ?? 0),
                'is_enabled' => !empty($payload['is_enabled']) ? 1 : 0,
            ]);

            return $this->findMenu((int) $pdo->lastInsertId()) ?? $this->normalizeMenu($payload);
        }

        return $this->createRuntimeMenu($payload);
    }

    public function updateMenu(int $id, array $payload): ?array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->updateRuntimeMenu($id, $payload);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            if ($this->findDatabaseMenu($id) === null) {
                return $this->updateRuntimeMenu($id, $payload);
            }

            $fields = [];
            $params = ['id' => $id];
            foreach (['name_zh', 'menu_key', 'menu_position', 'sort', 'is_enabled'] as $field) {
                if (!array_key_exists($field, $payload)) {
                    continue;
                }

                $fields[] = "{$field} = :{$field}";
                $params[$field] = in_array($field, ['sort', 'is_enabled'], true)
                    ? (int) $payload[$field]
                    : (string) $payload[$field];
            }

            if ($fields === []) {
                return $this->findMenu($id);
            }

            $statement = $pdo->prepare('UPDATE navigation_menus SET ' . implode(', ', $fields) . ' WHERE id = :id');
            $statement->execute($params);

            return $this->findMenu($id);
        }

        return $this->updateRuntimeMenu($id, $payload);
    }

    public function replaceItems(int $menuId, array $items): ?array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->replaceRuntimeItems($menuId, $items);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            if ($this->findDatabaseMenu($menuId) === null) {
                return $this->replaceRuntimeItems($menuId, $items);
            }

            $pdo->beginTransaction();
            try {
                $deleteStatement = $pdo->prepare('DELETE FROM navigation_items WHERE menu_id = :menu_id');
                $deleteStatement->execute(['menu_id' => $menuId]);

                $insertStatement = $pdo->prepare(
                    'INSERT INTO navigation_items (menu_id, parent_id, name_zh, code, route_key, item_type, link_type, linked_entity_type, linked_entity_id, root_category_id, max_depth, include_children, display_mode, url, open_in_new_tab, sort, is_enabled)
                     VALUES (:menu_id, :parent_id, :name_zh, :code, :route_key, :item_type, :link_type, :linked_entity_type, :linked_entity_id, :root_category_id, :max_depth, :include_children, :display_mode, :url, :open_in_new_tab, :sort, :is_enabled)'
                );

                foreach ($items as $item) {
                    $normalized = $this->normalizeItem(array_merge($item, ['menu_id' => $menuId]));
                    $insertStatement->execute([
                        'menu_id' => $menuId,
                        'parent_id' => (int) ($normalized['parent_id'] ?? 0),
                        'name_zh' => (string) ($normalized['name_zh'] ?? ''),
                        'code' => (string) ($normalized['code'] ?? ''),
                        'route_key' => (string) ($normalized['route_key'] ?? ''),
                        'item_type' => (string) ($normalized['item_type'] ?? 'manual_url'),
                        'link_type' => (string) ($normalized['link_type'] ?? 'manual_url'),
                        'linked_entity_type' => $normalized['linked_entity_type'] !== '' ? $normalized['linked_entity_type'] : null,
                        'linked_entity_id' => $normalized['linked_entity_id'],
                        'root_category_id' => $normalized['root_category_id'],
                        'max_depth' => (int) ($normalized['max_depth'] ?? 1),
                        'include_children' => !empty($normalized['include_children']) ? 1 : 0,
                        'display_mode' => (string) ($normalized['display_mode'] ?? 'plain'),
                        'url' => (string) ($normalized['url'] ?? ''),
                        'open_in_new_tab' => !empty($normalized['open_in_new_tab']) ? 1 : 0,
                        'sort' => (int) ($normalized['sort'] ?? 0),
                        'is_enabled' => !empty($normalized['is_enabled']) ? 1 : 0,
                    ]);
                }

                $pdo->commit();
            } catch (\Throwable $exception) {
                $pdo->rollBack();
                throw $exception;
            }

            return $this->findMenu($menuId);
        }

        return $this->replaceRuntimeItems($menuId, $items);
    }

    public function deleteMenu(int $id): ?array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->deleteRuntimeMenu($id);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $menu = $this->findMenu($id);
            if ($menu === null) {
                return $this->deleteRuntimeMenu($id);
            }

            $deleteItemsStatement = $pdo->prepare('DELETE FROM navigation_items WHERE menu_id = :menu_id');
            $deleteItemsStatement->execute(['menu_id' => $id]);

            $deleteMenuStatement = $pdo->prepare('DELETE FROM navigation_menus WHERE id = :id');
            $deleteMenuStatement->execute(['id' => $id]);

            return $menu;
        }

        return $this->deleteRuntimeMenu($id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function menuItems(int $menuId): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return [];
        }

        $statement = $pdo->prepare(
            'SELECT id, menu_id, parent_id, name_zh, code, route_key, item_type, link_type,
                    linked_entity_type, linked_entity_id, root_category_id, max_depth,
                    include_children, display_mode, url, open_in_new_tab, sort, is_enabled
             FROM navigation_items
             WHERE menu_id = :menu_id
             ORDER BY sort DESC, id ASC'
        );
        $statement->execute(['menu_id' => $menuId]);
        $rows = $statement->fetchAll();

        return is_array($rows)
            ? array_map(fn (array $item): array => $this->normalizeItem($item), $rows)
            : [];
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage($this->storagePath());
    }

    private function storagePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/navigation_menus.json';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRuntimeMenus(): array
    {
        $path = $this->storagePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $menus = [];
        foreach ($decoded as $menu) {
            if (!is_array($menu)) {
                continue;
            }

            $menus[] = $this->normalizeMenu($menu);
        }

        return $menus;
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     */
    private function writeRuntimeMenus(array $menus): void
    {
        $path = $this->storagePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($menus), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function runtimeMenuKeyExists(string $menuKey, int $excludeId = 0): bool
    {
        foreach ($this->readRuntimeMenus() as $menu) {
            if ((int) ($menu['id'] ?? 0) === $excludeId) {
                continue;
            }

            if ((string) ($menu['menu_key'] ?? '') === $menuKey) {
                return true;
            }
        }

        return false;
    }

    private function createRuntimeMenu(array $payload): array
    {
        $menus = $this->readRuntimeMenus();
        $record = $this->normalizeMenu(array_merge($payload, [
            'id' => $this->nextMenuId($menus),
            'items' => [],
        ]));
        $menus[] = $record;
        $this->writeRuntimeMenus($menus);

        return $record;
    }

    private function updateRuntimeMenu(int $id, array $payload): ?array
    {
        $menus = $this->readRuntimeMenus();
        foreach ($menus as $index => $menu) {
            if ((int) ($menu['id'] ?? 0) !== $id) {
                continue;
            }

            $menus[$index] = $this->normalizeMenu(array_merge($menu, $payload, ['id' => $id]));
            $this->writeRuntimeMenus($menus);

            return $menus[$index];
        }

        return null;
    }

    private function replaceRuntimeItems(int $menuId, array $items): ?array
    {
        $menus = $this->readRuntimeMenus();
        foreach ($menus as $menuIndex => $menu) {
            if ((int) ($menu['id'] ?? 0) !== $menuId) {
                continue;
            }

            $nextItemId = $this->nextItemId($menus);
            $normalizedItems = [];
            foreach (array_values($items) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $itemId = (int) ($item['id'] ?? 0);
                if ($itemId <= 0 || $this->itemIdExists($normalizedItems, $itemId)) {
                    $itemId = $nextItemId++;
                }

                $normalizedItems[] = $this->normalizeItem(array_merge($item, [
                    'id' => $itemId,
                    'menu_id' => $menuId,
                ]));
            }

            $menus[$menuIndex]['items'] = $this->sortItems($normalizedItems);
            $menus[$menuIndex] = $this->normalizeMenu($menus[$menuIndex]);
            $this->writeRuntimeMenus($menus);

            return $menus[$menuIndex];
        }

        return null;
    }

    private function deleteRuntimeMenu(int $id): ?array
    {
        $menus = $this->readRuntimeMenus();
        foreach ($menus as $index => $menu) {
            if ((int) ($menu['id'] ?? 0) !== $id) {
                continue;
            }

            $record = $menu;
            array_splice($menus, $index, 1);
            $this->writeRuntimeMenus($menus);

            return $record;
        }

        return null;
    }

    private function findDatabaseMenu(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return null;
        }

        $statement = $pdo->prepare(
            'SELECT id, name_zh, menu_key, menu_position, sort, is_enabled
             FROM navigation_menus
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $menu
     * @return array<string, mixed>
     */
    private function normalizeMenu(array $menu): array
    {
        $items = [];
        foreach (($menu['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = $this->normalizeItem($item);
        }

        return [
            'id' => (int) ($menu['id'] ?? 0),
            'name_zh' => (string) ($menu['name_zh'] ?? ''),
            'menu_key' => (string) ($menu['menu_key'] ?? ''),
            'menu_position' => (string) ($menu['menu_position'] ?? 'header'),
            'sort' => (int) ($menu['sort'] ?? 0),
            'is_enabled' => !empty($menu['is_enabled']) ? 1 : 0,
            'created_at' => (string) ($menu['created_at'] ?? ''),
            'updated_at' => (string) ($menu['updated_at'] ?? ''),
            'items' => $this->sortItems($items),
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'menu_id' => (int) ($item['menu_id'] ?? 0),
            'parent_id' => (int) ($item['parent_id'] ?? 0),
            'name_zh' => (string) ($item['name_zh'] ?? ''),
            'code' => (string) ($item['code'] ?? ''),
            'route_key' => (string) ($item['route_key'] ?? ''),
            'item_type' => (string) ($item['item_type'] ?? 'manual_url'),
            'link_type' => (string) ($item['link_type'] ?? 'manual_url'),
            'linked_entity_type' => (string) ($item['linked_entity_type'] ?? ''),
            'linked_entity_id' => isset($item['linked_entity_id']) && $item['linked_entity_id'] !== '' ? (int) $item['linked_entity_id'] : null,
            'root_category_id' => isset($item['root_category_id']) && $item['root_category_id'] !== '' ? (int) $item['root_category_id'] : null,
            'max_depth' => (int) ($item['max_depth'] ?? 1),
            'include_children' => !empty($item['include_children']) ? 1 : 0,
            'display_mode' => (string) ($item['display_mode'] ?? 'plain'),
            'url' => (string) ($item['url'] ?? ''),
            'open_in_new_tab' => !empty($item['open_in_new_tab']) ? 1 : 0,
            'sort' => (int) ($item['sort'] ?? 0),
            'is_enabled' => !empty($item['is_enabled']) ? 1 : 0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     * @param array<int, array<string, mixed>> $runtimeMenus
     * @return array<int, array<string, mixed>>
     */
    private function mergeRuntimeMenus(array $menus, array $runtimeMenus): array
    {
        $indexed = [];
        foreach ($menus as $index => $menu) {
            $indexed[(int) ($menu['id'] ?? 0)] = $index;
        }

        foreach ($runtimeMenus as $runtimeMenu) {
            $id = (int) ($runtimeMenu['id'] ?? 0);
            if (!isset($indexed[$id])) {
                $menus[] = $runtimeMenu;
                continue;
            }

            $menus[$indexed[$id]] = $this->normalizeMenu(array_merge($menus[$indexed[$id]], [
                'items' => $runtimeMenu['items'] ?? ($menus[$indexed[$id]]['items'] ?? []),
            ]));
        }

        return $menus;
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     * @return array<int, array<string, mixed>>
     */
    private function sortMenus(array $menus): array
    {
        foreach ($menus as &$menu) {
            $menu = $this->normalizeMenu($menu);
        }
        unset($menu);

        usort($menus, static function (array $left, array $right): int {
            return ((int) ($right['sort'] ?? 0) <=> (int) ($left['sort'] ?? 0))
                ?: ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });

        return $menus;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function sortItems(array $items): array
    {
        usort($items, static function (array $left, array $right): int {
            return ((int) ($right['sort'] ?? 0) <=> (int) ($left['sort'] ?? 0))
                ?: ((int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0));
        });

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     */
    private function nextMenuId(array $menus): int
    {
        $maxId = 0;
        foreach ($menus as $menu) {
            $maxId = max($maxId, (int) ($menu['id'] ?? 0));
        }

        return $maxId + 1;
    }

    /**
     * @param array<int, array<string, mixed>> $menus
     */
    private function nextItemId(array $menus): int
    {
        $maxId = 0;
        foreach ($menus as $menu) {
            foreach (($menu['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $maxId = max($maxId, (int) ($item['id'] ?? 0));
            }
        }

        return $maxId + 1;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function itemIdExists(array $items, int $id): bool
    {
        foreach ($items as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return true;
            }
        }

        return false;
    }
}
