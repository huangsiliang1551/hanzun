<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hanzun_cms;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = file_get_contents(__DIR__ . '/database/sql/001_init_schema.sql');
$statements = explode(";\n", $sql);

$count = 0;
foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if ($stmt === '' || stripos($stmt, 'CREATE DATABASE') === 0 || stripos($stmt, 'USE ') === 0) {
        continue;
    }
    try {
        $pdo->exec($stmt);
        $count++;
    } catch (Exception $e) {
        echo "Error at statement $count: " . $e->getMessage() . "\n";
        echo substr($stmt, 0, 100) . "...\n";
    }
}

echo "Schema imported: $count statements executed.\n";
