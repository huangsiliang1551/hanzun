<?php

declare(strict_types=1);

namespace app\service\auth;

use app\common\auth\SimpleToken;
use app\common\database\DatabaseManager;
use app\common\storage\RuntimeStorage;
use app\repository\AdminUserRepository;
use PDO;

final class SessionService
{
    public function issueTokens(array $user): array
    {
        $sessionCode = bin2hex(random_bytes(12));
        $accessExpiresAt = time() + (int) config('auth.access_ttl', 7200);
        $refreshExpiresAt = time() + (int) config('auth.refresh_ttl', 2592000);
        $secret = $this->tokenSecret();

        $accessPayload = [
            'type' => 'access',
            'user_id' => $user['id'],
            'username' => $user['username'],
            'session_code' => $sessionCode,
            'exp' => $accessExpiresAt,
        ];
        $refreshPayload = [
            'type' => 'refresh',
            'user_id' => $user['id'],
            'username' => $user['username'],
            'session_code' => $sessionCode,
            'exp' => $refreshExpiresAt,
        ];

        $accessToken = SimpleToken::encode($accessPayload, $secret);
        $refreshToken = SimpleToken::encode($refreshPayload, $secret);

        if (RuntimeStorage::enabled()) {
            $this->sessionStore()->transaction(function (array $sessions) use ($accessExpiresAt, $refreshExpiresAt, $refreshToken, $sessionCode, $user): array {
                $sessions[$sessionCode] = [
                    'session_code' => $sessionCode,
                    'user_id' => (int) ($user['id'] ?? 0),
                    'username' => (string) ($user['username'] ?? ''),
                    'nickname' => (string) ($user['nickname'] ?? $user['username'] ?? ''),
                    'status' => 'active',
                    'refresh_token_hash' => hash('sha256', $refreshToken),
                    'access_expires_at' => $accessExpiresAt,
                    'refresh_expires_at' => $refreshExpiresAt,
                    'updated_at' => date(DATE_ATOM),
                ];

                return $sessions;
            });
        } else {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO admin_login_sessions (session_code, user_id, refresh_token_hash, expired_at, created_at)
                 VALUES (:session_code, :user_id, :refresh_token_hash, :expired_at, NOW())'
            );
            $statement->execute([
                'session_code' => $sessionCode,
                'user_id' => $user['id'],
                'refresh_token_hash' => hash('sha256', $refreshToken),
                'expired_at' => date('Y-m-d H:i:s', $refreshExpiresAt),
            ]);
        }
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => (int) config('auth.access_ttl', 7200),
            'session_code' => $sessionCode,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validateAccessToken(string $token): ?array
    {
        $payload = SimpleToken::decode($token, $this->tokenSecret());
        if (!is_array($payload) || ($payload['type'] ?? null) !== 'access') {
            return null;
        }

        if (($payload['exp'] ?? 0) < time()) {
            return null;
        }

        if (RuntimeStorage::enabled()) {
            $sessionCode = (string) ($payload['session_code'] ?? '');
            $sessions = $this->sessionStore()->all();
            $session = $sessions[$sessionCode] ?? null;
            if (!is_array($session) || (string) ($session['status'] ?? '') !== 'active') {
                return null;
            }
            if ((int) ($session['access_expires_at'] ?? 0) < time()) {
                return null;
            }

            $user = $this->userRepository()->findById((int) ($session['user_id'] ?? 0));
            if (!is_array($user) || (int) ($user['status'] ?? 0) !== 1) {
                return null;
            }

            return [
                'id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'nickname' => (string) ($user['nickname'] ?? $user['username']),
                'session_code' => $sessionCode,
            ];
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT s.session_code, s.user_id, u.username, u.nickname, u.status
                 FROM admin_login_sessions s
                 JOIN admin_users u ON u.id = s.user_id
                 WHERE s.session_code = :session_code
                 AND s.revoked_at IS NULL
                 AND s.expired_at > NOW()
                 LIMIT 1'
            );
            $statement->execute(['session_code' => $payload['session_code'] ?? '']);
            $session = $statement->fetch();

            if (is_array($session) && (int) ($session['status'] ?? 0) === 1) {
                return [
                    'id' => (int) $session['user_id'],
                    'username' => (string) $session['username'],
                    'nickname' => (string) $session['nickname'],
                    'session_code' => (string) $session['session_code'],
                ];
            }
        }

