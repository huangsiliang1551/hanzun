const { execFileSync } = require('child_process');
const path = require('path');

const backendRoot = path.resolve(__dirname, '..');

function main() {
  const phpCode = `
    $basePath = getcwd();
    putenv('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK=1');
    $_ENV['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';
    $_SERVER['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';
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
    echo json_encode([
      'resolved_language' => $service->resolveLanguage('en', 'en-US,en;q=0.9'),
      'bootstrap' => $service->bootstrap(),
      'bootstrap_en' => $service->bootstrap('en'),
      'navigation' => $service->navigation('header'),
      'navigation_en' => $service->navigation('header', 'en'),
      'homepage' => $service->homepage(),
      'homepage_en' => $service->homepage('en'),
      'about' => $service->about(),
      'about_en' => $service->about('en'),
      'contact' => $service->contact(),
      'contact_en' => $service->contact('en'),
      'products' => $service->products(),
      'products_en' => $service->products('en'),
      'product_detail' => $service->productDetail('cake-depositor'),
      'product_detail_en' => $service->productDetail('cake-depositor', 'en'),
      'solutions' => $service->solutions(),
      'solutions_en' => $service->solutions('en'),
      'solution_detail' => $service->solutionDetail('cake-line'),
      'solution_detail_en' => $service->solutionDetail('cake-line', 'en'),
      'articles' => $service->articles(),
      'articles_en' => $service->articles(null, 'en'),
      'article_detail' => $service->articleDetail('uae-cake-project'),
      'article_detail_en' => $service->articleDetail('uae-cake-project', 'en'),
      'page_detail' => $service->pageDetail('cake-line-landing'),
      'page_detail_en' => $service->pageDetail('cake-line-landing', 'en')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;

  const output = execFileSync('php', ['-r', phpCode], {
    cwd: backendRoot,
    encoding: 'utf8',
  });

  const payload = JSON.parse(output);
  const issues = [];

  if (!Array.isArray(payload.bootstrap?.languages) || payload.bootstrap.languages.length < 2) {
    issues.push('bootstrap must return enabled languages');
  }
  if (String(payload.bootstrap?.site?.site_name || '') === '' || String(payload.bootstrap?.site?.language_strategy || '') === '') {
    issues.push('bootstrap must return public site settings');
  }
  if (!Array.isArray(payload.bootstrap?.navigation) || payload.bootstrap.navigation.length === 0) {
    issues.push('bootstrap must return public navigation');
  }
  if (!Array.isArray(payload.bootstrap?.homepage?.sections) || payload.bootstrap.homepage.sections.length === 0) {
    issues.push('bootstrap must return homepage sections');
  }
  if (!Array.isArray(payload.bootstrap?.contact?.items) || payload.bootstrap.contact.items.length === 0) {
    issues.push('bootstrap must return contact items');
  }
  if (String(payload.resolved_language?.resolved_code || '') !== 'en') {
    issues.push('resolveLanguage must resolve requested and header language to en');
  }

  if (String(payload.navigation?.[0]?.menu_key || '') !== 'main-header') {
    issues.push('navigation(header) must return the header menu first');
  }
  if (!Array.isArray(payload.navigation?.[0]?.items) || String(payload.navigation[0].items[0]?.name_zh || '') === '') {
    issues.push('navigation(header) must return a tree of items');
  }
  if (String(payload.navigation_en?.[0]?.name || '') !== 'Main Navigation' || String(payload.navigation_en?.[0]?.items?.[0]?.name || '') !== 'Products') {
    issues.push('navigation(en) must return translated menu and item names');
  }

  const homepageSections = Array.isArray(payload.homepage?.sections) ? payload.homepage.sections : [];
  const featuredProductsSection = homepageSections.find((item) => item.section_key === 'featured_products');
  if (!featuredProductsSection || !Array.isArray(featuredProductsSection.items) || String(featuredProductsSection.items[0]?.slug || '') !== 'cake-depositor') {
    issues.push('homepage must inject featured published products');
  }
  if (String(featuredProductsSection?.items?.[0]?.cover_image_url || '') === '') {
    issues.push('homepage featured products must expose cover image urls');
  }
  const heroEn = Array.isArray(payload.homepage_en?.sections) ? payload.homepage_en.sections.find((item) => item.section_key === 'hero') : null;
  if (String(heroEn?.title || '') !== 'Hero Banner' || String(heroEn?.extra_config?.cta_text || '') !== 'View Solutions') {
    issues.push('homepage(en) must return translated section copy and CTA text');
  }

  if (String(payload.about?.page?.page_key || '') !== 'company-about') {
    issues.push('about must return the company about page');
  }
  if (String(payload.about_en?.page?.name || '') !== 'About Hanzun') {
    issues.push('about(en) must localize the about page name');
  }
  const teamBlock = Array.isArray(payload.about?.blocks) ? payload.about.blocks.find((item) => item.block_type === 'team_list') : null;
  if (!teamBlock || !Array.isArray(teamBlock.items) || String(teamBlock.items[0]?.name_zh || '') === '') {
    issues.push('about must reuse team member entities');
  }
  const teamBlockEn = Array.isArray(payload.about_en?.blocks) ? payload.about_en.blocks.find((item) => item.block_type === 'team_list') : null;
  if (String(teamBlockEn?.title || '') !== 'Sales Team' || String(teamBlockEn?.items?.[0]?.title || '') !== 'Overseas Sales Manager') {
    issues.push('about(en) must localize about blocks and team members');
  }
  if (String(teamBlock?.items?.[0]?.avatar_asset_url || '') === '') {
    issues.push('about must expose team member avatar asset urls');
  }
  const certificateBlock = Array.isArray(payload.about?.blocks) ? payload.about.blocks.find((item) => item.block_type === 'certificate_list') : null;
  if (String(certificateBlock?.items?.[0]?.image_asset_url || '') === '') {
    issues.push('about must expose certificate image asset urls');
  }

  if (!Array.isArray(payload.contact?.field_types) || !payload.contact.field_types.some((item) => String(item.field_key) === 'email')) {
    issues.push('contact must return enabled contact field types');
  }
  if (String(payload.contact_en?.field_types?.[0]?.name || '') !== 'Email' || String(payload.contact_en?.items?.[0]?.label || '') !== 'Business Email') {
    issues.push('contact(en) must return translated contact labels');
  }

  if (!Array.isArray(payload.products?.items) || String(payload.products.items[0]?.slug || '') !== 'cake-depositor') {
    issues.push('products must return published products');
  }
  if (String(payload.products_en?.categories?.[0]?.name || '') === '' || String(payload.products_en?.categories?.[0]?.name || '') === String(payload.products_en?.categories?.[0]?.name_zh || '')) {
    issues.push('products(en) must return translated product categories');
  }
  if (String(payload.product_detail?.slug || '') !== 'cake-depositor') {
    issues.push('productDetail must support lookup by slug');
  }
  if (String(payload.product_detail_en?.name || '') === '' || String(payload.product_detail_en?.name || '') === String(payload.product_detail_en?.name_zh || '')) {
    issues.push('productDetail(en) must return translated product fields');
  }
  if (String(payload.product_detail?.cover_image_url || payload.product_detail?.cover_asset_url || '') === '') {
    issues.push('productDetail must expose product cover media');
  }

  if (!Array.isArray(payload.solutions?.items) || String(payload.solutions.items[0]?.slug || '') !== 'cake-line') {
    issues.push('solutions must return published solutions');
  }
  if (String(payload.solutions_en?.categories?.[0]?.name || '') === '' || String(payload.solutions_en?.categories?.[0]?.name || '') === String(payload.solutions_en?.categories?.[0]?.name_zh || '')) {
    issues.push('solutions(en) must return translated solution categories');
  }
  if (String(payload.solution_detail?.slug || '') !== 'cake-line') {
    issues.push('solutionDetail must support lookup by slug');
  }
  if (String(payload.solution_detail_en?.name || '') === '' || String(payload.solution_detail_en?.name || '') === String(payload.solution_detail_en?.name_zh || '')) {
    issues.push('solutionDetail(en) must return translated solution fields');
  }
  if (String(payload.solution_detail?.cover_image_url || payload.solution_detail?.cover_asset_url || '') === '') {
    issues.push('solutionDetail must expose solution cover media');
  }

  if (!Array.isArray(payload.articles?.items) || String(payload.article_detail?.slug || '') !== 'uae-cake-project') {
    issues.push('articles/articleDetail must return published articles and cases');
  }
  if (String(payload.articles_en?.categories?.[0]?.name || '') === '' || String(payload.articles_en?.categories?.[0]?.name || '') === String(payload.articles_en?.categories?.[0]?.name_zh || '')) {
    issues.push('articles(en) must return translated article categories');
  }
  if (!Array.isArray(payload.article_detail?.related_solution_ids) || Number(payload.article_detail.related_solution_ids[0] || 0) !== 1) {
    issues.push('articleDetail must decode related solution ids');
  }
  if (String(payload.article_detail_en?.title || '') === '' || String(payload.article_detail_en?.title || '') === String(payload.article_detail_en?.title_zh || '')) {
    issues.push('articleDetail(en) must return translated article fields');
  }
  if (String(payload.article_detail?.cover_image_url || payload.article_detail?.cover_asset_url || '') === '') {
    issues.push('articleDetail must expose article cover media');
  }

  if (String(payload.page_detail?.slug || '') !== 'cake-line-landing') {
    issues.push('pageDetail must support lookup by slug');
  }
  if (String(payload.page_detail_en?.title || '') === '' || String(payload.page_detail_en?.title || '') === String(payload.page_detail_en?.title_zh || '')) {
    issues.push('pageDetail(en) must return translated page fields');
  }

  if (issues.length > 0) {
    console.error('Public site runtime validation failed:');
    issues.forEach((issue) => console.error(`- ${issue}`));
    process.exit(1);
  }

  console.log('Public site runtime validation passed.');
}

main();
