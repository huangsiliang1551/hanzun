<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);

require_once $backendRoot . '/app/common/bootstrap/Autoloader.php';
require_once $backendRoot . '/app/common/bootstrap/EnvLoader.php';
require_once $backendRoot . '/app/common/bootstrap/helpers.php';

\app\common\bootstrap\Autoloader::register($backendRoot);
\app\common\bootstrap\EnvLoader::load($backendRoot . '/.env');
\app\common\config\ConfigRepository::instance()->load($backendRoot . '/config');
\app\common\database\DatabaseManager::instance()->configure(
    \app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);

$pdo = \app\common\database\DatabaseManager::instance()->connection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "System settings DB preference validation failed:\n - database connection unavailable\n");
    exit(1);
}

$runtimePath = $backendRoot . '/runtime/storage/system_settings.json';
$runtimeExists = is_file($runtimePath);
$runtimeOriginal = $runtimeExists ? (string) file_get_contents($runtimePath) : null;

$statement = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_group = :group AND setting_key = :key LIMIT 1');
$statement->execute([
    'group' => 'site',
    'key' => 'config',
]);
$row = $statement->fetch(PDO::FETCH_ASSOC);
$dbOriginalJson = (string) ($row['setting_value'] ?? '{}');
$dbOriginal = json_decode($dbOriginalJson, true);
if (!is_array($dbOriginal)) {
    $dbOriginal = [];
}

$dbSentinel = array_replace($dbOriginal, [
    'company_name' => 'DB Preferred Brand',
    'company_subtitle' => 'DB Preferred Subtitle',
]);

$runtimePayload = [];
if ($runtimeExists && $runtimeOriginal !== null && trim($runtimeOriginal) !== '') {
    $decoded = json_decode($runtimeOriginal, true);
    if (is_array($decoded)) {
        $runtimePayload = $decoded;
    }
}
$runtimePayload['site']['config'] = array_replace(
    is_array($runtimePayload['site']['config'] ?? null) ? $runtimePayload['site']['config'] : [],
    [
        'company_name' => 'Runtime Shadow Brand',
        'company_subtitle' => 'Runtime Shadow Subtitle',
    ]
);

$issues = [];

try {
    $upsert = $pdo->prepare(
        'INSERT INTO system_settings (setting_group, setting_key, setting_value, updated_at)
         VALUES (:group, :key, :value, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
    );
    $upsert->execute([
        'group' => 'site',
        'key' => 'config',
        'value' => json_encode($dbSentinel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    if (!is_dir(dirname($runtimePath))) {
        mkdir(dirname($runtimePath), 0777, true);
    }
    file_put_contents($runtimePath, json_encode($runtimePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    $repository = new \app\repository\SystemSettingRepository();
    $resolved = $repository->siteConfig();

    if ((string) ($resolved['company_name'] ?? '') !== 'DB Preferred Brand') {
        $issues[] = 'siteConfig must prefer database company_name when DB is available';
    }

    if ((string) ($resolved['company_subtitle'] ?? '') !== 'DB Preferred Subtitle') {
        $issues[] = 'siteConfig must prefer database company_subtitle when DB is available';
    }

    if ((string) ($resolved['company_name'] ?? '') === 'Runtime Shadow Brand') {
        $issues[] = 'siteConfig must not be overridden by runtime storage shadow values';
    }
} finally {
    $restore = $pdo->prepare(
        'INSERT INTO system_settings (setting_group, setting_key, setting_value, updated_at)
         VALUES (:group, :key, :value, NOW())
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
    );
    $restore->execute([
        'group' => 'site',
        'key' => 'config',
        'value' => json_encode($dbOriginal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    if ($runtimeExists) {
        file_put_contents($runtimePath, (string) $runtimeOriginal);
    } elseif (is_file($runtimePath)) {
        unlink($runtimePath);
    }
}

if ($issues !== []) {
    fwrite(STDERR, "System settings DB preference validation failed:\n");
    foreach ($issues as $issue) {
        fwrite(STDERR, " - {$issue}\n");
    }
    exit(1);
}

fwrite(STDOUT, "System settings DB preference validation passed.\n");
