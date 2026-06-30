import { useEffect, useMemo, useState } from 'react';
import { Button, Card, Checkbox, Col, Empty, Form, Input, List, Popconfirm, Row, Space, Tag, Tree, Typography, message } from 'antd';
import {
  createRole,
  deleteRole,
  getRoleDetail,
  getRolesBootstrap,
  normalizeActionPointItems,
  normalizeMenuItems,
  normalizeRoleItems,
  updateRole,
  updateRolePermissions,
} from '@/api/settingsAdmin';

const { Text } = Typography;

function buildMenuTree(items) {
  const map = new Map();
  const roots = [];

  items.forEach((item) => {
    map.set(Number(item.id), {
      key: Number(item.id),
      title: item.name,
      children: [],
    });
  });

  items.forEach((item) => {
    const current = map.get(Number(item.id));
    const parentId = Number(item.parent_id || 0);

    if (parentId > 0 && map.has(parentId)) {
      map.get(parentId).children.push(current);
      return;
    }

    roots.push(current);
  });

  return roots;
}

function roleStatusTag(status) {
  return Number(status) === 1 ? <Tag color="success">启用</Tag> : <Tag color="default">停用</Tag>;
}

export default function RolesPermissionsPanel() {
  const [roleForm] = Form.useForm();
  const [permissionsForm] = Form.useForm();
  const [loading, setLoading] = useState(true);
  const [detailLoading, setDetailLoading] = useState(false);
  const [savingRole, setSavingRole] = useState(false);
  const [savingPermissions, setSavingPermissions] = useState(false);
  const [roles, setRoles] = useState([]);
  const [menus, setMenus] = useState([]);
  const [actionPoints, setActionPoints] = useState([]);
  const [selectedRoleId, setSelectedRoleId] = useState(null);

  const menuTree = useMemo(() => buildMenuTree(menus), [menus]);
  const checkedMenuIds = Form.useWatch('menu_ids', permissionsForm) || [];
  const actionPointOptions = useMemo(
    () =>
      actionPoints.map((item) => ({
        label: (
          <div className="permission-action-option">
            <Text>{item.name}</Text>
            <Text type="secondary">{item.code}</Text>
          </div>
        ),
        value: Number(item.id),
      })),
    [actionPoints],
  );

  async function loadRoleDetail(roleId) {
    setDetailLoading(true);

    try {
      const detail = await getRoleDetail(roleId);
      setSelectedRoleId(Number(roleId));
      roleForm.setFieldsValue({
        name: detail?.role?.name || '',
        code: detail?.role?.code || '',
        description: detail?.role?.description || '',
        status: Number(detail?.role?.status || 0) === 1,
      });
      permissionsForm.setFieldsValue({
        menu_ids: Array.isArray(detail?.menus) ? detail.menus.map((item) => Number(item.id)) : [],
        action_point_ids: Array.isArray(detail?.action_points)
          ? detail.action_points.map((item) => Number(item.id))
          : [],
      });
    } catch (error) {
      message.error(error.message || '加载角色详情失败');
    } finally {
      setDetailLoading(false);
    }
  }

  async function loadBaseData(nextRoleId) {
    setLoading(true);

    try {
      const payload = await getRolesBootstrap();
      const nextRoles = normalizeRoleItems(payload?.roles);
      setRoles(nextRoles);
      setMenus(normalizeMenuItems(payload?.menus));
      setActionPoints(normalizeActionPointItems(payload?.action_points));

      const safeRoleId =
        nextRoleId ||
        (nextRoles.some((item) => Number(item.id) === Number(selectedRoleId))
          ? selectedRoleId
          : nextRoles[0]?.id || null);

      if (safeRoleId) {
        await loadRoleDetail(safeRoleId);
      } else {
        setSelectedRoleId(null);
        roleForm.resetFields();
        permissionsForm.resetFields();
      }
    } catch (error) {
      message.error(error.message || '加载角色与权限数据失败');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadBaseData();
  }, []);

  function startCreateRole() {
    setSelectedRoleId(null);
    roleForm.resetFields();
    roleForm.setFieldsValue({
      status: true,
    });
    permissionsForm.setFieldsValue({
      menu_ids: [],
      action_point_ids: [],
    });
  }

  async function handleSaveRole(values) {
    setSavingRole(true);

    try {
      const payload = {
        name: String(values.name || '').trim(),
        code: String(values.code || '').trim(),
        description: String(values.description || '').trim(),
        status: values.status ? 1 : 0,
      };

      let nextRoleId = selectedRoleId;
      if (selectedRoleId) {
        const result = await updateRole(selectedRoleId, payload);
        nextRoleId = Number(result?.role?.id || selectedRoleId);
        message.success('角色已更新');
      } else {
        const result = await createRole(payload);
        nextRoleId = Number(result?.role?.id || 0) || null;
        message.success('角色已创建');
      }

      await loadBaseData(nextRoleId);
    } catch (error) {
      message.error(error.message || '保存角色失败');
    } finally {
      setSavingRole(false);
    }
  }

  async function handleSavePermissions(values) {
    if (!selectedRoleId) {
      message.warning('请先保存角色，再配置权限。');
      return;
    }

    setSavingPermissions(true);

    try {
      await updateRolePermissions(selectedRoleId, {
        menu_ids: Array.isArray(values.menu_ids) ? values.menu_ids.map(Number) : [],
        action_point_ids: Array.isArray(values.action_point_ids)
          ? values.action_point_ids.map(Number)
          : [],
      });
      message.success('权限已保存');
      await loadRoleDetail(selectedRoleId);
    } catch (error) {
      message.error(error.message || '保存权限失败');
    } finally {
      setSavingPermissions(false);
    }
  }

  async function handleDeleteRole(roleId) {
    try {
      await deleteRole(roleId);
      message.success('角色已删除');
      await loadBaseData();
    } catch (error) {
      message.error(error.message || '删除角色失败');
    }
  }

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Row gutter={[16, 16]}>
        <Col xs={24} xl={7}>
          <Card
            bordered={false}
            title="角色列表"
            extra={
              <Space>
                <Button size="small" onClick={() => loadBaseData()}>
                  重新加载
                </Button>
                <Button size="small" type="primary" onClick={startCreateRole}>
                  新建角色
                </Button>
              </Space>
            }
            loading={loading}
          >
            <List
              dataSource={roles}
              locale={{ emptyText: '暂无角色。' }}
              renderItem={(item) => (
                <List.Item
                  actions={[
                    <Button
                      key="open"
                      type={Number(selectedRoleId) === Number(item.id) ? 'primary' : 'default'}
                      size="small"
                      onClick={() => loadRoleDetail(item.id)}
                    >
                      打开
                    </Button>,
                    <Popconfirm
                      key="delete"
                      title="确认删除这个角色吗？"
                      description="内置角色或已绑定用户的角色，后端可能会拒绝删除。"
                      okText="删除"
                      cancelText="取消"
                      onConfirm={() => handleDeleteRole(item.id)}
                    >
                      <Button size="small" danger>
                        删除
                      </Button>
                    </Popconfirm>,
                  ]}
                >
                  <List.Item.Meta
                    title={
                      <Space wrap>
                        <Text strong>{item.name}</Text>
                        {roleStatusTag(item.status)}
                      </Space>
                    }
                    description={
                      <Space direction="vertical" size={0}>
                        <Text type="secondary">{item.code}</Text>
                        <Text type="secondary">{item.description || '暂无说明'}</Text>
                      </Space>
                    }
                  />
                </List.Item>
              )}
            />
          </Card>
        </Col>

        <Col xs={24} xl={17}>
          <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card bordered={false} title={selectedRoleId ? '角色资料' : '新建角色'} loading={detailLoading && Boolean(selectedRoleId)}>
              <Form form={roleForm} layout="vertical" onFinish={handleSaveRole}>
                <Row gutter={[16, 0]}>
                  <Col xs={24} md={12}>
                    <Form.Item
                      name="name"
                      label="角色名称"
                      rules={[{ required: true, message: '请输入角色名称。' }]}
                    >
                      <Input placeholder="销售经理" maxLength={64} />
                    </Form.Item>
                  </Col>

                  <Col xs={24} md={12}>
                    <Form.Item
                      name="code"
                      label="角色编码"
                      rules={[{ required: true, message: '请输入角色编码。' }]}
                    >
                      <Input placeholder="例如：sales_manager" maxLength={64} />
                    </Form.Item>
                  </Col>
                </Row>

                <Form.Item name="description" label="角色说明">
                  <Input.TextArea rows={3} maxLength={255} placeholder="简要说明这个角色的职责范围" />
                </Form.Item>

                <Form.Item name="status" label="角色状态" valuePropName="checked">
                  <Checkbox>角色启用中</Checkbox>
                </Form.Item>

                <Space>
                  <Button onClick={selectedRoleId ? () => loadRoleDetail(selectedRoleId) : startCreateRole}>
                    重置
                  </Button>
                  <Button type="primary" htmlType="submit" loading={savingRole}>
                    保存角色
                  </Button>
                </Space>
              </Form>
            </Card>

            <Card bordered={false} title="权限配置" loading={detailLoading && Boolean(selectedRoleId)}>
              {selectedRoleId ? (
                <Form form={permissionsForm} layout="vertical" onFinish={handleSavePermissions}>
                  <Row gutter={[16, 16]}>
                    <Col xs={24} xl={6}>
                      <Form.Item name="menu_ids" label="可见菜单" className="permission-menu-panel">
                        <Tree
                          checkable
                          defaultExpandAll
                          treeData={menuTree}
                          checkedKeys={checkedMenuIds}
                          onCheck={(nextCheckedKeys) => {
                            permissionsForm.setFieldValue('menu_ids', nextCheckedKeys);
                          }}
                        />
                      </Form.Item>
                    </Col>

                    <Col xs={24} xl={18}>
                      <Form.Item name="action_point_ids" label="操作权限点">
                        <Checkbox.Group className="permission-checkbox-grid" style={{ width: '100%' }} options={actionPointOptions} />
                      </Form.Item>
                    </Col>
                  </Row>

                  <Space>
                    <Button onClick={() => loadRoleDetail(selectedRoleId)}>重新加载详情</Button>
                    <Button type="primary" htmlType="submit" loading={savingPermissions}>
                      保存权限
                    </Button>
                  </Space>
                </Form>
              ) : (
                <Empty description="请先选择或创建一个角色。" />
              )}
            </Card>
          </Space>
        </Col>
      </Row>
    </Space>
  );
}
