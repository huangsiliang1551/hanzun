<?php

declare(strict_types=1);

namespace app\service\rbac;

use app\repository\AdminUserRepository;
use app\repository\MenuRepository;
use app\repository\RoleRepository;

final class RbacService
{
    public function __construct(
        private readonly MenuRepository $menuRepository = new MenuRepository(),
        private readonly AdminUserRepository $adminUserRepository = new AdminUserRepository(),
        private readonly RoleRepository $roleRepository = new RoleRepository()
    )
    {
    }

    public function menuTree(?int $userId = null): array
    {
        if ($userId === null) {
            return $this->menuRepository->allVisibleMenus();
        }

        $allowedIds = [];
        foreach ($this->adminUserRepository->rolesForUser($userId) as $role) {
            $allowedIds = array_merge($allowedIds, $this->roleRepository->roleMenuIds((int) ($role['id'] ?? 0)));
        }
        $allowedIds = array_values(array_unique(array_filter($allowedIds)));

        if ($allowedIds === []) {
            return [];
        }

        $menus = $this->menuRepository->allMenus();
        $menus = array_values(array_filter($menus, static fn (array $menu): bool => in_array((int) ($menu['id'] ?? 0), $allowedIds, true)));

        if ($menus === []) {
            return [];
        }

        return $menus;
    }

    public function permissions(?int $userId = null): array
    {
        $actionPoints = $this->menuRepository->actionPoints();
        if ($userId === null) {
            return array_values(array_map(static fn (array $item): string => (string) $item['code'], $actionPoints));
        }

        $allowedIds = [];
        foreach ($this->adminUserRepository->rolesForUser($userId) as $role) {
            $allowedIds = array_merge($allowedIds, $this->roleRepository->roleActionPointIds((int) ($role['id'] ?? 0)));
        }
        $allowedIds = array_values(array_unique(array_filter($allowedIds)));

        return array_values(array_map(
            static fn (array $item): string => (string) $item['code'],
            array_filter($actionPoints, static fn (array $item): bool => in_array((int) ($item['id'] ?? 0), $allowedIds, true))
        ));
    }

    public function roleSummary(int $roleId): array
    {
        $role = $this->roleRepository->findRole($roleId);
        if ($role === null) {
            return [];
        }

        $menuIds = $this->roleRepository->roleMenuIds($roleId);
        $actionPointIds = $this->roleRepository->roleActionPointIds($roleId);
        $menus = array_values(array_filter(
            $this->menuRepository->allMenus(),
            static fn (array $menu): bool => in_array((int) ($menu['id'] ?? 0), $menuIds, true)
        ));
        $actionPoints = array_values(array_filter(
            $this->menuRepository->actionPoints(),
            static fn (array $point): bool => in_array((int) ($point['id'] ?? 0), $actionPointIds, true)
        ));

        return [
            'role' => $role,
            'menus' => $menus,
            'action_points' => $actionPoints,
        ];
    }

    public function allMenus(): array
    {
        return $this->menuRepository->allMenus();
    }

    public function allActionPoints(): array
    {
        return $this->menuRepository->actionPoints();
    }
}
