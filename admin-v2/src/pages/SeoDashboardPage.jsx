import { useEffect, useMemo, useState } from 'react';
import { Alert, Card, Col, Empty, Row, Space, Spin, Statistic, Table, Tag, Typography, message } from 'antd';
import PagePlaceholder from '@/components/PagePlaceholder';
import { getSeoDashboardOverview } from '@/api/seoDashboard';

const { Text } = Typography;

function formatDateTime(value) {
  return value ? String(value) : '-';
}

function formatNumber(value) {
  return new Intl.NumberFormat('zh-CN').format(Number(value || 0));
}

function formatHomeChainStatus(value) {
  const map = {
    connected: '已连通',
    disconnected: '未连通',
    pending: '待检查',
  };

  return map[String(value || '').trim()] || value || '-';
}

function renderStatusTag(status) {
  const colorMap = {
    pending: 'processing',
    generated: 'success',
    manual_override: 'warning',
    failed: 'error',
    processing: 'processing',
    resolved: 'success',
    ignored: 'default',
  };

  const labelMap = {
    pending: '待处理',
    generated: '已生成',
    manual_override: '人工覆盖',
    failed: '失败',
    processing: '处理中',
    resolved: '已修复',
    ignored: '已忽略',
  };

  return <Tag color={colorMap[status] || 'default'}>{labelMap[status] || status || '-'}</Tag>;
}

const tableLocale = {
  emptyText: <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="暂无数据" />,
};

