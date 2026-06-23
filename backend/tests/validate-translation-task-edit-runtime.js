const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  products: path.join(storageDir, 'products.json'),
  productTranslations: path.join(storageDir, 'product_translations.json'),
  translationJobs: path.join(storageDir, 'translation_jobs.json'),
  languages: path.join(storageDir, 'languages.json'),
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
  const backups = Object.fromEntries(Object.entries(files).map(([key, filePath]) => [key, backup(filePath)]));

  try {
    writeJson(files.products, [
      {
        id: 1,
        category_id: 0,
        sku: 'TRANS-001',
        name_zh: '翻译审核产品',
        summary_zh: '翻译审核摘要',
        content_zh: '<p>翻译审核内容</p>',
        business_status: 'on_sale',
        publish_status: 'published',
        translation_status: 'review_required',
        seo_status: 'pending',
        is_home_featured: 0,
        manual_sort: 10,
        slug: 'trans-001',
        seo_title: '',
        seo_keywords: '',
        seo_description: '',
        publish_time: '2026-06-11 10:00:00',
        created_by: 1,
        updated_by: 1,
        created_at: '2026-06-11 10:00:00',
        updated_at: '2026-06-11 10:00:00'
      }
    ]);

    writeJson(files.productTranslations, [
      {
        id: 1,
        product_id: 1,
        language_code: 'en',
        name: 'Old Product Name',
        summary: 'Old summary',
        content: '<p>Old content</p>',
        translation_status: 'review_required'
      }
    ]);

    writeJson(files.translationJobs, [
      {
        id: 1,
        entity_type: 'product',
        entity_id: 1,
        language_code: 'en',
        status: 'review_required',
        retry_count: 0,
        error_message: null,
        created_at: '2026-06-11 10:05:00',
        updated_at: '2026-06-11 10:05:00'
      }
    ]);

    writeJson(files.languages, [
      { id: 1, code: 'zh', name: 'Chinese', is_default: 1, is_enabled: 1, sort: 100 },
      { id: 2, code: 'en', name: 'English', is_default: 0, is_enabled: 1, sort: 90 }
    ]);

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

      $service = new app\\service\\translation\\TranslationService();
      $bridge = new app\\service\\content\\ContentEntityBridge();
      $logService = new app\\service\\log\\OperationLogService();

      $updated = $service->update(1, [
        'translated_fields' => [
          'name' => 'Manual Product Name',
          'summary' => 'Manual summary',
          'content' => '<p>Manual content</p>'
        ]
      ]);

      echo json_encode([
        'updated' => $updated,
        'translation' => $bridge->translationRecord('product', 1, 'en'),
        'product' => $bridge->find('product', 1),
        'operation_logs' => $logService->listOperations(),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '0'
      }
    }));

    const issues = [];

    if (String(payload.updated?.status || '') !== 'review_required') {
      issues.push('TranslationService::update must keep the job in review_required');
    }
    if (String(payload.translation?.name || '') !== 'Manual Product Name') {
      issues.push('TranslationService::update must persist edited translated name');
    }
    if (String(payload.translation?.summary || '') !== 'Manual summary') {
      issues.push('TranslationService::update must persist edited translated summary');
    }
    if (String(payload.translation?.translation_status || '') !== 'review_required') {
      issues.push('TranslationService::update must keep translation record in review_required before approval');
    }
    if (String(payload.product?.translation_status || '') !== 'review_required') {
      issues.push('TranslationService::update must keep entity translation_status in review_required');
    }

    const logs = payload.operation_logs && Array.isArray(payload.operation_logs.items)
      ? payload.operation_logs.items
      : (Array.isArray(payload.operation_logs) ? payload.operation_logs : []);
    const actionPoints = logs.map((item) => String(item.action_point || ''));
    if (!actionPoints.includes('translation.update')) {
      issues.push('TranslationService::update must write operation log translation.update');
    }

    if (issues.length > 0) {
      console.error('Translation task edit runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Translation task edit runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => restore(filePath, backups[key]));
  }
}

main();
