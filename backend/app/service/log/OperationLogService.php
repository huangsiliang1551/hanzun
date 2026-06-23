<?php

declare(strict_types=1);

namespace app\service\log;

use app\common\database\DatabaseManager;
use PDO;

final class OperationLogService
{
    public function buildPayload(
        int $operatorId,
        string $module,
        string $actionPoint,
        string $targetType,
        int|string|null $targetId = null
    ): array {
        return [
            'operator_id' => $operatorId,
            'module' => $module,
            'action_point' => $actionPoint,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordOperation(array $payload): void
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO operation_logs (request_id, operator_id, operator_name, module, action_point, target_type, target_id, request_method, request_path, request_ip, user_agent, result_code, result_message, is_success, duration_ms, created_at)
                 VALUES (:request_id, :operator_id, :operator_name, :module, :action_point, :target_type, :target_id, :request_method, :request_path, :request_ip, :user_agent, :result_code, :result_message, :is_success, :duration_ms, :created_at)'
            );
            $statement->execute([
                'request_id' => $payload['request_id'] ?? '',
                'operator_id' => $payload['operator_id'] ?? null,
                'operator_name' => $payload['operator_name'] ?? null,
                'module' => $payload['module'] ?? 'system',
                'action_point' => $payload['action_point'] ?? 'unknown',
                'target_type' => $payload['target_type'] ?? null,
                'target_id' => $payload['target_id'] ?? null,
                'request_method' => $payload['request_method'] ?? null,
                'request_path' => $payload['request_path'] ?? null,
                'request_ip' => $payload['request_ip'] ?? null,
                'user_agent' => $payload['user_agent'] ?? null,
                'result_code' => $payload['result_code'] ?? 0,
                'result_message' => $payload['result_message'] ?? null,
                'is_success' => $payload['is_success'] ?? 1,
                'duration_ms' => $payload['duration_ms'] ?? 0,
                'created_at' => $payload['created_at'] ?? date('Y-m-d H:i:s'),
            ]);

            return;
        }

