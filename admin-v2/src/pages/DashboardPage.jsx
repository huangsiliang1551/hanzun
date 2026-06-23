import { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Col, Progress, Row, Space, Spin, Table, Tag, Typography } from 'antd';
import PagePlaceholder from '@/components/PagePlaceholder';
import { getDashboardOverview } from '@/api/dashboard';
import { useSiteBuild } from '@/providers/SiteBuildProvider';
import { getCountryMeta } from '@/utils/localeMeta';

const { Text } = Typography;

function formatNumber(value) {
  return new Intl.NumberFormat('zh-CN').format(Number(value || 0));
}

function formatPercent(value, fractionDigits = 1) {
  return `${Number(value || 0).toFixed(fractionDigits)}%`;
}

function formatMinutes(value) {
  if (value === null || value === undefined || value === '') {
    return '-';
  }

  return `${Number(value).toFixed(1)} 分钟`;
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
      {meta.flag ? <span className="locale-flag-emoji">{meta.flag}</span> : null}
      <span>{meta.localName || meta.name || meta.code}</span>
    </span>
  );
}

function renderCountryCell(value) {
  const meta = getCountryMeta(value);
  if (!meta.code) {
    return '-';
  }

  return (
    <Space direction="vertical" size={2} className="dashboard-country-cell">
      <span className="dashboard-country-name">{renderLocaleLabel(meta)}</span>
      <Text type="secondary">{meta.code}</Text>
    </Space>
  );
}

function buildSummaryCards(overview) {
  const traffic = overview?.traffic || {};
  const ai = overview?.ai || {};
  const inquiries = overview?.inquiries || {};
  const jobs = overview?.jobs || {};

  return [
    { label: 'UV', value: traffic.uv || 0 },
    { label: 'PV', value: traffic.pv || 0 },
    { label: 'AI 会话', value: ai.total_sessions || 0 },
    { label: '新增询盘', value: inquiries.new_count || 0 },
    { label: '待翻译', value: jobs.pending_translation || 0 },
    { label: '待 SEO', value: jobs.pending_seo || 0 },
  ];
}

function buildTodoItems(overview) {
  const jobs = overview?.jobs || {};
  const inquiries = overview?.inquiries || {};
  const items = [];

  if (Number(jobs.pending_translation || 0) > 0) {
    items.push({ color: 'gold', title: `待处理翻译 ${formatNumber(jobs.pending_translation)} 条` });
  }
  if (Number(jobs.pending_seo || 0) > 0) {
    items.push({ color: 'purple', title: `待处理 SEO ${formatNumber(jobs.pending_seo)} 条` });
  }
  if (Number(jobs.failed_ai_jobs || 0) > 0) {
    items.push({ color: 'red', title: `AI 失败任务 ${formatNumber(jobs.failed_ai_jobs)} 条` });
  }

  const inquiryFollowCount = Number(inquiries.new_count || 0) + Number(inquiries.quoting_count || 0);
  if (inquiryFollowCount > 0) {
    items.push({ color: 'blue', title: `待跟进询盘 ${formatNumber(inquiryFollowCount)} 条` });
  }

  if (Number(jobs.seo_404_count || 0) > 0) {
    items.push({ color: 'orange', title: `待处理 404 ${formatNumber(jobs.seo_404_count)} 条` });
  }

  if (items.length === 0) {
    items.push({ color: 'success', title: '当前没有紧急待办' });
  }

  return items;
}

