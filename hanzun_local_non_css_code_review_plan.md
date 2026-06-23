# Hanzun 本地项目非 CSS 代码审查与修改方案

> 目标：在**不改变现有页面样式**的前提下，对本地项目进行查缺补漏，修复工程结构、安全、稳定性、SEO、可维护性、构建和验证问题。  
> 适用场景：你的完整代码在本地，其他 AI 或开发者可以按本文逐项修改本地文件。  
> 重要约束：**不修改 CSS，不做视觉迭代，不改变页面布局和样式。**

---

## 0. 本方案的硬性边界

### 0.1 禁止修改的内容

以下内容原则上不允许修改：

```txt
assets/css/**
admin-v2/src/**/*.css
admin-v2/src/**/*.scss
admin-v2/src/**/*.less
任何会改变视觉效果的 style 属性
任何会改变布局、颜色、字体、间距、阴影、圆角、断点、动画的代码
```

同时禁止：

```txt
1. 不改页面排版。
2. 不改模块顺序。
3. 不改 class 名。
4. 不改图片资源。
5. 不替换 logo。
6. 不更换首屏图。
7. 不改按钮视觉。
8. 不改卡片结构。
9. 不重写首页。
10. 不生成新的设计稿或效果图。
```

### 0.2 允许修改的内容

允许做这些“不影响视觉”的修改：

```txt
1. 增加 .gitignore、.env.example、README、部署文档。
2. 删除或停止追踪本地敏感配置、运行产物、临时文件。
3. 加固 router.php，防止敏感文件被浏览器访问。
4. 加固上传逻辑、登录逻辑、表单提交逻辑。
5. 修改 JS 的异常兜底，例如 localStorage、fetch、菜单 aria 状态。
6. 给 HTML 图片增加 loading、decoding、fetchpriority、alt。
7. 给 HTML 增加 SEO meta、canonical、hreflang、JSON-LD。
8. 后台 API 客户端增加异常处理。
9. admin-v2 构建脚本跨平台化。
10. 增加测试脚本、CI、验证脚本。
```

### 0.3 验收标准

所有修改完成后必须满足：

```txt
1. git diff 中没有 CSS 文件变更。
2. PC、平板、手机截图和修改前基本一致。
3. 首页、产品页、新闻页、案例页、联系表单、在线客服功能正常。
4. 后台登录、内容管理、上传、构建正常。
5. 敏感文件不能通过浏览器访问。
6. 表单提交有校验、防重复、防垃圾提交。
7. npm build、composer validate、php -l、基础 smoke test 通过。
```

---

## 1. 修改前准备

### 1.1 新建安全分支

在本地项目根目录执行：

```bash
git status
git checkout -b chore/non-css-code-audit
```

如果本地有未提交代码，先提交或暂存：

```bash
git add .
git commit -m "backup before non-css audit"
```

### 1.2 保存修改前文件清单

```bash
mkdir -p .audit/before

git ls-files > .audit/before/git-files.txt

find . \
  -path ./node_modules -prune -o \
  -path ./backend/vendor -prune -o \
  -path ./.git -prune -o \
  -type f -print \
  | sort > .audit/before/all-files.txt
```

### 1.3 保存修改前截图

不要只靠肉眼判断“样式没变”，先保存截图。

建议使用浏览器或 Playwright 保存以下页面截图：

```txt
/zh/index.html
/en/index.html
/zh/products 或实际产品列表页
/zh/contact 或包含联系表单的页面
/admin-app/ 或后台登录页
```

建议视口：

```txt
1920x1080
1366x768
768x1024
390x844
```

如果暂时没有 Playwright，也可以手动截图，存到：

```txt
.audit/before/screenshots/
```

### 1.4 生成 CSS 零变更基线

```bash
mkdir -p .audit/before/css-hash

find assets/css -type f -print0 2>/dev/null \
  | sort -z \
  | xargs -0 sha256sum > .audit/before/css-hash/assets-css.sha256

find admin-v2/src -type f \( -name "*.css" -o -name "*.scss" -o -name "*.less" \) -print0 2>/dev/null \
  | sort -z \
  | xargs -0 sha256sum > .audit/before/css-hash/admin-style.sha256
```

后续验证时会对比这些 hash，确保 CSS 没被改。

---

## 2. 仓库结构查缺补漏

### 2.1 新增或完善 `.gitignore`

目标：避免把敏感文件、依赖目录、运行产物、临时文件提交到仓库。

修改或新建根目录：

```txt
.gitignore
```

建议内容：

```gitignore
# OS / editor
.DS_Store
Thumbs.db
.idea/
.vscode/
*.swp
*.swo

# logs
*.log
logs/
backend/runtime/
runtime/

# env
.env
.env.*
!.env.example
backend/.env
backend/.env.*
!backend/.env.example
admin-v2/.env
admin-v2/.env.*
!admin-v2/.env.example

# PHP dependencies / cache
backend/vendor/
vendor/
composer.phar

# Node dependencies / cache
node_modules/
admin-v2/node_modules/
.cache/
.vite/
dist/

# uploads / user generated files
uploads/
backend/public/uploads/
!uploads/.gitkeep
!backend/public/uploads/.gitkeep

# temp / backups
*.bak
*.backup
*.tmp
.tmp-*
*.old

# database dumps
*.sql
*.dump
*.sqlite
*.sqlite3

# secrets / certs / server config
*.key
*.pem
*.crt
*.p12
*.conf
*.nginx
.well-known/acme-challenge/

# local audit outputs
.audit/after/
```

