<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class ContactRepository
{
    public function list(): array
    {
        $runtimeItems = $this->readRuntimeItems();
        if ($this->preferRuntimeStorage()) {
            return $this->hasRuntimeItemFile() ? $runtimeItems : ($runtimeItems !== [] ? $runtimeItems : $this->defaultItems());
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->query(
                'SELECT ci.id, ci.field_type_id, ci.label_zh, ci.value, ci.description_zh, ci.display_scope, ci.sort, ci.is_enabled, cft.field_key, cft.name_zh AS field_name
                 FROM contact_items ci
                 LEFT JOIN contact_field_types cft ON cft.id = ci.field_type_id
                 ORDER BY ci.sort DESC, ci.id ASC'
            );
            $rows = $statement->fetchAll();
            $items = is_array($rows) ? $rows : [];

            return $this->mergeRuntimeItems($items, $runtimeItems);
        }

        return $runtimeItems !== [] ? $runtimeItems : $this->defaultItems();
    }

    public function listFieldTypes(): array
    {
        $runtimeItems = $this->readRuntimeFieldTypes();
        if ($this->preferRuntimeStorage()) {
            return $this->hasRuntimeFieldTypeFile() ? $runtimeItems : ($runtimeItems !== [] ? $runtimeItems : $this->defaultFieldTypes());
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $this->ensureBuiltinFieldTypes($pdo);
            $statement = $pdo->query(
                'SELECT id, field_key, name_zh, icon, validation_rule, sort, is_enabled
                 FROM contact_field_types
                 ORDER BY sort DESC, id ASC'
            );
            $rows = $statement->fetchAll();
            $items = is_array($rows) ? $rows : [];

            return $this->mergeRuntimeFieldTypes($items, $runtimeItems);
        }

        return $runtimeItems !== [] ? $runtimeItems : $this->defaultFieldTypes();
    }

    private function ensureBuiltinFieldTypes(PDO $pdo): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO contact_field_types (id, field_key, name_zh, icon, validation_rule, sort, is_enabled)
             VALUES (:id, :field_key, :name_zh, :icon, :validation_rule, :sort, :is_enabled)
             ON DUPLICATE KEY UPDATE
               field_key = VALUES(field_key),
               name_zh = VALUES(name_zh),
               icon = VALUES(icon),
               validation_rule = VALUES(validation_rule),
               sort = VALUES(sort),
               is_enabled = VALUES(is_enabled)'
        );

        foreach ($this->defaultFieldTypes() as $item) {
            $statement->execute([
                'id' => (int) ($item['id'] ?? 0),
                'field_key' => (string) ($item['field_key'] ?? ''),
                'name_zh' => (string) ($item['name_zh'] ?? ''),
                'icon' => (string) ($item['icon'] ?? ''),
                'validation_rule' => (string) ($item['validation_rule'] ?? 'text'),
                'sort' => (int) ($item['sort'] ?? 0),
                'is_enabled' => (int) ($item['is_enabled'] ?? 0),
            ]);
        }
    }

    public function createFieldType(array $payload): array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->createRuntimeFieldType($payload);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO contact_field_types (field_key, name_zh, icon, validation_rule, sort, is_enabled)
                 VALUES (:field_key, :name_zh, :icon, :validation_rule, :sort, :is_enabled)'
            );
            $statement->execute([
                'field_key' => $payload['field_key'],
                'name_zh' => $payload['name_zh'],
                'icon' => $payload['icon'],
                'validation_rule' => $payload['validation_rule'],
                'sort' => $payload['sort'],
                'is_enabled' => $payload['is_enabled'],
            ]);

            return $this->findFieldTypeDatabase((int) $pdo->lastInsertId()) ?? $payload;
        }

        return $this->createRuntimeFieldType($payload);
    }

    public function updateFieldType(int $id, array $payload): ?array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->updateRuntimeFieldType($id, $payload);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            if ($this->findFieldTypeDatabase($id) === null) {
                return $this->updateRuntimeFieldType($id, $payload);
            }

            $statement = $pdo->prepare(
                'UPDATE contact_field_types
                 SET field_key = :field_key, name_zh = :name_zh, icon = :icon, validation_rule = :validation_rule, sort = :sort, is_enabled = :is_enabled
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'field_key' => $payload['field_key'],
                'name_zh' => $payload['name_zh'],
                'icon' => $payload['icon'],
                'validation_rule' => $payload['validation_rule'],
                'sort' => $payload['sort'],
                'is_enabled' => $payload['is_enabled'],
            ]);

            return $this->findFieldTypeDatabase($id);
        }

        return $this->updateRuntimeFieldType($id, $payload);
    }

    public function countItemsByFieldType(int $fieldTypeId): int
    {
        if ($fieldTypeId <= 0) {
            return 0;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO && !$this->preferRuntimeStorage()) {
            $statement = $pdo->prepare(
                'SELECT COUNT(*) AS total
                 FROM contact_items
                 WHERE field_type_id = :field_type_id'
            );
            $statement->execute(['field_type_id' => $fieldTypeId]);
            $row = $statement->fetch();

            return (int) ($row['total'] ?? 0);
        }

        $count = 0;
        foreach ($this->list() as $item) {
            if ((int) ($item['field_type_id'] ?? 0) === $fieldTypeId) {
                $count++;
            }
        }

        return $count;
    }

    public function deleteFieldType(int $id): ?array
    {
        $existing = $this->findFieldType($id);
        if ($existing === null) {
            return null;
        }

        if ($this->preferRuntimeStorage()) {
            return $this->deleteRuntimeFieldType($id);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            if ($this->findFieldTypeDatabase($id) === null) {
                return $this->deleteRuntimeFieldType($id);
            }

            $translationStatement = $pdo->prepare('DELETE FROM contact_field_type_translations WHERE field_type_id = :id');
            $translationStatement->execute(['id' => $id]);

            $statement = $pdo->prepare('DELETE FROM contact_field_types WHERE id = :id');
            $statement->execute(['id' => $id]);

            return $existing;
        }

        return $this->deleteRuntimeFieldType($id);
    }

    public function fieldKeyExists(string $fieldKey, int $excludeId = 0): bool
    {
        $normalized = trim($fieldKey);
        if ($normalized === '') {
            return false;
        }

        foreach ($this->listFieldTypes() as $item) {
            if ((string) ($item['field_key'] ?? '') !== $normalized) {
                continue;
            }

            if ((int) ($item['id'] ?? 0) !== $excludeId) {
                return true;
            }
        }

        return false;
    }

    public function find(int $id): ?array
    {
        foreach ($this->list() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    public function create(array $payload): array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->createRuntimeItem($payload);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'INSERT INTO contact_items (field_type_id, label_zh, value, description_zh, display_scope, sort, is_enabled)
                 VALUES (:field_type_id, :label_zh, :value, :description_zh, :display_scope, :sort, :is_enabled)'
            );
            $statement->execute([
                'field_type_id' => $payload['field_type_id'],
                'label_zh' => $payload['label_zh'],
                'value' => $payload['value'],
                'description_zh' => $payload['description_zh'],
                'display_scope' => $payload['display_scope'],
                'sort' => $payload['sort'],
                'is_enabled' => $payload['is_enabled'],
            ]);

            return $this->findDatabase((int) $pdo->lastInsertId()) ?? $payload;
        }

        return $this->createRuntimeItem($payload);
    }

    public function update(int $id, array $payload): ?array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->updateRuntimeItem($id, $payload);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            if ($this->findDatabase($id) === null) {
                return $this->updateRuntimeItem($id, $payload);
            }

            $statement = $pdo->prepare(
                'UPDATE contact_items
                 SET field_type_id = :field_type_id, label_zh = :label_zh, value = :value, description_zh = :description_zh, display_scope = :display_scope, sort = :sort, is_enabled = :is_enabled
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'field_type_id' => $payload['field_type_id'],
                'label_zh' => $payload['label_zh'],
                'value' => $payload['value'],
                'description_zh' => $payload['description_zh'],
                'display_scope' => $payload['display_scope'],
                'sort' => $payload['sort'],
                'is_enabled' => $payload['is_enabled'],
            ]);

            return $this->findDatabase($id);
        }

        return $this->updateRuntimeItem($id, $payload);
    }

    public function delete(int $id): ?array
    {
        $existing = $this->find($id);
        if ($existing === null) {
            return null;
        }

        if ($this->preferRuntimeStorage()) {
            return $this->deleteRuntimeItem($id);
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            if ($this->findDatabase($id) === null) {
                return $this->deleteRuntimeItem($id);
            }

            $translationStatement = $pdo->prepare('DELETE FROM contact_item_translations WHERE contact_item_id = :id');
            $translationStatement->execute(['id' => $id]);

            $statement = $pdo->prepare('DELETE FROM contact_items WHERE id = :id');
            $statement->execute(['id' => $id]);

            return $existing;
        }

        return $this->deleteRuntimeItem($id);
    }

    public function findFieldType(int $id): ?array
    {
        foreach ($this->listFieldTypes() as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return $item;
            }
        }

        return null;
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage([
            $this->fieldTypePath(),
            $this->itemPath(),
        ]);
    }

    private function fieldTypePath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/contact_field_types.json';
    }

    private function itemPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/contact_items.json';
    }

    private function hasRuntimeFieldTypeFile(): bool
    {
        return is_file($this->fieldTypePath());
    }

    private function hasRuntimeItemFile(): bool
    {
        return is_file($this->itemPath());
    }

    private function readRuntimeFieldTypes(): array
    {
        $path = $this->fieldTypePath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = $this->normalizeFieldType($item);
        }

        usort($items, static fn (array $left, array $right): int => (((int) ($right['sort'] ?? 0)) <=> ((int) ($left['sort'] ?? 0))) ?: (((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0))));

        return $items;
    }

    private function writeRuntimeFieldTypes(array $items): void
    {
        $path = $this->fieldTypePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function readRuntimeItems(): array
    {
        $path = $this->itemPath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = $this->normalizeItem($item);
        }

        usort($items, static fn (array $left, array $right): int => (((int) ($right['sort'] ?? 0)) <=> ((int) ($left['sort'] ?? 0))) ?: (((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0))));

        return $items;
    }

    private function writeRuntimeItems(array $items): void
    {
        $path = $this->itemPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function findFieldTypeDatabase(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return null;
        }

        $statement = $pdo->prepare(
            'SELECT id, field_key, name_zh, icon, validation_rule, sort, is_enabled
             FROM contact_field_types
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function findDatabase(int $id): ?array
    {
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return null;
        }

        $statement = $pdo->prepare(
            'SELECT ci.id, ci.field_type_id, ci.label_zh, ci.value, ci.description_zh, ci.display_scope, ci.sort, ci.is_enabled, cft.field_key, cft.name_zh AS field_name
             FROM contact_items ci
             LEFT JOIN contact_field_types cft ON cft.id = ci.field_type_id
             WHERE ci.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    private function createRuntimeFieldType(array $payload): array
    {
        $items = $this->readRuntimeFieldTypes();
        if ($items === []) {
            $items = $this->defaultFieldTypes();
        }

        $record = $this->normalizeFieldType(array_merge($payload, [
            'id' => $this->nextId($items),
        ]));
        $items[] = $record;
        $this->writeRuntimeFieldTypes($items);

        return $record;
    }

    private function updateRuntimeFieldType(int $id, array $payload): ?array
    {
        $items = $this->readRuntimeFieldTypes();
        if ($items === []) {
            $items = $this->defaultFieldTypes();
        }

        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $id) {
                continue;
            }

            $items[$index] = $this->normalizeFieldType(array_merge($item, $payload, ['id' => $id]));
            $this->writeRuntimeFieldTypes($items);

            return $items[$index];
        }

        return null;
    }

    private function deleteRuntimeFieldType(int $id): ?array
    {
        $items = $this->readRuntimeFieldTypes();
        if ($items === []) {
            $items = $this->defaultFieldTypes();
        }

        $record = null;
        $filtered = [];
        foreach ($items as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $record = $item;
                continue;
            }
            $filtered[] = $item;
        }

        if ($record === null) {
            return null;
        }

        $this->writeRuntimeFieldTypes($filtered);

        return $record;
    }

    private function createRuntimeItem(array $payload): array
    {
        $items = $this->readRuntimeItems();
        if ($items === []) {
            $items = $this->defaultItems();
        }

        $record = $this->normalizeItem(array_merge($payload, [
            'id' => $this->nextId($items),
            'field_key' => $this->findFieldType((int) ($payload['field_type_id'] ?? 0))['field_key'] ?? '',
            'field_name' => $this->findFieldType((int) ($payload['field_type_id'] ?? 0))['name_zh'] ?? '',
        ]));
        $items[] = $record;
        $this->writeRuntimeItems($items);

        return $record;
    }

    private function updateRuntimeItem(int $id, array $payload): ?array
    {
        $items = $this->readRuntimeItems();
        if ($items === []) {
            $items = $this->defaultItems();
        }

        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) !== $id) {
                continue;
            }

            $fieldTypeId = (int) ($payload['field_type_id'] ?? $item['field_type_id'] ?? 0);
            $fieldType = $this->findFieldType($fieldTypeId);
            $items[$index] = $this->normalizeItem(array_merge($item, $payload, [
                'id' => $id,
                'field_type_id' => $fieldTypeId,
                'field_key' => $fieldType['field_key'] ?? ($item['field_key'] ?? ''),
                'field_name' => $fieldType['name_zh'] ?? ($item['field_name'] ?? ''),
            ]));
            $this->writeRuntimeItems($items);

            return $items[$index];
        }

        return null;
    }

    private function deleteRuntimeItem(int $id): ?array
    {
        $items = $this->readRuntimeItems();
        if ($items === []) {
            $items = $this->defaultItems();
        }

        $record = null;
        $filtered = [];
        foreach ($items as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                $record = $item;
                continue;
            }
            $filtered[] = $item;
        }

        if ($record === null) {
            return null;
        }

        $this->writeRuntimeItems($filtered);

        return $record;
    }

    private function normalizeFieldType(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'field_key' => trim((string) ($item['field_key'] ?? '')),
            'name_zh' => trim((string) ($item['name_zh'] ?? '')),
            'icon' => trim((string) ($item['icon'] ?? '')),
            'validation_rule' => trim((string) ($item['validation_rule'] ?? '')),
            'sort' => (int) ($item['sort'] ?? 0),
            'is_enabled' => !empty($item['is_enabled']) ? 1 : 0,
        ];
    }

    private function normalizeItem(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'field_type_id' => (int) ($item['field_type_id'] ?? 0),
            'label_zh' => trim((string) ($item['label_zh'] ?? '')),
            'value' => trim((string) ($item['value'] ?? '')),
            'description_zh' => trim((string) ($item['description_zh'] ?? '')),
            'display_scope' => trim((string) ($item['display_scope'] ?? '')),
            'sort' => (int) ($item['sort'] ?? 0),
            'is_enabled' => !empty($item['is_enabled']) ? 1 : 0,
            'field_key' => trim((string) ($item['field_key'] ?? '')),
            'field_name' => trim((string) ($item['field_name'] ?? ($item['name_zh'] ?? ''))),
        ];
    }

    private function mergeRuntimeFieldTypes(array $databaseItems, array $runtimeItems): array
    {
        if ($runtimeItems === []) {
            return $databaseItems !== [] ? $databaseItems : $this->defaultFieldTypes();
        }

        $seen = [];
        foreach ($databaseItems as $item) {
            $seen[(int) ($item['id'] ?? 0)] = true;
        }

        foreach ($runtimeItems as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (isset($seen[$id])) {
                continue;
            }
            $databaseItems[] = $item;
        }

        usort($databaseItems, static fn (array $left, array $right): int => (((int) ($right['sort'] ?? 0)) <=> ((int) ($left['sort'] ?? 0))) ?: (((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0))));

        return $databaseItems;
    }

    private function mergeRuntimeItems(array $databaseItems, array $runtimeItems): array
    {
        if ($runtimeItems === []) {
            return $databaseItems !== [] ? $databaseItems : $this->defaultItems();
        }

        $seen = [];
        foreach ($databaseItems as $item) {
            $seen[(int) ($item['id'] ?? 0)] = true;
        }

        foreach ($runtimeItems as $item) {
            $id = (int) ($item['id'] ?? 0);
            if (isset($seen[$id])) {
                continue;
            }
            $databaseItems[] = $item;
        }

        usort($databaseItems, static fn (array $left, array $right): int => (((int) ($right['sort'] ?? 0)) <=> ((int) ($left['sort'] ?? 0))) ?: (((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0))));

        return $databaseItems;
    }

    private function nextId(array $items): int
    {
        $maxId = 0;
        foreach ($items as $item) {
            $maxId = max($maxId, (int) ($item['id'] ?? 0));
        }

        return $maxId + 1;
    }

    private function defaultFieldTypes(): array
    {
        return [
            [
                'id' => 1,
                'field_key' => 'email',
                'name_zh' => '邮箱',
                'icon' => 'mail',
                'validation_rule' => 'email',
                'sort' => 100,
                'is_enabled' => 1,
            ],
            [
                'id' => 2,
                'field_key' => 'phone',
                'name_zh' => '电话',
                'icon' => 'phone',
                'validation_rule' => 'phone',
                'sort' => 99,
                'is_enabled' => 1,
            ],
            [
                'id' => 3,
                'field_key' => 'whatsapp',
                'name_zh' => 'WhatsApp',
                'icon' => 'message',
                'validation_rule' => 'text',
                'sort' => 98,
                'is_enabled' => 1,
            ],
            [
                'id' => 4,
                'field_key' => 'linkedin',
                'name_zh' => 'LinkedIn',
                'icon' => 'link',
                'validation_rule' => 'url',
                'sort' => 97,
                'is_enabled' => 1,
            ],
            [
                'id' => 5,
                'field_key' => 'youtube',
                'name_zh' => 'YouTube',
                'icon' => 'play',
                'validation_rule' => 'url',
                'sort' => 96,
                'is_enabled' => 1,
            ],
            [
                'id' => 6,
                'field_key' => 'line',
                'name_zh' => 'LINE',
                'icon' => 'message-circle',
                'validation_rule' => 'text',
                'sort' => 95,
                'is_enabled' => 1,
            ],
            [
                'id' => 7,
                'field_key' => 'address',
                'name_zh' => '地址',
                'icon' => 'map-pin',
                'validation_rule' => 'text',
                'sort' => 94,
                'is_enabled' => 1,
            ],
        ];
    }

    private function defaultItems(): array
    {
        return [
            [
                'id' => 1,
                'field_type_id' => 1,
                'label_zh' => '商务邮箱',
                'value' => 'hanzunkunshanmachinery@gmail.com',
                'description_zh' => '用于海外询盘联系',
                'display_scope' => 'contact_page',
                'sort' => 100,
                'is_enabled' => 1,
                'field_key' => 'email',
                'field_name' => '邮箱',
            ],
            [
                'id' => 2,
                'field_type_id' => 2,
                'label_zh' => '工厂总机',
                'value' => '+85253441653',
                'description_zh' => '工作时间 09:00-18:00',
                'display_scope' => 'footer',
                'sort' => 99,
                'is_enabled' => 1,
                'field_key' => 'phone',
                'field_name' => '电话',
            ],
            [
                'id' => 3,
                'field_type_id' => 3,
                'label_zh' => '海外 WhatsApp',
                'value' => '+85253441653',
                'description_zh' => '销售团队在线接待',
                'display_scope' => 'footer',
                'sort' => 98,
                'is_enabled' => 1,
                'field_key' => 'whatsapp',
                'field_name' => 'WhatsApp',
            ],
        ];
    }
}
