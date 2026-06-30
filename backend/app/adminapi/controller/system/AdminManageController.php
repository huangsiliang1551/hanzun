<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\system\AdminManageService;

class AdminManageController extends BaseAdminController
{
    public function __construct(private readonly AdminManageService $adminManageService = new AdminManageService())
    {
    }

    public function users(): array
    {
        return $this->success($this->adminManageService->users());
    }

    public function usersBootstrap(): array
    {
        return $this->success($this->adminManageService->usersBootstrap());
    }

    public function userDetail(Request $request): array
    {
        return $this->success($this->adminManageService->userDetail((int) $request->routeParam('id')));
    }

    public function roles(): array
    {
        return $this->success($this->adminManageService->roles());
    }

    public function rolesBootstrap(): array
    {
        return $this->success($this->adminManageService->rolesBootstrap());
    }

    public function roleDetail(Request $request): array
    {
        return $this->success($this->adminManageService->roleDetail((int) $request->routeParam('id')));
    }

    public function menus(): array
    {
        return $this->success($this->adminManageService->menus());
    }

    public function actionPoints(): array
    {
        return $this->success($this->adminManageService->actionPoints());
    }

    public function createUser(Request $request): array
    {
        return $this->success($this->adminManageService->createUser([
            'username' => $request->input('username'),
            'password' => $request->input('password'),
            'nickname' => $request->input('nickname'),
            'email' => $request->input('email'),
            'mobile' => $request->input('mobile'),
            'status' => $request->input('status'),
            'role_ids' => $request->input('role_ids'),
        ]), [], '管理员已创建');
    }

    public function updateUser(Request $request): array
    {
        return $this->success($this->adminManageService->updateUser((int) $request->routeParam('id'), [
            'password' => $request->input('password'),
            'nickname' => $request->input('nickname'),
            'email' => $request->input('email'),
            'mobile' => $request->input('mobile'),
            'status' => $request->input('status'),
            'role_ids' => $request->input('role_ids'),
        ]), [], '管理员已更新');
    }

    public function deleteUser(Request $request): array
    {
        return $this->success(
            $this->adminManageService->deleteUser((int) $request->routeParam('id'), current_user()),
            [],
            '管理员已删除'
        );
    }

    public function createRole(Request $request): array
    {
        return $this->success($this->adminManageService->createRole([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'description' => $request->input('description'),
            'status' => $request->input('status'),
        ]), [], '角色已创建');
    }

    public function updateRole(Request $request): array
    {
        return $this->success($this->adminManageService->updateRole((int) $request->routeParam('id'), [
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'description' => $request->input('description'),
            'status' => $request->input('status'),
        ]), [], '角色已更新');
    }

    public function deleteRole(Request $request): array
    {
        return $this->success(
            $this->adminManageService->deleteRole((int) $request->routeParam('id')),
            [],
            '角色已删除'
        );
    }

    public function updateRolePermissions(Request $request): array
    {
        return $this->success($this->adminManageService->updateRolePermissions((int) $request->routeParam('id'), [
            'menu_ids' => $request->input('menu_ids'),
            'action_point_ids' => $request->input('action_point_ids'),
        ]), [], '角色权限已更新');
    }
}
