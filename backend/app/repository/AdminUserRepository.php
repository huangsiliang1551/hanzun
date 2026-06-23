<?php

/**
 * Default admin email: 'email' => 'admin@hanzunmachinery.com'
 */

declare(strict_types=1);

namespace app\repository;

use app\common\storage\RuntimeStorage;
use app\common\database\DatabaseManager;
use PDO;

final class AdminUserRepository
{
    public function listUsers(): array
    {
        if (RuntimeStorage::enabled()) {
            return $this->userStore()->all();
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->query(
                'SELECT id, username, nickname, email, mobile, status, last_login_at, last_login_ip, created_at, updated_at
                 FROM admin_users
                 ORDER BY id ASC'
            );
            $records = $statement->fetchAll();

            return is_array($records) ? $records : [];
        }

        return [];
    }

    public function findById(int $id): ?array
    {
        if (RuntimeStorage::enabled()) {
            foreach ($this->userStore()->all() as $record) {
                if ((int) ($record['id'] ?? 0) === $id) {
                    return $record;
                }
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $record = $statement->fetch();

            return is_array($record) ? $record : null;
        }

        return null;
    }

    public function findByUsername(string $username): ?array
    {
        if (RuntimeStorage::enabled()) {
            foreach ($this->userStore()->all() as $record) {
                if (strcasecmp((string) ($record['username'] ?? ''), $username) === 0) {
                    return $record;
                }
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare('SELECT * FROM admin_users WHERE username = :username LIMIT 1');
            $statement->execute(['username' => $username]);
            $record = $statement->fetch();

            return is_array($record) ? $record : null;
        }

        return null;
    }

    public function rolesForUser(int $userId): array
    {
        if (RuntimeStorage::enabled()) {
            $roleIds = [];
            foreach ($this->userRoleStore()->all() as $item) {
                if ((int) ($item['user_id'] ?? 0) === $userId) {
                    $roleIds[] = (int) ($item['role_id'] ?? 0);
                }
            }

            $roles = [];
            foreach ($this->roleStore()->all() as $role) {
                if (in_array((int) ($role['id'] ?? 0), $roleIds, true)) {
                    $roles[] = $role;
                }
            }

            return $roles;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT r.id, r.name, r.code, r.description, r.status
                 FROM admin_roles r
                 INNER JOIN admin_user_roles ur ON ur.role_id = r.id
                 WHERE ur.user_id = :user_id
                 ORDER BY r.id ASC'
            );
            $statement->execute(['user_id' => $userId]);
            $records = $statement->fetchAll();

            return is_array($records) ? $records : [];
        }

        return [];
    }

    public function create(array $payload, array $roleIds): array
    {
        if (RuntimeStorage::enabled()) {
            $items = $this->userStore()->all();
            $now = date('Y-m-d H:i:s');
            $record = [
                'id' => RuntimeStorage::nextId($items),
                'username' => $payload['username'],
                'password_hash' => $payload['password_hash'],
                'nickname' => $payload['nickname'],
                'email' => $payload['email'],
                'mobile' => $payload['mobile'],
                'status' => (int) $payload['status'],
                'password_version' => 1,
                'last_login_at' => null,
                'last_login_ip' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $items[] = $record;
            $this->userStore()->put($items);
            $this->replaceUserRoles((int) $record['id'], $roleIds);

            return $record;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO admin_users (username, password_hash, nickname, email, mobile, status, password_version, created_at, updated_at)
                 VALUES (:username, :password_hash, :nickname, :email, :mobile, :status, 1, NOW(), NOW())'
            );
            $statement->execute([
                'username' => $payload['username'],
                'password_hash' => $payload['password_hash'],
                'nickname' => $payload['nickname'],
                'email' => $payload['email'],
                'mobile' => $payload['mobile'],
                'status' => $payload['status'],
            ]);
            $userId = (int) $pdo->lastInsertId();
            $this->replaceUserRoles($userId, $roleIds);

            return $this->findById($userId) ?? $payload;
        }

        return $payload;
    }

    public function update(int $id, array $payload, array $roleIds): ?array
    {
        if (RuntimeStorage::enabled()) {
            $items = $this->userStore()->all();
            $updated = null;
            foreach ($items as $index => $item) {
                if ((int) ($item['id'] ?? 0) !== $id) {
                    continue;
                }

                $item['nickname'] = $payload['nickname'];
                $item['email'] = $payload['email'];
                $item['mobile'] = $payload['mobile'];
                $item['status'] = (int) $payload['status'];
                $item['updated_at'] = date('Y-m-d H:i:s');

                if (($payload['password_hash'] ?? '') !== '') {
                    $item['password_hash'] = $payload['password_hash'];
                    $item['password_version'] = (int) ($item['password_version'] ?? 0) + 1;
                }

                $items[$index] = $item;
                $updated = $item;
                break;
            }

            if ($updated === null) {
                return null;
            }

            $this->userStore()->put($items);
            $this->replaceUserRoles($id, $roleIds);

            return $updated;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'UPDATE admin_users
                 SET nickname = :nickname, email = :email, mobile = :mobile, status = :status, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'nickname' => $payload['nickname'],
                'email' => $payload['email'],
                'mobile' => $payload['mobile'],
                'status' => $payload['status'],
            ]);

            if (($payload['password_hash'] ?? '') !== '') {
                $passwordStatement = $pdo->prepare(
                    'UPDATE admin_users
                     SET password_hash = :password_hash, password_version = password_version + 1, updated_at = NOW()
                     WHERE id = :id'
                );
                $passwordStatement->execute([
                    'id' => $id,
                    'password_hash' => $payload['password_hash'],
                ]);
            }

            $this->replaceUserRoles($id, $roleIds);

            return $this->findById($id);
        }

