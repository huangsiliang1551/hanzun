import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { App as AntdApp, Button, Modal, Progress, Space, Tag, Typography } from 'antd';
import { useLocation } from 'react-router-dom';
import { useNavigate } from 'react-router-dom';
import {
  createSiteBuildJob,
  getCurrentSiteBuildJob,
  getSiteBuildJobDetail,
} from '@/api/siteBuild';
import { useAuth } from '@/providers/AuthProvider';
import { shouldPollSiteBuildStatus } from '@/utils/siteBuildPollingPolicy';

const { Paragraph, Text } = Typography;

const SiteBuildContext = createContext({
  currentJob: null,
  openFullBuild: async () => {},
});

const phaseLabelMap = {
  collect_pages: '\u6536\u96c6\u9875\u9762',
  read_data: '\u8bfb\u53d6\u6570\u636e',
  render_templates: '\u6e32\u67d3\u6a21\u677f',
  write_files: '\u5199\u5165\u6587\u4ef6',
  rebuild_sitemap: '\u66f4\u65b0\u7ad9\u70b9\u5730\u56fe',
  deploy_outputs: '\u53d1\u5e03\u6587\u4ef6',
  completed: '\u5df2\u5b8c\u6210',
  failed: '\u5931\u8d25',
};

const statusLabelMap = {
  queued: '\u5df2\u6392\u961f',
  running: '\u8fdb\u884c\u4e2d',
  completed: '\u5df2\u5b8c\u6210',
  failed: '\u5931\u8d25',
};

function normalizeJob(job) {
  return job && typeof job === 'object' ? job : null;
}

function normalizeStatus(status) {
  return String(status || '').trim().toLowerCase();
}

function isActiveStatus(status) {
  return ['queued', 'running'].includes(normalizeStatus(status));
}

function buildStatusColor(status) {
  const normalized = normalizeStatus(status);
  if (normalized === 'failed') return 'error';
  if (normalized === 'completed') return 'success';
  return 'processing';
}

function translateJobStatus(status) {
  const key = normalizeStatus(status);
  return statusLabelMap[key] || status || '\u672a\u77e5\u72b6\u6001';
}

