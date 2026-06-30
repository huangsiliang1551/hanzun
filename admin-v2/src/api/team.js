import { request } from '@/api/client';

const TEAM_REQUEST_TIMEOUT = 60000;

export function getTeamMembers() {
  return request('/admin/team-members');
}

export function getTeamMemberDetail(id) {
  return request(`/admin/team-members/${id}`);
}

export function createTeamMember(payload) {
  return request('/admin/team-members', {
    method: 'POST',
    body: payload,
    timeout: TEAM_REQUEST_TIMEOUT,
  });
}

export function updateTeamMember(id, payload) {
  return request(`/admin/team-members/${id}`, {
    method: 'PUT',
    body: payload,
    timeout: TEAM_REQUEST_TIMEOUT,
  });
}

export function deleteTeamMember(id) {
  return request(`/admin/team-members/${id}`, {
    method: 'DELETE',
    timeout: TEAM_REQUEST_TIMEOUT,
  });
}

export function updateTeamMemberPublishStatus(id, publishStatus) {
  return request(`/admin/team-members/${id}/publish`, {
    method: 'PATCH',
    timeout: TEAM_REQUEST_TIMEOUT,
    body: {
      publish_status: publishStatus,
    },
  });
}

export function batchUpdateTeamMemberPublishStatus(ids, publishStatus) {
  return request('/admin/team-members/batch-publish', {
    method: 'POST',
    timeout: TEAM_REQUEST_TIMEOUT,
    body: {
      ids,
      publish_status: publishStatus,
    },
  });
}

export function getTeamAvatarAsset(id) {
  return request(`/admin/media/assets/${id}`);
}
