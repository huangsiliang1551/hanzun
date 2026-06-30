<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class MenuRepository
{
    public function allMenus(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $this->syncCatalog($pdo);
            $statement = $pdo->query(
                'SELECT id, parent_id, name, path, route_name, icon, sort, is_visible, status
                 FROM admin_menus
                 ORDER BY sort DESC, id ASC'
            );
            $records = $statement->fetchAll();

            return is_array($records) ? $records : [];
        }

        return array_map(static function (array $item): array {
            return array_merge($item, ['is_visible' => 1, 'status' => 1]);
        }, $this->fallbackMenus());
    }

    public function allVisibleMenus(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $this->syncCatalog($pdo);
            $statement = $pdo->query('SELECT id, parent_id, name, path, route_name, icon, sort FROM admin_menus WHERE is_visible = 1 AND status = 1 ORDER BY sort DESC, id ASC');
            $records = $statement->fetchAll();

            return is_array($records) ? $records : [];
        }

        return $this->fallbackMenus();
    }

    public function actionPoints(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $this->syncCatalog($pdo);
            $statement = $pdo->query(
                'SELECT id, name, code, description
                 FROM admin_action_points
                 ORDER BY id ASC'
            );
            $records = $statement->fetchAll();

            return is_array($records) ? $records : [];
        }

        return $this->fallbackActionPoints();
    }

    /** @return array<int, array<string, mixed>> */
    private function fallbackMenus(): array
    {
        return [
            ['id' => 1, 'parent_id' => 0, 'name' => '数据看板', 'path' => '/dashboard', 'route_name' => 'dashboard', 'icon' => 'dashboard', 'sort' => 100],
            ['id' => 2, 'parent_id' => 0, 'name' => '首页配置', 'path' => '/homepage', 'route_name' => 'homepage', 'icon' => 'home', 'sort' => 99],
            ['id' => 3, 'parent_id' => 0, 'name' => '产品管理', 'path' => '/products', 'route_name' => 'products', 'icon' => 'appstore', 'sort' => 98],
            ['id' => 4, 'parent_id' => 0, 'name' => '生产线/方案', 'path' => '/solutions', 'route_name' => 'solutions', 'icon' => 'deployment-unit', 'sort' => 97],
            ['id' => 5, 'parent_id' => 0, 'name' => '新闻与案例', 'path' => '/articles', 'route_name' => 'articles', 'icon' => 'read', 'sort' => 96],
            ['id' => 12, 'parent_id' => 0, 'name' => '新闻管理', 'path' => '/news', 'route_name' => 'news', 'icon' => 'read', 'sort' => 96],
            ['id' => 6, 'parent_id' => 0, 'name' => '资源管理', 'path' => '/media', 'route_name' => 'media', 'icon' => 'folder-open', 'sort' => 95],
            ['id' => 13, 'parent_id' => 0, 'name' => '案例管理', 'path' => '/cases', 'route_name' => 'cases', 'icon' => 'deployment-unit', 'sort' => 95],
            ['id' => 7, 'parent_id' => 0, 'name' => '企业介绍', 'path' => '/about', 'route_name' => 'about', 'icon' => 'team', 'sort' => 94],
            ['id' => 8, 'parent_id' => 0, 'name' => '单页/专题页', 'path' => '/pages', 'route_name' => 'pages', 'icon' => 'file-text', 'sort' => 93],
            ['id' => 9, 'parent_id' => 0, 'name' => '询盘管理', 'path' => '/inquiries', 'route_name' => 'inquiries', 'icon' => 'message', 'sort' => 92],
            ['id' => 10, 'parent_id' => 0, 'name' => 'SEO 管理', 'path' => '/seo-center', 'route_name' => 'seo-center', 'icon' => 'search', 'sort' => 91],
            ['id' => 11, 'parent_id' => 0, 'name' => '系统设置', 'path' => '/settings', 'route_name' => 'settings', 'icon' => 'setting', 'sort' => 90],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function fallbackActionPoints(): array
    {
        return $this->canonicalActionPoints();
    }

    private function syncCatalog(PDO $pdo): void
    {
        $menuStatement = $pdo->prepare(
            'INSERT INTO admin_menus (id, parent_id, name, path, route_name, icon, menu_type, sort, is_visible, status)
             VALUES (:id, :parent_id, :name, :path, :route_name, :icon, :menu_type, :sort, :is_visible, :status)
             ON DUPLICATE KEY UPDATE
                parent_id = VALUES(parent_id),
                name = VALUES(name),
                path = VALUES(path),
                route_name = VALUES(route_name),
                icon = VALUES(icon),
                menu_type = VALUES(menu_type),
                sort = VALUES(sort),
                is_visible = VALUES(is_visible),
                status = VALUES(status)'
        );

        foreach ($this->canonicalMenus() as $menu) {
            $menuStatement->execute($menu);
        }

        $actionStatement = $pdo->prepare(
            'INSERT INTO admin_action_points (id, name, code, description)
             VALUES (:id, :name, :code, :description)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                code = VALUES(code),
                description = VALUES(description)'
        );

        foreach ($this->canonicalActionPoints() as $actionPoint) {
            $actionStatement->execute($actionPoint);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function canonicalMenus(): array
    {
        return array_map(
            static fn (array $item): array => array_merge($item, ['menu_type' => 'menu', 'is_visible' => 1, 'status' => 1]),
            $this->fallbackMenus()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function canonicalActionPoints(): array
    {
        return [
            ['id' => 1, 'name' => '查看数据看板', 'code' => 'dashboard.view', 'description' => '查看数据看板'],
            ['id' => 2, 'name' => '查看首页配置', 'code' => 'homepage.view', 'description' => '查看首页配置'],
            ['id' => 3, 'name' => '更新首页配置', 'code' => 'homepage.update', 'description' => '更新首页配置'],
            ['id' => 4, 'name' => '查看产品', 'code' => 'product.view', 'description' => '查看产品列表'],
            ['id' => 5, 'name' => '创建产品', 'code' => 'product.create', 'description' => '创建产品'],
            ['id' => 6, 'name' => '更新产品', 'code' => 'product.update', 'description' => '更新产品'],
            ['id' => 7, 'name' => '发布产品', 'code' => 'product.publish', 'description' => '发布产品'],
            ['id' => 8, 'name' => '查看方案', 'code' => 'solution.view', 'description' => '查看方案列表'],
            ['id' => 9, 'name' => '创建方案', 'code' => 'solution.create', 'description' => '创建方案'],
            ['id' => 10, 'name' => '更新方案', 'code' => 'solution.update', 'description' => '更新方案'],
            ['id' => 11, 'name' => '发布方案', 'code' => 'solution.publish', 'description' => '发布方案'],
            ['id' => 12, 'name' => '查看文章', 'code' => 'article.view', 'description' => '查看文章列表'],
            ['id' => 13, 'name' => '创建文章', 'code' => 'article.create', 'description' => '创建文章'],
            ['id' => 14, 'name' => '更新文章', 'code' => 'article.update', 'description' => '更新文章'],
            ['id' => 15, 'name' => '发布文章', 'code' => 'article.publish', 'description' => '发布文章'],
            ['id' => 16, 'name' => '查看单页', 'code' => 'page.view', 'description' => '查看单页列表'],
            ['id' => 17, 'name' => '创建单页', 'code' => 'page.create', 'description' => '创建单页'],
            ['id' => 18, 'name' => '更新单页', 'code' => 'page.update', 'description' => '更新单页'],
            ['id' => 19, 'name' => '发布单页', 'code' => 'page.publish', 'description' => '发布单页'],
            ['id' => 20, 'name' => '查看企业介绍', 'code' => 'about.view', 'description' => '查看企业介绍'],
            ['id' => 21, 'name' => '更新企业介绍', 'code' => 'about.update', 'description' => '更新企业介绍'],
            ['id' => 22, 'name' => '查看团队成员', 'code' => 'team.view', 'description' => '查看团队成员'],
            ['id' => 23, 'name' => '创建团队成员', 'code' => 'team.create', 'description' => '创建团队成员'],
            ['id' => 24, 'name' => '更新团队成员', 'code' => 'team.update', 'description' => '更新团队成员'],
            ['id' => 25, 'name' => '发布团队成员', 'code' => 'team.publish', 'description' => '发布团队成员'],
            ['id' => 26, 'name' => '查看证书', 'code' => 'certificate.view', 'description' => '查看证书'],
            ['id' => 27, 'name' => '创建证书', 'code' => 'certificate.create', 'description' => '创建证书'],
            ['id' => 28, 'name' => '更新证书', 'code' => 'certificate.update', 'description' => '更新证书'],
            ['id' => 29, 'name' => '发布证书', 'code' => 'certificate.publish', 'description' => '发布证书'],
            ['id' => 32, 'name' => '查看询盘', 'code' => 'inquiry.view', 'description' => '查看询盘列表'],
            ['id' => 33, 'name' => '更新询盘状态', 'code' => 'inquiry.update', 'description' => '更新询盘状态'],
            ['id' => 34, 'name' => '查看翻译任务', 'code' => 'translation.view', 'description' => '查看翻译任务'],
            ['id' => 35, 'name' => '重试翻译任务', 'code' => 'translation.retry', 'description' => '重试翻译任务'],
            ['id' => 36, 'name' => '查看 SEO', 'code' => 'seo.view', 'description' => '查看 SEO'],
            ['id' => 37, 'name' => '重试 SEO 任务', 'code' => 'seo.retry', 'description' => '重试 SEO 任务'],
            ['id' => 38, 'name' => '查看联系方式', 'code' => 'contact.view', 'description' => '查看联系方式'],
            ['id' => 39, 'name' => '创建联系方式', 'code' => 'contact.create', 'description' => '创建联系方式'],
            ['id' => 40, 'name' => '更新联系方式', 'code' => 'contact.update', 'description' => '更新联系方式'],
            ['id' => 41, 'name' => '查看管理员', 'code' => 'system.admin_user.view', 'description' => '查看管理员'],
            ['id' => 42, 'name' => '创建管理员', 'code' => 'system.admin_user.create', 'description' => '创建管理员'],
            ['id' => 43, 'name' => '更新管理员', 'code' => 'system.admin_user.update', 'description' => '更新管理员'],
            ['id' => 44, 'name' => '查看角色', 'code' => 'system.role.view', 'description' => '查看角色'],
            ['id' => 45, 'name' => '更新角色权限', 'code' => 'system.role.permissions.update', 'description' => '更新角色权限'],
            ['id' => 46, 'name' => '查看权限点', 'code' => 'system.permission.view', 'description' => '查看权限点'],
            ['id' => 47, 'name' => '查看语言配置', 'code' => 'system.languages.view', 'description' => '查看语言配置'],
            ['id' => 48, 'name' => '更新语言配置', 'code' => 'system.languages.update', 'description' => '更新语言配置'],
            ['id' => 49, 'name' => '查看 DeepSeek 配置', 'code' => 'system.deepseek.view', 'description' => '查看 DeepSeek 配置'],
            ['id' => 50, 'name' => '更新 DeepSeek 配置', 'code' => 'system.deepseek.update', 'description' => '更新 DeepSeek 配置'],
            ['id' => 51, 'name' => '查看日志', 'code' => 'system.logs.view', 'description' => '查看日志'],
            ['id' => 52, 'name' => '查看媒体资源', 'code' => 'media.view', 'description' => '查看媒体资源'],
            ['id' => 53, 'name' => '创建媒体资源', 'code' => 'media.create', 'description' => '创建媒体资源'],
            ['id' => 54, 'name' => '更新媒体资源', 'code' => 'media.update', 'description' => '更新媒体资源'],
            ['id' => 55, 'name' => '更新 SEO 配置', 'code' => 'seo.update', 'description' => '更新 SEO 配置'],
            ['id' => 56, 'name' => '审核翻译任务', 'code' => 'translation.approve', 'description' => '审核翻译任务'],
            ['id' => 57, 'name' => '查看站点配置', 'code' => 'system.site.view', 'description' => '查看站点配置'],
            ['id' => 58, 'name' => '更新站点配置', 'code' => 'system.site.update', 'description' => '更新站点配置'],
            ['id' => 59, 'name' => '删除管理员', 'code' => 'system.admin_user.delete', 'description' => '删除管理员'],
            ['id' => 60, 'name' => '创建角色', 'code' => 'system.role.create', 'description' => '创建角色'],
            ['id' => 61, 'name' => '更新角色', 'code' => 'system.role.update', 'description' => '更新角色'],
            ['id' => 62, 'name' => '删除角色', 'code' => 'system.role.delete', 'description' => '删除角色'],
            ['id' => 63, 'name' => '查看新闻', 'code' => 'news.view', 'description' => '查看新闻列表'],
            ['id' => 64, 'name' => '创建新闻', 'code' => 'news.create', 'description' => '创建新闻'],
            ['id' => 65, 'name' => '更新新闻', 'code' => 'news.update', 'description' => '更新新闻'],
            ['id' => 66, 'name' => '发布新闻', 'code' => 'news.publish', 'description' => '发布新闻'],
            ['id' => 67, 'name' => '查看案例', 'code' => 'case.view', 'description' => '查看案例列表'],
            ['id' => 68, 'name' => '创建案例', 'code' => 'case.create', 'description' => '创建案例'],
            ['id' => 69, 'name' => '更新案例', 'code' => 'case.update', 'description' => '更新案例'],
            ['id' => 70, 'name' => '发布案例', 'code' => 'case.publish', 'description' => '发布案例'],
            ['id' => 71, 'name' => '删除联系方式', 'code' => 'contact.delete', 'description' => '删除联系方式'],
        ];
    }
}
