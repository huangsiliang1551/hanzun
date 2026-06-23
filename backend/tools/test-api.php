<?php
$base = 'http://127.0.0.1:18080';

// Login - no captcha needed (AuthController just takes username+password)
$ch = curl_init($base . '/admin/auth/login');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'username' => 'admin',
        'password' => 'admin123456',
    ]),
    CURLOPT_RETURNTRANSFER => true,
]);
$loginRes = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "=== LOGIN ===\nHTTP: $httpCode\n$loginRes\n\n";

$login = json_decode($loginRes, true);
if ($login && isset($login['data']['access_token'])) {
    $token = $login['data']['access_token'];

    // Test translation jobs
    $ch = curl_init($base . '/admin/translation/jobs');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    echo "=== TRANSLATION JOBS ===\n";
    echo curl_exec($ch) . "\n\n";
    curl_close($ch);

    // Test SEO jobs
    $ch = curl_init($base . '/admin/seo/jobs');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    echo "=== SEO JOBS ===\n";
    echo curl_exec($ch) . "\n\n";
    curl_close($ch);

    // Test settings deepseek-logs
    $ch = curl_init($base . '/admin/settings/deepseek-logs');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ],
        CURLOPT_RETURNTRANSFER => true,
    ]);
    echo "=== DEEPSEEK LOGS ===\n";
    echo curl_exec($ch) . "\n\n";
    curl_close($ch);
} else {
    echo "LOGIN FAILED\n";
    if ($login) echo json_encode($login, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}
