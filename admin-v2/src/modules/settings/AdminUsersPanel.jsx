import { useEffect, useMemo, useState } from 'react';
import {
  App,
  Button,
  Drawer,
  Form,
  Input,
  Popconfirm,
  Select,
  Space,
  Table,
  Tag,
  Typography,
} from 'antd';
import {
  createAdminUser,
  deleteAdminUser,
  getAdminUserDetail,
  getAdminUsersBootstrap,
  normalizeRoleItems,
  normalizeUserItems,
  updateAdminUser,
} from '@/api/settingsAdmin';

const { Search } = Input;
const { Text } = Typography;

const statusOptions = [
  { label: '启用', value: 1 },
  { label: '停用', value: 0 },
];

function renderStatus(status) {
  return Number(status) === 1 ? <Tag color="success">启用</Tag> : <Tag color="default">停用</Tag>;
}

export default function AdminUsersPanel() {
  const { message } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(true);
  const [drawerLoading, setDrawerLoading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [users, setUsers] = useState([]);
  const [roles, setRoles] = useState([]);
  const [keyword, setKeyword] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);

  const roleOptions = useMemo(
    () =>
      roles.map((item) => ({
        label: item.name,
        value: Number(item.id),
      })),
    [roles],
  );

  const filteredUsers = useMemo(() => {
    const normalizedKeyword = keyword.trim().toLowerCase();
    if (!normalizedKeyword) {
      return users;
    }

    return users.filter((item) =>
      [item.username, item.nickname]
        .filter(Boolean)
        .some((value) => String(value).toLowerCase().includes(normalizedKeyword)),
    );
  }, [keyword, users]);

  async function loadPanel() {
    setLoading(true);

    try {
      const payload = await getAdminUsersBootstrap();
      setUsers(normalizeUserItems(payload?.users));
      setRoles(normalizeRoleItems(payload?.roles));
    } catch (error) {
      message.error(error.message || '加载管理员与角色数据失败');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadPanel();
  }, []);

  function openCreateDrawer() {
    setEditingId(null);
    form.resetFields();
    form.setFieldsValue({
      status: 1,
      role_ids: [],
    });
    setDrawerOpen(true);
  }

  async function openEditDrawer(record) {
    setEditingId(Number(record.id));
    setDrawerOpen(true);
    setDrawerLoading(true);

    try {
      const detail = await getAdminUserDetail(record.id);
      form.setFieldsValue({
        username: detail?.user?.username || '',
        password: '',
        nickname: detail?.user?.nickname || '',
        status: Number(detail?.user?.status || 0),
        role_ids: Array.isArray(detail?.roles) ? detail.roles.map((item) => Number(item.id)) : [],
      });
    } catch (error) {
      message.error(error.message || '加载管理员详情失败');
      setDrawerOpen(false);
      setEditingId(null);
    } finally {
      setDrawerLoading(false);
    }
  }

  async function handleSubmit(values) {
    setSubmitting(true);

    const payload = {
      nickname: String(values.nickname || '').trim(),
      status: Number(values.status),
      role_ids: Array.isArray(values.role_ids) ? values.role_ids.map(Number) : [],
    };

    if (!editingId) {
      payload.username = String(values.username || '').trim();
      payload.password = String(values.password || '').trim();
    } else if (String(values.password || '').trim()) {
      payload.password = String(values.password || '').trim();
    }

    try {
      if (editingId) {
        await updateAdminUser(editingId, payload);
        message.success('管理员已更新');
      } else {
        await createAdminUser(payload);
        message.success('管理员已创建');
      }

      setDrawerOpen(false);
      setEditingId(null);
      form.resetFields();
      loadPanel();
    } catch (error) {
      message.error(error.message || '保存管理员失败');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(record) {
    try {
      await deleteAdminUser(record.id);
      message.success('管理员已删除');
      loadPanel();
    } catch (error) {
      message.error(error.message || '删除管理员失败');
    }
  }

  const columns = [
    {
      title: '登录账号',
      dataIndex: 'username',
      key: 'username',
      width: 180,
      render: (value, record) => (
        <Space direction="vertical" size={0}>
          <Text strong>{value || '-'}</Text>
          <Text type="secondary">ID #{record.id}</Text>
        </Space>
      ),
    },
    {
      title: '显示名称',
      dataIndex: 'nickname',
      key: 'nickname',
      width: 180,
      render: (value) => value || '-',
    },
    {
      title: '角色',
      key: 'roles',
      render: (_, record) => (
        <Space wrap>
          {(record.roles || []).map((role) => (
            <Tag key={`${record.id}-${role.id}`}>{role.name}</Tag>
          ))}
        </Space>
      ),
    },
    {
      title: '状态',
      dataIndex: 'status',
      key: 'status',
      width: 120,
      render: renderStatus,
    },
    {
      title: '最近登录',
      dataIndex: 'last_login_at',
      key: 'last_login_at',
      width: 200,
      render: (value) => value || '-',
    },
    {
      title: '操作',
      key: 'actions',
      width: 180,
      render: (_, record) => (
        <Space wrap>
          <Button size="small" onClick={() => openEditDrawer(record)}>
            编辑
          </Button>
          <Popconfirm
            title="确认删除该管理员吗？"
            description="删除后将同时移除账号与角色绑定关系。"
            okText="删除"
            cancelText="取消"
            onConfirm={() => handleDelete(record)}
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
    <Space direction="vertical" size={16} style={{ width: '100%' }} className="admin-users-panel">
      <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
        <Search
          allowClear
          placeholder="搜索账号或显示名称"
          onSearch={setKeyword}
          onChange={(event) => setKeyword(event.target.value)}
          value={keyword}
          style={{ width: 320, maxWidth: '100%' }}
        />

        <Space>
          <Button onClick={loadPanel}>刷新列表</Button>
          <Button type="primary" onClick={openCreateDrawer}>
            新建管理员
          </Button>
        </Space>
      </Space>

      <div className="table-scroll-shell">
        <Table
          rowKey="id"
          loading={loading}
          columns={columns}
          dataSource={filteredUsers}
          pagination={{ pageSize: 10, showSizeChanger: false }}
          scroll={{ x: 980 }}
        />
      </div>

      <Drawer
        title={editingId ? '编辑管理员' : '新建管理员'}
        open={drawerOpen}
        width={520}
        onClose={() => {
          setDrawerOpen(false);
          setEditingId(null);
        }}
        destroyOnHidden
      >
        <Form form={form} layout="vertical" onFinish={handleSubmit} disabled={drawerLoading || submitting}>
          <Form.Item
            name="username"
            label="登录账号"
            rules={[{ required: !editingId, message: '请输入登录账号。' }]}
          >
            <Input disabled={Boolean(editingId)} placeholder="admin.operator" maxLength={32} />
          </Form.Item>

          <Form.Item
            name="password"
            label={editingId ? '新密码' : '登录密码'}
            extra={editingId ? '留空表示不修改密码。' : '建议至少 8 位。'}
            rules={editingId ? [] : [{ required: true, message: '请输入登录密码。' }]}
          >
            <Input.Password placeholder="请输入密码" maxLength={64} />
          </Form.Item>

          <Form.Item
            name="nickname"
            label="显示名称"
            rules={[{ required: true, message: '请输入显示名称。' }]}
          >
            <Input placeholder="请输入显示名称" maxLength={50} />
          </Form.Item>

          <Form.Item
            name="status"
            label="账号状态"
            rules={[{ required: true, message: '请选择账号状态。' }]}
          >
            <Select options={statusOptions} />
          </Form.Item>

          <Form.Item
            name="role_ids"
            label="角色"
            rules={[{ required: true, message: '至少选择一个角色。' }]}
          >
            <Select mode="multiple" options={roleOptions} placeholder="请选择角色" optionFilterProp="label" />
          </Form.Item>

          <Space>
            <Button onClick={loadPanel}>刷新列表</Button>
            <Button type="primary" htmlType="submit" loading={submitting}>
              保存
            </Button>
          </Space>
        </Form>
      </Drawer>
    </Space>
  );
}
