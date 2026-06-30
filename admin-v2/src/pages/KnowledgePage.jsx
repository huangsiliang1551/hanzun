import { useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  Descriptions,
  Drawer,
  Empty,
  Form,
  Input,
  Modal,
  Pagination,
  Popconfirm,
  Select,
  Space,
  Table,
  Tag,
  Typography,
  message,
} from 'antd';
import MediaPickerModal from '@/components/MediaPickerModal';
import PagePlaceholder from '@/components/PagePlaceholder';
import {
  createKnowledgeDocument,
  deleteKnowledgeDocument,
  getKnowledgeDocumentDetail,
  getKnowledgeDocuments,
  reindexAllKnowledge,
  reindexKnowledgeDocument,
  syncCmsKnowledge,
  updateKnowledgeDocument,
} from '@/api/knowledge';

const { Text, Paragraph } = Typography;
const { TextArea } = Input;

const STATUS_OPTIONS = [
  { label: '全部状态', value: '' },
  { label: '待处理', value: 'pending' },
  { label: '已索引', value: 'indexed' },
  { label: '失败', value: 'failed' },
  { label: '停用', value: 'disabled' },
];

const SOURCE_OPTIONS = [
  { label: '全部来源', value: '' },
  { label: '手动录入', value: 'manual' },
  { label: '上传文件', value: 'upload' },
  { label: '产品内容', value: 'product' },
  { label: '解决方案', value: 'solution' },
  { label: '新闻与案例', value: 'article' },
];

const CREATE_SOURCE_OPTIONS = [
  { label: '手动录入', value: 'manual' },
  { label: '上传文件', value: 'upload' },
];

const LANGUAGE_OPTIONS = [
  { label: '全部语言', value: '' },
  { label: '中文', value: 'zh' },
  { label: '英文', value: 'en' },
];

const STATUS_LABEL_MAP = {
  pending: '待处理',
  indexed: '已索引',
  failed: '失败',
  disabled: '停用',
};

const SOURCE_LABEL_MAP = {
  manual: '手动录入',
  upload: '上传文件',
  product: '产品内容',
  solution: '解决方案',
  article: '新闻与案例',
};

const tableLocale = {
  emptyText: <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="还没有知识库文档" />,
};

function renderStatus(status) {
  const colorMap = {
    pending: 'processing',
    indexed: 'success',
    failed: 'error',
    disabled: 'default',
  };

  return <Tag color={colorMap[status] || 'default'}>{STATUS_LABEL_MAP[status] || status || '-'}</Tag>;
}

function normalizeTags(value) {
  if (Array.isArray(value)) {
    return value;
  }

  if (value && typeof value === 'object') {
    return Object.entries(value).map(([key, current]) => `${key}:${current}`);
  }

  return [];
}

function toTagArray(value) {
  return String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean);
}

function joinChunks(chunks) {
  return (Array.isArray(chunks) ? chunks : [])
    .map((item) => String(item.content || '').trim())
    .filter(Boolean)
    .join('\n\n');
}

function buildSummaryMessage(prefix, result) {
  const created = Number(result?.created || 0);
  const updated = Number(result?.updated || 0);
  const skipped = Number(result?.skipped || 0);
  const failed = Number(result?.failed || 0);
  return `${prefix}：新增 ${created}，更新 ${updated}，跳过 ${skipped}，失败 ${failed}`;
}

