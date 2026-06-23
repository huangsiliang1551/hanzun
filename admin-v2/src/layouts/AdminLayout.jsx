import { LogoutOutlined, MenuOutlined, UserOutlined } from '@ant-design/icons';
import { Avatar, Button, Drawer, Dropdown, Grid, Layout, Menu, Space, Typography } from 'antd';
import { useEffect, useMemo, useState } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import { getSiteBootstrap } from '@/api/settings';
import { AuthErrorState } from '@/components/AuthGuard';
import { menuItems, menuTitleMap } from '@/config/menu';
import { useAuth } from '@/providers/AuthProvider';
import {
  getAdminBrandName,
  getAdminDisplayName,
  getAdminDisplaySecondary,
  getAdminLogoPath,
} from '@/utils/adminShell';
import { resolveAssetUrl } from '@/utils/media';
import { canAccessPath, filterMenuItemsByAccess } from '@/utils/rbac';

const { Content, Sider } = Layout;
const { Text } = Typography;

const fallbackBrandName = '涵尊后台';
const accountMenuLabel = '璐﹀彿璁剧疆';
const logoutMenuLabel = '閫€鍑虹櫥褰?';
const mobileMenuLabel = '鑿滃崟';
const noPermissionText = '褰撳墠璐﹀彿鏆傛湭寮€閫氭妯″潡璁块棶鏉冮檺銆?';

const groupedRoutePrefixes = {
  content: ['/products', '/solutions', '/news', '/cases', '/certificates', '/team', '/pages'],
  site: ['/homepage', '/contacts', '/ads', '/company', '/navigation'],
  system: ['/knowledge', '/seo-dashboard', '/seo-center', '/tasks', '/settings'],
};

function getOpenKeys(pathname) {
  return Object.entries(groupedRoutePrefixes)
    .filter(([, prefixes]) => prefixes.some((prefix) => pathname.startsWith(prefix)))
    .map(([groupKey]) => groupKey);
}

export default function AdminLayout() {
  const location = useLocation();
  const navigate = useNavigate();
  const { logout, profile } = useAuth();
  const screens = Grid.useBreakpoint();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [openKeys, setOpenKeys] = useState(getOpenKeys(location.pathname));
  const [siteConfig, setSiteConfig] = useState({});
  const isDesktop = Boolean(screens.lg);
  const brandName = getAdminBrandName(siteConfig) || fallbackBrandName;
  const brandLogoUrl = resolveAssetUrl(getAdminLogoPath(siteConfig));
  const currentTitle = menuTitleMap[location.pathname] || brandName;
  const displayName = getAdminDisplayName(profile);
  const displaySecondary = getAdminDisplaySecondary(profile);
  const accessibleMenuItems = useMemo(
    () => filterMenuItemsByAccess(menuItems, profile?.permissions || []),
    [profile?.permissions],
  );
  const canViewCurrentPath = canAccessPath(location.pathname, profile?.permissions || []);

  useEffect(() => {
    setOpenKeys(getOpenKeys(location.pathname));
  }, [location.pathname]);

  useEffect(() => {
    let active = true;

    async function loadSiteConfig() {
      try {
        const payload = await getSiteBootstrap();
        if (!active) {
          return;
        }

        setSiteConfig(payload?.config || {});
      } catch {
        if (!active) {
          return;
        }

        setSiteConfig({});
      }
    }

    loadSiteConfig();

    return () => {
      active = false;
    };
  }, []);

  const menuNode = (
    <Menu
      mode="inline"
      theme="dark"
      selectedKeys={[location.pathname]}
      openKeys={openKeys}
      onOpenChange={setOpenKeys}
      items={accessibleMenuItems}
      onClick={({ key }) => {
        if (typeof key === 'string' && key.startsWith('/')) {
          navigate(key);
          setDrawerOpen(false);
        }
      }}
      style={{ background: 'transparent', borderInlineEnd: 0 }}
    />
  );

  const brandNode = (
    <div className="brand-block">
      <div className="brand-mark">
        <img src={brandLogoUrl} alt={brandName} className="brand-logo" />
        <div className="brand-copy">
          <p className="brand-title">{brandName}</p>
        </div>
      </div>
    </div>
  );

  const accountNode = (
    <div className="sidebar-account-card">
      <Dropdown
        trigger={['click']}
        menu={{
          items: [
            {
              key: 'account',
              icon: <UserOutlined />,
              label: accountMenuLabel,
            },
            {
              key: 'logout',
              icon: <LogoutOutlined />,
              label: logoutMenuLabel,
              danger: true,
            },
          ],
          onClick: async ({ key }) => {
            if (key === 'account') {
              navigate('/settings?tab=account');
              return;
            }

            if (key === 'logout') {
              await logout();
              navigate('/login', { replace: true });
            }
          },
        }}
      >
        <Space size={12} align="center" style={{ width: '100%', cursor: 'pointer' }}>
          <Avatar
            size={42}
            icon={<UserOutlined />}
            className="sidebar-account-avatar"
            style={{ backgroundColor: '#1f6feb', flexShrink: 0 }}
          />
          <div className="sidebar-account-copy">
            <Text className="sidebar-account-name">
              {displayName}
            </Text>
            <Text className="sidebar-account-text">
              {displaySecondary}
            </Text>
          </div>
        </Space>
      </Dropdown>
    </div>
  );

  return (
    <Layout className="app-shell">
      {isDesktop ? (
        <Sider width={248} className="app-sidebar" theme="dark">
          <div className="sidebar-inner">
            {brandNode}
            <div className="sidebar-menu-shell">{menuNode}</div>
            {accountNode}
          </div>
        </Sider>
      ) : (
        <Drawer
          placement="left"
          open={drawerOpen}
          onClose={() => setDrawerOpen(false)}
          width={248}
          closable={false}
          styles={{
            body: {
              padding: 0,
              background: 'linear-gradient(180deg, #0f172a 0%, #172554 100%)',
            },
          }}
        >
          <div className="sidebar-inner">
            {brandNode}
            <div className="sidebar-menu-shell">{menuNode}</div>
            {accountNode}
          </div>
        </Drawer>
      )}

      <Layout>
        <Content className="app-content">
          {!isDesktop ? (
            <div className="app-mobile-bar">
              <Button type="default" size="large" icon={<MenuOutlined />} onClick={() => setDrawerOpen(true)}>
                {mobileMenuLabel}
              </Button>
              <Text className="app-mobile-title">{currentTitle}</Text>
            </div>
          ) : null}

          {canViewCurrentPath ? <Outlet /> : <AuthErrorState title="403" subTitle={noPermissionText} />}
        </Content>
      </Layout>
    </Layout>
  );
}
