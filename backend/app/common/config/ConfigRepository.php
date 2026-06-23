<?php

declare(strict_types=1);

namespace app\common\config;

final class ConfigRepository
{
    private static ?self $instance = null;

    /**
     * @var array<string, mixed>
     */
    private array $items = [];

    private bool $loaded = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function load(string $configPath): void
    {
        $files = glob($configPath . DIRECTORY_SEPARATOR . '*.php') ?: [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $this->items[$name] = require $file;
        }
        $this->loaded = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureLoaded();

        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $fallbackPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config';
        if (is_dir($fallbackPath)) {
            $this->load($fallbackPath);
        }
    }
}
