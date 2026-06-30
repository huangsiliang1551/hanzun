const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  products: path.join(storageDir, 'products.json'),
  solutions: path.join(storageDir, 'solutions.json'),
  articles: path.join(storageDir, 'articles.json'),
  pages: path.join(storageDir, 'pages.json'),
  productTranslations: path.join(storageDir, 'product_translations.json'),
  solutionTranslations: path.join(storageDir, 'solution_translations.json'),
  articleTranslations: path.join(storageDir, 'article_translations.json'),
  pageTranslations: path.join(storageDir, 'page_translations.json'),
  translationJobs: path.join(storageDir, 'translation_jobs.json'),
  seoJobs: path.join(storageDir, 'seo_jobs.json'),
  seoRoutes: path.join(storageDir, 'seo_routes.json'),
  operationLogs: path.join(storageDir, 'operation_logs.json'),
  loginLogs: path.join(storageDir, 'login_logs.json'),
  systemSettings: path.join(storageDir, 'system_settings.json'),
  languages: path.join(storageDir, 'languages.json')
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

function assert(condition, message, issues) {
  if (!condition) {
    issues.push(message);
  }
}

function seedStorage() {
  const now = '2026-06-12 09:00:00';

  writeJson(files.products, [
    {
      id: 101,
      category_id: 1,
      sku: 'P-101',
      name_zh: '产品 Live V1',
      summary_zh: '产品摘要 V1',
      content_zh: '<p>产品正文 V1</p>',
      business_status: 'on_sale',
      publish_status: 'published',
      translation_status: 'completed',
      seo_status: 'generated',
      is_home_featured: 1,
      manual_sort: 120,
      slug: 'live-product',
      seo_title: '产品 Live V1',
      seo_keywords: '产品',
      seo_description: '产品摘要 V1',
      publish_time: now,
      created_by: 1,
      updated_by: 1,
      created_at: now,
      updated_at: now
    }
  ]);

  writeJson(files.solutions, [
    {
      id: 201,
      category_id: 1,
      name_zh: '方案 Live V1',
      summary_zh: '方案摘要 V1',
      content_zh: '<p>方案正文 V1</p>',
      flow_text_zh: '流程 V1',
      capacity_text_zh: '1000 pcs/h',
      manual_asset_id: null,
      publish_status: 'published',
      translation_status: 'completed',
      seo_status: 'generated',
      is_home_featured: 1,
      manual_sort: 88,
      slug: 'live-solution',
      seo_title: '方案 Live V1',
      seo_keywords: '方案',
      seo_description: '方案摘要 V1',
      publish_time: now,
      created_by: 1,
      updated_by: 1,
      created_at: now,
      updated_at: now
    }
  ]);

  writeJson(files.articles, [
    {
      id: 301,
      category_id: 1,
      content_type: 'case',
      title_zh: '案例 Live V1',
      summary_zh: '案例摘要 V1',
      content_zh: '<p>案例正文 V1</p>',
      country_code: 'AE',
      case_tags: '交付',
      related_solution_ids: '[201]',
      related_product_ids: '[101]',
      publish_status: 'published',
      translation_status: 'completed',
      seo_status: 'generated',
      is_home_featured: 1,
      manual_sort: 66,
      slug: 'live-article',
      seo_title: '案例 Live V1',
      seo_keywords: '案例',
      seo_description: '案例摘要 V1',
      publish_time: now,
      created_by: 1,
      updated_by: 1,
      created_at: now,
      updated_at: now
    }
  ]);

  writeJson(files.pages, [
    {
      id: 401,
      page_type: 'landing',
      title_zh: '单页 Live V1',
      summary_zh: '单页摘要 V1',
      content_zh: '<p>单页正文 V1</p>',
      publish_status: 'published',
      translation_status: 'completed',
      seo_status: 'generated',
      slug: 'live-page',
      seo_title: '单页 Live V1',
      seo_keywords: '单页',
      seo_description: '单页摘要 V1',
      publish_time: now,
      created_by: 1,
      updated_by: 1,
      created_at: now,
      updated_at: now
    }
  ]);

  writeJson(files.productTranslations, []);
  writeJson(files.solutionTranslations, []);
  writeJson(files.articleTranslations, []);
  writeJson(files.pageTranslations, []);
  writeJson(files.translationJobs, []);
  writeJson(files.seoJobs, []);
  writeJson(files.seoRoutes, []);
  writeJson(files.operationLogs, []);
  writeJson(files.loginLogs, []);
  writeJson(files.languages, [
    { id: 1, code: 'zh', name: '简体中文', is_default: 1, is_enabled: 1, sort: 100 },
    { id: 2, code: 'en', name: 'English', is_default: 0, is_enabled: 1, sort: 99 }
  ]);
  writeJson(files.systemSettings, {
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
}

function main() {
  const backups = {};
  Object.entries(files).forEach(([key, filePath]) => {
    backups[key] = backup(filePath);
  });

  try {
    seedStorage();

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

      $operator = ['id' => 1, 'username' => 'admin', 'nickname' => 'Admin'];
      $productService = new app\\service\\content\\ProductService();
      $solutionService = new app\\service\\content\\SolutionService();
      $articleService = new app\\service\\content\\ArticleService();
      $pageService = new app\\service\\content\\PageService();
      $publicSiteService = new app\\service\\content\\PublicSiteService();

      $productService->publish(101, 'published', $operator);
      $solutionService->publish(201, 'published', $operator);
      $articleService->publish(301, 'published', $operator);
      $pageService->publish(401, 'published', $operator);

      $productService->update(101, ['name_zh' => '产品 Draft V2', 'summary_zh' => '产品摘要 V2'], $operator);
      $solutionService->update(201, ['name_zh' => '方案 Draft V2', 'summary_zh' => '方案摘要 V2'], $operator);
      $articleService->update(301, ['title_zh' => '案例 Draft V2', 'summary_zh' => '案例摘要 V2'], $operator);
      $pageService->update(401, ['title_zh' => '单页 Draft V2', 'summary_zh' => '单页摘要 V2'], $operator);

      $result = [
        'product_public_after_draft' => $publicSiteService->productDetail('live-product'),
        'solution_public_after_draft' => $publicSiteService->solutionDetail('live-solution'),
        'article_public_after_draft' => $publicSiteService->articleDetail('live-article'),
        'page_public_after_draft' => $publicSiteService->pageDetail('live-page'),
        'product_repo_after_draft' => (new app\\repository\\ProductRepository())->find(101),
        'solution_repo_after_draft' => (new app\\repository\\SolutionRepository())->find(201),
        'article_repo_after_draft' => (new app\\repository\\ArticleRepository())->find(301),
        'page_repo_after_draft' => (new app\\repository\\PageRepository())->find(401),
        'product_workflow' => $productService->workflow(101),
        'solution_workflow' => $solutionService->workflow(201),
        'article_workflow' => $articleService->workflow(301),
        'page_workflow' => $pageService->workflow(401),
      ];

      $productService->restoreLive(101, $operator);
      $solutionService->restoreLive(201, $operator);
      $articleService->restoreLive(301, $operator);
      $pageService->restoreLive(401, $operator);

      $result['product_repo_after_restore'] = (new app\\repository\\ProductRepository())->find(101);
      $result['solution_repo_after_restore'] = (new app\\repository\\SolutionRepository())->find(201);
      $result['article_repo_after_restore'] = (new app\\repository\\ArticleRepository())->find(301);
      $result['page_repo_after_restore'] = (new app\\repository\\PageRepository())->find(401);
      $result['product_public_after_restore'] = $publicSiteService->productDetail('live-product');

      $productService->publish(101, 'offline', $operator);
      try {
        $publicSiteService->productDetail('live-product');
        $result['product_hidden_when_offline'] = false;
      } catch (Throwable $exception) {
        $result['product_hidden_when_offline'] = true;
      }

      echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8'
    }));

    const issues = [];

    assert(String(payload.product_public_after_draft?.name || '') === '产品 Live V1', '产品草稿修改后前台仍应显示 live 版本', issues);
    assert(String(payload.solution_public_after_draft?.name || '') === '方案 Live V1', '方案草稿修改后前台仍应显示 live 版本', issues);
    assert(String(payload.article_public_after_draft?.title || '') === '案例 Live V1', '文章草稿修改后前台仍应显示 live 版本', issues);
    assert(String(payload.page_public_after_draft?.title || '') === '单页 Live V1', '单页草稿修改后前台仍应显示 live 版本', issues);

    assert(String(payload.product_repo_after_draft?.name_zh || '') === '产品 Draft V2', '产品后台草稿应保留最新编辑内容', issues);
    assert(String(payload.solution_repo_after_draft?.name_zh || '') === '方案 Draft V2', '方案后台草稿应保留最新编辑内容', issues);
    assert(String(payload.article_repo_after_draft?.title_zh || '') === '案例 Draft V2', '文章后台草稿应保留最新编辑内容', issues);
    assert(String(payload.page_repo_after_draft?.title_zh || '') === '单页 Draft V2', '单页后台草稿应保留最新编辑内容', issues);

    assert(Number(payload.product_workflow?.has_unpublished_changes || 0) === 1, '产品 workflow 应识别未发布变更', issues);
    assert(Number(payload.solution_workflow?.has_unpublished_changes || 0) === 1, '方案 workflow 应识别未发布变更', issues);
    assert(Number(payload.article_workflow?.has_unpublished_changes || 0) === 1, '文章 workflow 应识别未发布变更', issues);
    assert(Number(payload.page_workflow?.has_unpublished_changes || 0) === 1, '单页 workflow 应识别未发布变更', issues);
    assert(String(payload.product_workflow?.live_updated_at || '') !== '', '产品 workflow 应返回最近上线时间', issues);

    assert(String(payload.product_repo_after_restore?.name_zh || '') === '产品 Live V1', '产品 restoreLive 应恢复已发布版本', issues);
    assert(String(payload.solution_repo_after_restore?.name_zh || '') === '方案 Live V1', '方案 restoreLive 应恢复已发布版本', issues);
    assert(String(payload.article_repo_after_restore?.title_zh || '') === '案例 Live V1', '文章 restoreLive 应恢复已发布版本', issues);
    assert(String(payload.page_repo_after_restore?.title_zh || '') === '单页 Live V1', '单页 restoreLive 应恢复已发布版本', issues);
    assert(String(payload.product_public_after_restore?.name || '') === '产品 Live V1', '产品恢复后前台应继续返回 live 版本', issues);
    assert(payload.product_hidden_when_offline === true, '内容下线后前台必须隐藏，即使存在 live 快照', issues);

    const routeContent = fs.readFileSync(path.join(backendRoot, 'route', 'adminapi.php'), 'utf8');
    const productController = fs.readFileSync(path.join(backendRoot, 'app', 'adminapi', 'controller', 'content', 'ProductController.php'), 'utf8');
    const solutionController = fs.readFileSync(path.join(backendRoot, 'app', 'adminapi', 'controller', 'content', 'SolutionController.php'), 'utf8');
    const articleController = fs.readFileSync(path.join(backendRoot, 'app', 'adminapi', 'controller', 'content', 'ArticleController.php'), 'utf8');
    const pageController = fs.readFileSync(path.join(backendRoot, 'app', 'adminapi', 'controller', 'content', 'PageController.php'), 'utf8');

    assert(routeContent.includes("['GET', '/admin/products/{id}/workflow'"), '缺少产品 workflow 路由', issues);
    assert(routeContent.includes("['POST', '/admin/products/{id}/restore-live'"), '缺少产品 restore-live 路由', issues);
    assert(routeContent.includes("['GET', '/admin/solutions/{id}/workflow'"), '缺少方案 workflow 路由', issues);
    assert(routeContent.includes("['POST', '/admin/solutions/{id}/restore-live'"), '缺少方案 restore-live 路由', issues);
    assert(routeContent.includes("['GET', '/admin/articles/{id}/workflow'"), '缺少文章 workflow 路由', issues);
    assert(routeContent.includes("['POST', '/admin/articles/{id}/restore-live'"), '缺少文章 restore-live 路由', issues);
    assert(routeContent.includes("['GET', '/admin/pages/{id}/workflow'"), '缺少单页 workflow 路由', issues);
    assert(routeContent.includes("['POST', '/admin/pages/{id}/restore-live'"), '缺少单页 restore-live 路由', issues);

    assert(/function\s+workflow\s*\(/.test(productController), 'ProductController 缺少 workflow 方法', issues);
    assert(/function\s+restoreLive\s*\(/.test(productController), 'ProductController 缺少 restoreLive 方法', issues);
    assert(/function\s+workflow\s*\(/.test(solutionController), 'SolutionController 缺少 workflow 方法', issues);
    assert(/function\s+restoreLive\s*\(/.test(solutionController), 'SolutionController 缺少 restoreLive 方法', issues);
    assert(/function\s+workflow\s*\(/.test(articleController), 'ArticleController 缺少 workflow 方法', issues);
    assert(/function\s+restoreLive\s*\(/.test(articleController), 'ArticleController 缺少 restoreLive 方法', issues);
    assert(/function\s+workflow\s*\(/.test(pageController), 'PageController 缺少 workflow 方法', issues);
    assert(/function\s+restoreLive\s*\(/.test(pageController), 'PageController 缺少 restoreLive 方法', issues);

    if (issues.length > 0) {
      console.error('Content live publish/restore validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Content live publish/restore validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
