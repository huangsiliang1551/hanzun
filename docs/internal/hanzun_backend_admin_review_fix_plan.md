# Hanzun 后台与后端代码审查修复任务书（不改 CSS / 不改样式）

> 适用对象：将本文直接交给其他 AI 或开发者执行。  
> 项目：`huangsiliang1551/hanzun`  
> 目标：审查并修复 `admin-v2` 后台前端与 `backend` 后端中的不合理点、潜在 Bug、安全风险和稳定性问题。  
> 强制约束：**不得修改 CSS，不得改变页面样式、布局、颜色、间距、字体、圆角、阴影、图片、DOM 结构视觉效果。**

---

## 0. 执行总原则

### 0.1 本次允许修改的范围

允许修改以下类型文件：

```txt
backend/**/*.php
backend/config/**/*.php
backend/route/**/*.php
backend/.env.example
admin-v2/src/**/*.js
admin-v2/src/**/*.jsx
admin-v2/src/**/*.ts
admin-v2/src/**/*.tsx
admin-v2/package.json
admin-v2/scripts/**/*.mjs
router.php
README.md
.github/workflows/*.yml
package.json
.gitignore
```

### 0.2 本次禁止修改的范围

禁止修改：

```txt
assets/css/**
*.css
admin-v2/src/**/*.css
admin-v2/src/**/*.scss
admin-v2/src/**/*.less
前台页面视觉结构
图片资源
产品内容文案，除非是修复乱码或明显错误
页面布局 class 名，除非确认不会影响 CSS
```

### 0.3 验收铁律

修改完成后必须满足：

```txt
1. git diff 中不包含 CSS 文件。
2. 后台 build 通过。
3. 后端 composer validate --strict 通过。
4. PHP 语法检查通过。
5. GitHub Actions 通过。
6. 修复项有明确验证命令或测试说明。
7. 提交后提供 commit hash 供复查。
```

---

## 1. Git 工作流要求

### 1.1 开始前

```bash
git fetch origin
git checkout main
git pull --ff-only origin main
git status
```

确认工作区干净后再改。

### 1.2 建议使用独立分支

```bash
git checkout -b fix/backend-admin-hardening
```

如果必须直接推 `main`，每一批修改也要保持小提交，便于复查。

### 1.3 每次提交前检查 CSS 未改动

```bash
git diff --name-only -- "*.css" "assets/css/**" "admin-v2/src/**/*.css" "admin-v2/src/**/*.scss" "admin-v2/src/**/*.less"
```

期望无输出。

也可以检查最近提交是否包含 CSS：

```bash
git show --name-only --pretty="" HEAD | grep -E '\.css$|assets/css|admin-v2/src/.*\.(css|scss|less)$' || true
```

期望无输出。

---

# 2. P0 修复项：优先处理

---

## P0-1. 后端 BusinessException 应映射正确 HTTP 状态码

### 问题

当前后端业务异常大多可能返回 HTTP 200，只在少数鉴权错误时返回 401。这样会导致前端、监控、代理层、第三方系统误判接口成功。

### 重点文件

```txt
backend/app/common/bootstrap/Application.php
backend/app/enum/ErrorCode.php
```

### 修改目标

为 `BusinessException` 增加统一 HTTP status 映射。

建议映射：

```txt
UNAUTHORIZED / INVALID_REFRESH_TOKEN / USER_DISABLED => 401
FORBIDDEN / ACTION_FORBIDDEN => 403
NOT_FOUND => 404
ALREADY_EXISTS => 409
INVALID_PARAMS => 422
限流错误 => 429
INTERNAL_ERROR => 500
其他业务错误 => 400
```

### 参考实现

在 `Application.php` 中增加类似方法：

