<?php
$basePath = 'C:/Users/ZhuanZ1/my-project/涵尊实业有限公司/backend';
require_once $basePath . '/app/common/bootstrap/Autoloader.php';
require_once $basePath . '/app/common/bootstrap/EnvLoader.php';
require_once $basePath . '/app/common/bootstrap/helpers.php';
app\common\bootstrap\Autoloader::register($basePath);
app\common\bootstrap\EnvLoader::load($basePath . '/.env');
app\common\config\ConfigRepository::instance()->load($basePath . '/config');
app\common\database\DatabaseManager::instance()->configure(
    app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);
$pdo = app\common\database\DatabaseManager::instance()->connection();
foreach (['inquiries','chat_sessions','lead_snapshots'] as $table) {
  $stmt = $pdo->query('SHOW COLUMNS FROM ' . $table);
  $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
  echo "TABLE:$table\n";
  foreach ($rows as $row) {
    echo $row['Field'] . '|' . $row['Type'] . "\n";
  }
}
