import { request } from '@/api/client';

const AI_REQUEST_TIMEOUT = 300000;

function normalizeArray(value) {
  return Array.isArray(value) ? value : [];
}

export function getAccountSettings() {
  return request('/admin/settings/account');
}

export function getAccountBootstrap() {
  return request('/admin/settings/account/bootstrap');
}

export function updateAccountSettings(payload) {
  return request('/admin/settings/account', {
    method: 'PUT',
    body: payload,
  });
}

export function getAdminUsers() {
  return request('/admin/settings/admin-users');
}

export function getAdminUsersBootstrap() {
  return request('/admin/settings/admin-users/bootstrap');
}

export function getAdminUserDetail(id) {
  return request(`/admin/settings/admin-users/${id}`);
}

export function createAdminUser(payload) {
  return request('/admin/settings/admin-users', {
    method: 'POST',
    body: payload,
  });
}

export function updateAdminUser(id, payload) {
  return request(`/admin/settings/admin-users/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function deleteAdminUser(id) {
  return request(`/admin/settings/admin-users/${id}`, {
    method: 'DELETE',
  });
}

export function getRoles() {
  return request('/admin/settings/roles');
}

export function getRolesBootstrap() {
  return request('/admin/settings/roles/bootstrap');
}

export function getRoleDetail(id) {
  return request(`/admin/settings/roles/${id}`);
}

export function createRole(payload) {
  return request('/admin/settings/roles', {
    method: 'POST',
    body: payload,
  });
}

export function updateRole(id, payload) {
  return request(`/admin/settings/roles/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function deleteRole(id) {
  return request(`/admin/settings/roles/${id}`, {
    method: 'DELETE',
  });
}

export function updateRolePermissions(id, payload) {
  return request(`/admin/settings/roles/${id}/permissions`, {
    method: 'PUT',
    body: payload,
  });
}

export function getMenuOptions() {
  return request('/admin/settings/menus');
}

export function getActionPointOptions() {
  return request('/admin/settings/action-points');
}

export function getLanguageSettings() {
  return request('/admin/settings/languages');
}

export function updateLanguageSettings(payload) {
  return request('/admin/settings/languages', {
    method: 'PUT',
    body: payload,
  });
}

export function getAISettings() {
  return request('/admin/settings/deepseek');
}

export function getAIBootstrap() {
  return request('/admin/settings/deepseek/bootstrap');
}

export function updateAISettings(payload) {
  return request('/admin/settings/deepseek', {
    method: 'PUT',
    body: payload,
  });
}

export function testAISettingsConnection(payload = {}) {
  return request('/admin/settings/deepseek/test', {
    method: 'POST',
    body: payload,
    timeout: AI_REQUEST_TIMEOUT,
  });
}

export function getAIModels() {
  return request('/admin/settings/deepseek/models', {
    timeout: AI_REQUEST_TIMEOUT,
  });
}

export function getAIBalance() {
  return request('/admin/settings/deepseek/balance', {
    timeout: AI_REQUEST_TIMEOUT,
  });
}

export function getAILogs() {
  return request('/admin/settings/deepseek-logs');
}

export function getLogsBootstrap(query) {
  return request('/admin/settings/logs/bootstrap', {
    query,
  });
}

export function getOperationLogs(query) {
  return request('/admin/settings/operation-logs', {
    query,
  });
}

export function getLoginLogs(query) {
  return request('/admin/settings/login-logs', {
    query,
  });
}

export function normalizeUserItems(payload) {
  return normalizeArray(payload?.items);
}

export function normalizeRoleItems(payload) {
  return normalizeArray(payload?.items);
}

export function normalizeMenuItems(payload) {
  return normalizeArray(payload?.items);
}

export function normalizeActionPointItems(payload) {
  return normalizeArray(payload?.items);
}

export function normalizeLanguagePayload(payload) {
  return {
    items: normalizeArray(payload?.items),
    summary: payload?.summary || {},
    management_mode: payload?.management_mode || '',
    fallback_chain: normalizeArray(payload?.fallback_chain),
  };
}

export function normalizeAISettings(payload) {
  return payload?.config || {};
}

export function normalizeAILogs(payload) {
  return {
    items: normalizeArray(payload?.items),
    summary: payload?.summary || {},
  };
}

export function normalizePaginatedLogs(payload) {
  return {
    items: normalizeArray(payload?.items),
    total: Number(payload?.total || 0),
    page: Number(payload?.page || 1),
    page_size: Number(payload?.page_size || 20),
    total_pages: Number(payload?.total_pages || 1),
  };
}
