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
  team: path.join(storageDir, 'team_members.json'),
  certificates: path.join(storageDir, 'certificates.json'),
  operationLogs: path.join(storageDir, 'operation_logs.json'),
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
    const now = '2026-06-12 12:00:00';

    writeJson(files.products, [
      { id: 101, category_id: 1, sku: 'P-101', name_zh: '待删产品', summary_zh: '', content_zh: '', business_status: 'on_sale', publish_status: 'draft', translation_status: 'pending', seo_status: 'pending', is_home_featured: 0, manual_sort: 10, slug: 'delete-product', seo_title: '待删产品', seo_keywords: '待删产品', seo_description: '待删产品', publish_time: null, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
      { id: 102, category_id: 1, sku: 'P-102', name_zh: '保留产品', summary_zh: '', content_zh: '', business_status: 'on_sale', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', is_home_featured: 1, manual_sort: 20, slug: 'keep-product', seo_title: '保留产品', seo_keywords: '保留产品', seo_description: '保留产品', publish_time: now, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
    ]);
    writeJson(files.solutions, [
      { id: 201, category_id: 1, name_zh: '待删方案', summary_zh: '', content_zh: '', flow_text_zh: '', capacity_text_zh: '1000 pcs/h', manual_asset_id: null, publish_status: 'draft', translation_status: 'pending', seo_status: 'pending', is_home_featured: 0, manual_sort: 10, slug: 'delete-solution', seo_title: '待删方案', seo_keywords: '待删方案', seo_description: '待删方案', publish_time: null, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
      { id: 202, category_id: 1, name_zh: '保留方案', summary_zh: '', content_zh: '', flow_text_zh: '', capacity_text_zh: '2000 pcs/h', manual_asset_id: null, publish_status: 'published', translation_status: 'completed', seo_status: 'generated', is_home_featured: 1, manual_sort: 20, slug: 'keep-solution', seo_title: '保留方案', seo_keywords: '保留方案', seo_description: '保留方案', publish_time: now, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
    ]);
    writeJson(files.articles, [
      { id: 301, category_id: 1, content_type: 'case', title_zh: '待删案例', summary_zh: '', content_zh: '', country_code: 'MX', case_tags: 'case', related_solution_ids: '[]', related_product_ids: '[]', publish_status: 'draft', translation_status: 'pending', seo_status: 'pending', is_home_featured: 0, manual_sort: 10, slug: 'delete-article', seo_title: '待删案例', seo_keywords: '待删案例', seo_description: '待删案例', publish_time: null, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
      { id: 302, category_id: 1, content_type: 'news', title_zh: '保留文章', summary_zh: '', content_zh: '', country_code: '', case_tags: '', related_solution_ids: '[]', related_product_ids: '[]', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', is_home_featured: 1, manual_sort: 20, slug: 'keep-article', seo_title: '保留文章', seo_keywords: '保留文章', seo_description: '保留文章', publish_time: now, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
    ]);
    writeJson(files.pages, [
      { id: 401, page_type: 'landing', title_zh: '待删页面', summary_zh: '', content_zh: '', publish_status: 'draft', translation_status: 'pending', seo_status: 'pending', slug: 'delete-page', seo_title: '待删页面', seo_keywords: '待删页面', seo_description: '待删页面', publish_time: null, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
      { id: 402, page_type: 'landing', title_zh: '保留页面', summary_zh: '', content_zh: '', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', slug: 'keep-page', seo_title: '保留页面', seo_keywords: '保留页面', seo_description: '保留页面', publish_time: now, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
    ]);
    writeJson(files.team, [
      { id: 501, name_zh: '待删成员', title_zh: 'Sales', department_zh: 'Overseas', bio_zh: '', avatar_asset_id: null, email: 'delete@example.com', phone: '', whatsapp: '', wechat: '', publish_status: 'draft', translation_status: 'pending', is_home_featured: 0, manual_sort: 10, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
      { id: 502, name_zh: '保留成员', title_zh: 'Sales', department_zh: 'Overseas', bio_zh: '', avatar_asset_id: null, email: 'keep@example.com', phone: '', whatsapp: '', wechat: '', publish_status: 'published', translation_status: 'completed', is_home_featured: 1, manual_sort: 20, created_by: 1, updated_by: 1, created_at: now, updated_at: now },
    ]);
    writeJson(files.certificates, [
      { id: 601, name_zh: '待删证书', issuer_zh: 'Org', certificate_no: 'D-001', certificate_type: 'quality', description_zh: '', image_asset_id: null, publish_status: 'draft', translation_status: 'pending', seo_status: 'pending', is_home_featured: 0, manual_sort: 10, created_at: now, updated_at: now },
      { id: 602, name_zh: '保留证书', issuer_zh: 'Org', certificate_no: 'K-001', certificate_type: 'quality', description_zh: '', image_asset_id: null, publish_status: 'published', translation_status: 'completed', seo_status: 'generated', is_home_featured: 1, manual_sort: 20, created_at: now, updated_at: now },
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

      $operator = ['id' => 1, 'username' => 'admin', 'nickname' => 'Admin'];
      $productService = new app\\service\\content\\ProductService();
      $solutionService = new app\\service\\content\\SolutionService();
      $articleService = new app\\service\\content\\ArticleService();
      $pageService = new app\\service\\content\\PageService();
      $teamService = new app\\service\\content\\TeamService();
      $certificateService = new app\\service\\content\\CertificateService();
      $logService = new app\\service\\log\\OperationLogService();

      $deletedProduct = $productService->remove(101, $operator);
      $deletedSolution = $solutionService->remove(201, $operator);
      $deletedArticle = $articleService->remove(301, $operator);
      $deletedPage = $pageService->remove(401, $operator);
      $deletedTeam = $teamService->remove(501, $operator);
      $deletedCertificate = $certificateService->remove(601, $operator);

      echo json_encode([
        'deleted_product' => $deletedProduct,
        'deleted_solution' => $deletedSolution,
        'deleted_article' => $deletedArticle,
        'deleted_page' => $deletedPage,
        'deleted_team' => $deletedTeam,
        'deleted_certificate' => $deletedCertificate,
        'remaining_products' => (new app\\repository\\ProductRepository())->list()['items'] ?? [],
        'remaining_solutions' => (new app\\repository\\SolutionRepository())->list()['items'] ?? [],
        'remaining_articles' => (new app\\repository\\ArticleRepository())->list()['items'] ?? [],
        'remaining_pages' => (new app\\repository\\PageRepository())->list()['items'] ?? [],
        'remaining_team' => (new app\\repository\\TeamRepository())->list(),
        'remaining_certificates' => (new app\\repository\\CertificateRepository())->list(),
        'operation_logs' => $logService->listOperations()
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8',
    }));

    const issues = [];

    if (Number(payload.deleted_product?.id || 0) !== 101) {
      issues.push('ProductService::remove must return deleted product');
    }
    if ((payload.remaining_products || []).some((item) => Number(item.id) === 101)) {
      issues.push('ProductService::remove must remove product from list');
    }
    if (Number(payload.deleted_solution?.id || 0) !== 201) {
      issues.push('SolutionService::remove must return deleted solution');
    }
    if ((payload.remaining_solutions || []).some((item) => Number(item.id) === 201)) {
      issues.push('SolutionService::remove must remove solution from list');
    }
    if (Number(payload.deleted_article?.id || 0) !== 301) {
      issues.push('ArticleService::remove must return deleted article');
    }
    if ((payload.remaining_articles || []).some((item) => Number(item.id) === 301)) {
      issues.push('ArticleService::remove must remove article from list');
    }
    if (Number(payload.deleted_page?.id || 0) !== 401) {
      issues.push('PageService::remove must return deleted page');
    }
    if ((payload.remaining_pages || []).some((item) => Number(item.id) === 401)) {
      issues.push('PageService::remove must remove page from list');
    }
    if (Number(payload.deleted_team?.id || 0) !== 501) {
      issues.push('TeamService::remove must return deleted team member');
    }
    if ((payload.remaining_team || []).some((item) => Number(item.id) === 501)) {
      issues.push('TeamService::remove must remove team member from list');
    }
    if (Number(payload.deleted_certificate?.id || 0) !== 601) {
      issues.push('CertificateService::remove must return deleted certificate');
    }
    if ((payload.remaining_certificates || []).some((item) => Number(item.id) === 601)) {
      issues.push('CertificateService::remove must remove certificate from list');
    }

    const logs = payload.operation_logs && Array.isArray(payload.operation_logs.items)
      ? payload.operation_logs.items
      : (Array.isArray(payload.operation_logs) ? payload.operation_logs : []);
    const actionPoints = logs.map((item) => String(item.action_point || ''));
    [
      'product.delete',
      'solution.delete',
      'article.delete',
      'page.delete',
      'team.delete',
      'certificate.delete',
    ].forEach((actionPoint) => {
      if (!actionPoints.includes(actionPoint)) {
        issues.push(`OperationLogService missing action log: ${actionPoint}`);
      }
    });

    if (issues.length > 0) {
      console.error('Content delete runtime validation failed:');
      issues.forEach((issue) => console.error('- ' + issue));
      process.exit(1);
    }

    console.log('Content delete runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
