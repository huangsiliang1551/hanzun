function getBaseOrigin() {
  if (typeof window === 'undefined' || !window.location?.origin) {
    return '';
  }

  return window.location.origin;
}

export function buildPublicPreviewUrl(entityType, record = {}) {
  const slug = String(record.slug || record.route_slug || '').trim();
  const languageCode = String(record.language_code || record.language || 'zh').trim().toLowerCase().startsWith('en')
    ? 'en'
    : 'zh';
  const baseOrigin = getBaseOrigin();
  const langPrefix = `/${languageCode}`;

  let pathname = '/';
  switch (entityType) {
    case 'product':
      pathname = slug ? `${langPrefix}/products/${encodeURIComponent(slug)}.html` : `${langPrefix}/products.html`;
      break;
    case 'solution':
      pathname = slug ? `${langPrefix}/solutions/${encodeURIComponent(slug)}.html` : `${langPrefix}/solutions.html`;
      break;
    case 'news':
      pathname = slug ? `${langPrefix}/news/${encodeURIComponent(slug)}.html` : `${langPrefix}/news.html`;
      break;
    case 'case':
      pathname = slug ? `${langPrefix}/cases/${encodeURIComponent(slug)}.html` : `${langPrefix}/cases.html`;
      break;
    default:
      pathname = '/';
      break;
  }

  return `${baseOrigin}${pathname}`;
}

export function openPublicPreview(entityType, record) {
  const url = buildPublicPreviewUrl(entityType, record);
  window.open(url, '_blank', 'noopener,noreferrer');
}
