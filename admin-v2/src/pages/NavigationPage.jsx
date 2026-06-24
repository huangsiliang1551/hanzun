import { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Button,
  Card,
  Col,
  Empty,
  Form,
  Input,
  List,
  Modal,
  Popconfirm,
  Row,
  Select,
  Space,
  Spin,
  Switch,
  Table,
  Tag,
  Typography,
  message,
} from 'antd';
import {
  createNavigationMenu,
  deleteNavigationMenu,
  getNavigationBootstrap,
  getNavigationLookups,
  getNavigationMenuDetail,
  getNavigationMenus,
  updateNavigationMenu,
  updateNavigationMenuItems,
} from '../api/navigation';
import PagePlaceholder from '@/components/PagePlaceholder';

const { Text, Title, Paragraph } = Typography;

const menuPositionOptions = [
  { label: '顶部', value: 'header' },
  { label: '底部', value: 'footer' },
];

const itemTypeOptions = [
  { label: '自动分类树', value: 'auto_category_tree' },
  { label: '页面链接', value: 'page' },
  { label: '公司页面链接', value: 'about_page' },
  { label: '自定义地址', value: 'manual_url' },
];

const displayModeOptions = [
  { label: '普通', value: 'plain' },
  { label: '下拉', value: 'dropdown' },
];

const menuTemplates = [
  {
    key: 'header_main',
    label: '顶部主菜单',
    name_zh: '主导航',
    menu_key: 'header_main',
    menu_position: 'header',
    sort: 100,
  },
  {
    key: 'footer_quick_links',
    label: '底部快捷入口',
    name_zh: '快捷入口',
    menu_key: 'footer_quick_links',
    menu_position: 'footer',
    sort: 100,
  },
];

const itemTypeHints = {
  auto_category_tree: '自动展开某个分类树作为下拉菜单。',
  page: '链接到单个页面。',
  about_page: '链接到公司介绍页面。',
  manual_url: '使用自定义相对路径或完整外链。',
};

const commonMenuItemTemplates = [
  { key: 'home_page', label: '首页', item_type: 'manual_url', name_zh: '首页', url: '/', code: 'home', route_key: '', open_in_new_tab: false },
  { key: 'about_page', label: '公司介绍', item_type: 'about_page', name_zh: '公司介绍' },
  { key: 'products_tree', label: '产品', item_type: 'auto_category_tree', name_zh: '产品', linked_entity_type: 'product_category', max_depth: 2, display_mode: 'dropdown' },
  { key: 'solutions_tree', label: '方案', item_type: 'auto_category_tree', name_zh: '方案', linked_entity_type: 'solution_category', max_depth: 2, display_mode: 'dropdown' },
  { key: 'news_tree', label: '新闻', item_type: 'auto_category_tree', name_zh: '新闻', linked_entity_type: 'article_category', max_depth: 2, display_mode: 'dropdown' },
  { key: 'contact_url', label: '联系', item_type: 'manual_url', name_zh: '联系', url: '/about', code: 'contact', route_key: '', open_in_new_tab: false },
];

const itemTypeLabelMap = {
  auto_category_tree: '分类树菜单',
  page: '单页链接',
  about_page: '公司页面',
  manual_url: '自定义链接',
};

const entityTypeLabelMap = {
  product_category: '产品分类',
  solution_category: '方案分类',
  article_category: '新闻分类',
  page: '单页',
  about_page: '公司页',
  custom_url: '自定义地址',
};

function flattenCategoryTree(nodes, level = 0) {
  return (Array.isArray(nodes) ? nodes : []).flatMap((item) => {
    const current = [
      {
        id: Number(item.id || 0),
        name_zh: item.name_zh || item.title_zh || `分类 #${item.id}`,
        slug: item.slug || '',
        level,
      },
    ];
    return current.concat(flattenCategoryTree(item.children || [], level + 1));
  });
}

function buildIndentedLabel(item) {
  return `${item.level > 0 ? `${'  '.repeat(item.level)}- ` : ''}${item.name_zh || ''}`;
}

function buildMenuItemLabel(item) {
  return `${item.level > 0 ? `${'  '.repeat(item.level)}- ` : ''}${item.name_zh || '项目'}`;
}

function isValidNavigationUrl(value) {
  const text = String(value || '').trim();
  return text.startsWith('/') || /^https?:\/\//i.test(text);
}

function getDefaultMenuValues() {
  return {
    template_key: undefined,
    name_zh: '',
    menu_key: '',
    menu_position: 'header',
    sort: 100,
    is_enabled: true,
  };
}

function getDefaultItemValues(productCategories = []) {
  return {
    parent_id: 0,
    name_zh: '',
    code: '',
    route_key: '',
    item_type: 'auto_category_tree',
    linked_entity_type: 'product_category',
    linked_entity_id: productCategories[0]?.id || 0,
    max_depth: 2,
    include_children: true,
    display_mode: 'dropdown',
    url: '',
    open_in_new_tab: false,
    sort: 100,
    is_enabled: true,
  };
}

