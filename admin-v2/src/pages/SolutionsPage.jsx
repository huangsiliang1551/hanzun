import { useEffect, useMemo, useState } from 'react';
import {
  Button,
  Drawer,
  Form,
  Input,
  message,
  Pagination,
  Popconfirm,
  Select,
  Space,
  Switch,
  Table,
  Tag,
  TreeSelect,
  Typography,
} from 'antd';
import ContentAiActions from '@/components/ContentAiActions';
import ContentCoverThumb from '@/components/ContentCoverThumb';
import CoverAssetField from '@/components/CoverAssetField';
import ContentWorkflowPanel from '@/components/ContentWorkflowPanel';
import MediaPickerModal from '@/components/MediaPickerModal';
import PagePlaceholder from '@/components/PagePlaceholder';
import LazyRichTextEditor from '@/components/LazyRichTextEditor';
import TableSelectionFooter from '@/components/TableSelectionFooter';
import CategoryQuickManager from '@/components/CategoryQuickManager';
import { getResourceDetail } from '@/api/resources';
import {
  batchDeleteSolutions,
  batchUpdateSolutionPublishStatus,
  createSolution,
  deleteSolution,
  getSolutionBootstrap,
  getSolutionLookups,
  getSolutions,
  restoreSolutionLive,
  updateSolution,
  updateSolutionPublishStatus,
} from '@/api/solutions';
import { buildCoverMediaGallery, getDetailCoverAssetId, getDetailCoverAssetPreview } from '@/utils/contentMedia';
import { openPublicPreview } from '@/utils/publicPreview';

const { Text } = Typography;

const publishStatusOptions = [
  { label: '全部状态', value: '' },
  { label: '已发布', value: 'published' },
  { label: '草稿', value: 'draft' },
  { label: '已下线', value: 'offline' },
];

const featuredFilterOptions = [
  { label: '全部推荐状态', value: '' },
  { label: '已推荐', value: '1' },
  { label: '未推荐', value: '0' },
];

