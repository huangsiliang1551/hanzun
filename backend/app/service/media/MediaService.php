<?php

declare(strict_types=1);

namespace app\service\media;

use app\common\exception\BusinessException;
use app\enum\ErrorCode;
use app\repository\CertificateRepository;
use app\repository\MediaRepository;
use app\repository\SolutionRepository;
use app\repository\TeamRepository;
use app\service\log\OperationLogService;

final class MediaService
{
    public function __construct(
        private readonly MediaRepository $mediaRepository = new MediaRepository(),
        private readonly SolutionRepository $solutionRepository = new SolutionRepository(),
        private readonly TeamRepository $teamRepository = new TeamRepository(),
        private readonly CertificateRepository $certificateRepository = new CertificateRepository(),
        private readonly OperationLogService $operationLogService = new OperationLogService()
    ) {
    }

    public function assertMimeAllowed(string $category, string $mimeType): void
    {
        $allowed = config('upload.mime_types.' . $category, []);
        if (!in_array($mimeType, $allowed, true)) {
            throw new BusinessException('婵炲濮撮幊搴ㄥ储閹寸姵濯奸梻鈧幇顔炬啰婵炵鍋愭慨鏉懨瑰鈧幃褔宕堕妷銏犱壕濞达絿鐡斿鎺懳涢悧鍫濈仸闁?PDF 闂佸搫鍊稿ú锝呪枎閵忋倕违?, ErrorCode::UNSUPPORTED_FILE_TYPE);
        }
    }

    public function assertUploadFileAllowed(string $originalFileName, string $mimeType = ''): void
    {
        $category = $this->categoryFromFileName($originalFileName);
        if ($mimeType !== '') {
            $this->assertMimeAllowed($category, $mimeType);
        }
    }

    public function assets(array $query = []): array
    {
        $normalized = $this->normalizeAssetQuery($query);
        $allItems = $this->mediaRepository->list();
        $folderSummaryQuery = $normalized;
        $folderSummaryQuery['folder_name'] = '';
        $folderSummaryQuery['folder_id'] = 0;
        $filtered = $this->filterAssets($allItems, $normalized);
        $sorted = $this->sortAssets($filtered, $normalized);
        $paged = $this->paginateAssets($sorted, $normalized);

        return [
            'items' => array_map(function (array $item): array {
                $item['thumbnail_url'] = (string) ($item['thumb_url'] ?? $item['file_path'] ?? '');
                return $item;
            }, $paged['items']),
            'folders' => $this->folderSummary($allItems),
            'folder_counts' => $this->folderCounts($this->filterAssets($allItems, $folderSummaryQuery)),
            'filters' => ['folder_name', 'file_category', 'status', 'keyword'],
            'pagination' => $paged['pagination'],
            'sort' => [
                'field' => (string) $normalized['sort_field'],
                'order' => (string) $normalized['sort_order'],
            ],
        ];
    }

    public function bootstrap(array $query = []): array
    {
        return [
            'assets' => $this->assets($query),
            'folders' => $this->folderTree(),
        ];
    }

    public function picker(array $query = []): array
    {
        $normalized = $this->normalizeAssetQuery($query);
        $filtered = $this->sortAssets($this->filterAssets($this->mediaRepository->list(), $normalized), $normalized);

        return [
            'items' => array_map(function (array $item): array {
                return [
                    'id' => (int) ($item['id'] ?? 0),
                    'file_name' => (string) ($item['file_name'] ?? ''),
                    'file_path' => (string) ($item['file_path'] ?? ''),
                    'thumbnail_url' => (string) ($item['thumb_url'] ?? $item['file_path'] ?? ''),
                    'folder_name' => (string) ($item['folder_name'] ?? ''),
                    'mime_type' => (string) ($item['mime_type'] ?? ''),
                    'file_ext' => (string) ($item['file_ext'] ?? ''),
                    'file_size' => (int) ($item['file_size'] ?? 0),
                    'file_category' => $this->fileCategory($item),
                    'status' => (int) ($item['status'] ?? 0),
                ];
            }, array_slice($filtered, 0, 100)),
        ];
    }

    public function detail(int $id): array
    {
        $record = $this->mediaRepository->find($id);
        if ($record === null) {
            throw new BusinessException('闁荤姍鍐仾缂侇煈鍠楃粙澶婎吋閸涱垱鎲奸梺闈╄礋閸斿﹪鍩€?, ErrorCode::NOT_FOUND);
        }

        return $record;
    }

    public function create(array $input): array
    {
        [$sourcePath, $originalFileName, $isTemporaryFile] = $this->resolveSourceFile($input);
        $folderName = $this->normalizeFolderName((string) ($input['folder_name'] ?? 'misc'));
        $altText = $this->normalizeText((string) ($input['alt_text_zh'] ?? ''), 120, '闂佸搫娲ら妵姗€宕鍕闁搞儯鍔嶉幏?);
        $description = $this->normalizeText((string) ($input['description_zh'] ?? ''), 500, '闂佺顕х换妤呭醇?);
        $status = $this->normalizeStatus($input['status'] ?? 1);

        try {
            return $this->persistFile($sourcePath, $originalFileName, $folderName, $altText, $description, $status);
        } finally {
            if ($isTemporaryFile && is_file($sourcePath)) {
                @unlink($sourcePath);
            }
        }
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function storeUploadedFile(array $file, array $options = []): array
    {
        $originalFileName = $file['name'] ?? '';
        $tmpPath = $file['tmp_name'] ?? '';

        if ($originalFileName === '' || $tmpPath === '' || !is_file($tmpPath)) {
            throw new BusinessException('婵炴垶鎸搁敃锝囨閸洖妫橀柛銉檮椤愪粙鏌￠崘顏勑ｉ柡鍛劦閺佸秴鐣濋崘鎯ф闂備焦褰冪粔鐢稿蓟婵犲洦鐒诲璺侯儏椤忋儵鏌￠崒姘煑婵炲棎鍨芥俊?, ErrorCode::INVALID_PARAMS);
        }

        $folderId = (int) ($options['folder_id'] ?? 0);
        $folderName = $this->resolveFolderNameForWrite(
            $folderId,
            (string) ($options['folder_name'] ?? 'misc')
        );
        $altText = $this->normalizeText((string) ($options['alt_text_zh'] ?? ''), 120, '闂佸搫娲ら妵姗€宕鍕闁搞儯鍔嶉幏?);
        $description = $this->normalizeText((string) ($options['description_zh'] ?? ''), 500, '闂佺顕х换妤呭醇?);

        $category = $this->categoryFromFileName($originalFileName);
        $extension = $this->extractExtension($originalFileName);
        $mimeType = $this->detectMimeType($tmpPath, $extension);
        $this->assertMimeAllowed($category, $mimeType);
        $this->assertFileSizeAllowed($tmpPath, $category);

        // Use UUID-based filename
        $uuid = bin2hex(random_bytes(16));
        $targetFileName = $uuid . '.' . $extension;

        // Sub-directory by category for uploads
        $subDir = match ($category) {
            'image' => 'images',
            'video' => 'videos',
            'pdf' => 'documents',
            default => 'files',
        };

        $targetDirectory = base_path('public/uploads/' . $subDir);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new BusinessException('闂佸憡甯楃粙鎴犵磽閹惧鈻斿┑鐘辫兌閻愬﹪鏌ｉ埡濠傛灈缂傚秴绉靛鍕綇椤愩儛鏇㈡煏?, ErrorCode::UPLOAD_FAILED);
        }

        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $targetFileName;
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new BusinessException('婵烇絽娲︾换鍌炴偤閵婏妇鈻斿┑鐘辫兌閻愬﹪鏌￠崒姘煑婵炲棎鍨哄鍕綇椤愩儛鏇㈡煏?, ErrorCode::UPLOAD_FAILED);
        }

        $imageMeta = null;
        if ($category === 'image') {
            $imageMeta = $this->optimizeImageForWeb($targetPath, $extension);
        }

        $payload = [
            'folder_id' => $folderId,
            'folder_name' => $folderName,
            'storage_disk' => 'local',
            'file_path' => '/uploads/' . $subDir . '/' . $targetFileName,
            'file_name' => $targetFileName,
            'original_name' => $originalFileName,
            'file_ext' => $extension,
            'mime_type' => $mimeType,
            'file_size' => filesize($targetPath) ?: 0,
            'sha1' => sha1_file($targetPath) ?: null,
            'width' => $imageMeta['width'] ?? null,
            'height' => $imageMeta['height'] ?? null,
            'duration_seconds' => null,
            'alt_text_zh' => $altText,
            'description_zh' => $description,
            'uploaded_by' => current_user()['id'] ?? null,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($category === 'image') {
            // Generate thumbnail
            $thumbPath = $this->generateThumbnail($targetPath, $uuid, $extension);
            if ($thumbPath !== null) {
                $payload['thumb_url'] = '/uploads/thumbs/' . $uuid . '.' . $extension;
            }
        } elseif ($category === 'video') {
            $thumbPath = $this->generateVideoThumbnailPlaceholder($uuid, $originalFileName);
            if ($thumbPath !== null) {
                $payload['thumb_url'] = '/uploads/thumbs/' . $uuid . '.png';
            }
        }

        $record = $this->mediaRepository->create($payload);
        $this->operationLogService->recordCurrentAction('media', 'media.upload', 'media_asset', $record, 'media uploaded');

        return $record;
    }

    private function resolveFolderNameForWrite(int $folderId, string $fallbackName = 'misc'): string
    {
        if ($folderId > 0) {
            $folder = $this->mediaRepository->findFolder($folderId);
            $folderName = trim((string) ($folder['name'] ?? ''));
            if ($folderName !== '') {
                return $this->normalizeFolderName($folderName);
            }
        }

        return $this->normalizeFolderName($fallbackName);
    }

    /**
     * Generate a 200x200 thumbnail for an image using GD.
     */
    private function generateThumbnail(string $sourcePath, string $uuid, string $extension): ?string
    {
        $thumbDir = base_path('public/uploads/thumbs');
        if (!is_dir($thumbDir) && !mkdir($thumbDir, 0777, true) && !is_dir($thumbDir)) {
            return null;
        }

        $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $uuid . '.' . $extension;
        $thumbWidth = 200;
        $thumbHeight = 200;

        // Create GD image from source
        $sourceImage = match ($extension) {
            'jpg', 'jpeg' => @\imagecreatefromjpeg($sourcePath),
            'png' => @\imagecreatefrompng($sourcePath),
            'webp' => @\imagecreatefromwebp($sourcePath),
            default => null,
        };

        if ($sourceImage === null) {
            return null;
        }

        $origWidth = \imagesx($sourceImage);
        $origHeight = \imagesy($sourceImage);

        // Calculate proportional resize
        $ratio = min($thumbWidth / max($origWidth, 1), $thumbHeight / max($origHeight, 1));
        $newWidth = (int) round($origWidth * $ratio);
        $newHeight = (int) round($origHeight * $ratio);

        $thumbImage = \imagecreatetruecolor($thumbWidth, $thumbHeight);
        if ($thumbImage === false) {
            \imagedestroy($sourceImage);
            return null;
        }

        // Preserve transparency for PNG
        if ($extension === 'png') {
            \imagealphablending($thumbImage, false);
            \imagesavealpha($thumbImage, true);
        }

        // Fill background with white for non-transparent images
        $white = \imagecolorallocate($thumbImage, 255, 255, 255);
        \imagefilledrectangle($thumbImage, 0, 0, $thumbWidth, $thumbHeight, $white);

        // Center the resized image
        $dstX = (int) round(($thumbWidth - $newWidth) / 2);
        $dstY = (int) round(($thumbHeight - $newHeight) / 2);

        \imagecopyresampled($thumbImage, $sourceImage, $dstX, $dstY, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        $saved = match ($extension) {
            'jpg', 'jpeg' => \imagejpeg($thumbImage, $thumbPath, 85),
            'png' => \imagepng($thumbImage, $thumbPath, 6),
            'webp' => \imagewebp($thumbImage, $thumbPath, 80),
            default => false,
        };

        \imagedestroy($sourceImage);
        \imagedestroy($thumbImage);

        return $saved ? $thumbPath : null;
    }

    private function generateVideoThumbnailPlaceholder(string $uuid, string $originalFileName): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $thumbDir = base_path('public/uploads/thumbs');
        if (!is_dir($thumbDir) && !mkdir($thumbDir, 0777, true) && !is_dir($thumbDir)) {
            return null;
        }

        $thumbPath = $thumbDir . DIRECTORY_SEPARATOR . $uuid . '.png';
        $width = 640;
        $height = 360;
        $image = \imagecreatetruecolor($width, $height);
        if ($image === false) {
            return null;
        }

        $background = \imagecolorallocate($image, 15, 23, 42);
        $accent = \imagecolorallocate($image, 37, 99, 235);
        $accentSoft = \imagecolorallocate($image, 96, 165, 250);
        $white = \imagecolorallocate($image, 255, 255, 255);
        $muted = \imagecolorallocate($image, 219, 234, 254);

        \imagefilledrectangle($image, 0, 0, $width, $height, $background);
        \imagefilledrectangle($image, 0, $height - 76, $width, $height, $accent);
        \imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2) - 12, 120, 120, $accentSoft);
        \imagefilledellipse($image, (int) ($width / 2), (int) ($height / 2) - 12, 92, 92, $accent);

        $triangle = [
            (int) ($width / 2) - 14, (int) ($height / 2) - 40,
            (int) ($width / 2) - 14, (int) ($height / 2) + 16,
            (int) ($width / 2) + 30, (int) ($height / 2) - 12,
        ];
        \imagefilledpolygon($image, $triangle, $white);

        $label = 'VIDEO';
        $fileLabel = strtoupper((string) pathinfo($originalFileName, PATHINFO_FILENAME));
        $fileLabel = preg_replace('/[^A-Z0-9 _-]+/', '', $fileLabel) ?? '';
        $fileLabel = trim((string) $fileLabel);
        if ($fileLabel === '') {
            $fileLabel = 'MEDIA ASSET';
        }
        if (strlen($fileLabel) > 28) {
            $fileLabel = substr($fileLabel, 0, 28);
        }

        \imagestring($image, 5, 24, 24, $label, $white);
        \imagestring($image, 4, 24, $height - 48, $fileLabel, $muted);

        $saved = \imagepng($image, $thumbPath, 6);
        \imagedestroy($image);

        return $saved ? $thumbPath : null;
    }

    /**
     * @return array{width:int,height:int}|null
     */
    private function optimizeImageForWeb(string $filePath, string $extension): ?array
    {
        $extension = strtolower($extension);
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $imageInfo = @\getimagesize($filePath);
            return is_array($imageInfo)
                ? ['width' => (int) ($imageInfo[0] ?? 0), 'height' => (int) ($imageInfo[1] ?? 0)]
                : null;
        }

        $imageInfo = @\getimagesize($filePath);
        if (!is_array($imageInfo)) {
            return null;
        }

        $origWidth = (int) ($imageInfo[0] ?? 0);
        $origHeight = (int) ($imageInfo[1] ?? 0);
        if ($origWidth <= 0 || $origHeight <= 0) {
            return null;
        }

        $sourceImage = match ($extension) {
            'jpg', 'jpeg' => @\imagecreatefromjpeg($filePath),
            'png' => @\imagecreatefrompng($filePath),
            'webp' => @\imagecreatefromwebp($filePath),
            default => null,
        };

        if ($sourceImage === null || $sourceImage === false) {
            return ['width' => $origWidth, 'height' => $origHeight];
        }

        $maxWidth = 1920;
        $maxHeight = 1920;
        $ratio = min(1, $maxWidth / max($origWidth, 1), $maxHeight / max($origHeight, 1));
        $targetWidth = max(1, (int) round($origWidth * $ratio));
        $targetHeight = max(1, (int) round($origHeight * $ratio));
        $targetImage = $sourceImage;

        if ($ratio < 1) {
            $canvas = \imagecreatetruecolor($targetWidth, $targetHeight);
            if ($canvas !== false) {
                if ($extension === 'png' || $extension === 'webp') {
                    \imagealphablending($canvas, false);
                    \imagesavealpha($canvas, true);
                    $transparent = \imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                    \imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
                } else {
                    $white = \imagecolorallocate($canvas, 255, 255, 255);
                    \imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
                }

                \imagecopyresampled(
                    $canvas,
                    $sourceImage,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $origWidth,
                    $origHeight
                );
                $targetImage = $canvas;
            }
        }

        match ($extension) {
            'jpg', 'jpeg' => \imagejpeg($targetImage, $filePath, 82),
            'png' => \imagepng($targetImage, $filePath, 7),
            'webp' => \imagewebp($targetImage, $filePath, 80),
            default => null,
        };

        if ($targetImage !== $sourceImage) {
            \imagedestroy($targetImage);
        }
        \imagedestroy($sourceImage);

        clearstatcache(true, $filePath);

        return ['width' => $targetWidth, 'height' => $targetHeight];
    }

    private function persistFile(string $sourcePath, string $originalFileName, string $folderName, string $altText, string $description, int $status): array
    {
        $category = $this->categoryFromFileName($originalFileName);
        $extension = $this->extractExtension($originalFileName);
        $mimeType = $this->detectMimeType($sourcePath, $extension);
        $this->assertMimeAllowed($category, $mimeType);
        $this->assertFileSizeAllowed($sourcePath, $category);

        // Use UUID-based filename
        $uuid = bin2hex(random_bytes(16));
        $targetFileName = $uuid . '.' . $extension;

        // Sub-directory by category
        $subDir = match ($category) {
            'image' => 'images',
            'video' => 'videos',
            'pdf' => 'documents',
            default => 'files',
        };

        $targetDirectory = base_path('public/uploads/' . $subDir);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new BusinessException('闂佸憡甯楃粙鎴犵磽閹惧鈻斿┑鐘辫兌閻愬﹪鏌ｉ埡濠傛灈缂傚秴绉靛鍕綇椤愩儛鏇㈡煏?, ErrorCode::UPLOAD_FAILED);
        }

        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $targetFileName;
        if (!copy($sourcePath, $targetPath)) {
            throw new BusinessException('婵烇絽娲︾换鍌炴偤閵婏妇鈻斿┑鐘辫兌閻愬﹪鏌￠崒姘煑婵炲棎鍨哄鍕綇椤愩儛鏇㈡煏?, ErrorCode::UPLOAD_FAILED);
        }

        $imageMeta = null;
        if ($category === 'image') {
            $imageMeta = $this->optimizeImageForWeb($targetPath, $extension);
        }

        $payload = [
            'folder_name' => $folderName,
            'storage_disk' => 'local',
            'file_path' => '/uploads/' . $subDir . '/' . $targetFileName,
            'file_name' => $targetFileName,
            'original_name' => $originalFileName,
            'file_ext' => $extension,
            'mime_type' => $mimeType,
            'file_size' => filesize($targetPath) ?: 0,
            'sha1' => sha1_file($targetPath) ?: null,
            'width' => $imageMeta['width'] ?? null,
            'height' => $imageMeta['height'] ?? null,
            'duration_seconds' => null,
            'alt_text_zh' => $altText,
            'description_zh' => $description,
            'uploaded_by' => current_user()['id'] ?? null,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($category === 'image') {
            $thumbPath = $this->generateThumbnail($targetPath, $uuid, $extension);
            if ($thumbPath !== null) {
                $payload['thumb_url'] = '/uploads/thumbs/' . $uuid . '.' . $extension;
            }
        } elseif ($category === 'video') {
            $thumbPath = $this->generateVideoThumbnailPlaceholder($uuid, $originalFileName);
            if ($thumbPath !== null) {
                $payload['thumb_url'] = '/uploads/thumbs/' . $uuid . '.png';
            }
        }

        $record = $this->mediaRepository->create($payload);
        $this->operationLogService->recordCurrentAction('media', 'media.create', 'media_asset', $record, 'media asset created');

        return $record;
    }

    public function update(int $id, array $input): array
    {
        $existing = $this->detail($id);
        $updated = $this->mediaRepository->update($id, array_merge($existing, [
            'folder_name' => $this->normalizeFolderName((string) ($input['folder_name'] ?? ($existing['folder_name'] ?? 'misc'))),
            'alt_text_zh' => $this->normalizeText((string) ($input['alt_text_zh'] ?? ($existing['alt_text_zh'] ?? '')), 120, '闂佸搫娲ら妵姗€宕鍕闁搞儯鍔嶉幏?),
            'description_zh' => $this->normalizeText((string) ($input['description_zh'] ?? ($existing['description_zh'] ?? '')), 500, '闂佺顕х换妤呭醇?),
            'status' => array_key_exists('status', $input) ? $this->normalizeStatus($input['status']) : (int) ($existing['status'] ?? 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));

        if ($updated === null) {
            throw new BusinessException('闁荤姍鍐仾缂侇煈鍠楃粙澶婎吋閸涱垱鎲奸梺闈╄礋閸斿﹪鍩€?, ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('media', 'media.update', 'media_asset', $updated, 'media updated');

        return $updated;
    }

    public function updateStatus(int $id, int $status): array
    {
        $updated = $this->mediaRepository->updateStatus($id, $this->normalizeStatus($status));
        if ($updated === null) {
            throw new BusinessException('闁荤姍鍐仾缂侇煈鍠楃粙澶婎吋閸涱垱鎲奸梺闈╄礋閸斿﹪鍩€?, ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('media', 'media.status.update', 'media_asset', $updated, 'media status updated');

        return $updated;
    }

    public function references(int $id): array
    {
        $asset = $this->detail($id);
        $items = [];
        $referenceKeys = [];

        $solutionPayload = $this->solutionRepository->list();
        $solutionItems = is_array($solutionPayload['items'] ?? null) ? $solutionPayload['items'] : (is_array($solutionPayload) ? $solutionPayload : []);
        foreach ($solutionItems as $solution) {
            $this->appendReferenceIfMatch($items, $referenceKeys, $id, $solution, 'manual_asset_id', 'solution', 'name_zh');
        }

        foreach ($this->teamRepository->list() as $member) {
            $this->appendReferenceIfMatch($items, $referenceKeys, $id, $member, 'avatar_asset_id', 'team_member', 'name_zh');
        }

        foreach ($this->certificateRepository->list() as $certificate) {
            $this->appendReferenceIfMatch($items, $referenceKeys, $id, $certificate, 'image_asset_id', 'certificate', 'name_zh');
        }

        return [
            'asset' => $asset,
            'references' => $items,
            'reference_count' => count($items),
            'can_delete' => count($items) === 0 ? 1 : 0,
        ];
    }

    public function remove(int $id): array
    {
        $references = $this->references($id);
        $referenceCount = (int) ($references['reference_count'] ?? 0);
        if ($referenceCount > 0) {
            throw new BusinessException('asset is still referenced by other content', ErrorCode::INVALID_PARAMS);
        }

        $deleted = $this->mediaRepository->delete($id);
        if ($deleted === null) {
            throw new BusinessException('闁荤姍鍐仾缂侇煈鍠楃粙澶婎吋閸涱垱鎲奸梺闈╄礋閸斿﹪鍩€?, ErrorCode::NOT_FOUND);
        }

        $diskPath = $this->resolveDiskPath($deleted);
        if ($diskPath !== null && is_file($diskPath)) {
            @unlink($diskPath);
        }

        $this->operationLogService->recordCurrentAction('media', 'media.delete', 'media_asset', $deleted, 'media deleted');

        return $deleted;
    }

    public function rename(int $id, string $newFileName): array
    {
        $newFileName = trim($newFileName);
        if ($newFileName === '') throw new BusinessException('闂佸搫鍊稿ú锝呪枎閵忋倕瑙︾€广儱瀚悷婵嬫煠閾忣偄鐏婇悹鎰枔缁岸骞侀幒鍡椾壕?, ErrorCode::INVALID_PARAMS);
        if (mb_strlen($newFileName) > 255) throw new BusinessException('闂佸搫鍊稿ú锝呪枎閵忋倕瑙︾€广儱鐗忕粻鏍⒒閳ь剛鎲楅妶鍌氫壕?, ErrorCode::INVALID_PARAMS);

        $asset = $this->mediaRepository->find($id);
        if (!$asset) throw new BusinessException('闁荤姍鍐仾缂侇煈鍠楃粙澶婎吋閸涱垱鎲奸梺闈╄礋閸斿﹪鍩€?, ErrorCode::NOT_FOUND);

        $ext = pathinfo($newFileName, PATHINFO_EXTENSION);
        $oldExt = $asset['file_ext'] ?? '';
        if ($ext !== '' && strtolower($ext) !== strtolower($oldExt)) {
            throw new BusinessException('闂佸搫鍊稿ú锝呪枎閵忋倕绠ラ柍杞拌兌濞兼棃鏌涘顒傂㈢紒鐑╁亾婵＄偑鍊涢濠勭箔瀹€鍕偍闁绘柨鎲￠悗顔济归悩铏瀯缂佺粯宀搁獮鎰媴妞嬪寒浼囬梺鑲╂焿閹活亞妲? . $oldExt . '闂佹寧绋戦ˇ顓㈠焵?, ErrorCode::INVALID_PARAMS);
        }
        if ($ext === '') $newFileName .= '.' . $oldExt;

        $updated = $this->mediaRepository->update($id, [
            'file_name' => $newFileName,
        ]);
        return $updated ?? $asset;
    }

    private function categoryFromExtension(string $extension): string
    {
        $extension = strtolower(trim($extension));
        foreach ($this->allowedExtensionsByCategory() as $category => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return $category;
            }
        }

        throw new BusinessException('婵炲濮撮幊搴ㄥ储閹寸姵濯奸梻鈧幇顔炬啰婵炵鍋愭慨鏉懨瑰鈧幃褔宕堕妷銏犱壕濞达絿鐡斿鎺懳涢悧鍫濈仸闁?PDF 闂佸搫鍊稿ú锝呪枎閵忋倕违?, ErrorCode::UNSUPPORTED_FILE_TYPE);
    }

    private function categoryFromFileName(string $fileName): string
    {
        $extension = $this->extractExtension($fileName);
        $this->assertExtensionAllowed($fileName, $extension);

        return $this->categoryFromExtension($extension);
    }

    private function detectMimeType(string $sourcePath, string $extension): string
    {
        $mimeType = function_exists('mime_content_type') ? mime_content_type($sourcePath) : null;
        if (is_string($mimeType) && $mimeType !== '' && $mimeType !== 'application/octet-stream') {
            return $mimeType;
        }

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    private function resolveAllowedSourcePath(string $sourcePath): string
    {
        $sourcePath = trim($sourcePath);
        if ($sourcePath === '') {
            throw new BusinessException('缂傚倸鍊搁幖顐︽儍椤栨埃鏀﹂柟閭﹀幗閻庮喖霉閻樺啿鍔堕柣顓熷劤椤曘儵宕熼埞鎯т壕?, ErrorCode::INVALID_PARAMS);
        }

        if (str_starts_with($sourcePath, 'http://') || str_starts_with($sourcePath, 'https://')) {
            throw new BusinessException('闂佸搫鍊稿ú锝呪枎閵忋垺宕夋い鏍ㄦ皑缁愮偛鈽夐幘宕囆ラ柛蹇旓耿瀵?URL闂?, ErrorCode::INVALID_PARAMS);
        }

        if (str_contains($sourcePath, '..')) {
            throw new BusinessException('闂佸搫鍊稿ú锝呪枎閵忋垺宕夋い鏍ㄦ皑缁愮偛鈽夐幘宕囆㈤柟顔奸閳绘棃寮村Ο宄颁壕?, ErrorCode::INVALID_PARAMS);
        }

        $realPath = realpath($sourcePath);
        if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
            throw new BusinessException('濠电姍鍕闁哄鍟粋鎺楀嫉閻㈢數鎲归柣搴㈢⊕閿氭繝鈧鍫濈闁哄稄濡囬悷婵嬫煕濞嗘ê鐏ユい鏇氬嵆瀹曪綁寮介妷銏犱壕?, ErrorCode::INVALID_PARAMS);
        }

        $allowedRoots = array_filter([
            realpath(base_path('runtime/imports')),
            realpath(base_path('public/uploads/tmp')),
        ]);

        foreach ($allowedRoots as $allowedRoot) {
            if ($realPath === $allowedRoot || str_starts_with($realPath, $allowedRoot . DIRECTORY_SEPARATOR)) {
                return $realPath;
            }
        }

        throw new BusinessException('濠电姍鍕闁哄鍟粋鎺楀箚閹殿喚鍞撮悗鍨緲鐎氼亞绮径鎰嵍闁靛鍎辩敮鎺楁偣娴ｅ憡鎲告繛鍫熷灥椤斿繘濡烽妶鍥┾枙闂佺儵鏅╅崰鏍礊瀹ュ绀冮柛娑卞亐閸?, ErrorCode::INVALID_PARAMS);
    }

    /**
     * @return array{0:string,1:string,2:bool}
     */
    private function resolveSourceFile(array $input): array
    {
        $inlineContent = trim((string) ($input['file_content_base64'] ?? ''));
        $fileName = trim((string) ($input['file_name'] ?? ''));

        if ($inlineContent !== '') {
            $normalizedFileName = $this->normalizeUploadFileName($fileName);
            $tempPath = $this->createTempSourceFileFromBase64($normalizedFileName, $inlineContent);
            return [$tempPath, $normalizedFileName, true];
        }

        $sourcePath = $this->resolveAllowedSourcePath((string) ($input['source_path'] ?? ''));
        return [$sourcePath, basename($sourcePath), false];
    }
    private function normalizeUploadFileName(string $fileName): string
    {
        $fileName = trim($fileName);
        if ($fileName === '') {
            throw new BusinessException('缂傚倸鍊搁幖顐︽儍椤栫偛妫橀柛銉檮椤愪粙鏌涘顒傂犻柍?, ErrorCode::INVALID_PARAMS);
        }

        $fileName = basename($fileName);
        if (preg_match('/^[A-Za-z0-9._-]{1,120}$/', $fileName) !== 1) {
            throw new BusinessException('闂佸搫鍊稿ú锝呪枎閵忋倕瑙︾€广儱妫涙竟鎰偓娈垮枛缁诲绮径鎰Е闁割偆鍠撻妴濠囨煥濞戞鐒锋い鏇ㄥ墯缁傛帡宕ㄩ鈧埢蹇涙煟椤剙濡奸柣鈯欏唭鎺戭吋閸愶絽浜惧ù锝堫潐濞堝爼鎮楀☉娆嶄粻闁逞屽厸閼冲爼宕戦敐澶娢ュù锝夘棑閻熸捇鏌涢幒鎾崇闁搞倕閰ｉ獮瀣冀椤愩倕褰嬪┑鈽嗗灱娴滅偤宕垫惔銊ノ?, ErrorCode::INVALID_PARAMS);
        }

        $this->assertUploadFileAllowed($fileName);

        return $fileName;
    }

    private function createTempSourceFileFromBase64(string $fileName, string $inlineContent): string
    {
        $payload = $inlineContent;
        if (preg_match('/^data:[^,]+;base64,(.+)$/i', $payload, $matches) === 1) {
            $payload = $matches[1];
        }

        $payload = preg_replace('/\s+/', '', $payload);
        if (!is_string($payload) || $payload === '') {
            throw new BusinessException('闂佸搫鍊稿ú锝呪枎閵忋倕绀冮柛娑卞弾閸熷洭鏌￠崘顏勑ｉ柡鍛劦婵?, ErrorCode::INVALID_PARAMS);
        }
        $category = $this->categoryFromFileName($fileName);
        $estimatedSize = $this->estimateBase64DecodedSize($payload);
        $maxAllowed = (int) config('upload.limits.' . $category, 0);
        if ($estimatedSize > 0 && $maxAllowed > 0 && $estimatedSize > $maxAllowed) {
            throw new BusinessException('闂佸搫鍊稿ú锝呪枎閵忋倕绀冮柛娑卞弾閸熷洭寮堕埡浣规悙闁靛洤娲俊?, ErrorCode::FILE_TOO_LARGE);
        }

        $binaryContent = base64_decode($payload, true);
        if ($binaryContent === false || $binaryContent === '') {
            throw new BusinessException('闂佸搫鍊稿ú锝呪枎閵忋倕绀冮柛娑卞弾閸熷洭鏌￠崘顏勑ｉ柡鍛劦閺佸秶浠﹂悙顒婇獜濠电偛顦板ú婵婎暰闂佸搫顑嗛崝鎺旂箔閸屾稑顕遍柣妯挎珪濞堝爼鏌熺拠鈥虫灁闁?, ErrorCode::INVALID_PARAMS);
        }

        $extension = $this->extractExtension($fileName);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'cms-media-');
        if ($temporaryPath === false) {
            throw new BusinessException('闂佸憡甯楃粙鎴犵磽閹惧鈻旈悗娑櫳戦ˇ褍鈽夐幘绛规缂佸崬鐖煎顒勫炊閿旂瓔鍋ㄦ繝銏″劶缁墽鎲撮敃鍌毼?, ErrorCode::UPLOAD_FAILED);
        }

        $targetTemporaryPath = $temporaryPath . '.' . $extension;
        if (!@rename($temporaryPath, $targetTemporaryPath)) {
            @unlink($temporaryPath);
            throw new BusinessException('闂佸憡甯楃粙鎴犵磽閹惧鈻旈悗娑櫳戦ˇ褍鈽夐幘绛规缂佸崬鐖煎顒勫炊閿旂瓔鍋ㄦ繝銏″劶缁墽鎲撮敃鍌毼?, ErrorCode::UPLOAD_FAILED);
        }

        if (file_put_contents($targetTemporaryPath, $binaryContent, LOCK_EX) === false) {
            @unlink($targetTemporaryPath);
            throw new BusinessException('闂佸憡鍔栭悷銉╁矗閸℃鈻旈悗娑櫳戦ˇ褍鈽夐幘绛规缂佸崬鐖煎顒勫炊閿旂瓔鍋ㄦ繝銏″劶缁墽鎲撮敃鍌毼?, ErrorCode::UPLOAD_FAILED);
        }

        return $targetTemporaryPath;
    }
    private function normalizeFolderName(string $folderName): string
    {
        $folderName = trim($folderName);
        if ($folderName === '') {
            $folderName = 'misc';
        }

        if (mb_strlen($folderName) > 50 || str_contains($folderName, '..') || preg_match('/[\r\n\t\x00-\x1F\x7F]/u', $folderName) === 1) {
            throw new BusinessException('闂佺儵鏅╅崰鏍礊瀹ュ瑙︾€广儱娉﹂悙瀵糕枖鐎广儱鎳忛崐銈嗙箾婢跺纾搁柍?, ErrorCode::INVALID_PARAMS);
        }

        return trim($folderName, " /");
    }

    private function normalizeText(string $value, int $maxLength, string $fieldLabel): string
    {
        $value = trim($value);
        if (mb_strlen($value) > $maxLength) {
            throw new BusinessException($fieldLabel . '闂傚倵鍋撻柛顭戝枛椤斿﹪鎮洪幒鎴炲櫣闁搞値鍙冨浠嬪箛椤掆偓閻撴垿鏌?, ErrorCode::INVALID_PARAMS);
        }

        return $value;
    }

    private function normalizeStatus(mixed $status): int
    {
        if ($status === 1 || $status === '1' || $status === true || $status === 'true' || $status === 'enabled' || $status === 'active') {
            return 1;
        }

        if ($status === 0 || $status === '0' || $status === false || $status === 'false' || $status === 'disabled' || $status === 'inactive') {
            return 0;
        }

        throw new BusinessException('闂佺粯顭堥崺鏍焵椤戣法顦﹂柍褜鍓熷Λ璺ㄧ箔婢舵劕瑙﹂柛顐ゅ枔閵嗗﹪鏌?, ErrorCode::INVALID_PARAMS);
    }

    private function assertFileSizeAllowed(string $sourcePath, string $category): void
    {
        $size = filesize($sourcePath);
        if ($size === false) {
            throw new BusinessException('闂佸搫鍟版慨鐢垫兜閸撲焦瀚氶悹鍥ㄥ絻缁插潡鏌￠崒姘煑婵炲棎鍨哄鍕槻闁活煈鍓熸俊?, ErrorCode::UPLOAD_FAILED);
        }

        $limit = (int) config('upload.limits.' . $category, 0);
        if ($limit > 0 && $size > $limit) {
            throw new BusinessException('婵炴垶鎸搁敃锝囨閸洖妫橀柛銉檮椤愯棄顭块崼鍡楀暟濮ｅ牓鎮洪幒鎴炲櫣闁搞値鍙冨浠嬪箛椤掆偓閻撴垿鏌?, ErrorCode::FILE_TOO_LARGE);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function estimateBase64DecodedSize(string $base64): int
    {
        $base64 = preg_replace('/\s+/', '', $base64);
        if (!is_string($base64)) {
            return 0;
        }

        $length = strlen($base64);
        if ($length === 0) {
            return 0;
        }

        $padding = substr_count(substr($base64, -2), '=');

        return (int) floor($length * 3 / 4) - $padding;
    }
    private function allowedExtensionsByCategory(): array
    {
        $config = config('upload.allowed_extensions', []);

        return is_array($config) ? $config : [];
    }

    /**
     * @return array<int, string>
     */
    private function blockedExtensions(): array
    {
        $config = config('upload.blocked_extensions', []);

        return is_array($config) ? array_values(array_unique(array_map(
            static fn (mixed $extension): string => strtolower(trim((string) $extension)),
            $config
        ))) : [];
    }

    private function extractExtension(string $fileName): string
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        if ($extension === '') {
            throw new BusinessException('闂佸搫鍊稿ú锝呪枎閵忥絿鐤€闁告稒鐣埀顒€绻掗弫顕€鏁傞懞銉х暢闂佸湱顣介弲娑㈡儓瀹ュ瑙︾€广儱绻掔粈澶娾槈閹惧瓨鐓ｇ紒顔奸叄瀹曟鎷呯粵瀣櫊闂佹悶鍎辨晶鑺ユ櫠閺嶎厼违濞达絿鐡斿鎺懳涢悧鍫濈仸闁?PDF闂?, ErrorCode::INVALID_PARAMS);
        }

        return $extension;
    }

    private function assertExtensionAllowed(string $fileName, string $extension): void
    {
        $parts = array_values(array_filter(explode('.', strtolower(basename($fileName))), static fn (string $part): bool => $part !== ''));
        $blocked = $this->blockedExtensions();
        foreach ($parts as $part) {
            if (in_array($part, $blocked, true)) {
                throw new BusinessException('缂備礁鍊烽悞锕傤敆濞戞瑧鈻斿┑鐘辫兌閻愬﹪鏌涘▎妯虹仭濠⒀呮櫕閹壆浠﹂幆褏浠愰梺鐓庣摠绾板秴锕㈡导鏉戞闁搞儻闄勯浠嬫煏?, ErrorCode::UNSUPPORTED_FILE_TYPE);
            }
        }

        $allowedExtensions = [];
        foreach ($this->allowedExtensionsByCategory() as $extensions) {
            if (is_array($extensions)) {
                $allowedExtensions = array_merge($allowedExtensions, $extensions);
            }
        }
        $allowedExtensions = array_values(array_unique(array_map('strtolower', $allowedExtensions)));

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new BusinessException('婵炲濮撮幊搴ㄥ储閹寸姵濯奸梻鈧幇顔炬啰婵炵鍋愭慨鏉懨瑰鈧幃褔宕堕妷銏犱壕濞达絿鐡斿鎺懳涢悧鍫濈仸闁?PDF 闂佸搫鍊稿ú锝呪枎閵忋倕违?, ErrorCode::UNSUPPORTED_FILE_TYPE);
        }
    }

    private function buildTargetFileName(string $originalFileName): string
    {
        $baseName = (string) pathinfo($originalFileName, PATHINFO_FILENAME);
        $baseName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $baseName) ?: 'asset';
        $extension = strtolower((string) pathinfo($originalFileName, PATHINFO_EXTENSION));

        return trim($baseName, '-.') . '-' . date('YmdHis') . '.' . $extension;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAssetQuery(array $query): array
    {
        $sortField = (string) ($query['sort_field'] ?? 'updated_at');
        if (!in_array($sortField, ['updated_at', 'created_at', 'file_size', 'file_name'], true)) {
            $sortField = 'updated_at';
        }

        $sortOrder = strtolower((string) ($query['sort_order'] ?? 'desc'));
        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            $sortOrder = 'desc';
        }

        $fileCategory = strtolower(trim((string) ($query['file_category'] ?? '')));
        if (!in_array($fileCategory, ['image', 'video', 'pdf'], true)) {
            $fileCategory = '';
        }

        $status = null;
        if (array_key_exists('status', $query) && $query['status'] !== '' && $query['status'] !== null) {
            $status = $this->normalizeStatus($query['status']);
        }

        return [
            'folder_name' => trim((string) ($query['folder_name'] ?? '')),
            'folder_id' => (int) ($query['folder_id'] ?? 0),
            'file_category' => $fileCategory,
            'status' => $status,
            'keyword' => trim((string) ($query['keyword'] ?? '')),
            'page' => max(1, (int) ($query['page'] ?? 1)),
            'page_size' => max(1, min(500, (int) ($query['page_size'] ?? 24))),
            'sort_field' => $sortField,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    private function filterAssets(array $items, array $query): array
    {
        return array_values(array_filter($items, function (array $item) use ($query): bool {
            if ($query['folder_id'] > 0 && (int) ($item['folder_id'] ?? 0) !== $query['folder_id']) {
                return false;
            }
            if ($query['folder_name'] !== '' && (string) ($item['folder_name'] ?? '') !== $query['folder_name']) {
                return false;
            }
            if ($query['file_category'] !== '' && $this->fileCategory($item) !== $query['file_category']) {
                return false;
            }
            if ($query['status'] !== null && (int) ($item['status'] ?? 0) !== (int) $query['status']) {
                return false;
            }
            if ($query['keyword'] !== '') {
                $haystack = mb_strtolower(implode(' ', array_map('strval', [
                    $item['file_name'] ?? '',
                    $item['alt_text_zh'] ?? '',
                    $item['description_zh'] ?? '',
                    $item['folder_name'] ?? '',
                ])));
                if (!str_contains($haystack, mb_strtolower((string) $query['keyword']))) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    private function sortAssets(array $items, array $query): array
    {
        usort($items, function (array $left, array $right) use ($query): int {
            $field = (string) $query['sort_field'];
            $leftValue = $left[$field] ?? '';
            $rightValue = $right[$field] ?? '';
            $compare = is_numeric($leftValue) && is_numeric($rightValue)
                ? ((float) $leftValue <=> (float) $rightValue)
                : strcmp((string) $leftValue, (string) $rightValue);
            if ((string) $query['sort_order'] === 'desc') {
                $compare *= -1;
            }
            if ($compare !== 0) {
                return $compare;
            }

            return ((int) ($right['id'] ?? 0)) <=> ((int) ($left['id'] ?? 0));
        });

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function paginateAssets(array $items, array $query): array
    {
        $total = count($items);
        $page = (int) $query['page'];
        $pageSize = (int) $query['page_size'];
        $offset = ($page - 1) * $pageSize;

        return [
            'items' => array_slice($items, $offset, $pageSize),
            'pagination' => [
                'page' => $page,
                'page_size' => $pageSize,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / max(1, $pageSize))),
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, string>
     */
    private function folderSummary(array $items): array
    {
        $names = [];
        foreach ($items as $item) {
            $name = trim((string) ($item['folder_name'] ?? ''));
            if ($name === '') {
                $fid = (int) ($item['folder_id'] ?? 0);
                $folder = $this->mediaRepository->findFolder($fid);
                $name = trim((string) ($folder['name'] ?? 'misc'));
            }
            $names[] = $name !== '' ? $name : 'misc';
        }

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, int>
     */
    private function folderCounts(array $items): array
    {
        $counts = [];
        foreach ($items as $item) {
            $key = trim((string) ($item['folder_name'] ?? ''));
            if ($key === '') {
                $fid = (int) ($item['folder_id'] ?? 0);
                $folder = $this->mediaRepository->findFolder($fid);
                $key = (string) ($folder['name'] ?? 'misc');
            }
            if ($key === '') {
                $key = 'misc';
            }
            $counts[$key] = (int) (($counts[$key] ?? 0) + 1);
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function fileCategory(array $item): string
    {
        $extension = strtolower((string) ($item['file_ext'] ?? ''));

        return match ($extension) {
            'jpg', 'jpeg', 'png', 'webp' => 'image',
            'mp4', 'webm' => 'video',
            'pdf' => 'pdf',
            default => 'other',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @param array<string, bool> $referenceKeys
     * @param array<string, mixed> $record
     */
    private function appendReferenceIfMatch(
        array &$items,
        array &$referenceKeys,
        int $assetId,
        array $record,
        string $fieldName,
        string $entityType,
        string $titleField
    ): void {
        if ((int) ($record[$fieldName] ?? 0) !== $assetId) {
            return;
        }

        $entityId = (int) ($record['id'] ?? 0);
        if ($entityId <= 0) {
            return;
        }

        $referenceKey = $entityType . ':' . $entityId . ':' . $fieldName;
        if (isset($referenceKeys[$referenceKey])) {
            return;
        }

        $referenceKeys[$referenceKey] = true;
        $items[] = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'title' => (string) ($record[$titleField] ?? ''),
            'field' => $fieldName,
        ];
    }

    // 闂備礁鍟块崢婊堝磻閹剧粯鐓冮柛蹇擃槸娴?Folder Service 闂備礁鍟块崢婊堝磻閹剧粯鐓冮柛蹇擃槸娴?

    public function folderTree(): array
    {
        $folders = $this->mediaRepository->listFolders();
        $counts = $this->mediaRepository->assetCountsPerFolder();
        return $this->buildFolderTree($folders, $counts, 0);
    }

    /** @param array<int, array<string, mixed>> $folders */
    private function buildFolderTree(array $folders, array $counts, int $parentId): array
    {
        $tree = [];
        foreach ($folders as $f) {
            if ((int) ($f['parent_id'] ?? 0) !== $parentId) continue;
            $id = (int) ($f['id'] ?? 0);
            $node = [
                'id' => $id,
                'parent_id' => $parentId,
                'name' => $f['name'],
                'asset_count' => $counts[$id] ?? 0,
                'children' => $this->buildFolderTree($folders, $counts, $id),
            ];
            $tree[] = $node;
        }
        return $tree;
    }

    public function createFolder(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') throw new BusinessException('闂佺儵鏅╅崰鏍礊瀹ュ瑙︾€广儱娉﹂悙瀵糕枖鐎广儱鐗嗛崢鏉戔槈閹捐顏犻柍瑙勭墵婵?, ErrorCode::INVALID_PARAMS);
        if (mb_strlen($name) > 64) throw new BusinessException('闂佺儵鏅╅崰鏍礊瀹ュ瑙︾€广儱娉﹂悙瀛樹氦闁搞儺鍓氬В鎰版煏?, ErrorCode::INVALID_PARAMS);
        return $this->mediaRepository->createFolder([
            'parent_id' => (int) ($input['parent_id'] ?? 0),
            'name' => $name,
        ]);
    }

    public function updateFolder(int $id, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') throw new BusinessException('闂佺儵鏅╅崰鏍礊瀹ュ瑙︾€广儱娉﹂悙瀵糕枖鐎广儱鐗嗛崢鏉戔槈閹捐顏犻柍瑙勭墵婵?, ErrorCode::INVALID_PARAMS);
        $folder = $this->mediaRepository->findFolder($id);
        if (!$folder) throw new BusinessException('闂佺儵鏅╅崰鏍礊瀹ュ棛鈻旂€广儱鎳愰幗鐘绘煕閿斿搫濡搁柍?, ErrorCode::NOT_FOUND);
        $parentId = max(0, (int) ($input['parent_id'] ?? ($folder['parent_id'] ?? 0)));
        if ($parentId === $id) {
            throw new BusinessException('闂佺粯鐗曟晶搴㈩殽閸ヮ剚鍎庢い鏃囧亹缁夊灝鈽夐幘宕囆ラ柛蹇旓耿閺屽懏寰勭€ｎ亶浠撮梺鐓庮殠娴滐綁妫呴埡鍛?, ErrorCode::INVALID_PARAMS);
        }
        if ($parentId > 0 && $this->mediaRepository->findFolder($parentId) === null) {
            throw new BusinessException('闂佺粯鐗曟晶搴㈩殽閸ヮ剚鍎庢い鏃囧亹缁夊灝鈽夐幘宕囆㈤柣掳鍔戝畷鐑藉Ω閿濆倸浜?, ErrorCode::NOT_FOUND);
        }

        $updated = $this->mediaRepository->updateFolder($id, [
            'name' => $name,
            'parent_id' => $parentId,
            'sort_order' => (int) ($input['sort_order'] ?? ($folder['sort_order'] ?? 0)),
        ]);
        return $updated ?? $folder;
    }

    public function deleteFolder(int $id): array
    {
        $folder = $this->mediaRepository->findFolder($id);
        if (!$folder) throw new BusinessException('闂佺儵鏅╅崰鏍礊瀹ュ棛鈻旂€广儱鎳愰幗鐘绘煕閿斿搫濡搁柍?, ErrorCode::NOT_FOUND);
        // Check it's empty (no child folders, no assets)
        $children = array_filter($this->mediaRepository->listFolders(), fn (array $f) => ((int) ($f['parent_id'] ?? 0)) === $id);
        if (!empty($children)) throw new BusinessException('闂佺儵鏅╅崰鏍礊瀹ュ棛鈻旈悗锝傛櫇閻繈鏌￠崼婵愭Ц闁烩剝鐟╅幆鍕敊閼测晝协闂佹寧绋戦張顒€螞閵堝應鏋栭柡鍥╁仜閻忊晠姊婚崟鈺佲偓鎴﹀焵?, ErrorCode::INVALID_PARAMS);
        $counts = $this->mediaRepository->assetCountsPerFolder();
        if (($counts[$id] ?? 0) > 0) throw new BusinessException('闂佺儵鏅╅崰鏍礊瀹ュ棛鈻旈悗锝傛櫇閻繈鏌?' . $counts[$id] . ' 婵炴垶鎼╂禍婵嬪几閸愨晝顩烽悹浣告贡缁€澶愭煛閸愵亜校缁绢厼鐖煎畷姘舵偐缂佹褰戦梺?, ErrorCode::INVALID_PARAMS);
        $deleted = $this->mediaRepository->deleteFolder($id);
        return $deleted ?? $folder;
    }

    public function sortFolder(int $id, int $sortOrder): ?array
    {
        $folder = $this->mediaRepository->findFolder($id);
        if (!$folder) throw new BusinessException('闂佺儵鏅╅崰鏍礊瀹ュ棛鈻旂€广儱鎳愰幗鐘绘煕閿斿搫濡搁柍?, ErrorCode::NOT_FOUND);
        return $this->mediaRepository->updateFolderSort($id, $sortOrder);
    }

    // 闂備礁鍟块崢婊堝磻閹剧粯鐓冮柛蹇擃槸娴?Batch Operations 闂備礁鍟块崢婊堝磻閹剧粯鐓冮柛蹇擃槸娴?

    /** @param int[] $ids */
    public function batchMove(array $ids, int $targetFolderId): array
    {
        if (empty($ids)) throw new BusinessException('闂佸搫鐗滄禍顏堝焵椤掆偓椤︽壆鈧哎鍔嶇粋鎺旀媼瀹曞洨协闁荤姍鍐仾缂侇煈鍣ｆ俊?, ErrorCode::INVALID_PARAMS);
        $targetFolder = $targetFolderId > 0 ? $this->mediaRepository->findFolder($targetFolderId) : null;
        if ($targetFolderId > 0 && !$targetFolder) throw new BusinessException('闂佺儵鏅╅崰妤呮偉閿濆鍎庢い鏃囧亹缁夊灝鈽夐幘宕囆㈤柣掳鍔戝畷鐑藉Ω閿濆倸浜?, ErrorCode::NOT_FOUND);

        $targetFolderName = $targetFolder['name'] ?? 'misc';
        $moved = 0;
        foreach ($ids as $id) {
            $asset = $this->mediaRepository->find((int) $id);
            if (!$asset) continue;
            $this->mediaRepository->update((int) $id, [
                'folder_id' => $targetFolderId,
                'folder_name' => $targetFolderName,
            ]);
            $moved++;
        }
        return ['moved' => $moved, 'target_folder_id' => $targetFolderId];
    }

    public function batchCopy(array $ids, int $targetFolderId): array
    {
        if (empty($ids)) throw new BusinessException('闂佸搫鐗滄禍顏堝焵椤掆偓椤︽壆鈧哎鍔嶇粋鎺旀媼瀹曞洨协闁荤姍鍐仾缂侇煈鍣ｆ俊?, ErrorCode::INVALID_PARAMS);
        $targetFolder = $targetFolderId > 0 ? $this->mediaRepository->findFolder($targetFolderId) : null;
        if ($targetFolderId > 0 && !$targetFolder) throw new BusinessException('闂佺儵鏅╅崰妤呮偉閿濆鍎庢い鏃囧亹缁夊灝鈽夐幘宕囆㈤柣掳鍔戝畷鐑藉Ω閿濆倸浜?, ErrorCode::NOT_FOUND);

        $targetFolderName = $targetFolder['name'] ?? 'misc';
        $copied = 0;
        foreach ($ids as $id) {
            $asset = $this->mediaRepository->find((int) $id);
            if (!$asset) continue;

            $uuid = bin2hex(random_bytes(16));
            $ext = $asset['file_ext'] ?? '';
            $category = $this->categoryFromExtension($ext);
            $subDir = match ($category) {
                'image' => 'images',
                'video' => 'videos',
                'pdf' => 'documents',
                default => 'files',
            };

            $newFileName = $uuid . '.' . $ext;
            $newPath = '/uploads/' . $subDir . '/' . $newFileName;
            $targetDir = base_path('public/uploads/' . $subDir);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $newFileName;

            // Copy physical file
            $sourcePath = base_path('public') . ($asset['file_path'] ?? '');
            if (is_file($sourcePath)) {
                @copy($sourcePath, $targetPath);
            }

            // Copy thumbnail for images
            $thumbUrl = null;
            if ($category === 'image' && !empty($asset['thumb_url'])) {
                $thumbSource = base_path('public') . $asset['thumb_url'];
                $thumbName = $uuid . '.' . $ext;
                $thumbDir = base_path('public/uploads/thumbs');
                $thumbTarget = $thumbDir . DIRECTORY_SEPARATOR . $thumbName;
                if (is_file($thumbSource)) {
                    @copy($thumbSource, $thumbTarget);
                    $thumbUrl = '/uploads/thumbs/' . $thumbName;
                }
            }

            $this->mediaRepository->create([
                'folder_id' => $targetFolderId,
                'folder_name' => $targetFolderName,
                'storage_disk' => $asset['storage_disk'] ?? 'local',
                'file_path' => $newPath,
                'file_name' => $newFileName,
                'original_name' => ($asset['original_name'] ?? $asset['file_name'] ?? $newFileName),
                'file_ext' => $ext,
                'mime_type' => $asset['mime_type'] ?? '',
                'file_size' => (int) ($asset['file_size'] ?? 0),
                'sha1' => $asset['sha1'] ?? null,
                'thumb_url' => $thumbUrl,
                'width' => $asset['width'] ?? null,
                'height' => $asset['height'] ?? null,
                'duration_seconds' => $asset['duration_seconds'] ?? null,
                'alt_text_zh' => $asset['alt_text_zh'] ?? '',
                'description_zh' => $asset['description_zh'] ?? '',
                'uploaded_by' => current_user()['id'] ?? null,
                'status' => (int) ($asset['status'] ?? 1),
            ]);
            $copied++;
        }
        return ['copied' => $copied, 'target_folder_id' => $targetFolderId];
    }

    /** @param int[] $ids */
    public function batchDelete(array $ids): array
    {
        if (empty($ids)) throw new BusinessException('闂佸搫鐗滄禍顏堝焵椤掆偓椤︽壆鈧哎鍔嶇粋鎺旀媼瀹曞洨协闁荤姍鍐仾缂侇煈鍣ｆ俊?, ErrorCode::INVALID_PARAMS);
        $deleted = 0;
        foreach ($ids as $id) {
            $asset = $this->mediaRepository->find((int) $id);
            if (!$asset) continue;
            // Delete physical file
            $diskPath = $this->resolveDiskPath($asset);
            if ($diskPath !== null && is_file($diskPath)) {
                @unlink($diskPath);
            }
            $this->mediaRepository->delete((int) $id);
            $deleted++;
        }
        return ['deleted' => $deleted];
    }

    /**
     * @return string|null
     * absolute disk path to the asset file
     */
    private function resolveDiskPath(array $asset): ?string
    {
        $filePath = $asset['file_path'] ?? '';
        if ($filePath === '') {
            return null;
        }

        $disk = $asset['storage_disk'] ?? 'local';
        if ($disk !== 'local') {
            return null;
        }

        $relative = ltrim((string) $filePath, '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        if (!str_starts_with($relative, 'uploads/') && !str_starts_with($relative, 'assets/')) {
            return null;
        }

        $baseDir = realpath(base_path('public'));
        if ($baseDir === false) {
            return null;
        }

        $absolute = realpath(base_path('public/' . $relative));
        if ($absolute === false) {
            return null;
        }

        if ($absolute === $baseDir || str_starts_with($absolute, $baseDir . DIRECTORY_SEPARATOR)) {
            return $absolute;
        }

        return null;
    }
}
