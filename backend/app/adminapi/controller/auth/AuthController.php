<?php

declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\auth\AuthService;

class AuthController extends BaseAdminController
{
    public function __construct(
        private readonly AuthService $authService = new AuthService()
    ) {
    }

    public function login(Request $request): array
    {
        $username = (string) $request->input('username', '');
        $password = (string) $request->input('password', '');

        return $this->success($this->authService->login($username, $password));
    }

    public function refresh(Request $request): array
    {
        $refreshToken = (string) $request->input('refresh_token', '');

        return $this->success($this->authService->refresh($refreshToken));
    }

    public function logout(Request $request): array
    {
        $this->authService->logout($request->header('Authorization', ''));
        return $this->success([], [], '已退出登录');
    }

    public function profile(): array
    {
        return $this->success($this->authService->currentUser());
    }
}
