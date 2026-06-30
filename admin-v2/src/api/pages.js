import { request } from './client';

export function getPages(params) {
  return request('/admin/pages', {
    query: params,
  });
}

export function getPagesBootstrap(params) {
  return request('/admin/pages/bootstrap', {
    query: params,
  });
}

export function getPageDetail(id) {
  return request(`/admin/pages/${id}`);
}

export function getPageBootstrap(id) {
  return request(`/admin/pages/${id}/bootstrap`);
}

export function getPageWorkflow(id) {
  return request(`/admin/pages/${id}/workflow`);
}

export function createPage(payload) {
  return request('/admin/pages', {
    method: 'POST',
    body: payload,
  });
}

export function updatePage(id, payload) {
  return request(`/admin/pages/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function updatePagePublishStatus(id, publishStatus) {
  return request(`/admin/pages/${id}/publish`, {
    method: 'PATCH',
    body: {
      publish_status: publishStatus,
    },
  });
}

export function restorePageLive(id) {
  return request(`/admin/pages/${id}/restore-live`, {
    method: 'POST',
    body: {},
  });
}

export function deletePage(id) {
  return request(`/admin/pages/${id}`, {
    method: 'DELETE',
  });
}

export function batchUpdatePagePublishStatus(ids, publishStatus) {
  return request('/admin/pages/batch-publish', {
    method: 'POST',
    body: {
      ids,
      publish_status: publishStatus,
    },
  });
}

export function batchDeletePages(ids) {
  return request('/admin/pages/batch-delete', {
    method: 'POST',
    body: {
      ids,
    },
  });
}
