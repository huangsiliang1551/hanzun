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
            throw new BusinessException('Unsupported file type.', ErrorCode::UNSUPPORTED_FILE_TYPE);
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
            throw new BusinessException('Asset not found.', ErrorCode::NOT_FOUND);
        }

        return $record;
    }

    public function create(array $input): array
    {
        [$sourcePath, $originalFileName, $isTemporaryFile] = $this->resolveSourceFile($input);
        $folderName = $this->normalizeFolderName((string) ($input['folder_name'] ?? 'misc'));
        $altText = $this->normalizeText((string) ($input['alt_text_zh'] ?? ''), 120, 'Alt text');
        $description = $this->normalizeText((string) ($input['description_zh'] ?? ''), 500, 'Description');
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
            throw new BusinessException('Uploaded file is invalid.', ErrorCode::INVALID_PARAMS);
        }

        $folderId = (int) ($options['folder_id'] ?? 0);
        $folderName = $this->resolveFolderNameForWrite(
            $folderId,
            (string) ($options['folder_name'] ?? 'misc')
        );
        $altText = $this->normalizeText((string) ($options['alt_text_zh'] ?? ''), 120, 'Alt text');
        $description = $this->normalizeText((string) ($options['description_zh'] ?? ''), 500, 'Description');

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
            throw new BusinessException('Failed to create upload directory.', ErrorCode::UPLOAD_FAILED);
        }

        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $targetFileName;
        if (!move_uploaded_file($tmpPath, $targetPath)) {
            throw new BusinessException('Failed to move uploaded file.', ErrorCode::UPLOAD_FAILED);
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
            throw new BusinessException('Failed to create upload directory.', ErrorCode::UPLOAD_FAILED);
        }

        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . $targetFileName;
        if (!copy($sourcePath, $targetPath)) {
            throw new BusinessException('Failed to move uploaded file.', ErrorCode::UPLOAD_FAILED);
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
            'alt_text_zh' => $this->normalizeText((string) ($input['alt_text_zh'] ?? ($existing['alt_text_zh'] ?? '')), 120, 'Alt text'),
            'description_zh' => $this->normalizeText((string) ($input['description_zh'] ?? ($existing['description_zh'] ?? '')), 500, 'Description'),
            'status' => array_key_exists('status', $input) ? $this->normalizeStatus($input['status']) : (int) ($existing['status'] ?? 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ]));

        if ($updated === null) {
            throw new BusinessException('Asset not found.', ErrorCode::NOT_FOUND);
        }

        $this->operationLogService->recordCurrentAction('media', 'media.update', 'media_asset', $updated, 'media updated');

        return $updated;
    }

    public function updateStatus(int $id, int $status): array
    {
        $updated = $this->mediaRepository->updateStatus($id, $this->normalizeStatus($status));
        if ($updated === null) {
            throw new BusinessException('Asset not found.', ErrorCode::NOT_FOUND);
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
            throw new BusinessException('Asset not found.', ErrorCode::NOT_FOUND);
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
        if ($newFileName === '') throw new BusinessException('File name is required.', ErrorCode::INVALID_PARAMS);
        if (mb_strlen($newFileName) > 255) throw new BusinessException('File name is too long.', ErrorCode::INVALID_PARAMS);

        $asset = $this->mediaRepository->find($id);
        if (!$asset) throw new BusinessException('Asset not found.', ErrorCode::NOT_FOUND);

        $ext = pathinfo($newFileName, PATHINFO_EXTENSION);
        $oldExt = $asset['file_ext'] ?? '';
        if ($ext !== '' && strtolower($ext) !== strtolower($oldExt)) {
            throw new BusinessException('File extension cannot be changed.', ErrorCode::INVALID_PARAMS);
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

        throw new BusinessException('Unsupported file type.', ErrorCode::UNSUPPORTED_FILE_TYPE);
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
            throw new BusinessException('source_path is required.', ErrorCode::INVALID_PARAMS);
        }

        if (str_starts_with($sourcePath, 'http://') || str_starts_with($sourcePath, 'https://')) {
            throw new BusinessException('source_path must not be a URL.', ErrorCode::INVALID_PARAMS);
        }

        if (str_contains($sourcePath, '..')) {
            throw new BusinessException('source_path must not contain path traversal.', ErrorCode::INVALID_PARAMS);
        }

        $realPath = realpath($sourcePath);
        if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
            throw new BusinessException('source_path file is invalid or unreadable.', ErrorCode::INVALID_PARAMS);
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

        throw new BusinessException('source_path is outside allowed import directories.', ErrorCode::INVALID_PARAMS);
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
            throw new BusinessException('file_name is required.', ErrorCode::INVALID_PARAMS);
        }

        $fileName = basename($fileName);
        if (preg_match('/^[A-Za-z0-9._-]{1,120}$/', $fileName) !== 1) {
            throw new BusinessException('file_name contains invalid characters.', ErrorCode::INVALID_PARAMS);
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
            throw new BusinessException('file_content_base64 is required.', ErrorCode::INVALID_PARAMS);
        }
        $category = $this->categoryFromFileName($fileName);
        $estimatedSize = $this->estimateBase64DecodedSize($payload);
        $maxAllowed = (int) config('upload.limits.' . $category, 0);
        if ($estimatedSize > 0 && $maxAllowed > 0 && $estimatedSize > $maxAllowed) {
            throw new BusinessException('Decoded file exceeds upload size limit.', ErrorCode::FILE_TOO_LARGE);
        }

        $binaryContent = base64_decode($payload, true);
        if ($binaryContent === false || $binaryContent === '') {
            throw new BusinessException('file_content_base64 is not valid base64 data.', ErrorCode::INVALID_PARAMS);
        }

        $extension = $this->extractExtension($fileName);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'cms-media-');
        if ($temporaryPath === false) {
            throw new BusinessException('Failed to create temporary file.', ErrorCode::UPLOAD_FAILED);
        }

        $targetTemporaryPath = $temporaryPath . '.' . $extension;
        if (!@rename($temporaryPath, $targetTemporaryPath)) {
            @unlink($temporaryPath);
            throw new BusinessException('Failed to prepare temporary file path.', ErrorCode::UPLOAD_FAILED);
        }

        if (file_put_contents($targetTemporaryPath, $binaryContent, LOCK_EX) === false) {
            @unlink($targetTemporaryPath);
            throw new BusinessException('Failed to write temporary file.', ErrorCode::UPLOAD_FAILED);
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
            throw new BusinessException('Folder name is invalid.', ErrorCode::INVALID_PARAMS);
        }

        return trim($folderName, " /");
    }

    private function normalizeText(string $value, int $maxLength, string $fieldLabel): string
    {
        $value = trim($value);
        if (mb_strlen($value) > $maxLength) {
            throw new BusinessException($fieldLabel . ' is too long.', ErrorCode::INVALID_PARAMS);
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

        throw new BusinessException('Invalid status value.', ErrorCode::INVALID_PARAMS);
    }

    private function assertFileSizeAllowed(string $sourcePath, string $category): void
    {
        $size = filesize($sourcePath);
        if ($size === false) {
            throw new BusinessException('Unable to read file size.', ErrorCode::UPLOAD_FAILED);
        }

        $limit = (int) config('upload.limits.' . $category, 0);
        if ($limit > 0 && $size > $limit) {
            throw new BusinessException('File exceeds upload size limit.', ErrorCode::FILE_TOO_LARGE);
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
            throw new BusinessException('File extension is required.', ErrorCode::INVALID_PARAMS);
        }

        return $extension;
    }

    private function assertExtensionAllowed(string $fileName, string $extension): void
    {
        $parts = array_values(array_filter(explode('.', strtolower(basename($fileName))), static fn (string $part): bool => $part !== ''));
        $blocked = $this->blockedExtensions();
        foreach ($parts as $part) {
            if (in_array($part, $blocked, true)) {
                throw new BusinessException('Blocked file extension is not allowed.', ErrorCode::UNSUPPORTED_FILE_TYPE);
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
            throw new BusinessException('Unsupported file type.', ErrorCode::UNSUPPORTED_FILE_TYPE);
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

    // 闂傚倸鍊搁崐椋庣矆娓氣偓瀹曨垶宕稿Δ鈧崒銊︾節婵犲倻澧曠痪鎯ь煼閺岀喖宕滆鐢盯鏌ｉ幘鍐叉殻闁哄本绋栫粻娑㈠箼閸愨敩锔界箾?Folder Service 闂傚倸鍊搁崐椋庣矆娓氣偓瀹曨垶宕稿Δ鈧崒銊︾節婵犲倻澧曠痪鎯ь煼閺岀喖宕滆鐢盯鏌ｉ幘鍐叉殻闁哄本绋栫粻娑㈠箼閸愨敩锔界箾?

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
        if ($name === '') throw new BusinessException('Folder name is required.', ErrorCode::INVALID_PARAMS);
        if (mb_strlen($name) > 64) throw new BusinessException('Folder name is too long.', ErrorCode::INVALID_PARAMS);
        return $this->mediaRepository->createFolder([
            'parent_id' => (int) ($input['parent_id'] ?? 0),
            'name' => $name,
        ]);
    }

    public function updateFolder(int $id, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') throw new BusinessException('Folder name is required.', ErrorCode::INVALID_PARAMS);
        $folder = $this->mediaRepository->findFolder($id);
        if (!$folder) throw new BusinessException('Folder not found.', ErrorCode::NOT_FOUND);
        $parentId = max(0, (int) ($input['parent_id'] ?? ($folder['parent_id'] ?? 0)));
        if ($parentId === $id) {
            throw new BusinessException('Folder cannot be its own parent.', ErrorCode::INVALID_PARAMS);
        }
        if ($parentId > 0 && $this->mediaRepository->findFolder($parentId) === null) {
            throw new BusinessException('Parent folder not found.', ErrorCode::NOT_FOUND);
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
        if (!$folder) throw new BusinessException('Folder not found.', ErrorCode::NOT_FOUND);
        // Check it's empty (no child folders, no assets)
        $children = array_filter($this->mediaRepository->listFolders(), fn (array $f) => ((int) ($f['parent_id'] ?? 0)) === $id);
        if (!empty($children)) throw new BusinessException('Folder is not empty.', ErrorCode::INVALID_PARAMS);
        $counts = $this->mediaRepository->assetCountsPerFolder();
        if (($counts[$id] ?? 0) > 0) throw new BusinessException('Folder still contains assets.', ErrorCode::INVALID_PARAMS);
        $deleted = $this->mediaRepository->deleteFolder($id);
        return $deleted ?? $folder;
    }

    public function sortFolder(int $id, int $sortOrder): ?array
    {
        $folder = $this->mediaRepository->findFolder($id);
        if (!$folder) throw new BusinessException('Folder not found.', ErrorCode::NOT_FOUND);
        return $this->mediaRepository->updateFolderSort($id, $sortOrder);
    }

    // 闂傚倸鍊搁崐椋庣矆娓氣偓瀹曨垶宕稿Δ鈧崒銊︾節婵犲倻澧曠痪鎯ь煼閺岀喖宕滆鐢盯鏌ｉ幘鍐叉殻闁哄本绋栫粻娑㈠箼閸愨敩锔界箾?Batch Operations 闂傚倸鍊搁崐椋庣矆娓氣偓瀹曨垶宕稿Δ鈧崒銊︾節婵犲倻澧曠痪鎯ь煼閺岀喖宕滆鐢盯鏌ｉ幘鍐叉殻闁哄本绋栫粻娑㈠箼閸愨敩锔界箾?

    /** @param int[] $ids */
    public function batchMove(array $ids, int $targetFolderId): array
    {
        if (empty($ids)) throw new BusinessException('ids must not be empty.', ErrorCode::INVALID_PARAMS);
        $targetFolder = $targetFolderId > 0 ? $this->mediaRepository->findFolder($targetFolderId) : null;
        if ($targetFolderId > 0 && !$targetFolder) throw new BusinessException('Target folder not found.', ErrorCode::NOT_FOUND);

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
        if (empty($ids)) throw new BusinessException('ids must not be empty.', ErrorCode::INVALID_PARAMS);
        $targetFolder = $targetFolderId > 0 ? $this->mediaRepository->findFolder($targetFolderId) : null;
        if ($targetFolderId > 0 && !$targetFolder) throw new BusinessException('Target folder not found.', ErrorCode::NOT_FOUND);

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
        if (empty($ids)) throw new BusinessException('ids must not be empty.', ErrorCode::INVALID_PARAMS);
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
