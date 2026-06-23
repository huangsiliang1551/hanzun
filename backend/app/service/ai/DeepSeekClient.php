<?php

declare(strict_types=1);

namespace app\service\ai;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\DeepSeekLogRepository;
use app\repository\SystemSettingRepository;

final class DeepSeekClient
{
    public function __construct(
        private readonly SystemSettingRepository $systemSettingRepository = new SystemSettingRepository(),
        private readonly DeepSeekLogRepository $deepSeekLogRepository = new DeepSeekLogRepository()
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<string, mixed>
     */
    public function jsonChat(array $messages, ?string $featureFlag = null): array
    {
        $start = microtime(true);
        $attempts = 0;
        $statusCode = 0;
        $config = $this->config();
        $featureCode = $this->normalizeFeatureCode($featureFlag);

        try {
            if ($featureFlag !== null && (int) ($config[$featureFlag] ?? 0) !== 1) {
                throw new BusinessException('dashscope feature disabled', ErrorCode::INVALID_PARAMS);
            }

            $apiKey = trim((string) ($config['api_key'] ?? ''));
            if ($apiKey === '') {
                throw new BusinessException('dashscope api key missing', ErrorCode::INVALID_PARAMS);
            }

            $payload = [
                'model' => (string) ($config['model'] ?? 'qwen-plus'),
                'messages' => $this->ensureJsonKeyword($messages),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
            ];

            $result = $this->request(
                rtrim((string) ($config['base_url'] ?? 'https://dashscope.aliyuncs.com/compatible-mode/v1'), '/') . '/chat/completions',
                $apiKey,
                $payload,
                (int) ($config['timeout_seconds'] ?? 90),
                max(1, (int) ($config['retry_times'] ?? 0) + 1),
                $attempts,
                $statusCode
            );

            $content = $result['choices'][0]['message']['content'] ?? null;
            if (!is_string($content) || trim($content) === '') {
                throw new BusinessException('dashscope empty response', ErrorCode::INTERNAL_ERROR);
            }

            $decoded = $this->decodeJsonContent($content);
            $this->recordCallLog($featureCode, (string) ($config['model'] ?? 'qwen-plus'), 1, $statusCode > 0 ? $statusCode : 200, $attempts, '', $start);

            return $decoded;
        } catch (BusinessException $exception) {
            $this->recordCallLog($featureCode, (string) ($config['model'] ?? 'qwen-plus'), 0, $statusCode > 0 ? $statusCode : 500, $attempts, $exception->getMessage(), $start);
            throw $exception;
        }
    }

    private function config(): array
    {
        return $this->systemSettingRepository->deepseekConfig();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function request(string $url, string $apiKey, array $payload, int $timeoutSeconds, int $maxAttempts, int &$attempts, int &$statusCode): array
    {
        $lastException = null;
        $requestUrls = $this->candidateCompatibleUrls($url);
        $attemptCounter = 0;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            foreach ($requestUrls as $index => $requestUrl) {
                $attemptCounter++;
                $attempts = $attemptCounter;
                try {
                    return $this->requestOnce($requestUrl, $apiKey, $payload, $timeoutSeconds, $statusCode);
                } catch (BusinessException $exception) {
                    $lastException = $exception;
                    $canTryNextRegion = $index < count($requestUrls) - 1 && $this->shouldRetryAlternateCompatibleUrl($exception);
                    if ($canTryNextRegion) {
                        continue;
                    }

                    if ($attempt === $maxAttempts) {
                        throw $exception;
                    }
                }
            }
        }

        throw $lastException ?? new BusinessException('dashscope request failed', ErrorCode::INTERNAL_ERROR);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function requestOnce(string $url, string $apiKey, array $payload, int $timeoutSeconds, int &$statusCode): array
    {
        $caBundle = $this->resolveCaBundlePath();

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $options = [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];
            if ($caBundle !== null) {
                $options[CURLOPT_CAINFO] = $caBundle;
            }
            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if (!is_string($response) || $response === '') {
                if ($statusCode >= 400) {
                    throw new BusinessException('http ' . $statusCode, ErrorCode::INTERNAL_ERROR);
                }

                throw new BusinessException($error !== '' ? $error : 'dashscope request failed', ErrorCode::INTERNAL_ERROR);
            }

            $decoded = json_decode($response, true);
            if ($statusCode >= 400) {
                $message = is_array($decoded)
                    ? (string) (($decoded['error']['message'] ?? $decoded['message'] ?? 'dashscope request failed'))
                    : 'dashscope request failed';
                throw new BusinessException($message, ErrorCode::INTERNAL_ERROR);
            }

            return is_array($decoded) ? $decoded : [];
        }

        $contextOptions = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ]),
                'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'timeout' => $timeoutSeconds,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];
        if ($caBundle !== null) {
            $contextOptions['ssl']['cafile'] = $caBundle;
        }
        $context = stream_context_create($contextOptions);

