<?php

declare(strict_types=1);

namespace app\service\homepage;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\HomepageRepository;
use app\repository\ProductRepository;
use app\repository\SolutionRepository;
use app\repository\ArticleRepository;
use app\repository\NewsRepository;
use app\repository\CaseRepository;
use app\service\log\OperationLogService;
use app\service\translation\SharedTranslationPipelineService;

final class HomepageService
{
    private const DEFAULT_SECTION_COPY = [
        'hero' => [
            'section_key' => 'hero',
            'section_type' => 'fixed_config',
            'title_zh' => '首页主视觉',
            'subtitle_zh' => '面向海外客户展示整线与单机设备能力',
            'fetch_mode' => 'fixed_config',
            'extra_config' => ['cta_text' => '查看方案'],
        ],
        'hero_banner' => [
            'section_key' => 'hero_banner',
            'section_type' => 'fixed_config',
            'title_zh' => '首页主视觉',
            'subtitle_zh' => '面向海外客户展示整线与单机设备能力',
            'fetch_mode' => 'fixed_config',
            'extra_config' => ['cta_text' => '查看方案'],
        ],
        'featured_products' => [
            'section_key' => 'featured_products',
            'section_type' => 'product_list',
            'title_zh' => '推荐产品',
            'subtitle_zh' => '首页推荐池',
            'fetch_mode' => 'auto_latest',
            'extra_config' => ['limit' => 6],
        ],
        'featured_solutions' => [
            'section_key' => 'featured_solutions',
            'section_type' => 'solution_list',
            'title_zh' => '推荐方案',
            'subtitle_zh' => '首页推荐池',
            'fetch_mode' => 'auto_latest',
            'extra_config' => ['limit' => 4],
        ],
        'company_news' => [
            'section_key' => 'company_news',
            'section_type' => 'news_list',
            'title_zh' => '企业新闻',
            'subtitle_zh' => '展示企业动态与内容更新',
            'fetch_mode' => 'auto_latest',
            'extra_config' => ['limit' => 6],
        ],
        'customer_cases' => [
            'section_key' => 'customer_cases',
            'section_type' => 'case_list',
            'title_zh' => '客户案例',
            'subtitle_zh' => '展示交付案例与项目成果',
            'fetch_mode' => 'auto_latest',
            'extra_config' => ['limit' => 6],
        ],
        'production_lines' => [
            'section_key' => 'production_lines',
            'section_type' => 'solution_list',
            'title_zh' => '热门生产线',
            'subtitle_zh' => '食品加工整线解决方案',
            'fetch_mode' => 'manual_pick',
            'extra_config' => ['limit' => 6],
        ],
    ];

    public function __construct(
        private readonly HomepageRepository $homepageRepository = new HomepageRepository(),
        private readonly ProductRepository $productRepository = new ProductRepository(),
        private readonly SolutionRepository $solutionRepository = new SolutionRepository(),
        private readonly ArticleRepository $articleRepository = new ArticleRepository(),
        private readonly NewsRepository $newsRepository = new NewsRepository(),
        private readonly CaseRepository $caseRepository = new CaseRepository(),
        private readonly OperationLogService $operationLogService = new OperationLogService(),
        private readonly SharedTranslationPipelineService $sharedTranslationPipelineService = new SharedTranslationPipelineService()
    ) {
    }

    public function list(): array
    {
        $this->ensureSplitContentSections();
        return $this->homepageRepository->list();
    }

    public function bootstrap(): array
    {
        $sections = $this->list();
        $workflow = $this->workflow();
        $preferredSectionId = isset($sections[0]['id']) ? (int) $sections[0]['id'] : 0;
        $currentSection = $preferredSectionId > 0 ? $this->sectionDetail($preferredSectionId) : null;
        $currentItems = $preferredSectionId > 0 ? $this->sectionItems($preferredSectionId) : ['items' => []];

        return [
            'sections' => $sections,
            'workflow' => $workflow,
            'current_section' => $currentSection,
            'current_items' => $currentItems,
        ];
    }

    public function createSection(array $data): array
    {
        $section = $this->homepageRepository->create($data);
        $this->sharedTranslationPipelineService->syncEntity('homepage_section', (int) ($section['id'] ?? 0));

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.create', 'homepage_section', $section, '首页板块已创建');

        $meta = $this->homepageRepository->publishMeta();
        $meta['draft_updated_at'] = date('Y-m-d H:i:s');
        $meta['has_unpublished_changes'] = 1;
        $this->homepageRepository->savePublishMeta($meta);

        return $section;
    }

