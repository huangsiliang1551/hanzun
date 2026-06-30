import assert from 'node:assert/strict';
import {
  getAdminBrandName,
  getAdminBrandSubtitle,
  getAdminDisplayName,
  getAdminDisplaySecondary,
  getAdminLogoPath,
} from '../src/utils/adminShell.js';
import {
  getResourcePosterPath,
  getResourcePreviewPath,
} from '../src/utils/resourcePreview.js';

assert.equal(getAdminLogoPath(), '/assets/images/common/logo-110.png', 'admin logo path should stay stable');
assert.equal(getAdminBrandName({ company_name: 'Any Company' }), '涵尊后台', 'admin brand name should stay fixed');
assert.equal(getAdminBrandSubtitle({ company_subtitle: 'Any Subtitle' }), '', 'admin brand subtitle should stay empty');

assert.equal(
  getAdminDisplayName({ user: { username: 'admin', nickname: 'Super Admin' } }),
  'Super Admin',
  'display name should prefer nickname from nested user profile',
);

assert.equal(
  getAdminDisplaySecondary({ user: { username: 'admin', nickname: 'Super Admin' } }),
  'admin',
  'secondary line should show username when available',
);

assert.equal(
  getAdminDisplayName({ username: 'operator' }),
  'operator',
  'display name should fall back to top-level username',
);

assert.equal(
  getResourcePreviewPath({ thumbnail_url: '/thumb-a.jpg', thumb_url: '/thumb-b.jpg', file_path: '/file.jpg' }),
  '/thumb-a.jpg',
  'preview should prefer thumbnail_url',
);

assert.equal(
  getResourcePreviewPath({ thumb_url: '/thumb-b.jpg', file_path: '/file.jpg' }),
  '/thumb-b.jpg',
  'preview should fall back to thumb_url',
);

assert.equal(
  getResourcePreviewPath({ file_path: '/file.jpg' }),
  '/file.jpg',
  'preview should finally fall back to file path',
);

assert.equal(
  getResourcePosterPath({ thumb_url: '/thumb-b.jpg', file_path: '/file.jpg' }),
  '/thumb-b.jpg',
  'poster should support legacy thumb_url payloads',
);

console.log('Admin shell and media helper validation passed.');
