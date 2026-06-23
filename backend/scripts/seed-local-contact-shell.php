<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);

putenv('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK=1');
$_ENV['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';
$_SERVER['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';

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
    fwrite(STDERR, "contact shell seed failed: database connection unavailable\n");
    exit(1);
}

$fieldTypeRows = $pdo->query('SELECT id, field_key FROM contact_field_types')->fetchAll(PDO::FETCH_ASSOC);
$fieldTypeMap = [];
foreach ($fieldTypeRows as $row) {
    $fieldKey = strtolower(trim((string) ($row['field_key'] ?? '')));
    $fieldId = (int) ($row['id'] ?? 0);
    if ($fieldKey !== '' && $fieldId > 0) {
        $fieldTypeMap[$fieldKey] = $fieldId;
    }
}

$requiredItems = [
    [
        'field_key' => 'linkedin',
        'label_zh' => 'LinkedIn 页面',
        'value' => 'https://www.linkedin.com/company/hanzun',
        'description_zh' => '企业 LinkedIn 品牌主页',
        'display_scope' => 'footer',
        'sort' => 95,
    ],
    [
        'field_key' => 'youtube',
        'label_zh' => 'YouTube 频道',
        'value' => 'https://www.youtube.com/@hanzun',
        'description_zh' => '企业视频与案例内容频道',
        'display_scope' => 'footer',
        'sort' => 94,
    ],
    [
        'field_key' => 'line',
        'label_zh' => 'LINE 联系',
        'value' => 'https://line.me/R/ti/p/~hanzun-machinery',
        'description_zh' => '适用于 LINE 渠道联系',
        'display_scope' => 'footer',
        'sort' => 93,
    ],
];

$select = $pdo->prepare(
    'SELECT ci.id
     FROM contact_items ci
     INNER JOIN contact_field_types cft ON cft.id = ci.field_type_id
     WHERE cft.field_key = :field_key AND ci.display_scope = :display_scope
     LIMIT 1'
);
$update = $pdo->prepare(
    'UPDATE contact_items
     SET label_zh = :label_zh,
         value = :value,
         description_zh = :description_zh,
         sort = :sort,
         is_enabled = 1
     WHERE id = :id'
);
$insert = $pdo->prepare(
    'INSERT INTO contact_items (field_type_id, label_zh, value, description_zh, display_scope, sort, is_enabled)
     VALUES (:field_type_id, :label_zh, :value, :description_zh, :display_scope, :sort, 1)'
);

$result = [];
foreach ($requiredItems as $item) {
    $fieldKey = (string) $item['field_key'];
    $fieldTypeId = (int) ($fieldTypeMap[$fieldKey] ?? 0);
    if ($fieldTypeId <= 0) {
        fwrite(STDERR, "contact shell seed failed: missing field type {$fieldKey}\n");
        exit(1);
    }

    $select->execute([
        'field_key' => $fieldKey,
        'display_scope' => (string) $item['display_scope'],
    ]);
    $existingId = (int) ($select->fetchColumn() ?: 0);

    if ($existingId > 0) {
        $update->execute([
            'id' => $existingId,
            'label_zh' => (string) $item['label_zh'],
            'value' => (string) $item['value'],
            'description_zh' => (string) $item['description_zh'],
            'sort' => (int) $item['sort'],
        ]);
        $result[$fieldKey] = 'updated';
        continue;
    }

    $insert->execute([
        'field_type_id' => $fieldTypeId,
        'label_zh' => (string) $item['label_zh'],
        'value' => (string) $item['value'],
        'description_zh' => (string) $item['description_zh'],
        'display_scope' => (string) $item['display_scope'],
        'sort' => (int) $item['sort'],
    ]);
    $result[$fieldKey] = 'inserted';
}

fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL);
