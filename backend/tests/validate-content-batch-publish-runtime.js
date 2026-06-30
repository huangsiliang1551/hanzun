const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  solutions: path.join(storageDir, 'solutions.json'),
  articles: path.join(storageDir, 'articles.json'),
  pages: path.join(storageDir, 'pages.json'),
  languages: path.join(storageDir, 'languages.json'),
  settings: path.join(storageDir, 'system_settings.json'),
  translationJobs: path.join(storageDir, 'translation_jobs.json'),
  seoJobs: path.join(storageDir, 'seo_jobs.json'),
  seoRoutes: path.join(storageDir, 'seo_routes.json'),
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

function writeJson(filePath, data) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf8');
}

function phpPayload() {
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

    $operator = ['id' => 1, 'username' => 'admin', 'nickname' => 'Admin'];
    $solutionService = new app\\service\\content\\SolutionService();
    $articleService = new app\\service\\content\\ArticleService();
    $pageService = new app\\service\\content\\PageService();

    $solutionPublished = $solutionService->batchPublish([201, 202], 'published', $operator);
    $solutionDrafted = $solutionService->batchPublish([202], 'draft', $operator);

    $articlePublished = $articleService->batchPublish([301, 302], 'published', $operator);
    $articleDrafted = $articleService->batchPublish([302], 'draft', $operator);

    $pagePublished = $pageService->batchPublish([401, 402], 'published', $operator);
    $pageDrafted = $pageService->batchPublish([402], 'draft', $operator);

    echo json_encode([
      'solution_published' => $solutionPublished,
      'solution_drafted' => $solutionDrafted,
      'solutions' => (new app\\repository\\SolutionRepository())->list(['page_size' => 20])['items'],
      'article_published' => $articlePublished,
      'article_drafted' => $articleDrafted,
      'articles' => (new app\\repository\\ArticleRepository())->list(['page_size' => 20])['items'],
      'page_published' => $pagePublished,
      'page_drafted' => $pageDrafted,
      'pages' => (new app\\repository\\PageRepository())->list(['page_size' => 20])['items'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const backups = {};
  Object.values(files).forEach((filePath) => {
    backups[filePath] = backup(filePath);
  });

  try {
    const now = '2026-06-12 12:00:00';
    writeJson(files.solutions, [
      { id: 201, category_id: 1, name_zh: '方案一', summary_zh: '', content_zh: '', flow_text_zh: '', capacity_text_zh: '1000', manual_asset_id: null, publish_status: 'draft', translation_status: 'pending', seo_status: 'pending', is_home_featured: 0, manual_sort: 50, slug: 'solution-201', seo_title: '方案一', seo_keywords: '方案一', seo_description: 'desc', publish_time: null, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
      { id: 202, category_id: 1, name_zh: '方案二', summary_zh: '', content_zh: '', flow_text_zh: '', capacity_text_zh: '2000', manual_asset_id: null, publish_status: 'offline', translation_status: 'pending', seo_status: 'pending', is_home_featured: 0, manual_sort: 40, slug: 'solution-202', seo_title: '方案二', seo_keywords: '方案二', seo_description: 'desc', publish_time: null, created_by: 1, updated_by: 1, created_at: now, updated_at: now }
    ]);
    writeJson(files.articles, [
      { id: 301, category_id: 1, content_type: 'news', title_zh: '文章一', summary_zh: '', content_zh: '', country_code: '', case_tags: '', related_solution_ids: '[]', related_product_ids: '[]', publish_status: 'draft', translation_status: 'pending', seo_status: 'pending', is_home_featured: 0, manual_sort: 30, slug: 'article-301', seo_title: '文章一', seo_keywords: '文章一', seo_description: 'desc', publish_time: null, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
      { id: 302, category_id: 1, content_type: 'case', title_zh: '文章二', summary_zh: '', content_zh: '', country_code: 'MX', case_tags: 'case', related_solution_ids: '[201]', related_product_ids: '[]', publish_status: 'offline', translation_status: 'pending', seo_status: 'pending', is_home_featured: 0, manual_sort: 20, slug: 'article-302', seo_title: '文章二', seo_keywords: '文章二', seo_description: 'desc', publish_time: null, created_by: 1, updated_by: 1, created_at: now, updated_at: now }
    ]);
    writeJson(files.pages, [
      { id: 401, page_type: 'landing', title_zh: '页面一', summary_zh: '', content_zh: '', publish_status: 'draft', translation_status: 'pending', seo_status: 'pending', slug: 'page-401', seo_title: '页面一', seo_keywords: '页面一', seo_description: 'desc', publish_time: null, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
      { id: 402, page_type: 'campaign', title_zh: '页面二', summary_zh: '', content_zh: '', publish_status: 'offline', translation_status: 'pending', seo_status: 'pending', slug: 'page-402', seo_title: '页面二', seo_keywords: '页面二', seo_description: 'desc', publish_time: null, created_by: 1, updated_by: 1, created_at: now, updated_at: now }
    ]);
    writeJson(files.languages, [
      { id: 1, code: 'zh', name: '简体中文', is_default: 1, is_enabled: 1, sort: 100 },
      { id: 2, code: 'en', name: 'English', is_default: 0, is_enabled: 1, sort: 99 }
    ]);
    writeJson(files.settings, { deepseek: { config: { translation_enabled: 0, seo_enabled: 0 } } });
    writeJson(files.translationJobs, []);
    writeJson(files.seoJobs, []);
    writeJson(files.seoRoutes, []);
    writeJson(files.operationLogs, []);

    const routeContent = fs.readFileSync(path.join(backendRoot, 'route', 'adminapi.php'), 'utf8');
    const solutionController = fs.readFileSync(path.join(backendRoot, 'app', 'adminapi', 'controller', 'content', 'SolutionController.php'), 'utf8');
    const articleController = fs.readFileSync(path.join(backendRoot, 'app', 'adminapi', 'controller', 'content', 'ArticleController.php'), 'utf8');
    const pageController = fs.readFileSync(path.join(backendRoot, 'app', 'adminapi', 'controller', 'content', 'PageController.php'), 'utf8');
    const solutionServiceSource = fs.readFileSync(path.join(backendRoot, 'app', 'service', 'content', 'SolutionService.php'), 'utf8');
    const articleServiceSource = fs.readFileSync(path.join(backendRoot, 'app', 'service', 'content', 'ArticleService.php'), 'utf8');
    const pageServiceSource = fs.readFileSync(path.join(backendRoot, 'app', 'service', 'content', 'PageService.php'), 'utf8');

    const staticIssues = [];
    if (!/\/admin\/solutions\/batch-publish/.test(routeContent)) staticIssues.push('missing solution batch-publish route');
    if (!/\/admin\/articles\/batch-publish/.test(routeContent)) staticIssues.push('missing article batch-publish route');
    if (!/\/admin\/pages\/batch-publish/.test(routeContent)) staticIssues.push('missing page batch-publish route');
    if (!/function\s+batchPublish\s*\(/.test(solutionController)) staticIssues.push('SolutionController must expose batchPublish()');
    if (!/function\s+batchPublish\s*\(/.test(articleController)) staticIssues.push('ArticleController must expose batchPublish()');
    if (!/function\s+batchPublish\s*\(/.test(pageController)) staticIssues.push('PageController must expose batchPublish()');
    if (!/function\s+batchPublish\s*\(/.test(solutionServiceSource)) staticIssues.push('SolutionService must expose batchPublish()');
    if (!/function\s+batchPublish\s*\(/.test(articleServiceSource)) staticIssues.push('ArticleService must expose batchPublish()');
    if (!/function\s+batchPublish\s*\(/.test(pageServiceSource)) staticIssues.push('PageService must expose batchPublish()');
    if (staticIssues.length > 0) {
      console.error('Content batch publish static validation failed:');
      staticIssues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    const payload = JSON.parse(execFileSync('php', ['-r', phpPayload()], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: { ...process.env, PREFER_RUNTIME_STORAGE: '0' }
    }));

    const issues = [];
    const solution201 = (payload.solutions || []).find((item) => Number(item.id) === 201) || {};
    const solution202 = (payload.solutions || []).find((item) => Number(item.id) === 202) || {};
    const article301 = (payload.articles || []).find((item) => Number(item.id) === 301) || {};
    const article302 = (payload.articles || []).find((item) => Number(item.id) === 302) || {};
    const page401 = (payload.pages || []).find((item) => Number(item.id) === 401) || {};
    const page402 = (payload.pages || []).find((item) => Number(item.id) === 402) || {};

    if (Number(payload.solution_published?.updated_count || 0) !== 2) issues.push('SolutionService::batchPublish must update all selected solutions');
    if (String(solution201.publish_status || '') !== 'published') issues.push('Solution batch publish must set first solution to published');
    if (String(solution202.publish_status || '') !== 'draft') issues.push('Solution batch draft must set selected solution back to draft');
    if (!String(solution201.publish_time || '').trim()) issues.push('Solution batch publish must set publish_time');

    if (Number(payload.article_published?.updated_count || 0) !== 2) issues.push('ArticleService::batchPublish must update all selected articles');
    if (String(article301.publish_status || '') !== 'published') issues.push('Article batch publish must set first article to published');
    if (String(article302.publish_status || '') !== 'draft') issues.push('Article batch draft must set selected article back to draft');
    if (!String(article301.publish_time || '').trim()) issues.push('Article batch publish must set publish_time');

    if (Number(payload.page_published?.updated_count || 0) !== 2) issues.push('PageService::batchPublish must update all selected pages');
    if (String(page401.publish_status || '') !== 'published') issues.push('Page batch publish must set first page to published');
    if (String(page402.publish_status || '') !== 'draft') issues.push('Page batch draft must set selected page back to draft');
    if (!String(page401.publish_time || '').trim()) issues.push('Page batch publish must set publish_time');

    const logs = JSON.parse(fs.readFileSync(files.operationLogs, 'utf8'));
    const solutionLogs = Array.isArray(logs) ? logs.filter((item) => String(item.action_point || '') === 'solution.batch_publish').length : 0;
    const articleLogs = Array.isArray(logs) ? logs.filter((item) => String(item.action_point || '') === 'article.batch_publish').length : 0;
    const pageLogs = Array.isArray(logs) ? logs.filter((item) => String(item.action_point || '') === 'page.batch_publish').length : 0;
    if (solutionLogs < 2) issues.push('Solution batch actions must write operation logs');
    if (articleLogs < 2) issues.push('Article batch actions must write operation logs');
    if (pageLogs < 2) issues.push('Page batch actions must write operation logs');

    if (issues.length > 0) {
      console.error('Content batch publish runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Content batch publish runtime validation passed.');
  } finally {
    Object.entries(backups).forEach(([filePath, content]) => restore(filePath, content));
  }
}

main();
