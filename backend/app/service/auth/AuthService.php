<?php

declare(strict_types=1);

namespace app\service\auth;

use app\common\auth\BearerToken;
use app\common\http\ClientIp;
use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\AdminUserRepository;
use app\service\log\OperationLogService;
use app\service\rbac\RbacService;

final class AuthService
{
    private static ?bool $supportsLoginKey = null;

    public function __construct(
        private readonly AdminUserRepository $adminUserRepository = new AdminUserRepository(),
        private readonly SessionService $sessionService = new SessionService(),
        private readonly RbacService $rbacService = new RbacService(),
        private readonly OperationLogService $operationLogService = new OperationLogService()
    ) {
    }

    public function login(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '') {
            throw new BusinessException('Username is required.', ErrorCode::INVALID_PARAMS);
        }

        $this->checkLoginLockout($username);

        $user = $this->adminUserRepository->findByUsername($username);
        if ($user === null) {
            $this->recordLoginFailure($username);
            $this->operationLogService->recordLoginAttempt($username, false, null, 'invalid credentials');
            throw new BusinessException('Invalid credentials.', ErrorCode::UNAUTHORIZED);
        }

        if (!$this->adminUserRepository->verifyPassword($user, $password)) {
            $this->recordLoginFailure($username);
            $this->operationLogService->recordLoginAttempt($username, false, (int) ($user['id'] ?? 0), 'invalid credentials');
            throw new BusinessException('Invalid credentials.', ErrorCode::UNAUTHORIZED);
        }

        if ((int) ($user['status'] ?? 0) !== 1) {
            $this->recordLoginFailure($username);
            $this->operationLogService->recordLoginAttempt($username, false, (int) ($user['id'] ?? 0), 'user disabled');
            throw new BusinessException('User is disabled.', ErrorCode::USER_DISABLED);
        }

