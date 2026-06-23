const fs = require('fs');
const path = require('path');

const backendRoot = path.resolve(__dirname, '..');
const schemaFile = path.join(backendRoot, 'database', 'sql', '001_init_schema.sql');
const smokeFile = path.join(backendRoot, 'tools', 'smoke-test.ps1');
const ensureFile = path.join(backendRoot, 'tools', 'ensure-smoke-fixtures.ps1');

function read(filePath) {
  return fs.readFileSync(filePath, 'utf8');
}

function assert(condition, message, issues) {
  if (!condition) {
    issues.push(message);
  }
}

function main() {
  const issues = [];
  const schema = read(schemaFile);
  const smoke = read(smokeFile);

  assert(schema.includes('INSERT INTO `media_assets`'), 'schema 缺少 media_assets seed', issues);
  assert(!schema.includes('INSERT INTO `chat_sessions`'), 'schema 不应包含 chat_sessions 演示 seed，它应仅存在于 ensure-smoke-fixtures.ps1', issues);
  assert(!schema.includes('INSERT INTO `chat_messages`'), 'schema 不应包含 chat_messages 演示 seed，它应仅存在于 ensure-smoke-fixtures.ps1', issues);
  assert(!schema.includes('INSERT INTO `lead_snapshots`'), 'schema 不应包含 lead_snapshots 演示 seed，它应仅存在于 ensure-smoke-fixtures.ps1', issues);
  assert(!schema.includes('INSERT INTO `inquiries`'), 'schema 不应包含 inquiries 演示 seed，它应仅存在于 ensure-smoke-fixtures.ps1', issues);
  assert(schema.includes('6000 pcs/h') && schema.includes(', 2,') && schema.includes(', \'published\','), 'schema 未将 solution manual 对齐到 seed asset id', issues);
  assert(schema.includes(", 4, 'amy.zhang@hanzunmachinery.com'"), 'schema 未将 team avatar 对齐到 seed asset id', issues);
  assert(schema.includes(", 3, 'published', 'completed', 'generated', 1, 100)"), 'schema 未将 certificate image 对齐到 seed asset id', issues);

  assert(fs.existsSync(ensureFile), '缺少 smoke fixture helper 脚本 backend/tools/ensure-smoke-fixtures.ps1', issues);
  if (fs.existsSync(ensureFile)) {
    const ensure = read(ensureFile);
    assert(ensure.includes('900001'), 'smoke fixture helper 缺少固定 fixture id', issues);
    assert(/INSERT INTO\s+`?media_assets`?/i.test(ensure), 'smoke fixture helper 未 upsert media_assets', issues);
    assert(/INSERT INTO\s+`?chat_sessions`?/i.test(ensure), 'smoke fixture helper 未 upsert chat_sessions', issues);
    assert(/INSERT INTO\s+`?inquiries`?/i.test(ensure), 'smoke fixture helper 未 upsert inquiries', issues);
  }

  assert(smoke.includes('ensure-smoke-fixtures.ps1'), 'smoke-test 未调用 ensure-smoke-fixtures.ps1', issues);
  assert(smoke.includes('$fixtureMediaId = 900001'), 'smoke-test 未固定命中 media fixture id', issues);
  assert(smoke.includes('$fixtureInquiryId = 900001'), 'smoke-test 未固定命中 inquiry fixture id', issues);
  assert(smoke.includes('/admin/media/assets/$fixtureMediaId'), 'smoke-test 未直接命中 media fixture detail', issues);
  assert(smoke.includes('/admin/inquiries/$fixtureInquiryId'), 'smoke-test 未直接命中 inquiry fixture detail', issues);

  if (issues.length > 0) {
    console.error('Smoke fixture validation failed:');
    issues.forEach((issue) => console.error('- ' + issue));
    process.exit(1);
  }

  console.log('Smoke fixture validation passed.');
}

main();
