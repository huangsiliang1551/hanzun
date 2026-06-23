const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  routes: path.join(storageDir, 'seo_routes.json'),
  logs404: path.join(storageDir, 'seo_404_logs.json'),
  settings: path.join(storageDir, 'system_settings.json'),
  operationLogs: path.join(storageDir, 'operation_logs.json')
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

function writeJson(filePath, payload) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, JSON.stringify(payload, null, 2), 'utf8');
}

function main() {
  const backups = {};
  Object.entries(files).forEach(([key, filePath]) => {
    backups[key] = backup(filePath);
  });

  try {
    writeJson(files.routes, [
      {
        id: 1,
        entity_type: 'product',
        entity_id: 1,
        language_code: 'en',
        route_path: '/en/products/cake-depositor',
        slug: 'cake-depositor',
        seo_title: 'Cake Depositor',
        seo_keywords: 'cake depositor,bakery equipment',
        seo_description: 'Automatic cake depositor for bakery production lines.',
        canonical_url: 'https://example.com/en/products/cake-depositor',
        index_status: 'index',
        last_generated_at: '2026-06-11 10:00:00'
      },
      {
        id: 2,
        entity_type: 'solution',
        entity_id: 1,
        language_code: 'en',
        route_path: '/en/solutions/cake-line',
        slug: 'cake-line',
        seo_title: 'Cake Production Line',
        seo_keywords: 'cake line,bakery line',
        seo_description: 'Automated cake production line solution.',
        canonical_url: 'https://example.com/en/solutions/cake-line',
        index_status: 'index',
        last_generated_at: '2026-06-11 10:05:00'
      },
      {
        id: 3,
        entity_type: 'article',
        entity_id: 7,
        language_code: 'en',
        route_path: '/en/news/exhibition-updates',
        slug: 'exhibition-updates',
        seo_title: 'Exhibition Updates',
        seo_keywords: 'exhibition,bakery expo',
        seo_description: 'Latest updates from bakery exhibitions.',
        canonical_url: 'https://example.com/en/news/exhibition-updates',
        index_status: 'noindex',
        last_generated_at: '2026-06-11 10:10:00'
      }
    ]);
    writeJson(files.logs404, [
      {
        id: 1,
        request_path: '/en/products/legacy-cake-line',
        referrer: 'https://google.com',
        hit_count: 12,
        fix_status: 'pending',
        suggested_route: '/en/solutions/cake-line',
        note: 'legacy route needs redirect',
        first_seen_at: '2026-06-09 12:00:00',
        last_seen_at: '2026-06-11 09:00:00',
        resolved_at: null
      },
      {
        id: 2,
        request_path: '/de/news/expo-2023',
        referrer: 'https://bing.com',
        hit_count: 5,
        fix_status: 'processing',
        suggested_route: '/en/news/exhibition-updates',
        note: 'waiting redirect confirmation',
        first_seen_at: '2026-06-10 12:00:00',
        last_seen_at: '2026-06-11 08:00:00',
        resolved_at: null
      },
      {
        id: 3,
        request_path: '/es/contact-us',
        referrer: '',
        hit_count: 3,
        fix_status: 'resolved',
        suggested_route: '/en/contact',
        note: 'redirect already applied',
        first_seen_at: '2026-06-07 12:00:00',
        last_seen_at: '2026-06-08 08:00:00',
        resolved_at: '2026-06-08 10:00:00'
      }
    ]);
    writeJson(files.settings, {
      deepseek: {
        config: {
          base_url: 'https://api.deepseek.com/v1',
          model: 'deepseek-chat',
          api_key: '',
          timeout_seconds: 30,
          retry_times: 0,
          chat_enabled: 0,
          translation_enabled: 0,
          seo_enabled: 0,
          prompts: {
            chat: { system: 'disabled' },
            seo: { system: 'disabled' },
            translation: { system: 'disabled' }
          }
        }
      }
    });
    writeJson(files.operationLogs, []);

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

      $service = new app\\service\\seo\\SeoService();
      $logService = new app\\service\\log\\OperationLogService();

      $routes = $service->routes();
      $logs = $service->fourOhFourLogs();
      $initialSiteFiles = $service->siteFiles();
      $updated404 = $service->update404Log(1, [
        'fix_status' => 'resolved',
        'suggested_route' => '/en/solutions/cake-line',
        'note' => 'redirect configured',
      ]);
      $updatedRobots = $service->updateRobots("User-agent: *\\nAllow: /\\nSitemap: https://example.com/sitemap.xml");
      $rebuiltSitemap = $service->rebuildSitemap();

      echo json_encode([
        'routes' => $routes,
        'logs' => $logs,
        'initial_site_files' => $initialSiteFiles,
        'updated_404' => $updated404,
        'updated_robots' => $updatedRobots,
        'rebuilt_sitemap' => $rebuiltSitemap,
        'operation_logs' => $logService->listOperations(),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8'
    }));

    const issues = [];

    if (Number(payload.routes?.summary?.route_count || 0) !== 3) {
      issues.push('SeoService::routes must summarize total route count');
    }
    if (Number(payload.routes?.summary?.index_count || 0) !== 2) {
      issues.push('SeoService::routes must summarize index route count');
    }
    if (Number(payload.routes?.summary?.noindex_count || 0) !== 1) {
      issues.push('SeoService::routes must summarize noindex route count');
    }
    if (Number(payload.logs?.summary?.pending || 0) !== 1 || Number(payload.logs?.summary?.processing || 0) !== 1 || Number(payload.logs?.summary?.resolved || 0) !== 1) {
      issues.push('SeoService::fourOhFourLogs must summarize 404 statuses');
    }
    if (Number(payload.initial_site_files?.pending_404_count || 0) !== 2) {
      issues.push('SeoService::siteFiles must expose unresolved 404 count');
    }
    if (String(payload.updated_404?.fix_status || '') !== 'resolved') {
      issues.push('SeoService::update404Log must update fix_status');
    }
    if (!payload.updated_404?.resolved_at) {
      issues.push('SeoService::update404Log must stamp resolved_at when resolved');
    }
    if (String(payload.updated_404?.note || '') !== 'redirect configured') {
      issues.push('SeoService::update404Log must persist note');
    }
    if (String(payload.updated_robots?.robots_content || '').indexOf('Sitemap: https://example.com/sitemap.xml') === -1) {
      issues.push('SeoService::updateRobots must persist robots content');
    }
    if (!payload.updated_robots?.robots_updated_at) {
      issues.push('SeoService::updateRobots must update robots_updated_at');
    }
    if (!payload.rebuilt_sitemap?.sitemap_last_generated_at) {
      issues.push('SeoService::rebuildSitemap must stamp sitemap_last_generated_at');
    }
    if (Number(payload.rebuilt_sitemap?.sitemap_route_count || 0) !== 3) {
      issues.push('SeoService::rebuildSitemap must persist sitemap route count');
    }
    if (Number(payload.rebuilt_sitemap?.pending_404_count || 0) !== 1) {
      issues.push('SeoService::rebuildSitemap must reflect updated unresolved 404 count');
    }

    const logs = payload.operation_logs && Array.isArray(payload.operation_logs.items)
      ? payload.operation_logs.items
      : (Array.isArray(payload.operation_logs) ? payload.operation_logs : []);
    const actionPoints = logs.map((item) => String(item.action_point || ''));

    ['seo.404.update', 'seo.robots.update', 'seo.sitemap.rebuild'].forEach((actionPoint) => {
      if (!actionPoints.includes(actionPoint)) {
        issues.push(`OperationLogService missing action log: ${actionPoint}`);
      }
    });

    if (issues.length > 0) {
      console.error('SEO center runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('SEO center runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
