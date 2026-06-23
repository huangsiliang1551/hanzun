# 项目迁移交接文档

## 1. 项目根目录

`C:\Users\ZhuanZ1\my-project\涵尊实业有限公司`

这是需要整体迁移到新电脑的项目目录。

## 2. 当前工作重点

当前重点是前台公开站，不是后台。

目标：

- 前台全部生成静态 HTML
- 多语言目录输出：`/zh/...`、`/en/...`
- 根目录 `/index.html` 负责首访语言跳转
- 所有公开页统一头部、底部、悬浮联系方式
- 当前只做本地，不同步服务器

必须遵守：

1. 修改前台源码后，必须重新全量生成静态页
2. 不能只改源码不验证生成结果
3. 验证对象必须是最终生成的 HTML 页面

## 3. 当前已完成状态

前台静态站主链路已本地跑通。

已完成：

- 静态生成器可正常执行
- 已生成根入口、`zh/en` 页面、`sitemap.xml`、`robots.txt`
- 已统一公开站公共壳层
- 已统一“联系”入口到 `contact.html`
- 已清理页脚一组重复栏目
- 已抽查中英文页面，无明显串语言
- 已检查资源引用和内部链接，无缺失引用

## 4. 必须保留的关键目录和文件

整目录复制最稳，至少保留以下内容：

### 前台和生成相关

- `backend/`
- `assets/`
- `uploads/`
- `zh/`
- `en/`
- `index.html`
- `sitemap.xml`
- `robots.txt`
- `.tmp-render-full.php`

### 后台相关

- `admin-v2/`
- `admin-app/`

### 配置和脚本

- `backend/.env`
- `backend/.env.example`
- `backend/.env.production`
- `verify-project.ps1`
- `router.php`
- `hanzun_cms.sql`

### 报告与上下文

- `admin-v2-上线前评估报告.html`
- `admin-v2-功能BUG与设计问题报告.html`

## 5. 关键源码文件

新会话优先读取这些文件：

- [backend/app/service/StaticPublisher.php](C:/Users/ZhuanZ1/my-project/涵尊实业有限公司/backend/app/service/StaticPublisher.php)
- [backend/app/service/content/PublicSiteService.php](C:/Users/ZhuanZ1/my-project/涵尊实业有限公司/backend/app/service/content/PublicSiteService.php)
- [backend/templates/index.template.html](C:/Users/ZhuanZ1/my-project/涵尊实业有限公司/backend/templates/index.template.html)
- [assets/css/future.css](C:/Users/ZhuanZ1/my-project/涵尊实业有限公司/assets/css/future.css)
- [assets/css/future-mobile.css](C:/Users/ZhuanZ1/my-project/涵尊实业有限公司/assets/css/future-mobile.css)
- [assets/js/future.js](C:/Users/ZhuanZ1/my-project/涵尊实业有限公司/assets/js/future.js)

后台源码：

- [admin-v2](C:/Users/ZhuanZ1/my-project/涵尊实业有限公司/admin-v2)

## 6. 新电脑所需环境

建议安装：

- PHP `8.1+`
- Composer `2.x`
- Node.js `18+`
- npm
- MySQL `8.x`

已确认依赖：

- `backend/composer.json`
  - `php: ^8.1`
  - `topthink/framework: ^8.0`
- 根目录 `package.json`
  - `live-server`
- `admin-v2/package.json`
  - `react`
  - `react-dom`
  - `react-router-dom`
  - `antd`
  - `@ant-design/icons`
  - `suneditor`
  - `suneditor-react`
  - `vite`

## 7. 环境文件说明

这些文件不要丢：

- `backend/.env`
- `admin-v2/.env.local`
- `admin-v2/.env.development.local`

说明：

- `backend/.env` 很关键，直接原样复制
- `admin-v2` 下两个 `.env` 也建议原样复制

## 8. 数据库说明

数据库有两种方式：

### 方式 A：沿用现有 `backend/.env`

如果 `backend/.env` 已配置可用数据库，直接复制后使用。

### 方式 B：新电脑本地重建数据库

项目内已有本地脚本：

- `backend/tools/bootstrap-local.ps1`
- `backend/tools/start-local-mysql.ps1`
- `backend/tools/import-schema.ps1`
- `backend/tools/start-local-backend.ps1`
- `backend/tools/smoke-test.ps1`

数据库 SQL 文件：

- [hanzun_cms.sql](C:/Users/ZhuanZ1/my-project/涵尊实业有限公司/hanzun_cms.sql)

## 9. 后端本地启动方式

推荐一键执行：

