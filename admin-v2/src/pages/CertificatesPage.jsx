import { useEffect, useMemo, useState } from 'react';
import {
  App,
  Button,
  Card,
  Drawer,
  Form,
  Image,
  Input,
  InputNumber,
  Pagination,
  Popconfirm,
  Select,
  Space,
  Switch,
  Table,
  Tag,
  Typography,
} from 'antd';
import MediaPickerModal from '@/components/MediaPickerModal';
import PagePlaceholder from '@/components/PagePlaceholder';
import TableSelectionFooter from '@/components/TableSelectionFooter';
import {
  batchUpdateCertificatePublishStatus,
  createCertificate,
  deleteCertificate,
  getCertificateDetail,
  getCertificateImageAsset,
  getCertificates,
  updateCertificate,
  updateCertificatePublishStatus,
} from '@/api/certificates';
import { resolveAssetUrl } from '@/utils/media';
import { openPublicPreview } from '@/utils/publicPreview';

const { TextArea } = Input;
const { Text } = Typography;

const publishStatusOptions = [
  { label: '全部发布状态', value: '' },
  { label: '已发布', value: 'published' },
  { label: '草稿', value: 'draft' },
  { label: '已下线', value: 'offline' },
];

const publishStatusFormOptions = publishStatusOptions.slice(1);

const translationStatusOptions = [
  { label: '待处理', value: 'pending' },
  { label: '已完成', value: 'completed' },
];

const seoStatusOptions = [
  { label: '待处理', value: 'pending' },
  { label: '已生成', value: 'generated' },
];

function renderPublishStatus(status) {
  const colorMap = {
    published: 'success',
    draft: 'default',
    offline: 'warning',
  };

  const labelMap = {
    published: '已发布',
    draft: '草稿',
    offline: '已下线',
  };

  return <Tag color={colorMap[status] || 'default'}>{labelMap[status] || status || '-'}</Tag>;
}

function normalizeListPayload(payload) {
  if (Array.isArray(payload?.items)) {
    return payload.items;
  }

  if (Array.isArray(payload)) {
    return payload;
  }

  return [];
}

