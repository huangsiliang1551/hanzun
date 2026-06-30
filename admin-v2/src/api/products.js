import { request } from '@/api/client';

export function getProducts(params) {
  return request('/admin/products', {
    query: params,
  });
}

export function getProductCategories() {
  return request('/admin/product-categories/tree');
}

export function getProductLookups() {
  return request('/admin/products/lookups');
}

export function getProductDetail(id) {
  return request(`/admin/products/${id}`);
}

export function getProductBootstrap(id) {
  return request(`/admin/products/${id}/bootstrap`);
}

export function getProductWorkflow(id) {
  return request(`/admin/products/${id}/workflow`);
}

export function createProduct(payload) {
  return request('/admin/products', {
    method: 'POST',
    body: payload,
  });
}

export function updateProduct(id, payload) {
  return request(`/admin/products/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function updateProductPublishStatus(id, publishStatus) {
  return request(`/admin/products/${id}/publish`, {
    method: 'PATCH',
    body: {
      publish_status: publishStatus,
    },
  });
}

export function restoreProductLive(id) {
  return request(`/admin/products/${id}/restore-live`, {
    method: 'POST',
    body: {},
  });
}

export function batchUpdateProductPublishStatus(ids, publishStatus) {
  return request('/admin/products/batch-publish', {
    method: 'POST',
    body: {
      ids,
      publish_status: publishStatus,
    },
  });
}

export function deleteProduct(id) {
  return request(`/admin/products/${id}`, {
    method: 'DELETE',
  });
}

export function batchDeleteProducts(ids) {
  return request('/admin/products/batch-delete', {
    method: 'POST',
    body: {
      ids,
    },
  });
}
