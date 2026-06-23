import { request } from '@/api/client';

export function getInquiries(params) {
  return request('/admin/inquiries', {
    query: params,
  });
}

export function getInquiryWorkbench(params) {
  return request('/admin/inquiry-workbench', {
    query: params,
  });
}

export function getInquiryLookups() {
  return request('/admin/inquiries/lookups');
}

export function getInquiryDetail(id) {
  return request(`/admin/inquiries/${id}`);
}

export function getInquiryWorkbenchDetail(type, id) {
  return request(`/admin/inquiry-workbench/${type}/${id}`);
}

export function updateInquiry(id, payload) {
  return request(`/admin/inquiries/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function updateInquiryStatus(id, status) {
  return request(`/admin/inquiries/${id}/status`, {
    method: 'PATCH',
    body: {
      status,
    },
  });
}

export function addInquiryFollowUp(id, content) {
  return request(`/admin/inquiries/${id}/follow-ups`, {
    method: 'POST',
    body: {
      content,
    },
  });
}

export function updateInquiryArchiveStatus(id, archiveStatus) {
  return request(`/admin/inquiry-workbench/inquiry/${id}/archive-status`, {
    method: 'PATCH',
    body: {
      archive_status: archiveStatus,
    },
  });
}

export function updateWorkbenchArchiveStatus(type, id, archiveStatus) {
  return request(`/admin/inquiry-workbench/${type}/${id}/archive-status`, {
    method: 'PATCH',
    body: {
      archive_status: archiveStatus,
    },
  });
}

export function batchUpdateInquiryStatus(ids, status) {
  return request('/admin/inquiries/batch/status', {
    method: 'POST',
    body: {
      ids,
      status,
    },
  });
}

export function batchUpdateWorkbenchArchiveStatus(type, ids, archiveStatus) {
  return request('/admin/inquiry-workbench/batch/archive-status', {
    method: 'POST',
    body: {
      record_type: type,
      ids,
      archive_status: archiveStatus,
    },
  });
}

export function convertAiConversation(id, payload) {
  return request(`/admin/ai-conversations/${id}/convert`, {
    method: 'POST',
    body: payload,
  });
}

