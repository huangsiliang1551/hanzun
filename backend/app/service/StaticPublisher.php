<?php

declare(strict_types=1);

namespace app\service;

use app\common\bootstrap\Autoloader;
use app\common\bootstrap\EnvLoader;
use app\common\config\ConfigRepository;
use app\common\database\DatabaseManager;
use app\repository\LanguageRepository;
use app\repository\SiteBuildRepository;
use app\repository\SitePhraseRepository;
use app\repository\SitePhraseWorkspaceRepository;
use app\service\content\PublicSiteService;

final class StaticPublisher
{
    private const PARALLEL_WORKER_COUNT = 2;

    private string $projectRoot;
    private string $outputDir;
    private PublicSiteService $siteService;
    private SiteBuildRepository $siteBuildRepository;
    private SitePhraseRepository $sitePhraseRepository;
    private SitePhraseWorkspaceRepository $sitePhraseWorkspaceRepository;
    private LanguageRepository $languageRepository;
    private array $renderCache = [];
    private array $fragmentCache = [];
    private array $resolvedPhraseCache = [];
    private bool $bootstrapped = false;
    private bool $publicUploadsMirrored = false;
    private bool $disablePhraseUsageTracking = false;

    public function __construct()
    {
        $this->projectRoot = dirname(__DIR__, 3);
        $this->outputDir = $this->projectRoot;
        $this->bootstrap();
    }

    public function planJob(array $descriptor): array
    {
        $items = array_map(
            static fn (array $target): array => [
                'language_code' => (string) ($target['language_code'] ?? ''),
                'page_type' => (string) ($target['page_type'] ?? ''),
                'route' => (string) ($target['route'] ?? ''),
                'output_file' => (string) ($target['output_file'] ?? ''),
                'status' => 'queued',
                'error_message' => null,
            ],
            $this->collectTargets(
                (string) ($descriptor['scope'] ?? 'incremental'),
                (string) ($descriptor['trigger_source'] ?? 'manual'),
                (string) ($descriptor['entity_type'] ?? ''),
                (int) ($descriptor['entity_id'] ?? 0),
                $this->resolveLanguageCodes($descriptor['language_codes'] ?? []),
                is_array($descriptor['context'] ?? null) ? $descriptor['context'] : []
            )
        );

        return [
            'items' => $items,
            'total_steps' => count($items),
        ];
    }