        $response = @file_get_contents($url, false, $context);
        if (!is_string($response) || $response === '') {
            if ($statusCode >= 400) {
                throw new BusinessException('http ' . $statusCode, ErrorCode::INTERNAL_ERROR);
            }

            throw new BusinessException('dashscope request failed', ErrorCode::INTERNAL_ERROR);
        }

        $decoded = json_decode($response, true);
        $statusCode = $this->extractHttpStatusCode($http_response_header ?? []);
        if ($statusCode >= 400) {
            $message = is_array($decoded)
                ? (string) (($decoded['error']['message'] ?? $decoded['message'] ?? 'dashscope request failed'))
                : 'dashscope request failed';
            throw new BusinessException($message, ErrorCode::INTERNAL_ERROR);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonContent(string $content): array
    {
        $decoded = json_decode(trim($content), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $content, $matches) === 1) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new BusinessException('dashscope json parse failed', ErrorCode::INTERNAL_ERROR);
    }

    /**
     * @param array<int, string> $headers
     */
    private function extractHttpStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function normalizeFeatureCode(?string $featureFlag): string
    {
        return match ($featureFlag) {
            'translation_enabled' => 'translation',
            'seo_enabled', 'seo_generate_enabled' => 'seo',
            default => 'chat',
        };
    }

