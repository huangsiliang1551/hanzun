const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const settingsPath = path.join(storageDir, 'system_settings.json');
const conversationsPath = path.join(storageDir, 'conversations.json');
const inquiriesPath = path.join(storageDir, 'inquiries.json');
const visitorEventsPath = path.join(storageDir, 'visitor_events.json');

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

function buildSessionCode(clientId) {
  return `web-${crypto.createHash('sha1').update(clientId).digest('hex').slice(0, 8)}-knowledge-fallback-001`;
}

function phpRoot() {
  return backendRoot.replace(/\\/g, '/');
}

function main() {
  const settingsBackup = backup(settingsPath);
  const conversationsBackup = backup(conversationsPath);
  const inquiriesBackup = backup(inquiriesPath);
  const visitorBackup = backup(visitorEventsPath);
  const root = phpRoot();

  try {
    fs.mkdirSync(storageDir, { recursive: true });
    const seed = JSON.parse(settingsBackup || '{}');
    if (!seed.deepseek) {
      seed.deepseek = {};
    }
    seed.deepseek.config = {
      base_url: 'https://api.deepseek.com/v1',
      model: 'deepseek-chat',
      api_key: '',
      timeout_seconds: 30,
      retry_times: 0,
      chat_enabled: 0,
      translation_enabled: 0,
      seo_enabled: 0,
      knowledge_enabled: 1,
      knowledge_top_k: 5,
      knowledge_max_chars: 4000,
      chat_max_history_messages: 6,
      prompts: {
        chat: {
          system: 'You are a bakery equipment assistant.',
          rag: 'Use the knowledge context first.',
        },
      },
    };
    restore(settingsPath, JSON.stringify(seed, null, 2));
    restore(conversationsPath, JSON.stringify([], null, 2));
    restore(inquiriesPath, JSON.stringify([], null, 2));
    restore(visitorEventsPath, JSON.stringify({}, null, 2));

    const clientId = 'knowledge-fallback-client';
    const sessionCode = buildSessionCode(clientId);
    const output = execFileSync('php', ['-r', `
require '${root}/app/common/bootstrap/Autoloader.php';
require '${root}/app/common/bootstrap/EnvLoader.php';
require '${root}/app/common/bootstrap/helpers.php';
app\\common\\bootstrap\\Autoloader::register('${root}');
app\\common\\bootstrap\\EnvLoader::load('${root}/.env');
app\\common\\config\\ConfigRepository::instance()->load('${root}/config');
app\\common\\database\\DatabaseManager::instance()->configure(app\\common\\config\\ConfigRepository::instance()->get('database.connections.mysql', []));
$service = new app\\service\\inquiry\\PublicChatService();
$chat = $service->chat([
  'client_id' => '${clientId}',
  'session_code' => '${sessionCode}',
  'message' => '请介绍蛋糕自动生产线和蛋糕自动灌装机，你们可以提供哪些设备？',
  'path' => '/zh/index.html',
  'title' => '知识库兜底测试',
  'referrer' => '',
  'language' => 'zh',
  'utm_source' => '',
]);
echo json_encode($chat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
`], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '1',
      },
    });

    const payload = JSON.parse(output);
    const issues = [];

    if (!Array.isArray(payload.sources) || payload.sources.length === 0) {
      issues.push('chat fallback must still expose knowledge sources when runtime knowledge documents exist');
    }

    const reply = String(payload.assistant_reply || '').trim();
    if (!reply) {
      issues.push('chat fallback must still return a reply when chat_enabled is disabled');
    }
    if (reply === '请留下您的邮箱或 WhatsApp，并告诉我您需要哪类烘焙设备或整线方案。') {
      issues.push('chat fallback should prioritize knowledge guidance before the generic contact-info prompt');
    }

    if (issues.length > 0) {
      console.error('Public chat knowledge fallback validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Public chat knowledge fallback validation passed.');
  } finally {
    restore(settingsPath, settingsBackup);
    restore(conversationsPath, conversationsBackup);
    restore(inquiriesPath, inquiriesBackup);
    restore(visitorEventsPath, visitorBackup);
  }
}

main();
