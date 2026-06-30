import { useEffect, useMemo, useState } from 'react';
import {
  Button,
  Drawer,
  Form,
  Input,
  InputNumber,
  message,
  Modal,
  Popconfirm,
  Select,
  Space,
  Switch,
  Table,
} from 'antd';
import { createCategory, deleteCategory, getCategoryTree, updateCategory } from '@/api/categories';

function flattenCategoryTree(nodes = [], level = 0, result = []) {
  (Array.isArray(nodes) ? nodes : []).forEach((node) => {
    result.push({
      ...(node || {}),
      id: Number(node?.id),
      parent_id: Number(node?.parent_id || 0),
      sort: Number(node?.sort || 0),
      is_enabled: Number(node?.is_enabled || 0) === 1,
      level: Number(level || 0),
    });

    flattenCategoryTree(node?.children || [], level + 1, result);
  });

  return result;
}

function normalizeCategoryForSave(values) {
  return {
    parent_id: Number(values.parent_id || 0),
    name_zh: String(values.name_zh || '').trim(),
    slug: String(values.slug || '').trim(),
    sort: Number(values.sort || 0),
    is_enabled: values.is_enabled ? 1 : 0,
  };
}

function buildNamePrefix(level) {
  if (!level || level <= 0) {
    return '';
  }

  return `${'  '.repeat(level)}- `;
}

