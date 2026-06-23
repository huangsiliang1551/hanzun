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

    app\\common\\http\\RequestContext::setUser([
      'id' => 1,
      'username' => 'admin',
      'nickname' => 'Admin'
    ]);
    app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
      'PUT',
      '/admin/settings/admin-users/2',
      [],
      [],
      ['Authorization' => 'Bearer admin-token'],
      'admin-reset-001',
      ['id' => '2']
    ));

    $service = new app\\service\\system\\AdminManageService();
    $repository = new app\\repository\\AdminUserRepository();
    $updated = $service->updateUser(2, [
      'password' => 'resetpass123',
      'nickname' => 'Operator Updated',
      'email' => 'operator.updated@example.com',
      'mobile' => '+86 13800009999',
      'status' => 1,
      'role_ids' => [2],
    ]);

    $stored = $repository->findById(2);

    echo json_encode([
      'updated' => $updated,
      'stored' => $stored,
      'new_password_valid' => $repository->verifyPassword($stored ?? [], 'resetpass123'),
      'old_password_valid' => $repository->verifyPassword($stored ?? [], 'operator123'),
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
      },
      {
        id: 2,
        username: 'operator',
        password_hash: crypto.createHash('sha256').update('operator123').digest('hex'),
        nickname: 'Operator',
        email: 'operator@example.com',
        mobile: '',
        status: 1,
        password_version: 1,
        last_login_at: null,
        last_login_ip: null,
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:00:00'
      },
      {
        id: 3,
        username: 'other-user',
        password_hash: crypto.createHash('sha256').update('other123456').digest('hex'),
        nickname: 'Other User',
        email: 'other@example.com',
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
      { user_id: 1, role_id: 1 },
      { user_id: 2, role_id: 2 },
      { user_id: 3, role_id: 2 }
    ]);
    writeJson(files.roles, [
      { id: 1, name: '超级管理员', code: 'super-admin', description: 'all', status: 1, created_at: '2026-06-11 09:00:00', updated_at: '2026-06-11 09:00:00' },
      { id: 2, name: '操作员', code: 'operator', description: 'ops', status: 1, created_at: '2026-06-11 09:00:00', updated_at: '2026-06-11 09:00:00' }
    ]);
    writeJson(files.sessions, {
      'operator-current': {
        session_code: 'operator-current',
        user_id: 2,
        username: 'operator',
        nickname: 'Operator',
        status: 'active',
        refresh_token_hash: 'hash-a',
        access_expires_at: 9999999999,
        refresh_expires_at: 9999999999,
        updated_at: '2026-06-11T09:00:00+08:00'
      },
      'operator-old': {
        session_code: 'operator-old',
        user_id: 2,
        username: 'operator',
        nickname: 'Operator',
        status: 'active',
        refresh_token_hash: 'hash-b',
        access_expires_at: 9999999999,
        refresh_expires_at: 9999999999,
        updated_at: '2026-06-11T09:00:00+08:00'
      },
      'other-user-current': {
        session_code: 'other-user-current',
        user_id: 3,
        username: 'other-user',
        nickname: 'Other User',
        status: 'active',
        refresh_token_hash: 'hash-c',
        access_expires_at: 9999999999,
        refresh_expires_at: 9999999999,
        updated_at: '2026-06-11T09:00:00+08:00'
      }
    });
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
    const sessions = JSON.parse(fs.readFileSync(files.sessions, 'utf8'));
    const logs = JSON.parse(fs.readFileSync(files.operationLogs, 'utf8'));
    const issues = [];

    if (typeof payload.stored?.password_hash !== 'string' || !payload.stored.password_hash.startsWith('$2y$')) {
      issues.push('admin user password reset must persist bcrypt password hash');
    }
    if (payload.new_password_valid !== true) {
      issues.push('admin user password reset must set the new password correctly');
    }
    if (payload.old_password_valid !== false) {
      issues.push('admin user password reset must invalidate the old password');
    }
    if (String(sessions['operator-current']?.status || '') !== 'revoked') {
      issues.push('admin user password reset must revoke current sessions of the target user');
    }
    if (String(sessions['operator-old']?.status || '') !== 'revoked') {
      issues.push('admin user password reset must revoke previous sessions of the target user');
    }
    if (String(sessions['other-user-current']?.status || '') !== 'active') {
      issues.push('admin user password reset must not revoke other users sessions');
    }
    const actionPoints = Array.isArray(logs) ? logs.map((item) => String(item.action_point || '')) : [];
    if (!actionPoints.includes('system.admin_user.update')) {
      issues.push('admin user password reset must still write system.admin_user.update operation log');
    }

    if (issues.length > 0) {
      console.error('Admin user password reset revoke runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Admin user password reset revoke runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
