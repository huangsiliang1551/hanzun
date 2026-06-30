import { request } from '@/api/client';

export function getNewsList(params) {
  return request('/admin/news', {
    query: params,
  });
}

export function getNewsDetail(id) {
  return request(`/admin/news/${id}`);
}

export function getNewsBootstrap(id) {
  return request(`/admin/news/${id}/bootstrap`);
}

export function getNewsWorkflow(id) {
  return request(`/admin/news/${id}/workflow`);
}

export function createNews(payload) {
  return request('/admin/news', {
    method: 'POST',
    body: payload,
  });
}

export function updateNews(id, payload) {
  return request(`/admin/news/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function deleteNews(id) {
  return request(`/admin/news/${id}`, {
    method: 'DELETE',
  });
}

export function updateNewsPublishStatus(id, publishStatus) {
  return request(`/admin/news/${id}/publish`, {
    method: 'PATCH',
    body: {
      publish_status: publishStatus,
    },
  });
}

export function restoreNewsLive(id) {
  return request(`/admin/news/${id}/restore-live`, {
    method: 'POST',
    body: {},
  });
}

export function batchUpdateNewsPublishStatus(ids, publishStatus) {
  return request('/admin/news/batch-publish', {
    method: 'POST',
    body: {
      ids,
      publish_status: publishStatus,
    },
  });
}

export function batchDeleteNews(ids) {
  return request('/admin/news/batch-delete', {
    method: 'POST',
    body: {
      ids,
    },
  });
}

export function getNewsCategories() {
  return request('/admin/news-categories/tree');
}

export function getNewsLookups() {
  return request('/admin/news/lookups');
}