export default function DashboardPage() {
  const { message } = App.useApp();
  const { currentJob } = useSiteBuild();
  const [loading, setLoading] = useState(true);
  const [overview, setOverview] = useState(null);

  const summaryCards = useMemo(() => buildSummaryCards(overview), [overview]);
  const todoItems = useMemo(() => buildTodoItems(overview), [overview]);
  const traffic = overview?.traffic || {};
  const ai = overview?.ai || {};
  const inquiries = overview?.inquiries || {};
  const topPages = Array.isArray(traffic.top_pages) ? traffic.top_pages.slice(0, 5) : [];
  const trafficCountries = Array.isArray(traffic.countries) ? traffic.countries.slice(0, 5) : [];
  const inquiryCountries = Array.isArray(inquiries.countries) ? inquiries.countries.slice(0, 5) : [];

  const validSessionRate =
    Number(ai.total_sessions || 0) > 0
      ? (Number(ai.valid_sessions || 0) / Number(ai.total_sessions || 1)) * 100
      : 0;

  const healthItems = [
    { label: '跳出率', value: formatPercent(Number(traffic.bounce_rate || 0) * 100) },
    { label: '留资转化率', value: formatPercent(Number(ai.lead_capture_rate || 0) * 100) },
    { label: '有效 AI 会话率', value: formatPercent(validSessionRate) },
    { label: '首轮响应', value: formatMinutes(inquiries.avg_first_response_minutes) },
  ];

  async function loadDashboard() {
    setLoading(true);
    try {
      const data = await getDashboardOverview({ range: '7d' });
      setOverview(data);
    } catch (error) {
      message.error(error.message || '加载数据看板失败。');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadDashboard();
  }, []);

  if (loading) {
    return (
      <PagePlaceholder hideHeader compact>
        <div style={{ display: 'grid', placeItems: 'center', minHeight: 320 }}>
          <Spin size="large" />
        </div>
      </PagePlaceholder>
    );
  }

  return (
    <PagePlaceholder hideHeader compact>
      <Space direction="vertical" size={16} style={{ width: '100%' }}>
        <Card className="page-card" variant="borderless">
          <div className="dashboard-summary-header">
            <Space direction="vertical" size={4} className="dashboard-summary-title">
              <Text strong style={{ fontSize: 18 }}>
                数据看板
              </Text>
              <Text type="secondary">集中查看流量、询盘、AI 任务和静态生成状态。</Text>
            </Space>
            <Space wrap className="dashboard-summary-actions">
              <Button onClick={loadDashboard}>刷新数据</Button>
            </Space>
          </div>
        </Card>

        <div className="dashboard-metrics-grid">
          {summaryCards.map((item) => (
            <Card key={item.label} className="page-card dashboard-stat-card" variant="borderless">
              <Space direction="vertical" size={4}>
                <Text type="secondary">{item.label}</Text>
                <Text strong style={{ fontSize: 24 }}>
                  {formatNumber(item.value)}
                </Text>
              </Space>
            </Card>
          ))}
        </div>

        <Row gutter={[16, 16]}>
          <Col xs={24} xl={9}>
            <Card className="page-card dashboard-equal-card" variant="borderless" title="当前待办">
              <Space direction="vertical" size={10} style={{ width: '100%' }}>
                {todoItems.map((item) => (
                  <Tag key={item.title} color={item.color} style={{ width: 'fit-content' }}>
                    {item.title}
                  </Tag>
                ))}

                {currentJob ? (
                  <Space direction="vertical" size={6} style={{ width: '100%', marginTop: 8 }}>
                    <Text strong>静态页面生成中</Text>
                    <Progress percent={Number(currentJob.progress_percent || 0)} size="small" />
                    <Text type="secondary">{currentJob.current_step || '任务执行中'}</Text>
                  </Space>
                ) : null}
              </Space>
            </Card>
          </Col>

          <Col xs={24} xl={7}>
            <Card className="page-card dashboard-equal-card" variant="borderless" title="核心健康度">
              <Space direction="vertical" size={12} style={{ width: '100%' }}>
                {healthItems.map((item) => (
                  <div key={item.label} className="dashboard-inline-pair">
                    <Text strong>{item.label}</Text>
                    <Text>{item.value}</Text>
                  </div>
                ))}
              </Space>
            </Card>
          </Col>

          <Col xs={24} xl={8}>
            <Card className="page-card dashboard-equal-card" variant="borderless" title="热门页面">
              <Table
                rowKey={(item) => item.landing_page || item.slug || item.title}
                size="small"
                pagination={false}
                locale={{ emptyText: '暂无数据' }}
                dataSource={topPages}
                columns={[
                  { title: '页面', dataIndex: 'landing_page', render: (value) => value || '-' },
                  {
                    title: 'PV',
                    dataIndex: 'pv',
                    width: 90,
                    render: (value) => formatNumber(value),
                  },
                ]}
              />
            </Card>
          </Col>
        </Row>

        <Row gutter={[16, 16]}>
          <Col xs={24} xl={12}>
            <Card className="page-card dashboard-equal-card" variant="borderless" title="流量来源国家">
              <Table
                rowKey={(item) => `${item.country_code || item.country_name || 'unknown'}-traffic`}
                size="small"
                pagination={false}
                locale={{ emptyText: '暂无数据' }}
                dataSource={trafficCountries}
                columns={[
                  {
                    title: '国家',
                    dataIndex: 'country_code',
                    render: (value) => renderCountryCell(value),
                  },
                  {
                    title: 'UV',
                    dataIndex: 'uv',
                    width: 90,
                    render: (_, record) => formatNumber(record.uv ?? record.total_count ?? 0),
                  },
                ]}
              />
            </Card>
          </Col>

          <Col xs={24} xl={12}>
            <Card className="page-card dashboard-equal-card" variant="borderless" title="询盘来源国家">
              <Table
                rowKey={(item) => `${item.country_code || item.country_name || 'unknown'}-inquiry`}
                size="small"
                pagination={false}
                locale={{ emptyText: '暂无数据' }}
                dataSource={inquiryCountries}
                columns={[
                  {
                    title: '国家',
                    dataIndex: 'country_code',
                    render: (value) => renderCountryCell(value),
                  },
                  {
                    title: '询盘数',
                    dataIndex: 'total_count',
                    width: 90,
                    render: (_, record) => formatNumber(record.total_count ?? record.sessions ?? 0),
                  },
                ]}
              />
            </Card>
          </Col>
        </Row>
      </Space>
    </PagePlaceholder>
  );
}
