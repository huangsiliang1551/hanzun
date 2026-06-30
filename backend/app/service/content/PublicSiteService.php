<?php

declare(strict_types=1);

namespace app\service\content;

use app\common\database\DatabaseManager;
use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\AboutRepository;
use app\repository\ArticleRepository;
use app\repository\CertificateRepository;
use app\repository\ContactRepository;
use app\repository\HomepageRepository;
use app\repository\LanguageRepository;
use app\repository\NavigationRepository;
use app\repository\NewsRepository;
use app\repository\PageRepository;
use app\repository\ProductRepository;
use app\repository\MediaGalleryRepository;
use app\repository\MediaRepository;
use app\repository\SeoRepository;
use app\repository\SolutionRepository;
use app\repository\CaseRepository;
use app\repository\SystemSettingRepository;
use app\repository\TeamRepository;
use PDO;

final class PublicSiteService
{
    /** @var array<string, array<string, mixed>> */
    private array $translationCache = [];
    /** @var array<string, array<string, array<string, mixed>>> */
    private array $translationTableCache = [];
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $runtimeTranslationFileCache = [];
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $publishedContentCache = [];
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $repositoryItemsCache = [];
    /** @var array<string, array<string, mixed>> */
    private array $detailCache = [];

    public function __construct(
        private readonly LanguageRepository $languageRepository = new LanguageRepository(),
        private readonly NavigationRepository $navigationRepository = new NavigationRepository(),
        private readonly HomepageRepository $homepageRepository = new HomepageRepository(),
        private readonly ContactRepository $contactRepository = new ContactRepository(),
        private readonly ProductRepository $productRepository = new ProductRepository(),
        private readonly SolutionRepository $solutionRepository = new SolutionRepository(),
        private readonly ArticleRepository $articleRepository = new ArticleRepository(),
        private readonly NewsRepository $newsRepository = new NewsRepository(),
        private readonly CaseRepository $caseRepository = new CaseRepository(),
        private readonly PageRepository $pageRepository = new PageRepository(),
        private readonly MediaRepository $mediaRepository = new MediaRepository(),
        private readonly MediaGalleryRepository $mediaGalleryRepository = new MediaGalleryRepository(),
        private readonly AboutRepository $aboutRepository = new AboutRepository(),
        private readonly TeamRepository $teamRepository = new TeamRepository(),
        private readonly CertificateRepository $certificateRepository = new CertificateRepository(),
        private readonly SeoRepository $seoRepository = new SeoRepository(),
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository(),
        private readonly AdService $adService = new AdService(),
        private readonly ContentWorkflowService $contentWorkflowService = new ContentWorkflowService()
    ) {
    }

    public function bootstrap(string $languageCode = 'zh'): array
    {
        return [
            'language' => $this->languagePayload($languageCode),
            'languages' => $this->languages(),
            'site' => $this->site($languageCode),
            'navigation' => $this->navigation(null, $languageCode),
            'homepage' => $this->homepage($languageCode),
            'ads' => $this->ads($languageCode),
            'contact' => $this->contact($languageCode),
            'certificates' => array_map(
                fn(array $r) => $this->localizeCertificate($r, $languageCode),
                $this->publishedCertificates()
            ),
            'team_members' => array_map(
                fn(array $r) => $this->localizeTeamMember($r, $languageCode),
                $this->publishedTeamMembers()
            ),
        ];
    }

    public function site(string $languageCode = 'zh'): array
    {
        $config = $this->systemSettingRepository->siteConfig();
        $settingTextRepository = new \app\repository\SettingTextRepository();
        $languageCode = $this->normalizeLanguageCode($languageCode);

        $localized = static function (string $field, string $fallback) use ($settingTextRepository, $languageCode): string {
            if ($languageCode === '') {
                return $fallback;
            }

            return $settingTextRepository->getText('site_config', $field, $languageCode, $fallback);
        };

        return [
            'site_name' => $localized('site_name', (string) ($config['site_name'] ?? 'HANZUN')),
            'site_title' => $localized('site_title', (string) ($config['site_title'] ?? '')),
            'logo_url' => (string) ($config['logo_url'] ?? ''),
            'logo_alt' => $localized('logo_alt', (string) ($config['logo_alt'] ?? '')),
            'company_name' => $localized('company_name', (string) ($config['company_name'] ?? '')),
            'company_subtitle' => $localized('company_subtitle', (string) ($config['company_subtitle'] ?? '')),
            'meta_description' => $localized('meta_description', (string) ($config['meta_description'] ?? '')),
            'footer_text' => $localized('footer_text', (string) ($config['footer_text'] ?? '')),
            'language_strategy' => (string) ($config['language_strategy'] ?? 'ua-first'),
            'default_language' => (string) ($config['default_language'] ?? 'zh'),
            'social_linkedin' => (string) ($config['social_linkedin'] ?? ''),
            'social_youtube' => (string) ($config['social_youtube'] ?? ''),
            'enterprise_video_url' => (string) ($config['enterprise_video_url'] ?? ''),
            'hero_image_url' => (string) ($config['hero_image_url'] ?? ''),
            'hero_image_alt' => $localized('hero_image_alt', (string) ($config['hero_image_alt'] ?? '')),
            'hero_title' => $localized('hero_title', (string) ($config['hero_title'] ?? '')),
            'hero_subtitle' => $localized('hero_subtitle', (string) ($config['hero_subtitle'] ?? '')),
            'hero_cta_primary' => $localized('hero_cta_primary', (string) ($config['hero_cta_primary'] ?? '')),
            'hero_cta_secondary' => $localized('hero_cta_secondary', (string) ($config['hero_cta_secondary'] ?? '')),
            'notice_image_url' => (string) ($config['notice_image_url'] ?? ''),
            'notice_title' => $localized('notice_title', (string) ($config['notice_title'] ?? '')),
            'notice_content' => $localized('notice_content', (string) ($config['notice_content'] ?? '')),
            'service_support_title' => $localized('service_support_title', (string) ($config['service_support_title'] ?? '')),
            'service_support_line_1' => $localized('service_support_line_1', (string) ($config['service_support_line_1'] ?? '')),
            'service_support_line_2' => $localized('service_support_line_2', (string) ($config['service_support_line_2'] ?? '')),
            'service_support_line_3' => $localized('service_support_line_3', (string) ($config['service_support_line_3'] ?? '')),
            'service_support_line_4' => $localized('service_support_line_4', (string) ($config['service_support_line_4'] ?? '')),
        ];
    }

    public function languages(): array
    {
        $items = [];
        foreach (array_values(array_filter(
            $this->languageRepository->list(),
            static fn (array $item): bool => (int) ($item['is_enabled'] ?? 0) === 1
        )) as $item) {
            $code = (string) ($item['code'] ?? '');
            $meta = $this->languageMeta($code, (string) ($item['name'] ?? ''));
            $items[] = [
                'id' => (int) ($item['id'] ?? 0),
                'code' => $meta['code'],
                'name' => (string) ($item['name'] ?? ''),
                'country' => $meta['country'],
                'native' => $meta['native'],
                'content' => $meta['content'],
                'htmlLang' => $meta['htmlLang'],
                'continent' => $meta['continent'],
                'flag_code' => $meta['flag_code'],
                'is_default' => (int) ($item['is_default'] ?? 0),
                'is_enabled' => (int) ($item['is_enabled'] ?? 0),
                'sort' => (int) ($item['sort'] ?? 0),
            ];
        }

        return array_values($items);
    }