```php
private function statusFromBusinessException(BusinessException $exception): int
{
    $errorCode = $exception->errorCode();

    return match (true) {
        in_array($errorCode, [
            ErrorCode::UNAUTHORIZED,
            ErrorCode::INVALID_REFRESH_TOKEN,
            ErrorCode::USER_DISABLED,
        ], true) => 401,

        in_array($errorCode, [
            ErrorCode::FORBIDDEN,
            ErrorCode::ACTION_FORBIDDEN,
        ], true) => 403,

        $errorCode === ErrorCode::NOT_FOUND => 404,
        $errorCode === ErrorCode::ALREADY_EXISTS => 409,
        $errorCode === ErrorCode::INVALID_PARAMS => 422,
        $exception->getCode() === 429 => 429,
        $errorCode === ErrorCode::INTERNAL_ERROR => 500,

        default => 400,
    };
}
```

捕获 `BusinessException` 时使用：

```php
return Response::json(
    [
        'code' => $exception->errorCode(),
        'message' => $exception->getMessage(),
        'data' => null,
    ],
    $this->statusFromBusinessException($exception)
);
```

### 验证

启动本地服务：

```bash
php -S 127.0.0.1:8080 router.php
```

未登录访问后台接口：

```bash
curl -i http://127.0.0.1:8080/admin/auth/profile
```

期望：

```txt
HTTP/1.1 401 Unauthorized
```

参数错误：

```bash
curl -i -X POST http://127.0.0.1:8080/api/site/lead \
  -H "Content-Type: application/json" \
  -d '{"name":"","email":"bad"}'
```

期望：

```txt
HTTP/1.1 422
```

---

## P0-2. RateLimitMiddleware 限流 IP 不应默认信任 X-Forwarded-For

### 问题

如果直接读取 `HTTP_X_FORWARDED_FOR` / `HTTP_X_REAL_IP`，攻击者可以伪造请求头绕过限流。限流存储不可写时也不应静默放行关键接口。

### 重点文件

```txt
backend/app/common/middleware/RateLimitMiddleware.php
backend/app/common/http/Router.php
backend/.env.example
```

### 修改目标

1. 仅当 `REMOTE_ADDR` 属于可信代理时，才读取 `X-Forwarded-For`。
2. 增加 `TRUSTED_PROXIES` 配置。
3. 限流存储目录不可写时至少写日志；对登录、询盘等关键接口建议 fail-closed。
4. 尽可能保证读-改-写过程有锁，避免并发穿透。

### 参考实现

新增 IP 解析方法：

```php
private function resolveClientIp(): string
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $trustedProxies = array_filter(array_map(
        'trim',
        explode(',', getenv('TRUSTED_PROXIES') ?: '')
    ));

    if ($remoteAddr !== 'unknown' && in_array($remoteAddr, $trustedProxies, true)) {
        $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        if ($forwardedFor !== '') {
            return trim(explode(',', $forwardedFor)[0]);
        }

        $realIp = $_SERVER['HTTP_X_REAL_IP'] ?? '';

        if ($realIp !== '') {
            return trim($realIp);
        }
    }

    return $remoteAddr;
}
```

在 `backend/.env.example` 增加：

```env
TRUSTED_PROXIES=127.0.0.1
```

如果生产环境在 Nginx / CDN 后面，应配置真实代理 IP。

### 验证

连续失败登录测试：

```bash
for i in {1..7}; do
  curl -s -o /dev/null -w "%{http_code}\n" \
    -X POST http://127.0.0.1:8080/admin/auth/login \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"wrong"}'
done
```

期望最后几次返回：

```txt
429
```

伪造 XFF 测试：

```bash
for i in {1..7}; do
  curl -s -o /dev/null -w "%{http_code}\n" \
    -X POST http://127.0.0.1:8080/admin/auth/login \
    -H "X-Forwarded-For: 8.8.8.$i" \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"wrong"}'
done
```

如果没有配置可信代理，伪造 XFF 不应绕过限流。

---

## P0-3. Refresh Token 轮换需要原子化，避免并发重放

### 问题

刷新 token 时，如果流程是“查询旧 session 是否有效 → 撤销旧 session → 签发新 token”，并发请求可能同时通过查询，导致同一个 refresh token 签发多个新 token。

### 重点文件

```txt
backend/app/service/auth/SessionService.php
backend/app/service/auth/AuthService.php
```

### 修改目标

