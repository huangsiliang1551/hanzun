import { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Button,
  Card,
  Col,
  Descriptions,
  Divider,
  Drawer,
  Empty,
  Form,
  Image,
  Input,
  List,
  Modal,
  Pagination,
  Popconfirm,
  Progress,
  Row,
  Select,
  Space,
  Switch,
  Table,
  Tag,
  Tree,
  Typography,
  Upload,
  message,
  notification,
} from 'antd';
import {
  CopyOutlined,
  DeleteOutlined,
  DownloadOutlined,
  EditOutlined,
  EyeOutlined,
  FileImageOutlined,
  FilePdfOutlined,
  FolderAddOutlined,
  FolderOpenOutlined,
  InboxOutlined,
  PlayCircleOutlined,
  ReloadOutlined,
  UploadOutlined,
} from '@ant-design/icons';
import {
  RESOURCE_UPLOAD_HINTS,
  batchDeleteResources,
  copyResources,
  createResourceFolder,
  deleteResource,
  deleteResourceFolder,
  getResourceDetail,
  getResourceUploadAccept,
  getResourceUploadLabel,
  getResourceUploadSummary,
  getResourcesBootstrap,
  getResourceReferences,
  moveResources,
  renameResource,
  updateResource,
  updateResourceFolder,
  updateResourceStatus,
  uploadResourceFile,
  validateResourceUploadFile,
} from '@/api/resources';
import PagePlaceholder from '@/components/PagePlaceholder';
import TableSelectionFooter from '@/components/TableSelectionFooter';
import { isImageAsset, isPdfAsset, isVideoAsset, resolveAssetUrl } from '@/utils/media';
import { getResourcePosterPath, getResourcePreviewPath } from '@/utils/resourcePreview';


const { Paragraph, Text } = Typography;
const { TextArea } = Input;

const rootFolderKey = 'root';

const fileCategoryOptions = [
  { label: '全部类型', value: '' },
  { label: '图片', value: 'image' },
  { label: '视频', value: 'video' },
  { label: 'PDF', value: 'pdf' },
];

const statusOptions = [
  { label: '全部状态', value: '' },
  { label: '启用', value: 1 },
  { label: '停用', value: 0 },
];

const sortOptions = [
  { label: '更新时间: 最新', value: 'updated_at:desc' },
  { label: '更新时间: 最早', value: 'updated_at:asc' },
  { label: '创建时间: 最新', value: 'created_at:desc' },
  { label: '创建时间: 最早', value: 'created_at:asc' },
  { label: '文件名: 升序', value: 'file_name:asc' },
  { label: '文件名: 降序', value: 'file_name:desc' },
  { label: '文件大小: 从大到小', value: 'file_size:desc' },
  { label: '文件大小: 从小到大', value: 'file_size:asc' },
];

function formatFileSize(value) {
  const size = Number(value || 0);
  if (size <= 0) {
    return '-';
  }
  if (size < 1024) {
    return `${size} B`;
  }
  if (size < 1024 * 1024) {
    return `${(size / 1024).toFixed(1)} KB`;
  }
  if (size < 1024 * 1024 * 1024) {
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
  }
  return `${(size / (1024 * 1024 * 1024)).toFixed(1)} GB`;
}

function formatDate(value) {
  if (!value) {
    return '-';
  }

  const date = new Date(String(value).replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) {
    return String(value);
  }
  return date.toLocaleString();
}

function flattenFolders(nodes, bucket = []) {
  (Array.isArray(nodes) ? nodes : []).forEach((node) => {
    bucket.push(node);
    flattenFolders(node.children || [], bucket);
  });
  return bucket;
}

function collectDescendantFolderIds(node, bucket = new Set()) {
  (node?.children || []).forEach((child) => {
    const childId = Number(child.id || 0);
    if (childId > 0) {
      bucket.add(childId);
    }
    collectDescendantFolderIds(child, bucket);
  });
  return bucket;
}

function buildFolderTreeNode(node) {
  return {
    key: String(node.id),
    title: (
      <span className="resource-tree-title">
        <span className="resource-tree-title-prefix">
          <FolderOpenOutlined />
        </span>
        <span className="resource-tree-title-name">{node.name}</span>
        <Text type="secondary" className="resource-tree-title-count">{Number(node.asset_count || 0)}</Text>
      </span>
    ),
    icon: <FolderOpenOutlined />,
    children: (node.children || []).map((child) => buildFolderTreeNode(child)),
  };
}

function buildFolderTreeData(nodes) {
  return [
    {
      key: rootFolderKey,
      title: (
        <span className="resource-tree-title">
          <span className="resource-tree-title-prefix">
            <FolderOpenOutlined />
          </span>
          <span className="resource-tree-title-name">全部文件</span>
        </span>
      ),
      icon: <FolderOpenOutlined />,
      children: (nodes || []).map((node) => buildFolderTreeNode(node)),
    },
  ];
}

function findFolderName(folderMap, record) {
  const folderId = Number(record?.folder_id || 0);
  if (folderId > 0 && folderMap.has(folderId)) {
    return folderMap.get(folderId).name;
  }
  return String(record?.folder_name || '未分类');
}

function renderAssetCategory(record) {
  if (isImageAsset(record)) {
    return <Tag color="blue">图片</Tag>;
  }
  if (isVideoAsset(record)) {
    return <Tag color="purple">视频</Tag>;
  }
  if (isPdfAsset(record)) {
    return <Tag color="gold">PDF</Tag>;
  }
  return <Tag>文件</Tag>;
}