验证：

```bash
git status --ignored
```

确认下面这些不再被追踪或不再准备提交：

```txt
.env
.env.production
backend/.env.production
backend/vendor/
uploads/
*.sql
*.bak
.well-known/acme-challenge/
```

如果已经被 Git 追踪，需要执行：

```bash
git rm --cached backend/.env.production 2>/dev/null || true
git rm --cached -r backend/vendor 2>/dev/null || true
git rm --cached -r uploads 2>/dev/null || true
git rm --cached "*.sql" 2>/dev/null || true
```

注意：`git rm --cached` 只是不再追踪，不会删除本地文件。

---

## 3. 环境配置安全化

### 3.1 删除真实环境配置的 Git 追踪

如果项目里有：

```txt
backend/.env.production
backend/.env
.env
.env.production
```

这些文件不能提交到公开仓库。

执行：

```bash
git rm --cached backend/.env.production 2>/dev/null || true
git rm --cached backend/.env 2>/dev/null || true
git rm --cached .env 2>/dev/null || true
git rm --cached .env.production 2>/dev/null || true
```

### 3.2 新增 `backend/.env.example`

文件：

```txt
backend/.env.example
```

建议内容：

```env
APP_NAME="Hanzun CMS"
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=Asia/Shanghai
APP_URL=http://127.0.0.1:8080

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hanzun_cms
DB_USERNAME=root
DB_PASSWORD=

AUTH_ACCESS_TTL=7200
AUTH_REFRESH_TTL=2592000
AUTH_JWT_SECRET=change_me_to_a_random_64_char_secret
AUTH_LOGIN_MAX_ATTEMPTS=5
AUTH_LOGIN_LOCK_SECONDS=900

UPLOAD_DISK=local
UPLOAD_ROOT=public/uploads
UPLOAD_MAX_IMAGE_MB=10
UPLOAD_MAX_VIDEO_MB=100
UPLOAD_MAX_FILE_MB=20

MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="Hanzun"

AI_API_KEY=
AI_API_BASE_URL=
```

### 3.3 新增 `admin-v2/.env.example`

文件：

```txt
admin-v2/.env.example
```

建议内容：

```env
VITE_DEV_PROXY_TARGET=http://127.0.0.1:8080
VITE_DEV_HOST=127.0.0.1
VITE_DEV_PORT=5174
```

### 3.4 轮换已暴露密钥

如果任何真实密钥、JWT secret、数据库密码曾经进入仓库，需要视为已经泄露。

生成新的 JWT secret：

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

本地开发把生成值写入：

```txt
backend/.env
```

生产环境在服务器上单独配置，不写进仓库。

验证：

```bash
grep -R "AUTH_JWT_SECRET" -n . \
  --exclude-dir=.git \
  --exclude=".env" \
  --exclude=".env.production"
```

仓库里只应在 `.env.example` 中出现占位值。

---

## 4. `router.php` 静态资源访问加固

> 目标：不改变页面样式，只防止浏览器访问敏感文件。

### 4.1 问题描述

如果 `router.php` 里存在类似逻辑：

```php
if (is_file($file)) {
    readfile($file);
    return true;
}
```

且没有严格白名单，可能导致下面文件被浏览器读取：

```txt
/backend/.env
/backend/.env.production
/hanzun_cms.sql
/admin-v2/package.json
/backend/composer.json
/*.conf
/*.bak
```

### 4.2 修改文件

```txt
router.php
```

### 4.3 建议新增函数

把以下函数加入 `router.php` 顶部工具函数区域：

```php
<?php

function normalizeRequestPath(string $uri): string
{
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $path = rawurldecode($path);
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path);
    return $path ?: '/';
}

function isPathTraversal(string $path): bool
{
    return str_contains($path, '..') || str_contains($path, "\0");
}

function isSensitivePath(string $path): bool
{
    $normalized = strtolower(str_replace('\\', '/', $path));

    $blockedExact = [
        '/composer.json',
        '/composer.lock',
        '/package.json',
        '/package-lock.json',
        '/pnpm-lock.yaml',
        '/yarn.lock',
        '/readme.md',
        '/migration-handoff.md',
        '/router.php',
    ];

    if (in_array($normalized, $blockedExact, true)) {
        return true;
    }

    $blockedPrefixes = [
        '/.git/',
        '/.github/',
        '/backend/',
        '/admin-v2/',
        '/node_modules/',
        '/vendor/',
        '/runtime/',
        '/logs/',
        '/database/',
        '/config/',
        '/.well-known/acme-challenge/',
    ];

    foreach ($blockedPrefixes as $prefix) {
        if (str_starts_with($normalized, $prefix)) {
            return true;
        }
    }

    $basename = basename($normalized);

    if (str_starts_with($basename, '.env')) {
        return true;
    }

    $blockedExtensions = [
        'env',
        'sql',
        'dump',
        'sqlite',
        'sqlite3',
        'conf',
        'ini',
        'log',
        'bak',
        'backup',
        'old',
        'tmp',
        'lock',
        'key',
        'pem',
        'crt',
        'p12',
        'ps1',
        'sh',
        'bat',
        'cmd',
        'php',
        'phar',
        'phtml',
    ];

    $ext = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));

    return in_array($ext, $blockedExtensions, true);
}

function isPublicAssetPath(string $path): bool
{
    $allowedPrefixes = [
        '/assets/',
        '/zh/',
        '/en/',
        '/admin-app/',
        '/uploads/',
        '/storage/',
    ];

    foreach ($allowedPrefixes as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return true;
        }
    }

    $allowedExact = [
        '/',
        '/index.html',
        '/robots.txt',
        '/sitemap.xml',
        '/favicon.ico',
    ];

    return in_array($path, $allowedExact, true);
}

function sendNotFound(): bool
{
    http_response_code(404);
    echo 'Not Found';
    return true;
}
```

