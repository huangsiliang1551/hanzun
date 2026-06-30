const STORAGE_KEY = 'cms_auth_expired_notice_at';
const DEFAULT_THROTTLE_MS = 5000;
const DEFAULT_STORE = {
  get() {
    try {
      if (typeof window === 'undefined' || !window.sessionStorage) {
        return '';
      }

      return window.sessionStorage.getItem(STORAGE_KEY) || '';
    } catch {
      return '';
    }
  },
  set(value) {
    try {
      if (typeof window === 'undefined' || !window.sessionStorage) {
        return;
      }

      window.sessionStorage.setItem(STORAGE_KEY, String(value));
    } catch {
      // noop
    }
  },
  remove() {
    try {
      if (typeof window === 'undefined' || !window.sessionStorage) {
        return;
      }

      window.sessionStorage.removeItem(STORAGE_KEY);
    } catch {
      // noop
    }
  },
};

export function shouldDispatchAuthExpiredEvent(options = {}) {
  const { skipAuth = false, suppressAuthExpiredEvent = false, throttleMs = DEFAULT_THROTTLE_MS } = options;
  if (skipAuth || suppressAuthExpiredEvent) {
    return false;
  }

  const now = Date.now();
  const stored = Number(DEFAULT_STORE.get() || 0);
  if (!Number.isFinite(stored) || now - stored < throttleMs) {
    return false;
  }

  DEFAULT_STORE.set(now);
  return true;
}

export function clearAuthExpiredNoticeState() {
  DEFAULT_STORE.remove();
}
