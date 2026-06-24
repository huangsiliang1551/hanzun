const fs = require('fs');
const os = require('os');
const path = require('path');
const http = require('http');
const { spawn } = require('child_process');
const crypto = require('crypto');

const projectRoot = path.resolve(__dirname, '..', '..');
const backendRoot = path.join(projectRoot, 'backend');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const rateLimitDir = path.join(storageDir, 'rate_limits');

const files = {
  users: path.join(storageDir, 'admin_users.json'),
  userRoles: path.join(storageDir, 'admin_user_roles.json'),
  roles: path.join(storageDir, 'admin_roles.json'),
  roleMenus: path.join(storageDir, 'admin_role_menus.json'),
  roleActions: path.join(storageDir, 'admin_role_action_points.json'),
  sessions: path.join(storageDir, 'admin_sessions.json'),
  loginLogs: path.join(storageDir, 'login_logs.json'),
  operationLogs: path.join(storageDir, 'operation_logs.json'),
  media: path.join(storageDir, 'media_assets.json')
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

function resetRateLimitDir() {
  fs.rmSync(rateLimitDir, { recursive: true, force: true });
}

function seedRuntimeAuth() {
  writeJson(files.users, [
    {
      id: 1,
      username: 'super-admin',
      password_hash: crypto.createHash('sha256').update('super123456').digest('hex'),
      nickname: 'Super Admin',
      email: 'super@example.com',
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
  writeJson(files.loginLogs, []);
  writeJson(files.operationLogs, []);
  writeJson(files.media, []);
}

function request(port, method, targetPath, options = {}) {
  const headers = options.headers || {};
  const body = options.body || null;

  return new Promise((resolve, reject) => {
    const req = http.request({
      host: '127.0.0.1',
      port,
      path: targetPath,
      method,
      headers
    }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const raw = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try {
          json = raw === '' ? null : JSON.parse(raw);
        } catch (error) {
          json = null;
        }

        resolve({
          status: res.statusCode || 0,
          headers: res.headers,
          text: raw,
          json
        });
      });
    });

    req.on('error', reject);

    if (body !== null) {
      req.write(body);
    }

    req.end();
  });
}

async function waitForServer(port, timeoutMs = 15000) {
  const startedAt = Date.now();
  while (Date.now() - startedAt < timeoutMs) {
    try {
      const response = await request(port, 'GET', '/health');
      if (response.status > 0) {
        return;
      }
    } catch (error) {
      // retry
    }

    await new Promise((resolve) => setTimeout(resolve, 200));
  }

  throw new Error('Timed out waiting for test server.');
}

async function withServer(env, callback) {
  const port = 18080 + Math.floor(Math.random() * 1000);
  const child = spawn('php', ['-S', `127.0.0.1:${port}`, 'router.php'], {
    cwd: projectRoot,
    env: {
      ...process.env,
      AUTH_JWT_SECRET: '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
      APP_ALLOW_RUNTIME_FALLBACK: '1',
      PREFER_RUNTIME_STORAGE: '1',
      DB_HOST: 'invalid-runtime-db-host',
      DB_DATABASE: 'invalid_runtime_db',
      ...env
    },
    stdio: ['ignore', 'pipe', 'pipe']
  });

  let stderr = '';
  child.stderr.on('data', (chunk) => {
    stderr += chunk.toString('utf8');
  });

  try {
    await waitForServer(port);
    return await callback(port, () => stderr);
  } finally {
    child.kill();
    await new Promise((resolve) => child.once('exit', resolve));
  }
}

async function login(port) {
  const response = await request(port, 'POST', '/admin/auth/login', {
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      username: 'super-admin',
      password: 'super123456'
    })
  });

  if (response.status !== 200 || typeof response.json?.data?.access_token !== 'string') {
    throw new Error(`Failed to obtain access token for test user. status=${response.status} body=${response.text}`);
  }

  return response.json.data.access_token;
}

async function main() {
  const backups = {};
  Object.entries(files).forEach(([key, filePath]) => {
    backups[key] = backup(filePath);
  });

  try {
    seedRuntimeAuth();
    resetRateLimitDir();

    await withServer({}, async (port) => {
      const malformed = await request(port, 'POST', '/admin/auth/login', {
        headers: {
          'Content-Type': 'application/json'
        },
        body: '{"username":"bad"'
      });

      if (malformed.status !== 422 || malformed.json?.message !== 'Malformed JSON request body.') {
        throw new Error(`Malformed JSON must return 422. got status=${malformed.status} body=${malformed.text}`);
      }

      const token = await login(port);

      const svgPayload = Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'utf8');
      const boundary = '----CodexSvgBoundary' + Date.now();
      const multipartBody = Buffer.concat([
        Buffer.from(`--${boundary}\r\n`, 'utf8'),
        Buffer.from('Content-Disposition: form-data; name="file"; filename="attack.svg"\r\n', 'utf8'),
        Buffer.from('Content-Type: image/svg+xml\r\n\r\n', 'utf8'),
        svgPayload,
        Buffer.from(`\r\n--${boundary}--\r\n`, 'utf8')
      ]);

      const svgUpload = await request(port, 'POST', '/admin/media/assets/upload', {
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': `multipart/form-data; boundary=${boundary}`,
          'Content-Length': String(multipartBody.length)
        },
        body: multipartBody
      });

      if (svgUpload.status !== 422) {
        throw new Error(`SVG upload must return 422. got status=${svgUpload.status} body=${svgUpload.text}`);
      }

      const sourcePathTargets = [
        projectRoot,
        path.join(backendRoot, '.env'),
        'https://example.com/demo.jpg',
        '..\\backend\\.env'
      ];

      for (const sourcePath of sourcePathTargets) {
        const response = await request(port, 'POST', '/admin/media/assets', {
          headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            source_path: sourcePath,
            folder_name: 'misc',
            status: 1
          })
        });

        if (response.status !== 422) {
          throw new Error(`source_path=${sourcePath} must be rejected with 422. got status=${response.status} body=${response.text}`);
        }
      }
    });

    resetRateLimitDir();
    await withServer({ TRUSTED_PROXIES: '' }, async (port) => {
      const statuses = [];
      for (let index = 0; index < 6; index += 1) {
        const response = await request(port, 'POST', '/admin/auth/login', {
          headers: {
            'Content-Type': 'application/json',
            'X-Forwarded-For': `198.51.100.${index + 1}`
          },
          body: JSON.stringify({
            username: 'super-admin',
            password: 'wrong-password'
          })
        });
        statuses.push(response.status);
      }

      if (statuses[5] !== 429) {
        throw new Error(`Untrusted X-Forwarded-For must not bypass rate limit. statuses=${statuses.join(',')}`);
      }
    });

    resetRateLimitDir();
    await withServer({ TRUSTED_PROXIES: '127.0.0.1' }, async (port) => {
      const statuses = [];
      for (let index = 0; index < 6; index += 1) {
        const response = await request(port, 'POST', '/admin/auth/login', {
          headers: {
            'Content-Type': 'application/json',
            'X-Forwarded-For': `203.0.113.${index + 1}`
          },
          body: JSON.stringify({
            username: 'super-admin',
            password: 'wrong-password'
          })
        });
        statuses.push(response.status);
      }

      if (statuses.some((status) => status === 429)) {
        throw new Error(`Trusted proxy case should key rate limits by forwarded address. statuses=${statuses.join(',')}`);
      }
    });

    console.log('HTTP hardening runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
    resetRateLimitDir();
  }
}

main().catch((error) => {
  console.error('HTTP hardening runtime validation failed:');
  console.error(`- ${error.message}`);
  process.exit(1);
});