### 4.4 在读取文件前统一拦截

找到所有 `readfile($file)` 或静态文件输出前的逻辑，统一加：

```php
$requestPath = normalizeRequestPath($_SERVER['REQUEST_URI'] ?? '/');

if (isPathTraversal($requestPath) || isSensitivePath($requestPath) || !isPublicAssetPath($requestPath)) {
    return sendNotFound();
}
```

如果项目中 `/api` 或 `/admin` 是后端接口，需要让这些路径交给后端入口处理，不要按静态文件读取：

```php
if (str_starts_with($requestPath, '/api/') || str_starts_with($requestPath, '/admin/')) {
    // 交给后端框架入口
}
```

### 4.5 上传目录后缀白名单

对 `/uploads/` 增加后缀限制：

```php
function isAllowedUploadFile(string $path): bool
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    $allowed = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
        'pdf',
        'mp4',
        'webm',
        'mov',
    ];

    return in_array($ext, $allowed, true);
}
```

在 `/uploads/` 分支读取前：

```php
if (str_starts_with($requestPath, '/uploads/') && !isAllowedUploadFile($requestPath)) {
    return sendNotFound();
}
```

### 4.6 验证命令

启动本地服务后执行：

```bash
curl -I http://127.0.0.1:8080/zh/index.html
curl -I http://127.0.0.1:8080/en/index.html
curl -I http://127.0.0.1:8080/assets/js/site.js
```

应返回 200。

执行：

```bash
curl -I http://127.0.0.1:8080/backend/.env.production
curl -I http://127.0.0.1:8080/backend/.env
curl -I http://127.0.0.1:8080/hanzun_cms.sql
curl -I http://127.0.0.1:8080/admin-v2/package.json
curl -I http://127.0.0.1:8080/backend/composer.json
curl -I http://127.0.0.1:8080/router.php
curl -I http://127.0.0.1:8080/uploads/test.php
```

必须返回 403 或 404，推荐 404。

---

## 5. `assets/js/site.js` 稳定性加固

> 目标：不改变页面样式，只让现有 JS 更稳、更不容易报错。

### 5.1 修改文件

```txt
assets/js/site.js
```

### 5.2 localStorage/sessionStorage 安全封装

在文件顶部增加：

```js
function safeGetStorage(storage, key) {
  try {
    return storage && typeof storage.getItem === 'function' ? storage.getItem(key) || '' : '';
  } catch {
    return '';
  }
}

function safeSetStorage(storage, key, value) {
  try {
    if (storage && typeof storage.setItem === 'function') {
      storage.setItem(key, value);
    }
  } catch {
    // ignore storage errors
  }
}

function safeRemoveStorage(storage, key) {
  try {
    if (storage && typeof storage.removeItem === 'function') {
      storage.removeItem(key);
    }
  } catch {
    // ignore storage errors
  }
}
```

### 5.3 访问上报加兜底

如果现有代码有：

```js
fetch('/api/visitor-events', ...)
```

替换成更安全版本：

```js
function trackVisit() {
  if (!window.fetch) return;

  const clientStorageKey = 'hanzun-client-id';
  const sessionStorageKey = 'hanzun-support-session';

  let clientId = safeGetStorage(window.localStorage, clientStorageKey);

  if (!clientId) {
    clientId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    safeSetStorage(window.localStorage, clientStorageKey, clientId);
  }

  const sessionCode = safeGetStorage(window.sessionStorage, sessionStorageKey);

  fetch('/api/visitor-events', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    keepalive: true,
    body: JSON.stringify({
      client_id: clientId,
      session_code: sessionCode || undefined,
      path: window.location.pathname + window.location.search,
      title: document.title,
      referrer: document.referrer,
      language:
        document.documentElement.getAttribute('lang') ||
        document.body?.dataset?.lang ||
        navigator.language ||
        'en',
    }),
  })
    .then(async (response) => {
      if (!response.ok) return;

      const result = await response.json().catch(() => null);
      const nextSessionCode = result?.data?.session_code;

      if (nextSessionCode) {
        safeSetStorage(window.sessionStorage, sessionStorageKey, nextSessionCode);
      }
    })
    .catch(() => {
      // 上报失败不影响页面
    });
}
```

调用方式保持不变：

```js
trackVisit();
```

### 5.4 菜单 aria 状态补充

如果已有移动端菜单切换逻辑：

```js
const toggle = document.querySelector('[data-menu-toggle]');
const nav = document.querySelector('[data-menu]');
```

请改成：

```js
const toggle = document.querySelector('[data-menu-toggle]');
const nav = document.querySelector('[data-menu]');

if (toggle && nav) {
  const setMenuOpen = (open) => {
    nav.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', String(open));
  };

  toggle.setAttribute('aria-expanded', nav.classList.contains('open') ? 'true' : 'false');

  toggle.addEventListener('click', () => {
    setMenuOpen(!nav.classList.contains('open'));
  });

  toggle.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      setMenuOpen(!nav.classList.contains('open'));
    }

    if (event.key === 'Escape') {
      setMenuOpen(false);
    }
  });
}
```

