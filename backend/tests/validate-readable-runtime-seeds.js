const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  contactFieldTypes: path.join(storageDir, 'contact_field_types.json'),
  contactItems: path.join(storageDir, 'contact_items.json'),
  teamMembers: path.join(storageDir, 'team_members.json')
};

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

function assert(condition, message, issues) {
  if (!condition) {
    issues.push(message);
  }
}

function validateCommittedRuntimeFiles(issues) {
  const fieldTypes = JSON.parse(fs.readFileSync(files.contactFieldTypes, 'utf8'));
  const contactItems = JSON.parse(fs.readFileSync(files.contactItems, 'utf8'));
  const teamMembers = JSON.parse(fs.readFileSync(files.teamMembers, 'utf8'));

  assert(fieldTypes.some((item) => item.field_key === 'email' && item.name_zh === '邮箱'), 'runtime 联系方式类型缺少中文“邮箱”', issues);
  assert(fieldTypes.some((item) => item.field_key === 'phone' && item.name_zh === '电话'), 'runtime 联系方式类型缺少中文“电话”', issues);
  assert(fieldTypes.some((item) => item.field_key === 'whatsapp' && item.name_zh === 'WhatsApp'), 'runtime 联系方式类型缺少 “WhatsApp”', issues);

  const emailItem = contactItems.find((item) => item.field_key === 'email');
  const phoneItem = contactItems.find((item) => item.field_key === 'phone');
  const whatsappItem = contactItems.find((item) => item.field_key === 'whatsapp');

  assert(emailItem && emailItem.value === 'hanzunkunshanmachinery@gmail.com', 'runtime 邮箱联系方式仍不是正式默认值', issues);
  assert(phoneItem && phoneItem.value === '+85253441653', 'runtime 电话联系方式仍不是正式默认值', issues);
  assert(whatsappItem && whatsappItem.value === '+85253441653', 'runtime WhatsApp 联系方式仍不是正式默认值', issues);

  const teamMember = teamMembers.find((item) => Number(item.id) === 1);
  assert(teamMember && teamMember.name_zh === 'Amy Zhang', 'runtime 团队成员默认数据未切换为 Amy Zhang', issues);
  assert(teamMember && teamMember.email === 'amy.zhang@hanzunmachinery.com', 'runtime 团队成员邮箱仍是占位值', issues);
  assert(teamMember && teamMember.phone === '+8615216813602', 'runtime 团队成员电话仍是占位值', issues);
  assert(teamMember && teamMember.whatsapp === '+8615216813602', 'runtime 团队成员 WhatsApp 仍是占位值', issues);
}

function validateRepositoryFallbackSeeds(issues) {
  const backups = Object.fromEntries(Object.entries(files).map(([key, filePath]) => [key, backup(filePath)]));

  try {
    Object.values(files).forEach((filePath) => {
      if (fs.existsSync(filePath)) {
        fs.unlinkSync(filePath);
      }
    });

    const phpCode = `
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

      $contactRepository = new app\\repository\\ContactRepository();
      $teamRepository = new app\\repository\\TeamRepository();

      echo json_encode([
        'field_types' => $contactRepository->listFieldTypes(),
        'contact_items' => $contactRepository->list(),
        'team_members' => $teamRepository->list(),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8'
    }));

    const fieldTypes = Array.isArray(payload.field_types) ? payload.field_types : [];
    const contactItems = Array.isArray(payload.contact_items) ? payload.contact_items : [];
    const teamMembers = Array.isArray(payload.team_members) ? payload.team_members : [];

    assert(fieldTypes.some((item) => item.field_key === 'email' && item.name_zh === '邮箱'), 'ContactRepository fallback 缺少“邮箱”', issues);
    assert(fieldTypes.some((item) => item.field_key === 'phone' && item.name_zh === '电话'), 'ContactRepository fallback 缺少“电话”', issues);
    assert(fieldTypes.some((item) => item.field_key === 'whatsapp' && item.name_zh === 'WhatsApp'), 'ContactRepository fallback 缺少 “WhatsApp”', issues);

    const emailItem = contactItems.find((item) => item.field_key === 'email');
    const phoneItem = contactItems.find((item) => item.field_key === 'phone');
    const whatsappItem = contactItems.find((item) => item.field_key === 'whatsapp');

    assert(emailItem && emailItem.value === 'hanzunkunshanmachinery@gmail.com', 'ContactRepository fallback 邮箱默认值错误', issues);
    assert(phoneItem && phoneItem.value === '+85253441653', 'ContactRepository fallback 电话默认值错误', issues);
    assert(whatsappItem && whatsappItem.value === '+85253441653', 'ContactRepository fallback WhatsApp 默认值错误', issues);

    const teamMember = teamMembers.find((item) => Number(item.id) === 1);
    assert(teamMember && teamMember.name_zh === 'Amy Zhang', 'TeamRepository fallback 默认成员不是 Amy Zhang', issues);
    assert(teamMember && teamMember.email === 'amy.zhang@hanzunmachinery.com', 'TeamRepository fallback 默认邮箱错误', issues);
    assert(teamMember && teamMember.phone === '+8615216813602', 'TeamRepository fallback 默认电话错误', issues);
    assert(teamMember && teamMember.whatsapp === '+8615216813602', 'TeamRepository fallback 默认 WhatsApp 错误', issues);
  } finally {
    Object.entries(files).forEach(([key, filePath]) => restore(filePath, backups[key]));
  }
}

function main() {
  const issues = [];

  validateCommittedRuntimeFiles(issues);
  validateRepositoryFallbackSeeds(issues);

  if (issues.length > 0) {
    console.error('Readable runtime seed validation failed:');
    issues.forEach((issue) => console.error(`- ${issue}`));
    process.exit(1);
  }

  console.log('Readable runtime seed validation passed.');
}

main();
