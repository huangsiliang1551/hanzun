import { useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  Col,
  Input,
  Row,
  Select,
  Space,
  Statistic,
  Table,
  Tag,
  Typography,
  message,
} from 'antd';
import { getSitePhrases, updateSitePhrases } from '@/api/settings';
import { getLanguageMeta } from '@/utils/localeMeta';

const { Search, TextArea } = Input;
const { Text } = Typography;

function statusColor(status) {
  switch (status) {
    case 'published':
      return 'success';
    case 'reviewed':
      return 'processing';
    case 'pending':
      return 'warning';
    default:
      return 'default';
  }
}

function statusText(status) {
  switch (status) {
    case 'published':
      return '已发布';
    case 'reviewed':
      return '已校对';
    case 'pending':
      return '待处理';
    default:
      return status || '-';
  }
}

function normalizeItems(payload) {
  return Array.isArray(payload?.items) ? payload.items : [];
}

function normalizeLanguages(payload) {
  return Array.isArray(payload?.languages) ? payload.languages : [];
}

export default function SitePhrasesPanel() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [languages, setLanguages] = useState([]);
  const [activeLanguageCode, setActiveLanguageCode] = useState('');
  const [filters, setFilters] = useState({
    keyword: '',
    module: '',
    status: '',
  });
  const [summary, setSummary] = useState({
    total: 0,
    completed: 0,
    pending: 0,
    completion_percent: 0,
  });
  const [items, setItems] = useState([]);
  const [moduleOptions, setModuleOptions] = useState([]);
  const [missingLogs, setMissingLogs] = useState([]);

  async function loadPhrases(nextFilters = filters, nextLanguageCode = activeLanguageCode) {
    setLoading(true);
    try {
      const payload = await getSitePhrases({
        ...nextFilters,
        language_code: nextLanguageCode || undefined,
      });
      const nextLanguages = normalizeLanguages(payload);
      const resolvedLanguageCode = String(payload?.active_language_code || nextLanguageCode || '').trim();
      setLanguages(nextLanguages);
      setActiveLanguageCode(resolvedLanguageCode);
      setItems(normalizeItems(payload));
      setSummary(payload?.summary || {});
      setModuleOptions(Array.isArray(payload?.module_options) ? payload.module_options : []);
      setMissingLogs(Array.isArray(payload?.missing_logs) ? payload.missing_logs : []);
    } catch (error) {
      message.error(error.message || '加载站点文案失败');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadPhrases();
  }, []);

  const activeLanguageMeta = useMemo(
    () => getLanguageMeta(activeLanguageCode, languages),
    [activeLanguageCode, languages],
  );

  const languageOptions = useMemo(
    () =>
      languages.map((item) => {
        const meta = getLanguageMeta(item.code, languages);
        return {
          label: `${meta.nativeName} / ${meta.englishName} (${meta.code.toUpperCase()})`,
          value: meta.code,
        };
      }),
    [languages],
  );

  const columns = useMemo(
    () => [
      {
        title: '词条',
        dataIndex: 'label',
        width: 240,
        fixed: 'left',
        render: (_, record) => (
          <Space direction="vertical" size={2}>
            <Text strong>{record.label || record.phrase_key}</Text>
            <Text type="secondary" style={{ fontSize: 12 }}>
              {record.phrase_key}
            </Text>
          </Space>
        ),
      },
      {
        title: '模块',
        dataIndex: 'module',
        width: 120,
        render: (value) => value || '-',
      },
      {
        title: '中文原文',
        dataIndex: 'default_text_zh',
        width: 260,
        render: (value) => (
          <Text ellipsis={{ tooltip: value || '-' }} style={{ maxWidth: 240 }}>
            {value || '-'}
          </Text>
        ),
      },
      {
        title: `${activeLanguageMeta.nativeName || activeLanguageCode.toUpperCase()} 文案`,
        dataIndex: 'translation',
        width: 360,
        render: (_, record) => (
          <TextArea
            autoSize={{ minRows: 1, maxRows: 4 }}
            value={record.translation || ''}
            placeholder={`输入 ${activeLanguageMeta.nativeName || activeLanguageCode.toUpperCase()} 文案`}
            onChange={(event) => {
              const value = event.target.value;
              setItems((current) =>
                current.map((item) =>
                  item.phrase_key === record.phrase_key
                    ? { ...item, translation: value }
                    : item,
                ),
              );
            }}
          />
        ),
      },
      {
        title: '状态',
        dataIndex: 'status',
        width: 150,
        render: (_, record) => (
          <Select
            value={record.status || 'pending'}
            style={{ width: '100%' }}
            options={[
              { label: '待处理', value: 'pending' },
              { label: '已校对', value: 'reviewed' },
              { label: '已发布', value: 'published' },
            ]}
            onChange={(value) => {
              setItems((current) =>
                current.map((item) =>
                  item.phrase_key === record.phrase_key ? { ...item, status: value } : item,
                ),
              );
            }}
          />
        ),
      },
      {
        title: '来源',
        dataIndex: 'source_type',
        width: 120,
        render: (value) => (
          <Tag color={value === 'manual' ? 'blue' : 'default'}>
            {value === 'manual' ? '人工维护' : '回退占位'}
          </Tag>
        ),
      },
      {
        title: '当前状态',
        dataIndex: 'status',
        width: 120,
        render: (value) => <Tag color={statusColor(value)}>{statusText(value)}</Tag>,
      },
      {
        title: '最近更新',
        dataIndex: 'updated_at',
        width: 180,
        render: (value) => value || '-',
      },
    ],
    [activeLanguageCode, activeLanguageMeta.nativeName],
  );

  async function handleSave() {
    if (!activeLanguageCode) {
      message.error('请先选择目标语言。');
      return;
    }

    setSaving(true);
    try {
      const payload = await updateSitePhrases({
        language_code: activeLanguageCode,
        items: items.map((item) => ({
          phrase_key: item.phrase_key,
          translation: item.translation || '',
          status: item.status || 'reviewed',
          default_text_zh: item.default_text_zh || '',
        })),
      });
      setItems(normalizeItems(payload));
      setSummary(payload?.summary || {});
      setMissingLogs(Array.isArray(payload?.missing_logs) ? payload.missing_logs : []);
      setLanguages(normalizeLanguages(payload));
      setActiveLanguageCode(String(payload?.active_language_code || activeLanguageCode));
      message.success('站点文案翻译已保存');
    } catch (error) {
      message.error(error.message || '保存站点文案失败');
    } finally {
      setSaving(false);
    }
  }

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Row gutter={[12, 12]}>
        <Col xs={12} xl={6}>
          <Card className="stat-card" bordered={false} size="small" loading={loading}>
            <Statistic title="目标语言" value={activeLanguageMeta.nativeName || '-'} />
          </Card>
        </Col>
        <Col xs={12} xl={6}>
          <Card className="stat-card" bordered={false} size="small" loading={loading}>
            <Statistic title="词条总数" value={Number(summary.total || 0)} />
          </Card>
        </Col>
        <Col xs={12} xl={6}>
          <Card className="stat-card" bordered={false} size="small" loading={loading}>
            <Statistic title="已完成" value={Number(summary.completed || 0)} />
          </Card>
        </Col>
        <Col xs={12} xl={6}>
          <Card className="stat-card" bordered={false} size="small" loading={loading}>
            <Statistic title="待处理" value={Number(summary.pending || 0)} />
          </Card>
        </Col>
      </Row>

      <Card bordered={false}>
        <Space direction="vertical" size={12} style={{ width: '100%' }}>
          <Space wrap style={{ justifyContent: 'space-between', width: '100%' }}>
            <Space wrap>
              <Select
                style={{ width: 280 }}
                value={activeLanguageCode || undefined}
                options={languageOptions}
                placeholder="选择要维护的语言"
                onChange={(value) => {
                  const nextFilters = { ...filters };
                  setActiveLanguageCode(value);
                  loadPhrases(nextFilters, value);
                }}
              />
              <Select
                style={{ width: 180 }}
                value={filters.module}
                placeholder="筛选模块"
                options={[
                  { label: '全部模块', value: '' },
                  ...moduleOptions.map((item) => ({ label: item, value: item })),
                ]}
                onChange={(value) => {
                  const nextFilters = { ...filters, module: value };
                  setFilters(nextFilters);
                  loadPhrases(nextFilters, activeLanguageCode);
                }}
              />
              <Select
                style={{ width: 180 }}
                value={filters.status}
                placeholder="筛选状态"
                options={[
                  { label: '全部状态', value: '' },
                  { label: '待处理', value: 'pending' },
                  { label: '已校对', value: 'reviewed' },
                  { label: '已发布', value: 'published' },
                ]}
                onChange={(value) => {
                  const nextFilters = { ...filters, status: value };
                  setFilters(nextFilters);
                  loadPhrases(nextFilters, activeLanguageCode);
                }}
              />
              <Search
                allowClear
                placeholder="搜索词条 key、模块、中文或译文"
                style={{ width: 320 }}
                value={filters.keyword}
                onChange={(event) => setFilters((current) => ({ ...current, keyword: event.target.value }))}
                onSearch={(value) => {
                  const nextFilters = { ...filters, keyword: value };
                  setFilters(nextFilters);
                  loadPhrases(nextFilters, activeLanguageCode);
                }}
              />
            </Space>
            <Space wrap>
              <Button onClick={() => loadPhrases(filters, activeLanguageCode)}>重新加载</Button>
              <Button type="primary" loading={saving} onClick={handleSave}>
                保存当前语言
              </Button>
            </Space>
          </Space>

          <Table
            rowKey="phrase_key"
            loading={loading}
            columns={columns}
            dataSource={items}
            pagination={{ pageSize: 12 }}
            scroll={{ x: 1600 }}
          />
        </Space>
      </Card>

      <Card
        bordered={false}
        title={`回退占位记录（${activeLanguageMeta.nativeName || activeLanguageCode.toUpperCase() || '-'}）`}
        extra={<Tag>{missingLogs.length} 条</Tag>}
      >
        <Table
          rowKey={(record) => `${record.phrase_key}-${record.language_code}`}
          loading={loading}
          pagination={{ pageSize: 6 }}
          dataSource={missingLogs}
          columns={[
            {
              title: '词条',
              dataIndex: 'phrase_key',
              width: 220,
            },
            {
              title: '模块',
              dataIndex: 'module',
              width: 120,
              render: (value) => value || '-',
            },
            {
              title: '回退文案',
              dataIndex: 'fallback_text',
              render: (value) => (
                <Text ellipsis={{ tooltip: value || '-' }} style={{ maxWidth: 320 }}>
                  {value || '-'}
                </Text>
              ),
            },
            {
              title: '命中次数',
              dataIndex: 'hit_count',
              width: 100,
            },
            {
              title: '最近命中',
              dataIndex: 'last_seen_at',
              width: 180,
              render: (value) => value || '-',
            },
          ]}
        />
      </Card>
    </Space>
  );
}
