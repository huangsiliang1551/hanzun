const fs = require('fs');
const path = require('path');

const workspaceRoot = path.resolve(__dirname, '..', '..');
const templateFile = path.join(workspaceRoot, 'index.template.html');
const jsFile = path.join(workspaceRoot, 'assets', 'js', 'future.js');
const cssFile = path.join(workspaceRoot, 'assets', 'css', 'future.css');

function read(filePath) {
  return fs.existsSync(filePath) ? fs.readFileSync(filePath, 'utf8') : '';
}

function expect(pattern, content, message, issues) {
  if (!pattern.test(content)) {
    issues.push(message);
  }
}

function main() {
  const template = read(templateFile);
  const js = read(jsFile);
  const css = read(cssFile);
  const issues = [];

  expect(/data-support-status/, template, 'support panel template must expose a status node for composer state', issues);
  expect(/data-support-submit/, template, 'support panel template must expose a submit button hook', issues);
  expect(/data-support-submit-label/, template, 'support panel template must expose a submit label hook', issues);
  expect(/\/api\/ai\/session/, js, 'frontend support chat must hydrate history from /api/ai/session', issues);
  expect(/retryLastSupportMessage/, js, 'frontend support chat must expose a retry path for failed sends', issues);
  expect(/restoreSupportComposerFocus/, js, 'frontend support chat must restore focus after send attempts', issues);
  expect(/supportInput\.disabled\s*=\s*isBusy/, js, 'frontend support chat must disable the composer input while sending', issues);
  expect(/supportSubmitButton\.disabled\s*=\s*isBusy/, js, 'frontend support chat must disable the submit button while sending', issues);
  expect(/Enter a question before sending\.|请输入问题后再发送。/, js, 'frontend support chat must block empty input before sending', issues);
  expect(/support-message-system-status/, js, 'frontend support chat must render system-status messages inside the chat UI', issues);
  expect(/function formatSupportMessageTime\(value\)/, js, 'frontend support chat must format message timestamps for hydrated history', issues);
  expect(/created_at/, js, 'frontend support chat must pass created_at metadata through the history renderer', issues);
  expect(/support-source-list/, css, 'frontend support styles must include inline knowledge source presentation', issues);
  expect(/support-message-meta/, css, 'frontend support styles must include message meta rows for timestamps', issues);
  expect(/function localizeSupportSourceType\(value\)/, js, 'frontend support chat must localize source type labels before rendering', issues);
  expect(/function publicApiUrl\(path\)/, js, 'frontend support chat must centralize public API URL resolution', issues);
  expect(/location\.port\s*\|\|\s*""\)\s*===\s*"8091"/, js, 'frontend public API resolver must detect local static preview port 8091', issues);
  expect(/location\.protocol}\s*\/\/\$\{location\.hostname}:8080/, js, 'frontend public API resolver must target port 8080 for local preview API calls', issues);

  if (/sourceType\.textContent\s*=\s*item\.sourceType;/.test(js)) {
    issues.push('frontend support chat must not render raw source_type values directly');
  }

  if (/translated_text/.test(js)) {
    issues.push('frontend support chat must not render translated_text on the public site');
  }

  if (issues.length > 0) {
    console.error('Public chat frontend contract validation failed:');
    issues.forEach((issue) => console.error(`- ${issue}`));
    process.exit(1);
  }

  console.log('Public chat frontend contract validation passed.');
}

main();
