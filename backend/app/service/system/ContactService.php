<?php

declare(strict_types=1);

namespace app\service\system;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\ContactRepository;
use app\repository\TranslationRepository;
use app\service\log\OperationLogService;
use app\service\translation\SharedTranslationPipelineService;

final class ContactService
{
    public function __construct(
        private readonly ContactRepository $contactRepository = new ContactRepository(),
        private readonly OperationLogService $operationLogService = new OperationLogService(),
        private readonly SharedTranslationPipelineService $sharedTranslationPipelineService = new SharedTranslationPipelineService(),
        private readonly TranslationRepository $translationRepository = new TranslationRepository()
    ) {
    }

    public function items(): array
    {
        return [
            'items' => $this->contactRepository->list(),
            'field_types' => $this->contactRepository->listFieldTypes(),
            'scopes' => $this->allowedScopes(),
        ];
    }

    public function fieldTypes(): array
    {
        return $this->contactRepository->listFieldTypes();
    }

    public function createFieldType(array $input): array
    {
        $payload = $this->normalizeFieldTypePayload($input);
        if ($this->contactRepository->fieldKeyExists($payload['field_key'])) {
            throw new BusinessException('duplicate contact field key', ErrorCode::INVALID_PARAMS);
        }

        $record = $this->contactRepository->createFieldType($payload);
        $this->sharedTranslationPipelineService->syncEntity('contact_field_type', (int) ($record['id'] ?? 0));
        $this->operationLogService->recordCurrentAction('contact', 'contact.field_type.create', 'contact_field_type', $record, 'contact field type created');

        return $record;
    }

