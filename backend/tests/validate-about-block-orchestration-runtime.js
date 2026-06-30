const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const backendRoot = path.resolve(__dirname, '..');
const storageDir = path.join(backendRoot, 'runtime', 'storage');
const aboutPagesPath = path.join(storageDir, 'about_pages.json');

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

    $pdo = app\\common\\database\\DatabaseManager::instance()->connection();
    $pdo->exec("REPLACE INTO about_blocks (id, about_page_id, block_type, title_zh, subtitle_zh, content_zh, extra_config, sort, is_enabled) VALUES (11, 1, 'text', '企业概况', '核心能力', '旧内容', '{}', 100, 1)");
    $pdo->exec("REPLACE INTO about_blocks (id, about_page_id, block_type, title_zh, subtitle_zh, content_zh, extra_config, sort, is_enabled) VALUES (12, 1, 'team_list', '团队模块', '旧团队', '', '{\\"source\\":\\"team_members\\"}', 90, 1)");

    $service = new app\\service\\content\\AboutService();
    $updated = $service->updateBlocks(1, [
      [
        'id' => 12,
        'block_type' => 'team_list',
        'title_zh' => '团队模块',
        'subtitle_zh' => '销售团队',
        'content_zh' => '',
        'extra_config' => ['source' => 'team_members'],
        'sort' => 120,
        'is_enabled' => 1,
      ],
      [
        'id' => 0,
        'block_type' => 'video',
        'title_zh' => '新增视频模块',
        'subtitle_zh' => '宣传片',
        'content_zh' => '',
        'extra_config' => ['video_url' => 'https://example.com/intro.mp4'],
        'sort' => 110,
        'is_enabled' => 1,
      ],
      [
        'id' => 11,
        'block_type' => 'text',
        'title_zh' => '企业概况',
        'subtitle_zh' => '核心能力',
        'content_zh' => '面向海外市场的产线设备定制。',
        'extra_config' => [],
        'sort' => 100,
        'is_enabled' => 1,
      ],
    ]);

    echo json_encode([
      'updated' => $updated,
      'page' => $service->page(1),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  `;
}

function main() {
  const backupContent = backup(aboutPagesPath);

  try {
    fs.mkdirSync(storageDir, { recursive: true });
    fs.writeFileSync(aboutPagesPath, JSON.stringify([
      {
        id: 1,
        page_key: 'company-about',
        name_zh: '企业介绍',
        is_enabled: 1,
        blocks: [
          {
            id: 11,
            block_type: 'text',
            title_zh: '企业概况',
            subtitle_zh: '核心能力',
            content_zh: '旧内容',
            extra_config: [],
            sort: 100,
            is_enabled: 1
          },
          {
            id: 12,
            block_type: 'team_list',
            title_zh: '团队模块',
            subtitle_zh: '旧团队',
            content_zh: '',
            extra_config: { source: 'team_members' },
            sort: 90,
            is_enabled: 1
          }
        ]
      }
    ], null, 2), 'utf8');

    const output = execFileSync('php', ['-r', buildPhpPayload()], {
      cwd: backendRoot,
      encoding: 'utf8',
      env: {
        ...process.env,
        PREFER_RUNTIME_STORAGE: '0',
      },
    });

    const payload = JSON.parse(output);
    const blocks = Array.isArray(payload.updated?.blocks) ? payload.updated.blocks : [];
    const persistedBlocks = Array.isArray(payload.page?.blocks) ? payload.page.blocks : [];
    const issues = [];

    if (blocks.length !== 3) {
      issues.push('AboutService::updateBlocks must persist create/delete changes as the full new block set');
    }

    const videoBlock = blocks.find((item) => String(item.block_type || '') === 'video');
    if (!videoBlock || Number(videoBlock.id || 0) <= 12) {
      issues.push('AboutService::updateBlocks must assign a new id for newly added blocks');
    }

    if (String(blocks[0]?.block_type || '') !== 'team_list' || String(blocks[1]?.block_type || '') !== 'video') {
      issues.push('AboutService::updateBlocks must preserve the submitted block ordering');
    }

    if (Number(blocks[0]?.sort || 0) !== 120 || Number(blocks[1]?.sort || 0) !== 110) {
      issues.push('AboutService::updateBlocks must preserve submitted sort values');
    }

    if (persistedBlocks.length !== 3 || String(persistedBlocks[1]?.block_type || '') !== 'video') {
      issues.push('AboutRepository runtime persistence must keep the updated block sequence');
    }

    if (String(persistedBlocks[2]?.content_zh || '') !== '面向海外市场的产线设备定制。') {
      issues.push('AboutService::updateBlocks must persist edited block content');
    }

    if (issues.length > 0) {
      console.error('About block orchestration runtime validation failed:');
      issues.forEach((issue) => console.error(`- ${issue}`));
      process.exit(1);
    }

    console.log('About block orchestration runtime validation passed.');
  } finally {
    restore(aboutPagesPath, backupContent);
  }
}

main();