export default function KnowledgePage() {
  const [filters, setFilters] = useState({
    keyword: '',
    status: '',
    source_type: '',
    language_code: '',
    page: 1,
    page_size: 10,
  });
  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, page_size: 10, total: 0 });
  const [loading, setLoading] = useState(false);
  const [createOpen, setCreateOpen] = useState(false);
  const [pickerOpen, setPickerOpen] = useState(false);
  const [detailOpen, setDetailOpen] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);
  const [createSaving, setCreateSaving] = useState(false);
  const [detailSaving, setDetailSaving] = useState(false);
  const [syncLoading, setSyncLoading] = useState(false);
  const [reindexAllLoading, setReindexAllLoading] = useState(false);
  const [currentDetail, setCurrentDetail] = useState(null);
  const [selectedUploadAsset, setSelectedUploadAsset] = useState(null);
  const [createForm] = Form.useForm();
  const [detailForm] = Form.useForm();
  const createSource = Form.useWatch('source', createForm);

  const summary = useMemo(
    () => ({
      total: Number(pagination.total || 0),
      indexed: items.filter((item) => item.status === 'indexed').length,
      manual: items.filter((item) => item.source_type === 'manual').length,
      upload: items.filter((item) => item.source_type === 'upload').length,
    }),
    [items, pagination.total],
  );

  async function loadKnowledge(nextFilters = filters) {
    setLoading(true);
    try {
      const payload = await getKnowledgeDocuments(nextFilters);
      setItems(Array.isArray(payload?.items) ? payload.items : []);
      setPagination(
        payload?.pagination || {
          page: nextFilters.page,
          page_size: nextFilters.page_size,
          total: 0,
        },
      );
    } catch (error) {
      message.error(error.message || '加载知识库文档失败');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadKnowledge(filters);
  }, [filters]);

  function openCreateModal() {
    setSelectedUploadAsset(null);
    createForm.resetFields();
    createForm.setFieldsValue({
      source: 'manual',
      title: '',
      content: '',
      file_path: '',
      language_code: 'zh',
      tags: '',
    });
    setCreateOpen(true);
  }

  async function openDetail(record) {
    setDetailOpen(true);
    setDetailLoading(true);

    try {
      const payload = await getKnowledgeDocumentDetail(record.id);
      setCurrentDetail(payload);
      detailForm.setFieldsValue({
        title: payload?.document?.title || '',
        language_code: payload?.document?.language_code || 'zh',
        status: payload?.document?.status || 'pending',
        tags: normalizeTags(payload?.document?.tags).join(', '),
        reindex_content: payload?.document?.source_type === 'manual' ? joinChunks(payload?.chunks) : '',
      });
    } catch (error) {
      message.error(error.message || '加载知识库详情失败');
      setDetailOpen(false);
      setCurrentDetail(null);
    } finally {
      setDetailLoading(false);
    }
  }

  async function handleCreate(values) {
    setCreateSaving(true);
    try {
      const payload = {
        source: values.source,
        title: String(values.title || '').trim(),
        language_code: values.language_code || 'zh',
        tags: toTagArray(values.tags),
      };

      if (values.source === 'upload') {
        payload.file_path = String(values.file_path || '').trim();
      } else {
        payload.content = String(values.content || '').trim();
      }

      await createKnowledgeDocument(payload);
      message.success('知识库文档已创建');
      setCreateOpen(false);
      await loadKnowledge(filters);
    } catch (error) {
      message.error(error.message || '创建知识库文档失败');
    } finally {
      setCreateSaving(false);
    }
  }

  async function handleSaveDetail(values) {
    const documentId = Number(currentDetail?.document?.id || 0);
    if (!documentId) {
      return;
    }

    setDetailSaving(true);
    try {
      await updateKnowledgeDocument(documentId, {
        title: String(values.title || '').trim(),
        language_code: values.language_code || 'zh',
        status: values.status || 'pending',
        tags: toTagArray(values.tags),
      });

      const content = String(values.reindex_content || '').trim();
      if (content) {
        await reindexKnowledgeDocument(documentId, { content });
      }

      message.success('知识库文档已更新');
      await openDetail({ id: documentId });
      await loadKnowledge(filters);
    } catch (error) {
      message.error(error.message || '更新知识库文档失败');
    } finally {
      setDetailSaving(false);
    }
  }

  async function handleDelete(record) {
    try {
      await deleteKnowledgeDocument(record.id);
      message.success('知识库文档已删除');
      await loadKnowledge(filters);
    } catch (error) {
      message.error(error.message || '删除知识库文档失败');
    }
  }

  async function handleReindex(record) {
    try {
      await reindexKnowledgeDocument(record.id);
      message.success('已提交重建索引');
      await loadKnowledge(filters);
    } catch (error) {
      message.error(error.message || '重建索引失败');
    }
  }

  async function handleSyncCms() {
    setSyncLoading(true);
    try {
      const result = await syncCmsKnowledge();
      message.success(buildSummaryMessage('CMS 同步完成', result));
      await loadKnowledge(filters);
    } catch (error) {
      message.error(error.message || '同步 CMS 知识库失败');
    } finally {
      setSyncLoading(false);
    }
  }

  async function handleReindexAll() {
    setReindexAllLoading(true);
    try {
      const result = await reindexAllKnowledge();
      message.success(`全量重建完成：成功 ${Number(result?.success || 0)}，失败 ${Number(result?.failed || 0)}，跳过 ${Number(result?.skipped || 0)}`);
      await loadKnowledge(filters);
      return;
      message.success(`全量重建完成：成功 ${Number(result?.success || 0)}，失败 ${Number(result?.failed || 0)}`);
      await loadKnowledge(filters);
    } catch (error) {
      message.error(error.message || '全量重建索引失败');
    } finally {
      setReindexAllLoading(false);
    }
  }

  const columns = [
    {
      title: '文档标题',
      dataIndex: 'title',
      width: 240,
      render: (_, record) => (
        <Space direction="vertical" size={0}>
          <Text strong>{record.title || '-'}</Text>
          <Text type="secondary">{SOURCE_LABEL_MAP[record.source_type] || record.source_type || '-'}</Text>
        </Space>
      ),
    },
    {
      title: '语言',
      dataIndex: 'language_code',
      width: 90,
      render: (value) => String(value || '-').toUpperCase(),
    },
    {
      title: '状态',
      dataIndex: 'status',
      width: 110,
      render: renderStatus,
    },
    {
      title: '分块数',
      dataIndex: 'chunk_count',
      width: 90,
    },
    {
      title: '更新时间',
      dataIndex: 'updated_at',
      width: 170,
    },
    {
      title: '错误信息',
      dataIndex: 'error_message',
      render: (value) => (
        <Text type={value ? 'danger' : 'secondary'} ellipsis={{ tooltip: value || '' }}>
          {value || '-'}
        </Text>
      ),
    },
    {
      title: '操作',
      key: 'actions',
      width: 240,
      render: (_, record) => (
        <Space size={4} wrap>
          <Button size="small" onClick={() => openDetail(record)}>
            详情
          </Button>
          <Button size="small" onClick={() => handleReindex(record)}>
            重建索引
          </Button>
          <Popconfirm
            title="确认删除该知识库文档吗？"
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
      <PagePlaceholder hideHeader compact>
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <Card className="page-card" bordered={false} size="small">
            <Space direction="vertical" size={12} style={{ width: '100%' }}>
              <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                <Space wrap size={12}>
                  <Input.Search
                    allowClear
                    placeholder="搜索标题或标签"
                    style={{ width: 260 }}
                    onSearch={(value) =>
                      setFilters((current) => ({
                        ...current,
                        keyword: value.trim(),
                        page: 1,
                      }))
                    }
                  />
                  <Select
                    style={{ width: 140 }}
                    options={STATUS_OPTIONS}
                    value={filters.status}
                    onChange={(value) =>
                      setFilters((current) => ({
                        ...current,
                        status: value,
                        page: 1,
                      }))
                    }
                  />
                  <Select
                    style={{ width: 140 }}
                    options={SOURCE_OPTIONS}
                    value={filters.source_type}
                    onChange={(value) =>
                      setFilters((current) => ({
                        ...current,
                        source_type: value,
                        page: 1,
                      }))
                    }
                  />
                  <Select
                    style={{ width: 120 }}
                    options={LANGUAGE_OPTIONS}
                    value={filters.language_code}
                    onChange={(value) =>
                      setFilters((current) => ({
                        ...current,
                        language_code: value,
                        page: 1,
                      }))
                    }
                  />
                </Space>

                <Space wrap>
                  {false && (
                    <>
                  <Tag color="blue">总数 {summary.total}</Tag>
                  <Tag color="green">已索引 {summary.indexed}</Tag>
                  <Tag color="purple">手动 {summary.manual}</Tag>
                  <Tag color="gold">上传 {summary.upload}</Tag>
                  <Button onClick={() => loadKnowledge(filters)}>刷新</Button>
                    </>
                  )}
                  <Button loading={syncLoading} onClick={handleSyncCms}>
                    同步 CMS
                  </Button>
                  <Button loading={reindexAllLoading} onClick={handleReindexAll}>
                    全量重建索引
                  </Button>
                  <Button type="primary" onClick={openCreateModal}>
                    新增文档
                  </Button>
                </Space>
              </Space>

              <div className="table-scroll-shell">
                <Table
                  rowKey="id"
                  loading={loading}
                  columns={columns}
                  dataSource={items}
                  size="small"
                  scroll={{ x: 1100 }}
                  locale={tableLocale}
                  pagination={false}
                />
              </div>

              <div className="table-selection-footer">
                <div className="table-selection-footer-main">
                  <Text type="secondary">知识库会被 AI 对话、检索增强和内容辅助能力直接使用。</Text>
                </div>
                <div className="table-selection-footer-pagination">
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
                </div>
              </div>
            </Space>
          </Card>
        </Space>
      </PagePlaceholder>

      <Modal
        title="新增知识库文档"
        open={createOpen}
        onCancel={() => {
          setCreateOpen(false);
          setSelectedUploadAsset(null);
        }}
        onOk={() => createForm.submit()}
        okText="保存"
        cancelText="取消"
        okButtonProps={{ loading: createSaving }}
        width={760}
        destroyOnHidden
      >
        <Form form={createForm} layout="vertical" onFinish={handleCreate}>
          <div className="form-two-column">
            <Form.Item name="source" label="来源方式">
              <Select options={CREATE_SOURCE_OPTIONS} />
            </Form.Item>
            <Form.Item name="language_code" label="语言">
              <Select options={LANGUAGE_OPTIONS.filter((item) => item.value)} />
            </Form.Item>
          </div>

          <Form.Item name="title" label="文档标题" rules={[{ required: true, message: '请输入文档标题' }]}>
            <Input placeholder="请输入知识库文档标题" />
          </Form.Item>

          {createSource === 'upload' ? (
            <>
              <Form.Item
                name="file_path"
                label="文件路径"
                rules={[{ required: true, message: '请选择上传文件' }]}
              >
                <Input placeholder="/uploads/..." readOnly />
              </Form.Item>
              <Space direction="vertical" size={6} style={{ width: '100%', marginBottom: 12 }}>
                <Button onClick={() => setPickerOpen(true)}>从资源中心选择文件</Button>
                {selectedUploadAsset ? (
                  <Text type="secondary">
                    已选择：{selectedUploadAsset.file_name || selectedUploadAsset.file_path}
                  </Text>
                ) : (
                  <Text type="secondary">支持从资源中心选择 PDF、文档或文本类素材作为知识库来源。</Text>
                )}
              </Space>
            </>
          ) : (
            <Form.Item name="content" label="正文内容" rules={[{ required: true, message: '请输入正文内容' }]}>
              <TextArea rows={10} placeholder="请输入知识库正文内容，系统会自动切分并建立索引。" />
            </Form.Item>
          )}

          <Form.Item name="tags" label="标签">
            <Input placeholder="多个标签用英文逗号分隔，例如：报价, 烘焙设备, 出口" />
          </Form.Item>
        </Form>
      </Modal>

      <Drawer
        title={currentDetail?.document?.title || '知识库详情'}
        width={860}
        open={detailOpen}
        onClose={() => {
          setDetailOpen(false);
          setCurrentDetail(null);
        }}
        destroyOnHidden
        extra={
          <Space>
            <Button onClick={() => setDetailOpen(false)}>关闭</Button>
            <Button type="primary" loading={detailSaving} onClick={() => detailForm.submit()}>
              保存
            </Button>
          </Space>
        }
      >
        {currentDetail ? (
          <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card bordered={false} size="small" style={{ background: '#f8fbff' }}>
              <Descriptions bordered size="small" column={2}>
                <Descriptions.Item label="ID">{currentDetail.document?.id}</Descriptions.Item>
                <Descriptions.Item label="状态">{renderStatus(currentDetail.document?.status)}</Descriptions.Item>
                <Descriptions.Item label="来源">
                  {SOURCE_LABEL_MAP[currentDetail.document?.source_type] || currentDetail.document?.source_type || '-'}
                </Descriptions.Item>
                <Descriptions.Item label="来源 ID">{currentDetail.document?.source_id || '-'}</Descriptions.Item>
                <Descriptions.Item label="语言">
                  {String(currentDetail.document?.language_code || '-').toUpperCase()}
                </Descriptions.Item>
                <Descriptions.Item label="分块数">{currentDetail.document?.chunk_count || 0}</Descriptions.Item>
                <Descriptions.Item label="文件路径" span={2}>
                  {currentDetail.document?.file_path || '-'}
                </Descriptions.Item>
                <Descriptions.Item label="错误信息" span={2}>
                  {currentDetail.document?.error_message || '-'}
                </Descriptions.Item>
              </Descriptions>
            </Card>

            <Form form={detailForm} layout="vertical" onFinish={handleSaveDetail} disabled={detailLoading || detailSaving}>
              <div className="form-two-column">
                <Form.Item name="title" label="标题" rules={[{ required: true, message: '请输入标题' }]}>
                  <Input />
                </Form.Item>
                <Form.Item name="language_code" label="语言">
                  <Select options={LANGUAGE_OPTIONS.filter((item) => item.value)} />
                </Form.Item>
              </div>

              <div className="form-two-column">
                <Form.Item name="status" label="状态">
                  <Select options={STATUS_OPTIONS.filter((item) => item.value)} />
                </Form.Item>
                <Form.Item name="tags" label="标签">
                  <Input placeholder="多个标签用英文逗号分隔" />
                </Form.Item>
              </div>

              <Form.Item
                name="reindex_content"
                label={currentDetail.document?.source_type === 'manual' ? '重建索引内容' : '手动重建内容'}
              >
                <TextArea
                  rows={10}
                  placeholder={
                    currentDetail.document?.source_type === 'manual'
                      ? '手动文档可直接编辑内容后重新建立索引。'
                      : '如需覆盖原始分块内容，可在这里输入新内容并保存。'
                  }
                />
              </Form.Item>
            </Form>

            <Card className="page-card" bordered={false} title={`分块预览（${currentDetail.chunks?.length || 0}）`}>
              <Space direction="vertical" size={12} style={{ width: '100%' }}>
                {Array.isArray(currentDetail.chunks) && currentDetail.chunks.length > 0 ? (
                  currentDetail.chunks.map((chunk) => (
                    <div className="inquiry-feed-item" key={chunk.id || `${chunk.document_id}-${chunk.chunk_index}`}>
                      <Space direction="vertical" size={4} style={{ width: '100%' }}>
                        <Text strong>{`分块 ${chunk.chunk_index} / 约 ${chunk.token_estimate || 0} tokens`}</Text>
                        <Paragraph style={{ margin: 0 }}>{chunk.content || '-'}</Paragraph>
                        <Space size={[6, 6]} wrap>
                          {Array.isArray(chunk.keywords) && chunk.keywords.length > 0 ? (
                            chunk.keywords.map((keyword) => <Tag key={`${chunk.id}-${keyword}`}>{keyword}</Tag>)
                          ) : (
                            <Text type="secondary">暂无关键词</Text>
                          )}
                        </Space>
                      </Space>
                    </div>
                  ))
                ) : (
                  <Text type="secondary">当前没有可预览的分块内容。</Text>
                )}
              </Space>
            </Card>
          </Space>
        ) : null}
      </Drawer>

      <MediaPickerModal
        open={pickerOpen}
        title="选择知识库文件"
        assetType="all"
        onCancel={() => setPickerOpen(false)}
        onSelect={(asset) => {
          createForm.setFieldValue('file_path', asset.file_path || '');
          setSelectedUploadAsset(asset);
          setPickerOpen(false);
        }}
      />
    </>
  );
}
