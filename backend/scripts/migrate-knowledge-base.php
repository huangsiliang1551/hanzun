<?php

declare(strict_types=1);

$sqlFile = dirname(__DIR__) . '/database/sql/004_knowledge_base.sql';
$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "Cannot read SQL file\n");
    exit(1);
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3307';
$db = getenv('DB_DATABASE') ?: 'hanzun_cms';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: 'root';

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: [])) as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    echo "knowledge base migration ok\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'migration failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
