import { request } from '@/api/client';

export function getCertificates() {
  return request('/admin/certificates');
}

export function getCertificateDetail(id) {
  return request(`/admin/certificates/${id}`);
}

export function createCertificate(payload) {
  return request('/admin/certificates', {
    method: 'POST',
    body: payload,
  });
}

export function updateCertificate(id, payload) {
  return request(`/admin/certificates/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function deleteCertificate(id) {
  return request(`/admin/certificates/${id}`, {
    method: 'DELETE',
  });
}

export function updateCertificatePublishStatus(id, publishStatus) {
  return request(`/admin/certificates/${id}/publish`, {
    method: 'PATCH',
    body: {
      publish_status: publishStatus,
    },
  });
}

export function batchUpdateCertificatePublishStatus(ids, publishStatus) {
  return request('/admin/certificates/batch-publish', {
    method: 'POST',
    body: {
      ids,
      publish_status: publishStatus,
    },
  });
}

export function getCertificateImageAsset(id) {
  return request(`/admin/media/assets/${id}`);
}
