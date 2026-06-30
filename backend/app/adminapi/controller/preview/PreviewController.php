<?php

declare(strict_types=1);

namespace app\adminapi\controller\preview;

use app\adminapi\controller\BaseAdminController;
use app\common\database\DatabaseManager;
use app\common\http\Request;
use PDO;

class PreviewController extends BaseAdminController
{
    /**
     * POST /admin/preview
     * Accept entity_type + payload, generate a preview token, store data, return token.
     */
    public function store(Request $request): array
    {
        $entityType = (string) $request->input('entity_type', '');
        $payload = $request->input('payload');

        if ($entityType === '' || $payload === null || !is_array($payload)) {
            return $this->error(400, '参数错误');
        }

        $token = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour TTL

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $this->cleanExpiredTokens($pdo);

            $statement = $pdo->prepare(
                'INSERT INTO preview_tokens (token, entity_type, payload, created_at, expires_at)
                 VALUES (:token, :entity_type, :payload, NOW(), :expires_at)'
            );
            $statement->execute([
                'token' => $token,
                'entity_type' => $entityType,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'expires_at' => $expiresAt,
            ]);
        }

        return $this->success(['token' => $token], [], '预览链接已生成');
    }

    /**
     * GET /admin/preview/{token}
     * Retrieve stored preview data by token.
     */
    public function show(Request $request): array
    {
        $token = (string) $request->routeParam('token', '');

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT token, entity_type, payload, created_at, expires_at
                 FROM preview_tokens
                 WHERE token = :token
                 LIMIT 1'
            );
            $statement->execute(['token' => $token]);
            $row = $statement->fetch();

            if (is_array($row)) {
                $expiresAt = (string) ($row['expires_at'] ?? '1970-01-01');
                if (strtotime($expiresAt) < time()) {
                    return $this->error(410, '预览已过期');
                }

                return $this->success([
                    'entity_type' => $row['entity_type'] ?? '',
                    'payload' => json_decode((string) ($row['payload'] ?? '{}'), true) ?? [],
                    'created_at' => $row['created_at'] ?? '',
                ]);
            }
        }

        return $this->error(404, '预览不存在');
    }

    private function cleanExpiredTokens(PDO $pdo): void
    {
        $pdo->exec('DELETE FROM preview_tokens WHERE expires_at < NOW()');
    }
}
