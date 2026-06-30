<?php

declare(strict_types=1);

namespace app\service\content;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\NavigationRepository;
use app\service\log\OperationLogService;
use app\service\translation\SharedTranslationPipelineService;

final class NavigationService
{
    public function __construct(
        private readonly NavigationRepository $navigationRepository = new NavigationRepository(),
        private readonly OperationLogService $operationLogService = new OperationLogService(),
        private readonly SharedTranslationPipelineService $sharedTranslationPipelineService = new SharedTranslationPipelineService()
    ) {
    }

    public function list(): array
    {
        return $this->navigationRepository->menus();
    }

    public function lookups(): array
    {
        $pageService = new PageService();
        $aboutService = new AboutService();
        $productService = new ProductService();
        $solutionService = new SolutionService();
        $articleService = new ArticleService();

        return [
            'pages' => $pageService->list([
                'page' => 1,
                'page_size' => 200,
            ]),
            'about_pages' => $aboutService->pages(),
            'product_categories' => $productService->categoryTree(),
            'solution_categories' => $solutionService->categoryTree(),
            'article_categories' => $articleService->categoryTree(),
        ];
    }

    public function bootstrap(?int $preferredId = null): array
    {
        $menus = $this->list();
        $lookups = $this->lookups();
        $targetId = $preferredId && $preferredId > 0 ? $preferredId : (int) ($menus[0]['id'] ?? 0);
        $detail = null;

        if ($targetId > 0) {
            try {
                $detail = $this->detail($targetId);
            } catch (BusinessException) {
                $detail = null;
            }
        }

        return [
            'menus' => $menus,
            'lookups' => $lookups,
            'current_id' => $targetId > 0 ? $targetId : null,
            'detail' => $detail,
        ];
    }

    public function detail(int $id): array
    {
        $menu = $this->navigationRepository->findMenu($id);
        if ($menu === null) {
            throw new BusinessException('导航菜单不存在', ErrorCode::NOT_FOUND);
        }

        return $menu;
    }

    public function createMenu(array $data): array
    {
        $name = trim((string) ($data['name_zh'] ?? ''));
        $menuKey = trim((string) ($data['menu_key'] ?? ''));
        if ($name === '' || $menuKey === '') {
            throw new BusinessException('导航菜单名称和标识不能为空', ErrorCode::INVALID_PARAMS);
        }

        if ($this->navigationRepository->menuKeyExists($menuKey)) {
            throw new BusinessException('导航菜单标识已存在', ErrorCode::INVALID_PARAMS);
        }

        $menu = $this->navigationRepository->createMenu([
            'name_zh' => $name,
            'menu_key' => $menuKey,
            'menu_position' => (string) ($data['menu_position'] ?? 'header'),
            'sort' => (int) ($data['sort'] ?? 0),
            'is_enabled' => (int) ($data['is_enabled'] ?? 1),
        ]);

        $menuId = (int) ($menu['id'] ?? 0);
        $this->sharedTranslationPipelineService->syncEntities('navigation_menu', [$menuId]);
        $this->operationLogService->recordCurrentAction('navigation', 'navigation.menu.create', 'navigation_menu', $menu, '导航菜单已创建');

        return $menu;
    }

    public function updateMenu(int $id, array $data): array
    {
        $existing = $this->navigationRepository->findMenu($id);
        if ($existing === null) {
            throw new BusinessException('导航菜单不存在', ErrorCode::NOT_FOUND);
        }

        if (array_key_exists('menu_key', $data)) {
            $menuKey = trim((string) ($data['menu_key'] ?? ''));
            if ($menuKey === '' || $this->navigationRepository->menuKeyExists($menuKey, $id)) {
                throw new BusinessException('导航菜单标识无效', ErrorCode::INVALID_PARAMS);
            }
            $data['menu_key'] = $menuKey;
        }

        $payload = [];
        foreach (['name_zh', 'menu_key', 'menu_position', 'sort', 'is_enabled'] as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = $data[$field];
            }
        }

        if ($payload === []) {
            throw new BusinessException('导航菜单数据无效', ErrorCode::INVALID_PARAMS);
        }

        $updated = $this->navigationRepository->updateMenu($id, $payload);
        if ($updated === null) {
            throw new BusinessException('导航菜单不存在', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('navigation', 'navigation.update', 'navigation_menu', $updated, '导航菜单已更新');

        return $updated;
    }

    public function updateItems(int $menuId, array $items): array
    {
        if ($this->navigationRepository->findMenu($menuId) === null) {
            throw new BusinessException('导航菜单不存在', ErrorCode::NOT_FOUND);
        }

        $updated = $this->navigationRepository->replaceItems($menuId, $items);
        if ($updated === null) {
            throw new BusinessException('导航菜单不存在', ErrorCode::NOT_FOUND);
        }

        $itemIds = [];
        if (isset($updated['items']) && is_array($updated['items'])) {
            $itemIds = array_map(static fn (array $item): int => (int) ($item['id'] ?? 0), $updated['items']);
        }

        $this->sharedTranslationPipelineService->syncEntities('navigation_item', $itemIds);
        $this->operationLogService->recordCurrentAction('navigation', 'navigation.items.update', 'navigation_menu', $updated, '导航菜单项已更新');

        return $updated;
    }

    public function delete(int $id): void
    {
        $deleted = $this->navigationRepository->deleteMenu($id);
        if ($deleted === null) {
            throw new BusinessException('导航菜单不存在', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('navigation', 'navigation.delete', 'navigation_menu', $deleted, '导航菜单已删除');
    }
}
