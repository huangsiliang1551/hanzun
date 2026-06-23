<?php

declare(strict_types=1);

namespace app\publicapi\controller;

use app\common\helpers\Sanitizer;
use app\common\http\Request;
use app\service\content\PublicSiteService;
use app\service\inquiry\InquiryService;

final class ContentController extends BasePublicController
{
    private const int MAX_NAME_LENGTH = 80;
    private const int MAX_PHONE_LENGTH = 40;
    private const int MAX_EMAIL_LENGTH = 120;
    private const int MAX_MESSAGE_LENGTH = 2000;

    public function __construct(private readonly PublicSiteService $publicSiteService = new PublicSiteService())
    {
    }

    public function bootstrap(Request $request): array
    {
        $language = $this->resolveLanguage($request);

        return $this->success(
            $this->publicSiteService->bootstrap($language['resolved_code']),
            ['language' => $language]
        );
    }

    public function navigation(Request $request): array
    {
        $language = $this->resolveLanguage($request);

        return $this->success(
            $this->publicSiteService->navigation((string) $request->input('menu_position', ''), $language['resolved_code']),
            ['language' => $language]
        );
    }

    public function homepage(Request $request): array
    {
        $language = $this->resolveLanguage($request);

        return $this->success(
            $this->publicSiteService->homepage($language['resolved_code']),
            ['language' => $language]
        );
    }

    public function ads(Request $request): array
    {
        $language = $this->resolveLanguage($request);

        return $this->success(
            $this->publicSiteService->ads($language['resolved_code'], (string) $request->input('page_scope', '')),
            ['language' => $language]
        );
    }

    public function about(Request $request): array
    {
        $language = $this->resolveLanguage($request);

        return $this->success(
            $this->publicSiteService->about($language['resolved_code']),
            ['language' => $language]
        );
    }

    public function contact(Request $request): array
    {
        $language = $this->resolveLanguage($request);

        return $this->success(
            $this->publicSiteService->contact($language['resolved_code']),
            ['language' => $language]
        );
    }

    public function products(Request $request): array
    {
        $language = $this->resolveLanguage($request);
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(1, min(100, (int) $request->input('per_page', '12')));
        $ids = $request->input('ids', '');

        return $this->success(
            $this->publicSiteService->products($language['resolved_code'], $page, $perPage, $ids),
            ['language' => $language]
        );
    }

    public function productDetail(Request $request): array
    {
        $language = $this->resolveLanguage($request);

        return $this->success(
            $this->normalizeSeoMeta($this->publicSiteService->productDetail((string) $request->routeParam('slug'), $language['resolved_code'])),
            ['language' => $language]
        );
    }

    public function solutions(Request $request): array
    {
        $language = $this->resolveLanguage($request);
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(1, min(100, (int) $request->input('per_page', '12')));
        $ids = $request->input('ids', '');

        return $this->success(
            $this->publicSiteService->solutions($language['resolved_code'], $page, $perPage, $ids),
            ['language' => $language]
        );
    }

    public function solutionDetail(Request $request): array
    {
        $language = $this->resolveLanguage($request);

        return $this->success(
            $this->normalizeSeoMeta($this->publicSiteService->solutionDetail((string) $request->routeParam('slug'), $language['resolved_code'])),
            ['language' => $language]
        );
    }

    public function articles(Request $request): array
    {
        $language = $this->resolveLanguage($request);
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(1, min(100, (int) $request->input('per_page', '12')));

        return $this->success(
            $this->publicSiteService->articles((string) $request->input('content_type', ''), $language['resolved_code'], $page, $perPage),
            ['language' => $language]
        );
    }

    public function newsList(Request $request): array
    {
        $language = $this->resolveLanguage($request);
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(1, min(100, (int) $request->input('per_page', '12')));

        return $this->success(
            $this->publicSiteService->newsList($language['resolved_code'], $page, $perPage),
            ['language' => $language]
        );
    }

    public function caseList(Request $request): array
    {
        $language = $this->resolveLanguage($request);
        $page = max(1, (int) $request->input('page', '1'));
        $perPage = max(1, min(100, (int) $request->input('per_page', '12')));

        return $this->success(
            $this->publicSiteService->caseList($language['resolved_code'], $page, $perPage),
            ['language' => $language]
        );
    }

    public function articleDetail(Request $request): array
    {
        $language = $this->resolveLanguage($request);

        return $this->success(
            $this->normalizeSeoMeta($this->publicSiteService->articleDetail((string) $request->routeParam('slug'), $language['resolved_code'])),
            ['language' => $language]
        );
    }

    public function pageDetail(Request $request): array
    {
        $language = $this->resolveLanguage($request);

        return $this->success(
            $this->normalizeSeoMeta($this->publicSiteService->pageDetail((string) $request->routeParam('slug'), $language['resolved_code'])),
            ['language' => $language]
        );
    }

    public function lead(Request $request): array
    {
        $honeypot = trim((string) $request->input('_website', ''));
        $legacyHoneypot = trim((string) $request->input('company_website', ''));

        if ($honeypot !== '' || $legacyHoneypot !== '') {
            return $this->success(['id' => 0, 'silent' => true]);
        }

        $inquiryService = new InquiryService();

        $data = Sanitizer::sanitizeArray([
            'name' => $this->normalizeLeadValue((string) $request->input('name', ''), self::MAX_NAME_LENGTH),
            'phone' => $this->normalizeLeadValue((string) $request->input('phone', ''), self::MAX_PHONE_LENGTH),
            'email' => $this->normalizeLeadValue((string) $request->input('email', ''), self::MAX_EMAIL_LENGTH),
            'message' => $this->normalizeLeadValue((string) $request->input('message', ''), self::MAX_MESSAGE_LENGTH),
        ]);

        $result = $inquiryService->createFromLeadForm($data);

        return $this->success($result);
    }
    public function pageview(Request $request): array
    {
        $entityType = trim((string) $request->input('entity_type', ''));
        $entityId = (int) $request->input('entity_id', '0');

        if ($entityType === '' || $entityId <= 0) {
            return $this->success();
        }

        $this->publicSiteService->recordPageView($entityType, $entityId);

        return $this->success();
    }

    public function robotsTxt(): void
    {
        $content = $this->publicSiteService->robotsTxt();
        header('Content-Type: text/plain; charset=utf-8');
        echo $content;
        exit;
    }

    public function sitemapXml(): void
    {
        $xml = $this->publicSiteService->sitemapXml();
        header('Content-Type: application/xml; charset=utf-8');
        echo $xml;
        exit;
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function normalizeSeoMeta(array $record): array
    {
        $seo = array_filter([
            'title' => $record['seo_title'] ?? null,
            'description' => $record['seo_description'] ?? null,
            'keywords' => $record['seo_keywords'] ?? null,
            'canonical_url' => $record['canonical_url'] ?? null,
            'index_status' => $record['index_status'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $record['seo'] = $seo;

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeLeadValue(string $value, int $maxLength): string
    {
        $normalized = trim($value);

        if ($maxLength <= 0) {
            return $normalized;
        }

        return mb_strlen($normalized, 'UTF-8') > $maxLength
            ? mb_substr($normalized, 0, $maxLength, 'UTF-8')
            : $normalized;
    }

    private function resolveLanguage(Request $request): array
    {
        return $this->publicSiteService->resolveLanguage(
            (string) $request->input('lang', ''),
            $request->header('Accept-Language', '')
        );
    }
}
