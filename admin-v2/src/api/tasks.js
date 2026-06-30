import { request } from '@/api/client';
import { getSiteBuildJobs } from '@/api/siteBuild';

const TASK_CENTER_TIMEOUT = 60000;

export function getTaskCenterOverview() {
  return request('/admin/tasks/overview', {
    timeout: TASK_CENTER_TIMEOUT,
  });
}

export function getTranslationJobs() {
  return request('/admin/translations/jobs', {
    timeout: TASK_CENTER_TIMEOUT,
  });
}

export function updateTranslationJob(id, payload) {
  return request(`/admin/translations/jobs/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function approveTranslationJob(id) {
  return request(`/admin/translations/jobs/${id}/approve`, {
    method: 'POST',
    body: {},
  });
}

export function retryTranslationJob(id) {
  return request(`/admin/translations/jobs/${id}/retry`, {
    method: 'POST',
    body: {},
  });
}

export function getSeoJobs() {
  return request('/admin/seo-center/jobs', {
    timeout: TASK_CENTER_TIMEOUT,
  });
}

export function retrySeoJob(id) {
  return request(`/admin/seo-center/jobs/${id}/retry`, {
    method: 'POST',
    body: {},
  });
}

export function getSeoRoutes() {
  return request('/admin/seo-center/routes', {
    timeout: TASK_CENTER_TIMEOUT,
  });
}

export function updateSeoRoute(id, payload) {
  return request(`/admin/seo-center/routes/${id}`, {
    method: 'PATCH',
    body: payload,
  });
}

export function getSeo404Logs() {
  return request('/admin/seo-center/404-logs', {
    timeout: TASK_CENTER_TIMEOUT,
  });
}

export function updateSeo404Log(id, payload) {
  return request(`/admin/seo-center/404-logs/${id}`, {
    method: 'PATCH',
    body: payload,
  });
}

export function getSeoSiteFiles() {
  return request('/admin/seo-center/site-files', {
    timeout: TASK_CENTER_TIMEOUT,
  });
}

export function updateSeoRobots(payload) {
  return request('/admin/seo-center/robots', {
    method: 'PUT',
    body: payload,
  });
}

export function rebuildSeoSitemap() {
  return request('/admin/seo-center/sitemap/rebuild', {
    method: 'POST',
    body: {},
  });
}

export async function getTaskCenterData() {
  const [translation, seoJobs, seoRoutes, seo404Logs, siteFiles, siteBuild] = await Promise.allSettled([
    getTranslationJobs(),
    getSeoJobs(),
    getSeoRoutes(),
    getSeo404Logs(),
    getSeoSiteFiles(),
    getSiteBuildJobs(),
  ]);

  const fallbackList = { items: [], summary: {} };
  const fallbackSiteFiles = {
    robots_content: '',
    robots_updated_at: null,
    sitemap_last_generated_at: null,
    sitemap_route_count: 0,
    sitemap_index_count: 0,
  };

  return {
    translation: translation.status === 'fulfilled' ? translation.value : fallbackList,
    seoJobs: seoJobs.status === 'fulfilled' ? seoJobs.value : fallbackList,
    seoRoutes: seoRoutes.status === 'fulfilled' ? seoRoutes.value : fallbackList,
    seo404Logs: seo404Logs.status === 'fulfilled' ? seo404Logs.value : fallbackList,
    siteFiles: siteFiles.status === 'fulfilled' ? siteFiles.value : fallbackSiteFiles,
    siteBuild:
      siteBuild.status === 'fulfilled'
        ? siteBuild.value
        : { items: [], summary: {}, current: null },
  };
}
