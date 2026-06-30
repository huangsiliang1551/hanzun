import { request } from '@/api/client';

function buildRangeQuery(params = {}) {
  const { range = '7d', startDate, endDate } = params;
  const query = { range };

  if (range === 'custom') {
    query.start_date = startDate || undefined;
    query.end_date = endDate || undefined;
  }
  if (params.lite === true || params.lite === 1 || params.lite === '1') {
    query.lite = 1;
  }

  return query;
}

export function getDashboardTraffic(params = {}) {
  return request('/admin/dashboard/traffic', {
    query: buildRangeQuery(params),
  });
}

export function getDashboardAiConversations(params = {}) {
  return request('/admin/dashboard/ai-conversations', {
    query: buildRangeQuery(params),
  });
}

export function getDashboardInquiries(params = {}) {
  return request('/admin/dashboard/inquiries', {
    query: buildRangeQuery(params),
  });
}

export function getDashboardJobs() {
  return request('/admin/dashboard/jobs');
}

export function getDashboardOverview(params = {}) {
  return request('/admin/dashboard/overview', {
    query: buildRangeQuery(params),
  });
}