const pdfFilterOptions = [
  { label: '全部 PDF 状态', value: '' },
  { label: '有 PDF', value: '1' },
  { label: '无 PDF', value: '0' },
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

function buildTreeOptions(nodes = []) {
  return nodes.map((item) => ({
    title: `${item.name_zh}${item.aggregate_count ? ` (${item.aggregate_count})` : ''}`,
    value: item.id,
    key: item.id,
    children: buildTreeOptions(item.children || []),
  }));
}

function normalizeNumberInput(value) {
  if (value === '' || value === null || value === undefined) {
    return null;
  }

  const nextValue = Number(value);
  return Number.isFinite(nextValue) ? nextValue : null;
}

export default function SolutionsPage() {
  const [filters, setFilters] = useState({
    keyword: '',
    publish_status: '',
    category_id: '',
    is_home_featured: '',
    pdf_status: '',
    page: 1,
    page_size: 10,
  });
  const [listLoading, setListLoading] = useState(false);
  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState({
    page: 1,
    page_size: 10,
    total: 0,
  });
  const [categories, setCategories] = useState([]);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [drawerLoading, setDrawerLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [batchSubmitting, setBatchSubmitting] = useState(false);
  const [publishing, setPublishing] = useState(false);
  const [restoring, setRestoring] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [currentDetail, setCurrentDetail] = useState(null);
  const [currentWorkflow, setCurrentWorkflow] = useState(null);
  const [manualPickerOpen, setManualPickerOpen] = useState(false);
  const [manualAsset, setManualAsset] = useState(null);
  const [selectedRowKeys, setSelectedRowKeys] = useState([]);
  const [categoryManagerOpen, setCategoryManagerOpen] = useState(false);
  const [form] = Form.useForm();
  const manualAssetId = Form.useWatch('manual_asset_id', form);

  const categoryTreeOptions = useMemo(() => buildTreeOptions(categories), [categories]);

  async function loadLookups() {
    try {
      const data = await getSolutionLookups();
      setCategories(Array.isArray(data?.categories) ? data.categories : []);
    } catch (error) {
      message.error(error.message || '加载解决方案分类失败');
    }
  }

  async function loadSolutions(nextFilters = filters) {
    setListLoading(true);
    try {
      const data = await getSolutions(nextFilters);
      setItems(Array.isArray(data.items) ? data.items : []);
      setSelectedRowKeys([]);
      setPagination(data.pagination || { page: 1, page_size: 10, total: 0 });
    } catch (error) {
      message.error(error.message || '加载解决方案列表失败');
    } finally {
      setListLoading(false);
    }
  }

  async function loadSolutionDetail(id) {
    const payload = await getSolutionBootstrap(id);
    const detail = payload?.detail || {};
    const workflow = payload?.workflow || null;

    setCurrentDetail(detail);
    setCurrentWorkflow(workflow);
    form.setFieldsValue({
      category_id: detail.category_id || undefined,
      cover_asset_id: getDetailCoverAssetId(detail) || 0,
      name_zh: detail.name_zh || '',
      content_zh: detail.content_zh || '',
      manual_asset_id:
        detail.manual_asset_id === null || detail.manual_asset_id === undefined ? '' : String(detail.manual_asset_id),
      publish_status: detail.publish_status || 'published',
      translation_status: detail.translation_status || 'pending',
      seo_status: detail.seo_status || 'pending',
      is_home_featured: Number(detail.is_home_featured || 0) === 1,
      manual_sort: Number(detail.manual_sort || 0),
    });
  }

  useEffect(() => {
    loadLookups();
  }, []);

  useEffect(() => {
    loadSolutions(filters);
  }, [filters]);

  useEffect(() => {
    if (!drawerOpen) {
      setManualAsset(null);
      return;
    }

    const assetId = Number(manualAssetId || 0);
    if (!assetId) {
      setManualAsset(null);
      return;
    }

    let disposed = false;
    getResourceDetail(assetId)
      .then((asset) => {
        if (!disposed) {
          setManualAsset(asset);
        }
      })
      .catch(() => {
        if (!disposed) {
          setManualAsset(null);
        }
      });

    return () => {
      disposed = true;
    };
  }, [drawerOpen, manualAssetId]);

  function closeDrawer() {
    setDrawerOpen(false);
    setEditingId(null);
    setManualPickerOpen(false);
    setManualAsset(null);
    setCurrentDetail(null);
    setCurrentWorkflow(null);
  }

  function openCreateDrawer() {
    setEditingId(null);
    setCurrentDetail(null);
    setCurrentWorkflow(null);
    form.resetFields();
    form.setFieldsValue({
      publish_status: 'published',
      translation_status: 'pending',
      seo_status: 'pending',
      is_home_featured: false,
      cover_asset_id: 0,
      manual_sort: 100,
      manual_asset_id: '',
      content_zh: '',
    });
    setDrawerOpen(true);
  }

  async function openEditDrawer(record) {
    setEditingId(record.id);
    setDrawerOpen(true);
    setDrawerLoading(true);
    try {
      await loadSolutionDetail(record.id);
    } catch (error) {
      message.error(error.message || '加载解决方案详情失败');
      closeDrawer();
    } finally {
      setDrawerLoading(false);
    }
  }

  async function handleSubmit(values) {
    setSubmitting(true);
    const payload = {
      ...values,
      category_id: Number(values.category_id || 0),
      manual_asset_id: normalizeNumberInput(values.manual_asset_id),
      is_home_featured: values.is_home_featured ? 1 : 0,
      manual_sort: Number(values.manual_sort || 0),
      media_gallery: buildCoverMediaGallery(
        values.cover_asset_id,
        Array.isArray(currentDetail?.media_gallery) ? currentDetail.media_gallery : [],
      ),
    };

    try {
      if (editingId) {
        await updateSolution(editingId, payload);
      } else {
        await createSolution(payload);
      }
      message.success(editingId ? '解决方案已更新' : '解决方案已创建');
      closeDrawer();
      form.resetFields();
      await loadSolutions(filters);
    } catch (error) {
      message.error(error.message || '保存解决方案失败');
    } finally {
      setSubmitting(false);
    }
  }

  async function handlePublishToggle(record, publishStatus) {
    setPublishing(true);
    try {
      await updateSolutionPublishStatus(record.id, publishStatus);
      message.success('发布状态已更新');
      if (editingId && Number(editingId) === Number(record.id)) {
        await loadSolutionDetail(record.id);
      }
      await loadSolutions(filters);
    } catch (error) {
      message.error(error.message || '更新解决方案发布状态失败');
    } finally {
      setPublishing(false);
    }
  }

  async function handleRestoreLive() {
    if (!editingId) {
      return;
    }
    setRestoring(true);
    try {
      await restoreSolutionLive(editingId);
      message.success('已从线上版本恢复草稿');
      await loadSolutionDetail(editingId);
      await loadSolutions(filters);
    } catch (error) {
      message.error(error.message || '恢复解决方案线上版本失败');
    } finally {
      setRestoring(false);
    }
  }

  async function handleDelete(record) {
    try {
      await deleteSolution(record.id);
      message.success('解决方案已删除');
      await loadSolutions(filters);
    } catch (error) {
      message.error(error.message || '删除解决方案失败');
    }
  }

  async function handleBatchPublish(publishStatus) {
    if (selectedRowKeys.length === 0) {
      message.warning('请先选择内容');
      return;
    }
    setBatchSubmitting(true);
    try {
      await batchUpdateSolutionPublishStatus(selectedRowKeys, publishStatus);
      message.success('解决方案批量发布状态已更新');
      await loadSolutions(filters);
    } catch (error) {
      message.error(error.message || '批量更新解决方案发布状态失败');
    } finally {
      setBatchSubmitting(false);
    }
  }

  async function handleBatchDelete() {
    if (selectedRowKeys.length === 0) {
      message.warning('请先选择内容');
      return;
    }
    setBatchSubmitting(true);
    try {
      await batchDeleteSolutions(selectedRowKeys);
      message.success('解决方案批量删除成功');
      await loadSolutions(filters);
    } catch (error) {
      message.error(error.message || '批量删除解决方案失败');
    } finally {
      setBatchSubmitting(false);
    }
  }

  const columns = [
    { title: 'ID', dataIndex: 'id', width: 80 },
    {
      title: '封面图片',
      key: 'cover',
      width: 92,
      render: (_, record) => <ContentCoverThumb record={record} label="解决方案" />,
    },
    {
      title: '解决方案名称',
      dataIndex: 'name_zh',
      width: 360,
      render: (value, record) => (
        <Space direction="vertical" size={2} className="content-title-cell">
          <button type="button" className="content-title-link" onClick={() => openPublicPreview('solution', record)}>
            <span className="content-title-primary">{value || '-'}</span>
          </button>
          <Text type="secondary" className="content-title-secondary">{record.category_name || '未分类'}</Text>
        </Space>
      ),
    },
    { title: '发布状态', dataIndex: 'publish_status', width: 120, render: renderPublishStatus },
    {
      title: '首页推荐',
      dataIndex: 'is_home_featured',
      width: 100,
      render: (value) => (Number(value || 0) === 1 ? <Tag color="blue">是</Tag> : '否'),
    },
    { title: '排序值', dataIndex: 'manual_sort', width: 90 },
    { title: '浏览量', dataIndex: 'views_count', width: 98, render: (value) => Number(value || 0) },
    {
      title: '操作',
      key: 'actions',
      width: 300,
      render: (_, record) => (
        <Space wrap>
          <Button size="small" onClick={() => openPublicPreview('solution', record)}>查看页面</Button>
          <Button size="small" onClick={() => openEditDrawer(record)}>编辑</Button>
          {record.publish_status === 'published' ? (
            <Button size="small" onClick={() => handlePublishToggle(record, 'offline')}>下线</Button>
          ) : (
            <Button size="small" type="primary" onClick={() => handlePublishToggle(record, 'published')}>发布</Button>
          )}
          <Popconfirm title="确认删除该解决方案吗？" okText="删除" cancelText="取消" onConfirm={() => handleDelete(record)}>
            <Button size="small" danger>删除</Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <>
      <PagePlaceholder hideHeader compact tags={[`当前 ${items.length} 条`, `已选 ${selectedRowKeys.length} 条`]}>
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <div className="toolbar-surface">
            <Space wrap size={12} style={{ width: '100%', justifyContent: 'space-between' }}>
              <Space wrap size={12}>
                <Button type="default" className="toolbar-quick-action" onClick={() => setCategoryManagerOpen(true)}>
                  分类管理
                </Button>
                <Input.Search
                  allowClear
                  placeholder="搜索解决方案名称"
                  style={{ width: 260 }}
                  onSearch={(value) => setFilters((current) => ({ ...current, keyword: value.trim(), page: 1 }))}
                />
                <Select
                  style={{ width: 170 }}
                  options={publishStatusOptions}
                  value={filters.publish_status}
                  onChange={(value) => setFilters((current) => ({ ...current, publish_status: value, page: 1 }))}
                />
                <Select
                  style={{ width: 170 }}
                  options={featuredFilterOptions}
                  value={filters.is_home_featured}
                  onChange={(value) => setFilters((current) => ({ ...current, is_home_featured: value, page: 1 }))}
                />
                <Select
                  style={{ width: 170 }}
                  options={pdfFilterOptions}
                  value={filters.pdf_status}
                  onChange={(value) => setFilters((current) => ({ ...current, pdf_status: value, page: 1 }))}
                />
                <TreeSelect
                  allowClear
                  treeDefaultExpandAll
                  style={{ width: 280 }}
                  placeholder="按分类筛选"
                  treeData={categoryTreeOptions}
                  value={filters.category_id || undefined}
                  onChange={(value) => setFilters((current) => ({ ...current, category_id: value || '', page: 1 }))}
                />
              </Space>
              <Space wrap size={8}>
                <Button onClick={() => loadSolutions(filters)}>刷新列表</Button>
                <Button type="primary" onClick={openCreateDrawer}>新建解决方案</Button>
              </Space>
            </Space>
          </div>

          <Table
            className="content-list-table"
            rowKey="id"
            loading={listLoading}
            columns={columns}
            dataSource={items}
            rowSelection={{ selectedRowKeys, onChange: setSelectedRowKeys }}
            pagination={false}
            tableLayout="fixed"
            scroll={{ x: 1160 }}
          />

          <TableSelectionFooter
            rowKeys={items.map((item) => item.id)}
            selectedRowKeys={selectedRowKeys}
            onChange={setSelectedRowKeys}
            actions={
              <>
                <Button onClick={() => handleBatchPublish('draft')} loading={batchSubmitting}>批量下线</Button><Button onClick={() => handleBatchPublish('draft')} loading={batchSubmitting}>批量转草稿</Button>
                <Button onClick={() => handleBatchPublish('published')} loading={batchSubmitting}>批量发布</Button>
                <Popconfirm
                  title="确认删除所选解决方案吗？"
                  okText="删除"
                  cancelText="取消"
                  onConfirm={handleBatchDelete}
                  disabled={selectedRowKeys.length === 0}
                >
                  <Button danger loading={batchSubmitting} disabled={selectedRowKeys.length === 0}>批量删除</Button>
                </Popconfirm>
              </>
            }
            pagination={
              <Pagination
                current={pagination.page || filters.page}
                pageSize={pagination.page_size || filters.page_size}
                total={pagination.total || 0}
                showSizeChanger
                onChange={(page, pageSize) => setFilters((current) => ({ ...current, page, page_size: pageSize }))}
              />
            }
          />
        </Space>
      </PagePlaceholder>

      <CategoryQuickManager
        open={categoryManagerOpen}
        onClose={() => setCategoryManagerOpen(false)}
        onSaved={() => {
          setCategoryManagerOpen(false);
          loadLookups();
        }}
        entityType="solution"
      />

      <Drawer
        title={editingId ? '编辑解决方案' : '新建解决方案'}
        width={1200}
        rootClassName="content-editor-drawer"
        open={drawerOpen}
        onClose={closeDrawer}
        destroyOnHidden
        extra={
          <Space wrap className="content-editor-drawer-actions">
            <ContentAiActions
              form={form}
              entityLabel="解决方案"
              entityType="solution"
              entityId={editingId}
              titleField="name_zh"
              buttons={['translation']}
            />
            <Button onClick={closeDrawer}>取消</Button>
            {editingId ? (
              <Button
                loading={restoring}
                disabled={Number(currentWorkflow?.has_live_snapshot || 0) !== 1}
                onClick={handleRestoreLive}
              >
                恢复线上
              </Button>
            ) : null}
            <Button type="primary" loading={submitting} onClick={() => form.submit()}>保存</Button>
          </Space>
        }
      >
        <Form
          form={form}
          layout="vertical"
          onFinish={handleSubmit}
          disabled={drawerLoading || submitting}
          className="content-editor-form"
        >
          <div className="content-editor-row">
            <Form.Item
              name="category_id"
              label="分类"
              className="content-editor-col-label-12"
              rules={[{ required: true, message: '请选择分类' }]}
            >
              <TreeSelect treeDefaultExpandAll treeData={categoryTreeOptions} placeholder="请选择分类" />
            </Form.Item>

            <Form.Item
              name="name_zh"
              label="解决方案名称"
              className="content-editor-col-fill"
              rules={[{ required: true, message: '请输入解决方案名称' }]}
            >
              <Input placeholder="请输入解决方案名称" />
            </Form.Item>
          </div>

          <div className="content-editor-row">
            <Form.Item name="publish_status" label="发布状态" className="content-editor-col-compact">
              <Select options={publishStatusOptions.slice(1)} />
            </Form.Item>
            <Form.Item name="manual_sort" label="排序值" className="content-editor-col-compact">
              <Input type="number" />
            </Form.Item>
            <Form.Item name="is_home_featured" label="首页推荐" valuePropName="checked" className="content-editor-col-compact">
              <Switch checkedChildren="是" unCheckedChildren="否" />
            </Form.Item>
          </div>

          <Form.Item name="manual_asset_id" hidden>
            <Input />
          </Form.Item>

          <div className="form-two-column">
            <CoverAssetField form={form} label="封面图片" helper="从资源中心选择一张图片作为解决方案封面。" previewAsset={getDetailCoverAssetPreview(currentDetail)} />

            <Form.Item label="方案PDF">
              <div className="media-field-shell media-field-shell-wide">
                <div className="media-field-empty media-field-empty-wide">
                  {manualAsset ? '已选择 PDF' : '尚未选择 PDF'}
                </div>
                <div className="media-field-meta">
                  <Space wrap>
                    <Button type="primary" onClick={() => setManualPickerOpen(true)}>
                      {manualAssetId ? '更换 PDF' : '选择 PDF'}
                    </Button>
                    {manualAssetId ? (
                      <Button
                        onClick={() => {
                          form.setFieldValue('manual_asset_id', '');
                          setManualAsset(null);
                        }}
                      >
                        移除
                      </Button>
                    ) : null}
                  </Space>
                  <Text type="secondary">{`已选资源ID：${manualAssetId || '无'}`}</Text>
                  {manualAsset?.file_name ? (
                    <Space direction="vertical" size={2}>
                      <Text strong>{manualAsset.file_name}</Text>
                      <Text type="secondary">{manualAsset.file_path}</Text>
                    </Space>
                  ) : (
                    <Text type="secondary">从资源中心选择一个 PDF 作为下载资料或配套说明。</Text>
                  )}
                </div>
              </div>
            </Form.Item>
          </div>

          <Form.Item name="content_zh" label="详细内容">
            <LazyRichTextEditor active={drawerOpen} minHeight={360} placeholder="请输入解决方案内容。" />
          </Form.Item>

          {editingId ? (
            <ContentWorkflowPanel
              workflow={currentWorkflow}
              detail={currentDetail}
              loading={drawerLoading || publishing}
              restoring={restoring}
              onRestore={handleRestoreLive}
            />
          ) : null}
        </Form>
      </Drawer>

      <MediaPickerModal
        open={manualPickerOpen}
        title="选择解决方案 PDF"
        assetType="pdf"
        selectedAssetId={manualAssetId}
        onCancel={() => setManualPickerOpen(false)}
        onSelect={(asset) => {
          form.setFieldValue('manual_asset_id', String(asset.id));
          setManualAsset(asset);
          setManualPickerOpen(false);
        }}
      />
    </>
  );
}


