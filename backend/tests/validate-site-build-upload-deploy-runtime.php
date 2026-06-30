<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$backendRoot = dirname(__DIR__);

require_once $backendRoot . '/app/common/bootstrap/helpers.php';
require_once $backendRoot . '/app/common/bootstrap/Autoloader.php';
require_once $backendRoot . '/app/common/bootstrap/EnvLoader.php';
require_once $backendRoot . '/app/common/config/ConfigRepository.php';
require_once $backendRoot . '/app/common/database/DatabaseManager.php';

\app\common\bootstrap\Autoloader::register($backendRoot);
\app\common\bootstrap\EnvLoader::load($backendRoot . '/.env');
\app\common\config\ConfigRepository::instance()->load($backendRoot . '/config');
\app\common\database\DatabaseManager::instance()->configure(
    \app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);

$publisher = new \app\service\StaticPublisher();
$reflection = new ReflectionClass($publisher);
$deployMethod = $reflection->getMethod('deployFullBuildOutputs');
$deployMethod->setAccessible(true);

$unique = 'upload-deploy-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
$stagingDir = $backendRoot . '/runtime/storage/' . $unique . '-staging';
$finalDir = $backendRoot . '/runtime/storage/' . $unique . '-final';
$sourceUploadsDir = $backendRoot . '/public/uploads/images';
$fixtureName = 'codex-upload-deploy-fixture.txt';
$sourceFixture = $sourceUploadsDir . '/' . $fixtureName;
$finalFixture = $finalDir . '/uploads/images/' . $fixtureName;

$cleanup = null;
$cleanup = static function (string $path) use (&$cleanup): void {
    if (!file_exists($path)) {
        return;
    }

    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }

    $items = scandir($path);
    if (is_array($items)) {
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . DIRECTORY_SEPARATOR . $item;
            $cleanup($child);
        }
    }

    @rmdir($path);
};

try {
    if (!is_dir($sourceUploadsDir) && !mkdir($sourceUploadsDir, 0777, true) && !is_dir($sourceUploadsDir)) {
        throw new RuntimeException('Unable to prepare source uploads dir.');
    }
    if (!is_dir($stagingDir) && !mkdir($stagingDir, 0777, true) && !is_dir($stagingDir)) {
        throw new RuntimeException('Unable to prepare staging dir.');
    }
    if (!is_dir($finalDir) && !mkdir($finalDir, 0777, true) && !is_dir($finalDir)) {
        throw new RuntimeException('Unable to prepare final dir.');
    }

    file_put_contents($sourceFixture, 'codex upload deploy fixture');

    $failures = $deployMethod->invoke($publisher, $stagingDir, $finalDir, ['zh', 'en']);
    if ($failures !== []) {
        throw new RuntimeException('Expected deployFullBuildOutputs() to succeed when staging/uploads is missing, got: ' . json_encode($failures, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    if (!is_file($finalFixture)) {
        throw new RuntimeException('Expected deployFullBuildOutputs() to copy backend/public/uploads into final uploads output.');
    }

    echo "Site build upload deploy runtime validation passed." . PHP_EOL;
} finally {
    @unlink($sourceFixture);
    $cleanup($stagingDir);
    $cleanup($finalDir);
}
