import { useEffect, useState } from 'react';
import {
  Alert,
  Button,
  Card,
  Col,
  Descriptions,
  Empty,
  Form,
  Input,
  List,
  Pagination,
  Popconfirm,
  Row,
  Select,
  Space,
  Spin,
  Tag,
  Typography,
  message,
} from 'antd';
import ContentAiActions from '@/components/ContentAiActions';
import ContentCoverThumb from '@/components/ContentCoverThumb';
import CoverAssetField from '@/components/CoverAssetField';
import LazyRichTextEditor from '@/components/LazyRichTextEditor';
import {
  createPage,
  deletePage,
  getPageBootstrap,
  getPagesBootstrap,
  restorePageLive,
  updatePage,
  updatePagePublishStatus,
} from '../api/pages';
import PagePlaceholder from '@/components/PagePlaceholder';
import { buildCoverMediaGallery, getDetailCoverAssetId } from '@/utils/contentMedia';

const { TextArea } = Input;
const { Paragraph, Text, Title } = Typography;

const defaultFilters = {
  keyword: '',
  publish_status: '',
  page_type: '',
  page: 1,
  page_size: 10,
};

const pageTypeOptions = [
  { label: '全部类型', value: '' },
  { label: '普通单页', value: 'page' },
  { label: '活动页', value: 'campaign' },
  { label: '落地页', value: 'landing' },
];

const publishOptions = [
  { label: '全部状态', value: '' },
  { label: '草稿', value: 'draft' },
  { label: '已发布', value: 'published' },
  { label: '已下线', value: 'offline' },
];

const pageTypeLabelMap = {
  page: '普通单页',
  campaign: '活动页',
  landing: '落地页',
};

const taskStatusLabelMap = {
  pending: '待处理',
  processing: '处理中',
  completed: '已完成',
  failed: '失败',
  skipped: '已跳过',
};

function renderStatusTag(status) {
  const colorMap = {
    draft: 'default',
    published: 'success',
    offline: 'warning',
  };

  const labelMap = {
    draft: '草稿',
    published: '已发布',
    offline: '已下线',
  };

  return <Tag color={colorMap[status] || 'default'}>{labelMap[status] || status || '-'}</Tag>;
}

function renderTaskStatusTag(status) {
  const colorMap = {
    pending: 'default',
    processing: 'processing',
    completed: 'success',
    failed: 'error',
    skipped: 'warning',
  };

  return <Tag color={colorMap[status] || 'default'}>{taskStatusLabelMap[status] || status || '-'}</Tag>;
}

function getDefaultPageValues() {
  return {
    cover_asset_id: 0,
    title_zh: '',
    page_type: 'page',
    content_zh: '',
    publish_status: 'published',
    translation_status: 'pending',
    seo_status: 'pending',
  };
}