function applyMenuTemplate(form, templateKey) {
  const template = menuTemplates.find((item) => item.key === templateKey);
  if (!template) return;
  form.setFieldsValue({
    template_key: template.key,
    name_zh: template.name_zh,
    menu_key: template.menu_key,
    menu_position: template.menu_position,
    sort: template.sort,
  });
}

function normalizeItemPayload(values, entityMeta) {
  const itemType = values.item_type || 'auto_category_tree';
  const normalized = {
    parent_id: Number(values.parent_id || 0),
    name_zh: values.name_zh || '',
    code: values.code || '',
    route_key: values.route_key || '',
    item_type: itemType,
    link_type: 'category_tree',
    linked_entity_type: values.linked_entity_type || 'product_category',
    linked_entity_id: Number(values.linked_entity_id || 0) || null,
    root_category_id: null,
    max_depth: Number(values.max_depth || 1),
    include_children: values.include_children ? 1 : 0,
    display_mode: values.display_mode || 'dropdown',
    url: values.url || '',
    open_in_new_tab: values.open_in_new_tab ? 1 : 0,
    sort: Number(values.sort || 0),
    is_enabled: values.is_enabled ? 1 : 0,
  };

  if (itemType === 'manual_url') {
    normalized.link_type = 'manual_url';
    normalized.linked_entity_type = 'custom_url';
    normalized.linked_entity_id = null;
    normalized.root_category_id = null;
    return normalized;
  }

  if (itemType === 'page') {
    normalized.link_type = 'page';
    normalized.linked_entity_type = 'page';
    normalized.root_category_id = null;
    normalized.route_key = normalized.route_key || entityMeta.slug || '';
    normalized.url = normalized.url || (entityMeta.slug ? `/${entityMeta.slug}` : '');
    normalized.code = normalized.code || entityMeta.slug || '';
    normalized.name_zh = normalized.name_zh || entityMeta.name || '';
    return normalized;
  }

  if (itemType === 'about_page') {
    normalized.link_type = 'page';
    normalized.linked_entity_type = 'about_page';
    normalized.root_category_id = null;
    normalized.route_key = normalized.route_key || 'about';
    normalized.url = normalized.url || '/about';
    normalized.code = normalized.code || entityMeta.page_key || 'about';
    normalized.name_zh = normalized.name_zh || entityMeta.name || '';
    return normalized;
  }

  normalized.link_type = 'category_tree';
  normalized.root_category_id = normalized.linked_entity_id;

  const prefixMap = {
    product_category: 'products',
    solution_category: 'solutions',
    article_category: 'news',
  };
  const prefix = prefixMap[normalized.linked_entity_type] || '';

  normalized.route_key =
    normalized.route_key || (prefix && entityMeta.slug ? `${prefix}/${entityMeta.slug}` : prefix);
  normalized.url =
    normalized.url || (prefix && entityMeta.slug ? `/${prefix}/${entityMeta.slug}` : '');
  normalized.code = normalized.code || entityMeta.slug || '';
  normalized.name_zh = normalized.name_zh || entityMeta.name || '';

  return normalized;
}

function collectDescendantIds(items, itemId) {
  const result = [Number(itemId)];
  let cursor = 0;

  while (cursor < result.length) {
    const parentId = result[cursor];
    items.forEach((item) => {
      if (Number(item.parent_id || 0) === Number(parentId) && !result.includes(Number(item.id || 0))) {
        result.push(Number(item.id || 0));
      }
    });
    cursor += 1;
  }

  return result;
}

function flattenMenuItems(items, parentId = 0, level = 0) {
  return (Array.isArray(items) ? items : [])
    .filter((item) => Number(item.parent_id || 0) === Number(parentId))
    .sort((left, right) => Number(right.sort || 0) - Number(left.sort || 0))
    .flatMap((item) => [
      {
        ...item,
        level,
      },
      ...flattenMenuItems(items, item.id, level + 1),
    ]);
}

