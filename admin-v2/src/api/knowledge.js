import { request } from '@/api/client';

export function getKnowledgeDocuments(params) {
  return request('/admin/knowledge/documents', {
    query: params,
  });
}

export function getKnowledgeDocumentDetail(id) {
  return request(`/admin/knowledge/documents/${id}`);
}

export function createKnowledgeDocument(payload) {
  return request('/admin/knowledge/documents', {
    method: 'POST',
    body: payload,
  });
}

export function updateKnowledgeDocument(id, payload) {
  return request(`/admin/knowledge/documents/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function deleteKnowledgeDocument(id) {
  return request(`/admin/knowledge/documents/${id}`, {
    method: 'DELETE',
  });
}

export function reindexKnowledgeDocument(id, payload = {}) {
  return request(`/admin/knowledge/documents/${id}/reindex`, {
    method: 'POST',
    body: payload,
  });
}

export function syncCmsKnowledge() {
  return request('/admin/knowledge/sync-cms', {
    method: 'POST',
  });
}

export function reindexAllKnowledge() {
  return request('/admin/knowledge/reindex-all', {
    method: 'POST',
  });
}