    public function resolveLanguage(?string $requestedCode = null, string $acceptLanguage = ''): array
    {
        $enabledCodes = [];
        $defaultCode = 'zh';
        foreach ($this->languageRepository->list() as $language) {
            if ((int) ($language['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $code = $this->normalizeLanguageCode((string) ($language['code'] ?? ''));
            if ($code === '') {
                continue;
            }

            $enabledCodes[$code] = true;
            if ((int) ($language['is_default'] ?? 0) === 1) {
                $defaultCode = $code;
            }
        }

        if ($enabledCodes === []) {
            $enabledCodes['zh'] = true;
        }

        $requested = $this->normalizeLanguageCode((string) $requestedCode);
        $headerCandidates = $this->headerLanguageCandidates($acceptLanguage);

        $resolved = $defaultCode;
        if ($requested !== '' && isset($enabledCodes[$requested])) {
            $resolved = $requested;
        } else {
            foreach ($headerCandidates as $candidate) {
                if (isset($enabledCodes[$candidate])) {
                    $resolved = $candidate;
                    break;
                }
            }
        }

        return [
            'requested_code' => $requested !== '' ? $requested : null,
            'resolved_code' => $resolved,
            'default_code' => $defaultCode,
            'available_codes' => array_values(array_keys($enabledCodes)),
        ];
    }

    public function navigation(?string $menuPosition = null, string $languageCode = 'zh'): array
    {
        $menus = array_values(array_filter(
            $this->navigationRepository->menus(),
            static function (array $menu) use ($menuPosition): bool {
                if ((int) ($menu['is_enabled'] ?? 0) !== 1) {
                    return false;
                }

                if ($menuPosition === null || $menuPosition === '') {
                    return true;
                }

                return (string) ($menu['menu_position'] ?? 'header') === $menuPosition;
            }
        ));

        foreach ($menus as &$menu) {
            $translation = $this->translationRow(
                'navigation_menu_translations',
                'menu_id',
                (int) ($menu['id'] ?? 0),
                $languageCode,
                ['name']
            );
            $menu['name'] = trim((string) ($translation['name'] ?? '')) !== '' ? (string) $translation['name'] : (string) ($menu['name_zh'] ?? '');
            $menu['language_code'] = $languageCode;
            $menu['items'] = $this->localizeNavigationTree($this->buildNavigationTree(array_values(array_filter(
                $menu['items'] ?? [],
                static fn (array $item): bool => (int) ($item['is_enabled'] ?? 0) === 1
            ))), $languageCode);
        }

        return $menus;
    }

    public function homepage(string $languageCode = 'zh'): array
    {
        $snapshot = $this->homepageRepository->publishedSnapshot();
        $sections = $snapshot['sections'] ?? [];
        $sectionItems = $snapshot['section_items'] ?? [];
        if (!is_array($sections) || $sections === []) {
            $sections = $this->homepageRepository->list();
            $sectionItems = $this->homepageRepository->allSectionItems();
        }

        $featuredProducts = $this->publishedProducts();
        $featuredSolutions = $this->publishedSolutions();
        $featuredNews = $this->publishedNews();
        $featuredCases = $this->publishedCases();

        $normalized = [];
        foreach ($sections as $section) {
            if ((int) ($section['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $translation = $this->translationRow(
                'homepage_section_translations',
                'section_id',
                (int) ($section['id'] ?? 0),
                $languageCode,
                ['title', 'subtitle', 'content']
            );
            $extraConfig = $this->decodeJsonField($section['extra_config'] ?? []);
            if (isset($extraConfig['cta_text']) && trim((string) ($translation['content'] ?? '')) !== '') {
                $extraConfig['cta_text'] = (string) $translation['content'];
            }
            $normalizedSectionType = $this->normalizeHomepageSectionType(
                (string) ($section['section_key'] ?? ''),
                (string) ($section['section_type'] ?? '')
            );
            $normalizedSectionKey = $this->normalizeHomepageSectionKey(
                (string) ($section['section_key'] ?? ''),
                $normalizedSectionType
            );
            $normalizedTitleZh = $this->normalizeHomepageSectionTitle(
                (string) ($section['title_zh'] ?? ''),
                $normalizedSectionType
            );

            $item = [
                'id' => (int) ($section['id'] ?? 0),
                'section_key' => $normalizedSectionKey,
                'section_type' => $normalizedSectionType,
                'source_section_key' => (string) ($section['section_key'] ?? ''),
                'title_zh' => $normalizedTitleZh,
                'subtitle_zh' => (string) ($section['subtitle_zh'] ?? ''),
                'title' => trim((string) ($translation['title'] ?? '')) !== '' ? (string) $translation['title'] : $normalizedTitleZh,
                'subtitle' => trim((string) ($translation['subtitle'] ?? '')) !== '' ? (string) $translation['subtitle'] : (string) ($section['subtitle_zh'] ?? ''),
                'content' => (string) ($translation['content'] ?? ''),
                'fetch_mode' => (string) ($section['fetch_mode'] ?? ''),
                'extra_config' => $extraConfig,
                'sort' => (int) ($section['sort'] ?? 0),
                'language_code' => $languageCode,
            ];

            $limit = max(1, (int) (($item['extra_config']['limit'] ?? 12)));
            $item['items'] = match ($item['section_type']) {
                'product_list' => ((string) ($item['fetch_mode'] ?? '') === 'manual_pick')
                    ? array_slice($this->manualHomepageItems((int) ($item['id'] ?? 0), $languageCode, $sectionItems), 0, $limit)
                    : array_slice(array_map(
                    fn (array $record): array => $this->localizeProduct($record, $languageCode),
                    $featuredProducts
                ), 0, $limit),
                'solution_list' => ((string) ($item['fetch_mode'] ?? '') === 'manual_pick')
                    ? array_slice($this->manualHomepageItems((int) ($item['id'] ?? 0), $languageCode, $sectionItems), 0, $limit)
                    : array_slice(array_map(
                    fn (array $record): array => $this->localizeSolution($record, $languageCode),
                    $featuredSolutions
                ), 0, $limit),
                'news_list' => ((string) ($item['fetch_mode'] ?? '') === 'manual_pick')
                    ? array_slice($this->manualHomepageItems((int) ($item['id'] ?? 0), $languageCode, $sectionItems), 0, $limit)
                    : array_slice(array_map(
                    fn (array $record): array => $this->localizeNews($record, $languageCode),
                    $featuredNews
                ), 0, $limit),
                'case_list' => ((string) ($item['fetch_mode'] ?? '') === 'manual_pick')
                    ? array_slice($this->manualHomepageItems((int) ($item['id'] ?? 0), $languageCode, $sectionItems), 0, $limit)
                    : array_slice(array_map(
                    fn (array $record): array => $this->localizeCase($record, $languageCode),
                    $featuredCases
                ), 0, $limit),
                default => ((string) ($item['fetch_mode'] ?? '') === 'manual_pick')
                    ? array_slice($this->manualHomepageItems((int) ($item['id'] ?? 0), $languageCode, $sectionItems), 0, $limit)
                    : [],
            };

            $normalized[] = $item;
        }

        return [
            'sections' => $normalized,
            'publish_meta' => $this->homepageRepository->publishMeta(),
        ];
    }

    private function normalizeHomepageSectionType(string $sectionKey, string $sectionType): string
    {
        if (in_array($sectionKey, ['featured_articles', 'company_news'], true)) {
            return 'news_list';
        }

        if (in_array($sectionKey, ['featured_cases', 'customer_cases'], true)) {
            return 'case_list';
        }

        return $sectionType !== '' ? $sectionType : 'fixed_config';
    }

    private function normalizeHomepageSectionKey(string $sectionKey, string $sectionType): string
    {
        if ($sectionType === 'news_list') {
            return 'company_news';
        }

        if ($sectionType === 'case_list') {
            return 'customer_cases';
        }

        return $sectionKey;
    }

    private function normalizeHomepageSectionTitle(string $titleZh, string $sectionType): string
    {
        $trimmedTitle = trim($titleZh);

        if ($trimmedTitle !== '' && $trimmedTitle !== '新闻与案例') {
            return $trimmedTitle;
        }

        return match ($sectionType) {
            'news_list' => '企业新闻',
            'case_list' => '客户案例',
            default => $trimmedTitle,
        };
    }

    public function about(string $languageCode = 'zh'): array
    {
        $page = null;
        foreach ($this->aboutRepository->pages() as $candidate) {
            if ((int) ($candidate['is_enabled'] ?? 0) === 1) {
                $page = $candidate;
                break;
            }
        }

        if ($page === null) {
            $page = [
                'id' => 0,
                'page_key' => 'company-about',
                'name_zh' => '公司介绍',
                'is_enabled' => 1,
                'blocks' => [],
            ];
        }

        $teamMembers = array_map(
            fn (array $record): array => $this->localizeTeamMember($record, $languageCode),
            $this->publishedTeamMembers()
        );
        usort($teamMembers, function (array $left, array $right): int {
            $leftHasAvatar = trim((string) ($left['avatar_asset_url'] ?? '')) !== '' ? 1 : 0;
            $rightHasAvatar = trim((string) ($right['avatar_asset_url'] ?? '')) !== '' ? 1 : 0;
            if ($leftHasAvatar !== $rightHasAvatar) {
                return $rightHasAvatar <=> $leftHasAvatar;
            }

            $sortCompare = (int) ($right['manual_sort'] ?? 0) <=> (int) ($left['manual_sort'] ?? 0);
            if ($sortCompare !== 0) {
                return $sortCompare;
            }

            return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
        });
        $certificates = array_map(
            fn (array $record): array => $this->localizeCertificate($record, $languageCode),
            $this->publishedCertificates()
        );

        $blocks = [];
        foreach ($page['blocks'] ?? [] as $block) {
            if ((int) ($block['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $translation = $this->translationRow(
                'about_block_translations',
                'block_id',
                (int) ($block['id'] ?? 0),
                $languageCode,
                ['title', 'subtitle', 'content']
            );
            $item = [
                'id' => (int) ($block['id'] ?? 0),
                'block_type' => (string) ($block['block_type'] ?? 'text'),
                'title_zh' => (string) ($block['title_zh'] ?? ''),
                'subtitle_zh' => (string) ($block['subtitle_zh'] ?? ''),
                'content_zh' => (string) ($block['content_zh'] ?? ''),
                'title' => trim((string) ($translation['title'] ?? '')) !== '' ? (string) $translation['title'] : (string) ($block['title_zh'] ?? ''),
                'subtitle' => trim((string) ($translation['subtitle'] ?? '')) !== '' ? (string) $translation['subtitle'] : (string) ($block['subtitle_zh'] ?? ''),
                'content' => trim((string) ($translation['content'] ?? '')) !== '' ? (string) $translation['content'] : (string) ($block['content_zh'] ?? ''),
                'extra_config' => $this->decodeJsonField($block['extra_config'] ?? []),
                'sort' => (int) ($block['sort'] ?? 0),
                'items' => [],
                'language_code' => $languageCode,
            ];

            if (in_array($item['block_type'], ['team', 'team_list'], true)) {
                $item['items'] = $teamMembers;
            } elseif (in_array($item['block_type'], ['certificate', 'certificate_list'], true)) {
                $item['items'] = $certificates;
            }

            $blocks[] = $item;
        }

        $hasTeamBlock = count(array_filter(
            $blocks,
            static fn (array $item): bool => in_array((string) ($item['block_type'] ?? ''), ['team', 'team_list'], true)
        )) > 0;
        $hasCertificateBlock = count(array_filter(
            $blocks,
            static fn (array $item): bool => in_array((string) ($item['block_type'] ?? ''), ['certificate', 'certificate_list'], true)
        )) > 0;

        if (!$hasTeamBlock && $teamMembers !== []) {
            $blocks[] = [
                'id' => 0,
                'block_type' => 'team_list',
                'title_zh' => '销售团队',
                'subtitle_zh' => '可复用团队成员实体',
                'content_zh' => '',
                'title' => $languageCode === 'en' ? 'Sales Team' : '销售团队',
                'subtitle' => $languageCode === 'en' ? 'Reusable team member profiles' : '可复用团队成员实体',
                'content' => '',
                'extra_config' => ['source' => 'team_members'],
                'sort' => 99,
                'items' => $teamMembers,
                'language_code' => $languageCode,
            ];
        }

        if (!$hasCertificateBlock && $certificates !== []) {
            $blocks[] = [
                'id' => 0,
                'block_type' => 'certificate_list',
                'title_zh' => '资质证书',
                'subtitle_zh' => '企业资质与项目准入证明',
                'content_zh' => '',
                'title' => $languageCode === 'en' ? 'Qualifications' : '资质证书',
                'subtitle' => $languageCode === 'en' ? 'Certificates and market access proof' : '企业资质与项目准入证明',
                'content' => '',
                'extra_config' => ['source' => 'certificates'],
                'sort' => 98,
                'items' => $certificates,
                'language_code' => $languageCode,
            ];
        }

        usort(
            $blocks,
            static fn (array $left, array $right): int => ((int) ($right['sort'] ?? 0) <=> (int) ($left['sort'] ?? 0))
        );

        $pageTranslation = $this->translationRow(
            'about_page_translations',
            'about_page_id',
            (int) ($page['id'] ?? 0),
            $languageCode,
            ['name']
        );

        return [
            'page' => [
                'id' => (int) ($page['id'] ?? 0),
                'page_key' => (string) ($page['page_key'] ?? ''),
                'name_zh' => (string) ($page['name_zh'] ?? ''),
                'name' => trim((string) ($pageTranslation['name'] ?? '')) !== '' ? (string) $pageTranslation['name'] : (string) ($page['name_zh'] ?? ''),
                'is_enabled' => (int) ($page['is_enabled'] ?? 0),
                'language_code' => $languageCode,
            ],
            'blocks' => $blocks,
        ];
    }

    public function contact(string $languageCode = 'zh'): array
    {
        $fieldTypes = array_values(array_filter(
            $this->contactRepository->listFieldTypes(),
            static fn (array $item): bool => (int) ($item['is_enabled'] ?? 0) === 1
        ));
        $items = array_values(array_filter(
            $this->contactRepository->list(),
            static fn (array $item): bool => (int) ($item['is_enabled'] ?? 0) === 1
        ));

        foreach ($fieldTypes as &$fieldType) {
            $translation = $this->translationRow(
                'contact_field_type_translations',
                'field_type_id',
                (int) ($fieldType['id'] ?? 0),
                $languageCode,
                ['name']
            );
            $fieldType['name'] = trim((string) ($translation['name'] ?? '')) !== '' ? (string) $translation['name'] : (string) ($fieldType['name_zh'] ?? '');
            $fieldType['language_code'] = $languageCode;
        }

        foreach ($items as &$item) {
            $fieldTranslation = $this->translationRow(
                'contact_field_type_translations',
                'field_type_id',
                (int) ($item['field_type_id'] ?? 0),
                $languageCode,
                ['name']
            );
            $itemTranslation = $this->translationRow(
                'contact_item_translations',
                'contact_item_id',
                (int) ($item['id'] ?? 0),
                $languageCode,
                ['label', 'description', 'value']
            );
            $fieldKey = strtolower(trim((string) ($item['field_key'] ?? '')));
            $item['field_name'] = trim((string) ($fieldTranslation['name'] ?? '')) !== ''
                ? (string) $fieldTranslation['name']
                : $this->defaultContactFieldName($fieldKey, $languageCode, (string) ($item['field_name'] ?? ''));
            $item['label'] = trim((string) ($itemTranslation['label'] ?? '')) !== ''
                ? (string) $itemTranslation['label']
                : $this->defaultContactLabel($fieldKey, $languageCode, (string) ($item['label_zh'] ?? ''));
            $item['description'] = trim((string) ($itemTranslation['description'] ?? '')) !== ''
                ? (string) $itemTranslation['description']
                : $this->defaultContactDescription($fieldKey, $languageCode, (string) ($item['description_zh'] ?? ''));
            // 地址等联系值按语言翻译：翻译表有 value 就用，否则保留原值（zh 用数据库原值）。
            $translatedValue = trim((string) ($itemTranslation['value'] ?? ''));
            if ($translatedValue !== '') {
                $item['value'] = $translatedValue;
                $item['field_value'] = $translatedValue;
            }
            $item['language_code'] = $languageCode;
        }

        return [
            'field_types' => $fieldTypes,
            'items' => $items,
        ];
    }

    private function defaultContactFieldName(string $fieldKey, string $languageCode, string $fallback): string
    {
        // zh 语言优先使用 fallback（数据库中文值）
        if ($languageCode === 'zh' && trim($fallback) !== '') {
            return $fallback;
        }
        // 非 zh 语言返回空，让 StaticPublisher::normalizeContactEntry 的 phrase() 系统接管
        // phrase('contact_email', ...) 等已有 25 种语言翻译
        return '';
    }

    private function defaultContactLabel(string $fieldKey, string $languageCode, string $fallback): string
    {
        // zh 语言优先使用 fallback（数据库中文值）
        if ($languageCode === 'zh' && trim($fallback) !== '') {
            return $fallback;
        }
        // 非 zh 语言返回空，让 StaticPublisher::normalizeContactEntry 的 phrase() 系统接管
        return '';
    }

    private function defaultContactDescription(string $fieldKey, string $languageCode, string $fallback): string
    {
        // zh 语言优先使用 fallback（数据库中文值）
        if ($languageCode === 'zh' && trim($fallback) !== '') {
            return $fallback;
        }
        // 非 zh 语言返回空

        // 非 zh 语言返回空（让 contact_item_translations 表的翻译接管，无翻译则显示空）
        return '';
    }

    public function ads(string $languageCode = 'zh', string $pageScope = ''): array
    {
        $items = $this->adService->publicList($pageScope);
        $items = array_map(fn (array $item): array => $this->localizeAd($item, $languageCode), $items);

        return [
            'items' => $items,
            'language_code' => $languageCode,
        ];
    }

    /**
     * 按语言本地化广告条目。
     * 广告系统本身没有 translations 表，这里只翻译 linked_page_title（查询 page_translations）。
     * 广告自身的 title 保持原值——广告多语言需通过后台为每个广告配置多语言内容来实现。
     */
    private function localizeAd(array $ad, string $languageCode): array
    {
        if ($languageCode === '' || $languageCode === 'zh') {
            return $ad;
        }

        // linked_page_title: 查询 page_translations
        $linkedPageId = (int) ($ad['linked_page_id'] ?? 0);
        if ($linkedPageId > 0) {
            $translated = $this->translationRow('page_translations', 'page_id', $linkedPageId, $languageCode, ['title']);
            if (is_array($translated) && !empty($translated['title'])) {
                $ad['linked_page_title'] = $translated['title'];
            }
        }

        return $ad;
    }

    public function products(string $languageCode = 'zh', int $page = 1, int $perPage = 12, string $ids = ''): array
    {
        $allItems = $this->publishedProducts();

        if ($ids !== '') {
            $idList = array_map('intval', array_filter(explode(',', $ids), fn(string $v): bool => is_numeric(trim($v))));
            $idSet = array_flip($idList);
            $allItems = array_values(array_filter($allItems, fn(array $item): bool => isset($idSet[(int) ($item['id'] ?? 0)])));
        }

        $items = array_map(
            fn (array $record): array => $this->localizeProduct($record, $languageCode),
            $allItems
        );

        $result = $this->paginateItems($items, $page, $perPage);

        if ($ids !== '') {
            $result['data'] = $result['items'];
            unset($result['items']);
        }

        return $result + [
            'categories' => $this->localizeCategoryTree(
                $this->productRepository->categoryTree(),
                $languageCode,
                'product_category_translations',
                'category_id'
            ),
        ];
    }

    public function productDetail(string $slug, string $languageCode = 'zh'): array
    {
        $detail = $this->findPublishedEntityBySlug(
            $slug,
            $this->publishedProducts(),
            $this->repositoryItems($this->productRepository->list()),
            'product',
            fn (int $id): ?array => $this->productRepository->find($id)
        );
        if ($detail !== null) {
            return $this->localizeProduct($detail, $languageCode);
        }

        throw new BusinessException('产品不存在', ErrorCode::NOT_FOUND);
    }

    public function solutions(string $languageCode = 'zh', int $page = 1, int $perPage = 12, string $ids = ''): array
    {
        $allItems = $this->publishedSolutions();

        if ($ids !== '') {
            $idList = array_map('intval', array_filter(explode(',', $ids), fn(string $v): bool => is_numeric(trim($v))));
            $idSet = array_flip($idList);
            $allItems = array_values(array_filter($allItems, fn(array $item): bool => isset($idSet[(int) ($item['id'] ?? 0)])));
        }

        $items = array_map(
            fn (array $record): array => $this->localizeSolution($record, $languageCode),
            $allItems
        );

        $result = $this->paginateItems($items, $page, $perPage);

        if ($ids !== '') {
            $result['data'] = $result['items'];
            unset($result['items']);
        }

        return $result + [
            'categories' => $this->localizeCategoryTree(
                $this->solutionRepository->categoryTree(),
                $languageCode,
                'solution_category_translations',
                'category_id'
            ),
        ];
    }

    public function solutionDetail(string $slug, string $languageCode = 'zh'): array
    {
        $detail = $this->findPublishedEntityBySlug(
            $slug,
            $this->publishedSolutions(),
            $this->repositoryItems($this->solutionRepository->list()),
            'solution',
            fn (int $id): ?array => $this->solutionRepository->find($id)
        );
        if ($detail !== null) {
            return $this->localizeSolution($detail, $languageCode);
        }

        throw new BusinessException('解决方案不存在', ErrorCode::NOT_FOUND);
    }

    public function articles(?string $contentType = null, string $languageCode = 'zh', int $page = 1, int $perPage = 12): array
    {
        $items = $this->publishedArticles();
        if ($contentType !== null && $contentType !== '') {
            $items = array_values(array_filter(
                $items,
                static fn (array $item): bool => (string) ($item['content_type'] ?? '') === $contentType
            ));
        }

        $items = array_map(
            fn (array $record): array => $this->localizeArticle($record, $languageCode),
            $items
        );

        return $this->paginateItems($items, $page, $perPage) + [
            'categories' => $this->localizeCategoryTree(
                $this->articleRepository->categoryTree(),
                $languageCode,
                'article_category_translations',
                'category_id'
            ),
        ];
    }

    public function articleDetail(string $slug, string $languageCode = 'zh'): array
    {
        $detail = $this->findPublishedEntityBySlug(
            $slug,
            $this->publishedArticles(),
            $this->repositoryItems($this->articleRepository->list()),
            'article',
            fn (int $id): ?array => $this->articleRepository->find($id)
        );
        if ($detail !== null) {
            if (!isset($detail['related_solution_ids']) || !is_array($detail['related_solution_ids'])) {
                $detail['related_solution_ids'] = $this->decodeJsonField($detail['related_solution_ids'] ?? []);
            }
            if (!isset($detail['related_product_ids']) || !is_array($detail['related_product_ids'])) {
                $detail['related_product_ids'] = $this->decodeJsonField($detail['related_product_ids'] ?? []);
            }

            return $this->localizeArticle($detail, $languageCode);
        }

        throw new BusinessException('文章不存在', ErrorCode::NOT_FOUND);
    }

    public function newsList(string $languageCode = 'zh', int $page = 1, int $perPage = 12): array
    {
        $items = $this->publishedNews();

        $items = array_map(
            fn (array $record): array => $this->localizeNews($record, $languageCode),
            $items
        );

        return $this->paginateItems($items, $page, $perPage) + [
            'categories' => $this->localizeCategoryTree(
                $this->filterArticleCategoriesByScope($this->articleRepository->categoryTree(), 'news'),
                $languageCode,
                'article_category_translations',
                'category_id'
            ),
        ];
    }

    public function newsDetail(string $slug, string $languageCode = 'zh'): array
    {
        $detail = $this->findPublishedEntityBySlug(
            $slug,
            $this->resolvePublishedContent(
                'news',
                $this->repositoryItems($this->newsRepository->list()),
                fn (int $id): ?array => $this->newsRepository->find($id)
            ),
            $this->repositoryItems($this->newsRepository->list()),
            'news',
            fn (int $id): ?array => $this->newsRepository->find($id)
        );
        if ($detail !== null) {
            return $this->localizeNews($detail, $languageCode);
        }

        return $this->articleDetail($slug, $languageCode);
    }

    public function caseList(string $languageCode = 'zh', int $page = 1, int $perPage = 12): array
    {
        $items = $this->publishedCases();

        $items = array_map(
            fn (array $record): array => $this->localizeCase($record, $languageCode),
            $items
        );

        return $this->paginateItems($items, $page, $perPage) + [
            'categories' => $this->localizeCategoryTree(
                $this->filterArticleCategoriesByScope($this->articleRepository->categoryTree(), 'case'),
                $languageCode,
                'article_category_translations',
                'category_id'
            ),
        ];
    }

    public function caseDetail(string $slug, string $languageCode = 'zh'): array
    {
        $detail = $this->findPublishedEntityBySlug(
            $slug,
            $this->resolvePublishedContent(
                'case',
                $this->repositoryItems($this->caseRepository->list()),
                fn (int $id): ?array => $this->caseRepository->find($id)
            ),
            $this->repositoryItems($this->caseRepository->list()),
            'case',
            fn (int $id): ?array => $this->caseRepository->find($id)
        );
        if ($detail !== null) {
            return $this->localizeCase($detail, $languageCode);
        }

        return $this->articleDetail($slug, $languageCode);
    }

    public function pageDetail(string $slug, string $languageCode = 'zh'): array
    {
        $detail = $this->findPublishedEntityBySlug(
            $slug,
            $this->publishedPages(),
            $this->repositoryItems($this->pageRepository->list()),
            'page',
            fn (int $id): ?array => $this->pageRepository->find($id)
        );
        if ($detail !== null) {
            return $this->localizePage($detail, $languageCode);
        }

        throw new BusinessException('单页不存在', ErrorCode::NOT_FOUND);
    }

    public function pages(string $languageCode = 'zh', int $page = 1, int $perPage = 1000, string $pageType = ''): array
    {
        $items = $this->publishedPages();
        if ($pageType !== '') {
            $items = array_values(array_filter(
                $items,
                static fn (array $item): bool => (string) ($item['page_type'] ?? '') === $pageType
            ));
        }

        $items = array_values(array_filter(
            array_map(
                fn (array $record): array => $this->localizePage($record, $languageCode),
                $items
            ),
            static fn (array $item): bool => trim((string) ($item['slug'] ?? '')) !== ''
        ));

        return $this->paginateItems($items, $page, $perPage);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publishedProducts(): array
    {
        return $this->rememberPublishedContent('product:all', function (): array {
            $items = $this->resolvePublishedContent(
                'product',
                $this->repositoryListItems('product:list', fn (): array => $this->productRepository->list()),
                fn (int $id): ?array => $this->rememberDetail('product:id:' . $id, fn (): ?array => $this->productRepository->find($id))
            );

            usort($items, [$this, 'compareManualSortRecords']);

            return $items;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publishedSolutions(): array
    {
        return $this->rememberPublishedContent('solution:all', function (): array {
            $items = $this->resolvePublishedContent(
                'solution',
                $this->repositoryListItems('solution:list', fn (): array => $this->solutionRepository->list()),
                fn (int $id): ?array => $this->rememberDetail('solution:id:' . $id, fn (): ?array => $this->solutionRepository->find($id))
            );

            usort($items, [$this, 'compareManualSortRecords']);

            return $items;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publishedArticles(): array
    {
        return $this->rememberPublishedContent('article:all', function (): array {
            $items = $this->resolvePublishedContent(
                'article',
                $this->repositoryListItems('article:list', fn (): array => $this->articleRepository->list()),
                fn (int $id): ?array => $this->rememberDetail('article:id:' . $id, fn (): ?array => $this->articleRepository->find($id))
            );

            foreach ($items as $index => $detail) {
                $items[$index]['related_solution_ids'] = $this->decodeJsonField($detail['related_solution_ids'] ?? []);
                $items[$index]['related_product_ids'] = $this->decodeJsonField($detail['related_product_ids'] ?? []);
            }

            usort($items, [$this, 'compareManualSortRecords']);

            return $items;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publishedNews(): array
    {
        return $this->rememberPublishedContent('news:all', function (): array {
            $items = $this->resolvePublishedContent(
                'news',
                $this->repositoryListItems('news:list', fn (): array => $this->newsRepository->list()),
                fn (int $id): ?array => $this->rememberDetail('news:id:' . $id, fn (): ?array => $this->newsRepository->find($id))
            );

            usort($items, [$this, 'compareManualSortRecords']);

            return $items;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publishedCases(): array
    {
        return $this->rememberPublishedContent('case:all', function (): array {
            $items = $this->resolvePublishedContent(
                'case',
                $this->repositoryListItems('case:list', fn (): array => $this->caseRepository->list()),
                fn (int $id): ?array => $this->rememberDetail('case:id:' . $id, fn (): ?array => $this->caseRepository->find($id))
            );

            usort($items, [$this, 'compareManualSortRecords']);

            return $items;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publishedPages(): array
    {
        return $this->rememberPublishedContent('page:all', function (): array {
            $items = $this->resolvePublishedContent(
                'page',
                $this->repositoryListItems('page:list', fn (): array => $this->pageRepository->list()),
                fn (int $id): ?array => $this->rememberDetail('page:id:' . $id, fn (): ?array => $this->pageRepository->find($id))
            );

            usort($items, [$this, 'comparePublishTimeRecords']);

            return $items;
        });
    }

    /**
     * @param array<string, mixed>|array<int, array<string, mixed>> $payload
     * @return array<int, array<string, mixed>>
     */
    private function repositoryItems(array $payload): array
    {
        if (isset($payload['items']) && is_array($payload['items'])) {
            return array_values(array_filter($payload['items'], static fn (mixed $item): bool => is_array($item)));
        }

        return array_values(array_filter($payload, static fn (mixed $item): bool => is_array($item)));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param callable(int): ?array $findRecord
     * @return array<int, array<string, mixed>>
     */
    private function resolvePublishedContent(string $entityType, array $rows, callable $findRecord): array
    {
        $items = [];
        foreach ($rows as $row) {
            if ((string) ($row['publish_status'] ?? '') !== 'published') {
                continue;
            }

            $entityId = (int) ($row['id'] ?? 0);
            if ($entityId <= 0) {
                continue;
            }

            $detail = $this->contentWorkflowService->liveRecord($entityType, $entityId);
            if ($detail === null) {
                $detail = $findRecord($entityId);
            }

            if ($detail === null) {
                continue;
            }

            $items[] = $detail;
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     *
     * @return array<string, mixed>
     */
    private function paginateItems(array $items, int $page, int $perPage): array
    {
        $total = count($items);
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_slice($items, $offset, $perPage),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    private function compareManualSortRecords(array $left, array $right): int
    {
        $sortCompare = (int) ($right['manual_sort'] ?? 0) <=> (int) ($left['manual_sort'] ?? 0);
        if ($sortCompare !== 0) {
            return $sortCompare;
        }

        $timeCompare = strcmp((string) ($right['publish_time'] ?? ''), (string) ($left['publish_time'] ?? ''));
        if ($timeCompare !== 0) {
            return $timeCompare;
        }

        return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
    }

    private function comparePublishTimeRecords(array $left, array $right): int
    {
        $timeCompare = strcmp((string) ($right['publish_time'] ?? ''), (string) ($left['publish_time'] ?? ''));
        if ($timeCompare !== 0) {
            return $timeCompare;
        }

        return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publishedTeamMembers(): array
    {
        return array_values(array_filter(
            $this->teamRepository->list(),
            fn (array $item): bool => (string) ($item['publish_status'] ?? '') === 'published'
                && $this->isRenderableTeamMember($item)
        ));
    }

    private function isRenderableTeamMember(array $item): bool
    {
        $name = trim((string) ($item['name_zh'] ?? ''));
        if ($name === '') {
            return false;
        }

        $email = strtolower(trim((string) ($item['email'] ?? '')));
        if ($email !== '' && str_contains($email, '@example.com')) {
            return false;
        }

        $placeholderDigits = ['10000000000', '8610000000000'];
        $phoneDigits = preg_replace('/\D+/', '', (string) ($item['phone'] ?? '')) ?? '';
        $whatsappDigits = preg_replace('/\D+/', '', (string) ($item['whatsapp'] ?? '')) ?? '';

        if (in_array($phoneDigits, $placeholderDigits, true) || in_array($whatsappDigits, $placeholderDigits, true)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publishedCertificates(): array
    {
        return array_values(array_filter(
            $this->certificateRepository->list(),
            static fn (array $item): bool => (string) ($item['publish_status'] ?? '') === 'published'
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function buildNavigationTree(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $item['children'] = [];
            $indexed[(int) ($item['id'] ?? 0)] = $item;
        }

        $tree = [];
        foreach ($indexed as $id => $item) {
            $parentId = (int) ($item['parent_id'] ?? 0);
            if ($parentId > 0 && isset($indexed[$parentId])) {
                $indexed[$parentId]['children'][] = &$indexed[$id];
                continue;
            }

            $tree[] = &$indexed[$id];
        }

        return array_values($tree);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function localizeNavigationTree(array $items, string $languageCode): array
    {
        foreach ($items as $index => $item) {
            $translation = $this->translationRow(
                'navigation_item_translations',
                'item_id',
                (int) ($item['id'] ?? 0),
                $languageCode,
                ['name']
            );
            $items[$index]['name'] = trim((string) ($translation['name'] ?? '')) !== '' ? (string) $translation['name'] : (string) ($item['name_zh'] ?? '');
            $items[$index]['language_code'] = $languageCode;
            if (is_array($item['children'] ?? null)) {
                $items[$index]['children'] = $this->localizeNavigationTree($item['children'], $languageCode);
            }
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function localizeCategoryTree(
        array $items,
        string $languageCode,
        string $table,
        string $entityKey
    ): array
    {
        foreach ($items as $index => $item) {
            $translation = $this->translationRow(
                $table,
                $entityKey,
                (int) ($item['id'] ?? 0),
                $languageCode,
                ['name']
            );
            $items[$index]['name'] = trim((string) ($translation['name'] ?? '')) !== '' ? (string) $translation['name'] : (string) ($item['name_zh'] ?? '');
            $items[$index]['language_code'] = $languageCode;
            if (is_array($item['children'] ?? null)) {
                $items[$index]['children'] = $this->localizeCategoryTree(
                    $item['children'],
                    $languageCode,
                    $table,
                    $entityKey
                );
            }
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    private function filterArticleCategoriesByScope(array $categories, string $targetScope): array
    {
        $targetScope = strtolower(trim($targetScope));
        $filtered = [];

        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }

            $children = is_array($category['children'] ?? null)
                ? $this->filterArticleCategoriesByScope($category['children'], $targetScope)
                : [];

            $scope = strtolower(trim((string) ($category['content_type_scope'] ?? 'all')));
            $scopeMatches = in_array($scope, ['all', $targetScope], true);

            if (!$scopeMatches && $children === []) {
                continue;
            }

            $category['children'] = $children;
            $filtered[] = $category;
        }

        return array_values($filtered);
    }

    private function localizeProduct(array $record, string $languageCode): array
    {
        $record = $this->applyLocalizedFields(
            $record,
            $this->translationRow('product_translations', 'product_id', (int) ($record['id'] ?? 0), $languageCode, ['name', 'summary', 'content']),
            [
                'name' => ['source' => 'name_zh', 'translation' => 'name'],
                'summary' => ['source' => 'summary_zh', 'translation' => 'summary'],
                'content' => ['source' => 'content_zh', 'translation' => 'content'],
            ],
            $languageCode
        );

        $record = $this->applySeoRoute($record, 'product', $languageCode);

        return $this->attachContentCover($record, 'product');
    }

    private function localizeSolution(array $record, string $languageCode): array
    {
        $record = $this->applyLocalizedFields(
            $record,
            $this->translationRow(
                'solution_translations',
                'solution_id',
                (int) ($record['id'] ?? 0),
                $languageCode,
                ['name', 'summary', 'content', 'flow_text', 'capacity_text']
            ),
            [
                'name' => ['source' => 'name_zh', 'translation' => 'name'],
                'summary' => ['source' => 'summary_zh', 'translation' => 'summary'],
                'content' => ['source' => 'content_zh', 'translation' => 'content'],
                'flow_text' => ['source' => 'flow_text_zh', 'translation' => 'flow_text'],
                'capacity_text' => ['source' => 'capacity_text_zh', 'translation' => 'capacity_text'],
            ],
            $languageCode
        );

        $record = $this->applySeoRoute($record, 'solution', $languageCode);

        return $this->attachContentCover($record, 'solution');
    }

    private function localizeArticle(array $record, string $languageCode): array
    {
        $record = $this->applyLocalizedFields(
            $record,
            $this->translationRow('article_translations', 'article_id', (int) ($record['id'] ?? 0), $languageCode, ['title', 'summary', 'content']),
            [
                'title' => ['source' => 'title_zh', 'translation' => 'title'],
                'summary' => ['source' => 'summary_zh', 'translation' => 'summary'],
                'content' => ['source' => 'content_zh', 'translation' => 'content'],
            ],
            $languageCode
        );

        $record = $this->applySeoRoute($record, 'article', $languageCode);

        return $this->attachContentCover($record, 'article');
    }

    private function localizeNews(array $record, string $languageCode): array
    {
        $record = $this->applyLocalizedFields(
            $record,
            $this->translationRow('news_translations', 'news_id', (int) ($record['id'] ?? 0), $languageCode, ['title', 'summary', 'content']),
            [
                'title' => ['source' => 'title_zh', 'translation' => 'title'],
                'summary' => ['source' => 'summary_zh', 'translation' => 'summary'],
                'content' => ['source' => 'content_zh', 'translation' => 'content'],
            ],
            $languageCode
        );

        $record = $this->applySeoRoute($record, 'news', $languageCode);

        return $this->attachContentCover($record, 'news');
    }

    private function localizeCase(array $record, string $languageCode): array
    {
        $record = $this->applyLocalizedFields(
            $record,
            $this->translationRow('case_translations', 'case_id', (int) ($record['id'] ?? 0), $languageCode, ['title', 'summary', 'content']),
            [
                'title' => ['source' => 'title_zh', 'translation' => 'title'],
                'summary' => ['source' => 'summary_zh', 'translation' => 'summary'],
                'content' => ['source' => 'content_zh', 'translation' => 'content'],
            ],
            $languageCode
        );

        $record = $this->applySeoRoute($record, 'case', $languageCode);
        $record['related_solution_ids'] = $this->decodeJsonField($record['related_solution_ids'] ?? []);
        $record['related_product_ids'] = $this->decodeJsonField($record['related_product_ids'] ?? []);

        return $this->attachContentCover($record, 'case');
    }

    private function localizePage(array $record, string $languageCode): array
    {
        $record = $this->applyLocalizedFields(
            $record,
            $this->translationRow('page_translations', 'page_id', (int) ($record['id'] ?? 0), $languageCode, ['title', 'summary', 'content']),
            [
                'title' => ['source' => 'title_zh', 'translation' => 'title'],
                'summary' => ['source' => 'summary_zh', 'translation' => 'summary'],
                'content' => ['source' => 'content_zh', 'translation' => 'content'],
            ],
            $languageCode
        );

        return $this->applySeoRoute($record, 'page', $languageCode);
    }

    private function localizeTeamMember(array $record, string $languageCode): array
    {
        $record = $this->applyLocalizedFields(
            $record,
            $this->translationRow('team_member_translations', 'team_member_id', (int) ($record['id'] ?? 0), $languageCode, ['name', 'title', 'department', 'bio']),
            [
                'name' => ['source' => 'name_zh', 'translation' => 'name'],
                'title' => ['source' => 'title_zh', 'translation' => 'title'],
                'department' => ['source' => 'department_zh', 'translation' => 'department'],
                'bio' => ['source' => 'bio_zh', 'translation' => 'bio'],
            ],
            $languageCode
        );

        return $this->attachMediaAsset($record, 'avatar_asset_id', 'avatar_asset');
    }

    private function localizeCertificate(array $record, string $languageCode): array
    {
        $record = $this->applyLocalizedFields(
            $record,
            $this->translationRow('certificate_translations', 'certificate_id', (int) ($record['id'] ?? 0), $languageCode, ['name', 'issuer', 'description']),
            [
                'name' => ['source' => 'name_zh', 'translation' => 'name'],
                'issuer' => ['source' => 'issuer_zh', 'translation' => 'issuer'],
                'description' => ['source' => 'description_zh', 'translation' => 'description'],
            ],
            $languageCode
        );

        return $this->attachMediaAsset($record, 'image_asset_id', 'image_asset');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manualHomepageItems(int $sectionId, string $languageCode, array $snapshotItems = []): array
    {
        $items = [];
        $rawItems = $snapshotItems !== []
            ? array_values(array_filter($snapshotItems, static fn (array $item): bool => (int) ($item['section_id'] ?? 0) === $sectionId))
            : $this->homepageRepository->listItems($sectionId);
        foreach ($rawItems as $item) {
            if ((int) ($item['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $sourceType = (string) ($item['source_type'] ?? '');
            $sourceRecord = $this->contentEntityBridgeRecord($sourceType, (int) ($item['source_id'] ?? 0));
            if ($sourceRecord === null) {
                continue;
            }
            if (array_key_exists('publish_status', $sourceRecord) && (string) ($sourceRecord['publish_status'] ?? '') !== 'published') {
                continue;
            }

            $effectiveSourceType = $this->normalizeHomepageSourceType($sourceType, $sourceRecord);

            $localized = $this->localizeHomepageSourceItem($effectiveSourceType, $sourceRecord, $languageCode);
            $itemTranslation = $this->translationRow(
                'homepage_section_item_translations',
                'item_id',
                (int) ($item['id'] ?? 0),
                $languageCode,
                ['title', 'summary']
            );

            $titleOverride = trim((string) ($itemTranslation['title'] ?? '')) !== ''
                ? (string) $itemTranslation['title']
                : (string) ($item['title_override_zh'] ?? '');
            $summaryOverride = trim((string) ($itemTranslation['summary'] ?? '')) !== ''
                ? (string) $itemTranslation['summary']
                : (string) ($item['summary_override_zh'] ?? '');

            $localized['homepage_item_id'] = (int) ($item['id'] ?? 0);
            $localized['source_type'] = $effectiveSourceType;
            $localized['source_id'] = (int) ($item['source_id'] ?? 0);
            $localized['display_title'] = $titleOverride !== '' ? $titleOverride : $this->defaultHomepageItemTitle($effectiveSourceType, $localized);
            $localized['display_summary'] = $summaryOverride !== '' ? $summaryOverride : $this->defaultHomepageItemSummary($effectiveSourceType, $localized);
            $manualCoverAssetId = (int) ($item['cover_asset_id'] ?? 0);
            if ($manualCoverAssetId > 0) {
                $localized['cover_asset_id'] = $manualCoverAssetId;
                $localized = $this->attachMediaAsset($localized, 'cover_asset_id', 'cover_asset');
                if (isset($localized['cover_asset_url']) && trim((string) $localized['cover_asset_url']) !== '') {
                    $localized['cover_image_url'] = (string) $localized['cover_asset_url'];
                }
            }

            $items[] = $localized;
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $publishedItems
     * @param array<int, array<string, mixed>> $rows
     * @param callable(int): ?array $findRecord
     */
    private function findPublishedEntityBySlug(
        string $slug,
        array $publishedItems,
        array $rows,
        string $entityType,
        callable $findRecord
    ): ?array {
        $targetSlug = trim($slug);
        if ($targetSlug === '') {
            return null;
        }

        foreach ($publishedItems as $item) {
            if ((string) ($item['slug'] ?? '') === $targetSlug) {
                return $item;
            }
        }

        foreach ($rows as $row) {
            if ((string) ($row['publish_status'] ?? '') !== 'published') {
                continue;
            }

            if ((string) ($row['slug'] ?? '') !== $targetSlug) {
                continue;
            }

            $entityId = (int) ($row['id'] ?? 0);
            if ($entityId <= 0) {
                continue;
            }

            $detail = $this->rememberDetail($entityType . ':id:' . $entityId, function () use ($entityType, $entityId, $findRecord): ?array {
                $record = $this->contentWorkflowService->liveRecord($entityType, $entityId);
                if ($record === null) {
                    $record = $findRecord($entityId);
                }

                return $record;
            });

            if ($detail !== null) {
                return $detail;
            }
        }

        return null;
    }

    private function normalizeHomepageSourceType(string $sourceType, array $record): string
    {
        if ($sourceType === 'article') {
            $contentType = (string) ($record['content_type'] ?? '');
            if ($contentType === 'news' || $contentType === 'case') {
                return $contentType;
            }
        }

        return $sourceType;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed>|null $translation
     * @param array<string, array{source:string,translation:string}> $fields
     * @return array<string, mixed>
     */
    private function applyLocalizedFields(array $record, ?array $translation, array $fields, string $languageCode): array
    {
        foreach ($fields as $target => $config) {
            $sourceValue = (string) ($record[$config['source']] ?? '');
            $translatedValue = trim((string) ($translation[$config['translation']] ?? ''));
            $record[$target] = $translatedValue !== '' ? $translatedValue : $sourceValue;
        }

        $record['language_code'] = $languageCode;

        return $record;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function attachMediaAsset(array $record, string $assetIdField, string $targetField): array
    {
        $assetId = (int) ($record[$assetIdField] ?? 0);
        if ($assetId <= 0) {
            return $record;
        }

        $asset = $this->mediaRepository->find($assetId);
        if ($asset === null || (int) ($asset['status'] ?? 0) !== 1) {
            return $record;
        }

        return $this->attachResolvedMediaAsset($record, $asset, $targetField);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $asset
     * @return array<string, mixed>
     */
    private function attachResolvedMediaAsset(array $record, array $asset, string $targetField): array
    {
        $record[$targetField] = [
            'id' => (int) ($asset['id'] ?? 0),
            'folder_name' => (string) ($asset['folder_name'] ?? ''),
            'file_path' => (string) ($asset['file_path'] ?? ''),
            'file_name' => (string) ($asset['file_name'] ?? ''),
            'mime_type' => (string) ($asset['mime_type'] ?? ''),
            'alt_text' => (string) ($asset['alt_text_zh'] ?? ''),
            'description' => (string) ($asset['description_zh'] ?? ''),
            'width' => isset($asset['width']) ? (int) $asset['width'] : null,
            'height' => isset($asset['height']) ? (int) $asset['height'] : null,
            'public_url' => (string) ($asset['file_path'] ?? ''),
        ];
        $record[$targetField . '_url'] = (string) ($asset['file_path'] ?? '');

        return $record;
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function attachContentCover(array $record, string $contentType): array
    {
        $asset = $this->resolveContentCoverAsset($record, $contentType);
        if ($asset !== null) {
            $record['cover_asset_id'] = (int) ($asset['id'] ?? ($record['cover_asset_id'] ?? 0));
            $record = $this->attachResolvedMediaAsset($record, $asset, 'cover_asset');
            // Prefer thumbnail for card display, fallback to full image
            $thumbUrl = trim((string) ($asset['thumb_url'] ?? ''));
            $record['cover_image_url'] = $thumbUrl !== '' ? $thumbUrl : (string) ($record['cover_asset_url'] ?? '');
            $record['has_cover_image'] = true;

            return $record;
        }

        // Before fallback: try to extract first image from content_zh
        $contentImage = $this->extractFirstContentImage($record);
        if ($contentImage !== '') {
            $record['cover_image_url'] = $contentImage;
            $record['has_cover_image'] = true;

            return $record;
        }

        $fallbackPath = $this->defaultContentCoverPath($contentType, $record);
        if ($fallbackPath !== '') {
            $record['cover_image_url'] = $fallbackPath;
        }
        $record['has_cover_image'] = false;

        return $record;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function resolveContentCoverAsset(array $record, string $contentType): ?array
    {
        $assetIds = [];
        $coverAssetId = (int) ($record['cover_asset_id'] ?? 0);
        if ($coverAssetId > 0) {
            $assetIds[] = $coverAssetId;
        }

        if ($contentType === 'solution') {
            $manualAssetId = (int) ($record['manual_asset_id'] ?? 0);
            if ($manualAssetId > 0) {
                $assetIds[] = $manualAssetId;
            }
        }

        // When no direct cover_asset_id, try media_gallery (admin-panel cover images)
        if (empty($assetIds)) {
            $entityId = (int) ($record['id'] ?? 0);
            if ($entityId > 0) {
                $covers = $this->mediaGalleryRepository->coverMap($contentType, [$entityId]);
                if (isset($covers[$entityId]) && (int) ($covers[$entityId]['asset_id'] ?? 0) > 0) {
                    $assetIds[] = (int) $covers[$entityId]['asset_id'];
                }
            }
        }

        foreach ($assetIds as $assetId) {
            $asset = $this->mediaRepository->find($assetId);
            if ($this->isImageMediaAsset($asset)) {
                return $asset;
            }
        }

        $fallbackPath = $this->defaultContentCoverPath($contentType, $record);
        if ($fallbackPath === '') {
            return null;
        }

        foreach ($this->mediaRepository->list() as $asset) {
            if (!$this->isImageMediaAsset($asset)) {
                continue;
            }

            if ((string) ($asset['file_path'] ?? '') === $fallbackPath) {
                return $asset;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $asset
     */
    private function isImageMediaAsset(?array $asset): bool
    {
        if (!is_array($asset) || (int) ($asset['status'] ?? 0) !== 1) {
            return false;
        }

        return str_starts_with(strtolower((string) ($asset['mime_type'] ?? '')), 'image/');
    }

    /**
     * @param array<string, mixed> $record
     */
    private function defaultContentCoverPath(string $contentType, array $record): string
    {
        // No hardcoded fallback paths — use media_gallery or content_zh images instead.
        // Records without a cover will show a placeholder.
        return '';
    }

    /**
     * Extract the first <img> src from content_zh or content field.
     */
    private function extractFirstContentImage(array $record): string
    {
        $html = (string) ($record['content_zh'] ?? $record['content'] ?? '');
        if ($html === '') {
            return '';
        }

        if (preg_match('/<img[^>]+src="([^"]+)"/i', $html, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match("/<img[^>]+src='([^']+)'/i", $html, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    private function contentEntityBridgeRecord(string $sourceType, int $sourceId): ?array
    {
        return match ($sourceType) {
            'product' => $this->productRepository->find($sourceId),
            'solution' => $this->solutionRepository->find($sourceId),
            'news', 'case' => $this->articleRepository->find($sourceId),
            'article' => $this->articleRepository->find($sourceId),
            'page' => $this->pageRepository->find($sourceId),
            'team_member' => $this->teamRepository->find($sourceId),
            'certificate' => $this->certificateRepository->find($sourceId),
            default => null,
        };
    }

    private function localizeHomepageSourceItem(string $sourceType, array $record, string $languageCode): array
    {
        return match ($sourceType) {
            'product' => $this->localizeProduct($record, $languageCode),
            'solution' => $this->localizeSolution($record, $languageCode),
            'news' => $this->localizeNews($record, $languageCode),
            'case' => $this->localizeCase($record, $languageCode),
            'article' => $this->localizeArticle($record, $languageCode),
            'page' => $this->localizePage($record, $languageCode),
            'team_member' => $this->localizeTeamMember($record, $languageCode),
            'certificate' => $this->localizeCertificate($record, $languageCode),
            default => $record,
        };
    }

    private function defaultHomepageItemTitle(string $sourceType, array $record): string
    {
        return match ($sourceType) {
            'product', 'solution', 'team_member', 'certificate' => (string) ($record['name'] ?? $record['name_zh'] ?? ''),
            'news', 'case', 'page' => (string) ($record['title'] ?? $record['title_zh'] ?? ''),
            default => (string) ($record['name'] ?? $record['title'] ?? ''),
        };
    }

    private function defaultHomepageItemSummary(string $sourceType, array $record): string
    {
        return match ($sourceType) {
            'product', 'solution', 'news', 'case', 'page' => (string) ($record['summary'] ?? $record['summary_zh'] ?? ''),
            'team_member' => (string) ($record['bio'] ?? $record['bio_zh'] ?? ''),
            'certificate' => (string) ($record['description'] ?? $record['description_zh'] ?? ''),
            default => (string) ($record['summary'] ?? $record['description'] ?? $record['bio'] ?? ''),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function translationRow(
        string $table,
        string $entityKey,
        int $entityId,
        string $languageCode,
        array $fields
    ): ?array {
        if ($entityId <= 0 || $languageCode === '' || $languageCode === 'zh') {
            return null;
        }

        $cacheKey = implode(':', [
            $table,
            $entityKey,
            (string) $entityId,
            $languageCode,
            implode(',', $fields),
        ]);
        if (array_key_exists($cacheKey, $this->translationCache)) {
            return $this->translationCache[$cacheKey];
        }

        $pdo = DatabaseManager::instance()->connection();
        if ($pdo instanceof PDO) {
            try {
                $tableRows = $this->translationTableRows($table, $entityKey, $languageCode, $fields);
                $lookupKey = $entityId . ':' . $languageCode;
                if (isset($tableRows[$lookupKey])) {
                    return $this->translationCache[$cacheKey] = $tableRows[$lookupKey];
                }
            } catch (\Throwable) {
            }
        }

        $runtimeRow = $this->runtimeTranslationRow($table, $entityKey, $entityId, $languageCode, $fields);
        return $this->translationCache[$cacheKey] = $runtimeRow;
    }

    /**
     * @param array<int, string> $fields
     * @return array<string, array<string, mixed>>
     */
    private function translationTableRows(string $table, string $entityKey, string $languageCode, array $fields): array
    {
        $cacheKey = $table . ':' . $entityKey . ':' . $languageCode . ':' . implode(',', $fields);
        if (array_key_exists($cacheKey, $this->translationTableCache)) {
            return $this->translationTableCache[$cacheKey];
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!($pdo instanceof PDO)) {
            return $this->translationTableCache[$cacheKey] = [];
        }

        $columns = implode(', ', array_unique(array_merge([$entityKey, 'language_code'], $fields, ['translation_status'])));
        $statement = $pdo->prepare(
            sprintf(
                'SELECT %s FROM %s WHERE language_code = :language_code',
                $columns,
                $table
            )
        );
        $statement->execute([
            'language_code' => $languageCode,
        ]);
        $rows = $statement->fetchAll();
        if (!is_array($rows)) {
            return $this->translationTableCache[$cacheKey] = [];
        }

        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $map[(int) ($row[$entityKey] ?? 0) . ':' . (string) ($row['language_code'] ?? '')] = $row;
        }

        return $this->translationTableCache[$cacheKey] = $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function repositoryListItems(string $cacheKey, callable $loader): array
    {
        if (array_key_exists($cacheKey, $this->repositoryItemsCache)) {
            return $this->repositoryItemsCache[$cacheKey];
        }

        $items = $this->repositoryItems($loader());
        $this->repositoryItemsCache[$cacheKey] = $items;

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rememberPublishedContent(string $cacheKey, callable $loader): array
    {
        if (array_key_exists($cacheKey, $this->publishedContentCache)) {
            return $this->publishedContentCache[$cacheKey];
        }

        $items = $loader();
        $this->publishedContentCache[$cacheKey] = is_array($items) ? $items : [];

        return $this->publishedContentCache[$cacheKey];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rememberDetail(string $cacheKey, callable $loader): ?array
    {
        if (array_key_exists($cacheKey, $this->detailCache)) {
            return $this->detailCache[$cacheKey];
        }

        $detail = $loader();
        $this->detailCache[$cacheKey] = is_array($detail) ? $detail : null;

        return $this->detailCache[$cacheKey];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function runtimeTranslationRow(
        string $table,
        string $entityKey,
        int $entityId,
        string $languageCode,
        array $fields
    ): ?array {
        $path = $this->translationRuntimePath($table);
        if ($path === '' || !is_file($path)) {
            return null;
        }

        if (!array_key_exists($path, $this->runtimeTranslationFileCache)) {
            $raw = (string) file_get_contents($path);
            if (str_starts_with($raw, "\xEF\xBB\xBF")) {
                $raw = substr($raw, 3);
            }
            $decoded = json_decode($raw, true);
            $this->runtimeTranslationFileCache[$path] = is_array($decoded) ? $decoded : [];
        }

        $decoded = $this->runtimeTranslationFileCache[$path];
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

            foreach ($fields as $field) {
                $payload[$field] = (string) ($row[$field] ?? '');
            }

            return $payload;
        }

        return null;
    }

    private function translationRuntimePath(string $table): string
    {
        $fileName = match ($table) {
            'product_translations' => 'product_translations.json',
            'product_category_translations' => 'product_category_translations.json',
            'solution_translations' => 'solution_translations.json',
            'solution_category_translations' => 'solution_category_translations.json',
            'article_translations' => 'article_translations.json',
            'news_translations' => 'news_translations.json',
            'case_translations' => 'case_translations.json',
            'article_category_translations' => 'article_category_translations.json',
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
            'homepage_section_item_translations' => 'homepage_section_item_translations.json',
            default => '',
        };

        if ($fileName === '') {
            return '';
        }

        return dirname(__DIR__, 3) . '/runtime/storage/' . $fileName;
    }

    /**
     * @return array<string, mixed>
     */
    private function applySeoRoute(array $record, string $entityType, string $languageCode): array
    {
        if ($languageCode === 'zh') {
            return $record;
        }

        $seoRoute = $this->seoRouteRow($entityType, (int) ($record['id'] ?? 0), $languageCode);
        if ($seoRoute === null) {
            return $record;
        }

        $record['seo_title'] = (string) ($seoRoute['seo_title'] ?? ($record['seo_title'] ?? ''));
        $record['seo_keywords'] = (string) ($seoRoute['seo_keywords'] ?? ($record['seo_keywords'] ?? ''));
        $record['seo_description'] = (string) ($seoRoute['seo_description'] ?? ($record['seo_description'] ?? ''));
        $routePath = (string) ($seoRoute['route_path'] ?? '');
        $record['canonical_url'] = $this->resolveCanonicalUrl((string) ($seoRoute['canonical_url'] ?? ''), $routePath);
        $record['route_path'] = $routePath;
        $record['index_status'] = (string) ($seoRoute['index_status'] ?? 'index');

        return $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function seoRouteRow(string $entityType, int $entityId, string $languageCode): ?array
    {
        return $this->seoRepository->findRoute($entityType, $entityId, $languageCode);
    }

    private function resolveCanonicalUrl(string $canonicalUrl, string $routePath): string
    {
        $canonicalUrl = trim($canonicalUrl);
        $routePath = trim($routePath);
        $baseUrl = rtrim((string) env('APP_URL', 'http://127.0.0.1:8080'), '/');

        if ($routePath === '') {
            return $canonicalUrl;
        }

        if ($canonicalUrl === '' || str_starts_with($canonicalUrl, '/') || str_contains($canonicalUrl, '://example.com/')) {
            $normalizedRoutePath = str_starts_with($routePath, '/') ? $routePath : '/' . $routePath;

            return $baseUrl . $normalizedRoutePath;
        }

        return $canonicalUrl;
    }

    private function languagePayload(string $languageCode): array
    {
        foreach ($this->languages() as $language) {
            if ((string) ($language['code'] ?? '') === $languageCode) {
                return $language;
            }
        }

        $meta = $this->languageMeta($languageCode);

        return [
            'code' => $meta['code'],
            'name' => strtoupper($languageCode),
            'country' => $meta['country'],
            'native' => $meta['native'],
            'content' => $meta['content'],
            'htmlLang' => $meta['htmlLang'],
            'continent' => $meta['continent'],
            'flag_code' => $meta['flag_code'],
            'is_default' => $languageCode === 'zh' ? 1 : 0,
            'is_enabled' => 1,
            'sort' => 0,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function headerLanguageCandidates(string $header): array
    {
        $candidates = [];
        foreach (explode(',', $header) as $item) {
            $language = trim((string) explode(';', trim($item))[0]);
            $normalized = $this->normalizeLanguageCode($language);
            if ($normalized !== '') {
                $candidates[] = $normalized;
            }
        }

        return array_values(array_unique($candidates));
    }

    public function recordPageView(string $entityType, int $entityId, string $slug = '', array $context = []): void
    {
        $target = $this->resolvePageViewTarget($entityType, $entityId, $slug);
        if ($target === null) {
            return;
        }

        $table = $target['table'];
        $targetId = (int) ($target['id'] ?? 0);
        if ($table === '' || $targetId <= 0) {
            return;
        }

        $normalizedType = $this->normalizePageViewType((string) ($target['type'] ?? $entityType));
        $visitorCode = $this->resolvePageViewVisitorCode($context);
        if ($visitorCode === '') {
            return;
        }

        $page = $this->buildPageViewPath($normalizedType, $slug, $targetId, $context['page'] ?? '');
        $title = $this->buildPageViewTitle($context['title'] ?? '', $normalizedType, $targetId);
        $languageCode = trim((string) ($context['language_code'] ?? $context['accept_language'] ?? ''));
        $normalizedLanguageCode = $languageCode !== '' ? substr(trim(preg_replace('/\s*,.*$/', '', $languageCode)), 0, 12) : '';
        $referrer = trim((string) ($context['referer'] ?? $context['referrer'] ?? $context['source_page'] ?? ''));
        if ($normalizedLanguageCode === '') {
            $normalizedLanguageCode = trim((string) ($context['locale'] ?? ''));
        }

        $pdo = DatabaseManager::instance()->connection();
        if (!($pdo instanceof PDO)) {
            return;
        }

        $skipDedupe = !empty($context['skip_dedupe']);
        if (!$skipDedupe && $this->isDuplicatePageViewForSession($visitorCode, $page, $context, $pdo)) {
            return;
        }

        try {
            $update = $pdo->prepare("UPDATE {$table} SET views_count = views_count + 1 WHERE id = :id");
            $update->execute(['id' => $targetId]);

            if (empty($context['skip_event_log'])) {
                $insert = $pdo->prepare(
                    'INSERT INTO visitor_events (session_code, page, title, referrer, visited_at, language_code)
                     VALUES (:session_code, :page, :title, :referrer, :visited_at, :language_code)'
                );
                $insert->execute([
                    'session_code' => $visitorCode,
                    'page' => $page,
                    'title' => $title,
                    'referrer' => $referrer,
                    'visited_at' => date('Y-m-d H:i:s'),
                    'language_code' => $normalizedLanguageCode,
                ]);
            }
        } catch (\Throwable) {
        }
    }

    public function recordPageViewByPath(string $path, array $context = []): void
    {
        $resolved = $this->resolvePageViewMetaFromPath($path);
        if ($resolved === null) {
            return;
        }

        $this->recordPageView($resolved['type'], 0, $resolved['slug'], $context);
    }

    /**
     * @return array{table:string,id:int,type:string}|null
     */
    private function resolvePageViewTarget(string $entityType, int $entityId, string $slug = ''): ?array
    {
        $tableMap = [
            'product' => 'products',
            'solution' => 'solutions',
            'article' => 'articles',
            'case' => 'cases',
            'news' => 'news',
        ];

        $normalizedType = trim(strtolower($entityType));
        $normalizedSlug = trim((string) rawurldecode($slug));

        $resolveArticleType = static function (array $record): string {
            $contentType = strtolower(trim((string) ($record['content_type'] ?? '')));
            if (in_array($contentType, ['news', 'case'], true)) {
                return $contentType;
            }

            return 'article';
        };

        if ($normalizedSlug !== '' && $normalizedType === 'product') {
            $record = $this->productRepository->findBySlug($normalizedSlug);
            if ($record !== null && (int) ($record['id'] ?? 0) > 0) {
                return ['table' => 'products', 'id' => (int) $record['id'], 'type' => 'product'];
            }
        }

        if ($normalizedSlug !== '' && $normalizedType === 'solution') {
            $record = $this->solutionRepository->findBySlug($normalizedSlug);
            if ($record !== null && (int) ($record['id'] ?? 0) > 0) {
                return ['table' => 'solutions', 'id' => (int) $record['id'], 'type' => 'solution'];
            }
        }

        if ($normalizedSlug !== '' && $normalizedType === 'article') {
            $record = $this->articleRepository->findBySlug($normalizedSlug);
            if ($record !== null && (int) ($record['id'] ?? 0) > 0) {
                return [
                    'table' => 'articles',
                    'id' => (int) $record['id'],
                    'type' => $resolveArticleType($record),
                ];
            }
        }

        if ($normalizedSlug !== '' && $normalizedType === 'news') {
            $record = $this->newsRepository->findBySlug($normalizedSlug);
            if ($record !== null && (int) ($record['id'] ?? 0) > 0) {
                return ['table' => 'news', 'id' => (int) $record['id'], 'type' => 'news'];
            }
        }

        if ($normalizedSlug !== '' && $normalizedType === 'case') {
            $record = $this->caseRepository->findBySlug($normalizedSlug);
            if ($record !== null && (int) ($record['id'] ?? 0) > 0) {
                return ['table' => 'cases', 'id' => (int) $record['id'], 'type' => 'case'];
            }
        }

        if ($normalizedType === 'article') {
            $record = $this->articleRepository->find($entityId);
            if ($record !== null && (int) ($record['id'] ?? 0) > 0) {
                return [
                    'table' => 'articles',
                    'id' => (int) $record['id'],
                    'type' => $resolveArticleType($record),
                ];
            }
        }

        $table = $tableMap[$normalizedType] ?? '';
        if ($table === '' || $entityId <= 0) {
            return null;
        }

        return ['table' => $table, 'id' => $entityId, 'type' => $normalizedType];
    }

    private function resolvePageViewMetaFromPath(string $path): ?array
    {
        $normalizedPath = trim((string) preg_replace('/[#?].*$/', '', (string) $path));
        if ($normalizedPath === '') {
            return null;
        }

        $normalizedPath = preg_replace('/^\/[a-z]{2}(?=\/)/i', '', $normalizedPath) ?? '';
        $normalizedPath = ltrim((string) $normalizedPath, '/');
        $normalizedPath = trim((string) $normalizedPath, '/');

        $matches = [];
        if (!preg_match('/^(products|solutions|news|cases)\/([^\/]+?)(?:\\.html)?$/i', $normalizedPath, $matches)) {
            return null;
        }

        $section = strtolower((string) $matches[1]);
        $slug = trim((string) $matches[2]);
        if ($slug === '') {
            return null;
        }

        $typeMap = [
            'products' => 'product',
            'solutions' => 'solution',
            'news' => 'news',
            'cases' => 'case',
        ];
        $type = $typeMap[$section] ?? '';
        if ($type === '') {
            return null;
        }

        return ['type' => $type, 'slug' => $slug];
    }

    private function isDuplicatePageViewForSession(string $visitorCode, string $page, array $context, PDO $pdo): bool
    {
        $normalizedVisitorCode = trim($visitorCode);
        $clientId = trim((string) ($context['client_id'] ?? ''));
        $normalizedPage = trim($page);
        if (($normalizedVisitorCode === '' && $clientId === '') || $normalizedPage === '') {
            return false;
        }

        try {
            $conditions = ['session_code = :visitor_code'];
            $params = [
                'visitor_code' => $normalizedVisitorCode,
                'page' => $normalizedPage,
            ];
            if ($clientId !== '') {
                $conditions[] = 'session_code = :client_id';
                $params['client_id'] = $clientId;
            }

            $statement = $pdo->prepare(
                'SELECT 1
                 FROM visitor_events
                 WHERE (' . implode(' OR ', $conditions) . ')
                   AND page = :page
                   AND visited_at >= DATE_SUB(NOW(), INTERVAL 3 SECOND)
                 LIMIT 1'
            );
            $statement->execute($params);

            return (bool) $statement->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizePageViewType(string $type): string
    {
        $normalized = strtolower(trim($type));
        if ($normalized === '') {
            return 'page';
        }

        return in_array($normalized, ['article', 'news', 'case', 'product', 'solution'], true)
            ? $normalized
            : 'page';
    }

    private function resolvePageViewVisitorCode(array $context): string
    {
        $incoming = trim((string) ($context['visitor_code'] ?? ''));
        if ($incoming !== '') {
            $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '', $incoming);
            if ($sanitized !== '') {
                return substr($sanitized, 0, 64);
            }
        }

        $clientId = trim((string) ($context['client_id'] ?? ''));
        if ($clientId !== '') {
            $sanitizedClientId = preg_replace('/[^a-zA-Z0-9._-]/', '', $clientId);
            if ($sanitizedClientId !== '') {
                return substr($sanitizedClientId, 0, 64);
            }
        }

        $ip = $this->extractPageViewIp($context);
        $userAgent = $this->extractPageViewUserAgent($context);
        if ($ip === '' && $userAgent === '') {
            return '';
        }

        return 'web-' . substr(hash('sha256', $ip . '|' . $userAgent), 0, 48);
    }

    private function extractPageViewIp(array $context): string
    {
        $candidates = [
            $context['ip'] ?? '',
            $context['client_ip'] ?? '',
            $context['x_forwarded_for'] ?? '',
            $context['x-forwarded-for'] ?? '',
            $context['x_real_ip'] ?? '',
            $context['x-real-ip'] ?? '',
            $context['cf_connecting_ip'] ?? '',
            $context['cf-connecting-ip'] ?? '',
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $ip = trim($candidate);
            if ($ip === '') {
                continue;
            }

            if (str_contains($ip, ',')) {
                $ip = trim((string) explode(',', $ip)[0]);
            }
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        return '';
    }

    private function extractPageViewUserAgent(array $context): string
    {
        $userAgent = trim((string) ($context['user_agent'] ?? $context['user-agent'] ?? $context['ua'] ?? ''));
        if ($userAgent === '') {
            $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        }
        if ($userAgent === '') {
            return 'unknown';
        }

        return substr($userAgent, 0, 220);
    }

    private function buildPageViewPath(string $type, string $slug, int $targetId, string $fallbackPage): string
    {
        $fallback = trim((string) $fallbackPage);
        if ($fallback !== '') {
            return $fallback;
        }

        $normalizedSlug = trim($slug);
        if ($normalizedSlug !== '') {
            $normalizedType = in_array($type, ['product', 'solution', 'news', 'case', 'article'], true)
                ? $type
                : 'detail';
            return '/' . $normalizedType . '/' . $normalizedSlug . '.html';
        }

        return '/' . $type . '/id-' . $targetId;
    }

    private function buildPageViewTitle(string $title, string $type, int $targetId): string
    {
        $normalizedType = in_array($type, ['product', 'solution', 'news', 'case', 'article'], true) ? $type : 'page';
        $trimmedTitle = trim((string) $title);
        if ($trimmedTitle !== '') {
            return $trimmedTitle;
        }

        return ucfirst($normalizedType) . ' #' . $targetId;
    }

    public function robotsTxt(): string
    {
        $config = $this->systemSettingRepository->get('seo', 'site_files', []);
        $content = is_array($config) ? ($config['robots_content'] ?? '') : '';
        if (empty(trim((string) $content))) {
            $baseUrl = env('APP_URL', 'https://bagelsmachinery.com');
            $content = "User-agent: *\nAllow: /\n\nSitemap: {$baseUrl}/sitemap.xml\n";
        }
        return (string) $content;
    }

    public function sitemapXml(): string
    {
        $baseUrl = rtrim((string) env('APP_URL', 'https://bagelsmachinery.com'), '/');
        $languages = array_values(array_filter(
            $this->languageRepository->list(),
            static fn (array $item): bool => (int) ($item['is_enabled'] ?? 0) === 1
        ));
        $langCodes = array_values(array_unique(array_filter(array_map(function (array $lang): string {
            return strtolower(substr((string) ($lang['code'] ?? ''), 0, 2));
        }, $languages))));
        if ($langCodes === []) {
            $langCodes = ['zh'];
        }

        $localizedPath = static function (string $code, string $path): string {
            $normalized = trim($path, '/');
            $filePath = match ($normalized) {
                '', 'index' => 'index.html',
                'about' => 'about.html',
                'contact' => 'contact.html',
                'products' => 'products.html',
                'solutions' => 'solutions.html',
                'news' => 'news.html',
                'cases' => 'cases.html',
                default => $normalized,
            };

            if (!str_ends_with($filePath, '.html')) {
                $filePath .= '.html';
            }

            return '/' . trim($code, '/') . '/' . $filePath;
        };

        $routes = [];
        foreach ($langCodes as $langCode) {
            foreach (['index', 'about', 'contact', 'products', 'solutions', 'news', 'cases'] as $page) {
                $routes[] = $localizedPath($langCode, $page);
            }

            try {
                foreach ((array) ($this->products($langCode, 1, 100000)['items'] ?? []) as $item) {
                    if (!empty($item['slug'])) {
                        $routes[] = $localizedPath($langCode, 'products/' . (string) $item['slug']);
                    }
                }
                foreach ((array) ($this->solutions($langCode, 1, 100000)['items'] ?? []) as $item) {
                    if (!empty($item['slug'])) {
                        $routes[] = $localizedPath($langCode, 'solutions/' . (string) $item['slug']);
                    }
                }
                foreach ((array) ($this->newsList($langCode, 1, 100000)['items'] ?? []) as $item) {
                    if (!empty($item['slug'])) {
                        $routes[] = $localizedPath($langCode, 'news/' . (string) $item['slug']);
                    }
                }
                foreach ((array) ($this->caseList($langCode, 1, 100000)['items'] ?? []) as $item) {
                    if (!empty($item['slug'])) {
                        $routes[] = $localizedPath($langCode, 'cases/' . (string) $item['slug']);
                    }
                }
                foreach ((array) ($this->pages($langCode, 1, 100000)['items'] ?? []) as $item) {
                    $slug = strtolower(trim((string) ($item['slug'] ?? '')));
                    if ($slug === '' || in_array($slug, ['index', 'home', 'about', 'contact'], true)) {
                        continue;
                    }
                    $routes[] = $localizedPath($langCode, 'pages/' . $slug);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        $routes = array_values(array_unique($routes));
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($routes as $route) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . $escape($baseUrl . $route) . "</loc>\n";
            foreach ($langCodes as $langCode) {
                $parts = explode('/', trim($route, '/'));
                if ($parts !== []) {
                    $parts[0] = $langCode;
                }
                $alternate = '/' . implode('/', array_filter($parts, static fn (string $part): bool => $part !== ''));
                $xml .= '    <xhtml:link rel="alternate" hreflang="' . $escape($langCode) . '" href="' . $escape($baseUrl . $alternate) . "\" />\n";
            }
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        return $xml;
    }
    private function normalizeLanguageCode(string $languageCode): string
    {
        $languageCode = trim(strtolower($languageCode));
        if ($languageCode === '') {
            return '';
        }

        $languageCode = str_replace('_', '-', $languageCode);
        $parts = explode('-', $languageCode);

        return trim((string) ($parts[0] ?? ''));
    }

    private function resolveLanguageFlagCode(string $languageCode): string
    {
        return $this->languageMeta($languageCode)['flag_code'];
    }

    /**
     * @return array{code:string,country:string,native:string,content:string,htmlLang:string,continent:string,flag_code:string}
     */
    private function languageMeta(string $languageCode, string $fallbackName = ''): array
    {
        $normalized = $this->normalizeLanguageCode(str_replace('_', '-', $languageCode));
        if ($normalized === '') {
            return [
                'code' => 'zh',
                'country' => 'China',
                'native' => $fallbackName !== '' ? $fallbackName : '中文',
                'content' => 'zh',
                'htmlLang' => 'zh-CN',
                'continent' => 'asia',
                'flag_code' => 'cn',
            ];
        }

        $map = [
            'zh' => ['country' => 'China', 'native' => '中文', 'content' => 'zh', 'htmlLang' => 'zh-CN', 'continent' => 'asia', 'flag_code' => 'cn'],
            'en' => ['country' => 'United Kingdom', 'native' => 'English', 'content' => 'en', 'htmlLang' => 'en-GB', 'continent' => 'europe', 'flag_code' => 'gb'],
            'es' => ['country' => 'Spain', 'native' => 'Español', 'content' => 'es', 'htmlLang' => 'es-ES', 'continent' => 'europe', 'flag_code' => 'es'],
            'hi' => ['country' => 'India', 'native' => 'हिन्दी', 'content' => 'hi', 'htmlLang' => 'hi-IN', 'continent' => 'asia', 'flag_code' => 'in'],
            'ar' => ['country' => 'United Arab Emirates', 'native' => 'العربية', 'content' => 'ar', 'htmlLang' => 'ar-AE', 'continent' => 'asia', 'flag_code' => 'ae'],
            'fr' => ['country' => 'France', 'native' => 'Français', 'content' => 'fr', 'htmlLang' => 'fr-FR', 'continent' => 'europe', 'flag_code' => 'fr'],
            'de' => ['country' => 'Germany', 'native' => 'Deutsch', 'content' => 'de', 'htmlLang' => 'de-DE', 'continent' => 'europe', 'flag_code' => 'de'],
            'ja' => ['country' => 'Japan', 'native' => '日本語', 'content' => 'ja', 'htmlLang' => 'ja-JP', 'continent' => 'asia', 'flag_code' => 'jp'],
            'pt' => ['country' => 'Portugal', 'native' => 'Português', 'content' => 'pt', 'htmlLang' => 'pt-PT', 'continent' => 'europe', 'flag_code' => 'pt'],
            'ru' => ['country' => 'Russia', 'native' => 'Русский', 'content' => 'ru', 'htmlLang' => 'ru-RU', 'continent' => 'europe', 'flag_code' => 'ru'],
            'it' => ['country' => 'Italy', 'native' => 'Italiano', 'content' => 'it', 'htmlLang' => 'it-IT', 'continent' => 'europe', 'flag_code' => 'it'],
            'ko' => ['country' => 'South Korea', 'native' => '한국어', 'content' => 'ko', 'htmlLang' => 'ko-KR', 'continent' => 'asia', 'flag_code' => 'kr'],
            'tr' => ['country' => 'Turkey', 'native' => 'Türkçe', 'content' => 'tr', 'htmlLang' => 'tr-TR', 'continent' => 'asia', 'flag_code' => 'tr'],
            'nl' => ['country' => 'Netherlands', 'native' => 'Nederlands', 'content' => 'nl', 'htmlLang' => 'nl-NL', 'continent' => 'europe', 'flag_code' => 'nl'],
            'pl' => ['country' => 'Poland', 'native' => 'Polski', 'content' => 'pl', 'htmlLang' => 'pl-PL', 'continent' => 'europe', 'flag_code' => 'pl'],
            'vi' => ['country' => 'Vietnam', 'native' => 'Tiếng Việt', 'content' => 'vi', 'htmlLang' => 'vi-VN', 'continent' => 'asia', 'flag_code' => 'vn'],
            'th' => ['country' => 'Thailand', 'native' => 'ไทย', 'content' => 'th', 'htmlLang' => 'th-TH', 'continent' => 'asia', 'flag_code' => 'th'],
            'sv' => ['country' => 'Sweden', 'native' => 'Svenska', 'content' => 'sv', 'htmlLang' => 'sv-SE', 'continent' => 'europe', 'flag_code' => 'se'],
            'id' => ['country' => 'Indonesia', 'native' => 'Bahasa Indonesia', 'content' => 'id', 'htmlLang' => 'id-ID', 'continent' => 'asia', 'flag_code' => 'id'],
            'el' => ['country' => 'Greece', 'native' => 'Ελληνικά', 'content' => 'el', 'htmlLang' => 'el-GR', 'continent' => 'europe', 'flag_code' => 'gr'],
            'cs' => ['country' => 'Czech Republic', 'native' => 'Čeština', 'content' => 'cs', 'htmlLang' => 'cs-CZ', 'continent' => 'europe', 'flag_code' => 'cz'],
            'hu' => ['country' => 'Hungary', 'native' => 'Magyar', 'content' => 'hu', 'htmlLang' => 'hu-HU', 'continent' => 'europe', 'flag_code' => 'hu'],
            'ro' => ['country' => 'Romania', 'native' => 'Română', 'content' => 'ro', 'htmlLang' => 'ro-RO', 'continent' => 'europe', 'flag_code' => 'ro'],
            'uk' => ['country' => 'Ukraine', 'native' => 'Українська', 'content' => 'uk', 'htmlLang' => 'uk-UA', 'continent' => 'europe', 'flag_code' => 'ua'],
            'ms' => ['country' => 'Malaysia', 'native' => 'Bahasa Melayu', 'content' => 'ms', 'htmlLang' => 'ms-MY', 'continent' => 'asia', 'flag_code' => 'my'],
        ];

        $base = substr($normalized, 0, 2);
        $meta = $map[$base] ?? null;
        if ($meta !== null) {
            return ['code' => $base] + $meta;
        }

        if (preg_match('/^[a-z]{2}-([a-z]{2})$/', $normalized, $matches)) {
            return [
                'code' => $base,
                'country' => strtoupper($matches[1]),
                'native' => $fallbackName !== '' ? $fallbackName : strtoupper($base),
                'content' => $base,
                'htmlLang' => $normalized,
                'continent' => 'global',
                'flag_code' => $matches[1],
            ];
        }

        return [
            'code' => $base,
            'country' => strtoupper($base),
            'native' => $fallbackName !== '' ? $fallbackName : strtoupper($base),
            'content' => $base,
            'htmlLang' => $base,
            'continent' => 'global',
            'flag_code' => $base,
        ];
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
}
