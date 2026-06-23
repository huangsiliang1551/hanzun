import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { Result, Spin } from 'antd';
import { useAuth } from '@/providers/AuthProvider';
import { canAccessPath, filterMenuItemsByAccess, flattenAccessiblePaths } from '@/utils/rbac';
import { menuItems } from '@/config/menu';

export default function AuthGuard() {
  const { authenticated, bootstrapping } = useAuth();
  const location = useLocation();

  if (bootstrapping) {
    return (
      <div className="auth-loading-shell">
        <Spin size="large" />
      </div>
    );
  }

  if (!authenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  return <Outlet />;
}

export function LoginRedirectGuard({ children }) {
  const { authenticated, bootstrapping, profile } = useAuth();
  const location = useLocation();

  if (bootstrapping) {
    return (
      <div className="auth-loading-shell">
        <Spin size="large" />
      </div>
    );
  }

  if (authenticated) {
    const accessibleMenuItems = filterMenuItemsByAccess(menuItems, profile?.permissions || []);
    const fallbackPath = flattenAccessiblePaths(accessibleMenuItems)[0] || '/settings';
    const targetPath = location.state?.from?.pathname;
    const nextPath =
      targetPath && targetPath !== '/login' && canAccessPath(targetPath, profile?.permissions || [])
        ? targetPath
        : fallbackPath;
    return <Navigate to={nextPath} replace />;
  }

  return children;
}

export function AuthErrorState({ title, subTitle }) {
  return <Result status="403" title={title} subTitle={subTitle} />;
}
