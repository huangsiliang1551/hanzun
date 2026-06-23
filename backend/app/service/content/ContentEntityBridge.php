<?php

declare(strict_types=1);

namespace app\service\content;

use app\common\database\DatabaseManager;
use app\repository\AboutRepository;
use app\repository\ArticleRepository;
use app\repository\CaseRepository;
use app\repository\CertificateRepository;
use app\repository\ContactRepository;
use app\repository\NewsRepository;
use app\repository\NavigationRepository;
use app\repository\PageRepository;
use app\repository\ProductRepository;
use app\repository\SolutionRepository;
use app\repository\TeamRepository;
use app\repository\HomepageRepository;
use PDO;

final class ContentEntityBridge
{
    public function __construct(
        private readonly ProductRepository $productRepository = new ProductRepository(),
        private readonly SolutionRepository $solutionRepository = new SolutionRepository(),
        private readonly ArticleRepository $articleRepository = new ArticleRepository(),
        private readonly NewsRepository $newsRepository = new NewsRepository(),
        private readonly CaseRepository $caseRepository = new CaseRepository(),
        private readonly PageRepository $pageRepository = new PageRepository(),
        private readonly TeamRepository $teamRepository = new TeamRepository(),
        private readonly CertificateRepository $certificateRepository = new CertificateRepository(),
        private readonly ContactRepository $contactRepository = new ContactRepository(),
        private readonly AboutRepository $aboutRepository = new AboutRepository(),
        private readonly NavigationRepository $navigationRepository = new NavigationRepository(),
        private readonly HomepageRepository $homepageRepository = new HomepageRepository()
    ) {
    }

    public function find(string $entityType, int $entityId): ?array
    {
        return match ($entityType) {
            'product_category' => $this->productRepository->findCategory($entityId),
            'product' => $this->productRepository->find($entityId),
            'solution_category' => $this->solutionRepository->findCategory($entityId),
            'solution' => $this->solutionRepository->find($entityId),
            'article_category' => $this->articleRepository->findCategory($entityId),
            'news_category' => $this->newsRepository->findCategory($entityId),
            'case_category' => $this->caseRepository->findCategory($entityId),
            'news' => $this->newsRepository->find($entityId),
            'case' => $this->caseRepository->find($entityId),
            'article' => $this->articleRepository->find($entityId),
            'page' => $this->pageRepository->find($entityId),
            'team_member' => $this->teamRepository->find($entityId),
            'certificate' => $this->certificateRepository->find($entityId),
            'contact_field_type' => $this->contactRepository->findFieldType($entityId),
            'contact_item' => $this->contactRepository->find($entityId),
            'about_page' => $this->aboutRepository->page($entityId),
            'about_block' => $this->findAboutBlock($entityId),
            'navigation_menu' => $this->navigationRepository->findMenu($entityId),
            'navigation_item' => $this->findNavigationItem($entityId),
            'homepage_section' => $this->homepageRepository->find($entityId),
            default => null,
        };
    }

