const fs = require('fs');
const http = require('http');
const path = require('path');
const { execFile } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const fakePort = 18998;
const filesToBackup = [
  'languages.json',
  'system_settings.json',
  'translation_jobs.json',
  'deepseek_logs.json',
  'operation_logs.json',
  'contact_field_types.json',
  'contact_items.json',
  'contact_field_type_translations.json',
  'contact_item_translations.json',
  'navigation_menus.json',
  'navigation_menu_translations.json',
  'navigation_item_translations.json',
  'about_pages.json',
  'about_block_translations.json',
  'homepage_sections.json',
  'homepage_section_translations.json',
  'team_members.json',
  'team_member_translations.json',
  'certificates.json',
  'certificate_translations.json',
  'media_assets.json'
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

function readJson(file) {
  return JSON.parse(fs.readFileSync(filePath(file), 'utf8'));
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

    $contactService = new app\\service\\system\\ContactService();
    $navigationService = new app\\service\\content\\NavigationService();
    $aboutService = new app\\service\\content\\AboutService();
    $homepageService = new app\\service\\homepage\\HomepageService();
    $teamService = new app\\service\\content\\TeamService();
    $certificateService = new app\\service\\content\\CertificateService();
    $bridge = new app\\service\\content\\ContentEntityBridge();
    $translationRepository = new app\\repository\\TranslationRepository();

    $fieldType = $contactService->createFieldType([
      'field_key' => 'line',
      'name_zh' => 'Line',
      'icon' => 'message',
      'validation_rule' => 'text',
      'sort' => 88,
      'is_enabled' => 1,
    ]);
    $contactItem = $contactService->create([
      'field_type_id' => (int) $fieldType['id'],
      'label_zh' => 'LINE 客服',
      'value' => 'line://hanzun',
      'description_zh' => '海外客服',
      'display_scope' => 'footer',
      'sort' => 12,
      'is_enabled' => 1,
    ]);

    $menu = $navigationService->createMenu([
      'name_zh' => '顶部导航',
      'menu_key' => 'primary',
      'menu_position' => 'header',
      'sort' => 100,
      'is_enabled' => 1,
    ]);
    $menu = $navigationService->updateItems((int) $menu['id'], [[
      'name_zh' => '产品中心',
      'code' => 'products',
      'route_key' => 'products',
      'item_type' => 'manual_url',
      'link_type' => 'manual_url',
      'linked_entity_type' => 'custom_url',
      'linked_entity_id' => null,
      'root_category_id' => null,
      'max_depth' => 1,
      'include_children' => 0,
      'display_mode' => 'plain',
      'url' => '/products',
      'open_in_new_tab' => 0,
      'sort' => 100,
      'is_enabled' => 1,
    ]]);

    $about = $aboutService->updateBlocks(1, [[
      'id' => 8101,
      'block_type' => 'text',
      'title_zh' => '企业概况',
      'subtitle_zh' => '全球烘焙设备制造',
      'content_zh' => '专注海外产线项目。',
      'extra_config' => [],
      'sort' => 100,
      'is_enabled' => 1,
    ]]);

    $section = $homepageService->updateSection(1, [
      'title_zh' => '首页主视觉',
      'subtitle_zh' => '自动翻译首页文案',
      'fetch_mode' => 'fixed_config',
      'extra_config' => ['cta_text' => '立即咨询'],
      'sort' => 100,
      'is_enabled' => 1,
    ]);

    $team = $teamService->create([
      'name_zh' => 'Daniel',
      'title_zh' => '销售经理',
      'department_zh' => '国际销售',
      'bio_zh' => '负责海外客户沟通',
      'avatar_asset_id' => 5,
      'email' => 'daniel@example.com',
      'phone' => '+86-10000000000',
      'whatsapp' => '+86-10000000000',
      'wechat' => 'daniel',
      'publish_status' => 'published',
      'translation_status' => 'pending',
      'is_home_featured' => 1,
      'manual_sort' => 100,
    ], ['id' => 1, 'username' => 'tester', 'nickname' => 'Tester']);

    $certificate = $certificateService->create([
      'name_zh' => 'CE 认证',
      'issuer_zh' => '认证机构',
      'certificate_no' => 'CE-001',
      'certificate_type' => '出口认证',
      'description_zh' => '覆盖欧盟市场',
      'image_asset_id' => 3,
      'publish_status' => 'published',
      'translation_status' => 'pending',
      'seo_status' => 'pending',
      'is_home_featured' => 1,
      'manual_sort' => 100,
    ]);

    $menuItemId = (int) (($menu['items'][0]['id'] ?? 0));
    $aboutBlockId = (int) (($about['blocks'][0]['id'] ?? 0));

    echo json_encode([
      'field_type' => $fieldType,
      'contact_item' => $contactItem,
      'menu' => $menu,
      'menu_item_id' => $menuItemId,
      'about' => $about,
      'about_block_id' => $aboutBlockId,
      'section' => $section,
      'team' => $team,
      'certificate' => $certificate,
      'jobs' => $translationRepository->list(),
      'contact_field_type_translation' => $bridge->translationRecord('contact_field_type', (int) $fieldType['id'], 'en'),
      'contact_item_translation' => $bridge->translationRecord('contact_item', (int) $contactItem['id'], 'en'),
      'navigation_menu_translation' => $bridge->translationRecord('navigation_menu', (int) $menu['id'], 'en'),
      'navigation_item_translation' => $bridge->translationRecord('navigation_item', $menuItemId, 'en'),
      'about_block_translation' => $bridge->translationRecord('about_block', $aboutBlockId, 'en'),
      'homepage_section_translation' => $bridge->translationRecord('homepage_section', (int) $section['id'], 'en'),
      'team_translation' => $bridge->translationRecord('team_member', (int) $team['id'], 'en'),
      'certificate_translation' => $bridge->translationRecord('certificate', (int) $certificate['id'], 'en'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
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
          seo_enabled: 1
        }
      }
    });
    writeJson('translation_jobs.json', []);
    writeJson('deepseek_logs.json', []);
    writeJson('operation_logs.json', []);
    writeJson('contact_field_types.json', []);
    writeJson('contact_items.json', []);
    writeJson('contact_field_type_translations.json', []);
    writeJson('contact_item_translations.json', []);
    writeJson('navigation_menus.json', []);
    writeJson('navigation_menu_translations.json', []);
    writeJson('navigation_item_translations.json', []);
    writeJson('about_pages.json', [{
      id: 1,
      page_key: 'company-about',
      name_zh: '企业介绍',
      is_enabled: 1,
      blocks: []
    }]);
    writeJson('about_block_translations.json', []);
    writeJson('homepage_sections.json', [{
      id: 1,
      section_key: 'hero',
      section_type: 'fixed_config',
      title_zh: '首页主视觉',
      subtitle_zh: '初始文案',
      fetch_mode: 'fixed_config',
      extra_config: JSON.stringify({ cta_text: '了解更多' }),
      sort: 100,
      is_enabled: 1
    }]);
    writeJson('homepage_section_translations.json', []);
    writeJson('team_members.json', []);
    writeJson('team_member_translations.json', []);
    writeJson('certificates.json', []);
    writeJson('certificate_translations.json', []);
    writeJson('media_assets.json', [
      {
        id: 3,
        folder_name: 'certificates',
        storage_disk: 'local',
        file_path: '/assets/images/certificates/cert-1.png',
        file_name: 'cert-1.png',
        file_ext: 'png',
        mime_type: 'image/png',
        file_size: 416650,
        width: 1280,
        height: 920,
        duration_seconds: null,
        alt_text_zh: '证书',
        description_zh: '证书素材',
        status: 1,
        created_at: '2026-06-01 10:00:00',
        updated_at: '2026-06-01 10:00:00'
      },
      {
        id: 5,
        folder_name: 'team',
        storage_disk: 'local',
        file_path: '/assets/images/team/sales-daniel.png',
        file_name: 'sales-daniel.png',
        file_ext: 'png',
        mime_type: 'image/png',
        file_size: 2026652,
        width: 1024,
        height: 1024,
        duration_seconds: null,
        alt_text_zh: '团队成员',
        description_zh: '团队头像',
        status: 1,
        created_at: '2026-06-01 10:00:00',
        updated_at: '2026-06-01 10:00:00'
      }
    ]);

    const payload = JSON.parse(await execPhp(mainPhp()));
    const issues = [];
    const jobMap = new Map(
      (payload.jobs || []).map((item) => [`${item.entity_type}:${item.entity_id}:${item.language_code}`, item])
    );

    const expectedJobs = [
      ['contact_field_type', payload.field_type?.id],
      ['contact_item', payload.contact_item?.id],
      ['navigation_menu', payload.menu?.id],
      ['navigation_item', payload.menu_item_id],
      ['about_block', payload.about_block_id],
      ['homepage_section', payload.section?.id],
      ['team_member', payload.team?.id],
      ['certificate', payload.certificate?.id]
    ];

    expectedJobs.forEach(([entityType, entityId]) => {
      const job = jobMap.get(`${entityType}:${entityId}:en`);
      if (String(job?.status || '') !== 'completed') {
        issues.push(`expected translation job ${entityType}:${entityId}:en to complete automatically`);
      }
    });

    if (!payload.contact_field_type_translation || String(payload.contact_field_type_translation.name || '').indexOf('[EN]') === -1) {
      issues.push('expected contact field type translation to be generated');
    }
    if (!payload.contact_item_translation || String(payload.contact_item_translation.label || '').indexOf('[EN]') === -1) {
      issues.push('expected contact item translation to be generated');
    }
    if (!payload.navigation_menu_translation || String(payload.navigation_menu_translation.name || '').indexOf('[EN]') === -1) {
      issues.push('expected navigation menu translation to be generated');
    }
    if (!payload.navigation_item_translation || String(payload.navigation_item_translation.name || '').indexOf('[EN]') === -1) {
      issues.push('expected navigation item translation to be generated');
    }
    if (!payload.about_block_translation || String(payload.about_block_translation.title || '').indexOf('[EN]') === -1) {
      issues.push('expected about block translation to be generated');
    }
    if (!payload.homepage_section_translation || String(payload.homepage_section_translation.title || '').indexOf('[EN]') === -1) {
      issues.push('expected homepage section translation to be generated');
    }
    if (!payload.team_translation || String(payload.team_translation.name || '').indexOf('[EN]') === -1) {
      issues.push('expected team member translation to be generated');
    }
    if (!payload.certificate_translation || String(payload.certificate_translation.name || '').indexOf('[EN]') === -1) {
      issues.push('expected certificate translation to be generated');
    }
    if (String(payload.team?.translation_status || '') !== 'completed') {
      issues.push(`expected team member translation_status to be completed, got ${payload.team?.translation_status}`);
    }
    if (String(payload.certificate?.translation_status || '') !== 'completed') {
      issues.push(`expected certificate translation_status to be completed, got ${payload.certificate?.translation_status}`);
    }

    const deepseekLogs = readJson('deepseek_logs.json');
    if (!Array.isArray(deepseekLogs) || deepseekLogs.length < expectedJobs.length) {
      issues.push('expected deepseek logs to record shared translation calls');
    }

    if (issues.length > 0) {
      console.error('Shared translation pipeline runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Shared translation pipeline runtime validation passed.');
  } finally {
    await new Promise((resolve) => server.close(resolve));
    Object.entries(backups).forEach(([file, content]) => restore(file, content));
  }
}

main().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
