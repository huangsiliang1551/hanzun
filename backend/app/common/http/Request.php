<?php

declare(strict_types=1);

namespace app\common\http;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $headers,
        private readonly string $requestId,
        private readonly array $routeParams = []
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $query = $_GET;
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[(string) $key] = (string) $value;
        }
        $contentType = '';
        foreach ($normalizedHeaders as $headerName => $headerValue) {
            if (strcasecmp($headerName, 'Content-Type') === 0) {
                $contentType = strtolower(trim($headerValue));
                break;
            }
        }

        $body = $_POST;
        if (str_contains($contentType, 'application/json') || str_contains($contentType, '+json') || str_contains($contentType, 'text/json')) {
            $rawBody = file_get_contents('php://input') ?: '';
            $decoded = json_decode($rawBody, true);
            if ($rawBody !== '' && json_last_error() !== JSON_ERROR_NONE) {
                throw new BusinessException('Malformed JSON request body.', ErrorCode::INVALID_PARAMS);
            }
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self(
            $method,
            rtrim($path, '/') === '' ? '/' : rtrim($path, '/'),
            $query,
            is_array($body) ? $body : [],
            $normalizedHeaders,
            bin2hex(random_bytes(8)),
            []
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function header(string $key, string $default = ''): string
    {
        foreach ($this->headers as $headerName => $headerValue) {
            if (strcasecmp($headerName, $key) === 0) {
                return $headerValue;
            }
        }

        return $default;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function routeParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function file(string $key = 'file'): array
    {
        $file = $_FILES[$key] ?? null;
        if ($file === null || !is_array($file) || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return [];
        }

        return [
            'name' => $file['name'] ?? '',
            'type' => $file['type'] ?? '',
            'tmp_name' => $file['tmp_name'] ?? '',
            'error' => $file['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => $file['size'] ?? 0,
        ];
    }

    public function hasFile(string $key = 'file'): bool
    {
        $file = $_FILES[$key] ?? null;
        return $file !== null && is_array($file) && !empty($file['tmp_name']) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }

    public function fileErrorCode(string $key = 'file'): int
    {
        $file = $_FILES[$key] ?? null;
        if ($file === null || !is_array($file)) {
            return UPLOAD_ERR_NO_FILE;
        }

        return (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    }

    public function fileErrorMessage(string $key = 'file'): string
    {
        return match ($this->fileErrorCode($key)) {
            UPLOAD_ERR_OK => '',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                '上传文件超过服务器当前限制，请控制在 %s 以内后重试。',
                self::formatIniSize(ini_get('upload_max_filesize') ?: ini_get('post_max_size') ?: '8M')
            ),
            UPLOAD_ERR_PARTIAL => '文件上传未完成，请重新上传。',
            UPLOAD_ERR_NO_FILE => $this->contentLengthExceededMessage() ?: '请选择要上传的文件。',
            UPLOAD_ERR_NO_TMP_DIR => '服务器临时目录缺失，暂时无法上传文件。',
            UPLOAD_ERR_CANT_WRITE => '服务器写入上传文件失败，请稍后重试。',
            UPLOAD_ERR_EXTENSION => '文件上传被服务器扩展中止，请检查上传配置。',
            default => '文件上传失败，请稍后重试。',
        };
    }

    private function contentLengthExceededMessage(): string
    {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMaxSize = ini_get('post_max_size') ?: '';
        $postMaxBytes = self::iniSizeToBytes($postMaxSize);

        if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
            return sprintf('上传内容超过服务器当前限制，请控制在 %s 以内后重试。', self::formatIniSize($postMaxSize));
        }

        return '';
    }

    private static function formatIniSize(string $value): string
    {
        $normalized = strtoupper(trim($value));
        if ($normalized === '') {
            return '8M';
        }

        return $normalized;
    }

    private static function iniSizeToBytes(string $value): int
    {
        $normalized = strtoupper(trim($value));
        if ($normalized === '') {
            return 0;
        }

        $unit = substr($normalized, -1);
        $number = (float) $normalized;

        return match ($unit) {
            'G' => (int) round($number * 1024 * 1024 * 1024),
            'M' => (int) round($number * 1024 * 1024),
            'K' => (int) round($number * 1024),
            default => (int) round((float) $normalized),
        };
    }

    /**
     * @param array<string, string> $routeParams
     */
    public function withRouteParams(array $routeParams): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->query,
            $this->body,
            $this->headers,
            $this->requestId,
            $routeParams
        );
    }
}