    public function supports(string $entityType): bool
    {
        return in_array($entityType, [
            'product_category',
            'product',
            'solution_category',
            'solution',
            'article_category',
            'news_category',
            'case_category',
            'news',
            'case',
            'article',
            'page',
            'team_member',
            'certificate',
            'contact_field_type',
            'contact_item',
            'about_page',
            'about_block',
            'navigation_menu',
            'navigation_item',
            'homepage_section',
        ], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function translationSource(string $entityType, array $record): array
    {
        return match ($entityType) {
            'product_category', 'solution_category', 'article_category', 'news_category', 'case_category' => [
                'name' => (string) ($record['name_zh'] ?? ''),
            ],
            'product' => [
                'name' => (string) ($record['name_zh'] ?? ''),
                'summary' => (string) ($record['summary_zh'] ?? ''),
                'content' => (string) ($record['content_zh'] ?? ''),
            ],
            'solution' => [
                'name' => (string) ($record['name_zh'] ?? ''),
                'summary' => (string) ($record['summary_zh'] ?? ''),
                'content' => (string) ($record['content_zh'] ?? ''),
                'flow_text' => (string) ($record['flow_text_zh'] ?? ''),
                'capacity_text' => (string) ($record['capacity_text_zh'] ?? ''),
            ],
            'article', 'news', 'case' => [
                'title' => (string) ($record['title_zh'] ?? ''),
                'summary' => (string) ($record['summary_zh'] ?? ''),
                'content' => (string) ($record['content_zh'] ?? ''),
            ],
            'page' => [
                'title' => (string) ($record['title_zh'] ?? ''),
                'summary' => (string) ($record['summary_zh'] ?? ''),
                'content' => (string) ($record['content_zh'] ?? ''),
            ],
            'team_member' => [
                'name' => (string) ($record['name_zh'] ?? ''),
                'title' => (string) ($record['title_zh'] ?? ''),
                'department' => (string) ($record['department_zh'] ?? ''),
                'bio' => (string) ($record['bio_zh'] ?? ''),
            ],
            'certificate' => [
                'name' => (string) ($record['name_zh'] ?? ''),
                'issuer' => (string) ($record['issuer_zh'] ?? ''),
                'description' => (string) ($record['description_zh'] ?? ''),
            ],
            'contact_field_type' => [
                'name' => (string) ($record['name_zh'] ?? ''),
            ],
            'contact_item' => [
                'label' => (string) ($record['label_zh'] ?? ''),
                'description' => (string) ($record['description_zh'] ?? ''),
            ],
            'about_page' => [
                'name' => (string) ($record['name_zh'] ?? ''),
            ],
            'about_block' => [
                'title' => (string) ($record['title_zh'] ?? ''),
                'subtitle' => (string) ($record['subtitle_zh'] ?? ''),
                'content' => (string) ($record['content_zh'] ?? ''),
            ],
            'navigation_menu' => [
                'name' => (string) ($record['name_zh'] ?? ''),
            ],
            'navigation_item' => [
                'name' => (string) ($record['name_zh'] ?? ''),
            ],
            'homepage_section' => [
                'title' => (string) ($record['title_zh'] ?? ''),
                'subtitle' => (string) ($record['subtitle_zh'] ?? ''),
            ],
            default => [],
        };
    }

    /**
     * @return array{title:string,summary:string,content:string}
     */
    public function seoSource(string $entityType, array $record, string $languageCode): array
    {
        $translation = $languageCode === 'zh'
            ? null
            : $this->findTranslation($entityType, (int) ($record['id'] ?? 0), $languageCode);

        return match ($entityType) {
            'product', 'solution' => [
                'title' => (string) ($translation['name'] ?? $record['name_zh'] ?? ''),
                'summary' => (string) ($translation['summary'] ?? $record['summary_zh'] ?? ''),
                'content' => trim(implode("\n", array_filter([
                    (string) ($translation['content'] ?? $record['content_zh'] ?? ''),
                    $entityType === 'solution' ? (string) ($translation['flow_text'] ?? $record['flow_text_zh'] ?? '') : '',
                    $entityType === 'solution' ? (string) ($translation['capacity_text'] ?? $record['capacity_text_zh'] ?? '') : '',
                ], static fn (string $value): bool => trim($value) !== ''))),
            ],
            'news', 'case', 'page' => [
                'title' => (string) ($translation['title'] ?? $record['title_zh'] ?? ''),
                'summary' => (string) ($translation['summary'] ?? $record['summary_zh'] ?? ''),
                'content' => (string) ($translation['content'] ?? $record['content_zh'] ?? ''),
            ],
            'article' => [
                'title' => (string) ($translation['title'] ?? $record['title_zh'] ?? ''),
                'summary' => (string) ($translation['summary'] ?? $record['summary_zh'] ?? ''),
                'content' => (string) ($translation['content'] ?? $record['content_zh'] ?? ''),
            ],
            default => ['title' => '', 'summary' => '', 'content' => ''],
        };
    }

    public function upsertTranslation(string $entityType, int $entityId, string $languageCode, array $payload, string $status): void
    {
        [$table, $entityKey, $fields] = $this->translationMeta($entityType);
        $runtimePath = $this->translationStoragePath($table);
        if ($runtimePath !== '' && should_prefer_runtime_storage($runtimePath)) {
            $this->upsertRuntimeTranslation($table, $entityKey, $entityId, $languageCode, $payload, $fields, $status);

            return;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            try {
                $insertColumns = array_merge([$entityKey, 'language_code'], array_keys($fields), ['translation_status']);
                $placeholders = array_map(static fn (string $column): string => ':' . $column, $insertColumns);
                $updateColumns = array_map(static fn (string $column): string => sprintf('%s = VALUES(%s)', $column, $column), array_merge(array_keys($fields), ['translation_status']));

                $statement = $pdo->prepare(sprintf(
                    'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                    $table,
                    implode(', ', $insertColumns),
                    implode(', ', $placeholders),
                    implode(', ', $updateColumns)
                ));

                $params = [
                    $entityKey => $entityId,
                    'language_code' => $languageCode,
                    'translation_status' => $status,
                ];
                foreach ($fields as $targetField => $sourceField) {
                    $params[$targetField] = $payload[$sourceField] ?? null;
                }
                $statement->execute($params);

                return;
            } catch (\Throwable) {
            }
        }

        if ($runtimePath !== '') {
            $this->upsertRuntimeTranslation($table, $entityKey, $entityId, $languageCode, $payload, $fields, $status);
        }
    }

    public function translationRecord(string $entityType, int $entityId, string $languageCode): ?array
    {
        return $this->findTranslation($entityType, $entityId, $languageCode);
    }

    public function updateTranslationStatus(string $entityType, int $entityId, string $status): ?array
    {
        $record = $this->find($entityType, $entityId);
        if ($record === null) {
            return null;
        }

        if (!$this->tracksTranslationStatus($entityType)) {
            return $record;
        }

        $record['translation_status'] = $status;

        return $this->saveEntity($entityType, $entityId, $record);
    }

    public function applySeoResult(string $entityType, int $entityId, array $seoPayload, ?string $status = null): ?array
    {
        $record = $this->find($entityType, $entityId);
        if ($record === null || !$this->tracksSeoStatus($entityType)) {
            return $record;
        }

        $record['slug'] = (string) ($seoPayload['slug'] ?? ($record['slug'] ?? ''));
        $record['seo_title'] = (string) ($seoPayload['seo_title'] ?? ($record['seo_title'] ?? ''));
        $record['seo_keywords'] = (string) ($seoPayload['seo_keywords'] ?? ($record['seo_keywords'] ?? ''));
        $record['seo_description'] = (string) ($seoPayload['seo_description'] ?? ($record['seo_description'] ?? ''));
        if ($status !== null) {
            $record['seo_status'] = $status;
        }

        return $this->saveEntity($entityType, $entityId, $record);
    }

    public function updateSeoStatus(string $entityType, int $entityId, string $status): ?array
    {
        $record = $this->find($entityType, $entityId);
        if ($record === null || !$this->tracksSeoStatus($entityType)) {
            return $record;
        }

        $record['seo_status'] = $status;

        return $this->saveEntity($entityType, $entityId, $record);
    }

    private function saveEntity(string $entityType, int $entityId, array $record): ?array
    {
        return match ($entityType) {
            'product' => $this->productRepository->update($entityId, $record),
            'solution' => $this->solutionRepository->update($entityId, $record),
            'news' => $this->newsRepository->update($entityId, $record),
            'case' => $this->caseRepository->update($entityId, $record),
            'article' => $this->articleRepository->update($entityId, $record),
            'page' => $this->pageRepository->update($entityId, $record),
            'team_member' => $this->teamRepository->update($entityId, $record),
            'certificate' => $this->certificateRepository->update($entityId, $record),
            default => null,
        };
    }

    /**
     * @return array{0:string,1:string,2:array<string,string>}
     */
    private function translationMeta(string $entityType): array
    {
        return match ($entityType) {
            'product_category' => ['product_category_translations', 'category_id', ['name' => 'name']],
            'product' => ['product_translations', 'product_id', ['name' => 'name', 'summary' => 'summary', 'content' => 'content']],
            'solution_category' => ['solution_category_translations', 'category_id', ['name' => 'name']],
            'solution' => ['solution_translations', 'solution_id', ['name' => 'name', 'summary' => 'summary', 'content' => 'content', 'flow_text' => 'flow_text', 'capacity_text' => 'capacity_text']],
            'article_category' => ['article_category_translations', 'category_id', ['name' => 'name']],
            'news_category' => ['news_category_translations', 'category_id', ['name' => 'name']],
            'case_category' => ['case_category_translations', 'category_id', ['name' => 'name']],
            'news' => ['news_translations', 'news_id', ['title' => 'title', 'summary' => 'summary', 'content' => 'content']],
            'case' => ['case_translations', 'case_id', ['title' => 'title', 'summary' => 'summary', 'content' => 'content']],
            'article' => ['article_translations', 'article_id', ['title' => 'title', 'summary' => 'summary', 'content' => 'content']],
            'page' => ['page_translations', 'page_id', ['title' => 'title', 'summary' => 'summary', 'content' => 'content']],
            'team_member' => ['team_member_translations', 'team_member_id', ['name' => 'name', 'title' => 'title', 'department' => 'department', 'bio' => 'bio']],
            'certificate' => ['certificate_translations', 'certificate_id', ['name' => 'name', 'issuer' => 'issuer', 'description' => 'description']],
            'contact_field_type' => ['contact_field_type_translations', 'field_type_id', ['name' => 'name']],
            'contact_item' => ['contact_item_translations', 'contact_item_id', ['label' => 'label', 'description' => 'description']],
            'about_page' => ['about_page_translations', 'about_page_id', ['name' => 'name']],
            'about_block' => ['about_block_translations', 'block_id', ['title' => 'title', 'subtitle' => 'subtitle', 'content' => 'content']],
            'navigation_menu' => ['navigation_menu_translations', 'menu_id', ['name' => 'name']],
            'navigation_item' => ['navigation_item_translations', 'item_id', ['name' => 'name']],
            'homepage_section' => ['homepage_section_translations', 'section_id', ['title' => 'title', 'subtitle' => 'subtitle']],
            default => throw new \InvalidArgumentException('unsupported entity type'),
        };
    }

    private function translationEntityKey(string $entityType): string
    {
        return $this->translationMeta($entityType)[1];
    }

    private function findTranslation(string $entityType, int $entityId, string $languageCode): ?array
    {
        if ($entityId <= 0 || $languageCode === '' || !$this->supports($entityType)) {
            return null;
        }

        [$table, $entityKey, $fields] = $this->translationMeta($entityType);
        $runtimeRow = $this->findRuntimeTranslation($table, $entityKey, $entityId, $languageCode, array_keys($fields));
        if ($runtimeRow !== null && should_prefer_runtime_storage($this->translationStoragePath($table))) {
            return $runtimeRow;
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            try {
                $columns = implode(', ', array_merge([$entityKey, 'language_code'], array_keys($fields), ['translation_status']));
                $statement = $pdo->prepare(
                    sprintf(
                        'SELECT %s FROM %s WHERE %s = :entity_id AND language_code = :language_code LIMIT 1',
                        $columns,
                        $table,
                        $entityKey
                    )
                );
                $statement->execute([
                    'entity_id' => $entityId,
                    'language_code' => $languageCode,
                ]);
                $row = $statement->fetch();

                if (is_array($row)) {
                    return $row;
                }
            } catch (\Throwable) {
            }
        }

        return $runtimeRow;
    }

    private function tracksTranslationStatus(string $entityType): bool
    {
        return in_array($entityType, ['product', 'solution', 'article', 'news', 'case', 'page', 'team_member', 'certificate'], true);
    }

    private function tracksSeoStatus(string $entityType): bool
    {
        return in_array($entityType, ['product', 'solution', 'article', 'news', 'case', 'page', 'certificate'], true);
    }

    private function findAboutBlock(int $blockId): ?array
    {
        foreach ($this->aboutRepository->pages() as $page) {
            foreach (($page['blocks'] ?? []) as $block) {
                if ((int) ($block['id'] ?? 0) === $blockId) {
                    return $block;
                }
            }
        }

        return null;
    }

    private function findNavigationItem(int $itemId): ?array
    {
        foreach ($this->navigationRepository->menus() as $menu) {
            foreach (($menu['items'] ?? []) as $item) {
                if ((int) ($item['id'] ?? 0) === $itemId) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|array<int, mixed>
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

    private function translationStoragePath(string $table): string
    {
        $fileName = match ($table) {
            'product_category_translations' => 'product_category_translations.json',
            'product_translations' => 'product_translations.json',
            'solution_category_translations' => 'solution_category_translations.json',
            'solution_translations' => 'solution_translations.json',
            'article_category_translations' => 'article_category_translations.json',
            'news_category_translations' => 'news_category_translations.json',
            'case_category_translations' => 'case_category_translations.json',
            'news_translations' => 'news_translations.json',
            'case_translations' => 'case_translations.json',
            'article_translations' => 'article_translations.json',
            'page_translations' => 'page_translations.json',
            'team_member_translations' => 'team_member_translations.json',
            'certificate_translations' => 'certificate_translations.json',
            'contact_field_type_translations' => 'contact_field_type_translations.json',
            'contact_item_translations' => 'contact_item_translations.json',
            'about_page_translations' => 'about_page_translations.json',
            'about_block_translations' => 'about_block_translations.json',
            'navigation_menu_translations' => 'navigation_menu_translations.json',
            'navigation_item_translations' => 'navigation_item_translations.json',
            'homepage_section_translations' => 'homepage_section_translations.json',
            default => '',
        };

        if ($fileName === '') {
            return '';
        }

        return dirname(__DIR__, 3) . '/runtime/storage/' . $fileName;
    }

    /**
     * @param array<int, string> $fields
     * @return array<string, mixed>|null
     */
    private function findRuntimeTranslation(
        string $table,
        string $entityKey,
        int $entityId,
        string $languageCode,
        array $fields
    ): ?array {
        $path = $this->translationStoragePath($table);
        if ($path === '' || !is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return null;
        }

        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }

            if ((int) ($row[$entityKey] ?? 0) !== $entityId) {
                continue;
            }

            if (strtolower(trim((string) ($row['language_code'] ?? ''))) !== strtolower($languageCode)) {
                continue;
            }

            $payload = [
                $entityKey => $entityId,
                'language_code' => $languageCode,
                'translation_status' => (string) ($row['translation_status'] ?? ''),
            ];

            if (isset($row['id'])) {
                $payload['id'] = (int) $row['id'];
            }

            foreach ($fields as $field) {
                $payload[$field] = $row[$field] ?? null;
            }

            return $payload;
        }

        return null;
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, mixed> $payload
     */
    private function upsertRuntimeTranslation(
        string $table,
        string $entityKey,
        int $entityId,
        string $languageCode,
        array $payload,
        array $fields,
        string $status
    ): void {
        $path = $this->translationStoragePath($table);
        if ($path === '') {
            return;
        }

        $rows = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $rows = array_values(array_filter($decoded, static fn (mixed $row): bool => is_array($row)));
            }
        }

        $updated = false;
        foreach ($rows as $index => $row) {
            if ((int) ($row[$entityKey] ?? 0) !== $entityId) {
                continue;
            }

            if (strtolower(trim((string) ($row['language_code'] ?? ''))) !== strtolower($languageCode)) {
                continue;
            }

            $normalized = $row;
            $normalized[$entityKey] = $entityId;
            $normalized['language_code'] = $languageCode;
            $normalized['translation_status'] = $status;
            foreach ($fields as $targetField => $sourceField) {
                $normalized[$targetField] = $payload[$sourceField] ?? null;
            }

            $rows[$index] = $normalized;
            $updated = true;
            break;
        }

        if (!$updated) {
            $nextId = 1;
            foreach ($rows as $row) {
                $nextId = max($nextId, ((int) ($row['id'] ?? 0)) + 1);
            }

            $normalized = [
                'id' => $nextId,
                $entityKey => $entityId,
                'language_code' => $languageCode,
                'translation_status' => $status,
            ];
            foreach ($fields as $targetField => $sourceField) {
                $normalized[$targetField] = $payload[$sourceField] ?? null;
            }

            $rows[] = $normalized;
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
