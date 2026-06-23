<?php

declare(strict_types=1);

namespace app\adminapi\middleware;

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
            throw new BusinessException('登录会话已失效', ErrorCode::UNAUTHORIZED);
        }

        $token = $this->parseBearerToken($authorization);
        $user = $this->sessionService->validateAccessToken($token);
        if ($user === null) {
            throw new BusinessException('登录会话已失效', ErrorCode::UNAUTHORIZED);
        }

        RequestContext::setUser($user);
        return $next($request);
    }

    private function parseBearerToken(string $authorization): string
    {
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            throw new BusinessException('登录会话已失效', ErrorCode::UNAUTHORIZED);
        }

        return trim((string) $matches[1]);
    }
}
