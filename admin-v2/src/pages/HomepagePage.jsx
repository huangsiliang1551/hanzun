import { useEffect, useState } from 'react';
import {
  Alert,
  Button,
  Card,
  Col,
  Empty,
  Form,
  Image,
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
import MediaPickerModal from '@/components/MediaPickerModal';
import PagePlaceholder from '@/components/PagePlaceholder';
import { getResourceDetail } from '@/api/resources';
import { resolveAssetUrl } from '@/utils/media';
import {
  createHomepageSection,
  getHomepageBootstrap,
  getHomepageSectionDetail,
  getHomepageSectionItems,
  getHomepagePreview,
  getHomepageSourceOptions,
  publishHomepage,
  restoreHomepageLive,
  updateHomepageSection,
  updateHomepageSectionStatus,
  updateHomepageSectionItems,
} from '../api/homepage';

const { TextArea } = Input;
const { Paragraph, Text, Title } = Typography;

const sectionTypeOptions = [
  { label: '固定配置', value: 'fixed_config' },
  { label: '产品列表', value: 'product_list' },
  { label: '方案列表', value: 'solution_list' },
  { label: '新闻列表', value: 'news_list' },
  { label: '案例列表', value: 'case_list' },
  { label: '手动编排', value: 'manual_pick' },
];

const fetchModeOptions = [
  { label: '固定配置', value: 'fixed_config' },
  { label: '自动读取最新', value: 'auto_latest' },
  { label: '手动编排', value: 'manual_pick' },
];

const sourceTypeOptions = [
  { label: '产品', value: 'product' },
  { label: '方案', value: 'solution' },
  { label: '新闻', value: 'news' },
  { label: '案例', value: 'case' },
];

const sectionTypeLabelMap = {
  fixed_config: '固定内容',
  product_list: '产品列表',
  solution_list: '方案列表',
  news_list: '新闻列表',
  case_list: '案例列表',
  manual_pick: '手动编排',
};

const fetchModeLabelMap = {
  fixed_config: '固定配置',
  auto_latest: '自动读取最新',
  manual_pick: '手动编排',
};

const sourceTypeLabelMap = {
  product: '产品',
  solution: '方案',
  news: '新闻',
  case: '案例',
  article: '文章',
};

const legacySectionKeyMap = {
  hero: 'hero_banner',
  hero_banner: 'hero_banner',
  featured_articles: 'company_news',
  featured_cases: 'customer_cases',
};

const sectionTitleFallbackMap = {
  hero_banner: '首屏主视觉',
  production_lines: '热门生产线',
  featured_products: '热门产品',
  featured_solutions: '热门方案',
  company_news: '企业新闻',
  customer_cases: '客户案例',
};

const homepageSectionTemplates = [
  {
    key: 'hero_banner',
    label: '首屏主视觉',
    section_key: 'hero_banner',
    title_zh: '首屏主视觉',
    section_type: 'fixed_config',
    fetch_mode: 'fixed_config',
    extra_limit: undefined,
    extra_cta_text: '获取方案',
  },
  {
    key: 'production_lines',
    label: '热门生产线',
    section_key: 'production_lines',
    title_zh: '热门生产线',
    section_type: 'product_list',
    fetch_mode: 'manual_pick',
    extra_limit: 6,
    extra_cta_text: '',
  },
  {
    key: 'featured_products',
    label: '热门产品',
    section_key: 'featured_products',
    title_zh: '热门产品',
    section_type: 'product_list',
    fetch_mode: 'manual_pick',
    extra_limit: 7,
    extra_cta_text: '',
  },
  {
    key: 'featured_solutions',
    label: '热门方案',
    section_key: 'featured_solutions',
    title_zh: '热门方案',
    section_type: 'solution_list',
    fetch_mode: 'manual_pick',
    extra_limit: 6,
    extra_cta_text: '',
  },
  {
    key: 'customer_cases',
    label: '客户案例',
    section_key: 'customer_cases',
    title_zh: '客户案例',
    section_type: 'case_list',
    fetch_mode: 'manual_pick',
    extra_limit: 5,
    extra_cta_text: '',
  },
  {
    key: 'company_news',
    label: '企业新闻',
    section_key: 'company_news',
    title_zh: '企业新闻',
    section_type: 'news_list',
    fetch_mode: 'manual_pick',
    extra_limit: 5,
    extra_cta_text: '',
  },
];

const sectionTypeGuides = {
  fixed_config: '适合首屏主视觉、广告位和公司说明。',
  product_list: '适合热门产品、生产线等产品型展示板块。',
  solution_list: '适合解决方案或案例展示板块。',
  news_list: '适合企业新闻、展会动态和资讯更新板块。',
  case_list: '适合客户案例、交付案例和项目展示板块。',
  manual_pick: '适合需要人工排序、指定来源的混合型板块。',
};

const fetchModeGuides = {
  fixed_config: '该板块只使用本身的配置内容，不需要单独维护来源列表。',
  auto_latest: '系统会自动读取该类型下最新已发布内容。',
  manual_pick: '可在下方手动选择来源、排序、覆盖标题摘要，并单独启停。',
};

function parseJsonText(rawValue, fallback) {
  const source = String(rawValue || '').trim();
  if (!source) {
    return fallback;
  }

  try {
    return JSON.parse(source);
  } catch {
    return fallback;
  }
}

function formatJson(value) {
  if (value === null || value === undefined || value === '') {
    return '{}';
  }

  if (typeof value === 'string') {
    return value;
  }

  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return '{}';
  }
}

