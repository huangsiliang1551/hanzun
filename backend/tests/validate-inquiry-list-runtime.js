const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  inquiries: path.join(storageDir, 'inquiries.json'),
  conversations: path.join(storageDir, 'conversations.json')
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

function writeJson(filePath, payload) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, JSON.stringify(payload, null, 2), 'utf8');
}

function main() {
  const backups = {};
  Object.entries(files).forEach(([key, filePath]) => {
    backups[key] = backup(filePath);
  });

  try {
    writeJson(files.inquiries, [
      {
        id: 1,
        source: 'ai',
        session_id: 1001,
        primary_contact_type: 'email',
        primary_contact_value: 'daniel@example.com',
        customer_name: 'Daniel Foods',
        company_name: 'Daniel Foods LLC',
        country_code: 'AE',
        language_code: 'en',
        product_interest: 'Cake depositor',
        solution_interest: 'Cake line',
        requirement_summary: 'Need cake production line',
        inquiry_score: 88.5,
        status: 'new',
        assigned_to: null,
        first_response_at: null,
        archive_status: 'active',
        source_page: '/en/products/cake-depositor',
        utm_source: 'organic',
        last_message_at: '2026-06-11 10:00:00',
        created_at: '2026-06-08 09:00:00',
        updated_at: '2026-06-11 10:00:00',
        browse_traces: [],
        change_logs: [],
        follow_ups: []
      },
      {
        id: 2,
        source: 'ai',
        session_id: 1002,
        primary_contact_type: 'phone',
        primary_contact_value: '+971500000000',
        customer_name: 'Bakery MX',
        company_name: 'Bakery MX SA',
        country_code: 'MX',
        language_code: 'es',
        product_interest: 'Bread line',
        solution_interest: 'Bread automatic line',
        requirement_summary: 'Need bread line',
        inquiry_score: 72.1,
        status: 'quoting',
        assigned_to: null,
        first_response_at: '2026-06-10 10:00:00',
        archive_status: 'active',
        source_page: '/es/solutions/bread-line',
        utm_source: 'ads',
        last_message_at: '2026-06-10 14:00:00',
        created_at: '2026-06-10 09:00:00',
        updated_at: '2026-06-10 14:00:00',
        browse_traces: [],
        change_logs: [],
        follow_ups: []
      },
      {
        id: 3,
        source: 'ai',
        session_id: 1003,
        primary_contact_type: 'email',
        primary_contact_value: 'won@example.com',
        customer_name: 'Cake Won',
        company_name: 'Cake Won GmbH',
        country_code: 'DE',
        language_code: 'en',
        product_interest: 'Cake line',
        solution_interest: 'Cake turnkey line',
        requirement_summary: 'Won cake project',
        inquiry_score: 95.3,
        status: 'won',
        assigned_to: 1,
        first_response_at: '2026-06-09 09:00:00',
        archive_status: 'active',
        source_page: '/en/solutions/cake-line',
        utm_source: 'organic',
        last_message_at: '2026-06-09 12:00:00',
        created_at: '2026-06-09 08:00:00',
        updated_at: '2026-06-09 12:00:00',
        browse_traces: [],
        change_logs: [],
        follow_ups: []
      }
    ]);
    writeJson(files.conversations, []);

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

      echo json_encode([
        'filtered_list' => $service->list([
          'status' => 'new',
          'country_code' => 'AE',
          'page' => 1,
          'page_size' => 10,
          'sort_field' => 'updated_at',
          'sort_order' => 'desc',
        ]),
        'stats' => $service->stats([
          'language_code' => 'en',
        ]),
        'export' => $service->export([
          'keyword' => 'cake',
          'sort_field' => 'created_at',
          'sort_order' => 'asc',
        ]),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8'
    }));

    const issues = [];

    if (Number(payload.filtered_list?.pagination?.total || 0) !== 1) {
      issues.push('InquiryService::list must filter by status and country');
    }
    if (Number(payload.filtered_list?.items?.[0]?.id || 0) !== 1) {
      issues.push('InquiryService::list must return the matching inquiry');
    }
    if (Number(payload.filtered_list?.stats?.stale_48h_count || 0) !== 1) {
      issues.push('InquiryService::list stats must count stale 48h inquiries');
    }
    if (Number(payload.stats?.total || 0) !== 2) {
      issues.push('InquiryService::stats must filter by language');
    }
    if (Number(payload.stats?.won_count || 0) !== 1) {
      issues.push('InquiryService::stats must count won inquiries');
    }
    if (Number(payload.stats?.conversion_rate || 0) !== 50) {
      issues.push('InquiryService::stats must calculate conversion rate');
    }
    if (Number(payload.export?.total || 0) !== 2) {
      issues.push('InquiryService::export must filter by keyword');
    }
    if (!String(payload.export?.filename || '').startsWith('inquiries-')) {
      issues.push('InquiryService::export must produce export filename');
    }
    if (!Array.isArray(payload.export?.rows) || Number(payload.export.rows[0]?.id || 0) !== 1 || Number(payload.export.rows[1]?.id || 0) !== 3) {
      issues.push('InquiryService::export must sort exported rows');
    }

    if (issues.length > 0) {
      console.error('Inquiry list runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Inquiry list runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
