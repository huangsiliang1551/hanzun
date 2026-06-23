import { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Button,
  Card,
  Form,
  Image,
  Input,
  InputNumber,
  Modal,
  Popconfirm,
  Select,
  Space,
  Switch,
  Table,
  Tag,
  Typography,
  message,
} from 'antd';
import {
  ArrowDownOutlined,
  ArrowUpOutlined,
  DeleteOutlined,
  EditOutlined,
  PlusOutlined,
} from '@ant-design/icons';
import MediaPickerModal from '@/components/MediaPickerModal';
import PagePlaceholder from '@/components/PagePlaceholder';
import { getAds, saveAds } from '@/api/ads';
import { getPages } from '@/api/pages';
import { resolveAssetUrl } from '@/utils/media';

const { Text } = Typography;

const POSITION_TEMPLATES = [
  {
    key: 'homepage_notice',
    label: '首页通知横幅',
    position_key: 'homepage_notice',
    page_scope: 'home',
    title: '首页通知横幅',
  },
  {
    key: 'homepage_featured',
    label: '首页重点广告位',
    position_key: 'homepage_featured',
    page_scope: 'home',
    title: '首页重点广告位',
  },
  {
    key: 'products_featured',
    label: '产品页重点广告位',
    position_key: 'products_featured',
    page_scope: 'products',
    title: '产品页重点广告位',
  },
  {
    key: 'news_featured',
    label: '新闻页重点广告位',
    position_key: 'news_featured',
    page_scope: 'news',
    title: '新闻页重点广告位',
  },
];

function normalizeAds(payload) {
  if (Array.isArray(payload?.items)) {
    return payload.items;
  }

  if (Array.isArray(payload)) {
    return payload;
  }

  return [];
}

function normalizePageOptions(payload) {
  const items = Array.isArray(payload?.items) ? payload.items : [];
  return items.map((item) => ({
    value: Number(item.id || 0),
    label: item.title_zh || item.title_en || item.slug || `单页 #${item.id}`,
    slug: item.slug || '',
  }));
}

function sortAds(items) {
  return [...items].sort((left, right) => {
    const sortDiff = Number(left.sort || 0) - Number(right.sort || 0);
    if (sortDiff !== 0) {
      return sortDiff;
    }
    return String(left.id || '').localeCompare(String(right.id || ''));
  });
}

function normalizeSort(items) {
  return sortAds(items).map((item, index) => ({
    ...item,
    sort: (index + 1) * 10,
  }));
}

function createEmptyRecord(ads = []) {
  return {
    id: '',
    position_key: 'homepage_notice',
    page_scope: 'home',
    title: '',
    image_url: '',
    linked_page_id: undefined,
    linked_page_slug: '',
    open_in_new_tab: false,
    sort: ads.length > 0 ? Number(ads[ads.length - 1].sort || 0) + 10 : 10,
    is_enabled: true,
  };
}

function recordToFormValues(record) {
  return {
    id: record?.id || '',
    template_key: undefined,
    position_key: record?.position_key || 'homepage_notice',
    page_scope: record?.page_scope || 'home',
    title: record?.title || '',
    image_url: record?.image_url || '',
    linked_page_id: record?.linked_page_id ? Number(record.linked_page_id) : undefined,
    linked_page_slug: record?.linked_page_slug || '',
    open_in_new_tab: Number(record?.open_in_new_tab || 0) === 1,
    sort: Number(record?.sort || 10),
    is_enabled: Number(record?.is_enabled || 0) === 1,
  };
}

function formValuesToRecord(values, pageOptionMap) {
  const linkedPageId = Number(values.linked_page_id || 0);
  const linkedPageOption = pageOptionMap.get(linkedPageId);

  return {
    id: String(values.id || '').trim(),
    position_key: String(values.position_key || '').trim(),
    page_scope: String(values.page_scope || '').trim(),
    title: String(values.title || '').trim(),
    image_url: String(values.image_url || '').trim(),
    linked_page_id: linkedPageId,
    linked_page_slug: linkedPageOption?.slug || String(values.linked_page_slug || '').trim(),
    open_in_new_tab: values.open_in_new_tab ? 1 : 0,
    sort: Number(values.sort || 0),
    is_enabled: values.is_enabled ? 1 : 0,
  };
}

