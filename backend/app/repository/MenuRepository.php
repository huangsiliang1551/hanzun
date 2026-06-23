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
        }, $this->allVisibleMenus());
    }

    public function allVisibleMenus(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->query('SELECT id, parent_id, name, path, route_name, icon, sort FROM admin_menus WHERE is_visible = 1 AND status = 1 ORDER BY sort DESC, id ASC');
            $records = $statement->fetchAll();

            return is_array($records) ? $records : [];
        }

        return [
            ['id' => 1, 'parent_id' => 0, 'name' => '数据看板', 'path' => '/dashboard', 'route_name' => 'dashboard', 'icon' => 'dashboard', 'sort' => 100],
            ['id' => 2, 'parent_id' => 0, 'name' => '首页编辑', 'path' => '/homepage', 'route_name' => 'homepage', 'icon' => 'home', 'sort' => 99],
            ['id' => 3, 'parent_id' => 0, 'name' => '产品管理', 'path' => '/products', 'route_name' => 'products', 'icon' => 'appstore', 'sort' => 98],
            ['id' => 4, 'parent_id' => 0, 'name' => '解决方案', 'path' => '/solutions', 'route_name' => 'solutions', 'icon' => 'deployment-unit', 'sort' => 97],
            ['id' => 5, 'parent_id' => 0, 'name' => '新闻与案例', 'path' => '/news', 'route_name' => 'news', 'icon' => 'read', 'sort' => 96],
            ['id' => 6, 'parent_id' => 0, 'name' => '资源中心', 'path' => '/media', 'route_name' => 'media', 'icon' => 'folder-open', 'sort' => 95],
            ['id' => 7, 'parent_id' => 0, 'name' => '公司介绍', 'path' => '/about', 'route_name' => 'about', 'icon' => 'team', 'sort' => 94],
            ['id' => 8, 'parent_id' => 0, 'name' => '单页管理', 'path' => '/pages', 'route_name' => 'pages', 'icon' => 'file-text', 'sort' => 93],
            ['id' => 9, 'parent_id' => 0, 'name' => '询盘中心', 'path' => '/inquiries', 'route_name' => 'inquiries', 'icon' => 'message', 'sort' => 92],
            ['id' => 10, 'parent_id' => 0, 'name' => 'SEO 中心', 'path' => '/seo-center', 'route_name' => 'seo-center', 'icon' => 'search', 'sort' => 91],
            ['id' => 11, 'parent_id' => 0, 'name' => '系统设置', 'path' => '/settings', 'route_name' => 'settings', 'icon' => 'setting', 'sort' => 90],
        ];
    }

    public function actionPoints(): array
    {
        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->query(
                'SELECT id, name, code, description
                 FROM admin_action_points
                 ORDER BY id ASC'
            );
            $records = $statement->fetchAll();

            return is_array($records) ? $records : [];
        }

        return [
            ['id' => 1, 'name' => '查看数据看板', 'code' => 'dashboard.view', 'description' => '查看数据看板'],
            ['id' => 2, 'name' => '查看首页配置', 'code' => 'homepage.view', 'description' => '查看首页配置'],
            ['id' => 3, 'name' => '更新首页配置', 'code' => 'homepage.update', 'description' => '更新首页配置'],
            ['id' => 4, 'name' => '查看产品', 'code' => 'product.view', 'description' => '查看产品列表'],
            ['id' => 5, 'name' => '新增产品', 'code' => 'product.create', 'description' => '新增产品'],
            ['id' => 6, 'name' => '编辑产品', 'code' => 'product.update', 'description' => '编辑产品'],
            ['id' => 7, 'name' => '发布产品', 'code' => 'product.publish', 'description' => '发布产品'],
            ['id' => 8, 'name' => '查看方案', 'code' => 'solution.view', 'description' => '查看方案列表'],
            ['id' => 9, 'name' => '新增方案', 'code' => 'solution.create', 'description' => '新增方案'],
            ['id' => 10, 'name' => '编辑方案', 'code' => 'solution.update', 'description' => '编辑方案'],
            ['id' => 11, 'name' => '发布方案', 'code' => 'solution.publish', 'description' => '发布方案'],
            ['id' => 12, 'name' => '查看新闻与案例', 'code' => 'article.view', 'description' => '查看新闻与案例'],
            ['id' => 13, 'name' => '新增新闻与案例', 'code' => 'article.create', 'description' => '新增新闻与案例'],
            ['id' => 14, 'name' => '编辑新闻与案例', 'code' => 'article.update', 'description' => '编辑新闻与案例'],
            ['id' => 15, 'name' => '发布新闻与案例', 'code' => 'article.publish', 'description' => '发布新闻与案例'],
            ['id' => 16, 'name' => '查看单页', 'code' => 'page.view', 'description' => '查看单页'],
            ['id' => 17, 'name' => '新增单页', 'code' => 'page.create', 'description' => '新增单页'],
            ['id' => 18, 'name' => '编辑单页', 'code' => 'page.update', 'description' => '编辑单页'],
            ['id' => 19, 'name' => '发布单页', 'code' => 'page.publish', 'description' => '发布单页'],
            ['id' => 20, 'name' => '查看公司介绍', 'code' => 'about.view', 'description' => '查看公司介绍板块'],
            ['id' => 21, 'name' => '编辑公司介绍', 'code' => 'about.update', 'description' => '编辑公司介绍板块'],
            ['id' => 22, 'name' => '查看团队', 'code' => 'team.view', 'description' => '查看团队成员'],
            ['id' => 23, 'name' => '新增团队', 'code' => 'team.create', 'description' => '新增团队成员'],
            ['id' => 24, 'name' => '编辑团队', 'code' => 'team.update', 'description' => '编辑团队成员'],
            ['id' => 25, 'name' => '发布团队', 'code' => 'team.publish', 'description' => '发布团队成员'],
            ['id' => 26, 'name' => '查看证书', 'code' => 'certificate.view', 'description' => '查看资质证书'],
            ['id' => 27, 'name' => '新增证书', 'code' => 'certificate.create', 'description' => '新增资质证书'],
            ['id' => 28, 'name' => '编辑证书', 'code' => 'certificate.update', 'description' => '编辑资质证书'],
            ['id' => 29, 'name' => '发布证书', 'code' => 'certificate.publish', 'description' => '发布资质证书'],
            ['id' => 30, 'name' => '查看导航', 'code' => 'navigation.view', 'description' => '查看导航菜单'],
            ['id' => 31, 'name' => '编辑导航', 'code' => 'navigation.update', 'description' => '编辑导航菜单'],
            ['id' => 32, 'name' => '查看询盘', 'code' => 'inquiry.view', 'description' => '查看询盘'],
            ['id' => 33, 'name' => '处理询盘', 'code' => 'inquiry.update', 'description' => '处理询盘'],
            ['id' => 34, 'name' => '查看翻译任务', 'code' => 'translation.view', 'description' => '查看翻译任务'],
            ['id' => 35, 'name' => '重试翻译任务', 'code' => 'translation.retry', 'description' => '重试翻译任务'],
            ['id' => 36, 'name' => '查看 SEO', 'code' => 'seo.view', 'description' => '查看 SEO'],
            ['id' => 37, 'name' => '重试 SEO 任务', 'code' => 'seo.retry', 'description' => '重试 SEO 任务'],
            ['id' => 38, 'name' => '查看联系信息', 'code' => 'contact.view', 'description' => '查看联系工厂配置'],
            ['id' => 39, 'name' => '新增联系信息', 'code' => 'contact.create', 'description' => '新增联系工厂配置'],
            ['id' => 40, 'name' => '编辑联系信息', 'code' => 'contact.update', 'description' => '编辑联系工厂配置'],
            ['id' => 63, 'name' => '删除联系信息', 'code' => 'contact.delete', 'description' => '删除联系信息与字段类型'],
            ['id' => 41, 'name' => '查看管理员', 'code' => 'system.admin_user.view', 'description' => '查看管理员'],
            ['id' => 42, 'name' => '新增管理员', 'code' => 'system.admin_user.create', 'description' => '新增管理员'],
            ['id' => 43, 'name' => '编辑管理员', 'code' => 'system.admin_user.update', 'description' => '编辑管理员'],
            ['id' => 44, 'name' => '查看角色', 'code' => 'system.role.view', 'description' => '查看角色'],
            ['id' => 45, 'name' => '编辑角色权限', 'code' => 'system.role.permissions.update', 'description' => '编辑角色权限'],
            ['id' => 46, 'name' => '查看权限点', 'code' => 'system.permission.view', 'description' => '查看菜单与操作权限点'],
            ['id' => 47, 'name' => '查看语言设置', 'code' => 'system.languages.view', 'description' => '查看语言设置'],
            ['id' => 48, 'name' => '编辑语言设置', 'code' => 'system.languages.update', 'description' => '编辑语言设置'],
            ['id' => 49, 'name' => '查看 AI 设置', 'code' => 'system.deepseek.view', 'description' => '查看 AI 设置'],
            ['id' => 50, 'name' => '编辑 AI 设置', 'code' => 'system.deepseek.update', 'description' => '编辑 AI 设置'],
            ['id' => 51, 'name' => '查看日志', 'code' => 'system.logs.view', 'description' => '查看系统日志'],
            ['id' => 52, 'name' => '查看资源', 'code' => 'media.view', 'description' => '查看资源素材'],
            ['id' => 53, 'name' => '上传资源', 'code' => 'media.create', 'description' => '上传资源素材'],
            ['id' => 54, 'name' => '编辑资源', 'code' => 'media.update', 'description' => '编辑资源素材'],
            ['id' => 55, 'name' => '编辑 SEO', 'code' => 'seo.update', 'description' => '编辑 SEO 配置'],
            ['id' => 56, 'name' => '审核翻译任务', 'code' => 'translation.approve', 'description' => '审核翻译任务'],
            ['id' => 57, 'name' => '查看站点设置', 'code' => 'system.site.view', 'description' => '查看站点设置'],
            ['id' => 58, 'name' => '编辑站点设置', 'code' => 'system.site.update', 'description' => '编辑站点设置'],
            ['id' => 59, 'name' => '删除管理员', 'code' => 'system.admin_user.delete', 'description' => '删除管理员'],
            ['id' => 60, 'name' => '新增角色', 'code' => 'system.role.create', 'description' => '新增角色'],
            ['id' => 61, 'name' => '编辑角色', 'code' => 'system.role.update', 'description' => '编辑角色'],
            ['id' => 62, 'name' => '删除角色', 'code' => 'system.role.delete', 'description' => '删除角色'],
        ];
    }
}
