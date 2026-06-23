const fs = require('fs');
const path = require('path');

const backendRoot = path.resolve(__dirname, '..');
const routeFile = path.join(backendRoot, 'route', 'publicapi.php');
const routerFile = path.join(backendRoot, 'app', 'common', 'http', 'Router.php');
const serviceFile = path.join(backendRoot, 'app', 'service', 'inquiry', 'PublicChatService.php');

function read(filePath) {
  return fs.existsSync(filePath) ? fs.readFileSync(filePath, 'utf8') : '';
}

function expect(pattern, content, message, issues) {
  if (!pattern.test(content)) {
    issues.push(message);
  }
}

function main() {
  const routes = read(routeFile);
  const router = read(routerFile);
  const service = read(serviceFile);
  const issues = [];

  expect(
    /\['POST',\s*'\/api\/ai\/session',\s*'app\\\\publicapi\\\\controller\\\\AiChatController@session',\s*null\]/,
    routes,
    'public route file must register POST /api/ai/session',
    issues,
  );
  expect(
    /'POST \/api\/ai\/session'\s*=>\s*\[\['window_seconds'\s*=>\s*60,\s*'max_requests'\s*=>\s*10\]/,
    router,
    'router must apply rate limits to POST /api/ai/session',
    issues,
  );
  expect(
    /public function session\(array \$input\): array/,
    service,
    'PublicChatService must expose a public session(array $input): array method',
    issues,
  );
  expect(
    /'role'\s*=>\s*'assistant'[\s\S]{0,600}'extracted_entities_json'\s*=>\s*\[[\s\S]{0,200}'sources'\s*=>/,
    service,
    'PublicChatService must persist assistant sources into extracted_entities_json when saving assistant replies',
    issues,
  );

  if (issues.length > 0) {
    console.error('Public chat session contract validation failed:');
    issues.forEach((issue) => console.error(`- ${issue}`));
    process.exit(1);
  }

  console.log('Public chat session contract validation passed.');
}

main();
