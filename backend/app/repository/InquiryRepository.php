<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class InquiryRepository
{
    public function listInquiries(): array
    {
        if ($this->preferRuntimeStorage()) {
            $conversations = $this->runtimeConversationsBySessionId();
            $rows = array_map(function (array $item) use ($conversations): array {
                $conversation = $conversations[(int) ($item['session_id'] ?? 0)] ?? null;

                return $this->normalizeInquiryRecord($this->mergeInquiryConversation($item, $conversation));
            }, $this->loadRuntimeInquiries());

            usort($rows, fn (array $left, array $right): int => $this->compareInquiryRows($left, $right));

            return $rows;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $inquiryArchiveSelect = $this->hasTableColumn($pdo, 'inquiries', 'archive_status')
                ? 'i.archive_status'
                : "'active' AS archive_status";
            $statement = $pdo->query(
                'SELECT i.id, i.source, i.session_id, i.primary_contact_type, i.primary_contact_value, i.customer_name, i.company_name, i.country_code, i.language_code, i.product_interest, i.solution_interest, i.requirement_summary, i.inquiry_score, i.status, ' . $inquiryArchiveSelect . ', i.assigned_to, i.first_response_at, i.created_at, i.updated_at,
                        cs.source_page, cs.utm_source, cs.last_message_at,
                        COALESCE(cm.message_count, 0) AS message_count,
                        COALESCE(ls.snapshot_count, 0) AS snapshot_count
                 FROM inquiries i
                 LEFT JOIN chat_sessions cs ON cs.id = i.session_id
                 LEFT JOIN (SELECT session_id, COUNT(*) AS message_count FROM chat_messages GROUP BY session_id) cm ON cm.session_id = i.session_id
                 LEFT JOIN (SELECT session_id, COUNT(*) AS snapshot_count FROM lead_snapshots GROUP BY session_id) ls ON ls.session_id = i.session_id
                 ORDER BY i.updated_at DESC, i.id DESC'
            );
            $rows = $statement->fetchAll();

            return array_map(fn (array $item): array => $this->normalizeInquiryRecord($item), is_array($rows) ? $rows : []);
        }

        return [];
    }

    public function findInquiry(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        if ($this->preferRuntimeStorage()) {
            $conversations = $this->runtimeConversationsBySessionId();
            foreach ($this->loadRuntimeInquiries() as $item) {
                if ((int) ($item['id'] ?? 0) !== $id) {
                    continue;
                }

                $conversation = $conversations[(int) ($item['session_id'] ?? 0)] ?? null;

                return $this->normalizeInquiryRecord($this->mergeInquiryConversation($item, $conversation));
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $inquiryArchiveSelect = $this->hasTableColumn($pdo, 'inquiries', 'archive_status')
                ? 'i.archive_status'
                : "'active' AS archive_status";
            $statement = $pdo->prepare(
                'SELECT i.id, i.source, i.session_id, i.primary_contact_type, i.primary_contact_value, i.customer_name, i.company_name, i.country_code, i.language_code, i.product_interest, i.solution_interest, i.requirement_summary, i.inquiry_score, i.status, ' . $inquiryArchiveSelect . ', i.assigned_to, i.first_response_at, i.browse_traces, i.change_logs, i.follow_ups, i.created_at, i.updated_at,
                        cs.source_page, cs.utm_source, cs.last_message_at
                 FROM inquiries i
                 LEFT JOIN chat_sessions cs ON cs.id = i.session_id
                 WHERE i.id = :id
                 LIMIT 1'
            );
            $statement->execute(['id' => $id]);
            $record = $statement->fetch();
            if (is_array($record)) {
                return $this->normalizeInquiryRecord($record);
            }
        }

        return null;
    }

    public function listConversations(): array
    {
        if ($this->preferRuntimeStorage()) {
            $rows = array_map(fn (array $item): array => $this->normalizeConversationRecord($item), $this->loadRuntimeConversations());
            usort($rows, fn (array $left, array $right): int => $this->compareConversationRows($left, $right));

            return $rows;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $conversationArchiveSelect = $this->hasTableColumn($pdo, 'chat_sessions', 'archive_status')
                ? 'archive_status'
                : "'active' AS archive_status";
            $statement = $pdo->query(
                'SELECT id AS session_id, session_code, source, source_page, entry_language, resolved_language, country_code, device_type, utm_source, is_valid_conversation, inquiry_id, ' . $conversationArchiveSelect . ', last_message_at, created_at, updated_at
                 FROM chat_sessions
                 ORDER BY updated_at DESC, id DESC'
            );
            $rows = $statement->fetchAll();

            return is_array($rows) ? array_map(fn (array $item): array => $this->normalizeConversationRecord($item), $rows) : [];
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
            $conversationArchiveSelect = $this->hasTableColumn($pdo, 'chat_sessions', 'archive_status')
                ? 'archive_status'
                : "'active' AS archive_status";
            $statement = $pdo->prepare(
                'SELECT id AS session_id, session_code, source, source_page, entry_language, resolved_language, country_code, device_type, utm_source, is_valid_conversation, inquiry_id, ' . $conversationArchiveSelect . ', last_message_at, created_at, updated_at
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
                $messages = $messageStatement->fetchAll() ?: [];
                $conversation['messages'] = array_map(fn (array $item): array => $this->normalizeConversationMessage($item), is_array($messages) ? $messages : []);

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

    public function updateInquiryStatus(int $id, string $status, array $extra = []): ?array
    {
        if ($this->preferRuntimeStorage()) {
            $inquiries = $this->loadRuntimeInquiries();
            foreach ($inquiries as $index => $inquiry) {
                if ((int) ($inquiry['id'] ?? 0) !== $id) {
                    continue;
                }

                $inquiries[$index]['status'] = $status;
                if (array_key_exists('first_response_at', $extra)) {
                    $inquiries[$index]['first_response_at'] = $extra['first_response_at'];
                }
                if (array_key_exists('change_logs', $extra)) {
                    $inquiries[$index]['change_logs'] = is_array($extra['change_logs']) ? array_values($extra['change_logs']) : [];
                }
                $inquiries[$index]['updated_at'] = date('Y-m-d H:i:s');
                $this->saveRuntimeInquiries($inquiries);

                return $this->findInquiry($id);
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $fields = ['status = :status', 'updated_at = NOW()'];
            $params = [
                'id' => $id,
                'status' => $status,
            ];

            if (array_key_exists('first_response_at', $extra)) {
                $fields[] = 'first_response_at = :first_response_at';
                $params['first_response_at'] = $extra['first_response_at'];
            }
            if (array_key_exists('change_logs', $extra)) {
                $fields[] = 'change_logs = :change_logs';
                $params['change_logs'] = json_encode($extra['change_logs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $statement = $pdo->prepare(sprintf('UPDATE inquiries SET %s WHERE id = :id', implode(', ', $fields)));
            $statement->execute($params);

            return $this->findInquiry($id);
        }

        return null;
    }

    public function updateInquiry(int $id, array $payload): ?array
    {
        $existing = $this->findInquiry($id);
        if ($existing === null) {
            return null;
        }

        $next = array_merge($existing, $payload);

        if ($this->preferRuntimeStorage()) {
            $inquiries = $this->loadRuntimeInquiries();
            foreach ($inquiries as $index => $inquiry) {
                if ((int) ($inquiry['id'] ?? 0) !== $id) {
                    continue;
                }

                $inquiries[$index]['country_code'] = $next['country_code'] ?? '';
                $inquiries[$index]['language_code'] = $next['language_code'] ?? '';
                $inquiries[$index]['product_interest'] = $next['product_interest'] ?? '';
                $inquiries[$index]['solution_interest'] = $next['solution_interest'] ?? '';
                $inquiries[$index]['assigned_to'] = $next['assigned_to'] ?? null;
                $inquiries[$index]['status'] = $next['status'] ?? 'new';
                $inquiries[$index]['first_response_at'] = $next['first_response_at'] ?? null;
                $inquiries[$index]['change_logs'] = is_array($next['change_logs'] ?? null) ? array_values($next['change_logs']) : [];
                $inquiries[$index]['updated_at'] = date('Y-m-d H:i:s');
                $this->saveRuntimeInquiries($inquiries);

                return $this->findInquiry($id);
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'UPDATE inquiries
                 SET country_code = :country_code,
                     language_code = :language_code,
                     product_interest = :product_interest,
                     solution_interest = :solution_interest,
                     assigned_to = :assigned_to,
                     status = :status,
                     first_response_at = :first_response_at,
                     change_logs = :change_logs,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->bindValue(':id', $id, PDO::PARAM_INT);
            $statement->bindValue(':country_code', $next['country_code'] ?? null);
            $statement->bindValue(':language_code', $next['language_code'] ?? null);
            $statement->bindValue(':product_interest', $next['product_interest'] ?? null);
            $statement->bindValue(':solution_interest', $next['solution_interest'] ?? null);
            if ($next['assigned_to'] === null) {
                $statement->bindValue(':assigned_to', null, PDO::PARAM_NULL);
            } else {
                $statement->bindValue(':assigned_to', (int) $next['assigned_to'], PDO::PARAM_INT);
            }
            $statement->bindValue(':status', $next['status'] ?? 'new');
            $statement->bindValue(':first_response_at', $next['first_response_at'] ?? null);
            $statement->bindValue(':change_logs', json_encode($next['change_logs'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $statement->execute();

            return $this->findInquiry($id);
        }

        return null;
    }

    public function updateArchiveStatus(int $id, string $archiveStatus): ?array
    {
        $archiveStatus = $this->normalizeArchiveStatus($archiveStatus);

        if ($this->preferRuntimeStorage()) {
            $inquiries = $this->loadRuntimeInquiries();
            foreach ($inquiries as $index => $inquiry) {
                if ((int) ($inquiry['id'] ?? 0) !== $id) {
                    continue;
                }

                $inquiries[$index]['archive_status'] = $archiveStatus;
                $inquiries[$index]['updated_at'] = date('Y-m-d H:i:s');
                $this->saveRuntimeInquiries($inquiries);

                return $this->findInquiry($id);
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            if (!$this->hasTableColumn($pdo, 'inquiries', 'archive_status')) {
                $updated = $this->findInquiry($id);
                if ($updated === null) {
                    return null;
                }

                $updated['archive_status'] = $archiveStatus;

                return $updated;
            }

            $statement = $pdo->prepare(
                'UPDATE inquiries
                 SET archive_status = :archive_status, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'archive_status' => $archiveStatus,
            ]);

            return $this->findInquiry($id);
        }

        return null;
    }

    public function createInquiry(array $payload): array
    {
        $now = date('Y-m-d H:i:s');

        $record = array_merge([
            'id' => 0,
            'source' => 'lead_form',
            'session_id' => 0,
            'primary_contact_type' => '',
            'primary_contact_value' => '',
            'customer_name' => '',
            'company_name' => '',
            'country_code' => '',
            'language_code' => '',
            'product_interest' => '',
            'solution_interest' => '',
            'requirement_summary' => '',
            'inquiry_score' => null,
            'status' => 'new',
            'archive_status' => 'active',
            'assigned_to' => null,
            'first_response_at' => null,
            'browse_traces' => [],
            'change_logs' => [],
            'follow_ups' => [],
            'source_page' => '',
            'utm_source' => '',
            'last_message_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $payload);

        if ($this->preferRuntimeStorage()) {
            $inquiries = $this->loadRuntimeInquiries();
            $record['id'] = $this->nextRuntimeInquiryId($inquiries);
            $record['browse_traces'] = is_array($record['browse_traces'] ?? null) ? array_values($record['browse_traces']) : [];
            $record['change_logs'] = is_array($record['change_logs'] ?? null) ? array_values($record['change_logs']) : [];
            $record['follow_ups'] = is_array($record['follow_ups'] ?? null) ? array_values($record['follow_ups']) : [];
            $inquiries[] = $record;
            $this->saveRuntimeInquiries($inquiries);

            return $this->findInquiry((int) $record['id']) ?? $record;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO inquiries (source, session_id, primary_contact_type, primary_contact_value, customer_name, company_name, country_code, language_code, product_interest, solution_interest, requirement_summary, inquiry_score, status, assigned_to, browse_traces, change_logs, follow_ups, source_page, utm_source, last_message_at, created_at, updated_at)
                 VALUES (:source, :session_id, :primary_contact_type, :primary_contact_value, :customer_name, :company_name, :country_code, :language_code, :product_interest, :solution_interest, :requirement_summary, :inquiry_score, :status, :assigned_to, :browse_traces, :change_logs, :follow_ups, :source_page, :utm_source, :last_message_at, NOW(), NOW())'
            );
            $statement->execute([
                'source' => $record['source'],
                'session_id' => (int) ($record['session_id'] ?? 0),
                'primary_contact_type' => $record['primary_contact_type'],
                'primary_contact_value' => $record['primary_contact_value'],
                'customer_name' => $record['customer_name'],
                'company_name' => $record['company_name'],
                'country_code' => $record['country_code'],
                'language_code' => $record['language_code'],
                'product_interest' => $record['product_interest'],
                'solution_interest' => $record['solution_interest'],
                'requirement_summary' => $record['requirement_summary'],
                'inquiry_score' => $record['inquiry_score'],
                'status' => $record['status'],
                'assigned_to' => $record['assigned_to'],
                'browse_traces' => json_encode($record['browse_traces'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'change_logs' => json_encode($record['change_logs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'follow_ups' => json_encode($record['follow_ups'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'source_page' => $record['source_page'],
                'utm_source' => $record['utm_source'],
                'last_message_at' => $record['last_message_at'],
            ]);

            return $this->findInquiry((int) $pdo->lastInsertId()) ?? $record;
        }

        return $record;
    }

    public function appendFollowUp(int $id, array $followUp, ?array $changeLogs = null): ?array
    {
        $existing = $this->findInquiry($id);
        if ($existing === null) {
            return null;
        }

        $followUps = is_array($existing['follow_ups'] ?? null) ? $existing['follow_ups'] : [];
        $followUps[] = $followUp;
        $nextChangeLogs = $changeLogs;
        if ($nextChangeLogs === null) {
            $nextChangeLogs = is_array($existing['change_logs'] ?? null) ? $existing['change_logs'] : [];
        }

        if ($this->preferRuntimeStorage()) {
            $inquiries = $this->loadRuntimeInquiries();
            foreach ($inquiries as $index => $inquiry) {
                if ((int) ($inquiry['id'] ?? 0) !== $id) {
                    continue;
                }

                $inquiries[$index]['follow_ups'] = array_values($followUps);
                $inquiries[$index]['change_logs'] = is_array($nextChangeLogs) ? array_values($nextChangeLogs) : [];
                $inquiries[$index]['updated_at'] = date('Y-m-d H:i:s');
                $this->saveRuntimeInquiries($inquiries);

                return $this->findInquiry($id);
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'UPDATE inquiries
                 SET follow_ups = :follow_ups, change_logs = :change_logs, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'follow_ups' => json_encode($followUps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'change_logs' => json_encode($nextChangeLogs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return $this->findInquiry($id);
        }

        return null;
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
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    private function normalizeConversationMessage(array $message): array
    {
        $message['role'] = (string) ($message['role'] ?? '');
        $message['content'] = (string) ($message['content'] ?? '');
        $message['created_at'] = (string) ($message['created_at'] ?? '');
        $message['message_language'] = (string) ($message['message_language'] ?? '');
        $message['translated_text'] = (string) ($message['translated_text'] ?? '');
        $message['intent_code'] = (string) ($message['intent_code'] ?? '');
        $message['extracted_entities_json'] = $this->decodeJsonField($message['extracted_entities_json'] ?? null);
        $message['contains_contact_info'] = (int) ($message['contains_contact_info'] ?? 0);

        return $message;
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
        $item['status'] = (string) ($item['status'] ?? 'new');
        $item['archive_status'] = $this->normalizeArchiveStatus($item['archive_status'] ?? null);
        $item['browse_traces'] = $this->normalizeVisitorEvents($this->decodeJsonField($item['browse_traces'] ?? null));
        $item['change_logs'] = $this->normalizeChangeLogs($this->decodeJsonField($item['change_logs'] ?? null));
        $item['follow_ups'] = $this->normalizeFollowUps($this->decodeJsonField($item['follow_ups'] ?? null));
        $item['message_count'] = (int) ($item['message_count'] ?? 0);
        $item['snapshot_count'] = (int) ($item['snapshot_count'] ?? 0);
        $item['source_page'] = (string) ($item['source_page'] ?? '');
        $item['utm_source'] = (string) ($item['utm_source'] ?? '');
        $item['last_message_at'] = $item['last_message_at'] ?? null;

        return $item;
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
        $item['is_valid_conversation'] = (int) ($item['is_valid_conversation'] ?? 0);
        $item['inquiry_id'] = (int) ($item['inquiry_id'] ?? 0);
        $item['archive_status'] = $this->normalizeArchiveStatus($item['archive_status'] ?? null);
        $item['messages'] = array_map(fn (array $message): array => $this->normalizeConversationMessage($message), $this->arrayItems($item['messages'] ?? []));
        $item['snapshots'] = $this->normalizeConversationSnapshots($item['snapshots'] ?? []);
        $item['message_count'] = (int) ($item['message_count'] ?? count($item['messages']));
        $item['snapshot_count'] = (int) ($item['snapshot_count'] ?? count($item['snapshots']));

        $latestSnapshot = is_array($item['snapshots'][0] ?? null) ? $item['snapshots'][0] : [];
        foreach (['contact_name', 'company_name', 'email', 'phone', 'whatsapp', 'product_interest', 'solution_interest', 'requirement_summary', 'confidence_score'] as $field) {
            if (array_key_exists($field, $item) && $item[$field] !== null && $item[$field] !== '') {
                continue;
            }

            $item[$field] = $latestSnapshot[$field] ?? ($field === 'confidence_score' ? null : '');
        }

        return $item;
    }

    private function normalizeArchiveStatus(mixed $value): string
    {
        $archiveStatus = trim((string) $value);

        return $archiveStatus !== '' ? $archiveStatus : 'active';
    }

    /**
     * @param array<string, mixed> $inquiry
     * @param array<string, mixed>|null $conversation
     * @return array<string, mixed>
     */
    private function mergeInquiryConversation(array $inquiry, ?array $conversation): array
    {
        $normalizedConversation = is_array($conversation) ? $this->normalizeConversationRecord($conversation) : null;

        $inquiry['browse_traces'] = $this->decodeJsonField($inquiry['browse_traces'] ?? null);
        $inquiry['change_logs'] = $this->decodeJsonField($inquiry['change_logs'] ?? null);
        $inquiry['follow_ups'] = $this->decodeJsonField($inquiry['follow_ups'] ?? null);
        $inquiry['source_page'] = (string) ($inquiry['source_page'] ?? ($normalizedConversation['source_page'] ?? ''));
        $inquiry['utm_source'] = (string) ($inquiry['utm_source'] ?? ($normalizedConversation['utm_source'] ?? ''));
        $inquiry['last_message_at'] = $inquiry['last_message_at'] ?? ($normalizedConversation['last_message_at'] ?? null);
        $inquiry['message_count'] = (int) ($inquiry['message_count'] ?? ($normalizedConversation['message_count'] ?? 0));
        $inquiry['snapshot_count'] = (int) ($inquiry['snapshot_count'] ?? ($normalizedConversation['snapshot_count'] ?? 0));

        return $inquiry;
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
     * @return array<int, array<string, mixed>>
     */
    private function loadRuntimeConversations(): array
    {
        return $this->loadRuntimeList('conversations.json');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function runtimeConversationsBySessionId(): array
    {
        $mapped = [];
        foreach ($this->loadRuntimeConversations() as $conversation) {
            $mapped[$this->conversationSessionId($conversation)] = $conversation;
        }

        return $mapped;
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
            $this->runtimeStoragePath('inquiries.json'),
            $this->runtimeStoragePath('conversations.json'),
        ]);
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
     * @return array<int, array<string, mixed>>
     */
    private function arrayItems(array $rows): array
    {
        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeConversationSnapshots(array $rows): array
    {
        $snapshots = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $row['snapshot_version'] = (int) ($row['snapshot_version'] ?? ($index + 1));
            $row['contact_name'] = (string) ($row['contact_name'] ?? '');
            $row['company_name'] = (string) ($row['company_name'] ?? '');
            $row['email'] = (string) ($row['email'] ?? '');
            $row['phone'] = (string) ($row['phone'] ?? '');
            $row['whatsapp'] = (string) ($row['whatsapp'] ?? '');
            $row['country_code'] = (string) ($row['country_code'] ?? '');
            $row['product_interest'] = (string) ($row['product_interest'] ?? '');
            $row['solution_interest'] = (string) ($row['solution_interest'] ?? '');
            $row['requirement_summary'] = (string) ($row['requirement_summary'] ?? '');
            $row['created_at'] = (string) ($row['created_at'] ?? '');
            $snapshots[] = $row;
        }

        usort($snapshots, function (array $left, array $right): int {
            $versionCompare = ((int) ($right['snapshot_version'] ?? 0)) <=> ((int) ($left['snapshot_version'] ?? 0));
            if ($versionCompare !== 0) {
                return $versionCompare;
            }

            return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
        });

        return $snapshots;
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
     * @param array<int, mixed> $rows
     * @return array<int, array<string, string>>
     */
    private function normalizeChangeLogs(array $rows): array
    {
        $logs = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $logs[] = [
                'field' => (string) ($row['field'] ?? ''),
                'from' => (string) ($row['from'] ?? ''),
                'to' => (string) ($row['to'] ?? ''),
                'changed_at' => (string) ($row['changed_at'] ?? ''),
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
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $followUps[] = [
                'content' => (string) ($row['content'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $followUps;
    }

    private function conversationSessionId(array $item): int
    {
        return (int) ($item['session_id'] ?? $item['id'] ?? 0);
    }

    private function compareInquiryRows(array $left, array $right): int
    {
        $updatedCompare = strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
        if ($updatedCompare !== 0) {
            return $updatedCompare;
        }

        return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
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