注意：不要改 class，不要改 CSS，只补充属性和异常处理。

### 5.5 验证

浏览器控制台不应再出现：

```txt
localStorage access denied
Cannot read properties of null
Failed to fetch 导致脚本中断
```

手动验证：

```txt
1. 首页打开正常。
2. 菜单点击正常。
3. Enter / Space 可触发菜单。
4. Escape 可关闭菜单。
5. 断网后页面不报错、不白屏。
6. 后端 /api/visitor-events 不存在时页面不受影响。
```

---

## 6. 联系表单查缺补漏

> 目标：不改样式，只增强校验、防重复、防垃圾提交、接口稳定性。

### 6.1 前端 HTML 不改变视觉

找到联系表单，例如：

```html
<form ...>
```

允许增加这些不影响视觉的属性：

```html
<form data-inquiry-form novalidate>
```

给字段增加 `name`、`autocomplete`、`maxlength`、`required`：

```html
<input name="name" autocomplete="name" maxlength="80" required>
<input name="email" autocomplete="email" maxlength="120" required>
<input name="phone" autocomplete="tel" maxlength="40">
<textarea name="message" maxlength="2000"></textarea>
```

不要改 class。

### 6.2 增加 honeypot 字段

在表单内部增加：

```html
<input
  type="text"
  name="company_website"
  tabindex="-1"
  autocomplete="off"
  aria-hidden="true"
  style="position:absolute;left:-9999px;opacity:0;height:0;width:0;"
>
```

说明：这行使用了内联 style，但它不会影响现有可见样式，只是隐藏机器人诱捕字段。若团队严格禁止任何 style 变更，可以改为使用现有全局隐藏类；但不能新增 CSS。

### 6.3 前端 JS 提交防重复

如果表单已有提交逻辑，只增强，不改视觉。

示例：

```js
function initInquiryForm() {
  const form = document.querySelector('[data-inquiry-form]');
  if (!form || !window.fetch) return;

  let submitting = false;

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (submitting) return;

    const formData = new FormData(form);
    const payload = Object.fromEntries(formData.entries());

    if (payload.company_website) {
      return;
    }

    const email = String(payload.email || '').trim();
    const name = String(payload.name || '').trim();

    if (!name) {
      alert('Please enter your name.');
      return;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      alert('Please enter a valid email address.');
      return;
    }

    submitting = true;

    const submitButton = form.querySelector('[type="submit"]');
    const originalText = submitButton ? submitButton.textContent : '';

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.textContent = submitButton.dataset.loadingText || originalText;
    }

    try {
      const response = await fetch('/api/inquiries', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          ...payload,
          source_path: window.location.pathname,
          referrer: document.referrer,
          language: document.documentElement.lang || document.body?.dataset?.lang || '',
        }),
      });

      const result = await response.json().catch(() => null);

      if (!response.ok || result?.code) {
        throw new Error(result?.message || 'Submit failed.');
      }

      form.reset();
      alert(result?.message || 'Submitted successfully.');
    } catch (error) {
      alert(error?.message || 'Submit failed. Please try again later.');
    } finally {
      submitting = false;

      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
      }
    }
  });
}

initInquiryForm();
```

注意：

```txt
1. 如果现有项目已有 toast/提示组件，继续用原有组件。
2. 不新增样式。
3. 不改按钮 class。
4. 不改表单布局。
```

### 6.4 后端接口校验

接口建议：

```txt
POST /api/inquiries
```

后端必须校验：

```txt
name: 必填，2-80 字符
email: 必填，合法邮箱，5-120 字符
phone: 可选，5-40 字符
message: 可选，最大 2000 字符
product_interest: 可选，最大 200 字符
source_path: 可选，最大 500 字符
referrer: 可选，最大 1000 字符
language: 可选，最大 20 字符
company_website: honeypot，非空直接返回成功但不入库
```

防重复：

```txt
1. 同一 IP 60 秒内最多提交 3 次。
2. 同一 email 10 分钟内最多提交 2 次。
3. 相同 email + message 30 分钟内重复提交只保存一次。
```

统一响应：

```json
{
  "code": 0,
  "message": "Submitted successfully.",
  "data": {
    "id": 123
  }
}
```

### 6.5 验证

```bash
curl -X POST http://127.0.0.1:8080/api/inquiries \
  -H "Content-Type: application/json" \
  -d '{"name":"","email":"bad"}'
```

应返回 422。

```bash
curl -X POST http://127.0.0.1:8080/api/inquiries \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","message":"Need a bakery line"}'
```

应返回 200 或业务成功 JSON。

测试 honeypot：

```bash
curl -X POST http://127.0.0.1:8080/api/inquiries \
  -H "Content-Type: application/json" \
  -d '{"name":"Bot","email":"bot@example.com","company_website":"spam"}'
```

应返回成功外观响应，但后台不保存真实询盘。

---

## 7. 上传安全加固

> 目标：后台上传功能更安全，不改变前台页面样式。

### 7.1 检查文件

常见路径：

```txt
backend/config/upload.php
backend/app/controller/admin/UploadController.php
backend/app/service/UploadService.php
```

以本地实际路径为准。

### 7.2 建议规则

```txt
1. 上传文件必须重命名。
2. 真实存储名使用随机 hash，不使用用户原始文件名。
3. 后缀、MIME、文件头三重校验。
4. 默认不允许 svg。
5. 上传目录禁止执行 php/phtml/phar。
6. 限制图片像素宽高。
7. 限制文件大小。
8. 返回给前端的 URL 必须是公开路径，不能返回服务器绝对路径。
```

