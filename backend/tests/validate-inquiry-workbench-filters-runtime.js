const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const conversationsPath = path.join(storageDir, 'conversations.json');
const inquiriesPath = path.join(storageDir, 'inquiries.json');
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
    echo json_encode([
      'status_filtered' => $service->workbench(['status' => 'new']),
      'country_filtered' => $service->workbench(['country_code' => 'BR']),
      'language_filtered' => $service->workbench(['language_code' => 'fr']),
      'archive_filtered' => $service->workbench(['archive_status' => 'archived']),
      'date_filtered' => $service->workbench(['date_from' => '2026-06-11', 'date_to' => '2026-06-11']),
      'source_filtered' => $service->workbench(['source' => 'ai']),
      'record_type_filtered' => $service->workbench(['record_type' => 'conversation']),
      'paged' => $service->workbench(['record_type' => 'inquiry', 'page' => 2, 'page_size' => 1]),
      'requirement_keyword_filtered' => $service->workbench(['keyword' => 'Need bread line']),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function ids(items) {
  return (Array.isArray(items) ? items : []).map((item) => String(item.workbench_id || ''));
}

function main() {
  const backups = {
    conversations: backup(conversationsPath),
    inquiries: backup(inquiriesPath)
  };

  try {
    fs.mkdirSync(storageDir, { recursive: true });

    writeJson(conversationsPath, [
      {
        id: 501,
        session_id: 501,
        session_code: 'sess-501',
        source: 'ai',
        source_page: '/en/products/cake-line',
        entry_language: 'en',
        resolved_language: 'en',
        country_code: 'MX',
        device_type: 'desktop',
        utm_source: 'seo',
        is_valid_conversation: 1,
        inquiry_id: 601,
        archive_status: 'active',
        last_message_at: '2026-06-11 09:20:00',
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:20:00',
        messages: [],
        snapshots: []
      },
      {
        id: 502,
        session_id: 502,
        session_code: 'sess-502',
        source: 'ai',
        source_page: '/fr/solutions/bread-line',
        entry_language: 'fr',
        resolved_language: 'fr',
        country_code: 'BR',
        device_type: 'mobile',
        utm_source: 'ads',
        is_valid_conversation: 1,
        inquiry_id: 0,
        archive_status: 'archived',
        last_message_at: '2026-06-11 11:20:00',
        created_at: '2026-06-11 11:00:00',
        updated_at: '2026-06-11 11:20:00',
        messages: [],
        snapshots: []
      }
    ]);

    writeJson(inquiriesPath, [
      {
        id: 601,
        session_id: 501,
        source: 'ai',
        primary_contact_type: 'email',
        primary_contact_value: 'mx@example.com',
        customer_name: 'MX Lead',
        company_name: 'MX Foods',
        country_code: 'MX',
        language_code: 'en',
        product_interest: 'Cake line',
        solution_interest: 'Cake turnkey',
        requirement_summary: 'Need cake line',
        inquiry_score: 88,
        status: 'quoting',
        archive_status: 'active',
        assigned_to: null,
        first_response_at: '2026-06-11 09:30:00',
        browse_traces: [],
        change_logs: [],
        follow_ups: [],
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:30:00'
      },
      {
        id: 602,
        session_id: 0,
        source: 'ai',
        primary_contact_type: 'email',
        primary_contact_value: 'br@example.com',
        customer_name: 'BR Lead',
        company_name: 'BR Foods',
        country_code: 'BR',
        language_code: 'fr',
        product_interest: 'Bread line',
        solution_interest: 'Bread turnkey',
        requirement_summary: 'Need bread line',
        inquiry_score: 75,
        status: 'new',
        archive_status: 'archived',
        assigned_to: null,
        first_response_at: null,
        browse_traces: [],
        change_logs: [],
        follow_ups: [],
        created_at: '2026-06-11 11:00:00',
        updated_at: '2026-06-11 11:30:00'
      }
    ]);

    const routeContent = fs.readFileSync(routePath, 'utf8');
    const controllerContent = fs.readFileSync(controllerPath, 'utf8');
    const serviceContent = fs.readFileSync(servicePath, 'utf8');
    const staticIssues = [];

    if (!/\/admin\/inquiry-workbench/.test(routeContent)) {
      staticIssues.push('missing inquiry workbench route');
    }
    if (!/function\s+workbench\s*\(/.test(controllerContent)) {
      staticIssues.push('InquiryController must expose workbench()');
    }
    if (!/function\s+workbench\s*\(array \$query = \[\]\)/.test(serviceContent)) {
      staticIssues.push('InquiryService::workbench must accept query filters');
    }

    if (staticIssues.length > 0) {
      console.error('Inquiry workbench filter static validation failed:');
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
    const issues = [];

    if (ids(payload.status_filtered?.items).join(',') !== 'inquiry:602') {
      issues.push('workbench status filter must keep only new inquiry records');
    }
    if (ids(payload.country_filtered?.items).sort().join(',') !== 'conversation:502,inquiry:602') {
      issues.push('workbench country filter must match both inquiries and conversations');
    }
    if (ids(payload.language_filtered?.items).sort().join(',') !== 'conversation:502,inquiry:602') {
      issues.push('workbench language filter must match resolved/entry language');
    }
    if (ids(payload.archive_filtered?.items).sort().join(',') !== 'conversation:502,inquiry:602') {
      issues.push('workbench archive filter must keep archived records only');
    }
    if (ids(payload.source_filtered?.items).length !== 3) {
      issues.push('workbench source filter must keep AI records');
    }
    if (ids(payload.date_filtered?.items).length !== 3) {
      issues.push('workbench date range filter must include records within selected day');
    }
    if (ids(payload.record_type_filtered?.items).join(',') !== 'conversation:502') {
      issues.push('workbench record_type filter must isolate conversation records');
    }
    if (String(payload.paged?.items?.[0]?.workbench_id || '') !== 'inquiry:601') {
      issues.push('workbench must support page/page_size pagination after filtering');
    }
    if (Number(payload.paged?.pagination?.page || 0) !== 2) {
      issues.push('workbench pagination must return current page');
    }
    if (Number(payload.paged?.pagination?.page_size || 0) !== 1) {
      issues.push('workbench pagination must return current page size');
    }
    if (Number(payload.paged?.pagination?.total || 0) !== 2) {
      issues.push('workbench pagination total must reflect filtered inquiry records');
    }
    if (Number(payload.paged?.pagination?.total_pages || 0) !== 2) {
      issues.push('workbench pagination total_pages must be calculated from filtered records');
    }
    if (ids(payload.requirement_keyword_filtered?.items).join(',') !== 'inquiry:602') {
      issues.push('workbench keyword filter must match requirement_summary content');
    }

    if (issues.length > 0) {
      console.error('Inquiry workbench filter runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Inquiry workbench filter runtime validation passed.');
  } finally {
    restore(conversationsPath, backups.conversations);
    restore(inquiriesPath, backups.inquiries);
  }
}

main();
