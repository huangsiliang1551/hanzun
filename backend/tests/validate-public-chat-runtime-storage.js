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

function writeRuntimeJson(filePath, payload, { bom = false } = {}) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  const json = JSON.stringify(payload, null, 2);
  fs.writeFileSync(filePath, bom ? `\uFEFF${json}` : json, 'utf8');
}

function main() {
  const conversationBackup = backup(conversationsPath);
  const inquiryBackup = backup(inquiriesPath);
  const visitorEventBackup = backup(visitorEventsPath);

  try {
    fs.mkdirSync(storageDir, { recursive: true });

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    writeRuntimeJson(conversationsPath, [
      {
        id: 8,
        session_id: 8,
        session_code: 'sess-storage-check',
        source: 'ai',
        source_page: '/en/products/cake-line',
        entry_language: 'en',
        resolved_language: 'en',
        country_code: 'AE',
        device_type: 'desktop',
        utm_source: 'google',
        is_valid_conversation: 1,
        inquiry_id: 18,
        last_message_at: now,
        created_at: now,
        updated_at: now,
        messages: [
          {
            role: 'user',
            content: 'Need cake line',
            created_at: now,
            message_language: 'en',
            translated_text: '',
            intent_code: 'product_consulting',
            contains_contact_info: 0,
            extracted_entities_json: { product_interest: 'Cake line' }
          }
        ],
        snapshots: [
          {
            contact_name: 'Daniel',
            company_name: 'Daniel Foods',
            email: 'daniel@example.com',
            phone: '',
            whatsapp: '',
            country_code: 'AE',
            product_interest: 'Cake line',
            solution_interest: 'Cake production line',
            requirement_summary: 'Need quotation',
            confidence_score: 78,
            created_at: now
          }
        ]
      }
    ], { bom: true });

    writeRuntimeJson(inquiriesPath, [
      {
        id: 18,
        session_id: 8,
        source: 'ai',
        primary_contact_type: 'email',
        primary_contact_value: 'daniel@example.com',
        customer_name: 'Daniel',
        company_name: 'Daniel Foods',
        country_code: 'AE',
        language_code: 'en',
        product_interest: 'Cake line',
        solution_interest: 'Cake production line',
        requirement_summary: 'Need quotation',
        inquiry_score: 78,
        status: 'new',
        assigned_to: null,
        first_response_at: null,
        browse_traces: [
          {
            page: '/en/products/cake-line',
            title: 'Cake Line',
            referrer: 'https://example.com',
            visited_at: now,
            language_code: 'en'
          }
        ],
        change_logs: [
          {
            field: 'status',
            from: 'new',
            to: 'new',
            changed_at: now
          }
        ],
        follow_ups: [
          {
            content: 'AI created inquiry automatically',
            created_at: now
          }
        ],
        created_at: now,
        updated_at: now
      }
    ], { bom: true });

    writeRuntimeJson(visitorEventsPath, {
      'sess-storage-check': [
        {
          page: '/en/products/cake-line',
          title: 'Cake Line',
          referrer: 'https://example.com',
          visited_at: now,
          language_code: 'en'
        }
      ]
    }, { bom: true });

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
      $repo = new app\\repository\\PublicChatRepository();
      echo json_encode([
        'conversation' => $repo->findConversationByCode('sess-storage-check'),
        'inquiry' => $repo->findInquiryBySessionId(8),
        'visitor_events' => $repo->listVisitorEvents('sess-storage-check')
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const output = execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '1',
      },
    });

    const payload = JSON.parse(output);
    const issues = [];

    if (String(payload.conversation?.session_code || '') !== 'sess-storage-check') {
      issues.push('findConversationByCode did not return runtime conversation');
    }
    if (!Array.isArray(payload.conversation?.messages) || payload.conversation.messages.length !== 1) {
      issues.push('findConversationByCode did not normalize conversation messages');
    }
    if (!Array.isArray(payload.conversation?.snapshots) || payload.conversation.snapshots.length !== 1) {
      issues.push('findConversationByCode did not normalize conversation snapshots');
    }
    if (String(payload.inquiry?.primary_contact_value || '') !== 'daniel@example.com') {
      issues.push('findInquiryBySessionId did not return runtime inquiry');
    }
    if (!Array.isArray(payload.inquiry?.browse_traces) || String(payload.inquiry.browse_traces[0]?.title || '') !== 'Cake Line') {
      issues.push('findInquiryBySessionId did not preserve browse trace metadata');
    }
    if (!Array.isArray(payload.visitor_events) || String(payload.visitor_events[0]?.title || '') !== 'Cake Line') {
      issues.push('listVisitorEvents did not return full visitor event metadata');
    }

    if (issues.length > 0) {
      console.error('Public chat runtime storage validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Public chat runtime storage validation passed.');
  } finally {
    restore(conversationsPath, conversationBackup);
    restore(inquiriesPath, inquiryBackup);
    restore(visitorEventsPath, visitorEventBackup);
  }
}

main();
