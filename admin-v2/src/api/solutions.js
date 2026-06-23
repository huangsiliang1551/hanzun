import { request } from '@/api/client';

export function getSolutions(params) {
  return request('/admin/solutions', {
    query: params,
  });
}

export function getSolutionDetail(id) {
  return request(`/admin/solutions/${id}`);
}

export function getSolutionBootstrap(id) {
  return request(`/admin/solutions/${id}/bootstrap`);
}

export function getSolutionWorkflow(id) {
  return request(`/admin/solutions/${id}/workflow`);
}

export function createSolution(payload) {
  return request('/admin/solutions', {
    method: 'POST',
    body: payload,
  });
}

export function updateSolution(id, payload) {
  return request(`/admin/solutions/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function deleteSolution(id) {
  return request(`/admin/solutions/${id}`, {
    method: 'DELETE',
  });
}

export function updateSolutionPublishStatus(id, publishStatus) {
  return request(`/admin/solutions/${id}/publish`, {
    method: 'PATCH',
    body: {
      publish_status: publishStatus,
    },
  });
}

export function restoreSolutionLive(id) {
  return request(`/admin/solutions/${id}/restore-live`, {
    method: 'POST',
    body: {},
  });
}

export function batchUpdateSolutionPublishStatus(ids, publishStatus) {
  return request('/admin/solutions/batch-publish', {
    method: 'POST',
    body: {
      ids,
      publish_status: publishStatus,
    },
  });
}

export function batchDeleteSolutions(ids) {
  return request('/admin/solutions/batch-delete', {
    method: 'POST',
    body: {
      ids,
    },
  });
}

export function getSolutionCategories() {
  return request('/admin/solution-categories/tree');
}

export function getSolutionLookups() {
  return request('/admin/solutions/lookups');
}
