const fs = require('fs');
const path = require('path');

const workspaceRoot = path.resolve(__dirname, '..');
const routeFile = path.join(workspaceRoot, 'route', 'adminapi.php');

function readFile(filePath) {
  return fs.readFileSync(filePath, 'utf8');
}

function parseRoutes(content) {
  const routePattern = /\[\s*'([A-Z]+)'\s*,\s*'([^']+)'\s*,\s*'([^']+)'\s*(?:,\s*(null|'([^']+)'))?\s*\]/g;
  const routes = [];
  let match;
  while ((match = routePattern.exec(content)) !== null) {
    routes.push({
      method: match[1],
      path: match[2],
      handler: match[3],
      permission: match[5] || null,
    });
  }
  return routes;
}

function handlerToFile(handler) {
  const [className, methodName] = handler.split('@');
  const relativeClassPath = className.replace(/^app\\\\/, '').replace(/\\\\/g, path.sep) + '.php';
  return {
    methodName,
    filePath: path.join(workspaceRoot, 'app', relativeClassPath),
  };
}

function methodExists(fileContent, methodName) {
  const pattern = new RegExp(`function\\s+${methodName}\\s*\\(`);
  return pattern.test(fileContent);
}

function expectPattern(filePath, pattern, message, issues) {
  const content = readFile(filePath);
  if (!pattern.test(content)) {
    issues.push(`${message} (${filePath})`);
  }
}

function main() {
  const content = readFile(routeFile);
  const routes = parseRoutes(content);
  const issues = [];

  for (const route of routes) {
    const { filePath, methodName } = handlerToFile(route.handler);
    if (!fs.existsSync(filePath)) {
      issues.push(`${route.method} ${route.path} -> missing file: ${filePath}`);
      continue;
    }

    const controllerContent = readFile(filePath);
    if (!methodExists(controllerContent, methodName)) {
      issues.push(`${route.method} ${route.path} -> missing method ${methodName} in ${filePath}`);
    }
  }

  expectPattern(
    path.join(workspaceRoot, 'app', 'service', 'system', 'SettingService.php'),
    /\$config\['api_key'\]\s*=\s*'';[\s\S]*\$config\['api_key_masked'\][\s\S]*\$config\['has_api_key'\]/,
    'DeepSeek read contract must blank api_key while exposing api_key_masked and has_api_key',
    issues
  );
  expectPattern(
    path.join(workspaceRoot, 'app', 'service', 'system', 'SettingService.php'),
    /\$hasApiKeyInput\s*=\s*array_key_exists\('api_key',\s*\$input\);[\s\S]*if\s*\(\$hasApiKeyInput\s*&&\s*\$incomingApiKey\s*===\s*''\)/,
    'DeepSeek update contract must only clear api_key when an explicit empty value is submitted',
    issues
  );
  expectPattern(
    path.join(workspaceRoot, 'app', 'adminapi', 'controller', 'system', 'SettingController.php'),
    /__deepseek_api_key_missing__[\s\S]*array_key_exists\('api_key',\s*\$payload\)/,
    'DeepSeek controller must preserve stored api_key when the request omits api_key',
    issues
  );
  expectPattern(
    path.join(workspaceRoot, 'app', 'service', 'dashboard', 'DashboardService.php'),
    /'pending_translation'\s*=>\s*\$this->translationRepository->countByStatuses\(\['pending', 'processing', 'review_required'\]\)[\s\S]*'pending_seo'\s*=>\s*\$this->seoRepository->countByStatuses\(\['pending'\]\)/,
    'Dashboard job counts must keep translation backlog and new SEO backlog status mappings aligned',
    issues
  );

  if (issues.length > 0) {
    console.error('Route validation failed:');
    for (const issue of issues) {
      console.error(`- ${issue}`);
    }
    process.exit(1);
  }

  console.log(`Route validation passed. Checked ${routes.length} routes.`);
}

main();
