<?php

declare(strict_types=1);

namespace app\service\inquiry;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\ConversationRepository;
use app\repository\InquiryRepository;
use app\repository\PublicChatRepository;
use app\service\log\OperationLogService;

final class InquiryService
{
    private const int MAX_NAME_LENGTH = 80;
    private const int MAX_PHONE_LENGTH = 40;
    private const int MAX_EMAIL_LENGTH = 120;
    private const int MAX_MESSAGE_LENGTH = 2000;

    public function __construct(
        private readonly InquiryRepository $inquiryRepository = new InquiryRepository(),
        private readonly ConversationRepository $conversationRepository = new ConversationRepository(),
        private readonly PublicChatRepository $publicChatRepository = new PublicChatRepository(),
        private readonly ConversationService $conversationService = new ConversationService(),
        private readonly OperationLogService $operationLogService = new OperationLogService()
    ) {
    }

    public function list(array $query = []): array
    {
        $normalized = $this->normalizeListQuery($query);
        $filtered = $this->filterListItems(
            array_map(fn (array $item): array => $this->normalizeInquiryListItem($item), $this->inquiryRepository->listInquiries()),
            $normalized
        );
        $sorted = $this->sortListItems($filtered, $normalized);
        $paged = $this->paginateListItems($sorted, $normalized);

        return [
            'items' => $paged['items'],
            'filters' => ['status', 'archive_status', 'country_code', 'language_code', 'source', 'date_from', 'date_to', 'keyword'],
            'pagination' => $paged['pagination'],
            'sort' => [
                'field' => (string) $normalized['sort_field'],
                'order' => (string) $normalized['sort_order'],
            ],
            'stats' => $this->buildListStats($sorted),
        ];
    }

    public function stats(array $query = []): array
    {
        $normalized = $this->normalizeListQuery($query);
        $filtered = $this->filterListItems(
            array_map(fn (array $item): array => $this->normalizeInquiryListItem($item), $this->inquiryRepository->listInquiries()),
            $normalized
        );

        return $this->buildListStats($filtered);
    }

    public function export(array $query = []): array
    {
        $normalized = $this->normalizeListQuery($query);
        $normalized['page'] = 1;
        $normalized['page_size'] = 5000;
        $filtered = $this->filterListItems(
            array_map(fn (array $item): array => $this->normalizeInquiryListItem($item), $this->inquiryRepository->listInquiries()),
            $normalized
        );
        $sorted = $this->sortListItems($filtered, $normalized);

        return [
            'filename' => 'inquiries-' . date('Ymd-His') . '.csv',
            'columns' => ['id', 'source', 'customer_name', 'company_name', 'country_code', 'language_code', 'product_interest', 'solution_interest', 'primary_contact_type', 'primary_contact_value', 'status', 'created_at', 'updated_at'],
            'rows' => array_map(static function (array $item): array {
                return [
                    'id' => (int) ($item['id'] ?? 0),
                    'source' => (string) ($item['source'] ?? ''),
                    'customer_name' => (string) ($item['customer_name'] ?? ''),
                    'company_name' => (string) ($item['company_name'] ?? ''),
                    'country_code' => (string) ($item['country_code'] ?? ''),
                    'language_code' => (string) ($item['language_code'] ?? ''),
                    'product_interest' => (string) ($item['product_interest'] ?? ''),
                    'solution_interest' => (string) ($item['solution_interest'] ?? ''),
                    'primary_contact_type' => (string) ($item['primary_contact_type'] ?? ''),
                    'primary_contact_value' => (string) ($item['primary_contact_value'] ?? ''),
                    'status' => (string) ($item['status'] ?? ''),
                    'created_at' => (string) ($item['created_at'] ?? ''),
                    'updated_at' => (string) ($item['updated_at'] ?? ''),
                ];
            }, $sorted),
            'total' => count($sorted),
        ];
    }