export default function SeoDashboardPage() {
  const [loading, setLoading] = useState(true);
  const [payload, setPayload] = useState(null);

  async function loadOverview() {
    setLoading(true);

    try {
      const data = await getSeoDashboardOverview();
      setPayload(data);
    } catch (error) {
      message.error(error.message || '加载 SEO 看板失败。');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadOverview();
  }, []);

  const overview = payload?.overview || {};
  const siteFiles = payload?.siteFiles || {};
  const jobSummary = overview.job_summary || {};
  const routeSummary = overview.route_summary || {};
  const fourOhFourSummary = overview.four_oh_four_summary || {};

  const statCards = useMemo(
    () => [
      { label: 'SEO 任务总数', value: jobSummary.total || 0 },
      { label: '待处理任务', value: jobSummary.pending || 0 },
      { label: '失败任务', value: jobSummary.failed || 0 },
      { label: '路由总数', value: routeSummary.route_count || 0 },
      { label: '已收录路由', value: routeSummary.index_count || 0 },
      { label: '待处理 404', value: fourOhFourSummary.pending || 0 },
    ],
    [jobSummary, routeSummary, fourOhFourSummary],
  );

  const failedJobColumns = [
    { title: '对象', dataIndex: 'entity_label', width: 120 },
    { title: '来源标题', dataIndex: 'source_title', render: (value) => value || '-' },
    {
      title: '语言',
      dataIndex: 'language_code',
      width: 90,
      render: (value) => String(value || '-').toUpperCase(),
    },
    { title: '重试次数', dataIndex: 'retry_count', width: 100, render: (value) => formatNumber(value) },
    {
      title: '错误信息',
      dataIndex: 'error_message',
      render: (value) => <Text type="secondary">{value || '-'}</Text>,
    },
  ];

  const fourOhFourColumns = [
    { title: '访问路径', dataIndex: 'request_path', render: (value) => <Text code>{value || '/'}</Text> },
    { title: '命中次数', dataIndex: 'hit_count', width: 90, render: (value) => formatNumber(value) },
    { title: '状态', dataIndex: 'fix_status', width: 130, render: renderStatusTag },
    { title: '最近出现', dataIndex: 'last_seen_at', width: 180, render: formatDateTime },
    { title: '建议路由', dataIndex: 'suggested_route', render: (value) => value || '-' },
  ];

  const riskLevel =
    Number(jobSummary.failed || 0) > 0 || Number(fourOhFourSummary.pending || 0) > 0
      ? 'warning'
      : 'success';

  return (
    <PagePlaceholder hideHeader compact>
      <Space direction="vertical" size={16} style={{ width: '100%' }} className="seo-dashboard-page">
        <Alert
          type={riskLevel}
          showIcon
          message={riskLevel === 'warning' ? 'SEO 需要关注' : 'SEO 状态正常'}
          description={
            riskLevel === 'warning'
              ? '当前存在失败的 SEO 任务或未处理的 404 路径，可前往任务中心继续重试或清理。'
              : '当前快照中没有失败的 SEO 任务，也没有待处理的 404 队列。'
          }
        />

        <Spin spinning={loading}>
          <Row gutter={[16, 16]}>
            {statCards.map((item) => (
              <Col xs={24} sm={12} xl={4} key={item.label}>
                <Card className="stat-card seo-dashboard-card" bordered={false}>
                  <Statistic title={item.label} value={item.value} formatter={(value) => formatNumber(value)} />
                </Card>
              </Col>
            ))}
          </Row>

          <Row gutter={[16, 16]}>
            <Col xs={24} xl={8}>
              <Card className="page-card seo-dashboard-card" title="站点文件" bordered={false}>
                <Space direction="vertical" size={10} style={{ width: '100%' }}>
                  <Statistic title="站点地图更新时间" value={formatDateTime(siteFiles.sitemap_last_generated_at)} />
                  <Statistic title="robots 更新时间" value={formatDateTime(siteFiles.robots_updated_at)} />
                  <Statistic title="首页链路状态" value={formatHomeChainStatus(siteFiles.home_chain_status)} />
                  <Tag color={siteFiles.home_chain_status === 'connected' ? 'success' : 'warning'}>
                    {siteFiles.home_chain_status === 'connected' ? '链路正常' : '需要检查'}
                  </Tag>
                </Space>
              </Card>
            </Col>

            <Col xs={24} xl={8}>
              <Card className="page-card seo-dashboard-card" title="路由覆盖" bordered={false}>
                <Space direction="vertical" size={10} style={{ width: '100%' }}>
                  <Statistic title="路由总数" value={formatNumber(routeSummary.route_count || 0)} />
                  <Statistic title="已收录" value={formatNumber(routeSummary.index_count || 0)} />
                  <Statistic
                    title="未收录"
                    value={formatNumber((routeSummary.route_count || 0) - (routeSummary.index_count || 0))}
                  />
                </Space>
              </Card>
            </Col>

            <Col xs={24} xl={8}>
              <Card className="page-card seo-dashboard-card" title="404 队列" bordered={false}>
                <Space direction="vertical" size={10} style={{ width: '100%' }}>
                  <Statistic title="404 记录总数" value={formatNumber(fourOhFourSummary.total || 0)} />
                  <Statistic title="待处理" value={formatNumber(fourOhFourSummary.pending || 0)} />
                  <Statistic title="已处理" value={formatNumber(fourOhFourSummary.resolved || 0)} />
                </Space>
              </Card>
            </Col>
          </Row>

          <Row gutter={[16, 16]}>
            <Col xs={24} xl={12}>
              <Card className="page-card seo-dashboard-card" title="最近失败的 SEO 任务" bordered={false}>
                <Table
                  rowKey={(record) => record.id}
                  columns={failedJobColumns}
                  dataSource={Array.isArray(overview.recent_failed_jobs) ? overview.recent_failed_jobs : []}
                  pagination={false}
                  size="small"
                  scroll={{ x: 760 }}
                  locale={tableLocale}
                />
              </Card>
            </Col>

            <Col xs={24} xl={12}>
              <Card className="page-card seo-dashboard-card" title="最近未处理的 404 记录" bordered={false}>
                <Table
                  rowKey={(record) => record.id}
                  columns={fourOhFourColumns}
                  dataSource={Array.isArray(overview.recent_404_logs) ? overview.recent_404_logs : []}
                  pagination={false}
                  size="small"
                  scroll={{ x: 760 }}
                  locale={tableLocale}
                />
              </Card>
            </Col>
          </Row>
        </Spin>
      </Space>
    </PagePlaceholder>
  );
}
