const fs = require('fs');
const http = require('http');
const path = require('path');
const { execFile } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const fakePort = 18996;
const filesToBackup = [
  'products.json',
  'languages.json',
  'system_settings.json',
  'translation_jobs.json',
  'seo_jobs.json',
  'seo_routes.json',
  'product_translations.json',
  'deepseek_logs.json',
  'operation_logs.json'
];

function filePath(name) {
  return path.join(storageDir, name);
}

function backup(file) {
  const target = filePath(file);
  return fs.existsSync(target) ? fs.readFileSync(target, 'utf8') : null;
}

function restore(file, content) {
  const target = filePath(file);
  if (content === null) {
    if (fs.existsSync(target)) {
      fs.unlinkSync(target);
    }
    return;
  }

  fs.mkdirSync(path.dirname(target), { recursive: true });
  fs.writeFileSync(target, content, 'utf8');
}

function writeJson(file, payload) {
  fs.mkdirSync(storageDir, { recursive: true });
  fs.writeFileSync(filePath(file), JSON.stringify(payload, null, 2), 'utf8');
}

function startFakeDeepSeekServer() {
  return new Promise((resolve, reject) => {
    const server = http.createServer((req, res) => {
      if (req.method !== 'POST' || req.url !== '/chat/completions') {
        res.statusCode = 404;
        res.end('not found');
        return;
      }

      let body = '';
      req.on('data', (chunk) => {
        body += chunk;
      });
      req.on('end', () => {
        try {
          const payload = JSON.parse(body || '{}');
          const userMessage = Array.isArray(payload.messages)
            ? payload.messages.find((item) => item && item.role === 'user')
            : null;
          const userPayload = JSON.parse(String(userMessage && userMessage.content ? userMessage.content : '{}'));

          let content = {};
          if (userPayload.task === 'generate_seo') {
            const suffix = String(userPayload.language_code || 'xx').toLowerCase();
            content = {
              seo_title: `${String(userPayload.title || 'title')} seo ${suffix}`,
              seo_keywords: `keyword-${suffix},line-${suffix}`,
              seo_description: `${String(userPayload.summary || userPayload.title || 'desc').slice(0, 48)} seo ${suffix}`,
              slug: `generated-${suffix}-slug`
            };
          } else {
            content = { ok: true };
          }

          res.setHeader('Content-Type', 'application/json');
          res.end(JSON.stringify({
            choices: [
              {
                message: {
                  content: JSON.stringify(content)
                }
              }
            ]
          }));
        } catch (error) {
          res.statusCode = 500;
          res.setHeader('Content-Type', 'application/json');
          res.end(JSON.stringify({ error: { message: error.message } }));
        }
      });
    });

    server.listen(fakePort, '127.0.0.1', () => resolve(server));
    server.on('error', reject);
  });
}

function execPhp(script) {
  return new Promise((resolve, reject) => {
    execFile('php', ['-r', script], {
      cwd: backendRoot,
      encoding: 'utf8'
    }, (error, stdout, stderr) => {
      if (error) {
        const wrapped = new Error(stderr || stdout || error.message);
        wrapped.cause = error;
        reject(wrapped);
        return;
      }

      resolve(stdout);
    });
  });
}