1. 数据库存储路径使用事务或带条件的原子更新。
2. 同一个 refresh token 并发刷新时，只允许一次成功。
3. 其他请求必须返回 401 / INVALID_REFRESH_TOKEN。
4. 如果 runtime JSON fallback 也支持 refresh，需要使用文件锁避免并发。

### 参考思路

伪代码：

```php
$pdo->beginTransaction();

$stmt = $pdo->prepare(
    'UPDATE admin_sessions
     SET revoked_at = :now
     WHERE session_code = :session_code
       AND refresh_token_hash = :refresh_hash
       AND revoked_at IS NULL
       AND expired_at > :now'
);

$stmt->execute([
    'session_code' => $sessionCode,
    'refresh_hash' => $refreshHash,
    'now' => $now,
]);

if ($stmt->rowCount() !== 1) {
    $pdo->rollBack();
    throw new BusinessException(ErrorCode::INVALID_REFRESH_TOKEN, 'Invalid refresh token.');
}

$newTokens = $this->issueTokens($userId, $context);

$pdo->commit();
```

### 验证

1. 正常登录获取 `refresh_token`。
2. 用同一个 refresh token 并发调用 `/admin/auth/refresh` 10 次。
3. 期望只有 1 次成功，其余失败。

示例：

```bash
seq 1 10 | xargs -I{} -P10 curl -s \
  -X POST http://127.0.0.1:8080/admin/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"同一个refresh_token"}'
```

---

## P0-4. MediaService 删除物理文件路径可能拼接错误

### 问题

媒体文件删除时，如果 `file_path` 已经是 `/uploads/...`，再与上传根目录拼接，可能形成重复路径，导致数据库记录删除了，但物理文件残留。

### 重点文件

```txt
backend/app/service/media/MediaService.php
backend/app/adminapi/controller/media/MediaController.php
```

### 修改目标

1. 明确处理 `/uploads/` 和 `/assets/` 路径。
2. 禁止 `..` 路径穿越。
3. 删除记录时同时删除物理文件。
4. 删除失败应记录日志或返回明确错误，不要假装成功。

### 参考实现

```php
private function resolveDiskPath(string $filePath): string
{
    $relative = ltrim($filePath, '/');

    if (str_contains($relative, '..')) {
        throw new BusinessException(ErrorCode::FILE_NOT_FOUND, 'Invalid file path.');
    }

    if (str_starts_with($relative, 'uploads/')) {
        return base_path('public/' . $relative);
    }

    if (str_starts_with($relative, 'assets/')) {
        return base_path('public/' . $relative);
    }

    throw new BusinessException(ErrorCode::FILE_NOT_FOUND, 'Invalid file path.');
}
```

### 验证

1. 后台上传一张 jpg/png/webp。
2. 记录返回的 `file_path`。
3. 确认磁盘文件存在。
4. 后台删除该媒体。
5. 确认记录删除。
6. 确认物理文件也被删除。

命令示例：

```bash
find backend public -path "*uploads*" -type f | grep "刚才上传的文件名" || true
```

删除后期望无输出。

---

## P0-5. 默认禁用 SVG 上传

### 问题

SVG 是 XML 文档，容易引入 XSS、外链、事件属性和命名空间绕过。即使做简单正则清洗，也很难彻底安全。

### 重点文件

```txt
backend/config/upload.php
backend/app/service/media/MediaService.php
```

### 修改目标

1. 默认从上传白名单中移除 `svg`。
2. 默认移除 `image/svg+xml`。
3. 如果业务必须上传 SVG，应只允许超级管理员，并使用成熟 sanitizer 库。
4. 不要改前台或后台样式。

### 建议修改

从配置中移除：

```txt
svg
image/svg+xml
```

### 验证

准备 `test.svg`：

```bash
curl -i -F "file=@test.svg" http://127.0.0.1:8080/admin/media/upload \
  -H "Authorization: Bearer 管理员access_token"
```

期望：

```txt
HTTP 422
或业务错误：不支持的文件类型
```

---

## P0-6. source_path 导入范围过宽

### 问题

媒体接口如果支持 `source_path`，且允许从项目根目录导入文件，后台账号被滥用时可能把内部文件复制到公开上传目录。

### 重点文件

