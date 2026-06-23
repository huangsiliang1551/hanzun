import assert from 'node:assert/strict';
import { canAccessPath, filterMenuItemsByAccess } from '../src/utils/rbac.js';

const legacyArticlePermissions = ['article.view'];
const mockMenuItems = [
  {
    key: 'content',
    children: [
      { key: '/products', label: '产品管理' },
      { key: '/news', label: '新闻管理' },
      { key: '/cases', label: '案例管理' },
    ],
  },
];

assert.equal(
  canAccessPath('/news', legacyArticlePermissions),
  true,
  'legacy article.view permission should still unlock the news page',
);

assert.equal(
  canAccessPath('/cases', legacyArticlePermissions),
  true,
  'legacy article.view permission should still unlock the cases page',
);

const accessibleMenu = filterMenuItemsByAccess(mockMenuItems, legacyArticlePermissions);
const contentGroup = accessibleMenu.find((item) => item.key === 'content');
const contentKeys = Array.isArray(contentGroup?.children)
  ? contentGroup.children.map((item) => item.key)
  : [];

assert.equal(
  contentKeys.includes('/news'),
  true,
  'legacy article.view permission should keep the news menu visible',
);

assert.equal(
  contentKeys.includes('/cases'),
  true,
  'legacy article.view permission should keep the cases menu visible',
);

console.log('RBAC legacy article permission validation passed.');