export function SiteBuildProvider({ children }) {
  const { message } = AntdApp.useApp();
  const navigate = useNavigate();
  const location = useLocation();
  const { authenticated, bootstrapping } = useAuth();
  const [currentJob, setCurrentJob] = useState(null);
  const [activeJob, setActiveJob] = useState(null);
  const [jobItems, setJobItems] = useState([]);
  const [modalOpen, setModalOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [floatingDismissedJobId, setFloatingDismissedJobId] = useState(null);

  async function loadDetail(jobId, openModal = true) {
    if (!jobId) return;

    setLoadingDetail(true);
    try {
      const payload = await getSiteBuildJobDetail(jobId);
      const job = normalizeJob(payload?.job);
      setActiveJob(job);
      setJobItems(Array.isArray(payload?.items) ? payload.items : []);
      if (job) {
        setCurrentJob(job);
      }
      if (openModal) {
        setModalOpen(true);
      }
    } catch (error) {
      message.error(error.message || '\u52a0\u8f7d\u9759\u6001\u751f\u6210\u4efb\u52a1\u8be6\u60c5\u5931\u8d25\u3002');
    } finally {
      setLoadingDetail(false);
    }
  }

  async function refreshCurrent() {
    try {
      const payload = await getCurrentSiteBuildJob();
      const job = normalizeJob(payload?.job);

      if (job) {
        setCurrentJob(job);
        if (activeJob?.id === job.id || modalOpen) {
          await loadDetail(job.id, false);
        }
        return;
      }

      if (activeJob?.id && isActiveStatus(activeJob.status)) {
        await loadDetail(activeJob.id, false);
        return;
      }

      setCurrentJob(null);
    } catch (error) {
      if (modalOpen) {
        message.error(error.message || '\u5237\u65b0\u9759\u6001\u751f\u6210\u4efb\u52a1\u72b6\u6001\u5931\u8d25\u3002');
      }
    }
  }

  async function openFullBuild() {
    const busyJob = currentJob || activeJob;
    if (busyJob && isActiveStatus(busyJob.status)) {
      message.info('\u5df2\u6709\u9759\u6001\u751f\u6210\u4efb\u52a1\u6b63\u5728\u6267\u884c\uff0c\u8bf7\u7b49\u5f85\u5f53\u524d\u4efb\u52a1\u5b8c\u6210\u3002');
      if (busyJob.id) {
        await loadDetail(busyJob.id, true);
      }
      return;
    }

    setCreating(true);
    try {
      const payload = await createSiteBuildJob({
        scope: 'full',
        trigger_source: 'manual_full_rebuild',
      });
      const job = normalizeJob(payload?.job);
      if (job) {
        setActiveJob(job);
        setCurrentJob(job);
        setModalOpen(true);
      }
      message.success('\u5df2\u63d0\u4ea4\u5168\u7ad9\u91cd\u65b0\u751f\u6210\u4efb\u52a1\u3002');
    } catch (error) {
      message.error(error.message || '\u521b\u5efa\u9759\u6001\u751f\u6210\u4efb\u52a1\u5931\u8d25\u3002');
    } finally {
      setCreating(false);
    }
  }

  useEffect(() => {
    if (!shouldPollSiteBuildStatus({
      authenticated,
      bootstrapping,
      pathname: location.pathname,
      hash: location.hash,
    })) {
      setCurrentJob(null);
      setActiveJob(null);
      setJobItems([]);
      setModalOpen(false);
      setFloatingDismissedJobId(null);
      return undefined;
    }

    refreshCurrent();
    const activeStatus = normalizeStatus(currentJob?.status || activeJob?.status || '');
    const timer = window.setInterval(refreshCurrent, isActiveStatus(activeStatus) ? 800 : 5000);
    return () => window.clearInterval(timer);
  }, [authenticated, bootstrapping, location.pathname, location.hash, currentJob?.status, activeJob?.status, modalOpen]);

  useEffect(() => {
    const currentJobId = Number(currentJob?.id || 0);
    const currentStatus = normalizeStatus(currentJob?.status || '');

    if (currentJobId <= 0 || !isActiveStatus(currentStatus)) {
      setFloatingDismissedJobId(null);
      return;
    }

    if (floatingDismissedJobId !== null && floatingDismissedJobId !== currentJobId) {
      setFloatingDismissedJobId(null);
    }
  }, [currentJob?.id, currentJob?.status, floatingDismissedJobId]);

  useEffect(() => {
    function handleCreated(event) {
      const job = normalizeJob(event?.detail?.job || event?.detail);
      if (!job?.id) return;
      setCurrentJob(job);
      loadDetail(job.id, true);
    }

    window.addEventListener('site-build-job-created', handleCreated);
    return () => window.removeEventListener('site-build-job-created', handleCreated);
  }, []);

  const contextValue = useMemo(
    () => ({
      currentJob,
      openFullBuild,
    }),
    [currentJob],
  );

  const displayJob = activeJob || currentJob;
  const isRunning = isActiveStatus(displayJob?.status);
  const currentStepLabel = phaseLabelMap[displayJob?.current_step] || displayJob?.current_step || '\u7b49\u5f85\u5f00\u59cb';
  const floatingCardVisible =
    currentJob && !modalOpen && isRunning && Number(floatingDismissedJobId || 0) !== Number(currentJob?.id || 0);

  return (
    <SiteBuildContext.Provider value={contextValue}>
      {children}

      {floatingCardVisible ? (
        <div className="site-build-floating-entry">
          <Space direction="vertical" size={6} style={{ width: '100%' }}>
            <div className="site-build-floating-head">
              <Text strong>{'\u9759\u6001\u751f\u6210\u4efb\u52a1\u8fdb\u884c\u4e2d'}</Text>
              <Button
                size="small"
                type="text"
                className="site-build-floating-close"
                onClick={() => setFloatingDismissedJobId(Number(currentJob?.id || 0))}
              >
                {'\u5173\u95ed\u63d0\u9192'}
              </Button>
            </div>
            <Progress
              percent={Number(displayJob?.progress_percent || 0)}
              size="small"
              status="active"
              showInfo={false}
            />
            <Space size={8} wrap>
              <Tag color="blue">{currentStepLabel}</Tag>
              <Button size="small" type="link" onClick={() => loadDetail(currentJob.id, true)}>
                {'\u67e5\u770b\u8be6\u60c5'}
              </Button>
            </Space>
          </Space>
        </div>
      ) : null}

      <Modal
        title={'\u9759\u6001\u9875\u9762\u751f\u6210'}
        open={modalOpen}
        onCancel={() => setModalOpen(false)}
        footer={[
          <Button
            key="tasks"
            onClick={() => {
              setModalOpen(false);
              navigate('/tasks');
            }}
          >
            {'\u8f6c\u5230\u4efb\u52a1\u4e2d\u5fc3'}
          </Button>,
          <Button key="close" type="primary" onClick={() => setModalOpen(false)}>
            {'\u5173\u95ed\u7a97\u53e3'}
          </Button>,
        ]}
        confirmLoading={loadingDetail || creating}
        destroyOnHidden={false}
      >
        <Space direction="vertical" size={14} style={{ width: '100%' }}>
          <div className="site-build-topbar">
            {displayJob?.status ? (
              <Tag color={buildStatusColor(displayJob.status)}>{translateJobStatus(displayJob.status)}</Tag>
            ) : null}
          </div>

          <Progress
            percent={Number(displayJob?.progress_percent || 0)}
            status={
              displayJob?.status === 'failed'
                ? 'exception'
                : displayJob?.status === 'completed'
                  ? 'success'
                  : 'active'
            }
          />

          <Space size={[8, 8]} wrap>
            <Tag color="blue">{currentStepLabel}</Tag>
            <Tag>{`\u5df2\u5b8c\u6210 ${Number(displayJob?.completed_steps || 0)} / ${Number(displayJob?.total_steps || 0)}`}</Tag>
            {displayJob?.scope ? (
              <Tag>{displayJob.scope === 'full' ? '\u5168\u7ad9\u91cd\u5efa' : '\u589e\u91cf\u751f\u6210'}</Tag>
            ) : null}
          </Space>

          {displayJob?.error_message ? (
            <Paragraph type="danger" style={{ marginBottom: 0 }}>
              {displayJob.error_message}
            </Paragraph>
          ) : null}

          <div className="site-build-items">
            {jobItems.slice(0, 8).map((item) => (
              <div key={item.id || `${item.page_type}-${item.route}`} className="site-build-item-row">
                <Text ellipsis>{item.route}</Text>
                <Tag color={buildStatusColor(item.status)}>{translateJobStatus(item.status)}</Tag>
              </div>
            ))}
          </div>
        </Space>
      </Modal>
    </SiteBuildContext.Provider>
  );
}

export function useSiteBuild() {
  return useContext(SiteBuildContext);
}
