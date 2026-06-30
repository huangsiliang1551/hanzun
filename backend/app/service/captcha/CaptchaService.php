<?php

declare(strict_types=1);

namespace app\service\captcha;

use app\common\exception\BusinessException;

/**
 * Captcha service for admin login.
 *
 * Generates text-based captcha codes stored in runtime storage.
 * Codes are one-time-use with 5-minute expiry.
 */
final class CaptchaService
{
    private const CODE_LENGTH = 4;

    private const EXPIRY_SECONDS = 300; // 5 minutes

    private const STORAGE_DIR = '/runtime/storage/captcha/';

    public function generate(): array
    {
        $storageDir = $this->getStorageDir();
        if (!is_dir($storageDir) && !mkdir($storageDir, 0777, true) && !is_dir($storageDir)) {
            throw new BusinessException('验证码存储目录创建失败', 50001);
        }

        // Generate random alphanumeric code (excluding ambiguous chars)
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $token = bin2hex(random_bytes(16));
        $expiresAt = time() + self::EXPIRY_SECONDS;

        $record = [
            'code' => $code,
            'expires_at' => $expiresAt,
        ];

        file_put_contents(
            $storageDir . $token . '.json',
            json_encode($record, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        // Clean expired captchas
        $this->cleanExpired();

        return [
            'captcha_token' => $token,
            'captcha_code' => $code,
        ];
    }

    public function validate(string $token, string $code): bool
    {
        $storageDir = $this->getStorageDir();
        $filePath = $storageDir . $token . '.json';

        if (!is_file($filePath)) {
            return false;
        }

        $content = @file_get_contents($filePath);
        if ($content === false || $content === '') {
            return false;
        }

        $record = json_decode($content, true);
        if (!is_array($record)) {
            return false;
        }

        // Delete the file regardless (one-time use)
        @unlink($filePath);

        // Check expiry
        if ((int) ($record['expires_at'] ?? 0) < time()) {
            return false;
        }

        // Compare codes (case-insensitive)
        return strtoupper(trim($code)) === strtoupper(trim((string) ($record['code'] ?? '')));
    }

    private function getStorageDir(): string
    {
        return base_path(self::STORAGE_DIR);
    }

    private function cleanExpired(): void
    {
        $storageDir = $this->getStorageDir();
        $files = @scandir($storageDir);
        if (!is_array($files)) {
            return;
        }

        $now = time();
        foreach ($files as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }

            $filePath = $storageDir . $file;
            $content = @file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            $record = json_decode($content, true);
            if (is_array($record) && (int) ($record['expires_at'] ?? 0) < $now) {
                @unlink($filePath);
            }
        }
    }
}