### 7.3 文件名生成

```php
function generateUploadFilename(string $extension): string
{
    $extension = strtolower($extension);
    return date('Ymd') . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
}
```

### 7.4 图片文件头校验

```php
function isValidImageFile(string $path): bool
{
    $info = @getimagesize($path);

    if ($info === false) {
        return false;
    }

    [$width, $height] = $info;

    if ($width <= 0 || $height <= 0) {
        return false;
    }

    if ($width > 8000 || $height > 8000) {
        return false;
    }

    return true;
}
```

### 7.5 禁止危险后缀

```php
$blockedExtensions = [
    'php',
    'phtml',
    'phar',
    'cgi',
    'pl',
    'py',
    'sh',
    'bat',
    'cmd',
    'exe',
    'dll',
    'js',
    'html',
    'htm',
    'svg',
];
```

如业务确实需要 SVG，必须先做 SVG 清洗，否则不建议允许。

### 7.6 验证

准备一个伪装文件：

```bash
echo "<?php phpinfo();" > /tmp/test.php
cp /tmp/test.php /tmp/test.jpg
```

上传 `/tmp/test.jpg`，必须失败。

上传正常 jpg/png/webp，应成功。

通过浏览器访问：

```txt
/uploads/xxx.php
/uploads/xxx.phtml
/uploads/xxx.phar
```

必须 403 或 404。

---

## 8. 后台登录与鉴权查缺补漏

### 8.1 默认账号风险

如果项目文档或 seed 中存在：

```txt
admin / admin123456
```

要求：

```txt
1. 只能用于本地开发。
2. 生产环境禁止使用。
3. 首次登录必须提示修改密码，或后台强制修改。
4. APP_ENV=production 或 APP_DEBUG=false 时，检测到默认密码应拒绝登录。
```

### 8.2 新增生产安全检查脚本

文件：

```txt
backend/scripts/check-production-security.php
```

建议内容：

```php
<?php

$env = getenv('APP_ENV') ?: 'local';
$debug = getenv('APP_DEBUG') ?: 'true';

if ($env !== 'production' && $debug !== 'false') {
    echo "Skip production security check.\n";
    exit(0);
}

$required = [
    'AUTH_JWT_SECRET',
    'DB_HOST',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
];

$failed = false;

foreach ($required as $key) {
    $value = getenv($key);

    if ($value === false || trim((string) $value) === '') {
        fwrite(STDERR, "{$key} is required in production.\n");
        $failed = true;
    }
}

$jwt = getenv('AUTH_JWT_SECRET') ?: '';

if ($jwt === 'change_me_to_a_random_64_char_secret' || strlen($jwt) < 32) {
    fwrite(STDERR, "AUTH_JWT_SECRET is insecure.\n");
    $failed = true;
}

exit($failed ? 1 : 0);
```

### 8.3 登录失败限制

确认后端实际实现：

```txt
1. 同一用户名 + IP 连续失败超过 N 次锁定。
2. 锁定时间来自环境变量。
3. 登录成功后清除失败计数。
4. 返回信息不要暴露“用户名存在/不存在”。
```

错误提示统一：

```txt
Invalid username or password.
```

### 8.4 Token 失效规则

确认实现：

```txt
1. logout 后 refresh token 失效。
2. 修改密码后所有旧 refresh token 失效。
3. 管理员禁用后所有 token 失效。
4. JWT secret 不在代码中硬编码。
```

### 8.5 验证

```txt
1. 连续输错密码，达到阈值后锁定。
2. logout 后刷新接口失败。
3. 改密码后旧 token 不能再用。
4. 禁用用户后旧 token 不能再用。
5. 生产环境使用默认 secret 时启动检查失败。
```

---

## 9. `admin-v2` 构建脚本跨平台化

> 目标：不改后台样式，只让构建在 Windows、Linux、macOS 都可运行。

### 9.1 问题

如果 `admin-v2/package.json` 中有 PowerShell 专用命令，例如：

```json
"build": "powershell -Command \"Remove-Item ...\" && vite build"
```

Linux/macOS/CI 可能失败。

### 9.2 新增清理脚本

文件：

```txt
admin-v2/scripts/clean-output.mjs
```

内容：

```js
import fs from 'node:fs/promises';
import path from 'node:path';

const outputDir = path.resolve(process.cwd(), '../admin-app');

try {
  await fs.rm(outputDir, { recursive: true, force: true });
  console.log(`Removed ${outputDir}`);
} catch (error) {
  console.error(`Failed to remove ${outputDir}`);
  console.error(error);
  process.exit(1);
}
```

### 9.3 修改 `admin-v2/package.json`

```json
{
  "scripts": {
    "dev": "vite",
    "build": "node scripts/clean-output.mjs && vite build",
    "preview": "vite preview"
  }
}
```

不要改依赖版本，除非当前构建确实失败。

### 9.4 验证

```bash
cd admin-v2
npm ci
npm run build
```

验证输出目录：

```bash
ls ../admin-app
```

浏览器打开：

```txt
/admin-app/
```

后台页面应正常加载，样式不变。

---

## 10. `admin-v2` API 客户端稳定性

### 10.1 检查文件

常见路径：

```txt
admin-v2/src/api/client.js
admin-v2/src/api/auth.js
```

### 10.2 修改目标

不改后台 UI，只增强：

