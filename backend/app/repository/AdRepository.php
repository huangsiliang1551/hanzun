<?php

declare(strict_types=1);

namespace app\repository;

final class AdRepository
{
    public function __construct(
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository(),
        private readonly PageRepository $pageRepository = new PageRepository()
    ) {
    }

    public function list(): array
    {
        $stored = $this->systemSettingRepository->get('site', 'ads', ['items' => []]);

        if (is_array($stored) && isset($stored['items']) && is_array($stored['items'])) {
            return array_values(array_map([$this, 'normalizeItem'], $stored['items']));
        }

        if (is_array($stored) && array_is_list($stored)) {
            return array_values(array_map([$this, 'normalizeItem'], $stored));
        }

        return [];
    }

    public function replaceAll(array $items): array
    {
        $payload = [
            'items' => array_values(array_map([$this, 'normalizeItem'], $items)),
        ];

        $stored = $this->systemSettingRepository->put('site', 'ads', $payload);

        if (is_array($stored) && isset($stored['items']) && is_array($stored['items'])) {
            return array_values(array_map([$this, 'normalizeItem'], $stored['items']));
        }

        return [];
    }

    public function findLinkedPage(int $pageId): ?array
    {
        if ($pageId <= 0) {
            return null;
        }

        return $this->pageRepository->find($pageId);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        return [
            'id' => trim((string) ($item['id'] ?? '')),
            'position_key' => trim((string) ($item['position_key'] ?? '')),
            'page_scope' => trim((string) ($item['page_scope'] ?? '')),
            'title' => trim((string) ($item['title'] ?? '')),
            'image_url' => trim((string) ($item['image_url'] ?? '')),
            'linked_page_id' => (int) ($item['linked_page_id'] ?? 0),
            'linked_page_slug' => trim((string) ($item['linked_page_slug'] ?? '')),
            'linked_page_title' => trim((string) ($item['linked_page_title'] ?? '')),
            'open_in_new_tab' => (int) ($item['open_in_new_tab'] ?? 0) === 1 ? 1 : 0,
            'sort' => (int) ($item['sort'] ?? 0),
            'is_enabled' => (int) ($item['is_enabled'] ?? 0) === 1 ? 1 : 0,
        ];
    }
}