```txt
backend/app/adminapi/controller/media/MediaController.php
backend/app/service/media/MediaService.php
```

### 修改目标

1. 不允许从整个 `base_path()` 导入。
2. 只允许从明确临时导入目录读取，例如：

```txt
runtime/imports/
public/uploads/tmp/
```

3. 导入文件必须继续走扩展名、MIME、文件头、大小检查。
4. 禁止路径穿越。

### 验证

尝试导入项目内部文件：

```json
{
  "source_path": "/项目根目录/backend/.env",
  "file_name": "test.jpg"
}
```

期望：

```txt
拒绝导入
HTTP 422 或 403
```

---

## P0-7. Base64 上传应在解码前检查大小

### 问题

如果先完整解码 base64 再检查大小，攻击者可以提交超大 base64 内容造成内存压力。

### 重点文件

```txt
backend/app/service/media/MediaService.php
```

### 修改目标

1. 在 `base64_decode` 前估算解码后大小。
2. 超过限制立即拒绝。
3. 不产生临时文件。

### 参考实现

```php
private function estimateBase64DecodedSize(string $base64): int
{
    $base64 = preg_replace('/\s+/', '', $base64);
    $padding = substr_count(substr($base64, -2), '=');

    return (int) floor(strlen($base64) * 3 / 4) - $padding;
}
```

使用：

```php
$estimatedSize = $this->estimateBase64DecodedSize($content);

if ($estimatedSize > $this->maxAllowedUploadSize()) {
    throw new BusinessException(ErrorCode::FILE_TOO_LARGE, 'File is too large.');
}
```

### 验证

提交超过限制的大 base64：

```txt
期望：
1. 快速返回错误。
2. 不产生临时文件。
3. PHP 进程内存无明显上涨。
```

---

# 3. P1 修复项：尽快处理

---

## P1-1. Authorization Bearer 解析应严格

### 问题

如果只是字符串替换 `Bearer`，格式不标准的 Authorization header 也可能进入 token 校验。

### 重点文件

```txt
backend/app/adminapi/middleware/AuthMiddleware.php
backend/app/service/auth/AuthService.php
```

### 修改目标

统一严格解析 `Bearer <token>`。

### 参考实现

```php
private function parseBearerToken(string $authorization): string
{
    if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
        throw new BusinessException(ErrorCode::UNAUTHORIZED, 'Authentication required.');
    }

    return trim($matches[1]);
}
```

### 验证

```bash
curl -i http://127.0.0.1:8080/admin/auth/profile \
  -H "Authorization: BearerX invalid"
```

期望：

```txt
401
```

---

## P1-2. 登录锁定参数不要硬编码，并避免账号 DoS

### 问题

登录失败锁定如果完全按用户名硬编码阈值，攻击者可以反复对 `admin` 输错密码导致真实管理员被锁。

### 重点文件

```txt
backend/app/service/auth/AuthService.php
backend/config/auth.php
backend/.env.example
```

### 修改目标

1. 登录失败阈值、窗口、锁定时间读取配置。
2. 登录失败统计同时考虑 username + IP。
3. 配合限流可信代理修复，避免伪造 IP 绕过。
4. 错误响应不要透露“用户名存在但密码错误”。

### 建议新增配置

```env
AUTH_LOGIN_MAX_ATTEMPTS=5
AUTH_LOGIN_WINDOW_SECONDS=900
AUTH_LOGIN_LOCK_SECONDS=900
```

### 验证

```txt
1. 连续输错达到阈值后账号或 IP 被锁。
2. 等待锁定时间后恢复。
3. 修改 .env 后不需要改代码即可调整阈值。
4. 伪造 XFF 不应绕过限制。
```

---

## P1-3. 询盘表单不要静默截断字段，修复乱码错误

### 问题

表单字段如果先截断再校验，用户提交超长内容会被静默丢失。部分错误提示存在乱码。

### 重点文件

```txt
backend/app/publicapi/controller/ContentController.php
backend/app/service/inquiry/InquiryService.php
```

### 修改目标

