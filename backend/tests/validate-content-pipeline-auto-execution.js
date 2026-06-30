const fs = require('fs');
const http = require('http');
const path = require('path');
const { execFile } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const fakePort = 18997;
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
          if (userPayload.task === 'translate') {
            content = Object.fromEntries(
              Object.entries(userPayload.source_fields || {}).map(([key, value]) => [key, `${String(value || '')} [${String(userPayload.target_language || 'xx').toUpperCase()}]`])
            );
          } else if (userPayload.task === 'generate_seo') {
            const suffix = String(userPayload.language_code || 'xx').toLowerCase();
            content = {
              seo_title: `${String(userPayload.title || 'title')} SEO ${suffix}`,
              seo_keywords: `keyword-${suffix},line-${suffix}`,
              seo_description: `${String(userPayload.summary || userPayload.content || userPayload.title || 'desc').slice(0, 60)} SEO ${suffix}`,
              slug: `auto-${suffix}-slug`
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

function mainPhp(baseUrl) {
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
    $created = $service->create([
      'category_id' => 0,
      'sku' => 'AUTO-PIPE-001',
      'name_zh' => 'auto pipeline product',
      'summary_zh' => 'auto pipeline summary',
      'content_zh' => '<p>auto pipeline body</p>',
      'publish_status' => 'published',
      'business_status' => 'on_sale'
    ], ['id' => 1, 'username' => 'tester', 'nickname' => 'Tester']);

    $translationRepository = new app\\repository\\TranslationRepository();
    $seoRepository = new app\\repository\\SeoRepository();
    $bridge = new app\\service\\content\\ContentEntityBridge();
    $routeZh = $seoRepository->findRoute('product', (int) $created['id'], 'zh');
    $routeEn = $seoRepository->findRoute('product', (int) $created['id'], 'en');
    $translationEn = $bridge->translationRecord('product', (int) $created['id'], 'en');
    $translationJob = $translationRepository->findByEntity('product', (int) $created['id'], 'en');
    $seoJobZh = $seoRepository->findJobByEntity('product', (int) $created['id'], 'zh');
    $seoJobEn = $seoRepository->findJobByEntity('product', (int) $created['id'], 'en');
    $logs = (new app\\repository\\DeepSeekLogRepository())->list();

    echo json_encode([
      'created' => $created,
      'translation_en' => $translationEn,
      'translation_job' => $translationJob,
      'seo_job_zh' => $seoJobZh,
      'seo_job_en' => $seoJobEn,
      'route_zh' => $routeZh,
      'route_en' => $routeEn,
      'deepseek_logs' => $logs,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
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

async function main() {
  const backups = Object.fromEntries(filesToBackup.map((file) => [file, backup(file)]));
  const server = await startFakeDeepSeekServer();
  const baseUrl = `http://127.0.0.1:${fakePort}`;

  try {
    writeJson('products.json', []);
    writeJson('languages.json', [
      { id: 1, code: 'zh', name: 'Chinese', is_default: 1, is_enabled: 1, sort: 100 },
      { id: 2, code: 'en', name: 'English', is_default: 0, is_enabled: 1, sort: 90 }
    ]);
    writeJson('system_settings.json', {
      deepseek: {
        config: {
          base_url: baseUrl,
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
    writeJson('translation_jobs.json', []);
    writeJson('seo_jobs.json', []);
    writeJson('seo_routes.json', []);
    writeJson('product_translations.json', []);
    writeJson('deepseek_logs.json', []);
    writeJson('operation_logs.json', []);

    const payload = JSON.parse(await execPhp(mainPhp(baseUrl)));

    const issues = [];
    if (String(payload.created?.translation_status || '') !== 'completed') {
      issues.push(`expected created.translation_status to be completed, got ${payload.created?.translation_status}`);
    }
    if (String(payload.created?.seo_status || '') !== 'generated') {
      issues.push(`expected created.seo_status to be generated, got ${payload.created?.seo_status}`);
    }
    if (String(payload.translation_job?.status || '') !== 'completed') {
      issues.push(`expected translation job status to be completed, got ${payload.translation_job?.status} / ${payload.translation_job?.error_message || ''}`);
    }
    if (String(payload.seo_job_zh?.status || '') !== 'generated') {
      issues.push(`expected zh seo job status to be generated, got ${payload.seo_job_zh?.status} / ${payload.seo_job_zh?.error_message || ''}`);
    }
    if (String(payload.seo_job_en?.status || '') !== 'generated') {
      issues.push(`expected en seo job status to be generated, got ${payload.seo_job_en?.status} / ${payload.seo_job_en?.error_message || ''}`);
    }
    if (!payload.translation_en || String(payload.translation_en.name || '').indexOf('[EN]') === -1) {
      issues.push('expected english translation record to be created automatically');
    }
    if (String(payload.route_zh?.seo_title || '').trim() === '') {
      issues.push('expected zh seo route to receive generated seo_title automatically');
    }
    if (String(payload.route_en?.seo_title || '').trim() === '') {
      issues.push('expected en seo route to receive generated seo_title automatically');
    }
    if (!Array.isArray(payload.deepseek_logs) || payload.deepseek_logs.length < 3) {
      issues.push('expected deepseek logs to include auto translation and seo calls');
    }

    if (issues.length > 0) {
      console.error('Content pipeline auto execution validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Content pipeline auto execution validation passed.');
  } finally {
    await new Promise((resolve) => server.close(resolve));
    Object.entries(backups).forEach(([file, content]) => restore(file, content));
  }
}

main().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
