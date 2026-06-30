<?php

declare(strict_types=1);

namespace app\service\seo;

use app\repository\LanguageRepository;
use app\repository\PageSeoRepository;
use app\repository\SitePhraseRepository;
use app\service\content\PublicSiteService;

final class PageSeoSyncService
{
    public function __construct(
        private readonly LanguageRepository $languageRepository = new LanguageRepository(),
        private readonly PageSeoRepository $pageSeoRepository = new PageSeoRepository(),
        private readonly PublicSiteService $publicSiteService = new PublicSiteService(),
        private readonly SitePhraseRepository $sitePhraseRepository = new SitePhraseRepository()
    ) {
    }

    public function syncAll(): void
    {
        foreach ($this->enabledLanguages() as $languageCode) {
            foreach ($this->pageDefinitions($languageCode) as $pageKey => $payload) {
                $this->pageSeoRepository->upsert($pageKey, $languageCode, $payload);
            }
        }
    }

    public function syncByEntityType(string $entityType): void
    {
        $pageKeys = match ($entityType) {
            'product' => ['homepage', 'product_list'],
            'solution' => ['homepage', 'solution_list'],
            'news' => ['homepage', 'news_list'],
            'case' => ['homepage', 'case_list'],
            'article' => ['homepage', 'news_list', 'case_list'],
            default => ['homepage'],
        };

        // Precompute definitions for ALL languages once (was N× per language before)
        $definitionsByLanguage = [];
        foreach ($this->enabledLanguages() as $languageCode) {
            $definitionsByLanguage[$languageCode] = $this->pageDefinitions($languageCode);
        }

        foreach ($definitionsByLanguage as $languageCode => $definitions) {
            foreach ($pageKeys as $pageKey) {
                if (!isset($definitions[$pageKey])) {
                    continue;
                }
                $this->pageSeoRepository->upsert($pageKey, $languageCode, $definitions[$pageKey]);
            }
        }
    }

    public function deleteLanguage(string $languageCode): void
    {
        $this->pageSeoRepository->deleteLanguage($languageCode);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function pageDefinitions(string $languageCode): array
    {
        $site = $this->publicSiteService->site($languageCode);
        $companyName = trim((string) ($site['company_name'] ?? $site['site_name'] ?? ''));
        $companySubtitle = trim((string) ($site['company_subtitle'] ?? ''));
        $siteDescription = trim((string) ($site['meta_description'] ?? ''));
        $brandTitle = $companySubtitle !== '' ? $companyName . ' | ' . $companySubtitle : $companyName;

        $labels = $this->labels($languageCode);
        $baseUrl = rtrim((string) env('APP_URL', 'https://bagelsmachinery.com'), '/');

        $build = function (string $pageKey, string $title, string $description, string $route) use ($baseUrl): array {
            return [
                'seo_title' => $title,
                'seo_keywords' => $title,
                'seo_description' => $description !== '' ? $description : $title,
                'canonical_url' => $baseUrl . $route,
                'index_status' => 'index',
            ];
        };

        return [
            'homepage' => $build('homepage', $brandTitle, $siteDescription, '/' . $languageCode . '/index.html'),
            'about' => $build('about', $companyName . ' - ' . $labels['about'], $siteDescription, '/' . $languageCode . '/about.html'),
            'contact' => $build('contact', $labels['contact'] . ' - ' . $companyName, $labels['contact'], '/' . $languageCode . '/contact.html'),
            'product_list' => $build('product_list', $labels['products'] . ' - ' . $companyName, $this->listingDescription($languageCode, 'product', $siteDescription), '/' . $languageCode . '/products.html'),
            'solution_list' => $build('solution_list', $labels['solutions'] . ' - ' . $companyName, $this->listingDescription($languageCode, 'solution', $siteDescription), '/' . $languageCode . '/solutions.html'),
            'news_list' => $build('news_list', $labels['news'] . ' - ' . $companyName, $this->listingDescription($languageCode, 'news', $siteDescription), '/' . $languageCode . '/news.html'),
            'case_list' => $build('case_list', $labels['cases'] . ' - ' . $companyName, $this->listingDescription($languageCode, 'case', $siteDescription), '/' . $languageCode . '/cases.html'),
        ];
    }

    private function listingDescription(string $languageCode, string $entityType, string $fallback): string
    {
        $phraseKey = match ($entityType) {
            'product' => 'listing_desc_products',
            'solution' => 'listing_desc_solutions',
            'news' => 'listing_desc_news',
            'case' => 'listing_desc_cases',
            default => null,
        };

        if ($phraseKey !== null) {
            $text = $this->sitePhraseRepository->getText($phraseKey, $languageCode, '');
            if (trim($text) !== '') {
                return $text;
            }
        }

        return $fallback;
    }

    /**
     * @return array<string, string>
     */
    private function labels(string $languageCode): array
    {
        $phrase = fn (string $key, string $fallback): string => $this->sitePhraseRepository->getText($key, $languageCode, $fallback);

        return [
            'about' => $phrase('nav_about', $languageCode === 'zh' ? '公司介绍' : 'About'),
            'contact' => $phrase('nav_contact', $languageCode === 'zh' ? '联系我们' : 'Contact'),
            'products' => $phrase('nav_products', $languageCode === 'zh' ? '产品中心' : 'Products'),
            'solutions' => $phrase('nav_solutions', $languageCode === 'zh' ? '解决方案' : 'Solutions'),
            'news' => $phrase('nav_news', $languageCode === 'zh' ? '企业新闻' : 'News'),
            'cases' => $phrase('nav_cases', $languageCode === 'zh' ? '客户案例' : 'Cases'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function enabledLanguages(): array
    {
        $items = [];
        foreach ($this->languageRepository->list() as $language) {
            if ((int) ($language['is_enabled'] ?? 0) !== 1) {
                continue;
            }

            $code = strtolower(trim((string) ($language['code'] ?? '')));
            if ($code !== '') {
                $items[] = $code;
            }
        }

        return $items === [] ? ['zh'] : array_values(array_unique($items));
    }
}