1. 输入可以 `trim`，但不要静默截断核心业务字段。
2. 超长字段返回 422。
3. 修复乱码错误信息。
4. 统一错误语言，可使用英文或中文，但不能乱码。

### 建议规则

```txt
name: 2-80
phone: 5-40
email: 5-120
message: 0-2000
product_interest: 0-200
country_code: 0-10
```

### 参考方法

```php
private function requireMaxLength(string $field, string $value, int $max): void
{
    if (mb_strlen($value) > $max) {
        throw new BusinessException(
            ErrorCode::INVALID_PARAMS,
            sprintf('%s is too long.', $field)
        );
    }
}
```

处理顺序：

```txt
1. trim
2. required 检查
3. max length 检查
4. email / phone 格式检查
5. 保存
```

### 验证

```bash
curl -i -X POST http://127.0.0.1:8080/api/site/lead \
  -H "Content-Type: application/json" \
  -d '{"name":"测试","email":"bad-email","phone":"123","message":"hello"}'
```

期望：

```txt
HTTP 422
message 可读，不乱码
```

提交超长 message：

```txt
期望：
1. 返回 422。
2. 数据库或 JSON 中不保存被截断内容。
```

---

## P1-4. 修复 UTF-8 BOM 和乱码文案

### 问题

部分 PHP 文件可能带 BOM，后台 JSX 中存在乱码文案。

### 重点文件

```txt
backend/app/publicapi/controller/ContentController.php
admin-v2/src/layouts/AdminLayout.jsx
其他 grep 发现的乱码文件
```

### 修改目标

1. 所有业务代码保存为 UTF-8 without BOM。
2. 修复乱码字符串。
3. 不改 class、不改 DOM 层级、不改 CSS。

### 验证 BOM

```bash
grep -RIl $'\xEF\xBB\xBF' backend admin-v2 \
  --exclude-dir=node_modules \
  --exclude-dir=vendor
```

期望无输出。

### 验证乱码

```bash
grep -RInE '璐|閫|鑿|褰|璇|鍐|鈹|�' backend admin-v2 \
  --exclude-dir=node_modules \
  --exclude-dir=vendor
```

期望业务代码无乱码。

---

## P1-5. Malformed JSON 应返回 422，而不是当作空参数

### 问题

JSON 请求体格式错误时，如果被当成空数组，错误提示不准确，也不利于调试。

### 重点文件

```txt
backend/app/common/http/Request.php
```

### 修改目标

1. JSON 格式错误直接抛业务异常。
2. 返回 422。
3. 错误信息明确：`Malformed JSON request body.`

### 参考实现

```php
$decoded = json_decode($rawBody, true);

if ($rawBody !== '' && json_last_error() !== JSON_ERROR_NONE) {
    throw new BusinessException(
        ErrorCode::INVALID_PARAMS,
        'Malformed JSON request body.'
    );
}
```

### 验证

```bash
curl -i -X POST http://127.0.0.1:8080/api/site/lead \
  -H "Content-Type: application/json" \
  -d '{"name":'
```

期望：

```txt
HTTP 422
message: Malformed JSON request body.
```

---

## P1-6. JSON 文件存储应原子写入

### 问题

如果 runtime JSON 存储在并发写入时没有整体锁和原子替换，可能产生半截 JSON 或后写覆盖先写。

### 重点文件

```txt
backend/app/common/storage/JsonFileStore.php
backend/app/common/storage/RuntimeStorage.php
```

### 修改目标

1. 写入时使用独占锁。
2. 推荐写临时文件后 `rename` 原子替换。
3. read-modify-write 整个过程尽量锁住。

### 参考实现

```php
$lockPath = $path . '.lock';
$lock = fopen($lockPath, 'c');

if (!$lock || !flock($lock, LOCK_EX)) {
    throw new RuntimeException('Unable to lock storage file.');
}

try {
    $tmp = $path . '.tmp.' . getmypid();
    file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    rename($tmp, $path);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
```

### 验证

并发创建或更新内容 20 次：

```txt
期望：
1. JSON 文件始终可被 json_decode。
2. 不丢记录。
3. 不出现半截 JSON。
```

---

# 4. P2 中期优化项

---

