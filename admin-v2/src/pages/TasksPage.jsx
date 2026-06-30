import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  Descriptions,
  Divider,
  Drawer,
  Empty,
  Form,
  Input,
  Pagination,
  Progress,
  Select,
  Space,
  Table,
  Tabs,
  Tag,
  Typography,
  message,
} from 'antd';
import PagePlaceholder from '@/components/PagePlaceholder';
import { getSiteBuildJobDetail, retrySiteBuildJob } from '@/api/siteBuild';
import {
  approveTranslationJob,
  getTaskCenterData,
  rebuildSeoSitemap,
  retrySeoJob,
  retryTranslationJob,
  updateSeo404Log,
  updateSeoRobots,
  updateSeoRoute,
  updateTranslationJob,
} from '@/api/tasks';
import { useSiteBuild } from '@/providers/SiteBuildProvider';
import { useSearchParams } from 'react-router-dom';

const { Paragraph, Text } = Typography;
const { TextArea } = Input;

const TRANSLATION_STATUS_OPTIONS = [
  { label: '全部状态', value: '' },
  { label: '待处理', value: 'pending' },
  { label: '处理中', value: 'processing' },
  { label: '已完成', value: 'completed' },
  { label: '待审核', value: 'review_required' },
  { label: '失败', value: 'failed' },
];

const SEO_404_STATUS_OPTIONS = [
  { label: '待处理', value: 'pending' },
  { label: '已解决', value: 'resolved' },
];

const SEO_ROUTE_INDEX_OPTIONS = [
  { label: '全部索引状态', value: '' },
  { label: '可索引', value: 'index' },
  { label: '不可索引', value: 'noindex' },
];

const SITE_BUILD_STATUS_OPTIONS = [
  { label: '全部状态', value: '' },
  { label: '排队中', value: 'queued' },
  { label: '进行中', value: 'running' },
  { label: '已完成', value: 'completed' },
  { label: '失败', value: 'failed' },
];

const PHASE_LABEL_MAP = {
  collect_pages: '收集页面',
  read_data: '读取数据',
  render_templates: '渲染模板',
  write_files: '写入文件',
  rebuild_sitemap: '更新站点地图',
  deploy_outputs: '发布文件',
  completed: '已完成',
  failed: '失败',
};

const SITE_BUILD_TRIGGER_LABEL_MAP = {
  manual: '手动触发',
  publish: '手动发布',
  site_config: '站点设置变更',
  homepage: '首页配置变更',
  content_update: '内容更新',
  content_delete: '内容删除',
  content_create: '内容新增',
  translation_update: '翻译更新',
  seo_update: 'SEO 更新',
  retry: '失败重试',
  deploy: '发布部署',
};

const SITE_BUILD_PAGE_TYPE_LABEL_MAP = {
  home: '首页',
  about: '关于页',
  contact: '联系页',
  sitemap: '站点地图 XML',
  robots: 'robots.txt',
  root_redirect: '根路径跳转',
  sitemap_page: 'HTML 站点地图',
  product_list: '产品列表',
  product_detail: '产品详情',
  solution_list: '方案列表',
  solution_detail: '方案详情',
  news_list: '新闻列表',
  news_detail: '新闻详情',
  case_list: '案例列表',
  case_detail: '案例详情',
  page_detail: '单页详情',
};

const TABLE_LOCALE = {
  emptyText: <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="暂无数据" />,
};

function formatNumber(value) {
  return new Intl.NumberFormat('zh-CN').format(Number(value || 0));
}

function normalizeStatus(status) {
  return String(status || '').trim().toLowerCase();
}

function statusText(status) {
  const map = {
    published: '已发布',
    generated: '已生成',
    completed: '已完成',
    resolved: '已解决',
    manual_override: '人工覆盖',
    review_required: '待审核',
    failed: '失败',
    pending: '待处理',
    processing: '处理中',
    ignored: '已忽略',
    index: '可索引',
    noindex: '不可索引',
    queued: '排队中',
    running: '进行中',
  };

  return map[normalizeStatus(status)] || status || '-';
}

function statusColor(status) {
  switch (normalizeStatus(status)) {
    case 'published':
    case 'generated':
    case 'completed':
    case 'resolved':
      return 'success';
    case 'manual_override':
    case 'review_required':
      return 'warning';
    case 'failed':
      return 'error';
    case 'pending':
    case 'processing':
    case 'queued':
    case 'running':
      return 'processing';
    default:
      return 'default';
  }
}

function renderStatus(status) {
  return <Tag color={statusColor(status)}>{statusText(status)}</Tag>;
}

