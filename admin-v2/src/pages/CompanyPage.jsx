import { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Button,
  Card,
  Col,
  Collapse,
  Empty,
  Form,
  Image,
  Input,
  InputNumber,
  List,
  Popconfirm,
  Row,
  Select,
  Space,
  Spin,
  Switch,
  Tag,
  Typography,
  message,
} from 'antd';
import PagePlaceholder from '@/components/PagePlaceholder';
import MediaPickerModal from '@/components/MediaPickerModal';
import { getResourceDetail } from '@/api/resources';
import { isImageAsset, isPdfAsset, isVideoAsset, resolveAssetUrl } from '@/utils/media';
import { getAboutBootstrap, getAboutPageDetail, getAboutPages, updateAboutPageBlocks } from '../api/about';

const { TextArea } = Input;
const { Paragraph, Text, Title } = Typography;

const blockTypeOptions = [
  { label: '文本', value: 'text' },
  { label: '视频', value: 'video' },
  { label: '图集', value: 'image_gallery' },
  { label: '证书', value: 'certificate_list' },
  { label: '团队', value: 'team_list' },
];

const blockTemplates = [
  {
    key: 'company_intro',
    label: '公司介绍',
    block_type: 'text',
    title_zh: '公司介绍',
    subtitle_zh: '',
    content_zh: '',
    extra_config: {
      layout: 'split',
      source: 'company_intro',
      cta_text: '了解更多',
      cta_link: '/about.html',
    },
  },
  {
    key: 'factory_video',
    label: '企业视频',
    block_type: 'video',
    title_zh: '企业视频',
    subtitle_zh: '',
    content_zh: '',
    extra_config: {
      layout: 'side-by-side',
      source: 'factory_video',
      autoplay: false,
      muted: false,
      loop: false,
    },
  },
  {
    key: 'company_gallery',
    label: '企业图集',
    block_type: 'image_gallery',
    title_zh: '企业图集',
    subtitle_zh: '',
    content_zh: '',
    extra_config: {
      layout: 'grid',
      columns: 3,
      limit: 6,
      source: 'company_gallery',
    },
  },
  {
    key: 'certificates',
    label: '资质证书',
    block_type: 'certificate_list',
    title_zh: '资质证书',
    subtitle_zh: '',
    content_zh: '',
    extra_config: {
      layout: 'grid',
      columns: 5,
      limit: 5,
      source: 'certificates',
    },
  },
  {
    key: 'sales_team',
    label: '销售团队',
    block_type: 'team_list',
    title_zh: '销售团队',
    subtitle_zh: '',
    content_zh: '',
    extra_config: {
      layout: 'carousel',
      columns: 3,
      limit: 6,
      source: 'team_members',
    },
  },
];

const blockTypeGuides = {
  text: '适合公司介绍和文案展示。',
  video: '适合企业视频展示。',
  image_gallery: '适合工厂图集和场景展示。',
  certificate_list: '适合资质证书展示。',
  team_list: '适合销售团队展示。',
};

const blockTypeLabelMap = {
  text: '文本介绍',
  video: '视频展示',
  image_gallery: '图集展示',
  certificate_list: '证书列表',
  team_list: '团队列表',
};

const STRUCTURED_EXTRA_CONFIG_KEYS = [
  'source',
  'asset_ids',
  'cover_asset_id',
  'video_url',
  'poster_url',
  'columns',
  'limit',
  'layout',
  'cta_text',
  'cta_link',
  'autoplay',
  'muted',
  'loop',
];

function formatJson(value) {
  if (!value) return '{}';
  if (typeof value === 'string') return value;
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return '{}';
  }
}

function parseJsonText(rawValue) {
  const source = String(rawValue || '').trim();
  if (!source) return {};
  return JSON.parse(source);
}

function normalizeExtraConfig(value) {
  if (!value) return {};
  if (typeof value === 'string') {
    const parsed = parseJsonText(value);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
  }
  return typeof value === 'object' && !Array.isArray(value) ? value : {};
}

function getDefaultSourceForBlockType(type) {
  if (type === 'certificate_list') return 'certificates';
  if (type === 'team_list') return 'team_members';
  return '';
}

function createDefaultBlock(type = 'text') {
  return {
    id: 0,
    block_type: type,
    title_zh: '',
    subtitle_zh: '',
    content_zh: '',
    extra_config: {},
    sort: 0,
    is_enabled: 1,
  };
}

function createBlockFromTemplate(templateKey) {
  const template = blockTemplates.find((item) => item.key === templateKey);
  if (!template) return createDefaultBlock();
  return {
    ...createDefaultBlock(template.block_type),
    block_type: template.block_type,
    title_zh: template.title_zh || '',
    subtitle_zh: template.subtitle_zh || '',
    content_zh: template.content_zh || '',
    extra_config: normalizeExtraConfig(template.extra_config),
  };
}

function parseAssetIdsText(value) {
  return String(value || '')
    .split(/[\n,]/)
    .map((item) => item.trim())
    .filter(Boolean)
    .map((item) => (/^\d+$/.test(item) ? Number(item) : item));
}