function splitExtraConfig(value) {
  const config =
    value && typeof value === 'object' && !Array.isArray(value)
      ? { ...value }
      : {};

  const limit =
    Number.isFinite(Number(config.limit)) && Number(config.limit) > 0
      ? Number(config.limit)
      : undefined;
  const ctaText = typeof config.cta_text === 'string' ? config.cta_text : '';

  delete config.limit;
  delete config.cta_text;

  return {
    limit,
    ctaText,
    advanced: config,
  };
}

function getDefaultSectionValues() {
  return {
    section_key: '',
    section_type: 'fixed_config',
    title_zh: '',
    subtitle_zh: '',
    fetch_mode: 'fixed_config',
    sort: 100,
    is_enabled: true,
    extra_limit: undefined,
    extra_cta_text: '',
    extra_config: '{}',
  };
}

function buildItemLabel(item) {
  const sourceRecord =
    item && typeof item.source_record === 'object' ? item.source_record : {};

  return (
    item.display_title_zh ||
    item.title_override_zh ||
    sourceRecord.name_zh ||
    sourceRecord.title_zh ||
    `来源 #${item.source_id || 0}`
  );
}

function buildSourceOptions(items) {
  return (Array.isArray(items) ? items : []).map((item) => ({
    label: item.name_zh || item.title_zh || `记录 #${item.id}`,
    value: item.id,
  }));
}

function isLegacyNewsSectionKey(sectionKey) {
  return ['featured_articles', 'company_news'].includes(String(sectionKey || ''));
}

function isLegacyCaseSectionKey(sectionKey) {
  return ['featured_cases', 'customer_cases'].includes(String(sectionKey || ''));
}

function normalizeSectionKey(sectionKey, sectionType) {
  const rawKey = String(sectionKey || '').trim();

  if (legacySectionKeyMap[rawKey]) {
    return legacySectionKeyMap[rawKey];
  }

  if (isLegacyNewsSectionKey(rawKey) || sectionType === 'news_list') {
    return 'company_news';
  }

  if (isLegacyCaseSectionKey(rawKey) || sectionType === 'case_list') {
    return 'customer_cases';
  }

  return rawKey;
}

function normalizeLegacySectionType(sectionKey, sectionType) {
  const rawType = String(sectionType || '').trim();
  const rawKey = String(sectionKey || '').trim();

  if (rawKey === 'hero' || rawKey === 'hero_banner') {
    return 'fixed_config';
  }

  if (rawKey === 'featured_solutions') {
    return 'solution_list';
  }

  if (rawKey === 'featured_products' || rawKey === 'production_lines') {
    return 'product_list';
  }

  if (isLegacyNewsSectionKey(sectionKey)) {
    return 'news_list';
  }

  if (isLegacyCaseSectionKey(sectionKey)) {
    return 'case_list';
  }

  return rawType || 'fixed_config';
}

function getHomepageSectionDisplayTitle(section) {
  const normalizedKey = normalizeSectionKey(section?.section_key, section?.section_type);
  const normalizedType = normalizeLegacySectionType(section?.section_key, section?.section_type);
  const title = String(section?.title_zh || '').trim();

  if (title && title !== '新闻与案例' && title !== '推荐设备' && title !== '推荐方案') {
    return title;
  }

  if (sectionTitleFallbackMap[normalizedKey]) {
    return sectionTitleFallbackMap[normalizedKey];
  }

  if (normalizedType === 'news_list') {
    return '企业新闻';
  }

  if (normalizedType === 'case_list') {
    return '客户案例';
  }

  return title || String(section?.section_key || '').trim() || `板块 #${section?.id || 0}`;
}

function applyHomepageTemplate(form, templateKey) {
  const template = homepageSectionTemplates.find((item) => item.key === templateKey);
  if (!template) {
    return;
  }

  form.setFieldsValue({
    section_key: template.section_key,
    title_zh: template.title_zh,
    section_type: template.section_type,
    fetch_mode: template.fetch_mode,
    extra_limit: template.extra_limit,
    extra_cta_text: template.extra_cta_text,
  });
}

function resolvePreferredSourceType(sectionType, sectionKey) {
  if (isLegacyCaseSectionKey(sectionKey)) {
    return 'case';
  }

  if (isLegacyNewsSectionKey(sectionKey)) {
    return 'news';
  }

  if (sectionType === 'case_list') {
    return 'case';
  }

  if (sectionType === 'news_list') {
    return 'news';
  }

  if (sectionType === 'solution_list') {
    return 'solution';
  }

  return 'product';
}

function normalizeSectionType(sectionKey, sectionType) {
  return normalizeLegacySectionType(sectionKey, sectionType);
}

function normalizeHomepageSectionList(data) {
  return (Array.isArray(data) ? data : []).map((item) => ({
    ...item,
    section_key: normalizeSectionKey(item.section_key, item.section_type),
    section_type: normalizeLegacySectionType(item.section_key, item.section_type),
    title_zh: getHomepageSectionDisplayTitle(item),
  }));
}

