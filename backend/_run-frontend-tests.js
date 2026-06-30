const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const testDir = 'e:/codex/企业网站/tests';
const files = fs.readdirSync(testDir).filter(f => f.endsWith('.js')).sort();
const results = {pass: [], fail: []};

for (const file of files) {
  try {
    execSync('node ' + path.join(testDir, file), {timeout: 15000, cwd: 'e:/codex/企业网站'});
    results.pass.push(file);
    console.log('PASS: ' + file);
  } catch(e) {
    const errStr = (e.stderr && e.stderr.toString) ? e.stderr.toString().substring(0, 200) : (e.message || '').substring(0, 200);
    results.fail.push({name: file, error: errStr});
    console.log('FAIL: ' + file);
  }
}

console.log('\n=== FRONTEND TEST SUMMARY ===');
console.log('PASS: ' + results.pass.length + ' / FAIL: ' + results.fail.length);
results.fail.forEach(f => console.log('  ' + f.name + ': ' + f.error.substring(0, 80)));