        return null;
    }

    public function refresh(string $refreshToken): ?array
    {
        $payload = SimpleToken::decode($refreshToken, $this->tokenSecret());
        if (!is_array($payload) || ($payload['type'] ?? null) !== 'refresh') {
            return null;
        }

        if (($payload['exp'] ?? 0) < time()) {
            return null;
        }

        if (RuntimeStorage::enabled()) {
            $sessionCode = (string) ($payload['session_code'] ?? '');
            $now = time();
            $userId = 0;
            $rotationAllowed = false;

            $this->sessionStore()->transaction(function (array $sessions) use ($now, $refreshToken, $sessionCode, &$rotationAllowed, &$userId): array {
                $session = $sessions[$sessionCode] ?? null;
                if (!is_array($session) || (string) ($session['status'] ?? '') !== 'active') {
                    return $sessions;
                }

                if ((int) ($session['refresh_expires_at'] ?? 0) < $now) {
                    return $sessions;
                }

                if (!hash_equals((string) ($session['refresh_token_hash'] ?? ''), hash('sha256', $refreshToken))) {
                    return $sessions;
                }

                $session['status'] = 'revoked';
                $session['revoked_at'] = date(DATE_ATOM);
                $session['updated_at'] = date(DATE_ATOM);
                $sessions[$sessionCode] = $session;
                $rotationAllowed = true;
                $userId = (int) ($session['user_id'] ?? 0);

                return $sessions;
            });

            if (!$rotationAllowed || $userId <= 0) {
                return null;
            }

            $user = $this->userRepository()->findById($userId);
            if (!is_array($user) || (int) ($user['status'] ?? 0) !== 1) {
                return null;
            }

            return $this->issueTokens([
                'id' => (int) $user['id'],
                'username' => (string) $user['username'],
                'nickname' => (string) ($user['nickname'] ?? $user['username']),
            ]);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            try {
                $pdo->beginTransaction();

                $statement = $pdo->prepare(
                    'SELECT s.session_code, s.user_id, u.username, u.nickname, u.status
                     FROM admin_login_sessions s
                     JOIN admin_users u ON u.id = s.user_id
                     WHERE s.session_code = :session_code
                       AND s.refresh_token_hash = :token_hash
                       AND s.revoked_at IS NULL
                       AND s.expired_at > NOW()
                     LIMIT 1
                     FOR UPDATE'
                );
                $statement->execute([
                    'session_code' => (string) ($payload['session_code'] ?? ''),
                    'token_hash' => hash('sha256', $refreshToken),
                ]);
                $session = $statement->fetch();

                if (!is_array($session) || (int) ($session['status'] ?? 0) !== 1) {
                    $pdo->rollBack();
                    return null;
                }

                $revoke = $pdo->prepare(
                    'UPDATE admin_login_sessions
                     SET revoked_at = NOW()
                     WHERE session_code = :session_code
                       AND refresh_token_hash = :token_hash
                       AND revoked_at IS NULL
                       AND expired_at > NOW()'
                );
                $revoke->execute([
                    'session_code' => (string) ($session['session_code'] ?? ''),
                    'token_hash' => hash('sha256', $refreshToken),
                ]);

                if ($revoke->rowCount() !== 1) {
                    $pdo->rollBack();
                    return null;
                }

                $pdo->commit();

                return $this->issueTokens([
                    'id' => (int) $session['user_id'],
                    'username' => (string) $session['username'],
                    'nickname' => (string) $session['nickname'],
                ]);
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                return null;
            }
        }

        return null;
    }

    public function revokeByAccessToken(string $token): void
    {
        $payload = SimpleToken::decode($token, $this->tokenSecret());
        if (!is_array($payload)) {
            return;
        }

        if (RuntimeStorage::enabled()) {
            $sessionCode = (string) ($payload['session_code'] ?? '');
            $this->sessionStore()->transaction(function (array $sessions) use ($sessionCode): array {
                if (isset($sessions[$sessionCode]) && is_array($sessions[$sessionCode])) {
                    $sessions[$sessionCode]['status'] = 'revoked';
                    $sessions[$sessionCode]['revoked_at'] = date(DATE_ATOM);
                    $sessions[$sessionCode]['updated_at'] = date(DATE_ATOM);
                }

                return $sessions;
            });
            return;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'UPDATE admin_login_sessions SET revoked_at = NOW()
                 WHERE session_code = :session_code AND revoked_at IS NULL'
            );
            $statement->execute(['session_code' => $payload['session_code'] ?? '']);
        }
    }

    public function revokeAllForUser(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        if (RuntimeStorage::enabled()) {
            $this->sessionStore()->transaction(function (array $sessions) use ($userId): array {
                foreach ($sessions as $sessionCode => $session) {
                    if (!is_array($session) || (int) ($session['user_id'] ?? 0) !== $userId) {
                        continue;
                    }

                    $session['status'] = 'revoked';
                    $session['revoked_at'] = date(DATE_ATOM);
                    $session['updated_at'] = date(DATE_ATOM);
                    $sessions[$sessionCode] = $session;
                }

                return $sessions;
            });
            return;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'UPDATE admin_login_sessions
                 SET revoked_at = NOW()
                 WHERE user_id = :user_id AND revoked_at IS NULL'
            );
            $statement->execute([
                'user_id' => $userId,
            ]);
        }
    }

    private function tokenSecret(): string
    {
        $secret = trim((string) (config('auth.jwt_secret', '') ?: env('AUTH_JWT_SECRET', '')));
        $invalidSecrets = [
            '',
            'replace_this_secret',
            'CHANGE_ME_USE_64_RANDOM_HEX_CHARS',
            '6eec8b34de2b3ef0a7b64c11bac4fc625caaf4d8abd246a3ecac98c1871cd3e4',
        ];

        if (in_array($secret, $invalidSecrets, true) || strlen($secret) < 32) {
            throw new \RuntimeException('AUTH_JWT_SECRET must be set to a unique production secret.');
        }

        return $secret;
    }

    private function sessionStore(): \app\common\storage\JsonFileStore
    {
        return RuntimeStorage::store('admin_sessions.json');
    }

    private function userRepository(): AdminUserRepository
    {
        return new AdminUserRepository();
    }
}
