import { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Button,
  Card,
  Empty,
  Image,
  Input,
  List,
  Modal,
  Pagination,
  Progress,
  Select,
  Space,
  Tag,
  Tree,
  Typography,
  Upload,
  message,
  notification,
} from 'antd';
import {
  FileImageOutlined,
  FilePdfOutlined,
  FolderOpenOutlined,
  PlayCircleOutlined,
  ReloadOutlined,
  UploadOutlined,
} from '@ant-design/icons';
import {
  RESOURCE_UPLOAD_HINTS,
  getResourceDetail,
  getResourcePickerBootstrap,
  getResourceUploadAccept,
  getResourceUploadLabel,
  getResourceUploadSummary,
  uploadResourceFile,
  validateResourceUploadFile,
} from '@/api/resources';
import {
  getAssetCategory,
  getAssetDisplayName,
  isImageAsset,
  isPdfAsset,
  isVideoAsset,
  resolveAssetUrl,
} from '@/utils/media';
import { getResourcePosterPath, getResourcePreviewPath } from '@/utils/resourcePreview';

const { Paragraph, Text } = Typography;

const ROOT_FOLDER_KEY = 'root';

const SORT_OPTIONS = [
  { label: '更新时间：最新', value: 'updated_at:desc' },
  { label: '更新时间：最早', value: 'updated_at:asc' },
  { label: '创建时间：最新', value: 'created_at:desc' },
  { label: '创建时间：最早', value: 'created_at:asc' },
];

const FILE_CATEGORY_OPTIONS = [
  { label: '全部类型', value: '' },
  { label: '图片', value: 'image' },
  { label: '视频', value: 'video' },
  { label: 'PDF', value: 'pdf' },
];

function buildFolderTreeNode(node) {
  return {
    key: String(node.id),
    title: (
      <span className="resource-tree-title">
        <span className="resource-tree-title-name">{node.name}</span>
        <Text type="secondary" className="resource-tree-title-count">
          {Number(node.asset_count || 0)}
        </Text>
      </span>
    ),
    icon: <FolderOpenOutlined />,
    children: (node.children || []).map((child) => buildFolderTreeNode(child)),
  };
}

function buildFolderTreeData(nodes) {
  return [
    {
      key: ROOT_FOLDER_KEY,
      title: (
        <span className="resource-tree-title">
          <span className="resource-tree-title-name">全部文件</span>
        </span>
      ),
      icon: <FolderOpenOutlined />,
      children: (nodes || []).map((node) => buildFolderTreeNode(node)),
    },
  ];
}

function findFolderLabel(nodes, targetId) {
  const folderId = Number(targetId || 0);
  if (folderId <= 0) {
    return '全部文件';
  }

  const stack = [...(nodes || [])];
  while (stack.length > 0) {
    const current = stack.shift();
    if (Number(current?.id || 0) === folderId) {
      return current.name || `目录 #${folderId}`;
    }
    if (Array.isArray(current?.children) && current.children.length > 0) {
      stack.push(...current.children);
    }
  }

  return `目录 #${folderId}`;
}

