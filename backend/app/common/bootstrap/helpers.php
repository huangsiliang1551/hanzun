<?php

declare(strict_types=1);

use app\common\config\ConfigRepository;
use app\common\http\Request;
use app\common\http\RequestContext;

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = dirname(__DIR__, 3);
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('env_flag')) {
    function env_flag(string $key, bool $default = false): bool
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', '(true)', 'yes', 'on'], true);
    }
}

if (!function_exists('should_prefer_runtime_storage')) {
    function should_prefer_runtime_storage(string|array $paths = []): bool
    {
        if (env_flag('PREFER_RUNTIME_STORAGE')) {
            return true;
        }

        if (env_flag('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK')) {
            return false;
        }

        if (PHP_SAPI !== 'cli') {
            return false;
        }

        foreach ((array) $paths as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return ConfigRepository::instance()->get($key, $default);
    }
}
if (!function_exists('request')) {
    function request(): ?Request
    {
        return RequestContext::request();
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return RequestContext::user();
    }
}
