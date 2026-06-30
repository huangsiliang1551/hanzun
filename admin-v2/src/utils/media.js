const IMAGE_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'webp', 'gif']);
const VIDEO_EXTENSIONS = new Set(['mp4', 'webm', 'mov', 'm4v']);
const PDF_EXTENSIONS = new Set(['pdf']);

export function resolveAssetUrl(path) {
  if (!path) {
    return '';
  }

  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  const base = import.meta.env.VITE_API_BASE_URL || window.location.origin;
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return new URL(normalizedPath, base).toString();
}

export function getAssetExtension(asset) {
  return String(asset?.file_ext || '')
    .trim()
    .toLowerCase();
}

export function isImageAsset(asset) {
  return IMAGE_EXTENSIONS.has(getAssetExtension(asset));
}

export function isVideoAsset(asset) {
  return VIDEO_EXTENSIONS.has(getAssetExtension(asset));
}

export function isPdfAsset(asset) {
  return PDF_EXTENSIONS.has(getAssetExtension(asset));
}

export function getAssetCategory(asset) {
  if (isImageAsset(asset)) {
    return 'image';
  }

  if (isVideoAsset(asset)) {
    return 'video';
  }

  if (isPdfAsset(asset)) {
    return 'pdf';
  }

  return 'file';
}

export function matchesAssetType(asset, assetType) {
  if (!assetType || assetType === 'all') {
    return true;
  }

  return getAssetCategory(asset) === assetType;
}

export function getAssetDisplayName(asset) {
  return asset?.file_name || asset?.original_name || `Asset #${asset?.id || 0}`;
}
