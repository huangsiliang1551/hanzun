<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hanzun_cms;charset=utf8mb4', 'root', '');
$hash = trim(file_get_contents(__DIR__ . '/_hash.txt'));
echo "Hash from file: $hash\n";
$stmt = $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = 1');
$stmt->execute([$hash]);
echo "Updated: " . $stmt->rowCount() . " rows\n";
// Verify
$stmt = $pdo->query('SELECT id, username, password_hash FROM admin_users WHERE id = 1');
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "User: " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
echo "Password verify: " . (password_verify('admin123456', $row['password_hash']) ? 'OK' : 'FAIL') . "\n";
