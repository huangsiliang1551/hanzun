<?php

declare(strict_types=1);

namespace app\common\bootstrap;

final class Autoloader
{
    public static function register(string $rootPath): void
    {
        spl_autoload_register(static function (string $className) use ($rootPath): void {
            $prefix = 'app\\';
            if (!str_starts_with($className, $prefix)) {
                return;
            }

            $relative = substr($className, strlen($prefix));
            $filePath = $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_file($filePath)) {
                require_once $filePath;
            }
        });
    }
}
