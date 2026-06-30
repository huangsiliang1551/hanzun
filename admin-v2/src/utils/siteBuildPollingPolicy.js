export function shouldPollSiteBuildStatus(options = {}) {
  const {
    authenticated = false,
    bootstrapping = false,
    pathname = '',
    hash = '',
  } = options;

  if (bootstrapping) {
    return false;
  }

  const normalizedPathname = String(pathname || '').trim().toLowerCase();
  const normalizedHash = String(hash || '').trim().toLowerCase();
  const authBoundaryPaths = ['/login', '/reset-password', '/forgot-password'];
  const isAuthBoundaryRoute =
    authBoundaryPaths.includes(normalizedPathname) ||
    authBoundaryPaths.some((path) => normalizedHash.includes(path));

  if (isAuthBoundaryRoute) {
    return false;
  }

  return Boolean(authenticated);
}
