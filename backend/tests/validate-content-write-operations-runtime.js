const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  pages: path.join(storageDir, 'pages.json'),
  about: path.join(storageDir, 'about_pages.json'),
  team: path.join(storageDir, 'team_members.json'),
  certificates: path.join(storageDir, 'certificates.json'),
  media: path.join(storageDir, 'media_assets.json'),
  operationLogs: path.join(storageDir, 'operation_logs.json'),
  settings: path.join(storageDir, 'system_settings.json'),
  translationJobs: path.join(storageDir, 'translation_jobs.json'),
  seoJobs: path.join(storageDir, 'seo_jobs.json'),
  seoRoutes: path.join(storageDir, 'seo_routes.json'),
  pageTranslations: path.join(storageDir, 'page_translations.json')
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
    fs.mkdirSync(storageDir, { recursive: true });
    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');

    writeJson(files.pages, []);
    writeJson(files.about, [
      {
        id: 91,
        page_key: 'company-about',
        name_zh: 'Runtime About',
        is_enabled: 1,
        blocks: [
          {
            id: 901,
            block_type: 'text',
            title_zh: 'Origin Block',
            subtitle_zh: 'origin subtitle',
            content_zh: 'origin content',
            sort: 100,
            is_enabled: 1
          }
        ]
      }
    ]);
    writeJson(files.team, []);
    writeJson(files.certificates, []);
    writeJson(files.media, [
      {
        id: 801,
        folder_name: 'team',
        storage_disk: 'local',
        file_path: '/uploads/team/runtime-team.png',
        file_name: 'runtime-team.png',
        file_ext: 'png',
        mime_type: 'image/png',
        file_size: 1024,
        width: 256,
        height: 256,
        duration_seconds: null,
        alt_text_zh: 'Runtime team image',
        description_zh: 'runtime team image',
        status: 1,
        created_at: now,
        updated_at: now
      },
      {
        id: 802,
        folder_name: 'certificates',
        storage_disk: 'local',
        file_path: '/uploads/certificates/runtime-cert.png',
        file_name: 'runtime-cert.png',
        file_ext: 'png',
        mime_type: 'image/png',
        file_size: 2048,
        width: 400,
        height: 300,
        duration_seconds: null,
        alt_text_zh: 'Runtime certificate image',
        description_zh: 'runtime certificate image',
        status: 1,
        created_at: now,
        updated_at: now
      }
    ]);
    writeJson(files.operationLogs, []);
    writeJson(files.translationJobs, []);
    writeJson(files.seoJobs, []);
    writeJson(files.seoRoutes, []);
    writeJson(files.pageTranslations, []);
    writeJson(files.settings, {
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

    const phpCode = `
      $basePath = getcwd();
      require_once $basePath . '/app/common/bootstrap/Autoloader.php';
      require_once $basePath . '/app/common/bootstrap/EnvLoader.php';
      require_once $basePath . '/app/common/bootstrap/helpers.php';
      app\\common\\bootstrap\\Autoloader::register($basePath);
      app\\common\\bootstrap\\EnvLoader::load($basePath . '/.env');
      
      error_reporting(0);
      
      app\\common\\config\\ConfigRepository::instance()->load($basePath . '/config');
      app\\common\\database\\DatabaseManager::instance()->configure(
          app\\common\\config\\ConfigRepository::instance()->get('database.connections.mysql', [])
      );

      $pdo = app\\common\\database\\DatabaseManager::instance()->connection();
      $pdo->exec("REPLACE INTO media_assets (id, folder_name, storage_disk, file_path, file_name, original_name, file_ext, mime_type, file_size, sha1, width, height, status, created_at, updated_at) VALUES (801, 'team', 'local', '/uploads/team/runtime-team.png', 'runtime-team.png', 'runtime-team.png', 'png', 'image/png', 1024, '', 256, 256, 1, NOW(), NOW())");
      $pdo->exec("REPLACE INTO media_assets (id, folder_name, storage_disk, file_path, file_name, original_name, file_ext, mime_type, file_size, sha1, width, height, status, created_at, updated_at) VALUES (802, 'certificates', 'local', '/uploads/certificates/runtime-cert.png', 'runtime-cert.png', 'runtime-cert.png', 'png', 'image/png', 2048, '', 400, 300, 1, NOW(), NOW())");
      $configJson = '{"translation_enabled":0,"seo_enabled":0,"chat_enabled":0}';
      $pdo->exec("REPLACE INTO system_settings (setting_group, setting_key, setting_value, updated_at) VALUES ('deepseek', 'config', '{$configJson}', NOW())");

      $pageService = new app\\service\\content\\PageService();
      $aboutService = new app\\service\\content\\AboutService();
      $teamService = new app\\service\\content\\TeamService();
      $certificateService = new app\\service\\content\\CertificateService();
      $logService = new app\\service\\log\\OperationLogService();
      $seoRepository = new app\\repository\\SeoRepository();

      $createdPage = $pageService->create([
        'page_type' => 'landing',
        'title_zh' => 'Runtime Landing Page',
        'summary_zh' => 'runtime landing summary',
        'content_zh' => '<p>runtime landing content</p>',
        'publish_status' => 'draft',
        'translation_status' => 'pending',
        'seo_status' => 'pending',
        'slug' => '',
        'seo_title' => '',
        'seo_keywords' => '',
        'seo_description' => ''
      ], ['id' => 1, 'username' => 'admin', 'nickname' => 'Admin']);

      $updatedPage = $pageService->update((int) $createdPage['id'], [
        'summary_zh' => 'runtime landing summary updated',
        'content_zh' => '<p>runtime landing content updated</p>'
      ], ['id' => 1, 'username' => 'admin', 'nickname' => 'Admin']);

      $publishedPage = $pageService->publish((int) $createdPage['id'], 'published', ['id' => 1, 'username' => 'admin', 'nickname' => 'Admin']);

      $updatedAbout = $aboutService->updateBlocks(1, [
        [
          'id' => 910,
          'block_type' => 'text',
          'title_zh' => 'Updated Intro',
          'subtitle_zh' => 'updated subtitle',
          'content_zh' => 'updated content',
          'sort' => 100,
          'is_enabled' => 1
        ],
        [
          'id' => 911,
          'block_type' => 'team_list',
          'title_zh' => 'Sales Team',
          'subtitle_zh' => 'team module',
          'content_zh' => '',
          'extra_config' => ['source' => 'team_members'],
          'sort' => 90,
          'is_enabled' => 1
        ]
      ]);

      $createdTeam = $teamService->create([
        'name_zh' => 'Runtime Team',
        'title_zh' => 'Sales Manager',
        'department_zh' => 'International',
        'bio_zh' => 'runtime bio',
        'avatar_asset_id' => 801,
        'email' => 'runtime-team@example.com',
        'phone' => '+86-512-00000000',
        'whatsapp' => '+86-13800000000',
        'wechat' => 'runtime-team',
        'publish_status' => 'draft',
        'translation_status' => 'pending',
        'is_home_featured' => 1,
        'manual_sort' => 50
      ], ['id' => 1, 'username' => 'admin', 'nickname' => 'Admin']);

      $updatedTeam = $teamService->update((int) $createdTeam['id'], [
        'title_zh' => 'Senior Sales Manager',
        'manual_sort' => 80
      ], ['id' => 1, 'username' => 'admin', 'nickname' => 'Admin']);

      $publishedTeam = $teamService->publish((int) $createdTeam['id'], 'published', ['id' => 1, 'username' => 'admin', 'nickname' => 'Admin']);

      $createdCertificate = $certificateService->create([
        'name_zh' => 'Runtime Certificate',
        'issuer_zh' => 'Runtime Issuer',
        'certificate_no' => 'RT-001',
        'certificate_type' => 'quality',
        'description_zh' => 'runtime cert desc',
        'image_asset_id' => 802,
        'publish_status' => 'draft',
        'translation_status' => 'pending',
        'seo_status' => 'pending',
        'is_home_featured' => 0,
        'manual_sort' => 60
      ]);

      $updatedCertificate = $certificateService->update((int) $createdCertificate['id'], [
        'description_zh' => 'runtime cert desc updated',
        'manual_sort' => 95
      ]);

      $publishedCertificate = $certificateService->publish((int) $createdCertificate['id'], 'published');

      echo json_encode([
        'created_page' => $createdPage,
        'updated_page' => $updatedPage,
        'published_page' => $publishedPage,
        'about_page' => $updatedAbout,
        'created_team' => $createdTeam,
        'updated_team' => $updatedTeam,
        'published_team' => $publishedTeam,
        'created_certificate' => $createdCertificate,
        'updated_certificate' => $updatedCertificate,
        'published_certificate' => $publishedCertificate,
        'seo_routes' => $seoRepository->routes(),
        'operation_logs' => $logService->listOperations()
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8'
    }));

    const issues = [];

    if (String(payload.created_page?.title_zh || '') !== 'Runtime Landing Page') {
      issues.push('PageService::create failed to persist page');
    }
    if (String(payload.updated_page?.content_zh || '') !== '<p>runtime landing content updated</p>') {
      issues.push('PageService::update failed to update content');
    }
    if (String(payload.published_page?.publish_status || '') !== 'published') {
      issues.push('PageService::publish failed to set publish_status');
    }
    if (!payload.published_page?.publish_time) {
      issues.push('PageService::publish must set publish_time');
    }
    if (String(payload.published_page?.translation_status || '') !== 'completed') {
      issues.push('PageService with translation disabled should mark translation_status completed');
    }
    if (String(payload.published_page?.seo_status || '') !== 'generated') {
      issues.push('PageService with seo disabled should mark seo_status generated');
    }
    if (!Array.isArray(payload.seo_routes) || payload.seo_routes.length < 2) {
      issues.push('Page pipeline must still generate SEO routes for enabled languages');
    }
    if (String(payload.about_page?.blocks?.[0]?.title_zh || '') !== 'Updated Intro') {
      issues.push('AboutService::updateBlocks failed to replace blocks');
    }
    if (String(payload.about_page?.blocks?.[1]?.block_type || '') !== 'team_list') {
      issues.push('AboutService::updateBlocks failed to persist repeated module types');
    }
    if (Number(payload.created_team?.avatar_asset_id || 0) !== 801) {
      issues.push('TeamService::create failed to persist avatar_asset_id');
    }
    if (String(payload.updated_team?.title_zh || '') !== 'Senior Sales Manager') {
      issues.push('TeamService::update failed to update title');
    }
    if (String(payload.published_team?.publish_status || '') !== 'published') {
      issues.push('TeamService::publish failed to update publish_status');
    }
    if (Number(payload.created_certificate?.image_asset_id || 0) !== 802) {
      issues.push('CertificateService::create failed to persist image_asset_id');
    }
    if (String(payload.updated_certificate?.description_zh || '') !== 'runtime cert desc updated') {
      issues.push('CertificateService::update failed to update description');
    }
    if (String(payload.published_certificate?.publish_status || '') !== 'published') {
      issues.push('CertificateService::publish failed to update publish_status');
    }

    const logs = payload.operation_logs && Array.isArray(payload.operation_logs.items)
      ? payload.operation_logs.items
      : (Array.isArray(payload.operation_logs) ? payload.operation_logs : []);
    const actionPoints = logs.map((item) => String(item.action_point || ''));
    [
      'page.create',
      'page.update',
      'page.publish',
      'about.blocks.update',
      'team.create',
      'team.update',
      'team.publish',
      'certificate.create',
      'certificate.update',
      'certificate.publish'
    ].forEach((actionPoint) => {
      if (!actionPoints.includes(actionPoint)) {
        issues.push(`OperationLogService missing action log: ${actionPoint}`);
      }
    });

    if (issues.length > 0) {
      console.error('Content write operations runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Content write operations runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
