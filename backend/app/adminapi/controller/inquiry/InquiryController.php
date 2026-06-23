<?php

declare(strict_types=1);

namespace app\adminapi\controller\inquiry;

use app\adminapi\controller\BaseAdminController;
use app\common\http\Request;
use app\service\content\TeamService;
use app\service\inquiry\InquiryService;
use app\service\system\SettingService;

class InquiryController extends BaseAdminController
{
    public function __construct(
        private readonly InquiryService $inquiryService = new InquiryService(),
        private readonly SettingService $settingService = new SettingService(),
        private readonly TeamService $teamService = new TeamService()
    )
    {
    }

    public function index(Request $request): array
    {
        return $this->success($this->inquiryService->list($this->buildListQuery($request)));
    }

    public function stats(Request $request): array
    {
        return $this->success($this->inquiryService->stats($this->buildListQuery($request)));
    }

    public function export(Request $request): array
    {
        return $this->success($this->inquiryService->export($this->buildListQuery($request)));
    }

    public function detail(Request $request): array
    {
        return $this->success($this->inquiryService->detail((int) $request->routeParam('id')));
    }

    public function update(Request $request): array
    {
        return $this->success(
            $this->inquiryService->update((int) $request->routeParam('id'), [
                'country_code' => $request->input('country_code'),
                'language_code' => $request->input('language_code'),
                'product_interest' => $request->input('product_interest'),
                'solution_interest' => $request->input('solution_interest'),
                'assigned_to' => $request->input('assigned_to'),
                'status' => $request->input('status'),
            ]),
            [],
            'update success'
        );
    }

    public function workbench(Request $request): array
    {
        return $this->success($this->inquiryService->workbench($this->buildWorkbenchQuery($this->buildListQuery($request))));
    }

    public function lookups(): array
    {
        return $this->success([
            'languages' => $this->settingService->languages(),
            'team_members' => $this->teamService->list(),
        ]);
    }

    public function workbenchDetail(Request $request): array
    {
        $recordType = trim((string) ($request->routeParam('type') ?? ''));
        if ($recordType === '') {
            $recordType = trim((string) $request->input('record_type', $request->input('type', '')));
        }

        return $this->success($this->inquiryService->workbenchDetail($recordType, (int) $request->routeParam('id')));
    }

    public function updateArchiveStatus(Request $request): array
    {
        $recordType = trim((string) ($request->routeParam('type') ?? ''));
        if ($recordType === '') {
            $recordType = trim((string) $request->input('record_type', $request->input('type', '')));
        }

        return $this->success(
            $this->inquiryService->updateWorkbenchArchiveStatus(
                $recordType,
                (int) $request->routeParam('id'),
                (string) $request->input('archive_status', 'archived')
            ),
            [],
            'archive status updated'
        );
    }

    public function batchUpdateArchiveStatus(Request $request): array
    {
        $recordType = trim((string) $request->input('record_type', $request->input('type', '')));
        $archiveStatus = (string) $request->input('archive_status', 'archived');
        $result = [];

        foreach ($this->extractIds($request->input('ids', [])) as $id) {
            $result[] = $this->inquiryService->updateWorkbenchArchiveStatus($recordType, $id, $archiveStatus);
        }

        return $this->success(
            [
                'items' => $result,
                'count' => count($result),
            ],
            [],
            'batch archive status updated'
        );
    }

    public function updateStatus(Request $request): array
    {
        return $this->success(
            $this->inquiryService->updateStatus((int) $request->routeParam('id'), (string) $request->input('status', 'new')),
            [],
            'status updated'
        );
    }

    public function batchUpdateStatus(Request $request): array
    {
        $status = (string) $request->input('status', 'new');
        $result = [];

        foreach ($this->extractIds($request->input('ids', [])) as $id) {
            $result[] = $this->inquiryService->updateStatus($id, $status);
        }

        return $this->success(
            [
                'items' => $result,
                'count' => count($result),
            ],
            [],
            'batch status updated'
        );
    }

    public function addFollowUp(Request $request): array
    {
        return $this->success(
            $this->inquiryService->addFollowUp((int) $request->routeParam('id'), [
                'content' => $request->input('content'),
            ]),
            [],
            'follow-up added'
        );
    }

    public function convertConversation(Request $request): array
    {
        return $this->success(
            $this->inquiryService->convertConversationToInquiry((int) $request->routeParam('id'), [
                'country_code' => $request->input('country_code'),
                'language_code' => $request->input('language_code'),
                'product_interest' => $request->input('product_interest'),
                'solution_interest' => $request->input('solution_interest'),
                'assigned_to' => $request->input('assigned_to'),
                'status' => $request->input('status'),
            ]),
            [],
            'conversation converted'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListQuery(Request $request): array
    {
        return [
            'record_type' => $request->input('record_type', $request->input('type')),
            'status' => $request->input('status'),
            'archive_status' => $request->input('archive_status'),
            'country_code' => $request->input('country_code'),
            'language_code' => $request->input('language_code'),
            'source' => $request->input('source'),
            'keyword' => $request->input('keyword'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'page' => $request->input('page'),
            'page_size' => $request->input('page_size'),
            'sort_field' => $request->input('sort_field'),
            'sort_order' => $request->input('sort_order'),
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function buildWorkbenchQuery(array $query): array
    {
        return [
            'record_type' => $query['record_type'] ?? null,
            'status' => $query['status'] ?? null,
            'archive_status' => $query['archive_status'] ?? null,
            'country_code' => $query['country_code'] ?? null,
            'language_code' => $query['language_code'] ?? null,
            'source' => $query['source'] ?? null,
            'keyword' => $query['keyword'] ?? null,
            'date_from' => $query['date_from'] ?? null,
            'date_to' => $query['date_to'] ?? null,
            'page' => $query['page'] ?? null,
            'page_size' => $query['page_size'] ?? null,
        ];
    }

    /**
     * @param mixed $rawIds
     * @return list<int>
     */
    private function extractIds(mixed $rawIds): array
    {
        if (!is_array($rawIds)) {
            return [];
        }

        $ids = [];
        foreach ($rawIds as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
