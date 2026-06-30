<?php

declare(strict_types=1);

$backendRoot = dirname(__DIR__);

require_once $backendRoot . '/app/common/bootstrap/Autoloader.php';
require_once $backendRoot . '/app/common/bootstrap/EnvLoader.php';
require_once $backendRoot . '/app/common/bootstrap/helpers.php';
require_once $backendRoot . '/app/common/config/ConfigRepository.php';
require_once $backendRoot . '/app/common/database/DatabaseManager.php';

\app\common\bootstrap\Autoloader::register($backendRoot);
\app\common\bootstrap\EnvLoader::load($backendRoot . '/.env');
\app\common\config\ConfigRepository::instance()->load($backendRoot . '/config');
\app\common\database\DatabaseManager::instance()->configure(
    \app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);

$pdo = \app\common\database\DatabaseManager::instance()->connection();
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "Database unavailable for admin menu catalog sync validation.\n");
    exit(1);
}

$backupActions = $pdo->query('SELECT id, name, code, description FROM admin_action_points ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
$backupMenus = $pdo->query('SELECT id, parent_id, name, path, route_name, icon, menu_type, sort, is_visible, status FROM admin_menus ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

try {
    $pdo->exec("UPDATE admin_action_points SET code = 'broken.news.view', name = '查看新闻', description = 'broken fixture' WHERE id = 63");
    $pdo->exec("DELETE FROM admin_action_points WHERE id = 71");
    $pdo->exec("DELETE FROM admin_menus WHERE id IN (12,13)");

    $repository = new \app\repository\MenuRepository();
    $menus = $repository->allMenus();
    $actions = $repository->actionPoints();

    $menuRouteNames = array_values(array_map(static fn (array $item): string => (string) ($item['route_name'] ?? ''), $menus));
    $actionCodes = array_values(array_map(static fn (array $item): string => (string) ($item['code'] ?? ''), $actions));
    $actionById = [];
    foreach ($actions as $action) {
        $actionById[(int) ($action['id'] ?? 0)] = (string) ($action['code'] ?? '');
    }

    $issues = [];
    if (!in_array('news', $menuRouteNames, true)) {
        $issues[] = 'MenuRepository::allMenus must restore the news admin menu';
    }
    if (!in_array('cases', $menuRouteNames, true)) {
        $issues[] = 'MenuRepository::allMenus must restore the cases admin menu';
    }
    if (!in_array('news.view', $actionCodes, true)) {
        $issues[] = 'MenuRepository::actionPoints must restore news.view';
    }
    if (!in_array('case.publish', $actionCodes, true)) {
        $issues[] = 'MenuRepository::actionPoints must restore case.publish';
    }
    if (($actionById[63] ?? '') !== 'news.view') {
        $issues[] = 'MenuRepository::actionPoints must correct id=63 back to news.view';
    }

    if ($issues !== []) {
        fwrite(STDERR, "Admin menu catalog sync validation failed:\n");
        foreach ($issues as $issue) {
            fwrite(STDERR, " - {$issue}\n");
        }
        exit(1);
    }

    fwrite(STDOUT, "Admin menu catalog sync validation passed.\n");
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DELETE FROM admin_role_action_points');
    $pdo->exec('DELETE FROM admin_role_menus');
    $pdo->exec('DELETE FROM admin_action_points');
    $pdo->exec('DELETE FROM admin_menus');

    $insertAction = $pdo->prepare('INSERT INTO admin_action_points (id, name, code, description) VALUES (:id, :name, :code, :description)');
    foreach ($backupActions as $row) {
        $insertAction->execute([
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'code' => (string) $row['code'],
            'description' => $row['description'],
        ]);
    }

    $insertMenu = $pdo->prepare('INSERT INTO admin_menus (id, parent_id, name, path, route_name, icon, menu_type, sort, is_visible, status) VALUES (:id, :parent_id, :name, :path, :route_name, :icon, :menu_type, :sort, :is_visible, :status)');
    foreach ($backupMenus as $row) {
        $insertMenu->execute([
            'id' => (int) $row['id'],
            'parent_id' => (int) $row['parent_id'],
            'name' => (string) $row['name'],
            'path' => (string) $row['path'],
            'route_name' => (string) $row['route_name'],
            'icon' => (string) $row['icon'],
            'menu_type' => (string) $row['menu_type'],
            'sort' => (int) $row['sort'],
            'is_visible' => (int) $row['is_visible'],
            'status' => (int) $row['status'],
        ]);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}
