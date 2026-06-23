import { useEffect, useMemo, useState } from 'react';
import { App, Button, Form, Input, InputNumber, Modal, Popconfirm, Select, Space, Switch, Table, Tag, Typography } from 'antd';
import PagePlaceholder from '@/components/PagePlaceholder';
import { createCategory, deleteCategory, getCategoryTree, updateCategory } from '@/api/categories';

const { Text } = Typography;

const entityOptions = [
  { label: '产品分类', value: 'product' },
  { label: '解决方案分类', value: 'solution' },
  { label: '新闻分类', value: 'news' },
  { label: '案例分类', value: 'case' },
];

function flattenTree(nodes = [], level = 0, bucket = []) {
  (Array.isArray(nodes) ? nodes : []).forEach((node) => {
    bucket.push({
      ...node,
      level,
    });
    flattenTree(node.children || [], level + 1, bucket);
  });
  return bucket;
}

export default function CategoriesPage() {
  const { message } = App.useApp();
  const [entityType, setEntityType] = useState('product');
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [modalOpen, setModalOpen] = useState(false);
  const [editingRecord, setEditingRecord] = useState(null);
  const [form] = Form.useForm();

  const flatItems = useMemo(() => flattenTree(items), [items]);
  const currentEntityLabel = entityOptions.find((option) => option.value === entityType)?.label || '产品分类';

  async function loadTree(nextEntityType = entityType) {
    setLoading(true);
    try {
      const data = await getCategoryTree(nextEntityType);
      setItems(Array.isArray(data) ? data : []);
    } catch (error) {
      message.error(error.message || '加载分类树失败，请稍后重试。');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadTree(entityType);
  }, [entityType]);

  function openCreateModal() {
    setEditingRecord(null);
    form.resetFields();
    form.setFieldsValue({
      parent_id: 0,
      name_zh: '',
      slug: '',
      sort: 100,
      is_enabled: true,
    });
    setModalOpen(true);
  }

  function openEditModal(record) {
    setEditingRecord(record);
    form.resetFields();
    form.setFieldsValue({
      parent_id: Number(record.parent_id || 0),
      name_zh: record.name_zh || '',
      slug: record.slug || '',
      sort: Number(record.sort || 0),
      is_enabled: Number(record.is_enabled || 0) === 1,
    });
    setModalOpen(true);
  }

  async function handleSubmit(values) {
    setSaving(true);

    const payload = {
      parent_id: Number(values.parent_id || 0),
      name_zh: String(values.name_zh || '').trim(),
      slug: String(values.slug || '').trim(),
      sort: Number(values.sort || 0),
      is_enabled: values.is_enabled ? 1 : 0,
    };

    try {
      if (editingRecord?.id) {
        await updateCategory(entityType, editingRecord.id, payload);
        message.success('分类已更新。');
      } else {
        await createCategory(entityType, payload);
        message.success('分类已创建。');
      }

      setModalOpen(false);
      setEditingRecord(null);
      await loadTree(entityType);
    } catch (error) {
      message.error(error.message || '保存分类失败，请稍后重试。');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(record) {
    try {
      await deleteCategory(entityType, record.id);
      message.success('分类已删除。');
      await loadTree(entityType);
    } catch (error) {
      message.error(error.message || '删除分类失败，请稍后重试。');
    }
  }

  const columns = [
    {
      title: '分类名称',
      dataIndex: 'name_zh',
      render: (value, record) => (
        <Space direction="vertical" size={2}>
          <span>{`${'└ '.repeat(record.level || 0)}${value || '-'}`}</span>
          <Text type="secondary">{record.slug || '-'}</Text>
        </Space>
      ),
    },
    { title: '排序值', dataIndex: 'sort', width: 90 },
    {
      title: '状态',
      dataIndex: 'is_enabled',
      width: 100,
      render: (value) => (Number(value || 0) === 1 ? <Tag color="success">启用</Tag> : <Tag>停用</Tag>),
    },
    {
      title: '操作',
      key: 'actions',
      width: 180,
      render: (_, record) => (
        <Space>
          <Button size="small" onClick={() => openEditModal(record)}>
            编辑
          </Button>
          <Popconfirm title="确认删除该分类吗？" okText="删除" cancelText="取消" onConfirm={() => handleDelete(record)}>
            <Button size="small" danger>
              删除
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <>
      <PagePlaceholder hideHeader tags={[`当前类型 ${currentEntityLabel}`, `共 ${flatItems.length} 项`]}>
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <div className="toolbar-surface">
            <Space wrap size={12} style={{ width: '100%', justifyContent: 'space-between' }}>
              <Space wrap size={12}>
                <Text type="secondary">选择分类实体类型后，可统一维护对应目录树。</Text>
                <Select style={{ width: 220 }} options={entityOptions} value={entityType} onChange={setEntityType} />
              </Space>
              <Space wrap size={8}>
                <Button onClick={() => loadTree(entityType)}>刷新列表</Button>
                <Button type="primary" onClick={openCreateModal}>
                  新建分类
                </Button>
              </Space>
            </Space>
          </div>

          <Table
            rowKey="id"
            loading={loading}
            columns={columns}
            dataSource={flatItems}
            pagination={false}
            locale={{ emptyText: '暂无分类。' }}
          />
        </Space>
      </PagePlaceholder>

      <Modal
        title={editingRecord?.id ? '编辑分类' : '新建分类'}
        open={modalOpen}
        onCancel={() => {
          setModalOpen(false);
          setEditingRecord(null);
        }}
        onOk={() => form.submit()}
        okText="保存"
        cancelText="取消"
        confirmLoading={saving}
        destroyOnHidden
      >
        <Form form={form} layout="vertical" onFinish={handleSubmit}>
          <Form.Item name="parent_id" label="上级分类">
            <Select
              options={[
                { label: '顶级分类', value: 0 },
                ...flatItems.map((item) => ({
                  label: `${'└ '.repeat(item.level || 0)}${item.name_zh || '-'}`,
                  value: Number(item.id),
                })),
              ]}
            />
          </Form.Item>

          <Form.Item name="name_zh" label="分类名称" rules={[{ required: true, message: '请输入分类名称。' }]}>
            <Input />
          </Form.Item>

          <Form.Item name="slug" label="路由标识">
            <Input placeholder="可留空，由系统处理" />
          </Form.Item>

          <div className="form-two-column">
            <Form.Item name="sort" label="排序值">
              <InputNumber min={0} precision={0} style={{ width: '100%' }} />
            </Form.Item>
            <Form.Item name="is_enabled" label="启用状态" valuePropName="checked">
              <Switch />
            </Form.Item>
          </div>
        </Form>
      </Modal>
    </>
  );
}
