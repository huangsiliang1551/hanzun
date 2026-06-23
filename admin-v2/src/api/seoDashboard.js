import { request } from '@/api/client';

export function getSeoOverview() {
  return request('/admin/seo/overview');
}

export function getSeoSiteFiles() {
  return request('/admin/seo-center/site-files');
}

export async function getSeoDashboardOverview() {
  const [overview, siteFiles] = await Promise.all([
    getSeoOverview(),
    getSeoSiteFiles(),
  ]);

  return {
    overview,
    siteFiles,
  };
}
