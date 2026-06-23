<?php

declare(strict_types=1);

namespace app\service\system;

use app\common\database\DatabaseManager;

final class HealthService
{
    public function status(): array
    {
        $dbConfigured = DatabaseManager::instance()->isConfigured();
        $dbConnected = DatabaseManager::instance()->connection() !== null;

        return [
            'service' => 'hanzun-cms-backend',
            'status' => $dbConfigured && !$dbConnected ? 'degraded' : 'ok',
            'time' => date('c'),
        ];
    }
}
