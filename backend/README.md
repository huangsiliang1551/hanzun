# Hanzun CMS Backend

This directory contains the backend API and local runtime tooling for the Hanzun CMS admin system.

## Current state

- PHP runtime is installed
- Composer dependencies are installed
- Local MySQL 8.4 bootstrap is available
- `hanzun_cms.sql` can be imported successfully
- Local smoke checks pass against auth, dashboard, content, and inquiry APIs

## Quick start

From the workspace root:

```powershell
powershell -ExecutionPolicy Bypass -File .\backend\tools\bootstrap-local.ps1 -ReimportSchema
```

This will:

1. Start or initialize local MySQL on `127.0.0.1:3306`
2. Import `hanzun_cms.sql`
3. Start the backend on `http://127.0.0.1:8080`
4. Run smoke tests

Local bootstrap resolves PHP/MySQL runtimes in this order:

1. Explicit parameters such as `-PhpExe`, `-MysqlExe`, `-MysqldExe`
2. Environment variables `HANZUN_PHP_EXE`, `HANZUN_MYSQL_EXE`, `HANZUN_MYSQLD_EXE`
3. Executables available on `PATH`
4. The previous built-in Windows fallback paths

Example with explicit overrides:

```powershell
powershell -ExecutionPolicy Bypass -File .\backend\tools\bootstrap-local.ps1 -PhpExe "D:\php\php.exe" -MysqlExe "D:\mysql\bin\mysql.exe" -MysqldExe "D:\mysql\bin\mysqld.exe" -ReimportSchema
```

## Full verification

From the workspace root, run the full serial verification flow:

```powershell
powershell -ExecutionPolicy Bypass -File .\verify-project.ps1
```

This runs:

1. `backend/tests/*.js`
2. `tests/*.js`

The script intentionally runs these phases serially. Some runtime tests temporarily rewrite `backend/runtime/storage`, and parallel execution can produce false failures.
It also calls `backend/tools/reset-runtime-state.ps1` between mutable phases so runtime JSON state does not leak into the next verification stage.

## Main scripts

- `backend/tools/bootstrap-local.ps1`
- `backend/tools/start-local-mysql.ps1`
- `backend/tools/import-schema.ps1`
- `backend/tools/start-local-backend.ps1`
- `backend/tools/smoke-test.ps1`
- `verify-project.ps1`

## Default admin account

- Username: `admin`
- Password: `admin123456`

## Verification

Static route validation:

```bash
node backend/tests/validate-routes.js
```

Runtime smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\backend\tools\smoke-test.ps1
```

Public site localization checks:

```bash
curl "http://127.0.0.1:8080/api/site/bootstrap?lang=en"
curl "http://127.0.0.1:8080/api/site/products/cake-depositor?lang=en"
```

The public site endpoints support two language resolution inputs:

1. Query parameter `lang`, for example `?lang=en`
2. HTTP header `Accept-Language`, for example `Accept-Language: en-US,en;q=0.9`

Resolution falls back in this order:

1. Explicit `lang`
2. The first enabled language found in `Accept-Language`
3. The default enabled language in `languages`

Translated text is exposed through language-neutral fields such as `name`, `title`, `summary`, and `content`, while the original Chinese source fields remain available as `*_zh`.

Expected smoke-test result:

```json
{
  "health_code": 0,
  "health_status": "ok",
  "profile_ok": true,
  "products_ok": true,
  "solutions_ok": true,
  "articles_ok": true,
  "inquiries_ok": true,
  "inquiry_detail_ok": true,
  "jobs_ok": true
}
```

## Local runtime paths

- MySQL runtime: `C:\hanzun-cms-runtime\mysql`
- Public entry: `backend/public`
- Schema file: `hanzun_cms.sql`

See `backend/docs-setup.md` for the detailed local setup notes.