function formatSiteBuildTrigger(value) {
  const key = normalizeStatus(value).replace(/-/g, '_');
  if (SITE_BUILD_TRIGGER_LABEL_MAP[key]) {
    return SITE_BUILD_TRIGGER_LABEL_MAP[key];
  }

  const raw = String(value || '').trim();
  if (!raw) {
    return '-';
  }

  return raw
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatSiteBuildPageType(value) {
  const key = normalizeStatus(value);
  if (SITE_BUILD_PAGE_TYPE_LABEL_MAP[key]) {
    return SITE_BUILD_PAGE_TYPE_LABEL_MAP[key];
  }

  return value || '-';
}

function fieldLabel(field) {
  const labelMap = {
    title_zh: '中文标题',
    title_en: '英文标题',
    summary_zh: '中文摘要',
    summary_en: '英文摘要',
    content_zh: '中文正文',
    content_en: '英文正文',
    seo_title: 'SEO 标题',
    seo_keywords: 'SEO 关键词',
    seo_description: 'SEO 描述',
    name_zh: '中文名称',
    name_en: '英文名称',
    description_zh: '中文描述',
    description_en: '英文描述',
  };

  return labelMap[field] || String(field || '').replace(/_/g, ' ');
}

function computeTaskProgress(summary, completedKeys) {
  const total = Number(summary?.total || 0);
  if (total <= 0) {
    return 100; // no tasks = fully complete
  }

  const completed = completedKeys.reduce((count, key) => count + Number(summary?.[key] || 0), 0);
  return Math.min(100, Math.max(0, Math.round((completed / total) * 100)));
}

function hasActiveTask(summary, activeKeys) {
  return activeKeys.some((key) => Number(summary?.[key] || 0) > 0);
}

function buildSummaryCards(payload, currentJob) {
  const translationSummary = payload?.translation?.summary || {};
  const seoJobsSummary = payload?.seoJobs?.summary || {};
  const siteBuildSummary = payload?.siteBuild?.summary || {};

  return [
    { label: '翻译任务', value: Number(translationSummary.total || payload?.translation?.items?.length || 0) },
    { label: '翻译完成率', value: computeTaskProgress(translationSummary, ['completed', 'review_required']), suffix: '%' },
    { label: 'SEO 任务', value: Number(seoJobsSummary.total || payload?.seoJobs?.items?.length || 0) },
    { label: 'SEO 完成率', value: computeTaskProgress(seoJobsSummary, ['completed', 'generated', 'manual_override']), suffix: '%' },
    { label: '静态生成任务', value: Number(siteBuildSummary.total || payload?.siteBuild?.items?.length || 0) },
    { label: '当前生成进度', value: Number(currentJob?.progress_percent || 0), suffix: '%' },
  ];
}

function filterByKeyword(items, keyword, fields) {
  const normalizedKeyword = String(keyword || '').trim().toLowerCase();
  if (!normalizedKeyword) {
    return Array.isArray(items) ? items : [];
  }

  return (Array.isArray(items) ? items : []).filter((item) =>
    fields.some((field) => String(item?.[field] || '').toLowerCase().includes(normalizedKeyword)),
  );
}

export default function TasksPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const { currentJob, openFullBuild } = useSiteBuild();
  const [loading, setLoading] = useState(true);
  const [payload, setPayload] = useState(null);
  const [activeTab, setActiveTab] = useState('translation');
  const [translationFilters, setTranslationFilters] = useState({ keyword: '', status: '' });
  const [seoJobFilters, setSeoJobFilters] = useState({ status: '' });
  const [siteBuildFilters, setSiteBuildFilters] = useState({ keyword: '', status: '' });
  const [translationPager, setTranslationPager] = useState({ current: 1, pageSize: 10 });
  const [seoJobPager, setSeoJobPager] = useState({ current: 1, pageSize: 10 });
  const [siteBuildPager, setSiteBuildPager] = useState({ current: 1, pageSize: 10 });
  const [translationDrawerOpen, setTranslationDrawerOpen] = useState(false);
  const [translationSubmitting, setTranslationSubmitting] = useState(false);
  const [currentTranslationJob, setCurrentTranslationJob] = useState(null);
  const [siteBuildDrawerOpen, setSiteBuildDrawerOpen] = useState(false);
  const [siteBuildDetailLoading, setSiteBuildDetailLoading] = useState(false);
  const [siteBuildRetryingId, setSiteBuildRetryingId] = useState(null);
  const [siteBuildDetail, setSiteBuildDetail] = useState(null);
  const [siteBuildItems, setSiteBuildItems] = useState([]);
  const [seoRouteFilters, setSeoRouteFilters] = useState({ keyword: '', indexStatus: '', page: 1, pageSize: 10 });
  const [seo404Filters, setSeo404Filters] = useState({ keyword: '', status: '', page: 1, pageSize: 10 });
  const [robotsSaving, setRobotsSaving] = useState(false);
  const [sitemapLoading, setSitemapLoading] = useState(false);
  const [translationForm] = Form.useForm();
  const [robotsForm] = Form.useForm();

  useEffect(() => {
    const tab = String(searchParams.get('tab') || '').trim();
    const translationStatus = String(searchParams.get('translation_status') || '').trim();
    const seoJobStatus = String(searchParams.get('seo_job_status') || '').trim();
    const seo404Status = String(searchParams.get('seo404_status') || '').trim();
    const siteBuildStatus = String(searchParams.get('site_build_status') || '').trim();
    const nextTab = ['translation', 'seo', 'site-build'].includes(tab) ? tab : 'translation';

    setActiveTab(nextTab);
    setTranslationFilters((current) => (current.status === translationStatus ? current : { ...current, status: translationStatus }));
    setSeoJobFilters((current) => (current.status === seoJobStatus ? current : { ...current, status: seoJobStatus }));
    setSeo404Filters((current) => (current.status === seo404Status ? current : { ...current, status: seo404Status, page: 1 }));
    setSiteBuildFilters((current) => (current.status === siteBuildStatus ? current : { ...current, status: siteBuildStatus }));
  }, [searchParams]);

  const loadTasks = useCallback(async () => {
    setLoading(true);
    try {
      const data = await getTaskCenterData();
      setPayload(data);
      robotsForm.setFieldsValue({
        robots_content: data?.siteFiles?.robots_content || '',
      });
    } catch (error) {
      message.error(error.message || '加载任务中心失败');
    } finally {
      setLoading(false);
    }
  }, [robotsForm]);

  useEffect(() => {
    loadTasks();
  }, [loadTasks]);

  const hasLiveTask = useMemo(() => {
    const translationSummary = payload?.translation?.summary || {};
    const seoJobsSummary = payload?.seoJobs?.summary || {};
    const siteBuildSummary = payload?.siteBuild?.summary || {};

    return (
      hasActiveTask(translationSummary, ['pending', 'processing']) ||
      hasActiveTask(seoJobsSummary, ['pending']) ||
      hasActiveTask(siteBuildSummary, ['queued', 'running']) ||
      ['queued', 'running'].includes(normalizeStatus(currentJob?.status))
    );
  }, [payload, currentJob]);

  useEffect(() => {
    if (!hasLiveTask) {
      return undefined;
    }

    const timer = window.setInterval(() => {
      loadTasks();
    }, 800);

    return () => window.clearInterval(timer);
  }, [hasLiveTask, loadTasks]);

  const syncTaskSearchParams = useCallback((nextTab, extraParams = {}) => {
    const next = new URLSearchParams(searchParams);
    next.set('tab', nextTab);

    ['translation_status', 'seo_job_status', 'seo404_status', 'site_build_status'].forEach((key) => {
      next.delete(key);
    });

    Object.entries(extraParams).forEach(([key, value]) => {
      const normalized = String(value || '').trim();
      if (normalized !== '') {
        next.set(key, normalized);
      }
    });

    setSearchParams(next, { replace: true });
  }, [searchParams, setSearchParams]);

  const summaryCards = useMemo(() => buildSummaryCards(payload, currentJob), [payload, currentJob]);

  const translationJobs = useMemo(() => {
    const items = filterByKeyword(payload?.translation?.items || [], translationFilters.keyword, [
      'entity_label',
      'entity_type',
      'language_name',
      'language_code',
      'source_title',
    ]);

    if (!translationFilters.status) {
      return items;
    }

    return items.filter((item) => normalizeStatus(item.status) === normalizeStatus(translationFilters.status));
  }, [payload, translationFilters]);

  const pagedTranslationJobs = useMemo(() => {
    const start = (translationPager.current - 1) * translationPager.pageSize;
    return translationJobs.slice(start, start + translationPager.pageSize);
  }, [translationJobs, translationPager]);

  const seoJobItems = useMemo(() => {
    const items = payload?.seoJobs?.items || [];
    if (!seoJobFilters.status) {
      return items;
    }

    return items.filter((item) => normalizeStatus(item.status) === normalizeStatus(seoJobFilters.status));
  }, [payload, seoJobFilters.status]);

  const pagedSeoJobItems = useMemo(() => {
    const start = (seoJobPager.current - 1) * seoJobPager.pageSize;
    return seoJobItems.slice(start, start + seoJobPager.pageSize);
  }, [seoJobItems, seoJobPager]);

  const seoRouteItems = useMemo(() => {
    let items = filterByKeyword(payload?.seoRoutes?.items || [], seoRouteFilters.keyword, [
      'route_path',
      'entity_type',
      'entity_label',
      'seo_title',
    ]);

    if (seoRouteFilters.indexStatus) {
      items = items.filter((item) => normalizeStatus(item.index_status) === normalizeStatus(seoRouteFilters.indexStatus));
    }

    return items;
  }, [payload, seoRouteFilters]);

  const pagedSeoRouteItems = useMemo(() => {
    const start = (seoRouteFilters.page - 1) * seoRouteFilters.pageSize;
    return seoRouteItems.slice(start, start + seoRouteFilters.pageSize);
  }, [seoRouteItems, seoRouteFilters]);

  const seo404Items = useMemo(() => {
    let items = filterByKeyword(payload?.seo404Logs?.items || [], seo404Filters.keyword, ['request_path', 'suggested_route', 'note']);
    if (seo404Filters.status) {
      items = items.filter((item) => normalizeStatus(item.fix_status || item.status) === normalizeStatus(seo404Filters.status));
    }
    return items;
  }, [payload, seo404Filters]);

  const pagedSeo404Items = useMemo(() => {
    const start = (seo404Filters.page - 1) * seo404Filters.pageSize;
    return seo404Items.slice(start, start + seo404Filters.pageSize);
  }, [seo404Items, seo404Filters]);

  const siteBuildJobs = useMemo(() => {
    const items = filterByKeyword(payload?.siteBuild?.items || [], siteBuildFilters.keyword, [
      'trigger_source',
      'scope',
      'status',
      'current_step',
    ]);

    if (!siteBuildFilters.status) {
      return items;
    }

    return items.filter((item) => normalizeStatus(item.status) === normalizeStatus(siteBuildFilters.status));
  }, [payload, siteBuildFilters]);

  const pagedSiteBuildJobs = useMemo(() => {
    const start = (siteBuildPager.current - 1) * siteBuildPager.pageSize;
    return siteBuildJobs.slice(start, start + siteBuildPager.pageSize);
  }, [siteBuildJobs, siteBuildPager]);

  const siteBuildBusy = useMemo(() => {
    const summary = payload?.siteBuild?.summary || {};
    return (
      Number(summary.queued || 0) > 0 ||
      Number(summary.running || 0) > 0 ||
      ['queued', 'running'].includes(normalizeStatus(currentJob?.status))
    );
  }, [payload, currentJob]);

  const currentSiteBuildJob = useMemo(() => {
    if (currentJob) {
      return currentJob;
    }

    return (
      (payload?.siteBuild?.items || []).find((item) => ['queued', 'running'].includes(normalizeStatus(item?.status))) || null
    );
  }, [payload, currentJob]);

  useEffect(() => {
    setTranslationPager((current) => ({ ...current, current: 1 }));
  }, [translationFilters.keyword, translationFilters.status]);

  useEffect(() => {
    setSeoJobPager((current) => ({ ...current, current: 1 }));
  }, [payload?.seoJobs?.items, seoJobFilters.status]);

  useEffect(() => {
    setSiteBuildPager((current) => ({ ...current, current: 1 }));
  }, [siteBuildFilters.keyword, siteBuildFilters.status]);

  async function openTranslationDrawer(record) {
    setCurrentTranslationJob(record);
    translationForm.setFieldsValue({
      translated_fields: record.translated_fields || {},
    });
    setTranslationDrawerOpen(true);
  }

  async function handleSaveTranslation(values, action = 'save') {
    const jobId = Number(currentTranslationJob?.id || 0);
    if (!jobId) return;

    setTranslationSubmitting(true);
    try {
      const payloadToSend = {
        translated_fields: values.translated_fields || {},
      };

      await updateTranslationJob(jobId, payloadToSend);
      if (action === 'approve') {
        await approveTranslationJob(jobId);
        message.success('译文已保存并通过审核');
      } else {
        message.success('译文已保存');
      }

      setTranslationDrawerOpen(false);
      setCurrentTranslationJob(null);
      await loadTasks();
    } catch (error) {
      message.error(error.message || '保存译文失败');
    } finally {
      setTranslationSubmitting(false);
    }
  }

  async function handleRetryTranslation(record) {
    try {
      await retryTranslationJob(record.id);
      message.success('已提交翻译重试');
      await loadTasks();
    } catch (error) {
      message.error(error.message || '重试翻译任务失败');
    }
  }

  async function handleRetrySeoJob(record) {
    try {
      await retrySeoJob(record.id);
      message.success('已提交 SEO 任务重试');
      await loadTasks();
    } catch (error) {
      message.error(error.message || '重试 SEO 任务失败');
    }
  }

  async function handleSaveSeoRoute(record, indexStatus) {
    try {
      await updateSeoRoute(record.id, { index_status: indexStatus });
      message.success('SEO 路由状态已更新');
      await loadTasks();
    } catch (error) {
      message.error(error.message || '更新 SEO 路由失败');
    }
  }

  async function handleSaveSeo404(record, fixStatus) {
    try {
      await updateSeo404Log(record.id, { fix_status: fixStatus });
      message.success('404 记录状态已更新');
      await loadTasks();
    } catch (error) {
      message.error(error.message || '更新 404 记录失败');
    }
  }

  async function handleSaveRobots(values) {
    setRobotsSaving(true);
    try {
      await updateSeoRobots({ robots_content: String(values.robots_content || '') });
      message.success('robots.txt 已更新');
      await loadTasks();
    } catch (error) {
      message.error(error.message || '更新 robots.txt 失败');
    } finally {
      setRobotsSaving(false);
    }
  }

  async function handleRebuildSitemap() {
    setSitemapLoading(true);
    try {
      await rebuildSeoSitemap();
      message.success('已提交 sitemap 重建');
      await loadTasks();
    } catch (error) {
      message.error(error.message || '重建 sitemap 失败');
    } finally {
      setSitemapLoading(false);
    }
  }

  async function openSiteBuildDrawer(record) {
    setSiteBuildDrawerOpen(true);
    setSiteBuildDetailLoading(true);

    try {
      const detail = await getSiteBuildJobDetail(record.id);
      setSiteBuildDetail(detail?.job || null);
      setSiteBuildItems(Array.isArray(detail?.items) ? detail.items : []);
    } catch (error) {
      message.error(error.message || '加载静态生成任务详情失败');
      setSiteBuildDrawerOpen(false);
    } finally {
      setSiteBuildDetailLoading(false);
    }
  }

  async function handleRetrySiteBuild(record) {
    setSiteBuildRetryingId(record.id);
    try {
      await retrySiteBuildJob(record.id);
      message.success('已提交静态生成任务重试');
      await loadTasks();
    } catch (error) {
      message.error(error.message || '重试静态生成任务失败');
    } finally {
      setSiteBuildRetryingId(null);
    }
  }

  const translationColumns = [
    {
      title: '内容',
      dataIndex: 'entity_label',
      width: 260,
      render: (_, record) => (
        <Space direction="vertical" size={0}>
          <Text strong>{record.entity_label || '-'}</Text>
          <Text type="secondary">{record.entity_type || '-'}</Text>
        </Space>
      ),
    },
    {
      title: '语言',
      dataIndex: 'language_name',
      width: 120,
      render: (_, record) => record.language_name || record.language_code || '-',
    },
    {
      title: '状态',
      dataIndex: 'status',
      width: 120,
      render: renderStatus,
    },
    {
      title: '更新时间',
      dataIndex: 'updated_at',
      width: 170,
    },
    {
      title: '操作',
      key: 'actions',
      width: 220,
      render: (_, record) => (
        <Space wrap size={4}>
          <Button size="small" onClick={() => openTranslationDrawer(record)}>
            查看译文
          </Button>
          <Button size="small" onClick={() => handleRetryTranslation(record)}>
            重试
          </Button>
        </Space>
      ),
    },
  ];

  const seoJobColumns = [
    {
      title: '对象',
      dataIndex: 'entity_label',
      render: (_, record) => (
        <Space direction="vertical" size={0}>
          <Text strong>{record.entity_label || '-'}</Text>
          <Text type="secondary">{record.entity_type || '-'}</Text>
        </Space>
      ),
    },
    {
      title: '语言',
      width: 100,
      render: (_, record) => record.language_name || record.language_code || '-',
    },
    {
      title: '状态',
      dataIndex: 'status',
      width: 120,
      render: renderStatus,
    },
    {
      title: '更新时间',
      dataIndex: 'updated_at',
      width: 170,
    },
    {
      title: '操作',
      key: 'actions',
      width: 120,
      render: (_, record) => (
        <Button size="small" onClick={() => handleRetrySeoJob(record)}>
          重试
        </Button>
      ),
    },
  ];

  const seoRouteColumns = [
    {
      title: '路由',
      dataIndex: 'route_path',
      render: (value) => value || '-',
    },
    {
      title: '对象',
      width: 160,
      render: (_, record) => record.entity_label || record.entity_type || '-',
    },
    {
      title: '索引状态',
      dataIndex: 'index_status',
      width: 160,
      render: (_, record) => (
        <Select
          size="small"
          style={{ width: '100%' }}
          options={SEO_ROUTE_INDEX_OPTIONS.filter((item) => item.value)}
          value={record.index_status || 'index'}
          onChange={(value) => handleSaveSeoRoute(record, value)}
        />
      ),
    },
  ];

  const seo404Columns = [
    {
      title: '路径',
      dataIndex: 'request_path',
      render: (value) => value || '-',
    },
    {
      title: '建议路由',
      dataIndex: 'suggested_route',
      render: (value) => value || '-',
    },
    {
      title: '状态',
      width: 160,
      render: (_, record) => (
        <Select
          size="small"
          style={{ width: '100%' }}
          options={SEO_404_STATUS_OPTIONS}
          value={record.fix_status || record.status || 'pending'}
          onChange={(value) => handleSaveSeo404(record, value)}
        />
      ),
    },
  ];

  const siteBuildColumns = [
    {
      title: '任务',
      dataIndex: 'trigger_source',
      render: (_, record) => (
        <Space direction="vertical" size={0}>
          <Text strong>{formatSiteBuildTrigger(record.trigger_source)}</Text>
          <Text type="secondary">{record.scope === 'full' ? '全站重建' : '增量生成'}</Text>
        </Space>
      ),
    },
    {
      title: '状态',
      dataIndex: 'status',
      width: 120,
      render: renderStatus,
    },
    {
      title: '进度',
      dataIndex: 'progress_percent',
      width: 220,
      render: (value, record) => (
        <Space direction="vertical" size={4} style={{ width: '100%' }}>
          <Progress percent={Number(value || 0)} size="small" showInfo={false} />
          <Text type="secondary">{PHASE_LABEL_MAP[record.current_step] || record.current_step || '等待中'}</Text>
        </Space>
      ),
    },
    {
      title: '更新时间',
      dataIndex: 'finished_at',
      width: 170,
      render: (value, record) => value || record.started_at || record.created_at || '-',
    },
    {
      title: '执行时间',
      key: 'duration',
      width: 120,
      render: (_, record) => {
        const startedAt = record?.started_at ? new Date(record.started_at) : null;
        const finishedAt = record?.finished_at ? new Date(record.finished_at) : null;
        if (!(startedAt instanceof Date) || Number.isNaN(startedAt.getTime())) {
          return '-';
        }
        const endTime = finishedAt instanceof Date && !Number.isNaN(finishedAt.getTime()) ? finishedAt : new Date();
        const diffSeconds = Math.max(0, Math.round((endTime.getTime() - startedAt.getTime()) / 1000));
        if (diffSeconds < 60) {
          return `${diffSeconds} 秒`;
        }
        return `${Math.floor(diffSeconds / 60)} 分 ${diffSeconds % 60} 秒`;
      },
    },
    {
      title: '操作',
      key: 'actions',
      width: 220,
      render: (_, record) => (
        <Space wrap size={4}>
          <Button size="small" onClick={() => openSiteBuildDrawer(record)}>
            详情
          </Button>
          <Button
            size="small"
            onClick={() => handleRetrySiteBuild(record)}
            loading={siteBuildRetryingId === record.id}
          >
            重试
          </Button>
        </Space>
      ),
    },
  ];

  return (
    <>
      <PagePlaceholder hideHeader compact>
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <div className="dashboard-metrics-grid">
            {summaryCards.map((item) => (
              <Card key={item.label} className="page-card dashboard-stat-card" bordered={false}>
                <Space direction="vertical" size={4}>
                  <Text type="secondary">{item.label}</Text>
                  <Text strong style={{ fontSize: 24 }}>
                    {formatNumber(item.value)}
                    {item.suffix || ''}
                  </Text>
                </Space>
              </Card>
            ))}
          </div>

          <Card className="page-card" bordered={false}>
            <Tabs
              activeKey={activeTab}
              onChange={(key) => {
                setActiveTab(key);
                syncTaskSearchParams(key);
              }}
              items={[
                {
                  key: 'translation',
                  label: '翻译任务',
                  children: (
                    <Space direction="vertical" size={16} style={{ width: '100%' }}>
                      <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                        <Space wrap>
                          <Input.Search
                            allowClear
                            placeholder="搜索内容名称或语言"
                            style={{ width: 260 }}
                            onSearch={(value) =>
                              setTranslationFilters((current) => ({
                                ...current,
                                keyword: value.trim(),
                              }))
                            }
                          />
                          <Select
                            style={{ width: 140 }}
                            options={TRANSLATION_STATUS_OPTIONS}
                            value={translationFilters.status}
                            onChange={(value) =>
                              setTranslationFilters((current) => ({
                                ...current,
                                status: value,
                              }))
                            }
                          />
                        </Space>
                        <Button onClick={loadTasks}>刷新</Button>
                      </Space>
                      <Table
                        rowKey="id"
                        size="small"
                        columns={translationColumns}
                        dataSource={pagedTranslationJobs}
                        pagination={{
                          current: translationPager.current,
                          pageSize: translationPager.pageSize,
                          total: translationJobs.length,
                          showSizeChanger: false,
                          onChange: (page, pageSize) =>
                            setTranslationPager({
                              current: page,
                              pageSize: pageSize || translationPager.pageSize,
                            }),
                        }}
                        locale={TABLE_LOCALE}
                        scroll={{ x: 860 }}
                      />
                    </Space>
                  ),
                },
                {
                  key: 'seo',
                  label: 'SEO 任务',
                  children: (
                    <Space direction="vertical" size={16} style={{ width: '100%' }}>
                      <Card size="small" bordered={false} className="page-card">
                        <Space direction="vertical" size={12} style={{ width: '100%' }}>
                          <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                            <Text strong>SEO 生成任务</Text>
                            <Space wrap>
                              <Select
                                style={{ width: 160 }}
                                options={[
                                  { label: '全部状态', value: '' },
                                  { label: '待处理', value: 'pending' },
                                  { label: '已生成', value: 'generated' },
                                  { label: '人工覆盖', value: 'manual_override' },
                                  { label: '失败', value: 'failed' },
                                ]}
                                value={seoJobFilters.status}
                                onChange={(value) =>
                                  setSeoJobFilters((current) => ({
                                    ...current,
                                    status: value,
                                  }))
                                }
                              />
                              <Button onClick={loadTasks}>刷新</Button>
                            </Space>
                          </Space>
                          <Table
                            rowKey="id"
                            size="small"
                            columns={seoJobColumns}
                            dataSource={pagedSeoJobItems}
                            pagination={{
                              current: seoJobPager.current,
                              pageSize: seoJobPager.pageSize,
                              total: seoJobItems.length,
                              showSizeChanger: false,
                              onChange: (page, pageSize) =>
                                setSeoJobPager({
                                  current: page,
                                  pageSize: pageSize || seoJobPager.pageSize,
                                }),
                            }}
                            locale={TABLE_LOCALE}
                            scroll={{ x: 760 }}
                          />
                        </Space>
                      </Card>

                      <Card size="small" bordered={false} className="page-card">
                        <Space direction="vertical" size={12} style={{ width: '100%' }}>
                          <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                            <Space wrap>
                              <Input.Search
                                allowClear
                                placeholder="搜索路由或对象"
                                style={{ width: 240 }}
                                onSearch={(value) =>
                                  setSeoRouteFilters((current) => ({
                                    ...current,
                                    keyword: value.trim(),
                                    page: 1,
                                  }))
                                }
                              />
                              <Select
                                style={{ width: 140 }}
                                options={SEO_ROUTE_INDEX_OPTIONS}
                                value={seoRouteFilters.indexStatus}
                                onChange={(value) =>
                                  setSeoRouteFilters((current) => ({
                                    ...current,
                                    indexStatus: value,
                                    page: 1,
                                  }))
                                }
                              />
                            </Space>
                            <Text type="secondary">{`共 ${seoRouteItems.length} 条路由`}</Text>
                          </Space>
                          <Table
                            rowKey="id"
                            size="small"
                            columns={seoRouteColumns}
                            dataSource={pagedSeoRouteItems}
                            pagination={false}
                            locale={TABLE_LOCALE}
                            scroll={{ x: 920 }}
                          />
                          <Pagination
                            current={seoRouteFilters.page}
                            pageSize={seoRouteFilters.pageSize}
                            total={seoRouteItems.length}
                            showSizeChanger
                            onChange={(page, pageSize) =>
                              setSeoRouteFilters((current) => ({
                                ...current,
                                page,
                                pageSize,
                              }))
                            }
                          />
                        </Space>
                      </Card>

                      <Card size="small" bordered={false} className="page-card">
                        <Space direction="vertical" size={12} style={{ width: '100%' }}>
                          <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                            <Space wrap>
                              <Input.Search
                                allowClear
                                placeholder="搜索 404 路径"
                                style={{ width: 240 }}
                                onSearch={(value) =>
                                  setSeo404Filters((current) => ({
                                    ...current,
                                    keyword: value.trim(),
                                    page: 1,
                                  }))
                                }
                              />
                              <Select
                                style={{ width: 140 }}
                                options={[{ label: '全部状态', value: '' }, ...SEO_404_STATUS_OPTIONS]}
                                value={seo404Filters.status}
                                onChange={(value) =>
                                  setSeo404Filters((current) => ({
                                    ...current,
                                    status: value,
                                    page: 1,
                                  }))
                                }
                              />
                            </Space>
                            <Text type="secondary">{`共 ${seo404Items.length} 条 404 记录`}</Text>
                          </Space>
                          <Table
                            rowKey="id"
                            size="small"
                            columns={seo404Columns}
                            dataSource={pagedSeo404Items}
                            pagination={false}
                            locale={TABLE_LOCALE}
                            scroll={{ x: 920 }}
                          />
                          <Pagination
                            current={seo404Filters.page}
                            pageSize={seo404Filters.pageSize}
                            total={seo404Items.length}
                            showSizeChanger
                            onChange={(page, pageSize) =>
                              setSeo404Filters((current) => ({
                                ...current,
                                page,
                                pageSize,
                              }))
                            }
                          />
                        </Space>
                      </Card>

                      <Card size="small" bordered={false} className="page-card">
                        <Space direction="vertical" size={12} style={{ width: '100%' }}>
                          <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                            <Text strong>站点文件</Text>
                            <Button loading={sitemapLoading} onClick={handleRebuildSitemap}>
                              重建 sitemap
                            </Button>
                          </Space>
                          <Descriptions bordered size="small" column={2}>
                            <Descriptions.Item label="robots 更新时间">
                              {payload?.siteFiles?.robots_updated_at || '-'}
                            </Descriptions.Item>
                            <Descriptions.Item label="sitemap 更新时间">
                              {payload?.siteFiles?.sitemap_last_generated_at || '-'}
                            </Descriptions.Item>
                            <Descriptions.Item label="sitemap 路由数">
                              {payload?.siteFiles?.sitemap_route_count || 0}
                            </Descriptions.Item>
                            <Descriptions.Item label="索引数">
                              {payload?.siteFiles?.sitemap_index_count || 0}
                            </Descriptions.Item>
                          </Descriptions>
                          <Form form={robotsForm} layout="vertical" onFinish={handleSaveRobots}>
                            <Form.Item name="robots_content" label="robots.txt">
                              <TextArea rows={8} placeholder="请输入 robots.txt 内容" />
                            </Form.Item>
                            <Button type="primary" htmlType="submit" loading={robotsSaving}>
                              保存 robots.txt
                            </Button>
                          </Form>
                        </Space>
                      </Card>
                    </Space>
                  ),
                },
                {
                  key: 'site-build',
                  label: '静态生成任务',
                  children: (
                    <Space direction="vertical" size={16} style={{ width: '100%' }}>
                      <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                        <Space wrap>
                          <Input.Search
                            allowClear
                            placeholder="搜索任务来源"
                            style={{ width: 240 }}
                            onSearch={(value) =>
                              setSiteBuildFilters((current) => ({
                                ...current,
                                keyword: value.trim(),
                              }))
                            }
                          />
                          <Select
                            style={{ width: 140 }}
                            options={SITE_BUILD_STATUS_OPTIONS}
                            value={siteBuildFilters.status}
                            onChange={(value) =>
                              setSiteBuildFilters((current) => ({
                                ...current,
                                status: value,
                              }))
                            }
                          />
                        </Space>
                        <Space wrap>
                          <Button type="primary" onClick={() => void openFullBuild()} disabled={siteBuildBusy}>
                            全站重新生成
                          </Button>
                          <Button onClick={loadTasks}>刷新</Button>
                        </Space>
                      </Space>

                      {currentSiteBuildJob ? (
                        <Card size="small" bordered={false} className="page-card">
                          <Space direction="vertical" size={8} style={{ width: '100%' }}>
                            <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                              <Text strong>当前运行中的静态生成任务</Text>
                              {renderStatus(currentSiteBuildJob.status)}
                            </Space>
                            <Progress percent={Number(currentSiteBuildJob.progress_percent || 0)} />
                            <Text type="secondary">
                              {PHASE_LABEL_MAP[currentSiteBuildJob.current_step] || currentSiteBuildJob.current_step || '等待中'}
                            </Text>
                          </Space>
                        </Card>
                      ) : null}

                      <Table
                        rowKey="id"
                        size="small"
                        columns={siteBuildColumns}
                        dataSource={pagedSiteBuildJobs}
                        pagination={{
                          current: siteBuildPager.current,
                          pageSize: siteBuildPager.pageSize,
                          total: siteBuildJobs.length,
                          showSizeChanger: false,
                          onChange: (page, pageSize) =>
                            setSiteBuildPager({
                              current: page,
                              pageSize: pageSize || siteBuildPager.pageSize,
                            }),
                        }}
                        locale={TABLE_LOCALE}
                        scroll={{ x: 980 }}
                      />
                    </Space>
                  ),
                },
              ]}
            />
          </Card>
        </Space>
      </PagePlaceholder>

      <Drawer
        title={currentTranslationJob ? `${currentTranslationJob.entity_label || '翻译任务'} 的译文校对` : '翻译任务'}
        width={920}
        open={translationDrawerOpen}
        onClose={() => {
          setTranslationDrawerOpen(false);
          setCurrentTranslationJob(null);
        }}
        destroyOnHidden
        extra={
          <Space wrap>
            <Button onClick={() => setTranslationDrawerOpen(false)}>关闭</Button>
            <Button
              loading={translationSubmitting}
              onClick={() => {
                translationForm
                  .validateFields()
                  .then((values) => handleSaveTranslation(values, 'save'))
                  .catch(() => {});
              }}
            >
              保存译文
            </Button>
            <Button
              type="primary"
              loading={translationSubmitting}
              onClick={() => {
                translationForm
                  .validateFields()
                  .then((values) => handleSaveTranslation(values, 'approve'))
                  .catch(() => {});
              }}
            >
              保存并通过
            </Button>
          </Space>
        }
      >
        {currentTranslationJob ? (
          <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Descriptions size="small" column={2}>
              <Descriptions.Item label="内容类型">
                {currentTranslationJob.entity_label || currentTranslationJob.entity_type || '-'}
              </Descriptions.Item>
              <Descriptions.Item label="目标语言">
                {currentTranslationJob.language_name || currentTranslationJob.language_code || '-'}
              </Descriptions.Item>
              <Descriptions.Item label="状态">{renderStatus(currentTranslationJob.status)}</Descriptions.Item>
              <Descriptions.Item label="更新时间">{currentTranslationJob.updated_at || '-'}</Descriptions.Item>
            </Descriptions>

            <Divider style={{ margin: 0 }} />

            <Form form={translationForm} layout="vertical">
              {Object.entries(currentTranslationJob.source_fields || {}).map(([field, sourceValue]) => (
                <Card key={field} size="small" title={fieldLabel(field)} style={{ marginBottom: 12 }}>
                  <Space direction="vertical" size={12} style={{ width: '100%' }}>
                    <div>
                      <Text strong>原文</Text>
                      <Paragraph style={{ margin: '6px 0 0' }}>
                        {String(sourceValue || '').trim() || '-'}
                      </Paragraph>
                    </div>
                    <Form.Item
                      name={['translated_fields', field]}
                      label="译文"
                      rules={[{ required: true, message: `请输入“${fieldLabel(field)}”的译文内容。` }]}
                    >
                      <TextArea
                        rows={field.includes('content') ? 6 : 3}
                        placeholder={`请输入“${fieldLabel(field)}”的译文内容`}
                      />
                    </Form.Item>
                  </Space>
                </Card>
              ))}
            </Form>
          </Space>
        ) : null}
      </Drawer>

      <Drawer
        title={siteBuildDetail ? `静态生成任务 #${siteBuildDetail.id}` : '静态生成任务'}
        width={1040}
        open={siteBuildDrawerOpen}
        onClose={() => {
          setSiteBuildDrawerOpen(false);
          setSiteBuildDetail(null);
          setSiteBuildItems([]);
        }}
        destroyOnHidden
      >
        {siteBuildDetail ? (
          <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Descriptions bordered size="small" column={2}>
              <Descriptions.Item label="状态">{renderStatus(siteBuildDetail.status)}</Descriptions.Item>
              <Descriptions.Item label="范围">{siteBuildDetail.scope === 'full' ? '全站重建' : '增量生成'}</Descriptions.Item>
              <Descriptions.Item label="触发来源">{formatSiteBuildTrigger(siteBuildDetail.trigger_source)}</Descriptions.Item>
              <Descriptions.Item label="当前阶段">
                {PHASE_LABEL_MAP[siteBuildDetail.current_step] || siteBuildDetail.current_step || '-'}
              </Descriptions.Item>
              <Descriptions.Item label="开始时间">{siteBuildDetail.started_at || '-'}</Descriptions.Item>
              <Descriptions.Item label="结束时间">{siteBuildDetail.finished_at || '-'}</Descriptions.Item>
            </Descriptions>

            <Progress
              percent={Number(siteBuildDetail.progress_percent || 0)}
              status={siteBuildDetail.status === 'failed' ? 'exception' : 'active'}
            />

            {siteBuildDetail.error_message ? (
              <Paragraph type="danger" style={{ marginBottom: 0 }}>
                {siteBuildDetail.error_message}
              </Paragraph>
            ) : null}

            <Table
              rowKey={(item) => item.id || `${item.page_type}-${item.route}`}
              loading={siteBuildDetailLoading}
              size="small"
              dataSource={siteBuildItems}
              pagination={false}
              locale={TABLE_LOCALE}
              columns={[
                { title: '语言', dataIndex: 'language_code', width: 100 },
                { title: '页面类型', dataIndex: 'page_type', width: 140, render: formatSiteBuildPageType },
                { title: '路由', dataIndex: 'route' },
                { title: '状态', dataIndex: 'status', width: 120, render: renderStatus },
              ]}
              scroll={{ x: 900 }}
            />
          </Space>
        ) : null}
      </Drawer>
    </>
  );
}
