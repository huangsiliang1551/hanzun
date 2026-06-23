<?php

declare(strict_types=1);

namespace app\service\system;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\AdminUserRepository;
use app\repository\RoleRepository;
use app\service\auth\SessionService;
use app\service\log\OperationLogService;
use app\service\rbac\RbacService;

final class AdminManageService
{
    public function __construct(
        private readonly AdminUserRepository $adminUserRepository = new AdminUserRepository(),
        private readonly RoleRepository $roleRepository = new RoleRepository(),
        private readonly RbacService $rbacService = new RbacService(),
        private readonly SessionService $sessionService = new SessionService(),
        private readonly OperationLogService $operationLogService = new OperationLogService()
    ) {
    }

    public function users(): array
    {
        $items = [];
        foreach ($this->adminUserRepository->listUsers() as $user) {
            $user['roles'] = $this->adminUserRepository->rolesForUser((int) ($user['id'] ?? 0));
            $items[] = $user;
        }

        return ['items' => $items];
    }

    public function usersBootstrap(): array
    {
        return [
            'users' => $this->users(),
            'roles' => $this->roles(),
        ];
    }

    public function userDetail(int $id): array
    {
        $user = $this->adminUserRepository->findById($id);
        if ($user === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        return [
            'user' => $user,
            'roles' => $this->adminUserRepository->rolesForUser($id),
            'permissions' => $this->rbacService->permissions($id),
            'menus' => $this->rbacService->menuTree($id),
        ];
    }

    public function roles(): array
    {
        return ['items' => $this->roleRepository->listRoles()];
    }

    public function rolesBootstrap(): array
    {
        return [
            'roles' => $this->roles(),
            'menus' => $this->menus(),
            'action_points' => $this->actionPoints(),
        ];
    }

    public function roleDetail(int $id): array
    {
        $summary = $this->rbacService->roleSummary($id);
        if ($summary === []) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        return $summary;
    }

    public function menus(): array
    {
        return ['items' => $this->rbacService->allMenus()];
    }

    public function actionPoints(): array
    {
        return ['items' => $this->rbacService->allActionPoints()];
    }

    public function createUser(array $input): array
    {
        $username = $this->validateUsername((string) ($input['username'] ?? ''));
        $password = $this->validatePassword((string) ($input['password'] ?? ''), true);
        if ($this->adminUserRepository->findByUsername($username) !== null) {
            throw new BusinessException('record already exists', ErrorCode::ALREADY_EXISTS);
        }

        $roleIds = $this->normalizeRoleIds($input['role_ids'] ?? [2], true);
        $record = $this->adminUserRepository->create([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'nickname' => $this->validateNickname((string) ($input['nickname'] ?? $username), $username),
            'email' => $this->validateEmail((string) ($input['email'] ?? '')),
            'mobile' => $this->validateMobile((string) ($input['mobile'] ?? '')),
            'status' => $this->normalizeStatus($input['status'] ?? 1),
        ], $roleIds);

        $this->operationLogService->recordCurrentAction('system', 'system.admin_user.create', 'admin_user', $record, 'admin user created');

        return $this->userDetail((int) ($record['id'] ?? 0));
    }

    public function updateUser(int $id, array $input): array
    {
        $existing = $this->adminUserRepository->findById($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $roleIds = $this->normalizeRoleIds($input['role_ids'] ?? [], false);
        if ($roleIds === []) {
            $roleIds = array_map(
                static fn (array $role): int => (int) ($role['id'] ?? 0),
                $this->adminUserRepository->rolesForUser($id)
            );
        }

        $password = $this->validatePassword((string) ($input['password'] ?? ''), false);
        $record = $this->adminUserRepository->update($id, [
            'nickname' => $this->validateNickname((string) ($input['nickname'] ?? ($existing['nickname'] ?? '')), (string) ($existing['username'] ?? '')),
            'email' => $this->validateEmail((string) ($input['email'] ?? ($existing['email'] ?? ''))),
            'mobile' => $this->validateMobile((string) ($input['mobile'] ?? ($existing['mobile'] ?? ''))),
            'status' => array_key_exists('status', $input) ? $this->normalizeStatus($input['status']) : (int) ($existing['status'] ?? 1),
            'password_hash' => $password !== '' ? password_hash($password, PASSWORD_BCRYPT) : '',
        ], $roleIds);

        if ($record === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        if ($password !== '' || (int) ($record['status'] ?? 1) !== 1) {
            $this->sessionService->revokeAllForUser($id);
        }

        $this->operationLogService->recordCurrentAction('system', 'system.admin_user.update', 'admin_user', $record, 'admin user updated');

        return $this->userDetail($id);
    }

    public function deleteUser(int $id, ?array $operator): array
    {
        $existing = $this->adminUserRepository->findById($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        if ($operator !== null && (int) ($operator['id'] ?? 0) === $id) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $detail = $this->userDetail($id);
        $this->sessionService->revokeAllForUser($id);
        $deleted = $this->adminUserRepository->deleteUser($id);
        if ($deleted === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('system', 'system.admin_user.delete', 'admin_user', $deleted, 'admin user deleted');

        return $detail;
    }

    public function createRole(array $input): array
    {
        $payload = $this->normalizeRolePayload($input);
        if ($this->roleRepository->findRoleByCode((string) ($payload['code'] ?? '')) !== null) {
            throw new BusinessException('record already exists', ErrorCode::ALREADY_EXISTS);
        }

        $record = $this->roleRepository->createRole($payload);
        $this->operationLogService->recordCurrentAction('system', 'system.role.create', 'admin_role', $record, 'role created');

        return $this->roleDetail((int) ($record['id'] ?? 0));
    }

    public function updateRole(int $id, array $input): array
    {
        $existing = $this->roleRepository->findRole($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $payload = $this->normalizeRolePayload(array_merge($existing, $input));
        $duplicate = $this->roleRepository->findRoleByCode((string) ($payload['code'] ?? ''));
        if ($duplicate !== null && (int) ($duplicate['id'] ?? 0) !== $id) {
            throw new BusinessException('record already exists', ErrorCode::ALREADY_EXISTS);
        }

        if (in_array((string) ($existing['code'] ?? ''), ['super-admin', 'operator'], true)
            && (string) ($existing['code'] ?? '') !== (string) ($payload['code'] ?? '')) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $updated = $this->roleRepository->updateRole($id, $payload);
        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('system', 'system.role.update', 'admin_role', $updated, 'role updated');

        return $this->roleDetail($id);
    }

    public function deleteRole(int $id): array
    {
        $existing = $this->roleRepository->findRole($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        if (in_array((string) ($existing['code'] ?? ''), ['super-admin', 'operator'], true)) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        if ($this->roleRepository->countUsersForRole($id) > 0) {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $detail = $this->roleDetail($id);
        $deleted = $this->roleRepository->deleteRole($id);
        if ($deleted === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('system', 'system.role.delete', 'admin_role', $deleted, 'role deleted');

        return $detail;
    }

    public function updateRolePermissions(int $id, array $input): array
    {
        $role = $this->roleRepository->findRole($id);
        if ($role === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $menuIds = $this->normalizePermissionIds(
            $input['menu_ids'] ?? [],
            array_map(static fn (array $menu): int => (int) ($menu['id'] ?? 0), $this->rbacService->allMenus())
        );
        $actionPointIds = $this->normalizePermissionIds(
            $input['action_point_ids'] ?? [],
            array_map(static fn (array $point): int => (int) ($point['id'] ?? 0), $this->rbacService->allActionPoints())
        );

        $this->roleRepository->updateRolePermissions($id, $menuIds, $actionPointIds);
        $this->operationLogService->recordCurrentAction('system', 'system.role.permissions.update', 'admin_role', $role, 'role permissions updated');

        return $this->roleDetail($id);
    }

    private function validateUsername(string $username): string
    {
        $username = trim($username);
        if ($username === '' || preg_match('/^[A-Za-z0-9._-]{3,32}$/', $username) !== 1) {
            throw new BusinessException('invalid username', ErrorCode::INVALID_PARAMS);
        }

        return $username;
    }

    private function validatePassword(string $password, bool $required): string
    {
        $password = trim($password);
        if ($password === '') {
            if ($required) {
                throw new BusinessException('password is required', ErrorCode::INVALID_PARAMS);
            }

            return '';
        }

        if (strlen($password) < 8 || strlen($password) > 64) {
            throw new BusinessException('invalid password length', ErrorCode::INVALID_PARAMS);
        }

        return $password;
    }

    private function validateNickname(string $nickname, string $fallback): string
    {
        $nickname = trim($nickname);
        if ($nickname === '') {
            $nickname = $fallback;
        }

        if (mb_strlen($nickname) > 50) {
            throw new BusinessException('nickname too long', ErrorCode::INVALID_PARAMS);
        }

        return $nickname;
    }

    private function validateEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '') {
            return '';
        }

        if (strlen($email) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BusinessException('invalid email', ErrorCode::INVALID_PARAMS);
        }

        return $email;
    }

    private function validateMobile(string $mobile): string
    {
        $mobile = trim($mobile);
        if ($mobile === '') {
            return '';
        }

        if (preg_match('/^[0-9+\-\s()]{6,30}$/', $mobile) !== 1) {
            throw new BusinessException('invalid mobile', ErrorCode::INVALID_PARAMS);
        }

        return $mobile;
    }

    private function normalizeRolePayload(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $code = trim((string) ($input['code'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));

        if ($name === '' || mb_strlen($name) > 64) {
            throw new BusinessException('invalid role name', ErrorCode::INVALID_PARAMS);
        }

        if ($code === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,63}$/', $code) !== 1) {
            throw new BusinessException('invalid role code', ErrorCode::INVALID_PARAMS);
        }

        if (mb_strlen($description) > 255) {
            throw new BusinessException('role description too long', ErrorCode::INVALID_PARAMS);
        }

        return [
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'status' => $this->normalizeStatus($input['status'] ?? 1),
        ];
    }

    private function normalizeStatus(mixed $status): int
    {
        if ($status === 1 || $status === '1' || $status === true || $status === 'true' || $status === 'enabled' || $status === 'active') {
            return 1;
        }

        if ($status === 0 || $status === '0' || $status === false || $status === 'false' || $status === 'disabled' || $status === 'inactive') {
            return 0;
        }

        throw new BusinessException('invalid status', ErrorCode::INVALID_PARAMS);
    }

    /**
     * @return array<int, int>
     */
    private function normalizeRoleIds(mixed $roleIds, bool $required): array
    {
        if (!is_array($roleIds)) {
            if ($required) {
                throw new BusinessException('role ids required', ErrorCode::INVALID_PARAMS);
            }

            return [];
        }

        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn (int $id): bool => $id > 0)));
        if ($required && $roleIds === []) {
            throw new BusinessException('role ids required', ErrorCode::INVALID_PARAMS);
        }

        $validRoleIds = array_map(static fn (array $role): int => (int) ($role['id'] ?? 0), $this->roleRepository->listRoles());
        foreach ($roleIds as $roleId) {
            if (!in_array($roleId, $validRoleIds, true)) {
                throw new BusinessException('invalid role id', ErrorCode::INVALID_PARAMS);
            }
        }

        return $roleIds;
    }

    /**
     * @param array<int, int> $validIds
     * @return array<int, int>
     */
    private function normalizePermissionIds(mixed $ids, array $validIds): array
    {
        if (!is_array($ids)) {
            throw new BusinessException('invalid permission ids', ErrorCode::INVALID_PARAMS);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        foreach ($ids as $id) {
            if (!in_array($id, $validIds, true)) {
                throw new BusinessException('invalid permission id', ErrorCode::INVALID_PARAMS);
            }
        }

        return $ids;
    }
}
