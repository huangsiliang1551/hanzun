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

function main() {
  const conversationBackup = backup(conversationsPath);
  const inquiryBackup = backup(inquiriesPath);
  const visitorEventBackup = backup(visitorEventsPath);

  try {
    fs.mkdirSync(storageDir, { recursive: true });

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    fs.writeFileSync(conversationsPath, JSON.stringify([
      {
        id: 21,
        session_id: 21,
        session_code: 'sess-ai-detail',
        source: 'ai',
        source_page: '/en/products/cake-line',
        entry_language: 'en',
        resolved_language: 'en',
        country_code: 'AE',
        device_type: 'desktop',
        utm_source: 'google',
        is_valid_conversation: 1,
        inquiry_id: 31,
        last_message_at: now,
        created_at: now,
        updated_at: now,
        messages: [
          {
            role: 'user',
            content: 'Need a cake line',
            created_at: now,
            message_language: 'en',
            translated_text: 'Need a cake line',
            intent_code: 'product_consulting',
            contains_contact_info: 1,
            extracted_entities_json: { company_name: 'Daniel Foods', email: 'daniel@example.com' },
          },
          {
            role: 'assistant',
            content: 'We can support your cake line project.',
            created_at: now,
            message_language: 'en',
            translated_text: 'We can support your cake line project.',
            intent_code: 'product_consulting',
            contains_contact_info: 0,
            extracted_entities_json: {
              sources: [
                {
                  title: 'Cake Line Specs',
                  source_type: 'product',
                  source_id: 7,
                  url: '/products/7',
                },
              ],
            },
          },
        ],
        snapshots: [
          {
            snapshot_version: 2,
            contact_name: 'Daniel',
            company_name: 'Daniel Foods',
            email: 'daniel@example.com',
            phone: '',
            whatsapp: '',
            country_code: 'AE',
            product_interest: 'Cake line',
            solution_interest: 'Cake production line',
            requirement_summary: 'Need quote',
            confidence_score: 88,
            created_at: now,
          },
        ],
      },
    ], null, 2), 'utf8');

    fs.writeFileSync(inquiriesPath, JSON.stringify([
      {
        id: 31,
        session_id: 21,
        source: 'ai',
        primary_contact_type: 'email',
        primary_contact_value: 'daniel@example.com',
        customer_name: 'Daniel',
        company_name: 'Daniel Foods',
        country_code: 'AE',
        language_code: 'en',
        product_interest: 'Cake line',
        solution_interest: 'Cake production line',
        requirement_summary: 'Need quote',
        inquiry_score: 88,
        status: 'new',
        assigned_to: null,
        first_response_at: null,
        browse_traces: [],
        change_logs: [],
        follow_ups: [],
        created_at: now,
        updated_at: now,
      },
    ], null, 2), 'utf8');

    fs.writeFileSync(visitorEventsPath, JSON.stringify({
      'sess-ai-detail': [
        {
          page: '/en/products/cake-line',
          title: 'Cake Line',
          referrer: 'https://example.com',
          visited_at: now,
          language_code: 'en',
        },
      ],
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

      $service = new app\\service\\inquiry\\ConversationService();
      echo json_encode($service->detail(21), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

    if (String(payload.summary?.session_code || '') !== 'sess-ai-detail') {
      issues.push('ConversationService::detail must expose session summary');
    }
    if (!Array.isArray(payload.chat_messages) || String(payload.chat_messages[0]?.extracted_entities_json?.company_name || '') !== 'Daniel Foods') {
      issues.push('ConversationService::detail must expose message extracted entities');
    }
    if (!Array.isArray(payload.chat_messages?.[1]?.sources) || String(payload.chat_messages[1].sources[0]?.title || '') !== 'Cake Line Specs') {
      issues.push('ConversationService::detail must expose normalized assistant sources from extracted_entities_json');
    }
    if (!Array.isArray(payload.snapshots) || Number(payload.snapshots[0]?.snapshot_version || 0) !== 2) {
      issues.push('ConversationService::detail must expose snapshot_version');
    }
    if (!Array.isArray(payload.browse_traces) || String(payload.browse_traces[0]?.title || '') !== 'Cake Line') {
      issues.push('ConversationService::detail must expose full browse trace metadata');
    }
    if (Number(payload.inquiry_summary?.id || 0) !== 31) {
      issues.push('ConversationService::detail must expose linked inquiry summary');
    }

    if (issues.length > 0) {
      console.error('AI conversation detail runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('AI conversation detail runtime validation passed.');
  } finally {
    restore(conversationsPath, conversationBackup);
    restore(inquiriesPath, inquiryBackup);
    restore(visitorEventsPath, visitorEventBackup);
  }
}

main();
