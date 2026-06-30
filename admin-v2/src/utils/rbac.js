const routePermissionMap = {
  '/dashboard': ['dashboard.view'],
  '/inquiries': ['inquiry.view'],
  '/products': ['product.view'],
  '/solutions': ['solution.view'],
  '/news': ['news.view', 'article.view'],
  '/cases': ['case.view', 'article.view'],
  '/categories': ['product.view', 'solution.view', 'news.view', 'case.view', 'article.view'],
  '/certificates': ['certificate.view'],
  '/team': ['team.view'],
  '/pages': ['page.view'],
  '/homepage': ['homepage.view'],
  '/contacts': ['contact.view'],
  '/ads': ['system.site.view'],
  '/company': ['about.view'],
  '/resources': ['media.view'],
  '/knowledge': ['system.deepseek.view'],
  '/seo-dashboard': ['seo.view'],
  '/seo-center': ['seo.view'],
  '/tasks': ['translation.view', 'seo.view'],
};

const permissionAliasMap = {
  'news.view': ['article.view'],
  'case.view': ['article.view'],
  'news.create': ['article.create'],
  'case.create': ['article.create'],
  'news.update': ['article.update'],
  'case.update': ['article.update'],
  'news.publish': ['article.publish'],
  'case.publish': ['article.publish'],
};

function normalizePermissionInput(input) {
  if (typeof input !== 'string') {
    return '';
  }

  return input.trim();
}

function flattenMenuTreeForRoutes(items = []) {
  return items.reduce((accumulator, item) => {
    if (!item || typeof item !== 'object') {
      return accumulator;
    }

    const children = Array.isArray(item.children) ? item.children : [];
    const route = normalizePermissionInput(item.path || item.route_path || item.key || '');
    if (route) {
      accumulator.push(route);
    }

    const childRoutes = flattenMenuTreeForRoutes(children);
    if (childRoutes.length > 0) {
      accumulator.push(...childRoutes);
    }

    return accumulator;
  }, []);
}

export function hasPermission(permissions = [], requiredPermissions = []) {
  if (!Array.isArray(requiredPermissions) || requiredPermissions.length === 0) {
    return true;
  }

  const permissionSet = new Set(
    Array.isArray(permissions) ? permissions.map((permission) => normalizePermissionInput(permission)).filter(Boolean) : [],
  );
  return requiredPermissions.some((permission) => {
    if (permissionSet.has(permission)) {
      return true;
    }

    const aliases = permissionAliasMap[permission] || [];
    return aliases.some((alias) => permissionSet.has(alias));
  });
}

export function canAccessPath(pathname, permissions = []) {
  if (!pathname || pathname === '/' || pathname === '/login' || pathname === '/settings') {
    return true;
  }

  const normalizedPath = Object.keys(routePermissionMap).find(
    (routePath) => pathname === routePath || pathname.startsWith(`${routePath}/`),
  );

  if (!normalizedPath) {
    return false;
  }

  return hasPermission(permissions, routePermissionMap[normalizedPath]);
}

export function hasMenuPathForPath(pathname, menus = []) {
  const normalizedPath = normalizePermissionInput(pathname);
  if (!normalizedPath) {
    return false;
  }

  const menuPaths = flattenMenuTreeForRoutes(menus);
  if (menuPaths.length === 0) {
    return false;
  }

  return menuPaths.some((menuPath) => {
    const normalizedMenuPath = normalizePermissionInput(menuPath);
    if (!normalizedMenuPath) {
      return false;
    }

    return normalizedPath === normalizedMenuPath || normalizedPath.startsWith(`${normalizedMenuPath}/`);
  });
}

export function canAccessPathByMenus(pathname, permissions = [], menus = []) {
  if (Array.isArray(permissions) && permissions.length > 0 && canAccessPath(pathname, permissions)) {
    return true;
  }

  if (hasMenuPathForPath(pathname, menus)) {
    return true;
  }

  return false;
}

export function filterMenuItemsByAccess(items = [], permissions = []) {
  return items.reduce((accumulator, item) => {
    if (Array.isArray(item.children) && item.children.length > 0) {
      const nextChildren = filterMenuItemsByAccess(item.children, permissions);
      if (nextChildren.length > 0) {
        accumulator.push({
          ...item,
          children: nextChildren,
        });
      }
      return accumulator;
    }

    if (typeof item.key === 'string' && item.key.startsWith('/')) {
      if (canAccessPath(item.key, permissions)) {
        accumulator.push(item);
      }
      return accumulator;
    }

    accumulator.push(item);
    return accumulator;
  }, []);
}

export function filterMenuItemsByAccessAndMenus(items = [], permissions = [], menus = []) {
  return items.reduce((accumulator, item) => {
    if (!item || typeof item !== 'object') {
      return accumulator;
    }

    if (Array.isArray(item.children) && item.children.length > 0) {
      const nextChildren = filterMenuItemsByAccessAndMenus(item.children, permissions, menus);
      if (nextChildren.length > 0) {
        accumulator.push({
          ...item,
          children: nextChildren,
        });
      }
      return accumulator;
    }

    if (typeof item.key === 'string' && item.key.startsWith('/')) {
      if (canAccessPathByMenus(item.key, permissions, menus)) {
        accumulator.push(item);
      }
      return accumulator;
    }

    accumulator.push(item);
    return accumulator;
  }, []);
}

export function flattenAccessiblePaths(items = []) {
  return items.flatMap((item) => {
    if (Array.isArray(item.children) && item.children.length > 0) {
      return flattenAccessiblePaths(item.children);
    }

    return typeof item.key === 'string' && item.key.startsWith('/') ? [item.key] : [];
  });
}