export default function AdsPage() {
  const [ads, setAds] = useState([]);
  const [pageOptions, setPageOptions] = useState([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [reordering, setReordering] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [editingId, setEditingId] = useState('');
  const [form] = Form.useForm();
  const imageUrl = Form.useWatch('image_url', form);

  const enabledAdCount = useMemo(
    () => ads.filter((item) => Number(item.is_enabled || 0) === 1).length,
    [ads],
  );

  const pageOptionMap = useMemo(() => {
    const map = new Map();
    pageOptions.forEach((item) => map.set(Number(item.value || 0), item));
    return map;
  }, [pageOptions]);

  const editingRecord = useMemo(
    () => ads.find((item) => String(item.id || '') === String(editingId || '')) || null,
    [ads, editingId],
  );

  async function loadPageOptions() {
    try {
      const payload = await getPages({ page: 1, page_size: 200 });
      setPageOptions(normalizePageOptions(payload));
    } catch (error) {
      message.error(error.message || '加载单页选项失败');
    }
  }

  async function loadAds() {
    setLoading(true);
    try {
      const payload = await getAds();
      setAds(sortAds(normalizeAds(payload)));
    } catch (error) {
      message.error(error.message || '加载广告位失败');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadAds();
    loadPageOptions();
  }, []);

  function openCreateModal() {
    setEditingId('');
    form.setFieldsValue(recordToFormValues(createEmptyRecord(ads)));
    setModalOpen(true);
  }

  function openEditModal(record) {
    setEditingId(String(record.id || ''));
    form.setFieldsValue(recordToFormValues(record));
    setModalOpen(true);
  }

  function closeModal() {
    setModalOpen(false);
    setEditingId('');
  }

  function applyTemplate(templateKey) {
    const template = POSITION_TEMPLATES.find((item) => item.key === templateKey);
    if (!template) {
      return;
    }

    form.setFieldsValue({
      template_key: template.key,
      position_key: template.position_key,
      page_scope: template.page_scope,
      title: template.title,
    });
  }

  async function persistAds(nextAds, successMessage) {
    const normalized = normalizeSort(nextAds);
    await saveAds({ items: normalized });
    setAds(normalized);
    if (successMessage) {
      message.success(successMessage);
    }
  }

  async function handleSubmit(values) {
    setSaving(true);
    try {
      const nextRecord = formValuesToRecord(values, pageOptionMap);
      const currentId = String(nextRecord.id || '').trim();
      let nextAds = [...ads];

      if (currentId) {
        nextAds = nextAds.map((item) => (String(item.id || '') === currentId ? { ...item, ...nextRecord } : item));
      } else {
        nextRecord.id = `ad_${Date.now()}`;
        nextAds = [...nextAds, nextRecord];
      }

      await persistAds(nextAds, currentId ? '广告位已更新' : '广告位已创建');
      closeModal();
    } catch (error) {
      message.error(error.message || '保存广告位失败');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(record) {
    try {
      const nextAds = ads.filter((item) => String(item.id || '') !== String(record.id || ''));
      await persistAds(nextAds, '广告位已删除');
    } catch (error) {
      message.error(error.message || '删除广告位失败');
    }
  }

  async function handleToggleEnabled(record, checked) {
    try {
      const nextAds = ads.map((item) =>
        String(item.id || '') === String(record.id || '')
          ? { ...item, is_enabled: checked ? 1 : 0 }
          : item,
      );
      await persistAds(nextAds, checked ? '广告位已启用' : '广告位已停用');
    } catch (error) {
      message.error(error.message || '更新广告位状态失败');
    }
  }

  async function handleMove(record, direction) {
    const sorted = sortAds(ads);
    const index = sorted.findIndex((item) => String(item.id || '') === String(record.id || ''));
    if (index < 0) {
      return;
    }

    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex < 0 || targetIndex >= sorted.length) {
      return;
    }

    const nextAds = [...sorted];
    [nextAds[index], nextAds[targetIndex]] = [nextAds[targetIndex], nextAds[index]];

    setReordering(true);
    try {
      await persistAds(nextAds, '广告位顺序已更新');
    } catch (error) {
      message.error(error.message || '更新广告位顺序失败');
    } finally {
      setReordering(false);
    }
  }

  const columns = [
    {
      title: '广告位',
      dataIndex: 'title',
      width: 220,
      render: (_, record) => (
        <Space direction="vertical" size={0}>
          <Text strong>{record.title || '-'}</Text>
          <Text type="secondary">{record.position_key || '-'}</Text>
        </Space>
      ),
    },
    {
      title: '范围',
      dataIndex: 'page_scope',
      width: 110,
    },
    {
      title: '图片',
      dataIndex: 'image_url',
      width: 120,
      render: (value) =>
        resolveAssetUrl(value || '') ? (
          <Image
            src={resolveAssetUrl(value || '')}
            width={84}
            height={48}
            style={{ objectFit: 'cover', borderRadius: 8 }}
            preview={false}
          />
        ) : (
          <Text type="secondary">未设置</Text>
        ),
    },
    {
      title: '跳转页面',
      dataIndex: 'linked_page_title',
      width: 200,
      render: (_, record) => (
        <Space direction="vertical" size={0}>
          <Text>{record.linked_page_title || '-'}</Text>
          <Text type="secondary">{record.linked_page_slug || '-'}</Text>
        </Space>
      ),
    },
    {
      title: '状态',
      dataIndex: 'is_enabled',
      width: 100,
      render: (_, record) => (
        <Switch checked={Number(record.is_enabled || 0) === 1} onChange={(checked) => handleToggleEnabled(record, checked)} />
      ),
    },
    {
      title: '打开方式',
      dataIndex: 'open_in_new_tab',
      width: 120,
      render: (value) => (
        <Tag color={Number(value || 0) === 1 ? 'processing' : 'default'}>
          {Number(value || 0) === 1 ? '新窗口' : '当前页'}
        </Tag>
      ),
    },
    {
      title: '排序',
      dataIndex: 'sort',
      width: 80,
    },
    {
      title: '操作',
      key: 'actions',
      width: 220,
      render: (_, record, index) => (
        <Space wrap size={4}>
          <Button size="small" icon={<EditOutlined />} onClick={() => openEditModal(record)}>
            编辑
          </Button>
          <Button
            size="small"
            icon={<ArrowUpOutlined />}
            disabled={index === 0 || reordering}
            onClick={() => handleMove(record, 'up')}
          />
          <Button
            size="small"
            icon={<ArrowDownOutlined />}
            disabled={index === ads.length - 1 || reordering}
            onClick={() => handleMove(record, 'down')}
          />
          <Popconfirm
            title="确认删除该广告位吗？"
            description="删除后前台将不再展示该广告内容。"
            okText="删除"
            cancelText="取消"
            onConfirm={() => handleDelete(record)}
          >
            <Button danger size="small" icon={<DeleteOutlined />}>
              删除
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <PagePlaceholder hideHeader compact tags={[`广告位 ${ads.length} 条`, `启用中 ${enabledAdCount} 条`]}>
      <Space direction="vertical" size={16} style={{ width: '100%' }}>
        <Card className="page-card" bordered={false} size="small">
          <Space align="start" style={{ width: '100%', justifyContent: 'space-between' }} wrap>
            <Space size={[8, 8]} wrap>
              <Tag color="blue">广告位 {ads.length} 条</Tag>
              <Tag color="success">启用中 {enabledAdCount} 条</Tag>
            </Space>
            <Space wrap>
              <Button onClick={loadAds}>刷新列表</Button>
              <Button type="primary" icon={<PlusOutlined />} onClick={openCreateModal}>
                新增广告位
              </Button>
            </Space>
          </Space>
        </Card>

        <Card className="page-card" bordered={false} size="small">
          <Table
            rowKey="id"
            size="small"
            loading={loading || reordering}
            columns={columns}
            dataSource={ads}
            pagination={false}
            locale={{
              emptyText: (
                <Space direction="vertical" size={12}>
                  <Text type="secondary">还没有广告位内容，可以先新增首页通知横幅或重点广告位。</Text>
                  <Button type="primary" onClick={openCreateModal}>
                    新增广告位
                  </Button>
                </Space>
              ),
            }}
            scroll={{ x: 1200 }}
          />
        </Card>

        <Modal
          title={editingRecord ? '编辑广告位' : '新增广告位'}
          open={modalOpen}
          onCancel={closeModal}
          onOk={() => form.submit()}
          okText="保存"
          cancelText="取消"
          okButtonProps={{ loading: saving }}
          width={760}
          destroyOnHidden
        >
          <Form form={form} layout="vertical" preserve={false} onFinish={handleSubmit}>
            <Form.Item name="id" hidden>
              <Input />
            </Form.Item>

            <Alert
              type="info"
              showIcon
              style={{ marginBottom: 16 }}
              message="广告位将直接参与前台页面生成"
              description="建议优先维护首页横幅、首页通知、产品页重点广告和新闻页重点广告等高频位置。"
            />

            {!editingRecord ? (
              <Form.Item name="template_key" label="快速模板">
                <Select
                  allowClear
                  placeholder="选择预设广告位置模板"
                  options={POSITION_TEMPLATES.map((item) => ({
                    label: item.label,
                    value: item.key,
                  }))}
                  onChange={applyTemplate}
                />
              </Form.Item>
            ) : null}

            <Space direction="vertical" size={4} style={{ width: '100%', marginBottom: 16 }}>
              <Text strong>广告图片</Text>
              <Space wrap>
                <Button onClick={() => setPickerOpen(true)}>从资源中心选择图片</Button>
              </Space>
              {resolveAssetUrl(imageUrl || '') ? (
                <Image
                  src={resolveAssetUrl(imageUrl || '')}
                  alt="广告预览"
                  width={240}
                  height={135}
                  style={{ objectFit: 'cover', borderRadius: 12 }}
                  preview={false}
                />
              ) : (
                <Text type="secondary">尚未选择图片</Text>
              )}
            </Space>

            <Form.Item
              label="图片地址"
              name="image_url"
              extra="建议通过资源中心选择，保持图片路径和素材库一致。"
              rules={[{ required: true, message: '请选择广告图片' }]}
            >
              <Input placeholder="请选择资源图片，或手动填写图片地址" />
            </Form.Item>

            <Form.Item
              label="广告标题"
              name="title"
              extra="用于后台识别，也可作为前台悬浮提示。"
              rules={[{ required: true, message: '请输入广告标题' }]}
            >
              <Input placeholder="例如 首页重点通知横幅" />
            </Form.Item>

            <Space size={16} style={{ width: '100%' }} align="start">
              <Form.Item
                label="位置键名"
                name="position_key"
                extra="建议使用清晰的英文键名。"
                rules={[{ required: true, message: '请输入位置键名' }]}
                style={{ flex: 1 }}
              >
                <Input placeholder="例如 homepage_notice" />
              </Form.Item>
              <Form.Item
                label="页面范围"
                name="page_scope"
                extra="用于区分首页、产品页、新闻页等模块。"
                rules={[{ required: true, message: '请输入页面范围' }]}
                style={{ flex: 1 }}
              >
                <Input placeholder="例如 home / products / news" />
              </Form.Item>
            </Space>

            <Space size={16} style={{ width: '100%' }} align="start">
              <Form.Item
                label="关联单页"
                name="linked_page_id"
                style={{ flex: 1 }}
                rules={[{ required: true, message: '请选择关联单页' }]}
              >
                <Select
                  allowClear
                  showSearch
                  placeholder="请选择需要跳转的单页"
                  options={pageOptions}
                  optionFilterProp="label"
                  onChange={(value) => {
                    const option = pageOptionMap.get(Number(value || 0));
                    form.setFieldValue('linked_page_slug', option?.slug || '');
                  }}
                  onClear={() => {
                    form.setFieldsValue({
                      linked_page_id: undefined,
                      linked_page_slug: '',
                    });
                  }}
                />
              </Form.Item>
              <Form.Item label="跳转 slug" name="linked_page_slug" style={{ flex: 1 }} tooltip="由关联单页自动带出">
                <Input placeholder="例如 about" readOnly />
              </Form.Item>
            </Space>

            <Space size={16} style={{ width: '100%' }} align="start">
              <Form.Item label="排序值" name="sort" style={{ width: 160 }}>
                <InputNumber min={0} step={10} style={{ width: '100%' }} />
              </Form.Item>
              <Form.Item label="新窗口打开" name="open_in_new_tab" valuePropName="checked" style={{ width: 180 }}>
                <Switch />
              </Form.Item>
              <Form.Item label="是否启用" name="is_enabled" valuePropName="checked" style={{ width: 140 }}>
                <Switch />
              </Form.Item>
            </Space>
          </Form>
        </Modal>

        <MediaPickerModal
          open={pickerOpen}
          title="选择广告图片"
          assetType="image"
          onCancel={() => setPickerOpen(false)}
          onSelect={async (asset) => {
            form.setFieldValue('image_url', asset?.file_path || asset?.thumbnail_url || '');
            setPickerOpen(false);
          }}
        />
      </Space>
    </PagePlaceholder>
  );
}
