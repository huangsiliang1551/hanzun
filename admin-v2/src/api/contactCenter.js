import { request } from '@/api/client';

export function getContactCenterData() {
  return request('/admin/contact-center/items');
}

export function getContactFieldTypes() {
  return request('/admin/contact-center/field-types');
}

export function createContactFieldType(payload) {
  return request('/admin/contact-center/field-types', {
    method: 'POST',
    body: payload,
  });
}

export function updateContactFieldType(id, payload) {
  return request(`/admin/contact-center/field-types/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function deleteContactFieldType(id) {
  return request(`/admin/contact-center/field-types/${id}`, {
    method: 'DELETE',
  });
}

export function getContactItemDetail(id) {
  return request(`/admin/contact-center/items/${id}`);
}

export function createContactItem(payload) {
  return request('/admin/contact-center/items', {
    method: 'POST',
    body: payload,
  });
}

export function updateContactItem(id, payload) {
  return request(`/admin/contact-center/items/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function deleteContactItem(id) {
  return request(`/admin/contact-center/items/${id}`, {
    method: 'DELETE',
  });
}
