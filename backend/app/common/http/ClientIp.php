<?php

declare(strict_types=1);

namespace app\common\http;

final class ClientIp
{
    public static function resolve(array $server = []): string
    {
        $server = $server === [] ? $_SERVER : $server;
        $remoteAddr = trim((string) ($server['REMOTE_ADDR'] ?? ''));

        $trustedProxies = array_filter(array_map(
            'trim',
            explode(',', (string) getenv('TRUSTED_PROXIES'))
        ));

        if ($remoteAddr !== '' && in_array($remoteAddr, $trustedProxies, true)) {
            $forwardedFor = trim((string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));

            if ($forwardedFor !== '') {
                $first = trim(explode(',', $forwardedFor)[0] ?? '');

                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }

            $realIp = trim((string) ($server['HTTP_X_REAL_IP'] ?? ''));
            if (filter_var($realIp, FILTER_VALIDATE_IP)) {
                return $realIp;
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
    }
}
