const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  sections: path.join(storageDir, 'homepage_sections.json'),
  sectionItems: path.join(storageDir, 'homepage_section_items.json'),
  settings: path.join(storageDir, 'system_settings.json'),
  operationLogs: path.join(storageDir, 'operation_logs.json'),
  loginLogs: path.join(storageDir, 'login_logs.json'),
  languages: path.join(storageDir, 'languages.json'),
  translationJobs: path.join(storageDir, 'translation_jobs.json'),
  products: path.join(storageDir, 'products.json'),
  articles: path.join(storageDir, 'articles.json'),
  solutions: path.join(storageDir, 'solutions.json'),
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
    writeJson(files.sections, [
      {
        id: 10,
        section_key: 'manual_featured_products',
        section_type: 'product_list',
        title_zh: '手动推荐产品',
        subtitle_zh: '手工编排',
        fetch_mode: 'manual_pick',
        extra_config: JSON.stringify({ limit: 6 }),
        sort: 100,
        is_enabled: 1,
      }
    ]);
    writeJson(files.sectionItems, []);
    writeJson(files.settings, {
      homepage: {
        publish_meta: {
          draft_updated_at: null,
          live_updated_at: null,
          last_published_by: '',
          last_restored_by: '',
          has_unpublished_changes: 0,
          publish_log: [],
        },
      },
      deepseek: {
        config: {
          translation_enabled: 0,
          seo_enabled: 0,
          chat_enabled: 0,
        },
      },
    });
    writeJson(files.operationLogs, []);
    writeJson(files.loginLogs, []);
    writeJson(files.languages, [
      { id: 1, code: 'zh', name: 'Chinese', is_default: 1, is_enabled: 1, sort: 100 },
      { id: 2, code: 'en', name: 'English', is_default: 0, is_enabled: 1, sort: 90 },
    ]);
    writeJson(files.translationJobs, []);
    writeJson(files.products, [
      {
        id: 101,
        category_id: 0,
        sku: 'HOME-101',
        name_zh: '曲奇成型机',
        summary_zh: '适合饼干产线',
        content_zh: '<p>内容</p>',
        business_status: 'on_sale',
        publish_status: 'published',
        translation_status: 'completed',
        seo_status: 'generated',
        is_home_featured: 0,
        manual_sort: 10,
        slug: 'cookie-former',
        seo_title: '',
        seo_keywords: '',
        seo_description: '',
        publish_time: '2026-06-10 12:00:00',
        created_by: 1,
        updated_by: 1,
        created_at: '2026-06-10 12:00:00',
        updated_at: '2026-06-10 12:00:00'
      }
    ]);
    writeJson(files.articles, [
      {
        id: 201,
        category_id: 0,
        content_type: 'case',
        title_zh: '墨西哥客户案例',
        summary_zh: '整线交付案例',
        content_zh: '<p>案例</p>',
        country_code: 'MX',
        case_tags: '案例',
        related_solution_ids: '[1]',
        related_product_ids: '[101]',
        publish_status: 'published',
        translation_status: 'completed',
        seo_status: 'generated',
        is_home_featured: 0,
        manual_sort: 5,
        slug: 'mexico-case',
        seo_title: '',
        seo_keywords: '',
        seo_description: '',
        publish_time: '2026-06-10 09:00:00',
        created_by: 1,
        updated_by: 1,
        created_at: '2026-06-10 09:00:00',
        updated_at: '2026-06-10 09:00:00'
      }
    ]);
    writeJson(files.solutions, []);

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

      $service = new app\\service\\homepage\\HomepageService();
      $logService = new app\\service\\log\\OperationLogService();

      $updated = $service->updateSectionItems(10, [
        [
          'source_type' => 'product',
          'source_id' => 101,
          'title_override_zh' => '首页主推曲奇机',
          'summary_override_zh' => '覆盖摘要',
          'cover_asset_id' => 0,
          'sort' => 120,
          'is_enabled' => 1
        ],
        [
          'source_type' => 'article',
          'source_id' => 201,
          'title_override_zh' => '',
          'summary_override_zh' => '',
          'cover_asset_id' => 0,
          'sort' => 90,
          'is_enabled' => 1
        ]
      ]);

      $listed = $service->sectionItems(10);
      $preview = $service->previewPayload();

      echo json_encode([
        'updated' => $updated,
        'listed' => $listed,
        'preview' => $preview,
        'operation_logs' => $logService->listOperations(),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8',
    }));

    const issues = [];
    if (!Array.isArray(payload.updated?.items) || payload.updated.items.length !== 2) {
      issues.push('HomepageService::updateSectionItems must persist two items');
    }
    if (String(payload.updated?.items?.[0]?.source_type || '') !== 'product') {
      issues.push('HomepageService::updateSectionItems must keep source_type');
    }
    if (Number(payload.updated?.items?.[0]?.id || 0) <= 0) {
      issues.push('HomepageService::updateSectionItems must assign item ids');
    }
    if (String(payload.listed?.items?.[0]?.title_override_zh || '') !== '首页主推曲奇机') {
      issues.push('HomepageService::sectionItems must return persisted override fields');
    }
    if (String(payload.listed?.items?.[0]?.source_record?.name_zh || '') !== '曲奇成型机') {
      issues.push('HomepageService::sectionItems must hydrate source record');
    }
    if (Number(payload.listed?.items?.[0]?.sort || 0) !== 120) {
      issues.push('HomepageService::sectionItems must sort items by sort desc');
    }

    const previewSection = Array.isArray(payload.preview?.sections)
      ? payload.preview.sections.find((item) => Number(item.id || 0) === 10)
      : null;
    if (!previewSection || !Array.isArray(previewSection.items) || previewSection.items.length !== 2) {
      issues.push('HomepageService::previewPayload must include manual_pick section items');
    }
    if (String(previewSection?.items?.[0]?.display_title_zh || '') !== '首页主推曲奇机') {
      issues.push('HomepageService::previewPayload must expose item title overrides');
    }

    const logs = payload.operation_logs && Array.isArray(payload.operation_logs.items)
      ? payload.operation_logs.items
      : (Array.isArray(payload.operation_logs) ? payload.operation_logs : []);
    const actionPoints = logs.map((item) => String(item.action_point || ''));
    if (!actionPoints.includes('homepage.items.update')) {
      issues.push('OperationLogService missing action log: homepage.items.update');
    }

    if (issues.length > 0) {
      console.error('Homepage section item management runtime validation failed:');
      issues.forEach((issue) => console.error('- ' + issue));
      process.exit(1);
    }

    console.log('Homepage section item management runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