```txt
1. 请求超时。
2. 网络断开提示。
3. 401 统一处理。
4. refresh token 失败只触发一次退出。
5. 500 错误不暴露后端堆栈。
6. 429 限流提示明确。
```

### 10.3 建议增加错误归一化

```js
function normalizeApiError(error, response) {
  if (error?.name === 'AbortError') {
    return new Error('Request timed out. Please try again.');
  }

  if (typeof navigator !== 'undefined' && navigator.onLine === false) {
    return new Error('Network is offline.');
  }

  if (response?.status === 429) {
    return new Error('Too many requests. Please try again later.');
  }

  if (response?.status >= 500) {
    return new Error('Server error. Please try again later.');
  }

  return error instanceof Error ? error : new Error('Request failed.');
}
```

### 10.4 验证

```txt
1. 关闭后端服务，后台不会白屏。
2. token 过期后刷新成功。
3. refresh token 失效后跳回登录页。
4. 连续请求触发 429 时提示明确。
5. 后端返回 500 时不显示堆栈。
```

---

## 11. HTML SEO 查缺补漏，不改样式

> 目标：只改 `<head>` 和不可见语义属性，不改页面视觉。

### 11.1 修改范围

```txt
zh/**/*.html
en/**/*.html
index.html
index.template.html
```

禁止：

```txt
1. 不改 class。
2. 不改可见文案，除非修错别字。
3. 不改模块顺序。
4. 不改图片 src。
5. 不改 CSS 引用顺序，除非只是修复明显错误路径。
```

### 11.2 每页必须检查

```txt
<title>
<meta name="description">
<link rel="canonical">
<link rel="alternate" hreflang="zh-CN">
<link rel="alternate" hreflang="en">
<link rel="alternate" hreflang="x-default">
<meta property="og:title">
<meta property="og:description">
<meta property="og:type">
<meta property="og:url">
<meta property="og:image">
```

示例：

```html
<link rel="canonical" href="https://www.hanzunfactory.com/zh/index.html">
<link rel="alternate" hreflang="zh-CN" href="https://www.hanzunfactory.com/zh/index.html">
<link rel="alternate" hreflang="en" href="https://www.hanzunfactory.com/en/index.html">
<link rel="alternate" hreflang="x-default" href="https://www.hanzunfactory.com/en/index.html">
```

### 11.3 Organization JSON-LD

在首页 head 内增加：

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Hanzun Machinery",
  "url": "https://www.hanzunfactory.com/",
  "logo": "https://www.hanzunfactory.com/assets/images/logo.png",
  "email": "hanzunkunshanmachinery@gmail.com",
  "address": {
    "@type": "PostalAddress",
    "addressCountry": "CN",
    "addressRegion": "Jiangsu",
    "addressLocality": "Kunshan"
  }
}
</script>
```

请根据本地真实域名、logo 路径、邮箱、地址修改。

### 11.4 产品详情页 JSON-LD

产品页增加：

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "产品名称",
  "image": "https://www.hanzunfactory.com/图片路径",
  "description": "产品描述",
  "brand": {
    "@type": "Brand",
    "name": "Hanzun"
  }
}
</script>
```

### 11.5 新闻页 JSON-LD

新闻详情页增加：

```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "文章标题",
  "datePublished": "2024-01-01",
  "dateModified": "2024-01-01",
  "author": {
    "@type": "Organization",
    "name": "Hanzun Machinery"
  }
}
</script>
```

### 11.6 验证

```bash
grep -R "<title>" -n zh en | wc -l
grep -R "rel=\"canonical\"" -n zh en | wc -l
grep -R "hreflang" -n zh en | wc -l
grep -R "application/ld+json" -n zh en | wc -l
```

浏览器打开页面，视觉应完全不变。

---

## 12. 图片属性补充，不改样式

### 12.1 允许增加的属性

```html
loading="lazy"
decoding="async"
fetchpriority="high"
alt="..."
width="..."
height="..."
```

### 12.2 首屏图

首屏主图不要 lazy：

```html
<img
  src="..."
  alt="食品机械自动化生产线"
  decoding="async"
  fetchpriority="high"
>
```

### 12.3 非首屏图

```html
<img
  src="..."
  alt="..."
  loading="lazy"
  decoding="async"
>
```

### 12.4 width/height 注意事项

只有在确认图片真实尺寸且不会改变布局时，才添加：

```html
width="1200"
height="800"
```

如果添加后截图有差异，撤回 width/height，只保留 loading/decoding/alt。

### 12.5 验证

```bash
grep -R "<img" -n zh en | wc -l
grep -R "loading=\"lazy\"" -n zh en | wc -l
grep -R "decoding=\"async\"" -n zh en | wc -l
grep -R "alt=\"" -n zh en | wc -l
```

浏览器验证：

```txt
1. 图片仍正常显示。
2. 图片尺寸无变化。
3. 布局无跳动。
4. 首屏图没有被 lazy。
```

---

## 13. 根目录脚本补充

### 13.1 修改文件

```txt
package.json
```

### 13.2 建议脚本

不要添加自动格式化 CSS 的脚本。

```json
{
  "scripts": {
    "dev:site": "live-server --port=5173 --open=/zh/index.html",
    "dev:admin": "npm --prefix admin-v2 run dev",
    "build:admin": "npm --prefix admin-v2 run build",
    "check:admin": "npm --prefix admin-v2 run build",
    "check:php": "cd backend && composer validate --strict",
    "check": "npm run check:admin && npm run check:php"
  }
}
```

如果已有 scripts，合并进去，不要覆盖原有有效命令。

### 13.3 验证

