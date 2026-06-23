import assert from 'node:assert/strict';
import {
  getAdminBrandName,
  getAdminBrandSubtitle,
  getAdminDisplayName,
  getAdminDisplaySecondary,
  getAdminLogoPath,
} from '../src/utils/adminShell.js';

const customSiteConfig = {
  logo_url: '/uploads/site/custom-logo.png',
  company_name: 'Custom Factory',
  company_subtitle: 'Smart Bakery Lines',
};

assert.equal(
  getAdminLogoPath(customSiteConfig),
  '/uploads/site/custom-logo.png',
  'admin shell logo should prefer site config logo_url'
);
assert.equal(
  getAdminBrandName(customSiteConfig),
  'Custom Factory',
  'admin shell brand name should prefer site config company_name'
);
assert.equal(
  getAdminBrandSubtitle(customSiteConfig),
  'Smart Bakery Lines',
  'admin shell subtitle should prefer site config company_subtitle'
);

assert.equal(
  getAdminLogoPath({}),
  '/assets/images/common/logo-110.png',
  'admin shell logo should keep a safe default when site config is empty'
);
assert.equal(
  getAdminBrandName({}),
  'HANZUN 后台',
  'admin shell brand name should keep a safe default when site config is empty'
);
assert.equal(
  getAdminBrandSubtitle({}),
  '',
  'admin shell subtitle should be empty when no subtitle is configured'
);

assert.equal(
  getAdminDisplayName({ user: { nickname: 'Alice', username: 'alice' } }),
  'Alice',
  'display name should prefer nickname'
);
assert.equal(
  getAdminDisplaySecondary({ user: { username: 'alice' } }),
  'alice',
  'display secondary should prefer username'
);
assert.equal(
  getAdminDisplayName({}),
  '未登录',
  'display name should keep a readable fallback'
);
assert.equal(
  getAdminDisplaySecondary({}),
  '当前账号',
  'display secondary should keep a readable fallback'
);

console.log('Admin shell branding validation passed.');