    public function sectionDetail(int $id): array
    {
        $this->ensureSplitContentSections();
        $section = $this->homepageRepository->find($id);
        if ($section === null) {
            throw new BusinessException('首页板块不存在', ErrorCode::NOT_FOUND);
        }
        return $section;
    }

    public function updateSection(int $id, array $data): array
    {
        $updated = $this->homepageRepository->update($id, $data);
        if ($updated === null) {
            throw new BusinessException('首页板块不存在', ErrorCode::NOT_FOUND);
        }

        $this->sharedTranslationPipelineService->syncEntity('homepage_section', $id);

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.update', 'homepage_section', $updated, '首页板块已更新');

        $meta = $this->homepageRepository->publishMeta();
        $meta['draft_updated_at'] = date('Y-m-d H:i:s');
        $meta['has_unpublished_changes'] = 1;
        $this->homepageRepository->savePublishMeta($meta);

        return $updated;
    }

    public function sortSections(array $sorts): array
    {
        $result = $this->homepageRepository->updateSorts($sorts);

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.sort', 'homepage_section', ['sorts' => $sorts], '首页板块排序已更新');

        $meta = $this->homepageRepository->publishMeta();
        $meta['draft_updated_at'] = date('Y-m-d H:i:s');
        $meta['has_unpublished_changes'] = 1;
        $this->homepageRepository->savePublishMeta($meta);

        return $result;
    }

    public function updateSectionStatus(int $id, int $isEnabled): array
    {
        $result = $this->homepageRepository->updateStatus($id, $isEnabled);

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.status.update', 'homepage_section', ['id' => $id, 'is_enabled' => $isEnabled], '首页板块状态已更新');

        $meta = $this->homepageRepository->publishMeta();
        $meta['draft_updated_at'] = date('Y-m-d H:i:s');
        $meta['has_unpublished_changes'] = 1;
        $this->homepageRepository->savePublishMeta($meta);

        return $result;
    }

    public function sectionItems(int $sectionId): array
    {
        $items = $this->homepageRepository->listItems($sectionId);

        foreach ($items as &$item) {
            $sourceRecord = $this->resolveSourceRecord(
                (string) ($item['source_type'] ?? ''),
                (int) ($item['source_id'] ?? 0)
            );
            $item['source_record'] = $sourceRecord;
            $this->applyDisplayOverrides($item, $sourceRecord);
        }
        unset($item);

        return ['items' => $items];
    }

    public function updateSectionItems(int $sectionId, array $items): array
    {
        $updated = $this->homepageRepository->replaceItems($sectionId, $items);

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.items.update', 'homepage_section', ['section_id' => $sectionId, 'items' => $updated], '首页板块项目已更新');

        $meta = $this->homepageRepository->publishMeta();
        $meta['draft_updated_at'] = date('Y-m-d H:i:s');
        $meta['has_unpublished_changes'] = 1;
        $this->homepageRepository->savePublishMeta($meta);

        return ['items' => $updated];
    }

    public function previewPayload(): array
    {
        $this->ensureSplitContentSections();
        $sections = $this->homepageRepository->list();
        foreach ($sections as &$section) {
            $items = $this->homepageRepository->listItems((int) ($section['id'] ?? 0));
            foreach ($items as &$item) {
                $sourceRecord = $this->resolveSourceRecord(
                    (string) ($item['source_type'] ?? ''),
                    (int) ($item['source_id'] ?? 0)
                );
                $item['source_record'] = $sourceRecord;
                $this->applyDisplayOverrides($item, $sourceRecord);
            }
            unset($item);
            $section['items'] = $items;
        }
        unset($section);

        return ['sections' => $sections];
    }

    public function workflow(): array
    {
        return $this->homepageRepository->publishMeta();
    }

