export function getResourcePreviewPath(asset) {
  return String(asset?.thumbnail_url || asset?.thumb_url || asset?.file_path || '').trim();
}

export function getResourcePosterPath(asset) {
  return String(asset?.thumbnail_url || asset?.thumb_url || '').trim();
}
