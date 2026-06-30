<?php

declare(strict_types=1);

namespace app\adminapi\controller\content;

use app\adminapi\controller\BaseAdminController;
use app\common\database\DatabaseManager;
use PDO;

class CategorySeedController extends BaseAdminController
{
    public function seed(): array
    {
        $this->seedProductCategories();
        $this->seedSolutionCategories();

        return $this->success([
            'product_categories' => $this->getProductCategoryNames(),
            'solution_categories' => $this->getSolutionCategoryNames(),
        ], [], '分类目录已重置');
    }

    private function seedProductCategories(): void
    {
        $categories = [
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

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $pdo->exec('DELETE FROM product_categories');
            $statement = $pdo->prepare(
                'INSERT INTO product_categories (id, parent_id, name_zh, slug, sort, is_enabled)
                 VALUES (:id, :parent_id, :name_zh, :slug, :sort, :is_enabled)'
            );
            foreach ($categories as $cat) {
                $statement->execute($cat);
            }
        }
    }

    private function seedSolutionCategories(): void
    {
        $categories = [
            ['id' => 1, 'parent_id' => 0, 'name_zh' => '定制烘焙生产线', 'slug' => 'custom-baking-line', 'sort' => 100, 'is_enabled' => 1],
        ];

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $pdo->exec('DELETE FROM solution_categories');
            $statement = $pdo->prepare(
                'INSERT INTO solution_categories (id, parent_id, name_zh, slug, sort, is_enabled)
                 VALUES (:id, :parent_id, :name_zh, :slug, :sort, :is_enabled)'
            );
            foreach ($categories as $cat) {
                $statement->execute($cat);
            }
        }
    }

    private function getProductCategoryNames(): array
    {
        return array_map(
            static fn (string $name): string => $name,
            ['中式食品加工机械', '蛋糕制作机', '面包制作机', '烘焙成品机', '食品成型机', '食品切片机', '食品充填机', '食品摆盘机', '食品搅拌机', '其它烘焙机']
        );
    }

    private function getSolutionCategoryNames(): array
    {
        return ['定制烘焙生产线'];
    }
}
