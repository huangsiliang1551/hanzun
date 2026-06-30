const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const conversationsPath = path.join(storageDir, 'conversations.json');
const inquiriesPath = path.join(storageDir, 'inquiries.json');
const visitorEventsPath = path.join(storageDir, 'visitor_events.json');
const inquiryControllerPath = path.join(backendRoot, 'app', 'adminapi', 'controller', 'inquiry', 'InquiryController.php');

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

    $inquiryService = new app\\service\\inquiry\\InquiryService();
    $conversationService = new app\\service\\inquiry\\ConversationService();

    echo json_encode([
      'workbench' => $inquiryService->workbench(),
      'workbench_inquiry_detail' => $inquiryService->workbenchDetail('inquiry', 51),
      'workbench_conversation_detail' => $inquiryService->workbenchDetail('conversation', 42),
      'inquiry_list' => $inquiryService->list(),
      'inquiry_detail' => $inquiryService->detail(51),
      'conversation_list' => $conversationService->list(),
      'conversation_detail' => $conversationService->detail(42),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const conversationBackup = backup(conversationsPath);
  const inquiryBackup = backup(inquiriesPath);
  const visitorEventBackup = backup(visitorEventsPath);

  try {
    fs.mkdirSync(storageDir, { recursive: true });

    const now = '2026-06-11 10:00:00';
    const earlier = '2026-06-11 09:00:00';
    const oldest = '2026-06-11 08:00:00';

    fs.writeFileSync(conversationsPath, JSON.stringify([
      {
        id: 41,
        session_id: 41,
        session_code: 'sess-converted',
        source: 'ai',
        source_page: '/en/products/cake-line',
        entry_language: 'en',
        resolved_language: 'en',
        country_code: 'AE',
        device_type: 'desktop',
        utm_source: 'google',
        is_valid_conversation: 1,
        inquiry_id: 51,
        archive_status: 'active',
        last_message_at: earlier,
        created_at: oldest,
        updated_at: earlier,
        messages: [
          {
            role: 'user',
            content: 'Need a cake line quotation',
            created_at: earlier,
            message_language: 'en',
            translated_text: '',
            intent_code: 'product_consulting',
            contains_contact_info: 1,
            extracted_entities_json: { email: 'converted@example.com' }
          }
        ],
        snapshots: [
          {
            snapshot_version: 1,
            contact_name: 'Converted Lead',
            company_name: 'Converted Foods',
            email: 'converted@example.com',
            phone: '',
            whatsapp: '',
            country_code: 'AE',
            product_interest: 'Cake line',
            solution_interest: 'Cake production line',
            requirement_summary: 'Need turnkey solution',
            confidence_score: 90,
            created_at: earlier
          }
        ]
      },
      {
        id: 42,
        session_id: 42,
        session_code: 'sess-unconverted',
        source: 'ai',
        source_page: '/en/solutions/biscuit-line',
        entry_language: 'fr',
        resolved_language: 'en',
        country_code: 'FR',
        device_type: 'mobile',
        utm_source: 'linkedin',
        is_valid_conversation: 1,
        inquiry_id: 0,
        archive_status: 'archived',
        last_message_at: now,
        created_at: earlier,
        updated_at: now,
        messages: [
          {
            role: 'user',
            content: 'Need biscuit line capacity and layout',
            created_at: now,
            message_language: 'fr',
            translated_text: 'Need biscuit line capacity and layout',
            intent_code: 'product_consulting',
            contains_contact_info: 0,
            extracted_entities_json: {}
          }
        ],
        snapshots: [
          {
            snapshot_version: 3,
            contact_name: '',
            company_name: '',
            email: '',
            phone: '',
            whatsapp: '',
            country_code: 'FR',
            product_interest: 'Biscuit line',
            solution_interest: 'Biscuit production line',
            requirement_summary: 'Need capacity comparison',
            confidence_score: 66,
            created_at: now
          }
        ]
      }
    ], null, 2), 'utf8');

    fs.writeFileSync(inquiriesPath, JSON.stringify([
      {
        id: 51,
        session_id: 41,
        source: 'ai',
        primary_contact_type: 'email',
        primary_contact_value: 'converted@example.com',
        customer_name: 'Converted Lead',
        company_name: 'Converted Foods',
        country_code: 'AE',
        language_code: 'en',
        product_interest: 'Cake line',
        solution_interest: 'Cake production line',
        requirement_summary: 'Need turnkey solution',
        inquiry_score: 90,
        status: 'contacted',
        archive_status: 'archived',
        assigned_to: null,
        first_response_at: earlier,
        browse_traces: [],
        change_logs: [],
        follow_ups: [],
        created_at: oldest,
        updated_at: now
      }
    ], null, 2), 'utf8');

    fs.writeFileSync(visitorEventsPath, JSON.stringify({
      'sess-converted': [
        {
          page: '/en/products/cake-line',
          title: 'Cake Line',
          referrer: 'https://example.com',
          visited_at: earlier,
          language_code: 'en'
        }
      ],
      'sess-unconverted': [
        {
          page: '/en/solutions/biscuit-line',
          title: 'Biscuit Line',
          referrer: 'https://example.com/chat',
          visited_at: now,
          language_code: 'fr'
        }
      ]
    }, null, 2), 'utf8');

    const controllerContent = fs.readFileSync(inquiryControllerPath, 'utf8');
    const staticIssues = [];
    if (!/function\s+workbench\s*\(/.test(controllerContent)) {
      staticIssues.push('InquiryController must expose workbench()');
    }
    if (!/function\s+workbenchDetail\s*\(/.test(controllerContent)) {
      staticIssues.push('InquiryController must expose workbenchDetail()');
    }

    if (staticIssues.length > 0) {
      console.error('Inquiry workbench static validation failed:');
      staticIssues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    const output = execFileSync('php', ['-r', buildPhpPayload()], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '0',
      },
    });

    const payload = JSON.parse(output);
    const issues = [];

    const workbenchItems = Array.isArray(payload.workbench?.items) ? payload.workbench.items : [];
    if (workbenchItems.length !== 2) {
      issues.push('InquiryService::workbench must merge inquiries with unconverted conversations only once each');
    }

    const inquiryWorkbenchItem = workbenchItems.find((item) => item.record_type === 'inquiry');
    const conversationWorkbenchItem = workbenchItems.find((item) => item.record_type === 'conversation');

    if (String(inquiryWorkbenchItem?.workbench_id || '') !== 'inquiry:51') {
      issues.push('Workbench inquiry items must expose a stable workbench_id');
    }
    if (String(inquiryWorkbenchItem?.archive_status || '') !== 'archived') {
      issues.push('Workbench inquiry items must expose archive_status');
    }
    if (String(conversationWorkbenchItem?.workbench_id || '') !== 'conversation:42') {
      issues.push('Workbench conversation items must expose a stable workbench_id');
    }
    if (String(conversationWorkbenchItem?.archive_status || '') !== 'archived') {
      issues.push('Workbench conversation items must expose archive_status');
    }
    if (Number(conversationWorkbenchItem?.message_count || 0) !== 1) {
      issues.push('Workbench conversation items must reuse conversation aggregate fields');
    }

    if (String(payload.workbench_inquiry_detail?.record_type || '') !== 'inquiry') {
      issues.push('Workbench detail must identify inquiry records');
    }
    if (String(payload.workbench_inquiry_detail?.summary?.archive_status || '') !== 'archived') {
      issues.push('Workbench inquiry detail must expose inquiry archive_status');
    }
    if (String(payload.workbench_inquiry_detail?.conversation?.archive_status || '') !== 'active') {
      issues.push('Workbench inquiry detail must expose linked conversation archive_status');
    }

    if (String(payload.workbench_conversation_detail?.record_type || '') !== 'conversation') {
      issues.push('Workbench detail must identify conversation records');
    }
    if (String(payload.workbench_conversation_detail?.summary?.archive_status || '') !== 'archived') {
      issues.push('Workbench conversation detail must expose conversation archive_status');
    }
    if (!Array.isArray(payload.workbench_conversation_detail?.browse_traces) || String(payload.workbench_conversation_detail.browse_traces[0]?.title || '') !== 'Biscuit Line') {
      issues.push('Workbench conversation detail must reuse the existing conversation detail structure');
    }

    if (String(payload.inquiry_list?.items?.[0]?.archive_status || '') !== 'archived') {
      issues.push('InquiryService::list must expose archive_status');
    }
    if (String(payload.inquiry_detail?.summary?.archive_status || '') !== 'archived') {
      issues.push('InquiryService::detail summary must expose archive_status');
    }
    if (String(payload.inquiry_detail?.conversation?.archive_status || '') !== 'active') {
      issues.push('InquiryService::detail conversation block must expose archive_status');
    }

    const conversationListItem = Array.isArray(payload.conversation_list?.items)
      ? payload.conversation_list.items.find((item) => Number(item.session_id || 0) === 42)
      : null;
    if (String(conversationListItem?.archive_status || '') !== 'archived') {
      issues.push('ConversationService::list must expose archive_status');
    }
    if (String(payload.conversation_detail?.summary?.archive_status || '') !== 'archived') {
      issues.push('ConversationService::detail summary must expose archive_status');
    }

    if (issues.length > 0) {
      console.error('Inquiry workbench runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Inquiry workbench runtime validation passed.');
  } finally {
    restore(conversationsPath, conversationBackup);
    restore(inquiriesPath, inquiryBackup);
    restore(visitorEventsPath, visitorEventBackup);
  }
}

main();
