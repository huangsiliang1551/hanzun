const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const inquiriesPath = path.join(storageDir, 'inquiries.json');
const routePath = path.join(backendRoot, 'route', 'adminapi.php');
const controllerPath = path.join(backendRoot, 'app', 'adminapi', 'controller', 'inquiry', 'InquiryController.php');
const servicePath = path.join(backendRoot, 'app', 'service', 'inquiry', 'InquiryService.php');
const repositoryPath = path.join(backendRoot, 'app', 'repository', 'InquiryRepository.php');

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

    $service = new app\\service\\inquiry\\InquiryService();
    $updated = $service->update(81, [
      'country_code' => 'MX',
      'product_interest' => 'Cake filling line',
      'solution_interest' => 'Cake turnkey line',
      'assigned_to' => 1,
      'status' => 'quoting'
    ]);

    echo json_encode([
      'updated' => $updated,
      'detail' => $service->detail(81),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const inquiryBackup = backup(inquiriesPath);

  try {
    fs.mkdirSync(storageDir, { recursive: true });

    fs.writeFileSync(inquiriesPath, JSON.stringify([
      {
        id: 81,
        session_id: 301,
        source: 'ai',
        primary_contact_type: 'email',
        primary_contact_value: 'lead@example.com',
        customer_name: 'Daniel Foods',
        company_name: 'Daniel Foods',
        country_code: 'AE',
        language_code: 'en',
        product_interest: 'Cake depositor',
        solution_interest: 'Cake line',
        requirement_summary: 'Need a mid-size line',
        inquiry_score: 86,
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
    ], null, 2), 'utf8');

    const routeContent = fs.readFileSync(routePath, 'utf8');
    const controllerContent = fs.readFileSync(controllerPath, 'utf8');
    const serviceContent = fs.readFileSync(servicePath, 'utf8');
    const repositoryContent = fs.readFileSync(repositoryPath, 'utf8');
    const staticIssues = [];

    if (!/\['PUT',\s*'\/admin\/inquiries\/\{id\}'/.test(routeContent)) {
      staticIssues.push('missing inquiry manual update route');
    }
    if (!/function\s+update\s*\(/.test(controllerContent)) {
      staticIssues.push('InquiryController must expose update()');
    }
    if (!/function\s+update\s*\(/.test(serviceContent)) {
      staticIssues.push('InquiryService must expose update()');
    }
    if (!/function\s+updateInquiry\s*\(/.test(repositoryContent)) {
      staticIssues.push('InquiryRepository must expose updateInquiry()');
    }

    if (staticIssues.length > 0) {
      console.error('Inquiry manual update static validation failed:');
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
    const updated = payload.updated || {};
    const detail = payload.detail || {};
    const summary = detail.summary || {};
    const issues = [];

    if (String(updated.country_code || '') !== 'MX') {
      issues.push('InquiryService::update must persist country_code');
    }
    if (String(updated.product_interest || '') !== 'Cake filling line') {
      issues.push('InquiryService::update must persist product_interest');
    }
    if (String(updated.solution_interest || '') !== 'Cake turnkey line') {
      issues.push('InquiryService::update must persist solution_interest');
    }
    if (Number(updated.assigned_to || 0) !== 1) {
      issues.push('InquiryService::update must persist assigned_to');
    }
    if (String(updated.status || '') !== 'quoting') {
      issues.push('InquiryService::update must persist status');
    }
    if (!String(updated.first_response_at || '').trim()) {
      issues.push('InquiryService::update must set first_response_at when moving into active follow-up status');
    }

    const changeFields = Array.isArray(updated.change_logs) ? updated.change_logs.map((item) => String(item.field || '')) : [];
    ['country_code', 'product_interest', 'solution_interest', 'assigned_to', 'status'].forEach((field) => {
      if (!changeFields.includes(field)) {
        issues.push(`InquiryService::update must append change log for ${field}`);
      }
    });

    if (String(summary.country_code || '') !== 'MX' || Number(summary.assigned_to || 0) !== 1) {
      issues.push('InquiryService::detail must reflect manual correction changes');
    }

    if (issues.length > 0) {
      console.error('Inquiry manual update runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Inquiry manual update runtime validation passed.');
  } finally {
    restore(inquiriesPath, inquiryBackup);
  }
}

main();
