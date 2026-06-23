const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const inquiriesPath = path.join(storageDir, 'inquiries.json');
const conversationsPath = path.join(storageDir, 'conversations.json');
const operationLogsPath = path.join(storageDir, 'operation_logs.json');
const routePath = path.join(backendRoot, 'route', 'adminapi.php');
const controllerPath = path.join(backendRoot, 'app', 'adminapi', 'controller', 'inquiry', 'InquiryController.php');
const servicePath = path.join(backendRoot, 'app', 'service', 'inquiry', 'InquiryService.php');

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

    $service = new app\\service\\inquiry\\InquiryService();

    $archivedInquiry = $service->updateWorkbenchArchiveStatus('inquiry', 81, 'archived');
    $restoredInquiry = $service->updateWorkbenchArchiveStatus('inquiry', 81, 'active');
    $archivedConversation = $service->updateWorkbenchArchiveStatus('conversation', 302, 'archived');

    echo json_encode([
      'archived_inquiry' => $archivedInquiry,
      'restored_inquiry' => $restoredInquiry,
      'archived_conversation' => $archivedConversation,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const backups = {
    inquiries: backup(inquiriesPath),
    conversations: backup(conversationsPath),
    logs: backup(operationLogsPath)
  };

  try {
    fs.mkdirSync(storageDir, { recursive: true });

    writeJson(inquiriesPath, [
      {
        id: 81,
        session_id: 301,
        source: 'ai',
        primary_contact_type: 'email',
        primary_contact_value: 'lead@example.com',
        customer_name: 'Daniel Foods',
        company_name: 'Daniel Foods',
        country_code: 'MX',
        language_code: 'en',
        product_interest: 'Cake filling line',
        solution_interest: 'Cake turnkey line',
        requirement_summary: 'Need a full line',
        inquiry_score: 88,
        status: 'new',
        archive_status: 'active',
        assigned_to: null,
        first_response_at: null,
        browse_traces: [],
        change_logs: [],
        follow_ups: [],
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:00:00'
      }
    ]);

    writeJson(conversationsPath, [
      {
        id: 301,
        session_id: 301,
        session_code: 'sess-301',
        source: 'ai',
        source_page: '/en/products/cake-line',
        entry_language: 'en',
        resolved_language: 'en',
        country_code: 'MX',
        device_type: 'desktop',
        utm_source: 'seo',
        is_valid_conversation: 1,
        inquiry_id: 81,
        archive_status: 'active',
        last_message_at: '2026-06-11 09:10:00',
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:10:00',
        messages: [],
        snapshots: []
      },
      {
        id: 302,
        session_id: 302,
        session_code: 'sess-302',
        source: 'ai',
        source_page: '/en/solutions/bread-line',
        entry_language: 'en',
        resolved_language: 'en',
        country_code: 'BR',
        device_type: 'mobile',
        utm_source: 'ads',
        is_valid_conversation: 1,
        inquiry_id: 0,
        archive_status: 'active',
        last_message_at: '2026-06-11 09:20:00',
        created_at: '2026-06-11 09:05:00',
        updated_at: '2026-06-11 09:20:00',
        messages: [],
        snapshots: []
      }
    ]);

    writeJson(operationLogsPath, []);

    const routeContent = fs.readFileSync(routePath, 'utf8');
    const controllerContent = fs.readFileSync(controllerPath, 'utf8');
    const serviceContent = fs.readFileSync(servicePath, 'utf8');
    const staticIssues = [];

    if (!/\/admin\/inquiry-workbench\/\{type\}\/\{id\}\/archive-status/.test(routeContent)) {
      staticIssues.push('missing inquiry archive-status route');
    }
    if (!/function\s+updateArchiveStatus\s*\(/.test(controllerContent)) {
      staticIssues.push('InquiryController must expose updateArchiveStatus()');
    }
    if (!/function\s+updateWorkbenchArchiveStatus\s*\(/.test(serviceContent)) {
      staticIssues.push('InquiryService must expose updateWorkbenchArchiveStatus()');
    }

    if (staticIssues.length > 0) {
      console.error('Inquiry archive static validation failed:');
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
    const archivedInquiry = payload.archived_inquiry || {};
    const restoredInquiry = payload.restored_inquiry || {};
    const archivedConversation = payload.archived_conversation || {};
    const issues = [];

    if (String(archivedInquiry.summary?.archive_status || '') !== 'archived') {
      issues.push('Inquiry archive action must archive inquiry summary');
    }
    if (String(archivedInquiry.conversation?.archive_status || '') !== 'archived') {
      issues.push('Inquiry archive action must sync linked conversation archive status');
    }
    if (String(restoredInquiry.summary?.archive_status || '') !== 'active') {
      issues.push('Inquiry restore action must restore inquiry summary');
    }
    if (String(restoredInquiry.conversation?.archive_status || '') !== 'active') {
      issues.push('Inquiry restore action must restore linked conversation archive status');
    }
    if (String(archivedConversation.summary?.archive_status || '') !== 'archived') {
      issues.push('Conversation archive action must archive conversation summary');
    }

    const logContent = JSON.parse(fs.readFileSync(operationLogsPath, 'utf8'));
    const actionPoints = Array.isArray(logContent) ? logContent.map((item) => String(item.action_point || '')) : [];
    if (!actionPoints.includes('inquiry.archive_status.update')) {
      issues.push('Inquiry archive action must write operation log');
    }
    if (!actionPoints.includes('conversation.archive_status.update')) {
      issues.push('Conversation archive action must write operation log');
    }

    if (issues.length > 0) {
      console.error('Inquiry archive runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Inquiry archive runtime validation passed.');
  } finally {
    restore(inquiriesPath, backups.inquiries);
    restore(conversationsPath, backups.conversations);
    restore(operationLogsPath, backups.logs);
  }
}

main();
