const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const files = {
  fieldTypes: path.join(storageDir, 'contact_field_types.json'),
  items: path.join(storageDir, 'contact_items.json'),
  logs: path.join(storageDir, 'operation_logs.json'),
  settings: path.join(storageDir, 'system_settings.json')
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

function writeJson(filePath, data) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf8');
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

    app\\common\\http\\RequestContext::setUser([
      'id' => 1,
      'username' => 'admin',
      'nickname' => 'Admin'
    ]);

    $service = new app\\service\\system\\ContactService();
    $before = $service->items();

    $created = $service->create([
      'field_type_id' => 1,
      'label_zh' => '海外售前邮箱',
      'value' => 'global@example.com',
      'description_zh' => '用于海外询盘',
      'display_scope' => 'footer',
      'sort' => 120,
      'is_enabled' => 1,
    ]);

    $updated = $service->update((int) $created['id'], [
      'label_zh' => '海外主邮箱',
      'value' => 'primary@example.com',
      'description_zh' => '作为全局主联系邮箱',
      'display_scope' => 'contact_page',
      'sort' => 80,
      'is_enabled' => 0,
    ]);

    $detail = $service->detail((int) $created['id']);
    $service->delete((int) $created['id']);
    $after = $service->items();

    $deletedDetailError = '';
    try {
      $service->detail((int) $created['id']);
    } catch (Throwable $exception) {
      $deletedDetailError = $exception->getMessage();
    }

    $disabledTypeError = '';
    try {
      $service->update((int) $created['id'], [
        'field_type_id' => 2,
      ]);
    } catch (Throwable $exception) {
      $disabledTypeError = $exception->getMessage();
    }

    echo json_encode([
      'before' => $before,
      'created' => $created,
      'updated' => $updated,
      'detail' => $detail,
      'after' => $after,
      'deleted_detail_error' => $deletedDetailError,
      'disabled_type_error' => $disabledTypeError,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const backups = {};
  Object.entries(files).forEach(([key, filePath]) => {
    backups[key] = backup(filePath);
  });

  try {
    writeJson(files.fieldTypes, [
      {
        id: 1,
        field_key: 'email',
        name_zh: '邮箱',
        icon: 'mail',
        validation_rule: 'email',
        sort: 100,
        is_enabled: 1
      },
      {
        id: 2,
        field_key: 'wechat',
        name_zh: '微信',
        icon: 'wechat',
        validation_rule: 'text',
        sort: 90,
        is_enabled: 0
      }
    ]);
    writeJson(files.items, [
      {
        id: 1,
        field_type_id: 1,
        field_key: 'email',
        field_name: '邮箱',
        label_zh: '默认邮箱',
        value: 'default@example.com',
        description_zh: '默认联系邮箱',
        display_scope: 'footer',
        sort: 100,
        is_enabled: 1
      }
    ]);
    writeJson(files.logs, []);
    writeJson(files.settings, {
      deepseek: {
        config: {
          translation_enabled: 0
        }
      }
    });

    const output = execFileSync('php', ['-r', buildPhpPayload()], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '0'
      }
    });

    const payload = JSON.parse(output);
    const logs = JSON.parse(fs.readFileSync(files.logs, 'utf8'));
    const issues = [];

    if (!Array.isArray(payload.before?.items) || payload.before.items.length !== 1) {
      issues.push('ContactService::items must return existing contact items');
    }
    if (String(payload.created?.field_key || '') !== 'email') {
      issues.push('ContactService::create must hydrate field_key from the field type');
    }
    if (String(payload.created?.field_name || '') !== '邮箱') {
      issues.push('ContactService::create must hydrate field_name from the field type');
    }
    if (String(payload.updated?.label_zh || '') !== '海外主邮箱') {
      issues.push('ContactService::update must persist updated label_zh');
    }
    if (String(payload.updated?.display_scope || '') !== 'contact_page') {
      issues.push('ContactService::update must persist updated display_scope');
    }
    if (Number(payload.updated?.is_enabled || 0) !== 0) {
      issues.push('ContactService::update must persist updated enabled status');
    }
    if (String(payload.detail?.value || '') !== 'primary@example.com') {
      issues.push('ContactService::detail must return the latest updated value');
    }
    const afterItems = Array.isArray(payload.after?.items) ? payload.after.items : [];
    if (afterItems.length !== 1) {
      issues.push('ContactService::delete must remove the created contact item');
    }
    if (String(afterItems[0]?.label_zh || '') !== '默认邮箱') {
      issues.push('ContactService::items must remain sorted by sort desc');
    }
    if (!String(payload.deleted_detail_error || '').trim()) {
      issues.push('ContactService::detail must fail after deleting a contact item');
    }
    if (!String(payload.disabled_type_error || '').trim()) {
      issues.push('ContactService::update must reject disabled contact field types');
    }

    const actionPoints = Array.isArray(logs) ? logs.map((item) => String(item.action_point || '')) : [];
    if (!actionPoints.includes('contact.create')) {
      issues.push('ContactService::create must write contact.create operation log');
    }
    if (!actionPoints.includes('contact.update')) {
      issues.push('ContactService::update must write contact.update operation log');
    }
    if (!actionPoints.includes('contact.delete')) {
      issues.push('ContactService::delete must write contact.delete operation log');
    }

    if (issues.length > 0) {
      console.error('Contact items runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Contact items runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