function renderAssetPreview(asset, large = false) {
  const previewUrl = resolveAssetUrl(getResourcePreviewPath(asset));
  const width = large ? '100%' : 120;
  const height = large ? 220 : 120;

  if (isImageAsset(asset) && previewUrl) {
    return (
      <Image
        src={previewUrl}
        alt={getAssetDisplayName(asset)}
        width={width}
        height={height}
        style={{ objectFit: 'cover', borderRadius: 14 }}
        preview={large}
      />
    );
  }

  if (isVideoAsset(asset)) {
    const videoUrl = resolveAssetUrl(asset?.file_path || '');

    return large ? (
      <video
        controls
        preload="metadata"
        src={videoUrl}
        poster={resolveAssetUrl(getResourcePosterPath(asset)) || undefined}
        style={{ width: '100%', height, objectFit: 'cover', borderRadius: 14 }}
      />
    ) : (
      <div
        style={{
          width,
          height,
          overflow: 'hidden',
          borderRadius: 14,
          border: '1px solid rgba(15, 23, 42, 0.08)',
          background: '#0f172a',
          position: 'relative',
        }}
      >
        {getResourcePosterPath(asset) ? (
          <img
            src={resolveAssetUrl(getResourcePosterPath(asset))}
            alt={getAssetDisplayName(asset)}
            style={{ width, height, objectFit: 'cover', display: 'block' }}
          />
        ) : (
          <video
            src={videoUrl}
            width={width}
            height={height}
            muted
            playsInline
            preload="metadata"
            style={{ objectFit: 'cover', width, height, display: 'block' }}
          />
        )}
        <div
          style={{
            position: 'absolute',
            inset: 0,
            display: 'grid',
            placeItems: 'center',
            background: 'linear-gradient(180deg, rgba(15,23,42,0.04), rgba(15,23,42,0.36))',
          }}
        >
          <PlayCircleOutlined style={{ fontSize: 28, color: '#ffffff' }} />
        </div>
      </div>
    );
  }

  if (isPdfAsset(asset)) {
    return (
      <div className="media-picker-fallback media-picker-fallback-pdf">
        <FilePdfOutlined style={{ fontSize: 28, color: '#d97706' }} />
      </div>
    );
  }

  return (
    <div className="media-picker-fallback">
      <FileImageOutlined style={{ fontSize: 28, color: '#64748b' }} />
    </div>
  );
}

