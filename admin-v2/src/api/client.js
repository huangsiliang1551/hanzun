import {
  clearAuthSession,
  getAccessToken,
  getRefreshToken,
  persistAuthSession,
} from '@/utils/auth';
import { shouldDispatchAuthExpiredEvent } from '@/utils/authExpiredPolicy';

const DEFAULT_TIMEOUT = 20000;
const DEFAULT_BASE_URL = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/$/, '');
let refreshPromise = null;
const TIMEOUT_MESSAGE = 'Request timed out. Please try again.';
const NETWORK_OFFLINE_MESSAGE = 'Network is offline. Please check your connection.';
const TOO_MANY_REQUESTS_MESSAGE = 'Too many requests. Please try again later.';
const SERVER_ERROR_MESSAGE = 'Server error. Please try again later.';
const SERVER_AUTH_MESSAGE = 'Authentication required. Please log in again.';

function dispatchAuthExpiredEvent() {
  if (typeof window === 'undefined' || typeof CustomEvent === 'undefined') {
    return;
  }

  window.dispatchEvent(new CustomEvent('admin-auth-expired'));
}

function joinBase(path) {
  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${DEFAULT_BASE_URL}${normalizedPath}`;
}

function buildUrl(path, query) {
  const origin = typeof window === 'undefined' ? 'http://localhost' : window.location.origin;
  const url = new URL(joinBase(path), origin);

  if (query) {
    Object.entries(query).forEach(([key, value]) => {
      if (value === undefined || value === null || value === '') {
        return;
      }

      if (Array.isArray(value)) {
        value.forEach((item) => url.searchParams.append(key, item));
        return;
      }

      url.searchParams.set(key, value);
    });
  }

  return url.toString();
}

async function parseResponse(response, responseType) {
  if (responseType === 'text') {
    return response.text();
  }

  if (response.status === 204) {
    return null;
  }

  const text = await response.text();
  if (!text) {
    return null;
  }

  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}

function createHttpError(response, payload) {
  const message =
    (payload && typeof payload === 'object' && payload.message) ||
    (typeof payload === 'string' ? payload : '') ||
    `Request failed with status ${response.status}`;

  const error = new Error(message);
  error.status = response.status;
  error.payload = payload;
  return error;
}

function normalizeApiError(error, response, payload) {
  if (error?.name === 'AbortError') {
    return new Error(TIMEOUT_MESSAGE);
  }

  if (typeof navigator !== 'undefined' && navigator.onLine === false) {
    return new Error(NETWORK_OFFLINE_MESSAGE);
  }

  if (response?.status === 401) {
    return new Error(SERVER_AUTH_MESSAGE);
  }

  if (response?.status === 429) {
    return new Error(TOO_MANY_REQUESTS_MESSAGE);
  }

  if (response?.status >= 500) {
    return new Error(SERVER_ERROR_MESSAGE);
  }

  if (payload && typeof payload === 'object' && payload.message) {
    return new Error(payload.message);
  }

  if (typeof payload === 'string' && payload.trim()) {
    return new Error(payload);
  }

  if (error instanceof Error) {
    return error;
  }

  return new Error('Request failed.');
}

async function refreshAuthSession() {
  const refreshToken = getRefreshToken();
  if (!refreshToken) {
    throw new Error('Refresh token missing');
  }

  if (!refreshPromise) {
    refreshPromise = (async () => {
      const response = await fetch(buildUrl('/admin/auth/refresh'), {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          refresh_token: refreshToken,
        }),
      });

      const payload = await parseResponse(response, 'json');
      if (!response.ok) {
        throw createHttpError(response, payload);
      }

      const data =
        payload && typeof payload === 'object' && 'code' in payload
          ? Number(payload.code) === 0
            ? payload.data || {}
            : (() => {
                const error = new Error(payload.message || 'Refresh failed');
                error.code = payload.code;
                error.payload = payload;
                throw error;
              })()
          : payload;

      persistAuthSession(data || {});
      return data;
    })().finally(() => {
      refreshPromise = null;
    });
  }

  return refreshPromise;
}

export async function request(path, options = {}) {
  const {
    method = 'GET',
    query,
    body,
    headers,
    timeout = DEFAULT_TIMEOUT,
    responseType = 'json',
    skipAuth = false,
    skipAuthRefresh = false,
    suppressAuthExpiredEvent = false,
    ...rest
  } = options;

  const abortController = typeof window === 'undefined' ? {} : new AbortController();
  const timer = typeof window === 'undefined' ? null : window.setTimeout(() => abortController?.abort(), timeout);
  const token = skipAuth ? '' : getAccessToken();
  const hasJsonBody = body !== undefined && body !== null && !(body instanceof FormData);

  try {
    const response = await fetch(buildUrl(path, query), {
      method,
      headers: {
        Accept: 'application/json',
        ...(hasJsonBody ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...headers,
      },
      body: hasJsonBody ? JSON.stringify(body) : body,
      ...(typeof window === 'undefined' ? {} : { signal: abortController.signal }),
      ...rest,
    });

    const payload = await parseResponse(response, responseType);

    if (!response.ok) {
      if (response.status === 401 && !skipAuth && !skipAuthRefresh) {
        try {
          await refreshAuthSession();
          return request(path, {
            ...options,
            skipAuthRefresh: true,
          });
        } catch {
          clearAuthSession();
          if (shouldDispatchAuthExpiredEvent({ skipAuth, suppressAuthExpiredEvent })) {
            dispatchAuthExpiredEvent();
          }
        }
      } else if (response.status === 401) {
        clearAuthSession();
        if (shouldDispatchAuthExpiredEvent({ skipAuth, suppressAuthExpiredEvent })) {
          dispatchAuthExpiredEvent();
        }
      }
      throw normalizeApiError(createHttpError(response, payload), response, payload);
    }

    if (responseType === 'text') {
      return payload;
    }

    if (payload && typeof payload === 'object' && 'code' in payload) {
      if (Number(payload.code) !== 0) {
        if (Number(payload.code) === 401 && !skipAuth && !skipAuthRefresh) {
          try {
            await refreshAuthSession();
            return request(path, {
              ...options,
              skipAuthRefresh: true,
            });
          } catch {
            clearAuthSession();
              if (shouldDispatchAuthExpiredEvent({ skipAuth, suppressAuthExpiredEvent })) {
                dispatchAuthExpiredEvent();
              }
            }
          }

          const error = new Error(payload.message || 'Request failed');
          error.code = payload.code;
          error.payload = payload;
          throw normalizeApiError(error, response, payload);
        }

      const data = payload.data !== undefined ? payload.data : payload;
      if (data && typeof data === 'object' && data.generation_job) {
        if (typeof window !== 'undefined' && typeof CustomEvent !== 'undefined') {
          window.dispatchEvent(
            new CustomEvent('site-build-job-created', {
              detail: {
                job: data.generation_job,
              },
            }),
          );
        }
      }

      return data;
    }

    return payload;
  } catch (error) {
    throw normalizeApiError(error);
  } finally {
    if (typeof window !== 'undefined' && timer) {
      window.clearTimeout(timer);
    }
  }
}

export const adminApi = {
  getHealth() {
    return request('/health');
  },
};
