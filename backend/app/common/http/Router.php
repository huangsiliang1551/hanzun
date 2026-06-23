<?php

declare(strict_types=1);

namespace app\common\http;

use app\adminapi\middleware\AuthMiddleware;
use app\common\exception\BusinessException;
use app\common\middleware\RateLimitExceededException;
use app\common\middleware\RateLimitMiddleware;
use app\enum\ErrorCode;
use app\repository\AdminUserRepository;
use app\service\rbac\RbacService;

final class Router
{
    /**
     * @param array<int, array<int, string|null>> $routes
     */
    public function __construct(
        private readonly array $routes,
        private readonly RbacService $rbacService = new RbacService(),
        private readonly AdminUserRepository $adminUserRepository = new AdminUserRepository()
    )
    {
    }

    public function dispatch(Request $request): array
    {
        $this->applyRateLimit($request);

        foreach ($this->routes as $route) {
            [$method, $path, $handler] = $route;
            $permission = $route[3] ?? null;
            if ($request->method() !== strtoupper($method)) {
                continue;
            }

            $params = $this->matchParams($path, $request->path());
            if ($params === null) {
                continue;
            }

            $request = $request->withRouteParams($params);
            RequestContext::setRequest($request);

            if (!$this->isPublicRoute($path)) {
                $middleware = new AuthMiddleware();
                $middleware->handle($request, static fn (Request $request) => $request);
                $this->authorize($permission);
            }

            return $this->invokeHandler($handler, $request);
        }

        throw new BusinessException('记录不存在', ErrorCode::NOT_FOUND);
    }

    private function applyRateLimit(Request $request): void
    {
        $path = $request->path();
        $method = $request->method();

        $rateRules = [
            'POST /api/ai/chat' => [['window_seconds' => 60, 'max_requests' => 10], ['window_seconds' => 3600, 'max_requests' => 60]],
            'POST /api/ai/session' => [['window_seconds' => 60, 'max_requests' => 10], ['window_seconds' => 3600, 'max_requests' => 60]],
            'POST /api/site/lead' => [['window_seconds' => 60, 'max_requests' => 3], ['window_seconds' => 3600, 'max_requests' => 10]],
            'POST /api/visitor-events' => [['window_seconds' => 60, 'max_requests' => 30]],
            'POST /api/site/pageview' => [['window_seconds' => 60, 'max_requests' => 30]],
            'POST /admin/auth/login' => [['window_seconds' => 60, 'max_requests' => 5]],
        ];

        $matchedRule = null;
        $matchedRuleKey = null;
        foreach ($rateRules as $pattern => $rules) {
            $routeMethod = explode(' ', $pattern, 2)[0] ?? '';
            $routePath = explode(' ', $pattern, 2)[1] ?? '';
            if (strtoupper($method) !== $routeMethod) {
                continue;
            }
            if ($routePath !== '' && str_starts_with($path, $routePath)) {
                $matchedRule = $rules;
                $matchedRuleKey = $routeMethod . ' ' . $routePath;
                break;
            }
        }

        if ($matchedRule === null) {
            return;
        }

        $serverVars = $_SERVER;
        foreach ($matchedRule as $rule) {
            $rateLimiter = new RateLimitMiddleware([
                (string) $matchedRuleKey => $rule,
            ]);
            try {
                $rateLimiter->handle($path, $method, $serverVars);
            } catch (RateLimitExceededException $e) {
                throw $e;
            }
        }
    }

    private function isPublicRoute(string $path): bool
    {
        if (in_array($path, ['/health', '/admin/auth/login', '/admin/auth/refresh', '/robots.txt', '/sitemap.xml'], true)) {
            return true;
        }

        return str_starts_with($path, '/api/');
    }

    private function authorize(?string $permission): void
    {
        if ($permission === null || $permission === '') {
            return;
        }

        $user = RequestContext::user();
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            throw new BusinessException('登录状态已失效', ErrorCode::UNAUTHORIZED);
        }

        if ($this->isSuperAdmin($userId)) {
            return;
        }

        $permissions = $this->rbacService->permissions($userId);
        if (in_array($permission, $permissions, true)) {
            return;
        }

        throw new BusinessException('当前账号没有操作权限', ErrorCode::ACTION_FORBIDDEN);
    }

    private function isSuperAdmin(int $userId): bool
    {
        foreach ($this->adminUserRepository->rolesForUser($userId) as $role) {
            if ((string) ($role['code'] ?? '') === 'super-admin') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>|null
     */
    private function matchParams(string $routePath, string $requestPath): ?array
    {
        $paramNames = [];
        $pattern = preg_replace_callback('/\{([^\/]+)\}/', static function (array $matches) use (&$paramNames): string {
            $paramNames[] = $matches[1];
            return '([^/]+)';
        }, $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestPath, $matches)) {
            return null;
        }

        array_shift($matches);
        $params = [];
        foreach ($paramNames as $index => $name) {
            $params[$name] = $matches[$index] ?? '';
        }

        return $params;
    }

    private function invokeHandler(string $handler, Request $request): array
    {
        [$className, $method] = explode('@', $handler, 2);
        if (!class_exists($className) || !method_exists($className, $method)) {
            throw new BusinessException('接口未实现', ErrorCode::NOT_FOUND);
        }

        $instance = new $className();
        $reflection = new \ReflectionMethod($instance, $method);
        $result = $reflection->getNumberOfParameters() > 0 ? $instance->{$method}($request) : $instance->{$method}();

        return is_array($result) ? $result : [];
    }
}
