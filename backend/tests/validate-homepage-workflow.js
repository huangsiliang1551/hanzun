const fs = require('fs');
const path = require('path');

const workspaceRoot = path.resolve(__dirname, '..');
const routeFile = path.join(workspaceRoot, 'route', 'adminapi.php');
const controllerFile = path.join(workspaceRoot, 'app', 'adminapi', 'controller', 'homepage', 'HomepageController.php');
const serviceFile = path.join(workspaceRoot, 'app', 'service', 'homepage', 'HomepageService.php');
const repositoryFile = path.join(workspaceRoot, 'app', 'repository', 'HomepageRepository.php');

function read(filePath) {
  return fs.readFileSync(filePath, 'utf8');
}

function assert(condition, message, issues) {
  if (!condition) {
    issues.push(message);
  }
}

function main() {
  const issues = [];
  const routeContent = read(routeFile);
  const controllerContent = read(controllerFile);
  const serviceContent = read(serviceFile);
  const repositoryContent = read(repositoryFile);

  assert(routeContent.includes("['GET', '/admin/homepage/workflow'"), '缺少 GET /admin/homepage/workflow 路由', issues);
  assert(routeContent.includes("['POST', '/admin/homepage/sections'"), '缺少 POST /admin/homepage/sections 路由', issues);
  assert(routeContent.includes("['PUT', '/admin/homepage/sections/sort'"), '缺少 PUT /admin/homepage/sections/sort 路由', issues);
  assert(routeContent.includes("['PATCH', '/admin/homepage/sections/{id}/status'"), '缺少 PATCH /admin/homepage/sections/{id}/status 路由', issues);
  assert(routeContent.includes("['GET', '/admin/homepage/sections/{id}/items'"), '缺少 GET /admin/homepage/sections/{id}/items 路由', issues);
  assert(routeContent.includes("['PUT', '/admin/homepage/sections/{id}/items'"), '缺少 PUT /admin/homepage/sections/{id}/items 路由', issues);
  assert(routeContent.includes("['PATCH', '/admin/homepage/featured-items/{source_type}/{id}'"), '缺少 PATCH /admin/homepage/featured-items/{source_type}/{id} 路由', issues);
  assert(routeContent.includes("['POST', '/admin/homepage/publish'"), '缺少 POST /admin/homepage/publish 路由', issues);
  assert(routeContent.includes("['POST', '/admin/homepage/restore-live'"), '缺少 POST /admin/homepage/restore-live 路由', issues);

  assert(/function\s+store\s*\(/.test(controllerContent), 'HomepageController 缺少 store 方法', issues);
  assert(/function\s+sort\s*\(/.test(controllerContent), 'HomepageController 缺少 sort 方法', issues);
  assert(/function\s+updateStatus\s*\(/.test(controllerContent), 'HomepageController 缺少 updateStatus 方法', issues);
  assert(/function\s+items\s*\(/.test(controllerContent), 'HomepageController 缺少 items 方法', issues);
  assert(/function\s+updateItems\s*\(/.test(controllerContent), 'HomepageController 缺少 updateItems 方法', issues);
  assert(/function\s+workflow\s*\(/.test(controllerContent), 'HomepageController 缺少 workflow 方法', issues);
  assert(/function\s+updateFeaturedItem\s*\(/.test(controllerContent), 'HomepageController 缺少 updateFeaturedItem 方法', issues);
  assert(/function\s+publish\s*\(/.test(controllerContent), 'HomepageController 缺少 publish 方法', issues);
  assert(/function\s+restoreLive\s*\(/.test(controllerContent), 'HomepageController 缺少 restoreLive 方法', issues);
  assert(controllerContent.includes("'section_key' => $request->input('section_key')"), 'HomepageController 缺少 section_key 读取', issues);
  assert(controllerContent.includes("'section_type' => $request->input('section_type')"), 'HomepageController 缺少 section_type 读取', issues);
  assert(controllerContent.includes("(array) $request->input('sections', [])"), 'HomepageController 缺少排序 sections 读取', issues);
  assert(controllerContent.includes("(array) $request->input('items', [])"), 'HomepageController 缺少 items 读取', issues);
  assert(controllerContent.includes("(int) $request->input('is_enabled')"), 'HomepageController 缺少状态 is_enabled 读取', issues);
  assert(controllerContent.includes("(string) $request->routeParam('source_type')"), 'HomepageController 缺少 source_type 路径参数读取', issues);
  assert(controllerContent.includes("(int) $request->routeParam('id')"), 'HomepageController 缺少 id 路径参数读取', issues);
  assert(controllerContent.includes("'is_home_featured' => $request->input('is_home_featured')"), 'HomepageController 缺少 is_home_featured 读取', issues);
  assert(controllerContent.includes("'manual_sort' => $request->input('manual_sort')"), 'HomepageController 缺少 manual_sort 读取', issues);

  assert(/function\s+createSection\s*\(/.test(serviceContent), 'HomepageService 缺少 createSection 方法', issues);
  assert(/function\s+sortSections\s*\(/.test(serviceContent), 'HomepageService 缺少 sortSections 方法', issues);
  assert(/function\s+updateSectionStatus\s*\(/.test(serviceContent), 'HomepageService 缺少 updateSectionStatus 方法', issues);
  assert(/function\s+sectionItems\s*\(/.test(serviceContent), 'HomepageService 缺少 sectionItems 方法', issues);
  assert(/function\s+updateSectionItems\s*\(/.test(serviceContent), 'HomepageService 缺少 updateSectionItems 方法', issues);
  assert(/function\s+workflow\s*\(/.test(serviceContent), 'HomepageService 缺少 workflow 方法', issues);
  assert(/function\s+publish\s*\(/.test(serviceContent), 'HomepageService 缺少 publish 方法', issues);
  assert(/function\s+restoreLive\s*\(/.test(serviceContent), 'HomepageService 缺少 restoreLive 方法', issues);
  assert(serviceContent.includes('draft_updated_at'), 'HomepageService 返回结构缺少 draft_updated_at', issues);
  assert(serviceContent.includes('live_updated_at'), 'HomepageService 返回结构缺少 live_updated_at', issues);
  assert(serviceContent.includes('has_unpublished_changes'), 'HomepageService 返回结构缺少 has_unpublished_changes', issues);

  assert(/function\s+create\s*\(/.test(repositoryContent), 'HomepageRepository 缺少 create 方法', issues);
  assert(/function\s+updateSorts\s*\(/.test(repositoryContent), 'HomepageRepository 缺少 updateSorts 方法', issues);
  assert(/function\s+updateStatus\s*\(/.test(repositoryContent), 'HomepageRepository 缺少 updateStatus 方法', issues);
  assert(/function\s+listItems\s*\(/.test(repositoryContent), 'HomepageRepository 缺少 listItems 方法', issues);
  assert(/function\s+replaceItems\s*\(/.test(repositoryContent), 'HomepageRepository 缺少 replaceItems 方法', issues);
  assert(/function\s+findSectionItem\s*\(/.test(repositoryContent), 'HomepageRepository 缺少 findSectionItem 方法', issues);
  assert(repositoryContent.includes('published_snapshot'), 'HomepageRepository 缺少 published_snapshot 持久化约定', issues);
  assert(repositoryContent.includes('publish_meta'), 'HomepageRepository 缺少 publish_meta 持久化约定', issues);

  if (issues.length > 0) {
    console.error('Homepage workflow validation failed:');
    issues.forEach((issue) => console.error('- ' + issue));
    process.exit(1);
  }

  console.log('Homepage workflow validation passed.');
}

main();
