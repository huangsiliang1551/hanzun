<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\system\SettingService;

class SettingController extends BaseAdminController
{
    public function __construct(private readonly SettingService $settingService = new SettingService())
    {
    }

    public function account(): array
    {
        return $this->success($this->settingService->accountProfile());
    }

    public function accountBootstrap(): array
    {
        return $this->success($this->settingService->accountBootstrap());
    }

    public function updateAccount(Request $request): array
    {
        return $this->success($this->settingService->updateAccountProfile([
            'nickname' => $request->input('nickname'),
            'email' => $request->input('email'),
            'mobile' => $request->input('mobile'),
            'password' => $request->input('password'),
        ]), [], 'update success');
    }

    public function languages(): array
    {
        return $this->success($this->settingService->languages());
    }

    public function updateLanguages(Request $request): array
    {
        $items = $request->input('items', []);
        return $this->success(
            $this->settingService->updateLanguages(is_array($items) ? $items : []),
            [],
            'update success'
        );
    }

    public function deepseek(): array
    {
        return $this->success($this->settingService->deepseekConfig());
    }

    public function deepseekBootstrap(): array
    {
        return $this->success($this->settingService->deepseekBootstrap());
    }

    public function site(): array
    {
        return $this->success($this->settingService->siteConfig());
    }

    public function siteBootstrap(): array
    {
        return $this->success($this->settingService->siteBootstrap());
    }

    public function sitePhrases(Request $request): array
    {
        return $this->success($this->settingService->sitePhrases([
            'language_code' => $request->input('language_code', ''),
            'keyword' => $request->input('keyword', ''),
            'module' => $request->input('module', ''),
            'status' => $request->input('status', ''),
        ]));
    }

    public function updateSite(Request $request): array
    {
        return $this->success($this->settingService->updateSiteConfig([
            'site_name' => $request->input('site_name'),
            'site_title' => $request->input('site_title'),
            'logo_url' => $request->input('logo_url'),
            'logo_alt' => $request->input('logo_alt'),
            'company_name' => $request->input('company_name'),
            'company_subtitle' => $request->input('company_subtitle'),
            'meta_description' => $request->input('meta_description'),
            'footer_text' => $request->input('footer_text'),
            'language_strategy' => $request->input('language_strategy'),
            'default_language' => $request->input('default_language'),
            'social_linkedin' => $request->input('social_linkedin'),
            'social_youtube' => $request->input('social_youtube'),
            'enterprise_video_url' => $request->input('enterprise_video_url'),
        ]), [], 'update success');
    }

    public function updateSitePhrases(Request $request): array
    {
        $items = $request->input('items', []);

        return $this->success(
            $this->settingService->updateSitePhrases(
                is_array($items) ? $items : [],
                (string) $request->input('language_code', '')
            ),
            [],
            'update success'
        );
    }

    public function updateDeepseek(Request $request): array
    {
        return $this->success($this->settingService->updateDeepseekConfig($this->buildDeepseekPayload($request)), [], 'update success');
    }

    public function testDeepseek(Request $request): array
    {
        return $this->success(
            $this->settingService->testDeepseekConnection($this->buildDeepseekPayload($request)),
            [],
            'test success'
        );
    }

    public function deepseekLogs(): array
    {
        return $this->success($this->settingService->deepseekLogs());
    }

    public function logsBootstrap(Request $request): array
    {
        $operationFilters = [
            'module' => trim((string) $request->input('module', '')),
            'action_point' => trim((string) $request->input('action_point', '')),
            'operator_name' => trim((string) $request->input('operator_name', '')),
            'date_from' => trim((string) $request->input('date_from', '')),
            'date_to' => trim((string) $request->input('date_to', '')),
        ];
        $operationFilters = array_filter($operationFilters, fn ($v) => $v !== '');

        $loginFilters = [
            'username' => trim((string) $request->input('username', '')),
            'date_from' => trim((string) $request->input('login_date_from', '')),
            'date_to' => trim((string) $request->input('login_date_to', '')),
        ];
        $loginFilters = array_filter($loginFilters, fn ($v) => $v !== '');

        return $this->success($this->settingService->logsBootstrap(
            max(1, (int) $request->input('operation_page', '1')),
            max(1, min(100, (int) $request->input('operation_page_size', '10'))),
            $operationFilters,
            max(1, (int) $request->input('login_page', '1')),
            max(1, min(100, (int) $request->input('login_page_size', '10'))),
            $loginFilters
        ));
    }

    public function deepseekModels(): array
    {
        return $this->success($this->settingService->deepseekModels());
    }

    public function deepseekBalance(): array
    {
        return $this->success($this->settingService->deepseekBalance());
    }

    private function buildDeepseekPayload(Request $request): array
    {
        $missing = '__deepseek_api_key_missing__';
        $payload = [
            'base_url' => $request->input('base_url'),
            'model' => $request->input('model'),
            'timeout_seconds' => $request->input('timeout_seconds'),
            'retry_times' => $request->input('retry_times'),
            'chat_enabled' => $request->input('chat_enabled'),
            'translation_enabled' => $request->input('translation_enabled'),
            'seo_enabled' => $request->input('seo_enabled'),
            'knowledge_enabled' => $request->input('knowledge_enabled'),
            'knowledge_top_k' => $request->input('knowledge_top_k'),
            'knowledge_max_chars' => $request->input('knowledge_max_chars'),
            'knowledge_auto_sync_cms' => $request->input('knowledge_auto_sync_cms'),
            'chat_max_history_messages' => $request->input('chat_max_history_messages'),
            'prompts' => $request->input('prompts'),
        ];

        $apiKey = $request->input('api_key', $missing);
        if ($apiKey !== $missing) {
            $payload['api_key'] = $apiKey;
        } else {
            $maskedApiKey = $request->input('api_key_masked', $missing);
            if ($maskedApiKey !== $missing) {
                $payload['api_key'] = $maskedApiKey;
            }
        }

        if (!array_key_exists('api_key', $payload)) {
            return $payload;
        }

        $payload['api_key'] = is_string($payload['api_key']) ? trim($payload['api_key']) : $payload['api_key'];

        return $payload;
    }

    public function operationLogs(Request $request): array
    {
        $page = max(1, (int) $request->input('page', '1'));
        $pageSize = max(1, min(100, (int) $request->input('page_size', '20')));
        $filters = [
            'module' => trim((string) $request->input('module', '')),
            'action_point' => trim((string) $request->input('action_point', '')),
            'operator_name' => trim((string) $request->input('operator_name', '')),
            'date_from' => trim((string) $request->input('date_from', '')),
            'date_to' => trim((string) $request->input('date_to', '')),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== '');

        return $this->success($this->settingService->operationLogs($page, $pageSize, $filters));
    }

    public function loginLogs(Request $request): array
    {
        $page = max(1, (int) $request->input('page', '1'));
        $pageSize = max(1, min(100, (int) $request->input('page_size', '20')));
        $filters = [
            'username' => trim((string) $request->input('username', '')),
            'date_from' => trim((string) $request->input('date_from', '')),
            'date_to' => trim((string) $request->input('date_to', '')),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== '');

        return $this->success($this->settingService->loginLogs($page, $pageSize, $filters));
    }
}
