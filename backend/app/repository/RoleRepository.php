<?php

declare(strict_types=1);

namespace app\repository;

use app\common\storage\RuntimeStorage;
use app\common\database\DatabaseManager;
use PDO;

final class RoleRepository
{
    public function listRoles(): array
    {
        if (RuntimeStorage::enabled()) {
            return $this->roleStore()->all();
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->query(
                'SELECT id, name, code, description, status, created_at, updated_at
                 FROM admin_roles
                 ORDER BY id ASC'
            );
            $records = $statement->fetchAll();

            return is_array($records) ? $records : [];
        }

        return [];
    }

    public function findRole(int $id): ?array
    {
        foreach ($this->listRoles() as $role) {
            if ((int) ($role['id'] ?? 0) === $id) {
                return $role;
            }
        }

        return null;
    }

    public function findRoleByCode(string $code): ?array
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return null;
        }

        if (RuntimeStorage::enabled()) {
            foreach ($this->roleStore()->all() as $record) {
                if (strtolower((string) ($record['code'] ?? '')) === $code) {
                    return $record;
                }
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT id, name, code, description, status, created_at, updated_at
                 FROM admin_roles
                 WHERE LOWER(code) = :code
                 LIMIT 1'
            );
            $statement->execute(['code' => $code]);
            $record = $statement->fetch();

            return is_array($record) ? $record : null;
        }

        return null;
    }

    public function roleMenuIds(int $roleId): array
    {
        if (RuntimeStorage::enabled()) {
            $rows = array_filter(
                $this->roleMenuStore()->all(),
                static fn (array $row): bool => (int) ($row['role_id'] ?? 0) === $roleId
            );

            return array_values(array_map(static fn (array $row): int => (int) ($row['menu_id'] ?? 0), $rows));
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare('SELECT menu_id FROM admin_role_menus WHERE role_id = :role_id');
            $statement->execute(['role_id' => $roleId]);
            $rows = $statement->fetchAll();

            return array_map(static fn (array $row): int => (int) ($row['menu_id'] ?? 0), is_array($rows) ? $rows : []);
        }

        return [];
    }

    public function roleActionPointIds(int $roleId): array
    {
        if (RuntimeStorage::enabled()) {
            $rows = array_filter(
                $this->roleActionStore()->all(),
                static fn (array $row): bool => (int) ($row['role_id'] ?? 0) === $roleId
            );

            return array_values(array_map(static fn (array $row): int => (int) ($row['action_point_id'] ?? 0), $rows));
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare('SELECT action_point_id FROM admin_role_action_points WHERE role_id = :role_id');
            $statement->execute(['role_id' => $roleId]);
            $rows = $statement->fetchAll();

            return array_map(static fn (array $row): int => (int) ($row['action_point_id'] ?? 0), is_array($rows) ? $rows : []);
        }

        return [];
    }

    public function createRole(array $payload): array
    {
        if (RuntimeStorage::enabled()) {
            $items = $this->roleStore()->all();
            $now = date('Y-m-d H:i:s');
            $record = [
                'id' => RuntimeStorage::nextId($items),
                'name' => $payload['name'],
                'code' => $payload['code'],
                'description' => $payload['description'],
                'status' => (int) $payload['status'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $items[] = $record;
            $this->roleStore()->put($items);

            return $record;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO admin_roles (name, code, description, status, created_at, updated_at)
                 VALUES (:name, :code, :description, :status, NOW(), NOW())'
            );
            $statement->execute([
                'name' => $payload['name'],
                'code' => $payload['code'],
                'description' => $payload['description'],
                'status' => $payload['status'],
            ]);

            return $this->findRole((int) $pdo->lastInsertId()) ?? $payload;
        }

        return $payload;
    }

    public function updateRole(int $id, array $payload): ?array
    {
        if (RuntimeStorage::enabled()) {
            $items = $this->roleStore()->all();
            foreach ($items as $index => $item) {
                if ((int) ($item['id'] ?? 0) !== $id) {
                    continue;
                }

                $item['name'] = $payload['name'];
                $item['code'] = $payload['code'];
                $item['description'] = $payload['description'];
                $item['status'] = (int) $payload['status'];
                $item['updated_at'] = date('Y-m-d H:i:s');
                $items[$index] = $item;
                $this->roleStore()->put($items);

                return $item;
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'UPDATE admin_roles
                 SET name = :name, code = :code, description = :description, status = :status, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'name' => $payload['name'],
                'code' => $payload['code'],
                'description' => $payload['description'],
                'status' => $payload['status'],
            ]);

            return $this->findRole($id);
        }

        return null;
    }

    public function deleteRole(int $id): ?array
    {
        $existing = $this->findRole($id);
        if ($existing === null) {
            return null;
        }

        if (RuntimeStorage::enabled()) {
            $this->roleStore()->put(array_values(array_filter(
                $this->roleStore()->all(),
                static fn (array $item): bool => (int) ($item['id'] ?? 0) !== $id
            )));
            $this->roleMenuStore()->put(array_values(array_filter(
                $this->roleMenuStore()->all(),
                static fn (array $item): bool => (int) ($item['role_id'] ?? 0) !== $id
            )));
            $this->roleActionStore()->put(array_values(array_filter(
                $this->roleActionStore()->all(),
                static fn (array $item): bool => (int) ($item['role_id'] ?? 0) !== $id
            )));
            $this->userRoleStore()->put(array_values(array_filter(
                $this->userRoleStore()->all(),
                static fn (array $item): bool => (int) ($item['role_id'] ?? 0) !== $id
            )));

            return $existing;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $deleteMenus = $pdo->prepare('DELETE FROM admin_role_menus WHERE role_id = :role_id');
            $deleteMenus->execute(['role_id' => $id]);

            $deleteActions = $pdo->prepare('DELETE FROM admin_role_action_points WHERE role_id = :role_id');
            $deleteActions->execute(['role_id' => $id]);

            $deleteUserRoles = $pdo->prepare('DELETE FROM admin_user_roles WHERE role_id = :role_id');
            $deleteUserRoles->execute(['role_id' => $id]);

            $deleteRole = $pdo->prepare('DELETE FROM admin_roles WHERE id = :id');
            $deleteRole->execute(['id' => $id]);

            return $existing;
        }

        return null;
    }

    public function countUsersForRole(int $roleId): int
    {
        if (RuntimeStorage::enabled()) {
            return count(array_filter(
                $this->userRoleStore()->all(),
                static fn (array $item): bool => (int) ($item['role_id'] ?? 0) === $roleId
            ));
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM admin_user_roles WHERE role_id = :role_id');
            $statement->execute(['role_id' => $roleId]);

            return (int) $statement->fetchColumn();
        }

        return 0;
    }

    public function updateRolePermissions(int $roleId, array $menuIds, array $actionPointIds): void
    {
        $menuIds = array_values(array_unique(array_map('intval', $menuIds)));
        $actionPointIds = array_values(array_unique(array_map('intval', $actionPointIds)));

        if (RuntimeStorage::enabled()) {
            $menus = array_values(array_filter(
                $this->roleMenuStore()->all(),
                static fn (array $item): bool => (int) ($item['role_id'] ?? 0) !== $roleId
            ));
            foreach ($menuIds as $menuId) {
                $menus[] = [
                    'role_id' => $roleId,
                    'menu_id' => $menuId,
                ];
            }
            $this->roleMenuStore()->put($menus);

            $actions = array_values(array_filter(
                $this->roleActionStore()->all(),
                static fn (array $item): bool => (int) ($item['role_id'] ?? 0) !== $roleId
            ));
            foreach ($actionPointIds as $actionPointId) {
                $actions[] = [
                    'role_id' => $roleId,
                    'action_point_id' => $actionPointId,
                ];
            }
            $this->roleActionStore()->put($actions);
            return;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $deleteMenus = $pdo->prepare('DELETE FROM admin_role_menus WHERE role_id = :role_id');
            $deleteMenus->execute(['role_id' => $roleId]);
            $insertMenu = $pdo->prepare('INSERT INTO admin_role_menus (role_id, menu_id) VALUES (:role_id, :menu_id)');
            foreach ($menuIds as $menuId) {
                $insertMenu->execute(['role_id' => $roleId, 'menu_id' => $menuId]);
            }

            $deleteActions = $pdo->prepare('DELETE FROM admin_role_action_points WHERE role_id = :role_id');
            $deleteActions->execute(['role_id' => $roleId]);
            $insertAction = $pdo->prepare('INSERT INTO admin_role_action_points (role_id, action_point_id) VALUES (:role_id, :action_point_id)');
            foreach ($actionPointIds as $actionPointId) {
                $insertAction->execute(['role_id' => $roleId, 'action_point_id' => $actionPointId]);
            }
        }
    }

    private function roleStore(): \app\common\storage\JsonFileStore
    {
        return RuntimeStorage::store('admin_roles.json');
    }

    private function roleMenuStore(): \app\common\storage\JsonFileStore
    {
        return RuntimeStorage::store('admin_role_menus.json');
    }

    private function roleActionStore(): \app\common\storage\JsonFileStore
    {
        return RuntimeStorage::store('admin_role_action_points.json');
    }

    private function userRoleStore(): \app\common\storage\JsonFileStore
    {
        return RuntimeStorage::store('admin_user_roles.json');
    }
}
