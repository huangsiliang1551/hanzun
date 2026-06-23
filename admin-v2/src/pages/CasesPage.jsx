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
import PagePlaceholder from '@/components/PagePlaceholder';
import LazyRichTextEditor from '@/components/LazyRichTextEditor';
import TableSelectionFooter from '@/components/TableSelectionFooter';
import {
  batchDeleteCases,
  batchUpdateCasePublishStatus,
  createCase,
  deleteCase,
  getCaseBootstrap,
  getCaseLookups,
  getCases,
  restoreCaseLive,
  updateCase,
  updateCasePublishStatus,
} from '@/api/cases';
import { buildCoverMediaGallery, getDetailCoverAssetId } from '@/utils/contentMedia';
import { openPublicPreview } from '@/utils/publicPreview';

const { TextArea } = Input;
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

function parseIdList(value) {
  if (!value) {
    return [];
  }

  if (Array.isArray(value)) {
    return value
      .map((item) => Number(item))
      .filter((item) => Number.isInteger(item) && item > 0);
  }

  return String(value)
    .split(',')
    .map((item) => Number(item.trim()))
    .filter((item) => Number.isInteger(item) && item > 0);
}

function buildRecordOptions(items = []) {
  return items.map((item) => ({
    value: Number(item.id),
    label: `${item.name_zh || item.title_zh || `#${item.id}`} (#${item.id})`,
  }));
}

