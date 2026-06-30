import { request } from '@/api/client';

export function createSiteBuildJob(payload) {
  return request('/admin/site-build/jobs', {
    method: 'POST',
    body: payload,
  });
}

export function getSiteBuildJobs() {
  return request('/admin/site-build/jobs');
}

export function getCurrentSiteBuildJob() {
  return request('/admin/site-build/current');
}

export function getSiteBuildJobDetail(id) {
  return request(`/admin/site-build/jobs/${id}`);
}

export function retrySiteBuildJob(id) {
  return request(`/admin/site-build/jobs/${id}/retry`, {
    method: 'POST',
    body: {},
  });
}