    public function publish(array $operator): array
    {
        $this->ensureSplitContentSections();
        $snapshot = $this->previewPayload();
        $snapshot['section_items'] = $this->homepageRepository->allSectionItems();
        $snapshot['featured_pool'] = [
            'product' => array_values(array_filter(
                $this->productRepository->list(['page' => 1, 'page_size' => 200])['items'] ?? [],
                static fn (array $item): bool => (int) ($item['is_home_featured'] ?? 0) === 1
            )),
            'solution' => array_values(array_filter(
                $this->solutionRepository->list(['page' => 1, 'page_size' => 200])['items'] ?? [],
                static fn (array $item): bool => (int) ($item['is_home_featured'] ?? 0) === 1
            )),
            'article' => array_values(array_filter(
                $this->articleRepository->list(['page' => 1, 'page_size' => 200])['items'] ?? [],
                static fn (array $item): bool => (int) ($item['is_home_featured'] ?? 0) === 1
            )),
            'news' => array_values(array_filter(
                $this->newsRepository->list(['page' => 1, 'page_size' => 200])['items'] ?? [],
                static fn (array $item): bool => (int) ($item['is_home_featured'] ?? 0) === 1
            )),
            'case' => array_values(array_filter(
                $this->caseRepository->list(['page' => 1, 'page_size' => 200])['items'] ?? [],
                static fn (array $item): bool => (int) ($item['is_home_featured'] ?? 0) === 1
            )),
        ];
        $this->homepageRepository->savePublishedSnapshot($snapshot);

        $now = date('Y-m-d H:i:s');
        $meta = [
            'draft_updated_at' => null,
            'live_updated_at' => $now,
            'last_published_by' => (string) ($operator['nickname'] ?? ''),
            'last_restored_by' => '',
            'has_unpublished_changes' => 0,
            'publish_log' => [],
        ];
        $this->homepageRepository->savePublishMeta($meta);

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.publish', 'homepage', $meta, '首页已发布');

        return $meta;
    }

    public function restoreLive(array $operator): array
    {
        $snapshot = $this->homepageRepository->publishedSnapshot();
        if ($snapshot !== []) {
            $this->homepageRepository->replaceSnapshot($snapshot);
            $this->restoreFeaturedPool($snapshot['featured_pool'] ?? []);
        }

        $now = date('Y-m-d H:i:s');
        $meta = [
            'draft_updated_at' => $now,
            'live_updated_at' => null,
            'last_published_by' => '',
            'last_restored_by' => (string) ($operator['nickname'] ?? ''),
            'has_unpublished_changes' => 0,
            'publish_log' => [],
        ];
        $this->homepageRepository->savePublishMeta($meta);

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.restore', 'homepage', $meta, '首页已根据线上快照恢复草稿');

        return $meta;
    }

    private function resolveSourceRecord(string $sourceType, int $sourceId): ?array
    {
        if ($sourceId <= 0) {
            return null;
        }

        return match ($sourceType) {
            'product' => $this->productRepository->find($sourceId),
            'solution' => $this->solutionRepository->find($sourceId),
            'news' => $this->newsRepository->find($sourceId),
            'case' => $this->caseRepository->find($sourceId),
            'article' => $this->articleRepository->find($sourceId),
            default => null,
        };
    }

