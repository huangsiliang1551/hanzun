import { useEffect, useMemo, useState } from 'react';
import { App, Button, Card, Progress, Space, Spin, Table, Tag, Typography } from 'antd';
import { Line } from '@ant-design/charts';
import PagePlaceholder from '@/components/PagePlaceholder';
import { getDashboardOverview } from '@/api/dashboard';
import { useSiteBuild } from '@/providers/SiteBuildProvider';
import { getCountryMeta, getLanguageMetaWithFlag } from '@/utils/localeMeta';
import { buildPublicHomeUrl } from '@/utils/publicPreview';

const { Text } = Typography;

const BUILD_PHASE_LABEL_MAP = {
  collect_pages: '收集页面',
  read_data: '读取数据',
  render_templates: '渲染模板',
  write_files: '写入文件',
  rebuild_sitemap: '更新站点地图',
  deploy_outputs: '发布文件',
  completed: '已完成',
  failed: '失败',
};

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

function formatBuildPhase(value) {
  const key = String(value || '').trim();
  return BUILD_PHASE_LABEL_MAP[key] || key || '任务执行中';
}

function getNumeric(row, keys = []) {
  for (const key of keys) {
    const candidate = row?.[key];
    if (candidate === undefined || candidate === null || candidate === '') {
      continue;
    }

    const value = Number(candidate);
    if (Number.isFinite(value)) {
      return Math.max(0, Math.round(value));
    }
  }

  return 0;
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

function renderLanguageLabel(meta, fallback = '-') {
  if (!meta?.code) {
    return <span>{fallback}</span>;
  }

  return (
    <span className={`locale-flag-line${meta.flagUrl ? ' has-flag-image' : ''}`}>
      {meta.flagUrl ? (
        <img
          src={meta.flagUrl}
          alt={meta.flagCountryCode || meta.code}
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
      <span>{meta.zhName || meta.name || meta.englishName || meta.code}</span>
    </span>
  );
}

function renderCountryCell(value) {
  const meta = getCountryMeta(value);
  if (!meta.code) {
    return '-';
  }

  const countryName = meta.zhName || meta.name || meta.englishName || '';

  return (
    <span className="dashboard-country-name">
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
        <span>{countryName || '-'}</span>
      </span>
    </span>
  );
}

function normalizeTrafficCountries(rows = []) {
  return (Array.isArray(rows) ? rows : []).map((item) => ({
    language_code: item.language_code || item.country_code || item.country || '',
    country_code: item.country_code || item.country || '',
    uv: Number(item.uv ?? item.total_count ?? item.visits ?? 0),
    pv: Number(item.pv ?? item.visits ?? 0),
  }));
}

function normalizeTopPages(rows = []) {
  return (Array.isArray(rows) ? rows : []).map((item) => ({
    landing_page: item.landing_page || item.page || item.slug || '',
    title: item.title || '',
    uv: Number(item.uv ?? 0),
    pv: Number(item.pv ?? item.visits ?? 0),
  }));
}

function normalizeLandingPagePath(value) {
  const raw = String(value || '').trim();
  if (!raw) {
    return '';
  }

  if (/^https?:\/\//i.test(raw)) {
    try {
      return new URL(raw).pathname || '/';
    } catch {
      return raw.startsWith('/') ? raw : `/${raw}`;
    }
  }

  return raw.startsWith('/') ? raw : `/${raw}`;
}

function parseProductFromPath(path) {
  const normalizedPath = normalizeLandingPagePath(path);
  const matched = normalizedPath.match(/^\/(?:zh\/|en\/)?products\/([^/?#]+)(?:\.html)?(?:\/.*)?$/i);
  if (!matched) {
    return null;
  }

  const slug = String(matched[1] || '').trim().replace(/\.html$/i, '');
  return {
    slug: decodeURIComponent(slug),
    url: normalizedPath.startsWith('/en/') || normalizedPath.startsWith('/zh/')
      ? normalizedPath
      : `/zh${normalizedPath}`,
  };
}

function buildTopPageDisplayName(record) {
  const path = normalizeLandingPagePath(record.landing_page);
  if (!path) {
    return { text: '-', url: '' };
  }

  const productMeta = parseProductFromPath(path);
  if (productMeta) {
    const productName = String(record.title || '').trim() || (productMeta.slug ? `产品：${productMeta.slug}` : '产品');
    return { text: productName, url: productMeta.url, isProduct: true };
  }

  const fallback = String(record.title || '').trim() || path;
  return { text: fallback, url: path, isProduct: false };
}

function renderTopPageCell(record, options = {}) {
  const { compact = false } = options;
  const { text, url, isProduct } = buildTopPageDisplayName(record);
  if (!text || !url) {
    return <span>-</span>;
  }

  const publicOrigin = buildPublicHomeUrl('zh').replace(/\/$/, '');
  const targetUrl = /^https?:\/\//i.test(url) ? url : `${publicOrigin}${url}`;
  const displayPath = normalizeLandingPagePath(url) || url;

  if (isProduct) {
    if (compact) {
      return (
        <a href={targetUrl} target="_blank" rel="noopener noreferrer" className="dashboard-hot-page-link" title={displayPath || text}>
          <Text ellipsis>{text}</Text>
        </a>
      );
    }

    return (
      <Space direction="vertical" size={2}>
        <a href={targetUrl} target="_blank" rel="noopener noreferrer">
          {text}
        </a>
        <Text type="secondary">{displayPath}</Text>
      </Space>
    );
  }

  return compact ? (
    <a href={targetUrl} target="_blank" rel="noopener noreferrer" className="dashboard-hot-page-link" title={text}>
      <Text ellipsis>{text}</Text>
    </a>
  ) : (
    <a href={targetUrl} target="_blank" rel="noopener noreferrer">
      {text}
    </a>
  );
}

function buildDualTrendRows(rows = [], valueDefs = {}, days = 30) {
  const grouped = {};
  for (const row of rows) {
    const date = String(
      row?.stat_date ||
        row?.date ||
        row?.day ||
        row?.statDay ||
        row?.created_at ||
        row?.time ||
        ''
    ).trim();
    if (!date) {
      continue;
    }

    if (!grouped[date]) {
      grouped[date] = {};
    }

    Object.entries(valueDefs).forEach(([key, aliases]) => {
      grouped[date][key] = (grouped[date][key] || 0) + getNumeric(row, aliases);
    });
  }

  const safeRows = Object.entries(grouped).map(([date, values]) => {
    const row = { date };
    Object.keys(valueDefs).forEach((key) => {
      row[key] = Math.max(0, Math.round(Number(values?.[key] || 0)));
    });

    return row;
  });

  safeRows.sort((a, b) => a.date.localeCompare(b.date));

  const emptyRow = Object.keys(valueDefs).reduce((acc, key) => {
    acc[key] = 0;
    return acc;
  }, {});

  const formatLocalDate = (value) => {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, '0');
    const day = String(value.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  const maxDate = new Date();
  const result = [];
  for (let offset = days - 1; offset >= 0; offset -= 1) {
    const current = new Date(maxDate);
    current.setDate(current.getDate() - offset);
    const date = formatLocalDate(current);
    const matched = safeRows.find((item) => item.date === date);
    result.push({
      ...emptyRow,
      ...(matched || {}),
      date,
    });
  }

  return result;
}

function estimateDashboardCellHeight(kind, rows = 1) {
  const safeRows = Math.max(1, Number(rows) || 1);
  if (kind === 'chart') {
    return 250;
  }
  if (kind === 'table') {
    const tableHeaderHeight = 36;
    return 92 + safeRows * 36 + tableHeaderHeight;
  }
  if (kind === 'tag-list') {
    return 98 + safeRows * 32;
  }
  if (kind === 'key-values') {
    return 78 + safeRows * 28;
  }
  return 236;
}

function MiniTrendChart({ seriesRows, seriesDefs = [], emptyText = '暂无数据', chartHeight = 170 }) {
  if (!Array.isArray(seriesRows) || seriesRows.length === 0 || !Array.isArray(seriesDefs) || seriesDefs.length === 0) {
    return <Text type="secondary">{emptyText}</Text>;
  }

  const lineHeight = Math.max(chartHeight, 170);
  const lineColors = seriesDefs.map((series) => series.color || '#888');
  const lineColorMap = seriesDefs.reduce((acc, series) => {
    acc[series.label] = series.color || '#888';
    acc[series.key] = series.color || '#888';
    acc[series.type] = series.color || '#888';
    return acc;
  }, {});
  const getSeriesColor = (trend = '') => lineColorMap[trend] || lineColors[0] || '#888';
  const formatAxisDate = (rawDate) => {
    if (!rawDate) {
      return '';
    }
    const parsed = new Date(rawDate);
    if (Number.isNaN(parsed.getTime())) {
      return String(rawDate).slice(5, 10);
    }
    const month = parsed.getMonth() + 1;
    const day = parsed.getDate();
    return `${month}-${day}`;
  };
  const lineRows = seriesRows.map((item) => ({
    ...item,
    __axis_date: formatAxisDate(item?.date || item?.stat_date || ''),
  }));

  const lineData = lineRows.flatMap((item) =>
    seriesDefs.map((series) => ({
      date: item.__axis_date,
      value: Number(item?.[series.key] || 0),
      trend: series.label,
      type: series.key,
      seriesKey: series.key,
      seriesColor: series.color || '#888',
      raw: item,
    }))
  );
  const maxValue = Math.max(
    ...lineData.map((item) => Number(item.value || 0)),
    0
  );

  if (maxValue <= 0) {
    return (
      <div className="dashboard-line-chart-wrap">
        <div className="dashboard-line-chart-legend">
          {seriesDefs.map((series) => (
            <span key={series.key} className="dashboard-line-chart-legend-item">
              <span className="dashboard-line-chart-legend-dot" style={{ '--dash-color': series.color || '#888' }} />
              <span>{series.label}</span>
            </span>
          ))}
        </div>
        <div style={{ display: 'grid', placeItems: 'center', minHeight: lineHeight }}>
          <Text type="secondary">近30天暂无有效趋势数据</Text>
        </div>
      </div>
    );
  }

  const lineConfig = {
    data: lineData,
    xField: 'date',
    yField: 'value',
    seriesField: 'trend',
    smooth: true,
    padding: [12, 12, 18, 32],
    xAxis: {
      type: 'cat',
      tickCount: 8,
      label: {
        autoRotate: false,
        formatter: (value) => {
          return String(value || '');
        },
      },
      title: {
        text: '日期',
      },
    },
    yAxis: {
      label: {
        formatter: (value) => formatNumber(value),
      },
    },
    color: lineColors,
    line: {
      style: (datum) => ({
        lineWidth: 2.5,
        lineCap: 'round',
        stroke: getSeriesColor(
          datum?.series || datum?.seriesKey || datum?.trend || datum?.type || datum?.seriesColor || ''
        ),
      }),
    },
    point: {
      size: 3,
      shape: 'circle',
      style: (datum) => {
        const pointColor = getSeriesColor(
          datum?.series || datum?.seriesKey || datum?.trend || datum?.type || datum?.seriesColor || ''
        );

        return {
          fill: pointColor,
          stroke: '#ffffff',
          lineWidth: 1,
        };
      },
    },
    state: {
      active: {
        style: (datum) => ({
          stroke: getSeriesColor(
            datum?.series || datum?.seriesKey || datum?.trend || datum?.type || datum?.seriesColor || ''
          ),
        }),
      },
      inactive: {
        style: {
          lineWidth: 2.5,
          opacity: 0.5,
        },
      },
    },
    legend: false,
    tooltip: {
      shared: true,
      showCrosshairs: true,
      showMarkers: true,
      title: 'date',
      customItems: (items) =>
        items.map((item) => ({
          name: item.name,
          value: `${formatNumber(item.value || 0)}`,
          title: String(item?.datum?.date || item?.title || ''),
        })),
    },
  };

  return (
    <div className="dashboard-line-chart-wrap">
      <div className="dashboard-line-chart-legend">
        {seriesDefs.map((series) => (
          <span key={series.key} className="dashboard-line-chart-legend-item">
            <span className="dashboard-line-chart-legend-dot" style={{ '--dash-color': series.color || '#888' }} />
            <span>{series.label}</span>
          </span>
        ))}
      </div>
      <div style={{ width: '100%', height: lineHeight }}>
        <Line {...lineConfig} height={lineHeight} autoFit />
      </div>
    </div>
  );
}

function renderMiniTrendChart(seriesRows, seriesDefs = [], emptyText = '暂无数据', chartHeight = 110, chartKey) {
  return (
    <MiniTrendChart
      key={chartKey}
      seriesRows={seriesRows}
      seriesDefs={seriesDefs}
      emptyText={emptyText}
      chartHeight={chartHeight}
    />
  );
}

function buildTodoItems(overview) {
  const jobs = overview?.jobs || {};
  const inquiries = overview?.inquiries || {};
  const business = [];
  const system = [];
  const pushIfPositive = (list, item) => {
    if (Number(item.count || 0) > 0) {
      list.push(item);
    }
  };

  pushIfPositive(business, {
    color: 'blue',
    title: '新增询盘未处理',
    count: Number(inquiries.new_count || 0),
    href: '#/inquiries?status=new',
    priority: 1,
  });
  pushIfPositive(business, {
    color: 'geekblue',
    title: '待跟进询盘',
    count: Number(inquiries.quoting_count || 0),
    href: '#/inquiries?status=quoting',
    priority: 1,
  });
  pushIfPositive(business, {
    color: 'gold',
    title: '待处理翻译',
    count: Number(jobs.pending_translation || 0),
    href: '#/tasks?tab=translation&translation_status=pending',
    priority: 2,
  });
  pushIfPositive(business, {
    color: 'gold',
    title: '待审核翻译',
    count: Number(jobs.review_translation || 0),
    href: '#/tasks?tab=translation&translation_status=review_required',
    priority: 2,
  });
  pushIfPositive(business, {
    color: 'purple',
    title: '待处理 SEO',
    count: Number(jobs.pending_seo || 0),
    href: '#/tasks?tab=seo&seo_job_status=pending',
    priority: 2,
  });

  pushIfPositive(system, {
    color: 'red',
    title: '失败翻译任务',
    count: Number(jobs.failed_translation || 0),
    href: '#/tasks?tab=translation&translation_status=failed',
    priority: 1,
  });
  pushIfPositive(system, {
    color: 'red',
    title: '失败 SEO 任务',
    count: Number(jobs.failed_seo || 0),
    href: '#/tasks?tab=seo&seo_job_status=failed',
    priority: 1,
  });
  pushIfPositive(system, {
    color: 'volcano',
    title: '失败静态生成任务',
    count: Number(jobs.failed_site_build || 0),
    href: '#/tasks?tab=site-build&site_build_status=failed',
    priority: 1,
  });
  pushIfPositive(system, {
    color: 'orange',
    title: '待修复 404',
    count: Number(jobs.seo_404_count || 0),
    href: '#/tasks?tab=seo&seo404_status=pending',
    priority: 1,
  });

  const sortByPriority = (a, b) => {
    if (a.priority !== b.priority) {
      return a.priority - b.priority;
    }

    return b.count - a.count;
  };

  business.sort(sortByPriority);
  system.sort(sortByPriority);

  return {
    business: business.map((item) => ({
      ...item,
      title: `${item.title} ${formatNumber(item.count)} 条`,
    })),
    system: system.map((item) => ({
      ...item,
      title: `${item.title} ${formatNumber(item.count)} 条`,
    })),
  };
}

function buildLatestTip(seriesRows, seriesDefs = []) {
  if (!Array.isArray(seriesRows) || seriesRows.length === 0 || !Array.isArray(seriesDefs) || seriesDefs.length === 0) {
    return '今日：-';
  }

  const latest = seriesRows[seriesRows.length - 1] || {};
  const text = seriesDefs
    .map((series) => `${series.label} ${formatNumber(latest?.[series.key] || 0)}`)
    .join(' / ');

  return `今日：${text}`;
}

function buildCardTitleWithTodayText(title, tipText) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, width: '100%' }}>
      <span>{title}</span>
      <span style={{ color: 'rgba(0,0,0,0.45)', fontSize: 12, whiteSpace: 'nowrap' }}>{tipText}</span>
    </div>
  );
}

export default function DashboardPage() {
  const { message } = App.useApp();
  const { currentJob, openFullBuild } = useSiteBuild();
  const [loading, setLoading] = useState(true);
  const [overview, setOverview] = useState(null);
  const [viewportTick, setViewportTick] = useState(0);

  const todoItems = useMemo(() => buildTodoItems(overview), [overview]);
  const todoBusinessItems = todoItems.business || [];
  const todoSystemItems = todoItems.system || [];
  const traffic = overview?.traffic || {};
  const ai = overview?.ai || {};
  const inquiries = overview?.inquiries || {};
  const jobs = overview?.jobs || {};
  const topPages = useMemo(() => normalizeTopPages(traffic.top_pages).slice(0, 5), [traffic.top_pages]);
  const trafficCountries = useMemo(() => normalizeTrafficCountries(traffic.countries).slice(0, 5), [traffic.countries]);
  const inquiryCountries = Array.isArray(inquiries.countries) ? inquiries.countries.slice(0, 5) : [];
  const trafficTrendRows = useMemo(
    () =>
      buildDualTrendRows(traffic.series, {
        uv: ['uv', 'unique_visitors', 'unique_visitor_count', 'uv_count'],
        pv: ['pv', 'page_views', 'pageview', 'page_view_count', 'visits', 'visit_count'],
      }, 15),
    [traffic.series]
  );
  const inquiryTrendRows = useMemo(
    () =>
      buildDualTrendRows(inquiries.series, {
        ai_sessions: ['ai_sessions', 'ai_session', 'sessions', 'total_sessions', 'ai_count'],
        new_inquiries: ['new_count', 'new_inquiries', 'created_inquiries', 'inquiry_count', 'inquiries'],
      }, 15),
    [inquiries.series]
  );

  const validSessionRate =
    Number(ai.total_sessions || 0) > 0
      ? (Number(ai.valid_sessions || 0) / Number(ai.total_sessions || 1)) * 100
      : 0;
  const leadCaptureRate =
    Number(ai.total_sessions || 0) > 0
      ? (Number(ai.created_inquiries || 0) / Number(ai.total_sessions || 1)) * 100
      : Number(ai.lead_capture_rate || 0);

  const healthItems = [
    { label: '询盘转化率', value: formatPercent(leadCaptureRate) },
    { label: '有效会话率', value: formatPercent(validSessionRate) },
    { label: '平均首次响应', value: formatMinutes(inquiries.avg_first_response_minutes) },
    {
      label: '失败任务数',
      value: formatNumber(Number(jobs.failed_total || 0) + Number(jobs.seo_404_count || 0)),
    },
  ];

  const dashboardRowHeights = useMemo(() => {
    const row3LeftRows = Math.max(inquiryCountries.length, trafficCountries.length);
    const todoRows = todoBusinessItems.length + todoSystemItems.length;
    const row1Height = Math.max(
      estimateDashboardCellHeight('chart', inquiryTrendRows.length),
      estimateDashboardCellHeight('tag-list', todoRows + (currentJob ? 1 : 0))
    );
    const row2Height = Math.max(
      estimateDashboardCellHeight('chart', trafficTrendRows.length),
      estimateDashboardCellHeight('key-values', healthItems.length)
    );
    const row3Height = Math.max(
      estimateDashboardCellHeight('table', row3LeftRows),
      estimateDashboardCellHeight('table', topPages.length)
    );

    return {
      row1: Math.min(420, Math.max(236, row1Height)),
      row2: Math.min(420, Math.max(236, row2Height)),
      row3: Math.min(420, Math.max(236, row3Height)),
    };
  }, [currentJob, inquiryCountries.length, inquiryTrendRows.length, healthItems.length, todoBusinessItems.length, todoSystemItems.length, topPages.length, trafficCountries.length, trafficTrendRows.length]);

  async function loadDashboard() {
    setLoading(true);
    try {
      const data = await getDashboardOverview({ range: '30d', lite: true });
      setOverview(data);
    } catch (error) {
      message.error(error.message || '加载数据失败');
    } finally {
      setLoading(false);
    }
  }

  function openPublicHome() {
    window.open(buildPublicHomeUrl('zh'), '_blank', 'noopener,noreferrer');
  }

  useEffect(() => {
    loadDashboard();
  }, []);

  useEffect(() => {
    const onResize = () => setViewportTick((prev) => prev + 1);
    onResize();
    window.addEventListener('resize', onResize);
    return () => {
      window.removeEventListener('resize', onResize);
    };
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
        <Text type="secondary">按当前周期查看 UV/PV、询盘代办和系统健康度</Text>
            </Space>
            <Space wrap className="dashboard-summary-actions">
              <Button onClick={loadDashboard}>刷新数据</Button>
              <Button onClick={openPublicHome}>打开首页</Button>
              <Button type="primary" onClick={openFullBuild}>
                全站更新
              </Button>
            </Space>
          </div>
        </Card>

        <div
          className="dashboard-bottom-grid"
          style={
            {
              '--dashboard-bottom-row-1-height': `${dashboardRowHeights.row1}px`,
              '--dashboard-bottom-row-2-height': `${dashboardRowHeights.row2}px`,
              '--dashboard-bottom-row-3-height': `${dashboardRowHeights.row3}px`,
            }
          }
        >
          <Card
            className="page-card dashboard-equal-card dashboard-bottom-cell dashboard-bottom-card-compact"
            variant="borderless"
            title={buildCardTitleWithTodayText(
              '询盘走势图',
              buildLatestTip(inquiryTrendRows, [
                { key: 'ai_sessions', label: 'AI 会话', color: '#597ef7' },
                { key: 'new_inquiries', label: '新增询盘', color: '#69b1ff' },
              ])
            )}
            style={{ gridRow: 1, gridColumn: 1 }}
          >
            {renderMiniTrendChart(
              inquiryTrendRows,
              [
                { key: 'ai_sessions', label: 'AI 会话', color: '#597ef7' },
                { key: 'new_inquiries', label: '新增询盘', color: '#69b1ff' },
              ],
              '暂无数据',
              66,
              `inquiry-trend-${viewportTick}`
            )}
          </Card>
          <Card
            className="page-card dashboard-equal-card dashboard-bottom-cell dashboard-bottom-card-compact"
            variant="borderless"
            title="当前代办"
            style={{ gridRow: 1, gridColumn: 2 }}
          >
            <Space
              direction="vertical"
              size={10}
              style={{ width: '100%' }}
              className="dashboard-todo-list-wrap"
            >
              <div className="dashboard-todo-columns">
                <div className="dashboard-todo-column">
                  <Text strong style={{ fontSize: 13 }}>业务代办</Text>
                  <div className="dashboard-todo-panel">
                    {todoBusinessItems.length > 0 ? (
                      todoBusinessItems.map((item) => (
                        item.href ? (
                          <a key={item.title} href={item.href} target="_self">
                            <Tag color={item.color} style={{ width: 'fit-content' }}>
                              {item.title}
                            </Tag>
                          </a>
                        ) : (
                          <Tag key={item.title} color={item.color} style={{ width: 'fit-content' }}>
                            {item.title}
                          </Tag>
                        )
                      ))
                    ) : (
                      <Text type="secondary">暂无事项</Text>
                    )}
                  </div>
                </div>
                <div className="dashboard-todo-column">
                  <Text strong style={{ fontSize: 13 }}>系统代办</Text>
                  <div className="dashboard-todo-panel">
                    {todoSystemItems.length > 0 ? (
                      todoSystemItems.map((item) => (
                        item.href ? (
                          <a key={item.title} href={item.href} target="_self">
                            <Tag color={item.color} style={{ width: 'fit-content' }}>
                              {item.title}
                            </Tag>
                          </a>
                        ) : (
                          <Tag key={item.title} color={item.color} style={{ width: 'fit-content' }}>
                            {item.title}
                          </Tag>
                        )
                      ))
                    ) : (
                      <Text type="secondary">暂无事项</Text>
                    )}
                  </div>
                </div>
              </div>

              {currentJob ? (
                <Space direction="vertical" size={6} style={{ width: '100%', marginTop: 8 }}>
                  <Text strong>进行中的任务</Text>
                  <Progress percent={Number(currentJob.progress_percent || 0)} size="small" />
                  <Text type="secondary">{formatBuildPhase(currentJob.current_step)}</Text>
                </Space>
              ) : null}
            </Space>
          </Card>
          <Card
            className="page-card dashboard-equal-card dashboard-bottom-cell dashboard-bottom-card-compact"
            variant="borderless"
            title={buildCardTitleWithTodayText(
              '流量走势图',
              buildLatestTip(trafficTrendRows, [
                { key: 'uv', label: 'UV', color: '#52c41a' },
                { key: 'pv', label: 'PV', color: '#36cfc9' },
              ])
            )}
            style={{ gridRow: 2, gridColumn: 1 }}
          >
            {renderMiniTrendChart(
              trafficTrendRows,
              [
                { key: 'uv', label: 'UV', color: '#52c41a' },
                { key: 'pv', label: 'PV', color: '#36cfc9' },
              ],
              '暂无数据',
              66,
              `traffic-trend-${viewportTick}`
            )}
          </Card>
          <Card
            className="page-card dashboard-equal-card dashboard-bottom-cell dashboard-bottom-card-compact"
            variant="borderless"
            title="核心健康度"
            style={{ gridRow: 2, gridColumn: 2 }}
          >
            <Space
              direction="vertical"
              size={12}
              style={{ width: '100%' }}
              className="dashboard-health-list-wrap"
            >
              {healthItems.map((item) => (
                <div key={item.label} className="dashboard-inline-pair">
                  <Text strong>{item.label}</Text>
                  <Text>{item.value}</Text>
                </div>
              ))}
            </Space>
          </Card>
          <Card
            className="page-card dashboard-equal-card dashboard-bottom-cell dashboard-country-pair-card"
            variant="borderless"
            title="来源分布"
            style={{ gridRow: 3, gridColumn: 1 }}
          >
            <div className="dashboard-country-pair-grid">
              <div>
                <Table
                  rowKey={(item) => `${item.country_code || item.country_name || 'unknown'}-inquiry`}
                  size="small"
                  pagination={false}
                  locale={{ emptyText: '暂无数据' }}
                  dataSource={inquiryCountries}
                  columns={[
                    {
                      title: '询盘来源国家',
                      dataIndex: 'country_code',
                      render: (value) => renderCountryCell(value),
                    },
                    {
                      title: '询盘',
                      dataIndex: 'total_count',
                      width: 90,
                      render: (_, record) => formatNumber(record.total_count ?? record.sessions ?? 0),
                    },
                  ]}
                />
              </div>
              <div>
                <Table
                  rowKey={(item) => `${item.language_code || item.country_code || 'unknown'}-traffic`}
                  size="small"
                  pagination={false}
                  locale={{ emptyText: '暂无数据' }}
                  dataSource={trafficCountries}
                  columns={[
                    {
                      title: '流量来源语言',
                      dataIndex: 'language_code',
                      render: (value) => renderLanguageLabel(getLanguageMetaWithFlag(value), value),
                    },
                    {
                      title: 'UV',
                      dataIndex: 'uv',
                      width: 90,
                      render: (value) => formatNumber(value),
                    },
                  ]}
                />
              </div>
            </div>
          </Card>
          <Card
            className="page-card dashboard-equal-card dashboard-bottom-cell"
            variant="borderless"
            title="热门页面 UV"
            style={{ gridRow: 3, gridColumn: 2 }}
          >
            <Table
              rowKey={(item) => item.landing_page || item.title}
              size="small"
              pagination={false}
              locale={{ emptyText: '暂无数据' }}
              dataSource={topPages}
              columns={[
                {
                  title: '页面',
                  dataIndex: 'landing_page',
                  ellipsis: true,
                  render: (_, record) => renderTopPageCell(record, { compact: true }),
                },
                {
                  title: 'UV',
                  dataIndex: 'uv',
                  width: 90,
                  render: (value) => formatNumber(value),
                },
              ]}
            />
          </Card>
        </div>

      </Space>
    </PagePlaceholder>
  );
}
