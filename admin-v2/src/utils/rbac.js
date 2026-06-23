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
  '/navigation': ['navigation.view'],
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

export function hasPermission(permissions = [], requiredPermissions = []) {
  if (!Array.isArray(requiredPermissions) || requiredPermissions.length === 0) {
    return true;
  }

  const permissionSet = new Set(Array.isArray(permissions) ? permissions : []);
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

export function flattenAccessiblePaths(items = []) {
  return items.flatMap((item) => {
    if (Array.isArray(item.children) && item.children.length > 0) {
      return flattenAccessiblePaths(item.children);
    }

    return typeof item.key === 'string' && item.key.startsWith('/') ? [item.key] : [];
  });
}
