import { request } from '@/api/client';

const AI_REQUEST_TIMEOUT = 300000;

export function aiPolishContent(payload) {
  return request('/admin/seo-center/ai-polish', {
    method: 'POST',
    body: payload,
    timeout: AI_REQUEST_TIMEOUT,
  });
}

export function aiGenerateSeo(payload) {
  return request('/admin/seo-center/ai-generate', {
    method: 'POST',
    body: payload,
    timeout: AI_REQUEST_TIMEOUT,
  });
}

export function triggerEntityTranslation(entityType, entityId) {
  return request(`/admin/translations/trigger/${entityType}/${entityId}`, {
    method: 'POST',
    body: {},
  });
}
