const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const usersPath = path.join(storageDir, 'admin_users.json');
const userRolesPath = path.join(storageDir, 'admin_user_roles.json');
const sessionsPath = path.join(storageDir, 'admin_sessions.json');
const operationLogsPath = path.join(storageDir, 'operation_logs.json');
const repositoryPath = path.join(backendRoot, 'app', 'repository', 'AdminUserRepository.php');
const servicePath = path.join(backendRoot, 'app', 'service', 'system', 'SettingService.php');
const sessionServicePath = path.join(backendRoot, 'app', 'service', 'auth', 'SessionService.php');
const controllerPath = path.join(backendRoot, 'app', 'adminapi', 'controller', 'system', 'SettingController.php');

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

    $request = new app\\common\\http\\Request('PUT', '/admin/settings/account', [], [], ['Authorization' => 'Bearer token-current'], 'req-001');
    app\\common\\http\\RequestContext::setRequest($request);
    app\\common\\http\\RequestContext::setUser([
      'id' => 1,
      'username' => 'admin',
      'nickname' => 'Admin',
      'session_code' => 'current-session'
    ]);

    $service = new app\\service\\system\\SettingService();
    $result = $service->updateAccountProfile([
      'nickname' => 'Admin Updated',
      'email' => 'admin@example.com',
      'mobile' => '13800000000',
      'password' => 'newpassword123'
    ]);

    echo json_encode([
      'result' => $result,
      'stored_user' => (new app\\repository\\AdminUserRepository())->findById(1),
      'new_password_valid' => (new app\\repository\\AdminUserRepository())->verifyPassword((new app\\repository\\AdminUserRepository())->findById(1) ?? [], 'newpassword123'),
      'old_password_valid' => (new app\\repository\\AdminUserRepository())->verifyPassword((new app\\repository\\AdminUserRepository())->findById(1) ?? [], 'oldpassword'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const backups = {
    users: backup(usersPath),
    roles: backup(userRolesPath),
    sessions: backup(sessionsPath),
    logs: backup(operationLogsPath)
  };

  try {
    fs.mkdirSync(storageDir, { recursive: true });
    writeJson(usersPath, [
      {
        id: 1,
        username: 'admin',
        password_hash: require('crypto').createHash('sha256').update('oldpassword').digest('hex'),
        nickname: 'Admin',
        email: 'admin@example.com',
        mobile: '13800000000',
        status: 1,
        password_version: 1,
        last_login_at: null,
        last_login_ip: null,
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:00:00'
      },
      {
        id: 2,
        username: 'operator',
        password_hash: require('crypto').createHash('sha256').update('operator123').digest('hex'),
        nickname: 'Operator',
        email: 'operator@example.com',
        mobile: '',
        status: 1,
        password_version: 1,
        last_login_at: null,
        last_login_ip: null,
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:00:00'
      }
    ]);
    writeJson(userRolesPath, [
      { user_id: 1, role_id: 1 },
      { user_id: 2, role_id: 2 }
    ]);
    writeJson(sessionsPath, {
      'current-session': {
        session_code: 'current-session',
        user_id: 1,
        username: 'admin',
        nickname: 'Admin',
        status: 'active',
        refresh_token_hash: 'hash-a',
        access_expires_at: 9999999999,
        refresh_expires_at: 9999999999,
        updated_at: '2026-06-11T09:00:00+08:00'
      },
      'old-session': {
        session_code: 'old-session',
        user_id: 1,
        username: 'admin',
        nickname: 'Admin',
        status: 'active',
        refresh_token_hash: 'hash-b',
        access_expires_at: 9999999999,
        refresh_expires_at: 9999999999,
        updated_at: '2026-06-11T09:00:00+08:00'
      },
      'other-user-session': {
        session_code: 'other-user-session',
        user_id: 2,
        username: 'operator',
        nickname: 'Operator',
        status: 'active',
        refresh_token_hash: 'hash-c',
        access_expires_at: 9999999999,
        refresh_expires_at: 9999999999,
        updated_at: '2026-06-11T09:00:00+08:00'
      }
    });
    writeJson(operationLogsPath, []);

    const serviceContent = fs.readFileSync(servicePath, 'utf8');
    const sessionServiceContent = fs.readFileSync(sessionServicePath, 'utf8');
    const controllerContent = fs.readFileSync(controllerPath, 'utf8');
    const repositoryContent = fs.readFileSync(repositoryPath, 'utf8');
    const staticIssues = [];

    if (!/function\s+updateAccountProfile\s*\(/.test(serviceContent)) {
      staticIssues.push('SettingService must expose updateAccountProfile()');
    }
    if (!/function\s+revokeAllForUser\s*\(/.test(sessionServiceContent)) {
      staticIssues.push('SessionService must expose revokeAllForUser()');
    }
    if (!/function\s+updateAccount\s*\(/.test(controllerContent)) {
      staticIssues.push('SettingController must expose updateAccount()');
    }
    if (!/password_hash\s*\(/.test(serviceContent)) {
      staticIssues.push('SettingService password updates must use password_hash()');
    }
    if (!/password_verify\s*\(/.test(repositoryContent)) {
      staticIssues.push('AdminUserRepository must support password_verify()');
    }

    if (staticIssues.length > 0) {
      console.error('Account password revoke static validation failed:');
      staticIssues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    const output = execFileSync('php', ['-r', buildPhpPayload()], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '1'
      }
    });

    const payload = JSON.parse(output);
    const result = payload.result || {};
    const storedUser = payload.stored_user || {};
    const sessions = JSON.parse(fs.readFileSync(sessionsPath, 'utf8'));
    const logs = JSON.parse(fs.readFileSync(operationLogsPath, 'utf8'));
    const issues = [];

    if (Number(result.require_relogin || 0) !== 1) {
      issues.push('updateAccountProfile must require relogin after password change');
    }
    if (String(result.nickname || '') !== 'Admin Updated') {
      issues.push('updateAccountProfile must still return updated profile data');
    }
    if (typeof storedUser.password_hash !== 'string' || !storedUser.password_hash.startsWith('$2y$')) {
      issues.push('password change must persist bcrypt password hash');
    }
    if (payload.new_password_valid !== true) {
      issues.push('new bcrypt password hash must be verifiable');
    }
    if (payload.old_password_valid !== false) {
      issues.push('old password must no longer match after password change');
    }
    if (String(sessions['current-session']?.status || '') !== 'revoked') {
      issues.push('password change must revoke current session');
    }
    if (String(sessions['old-session']?.status || '') !== 'revoked') {
      issues.push('password change must revoke previous sessions for the same user');
    }
    if (String(sessions['other-user-session']?.status || '') !== 'active') {
      issues.push('password change must not revoke sessions of other users');
    }

    const actionPoints = Array.isArray(logs) ? logs.map((item) => String(item.action_point || '')) : [];
    if (!actionPoints.includes('system.account.update')) {
      issues.push('account update must still write operation log');
    }

    if (issues.length > 0) {
      console.error('Account password revoke runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Account password revoke runtime validation passed.');
  } finally {
    restore(usersPath, backups.users);
    restore(userRolesPath, backups.roles);
    restore(sessionsPath, backups.sessions);
    restore(operationLogsPath, backups.logs);
  }
}

main();
