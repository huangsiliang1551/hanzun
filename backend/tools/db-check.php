<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=hanzun_cms;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$stmt = $pdo->query('SELECT id, username, password_hash FROM admin_users');
$user = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Stored hash: " . $user['password_hash'] . "\n";
echo "Hash length: " . strlen($user['password_hash']) . "\n";

// Try to find matching password
$candidates = ['admin123', 'admin123456', 'admin', 'password', '123456', '12345678', 'Aa123456'];
foreach ($candidates as $pwd) {
    $hash = hash('sha256', $pwd);
    $match = hash_equals(strtolower($user['password_hash']), $hash) ? 'YES' : 'no';
    echo "  $pwd: $match (sha256: $hash)\n";
}