        $items = $this->readOperations();
        $items[] = [
            'id' => $this->nextId($items),
            'request_id' => $payload['request_id'] ?? '',
            'operator_id' => $payload['operator_id'] ?? null,
            'operator_name' => $payload['operator_name'] ?? null,
            'module' => $payload['module'] ?? 'system',
            'action_point' => $payload['action_point'] ?? 'unknown',
            'target_type' => $payload['target_type'] ?? null,
            'target_id' => $payload['target_id'] ?? null,
            'request_method' => $payload['request_method'] ?? null,
            'request_path' => $payload['request_path'] ?? null,
            'request_ip' => $payload['request_ip'] ?? null,
            'user_agent' => $payload['user_agent'] ?? null,
            'result_code' => $payload['result_code'] ?? 0,
            'result_message' => $payload['result_message'] ?? null,
            'is_success' => $payload['is_success'] ?? 1,
            'duration_ms' => $payload['duration_ms'] ?? 0,
            'created_at' => $payload['created_at'] ?? date('Y-m-d H:i:s'),
        ];
        $this->writeOperations($items);
    }

    public function recordLoginAttempt(
        string $username,
        bool $isSuccess,
        ?int $userId = null,
        ?string $reason = null
    ): void {
        $record = [
            'user_id' => $userId,
            'username' => $username,
            'login_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'is_success' => $isSuccess ? 1 : 0,
            'reason' => $reason,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'INSERT INTO admin_login_logs (user_id, username, login_ip, user_agent, is_success, reason, created_at)
                 VALUES (:user_id, :username, :login_ip, :user_agent, :is_success, :reason, :created_at)'
            );
            $statement->execute($record);

            return;
        }

        $items = $this->readLoginLogs();
        $items[] = array_merge(['id' => $this->nextId($items)], $record);
        $this->writeLoginLogs($items);
    }

    /**
     * @param array<string, mixed>|null $target
     */
    public function recordCurrentAction(
        string $module,
        string $actionPoint,
        string $targetType,
        array|string|int|null $target = null,
        string $resultMessage = 'ok'
    ): void {
        $user = current_user();
        $request = request();

        $targetId = null;
        if (is_array($target)) {
            $targetId = $target['id'] ?? $target['slug'] ?? null;
        } elseif (is_scalar($target)) {
            $targetId = $target;
        }

        $this->recordOperation([
            'request_id' => $request?->requestId() ?? '',
            'operator_id' => $user['id'] ?? null,
            'operator_name' => $user['nickname'] ?? $user['username'] ?? null,
            'module' => $module,
            'action_point' => $actionPoint,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'request_method' => $request?->method(),
            'request_path' => $request?->path(),
            'request_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'result_code' => 0,
            'result_message' => $resultMessage,
            'is_success' => 1,
            'duration_ms' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function listOperations(int $page = 1, int $pageSize = 20, array $filters = []): array
    {
        $rows = $this->filterOperations($this->readOperations(), $filters);
        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''))
                ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
        });

        if (func_num_args() === 0) {
            return $rows;
        }

        $total = count($rows);
        $offset = max(0, ($page - 1) * $pageSize);
        $items = array_slice($rows, $offset, $pageSize);

        return [
            'items' => array_values($items),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'total_pages' => max(1, (int) ceil($total / max(1, $pageSize))),
        ];
    }

    public function listLoginLogs(int $page = 1, int $pageSize = 20, array $filters = []): array
    {
        $rows = $this->readLoginLogs();
        $rows = array_values(array_filter($rows, static function (array $item) use ($filters): bool {
            if (!empty($filters['username']) && (string) ($item['username'] ?? '') !== (string) $filters['username']) {
                return false;
            }
            if (!empty($filters['date_from']) && (string) ($item['created_at'] ?? '') < $filters['date_from'] . ' 00:00:00') {
                return false;
            }
            if (!empty($filters['date_to']) && (string) ($item['created_at'] ?? '') > $filters['date_to'] . ' 23:59:59') {
                return false;
            }

            return true;
        }));
        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''))
                ?: ((int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0));
        });

        if (func_num_args() === 0) {
            return $rows;
        }

        $total = count($rows);
        $offset = max(0, ($page - 1) * $pageSize);
        $items = array_slice($rows, $offset, $pageSize);

        return [
            'items' => array_values($items),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'total_pages' => max(1, (int) ceil($total / max(1, $pageSize))),
        ];
    }

    private function preferRuntimeStorage(): bool
    {
        return (string) env('PREFER_RUNTIME_STORAGE', '0') === '1'
            || (PHP_SAPI === 'cli' && (is_file($this->operationsPath()) || is_file($this->loginLogsPath())));
    }

    private function operationsPath(): string
    {
        return dirname(__DIR__, 3) . '/runtime/storage/operation_logs.json';
    }

    private function loginLogsPath(): string
    {
        return dirname(__DIR__, 3) . '/runtime/storage/login_logs.json';
    }

    private function readOperations(): array
    {
        return $this->readJsonList($this->operationsPath());
    }

    private function writeOperations(array $items): void
    {
        $this->writeJsonList($this->operationsPath(), $items);
    }

    private function readLoginLogs(): array
    {
        return $this->readJsonList($this->loginLogsPath());
    }

    private function writeLoginLogs(array $items): void
    {
        $this->writeJsonList($this->loginLogsPath(), $items);
    }

    private function readJsonList(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    private function writeJsonList(string $path, array $items): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function nextId(array $items): int
    {
        return array_reduce($items, static function (int $carry, array $item): int {
            return max($carry, (int) ($item['id'] ?? 0));
        }, 0) + 1;
    }

    private function filterOperations(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $item) use ($filters): bool {
            if (!empty($filters['module']) && (string) ($item['module'] ?? '') !== (string) $filters['module']) {
                return false;
            }
            if (!empty($filters['action_point']) && (string) ($item['action_point'] ?? '') !== (string) $filters['action_point']) {
                return false;
            }
            if (!empty($filters['operator_name']) && (string) ($item['operator_name'] ?? '') !== (string) $filters['operator_name']) {
                return false;
            }
            if (!empty($filters['date_from']) && (string) ($item['created_at'] ?? '') < $filters['date_from'] . ' 00:00:00') {
                return false;
            }
            if (!empty($filters['date_to']) && (string) ($item['created_at'] ?? '') > $filters['date_to'] . ' 23:59:59') {
                return false;
            }

            return true;
        }));
    }
}