    public function executeJob(int $jobId): array
    {
        $job = $this->siteBuildRepository->findJob($jobId);
        if ($job === null) {
            return ['job' => null, 'items' => []];
        }

        $this->publicUploadsMirrored = false;

        $targets = $this->collectTargets(
            (string) ($job['scope'] ?? 'incremental'),
            (string) ($job['trigger_source'] ?? 'manual'),
            (string) ($job['entity_type'] ?? ''),
            (int) ($job['entity_id'] ?? 0),
            $this->resolveLanguageCodes($job['language_codes'] ?? []),
            is_array($job['context'] ?? null) ? $job['context'] : []
        );

        $items = array_map(
            static fn (array $target): array => [
                'language_code' => (string) ($target['language_code'] ?? ''),
                'page_type' => (string) ($target['page_type'] ?? ''),
                'route' => (string) ($target['route'] ?? ''),
                'output_file' => (string) ($target['output_file'] ?? ''),
                'status' => 'queued',
                'error_message' => null,
            ],
            $targets
        );
        $jobItems = $this->siteBuildRepository->listJobItems($jobId);
        if ($this->canReuseJobItems($jobItems, $items)) {
            $this->siteBuildRepository->resetJobItems($jobId);
        } else {
            $this->siteBuildRepository->replaceJobItems($jobId, $items);
            $jobItems = $this->siteBuildRepository->listJobItems($jobId);
        }
        $this->siteBuildRepository->updateJob($jobId, [
            'status' => 'running',
            'total_steps' => count($targets),
            'completed_steps' => 0,
            'progress_percent' => 0,
            'current_step' => 'read_data',
            'error_message' => null,
            'started_at' => date('Y-m-d H:i:s'),
            'finished_at' => null,
        ]);

        $previousDisablePhraseUsageTracking = $this->disablePhraseUsageTracking;
        $this->disablePhraseUsageTracking = true;

        try {
            $failures = [];
            $rendered = [];
            $targetCount = count($targets);
            $scope = (string) ($job['scope'] ?? 'incremental');
            $languageCodes = $this->resolveLanguageCodes($job['language_codes'] ?? []);
            $finalOutputDir = $this->outputDir;
            $stagingOutputDir = null;
            $activePhase = 'read_data';
            $lastProgressFlushAt = microtime(true);

            if ($scope === 'full') {
                $stagingOutputDir = $this->createStagingOutputDir($jobId);
                $this->outputDir = $stagingOutputDir;
                $this->publicUploadsMirrored = false;
                $targets = array_map(fn (array $target): array => $this->retargetOutputFile($target), $targets);
                $jobItems = array_map(
                    static fn (array $target): array => [
                        'language_code' => (string) ($target['language_code'] ?? ''),
                        'page_type' => (string) ($target['page_type'] ?? ''),
                        'route' => (string) ($target['route'] ?? ''),
                        'output_file' => (string) ($target['output_file'] ?? ''),
                        'status' => 'queued',
                        'error_message' => null,
                    ],
                    $targets
                );
                $this->siteBuildRepository->replaceJobItems($jobId, $jobItems);
            }

            if (self::PARALLEL_WORKER_COUNT > 1 && $targetCount > 1) {
                $this->mirrorPublicUploadsToOutput();
                $this->runParallelWorkers($jobId, $targets, $languageCodes, max(2, self::PARALLEL_WORKER_COUNT));
                $jobItems = $this->siteBuildRepository->listJobItems($jobId);
                foreach ($jobItems as $item) {
                    $status = (string) ($item['status'] ?? '');
                    if ($status === 'completed') {
                        $rendered[] = [
                            'route' => (string) ($item['route'] ?? ''),
                            'output_file' => (string) ($item['output_file'] ?? ''),
                            'page_type' => (string) ($item['page_type'] ?? ''),
                            'language_code' => (string) ($item['language_code'] ?? ''),
                        ];
                        continue;
                    }

                    if ($status !== 'failed') {
                        $this->siteBuildRepository->updateJobItem((int) ($item['id'] ?? 0), [
                            'status' => 'failed',
                            'error_message' => 'parallel site build worker exited before rendering this page',
                        ]);
                        $item['status'] = 'failed';
                        $item['error_message'] = 'parallel site build worker exited before rendering this page';
                    }

                    $failures[] = [
                        'route' => (string) ($item['route'] ?? ''),
                        'message' => trim((string) ($item['error_message'] ?? 'parallel site build worker exited before rendering this page')),
                    ];
                }
            } else {
                $this->warmBuildCaches($targets, $languageCodes);

                foreach ($targets as $index => $target) {
                    $itemId = (int) ($jobItems[$index]['id'] ?? 0);
                    $phase = $this->phaseForTarget((string) ($target['page_type'] ?? ''));
                    if ($phase !== $activePhase) {
                        $activePhase = $phase;
                        $this->siteBuildRepository->updateJob($jobId, [
                            'current_step' => $activePhase,
                        ]);
                    }

                    try {
                        $rendered[] = $this->renderAndWriteTarget($target);
                        if ($itemId > 0) {
                            $this->siteBuildRepository->updateJobItem($itemId, [
                                'status' => 'completed',
                                'error_message' => null,
                            ]);
                        }
                    } catch (\Throwable $exception) {
                        $failures[] = [
                            'route' => (string) ($target['route'] ?? ''),
                            'message' => $exception->getMessage(),
                        ];
                        if ($itemId > 0) {
                            $this->siteBuildRepository->updateJobItem($itemId, [
                                'status' => 'failed',
                                'error_message' => $exception->getMessage(),
                            ]);
                        }
                    }

                    $completed = $index + 1;
                    $now = microtime(true);
                    $shouldFlushProgress = $completed >= $targetCount
                        || ($completed % 10) === 0
                        || ($now - $lastProgressFlushAt) >= 1.0;

                    if ($shouldFlushProgress) {
                        $this->siteBuildRepository->updateJob($jobId, [
                            'completed_steps' => $completed,
                            'progress_percent' => $this->runningProgressPercent($completed, max(1, $targetCount)),
                            'current_step' => $completed >= $targetCount ? 'rebuild_sitemap' : 'write_files',
                            'output_summary' => [
                                'rendered_files' => count($rendered),
                                'failed_files' => count($failures),
                            ],
                        ]);
                        $lastProgressFlushAt = $now;
                    }
                }
            }

            if ($scope === 'full' && $stagingOutputDir !== null && $failures === []) {
                try {
                    $this->siteBuildRepository->updateJob($jobId, [
                        'current_step' => 'deploy_outputs',
                        'progress_percent' => 99,
                    ]);
                    $deployFailures = $this->deployFullBuildOutputs($stagingOutputDir, $finalOutputDir, $languageCodes);
                    foreach ($deployFailures as $failure) {
                        $failures[] = $failure;
                    }
                } catch (\Throwable $exception) {
                    $failures[] = [
                        'route' => '[deploy]',
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            if ($stagingOutputDir !== null) {
                $this->outputDir = $finalOutputDir;
                $this->publicUploadsMirrored = false;
                if ($failures === []) {
                    $this->mirrorPublicUploadsToOutput();
                }
                if (is_dir($stagingOutputDir)) {
                    $this->removeDirectory($stagingOutputDir);
                }
            }

            $status = $failures === [] ? 'completed' : 'failed';
            $finalJob = $this->siteBuildRepository->updateJob($jobId, [
                'status' => $status,
                'completed_steps' => $targetCount,
                'progress_percent' => 100,
                'current_step' => $status === 'completed' ? 'completed' : 'failed',
                'error_message' => $failures === [] ? null : implode(' | ', array_map(
                    static fn (array $failure): string => ($failure['route'] ?? '') . ': ' . ($failure['message'] ?? ''),
                    $failures
                )),
                'output_summary' => [
                    'rendered_files' => count($rendered),
                    'failed_files' => count($failures),
                    'failures' => $failures,
                ],
                'finished_at' => date('Y-m-d H:i:s'),
            ]);

            return [
                'job' => $finalJob,
                'items' => $this->siteBuildRepository->listJobItems($jobId),
            ];
        } finally {
            $this->disablePhraseUsageTracking = $previousDisablePhraseUsageTracking;
        }
    }

    public function executeJobWorker(int $jobId): void
    {
        $job = $this->siteBuildRepository->findJob($jobId);
        if ($job === null) {
            return;
        }

        $previousDisablePhraseUsageTracking = $this->disablePhraseUsageTracking;
        $this->disablePhraseUsageTracking = true;

        try {
            $scope = (string) ($job['scope'] ?? 'incremental');
            $languageCodes = $this->resolveLanguageCodes($job['language_codes'] ?? []);
            if ($scope === 'full') {
                $this->outputDir = $this->stagingOutputDirPath($jobId);
            }
            $this->publicUploadsMirrored = true;

            $seedTargets = array_map(
                fn (array $item): array => $this->targetFromJobItem($item),
                $this->siteBuildRepository->listJobItems($jobId)
            );
            $this->warmBuildCaches($seedTargets, $languageCodes);

            while (true) {
                $item = $this->siteBuildRepository->claimNextQueuedJobItem($jobId);
                if ($item === null) {
                    return;
                }

                $target = $this->targetFromJobItem($item);

                try {
                    $this->renderAndWriteTarget($target);
                    $this->siteBuildRepository->updateJobItem((int) ($item['id'] ?? 0), [
                        'status' => 'completed',
                        'error_message' => null,
                    ]);
                } catch (\Throwable $exception) {
                    $this->siteBuildRepository->updateJobItem((int) ($item['id'] ?? 0), [
                        'status' => 'failed',
                        'error_message' => $exception->getMessage(),
                    ]);
                }
            }
        } finally {
            $this->disablePhraseUsageTracking = $previousDisablePhraseUsageTracking;
        }
    }

    private function canReuseJobItems(array $existingItems, array $targets): bool
    {
        if (count($existingItems) !== count($targets)) {
            return false;
        }

        foreach ($targets as $index => $target) {
            $existing = $existingItems[$index] ?? null;
            if (!is_array($existing)) {
                return false;
            }

            if ((string) ($existing['language_code'] ?? '') !== (string) ($target['language_code'] ?? '')) {
                return false;
            }
            if ((string) ($existing['page_type'] ?? '') !== (string) ($target['page_type'] ?? '')) {
                return false;
            }
            if ((string) ($existing['route'] ?? '') !== (string) ($target['route'] ?? '')) {
                return false;
            }
            if ((string) ($existing['output_file'] ?? '') !== (string) ($target['output_file'] ?? '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $targets
     * @param array<int, string> $languageCodes
     */
    private function warmBuildCaches(array $targets, array $languageCodes): void
    {
        $this->cachedSite();

        $languages = $languageCodes !== [] ? $languageCodes : $this->resolveLanguageCodes([]);
        $pageTypes = [];
        foreach ($targets as $target) {
            $pageType = (string) ($target['page_type'] ?? '');
            if ($pageType !== '') {
                $pageTypes[$pageType] = true;
            }
        }

        foreach ($languages as $languageCode) {
            $this->enabledLanguages();
            $this->defaultLanguage();
            $this->publicRuntimeJson();
            $this->loadPublicTemplate();
            $this->cachedNavigation('header', $languageCode);

            if (isset($pageTypes['homepage'])) {
                $this->cachedHomepage($languageCode);
            }
            if (isset($pageTypes['about']) || isset($pageTypes['about_page_alias'])) {
                $this->cachedAbout($languageCode);
            }
            if (isset($pageTypes['contact'])) {
                $this->cachedContact($languageCode);
            }
            if (isset($pageTypes['product_list']) || isset($pageTypes['product_detail']) || isset($pageTypes['sitemap_page'])) {
                $this->cachedCollectionPayload('product', $languageCode);
            }
            if (isset($pageTypes['solution_list']) || isset($pageTypes['solution_detail']) || isset($pageTypes['sitemap_page'])) {
                $this->cachedCollectionPayload('solution', $languageCode);
            }
            if (isset($pageTypes['news_list']) || isset($pageTypes['news_detail']) || isset($pageTypes['sitemap_page'])) {
                $this->cachedCollectionPayload('news', $languageCode);
            }
            if (isset($pageTypes['case_list']) || isset($pageTypes['case_detail']) || isset($pageTypes['sitemap_page'])) {
                $this->cachedCollectionPayload('case', $languageCode);
            }
            if (isset($pageTypes['page_detail']) || isset($pageTypes['sitemap_page'])) {
                $this->cachedCollectionPayload('page', $languageCode);
            }
        }
    }

    private function createStagingOutputDir(int $jobId): string
    {
        $dir = $this->stagingOutputDirPath($jobId);

        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('无法创建构建暂存目录: ' . $dir);
        }
        @chmod($dir, 0777);

        return $dir;
    }

    private function stagingOutputDirPath(int $jobId): string
    {
        return $this->projectRoot
            . '/backend/runtime/storage/site-build-staging/job-'
            . max(1, $jobId);
    }

    private function targetFromJobItem(array $item): array
    {
        $route = (string) ($item['route'] ?? '/');
        $pageType = (string) ($item['page_type'] ?? '');
        $slug = '';

        if (str_contains($pageType, '_detail')) {
            $path = parse_url($route, PHP_URL_PATH);
            $basename = is_string($path) ? basename($path) : '';
            $slug = preg_replace('/\.html$/i', '', $basename) ?? '';
        }

        return [
            'language_code' => (string) ($item['language_code'] ?? ''),
            'page_type' => $pageType,
            'route' => $route,
            'output_file' => (string) ($item['output_file'] ?? ''),
            'entity_slug' => $slug,
        ];
    }

    private function runParallelWorkers(int $jobId, array $targets, array $languageCodes, int $workerCount): void
    {
        $workers = [];
        for ($index = 0; $index < $workerCount; $index++) {
            $workers[] = $this->spawnParallelWorker($jobId);
        }

        $lastProgressFlushAt = microtime(true);
        while ($workers !== []) {
            $counts = $this->siteBuildRepository->jobItemCounts($jobId);
            $processed = (int) ($counts['processed'] ?? 0);
            $failed = (int) ($counts['failed'] ?? 0);
            $completed = (int) ($counts['completed'] ?? 0);
            $total = max(1, (int) ($counts['total'] ?? count($targets)));
            $now = microtime(true);

            if (($now - $lastProgressFlushAt) >= 0.5 || $processed >= $total) {
                $this->siteBuildRepository->updateJob($jobId, [
                    'completed_steps' => $processed,
                    'progress_percent' => $this->runningProgressPercent($processed, $total),
                    'current_step' => $processed >= $total ? 'rebuild_sitemap' : 'write_files',
                    'output_summary' => [
                        'rendered_files' => $completed,
                        'failed_files' => $failed,
                    ],
                ]);
                $lastProgressFlushAt = $now;
            }

            foreach ($workers as $workerIndex => $worker) {
                $status = proc_get_status($worker['process']);
                if ($status['running']) {
                    continue;
                }

                foreach ($worker['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($worker['process']);
                unset($workers[$workerIndex]);
            }

            if ($processed >= $total && $workers === []) {
                break;
            }

            usleep(200000);
        }
    }

    private function spawnParallelWorker(int $jobId): array
    {
        $script = dirname(__DIR__, 2) . '/scripts/site_build_job.php';
        $phpBinary = \trim((string) (defined('PHP_BINARY') ? PHP_BINARY : 'php'));
        $phpBinaryBase = basename((string) $phpBinary);

        if ($phpBinary === '' || \str_contains($phpBinaryBase, 'php-cgi') || \str_starts_with($phpBinaryBase, 'php-fpm')) {
            $phpBinary = 'php';
        }
        if (!\is_file($phpBinary) || !\is_executable($phpBinary)) {
            $phpBinary = 'php';
        }

        $nullDevice = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $command = [
            $phpBinary,
            $script,
            '--job=' . $jobId,
            '--child=1',
        ];
        $descriptorSpec = [
            0 => ['file', $nullDevice, 'r'],
            1 => ['file', $nullDevice, 'w'],
            2 => ['file', $nullDevice, 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, dirname(__DIR__, 2));
        if (!is_resource($process)) {
            throw new \RuntimeException('无法启动并行构建子进程');
        }

        return [
            'process' => $process,
            'pipes' => $pipes,
        ];
    }

    private function retargetOutputFile(array $target): array
    {
        $target['output_file'] = $this->outputPathFromRoute((string) ($target['route'] ?? '/'));

        return $target;
    }

    /**
     * @param array<int, string> $languageCodes
     */
    private function deployFullBuildOutputs(string $stagingDir, string $finalDir, array $languageCodes): array
    {
        $failures = [];

        $codes = array_values(array_unique(array_filter(array_merge(
            $languageCodes,
            $this->allLanguageCodes(),
            $this->stageLanguageDirectories($stagingDir)
        ))));

        foreach ($codes as $languageCode) {
            $sourceDir = $stagingDir . DIRECTORY_SEPARATOR . $languageCode;
            $targetDir = $finalDir . DIRECTORY_SEPARATOR . $languageCode;

            if (is_dir($sourceDir)) {
                try {
                    $this->syncDirectory($sourceDir, $targetDir);
                } catch (\Throwable $exception) {
                    $failures[] = [
                        'route' => '/' . $languageCode . '/',
                        'message' => $exception->getMessage(),
                    ];
                }
                continue;
            }

            if (is_dir($targetDir)) {
                $this->removeDirectory($targetDir);
            }
        }

        foreach ($this->syncRootPublicFiles($stagingDir, $finalDir) as $failure) {
            $failures[] = $failure;
        }

        foreach ($this->syncRootPublicDirectories($stagingDir, $finalDir) as $failure) {
            $failures[] = $failure;
        }

        return $failures;
    }

    private function syncRootPublicFiles(string $stagingDir, string $finalDir): array
    {
        $failures = [];

        foreach ($this->managedRootPublicFiles() as $filename) {
            $sourcePath = $stagingDir . DIRECTORY_SEPARATOR . $filename;
            $targetPath = $finalDir . DIRECTORY_SEPARATOR . $filename;

            if (is_file($sourcePath)) {
                try {
                    $this->copyFileIntoPlace($sourcePath, $targetPath);
                } catch (\Throwable $exception) {
                    $failures[] = [
                        'route' => '/' . $filename,
                        'message' => $exception->getMessage(),
                    ];
                }
                continue;
            }

            if (is_file($targetPath)) {
                if (!@unlink($targetPath)) {
                    $error = error_get_last();
                    $failures[] = [
                        'route' => '/' . $filename,
                        'message' => '无法删除旧文件: ' . $targetPath . ' (' . (is_array($error) && isset($error['message']) ? (string) $error['message'] : 'unlink() failed') . ')',
                    ];
                }
            }
        }

        return $failures;
    }

    private function syncRootPublicDirectories(string $stagingDir, string $finalDir): array
    {
        $failures = [];

        foreach ($this->managedRootPublicDirectories() as $directory) {
            $sourcePath = $stagingDir . DIRECTORY_SEPARATOR . $directory;
            $targetPath = $finalDir . DIRECTORY_SEPARATOR . $directory;

            if (is_dir($sourcePath)) {
                try {
                    $this->syncDirectory($sourcePath, $targetPath);
                } catch (\Throwable $exception) {
                    $failures[] = [
                        'route' => '/' . $directory . '/',
                        'message' => $exception->getMessage(),
                    ];
                }
                continue;
            }

            if (is_dir($targetPath)) {
                try {
                    $this->removeDirectory($targetPath);
                } catch (\Throwable $exception) {
                    $failures[] = [
                        'route' => '/' . $directory . '/',
                        'message' => $exception->getMessage(),
                    ];
                }
            }
        }

        return $failures;
    }

    private function syncDirectory(string $sourceDir, string $targetDir): void
    {
        if (!is_dir($sourceDir)) {
            return;
        }

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('无法创建目标目录: ' . $targetDir);
        }
        @chmod($targetDir, 0777);

        $sourceItems = scandir($sourceDir);
        if (!is_array($sourceItems)) {
            throw new \RuntimeException('无法读取源目录: ' . $sourceDir);
        }

        foreach ($sourceItems as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $item;
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($sourcePath)) {
                $this->syncDirectory($sourcePath, $targetPath);
                continue;
            }

            $this->copyFileIntoPlace($sourcePath, $targetPath);
        }

        $targetItems = scandir($targetDir);
        if (!is_array($targetItems)) {
            return;
        }

        foreach ($targetItems as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $sourceDir . DIRECTORY_SEPARATOR . $item;
            if (file_exists($sourcePath)) {
                continue;
            }

            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($targetPath)) {
                $this->removeDirectory($targetPath);
                continue;
            }

            @unlink($targetPath);
        }
    }

    private function copyFileIntoPlace(string $sourcePath, string $targetPath): void
    {
        $dir = dirname($targetPath);
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('无法创建输出目录: ' . $dir);
        }
        // 强制目标目录权限为 0777，规避 umask 把 mkdir(0777) 实际降为 0755 的问题。
        @chmod($dir, 0777);

        // 若目标文件已存在但权限不允许覆盖（例如历史 root 进程写入留下的 0644 文件），
        // 先尝试 chmod 放宽权限再重试，避免 Permission denied 阻断整站构建。
        if (is_file($targetPath)) {
            $sourceRealPath = realpath($sourcePath);
            $targetRealPath = realpath($targetPath);
            $sameRealPath = $sourceRealPath !== false && $targetRealPath !== false && $sourceRealPath === $targetRealPath;
            $sameInode = @fileinode($sourcePath) !== false
                && @fileinode($targetPath) !== false
                && @fileinode($sourcePath) === @fileinode($targetPath)
                && @filesize($sourcePath) === @filesize($targetPath);
            if ($sameRealPath || $sameInode) {
                return;
            }

            @chmod($targetPath, 0666);
        }

        if (!@copy($sourcePath, $targetPath)) {
            $error = error_get_last();
            $detail = is_array($error) && isset($error['message']) ? (string) $error['message'] : 'copy() failed';
            throw new \RuntimeException('无法部署文件到目标位置: ' . $targetPath . ' (' . $detail . ')');
        }

        // 写入后强制 0666，保证下一次构建/上传覆盖时不再出现权限问题。
        @chmod($targetPath, 0666);
    }

    /**
     * @return array<int, string>
     */
    private function stageLanguageDirectories(string $stagingDir): array
    {
        $items = scandir($stagingDir);
        if (!is_array($items)) {
            return [];
        }

        $codes = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $stagingDir . DIRECTORY_SEPARATOR . $item;
            if (!is_dir($path)) {
                continue;
            }

            $codes[] = strtolower(trim($item));
        }

        return $codes;
    }

    /**
     * @return array<int, string>
     */
    private function managedRootPublicFiles(): array
    {
        return [
            'index.html',
            'robots.txt',
            'sitemap.xml',
            'about.html',
            'contact.html',
            'products.html',
            'solutions.html',
            'news.html',
            'cases.html',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function managedRootPublicDirectories(): array
    {
        return ['uploads'];
    }

    private function mirrorPublicUploadsToOutput(): void
    {
        if ($this->publicUploadsMirrored) {
            return;
        }

        $sourceDir = $this->projectRoot . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($sourceDir)) {
            $this->publicUploadsMirrored = true;
            return;
        }

        $targetDir = $this->outputDir . DIRECTORY_SEPARATOR . 'uploads';
        $sourceRealPath = realpath($sourceDir) ?: $sourceDir;
        $targetRealPath = realpath($targetDir) ?: $targetDir;
        if ($sourceRealPath === $targetRealPath) {
            $this->publicUploadsMirrored = true;
            return;
        }

        $this->syncDirectory($sourceDir, $targetDir);
        $this->publicUploadsMirrored = true;
    }

    private function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        $basePath = $this->projectRoot . '/backend';
        require_once $basePath . '/app/common/bootstrap/helpers.php';
        require_once $basePath . '/app/common/bootstrap/Autoloader.php';
        require_once $basePath . '/app/common/bootstrap/EnvLoader.php';
        require_once $basePath . '/app/common/config/ConfigRepository.php';
        require_once $basePath . '/app/common/database/DatabaseManager.php';
        Autoloader::register($basePath);
        EnvLoader::load($basePath . '/.env');

        $config = ConfigRepository::instance();
        $config->load($basePath . '/config');
        DatabaseManager::instance()->configure(
            $config->get('database.connections.mysql', [])
        );
        putenv('DISABLE_CLI_RUNTIME_STORAGE_FALLBACK=1');
        $_ENV['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';
        $_SERVER['DISABLE_CLI_RUNTIME_STORAGE_FALLBACK'] = '1';
        $this->outputDir = $this->resolveOutputDir((string) env('STATIC_PUBLISH_OUTPUT_DIR', $this->projectRoot));

        $this->siteService = new PublicSiteService();
        $this->siteBuildRepository = new SiteBuildRepository();
        $this->sitePhraseRepository = new SitePhraseRepository();
        $this->sitePhraseWorkspaceRepository = new SitePhraseWorkspaceRepository();
        $this->languageRepository = new LanguageRepository();
        $this->bootstrapped = true;
    }

    private function renderAndWriteTarget(array $target): array
    {
        $pageType = (string) ($target['page_type'] ?? '');
        $route = (string) ($target['route'] ?? '/');
        $languageCode = (string) ($target['language_code'] ?? 'zh');
        $slug = (string) ($target['entity_slug'] ?? '');
        $outputFile = (string) ($target['output_file'] ?? '');

        $content = match ($pageType) {
            'root_redirect' => $this->renderRootRedirectPage(),
            'sitemap' => $this->renderSitemapXml(),
            'robots' => $this->siteService->robotsTxt(),
            'homepage' => $this->renderHomepagePage($languageCode, $route),
            'about' => $this->renderAboutPage($languageCode, $route),
            'about_page_alias' => $this->renderAboutPage($languageCode, $route),
            'contact' => $this->renderContactPage($languageCode, $route),
            'sitemap_page' => $this->renderSitemapPage($languageCode, $route),
            'product_list' => $this->renderListingPage('product', $languageCode, $route),
            'solution_list' => $this->renderListingPage('solution', $languageCode, $route),
            'news_list' => $this->renderListingPage('news', $languageCode, $route),
            'case_list' => $this->renderListingPage('case', $languageCode, $route),
            'product_detail' => $this->renderDetailPage('product', $languageCode, $slug, $route),
            'solution_detail' => $this->renderDetailPage('solution', $languageCode, $slug, $route),
            'news_detail' => $this->renderDetailPage('news', $languageCode, $slug, $route),
            'case_detail' => $this->renderDetailPage('case', $languageCode, $slug, $route),
            'page_detail' => $this->renderDetailPage('page', $languageCode, $slug, $route),
            default => '',
        };

        if ($content === '') {
            throw new \RuntimeException('不支持的页面类型: ' . $pageType);
        }

        $this->mirrorPublicUploadsToOutput();
        $this->writeOutput($outputFile, $content);

        return [
            'route' => $route,
            'output_file' => $outputFile,
            'page_type' => $pageType,
            'language_code' => $languageCode,
        ];
    }

    private function collectTargets(
        string $scope,
        string $triggerSource,
        string $entityType,
        int $entityId,
        array $languageCodes,
        array $context
    ): array {
        if ($scope === 'full' || in_array($entityType, ['site_settings', 'navigation', 'contact_settings', 'language_settings', 'ad'], true)) {
            return $this->collectFullTargets($languageCodes);
        }

        if (($context['force_full'] ?? false) === true || $triggerSource === 'manual_full_rebuild') {
            return $this->collectFullTargets($languageCodes);
        }

        $targets = [
            $this->rootTarget('sitemap'),
            $this->rootTarget('robots'),
        ];

        foreach ($languageCodes as $languageCode) {
            switch ($entityType) {
                case 'homepage':
                    $targets[] = $this->pageTarget($languageCode, 'homepage', $this->localizedRoute($languageCode, 'index'));
                    break;

                case 'product':
                    $targets[] = $this->pageTarget($languageCode, 'homepage', $this->localizedRoute($languageCode, 'index'));
                    $targets[] = $this->pageTarget($languageCode, 'product_list', $this->localizedRoute($languageCode, 'products'));
                    $record = $this->findPublishedRecord('product', $entityId, $languageCode);
                    if ($record !== null) {
                        $targets[] = $this->pageTarget($languageCode, 'product_detail', $this->localizedRoute($languageCode, 'products/' . (string) $record['slug'] . '.html'), (string) $record['slug']);
                    }
                    break;

                case 'solution':
                    $targets[] = $this->pageTarget($languageCode, 'homepage', $this->localizedRoute($languageCode, 'index'));
                    $targets[] = $this->pageTarget($languageCode, 'solution_list', $this->localizedRoute($languageCode, 'solutions'));
                    $record = $this->findPublishedRecord('solution', $entityId, $languageCode);
                    if ($record !== null) {
                        $targets[] = $this->pageTarget($languageCode, 'solution_detail', $this->localizedRoute($languageCode, 'solutions/' . (string) $record['slug'] . '.html'), (string) $record['slug']);
                    }
                    break;

                case 'news':
                    $targets[] = $this->pageTarget($languageCode, 'homepage', $this->localizedRoute($languageCode, 'index'));
                    $targets[] = $this->pageTarget($languageCode, 'news_list', $this->localizedRoute($languageCode, 'news'));
                    $record = $this->findPublishedRecord('news', $entityId, $languageCode);
                    if ($record !== null) {
                        $targets[] = $this->pageTarget($languageCode, 'news_detail', $this->localizedRoute($languageCode, 'news/' . (string) $record['slug'] . '.html'), (string) $record['slug']);
                    }
                    break;

                case 'case':
                    $targets[] = $this->pageTarget($languageCode, 'homepage', $this->localizedRoute($languageCode, 'index'));
                    $targets[] = $this->pageTarget($languageCode, 'case_list', $this->localizedRoute($languageCode, 'cases'));
                    $record = $this->findPublishedRecord('case', $entityId, $languageCode);
                    if ($record !== null) {
                        $targets[] = $this->pageTarget($languageCode, 'case_detail', $this->localizedRoute($languageCode, 'cases/' . (string) $record['slug'] . '.html'), (string) $record['slug']);
                    }
                    break;

                case 'page':
                    $record = $this->findPublishedRecord('page', $entityId, $languageCode);
                    if ($record !== null) {
                        $targets[] = $this->pageTarget($languageCode, 'page_detail', $this->localizedRoute($languageCode, 'pages/' . (string) $record['slug'] . '.html'), (string) $record['slug']);
                    }
                    break;

                case 'about':
                case 'team':
                case 'certificate':
                case 'contact':
                    $targets[] = $this->pageTarget($languageCode, 'homepage', $this->localizedRoute($languageCode, 'index'));
                    $targets[] = $this->pageTarget($languageCode, 'about', $this->localizedRoute($languageCode, 'about'));
                    $targets[] = $this->pageTarget($languageCode, 'about_page_alias', $this->localizedRoute($languageCode, 'pages/about-us.html'));
                    $targets[] = $this->pageTarget($languageCode, 'contact', $this->localizedRoute($languageCode, 'contact'));
                    break;

                default:
                    $targets[] = $this->pageTarget($languageCode, 'homepage', $this->localizedRoute($languageCode, 'index'));
                    break;
            }
        }

        return $this->uniqueTargets($targets);
    }

    private function collectFullTargets(array $languageCodes): array
    {
        $targets = [
            $this->rootTarget('root_redirect'),
            $this->rootTarget('sitemap'),
            $this->rootTarget('robots'),
        ];

        foreach ($languageCodes as $languageCode) {
            $targets[] = $this->pageTarget($languageCode, 'homepage', $this->localizedRoute($languageCode, 'index'));
            $targets[] = $this->pageTarget($languageCode, 'about', $this->localizedRoute($languageCode, 'about'));
            $targets[] = $this->pageTarget($languageCode, 'about_page_alias', $this->localizedRoute($languageCode, 'pages/about-us.html'));
            $targets[] = $this->pageTarget($languageCode, 'contact', $this->localizedRoute($languageCode, 'contact'));
            $targets[] = $this->pageTarget($languageCode, 'sitemap_page', $this->localizedRoute($languageCode, 'sitemap'));
            $targets[] = $this->pageTarget($languageCode, 'product_list', $this->localizedRoute($languageCode, 'products'));
            $targets[] = $this->pageTarget($languageCode, 'solution_list', $this->localizedRoute($languageCode, 'solutions'));
            $targets[] = $this->pageTarget($languageCode, 'news_list', $this->localizedRoute($languageCode, 'news'));
            $targets[] = $this->pageTarget($languageCode, 'case_list', $this->localizedRoute($languageCode, 'cases'));

            foreach ($this->cachedCollectionItems('product', $languageCode) as $item) {
                $targets[] = $this->pageTarget($languageCode, 'product_detail', $this->localizedRoute($languageCode, 'products/' . (string) ($item['slug'] ?? '') . '.html'), (string) ($item['slug'] ?? ''));
            }
            foreach ($this->cachedCollectionItems('solution', $languageCode) as $item) {
                $targets[] = $this->pageTarget($languageCode, 'solution_detail', $this->localizedRoute($languageCode, 'solutions/' . (string) ($item['slug'] ?? '') . '.html'), (string) ($item['slug'] ?? ''));
            }
            foreach ($this->cachedCollectionItems('news', $languageCode) as $item) {
                $targets[] = $this->pageTarget($languageCode, 'news_detail', $this->localizedRoute($languageCode, 'news/' . (string) ($item['slug'] ?? '') . '.html'), (string) ($item['slug'] ?? ''));
            }
            foreach ($this->cachedCollectionItems('case', $languageCode) as $item) {
                $targets[] = $this->pageTarget($languageCode, 'case_detail', $this->localizedRoute($languageCode, 'cases/' . (string) ($item['slug'] ?? '') . '.html'), (string) ($item['slug'] ?? ''));
            }
            foreach ($this->publishedStandalonePages($languageCode) as $item) {
                $targets[] = $this->pageTarget($languageCode, 'page_detail', $this->localizedRoute($languageCode, 'pages/' . (string) ($item['slug'] ?? '') . '.html'), (string) ($item['slug'] ?? ''));
            }
        }

        return $this->uniqueTargets($targets);
    }

    private function rootTarget(string $pageType): array
    {
        $route = match ($pageType) {
            'root_redirect' => '/index.html',
            'sitemap' => '/sitemap.xml',
            'robots' => '/robots.txt',
            default => '/',
        };

        return [
            'language_code' => '',
            'page_type' => $pageType,
            'route' => $route,
            'output_file' => $this->outputPathFromRoute($route),
            'entity_slug' => '',
        ];
    }

    public function buildHomepage(string $languageCode): array
    {
        $route = $this->localizedRoute($languageCode, 'index');
        return $this->renderAndWriteTarget($this->pageTarget($languageCode, 'homepage', $route));
    }

    public function buildListPage(string $entityType, string $languageCode): array
    {
        $typeMap = [
            'product' => 'product_list',
            'solution' => 'solution_list',
            'news' => 'news_list',
            'case' => 'case_list',
        ];
        $routeMap = [
            'product' => 'products',
            'solution' => 'solutions',
            'news' => 'news',
            'case' => 'cases',
        ];
        if (!isset($typeMap[$entityType], $routeMap[$entityType])) {
            throw new \InvalidArgumentException('Unsupported list entity type: ' . $entityType);
        }

        return $this->renderAndWriteTarget(
            $this->pageTarget($languageCode, $typeMap[$entityType], $this->localizedRoute($languageCode, $routeMap[$entityType]))
        );
    }

    public function buildDetailPage(string $entityType, string $slug, string $languageCode): array
    {
        $typeMap = [
            'product' => 'product_detail',
            'solution' => 'solution_detail',
            'news' => 'news_detail',
            'case' => 'case_detail',
            'page' => 'page_detail',
        ];
        $routeMap = [
            'product' => 'products',
            'solution' => 'solutions',
            'news' => 'news',
            'case' => 'cases',
            'page' => 'pages',
        ];
        if (!isset($typeMap[$entityType], $routeMap[$entityType])) {
            throw new \InvalidArgumentException('Unsupported detail entity type: ' . $entityType);
        }

        return $this->renderAndWriteTarget(
            $this->pageTarget(
                $languageCode,
                $typeMap[$entityType],
                $this->localizedRoute($languageCode, $routeMap[$entityType] . '/' . trim($slug) . '.html'),
                trim($slug)
            )
        );
    }

    public function buildStandalonePage(string $slug, string $languageCode): array
    {
        $slug = trim($slug);
        return $this->renderAndWriteTarget(
            $this->pageTarget($languageCode, 'page_detail', $this->localizedRoute($languageCode, 'pages/' . $slug . '.html'), $slug)
        );
    }

    public function buildSiteMap(): array
    {
        return $this->renderAndWriteTarget($this->rootTarget('sitemap'));
    }

    public function buildRobots(): array
    {
        return $this->renderAndWriteTarget($this->rootTarget('robots'));
    }

    private function pageTarget(string $languageCode, string $pageType, string $route, string $entitySlug = ''): array
    {
        return [
            'language_code' => $languageCode,
            'page_type' => $pageType,
            'route' => $route,
            'output_file' => $this->outputPathFromRoute($route),
            'entity_slug' => $entitySlug,
        ];
    }

    private function localizedRoute(string $languageCode, string $path): string
    {
        $normalized = trim($path, '/');
        $filePath = match ($normalized) {
            '', 'index' => 'index.html',
            'about' => 'about.html',
            'contact' => 'contact.html',
            'sitemap' => 'sitemap.html',
            'products' => 'products.html',
            'solutions' => 'solutions.html',
            'news' => 'news.html',
            'cases' => 'cases.html',
            default => $normalized,
        };

        if (!str_ends_with($filePath, '.html')) {
            $filePath .= '.html';
        }

        return '/' . trim($languageCode, '/') . '/' . $filePath;
    }

    private function renderRootRedirectPage(): string
    {
        $codes = array_map(static fn (array $item): string => (string) ($item['code'] ?? ''), $this->enabledLanguages());
        $defaultLanguage = $this->defaultLanguage();
        $defaultRoute = htmlspecialchars('/' . $defaultLanguage . '/index.html', ENT_QUOTES, 'UTF-8');
        $jsonCodes = json_encode($codes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting...</title>
    <meta http-equiv="refresh" content="0; url={$defaultRoute}">
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f8fafc;color:#0f172a;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif}
        .shell{padding:24px 28px;border-radius:18px;background:#fff;box-shadow:0 24px 60px rgba(15,23,42,.08);text-align:center}
        .shell p{margin:0;color:#475569}
    </style>
</head>
<body>
    <div class="shell">
        <p>Redirecting...</p>
        <noscript><a href="{$defaultRoute}">Continue</a></noscript>
    </div>
    <script>
        (function(){
            var enabled = {$jsonCodes};
            var raw = String(navigator.language || navigator.userLanguage || '{$defaultLanguage}').toLowerCase();
            var code = raw.split('-')[0];
            if (enabled.indexOf(code) === -1) { code = '{$defaultLanguage}'; }
            window.location.replace('/' + code + '/index.html');
        })();
    </script>
</body>
</html>
HTML;
    }

    private function renderSitemapXml(): string
    {
        $baseUrl = rtrim((string) env('APP_URL', 'https://bagelsmachinery.com'), '/');
        $languages = $this->resolveLanguageCodes([]);
        $routes = [];

        foreach ($languages as $languageCode) {
            $routes[] = $this->localizedRoute($languageCode, 'index');
            $routes[] = $this->localizedRoute($languageCode, 'about');
            $routes[] = $this->localizedRoute($languageCode, 'contact');
            $routes[] = $this->localizedRoute($languageCode, 'products');
            $routes[] = $this->localizedRoute($languageCode, 'solutions');
            $routes[] = $this->localizedRoute($languageCode, 'news');
            $routes[] = $this->localizedRoute($languageCode, 'cases');

            foreach ($this->cachedCollectionItems('product', $languageCode) as $item) {
                $routes[] = $this->localizedRoute($languageCode, 'products/' . (string) ($item['slug'] ?? ''));
            }
            foreach ($this->cachedCollectionItems('solution', $languageCode) as $item) {
                $routes[] = $this->localizedRoute($languageCode, 'solutions/' . (string) ($item['slug'] ?? ''));
            }
            foreach ($this->cachedCollectionItems('news', $languageCode) as $item) {
                $routes[] = $this->localizedRoute($languageCode, 'news/' . (string) ($item['slug'] ?? ''));
            }
            foreach ($this->cachedCollectionItems('case', $languageCode) as $item) {
                $routes[] = $this->localizedRoute($languageCode, 'cases/' . (string) ($item['slug'] ?? ''));
            }
            foreach ($this->publishedStandalonePages($languageCode) as $item) {
                $routes[] = $this->localizedRoute($languageCode, 'pages/' . (string) ($item['slug'] ?? ''));
            }
        }

        $routes = array_values(array_unique($routes));
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($routes as $route) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . $escape($baseUrl . $route) . "</loc>\n";
            foreach ($languages as $languageCode) {
                $alternate = $this->alternateRouteForLanguage($route, $languageCode);
                $xml .= '    <xhtml:link rel="alternate" hreflang="' . $escape($languageCode) . '" href="' . $escape($baseUrl . $alternate) . "\" />\n";
            }
            $defaultAlternate = $this->alternateRouteForLanguage($route, $this->defaultLanguage());
            $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $escape($baseUrl . $defaultAlternate) . "\" />\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';

        return $xml;
    }

    private function renderHomepagePage(string $languageCode, string $route): string
    {
        $site = $this->cachedSite($languageCode);
        $homepage = $this->cachedHomepage($languageCode);
        $about = $this->cachedAbout($languageCode);
        $intro = trim((string) ($this->extractAboutIntro($about) ?: ($site['meta_description'] ?? '')));
        $heroImage = trim((string) ($site['hero_image_url'] ?? '')) !== '' ? (string) $site['hero_image_url'] : '/assets/videos/home/hero-enterprise-showcase.webm';

        // Pull real backend data, filter by is_home_featured + has cover image
        $products = $this->filterHomeFeaturedWithCover($this->cachedCollectionItems('product', $languageCode));
        $solutions = $this->filterHomeFeaturedWithCover($this->cachedCollectionItems('solution', $languageCode));
        $news = $this->filterHomeFeaturedWithCover($this->cachedCollectionItems('news', $languageCode));
        $cases = $this->filterHomeFeaturedWithCover($this->cachedCollectionItems('case', $languageCode));
        return $this->renderShellPage($languageCode, $route, [
            'title' => $this->homepageTitle($languageCode),
            'description' => $intro !== '' ? $intro : (string) ($site['meta_description'] ?? ''),
            'og_image' => $this->assetUrl($heroImage),
            'structured_data_nodes' => $this->homepageStructuredDataNodes($languageCode, $route),
            'template_replacements' => [
                '{{hero_image_url}}' => $this->escape($this->assetUrl($heroImage)),
                '{{homepage_video_support_html}}' => $this->renderHomepageVideoSupportHtmlV2($site, $languageCode),
                '{{featured_solutions_html}}' => $this->renderHomepageSolutionsHtml($solutions),
                '{{featured_products_html}}' => $this->renderHomepageProductsHtml($products, $languageCode),
                '{{homepage_notice_html}}' => $this->renderHomepageNoticeHtml($site, $languageCode),
                '{{homepage_metrics_html}}' => $this->renderHomepageMetricsHtml($about, $languageCode),
                '{{featured_cases_html}}' => $this->renderHomepageCasesHtml($cases, $languageCode),
                '{{featured_news_html}}' => $this->renderHomepageNewsHtml($news, $languageCode),
                '{{homepage_sales_html}}' => $this->renderHomepageSalesHtml($about, $languageCode),
                '{{homepage_contact_html}}' => $this->renderHomepageContactHtmlV2($languageCode),
                '{{footer_contact_html}}' => $this->renderFooterContactCardsHtml($languageCode),
                '{{footer_featured_products_html}}' => $this->renderFooterFeaturedLinksHtml($this->phrase('footer_popular_products', $languageCode, 'Popular Products'), array_slice($products, 0, 5), 'product', $languageCode),
                '{{footer_featured_solutions_html}}' => $this->renderFooterFeaturedLinksHtml($this->phrase('footer_popular_solutions', $languageCode, 'Popular Solutions'), array_slice($solutions, 0, 5), 'solution', $languageCode),
            ],
        ]);
    }

    private function renderAboutPage(string $languageCode, string $route): string
    {
        $site = $this->cachedSite($languageCode);
        $about = $this->cachedAbout($languageCode);
        $isAboutAliasPage = str_contains($route, '/pages/about-us.html');
        $aboutAliasTitle = $languageCode === 'zh' ? $this->unicodeText('\u516c\u53f8\u4ecb\u7ecd') : 'About Us';
        $intro = $this->extractAboutIntro($about);
        $image = trim((string) ($this->extractAboutImage($about) ?: '/assets/images/home/company-service-team-real.jpg'));
        $blocks = is_array($about['blocks'] ?? null) ? $about['blocks'] : [];
        $team = $this->extractAboutItems($blocks, ['team', 'team_list']);
        $certificates = $this->extractAboutItems($blocks, ['certificate', 'certificate_list']);
        if ($intro === '') {
            $intro = trim((string) ($site['meta_description'] ?? ''));
        }

        $main = '<main class="about-main">';
        if ($isAboutAliasPage) {
            $main .= '<section class="section"><div class="container"><div class="public-listing-hero"><h1>' . $this->escape($aboutAliasTitle) . '</h1><p>' . $this->escape($this->excerpt($intro, 120)) . '</p></div></div></section>';
        }
        $main .= '<section class="section section-metrics" id="about"><div class="container"><article class="metrics-dashboard reveal">';
        $main .= '<figure class="metrics-dashboard-visual"><img src="' . $this->assetUrl($image) . '" alt="' . $this->escape($this->companyName($languageCode)) . '"><figcaption class="metrics-dashboard-intro"><p>' . $this->escape($this->excerpt($intro, 120)) . '</p></figcaption></figure>';
        $main .= '<div class="metrics-dashboard-copy"><div class="metrics-capability-combo">';
            $main .= '<section class="metrics-capability-section metrics-capability-flow"><h3>' . $this->escape($this->phrase('cooperation_flow_title', $languageCode, $languageCode === 'zh' ? '合作流程' : 'Cooperation Flow')) . '</h3><div class="metrics-flow-list">';
        foreach ($this->homepageFlowItems($languageCode) as $item) {
            $main .= '<article class="metrics-flow-item"><span class="metrics-flow-icon" aria-hidden="true">' . ($item['icon'] ?? '') . '</span><div><strong>' . $this->escape((string) ($item['label'] ?? '')) . '</strong></div></article>';
        }
        $main .= '</div></section>';
        if ($certificates !== []) {
            $main .= '<section class="metrics-capability-section metrics-capability-certs"><h3>' . $this->escape($this->phrase('qualifications_title', $languageCode, $languageCode === 'zh' ? '资质证书' : 'Qualifications')) . '</h3><div class="metrics-cert-grid about-certificate-grid">';
            foreach (array_slice($certificates, 0, 5) as $item) {
                $name = trim((string) ($item['name'] ?? 'Certificate'));
                $imageUrl = trim((string) ($item['image_asset_url'] ?? $item['image_url'] ?? $item['cover_image_url'] ?? ''));
                if ($imageUrl === '') {
                    continue;
                }
                $main .= '<article class="metrics-cert-card"><figure class="metrics-cert-media"><img src="' . $this->assetUrl($imageUrl) . '" alt="' . $this->escape($name) . '"></figure><span>' . $this->escape($name) . '</span></article>';
            }
            $main .= '</div></section>';
        }
        $main .= '</div></div></article></div></section>';

        if ($team !== []) {
            $main .= '<section class="section section-sales" id="sales-team"><div class="container"><div class="sales-loop loop-strip" data-loop-strip data-loop-step-delay="1800"><div class="sales-grid sales-track loop-track" data-loop-track>';
            foreach (array_slice($team, 0, 9) as $member) {
                $name = trim((string) ($member['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $main .= '<article class="sales-card"><figure class="sales-avatar"><img src="' . $this->assetUrl((string) ($member['avatar_asset_url'] ?? '')) . '" alt="' . $this->escape($name) . '"><figcaption class="sales-name-bar"><strong>' . $this->escape($name) . '</strong></figcaption></figure><div class="sales-copy">';
                foreach ($this->memberActionLinks($member, $languageCode) as $action) {
                    $classSuffix = $this->escape((string) ($action['key'] ?? 'contact'));
                    $main .= '<a class="sales-contact-link sales-contact-' . $classSuffix . '" href="' . $this->escape((string) $action['href']) . '"' . (str_starts_with((string) $action['href'], 'http') ? ' target="_blank" rel="noopener"' : '') . '>' . $this->escape((string) $action['label']) . '</a>';
                }
                $main .= '</div></article>';
            }
            $main .= '</div></div></div></section>';
        }

        $main .= '</main>';

        return $this->renderShellPage($languageCode, $route, [
            'title' => $this->companyName($languageCode) . ' - ' . ($isAboutAliasPage ? $aboutAliasTitle : $this->phrase('nav_about', $languageCode, 'About')),
            'description' => $intro,
            'main' => $main,
        ]);
    }

    private function renderContactPage(string $languageCode, string $route): string
    {
        $items = [];
        $seenContacts = [];
        foreach ($this->collectScopedContacts($languageCode, ['contact_page', 'footer']) as $contact) {
            $signature = strtolower(trim((string) ($contact['field_key'] ?? ''))) . '|' . trim((string) ($contact['label'] ?? ''));
            if ($signature === '|' || isset($seenContacts[$signature])) {
                continue;
            }

            $seenContacts[$signature] = true;
            $items[] = $contact;
        }

        $main = '<main class="public-listing-main"><section class="section"><div class="container"><div class="public-listing-hero"><h1>' . $this->escape($this->phrase('page_contact', $languageCode, 'Contact')) . '</h1><p>' . $this->escape($this->phrase('footer_contact', $languageCode, 'Contact')) . '</p></div><div class="contact-grid">';
        foreach ($items as $item) {
            $label = trim((string) ($item['kind_label'] ?? $item['label'] ?? 'Contact'));
            $value = trim((string) ($item['label'] ?? ''));
            if ($value === '') {
                continue;
            }
            $main .= '<article class="contact-card"><div class="contact-card-head"><small>' . $this->escape($label) . '</small></div><strong><span>' . $this->escape($value) . '</span></strong></article>';
        }
        $main .= '</div><div class="metrics-dashboard-intro" style="margin-top:24px"><h3>' . $this->escape($this->phrase('contact_page_prompt_title', $languageCode, 'Get Solution')) . '</h3><p>' . $this->escape($this->phrase('contact_page_prompt_copy', $languageCode, 'Leave your project requirements and we will contact you shortly.')) . '</p></div></div></section></main>';

        return $this->renderShellPage($languageCode, $route, [
            'title' => $this->phrase('page_contact', $languageCode, 'Contact') . ' - ' . $this->companyName($languageCode),
            'description' => $this->phrase('footer_contact', $languageCode, 'Contact'),
            'main' => $main,
        ]);
    }

    private function renderSitemapPage(string $languageCode, string $route): string
    {
        $main = '<main class="public-listing-main"><section class="section"><div class="container"><div class="public-listing-hero"><h1>'
            . $this->escape($this->phrase('html_sitemap', $languageCode, 'Site Map'))
            . '</h1><p>'
            . $this->escape($this->phrase('footer_sitemap', $languageCode, 'Site Map'))
            . '</p></div>'
            . $this->renderSitemapPageSections($languageCode)
            . '</div></section></main>';

        return $this->renderShellPage($languageCode, $route, [
            'title' => $this->phrase('html_sitemap', $languageCode, 'Site Map') . ' - ' . $this->companyName($languageCode),
            'description' => $this->phrase('footer_sitemap', $languageCode, 'Site Map'),
            'main' => $main,
            'extra_css' => ['/assets/css/public-listing.css?v=20260611-01'],
        ]);
    }

    private function renderListingPage(string $entityType, string $languageCode, string $route): string
    {
        return $this->renderListingPageV2($entityType, $languageCode, $route);

        $payload = $this->cachedCollectionPayload($entityType, $languageCode);

        $title = match ($entityType) {
            'product' => $this->phrase('page_products', $languageCode, 'Products'),
            'solution' => $this->phrase('page_solutions', $languageCode, 'Solutions'),
            'news' => $this->phrase('page_news', $languageCode, 'News'),
            'case' => $this->phrase('page_cases', $languageCode, 'Cases'),
            default => 'Listing',
        };
        $lead = $this->companyName($languageCode) . ' ' . $title;
        $items = $this->filterRenderableItems(is_array($payload['items'] ?? null) ? $payload['items'] : [], $entityType);
        $categories = $this->filterRenderableCategories(is_array($payload['categories'] ?? null) ? $payload['categories'] : []);
        $filterCategories = $this->uniqueRenderableCategoriesBySlug($categories);
        $baseRoute = $this->localizedRoute($languageCode, $this->listingRouteKeyForEntity($entityType));
        $filterCategories = $this->uniqueRenderableCategoriesBySlug($categories);
        $categoryLookup = $this->categoryLookupById($categories);
        $allLabel = $this->phrase('filter_all', $languageCode, 'All');

        $main = '<main class="public-listing-main"><section class="section"><div class="container"><div class="public-listing-hero"><h1>' . $this->escape($title) . '</h1><p>' . $this->escape($lead) . '</p></div>';
        if ($filterCategories !== []) {
            $main .= '<div class="public-filter-shell"><span class="public-filter-label">' . $this->escape($title) . '</span><div class="public-filter-list"><button class="public-filter-button is-active" type="button">' . $this->escape($allLabel) . '</button>';
            foreach (array_slice($filterCategories, 0, 12) as $category) {
                $main .= '<button class="public-filter-button" type="button">' . $this->escape((string) ($category['name'] ?? $category['name_zh'] ?? '')) . '</button>';
            }
            $main .= '</div></div>';
        }
        $main .= '<div class="public-card-grid">';
        foreach ($items as $item) {
            $detailRoute = match ($entityType) {
                'product' => $this->localizedRoute($languageCode, 'products/' . (string) ($item['slug'] ?? '')),
                'solution' => $this->localizedRoute($languageCode, 'solutions/' . (string) ($item['slug'] ?? '')),
                'news' => $this->localizedRoute($languageCode, 'news/' . (string) ($item['slug'] ?? '')),
                'case' => $this->localizedRoute($languageCode, 'cases/' . (string) ($item['slug'] ?? '')),
                default => '#',
            };
            $main .= $this->renderListingCard($item, $detailRoute, '', $entityType, $languageCode);
        }
        $main .= '</div></div></section></main>';

        return $this->renderShellPage($languageCode, $route, [
            'title' => $title . ' - ' . $this->companyName($languageCode),
            'description' => $lead,
            'main' => $main,
            'extra_css' => ['/assets/css/public-listing.css?v=20260611-01'],
        ]);
    }

    private function renderListingPageV2(string $entityType, string $languageCode, string $route): string
    {
        $payload = $this->cachedCollectionPayload($entityType, $languageCode);

        $title = match ($entityType) {
            'product' => $this->phrase('page_products', $languageCode, 'Products'),
            'solution' => $this->phrase('page_solutions', $languageCode, 'Solutions'),
            'news' => $this->phrase('page_news', $languageCode, 'News'),
            'case' => $this->phrase('page_cases', $languageCode, 'Cases'),
            default => 'Listing',
        };

        $items = $this->filterRenderableItems(is_array($payload['items'] ?? null) ? $payload['items'] : [], $entityType);
        $categories = $this->filterRenderableCategories(is_array($payload['categories'] ?? null) ? $payload['categories'] : []);
        $filterCategories = $this->uniqueRenderableCategoriesBySlug($categories);
        $itemCount = count($items);
        $categoryCount = count($filterCategories);
        $lead = $this->listingLead($entityType, $languageCode);
        $baseRoute = $this->localizedRoute($languageCode, $this->listingRouteKeyForEntity($entityType));
        $categoryLookup = $this->categoryLookupById($categories);
        $allLabel = $this->phrase('filter_all', $languageCode, $languageCode === 'zh' ? '全部' : 'All');

        $heroKicker = match ($entityType) {
            'product' => $languageCode === 'zh' ? $this->unicodeText('\u4ea7\u54c1\u4e2d\u5fc3') : 'Product Catalog',
            'solution' => $languageCode === 'zh' ? $this->unicodeText('\u65b9\u6848\u4e2d\u5fc3') : 'Solution Library',
            'news' => $languageCode === 'zh' ? $this->unicodeText('\u65b0\u95fb\u52a8\u6001') : 'Latest Updates',
            'case' => $languageCode === 'zh' ? $this->unicodeText('\u5ba2\u6237\u6848\u4f8b') : 'Delivery Cases',
            default => $title,
        };
        $allLabel = $this->phrase('filter_all', $languageCode, $languageCode === 'zh' ? $this->unicodeText('\u5168\u90e8') : 'All');

        $main = '<main class="public-content-page public-content-listing public-listing-main" data-public-content-listing="1"><section class="section public-listing-section"><div class="container"><div class="public-listing-hero"><span class="public-section-kicker">' . $this->escape($heroKicker) . '</span><div class="public-listing-hero-copy"><h1>' . $this->escape($title) . '</h1><p>' . $this->escape($lead) . '</p>' . $this->renderListingIntro($entityType, $languageCode) . $this->renderListingSummary($entityType, $languageCode, $itemCount, $categoryCount) . '</div></div>';
        if ($filterCategories !== []) {
            $main .= '<div class="public-filter-shell" data-category-filter-root="1"><span class="public-filter-label">' . $this->escape($title) . '</span><div class="public-filter-list">';
            $main .= '<a class="public-filter-button is-active" href="' . $this->escape($baseRoute) . '" data-category-all="1">' . $this->escape($allLabel) . '</a>';
            foreach (array_slice($filterCategories, 0, 12) as $category) {
                $categorySlug = strtolower(trim((string) ($category['slug'] ?? '')));
                if ($categorySlug === '') {
                    continue;
                }

                $main .= '<a class="public-filter-button" href="' . $this->escape($this->listingCategoryUrl($entityType, $languageCode, $categorySlug)) . '" id="' . $this->escape($this->listingCategoryAnchorId($categorySlug)) . '" data-category-slug="' . $this->escape($categorySlug) . '">' . $this->escape((string) ($category['name'] ?? $category['name_zh'] ?? '')) . '</a>';
            }
            $main .= '</div></div>';
        }
        $main .= '<div class="public-content-grid public-card-grid" data-public-listing-grid="1">';
        foreach ($items as $item) {
            $detailRoute = match ($entityType) {
                'product' => $this->localizedRoute($languageCode, 'products/' . (string) ($item['slug'] ?? '')),
                'solution' => $this->localizedRoute($languageCode, 'solutions/' . (string) ($item['slug'] ?? '')),
                'news' => $this->localizedRoute($languageCode, 'news/' . (string) ($item['slug'] ?? '')),
                'case' => $this->localizedRoute($languageCode, 'cases/' . (string) ($item['slug'] ?? '')),
                default => '#',
            };
            $itemCategoryId = (int) ($item['category_id'] ?? 0);
            $itemCategorySlug = strtolower(trim((string) ($categoryLookup[$itemCategoryId]['slug'] ?? '')));
            $main .= $this->renderListingCard($item, $detailRoute, $itemCategorySlug, $entityType, $languageCode);
        }
        $main .= '</div></div></section></main>';

        return $this->renderShellPage($languageCode, $route, [
            'title' => $title . ' - ' . $this->companyName($languageCode),
            'description' => $lead,
            'og_image' => $this->listingOgImage($entityType),
            'structured_data_nodes' => $this->listingStructuredDataNodes($entityType, $languageCode, $route, $title, $lead),
            'main' => $main,
            'extra_css' => ['/assets/css/public-content-pages.css?v=20260623-01'],
        ]);
    }

    private function listingOgImage(string $entityType): string
    {
        return match ($entityType) {
            'product' => $this->assetUrl('/assets/images/home/equipment-forming-module.jpg'),
            'solution' => $this->assetUrl('/assets/images/home/equipment-integrated-line.jpg'),
            'news' => $this->assetUrl('/assets/images/home/news-real-expo-hall.jpg'),
            'case' => $this->assetUrl('/assets/images/home/news-real-handshake-team.jpg'),
            default => $this->assetUrl('/assets/videos/home/hero-enterprise-showcase.webm'),
        };
    }

    private function listingLead(string $entityType, string $languageCode): string
    {
        if ($languageCode === 'zh') {
            return match ($entityType) {
                'product' => $this->unicodeText('\u6309\u8bbe\u5907\u7c7b\u578b\u3001\u5de5\u827a\u73af\u8282\u4e0e\u5e94\u7528\u573a\u666f\u7b5b\u9009\u8bbe\u5907\uff0c\u5feb\u901f\u627e\u5230\u9002\u5408\u5f53\u524d\u4ea7\u7ebf\u7684\u6838\u5fc3\u673a\u578b\u3002'),
                'solution' => $this->unicodeText('\u56f4\u7ed5\u86cb\u7cd5\u3001\u9762\u5305\u3001\u997c\u5e72\u4e0e\u4e2d\u592e\u53a8\u623f\u573a\u666f\u8f93\u51fa\u6574\u7ebf\u65b9\u6848\uff0c\u8986\u76d6\u4ea7\u80fd\u89c4\u5212\u3001\u5de5\u827a\u8854\u63a5\u4e0e\u4ea4\u4ed8\u843d\u5730\u3002'),
                'news' => $this->unicodeText('\u67e5\u770b\u5c55\u4f1a\u3001\u53d1\u8fd0\u3001\u6765\u8bbf\u4e0e\u9879\u76ee\u8fdb\u5c55\uff0c\u5feb\u901f\u4e86\u89e3\u5de5\u5382\u52a8\u6001\u4e0e\u6d77\u5916\u4ea4\u4ed8\u8282\u594f\u3002'),
                'case' => $this->unicodeText('\u6309\u56fd\u5bb6\u3001\u9879\u76ee\u7c7b\u578b\u4e0e\u4ea4\u4ed8\u9636\u6bb5\u67e5\u770b\u771f\u5b9e\u9879\u76ee\u6848\u4f8b\uff0c\u4e86\u89e3\u6211\u4eec\u5728\u6d77\u5916\u5e02\u573a\u7684\u843d\u5730\u7ecf\u9a8c\u3002'),
                default => $this->companyName($languageCode) . ' ' . $this->phrase('page_products', $languageCode, 'Products'),
            };
        }

        return match ($entityType) {
            'product' => 'Filter equipment by process step, equipment type, and production scenario to find the right machine for your current line.',
            'solution' => 'Review complete production line solutions for cake, bread, biscuits, and central kitchens with capacity planning, process flow, and delivery support.',
            'news' => 'Follow exhibitions, shipments, customer visits, and project milestones to understand factory activity and overseas delivery progress.',
            'case' => 'Browse real delivery cases by country, project type, and rollout stage to understand how our lines land in overseas markets.',
            default => $this->companyName($languageCode) . ' ' . $this->phrase('page_products', $languageCode, 'Products'),
        };
    }

    private function renderListingIntro(string $entityType, string $languageCode): string
    {
        $items = $this->listingIntroItems($entityType, $languageCode);
        if ($items === []) {
            return '';
        }

        $html = '<div class="public-listing-intro" data-listing-intro="1">';
        foreach ($items as $item) {
            $html .= '<span class="public-listing-intro-chip">' . $this->escape($item) . '</span>';
        }
        $html .= '</div>';

        return $html;
    }

    private function listingIntroItems(string $entityType, string $languageCode): array
    {
        if ($languageCode === 'zh') {
            return match ($entityType) {
                'product' => [
                    $this->unicodeText('\u8bbe\u5907\u9009\u578b'),
                    $this->unicodeText('\u5de5\u827a\u5339\u914d'),
                    $this->unicodeText('\u5feb\u901f\u8be2\u76d8'),
                ],
                'solution' => [
                    $this->unicodeText('\u4ea7\u80fd\u89c4\u5212'),
                    $this->unicodeText('\u6574\u7ebf\u96c6\u6210'),
                    $this->unicodeText('\u4ea4\u4ed8\u652f\u6301'),
                ],
                'news' => [
                    $this->unicodeText('\u5de5\u5382\u52a8\u6001'),
                    $this->unicodeText('\u5c55\u4f1a\u4ea4\u6d41'),
                    $this->unicodeText('\u9879\u76ee\u8fdb\u5ea6'),
                ],
                'case' => [
                    $this->unicodeText('\u56fd\u5bb6\u5206\u5e03'),
                    $this->unicodeText('\u4ea4\u4ed8\u8282\u70b9'),
                    $this->unicodeText('\u884c\u4e1a\u573a\u666f'),
                ],
                default => [],
            };
        }

        return match ($entityType) {
            'product' => ['Equipment Selection', 'Process Fit', 'Fast Inquiry'],
            'solution' => ['Capacity Planning', 'Line Integration', 'Delivery Support'],
            'news' => ['Factory Updates', 'Exhibitions', 'Project Progress'],
            'case' => ['Country Coverage', 'Delivery Milestones', 'Industry Scenarios'],
            default => [],
        };
    }

    private function renderListingSummary(string $entityType, string $languageCode, int $itemCount, int $categoryCount): string
    {
        $items = [
            [
                'label' => $languageCode === 'zh' ? $this->unicodeText('\u5df2\u53d1\u5e03') : 'Published',
                'value' => (string) max(0, $itemCount),
            ],
            [
                'label' => $languageCode === 'zh' ? $this->unicodeText('\u5206\u7c7b') : 'Categories',
                'value' => (string) max(0, $categoryCount),
            ],
            [
                'label' => $languageCode === 'zh' ? $this->unicodeText('\u652f\u6301') : 'Support',
                'value' => $this->listingSupportLabel($entityType, $languageCode),
            ],
        ];

        $html = '<div class="public-listing-summary" data-listing-summary="1">';
        foreach ($items as $item) {
            $html .= '<article class="public-listing-summary-card"><small>' . $this->escape((string) $item['label']) . '</small><strong>' . $this->escape((string) $item['value']) . '</strong></article>';
        }
        $html .= '</div>';

        return $html;
    }

    private function listingSupportLabel(string $entityType, string $languageCode): string
    {
        if ($languageCode === 'zh') {
            return match ($entityType) {
                'product' => $this->unicodeText('AI + \u9500\u552e\u8ddf\u8fdb'),
                'solution' => $this->unicodeText('\u8bd5\u673a + \u4ea4\u4ed8\u89c4\u5212'),
                'news' => $this->unicodeText('\u5de5\u5382 + \u5e02\u573a\u52a8\u6001'),
                'case' => $this->unicodeText('\u771f\u5b9e\u4ea4\u4ed8\u9879\u76ee'),
                default => $this->unicodeText('\u5728\u7ebf\u652f\u6301'),
            };
        }

        return match ($entityType) {
            'product' => 'AI + Sales Follow-up',
            'solution' => 'Trials + Delivery Planning',
            'news' => 'Factory + Market Updates',
            'case' => 'Real Delivery Projects',
            default => 'Online Support',
        };
    }

    private function renderDetailPage(string $entityType, string $languageCode, string $slug, string $route): string
    {
        $record = $this->cachedDetailRecord($entityType, $slug, $languageCode);

        $title = trim((string) ($record['title'] ?? $record['name'] ?? $record['name_zh'] ?? ''));
        $title = $this->normalizeDetailTitle($entityType, $slug, $languageCode, $title);
        $summary = $this->sanitizeSummary((string) ($record['summary'] ?? $record['summary_zh'] ?? $record['description'] ?? ''), 220);
        $image = trim((string) ($record['cover_image_url'] ?? $record['cover_asset_url'] ?? ''));
        if ($image === '') {
            $image = match ($entityType) {
                'product' => '/assets/images/home/equipment-forming-module.jpg',
                'solution' => '/assets/images/home/equipment-integrated-line.jpg',
                'news' => '/assets/images/home/news-real-expo-hall.jpg',
                'case' => '/assets/images/home/news-real-handshake-team.jpg',
                default => '/assets/images/common/logo-110.png',
            };
        }
        $content = $this->normalizeRichContent((string) ($record['content'] ?? $record['content_zh'] ?? ''));
        $resolvedImage = $this->assetUrl($image);

        $facts = [];
        if (trim((string) ($record['sku'] ?? '')) !== '') {
            $facts['SKU'] = (string) ($record['sku'] ?? '');
        }
        if (trim((string) ($record['business_status'] ?? '')) !== '') {
            $facts[$this->phrase('detail_status', $languageCode, 'Status')] = $this->localizedBusinessStatus(
                (string) ($record['business_status'] ?? ''),
                $languageCode
            );
        }
        if (trim((string) ($record['published_at'] ?? $record['publish_time'] ?? '')) !== '') {
            $facts[$this->phrase('detail_date', $languageCode, 'Published')] = substr(
                (string) ($record['published_at'] ?? $record['publish_time'] ?? ''),
                0,
                10
            );
        }

        $summaryHtml = $summary !== '' ? '<p>' . $this->escape($summary) . '</p>' : '';
        $heroMetaHtml = $this->renderDetailHeroMeta(
            $this->buildDetailHeroMetaItems($entityType, $record, $languageCode, $facts)
        );
        $highlightsHtml = $this->renderDetailHighlights(
            $this->buildDetailHighlightItems($entityType, $record, $languageCode, $title)
        );
        $typedModules = $this->renderDetailTypedModules($entityType, $record, $languageCode, $facts);

        $entityLabel = match ($entityType) {
            'product' => $this->phrase('page_products', $languageCode, 'Products'),
            'solution' => $this->phrase('page_solutions', $languageCode, 'Solutions'),
            'news' => $this->phrase('page_news', $languageCode, 'News'),
            'case' => $this->phrase('page_cases', $languageCode, 'Cases'),
            default => $this->companyName($languageCode),
        };
        $overviewLabel = $this->phrase('detail_overview', $languageCode, 'Overview');
        $contentHtml = $content !== '' ? $content : '';

        $main = '<main id="top" class="public-content-page public-content-detail" data-public-detail-root data-public-content-detail="1"><section class="section public-detail-section"><div class="container">';
        $main .= $this->renderDetailBreadcrumb($entityType, $languageCode, $title);
        $main .= '<div class="public-detail-hero reveal"><span class="public-section-kicker">' . $this->escape($entityLabel) . '</span><div class="public-detail-hero-copy"><h1 class="public-detail-title">' . $this->escape($title) . '</h1>' . $summaryHtml . $heroMetaHtml . $highlightsHtml . '</div></div>';
        $main .= '<div class="public-detail-layout" data-public-detail-layout="1"><div class="public-detail-main" data-public-detail-main="1"><figure class="public-detail-media reveal"><img src="' . $resolvedImage . '" alt="' . $this->escape($title) . '" loading="eager" decoding="async"></figure>';
        if ($contentHtml !== '') {
            $main .= '<article class="public-detail-article reveal"><section class="public-detail-overview"><span class="public-detail-overview-label">' . $this->escape($overviewLabel) . '</span><div class="article-rich-content">' . $contentHtml . '</div></section></article>';
        }
        $main .= $this->renderContentDetailRelatedSection($entityType, $record, $languageCode);
        $main .= '</div><aside class="public-detail-sidebar" data-public-detail-sidebar="1"><div class="public-detail-sidebar-stack">';
        $main .= $typedModules;
        $main .= $this->renderDetailActionCard($entityType, $languageCode);
        $main .= '</div></aside></div></div></section></main>';

        return $this->renderShellPage($languageCode, $route, [
            'title' => $title . ' - ' . $this->companyName($languageCode),
            'description' => $summary,
            'og_image' => $resolvedImage,
            'og_type' => in_array($entityType, ['news', 'case'], true) ? 'article' : 'website',
            'structured_data_nodes' => $this->detailStructuredDataNodes($entityType, $languageCode, $route, $title, $summary, $resolvedImage, $record, $facts),
            'main' => $main,
            'extra_css' => ['/assets/css/public-content-pages.css?v=20260623-01'],
        ]);
    }

    private function homepageStructuredDataNodes(string $languageCode, string $route): array
    {
        return [
            $this->organizationStructuredData($languageCode),
            [
                '@type' => 'WebSite',
                'name' => $this->companyName($languageCode),
                'url' => $this->absolutePublicUrl($route),
                'inLanguage' => $languageCode,
            ],
        ];
    }

    private function listingStructuredDataNodes(string $entityType, string $languageCode, string $route, string $title, string $description): array
    {
        return [
            $this->organizationStructuredData($languageCode),
            [
                '@type' => 'CollectionPage',
                'name' => $title,
                'url' => $this->absolutePublicUrl($route),
                'description' => $description,
                'inLanguage' => $languageCode,
                'image' => $this->absolutePublicUrl($this->listingOgImage($entityType)),
            ],
        ];
    }

    private function detailStructuredDataNodes(string $entityType, string $languageCode, string $route, string $title, string $description, string $image, array $record, array $facts): array
    {
        $nodes = [
            $this->organizationStructuredData($languageCode),
            $this->breadcrumbStructuredData($entityType, $languageCode, $route, $title),
        ];

        $detailUrl = $this->absolutePublicUrl($route);
        $publishedAt = substr((string) ($record['published_at'] ?? $record['publish_time'] ?? ''), 0, 10);

        $entityNode = match ($entityType) {
            'product' => array_filter([
                '@type' => 'Product',
                'name' => $title,
                'description' => $description,
                'image' => $this->absolutePublicUrl($image),
                'sku' => trim((string) ($record['sku'] ?? '')),
                'url' => $detailUrl,
                'category' => trim((string) ($record['category_name'] ?? '')),
            ], static fn (mixed $value): bool => $value !== ''),
            'solution' => array_filter([
                '@type' => 'Service',
                'name' => $title,
                'description' => $description,
                'image' => $this->absolutePublicUrl($image),
                'url' => $detailUrl,
                'serviceType' => trim((string) ($record['category_name'] ?? '')) !== '' ? (string) ($record['category_name'] ?? '') : $title,
                'provider' => [
                    '@type' => 'Organization',
                    'name' => $this->companyName($languageCode),
                ],
            ], static fn (mixed $value): bool => $value !== ''),
            'news', 'case' => array_filter([
                '@type' => 'Article',
                'headline' => $title,
                'description' => $description,
                'image' => [$this->absolutePublicUrl($image)],
                'datePublished' => $publishedAt,
                'mainEntityOfPage' => $detailUrl,
                'author' => [
                    '@type' => 'Organization',
                    'name' => $this->companyName($languageCode),
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => $this->companyName($languageCode),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => $this->absolutePublicUrl((string) ($this->cachedSite()['logo_url'] ?? '/assets/images/common/logo-110.png')),
                    ],
                ],
            ], static fn (mixed $value): bool => $value !== '' && $value !== []),
            default => [],
        };

        if ($entityNode !== []) {
            $nodes[] = $entityNode;
        }

        return $nodes;
    }

    private function organizationStructuredData(string $languageCode): array
    {
        $site = $this->cachedSite($languageCode);
        $sameAs = [];
        $contactPoint = [];
        $address = '';

        foreach ($this->collectScopedContacts($languageCode, ['footer']) as $contact) {
            $fieldKey = strtolower(trim((string) ($contact['field_key'] ?? '')));
            $href = trim((string) ($contact['href'] ?? ''));
            $label = trim((string) ($contact['label'] ?? ''));

            if (in_array($fieldKey, ['linkedin', 'youtube', 'line'], true) && $href !== '' && $href !== '#') {
                $sameAs[] = $this->absolutePublicUrl($href);
            }

            if ($fieldKey === 'email' && $label !== '') {
                $contactPoint[] = [
                    '@type' => 'ContactPoint',
                    'contactType' => 'sales',
                    'email' => $label,
                    'availableLanguage' => ['zh', 'en'],
                ];
            }

            if (in_array($fieldKey, ['phone', 'whatsapp'], true) && $label !== '') {
                $contactPoint[] = [
                    '@type' => 'ContactPoint',
                    'contactType' => $fieldKey === 'whatsapp' ? 'customer support' : 'sales',
                    'telephone' => $label,
                    'availableLanguage' => ['zh', 'en'],
                ];
            }

            if ($fieldKey === 'address' && $label !== '') {
                $address = $label;
            }
        }

        $organization = [
            '@type' => 'Organization',
            'name' => $this->companyName($languageCode),
            'url' => $this->absolutePublicUrl($this->localizedRoute($languageCode, 'index')),
            'logo' => $this->absolutePublicUrl((string) ($site['logo_url'] ?? '/assets/images/common/logo-110.png')),
        ];

        if ($sameAs !== []) {
            $organization['sameAs'] = array_values(array_unique($sameAs));
        }
        if ($contactPoint !== []) {
            $organization['contactPoint'] = $contactPoint;
        }
        if ($address !== '') {
            $organization['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $address,
            ];
        }

        return $organization;
    }

    private function breadcrumbStructuredData(string $entityType, string $languageCode, string $route, string $title): array
    {
        $listName = match ($entityType) {
            'product' => $this->phrase('page_products', $languageCode, 'Products'),
            'solution' => $this->phrase('page_solutions', $languageCode, 'Solutions'),
            'news' => $this->phrase('page_news', $languageCode, 'News'),
            'case' => $this->phrase('page_cases', $languageCode, 'Cases'),
            default => $this->companyName($languageCode),
        };

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => $this->phrase('nav_home', $languageCode, 'Home'),
                    'item' => $this->absolutePublicUrl($this->localizedRoute($languageCode, 'index')),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $listName,
                    'item' => $this->absolutePublicUrl($this->backToListRoute($entityType, $languageCode)),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $title,
                    'item' => $this->absolutePublicUrl($route),
                ],
            ],
        ];
    }

    private function renderDetailBreadcrumb(string $entityType, string $languageCode, string $title): string
    {
        $items = [
            [
                'label' => $this->phrase('nav_home', $languageCode, 'Home'),
                'href' => $this->localizedRoute($languageCode, 'index'),
            ],
            [
                'label' => match ($entityType) {
                    'product' => $this->phrase('page_products', $languageCode, 'Products'),
                    'solution' => $this->phrase('page_solutions', $languageCode, 'Solutions'),
                    'news' => $this->phrase('page_news', $languageCode, 'News'),
                    'case' => $this->phrase('page_cases', $languageCode, 'Cases'),
                    default => $this->companyName($languageCode),
                },
                'href' => $this->backToListRoute($entityType, $languageCode),
            ],
            [
                'label' => $title,
                'href' => '',
            ],
        ];

        $html = '<nav class="public-detail-breadcrumb" aria-label="' . $this->escape($languageCode === 'zh' ? $this->unicodeText('\u9762\u5305\u5c51\u5bfc\u822a') : 'Breadcrumb') . '" data-detail-breadcrumb="1">';
        foreach ($items as $index => $item) {
            if ($index > 0) {
                $html .= '<span class="public-detail-breadcrumb-separator" aria-hidden="true">/</span>';
            }

            $label = (string) ($item['label'] ?? '');
            $href = trim((string) ($item['href'] ?? ''));
            if ($href !== '' && $index < count($items) - 1) {
                $html .= '<a href="' . $this->escape($href) . '">' . $this->escape($label) . '</a>';
                continue;
            }

            $html .= '<span class="is-current">' . $this->escape($label) . '</span>';
        }
        $html .= '</nav>';

        return $html;
    }

    private function localizedBusinessStatus(string $status, string $languageCode): string
    {
        return match (strtolower(trim($status))) {
            'on_sale' => $languageCode === 'zh' ? $this->unicodeText('\u53ef\u552e') : 'Available',
            'off_sale' => $languageCode === 'zh' ? $this->unicodeText('\u505c\u552e') : 'Unavailable',
            'discontinued' => $languageCode === 'zh' ? $this->unicodeText('\u5df2\u505c\u4ea7') : 'Discontinued',
            default => $status,
        };
    }

    private function buildDetailHeroMetaItems(string $entityType, array $record, string $languageCode, array $facts): array
    {
        $items = [];
        foreach ($facts as $label => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $items[] = [
                'label' => (string) $label,
                'value' => $value,
            ];
        }

        $categoryName = trim((string) ($record['category_name'] ?? ''));
        if ($categoryName !== '') {
            $items[] = [
                'label' => $languageCode === 'zh' ? $this->unicodeText('\u5206\u7c7b') : 'Category',
                'value' => $categoryName,
            ];
        }

        if ($entityType === 'solution') {
            $capacityText = trim((string) ($record['capacity_text'] ?? $record['capacity_text_zh'] ?? ''));
            if ($capacityText !== '') {
                $items[] = [
                    'label' => $languageCode === 'zh' ? $this->unicodeText('\u4ea7\u80fd') : 'Capacity',
                    'value' => $capacityText,
                ];
            }
        }

        if ($entityType === 'case') {
            $countryCode = strtoupper(trim((string) ($record['country_code'] ?? '')));
            if ($countryCode !== '') {
                $items[] = [
                    'label' => $languageCode === 'zh' ? $this->unicodeText('\u56fd\u5bb6') : 'Country',
                    'value' => $countryCode,
                ];
            }
        }

        return array_slice($items, 0, 4);
    }

    private function renderDetailHeroMeta(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $html = '<div class="public-detail-hero-meta" data-detail-hero-meta="1">';
        foreach ($items as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $value = trim((string) ($item['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            $html .= '<article class="public-detail-meta-chip">';
            if ($label !== '') {
                $html .= '<small>' . $this->escape($label) . '</small>';
            }
            $html .= '<strong>' . $this->escape($value) . '</strong></article>';
        }
        $html .= '</div>';

        return $html;
    }

    private function buildDetailHighlightItems(string $entityType, array $record, string $languageCode, string $title): array
    {
        $tokens = [];
        $seen = [];
        $push = function (string $value) use (&$tokens, &$seen, $languageCode): void {
            $normalized = $this->normalizeDetailHighlightText($value, $languageCode);
            if ($normalized === '') {
                return;
            }

            $signature = strtolower($normalized);
            if (isset($seen[$signature])) {
                return;
            }

            $seen[$signature] = true;
            $tokens[] = $normalized;
        };

        $push($title);
        foreach ($this->splitHighlightTokens((string) ($record['seo_keywords'] ?? '')) as $keyword) {
            $push($keyword);
        }
        foreach ($this->splitHighlightTokens((string) ($record['case_tags'] ?? '')) as $tag) {
            $push($tag);
        }

        $capacityText = trim((string) ($record['capacity_text'] ?? $record['capacity_text_zh'] ?? ''));
        if ($capacityText !== '') {
            $push($capacityText);
        }

        $countryCode = strtoupper(trim((string) ($record['country_code'] ?? '')));
        if ($countryCode !== '') {
            $push($countryCode);
        }

        return array_slice($tokens, 0, 8);
    }

    private function splitHighlightTokens(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[\r\n,，；;]+/u', $value) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $item): string => trim($item), $parts),
            static fn (string $item): bool => $item !== ''
        ));
    }

    private function normalizeDetailHighlightText(string $value, string $languageCode): string
    {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return '';
        }

        if ($languageCode !== 'zh' && preg_match('/[\x{4E00}-\x{9FFF}]/u', $value) === 1) {
            $translated = $this->translateKnownDetailHighlight($value, $languageCode);
            if ($translated === '') {
                return '';
            }

            $value = $translated;
        }

        return mb_substr($value, 0, 42);
    }

    private function translateKnownDetailHighlight(string $value, string $languageCode): string
    {
        if ($languageCode === 'zh') {
            return $value;
        }

        $map = [
            $this->unicodeText('\u86cb\u7cd5\u9762\u7cca\u6405\u62cc\u673a') => 'Cake batter mixer',
            $this->unicodeText('\u86cb\u7cd5\u751f\u4ea7\u8bbe\u5907') => 'Cake production equipment',
            $this->unicodeText('\u70d8\u7119\u6405\u62cc\u673a') => 'bakery mixer',
            $this->unicodeText('\u58a8\u897f\u54e5\u6848\u4f8b') => 'Mexico project',
            $this->unicodeText('\u7eb8\u676f\u86cb\u7cd5\u9879\u76ee') => 'cupcake',
            $this->unicodeText('\u70d8\u7119\u6574\u7ebf') => 'bakery line',
            $this->unicodeText('\u7eb8\u676f\u86cb\u7cd5') => 'cupcake',
            $this->unicodeText('\u4e2d\u592e\u5de5\u5382') => 'central factory',
            $this->unicodeText('\u51fa\u53e3') => 'export',
            $this->unicodeText('\u9762\u5305\u81ea\u52a8\u751f\u4ea7\u7ebf') => 'Bread production line',
            $this->unicodeText('\u5410\u53f8\u751f\u4ea7\u7ebf') => 'Toast production line',
            $this->unicodeText('\u70d8\u7119\u6574\u7ebf\u65b9\u6848') => 'Bakery line solution',
            $this->unicodeText('\u5370\u5c3c\u5ba2\u6237') => 'Indonesia client',
            $this->unicodeText('\u86cb\u7cd5\u7ebf\u6d4b\u8bd5') => 'Cake line test',
            $this->unicodeText('\u70d8\u7119\u8bbe\u5907') => 'Bakery equipment',
        ];

        return $map[$value] ?? '';
    }

    private function renderDetailHighlights(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $html = '<section class="public-detail-highlights" data-detail-highlights="1"><div class="public-detail-highlight-list">';
        foreach ($items as $item) {
            $html .= '<span class="public-detail-highlight-chip">' . $this->escape($item) . '</span>';
        }
        $html .= '</div></section>';

        return $html;
    }

    private function renderDetailActionCard(string $entityType, string $languageCode): string
    {
        $primaryLabel = $this->phrase('button_get_quote', $languageCode, $languageCode === 'zh' ? $this->unicodeText('\u83b7\u53d6\u65b9\u6848') : 'Get Solution');
        $chatLabel = $this->phrase('button_chat_ai', $languageCode, $languageCode === 'zh' ? $this->unicodeText('\u54a8\u8be2 AI \u5ba2\u670d') : 'Ask AI Assistant');
        $note = $languageCode === 'zh'
            ? $this->unicodeText('\u7559\u4e0b\u9700\u6c42\u6216\u76f4\u63a5\u5f00\u542f AI \u5bf9\u8bdd\uff0c\u540e\u7eed\u4f1a\u5728\u540c\u4e00\u4f1a\u8bdd\u4e2d\u7ee7\u7eed\u8ddf\u8fdb\u3002')
            : 'Leave your requirements or open the AI assistant. The same session will continue across pages.';

        return '<section class="public-detail-module public-detail-action-card" data-detail-actions="1"><span class="public-detail-panel-label">'
            . $this->escape($languageCode === 'zh' ? $this->unicodeText('\u9879\u76ee\u6c9f\u901a') : 'Project Next Step')
            . '</span><div class="public-detail-action-buttons"><a class="button button-primary" href="' . $this->contactAnchorRoute($languageCode) . '">'
            . $this->escape($primaryLabel)
            . '</a><button class="button button-secondary" type="button" data-support-trigger>'
            . $this->escape($chatLabel)
            . '</button></div><p class="public-detail-action-note">'
            . $this->escape($note)
            . '</p><a class="public-detail-back-link" href="' . $this->backToListRoute($entityType, $languageCode) . '">'
            . $this->escape($this->phrase('button_back', $languageCode, 'Back to List'))
            . '</a></section>';
    }

    private function renderDetailTypedModules(string $entityType, array $record, string $languageCode, array $facts): string
    {
        return match ($entityType) {
            'product' => $this->renderProductFactsModule($facts, $languageCode),
            'solution' => $this->renderSolutionFactsModules($record, $languageCode),
            'news' => $this->renderNewsMetaModule($record, $languageCode, $facts),
            'case' => $this->renderCaseMetaModule($record, $languageCode, $facts),
            default => $facts !== [] ? $this->renderFactsGrid($facts) : '',
        };
    }

    private function renderProductFactsModule(array $facts, string $languageCode): string
    {
        $title = $languageCode === 'zh' ? $this->unicodeText('\u4ea7\u54c1\u4fe1\u606f') : 'Product Facts';
        $body = $facts !== []
            ? $this->renderFactsGrid($facts)
            : '<p>' . $this->escape($languageCode === 'zh'
                ? $this->unicodeText('\u66f4\u591a\u4ea7\u54c1\u53c2\u6570\u53ef\u5728\u8be2\u76d8\u6c9f\u901a\u65f6\u83b7\u53d6\u3002')
                : 'More product parameters are available during inquiry.') . '</p>';

        return '<section class="public-detail-module" data-detail-module="product-facts"><div class="public-detail-panel-shell"><span class="public-detail-panel-label">'
            . $this->escape($title)
            . '</span>'
            . $body
            . '</div></section>';
    }

    private function renderSolutionFactsModules(array $record, string $languageCode): string
    {
        $flowText = trim((string) ($record['flow_text'] ?? $record['flow_text_zh'] ?? ''));
        $capacityText = trim((string) ($record['capacity_text'] ?? $record['capacity_text_zh'] ?? ''));

        $flowBody = $flowText !== ''
            ? '<p>' . nl2br($this->escape($flowText)) . '</p>'
            : '<p>' . $this->escape($languageCode === 'zh'
                ? $this->unicodeText('\u5de5\u827a\u6d41\u7a0b\u5c06\u5728\u9700\u6c42\u786e\u8ba4\u540e\u63d0\u4f9b\u3002')
                : 'Process flow will be confirmed after requirement alignment.') . '</p>';
        $capacityBody = $capacityText !== ''
            ? '<div class="capacity-display">' . $this->escape($capacityText) . '</div>'
            : '<p>' . $this->escape($languageCode === 'zh'
                ? $this->unicodeText('\u4ea7\u80fd\u8303\u56f4\u5c06\u6839\u636e\u4ea7\u54c1\u89c4\u683c\u548c\u4ea7\u7ebf\u914d\u7f6e\u786e\u8ba4\u3002')
                : 'Capacity range will be confirmed by product specification and line configuration.') . '</p>';

        return '<section class="public-detail-module" data-detail-module="solution-flow"><div class="public-detail-panel-shell"><span class="public-detail-panel-label">'
            . $this->escape($languageCode === 'zh' ? $this->unicodeText('\u5de5\u827a\u6d41\u7a0b') : 'Process Flow')
            . '</span>'
            . $flowBody
            . '</div></section>'
            . '<section class="public-detail-module" data-detail-module="solution-capacity"><div class="public-detail-panel-shell"><span class="public-detail-panel-label">'
            . $this->escape($languageCode === 'zh' ? $this->unicodeText('\u4ea7\u80fd\u8303\u56f4') : 'Capacity')
            . '</span>'
            . $capacityBody
            . '</div></section>';
    }

    private function renderCaseMetaModule(array $record, string $languageCode, array $facts): string
    {
        $meta = $facts;
        if (trim((string) ($record['country_code'] ?? '')) !== '') {
            $meta[$languageCode === 'zh' ? $this->unicodeText('\u56fd\u5bb6') : 'Country'] = strtoupper(trim((string) ($record['country_code'] ?? '')));
        }

        return '<section class="public-detail-module" data-detail-module="case-meta"><div class="public-detail-panel-shell"><span class="public-detail-panel-label">'
            . $this->escape($languageCode === 'zh' ? $this->unicodeText('\u6848\u4f8b\u4fe1\u606f') : 'Case Facts')
            . '</span>'
            . ($meta !== [] ? $this->renderFactsGrid($meta) : '<p>' . $this->escape($languageCode === 'zh'
                ? $this->unicodeText('\u6848\u4f8b\u4fe1\u606f\u5c06\u5728\u9700\u6c42\u6c9f\u901a\u540e\u8865\u5145\u3002')
                : 'Case facts will be completed after inquiry alignment.') . '</p>')
            . '</div></section>';
    }

    private function renderNewsMetaModule(array $record, string $languageCode, array $facts): string
    {
        $meta = $facts;

        return '<section class="public-detail-module" data-detail-module="news-meta"><div class="public-detail-panel-shell"><span class="public-detail-panel-label">'
            . $this->escape($languageCode === 'zh' ? $this->unicodeText('\u65b0\u95fb\u4fe1\u606f') : 'News Facts')
            . '</span>'
            . ($meta !== [] ? $this->renderFactsGrid($meta) : '<p>' . $this->escape($languageCode === 'zh'
                ? $this->unicodeText('\u65b0\u95fb\u4fe1\u606f\u5c06\u5728\u53d1\u5e03\u65f6\u540c\u6b65\u3002')
                : 'News metadata will be synchronized at publish time.') . '</p>')
            . '</div></section>';
    }

    private function renderContentDetailRelatedSection(string $entityType, array $record, string $languageCode): string
    {
        $blocks = '';

        if ($entityType === 'case') {
            $blocks .= $this->renderRelatedLinkGroup(
                'product',
                $this->resolveRelatedEntityItems('product', $this->normalizeRelatedIds($record['related_product_ids'] ?? []), $languageCode),
                $languageCode,
                'data-related-products="1"'
            );
            $blocks .= $this->renderRelatedLinkGroup(
                'solution',
                $this->resolveRelatedEntityItems('solution', $this->normalizeRelatedIds($record['related_solution_ids'] ?? []), $languageCode),
                $languageCode,
                'data-related-solutions="1"'
            );
        } elseif (in_array($entityType, ['product', 'solution', 'news'], true)) {
            $blocks .= $this->renderRelatedLinkGroup(
                $entityType,
                $this->sameCategoryRelatedItems($entityType, $record, $languageCode),
                $languageCode
            );
        }

        if ($blocks === '') {
            return '';
        }

        return '<section class="public-detail-related" data-detail-related="1">' . $blocks . '</section>';
    }

    private function renderRelatedLinkGroup(string $entityType, array $items, string $languageCode, string $extraAttributes = ''): string
    {
        if ($items === []) {
            return '';
        }

        $titles = [
            'product' => $languageCode === 'zh' ? $this->unicodeText('\u76f8\u5173\u4ea7\u54c1') : 'Related Products',
            'solution' => $languageCode === 'zh' ? $this->unicodeText('\u76f8\u5173\u65b9\u6848') : 'Related Solutions',
            'news' => $languageCode === 'zh' ? $this->unicodeText('\u76f8\u5173\u65b0\u95fb') : 'Related News',
            'case' => $languageCode === 'zh' ? $this->unicodeText('\u76f8\u5173\u6848\u4f8b') : 'Related Cases',
        ];
        $title = $titles[$entityType] ?? ($languageCode === 'zh' ? $this->unicodeText('\u76f8\u5173\u5185\u5bb9') : 'Related Content');

        $html = '<div class="public-detail-related-group"' . ($extraAttributes !== '' ? ' ' . $extraAttributes : '') . '>';
        $html .= '<h3>' . $this->escape($title) . '</h3>';

        $html .= '<div class="public-detail-related-links">';
        foreach (array_slice($items, 0, 4) as $item) {
            $itemTitle = (string) ($item['title'] ?? $item['name'] ?? $item['name_zh'] ?? '');
            $itemSlug = trim((string) ($item['slug'] ?? ''));
            if ($itemTitle === '' || $itemSlug === '') {
                continue;
            }

            $html .= '<a class="related-chip" href="' . $this->escape($this->localizedRoute($languageCode, $this->listingRouteKeyForEntity($entityType) . '/' . $itemSlug . '.html')) . '">' . $this->escape($itemTitle) . '</a>';
        }
        $html .= '</div></div>';

        return $html;
    }

    private function normalizeRelatedIds(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(
                array_map(static fn (mixed $item): int => (int) $item, $value),
                static fn (int $id): bool => $id > 0
            ));
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter(
                    array_map(static fn (mixed $item): int => (int) $item, $decoded),
                    static fn (int $id): bool => $id > 0
                ));
            }
        }

        return [];
    }

    private function resolveRelatedEntityItems(string $entityType, array $ids, string $languageCode): array
    {
        if ($ids === []) {
            return [];
        }

        $lookup = [];
        foreach ($this->cachedCollectionItems($entityType, $languageCode) as $item) {
            $lookup[(int) ($item['id'] ?? 0)] = $item;
        }

        $items = [];
        foreach ($ids as $id) {
            if (isset($lookup[$id]) && is_array($lookup[$id])) {
                $items[] = $lookup[$id];
            }
        }

        return $items;
    }

    private function sameCategoryRelatedItems(string $entityType, array $record, string $languageCode): array
    {
        $categoryId = (int) ($record['category_id'] ?? 0);
        $recordId = (int) ($record['id'] ?? 0);
        $recordSlug = trim((string) ($record['slug'] ?? ''));
        $items = $this->cachedCollectionItems($entityType, $languageCode);
        $excludeCurrent = static function (array $item) use ($recordId, $recordSlug): bool {
            if ((int) ($item['id'] ?? 0) === $recordId) {
                return false;
            }

            if ($recordSlug !== '' && trim((string) ($item['slug'] ?? '')) === $recordSlug) {
                return false;
            }

            return true;
        };

        $sameCategoryItems = $categoryId > 0
            ? array_values(array_filter(
                $items,
                static function (array $item) use ($categoryId, $excludeCurrent): bool {
                    return $excludeCurrent($item) && (int) ($item['category_id'] ?? 0) === $categoryId;
                }
            ))
            : [];

        if ($sameCategoryItems !== []) {
            return $sameCategoryItems;
        }

        return array_values(array_filter($items, $excludeCurrent));
    }

    private function normalizeDetailTitle(string $entityType, string $slug, string $languageCode, string $title): string
    {
        $slug = strtolower(trim($slug));
        $title = trim($title);
        if ($title === '') {
            return $title;
        }

        if ($entityType !== 'page') {
            return $title;
        }

        if ($languageCode === 'zh') {
            return match ($slug) {
                'about-us' => $this->unicodeText('\u516c\u53f8\u4ecb\u7ecd'),
                'contact-us' => $this->unicodeText('\u8054\u7cfb\u6211\u4eec'),
                default => $title,
            };
        }

        return $title;
    }

    private function renderFactsGrid(array $facts): string
    {
        if ($facts === []) {
            return '';
        }

        $html = '<div class="public-facts-grid">';
        foreach ($facts as $label => $value) {
            $html .= '<article class="public-fact-card"><small>' . $this->escape((string) $label) . '</small><strong>' . $this->escape((string) $value) . '</strong></article>';
        }
        $html .= '</div>';

        return $html;
    }

    private function renderShellPage(string $languageCode, string $route, array $payload): string
    {
        $shellReplacements = [
            '{{site_header_html}}' => $this->cachedHeaderHtml($languageCode, $route),
            '{{site_footer_html}}' => $this->cachedFooterHtml($languageCode),
            '{{floating_contact_html}}' => $this->cachedFloatingContactsHtml($languageCode),
            '{{support_panel_html}}' => $this->cachedSupportPanelHtml($languageCode),
            '{{wechat_panel_html}}' => $this->cachedWechatPanelHtml($languageCode),
            '{{homepage_video_support_html}}' => '',
            '{{homepage_notice_html}}' => '',
            '{{homepage_metrics_html}}' => '',
            '{{homepage_sales_html}}' => '',
            '{{homepage_contact_html}}' => '',
            '{{featured_solutions_html}}' => '',
            '{{featured_products_html}}' => '',
            '{{featured_cases_html}}' => '',
            '{{featured_news_html}}' => '',
            '{{footer_contact_html}}' => $this->cachedFooterContactCardsHtml($languageCode),
            '{{footer_featured_products_html}}' => $this->cachedFooterFeaturedLinksHtml('product', $languageCode),
            '{{footer_featured_solutions_html}}' => $this->cachedFooterFeaturedLinksHtml('solution', $languageCode),
            '{{public_scripts_html}}' => $this->renderPublicScriptsHtml($route),
        ];
        $templateReplacements = is_array($payload['template_replacements'] ?? null) ? $payload['template_replacements'] : [];

        return $this->renderTemplatePage($this->loadPublicTemplate(), $languageCode, $route, [
            ...$shellReplacements,
            ...$templateReplacements,
        ], array_key_exists('main', $payload) ? (string) $payload['main'] : null, $payload);
    }

    /**
     * 根据页面 route 决定加载哪些 JS。
     * - 所有页面：site-runtime + site-nav + site-aside + home-hydrate（home-hydrate 内部有 isStaticGeneratedPublicPage 守卫，非首页只跑 bootstrap 拉取和详情页埋点）
     * - 仅首页：额外加载 home-marquee（团队滚动 + 证书舞台 + 数字动画）
     * 首页判断：route 形如 /zh/ 或 /zh/index.html
     */
    private function renderPublicScriptsHtml(string $route): string
    {
        $version = '20260701-ai26';
        $isHomepage = (bool) preg_match('#^/[a-z]{2}/(index\.html)?$#i', $route);

        $scripts = [
            '/assets/js/site-runtime.js',
            '/assets/js/site-nav.js',
            '/assets/js/site-aside.js',
            '/assets/js/home-hydrate.js',
        ];
        if ($isHomepage) {
            $scripts[] = '/assets/js/home-marquee.js';
        }

        $lines = [];
        foreach ($scripts as $src) {
            $lines[] = '<script src="' . $this->escape($src . '?v=' . $version) . '" defer></script>';
        }
        return implode("\n    ", $lines);
    }

    private function cachedHeaderHtml(string $languageCode, string $route): string
    {
        return $this->rememberFragment('shell:header:' . $languageCode . ':' . md5($route), fn (): string => $this->renderHeader($languageCode, $route));
    }

    private function cachedFooterHtml(string $languageCode): string
    {
        return $this->rememberFragment('shell:footer:' . $languageCode, fn (): string => $this->renderFooter($languageCode));
    }

    private function cachedFloatingContactsHtml(string $languageCode): string
    {
        return $this->rememberFragment('shell:floating:' . $languageCode, fn (): string => $this->renderFloatingContacts($languageCode));
    }

    private function cachedSupportPanelHtml(string $languageCode): string
    {
        return $this->rememberFragment('shell:support:' . $languageCode, fn (): string => $this->renderSupportPanel($languageCode));
    }

    private function cachedWechatPanelHtml(string $languageCode): string
    {
        return $this->rememberFragment('shell:wechat:' . $languageCode, fn (): string => $this->renderWechatPanel($languageCode));
    }

    private function cachedFooterContactCardsHtml(string $languageCode): string
    {
        return $this->rememberFragment('shell:footer-contacts:' . $languageCode, fn (): string => $this->renderFooterContactCardsHtml($languageCode));
    }

    private function cachedFooterFeaturedLinksHtml(string $entityType, string $languageCode): string
    {
        return $this->rememberFragment('shell:footer-featured:' . $entityType . ':' . $languageCode, function () use ($entityType, $languageCode): string {
            $heading = match ($entityType) {
                'product' => $this->phrase('footer_popular_products', $languageCode, 'Popular Products'),
                'solution' => $this->phrase('footer_popular_solutions', $languageCode, 'Popular Solutions'),
                default => '',
            };
            $items = array_slice($this->filterHomeFeaturedWithCover($this->cachedCollectionItems($entityType, $languageCode)), 0, 5);

            return $this->renderFooterFeaturedLinksHtml($heading, $items, $entityType, $languageCode);
        });
    }

    private function renderHeader(string $languageCode, string $route): string
    {
        $site = $this->cachedSite($languageCode);
        $companyName = $this->companyName($languageCode);
        $brandSubtitle = $this->companySubtitle($languageCode);
        $logoUrl = $this->assetUrl((string) ($site['logo_url'] ?? '/assets/images/common/logo-110.png'));
        $logoAlt = trim((string) ($site['logo_alt'] ?? ''));
        if ($logoAlt === '' || ($languageCode !== 'zh' && preg_match('/[\x{4e00}-\x{9fff}]/u', $logoAlt) === 1)) {
            $logoAlt = $companyName;
        }
        $brandTitle = $companyName;
        $menus = [[
            'items' => $this->ensureHeaderNavItems([
                ['name' => $this->phrase('nav_home', $languageCode, 'Home'), 'url' => $this->localizedRoute($languageCode, 'index'), 'route_key' => 'home'],
                ['name' => $this->phrase('nav_about', $languageCode, 'About'), 'url' => $this->aboutAnchorRoute($languageCode), 'route_key' => 'about'],
                ['name' => $this->phrase('nav_products', $languageCode, 'Products'), 'url' => $this->localizedRoute($languageCode, 'products'), 'route_key' => 'products', 'display_mode' => 'dropdown'],
                ['name' => $this->phrase('nav_solutions', $languageCode, 'Solutions'), 'url' => $this->localizedRoute($languageCode, 'solutions'), 'route_key' => 'solutions', 'display_mode' => 'flyout'],
                ['name' => $this->phrase('nav_news', $languageCode, 'News'), 'url' => $this->localizedRoute($languageCode, 'news'), 'route_key' => 'news', 'display_mode' => 'flyout'],
                ['name' => $this->phrase('nav_cases', $languageCode, 'Cases'), 'url' => $this->localizedRoute($languageCode, 'cases'), 'route_key' => 'cases', 'display_mode' => 'flyout'],
                ['name' => $this->phrase('nav_contact', $languageCode, 'Contact'), 'url' => $this->contactAnchorRoute($languageCode), 'route_key' => 'contact'],
            ], $languageCode),
        ]];

        $navHtml = '';
        foreach ($menus as $menu) {
            foreach ((array) ($menu['items'] ?? []) as $item) {
                $navHtml .= $this->renderNavItem($item, $languageCode, $route);
            }
        }

        $brandSubtitleHtml = $brandSubtitle !== ''
            ? "\n                <span>{$this->escape($brandSubtitle)}</span>"
            : '';

        return <<<HTML
<header class="site-header">
    <div class="container header-row">
        <a class="brand" href="{$this->localizedRoute($languageCode, 'index')}" aria-label="{$this->escape($companyName)}">
            <img src="{$this->escape($logoUrl)}" alt="{$this->escape($logoAlt)}">
            <div class="brand-copy">
                <strong>{$this->escape($brandTitle)}</strong>
{$brandSubtitleHtml}
            </div>
        </a>
        <button class="menu-toggle" type="button" aria-label="{$this->escape($this->phrase('open_navigation', $languageCode, $languageCode === 'zh' ? '打开导航' : 'Open navigation'))}" data-menu-toggle>
            <span></span><span></span>
        </button>
        <div class="nav-shell" data-menu>
            <nav class="site-nav" data-static-nav="1">{$navHtml}</nav>
            <div class="header-actions">{$this->renderLanguageMenu($languageCode, $route)}</div>
        </div>
    </div>
</header>
HTML;
    }

    private function renderNavItem(array $item, string $languageCode, string $currentRoute): string
    {
        $title = trim((string) ($item['name'] ?? ''));
        $url = $this->resolveNavUrl($item, $languageCode);
        $children = $this->resolveNavChildren($item, $languageCode);
        $routeKey = strtolower(trim((string) ($item['route_key'] ?? $item['code'] ?? '')));
        $displayMode = strtolower(trim((string) ($item['display_mode'] ?? $item['item_type'] ?? '')));
        $isActive = $routeKey !== 'contact' && strtok($url, '#') === strtok($currentRoute, '#');

        if ($children === [] || ($displayMode !== 'dropdown' && $displayMode !== 'flyout')) {
            return '<a class="' . $this->navLinkClass('site-nav-link', $isActive) . '" href="' . $this->escape($url) . '">' . $this->escape($title) . '</a>';
        }

        if ($routeKey === 'products' || $routeKey === 'solutions') {
            return $this->renderMegaNav($title, $url, $routeKey, $languageCode, $isActive);
        }

        if ($routeKey === 'news' || $routeKey === 'cases') {
            return $this->renderCardGridNav($title, $url, $routeKey, $languageCode, $isActive);
        }

        return $this->renderDropdownNavItem(
            $title !== '' ? $title : $this->phrase('nav_products', $languageCode, 'Products'),
            $url,
            $children,
            $languageCode,
            $isActive
        );
    }

    private function renderDropdownNavItem(string $title, string $url, array $children, string $languageCode, bool $isActive): string
    {
        $submenu = '';
        foreach ($children as $child) {
            $childTitle = trim((string) ($child['name'] ?? ''));
            if ($childTitle === '') {
                continue;
            }
            $submenu .= '<a href="' . $this->escape($this->resolveNavUrl($child, $languageCode)) . '">' . $this->escape($childTitle) . '</a>';
        }

        $toggleLabel = $this->phrase('nav_toggle', $languageCode, 'Toggle') . ' ' . $title;

        return '<div class="nav-item nav-item-submenu" data-nav-dropdown><div class="nav-link-split"><a class="' . $this->navLinkClass('nav-link-direct', $isActive) . '" href="' . $this->escape($url) . '">' . $this->escape($title) . '</a><button class="nav-link-button nav-link-toggle" type="button" data-nav-dropdown-trigger aria-expanded="false" aria-label="' . $this->escape($toggleLabel) . '"><span class="nav-link-arrow" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg></span></button></div><div class="nav-dropdown-panel" data-nav-dropdown-panel><div class="nav-submenu-list">' . $submenu . '</div></div></div>';
    }

    private function navLinkClass(string $baseClass, bool $isActive): string
    {
        return $isActive ? $baseClass . ' is-active' : $baseClass;
    }

    private function renderMegaNav(string $title, string $url, string $routeKey, string $languageCode, bool $isActive): string
    {
        $entityType = $routeKey === 'solutions' ? 'solution' : 'product';
        $branches = $entityType === 'solution'
            ? $this->resolveSolutionNavBranches($languageCode)
            : $this->resolveProductNavBranches($languageCode);

        $items = $this->cachedCollectionItems($entityType, $languageCode);
        // Filter: only homepage-featured items with cover image
        $items = array_values(array_filter($items, fn(array $i): bool => (int)($i['is_home_featured']??0) === 1 && !empty($i['has_cover_image'])));
        $items = array_slice($items, 0, 12);

        $itemsHtml = '';
        foreach ($items as $item) {
            $itemName = trim((string) ($item['name'] ?? $item['title'] ?? ''));
            $itemSlug = trim((string) ($item['slug'] ?? ''));
            if ($itemName === '' || $itemSlug === '') {
                continue;
            }
            $itemUrl = $this->localizedRoute($languageCode, $routeKey . '/' . $itemSlug);
            $thumb = trim((string) ($item['cover_image_url'] ?? $item['cover_asset_url'] ?? ''));
            $thumbHtml = $thumb !== ''
                ? '<div class="nav-mega-card-img"><img src="' . $this->assetUrl($thumb) . '" alt="' . $this->escape($itemName) . '" loading="lazy"></div>'
                : '<div class="nav-mega-card-img nav-mega-card-img--placeholder"><svg viewBox="0 0 40 40" fill="none"><rect width="40" height="40" rx="6" fill="rgba(255,255,255,0.06)"/><path d="M12 26L18 18L22 22L28 14" stroke="rgba(255,255,255,0.3)" stroke-width="1.5"/></svg></div>';

            $catId = (int)($item['category_id'] ?? 0);
            $itemsHtml .= '<a class="nav-mega-card" data-cat="' . $catId . '" href="' . $this->escape($itemUrl) . '">'
                . $thumbHtml
                . '<span class="nav-mega-card-label">' . $this->escape($itemName) . '</span></a>';
        }

        $branchHtml = '';
        foreach ($branches as $branch) {
            $branchTitle = trim((string) ($branch['name'] ?? ''));
            $branchUrl = trim((string) ($branch['url'] ?? '')) ?: $url;
            if ($branchTitle === '') {
                continue;
            }

            $leafHtml = '';
            foreach ((array) ($branch['children'] ?? []) as $child) {
                $childTitle = trim((string) ($child['name'] ?? ''));
                if ($childTitle === '') {
                    continue;
                }
                $leafHtml .= '<a href="' . $this->escape((string) ($child['url'] ?? '#')) . '">' . $this->escape($childTitle) . '</a>';
            }

            $catId = (int)($branch['id'] ?? 0);
            $branchHtml .= '<article class="nav-tree-branch"><a class="nav-tree-branch-title" data-cat="' . $catId . '" href="' . $this->escape($branchUrl) . '">' . $this->escape($branchTitle) . '</a><div class="nav-tree-leaf-list">' . $leafHtml . '</div></article>';
        }

        $toggleLabel = $this->phrase('nav_toggle', $languageCode, 'Toggle') . ' ' . $title;
        $catalogLabel = $this->phrase('page_' . $routeKey . 's', $languageCode, $title);
        $browseLabel = $this->escape($this->phrase($routeKey === 'solutions' ? 'solution_catalog_browse' : 'product_catalog_browse', $languageCode, 'Browse by category'));

        $suffix = $routeKey === 'solutions' ? '-solutions' : '';

        return '<div class="nav-item nav-item-mega" data-mega-nav="' . $this->escape($routeKey) . '"><div class="nav-link-split"><a class="' . $this->navLinkClass('nav-link-direct', $isActive) . '" href="' . $this->escape($url) . '">' . $this->escape($title) . '</a><button class="nav-link-button nav-link-toggle" type="button" data-mega-trigger aria-expanded="false" aria-label="' . $this->escape($toggleLabel) . '"><span class="nav-link-arrow" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg></span></button></div><div class="nav-mega-panel" data-mega-panel><div class="nav-mega-layout"><div class="nav-mega-sidebar"><div class="nav-mega-sidebar-head"><strong>' . $this->escape($title) . '</strong><small>' . $browseLabel . '</small></div><div class="nav-tree-branch-grid">' . $branchHtml . '</div></div><div class="nav-mega-cards">' . $itemsHtml . '</div></div></div></div>';
    }

    private function renderCardGridNav(string $title, string $url, string $routeKey, string $languageCode, bool $isActive): string
    {
        $entityType = $routeKey === 'news' ? 'news' : 'case';
        $items = $this->cachedCollectionItems($entityType, $languageCode);
        // Filter: only homepage-featured items with cover image
        $items = array_values(array_filter($items, fn(array $i): bool => (int)($i['is_home_featured']??0) === 1 && !empty($i['has_cover_image'])));
        $items = array_slice($items, 0, 6);

        $cardsHtml = '';
        foreach ($items as $item) {
            $itemTitle = trim((string) ($item['title'] ?? $item['name'] ?? ''));
            $itemSlug = trim((string) ($item['slug'] ?? ''));
            if ($itemTitle === '' || $itemSlug === '') {
                continue;
            }
            $itemUrl = $this->localizedRoute($languageCode, $routeKey . '/' . $itemSlug);
            $thumb = trim((string) ($item['cover_image_url'] ?? $item['cover_asset_url'] ?? ''));
            $thumbHtml = $thumb !== ''
                ? '<img src="' . $this->assetUrl($thumb) . '" alt="' . $this->escape($itemTitle) . '" loading="lazy">'
                : '<div class="nav-card-grid-placeholder"><svg viewBox="0 0 40 40" fill="none"><rect width="40" height="40" rx="6" fill="rgba(255,255,255,0.06)"/></svg></div>';

            $cardsHtml .= '<a class="nav-card-grid-item" href="' . $this->escape($itemUrl) . '"><div class="nav-card-grid-thumb">' . $thumbHtml . '</div><span class="nav-card-grid-label">' . $this->escape($itemTitle) . '</span></a>';
        }

        $toggleLabel = $this->phrase('nav_toggle', $languageCode, 'Toggle') . ' ' . $title;

        $viewAllLabel = $this->phrase('view_all', $languageCode, $languageCode === 'zh' ? '查看全部 →' : 'View all →');

        return '<div class="nav-item nav-item-mega" data-mega-nav="' . $this->escape($routeKey) . '"><div class="nav-link-split"><a class="' . $this->navLinkClass('nav-link-direct', $isActive) . '" href="' . $this->escape($url) . '">' . $this->escape($title) . '</a><button class="nav-link-button nav-link-toggle" type="button" data-mega-trigger aria-expanded="false" aria-label="' . $this->escape($toggleLabel) . '"><span class="nav-link-arrow" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg></span></button></div><div class="nav-mega-panel" data-mega-panel><div class="nav-mega-layout--cards"><div class="nav-mega-sidebar-head"><strong>' . $this->escape($title) . '</strong><a class="nav-mega-sidebar-more" href="' . $this->escape($url) . '">' . $this->escape($viewAllLabel) . '</a></div><div class="nav-card-grid">' . $cardsHtml . '</div></div></div></div>';
    }

    private function resolveSolutionNavBranches(string $languageCode): array
    {
        return $this->remember(
            'solution-nav-branches:' . $languageCode,
            function () use ($languageCode): array {
                $payload = $this->cachedCollectionPayload('solution', $languageCode);
                $categories = array_slice(array_values(array_filter((array) ($payload['categories'] ?? []), 'is_array')), 0, 8);
                $items = array_values(array_filter((array) ($payload['items'] ?? []), 'is_array'));
                // Only homepage-featured items with cover image
                $items = array_values(array_filter($items, fn(array $i): bool => (int)($i['is_home_featured']??0) === 1 && !empty($i['has_cover_image'])));
                $branches = [];

                foreach ($categories as $category) {
                    $categoryId = (int) ($category['id'] ?? 0);
                    $children = array_values(array_filter(
                        $items,
                        static fn (array $record): bool => (int) ($record['category_id'] ?? 0) === $categoryId && trim((string) ($record['slug'] ?? '')) !== ''
                    ));

                    $leafs = [];
                    foreach (array_slice($children, 0, 4) as $record) {
                        $leafs[] = [
                            'name' => (string) ($record['name'] ?? $record['title'] ?? ''),
                            'url' => $this->localizedRoute($languageCode, 'solutions/' . (string) ($record['slug'] ?? '')),
                        ];
                    }

                    $branches[] = [
                        'id' => $categoryId,
                        'name' => (string) ($category['name'] ?? $category['name_zh'] ?? ''),
                        'url' => $this->listingCategoryUrl('solution', $languageCode, (string) ($category['slug'] ?? '')),
                        'children' => $leafs,
                    ];
                }

                return $branches;
            }
        );
    }

    private function renderLanguageMenu(string $languageCode, string $route): string
    {
        return '<div class="lang-dropdown" data-lang-dropdown><button class="lang-trigger" type="button" data-lang-trigger aria-expanded="false"><span class="lang-current-flag" data-lang-flag aria-hidden="true"></span><span data-lang-label>' . $this->escape(strtoupper($languageCode)) . '</span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg></button><div class="lang-menu" data-lang-menu></div></div>';
    }

    private function ensureHeaderNavItems(array $items, string $languageCode): array
    {
        $required = [
            'home' => ['name' => $this->phrase('nav_home', $languageCode, 'Home'), 'url' => $this->localizedRoute($languageCode, 'index'), 'route_key' => 'home'],
            'about' => ['name' => $this->phrase('nav_about', $languageCode, 'About'), 'url' => $this->aboutAnchorRoute($languageCode), 'route_key' => 'about'],
            'products' => ['name' => $this->phrase('nav_products', $languageCode, 'Products'), 'url' => $this->localizedRoute($languageCode, 'products'), 'route_key' => 'products', 'display_mode' => 'dropdown'],
            'solutions' => ['name' => $this->phrase('nav_solutions', $languageCode, 'Solutions'), 'url' => $this->localizedRoute($languageCode, 'solutions'), 'route_key' => 'solutions', 'display_mode' => 'flyout'],
            'news' => ['name' => $this->phrase('nav_news', $languageCode, 'News'), 'url' => $this->localizedRoute($languageCode, 'news'), 'route_key' => 'news', 'display_mode' => 'flyout'],
            'cases' => ['name' => $this->phrase('nav_cases', $languageCode, 'Cases'), 'url' => $this->localizedRoute($languageCode, 'cases'), 'route_key' => 'cases', 'display_mode' => 'flyout'],
            'contact' => ['name' => $this->phrase('nav_contact', $languageCode, 'Contact'), 'url' => $this->contactAnchorRoute($languageCode), 'route_key' => 'contact'],
        ];

        $indexed = [];
        foreach ($items as $item) {
            $key = strtolower(trim((string) ($item['route_key'] ?? $item['code'] ?? '')));
            if ($key === '') {
                $key = strtolower(trim((string) ($item['name'] ?? '')));
            }
            if ($key === '') {
                continue;
            }
            $indexed[$key] = $item;
        }

        $ordered = [];
        foreach (['home', 'about', 'products', 'solutions', 'news', 'cases', 'contact'] as $key) {
            if (!isset($indexed[$key])) {
                $ordered[] = $required[$key];
                continue;
            }

            $existing = $indexed[$key];
            $merged = array_merge($required[$key], $existing);

            if (trim((string) ($existing['name'] ?? '')) === '') {
                $merged['name'] = $required[$key]['name'];
            }
            if (trim((string) ($existing['url'] ?? '')) === '') {
                $merged['url'] = $required[$key]['url'];
            }
            if (trim((string) ($existing['route_key'] ?? '')) === '') {
                $merged['route_key'] = $required[$key]['route_key'];
            }
            if (isset($required[$key]['display_mode']) && trim((string) ($existing['display_mode'] ?? '')) === '') {
                $merged['display_mode'] = $required[$key]['display_mode'];
            }

            $ordered[] = $merged;
        }

        return $ordered;
    }

    private function resolveNavUrl(array $item, string $languageCode): string
    {
        $routeKey = strtolower(trim((string) ($item['route_key'] ?? $item['code'] ?? '')));
        $url = trim((string) ($item['url'] ?? ''));

        if ($url !== '') {
            return $this->normalizeManagedUrl($url, $languageCode);
        }

        return match ($routeKey) {
            'home', 'index' => $this->localizedRoute($languageCode, 'index'),
            'about' => $this->aboutAnchorRoute($languageCode),
            'contact' => $this->contactAnchorRoute($languageCode),
            'products', 'product' => $this->localizedRoute($languageCode, 'products'),
            'solutions', 'solution' => $this->localizedRoute($languageCode, 'solutions'),
            'news' => $this->localizedRoute($languageCode, 'news'),
            'cases', 'case' => $this->localizedRoute($languageCode, 'cases'),
            default => '#',
        };
    }

    private function resolveNavChildren(array $item, string $languageCode): array
    {
        $routeKey = strtolower(trim((string) ($item['route_key'] ?? $item['code'] ?? '')));
        return match ($routeKey) {
            'products', 'product' => $this->resolveProductNavBranches($languageCode),
            'solutions', 'solution' => $this->buildCategoryNavChildren('solution', $languageCode),
            'news' => $this->buildCategoryNavChildren('news', $languageCode),
            'cases', 'case' => $this->buildCategoryNavChildren('case', $languageCode),
            default => array_values(array_filter((array) ($item['children'] ?? []), 'is_array')),
        };
    }

    private function aboutAnchorRoute(string $languageCode): string
    {
        return $this->localizedRoute($languageCode, 'about') . '#about';
    }

    private function contactAnchorRoute(string $languageCode): string
    {
        return $this->localizedRoute($languageCode, 'about') . '#contact';
    }

    private function listingRouteKeyForEntity(string $entityType): string
    {
        return match ($entityType) {
            'product' => 'products',
            'solution' => 'solutions',
            'news' => 'news',
            'case' => 'cases',
            default => 'index',
        };
    }

    private function listingCategoryAnchorId(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9_-]+/i', '-', $normalized) ?? $normalized;
        $normalized = trim($normalized, '-');

        return 'category-' . ($normalized !== '' ? $normalized : 'all');
    }

    private function listingCategoryUrl(string $entityType, string $languageCode, string $slug): string
    {
        return $this->localizedRoute($languageCode, $this->listingRouteKeyForEntity($entityType))
            . '?category=' . rawurlencode($slug)
            . '#' . $this->listingCategoryAnchorId($slug);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildCategoryNavChildren(string $entityType, string $languageCode): array
    {
        return $this->remember(
            'nav-children:' . $entityType . ':' . $languageCode,
            function () use ($entityType, $languageCode): array {
                $payload = $this->cachedCollectionPayload($entityType, $languageCode);
                $categories = $this->filterRenderableCategories(is_array($payload['categories'] ?? null) ? $payload['categories'] : []);
                if ($entityType !== 'case') {
                    $categories = $this->flattenNavCategories($categories);
                }

                return array_values(array_filter(array_map(
                    fn (array $category): array => [
                        'name' => (string) ($category['name'] ?? $category['name_zh'] ?? ''),
                        'url' => $this->listingCategoryUrl($entityType, $languageCode, (string) ($category['slug'] ?? '')),
                    ],
                    array_slice($categories, 0, 8)
                ), static fn (array $item): bool => trim((string) ($item['name'] ?? '')) !== ''));
            }
        );
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    private function flattenNavCategories(array $categories): array
    {
        $flattened = [];

        foreach ($categories as $category) {
            if (!is_array($category)) {
                continue;
            }

            $flattened[] = $category;
            $children = $this->filterRenderableCategories(array_values(array_filter((array) ($category['children'] ?? []), 'is_array')));
            if ($children !== []) {
                $flattened = array_merge($flattened, $this->flattenNavCategories($children));
            }
        }

        return $flattened;
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    private function uniqueRenderableCategoriesBySlug(array $categories): array
    {
        $unique = [];

        foreach ($this->flattenNavCategories($categories) as $category) {
            $slug = strtolower(trim((string) ($category['slug'] ?? '')));
            if ($slug === '' || isset($unique[$slug])) {
                continue;
            }

            $unique[$slug] = $category;
        }

        return array_values($unique);
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     * @return array<int, array<string, mixed>>
     */
    private function categoryLookupById(array $categories): array
    {
        $lookup = [];
        foreach ($this->flattenNavCategories($categories) as $category) {
            $categoryId = (int) ($category['id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            $lookup[$categoryId] = $category;
        }

        return $lookup;
    }

    private function resolveProductNavBranches(string $languageCode): array
    {
        return $this->remember(
            'product-nav-branches:' . $languageCode,
            function () use ($languageCode): array {
                $payload = $this->cachedCollectionPayload('product', $languageCode);
                $categories = array_slice(array_values(array_filter((array) ($payload['categories'] ?? []), 'is_array')), 0, 8);
                $items = array_values(array_filter((array) ($payload['items'] ?? []), 'is_array'));
                // Only homepage-featured items with cover image
                $items = array_values(array_filter($items, fn(array $i): bool => (int)($i['is_home_featured']??0) === 1 && !empty($i['has_cover_image'])));
                $branches = [];

                foreach ($categories as $category) {
                    $categoryId = (int) ($category['id'] ?? 0);
                    $children = array_values(array_filter(
                        $items,
                        static fn (array $record): bool => (int) ($record['category_id'] ?? 0) === $categoryId && trim((string) ($record['slug'] ?? '')) !== ''
                    ));

                    $leafs = [];
                    foreach (array_slice($children, 0, 4) as $record) {
                        $leafs[] = [
                            'name' => (string) ($record['name'] ?? $record['title'] ?? ''),
                            'url' => $this->localizedRoute($languageCode, 'products/' . (string) ($record['slug'] ?? '')),
                        ];
                    }

                    $branches[] = [
                        'id' => $categoryId,
                        'name' => (string) ($category['name'] ?? $category['name_zh'] ?? ''),
                        'url' => $this->listingCategoryUrl('product', $languageCode, (string) ($category['slug'] ?? '')),
                        'children' => $leafs,
                    ];
                }

                return $branches;
            }
        );
    }

    private function renderProductMegaNav(string $title, string $url, array $branches, string $languageCode, bool $isActive = false): string
    {
        $branchHtml = '';
        foreach ($branches as $branch) {
            $branchTitle = trim((string) ($branch['name'] ?? ''));
            $branchUrl = trim((string) ($branch['url'] ?? '')) ?: $url;
            if ($branchTitle === '') {
                continue;
            }

            $leafHtml = '';
            foreach ((array) ($branch['children'] ?? []) as $child) {
                $childTitle = trim((string) ($child['name'] ?? ''));
                if ($childTitle === '') {
                    continue;
                }
                $leafHtml .= '<a href="' . $this->escape((string) ($child['url'] ?? '#')) . '">' . $this->escape($childTitle) . '</a>';
            }

            $catId = (int)($branch['id'] ?? 0);
            $branchHtml .= '<article class="nav-tree-branch"><a class="nav-tree-branch-title" data-cat="' . $catId . '" href="' . $this->escape($branchUrl) . '">' . $this->escape($branchTitle) . '</a><div class="nav-tree-leaf-list">' . $leafHtml . '</div></article>';
        }

        if ($branchHtml === '') {
            $branchHtml = '<article class="nav-tree-branch"><a class="nav-tree-branch-title" href="' . $this->escape($url) . '">' . $this->escape($title) . '</a><div class="nav-tree-leaf-list"></div></article>';
        }

        $toggleLabel = $this->phrase('nav_toggle', $languageCode, 'Toggle') . ' ' . $title;
        $catalogLabel = $this->phrase('page_products', $languageCode, 'Products');

        return '<div class="nav-item nav-item-mega" data-product-nav><div class="nav-link-split"><a class="' . $this->navLinkClass('nav-link-direct', $isActive) . '" href="' . $this->escape($url) . '">' . $this->escape($title) . '</a><button class="nav-link-button nav-link-toggle" type="button" data-product-trigger aria-expanded="false" aria-label="' . $this->escape($toggleLabel) . '"><span class="nav-link-arrow" aria-hidden="true"><svg viewBox="0 0 20 20"><path d="M5 7.5 10 12.5 15 7.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg></span></button></div><div class="nav-mega-panel" data-product-panel><div class="nav-tree"><div class="nav-tree-tabs" role="tablist" aria-label="' . $this->escape($catalogLabel) . '"><button class="nav-tree-tab is-active" type="button" role="tab" aria-selected="true" data-product-tab="factory"><span class="nav-tree-tab-index">01</span><span class="nav-tree-tab-copy"><strong>' . $this->escape($title) . '</strong><small>' . $this->escape($this->phrase('product_catalog_browse', $languageCode, 'Browse by product categories')) . '</small></span></button></div><div class="nav-tree-views"><section class="nav-tree-view is-active" role="tabpanel" data-product-view="factory"><div class="nav-tree-branch-grid">' . $branchHtml . '</div></section></div></div></div></div>';
    }

    private function renderSupportPromptButton(string $languageCode, string $labelPhraseKey, string $textPhraseKey, string $fallbackLabel, string $fallbackText): string
    {
        $label = $this->phrase($labelPhraseKey, $languageCode, $fallbackLabel);
        $promptText = $this->phrase($textPhraseKey, $languageCode, $fallbackText);
        return '<button type="button" data-support-prompt="' . $this->escape($promptText) . '">' . $this->escape($label) . '</button>';
    }

    private function primaryWechatId(string $languageCode): string
    {
        foreach ($this->collectScopedContacts($languageCode, ['floating_contact', 'footer', 'contact_page']) as $contact) {
            $kind = strtolower(trim((string) ($contact['kind_label'] ?? '')));
            if (str_contains($kind, 'whatsapp') || str_contains($kind, 'wechat') || str_contains((string) ($contact['kind_label'] ?? ''), '微信')) {
                return trim((string) ($contact['label'] ?? ''));
            }
        }

        foreach ($this->collectScopedContacts($languageCode, ['floating_contact', 'footer', 'contact_page']) as $contact) {
            $href = trim((string) ($contact['href'] ?? ''));
            if (str_starts_with($href, 'tel:')) {
                return trim((string) ($contact['label'] ?? ''));
            }
        }

        return '15216813602';
    }

    private function renderFooter(string $languageCode): string
    {
        $site = $this->cachedSite($languageCode);
        $footerText = $this->resolveFooterText($languageCode, trim((string) ($site['footer_text'] ?? '')));
        $logoUrl = $this->assetUrl((string) ($site['logo_url'] ?? '/assets/images/common/logo-110.png'));
        $companyName = $this->companyName($languageCode);
        $companySubtitle = $this->companySubtitle($languageCode);
        $footerSubtitleHtml = $companySubtitle !== ''
            ? '<span class="footer-brand-subtitle">' . $this->escape($companySubtitle) . '</span>'
            : '';

        $footerPopularProducts = $this->cachedFooterFeaturedLinksHtml('product', $languageCode);
        $footerPopularSolutions = $this->cachedFooterFeaturedLinksHtml('solution', $languageCode);
        $footerContacts = $this->rememberFragment('shell:footer-primary-contact:' . $languageCode, fn (): string => $this->renderFooterPrimaryContactHtml($languageCode));
        $footerSocials = $this->renderFooterSocialLinksHtml($languageCode);
        $footerSocialsHtml = $footerSocials !== ''
            ? '<div class="footer-brand-socials">' . $footerSocials . '</div>'
            : '';

        return <<<HTML
<footer class="site-footer-redesign" id="contact">
    <div class="container footer-redesign-shell">
        <div class="footer-redesign-main footer-redesign-main-adaptive">
            <div class="footer-redesign-brand footer-redesign-brand-fluid">
                <div class="footer-brand-title">
                    <img src="{$this->escape($logoUrl)}" alt="{$this->escape($companyName)}" class="footer-brand-logo">
                    <div class="footer-brand-copy footer-brand-copy-lockup">
                        <strong>{$this->escape($companyName)}</strong>
                        {$footerSubtitleHtml}
                    </div>
                </div>
                <div class="footer-brand-rows footer-brand-contacts footer-brand-contacts-compact" data-footer-contact-list>{$footerContacts}</div>
                {$footerSocialsHtml}
            </div>
            <div class="footer-redesign-column" data-footer-featured-products>
                {$footerPopularProducts}
            </div>
            <div class="footer-redesign-column" data-footer-featured-solutions>
                {$footerPopularSolutions}
            </div>
        </div>
        <div class="footer-redesign-bottom"><span>{$this->escape($footerText)}</span></div>
    </div>
</footer>
HTML;
    }

    private function resolveFooterText(string $languageCode, string $configuredFooterText): string
    {
        $configuredFooterText = trim($configuredFooterText);
        if ($configuredFooterText !== '') {
            return $configuredFooterText;
        }

        return 'Copyright ' . date('Y') . ' ' . $this->companyName($languageCode) . '. ' . $this->phrase('copyright_suffix', $languageCode, 'All rights reserved.');
    }

    private function renderFooterSitemapLinkHtml(string $languageCode): string
    {
        $title = $this->phrase('footer_sitemap', $languageCode, 'Site Map');
        $href = $this->localizedRoute($languageCode, 'sitemap');

        return '<h3>' . $this->escape($title) . '</h3><a href="' . $this->escape($href) . '">' . $this->escape($title) . '</a>';
    }

    private function sitemapGroups(string $languageCode): array
    {
        $standalonePages = array_slice($this->publishedStandalonePages($languageCode), 0, 6);
        $groups = [
            $this->phrase('html_sitemap', $languageCode, 'Site Map') => [
                ['label' => $this->phrase('nav_home', $languageCode, 'Home'), 'href' => $this->localizedRoute($languageCode, 'index')],
                ['label' => $this->phrase('nav_about', $languageCode, 'About'), 'href' => $this->aboutAnchorRoute($languageCode)],
                ['label' => $this->phrase('nav_contact', $languageCode, 'Contact'), 'href' => $this->contactAnchorRoute($languageCode)],
            ],
            $this->phrase('page_products', $languageCode, 'Products') => array_map(
                fn (array $item): array => ['label' => (string) ($item['name'] ?? ''), 'href' => $this->localizedRoute($languageCode, 'products/' . (string) ($item['slug'] ?? ''))],
                array_slice($this->cachedCollectionItems('product', $languageCode), 0, 6)
            ),
            $this->phrase('page_solutions', $languageCode, 'Solutions') => array_map(
                fn (array $item): array => ['label' => (string) ($item['title'] ?? $item['name'] ?? ''), 'href' => $this->localizedRoute($languageCode, 'solutions/' . (string) ($item['slug'] ?? ''))],
                array_slice($this->cachedCollectionItems('solution', $languageCode), 0, 6)
            ),
            $this->phrase('page_news', $languageCode, 'News') => array_map(
                fn (array $item): array => ['label' => (string) ($item['title'] ?? ''), 'href' => $this->localizedRoute($languageCode, 'news/' . (string) ($item['slug'] ?? ''))],
                array_slice($this->cachedCollectionItems('news', $languageCode), 0, 6)
            ),
            $this->phrase('page_cases', $languageCode, 'Cases') => array_map(
                fn (array $item): array => ['label' => (string) ($item['title'] ?? ''), 'href' => $this->localizedRoute($languageCode, 'cases/' . (string) ($item['slug'] ?? ''))],
                array_slice($this->cachedCollectionItems('case', $languageCode), 0, 6)
            ),
        ];
        if ($standalonePages !== []) {
            $groups[$this->phrase('page_pages', $languageCode, 'Pages')] = array_map(
                fn (array $item): array => ['label' => (string) ($item['title'] ?? $item['slug'] ?? ''), 'href' => $this->localizedRoute($languageCode, 'pages/' . (string) ($item['slug'] ?? ''))],
                $standalonePages
            );
        }

        return $groups;
    }

    private function renderSitemapPageSections(string $languageCode): string
    {
        $html = '<div class="site-static-sitemap-page"><div class="site-static-sitemap-grid">';
        foreach ($this->sitemapGroups($languageCode) as $title => $links) {
            if ($links === []) {
                continue;
            }
            $html .= '<section><h3>' . $this->escape($title) . '</h3>';
            foreach ($links as $link) {
                $html .= '<a href="' . $this->escape((string) ($link['href'] ?? '#')) . '">' . $this->escape((string) ($link['label'] ?? '')) . '</a>';
            }
            $html .= '</section>';
        }
        $html .= '</div></div>';

        return $html;
    }

    private function renderFloatingContacts(string $languageCode): string
    {
        return $this->renderFloatingContactsV2($languageCode);

        $emailContacts = $this->collectScopedContacts($languageCode, ['floating_contact'], ['email']);
        $phoneContacts = $this->collectScopedContacts($languageCode, ['floating_contact'], ['phone']);
        $whatsappContacts = $this->collectScopedContacts($languageCode, ['floating_contact'], ['whatsapp']);
        $addressContacts = $this->collectScopedContacts($languageCode, ['floating_contact'], ['address']);
        $emailGroup = $this->renderFloatingChooserGroup(
            'email',
            $this->phrase('contact_email', $languageCode, 'Email'),
            $this->phrase('contact_email_hint', $languageCode, 'Tap to choose an email'),
            '<svg viewBox="0 0 24 24"><path d="M4.2 6.8h15.6a1.2 1.2 0 0 1 1.2 1.2v8a1.8 1.8 0 0 1-1.8 1.8H4.8A1.8 1.8 0 0 1 3 16V8a1.2 1.2 0 0 1 1.2-1.2Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/><path d="m4.3 8 7 5 8.4-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/></svg>',
            $emailContacts
        );
        $phoneGroup = $this->renderFloatingChooserGroup(
            'phone',
            $this->phrase('contact_phone', $languageCode, 'Phone'),
            $this->phrase('contact_phone_hint', $languageCode, 'Tap to choose a phone number'),
            '<svg viewBox="0 0 24 24"><path d="M12 3.2a8.8 8.8 0 0 0-7.6 13.2L3 21l4.8-1.3A8.8 8.8 0 1 0 12 3.2Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/><path d="M9.2 8.7c-.2-.4-.4-.4-.7-.4h-.6c-.2 0-.5.1-.7.4-.2.3-.8.9-.8 2.1 0 1.1.8 2.2.9 2.4.1.1 1.6 2.6 4 3.5 1.9.7 2.3.6 2.7.6.4-.1 1.4-.6 1.6-1.2.2-.6.2-1 .1-1.1-.1-.1-.4-.2-.8-.4s-1.1-.5-1.2-.5c-.2-.1-.4-.1-.6.2-.2.3-.7.9-.8 1-.1.2-.3.2-.6.1a6.7 6.7 0 0 1-3.3-3c-.2-.3 0-.5.1-.6.1-.1.3-.4.5-.6.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5 0-.1-.4-1.1-.6-1.5Z" fill="currentColor"/></svg>',
            $phoneContacts
        );
        $whatsappLink = $this->renderFloatingDirectLink(
            'whatsapp',
            $this->phrase('contact_whatsapp', $languageCode, 'WhatsApp'),
            $this->phrase('contact_whatsapp_hint', $languageCode, 'Open WhatsApp chat'),
            '<svg viewBox="0 0 24 24"><path d="M12 3.2a8.8 8.8 0 0 0-7.6 13.2L3 21l4.4-1.2a8.8 8.8 0 1 0 4.6-16.6Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/><path d="M9.4 8.8c-.2-.4-.4-.4-.7-.4h-.5c-.2 0-.5.1-.7.4-.2.3-.7.9-.7 2 0 1 .7 2.1.8 2.2.1.1 1.5 2.4 3.7 3.2 1.7.7 2.1.6 2.5.6.4-.1 1.3-.5 1.5-1.1.2-.5.2-.9.1-1s-.4-.2-.7-.4c-.4-.2-1-.5-1.2-.5-.2-.1-.4-.1-.5.2-.2.3-.6.8-.8.9-.1.2-.3.2-.5.1a6.1 6.1 0 0 1-3-2.7c-.2-.3 0-.4.1-.5.1-.1.3-.4.4-.5.1-.2.2-.3.2-.5.1-.1 0-.3 0-.4 0-.1-.3-1-.5-1.4Z" fill="currentColor"/></svg>',
            $whatsappContacts[0] ?? null
        );
        $addressLink = $this->renderFloatingDirectLink(
            'address',
            $this->phrase('contact_address', $languageCode, 'Address'),
            $this->phrase('contact_address_hint', $languageCode, 'Factory contact details'),
            '<svg viewBox="0 0 24 24"><path d="M12 4.2c-4.7 0-8.4 3-8.4 6.9 0 3.5 2.9 6.4 6.8 6.8l-.3 2.8 2.9-2.6h.2c4.1-.3 7.2-3.3 7.2-7 0-3.9-3.7-6.9-8.4-6.9Z" fill="currentColor"/><path d="M8.1 9.4v4.8h2.6M11.8 9.4v4.8M15.8 9.4h-2.4v4.8h2.4M13.4 11.8h1.9" fill="none" stroke="#ffffff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4"/></svg>',
            $addressContacts[0] ?? null
        );

        return <<<HTML
<div class="floating-contact" aria-label="{$this->escape($this->phrase('floating_contact', $languageCode, $languageCode === 'zh' ? '联系方式' : 'Contact'))}" data-contact-fab>
    <div class="floating-menu" data-contact-menu>
        <button class="float-link support-chat" type="button" aria-label="{$this->escape($this->phrase('support_title', $languageCode, $languageCode === 'zh' ? '在线客服' : 'Online Support'))}" data-support-trigger>
            <span class="float-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 4.2a7.8 7.8 0 0 0-7.8 7.8v3.8a2 2 0 0 0 2 2h1.6v-6.2H6.1a6 6 0 0 1 11.8 0h-1.7v6.2h1.6a2 2 0 0 0 2-2V12A7.8 7.8 0 0 0 12 4.2Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/><path d="M9.6 18.2c.5.5 1.4.8 2.4.8 1.6 0 2.8-.7 3.4-1.8" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.7"/></svg>
            </span>
            <span class="float-copy"><strong>{$this->escape($this->phrase('support_title', $languageCode, 'Support'))}</strong><small>{$this->escape($this->phrase('support_online_chat', $languageCode, 'Online chat'))}</small></span>
        </button>
        <div class="float-link-group support" data-contact-chooser>
            <button class="float-link float-link-toggle support" type="button" aria-label="{$this->escape($this->phrase('contact_email', $languageCode, $languageCode === 'zh' ? '邮箱' : 'Email'))}" data-contact-chooser-trigger aria-expanded="false">
                <span class="float-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M4.2 6.8h15.6a1.2 1.2 0 0 1 1.2 1.2v8a1.8 1.8 0 0 1-1.8 1.8H4.8A1.8 1.8 0 0 1 3 16V8a1.2 1.2 0 0 1 1.2-1.2Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/><path d="m4.3 8 7 5 8.4-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/></svg>
                </span>
                <span class="float-copy"><strong>{$this->escape($this->phrase('contact_email', $languageCode, 'Email'))}</strong><small>{$this->escape($this->phrase('contact_email_hint', $languageCode, 'Tap to choose an email'))}</small></span>
                <span class="float-link-caret" aria-hidden="true"></span>
            </button>
            <div class="float-options" data-contact-chooser-menu data-contact-list="email" hidden></div>
        </div>
        <div class="float-link-group phone" data-contact-chooser>
            <button class="float-link float-link-toggle phone" type="button" aria-label="{$this->escape($this->phrase('contact_phone', $languageCode, $languageCode === 'zh' ? '电话' : 'Phone'))}" data-contact-chooser-trigger aria-expanded="false">
                <span class="float-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 3.2a8.8 8.8 0 0 0-7.6 13.2L3 21l4.8-1.3A8.8 8.8 0 1 0 12 3.2Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/><path d="M9.2 8.7c-.2-.4-.4-.4-.7-.4h-.6c-.2 0-.5.1-.7.4-.2.3-.8.9-.8 2.1 0 1.1.8 2.2.9 2.4.1.1 1.6 2.6 4 3.5 1.9.7 2.3.6 2.7.6.4-.1 1.4-.6 1.6-1.2.2-.6.2-1 .1-1.1-.1-.1-.4-.2-.8-.4s-1.1-.5-1.2-.5c-.2-.1-.4-.1-.6.2-.2.3-.7.9-.8 1-.1.2-.3.2-.6.1a6.7 6.7 0 0 1-3.3-3c-.2-.3 0-.5.1-.6.1-.1.3-.4.5-.6.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5 0-.1-.4-1.1-.6-1.5Z" fill="currentColor"/></svg>
                </span>
                <span class="float-copy"><strong>{$this->escape($this->phrase('contact_phone', $languageCode, 'Phone'))}</strong><small>{$this->escape($this->phrase('contact_phone_hint', $languageCode, 'Tap to choose a phone number'))}</small></span>
                <span class="float-link-caret" aria-hidden="true"></span>
            </button>
            <div class="float-options" data-contact-chooser-menu data-contact-list="phone" hidden></div>
        </div>
        <a class="float-link location" href="{$this->escape($addressHref)}" aria-label="{$this->escape($this->phrase('contact_address', $languageCode, $languageCode === 'zh' ? '地址' : 'Address'))}">
            <span class="float-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 4.2c-4.7 0-8.4 3-8.4 6.9 0 3.5 2.9 6.4 6.8 6.8l-.3 2.8 2.9-2.6h.2c4.1-.3 7.2-3.3 7.2-7 0-3.9-3.7-6.9-8.4-6.9Z" fill="currentColor"/><path d="M8.1 9.4v4.8h2.6M11.8 9.4v4.8M15.8 9.4h-2.4v4.8h2.4M13.4 11.8h1.9" fill="none" stroke="#ffffff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4"/></svg>
            </span>
            <span class="float-copy"><strong>{$this->escape($this->phrase('contact_address', $languageCode, 'Address'))}</strong><small>{$this->escape($this->phrase('contact_address_hint', $languageCode, 'Factory contact details'))}</small></span>
        </a>
        <button class="float-link wechat" type="button" aria-label="WeChat" data-wechat-trigger>
            <span class="float-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M9.2 5.2c-3.7 0-6.7 2.4-6.7 5.5 0 1.8 1 3.4 2.7 4.4l-.6 2.4 2.6-1.3c.8.2 1.3.2 2 .2 3.7 0 6.7-2.4 6.7-5.6 0-3-3-5.6-6.7-5.6Z" fill="currentColor"/><path d="M16.3 9.4c-2.9 0-5.2 1.9-5.2 4.3 0 1.3.7 2.5 1.9 3.3l-.4 1.9 2.1-1.1c.5.1 1 .1 1.5.1 2.9 0 5.2-1.9 5.2-4.2 0-2.4-2.3-4.3-5.1-4.3Z" fill="#ffffff" opacity="0.92"/><circle cx="7.2" cy="10.2" r="0.8" fill="#15a34a"/><circle cx="11.1" cy="10.2" r="0.8" fill="#15a34a"/><circle cx="14.8" cy="13.2" r="0.7" fill="#15a34a"/><circle cx="17.9" cy="13.2" r="0.7" fill="#15a34a"/></svg>
            </span>
            <span class="float-copy"><strong>WeChat</strong><small>{$this->escape($this->phrase('wechat_hint', $languageCode, 'Same phone number on WeChat'))}</small></span>
        </button>
    </div>
    <button class="floating-trigger" type="button" data-contact-trigger aria-expanded="false" aria-label="{$this->escape($this->phrase('floating_contact_hint', $languageCode, $languageCode === 'zh' ? '点击打开联系方式' : 'Tap to open contact options'))}">
        <span class="floating-trigger-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.4 12a7.6 7.6 0 0 1 15.2 0v3.1a1.9 1.9 0 0 1-1.9 1.9h-1.6v-5.8h2A5.7 5.7 0 0 0 6.3 11.2h1.9V17H6.3a1.9 1.9 0 0 1-1.9-1.9Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/><path d="M10.2 18.3c.4.4 1.1.7 1.9.7 1.3 0 2.2-.5 2.8-1.4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.7"/></svg>
        </span>
        <span class="floating-trigger-copy"><strong>{$this->escape($this->phrase('floating_contact', $languageCode, 'Contact'))}</strong><small>{$this->escape($this->phrase('floating_contact_hint', $languageCode, 'Tap to open contact options'))}</small></span>
    </button>
    <button class="back-to-top" type="button" data-back-to-top aria-label="{$this->escape($this->phrase('back_to_top', $languageCode, $languageCode === 'zh' ? '顶部' : 'TOP'))}">
        <span class="back-to-top-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 18V6M7 11l5-5 5 5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg></span>
        <span class="back-to-top-copy"><strong>{$this->escape($this->phrase('back_to_top', $languageCode, 'TOP'))}</strong></span>
    </button>
</div>
HTML;
    }



    private function renderFloatingContactsV2(string $languageCode): string
    {
        $emailContacts = $this->collectScopedContacts($languageCode, ['floating_contact'], ['email']);
        $phoneContacts = $this->collectScopedContacts($languageCode, ['floating_contact'], ['phone']);
        $whatsappContacts = $this->collectScopedContacts($languageCode, ['floating_contact'], ['whatsapp']);
        $addressContacts = $this->collectScopedContacts($languageCode, ['floating_contact'], ['address']);
        $emailGroup = $this->renderFloatingChooserGroup(
            'email',
            $this->phrase('floating_email', $languageCode, 'Business Email'),
            $this->phrase('floating_email_hint', $languageCode, 'Show email address'),
            '<svg viewBox="0 0 24 24"><path d="M4.2 6.8h15.6a1.2 1.2 0 0 1 1.2 1.2v8a1.8 1.8 0 0 1-1.8 1.8H4.8A1.8 1.8 0 0 1 3 16V8a1.2 1.2 0 0 1 1.2-1.2Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/><path d="m4.3 8 7 5 8.4-5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/></svg>',
            $emailContacts
        );
        $phoneGroup = $this->renderFloatingChooserGroup(
            'phone',
            $this->phrase('floating_phone', $languageCode, 'Factory Line'),
            $this->phrase('floating_phone_hint', $languageCode, 'Show phone number'),
            '<svg viewBox="0 0 24 24"><path d="M12 3.2a8.8 8.8 0 0 0-7.6 13.2L3 21l4.8-1.3A8.8 8.8 0 1 0 12 3.2Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/><path d="M9.2 8.7c-.2-.4-.4-.4-.7-.4h-.6c-.2 0-.5.1-.7.4-.2.3-.8.9-.8 2.1 0 1.1.8 2.2.9 2.4.1.1 1.6 2.6 4 3.5 1.9.7 2.3.6 2.7.6.4-.1 1.4-.6 1.6-1.2.2-.6.2-1 .1-1.1-.1-.1-.4-.2-.8-.4s-1.1-.5-1.2-.5c-.2-.1-.4-.1-.6.2-.2.3-.7.9-.8 1-.1.2-.3.2-.6.1a6.7 6.7 0 0 1-3.3-3c-.2-.3 0-.5.1-.6.1-.1.3-.4.5-.6.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5 0-.1-.4-1.1-.6-1.5Z" fill="currentColor"/></svg>',
            $phoneContacts
        );
        $whatsappLink = $this->renderFloatingDirectLink(
            'whatsapp',
            $this->phrase('floating_whatsapp', $languageCode, 'WhatsApp'),
            $this->phrase('floating_whatsapp_hint', $languageCode, 'Start a quick chat'),
            '<svg viewBox="0 0 24 24"><path d="M12 3.2a8.8 8.8 0 0 0-7.6 13.2L3 21l4.4-1.2a8.8 8.8 0 1 0 4.6-16.6Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/><path d="M9.4 8.8c-.2-.4-.4-.4-.7-.4h-.5c-.2 0-.5.1-.7.4-.2.3-.7.9-.7 2 0 1 .7 2.1.8 2.2.1.1 1.5 2.4 3.7 3.2 1.7.7 2.1.6 2.5.6.4-.1 1.3-.5 1.5-1.1.2-.5.2-.9.1-1s-.4-.2-.7-.4c-.4-.2-1-.5-1.2-.5-.2-.1-.4-.1-.5.2-.2.3-.6.8-.8.9-.1.2-.3.2-.5.1a6.1 6.1 0 0 1-3-2.7c-.2-.3 0-.4.1-.5.1-.1.3-.4.4-.5.1-.2.2-.3.2-.5.1-.1 0-.3 0-.4 0-.1-.3-1-.5-1.4Z" fill="currentColor"/></svg>',
            $whatsappContacts[0] ?? null
        );
        $addressLink = $this->renderFloatingDirectLink(
            'address',
            $this->phrase('floating_address', $languageCode, 'Factory Address'),
            $this->phrase('floating_address_hint', $languageCode, 'Open contact page'),
            '<svg viewBox="0 0 24 24"><path d="M12 4.2c-4.7 0-8.4 3-8.4 6.9 0 3.5 2.9 6.4 6.8 6.8l-.3 2.8 2.9-2.6h.2c4.1-.3 7.2-3.3 7.2-7 0-3.9-3.7-6.9-8.4-6.9Z" fill="currentColor"/><path d="M8.1 9.4v4.8h2.6M11.8 9.4v4.8M15.8 9.4h-2.4v4.8h2.4M13.4 11.8h1.9" fill="none" stroke="#ffffff" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.4"/></svg>',
            $addressContacts[0] ?? null
        );

        return <<<HTML
<div class="floating-contact" aria-label="{$this->escape($this->phrase('floating_contact', $languageCode, 'Contact'))}" data-contact-fab>
    <div class="floating-menu" data-contact-menu>
        <button class="float-link support-chat" type="button" aria-label="{$this->escape($this->phrase('floating_ai_title', $languageCode, 'AI Consult'))}" data-support-trigger>
            <span class="float-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M12 4.2a7.8 7.8 0 0 0-7.8 7.8v3.8a2 2 0 0 0 2 2h1.6v-6.2H6.1a6 6 0 0 1 11.8 0h-1.7v6.2h1.6a2 2 0 0 0 2-2V12A7.8 7.8 0 0 0 12 4.2Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/><path d="M9.6 18.2c.5.5 1.4.8 2.4.8 1.6 0 2.8-.7 3.4-1.8" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.7"/></svg>
            </span>
            <span class="float-copy"><strong>{$this->escape($this->phrase('floating_ai_title', $languageCode, 'AI Consult'))}</strong><small>{$this->escape($this->phrase('floating_ai_hint', $languageCode, 'Machine matching, solution discussion, and quotations'))}</small></span>
        </button>
        {$emailGroup}
        {$phoneGroup}
        {$whatsappLink}
        {$addressLink}
    </div>
    <button class="floating-trigger" type="button" data-contact-trigger aria-expanded="false" aria-label="{$this->escape($this->phrase('floating_contact_trigger_title', $languageCode, 'AI Consult'))}">
        <span class="floating-trigger-badge" aria-hidden="true">1</span>
        <span class="floating-trigger-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.4 12a7.6 7.6 0 0 1 15.2 0v3.1a1.9 1.9 0 0 1-1.9 1.9h-1.6v-5.8h2A5.7 5.7 0 0 0 6.3 11.2h1.9V17H6.3a1.9 1.9 0 0 1-1.9-1.9Z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"/><path d="M10.2 18.3c.4.4 1.1.7 1.9.7 1.3 0 2.2-.5 2.8-1.4" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.7"/></svg>
        </span>
        <span class="floating-trigger-copy"><strong>{$this->escape($this->phrase('floating_contact_trigger_title', $languageCode, 'AI Consult'))}</strong><small>{$this->escape($this->phrase('floating_contact_trigger_hint', $languageCode, 'Tap to open contact options'))}</small></span>
    </button>
    <button class="back-to-top" type="button" data-back-to-top aria-label="{$this->escape($this->phrase('back_to_top', $languageCode, 'TOP'))}">
        <span class="back-to-top-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 18V6M7 11l5-5 5 5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"/></svg></span>
    </button>
</div>
HTML;
    }

    /**
     * @param array<int, array<string, mixed>> $contacts
     */
    private function renderFloatingChooserGroup(string $type, string $title, string $hint, string $iconSvg, array $contacts): string
    {
        if ($contacts === []) {
            return '';
        }

        $options = '';
        foreach ($contacts as $contact) {
            $options .= '<a class="float-option float-option-inline" href="' . $this->escape((string) ($contact['href'] ?? '#')) . '"' . (!empty($contact['target']) ? ' target="_blank" rel="noopener"' : '') . '><strong>' . $this->escape((string) ($contact['label'] ?? '')) . '</strong></a>';
        }

        return '<div class="float-link-group ' . $this->escape($type) . '" data-contact-chooser><button class="float-link float-link-toggle ' . $this->escape($type) . '" type="button" aria-label="' . $this->escape($title) . '" data-contact-chooser-trigger aria-expanded="false"><span class="float-icon" aria-hidden="true">' . $iconSvg . '</span><span class="float-copy"><strong>' . $this->escape($title) . '</strong><small>' . $this->escape($hint) . '</small></span><span class="float-link-caret" aria-hidden="true"></span></button><div class="float-options" data-contact-chooser-menu data-contact-list="' . $this->escape($type) . '" data-static-contact-menu="1" hidden>' . $options . '</div></div>';
    }

    /**
     * @param array<string, mixed>|null $contact
     */
    private function renderFloatingDirectLink(string $type, string $title, string $hint, string $iconSvg, ?array $contact): string
    {
        if ($contact === null) {
            return '';
        }

        return '<a class="float-link ' . $this->escape($type) . '" href="' . $this->escape((string) ($contact['href'] ?? '#')) . '"' . (!empty($contact['target']) ? ' target="_blank" rel="noopener"' : '') . ' aria-label="' . $this->escape($title) . '"><span class="float-icon" aria-hidden="true">' . $iconSvg . '</span><span class="float-copy"><strong>' . $this->escape($title) . '</strong><small>' . $this->escape($hint) . '</small></span></a>';
    }

    private function renderSupportPanel(string $languageCode): string
    {
        $title = $this->escape($this->phrase('support_title', $languageCode, $languageCode === 'zh' ? '涵尊智能客服' : 'Hanzun AI Support'));
        $statusOnline = $this->escape($this->phrase('support_status_online', $languageCode, $languageCode === 'zh' ? '在线' : 'Online'));
        $greeting = $this->escape($this->phrase('support_greeting', $languageCode, 'Hello! Ask about equipment, line solutions, lead times, and quotations. You can also leave your contact details and requirements.'));
        $placeholder = $this->escape($this->phrase('support_placeholder', $languageCode, $languageCode === 'zh' ? '请输入您的问题' : 'Type a message'));
        $closeLabel = $this->escape($this->phrase('close_support_panel', $languageCode, $languageCode === 'zh' ? '关闭' : 'Close'));
        $todayLabel = $this->escape($this->phrase('support_date_today', $languageCode, $languageCode === 'zh' ? '今天' : 'Today'));

        return <<<HTML
<div class="support-panel" hidden data-support-panel>
    <div class="support-window" role="dialog" aria-modal="true" aria-label="{$title}">
        <header class="support-header">
            <div class="support-header-info">
                <div class="support-header-avatar" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12c0 1.77.46 3.43 1.27 4.87L2 22l5.13-1.27C8.57 21.54 10.23 22 12 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18c-1.66 0-3.22-.45-4.56-1.24l-.33-.2-3.04.76.76-3.04-.2-.33C3.45 15.22 3 13.66 3 12c0-4.96 4.04-9 9-9s9 4.04 9 9-4.04 9-9 9z" fill="currentColor"/><path d="M8.5 9c-.83 0-1.5.67-1.5 1.5S7.67 12 8.5 12s1.5-.67 1.5-1.5S9.33 9 8.5 9zm7 0c-.83 0-1.5.67-1.5 1.5S14.67 12 15.5 12s1.5-.67 1.5-1.5S16.33 9 15.5 9z" fill="currentColor"/></svg>
                </div>
                <div class="support-header-text">
                    <strong>{$title}</strong>
                    <span class="support-header-status">
                        <i class="support-online-dot"></i>
                        <span>{$statusOnline}</span>
                    </span>
                </div>
            </div>
            <button class="support-close-btn" type="button" aria-label="{$closeLabel}" data-support-close>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="2"/></svg>
            </button>
        </header>
        <div class="support-conversation" data-support-messages>
            <div class="support-date-divider"><span>{$todayLabel}</span></div>
            <article class="support-message support-message-assistant">
                <div class="support-bubble">
                    <p>{$greeting}</p>
                </div>
            </article>
        </div>
        <div class="support-suggestions" data-support-suggestions>
            {$this->renderSupportPromptButton($languageCode, 'support_prompt_cake_label', 'support_prompt_cake_text', 'Cake Lines', 'I want to ask about cake production lines.')}
            {$this->renderSupportPromptButton($languageCode, 'support_prompt_leadtime_label', 'support_prompt_leadtime_text', 'Lead Time', 'I want to know the equipment lead time.')}
            {$this->renderSupportPromptButton($languageCode, 'support_prompt_quotation_label', 'support_prompt_quotation_text', 'Line Quotation', 'I need a quotation for a complete line.')}
        </div>
        <form class="support-composer" data-support-form>
            <p class="support-composer-status" data-support-status hidden></p>
            <div class="support-composer-row">
                <textarea rows="1" placeholder="{$placeholder}" data-support-input></textarea>
                <button type="submit" data-support-submit aria-label="{$this->escape($this->phrase('button_send', $languageCode, $languageCode === 'zh' ? '发送' : 'Send'))}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z" fill="currentColor"/></svg>
                </button>
            </div>
        </form>
    </div>
</div>
HTML;
    }

    private function renderWechatPanel(string $languageCode): string
    {
        $wechatId = $this->primaryWechatId($languageCode);
        $wechatQr = $this->assetUrl('/assets/images/common/wechat-qr.png');
        $wechatTitle = $this->escape($this->phrase('wechat_title', $languageCode, 'WeChat'));
        $wechatClose = $this->escape($this->phrase('close_wechat_panel', $languageCode, 'Close WeChat panel'));
        $wechatDialog = $this->escape($this->phrase('wechat_dialog_label', $languageCode, 'WeChat QR code'));
        $wechatImageAlt = $this->escape($this->phrase('wechat_qr_alt', $languageCode, 'WeChat QR code'));

        return <<<HTML
<div class="wechat-panel" hidden data-wechat-panel>
    <button class="wechat-panel-backdrop" type="button" aria-label="{$wechatClose}" data-wechat-close></button>
    <div class="wechat-card" role="dialog" aria-modal="true" aria-label="{$wechatDialog}">
        <button class="wechat-card-close" type="button" aria-label="{$wechatClose}" data-wechat-close>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.8"/></svg>
        </button>
        <div class="wechat-card-head">
            <strong>{$wechatTitle}</strong>
            <span>{$this->escape($this->phrase('wechat_panel_hint', $languageCode, 'Scan the code or copy the WeChat ID'))}</span>
        </div>
        <figure class="wechat-qr"><img src="{$this->escape($wechatQr)}" alt="{$wechatImageAlt}" loading="lazy" decoding="async" data-progressive-media></figure>
        <div class="wechat-id"><span>{$this->escape($this->phrase('wechat_id', $languageCode, 'WeChat ID'))}</span><strong data-wechat-id>{$this->escape($wechatId)}</strong></div>
        <button class="wechat-copy" type="button" data-wechat-copy data-copy-value="{$this->escape($wechatId)}"><span>{$this->escape($this->phrase('wechat_copy', $languageCode, 'Copy WeChat ID'))}</span></button>
    </div>
</div>
HTML;
    }

    private function loadPublicTemplate(): string
    {
        return $this->rememberFragment('shell:public-template', function (): string {
            $candidates = [
                $this->projectRoot . '/index.template.html',
                $this->projectRoot . '/backend/templates/index.template.html',
            ];

            foreach ($candidates as $path) {
                $content = @file_get_contents($path);
                if ($content !== false && trim($content) !== '') {
                    return $content;
                }
            }

            throw new \RuntimeException('无法读取模板文件: ' . implode(' | ', $candidates));
        });
    }

    private function prepareTemplateShell(string $template): string
    {
        $cacheKey = 'shell:prepared-template:' . md5($template);

        return $this->rememberFragment($cacheKey, function () use ($template): string {
            $template = preg_replace('/<header class="site-header">.*?<\/header>/su', '{{site_header_html}}', $template, 1) ?? $template;
            $template = preg_replace('/<footer class="site-footer-redesign".*?<\/footer>.*?<footer class="site-footer">.*?<\/footer>/su', '{{site_footer_html}}', $template, 1) ?? $template;
            $template = preg_replace('/<div class="floating-contact".*?(?=<div class="bottom-contact-dock"|<div class="support-panel")/su', "{{floating_contact_html}}\n\n    ", $template, 1) ?? $template;
            $template = preg_replace('/<div class="bottom-contact-dock".*?(?=<div class="support-panel")/su', '', $template, 1) ?? $template;
            $template = preg_replace('/<div class="support-panel".*?(?=<div class="wechat-panel")/su', "{{support_panel_html}}\n\n    ", $template, 1) ?? $template;
            $template = preg_replace('/<div class="wechat-panel".*?(?=<script\b)/su', "{{wechat_panel_html}}\n\n    ", $template, 1) ?? $template;

            return $template;
        });
    }

    private function renderTemplatePage(
        string $template,
        string $languageCode,
        string $route,
        array $replacements,
        ?string $mainOverride,
        array $payload
    ): string {
        $site = $this->cachedSite($languageCode);
        $template = $this->localizedTemplateShell($template, $languageCode);
        $title = trim((string) ($payload['title'] ?? $this->companyName($languageCode)));
        $description = trim((string) ($payload['description'] ?? ''));
        if ($description === '') {
            $description = trim((string) ($site['meta_description'] ?? ''));
        }
        if ($description === '') {
            $description = $title;
        }
        $baseUrl = rtrim((string) env('APP_URL', 'https://bagelsmachinery.com'), '/');
        $canonical = $baseUrl . $route;
        $pageSeo = (new \app\repository\PageSeoRepository())->findByRoute($languageCode, $route);
        $keywords = '';
        if (is_array($pageSeo)) {
            $title = trim((string) ($pageSeo['seo_title'] ?? '')) !== '' ? trim((string) $pageSeo['seo_title']) : $title;
            $description = trim((string) ($pageSeo['seo_description'] ?? '')) !== '' ? trim((string) $pageSeo['seo_description']) : $description;
            $canonical = trim((string) ($pageSeo['canonical_url'] ?? '')) !== '' ? trim((string) $pageSeo['canonical_url']) : $canonical;
            $keywords = trim((string) ($pageSeo['seo_keywords'] ?? ''));
        } else {
            $seoRouteRepository = new \app\repository\SeoRepository();
            $normalizedRoute = preg_replace('/\.html$/', '', strtolower(trim($route))) ?? strtolower(trim($route));
            foreach ($seoRouteRepository->routes() as $routeItem) {
                $itemLanguage = strtolower(trim((string) ($routeItem['language_code'] ?? '')));
                if ($itemLanguage !== strtolower(trim($languageCode))) {
                    continue;
                }

                $routePath = strtolower(trim((string) ($routeItem['route_path'] ?? '')));
                if ($routePath === '' || $routePath !== $normalizedRoute) {
                    continue;
                }

                $title = trim((string) ($routeItem['seo_title'] ?? '')) !== '' ? trim((string) $routeItem['seo_title']) : $title;
                $description = trim((string) ($routeItem['seo_description'] ?? '')) !== '' ? trim((string) $routeItem['seo_description']) : $description;
                $canonical = trim((string) ($routeItem['canonical_url'] ?? '')) !== '' ? trim((string) $routeItem['canonical_url']) : $canonical;
                $keywords = trim((string) ($routeItem['seo_keywords'] ?? ''));
                break;
            }
        }
        $logoUrl = $this->assetUrl((string) ($site['logo_url'] ?? '/assets/images/common/logo-110.png'));
        $heroImage = $this->assetUrl((string) ($site['hero_image_url'] ?? '/assets/videos/home/hero-enterprise-showcase.webm'));
        $heroPosterImage = $this->assetUrl((string) ($site['hero_poster_url'] ?? '/assets/images/home/hero-first-frame.webp'));
        $ogImage = $this->assetUrl((string) ($payload['og_image'] ?? $heroPosterImage));
        $ogType = trim((string) ($payload['og_type'] ?? 'website'));
        if ($ogType === '') {
            $ogType = 'website';
        }
        $siteName = trim((string) ($site['site_name'] ?? ''));
        $heroAlt = trim((string) ($site['hero_image_alt'] ?? ''));
        if ($heroAlt === '') {
            $heroAlt = $this->phrase(
                'hero_image_alt',
                $languageCode,
                ''
            );
        }

        if ($mainOverride !== null) {
            $template = preg_replace('/<main id="top">.*?<\/main>/su', $mainOverride, $template, 1) ?? $template;
        }

        $template = preg_replace(
            '/<title>.*?<\/title>/isu',
            '<title>' . $this->escape($title) . '</title>',
            $template,
            1
        ) ?? $template;
        $template = preg_replace(
            '/<meta\s+property="og:type"\s+content="[^"]*"\s*\/?>/isu',
            '<meta property="og:type" content="' . $this->escape($ogType) . '">',
            $template,
            1
        ) ?? $template;

        $template = strtr($template, array_merge([
            '{{page_title_zh}}' => $this->escape($title),
            '{{meta_description_zh}}' => $this->escape($description),
            '{{meta_keywords}}' => $this->escape($keywords),
            '{{og_title_zh}}' => $this->escape($title),
            '{{og_description_zh}}' => $this->escape($description),
            '{{og_image_url}}' => $this->escape($ogImage),
            '{{canonical_url}}' => $this->escape($canonical),
            '{{site_name}}' => $this->escape($siteName !== '' ? $siteName : $this->companyName($languageCode)),
            '{{site_name_meta}}' => $this->escape($siteName !== '' ? $siteName : $this->companyName($languageCode)),
            '{{favicon_url}}' => $this->escape($logoUrl),
            '{{hero_image_url}}' => $this->escape($this->assetUrl((string) ($site['hero_image_url'] ?? '/assets/videos/home/hero-enterprise-showcase.webm'))),
            '{{hero_image_alt}}' => $this->escape($heroAlt),
            '{{hero_title}}' => $this->escape($this->resolveHeroCopy((string) ($site['hero_title'] ?? ''), 'hero_title', $languageCode, 'Full-Process Baking Solutions')),
            '{{hero_subtitle}}' => $this->escape($this->resolveHeroCopy((string) ($site['hero_subtitle'] ?? ''), 'hero_subtitle', $languageCode, 'From R&D customization to full-line delivery')),
            '{{hero_cta_primary}}' => $this->escape($this->resolveHeroCopy((string) ($site['hero_cta_primary'] ?? ''), 'hero_cta_primary', $languageCode, 'Get Solution')),
            '{{hero_cta_secondary}}' => $this->escape($this->resolveHeroCopy((string) ($site['hero_cta_secondary'] ?? ''), 'hero_cta_secondary', $languageCode, 'Learn More')),
            '{{hero_cta_secondary_href}}' => $this->escape($this->localizedRoute($languageCode, 'about.html')),
            '{{public_site_runtime_json}}' => $this->publicRuntimeJson(),
            '{{featured_solutions_html}}' => '',
            '{{featured_products_html}}' => '',
            '{{featured_cases_html}}' => '',
            '{{featured_news_html}}' => '',
            '{{footer_contact_html}}' => '',
            '{{footer_featured_products_html}}' => '',
            '{{footer_featured_solutions_html}}' => '',
            '{{public_scripts_html}}' => '',
        ], $replacements));

        $template = $this->injectAlternateLinks($template, $route);

        $extraCss = array_values(array_unique(array_filter([
            '/assets/css/public-shell-overrides.css?v=20260624-02',
            ...(is_array($payload['extra_css'] ?? null) ? $payload['extra_css'] : []),
        ], static fn (mixed $href): bool => is_string($href) && trim($href) !== '')));

        if ($extraCss !== []) {
            $styleLinks = implode("\n", array_map(
                fn (string $href): string => '<link rel="stylesheet" href="' . $this->escape($href) . '">',
                $extraCss
            ));
            $template = preg_replace('/<\/head>/i', $styleLinks . "\n</head>", $template, 1) ?? $template;
        }

        $structuredDataNodes = is_array($payload['structured_data_nodes'] ?? null) ? $payload['structured_data_nodes'] : [];
        $structuredDataMarkup = $this->structuredDataMarkup($structuredDataNodes);
        if ($structuredDataMarkup !== '') {
            $template = preg_replace('/<\/head>/i', $structuredDataMarkup . "\n</head>", $template, 1) ?? $template;
        }

        return $template;
    }

    private function localizedTemplateShell(string $template, string $languageCode): string
    {
        $cacheKey = 'shell:localized-template:' . strtolower(trim($languageCode)) . ':' . md5($template);

        return $this->rememberFragment($cacheKey, function () use ($template, $languageCode): string {
            return $this->localizeTemplateMarkup($this->prepareTemplateShell($template), $languageCode);
        });
    }

    private function structuredDataMarkup(array $nodes): string
    {
        $nodes = array_values(array_filter($nodes, static fn (mixed $node): bool => is_array($node) && $node !== []));
        if ($nodes === []) {
            return '';
        }

        $payload = count($nodes) === 1
            ? ['@context' => 'https://schema.org'] + $nodes[0]
            : ['@context' => 'https://schema.org', '@graph' => $nodes];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return '';
        }

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    private function absolutePublicUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        $baseUrl = rtrim((string) env('APP_URL', 'https://bagelsmachinery.com'), '/');
        if (!str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }

        return $baseUrl . $path;
    }

    private function injectAlternateLinks(string $template, string $route): string
    {
        $baseUrl = rtrim((string) env('APP_URL', 'https://bagelsmachinery.com'), '/');
        $languages = $this->resolveLanguageCodes([]);

        if ($languages === []) {
            return $template;
        }

        $links = [];
        foreach ($languages as $languageCode) {
            $alternateRoute = $this->alternateRouteForLanguage($route, $languageCode);
            $links[] = '<link rel="alternate" hreflang="' . $this->escape($languageCode) . '" href="' . $this->escape($baseUrl . $alternateRoute) . '">';
        }

        $defaultRoute = $this->alternateRouteForLanguage($route, $this->defaultLanguage());
        $links[] = '<link rel="alternate" hreflang="x-default" href="' . $this->escape($baseUrl . $defaultRoute) . '">';

        return preg_replace('/<\/head>/i', implode("\n", $links) . "\n</head>", $template, 1) ?? $template;
    }

    private function publicRuntimeJson(): string
    {
        $metaMap = [
            'zh' => ['country' => 'China', 'native' => '中文', 'content' => 'zh', 'htmlLang' => 'zh-CN', 'continent' => 'asia', 'flag_code' => 'cn'],
            'en' => ['country' => 'United Kingdom', 'native' => 'English', 'content' => 'en', 'htmlLang' => 'en-GB', 'continent' => 'europe', 'flag_code' => 'gb'],
            'es' => ['country' => 'Spain', 'native' => 'Español', 'content' => 'es', 'htmlLang' => 'es-ES', 'continent' => 'europe', 'flag_code' => 'es'],
            'hi' => ['country' => 'India', 'native' => 'हिन्दी', 'content' => 'hi', 'htmlLang' => 'hi-IN', 'continent' => 'asia', 'flag_code' => 'in'],
            'ar' => ['country' => 'United Arab Emirates', 'native' => 'العربية', 'content' => 'ar', 'htmlLang' => 'ar-AE', 'continent' => 'asia', 'flag_code' => 'ae'],
            'fr' => ['country' => 'France', 'native' => 'Français', 'content' => 'fr', 'htmlLang' => 'fr-FR', 'continent' => 'europe', 'flag_code' => 'fr'],
            'de' => ['country' => 'Germany', 'native' => 'Deutsch', 'content' => 'de', 'htmlLang' => 'de-DE', 'continent' => 'europe', 'flag_code' => 'de'],
            'ja' => ['country' => 'Japan', 'native' => '日本語', 'content' => 'ja', 'htmlLang' => 'ja-JP', 'continent' => 'asia', 'flag_code' => 'jp'],
            'pt' => ['country' => 'Portugal', 'native' => 'Português', 'content' => 'pt', 'htmlLang' => 'pt-PT', 'continent' => 'europe', 'flag_code' => 'pt'],
            'ru' => ['country' => 'Russia', 'native' => 'Русский', 'content' => 'ru', 'htmlLang' => 'ru-RU', 'continent' => 'europe', 'flag_code' => 'ru'],
            'it' => ['country' => 'Italy', 'native' => 'Italiano', 'content' => 'it', 'htmlLang' => 'it-IT', 'continent' => 'europe', 'flag_code' => 'it'],
            'ko' => ['country' => 'South Korea', 'native' => '한국어', 'content' => 'ko', 'htmlLang' => 'ko-KR', 'continent' => 'asia', 'flag_code' => 'kr'],
            'tr' => ['country' => 'Turkey', 'native' => 'Türkçe', 'content' => 'tr', 'htmlLang' => 'tr-TR', 'continent' => 'asia', 'flag_code' => 'tr'],
            'nl' => ['country' => 'Netherlands', 'native' => 'Nederlands', 'content' => 'nl', 'htmlLang' => 'nl-NL', 'continent' => 'europe', 'flag_code' => 'nl'],
            'pl' => ['country' => 'Poland', 'native' => 'Polski', 'content' => 'pl', 'htmlLang' => 'pl-PL', 'continent' => 'europe', 'flag_code' => 'pl'],
            'vi' => ['country' => 'Vietnam', 'native' => 'Tiếng Việt', 'content' => 'vi', 'htmlLang' => 'vi-VN', 'continent' => 'asia', 'flag_code' => 'vn'],
            'th' => ['country' => 'Thailand', 'native' => 'ไทย', 'content' => 'th', 'htmlLang' => 'th-TH', 'continent' => 'asia', 'flag_code' => 'th'],
            'sv' => ['country' => 'Sweden', 'native' => 'Svenska', 'content' => 'sv', 'htmlLang' => 'sv-SE', 'continent' => 'europe', 'flag_code' => 'se'],
            'id' => ['country' => 'Indonesia', 'native' => 'Bahasa Indonesia', 'content' => 'id', 'htmlLang' => 'id-ID', 'continent' => 'asia', 'flag_code' => 'id'],
            'el' => ['country' => 'Greece', 'native' => 'Ελληνικά', 'content' => 'el', 'htmlLang' => 'el-GR', 'continent' => 'europe', 'flag_code' => 'gr'],
            'cs' => ['country' => 'Czech Republic', 'native' => 'Čeština', 'content' => 'cs', 'htmlLang' => 'cs-CZ', 'continent' => 'europe', 'flag_code' => 'cz'],
            'hu' => ['country' => 'Hungary', 'native' => 'Magyar', 'content' => 'hu', 'htmlLang' => 'hu-HU', 'continent' => 'europe', 'flag_code' => 'hu'],
            'ro' => ['country' => 'Romania', 'native' => 'Română', 'content' => 'ro', 'htmlLang' => 'ro-RO', 'continent' => 'europe', 'flag_code' => 'ro'],
            'uk' => ['country' => 'Ukraine', 'native' => 'Українська', 'content' => 'uk', 'htmlLang' => 'uk-UA', 'continent' => 'europe', 'flag_code' => 'ua'],
            'ms' => ['country' => 'Malaysia', 'native' => 'Bahasa Melayu', 'content' => 'ms', 'htmlLang' => 'ms-MY', 'continent' => 'asia', 'flag_code' => 'my'],
        ];

        $languages = array_map(function (array $language) use ($metaMap): array {
            $code = strtolower(trim((string) ($language['code'] ?? 'zh')));
            $normalized = str_replace('_', '-', $code);
            $normalized = str_starts_with($normalized, 'zh') ? 'zh' : substr($normalized, 0, 2);
            $meta = $metaMap[$normalized] ?? null;
            $native = trim((string) ($language['name'] ?? ''));
            $flagCode = $meta['flag_code'] ?? (preg_match('/^[a-z]{2}$/', $normalized) ? $normalized : 'cn');

            return [
                'code' => $normalized,
                'country' => $meta['country'] ?? strtoupper($normalized),
                'native' => $native !== '' ? $native : ($meta['native'] ?? strtoupper($normalized)),
                'content' => $meta['content'] ?? $normalized,
                'htmlLang' => $meta['htmlLang'] ?? $normalized,
                'continent' => $meta['continent'] ?? 'global',
                'flag_code' => $flagCode,
            ];
        }, $this->enabledLanguages());

        if ($languages === []) {
            $languages = [
                ['code' => 'zh', 'country' => 'China', 'native' => '中文', 'content' => 'zh', 'htmlLang' => 'zh-CN', 'continent' => 'asia', 'flag_code' => 'cn'],
                ['code' => 'en', 'country' => 'United Kingdom', 'native' => 'English', 'content' => 'en', 'htmlLang' => 'en-GB', 'continent' => 'europe', 'flag_code' => 'gb'],
            ];
        }

        return json_encode([
            'languages' => array_values(array_unique($languages, SORT_REGULAR)),
            'defaultLanguage' => $this->defaultLanguage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"languages":[{"code":"zh","country":"China","native":"中文","content":"zh","htmlLang":"zh-CN","continent":"asia","flag_code":"cn"}],"defaultLanguage":"zh"}';
    }

    private function localizeTemplateMarkup(string $markup, string $languageCode): string
    {
        $htmlLang = $languageCode === 'zh' ? 'zh-CN' : strtolower($languageCode);
        $forceLang = $this->escape($languageCode);

        $markup = preg_replace('/<html\s+lang="[^"]+"/i', '<html lang="' . $htmlLang . '"', $markup, 1) ?? $markup;
        $markup = preg_replace('/<body\s+data-lang="[^"]*">/i', '<body data-lang="' . $forceLang . '" data-force-lang="' . $forceLang . '">', $markup, 1) ?? $markup;
        $markup = preg_replace('/\b(src|href|poster|data-src)=("|\')assets\//i', '$1=$2/assets/', $markup) ?? $markup;

        $routeMap = [
            'about.html#about' => $this->aboutAnchorRoute($languageCode),
            'about.html#contact' => $this->contactAnchorRoute($languageCode),
            'index.html' => $this->localizedRoute($languageCode, 'index'),
            'about.html' => $this->localizedRoute($languageCode, 'about'),
            'contact.html' => $this->localizedRoute($languageCode, 'contact'),
            'products.html' => $this->localizedRoute($languageCode, 'products'),
            'solutions.html' => $this->localizedRoute($languageCode, 'solutions'),
            'news.html' => $this->localizedRoute($languageCode, 'news'),
            'cases.html' => $this->localizedRoute($languageCode, 'cases'),
        ];

        foreach ($routeMap as $from => $to) {
            $markup = str_replace('href="' . $from . '"', 'href="' . $this->escape($to) . '"', $markup);
        }

        $markup = $this->applyLocalizedTemplateAttribute($markup, $languageCode, 'placeholder');
        $markup = $this->applyLocalizedTemplateAttribute($markup, $languageCode, 'alt');
        $markup = $this->applyLocalizedTemplateAttribute($markup, $languageCode, 'aria-label');
        $markup = $this->applyLocalizedTemplateText($markup, $languageCode);
        $markup = $this->cleanupGeneratedMarkup($markup, $languageCode);

        return $markup;
    }

    private function cleanupGeneratedMarkup(string $markup, string $languageCode): string
    {
        $replacements = [];

        foreach ($replacements as $from => $to) {
            $markup = str_replace($from, $to, $markup);
        }

        return $markup;
    }

    private function applyLocalizedTemplateAttribute(string $markup, string $languageCode, string $attribute): string
    {
        $variantAttribute = 'data-' . strtolower($languageCode) . '-' . strtolower($attribute);

        return preg_replace_callback(
            '/<(?P<tag>[a-z0-9:-]+)(?P<attrs>[^>]*\s' . preg_quote($variantAttribute, '/') . '="[^"]*"[^>]*)>/isu',
            function (array $matches) use ($attribute, $variantAttribute): string {
                $tag = (string) ($matches['tag'] ?? '');
                $attrs = (string) ($matches['attrs'] ?? '');
                if ($tag === '' || $attrs === '') {
                    return $matches[0];
                }

                if (preg_match('/\b' . preg_quote($variantAttribute, '/') . '="([^"]*)"/iu', $attrs, $valueMatch) !== 1) {
                    return $matches[0];
                }

                $localizedValue = $this->escape(html_entity_decode((string) ($valueMatch[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $targetPattern = '/\s' . preg_quote($attribute, '/') . '="[^"]*"/iu';

                if (preg_match($targetPattern, $attrs) === 1) {
                    $attrs = preg_replace($targetPattern, ' ' . $attribute . '="' . $localizedValue . '"', $attrs, 1) ?? $attrs;
                } else {
                    $attrs .= ' ' . $attribute . '="' . $localizedValue . '"';
                }

                return '<' . $tag . $attrs . '>';
            },
            $markup
        ) ?? $markup;
    }

    private function applyLocalizedTemplateText(string $markup, string $languageCode): string
    {
        return preg_replace_callback(
            '/<(?P<tag>[a-z0-9:-]+)(?P<attrs>[^>]*)>(?P<content>[^<]*)<\/(?P=tag)>/isu',
            function (array $matches) use ($languageCode): string {
                $attrs = (string) ($matches['attrs'] ?? '');
                if ($attrs === '' || !str_contains($attrs, 'data-zh=') || !str_contains($attrs, 'data-en=')) {
                    return $matches[0];
                }

                $selectedPattern = $languageCode === 'zh' ? '/\bdata-zh="([^"]*)"/iu' : '/\bdata-en="([^"]*)"/iu';
                if (preg_match($selectedPattern, $attrs, $valueMatch) !== 1) {
                    return $matches[0];
                }

                return '<' . (string) ($matches['tag'] ?? '') . $attrs . '>'
                    . $this->escape(html_entity_decode((string) ($valueMatch[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                    . '</' . (string) ($matches['tag'] ?? '') . '>';
            },
            $markup
        ) ?? $markup;
    }

    private function renderHomepageSolutionsHtml(array $items): string
    {
        $cards = '';
        foreach (array_slice($items, 0, 6) as $item) {
            $title = (string) ($item['title'] ?? $item['name'] ?? '');
            if ($title === '') {
                continue;
            }
            $summary = $this->excerpt((string) ($item['summary'] ?? $item['description'] ?? ''), 40);
            $cards .= '<article class="delivery-card reveal">';
            $cards .= '<figure class="delivery-media"><img src="' . $this->assetUrl((string) ($item['cover_image_url'] ?? '')) . '" alt="' . $this->escape($title) . '" loading="lazy" decoding="async" data-progressive-media></figure>';
            $cards .= '<h3>' . $this->escape($title) . '</h3>';
            if ($summary !== '') {
                $cards .= '<p>' . $this->escape($summary) . '</p>';
            }
            $cards .= '</article>';
        }

        if ($cards === '') {
            return '';
        }

        return '<section class="section section-delivery" id="delivery">'
            . '<div class="container">'
            . '<div class="delivery-grid">' . $cards . '</div>'
            . '</div>'
            . '</section>';
    }

    private function renderHomepageProductsHtml(array $items, string $languageCode): string
    {
        $items = $this->filterRenderableItems($items, 'product');
        if ($items === []) {
            return '';
        }

        $featured = $items[0];
        $featuredTitle = (string) ($featured['name'] ?? $featured['title'] ?? '');
        if ($featuredTitle === '') {
            return '';
        }

        $html = '<article class="showcase-feature-card">';
        $html .= '<a href="' . $this->localizedRoute($languageCode, 'products/' . (string) ($featured['slug'] ?? '')) . '">';
        $html .= '<figure class="showcase-feature-media"><img src="' . $this->assetUrl((string) ($featured['cover_image_url'] ?? '')) . '" alt="' . $this->escape($featuredTitle) . '" loading="lazy" decoding="async" data-progressive-media></figure>';
        $html .= '<div class="showcase-feature-copy"><span class="showcase-kicker">' . $this->escape((string) ($featured['category_name'] ?? $this->phrase('page_products', $languageCode, 'Products'))) . '</span><h3>' . $this->escape($featuredTitle) . '</h3></div>';
        $html .= '</a></article><div class="showcase-side-list">';

        foreach (array_slice($items, 1, 6) as $item) {
            $title = (string) ($item['name'] ?? $item['title'] ?? '');
            if ($title === '') {
                continue;
            }
            $html .= '<article class="showcase-mini-card">';
            $html .= '<a href="' . $this->localizedRoute($languageCode, 'products/' . (string) ($item['slug'] ?? '')) . '">';
            $html .= '<figure class="showcase-mini-media"><img src="' . $this->assetUrl((string) ($item['cover_image_url'] ?? '')) . '" alt="' . $this->escape($title) . '" loading="lazy" decoding="async" data-progressive-media></figure>';
            $html .= '<div class="showcase-mini-copy"><span class="showcase-kicker">' . $this->escape((string) ($item['category_name'] ?? $this->phrase('page_products', $languageCode, 'Products'))) . '</span><h3>' . $this->escape($title) . '</h3></div>';
            $html .= '</a></article>';
        }

        $html .= '</div>';

        return '<section class="section section-showcase" id="showcase">'
            . '<div class="container">'
            . '<div class="showcase-split">' . $html . '</div>'
            . '</div>'
            . '</section>';
    }

    private function renderHomepageCasesHtml(array $items, string $languageCode): string
    {
        $items = $this->filterRenderableItems($items, 'case');
        if ($items === []) {
            return '';
        }

        $hero = $items[0];
        $heroTitle = (string) ($hero['title'] ?? $hero['name'] ?? '');
        if ($heroTitle === '') {
            return '';
        }

        $html = '<article class="case-hero-card reveal">';
        $html .= '<figure class="case-hero-media"><img src="' . $this->assetUrl((string) ($hero['cover_image_url'] ?? '')) . '" alt="' . $this->escape($heroTitle) . '" loading="lazy" decoding="async" data-progressive-media></figure>';
        $html .= '<div class="case-hero-copy"><div class="case-hero-flags">' . $this->renderCountryFlagHtml((string) ($hero['country_code'] ?? '')) . '</div><h2 class="case-hero-title">' . $this->escape($heroTitle) . '</h2></div>';
        $html .= '<a class="case-hero-link" href="' . $this->localizedRoute($languageCode, 'cases/' . (string) ($hero['slug'] ?? '')) . '" aria-label="' . $this->escape($heroTitle) . '"></a>';
        $html .= '</article><div class="case-list-panel">';

        foreach (array_slice($items, 1, 4) as $item) {
            $title = (string) ($item['title'] ?? $item['name'] ?? '');
            if ($title === '') {
                continue;
            }
            $html .= '<article class="case-list-item reveal">';
            $html .= '<figure class="case-list-media"><img src="' . $this->assetUrl((string) ($item['cover_image_url'] ?? '')) . '" alt="' . $this->escape($title) . '" loading="lazy" decoding="async" data-progressive-media></figure>';
            $html .= '<div class="case-list-copy"><div class="case-title-row"><span class="case-title-flags">' . $this->renderCountryFlagHtml((string) ($item['country_code'] ?? '')) . '</span><h3 class="case-title-text">' . $this->escape($title) . '</h3></div></div>';
            $html .= '<a class="case-list-link" href="' . $this->localizedRoute($languageCode, 'cases/' . (string) ($item['slug'] ?? '')) . '" aria-label="' . $this->escape($title) . '"></a>';
            $html .= '</article>';
        }

        $html .= '</div>';

        return '<section class="section section-cases" id="cases">'
            . '<div class="container">'
            . '<div class="cases-board">' . $html . '</div>'
            . '</div>'
            . '</section>';
    }

    private function renderHomepageNewsHtml(array $items, string $languageCode): string
    {
        $items = $this->filterRenderableItems($items, 'news');
        $cards = '';
        foreach (array_slice($items, 0, 5) as $item) {
            $title = (string) ($item['title'] ?? $item['name'] ?? '');
            if ($title === '') {
                continue;
            }
            $tag = (string) ($item['category_name'] ?? $item['summary'] ?? '');
            $cards .= '<article class="news-card reveal">';
            $cards .= '<figure class="news-media"><img src="' . $this->assetUrl((string) ($item['cover_image_url'] ?? '')) . '" alt="' . $this->escape($title) . '" loading="lazy" decoding="async" data-progressive-media></figure>';
            $cards .= '<div class="news-card-copy">';
            if ($tag !== '') {
                $cards .= '<span class="news-card-tag">' . $this->escape($this->excerpt($tag, 32)) . '</span>';
            }
            $cards .= '<h3>' . $this->escape($title) . '</h3></div>';
            $cards .= '<a class="news-card-link" href="' . $this->localizedRoute($languageCode, 'news/' . (string) ($item['slug'] ?? '')) . '" aria-label="' . $this->escape($title) . '"></a>';
            $cards .= '</article>';
        }

        if ($cards === '') {
            return '';
        }

        return '<section class="section section-news" id="news">'
            . '<div class="container">'
            . '<div class="news-grid">' . $cards . '</div>'
            . '</div>'
            . '</section>';
    }

    private function renderHomepageNoticeHtml(array $site, string $languageCode): string
    {
        // 优先调用广告系统 homepage_featured 位置的广告
        $ads = $this->cachedAds($languageCode, 'home');
        $featuredAd = null;
        foreach ($ads as $ad) {
            if ((string) ($ad['position_key'] ?? '') === 'homepage_featured') {
                $featuredAd = $ad;
                break;
            }
        }

        if ($featuredAd !== null) {
            $image = trim((string) ($featuredAd['image_url'] ?? ''));
            $title = trim((string) ($featuredAd['title'] ?? ''));
            $linkedSlug = trim((string) ($featuredAd['linked_page_slug'] ?? ''));
            $linkedTitle = trim((string) ($featuredAd['linked_page_title'] ?? ''));
            $openNewTab = (int) ($featuredAd['open_in_new_tab'] ?? 0) === 1;

            if ($image === '') {
                $image = '/assets/images/home/company-strength-real.jpg';
            }
            if ($title === '') {
                $title = $this->phrase('notice_slot_title', $languageCode, '');
            }

            $href = $linkedSlug !== '' ? $this->localizedRoute($languageCode, $linkedSlug) : '';
            $targetAttr = $openNewTab ? ' target="_blank" rel="noopener"' : '';
            $tagOpen = $href !== '' ? '<a class="notice-banner" href="' . $this->escape($href) . '"' . $targetAttr . '>' : '<article class="notice-banner">';
            $tagClose = $href !== '' ? '</a>' : '</article>';

            return '<section class="section section-notice-strip" id="notice-banner"><div class="container">' . $tagOpen . '<figure class="notice-banner-media"><img src="' . $this->escape($this->assetUrl($image)) . '" alt="' . $this->escape($title) . '" loading="lazy" decoding="async" data-progressive-media></figure><div class="notice-banner-copy"><span>' . $this->escape($title) . '</span>' . ($linkedTitle !== '' ? '<strong>' . $this->escape($linkedTitle) . '</strong>' : '') . '</div>' . $tagClose . '</div></section>';
        }

        // Fallback: 使用 site notice_* 配置或 phrase
        $image = trim((string) ($site['notice_image_url'] ?? '')) !== '' ? (string) ($site['notice_image_url'] ?? '') : '/assets/images/home/company-strength-real.jpg';
        $title = trim((string) ($site['notice_title'] ?? ''));
        $content = trim((string) ($site['notice_content'] ?? ''));
        $title = $title !== '' ? $title : $this->phrase('notice_slot_title', $languageCode, '');
        $content = $content !== '' ? $content : $this->phrase('notice_slot_copy', $languageCode, '');
        return '<section class="section section-notice-strip" id="notice-banner"><div class="container"><article class="notice-banner reveal"><figure class="notice-banner-media"><img src="' . $this->escape($this->assetUrl($image)) . '" alt="' . $this->escape($title) . '" loading="lazy" decoding="async" data-progressive-media></figure><div class="notice-banner-copy"><span>' . $this->escape($title) . '</span><strong>' . $this->escape($content) . '</strong></div></article></div></section>';
    }

    private function renderHomepageMetricsHtml(array $about, string $languageCode): string
    {
        $intro = $this->extractAboutIntro($about);
        $image = trim((string) ($this->extractAboutImage($about) ?: '/assets/images/home/company-service-team-real.jpg'));
        $blocks = is_array($about['blocks'] ?? null) ? $about['blocks'] : [];
        $certificates = $this->extractAboutItems($blocks, ['certificate', 'certificate_list']);
        $flowItems = $this->homepageFlowItems($languageCode);

        $html = '<section class="section section-metrics" id="clients"><div class="container">';
        $html .= '<div class="section-heading reveal"><span class="eyebrow">' . $this->escape($this->phrase('service_capability_heading', $languageCode, $languageCode === 'zh' ? '服务能力' : 'Service Capability')) . '</span></div>';
        $html .= '<article class="metrics-dashboard reveal"><figure class="metrics-dashboard-visual"><img src="' . $this->assetUrl($image) . '" alt="' . $this->escape($this->companyName($languageCode)) . '" loading="lazy" decoding="async" data-progressive-media>';
        $html .= '</figure><div class="metrics-dashboard-copy"><div class="metrics-capability-combo">';
        $html .= '<section class="metrics-capability-section metrics-capability-flow"><h3>' . $this->escape($this->phrase('cooperation_flow_title', $languageCode, $languageCode === 'zh' ? '合作流程' : 'Cooperation Flow')) . '</h3><div class="metrics-flow-list">';
        foreach ($flowItems as $item) {
            $html .= '<article class="metrics-flow-item"><span class="metrics-flow-icon" aria-hidden="true">' . $item['icon'] . '</span><div><strong>' . $this->escape($item['label']) . '</strong></div></article>';
        }
        $html .= '</div></section>';
        $html .= '<section class="metrics-capability-section metrics-capability-certs"><h3>' . $this->escape($this->phrase('qualifications_title', $languageCode, $languageCode === 'zh' ? '资质证书' : 'Qualifications')) . '</h3><div class="metrics-cert-grid">';
        foreach (array_slice($certificates, 0, 5) as $item) {
            $name = trim((string) ($item['name'] ?? 'Certificate'));
            $imageUrl = trim((string) ($item['image_asset_url'] ?? $item['image_url'] ?? ''));
            if ($imageUrl === '') {
                continue;
            }
            $html .= '<article class="metrics-cert-card"><figure class="metrics-cert-media"><img src="' . $this->assetUrl($imageUrl) . '" alt="' . $this->escape($name) . '" loading="lazy" decoding="async" data-progressive-media></figure><span>' . $this->escape($name) . '</span></article>';
        }
        $html .= '</div></section></div></div></article></div></section>';

        return $html;
    }

    private function renderHomepageSalesHtml(array $about, string $languageCode): string
    {
        $blocks = is_array($about['blocks'] ?? null) ? $about['blocks'] : [];
        $team = $this->extractAboutItems($blocks, ['team', 'team_list']);
        if ($team === []) {
            return '';
        }

        $html = '<section class="section section-sales" id="sales-team"><div class="container"><div class="sales-loop loop-strip" data-loop-strip data-loop-step-delay="1800"><div class="sales-grid sales-track loop-track" data-loop-track>';
        foreach ($team as $member) {
            $name = trim((string) ($member['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $image = trim((string) ($member['avatar_asset_url'] ?? ''));
            $html .= '<article class="sales-card"><figure class="sales-avatar">';
            if ($image !== '') {
                $html .= '<img src="' . $this->assetUrl($image) . '" alt="' . $this->escape($name) . '" loading="lazy" decoding="async" data-progressive-media>';
            }
            $html .= '<figcaption class="sales-name-bar"><strong>' . $this->escape($name) . '</strong></figcaption></figure><div class="sales-copy">';
            foreach ($this->memberActionLinks($member, $languageCode) as $action) {
                $classSuffix = $this->escape((string) ($action['key'] ?? 'contact'));
                $html .= '<a class="sales-contact-link sales-contact-' . $classSuffix . '" href="' . $this->escape((string) $action['href']) . '"' . (str_starts_with((string) $action['href'], 'http') ? ' target="_blank" rel="noopener"' : '') . '>' . $this->escape((string) $action['label']) . '</a>';
            }
            $html .= '</div></article>';
        }
        $html .= '</div></div></div></section>';

        return $html;
    }

    private function renderHomepageContactHtml(string $languageCode): string
    {
        $payload = $this->cachedContact($languageCode);
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        $html = '<section class="section section-contact" id="contact-form"><div class="container contact-panel reveal"><div class="contact-form-panel"><div class="contact-panel-head"><span class="eyebrow">' . $this->escape($this->phrase('nav_contact', $languageCode, $languageCode === 'zh' ? '联系工厂' : 'Contact Factory')) . '</span></div>';
        $html .= '<form class="lead-form" action="javascript:void(0)"><div class="lead-form-grid">';
        $html .= '<label><span>' . $this->escape($this->phrase('form_contact_name', $languageCode, '')) . '</span><input type="text" name="name" placeholder="' . $this->escape($this->phrase('form_contact_name_placeholder', $languageCode, $languageCode === 'zh' ? '请输入联系人' : 'Name')) . '"></label>';
        $html .= '<label><span>' . $this->escape($this->phrase('form_phone_with_code', $languageCode, $languageCode === 'zh' ? '国家区号 + 电话' : 'Country Code + Phone')) . '</span><input type="tel" name="phone" placeholder=""></label>';
        $html .= '</div><label><span>' . $this->escape($this->phrase('form_email', $languageCode, 'Email')) . '</span><input type="email" name="email" placeholder="' . $this->escape($this->phrase('form_email_placeholder', $languageCode, '')) . '"></label>';
        $html .= '<label><span>' . $this->escape($this->phrase('form_message', $languageCode, '')) . '</span><textarea name="message" rows="3" placeholder="' . $this->escape($this->phrase('form_message_placeholder', $languageCode, '')) . '"></textarea></label>';
        $html .= '<div class="lead-form-actions"><button class="button button-primary" type="submit">' . $this->escape($this->phrase('button_submit_inquiry', $languageCode, $languageCode === 'zh' ? '提交联系信息' : 'Submit Inquiry')) . '</button></div></form></div>';
        $html .= '<div class="contact-info-panel"><div class="contact-info-shell"><div class="contact-grid" data-contact-grid>';
        foreach ($this->renderHomepageContactCards($items, $languageCode) as $card) {
            $html .= $card;
        }
        $html .= '</div></div></div></div></section>';

        return $html;
    }

    private function renderFooterContactCardsHtml(string $languageCode): string
    {
        return $this->renderFooterPrimaryContactHtml($languageCode) . $this->renderFooterSocialLinksHtml($languageCode);
    }

    private function renderFooterFeaturedLinksHtml(string $heading, array $items, string $entityType, string $languageCode): string
    {
        $html = '<h3>' . $this->escape($heading) . '</h3>';
        foreach ($items as $item) {
            $title = (string) ($item['name'] ?? $item['title'] ?? '');
            $slug = (string) ($item['slug'] ?? '');
            if ($title === '' || $slug === '') {
                continue;
            }
            $routeKey = match ($entityType) {
                'product' => 'products/' . $slug,
                'solution' => 'solutions/' . $slug,
                'news' => 'news/' . $slug,
                'case' => 'cases/' . $slug,
                default => $slug,
            };
            $html .= '<a href="' . $this->localizedRoute($languageCode, $routeKey) . '">' . $this->escape($title) . '</a>';
        }

        return $html;
    }

    private function renderHomepageVideoSupportHtmlV2(array $site, string $languageCode): string
    {
        $videoUrl = trim((string) ($site['enterprise_video_url'] ?? ''));
        if ($videoUrl === '') {
            $videoUrl = '/assets/videos/factory-showcase.mp4';
        }

        $heroImage = trim((string) ($site['hero_image_url'] ?? ''));
        $poster = $this->assetUrl((string) ($site['enterprise_video_poster_url'] ?? '/assets/images/home/hero-enterprise-showcase.webp'));
        // 优先 site config，fallback phrase，再 fallback 默认标题
        $title = trim((string) ($site['service_support_title'] ?? ''));
        if ($title === '') {
            $title = $this->phrase('service_support_title', $languageCode, $languageCode === 'zh' ? '服务能力' : 'Service Capability');
        }

        $html = '<section class="section section-video" id="factory-video"><div class="container video-panel reveal"><div class="video-frame">';
        $html .= '<video data-src="' . $this->escape($this->assetUrl($videoUrl)) . '" poster="' . $this->escape($poster) . '" controls playsinline webkit-playsinline x5-playsinline preload="none" data-progressive-video></video>';
        $html .= '</div><div class="video-copy service-capability-copy service-support-copy">';
        $html .= '<h2>' . $this->escape($title) . '</h2><div class="service-support-list">';
        foreach ($this->serviceCapabilityLines($languageCode, $site) as $line) {
            if ($line === '') {
                continue;
            }
            $html .= '<article class="service-support-item"><span class="service-support-icon" aria-hidden="true">&#10003;</span><strong>' . $this->escape($line) . '</strong></article>';
        }
        $html .= '</div><a class="button button-primary service-support-button" href="#contact-form">' . $this->escape($this->phrase('button_get_solution_detail', $languageCode, $languageCode === 'zh' ? '点击获取详细方案' : 'Click for Detailed Solution')) . '</a>';
        $html .= '</div></div></section>';

        return $html;
    }

    private function renderHomepageContactHtmlV2(string $languageCode): string
    {
        $payload = $this->cachedContact($languageCode);
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        $html = '<section class="section section-contact" id="contact-form"><div class="container contact-panel reveal"><div class="contact-form-panel"><div class="contact-panel-head"><span class="eyebrow">' . $this->escape($this->phrase('nav_contact', $languageCode, $languageCode === 'zh' ? '联系工厂' : 'Contact Factory')) . '</span></div>';
        $html .= '<form class="lead-form" action="javascript:void(0)"><div class="lead-form-grid">';
        $html .= '<label><span>' . $this->escape($this->phrase('form_contact_name', $languageCode, '')) . '</span><input type="text" name="name" placeholder="' . $this->escape($this->phrase('form_contact_name_placeholder', $languageCode, $languageCode === 'zh' ? '请输入联系人' : 'Name')) . '"></label>';
        $html .= '<label><span>' . $this->escape($this->phrase('form_phone_with_code', $languageCode, $languageCode === 'zh' ? '国家区号 + 电话' : 'Country Code + Phone')) . '</span><input type="tel" name="phone" placeholder=""></label>';
        $html .= '</div><label><span>' . $this->escape($this->phrase('form_email', $languageCode, 'Email')) . '</span><input type="email" name="email" placeholder="' . $this->escape($this->phrase('form_email_placeholder', $languageCode, '')) . '"></label>';
        $html .= '<label><span>' . $this->escape($this->phrase('form_message', $languageCode, '')) . '</span><textarea name="message" rows="3" placeholder="' . $this->escape($this->phrase('form_message_placeholder', $languageCode, '')) . '"></textarea></label>';
        $html .= '<div class="lead-form-actions"><button class="button button-primary" type="submit">' . $this->escape($this->phrase('button_submit_inquiry', $languageCode, $languageCode === 'zh' ? '提交联系信息' : 'Submit Inquiry')) . '</button></div></form></div>';
        $html .= '<div class="contact-info-panel"><div class="contact-info-shell"><div class="contact-grid" data-contact-grid>';
        foreach ($this->renderHomepageContactCardsV2($items, $languageCode) as $card) {
            $html .= $card;
        }
        $html .= '</div></div></div></div></section>';

        return $html;
    }

    private function renderHomepageContactCardsV2(array $items, string $languageCode): array
    {
        $cards = [];
        foreach ($items as $item) {
            $label = trim((string) ($item['label'] ?? $item['field_name'] ?? ''));
            $value = trim((string) ($item['field_value'] ?? $item['value'] ?? ''));
            $fieldKey = strtolower(trim((string) ($item['field_key'] ?? '')));
            if ($value === '') {
                continue;
            }

            $icon = match ($fieldKey) {
                'email' => '<svg viewBox="0 0 24 24"><path d="M3 6.75h18v10.5H3z" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m4.5 8 7.5 6 7.5-6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/></svg>',
                'phone' => '<svg viewBox="0 0 24 24"><path d="M6.6 10.8c1.6 3.2 3.8 5.4 7 7l2.3-2.3a1.4 1.4 0 0 1 1.4-.35c1.1.36 2.3.55 3.5.55A1.2 1.2 0 0 1 22 16.95V21a1.2 1.2 0 0 1-1.2 1.2C11.2 22.2 1.8 12.8 1.8 3.2A1.2 1.2 0 0 1 3 2h4.05a1.2 1.2 0 0 1 1.2 1.2c0 1.2.19 2.4.55 3.5a1.4 1.4 0 0 1-.35 1.4z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/></svg>',
                default => '<svg viewBox="0 0 24 24"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="10" r="2.3" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>',
            };

            $href = match ($fieldKey) {
                'email' => 'mailto:' . $value,
                'phone' => 'tel:' . preg_replace('/\s+/', '', $value),
                'whatsapp' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $value),
                'line' => 'https://line.me/R/ti/p/~' . rawurlencode($value),
                'address' => 'https://maps.google.com/?q=' . rawurlencode($value),
                default => '#',
            };

            $tag = $fieldKey === 'address' ? 'div' : 'a';
            $target = in_array($fieldKey, ['whatsapp', 'line'], true) ? ' target="_blank" rel="noopener"' : '';
            $open = $tag === 'a' ? '<a class="contact-card' . ($fieldKey === 'address' ? ' contact-card-wide' : '') . '" href="' . $this->escape($href) . '"' . $target . '>' : '<div class="contact-card contact-card-wide">';
            $close = $tag === 'a' ? '</a>' : '</div>';

            $cards[] = $open
                . '<div class="contact-card-head"><span class="contact-card-icon" aria-hidden="true">' . $icon . '</span><small>' . $this->escape($label !== '' ? $label : $this->phrase('footer_contact', $languageCode, 'Contact')) . '</small></div>'
                . '<strong><span>' . $this->escape($value) . '</span></strong>'
                . $close;
        }

        return $cards;
    }

    private function collectPrimaryContactsV2(string $languageCode): array
    {
        return array_slice($this->collectScopedContacts($languageCode, ['footer']), 0, 6);
    }

    private function renderCountryFlagHtml(string $countryCode): string
    {
        $code = strtolower(substr(trim($countryCode), 0, 2));
        if ($code === '') {
            return '';
        }

        return '<img src="/assets/images/flags/' . $this->escape($code) . '.svg" alt="' . $this->escape(strtoupper($code)) . ' flag" loading="lazy" decoding="async">';
    }

    private function renderListingCard(array $item, string $href, string $categorySlug = '', string $entityType = 'generic', string $languageCode = 'en'): string
    {
        $title = (string) ($item['title'] ?? $item['name'] ?? '');
        $summary = (string) ($item['summary'] ?? $item['description'] ?? $item['content'] ?? '');
        $kicker = trim((string) ($item['category_name'] ?? ''));
        if ($kicker === '') {
            $kicker = trim((string) ($item['content_type_name'] ?? ''));
        }
        $image = trim((string) ($item['cover_image_url'] ?? ''));
        if ($image === '') {
            $image = match ($entityType) {
                'product' => '/assets/images/home/equipment-forming-module.jpg',
                'solution' => '/assets/images/home/equipment-integrated-line.jpg',
                'news' => '/assets/images/home/news-real-expo-hall.jpg',
                'case' => '/assets/images/home/news-real-handshake-team.jpg',
                default => '/assets/images/common/logo-110.png',
            };
        }
        $image = $this->assetUrl($image);
        $summaryHtml = $summary !== '' ? '<p>' . $this->escape($this->excerpt($summary, 110)) . '</p>' : '';
        $kickerHtml = $kicker !== '' ? '<small>' . $this->escape($kicker) . '</small>' : '';
        $categoryAttr = $categorySlug !== '' ? ' data-category-card data-category-slug="' . $this->escape(strtolower($categorySlug)) . '"' : ' data-category-card';
        $entityAttr = ' data-public-card-type="' . $this->escape(strtolower(trim($entityType)) !== '' ? strtolower(trim($entityType)) : 'generic') . '"';
        $metaHtml = $this->renderListingCardMeta($item, $entityType);

        return '<a class="public-content-card public-card" href="' . $this->escape($href) . '"' . $entityAttr . $categoryAttr . '><figure class="public-content-card-media public-card-media"><img src="' . $this->escape($image) . '" alt="' . $this->escape($title) . '" loading="lazy" decoding="async"></figure><div class="public-content-card-copy public-card-copy">' . $kickerHtml . '<h3>' . $this->escape($title) . '</h3>' . $summaryHtml . '<div class="public-card-footer">' . $metaHtml . '<span class="public-card-cta">' . $this->escape($this->listingCardCtaLabel($entityType, $languageCode)) . '</span></div></div></a>';
    }

    private function renderListingCardMeta(array $item, string $entityType): string
    {
        $meta = [];
        $publishedAt = trim((string) ($item['published_at'] ?? $item['publish_time'] ?? ''));
        if ($publishedAt !== '' && in_array($entityType, ['news', 'case'], true)) {
            $meta[] = substr($publishedAt, 0, 10);
        }

        $countryCode = strtoupper(trim((string) ($item['country_code'] ?? '')));
        if ($countryCode !== '' && $entityType === 'case') {
            $meta[] = $countryCode;
        }

        $capacityText = trim((string) ($item['capacity_text'] ?? ''));
        if ($capacityText !== '' && $entityType === 'solution') {
            $meta[] = $this->excerpt($capacityText, 32);
        }

        $sku = trim((string) ($item['sku'] ?? ''));
        if ($sku !== '' && $entityType === 'product') {
            $meta[] = $sku;
        }

        if ($meta === []) {
            return '<div class="public-card-meta public-card-meta-empty" aria-hidden="true"></div>';
        }

        $html = '<div class="public-card-meta">';
        foreach (array_slice($meta, 0, 2) as $value) {
            $html .= '<span>' . $this->escape($value) . '</span>';
        }
        $html .= '</div>';

        return $html;
    }

    private function listingCardCtaLabel(string $entityType, string $languageCode): string
    {
        if ($languageCode === 'zh') {
            return match ($entityType) {
                'product' => $this->unicodeText('\u4ea7\u54c1\u8be6\u60c5'),
                'solution' => $this->unicodeText('\u67e5\u770b\u65b9\u6848'),
                'news' => $this->unicodeText('\u9605\u8bfb\u5168\u6587'),
                'case' => $this->unicodeText('\u67e5\u770b\u6848\u4f8b'),
                default => $this->unicodeText('\u67e5\u770b\u8be6\u60c5'),
            };
        }

        return match ($entityType) {
            'product' => 'Product Details',
            'solution' => 'View Line',
            'news' => 'Read Article',
            'case' => 'View Case',
            default => 'View Details',
        };
    }

    /**
     * @param array<string, mixed> $site
     */
    private function serviceCapabilityLines(string $languageCode, array $site = []): array
    {
        $fromConfig = [
            trim((string) ($site['service_support_line_1'] ?? '')),
            trim((string) ($site['service_support_line_2'] ?? '')),
            trim((string) ($site['service_support_line_3'] ?? '')),
            trim((string) ($site['service_support_line_4'] ?? '')),
        ];
        if (implode('', $fromConfig) !== '') {
            return $fromConfig;
        }

        // Fallback: phrase 语言包
        return [
            $this->phrase('service_capability_line_1', $languageCode, 'OEM / ODM Support'),
            $this->phrase('service_capability_line_2', $languageCode, 'Trial Verification Support'),
            $this->phrase('service_capability_line_3', $languageCode, 'Integrated Line Support'),
            $this->phrase('service_capability_line_4', $languageCode, 'Export Project Support'),
        ];
    }

    private function companyName(string $languageCode): string
    {
        $site = $this->cachedSite($languageCode);
        return trim((string) ($site['company_name'] ?? '')) !== ''
            ? trim((string) ($site['company_name'] ?? ''))
            : trim((string) ($site['site_name'] ?? 'HANZUN'));
    }

    /**
     * Hero 文案解析：site config → phrase → fallback。
     */
    private function resolveHeroCopy(string $value, string $phraseKey, string $languageCode, string $fallback): string
    {
        $value = trim($value);
        $languageCode = strtolower(trim($languageCode));

        // 默认语言(zh)直接用 site 配置值，保证后台中文修改即时生效。
        if ($languageCode === 'zh') {
            if ($value !== '') {
                return $value;
            }
            $resolved = $this->phrase($phraseKey, $languageCode, '');
            if ($resolved !== '') {
                return $resolved;
            }
            return $fallback;
        }

        // 非默认语言: 优先走 phrase 翻译链路(site_phrase_translations 表，
        // 后台 phrase 管理可单独编辑各语言翻译)，保证多语言不回退成中文配置值。
        $resolved = $this->phrase($phraseKey, $languageCode, '');
        if ($resolved !== '') {
            return $resolved;
        }

        // phrase 缺翻译时再用 site 配置值兜底，最后用英文 fallback。
        if ($value !== '') {
            return $value;
        }

        return $fallback;
    }

    private function companySubtitle(string $languageCode): string
    {
        $site = $this->cachedSite($languageCode);
        $configuredSubtitle = trim((string) ($site['company_subtitle'] ?? ''));
        if ($configuredSubtitle !== '') {
            return $this->shouldDisplayBrandSubtitle($configuredSubtitle, $languageCode) ? $configuredSubtitle : '';
        }

        return '';
    }

    private function homepageTitle(string $languageCode): string
    {
        $companyName = $this->companyName($languageCode);
        $companySubtitle = $this->companySubtitle($languageCode);

        if ($companySubtitle === '') {
            return $companyName;
        }

        return $companyName . ' | ' . $companySubtitle;
    }

    private function phrase(string $key, string $languageCode, string $fallback = ''): string
    {
        $cacheKey = strtolower(trim($languageCode)) . '|' . trim($key) . '|' . md5($fallback);
        if (array_key_exists($cacheKey, $this->resolvedPhraseCache)) {
            return (string) $this->resolvedPhraseCache[$cacheKey];
        }

        if (!$this->disablePhraseUsageTracking && !env_flag('DISABLE_SITE_PHRASE_USAGE_TRACKING')) {
            $this->sitePhraseWorkspaceRepository->registerPhraseUsage($key, $fallback, $languageCode);
        }

        $resolved = trim($this->sitePhraseRepository->getText($key, $languageCode, ''));
        if ($resolved !== '') {
            $this->resolvedPhraseCache[$cacheKey] = $resolved;
            return $resolved;
        }

        $override = $this->publicPhraseOverride($key, $languageCode);
        if ($override !== null && $override !== '') {
            $this->resolvedPhraseCache[$cacheKey] = $override;
            return $override;
        }

        $this->resolvedPhraseCache[$cacheKey] = $fallback;

        return $fallback;
    }

    private function publicPhraseOverride(string $key, string $languageCode): ?string
    {
        $languageCode = strtolower(trim($languageCode));
        $zh = fn (string $unicode): string => $this->unicodeText($unicode);
        $overrides = [
            'zh' => [
                'nav_home' => $zh('\u9996\u9875'),
                'nav_about' => $zh('\u4ecb\u7ecd'),
                'nav_products' => $zh('\u4ea7\u54c1'),
                'nav_solutions' => $zh('\u65b9\u6848'),
                'nav_news' => $zh('\u65b0\u95fb'),
                'nav_contact' => $zh('\u8054\u7cfb'),
                'product_catalog_browse' => $zh('\u6309\u4ea7\u54c1\u5206\u7c7b\u67e5\u770b'),
                'page_products' => $zh('\u4ea7\u54c1'),
                'page_solutions' => $zh('\u65b9\u6848'),
                'page_news' => $zh('\u65b0\u95fb'),
                'page_cases' => $zh('\u6848\u4f8b'),
                'page_contact' => $zh('\u8054\u7cfb'),
                'page_articles' => $zh('\u65b0\u95fb\u4e0e\u6848\u4f8b'),
                'page_pages' => $zh('\u5355\u9875'),
                'footer_contact' => $zh('\u8054\u7cfb\u65b9\u5f0f'),
                'footer_popular_products' => $zh('\u70ed\u95e8\u4ea7\u54c1'),
                'footer_popular_solutions' => $zh('\u70ed\u95e8\u65b9\u6848'),
                'floating_contact' => $zh('\u8054\u7cfb\u65b9\u5f0f'),
                'floating_contact_hint' => $zh('\u70b9\u51fb\u6253\u5f00\u8054\u7cfb\u65b9\u5f0f'),
                'floating_menu_title' => $zh('\u667a\u80fd\u54a8\u8be2'),
                'floating_menu_intro' => $zh('\u4f18\u5148\u901a\u8fc7 AI \u54a8\u8be2\u4ea7\u54c1\u3001\u65b9\u6848\u4e0e\u62a5\u4ef7\uff0c\u5176\u4ed6\u8054\u7cfb\u65b9\u5f0f\u4f5c\u4e3a\u8865\u5145\u3002'),
                'floating_menu_other' => $zh('\u5176\u4ed6\u8054\u7cfb\u65b9\u5f0f'),
                'floating_ai_title' => $zh('AI\u54a8\u8be2'),
                'floating_ai_hint' => $zh('\u4ea7\u54c1\u9009\u578b\u3001\u65b9\u6848\u6c9f\u901a\u3001\u62a5\u4ef7\u54a8\u8be2'),
                'floating_contact_trigger_title' => $zh('AI\u54a8\u8be2'),
                'floating_contact_trigger_hint' => $zh('\u70b9\u51fb\u5c55\u5f00\u8054\u7cfb\u65b9\u5f0f'),
                'floating_email' => $zh('\u5546\u52a1\u90ae\u7bb1'),
                'floating_email_hint' => $zh('\u70b9\u51fb\u67e5\u770b\u90ae\u7bb1\u5730\u5740'),
                'floating_phone' => $zh('\u5de5\u5382\u603b\u673a'),
                'floating_phone_hint' => $zh('\u70b9\u51fb\u67e5\u770b\u8054\u7cfb\u7535\u8bdd'),
                'floating_whatsapp' => 'WhatsApp',
                'floating_whatsapp_hint' => $zh('\u6253\u5f00 WhatsApp \u5bf9\u8bdd'),
                'floating_address' => $zh('\u5de5\u5382\u5730\u5740'),
                'floating_address_hint' => $zh('\u6253\u5f00\u8054\u7cfb\u9875\u9762'),
                'html_sitemap' => $zh('\u7ad9\u70b9\u5730\u56fe'),
                'detail_status' => $zh('\u72b6\u6001'),
                'detail_date' => $zh('\u53d1\u5e03\u65f6\u95f4'),
                'detail_overview' => $zh('\u5185\u5bb9\u4ecb\u7ecd'),
                'qualifications_title' => $zh('\u8d44\u8d28\u8bc1\u4e66'),
                'cooperation_flow_title' => $zh('\u5408\u4f5c\u6d41\u7a0b'),
                'support_title' => $zh('\u5728\u7ebf\u5ba2\u670d'),
                'support_online_chat' => $zh('\u5728\u7ebf\u804a\u5929'),
                'support_intro' => $zh('\u5728\u7ebf\u5ba2\u670d\u4f1a\u8bb0\u5f55\u60a8\u7684\u9700\u6c42\uff0c\u5e76\u81ea\u52a8\u6574\u7406\u4e3a\u8be2\u76d8\u7ebf\u7d22\u3002'),
                'support_assistant' => $zh('\u5ba2\u670d\u52a9\u624b'),
                'support_greeting' => $zh('\u60a8\u597d\uff0c\u60a8\u53ef\u4ee5\u54a8\u8be2\u8bbe\u5907\u3001\u6574\u7ebf\u65b9\u6848\u3001\u4ea4\u671f\u548c\u62a5\u4ef7\uff0c\u4e5f\u53ef\u4ee5\u7559\u4e0b\u8054\u7cfb\u65b9\u5f0f\u4e0e\u9700\u6c42\u3002'),
                'support_placeholder' => $zh('\u8bf7\u8f93\u5165\u60a8\u7684\u95ee\u9898'),
                'service_capability_heading' => $zh('\u670d\u52a1\u80fd\u529b'),
                'notice_slot_title' => $zh('\u91cd\u8981\u901a\u77e5\u4f4d'),
                'notice_slot_copy' => $zh('\u53ef\u7528\u4e8e\u65b0\u54c1\u53d1\u5e03\u3001\u5c55\u4f1a\u901a\u77e5\u6216\u91cd\u8981\u516c\u544a\u5c55\u793a\u3002'),
                'contact_email' => $zh('\u90ae\u7bb1'),
                'contact_email_hint' => $zh('\u70b9\u51fb\u9009\u62e9\u90ae\u7bb1'),
                'contact_phone' => $zh('\u7535\u8bdd'),
                'contact_phone_hint' => $zh('\u70b9\u51fb\u9009\u62e9\u7535\u8bdd'),
                'contact_address' => $zh('\u5730\u5740'),
                'contact_address_hint' => $zh('\u5de5\u5382\u8054\u7cfb\u4fe1\u606f'),
                'form_contact_name' => $zh('\u8054\u7cfb\u4eba'),
                'form_contact_name_placeholder' => $zh('\u8bf7\u8f93\u5165\u8054\u7cfb\u4eba'),
                'form_phone_with_code' => $zh('\u56fd\u5bb6\u533a\u53f7 + \u7535\u8bdd'),
                'form_email' => $zh('\u90ae\u7bb1'),
                'form_email_placeholder' => $zh('\u8bf7\u8f93\u5165\u90ae\u7bb1'),
                'form_message' => $zh('\u7559\u8a00\u5185\u5bb9'),
                'form_message_placeholder' => $zh('\u8bf7\u586b\u5199\u4ea7\u54c1\u65b9\u5411\u3001\u4ea7\u80fd\u4e0e\u9700\u6c42\u8bf4\u660e'),
                'button_submit_inquiry' => $zh('\u63d0\u4ea4\u8054\u7cfb\u4fe1\u606f'),
                'button_back' => $zh('\u8fd4\u56de\u5217\u8868'),
                'contact_page_prompt_title' => $zh('\u83b7\u53d6\u65b9\u6848'),
                'contact_page_prompt_copy' => $zh('\u6b22\u8fce\u7559\u4e0b\u60a8\u7684\u9879\u76ee\u9700\u6c42\uff0c\u6211\u4eec\u4f1a\u5c3d\u5feb\u4e0e\u60a8\u8054\u7cfb\u3002'),
                'wechat_hint' => $zh('\u5fae\u4fe1\u4e0e\u624b\u673a\u53f7\u540c\u6b65'),
                'wechat_title' => $zh('\u5fae\u4fe1'),
                'close_wechat_panel' => $zh('\u5173\u95ed\u5fae\u4fe1\u9762\u677f'),
                'wechat_dialog_label' => $zh('\u5fae\u4fe1\u4e8c\u7ef4\u7801'),
                'wechat_qr_alt' => $zh('\u5fae\u4fe1\u4e8c\u7ef4\u7801'),
                'button_send' => $zh('\u53d1\u9001'),
                'button_get_quote' => $zh('\u83b7\u53d6\u65b9\u6848'),
                'button_get_solution_detail' => $zh('\u70b9\u51fb\u83b7\u53d6\u8be6\u7ec6\u65b9\u6848'),
                'open_navigation' => $zh('\u6253\u5f00\u5bfc\u822a'),
                'close_support_panel' => $zh('\u5173\u95ed\u5728\u7ebf\u5ba2\u670d'),
                'back_to_top' => $zh('\u9876\u90e8'),
                'wechat_panel_hint' => $zh('\u626b\u7801\u6216\u590d\u5236\u5fae\u4fe1\u53f7'),
                'wechat_id' => $zh('\u5fae\u4fe1\u53f7'),
                'wechat_copy' => $zh('\u590d\u5236\u5fae\u4fe1\u53f7'),
                'filter_all' => $zh('\u5168\u90e8'),
                'hero_image_alt' => $zh('\u4f01\u4e1a\u5236\u9020\u5c55\u793a'),
                'copyright_suffix' => $zh('\u7248\u6743\u6240\u6709\u3002'),
            ],
            'en' => [
                'nav_home' => 'Home',
                'nav_about' => 'About',
                'nav_products' => 'Products',
                'nav_solutions' => 'Solutions',
                'nav_news' => 'News',
                'nav_contact' => 'Contact',
                'product_catalog_browse' => 'Browse by product categories',
                'page_products' => 'Products',
                'page_solutions' => 'Solutions',
                'page_news' => 'News',
                'page_cases' => 'Cases',
                'page_contact' => 'Contact',
                'page_articles' => 'News & Cases',
                'page_pages' => 'Pages',
                'footer_contact' => 'Contact',
                'footer_popular_products' => 'Popular Products',
                'footer_popular_solutions' => 'Popular Solutions',
                'floating_contact' => 'Contact',
                'floating_contact_hint' => 'Tap to open contact options',
                'floating_menu_title' => 'AI Consult',
                'floating_menu_intro' => 'Start with AI for products, solutions, and quotations. Other contact methods stay available as backup.',
                'floating_menu_other' => 'Other contact methods',
                'floating_ai_title' => 'AI Consult',
                'floating_ai_hint' => 'Machine matching, solution discussion, and quotations',
                'floating_contact_trigger_title' => 'AI Consult',
                'floating_contact_trigger_hint' => 'Tap to open contact options',
                'floating_email' => 'Business Email',
                'floating_email_hint' => 'Show email address',
                'floating_phone' => 'Factory Line',
                'floating_phone_hint' => 'Show phone number',
                'floating_whatsapp' => 'WhatsApp',
                'floating_whatsapp_hint' => 'Start a quick chat',
                'floating_address' => 'Factory Address',
                'floating_address_hint' => 'Open contact page',
                'html_sitemap' => 'Site Map',
                'detail_status' => 'Status',
                'detail_date' => 'Published',
                'detail_overview' => 'Overview',
                'qualifications_title' => 'Qualifications',
                'cooperation_flow_title' => 'Cooperation Flow',
                'support_title' => 'Online Support',
                'support_online_chat' => 'Online chat',
                'support_intro' => 'Online support records your request and automatically organizes it into an inquiry lead.',
                'support_assistant' => 'Support Assistant',
                'support_greeting' => 'Hello. You can ask about equipment, line solutions, lead times, and quotations. You can also leave your contact details and requirements for the support assistant to organize and follow up.',
                'support_placeholder' => 'Enter your question',
                'service_capability_heading' => 'Service Capability',
                'notice_slot_title' => 'Important Notice Slot',
                'notice_slot_copy' => 'Reserved for new releases, exhibition notices, or important announcements.',
                'contact_email' => 'Email',
                'contact_email_hint' => 'Tap to choose an email',
                'contact_phone' => 'Phone',
                'contact_phone_hint' => 'Tap to choose a phone number',
                'contact_address' => 'Address',
                'contact_address_hint' => 'Factory contact details',
                'form_contact_name' => 'Contact Name',
                'form_contact_name_placeholder' => 'Name',
                'form_phone_with_code' => 'Country Code + Phone',
                'form_email' => 'Email',
                'form_email_placeholder' => 'Email',
                'form_message' => 'Message',
                'form_message_placeholder' => 'Cake / Bread / Filling / Cutting / Food Processing',
                'button_submit_inquiry' => 'Submit Inquiry',
                'button_back' => 'Back to List',
                'contact_page_prompt_title' => 'Get Solution',
                'contact_page_prompt_copy' => 'Leave your project requirements and we will contact you shortly.',
                'wechat_hint' => 'Same phone number on WeChat',
                'wechat_title' => 'WeChat',
                'close_wechat_panel' => 'Close WeChat panel',
                'wechat_dialog_label' => 'WeChat QR code',
                'wechat_qr_alt' => 'WeChat QR code',
                'button_send' => 'Send',
                'button_get_quote' => 'Get Solution',
                'button_get_solution_detail' => 'Click for Detailed Solution',
                'open_navigation' => 'Open navigation',
                'close_support_panel' => 'Close support panel',
                'back_to_top' => 'TOP',
                'wechat_panel_hint' => 'Scan the code or copy the WeChat ID',
                'wechat_id' => 'WeChat ID',
                'wechat_copy' => 'Copy WeChat ID',
                'filter_all' => 'All',
                'hero_image_alt' => 'Enterprise manufacturing showcase',
                'copyright_suffix' => 'All rights reserved.',
            ],
        ];


        return $overrides[$languageCode][$key] ?? null;
    }

    private function enabledLanguages(): array
    {
        try {
            return array_values(array_filter(
                $this->languageRepository->list(),
                static fn (array $language): bool => (int) ($language['is_enabled'] ?? 0) === 1
            ));
        } catch (\Throwable) {
            return $this->defaultEnabledLanguages();
        }
    }

    private function defaultLanguage(): string
    {
        foreach ($this->enabledLanguages() as $language) {
            if (strtolower(trim((string) ($language['code'] ?? ''))) === 'zh') {
                return 'zh';
            }
        }

        foreach ($this->enabledLanguages() as $language) {
            if ((int) ($language['is_default'] ?? 0) === 1) {
                return strtolower(trim((string) ($language['code'] ?? 'zh')));
            }
        }

        return 'zh';
    }

    private function resolveLanguageCodes(array|string $codes): array
    {
        $rawCodes = is_array($codes) ? $codes : explode(',', (string) $codes);
        $normalized = [];
        foreach ($rawCodes as $code) {
            $value = strtolower(trim((string) $code));
            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        $normalized = array_values(array_unique($normalized));
        if ($normalized !== []) {
            return $normalized;
        }

        $fallback = array_map(
            static fn (array $language): string => strtolower(trim((string) ($language['code'] ?? 'zh'))),
            $this->enabledLanguages()
        );

        return $fallback !== [] ? array_values(array_unique($fallback)) : ['zh'];
    }

    private function defaultEnabledLanguages(): array
    {
        return [
            ['code' => 'zh', 'name' => '中文', 'is_default' => 1, 'is_enabled' => 1, 'id' => 1],
            ['code' => 'en', 'name' => 'English', 'is_default' => 0, 'is_enabled' => 1, 'id' => 2],
        ];
    }

    private function outputPathFromRoute(string $route): string
    {
        return $this->outputDir . str_replace('/', DIRECTORY_SEPARATOR, $route);
    }

    private function writeOutput(string $outputFile, string $content): void
    {
        $dir = dirname($outputFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @chmod($dir, 0777);

        $written = @file_put_contents($outputFile, $content);
        if ($written !== false) {
            @chmod($outputFile, 0666);
            return;
        }

        if ($this->isLockedRootEntry($outputFile)) {
            return;
        }

        $error = error_get_last();
        throw new \RuntimeException(
            '无法删除输出文件: '
            . $outputFile
            . ($error ? ' (' . (string) ($error['message'] ?? 'unknown error') . ')' : '')
        );
    }

    /**
     * @param array<int, string> $languageCodes
     */
    private function cleanupFullBuildOutputs(array $languageCodes): void
    {
        $codes = array_values(array_unique(array_filter(
            array_map(static fn (string $code): string => strtolower(trim($code)), $languageCodes),
            static fn (string $code): bool => $code !== ''
        )));
        $knownLanguageCodes = $this->allLanguageCodes();
        $codes = $codes === [] ? $knownLanguageCodes : array_values(array_unique(array_merge($knownLanguageCodes, $codes)));

        foreach ($codes as $languageCode) {
            $dir = $this->outputDir . DIRECTORY_SEPARATOR . $languageCode;
            if (is_dir($dir)) {
                $this->removeDirectory($dir);
            }
        }

        foreach ([
            'index.html',
            'robots.txt',
            'sitemap.xml',
            'about.html',
            'contact.html',
            'products.html',
            'solutions.html',
            'news.html',
            'cases.html',
        ] as $filename) {
            $path = $this->outputDir . DIRECTORY_SEPARATOR . $filename;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        foreach (['products', 'solutions', 'news', 'cases', 'pages'] as $dirname) {
            $path = $this->outputDir . DIRECTORY_SEPARATOR . $dirname;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }

    private function resolveOutputDir(string $configured): string
    {
        $configured = trim($configured);
        if ($configured === '') {
            return $this->projectRoot;
        }

        if (
            preg_match('/^[A-Za-z]:[\\\\\\/]/', $configured) === 1
            || str_starts_with($configured, '/')
            || str_starts_with($configured, '\\\\')
        ) {
            return rtrim($configured, '\\/');
        }

        return rtrim($this->projectRoot . DIRECTORY_SEPARATOR . ltrim($configured, '\\/'), '\\/');
    }

    private function isLockedRootEntry(string $outputFile): bool
    {
        $normalizedTarget = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $outputFile);
        $normalizedRootIndex = str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $this->outputDir . DIRECTORY_SEPARATOR . 'index.html'
        );

        if ($normalizedTarget !== $normalizedRootIndex) {
            return false;
        }

        return is_file($normalizedTarget);
    }

    /**
     * @return array<int, string>
     */
    private function allLanguageCodes(): array
    {
        $codes = [];
        foreach ($this->languageRepository->list() as $language) {
            $code = strtolower(trim((string) ($language['code'] ?? '')));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        if ($codes === []) {
            return $this->resolveLanguageCodes([]);
        }

        return array_values(array_unique($codes));
    }

    private function uniqueTargets(array $targets): array
    {
        $unique = [];
        foreach ($targets as $target) {
            $key = (string) ($target['page_type'] ?? '') . '|' . (string) ($target['route'] ?? '');
            $target['output_file'] = $this->outputPathFromRoute((string) ($target['route'] ?? '/'));
            $unique[$key] = $target;
        }

        return array_values($unique);
    }

    private function assetUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/assets/images/common/logo-110.png';
        }
        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        return str_starts_with($path, '/') ? $path : '/' . ltrim($path, '/');
    }

    private function shouldDisplayBrandSubtitle(string $text, string $languageCode): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }

        $hasCjk = preg_match('/[\x{4e00}-\x{9fff}]/u', $text) === 1;
        if ($languageCode !== 'zh' && $hasCjk) {
            return false;
        }

        return true;
    }

    private function alternateRouteForLanguage(string $route, string $languageCode): string
    {
        if ($route === '/index.html' || $route === '/sitemap.xml' || $route === '/robots.txt') {
            return '/' . $languageCode . '/index.html';
        }

        $parts = explode('/', trim($route, '/'));
        if (isset($parts[0]) && strlen((string) $parts[0]) <= 5) {
            $parts[0] = $languageCode;
        }

        return '/' . implode('/', array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    private function normalizeManagedUrl(string $url, string $languageCode): string
    {
        $url = trim($url);
        if ($url === '' || $url === '#') {
            return '#';
        }
        if (preg_match('/^https?:\/\//i', $url) === 1) {
            return $url;
        }
        if (str_starts_with($url, '/' . trim($languageCode, '/') . '/')) {
            return $url;
        }

        $normalized = ltrim($url, '/');
        return match (true) {
            $normalized === 'about.html#about' => $this->aboutAnchorRoute($languageCode),
            $normalized === 'about.html#contact' => $this->contactAnchorRoute($languageCode),
            $normalized === '' || $normalized === 'index.html' => $this->localizedRoute($languageCode, 'index'),
            $normalized === 'about.html' || str_contains($normalized, 'about') => $this->localizedRoute($languageCode, 'about'),
            $normalized === 'contact.html' || str_contains($normalized, 'contact') => $this->localizedRoute($languageCode, 'contact'),
            $normalized === 'products.html' || str_starts_with($normalized, 'product') => $this->localizedRoute($languageCode, 'products'),
            $normalized === 'solutions.html' || str_starts_with($normalized, 'solution') => $this->localizedRoute($languageCode, 'solutions'),
            $normalized === 'news.html' || str_starts_with($normalized, 'news') => $this->localizedRoute($languageCode, 'news'),
            $normalized === 'cases.html' || str_starts_with($normalized, 'case') => $this->localizedRoute($languageCode, 'cases'),
            default => '/' . trim($languageCode, '/') . '/' . $normalized,
        };
    }

    private function homepageSectionItems(array $homepage, array $keys): array
    {
        foreach ((array) ($homepage['sections'] ?? []) as $section) {
            $sectionKey = (string) ($section['section_key'] ?? '');
            $sectionType = (string) ($section['section_type'] ?? '');
            if (in_array($sectionKey, $keys, true) || in_array($sectionType, $keys, true)) {
                return is_array($section['items'] ?? null) ? $section['items'] : [];
            }
        }

        return [];
    }

    private function extractAboutIntro(array $about): string
    {
        foreach ((array) ($about['blocks'] ?? []) as $block) {
            $content = trim(strip_tags((string) ($block['content'] ?? '')));
            if ($content !== '') {
                return $content;
            }
        }

        return trim((string) ($about['intro'] ?? ''));
    }

    private function extractAboutImage(array $about): string
    {
        foreach ((array) ($about['blocks'] ?? []) as $block) {
            $extra = is_array($block['extra_config'] ?? null) ? $block['extra_config'] : [];
            $image = trim((string) ($extra['image_url'] ?? $extra['cover_image_url'] ?? ''));
            if ($image !== '') {
                return $image;
            }
        }

        return '';
    }

    private function extractAboutItems(array $blocks, array $types): array
    {
        foreach ($blocks as $block) {
            if (in_array((string) ($block['block_type'] ?? ''), $types, true)) {
                return is_array($block['items'] ?? null) ? $block['items'] : [];
            }
        }

        return [];
    }

    private function homepageFlowItems(string $languageCode): array
    {
        $labels = [
            $this->phrase('cooperation_flow_step_1', $languageCode, 'Requirement Review'),
            $this->phrase('cooperation_flow_step_2', $languageCode, 'Production'),
            $this->phrase('cooperation_flow_step_3', $languageCode, 'Commissioning & Delivery'),
            $this->phrase('cooperation_flow_step_4', $languageCode, 'After-Sales Support'),
        ];

        $icons = [
            '<svg viewBox="0 0 24 24" fill="none"><path d="M4 6.5C4 5.672 4.672 5 5.5 5h8A1.5 1.5 0 0 1 15 6.5v11A1.5 1.5 0 0 1 13.5 19h-8A1.5 1.5 0 0 1 4 17.5v-11Z" stroke="currentColor" stroke-width="1.8"/><path d="M7 9h5M7 12h5M7 15h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M17.5 9.5 20 12l-2.5 2.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.5 12H20" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            '<svg viewBox="0 0 24 24" fill="none"><path d="M4 19V9l5-3 5 3v10H4ZM14 19V12l6-3v10h-6Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M7 12h4M7 15h4M16 13h2M16 16h2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            '<svg viewBox="0 0 24 24" fill="none"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h6A2.5 2.5 0 0 1 15 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-6A2.5 2.5 0 0 1 4 16.5v-9ZM15 10h2.8L20 12.5V16a2 2 0 0 1-2 2h-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="m8.3 12 1.8 1.8 3.6-3.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            '<svg viewBox="0 0 24 24" fill="none"><path d="M6.5 11a5.5 5.5 0 1 1 11 0v4.5a2.5 2.5 0 0 1-2.5 2.5H13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M6 11H4.8A1.8 1.8 0 0 0 3 12.8v1.4A1.8 1.8 0 0 0 4.8 16H6v-5ZM18 11h1.2a1.8 1.8 0 0 1 1.8 1.8v1.4a1.8 1.8 0 0 1-1.8 1.8H18v-5ZM10 17.5h3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ];

        $items = [];
        foreach ($labels as $index => $label) {
            $items[] = [
                'label' => $label,
                'icon' => $icons[$index] ?? '',
            ];
        }

        return $items;
    }

    private function renderHomepageContactCards(array $items, string $languageCode): array
    {
        $cards = [];
        foreach ($items as $item) {
            $label = trim((string) ($item['label'] ?? $item['field_name'] ?? ''));
            $value = trim((string) ($item['field_value'] ?? $item['value'] ?? ''));
            $fieldKey = strtolower(trim((string) ($item['field_key'] ?? '')));
            if ($value === '') {
                continue;
            }

            $icon = match ($fieldKey) {
                'email' => '<svg viewBox="0 0 24 24"><path d="M3 6.75h18v10.5H3z" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m4.5 8 7.5 6 7.5-6" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/></svg>',
                'phone' => '<svg viewBox="0 0 24 24"><path d="M6.6 10.8c1.6 3.2 3.8 5.4 7 7l2.3-2.3a1.4 1.4 0 0 1 1.4-.35c1.1.36 2.3.55 3.5.55A1.2 1.2 0 0 1 22 16.95V21a1.2 1.2 0 0 1-1.2 1.2C11.2 22.2 1.8 12.8 1.8 3.2A1.2 1.2 0 0 1 3 2h4.05a1.2 1.2 0 0 1 1.2 1.2c0 1.2.19 2.4.55 3.5a1.4 1.4 0 0 1-.35 1.4z" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"/></svg>',
                default => '<svg viewBox="0 0 24 24"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="10" r="2.3" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>',
            };

            $href = match ($fieldKey) {
                'email' => 'mailto:' . $value,
                'phone' => 'tel:' . preg_replace('/\s+/', '', $value),
                'whatsapp' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $value),
                default => 'https://maps.google.com/?q=' . rawurlencode($value),
            };

            $tag = in_array($fieldKey, ['whatsapp', 'line'], true) ? 'a' : (str_contains(strtolower($label), 'address') ? 'div' : 'a');
            $target = in_array($fieldKey, ['whatsapp', 'line'], true) ? ' target="_blank" rel="noopener"' : '';
            $open = $tag === 'a' ? '<a class="contact-card' . ($fieldKey === 'address' ? ' contact-card-wide' : '') . '" href="' . $this->escape($href) . '"' . $target . '>' : '<div class="contact-card contact-card-wide">';
            $close = $tag === 'a' ? '</a>' : '</div>';

            $cards[] = $open
                . '<div class="contact-card-head"><span class="contact-card-icon" aria-hidden="true">' . $icon . '</span><small>' . $this->escape($label !== '' ? $label : $this->phrase('footer_contact', $languageCode, 'Contact')) . '</small></div>'
                . '<strong><span>' . $this->escape($value) . '</span></strong>'
                . $close;
        }

        return $cards;
    }

    private function contactLinks(string $languageCode): string
    {
        return $this->renderFooterPrimaryContactHtml($languageCode);
    }

    private function renderFooterPrimaryContactHtml(string $languageCode): string
    {
        $contactsByField = [];
        foreach ($this->collectScopedContacts($languageCode, ['footer']) as $contact) {
            $fieldKey = strtolower(trim((string) ($contact['field_key'] ?? '')));
            if ($fieldKey === '' || $this->isFooterSocialFieldKey($fieldKey)) {
                continue;
            }

            if (!isset($contactsByField[$fieldKey])) {
                $contactsByField[$fieldKey] = $contact;
            }
        }

        $rows = [];
        if (isset($contactsByField['email'])) {
            $rows[] = '<div class="footer-brand-row footer-brand-row-email">'
                . $this->renderFooterBrandContactItem((array) $contactsByField['email'])
                . '</div>';
        }

        $phoneWhatsappItems = [];
        foreach (['phone', 'whatsapp'] as $fieldKey) {
            if (!isset($contactsByField[$fieldKey])) {
                continue;
            }

            $phoneWhatsappItems[] = $this->renderFooterBrandContactItem((array) $contactsByField[$fieldKey]);
        }

        if ($phoneWhatsappItems !== []) {
            $rows[] = '<div class="footer-brand-row footer-brand-row-phone-whatsapp">'
                . implode('', $phoneWhatsappItems)
                . '</div>';
        }

        if (isset($contactsByField['address'])) {
            $rows[] = '<div class="footer-brand-row footer-brand-row-address">'
                . $this->renderFooterBrandContactItem((array) $contactsByField['address'])
                . '</div>';
        }

        return implode('', $rows);
    }

    private function renderFooterSocialLinksHtml(string $languageCode): string
    {
        $links = '';
        foreach ($this->collectScopedContacts($languageCode, ['footer']) as $contact) {
            $fieldKey = strtolower(trim((string) ($contact['field_key'] ?? '')));
            if (!$this->isFooterSocialFieldKey($fieldKey)) {
                continue;
            }

            $href = trim((string) ($contact['href'] ?? ''));
            if ($href === '' || $href === '#') {
                continue;
            }

            $kindLabel = trim((string) ($contact['kind_label'] ?? 'Contact'));
            $displayLabel = trim((string) ($contact['display_label'] ?? ''));
            $title = $displayLabel !== '' ? $displayLabel : $kindLabel;
            $links .= '<a class="footer-brand-social ' . $this->escape($fieldKey) . '" href="' . $this->escape($href) . '" target="_blank" rel="noreferrer" aria-label="' . $this->escape($title) . '" title="' . $this->escape($title) . '">'
                . '<span aria-hidden="true"><img src="' . $this->escape($this->footerSocialIconAsset($fieldKey)) . '" alt="" loading="lazy" decoding="async"></span>'
                . '</a>';
        }

        return $links;
    }

    private function isFooterSocialFieldKey(string $fieldKey): bool
    {
        return in_array($fieldKey, ['linkedin', 'youtube', 'line'], true);
    }

    private function footerSocialIconAsset(string $fieldKey): string
    {
        return match ($fieldKey) {
            'linkedin' => $this->assetUrl('/assets/images/common/icon-linkedin-color.svg'),
            'youtube' => $this->assetUrl('/assets/images/common/icon-youtube-color.svg'),
            'line' => $this->assetUrl('/assets/images/common/icon-line-color.svg'),
            default => $this->assetUrl('/assets/images/common/logo-110.png'),
        };
    }

    /**
     * @param array<string, mixed> $contact
     */
    private function renderFooterBrandContactItem(array $contact): string
    {
        $fieldKey = strtolower(trim((string) ($contact['field_key'] ?? '')));
        $href = (string) ($contact['href'] ?? '#');
        $label = (string) ($contact['label'] ?? '');
        $kindLabel = (string) ($contact['kind_label'] ?? 'Contact');
        $isAddress = $fieldKey === 'address';
        $tag = $isAddress ? 'div' : 'a';
        $target = !empty($contact['target']) ? ' target="_blank" rel="noreferrer"' : '';
        $contactClass = 'footer-brand-contact footer-brand-contact-' . $this->escape($fieldKey !== '' ? $fieldKey : 'generic')
            . ($isAddress ? ' footer-brand-contact-address' : '');
        $open = $tag === 'a'
            ? '<a class="' . $contactClass . '" href="' . $this->escape($href) . '"' . $target . '>'
            : '<div class="' . $contactClass . '">';
        $close = $tag === 'a' ? '</a>' : '</div>';

        return $open
            . '<small class="footer-brand-contact-label">' . $this->escape($kindLabel) . '</small>'
            . '<strong class="footer-brand-contact-value"><span>' . $this->escape($label) . '</span></strong>'
            . $close;
    }

    private function collectPrimaryContacts(string $languageCode): array
    {
        $payload = $this->cachedContact($languageCode);
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $contacts = [];

        foreach ($items as $item) {
            $fieldKey = strtolower(trim((string) ($item['field_key'] ?? '')));
            $fieldName = strtolower(trim((string) ($item['field_name'] ?? '')));
            $field = $fieldKey !== '' ? $fieldKey : $fieldName;
            $label = trim((string) ($item['label'] ?? ''));
            $value = trim((string) ($item['field_value'] ?? $item['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            if ($field === 'email' || str_contains($field, 'mail') || str_contains($label, '邮箱') || str_contains(strtolower($label), 'email')) {
                $contacts[] = ['kind_label' => $label ?: $this->phrase('contact_email', $languageCode, 'Email'), 'label' => $value, 'href' => 'mailto:' . $value];
            } elseif ($field === 'phone' || str_contains($field, 'phone') || str_contains($label, '电话') || str_contains($label, '手机') || str_contains(strtolower($label), 'phone')) {
                $contacts[] = ['kind_label' => $label ?: $this->phrase('contact_phone', $languageCode, 'Phone'), 'label' => $value, 'href' => 'tel:' . preg_replace('/\s+/', '', $value)];
            } elseif ($field === 'whatsapp' || str_contains($field, 'whatsapp') || str_contains(strtolower($label), 'whatsapp')) {
                $contacts[] = ['kind_label' => $label ?: 'WhatsApp', 'label' => $value, 'href' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $value), 'target' => true];
            } elseif ($field === 'address' || str_contains($field, 'address') || str_contains($label, '地址') || str_contains(strtolower($label), 'address')) {
                $contacts[] = ['kind_label' => $label ?: $this->phrase('contact_address', $languageCode, 'Address'), 'label' => $value, 'href' => 'https://maps.google.com/?q=' . rawurlencode($value), 'target' => true];
            }
        }

        return array_slice($contacts, 0, 6);
    }

    /**
     * @param array<int, string> $scopes
     * @param array<int, string> $allowedFieldKeys
     * @return array<int, array<string, mixed>>
     */
    private function collectScopedContacts(string $languageCode, array $scopes, array $allowedFieldKeys = []): array
    {
        $payload = $this->cachedContact($languageCode);
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $contacts = [];
        $allowedScopes = array_values(array_filter(array_map(
            static fn (string $scope): string => strtolower(trim($scope)),
            $scopes
        )));
        $allowedFieldKeys = array_values(array_filter(array_map(
            static fn (string $fieldKey): string => strtolower(trim($fieldKey)),
            $allowedFieldKeys
        )));

        foreach ($items as $item) {
            $scope = strtolower(trim((string) ($item['display_scope'] ?? '')));
            if ($allowedScopes !== [] && !in_array($scope, $allowedScopes, true)) {
                continue;
            }

            $normalized = $this->normalizeContactEntry($item, $languageCode);
            if ($normalized === null) {
                continue;
            }
            if ($allowedFieldKeys !== [] && !in_array((string) ($normalized['field_key'] ?? ''), $allowedFieldKeys, true)) {
                continue;
            }

            $contacts[] = $normalized;
        }

        return $contacts;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    private function normalizeContactEntry(array $item, string $languageCode): ?array
    {
        $fieldKey = strtolower(trim((string) ($item['field_key'] ?? '')));
        $fieldName = trim((string) ($item['field_name'] ?? ''));
        $label = trim((string) ($item['label'] ?? ''));
        $value = trim((string) ($item['field_value'] ?? $item['value'] ?? ''));
        if ($value === '') {
            return null;
        }

        if ($fieldKey === '') {
            $matcher = strtolower($fieldName . ' ' . $label);
            $fieldKey = match (true) {
                str_contains($matcher, 'email') => 'email',
                str_contains($matcher, 'whatsapp') => 'whatsapp',
                str_contains($matcher, 'linkedin') => 'linkedin',
                str_contains($matcher, 'youtube') => 'youtube',
                str_contains($matcher, 'line') => 'line',
                str_contains($matcher, 'wechat') => 'wechat',
                str_contains($matcher, 'address') => 'address',
                str_contains($matcher, 'phone') => 'phone',
                default => '',
            };
        }

        $kindLabel = $fieldName !== '' ? $fieldName : match ($fieldKey) {
            'email' => $this->phrase('contact_email', $languageCode, 'Email'),
            'phone' => $this->phrase('contact_phone', $languageCode, 'Phone'),
            'whatsapp' => $this->phrase('contact_whatsapp', $languageCode, 'WhatsApp'),
            'linkedin' => 'LinkedIn',
            'youtube' => 'YouTube',
            'line' => 'LINE',
            'wechat' => 'WeChat',
            'address' => $this->phrase('contact_address', $languageCode, 'Address'),
            default => $this->phrase('footer_contact', $languageCode, 'Contact'),
        };
        if ($fieldKey === 'line') {
            $kindLabel = 'LINE';
        }

        $target = false;
        $href = '#';
        switch ($fieldKey) {
            case 'email':
                $href = 'mailto:' . $value;
                break;
            case 'phone':
                $href = 'tel:' . preg_replace('/[^0-9+]/', '', $value);
                break;
            case 'whatsapp':
                $href = preg_match('/^https?:\/\//i', $value) === 1
                    ? $value
                    : 'https://wa.me/' . preg_replace('/[^0-9]/', '', $value);
                $target = true;
                break;
            case 'line':
                $href = preg_match('/^https?:\/\//i', $value) === 1
                    ? $value
                    : 'https://line.me/R/ti/p/~' . rawurlencode($value);
                $target = true;
                break;
            case 'linkedin':
            case 'youtube':
                $href = preg_match('/^https?:\/\//i', $value) === 1 ? $value : 'https://' . ltrim($value, '/');
                $target = true;
                break;
            case 'address':
                $href = $this->contactAnchorRoute($languageCode);
                break;
            case 'wechat':
                $href = '#';
                break;
            default:
                return null;
        }

        return [
            'field_key' => $fieldKey,
            'kind_label' => $kindLabel,
            'display_label' => $label,
            'label' => $value,
            'href' => $href,
            'target' => $target,
        ];
    }

    private function memberActionLinks(array $member, string $languageCode): array
    {
        $actions = [];
        $email = trim((string) ($member['email'] ?? ''));
        $phone = trim((string) ($member['phone'] ?? ''));
        $whatsapp = trim((string) ($member['whatsapp'] ?? $phone));
        if ($email !== '') {
            $actions[] = ['key' => 'email', 'label' => $this->phrase('contact_email', $languageCode, 'Email'), 'href' => 'mailto:' . $email];
        }
        if ($phone !== '') {
            $actions[] = ['key' => 'phone', 'label' => $this->phrase('contact_phone', $languageCode, 'Phone'), 'href' => 'tel:' . preg_replace('/\s+/', '', $phone)];
        }
        if ($whatsapp !== '') {
            $actions[] = ['key' => 'whatsapp', 'label' => 'WhatsApp', 'href' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsapp)];
        }

        return $actions;
    }

    private function salesContactClassSuffix(string $label): string
    {
        $normalized = strtolower(trim($label));

        if (str_contains($label, $this->unicodeText('\u90ae\u7bb1'))) {
            return 'email';
        }
        if (str_contains($label, $this->unicodeText('\u7535\u8bdd'))) {
            return 'phone';
        }

        return match (true) {
            str_contains($normalized, 'mail'), str_contains($label, '邮箱') => 'email',
            str_contains($normalized, 'phone'), str_contains($label, '电话') => 'phone',
            str_contains($normalized, 'whatsapp') => 'whatsapp',
            default => preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?: 'contact',
        };
    }

    private function resolveNewsDetail(string $slug, string $languageCode): array
    {
        foreach ($this->cachedCollectionItems('news', $languageCode) as $item) {
            if ((string) ($item['slug'] ?? '') === $slug) {
                return $item;
            }
        }

        throw new \RuntimeException('产品详情不存在: ' . $slug);
    }

    private function resolveCaseDetail(string $slug, string $languageCode): array
    {
        foreach ($this->cachedCollectionItems('case', $languageCode) as $item) {
            if ((string) ($item['slug'] ?? '') === $slug) {
                return $item;
            }
        }

        throw new \RuntimeException('案例详情不存在: ' . $slug);
    }

    private function publishedStandalonePages(string $languageCode): array
    {
        $items = $this->cachedCollectionItems('page', $languageCode);

        return array_values(array_filter($items, static function (array $item): bool {
            $slug = strtolower(trim((string) ($item['slug'] ?? '')));
            return $slug !== '' && !in_array($slug, ['index', 'home', 'about', 'contact'], true);
        }));
    }

    private function filterRenderableItems(array $items, string $entityType): array
    {
        return array_values(array_filter(
            $items,
            fn (array $item): bool => $this->isPublicRenderableItem($item, $entityType)
        ));
    }

    private function filterRenderableCategories(array $categories): array
    {
        return array_values(array_filter($categories, function (array $category): bool {
            $name = strtolower(trim((string) ($category['name'] ?? $category['name_zh'] ?? '')));
            if ($name === '') {
                return false;
            }

            if (preg_match('/^\d+$/', $name) === 1) {
                return false;
            }

            foreach (['level ', 'test', 'demo', 'sample', 'preview', 'runtime'] as $needle) {
                if (str_contains($name, $needle)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * 过滤"首页显示 + 有真实封面图"的内容，按既有排序（manual_sort DESC）返回。
     * 用于首页中间板块、footer 热门链接、菜单 mega nav 的统一数据筛选。
     */
    private function filterHomeFeaturedWithCover(array $items): array
    {
        return array_values(array_filter(
            $items,
            fn (array $i): bool => (int) ($i['is_home_featured'] ?? 0) === 1
                && !empty($i['has_cover_image'])
        ));
    }

    private function isPublicRenderableItem(array $item, string $entityType): bool
    {
        $slug = strtolower(trim((string) ($item['slug'] ?? '')));
        if ($slug === '') {
            return false;
        }

        if (preg_match('/^\d+$/', $slug) === 1) {
            return false;
        }

        foreach (['runtime-', 'test-', 'demo-', 'temp-', 'draft-', 'sample-', 'example-', 'preview-'] as $prefix) {
            if (str_starts_with($slug, $prefix)) {
                return false;
            }
        }

        $title = strtolower(trim((string) ($item['title'] ?? $item['name'] ?? '')));
        if ($title !== '') {
            if (preg_match('/^\d+$/', $title) === 1) {
                return false;
            }

            foreach (['runtime landing page', 'runtime test', 'test page', 'demo page', 'sample page', 'preview page'] as $needle) {
                if (str_contains($title, $needle)) {
                    return false;
                }
            }
        }

        if ($entityType === 'page' && in_array($slug, ['index', 'home', 'about', 'contact'], true)) {
            return false;
        }

        return true;
    }

    private function findPublishedRecord(string $entityType, int $entityId, string $languageCode): ?array
    {
        if ($entityId <= 0) {
            return null;
        }

        $records = match ($entityType) {
            'product' => $this->cachedCollectionItems('product', $languageCode),
            'solution' => $this->cachedCollectionItems('solution', $languageCode),
            'news' => $this->cachedCollectionItems('news', $languageCode),
            'case' => $this->cachedCollectionItems('case', $languageCode),
            'page' => $this->publishedStandalonePages($languageCode),
            default => [],
        };

        foreach ($records as $record) {
            if ((int) ($record['id'] ?? 0) === $entityId) {
                return $record;
            }
        }

        return null;
    }

    private function backToListRoute(string $entityType, string $languageCode): string
    {
        return match ($entityType) {
            'product' => $this->localizedRoute($languageCode, 'products'),
            'solution' => $this->localizedRoute($languageCode, 'solutions'),
            'news' => $this->localizedRoute($languageCode, 'news'),
            'case' => $this->localizedRoute($languageCode, 'cases'),
            'page' => $this->localizedRoute($languageCode, 'about'),
            default => $this->localizedRoute($languageCode, 'index'),
        };
    }

    private function cachedSite(string $languageCode = 'zh'): array
    {
        $normalizedLanguageCode = strtolower(trim($languageCode)) !== '' ? strtolower(trim($languageCode)) : 'zh';

        return $this->remember('site:' . $normalizedLanguageCode, fn (): array => $this->siteService->site($normalizedLanguageCode));
    }

    /**
     * 缓存广告列表。AdService::publicList 已按 is_enabled + linked_page 过滤，按 sort 排序。
     */
    private function cachedAds(string $languageCode = 'zh', string $pageScope = ''): array
    {
        return $this->remember(
            'ads:' . $languageCode . ':' . $pageScope,
            fn (): array => $this->safeLoad(fn (): array => $this->siteService->ads($languageCode, $pageScope)['items'] ?? [], [])
        );
    }

    private function cachedHomepage(string $languageCode): array
    {
        return $this->remember('homepage:' . $languageCode, fn (): array => $this->siteService->homepage($languageCode));
    }

    private function cachedAbout(string $languageCode): array
    {
        return $this->remember(
            'about:' . $languageCode,
            fn (): array => $this->safeLoad(fn (): array => $this->siteService->about($languageCode), [])
        );
    }

    private function cachedContact(string $languageCode): array
    {
        return $this->remember('contact:' . $languageCode, fn (): array => $this->siteService->contact($languageCode));
    }

    private function cachedNavigation(string $menuKey, string $languageCode): array
    {
        return $this->remember(
            'navigation:' . $menuKey . ':' . $languageCode,
            fn (): array => $this->siteService->navigation($menuKey, $languageCode)
        );
    }

    private function cachedCollectionPayload(string $entityType, string $languageCode): array
    {
        return $this->remember('collection:' . $entityType . ':' . $languageCode, function () use ($entityType, $languageCode): array {
            return match ($entityType) {
                'product' => $this->siteService->products($languageCode, 1, 100000),
                'solution' => $this->siteService->solutions($languageCode, 1, 100000),
                'news' => $this->siteService->newsList($languageCode, 1, 100000),
                'case' => $this->siteService->caseList($languageCode, 1, 100000),
                'page' => $this->safeLoad(fn (): array => $this->siteService->pages($languageCode, 1, 100000), ['items' => []]),
                default => ['items' => [], 'categories' => []],
            };
        });
    }

    private function cachedCollectionItems(string $entityType, string $languageCode): array
    {
        $payload = $this->cachedCollectionPayload($entityType, $languageCode);
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        return $this->filterRenderableItems($items, $entityType);
    }

    private function cachedDetailRecord(string $entityType, string $slug, string $languageCode): array
    {
        return $this->remember('detail:' . $entityType . ':' . $languageCode . ':' . $slug, function () use ($entityType, $slug, $languageCode): array {
            return match ($entityType) {
                'product' => $this->siteService->productDetail($slug, $languageCode),
                'solution' => $this->siteService->solutionDetail($slug, $languageCode),
                'news' => $this->resolveNewsDetail($slug, $languageCode),
                'case' => $this->resolveCaseDetail($slug, $languageCode),
                'page' => $this->siteService->pageDetail($slug, $languageCode),
                default => [],
            };
        });
    }

    private function safeLoad(callable $loader, array $fallback): array
    {
        try {
            $payload = $loader();
            return is_array($payload) ? $payload : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function remember(string $key, callable $resolver): array
    {
        if (array_key_exists($key, $this->renderCache)) {
            return is_array($this->renderCache[$key]) ? $this->renderCache[$key] : [];
        }

        $value = $resolver();
        $this->renderCache[$key] = is_array($value) ? $value : [];

        return is_array($this->renderCache[$key]) ? $this->renderCache[$key] : [];
    }

    private function rememberFragment(string $key, callable $resolver): string
    {
        if (array_key_exists($key, $this->fragmentCache)) {
            return is_string($this->fragmentCache[$key]) ? $this->fragmentCache[$key] : '';
        }

        $value = $resolver();
        $this->fragmentCache[$key] = is_string($value) ? $value : '';

        return is_string($this->fragmentCache[$key]) ? $this->fragmentCache[$key] : '';
    }

    private function excerpt(string $text, int $maxLength): string
    {
        $text = $this->sanitizeSummary($text);
        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > $maxLength ? mb_substr($text, 0, $maxLength - 1) . '...' : $text;
    }

    private function sanitizeSummary(string $text, int $maxLength = 0): string
    {
        $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        $text = preg_replace('/\b111\b/u', '', $text) ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '' || $maxLength <= 0) {
            return $text;
        }

        return mb_strlen($text) > $maxLength ? mb_substr($text, 0, $maxLength) : $text;
    }

    private function normalizeRichContent(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        return str_replace(
            [
                'http://127.0.0.1:8080/uploads/',
                'https://127.0.0.1:8080/uploads/',
                'http://localhost:8080/uploads/',
                'https://localhost:8080/uploads/',
            ],
            [
                '/uploads/',
                '/uploads/',
                '/uploads/',
                '/uploads/',
            ],
            $content
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function unicodeText(string $unicode): string
    {
        $decoded = json_decode('"' . $unicode . '"');

        return is_string($decoded) ? $decoded : '';
    }

    private function phaseForTarget(string $pageType): string
    {
        return match ($pageType) {
            'sitemap', 'robots', 'root_redirect' => 'rebuild_sitemap',
            default => 'render_templates',
        };
    }

    private function runningProgressPercent(int $completed, int $total): int
    {
        if ($total <= 0) {
            return 99;
        }

        return min(99, max(0, (int) floor(($completed / $total) * 99)));
    }

    private function progressPercent(int $completed, int $total): int
    {
        if ($total <= 0) {
            return 100;
        }

        return (int) floor(($completed / $total) * 100);
    }
}
