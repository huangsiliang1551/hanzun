<?php

declare(strict_types=1);

namespace app\repository;

final class PageSeoRepository
{
    private const GROUP = 'seo';
    private const KEY = 'page_meta';

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $pageKey, string $languageCode): ?array
    {
        $pageKey = $this->normalizePageKey($pageKey);
        $languageCode = $this->normalizeLanguageCode($languageCode);
        if ($pageKey === '' || $languageCode === '') {
            return null;
        }

        $storage = $this->storage();
        $payload = $storage[$pageKey][$languageCode] ?? null;

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function upsert(string $pageKey, string $languageCode, array $payload): void
    {
        $pageKey = $this->normalizePageKey($pageKey);
        $languageCode = $this->normalizeLanguageCode($languageCode);
        if ($pageKey === '' || $languageCode === '') {
            return;
        }

        $storage = $this->storage();
        if (!isset($storage[$pageKey]) || !is_array($storage[$pageKey])) {
            $storage[$pageKey] = [];
        }

        $storage[$pageKey][$languageCode] = [
            'page_key' => $pageKey,
            'language_code' => $languageCode,
            'seo_title' => trim((string) ($payload['seo_title'] ?? '')),
            'seo_keywords' => trim((string) ($payload['seo_keywords'] ?? '')),
            'seo_description' => trim((string) ($payload['seo_description'] ?? '')),
            'canonical_url' => trim((string) ($payload['canonical_url'] ?? '')),
            'index_status' => trim((string) ($payload['index_status'] ?? 'index')) ?: 'index',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->write($storage);
    }

    public function deletePage(string $pageKey): void
    {
        $pageKey = $this->normalizePageKey($pageKey);
        if ($pageKey === '') {
            return;
        }

        $storage = $this->storage();
        unset($storage[$pageKey]);
        $this->write($storage);
    }

    public function deleteLanguage(string $languageCode): void
    {
        $languageCode = $this->normalizeLanguageCode($languageCode);
        if ($languageCode === '') {
            return;
        }

        $storage = $this->storage();
        foreach ($storage as $pageKey => $items) {
            if (!is_array($items)) {
                continue;
            }

            unset($storage[$pageKey][$languageCode]);
            if (($storage[$pageKey] ?? []) === []) {
                unset($storage[$pageKey]);
            }
        }

        $this->write($storage);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByRoute(string $languageCode, string $route): ?array
    {
        $pageKey = $this->resolvePageKeyByRoute($route);
        if ($pageKey === '') {
            return null;
        }

        return $this->find($pageKey, $languageCode);
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function all(): array
    {
        return $this->storage();
    }

    public function resolvePageKeyByRoute(string $route): string
    {
        $route = trim(strtolower($route));
        if ($route === '') {
            return '';
        }

        $normalized = preg_replace('#^/[a-z]{2}(?=/)#', '', $route) ?? $route;
        $normalized = '/' . ltrim($normalized, '/');

        return match ($normalized) {
            '/index.html', '/' => 'homepage',
            '/about.html', '/pages/about-us.html' => 'about',
            '/contact.html' => 'contact',
            '/products.html' => 'product_list',
            '/solutions.html' => 'solution_list',
            '/news.html' => 'news_list',
            '/cases.html' => 'case_list',
            default => '',
        };
    }

    private function normalizePageKey(string $value): string
    {
        return strtolower(trim($value));
    }

    private function normalizeLanguageCode(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function storage(): array
    {
        $repository = new SystemSettingRepository();
        $storage = $repository->get(self::GROUP, self::KEY, []);

        return is_array($storage) ? $storage : [];
    }

    /**
     * @param array<string, array<string, array<string, mixed>>> $storage
     */
    private function write(array $storage): void
    {
        $repository = new SystemSettingRepository();
        $repository->put(self::GROUP, self::KEY, $storage);
    }
}
