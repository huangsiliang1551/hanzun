const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const testDir = path.resolve(__dirname, 'tests');
const storageDir = path.join(__dirname, 'runtime', 'storage');

// === STEP 1: Rebuild database ===
console.log('=== Rebuilding database ===');
const rebuildScript = '<?php\n' +
'require_once __DIR__ . \'/app/common/bootstrap/Autoloader.php\';\n' +
'require_once __DIR__ . \'/app/common/bootstrap/EnvLoader.php\';\n' +
'require_once __DIR__ . \'/app/common/bootstrap/helpers.php\';\n' +
'\\app\\common\\bootstrap\\Autoloader::register(__DIR__);\n' +
'\\app\\common\\bootstrap\\EnvLoader::load(__DIR__ . \'/.env\');\n' +
'\\app\\common\\config\\ConfigRepository::instance()->load(__DIR__ . \'/config\');\n' +
'\\app\\common\\database\\DatabaseManager::instance()->configure(\n' +
'    \\app\\common\\config\\ConfigRepository::instance()->get(\'database.connections.mysql\', [])\n' +
');\n' +
'\n' +
'$pdo = \\app\\common\\database\\DatabaseManager::instance()->connection();\n' +
'if (!$pdo instanceof \\PDO) { die("No DB connection\\n"); }\n' +
'\n' +
'echo "Dropping all tables...\\n";\n' +
'$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");\n' +
'$stmt = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = \'hanzun_cms\'");\n' +
'$tables = $stmt->fetchAll(\\PDO::FETCH_COLUMN);\n' +
'foreach ($tables as $table) { $pdo->exec("DROP TABLE IF EXISTS `$table`"); }\n' +
'$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");\n' +
'echo "All tables dropped.\\n";\n' +
'\n' +
'$sqlDir = __DIR__ . \'/database/sql\';\n' +
'$schemaPaths = glob($sqlDir . \'/*.sql\') ?: [];\n' +
'natsort($schemaPaths);\n' +
'foreach ($schemaPaths as $path) {\n' +
'    $file = basename($path);\n' +
'    if (!file_exists($path)) { echo "WARNING: $file not found, skipping.\\n"; continue; }\n' +
'    echo "Importing $file...\\n";\n' +
'    $sql = file_get_contents($path);\n' +
'    if ($pdo->exec($sql) === false) { $err = $pdo->errorInfo(); echo "ERROR: " . ($err[2] ?? \'unknown\') . "\\n"; exit(1); }\n' +
'}\n' +
'echo "Database schema imported.\\n";\n' +
'require __DIR__ . \'/_seed-test-data.php\';\n' +
'echo "Seed data populated.\\n";\n';

try {
  const rebuildPath = path.join(__dirname, '_rebuild-db.php');
  fs.writeFileSync(rebuildPath, rebuildScript, 'utf8');
  execSync('php _rebuild-db.php', { cwd: __dirname, timeout: 60000, stdio: 'inherit' });
  console.log('Database rebuild complete.\\n');
} catch (e) {
  console.error('Database rebuild failed:', e.message);
  process.exit(1);
}

// === STEP 2: Seed runtime storage ===
// Ensure correct committed runtime seed files exist
const seeds = {
  'contact_field_types.json': JSON.stringify([
    { id: 1, field_key: 'email', name_zh: '邮箱', icon: 'mail', validation_rule: 'email', sort: 100, is_enabled: 1 },
    { id: 2, field_key: 'phone', name_zh: '电话', icon: 'phone', validation_rule: 'text', sort: 90, is_enabled: 1 },
    { id: 3, field_key: 'whatsapp', name_zh: 'WhatsApp', icon: 'whatsapp', validation_rule: 'text', sort: 80, is_enabled: 1 }
  ], null, 2),
  'contact_items.json': JSON.stringify([
    { id: 1, field_type_id: 1, field_key: 'email', field_name: '邮箱', label_zh: '默认邮箱', value: 'hanzunkunshanmachinery@gmail.com', description_zh: '默认联系邮箱', display_scope: 'footer', sort: 100, is_enabled: 1 },
    { id: 2, field_type_id: 2, field_key: 'phone', field_name: '电话', label_zh: '默认电话', value: '+85253441653', description_zh: '默认联系电话', display_scope: 'footer', sort: 90, is_enabled: 1 },
    { id: 3, field_type_id: 3, field_key: 'whatsapp', field_name: 'WhatsApp', label_zh: '默认 WhatsApp', value: '+85253441653', description_zh: '默认联系 WhatsApp', display_scope: 'footer', sort: 80, is_enabled: 1 }
  ], null, 2),
  'team_members.json': JSON.stringify([
    { id: 1, name_zh: 'Amy Zhang', title_zh: '海外销售经理', department_zh: '国际销售部', bio_zh: '负责海外客户需求梳理、方案匹配、报价推进与交付协同。', avatar_asset_id: 4, email: 'amy.zhang@hanzunmachinery.com', phone: '+8615216813602', whatsapp: '+8615216813602', wechat: null, publish_status: 'published', translation_status: 'completed', is_home_featured: 1, manual_sort: 100, created_by: 1, updated_by: 1, created_at: '2026-06-11 10:00:00', updated_at: '2026-06-11 10:00:00' }
  ], null, 2)
};

fs.mkdirSync(storageDir, { recursive: true });
Object.entries(seeds).forEach(([name, content]) => {
  fs.writeFileSync(path.join(storageDir, name), content, 'utf8');
});
// Clean up stale operation_logs.json that might interfere
fs.writeFileSync(path.join(storageDir, 'operation_logs.json'), '[]', 'utf8');

// === STEP 3: Run all tests ===
const files = fs.readdirSync(testDir).filter(f => f.endsWith('.js') && f !== 'fix-db-init.js').sort();

const results = { pass: [], fail: [] };

for (const file of files) {
  const filePath = path.join(testDir, file);
  try {
    const stdout = execSync('node ' + filePath, { timeout: 30000, cwd: __dirname });
    results.pass.push(file);
    console.log('PASS: ' + file);
  } catch (e) {
    const errStr = (e.stderr && e.stderr.toString) ? e.stderr.toString().substring(0, 200) : (e.message || '').substring(0, 200);
    results.fail.push({ name: file, error: errStr });
    console.log('FAIL: ' + file);
    console.log('  ' + errStr);
  }
}

console.log('\n=== SUMMARY ===');
console.log('PASS: ' + results.pass.length);
console.log('FAIL: ' + results.fail.length);
console.log('PASS list:');
results.pass.forEach(f => console.log('  ' + f));
console.log('FAIL list:');
results.fail.forEach(f => console.log('  ' + f.name + ': ' + f.error.substring(0, 80)));

fs.writeFileSync(path.join(__dirname, 'test-results.json'), JSON.stringify(results, null, 2));