        $tokens = $this->sessionService->issueTokens($user);
        $this->operationLogService->recordLoginAttempt($username, true, (int) $user['id']);
        $this->operationLogService->recordOperation([
            'request_id' => request()?->requestId() ?? '',
            'operator_id' => $user['id'],
            'operator_name' => $user['nickname'] ?? $user['username'],
            'module' => 'auth',
            'action_point' => 'auth.login',
            'target_type' => 'admin_user',
            'target_id' => $user['id'],
            'request_method' => request()?->method(),
            'request_path' => request()?->path(),
            'request_ip' => $this->requestIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'result_code' => 0,
            'result_message' => 'login success',
            'is_success' => 1,
            'duration_ms' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            ...$tokens,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'nickname' => $user['nickname'] ?? $user['username'],
            ],
            'roles' => $this->adminUserRepository->rolesForUser((int) $user['id']),
            'permissions' => $this->rbacService->permissions((int) $user['id']),
            'menus' => $this->rbacService->menuTree((int) $user['id']),
        ];
    }

    public function refresh(string $refreshToken): array
    {
        $tokens = $this->sessionService->refresh($refreshToken);
        if ($tokens === null) {
            throw new BusinessException('Refresh token is invalid.', ErrorCode::INVALID_REFRESH_TOKEN);
        }

        return $tokens;
    }

    public function logout(string $authorization): void
    {
        $token = BearerToken::extract($authorization);
        $user = current_user();
        $this->sessionService->revokeByAccessToken($token);

        if ($user !== null) {
            $this->operationLogService->recordOperation([
                'request_id' => request()?->requestId() ?? '',
                'operator_id' => $user['id'] ?? null,
                'operator_name' => $user['nickname'] ?? $user['username'] ?? null,
                'module' => 'auth',
                'action_point' => 'auth.logout',
                'target_type' => 'admin_user',
                'target_id' => $user['id'] ?? null,
                'request_method' => request()?->method(),
                'request_path' => request()?->path(),
                'request_ip' => $this->requestIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'result_code' => 0,
                'result_message' => 'logout success',
                'is_success' => 1,
                'duration_ms' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function currentUser(): array
    {
        $user = current_user();
        if ($user === null) {
            throw new BusinessException('Login session has expired.', ErrorCode::UNAUTHORIZED);
        }

        return [
            'user' => $user,
            'roles' => $this->adminUserRepository->rolesForUser((int) ($user['id'] ?? 0)),
            'permissions' => $this->rbacService->permissions((int) ($user['id'] ?? 0)),
            'menus' => $this->rbacService->menuTree((int) ($user['id'] ?? 0)),
        ];
    }

    private function checkLoginLockout(string $username): void
    {
        $pdo = \app\common\database\DatabaseManager::instance()->connection();
        if (!$pdo instanceof \PDO) {
            return;
        }

        $loginKey = $this->loginFailureKey($username);
        $windowSeconds = $this->configInt('auth.login_window_seconds', 900);
        $maxAttempts = $this->configInt('auth.login_max_attempts', 5);
        $lockSeconds = $this->configInt('auth.login_lock_seconds', 900);
        $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);

        if ($this->supportsLoginKeyColumn($pdo)) {
            $countStatement = $pdo->prepare(
                'SELECT COUNT(*) AS failure_count
                 FROM admin_login_logs
                 WHERE login_key = :login_key AND is_success = 0 AND created_at > :cutoff'
            );
            $countStatement->execute([
                'login_key' => $loginKey,
                'cutoff' => $cutoff,
            ]);
        } else {
            $ip = $this->requestIp();
            $countStatement = $pdo->prepare(
                'SELECT COUNT(*) AS failure_count
                 FROM admin_login_logs
                 WHERE username = :username AND login_ip = :login_ip AND is_success = 0 AND created_at > :cutoff'
            );
            $countStatement->execute([
                'username' => $username,
                'login_ip' => $ip,
                'cutoff' => $cutoff,
            ]);
        }
        $countRow = $countStatement->fetch();

        if (!is_array($countRow) || (int) ($countRow['failure_count'] ?? 0) < $maxAttempts) {
            return;
        }

        if ($this->supportsLoginKeyColumn($pdo)) {
            $lastStatement = $pdo->prepare(
                'SELECT created_at
                 FROM admin_login_logs
                 WHERE login_key = :login_key AND is_success = 0
                 ORDER BY created_at DESC
                 LIMIT 1'
            );
            $lastStatement->execute(['login_key' => $loginKey]);
        } else {
            $ip = $this->requestIp();
            $lastStatement = $pdo->prepare(
                'SELECT created_at
                 FROM admin_login_logs
                 WHERE username = :username AND login_ip = :login_ip AND is_success = 0
                 ORDER BY created_at DESC
                 LIMIT 1'
            );
            $lastStatement->execute([
                'username' => $username,
                'login_ip' => $ip,
            ]);
        }
        $lastRow = $lastStatement->fetch();

        if (!is_array($lastRow) || empty($lastRow['created_at'])) {
            return;
        }

        $lastTime = strtotime((string) $lastRow['created_at']);
        $elapsed = time() - (int) $lastTime;
        $remaining = max(0, $lockSeconds - $elapsed);
        if ($remaining > 0) {
            throw new BusinessException('Too many failed login attempts. Please try again later.', 429);
        }
    }

    private function recordLoginFailure(string $username): void
    {
        $pdo = \app\common\database\DatabaseManager::instance()->connection();
        if (!$pdo instanceof \PDO) {
            return;
        }

        if ($this->supportsLoginKeyColumn($pdo)) {
            $statement = $pdo->prepare(
                'INSERT INTO admin_login_logs (username, login_key, login_ip, is_success, reason, created_at)
                 VALUES (:username, :login_key, :ip, 0, :reason, NOW())'
            );
            $statement->execute([
                'username' => $username,
                'login_key' => $this->loginFailureKey($username),
                'ip' => $this->requestIp(),
                'reason' => 'login_failure',
            ]);
            return;
        }

        $statement = $pdo->prepare(
            'INSERT INTO admin_login_logs (username, login_ip, is_success, reason, created_at)
             VALUES (:username, :ip, 0, :reason, NOW())'
        );
        $statement->execute([
            'username' => $username,
            'ip' => $this->requestIp(),
            'reason' => 'login_failure',
        ]);
    }

    private function supportsLoginKeyColumn(\PDO $pdo): bool
    {
        if (self::$supportsLoginKey !== null) {
            return self::$supportsLoginKey;
        }

        $statement = $pdo->query("SHOW COLUMNS FROM `admin_login_logs` LIKE 'login_key'");
        if ($statement === false) {
            self::$supportsLoginKey = false;
            return false;
        }

        self::$supportsLoginKey = $statement->fetchColumn() !== false;
        return self::$supportsLoginKey;
    }

    private function loginFailureKey(string $username): string
    {
        return strtolower(trim($username)) . '|' . $this->requestIp();
    }

    private function configInt(string $key, int $default): int
    {
        $value = (int) config($key, $default);
        if ($value < 1) {
            return $default;
        }

        return $value;
    }

    private function requestIp(): string
    {
        return ClientIp::resolve();
    }

}
