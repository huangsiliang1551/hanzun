const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const conversationsPath = path.join(storageDir, 'conversations.json');
const inquiriesPath = path.join(storageDir, 'inquiries.json');
const visitorEventsPath = path.join(storageDir, 'visitor_events.json');
const rateLimitDir = path.join(storageDir, 'rate_limits');

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

function backupDir(dirPath) {
  if (!fs.existsSync(dirPath)) {
    return null;
  }

  return fs.readdirSync(dirPath).map((name) => ({
    name,
    content: fs.readFileSync(path.join(dirPath, name)),
  }));
}

function restoreDir(dirPath, snapshot) {
  if (fs.existsSync(dirPath)) {
    fs.rmSync(dirPath, { recursive: true, force: true });
  }

  if (snapshot === null) {
    return;
  }

  fs.mkdirSync(dirPath, { recursive: true });
  snapshot.forEach((item) => {
    fs.writeFileSync(path.join(dirPath, item.name), item.content);
  });
}

function buildSessionCode(clientId) {
  return `web-${crypto.createHash('sha1').update(clientId).digest('hex').slice(0, 8)}-sessiondemo001`;
}

function buildPhpScript(sessionCode, clientId, otherClientId) {
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
    $service = new app\\service\\inquiry\\PublicChatService();

    $dispatch = static function (array $body) use ($service): array {
        try {
            return ['ok' => true, 'response' => ['code' => 0, 'data' => $service->session($body)]];
        } catch (app\\common\\exception\\BusinessException $exception) {
            return [
                'ok' => false,
                'code' => $exception->getErrorCode(),
                'message' => $exception->getMessage(),
            ];
        }
    };

    echo json_encode([
        'success' => $dispatch([
            'client_id' => '${clientId}',
            'session_code' => '${sessionCode}',
        ]),
        'wrong_client' => $dispatch([
            'client_id' => '${otherClientId}',
            'session_code' => '${sessionCode}',
        ]),
        'missing_params' => $dispatch([
            'client_id' => '',
            'session_code' => '',
        ]),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const conversationBackup = backup(conversationsPath);
  const inquiryBackup = backup(inquiriesPath);
  const visitorEventBackup = backup(visitorEventsPath);
  const rateLimitBackup = backupDir(rateLimitDir);

  try {
    fs.mkdirSync(storageDir, { recursive: true });

    const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const clientId = 'client-session-001';
    const otherClientId = 'client-session-002';
    const sessionCode = buildSessionCode(clientId);
    restoreDir(rateLimitDir, null);

    fs.writeFileSync(conversationsPath, JSON.stringify([
      {
        id: 34,
        session_id: 34,
        session_code: sessionCode,
        source: 'ai',
        source_page: '/en/solutions/cake-line',
        entry_language: 'en',
        resolved_language: 'en',
        country_code: 'DE',
        device_type: 'desktop',
        utm_source: 'google',
        is_valid_conversation: 1,
        inquiry_id: 52,
        last_message_at: now,
        created_at: now,
        updated_at: now,
        messages: [
          {
            role: 'user',
            content: 'Need a cake line quotation',
            created_at: now,
            message_language: 'en',
            translated_text: 'Need cake line quotation',
            intent_code: 'quotation',
            contains_contact_info: 1,
            extracted_entities_json: { email: 'daniel@example.com' },
          },
          {
            role: 'assistant',
            content: 'We can support your cake line project.',
            created_at: now,
            message_language: 'en',
            translated_text: 'We can support your cake line project.',
            intent_code: 'quotation',
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
            snapshot_version: 1,
            contact_name: 'Daniel',
            company_name: 'Daniel Foods',
            email: 'daniel@example.com',
            phone: '',
            whatsapp: '',
            country_code: 'DE',
            product_interest: 'Cake line',
            solution_interest: 'Cake production line',
            requirement_summary: 'Need quotation and layout plan',
            confidence_score: 88,
            created_at: now,
          },
        ],
      },
    ], null, 2), 'utf8');

    fs.writeFileSync(inquiriesPath, JSON.stringify([
      {
        id: 52,
        session_id: 34,
        source: 'ai',
        primary_contact_type: 'email',
        primary_contact_value: 'daniel@example.com',
        customer_name: 'Daniel',
        company_name: 'Daniel Foods',
        country_code: 'DE',
        language_code: 'en',
        product_interest: 'Cake line',
        solution_interest: 'Cake production line',
        requirement_summary: 'Need quotation and layout plan',
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
      [sessionCode]: [
        {
          page: '/en/solutions/cake-line',
          title: 'Cake Line',
          referrer: 'https://example.com',
          visited_at: now,
          language_code: 'en',
        },
      ],
    }, null, 2), 'utf8');

    const output = execFileSync('php', ['-r', buildPhpScript(sessionCode, clientId, otherClientId)], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '1',
      },
    });

    const payload = JSON.parse(output);
    const issues = [];

    if (!payload.success?.ok || Number(payload.success?.response?.code ?? -1) !== 0) {
      issues.push('PublicChatService::session must return a successful response for the owning client');
    }

    const successData = payload.success?.response?.data || {};
    if (String(successData.session_code || '') !== sessionCode) {
      issues.push('public session response must expose the matched session_code');
    }
    if (Number(successData.inquiry_id || 0) !== 52) {
      issues.push('public session response must expose the linked inquiry_id');
    }
    if (String(successData.lead_snapshot?.company_name || '') !== 'Daniel Foods') {
      issues.push('public session response must expose the latest lead snapshot');
    }
    if (!Array.isArray(successData.messages) || successData.messages.length !== 2) {
      issues.push('public session response must hydrate the full message history');
    }

    const assistantMessage = Array.isArray(successData.messages)
      ? successData.messages.find((item) => item && item.role === 'assistant')
      : null;
    if (!Array.isArray(assistantMessage?.sources) || String(assistantMessage.sources[0]?.title || '') !== 'Cake Line Specs') {
      issues.push('public session response must expose assistant knowledge sources from stored metadata');
    }
    if (assistantMessage?.sources?.some((item) => Object.prototype.hasOwnProperty.call(item || {}, 'url'))) {
      issues.push('public session response must not expose internal source URLs to the public frontend');
    }

    if (payload.wrong_client?.ok !== false) {
      issues.push('public session response must reject a mismatched client_id for an existing session');
    }
    if (payload.missing_params?.ok !== false) {
      issues.push('public session response must reject empty client_id and session_code');
    }

    if (issues.length > 0) {
      console.error('Public chat session runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Public chat session runtime validation passed.');
  } finally {
    restore(conversationsPath, conversationBackup);
    restore(inquiriesPath, inquiryBackup);
    restore(visitorEventsPath, visitorEventBackup);
    restoreDir(rateLimitDir, rateLimitBackup);
  }
}

main();
