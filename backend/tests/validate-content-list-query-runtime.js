const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const files = {
  products: path.join(storageDir, 'products.json'),
  productCategories: path.join(storageDir, 'product_categories.json'),
  solutions: path.join(storageDir, 'solutions.json'),
  solutionCategories: path.join(storageDir, 'solution_categories.json'),
  articles: path.join(storageDir, 'articles.json'),
  articleCategories: path.join(storageDir, 'article_categories.json'),
  pages: path.join(storageDir, 'pages.json')
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
    writeJson(files.productCategories, [
      { id: 2, parent_id: 0, name_zh: 'Cake Equipment', slug: 'cake-equipment', sort: 100, is_enabled: 1 },
      { id: 3, parent_id: 0, name_zh: 'Bread Equipment', slug: 'bread-equipment', sort: 90, is_enabled: 1 }
    ]);
    writeJson(files.products, [
      { id: 1, category_id: 2, sku: 'P-CAKE-1', name_zh: 'Cake Depositor', business_status: 'on_sale', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', is_home_featured: 1, manual_sort: 90, publish_time: '2026-06-11 12:00:00' },
      { id: 2, category_id: 2, sku: 'P-CAKE-2', name_zh: 'Cake Divider', business_status: 'on_sale', publish_status: 'draft', translation_status: 'pending', seo_status: 'pending', is_home_featured: 0, manual_sort: 80, publish_time: '2026-06-10 12:00:00' },
      { id: 3, category_id: 3, sku: 'P-BREAD-1', name_zh: 'Bread Slicer', business_status: 'discontinued', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', is_home_featured: 0, manual_sort: 70, publish_time: '2026-06-09 12:00:00' }
    ]);

    writeJson(files.solutionCategories, [
      { id: 2, parent_id: 0, name_zh: 'Cake Line', slug: 'cake-line', sort: 100, is_enabled: 1 },
      { id: 3, parent_id: 0, name_zh: 'Bread Line', slug: 'bread-line', sort: 90, is_enabled: 1 }
    ]);
    writeJson(files.solutions, [
      { id: 1, category_id: 2, name_zh: 'Cake Turnkey Line', capacity_text_zh: '6000 pcs/h', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', is_home_featured: 1, manual_sort: 100, manual_asset_id: 5, publish_time: '2026-06-11 12:00:00' },
      { id: 2, category_id: 3, name_zh: 'Bread Turnkey Line', capacity_text_zh: '4000 pcs/h', publish_status: 'draft', translation_status: 'pending', seo_status: 'pending', is_home_featured: 0, manual_sort: 60, manual_asset_id: null, publish_time: '2026-06-09 12:00:00' }
    ]);

    writeJson(files.articleCategories, [
      { id: 1, parent_id: 0, name_zh: 'Expo News', content_type_scope: 'news', sort: 100, is_enabled: 1 },
      { id: 2, parent_id: 0, name_zh: 'Customer Cases', content_type_scope: 'case', sort: 90, is_enabled: 1 }
    ]);
    writeJson(files.articles, [
      { id: 1, category_id: 1, content_type: 'news', title_zh: 'Germany Expo Update', country_code: 'DE', case_tags: '', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', is_home_featured: 1, manual_sort: 95, publish_time: '2026-06-11 12:00:00' },
      { id: 2, category_id: 2, content_type: 'case', title_zh: 'UAE Cake Project', country_code: 'AE', case_tags: 'cake,export', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', is_home_featured: 1, manual_sort: 85, publish_time: '2026-06-10 12:00:00' },
      { id: 3, category_id: 2, content_type: 'case', title_zh: 'Mexico Biscuit Project', country_code: 'MX', case_tags: 'biscuit', publish_status: 'draft', translation_status: 'pending', seo_status: 'pending', is_home_featured: 0, manual_sort: 75, publish_time: '2026-06-09 12:00:00' }
    ]);

    writeJson(files.pages, [
      { id: 1, page_type: 'landing', title_zh: 'Cake Line Landing', summary_zh: '', content_zh: '', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', slug: 'cake-line-landing', publish_time: '2026-06-11 12:00:00' },
      { id: 2, page_type: 'campaign', title_zh: 'Expo Campaign', summary_zh: '', content_zh: '', publish_status: 'draft', translation_status: 'pending', seo_status: 'pending', slug: 'expo-page', publish_time: '2026-06-10 12:00:00' },
      { id: 3, page_type: 'page', title_zh: 'About Us', summary_zh: '', content_zh: '', publish_status: 'published', translation_status: 'completed', seo_status: 'generated', slug: 'about-us', publish_time: '2026-06-09 12:00:00' }
    ]);

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

      $productService = new app\\service\\content\\ProductService();
      $solutionService = new app\\service\\content\\SolutionService();
      $articleService = new app\\service\\content\\ArticleService();
      $pageService = new app\\service\\content\\PageService();

      echo json_encode([
        'product_filtered' => $productService->list([
          'publish_status' => 'published',
          'category_id' => 2,
          'keyword' => 'Cake',
          'page' => 1,
          'page_size' => 10,
          'sort_field' => 'manual_sort',
          'sort_order' => 'desc'
        ]),
        'product_paged' => $productService->list([
          'page' => 2,
          'page_size' => 1,
          'sort_field' => 'manual_sort',
          'sort_order' => 'desc'
        ]),
        'solution_filtered' => $solutionService->list([
          'publish_status' => 'published',
          'is_home_featured' => 1,
          'pdf_status' => 1,
          'keyword' => 'Cake'
        ]),
        'article_filtered' => $articleService->list([
          'content_type' => 'case',
          'country_code' => 'AE',
          'page' => 1,
          'page_size' => 5,
          'sort_field' => 'publish_time',
          'sort_order' => 'desc'
        ]),
        'page_filtered' => $pageService->list([
          'publish_status' => 'published',
          'page_type' => 'landing',
          'keyword' => 'Cake',
          'sort_field' => 'publish_time',
          'sort_order' => 'desc'
        ]),
        'page_paged' => $pageService->list([
          'page' => 2,
          'page_size' => 1,
          'sort_field' => 'publish_time',
          'sort_order' => 'desc'
        ])
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: { ...process.env, PREFER_RUNTIME_STORAGE: '0' }
    }));

    const issues = [];

    if (!Array.isArray(payload.product_filtered?.items) || payload.product_filtered.items.length !== 1) {
      issues.push('Product list must support keyword/category/status filtering');
    }
    if (String(payload.product_filtered?.items?.[0]?.sku || '') !== 'P-CAKE-1') {
      issues.push('Product list filter must keep the matching product record');
    }
    if (Number(payload.product_filtered?.pagination?.total || 0) !== 1) {
      issues.push('Product list must return pagination total after filtering');
    }
    if (String(payload.product_paged?.items?.[0]?.sku || '') !== 'P-CAKE-2') {
      issues.push('Product list must support manual sort ordering with pagination');
    }

    if (!Array.isArray(payload.solution_filtered?.items) || payload.solution_filtered.items.length !== 1) {
      issues.push('Solution list must support publish_status/is_home_featured/pdf_status/keyword filters');
    }
    if (String(payload.solution_filtered?.items?.[0]?.name_zh || '') !== 'Cake Turnkey Line') {
      issues.push('Solution list filtering must preserve the matching solution record');
    }

    if (!Array.isArray(payload.article_filtered?.items) || payload.article_filtered.items.length !== 1) {
      issues.push('Article list must support content_type/country filters');
    }
    if (String(payload.article_filtered?.items?.[0]?.title_zh || '') !== 'UAE Cake Project') {
      issues.push('Article list filtering must keep the matching case record');
    }
    if (String(payload.article_filtered?.sort?.field || '') !== 'publish_time' || String(payload.article_filtered?.sort?.order || '') !== 'desc') {
      issues.push('Article list must echo the resolved sort contract');
    }

    if (!Array.isArray(payload.page_filtered?.items) || payload.page_filtered.items.length !== 1) {
      issues.push('Page list must support publish_status/page_type/keyword filters');
    }
    if (String(payload.page_filtered?.items?.[0]?.slug || '') !== 'cake-line-landing') {
      issues.push('Page list filtering must preserve the matching page record');
    }
    if (String(payload.page_paged?.items?.[0]?.slug || '') !== 'expo-page') {
      issues.push('Page list must support pagination with publish_time ordering');
    }
    if (String(payload.page_filtered?.sort?.field || '') !== 'publish_time' || String(payload.page_filtered?.sort?.order || '') !== 'desc') {
      issues.push('Page list must echo the resolved sort contract');
    }

    if (issues.length > 0) {
      console.error('Content list query runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Content list query runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
