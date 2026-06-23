import {
  ApartmentOutlined,
  AppstoreOutlined,
  BookOutlined,
  DashboardOutlined,
  FolderOpenOutlined,
  MessageOutlined,
  SettingOutlined,
} from '@ant-design/icons';

export const menuItems = [
  {
    key: '/dashboard',
    icon: <DashboardOutlined />,
    label: '\u6570\u636e\u770b\u677f',
  },
  {
    key: '/inquiries',
    icon: <MessageOutlined />,
    label: '\u8be2\u76d8\u4e2d\u5fc3',
  },
  {
    key: 'content',
    icon: <AppstoreOutlined />,
    label: '\u5185\u5bb9\u7ba1\u7406',
    children: [
      { key: '/products', label: '\u4ea7\u54c1\u7ba1\u7406' },
      { key: '/solutions', label: '\u89e3\u51b3\u65b9\u6848' },
      { key: '/news', label: '\u65b0\u95fb\u7ba1\u7406' },
      { key: '/cases', label: '\u6848\u4f8b\u7ba1\u7406' },
      { key: '/team', label: '\u9500\u552e\u56e2\u961f' },
      { key: '/pages', label: '\u5355\u9875\u7ba1\u7406' },
    ],
  },
  {
    key: 'site',
    icon: <ApartmentOutlined />,
    label: '\u524d\u53f0\u8fd0\u8425',
    children: [
      { key: '/homepage', label: '\u9996\u9875\u7f16\u8f91' },
      { key: '/contacts', label: '\u8054\u7cfb\u5de5\u5382' },
      { key: '/ads', label: '\u5e7f\u544a\u7ba1\u7406' },
      { key: '/company', label: '\u516c\u53f8\u4ecb\u7ecd' },
      { key: '/navigation', label: '\u5bfc\u822a\u83dc\u5355' },
    ],
  },
  {
    key: '/resources',
    icon: <FolderOpenOutlined />,
    label: '\u8d44\u6e90\u4e2d\u5fc3',
  },
  {
    key: '/knowledge',
    icon: <BookOutlined />,
    label: 'AI \u77e5\u8bc6\u5e93',
  },
  {
    key: 'system',
    icon: <SettingOutlined />,
    label: '\u7cfb\u7edf\u8bbe\u7f6e',
    children: [
      { key: '/seo-dashboard', label: 'SEO \u7ba1\u7406' },
      { key: '/tasks', label: '\u4efb\u52a1\u4e2d\u5fc3' },
      { key: '/settings', label: '\u7cfb\u7edf\u914d\u7f6e' },
    ],
  },
];

export const menuTitleMap = {
  '/dashboard': '\u6570\u636e\u770b\u677f',
  '/inquiries': '\u8be2\u76d8\u4e2d\u5fc3',
  '/products': '\u4ea7\u54c1\u7ba1\u7406',
  '/solutions': '\u89e3\u51b3\u65b9\u6848',
  '/news': '\u65b0\u95fb\u7ba1\u7406',
  '/cases': '\u6848\u4f8b\u7ba1\u7406',
  '/分类管理': '\u5206\u7c7b\u7ba1\u7406',
  '/team': '\u9500\u552e\u56e2\u961f',
  '/pages': '\u5355\u9875\u7ba1\u7406',
  '/homepage': '\u9996\u9875\u7f16\u8f91',
  '/contacts': '\u8054\u7cfb\u5de5\u5382',
  '/ads': '\u5e7f\u544a\u7ba1\u7406',
  '/company': '\u516c\u53f8\u4ecb\u7ecd',
  '/navigation': '\u5bfc\u822a\u83dc\u5355',
  '/resources': '\u8d44\u6e90\u4e2d\u5fc3',
  '/knowledge': 'AI \u77e5\u8bc6\u5e93',
  '/seo-dashboard': 'SEO \u7ba1\u7406',
  '/seo-center': 'SEO \u7ba1\u7406',
  '/tasks': '\u4efb\u52a1\u4e2d\u5fc3',
  '/settings': '\u7cfb\u7edf\u914d\u7f6e',
};