```powershell
powershell -ExecutionPolicy Bypass -File .\backend\tools\bootstrap-local.ps1 -ReimportSchema
```

作用：

1. 初始化或启动本地 MySQL
2. 导入 `hanzun_cms.sql`
3. 启动后端
4. 跑 smoke test

后端地址：

- `http://127.0.0.1:8080`

如果 PHP / MySQL 不在 PATH，可指定：

```powershell
powershell -ExecutionPolicy Bypass -File .\backend\tools\bootstrap-local.ps1 -PhpExe "D:\php\php.exe" -MysqlExe "D:\mysql\bin\mysql.exe" -MysqldExe "D:\mysql\bin\mysqld.exe" -ReimportSchema
```

## 10. 前台本地预览方式

```powershell
php -S 127.0.0.1:8091 -t C:\Users\ZhuanZ1\my-project\涵尊实业有限公司
```

常用预览地址：

- `http://127.0.0.1:8091/`
- `http://127.0.0.1:8091/zh/index.html`
- `http://127.0.0.1:8091/en/index.html`

## 11. 前台静态页生成方式

修改前台后，必须执行：

```powershell
php .tmp-render-full.php
```

## 12. 前台必须执行的验证命令

每次继续前台开发前，先执行：

```powershell
php -l backend/app/service/StaticPublisher.php
php .tmp-render-full.php
php backend/tests/validate-site-build-output-runtime.php
```

## 13. 已验证页面

以下页面已经实测访问过，可作为回归基线：

- `http://127.0.0.1:8091/`
- `http://127.0.0.1:8091/zh/index.html`
- `http://127.0.0.1:8091/en/index.html`
- `http://127.0.0.1:8091/zh/contact.html`
- `http://127.0.0.1:8091/en/contact.html`
- `http://127.0.0.1:8091/zh/solutions/cake-line.html`
- `http://127.0.0.1:8091/en/news/germany-bakery-expo.html`
- `http://127.0.0.1:8091/zh/cases/uae-cake-project.html`

## 14. 后台目录关系

- `admin-v2/` 是后台源码
- `admin-app/` 是构建输出目录

`admin-v2` 已确认信息：

- dev 端口：`5174`
- Vite base：`/admin-app/`
- build 输出：`../admin-app`
- 默认代理目标：`http://127.0.0.1:8080`

如果要继续后台：

```powershell
cd admin-v2
npm install
npm run dev
```

重新构建后台：

```powershell
cd admin-v2
npm run build
```

## 15. 新电脑推荐接手顺序

建议严格按以下顺序执行：

1. 复制整个项目目录
2. 检查这些文件是否存在：
   - `backend/.env`
   - `admin-v2/.env.local`
   - `admin-v2/.env.development.local`
   - `hanzun_cms.sql`
   - `uploads/`
   - `assets/`
   - `backend/`
   - `zh/`
   - `en/`
3. 安装运行环境：
   - PHP
   - Composer
   - Node
   - npm
   - MySQL
4. 如有需要安装依赖：
   - `backend` 下执行 `composer install`
   - `admin-v2` 下执行 `npm install`
5. 启动后端：
   - `powershell -ExecutionPolicy Bypass -File .\backend\tools\bootstrap-local.ps1 -ReimportSchema`
6. 重新验证前台静态生成：
   - `php -l backend/app/service/StaticPublisher.php`
   - `php .tmp-render-full.php`
   - `php backend/tests/validate-site-build-output-runtime.php`
7. 启动前台预览：
   - `php -S 127.0.0.1:8091 -t C:\Users\ZhuanZ1\my-project\涵尊实业有限公司`
8. 浏览器检查关键页面：
   - `/`
   - `/zh/index.html`
   - `/en/index.html`
   - `/zh/contact.html`
   - `/en/contact.html`

## 16. 当前不要做的事情

- 不要先同步服务器
- 不要只改模板源码不重建
- 不要只看源码或接口结果，必须验证最终生成 HTML
- 当前重点是前台公开站，不要优先切回后台

## 17. 当前建议下一步

迁移完成后，建议先做这三件事：

1. 确认本地环境都能跑起来
2. 重新执行前台静态站全量生成与验证
3. 继续做前台页面模板与最终生成页的上线审查

## 18. 新会话建议直接读取本文件

新会话第一条可直接这样说：

```text
请先读取项目根目录下的 MIGRATION-HANDOFF.md，然后按文档步骤接手项目。
当前重点是前台公开站，只在本地继续完善，不同步服务器。
每次修改前台源码后，必须重新生成并验证最终 HTML 页面。
```
