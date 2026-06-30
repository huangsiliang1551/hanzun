function getBaseOrigin() {
  if (typeof window === 'undefined' || !window.location?.origin) {
    return '';
  }

  return window.location.origin;
}

function normalizePublicLanguageCode(languageCode = 'zh') {
  const normalized = String(languageCode || 'zh').trim().toLowerCase().replace(/_/g, '-');
  if (!normalized) {
    return 'zh';
  }

  if (normalized.startsWith('zh')) {
    return 'zh';
  }

  return normalized.split('-')[0] || 'zh';
}

export function getPublicSiteOrigin() {
  const apiBase = String(import.meta.env.VITE_API_BASE_URL || '').trim().replace(/\/$/, '');
  if (/^https?:\/\//i.test(apiBase)) {
    try {
      return new URL(apiBase).origin;
    } catch {
      return getBaseOrigin();
    }
  }

  return getBaseOrigin();
}

export function buildPublicHomeUrl(languageCode = 'zh') {
  const normalizedLanguage = normalizePublicLanguageCode(languageCode);
  if (normalizedLanguage === 'zh') {
    return `${getPublicSiteOrigin()}/`;
  }

  return `${getPublicSiteOrigin()}/${normalizedLanguage}/index.html`;
}

export function buildPublicPreviewUrl(entityType, record = {}) {
  const slug = String(record.slug || record.route_slug || '').trim();
  const languageCode = normalizePublicLanguageCode(record.language_code || record.language || 'zh');
  const baseOrigin = getPublicSiteOrigin();
  const langPrefix = `/${languageCode}`;

  let pathname = '/';
  switch (entityType) {
    case 'home':
      pathname = languageCode === 'zh' ? '/' : `${langPrefix}/index.html`;
      break;
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
