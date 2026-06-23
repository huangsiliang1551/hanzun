<?php

declare(strict_types=1);

namespace app\adminapi\controller\inquiry;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\inquiry\ConversationService;

class ConversationController extends BaseAdminController
{
    public function __construct(private readonly ConversationService $conversationService = new ConversationService())
    {
    }

    public function index(): array
    {
        return $this->success($this->conversationService->list());
    }

    public function detail(Request $request): array
    {
        return $this->success($this->conversationService->detail((int) $request->routeParam('id')));
    }
}
