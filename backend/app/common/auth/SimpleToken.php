<?php

declare(strict_types=1);

namespace app\common\auth;

final class SimpleToken
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload, string $secret): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', (string) $json, $secret);

        return base64_encode((string) $json) . '.' . $signature;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decode(string $token, string $secret): ?array
    {
        [$encoded, $signature] = array_pad(explode('.', $token, 2), 2, null);
        if ($encoded === null || $signature === null) {
            return null;
        }

        $json = base64_decode($encoded, true);
        if ($json === false) {
            return null;
        }

        $expected = hash_hmac('sha256', $json, $secret);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payload = json_decode($json, true);
        return is_array($payload) ? $payload : null;
    }
}
