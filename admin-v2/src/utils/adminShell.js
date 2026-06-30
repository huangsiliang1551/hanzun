const DEFAULT_ADMIN_LOGO = '/assets/images/common/logo-110.png';
const DEFAULT_ADMIN_BRAND = '\u6db5\u5c0a\u540e\u53f0';

export function getAdminLogoPath(siteConfig) {
  const logoUrl = String(siteConfig?.logo_url || '').trim();
  return logoUrl || DEFAULT_ADMIN_LOGO;
}

export function getAdminBrandName() {
  return DEFAULT_ADMIN_BRAND;
}

export function getAdminBrandSubtitle() {
  return '';
}

export function getAdminDisplayName(profile) {
  return String(
    profile?.user?.nickname ||
      profile?.user?.username ||
      profile?.nickname ||
      profile?.username ||
      '\u672a\u767b\u5f55',
  );
}

export function getAdminDisplaySecondary(profile) {
  const username = String(profile?.user?.username || profile?.username || '').trim();
  if (username !== '') {
    return username;
  }

  return '\u5f53\u524d\u767b\u5f55\u8d26\u53f7';
}
