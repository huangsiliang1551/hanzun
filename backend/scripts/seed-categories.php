<?php

declare(strict_types=1);

/**
 * 分类种子脚本 — 直接写入数据库
 *
 * 用法: php backend/scripts/seed-categories.php
 */

use app\common\bootstrap\Autoloader;
use app\common\bootstrap\EnvLoader;
use app\common\config\ConfigRepository;
use app\common\database\DatabaseManager;
use PDO;

// ── Bootstrap ──
$basePath = dirname(__DIR__);

require_once $basePath . '/app/common/bootstrap/Autoloader.php';
require_once $basePath . '/app/common/bootstrap/EnvLoader.php';
require_once $basePath . '/app/common/bootstrap/helpers.php';

Autoloader::register($basePath);
EnvLoader::load($basePath . '/.env');

$configRepository = ConfigRepository::instance();
$configRepository->load($basePath . '/config');
DatabaseManager::instance()->configure($configRepository->get('database.connections.mysql', []));

// ── Product Categories ──
$productCategories = [
    ['id' => 1,  'parent_id' => 0, 'name_zh' => '中式食品加工机械', 'slug' => 'chinese-food-machine',     'sort' => 100, 'is_enabled' => 1],
    ['id' => 2,  'parent_id' => 0, 'name_zh' => '蛋糕制作机',       'slug' => 'cake-machine',           'sort' => 99,  'is_enabled' => 1],
    ['id' => 3,  'parent_id' => 0, 'name_zh' => '面包制作机',       'slug' => 'bread-machine',          'sort' => 98,  'is_enabled' => 1],
    ['id' => 4,  'parent_id' => 0, 'name_zh' => '烘焙成品机',       'slug' => 'baked-product-machine',  'sort' => 97,  'is_enabled' => 1],
    ['id' => 5,  'parent_id' => 0, 'name_zh' => '食品成型机',       'slug' => 'food-forming-machine',   'sort' => 96,  'is_enabled' => 1],
    ['id' => 6,  'parent_id' => 0, 'name_zh' => '食品切片机',       'slug' => 'food-slicing-machine',   'sort' => 95,  'is_enabled' => 1],
    ['id' => 7,  'parent_id' => 0, 'name_zh' => '食品充填机',       'slug' => 'food-filling-machine',   'sort' => 94,  'is_enabled' => 1],
    ['id' => 8,  'parent_id' => 0, 'name_zh' => '食品摆盘机',       'slug' => 'food-plating-machine',   'sort' => 93,  'is_enabled' => 1],
    ['id' => 9,  'parent_id' => 0, 'name_zh' => '食品搅拌机',       'slug' => 'food-mixing-machine',    'sort' => 92,  'is_enabled' => 1],
    ['id' => 10, 'parent_id' => 0, 'name_zh' => '其它烘焙机',       'slug' => 'other-baking-machine',   'sort' => 91,  'is_enabled' => 1],
];

// ── Solution Categories ──
$solutionCategories = [
    ['id' => 1, 'parent_id' => 0, 'name_zh' => '定制烘焙生产线', 'slug' => 'custom-baking-line', 'sort' => 100, 'is_enabled' => 1],
];

// ── Write to Database ──
$pdo = DatabaseManager::instance()->connection();

if ($pdo instanceof PDO) {
    echo "[DB] 写入产品分类...\n";
    $pdo->exec('DELETE FROM product_categories');
    $stmt = $pdo->prepare(
        'INSERT INTO product_categories (id, parent_id, name_zh, slug, sort, is_enabled)
         VALUES (:id, :parent_id, :name_zh, :slug, :sort, :is_enabled)'
    );
    foreach ($productCategories as $cat) {
        $stmt->execute($cat);
        echo "  - {$cat['name_zh']}\n";
    }

    echo "[DB] 写入方案分类...\n";
    $pdo->exec('DELETE FROM solution_categories');
    $stmt = $pdo->prepare(
        'INSERT INTO solution_categories (id, parent_id, name_zh, slug, sort, is_enabled)
         VALUES (:id, :parent_id, :name_zh, :slug, :sort, :is_enabled)'
    );
    foreach ($solutionCategories as $cat) {
        $stmt->execute($cat);
        echo "  - {$cat['name_zh']}\n";
    }

    echo "[OK] 数据库分类已写入完成。\n";
}

echo "\n完成！请刷新后台查看分类。\n";