        return null;
    }

    public function replaceUserRoles(int $userId, array $roleIds): void
    {
        if (RuntimeStorage::enabled()) {
            $items = array_values(array_filter(
                $this->userRoleStore()->all(),
                static fn (array $item): bool => (int) ($item['user_id'] ?? 0) !== $userId
            ));

            foreach (array_values(array_unique($roleIds)) as $roleId) {
                $items[] = [
                    'user_id' => $userId,
                    'role_id' => (int) $roleId,
                ];
            }

            $this->userRoleStore()->put($items);
            return;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $delete = $pdo->prepare('DELETE FROM admin_user_roles WHERE user_id = :user_id');
            $delete->execute(['user_id' => $userId]);

            $insert = $pdo->prepare('INSERT INTO admin_user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
            foreach (array_values(array_unique($roleIds)) as $roleId) {
                $insert->execute([
                    'user_id' => $userId,
                    'role_id' => (int) $roleId,
                ]);
            }
        }
    }

    public function deleteUser(int $id): ?array
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            return null;
        }

        if (RuntimeStorage::enabled()) {
            $users = array_values(array_filter(
                $this->userStore()->all(),
                static fn (array $item): bool => (int) ($item['id'] ?? 0) !== $id
            ));
            $this->userStore()->put($users);

            $userRoles = array_values(array_filter(
                $this->userRoleStore()->all(),
                static fn (array $item): bool => (int) ($item['user_id'] ?? 0) !== $id
            ));
            $this->userRoleStore()->put($userRoles);

            return $existing;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $deleteRoles = $pdo->prepare('DELETE FROM admin_user_roles WHERE user_id = :user_id');
            $deleteRoles->execute(['user_id' => $id]);

            $deleteUser = $pdo->prepare('DELETE FROM admin_users WHERE id = :id');
            $deleteUser->execute(['id' => $id]);

            return $existing;
        }

        return null;
    }

    public function verifyPassword(array $user, string $password): bool
    {
        $stored = (string) ($user['password_hash'] ?? '');
        if ($stored === '') {
            return false;
        }

        if (preg_match('/^[a-f0-9]{64}$/i', $stored) === 1) {
            return hash_equals(strtolower($stored), hash('sha256', $password));
        }

        return password_verify($password, $stored);
    }

    private function userStore(): \app\common\storage\JsonFileStore
    {
        return RuntimeStorage::store('admin_users.json');
    }

    private function userRoleStore(): \app\common\storage\JsonFileStore
    {
        return RuntimeStorage::store('admin_user_roles.json');
    }

    private function roleStore(): \app\common\storage\JsonFileStore
    {
        return RuntimeStorage::store('admin_roles.json');
    }
}
