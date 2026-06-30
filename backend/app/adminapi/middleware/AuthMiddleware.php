<?php

declare(strict_types=1);

namespace app\adminapi\middleware;

use app\common\auth\BearerToken;
use app\common\exception\BusinessException;
use app\common\http\RequestContext;
use app\enum\ErrorCode;
use app\service\auth\SessionService;

class AuthMiddleware
{
    public function __construct(private readonly SessionService $sessionService = new SessionService())
    {
    }

    public function handle($request, \Closure $next)
    {
        $authorization = $request->header('Authorization', '');
        if ($authorization === '') {
            throw new BusinessException('登录状态已失效', ErrorCode::UNAUTHORIZED);
        }

        $token = BearerToken::extract($authorization);
        $user = $this->sessionService->validateAccessToken($token);
        if ($user === null) {
            throw new BusinessException('登录状态已失效', ErrorCode::UNAUTHORIZED);
        }

        RequestContext::setUser($user);
        return $next($request);
    }

}
