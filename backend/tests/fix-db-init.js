const fs = require('fs');
const path = require('path');

const testDir = __dirname;

const files = fs.readdirSync(testDir).filter(f => f.endsWith('.js') && f !== 'fix-db-init.js');
let fixed = 0;

for (const file of files) {
  const filePath = path.join(testDir, file);
  let content = fs.readFileSync(filePath, 'utf8');

  // Skip if already has ConfigRepository init
  if (content.includes('ConfigRepository::instance()->load')) continue;

  // Skip if no EnvLoader::load (no PHP exec)
  if (!content.includes('EnvLoader::load')) continue;

  // Find the EnvLoader::load line - use literal line ending
  const searchFor = "app\\\\common\\\\bootstrap\\\\EnvLoader::load($basePath . '/.env');";
  const idx = content.indexOf(searchFor);
  if (idx === -1) continue;

  // Build insertion text with proper escaping - use 4 backslashes to represent 2 literal backslashes
  var insertion = "\n" + content.slice(content.lastIndexOf('\n', idx), idx) +
    "app\\\\common\\\\config\\\\ConfigRepository::instance()->load($basePath . '/config');\n" +
    content.slice(content.lastIndexOf('\n', idx), idx) +
    "app\\\\common\\\\database\\\\DatabaseManager::instance()->configure(\n" +
    content.slice(content.lastIndexOf('\n', idx), idx) +
    "    app\\\\common\\\\config\\\\ConfigRepository::instance()->get('database.connections.mysql', [])\n" +
    content.slice(content.lastIndexOf('\n', idx), idx) +
    ");";

  content = content.slice(0, idx + searchFor.length) + insertion + content.slice(idx + searchFor.length);
  fs.writeFileSync(filePath, content, 'utf8');
  fixed++;
  console.log('Fixed: ' + file);
}

console.log('\nDone: ' + fixed + ' files updated.');
