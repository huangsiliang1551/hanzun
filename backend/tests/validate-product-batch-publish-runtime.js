const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const productsPath = path.join(storageDir, 'products.json');
const languagesPath = path.join(storageDir, 'languages.json');
const settingsPath = path.join(storageDir, 'system_settings.json');
const translationJobsPath = path.join(storageDir, 'translation_jobs.json');
const seoJobsPath = path.join(storageDir, 'seo_jobs.json');
const seoRoutesPath = path.join(storageDir, 'seo_routes.json');
const operationLogsPath = path.join(storageDir, 'operation_logs.json');
const routePath = path.join(backendRoot, 'route', 'adminapi.php');
const controllerPath = path.join(backendRoot, 'app', 'adminapi', 'controller', 'content', 'ProductController.php');
const servicePath = path.join(backendRoot, 'app', 'service', 'content', 'ProductService.php');

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

function buildPhpPayload() {
  return `
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

    $service = new app\\service\\content\\ProductService();

    $published = $service->batchPublish([101, 102], 'published', ['id' => 1, 'username' => 'admin', 'nickname' => 'Admin']);
    $drafted = $service->batchPublish([102], 'draft', ['id' => 1, 'username' => 'admin', 'nickname' => 'Admin']);

    echo json_encode([
      'published' => $published,
      'drafted' => $drafted,
      'products' => (new app\\repository\\ProductRepository())->list(['page_size' => 20])['items'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const backups = {};
  [
    productsPath,
    languagesPath,
    settingsPath,
    translationJobsPath,
    seoJobsPath,
    seoRoutesPath,
    operationLogsPath
  ].forEach((filePath) => {
    backups[filePath] = backup(filePath);
  });

  try {
    fs.mkdirSync(storageDir, { recursive: true });

    writeJson(productsPath, [
      {
        id: 101,
        category_id: 1,
        sku: 'HZ-CF-101',
        name_zh: '批量产品一',
        summary_zh: 'summary',
        content_zh: '<p>content</p>',
        business_status: 'on_sale',
        publish_status: 'draft',
        translation_status: 'pending',
        seo_status: 'pending',
        is_home_featured: 0,
        manual_sort: 90,
        slug: 'batch-product-101',
        seo_title: '批量产品一',
        seo_keywords: '批量产品一',
        seo_description: '批量产品一描述',
        publish_time: null,
        created_by: 1,
        updated_by: 1,
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:00:00'
      },
      {
        id: 102,
        category_id: 1,
        sku: 'HZ-CF-102',
        name_zh: '批量产品二',
        summary_zh: 'summary',
        content_zh: '<p>content</p>',
        business_status: 'on_sale',
        publish_status: 'offline',
        translation_status: 'pending',
        seo_status: 'pending',
        is_home_featured: 0,
        manual_sort: 80,
        slug: 'batch-product-102',
        seo_title: '批量产品二',
        seo_keywords: '批量产品二',
        seo_description: '批量产品二描述',
        publish_time: null,
        created_by: 1,
        updated_by: 1,
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:00:00'
      }
    ]);

    writeJson(languagesPath, [
      { id: 1, code: 'zh', name: '简体中文', is_default: 1, is_enabled: 1, sort: 10 },
      { id: 2, code: 'en', name: 'English', is_default: 0, is_enabled: 1, sort: 20 }
    ]);
    writeJson(settingsPath, {
      deepseek: {
        config: {
          translation_enabled: 0,
          seo_enabled: 0
        }
      }
    });
    writeJson(translationJobsPath, []);
    writeJson(seoJobsPath, []);
    writeJson(seoRoutesPath, []);
    writeJson(operationLogsPath, []);

    const routeContent = fs.readFileSync(routePath, 'utf8');
    const controllerContent = fs.readFileSync(controllerPath, 'utf8');
    const serviceContent = fs.readFileSync(servicePath, 'utf8');
    const staticIssues = [];

    if (!/\/admin\/products\/batch-publish/.test(routeContent)) {
      staticIssues.push('missing product batch-publish route');
    }
    if (!/function\s+batchPublish\s*\(/.test(controllerContent)) {
      staticIssues.push('ProductController must expose batchPublish()');
    }
    if (!/function\s+batchPublish\s*\(/.test(serviceContent)) {
      staticIssues.push('ProductService must expose batchPublish()');
    }

    if (staticIssues.length > 0) {
      console.error('Product batch publish static validation failed:');
      staticIssues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    const output = execFileSync('php', ['-r', buildPhpPayload()], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '0'
      }
    });

    const payload = JSON.parse(output);
    const published = payload.published || {};
    const drafted = payload.drafted || {};
    const products = Array.isArray(payload.products) ? payload.products : [];
    const product101 = products.find((item) => Number(item.id) === 101) || {};
    const product102 = products.find((item) => Number(item.id) === 102) || {};
    const issues = [];

    if (Number(published.updated_count || 0) !== 2) {
      issues.push('ProductService::batchPublish must update all selected products');
    }
    if (String(product101.publish_status || '') !== 'published') {
      issues.push('Batch publish must set first product to published');
    }
    if (String(product102.publish_status || '') !== 'draft') {
      issues.push('Batch draft must set selected product back to draft');
    }
    if (!String(product101.publish_time || '').trim()) {
      issues.push('Batch publish must set publish_time for published products');
    }
    if (drafted.publish_status !== 'draft') {
      issues.push('Batch draft response must expose target publish_status');
    }

    const operationLogs = JSON.parse(fs.readFileSync(operationLogsPath, 'utf8'));
    const batchLogCount = Array.isArray(operationLogs)
      ? operationLogs.filter((item) => String(item.action_point || '') === 'product.batch_publish').length
      : 0;
    if (batchLogCount < 2) {
      issues.push('Batch product actions must write operation logs');
    }

    if (issues.length > 0) {
      console.error('Product batch publish runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Product batch publish runtime validation passed.');
  } finally {
    Object.entries(backups).forEach(([filePath, content]) => restore(filePath, content));
  }
}

main();