## P2-1. 数据库连接失败不要在生产环境静默降级

### 问题

数据库连接失败如果直接返回 null 并让业务走 runtime JSON fallback，线上数据库故障可能被隐藏。

### 重点文件

```txt
backend/app/common/database/DatabaseManager.php
backend/.env.example
```

### 修改目标

1. 增加配置：是否允许 runtime fallback。
2. 生产环境默认不允许静默降级。
3. 数据库异常至少写日志。
4. 评估是否关闭 PDO persistent。

### 建议配置

```env
APP_ALLOW_RUNTIME_FALLBACK=false
DB_PERSISTENT=false
```

### 验证

```txt
1. 本地开发允许 fallback 时，数据库不可用仍可启动。
2. 生产配置禁止 fallback 时，数据库不可用应明确报错。
3. 日志记录数据库连接失败原因。
```

---

## P2-2. Validator required 应 trim

### 问题

`"   "` 这种空白字符串可能通过 required 校验。

### 重点文件

```txt
backend/app/common/validation/Validator.php
```

### 修改目标

1. required 判断前 trim。
2. min/max 对字符串也建议基于 trim 后值。

### 参考实现

```php
if (is_string($value)) {
    $value = trim($value);
}
```

### 验证

提交空白 name：

```bash
curl -i -X POST http://127.0.0.1:8080/api/site/lead \
  -H "Content-Type: application/json" \
  -d '{"name":"   ","email":"test@example.com","phone":"123456"}'
```

期望：

```txt
HTTP 422
```

---

## P2-3. 后台 token 存 localStorage 的风险说明与短期缓解

### 问题

后台 token 如果存储在 localStorage，一旦后台发生 XSS，access token 和 refresh token 都可能被读取。

### 重点文件

```txt
admin-v2/src/utils/auth.js
backend/config/upload.php
富文本输出相关文件
```

### 本次建议

短期先做低风险改动：

```txt
1. 默认禁用 SVG 上传。
2. 后台所有标题、文件名、富文本渲染点确认转义。
3. 缩短 refresh token 生命周期。
4. 不在本轮强行重构为 HttpOnly Cookie，避免大改鉴权机制。
```

中期再考虑：

```txt
1. refresh token 改为 HttpOnly + SameSite Cookie。
2. access token 只存内存。
3. refresh 接口增加 CSRF 防护。
```

---

## P2-4. 后台 RBAC 权限映射补测试

### 问题

前端权限靠路由映射控制体验。如果新增页面忘记配置权限，可能出现后台菜单或路由访问控制不一致。

### 重点文件

```txt
admin-v2/src/utils/rbac.js
admin-v2/src/components/AuthGuard.jsx
admin-v2/src/layouts/AdminLayout.jsx
```

### 修改目标

1. 不改 UI。
2. 给 `routePermissionMap` / `canAccessPath()` 补单元测试或简单检查脚本。
3. 确认系统设置和个人设置权限区分。
4. 后端权限仍作为最终权限边界。

### 验证

```txt
1. 无权限用户访问受限路由时不可访问。
2. 有权限用户可访问。
3. /settings 如果包含系统配置，必须由后端 API 再次鉴权。
```

---

## P2-5. RequestContext 增加 clear，防止长驻进程串状态

### 问题

静态 RequestContext 在 PHP-FPM 下通常问题不大，但如果未来接入 Swoole / RoadRunner / Workerman，可能发生请求上下文串号。

### 重点文件

```txt
backend/app/common/http/RequestContext.php
backend/app/common/bootstrap/Application.php
```

### 修改目标

增加：

```php
public static function clear(): void
{
    self::$request = null;
    self::$user = null;
}
```

在应用入口 finally 中调用：

```php
try {
    // dispatch request
} finally {
    RequestContext::clear();
}
```

---

# 5. 后台前端 admin-v2 检查要求

## 5.1 不改样式，只改逻辑或文案 bug

允许：

```txt
1. 修复乱码文案。
2. 修复 API 错误处理。
3. 修复 token 过期处理。
4. 修复权限判断边界。
5. 修复重复提交。
6. 增加无视觉影响的 aria 属性。
```

