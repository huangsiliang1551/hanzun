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
$tables = ['inquiries','chat_sessions','chat_messages','lead_snapshots','visitor_events','media_assets','media_folders','translation_jobs','seo_jobs','seo_routes','seo_404_logs','content_items'];
foreach ($tables as $table) {
  echo "TABLE:$table\n";
  $stmt = $pdo->query("SHOW INDEX FROM `$table`");
  $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
  foreach ($rows as $row) {
    echo ($row['Key_name'] ?? '') . '|' . ($row['Column_name'] ?? '') . '|' . ($row['Non_unique'] ?? '') . "\n";
  }
}
