const fs = require('fs');
const path = require('path');

const backendRoot = path.resolve(__dirname, '..');
const repositoryFile = path.join(backendRoot, 'app', 'repository', 'PublicChatRepository.php');

function read(filePath) {
  return fs.existsSync(filePath) ? fs.readFileSync(filePath, 'utf8') : '';
}

function expect(pattern, content, message, issues) {
  if (!pattern.test(content)) {
    issues.push(message);
  }
}

function main() {
  const repository = read(repositoryFile);
  const issues = [];

  expect(/hasTableColumn\(\$pdo,\s*'inquiries',\s*'source_page'\)/, repository, 'PublicChatRepository must guard inquiry source_page writes behind a column check', issues);
  expect(/hasTableColumn\(\$pdo,\s*'inquiries',\s*'utm_source'\)/, repository, 'PublicChatRepository must guard inquiry utm_source writes behind a column check', issues);
  expect(/hasTableColumn\(\$pdo,\s*'inquiries',\s*'last_message_at'\)/, repository, 'PublicChatRepository must guard inquiry last_message_at writes behind a column check', issues);

  if (issues.length > 0) {
    console.error('Public chat DB compatibility contract validation failed:');
    issues.forEach((issue) => console.error(`- ${issue}`));
    process.exit(1);
  }

  console.log('Public chat DB compatibility contract validation passed.');
}

main();
