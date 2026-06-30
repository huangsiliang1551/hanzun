const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  conversations: path.join(storageDir, 'conversations.json'),
  inquiries: path.join(storageDir, 'inquiries.json'),
  visitorEvents: path.join(storageDir, 'visitor_events.json'),
  operationLogs: path.join(storageDir, 'operation_logs.json'),
};

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

function main() {
  const backups = {};
  Object.entries(files).forEach(([key, filePath]) => {
    backups[key] = backup(filePath);
  });

  try {
    writeJson(files.conversations, [
      {
        id: 61,
        session_id: 61,
        session_code: 'sess-convert-61',
        source: 'ai',
        source_page: '/en/products/cake-line',
        entry_language: 'en',
        resolved_language: 'en',
        country_code: 'MX',
        device_type: 'desktop',
        utm_source: 'google',
        is_valid_conversation: 1,
        inquiry_id: 0,
        archive_status: 'active',
        last_message_at: '2026-06-11 10:12:00',
        created_at: '2026-06-11 10:00:00',
        updated_at: '2026-06-11 10:12:00',
        messages: [
          {
            role: 'user',
            content: 'Need cake line quotation',
            created_at: '2026-06-11 10:12:00',
            message_language: 'en',
            translated_text: '',
            intent_code: 'product_consulting',
            contains_contact_info: 1,
            extracted_entities_json: { email: 'daniel@food.mx' }
          }
        ],
        snapshots: [
          {
            snapshot_version: 2,
            contact_name: 'Daniel',
            company_name: 'Daniel Foods',
            email: 'daniel@food.mx',
            phone: '',
            whatsapp: '',
            country_code: 'MX',
            product_interest: 'Cake line',
            solution_interest: 'Cake automatic production line',
            requirement_summary: 'Need full export setup',
            confidence_score: 88,
            created_at: '2026-06-11 10:12:00'
          }
        ]
      }
    ]);
    writeJson(files.inquiries, []);
    writeJson(files.visitorEvents, {
      'sess-convert-61': [
        {
          page: '/en/products/cake-line',
          title: 'Cake Line',
          referrer: 'https://example.com',
          visited_at: '2026-06-11 10:05:00',
          language_code: 'en'
        }
      ]
    });
    writeJson(files.operationLogs, []);

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

      $service = new app\\service\\inquiry\\InquiryService();
      $conversationService = new app\\service\\inquiry\\ConversationService();
      $logService = new app\\service\\log\\OperationLogService();

      $converted = $service->convertConversationToInquiry(61, [
        'country_code' => 'BR',
        'product_interest' => 'Cake line 3000 pcs/h',
        'assigned_to' => 9,
        'status' => 'contacted',
      ]);

      echo json_encode([
        'converted' => $converted,
        'workbench' => $service->workbench(),
        'conversation_detail' => $conversationService->detail(61),
        'operation_logs' => $logService->listOperations(),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '0',
      },
    }));

    const issues = [];
    const inquirySummary = payload.converted?.inquiry_summary || {};
    if (Number(inquirySummary.id || 0) <= 0) {
      issues.push('convertConversationToInquiry must create and return inquiry_summary');
    }
    if (String(inquirySummary.country_code || '') !== 'BR') {
      issues.push('convertConversationToInquiry must apply manual country override');
    }
    if (String(inquirySummary.product_interest || '') !== 'Cake line 3000 pcs/h') {
      issues.push('convertConversationToInquiry must apply manual product override');
    }
    if (String(inquirySummary.status || '') !== 'contacted') {
      issues.push('convertConversationToInquiry must apply manual status override');
    }
    if (Number(inquirySummary.assigned_to || 0) !== 9) {
      issues.push('convertConversationToInquiry must apply assignee override');
    }

    if (Number(payload.conversation_detail?.summary?.inquiry_id || 0) !== Number(inquirySummary.id || 0)) {
      issues.push('Conversation detail must link back to the newly created inquiry');
    }

    const workbenchItems = Array.isArray(payload.workbench?.items) ? payload.workbench.items : [];
    if (!workbenchItems.some((item) => item.record_type === 'inquiry' && Number(item.session_id || 0) === 61)) {
      issues.push('Workbench must include the converted inquiry record');
    }
    if (workbenchItems.some((item) => item.record_type === 'conversation' && Number(item.session_id || 0) === 61)) {
      issues.push('Workbench must remove converted conversation-only rows');
    }

    const logs = payload.operation_logs && Array.isArray(payload.operation_logs.items)
      ? payload.operation_logs.items
      : (Array.isArray(payload.operation_logs) ? payload.operation_logs : []);
    const actionPoints = logs.map((item) => String(item.action_point || ''));
    if (!actionPoints.includes('inquiry.conversation.convert')) {
      issues.push('OperationLogService missing inquiry.conversation.convert');
    }

    const routeContent = fs.readFileSync(path.join(backendRoot, 'route', 'adminapi.php'), 'utf8');
    const controllerContent = fs.readFileSync(path.join(backendRoot, 'app', 'adminapi', 'controller', 'inquiry', 'InquiryController.php'), 'utf8');
    if (!/\/admin\/ai-conversations\/\{id\}\/convert/.test(routeContent)) {
      issues.push('missing ai conversation convert route');
    }
    if (!/function\s+convertConversation\s*\(/.test(controllerContent)) {
      issues.push('InquiryController must expose convertConversation()');
    }

    if (issues.length > 0) {
      console.error('Inquiry conversion runtime validation failed:');
      issues.forEach((issue) => console.error('- ' + issue));
      process.exit(1);
    }

    console.log('Inquiry conversion runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
