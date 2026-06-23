<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class ConversationRepository
{
    public function listConversations(): array
    {
        if ($this->preferRuntimeStorage()) {
            $rows = array_map(
                fn (array $item): array => $this->normalizeConversationRecord($item),
                $this->loadRuntimeConversations()
            );

            usort($rows, fn (array $left, array $right): int => $this->compareConversationRows($left, $right));

            return $rows;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $archiveSelect = $this->hasTableColumn($pdo, 'chat_sessions', 'archive_status')
                ? 'cs.archive_status'
                : "'active' AS archive_status";
            $statement = $pdo->query(
                'SELECT cs.id AS session_id, cs.session_code, cs.source, cs.source_page, cs.entry_language, cs.resolved_language, cs.country_code, cs.device_type, cs.utm_source, cs.is_valid_conversation, cs.inquiry_id, ' . $archiveSelect . ', cs.last_message_at, cs.created_at, cs.updated_at,
                        (SELECT contact_name FROM lead_snapshots WHERE session_id = cs.id ORDER BY snapshot_version DESC, id DESC LIMIT 1) AS contact_name,
                        (SELECT company_name FROM lead_snapshots WHERE session_id = cs.id ORDER BY snapshot_version DESC, id DESC LIMIT 1) AS company_name,
                        (SELECT email FROM lead_snapshots WHERE session_id = cs.id ORDER BY snapshot_version DESC, id DESC LIMIT 1) AS email,
                        (SELECT phone FROM lead_snapshots WHERE session_id = cs.id ORDER BY snapshot_version DESC, id DESC LIMIT 1) AS phone,
                        (SELECT whatsapp FROM lead_snapshots WHERE session_id = cs.id ORDER BY snapshot_version DESC, id DESC LIMIT 1) AS whatsapp,
                        (SELECT product_interest FROM lead_snapshots WHERE session_id = cs.id ORDER BY snapshot_version DESC, id DESC LIMIT 1) AS product_interest,
                        (SELECT solution_interest FROM lead_snapshots WHERE session_id = cs.id ORDER BY snapshot_version DESC, id DESC LIMIT 1) AS solution_interest,
                        (SELECT requirement_summary FROM lead_snapshots WHERE session_id = cs.id ORDER BY snapshot_version DESC, id DESC LIMIT 1) AS requirement_summary,
                        (SELECT confidence_score FROM lead_snapshots WHERE session_id = cs.id ORDER BY snapshot_version DESC, id DESC LIMIT 1) AS confidence_score,
                        (SELECT COUNT(*) FROM chat_messages WHERE session_id = cs.id) AS message_count,
                        (SELECT COUNT(*) FROM lead_snapshots WHERE session_id = cs.id) AS snapshot_count
                 FROM chat_sessions cs
                 ORDER BY cs.updated_at DESC, cs.id DESC'
            );
            $rows = $statement->fetchAll();

            return array_map(fn (array $item): array => $this->normalizeConversationRecord($item), is_array($rows) ? $rows : []);
        }

        return [];
    }

    public function findConversationBySessionId(int $sessionId): ?array
    {
        if ($sessionId <= 0) {
            return null;
        }

        if ($this->preferRuntimeStorage()) {
            foreach ($this->loadRuntimeConversations() as $conversation) {
                if ($this->conversationSessionId($conversation) !== $sessionId) {
                    continue;
                }

                return $this->normalizeConversationRecord($conversation);
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $archiveSelect = $this->hasTableColumn($pdo, 'chat_sessions', 'archive_status')
                ? 'archive_status'
                : "'active' AS archive_status";
            $statement = $pdo->prepare(
                'SELECT id AS session_id, session_code, source, source_page, entry_language, resolved_language, country_code, device_type, utm_source, is_valid_conversation, inquiry_id, ' . $archiveSelect . ', last_message_at, created_at, updated_at
                 FROM chat_sessions
                 WHERE id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $sessionId]);
            $conversation = $statement->fetch();
            if (is_array($conversation)) {
                $messageStatement = $pdo->prepare(
                    'SELECT message_role AS role, content, created_at, message_language, translated_text, intent_code, contains_contact_info, extracted_entities_json
                     FROM chat_messages
                     WHERE session_id = :session_id
                     ORDER BY id ASC'
                );
                $messageStatement->execute(['session_id' => $sessionId]);
                $conversation['messages'] = $messageStatement->fetchAll() ?: [];

                $snapshotStatement = $pdo->prepare(
                    'SELECT snapshot_version, contact_name, company_name, email, phone, whatsapp, country_code, product_interest, solution_interest, requirement_summary, confidence_score, created_at
                     FROM lead_snapshots
                     WHERE session_id = :session_id
                     ORDER BY snapshot_version DESC, id DESC'
                );
                $snapshotStatement->execute(['session_id' => $sessionId]);
                $conversation['snapshots'] = $snapshotStatement->fetchAll() ?: [];

                $messageCountStatement = $pdo->prepare(
                    'SELECT COUNT(*) AS message_count
                     FROM chat_messages
                     WHERE session_id = :session_id'
                );
                $messageCountStatement->execute(['session_id' => $sessionId]);
                $conversation['message_count'] = (int) ($messageCountStatement->fetch()['message_count'] ?? 0);

                $snapshotCountStatement = $pdo->prepare(
                    'SELECT COUNT(*) AS snapshot_count
                     FROM lead_snapshots
                     WHERE session_id = :session_id'
                );
                $snapshotCountStatement->execute(['session_id' => $sessionId]);
                $conversation['snapshot_count'] = (int) ($snapshotCountStatement->fetch()['snapshot_count'] ?? 0);

                return $this->normalizeConversationRecord($conversation);
            }
        }

        return null;
    }

    public function listVisitorEvents(string $sessionCode): array
    {
        $sessionCode = trim($sessionCode);
        if ($sessionCode === '') {
            return [];
        }

        if ($this->preferRuntimeStorage()) {
            $events = $this->loadRuntimeVisitorEvents()[$sessionCode] ?? [];

            return $this->normalizeVisitorEvents(is_array($events) ? $events : []);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT page, title, referrer, visited_at, language_code
                 FROM visitor_events
                 WHERE session_code = :session_code
                 ORDER BY visited_at ASC'
            );
            $statement->execute(['session_code' => $sessionCode]);
            $rows = $statement->fetchAll();

            return $this->normalizeVisitorEvents(is_array($rows) ? $rows : []);
        }

        return [];
    }

    public function updateArchiveStatus(int $sessionId, string $archiveStatus): ?array
    {
        $archiveStatus = $this->normalizeArchiveStatus($archiveStatus);

        if ($this->preferRuntimeStorage()) {
            $conversations = $this->loadRuntimeConversations();
            foreach ($conversations as $index => $conversation) {
                if ($this->conversationSessionId($conversation) !== $sessionId) {
                    continue;
                }

                $conversations[$index]['archive_status'] = $archiveStatus;
                $conversations[$index]['updated_at'] = date('Y-m-d H:i:s');
                $this->saveRuntimeConversations($conversations);

                return $this->normalizeConversationRecord($conversations[$index]);
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            if (!$this->hasTableColumn($pdo, 'chat_sessions', 'archive_status')) {
                $updated = $this->findConversationBySessionId($sessionId);
                if ($updated === null) {
                    return null;
                }

                $updated['archive_status'] = $archiveStatus;

                return $updated;
            }

            $statement = $pdo->prepare(
                'UPDATE chat_sessions
                 SET archive_status = :archive_status, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $sessionId,
                'archive_status' => $archiveStatus,
            ]);

            return $this->findConversationBySessionId($sessionId);
        }

        return null;
    }

    private function normalizeConversationRecord(array $item): array
    {
        $item['session_id'] = $this->conversationSessionId($item);
        $item['source'] = (string) ($item['source'] ?? 'ai');
        $item['session_code'] = (string) ($item['session_code'] ?? '');
        $item['source_page'] = (string) ($item['source_page'] ?? '');
        $item['entry_language'] = (string) ($item['entry_language'] ?? '');
        $item['resolved_language'] = (string) ($item['resolved_language'] ?? '');
        $item['country_code'] = (string) ($item['country_code'] ?? '');
        $item['device_type'] = (string) ($item['device_type'] ?? '');
        $item['utm_source'] = (string) ($item['utm_source'] ?? '');
        $item['messages'] = $this->normalizeConversationMessages($item['messages'] ?? []);
        $item['snapshots'] = $this->normalizeConversationSnapshots($item['snapshots'] ?? []);
        $item['message_count'] = (int) ($item['message_count'] ?? count($item['messages']));
        $item['snapshot_count'] = (int) ($item['snapshot_count'] ?? count($item['snapshots']));
        $item['is_valid_conversation'] = (int) ($item['is_valid_conversation'] ?? 0);
        $item['inquiry_id'] = (int) ($item['inquiry_id'] ?? 0);
        $item['archive_status'] = $this->normalizeArchiveStatus($item['archive_status'] ?? null);

        $latestSnapshot = is_array($item['snapshots'][0] ?? null) ? $item['snapshots'][0] : [];
        foreach (['contact_name', 'company_name', 'email', 'phone', 'whatsapp', 'product_interest', 'solution_interest', 'requirement_summary', 'confidence_score'] as $field) {
            if (array_key_exists($field, $item) && $item[$field] !== null && $item[$field] !== '') {
                continue;
            }

            $item[$field] = $latestSnapshot[$field] ?? ($field === 'confidence_score' ? null : '');
        }

        return $item;
    }

    /**
     * @param array<int, mixed> $messages
     * @return array<int, array<string, mixed>>
     */
    private function normalizeConversationMessages(array $messages): array
    {
        $normalized = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $message['role'] = (string) ($message['role'] ?? '');
            $message['content'] = (string) ($message['content'] ?? '');
            $message['created_at'] = (string) ($message['created_at'] ?? '');
            $message['message_language'] = (string) ($message['message_language'] ?? '');
            $message['translated_text'] = (string) ($message['translated_text'] ?? '');
            $message['intent_code'] = (string) ($message['intent_code'] ?? '');
            $message['contains_contact_info'] = (int) ($message['contains_contact_info'] ?? 0);
            $message['extracted_entities_json'] = $this->decodeJsonField($message['extracted_entities_json'] ?? null);
            $message['sources'] = $this->normalizeMessageSources($message['extracted_entities_json']);
            $normalized[] = $message;
        }

        usort($normalized, fn (array $left, array $right): int => strcmp((string) ($left['created_at'] ?? ''), (string) ($right['created_at'] ?? '')));

        return $normalized;
    }

    /**
     * @param array<int, mixed> $snapshots
     * @return array<int, array<string, mixed>>
     */
    private function normalizeConversationSnapshots(array $snapshots): array
    {
        $normalized = [];
        foreach ($snapshots as $index => $snapshot) {
            if (!is_array($snapshot)) {
                continue;
            }

            $snapshot['snapshot_version'] = (int) ($snapshot['snapshot_version'] ?? ($index + 1));
            $snapshot['contact_name'] = (string) ($snapshot['contact_name'] ?? '');
            $snapshot['company_name'] = (string) ($snapshot['company_name'] ?? '');
            $snapshot['email'] = (string) ($snapshot['email'] ?? '');
            $snapshot['phone'] = (string) ($snapshot['phone'] ?? '');
            $snapshot['whatsapp'] = (string) ($snapshot['whatsapp'] ?? '');
            $snapshot['country_code'] = (string) ($snapshot['country_code'] ?? '');
            $snapshot['product_interest'] = (string) ($snapshot['product_interest'] ?? '');
            $snapshot['solution_interest'] = (string) ($snapshot['solution_interest'] ?? '');
            $snapshot['requirement_summary'] = (string) ($snapshot['requirement_summary'] ?? '');
            $snapshot['created_at'] = (string) ($snapshot['created_at'] ?? '');
            $normalized[] = $snapshot;
        }

        usort($normalized, function (array $left, array $right): int {
            $versionCompare = ((int) ($right['snapshot_version'] ?? 0)) <=> ((int) ($left['snapshot_version'] ?? 0));
            if ($versionCompare !== 0) {
                return $versionCompare;
            }

            return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
        });

        return $normalized;
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMessageSources(array $payload): array
    {
        $sources = is_array($payload['sources'] ?? null) ? $payload['sources'] : [];
        $normalized = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            $normalized[] = [
                'title' => trim((string) ($source['title'] ?? '')),
                'source_type' => trim((string) ($source['source_type'] ?? '')),
                'source_id' => is_numeric($source['source_id'] ?? null) ? (int) $source['source_id'] : null,
                'url' => trim((string) ($source['url'] ?? '')),
            ];
        }

        return array_values(array_filter($normalized, static fn (array $source): bool => $source['title'] !== '' || $source['source_type'] !== '' || $source['url'] !== '' || $source['source_id'] !== null));
    }

    private function normalizeArchiveStatus(mixed $value): string
    {
        $archiveStatus = trim((string) $value);

        return $archiveStatus !== '' ? $archiveStatus : 'active';
    }

    private function conversationSessionId(array $item): int
    {
        return (int) ($item['session_id'] ?? $item['id'] ?? 0);
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, string>>
     */
    private function normalizeVisitorEvents(array $rows): array
    {
        $events = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $events[] = [
                'page' => (string) ($row['page'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'referrer' => (string) ($row['referrer'] ?? ''),
                'visited_at' => (string) ($row['visited_at'] ?? ''),
                'language_code' => (string) ($row['language_code'] ?? ''),
            ];
        }

        usort($events, fn (array $left, array $right): int => strcmp($left['visited_at'], $right['visited_at']));

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRuntimeConversations(): array
    {
        return $this->loadRuntimeList('conversations.json');
    }

    private function saveRuntimeConversations(array $rows): void
    {
        $this->saveRuntimeList('conversations.json', $rows);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function loadRuntimeVisitorEvents(): array
    {
        $payload = $this->loadRuntimeFile('visitor_events.json');

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRuntimeList(string $fileName): array
    {
        $payload = $this->loadRuntimeFile($fileName);

        return is_array($payload) ? array_values(array_filter($payload, 'is_array')) : [];
    }

    private function saveRuntimeList(string $fileName, array $rows): void
    {
        $this->saveRuntimeFile($fileName, array_values($rows));
    }

    private function loadRuntimeFile(string $fileName): mixed
    {
        $path = $this->runtimeStoragePath($fileName);
        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if (!is_string($content) || trim($content) === '') {
            return null;
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        return json_decode($content, true);
    }

    private function saveRuntimeFile(string $fileName, mixed $payload): void
    {
        $path = $this->runtimeStoragePath($fileName);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function runtimeStoragePath(string $fileName): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/' . $fileName;
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage([
            $this->runtimeStoragePath('conversations.json'),
            $this->runtimeStoragePath('visitor_events.json'),
        ]);
    }

    private function compareConversationRows(array $left, array $right): int
    {
        $updatedCompare = strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
        if ($updatedCompare !== 0) {
            return $updatedCompare;
        }

        return ((int) ($right['session_id'] ?? 0)) <=> ((int) ($left['session_id'] ?? 0));
    }

    private function hasTableColumn(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];

        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $statement = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '` LIKE :column');
        $statement->execute(['column' => $column]);

        return $cache[$key] = (bool) $statement->fetch();
    }
}