    public function createFromLeadForm(array $input): array
    {
        $name = $this->normalizeLeadField((string) ($input['name'] ?? ''), self::MAX_NAME_LENGTH);
        $email = $this->normalizeLeadField((string) ($input['email'] ?? ''), self::MAX_EMAIL_LENGTH);
        $phone = $this->normalizeLeadField((string) ($input['phone'] ?? ''), self::MAX_PHONE_LENGTH);
        $message = $this->normalizeLeadField((string) ($input['message'] ?? ''), self::MAX_MESSAGE_LENGTH);

        if ($name === '') {
            throw new BusinessException('Name is required.', ErrorCode::INVALID_PARAMS);
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BusinessException('璇峰～鍐欐湁鏁堢殑閭鍦板潃', ErrorCode::INVALID_PARAMS);
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new BusinessException('Message is too long.', ErrorCode::INVALID_PARAMS);
        }

        if ($phone !== '' && mb_strlen($phone) > self::MAX_PHONE_LENGTH) {
            throw new BusinessException('Phone number is too long.', ErrorCode::INVALID_PARAMS);
        }

        $primaryContactType = 'email';
        $primaryContactValue = $email;
        if ($phone !== '') {
            $primaryContactType = 'phone';
            $primaryContactValue = $phone;
        }

        $requirement = $message !== ''
            ? $message
            : sprintf('Lead submitted from %s (%s)', $name, $email);

        $record = $this->inquiryRepository->createInquiry([
            'source' => 'lead_form',
            'session_id' => 0,
            'primary_contact_type' => $primaryContactType,
            'primary_contact_value' => $primaryContactValue,
            'customer_name' => $name,
            'company_name' => '',
            'country_code' => '',
            'language_code' => '',
            'product_interest' => '',
            'solution_interest' => '',
            'requirement_summary' => $requirement,
            'inquiry_score' => null,
            'status' => 'new',
        ]);

        return [
            'id' => (int) ($record['id'] ?? 0),
            'source' => (string) ($record['source'] ?? 'lead_form'),
            'customer_name' => (string) ($record['customer_name'] ?? ''),
            'primary_contact_type' => (string) ($record['primary_contact_type'] ?? ''),
            'primary_contact_value' => (string) ($record['primary_contact_value'] ?? ''),
            'requirement_summary' => (string) ($record['requirement_summary'] ?? ''),
            'status' => (string) ($record['status'] ?? 'new'),
            'created_at' => (string) ($record['created_at'] ?? ''),
        ];
    }

    private function normalizeLeadField(string $value, int $maxLength): string
    {
        $normalized = trim($value);

        if ($maxLength <= 0) {
            return $normalized;
        }

        return mb_strlen($normalized, 'UTF-8') > $maxLength
            ? mb_substr($normalized, 0, $maxLength, 'UTF-8')
            : $normalized;
    }