function mainPhp() {
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

    $translationService = new app\\service\\translation\\TranslationService();
    $seoService = new app\\service\\seo\\SeoService();
    $translationRepository = new app\\repository\\TranslationRepository();
    $seoRepository = new app\\repository\\SeoRepository();
    $productRepository = new app\\repository\\ProductRepository();
    $bridge = new app\\service\\content\\ContentEntityBridge();
    $logService = new app\\service\\log\\OperationLogService();

    $approved = $translationService->approve(1);
    $approvedTranslation = $bridge->translationRecord('product', 1, 'en');
    $approvedProduct = $productRepository->find(1);

    $updatedRoute = $seoService->updateRoute(10, [
      'slug' => 'manual-zh-route',
      'seo_title' => '人工 SEO 标题',
      'seo_keywords' => '人工关键词,产线',
      'seo_description' => '人工 SEO 描述',
      'index_status' => 'index',
    ]);
    $updatedRouteJob = $seoRepository->findJobByEntity('product', 1, 'zh');
    $updatedProduct = $productRepository->find(1);

    $generated = $seoService->generate([
      'entity_type' => 'product',
      'entity_id' => 2,
      'language_codes' => ['zh', 'en'],
    ]);
    $generatedZhJob = $seoRepository->findJobByEntity('product', 2, 'zh');
    $generatedEnJob = $seoRepository->findJobByEntity('product', 2, 'en');
    $generatedZhRoute = $seoRepository->findRoute('product', 2, 'zh');
    $generatedEnRoute = $seoRepository->findRoute('product', 2, 'en');
    $generatedProduct = $productRepository->find(2);

    echo json_encode([
      'approved' => $approved,
      'approved_translation' => $approvedTranslation,
      'approved_product' => $approvedProduct,
      'updated_route' => $updatedRoute,
      'updated_route_job' => $updatedRouteJob,
      'updated_product' => $updatedProduct,
      'generated' => $generated,
      'generated_zh_job' => $generatedZhJob,
      'generated_en_job' => $generatedEnJob,
      'generated_zh_route' => $generatedZhRoute,
      'generated_en_route' => $generatedEnRoute,
      'generated_product' => $generatedProduct,
      'operation_logs' => $logService->listOperations(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

async function main() {
  const backups = Object.fromEntries(filesToBackup.map((file) => [file, backup(file)]));
  const server = await startFakeDeepSeekServer();

  try {
    writeJson('products.json', [
      {
        id: 1,
        category_id: 0,
        sku: 'TASK-001',
        name_zh: '翻译审核产品',
        summary_zh: '翻译审核摘要',
        content_zh: '<p>翻译审核内容</p>',
        business_status: 'on_sale',
        publish_status: 'published',
        translation_status: 'review_required',
        seo_status: 'generated',
        is_home_featured: 0,
        manual_sort: 10,
        slug: 'task-001',
        seo_title: '原 SEO 标题',
        seo_keywords: '原关键词',
        seo_description: '原描述',
        publish_time: '2026-06-11 10:00:00',
        created_by: 1,
        updated_by: 1,
        created_at: '2026-06-11 10:00:00',
        updated_at: '2026-06-11 10:00:00'
      },
      {
        id: 2,
        category_id: 0,
        sku: 'TASK-002',
        name_zh: '手动生成 SEO 产品',
        summary_zh: '手动生成 SEO 摘要',
        content_zh: '<p>手动生成 SEO 内容</p>',
        business_status: 'on_sale',
        publish_status: 'published',
        translation_status: 'completed',
        seo_status: 'pending',
        is_home_featured: 0,
        manual_sort: 8,
        slug: 'task-002',
        seo_title: '',
        seo_keywords: '',
        seo_description: '',
        publish_time: '2026-06-11 11:00:00',
        created_by: 1,
        updated_by: 1,
        created_at: '2026-06-11 11:00:00',
        updated_at: '2026-06-11 11:00:00'
      }
    ]);
    writeJson('languages.json', [
      { id: 1, code: 'zh', name: 'Chinese', is_default: 1, is_enabled: 1, sort: 100 },
      { id: 2, code: 'en', name: 'English', is_default: 0, is_enabled: 1, sort: 90 }
    ]);
    writeJson('system_settings.json', {
      deepseek: {
        config: {
          base_url: `http://127.0.0.1:${fakePort}`,
          model: 'deepseek-chat',
          api_key: 'test-key',
          timeout_seconds: 10,
          retry_times: 0,
          chat_enabled: 1,
          translation_enabled: 1,
          seo_enabled: 1
        }
      }
    });
    writeJson('translation_jobs.json', [
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
    writeJson('product_translations.json', [
      {
        id: 1,
        product_id: 1,
        language_code: 'en',
        name: 'Review Product',
        summary: 'Review summary',
        content: '<p>Review content</p>',
        translation_status: 'review_required'
      }
    ]);
    writeJson('seo_jobs.json', [
      {
        id: 10,
        entity_type: 'product',
        entity_id: 1,
        language_code: 'zh',
        status: 'generated',
        retry_count: 0,
        error_message: null,
        created_at: '2026-06-11 10:06:00',
        updated_at: '2026-06-11 10:06:00'
      },
      {
        id: 11,
        entity_type: 'product',
        entity_id: 1,
        language_code: 'en',
        status: 'generated',
        retry_count: 0,
        error_message: null,
        created_at: '2026-06-11 10:06:00',
        updated_at: '2026-06-11 10:06:00'
      }
    ]);
    writeJson('seo_routes.json', [
      {
        id: 10,
        entity_type: 'product',
        entity_id: 1,
        language_code: 'zh',
        route_path: '/zh/products/task-001',
        slug: 'task-001',
        seo_title: '原 SEO 标题',
        seo_keywords: '原关键词',
        seo_description: '原描述',
        canonical_url: 'https://example.com/zh/products/task-001',
        index_status: 'index',
        last_generated_at: '2026-06-11 10:06:00'
      },
      {
        id: 11,
        entity_type: 'product',
        entity_id: 1,
        language_code: 'en',
        route_path: '/en/products/task-001',
        slug: 'task-001',
        seo_title: 'Original SEO Title',
        seo_keywords: 'original,keywords',
        seo_description: 'original description',
        canonical_url: 'https://example.com/en/products/task-001',
        index_status: 'index',
        last_generated_at: '2026-06-11 10:06:00'
      }
    ]);
    writeJson('deepseek_logs.json', []);
    writeJson('operation_logs.json', []);

    const payload = JSON.parse(await execPhp(mainPhp()));

    const issues = [];
    if (String(payload.approved?.status || '') !== 'completed') {
      issues.push('TranslationService::approve must mark job completed');
    }
    if (String(payload.approved_translation?.translation_status || '') !== 'completed') {
      issues.push('TranslationService::approve must mark translation record completed');
    }
    if (String(payload.approved_product?.translation_status || '') !== 'completed') {
      issues.push('TranslationService::approve must sync entity translation_status');
    }
    if (String(payload.updated_route?.slug || '') !== 'manual-zh-route') {
      issues.push('SeoService::updateRoute must persist manual slug');
    }
    if (String(payload.updated_route?.seo_title || '') !== '人工 SEO 标题') {
      issues.push('SeoService::updateRoute must persist manual seo_title');
    }
    if (String(payload.updated_route_job?.status || '') !== 'manual_override') {
      issues.push('SeoService::updateRoute must mark job manual_override');
    }
    if (String(payload.updated_product?.seo_status || '') !== 'manual_override') {
      issues.push('SeoService::updateRoute must sync entity seo_status to manual_override');
    }
    if (!Array.isArray(payload.generated?.jobs) || payload.generated.jobs.length !== 2) {
      issues.push('SeoService::generate must execute requested languages');
    }
    if (String(payload.generated_zh_job?.status || '') !== 'generated' || String(payload.generated_en_job?.status || '') !== 'generated') {
      issues.push('SeoService::generate must finish jobs as generated');
    }
    if (String(payload.generated_zh_route?.seo_title || '').trim() === '' || String(payload.generated_en_route?.seo_title || '').trim() === '') {
      issues.push('SeoService::generate must create/update SEO routes');
    }
    if (String(payload.generated_product?.seo_status || '') !== 'generated') {
      issues.push('SeoService::generate must sync entity seo_status');
    }

    const logs = payload.operation_logs && Array.isArray(payload.operation_logs.items)
      ? payload.operation_logs.items
      : (Array.isArray(payload.operation_logs) ? payload.operation_logs : []);
    const actionPoints = logs.map((item) => String(item.action_point || ''));
    ['translation.approve', 'seo.route.update', 'seo.generate'].forEach((actionPoint) => {
      if (!actionPoints.includes(actionPoint)) {
        issues.push(`OperationLogService missing action log: ${actionPoint}`);
      }
    });

    if (issues.length > 0) {
      console.error('Task center operations runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Task center operations runtime validation passed.');
  } finally {
    await new Promise((resolve) => server.close(resolve));
    Object.entries(backups).forEach(([file, content]) => restore(file, content));
  }
}

main().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
