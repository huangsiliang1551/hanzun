const fs = require('fs');
const path = require('path');

const backendRoot = path.resolve(__dirname, '..');

function read(file) {
  return fs.readFileSync(path.join(backendRoot, file), 'utf8');
}

function expect(content, pattern, label, issues) {
  if (!pattern.test(content)) {
    issues.push(label);
  }
}

function main() {
  const issues = [];

  const routes = read('route/adminapi.php');
  expect(
    routes,
    /\['PUT',\s*'\/admin\/settings\/account',\s*'app\\\\adminapi\\\\controller\\\\system\\\\SettingController@updateAccount'/,
    '缺少账号设置更新路由',
    issues
  );

  const settingController = read('app/adminapi/controller/system/SettingController.php');
  expect(settingController, /function\s+updateAccount\s*\(/, 'SettingController 缺少 updateAccount()', issues);

  const settingService = read('app/service/system/SettingService.php');
  expect(settingService, /function\s+updateAccountProfile\s*\(/, 'SettingService 缺少 updateAccountProfile()', issues);
  expect(settingService, /validateEmail\(/, 'SettingService 缺少 email 校验', issues);
  expect(settingService, /validateMobile\(/, 'SettingService 缺少 mobile 校验', issues);
  expect(settingService, /validatePassword\(/, 'SettingService 缺少 password 校验', issues);
  expect(settingService, /adminUserRepository->update\(/, 'SettingService 未调用 AdminUserRepository::update()', issues);

  const adminUserRepository = read('app/repository/AdminUserRepository.php');
  expect(adminUserRepository, /rolesForUser\(/, 'AdminUserRepository 缺少 rolesForUser()', issues);

  const dashboardService = read('app/service/dashboard/DashboardService.php');
  expect(dashboardService, /'seo_route_count'/, 'Dashboard jobs/health 缺少 seo_route_count', issues);
  expect(dashboardService, /'seo_404_count'/, 'Dashboard jobs/health 缺少 seo_404_count', issues);

  const seoRepository = read('app/repository/SeoRepository.php');
  expect(seoRepository, /function\s+count404Logs\s*\(/, 'SeoRepository 缺少 count404Logs()', issues);

  if (issues.length > 0) {
    console.error('Phase2 backend validation failed:');
    issues.forEach((issue) => console.error(`- ${issue}`));
    process.exit(1);
  }

  console.log('Phase2 backend validation passed.');
}

main();
