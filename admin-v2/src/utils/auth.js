export const STORAGE_KEYS = {
  accessToken: 'cms_access_token',
  refreshToken: 'cms_refresh_token',
  user: 'cms_user',
  authMeta: 'cms_auth_meta',
};

function safeGet(key) {
  try {
    return window.localStorage.getItem(key);
  } catch {
    return null;
  }
}

function safeSet(key, value) {
  try {
    window.localStorage.setItem(key, value);
  } catch {}
}

function safeRemove(key) {
  try {
    window.localStorage.removeItem(key);
  } catch {}
}

export function getAccessToken() {
  return safeGet(STORAGE_KEYS.accessToken) || '';
}

export function getRefreshToken() {
  return safeGet(STORAGE_KEYS.refreshToken) || '';
}

export function getStoredUser() {
  const raw = safeGet(STORAGE_KEYS.user);
  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

export function hasSession() {
  return Boolean(getAccessToken() && getRefreshToken() && getStoredUser());
}

export function persistAuthSession(data) {
  const currentUser = data.user === undefined ? getStoredUser() : data.user;
  safeSet(STORAGE_KEYS.accessToken, data.access_token || '');
  safeSet(STORAGE_KEYS.refreshToken, data.refresh_token || '');
  safeSet(STORAGE_KEYS.user, JSON.stringify(currentUser || null));
  safeSet(
    STORAGE_KEYS.authMeta,
    JSON.stringify({
      expires_in: data.expires_in || null,
      session_code: data.session_code || null,
    }),
  );
}

export function clearAuthSession() {
  Object.values(STORAGE_KEYS).forEach(safeRemove);
}
