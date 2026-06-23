import { createContext, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { App as AntdApp } from 'antd';
import { getProfile, login as loginRequest, logout as logoutRequest } from '@/api/auth';
import { clearAuthSession, getStoredUser, hasSession, persistAuthSession } from '@/utils/auth';
import {
  clearAuthExpiredNoticeState,
  shouldDispatchAuthExpiredEvent,
} from '@/utils/authExpiredPolicy';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const { message } = AntdApp.useApp();
  const [user, setUser] = useState(() => getStoredUser());
  const [profile, setProfile] = useState(null);
  const [bootstrapping, setBootstrapping] = useState(() => hasSession());
  const hasHandledExpiredRef = useRef(false);

  useEffect(() => {
    hasHandledExpiredRef.current = false;
    async function bootstrap() {
      if (!hasSession()) {
        setBootstrapping(false);
        return;
      }

      try {
        const data = await getProfile({
          suppressAuthExpiredEvent: true,
        });
        setUser(data.user || getStoredUser());
        setProfile(data);
      } catch {
        hasHandledExpiredRef.current = false;
        clearAuthSession();
        setUser(null);
        setProfile(null);
        clearAuthExpiredNoticeState();
      } finally {
        setBootstrapping(false);
      }
    }

    bootstrap();
  }, []);

  useEffect(() => {
    function handleExpired() {
      if (hasHandledExpiredRef.current) {
        return;
      }

      if (!shouldDispatchAuthExpiredEvent({})) {
        return;
      }

      hasHandledExpiredRef.current = true;
      clearAuthSession();
      setUser(null);
      setProfile(null);
      message.warning('登录已过期，请重新登录。');
    }

    window.addEventListener('admin-auth-expired', handleExpired);
    return () => window.removeEventListener('admin-auth-expired', handleExpired);
  }, [message]);

  async function login(payload) {
    const data = await loginRequest(payload);
    hasHandledExpiredRef.current = false;
    clearAuthExpiredNoticeState();

    if (!data?.user) {
      clearAuthSession();
      setUser(null);
      setProfile(null);
      throw new Error('登录响应缺少用户信息，请检查账号配置。');
    }

    persistAuthSession(data);
    setUser(data.user);
    setProfile({
      user: data.user,
      roles: data.roles || [],
      permissions: data.permissions || [],
      menus: data.menus || [],
    });
    hasHandledExpiredRef.current = false;
    clearAuthExpiredNoticeState();
    return data;
  }

  async function logout() {
    try {
      if (hasSession()) {
        await logoutRequest();
      }
    } catch {
      // Keep logout local even if remote revoke fails.
    } finally {
      clearAuthSession();
      setUser(null);
      setProfile(null);
      clearAuthExpiredNoticeState();
      hasHandledExpiredRef.current = false;
    }
  }

  const value = useMemo(
    () => ({
      user,
      profile,
      bootstrapping,
      authenticated: hasSession() && Boolean(user),
      login,
      logout,
    }),
    [bootstrapping, profile, user],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider');
  }

  return context;
}

