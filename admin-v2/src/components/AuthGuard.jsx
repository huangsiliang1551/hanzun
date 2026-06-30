import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { Result, Spin } from 'antd';
import { useAuth } from '@/providers/AuthProvider';
import {
  canAccessPathByMenus,
  filterMenuItemsByAccessAndMenus,
  flattenAccessiblePaths,
} from '@/utils/rbac';
import { menuItems } from '@/config/menu';

function isSuperAdminProfile(profile) {
  const roles = Array.isArray(profile?.roles) ? profile.roles : [];
  const username = String(profile?.user?.username || '').trim().toLowerCase();
  const isSuperAdminUser = username === 'admin';
  const directRoleMatch = roles.some(
    (role) => String(role?.code || '').trim().toLowerCase() === 'super-admin',
  );
  const hasExplicitSuperAdminFlag =
    profile?.is_super_admin === true || profile?.isSuperAdmin === true || profile?.is_super_admin_flag === true;

  return isSuperAdminUser || directRoleMatch || hasExplicitSuperAdminFlag;
}

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
    const accessibleMenuItems = isSuperAdminProfile(profile)
      ? menuItems
      : filterMenuItemsByAccessAndMenus(menuItems, profile?.permissions || [], profile?.menus || []);
    const accessiblePaths = flattenAccessiblePaths(accessibleMenuItems);
    const dashboardFirstPath = isSuperAdminProfile(profile) || canAccessPathByMenus('/dashboard', profile?.permissions || [], profile?.menus || [])
      ? '/dashboard'
      : '';
    const fallbackPath = dashboardFirstPath || accessiblePaths[0] || '/settings';
    const targetPath = location.state?.from?.pathname;
    const nextPath =
      targetPath
      && targetPath !== '/login'
      && (isSuperAdminProfile(profile) || canAccessPathByMenus(targetPath, profile?.permissions || [], profile?.menus || []))
        ? targetPath
        : fallbackPath;
    return <Navigate to={nextPath} replace />;
  }

  return children;
}

export function AuthErrorState({ title, subTitle }) {
  return <Result status="403" title={title} subTitle={subTitle} />;
}