export default function CasesPage() {
  const [filters, setFilters] = useState({
    keyword: '',
    publish_status: '',
    category_id: '',
    is_home_featured: '',
    country_code: '',
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
  const [selectedRowKeys, setSelectedRowKeys] = useState([]);
  const [relatedProductOptions, setRelatedProductOptions] = useState([]);
  const [relatedSolutionOptions, setRelatedSolutionOptions] = useState([]);
  const [form] = Form.useForm();

  const categoryTreeOptions = useMemo(() => buildTreeOptions(categories), [categories]);
  async function loadLookups() {
    try {
      const payload = await getCaseLookups();
      const productResult = payload?.products || {};
      const solutionResult = payload?.solutions || {};

      setCategories(Array.isArray(payload?.categories) ? payload.categories : []);
      setRelatedProductOptions(buildRecordOptions(productResult?.items || []));
      setRelatedSolutionOptions(buildRecordOptions(solutionResult?.items || []));
    } catch (error) {
      message.error(error.message || '加载案例引用数据失败。');
    }
  }

  async function loadCaseList(nextFilters = filters) {
    setListLoading(true);

    try {
      const data = await getCases(nextFilters);
      setItems(Array.isArray(data.items) ? data.items : []);
      setSelectedRowKeys([]);
      setPagination(data.pagination || { page: 1, page_size: 10, total: 0 });
    } catch (error) {
      message.error(error.message || '加载案例列表失败。');
    } finally {
      setListLoading(false);
    }
  }

  async function loadCaseDetail(id) {
    const payload = await getCaseBootstrap(id);
    const detail = payload?.detail || {};
    const workflow = payload?.workflow || null;

    setCurrentDetail(detail);
    setCurrentWorkflow(workflow);
    form.setFieldsValue({
      category_id: detail.category_id || undefined,
      cover_asset_id: getDetailCoverAssetId(detail) || 0,
      title_zh: detail.title_zh || '',
      content_zh: detail.content_zh || '',
      country_code: detail.country_code || '',
      case_tags: detail.case_tags || '',
      related_solution_ids: parseIdList(detail.related_solution_ids),
      related_product_ids: parseIdList(detail.related_product_ids),
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
    loadCaseList(filters);
  }, [filters]);

  function closeDrawer() {
    setDrawerOpen(false);
    setEditingId(null);
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
      country_code: '',
      case_tags: '',
      related_solution_ids: [],
      related_product_ids: [],
      content_zh: '',
    });
    setDrawerOpen(true);
  }

  async function openEditDrawer(record) {
    setEditingId(record.id);
    setDrawerOpen(true);
    setDrawerLoading(true);

    try {
      await loadCaseDetail(record.id);
    } catch (error) {
      message.error(error.message || '加载案例详情失败。');
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
      country_code: (values.country_code || '').trim().toUpperCase(),
      related_solution_ids: parseIdList(values.related_solution_ids),
      related_product_ids: parseIdList(values.related_product_ids),
      is_home_featured: values.is_home_featured ? 1 : 0,
      manual_sort: Number(values.manual_sort || 0),
      media_gallery: buildCoverMediaGallery(
        values.cover_asset_id,
        Array.isArray(currentDetail?.media_gallery) ? currentDetail.media_gallery : [],
      ),
    };

    try {
      if (editingId) {
        await updateCase(editingId, payload);
        message.success('案例已更新。');
      } else {
        await createCase(payload);
        message.success('案例已创建。');
      }

      closeDrawer();
      form.resetFields();
      await loadCaseList(filters);
    } catch (error) {
      message.error(error.message || '保存案例失败。');
    } finally {
      setSubmitting(false);
    }
  }

  async function handlePublishToggle(record, publishStatus) {
    setPublishing(true);

    try {
      await updateCasePublishStatus(record.id, publishStatus);
      message.success('发布状态已更新。');
      if (editingId && Number(editingId) === Number(record.id)) {
        await loadCaseDetail(record.id);
      }
      await loadCaseList(filters);
    } catch (error) {
      message.error(error.message || '更新发布状态失败。');
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
      await restoreCaseLive(editingId);
      message.success('已从线上版本恢复草稿。');
      await loadCaseDetail(editingId);
      await loadCaseList(filters);
    } catch (error) {
      message.error(error.message || '恢复线上版本失败。');
    } finally {
      setRestoring(false);
    }
  }

  async function handleDelete(record) {
    try {
      await deleteCase(record.id);
      message.success('案例已删除。');
      await loadCaseList(filters);
    } catch (error) {
      message.error(error.message || '删除案例失败。');
    }
  }

  async function handleBatchPublish(publishStatus) {
    if (selectedRowKeys.length === 0) {
      message.warning('请先选择至少一个案例。');
      return;
    }

    setBatchSubmitting(true);

    try {
      await batchUpdateCasePublishStatus(selectedRowKeys, publishStatus);
      message.success('批量发布状态已更新。');
      await loadCaseList(filters);
    } catch (error) {
      message.error(error.message || '批量更新发布状态失败。');
    } finally {
      setBatchSubmitting(false);
    }
  }

  async function handleBatchDelete() {
    if (selectedRowKeys.length === 0) {
      message.warning('请先选择至少一个案例。');
      return;
    }

    setBatchSubmitting(true);

    try {
      await batchDeleteCases(selectedRowKeys);
      message.success('已删除所选案例。');
      await loadCaseList(filters);
    } catch (error) {
      message.error(error.message || '删除所选案例失败。');
    } finally {
      setBatchSubmitting(false);
    }
  }

  const columns = [
    {
      title: 'ID',
      dataIndex: 'id',
      width: 80,
    },
    {
      title: '封面',
      key: 'cover',
      width: 92,
      render: (_, record) => <ContentCoverThumb record={record} label="案例" />,
    },
    {
      title: '案例标题',
      dataIndex: 'title_zh',
      width: 280,
      render: (value, record) => (
        <Space direction="vertical" size={2} className="content-title-cell">
          <button type="button" className="content-title-link" onClick={() => openPublicPreview('case', record)}>
            <span className="content-title-primary">{value || '-'}</span>
          </button>
          <Text type="secondary" className="content-title-secondary">{record.category_name || '未分类'}</Text>
        </Space>
      ),
    },
    {
      title: '国家',
      dataIndex: 'country_code',
      width: 110,
      render: (value) => value || '-',
    },
    {
      title: '发布状态',
      dataIndex: 'publish_status',
      width: 120,
      render: renderPublishStatus,
    },
    {
      title: '首页推荐',
      dataIndex: 'is_home_featured',
      width: 100,
      render: (value) => (Number(value || 0) === 1 ? <Tag color="blue">是</Tag> : '否'),
    },
    {
      title: '浏览量',
      dataIndex: 'views_count',
      width: 100,
    },
    {
      title: '排序',
      dataIndex: 'manual_sort',
      width: 90,
    },
    {
      title: '操作',
      key: 'actions',
      width: 360,
      render: (_, record) => (
        <Space wrap>
          <Button size="small" onClick={() => openPublicPreview('case', record)}>
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
          <Popconfirm
            title="确认删除该案例吗？"
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
        tags={[`当前 ${items.length} 条`, `已选 ${selectedRowKeys.length} 条`]}
      >
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <div className="toolbar-surface">
            <Space wrap size={12} style={{ width: '100%', justifyContent: 'space-between' }}>
              <Space wrap size={12}>
                <Input.Search
                  allowClear
                  placeholder="搜索案例标题或标签"
                  style={{ width: 260 }}
                  onSearch={(value) =>
                    setFilters((current) => ({
                      ...current,
                      keyword: value.trim(),
                      page: 1,
                    }))
                  }
                />
                <Input
                  placeholder="国家代码"
                  style={{ width: 140 }}
                  value={filters.country_code}
                  onChange={(event) =>
                    setFilters((current) => ({
                      ...current,
                      country_code: event.target.value.toUpperCase(),
                      page: 1,
                    }))
                  }
                />
                <Select
                  style={{ width: 170 }}
                  options={publishStatusOptions}
                  value={filters.publish_status}
                  onChange={(value) =>
                    setFilters((current) => ({
                      ...current,
                      publish_status: value,
                      page: 1,
                    }))
                  }
                />
                <Select
                  style={{ width: 170 }}
                  options={featuredFilterOptions}
                  value={filters.is_home_featured}
                  onChange={(value) =>
                    setFilters((current) => ({
                      ...current,
                      is_home_featured: value,
                      page: 1,
                    }))
                  }
                />
                <TreeSelect
                  allowClear
                  treeDefaultExpandAll
                  style={{ width: 280 }}
                  placeholder="按分类筛选"
                  treeData={categoryTreeOptions}
                  value={filters.category_id || undefined}
                  onChange={(value) =>
                    setFilters((current) => ({
                      ...current,
                      category_id: value || '',
                      page: 1,
                    }))
                  }
                />
              </Space>
              <Space wrap size={8}>
                <Button onClick={() => loadCaseList(filters)}>刷新列表</Button>
                <Button type="primary" onClick={openCreateDrawer}>
                  新建案例
                </Button>
              </Space>
            </Space>
          </div>

          <Table
            className="content-list-table"
            rowKey="id"
            loading={listLoading}
            columns={columns}
            dataSource={items}
            rowSelection={{
              selectedRowKeys,
              onChange: setSelectedRowKeys,
            }}
            pagination={false}
            tableLayout="fixed"
            scroll={{ x: 1260 }}
          />
          <TableSelectionFooter
            rowKeys={items.map((item) => item.id)}
            selectedRowKeys={selectedRowKeys}
            onChange={setSelectedRowKeys}
            actions={
              <>
                <Button onClick={() => handleBatchPublish('draft')} loading={batchSubmitting}>
                  批量转草稿
                </Button>
                <Button onClick={() => handleBatchPublish('published')} loading={batchSubmitting}>
                  批量发布
                </Button>
                <Popconfirm
                  title="确认删除所选案例吗？"
                  okText="删除"
                  cancelText="取消"
                  onConfirm={handleBatchDelete}
                  disabled={selectedRowKeys.length === 0}
                >
                  <Button danger loading={batchSubmitting} disabled={selectedRowKeys.length === 0}>
                    批量删除
                  </Button>
                </Popconfirm>
              </>
            }
            pagination={
              <Pagination
                current={pagination.page || filters.page}
                pageSize={pagination.page_size || filters.page_size}
                total={pagination.total || 0}
                showSizeChanger
                onChange={(page, pageSize) =>
                  setFilters((current) => ({
                    ...current,
                    page,
                    page_size: pageSize,
                  }))
                }
              />
            }
          />
        </Space>
      </PagePlaceholder>

      <Drawer
        title={editingId ? '编辑案例' : '新建案例'}
        width={1200}
        rootClassName="content-editor-drawer"
        open={drawerOpen}
        onClose={closeDrawer}
        destroyOnHidden
        extra={
          <Space wrap className="content-editor-drawer-actions">
            <ContentAiActions form={form} entityLabel="案例" entityType="case" entityId={editingId} buttons={['translation']} />
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
            <Button type="primary" loading={submitting} onClick={() => form.submit()}>
              保存
            </Button>
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
          <CoverAssetField form={form} label="封面图片" helper="从资源中心选择一张图片作为案例封面。" />
          <Form.Item
            name="title_zh"
            label="案例标题"
            rules={[{ required: true, message: '请输入案例标题。' }]}
          >
            <Input placeholder="请输入案例标题" />
          </Form.Item>

          <Form.Item name="country_code" label="国家代码">
            <Input placeholder="例如 CN" maxLength={12} />
          </Form.Item>

          <div className="form-four-column">
            <Form.Item name="category_id" label="分类">
              <TreeSelect allowClear treeDefaultExpandAll treeData={categoryTreeOptions} placeholder="请选择分类" />
            </Form.Item>

            <Form.Item name="publish_status" label="发布状态">
              <Select options={publishStatusOptions.slice(1)} />
            </Form.Item>

            <Form.Item name="is_home_featured" label="首页推荐" valuePropName="checked">
              <Switch checkedChildren="是" unCheckedChildren="否" />
            </Form.Item>

            <Form.Item name="manual_sort" label="排序值">
              <Input type="number" />
            </Form.Item>
          </div>
          <Form.Item name="case_tags" label="案例标签">
            <Input placeholder="例如：烘焙、出口、整线" />
          </Form.Item>

          <div className="form-two-column">
            <Form.Item name="related_solution_ids" label="关联解决方案">
              <Select
                mode="multiple"
                allowClear
                showSearch
                optionFilterProp="label"
                options={relatedSolutionOptions}
                placeholder="请选择关联解决方案"
              />
            </Form.Item>

            <Form.Item name="related_product_ids" label="关联产品">
              <Select
                mode="multiple"
                allowClear
                showSearch
                optionFilterProp="label"
                options={relatedProductOptions}
                placeholder="请选择关联产品"
              />
            </Form.Item>
          </div>


          <Form.Item name="content_zh" label="案例正文">
            <LazyRichTextEditor active={drawerOpen} minHeight={360} placeholder="请输入案例正文内容。" />
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
    </>
  );
}