禁止：

```txt
1. 改 CSS。
2. 改 className 用法导致布局变化。
3. 重排页面。
4. 换组件库。
5. 调整颜色、间距、字体、按钮样式。
```

## 5.2 admin-v2 API client 验证

重点文件：

```txt
admin-v2/src/api/client.js
admin-v2/src/api/auth.js
admin-v2/src/utils/auth.js
```

检查：

```txt
1. 401 只触发一次登录过期处理。
2. refresh token 失败后能清理登录态。
3. 网络断开时不会白屏。
4. 429 限流响应有明确错误。
5. 500 错误不暴露后端堆栈。
6. API 请求超时有明确提示。
```

---

# 6. 完整验证流程

## 6.1 安装依赖

根目录：

```bash
npm install
```

后台：

```bash
npm --prefix admin-v2 ci
```

后端：

```bash
cd backend
composer install --no-interaction --no-progress --prefer-dist
cd ..
```

## 6.2 构建与静态检查

后台构建：

```bash
npm --prefix admin-v2 run build
```

后端校验：

```bash
cd backend
composer validate --strict
find app config route public -name "*.php" -print0 | xargs -0 -n1 php -l
cd ..
php -l router.php
```

根目录总检查，如果 `package.json` 有 `check`：

```bash
npm run check
```

## 6.3 CSS 未修改检查

```bash
git diff --name-only -- "*.css" "assets/css/**" "admin-v2/src/**/*.css" "admin-v2/src/**/*.scss" "admin-v2/src/**/*.less"
```

期望无输出。

```bash
git diff --stat
```

确认没有 CSS 文件。

## 6.4 本地启动

```bash
php -S 127.0.0.1:8080 router.php
```

## 6.5 公开资源访问验证

```bash
curl -I http://127.0.0.1:8080/zh/index.html
curl -I http://127.0.0.1:8080/en/index.html
curl -I http://127.0.0.1:8080/assets/js/site.js
curl -I http://127.0.0.1:8080/assets/css/site.css
```

期望：

```txt
200
200
200
200
```

## 6.6 敏感路径拦截验证

```bash
curl -I http://127.0.0.1:8080/backend/.env.production
curl -I http://127.0.0.1:8080/hanzun_cms.sql
curl -I http://127.0.0.1:8080/admin-v2/package.json
curl -I http://127.0.0.1:8080/backend/composer.json
curl -I http://127.0.0.1:8080/.git/config
curl -I http://127.0.0.1:8080/uploads/test.php
curl -I http://127.0.0.1:8080/uploads/test.svg
```

期望全部：

```txt
404
```

## 6.7 HTTP 状态码验证

未登录后台接口：

```bash
curl -i http://127.0.0.1:8080/admin/auth/profile
```

期望：

```txt
401
```

JSON 格式错误：

```bash
curl -i -X POST http://127.0.0.1:8080/api/site/lead \
  -H "Content-Type: application/json" \
  -d '{"name":'
```

期望：

```txt
422
```

询盘参数错误：

```bash
curl -i -X POST http://127.0.0.1:8080/api/site/lead \
  -H "Content-Type: application/json" \
  -d '{"name":"","email":"bad"}'
```

期望：

```txt
422
```

## 6.8 限流验证

```bash
for i in {1..7}; do
  curl -s -o /dev/null -w "%{http_code}\n" \
    -X POST http://127.0.0.1:8080/admin/auth/login \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"wrong"}'
done
```

期望后几次：

```txt
429
```

伪造 XFF：

```bash
for i in {1..7}; do
  curl -s -o /dev/null -w "%{http_code}\n" \
    -X POST http://127.0.0.1:8080/admin/auth/login \
    -H "X-Forwarded-For: 8.8.8.$i" \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"wrong"}'
done
```

如果未配置可信代理，伪造 XFF 不应绕过限流。

## 6.9 编码检查

BOM：

```bash
grep -RIl $'\xEF\xBB\xBF' backend admin-v2 \
  --exclude-dir=node_modules \
  --exclude-dir=vendor
```

乱码：

