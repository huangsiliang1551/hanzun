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
  operationLogs: path.join(storageDir, 'operation_logs.json'),
  loginLogs: path.join(storageDir, 'login_logs.json')
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

    $routes = require $basePath . '/route/adminapi.php';
    $router = new app\\common\\http\\Router($routes);
    $auth = new app\\service\\auth\\AuthService();
    $service = new app\\service\\system\\AdminManageService();

    $login = $auth->login('operator', 'operator123');
    $token = 'Bearer ' . (string) ($login['access_token'] ?? '');

    $forbiddenMessage = '';
    try {
        $request = new app\\common\\http\\Request('GET', '/admin/settings/admin-users', [], [], ['Authorization' => $token], 'rbac-001');
        $router->dispatch($request);
    } catch (app\\common\\exception\\BusinessException $exception) {
        $forbiddenMessage = $exception->getMessage();
    }

    app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
      'PUT',
      '/admin/settings/roles/2/permissions',
      [],
      [],
      ['Authorization' => $token],
      'rbac-002',
      ['id' => '2']
    ));
    app\\common\\http\\RequestContext::setUser([
      'id' => 1,
      'username' => 'admin',
      'nickname' => 'Admin'
    ]);

    $updatedRole = $service->updateRolePermissions(2, [
      'menu_ids' => [11],
      'action_point_ids' => [41, 44],
    ]);

    $detail = $service->userDetail(2);

    $request = new app\\common\\http\\Request('GET', '/admin/settings/admin-users', [], [], ['Authorization' => $token], 'rbac-003');
    $allowedResponse = $router->dispatch($request);

    echo json_encode([
      'login' => $login,
      'forbidden_message' => $forbiddenMessage,
      'updated_role' => $updatedRole,
      'detail' => $detail,
      'allowed_response' => $allowedResponse,
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
      }
    ]);
    writeJson(files.userRoles, [
      { user_id: 1, role_id: 1 },
      { user_id: 2, role_id: 2 }
    ]);
    writeJson(files.roles, [
      { id: 1, name: '超级管理员', code: 'super-admin', description: 'all', status: 1, created_at: '2026-06-11 09:00:00', updated_at: '2026-06-11 09:00:00' },
      { id: 2, name: '操作员', code: 'operator', description: 'ops', status: 1, created_at: '2026-06-11 09:00:00', updated_at: '2026-06-11 09:00:00' }
    ]);
    writeJson(files.roleMenus, [
      { role_id: 1, menu_id: 1 },
      { role_id: 1, menu_id: 11 },
      { role_id: 2, menu_id: 11 }
    ]);
    writeJson(files.roleActions, [
      { role_id: 1, action_point_id: 41 },
      { role_id: 1, action_point_id: 42 },
      { role_id: 1, action_point_id: 43 },
      { role_id: 1, action_point_id: 44 },
      { role_id: 1, action_point_id: 45 },
      { role_id: 1, action_point_id: 46 },
      { role_id: 2, action_point_id: 44 }
    ]);
    writeJson(files.sessions, {});
    writeJson(files.operationLogs, []);
    writeJson(files.loginLogs, []);

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

    if (!payload.login || typeof payload.login.access_token !== 'string' || payload.login.access_token.length === 0) {
      issues.push('AuthService::login must issue access token for operator');
    }
    if (String(payload.forbidden_message || '') === '') {
      issues.push('operator without system.admin_user.view should be forbidden from admin user list route');
    }
    if (!Array.isArray(payload.updated_role?.menus) || payload.updated_role.menus.length !== 1 || Number(payload.updated_role.menus[0]?.id || 0) !== 11) {
      issues.push('updateRolePermissions must persist role menu bindings');
    }
    const actionCodes = Array.isArray(payload.updated_role?.action_points) ? payload.updated_role.action_points.map((item) => String(item.code || '')) : [];
    if (actionCodes.sort().join(',') !== 'system.admin_user.view,system.role.view') {
      issues.push('updateRolePermissions must persist selected action point bindings');
    }
    const detailPermissions = Array.isArray(payload.detail?.permissions) ? payload.detail.permissions.slice().sort() : [];
    if (detailPermissions.join(',') !== 'system.admin_user.view,system.role.view') {
      issues.push('userDetail must reflect updated permissions inherited from roles');
    }
    if (!Array.isArray(payload.detail?.menus) || Number(payload.detail.menus?.[0]?.id || 0) !== 11) {
      issues.push('userDetail must reflect updated menu access inherited from roles');
    }
    if (!Array.isArray(payload.allowed_response?.data?.items) || payload.allowed_response.data.items.length === 0) {
      issues.push('router dispatch must allow route after role permission is granted');
    }

    if (issues.length > 0) {
      console.error('Admin RBAC runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Admin RBAC runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
