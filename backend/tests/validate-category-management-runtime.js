const fs = require('fs');
const http = require('http');
const path = require('path');
const { execFile } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const fakePort = 18999;
const filesToBackup = [
  'product_categories.json',
  'solution_categories.json',
  'article_categories.json',
  'products.json',
  'solutions.json',
  'articles.json',
  'product_category_translations.json',
  'solution_category_translations.json',
  'article_category_translations.json',
  'translation_jobs.json',
  'languages.json',
  'system_settings.json',
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

          const content = Object.fromEntries(
            Object.entries(userPayload.source_fields || {}).map(([key, value]) => [
              key,
              `${String(value || '')} [${String(userPayload.target_language || 'xx').toUpperCase()}]`
            ])
          );

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

    $productService = new app\\service\\content\\ProductService();
    $solutionService = new app\\service\\content\\SolutionService();
    $articleService = new app\\service\\content\\ArticleService();
    $bridge = new app\\service\\content\\ContentEntityBridge();
    $translationRepository = new app\\repository\\TranslationRepository();

    $createdProductCategory = $productService->createCategory([
      'parent_id' => 2,
      'name_zh' => 'Cupcake Depositors',
      'slug' => '',
      'sort' => 80,
      'is_enabled' => 1,
    ]);
    $productDepthError = '';
    try {
      $productService->createCategory([
        'parent_id' => (int) $createdProductCategory['id'],
        'name_zh' => 'Level Four',
        'slug' => '',
        'sort' => 10,
        'is_enabled' => 1,
      ]);
    } catch (Throwable $exception) {
      $productDepthError = $exception->getMessage();
    }
    $updatedProductCategory = $productService->updateCategory((int) $createdProductCategory['id'], [
      'parent_id' => 1,
      'name_zh' => 'Cupcake Deposit Machines',
      'sort' => 70,
      'is_enabled' => 0,
    ]);
    $productDeleteBlockedError = '';
    try {
      $productService->deleteCategory(1);
    } catch (Throwable $exception) {
      $productDeleteBlockedError = $exception->getMessage();
    }
    $deletedProductCategory = $productService->deleteCategory((int) $createdProductCategory['id']);

    $createdSolutionCategory = $solutionService->createCategory([
      'parent_id' => 2,
      'name_zh' => 'Fondant Cake Line',
      'slug' => '',
      'sort' => 75,
      'is_enabled' => 1,
    ]);
    $updatedSolutionCategory = $solutionService->updateCategory((int) $createdSolutionCategory['id'], [
      'name_zh' => 'Fondant Cake Production Line',
      'slug' => 'fondant-line',
      'sort' => 65,
      'is_enabled' => 1,
    ]);
    $solutionDeleteBlockedError = '';
    try {
      $solutionService->deleteCategory(1);
    } catch (Throwable $exception) {
      $solutionDeleteBlockedError = $exception->getMessage();
    }
    $deletedSolutionCategory = $solutionService->deleteCategory((int) $createdSolutionCategory['id']);

    $createdArticleCategory = $articleService->createCategory([
      'parent_id' => 1,
      'name_zh' => 'Expo News',
      'content_type_scope' => 'news',
      'sort' => 88,
      'is_enabled' => 1,
    ]);
    $updatedArticleCategory = $articleService->updateCategory((int) $createdArticleCategory['id'], [
      'content_type_scope' => 'all',
      'sort' => 66,
      'is_enabled' => 0,
    ]);
    $articleDeleteBlockedError = '';
    try {
      $articleService->deleteCategory(1);
    } catch (Throwable $exception) {
      $articleDeleteBlockedError = $exception->getMessage();
    }
    $deletedArticleCategory = $articleService->deleteCategory((int) $createdArticleCategory['id']);

    echo json_encode([
      'product_tree' => $productService->categoryTree(),
      'solution_tree' => $solutionService->categoryTree(),
      'article_tree' => $articleService->categoryTree(),
      'created_product_category' => $createdProductCategory,
      'updated_product_category' => $updatedProductCategory,
      'product_depth_error' => $productDepthError,
      'product_delete_blocked_error' => $productDeleteBlockedError,
      'deleted_product_category' => $deletedProductCategory,
      'created_solution_category' => $createdSolutionCategory,
      'updated_solution_category' => $updatedSolutionCategory,
      'solution_delete_blocked_error' => $solutionDeleteBlockedError,
      'deleted_solution_category' => $deletedSolutionCategory,
      'created_article_category' => $createdArticleCategory,
      'updated_article_category' => $updatedArticleCategory,
      'article_delete_blocked_error' => $articleDeleteBlockedError,
      'deleted_article_category' => $deletedArticleCategory,
      'product_category_translation' => $bridge->translationRecord('product_category', (int) $createdProductCategory['id'], 'en'),
      'solution_category_translation' => $bridge->translationRecord('solution_category', (int) $createdSolutionCategory['id'], 'en'),
      'article_category_translation' => $bridge->translationRecord('article_category', (int) $createdArticleCategory['id'], 'en'),
      'product_category_job' => $translationRepository->findByEntity('product_category', (int) $createdProductCategory['id'], 'en'),
      'solution_category_job' => $translationRepository->findByEntity('solution_category', (int) $createdSolutionCategory['id'], 'en'),
      'article_category_job' => $translationRepository->findByEntity('article_category', (int) $createdArticleCategory['id'], 'en'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function findNode(tree, id) {
  for (const node of Array.isArray(tree) ? tree : []) {
    if (Number(node && node.id) === Number(id)) {
      return node;
    }
    const child = findNode(node && node.children, id);
    if (child) {
      return child;
    }
  }

  return null;
}

async function main() {
  const backups = Object.fromEntries(filesToBackup.map((file) => [file, backup(file)]));
  const server = await startFakeDeepSeekServer();

  try {
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
          seo_enabled: 0
        }
      }
    });
    writeJson('translation_jobs.json', []);
    writeJson('deepseek_logs.json', []);
    writeJson('operation_logs.json', []);
    writeJson('product_category_translations.json', []);
    writeJson('solution_category_translations.json', []);
    writeJson('article_category_translations.json', []);
    writeJson('product_categories.json', [
      { id: 1, parent_id: 0, name_zh: 'Bakery Equipment', slug: 'bakery-equipment', sort: 100, is_enabled: 1 },
      { id: 2, parent_id: 1, name_zh: 'Cake Equipment', slug: 'cake-equipment', sort: 90, is_enabled: 1 }
    ]);
    writeJson('solution_categories.json', [
      { id: 1, parent_id: 0, name_zh: 'Line Solutions', slug: 'line-solutions', sort: 100, is_enabled: 1 },
      { id: 2, parent_id: 1, name_zh: 'Cake Line', slug: 'cake-line', sort: 90, is_enabled: 1 }
    ]);
    writeJson('article_categories.json', [
      { id: 1, parent_id: 0, name_zh: 'Exhibitions', content_type_scope: 'news', sort: 100, is_enabled: 1 },
      { id: 2, parent_id: 1, name_zh: 'Germany Expo', content_type_scope: 'news', sort: 90, is_enabled: 1 },
      { id: 3, parent_id: 0, name_zh: 'Customer Cases', content_type_scope: 'case', sort: 80, is_enabled: 1 }
    ]);
    writeJson('products.json', [
      { id: 1, category_id: 2, sku: 'P-001', name_zh: 'Cake Depositor', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', business_status: 'on_sale', is_home_featured: 1, manual_sort: 100, publish_time: '2026-06-11 10:00:00' }
    ]);
    writeJson('solutions.json', [
      { id: 1, category_id: 2, name_zh: 'Cake Automatic Line', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', is_home_featured: 1, manual_sort: 100, manual_asset_id: null, publish_time: '2026-06-11 10:00:00' }
    ]);
    writeJson('articles.json', [
      { id: 1, category_id: 2, content_type: 'news', title_zh: 'Germany Expo Update', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', is_home_featured: 1, manual_sort: 100, publish_time: '2026-06-11 10:00:00' },
      { id: 2, category_id: 3, content_type: 'case', title_zh: 'UAE Cake Project', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', is_home_featured: 1, manual_sort: 90, publish_time: '2026-06-11 10:00:00' }
    ]);

    const payload = JSON.parse(await execPhp(mainPhp()));
    const issues = [];

    const productNode = findNode(payload.product_tree, payload.created_product_category && payload.created_product_category.id);
    const solutionNode = findNode(payload.solution_tree, payload.created_solution_category && payload.created_solution_category.id);
    const articleNode = findNode(payload.article_tree, payload.created_article_category && payload.created_article_category.id);
    const productRoot = findNode(payload.product_tree, 1);
    const solutionRoot = findNode(payload.solution_tree, 1);
    const articleRoot = findNode(payload.article_tree, 1);

    if (productNode) {
      issues.push('product category delete must remove deletable leaf categories');
    }
    if (!String(payload.product_depthError || payload.product_depth_error || '').trim()) {
      issues.push('product categories must reject level-4 nesting');
    }
    if (!String(payload.product_delete_blocked_error || '').trim()) {
      issues.push('product categories with child/content must reject delete');
    }
    if (Number(payload.deleted_product_category?.id || 0) <= 0) {
      issues.push('product category delete must return deleted category');
    }
    if (Number(productRoot && productRoot.content_total_count) !== 1) {
      issues.push('product category tree must expose aggregated content counts');
    }
    if (!payload.product_category_translation || String(payload.product_category_translation.name || '').indexOf('[EN]') === -1) {
      issues.push('product category create/update must auto-generate translation');
    }
    if (String(payload.product_category_job && payload.product_category_job.status || '') !== 'completed') {
      issues.push('product category translation job must complete automatically');
    }

    if (solutionNode) {
      issues.push('solution category delete must remove deletable leaf categories');
    }
    if (!String(payload.solution_delete_blocked_error || '').trim()) {
      issues.push('solution categories with child/content must reject delete');
    }
    if (Number(payload.deleted_solution_category?.id || 0) <= 0) {
      issues.push('solution category delete must return deleted category');
    }
    if (Number(solutionRoot && solutionRoot.content_total_count) !== 1) {
      issues.push('solution category tree must expose aggregated content counts');
    }
    if (!payload.solution_category_translation || String(payload.solution_category_translation.name || '').indexOf('[EN]') === -1) {
      issues.push('solution category create/update must auto-generate translation');
    }
    if (String(payload.solution_category_job && payload.solution_category_job.status || '') !== 'completed') {
      issues.push('solution category translation job must complete automatically');
    }

    if (articleNode) {
      issues.push('article category delete must remove deletable leaf categories');
    }
    if (!String(payload.article_delete_blocked_error || '').trim()) {
      issues.push('article categories with child/content must reject delete');
    }
    if (Number(payload.deleted_article_category?.id || 0) <= 0) {
      issues.push('article category delete must return deleted category');
    }
    if (Number(articleRoot && articleRoot.content_total_count) !== 1) {
      issues.push('article category tree must expose aggregated content counts');
    }
    if (!payload.article_category_translation || String(payload.article_category_translation.name || '').indexOf('[EN]') === -1) {
      issues.push('article category create/update must auto-generate translation');
    }
    if (String(payload.article_category_job && payload.article_category_job.status || '') !== 'completed') {
      issues.push('article category translation job must complete automatically');
    }

    if (issues.length > 0) {
      console.error('Category management runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Category management runtime validation passed.');
  } finally {
    await new Promise((resolve) => server.close(resolve));
    Object.entries(backups).forEach(([file, content]) => restore(file, content));
  }
}

main().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
