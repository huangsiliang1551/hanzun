<?php
require_once __DIR__ . '/app/common/bootstrap/Autoloader.php';
require_once __DIR__ . '/app/common/bootstrap/EnvLoader.php';
require_once __DIR__ . '/app/common/bootstrap/helpers.php';
\app\common\bootstrap\Autoloader::register(__DIR__);
\app\common\bootstrap\EnvLoader::load(__DIR__ . '/.env');
\app\common\config\ConfigRepository::instance()->load(__DIR__ . '/config');
\app\common\database\DatabaseManager::instance()->configure(
    \app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);

$pdo = \app\common\database\DatabaseManager::instance()->connection();
if (!$pdo instanceof \PDO) { die("No DB connection\n"); }

echo "Dropping all tables...\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'hanzun_cms'");
$tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
foreach ($tables as $table) { $pdo->exec("DROP TABLE IF EXISTS `$table`"); }
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "All tables dropped.\n";

$sqlDir = __DIR__ . '/database/sql';
$schemaPaths = glob($sqlDir . '/*.sql') ?: [];
natsort($schemaPaths);
foreach ($schemaPaths as $path) {
    $file = basename($path);
    if (!file_exists($path)) { echo "WARNING: $file not found, skipping.\n"; continue; }
    echo "Importing $file...\n";
    $sql = file_get_contents($path);
    if ($pdo->exec($sql) === false) { $err = $pdo->errorInfo(); echo "ERROR: " . ($err[2] ?? 'unknown') . "\n"; exit(1); }
}
echo "Database schema imported.\n";
require __DIR__ . '/_seed-test-data.php';
echo "Seed data populated.\n";
