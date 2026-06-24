const fs = require('fs');
const os = require('os');
const path = require('path');
const { execFileSync, spawn } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const barrierPath = path.join(os.tmpdir(), `refresh-barrier-${process.pid}-${Date.now()}.txt`);

const files = {
  users: path.join(storageDir, 'admin_users.json'),
  userRoles: path.join(storageDir, 'admin_user_roles.json'),
  roles: path.join(storageDir, 'admin_roles.json'),
  sessions: path.join(storageDir, 'admin_sessions.json'),
  roleMenus: path.join(storageDir, 'admin_role_menus.json'),
  roleActions: path.join(storageDir, 'admin_role_action_points.json')
};

function backup(filePath) {
  return fs.existsSync(filePath) ? fs.readFileSync(filePath) : null;
}

function restore(filePath, content) {
  if (content === null) {
    if (fs.existsSync(filePath)) {
      fs.unlinkSync(filePath);
    }
    return;
  }

  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, content);
}

function writeJson(filePath, data) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf8');
}

function seedRuntimeAuth() {
  writeJson(files.users, [
    {
      id: 1,
      username: 'runtime-admin',
      password_hash: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
      nickname: 'Runtime Admin',
      email: 'runtime@example.com',
      mobile: '',
      status: 1,
      password_version: 1,
      last_login_at: null,
      last_login_ip: null,
      created_at: '2026-06-24 00:00:00',
      updated_at: '2026-06-24 00:00:00'
    }
  ]);
  writeJson(files.roles, [
    {
      id: 1,
      name: 'Super Admin',
      code: 'super-admin',
      description: 'super admin',
      status: 1,
      created_at: '2026-06-24 00:00:00',
      updated_at: '2026-06-24 00:00:00'
    }
  ]);
  writeJson(files.userRoles, [{ user_id: 1, role_id: 1 }]);
  writeJson(files.roleMenus, []);
  writeJson(files.roleActions, []);
  writeJson(files.sessions, {});
}

function buildIssueTokenScript() {
  return `
    require_once getcwd() . '/app/common/bootstrap/Autoloader.php';
    require_once getcwd() . '/app/common/bootstrap/EnvLoader.php';
    require_once getcwd() . '/app/common/bootstrap/helpers.php';
    app\\common\\bootstrap\\Autoloader::register(getcwd());
    app\\common\\bootstrap\\EnvLoader::load(getcwd() . '/.env');
    app\\common\\config\\ConfigRepository::instance()->load(getcwd() . '/config');
    app\\common\\database\\DatabaseManager::instance()->configure(app\\common\\config\\ConfigRepository::instance()->get('database.connections.mysql', []));

    $service = new app\\service\\auth\\SessionService();
    $tokens = $service->issueTokens([
      'id' => 1,
      'username' => 'runtime-admin',
      'nickname' => 'Runtime Admin',
    ]);
    echo json_encode($tokens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function buildRefreshScript(barrier) {
  const barrierLiteral = JSON.stringify(barrier.replace(/\\/g, '\\\\'));

  return `
    require_once getcwd() . '/app/common/bootstrap/Autoloader.php';
    require_once getcwd() . '/app/common/bootstrap/EnvLoader.php';
    require_once getcwd() . '/app/common/bootstrap/helpers.php';
    app\\common\\bootstrap\\Autoloader::register(getcwd());
    app\\common\\bootstrap\\EnvLoader::load(getcwd() . '/.env');
    app\\common\\config\\ConfigRepository::instance()->load(getcwd() . '/config');
    app\\common\\database\\DatabaseManager::instance()->configure(app\\common\\config\\ConfigRepository::instance()->get('database.connections.mysql', []));

    $barrier = ${barrierLiteral};
    $deadline = microtime(true) + 10;
    while (!is_file($barrier) && microtime(true) < $deadline) {
      usleep(10000);
    }

    $service = new app\\service\\auth\\SessionService();
    $result = $service->refresh($argv[1]);
    echo $result === null ? '0' : '1';
  `;
}

async function main() {
  const backups = {};
  Object.entries(files).forEach(([key, filePath]) => {
    backups[key] = backup(filePath);
  });

  try {
    seedRuntimeAuth();
    fs.rmSync(barrierPath, { force: true });

    const env = {
      ...process.env,
      APP_ALLOW_RUNTIME_FALLBACK: '1',
      PREFER_RUNTIME_STORAGE: '1',
      AUTH_JWT_SECRET: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
      DB_HOST: '',
      DB_DATABASE: ''
    };

    const issued = JSON.parse(execFileSync('php', ['-r', buildIssueTokenScript()], {
      cwd: backendRoot,
      encoding: 'utf8',
      env
    }));

    const refreshToken = String(issued.refresh_token || '');
    if (refreshToken === '') {
      throw new Error('Failed to seed refresh token.');
    }

    const refreshCode = buildRefreshScript(barrierPath);
    const children = [];
    for (let index = 0; index < 10; index += 1) {
      children.push(spawn('php', ['-r', refreshCode, refreshToken], {
        cwd: backendRoot,
        env,
        stdio: ['ignore', 'pipe', 'pipe']
      }));
    }

    await new Promise((resolve) => setTimeout(resolve, 200));
    fs.writeFileSync(barrierPath, 'go', 'utf8');

    const results = await Promise.all(children.map((child) => new Promise((resolve) => {
      let stdout = '';
      let stderr = '';
      child.stdout.on('data', (chunk) => {
        stdout += chunk.toString('utf8');
      });
      child.stderr.on('data', (chunk) => {
        stderr += chunk.toString('utf8');
      });
      child.on('exit', (code) => {
        resolve({ code, stdout: stdout.trim(), stderr: stderr.trim() });
      });
    })));

    const successCount = results.filter((item) => item.code === 0 && item.stdout === '1').length;
    if (successCount !== 1) {
      throw new Error(`Expected exactly 1 successful refresh out of 10 concurrent attempts, got ${successCount}. Raw=${JSON.stringify(results)}`);
    }

    console.log('Session refresh concurrency runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
    fs.rmSync(barrierPath, { force: true });
  }
}

main().catch((error) => {
  console.error('Session refresh concurrency runtime validation failed:');
  console.error(`- ${error.message}`);
  process.exit(1);
});
