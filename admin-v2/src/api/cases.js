import { request } from '@/api/client';

export function getCases(params) {
  return request('/admin/cases', {
    query: params,
  });
}

export function getCaseLookups() {
  return request('/admin/cases/lookups');
}

export function getCaseDetail(id) {
  return request(`/admin/cases/${id}`);
}

export function getCaseBootstrap(id) {
  return request(`/admin/cases/${id}/bootstrap`);
}

export function getCaseWorkflow(id) {
  return request(`/admin/cases/${id}/workflow`);
}

export function createCase(payload) {
  return request('/admin/cases', {
    method: 'POST',
    body: payload,
  });
}

export function updateCase(id, payload) {
  return request(`/admin/cases/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function deleteCase(id) {
  return request(`/admin/cases/${id}`, {
    method: 'DELETE',
  });
}

export function updateCasePublishStatus(id, publishStatus) {
  return request(`/admin/cases/${id}/publish`, {
    method: 'PATCH',
    body: {
      publish_status: publishStatus,
    },
  });
}

export function restoreCaseLive(id) {
  return request(`/admin/cases/${id}/restore-live`, {
    method: 'POST',
    body: {},
  });
}

export function batchUpdateCasePublishStatus(ids, publishStatus) {
  return request('/admin/cases/batch-publish', {
    method: 'POST',
    body: {
      ids,
      publish_status: publishStatus,
    },
  });
}

export function batchDeleteCases(ids) {
  return request('/admin/cases/batch-delete', {
    method: 'POST',
    body: {
      ids,
    },
  });
}
