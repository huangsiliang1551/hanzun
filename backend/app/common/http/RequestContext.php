<?php

declare(strict_types=1);

namespace app\common\http;

final class RequestContext
{
    private static ?Request $request = null;

    /**
     * @var array<string, mixed>|null
     */
    private static ?array $user = null;

    public static function setRequest(Request $request): void
    {
        self::$request = $request;
    }

    public static function request(): ?Request
    {
        return self::$request;
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public static function setUser(?array $user): void
    {
        self::$user = $user;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        return self::$user;
    }
}
