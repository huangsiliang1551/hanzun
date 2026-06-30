const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const conversationsPath = path.join(storageDir, 'conversations.json');
const inquiriesPath = path.join(storageDir, 'inquiries.json');

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

  try {
    fs.mkdirSync(storageDir, { recursive: true });

    const now = new Date();
    const nowText = now.toISOString().slice(0, 19).replace('T', ' ');
    fs.writeFileSync(conversationsPath, JSON.stringify([
      {
        id: 1,
        session_id: 1,
        session_code: 'sess-a',
        country_code: 'AE',
        is_valid_conversation: 1,
        inquiry_id: 1,
        created_at: nowText,
        updated_at: nowText,
        messages: [
          { intent_code: 'lead_capture', created_at: nowText },
          { intent_code: 'product_consulting', created_at: nowText }
        ]
      },
      {
        id: 2,
        session_id: 2,
        session_code: 'sess-b',
        country_code: 'DE',
        is_valid_conversation: 0,
        inquiry_id: null,
        created_at: nowText,
        updated_at: nowText,
        messages: [
          { intent_code: 'general_inquiry', created_at: nowText }
        ]
      }
    ], null, 2), 'utf8');

    fs.writeFileSync(inquiriesPath, JSON.stringify([
      {
        id: 1,
        session_id: 1,
        country_code: 'AE',
        status: 'new',
        created_at: nowText,
        updated_at: nowText,
        first_response_at: null
      }
    ], null, 2), 'utf8');

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
      $repo = new app\\repository\\DashboardRepository();
      echo json_encode([
          'aiSummary' => $repo->aiSummary('7d'),
          'aiCountries' => $repo->aiCountrySummary('7d'),
          'aiTopics' => $repo->aiTopicSummary('7d'),
          'inquirySummary' => $repo->inquirySummary('7d'),
          'inquiryCountries' => $repo->inquiryCountrySummary('7d')
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const output = execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8',
    });

    const payload = JSON.parse(output);
    const issues = [];

    if (Number(payload.aiSummary.total_sessions) !== 2) {
      issues.push(`aiSummary.total_sessions expected 2, got ${payload.aiSummary.total_sessions}`);
    }
    if (Number(payload.aiSummary.valid_sessions) !== 1) {
      issues.push(`aiSummary.valid_sessions expected 1, got ${payload.aiSummary.valid_sessions}`);
    }
    if (Number(payload.aiSummary.created_inquiries) !== 1) {
      issues.push(`aiSummary.created_inquiries expected 1, got ${payload.aiSummary.created_inquiries}`);
    }
    if (!Array.isArray(payload.aiCountries) || String(payload.aiCountries[0]?.country_code || '') !== 'AE') {
      issues.push('aiCountrySummary did not read conversation country data');
    }
    if (!Array.isArray(payload.aiTopics) || !payload.aiTopics.some((item) => item.intent_code === 'lead_capture')) {
      issues.push('aiTopicSummary did not aggregate message intent data');
    }
    if (!Array.isArray(payload.inquirySummary) || Number(payload.inquirySummary[0]?.total_count || 0) !== 1) {
      issues.push('inquirySummary did not read inquiry runtime data');
    }
    if (!Array.isArray(payload.inquiryCountries) || String(payload.inquiryCountries[0]?.country_code || '') !== 'AE') {
      issues.push('inquiryCountrySummary did not read inquiry country data');
    }

    if (issues.length > 0) {
      console.error('Dashboard live runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Dashboard live runtime validation passed.');
  } finally {
    restore(conversationsPath, conversationBackup);
    restore(inquiriesPath, inquiryBackup);
  }
}

main();
