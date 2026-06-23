import { useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  Col,
  Descriptions,
  Drawer,
  Form,
  Input,
  message,
  Pagination,
  Row,
  Select,
  Space,
  Table,
  Tag,
  Typography,
} from 'antd';
import { useRef } from 'react';
import {
  addInquiryFollowUp,
  batchUpdateInquiryStatus,
  batchUpdateWorkbenchArchiveStatus,
  convertAiConversation,
  getInquiryDetail,
  getInquiries,
  getInquiryLookups,
  getInquiryWorkbench,
  getInquiryWorkbenchDetail,
  updateInquiry,
  updateInquiryArchiveStatus,
  updateInquiryStatus,
  updateWorkbenchArchiveStatus,
} from '@/api/inquiries';
import PagePlaceholder from '@/components/PagePlaceholder';
import TableSelectionFooter from '@/components/TableSelectionFooter';
import {
  buildCountryOptions,
  buildLanguageOptions,
  filterLocaleOption,
  formatGeoSummary,
} from '@/utils/localeMeta';

const { Paragraph, Text } = Typography;
const { TextArea } = Input;

const statusOptions = [
  { label: '全部状态', value: '' },
  { label: '新建', value: 'new' },
  { label: '已联系', value: 'contacted' },
  { label: '报价中', value: 'quoting' },
  { label: '已成交', value: 'won' },
  { label: '已关闭', value: 'closed' },
];

const archiveOptions = [
  { label: '全部记录', value: '' },
  { label: '正常', value: 'active' },
  { label: '已归档', value: 'archived' },
];

const viewOptions = [
  { label: '询盘池', value: 'inquiry' },
  { label: 'AI 对话', value: 'conversation' },
];

function renderStatus(status) {
  const colorMap = {
    new: 'default',
    contacted: 'blue',
    quoting: 'gold',
    won: 'success',
    closed: 'default',
  };

  const labelMap = {
    new: '新建',
    contacted: '已联系',
    quoting: '报价中',
    won: '已成交',
    closed: '已关闭',
  };

  return <Tag color={colorMap[status] || 'default'}>{labelMap[status] || status || '-'}</Tag>;
}

function renderArchiveStatus(status) {
  const isArchived = status === 'archived';
  return <Tag color={isArchived ? 'warning' : 'success'}>{isArchived ? '已归档' : '正常'}</Tag>;
}

function buildStatCards(stats) {
  const statusCounts = stats?.status_counts || {};

  return [
    { label: '总询盘数', value: Number(stats?.total || 0) },
    { label: '新建', value: Number(statusCounts.new || 0) },
    { label: '已联系', value: Number(statusCounts.contacted || 0) },
    { label: '报价中', value: Number(statusCounts.quoting || 0) },
    { label: '已成交', value: Number(statusCounts.won || 0) },
    { label: '已关闭', value: Number(statusCounts.closed || 0) },
  ];
}

function renderWorkbenchStatus(record) {
  if (record.record_type === 'conversation') {
    if (Number(record.inquiry_id || 0) > 0) {
      return <Tag color="success">已转为询盘</Tag>;
    }
    return <Tag color="processing">待转询盘</Tag>;
  }

  return renderStatus(record.status);
}

function renderTranslatedMessage(message) {
  const original = String(message?.content || '').trim();
  const translated = String(message?.translated_text || '').trim();
  const hasTranslation =
    translated !== '' &&
    translated !== original &&
    String(message?.message_language || '').trim().toLowerCase() !== 'zh';

  return (
    <Space direction="vertical" size={6} style={{ width: '100%' }}>
      <Paragraph style={{ margin: 0, whiteSpace: 'pre-wrap' }}>{original || '-'}</Paragraph>
      {hasTranslation ? (
        <Paragraph style={{ margin: 0, whiteSpace: 'pre-wrap' }} type="secondary">
          {translated}
        </Paragraph>
      ) : null}
    </Space>
  );
}