export default function NavigationPage() {
  const [menus, setMenus] = useState([]);
  const [currentMenuId, setCurrentMenuId] = useState(null);
  const [currentMenu, setCurrentMenu] = useState(null);
  const [listLoading, setListLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);
  const [menuSaving, setMenuSaving] = useState(false);
  const [itemSaving, setItemSaving] = useState(false);
  const [itemModalOpen, setItemModalOpen] = useState(false);
  const [editingItemId, setEditingItemId] = useState(null);
  const [pageOptions, setPageOptions] = useState([]);
  const [aboutOptions, setAboutOptions] = useState([]);
  const [productCategories, setProductCategories] = useState([]);
  const [solutionCategories, setSolutionCategories] = useState([]);
  const [articleCategories, setArticleCategories] = useState([]);
  const [advancedFieldsOpen, setAdvancedFieldsOpen] = useState(false);
  const [menuForm] = Form.useForm();
  const [itemForm] = Form.useForm();

  const currentItemType = Form.useWatch('item_type', itemForm) || 'auto_category_tree';
  const currentEntityType =
    Form.useWatch('linked_entity_type', itemForm) ||
    (currentItemType === 'page'
      ? 'page'
      : currentItemType === 'about_page'
        ? 'about_page'
        : 'product_category');
  const isManualUrl = currentItemType === 'manual_url';
  const isAutoCategoryTree = currentItemType === 'auto_category_tree';
  const isPageItem = currentItemType === 'page';
  const isAboutItem = currentItemType === 'about_page';
  const requiresEntitySelection = isAutoCategoryTree || isPageItem || isAboutItem;
  const showEntityTypeSelector = isAutoCategoryTree;
  const entityFieldLabel = isPageItem ? '页面' : isAboutItem ? '公司页面' : '根分类';

  async function loadReferences() {
    try {
      const payload = await getNavigationLookups();
      const pagesPayload = payload?.pages || {};
      const aboutPages = payload?.about_pages || [];
      const products = payload?.product_categories || [];
      const solutions = payload?.solution_categories || [];

      setPageOptions(
        Array.isArray(pagesPayload.items)
          ? pagesPayload.items.map((item) => ({
              id: Number(item.id || 0),
              name: item.title_zh || `页面 #${item.id}`,
              slug: item.slug || '',
            }))
          : [],
      );
      setAboutOptions(
        Array.isArray(aboutPages)
          ? aboutPages.map((item) => ({
              id: Number(item.id || 0),
              name: item.name_zh || `公司页面 #${item.id}`,
              page_key: item.page_key || '',
            }))
          : [],
      );
      setProductCategories(flattenCategoryTree(products));
      setSolutionCategories(flattenCategoryTree(solutions));
    } catch (error) {
      message.error(error.message || '加载导航引用数据失败。');
    }
  }

  async function loadMenuDetail(menuId) {
    if (!menuId) {
      setCurrentMenuId(null);
      setCurrentMenu(null);
      menuForm.setFieldsValue(getDefaultMenuValues());
      return;
    }

    setDetailLoading(true);
    try {
      const detail = await getNavigationMenuDetail(menuId);
      setCurrentMenuId(Number(menuId));
      setCurrentMenu(detail);
      menuForm.setFieldsValue({
        name_zh: detail.name_zh || '',
        menu_key: detail.menu_key || '',
        menu_position: detail.menu_position || 'header',
        sort: Number(detail.sort || 0),
        is_enabled: Number(detail.is_enabled || 0) === 1,
      });
    } catch (error) {
      message.error(error.message || '加载导航详情失败。');
    } finally {
      setDetailLoading(false);
    }
  }

  async function loadMenus(preferredId = currentMenuId) {
    setListLoading(true);
    try {
      const data = await getNavigationBootstrap({
        preferred_id: Number(preferredId || 0) || undefined,
      });
      const nextMenus = Array.isArray(data?.menus) ? data.menus : [];
      setMenus(nextMenus);
      const payload = data?.lookups || {};

      const pagesPayload = payload?.pages || {};
      const aboutPages = payload?.about_pages || [];
      const products = payload?.product_categories || [];
      const solutions = payload?.solution_categories || [];

      setPageOptions(
        Array.isArray(pagesPayload.items)
          ? pagesPayload.items.map((item) => ({
              id: Number(item.id || 0),
              name: item.title_zh || `页面 #${item.id}`,
              slug: item.slug || '',
            }))
          : [],
      );
      setAboutOptions(
        Array.isArray(aboutPages)
          ? aboutPages.map((item) => ({
              id: Number(item.id || 0),
              name: item.name_zh || `公司页面 #${item.id}`,
              page_key: item.page_key || '',
            }))
          : [],
      );
      setProductCategories(flattenCategoryTree(products));
      setSolutionCategories(flattenCategoryTree(solutions));

      const detail = data?.detail || null;
      const nextId = Number(data?.current_id || nextMenus[0]?.id || 0);

      if (!nextMenus.length) {
        setCurrentMenuId(null);
        setCurrentMenu(null);
        menuForm.setFieldsValue(getDefaultMenuValues());
        return;
      }

      setCurrentMenuId(nextId || null);
      setCurrentMenu(detail);
      menuForm.setFieldsValue({
        name_zh: detail?.name_zh || '',
        menu_key: detail?.menu_key || '',
        menu_position: detail?.menu_position || 'header',
        sort: Number(detail?.sort || 0),
        is_enabled: Number(detail?.is_enabled || 0) === 1,
      });
    } catch (error) {
      message.error(error.message || '加载导航菜单失败。');
    } finally {
      setListLoading(false);
    }
  }

  useEffect(() => {
    loadMenus();
  }, []);

  async function handleSaveMenu(values) {
    setMenuSaving(true);
    try {
      const payload = {
        name_zh: values.name_zh || '',
        menu_key: values.menu_key || '',
        menu_position: values.menu_position || 'header',
        sort: Number(values.sort || 0),
        is_enabled: values.is_enabled ? 1 : 0,
      };

      const response = currentMenuId
        ? await updateNavigationMenu(currentMenuId, payload)
        : await createNavigationMenu(payload);

      message.success(currentMenuId ? '菜单已更新。' : '菜单已创建。');
      await loadMenus(Number(response.id));
    } catch (error) {
      message.error(error.message || '保存菜单失败。');
    } finally {
      setMenuSaving(false);
    }
  }

  function handleCreateMenu() {
    setCurrentMenuId(null);
    setCurrentMenu(null);
    menuForm.setFieldsValue(getDefaultMenuValues());
  }

  async function handleDeleteMenu() {
    if (!currentMenuId) return;

    try {
      await deleteNavigationMenu(currentMenuId);
      message.success('菜单已删除。');
      await loadMenus(null);
    } catch (error) {
      message.error(error.message || '删除菜单失败。');
    }
  }

  function resolveEntityOptions(entityType) {
    if (entityType === 'page') {
      return pageOptions.map((item) => ({ label: item.name, value: item.id }));
    }
    if (entityType === 'about_page') {
      return aboutOptions.map((item) => ({ label: item.name, value: item.id }));
    }

    const source =
      entityType === 'solution_category'
        ? solutionCategories
        : entityType === 'article_category'
          ? articleCategories
          : productCategories;

    return source.map((item) => ({
      label: buildIndentedLabel(item),
      value: item.id,
    }));
  }

  function getEntityMeta(entityType, entityId) {
    if (entityType === 'page') {
      const item = pageOptions.find((entry) => Number(entry.id) === Number(entityId));
      return { name: item?.name || '', slug: item?.slug || '' };
    }
    if (entityType === 'about_page') {
      const item = aboutOptions.find((entry) => Number(entry.id) === Number(entityId));
      return { name: item?.name || '', page_key: item?.page_key || '' };
    }

    const source =
      entityType === 'solution_category'
        ? solutionCategories
        : entityType === 'article_category'
          ? articleCategories
          : productCategories;
    const item = source.find((entry) => Number(entry.id) === Number(entityId));
    return { name: item?.name_zh || '', slug: item?.slug || '' };
  }

  function openCreateItem() {
    setEditingItemId(null);
    setAdvancedFieldsOpen(false);
    itemForm.setFieldsValue(getDefaultItemValues(productCategories));
    setItemModalOpen(true);
  }

  function openEditItem(item) {
    setEditingItemId(Number(item.id || 0));
    setAdvancedFieldsOpen(
      Boolean(
        item.code ||
          item.route_key ||
          (item.item_type === 'manual_url' && item.open_in_new_tab) ||
          (item.item_type === 'auto_category_tree' &&
            ((item.display_mode || 'dropdown') !== 'dropdown' ||
              Number(item.max_depth || 1) !== 2 ||
              Number(item.include_children || 0) !== 1)),
      ),
    );
    itemForm.setFieldsValue({
      parent_id: Number(item.parent_id || 0),
      name_zh: item.name_zh || '',
      code: item.code || '',
      route_key: item.route_key || '',
      item_type: item.item_type || 'auto_category_tree',
      linked_entity_type: item.linked_entity_type || 'product_category',
      linked_entity_id: item.linked_entity_id ?? 0,
      max_depth: Number(item.max_depth || 1),
      include_children: Number(item.include_children || 0) === 1,
      display_mode: item.display_mode || 'dropdown',
      url: item.url || '',
      open_in_new_tab: Number(item.open_in_new_tab || 0) === 1,
      sort: Number(item.sort || 0),
      is_enabled: Number(item.is_enabled || 0) === 1,
    });
    setItemModalOpen(true);
  }

  function applyItemTemplate(templateKey) {
    const template = commonMenuItemTemplates.find((item) => item.key === templateKey);
    if (!template) return;

    itemForm.setFieldsValue({
      item_template_key: template.key,
      item_type: template.item_type,
      name_zh: template.name_zh || '',
      linked_entity_type:
        template.linked_entity_type ||
        (template.item_type === 'page'
          ? 'page'
          : template.item_type === 'about_page'
            ? 'about_page'
            : template.item_type === 'manual_url'
              ? 'custom_url'
              : 'product_category'),
      linked_entity_id: 0,
      url: template.url || '',
      code: template.code || '',
      route_key: template.route_key || '',
      max_depth: template.max_depth || 2,
      include_children: template.include_children ?? true,
      display_mode: template.display_mode || 'dropdown',
      open_in_new_tab: template.open_in_new_tab ?? false,
      sort: 100,
      is_enabled: true,
    });
  }

  async function persistItems(nextItems, successMessage) {
    if (!currentMenuId) return;

    setItemSaving(true);
    try {
      const response = await updateNavigationMenuItems(currentMenuId, nextItems);
      setCurrentMenu(response);
      message.success(successMessage);
      await loadMenus(currentMenuId);
      setItemModalOpen(false);
      setEditingItemId(null);
    } catch (error) {
      message.error(error.message || '保存导航项失败。');
    } finally {
      setItemSaving(false);
    }
  }

  async function handleSaveItem() {
    if (!currentMenuId || !currentMenu) return;

    const values = await itemForm.validateFields();
    const items = Array.isArray(currentMenu.items) ? currentMenu.items.slice() : [];
    const blockedParentIds = editingItemId ? collectDescendantIds(items, editingItemId) : [];

    if (editingItemId && blockedParentIds.includes(Number(values.parent_id || 0))) {
      message.error('父级不能选择当前项或其子项。');
      return;
    }

    const entityMeta = getEntityMeta(values.linked_entity_type, values.linked_entity_id);
    const payload = normalizeItemPayload(values, entityMeta);

    if (editingItemId) {
      const nextItems = items.map((item) =>
        Number(item.id) === Number(editingItemId) ? { ...item, ...payload } : item,
      );
      await persistItems(nextItems, '导航项已更新。');
      return;
    }

    const tempId = Date.now() * -1;
    const nextItems = items.concat([
      {
        id: tempId,
        menu_id: currentMenuId,
        ...payload,
      },
    ]);
    await persistItems(nextItems, '导航项已创建。');
  }

  async function handleDeleteItem(item) {
    if (!currentMenuId || !currentMenu) return;
    const items = Array.isArray(currentMenu.items) ? currentMenu.items.slice() : [];
    const deleteIds = collectDescendantIds(items, item.id);
    const nextItems = items.filter((entry) => !deleteIds.includes(Number(entry.id || 0)));
    await persistItems(nextItems, '导航项已删除。');
  }

  async function handleToggleItemEnabled(item, checked) {
    if (!currentMenuId || !currentMenu) return;
    const items = Array.isArray(currentMenu.items) ? currentMenu.items.slice() : [];
    const nextItems = items.map((entry) =>
      Number(entry.id) === Number(item.id) ? { ...entry, is_enabled: checked ? 1 : 0 } : entry,
    );
    await persistItems(nextItems, '导航项状态已更新。');
  }

  async function handleMoveItem(item, direction) {
    if (!currentMenuId || !currentMenu) return;

    const items = Array.isArray(currentMenu.items) ? currentMenu.items.slice() : [];
    const siblings = items
      .filter((entry) => Number(entry.parent_id || 0) === Number(item.parent_id || 0))
      .sort((left, right) => Number(right.sort || 0) - Number(left.sort || 0));
    const currentIndex = siblings.findIndex((entry) => Number(entry.id) === Number(item.id));
    const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

    if (currentIndex < 0 || targetIndex < 0 || targetIndex >= siblings.length) return;

    const target = siblings[targetIndex];
    const nextItems = items.map((entry) => {
      if (Number(entry.id) === Number(item.id)) {
        return { ...entry, sort: Number(target.sort || 0) };
      }
      if (Number(entry.id) === Number(target.id)) {
        return { ...entry, sort: Number(item.sort || 0) };
      }
      return entry;
    });

    await persistItems(nextItems, '导航顺序已更新。');
  }

  const flattenedItems = flattenMenuItems(currentMenu?.items || []);
  const blockedParentIds = editingItemId ? collectDescendantIds(currentMenu?.items || [], editingItemId) : [];
  const activeMenuCount = useMemo(
    () => menus.filter((item) => Number(item.is_enabled || 0) === 1).length,
    [menus],
  );

  const parentOptions = flattenedItems
    .filter((item) => Number(item.id || 0) > 0 && !blockedParentIds.includes(Number(item.id || 0)))
    .map((item) => ({
      label: buildMenuItemLabel(item),
      value: item.id,
    }));

  const itemColumns = [
    {
      title: '名称',
      dataIndex: 'name_zh',
      width: 240,
      ellipsis: true,
      render: (_, record) => (
        <span className="navigation-item-name">
          {`${record.level > 0 ? `${'  '.repeat(record.level)}- ` : ''}${record.name_zh || '项目'}`}
        </span>
      ),
    },
    {
      title: '类型',
      dataIndex: 'item_type',
      width: 130,
      ellipsis: true,
      render: (value) => itemTypeLabelMap[value] || value || '-',
    },
    {
      title: '目标',
      width: 300,
      ellipsis: true,
      render: (_, record) => (
        <Space direction="vertical" size={2} className="navigation-item-target">
          <Text>{entityTypeLabelMap[record.linked_entity_type] || record.linked_entity_type || '-'}</Text>
          <Text type="secondary">{record.url || record.route_key || '自动生成'}</Text>
        </Space>
      ),
    },
    {
      title: '排序',
      dataIndex: 'sort',
      width: 90,
      ellipsis: true,
    },
    {
      title: '启用',
      dataIndex: 'is_enabled',
      width: 100,
      ellipsis: true,
      render: (value, record) => (
        <Switch
          size="small"
          checked={Number(value || 0) === 1}
          checkedChildren="开"
          unCheckedChildren="关"
          onChange={(checked) => handleToggleItemEnabled(record, checked)}
        />
      ),
    },
    {
      title: '操作',
      width: 260,
      render: (_, record) => (
        <Space wrap>
          <Button size="small" onClick={() => openEditItem(record)}>编辑</Button>
          <Button size="small" onClick={() => handleMoveItem(record, 'up')}>上移</Button>
          <Button size="small" onClick={() => handleMoveItem(record, 'down')}>下移</Button>
          <Popconfirm
            title="删除这个导航项？"
            okText="删除"
            cancelText="取消"
            onConfirm={() => handleDeleteItem(record)}
          >
            <Button size="small" danger>删除</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <PagePlaceholder hideHeader compact tags={[`${menus.length} 个菜单`, `${activeMenuCount} 个启用`]}>
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <div className="toolbar-surface">
        <Space wrap size={12} style={{ width: '100%', justifyContent: 'space-between' }}>
          <Space wrap>
            <Button onClick={handleCreateMenu}>新建菜单</Button>
            <Button type="primary" loading={menuSaving} onClick={() => menuForm.submit()}>
              保存菜单
            </Button>
            <Popconfirm
              title="删除这个菜单？"
              okText="删除"
              cancelText="取消"
              disabled={!currentMenuId}
              onConfirm={handleDeleteMenu}
            >
              <Button danger disabled={!currentMenuId}>删除菜单</Button>
            </Popconfirm>
          </Space>
        </Space>
      </div>

      <Row gutter={16} align="stretch">
        <Col xs={24} lg={6}>
          <Card title="菜单列表" size="small" style={{ height: '100%' }}>
            <Spin spinning={listLoading}>
              {menus.length ? (
                <List
                  dataSource={menus}
                  renderItem={(item) => (
                    <List.Item
                      actions={[
                        <Tag key="position" color={item.menu_position === 'footer' ? 'purple' : 'blue'}>
                          {(item.menu_position || 'header') === 'footer' ? '底部' : '顶部'}
                        </Tag>,
                      ]}
                      style={{
                        cursor: 'pointer',
                        borderRadius: 8,
                        padding: 12,
                        background: Number(item.id) === Number(currentMenuId) ? '#f0f5ff' : 'transparent',
                      }}
                      onClick={() => loadMenuDetail(item.id)}
                    >
                      <Space direction="vertical" size={4} style={{ width: '100%' }}>
                        <Space align="center" style={{ width: '100%', justifyContent: 'space-between' }}>
                          <Text strong>{item.name_zh || `菜单 #${item.id}`}</Text>
                          {Number(item.is_enabled || 0) === 1 ? <Tag color="success">启用</Tag> : <Tag>停用</Tag>}
                        </Space>
                        <Text type="secondary">
                          {(item.menu_position || 'header') === 'footer' ? '底部' : '顶部'} / {Array.isArray(item.items) ? item.items.length : 0} 项
                        </Text>
                        <Text type="secondary">{item.menu_key || '-'}</Text>
                      </Space>
                    </List.Item>
                  )}
                />
              ) : (
                <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="暂无菜单">
                  <Button type="primary" onClick={handleCreateMenu}>创建第一个菜单</Button>
                </Empty>
              )}
            </Spin>
          </Card>
        </Col>

        <Col xs={24} lg={7}>
          <Card title="菜单配置" size="small" style={{ height: '100%' }}>
            <Spin spinning={detailLoading}>
              <Form form={menuForm} layout="vertical" initialValues={getDefaultMenuValues()} onFinish={handleSaveMenu}>
                <Form.Item name="template_key" label="模板">
                  <Select
                    allowClear
                    placeholder="选择菜单模板"
                    options={menuTemplates.map((item) => ({ label: item.label, value: item.key }))}
                    onChange={(value) => applyMenuTemplate(menuForm, value)}
                  />
                </Form.Item>

                <Form.Item
                  name="name_zh"
                  label="菜单名称"
                  rules={[{ required: true, message: '请输入菜单名称。' }]}
                >
                  <Input />
                </Form.Item>

                <Form.Item
                  name="menu_key"
                  label="菜单标识"
                  extra="前台会通过这个标识读取菜单内容，建议创建后保持稳定，例如 header_main 或 footer_quick_links。"
                  rules={[{ required: true, message: '请输入菜单标识。' }]}
                >
                  <Input placeholder="例如：header_main" />
                </Form.Item>

                <Row gutter={16}>
                  <Col xs={24} md={12}>
                    <Form.Item name="menu_position" label="位置">
                      <Select options={menuPositionOptions} />
                    </Form.Item>
                  </Col>
                  <Col xs={24} md={12}>
                    <Form.Item name="sort" label="排序">
                      <Input type="number" />
                    </Form.Item>
                  </Col>
                </Row>

                <Form.Item name="is_enabled" label="启用" valuePropName="checked">
                  <Switch checkedChildren="开" unCheckedChildren="关" />
                </Form.Item>
              </Form>
            </Spin>
          </Card>
        </Col>

        <Col xs={24} lg={11}>
          <Card
            title="菜单项"
            size="small"
            extra={
              <Button type="primary" disabled={!currentMenuId} onClick={openCreateItem}>
                新增菜单项
              </Button>
            }
            style={{ height: '100%' }}
          >
            {currentMenuId ? (
              <Table
                className="navigation-item-table"
                rowKey={(record) => `${record.id}-${record.level}`}
                columns={itemColumns}
                dataSource={flattenedItems}
                tableLayout="fixed"
                pagination={false}
                locale={{ emptyText: '暂无菜单项' }}
              />
            ) : (
              <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="请先创建或选择一个菜单" />
            )}
          </Card>
        </Col>
      </Row>

      <Modal
        title={editingItemId ? '编辑导航项' : '新建导航项'}
        open={itemModalOpen}
        confirmLoading={itemSaving}
        onCancel={() => {
          setItemModalOpen(false);
          setEditingItemId(null);
        }}
        onOk={handleSaveItem}
        width={720}
      >
        <Form form={itemForm} layout="vertical">
          {!editingItemId ? (
            <Form.Item name="item_template_key" label="模板">
              <Select
                allowClear
                placeholder="选择常用导航项模板"
                options={commonMenuItemTemplates.map((item) => ({ label: item.label, value: item.key }))}
                onChange={applyItemTemplate}
              />
            </Form.Item>
          ) : null}

          <Row gutter={16}>
            <Col xs={24} md={12}>
              <Form.Item
                name="name_zh"
                label="名称"
                rules={[
                  ({ getFieldValue }) => ({
                    validator(_, value) {
                      const itemType = getFieldValue('item_type');
                      if (itemType !== 'manual_url') return Promise.resolve();
                      if (String(value || '').trim()) return Promise.resolve();
                      return Promise.reject(new Error('请输入菜单名称。'));
                    },
                  }),
                ]}
              >
                <Input />
              </Form.Item>
            </Col>
            <Col xs={24} md={12}>
              <Form.Item name="parent_id" label="父级">
                <Select options={[{ label: '根项', value: 0 }].concat(parentOptions)} />
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={16}>
            <Col xs={24} md={12}>
              <Form.Item
                name="item_type"
                label="类型"
                rules={[{ required: true, message: '请选择导航项类型。' }]}
              >
                <Select
                  options={itemTypeOptions}
                  onChange={(value) => {
                    if (value === 'page') {
                      itemForm.setFieldsValue({ linked_entity_type: 'page' });
                    } else if (value === 'about_page') {
                      itemForm.setFieldsValue({ linked_entity_type: 'about_page' });
                    } else if (value === 'manual_url') {
                      itemForm.setFieldsValue({
                        linked_entity_type: 'custom_url',
                        linked_entity_id: 0,
                      });
                    } else {
                      itemForm.setFieldsValue({ linked_entity_type: 'product_category' });
                    }
                  }}
                />
              </Form.Item>
            </Col>
            <Col xs={24} md={12}>
              <Form.Item
                name="linked_entity_type"
                label="实体类型"
                hidden={!showEntityTypeSelector}
                rules={[
                  ({ getFieldValue }) => ({
                    validator(_, value) {
                      const itemType = getFieldValue('item_type');
                      if (!showEntityTypeSelector || itemType === 'manual_url') return Promise.resolve();
                      if (String(value || '').trim()) return Promise.resolve();
                      return Promise.reject(new Error('请选择实体类型。'));
                    },
                  }),
                ]}
              >
                <Select
                  disabled={currentItemType === 'manual_url'}
                  options={
                    currentItemType === 'page'
                      ? [{ label: '页面', value: 'page' }]
                      : currentItemType === 'about_page'
                        ? [{ label: '公司页面', value: 'about_page' }]
                        : currentItemType === 'manual_url'
                          ? [{ label: '自定义地址', value: 'custom_url' }]
                          : [
                              { label: '产品分类', value: 'product_category' },
                              { label: '方案分类', value: 'solution_category' },
                              { label: '新闻与案例分类', value: 'article_category' },
                            ]
                  }
                />
              </Form.Item>
            </Col>
          </Row>

          <Form.Item
            name="linked_entity_id"
            label={entityFieldLabel}
            hidden={!requiresEntitySelection}
            rules={[
              ({ getFieldValue }) => ({
                validator(_, value) {
                  const itemType = getFieldValue('item_type');
                  if (!['page', 'about_page', 'auto_category_tree'].includes(itemType)) {
                    return Promise.resolve();
                  }
                  if (Number(value || 0) > 0) return Promise.resolve();
                  return Promise.reject(new Error('请选择链接目标。'));
                },
              }),
            ]}
          >
            <Select options={resolveEntityOptions(currentEntityType)} showSearch optionFilterProp="label" />
          </Form.Item>

          <Row gutter={16}>
            <Col xs={24} md={12}>
              <Form.Item name="code" label="内部代码" extra="可选，用于后台识别或兼容历史数据。">
                <Input placeholder="可留空" />
              </Form.Item>
            </Col>
            <Col xs={24} md={12}>
              <Form.Item name="route_key" label="路由标识" extra="可留空，前台通常会根据目标自动生成。">
                <Input placeholder="例如：products/cake-line" />
              </Form.Item>
            </Col>
          </Row>

          <Form.Item
            name="url"
            label="地址"
            hidden={!isManualUrl}
            rules={[
              ({ getFieldValue }) => ({
                validator(_, value) {
                  const itemType = getFieldValue('item_type');
                  const text = String(value || '').trim();
                  if (itemType !== 'manual_url') return Promise.resolve();
                  if (!text) return Promise.reject(new Error('请输入自定义地址。'));
                  if (!isValidNavigationUrl(text)) {
                    return Promise.reject(new Error('请使用相对路径或完整 http(s) 地址。'));
                  }
                  return Promise.resolve();
                },
              }),
            ]}
          >
            <Input />
          </Form.Item>

          <Row gutter={16}>
            <Col xs={24} md={12}>
              <Form.Item name="sort" label="排序">
                <Input type="number" />
              </Form.Item>
            </Col>
            <Col xs={24} md={12}>
              <Form.Item name="is_enabled" label="启用" valuePropName="checked">
                <Switch checkedChildren="开" unCheckedChildren="关" />
              </Form.Item>
            </Col>
          </Row>

          <Card
            size="small"
            title="高级链接设置"
            extra={
              <Button type="link" onClick={() => setAdvancedFieldsOpen((value) => !value)}>
                {advancedFieldsOpen ? '收起' : '展开'}
              </Button>
            }
          >
            {advancedFieldsOpen ? (
              <>
                <Row gutter={16}>
                  <Col xs={24} md={12}>
                    <Form.Item name="code" label="内部代码">
                      <Input placeholder="例如：header_products" />
                    </Form.Item>
                  </Col>
                  <Col xs={24} md={12}>
                    <Form.Item name="route_key" label="路由标识">
                      <Input placeholder="仅在需要覆盖默认链接时填写" />
                    </Form.Item>
                  </Col>
                </Row>

                {isAutoCategoryTree ? (
                  <>
                    <Row gutter={16}>
                      <Col xs={24} md={12}>
                        <Form.Item name="display_mode" label="展示方式">
                          <Select options={displayModeOptions} />
                        </Form.Item>
                      </Col>
                      <Col xs={24} md={12}>
                        <Form.Item
                          name="max_depth"
                          label="最大层级"
                          rules={[
                            ({ getFieldValue }) => ({
                              validator(_, value) {
                                const itemType = getFieldValue('item_type');
                                if (itemType !== 'auto_category_tree') return Promise.resolve();
                                const parsed = Number(value || 0);
                                if (Number.isInteger(parsed) && parsed >= 1) return Promise.resolve();
                                return Promise.reject(new Error('层级至少为 1。'));
                              },
                            }),
                          ]}
                        >
                          <Input type="number" />
                        </Form.Item>
                      </Col>
                    </Row>

                    <Form.Item name="include_children" label="包含子项" valuePropName="checked">
                      <Switch checkedChildren="是" unCheckedChildren="否" />
                    </Form.Item>
                  </>
                ) : null}

                {isManualUrl ? (
                  <Form.Item name="open_in_new_tab" label="新窗口打开" valuePropName="checked">
                    <Switch checkedChildren="是" unCheckedChildren="否" />
                  </Form.Item>
                ) : null}
              </>
            ) : (
              <Text type="secondary">常规维护通常不需要展开，只有在要覆盖默认链接、层级或跳转方式时再设置。</Text>
            )}
          </Card>
        </Form>
      </Modal>
    </Space>
    </PagePlaceholder>
  );
}

