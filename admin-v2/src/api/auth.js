import { request } from '@/api/client';

export function login(payload) {
  return request('/admin/auth/login', {
    method: 'POST',
    body: payload,
    skipAuth: true,
  });
}

export function getProfile(options = {}) {
  return request('/admin/auth/profile', options);
}

export function logout() {
  return request('/admin/auth/logout', {
    method: 'POST',
  });
}

export function refreshSession(refreshToken) {
  return request('/admin/auth/refresh', {
    method: 'POST',
    body: {
      refresh_token: refreshToken,
    },
    skipAuth: true,
    skipAuthRefresh: true,
  });
}
