const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');

const files = {
  media: path.join(storageDir, 'media_assets.json'),
  solutions: path.join(storageDir, 'solutions.json'),
  team: path.join(storageDir, 'team_members.json'),
  certificates: path.join(storageDir, 'certificates.json'),
  operationLogs: path.join(storageDir, 'operation_logs.json')
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

function writeJson(filePath, payload) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  fs.writeFileSync(filePath, JSON.stringify(payload, null, 2), 'utf8');
}

function main() {
  const backups = {};
  Object.entries(files).forEach(([key, filePath]) => {
    backups[key] = backup(filePath);
  });

  try {
    writeJson(files.media, [
      {
        id: 1,
        folder_name: 'products',
        storage_disk: 'local',
        file_path: '/uploads/products/free-product.jpg',
        file_name: 'free-product.jpg',
        file_ext: 'jpg',
        mime_type: 'image/jpeg',
        file_size: 1000,
        width: 100,
        height: 100,
        duration_seconds: null,
        alt_text_zh: 'Free Product',
        description_zh: 'unreferenced image',
        status: 1,
        created_at: '2026-06-11 08:00:00',
        updated_at: '2026-06-11 08:00:00'
      },
      {
        id: 2,
        folder_name: 'manuals',
        storage_disk: 'local',
        file_path: '/uploads/manuals/cake-line.pdf',
        file_name: 'cake-line.pdf',
        file_ext: 'pdf',
        mime_type: 'application/pdf',
        file_size: 5000,
        width: null,
        height: null,
        duration_seconds: null,
        alt_text_zh: 'Cake Manual',
        description_zh: 'solution manual',
        status: 1,
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:00:00'
      },
      {
        id: 3,
        folder_name: 'team',
        storage_disk: 'local',
        file_path: '/uploads/team/sales-avatar.png',
        file_name: 'sales-avatar.png',
        file_ext: 'png',
        mime_type: 'image/png',
        file_size: 3000,
        width: 256,
        height: 256,
        duration_seconds: null,
        alt_text_zh: 'Sales Avatar',
        description_zh: 'sales team avatar',
        status: 1,
        created_at: '2026-06-11 10:00:00',
        updated_at: '2026-06-11 10:00:00'
      }
    ]);
    writeJson(files.solutions, [
      {
        id: 11,
        category_id: 1,
        name_zh: 'Cake Line',
        summary_zh: '',
        content_zh: '',
        flow_text_zh: '',
        capacity_text_zh: '',
        manual_asset_id: 2,
        publish_status: 'published',
        translation_status: 'completed',
        seo_status: 'generated',
        is_home_featured: 0,
        manual_sort: 0,
        slug: 'cake-line',
        seo_title: 'Cake Line',
        seo_keywords: 'cake',
        seo_description: 'cake',
        publish_time: '2026-06-11 09:00:00',
        created_by: 1,
        updated_by: 1,
        created_at: '2026-06-11 09:00:00',
        updated_at: '2026-06-11 09:00:00'
      }
    ]);
    writeJson(files.team, [
      {
        id: 21,
        name_zh: 'Amy',
        title_zh: 'Sales',
        department_zh: 'International',
        bio_zh: '',
        avatar_asset_id: 3,
        email: 'amy@example.com',
        phone: '+86-10000000000',
        whatsapp: '+86-10000000000',
        wechat: 'amy',
        publish_status: 'published',
        translation_status: 'completed',
        is_home_featured: 0,
        manual_sort: 0,
        created_by: 1,
        updated_by: 1,
        created_at: '2026-06-11 10:00:00',
        updated_at: '2026-06-11 10:00:00'
      }
    ]);
    writeJson(files.certificates, []);
    writeJson(files.operationLogs, []);

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

      $service = new app\\service\\media\\MediaService();
      $logService = new app\\service\\log\\OperationLogService();

      $assetList = $service->assets([
        'folder_name' => 'manuals',
        'file_category' => 'pdf',
      ]);
      $pagedList = $service->assets([
        'page' => 2,
        'page_size' => 1,
      ]);
      $picker = $service->picker([
        'keyword' => 'sales',
        'file_category' => 'image',
      ]);
      $manualReferences = $service->references(2);
      $teamReferences = $service->references(3);
      $deleted = $service->remove(1);

      $blockedDeleteMessage = '';
      try {
        $service->remove(2);
      } catch (app\\common\\exception\\BusinessException $exception) {
        $blockedDeleteMessage = $exception->getMessage();
      }

      echo json_encode([
        'asset_list' => $assetList,
        'paged_list' => $pagedList,
        'picker' => $picker,
        'manual_references' => $manualReferences,
        'team_references' => $teamReferences,
        'deleted' => $deleted,
        'blocked_delete_message' => $blockedDeleteMessage,
        'operation_logs' => $logService->listOperations(),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    `;

    const payload = JSON.parse(execFileSync('php', ['-r', phpCode], {
      cwd: backendRoot,
      encoding: 'utf8'
    }));

    const issues = [];

    if (Number(payload.asset_list?.pagination?.total || 0) !== 1 || Number(payload.asset_list?.items?.[0]?.id || 0) !== 2) {
      issues.push('MediaService::assets must filter by folder and file category');
    }
    if (Number(payload.paged_list?.folder_counts?.manuals || 0) !== 1 || Number(payload.paged_list?.folder_counts?.products || 0) !== 1 || Number(payload.paged_list?.folder_counts?.team || 0) !== 1) {
      issues.push('MediaService::assets must return stable folder counts for the filtered asset pool');
    }
    if (Number(payload.paged_list?.pagination?.page || 0) !== 2 || Number(payload.paged_list?.pagination?.page_size || 0) !== 1 || Number(payload.paged_list?.items?.[0]?.id || 0) !== 2) {
      issues.push('MediaService::assets must support pagination over the asset list');
    }
    if (!Array.isArray(payload.picker?.items) || Number(payload.picker.items[0]?.id || 0) !== 3) {
      issues.push('MediaService::picker must return filtered image choices');
    }
    if (Number(payload.manual_references?.reference_count || 0) !== 1 || String(payload.manual_references?.references?.[0]?.entity_type || '') !== 'solution') {
      issues.push('MediaService::references must detect solution manual references');
    }
    const teamReferenceTypes = Array.isArray(payload.team_references?.references)
      ? payload.team_references.references.map((item) => String(item.entity_type || ''))
      : [];
    if (Number(payload.team_references?.reference_count || 0) < 1 || !teamReferenceTypes.includes('team_member')) {
      issues.push('MediaService::references must detect team avatar references');
    }
    if (Number(payload.deleted?.id || 0) !== 1) {
      issues.push('MediaService::remove must delete unreferenced assets');
    }
    if (!String(payload.blocked_delete_message || '').includes('referenced')) {
      issues.push('MediaService::remove must block deleting referenced assets');
    }

    const logs = payload.operation_logs && Array.isArray(payload.operation_logs.items)
      ? payload.operation_logs.items
      : (Array.isArray(payload.operation_logs) ? payload.operation_logs : []);
    const actionPoints = logs.map((item) => String(item.action_point || ''));
    if (!actionPoints.includes('media.delete')) {
      issues.push('OperationLogService missing media.delete action log');
    }

    if (issues.length > 0) {
      console.error('Media center runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('Media center runtime validation passed.');
  } finally {
    Object.entries(files).forEach(([key, filePath]) => {
      restore(filePath, backups[key]);
    });
  }
}

main();
