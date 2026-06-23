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

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.create', 'homepage_section', $section, 'homepage section created');

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
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }
        return $section;
    }

    public function updateSection(int $id, array $data): array
    {
        $updated = $this->homepageRepository->update($id, $data);
        if ($updated === null) {
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->sharedTranslationPipelineService->syncEntity('homepage_section', $id);

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.update', 'homepage_section', $updated, 'homepage section updated');

        $meta = $this->homepageRepository->publishMeta();
        $meta['draft_updated_at'] = date('Y-m-d H:i:s');
        $meta['has_unpublished_changes'] = 1;
        $this->homepageRepository->savePublishMeta($meta);

        return $updated;
    }

    public function sortSections(array $sorts): array
    {
        $result = $this->homepageRepository->updateSorts($sorts);

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.sort', 'homepage_section', ['sorts' => $sorts], 'homepage sections sorted');

        $meta = $this->homepageRepository->publishMeta();
        $meta['draft_updated_at'] = date('Y-m-d H:i:s');
        $meta['has_unpublished_changes'] = 1;
        $this->homepageRepository->savePublishMeta($meta);

        return $result;
    }

    public function updateSectionStatus(int $id, int $isEnabled): array
    {
        $result = $this->homepageRepository->updateStatus($id, $isEnabled);

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.status.update', 'homepage_section', ['id' => $id, 'is_enabled' => $isEnabled], 'homepage section status updated');

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

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.items.update', 'homepage_section', ['section_id' => $sectionId, 'items' => $updated], 'homepage section items updated');

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

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.publish', 'homepage', $meta, 'homepage published');

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

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.restore', 'homepage', $meta, 'homepage restored from live');

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
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
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
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
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
            throw new BusinessException('record not found', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('homepage', 'homepage.featured.update', 'homepage', ['source_type' => $sourceType, 'id' => $id, 'data' => $updated], 'homepage featured item updated');

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
