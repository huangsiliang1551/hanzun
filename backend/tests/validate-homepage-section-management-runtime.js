const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  sections: path.join(storageDir, 'homepage_sections.json'),
  settings: path.join(storageDir, 'system_settings.json'),
  operationLogs: path.join(storageDir, 'operation_logs.json'),
  loginLogs: path.join(storageDir, 'login_logs.json'),
  languages: path.join(storageDir, 'languages.json'),
  translationJobs: path.join(storageDir, 'translation_jobs.json'),
  deepseekLogs: path.join(storageDir, 'deepseek_logs.json'),
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

function main() {
  const backups = {};
  Object.entries(files).forEach(([key, filePath]) => {
    backups[key] = backup(filePath);
  });

  try {
    writeJson(files.sections, [
      {
        id: 1,
        section_key: 'hero',
        section_type: 'fixed_config',
        title_zh: '首页主视觉',
        subtitle_zh: '固定展示区',
        fetch_mode: 'fixed_config',
        extra_config: JSON.stringify({ cta_text: '立即咨询' }),
        sort: 100,
        is_enabled: 1,
      },
      {
        id: 2,
        section_key: 'featured_products',
        section_type: 'product_list',
        title_zh: '推荐产品',
        subtitle_zh: '首页推荐池',
        fetch_mode: 'auto_latest',
        extra_config: JSON.stringify({ limit: 6 }),
        sort: 90,
        is_enabled: 1,
      }
    ]);
    writeJson(files.settings, {
      homepage: {
        publish_meta: {
          draft_updated_at: null,
          live_updated_at: null,
          last_published_by: '',
          last_restored_by: '',
          has_unpublished_changes: 0,
          publish_log: [],
        },
      },
      deepseek: {
        config: {
          translation_enabled: 0,
          seo_enabled: 0,
          chat_enabled: 0,
        },
      },
    });
    writeJson(files.operationLogs, []);
    writeJson(files.loginLogs, []);
    writeJson(files.languages, [
      { id: 1, code: 'zh', name: 'Chinese', is_default: 1, is_enabled: 1, sort: 100 },
      { id: 2, code: 'en', name: 'English', is_default: 0, is_enabled: 1, sort: 90 },
    ]);
    writeJson(files.translationJobs, []);
    writeJson(files.deepseekLogs, []);

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

      $service = new app\\service\\homepage\\HomepageService();
      $logService = new app\\service\\log\\OperationLogService();

      $created = $service->createSection([
        'section_key' => 'featured_cases',
        'section_type' => 'article_list',
        'title_zh' => '客户案例',
        'subtitle_zh' => '首页案例推荐位',
        'fetch_mode' => 'auto_latest',
        'extra_config' => ['limit' => 4],
        'sort' => 80,
        'is_enabled' => 1,
      ]);

      $sorted = $service->sortSections([
        ['id' => 1, 'sort' => 70],
        ['id' => 2, 'sort' => 120],
        ['id' => (int) $created['id'], 'sort' => 110],
      ]);

      $statusUpdated = $service->updateSectionStatus(1, 0);
      $workflow = $service->workflow();
      $detail = $service->sectionDetail((int) $created['id']);

      echo json_encode([
        'created' => $created,
        'sorted' => $sorted,
        'status_updated' => $statusUpdated,
        'workflow' => $workflow,
        'detail' => $detail,
        'operation_logs' => $logService->listOperations(),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8',
    }));

    const issues = [];
    if (String(payload.created?.section_key || '') !== 'featured_cases') {
      issues.push('HomepageService::createSection must persist section_key');
    }
    if (String(payload.created?.section_type || '') !== 'article_list') {
      issues.push('HomepageService::createSection must persist section_type');
    }
    if (Number(payload.created?.id || 0) <= 2) {
      issues.push('HomepageService::createSection must allocate a new id');
    }
    if (String(payload.detail?.title_zh || '') !== '客户案例') {
      issues.push('HomepageService::sectionDetail must return the created section');
    }
    const sortedIds = Array.isArray(payload.sorted) ? payload.sorted.map((item) => Number(item.id || 0)) : [];
    if (JSON.stringify(sortedIds.slice(0, 3)) !== JSON.stringify([2, Number(payload.created?.id || 0), 1])) {
      issues.push('HomepageService::sortSections must reorder by sort desc');
    }
    if (Number(payload.status_updated ? payload.status_updated.is_enabled : -1) !== 0) {
      issues.push('HomepageService::updateSectionStatus must persist is_enabled');
    }
    if (!payload.workflow?.draft_updated_at) {
      issues.push('HomepageService section mutations must update draft_updated_at');
    }
    if (Number(payload.workflow?.has_unpublished_changes || 0) !== 1) {
      issues.push('HomepageService section mutations must mark homepage as having unpublished changes');
    }

    const logs = payload.operation_logs && Array.isArray(payload.operation_logs.items)
      ? payload.operation_logs.items
      : (Array.isArray(payload.operation_logs) ? payload.operation_logs : []);
    const actionPoints = logs.map((item) => String(item.action_point || ''));
    ['homepage.create', 'homepage.sort', 'homepage.status.update'].forEach((actionPoint) => {
      if (!actionPoints.includes(actionPoint)) {
        issues.push(`OperationLogService missing action log: ${actionPoint}`);
      }
    });

    if (issues.length > 0) {
      console.error('Homepage section management runtime validation failed:');
      issues.forEach((issue) => console.error('- ' + issue));
      process.exit(1);
    }

    console.log('Homepage section management runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
