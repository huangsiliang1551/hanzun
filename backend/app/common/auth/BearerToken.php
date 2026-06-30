<?php

declare(strict_types=1);

namespace app\common\auth;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;

final class BearerToken
{
    public static function extract(string $authorization): string
    {
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            throw new BusinessException('登录会话已失效', ErrorCode::UNAUTHORIZED);
        }

        return trim((string) $matches[1]);
    }
}