function buildStructuredExtraConfigValues(blockType, extraConfig) {
  const source =
    typeof extraConfig.source === 'string' && extraConfig.source.trim() !== ''
      ? extraConfig.source
      : getDefaultSourceForBlockType(blockType);

  return {
    source,
    asset_ids_text: Array.isArray(extraConfig.asset_ids) ? extraConfig.asset_ids.join(', ') : '',
    cover_asset_id:
      extraConfig.cover_asset_id === null || extraConfig.cover_asset_id === undefined
        ? null
        : Number(extraConfig.cover_asset_id) || null,
    video_url: extraConfig.video_url || '',
    poster_url: extraConfig.poster_url || '',
    columns:
      extraConfig.columns === null || extraConfig.columns === undefined
        ? null
        : Number(extraConfig.columns) || null,
    limit:
      extraConfig.limit === null || extraConfig.limit === undefined
        ? null
        : Number(extraConfig.limit) || null,
    layout: extraConfig.layout || '',
    cta_text: extraConfig.cta_text || '',
    cta_link: extraConfig.cta_link || '',
    autoplay: Boolean(extraConfig.autoplay),
    muted: Boolean(extraConfig.muted),
    loop: Boolean(extraConfig.loop),
  };
}

function applyStringField(target, key, value) {
  const normalized = String(value || '').trim();
  if (normalized) {
    target[key] = normalized;
    return;
  }
  delete target[key];
}

function applyNumberField(target, key, value) {
  if (value === null || value === undefined || value === '') {
    delete target[key];
    return;
  }
  target[key] = Number(value);
}

function applyStructuredExtraConfig(baseExtraConfig, values, blockType) {
  const nextExtraConfig = { ...baseExtraConfig };

  if (blockType === 'text') {
    applyStringField(nextExtraConfig, 'source', values.source);
    applyStringField(nextExtraConfig, 'layout', values.layout);
    applyStringField(nextExtraConfig, 'cta_text', values.cta_text);
    applyStringField(nextExtraConfig, 'cta_link', values.cta_link);
    applyNumberField(nextExtraConfig, 'cover_asset_id', values.cover_asset_id);
    applyNumberField(nextExtraConfig, 'limit', values.limit);
    return nextExtraConfig;
  }

  if (blockType === 'video') {
    applyStringField(nextExtraConfig, 'source', values.source);
    applyStringField(nextExtraConfig, 'video_url', values.video_url);
    applyStringField(nextExtraConfig, 'poster_url', values.poster_url);
    applyStringField(nextExtraConfig, 'layout', values.layout);
    applyNumberField(nextExtraConfig, 'cover_asset_id', values.cover_asset_id);
    nextExtraConfig.autoplay = Boolean(values.autoplay);
    nextExtraConfig.muted = Boolean(values.muted);
    nextExtraConfig.loop = Boolean(values.loop);
    return nextExtraConfig;
  }

  if (blockType === 'image_gallery' || blockType === 'certificate_list' || blockType === 'team_list') {
    applyStringField(nextExtraConfig, 'source', values.source);
    applyStringField(nextExtraConfig, 'layout', values.layout);
    applyNumberField(nextExtraConfig, 'cover_asset_id', values.cover_asset_id);
    applyNumberField(nextExtraConfig, 'columns', values.columns);
    applyNumberField(nextExtraConfig, 'limit', values.limit);

    const assetIds = parseAssetIdsText(values.asset_ids_text);
    if (assetIds.length) {
      nextExtraConfig.asset_ids = assetIds;
    } else {
      delete nextExtraConfig.asset_ids;
    }
  }

  return nextExtraConfig;
}

