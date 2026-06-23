import { request } from './client';

export function getHomepageBootstrap() {
  return request('/admin/homepage/bootstrap');
}

export function getHomepageSections() {
  return request('/admin/homepage/sections');
}

export function getHomepageSectionDetail(id) {
  return request(`/admin/homepage/sections/${id}`);
}

export function getHomepageSectionItems(id) {
  return request(`/admin/homepage/sections/${id}/items`);
}

export function createHomepageSection(payload) {
  return request('/admin/homepage/sections', {
    method: 'POST',
    body: payload,
  });
}

export function updateHomepageSection(id, payload) {
  return request(`/admin/homepage/sections/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function updateHomepageSectionItems(id, items) {
  return request(`/admin/homepage/sections/${id}/items`, {
    method: 'PUT',
    body: {
      items,
    },
  });
}

export function updateHomepageSectionStatus(id, isEnabled) {
  return request(`/admin/homepage/sections/${id}/status`, {
    method: 'PATCH',
    body: {
      is_enabled: isEnabled,
    },
  });
}

export function getHomepageWorkflow() {
  return request('/admin/homepage/workflow');
}

export function getHomepagePreview() {
  return request('/admin/homepage/preview');
}

export function publishHomepage() {
  return request('/admin/homepage/publish', {
    method: 'POST',
  });
}

export function restoreHomepageLive() {
  return request('/admin/homepage/restore-live', {
    method: 'POST',
  });
}

export function getHomepageSourceOptions(sourceType) {
  if (sourceType === 'product') {
    return request('/admin/products', {
      query: {
        page: 1,
        page_size: 100,
        publish_status: 'published',
      },
    });
  }

  if (sourceType === 'solution') {
    return request('/admin/solutions', {
      query: {
        page: 1,
        page_size: 100,
        publish_status: 'published',
      },
    });
  }

  if (sourceType === 'news') {
    return request('/admin/news', {
      query: {
        page: 1,
        page_size: 100,
        publish_status: 'published',
      },
    });
  }

  if (sourceType === 'case') {
    return request('/admin/cases', {
      query: {
        page: 1,
        page_size: 100,
        publish_status: 'published',
      },
    });
  }

  throw new Error(`Unsupported homepage source type: ${sourceType}`);
}
