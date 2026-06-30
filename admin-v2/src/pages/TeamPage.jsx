import { useEffect, useMemo, useState } from 'react';
import {
  App,
  Avatar,
  Button,
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
  batchUpdateTeamMemberPublishStatus,
  createTeamMember,
  deleteTeamMember,
  getTeamAvatarAsset,
  getTeamMemberDetail,
  getTeamMembers,
  updateTeamMember,
  updateTeamMemberPublishStatus,
} from '@/api/team';
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
    item.title_zh,
    item.department_zh,
    item.email,
    item.phone,
    item.whatsapp,
    item.wechat,
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase();
}

function buildContactLines(record) {
  return [record.email, record.phone, record.whatsapp, record.wechat].filter(Boolean);
}

export default function TeamPage() {
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

  const avatarAssetId = Form.useWatch('avatar_asset_id', form);

  async function hydrateAssets(list) {
    const ids = Array.from(
      new Set(
        list
          .map((item) => Number(item.avatar_asset_id || 0))
          .filter((value) => Number.isFinite(value) && value > 0),
      ),
    );

    if (ids.length === 0) {
      setAssetMap({});
      return;
    }

    const settled = await Promise.allSettled(ids.map((id) => getTeamAvatarAsset(id)));
    const nextMap = {};
    settled.forEach((result, index) => {
      if (result.status === 'fulfilled') {
        nextMap[ids[index]] = result.value;
      }
    });
    setAssetMap(nextMap);
  }

  async function loadTeamMembers() {
    setLoading(true);

    try {
      const data = await getTeamMembers();
      const nextItems = normalizeListPayload(data);
      setItems(nextItems);
      setSelectedRowKeys([]);
      await hydrateAssets(nextItems);
    } catch (error) {
      message.error(error.message || '加载销售团队失败，请稍后重试。');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadTeamMembers();
  }, []);

  useEffect(() => {
    if (!drawerOpen) {
      setPreviewAsset(null);
      return;
    }

    const assetId = Number(avatarAssetId || 0);
    if (!assetId) {
      setPreviewAsset(null);
      return;
    }

    let disposed = false;
    setPreviewLoading(true);

    getTeamAvatarAsset(assetId)
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
  }, [drawerOpen, avatarAssetId]);

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
      title_zh: '',
      department_zh: '',
      email: '',
      phone: '',
      whatsapp: '',
      wechat: '',
      bio_zh: '',
      avatar_asset_id: null,
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
      const data = await getTeamMemberDetail(record.id);
      form.setFieldsValue({
        name_zh: data.name_zh || '',
        title_zh: data.title_zh || '',
        department_zh: data.department_zh || '',
        email: data.email || '',
        phone: data.phone || '',
        whatsapp: data.whatsapp || '',
        wechat: data.wechat || '',
        bio_zh: data.bio_zh || '',
        avatar_asset_id: data.avatar_asset_id ? Number(data.avatar_asset_id) : null,
        publish_status: data.publish_status || 'published',
        translation_status: data.translation_status || 'pending',
        seo_status: data.seo_status || 'pending',
        is_home_featured: Number(data.is_home_featured || 0) === 1,
        manual_sort: Number(data.manual_sort || 0),
      });
    } catch (error) {
      message.error(error.message || '加载团队成员详情失败，请稍后重试。');
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
      avatar_asset_id: values.avatar_asset_id ? Number(values.avatar_asset_id) : null,
      manual_sort: Number(values.manual_sort || 0),
      is_home_featured: values.is_home_featured ? 1 : 0,
    };

    try {
      if (editingId) {
        await updateTeamMember(editingId, payload);
        message.success('团队成员已更新');
      } else {
        await createTeamMember(payload);
        message.success('团队成员已创建');
      }

      setDrawerOpen(false);
      setEditingId(null);
      form.resetFields();
      await loadTeamMembers();
    } catch (error) {
      message.error(error.message || '保存团队成员失败，请稍后重试。');
    } finally {
      setSubmitting(false);
    }
  }

  async function handlePublishToggle(record, publishStatus) {
    try {
      await updateTeamMemberPublishStatus(record.id, publishStatus);
      message.success('团队成员发布状态已更新');
      await loadTeamMembers();
    } catch (error) {
      message.error(error.message || '更新团队成员发布状态失败。');
    }
  }

  async function handleBatchPublish(publishStatus) {
    if (selectedRowKeys.length === 0) {
      message.warning('请先至少选择一位成员。');
      return;
    }

    setBatchSubmitting(true);

    try {
      await batchUpdateTeamMemberPublishStatus(selectedRowKeys, publishStatus);
      message.success('团队成员批量发布状态已更新');
      await loadTeamMembers();
    } catch (error) {
      message.error(error.message || '批量更新团队成员发布状态失败。');
    } finally {
      setBatchSubmitting(false);
    }
  }

  async function handleBatchDelete() {
    if (selectedRowKeys.length === 0) {
      message.warning('请先至少选择一位成员。');
      return;
    }

    setBatchSubmitting(true);

    try {
      await Promise.all(selectedRowKeys.map((id) => deleteTeamMember(id)));
      message.success(`已删除 ${selectedRowKeys.length} 位成员。`);
      await loadTeamMembers();
    } catch (error) {
      message.error(error.message || '批量删除团队成员失败，请稍后重试。');
    } finally {
      setBatchSubmitting(false);
    }
  }

  async function handleDelete(record) {
    try {
      await deleteTeamMember(record.id);
      message.success('团队成员已删除');
      await loadTeamMembers();
    } catch (error) {
      message.error(error.message || '删除团队成员失败，请稍后重试。');
    }
  }

  const columns = [
    {
      title: '头像',
      dataIndex: 'avatar_asset_id',
      width: 96,
      render: (value, record) => {
        const asset = assetMap[Number(value || 0)];
        const src = asset?.file_path ? resolveAssetUrl(asset.file_path) : '';
        return src ? <Avatar src={src} shape="square" size={56} /> : <Avatar shape="square" size={56}>{String(record.name_zh || '?').slice(0, 1)}</Avatar>;
      },
    },
    {
      title: '姓名',
      dataIndex: 'name_zh',
      render: (value, record) => (
        <Space direction="vertical" size={2}>
          <span>{value || '-'}</span>
          <Text type="secondary">{record.title_zh || '未填写职位'}</Text>
        </Space>
      ),
    },
    {
      title: '部门',
      dataIndex: 'department_zh',
      width: 180,
      render: (value) => value || '-',
    },
    {
      title: '联系方式',
      width: 260,
      render: (_, record) => {
        const lines = buildContactLines(record);
        if (lines.length === 0) {
          return <Text type="secondary">暂无联系方式</Text>;
        }

        return (
          <Space direction="vertical" size={2}>
            {lines.slice(0, 2).map((line) => (
              <Text key={line} type="secondary">
                {line}
              </Text>
            ))}
            {lines.length > 2 ? <Text type="secondary">+{lines.length - 2} 项更多</Text> : null}
          </Space>
        );
      },
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
          <Button size="small" onClick={() => openPublicPreview('team', record)}>
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
            <Button size="small" type="primary" onClick={() => handlePublishToggle(record, 'published')}>
              发布
            </Button>
          )}
          <Popconfirm title="确认删除该成员吗？" okText="删除" cancelText="取消" onConfirm={() => handleDelete(record)}>
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
      <PagePlaceholder hideHeader compact tags={[`共 ${items.length} 条`, `已选 ${selectedRowKeys.length} 项`]}>
        <Space direction="vertical" size={20} style={{ width: '100%' }}>
          <div className="toolbar-surface">
            <Space wrap size={12} style={{ width: '100%', justifyContent: 'space-between' }}>
              <Space wrap size={12}>
                <Input.Search
                  allowClear
                  placeholder="搜索姓名、职位或联系方式"
                  style={{ width: 280 }}
                  onSearch={(value) => {
                    setFilters((current) => ({ ...current, keyword: value.trim() }));
                    setPagination((current) => ({ ...current, current: 1 }));
                  }}
                />
                <Select
                  style={{ width: 170 }}
                  options={publishStatusOptions}
                  value={filters.publish_status}
                  onChange={(value) => {
                    setFilters((current) => ({ ...current, publish_status: value }));
                    setPagination((current) => ({ ...current, current: 1 }));
                  }}
                />
                <Switch
                  checked={filters.featured_only}
                  checkedChildren="仅看推荐"
                  unCheckedChildren="全部成员"
                  onChange={(checked) => {
                    setFilters((current) => ({ ...current, featured_only: checked }));
                    setPagination((current) => ({ ...current, current: 1 }));
                  }}
                />
              </Space>

              <Space wrap>
                <Button type="primary" ghost onClick={openCreateDrawer}>
                  新建成员
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
                <Button type="primary" onClick={() => handleBatchPublish('published')} loading={batchSubmitting}>
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
                onChange={(page, pageSize) => setPagination({ current: page, pageSize })}
              />
            }
          />
        </Space>
      </PagePlaceholder>

      <Drawer
        title={editingId ? '编辑成员' : '新建成员'}
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
          <Form form={form} layout="vertical" onFinish={handleSubmit} disabled={drawerLoading || submitting}>
            <Form.Item name="avatar_asset_id" hidden>
              <Input />
            </Form.Item>

            <div className="form-two-column">
              <Form.Item name="name_zh" label="姓名" rules={[{ required: true, message: '请输入成员姓名。' }]}>
                <Input placeholder="请输入成员姓名" />
              </Form.Item>
              <Form.Item name="title_zh" label="职位">
                <Input placeholder="请输入职位" />
              </Form.Item>
            </div>

            <div className="form-two-column">
              <Form.Item name="department_zh" label="部门">
                <Input placeholder="请输入部门" />
              </Form.Item>
              <Form.Item name="email" label="邮箱">
                <Input placeholder="请输入邮箱" />
              </Form.Item>
            </div>

            <div className="form-two-column">
              <Form.Item name="phone" label="电话">
                <Input placeholder="请输入电话" />
              </Form.Item>
              <Form.Item name="whatsapp" label="WhatsApp">
                <Input placeholder="请输入 WhatsApp" />
              </Form.Item>
            </div>

            <Form.Item name="wechat" label="微信 / Line">
              <Input placeholder="请输入微信号或 Line" />
            </Form.Item>

            <Form.Item label="头像">
              <div className="media-field-shell media-field-shell-wide">
                <div className="media-field-preview media-field-preview-wide">
                  {previewAsset?.file_path ? (
                    <Image
                      src={resolveAssetUrl(previewAsset.file_path)}
                      alt={previewAsset.original_name || '头像预览'}
                      width={200}
                      height={132}
                      style={{ objectFit: 'cover', borderRadius: 12 }}
                      placeholder
                    />
                  ) : (
                    <div className="media-field-empty media-field-empty-wide">
                      {previewLoading ? '加载中...' : '未选择头像'}
                    </div>
                  )}
                </div>

                <div className="media-field-meta">
                  <Space wrap>
                    <Button type="primary" onClick={() => setPickerOpen(true)}>
                      {avatarAssetId ? '更换头像' : '选择头像'}
                    </Button>
                    {avatarAssetId ? (
                      <Button
                        onClick={() => {
                          form.setFieldValue('avatar_asset_id', null);
                          setPreviewAsset(null);
                        }}
                      >
                        清空
                      </Button>
                    ) : null}
                  </Space>

                  <Text type="secondary">已选资源 ID：{avatarAssetId || '未选择'}</Text>

                  {previewAsset?.file_name ? (
                    <Space direction="vertical" size={2}>
                      <Text strong>{previewAsset.file_name}</Text>
                      <Text type="secondary">{previewAsset.file_path}</Text>
                    </Space>
                  ) : (
                    <Text type="secondary">请从资源中心选择该成员对应头像。</Text>
                  )}
                </div>
              </div>
            </Form.Item>

            <Form.Item name="bio_zh" label="简介">
              <TextArea rows={5} placeholder="可填写成员简介、擅长领域等内容" />
            </Form.Item>

            <div className="form-two-column">
              <Form.Item name="publish_status" label="发布状态">
                <Select options={publishStatusFormOptions} />
              </Form.Item>
              <Form.Item name="is_home_featured" label="首页推荐" valuePropName="checked">
                <Switch checkedChildren="是" unCheckedChildren="否" />
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
        title="选择成员头像"
        assetType="image"
        selectedAssetId={avatarAssetId}
        onCancel={() => setPickerOpen(false)}
        onSelect={(asset) => {
          form.setFieldValue('avatar_asset_id', Number(asset.id));
          setPreviewAsset(asset);
          setPickerOpen(false);
        }}
      />
    </>
  );
}
