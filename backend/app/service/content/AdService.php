<?php

declare(strict_types=1);

namespace app\service\content;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\AdRepository;
use app\service\log\OperationLogService;

final class AdService
{
    public function __construct(
        private readonly AdRepository $adRepository = new AdRepository(),
        private readonly OperationLogService $operationLogService = new OperationLogService()
    ) {
    }

    public function list(): array
    {
        $items = $this->sortItems($this->adRepository->list());

        return [
            'items' => $items,
        ];
    }

    public function saveAll(array $items): array
    {
        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            if (!is_array($item)) {
                throw new BusinessException('invalid ad item', ErrorCode::INVALID_PARAMS);
            }

            $normalized[] = $this->normalizeItem($item, $index);
        }

        $stored = $this->sortItems($this->adRepository->replaceAll($normalized));
        $this->operationLogService->recordCurrentAction(
            'content',
            'system.site.update',
            'system_setting',
            'site.ads',
            'ads updated'
        );

        return [
            'items' => $stored,
        ];
    }

    public function publicList(string $pageScope = ''): array
    {
        $pageScope = trim($pageScope);
        $items = array_values(array_filter(
            $this->sortItems($this->adRepository->list()),
            function (array $item) use ($pageScope): bool {
                if ((int) ($item['is_enabled'] ?? 0) !== 1) {
                    return false;
                }

                if ((int) ($item['linked_page_id'] ?? 0) <= 0 || trim((string) ($item['linked_page_slug'] ?? '')) === '') {
                    return false;
                }

                if ($pageScope === '') {
                    return true;
                }

                $scope = trim((string) ($item['page_scope'] ?? ''));
                if ($scope === '' || $scope === '*' || $scope === 'all') {
                    return true;
                }

                return $scope === $pageScope;
            }
        ));

        return array_map(fn (array $item): array => [
            'id' => (string) ($item['id'] ?? ''),
            'position_key' => (string) ($item['position_key'] ?? ''),
            'page_scope' => (string) ($item['page_scope'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'image_url' => (string) ($item['image_url'] ?? ''),
            'linked_page_id' => (int) ($item['linked_page_id'] ?? 0),
            'linked_page_slug' => (string) ($item['linked_page_slug'] ?? ''),
            'linked_page_title' => (string) ($item['linked_page_title'] ?? ''),
            'open_in_new_tab' => (int) ($item['open_in_new_tab'] ?? 0),
            'sort' => (int) ($item['sort'] ?? 0),
            'is_enabled' => 1,
        ], $items);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item, int $index): array
    {
        $linkedPageId = (int) ($item['linked_page_id'] ?? 0);
        $linkedPage = $this->adRepository->findLinkedPage($linkedPageId);
        if ($linkedPage === null) {
            throw new BusinessException('linked page not found', ErrorCode::INVALID_PARAMS);
        }

        $linkedPageSlug = trim((string) ($linkedPage['slug'] ?? ''));
        if ($linkedPageSlug === '') {
            throw new BusinessException('linked page slug missing', ErrorCode::INVALID_PARAMS);
        }

        $linkedPageTitle = trim((string) ($linkedPage['title_zh'] ?? ''));
        if ($linkedPageTitle === '') {
            throw new BusinessException('linked page title missing', ErrorCode::INVALID_PARAMS);
        }

        return [
            'id' => $this->normalizeId($item['id'] ?? null, $index),
            'position_key' => $this->validateRequiredText((string) ($item['position_key'] ?? ''), 64, 'position_key'),
            'page_scope' => $this->validateRequiredText((string) ($item['page_scope'] ?? ''), 64, 'page_scope'),
            'title' => $this->validateRequiredText((string) ($item['title'] ?? ''), 180, 'title'),
            'image_url' => $this->validateAssetPath((string) ($item['image_url'] ?? '')),
            'linked_page_id' => $linkedPageId,
            'linked_page_slug' => $linkedPageSlug,
            'linked_page_title' => $linkedPageTitle,
            'open_in_new_tab' => $this->normalizeFlag($item['open_in_new_tab'] ?? 0),
            'sort' => (int) ($item['sort'] ?? 0),
            'is_enabled' => $this->normalizeFlag($item['is_enabled'] ?? 1),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function sortItems(array $items): array
    {
        usort($items, static function (array $left, array $right): int {
            $sortCompare = ((int) ($left['sort'] ?? 0)) <=> ((int) ($right['sort'] ?? 0));
            if ($sortCompare !== 0) {
                return $sortCompare;
            }

            return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
        });

        return $items;
    }

    private function normalizeId(mixed $value, int $index): string
    {
        $id = trim((string) $value);
        if ($id !== '') {
            return $id;
        }

        $entropy = function_exists('random_bytes')
            ? bin2hex(random_bytes(4))
            : str_replace('.', '', uniqid('', true));

        return 'ad_' . ($index + 1) . '_' . $entropy;
    }

    private function validateRequiredText(string $value, int $maxLength, string $field): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw new BusinessException('invalid ' . $field, ErrorCode::INVALID_PARAMS);
        }

        return $value;
    }

    private function validateAssetPath(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new BusinessException('invalid image_url', ErrorCode::INVALID_PARAMS);
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw new BusinessException('invalid image_url', ErrorCode::INVALID_PARAMS);
        }

        return $value;
    }

    private function normalizeFlag(mixed $value): int
    {
        if ($value === 1 || $value === '1' || $value === true || $value === 'true') {
            return 1;
        }

        if ($value === 0 || $value === '0' || $value === false || $value === 'false' || $value === null) {
            return 0;
        }

        throw new BusinessException('invalid switch value', ErrorCode::INVALID_PARAMS);
    }
}