export default function MediaPickerModal({
  open,
  title = '选择资源',
  assetType = 'image',
  selectedAssetId = null,
  onCancel,
  onSelect,
}) {
  const [filters, setFilters] = useState({
    keyword: '',
    file_category: assetType === 'all' ? '' : assetType,
    folder_id: '',
    page: 1,
    page_size: 12,
    status: 1,
    sort_field: 'updated_at',
    sort_order: 'desc',
  });
  const [searchInput, setSearchInput] = useState('');
  const [items, setItems] = useState([]);
  const [pagination, setPagination] = useState({ page: 1, page_size: 12, total: 0 });
  const [loading, setLoading] = useState(false);
  const [foldersLoading, setFoldersLoading] = useState(false);
  const [selectedAsset, setSelectedAsset] = useState(null);
  const [confirming, setConfirming] = useState(false);
  const [folderTree, setFolderTree] = useState([]);
  const [uploadTasks, setUploadTasks] = useState([]);

  const activeCategory = assetType === 'all' ? filters.file_category || 'all' : assetType;
  const activeSortValue = `${filters.sort_field}:${filters.sort_order}`;
  const folderTreeData = useMemo(() => buildFolderTreeData(folderTree), [folderTree]);
  const activeFolderLabel = useMemo(
    () => findFolderLabel(folderTree, filters.folder_id),
    [filters.folder_id, folderTree],
  );
  const activeUploadCount = uploadTasks.filter((item) => item.status === 'uploading').length;
  const activeUploadSummary = useMemo(
    () => getResourceUploadSummary(activeCategory === 'all' ? '' : activeCategory),
    [activeCategory],
  );
  const selectedTagColor = useMemo(() => {
    if (!selectedAsset) {
      return 'default';
    }

    return {
      image: 'blue',
      video: 'purple',
      pdf: 'gold',
      file: 'default',
    }[getAssetCategory(selectedAsset)];
  }, [selectedAsset]);

  function buildUploadTaskKey(file) {
    return String(
      file?.uid || `${file?.name || 'file'}-${file?.size || 0}-${file?.lastModified || 0}`,
    );
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

      return next.slice(0, 6);
    });
  }

  function removeUploadTask(taskKey) {
    setUploadTasks((current) => current.filter((item) => item.key !== taskKey));
  }

  function applyBootstrapPayload(data, nextPage = filters.page, nextPageSize = filters.page_size) {
    const assetsPayload = data?.assets || {};
    const nextItems = Array.isArray(assetsPayload?.items) ? assetsPayload.items : [];
    setFolderTree(Array.isArray(data?.folders) ? data.folders : []);
    setItems(nextItems);
    setPagination(
      assetsPayload?.pagination || {
        page: nextPage,
        page_size: nextPageSize,
        total: nextItems.length,
      },
    );
    setSelectedAsset((current) => {
      const preferredId = Number(selectedAssetId || current?.id || 0);
      if (preferredId > 0) {
        return nextItems.find((item) => Number(item.id) === preferredId) || current;
      }
      return current;
    });
  }

  async function loadBootstrap(nextFilters = filters) {
    setFoldersLoading(true);
    setLoading(true);

    try {
      const data = await getResourcePickerBootstrap({
        ...nextFilters,
        file_category: assetType === 'all' ? nextFilters.file_category : assetType,
      });
      applyBootstrapPayload(data, nextFilters.page, nextFilters.page_size);
    } catch (error) {
      message.error(error.message || '加载资源失败，请稍后重试。');
    } finally {
      setLoading(false);
      setFoldersLoading(false);
    }
  }

  useEffect(() => {
    if (!open) {
      return;
    }

    setFilters((current) => ({
      ...current,
      file_category: assetType === 'all' ? current.file_category : assetType,
      page: 1,
    }));
  }, [assetType, open]);

  useEffect(() => {
    if (!open) {
      return;
    }

    loadBootstrap(filters);
  }, [assetType, filters, open]);

  useEffect(() => {
    if (!open) {
      return;
    }

    setSearchInput(String(filters.keyword || ''));
  }, [filters.keyword, open]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const assetId = Number(selectedAssetId || 0);
    if (!assetId) {
      return;
    }

    let disposed = false;
    getResourceDetail(assetId)
      .then((asset) => {
        if (!disposed) {
          setSelectedAsset(asset);
        }
      })
      .catch(() => {
        if (!disposed) {
          setSelectedAsset(null);
        }
      });

    return () => {
      disposed = true;
    };
  }, [open, selectedAssetId]);

  useEffect(() => {
    if (activeUploadCount <= 0) {
      return undefined;
    }

    const handleBeforeUnload = (event) => {
      event.preventDefault();
      event.returnValue = '当前仍有资源上传中，离开页面可能导致上传中断。';
      return event.returnValue;
    };

    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => window.removeEventListener('beforeunload', handleBeforeUnload);
  }, [activeUploadCount]);

  async function handleConfirm() {
    if (!selectedAsset) {
      message.warning('请先选择要使用的资源。');
      return;
    }

    setConfirming(true);
    try {
      await onSelect?.(selectedAsset);
    } finally {
      setConfirming(false);
    }
  }

  function handleCancel() {
    if (activeUploadCount > 0) {
      message.warning('当前仍有文件上传中，请等待上传完成后再关闭。');
      return;
    }

    onCancel?.();
  }

  function buildUploadProps(category = '') {
    const uploadCategory = category || activeCategory;

    return {
      name: 'file',
      multiple: category ? true : assetType === 'all',
      showUploadList: false,
      accept: getResourceUploadAccept(uploadCategory),
      beforeUpload: (file) => {
        try {
          validateResourceUploadFile(file, uploadCategory);
          return true;
        } catch (error) {
          message.error(error.message || '上传文件校验失败，请重新选择。');
          return Upload.LIST_IGNORE;
        }
      },
      customRequest: async ({ file, onError, onProgress, onSuccess }) => {
        const taskKey = buildUploadTaskKey(file);
        upsertUploadTask(taskKey, {
          name: file.name,
          percent: 0,
          status: 'uploading',
          note: '等待上传',
        });

        try {
          const uploaded = await uploadResourceFile(file, {
            folderId: Number(filters.folder_id || 0),
            onProgress: ({ percent }) => {
              const nextPercent = Number(percent || 0);
              onProgress?.({ percent: nextPercent });
              upsertUploadTask(taskKey, {
                name: file.name,
                percent: nextPercent,
                status: 'uploading',
                note: nextPercent >= 100 ? '处理中' : '上传中',
              });
            },
          });

          let detail = uploaded;
          if (uploaded?.id && Number(uploaded.id) > 0) {
            try {
              detail = await getResourceDetail(uploaded.id);
            } catch {
              detail = uploaded;
            }
          }

          setSelectedAsset(detail || null);
          const nextFilters = {
            ...filters,
            page: 1,
          };
          setFilters(nextFilters);
          await loadBootstrap(nextFilters);
          upsertUploadTask(taskKey, {
            name: file.name,
            percent: 100,
            status: 'success',
            note: '上传完成',
          });
          message.success(`${file.name} 上传成功`);
          notification.success({
            message: '上传成功',
            description: `${file.name} 已进入当前资源库目录。`,
            placement: 'topRight',
            duration: 4,
          });
          onSuccess?.({}, file);
          window.setTimeout(() => removeUploadTask(taskKey), 5000);
        } catch (error) {
          upsertUploadTask(taskKey, {
            name: file.name,
            status: 'error',
            note: error.message || '上传失败',
          });
          message.error(error.message || `${file.name} 上传失败`);
          notification.error({
            message: '上传失败',
            description: error.message || `${file.name} 上传失败，请稍后重试。`,
            placement: 'topRight',
            duration: 6,
          });
          onError?.(error);
          window.setTimeout(() => removeUploadTask(taskKey), 8000);
        }
      },
    };
  }

  function renderUploadButtons() {
    if (assetType === 'all') {
      return (
        <Space wrap size={8} className="resource-upload-entry-group">
          <Upload {...buildUploadProps('image')}>
            <Button icon={<UploadOutlined />}>上传图片</Button>
          </Upload>
          <Upload {...buildUploadProps('video')}>
            <Button icon={<UploadOutlined />}>上传视频</Button>
          </Upload>
          <Upload {...buildUploadProps('pdf')}>
            <Button icon={<UploadOutlined />}>上传 PDF</Button>
          </Upload>
        </Space>
      );
    }

    return (
      <Upload {...buildUploadProps(assetType)}>
        <Button icon={<UploadOutlined />}>{`上传${getResourceUploadLabel(assetType)}`}</Button>
      </Upload>
    );
  }

  function renderEmptyDescription() {
    if (activeCategory === 'video') {
      return '暂无可用视频资源';
    }
    if (activeCategory === 'pdf') {
      return '暂无可用 PDF 资源';
    }
    if (activeCategory === 'image') {
      return '暂无可用图片资源';
    }
    return '暂无可用资源';
  }

  return (
    <Modal
      title={title}
      width={1200}
      open={open}
      onCancel={handleCancel}
      onOk={handleConfirm}
      okText="使用当前资源"
      cancelText="取消"
      closable={activeUploadCount === 0}
      keyboard={activeUploadCount === 0}
      maskClosable={activeUploadCount === 0}
      okButtonProps={{ loading: confirming, disabled: activeUploadCount > 0 || !selectedAsset }}
      cancelButtonProps={{ disabled: activeUploadCount > 0 }}
      destroyOnHidden
    >
      <Space direction="vertical" size={16} style={{ width: '100%' }}>
        {uploadTasks.length > 0 ? (
          <Alert
            type={activeUploadCount > 0 ? 'info' : 'success'}
            showIcon
            message={activeUploadCount > 0 ? `正在上传 ${activeUploadCount} 个文件` : '上传任务已完成'}
            description={
              <div className="resource-upload-progress-list resource-upload-progress-list-compact">
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
                        percent={
                          task.status === 'error'
                            ? 100
                            : Math.max(0, Math.min(100, Number(task.percent || 0)))
                        }
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
            }
          />
        ) : (
          <Alert
            className="resource-upload-help"
            type="info"
            showIcon
            message={
              <div className="resource-upload-help-content">
                <Text strong>上传说明</Text>
                <Text type="secondary">
                  {activeCategory === 'all' ? RESOURCE_UPLOAD_HINTS.join(' ') : activeUploadSummary}
                </Text>
              </div>
            }
          />
        )}

        <div className="toolbar-surface">
          <Space wrap size={12} style={{ width: '100%', justifyContent: 'space-between' }}>
            <Space wrap size={12}>
              <Input.Search
                allowClear
                placeholder="搜索文件名、原始名称或路径"
                style={{ width: 260 }}
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
                onSearch={(value) =>
                  setFilters((current) => ({
                    ...current,
                    keyword: value.trim(),
                    page: 1,
                  }))
                }
              />

              {assetType === 'all' ? (
                <Select
                  style={{ width: 150 }}
                  options={FILE_CATEGORY_OPTIONS}
                  value={filters.file_category}
                  onChange={(value) =>
                    setFilters((current) => ({
                      ...current,
                      file_category: value,
                      page: 1,
                    }))
                  }
                />
              ) : (
                <Tag color="blue" style={{ marginInlineEnd: 0 }}>
                  {getResourceUploadLabel(assetType)}
                </Tag>
              )}

              <Select
                style={{ width: 180 }}
                options={SORT_OPTIONS}
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

            <Space wrap size={8}>
              <Tag bordered={false} color="default" style={{ marginInlineEnd: 0 }}>
                {`当前目录：${activeFolderLabel}`}
              </Tag>
              {renderUploadButtons()}
            </Space>
          </Space>
        </div>

        <div className="media-picker-layout media-picker-layout-foldered">
          <Card
            className="media-picker-folder-card"
            bordered={false}
            title="资源目录"
            extra={
              <Button
                type="text"
                size="small"
                icon={<ReloadOutlined />}
                loading={foldersLoading}
                onClick={() => loadBootstrap(filters)}
              />
            }
          >
            <div className="media-picker-folder-panel resource-directory-tree-shell">
              <Tree
                showIcon
                blockNode
                selectedKeys={[filters.folder_id ? String(filters.folder_id) : ROOT_FOLDER_KEY]}
                treeData={folderTreeData}
                onSelect={(keys) => {
                  const key = keys?.[0] || ROOT_FOLDER_KEY;
                  setFilters((current) => ({
                    ...current,
                    folder_id: key === ROOT_FOLDER_KEY ? '' : String(key),
                    page: 1,
                  }));
                }}
              />
            </div>
          </Card>

          <div className="media-picker-gallery">
            <List
              loading={loading}
              grid={{ gutter: 16, column: 3 }}
              dataSource={items}
              locale={{
                emptyText: (
                  <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={renderEmptyDescription()}>
                    <div style={{ marginTop: 12 }}>{renderUploadButtons()}</div>
                  </Empty>
                ),
              }}
              renderItem={(item) => {
                const isSelected = Number(selectedAsset?.id || 0) === Number(item.id);

                return (
                  <List.Item>
                    <Card
                      hoverable
                      className={`media-picker-card${isSelected ? ' is-selected' : ''}`}
                      onClick={() => setSelectedAsset(item)}
                    >
                      <div className="media-picker-thumb">{renderAssetPreview(item)}</div>
                      <Space direction="vertical" size={6} style={{ width: '100%' }}>
                        <Text strong ellipsis={{ tooltip: getAssetDisplayName(item) }}>
                          {getAssetDisplayName(item)}
                        </Text>
                        <Space size={[8, 8]} wrap>
                          <Tag color="blue">#{item.id}</Tag>
                          <Tag>{String(item.file_ext || '-').toUpperCase()}</Tag>
                        </Space>
                        <Text type="secondary" ellipsis={{ tooltip: item.file_path || '' }}>
                          {item.file_path || '-'}
                        </Text>
                      </Space>
                    </Card>
                  </List.Item>
                );
              }}
            />

            <div className="media-picker-pagination">
              <Pagination
                current={pagination.page || filters.page}
                pageSize={pagination.page_size || filters.page_size}
                total={pagination.total || 0}
                showSizeChanger
                onChange={(page, pageSize) =>
                  setFilters((current) => ({
                    ...current,
                    page,
                    page_size: pageSize,
                  }))
                }
              />
            </div>
          </div>

          <Card className="media-picker-preview-card" bordered={false}>
            {selectedAsset ? (
              <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <div className="media-picker-preview-stage">
                  {renderAssetPreview(selectedAsset, true)}
                </div>
                <Space size={[8, 8]} wrap>
                  <Tag color={selectedTagColor}>{getAssetCategory(selectedAsset).toUpperCase()}</Tag>
                  <Tag>#{selectedAsset.id}</Tag>
                </Space>
                <div>
                  <Text strong>{getAssetDisplayName(selectedAsset)}</Text>
                  <Paragraph className="page-description" style={{ margin: '8px 0 0' }}>
                    {selectedAsset.file_path || '接口未返回文件路径。'}
                  </Paragraph>
                </div>
              </Space>
            ) : (
              <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="请选择左侧资源进行预览" />
            )}
          </Card>
        </div>
      </Space>
    </Modal>
  );
}
