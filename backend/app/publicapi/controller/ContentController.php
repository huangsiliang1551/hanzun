<?php

declare(strict_types=1);

namespace app\publicapi\controller;

use app\common\helpers\Sanitizer;
use app\common\http\Request;
use app\service\content\PublicSiteService;
use app\service\inquiry\InquiryService;

final class ContentController extends BasePublicController
{
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

    public function newsDetail(Request $request): array
    {
        $language = $this->resolveLanguage($request);

        return $this->success(
            $this->normalizeSeoMeta($this->publicSiteService->newsDetail((string) $request->routeParam('slug'), $language['resolved_code'])),
            ['language' => $language]
        );
    }

    public function caseDetail(Request $request): array
    {
        $language = $this->resolveLanguage($request);

        return $this->success(
            $this->normalizeSeoMeta($this->publicSiteService->caseDetail((string) $request->routeParam('slug'), $language['resolved_code'])),
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
        // FIX-25: Honeypot anti-spam — if the hidden field is filled, it's a bot
        $honeypot = trim((string) $request->input('_website', ''));
        if ($honeypot !== '') {
            // Silently accept but don't process (bot ignored)
            return $this->success(['id' => 0, 'silent' => true]);
        }

        // Rate limiting on lead form is already handled by Router

        $inquiryService = new InquiryService();

        $data = Sanitizer::sanitizeArray([
            'name' => $request->input('name', ''),
            'phone' => $request->input('phone', ''),
            'email' => $request->input('email', ''),
            'message' => $request->input('message', ''),
            'language_code' => $request->input('language_code', $request->input('language', '')),
            'accept_language' => $request->header('Accept-Language', ''),
            'country_code' => $request->input('country_code', $request->header('CF-IPCountry', '')),
            'source_page' => $request->input('source_page', $request->input('path', '')),
        ]);

        $result = $inquiryService->createFromLeadForm($data);

        return $this->success($result);
    }

    public function pageview(Request $request): array
    {
        $entityType = trim((string) $request->input('entity_type', ''));
        $entityId = (int) $request->input('entity_id', '0');
        $slug = trim((string) $request->input('slug', ''));

        if ($entityType === '' || ($entityId <= 0 && $slug === '')) {
            return $this->success();
        }

        $context = [
            'visitor_code' => trim((string) $request->input('visitor_code', '')),
            'client_id' => trim((string) $request->input('client_id', '')),
            'page' => trim((string) $request->input('page', '')),
            'source_page' => trim((string) $request->input('source_page', '')),
            'referer' => trim((string) $request->header('Referer', '')),
            'accept_language' => trim((string) $request->header('Accept-Language', '')),
            'language_code' => trim((string) $request->input('language_code', '')),
            'client_ip' => $this->resolveClientIpForPageView($request),
            'user_agent' => trim((string) $request->header('User-Agent', '')),
            'x_forwarded_for' => trim((string) $request->header('X-Forwarded-For', '')),
            'x_real_ip' => trim((string) $request->header('X-Real-IP', '')),
            'cf_connecting_ip' => trim((string) $request->header('CF-Connecting-IP', '')),
            'title' => trim((string) $request->input('title', '')),
            'skip_dedupe' => true,
            'skip_event_log' => true,
        ];

        $this->publicSiteService->recordPageView($entityType, $entityId, $slug, $context);

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

    private function resolveClientIpForPageView(Request $request): string
    {
        $candidates = [
            trim((string) $request->header('CF-Connecting-IP', '')),
            trim((string) $request->header('X-Real-IP', '')),
            trim((string) $request->header('X-Forwarded-For', '')),
        ];
        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $candidate = str_replace(' ', '', $candidate);
            if ($candidate === '') {
                continue;
            }

            $ip = str_contains($candidate, ',') ? trim((string) explode(',', $candidate)[0]) : $candidate;
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }

        if (isset($_SERVER['REMOTE_ADDR']) && is_string($_SERVER['REMOTE_ADDR'])) {
            return trim((string) $_SERVER['REMOTE_ADDR']);
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveLanguage(Request $request): array
    {
        return $this->publicSiteService->resolveLanguage(
            (string) $request->input('lang', ''),
            $request->header('Accept-Language', '')
        );
    }
}
