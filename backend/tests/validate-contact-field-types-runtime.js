const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const fieldTypesPath = path.join(storageDir, 'contact_field_types.json');
const itemsPath = path.join(storageDir, 'contact_items.json');
const operationLogsPath = path.join(storageDir, 'operation_logs.json');

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

function main() {
  const backups = {
    fieldTypes: backup(fieldTypesPath),
    items: backup(itemsPath),
    logs: backup(operationLogsPath)
  };

  try {
    fs.mkdirSync(storageDir, { recursive: true });
    fs.writeFileSync(fieldTypesPath, JSON.stringify([
      {
        id: 1,
        field_key: 'email',
        name_zh: '邮箱',
        icon: 'mail',
        validation_rule: 'email',
        sort: 100,
        is_enabled: 1
      }
    ], null, 2), 'utf8');
    fs.writeFileSync(itemsPath, JSON.stringify([
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
    ], null, 2), 'utf8');
    fs.writeFileSync(operationLogsPath, JSON.stringify([], null, 2), 'utf8');

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

      $service = new app\\service\\system\\ContactService();
      $before = $service->items();
      $createdType = $service->createFieldType([
        'field_key' => 'line',
        'name_zh' => 'Line',
        'icon' => 'message',
        'validation_rule' => 'text',
        'sort' => 88,
        'is_enabled' => 1,
      ]);
      $service->updateFieldType((int) $createdType['id'], [
        'name_zh' => 'LINE',
        'validation_rule' => 'url',
        'is_enabled' => 1,
      ]);
      $createdItem = $service->create([
        'field_type_id' => (int) $createdType['id'],
        'label_zh' => 'LINE 客服',
        'value' => 'https://line.me/company',
        'description_zh' => 'global line',
        'display_scope' => 'footer',
        'sort' => 9,
        'is_enabled' => 1,
      ]);
      $updatedType = $service->updateFieldType((int) $createdType['id'], [
        'name_zh' => 'LINE',
        'validation_rule' => 'url',
        'is_enabled' => 0,
      ]);

      $deleteInUseError = '';
      try {
        $service->deleteFieldType((int) $createdType['id']);
      } catch (Throwable $exception) {
        $deleteInUseError = $exception->getMessage();
      }

      $deletedFieldType = $service->createFieldType([
        'field_key' => 'viber',
        'name_zh' => 'Viber',
        'icon' => 'message',
        'validation_rule' => 'text',
        'sort' => 77,
        'is_enabled' => 1,
      ]);
      $service->deleteFieldType((int) $deletedFieldType['id']);

      $teamScopeError = '';
      try {
        $service->create([
          'field_type_id' => 1,
          'label_zh' => '非法团队作用域',
          'value' => 'info@example.com',
          'display_scope' => 'team_member',
          'sort' => 1,
          'is_enabled' => 1,
        ]);
      } catch (Throwable $exception) {
        $teamScopeError = $exception->getMessage();
      }

      $invalidValueError = '';
      try {
        $service->create([
          'field_type_id' => 1,
          'label_zh' => '非法邮箱',
          'value' => 'not-an-email',
          'display_scope' => 'footer',
          'sort' => 1,
          'is_enabled' => 1,
        ]);
      } catch (Throwable $exception) {
        $invalidValueError = $exception->getMessage();
      }

      echo json_encode([
        'before' => $before,
        'field_types' => $service->fieldTypes(),
        'created_type' => $createdType,
        'updated_type' => $updatedType,
        'created_item' => $createdItem,
        'delete_in_use_error' => $deleteInUseError,
        'deleted_field_type_id' => (int) $deletedFieldType['id'],
        'team_scope_error' => $teamScopeError,
        'invalid_value_error' => $invalidValueError,
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '0'
      }
    }));

    const issues = [];
    if ((payload.before?.scopes || []).includes('team_member')) {
      issues.push('global contact scopes must not include team_member');
    }
    if (!Array.isArray(payload.field_types) || payload.field_types.length !== 2) {
      issues.push('field type create/update flow did not persist field types');
    }
    if (String(payload.updated_type?.name_zh || '') !== 'LINE') {
      issues.push('updateFieldType() must update name_zh');
    }
    if (String(payload.updated_type?.validation_rule || '') !== 'url') {
      issues.push('updateFieldType() must update validation_rule');
    }
    if (Number(payload.updated_type?.is_enabled || 0) !== 0) {
      issues.push('updateFieldType() must update enabled status');
    }
    if (String(payload.created_item?.display_scope || '') !== 'footer') {
      issues.push('contact item create must preserve allowed display_scope');
    }
    if (!String(payload.delete_in_use_error || '').trim()) {
      issues.push('deleteFieldType() must reject field types that are still referenced by contact items');
    }
    if ((payload.field_types || []).some((item) => Number(item.id || 0) === Number(payload.deleted_field_type_id || 0))) {
      issues.push('deleteFieldType() must remove unused field types');
    }
    if (!String(payload.teamScopeError || payload.team_scope_error || '').trim()) {
      issues.push('contact item create must reject team_member display_scope');
    }
    if (!String(payload.invalidValueError || payload.invalid_value_error || '').trim()) {
      issues.push('contact item create must validate field value by validation_rule');
    }

    if (issues.length > 0) {
      console.error('Contact field type runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Contact field type runtime validation passed.');
  } finally {
    restore(fieldTypesPath, backups.fieldTypes);
    restore(itemsPath, backups.items);
    restore(operationLogsPath, backups.logs);
  }
}

main();