function buildSectionFormValues(detail) {
  const extraConfig = splitExtraConfig(detail?.extra_config);
  const normalizedSectionType = normalizeLegacySectionType(detail?.section_key, detail?.section_type);
  const normalizedSectionKey = normalizeSectionKey(detail?.section_key, normalizedSectionType);

  return {
    section_key: normalizedSectionKey,
    section_type: normalizedSectionType,
    title_zh: getHomepageSectionDisplayTitle(detail),
    subtitle_zh: detail?.subtitle_zh || '',
    fetch_mode: detail?.fetch_mode || 'fixed_config',
    sort: Number(detail?.sort || 0),
    is_enabled: Number(detail?.is_enabled || 0) === 1,
    extra_limit: extraConfig.limit,
    extra_cta_text: extraConfig.ctaText,
    extra_config: formatJson(extraConfig.advanced),
  };
}

export default function HomepagePage() {
  const [sections, setSections] = useState([]);
  const [workflow, setWorkflow] = useState(null);
  const [currentSectionId, setCurrentSectionId] = useState(null);
  const [currentSection, setCurrentSection] = useState(null);
  const [items, setItems] = useState([]);
  const [listLoading, setListLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);
  const [savingSection, setSavingSection] = useState(false);
  const [savingItems, setSavingItems] = useState(false);
  const [publishing, setPublishing] = useState(false);
  const [restoring, setRestoring] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [itemEditorOpen, setItemEditorOpen] = useState(false);
  const [itemOptionsLoading, setItemOptionsLoading] = useState(false);
  const [sourceOptions, setSourceOptions] = useState([]);
  const [editingItemIndex, setEditingItemIndex] = useState(null);
  const [coverPickerOpen, setCoverPickerOpen] = useState(false);
  const [coverPreviewAsset, setCoverPreviewAsset] = useState(null);
  const [coverPreviewLoading, setCoverPreviewLoading] = useState(false);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [previewPayload, setPreviewPayload] = useState(null);
  const [sectionForm] = Form.useForm();
  const [createForm] = Form.useForm();
  const [itemAddForm] = Form.useForm();
  const [itemEditForm] = Form.useForm();
  const coverAssetId = Form.useWatch('cover_asset_id', itemEditForm);
  const currentSectionType = Form.useWatch('section_type', sectionForm) || 'fixed_config';
  const currentSectionKey = Form.useWatch('section_key', sectionForm) || '';
  const currentFetchMode = Form.useWatch('fetch_mode', sectionForm) || 'fixed_config';
  const canEditItems =
    currentFetchMode === 'manual_pick' ||
    currentSectionType === 'manual_pick' ||
    [
      'product_list',
      'solution_list',
      'news_list',
      'case_list',
    ].includes(currentSectionType);
  const showsItemLimit = currentSectionType !== 'fixed_config';
  const showsCtaText = currentSectionType === 'fixed_config';
  const sourceTypeLocked = ['product_list', 'solution_list', 'news_list', 'case_list'].includes(
    currentSectionType,
  );
  const preferredSourceType = resolvePreferredSourceType(currentSectionType, currentSectionKey);
  const sectionGuide = sectionTypeGuides[currentSectionType] || sectionTypeGuides.fixed_config;
  const fetchGuide = fetchModeGuides[currentFetchMode] || fetchModeGuides.fixed_config;

  function applySectionSelection(sectionId, detail, payloadItems) {
    if (!sectionId || !detail) {
      setCurrentSectionId(null);
      setCurrentSection(null);
      setItems([]);
      sectionForm.setFieldsValue(getDefaultSectionValues());
      return;
    }

    setCurrentSectionId(Number(sectionId));
    setCurrentSection(detail);
    setItems(Array.isArray(payloadItems?.items) ? payloadItems.items : []);
    sectionForm.setFieldsValue(buildSectionFormValues(detail));
  }

  async function loadBootstrap(preferredId = currentSectionId) {
    setListLoading(true);
    setDetailLoading(true);

    try {
      const data = await getHomepageBootstrap();
      const nextSections = normalizeHomepageSectionList(data?.sections);
      const nextWorkflow = data?.workflow || null;
      setSections(nextSections);
      setWorkflow(nextWorkflow);

      if (!nextSections.length) {
        applySectionSelection(null, null, { items: [] });
        return;
      }

      const preferredSection =
        nextSections.find((item) => Number(item.id) === Number(preferredId)) || nextSections[0];
      const preferredSectionId = Number(preferredSection?.id || 0);
      const bootstrapSectionId = Number(data?.current_section?.id || 0);

      if (preferredSectionId === bootstrapSectionId && data?.current_section) {
        applySectionSelection(preferredSectionId, data.current_section, data?.current_items || { items: [] });
        return;
      }

      await loadSectionDetail(preferredSectionId);
    } catch (error) {
      message.error(error.message || '加载首页板块列表失败。');
    } finally {
      setListLoading(false);
      setDetailLoading(false);
    }
  }

  async function loadSectionItems(sectionId) {
    if (!sectionId) {
      setItems([]);
      return;
    }

    const payload = await getHomepageSectionItems(sectionId);
    setItems(Array.isArray(payload.items) ? payload.items : []);
  }

  async function loadSectionDetail(sectionId) {
    if (!sectionId) {
      applySectionSelection(null, null, { items: [] });
      return;
    }

    setDetailLoading(true);

    try {
      const [detail] = await Promise.all([
        getHomepageSectionDetail(sectionId),
        loadSectionItems(sectionId),
      ]);
      setCurrentSectionId(Number(sectionId));
      setCurrentSection(detail);
      sectionForm.setFieldsValue(buildSectionFormValues(detail));
    } catch (error) {
      message.error(error.message || '加载首页板块失败。');
    } finally {
      setDetailLoading(false);
    }
  }

  useEffect(() => {
    loadBootstrap();
  }, []);

  useEffect(() => {
    if (editingItemIndex === null) {
      setCoverPreviewAsset(null);
      return;
    }

    const assetId = Number(coverAssetId || 0);
    if (!assetId) {
      setCoverPreviewAsset(null);
      return;
    }

    let disposed = false;
    setCoverPreviewLoading(true);

    getResourceDetail(assetId)
      .then((asset) => {
        if (!disposed) {
          setCoverPreviewAsset(asset);
        }
      })
      .catch(() => {
        if (!disposed) {
          setCoverPreviewAsset(null);
        }
      })
      .finally(() => {
        if (!disposed) {
          setCoverPreviewLoading(false);
        }
      });

    return () => {
      disposed = true;
    };
  }, [coverAssetId, editingItemIndex]);

  async function handleCreateSection(values) {
    try {
      const normalizedSectionType = normalizeSectionType(values.section_key, values.section_type);
      const payload = {
        section_key: normalizeSectionKey(values.section_key, normalizedSectionType),
        section_type: normalizedSectionType,
        title_zh: values.title_zh || '',
        subtitle_zh: '',
        fetch_mode: values.fetch_mode,
        extra_config: {},
        sort: Number(values.sort || 100),
        is_enabled: 1,
      };
      const created = await createHomepageSection(payload);
      message.success('首页板块已创建。');
      setCreateOpen(false);
      createForm.resetFields();
      await loadBootstrap(Number(created.id));
    } catch (error) {
      message.error(error.message || '创建首页板块失败。');
    }
  }

  async function handleSaveSection(values) {
    if (!currentSectionId) {
      return;
    }

    setSavingSection(true);

    try {
      const normalizedSectionType = normalizeSectionType(values.section_key, values.section_type);
      const payload = {
        section_key: normalizeSectionKey(values.section_key, normalizedSectionType),
        section_type: normalizedSectionType,
        title_zh: values.title_zh || '',
        subtitle_zh: values.subtitle_zh || '',
        fetch_mode: values.fetch_mode,
        extra_config: {
          ...parseJsonText(values.extra_config, {}),
          ...(Number(values.extra_limit || 0) > 0 ? { limit: Number(values.extra_limit) } : {}),
          ...(String(values.extra_cta_text || '').trim()
            ? { cta_text: String(values.extra_cta_text).trim() }
            : {}),
        },
        sort: Number(values.sort || 0),
        is_enabled: values.is_enabled ? 1 : 0,
      };

      await updateHomepageSection(currentSectionId, payload);
      message.success('首页板块已更新。');
      await loadBootstrap(currentSectionId);
    } catch (error) {
      message.error(error.message || '保存首页板块失败。');
    } finally {
      setSavingSection(false);
    }
  }

  async function handleToggleSectionEnabled(section, checked) {
    try {
      await updateHomepageSectionStatus(Number(section.id), checked ? 1 : 0);
      message.success('板块状态已更新。');
      await loadBootstrap(currentSectionId);
    } catch (error) {
      message.error(error.message || '更新板块状态失败。');
    }
  }

  async function loadSourceOptions(sourceType) {
    setItemOptionsLoading(true);

    try {
      const data = await getHomepageSourceOptions(sourceType);
      setSourceOptions(Array.isArray(data.items) ? data.items : []);
    } catch (error) {
      setSourceOptions([]);
      message.error(error.message || '加载来源列表失败。');
    } finally {
      setItemOptionsLoading(false);
    }
  }

  function openAddItemModal() {
    const nextSourceType = preferredSourceType;
    itemAddForm.setFieldsValue({
      source_type: nextSourceType,
      source_id: undefined,
    });
    setSourceOptions([]);
    setEditingItemIndex(null);
    setCoverPreviewAsset(null);
    setItemEditorOpen(true);
    loadSourceOptions(nextSourceType);
  }

  function openEditItemModal(index) {
    const item = items[index];
    if (!item) {
      return;
    }

    setEditingItemIndex(index);
    itemEditForm.setFieldsValue({
      title_override_zh: item.title_override_zh || '',
      summary_override_zh: item.summary_override_zh || '',
      cover_asset_id: Number(item.cover_asset_id || 0),
      sort: Number(item.sort || 0),
      is_enabled: Number(item.is_enabled || 0) === 1,
    });
  }

  function closeItemModal() {
    setItemEditorOpen(false);
    setEditingItemIndex(null);
    setCoverPickerOpen(false);
    setCoverPreviewAsset(null);
    itemAddForm.resetFields();
    itemEditForm.resetFields();
  }

  async function handleAddItem() {
    const values = await itemAddForm.validateFields();

    if (
      items.some(
        (item) =>
          String(item.source_type || '') === String(values.source_type) &&
          Number(item.source_id || 0) === Number(values.source_id || 0),
      )
    ) {
      message.warning('该来源已经添加到当前板块。');
      return;
    }

    const sourceRecord =
      sourceOptions.find((item) => Number(item.id) === Number(values.source_id)) || null;
    const maxSort = items.reduce(
      (carry, item) => Math.max(carry, Number(item.sort || 0)),
      0,
    );

    setItems((current) =>
      current.concat([
        {
          id: 0,
          source_type: values.source_type,
          source_id: Number(values.source_id || 0),
          title_override_zh: '',
          summary_override_zh: '',
          cover_asset_id: 0,
          sort: maxSort + 10,
          is_enabled: 1,
          source_record: sourceRecord,
        },
      ]),
    );

    message.success('来源已加入当前板块，请记得保存项目。');
    closeItemModal();
  }

  async function handleSaveItemEdit() {
    if (editingItemIndex === null) {
      return;
    }

    const values = await itemEditForm.validateFields();

    setItems((current) =>
      current.map((item, index) =>
        index === editingItemIndex
          ? {
              ...item,
              title_override_zh: values.title_override_zh || '',
              summary_override_zh: values.summary_override_zh || '',
              cover_asset_id: Number(values.cover_asset_id || 0),
              sort: Number(values.sort || 0),
              is_enabled: values.is_enabled ? 1 : 0,
            }
          : item,
      ),
    );

    setEditingItemIndex(null);
    message.success('项目内容已更新，请保存板块项目。');
  }

  function handleRemoveItem(index) {
    setItems((current) => current.filter((_, itemIndex) => itemIndex !== index));
  }

  function handleMoveItem(index, direction) {
    setItems((current) => {
      const targetIndex = direction === 'up' ? index - 1 : index + 1;
      if (targetIndex < 0 || targetIndex >= current.length) {
        return current;
      }

      const next = current.slice();
      const temp = next[targetIndex];
      next[targetIndex] = next[index];
      next[index] = temp;
      return next.map((item, itemIndex, source) => ({
        ...item,
        sort: (source.length - itemIndex) * 10,
      }));
    });
  }

  function handleToggleItemEnabled(index, checked) {
    setItems((current) =>
      current.map((item, itemIndex) =>
        itemIndex === index
          ? {
              ...item,
              is_enabled: checked ? 1 : 0,
            }
          : item,
      ),
    );
  }

  async function handleSaveItems() {
    if (!currentSectionId) {
      return;
    }

    setSavingItems(true);

    try {
      const payload = items.map((item, index, source) => ({
        id: Number(item.id || 0) > 0 ? Number(item.id) : 0,
        source_type: String(item.source_type || ''),
        source_id: Number(item.source_id || 0),
        title_override_zh: item.title_override_zh || '',
        summary_override_zh: item.summary_override_zh || '',
        cover_asset_id: Number(item.cover_asset_id || 0),
        sort:
          Number(item.sort || 0) ||
          (source.length - index) * 10,
        is_enabled: Number(item.is_enabled || 0) === 1 ? 1 : 0,
      }));

      await updateHomepageSectionItems(currentSectionId, payload);
      message.success('首页板块项目已保存。');
      await loadBootstrap(currentSectionId);
    } catch (error) {
      message.error(error.message || '保存首页板块项目失败。');
    } finally {
      setSavingItems(false);
    }
  }

  async function handleOpenPreview() {
    setPreviewLoading(true);
    setPreviewOpen(true);

    try {
      const data = await getHomepagePreview();
      setPreviewPayload(data || null);
    } catch (error) {
      message.error(error.message || '加载首页预览失败。');
      setPreviewPayload(null);
    } finally {
      setPreviewLoading(false);
    }
  }

  async function handlePublishHomepage() {
    setPublishing(true);

    try {
      await publishHomepage();
      message.success('首页草稿已发布到线上。');
      await loadBootstrap(currentSectionId);
    } catch (error) {
      message.error(error.message || '发布首页失败。');
    } finally {
      setPublishing(false);
    }
  }

  async function handleRestoreHomepage() {
    setRestoring(true);

    try {
      await restoreHomepageLive();
      message.success('已根据线上快照恢复首页草稿。');
      await loadBootstrap(currentSectionId);
    } catch (error) {
      message.error(error.message || '恢复首页草稿失败。');
    } finally {
      setRestoring(false);
    }
  }

  const itemColumns = [
    {
      title: '来源内容',
      dataIndex: 'source_id',
      render: (_, record) => (
        <Space direction="vertical" size={2}>
          <Text strong>{buildItemLabel(record)}</Text>
          <Text type="secondary">
            {sourceTypeLabelMap[record.source_type] || record.source_type || '-'} / #{record.source_id || 0}
          </Text>
        </Space>
      ),
    },
    {
      title: '覆盖显示',
      render: (_, record) => (
        <Space direction="vertical" size={2}>
          <Text>{record.title_override_zh || '-'}</Text>
          <Text type="secondary">{record.summary_override_zh || '-'}</Text>
        </Space>
      ),
    },
    {
      title: '排序',
      dataIndex: 'sort',
      width: 90,
    },
    {
      title: '启用',
      dataIndex: 'is_enabled',
      width: 110,
      render: (value, record, index) => (
        <Switch
          size="small"
          checked={Number(value || 0) === 1}
          checkedChildren="开"
          unCheckedChildren="关"
          onChange={(checked) => handleToggleItemEnabled(index, checked)}
        />
      ),
    },
    {
      title: '操作',
      width: 230,
      render: (_, record, index) => (
        <Space wrap>
          <Button size="small" onClick={() => handleMoveItem(index, 'up')}>
            上移
          </Button>
          <Button size="small" onClick={() => handleMoveItem(index, 'down')}>
            下移
          </Button>
          <Button size="small" onClick={() => openEditItemModal(index)}>
            编辑
          </Button>
          <Button size="small" danger onClick={() => handleRemoveItem(index)}>
            删除
          </Button>
        </Space>
      ),
    },
  ];

  return (
    <PagePlaceholder
      hideHeader
      compact
      tags={[
        workflow?.has_unpublished_changes ? '有未发布改动' : '已与线上同步',
        workflow?.live_updated_at ? `线上发布时间 ${workflow.live_updated_at}` : '未发布',
      ]}
    >
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <div className="toolbar-surface">
        <Space wrap size={12} style={{ width: '100%', justifyContent: 'space-between' }}>
          <Space wrap>
            <Button onClick={handleOpenPreview} loading={previewLoading}>
              预览草稿
            </Button>
            <Popconfirm
              title="确认用当前线上快照恢复草稿吗？"
              okText="确认恢复"
              cancelText="取消"
              onConfirm={handleRestoreHomepage}
            >
              <Button loading={restoring}>恢复线上</Button>
            </Popconfirm>
            <Button onClick={() => setCreateOpen(true)}>新建板块</Button>
            <Button
              type="primary"
              loading={savingSection}
              disabled={!currentSectionId}
              onClick={() => sectionForm.submit()}
            >
              保存板块
            </Button>
            <Button
              type="primary"
              ghost
              loading={publishing}
              onClick={handlePublishHomepage}
            >
              发布首页
            </Button>
          </Space>
        </Space>
      </div>

      <Row gutter={16} align="stretch">
        <Col xs={24} lg={6}>
          <Card title="首页板块" size="small" style={{ height: '100%' }}>
            <Spin spinning={listLoading}>
              {sections.length ? (
                <List
                  dataSource={sections}
                  renderItem={(item) => (
                    <List.Item
                      actions={[
                        <Switch
                          key="enabled"
                          size="small"
                          checked={Number(item.is_enabled || 0) === 1}
                          checkedChildren="开"
                          unCheckedChildren="关"
                          onClick={(event) => event.stopPropagation()}
                          onChange={(checked) => handleToggleSectionEnabled(item, checked)}
                        />,
                      ]}
                      style={{
                        cursor: 'pointer',
                        borderRadius: 8,
                        padding: 12,
                        background:
                          Number(item.id) === Number(currentSectionId)
                            ? '#f0f5ff'
                            : 'transparent',
                      }}
                      onClick={() => loadSectionDetail(item.id)}
                    >
                      <Space direction="vertical" size={4} style={{ width: '100%' }}>
                        <Space
                          align="center"
                          style={{ width: '100%', justifyContent: 'space-between' }}
                        >
                          <Text strong>{getHomepageSectionDisplayTitle(item)}</Text>
                          {Number(item.is_enabled || 0) === 1 ? (
                            <Tag color="success">启用</Tag>
                          ) : (
                            <Tag>停用</Tag>
                          )}
                        </Space>
                        <Text type="secondary">
                          {item.section_key || '-'} / {fetchModeLabelMap[item.fetch_mode] || item.fetch_mode || '-'}
                        </Text>
                        <Space size={[6, 6]} wrap>
                          <Tag>{sectionTypeLabelMap[item.section_type] || item.section_type || '固定内容'}</Tag>
                          <Tag color="blue">
                            {(Array.isArray(item.items) ? item.items.length : item.item_count || 0)} 条内容
                          </Tag>
                        </Space>
                      </Space>
                    </List.Item>
                  )}
                />
              ) : (
                <Empty
                  image={Empty.PRESENTED_IMAGE_SIMPLE}
                  description="还没有首页板块"
                >
                  <Button type="primary" onClick={() => setCreateOpen(true)}>
                    新建第一个板块
                  </Button>
                </Empty>
              )}
            </Spin>
          </Card>
        </Col>

        <Col xs={24} lg={8}>
          <Card title="板块配置" size="small" style={{ height: '100%' }}>
            <Spin spinning={detailLoading}>
              {currentSectionId ? (
                <Form
                  form={sectionForm}
                  layout="vertical"
                  initialValues={getDefaultSectionValues()}
                  onFinish={handleSaveSection}
                >
                  <Alert
                    type="info"
                    showIcon
                    style={{ marginBottom: 16 }}
                    message="当前板块"
                    description={`${sectionGuide} ${fetchGuide} 当前内容：${items.length} 条。`}
                  />

                  <Form.Item
                    name="section_key"
                    label="板块标识"
                    extra="前台通过这个标识读取板块，建议保持稳定，例如 hero_banner、featured_products、customer_cases。"
                    rules={[{ required: true, message: '请输入板块标识。' }]}
                  >
                    <Input placeholder="例如：hero_banner" />
                  </Form.Item>

                  <Row gutter={16}>
                    <Col xs={24} md={12}>
                      <Form.Item name="section_type" label="板块类型">
                        <Select options={sectionTypeOptions} />
                      </Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                      <Form.Item name="fetch_mode" label="读取方式">
                        <Select options={fetchModeOptions} />
                      </Form.Item>
                    </Col>
                  </Row>

                  <Form.Item name="title_zh" label="板块标题">
                    <Input placeholder="前台显示标题" />
                  </Form.Item>

                  <Form.Item name="subtitle_zh" label="副标题">
                    <Input placeholder="可选内容" />
                  </Form.Item>

                  <Row gutter={16}>
                    <Col xs={24} md={12}>
                      <Form.Item name="sort" label="排序">
                        <Input type="number" />
                      </Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                      <Form.Item name="is_enabled" label="启用状态" valuePropName="checked">
                        <Switch checkedChildren="开" unCheckedChildren="关" />
                      </Form.Item>
                    </Col>
                  </Row>

                  {(showsItemLimit || showsCtaText) ? (
                    <Row gutter={16}>
                      {showsItemLimit ? (
                        <Col xs={24} md={12}>
                          <Form.Item
                            name="extra_limit"
                            label="数量限制"
                            extra="用于列表型板块，限制前台显示数量。"
                          >
                            <Input type="number" placeholder="可选" />
                          </Form.Item>
                        </Col>
                      ) : null}
                      {showsCtaText ? (
                        <Col xs={24} md={showsItemLimit ? 12 : 24}>
                          <Form.Item
                            name="extra_cta_text"
                            label="按钮文字"
                            extra="用于首屏或固定板块按钮。"
                          >
                            <Input placeholder="按钮文案" />
                          </Form.Item>
                        </Col>
                      ) : null}
                    </Row>
                  ) : null}

                  <Form.Item
                    name="extra_config"
                    label="高级扩展配置"
                    extra="用于少量特殊参数；常规情况下保持 {} 即可。"
                  >
                    <TextArea rows={8} />
                  </Form.Item>
                </Form>
              ) : (
                <Empty
                  image={Empty.PRESENTED_IMAGE_SIMPLE}
                  description="请先在左侧选择要编辑的板块"
                >
                  <Button type="primary" onClick={() => setCreateOpen(true)}>
                    新建板块
                  </Button>
                </Empty>
              )}
            </Spin>
          </Card>
        </Col>

        <Col xs={24} lg={10}>
          <Card
            title="板块内容"
            size="small"
            extra={
              <Space>
                <Button disabled={!currentSectionId || !canEditItems} onClick={openAddItemModal}>
                  添加内容
                </Button>
                <Button
                  type="primary"
                  loading={savingItems}
                  disabled={!currentSectionId || !canEditItems}
                  onClick={handleSaveItems}
                >
                  保存内容
                </Button>
              </Space>
            }
            style={{ height: '100%' }}
          >
            {!currentSectionId ? (
              <Empty
                image={Empty.PRESENTED_IMAGE_SIMPLE}
                description="请先选择一个板块"
              />
            ) : !canEditItems ? (
              <Alert
                type="info"
                showIcon
                message="当前板块无需单独维护内容"
                description="如果需要手动选择具体内容、排序和封面，请把读取方式改成“手动编排”。"
              />
            ) : (
              <Table
                rowKey={(record, index) => `${record.source_type}-${record.source_id}-${index}`}
                columns={itemColumns}
                dataSource={items}
                pagination={false}
                locale={{
                  emptyText: '当前板块暂无内容',
                }}
              />
            )}
          </Card>
        </Col>
      </Row>

      <Modal
        title="新建首页板块"
        open={createOpen}
        onCancel={() => {
          setCreateOpen(false);
          createForm.resetFields();
        }}
        onOk={() => createForm.submit()}
      >
        <Form
          form={createForm}
          layout="vertical"
          initialValues={{
            section_type: 'fixed_config',
            fetch_mode: 'fixed_config',
            sort: 100,
          }}
          onFinish={handleCreateSection}
        >
          <Form.Item name="template_key" label="快捷模板">
            <Select
              allowClear
              placeholder="选择板块模板"
              options={homepageSectionTemplates.map((item) => ({
                label: item.label,
                value: item.key,
              }))}
              onChange={(value) => applyHomepageTemplate(createForm, value)}
            />
          </Form.Item>
          <Form.Item
            name="section_key"
            label="板块标识"
            rules={[{ required: true, message: '请输入板块标识。' }]}
          >
            <Input placeholder="例如：homepage_notice" />
          </Form.Item>
          <Form.Item name="title_zh" label="标题">
            <Input />
          </Form.Item>
          <Row gutter={16}>
            <Col span={12}>
              <Form.Item name="section_type" label="板块类型">
                <Select options={sectionTypeOptions} />
              </Form.Item>
            </Col>
            <Col span={12}>
              <Form.Item name="fetch_mode" label="读取方式">
                <Select options={fetchModeOptions} />
              </Form.Item>
            </Col>
          </Row>
          <Form.Item name="sort" label="排序">
            <Input type="number" />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title={editingItemIndex === null ? '添加板块内容' : '编辑板块内容'}
        open={itemEditorOpen || editingItemIndex !== null}
        onCancel={() => {
          closeItemModal();
          setEditingItemIndex(null);
        }}
        onOk={() =>
          editingItemIndex === null ? handleAddItem() : handleSaveItemEdit()
        }
      >
        {editingItemIndex === null ? (
          <Form form={itemAddForm} layout="vertical">
            <Alert
              type="info"
              showIcon
              style={{ marginBottom: 16 }}
              message="手动编排内容"
              description={
                sourceTypeLocked
                  ? `当前板块固定使用“${sourceTypeLabelMap[preferredSourceType] || preferredSourceType}”内容，请选择一条已发布记录加入。`
                  : '请选择来源类型和需要加入当前板块的已发布内容。'
              }
            />
            <Form.Item name="source_type" label="来源类型">
              <Select
                options={sourceTypeOptions}
                disabled={sourceTypeLocked}
                onChange={(value) => {
                  itemAddForm.setFieldValue('source_id', undefined);
                  loadSourceOptions(value);
                }}
              />
            </Form.Item>
            <Form.Item
              name="source_id"
              label="来源记录"
              rules={[{ required: true, message: '请选择来源记录。' }]}
            >
              <Select
                loading={itemOptionsLoading}
                options={buildSourceOptions(sourceOptions)}
                showSearch
                optionFilterProp="label"
              />
            </Form.Item>
          </Form>
        ) : (
          <Form form={itemEditForm} layout="vertical">
            <Form.Item name="cover_asset_id" hidden>
              <Input />
            </Form.Item>

            <Form.Item name="title_override_zh" label="覆盖标题">
              <Input placeholder="留空则使用原始标题" />
            </Form.Item>
            <Form.Item name="summary_override_zh" label="覆盖摘要">
              <TextArea rows={4} placeholder="留空则使用原始摘要" />
            </Form.Item>
            <Form.Item label="封面图片">
              <div className="media-field-shell media-field-shell-wide">
                <div className="media-field-preview media-field-preview-wide">
                  {coverPreviewAsset?.file_path ? (
                    <Image
                      src={resolveAssetUrl(coverPreviewAsset.file_path)}
                      alt={coverPreviewAsset.file_name || '封面预览'}
                      width={200}
                      height={132}
                      style={{ objectFit: 'cover', borderRadius: 12 }}
                      preview
                    />
                  ) : (
                    <div className="media-field-empty media-field-empty-wide">
                      {coverPreviewLoading ? '加载中...' : '暂无预览'}
                    </div>
                  )}
                </div>
                <div className="media-field-meta">
                  <Space wrap>
                    <Button type="primary" onClick={() => setCoverPickerOpen(true)}>
                      {coverAssetId ? '更换图片' : '选择图片'}
                    </Button>
                    {coverAssetId ? (
                      <Button
                        onClick={() => {
                          itemEditForm.setFieldValue('cover_asset_id', 0);
                          setCoverPreviewAsset(null);
                        }}
                      >
                        移除
                      </Button>
                    ) : null}
                  </Space>
                  <Text type="secondary">
                    已选资源 ID：{coverAssetId || '未选择'}
                  </Text>
                  {coverPreviewAsset?.file_name ? (
                    <Space direction="vertical" size={2}>
                      <Text strong>{coverPreviewAsset.file_name}</Text>
                      <Text type="secondary">{coverPreviewAsset.file_path}</Text>
                    </Space>
                  ) : (
                    <Text type="secondary">
                      从资源中选择一张图片作为当前板块封面。
                    </Text>
                  )}
                </div>
              </div>
            </Form.Item>
            <Row gutter={16}>
              <Col span={12}>
                <Form.Item name="sort" label="排序">
                  <Input type="number" />
                </Form.Item>
              </Col>
              <Col span={12}>
                <Form.Item name="is_enabled" label="启用状态" valuePropName="checked">
                  <Switch checkedChildren="开" unCheckedChildren="关" />
                </Form.Item>
              </Col>
            </Row>
          </Form>
        )}
      </Modal>

      <MediaPickerModal
        open={coverPickerOpen}
        title="选择首页封面图"
        assetType="image"
        selectedAssetId={coverAssetId}
        onCancel={() => setCoverPickerOpen(false)}
        onSelect={(asset) => {
          itemEditForm.setFieldValue('cover_asset_id', Number(asset.id));
          setCoverPreviewAsset(asset);
          setCoverPickerOpen(false);
        }}
      />

      <Modal
        title="首页草稿预览"
        width={1120}
        open={previewOpen}
        footer={null}
        onCancel={() => setPreviewOpen(false)}
        destroyOnHidden
      >
        <Spin spinning={previewLoading}>
          <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Alert
              type="info"
              showIcon
              message="这里展示的是当前后台草稿数据。"
              description="发布前可在这里确认板块顺序、启用状态和内容数量是否正确。"
            />

            {(Array.isArray(previewPayload?.sections) ? previewPayload.sections : []).length > 0 ? (
              <Row gutter={[16, 16]}>
                {previewPayload.sections.map((section) => (
                  <Col xs={24} lg={12} key={section.id || section.section_key}>
                    <Card
                      size="small"
                      title={getHomepageSectionDisplayTitle(section)}
                      extra={Number(section.is_enabled || 0) === 1 ? <Tag color="success">启用</Tag> : <Tag>停用</Tag>}
                    >
                      <Space direction="vertical" size={8} style={{ width: '100%' }}>
                        <Text type="secondary">
                          {section.section_key || '-'} / {fetchModeLabelMap[section.fetch_mode] || section.fetch_mode || '-'}
                        </Text>
                        {section.subtitle_zh ? (
                          <Paragraph type="secondary" style={{ margin: 0 }}>
                            {section.subtitle_zh}
                          </Paragraph>
                        ) : null}
                        <Tag color="blue">
                          内容数：{Array.isArray(section.items) ? section.items.length : 0}
                        </Tag>
                        <List
                          size="small"
                          dataSource={(Array.isArray(section.items) ? section.items : []).slice(0, 4)}
                          locale={{ emptyText: '当前板块暂无内容' }}
                          renderItem={(item) => (
                            <List.Item>
                              <Space direction="vertical" size={2}>
                                <Text>{buildItemLabel(item)}</Text>
                                <Text type="secondary">
                                  {sourceTypeLabelMap[item.source_type] ||
                                    sectionTypeLabelMap[normalizeLegacySectionType(section.section_key, section.section_type)] ||
                                    item.source_type ||
                                    '固定内容'}
                                </Text>
                              </Space>
                            </List.Item>
                          )}
                        />
                      </Space>
                    </Card>
                  </Col>
                ))}
              </Row>
            ) : (
              <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="当前没有可预览的首页板块。" />
            )}
          </Space>
        </Spin>
      </Modal>
    </Space>
    </PagePlaceholder>
  );
}


