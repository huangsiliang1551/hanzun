import { request } from '@/api/client';

export function getCategoryTree(entityType) {
  if (entityType === 'product') {
    return request('/admin/product-categories/tree');
  }
  if (entityType === 'solution') {
    return request('/admin/solution-categories/tree');
  }
  if (entityType === 'news') {
    return request('/admin/news-categories/tree');
  }
  return request('/admin/case-categories/tree');
}

export function createCategory(entityType, payload) {
  const pathMap = {
    product: '/admin/product-categories',
    solution: '/admin/solution-categories',
    news: '/admin/news-categories',
    case: '/admin/case-categories',
  };

  return request(pathMap[entityType], {
    method: 'POST',
    body: payload,
  });
}

export function updateCategory(entityType, id, payload) {
  const pathMap = {
    product: '/admin/product-categories',
    solution: '/admin/solution-categories',
    news: '/admin/news-categories',
    case: '/admin/case-categories',
  };

  return request(`${pathMap[entityType]}/${id}`, {
    method: 'POST',
    body: payload,
  });
}

export function deleteCategory(entityType, id) {
  const pathMap = {
    product: '/admin/product-categories',
    solution: '/admin/solution-categories',
    news: '/admin/news-categories',
    case: '/admin/case-categories',
  };

  return request(`${pathMap[entityType]}/${id}/delete`, {
    method: 'POST',
  });
}
