import { request } from '@/api/client';

export function getSiteSettings() {
  return request('/admin/settings/site');
}

export function getSiteBootstrap() {
  return request('/admin/settings/site/bootstrap');
}

export function getSitePhrases(query) {
  return request('/admin/settings/site-phrases', {
    query,
  });
}

export function updateSiteSettings(payload) {
  return request('/admin/settings/site', {
    method: 'PUT',
    body: payload,
  });
}

export function updateSitePhrases(payload) {
  return request('/admin/settings/site-phrases', {
    method: 'PUT',
    body: payload,
  });
}