function renderMessageSources(message) {
  const sources = Array.isArray(message?.sources) ? message.sources : [];
  if (!sources.length) {
    return null;
  }

  return (
    <Space wrap size={[8, 8]} style={{ marginTop: 8 }}>
      {sources.map((item, index) => {
        const title = String(item?.title || '').trim() || 'Untitled source';
        const sourceType = String(item?.source_type || '').trim();
        return (
          <Tag key={`${title}-${sourceType || index}`} color="blue">
            {sourceType ? `${sourceType}: ${title}` : title}
          </Tag>
        );
      })}
    </Space>
  );
}

export default function InquiriesPage() {
  const [filters, setFilters] = useState({
    record_type: 'inquiry',
    keyword: '',
    status: '',
    archive_status: '',
    country_code: '',
    language_code: '',
    page: 1,
    page_size: 10,
  });
  const [listLoading, setListLoading] = useState(false);
  const [items, setItems] = useState([]);
  const [stats, setStats] = useState({});
  const [pagination, setPagination] = useState({
    page: 1,
    page_size: 10,
    total: 0,
  });
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [drawerLoading, setDrawerLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [followUpSaving, setFollowUpSaving] = useState(false);
  const [currentId, setCurrentId] = useState(null);
  const [currentRecordType, setCurrentRecordType] = useState('inquiry');
  const [detail, setDetail] = useState(null);
  const [selectedRowKeys, setSelectedRowKeys] = useState([]);
  const [batchStatus, setBatchStatus] = useState('contacted');
  const [batchSubmitting, setBatchSubmitting] = useState(false);
  const [languageItems, setLanguageItems] = useState([]);
  const [teamMembers, setTeamMembers] = useState([]);
  const hasLoadedLookupsRef = useRef(false);
  const [summaryForm] = Form.useForm();
  const [followUpForm] = Form.useForm();

  const statCards = useMemo(() => buildStatCards(stats), [stats]);
  const isConversationView = filters.record_type === 'conversation';
  const selectedCount = selectedRowKeys.length;
  const countryOptions = useMemo(() => buildCountryOptions(languageItems), [languageItems]);
  const languageOptions = useMemo(() => buildLanguageOptions(languageItems), [languageItems]);
  const assigneeOptions = useMemo(
    () =>
      (Array.isArray(teamMembers) ? teamMembers : []).map((item) => ({
        label: `${item.name_zh || item.name || '未命名成员'}${item.title_zh ? ` / ${item.title_zh}` : ''}`,
        value: String(item.id),
      })),
    [teamMembers],
  );
  const assigneeMap = useMemo(
    () => new Map(assigneeOptions.map((item) => [item.value, item.label])),
    [assigneeOptions],
  );

  async function loadLookups() {
    try {
      const result = await getInquiryLookups();
      setLanguageItems(Array.isArray(result?.languages?.items) ? result.languages.items : []);
      setTeamMembers(Array.isArray(result?.team_members) ? result.team_members : []);
    } catch {
      // Keep page usable even if auxiliary dictionaries fail.
    }
  }

  async function loadList(nextFilters = filters) {
    setListLoading(true);

    try {
      const data =
        nextFilters.record_type === 'inquiry'
          ? await getInquiries(nextFilters)
          : await getInquiryWorkbench(nextFilters);
      setItems(Array.isArray(data.items) ? data.items : []);
      setPagination(data.pagination || { page: 1, page_size: 10, total: 0 });
      setStats(data.stats || {});
      setSelectedRowKeys([]);
    } catch (error) {
      message.error(error.message || '加载询盘列表失败。');
    } finally {
      setListLoading(false);
    }
  }

  async function loadDetail(id) {
    setDrawerLoading(true);

    try {
      const data =
        currentRecordType === 'inquiry'
          ? await getInquiryDetail(id)
          : await getInquiryWorkbenchDetail(currentRecordType, id);
      setDetail(data);
      const summary =
        currentRecordType === 'inquiry'
          ? data?.summary || {}
          : {
              ...(data?.summary || {}),
              ...(data?.inquiry_summary || {}),
            };
      summaryForm.setFieldsValue({
        country_code: summary.country_code || '',
        language_code: summary.language_code || summary.language || '',
        product_interest: summary.product_interest || '',
        solution_interest: summary.solution_interest || '',
        assigned_to: summary.assigned_to ? String(summary.assigned_to) : undefined,
        status:
          currentRecordType === 'inquiry'
            ? summary.status || 'new'
            : summary.status || 'contacted',
      });
    } catch (error) {
      message.error(error.message || '加载询盘详情失败。');
      setDrawerOpen(false);
      setCurrentId(null);
      setDetail(null);
    } finally {
      setDrawerLoading(false);
    }
  }

  useEffect(() => {
    async function bootstrapOrRefresh() {
      if (hasLoadedLookupsRef.current) {
        await loadList(filters);
        return;
      }

      hasLoadedLookupsRef.current = true;
      await Promise.all([loadList(filters), loadLookups()]);
    }

    bootstrapOrRefresh();
  }, [filters]);

  async function refreshListAndDetail(targetId = currentId, options = {}) {
    const shouldReloadDetail =
      !!targetId && (options.forceDetail === true || (drawerOpen && currentRecordType === 'inquiry'));

    await Promise.all([
      loadList(filters),
      shouldReloadDetail ? loadDetail(targetId) : Promise.resolve(),
    ]);
  }

  function openDrawer(record, recordType = filters.record_type) {
    const targetId =
      recordType === 'conversation' ? Number(record.session_id || record.id || 0) : Number(record.id || 0);
    setCurrentId(targetId);
    setCurrentRecordType(recordType);
    setDrawerOpen(true);
    followUpForm.resetFields();
    loadDetail(targetId);
  }

  async function handleSaveSummary(values) {
    if (!currentId || currentRecordType !== 'inquiry') {
      return;
    }

    setSaving(true);

    try {
      await updateInquiry(currentId, {
        country_code: String(values.country_code || '').trim().toUpperCase(),
        language_code: String(values.language_code || '').trim().toLowerCase(),
        product_interest: String(values.product_interest || '').trim(),
        solution_interest: String(values.solution_interest || '').trim(),
        assigned_to: String(values.assigned_to || '').trim(),
        status: values.status,
      });
      message.success('询盘信息已更新。');
      await refreshListAndDetail(currentId, { forceDetail: true });
    } catch (error) {
      message.error(error.message || '更新询盘失败。');
    } finally {
      setSaving(false);
    }
  }

  async function handleQuickStatus(record, status) {
    if (filters.record_type !== 'inquiry') {
      return;
    }

    try {
      await updateInquiryStatus(record.id, status);
      message.success('询盘状态已更新。');
      await Promise.all([
        loadList(filters),
        currentId === record.id && drawerOpen ? loadDetail(record.id) : Promise.resolve(),
      ]);
    } catch (error) {
      message.error(error.message || '更新询盘状态失败。');
    }
  }

  async function handleFollowUp(values) {
    if (!currentId || currentRecordType !== 'inquiry') {
      return;
    }

    setFollowUpSaving(true);

    try {
      await addInquiryFollowUp(currentId, values.content.trim());
      message.success('跟进记录已添加。');
      followUpForm.resetFields();
      await refreshListAndDetail(currentId, { forceDetail: true });
    } catch (error) {
      message.error(error.message || '添加跟进记录失败。');
    } finally {
      setFollowUpSaving(false);
    }
  }

  async function handleArchiveToggle() {
    if (!currentId || !detail) {
      return;
    }

    const archiveSource = detail?.summary || {};
    const nextStatus = archiveSource.archive_status === 'archived' ? 'active' : 'archived';

    try {
      if (currentRecordType === 'inquiry') {
        await updateInquiryArchiveStatus(currentId, nextStatus);
      } else {
        await updateWorkbenchArchiveStatus(currentRecordType, currentId, nextStatus);
      }
      message.success('归档状态已更新。');
      await Promise.all([loadList(filters), loadDetail(currentId)]);
    } catch (error) {
      message.error(error.message || '更新归档状态失败。');
    }
  }

  async function handleConvertConversation() {
    if (!currentId || currentRecordType !== 'conversation') {
      return;
    }

    const values = await summaryForm.validateFields();

    try {
      await convertAiConversation(currentId, {
        country_code: String(values.country_code || '').trim().toUpperCase(),
        language_code: String(values.language_code || '').trim().toLowerCase(),
        product_interest: String(values.product_interest || '').trim(),
        solution_interest: String(values.solution_interest || '').trim(),
        assigned_to: String(values.assigned_to || '').trim(),
        status: values.status || 'contacted',
      });
      message.success('AI 对话已转为询盘。');
      setDrawerOpen(false);
      setCurrentId(null);
      setDetail(null);
      setFilters((current) => ({
        ...current,
        record_type: 'inquiry',
        page: 1,
      }));
    } catch (error) {
      message.error(error.message || 'AI 对话转询盘失败。');
    }
  }

  async function handleBatchArchive(archiveStatus) {
    if (selectedCount === 0) {
      message.warning('请先选择记录。');
      return;
    }

    setBatchSubmitting(true);

    try {
      await batchUpdateWorkbenchArchiveStatus(filters.record_type, selectedRowKeys, archiveStatus);
      message.success(archiveStatus === 'archived' ? '批量归档已完成。' : '批量恢复已完成。');
      await loadList(filters);
    } catch (error) {
      message.error(error.message || '批量操作失败。');
    } finally {
      setBatchSubmitting(false);
    }
  }

  async function handleBatchStatus() {
    if (selectedCount === 0) {
      message.warning('请先选择记录。');
      return;
    }

    setBatchSubmitting(true);

    try {
      await batchUpdateInquiryStatus(selectedRowKeys, batchStatus);
      message.success('批量状态已更新。');
      await loadList(filters);
    } catch (error) {
      message.error(error.message || '批量状态更新失败。');
    } finally {
      setBatchSubmitting(false);
    }
  }

  function getRecordKey(record) {
    if (filters.record_type === 'conversation') {
      return Number(record.session_id || record.id || 0);
    }

    return Number(record.id || 0);
  }

  function getGeoMeta(record) {
    return formatGeoSummary(record.country_code, record.language_code || record.language, languageItems);
  }

  function renderLocaleLabel(meta, fallback = '-') {
    if (!meta?.code) {
      return <span>{fallback}</span>;
    }

    return (
      <span className={`locale-flag-line${meta.flagUrl ? ' has-flag-image' : ''}`}>
        {meta.flagUrl ? (
          <img
            src={meta.flagUrl}
            alt={meta.code}
            className="locale-flag-icon"
            loading="lazy"
            onLoad={(event) => {
              event.currentTarget.closest('.locale-flag-line')?.classList.remove('has-flag-fallback');
            }}
            onError={(event) => {
              event.currentTarget.style.display = 'none';
              event.currentTarget.closest('.locale-flag-line')?.classList.add('has-flag-fallback');
            }}
          />
        ) : null}
        {meta.flag ? (
          <span className="locale-flag-emoji">{meta.flag}</span>
        ) : null}
        <span>{meta.name || meta.code}</span>
      </span>
    );
  }

  function renderGeoLine(meta, fallback = '-') {
    if (!meta?.code) {
      return <span>{fallback}</span>;
    }

    return renderLocaleLabel(meta, fallback);
  }

  function renderLanguageLine(meta, fallback = '-') {
    if (!meta?.code) {
      return <span>{fallback}</span>;
    }

    return <span>{meta.nativeName || meta.englishName || meta.zhName || meta.name || meta.code}</span>;
  }

  function renderGeoSummary(record) {
    const meta = getGeoMeta(record);

    return (
      <Space direction="vertical" size={2}>
        {renderGeoLine(meta.country, record.country_code || '-')}
        <Text type="secondary">{renderLanguageLine(meta.language, record.language_code || record.language || '-')}</Text>
      </Space>
    );
  }

  function renderBrowseLink(path, label = '') {
    const target = buildFrontendUrl(path);
    if (!target) {
      return <span>{label || '-'}</span>;
    }

    return (
      <a href={target} target="_blank" rel="noreferrer">
        {label || path || '-'}
      </a>
    );
  }

  function buildFrontendUrl(path) {
    const normalized = String(path || '').trim();
    if (!normalized) {
      return '';
    }

    if (/^https?:\/\//i.test(normalized)) {
      return normalized;
    }

    try {
      return new URL(normalized, window.location.origin).toString();
    } catch {
      return normalized;
    }
  }

  const columns = [
    {
      title: 'ID',
      dataIndex: filters.record_type === 'conversation' ? 'session_id' : 'id',
      width: 72,
    },
    {
      title: '客户',
      dataIndex: 'customer_name',
      width: 248,
      render: (value, record) => (
        <Space direction="vertical" size={2} className="inquiry-table-stack">
          <span className="inquiry-table-primary">{value || '未命名'}</span>
          <Text type="secondary" className="inquiry-table-secondary">{record.company_name || '未填写公司'}</Text>
        </Space>
      ),
    },
    {
      title: '联系方式',
      dataIndex: 'primary_contact_value',
      width: 198,
      render: (value, record) => (
        <Space direction="vertical" size={2} className="inquiry-table-stack">
          <span className="inquiry-table-primary">{value || '-'}</span>
          <Text type="secondary" className="inquiry-table-secondary">{record.primary_contact_type || '-'}</Text>
        </Space>
      ),
    },
    {
      title: '国家 / 语言',
      key: 'geo',
      width: 220,
      render: (_, record) => renderGeoSummary(record),
    },
    {
      title: '意向内容',
      key: 'intent',
      width: 288,
      render: (_, record) => (
        <Space direction="vertical" size={2} className="inquiry-table-stack">
          <span className="inquiry-table-primary">{record.product_interest || '-'}</span>
          <Text type="secondary" className="inquiry-table-secondary">{record.solution_interest || '-'}</Text>
        </Space>
      ),
    },
    {
      title: '状态',
      key: 'status',
      width: 90,
      render: (_, record) => renderWorkbenchStatus(record),
    },
    {
      title: '归档',
      dataIndex: 'archive_status',
      width: 82,
      render: renderArchiveStatus,
    },
    {
      title: '最近更新时间',
      dataIndex: 'updated_at',
      width: 144,
    },
    {
      title: '操作',
      key: 'actions',
      width: 186,
      render: (_, record) => (
        <Space wrap>
          <Button size="small" onClick={() => openDrawer(record, filters.record_type)}>
            查看
          </Button>
          {filters.record_type === 'inquiry' ? (
            <>
              <Button size="small" onClick={() => handleQuickStatus(record, 'contacted')}>
                标记已联系
              </Button>
              <Button size="small" onClick={() => handleQuickStatus(record, 'closed')}>
                关闭
              </Button>
            </>
          ) : Number(record.inquiry_id || 0) === 0 ? (
            <Button size="small" type="primary" onClick={() => openDrawer(record, 'conversation')}>
              转为询盘
            </Button>
          ) : null}
        </Space>
      ),
    },
  ];

  const summary =
    currentRecordType === 'inquiry'
      ? detail?.summary || {}
      : {
          ...(detail?.summary || {}),
          ...(detail?.inquiry_summary || {}),
        };
  const conversationSummary =
    currentRecordType === 'conversation' ? detail?.summary || {} : detail?.conversation || {};
  const followUps = Array.isArray(detail?.follow_ups) ? detail.follow_ups : [];
  const chatMessages = Array.isArray(detail?.chat_messages) ? detail.chat_messages : [];
  const browseTraces = Array.isArray(detail?.browse_traces) ? detail.browse_traces : [];
  const snapshots = Array.isArray(detail?.snapshots) ? detail.snapshots : [];
  const rowSelection = {
    selectedRowKeys,
    onChange: (keys) => setSelectedRowKeys(keys),
  };
  const batchStatusOptions = statusOptions.filter((option) => option.value);

  return (
    <>
      <PagePlaceholder hideHeader compact>
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
        <Row gutter={[16, 16]}>
          {statCards.map((item) => (
            <Col xs={24} sm={12} xl={4} key={item.label}>
              <Card className="stat-card inquiry-stat-card" bordered={false} loading={listLoading} size="small">
                <div className="stat-label">{item.label}</div>
                <div className="stat-value">{item.value}</div>
              </Card>
            </Col>
          ))}
        </Row>

        <Card className="page-card" bordered={false}>
          <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <div className="toolbar-surface">
              <Space wrap size={12}>
                <Select
                  style={{ width: 180 }}
                  options={viewOptions}
                  value={filters.record_type}
                  onChange={(value) =>
                    setFilters((current) => ({
                      ...current,
                      record_type: value,
                      status: '',
                      page: 1,
                    }))
                  }
                />
                <Input.Search
                  allowClear
                  placeholder="搜索客户、公司、产品或联系方式"
                  style={{ width: 320 }}
                  onSearch={(value) =>
                    setFilters((current) => ({
                      ...current,
                      keyword: value.trim(),
                      page: 1,
                    }))
                  }
                />
                <Select
                  style={{ width: 160 }}
                  options={statusOptions}
                  value={filters.status}
                  disabled={filters.record_type === 'conversation'}
                  onChange={(value) =>
                    setFilters((current) => ({
                      ...current,
                      status: value,
                      page: 1,
                    }))
                  }
                />
                <Select
                  style={{ width: 160 }}
                  options={archiveOptions}
                  value={filters.archive_status}
                  onChange={(value) =>
                    setFilters((current) => ({
                      ...current,
                      archive_status: value,
                      page: 1,
                    }))
                  }
                />
                <Select
                  allowClear
                  showSearch
                  placeholder="国家"
                  style={{ width: 200 }}
                  options={countryOptions}
                  value={filters.country_code || undefined}
                  filterOption={filterLocaleOption}
                  onChange={(value) =>
                    setFilters((current) => ({
                      ...current,
                      country_code: String(value || '').trim().toUpperCase(),
                      page: 1,
                    }))
                  }
                />
                <Select
                  allowClear
                  showSearch
                  placeholder="语言"
                  style={{ width: 220 }}
                  options={languageOptions}
                  value={filters.language_code || undefined}
                  filterOption={filterLocaleOption}
                  onChange={(value) =>
                    setFilters((current) => ({
                      ...current,
                      language_code: String(value || '').trim().toLowerCase(),
                      page: 1,
                    }))
                  }
                />
              </Space>
            </div>

            <Table
              className="inquiry-table"
              rowKey={getRecordKey}
              loading={listLoading}
              columns={columns}
              dataSource={items}
              rowSelection={rowSelection}
              pagination={false}
              tableLayout="fixed"
              scroll={{ x: 1420 }}
            />
            <TableSelectionFooter
              rowKeys={items.map((item) => getRecordKey(item))}
              selectedRowKeys={selectedRowKeys}
              onChange={setSelectedRowKeys}
              actions={
                <div className="batch-toolbar-actions inquiry-batch-toolbar">
                  <Button
                    disabled={selectedCount === 0}
                    loading={batchSubmitting}
                    onClick={() => handleBatchArchive('archived')}
                  >
                    批量归档
                  </Button>
                  <Button
                    disabled={selectedCount === 0}
                    loading={batchSubmitting}
                    onClick={() => handleBatchArchive('active')}
                  >
                    批量恢复
                  </Button>
                  {!isConversationView ? (
                    <>
                      <Select
                        style={{ width: 160 }}
                        options={batchStatusOptions}
                        value={batchStatus}
                        onChange={setBatchStatus}
                      />
                      <Button
                        type="primary"
                        disabled={selectedCount === 0}
                        loading={batchSubmitting}
                        onClick={handleBatchStatus}
                      >
                        批量更新状态
                      </Button>
                    </>
                  ) : (
                    <Text type="secondary">AI 会话仅支持批量归档和恢复</Text>
                  )}
                </div>
              }
              pagination={
                <div className="batch-toolbar-pagination">
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
              }
            />
          </Space>
        </Card>
        </Space>
      </PagePlaceholder>

      <Drawer
        title={summary.customer_name || `询盘 #${currentId || ''}`}
        width={820}
        open={drawerOpen}
        onClose={() => {
          setDrawerOpen(false);
          setCurrentId(null);
          setDetail(null);
        }}
        destroyOnHidden
        extra={
          <Space>
            <Button onClick={handleArchiveToggle}>
              {(currentRecordType === 'conversation'
                ? conversationSummary.archive_status
                : summary.archive_status) === 'archived'
                ? '恢复'
                : '归档'}
            </Button>
            {currentRecordType === 'conversation' && Number(conversationSummary.inquiry_id || 0) === 0 ? (
              <Button type="primary" onClick={handleConvertConversation}>
                转为询盘
              </Button>
            ) : (
              <Button type="primary" loading={saving} onClick={() => summaryForm.submit()}>
                保存
              </Button>
            )}
          </Space>
        }
      >
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <Card className="settings-card" bordered={false} loading={drawerLoading}>
            <Descriptions column={2} size="small" className="inquiry-descriptions">
              <Descriptions.Item label="客户">
                {summary.customer_name || conversationSummary.contact_name || '-'}
              </Descriptions.Item>
              <Descriptions.Item label="公司">
                {summary.company_name || conversationSummary.company_name || '-'}
              </Descriptions.Item>
              <Descriptions.Item label="联系方式">
                {summary.primary_contact_value || '-'}{' '}
                {summary.primary_contact_type ? `(${summary.primary_contact_type})` : ''}
              </Descriptions.Item>
              <Descriptions.Item label="状态">
                {currentRecordType === 'conversation'
                  ? renderWorkbenchStatus({
                      record_type: 'conversation',
                      inquiry_id: conversationSummary.inquiry_id,
                    })
                  : renderStatus(summary.status)}
              </Descriptions.Item>
              <Descriptions.Item label="归档">
                {renderArchiveStatus(
                  currentRecordType === 'conversation'
                    ? conversationSummary.archive_status
                    : summary.archive_status,
                )}
              </Descriptions.Item>
              <Descriptions.Item label="来源">
                {summary.source || conversationSummary.source || '-'}
              </Descriptions.Item>
              <Descriptions.Item label="国家">
                {renderGeoLine(
                  formatGeoSummary(
                    summary.country_code || conversationSummary.country_code,
                    '',
                    languageItems,
                  ).country,
                  summary.country_code || conversationSummary.country_code || '-',
                )}
              </Descriptions.Item>
              <Descriptions.Item label="语言">
                {renderLanguageLine(
                  formatGeoSummary(
                    '',
                    summary.language_code || conversationSummary.language,
                    languageItems,
                  ).language,
                  summary.language_code || conversationSummary.language || '-',
                )}
              </Descriptions.Item>
              <Descriptions.Item label="消息数">
                {conversationSummary.message_count || summary.message_count || 0}
              </Descriptions.Item>
              <Descriptions.Item label="快照数">
                {conversationSummary.snapshot_count || summary.snapshot_count || snapshots.length}
              </Descriptions.Item>
              <Descriptions.Item label="更新时间">
                {summary.updated_at || conversationSummary.updated_at || '-'}
              </Descriptions.Item>
              <Descriptions.Item label="首次响应">
                {summary.first_response_at || '-'}
              </Descriptions.Item>
            </Descriptions>
            <Paragraph style={{ marginBottom: 0 }}>
              <Text strong>需求摘要：</Text> {summary.requirement_summary || '-'}
            </Paragraph>
          </Card>

          <Card
            className="settings-card"
            title={currentRecordType === 'conversation' ? '转询盘与纠正' : '询盘信息编辑'}
            bordered={false}
            loading={drawerLoading}
          >
            <Form form={summaryForm} layout="vertical" onFinish={handleSaveSummary}>
              <div className="form-four-column">
                <Form.Item name="country_code" label="国家代码">
                  <Select
                    allowClear
                    showSearch
                    placeholder="请选择国家"
                    options={countryOptions}
                    filterOption={filterLocaleOption}
                    optionLabelProp="label"
                  />
                </Form.Item>
                <Form.Item name="language_code" label="语言">
                  <Select
                    allowClear
                    showSearch
                    placeholder="请选择语言"
                    options={languageOptions}
                    filterOption={filterLocaleOption}
                    optionLabelProp="label"
                  />
                </Form.Item>
                <Form.Item name="status" label="状态">
                  <Select options={statusOptions.slice(1)} />
                </Form.Item>
                <Form.Item name="assigned_to" label="负责人">
                  <Select
                    allowClear
                    showSearch
                    placeholder="请选择负责人"
                    options={assigneeOptions}
                    optionFilterProp="label"
                  />
                </Form.Item>
              </div>

              <Form.Item name="product_interest" label="产品意向">
                <TextArea rows={2} />
              </Form.Item>

              <Form.Item name="solution_interest" label="方案意向">
                <TextArea rows={2} />
              </Form.Item>
            </Form>
          </Card>

          <Row gutter={[16, 16]}>
            <Col xs={24} xl={12}>
              <Card
                className="settings-card"
                title={currentRecordType === 'conversation' ? `快照记录（${snapshots.length}）` : `跟进记录（${followUps.length}）`}
                bordered={false}
                loading={drawerLoading}
              >
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                  {currentRecordType === 'conversation' ? (
                    <div className="inquiry-feed">
                      {snapshots.length > 0 ? (
                        snapshots.map((item, index) => (
                          <div className="inquiry-feed-item" key={`${item.created_at || index}-${index}`}>
                            <Text strong>快照版本 {item.snapshot_version || index + 1}</Text>
                            <Paragraph style={{ margin: '4px 0 0' }}>
                              {(item.contact_name || '-')}{item.company_name ? ` | ${item.company_name}` : ''}
                            </Paragraph>
                            <Paragraph style={{ margin: 0 }}>
                              {item.requirement_summary || item.product_interest || '-'}
                            </Paragraph>
                          </div>
                        ))
                      ) : (
                        <Text type="secondary">暂无快照记录。</Text>
                      )}
                    </div>
                  ) : (
                    <>
                      <div className="inquiry-feed">
                        {followUps.length > 0 ? (
                          followUps.map((item, index) => (
                            <div className="inquiry-feed-item" key={`${item.created_at || index}-${index}`}>
                              <Text strong>{item.created_at || '-'}</Text>
                              <Paragraph style={{ margin: 0 }}>{item.content || '-'}</Paragraph>
                            </div>
                          ))
                        ) : (
                          <Text type="secondary">暂无跟进记录。</Text>
                        )}
                      </div>

                      <Form form={followUpForm} layout="vertical" onFinish={handleFollowUp}>
                        <Form.Item
                          name="content"
                          label="新增跟进"
                          rules={[{ required: true, message: '请输入跟进内容。' }]}
                        >
                          <TextArea rows={3} />
                        </Form.Item>
                        <Button type="primary" htmlType="submit" loading={followUpSaving}>
                          添加跟进
                        </Button>
                      </Form>
                    </>
                  )}
                </Space>
              </Card>
            </Col>

            <Col xs={24} xl={12}>
              <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <Card className="settings-card" title={`对话消息（${chatMessages.length}）`} bordered={false} loading={drawerLoading}>
                  <div className="inquiry-feed inquiry-feed-compact">
                    {chatMessages.length > 0 ? (
                        chatMessages.slice(0, 12).map((item, index) => (
                          <div className="inquiry-feed-item" key={`${item.created_at || index}-${index}`}>
                            <Text strong>{item.role || '消息'}</Text>
                            <div style={{ marginTop: 4 }}>
                              {renderTranslatedMessage(item)}
                              {String(item?.role || '').trim() === 'assistant' ? renderMessageSources(item) : null}
                            </div>
                          </div>
                        ))
                    ) : (
                      <Text type="secondary">暂无对话消息。</Text>
                    )}
                  </div>
                </Card>

                <Card className="settings-card" title={`浏览轨迹（${browseTraces.length}）`} bordered={false} loading={drawerLoading}>
                  <div className="inquiry-feed inquiry-feed-compact">
                    {browseTraces.length > 0 ? (
                        browseTraces.slice(0, 10).map((item, index) => (
                          <div className="inquiry-feed-item" key={`${item.visited_at || index}-${index}`}>
                            <Text strong>{item.title || item.page || '-'}</Text>
                            <Paragraph style={{ margin: '4px 0 0' }}>
                              {renderBrowseLink(item.page || '', item.page || '-')}
                              {item.visited_at ? ` | ${item.visited_at}` : ''}
                            </Paragraph>
                            {item.referrer ? (
                              <Paragraph style={{ margin: '4px 0 0' }}>
                                来源：
                                <span style={{ marginLeft: 6 }}>{renderBrowseLink(item.referrer, item.referrer)}</span>
                              </Paragraph>
                            ) : null}
                          </div>
                        ))
                    ) : (
                      <Text type="secondary">暂无浏览轨迹。</Text>
                    )}
                  </div>
                </Card>
              </Space>
            </Col>
          </Row>
        </Space>
      </Drawer>
    </>
  );
}

