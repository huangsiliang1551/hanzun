import { request } from './client';

export function getNavigationMenus() {
  return request('/admin/navigation/menus');
}

export function getNavigationBootstrap(params) {
  return request('/admin/navigation/bootstrap', {
    query: params,
  });
}

export function getNavigationLookups() {
  return request('/admin/navigation/lookups');
}

export function getNavigationMenuDetail(id) {
  return request(`/admin/navigation/menus/${id}`);
}

export function createNavigationMenu(payload) {
  return request('/admin/navigation/menus', {
    method: 'POST',
    body: payload,
  });
}

export function updateNavigationMenu(id, payload) {
  return request(`/admin/navigation/menus/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function updateNavigationMenuItems(id, items) {
  return request(`/admin/navigation/menus/${id}/items`, {
    method: 'PUT',
    body: {
      items,
    },
  });
}

export function deleteNavigationMenu(id) {
  return request(`/admin/navigation/menus/${id}`, {
    method: 'DELETE',
  });
}
