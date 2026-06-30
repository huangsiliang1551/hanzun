const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  sections: path.join(storageDir, 'homepage_sections.json'),
  sectionItems: path.join(storageDir, 'homepage_section_items.json'),
  sectionItemTranslations: path.join(storageDir, 'homepage_section_item_translations.json'),
  products: path.join(storageDir, 'products.json'),
  productTranslations: path.join(storageDir, 'product_translations.json'),
  languages: path.join(storageDir, 'languages.json'),
  settings: path.join(storageDir, 'system_settings.json'),
  seoRoutes: path.join(storageDir, 'seo_routes.json'),
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
        id: 20,
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
    writeJson(files.sectionItems, [
      {
        id: 301,
        section_id: 20,
        source_type: 'product',
        source_id: 101,
        title_override_zh: '中文首页主推',
        summary_override_zh: '中文摘要覆盖',
        cover_asset_id: 0,
        sort: 100,
        is_enabled: 1
      }
    ]);
    writeJson(files.sectionItemTranslations, [
      {
        id: 1,
        item_id: 301,
        language_code: 'en',
        title: 'English Hero Product',
        summary: 'English summary override',
        translation_status: 'completed'
      }
    ]);
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
    writeJson(files.productTranslations, [
      {
        id: 1,
        product_id: 101,
        language_code: 'en',
        name: 'Cookie Former',
        summary: 'For cookie production line',
        content: '<p>content</p>',
        translation_status: 'completed'
      }
    ]);
    writeJson(files.languages, [
      { id: 1, code: 'zh', name: 'Chinese', is_default: 1, is_enabled: 1, sort: 100 },
      { id: 2, code: 'en', name: 'English', is_default: 0, is_enabled: 1, sort: 90 },
    ]);
    writeJson(files.settings, {});
    writeJson(files.seoRoutes, []);

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

      $service = new app\\service\\content\\PublicSiteService();
      echo json_encode($service->homepage('en'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8',
    }));

    const issues = [];
    const section = Array.isArray(payload.sections)
      ? payload.sections.find((item) => Number(item.id || 0) === 20)
      : null;
    if (!section || !Array.isArray(section.items) || section.items.length !== 1) {
      issues.push('PublicSiteService::homepage(en) must render manual_pick section items');
    }
    if (String(section?.items?.[0]?.display_title || '') !== 'English Hero Product') {
      issues.push('PublicSiteService::homepage(en) must apply translated item title overrides');
    }
    if (String(section?.items?.[0]?.display_summary || '') !== 'English summary override') {
      issues.push('PublicSiteService::homepage(en) must apply translated item summary overrides');
    }
    if (String(section?.items?.[0]?.name || '') !== 'Cookie Former') {
      issues.push('PublicSiteService::homepage(en) must still localize the underlying source entity');
    }

    if (issues.length > 0) {
      console.error('Homepage section item public runtime validation failed:');
      issues.forEach((issue) => console.error('- ' + issue));
      process.exit(1);
    }

    console.log('Homepage section item public runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
