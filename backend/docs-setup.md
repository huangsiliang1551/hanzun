# Backend Setup Notes

## 1. Required runtime

- PHP 8.1+
- Composer 2.x
- MySQL 8.0+

## 2. Current local bootstrap flow

The workspace now includes repeatable local scripts under `backend/tools`:

- `bootstrap-local.ps1`
- `start-local-mysql.ps1`
- `import-schema.ps1`
- `start-local-backend.ps1`
- `smoke-test.ps1`

Local MySQL runtime is created at:

- `C:\hanzun-cms-runtime\mysql`

This avoids Windows path/encoding issues from running MySQL directly inside the workspace path.

## 3. One-command local bootstrap

From the workspace root:

```powershell
powershell -ExecutionPolicy Bypass -File .\backend\tools\bootstrap-local.ps1 -ReimportSchema
```

What it does:

1. Starts or initializes a local MySQL 8.4 instance on `127.0.0.1:3306`
2. Imports `hanzun_cms.sql`
3. Starts the PHP backend on `http://127.0.0.1:8080`
4. Runs smoke checks for health, auth, products, solutions, articles, inquiries, inquiry detail, and dashboard jobs

Local bootstrap resolves runtimes in this order:

1. Explicit parameters such as `-PhpExe`, `-MysqlExe`, `-MysqldExe`
2. Environment variables `HANZUN_PHP_EXE`, `HANZUN_MYSQL_EXE`, `HANZUN_MYSQLD_EXE`
3. Executables available on `PATH`
4. Built-in Windows fallback paths kept for compatibility

Example:

```powershell
powershell -ExecutionPolicy Bypass -File .\backend\tools\bootstrap-local.ps1 -PhpExe "D:\php\php.exe" -MysqlExe "D:\mysql\bin\mysql.exe" -MysqldExe "D:\mysql\bin\mysqld.exe" -ReimportSchema
```

## 4. Individual commands

Start MySQL only:

```powershell
powershell -ExecutionPolicy Bypass -File .\backend\tools\start-local-mysql.ps1
```

Re-import schema only:

```powershell
powershell -ExecutionPolicy Bypass -File .\backend\tools\import-schema.ps1
```

Start backend only:

```powershell
powershell -ExecutionPolicy Bypass -File .\backend\tools\start-local-backend.ps1
```

Run smoke checks only:

```powershell
powershell -ExecutionPolicy Bypass -File .\backend\tools\smoke-test.ps1
```

Full serial verification from the workspace root:

```powershell
powershell -ExecutionPolicy Bypass -File .\verify-project.ps1
```

This runs backend runtime tests and frontend/admin validation tests in a fixed serial order.


## 6. Default admin account

- Username: `admin`
- Password: `admin123456`

## 7. Expected healthy result

`smoke-test.ps1` should return JSON similar to:

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

## 8. Extra verification

Static route verification:

```bash
node backend/tests/validate-routes.js
```
