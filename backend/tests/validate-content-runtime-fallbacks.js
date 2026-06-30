const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const pagesPath = path.join(storageDir, 'pages.json');
const aboutPagesPath = path.join(storageDir, 'about_pages.json');
const teamMembersPath = path.join(storageDir, 'team_members.json');
const certificatesPath = path.join(storageDir, 'certificates.json');

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

function main() {
  const pageBackup = backup(pagesPath);
  const aboutBackup = backup(aboutPagesPath);
  const teamBackup = backup(teamMembersPath);
  const certificateBackup = backup(certificatesPath);

  try {
    fs.mkdirSync(storageDir, { recursive: true });
    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

    fs.writeFileSync(pagesPath, JSON.stringify([
      {
        id: 41,
        page_type: 'landing',
        title_zh: 'Runtime Landing',
        summary_zh: 'runtime page summary',
        content_zh: 'runtime page content',
        publish_status: 'published',
        translation_status: 'completed',
        seo_status: 'generated',
        slug: 'runtime-landing',
        seo_title: 'Runtime Landing',
        seo_keywords: 'runtime landing',
        seo_description: 'runtime landing desc',
        publish_time: now,
        created_by: 1,
        updated_by: 1,
        created_at: now,
        updated_at: now
      }
    ], null, 2), 'utf8');

    fs.writeFileSync(aboutPagesPath, JSON.stringify([
      {
        id: 91,
        page_key: 'company-about',
        name_zh: 'Runtime About',
        is_enabled: 1,
        blocks: [
          {
            id: 901,
            block_type: 'text',
            title_zh: 'Runtime Block',
            subtitle_zh: 'runtime subtitle',
            content_zh: 'runtime content',
            sort: 100,
            is_enabled: 1
          }
        ]
      }
    ], null, 2), 'utf8');

    fs.writeFileSync(teamMembersPath, JSON.stringify([
      {
        id: 51,
        name_zh: 'Runtime Team',
        title_zh: 'Sales Manager',
        department_zh: 'International',
        bio_zh: 'runtime bio',
        avatar_asset_id: null,
        email: 'runtime-team@example.com',
        phone: '+86-512-00000000',
        whatsapp: '',
        wechat: '',
        publish_status: 'published',
        translation_status: 'completed',
        is_home_featured: 1,
        manual_sort: 100,
        created_by: 1,
        updated_by: 1,
        created_at: now,
        updated_at: now
      }
    ], null, 2), 'utf8');

    fs.writeFileSync(certificatesPath, JSON.stringify([
      {
        id: 61,
        name_zh: 'Runtime Certificate',
        issuer_zh: 'Runtime Issuer',
        certificate_no: 'RT-001',
        certificate_type: 'quality',
        description_zh: 'runtime certificate desc',
        image_asset_id: null,
        publish_status: 'published',
        translation_status: 'completed',
        seo_status: 'generated',
        is_home_featured: 0,
        manual_sort: 90,
        created_at: now,
        updated_at: now
      }
    ], null, 2), 'utf8');

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

      $pdo = app\\common\\database\\DatabaseManager::instance()->connection();
      $pdo->exec("REPLACE INTO pages (id, page_type, title_zh, content_zh, publish_status, translation_status, seo_status, slug, seo_title, seo_keywords, seo_description, publish_time, created_by, updated_by, created_at, updated_at) VALUES (41, 'landing', 'Runtime Landing', 'runtime page content', 'published', 'completed', 'generated', 'runtime-landing', 'Runtime Landing', 'runtime landing', 'runtime landing desc', NOW(), 1, 1, NOW(), NOW())");
      $pdo->exec("REPLACE INTO team_members (id, name_zh, title_zh, department_zh, bio_zh, email, phone, whatsapp, wechat, publish_status, translation_status, is_home_featured, manual_sort, created_by, updated_by, created_at, updated_at) VALUES (51, 'Runtime Team', 'Sales Manager', 'International', 'runtime bio', 'runtime-team@example.com', '+86-512-00000000', '', '', 'published', 'completed', 1, 100, 1, 1, NOW(), NOW())");
      $pdo->exec("REPLACE INTO certificates (id, name_zh, issuer_zh, certificate_no, certificate_type, description_zh, publish_status, translation_status, seo_status, is_home_featured, manual_sort, created_at, updated_at) VALUES (61, 'Runtime Certificate', 'Runtime Issuer', 'RT-001', 'quality', 'runtime certificate desc', 'published', 'completed', 'generated', 0, 90, NOW(), NOW())");
      $pdo->exec("DELETE FROM about_blocks WHERE about_page_id = 1");
      $pdo->exec("REPLACE INTO about_blocks (id, about_page_id, block_type, title_zh, subtitle_zh, content_zh, extra_config, sort, is_enabled) VALUES (901, 1, 'text', 'Runtime Block', 'runtime subtitle', 'runtime content', '{}', 100, 1)");

      $pageService = new app\\service\\content\\PageService();
      $pageRepository = new app\\repository\\PageRepository();
      $aboutService = new app\\service\\content\\AboutService();
      $teamService = new app\\service\\content\\TeamService();
      $certificateService = new app\\service\\content\\CertificateService();

      $payload = [];

      try {
        $payload['page_list'] = $pageService->list();
        $payload['page_detail'] = $pageService->detail(41);
        $payload['page_slug_exists'] = $pageRepository->slugExists('runtime-landing');
      } catch (Throwable $exception) {
        $payload['page_error'] = $exception->getMessage();
      }

      try {
        $payload['about_page'] = $aboutService->page(1);
      } catch (Throwable $exception) {
        $payload['about_error'] = $exception->getMessage();
      }

      try {
        $payload['team_detail'] = $teamService->detail(51);
      } catch (Throwable $exception) {
        $payload['team_error'] = $exception->getMessage();
      }

      try {
        $payload['certificate_detail'] = $certificateService->detail(61);
      } catch (Throwable $exception) {
        $payload['certificate_error'] = $exception->getMessage();
      }

      echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8'
    }));

    const issues = [];

    if (String(payload.page_detail?.title_zh || '') !== 'Runtime Landing') {
      issues.push(`PageService runtime fallback failed: ${payload.page_error || 'detail mismatch'}`);
    }
    if (!Array.isArray(payload.page_list?.items) || Number(payload.page_list.items[0]?.id || 0) !== 41) {
      issues.push('PageService list must include runtime page records');
    }
    if (payload.page_slug_exists !== true) {
      issues.push('PageRepository slugExists must inspect runtime pages when DB misses');
    }
    if (String(payload.about_page?.blocks?.[0]?.title_zh || '') !== 'Runtime Block') {
      issues.push(`AboutService runtime fallback failed: ${payload.about_error || 'block mismatch'}`);
    }
    if (String(payload.team_detail?.email || '') !== 'runtime-team@example.com') {
      issues.push(`TeamService runtime fallback failed: ${payload.team_error || 'detail mismatch'}`);
    }
    if (String(payload.certificate_detail?.certificate_no || '') !== 'RT-001') {
      issues.push(`CertificateService runtime fallback failed: ${payload.certificate_error || 'detail mismatch'}`);
    }

    if (issues.length > 0) {
      console.error('Content runtime fallback validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Content runtime fallback validation passed.');
  } finally {
    restore(pagesPath, pageBackup);
    restore(aboutPagesPath, aboutBackup);
    restore(teamMembersPath, teamBackup);
    restore(certificatesPath, certificateBackup);
  }
}

main();
