import { request } from '@/api/client';
import { getAccessToken } from '@/utils/auth';

const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/$/, '');
const FALLBACK_SERVER_UPLOAD_HINT_LIMIT = 5 * 1024 * 1024;
const SERVER_HINTS = {
  image: 5 * 1024 * 1024,
  video: 50 * 1024 * 1024,
  pdf: 20 * 1024 * 1024,
};

export const RESOURCE_UPLOAD_RULES = {
  image: {
    label: 'Image',
    extensions: ['jpg', 'jpeg', 'png', 'webp', 'svg'],
    mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
    accept: '.jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml',
    maxSize: 5 * 1024 * 1024,
  },
  video: {
    label: 'Video',
    extensions: ['mp4', 'webm'],
    mimeTypes: ['video/mp4', 'video/webm'],
    accept: '.mp4,.webm,video/mp4,video/webm',
    maxSize: 50 * 1024 * 1024,
  },
  pdf: {
    label: 'PDF',
    extensions: ['pdf'],
    mimeTypes: ['application/pdf'],
    accept: '.pdf,application/pdf',
    maxSize: 20 * 1024 * 1024,
  },
};

export const RESOURCE_UPLOAD_ACCEPT_BY_TYPE = Object.fromEntries(
  Object.entries(RESOURCE_UPLOAD_RULES).map(([type, rule]) => [type, rule.accept]),
);

export const RESOURCE_UPLOAD_ACCEPT = Object.values(RESOURCE_UPLOAD_ACCEPT_BY_TYPE).join(',');

export const RESOURCE_UPLOAD_HINTS = [
  'Supported upload types: image, video, PDF.',
  'Image max: 5MB · Video max: 50MB · PDF max: 20MB.',
  'Server check message always follows backend limits.',
];

