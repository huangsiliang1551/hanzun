import { request } from '@/api/client';

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
  });
}

export function updateTeamMember(id, payload) {
  return request(`/admin/team-members/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function deleteTeamMember(id) {
  return request(`/admin/team-members/${id}`, {
    method: 'DELETE',
  });
}

export function updateTeamMemberPublishStatus(id, publishStatus) {
  return request(`/admin/team-members/${id}/publish`, {
    method: 'PATCH',
    body: {
      publish_status: publishStatus,
    },
  });
}

export function batchUpdateTeamMemberPublishStatus(ids, publishStatus) {
  return request('/admin/team-members/batch-publish', {
    method: 'POST',
    body: {
      ids,
      publish_status: publishStatus,
    },
  });
}

export function getTeamAvatarAsset(id) {
  return request(`/admin/media/assets/${id}`);
}