function renderPreview(record) {
  const thumbnailUrl = resolveAssetUrl(getResourcePreviewPath(record));
  const fileUrl = resolveAssetUrl(record?.file_path || '');

  if (isImageAsset(record) && (thumbnailUrl || fileUrl)) {
    return (
      <div className="resource-preview-card">
        <Image
          src={thumbnailUrl || fileUrl}
          alt={record?.file_name || '预览图'}
          width={72}
          height={72}
          className="resource-preview-image"
          style={{ objectFit: 'cover' }}
          preview={false}
        />
      </div>
    );
  }

  if (isVideoAsset(record) && (thumbnailUrl || fileUrl)) {
    return (
      <div className="resource-preview-card resource-preview-card-video">
        {thumbnailUrl ? (
          <img
            src={thumbnailUrl}
            alt={record?.file_name || '视频缩略图'}
            className="resource-preview-media"
          />
        ) : (
          <video
            src={fileUrl}
            width={72}
            height={72}
            muted
            playsInline
            preload="metadata"
            poster={thumbnailUrl || undefined}
            className="resource-preview-media"
          />
        )}
        <div className="resource-preview-overlay">
          <PlayCircleOutlined style={{ fontSize: 24, color: '#fff' }} />
        </div>
      </div>
    );
  }

  if (isVideoAsset(record)) {
    return (
      <div className="resource-preview-card resource-preview-card-fallback resource-preview-card-video-fallback">
        <PlayCircleOutlined style={{ fontSize: 24, color: '#6b7280' }} />
      </div>
    );
  }

  if (isPdfAsset(record)) {
    return (
      <div className="resource-preview-card resource-preview-card-fallback resource-preview-card-pdf">
        <FilePdfOutlined style={{ fontSize: 24, color: '#d48806' }} />
      </div>
    );
  }

  return (
    <div className="resource-preview-card resource-preview-card-fallback">
      <FileImageOutlined style={{ fontSize: 24, color: '#6b7280' }} />
    </div>
  );
}

async function copyText(value, successMessage) {
  const text = String(value || '').trim();
  if (!text) {
    throw new Error('没有可复制的内容。');
  }

  if (navigator?.clipboard?.writeText) {
    await navigator.clipboard.writeText(text);
  } else {
    const input = document.createElement('textarea');
    input.value = text;
    input.readOnly = true;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
  }

  return successMessage;
}

function downloadAsset(record) {
  const url = resolveAssetUrl(record?.file_path || '');
  if (!url) {
    throw new Error('文件地址为空。');
  }

  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = record?.original_name || record?.file_name || 'download';
  anchor.target = '_blank';
  anchor.rel = 'noreferrer';
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
}