function buildKeyword(item) {
  return [
    item.name_zh,
    item.issuer_zh,
    item.certificate_no,
    item.certificate_type,
    item.description_zh,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase();
}

export default function CertificatesPage() {
  const { message } = App.useApp();
  const [filters, setFilters] = useState({
    keyword: '',
    publish_status: '',
    featured_only: false,
  });
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 20,
  });
  const [items, setItems] = useState([]);
  const [assetMap, setAssetMap] = useState({});
  const [loading, setLoading] = useState(false);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [drawerLoading, setDrawerLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [batchSubmitting, setBatchSubmitting] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [selectedRowKeys, setSelectedRowKeys] = useState([]);
  const [previewAsset, setPreviewAsset] = useState(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [form] = Form.useForm();

  const imageAssetId = Form.useWatch('image_asset_id', form);

  async function hydrateAssets(list) {
    const ids = Array.from(
      new Set(
        list
          .map((item) => Number(item.image_asset_id || 0))
          .filter((value) => Number.isFinite(value) && value > 0),
      ),
    );

    if (ids.length === 0) {
      setAssetMap({});
      return;
    }

    const settled = await Promise.allSettled(ids.map((id) => getCertificateImageAsset(id)));
    const nextMap = {};
    settled.forEach((result, index) => {
      if (result.status === 'fulfilled') {
        nextMap[ids[index]] = result.value;
      }
    });
    setAssetMap(nextMap);
  }

  async function loadCertificatesList() {
    setLoading(true);

    try {
      const data = await getCertificates();
      const nextItems = normalizeListPayload(data);
      setItems(nextItems);
      setSelectedRowKeys([]);
      await hydrateAssets(nextItems);
    } catch (error) {
      message.error(error.message || '加载证书列表失败，请稍后重试。');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadCertificatesList();
  }, []);

  useEffect(() => {
    if (!drawerOpen) {
      setPreviewAsset(null);
      return;
    }

    const assetId = Number(imageAssetId || 0);
    if (!assetId) {
      setPreviewAsset(null);
      return;
    }

    let disposed = false;
    setPreviewLoading(true);

    getCertificateImageAsset(assetId)
      .then((asset) => {
        if (!disposed) {
          setPreviewAsset(asset);
        }
      })
      .catch(() => {
        if (!disposed) {
          setPreviewAsset(null);
        }
      })
      .finally(() => {
        if (!disposed) {
          setPreviewLoading(false);
        }
      });

    return () => {
      disposed = true;
    };
  }, [drawerOpen, imageAssetId]);

  const filteredItems = useMemo(() => {
    const keyword = filters.keyword.trim().toLowerCase();

    return items.filter((item) => {
      if (filters.publish_status && item.publish_status !== filters.publish_status) {
        return false;
      }

      if (filters.featured_only && Number(item.is_home_featured || 0) !== 1) {
        return false;
      }

      if (keyword && !buildKeyword(item).includes(keyword)) {
        return false;
      }

      return true;
    });
  }, [filters, items]);

  useEffect(() => {
    const maxPage = Math.max(1, Math.ceil(filteredItems.length / pagination.pageSize));
    if (pagination.current > maxPage) {
      setPagination((current) => ({
        ...current,
        current: maxPage,
      }));
    }
  }, [filteredItems.length, pagination.current, pagination.pageSize]);

  const paginatedItems = useMemo(() => {
    const start = (pagination.current - 1) * pagination.pageSize;
    return filteredItems.slice(start, start + pagination.pageSize);
  }, [filteredItems, pagination]);

  function openCreateDrawer() {
    setEditingId(null);
    setDrawerOpen(true);
    form.resetFields();
    form.setFieldsValue({
      name_zh: '',
      issuer_zh: '',
      certificate_no: '',
      certificate_type: '',
      description_zh: '',
      image_asset_id: null,
      publish_status: 'published',
      translation_status: 'pending',
      seo_status: 'pending',
      is_home_featured: false,
      manual_sort: 100,
    });
  }

  async function openEditDrawer(record) {
    setEditingId(record.id);
    setDrawerOpen(true);
    setDrawerLoading(true);

    try {
      const data = await getCertificateDetail(record.id);
      form.setFieldsValue({
        name_zh: data.name_zh || '',
        issuer_zh: data.issuer_zh || '',
        certificate_no: data.certificate_no || '',
        certificate_type: data.certificate_type || '',
        description_zh: data.description_zh || '',
        image_asset_id: data.image_asset_id ? Number(data.image_asset_id) : null,
        publish_status: data.publish_status || 'published',
        translation_status: data.translation_status || 'pending',
        seo_status: data.seo_status || 'pending',
        is_home_featured: Number(data.is_home_featured || 0) === 1,
        manual_sort: Number(data.manual_sort || 0),
      });
    } catch (error) {
      message.error(error.message || '加载证书详情失败，请稍后重试。');
      setDrawerOpen(false);
      setEditingId(null);
    } finally {
      setDrawerLoading(false);
    }
  }

  async function handleSubmit(values) {
    setSubmitting(true);

    const payload = {
      ...values,
      image_asset_id: values.image_asset_id ? Number(values.image_asset_id) : null,
      manual_sort: Number(values.manual_sort || 0),
      is_home_featured: values.is_home_featured ? 1 : 0,
    };

    try {
      if (editingId) {
        await updateCertificate(editingId, payload);
        message.success('证书已更新');
      } else {
        await createCertificate(payload);
        message.success('证书已创建');
      }

      setDrawerOpen(false);
      setEditingId(null);
      form.resetFields();
      await loadCertificatesList();
    } catch (error) {
      message.error(error.message || '保存证书失败，请稍后重试。');
    } finally {
      setSubmitting(false);
    }
  }

  async function handlePublishToggle(record, publishStatus) {
    try {
      await updateCertificatePublishStatus(record.id, publishStatus);
      message.success('证书发布状态已更新');
      await loadCertificatesList();
    } catch (error) {
      message.error(error.message || '更新证书发布状态失败。');
    }
  }

  async function handleBatchPublish(publishStatus) {
    if (selectedRowKeys.length === 0) {
      message.warning('请先至少选择一张证书。');
      return;
    }

    setBatchSubmitting(true);

    try {
      await batchUpdateCertificatePublishStatus(selectedRowKeys, publishStatus);
      message.success('证书批量发布状态已更新');
      await loadCertificatesList();
    } catch (error) {
      message.error(error.message || '批量更新证书发布状态失败。');
    } finally {
      setBatchSubmitting(false);
    }
  }

  async function handleBatchDelete() {
    if (selectedRowKeys.length === 0) {
      message.warning('请先至少选择一张证书。');
      return;
    }

    setBatchSubmitting(true);

    try {
      await Promise.all(selectedRowKeys.map((id) => deleteCertificate(id)));
      message.success(`已删除 ${selectedRowKeys.length} 张证书。`);
      await loadCertificatesList();
    } catch (error) {
      message.error(error.message || '批量删除证书失败，请稍后重试。');
    } finally {
      setBatchSubmitting(false);
    }
  }

  async function handleDelete(record) {
    try {
      await deleteCertificate(record.id);
      message.success('证书已删除');
      await loadCertificatesList();
    } catch (error) {
      message.error(error.message || '删除证书失败，请稍后重试。');
    }
  }

  const columns = [
    {
      title: '证书图片',
      dataIndex: 'image_asset_id',
      width: 116,
      render: (value) => {
        const asset = assetMap[Number(value || 0)];

        if (asset?.file_path) {
          return (
            <Image
              src={resolveAssetUrl(asset.file_path)}
              alt={asset.file_name || '证书'}
              width={76}
              height={54}
              style={{ objectFit: 'cover', borderRadius: 8 }}
              placeholder
            />
          );
        }

        return (
          <div
            style={{
              width: 76,
              height: 54,
              borderRadius: 8,
              background: '#f5f5f5',
              color: '#8c8c8c',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              border: '1px dashed #d9d9d9',
              fontSize: 12,
            }}
          >
            暂无图片
          </div>
        );
      },
    },
    {
      title: '证书名称',
      dataIndex: 'name_zh',
      render: (value, record) => (
        <Space direction="vertical" size={2}>
          <span>{value || '-'}</span>
          <Text type="secondary">{record.issuer_zh || '未填写颁发机构'}</Text>
        </Space>
      ),
    },
    {
      title: '证书类型 / 编号',
      key: 'type',
      width: 220,
      render: (_, record) => (
        <Space direction="vertical" size={2}>
          <span>{record.certificate_type || '-'}</span>
          <Text type="secondary">{record.certificate_no || '-'}</Text>
        </Space>
      ),
    },
    {
      title: '发布状态',
      dataIndex: 'publish_status',
      width: 120,
      render: renderPublishStatus,
    },
    {
      title: '浏览量',
      dataIndex: 'views_count',
      width: 98,
      render: (value) => Number(value || 0),
    },
    {
      title: '首页推荐',
      dataIndex: 'is_home_featured',
      width: 100,
      render: (value) => (Number(value || 0) === 1 ? <Tag color="blue">是</Tag> : '否'),
    },
    {
      title: '排序值',
      dataIndex: 'manual_sort',
      width: 90,
    },
    {
      title: '操作',
      key: 'actions',
      width: 250,
      render: (_, record) => (
        <Space wrap>
          <Button size="small" onClick={() => openPublicPreview('certificate', record)}>
            查看页面
          </Button>
          <Button size="small" onClick={() => openEditDrawer(record)}>
            编辑
          </Button>
          {record.publish_status === 'published' ? (
            <Button size="small" onClick={() => handlePublishToggle(record, 'offline')}>
              下线
            </Button>
          ) : (
            <Button
              size="small"
              type="primary"
              onClick={() => handlePublishToggle(record, 'published')}
            >
              发布
            </Button>
          )}
          <Popconfirm
            title="确定删除这张证书吗？"
            okText="删除"
            cancelText="取消"
            onConfirm={() => handleDelete(record)}
          >
            <Button size="small" danger>
              删除
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <>
      <PagePlaceholder
        hideHeader
        compact
        tags={[`共 ${items.length} 条`, `已选 ${selectedRowKeys.length} 项`]}
      >
        <Space direction="vertical" size={20} style={{ width: '100%' }}>
          <Card className="page-card" bordered={false}>
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
              <div className="toolbar-surface">
                <Space wrap size={12} style={{ width: '100%', justifyContent: 'space-between' }}>
                  <Space wrap size={12}>
                    <Input.Search
                      allowClear
                      placeholder="搜索证书名称、颁发机构或类型"
                      style={{ width: 280 }}
                      onSearch={(value) => {
                        setFilters((current) => ({
                          ...current,
                          keyword: value.trim(),
                        }));
                        setPagination((current) => ({
                          ...current,
                          current: 1,
                        }));
                      }}
                    />
                    <Select
                      style={{ width: 170 }}
                      options={publishStatusOptions}
                      value={filters.publish_status}
                      onChange={(value) => {
                        setFilters((current) => ({
                          ...current,
                          publish_status: value,
                        }));
                        setPagination((current) => ({
                          ...current,
                          current: 1,
                        }));
                      }}
                    />
                    <Switch
                      checked={filters.featured_only}
                      checkedChildren="仅看推荐"
                      unCheckedChildren="全部证书"
                      onChange={(checked) => {
                        setFilters((current) => ({
                          ...current,
                          featured_only: checked,
                        }));
                        setPagination((current) => ({
                          ...current,
                          current: 1,
                        }));
                      }}
                    />
                  </Space>

                  <Space wrap>
                    <Button type="primary" ghost onClick={openCreateDrawer}>
                      新建证书
                    </Button>
                  </Space>
                </Space>
              </div>

              <Table
                rowKey="id"
                loading={loading}
                columns={columns}
                dataSource={paginatedItems}
                rowSelection={{
                  selectedRowKeys,
                  onChange: setSelectedRowKeys,
                }}
                pagination={false}
              />
              <TableSelectionFooter
                rowKeys={paginatedItems.map((item) => item.id)}
                selectedRowKeys={selectedRowKeys}
                onChange={setSelectedRowKeys}
                actions={
                  <>
                    <Button danger onClick={handleBatchDelete} loading={batchSubmitting}>
                      批量删除
                    </Button>
                    <Button onClick={() => handleBatchPublish('offline')} loading={batchSubmitting}>
                      批量下线
                    </Button>
                    <Button onClick={() => handleBatchPublish('draft')} loading={batchSubmitting}>
                      批量转草稿
                    </Button>
                    <Button
                      type="primary"
                      onClick={() => handleBatchPublish('published')}
                      loading={batchSubmitting}
                    >
                      批量发布
                    </Button>
                  </>
                }
                pagination={
                  <Pagination
                    current={pagination.current}
                    pageSize={pagination.pageSize}
                    total={filteredItems.length}
                    showSizeChanger
                    onChange={(page, pageSize) =>
                      setPagination({
                        current: page,
                        pageSize,
                      })
                    }
                  />
                }
              />
            </Space>
          </Card>
        </Space>
      </PagePlaceholder>

      <Drawer
        title={editingId ? '编辑证书' : '新建证书'}
        width={760}
        open={drawerOpen}
        onClose={() => {
          setDrawerOpen(false);
          setEditingId(null);
          setPickerOpen(false);
        }}
        destroyOnHidden
        extra={
          <Space>
            <Button
              onClick={() => {
                setDrawerOpen(false);
                setEditingId(null);
              }}
            >
              取消
            </Button>
            <Button type="primary" loading={submitting} onClick={() => form.submit()}>
              保存
            </Button>
          </Space>
        }
      >
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <Form
            form={form}
            layout="vertical"
            onFinish={handleSubmit}
            disabled={drawerLoading || submitting}
          >
            <Form.Item name="image_asset_id" hidden>
              <Input />
            </Form.Item>

            <div className="form-two-column">
              <Form.Item
                name="name_zh"
                label="证书名称"
                rules={[{ required: true, message: '请输入证书名称。' }]}
              >
                <Input placeholder="请输入证书名称" />
              </Form.Item>

              <Form.Item name="issuer_zh" label="颁发机构">
                <Input placeholder="请输入机构或认证单位名称" />
              </Form.Item>
            </div>

            <div className="form-two-column">
              <Form.Item name="certificate_type" label="证书类型">
                <Input placeholder="例如 ISO、TUV、CE" />
              </Form.Item>

              <Form.Item name="certificate_no" label="证书编号">
                <Input placeholder="请输入证书编号" />
              </Form.Item>
            </div>

            <Form.Item label="证书图片">
              <div className="media-field-shell media-field-shell-wide">
                <div className="media-field-preview media-field-preview-wide">
                  {previewAsset?.file_path ? (
                    <Image
                      src={resolveAssetUrl(previewAsset.file_path)}
                      alt={previewAsset.original_name || '证书预览'}
                      width={200}
                      height={132}
                      style={{ objectFit: 'cover', borderRadius: 12 }}
                      placeholder
                    />
                  ) : (
                    <div className="media-field-empty media-field-empty-wide">
                      {previewLoading ? '加载中...' : '未选择图片'}
                    </div>
                  )}
                </div>

                <div className="media-field-meta">
                  <Space wrap>
                    <Button type="primary" onClick={() => setPickerOpen(true)}>
                      {imageAssetId ? '更换图片' : '选择图片'}
                    </Button>
                    {imageAssetId ? (
                      <Button
                        onClick={() => {
                          form.setFieldValue('image_asset_id', null);
                          setPreviewAsset(null);
                        }}
                      >
                        清空
                      </Button>
                    ) : null}
                  </Space>

                  <Text type="secondary">已选资源 ID：{imageAssetId || '未选择'}</Text>

                  {previewAsset?.file_name ? (
                    <Space direction="vertical" size={2}>
                      <Text strong>{previewAsset.file_name}</Text>
                      <Text type="secondary">{previewAsset.file_path}</Text>
                    </Space>
                  ) : (
                    <Text type="secondary">请从资源中心选择对应证书图片。</Text>
                  )}
                </div>
              </div>
            </Form.Item>

            <Form.Item name="description_zh" label="说明">
              <TextArea rows={6} placeholder="可填写证书适用范围、认证说明等内容" />
            </Form.Item>

            <div className="form-two-column">
              <Form.Item name="publish_status" label="发布状态">
                <Select options={publishStatusFormOptions} />
              </Form.Item>

              <Form.Item name="is_home_featured" label="首页推荐" valuePropName="checked">
                <Switch checkedChildren="是" unCheckedChildren="否" />
              </Form.Item>
            </div>

            <div className="form-two-column">
              <Form.Item name="translation_status" label="翻译状态">
                <Select options={translationStatusOptions} />
              </Form.Item>

              <Form.Item name="seo_status" label="SEO 状态">
                <Select options={seoStatusOptions} />
              </Form.Item>
            </div>

            <Form.Item name="manual_sort" label="排序值">
              <InputNumber min={0} precision={0} style={{ width: '100%' }} />
            </Form.Item>
          </Form>
        </Space>
      </Drawer>

      <MediaPickerModal
        open={pickerOpen}
        title="选择证书图片"
        assetType="image"
        selectedAssetId={imageAssetId}
        onCancel={() => setPickerOpen(false)}
        onSelect={(asset) => {
          form.setFieldValue('image_asset_id', Number(asset.id));
          setPreviewAsset(asset);
          setPickerOpen(false);
        }}
      />
    </>
  );
}
