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
