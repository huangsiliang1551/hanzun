const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const settingsPath = path.join(storageDir, 'system_settings.json');
const deepseekLogsPath = path.join(storageDir, 'deepseek_logs.json');
const operationLogsPath = path.join(storageDir, 'operation_logs.json');
const routePath = path.join(backendRoot, 'route', 'adminapi.php');
const controllerPath = path.join(backendRoot, 'app', 'adminapi', 'controller', 'system', 'SettingController.php');
const servicePath = path.join(backendRoot, 'app', 'service', 'system', 'SettingService.php');

function backup(filePath) {
  return fs.existsSync(filePath) ? fs.readFileSync(filePath, 'utf8') : null;
}

function restore(filePath, content) {
  if (content === null) {
    if (fs.existsSync(filePath)) {
      fs.unlinkSync(filePath);
    }
    return;
  }

  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, content, 'utf8');
}

function writeJson(filePath, data) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf8');
}

function buildPhpPayload() {
  return `
    $basePath = getcwd();
    require_once $basePath . '/app/common/bootstrap/Autoloader.php';
    require_once $basePath . '/app/common/bootstrap/EnvLoader.php';
    require_once $basePath . '/app/common/bootstrap/helpers.php';
    app\\common\\bootstrap\\Autoloader::register($basePath);
    app\\common\\bootstrap\\EnvLoader::load($basePath . '/.env');

    app\\common\\config\\ConfigRepository::instance()->load($basePath . '/config');

    app\\common\\database\\DatabaseManager::instance()->configure(

        app\\common\\config\\ConfigRepository::instance()->get('database.connections.mysql', [])

    );

    $service = new app\\service\\system\\SettingService();
    $result = $service->testDeepseekConnection();
    $config = $service->deepseekConfig();

    echo json_encode([
      'result' => $result,
      'config' => $config,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const backups = {
    settings: backup(settingsPath),
    logs: backup(deepseekLogsPath),
    operations: backup(operationLogsPath)
  };

  try {
    fs.mkdirSync(storageDir, { recursive: true });
    writeJson(settingsPath, {
      deepseek: {
        config: {
          base_url: 'https://api.deepseek.com/v1',
          model: 'deepseek-chat',
          api_key: '',
          timeout_seconds: 10,
          retry_times: 0,
          chat_enabled: 1,
          translation_enabled: 0,
          seo_enabled: 0,
          prompts: {}
        }
      }
    });
    writeJson(deepseekLogsPath, []);
    writeJson(operationLogsPath, []);

    const routeContent = fs.readFileSync(routePath, 'utf8');
    const controllerContent = fs.readFileSync(controllerPath, 'utf8');
    const serviceContent = fs.readFileSync(servicePath, 'utf8');
    const staticIssues = [];

    if (!/\/admin\/settings\/deepseek\/test/.test(routeContent)) {
      staticIssues.push('missing deepseek test route');
    }
    if (!/function\s+testDeepseek\s*\(/.test(controllerContent)) {
      staticIssues.push('SettingController must expose testDeepseek()');
    }
    if (!/function\s+testDeepseekConnection\s*\(/.test(serviceContent)) {
      staticIssues.push('SettingService must expose testDeepseekConnection()');
    }

    if (staticIssues.length > 0) {
      console.error('DeepSeek test static validation failed:');
      staticIssues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    const output = execFileSync('php', ['-r', buildPhpPayload()], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '0'
      }
    });

    const payload = JSON.parse(output);
    const result = payload.result || {};
    const config = payload.config?.config || {};
    const issues = [];

    if (String(result.status || '') !== 'failed') {
      issues.push('DeepSeek test should return failed when API key is missing');
    }
    if (!String(result.message || '').trim()) {
      issues.push('DeepSeek test should return failure reason');
    }
    if (!String(result.connection_label || '').includes('最近测试失败')) {
      issues.push('DeepSeek test should produce failure connection label');
    }
    if (String(config.last_test_status || '') !== 'failed') {
      issues.push('DeepSeek test should persist last_test_status');
    }
    if (!String(config.connection_label || '').includes('最近测试失败')) {
      issues.push('DeepSeek config should expose updated connection_label');
    }

    const logItems = JSON.parse(fs.readFileSync(deepseekLogsPath, 'utf8'));
    const hasFailedChatLog = Array.isArray(logItems) && logItems.some((item) => String(item.feature_code || '') === 'chat' && Number(item.is_success || 0) === 0);
    if (!hasFailedChatLog) {
      issues.push('DeepSeek test should append failed chat deepseek log record');
    }

    const operationLogs = JSON.parse(fs.readFileSync(operationLogsPath, 'utf8'));
    const actionPoints = Array.isArray(operationLogs) ? operationLogs.map((item) => String(item.action_point || '')) : [];
    if (!actionPoints.includes('system.deepseek.test')) {
      issues.push('DeepSeek test should write operation log');
    }

    if (issues.length > 0) {
      console.error('DeepSeek test runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('DeepSeek test runtime validation passed.');
  } finally {
    restore(settingsPath, backups.settings);
    restore(deepseekLogsPath, backups.logs);
    restore(operationLogsPath, backups.operations);
  }
}

main();