```bash
grep -RInE '璐|閫|鑿|褰|璇|鍐|鈹|�' backend admin-v2 \
  --exclude-dir=node_modules \
  --exclude-dir=vendor
```

期望业务代码无输出。

## 6.10 上传验证

SVG 拒绝：

```bash
curl -i -F "file=@test.svg" http://127.0.0.1:8080/admin/media/upload \
  -H "Authorization: Bearer 管理员access_token"
```

期望：

```txt
422
```

普通图片上传 + 删除：

```txt
1. 上传 jpg/png/webp。
2. 后台删除。
3. 确认数据库/JSON 记录删除。
4. 确认物理文件删除。
```

---

# 7. 建议提交拆分

建议不要一个大提交全部改完，便于复查。

```txt
commit 1: fix(backend): map business exceptions to http status codes
commit 2: fix(security): harden rate limit client ip resolving
commit 3: fix(auth): make refresh token rotation atomic
commit 4: fix(media): harden upload validation and disk path resolving
commit 5: fix(inquiry): validate lead inputs without silent truncation
commit 6: fix(admin): repair garbled text without style changes
commit 7: test(ci): add or update checks for backend and admin
```

如果由 AI 自动修改，也请尽量按模块分批提交。

---

# 8. 修改完成后需要回复给复查者的信息

请在推送后回复以下内容：

```txt
1. 分支名：
2. 最新 commit hash：
3. 是否有 CSS diff：无 / 有，说明原因
4. GitHub Actions 链接：
5. 本地执行过的命令：
   - npm --prefix admin-v2 run build
   - composer validate --strict
   - php -l
   - curl 安全路径测试
6. 已修复的 P0 项：
7. 未修复的项及原因：
8. 需要人工确认的风险：
```

---

# 9. 给执行 AI 的直接提示词

可以把下面这段原样交给执行 AI：

```txt
请按 hanzun_backend_admin_review_fix_plan.md 对 Hanzun 项目进行后台和后端代码修复。必须严格遵守：不修改任何 CSS，不改变前台或后台页面样式，不改颜色、字体、间距、圆角、阴影、布局、图片、视觉 DOM 结构。

优先修复 P0：
1. Application.php：BusinessException 映射正确 HTTP 状态码。
2. RateLimitMiddleware.php：只在可信代理下信任 X-Forwarded-For，限流存储失败不要静默放行关键接口。
3. SessionService.php：refresh token 原子轮换，并发刷新只允许一次成功。
4. MediaService.php：修复物理文件删除路径、禁止路径穿越。
5. upload.php / MediaService.php：默认禁用 SVG 上传。
6. MediaService.php：限制 source_path 导入范围。
7. MediaService.php：base64 上传解码前估算大小。

随后修复 P1：
1. 严格解析 Authorization Bearer。
2. 登录锁定参数配置化，并降低账号 DoS 风险。
3. 询盘表单不要静默截断，修乱码错误提示。
4. 去除 UTF-8 BOM，修复后台乱码文案。
5. malformed JSON 返回 422。
6. JSON 文件存储原子写入。

完成后必须执行验证：
1. git diff 中没有 CSS 文件。
2. npm --prefix admin-v2 run build 通过。
3. composer validate --strict 通过。
4. PHP lint 通过。
5. 敏感路径 curl 测试返回 404。
6. 未登录后台接口返回 401。
7. 参数错误返回 422。
8. 限流测试出现 429。
9. SVG 上传被拒绝。

请按模块分批提交，并推送到 GitHub。推送后提供最新 commit hash 和 GitHub Actions 链接。
```

---

# 10. 复查重点

复查时重点看：

```txt
1. 是否真的没有 CSS 改动。
2. HTTP status 是否从业务码正确映射。
3. 限流是否仍可被 XFF 绕过。
4. refresh token 并发刷新是否只有一次成功。
5. SVG 是否已经默认拒绝上传。
6. source_path 是否还允许 base_path。
7. 询盘超长字段是否还会静默截断。
8. 是否修复 BOM 和乱码。
9. malformed JSON 是否返回 422。
10. JSON 存储写入是否有锁或原子替换。
11. GitHub Actions 是否通过。
```
