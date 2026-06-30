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
  loginLogs: path.join(storageDir, 'login_logs.json'),
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

    $_SERVER['REMOTE_ADDR'] = '203.0.113.15';
    $_SERVER['HTTP_USER_AGENT'] = 'Codex Login Metadata Runtime Test';

    $authService = new app\\service\\auth\\AuthService();
    $settingService = new app\\service\\system\\SettingService();

    $loginPayload = $authService->login('admin', 'admin123456');
    app\\common\\http\\RequestContext::setUser([
        'id' => 1,
        'username' => 'admin',
        'nickname' => 'Admin',
    ]);

    echo json_encode([
        'login' => $loginPayload,
        'profile' => $settingService->accountProfile(),
        'bootstrap' => $settingService->accountBootstrap(),
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
        updated_at: '2026-06-11 09:00:00',
      },
    ]);
    writeJson(files.userRoles, [{ user_id: 1, role_id: 1 }]);
    writeJson(files.roles, [{ id: 1, name: 'Super Admin', code: 'super-admin', description: 'all', status: 1 }]);
    writeJson(files.sessions, {});
    writeJson(files.loginLogs, []);
    writeJson(files.operationLogs, []);

    const output = execFileSync('php', ['-r', buildPhpPayload()], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '1',
      },
    });

    const payload = JSON.parse(output);
    const profile = payload.profile || {};
    const bootstrap = payload.bootstrap || {};
    const loginLogs = Array.isArray(bootstrap.login_logs?.items) ? bootstrap.login_logs.items : [];
    const issues = [];

    if (!profile.last_login_at) {
      issues.push('accountProfile() must expose last_login_at after successful login');
    }
    if (String(profile.last_login_ip || '') !== '203.0.113.15') {
      issues.push('accountProfile() must expose the successful login IP');
    }
    if (loginLogs.length === 0) {
      issues.push('accountBootstrap() must include recent login logs for the current user');
    } else {
      const latestLog = loginLogs[0] || {};
      if (Number(latestLog.is_success || 0) !== 1) {
        issues.push('accountBootstrap() latest login log must record the successful login');
      }
      if (String(latestLog.login_ip || '') !== '203.0.113.15') {
        issues.push('accountBootstrap() login logs must preserve login_ip');
      }
    }

    if (issues.length > 0) {
      console.error('Auth login metadata runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Auth login metadata runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
