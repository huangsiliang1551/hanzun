<?php

declare(strict_types=1);

namespace app\service\content;

final class ContentCoverFallbackResolver
{
    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    public static function resolve(string $entityType, array $item): ?array
    {
        $path = self::resolvePath($entityType, $item);
        if ($path === '') {
            return null;
        }

        $fileName = basename($path);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return [
            'asset_id' => 0,
            'file_name' => $fileName,
            'file_path' => $path,
            'thumb_url' => $path,
            'mime_type' => self::mimeTypeFromExtension($extension),
            'file_ext' => $extension,
        ];
    }

    /**
     * @param array<string, mixed> $item
     */
    private static function resolvePath(string $entityType, array $item): string
    {
        $slug = trim((string) ($item['slug'] ?? ''));
        $contentType = trim((string) ($item['content_type'] ?? ''));

        return match ($entityType) {
            'product' => match ($slug) {
                'cake-depositor' => '/assets/images/home/equipment-forming-module.jpg',
                default => '/assets/images/home/equipment-transfer-line.jpg',
            },
            'solution' => match ($slug) {
                'cake-line' => '/assets/images/home/equipment-integrated-line.jpg',
                default => '/assets/images/home/company-strength-process-generated.jpg',
            },
            'news' => match ($slug) {
                'uae-cake-project' => '/assets/images/home/news-real-handshake-team.jpg',
                'germany-bakery-expo' => '/assets/images/home/news-real-expo-hall.jpg',
                default => '/assets/images/home/news-real-booth.jpg',
            },
            'case' => '/assets/images/home/news-real-handshake-team.jpg',
            'article' => match ($slug) {
                'uae-cake-project' => '/assets/images/home/news-real-handshake-team.jpg',
                'germany-bakery-expo' => '/assets/images/home/news-real-expo-hall.jpg',
                default => $contentType === 'case'
                    ? '/assets/images/home/news-real-handshake-team.jpg'
                    : '/assets/images/home/news-real-booth.jpg',
            },
            'page' => match ($slug) {
                'cake-line-landing' => '/assets/images/common/logo-110.png',
                default => '/assets/images/common/logo-110.png',
            },
            default => '',
        };
    }

    private static function mimeTypeFromExtension(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }
}
