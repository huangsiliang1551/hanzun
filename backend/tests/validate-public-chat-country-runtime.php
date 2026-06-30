<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
require __DIR__ . '/test-bootstrap.php';

putenv('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK=1');
$_ENV['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';
$_SERVER['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';

use app\repository\PublicChatRepository;
use app\service\inquiry\PublicChatService;

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$clientId = 'country-runtime-check';
$service = new PublicChatService();
$repository = new PublicChatRepository();

$visit = $service->recordVisitorEvent([
    'client_id' => $clientId,
    'path' => '/en/products/cake-depositor.html',
    'title' => 'Cake Depositor',
    'referrer' => 'https://example.test/',
    'language' => 'en-US',
]);

$chat = $service->chat([
    'client_id' => $clientId,
    'session_code' => (string) ($visit['session_code'] ?? ''),
    'message' => 'My name is Anna. Email anna@example.com. We need a cake depositor quotation.',
    'path' => '/en/products/cake-depositor.html',
    'title' => 'Cake Depositor',
    'referrer' => 'https://example.test/',
    'language' => 'en-US',
]);

$conversation = $repository->findConversationByCode((string) ($visit['session_code'] ?? ''));
if (!is_array($conversation)) {
    fail('Conversation was not persisted.');
}

if (StringableNotNeeded::value($conversation['entry_language'] ?? '') !== 'en') {
    fail('Conversation entry language must normalize en-US to en.');
}

if (StringableNotNeeded::value($conversation['country_code'] ?? '') !== 'US') {
    fail('Conversation country_code must be derived from locale/UA when frontend country is absent.');
}

$inquiry = $repository->findInquiryBySessionId((int) ($conversation['session_id'] ?? 0));
if (!is_array($inquiry) || (int) ($chat['inquiry_id'] ?? 0) <= 0) {
    fail('Chat should create an inquiry for contact info plus product intent.');
}

if (StringableNotNeeded::value($inquiry['language_code'] ?? '') !== 'en') {
    fail('Inquiry language_code must persist the frontend language.');
}

if (StringableNotNeeded::value($inquiry['country_code'] ?? '') !== 'US') {
    fail('Inquiry country_code must persist the locale-derived country.');
}

fwrite(STDOUT, "Public chat country runtime validation passed.\n");

final class StringableNotNeeded
{
    public static function value(mixed $value): string
    {
        return trim((string) $value);
    }
}