    public function updateFieldType(int $id, array $input): array
    {
        $existing = $this->contactRepository->findFieldType($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $payload = $this->normalizeFieldTypePayload(array_merge($existing, $input));
        if ($this->contactRepository->fieldKeyExists($payload['field_key'], $id)) {
            throw new BusinessException('duplicate contact field key', ErrorCode::INVALID_PARAMS);
        }

        $updated = $this->contactRepository->updateFieldType($id, $payload);
        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->sharedTranslationPipelineService->syncEntity('contact_field_type', $id);
        $this->operationLogService->recordCurrentAction('contact', 'contact.field_type.update', 'contact_field_type', $updated, 'contact field type updated');

        return $updated;
    }

    public function deleteFieldType(int $id): array
    {
        $existing = $this->contactRepository->findFieldType($id);
        if ($existing === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        if ($this->contactRepository->countItemsByFieldType($id) > 0) {
            throw new BusinessException('contact field type still in use', ErrorCode::INVALID_PARAMS);
        }

        $deleted = $this->contactRepository->deleteFieldType($id);
        if ($deleted === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->translationRepository->deleteByEntity('contact_field_type', $id);
        $this->operationLogService->recordCurrentAction('contact', 'contact.field_type.delete', 'contact_field_type', $deleted, 'contact field type deleted');

        return $deleted;
    }

    public function detail(int $id): array
    {
        $record = $this->contactRepository->find($id);
        if ($record === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        return $record;
    }

    public function create(array $input): array
    {
        $fieldTypeId = (int) ($input['field_type_id'] ?? 0);
        $fieldType = $this->contactRepository->findFieldType($fieldTypeId);
        if ($fieldType === null || (int) ($fieldType['is_enabled'] ?? 0) !== 1) {
            throw new BusinessException('invalid contact field type', ErrorCode::INVALID_PARAMS);
        }

        $label = trim((string) ($input['label_zh'] ?? ''));
        $value = trim((string) ($input['value'] ?? ''));
        if ($label === '' || $value === '') {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $record = $this->contactRepository->create([
            'field_type_id' => $fieldTypeId,
            'label_zh' => $label,
            'value' => $this->validateFieldValue($value, (string) ($fieldType['validation_rule'] ?? 'text')),
            'description_zh' => (string) ($input['description_zh'] ?? ''),
            'display_scope' => $this->validateScope((string) ($input['display_scope'] ?? 'contact_page')),
            'sort' => (int) ($input['sort'] ?? 0),
            'is_enabled' => !empty($input['is_enabled']) ? 1 : 0,
        ]);

        $this->sharedTranslationPipelineService->syncEntity('contact_item', (int) ($record['id'] ?? 0));
        $this->operationLogService->recordCurrentAction('contact', 'contact.create', 'contact_item', $record, 'contact item created');

        return $record;
    }

    public function update(int $id, array $input): array
    {
        $existing = $this->detail($id);
        $fieldTypeId = (int) ($input['field_type_id'] ?? $existing['field_type_id']);
        $fieldType = $this->contactRepository->findFieldType($fieldTypeId);
        if ($fieldType === null || (int) ($fieldType['is_enabled'] ?? 0) !== 1) {
            throw new BusinessException('invalid contact field type', ErrorCode::INVALID_PARAMS);
        }

        $updated = $this->contactRepository->update($id, array_merge($existing, [
            'field_type_id' => $fieldTypeId,
            'label_zh' => (string) ($input['label_zh'] ?? $existing['label_zh']),
            'value' => $this->validateFieldValue((string) ($input['value'] ?? $existing['value']), (string) ($fieldType['validation_rule'] ?? 'text')),
            'description_zh' => (string) ($input['description_zh'] ?? $existing['description_zh'] ?? ''),
            'display_scope' => $this->validateScope((string) ($input['display_scope'] ?? $existing['display_scope'])),
            'sort' => (int) ($input['sort'] ?? $existing['sort']),
            'is_enabled' => array_key_exists('is_enabled', $input) ? (!empty($input['is_enabled']) ? 1 : 0) : ($existing['is_enabled'] ?? 0),
        ]));

        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->sharedTranslationPipelineService->syncEntity('contact_item', $id);
        $this->operationLogService->recordCurrentAction('contact', 'contact.update', 'contact_item', $updated, 'contact item updated');

        return $updated;
    }

    public function delete(int $id): array
    {
        $existing = $this->detail($id);
        $deleted = $this->contactRepository->delete($id);
        if ($deleted === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->translationRepository->deleteByEntity('contact_item', $id);
        $this->operationLogService->recordCurrentAction('contact', 'contact.delete', 'contact_item', $existing, 'contact item deleted');

        return $existing;
    }

    /**
     * @return array<int, string>
     */
    private function allowedScopes(): array
    {
        return ['contact_page', 'footer', 'floating_contact', 'bottom_dock', 'ai_quick_reply'];
    }

    private function validateScope(string $scope): string
    {
        $normalized = trim($scope);
        if (!in_array($normalized, $this->allowedScopes(), true)) {
            throw new BusinessException('invalid contact scope', ErrorCode::INVALID_PARAMS);
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeFieldTypePayload(array $input): array
    {
        $fieldKey = strtolower(trim((string) ($input['field_key'] ?? '')));
        if ($fieldKey === '' || preg_match('/^[a-z][a-z0-9_]{1,31}$/', $fieldKey) !== 1) {
            throw new BusinessException('invalid contact field key', ErrorCode::INVALID_PARAMS);
        }

        $name = trim((string) ($input['name_zh'] ?? ''));
        if ($name === '') {
            throw new BusinessException('invalid contact field name', ErrorCode::INVALID_PARAMS);
        }

        $rule = strtolower(trim((string) ($input['validation_rule'] ?? 'text')));
        if (!in_array($rule, ['text', 'email', 'phone', 'mobile', 'url'], true)) {
            throw new BusinessException('invalid contact validation rule', ErrorCode::INVALID_PARAMS);
        }

        return [
            'field_key' => $fieldKey,
            'name_zh' => $name,
            'icon' => trim((string) ($input['icon'] ?? '')),
            'validation_rule' => $rule,
            'sort' => (int) ($input['sort'] ?? 0),
            'is_enabled' => !empty($input['is_enabled']) ? 1 : 0,
        ];
    }

    private function validateFieldValue(string $value, string $rule): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            throw new BusinessException('invalid params', ErrorCode::INVALID_PARAMS);
        }

        $isValid = match ($rule) {
            'email' => filter_var($normalized, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($normalized, FILTER_VALIDATE_URL) !== false,
            'phone', 'mobile' => preg_match('/^[0-9+().\-\/#\s]{6,48}$/', $normalized) === 1,
            default => true,
        };

        if (!$isValid) {
            throw new BusinessException('invalid contact value for validation rule: ' . $rule, ErrorCode::INVALID_PARAMS);
        }

        return $normalized;
    }
}
