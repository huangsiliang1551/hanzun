<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class PublicChatRepository
{
    public function findConversationByCode(string $sessionCode): ?array
    {
        $sessionCode = trim($sessionCode);
        if ($sessionCode === '') {
            return null;
        }

        if ($this->preferRuntimeStorage()) {
            foreach ($this->loadRuntimeConversations() as $record) {
                if ((string) ($record['session_code'] ?? '') !== $sessionCode) {
                    continue;
                }

                return $this->normalizeConversationRecord($record);
            }

            return null;
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT id AS session_id, session_code, source, source_page, entry_language, resolved_language, country_code, device_type, utm_source, is_valid_conversation, inquiry_id, last_message_at, created_at, updated_at
                 FROM chat_sessions
                 WHERE session_code = :session_code
                 LIMIT 1'
            );
            $statement->execute(['session_code' => $sessionCode]);
            $record = $statement->fetch();
            if (is_array($record)) {
                $sessionId = (int) ($record['session_id'] ?? 0);

                $messageStatement = $pdo->prepare(
                    'SELECT message_role AS role, content, created_at, message_language, translated_text, intent_code, contains_contact_info, extracted_entities_json
                     FROM chat_messages
                     WHERE session_id = :session_id
                     ORDER BY id ASC'
                );
                $messageStatement->execute(['session_id' => $sessionId]);
                $record['messages'] = $messageStatement->fetchAll() ?: [];

                $snapshotStatement = $pdo->prepare(
                    'SELECT snapshot_version, contact_name, company_name, email, phone, whatsapp, country_code, product_interest, solution_interest, requirement_summary, confidence_score, created_at
                     FROM lead_snapshots
                     WHERE session_id = :session_id
                     ORDER BY snapshot_version DESC, id DESC'
                );
                $snapshotStatement->execute(['session_id' => $sessionId]);
                $record['snapshots'] = $snapshotStatement->fetchAll() ?: [];

                return $this->normalizeConversationRecord($record);
            }
        }

        return null;
    }

    public function createOrTouchConversation(array $payload): array
    {
        $sessionCode = trim((string) ($payload['session_code'] ?? ''));
        $existing = $this->findConversationByCode($sessionCode);
        $now = date('Y-m-d H:i:s');

        $record = [
            'session_code' => $sessionCode,
            'source' => 'ai',
            'source_page' => (string) ($payload['source_page'] ?? ($existing['source_page'] ?? '')),
            'entry_language' => (string) ($payload['entry_language'] ?? ($existing['entry_language'] ?? '')),
            'resolved_language' => (string) ($payload['resolved_language'] ?? ($existing['resolved_language'] ?? '')),
            'country_code' => (string) ($payload['country_code'] ?? ($existing['country_code'] ?? '')),
            'device_type' => (string) ($payload['device_type'] ?? ($existing['device_type'] ?? '')),
            'utm_source' => (string) ($payload['utm_source'] ?? ($existing['utm_source'] ?? '')),
            'is_valid_conversation' => (int) ($payload['is_valid_conversation'] ?? ($existing['is_valid_conversation'] ?? 0)),
            'inquiry_id' => (int) ($payload['inquiry_id'] ?? ($existing['inquiry_id'] ?? 0)),
            'last_message_at' => $payload['last_message_at'] ?? ($existing['last_message_at'] ?? $now),
        ];

        if ($this->preferRuntimeStorage()) {
            $conversations = $this->loadRuntimeConversations();

            foreach ($conversations as $index => $conversation) {
                if ((string) ($conversation['session_code'] ?? '') !== $sessionCode) {
                    continue;
                }

                $conversations[$index] = array_merge($conversation, $record, [
                    'session_id' => $this->conversationSessionId($conversation),
                    'id' => $this->conversationSessionId($conversation),
                    'source' => (string) ($conversation['source'] ?? 'ai'),
                    'messages' => $this->arrayItems($conversation['messages'] ?? []),
                    'snapshots' => $this->arrayItems($conversation['snapshots'] ?? []),
                    'created_at' => $conversation['created_at'] ?? $now,
                    'updated_at' => $now,
                ]);
                $this->saveRuntimeConversations($conversations);

                return $this->normalizeConversationRecord($conversations[$index]);
            }

            $id = $this->nextRuntimeConversationId($conversations);
            $conversation = array_merge($record, [
                'id' => $id,
                'session_id' => $id,
                'messages' => [],
                'snapshots' => [],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $conversations[] = $conversation;
            $this->saveRuntimeConversations($conversations);

            return $this->normalizeConversationRecord($conversation);
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $params = [
                'session_code' => $record['session_code'],
                'source_page' => $record['source_page'],
                'entry_language' => $record['entry_language'],
                'resolved_language' => $record['resolved_language'],
                'country_code' => $record['country_code'],
                'device_type' => $record['device_type'],
                'utm_source' => $record['utm_source'],
                'is_valid_conversation' => $record['is_valid_conversation'],
                'inquiry_id' => $record['inquiry_id'],
                'last_message_at' => $record['last_message_at'],
            ];

            if ($existing === null) {
                $statement = $pdo->prepare(
                    'INSERT INTO chat_sessions (session_code, source, source_page, entry_language, resolved_language, country_code, device_type, utm_source, is_valid_conversation, inquiry_id, last_message_at, created_at, updated_at)
                     VALUES (:session_code, :source, :source_page, :entry_language, :resolved_language, :country_code, :device_type, :utm_source, :is_valid_conversation, :inquiry_id, :last_message_at, NOW(), NOW())'
                );
                $params['source'] = $record['source'];
            } else {
                $statement = $pdo->prepare(
                    'UPDATE chat_sessions
                     SET source_page = :source_page,
                         entry_language = :entry_language,
                         resolved_language = :resolved_language,
                         country_code = :country_code,
                         device_type = :device_type,
                         utm_source = :utm_source,
                         is_valid_conversation = :is_valid_conversation,
                         inquiry_id = :inquiry_id,
                         last_message_at = :last_message_at,
                         updated_at = NOW()
                     WHERE session_code = :session_code'
                );
            }

            $statement->execute($params);

            return $this->findConversationByCode($sessionCode) ?? [];
        }

        return [];
    }

    public function appendMessage(int $sessionId, array $message): array
    {
        $now = date('Y-m-d H:i:s');
        $payload = [
            'role' => (string) ($message['role'] ?? 'user'),
            'content' => (string) ($message['content'] ?? ''),
            'created_at' => $message['created_at'] ?? $now,
            'message_language' => (string) ($message['message_language'] ?? ''),
            'translated_text' => (string) ($message['translated_text'] ?? ''),
            'intent_code' => (string) ($message['intent_code'] ?? ''),
            'contains_contact_info' => (int) ($message['contains_contact_info'] ?? 0),
            'extracted_entities_json' => $message['extracted_entities_json'] ?? [],
        ];

        if ($this->preferRuntimeStorage()) {
            $conversations = $this->loadRuntimeConversations();
            foreach ($conversations as $index => $conversation) {
                if ($this->conversationSessionId($conversation) !== $sessionId) {
                    continue;
                }

                $messages = $this->arrayItems($conversation['messages'] ?? []);
                $messages[] = $payload;
                $conversations[$index]['messages'] = $messages;
                $conversations[$index]['last_message_at'] = $payload['created_at'];
                $conversations[$index]['updated_at'] = $now;
                $this->saveRuntimeConversations($conversations);

                return $payload;
            }

            return $payload;
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO chat_messages (session_id, message_role, message_language, content, translated_text, intent_code, contains_contact_info, extracted_entities_json, created_at)
                 VALUES (:session_id, :message_role, :message_language, :content, :translated_text, :intent_code, :contains_contact_info, :extracted_entities_json, :created_at)'
            );
            $statement->execute([
                'session_id' => $sessionId,
                'message_role' => $payload['role'],
                'message_language' => $payload['message_language'],
                'content' => $payload['content'],
                'translated_text' => $payload['translated_text'],
                'intent_code' => $payload['intent_code'],
                'contains_contact_info' => $payload['contains_contact_info'],
                'extracted_entities_json' => json_encode($payload['extracted_entities_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $payload['created_at'],
            ]);

            return $payload;
        }

        return $payload;
    }

    /**
     * @return array<int, array{role:string,content:string,created_at:string}>
     */
    public function listRecentMessages(int $sessionId, int $limit = 6): array
    {
        if ($sessionId <= 0 || $limit <= 0) {
            return [];
        }

        if ($this->preferRuntimeStorage()) {
            $conversation = $this->findConversationBySessionId($sessionId);
            if ($conversation === null) {
                return [];
            }

            $messages = array_slice($conversation['messages'] ?? [], -1 * max(1, min(40, $limit)));

            return array_map(static fn (array $row): array => [
                'role' => (string) ($row['role'] ?? ''),
                'content' => (string) ($row['content'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ], $messages);
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT message_role AS role, content, created_at
                 FROM chat_messages
                 WHERE session_id = :session_id
                 ORDER BY id DESC
                 LIMIT ' . (int) max(1, min(40, $limit))
            );
            $statement->execute(['session_id' => $sessionId]);
            $rows = array_reverse($statement->fetchAll() ?: []);

            return array_map(static fn (array $row): array => [
                'role' => (string) ($row['role'] ?? ''),
                'content' => (string) ($row['content'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ], $rows);
        }

        return [];
    }

    public function latestSnapshot(int $sessionId): ?array
    {
        if ($sessionId <= 0) {
            return null;
        }

        if ($this->preferRuntimeStorage()) {
            $conversation = $this->findConversationBySessionId($sessionId);
            $snapshot = $conversation['snapshots'][0] ?? null;

            return is_array($snapshot) ? $snapshot : null;
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT snapshot_version, contact_name, company_name, email, phone, whatsapp, country_code, product_interest, solution_interest, requirement_summary, confidence_score, created_at
                 FROM lead_snapshots
                 WHERE session_id = :session_id
                 ORDER BY snapshot_version DESC, id DESC
                 LIMIT 1'
            );
            $statement->execute(['session_id' => $sessionId]);
            $row = $statement->fetch();

            return is_array($row) ? $row : null;
        }

        return null;
    }

    public function appendSnapshot(int $sessionId, array $snapshot): array
    {
        $now = date('Y-m-d H:i:s');
        $payload = [
            'snapshot_version' => $this->nextSnapshotVersion($sessionId),
            'contact_name' => (string) ($snapshot['contact_name'] ?? ''),
            'company_name' => (string) ($snapshot['company_name'] ?? ''),
            'email' => (string) ($snapshot['email'] ?? ''),
            'phone' => (string) ($snapshot['phone'] ?? ''),
            'whatsapp' => (string) ($snapshot['whatsapp'] ?? ''),
            'country_code' => (string) ($snapshot['country_code'] ?? ''),
            'product_interest' => (string) ($snapshot['product_interest'] ?? ''),
            'solution_interest' => (string) ($snapshot['solution_interest'] ?? ''),
            'requirement_summary' => (string) ($snapshot['requirement_summary'] ?? ''),
            'confidence_score' => (float) ($snapshot['confidence_score'] ?? 0),
            'created_at' => $snapshot['created_at'] ?? $now,
        ];

        if ($this->preferRuntimeStorage()) {
            $conversations = $this->loadRuntimeConversations();
            foreach ($conversations as $index => $conversation) {
                if ($this->conversationSessionId($conversation) !== $sessionId) {
                    continue;
                }

                $snapshots = $this->arrayItems($conversation['snapshots'] ?? []);
                $snapshots[] = $payload;
                $conversations[$index]['snapshots'] = $snapshots;
                $conversations[$index]['updated_at'] = $now;
                $this->saveRuntimeConversations($conversations);

                return $payload;
            }

            return $payload;
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO lead_snapshots (session_id, snapshot_version, contact_name, company_name, email, phone, whatsapp, country_code, product_interest, solution_interest, requirement_summary, confidence_score, created_at)
                 VALUES (:session_id, :snapshot_version, :contact_name, :company_name, :email, :phone, :whatsapp, :country_code, :product_interest, :solution_interest, :requirement_summary, :confidence_score, :created_at)'
            );
            $statement->execute([
                'session_id' => $sessionId,
                'snapshot_version' => $payload['snapshot_version'],
                'contact_name' => $payload['contact_name'],
                'company_name' => $payload['company_name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'whatsapp' => $payload['whatsapp'],
                'country_code' => $payload['country_code'],
                'product_interest' => $payload['product_interest'],
                'solution_interest' => $payload['solution_interest'],
                'requirement_summary' => $payload['requirement_summary'],
                'confidence_score' => $payload['confidence_score'],
                'created_at' => $payload['created_at'],
            ]);

            return $payload;
        }

        return $payload;
    }

    public function findInquiryBySessionId(int $sessionId): ?array
    {
        if ($sessionId <= 0) {
            return null;
        }

        if ($this->preferRuntimeStorage()) {
            foreach ($this->loadRuntimeInquiries() as $record) {
                if ((int) ($record['session_id'] ?? 0) !== $sessionId) {
                    continue;
                }

                return $this->normalizeInquiryRecord($record);
            }

            return null;
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT *
                 FROM inquiries
                 WHERE session_id = :session_id
                 LIMIT 1'
            );
            $statement->execute(['session_id' => $sessionId]);
            $record = $statement->fetch();
            if (is_array($record)) {
                return $this->normalizeInquiryRecord($record);
            }
        }

        return null;
    }

    public function createInquiryFromSnapshot(int $sessionId, array $snapshot, array $browseTraces, string $languageCode, array $conversation): array
    {
        [$primaryType, $primaryValue] = $this->resolvePrimaryContact($snapshot);
        $now = date('Y-m-d H:i:s');

        if ($this->preferRuntimeStorage()) {
            $inquiries = $this->loadRuntimeInquiries();
            $record = [
                'id' => $this->nextRuntimeInquiryId($inquiries),
                'source' => 'ai',
                'session_id' => $sessionId,
                'primary_contact_type' => $primaryType,
                'primary_contact_value' => $primaryValue,
                'customer_name' => (string) ($snapshot['contact_name'] ?? ''),
                'company_name' => (string) ($snapshot['company_name'] ?? ''),
                'country_code' => (string) ($snapshot['country_code'] ?? ''),
                'language_code' => $languageCode,
                'product_interest' => (string) ($snapshot['product_interest'] ?? ''),
                'solution_interest' => (string) ($snapshot['solution_interest'] ?? ''),
                'requirement_summary' => (string) ($snapshot['requirement_summary'] ?? ''),
                'inquiry_score' => (float) ($snapshot['confidence_score'] ?? 0),
                'status' => 'new',
                'archive_status' => 'active',
                'assigned_to' => null,
                'first_response_at' => null,
                'browse_traces' => array_values($browseTraces),
                'change_logs' => [],
                'follow_ups' => [],
                'source_page' => (string) ($conversation['source_page'] ?? ''),
                'utm_source' => (string) ($conversation['utm_source'] ?? ''),
                'last_message_at' => $conversation['last_message_at'] ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $inquiries[] = $record;
            $this->saveRuntimeInquiries($inquiries);

            return $this->normalizeInquiryRecord($record);
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $params = [
                'source' => 'ai',
                'session_id' => $sessionId,
                'primary_contact_type' => $primaryType,
                'primary_contact_value' => $primaryValue,
                'customer_name' => (string) ($snapshot['contact_name'] ?? ''),
                'company_name' => (string) ($snapshot['company_name'] ?? ''),
                'country_code' => (string) ($snapshot['country_code'] ?? ''),
                'language_code' => $languageCode,
                'product_interest' => (string) ($snapshot['product_interest'] ?? ''),
                'solution_interest' => (string) ($snapshot['solution_interest'] ?? ''),
                'requirement_summary' => (string) ($snapshot['requirement_summary'] ?? ''),
                'inquiry_score' => (float) ($snapshot['confidence_score'] ?? 0),
                'status' => 'new',
                'assigned_to' => null,
                'first_response_at' => null,
                'browse_traces' => json_encode($browseTraces, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'change_logs' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'follow_ups' => json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $columns = [
                'source', 'session_id', 'primary_contact_type', 'primary_contact_value', 'customer_name', 'company_name',
                'country_code', 'language_code', 'product_interest', 'solution_interest', 'requirement_summary',
                'inquiry_score', 'status', 'assigned_to', 'first_response_at', 'browse_traces', 'change_logs', 'follow_ups',
            ];
            $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

            if ($this->hasTableColumn($pdo, 'inquiries', 'source_page')) {
                $columns[] = 'source_page';
                $placeholders[] = ':source_page';
                $params['source_page'] = (string) ($conversation['source_page'] ?? '');
            }
            if ($this->hasTableColumn($pdo, 'inquiries', 'utm_source')) {
                $columns[] = 'utm_source';
                $placeholders[] = ':utm_source';
                $params['utm_source'] = (string) ($conversation['utm_source'] ?? '');
            }
            if ($this->hasTableColumn($pdo, 'inquiries', 'last_message_at')) {
                $columns[] = 'last_message_at';
                $placeholders[] = ':last_message_at';
                $params['last_message_at'] = $conversation['last_message_at'] ?? $now;
            }

            $statement = $pdo->prepare(
                'INSERT INTO inquiries (' . implode(', ', $columns) . ', created_at, updated_at)
                 VALUES (' . implode(', ', $placeholders) . ', NOW(), NOW())'
            );
            $statement->execute($params);

            return $this->findInquiryById((int) $pdo->lastInsertId()) ?? [];
        }

        return [];
    }

    public function updateInquiryFromSnapshot(int $inquiryId, array $snapshot, array $browseTraces, string $languageCode, array $conversation): ?array
    {
        $existing = $this->findInquiryById($inquiryId);
        if ($existing === null) {
            return null;
        }

        [$primaryType, $primaryValue] = $this->resolvePrimaryContact($snapshot);
        $merged = array_merge($existing, [
            'primary_contact_type' => $primaryType !== '' ? $primaryType : (string) ($existing['primary_contact_type'] ?? ''),
            'primary_contact_value' => $primaryValue !== '' ? $primaryValue : (string) ($existing['primary_contact_value'] ?? ''),
            'customer_name' => $this->pickText($snapshot['contact_name'] ?? '', $existing['customer_name'] ?? ''),
            'company_name' => $this->pickText($snapshot['company_name'] ?? '', $existing['company_name'] ?? ''),
            'country_code' => $this->pickText($snapshot['country_code'] ?? '', $existing['country_code'] ?? ''),
            'language_code' => $languageCode !== '' ? $languageCode : (string) ($existing['language_code'] ?? ''),
            'product_interest' => $this->pickText($snapshot['product_interest'] ?? '', $existing['product_interest'] ?? ''),
            'solution_interest' => $this->pickText($snapshot['solution_interest'] ?? '', $existing['solution_interest'] ?? ''),
            'requirement_summary' => $this->pickText($snapshot['requirement_summary'] ?? '', $existing['requirement_summary'] ?? ''),
            'inquiry_score' => max((float) ($existing['inquiry_score'] ?? 0), (float) ($snapshot['confidence_score'] ?? 0)),
            'browse_traces' => $browseTraces,
            'source_page' => $this->pickText($conversation['source_page'] ?? '', $existing['source_page'] ?? ''),
            'utm_source' => $this->pickText($conversation['utm_source'] ?? '', $existing['utm_source'] ?? ''),
            'last_message_at' => $conversation['last_message_at'] ?? ($existing['last_message_at'] ?? null),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if ($this->preferRuntimeStorage()) {
            $inquiries = $this->loadRuntimeInquiries();
            foreach ($inquiries as $index => $record) {
                if ((int) ($record['id'] ?? 0) !== $inquiryId) {
                    continue;
                }

                $inquiries[$index] = array_merge($record, $merged, [
                    'browse_traces' => array_values($browseTraces),
                    'change_logs' => $record['change_logs'] ?? [],
                    'follow_ups' => $record['follow_ups'] ?? [],
                ]);
                $this->saveRuntimeInquiries($inquiries);

                return $this->normalizeInquiryRecord($inquiries[$index]);
            }

            return null;
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $params = [
                'id' => $inquiryId,
                'primary_contact_type' => $merged['primary_contact_type'],
                'primary_contact_value' => $merged['primary_contact_value'],
                'customer_name' => $merged['customer_name'],
                'company_name' => $merged['company_name'],
                'country_code' => $merged['country_code'],
                'language_code' => $merged['language_code'],
                'product_interest' => $merged['product_interest'],
                'solution_interest' => $merged['solution_interest'],
                'requirement_summary' => $merged['requirement_summary'],
                'inquiry_score' => $merged['inquiry_score'],
                'browse_traces' => json_encode($merged['browse_traces'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
            $setClauses = [
                'primary_contact_type = :primary_contact_type',
                'primary_contact_value = :primary_contact_value',
                'customer_name = :customer_name',
                'company_name = :company_name',
                'country_code = :country_code',
                'language_code = :language_code',
                'product_interest = :product_interest',
                'solution_interest = :solution_interest',
                'requirement_summary = :requirement_summary',
                'inquiry_score = :inquiry_score',
                'browse_traces = :browse_traces',
            ];

            if ($this->hasTableColumn($pdo, 'inquiries', 'source_page')) {
                $setClauses[] = 'source_page = :source_page';
                $params['source_page'] = $merged['source_page'];
            }
            if ($this->hasTableColumn($pdo, 'inquiries', 'utm_source')) {
                $setClauses[] = 'utm_source = :utm_source';
                $params['utm_source'] = $merged['utm_source'];
            }
            if ($this->hasTableColumn($pdo, 'inquiries', 'last_message_at')) {
                $setClauses[] = 'last_message_at = :last_message_at';
                $params['last_message_at'] = $merged['last_message_at'];
            }

            $statement = $pdo->prepare(
                'UPDATE inquiries
                 SET ' . implode(",\n                     ", $setClauses) . ',
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute($params);

            return $this->findInquiryById($inquiryId);
        }

        return null;
    }

    public function updateConversationInquiryLink(int $sessionId, int $inquiryId, bool $isValid = true): void
    {
        if ($sessionId <= 0) {
            return;
        }

        if ($this->preferRuntimeStorage()) {
            $conversations = $this->loadRuntimeConversations();
            foreach ($conversations as $index => $conversation) {
                if ($this->conversationSessionId($conversation) !== $sessionId) {
                    continue;
                }

                $conversations[$index]['inquiry_id'] = $inquiryId;
                $conversations[$index]['is_valid_conversation'] = $isValid ? 1 : 0;
                $conversations[$index]['updated_at'] = date('Y-m-d H:i:s');
                $this->saveRuntimeConversations($conversations);

                return;
            }

            return;
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'UPDATE chat_sessions
                 SET inquiry_id = :inquiry_id, is_valid_conversation = :is_valid_conversation, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $sessionId,
                'inquiry_id' => $inquiryId,
                'is_valid_conversation' => $isValid ? 1 : 0,
            ]);
        }
    }

    public function appendVisitorEvent(string $sessionCode, array $event): array
    {
        $sessionCode = trim($sessionCode);
        if ($sessionCode === '') {
            return [];
        }

        $normalizedEvent = [
            'page' => (string) ($event['page'] ?? ''),
            'title' => (string) ($event['title'] ?? ''),
            'referrer' => (string) ($event['referrer'] ?? ''),
            'visited_at' => (string) ($event['visited_at'] ?? date('Y-m-d H:i:s')),
            'language_code' => (string) ($event['language_code'] ?? ''),
        ];

        if ($this->preferRuntimeStorage()) {
            $events = $this->loadRuntimeVisitorEvents();
            $sessionEvents = $events[$sessionCode] ?? [];
            $sessionEvents = $this->arrayItems(is_array($sessionEvents) ? $sessionEvents : []);
            $sessionEvents[] = $normalizedEvent;
            $events[$sessionCode] = $sessionEvents;
            $this->saveRuntimeVisitorEvents($events);

            return $this->listVisitorEvents($sessionCode);
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO visitor_events (session_code, page, title, referrer, visited_at, language_code)
                 VALUES (:session_code, :page, :title, :referrer, :visited_at, :language_code)'
            );
            $statement->execute([
                'session_code' => $sessionCode,
                'page' => $normalizedEvent['page'],
                'title' => $normalizedEvent['title'],
                'referrer' => $normalizedEvent['referrer'],
                'visited_at' => $normalizedEvent['visited_at'],
                'language_code' => $normalizedEvent['language_code'],
            ]);

            return $this->listVisitorEvents($sessionCode);
        }

        return [];
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

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT page, title, referrer, visited_at, language_code
                 FROM visitor_events
                 WHERE session_code = :session_code
                 ORDER BY id ASC'
            );
            $statement->execute(['session_code' => $sessionCode]);
            $events = $statement->fetchAll() ?: [];

            return $this->normalizeVisitorEvents(is_array($events) ? $events : []);
        }

        return [];
    }

    public function syncInquiryBrowseTraces(int $inquiryId, array $browseTraces): ?array
    {
        $existing = $this->findInquiryById($inquiryId);
        if ($existing === null) {
            return null;
        }

        if ($this->preferRuntimeStorage()) {
            $inquiries = $this->loadRuntimeInquiries();
            foreach ($inquiries as $index => $record) {
                if ((int) ($record['id'] ?? 0) !== $inquiryId) {
                    continue;
                }

                $inquiries[$index]['browse_traces'] = array_values($browseTraces);
                $inquiries[$index]['updated_at'] = date('Y-m-d H:i:s');
                $this->saveRuntimeInquiries($inquiries);

                return $this->normalizeInquiryRecord($inquiries[$index]);
            }

            return null;
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'UPDATE inquiries SET browse_traces = :browse_traces, updated_at = NOW() WHERE id = :id'
            );
            $statement->execute([
                'id' => $inquiryId,
                'browse_traces' => json_encode($browseTraces, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return $this->findInquiryById($inquiryId);
        }

        return null;
    }

    private function findInquiryById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        if ($this->preferRuntimeStorage()) {
            foreach ($this->loadRuntimeInquiries() as $row) {
                if ((int) ($row['id'] ?? 0) !== $id) {
                    continue;
                }

                return $this->normalizeInquiryRecord($row);
            }

            return null;
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare('SELECT * FROM inquiries WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $row = $statement->fetch();
            if (is_array($row)) {
                return $this->normalizeInquiryRecord($row);
            }
        }

        return null;
    }

    private function nextSnapshotVersion(int $sessionId): int
    {
        if ($this->preferRuntimeStorage()) {
            $conversation = $this->findConversationBySessionId($sessionId);
            $snapshots = is_array($conversation['snapshots'] ?? null) ? $conversation['snapshots'] : [];
            $maxVersion = 0;
            foreach ($snapshots as $snapshot) {
                if (!is_array($snapshot)) {
                    continue;
                }
                $maxVersion = max($maxVersion, (int) ($snapshot['snapshot_version'] ?? 0));
            }

            return $maxVersion + 1;
        }

        $pdo = $this->databaseConnection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT COALESCE(MAX(snapshot_version), 0) AS max_version FROM lead_snapshots WHERE session_id = :session_id'
            );
            $statement->execute(['session_id' => $sessionId]);

            return (int) ($statement->fetch()['max_version'] ?? 0) + 1;
        }

        return 1;
    }

    private function resolvePrimaryContact(array $snapshot): array
    {
        if (trim((string) ($snapshot['email'] ?? '')) !== '') {
            return ['email', trim((string) $snapshot['email'])];
        }

        if (trim((string) ($snapshot['phone'] ?? '')) !== '') {
            return ['phone', trim((string) $snapshot['phone'])];
        }

        if (trim((string) ($snapshot['whatsapp'] ?? '')) !== '') {
            return ['whatsapp', trim((string) $snapshot['whatsapp'])];
        }

        return ['', ''];
    }

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

    private function pickText(mixed $preferred, mixed $fallback): string
    {
        $preferred = trim((string) $preferred);

        return $preferred !== '' ? $preferred : trim((string) $fallback);
    }

    private function databaseConnection(): ?PDO
    {
        $pdo = DatabaseManager::instance()->connection();

        return $pdo instanceof PDO ? $pdo : null;
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

    private function conversationSessionId(array $item): int
    {
        return (int) ($item['session_id'] ?? $item['id'] ?? 0);
    }

    private function findConversationBySessionId(int $sessionId): ?array
    {
        foreach ($this->loadRuntimeConversations() as $record) {
            if ($this->conversationSessionId($record) !== $sessionId) {
                continue;
            }

            return $this->normalizeConversationRecord($record);
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
        $item['archive_status'] = $this->normalizeArchiveStatus($item['archive_status'] ?? null);
        $item['messages'] = $this->normalizeConversationMessages($item['messages'] ?? []);
        $item['snapshots'] = $this->normalizeConversationSnapshots($item['snapshots'] ?? []);
        $item['message_count'] = count($item['messages']);
        $item['snapshot_count'] = count($item['snapshots']);
        $item['is_valid_conversation'] = (int) ($item['is_valid_conversation'] ?? 0);
        $item['inquiry_id'] = (int) ($item['inquiry_id'] ?? 0);

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

    private function normalizeInquiryRecord(array $item): array
    {
        $item['id'] = (int) ($item['id'] ?? 0);
        $item['source'] = (string) ($item['source'] ?? 'ai');
        $item['session_id'] = (int) ($item['session_id'] ?? 0);
        $item['primary_contact_type'] = (string) ($item['primary_contact_type'] ?? '');
        $item['primary_contact_value'] = (string) ($item['primary_contact_value'] ?? '');
        $item['customer_name'] = (string) ($item['customer_name'] ?? '');
        $item['company_name'] = (string) ($item['company_name'] ?? '');
        $item['country_code'] = (string) ($item['country_code'] ?? '');
        $item['language_code'] = (string) ($item['language_code'] ?? '');
        $item['product_interest'] = (string) ($item['product_interest'] ?? '');
        $item['solution_interest'] = (string) ($item['solution_interest'] ?? '');
        $item['requirement_summary'] = (string) ($item['requirement_summary'] ?? '');
        $item['archive_status'] = $this->normalizeArchiveStatus($item['archive_status'] ?? null);
        $item['browse_traces'] = $this->normalizeVisitorEvents($this->decodeJsonField($item['browse_traces'] ?? null));
        $item['change_logs'] = $this->normalizeChangeLogs($this->decodeJsonField($item['change_logs'] ?? null));
        $item['follow_ups'] = $this->normalizeFollowUps($this->decodeJsonField($item['follow_ups'] ?? null));
        $item['source_page'] = (string) ($item['source_page'] ?? '');
        $item['utm_source'] = (string) ($item['utm_source'] ?? '');
        $item['last_message_at'] = $item['last_message_at'] ?? null;

        return $item;
    }

    private function normalizeArchiveStatus(mixed $value): string
    {
        $archiveStatus = trim((string) $value);

        return $archiveStatus !== '' ? $archiveStatus : 'active';
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function arrayItems(array $rows): array
    {
        return array_values(array_filter($rows, 'is_array'));
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
     * @return array<int, array<string, mixed>>
     */
    private function loadRuntimeInquiries(): array
    {
        return $this->loadRuntimeList('inquiries.json');
    }

    private function saveRuntimeInquiries(array $rows): void
    {
        $this->saveRuntimeList('inquiries.json', $rows);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function loadRuntimeVisitorEvents(): array
    {
        $payload = $this->loadRuntimeFile('visitor_events.json');

        return is_array($payload) ? $payload : [];
    }

    private function saveRuntimeVisitorEvents(array $payload): void
    {
        $this->saveRuntimeFile('visitor_events.json', $payload);
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
            $this->runtimeStoragePath('inquiries.json'),
            $this->runtimeStoragePath('visitor_events.json'),
        ]);
    }

    private function nextRuntimeConversationId(array $rows): int
    {
        $maxId = 0;
        foreach ($rows as $row) {
            $maxId = max($maxId, $this->conversationSessionId($row));
        }

        return $maxId + 1;
    }

    private function nextRuntimeInquiryId(array $rows): int
    {
        $maxId = 0;
        foreach ($rows as $row) {
            $maxId = max($maxId, (int) ($row['id'] ?? 0));
        }

        return $maxId + 1;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, string>>
     */
    private function normalizeVisitorEvents(array $rows): array
    {
        $events = [];
        foreach ($rows as $item) {
            if (!is_array($item)) {
                continue;
            }

            $events[] = [
                'page' => (string) ($item['page'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'referrer' => (string) ($item['referrer'] ?? ''),
                'visited_at' => (string) ($item['visited_at'] ?? ''),
                'language_code' => (string) ($item['language_code'] ?? ''),
            ];
        }

        usort($events, fn (array $left, array $right): int => strcmp($left['visited_at'], $right['visited_at']));

        return $events;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, string>>
     */
    private function normalizeChangeLogs(array $rows): array
    {
        $logs = [];
        foreach ($rows as $item) {
            if (!is_array($item)) {
                continue;
            }

            $logs[] = [
                'field' => (string) ($item['field'] ?? ''),
                'from' => (string) ($item['from'] ?? ''),
                'to' => (string) ($item['to'] ?? ''),
                'changed_at' => (string) ($item['changed_at'] ?? ''),
            ];
        }

        return $logs;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, string>>
     */
    private function normalizeFollowUps(array $rows): array
    {
        $followUps = [];
        foreach ($rows as $item) {
            if (!is_array($item)) {
                continue;
            }

            $followUps[] = [
                'content' => (string) ($item['content'] ?? ''),
                'created_at' => (string) ($item['created_at'] ?? ''),
            ];
        }

        return $followUps;
    }
}
