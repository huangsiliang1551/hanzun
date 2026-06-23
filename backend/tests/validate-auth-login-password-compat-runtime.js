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
  roleMenus: path.join(storageDir, 'admin_role_menus.json'),
  roleActions: path.join(storageDir, 'admin_role_action_points.json'),
  sessions: path.join(storageDir, 'admin_sessions.json'),
  loginLogs: path.join(storageDir, 'login_logs.json'),
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

    $auth = new app\\service\\auth\\AuthService();

    $legacyLogin = $auth->login('legacy-user', 'legacy123456');
    $bcryptLogin = $auth->login('bcrypt-user', 'bcrypt123456');

    echo json_encode([
      'legacy_login' => $legacyLogin,
      'bcrypt_login' => $bcryptLogin,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const backups = {};
  Object.entries(files).forEach(([key, filePath]) => {
    backups[key] = backup(filePath);
  });

  try {
    const bcryptHash = execFileSync('php', ['-r', 'echo password_hash("bcrypt123456", PASSWORD_BCRYPT);'], {
      cwd: backendRoot,
      encoding: 'utf8'
    }).trim();

    writeJson(files.users, [
      {
        id: 1,
        username: 'legacy-user',
        password_hash: crypto.createHash('sha256').update('legacy123456').digest('hex'),
        nickname: 'Legacy User',
        email: 'legacy@example.com',
        mobile: '',
        status: 1,
        password_version: 1,
        last_login_at: null,
        last_login_ip: null,
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:00:00'
      },
      {
        id: 2,
        username: 'bcrypt-user',
        password_hash: bcryptHash,
        nickname: 'Bcrypt User',
        email: 'bcrypt@example.com',
        mobile: '',
        status: 1,
        password_version: 1,
        last_login_at: null,
        last_login_ip: null,
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:00:00'
      }
    ]);
    writeJson(files.userRoles, [
      { user_id: 1, role_id: 2 },
      { user_id: 2, role_id: 2 }
    ]);
    writeJson(files.roles, [
      { id: 2, name: '操作员', code: 'operator', description: 'ops', status: 1, created_at: '2026-06-11 09:00:00', updated_at: '2026-06-11 09:00:00' }
    ]);
    writeJson(files.roleMenus, [
      { role_id: 2, menu_id: 1 }
    ]);
    writeJson(files.roleActions, [
      { role_id: 2, action_point_id: 1 }
    ]);
    writeJson(files.sessions, {});
    writeJson(files.loginLogs, []);
    writeJson(files.operationLogs, []);

    const output = execFileSync('php', ['-r', buildPhpPayload()], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '0'
      }
    });

    const payload = JSON.parse(output);
    const issues = [];

    if (typeof payload.legacy_login?.access_token !== 'string' || payload.legacy_login.access_token.length === 0) {
      issues.push('AuthService::login must support legacy sha256 password hashes');
    }
    if (typeof payload.bcrypt_login?.access_token !== 'string' || payload.bcrypt_login.access_token.length === 0) {
      issues.push('AuthService::login must support bcrypt password hashes');
    }

    if (issues.length > 0) {
      console.error('Auth login password compatibility runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Auth login password compatibility runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
