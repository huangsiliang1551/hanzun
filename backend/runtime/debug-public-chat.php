<?php
$basePath = 'C:/Users/ZhuanZ1/my-project/涵尊实业有限公司/backend';
require_once $basePath . '/app/common/bootstrap/Autoloader.php';
require_once $basePath . '/app/common/bootstrap/EnvLoader.php';
require_once $basePath . '/app/common/bootstrap/helpers.php';
app\common\bootstrap\Autoloader::register($basePath);
app\common\bootstrap\EnvLoader::load($basePath . '/.env');
app\common\config\ConfigRepository::instance()->load($basePath . '/config');
app\common\database\DatabaseManager::instance()->configure(
    app\common\config\ConfigRepository::instance()->get('database.connections.mysql', [])
);
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148';
$clientId = 'debug-client-' . substr(bin2hex(random_bytes(8)), 0, 12);
$service = new app\service\inquiry\PublicChatService();
$visit = $service->recordVisitorEvent([
  'client_id' => $clientId,
  'path' => '/en/solutions/cake-line',
  'title' => 'Cake Line',
  'referrer' => 'https://www.google.com/',
  'language' => 'en-US',
]);
$chat = $service->chat([
  'client_id' => $clientId,
  'session_code' => $visit['session_code'],
  'message' => 'This is Daniel. Company name is Daniel Foods GmbH. Email daniel@example.com. We need a cake line quotation for Germany.',
  'path' => '/en/solutions/cake-line',
  'title' => 'Cake Line',
  'referrer' => 'https://www.google.com/',
  'language' => 'en-US',
  'utm_source' => 'google',
]);
$repo = new app\repository\PublicChatRepository();
$conv = $repo->findConversationByCode($visit['session_code']);
$inq = $repo->findInquiryBySessionId((int)($conv['session_id'] ?? 0));
echo json_encode([
  'visit' => $visit,
  'chat' => $chat,
  'conversation' => $conv,
  'inquiry' => $inq,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
