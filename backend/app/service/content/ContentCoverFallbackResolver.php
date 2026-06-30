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
            default => '', // no fallback — use uploaded cover or none
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
