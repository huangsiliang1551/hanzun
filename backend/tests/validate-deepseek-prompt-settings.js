const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const settingsPath = path.join(backendRoot, 'runtime', 'storage', 'system_settings.json');
const operationLogsPath = path.join(backendRoot, 'runtime', 'storage', 'operation_logs.json');

function read(relativePath) {
  return fs.readFileSync(path.join(backendRoot, relativePath), 'utf8');
}

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

function expect(content, pattern, message, issues) {
  if (!pattern.test(content)) {
    issues.push(message);
  }
}

function main() {
  const issues = [];

  expect(
    read('app/adminapi/controller/system/SettingController.php'),
    /'prompts'\s*=>\s*\$request->input\('prompts'\)/,
    'SettingController must forward prompts in deepseek update payload',
    issues
  );
  expect(
    read('app/service/system/SettingService.php'),
    /'prompts'\s*=>\s*\$this->mergeDeepseekPrompts\(/,
    'SettingService must merge deepseek prompts into config updates',
    issues
  );
  expect(
    read('app/service/inquiry/PublicChatService.php'),
    /deepseekPrompt\('chat'\)/,
    'PublicChatService must load chat prompt from settings',
    issues
  );
  expect(
    read('app/service/seo/SeoService.php'),
    /deepseekPrompt\('seo'\)/,
    'SeoService must load SEO prompt from settings',
    issues
  );
  expect(
    read('app/service/translation/TranslationService.php'),
    /deepseekPrompt\('translation'\)/,
    'TranslationService must load translation prompt from settings',
    issues
  );

  const settingsBackup = backup(settingsPath);
  const operationLogsBackup = backup(operationLogsPath);

  try {
    fs.mkdirSync(path.dirname(settingsPath), { recursive: true });
    fs.writeFileSync(settingsPath, JSON.stringify({
      deepseek: {
        config: {
          api_key: '',
          chat_enabled: 1,
          translation_enabled: 1,
          seo_enabled: 1
        }
      }
    }, null, 2), 'utf8');

    const phpCode = `
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
      $initial = $service->deepseekConfig();
      $updated = $service->updateDeepseekConfig([
          'prompts' => [
              'chat' => ['system' => 'Custom chat prompt'],
              'seo' => ['system' => 'Custom seo prompt'],
          ],
      ]);
      $reset = $service->updateDeepseekConfig([
          'prompts' => [
              'chat' => ['system' => '   '],
          ],
      ]);
      $stored = (new app\\repository\\SystemSettingRepository())->get('deepseek', 'config', []);

      echo json_encode([
          'initial' => $initial,
          'updated' => $updated,
          'reset' => $reset,
          'stored' => $stored,
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8',
    }));

    if (String(payload.initial?.config?.prompts?.chat?.system || '').trim() === '') {
      issues.push('deepseekConfig() must expose default chat prompt');
    }
    if (String(payload.initial?.config?.prompts?.seo?.system || '').trim() === '') {
      issues.push('deepseekConfig() must expose default SEO prompt');
    }
    if (String(payload.initial?.config?.prompts?.translation?.system || '').trim() === '') {
      issues.push('deepseekConfig() must expose default translation prompt');
    }
    if (String(payload.updated?.config?.prompts?.chat?.system || '') !== 'Custom chat prompt') {
      issues.push('updateDeepseekConfig() must persist custom chat prompt');
    }
    if (String(payload.updated?.config?.prompts?.seo?.system || '') !== 'Custom seo prompt') {
      issues.push('updateDeepseekConfig() must persist custom SEO prompt');
    }
    if (String(payload.updated?.config?.prompts?.translation?.system || '').trim() === '') {
      issues.push('updateDeepseekConfig() must preserve default translation prompt when omitted');
    }
    if (String(payload.reset?.config?.prompts?.chat?.system || '').trim() === '') {
      issues.push('blank chat prompt update must resolve to a usable prompt value');
    }
    if (String(payload.stored?.prompts?.seo?.system || '') !== 'Custom seo prompt') {
      issues.push('stored deepseek config must include persisted SEO prompt');
    }
  } finally {
    restore(settingsPath, settingsBackup);
    restore(operationLogsPath, operationLogsBackup);
  }

  if (issues.length > 0) {
    console.error('DeepSeek prompt settings validation failed:');
    issues.forEach((issue) => console.error(`- ${issue}`));
    process.exit(1);
  }

  console.log('DeepSeek prompt settings validation passed.');
}

main();
