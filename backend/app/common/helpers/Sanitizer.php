<?php

declare(strict_types=1);

namespace app\common\helpers;

/**
 * Input sanitization helper for XSS protection.
 *
 * - Rich text fields (article content): allows safe HTML tags (p, br, strong, em, a, img, ul, ol, li, etc.)
 * - Plain text fields: strip all HTML tags
 * - Strip <script>, <iframe>, on* event handlers, javascript: URLs
 */
final class Sanitizer
{
    /**
     * Allowed HTML tags for rich content (Quill-edited fields).
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'span',
        'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'pre', 'code',
        'a', 'img',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div', 'section', 'figure', 'figcaption',
        'hr', 'sub', 'sup', 'small', 'mark',
    ];

    private const ALLOWED_ATTRS = [
        'href', 'target', 'rel',
        'src', 'alt', 'width', 'height', 'loading',
        'class', 'id', 'style',
        'title', 'align',
    ];

    /**
     * Sanitize rich text (preserve safe HTML, remove dangerous content).
     */
    public static function richText(string $input): string
    {
        return self::stripDangerousHtml($input, true);
    }

    /**
     * Sanitize plain text (strip all HTML tags).
     */
    public static function plainText(string $input): string
    {
        return self::stripDangerousHtml($input, false);
    }

    /**
     * Deep-sanitize an associative array of string fields.
     *
     * @param array<string, mixed> $data
     * @param array<string, bool> $richFields Map of field name => is rich text
     * @return array<string, mixed>
     */
    public static function sanitizeArray(array $data, array $richFields = []): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $isRich = isset($richFields[$key]) && $richFields[$key] === true;
                $result[$key] = $isRich ? self::richText($value) : self::plainText($value);
            } elseif (is_array($value)) {
                $result[$key] = self::sanitizeArray($value, $richFields);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Strip dangerous HTML while optionally preserving safe tags.
     */
    private static function stripDangerousHtml(string $input, bool $preserveSafeTags): string
    {
        // Remove NULL bytes
        $input = str_replace("\0", '', $input);

        if (!$preserveSafeTags) {
            return strip_tags($input);
        }

        // Strip <script>, <iframe>, <object>, <embed>, <style> tags and their content
        $input = preg_replace(
            '/<(\/?)\s*(script|iframe|object|embed|style|form|input|button|textarea|select|option|optgroup|label|fieldset|legend|noscript|meta|link|base)[^>]*>/i',
            '',
            $input
        ) ?? $input;

        // Strip event handlers (onclick, onload, onerror, etc.)
        $input = preg_replace(
            '/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i',
            '',
            $input
        ) ?? $input;

        // Strip javascript: URLs in href and src
        $input = preg_replace(
            '/\s+(href|src)\s*=\s*"(javascript|vbscript):[^"]*"/i',
            '',
            $input
        ) ?? $input;
        $input = preg_replace(
            '/\s+(href|src)\s*=\s*\'(javascript|vbscript):[^\']*\'/i',
            '',
            $input
        ) ?? $input;

        // Strip data: URLs in src
        $input = preg_replace(
            '/\s+src\s*=\s*"data:[^"]*"/i',
            '',
            $input
        ) ?? $input;

        // Use strip_tags with allowed tags for final cleanup
        return strip_tags($input, '<' . implode('><', self::ALLOWED_TAGS) . '>');
    }
}