```bash
npm run dev:site
npm run build:admin
npm run check
```

---

## 14. 后端 Composer 和 PHP 检查

### 14.1 验证 composer

```bash
cd backend
composer validate --strict
composer install
```

### 14.2 PHP 语法检查

Linux/macOS：

```bash
find app config route public -name "*.php" -print0 2>/dev/null | xargs -0 -n1 php -l
```

Windows PowerShell：

```powershell
Get-ChildItem -Path app,config,route,public -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
```

### 14.3 路由检查

确认：

```txt
/admin/* 需要登录
/api/site/* 可公开访问
/api/inquiries 可公开提交但有限流和校验
/api/visitor-events 可公开提交但有限流
/backend/* 不可通过浏览器访问
```

---

## 15. GitHub Actions CI

> 如果项目使用 GitHub，建议增加 CI。CI 不改样式，只防止代码出错。

### 15.1 新增文件

```txt
.github/workflows/ci.yml
```

### 15.2 建议内容

```yaml
name: CI

on:
  push:
  pull_request:

jobs:
  admin-v2-build:
    runs-on: ubuntu-latest
    if: ${{ hashFiles('admin-v2/package.json') != '' }}
    defaults:
      run:
        working-directory: admin-v2
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 20
          cache: npm
          cache-dependency-path: admin-v2/package-lock.json
      - run: npm ci
      - run: npm run build

  backend-php-check:
    runs-on: ubuntu-latest
    if: ${{ hashFiles('backend/composer.json') != '' }}
    defaults:
      run:
        working-directory: backend
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer validate --strict
      - run: composer install --no-interaction --no-progress --prefer-dist
      - run: find app config route public -name "*.php" -print0 2>/dev/null | xargs -0 -n1 php -l
```

### 15.3 验证

本地提交后推送测试分支，GitHub Actions 应通过。

---

## 16. 视觉不变验证

### 16.1 CSS hash 对比

修改完成后执行：

```bash
mkdir -p .audit/after/css-hash

find assets/css -type f -print0 2>/dev/null \
  | sort -z \
  | xargs -0 sha256sum > .audit/after/css-hash/assets-css.sha256

find admin-v2/src -type f \( -name "*.css" -o -name "*.scss" -o -name "*.less" \) -print0 2>/dev/null \
  | sort -z \
  | xargs -0 sha256sum > .audit/after/css-hash/admin-style.sha256

diff -u .audit/before/css-hash/assets-css.sha256 .audit/after/css-hash/assets-css.sha256
diff -u .audit/before/css-hash/admin-style.sha256 .audit/after/css-hash/admin-style.sha256
```

如果有 diff，说明 CSS 被改了，必须回退。

### 16.2 Git diff 排查

```bash
git diff --name-only
```

确认没有：

```txt
assets/css/*
*.css
*.scss
*.less
```

检查是否有 class 变动：

```bash
git diff -- zh en | grep -E "class=|className=" || true
```

如果有 class diff，需要人工确认不是视觉变更。

### 16.3 截图对比

修改完成后保存截图到：

```txt
.audit/after/screenshots/
```

对比修改前后：

```txt
.audit/before/screenshots/
.audit/after/screenshots/
```

验收页面：

```txt
/zh/index.html
/en/index.html
产品列表页
产品详情页
新闻列表页
新闻详情页
联系表单区域
后台登录页
```

视口：

```txt
1920x1080
1366x768
768x1024
390x844
```

允许差异：

```txt
1. 动态时间。
2. 接口数据排序变化。
3. 浏览器字体渲染极小差异。
```

不允许差异：

```txt
1. 颜色变化。
2. 间距变化。
3. 图片尺寸变化。
4. 卡片宽度变化。
5. 字号变化。
6. 按钮位置变化。
7. 模块顺序变化。
```

---

## 17. 安全访问验证清单

启动本地服务后执行。

### 17.1 应该能访问

```bash
curl -I http://127.0.0.1:8080/
curl -I http://127.0.0.1:8080/zh/index.html
curl -I http://127.0.0.1:8080/en/index.html
curl -I http://127.0.0.1:8080/assets/js/site.js
curl -I http://127.0.0.1:8080/assets/css/site.css
curl -I http://127.0.0.1:8080/robots.txt
curl -I http://127.0.0.1:8080/sitemap.xml
```

### 17.2 不应该能访问

```bash
curl -I http://127.0.0.1:8080/backend/.env
curl -I http://127.0.0.1:8080/backend/.env.production
curl -I http://127.0.0.1:8080/.env
curl -I http://127.0.0.1:8080/hanzun_cms.sql
curl -I http://127.0.0.1:8080/backend/composer.json
curl -I http://127.0.0.1:8080/admin-v2/package.json
curl -I http://127.0.0.1:8080/package.json
curl -I http://127.0.0.1:8080/router.php
curl -I http://127.0.0.1:8080/.git/config
curl -I http://127.0.0.1:8080/uploads/test.php
```

全部应返回 403 或 404。

---

## 18. 功能验证清单

### 18.1 前台

```txt
[ ] 首页正常打开。
[ ] 中文页面正常打开。
[ ] 英文页面正常打开。
[ ] 导航链接正常。
[ ] 语言切换正常。
[ ] 产品卡片点击正常。
[ ] 新闻链接正常。
[ ] 案例链接正常。
[ ] 联系表单校验正常。
[ ] 联系表单提交成功。
[ ] 重复点击不会重复提交。
[ ] 在线客服入口正常。
[ ] WhatsApp、邮箱、电话链接正常。
[ ] 浏览器控制台无严重 JS 报错。
```