export default function CategoryQuickManager({
  open,
  onClose,
  onSaved,
  entityType,
  title = '分类管理',
}) {
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [treeData, setTreeData] = useState([]);
  const [rows, setRows] = useState([]);
  const [originRows, setOriginRows] = useState([]);
  const [deleteIds, setDeleteIds] = useState(new Set());
  const [newItems, setNewItems] = useState([]);
  const [editorOpen, setEditorOpen] = useState(false);
  const [isNewRecord, setIsNewRecord] = useState(false);
  const [editingRecord, setEditingRecord] = useState(null);
  const [form] = Form.useForm();

  const flatRows = useMemo(() => {
    return flattenCategoryTree(treeData).filter((item) => !deleteIds.has(item.id));
  }, [treeData, deleteIds]);

  const changedRows = useMemo(() => {
    return flatRows
      .filter((item) => !item.__new)
      .filter((item) => {
        const origin = originRows.find((entry) => Number(entry.id) === Number(item.id));
        if (!origin) {
          return false;
        }

        return (
          item.name_zh !== origin.name_zh ||
          item.slug !== origin.slug ||
          item.sort !== origin.sort ||
          item.parent_id !== origin.parent_id ||
          item.is_enabled !== origin.is_enabled
        );
      });
  }, [flatRows, originRows]);

  const hasPending = useMemo(() => {
    return deleteIds.size > 0 || newItems.length > 0 || changedRows.length > 0;
  }, [deleteIds, newItems, changedRows]);

  const parentOptions = useMemo(() => {
    return flatRows
      .filter((item) => !item.__new)
      .map((item) => ({
        label: `${buildNamePrefix(item.level)}${item.name_zh || ''}`,
        value: Number(item.id),
      }));
  }, [flatRows]);

  async function refreshTree() {
    setLoading(true);
    try {
      const data = await getCategoryTree(entityType);
      const normalized = Array.isArray(data) ? data : [];
      setTreeData(normalized);
      const flat = flattenCategoryTree(normalized);
      setRows(flat);
      setOriginRows(flat.map((item) => ({ ...item })));
      setDeleteIds(new Set());
      setNewItems([]);
    } catch (error) {
      message.error(error.message || '加载分类列表失败');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (!open) {
      return;
    }

    refreshTree();
  }, [open, entityType]);

  function openCreate() {
    setIsNewRecord(true);
    setEditingRecord({ __new: true });
    form.resetFields();
    form.setFieldsValue({
      parent_id: 0,
      name_zh: '',
      slug: '',
      sort: 100,
      is_enabled: true,
    });
    setEditorOpen(true);
  }

  function openEdit(record) {
    setIsNewRecord(false);
    setEditingRecord(record);
    form.resetFields();
    form.setFieldsValue({
      parent_id: Number(record.parent_id || 0),
      name_zh: record.name_zh || '',
      slug: record.slug || '',
      sort: Number(record.sort || 0),
      is_enabled: Boolean(record.is_enabled),
    });
    setEditorOpen(true);
  }

  function closeEditor() {
    setEditorOpen(false);
    setEditingRecord(null);
    setIsNewRecord(false);
  }

  function syncRow(id, patch) {
    setRows((current) => current.map((item) => (item.id === id ? { ...item, ...patch } : item)));
  }

  function removeRecord(record) {
    if (record.__new) {
      setRows((current) => current.filter((item) => item.__new !== true || item.id !== record.id));
      setNewItems((current) => current.filter((item) => item.id !== record.id));
      return;
    }

    setDeleteIds((current) => {
      const next = new Set(current);
      next.add(record.id);
      return next;
    });
  }

  async function handleEditSubmit(values) {
    const payload = normalizeCategoryForSave(values);
    const nextId = editingRecord?.id;

    if (!payload.name_zh) {
      message.error('分类名称不能为空');
      return;
    }

    if (isNewRecord) {
      setSaving(true);
      try {
        await createCategory(entityType, payload);
        message.success('分类已创建');
        await refreshTree();
        onSaved?.();
        setEditorOpen(false);
        setEditingRecord(null);
        setIsNewRecord(false);
      } catch (error) {
        message.error(error.message || '创建分类失败');
        return;
      } finally {
        setSaving(false);
      }
      return;
    }

    syncRow(nextId, { ...payload });
    setEditorOpen(false);
    setEditingRecord(null);
  }

  function calculateInsertLevel(parentId) {
    const parent = rows.find((item) => item.id === parentId);
    return parent ? Number(parent.level || 0) + 1 : 0;
  }

  async function handleSave() {
    setSaving(true);
    try {
      if (deleteIds.size > 0) {
        await Promise.all(Array.from(deleteIds).map((id) => deleteCategory(entityType, Number(id))));
      }

      const normalizedNew = rows
        .filter((item) => item.__new)
        .map((item) => ({
          ...item,
          level: calculateInsertLevel(item.parent_id),
        }));

      if (normalizedNew.length > 0) {
        await Promise.all(normalizedNew.map((item) => createCategory(entityType, normalizeCategoryForSave(item))));
      }

      if (changedRows.length > 0) {
        await Promise.all(
          changedRows.map((item) => updateCategory(entityType, Number(item.id), normalizeCategoryForSave(item))),
        );
      }

      message.success('分类已保存');
      await refreshTree();
      onSaved?.();
      onClose?.();
    } catch (error) {
      message.error(error.message || '保存分类失败');
    } finally {
      setSaving(false);
    }
  }

  function handleCancel() {
    onClose?.();
  }

  const columns = [
    {
      title: '名称',
      dataIndex: 'name_zh',
      key: 'name',
      render: (value, record) => `${buildNamePrefix(record.level)}${value || '-'}`,
    },
    {
      title: '排序',
      dataIndex: 'sort',
      width: 120,
      render: (value, record) => (
        <InputNumber
          min={0}
          precision={0}
          size="small"
          value={value}
          onChange={(nextValue) => {
            syncRow(record.id, { sort: Number(nextValue || 0) });
          }}
        />
      ),
    },
    {
      title: '启用',
      dataIndex: 'is_enabled',
      width: 90,
      render: (value, record) => (
        <Switch
          size="small"
          checked={Boolean(value)}
          onChange={(checked) => syncRow(record.id, { is_enabled: checked })}
        />
      ),
    },
    {
      title: '操作',
      width: 140,
      render: (_, record) => (
        <Space>
          <Button size="small" onClick={() => openEdit(record)}>
            编辑
          </Button>
          <Popconfirm
            title="确认删除该分类吗？"
            onConfirm={() => removeRecord(record)}
            okText="删除"
            cancelText="取消"
          >
            <Button size="small" danger>
              删除
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <Drawer
      title={title}
      open={open}
      width={780}
      onClose={handleCancel}
      destroyOnHidden
      extra={
        <Space>
          <Button onClick={handleCancel}>取消</Button>
          <Button type="primary" loading={saving} disabled={!hasPending} onClick={handleSave}>
            保存
          </Button>
        </Space>
      }
    >
      <Space direction="vertical" size={12} style={{ width: '100%' }}>
        <Space style={{ justifyContent: 'space-between', width: '100%' }}>
          <Button type="primary" onClick={openCreate}>
            新增分类
          </Button>
          <Button onClick={refreshTree} loading={loading}>
            刷新
          </Button>
        </Space>

        <Table
          rowKey="id"
          size="small"
          loading={loading}
          columns={columns}
          dataSource={flatRows}
          pagination={false}
        />
      </Space>

      <Modal
        title={isNewRecord ? '新增分类' : '编辑分类'}
        open={editorOpen}
        onCancel={closeEditor}
        onOk={() => form.submit()}
        okText="保存"
        cancelText="取消"
      >
        <Form form={form} layout="vertical" onFinish={handleEditSubmit}>
          <Form.Item name="parent_id" label="上级分类">
            <Select options={[{ label: '顶级分类', value: 0 }, ...parentOptions]} />
          </Form.Item>
          <Form.Item
            name="name_zh"
            label="分类名称"
            rules={[{ required: true, message: '请输入分类名称。' }]}
          >
            <Input />
          </Form.Item>
          <Form.Item name="slug" label="分类别名">
            <Input />
          </Form.Item>
          <Form.Item name="sort" label="排序">
            <InputNumber style={{ width: '100%' }} min={0} precision={0} />
          </Form.Item>
          <Form.Item name="is_enabled" label="启用" valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </Drawer>
  );
}
