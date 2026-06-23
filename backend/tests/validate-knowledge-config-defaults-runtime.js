const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const settingsPath = path.join(backendRoot, 'runtime', 'storage', 'system_settings.json');

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

function main() {
  const backupContent = backup(settingsPath);
  const backendRootAbs = backendRoot.replace(/\\/g, '/');

  try {
    const seed = JSON.parse(backupContent || '{}');
    if (!seed.deepseek) {
      seed.deepseek = {};
    }
    seed.deepseek.config = {
      base_url: 'https://api.deepseek.com/v1',
      model: 'deepseek-chat',
      api_key: '',
      timeout_seconds: 30,
      retry_times: 0,
      chat_enabled: 1,
      translation_enabled: 0,
      seo_enabled: 0,
      prompts: {
        chat: { system: 'enabled' },
      },
    };
    restore(settingsPath, JSON.stringify(seed, null, 2));

    const output = execFileSync('php', ['-r', `
require '${backendRootAbs}/app/common/bootstrap/Autoloader.php';
require '${backendRootAbs}/app/common/bootstrap/EnvLoader.php';
require '${backendRootAbs}/app/common/bootstrap/helpers.php';
app\\common\\bootstrap\\Autoloader::register('${backendRootAbs}');
app\\common\\bootstrap\\EnvLoader::load('${backendRootAbs}/.env');
app\\common\\config\\ConfigRepository::instance()->load('${backendRootAbs}/config');
app\\common\\database\\DatabaseManager::instance()->configure(app\\common\\config\\ConfigRepository::instance()->get('database.connections.mysql', []));
$repo = new app\\repository\\SystemSettingRepository();
echo json_encode($repo->deepseekConfig(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
`], {
      cwd: backendRoot,
      encoding: 'utf8',
    });

    const config = JSON.parse(output);
    const issues = [];
    if (Number(config.chat_enabled ?? 0) !== 1) {
      issues.push('deepseekConfig() must preserve stored chat_enabled value');
    }
    if (Number(config.translation_enabled ?? 1) !== 0) {
      issues.push('deepseekConfig() must preserve stored translation_enabled value');
    }
    if (Number(config.knowledge_enabled ?? 0) !== 1) {
      issues.push('deepseekConfig() must default knowledge_enabled to 1 when the stored config omits it');
    }
    if (Number(config.knowledge_top_k ?? 0) !== 5) {
      issues.push('deepseekConfig() must default knowledge_top_k to 5');
    }
    if (Number(config.chat_max_history_messages ?? 0) !== 6) {
      issues.push('deepseekConfig() must default chat_max_history_messages to 6');
    }

    if (issues.length > 0) {
      console.error('Knowledge config defaults validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Knowledge config defaults validation passed.');
  } finally {
    restore(settingsPath, backupContent);
  }
}

main();
