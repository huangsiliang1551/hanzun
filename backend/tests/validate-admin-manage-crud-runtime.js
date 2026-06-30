const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const crypto = require('crypto');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const files = {
  users: path.join(storageDir, 'admin_users.json'),
  roles: path.join(storageDir, 'admin_roles.json'),
  userRoles: path.join(storageDir, 'admin_user_roles.json'),
  roleMenus: path.join(storageDir, 'admin_role_menus.json'),
  roleActions: path.join(storageDir, 'admin_role_action_points.json'),
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

    $service = new app\\service\\system\\AdminManageService();
    $userRepository = new app\\repository\\AdminUserRepository();
    $roleRepository = new app\\repository\\RoleRepository();

    app\\common\\http\\RequestContext::setUser([
      'id' => 1,
      'username' => 'admin',
      'nickname' => 'Admin'
    ]);

    app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
      'POST',
      '/admin/settings/roles',
      [],
      [],
      ['Authorization' => 'Bearer admin-token'],
      'admin-manage-001'
    ));
    $createdRole = $service->createRole([
      'name' => 'Sales Manager',
      'code' => 'sales-manager',
      'description' => 'Manage sales users',
      'status' => 1,
    ]);

    $roleId = (int) (($createdRole['role']['id'] ?? 0));

    app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
      'PUT',
      '/admin/settings/roles/' . $roleId,
      [],
      [],
      ['Authorization' => 'Bearer admin-token'],
      'admin-manage-002',
      ['id' => (string) $roleId]
    ));
    $updatedRole = $service->updateRole($roleId, [
      'name' => 'Regional Sales Manager',
      'code' => 'regional-sales-manager',
      'description' => 'Manage regional sales users',
      'status' => 1,
    ]);

    app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
      'PUT',
      '/admin/settings/roles/' . $roleId . '/permissions',
      [],
      [],
      ['Authorization' => 'Bearer admin-token'],
      'admin-manage-003',
      ['id' => (string) $roleId]
    ));
    $updatedPermissions = $service->updateRolePermissions($roleId, [
      'menu_ids' => [11],
      'action_point_ids' => [41, 44],
    ]);

    app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
      'POST',
      '/admin/settings/admin-users',
      [],
      [],
      ['Authorization' => 'Bearer admin-token'],
      'admin-manage-004'
    ));
    $createdUser = $service->createUser([
      'username' => 'sales.user',
      'password' => 'salesuser123',
      'nickname' => 'Sales User',
      'email' => 'sales.user@example.com',
      'mobile' => '+86 13800000002',
      'status' => 1,
      'role_ids' => [$roleId],
    ]);

    $createdUserId = (int) (($createdUser['user']['id'] ?? 0));

    $deleteAssignedRoleError = '';
    try {
      app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
        'DELETE',
        '/admin/settings/roles/' . $roleId,
        [],
        [],
        ['Authorization' => 'Bearer admin-token'],
        'admin-manage-005',
        ['id' => (string) $roleId]
      ));
      $service->deleteRole($roleId);
    } catch (Throwable $exception) {
      $deleteAssignedRoleError = $exception->getMessage();
    }

    $deleteSelfError = '';
    try {
      app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
        'DELETE',
        '/admin/settings/admin-users/1',
        [],
        [],
        ['Authorization' => 'Bearer admin-token'],
        'admin-manage-006',
        ['id' => '1']
      ));
      $service->deleteUser(1, current_user());
    } catch (Throwable $exception) {
      $deleteSelfError = $exception->getMessage();
    }

    app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
      'DELETE',
      '/admin/settings/admin-users/' . $createdUserId,
      [],
      [],
      ['Authorization' => 'Bearer admin-token'],
      'admin-manage-007',
      ['id' => (string) $createdUserId]
    ));
    $deletedUser = $service->deleteUser($createdUserId, current_user());

    $deleteBuiltInRoleError = '';
    try {
      app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
        'DELETE',
        '/admin/settings/roles/1',
        [],
        [],
        ['Authorization' => 'Bearer admin-token'],
        'admin-manage-008',
        ['id' => '1']
      ));
      $service->deleteRole(1);
    } catch (Throwable $exception) {
      $deleteBuiltInRoleError = $exception->getMessage();
    }

    app\\common\\http\\RequestContext::setRequest(new app\\common\\http\\Request(
      'DELETE',
      '/admin/settings/roles/' . $roleId,
      [],
      [],
      ['Authorization' => 'Bearer admin-token'],
      'admin-manage-009',
      ['id' => (string) $roleId]
    ));
    $deletedRole = $service->deleteRole($roleId);

    echo json_encode([
      'created_role' => $createdRole,
      'updated_role' => $updatedRole,
      'updated_permissions' => $updatedPermissions,
      'created_user' => $createdUser,
      'deleted_user' => $deletedUser,
      'deleted_role' => $deletedRole,
      'delete_assigned_role_error' => $deleteAssignedRoleError,
      'delete_self_error' => $deleteSelfError,
      'delete_built_in_role_error' => $deleteBuiltInRoleError,
      'remaining_roles' => $roleRepository->listRoles(),
      'remaining_users' => $userRepository->listUsers(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const routeContent = fs.readFileSync(path.join(backendRoot, 'route', 'adminapi.php'), 'utf8');
  const serviceContent = fs.readFileSync(path.join(backendRoot, 'app', 'service', 'system', 'AdminManageService.php'), 'utf8');
  const repositoryContent = fs.readFileSync(path.join(backendRoot, 'app', 'repository', 'RoleRepository.php'), 'utf8');
  const menuRepositoryContent = fs.readFileSync(path.join(backendRoot, 'app', 'repository', 'MenuRepository.php'), 'utf8');
  const staticIssues = [];

  [
    "['DELETE', '/admin/settings/admin-users/{id}'",
    "['POST', '/admin/settings/roles'",
    "['PUT', '/admin/settings/roles/{id}'",
    "['DELETE', '/admin/settings/roles/{id}'"
  ].forEach((fragment) => {
    if (!routeContent.includes(fragment)) {
      staticIssues.push(`missing route fragment: ${fragment}`);
    }
  });

  [
    'createRole',
    'updateRole',
    'deleteRole',
    'deleteUser'
  ].forEach((methodName) => {
    if (!serviceContent.includes(`function ${methodName}`)) {
      staticIssues.push(`AdminManageService missing ${methodName}()`);
    }
  });

  [
    'system.admin_user.delete',
    'system.role.create',
    'system.role.update',
    'system.role.delete'
  ].forEach((actionCode) => {
    if (!menuRepositoryContent.includes(actionCode)) {
      staticIssues.push(`MenuRepository missing action point code ${actionCode}`);
    }
  });

  [
    'createRole',
    'updateRole',
    'deleteRole'
  ].forEach((methodName) => {
    if (!repositoryContent.includes(`function ${methodName}`)) {
      staticIssues.push(`RoleRepository missing ${methodName}()`);
    }
  });

  if (staticIssues.length > 0) {
    console.error('Admin manage CRUD static validation failed:');
    staticIssues.forEach((issue) => console.error(`- ${issue}`));
    process.exit(1);
  }

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
        mobile: '+86 13800000000',
        status: 1,
        password_version: 1,
        last_login_at: null,
        last_login_ip: null,
        created_at: '2026-06-12 09:00:00',
        updated_at: '2026-06-12 09:00:00'
      }
    ]);
    writeJson(files.roles, [
      { id: 1, name: '超级管理员', code: 'super-admin', description: 'all', status: 1, created_at: '2026-06-12 09:00:00', updated_at: '2026-06-12 09:00:00' },
      { id: 2, name: '操作员', code: 'operator', description: 'ops', status: 1, created_at: '2026-06-12 09:00:00', updated_at: '2026-06-12 09:00:00' }
    ]);
    writeJson(files.userRoles, [
      { user_id: 1, role_id: 1 }
    ]);
    writeJson(files.roleMenus, [
      { role_id: 1, menu_id: 1 },
      { role_id: 1, menu_id: 11 },
      { role_id: 2, menu_id: 11 }
    ]);
    writeJson(files.roleActions, [
      { role_id: 1, action_point_id: 1 },
      { role_id: 1, action_point_id: 41 },
      { role_id: 1, action_point_id: 42 },
      { role_id: 1, action_point_id: 43 },
      { role_id: 1, action_point_id: 44 },
      { role_id: 1, action_point_id: 45 },
      { role_id: 2, action_point_id: 41 }
    ]);
    writeJson(files.sessions, {
      'sales-session-a': {
        session_code: 'sales-session-a',
        user_id: 2,
        username: 'sales.user',
        nickname: 'Sales User',
        status: 'active',
        refresh_token_hash: 'hash-a',
        access_expires_at: 9999999999,
        refresh_expires_at: 9999999999,
        updated_at: '2026-06-12T09:00:00+08:00'
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
    const logs = JSON.parse(fs.readFileSync(files.operationLogs, 'utf8'));
    const issues = [];

    if (String(payload.created_role?.role?.code || '') !== 'sales-manager') {
      issues.push('createRole must create a custom role');
    }
    if (String(payload.updated_role?.role?.code || '') !== 'regional-sales-manager') {
      issues.push('updateRole must update role metadata');
    }
    const permissionActionCodes = Array.isArray(payload.updated_permissions?.action_points)
      ? payload.updated_permissions.action_points.map((item) => String(item.code || '')).sort()
      : [];
    if (permissionActionCodes.join(',') !== 'system.admin_user.view,system.role.view') {
      issues.push('updateRolePermissions must persist selected action points for custom role');
    }
    if (String(payload.delete_assigned_role_error || '') === '') {
      issues.push('deleteRole must reject roles that are still assigned to users');
    }
    if (String(payload.delete_self_error || '') === '') {
      issues.push('deleteUser must reject deleting the current operator');
    }
    if (Number(payload.deleted_user?.user?.id || 0) <= 0) {
      issues.push('deleteUser must return deleted user detail');
    }
    if ((payload.remaining_users || []).some((item) => Number(item.id) === Number(payload.deleted_user?.user?.id || 0))) {
      issues.push('deleteUser must remove the target user from storage');
    }
    if (String(payload.delete_built_in_role_error || '') === '') {
      issues.push('deleteRole must reject deleting built-in roles');
    }
    if (Number(payload.deleted_role?.role?.id || 0) <= 0) {
      issues.push('deleteRole must return deleted role detail');
    }
    if ((payload.remaining_roles || []).some((item) => Number(item.id) === Number(payload.deleted_role?.role?.id || 0))) {
      issues.push('deleteRole must remove custom role from storage');
    }

    const actionPoints = Array.isArray(logs) ? logs.map((item) => String(item.action_point || '')) : [];
    [
      'system.role.create',
      'system.role.update',
      'system.role.permissions.update',
      'system.admin_user.delete',
      'system.role.delete'
    ].forEach((actionPoint) => {
      if (!actionPoints.includes(actionPoint)) {
        issues.push(`operation log missing ${actionPoint}`);
      }
    });

    if (issues.length > 0) {
      console.error('Admin manage CRUD runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Admin manage CRUD runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