### 18.2 后台

```txt
[ ] 后台登录页正常打开。
[ ] 正确账号可登录。
[ ] 错误密码有提示。
[ ] 连续错误触发限制。
[ ] token 过期后可刷新或回到登录页。
[ ] 内容列表正常加载。
[ ] 产品编辑正常。
[ ] 新闻编辑正常。
[ ] 上传正常图片成功。
[ ] 上传伪装 PHP 文件失败。
[ ] 退出登录后不能访问后台接口。
```

### 18.3 构建

```txt
[ ] npm run build:admin 通过。
[ ] composer validate --strict 通过。
[ ] php -l 全部通过。
[ ] GitHub Actions 通过。
```

---

## 19. 推荐提交拆分

不要一次提交所有内容，建议按风险拆分：

### Commit 1：仓库安全与忽略规则

```bash
git add .gitignore backend/.env.example admin-v2/.env.example
git commit -m "chore: add env examples and ignore sensitive files"
```

### Commit 2：路由安全

```bash
git add router.php
git commit -m "fix: block sensitive static file access"
```

### Commit 3：前台 JS 稳定性

```bash
git add assets/js/site.js
git commit -m "fix: harden frontend runtime scripts"
```

### Commit 4：表单与上传加固

```bash
git add backend
git commit -m "fix: harden inquiry and upload handling"
```

### Commit 5：后台构建脚本

```bash
git add admin-v2/package.json admin-v2/scripts/clean-output.mjs
git commit -m "chore: make admin build cross-platform"
```

### Commit 6：SEO 与图片属性

```bash
git add zh en index.html index.template.html
git commit -m "chore: improve metadata and image attributes without style changes"
```

### Commit 7：CI 与文档

```bash
git add .github README.md
git commit -m "chore: add ci and verification docs"
```

每个 commit 后都跑一次：

```bash
git diff --name-only HEAD~1 HEAD
```

确认没有 CSS 文件。

---

## 20. 回滚方案

如果发现样式变化：

### 20.1 回滚 CSS

如果误改 CSS：

```bash
git checkout -- assets/css
git checkout -- admin-v2/src
```

注意：第二条会回退 `admin-v2/src` 所有修改。如果里面有非 CSS 修改，先用 `git diff` 选择性处理。

### 20.2 回滚某个 commit

```bash
git log --oneline
git revert <commit_hash>
```

### 20.3 回到修改前分支

```bash
git checkout main
git branch -D chore/non-css-code-audit
```

---

## 21. 给执行者的最终提示词

可以把下面这段直接发给其他 AI 或开发者：

```txt
请在本地 Hanzun 项目中执行非 CSS 代码审查和修复。

硬性要求：
1. 不修改任何 CSS、SCSS、LESS 文件。
2. 不改变页面样式、颜色、字体、间距、圆角、阴影、布局、图片、模块顺序。
3. 不修改现有 class 名。
4. 所有修改必须以“视觉不变”为前提。
5. 修改完成后必须提供 CSS hash 对比和修改前后截图对比。

允许修改：
1. .gitignore、.env.example、README、部署文档。
2. router.php 静态文件访问安全。
3. assets/js/site.js 的异常兜底、菜单 aria 状态、访问上报失败兜底。
4. 联系表单的前后端校验、防重复、防垃圾提交。
5. 上传安全校验。
6. 后台登录、token、默认密码风险修复。
7. admin-v2 package.json 构建脚本跨平台化。
8. admin-v2 API 客户端错误处理。
9. HTML head 中的 SEO meta、canonical、hreflang、JSON-LD。
10. img 标签的 loading、decoding、fetchpriority、alt。
11. CI、测试、验证脚本。

重点验证：
1. git diff 中没有 CSS 文件。
2. PC 和移动端截图与修改前一致。
3. /backend/.env、/.env、/*.sql、/admin-v2/package.json、/router.php 等敏感路径不能访问。
4. 联系表单提交正常，重复提交被限制。
5. 上传伪装 PHP 文件失败。
6. 后台登录、构建、退出、token 刷新正常。
7. npm run build:admin、composer validate、php -l 通过。
```

---

## 22. 最终交付物

执行完成后，交付这些内容：

```txt
1. 修改文件列表。
2. 每个文件为什么修改。
3. CSS hash 对比结果。
4. 修改前后截图。
5. 安全访问 curl 验证结果。
6. 表单提交验证结果。
7. 上传安全验证结果。
8. 后台登录与 token 验证结果。
9. npm/composer/php 检查结果。
10. 是否存在未解决问题。
```

---

## 23. 最终确认表

```txt
[ ] 没有修改 CSS。
[ ] 没有改变样式。
[ ] 没有改变页面布局。
[ ] 没有替换图片。
[ ] 没有改 class 名。
[ ] 敏感文件已从 Git 追踪中移除。
[ ] .env.example 已补充。
[ ] router.php 已阻断敏感路径。
[ ] 上传安全已加固。
[ ] 表单校验和防重复已完成。
[ ] 前台 JS 有异常兜底。
[ ] 后台构建脚本跨平台。
[ ] SEO 标签已补充。
[ ] 图片 loading/alt 已补充。
[ ] CI 已补充。
[ ] 本地验证全部通过。
```

本方案的核心原则是：**只提升代码质量、安全性、稳定性、SEO 和工程可维护性，不做任何 CSS 或视觉变化。**