export default function CompanyPage() {
  const [pages, setPages] = useState([]);
  const [currentPageId, setCurrentPageId] = useState(null);
  const [currentPage, setCurrentPage] = useState(null);
  const [currentBlockId, setCurrentBlockId] = useState(null);
  const [listLoading, setListLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [mediaPickerOpen, setMediaPickerOpen] = useState(false);
  const [mediaPickerMode, setMediaPickerMode] = useState('cover');
  const [coverAssetPreview, setCoverAssetPreview] = useState(null);
  const [videoAssetPreview, setVideoAssetPreview] = useState(null);
  const [posterAssetPreview, setPosterAssetPreview] = useState(null);
  const [galleryAssetPreviews, setGalleryAssetPreviews] = useState([]);
  const [selectedTemplateKey, setSelectedTemplateKey] = useState();
  const [form] = Form.useForm();

  const currentBlockType = Form.useWatch('block_type', form) || 'text';
  const coverAssetId = Form.useWatch('cover_asset_id', form);
  const videoUrl = Form.useWatch('video_url', form);
  const posterUrl = Form.useWatch('poster_url', form);
  const assetIdsText = Form.useWatch('asset_ids_text', form);
  const currentBlockGuide = blockTypeGuides[currentBlockType] || blockTypeGuides.text;

  function getCurrentBlocks(page) {
    return Array.isArray(page?.blocks) ? page.blocks : [];
  }

  function findBlockById(page, blockId) {
    return getCurrentBlocks(page).find((item) => Number(item.id) === Number(blockId)) || null;
  }

  function setBlockFormValues(block) {
    const resolvedBlockType = block?.block_type || 'text';
    const normalizedExtraConfig = normalizeExtraConfig(block?.extra_config);

    form.setFieldsValue({
      block_type: resolvedBlockType,
      title_zh: block?.title_zh || '',
      subtitle_zh: block?.subtitle_zh || '',
      content_zh: block?.content_zh || '',
      extra_config_json: formatJson(normalizedExtraConfig),
      sort: Number(block?.sort || 0),
      is_enabled: Number(block?.is_enabled || 0) === 1,
      ...buildStructuredExtraConfigValues(resolvedBlockType, normalizedExtraConfig),
    });
  }

  function openMediaPicker(mode) {
    setMediaPickerMode(mode);
    setMediaPickerOpen(true);
  }

  function clearPreviewStateForBlock() {
    setCoverAssetPreview(null);
    setVideoAssetPreview(null);
    setPosterAssetPreview(null);
    setGalleryAssetPreviews([]);
  }

  function updateGalleryIds(nextIds) {
    const normalized = Array.from(new Set(nextIds.map((item) => Number(item)).filter((item) => item > 0)));
    form.setFieldValue('asset_ids_text', normalized.join(', '));
  }

  async function handleMediaSelect(asset) {
    if (!asset) return;

    if (mediaPickerMode === 'cover') {
      form.setFieldValue('cover_asset_id', Number(asset.id || 0) || null);
      setCoverAssetPreview(asset);
    } else if (mediaPickerMode === 'video') {
      form.setFieldValue('video_url', asset.file_path || '');
      setVideoAssetPreview(asset);
    } else if (mediaPickerMode === 'poster') {
      form.setFieldValue('poster_url', asset.file_path || '');
      setPosterAssetPreview(asset);
    } else if (mediaPickerMode === 'gallery') {
      const currentIds = parseAssetIdsText(form.getFieldValue('asset_ids_text'));
      updateGalleryIds(currentIds.concat([Number(asset.id || 0)]));
      setGalleryAssetPreviews((current) => {
        const next = [...current];
        if (!next.some((item) => Number(item.id) === Number(asset.id))) {
          next.push(asset);
        }
        return next;
      });
    }

    setMediaPickerOpen(false);
  }

  function removeGalleryAsset(assetId) {
    const currentIds = parseAssetIdsText(form.getFieldValue('asset_ids_text'));
    updateGalleryIds(currentIds.filter((item) => Number(item) !== Number(assetId)));
    setGalleryAssetPreviews((current) => current.filter((item) => Number(item.id) !== Number(assetId)));
  }

  function renderAssetPreviewCard(asset, title, onPick, onClear, extra) {
    let previewNode = <Text type="secondary">尚未选择资源。</Text>;

    if (asset) {
      if (isImageAsset(asset)) {
        previewNode = (
          <Image
            src={resolveAssetUrl(asset.thumbnail_url || asset.file_path || '')}
            alt={asset.file_name || title}
            width={72}
            height={72}
            style={{ objectFit: 'cover', borderRadius: 12 }}
            preview={false}
          />
        );
      } else if (isVideoAsset(asset)) {
        previewNode = <div className="company-asset-fallback company-asset-fallback-video"><Text strong>视频</Text></div>;
      } else if (isPdfAsset(asset)) {
        previewNode = <div className="company-asset-fallback company-asset-fallback-pdf"><Text strong>证书</Text></div>;
      } else {
        previewNode = <div className="company-asset-fallback"><Text strong>素材</Text></div>;
      }
    }

    return (
      <Card size="small" className="page-card" styles={{ body: { padding: 12 } }}>
        <Space direction="vertical" size={10} style={{ width: '100%' }}>
          <Space align="start" style={{ width: '100%', justifyContent: 'space-between' }}>
            <Text strong>{title}</Text>
            <Space size={8}>
              <Button size="small" onClick={onPick}>选择</Button>
              {onClear ? <Button size="small" onClick={onClear}>清空</Button> : null}
            </Space>
          </Space>
          {asset ? (
            <Space align="start" size={12} style={{ width: '100%' }}>
              {previewNode}
              <Space direction="vertical" size={2} style={{ minWidth: 0 }}>
                <Text ellipsis>{asset.original_name || asset.file_name || `资源 #${asset.id}`}</Text>
                <Text type="secondary">资源 ID：{asset.id}</Text>
                {extra}
              </Space>
            </Space>
          ) : (
            <Text type="secondary">尚未选择资源。</Text>
          )}
        </Space>
      </Card>
    );
  }

  function readBlockPayload() {
    const values = form.getFieldsValue();
    const baseExtraConfig = normalizeExtraConfig(parseJsonText(values.extra_config_json));
    const mergedExtraConfig = applyStructuredExtraConfig(
      baseExtraConfig,
      values,
      values.block_type || 'text',
    );

    return {
      block_type: values.block_type || 'text',
      title_zh: values.title_zh || '',
      subtitle_zh: values.subtitle_zh || '',
      content_zh: values.content_zh || '',
      extra_config: mergedExtraConfig,
      sort: Number(values.sort || 0),
      is_enabled: values.is_enabled ? 1 : 0,
    };
  }

  function mergeCurrentBlock(page) {
    if (!page || !currentBlockId) return page;
    return {
      ...page,
      blocks: getCurrentBlocks(page).map((item) =>
        Number(item.id) === Number(currentBlockId) ? { ...item, ...readBlockPayload() } : item,
      ),
    };
  }

  async function loadAboutPageDetail(pageId) {
    if (!pageId) {
      setCurrentPageId(null);
      setCurrentPage(null);
      setCurrentBlockId(null);
      setBlockFormValues(createDefaultBlock());
      return;
    }

    setDetailLoading(true);
    try {
      const detail = await getAboutPageDetail(pageId);
      const blocks = getCurrentBlocks(detail);
      const nextBlockId = blocks[0] ? Number(blocks[0].id) : null;
      setCurrentPageId(Number(pageId));
      setCurrentPage(detail);
      setCurrentBlockId(nextBlockId);
      setBlockFormValues(blocks[0] || createDefaultBlock());
    } catch (error) {
      message.error(error.message || '加载关于页面详情失败。');
    } finally {
      setDetailLoading(false);
    }
  }

  async function loadAboutPages(preferredId = currentPageId) {
    setListLoading(true);
    try {
      const data = await getAboutBootstrap({
        preferred_id: Number(preferredId || 0) || undefined,
      });
      const nextPages = Array.isArray(data?.pages) ? data.pages : [];
      setPages(nextPages);

      if (!nextPages.length) {
        setCurrentPageId(null);
        setCurrentPage(null);
        setCurrentBlockId(null);
        setBlockFormValues(createDefaultBlock());
        return;
      }

      const detail = data?.detail || null;
      const nextId = Number(data?.current_id || nextPages[0]?.id || 0);
      const blocks = getCurrentBlocks(detail);
      const nextBlockId = blocks[0] ? Number(blocks[0].id) : null;

      setCurrentPageId(nextId || null);
      setCurrentPage(detail);
      setCurrentBlockId(nextBlockId);
      setBlockFormValues(blocks[0] || createDefaultBlock());
    } catch (error) {
      message.error(error.message || '加载关于页面列表失败。');
    } finally {
      setListLoading(false);
    }
  }

  useEffect(() => {
    loadAboutPages();
  }, []);

  useEffect(() => {
    clearPreviewStateForBlock();
  }, [currentBlockId]);

  useEffect(() => {
    const assetId = Number(coverAssetId || 0);
    if (!assetId) {
      setCoverAssetPreview(null);
      return;
    }

    let disposed = false;
    getResourceDetail(assetId)
      .then((asset) => {
        if (!disposed) setCoverAssetPreview(asset || null);
      })
      .catch(() => {
        if (!disposed) setCoverAssetPreview(null);
      });

    return () => {
      disposed = true;
    };
  }, [coverAssetId]);

  useEffect(() => {
    const ids = parseAssetIdsText(assetIdsText).filter((item) => Number(item) > 0).slice(0, 8);
    if (!ids.length) {
      setGalleryAssetPreviews([]);
      return;
    }

    let disposed = false;
    Promise.all(ids.map((id) => getResourceDetail(Number(id)).catch(() => null)))
      .then((assets) => {
        if (!disposed) {
          setGalleryAssetPreviews(assets.filter(Boolean));
        }
      })
      .catch(() => {
        if (!disposed) setGalleryAssetPreviews([]);
      });

    return () => {
      disposed = true;
    };
  }, [assetIdsText]);

  useEffect(() => {
    if (videoAssetPreview && videoAssetPreview.file_path !== videoUrl) setVideoAssetPreview(null);
  }, [videoAssetPreview, videoUrl]);

  useEffect(() => {
    if (posterAssetPreview && posterAssetPreview.file_path !== posterUrl) setPosterAssetPreview(null);
  }, [posterAssetPreview, posterUrl]);

  function handleSelectBlock(blockId) {
    try {
      const syncedPage = mergeCurrentBlock(currentPage);
      setCurrentPage(syncedPage);
      setCurrentBlockId(Number(blockId));
      setBlockFormValues(findBlockById(syncedPage, blockId) || createDefaultBlock());
    } catch (error) {
      message.error(error.message || '切换区块失败，请稍后重试。');
    }
  }

  function handleAddBlock() {
    try {
      const syncedPage = mergeCurrentBlock(currentPage);
      const nextBlocks = getCurrentBlocks(syncedPage).concat([
        {
          ...createDefaultBlock(currentBlockType),
          id: Date.now() * -1,
          block_type: currentBlockType,
        },
      ]);
      const nextPage = { ...syncedPage, blocks: nextBlocks };
      const nextBlock = nextBlocks[nextBlocks.length - 1];
      setCurrentPage(nextPage);
      setCurrentBlockId(nextBlock.id);
      setBlockFormValues(nextBlock);
    } catch (error) {
      message.error(error.message || '新增区块失败，请稍后重试。');
    }
  }

  function handleAddBlockFromTemplate(templateKey) {
    if (!currentPageId || !templateKey) return;

    try {
      const syncedPage = mergeCurrentBlock(currentPage);
      const nextBlock = {
        ...createBlockFromTemplate(templateKey),
        id: Date.now() * -1,
      };
      const nextBlocks = getCurrentBlocks(syncedPage).concat([nextBlock]);
      const nextPage = { ...syncedPage, blocks: nextBlocks };
      setCurrentPage(nextPage);
      setCurrentBlockId(nextBlock.id);
      setBlockFormValues(nextBlock);
      setSelectedTemplateKey(undefined);
      message.success('模板区块已添加');
    } catch (error) {
      message.error(error.message || '添加模板区块失败。');
    }
  }

  function handleApplyTemplateToCurrentBlock(templateKey) {
    if (!templateKey) return;

    const templateBlock = createBlockFromTemplate(templateKey);
    const currentValues = form.getFieldsValue();
    const mergedBlock = {
      ...templateBlock,
      title_zh: currentValues.title_zh || templateBlock.title_zh,
      subtitle_zh: currentValues.subtitle_zh || templateBlock.subtitle_zh,
      content_zh:
        String(currentValues.content_zh || '').trim() !== ''
          ? currentValues.content_zh
          : templateBlock.content_zh,
      sort: Number(currentValues.sort || 0),
      is_enabled: currentValues.is_enabled ? 1 : 0,
    };

    setBlockFormValues(mergedBlock);
    setSelectedTemplateKey(templateKey);
    message.success('模板已应用');
  }

  function moveBlock(direction) {
    if (!currentPage || !currentBlockId) return;

    try {
      const syncedPage = mergeCurrentBlock(currentPage);
      const blocks = getCurrentBlocks(syncedPage).slice();
      const index = blocks.findIndex((item) => Number(item.id) === Number(currentBlockId));
      const targetIndex = direction === 'up' ? index - 1 : index + 1;
      if (index < 0 || targetIndex < 0 || targetIndex >= blocks.length) return;
      const temp = blocks[targetIndex];
      blocks[targetIndex] = blocks[index];
      blocks[index] = temp;
      setCurrentPage({ ...syncedPage, blocks });
    } catch (error) {
      message.error(error.message || '移动区块失败，请稍后重试。');
    }
  }

  function deleteBlock() {
    if (!currentPage || !currentBlockId) return;

    try {
      const syncedPage = mergeCurrentBlock(currentPage);
      const nextBlocks = getCurrentBlocks(syncedPage).filter(
        (item) => Number(item.id) !== Number(currentBlockId),
      );
      const nextBlock = nextBlocks[0] || null;
      setCurrentPage({ ...syncedPage, blocks: nextBlocks });
      setCurrentBlockId(nextBlock ? Number(nextBlock.id) : null);
      setBlockFormValues(nextBlock || createDefaultBlock());
    } catch (error) {
      message.error(error.message || '删除区块失败，请稍后重试。');
    }
  }

  async function handleSaveBlocks() {
    if (!currentPageId || !currentPage) return;

    setSaving(true);
    try {
      const syncedPage = mergeCurrentBlock(currentPage);
      const blocks = getCurrentBlocks(syncedPage).map((item, index, source) => ({
        id: Number(item.id || 0) > 0 ? Number(item.id) : 0,
        block_type: item.block_type || 'text',
        title_zh: item.title_zh || '',
        subtitle_zh: item.subtitle_zh || '',
        content_zh: item.content_zh || '',
        extra_config: normalizeExtraConfig(item.extra_config),
        sort: Number(item.sort || 0) || (source.length - index) * 10,
        is_enabled: Number(item.is_enabled || 0) === 1 ? 1 : 0,
      }));

      const updated = await updateAboutPageBlocks(currentPageId, blocks);
      const updatedBlocks = getCurrentBlocks(updated);
      const nextIndex = Math.max(
        0,
        Math.min(
          getCurrentBlocks(syncedPage).findIndex((item) => Number(item.id) === Number(currentBlockId)),
          Math.max(0, updatedBlocks.length - 1),
        ),
      );
      const nextBlock = updatedBlocks[nextIndex] || updatedBlocks[0] || null;

      setCurrentPage(updated);
      setCurrentBlockId(nextBlock ? Number(nextBlock.id) : null);
      setBlockFormValues(nextBlock || createDefaultBlock());
      await loadAboutPages(currentPageId);
      message.success('关于页面区块已保存');
    } catch (error) {
      message.error(error.message || '保存关于页面区块失败。');
    } finally {
      setSaving(false);
    }
  }

  function renderStructuredExtraConfigEditor() {
    if (currentBlockType === 'text') {
      return (
        <Row gutter={16}>
          <Col xs={24} md={12}>
            <Form.Item name="source" label="数据来源键">
              <Input placeholder="例如：company_intro" />
            </Form.Item>
          </Col>
          <Col xs={24} md={12}>
            <Form.Item name="layout" label="展示布局">
              <Input placeholder="例如：hero、split、stacked" />
            </Form.Item>
          </Col>
          <Col xs={24} md={12}>
            <Form.Item name="cta_text" label="按钮文案">
              <Input placeholder="例如：了解更多" />
            </Form.Item>
          </Col>
          <Col xs={24} md={12}>
            <Form.Item name="cta_link" label="按钮链接">
              <Input placeholder="/about.html" />
            </Form.Item>
          </Col>
          <Col xs={24} md={12}>
            <Form.Item name="cover_asset_id" label="主图资源 ID">
              <InputNumber min={0} precision={0} style={{ width: '100%' }} />
            </Form.Item>
          </Col>
          <Col xs={24} md={12}>
            <Form.Item name="limit" label="数量限制">
              <InputNumber min={0} precision={0} style={{ width: '100%' }} />
            </Form.Item>
          </Col>
          <Col xs={24}>
            {renderAssetPreviewCard(
              coverAssetPreview,
              '主图资源',
              () => openMediaPicker('cover'),
              () => {
                form.setFieldValue('cover_asset_id', null);
                setCoverAssetPreview(null);
              },
              <Text type="secondary">作为该区块的主视觉内容。</Text>,
            )}
          </Col>
        </Row>
      );
    }

    if (currentBlockType === 'video') {
      return (
        <Row gutter={16}>
          <Col xs={24} md={12}>
            <Form.Item name="source" label="数据来源键">
              <Input placeholder="例如：factory_video" />
            </Form.Item>
          </Col>
          <Col xs={24} md={12}>
            <Form.Item name="layout" label="展示布局">
              <Input placeholder="例如：full、side_by_side" />
            </Form.Item>
          </Col>
          <Col xs={24} md={12}>
            <Form.Item name="video_url" label="视频地址">
              <Input placeholder="视频地址" />
            </Form.Item>
          </Col>
          <Col xs={24} md={12}>
            <Form.Item name="poster_url" label="封面地址">
              <Input placeholder="封面图片地址" />
            </Form.Item>
          </Col>
          <Col xs={24} md={12}>
            <Form.Item name="cover_asset_id" label="封面资源 ID">
              <InputNumber min={0} precision={0} style={{ width: '100%' }} />
            </Form.Item>
          </Col>
          <Col xs={24} md={4}>
            <Form.Item name="autoplay" label="自动播放" valuePropName="checked">
              <Switch checkedChildren="是" unCheckedChildren="否" />
            </Form.Item>
          </Col>
          <Col xs={24} md={4}>
            <Form.Item name="muted" label="静音" valuePropName="checked">
              <Switch checkedChildren="是" unCheckedChildren="否" />
            </Form.Item>
          </Col>
          <Col xs={24} md={4}>
            <Form.Item name="loop" label="循环" valuePropName="checked">
              <Switch checkedChildren="是" unCheckedChildren="否" />
            </Form.Item>
          </Col>
          <Col xs={24} md={12}>
            {renderAssetPreviewCard(
              videoAssetPreview,
              '视频资源',
              () => openMediaPicker('video'),
              () => {
                form.setFieldValue('video_url', '');
                setVideoAssetPreview(null);
              },
              videoUrl ? <Text type="secondary">{videoUrl}</Text> : null,
            )}
          </Col>
          <Col xs={24} md={12}>
            {renderAssetPreviewCard(
              posterAssetPreview || coverAssetPreview,
              '封面资源',
              () => openMediaPicker('poster'),
              () => {
                form.setFieldValue('poster_url', '');
                setPosterAssetPreview(null);
              },
              posterUrl ? <Text type="secondary">{posterUrl}</Text> : null,
            )}
          </Col>
        </Row>
      );
    }

    return (
      <Row gutter={16}>
        <Col xs={24} md={12}>
          <Form.Item name="source" label="数据来源键">
            <Input placeholder="例如：certificates、team_members" />
          </Form.Item>
        </Col>
        <Col xs={24} md={12}>
          <Form.Item name="layout" label="展示布局">
            <Input placeholder="例如：grid、slider、list" />
          </Form.Item>
        </Col>
        <Col xs={24} md={12}>
          <Form.Item name="columns" label="列数">
            <InputNumber min={0} precision={0} style={{ width: '100%' }} />
          </Form.Item>
        </Col>
        <Col xs={24} md={12}>
          <Form.Item name="limit" label="条数限制">
            <InputNumber min={0} precision={0} style={{ width: '100%' }} />
          </Form.Item>
        </Col>
        <Col xs={24} md={12}>
          <Form.Item name="cover_asset_id" label="封面资源 ID">
            <InputNumber min={0} precision={0} style={{ width: '100%' }} />
          </Form.Item>
        </Col>
        <Col xs={24} md={12}>
          <Form.Item name="asset_ids_text" label="资源 ID 列表">
            <Input placeholder="例如：12、18、25" />
          </Form.Item>
        </Col>
        <Col xs={24}>
          <Card size="small" className="page-card" styles={{ body: { padding: 12 } }}>
            <Space direction="vertical" size={12} style={{ width: '100%' }}>
              <Space align="center" style={{ width: '100%', justifyContent: 'space-between' }}>
                <Text strong>图集资源</Text>
                <Space size={8}>
                  <Button size="small" onClick={() => openMediaPicker('gallery')}>添加资源</Button>
                  <Button
                    size="small"
                    onClick={() => {
                      form.setFieldValue('asset_ids_text', '');
                      setGalleryAssetPreviews([]);
                    }}
                  >
                    清空全部
                  </Button>
                </Space>
              </Space>
              {galleryAssetPreviews.length ? (
                <div className="company-gallery-preview-grid">
                  {galleryAssetPreviews.map((asset) => (
                    <Card
                      key={asset.id}
                      size="small"
                      className="company-gallery-preview-card"
                      styles={{ body: { padding: 10 } }}
                    >
                      <Space direction="vertical" size={8} style={{ width: '100%' }}>
                        <Image
                          src={resolveAssetUrl(asset.thumbnail_url || asset.file_path || '')}
                          alt={asset.file_name || `Asset #${asset.id}`}
                          width="100%"
                          height={120}
                          style={{ objectFit: 'cover', borderRadius: 10 }}
                          preview={false}
                        />
                        <Text ellipsis>{asset.original_name || asset.file_name || `资源 #${asset.id}`}</Text>
                        <Space align="center" style={{ width: '100%', justifyContent: 'space-between' }}>
                          <Tag>ID {asset.id}</Tag>
                          <Button size="small" danger onClick={() => removeGalleryAsset(asset.id)}>
                            删除
                          </Button>
                        </Space>
                      </Space>
                    </Card>
                  ))}
                </div>
              ) : (
                <Text type="secondary">尚未选择图集资源。</Text>
              )}
            </Space>
          </Card>
        </Col>
      </Row>
    );
  }

  const blocks = getCurrentBlocks(currentPage);
  const structuredKeySummary = STRUCTURED_EXTRA_CONFIG_KEYS.join(', ');
  const enabledBlocksCount = blocks.filter((item) => Number(item.is_enabled || 0) === 1).length;
  const currentBlock = findBlockById(currentPage, currentBlockId);

  return (
    <PagePlaceholder
      hideHeader
      compact
      tags={[`${pages.length} 个页面`, `${blocks.length} 个区块`, `${enabledBlocksCount} 个启用`]}
    >
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
        <Card>
          <Space align="start" style={{ width: '100%', justifyContent: 'space-between' }} wrap>
            <div>
              <Title level={3} style={{ margin: 0 }}>公司介绍</Title>
              <Paragraph type="secondary" style={{ margin: '8px 0 0' }}>
                统一维护公司介绍、企业视频、图集、资质证书和销售团队内容。
              </Paragraph>
            </div>
            <Space wrap>
              <Tag color="blue">{pages.length} 个页面</Tag>
              <Tag color="purple">{blocks.length} 个区块</Tag>
              <Tag color="success">{enabledBlocksCount} 个启用</Tag>
              <Button type="primary" loading={saving} disabled={!currentPageId} onClick={handleSaveBlocks}>
                保存区块
              </Button>
            </Space>
          </Space>
        </Card>

        <Row gutter={16} align="stretch">
          <Col xs={24} lg={6}>
            <Card title="页面列表" size="small" style={{ height: '100%' }}>
              <Spin spinning={listLoading}>
                {pages.length ? (
                  <List
                    dataSource={pages}
                    renderItem={(item) => (
                      <List.Item
                        style={{
                          cursor: 'pointer',
                          borderRadius: 8,
                          padding: 12,
                          background: Number(item.id) === Number(currentPageId) ? '#f0f5ff' : 'transparent',
                        }}
                        onClick={() => loadAboutPageDetail(item.id)}
                      >
                        <Space direction="vertical" size={4} style={{ width: '100%' }}>
                          <Space align="center" style={{ width: '100%', justifyContent: 'space-between' }}>
                            <Text strong>{item.name_zh || `页面 #${item.id}`}</Text>
                            {Number(item.is_enabled || 0) === 1 ? <Tag color="success">启用</Tag> : <Tag>停用</Tag>}
                          </Space>
                          <Text type="secondary">页面标识：{item.page_key || '-'}</Text>
                        </Space>
                      </List.Item>
                    )}
                  />
                ) : (
                  <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="暂无公司介绍页面" />
                )}
              </Spin>
            </Card>
          </Col>

          <Col xs={24} lg={7}>
            <Card
              title="区块列表"
              size="small"
              extra={
                <Space>
                  <Button size="small" onClick={handleAddBlock} disabled={!currentPageId}>新增</Button>
                  <Button size="small" onClick={() => moveBlock('up')} disabled={!currentBlockId}>上移</Button>
                  <Button size="small" onClick={() => moveBlock('down')} disabled={!currentBlockId}>下移</Button>
                </Space>
              }
              style={{ height: '100%' }}
            >
              <Spin spinning={detailLoading}>
                <Space direction="vertical" size={12} style={{ width: '100%', marginBottom: 16 }}>
                  <Select
                    allowClear
                    placeholder="选择区块模板"
                    value={selectedTemplateKey}
                    options={blockTemplates.map((item) => ({ label: item.label, value: item.key }))}
                    onChange={handleAddBlockFromTemplate}
                    disabled={!currentPageId}
                  />
                  <Text type="secondary">模板包含公司介绍、视频、图集、证书和销售团队。</Text>
                </Space>
                {blocks.length ? (
                  <List
                    dataSource={blocks}
                    renderItem={(item) => (
                      <List.Item
                        actions={[
                          <Popconfirm
                            key="delete"
                            title="删除当前区块？"
                            okText="删除"
                            cancelText="取消"
                            onConfirm={deleteBlock}
                            disabled={Number(item.id) !== Number(currentBlockId)}
                          >
                            <Button size="small" danger disabled={Number(item.id) !== Number(currentBlockId)}>
                              删除
                            </Button>
                          </Popconfirm>,
                        ]}
                        style={{
                          cursor: 'pointer',
                          borderRadius: 8,
                          padding: 12,
                          background: Number(item.id) === Number(currentBlockId) ? '#f0f5ff' : 'transparent',
                        }}
                        onClick={() => handleSelectBlock(item.id)}
                      >
                        <Space direction="vertical" size={4} style={{ width: '100%' }}>
                          <Space align="center" style={{ width: '100%', justifyContent: 'space-between' }}>
                            <Text strong>{item.title_zh || blockTypeLabelMap[item.block_type] || '区块'}</Text>
                            {Number(item.is_enabled || 0) === 1 ? <Tag color="success">启用</Tag> : <Tag>停用</Tag>}
                          </Space>
                          <Text type="secondary">{blockTypeLabelMap[item.block_type] || item.block_type || '-'}</Text>
                        </Space>
                      </List.Item>
                    )}
                  />
                ) : (
                  <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="当前页面还没有区块">
                    <Button type="primary" onClick={handleAddBlock} disabled={!currentPageId}>
                      创建首个区块
                    </Button>
                  </Empty>
                )}
              </Spin>
            </Card>
          </Col>

          <Col xs={24} lg={11}>
            <Card title="区块编辑" size="small" style={{ height: '100%' }}>
              <Form
                form={form}
                layout="vertical"
                initialValues={{
                  block_type: 'text',
                  title_zh: '',
                  subtitle_zh: '',
                  content_zh: '',
                  extra_config_json: '{}',
                  source: '',
                  asset_ids_text: '',
                  cover_asset_id: null,
                  video_url: '',
                  poster_url: '',
                  columns: null,
                  limit: null,
                  layout: '',
                  cta_text: '',
                  cta_link: '',
                  autoplay: false,
                  muted: false,
                  loop: false,
                  sort: 0,
                  is_enabled: true,
                }}
              >
                <Spin spinning={detailLoading}>
                  <Alert
                    type="info"
                    showIcon
                    style={{ marginBottom: 16 }}
                    message={currentBlock ? `正在编辑：${currentBlock.title_zh || blockTypeLabelMap[currentBlock.block_type] || '区块'}` : '区块编辑'}
                    description={`${currentBlockGuide}。下方结构化字段适合日常维护，保存时会优先合并到区块数据中。`}
                  />

                  <Form.Item label="模板">
                    <Select
                      allowClear
                      placeholder="选择并应用模板"
                      options={blockTemplates.map((item) => ({ label: item.label, value: item.key }))}
                      onChange={handleApplyTemplateToCurrentBlock}
                    />
                  </Form.Item>

                  <Row gutter={16}>
                    <Col xs={24} md={12}>
                      <Form.Item name="block_type" label="区块类型">
                        <Select options={blockTypeOptions} />
                      </Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                      <Form.Item name="sort" label="排序">
                        <InputNumber min={0} precision={0} style={{ width: '100%' }} />
                      </Form.Item>
                    </Col>
                  </Row>

                  <Form.Item name="title_zh" label="标题" extra="显示在该区块最前面的标题。">
                    <Input placeholder="请输入标题" />
                  </Form.Item>

                  <Form.Item name="subtitle_zh" label="副标题" extra="可选。">
                    <Input placeholder="请输入副标题" />
                  </Form.Item>

                  <Form.Item name="content_zh" label="内容" extra="文本区块使用正文内容。">
                    <TextArea rows={10} />
                  </Form.Item>

                  <Alert
                    type="info"
                    showIcon
                    message="结构化编辑"
                    description="下方为常用字段，可直接维护页面展示内容。"
                    style={{ marginBottom: 16 }}
                  />

                  {renderStructuredExtraConfigEditor()}

                  <Collapse
                    items={[
                      {
                        key: 'advanced-json',
                        label: '备用配置',
                        children: (
                          <Space direction="vertical" size={12} style={{ width: '100%' }}>
                            <Paragraph type="secondary" style={{ margin: 0 }}>
                              结构化字段保存时会覆盖这些已知键：{structuredKeySummary}
                            </Paragraph>
                            <Form.Item name="extra_config_json" label="高级扩展配置" style={{ marginBottom: 0 }}>
                              <TextArea rows={8} />
                            </Form.Item>
                          </Space>
                        ),
                      },
                    ]}
                  />

                  <Form.Item name="is_enabled" label="启用" valuePropName="checked" style={{ marginTop: 16 }}>
                    <Switch checkedChildren="启用" unCheckedChildren="停用" />
                  </Form.Item>
                </Spin>
              </Form>
            </Card>
          </Col>
        </Row>
        </Space>

      <MediaPickerModal
        open={mediaPickerOpen}
        title={mediaPickerMode === 'video' ? '选择视频资源' : mediaPickerMode === 'gallery' ? '选择图集资源' : '选择图片资源'}
        assetType={mediaPickerMode === 'video' ? 'video' : 'image'}
        selectedAssetId={mediaPickerMode === 'cover' ? Number(coverAssetId || 0) : null}
        onCancel={() => setMediaPickerOpen(false)}
        onSelect={handleMediaSelect}
      />
    </PagePlaceholder>
  );
}