    public function updateFeaturedItem(string $sourceType, int $id, array $data): array
    {
        if ($id <= 0) {
            throw new BusinessException('推荐内容不存在', ErrorCode::NOT_FOUND);
        }

        $record = match ($sourceType) {
            'product' => $this->productRepository->find($id),
            'solution' => $this->solutionRepository->find($id),
            'news' => $this->newsRepository->find($id),
            'case' => $this->caseRepository->find($id),
            'article' => $this->articleRepository->find($id),
            default => null,
        };

        if ($record === null) {
            throw new BusinessException('推荐内容不存在', ErrorCode::NOT_FOUND);
        }

        $record['is_home_featured'] = !empty($data['is_home_featured']) ? 1 : 0;
        $record['manual_sort'] = (int) ($data['manual_sort'] ?? ($record['manual_sort'] ?? 0));

        $updated = match ($sourceType) {
            'product' => $this->productRepository->update($id, $record),
            'solution' => $this->solutionRepository->update($id, $record),
            'news' => $this->newsRepository->update($id, $record),
            'case' => $this->caseRepository->update($id, $record),
            'article' => $this->articleRepository->update($id, $record),
            default => null,
        };

        if ($updated === null) {
            throw new BusinessException('推荐内容不存在', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.featured.update', 'homepage', ['source_type' => $sourceType, 'id' => $id, 'data' => $updated], '首页推荐已更新');

        $meta = $this->homepageRepository->publishMeta();
        $meta['draft_updated_at'] = date('Y-m-d H:i:s');
        $meta['has_unpublished_changes'] = 1;
        $this->homepageRepository->savePublishMeta($meta);

        return $updated;
    }

    private function restoreFeaturedPool(array $featuredPool): void
    {
        foreach (['product', 'solution', 'article', 'news', 'case'] as $sourceType) {
            $items = is_array($featuredPool[$sourceType] ?? null) ? $featuredPool[$sourceType] : [];
            $snapshotById = [];
            foreach ($items as $item) {
                $snapshotById[(int) ($item['id'] ?? 0)] = $item;
            }

            $listPayload = match ($sourceType) {
                'product' => $this->productRepository->list(['page' => 1, 'page_size' => 500])['items'] ?? [],
                'solution' => $this->solutionRepository->list(['page' => 1, 'page_size' => 500])['items'] ?? [],
                'article' => $this->articleRepository->list(['page' => 1, 'page_size' => 500])['items'] ?? [],
                'news' => $this->newsRepository->list(['page' => 1, 'page_size' => 500])['items'] ?? [],
                'case' => $this->caseRepository->list(['page' => 1, 'page_size' => 500])['items'] ?? [],
                default => [],
            };

            foreach ($listPayload as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                if (isset($snapshotById[$id])) {
                    $row['is_home_featured'] = (int) ($snapshotById[$id]['is_home_featured'] ?? 0);
                    $row['manual_sort'] = (int) ($snapshotById[$id]['manual_sort'] ?? ($row['manual_sort'] ?? 0));
                } else {
                    $row['is_home_featured'] = 0;
                }

                match ($sourceType) {
                    'product' => $this->productRepository->update($id, $row),
                    'solution' => $this->solutionRepository->update($id, $row),
                    'article' => $this->articleRepository->update($id, $row),
                    'news' => $this->newsRepository->update($id, $row),
                    'case' => $this->caseRepository->update($id, $row),
                    default => null,
                };
            }
        }
    }

    private function ensureSplitContentSections(): void
    {
        $sections = $this->homepageRepository->list();
        if ($sections === []) {
            return;
        }

        $this->repairCorruptedDefaultSections($sections);
        $sections = $this->homepageRepository->list();

        $newsSection = null;
        $caseSection = null;
        $legacyCombinedSection = null;

        foreach ($sections as $section) {
            $sectionKey = trim((string) ($section['section_key'] ?? ''));
            $title = trim((string) ($section['title_zh'] ?? ''));

            if (in_array($sectionKey, ['company_news', 'featured_articles'], true)) {
                $newsSection = $section;
            }

            if (in_array($sectionKey, ['customer_cases', 'featured_cases'], true)) {
                $caseSection = $section;
            }

            if ($legacyCombinedSection === null && ($sectionKey === 'featured_articles' || $title === '新闻与案例')) {
                $legacyCombinedSection = $section;
            }
        }

        if ($newsSection !== null) {
            $newsSectionKey = trim((string) ($newsSection['section_key'] ?? ''));
            $newsTitle = trim((string) ($newsSection['title_zh'] ?? ''));
            $newsType = trim((string) ($newsSection['section_type'] ?? ''));

            if ($newsSectionKey !== 'company_news' || $newsType !== 'news_list' || $newsTitle === '' || $newsTitle === '新闻与案例') {
                $this->homepageRepository->update((int) ($newsSection['id'] ?? 0), [
                    'section_key' => 'company_news',
                    'section_type' => 'news_list',
                    'title_zh' => ($newsTitle === '' || $newsTitle === '新闻与案例') ? '企业新闻' : $newsTitle,
                ]);
            }
        }

        if ($caseSection !== null) {
            $caseSectionKey = trim((string) ($caseSection['section_key'] ?? ''));
            $caseTitle = trim((string) ($caseSection['title_zh'] ?? ''));
            $caseType = trim((string) ($caseSection['section_type'] ?? ''));

            if ($caseSectionKey !== 'customer_cases' || $caseType !== 'case_list' || $caseTitle === '') {
                $this->homepageRepository->update((int) ($caseSection['id'] ?? 0), [
                    'section_key' => 'customer_cases',
                    'section_type' => 'case_list',
                    'title_zh' => $caseTitle !== '' ? $caseTitle : '客户案例',
                ]);
            }
        }

        if ($caseSection === null && $legacyCombinedSection !== null) {
            $legacySort = (int) ($legacyCombinedSection['sort'] ?? 0);
            $legacySubtitle = (string) ($legacyCombinedSection['subtitle_zh'] ?? '');
            $legacyFetchMode = (string) ($legacyCombinedSection['fetch_mode'] ?? 'auto_latest');
            $legacyExtraConfig = $legacyCombinedSection['extra_config'] ?? [];
            if (is_string($legacyExtraConfig)) {
                $decoded = json_decode($legacyExtraConfig, true);
                $legacyExtraConfig = is_array($decoded) ? $decoded : [];
            }

            $this->homepageRepository->create([
                'section_key' => 'customer_cases',
                'section_type' => 'case_list',
                'title_zh' => '客户案例',
                'subtitle_zh' => $legacySubtitle,
                'fetch_mode' => $legacyFetchMode !== '' ? $legacyFetchMode : 'auto_latest',
                'extra_config' => is_array($legacyExtraConfig) ? $legacyExtraConfig : [],
                'sort' => max($legacySort - 1, 0),
                'is_enabled' => (int) ($legacyCombinedSection['is_enabled'] ?? 1),
            ]);
        }
    }

    private function repairCorruptedDefaultSections(array $sections): void
    {
        foreach ($sections as $section) {
            $sectionId = (int) ($section['id'] ?? 0);
            $sectionKey = trim((string) ($section['section_key'] ?? ''));
            if ($sectionId <= 0 || $sectionKey === '' || !isset(self::DEFAULT_SECTION_COPY[$sectionKey])) {
                continue;
            }

            $defaults = self::DEFAULT_SECTION_COPY[$sectionKey];
            $extraConfig = $section['extra_config'] ?? [];
            if (is_string($extraConfig)) {
                $decoded = json_decode($extraConfig, true);
                $extraConfig = is_array($decoded) ? $decoded : [];
            }

            $patch = [];
            if ($this->looksCorruptedText((string) ($section['title_zh'] ?? ''))) {
                $patch['title_zh'] = $defaults['title_zh'];
            }
            if ($this->looksCorruptedText((string) ($section['subtitle_zh'] ?? ''))) {
                $patch['subtitle_zh'] = $defaults['subtitle_zh'];
            }
            if ($this->looksCorruptedText((string) ($section['section_type'] ?? ''))) {
                $patch['section_type'] = $defaults['section_type'];
            }
            if ($this->looksCorruptedText((string) ($section['fetch_mode'] ?? ''))) {
                $patch['fetch_mode'] = $defaults['fetch_mode'];
            }

            if ($sectionKey === 'hero' || $sectionKey === 'hero_banner') {
                $ctaText = trim((string) ($extraConfig['cta_text'] ?? ''));
                if ($this->looksCorruptedText($ctaText)) {
                    $extraConfig['cta_text'] = $defaults['extra_config']['cta_text'];
                    $patch['extra_config'] = $extraConfig;
                }
            } elseif (array_key_exists('limit', $defaults['extra_config'])) {
                $limit = (int) ($extraConfig['limit'] ?? 0);
                if ($limit <= 0) {
                    $extraConfig['limit'] = $defaults['extra_config']['limit'];
                    $patch['extra_config'] = $extraConfig;
                }
            }

            if ($patch !== []) {
                $this->homepageRepository->update($sectionId, $patch);
            }
        }
    }

    private function looksCorruptedText(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return true;
        }

        return preg_match('/^[?？]+$/u', $trimmed) === 1;
    }

    private function applyDisplayOverrides(array &$item, ?array $sourceRecord): void
    {
        $titleOverride = trim((string) ($item['title_override_zh'] ?? ''));
        $summaryOverride = trim((string) ($item['summary_override_zh'] ?? ''));

        $defaultTitle = $this->defaultSourceTitle((string) ($item['source_type'] ?? ''), $sourceRecord);
        $defaultSummary = $this->defaultSourceSummary((string) ($item['source_type'] ?? ''), $sourceRecord);

        $item['display_title_zh'] = $titleOverride !== '' ? $titleOverride : $defaultTitle;
        $item['display_summary_zh'] = $summaryOverride !== '' ? $summaryOverride : $defaultSummary;
        $item['display_title'] = $item['display_title_zh'];
        $item['display_summary'] = $item['display_summary_zh'];
    }

    private function defaultSourceTitle(string $sourceType, ?array $sourceRecord): string
    {
        if (!is_array($sourceRecord)) {
            return '';
        }

        return match ($sourceType) {
            'product', 'solution' => trim((string) ($sourceRecord['name_zh'] ?? '')),
            default => trim((string) ($sourceRecord['title_zh'] ?? '')),
        };
    }

    private function defaultSourceSummary(string $sourceType, ?array $sourceRecord): string
    {
        if (!is_array($sourceRecord)) {
            return '';
        }

        return match ($sourceType) {
            'product', 'solution' => trim((string) ($sourceRecord['summary_zh'] ?? '')),
            default => trim((string) ($sourceRecord['summary_zh'] ?? '')),
        };
    }
}
