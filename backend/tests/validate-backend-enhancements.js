const fs = require('fs');
const path = require('path');

const workspaceRoot = path.resolve(__dirname, '..');

function read(file) {
  return fs.readFileSync(path.join(workspaceRoot, file), 'utf8');
}

function expect(content, pattern, label, issues) {
  if (!pattern.test(content)) {
    issues.push(label);
  }
}

function main() {
  const issues = [];

  const routes = read('route/adminapi.php');
  expect(
    routes,
    /\['POST',\s*'\/admin\/inquiries\/\{id\}\/follow-ups',\s*'app\\\\adminapi\\\\controller\\\\inquiry\\\\InquiryController@addFollowUp'/,
    'missing follow-up mutation route',
    issues
  );
  expect(
    routes,
    /\['GET',\s*'\/admin\/contact-center\/field-types',\s*'app\\\\adminapi\\\\controller\\\\system\\\\ContactController@fieldTypes'/,
    'missing contact field types route',
    issues
  );

  const inquiryController = read('app/adminapi/controller/inquiry/InquiryController.php');
  expect(inquiryController, /function\s+addFollowUp\s*\(/, 'InquiryController must expose addFollowUp()', issues);

  const inquiryService = read('app/service/inquiry/InquiryService.php');
  expect(inquiryService, /function\s+addFollowUp\s*\(/, 'InquiryService must implement addFollowUp()', issues);
  expect(
    inquiryService,
    /'utm_source'[\s\S]*'last_message_at'[\s\S]*'message_count'[\s\S]*'snapshot_count'/,
    'Inquiry detail contract must expose session enrichment fields',
    issues
  );
  expect(
    inquiryService,
    /normalizeRows\(\$conversation\['messages'\]\s*\?\?\s*\[\],\s*\['role', 'content', 'created_at', 'message_language', 'translated_text', 'intent_code', 'contains_contact_info', 'extracted_entities_json'\]\)/,
    'Inquiry detail contract must expose enriched chat message fields',
    issues
  );
  expect(
    inquiryService,
    /normalizeRows\(\$conversation\['snapshots'\]\s*\?\?\s*\[\],\s*\['contact_name', 'company_name', 'email', 'phone', 'whatsapp', 'country_code', 'product_interest', 'solution_interest', 'requirement_summary', 'confidence_score', 'created_at'\]\)/,
    'Inquiry detail contract must expose enriched lead snapshot fields',
    issues
  );

  const inquiryRepository = read('app/repository/InquiryRepository.php');
  expect(inquiryRepository, /function\s+appendFollowUp\s*\(/, 'InquiryRepository must persist appended follow-ups', issues);
  expect(
    inquiryRepository,
    /utm_source[\s\S]*last_message_at[\s\S]*COUNT\(\*\)\s+AS\s+message_count[\s\S]*COUNT\(\*\)\s+AS\s+snapshot_count/s,
    'InquiryRepository must query conversation enrichment and message/snapshot counts',
    issues
  );

  const dashboardController = read('app/adminapi/controller/dashboard/DashboardController.php');
  expect(
    dashboardController,
    /function\s+traffic\s*\(\s*Request\s+\$request\s*\)/,
    'DashboardController::traffic must accept Request for range query',
    issues
  );
  expect(
    dashboardController,
    /function\s+aiConversations\s*\(\s*Request\s+\$request\s*\)/,
    'DashboardController::aiConversations must accept Request for range query',
    issues
  );
  expect(
    dashboardController,
    /function\s+inquiries\s*\(\s*Request\s+\$request\s*\)/,
    'DashboardController::inquiries must accept Request for range query',
    issues
  );

  const dashboardService = read('app/service/dashboard/DashboardService.php');
  expect(
    dashboardService,
    /function\s+traffic\s*\(\s*string\s+\$range\s*=\s*'7d'/,
    'DashboardService::traffic must support range input',
    issues
  );
  expect(
    dashboardService,
    /'series'[\s\S]*'countries'[\s\S]*'top_pages'/,
    'Dashboard traffic payload must include series/countries/top_pages',
    issues
  );
  expect(
    dashboardService,
    /function\s+aiConversations\s*\(\s*string\s+\$range\s*=\s*'7d'/,
    'DashboardService::aiConversations must support range input',
    issues
  );
  expect(
    dashboardService,
    /'series'[\s\S]*'topics'[\s\S]*'countries'/,
    'Dashboard AI payload must include series/topics/countries',
    issues
  );
  expect(
    dashboardService,
    /function\s+inquiries\s*\(\s*string\s+\$range\s*=\s*'7d'/,
    'DashboardService::inquiries must support range input',
    issues
  );
  expect(
    dashboardService,
    /'series'[\s\S]*'countries'[\s\S]*'avg_first_response_minutes'/,
    'Dashboard inquiry payload must include series/countries/avg_first_response_minutes',
    issues
  );

  const dashboardRepository = read('app/repository/DashboardRepository.php');
  expect(dashboardRepository, /function\s+normalizeRangeDays\s*\(/, 'DashboardRepository must normalize range days', issues);
  expect(dashboardRepository, /function\s+trafficSeries\s*\(/, 'DashboardRepository must implement trafficSeries()', issues);
  expect(dashboardRepository, /function\s+aiTopicSummary\s*\(/, 'DashboardRepository must implement aiTopicSummary()', issues);
  expect(dashboardRepository, /function\s+inquirySeries\s*\(/, 'DashboardRepository must implement inquirySeries()', issues);

  const contactController = read('app/adminapi/controller/system/ContactController.php');
  expect(contactController, /function\s+fieldTypes\s*\(/, 'ContactController must expose fieldTypes()', issues);

  const contactService = read('app/service/system/ContactService.php');
  expect(
    contactService,
    /'field_types'\s*=>\s*\$this->contactRepository->listFieldTypes\(\)/,
    'ContactService items() must return field_types',
    issues
  );

  const contactRepository = read('app/repository/ContactRepository.php');
  expect(contactRepository, /function\s+listFieldTypes\s*\(/, 'ContactRepository must expose listFieldTypes()', issues);

  const settingService = read('app/service/system/SettingService.php');
  expect(
    settingService,
    /rolesForUser\(\$userId\)|rolesForUser\(\(int\)\s*\(\$user\['id'\]\s*\?\?\s*0\)\)/,
    'SettingService accountProfile() must resolve role names dynamically',
    issues
  );
  expect(
    settingService,
    /'email'\s*=>\s*\$(?:user|profile)\['email'\]\s*\?\?\s*''[\s\S]*'mobile'[\s\S]*'last_login_at'[\s\S]*'last_login_ip'/,
    'SettingService accountProfile() must expose richer account fields',
    issues
  );

  if (issues.length > 0) {
    console.error('Backend enhancement validation failed:');
    for (const issue of issues) {
      console.error(`- ${issue}`);
    }
    process.exit(1);
  }

  console.log('Backend enhancement validation passed.');
}

main();
