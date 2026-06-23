<?php
/**
 * 测试引导文件 — 初始化自动加载、环境变量、配置和数据库连接
 */
$basePath = getcwd();

require_once $basePath . '/app/common/bootstrap/Autoloader.php';
require_once $basePath . '/app/common/bootstrap/EnvLoader.php';
require_once $basePath . '/app/common/bootstrap/helpers.php';

\app\common\bootstrap\Autoloader::register($basePath);
\app\common\bootstrap\EnvLoader::load($basePath . '/.env');

\app\common\config\ConfigRepository::instance()->load($basePath . '/config');
\app\common\database\DatabaseManager::instance()->configure(
    \app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);
