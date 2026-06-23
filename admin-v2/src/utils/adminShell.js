const DEFAULT_ADMIN_LOGO = '/assets/images/common/logo-110.png';
const DEFAULT_ADMIN_BRAND = '涵尊后台';

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
  return String(profile?.user?.nickname || profile?.user?.username || profile?.nickname || profile?.username || '未登录');
}

export function getAdminDisplaySecondary(profile) {
  const username = String(profile?.user?.username || profile?.username || '').trim();
  if (username !== '') {
    return username;
  }

  return '当前登录账号';
}