    /**
     * @return array<int, string>
     */
    private function candidateCompatibleUrls(string $url): array
    {
        $urls = [$url];

        if (str_contains($url, 'https://dashscope.aliyuncs.com/compatible-mode/v1/')) {
            $urls[] = str_replace(
                'https://dashscope.aliyuncs.com/compatible-mode/v1/',
                'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/',
                $url
            );
        } elseif (str_contains($url, 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/')) {
            $urls[] = str_replace(
                'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/',
                'https://dashscope.aliyuncs.com/compatible-mode/v1/',
                $url
            );
        }

        return array_values(array_unique($urls));
    }

    private function shouldRetryAlternateCompatibleUrl(BusinessException $exception): bool
    {
        $message = strtolower(trim($exception->getMessage()));

        return str_contains($message, 'incorrect api key')
            || str_contains($message, 'invalid_api_key')
            || str_contains($message, 'apikey-error');
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function ensureJsonKeyword(array $messages): array
    {
        foreach ($messages as $message) {
            if ($this->messageContainsJsonKeyword($message['content'] ?? null)) {
                return $messages;
            }
        }

        array_unshift($messages, [
            'role' => 'system',
            'content' => 'Return valid JSON only. Reply strictly in JSON.',
        ]);

        return $messages;
    }

    private function messageContainsJsonKeyword(mixed $content): bool
    {
        if (is_string($content)) {
            return stripos($content, 'json') !== false;
        }

        if (is_array($content)) {
            foreach ($content as $item) {
                if ($this->messageContainsJsonKeyword($item)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function resolveCaBundlePath(): ?string
    {
        $candidates = [
            trim((string) ini_get('curl.cainfo')),
            trim((string) ini_get('openssl.cafile')),
            trim((string) env('SSL_CERT_FILE', '')),
            trim((string) env('CURL_CA_BUNDLE', '')),
        ];

        $userProfile = getenv('USERPROFILE');
        if (is_string($userProfile) && $userProfile !== '') {
            $candidates[] = $userProfile . '\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\python\\Lib\\site-packages\\pip\\_vendor\\certifi\\cacert.pem';
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function recordCallLog(string $featureCode, string $model, int $isSuccess, int $statusCode, int $attempts, string $errorMessage, float $startTime): void
    {
        $featureName = match ($featureCode) {
            'translation' => 'Translation',
            'seo' => 'SEO Generation',
            default => 'AI Chat',
        };

        $this->deepSeekLogRepository->append([
            'feature_code' => $featureCode,
            'feature_name' => $featureName,
            'model' => $model,
            'is_success' => $isSuccess,
            'status_code' => $statusCode,
            'duration_ms' => max(1, (int) round((microtime(true) - $startTime) * 1000)),
            'attempts' => max(1, $attempts),
            'error_message' => $errorMessage,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function listModels(string $baseUrl, string $apiKey): array
    {
        $requestUrl = rtrim($baseUrl, '/') . '/models';
        $response = $this->simpleGetRequest($requestUrl, $apiKey);
        if (isset($response['error'])) {
            throw new BusinessException((string) $response['error'], ErrorCode::INTERNAL_ERROR);
        }

        $items = [];
        if (is_array($response['data'] ?? null)) {
            $items = $response['data'];
        } elseif (is_array($response['models'] ?? null)) {
            $items = $response['models'];
        } elseif (is_array($response['items'] ?? null)) {
            $items = $response['items'];
        }

        $models = [];
        $modelMap = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $normalized = [
                'id' => $id,
                'name' => trim((string) ($item['name'] ?? $item['display_name'] ?? $id)),
            ];
            $modelMap[strtolower($id)] = $normalized;
        }

        if ($modelMap !== []) {
            $models = array_values($modelMap);
            usort($models, static function (array $left, array $right): int {
                return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
            });
            return [
                'items' => $models,
                'request_url' => $requestUrl,
                'raw_counts' => [
                    'data' => is_array($response['data'] ?? null) ? count($response['data']) : 0,
                    'models' => is_array($response['models'] ?? null) ? count($response['models']) : 0,
                    'items' => is_array($response['items'] ?? null) ? count($response['items']) : 0,
                ],
            ];
        }

        throw new BusinessException('dashscope models unavailable', ErrorCode::INTERNAL_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function checkBalance(string $baseUrl, string $apiKey): array
    {
        return [
            'balance' => null,
            'message' => '请前往阿里云 DashScope 控制台查看用量与计费信息。',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function simpleGetRequest(string $url, string $apiKey): array
    {
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];
        $caBundle = $this->resolveCaBundlePath();

        $statusCode = 0;
        $curlError = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];
            if ($caBundle !== null) {
                $options[CURLOPT_CAINFO] = $caBundle;
            }
            curl_setopt_array($ch, $options);
            $response = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
        } else {
            $contextOptions = [
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => 15,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ];
            if ($caBundle !== null) {
                $contextOptions['ssl']['cafile'] = $caBundle;
            }
            $context = stream_context_create($contextOptions);
            $response = @file_get_contents($url, false, $context);
            $statusCode = $response === false ? 0 : $this->extractHttpStatusCode($http_response_header ?? []);
        }

        if (!is_string($response) || $response === '') {
            if ($statusCode >= 400) {
                return ['error' => 'http ' . $statusCode];
            }

            $errorMsg = $curlError !== '' ? $curlError : 'empty response';
            return ['error' => $errorMsg];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            if ($statusCode >= 400) {
                return ['error' => 'http ' . $statusCode];
            }

            return ['error' => 'invalid json'];
        }

        if ($statusCode >= 400) {
            $errorMsg = (string) ($decoded['error']['message'] ?? $decoded['message'] ?? 'HTTP ' . $statusCode);
            return ['error' => $errorMsg];
        }

        return $decoded;
    }
}