function joinBase(path) {
  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${API_BASE_URL}${normalizedPath}`;
}

function parseJsonSafe(text) {
  if (!text) {
    return null;
  }

  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}

function createUploadError(message, payload) {
  const error = new Error(message || 'Upload failed.');
  error.payload = payload;
  return error;
}

function getUploadExtension(fileName) {
  return String(fileName || '')
    .split('.')
    .pop()
    ?.trim()
    .toLowerCase();
}

function formatFileSizeLimit(bytes) {
  const sizeInMb = bytes / 1024 / 1024;
  return Number.isInteger(sizeInMb) ? `${sizeInMb}MB` : `${sizeInMb.toFixed(1)}MB`;
}

export function getResourceUploadRule(type = '') {
  return RESOURCE_UPLOAD_RULES[type] || null;
}

export function getResourceUploadLabel(type = '') {
  return getResourceUploadRule(type)?.label || 'File';
}

export function getResourceUploadSummary(type = '') {
  const rule = getResourceUploadRule(type);
  if (!rule) {
    return RESOURCE_UPLOAD_HINTS.join(' ');
  }

  return `${rule.label} supports ${rule.extensions.map((item) => item.toUpperCase()).join(' / ')} and max size ${formatFileSizeLimit(rule.maxSize)}.`;
}

function findUploadRuleByFile(file) {
  const extension = getUploadExtension(file?.name);
  return (
    Object.entries(RESOURCE_UPLOAD_RULES).find(([, rule]) =>
      rule.extensions.includes(extension || ''),
    ) || null
  );
}

function normalizeUploadFailureMessage(message, file) {
  const text = String(message || '').trim();
  const size = Number(file?.size || 0);

  if (!text) {
    return 'Upload failed, please try again.';
  }

  if (
    text.includes('not allowed') ||
    text.includes('upload') ||
    text.includes('size')
  ) {
    const imageLimit = SERVER_HINTS.image;
    const videoLimit = SERVER_HINTS.video;
    const pdfLimit = SERVER_HINTS.pdf;
    const extension = String(file?.name || '').split('.').pop().toLowerCase();

    if (extension === 'pdf' && size > pdfLimit) {
      return `Server upload check failed. PDF files should be ${formatFileSizeLimit(pdfLimit)} or less.`;
    }

    if (['mp4', 'webm'].includes(extension) && size > videoLimit) {
      return `Server upload check failed. Video files should be ${formatFileSizeLimit(videoLimit)} or less.`;
    }

    if (size > imageLimit) {
      return `Server upload check failed. Image files should be ${formatFileSizeLimit(imageLimit)} or less.`;
    }

    return 'Server upload check failed. Please choose a valid file and retry.';
  }

  return text;
}

export function validateResourceUploadFile(file, expectedType = 'all') {
  if (!file || !file.name) {
    throw new Error('Please select a valid file.');
  }

  const matchedEntry = findUploadRuleByFile(file);
  const matchedType = matchedEntry?.[0] || '';
  const matchedRule = matchedEntry?.[1] || null;
  const expectedRule = expectedType && expectedType !== 'all' ? getResourceUploadRule(expectedType) : null;
  const mimeType = String(file?.type || '').toLowerCase();
  const fileSize = Number(file?.size || 0);

  if (!matchedRule) {
    throw new Error('Only image, video or PDF uploads are supported.');
  }

  if (expectedRule && matchedType !== expectedType) {
    throw new Error(`Expected ${expectedRule.label} file type, please choose matching format.`);
  }

  if (mimeType && !matchedRule.mimeTypes.includes(mimeType)) {
    throw new Error(`The MIME type is not allowed, please upload a ${matchedRule.label} file.`);
  }

  if (matchedRule.maxSize > 0 && fileSize > matchedRule.maxSize) {
    throw new Error(`${matchedRule.label} file is too large, keep it under ${formatFileSizeLimit(matchedRule.maxSize)}.`);
  }

  return { ok: true, type: matchedType, rule: matchedRule };
}

export function getResourceUploadAccept(type = 'all') {
  if (type && RESOURCE_UPLOAD_ACCEPT_BY_TYPE[type]) {
    return RESOURCE_UPLOAD_ACCEPT_BY_TYPE[type];
  }

  return RESOURCE_UPLOAD_ACCEPT;
}

export function getResources(params) {
  return request('/admin/media/assets', { query: params });
}

export function getResourcesBootstrap(params) {
  return request('/admin/media/bootstrap', { query: params });
}

export function getResourcePickerBootstrap(params) {
  return request('/admin/media/bootstrap', {
    query: {
      ...params,
      status: params?.status ?? 1,
    },
  });
}

export function getResourceDetail(id) {
  return request(`/admin/media/assets/${id}`);
}

export function getResourceReferences(id) {
  return request(`/admin/media/assets/${id}/references`);
}

export function updateResource(id, payload) {
  return request(`/admin/media/assets/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function updateResourceStatus(id, status) {
  return request(`/admin/media/assets/${id}/status`, {
    method: 'PATCH',
    body: { status },
  });
}

export function renameResource(id, fileName) {
  return request(`/admin/media/assets/${id}/rename`, {
    method: 'PATCH',
    body: { file_name: fileName },
  });
}

export function deleteResource(id) {
  return request(`/admin/media/assets/${id}`, { method: 'DELETE' });
}

export function moveResources(ids, targetFolderId) {
  return request('/admin/media/assets/batch/move', {
    method: 'POST',
    body: {
      ids,
      target_folder_id: targetFolderId,
    },
  });
}

export function copyResources(ids, targetFolderId) {
  return request('/admin/media/assets/batch/copy', {
    method: 'POST',
    body: {
      ids,
      target_folder_id: targetFolderId,
    },
  });
}

export function batchDeleteResources(ids) {
  return request('/admin/media/assets/batch/delete', {
    method: 'POST',
    body: { ids },
  });
}

export function getResourceFolders() {
  return request('/admin/media/folders');
}

export function createResourceFolder(payload) {
  return request('/admin/media/folders', {
    method: 'POST',
    body: payload,
  });
}

export function updateResourceFolder(id, payload) {
  return request(`/admin/media/folders/${id}`, {
    method: 'PUT',
    body: payload,
  });
}

export function deleteResourceFolder(id) {
  return request(`/admin/media/folders/${id}`, {
    method: 'DELETE',
  });
}

export function uploadResourceFile(file, options = {}) {
  const { folderId = 0, altText = '', description = '', onProgress } = options;

  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    const formData = new FormData();

    formData.append('file', file);
    formData.append('folder_id', String(folderId || 0));

    if (altText) {
      formData.append('alt_text_zh', altText);
    }

    if (description) {
      formData.append('description_zh', description);
    }

    xhr.open('POST', joinBase('/admin/media/assets/upload'));
    xhr.timeout = 120000;

    const token = getAccessToken();
    if (token) {
      xhr.setRequestHeader('Authorization', `Bearer ${token}`);
    }

    xhr.setRequestHeader('Accept', 'application/json');

    xhr.upload.onprogress = (event) => {
      if (!event.lengthComputable || typeof onProgress !== 'function') {
        return;
      }

      const percent = Math.round((event.loaded / event.total) * 100);
      onProgress({ percent });
    };

    xhr.onerror = () => {
      reject(createUploadError('Upload failed, please retry.'));
    };

    xhr.ontimeout = () => {
      reject(createUploadError('Upload timed out, please retry.'));
    };

    xhr.onload = () => {
      const payload = parseJsonSafe(xhr.responseText);

      if (xhr.status < 200 || xhr.status >= 300) {
        reject(
          createUploadError(
            normalizeUploadFailureMessage(
              payload && typeof payload === 'object' && payload.message ? payload.message : 'Upload failed.',
              file,
            ),
            payload,
          ),
        );
        return;
      }

      if (payload && typeof payload === 'object' && 'code' in payload && Number(payload.code) !== 0) {
        reject(
          createUploadError(
            normalizeUploadFailureMessage(payload.message || 'Upload failed.', file),
            payload,
          ),
        );
        return;
      }

      const data = payload && typeof payload === 'object' && 'data' in payload ? payload.data : payload;
      resolve(data);
    };

    xhr.send(formData);
  });
}