export default function PagesPage() {
  const [filters, setFilters] = useState(defaultFilters);
  const [keywordInput, setKeywordInput] = useState('');
  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState({
    page: 1,
    page_size: 10,
    total: 0,
  });
  const [listLoading, setListLoading] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [publishing, setPublishing] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [restoring, setRestoring] = useState(false);
  const [currentId, setCurrentId] = useState(null);
  const [currentDetail, setCurrentDetail] = useState(null);
  const [currentWorkflow, setCurrentWorkflow] = useState(null);
  const [form] = Form.useForm();

  const publishedCount = items.filter((item) => item.publish_status === 'published').length;
  const draftCount = items.filter((item) => item.publish_status === 'draft').length;
  const pendingChangesCount = items.filter((item) => Number(item.has_unpublished_changes || 0) === 1).length;

  async function loadPageDetail(id) {
    if (!id) {
      setCurrentId(null);
      setCurrentDetail(null);
      setCurrentWorkflow(null);
      form.setFieldsValue(getDefaultPageValues());
      return;
    }

    setDetailLoading(true);

    try {
      const payload = await getPageBootstrap(id);
      const detail = payload?.detail || {};
      const workflow = payload?.workflow || null;
      setCurrentId(Number(id));
      setCurrentDetail(detail);
      setCurrentWorkflow(workflow);
      form.setFieldsValue({
        cover_asset_id: getDetailCoverAssetId(detail) || 0,
        title_zh: detail.title_zh || '',
        page_type: detail.page_type || 'page',
        content_zh: detail.content_zh || '',
        publish_status: detail.publish_status || 'published',
        translation_status: detail.translation_status || 'pending',
        seo_status: detail.seo_status || 'pending',
      });
    } catch (error) {
      message.error(error.message || '加载单页列表失败');
    } finally {
      setDetailLoading(false);
    }
  }

  async function loadPageList(nextFilters = filters, preferredId = currentId) {
    setListLoading(true);

    try {
      const data = await getPagesBootstrap({
        ...nextFilters,
        preferred_id: Number(preferredId || 0) || undefined,
      });
      const listPayload = data?.list || {};
      const nextItems = Array.isArray(listPayload.items) ? listPayload.items : [];
      const nextPagination = listPayload.pagination || {
        page: 1,
        page_size: 10,
        total: 0,
      };

      setItems(nextItems);
      setPagination(nextPagination);

      if (!nextItems.length) {
        setCurrentId(null);
        setCurrentDetail(null);
        setCurrentWorkflow(null);
        form.setFieldsValue(getDefaultPageValues());
        return;
      }

      const detail = data?.detail || null;
      const workflow = data?.workflow || null;
      const nextId = Number(data?.current_id || nextItems[0]?.id || 0);
      setCurrentId(nextId || null);
      setCurrentDetail(detail);
      setCurrentWorkflow(workflow);
      form.setFieldsValue({
        cover_asset_id: getDetailCoverAssetId(detail) || 0,
        title_zh: detail?.title_zh || '',
        page_type: detail?.page_type || 'page',
        content_zh: detail?.content_zh || '',
        publish_status: detail?.publish_status || 'published',
        translation_status: detail?.translation_status || 'pending',
        seo_status: detail?.seo_status || 'pending',
      });
    } catch (error) {
      message.error(error.message || '加载单页详情失败');
    } finally {
      setListLoading(false);
    }
  }

  useEffect(() => {
    loadPageList(filters);
  }, [filters]);

  useEffect(() => {
    setKeywordInput(String(filters.keyword || ''));
  }, [filters.keyword]);

  function handleCreate() {
    setCurrentId(null);
    setCurrentDetail(null);
    setCurrentWorkflow(null);
    form.setFieldsValue(getDefaultPageValues());
  }

  function handleResetFilters() {
    setKeywordInput('');
    setFilters(defaultFilters);
  }

  async function handleSave(values) {
    setSaving(true);

    const payload = {
      media_gallery: buildCoverMediaGallery(
        values.cover_asset_id,
        Array.isArray(currentDetail?.media_gallery) ? currentDetail.media_gallery : [],
      ),
      page_type: values.page_type,
      title_zh: values.title_zh,
      content_zh: values.content_zh || '',
      publish_status: values.publish_status || currentDetail?.publish_status || 'published',
      translation_status: currentDetail?.translation_status || 'pending',
      seo_status: currentDetail?.seo_status || 'pending',
    };

    try {
      const response = currentId ? await updatePage(currentId, payload) : await createPage(payload);
      message.success(currentId ? '单页已更新' : '单页已创建');
      await loadPageList(filters, Number(response.id));
    } catch (error) {
      message.error(error.message || '保存单页失败');
    } finally {
      setSaving(false);
    }
  }

  async function handlePublishToggle() {
    if (!currentId || !currentDetail) {
      return;
    }

    setPublishing(true);

    try {
      const nextStatus = currentDetail.publish_status === 'published' ? 'draft' : 'published';
      await updatePagePublishStatus(currentId, nextStatus);
      message.success(nextStatus === 'published' ? '单页已发布' : '单页已切换为草稿或下线');
      await loadPageList(filters, currentId);
    } catch (error) {
      message.error(error.message || '更新单页发布状态失败');
    } finally {
      setPublishing(false);
    }
  }

  async function handleRestoreLive() {
    if (!currentId) {
      return;
    }

    setRestoring(true);

    try {
      await restorePageLive(currentId);
      message.success('单页已从线上版本恢复草稿');
      await loadPageList(filters, currentId);
    } catch (error) {
      message.error(error.message || '恢复单页草稿失败');
    } finally {
      setRestoring(false);
    }
  }

  async function handleDeleteCurrent() {
    if (!currentId) {
      return;
    }

    setDeleting(true);

    try {
      await deletePage(currentId);
      message.success('单页已删除');
      await loadPageList(filters, null);
    } catch (error) {
      message.error(error.message || '删除单页失败');
    } finally {
      setDeleting(false);
    }
  }

  return (
    <PagePlaceholder
      hideHeader
      compact
      tags={[
        `总数 ${pagination.total || 0}`,
        `已发布 ${publishedCount}`,
        `草稿 ${draftCount}`,
        `待发布改动 ${pendingChangesCount}`,
      ]}
    >
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <div className="toolbar-surface">
          <Space wrap size={12} style={{ width: '100%', justifyContent: 'space-between' }}>
            <Space wrap>
              <Button onClick={handleCreate}>新建单页</Button>
              <ContentAiActions form={form} entityLabel="单页" entityType="page" entityId={currentId} buttons={['translation']} />
              <Button type="primary" loading={saving} onClick={() => form.submit()}>
                保存
              </Button>
            <Button loading={publishing} disabled={!currentId} onClick={handlePublishToggle}>
              {currentDetail?.publish_status === 'published' ? '转为草稿' : '发布'}
            </Button>
            <Button
              loading={restoring}
              disabled={!currentId || Number(currentWorkflow?.has_live_snapshot || 0) !== 1}
              onClick={handleRestoreLive}
            >
              恢复线上版本
            </Button>
            <Popconfirm
              title="确认删除当前单页吗？"
              okText="删除"
              cancelText="取消"
              disabled={!currentId}
              onConfirm={handleDeleteCurrent}
            >
              <Button danger loading={deleting} disabled={!currentId}>
                删除
              </Button>
            </Popconfirm>
          </Space>
        </Space>
      </div>

      <Row gutter={16} align="stretch">
        <Col xs={24} lg={8}>
          <Card title="单页列表" size="small" style={{ height: '100%' }}>
            <Space direction="vertical" size={12} style={{ width: '100%' }}>
              <div className="toolbar-surface">
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                  <Input.Search
                    allowClear
                    value={keywordInput}
                    placeholder="搜索页面标题"
                    onChange={(event) => setKeywordInput(event.target.value)}
                    onSearch={(value) =>
                      setFilters((current) => ({
                        ...current,
                        keyword: value.trim(),
                        page: 1,
                      }))
                    }
                  />
                  <div className="form-two-column">
                    <Select
                      options={pageTypeOptions}
                      value={filters.page_type}
                      onChange={(value) =>
                        setFilters((current) => ({
                          ...current,
                          page_type: value,
                          page: 1,
                        }))
                      }
                    />
                    <Select
                      options={publishOptions}
                      value={filters.publish_status}
                      onChange={(value) =>
                        setFilters((current) => ({
                          ...current,
                          publish_status: value,
                          page: 1,
                        }))
                      }
                    />
                  </div>
                  <Space wrap>
                    <Button onClick={handleResetFilters}>重置筛选</Button>
                    <Button onClick={() => loadPageList(filters, currentId)}>刷新列表</Button>
                  </Space>
                </Space>
              </div>

              <Alert
                type="info"
                showIcon
                message="单页发布流程"
                description="每个单页都有独立的草稿、发布状态和线上恢复记录。选择左侧单页即可在右侧编辑。"
              />

              <Spin spinning={listLoading}>
                {items.length ? (
                  <List
                    dataSource={items}
                    renderItem={(item) => (
                      <List.Item
                        style={{
                          cursor: 'pointer',
                          borderRadius: 8,
                          padding: 12,
                          background: Number(item.id) === Number(currentId) ? '#f0f5ff' : 'transparent',
                        }}
                        onClick={() => loadPageDetail(item.id)}
                      >
                        <Space size={12} align="start" style={{ width: '100%' }}>
                          <ContentCoverThumb record={item} label="单页" />
                          <Space direction="vertical" size={4} style={{ width: '100%' }}>
                            <Space align="center" style={{ width: '100%', justifyContent: 'space-between' }}>
                              <Text strong>{item.title_zh || `单页 #${item.id}`}</Text>
                              <Space size={6}>
                                {Number(item.has_unpublished_changes || 0) === 1 ? (
                                  <Tag color="gold">草稿有改动</Tag>
                                ) : null}
                                {renderStatusTag(item.publish_status)}
                              </Space>
                            </Space>
                            <Text type="secondary">
                              {pageTypeLabelMap[item.page_type] || item.page_type || '普通单页'}
                            </Text>
                          </Space>
                        </Space>
                      </List.Item>
                    )}
                  />
                ) : (
                  <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="暂无单页内容">
                    <Button type="primary" onClick={handleCreate}>
                      新建第一个单页
                    </Button>
                  </Empty>
                )}
              </Spin>

              <Pagination
                size="small"
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
            </Space>
          </Card>
        </Col>

        <Col xs={24} lg={16}>
          <Card
            title={currentId ? `编辑单页 #${currentId}` : '新建单页'}
            size="small"
            extra={
              currentDetail || currentWorkflow ? (
                <Space size={8} wrap>
                  {currentDetail ? renderStatusTag(currentDetail.publish_status) : null}
                  {currentDetail ? renderTaskStatusTag(currentDetail.translation_status || 'pending') : null}
                  {currentDetail ? renderTaskStatusTag(currentDetail.seo_status || 'pending') : null}
                  {currentWorkflow?.has_unpublished_changes ? (
                    <Tag color="gold">待发布改动</Tag>
                  ) : (
                    <Tag color="success">已与线上同步</Tag>
                  )}
                </Space>
              ) : null
            }
          >
            <Spin spinning={detailLoading}>
              <Form
                form={form}
                layout="vertical"
                onFinish={handleSave}
                initialValues={getDefaultPageValues()}
                className="content-editor-form"
              >
                <CoverAssetField form={form} label="封面图片" helper="从资源中心选择一张图片作为单页封面。" />

                <Alert
                  type="info"
                  showIcon
                  style={{ marginBottom: 16 }}
                  message={currentId ? `正在编辑：${currentDetail?.title_zh || `单页 #${currentId}`}` : '正在新建独立单页'}
                  description="适用于公司介绍、说明页和独立营销页。"
                />

                <Row gutter={16}>
                  <Col xs={24} md={12}>
                    <Form.Item
                      name="title_zh"
                      label="页面标题"
                      extra="用于后台列表和前台页面标题展示。"
                      rules={[{ required: true, message: '请输入页面标题。' }]}
                    >
                      <Input placeholder="请输入页面标题" />
                    </Form.Item>
                  </Col>
                  <Col xs={24} md={12}>
                    <Form.Item name="page_type" label="页面类型" extra="用于后台组织和筛选。">
                      <Select options={pageTypeOptions.slice(1)} />
                    </Form.Item>
                  </Col>
                </Row>


                <Form.Item
                  name="content_zh"
                  label="页面正文"
                  extra="支持图文混排。"
                >
                  <LazyRichTextEditor active placeholder="请输入页面正文内容。" minHeight={360} />
                </Form.Item>

                {currentId ? (
                  <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Alert
                      type={currentWorkflow?.has_unpublished_changes ? 'warning' : 'success'}
                      showIcon
                      message={
                        currentWorkflow?.has_unpublished_changes
                          ? '当前草稿存在未发布改动。'
                          : '当前草稿已与线上版本一致。'
                      }
                      description={
                        currentWorkflow?.live_updated_at
                          ? `线上更新时间：${currentWorkflow.live_updated_at}`
                          : '该单页尚未发布到线上。'
                      }
                    />

                    <Descriptions size="small" column={2} bordered>
                      <Descriptions.Item label="草稿更新时间">
                        {currentWorkflow?.draft_updated_at || currentDetail?.updated_at || '-'}
                      </Descriptions.Item>
                      <Descriptions.Item label="线上更新时间">
                        {currentWorkflow?.live_updated_at || '-'}
                      </Descriptions.Item>
                      <Descriptions.Item label="最后发布人">
                        {currentWorkflow?.last_published_by || '-'}
                      </Descriptions.Item>
                      <Descriptions.Item label="最后恢复人">
                        {currentWorkflow?.last_restored_by || '-'}
                      </Descriptions.Item>
                    </Descriptions>

                    <Card size="small" title="最近流程记录" bodyStyle={{ paddingTop: 8, paddingBottom: 8 }}>
                      <List
                        dataSource={Array.isArray(currentWorkflow?.publish_log) ? currentWorkflow.publish_log : []}
                        locale={{ emptyText: '暂无流程记录。' }}
                        renderItem={(item) => (
                          <List.Item>
                            <Space direction="vertical" size={2} style={{ width: '100%' }}>
                              <Space size={8} wrap>
                                <Tag>{item.action || 'event'}</Tag>
                                <Text strong>{item.operator || 'system'}</Text>
                                <Text type="secondary">{item.created_at || '-'}</Text>
                              </Space>
                              <Text type="secondary">{item.message || '-'}</Text>
                            </Space>
                          </List.Item>
                        )}
                      />
                    </Card>
                  </Space>
                ) : null}
              </Form>
            </Spin>
          </Card>
        </Col>
      </Row>
    </Space>
    </PagePlaceholder>
  );
}
