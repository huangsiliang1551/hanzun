const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const crypto = require('crypto');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const usersPath = path.join(storageDir, 'admin_users.json');
const rolesPath = path.join(storageDir, 'admin_roles.json');
const userRolesPath = path.join(storageDir, 'admin_user_roles.json');
const roleMenusPath = path.join(storageDir, 'admin_role_menus.json');
const roleActionsPath = path.join(storageDir, 'admin_role_action_points.json');
const logsPath = path.join(storageDir, 'operation_logs.json');
const adminManageServicePath = path.join(backendRoot, 'app', 'service', 'system', 'AdminManageService.php');
const repositoryPath = path.join(backendRoot, 'app', 'repository', 'AdminUserRepository.php');

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

    $service = new app\\service\\system\\AdminManageService();
    $repository = new app\\repository\\AdminUserRepository();

    $created = $service->createUser([
      'username' => 'auditor',
      'password' => 'auditpass123',
      'nickname' => 'Auditor',
      'email' => 'auditor@example.com',
      'mobile' => '+86 13800000001',
      'status' => 1,
      'role_ids' => [2],
    ]);

    $createdUser = $repository->findByUsername('auditor');

    $updated = $service->updateUser((int) ($createdUser['id'] ?? 0), [
      'password' => 'auditpass456',
      'nickname' => 'Auditor Updated',
      'email' => 'auditor.updated@example.com',
      'mobile' => '+86 13800000002',
      'status' => 1,
      'role_ids' => [2],
    ]);

    $updatedUser = $repository->findById((int) ($createdUser['id'] ?? 0));
    $legacyUser = $repository->findById(3);

    echo json_encode([
      'created' => $created,
      'created_user' => $createdUser,
      'updated' => $updated,
      'updated_user' => $updatedUser,
      'created_password_valid' => $repository->verifyPassword($createdUser ?? [], 'auditpass123'),
      'updated_password_valid' => $repository->verifyPassword($updatedUser ?? [], 'auditpass456'),
      'updated_old_password_valid' => $repository->verifyPassword($updatedUser ?? [], 'auditpass123'),
      'legacy_password_valid' => $repository->verifyPassword($legacyUser ?? [], 'legacypass123'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const backups = {
    users: backup(usersPath),
    roles: backup(rolesPath),
    userRoles: backup(userRolesPath),
    roleMenus: backup(roleMenusPath),
    roleActions: backup(roleActionsPath),
    logs: backup(logsPath)
  };

  try {
    writeJson(usersPath, [
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
        id: 3,
        username: 'legacy-user',
        password_hash: crypto.createHash('sha256').update('legacypass123').digest('hex'),
        nickname: 'Legacy User',
        email: 'legacy@example.com',
        mobile: '',
        status: 1,
        password_version: 1,
        last_login_at: null,
        last_login_ip: null,
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:00:00'
      }
    ]);
    writeJson(rolesPath, [
      { id: 1, name: '超级管理员', code: 'super-admin', description: 'all', status: 1, created_at: '2026-06-11 09:00:00', updated_at: '2026-06-11 09:00:00' },
      { id: 2, name: '操作员', code: 'operator', description: 'ops', status: 1, created_at: '2026-06-11 09:00:00', updated_at: '2026-06-11 09:00:00' }
    ]);
    writeJson(userRolesPath, [
      { user_id: 1, role_id: 1 },
      { user_id: 3, role_id: 2 }
    ]);
    writeJson(roleMenusPath, [
      { role_id: 1, menu_id: 1 },
      { role_id: 2, menu_id: 1 }
    ]);
    writeJson(roleActionsPath, [
      { role_id: 1, action_point_id: 41 },
      { role_id: 1, action_point_id: 42 },
      { role_id: 1, action_point_id: 43 },
      { role_id: 2, action_point_id: 41 }
    ]);
    writeJson(logsPath, []);

    const serviceContent = fs.readFileSync(adminManageServicePath, 'utf8');
    const repositoryContent = fs.readFileSync(repositoryPath, 'utf8');
    const staticIssues = [];

    if (!/password_hash\s*\(/.test(serviceContent)) {
      staticIssues.push('AdminManageService must use password_hash() for admin passwords');
    }
    if (!/password_verify\s*\(/.test(repositoryContent)) {
      staticIssues.push('AdminUserRepository must verify bcrypt passwords');
    }
    if (!/sha256/.test(repositoryContent)) {
      staticIssues.push('AdminUserRepository must retain legacy sha256 verification for existing users');
    }

    if (staticIssues.length > 0) {
      console.error('Admin password hashing static validation failed:');
      staticIssues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

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

    if (typeof payload.created_user?.password_hash !== 'string' || !payload.created_user.password_hash.startsWith('$2y$')) {
      issues.push('createUser must persist bcrypt password hashes');
    }
    if (Number(payload.created_user?.password_version || 0) !== 1) {
      issues.push('createUser must initialize password_version to 1 in runtime storage mode');
    }
    if (typeof payload.updated_user?.password_hash !== 'string' || !payload.updated_user.password_hash.startsWith('$2y$')) {
      issues.push('updateUser must persist bcrypt password hashes after password change');
    }
    if (Number(payload.updated_user?.password_version || 0) !== 2) {
      issues.push('updateUser password changes must increment password_version in runtime storage mode');
    }
    if (payload.created_password_valid !== true) {
      issues.push('created bcrypt hash must verify with the new password');
    }
    if (payload.updated_password_valid !== true) {
      issues.push('updated bcrypt hash must verify with the new password');
    }
    if (payload.updated_old_password_valid !== false) {
      issues.push('updated bcrypt hash must reject the old password');
    }
    if (payload.legacy_password_valid !== true) {
      issues.push('verifyPassword must remain compatible with legacy sha256 hashes');
    }

    if (issues.length > 0) {
      console.error('Admin password hashing runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Admin password hashing runtime validation passed.');
  } finally {
    restore(usersPath, backups.users);
    restore(rolesPath, backups.roles);
    restore(userRolesPath, backups.userRoles);
    restore(roleMenusPath, backups.roleMenus);
    restore(roleActionsPath, backups.roleActions);
    restore(logsPath, backups.logs);
  }
}

main();
