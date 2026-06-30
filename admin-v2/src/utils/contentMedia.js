export function normalizeCoverAssetId(value) {
  const assetId = Number(value || 0);
  return Number.isInteger(assetId) && assetId > 0 ? assetId : 0;
}

export function buildCoverMediaGallery(coverAssetId, currentGallery = []) {
  const normalizedCoverId = normalizeCoverAssetId(coverAssetId);
  const source = Array.isArray(currentGallery) ? currentGallery : [];

  if (!normalizedCoverId) {
    return [];
  }

  const preserved = source
    .filter((item) => Number(item?.asset_id || 0) > 0 && Number(item?.asset_id || 0) !== normalizedCoverId)
    .map((item, index) => ({
      asset_id: Number(item.asset_id),
      sort: Number(item.sort ?? index + 1),
      is_cover: false,
      file_path: String(item.file_path || ''),
      file_name: String(item.file_name || ''),
    }));

  return [
    {
      asset_id: normalizedCoverId,
      sort: 0,
      is_cover: true,
    },
    ...preserved,
  ];
}

export function getDetailCoverAssetId(detail) {
  const direct = normalizeCoverAssetId(detail?.cover_asset_id);
  if (direct) {
    return direct;
  }

  const gallery = Array.isArray(detail?.media_gallery) ? detail.media_gallery : [];
  const cover = gallery.find((item) => Number(item?.is_cover ? 1 : 0) === 1) || gallery[0];

  return normalizeCoverAssetId(cover?.asset_id);
}

export function getDetailCoverAssetPreview(detail) {
  if (!detail || typeof detail !== 'object') {
    return null;
  }

  const directId = normalizeCoverAssetId(detail?.cover_asset_id || detail?.cover_asset?.id);
  const directPath = String(
    detail?.cover_asset?.file_path ||
      detail?.cover_asset?.url ||
      detail?.cover_image_url ||
      '',
  ).trim();

  if (directId || directPath) {
    return {
      id: directId || undefined,
      asset_id: directId || undefined,
      file_path: directPath,
      url: directPath,
      file_name: String(
        detail?.cover_asset?.file_name ||
          detail?.cover_asset?.original_name ||
          detail?.cover_asset?.name ||
          '',
      ).trim(),
      original_name: String(
        detail?.cover_asset?.original_name ||
          detail?.cover_asset?.file_name ||
          detail?.cover_asset?.name ||
          '',
      ).trim(),
    };
  }

  const gallery = Array.isArray(detail?.media_gallery) ? detail.media_gallery : [];
  const cover = gallery.find((item) => Number(item?.is_cover ? 1 : 0) === 1) || gallery[0];
  if (!cover) {
    return null;
  }

  const assetId = normalizeCoverAssetId(cover?.asset_id);
  const filePath = String(cover?.file_path || cover?.url || '').trim();

  return {
    id: assetId || undefined,
    asset_id: assetId || undefined,
    file_path: filePath,
    url: filePath,
    file_name: String(cover?.file_name || cover?.original_name || '').trim(),
    original_name: String(cover?.original_name || cover?.file_name || '').trim(),
  };
}
