<?php

declare(strict_types=1);

namespace app\repository;

use app\common\database\DatabaseManager;
use PDO;

final class HomepageRepository
{
    private ?array $schemaCache = null;

    public function publishedSnapshot(): array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->readRuntimeSetting('homepage', 'published_snapshot');
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT setting_value
                 FROM system_settings
                 WHERE setting_group = :setting_group AND setting_key = :setting_key
                 LIMIT 1'
            );
            $statement->execute([
                'setting_group' => 'homepage',
                'setting_key' => 'published_snapshot',
            ]);
            $row = $statement->fetch();
            if (is_array($row)) {
                $value = json_decode((string) ($row['setting_value'] ?? '{}'), true);

                return is_array($value) ? $value : [];
            }
        }

        return $this->readRuntimeSetting('homepage', 'published_snapshot');
    }

    public function list(): array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->readRuntimeSections();
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->query(
                'SELECT id, section_key, section_type, title_zh, subtitle_zh, fetch_mode, extra_config, sort, is_enabled
                 FROM homepage_sections
                 ORDER BY sort DESC, id ASC'
            );
            $rows = $statement->fetchAll();

            return is_array($rows) ? $rows : [];
        }

        return $this->readRuntimeSections();
    }

    public function find(int $id): ?array
    {
        if ($this->preferRuntimeStorage()) {
            foreach ($this->readRuntimeSections() as $row) {
                if ((int) ($row['id'] ?? 0) === $id) {
                    return $row;
                }
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT id, section_key, section_type, title_zh, subtitle_zh, fetch_mode, extra_config, sort, is_enabled
                 FROM homepage_sections WHERE id = :id LIMIT 1'
            );
            $statement->execute(['id' => $id]);
            $row = $statement->fetch();

            return is_array($row) ? $row : null;
        }

        foreach ($this->readRuntimeSections() as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }

        return null;
    }

    public function create(array $data): array
    {
        if ($this->preferRuntimeStorage()) {
            $items = $this->readRuntimeSections();
            $record = $this->normalizeSection(array_merge($data, [
                'id' => $this->nextId($items),
            ]));
            $items[] = $record;
            $this->writeRuntimeSections($items);

            return $record;
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return ['id' => 0];
        }

        $statement = $pdo->prepare(
            'INSERT INTO homepage_sections (section_key, section_type, title_zh, subtitle_zh, fetch_mode, extra_config, sort, is_enabled)
             VALUES (:section_key, :section_type, :title_zh, :subtitle_zh, :fetch_mode, :extra_config, :sort, :is_enabled)'
        );
        $statement->execute([
            'section_key' => (string) ($data['section_key'] ?? ''),
            'section_type' => (string) ($data['section_type'] ?? 'fixed_config'),
            'title_zh' => (string) ($data['title_zh'] ?? ''),
            'subtitle_zh' => (string) ($data['subtitle_zh'] ?? ''),
            'fetch_mode' => (string) ($data['fetch_mode'] ?? 'fixed_config'),
            'extra_config' => isset($data['extra_config']) ? json_encode($data['extra_config'], JSON_UNESCAPED_UNICODE) : '{}',
            'sort' => (int) ($data['sort'] ?? 0),
            'is_enabled' => (int) ($data['is_enabled'] ?? 1),
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'section_key' => $data['section_key'] ?? '',
            'section_type' => $data['section_type'] ?? '',
            'title_zh' => $data['title_zh'] ?? '',
            'subtitle_zh' => $data['subtitle_zh'] ?? '',
            'fetch_mode' => $data['fetch_mode'] ?? '',
            'extra_config' => $data['extra_config'] ?? [],
            'sort' => (int) ($data['sort'] ?? 0),
            'is_enabled' => (int) ($data['is_enabled'] ?? 1),
        ];
    }

    public function updateSorts(array $sorts): array
    {
        if ($this->preferRuntimeStorage()) {
            $items = $this->readRuntimeSections();
            $sortMap = [];
            foreach ($sorts as $sort) {
                $sortMap[(int) ($sort['id'] ?? 0)] = (int) ($sort['sort'] ?? 0);
            }

            foreach ($items as $index => $item) {
                $id = (int) ($item['id'] ?? 0);
                if (isset($sortMap[$id])) {
                    $items[$index]['sort'] = $sortMap[$id];
                }
            }

            $this->writeRuntimeSections($items);

            return $this->readRuntimeSections();
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return [];
        }

        $statement = $pdo->prepare('UPDATE homepage_sections SET sort = :sort WHERE id = :id');
        foreach ($sorts as $sort) {
            $statement->execute([
                'id' => (int) ($sort['id'] ?? 0),
                'sort' => (int) ($sort['sort'] ?? 0),
            ]);
        }

        return $this->list();
    }

    public function updateStatus(int $id, int $isEnabled): array
    {
        if ($this->preferRuntimeStorage()) {
            $items = $this->readRuntimeSections();
            foreach ($items as $index => $item) {
                if ((int) ($item['id'] ?? 0) !== $id) {
                    continue;
                }

                $items[$index]['is_enabled'] = $isEnabled;
                $this->writeRuntimeSections($items);

                return $items[$index];
            }

            return [];
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return [];
        }

        $statement = $pdo->prepare('UPDATE homepage_sections SET is_enabled = :is_enabled WHERE id = :id');
        $statement->execute(['id' => $id, 'is_enabled' => $isEnabled]);

        $updated = $this->find($id);

        return $updated ?? [];
    }

    public function allSectionItems(): array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->readRuntimeSectionItems();
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO || !$this->hasTable('homepage_section_items')) {
            return [];
        }

        $statement = $pdo->query(
            'SELECT id, section_id, source_type, source_id, title_override_zh, summary_override_zh, cover_asset_id, sort, is_enabled
             FROM homepage_section_items
             ORDER BY section_id ASC, sort DESC, id DESC'
        );
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function publishMeta(): array
    {
        if ($this->preferRuntimeStorage()) {
            return $this->readRuntimeSetting('homepage', 'publish_meta');
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            $statement = $pdo->prepare(
                'SELECT setting_value
                 FROM system_settings
                 WHERE setting_group = :setting_group AND setting_key = :setting_key
                 LIMIT 1'
            );
            $statement->execute([
                'setting_group' => 'homepage',
                'setting_key' => 'publish_meta',
            ]);
            $row = $statement->fetch();
            if (is_array($row)) {
                $value = json_decode((string) ($row['setting_value'] ?? '{}'), true);

                return is_array($value) ? $value : [];
            }
        }

        return $this->readRuntimeSetting('homepage', 'publish_meta');
    }

    public function listItems(int $sectionId): array
    {
        $sectionId = max(0, $sectionId);
        if ($sectionId <= 0) {
            return [];
        }

        if ($this->preferRuntimeStorage()) {
            return array_values(array_filter(
                $this->readRuntimeSectionItems(),
                static fn (array $item): bool => (int) ($item['section_id'] ?? 0) === $sectionId
            ));
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO || !$this->hasTable('homepage_section_items')) {
            return [];
        }

        $statement = $pdo->prepare(
            'SELECT id, section_id, source_type, source_id, title_override_zh, summary_override_zh, cover_asset_id, sort, is_enabled
             FROM homepage_section_items
             WHERE section_id = :section_id
             ORDER BY sort DESC, id DESC'
        );
        $statement->execute([
            'section_id' => $sectionId,
        ]);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function replaceItems(int $sectionId, array $items): array
    {
        $sectionId = max(0, $sectionId);
        if ($sectionId <= 0) {
            return [];
        }

        if ($this->preferRuntimeStorage()) {
            $existing = $this->readRuntimeSectionItems();
            $kept = array_values(array_filter(
                $existing,
                static fn (array $item): bool => (int) ($item['section_id'] ?? 0) !== $sectionId
            ));

            $nextId = $this->nextId($existing);
            foreach ($items as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = $nextId;
                    $nextId++;
                }

                $kept[] = $this->normalizeSectionItem(array_merge($item, [
                    'id' => $itemId,
                    'section_id' => $sectionId,
                ]));
            }

            $this->writeRuntimeSectionItems($kept);

            return $this->listItems($sectionId);
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO || !$this->hasTable('homepage_section_items')) {
            return [];
        }

        $delete = $pdo->prepare('DELETE FROM homepage_section_items WHERE section_id = :section_id');
        $delete->execute([
            'section_id' => $sectionId,
        ]);

        $insert = $pdo->prepare(
            'INSERT INTO homepage_section_items (section_id, source_type, source_id, title_override_zh, summary_override_zh, cover_asset_id, sort, is_enabled)
             VALUES (:section_id, :source_type, :source_id, :title_override_zh, :summary_override_zh, :cover_asset_id, :sort, :is_enabled)'
        );

        foreach ($items as $item) {
            $insert->execute([
                'section_id' => $sectionId,
                'source_type' => trim((string) ($item['source_type'] ?? '')),
                'source_id' => (int) ($item['source_id'] ?? 0),
                'title_override_zh' => trim((string) ($item['title_override_zh'] ?? '')),
                'summary_override_zh' => trim((string) ($item['summary_override_zh'] ?? '')),
                'cover_asset_id' => max(0, (int) ($item['cover_asset_id'] ?? 0)),
                'sort' => (int) ($item['sort'] ?? 0),
                'is_enabled' => !empty($item['is_enabled']) ? 1 : 0,
            ]);
        }

        return $this->listItems($sectionId);
    }

    public function findSectionItem(int $sectionId, int $itemId): ?array
    {
        $sectionId = max(0, $sectionId);
        $itemId = max(0, $itemId);
        if ($sectionId <= 0 || $itemId <= 0) {
            return null;
        }

        if ($this->preferRuntimeStorage()) {
            foreach ($this->readRuntimeSectionItems() as $item) {
                if ((int) ($item['section_id'] ?? 0) === $sectionId && (int) ($item['id'] ?? 0) === $itemId) {
                    return $item;
                }
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO || !$this->hasTable('homepage_section_items')) {
            return null;
        }

        $statement = $pdo->prepare(
            'SELECT id, section_id, source_type, source_id, title_override_zh, summary_override_zh, cover_asset_id, sort, is_enabled
             FROM homepage_section_items
             WHERE section_id = :section_id AND id = :id
             LIMIT 1'
        );
        $statement->execute([
            'section_id' => $sectionId,
            'id' => $itemId,
        ]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function savePublishedSnapshot(array $snapshot): void
    {
        if ($this->preferRuntimeStorage()) {
            $this->writeRuntimeSetting('homepage', 'published_snapshot', $snapshot);
            return;
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            $this->writeRuntimeSetting('homepage', 'published_snapshot', $snapshot);
            return;
        }

        $statement = $pdo->prepare(
            'INSERT INTO system_settings (setting_group, setting_key, setting_value)
             VALUES (:setting_group, :setting_key, :setting_value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $statement->execute([
            'setting_group' => 'homepage',
            'setting_key' => 'published_snapshot',
            'setting_value' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function savePublishMeta(array $meta): void
    {
        if ($this->preferRuntimeStorage()) {
            $this->writeRuntimeSetting('homepage', 'publish_meta', $meta);
            return;
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            $this->writeRuntimeSetting('homepage', 'publish_meta', $meta);
            return;
        }

        $statement = $pdo->prepare(
            'INSERT INTO system_settings (setting_group, setting_key, setting_value)
             VALUES (:setting_group, :setting_key, :setting_value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $statement->execute([
            'setting_group' => 'homepage',
            'setting_key' => 'publish_meta',
            'setting_value' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function update(int $id, array $data): ?array
    {
        if ($this->preferRuntimeStorage()) {
            $items = $this->readRuntimeSections();
            foreach ($items as $index => $item) {
                if ((int) ($item['id'] ?? 0) !== $id) {
                    continue;
                }

                $items[$index] = $this->normalizeSection(array_merge($item, $data, ['id' => $id]));
                $this->writeRuntimeSections($items);

                return $items[$index];
            }

            return null;
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return null;
        }

        $existing = $this->find($id);
        if ($existing === null) {
            return null;
        }

        $statement = $pdo->prepare(
            'UPDATE homepage_sections
             SET section_key = :section_key,
                 section_type = :section_type,
                 title_zh = :title_zh,
                 subtitle_zh = :subtitle_zh,
                 fetch_mode = :fetch_mode,
                 extra_config = :extra_config,
                 sort = :sort,
                 is_enabled = :is_enabled
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'section_key' => trim((string) ($data['section_key'] ?? ($existing['section_key'] ?? ''))),
            'section_type' => trim((string) ($data['section_type'] ?? ($existing['section_type'] ?? 'fixed_config'))),
            'title_zh' => (string) ($data['title_zh'] ?? ($existing['title_zh'] ?? '')),
            'subtitle_zh' => (string) ($data['subtitle_zh'] ?? ($existing['subtitle_zh'] ?? '')),
            'fetch_mode' => trim((string) ($data['fetch_mode'] ?? ($existing['fetch_mode'] ?? 'fixed_config'))),
            'extra_config' => json_encode($data['extra_config'] ?? $this->decodeJsonField($existing['extra_config'] ?? []), JSON_UNESCAPED_UNICODE),
            'sort' => (int) ($data['sort'] ?? ($existing['sort'] ?? 0)),
            'is_enabled' => array_key_exists('is_enabled', $data) ? (!empty($data['is_enabled']) ? 1 : 0) : (int) ($existing['is_enabled'] ?? 1),
        ]);

        return $this->find($id);
    }

    public function replaceSnapshot(array $snapshot): void
    {
        if ($this->preferRuntimeStorage()) {
            $sections = is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : [];
            $sectionItems = is_array($snapshot['section_items'] ?? null) ? $snapshot['section_items'] : [];

            if ($sections !== []) {
                $this->writeRuntimeSections($sections);
            }
            $this->writeRuntimeSectionItems($sectionItems);
            return;
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return;
        }

        $sections = is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : [];
        $sectionItems = is_array($snapshot['section_items'] ?? null) ? $snapshot['section_items'] : [];

        if ($sections !== []) {
            $updateSection = $pdo->prepare(
                'UPDATE homepage_sections
                 SET title_zh = :title_zh,
                     subtitle_zh = :subtitle_zh,
                     fetch_mode = :fetch_mode,
                     extra_config = :extra_config,
                     sort = :sort,
                     is_enabled = :is_enabled
                 WHERE id = :id'
            );

            foreach ($sections as $section) {
                $existing = $this->find((int) ($section['id'] ?? 0));
                if ($existing === null) {
                    continue;
                }

                $updateSection->execute([
                    'id' => (int) ($section['id'] ?? 0),
                    'title_zh' => (string) ($section['title_zh'] ?? $existing['title_zh'] ?? ''),
                    'subtitle_zh' => (string) ($section['subtitle_zh'] ?? $existing['subtitle_zh'] ?? ''),
                    'fetch_mode' => (string) ($section['fetch_mode'] ?? $existing['fetch_mode'] ?? 'fixed_config'),
                    'extra_config' => json_encode($section['extra_config'] ?? $this->decodeJsonField($existing['extra_config'] ?? []), JSON_UNESCAPED_UNICODE),
                    'sort' => (int) ($section['sort'] ?? $existing['sort'] ?? 0),
                    'is_enabled' => (int) ($section['is_enabled'] ?? $existing['is_enabled'] ?? 1),
                ]);
            }
        }

        if ($this->hasTable('homepage_section_items')) {
            $delete = $pdo->prepare('DELETE FROM homepage_section_items');
            $delete->execute();

            if ($sectionItems !== []) {
                $insert = $pdo->prepare(
                    'INSERT INTO homepage_section_items (id, section_id, source_type, source_id, title_override_zh, summary_override_zh, cover_asset_id, sort, is_enabled)
                     VALUES (:id, :section_id, :source_type, :source_id, :title_override_zh, :summary_override_zh, :cover_asset_id, :sort, :is_enabled)'
                );

                foreach ($sectionItems as $item) {
                    $insert->execute([
                        'id' => (int) ($item['id'] ?? 0),
                        'section_id' => (int) ($item['section_id'] ?? 0),
                        'source_type' => trim((string) ($item['source_type'] ?? '')),
                        'source_id' => (int) ($item['source_id'] ?? 0),
                        'title_override_zh' => trim((string) ($item['title_override_zh'] ?? '')),
                        'summary_override_zh' => trim((string) ($item['summary_override_zh'] ?? '')),
                        'cover_asset_id' => max(0, (int) ($item['cover_asset_id'] ?? 0)),
                        'sort' => (int) ($item['sort'] ?? 0),
                        'is_enabled' => !empty($item['is_enabled']) ? 1 : 0,
                    ]);
                }
            }
        }
    }

    public function upsertSectionItemTranslations(int $itemId, array $translations): void
    {
        if ($itemId <= 0) {
            return;
        }

        if ($this->preferRuntimeStorage()) {
            $items = array_values(array_filter(
                $this->readRuntimeSectionItemTranslations(),
                static fn (array $row): bool => (int) ($row['item_id'] ?? 0) !== $itemId
            ));

            foreach ($translations as $translation) {
                $languageCode = trim((string) ($translation['language_code'] ?? ''));
                if ($languageCode === '') {
                    continue;
                }

                $items[] = [
                    'id' => $this->nextId($items),
                    'item_id' => $itemId,
                    'language_code' => $languageCode,
                    'title' => trim((string) ($translation['title'] ?? '')),
                    'summary' => trim((string) ($translation['summary'] ?? '')),
                    'translation_status' => trim((string) ($translation['translation_status'] ?? 'completed')) ?: 'completed',
                ];
            }

            $this->writeRuntimeSectionItemTranslations($items);
            return;
        }

        if (!$this->hasTable('homepage_section_item_translations')) {
            return;
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return;
        }

        $delete = $pdo->prepare('DELETE FROM homepage_section_item_translations WHERE item_id = :item_id');
        $delete->execute([
            'item_id' => $itemId,
        ]);

        $insert = $pdo->prepare(
            'INSERT INTO homepage_section_item_translations (item_id, language_code, title, summary, translation_status)
             VALUES (:item_id, :language_code, :title, :summary, :translation_status)'
        );

        foreach ($translations as $translation) {
            $languageCode = trim((string) ($translation['language_code'] ?? ''));
            if ($languageCode === '') {
                continue;
            }

            $insert->execute([
                'item_id' => $itemId,
                'language_code' => $languageCode,
                'title' => trim((string) ($translation['title'] ?? '')),
                'summary' => trim((string) ($translation['summary'] ?? '')),
                'translation_status' => trim((string) ($translation['translation_status'] ?? 'completed')) ?: 'completed',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function hasTable(string $tableName): bool
    {
        return $this->tableExists($tableName);
    }

    private function tableExists(string $tableName): bool
    {
        if ($this->schemaCache !== null) {
            return !empty($this->schemaCache[$tableName]);
        }

        $this->schemaCache = [];
        $pdo = DatabaseManager::instance()->connection();
        if (!$pdo instanceof PDO) {
            return false;
        }

        try {
            $statement = $pdo->query('SHOW TABLES');
            $rows = $statement->fetchAll(PDO::FETCH_NUM);
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $name = (string) ($row[0] ?? '');
                    if ($name !== '') {
                        $this->schemaCache[$name] = true;
                    }
                }
            }
        } catch (\Throwable) {
            $this->schemaCache = [];
        }

        return !empty($this->schemaCache[$tableName]);
    }

    private function preferRuntimeStorage(): bool
    {
        return should_prefer_runtime_storage([
            $this->sectionPath(),
            $this->sectionItemPath(),
            $this->sectionItemTranslationPath(),
            $this->settingsPath(),
        ]);
    }

    private function sectionPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/homepage_sections.json';
    }

    private function sectionItemPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/homepage_section_items.json';
    }

    private function sectionItemTranslationPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/homepage_section_item_translations.json';
    }

    private function settingsPath(): string
    {
        return dirname(__DIR__, 2) . '/runtime/storage/system_settings.json';
    }

    private function readRuntimeSections(): array
    {
        $decoded = $this->readRuntimeJsonFile($this->sectionPath());
        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = $this->normalizeSection($item);
        }

        usort($items, static fn (array $left, array $right): int => (((int) ($right['sort'] ?? 0)) <=> ((int) ($left['sort'] ?? 0)))
            ?: (((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0))));

        return $items;
    }

    private function writeRuntimeSections(array $items): void
    {
        $normalized = array_map([$this, 'normalizeSection'], array_values(array_filter($items, 'is_array')));
        $this->writeRuntimeJsonFile($this->sectionPath(), $normalized);
    }

    private function readRuntimeSectionItems(): array
    {
        $decoded = $this->readRuntimeJsonFile($this->sectionItemPath());
        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = $this->normalizeSectionItem($item);
        }

        usort($items, static fn (array $left, array $right): int => (((int) ($left['section_id'] ?? 0)) <=> ((int) ($right['section_id'] ?? 0)))
            ?: (((int) ($right['sort'] ?? 0)) <=> ((int) ($left['sort'] ?? 0)))
            ?: (((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0))));

        return $items;
    }

    private function writeRuntimeSectionItems(array $items): void
    {
        $normalized = array_map([$this, 'normalizeSectionItem'], array_values(array_filter($items, 'is_array')));
        $this->writeRuntimeJsonFile($this->sectionItemPath(), $normalized);
    }

    private function readRuntimeSectionItemTranslations(): array
    {
        $decoded = $this->readRuntimeJsonFile($this->sectionItemTranslationPath());
        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = [
                'id' => (int) ($item['id'] ?? 0),
                'item_id' => (int) ($item['item_id'] ?? 0),
                'language_code' => trim((string) ($item['language_code'] ?? '')),
                'title' => trim((string) ($item['title'] ?? '')),
                'summary' => trim((string) ($item['summary'] ?? '')),
                'translation_status' => trim((string) ($item['translation_status'] ?? '')),
            ];
        }

        usort($items, static fn (array $left, array $right): int => (((int) ($left['item_id'] ?? 0)) <=> ((int) ($right['item_id'] ?? 0)))
            ?: strcmp((string) ($left['language_code'] ?? ''), (string) ($right['language_code'] ?? ''))
            ?: (((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0))));

        return $items;
    }

    private function writeRuntimeSectionItemTranslations(array $items): void
    {
        $this->writeRuntimeJsonFile($this->sectionItemTranslationPath(), array_values($items));
    }

    private function readRuntimeSetting(string $group, string $key): array
    {
        $settings = $this->readRuntimeJsonFile($this->settingsPath());
        $value = $settings[$group][$key] ?? [];

        return is_array($value) ? $value : [];
    }

    private function writeRuntimeSetting(string $group, string $key, array $value): void
    {
        $settings = $this->readRuntimeJsonFile($this->settingsPath());
        if (!isset($settings[$group]) || !is_array($settings[$group])) {
            $settings[$group] = [];
        }
        $settings[$group][$key] = $value;
        $this->writeRuntimeJsonFile($this->settingsPath(), $settings);
    }

    private function readRuntimeJsonFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeRuntimeJsonFile(string $path, array $payload): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($payload) === $payload ? array_values($payload) : $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }

    private function normalizeSection(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'section_key' => trim((string) ($item['section_key'] ?? '')),
            'section_type' => trim((string) ($item['section_type'] ?? 'fixed_config')),
            'title_zh' => (string) ($item['title_zh'] ?? ''),
            'subtitle_zh' => (string) ($item['subtitle_zh'] ?? ''),
            'fetch_mode' => trim((string) ($item['fetch_mode'] ?? 'fixed_config')),
            'extra_config' => $this->decodeJsonField($item['extra_config'] ?? []),
            'sort' => (int) ($item['sort'] ?? 0),
            'is_enabled' => !empty($item['is_enabled']) ? 1 : 0,
        ];
    }

    private function normalizeSectionItem(array $item): array
    {
        return [
            'id' => (int) ($item['id'] ?? 0),
            'section_id' => (int) ($item['section_id'] ?? 0),
            'source_type' => trim((string) ($item['source_type'] ?? '')),
            'source_id' => (int) ($item['source_id'] ?? 0),
            'title_override_zh' => trim((string) ($item['title_override_zh'] ?? '')),
            'summary_override_zh' => trim((string) ($item['summary_override_zh'] ?? '')),
            'cover_asset_id' => max(0, (int) ($item['cover_asset_id'] ?? 0)),
            'sort' => (int) ($item['sort'] ?? 0),
            'is_enabled' => !empty($item['is_enabled']) ? 1 : 0,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function nextId(array $items): int
    {
        $maxId = 0;
        foreach ($items as $item) {
            $maxId = max($maxId, (int) ($item['id'] ?? 0));
        }

        return $maxId + 1;
    }
}