    public function detail(int $id): array
    {
        $inquiry = $this->inquiryRepository->findInquiry($id);
        if ($inquiry === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $conversation = $this->conversationRepository->findConversationBySessionId((int) ($inquiry['session_id'] ?? 0)) ?? [];
        $browseTraces = $conversation !== []
            ? $this->conversationRepository->listVisitorEvents((string) ($conversation['session_code'] ?? ''))
            : ($inquiry['browse_traces'] ?? []);

        return [
            'summary' => $this->normalizeInquiry($inquiry),
            'conversation' => [
                'session_id' => (int) ($conversation['session_id'] ?? ($inquiry['session_id'] ?? 0)),
                'session_code' => (string) ($conversation['session_code'] ?? ''),
                'source' => (string) ($conversation['source'] ?? ($inquiry['source'] ?? 'ai')),
                'source_page' => (string) ($conversation['source_page'] ?? ($inquiry['source_page'] ?? '')),
                'entry_language' => (string) ($conversation['entry_language'] ?? ''),
                'resolved_language' => (string) ($conversation['resolved_language'] ?? ''),
                'country_code' => (string) ($conversation['country_code'] ?? ($inquiry['country_code'] ?? '')),
                'device_type' => (string) ($conversation['device_type'] ?? ''),
                'utm_source' => (string) ($conversation['utm_source'] ?? ($inquiry['utm_source'] ?? '')),
                'last_message_at' => $conversation['last_message_at'] ?? ($inquiry['last_message_at'] ?? null),
                'created_at' => $conversation['created_at'] ?? null,
                'updated_at' => $conversation['updated_at'] ?? null,
                'inquiry_id' => (int) ($conversation['inquiry_id'] ?? $id),
                'message_count' => (int) ($conversation['message_count'] ?? count($conversation['messages'] ?? [])),
                'snapshot_count' => (int) ($conversation['snapshot_count'] ?? count($conversation['snapshots'] ?? [])),
                'is_valid_conversation' => (int) ($conversation['is_valid_conversation'] ?? 0),
                'archive_status' => $this->normalizeArchiveStatus($conversation['archive_status'] ?? null),
            ],
            'chat_messages' => $this->normalizeRows($conversation['messages'] ?? [], ['role', 'content', 'created_at', 'message_language', 'translated_text', 'intent_code', 'contains_contact_info', 'extracted_entities_json']),
            'snapshots' => $this->appendSnapshotVersions(
                $this->normalizeRows($conversation['snapshots'] ?? [], ['contact_name', 'company_name', 'email', 'phone', 'whatsapp', 'country_code', 'product_interest', 'solution_interest', 'requirement_summary', 'confidence_score', 'created_at']),
                $conversation['snapshots'] ?? []
            ),
            'browse_traces' => $this->normalizeRows($browseTraces, ['page', 'title', 'referrer', 'visited_at', 'language_code']),
            'change_logs' => $this->normalizeRows($inquiry['change_logs'] ?? [], ['field', 'from', 'to', 'changed_at']),
            'follow_ups' => $this->normalizeRows($inquiry['follow_ups'] ?? [], ['content', 'created_at']),
        ];
    }

    public function workbench(array $query = []): array
    {
        $normalized = $this->normalizeWorkbenchQuery($query);
        $items = array_map(fn (array $item): array => $this->normalizeWorkbenchInquiryItem($item), $this->inquiryRepository->listInquiries());

        foreach ($this->conversationRepository->listConversations() as $conversation) {
            if ((int) ($conversation['inquiry_id'] ?? 0) > 0) {
                continue;
            }

            $items[] = $this->normalizeWorkbenchConversationItem($conversation);
        }

        $items = $this->filterWorkbenchItems($items, $normalized);
        usort($items, fn (array $left, array $right): int => strcmp($this->resolveWorkbenchSortTime($right), $this->resolveWorkbenchSortTime($left)));
        $paged = $this->paginateListItems($items, $normalized);

        return [
            'items' => $paged['items'],
            'pagination' => $paged['pagination'],
            'filters' => ['record_type', 'status', 'archive_status', 'country_code', 'language_code', 'source', 'date_from', 'date_to', 'keyword'],
            'stats' => $this->buildListStats($items),
        ];
    }

    public function workbenchDetail(string $recordType, int $id): array
    {
        $normalizedRecordType = strtolower(trim($recordType));
        if ($normalizedRecordType === 'inquiry') {
            $payload = $this->detail($id);

            return [
                'record_type' => 'inquiry',
                'workbench_id' => sprintf('inquiry:%d', $id),
                'archive_status' => $this->normalizeArchiveStatus($payload['summary']['archive_status'] ?? null),
                ...$payload,
            ];
        }

        if ($normalizedRecordType === 'conversation') {
            $payload = $this->conversationService->detail($id);

            return [
                'record_type' => 'conversation',
                'workbench_id' => sprintf('conversation:%d', $id),
                'archive_status' => $this->normalizeArchiveStatus($payload['summary']['archive_status'] ?? null),
                ...$payload,
            ];
        }

        throw new BusinessException('invalid workbench record type', ErrorCode::INVALID_PARAMS);
    }

    public function update(int $id, array $input): array
    {
        $existing = $this->inquiryRepository->findInquiry($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $targetStatus = array_key_exists('status', $input)
            ? trim((string) $input['status'])
            : (string) ($existing['status'] ?? 'new');
        if (!in_array($targetStatus, ['new', 'contacted', 'quoting', 'won', 'closed'], true)) {
            throw new BusinessException('invalid status transition', ErrorCode::INVALID_STATUS_TRANSITION);
        }

        $currentStatus = (string) ($existing['status'] ?? 'new');
        $this->assertTransitionAllowed($currentStatus, $targetStatus);

        $currentLogs = is_array($existing['change_logs'] ?? null) ? $existing['change_logs'] : [];
        $changes = [
            'country_code' => $this->normalizeInquiryText($input['country_code'] ?? ($existing['country_code'] ?? ''), 32),
            'language_code' => strtolower($this->normalizeInquiryText($input['language_code'] ?? ($existing['language_code'] ?? ''), 16)),
            'product_interest' => $this->normalizeInquiryText($input['product_interest'] ?? ($existing['product_interest'] ?? ''), 120),
            'solution_interest' => $this->normalizeInquiryText($input['solution_interest'] ?? ($existing['solution_interest'] ?? ''), 120),
            'assigned_to' => $this->normalizeAssignee($input['assigned_to'] ?? ($existing['assigned_to'] ?? null)),
            'status' => $targetStatus,
            'first_response_at' => $this->resolveFirstResponseAt($existing, $targetStatus),
        ];

        $changeLogs = $this->appendFieldChangeLog($currentLogs, 'country_code', (string) ($existing['country_code'] ?? ''), $changes['country_code']);
        $changeLogs = $this->appendFieldChangeLog($changeLogs, 'language_code', (string) ($existing['language_code'] ?? ''), $changes['language_code']);
        $changeLogs = $this->appendFieldChangeLog($changeLogs, 'product_interest', (string) ($existing['product_interest'] ?? ''), $changes['product_interest']);
        $changeLogs = $this->appendFieldChangeLog($changeLogs, 'solution_interest', (string) ($existing['solution_interest'] ?? ''), $changes['solution_interest']);
        $changeLogs = $this->appendFieldChangeLog($changeLogs, 'assigned_to', $this->stringifyComparableValue($existing['assigned_to'] ?? null), $this->stringifyComparableValue($changes['assigned_to']));
        if ($currentStatus !== $targetStatus) {
            $changeLogs = $this->appendStatusChangeLog(['change_logs' => $changeLogs], $currentStatus, $targetStatus);
        }
        $changes['change_logs'] = $changeLogs;

        $updated = $this->inquiryRepository->updateInquiry($id, $changes);
        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('inquiry', 'inquiry.update', 'inquiry', $updated, 'inquiry updated');

        return $updated;
    }

    public function updateStatus(int $id, string $status): array
    {
        $targetStatus = trim($status);
        if (!in_array($targetStatus, ['new', 'contacted', 'quoting', 'won', 'closed'], true)) {
            throw new BusinessException('invalid status transition', ErrorCode::INVALID_STATUS_TRANSITION);
        }

        $existing = $this->inquiryRepository->findInquiry($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $currentStatus = (string) ($existing['status'] ?? 'new');
        $this->assertTransitionAllowed($currentStatus, $targetStatus);

        $updated = $this->inquiryRepository->updateInquiryStatus($id, $targetStatus, [
            'first_response_at' => $this->resolveFirstResponseAt($existing, $targetStatus),
            'change_logs' => $this->appendStatusChangeLog($existing, $currentStatus, $targetStatus),
        ]);
        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('inquiry', 'inquiry.status.update', 'inquiry', $updated, 'inquiry status updated');

        return $updated;
    }

    public function addFollowUp(int $id, array $input): array
    {
        $content = trim((string) ($input['content'] ?? ''));
        if ($content === '') {
            throw new BusinessException('follow-up content required', ErrorCode::INVALID_PARAMS);
        }

        $existing = $this->inquiryRepository->findInquiry($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $followUp = [
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $changeLogs = is_array($existing['change_logs'] ?? null) ? $existing['change_logs'] : [];
        $changeLogs[] = [
            'field' => 'follow_ups',
            'from' => (string) count(is_array($existing['follow_ups'] ?? null) ? $existing['follow_ups'] : []),
            'to' => (string) (count(is_array($existing['follow_ups'] ?? null) ? $existing['follow_ups'] : []) + 1),
            'changed_at' => $followUp['created_at'],
        ];

        $updated = $this->inquiryRepository->appendFollowUp($id, $followUp, $changeLogs);
        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('inquiry', 'inquiry.follow_up.create', 'inquiry', $updated, 'inquiry follow-up added');

        return [
            'summary' => $this->normalizeInquiry($updated),
            'follow_up' => $followUp,
        ];
    }

    public function updateWorkbenchArchiveStatus(string $recordType, int $id, string $archiveStatus): array
    {
        $normalizedRecordType = strtolower(trim($recordType));
        $normalizedArchiveStatus = strtolower(trim($archiveStatus));
        if (!in_array($normalizedArchiveStatus, ['active', 'archived'], true)) {
            throw new BusinessException('invalid archive status', ErrorCode::INVALID_PARAMS);
        }

        if ($normalizedRecordType === 'inquiry') {
            $existing = $this->inquiryRepository->findInquiry($id);
            if ($existing === null) {
                throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
            }

            $updated = $this->inquiryRepository->updateArchiveStatus($id, $normalizedArchiveStatus);
            if ($updated === null) {
                throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
            }

            $sessionId = (int) ($existing['session_id'] ?? 0);
            if ($sessionId > 0) {
                $this->conversationRepository->updateArchiveStatus($sessionId, $normalizedArchiveStatus);
            }

            $this->operationLogService->recordCurrentAction(
                'inquiry',
                'inquiry.archive_status.update',
                'inquiry',
                $updated,
                'inquiry archive status updated'
            );

            return $this->workbenchDetail('inquiry', $id);
        }

        if ($normalizedRecordType === 'conversation') {
            $existing = $this->conversationRepository->findConversationBySessionId($id);
            if ($existing === null) {
                throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
            }

            $updatedConversation = $this->conversationRepository->updateArchiveStatus($id, $normalizedArchiveStatus);
            if ($updatedConversation === null) {
                throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
            }

            $inquiryId = (int) ($existing['inquiry_id'] ?? 0);
            if ($inquiryId > 0) {
                $this->inquiryRepository->updateArchiveStatus($inquiryId, $normalizedArchiveStatus);
            }

            $this->operationLogService->recordCurrentAction(
                'inquiry',
                'conversation.archive_status.update',
                'conversation',
                $updatedConversation['session_id'] ?? $id,
                'conversation archive status updated'
            );

            return $this->workbenchDetail('conversation', $id);
        }

        throw new BusinessException('invalid workbench record type', ErrorCode::INVALID_PARAMS);
    }

    public function convertConversationToInquiry(int $sessionId, array $input = []): array
    {
        $conversation = $this->conversationRepository->findConversationBySessionId($sessionId);
        if ($conversation === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $existingInquiryId = (int) ($conversation['inquiry_id'] ?? 0);
        $existingInquiry = $existingInquiryId > 0 ? $this->inquiryRepository->findInquiry($existingInquiryId) : null;

        if ($existingInquiry !== null) {
            $inquiry = $existingInquiry;
        } else {
            $snapshots = is_array($conversation['snapshots'] ?? null) ? $conversation['snapshots'] : [];
            $snapshot = is_array($snapshots[0] ?? null) ? $snapshots[0] : $this->snapshotFromConversation($conversation);
            $browseTraces = $this->conversationRepository->listVisitorEvents((string) ($conversation['session_code'] ?? ''));
            $languageCode = (string) (($conversation['resolved_language'] ?? '') !== '' ? $conversation['resolved_language'] : ($conversation['entry_language'] ?? ''));

            $inquiry = $this->publicChatRepository->createInquiryFromSnapshot(
                $sessionId,
                $snapshot,
                $browseTraces,
                $languageCode,
                $conversation
            );
            $this->publicChatRepository->updateConversationInquiryLink($sessionId, (int) ($inquiry['id'] ?? 0), true);
        }

        $inquiryId = (int) ($inquiry['id'] ?? 0);
        if ($inquiryId <= 0) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $updatePayload = $this->normalizeConversationConversionPayload($input);
        if ($updatePayload !== []) {
            $inquiry = $this->update($inquiryId, $updatePayload);
        } else {
            $inquiry = $this->inquiryRepository->findInquiry($inquiryId) ?? $inquiry;
        }

        $this->operationLogService->recordCurrentAction('inquiry', 'inquiry.conversation.convert', 'inquiry', $inquiry, 'ai conversation converted to inquiry');

        return $this->workbenchDetail('conversation', $sessionId);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeConversationConversionPayload(array $input): array
    {
        $payload = [];

        if (array_key_exists('country_code', $input)) {
            $payload['country_code'] = $this->normalizeInquiryText($input['country_code'], 32);
        }
        if (array_key_exists('language_code', $input)) {
            $payload['language_code'] = strtolower($this->normalizeInquiryText($input['language_code'], 16));
        }
        if (array_key_exists('product_interest', $input)) {
            $payload['product_interest'] = $this->normalizeInquiryText($input['product_interest'], 120);
        }
        if (array_key_exists('solution_interest', $input)) {
            $payload['solution_interest'] = $this->normalizeInquiryText($input['solution_interest'], 120);
        }
        if (array_key_exists('assigned_to', $input)) {
            $payload['assigned_to'] = $this->normalizeAssignee($input['assigned_to']);
        }
        if (array_key_exists('status', $input) && trim((string) $input['status']) !== '') {
            $payload['status'] = trim((string) $input['status']);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFromConversation(array $conversation): array
    {
        return [
            'contact_name' => (string) ($conversation['contact_name'] ?? ''),
            'company_name' => (string) ($conversation['company_name'] ?? ''),
            'email' => (string) ($conversation['email'] ?? ''),
            'phone' => (string) ($conversation['phone'] ?? ''),
            'whatsapp' => (string) ($conversation['whatsapp'] ?? ''),
            'country_code' => (string) ($conversation['country_code'] ?? ''),
            'product_interest' => (string) ($conversation['product_interest'] ?? ''),
            'solution_interest' => (string) ($conversation['solution_interest'] ?? ''),
            'requirement_summary' => (string) ($conversation['requirement_summary'] ?? ''),
            'confidence_score' => (float) ($conversation['confidence_score'] ?? 0),
        ];
    }

    private function assertTransitionAllowed(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        $allowed = [
            'new' => ['contacted', 'quoting', 'closed'],
            'contacted' => ['quoting', 'closed'],
            'quoting' => ['contacted', 'won', 'closed'],
            'won' => [],
            'closed' => [],
        ];

        if (!in_array($to, $allowed[$from] ?? [], true)) {
            throw new BusinessException('invalid status transition', ErrorCode::INVALID_STATUS_TRANSITION);
        }
    }

    private function resolveFirstResponseAt(array $existing, string $targetStatus): ?string
    {
        $firstResponseAt = trim((string) ($existing['first_response_at'] ?? ''));
        if ($firstResponseAt !== '') {
            return $firstResponseAt;
        }

        return in_array($targetStatus, ['contacted', 'quoting', 'won'], true) ? date('Y-m-d H:i:s') : null;
    }

    private function appendStatusChangeLog(array $existing, string $from, string $to): array
    {
        $logs = is_array($existing['change_logs'] ?? null) ? $existing['change_logs'] : [];
        $logs[] = [
            'field' => 'status',
            'from' => $from,
            'to' => $to,
            'changed_at' => date('Y-m-d H:i:s'),
        ];

        return $logs;
    }

    private function appendFieldChangeLog(array $logs, string $field, string $from, string $to): array
    {
        if ($from === $to) {
            return $logs;
        }

        $logs[] = [
            'field' => $field,
            'from' => $from,
            'to' => $to,
            'changed_at' => date('Y-m-d H:i:s'),
        ];

        return $logs;
    }

    private function normalizeInquiryText(mixed $value, int $maxLength): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) > $maxLength) {
            return mb_substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }

    private function normalizeAssignee(mixed $value): ?int
    {
        if ($value === null || $value === '' || (is_numeric($value) && (int) $value <= 0)) {
            return null;
        }

        return (int) $value;
    }

    private function stringifyComparableValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function normalizeInquiry(array $inquiry): array
    {
        $inquiry['browse_traces'] = $this->normalizeRows($inquiry['browse_traces'] ?? [], ['page', 'title', 'referrer', 'visited_at', 'language_code']);
        $inquiry['change_logs'] = $this->normalizeRows($inquiry['change_logs'] ?? [], ['field', 'from', 'to', 'changed_at']);
        $inquiry['follow_ups'] = $this->normalizeRows($inquiry['follow_ups'] ?? [], ['content', 'created_at']);
        $inquiry['browse_count'] = count($inquiry['browse_traces']);
        $inquiry['follow_up_count'] = count($inquiry['follow_ups']);
        $inquiry['archive_status'] = $this->normalizeArchiveStatus($inquiry['archive_status'] ?? null);

        return $inquiry;
    }

    private function normalizeInquiryListItem(array $inquiry): array
    {
        $inquiry['archive_status'] = $this->normalizeArchiveStatus($inquiry['archive_status'] ?? null);
        $inquiry['message_count'] = (int) ($inquiry['message_count'] ?? 0);
        $inquiry['snapshot_count'] = (int) ($inquiry['snapshot_count'] ?? 0);

        return $inquiry;
    }

    private function normalizeWorkbenchInquiryItem(array $inquiry): array
    {
        $normalized = $this->normalizeInquiryListItem($inquiry);
        $normalized['record_type'] = 'inquiry';
        $normalized['workbench_id'] = sprintf('inquiry:%d', (int) ($normalized['id'] ?? 0));
        $normalized['language'] = (string) ($normalized['language_code'] ?? '');

        return $normalized;
    }

    private function normalizeWorkbenchConversationItem(array $conversation): array
    {
        [$primaryContactType, $primaryContactValue] = $this->resolveConversationPrimaryContact($conversation);

        return [
            'record_type' => 'conversation',
            'workbench_id' => sprintf('conversation:%d', (int) ($conversation['session_id'] ?? 0)),
            'archive_status' => $this->normalizeArchiveStatus($conversation['archive_status'] ?? null),
            'session_id' => (int) ($conversation['session_id'] ?? 0),
            'inquiry_id' => (int) ($conversation['inquiry_id'] ?? 0),
            'source' => (string) ($conversation['source'] ?? 'ai'),
            'source_page' => (string) ($conversation['source_page'] ?? ''),
            'primary_contact_type' => $primaryContactType,
            'primary_contact_value' => $primaryContactValue,
            'customer_name' => (string) ($conversation['contact_name'] ?? ''),
            'company_name' => (string) ($conversation['company_name'] ?? ''),
            'country_code' => (string) ($conversation['country_code'] ?? ''),
            'language_code' => (string) (($conversation['resolved_language'] ?? '') !== '' ? $conversation['resolved_language'] : ($conversation['entry_language'] ?? '')),
            'language' => (string) (($conversation['resolved_language'] ?? '') !== '' ? $conversation['resolved_language'] : ($conversation['entry_language'] ?? '')),
            'product_interest' => (string) ($conversation['product_interest'] ?? ''),
            'solution_interest' => (string) ($conversation['solution_interest'] ?? ''),
            'requirement_summary' => (string) ($conversation['requirement_summary'] ?? ''),
            'inquiry_score' => $conversation['confidence_score'] ?? null,
            'status' => 'pending_conversion',
            'assigned_to' => null,
            'first_response_at' => null,
            'device_type' => (string) ($conversation['device_type'] ?? ''),
            'utm_source' => (string) ($conversation['utm_source'] ?? ''),
            'last_message_at' => $conversation['last_message_at'] ?? null,
            'created_at' => $conversation['created_at'] ?? null,
            'updated_at' => $conversation['updated_at'] ?? null,
            'message_count' => (int) ($conversation['message_count'] ?? 0),
            'snapshot_count' => (int) ($conversation['snapshot_count'] ?? 0),
            'is_valid_conversation' => (int) ($conversation['is_valid_conversation'] ?? 0),
        ];
    }

    private function resolveWorkbenchSortTime(array $item): string
    {
        return (string) ($item['updated_at'] ?? $item['last_message_at'] ?? $item['created_at'] ?? '');
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveConversationPrimaryContact(array $conversation): array
    {
        foreach (['email', 'phone', 'whatsapp'] as $field) {
            $value = trim((string) ($conversation[$field] ?? ''));
            if ($value !== '') {
                return [$field, $value];
            }
        }

        return ['', ''];
    }

    private function normalizeArchiveStatus(mixed $value): string
    {
        $archiveStatus = trim((string) $value);

        return $archiveStatus !== '' ? $archiveStatus : 'active';
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeWorkbenchQuery(array $query): array
    {
        $status = trim((string) ($query['status'] ?? ''));
        if (!in_array($status, ['new', 'contacted', 'quoting', 'won', 'closed', 'pending_conversion'], true)) {
            $status = '';
        }

        $recordType = trim((string) ($query['record_type'] ?? ''));
        if (!in_array($recordType, ['inquiry', 'conversation'], true)) {
            $recordType = '';
        }

        $archiveStatus = trim((string) ($query['archive_status'] ?? ''));
        if (!in_array($archiveStatus, ['active', 'archived'], true)) {
            $archiveStatus = '';
        }

        $source = trim((string) ($query['source'] ?? ''));
        if (!in_array($source, ['ai'], true)) {
            $source = '';
        }

        return [
            'record_type' => $recordType,
            'status' => $status,
            'archive_status' => $archiveStatus,
            'country_code' => strtoupper(trim((string) ($query['country_code'] ?? ''))),
            'language_code' => strtolower(trim((string) ($query['language_code'] ?? ''))),
            'source' => $source,
            'keyword' => trim((string) ($query['keyword'] ?? '')),
            'date_from' => trim((string) ($query['date_from'] ?? '')),
            'date_to' => trim((string) ($query['date_to'] ?? '')),
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'page_size' => max(1, min(5000, (int) ($query['page_size'] ?? 20))),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    private function filterWorkbenchItems(array $items, array $query): array
    {
        return array_values(array_filter($items, function (array $item) use ($query): bool {
            if ($query['record_type'] !== '' && (string) ($item['record_type'] ?? '') !== $query['record_type']) {
                return false;
            }
            if ($query['status'] !== '' && (string) ($item['status'] ?? '') !== $query['status']) {
                return false;
            }
            if ($query['archive_status'] !== '' && (string) ($item['archive_status'] ?? '') !== $query['archive_status']) {
                return false;
            }
            if ($query['country_code'] !== '' && strtoupper((string) ($item['country_code'] ?? '')) !== $query['country_code']) {
                return false;
            }
            if ($query['language_code'] !== '' && strtolower((string) ($item['language_code'] ?? '')) !== $query['language_code']) {
                return false;
            }
            if ($query['source'] !== '' && (string) ($item['source'] ?? '') !== $query['source']) {
                return false;
            }
            if ($query['date_from'] !== '' && strcmp((string) ($item['created_at'] ?? ''), $query['date_from'] . ' 00:00:00') < 0) {
                return false;
            }
            if ($query['date_to'] !== '' && strcmp((string) ($item['created_at'] ?? ''), $query['date_to'] . ' 23:59:59') > 0) {
                return false;
            }
            if ($query['keyword'] !== '') {
                $haystack = mb_strtolower(implode(' ', array_map('strval', [
                    $item['customer_name'] ?? '',
                    $item['company_name'] ?? '',
                    $item['country_code'] ?? '',
                    $item['language_code'] ?? '',
                    $item['product_interest'] ?? '',
                    $item['solution_interest'] ?? '',
                    $item['requirement_summary'] ?? '',
                    $item['primary_contact_value'] ?? '',
                    $item['source_page'] ?? '',
                ])));

                if (!str_contains($haystack, mb_strtolower((string) $query['keyword']))) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param array<int, mixed> $rows
     * @param array<int, string> $fields
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRows(array $rows, array $fields): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $item = [];
            foreach ($fields as $field) {
                $item[$field] = $row[$field] ?? null;
            }
            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $normalized
     * @param array<int, mixed> $sourceRows
     * @return array<int, array<string, mixed>>
     */
    private function appendSnapshotVersions(array $normalized, array $sourceRows): array
    {
        foreach ($normalized as $index => $item) {
            $normalized[$index]['snapshot_version'] = (int) (($sourceRows[$index]['snapshot_version'] ?? 0));
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeListQuery(array $query): array
    {
        $sortField = (string) ($query['sort_field'] ?? 'updated_at');
        if (!in_array($sortField, ['updated_at', 'created_at', 'last_message_at', 'inquiry_score'], true)) {
            $sortField = 'updated_at';
        }

        $sortOrder = strtolower((string) ($query['sort_order'] ?? 'desc'));
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }

        $status = trim((string) ($query['status'] ?? ''));
        if (!in_array($status, ['new', 'contacted', 'quoting', 'won', 'closed'], true)) {
            $status = '';
        }

        $archiveStatus = trim((string) ($query['archive_status'] ?? ''));
        if (!in_array($archiveStatus, ['active', 'archived'], true)) {
            $archiveStatus = '';
        }

        $source = trim((string) ($query['source'] ?? ''));
        if (!in_array($source, ['ai'], true)) {
            $source = '';
        }

        return [
            'status' => $status,
            'archive_status' => $archiveStatus,
            'country_code' => strtoupper(trim((string) ($query['country_code'] ?? ''))),
            'language_code' => strtolower(trim((string) ($query['language_code'] ?? ''))),
            'source' => $source,
            'keyword' => trim((string) ($query['keyword'] ?? '')),
            'date_from' => trim((string) ($query['date_from'] ?? '')),
            'date_to' => trim((string) ($query['date_to'] ?? '')),
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'page_size' => max(1, min(5000, (int) ($query['page_size'] ?? 20))),
            'sort_field' => $sortField,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    private function filterListItems(array $items, array $query): array
    {
        return array_values(array_filter($items, function (array $item) use ($query): bool {
            if ($query['status'] !== '' && (string) ($item['status'] ?? '') !== $query['status']) {
                return false;
            }
            if ($query['archive_status'] !== '' && (string) ($item['archive_status'] ?? '') !== $query['archive_status']) {
                return false;
            }
            if ($query['country_code'] !== '' && strtoupper((string) ($item['country_code'] ?? '')) !== $query['country_code']) {
                return false;
            }
            if ($query['language_code'] !== '' && strtolower((string) ($item['language_code'] ?? '')) !== $query['language_code']) {
                return false;
            }
            if ($query['source'] !== '' && (string) ($item['source'] ?? '') !== $query['source']) {
                return false;
            }
            if ($query['date_from'] !== '' && strcmp((string) ($item['created_at'] ?? ''), $query['date_from'] . ' 00:00:00') < 0) {
                return false;
            }
            if ($query['date_to'] !== '' && strcmp((string) ($item['created_at'] ?? ''), $query['date_to'] . ' 23:59:59') > 0) {
                return false;
            }
            if ($query['keyword'] !== '') {
                $haystack = mb_strtolower(implode(' ', array_map('strval', [
                    $item['customer_name'] ?? '',
                    $item['company_name'] ?? '',
                    $item['product_interest'] ?? '',
                    $item['solution_interest'] ?? '',
                    $item['requirement_summary'] ?? '',
                    $item['primary_contact_value'] ?? '',
                ])));
                if (!str_contains($haystack, mb_strtolower((string) $query['keyword']))) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    private function sortListItems(array $items, array $query): array
    {
        usort($items, function (array $left, array $right) use ($query): int {
            $field = (string) $query['sort_field'];
            $leftValue = $left[$field] ?? '';
            $rightValue = $right[$field] ?? '';
            $compare = is_numeric($leftValue) && is_numeric($rightValue)
                ? ((float) $leftValue <=> (float) $rightValue)
                : strcmp((string) $leftValue, (string) $rightValue);
            if ((string) $query['sort_order'] === 'desc') {
                $compare *= -1;
            }
            if ($compare !== 0) {
                return $compare;
            }

            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function paginateListItems(array $items, array $query): array
    {
        $total = count($items);
        $page = (int) $query['page'];
        $pageSize = (int) $query['page_size'];
        $offset = ($page - 1) * $pageSize;

        return [
            'items' => array_slice($items, $offset, $pageSize),
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / max(1, $pageSize))),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function buildListStats(array $items): array
    {
        $statusCounts = ['new' => 0, 'contacted' => 0, 'quoting' => 0, 'won' => 0, 'closed' => 0, 'pending_conversion' => 0];
        $sourceCounts = [];
        $countryCounts = [];
        $stale48hCount = 0;
        $archivedCount = 0;
        $now = time();

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'new');
            if (array_key_exists($status, $statusCounts)) {
                $statusCounts[$status] += 1;
            }

            if ((string) ($item['archive_status'] ?? '') === 'archived') {
                $archivedCount += 1;
            }

            $source = (string) ($item['source'] ?? 'ai');
            $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;

            $country = (string) ($item['country_code'] ?? '');
            if ($country !== '') {
                $countryCounts[$country] = ($countryCounts[$country] ?? 0) + 1;
            }

            $createdAt = strtotime((string) ($item['created_at'] ?? ''));
            if ($status === 'new' && empty($item['first_response_at']) && $createdAt !== false && ($now - $createdAt) > 172800) {
                $stale48hCount += 1;
            }
        }

        arsort($countryCounts);

        $total = count($items);
        $wonCount = (int) ($statusCounts['won'] ?? 0);

        return [
            'total' => $total,
            'status_counts' => $statusCounts,
            'source_counts' => $sourceCounts,
            'country_counts' => $countryCounts,
            'won_count' => $wonCount,
            'conversion_rate' => $total > 0 ? round(($wonCount / $total) * 100, 1) : 0.0,
            'stale_48h_count' => $stale48hCount,
            'archived_count' => $archivedCount,
        ];
    }
}
