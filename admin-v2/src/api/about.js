import { request } from './client';

export function getAboutPages() {
  return request('/admin/about/pages');
}

export function getAboutBootstrap(params) {
  return request('/admin/about/bootstrap', {
    query: params,
  });
}

export function getAboutPageDetail(id) {
  return request(`/admin/about/pages/${id}`);
}

export function updateAboutPageBlocks(id, blocks) {
  return request(`/admin/about/pages/${id}/blocks`, {
    method: 'PUT',
    body: {
      blocks,
    },
  });
}