export default function ResourcesPage() {
  const [filters, setFilters] = useState({
    keyword: '',
    file_category: '',
    status: '',
    folder_id: '',
    page: 1,
    page_size: 12,
    sort_field: 'updated_at',
    sort_order: 'desc',
  });
  const [keywordInput, setKeywordInput] = useState('');
  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, page_size: 12, total: 0 });
  const [folderTree, setFolderTree] = useState([]);
  const [folderCounts, setFolderCounts] = useState({});
  const [listLoading, setListLoading] = useState(false);
  const [foldersLoading, setFoldersLoading] = useState(false);
  const [detailOpen, setDetailOpen] = useState(false);
  const [detailLoading, setDetailLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [referencesLoading, setReferencesLoading] = useState(false);
  const [references, setReferences] = useState([]);
  const [currentItem, setCurrentItem] = useState(null);
  const [selectedRowKeys, setSelectedRowKeys] = useState([]);
  const [folderModalOpen, setFolderModalOpen] = useState(false);
  const [folderModalMode, setFolderModalMode] = useState('create');
  const [folderSubmitting, setFolderSubmitting] = useState(false);
  const [folderEditingNode, setFolderEditingNode] = useState(null);
  const [batchAction, setBatchAction] = useState('');
  const [batchFolderModalOpen, setBatchFolderModalOpen] = useState(false);
  const [batchSubmitting, setBatchSubmitting] = useState(false);
  const [uploadTasks, setUploadTasks] = useState([]);
  const [editForm] = Form.useForm();
  const [folderForm] = Form.useForm();
  const [batchFolderForm] = Form.useForm();

  const flatFolders = useMemo(() => flattenFolders(folderTree, []), [folderTree]);
  const folderMap = useMemo(
    () => new Map(flatFolders.map((folder) => [Number(folder.id), folder])),
    [flatFolders],
  );
  const folderTreeData = useMemo(() => buildFolderTreeData(folderTree), [folderTree]);
  const folderSelectOptions = useMemo(
    () => [
      { label: '根目录 / 未分类', value: 0 },
      ...flatFolders.map((folder) => ({
        label: folder.name,
        value: Number(folder.id),
      })),
    ],
    [flatFolders],
  );
  const folderParentOptions = useMemo(() => {
    if (!folderEditingNode) {
      return folderSelectOptions;
    }

    const invalidIds = collectDescendantFolderIds(
      folderEditingNode,
      new Set([Number(folderEditingNode.id || 0)]),
    );
    return folderSelectOptions.filter((option) => !invalidIds.has(Number(option.value || 0)));
  }, [folderEditingNode, folderSelectOptions]);

  const selectedFolder =
    Number(filters.folder_id || 0) > 0 ? folderMap.get(Number(filters.folder_id || 0)) : null;
  const activeSortValue = `${filters.sort_field}:${filters.sort_order}`;
  const selectedRowsCount = selectedRowKeys.length;
  const folderSummaryText = selectedFolder ? `当前目录：${selectedFolder.name}` : '当前查看全部目录';
  const currentPageEnabledCount = items.filter((item) => Number(item.status || 0) === 1).length;
  const currentPageImageCount = items.filter((item) => isImageAsset(item)).length;
  const currentPageVideoCount = items.filter((item) => isVideoAsset(item)).length;
  const currentPagePdfCount = items.filter((item) => isPdfAsset(item)).length;
  const activeUploadCount = uploadTasks.filter((item) => item.status === 'uploading').length;
  const activeUploadSummary = useMemo(
    () => getResourceUploadSummary(filters.file_category || ''),
    [filters.file_category],
  );

  function buildUploadTaskKey(file) {
    return String(file?.uid || `${file?.name || 'file'}-${file?.size || 0}-${file?.lastModified || 0}`);
  }

  function upsertUploadTask(taskKey, patch) {
    setUploadTasks((current) => {
      const next = current.slice();
      const index = next.findIndex((item) => item.key === taskKey);
      const nextRecord = {
        key: taskKey,
        name: patch.name || '文件',
        percent: Number(patch.percent || 0),
        status: patch.status || 'uploading',
        note: patch.note || '',
      };

      if (index >= 0) {
        next[index] = { ...next[index], ...nextRecord };
      } else {
        next.unshift(nextRecord);
      }

      return next.slice(0, 8);
    });
  }

  function removeUploadTask(taskKey) {
    setUploadTasks((current) => current.filter((item) => item.key !== taskKey));
  }

  function getFileCategoryFromName(fileName) {
    const extension = String(fileName || '').split('.').pop()?.toLowerCase() || '';
    if (['mp4', 'webm'].includes(extension)) {
      return 'video';
    }
    if (extension === 'pdf') {
      return 'pdf';
    }
    return 'image';
  }

  useEffect(() => {
    if (activeUploadCount <= 0) {
      return undefined;
    }

    const handleBeforeUnload = (event) => {
      event.preventDefault();
      event.returnValue = '当前仍有文件上传中，离开页面可能导致上传中断。';
      return event.returnValue;
    };

    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [activeUploadCount]);

  function applyResourcePayload(data) {
    const assetsPayload = data?.assets || data;
    setItems(Array.isArray(assetsPayload?.items) ? assetsPayload.items : []);
    setPagination(assetsPayload?.pagination || { page: 1, page_size: 12, total: 0 });
    setFolderCounts(assetsPayload?.folder_counts || {});
    setSelectedRowKeys([]);
    if (Array.isArray(data?.folders)) {
      setFolderTree(data.folders);
    }
  }

  async function loadBootstrap(nextFilters = filters) {
    setListLoading(true);
    setFoldersLoading(true);

    try {
      const data = await getResourcesBootstrap(nextFilters);
      applyResourcePayload(data);
    } catch (error) {
      message.error(error.message || '加载资源数据失败。');
    } finally {
      setListLoading(false);
      setFoldersLoading(false);
    }
  }

  useEffect(() => {
    loadBootstrap(filters);
  }, [filters]);

  useEffect(() => {
    setKeywordInput(String(filters.keyword || ''));
  }, [filters.keyword]);

  async function refreshCurrentDrawer(id = currentItem?.id) {
    if (!id) {
      return;
    }

    setDetailLoading(true);
    setReferencesLoading(true);

    try {
      const [detail, referencePayload] = await Promise.all([
        getResourceDetail(id),
        getResourceReferences(id),
      ]);

      setCurrentItem(detail);
      setReferences(Array.isArray(referencePayload?.references) ? referencePayload.references : []);
      editForm.setFieldsValue({
        file_name: detail?.file_name || '',
        folder_id: Number(detail?.folder_id || 0),
        status: Number(detail?.status || 0) === 1,
        alt_text_zh: detail?.alt_text_zh || '',
        description_zh: detail?.description_zh || '',
      });
    } catch (error) {
      message.error(error.message || '加载资源详情失败。');
      setDetailOpen(false);
      setCurrentItem(null);
      setReferences([]);
    } finally {
      setDetailLoading(false);
      setReferencesLoading(false);
    }
  }

  async function openDetail(record) {
    setDetailOpen(true);
    setCurrentItem(record);
    await refreshCurrentDrawer(record.id);
  }

  async function handleToggleStatus(record, checked) {
    try {
      await updateResourceStatus(record.id, checked ? 1 : 0);
      message.success('资源状态已更新。');
      if (currentItem && Number(currentItem.id) === Number(record.id)) {
        setCurrentItem((prev) => (prev ? { ...prev, status: checked ? 1 : 0 } : prev));
        editForm.setFieldValue('status', checked);
      }
      await loadBootstrap(filters);
    } catch (error) {
      message.error(error.message || '更新资源状态失败。');
    }
  }

  async function handleDelete(record) {
    try {
      await deleteResource(record.id);
      message.success('资源已删除。');
      if (currentItem && Number(currentItem.id) === Number(record.id)) {
        setDetailOpen(false);
        setCurrentItem(null);
        setReferences([]);
      }
      await loadBootstrap(filters);
    } catch (error) {
      message.error(error.message || '删除资源失败。');
    }
  }

  async function handleSaveDetail(values) {
    if (!currentItem) {
      return;
    }

    setSaving(true);

    try {
      const nextName = String(values.file_name || '').trim();
      const nextFolderId = Number(values.folder_id || 0);
      const nextStatus = values.status ? 1 : 0;

      if (nextName && nextName !== String(currentItem.file_name || '')) {
        await renameResource(currentItem.id, nextName);
      }

      await updateResource(currentItem.id, {
        alt_text_zh: String(values.alt_text_zh || '').trim(),
        description_zh: String(values.description_zh || '').trim(),
      });

      if (nextFolderId !== Number(currentItem.folder_id || 0)) {
        await moveResources([currentItem.id], nextFolderId);
      }

      if (nextStatus !== Number(currentItem.status || 0)) {
        await updateResourceStatus(currentItem.id, nextStatus);
      }

      message.success('资源信息已保存。');
      await Promise.all([refreshCurrentDrawer(currentItem.id), loadBootstrap(filters)]);
    } catch (error) {
      message.error(error.message || '保存资源失败。');
    } finally {
      setSaving(false);
    }
  }

  function openCreateFolderModal() {
    setFolderModalMode('create');
    setFolderEditingNode(null);
    folderForm.resetFields();
    folderForm.setFieldsValue({
      parent_id: Number(filters.folder_id || 0),
      name: '',
    });
    setFolderModalOpen(true);
  }

  function openRenameFolderModal() {
    if (!selectedFolder) {
      return;
    }

    setFolderModalMode('rename');
    setFolderEditingNode(selectedFolder);
    folderForm.resetFields();
    folderForm.setFieldsValue({
      id: Number(selectedFolder.id),
      parent_id: Number(selectedFolder.parent_id || 0),
      name: selectedFolder.name,
    });
    setFolderModalOpen(true);
  }

  async function handleFolderSubmit(values) {
    setFolderSubmitting(true);

    try {
      if (folderModalMode === 'create') {
        await createResourceFolder({
          name: String(values.name || '').trim(),
          parent_id: Number(values.parent_id || 0),
        });
        message.success('目录已创建。');
      } else {
        await updateResourceFolder(Number(values.id), {
          name: String(values.name || '').trim(),
          parent_id: Number(values.parent_id || 0),
        });
        message.success('目录已更新。');
      }

      setFolderModalOpen(false);
      setFolderEditingNode(null);
      await loadBootstrap(filters);
    } catch (error) {
      message.error(error.message || '保存目录失败。');
    } finally {
      setFolderSubmitting(false);
    }
  }

  async function handleDeleteFolder() {
    if (!selectedFolder) {
      return;
    }

    try {
      await deleteResourceFolder(selectedFolder.id);
      message.success('目录已删除。');
      setFilters((current) => ({
        ...current,
        folder_id: '',
        page: 1,
      }));
      await loadBootstrap({
        ...filters,
        folder_id: '',
        page: 1,
      });
    } catch (error) {
      message.error(error.message || '删除目录失败。');
    }
  }

  function openBatchFolderModal(action) {
    if (selectedRowsCount === 0) {
      return;
    }

    setBatchAction(action);
    batchFolderForm.resetFields();
    batchFolderForm.setFieldsValue({
      target_folder_id: Number(filters.folder_id || 0),
    });
    setBatchFolderModalOpen(true);
  }

  async function handleBatchDelete() {
    if (selectedRowsCount === 0) {
      return;
    }

    setBatchSubmitting(true);
    try {
      await batchDeleteResources(selectedRowKeys);
      message.success(`已删除 ${selectedRowsCount} 个资源。`);

      if (currentItem && selectedRowKeys.includes(Number(currentItem.id))) {
        setDetailOpen(false);
        setCurrentItem(null);
        setReferences([]);
      }

      await loadBootstrap(filters);
    } catch (error) {
      message.error(error.message || '批量删除资源失败。');
    } finally {
      setBatchSubmitting(false);
    }
  }

  async function handleBatchFolderSubmit(values) {
    if (!batchAction || selectedRowsCount === 0) {
      setBatchFolderModalOpen(false);
      return;
    }

    setBatchSubmitting(true);
    try {
      const targetFolderId = Number(values.target_folder_id || 0);
      if (batchAction === 'move') {
        await moveResources(selectedRowKeys, targetFolderId);
        message.success(`已移动 ${selectedRowsCount} 个资源。`);
      } else {
        await copyResources(selectedRowKeys, targetFolderId);
        message.success(`已复制 ${selectedRowsCount} 个资源。`);
      }

      setBatchFolderModalOpen(false);
      setBatchAction('');
      await loadBootstrap(filters);
    } catch (error) {
      message.error(
        error.message || (batchAction === 'move' ? '批量移动资源失败。' : '批量复制资源失败。'),
      );
    } finally {
      setBatchSubmitting(false);
    }
  }

  function handleResetFilters() {
    setKeywordInput('');
    setFilters({
      keyword: '',
      file_category: '',
      status: '',
      folder_id: '',
      page: 1,
      page_size: 12,
      sort_field: 'updated_at',
      sort_order: 'desc',
    });
  }

  function buildUploadProps(category = '') {
    const uploadCategory = category || filters.file_category || 'all';

    return {
      ...uploadProps,
      accept: getResourceUploadAccept(uploadCategory),
      beforeUpload: (file) => {
        try {
          validateResourceUploadFile(file, uploadCategory);
          return true;
        } catch (error) {
          message.error(error.message || '文件校验失败，请重新选择。');
          return Upload.LIST_IGNORE;
        }
      },
    };
  }

  const uploadProps = {
    name: 'file',
    multiple: true,
    showUploadList: false,
    accept: getResourceUploadAccept(filters.file_category || 'all'),
    customRequest: async ({ file, onSuccess, onError, onProgress }) => {
      const taskKey = buildUploadTaskKey(file);
      const fileCategory = getFileCategoryFromName(file.name);

      upsertUploadTask(taskKey, {
        name: file.name,
        percent: 0,
        status: 'uploading',
        file_category: fileCategory,
        note: '正在上传，请勿刷新页面。',
      });

      try {
        const payload = await uploadResourceFile(file, {
          folderId: Number(filters.folder_id || 0),
          onProgress: ({ percent }) => {
            const nextPercent = Number(percent || 0);
            onProgress?.({ percent: nextPercent });
            upsertUploadTask(taskKey, {
              name: file.name,
              percent: nextPercent,
              status: 'uploading',
              file_category: fileCategory,
              note: nextPercent >= 100 ? '文件已传输，正在写入资源中心。' : '正在上传，请勿刷新页面。',
            });
          },
        });
        onSuccess?.(payload);
        upsertUploadTask(taskKey, {
          name: file.name,
          percent: 100,
          status: 'success',
          file_category: fileCategory,
          note: '上传完成，资源已入库。',
        });
        message.success(`${file.name} 上传成功。`);
        notification.success({
          message: '上传成功',
          description: `${file.name} 已上传并写入资源中心。`,
          placement: 'topRight',
          duration: 4,
        });
        await loadBootstrap(filters);
        window.setTimeout(() => removeUploadTask(taskKey), 4000);
      } catch (error) {
        onError?.(error);
        upsertUploadTask(taskKey, {
          name: file.name,
          status: 'error',
          file_category: fileCategory,
          note: error.message || '上传失败，请重试。',
        });
        message.error(error.message || `${file.name} 上传失败。`);
        notification.error({
          message: '上传失败',
          description: error.message || `${file.name} 上传失败，请重试。`,
          placement: 'topRight',
          duration: 4,
        });
        window.setTimeout(() => removeUploadTask(taskKey), 6000);
      }
    },
  };

  const columns = [
    {
      title: '预览',
      dataIndex: 'preview',
      width: 96,
      render: (_, record) => renderPreview(record),
    },
    {
      title: '文件信息',
      dataIndex: 'file_name',
      render: (_, record) => (
        <div className="resource-file-cell">
          <Space wrap size={[6, 6]} className="resource-file-heading">
            <Text
              strong
              className="resource-file-name"
              ellipsis={{ tooltip: record.file_name || record.original_name || `资源 #${record.id}` }}
            >
              {record.file_name || record.original_name || `资源 #${record.id}`}
            </Text>
            {renderAssetCategory(record)}
            {Number(record.status || 0) === 1 ? <Tag color="success">启用</Tag> : <Tag>停用</Tag>}
          </Space>
          <Text type="secondary">
            {record.original_name || '-'} / {String(record.file_ext || '-').toUpperCase()}
          </Text>
          <Text type="secondary" ellipsis={{ tooltip: record.file_path || '' }}>
            {record.file_path || '-'}
          </Text>
        </div>
      ),
    },
    {
      title: '目录',
      dataIndex: 'folder_name',
      width: 180,
      render: (_, record) => (
        <Text ellipsis={{ tooltip: findFolderName(folderMap, record) }}>
          {findFolderName(folderMap, record)}
        </Text>
      ),
    },
    {
      title: '大小',
      dataIndex: 'file_size',
      width: 110,
      render: (value) => formatFileSize(value),
    },
    {
      title: '更新时间',
      dataIndex: 'updated_at',
      width: 180,
      render: (value) => formatDate(value),
    },
    {
      title: '启用',
      dataIndex: 'status',
      width: 92,
      render: (_, record) => (
        <Switch
          size="small"
          checked={Number(record.status || 0) === 1}
          onChange={(checked) => handleToggleStatus(record, checked)}
        />
      ),
    },
    {
      title: '操作',
      key: 'actions',
      width: 180,
      render: (_, record) => (
        <Space size={4} className="resource-table-actions">
          <Button size="small" icon={<EyeOutlined />} onClick={() => openDetail(record)}>
            查看
          </Button>
          <Button size="small" icon={<EditOutlined />} onClick={() => openDetail(record)}>
            编辑
          </Button>
          <Popconfirm
            title="确认删除这个资源吗？"
            okText="删除"
            cancelText="取消"
            onConfirm={() => handleDelete(record)}
          >
            <Button size="small" danger icon={<DeleteOutlined />}>
              删除
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <>
      <PagePlaceholder
        hideHeader
        compact
        tags={[
          `本页资源 ${items.length}`,
          `启用 ${currentPageEnabledCount}`,
          `图片 ${currentPageImageCount}`,
          `视频 ${currentPageVideoCount}`,
          `PDF ${currentPagePdfCount}`,
        ]}
      >
        <Space direction="vertical" size={16} style={{ width: '100%' }} className="resource-page-layout">
          <Alert
            className="resource-upload-help"
            type="info"
            showIcon
            message={
              <div className="resource-upload-help-content">
                <Text strong>上传说明</Text>
                <Text type="secondary">
                  {filters.file_category ? activeUploadSummary : RESOURCE_UPLOAD_HINTS.join(' ')}
                </Text>
              </div>
            }
          />

          {uploadTasks.length > 0 ? (
            <Card className="page-card resource-upload-progress-card" bordered={false}>
              <Space direction="vertical" size={12} style={{ width: '100%' }}>
                <Space align="center" style={{ width: '100%', justifyContent: 'space-between' }}>
                  <Text strong>上传进度</Text>
                  <Text type="secondary">
                    {activeUploadCount > 0 ? `上传中 ${activeUploadCount} 项` : '最近上传记录'}
                  </Text>
                </Space>
                <div className="resource-upload-progress-list">
                  {uploadTasks.map((task) => (
                    <div key={task.key} className={`resource-upload-progress-item is-${task.status}`}>
                      <div className="resource-upload-progress-copy">
                        <Text
                          strong
                          ellipsis={{ tooltip: task.name }}
                          className="resource-upload-progress-name"
                        >
                          {task.name}
                        </Text>
                        <Text type="secondary">{task.note || '-'}</Text>
                      </div>
                      <div className="resource-upload-progress-bar">
                        <Progress
                          percent={task.status === 'error' ? 100 : Math.max(0, Math.min(100, Number(task.percent || 0)))}
                          size="small"
                          status={
                            task.status === 'error'
                              ? 'exception'
                              : task.status === 'success'
                                ? 'success'
                                : 'active'
                          }
                        />
                      </div>
                    </div>
                  ))}
                </div>
              </Space>
            </Card>
          ) : null}

          <div className="toolbar-surface">
            <Space direction="vertical" size={12} style={{ width: '100%' }}>
              <Space wrap size={12} style={{ width: '100%', justifyContent: 'space-between' }}>
                <Space wrap size={12}>
                  <Input.Search
                    allowClear
                    placeholder="搜索文件名、备注或说明"
                    style={{ width: 280 }}
                    value={keywordInput}
                    onChange={(event) => setKeywordInput(event.target.value)}
                    onSearch={(value) =>
                      setFilters((current) => ({
                        ...current,
                        keyword: value.trim(),
                        page: 1,
                      }))
                    }
                  />
                  <Select
                    style={{ width: 150 }}
                    options={fileCategoryOptions}
                    value={filters.file_category}
                    onChange={(value) =>
                      setFilters((current) => ({
                        ...current,
                        file_category: value,
                        page: 1,
                      }))
                    }
                  />
                  <Select
                    style={{ width: 150 }}
                    options={statusOptions}
                    value={filters.status}
                    onChange={(value) =>
                      setFilters((current) => ({
                        ...current,
                        status: value,
                        page: 1,
                      }))
                    }
                  />
                  <Select
                    style={{ width: 190 }}
                    options={sortOptions}
                    value={activeSortValue}
                    onChange={(value) => {
                      const [sortField, sortOrder] = String(value).split(':');
                      setFilters((current) => ({
                        ...current,
                        sort_field: sortField,
                        sort_order: sortOrder,
                        page: 1,
                      }));
                    }}
                  />
                </Space>

                <Space wrap className="resource-upload-entry-group">
                  <Upload {...buildUploadProps('image')}>
                    <Button icon={<UploadOutlined />}>{`上传${getResourceUploadLabel('image')}`}</Button>
                  </Upload>
                  <Upload {...buildUploadProps('video')}>
                    <Button icon={<UploadOutlined />}>{`上传${getResourceUploadLabel('video')}`}</Button>
                  </Upload>
                  <Upload {...buildUploadProps('pdf')}>
                    <Button icon={<UploadOutlined />}>{`上传${getResourceUploadLabel('pdf')}`}</Button>
                  </Upload>
                  <Button onClick={handleResetFilters}>重置筛选</Button>
                  <Button icon={<ReloadOutlined />} onClick={() => loadBootstrap(filters)}>
                    刷新列表
                  </Button>
                </Space>
              </Space>

                <Text type="secondary" className="resource-toolbar-note">
                  可按目录筛选后直接批量移动、复制或删除，底部统一进行全选和批量操作。
                </Text>
            </Space>
          </div>

          <Row gutter={16} align="stretch" className="resource-layout-row">
            <Col xs={24} lg={7} xl={7} className="resource-layout-sidebar">
              <Card
                className="page-card resource-directory-card"
                bordered={false}
                styles={{ body: { minHeight: 520 } }}
              >
                <div className="resource-directory-panel-head">
                  <div className="resource-directory-card-title">
                    <span>资源目录</span>
                    <Text type="secondary">{folderSummaryText}</Text>
                  </div>
                  <div className="resource-directory-card-actions">
                    <Space size={[6, 6]} wrap>
                      <Button size="small" icon={<FolderAddOutlined />} onClick={openCreateFolderModal}>
                        新建
                      </Button>
                      <Button
                        size="small"
                        icon={<EditOutlined />}
                        disabled={!selectedFolder}
                        onClick={openRenameFolderModal}
                      >
                        编辑
                      </Button>
                      <Popconfirm
                        title="确认删除当前目录吗？"
                        description="目录必须为空，且不能包含子目录。"
                        okText="删除"
                        cancelText="取消"
                        disabled={!selectedFolder}
                        onConfirm={handleDeleteFolder}
                      >
                        <Button size="small" danger disabled={!selectedFolder}>
                          删除
                        </Button>
                      </Popconfirm>
                    </Space>
                  </div>
                </div>
                {foldersLoading ? (
                  <Text type="secondary">目录加载中...</Text>
                ) : (
                  <div className="resource-directory-tree-shell">
                    <Tree
                      blockNode
                      showIcon
                      defaultExpandAll
                      selectedKeys={[filters.folder_id ? String(filters.folder_id) : rootFolderKey]}
                      treeData={folderTreeData}
                      onSelect={(keys) => {
                        const key = keys[0];
                        setFilters((current) => ({
                          ...current,
                          folder_id: key === rootFolderKey ? '' : String(key),
                          page: 1,
                        }));
                      }}
                    />
                  </div>
                )}

                <Divider style={{ margin: '10px 0' }} />

                <Space direction="vertical" size={10} style={{ width: '100%' }}>
                  <Text strong>目录统计</Text>
                  <Space size={[8, 8]} wrap className="resource-directory-stats">
                    {Object.keys(folderCounts).length > 0 ? (
                      Object.entries(folderCounts).map(([name, count]) => (
                        <Tag key={name}>
                          {`${name}：${count}项`}
                        </Tag>
                      ))
                    ) : (
                      <Text type="secondary">暂无目录统计数据。</Text>
                    )}
                  </Space>
                </Space>
              </Card>
            </Col>

            <Col xs={24} lg={17} xl={17} className="resource-layout-main">
              <Card
                className="page-card resource-main-card"
                title={
                  <div className="resource-main-card-title">
                    <span>{selectedFolder ? `${selectedFolder.name} 下的资源` : '全部资源'}</span>
                    <Text type="secondary">{pagination.total || 0} 项</Text>
                  </div>
                }
                bordered={false}
              >
                <div className="table-scroll-shell">
                  <Table
                    className="resource-table"
                    rowKey="id"
                    loading={listLoading}
                    columns={columns}
                    dataSource={items}
                    size="middle"
                    tableLayout="fixed"
                    scroll={{ x: 1160 }}
                    rowSelection={{
                      selectedRowKeys,
                      onChange: setSelectedRowKeys,
                    }}
                    locale={{
                      emptyText: (
                        <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="当前条件下暂无资源">
                          <Space wrap className="resource-upload-entry-group">
                            <Upload {...buildUploadProps('image')}>
                              <Button icon={<UploadOutlined />}>上传图片</Button>
                            </Upload>
                            <Upload {...buildUploadProps('video')}>
                              <Button icon={<UploadOutlined />}>上传视频</Button>
                            </Upload>
                            <Upload {...buildUploadProps('pdf')}>
                              <Button icon={<UploadOutlined />}>上传 PDF</Button>
                            </Upload>
                            <Button onClick={openCreateFolderModal}>新建目录</Button>
                          </Space>
                        </Empty>
                      ),
                    }}
                    pagination={false}
                    onRow={(record) => ({
                      onDoubleClick: () => openDetail(record),
                    })}
                  />
                </div>
                <TableSelectionFooter
                  rowKeys={items.map((item) => item.id)}
                  selectedRowKeys={selectedRowKeys}
                  onChange={setSelectedRowKeys}
                  label="全选"
                  actions={
                    <div className="resource-footer-actions">
                      <Button
                        size="small"
                        disabled={selectedRowsCount === 0}
                        loading={batchSubmitting}
                        onClick={() => openBatchFolderModal('move')}
                      >
                        批量移动
                      </Button>
                      <Button
                        size="small"
                        disabled={selectedRowsCount === 0}
                        loading={batchSubmitting}
                        onClick={() => openBatchFolderModal('copy')}
                      >
                        批量复制
                      </Button>
                      <Popconfirm
                        title="确认删除已选资源吗？"
                        okText="删除"
                        cancelText="取消"
                        disabled={selectedRowsCount === 0}
                        onConfirm={handleBatchDelete}
                      >
                        <Button size="small" danger loading={batchSubmitting} disabled={selectedRowsCount === 0}>
                          批量删除
                        </Button>
                      </Popconfirm>
                      <Button size="small" disabled={selectedRowsCount === 0} onClick={() => setSelectedRowKeys([])}>
                        清空选择
                      </Button>
                    </div>
                  }
                  pagination={
                    <Pagination
                      size="small"
                      current={pagination.page || filters.page}
                      pageSize={pagination.page_size || filters.page_size}
                      total={pagination.total || 0}
                      showSizeChanger
                      showLessItems
                      onChange={(page, pageSize) =>
                        setFilters((current) => ({
                          ...current,
                          page,
                          page_size: pageSize,
                        }))
                      }
                    />
                  }
                />
              </Card>
            </Col>
          </Row>
        </Space>
      </PagePlaceholder>

      <Drawer
        title={currentItem?.file_name || '资源详情'}
        width={760}
        open={detailOpen}
        onClose={() => {
          setDetailOpen(false);
          setCurrentItem(null);
          setReferences([]);
        }}
        destroyOnHidden
        extra={
          <Space>
            <Button
              onClick={() => {
                setDetailOpen(false);
                setCurrentItem(null);
                setReferences([]);
              }}
            >
              关闭
            </Button>
            <Button type="primary" loading={saving} onClick={() => editForm.submit()}>
              保存
            </Button>
          </Space>
        }
      >
        {currentItem ? (
          <Space direction="vertical" size={20} style={{ width: '100%' }}>
            <Alert
              type="info"
              showIcon
              message="资源编辑说明"
              description={`当前正在编辑 ${currentItem.file_name || '该资源'}，所在目录：${findFolderName(folderMap, currentItem)}。这里可以修改文件名、归档目录、启用状态，以及前台会使用到的文字信息。`}
            />

            <Card bordered={false} style={{ background: '#f8fbff' }}>
              <Space
                direction="vertical"
                size={16}
                style={{ width: '100%', alignItems: 'center', textAlign: 'center' }}
              >
                {isImageAsset(currentItem) ? (
                  <Image
                    src={resolveAssetUrl(currentItem.file_path)}
                    alt={currentItem.file_name}
                    style={{ maxHeight: 220, objectFit: 'contain' }}
                  />
                ) : isVideoAsset(currentItem) ? (
                  <video
                    controls
                    preload="metadata"
                    src={resolveAssetUrl(currentItem.file_path)}
                    poster={resolveAssetUrl(getResourcePosterPath(currentItem)) || undefined}
                    style={{ width: '100%', maxHeight: 240, borderRadius: 12 }}
                  />
                ) : isPdfAsset(currentItem) ? (
                  <Button icon={<FilePdfOutlined />} href={resolveAssetUrl(currentItem.file_path)} target="_blank">
                    查看文件
                  </Button>
                ) : (
                  <InboxOutlined style={{ fontSize: 36, color: '#6b7280' }} />
                )}

                <Space wrap>
                  <Button
                    icon={<CopyOutlined />}
                    onClick={async () => {
                      try {
                        message.success(await copyText(currentItem.id, '资源 ID 已复制。'));
                      } catch (error) {
                        message.error(error.message || '复制资源 ID 失败。');
                      }
                    }}
                  >
                    复制 ID
                  </Button>
                  <Button
                    icon={<CopyOutlined />}
                    onClick={async () => {
                      try {
                        message.success(
                          await copyText(resolveAssetUrl(currentItem.file_path), '资源链接已复制。'),
                        );
                      } catch (error) {
                        message.error(error.message || '复制资源链接失败。');
                      }
                    }}
                  >
                    复制链接
                  </Button>
                  <Button
                    icon={<DownloadOutlined />}
                    onClick={() => {
                      try {
                        downloadAsset(currentItem);
                      } catch (error) {
                        message.error(error.message || '下载文件失败。');
                      }
                    }}
                  >
                    下载文件
                  </Button>
                </Space>
              </Space>
            </Card>

            <Descriptions bordered size="small" column={2}>
              <Descriptions.Item label="ID">{currentItem.id}</Descriptions.Item>
              <Descriptions.Item label="目录">{findFolderName(folderMap, currentItem)}</Descriptions.Item>
              <Descriptions.Item label="类型">
                {String(currentItem.file_ext || '').toUpperCase()} / {currentItem.mime_type || '-'}
              </Descriptions.Item>
              <Descriptions.Item label="大小">{formatFileSize(currentItem.file_size)}</Descriptions.Item>
              <Descriptions.Item label="尺寸">
                {currentItem.width && currentItem.height ? `${currentItem.width} x ${currentItem.height}` : '-'}
              </Descriptions.Item>
              <Descriptions.Item label="更新时间">{formatDate(currentItem.updated_at)}</Descriptions.Item>
            </Descriptions>

            <Form form={editForm} layout="vertical" disabled={detailLoading || saving} onFinish={handleSaveDetail}>
              <Form.Item
                name="file_name"
                label="文件名"
                extra="用于后台资源中心展示和检索识别。"
                rules={[{ required: true, message: '请输入文件名。' }]}
              >
                <Input placeholder="请输入文件名" />
              </Form.Item>

              <div className="form-two-column">
                <Form.Item
                  name="folder_id"
                  label="所属目录"
                  extra="可将当前资源移动到其他已维护的目录。"
                >
                  <Select options={folderSelectOptions} />
                </Form.Item>
                <Form.Item
                  name="status"
                  label="启用状态"
                  extra="停用后资源不会删除，但前台和选择器应避免继续使用。"
                  valuePropName="checked"
                >
                  <Switch checkedChildren="启用" unCheckedChildren="停用" />
                </Form.Item>
              </div>

              <Form.Item
                name="alt_text_zh"
                label="替代文本"
                extra="建议填写图片替代说明，用于 SEO 和无障碍描述。"
              >
                <Input placeholder="请输入替代文本" />
              </Form.Item>

              <Form.Item
                name="description_zh"
                label="内部备注"
                extra="可记录资源用途、投放位置或交接说明。"
              >
                <TextArea rows={4} placeholder="请输入备注" />
              </Form.Item>
            </Form>

            <Card
              title="引用情况"
              bordered={false}
              extra={referencesLoading ? <Text type="secondary">加载中...</Text> : `${references.length}`}
            >
              {references.length > 0 ? (
                <List
                  size="small"
                  dataSource={references}
                  renderItem={(item) => (
                    <List.Item>
                      <Space direction="vertical" size={2}>
                        <Text>{item.title || `${item.entity_type} #${item.entity_id}`}</Text>
                        <Text type="secondary">
                          {item.entity_type} / {item.field}
                        </Text>
                      </Space>
                    </List.Item>
                  )}
                />
              ) : (
                <Text type="secondary">当前没有下游引用记录。</Text>
              )}
            </Card>

            <Paragraph type="secondary" style={{ marginBottom: 0 }}>
              说明：文字信息通过 <Text code>alt_text_zh</Text> 和 <Text code>description_zh</Text> 保存；
              目录切换使用批量移动接口处理，因此单个资源和批量整理走的是同一套业务链路。
            </Paragraph>
          </Space>
        ) : null}
      </Drawer>

      <Modal
        title={folderModalMode === 'create' ? '新建目录' : '编辑目录'}
        open={folderModalOpen}
        onCancel={() => {
          setFolderModalOpen(false);
          setFolderEditingNode(null);
        }}
        onOk={() => folderForm.submit()}
        confirmLoading={folderSubmitting}
        destroyOnHidden
      >
        <Form form={folderForm} layout="vertical" onFinish={handleFolderSubmit}>
          <Form.Item name="id" hidden>
            <Input />
          </Form.Item>

          <Form.Item name="parent_id" label="父级目录">
            <Select options={folderParentOptions} />
          </Form.Item>

          <Form.Item
            name="name"
            label="目录名称"
            rules={[{ required: true, message: '请输入目录名称。' }]}
          >
            <Input placeholder="请输入目录名称" />
          </Form.Item>
        </Form>
      </Modal>

      <Modal
        title={batchAction === 'copy' ? '批量复制到目录' : '批量移动到目录'}
        open={batchFolderModalOpen}
        onCancel={() => {
          setBatchFolderModalOpen(false);
          setBatchAction('');
        }}
        onOk={() => batchFolderForm.submit()}
        confirmLoading={batchSubmitting}
        destroyOnHidden
      >
        <Form form={batchFolderForm} layout="vertical" onFinish={handleBatchFolderSubmit}>
          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 16 }}
            message={`已选择 ${selectedRowsCount} 个资源`}
          />
          <Form.Item
            name="target_folder_id"
            label="目标目录"
            rules={[{ required: true, message: '请选择目标目录。' }]}
          >
            <Select options={folderSelectOptions} />
          </Form.Item>
        </Form>
      </Modal>
    </>
  );
}
