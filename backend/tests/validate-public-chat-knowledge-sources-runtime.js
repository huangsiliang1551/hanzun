const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
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

function phpRoot() {
  return backendRoot.replace(/\\/g, '/');
}

function runPhp(script) {
  return execFileSync('php', ['-r', script], {
    cwd: backendRoot,
    encoding: 'utf8',
    env: {
      ...process.env,
      PREFER_RUNTIME_STORAGE: '1',
    },
  });
}

function buildSessionCode(clientId) {
  return `web-${require('crypto').createHash('sha1').update(clientId).digest('hex').slice(0, 8)}-knowledge-test-001`;
}

function main() {
  const conversationBackup = backup(conversationsPath);
  const inquiryBackup = backup(inquiriesPath);
  const visitorBackup = backup(visitorEventsPath);
  const root = phpRoot();

  try {
    fs.mkdirSync(storageDir, { recursive: true });
    fs.writeFileSync(conversationsPath, JSON.stringify([], null, 2), 'utf8');
    fs.writeFileSync(inquiriesPath, JSON.stringify([], null, 2), 'utf8');
    fs.writeFileSync(visitorEventsPath, JSON.stringify({}, null, 2), 'utf8');

    const clientId = 'knowledge-test-client';
    const sessionCode = buildSessionCode(clientId);

    const combinedOutput = runPhp(`
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
  'message' => 'Tell me about the cake line and what you can provide.',
  'path' => '/en/news/germany-bakery-expo.html',
  'title' => 'Germany Bakery Expo',
  'referrer' => '',
  'language' => 'en',
]);
$session = $service->session([
  'client_id' => '${clientId}',
  'session_code' => (string) ($chat['session_code'] ?? ''),
]);
echo json_encode([
  'chat' => $chat,
  'session' => $session,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
`);

    const payload = JSON.parse(combinedOutput);
    const chat = payload && typeof payload === 'object' ? payload.chat || {} : {};
    const issues = [];

    if (!Array.isArray(chat.sources) || chat.sources.length === 0) {
      issues.push('chat response must include knowledge sources when indexed documents exist');
    }
    if (!chat.sources.some((item) => String(item.title || '').length > 0)) {
      issues.push('chat response sources must include source titles');
    }

    const returnedSessionCode = String(chat.session_code || '').trim();
    if (!returnedSessionCode) {
      issues.push('chat response must return a session_code');
    }

    const session = payload && typeof payload === 'object' ? payload.session || {} : {};
    const assistant = Array.isArray(session.messages) ? session.messages.find((item) => item.role === 'assistant') : null;
    if (!Array.isArray(assistant?.sources) || assistant.sources.length === 0) {
      issues.push('public session hydrate must preserve assistant sources');
    }

    if (issues.length > 0) {
      console.error('Public chat knowledge source validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Public chat knowledge source validation passed.');
  } finally {
    restore(conversationsPath, conversationBackup);
    restore(inquiriesPath, inquiryBackup);
    restore(visitorEventsPath, visitorBackup);
  }
}

main();
