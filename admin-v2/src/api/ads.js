import { request } from './client';

export function getAds(params) {
  return request('/admin/ads', {
    query: params,
  });
}

export function saveAds(payload) {
  return request('/admin/ads', {
    method: 'PUT',
    body: payload,
  });
}
