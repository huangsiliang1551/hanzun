const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const crypto = require('crypto');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const files = {
  users: path.join(storageDir, 'admin_users.json'),
  userRoles: path.join(storageDir, 'admin_user_roles.json'),
  roles: path.join(storageDir, 'admin_roles.json'),
  sessions: path.join(storageDir, 'admin_sessions.json'),
  operationLogs: path.join(storageDir, 'operation_logs.json'),
  loginLogs: path.join(storageDir, 'login_logs.json'),
  deepseekLogs: path.join(storageDir, 'deepseek_logs.json'),
  systemSettings: path.join(storageDir, 'system_settings.json'),
  contactFieldTypes: path.join(storageDir, 'contact_field_types.json'),
  contactItems: path.join(storageDir, 'contact_items.json'),
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

function buildPhpPayload() {
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

    $authService = new app\\service\\auth\\AuthService();
    $settingService = new app\\service\\system\\SettingService();
    $contactService = new app\\service\\system\\ContactService();

    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'Codex Runtime Test';

    try {
      $authService->login('admin', 'wrong-password');
    } catch (Throwable $exception) {
    }
    $authService->login('admin', 'admin123456');

    app\\common\\http\\RequestContext::setUser([
      'id' => 1,
      'username' => 'admin',
      'nickname' => 'Admin'
    ]);
    app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
      'POST',
      '/admin/contact-center/field-types',
      [],
      [],
      ['Authorization' => 'Bearer admin-token'],
      'logs-001'
    ));

    $fieldType = $contactService->createFieldType([
      'field_key' => 'line',
      'name_zh' => 'Line',
      'icon' => 'message',
      'validation_rule' => 'text',
      'sort' => 80,
      'is_enabled' => 1,
    ]);

    app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
      'POST',
      '/admin/contact-center/items',
      [],
      [],
      ['Authorization' => 'Bearer admin-token'],
      'logs-002'
    ));

    $contactService->create([
      'field_type_id' => (int) ($fieldType['id'] ?? 0),
      'label_zh' => 'Line 客服',
      'value' => 'line-id-001',
      'description_zh' => '海外即时联系',
      'display_scope' => 'footer',
      'sort' => 70,
      'is_enabled' => 1,
    ]);

    app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
      'POST',
      '/admin/settings/deepseek/test',
      [],
      [],
      ['Authorization' => 'Bearer admin-token'],
      'logs-003'
    ));
    $deepseekTest = $settingService->testDeepseekConnection();

    echo json_encode([
      'operation_logs' => $settingService->operationLogs(),
      'login_logs' => $settingService->loginLogs(),
      'deepseek_logs' => $settingService->deepseekLogs(),
      'deepseek_test' => $deepseekTest,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const backups = {};
  Object.entries(files).forEach(([key, filePath]) => {
    backups[key] = backup(filePath);
  });

  try {
    writeJson(files.users, [
      {
        id: 1,
        username: 'admin',
        password_hash: crypto.createHash('sha256').update('admin123456').digest('hex'),
        nickname: 'Admin',
        email: 'admin@example.com',
        mobile: '13800000000',
        status: 1,
        password_version: 1,
        last_login_at: null,
        last_login_ip: null,
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:00:00'
      }
    ]);
    writeJson(files.userRoles, [
      { user_id: 1, role_id: 1 }
    ]);
    writeJson(files.roles, [
      { id: 1, name: '超级管理员', code: 'super-admin', description: 'all', status: 1, created_at: '2026-06-11 09:00:00', updated_at: '2026-06-11 09:00:00' }
    ]);
    writeJson(files.sessions, {});
    writeJson(files.operationLogs, []);
    writeJson(files.loginLogs, []);
    writeJson(files.deepseekLogs, []);
    writeJson(files.systemSettings, {
      deepseek: {
        config: {
          base_url: 'https://api.deepseek.com/v1',
          model: 'deepseek-chat',
          api_key: '',
          timeout_seconds: 30,
          retry_times: 2,
          chat_enabled: 1,
          translation_enabled: 0,
          seo_enabled: 1
        }
      }
    });
    writeJson(files.contactFieldTypes, [
      {
        id: 1,
        field_key: 'email',
        name_zh: '邮箱',
        icon: 'mail',
        validation_rule: 'email',
        sort: 100,
        is_enabled: 1
      }
    ]);
    writeJson(files.contactItems, []);
    writeJson(files.languages, [
      { id: 1, code: 'zh', name: '简体中文', is_default: 1, is_enabled: 1, sort: 100 },
      { id: 2, code: 'en', name: 'English', is_default: 0, is_enabled: 1, sort: 90 }
    ]);

    const output = execFileSync('php', ['-r', buildPhpPayload()], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '0'
      }
    });

    const payload = JSON.parse(output);
    const operationItems = Array.isArray(payload.operation_logs?.items) ? payload.operation_logs.items : [];
    const loginItems = Array.isArray(payload.login_logs?.items) ? payload.login_logs.items : [];
    const deepseekItems = Array.isArray(payload.deepseek_logs?.items) ? payload.deepseek_logs.items : [];
    const deepseekSummary = payload.deepseek_logs?.summary || {};
    const issues = [];

    const operationActions = operationItems.map((item) => String(item.action_point || ''));
    if (!operationActions.includes('contact.field_type.create')) {
      issues.push('operationLogs() must expose contact field type creation logs');
    }
    if (!operationActions.includes('contact.create')) {
      issues.push('operationLogs() must expose contact item creation logs');
    }
    if (!operationActions.includes('system.deepseek.test')) {
      issues.push('operationLogs() must expose DeepSeek test operation logs');
    }

    const loginStatuses = loginItems.map((item) => Number(item.is_success || 0));
    if (!loginStatuses.includes(0)) {
      issues.push('loginLogs() must include failed login attempts');
    }
    if (!loginStatuses.includes(1)) {
      issues.push('loginLogs() must include successful login attempts');
    }

    if (String(payload.deepseek_test?.status || '') !== 'failed') {
      issues.push('testDeepseekConnection() should fail when api_key is missing');
    }
    if (deepseekItems.length === 0) {
      issues.push('deepseekLogs() must expose DeepSeek call records');
    }
    if (Number(deepseekSummary.failed_count || 0) < 1) {
      issues.push('deepseekLogs() summary must count failed DeepSeek calls');
    }
    if (Number(deepseekSummary.today_total || 0) < 1) {
      issues.push('deepseekLogs() summary must count today logs');
    }
    if (Number(deepseekSummary.today_chat || 0) < 1) {
      issues.push('deepseekLogs() summary must classify chat-feature logs');
    }

    if (issues.length > 0) {
      console.error('Settings logs runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Settings logs runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
