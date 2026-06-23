const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const conversationsPath = path.join(storageDir, 'conversations.json');
const inquiriesPath = path.join(storageDir, 'inquiries.json');
const visitorEventsPath = path.join(storageDir, 'visitor_events.json');
const deepseekLogsPath = path.join(storageDir, 'deepseek_logs.json');

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

function buildPhpScript() {
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

    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148';

    $service = new app\\service\\inquiry\\PublicChatService();
    $firstVisit = $service->recordVisitorEvent([
      'client_id' => 'client-flow-001',
      'path' => '/en/solutions/cake-line',
      'title' => 'Cake Line',
      'referrer' => 'https://www.google.com/',
      'language' => 'en-US',
    ]);

    $firstChat = $service->chat([
      'client_id' => 'client-flow-001',
      'session_code' => $firstVisit['session_code'],
      'message' => 'This is Daniel. Company name is Daniel Foods GmbH. Email daniel@example.com. We need a cake line quotation for Germany.',
      'path' => '/en/solutions/cake-line',
      'title' => 'Cake Line',
      'referrer' => 'https://www.google.com/',
      'language' => 'en-US',
      'utm_source' => 'google',
    ]);

    $secondVisit = $service->recordVisitorEvent([
      'client_id' => 'client-flow-001',
      'session_code' => $firstVisit['session_code'],
      'path' => '/en/contact',
      'title' => 'Contact',
      'referrer' => 'https://www.google.com/',
      'language' => 'en',
    ]);

    $secondChat = $service->chat([
      'client_id' => 'client-flow-001',
      'session_code' => $firstVisit['session_code'],
      'message' => 'My phone is +49 123 456 789 and we also need layout planning.',
      'path' => '/en/contact',
      'title' => 'Contact',
      'referrer' => 'https://www.google.com/',
      'language' => 'en',
      'utm_source' => 'google',
    ]);

    $repository = new app\\repository\\PublicChatRepository();
    $conversation = $repository->findConversationByCode($firstVisit['session_code']);
    $inquiry = $repository->findInquiryBySessionId((int) ($conversation['session_id'] ?? 0));
    $events = $repository->listVisitorEvents($firstVisit['session_code']);
    $logs = (new app\\repository\\DeepSeekLogRepository())->list();

    echo json_encode([
      'first_visit' => $firstVisit,
      'first_chat' => $firstChat,
      'second_visit' => $secondVisit,
      'second_chat' => $secondChat,
      'conversation' => $conversation,
      'inquiry' => $inquiry,
      'events' => $events,
      'logs' => $logs,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const conversationBackup = backup(conversationsPath);
  const inquiryBackup = backup(inquiriesPath);
  const visitorEventBackup = backup(visitorEventsPath);
  const deepseekLogBackup = backup(deepseekLogsPath);

  try {
    fs.mkdirSync(storageDir, { recursive: true });
    fs.writeFileSync(conversationsPath, '[]', 'utf8');
    fs.writeFileSync(inquiriesPath, '[]', 'utf8');
    fs.writeFileSync(visitorEventsPath, '{}', 'utf8');
    fs.writeFileSync(deepseekLogsPath, '[]', 'utf8');

    const output = execFileSync('php', ['-r', buildPhpScript()], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '0',
      },
    });

    const payload = JSON.parse(output);
    const issues = [];

    if (!String(payload.first_visit?.session_code || '').startsWith('web-')) {
      issues.push('recordVisitorEvent must derive a web-* session code when the incoming session is empty');
    }
    if (Number(payload.first_visit?.visit_count || 0) !== 1) {
      issues.push('recordVisitorEvent must persist the initial visitor event');
    }
    if (Number(payload.first_chat?.inquiry_id || 0) <= 0) {
      issues.push('chat must auto-create an inquiry when email plus intent data are available');
    }
    if (String(payload.conversation?.source || '') !== 'ai') {
      issues.push('conversation source must remain ai');
    }
    if (String(payload.conversation?.entry_language || '') !== 'en') {
      issues.push('conversation entry_language must normalize en-US to en');
    }
    if (String(payload.conversation?.utm_source || '') !== 'google') {
      issues.push('conversation must persist utm_source');
    }
    if (String(payload.conversation?.device_type || '') !== 'mobile') {
      issues.push('conversation must infer mobile device type from user agent');
    }
    if (!Array.isArray(payload.conversation?.messages) || payload.conversation.messages.length !== 4) {
      issues.push('conversation must append both user and assistant messages for each chat turn');
    }
    if (!Array.isArray(payload.conversation?.snapshots) || payload.conversation.snapshots.length < 2) {
      issues.push('conversation must append lead snapshots across chat turns');
    }
    if (String(payload.inquiry?.source || '') !== 'ai') {
      issues.push('auto-created inquiry must use ai as the only source');
    }
    if (String(payload.inquiry?.primary_contact_type || '') !== 'email') {
      issues.push('inquiry primary contact type must prefer email when email is captured');
    }
    if (String(payload.inquiry?.primary_contact_value || '') !== 'daniel@example.com') {
      issues.push('inquiry must persist the extracted email');
    }
    if (String(payload.inquiry?.company_name || '') !== 'Daniel Foods GmbH') {
      issues.push('inquiry must persist the extracted company name');
    }
    if (String(payload.inquiry?.country_code || '') !== 'DE') {
      issues.push('inquiry must persist the extracted country code');
    }
    if (String(payload.inquiry?.product_interest || '') === '' && String(payload.inquiry?.solution_interest || '') === '') {
      issues.push('inquiry must persist product or solution interest');
    }
    if (String(payload.inquiry?.phone || '') !== '' && !String(payload.inquiry.phone).includes('+49')) {
      issues.push('inquiry phone should preserve the later captured phone number when present');
    }
    if (!Array.isArray(payload.inquiry?.browse_traces) || payload.inquiry.browse_traces.length < 3) {
      issues.push('inquiry browse traces must be synced after inquiry creation and later visits');
    }
    if (String(payload.inquiry?.browse_traces?.[payload.inquiry.browse_traces.length - 1]?.title || '') !== 'Contact') {
      issues.push('inquiry browse traces must include the latest visited page metadata');
    }
    if (Number(payload.second_visit?.visit_count || 0) < 3) {
      issues.push('recordVisitorEvent must keep accumulating events for an existing session');
    }
    if (Number(payload.second_chat?.inquiry_id || 0) !== Number(payload.first_chat?.inquiry_id || 0)) {
      issues.push('follow-up chat must update the existing inquiry instead of creating a new one');
    }
    if (!Array.isArray(payload.logs) || payload.logs.length < 2) {
      issues.push('deepseek fallback path must still record call logs for chat attempts');
    }

    if (issues.length > 0) {
      console.error('Public chat service flow validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Public chat service flow validation passed.');
  } finally {
    restore(conversationsPath, conversationBackup);
    restore(inquiriesPath, inquiryBackup);
    restore(visitorEventsPath, visitorEventBackup);
    restore(deepseekLogsPath, deepseekLogBackup);
  }
}

main();
