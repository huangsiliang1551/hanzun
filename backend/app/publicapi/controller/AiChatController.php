<?php

declare(strict_types=1);

namespace app\publicapi\controller;

use app\common\helpers\Sanitizer;
use app\common\http\Request;
use app\service\inquiry\PublicChatService;

final class AiChatController extends BasePublicController
{
    public function __construct(private readonly PublicChatService $publicChatService = new PublicChatService())
    {
    }

    public function track(Request $request): array
    {
        $data = Sanitizer::sanitizeArray([
            'client_id' => $request->input('client_id'),
            'session_code' => $request->input('session_code'),
            'path' => $request->input('path'),
            'title' => $request->input('title'),
            'referrer' => $request->input('referrer'),
            'language' => $request->input('language'),
        ]);

        return $this->success($this->publicChatService->recordVisitorEvent($data));
    }

    public function chat(Request $request): array
    {
        $data = Sanitizer::sanitizeArray([
            'client_id' => $request->input('client_id'),
            'session_code' => $request->input('session_code'),
            'message' => $request->input('message'),
            'path' => $request->input('path'),
            'title' => $request->input('title'),
            'referrer' => $request->input('referrer'),
            'language' => $request->input('language'),
            'utm_source' => $request->input('utm_source'),
            'country_code' => $request->input('country_code'),
        ]);

        return $this->success($this->publicChatService->chat($data));
    }

    public function session(Request $request): array
    {
        $data = Sanitizer::sanitizeArray([
            'client_id' => $request->input('client_id'),
            'session_code' => $request->input('session_code'),
        ]);

        return $this->success($this->publicChatService->session($data));
    }
}
