<?php

declare(strict_types=1);

namespace app\service\content;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\AboutRepository;
use app\service\log\OperationLogService;
use app\service\translation\SharedTranslationPipelineService;

final class AboutService
{
    public function __construct(
        private readonly AboutRepository $aboutRepository = new AboutRepository(),
        private readonly OperationLogService $operationLogService = new OperationLogService(),
        private readonly SharedTranslationPipelineService $sharedTranslationPipelineService = new SharedTranslationPipelineService()
    )
    {
    }

    public function pages(): array
    {
        return $this->aboutRepository->pages();
    }

    public function page(int $id): array
    {
        $page = $this->aboutRepository->page($id);
        if ($page === null) {
            throw new BusinessException('关于页面不存在', ErrorCode::NOT_FOUND);
        }

        return $page;
    }

    public function bootstrap(?int $preferredId = null): array
    {
        $pages = $this->pages();
        $targetId = $preferredId && $preferredId > 0 ? $preferredId : (int) ($pages[0]['id'] ?? 0);
        $detail = null;

        if ($targetId > 0) {
            try {
                $detail = $this->page($targetId);
            } catch (BusinessException) {
                $detail = null;
            }
        }

        return [
            'pages' => $pages,
            'current_id' => $targetId > 0 ? $targetId : null,
            'detail' => $detail,
        ];
    }

    public function updateBlocks(int $id, array $blocks): array
    {
        $updated = $this->aboutRepository->updateBlocks($id, $blocks);
        if ($updated === null) {
            throw new BusinessException('关于页面不存在', ErrorCode::NOT_FOUND);
        }

        $blockIds = array_values(array_filter(array_map(
            static fn (array $block): int => (int) ($block['id'] ?? 0),
            is_array($updated['blocks'] ?? null) ? $updated['blocks'] : []
        )));
        $this->sharedTranslationPipelineService->syncEntities('about_block', $blockIds);
        $this->operationLogService->recordCurrentAction('about', 'about.blocks.update', 'about_page', $updated, '关于页面区块已更新');

        return $updated;
    }
}
