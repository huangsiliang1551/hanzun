const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  navigation: path.join(storageDir, 'navigation_menus.json'),
  operationLogs: path.join(storageDir, 'operation_logs.json'),
  languages: path.join(storageDir, 'languages.json'),
  translationJobs: path.join(storageDir, 'translation_jobs.json'),
  deepseek: path.join(storageDir, 'deepseek_logs.json'),
};

function backup(filePath) {
  return fs.existsSync(filePath) ? fs.readFileSync(filePath, 'utf8') : null;
}

function restore(filePath, content) {
  if (content === null) {
    if (fs.existsSync(filePath)) {
      fs.unlinkSync(filePath);
    }
    return;
  }

  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, content, 'utf8');
}

function writeJson(filePath, data) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf8');
}

function main() {
  const backups = {};
  Object.entries(files).forEach(([key, filePath]) => {
    backups[key] = backup(filePath);
  });

  try {
    writeJson(files.navigation, [
      {
        id: 1,
        name_zh: '顶部主导航',
        menu_key: 'main-header',
        menu_position: 'header',
        sort: 100,
        is_enabled: 1,
        created_at: '2026-06-11 00:00:00',
        updated_at: '2026-06-11 00:00:00',
        items: [
          {
            id: 1,
            menu_id: 1,
            parent_id: 0,
            name_zh: '产品中心',
            code: 'products',
            route_key: 'products',
            item_type: 'auto_category_tree',
            link_type: 'category_tree',
            linked_entity_type: 'product_category',
            linked_entity_id: 1,
            root_category_id: 1,
            max_depth: 2,
            include_children: 1,
            display_mode: 'dropdown',
            url: '',
            open_in_new_tab: 0,
            sort: 100,
            is_enabled: 1,
          },
          {
            id: 2,
            menu_id: 1,
            parent_id: 0,
            name_zh: '生产线方案',
            code: 'solutions',
            route_key: 'solutions',
            item_type: 'auto_category_tree',
            link_type: 'category_tree',
            linked_entity_type: 'solution_category',
            linked_entity_id: 1,
            root_category_id: 1,
            max_depth: 3,
            include_children: 1,
            display_mode: 'flyout',
            url: '',
            open_in_new_tab: 0,
            sort: 90,
            is_enabled: 1,
          }
        ],
      }
    ]);
    writeJson(files.operationLogs, []);
    writeJson(files.languages, [
      { id: 1, code: 'zh', name: 'Chinese', is_default: 1, is_enabled: 1, sort: 100 },
      { id: 2, code: 'en', name: 'English', is_default: 0, is_enabled: 1, sort: 90 },
    ]);
    writeJson(files.translationJobs, []);
    writeJson(files.deepseek, []);

    const phpCode = `
      $basePath = getcwd();
      require_once $basePath . '/app/common/bootstrap/Autoloader.php';
      require_once $basePath . '/app/common/bootstrap/EnvLoader.php';
      require_once $basePath . '/app/common/bootstrap/helpers.php';
      app\\common\\bootstrap\\Autoloader::register($basePath);
      app\\common\\bootstrap\\EnvLoader::load($basePath . '/.env');

      app\\common\\config\\ConfigRepository::instance()->load($basePath . '/config');

      app\\common\\database\\DatabaseManager::instance()->configure(

          app\\common\\config\\ConfigRepository::instance()->get('database.connections.mysql', [])

      );

      $service = new app\\service\\content\\NavigationService();
      $public = new app\\service\\content\\PublicSiteService();
      $logs = new app\\service\\log\\OperationLogService();

      $created = $service->createMenu([
        'name_zh' => '页脚导航',
        'menu_key' => 'footer-links',
        'menu_position' => 'footer',
        'sort' => 80,
        'is_enabled' => 1,
      ]);

      $updated = $service->updateItems(1, [
        [
          'id' => 2,
          'menu_id' => 1,
          'parent_id' => 0,
          'name_zh' => '生产线方案',
          'code' => 'solutions',
          'route_key' => 'solutions',
          'item_type' => 'auto_category_tree',
          'link_type' => 'category_tree',
          'linked_entity_type' => 'solution_category',
          'linked_entity_id' => 1,
          'root_category_id' => 1,
          'max_depth' => 3,
          'include_children' => 1,
          'display_mode' => 'flyout',
          'url' => '',
          'open_in_new_tab' => 0,
          'sort' => 120,
          'is_enabled' => 0,
        ],
        [
          'menu_id' => 1,
          'parent_id' => 0,
          'name_zh' => '企业介绍',
          'code' => 'about',
          'route_key' => 'about',
          'item_type' => 'about_page',
          'link_type' => 'page',
          'linked_entity_type' => 'about_page',
          'linked_entity_id' => 1,
          'root_category_id' => null,
          'max_depth' => 1,
          'include_children' => 0,
          'display_mode' => 'plain',
          'url' => '/about',
          'open_in_new_tab' => 0,
          'sort' => 110,
          'is_enabled' => 1,
        ]
      ]);

      $duplicateCreateError = '';
      try {
        $service->createMenu([
          'name_zh' => '重复主导航',
          'menu_key' => 'main-header',
          'menu_position' => 'header',
          'sort' => 60,
          'is_enabled' => 1,
        ]);
      } catch (Throwable $exception) {
        $duplicateCreateError = $exception->getMessage();
      }

      $duplicateUpdateError = '';
      try {
        $service->updateMenu((int) $created['id'], [
          'menu_key' => 'main-header',
        ]);
      } catch (Throwable $exception) {
        $duplicateUpdateError = $exception->getMessage();
      }

      echo json_encode([
        'created' => $created,
        'updated' => $updated,
        'public_header' => $public->navigation('header'),
        'operation_logs' => $logs->listOperations(),
        'duplicate_create_error' => $duplicateCreateError,
        'duplicate_update_error' => $duplicateUpdateError,
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8',
    }));

    const issues = [];
    if (String(payload.created?.menu_key || '') !== 'footer-links') {
      issues.push('NavigationService::createMenu must persist menu_key');
    }
    if (Number(payload.created?.id || 0) <= 1) {
      issues.push('NavigationService::createMenu must allocate a new menu id');
    }

    const updatedItems = Array.isArray(payload.updated?.items) ? payload.updated.items : [];
    if (updatedItems.length !== 2) {
      issues.push('NavigationService::updateItems must replace menu item set to support delete behavior');
    }
    if (Number(updatedItems[0]?.sort || 0) !== 120) {
      issues.push('NavigationService::updateItems must persist sort values');
    }
    if (Number(updatedItems[0]?.is_enabled ?? -1) !== 0) {
      issues.push('NavigationService::updateItems must persist enable status');
    }
    if (Number(updatedItems[1]?.id || 0) <= 2) {
      issues.push('NavigationService::updateItems must allocate ids for new menu items');
    }

    const publicHeader = Array.isArray(payload.public_header) ? payload.public_header : [];
    const publicItems = Array.isArray(publicHeader[0]?.items) ? publicHeader[0].items : [];
    if (publicItems.length !== 1 || String(publicItems[0]?.name_zh || '') !== '企业介绍') {
      issues.push('PublicSiteService::navigation must filter disabled items after navigation updates');
    }

    const logs = payload.operation_logs && Array.isArray(payload.operation_logs.items)
      ? payload.operation_logs.items
      : (Array.isArray(payload.operation_logs) ? payload.operation_logs : []);
    const actionPoints = logs.map((item) => String(item.action_point || ''));
    ['navigation.menu.create', 'navigation.items.update'].forEach((actionPoint) => {
      if (!actionPoints.includes(actionPoint)) {
        issues.push(`OperationLogService missing action log: ${actionPoint}`);
      }
    });
    if (!String(payload.duplicate_create_error || '').trim()) {
      issues.push('NavigationService::createMenu must reject duplicate menu_key');
    }
    if (!String(payload.duplicate_update_error || '').trim()) {
      issues.push('NavigationService::updateMenu must reject duplicate menu_key');
    }

    if (issues.length > 0) {
      console.error('Navigation management runtime validation failed:');
      issues.forEach((issue) => console.error('- ' + issue));
      process.exit(1);
    }

    console.log('Navigation management runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
