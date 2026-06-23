import { useEffect, useState } from 'react';
import {
  Alert,
  Button,
  Card,
  Col,
  Descriptions,
  Form,
  Input,
  List,
  Row,
  Space,
  Tag,
  Typography,
  message,
} from 'antd';
import { getAccountBootstrap, normalizePaginatedLogs, updateAccountSettings } from '@/api/settingsAdmin';

const { Password } = Input;
const { Text } = Typography;

function statusTag(status) {
  return Number(status) === 1 ? <Tag color="success">启用</Tag> : <Tag color="default">停用</Tag>;
}

export default function AccountPanel() {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [profile, setProfile] = useState(null);
  const [loginLogs, setLoginLogs] = useState([]);

  async function loadPanel() {
    setLoading(true);

    try {
      const payload = await getAccountBootstrap();
      const profileData = payload?.profile || null;
      setProfile(profileData || null);
      form.setFieldsValue({
        nickname: profileData?.nickname || '',
        email: profileData?.email || '',
        mobile: profileData?.mobile || '',
        password: '',
      });

      const logsData = payload?.login_logs || { items: [] };
      setLoginLogs(normalizePaginatedLogs(logsData).items);
    } catch (error) {
      message.error(error.message || '加载账号信息失败。');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadPanel();
  }, []);

  async function handleSubmit(values) {
    setSaving(true);

    try {
      const result = await updateAccountSettings({
        nickname: String(values.nickname || '').trim(),
        email: String(values.email || '').trim(),
        mobile: String(values.mobile || '').trim(),
        password: String(values.password || '').trim(),
      });

      setProfile(result || null);
      form.setFieldsValue({
        nickname: result?.nickname || '',
        email: result?.email || '',
        mobile: result?.mobile || '',
        password: '',
      });

      if (Number(result?.require_relogin || 0) === 1) {
        message.success('账号已更新，请使用新密码重新登录。');
      } else {
        message.success('账号信息已更新。');
      }

      loadPanel();
    } catch (error) {
      message.error(error.message || '保存账号信息失败。');
    } finally {
      setSaving(false);
    }
  }

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Row gutter={[16, 16]}>
        <Col xs={24} xl={14}>
          <Card bordered={false} title="账号资料">
            <Descriptions column={2} size="small" bordered>
              <Descriptions.Item label="登录账号">{profile?.username || '-'}</Descriptions.Item>
              <Descriptions.Item label="账号状态">{statusTag(profile?.status)}</Descriptions.Item>
              <Descriptions.Item label="角色" span={2}>
                <Space wrap>
                  {(profile?.role_names || []).map((name) => (
                    <Tag key={name}>{name}</Tag>
                  ))}
                    {(!profile?.role_names || profile.role_names.length === 0) && (
                      <Text type="secondary">暂无角色</Text>
                    )}
                </Space>
              </Descriptions.Item>
              <Descriptions.Item label="最近登录时间">{profile?.last_login_at || '-'}</Descriptions.Item>
              <Descriptions.Item label="最近登录 IP">{profile?.last_login_ip || '-'}</Descriptions.Item>
            </Descriptions>

            <Form form={form} layout="vertical" onFinish={handleSubmit} style={{ marginTop: 20 }}>
              <Row gutter={[16, 0]}>
                <Col xs={24} md={12}>
                  <Form.Item
                    name="nickname"
                    label="显示名称"
                    rules={[{ required: true, message: '请输入显示名称。' }]}
                  >
                    <Input maxLength={50} placeholder="请输入显示名称" />
                  </Form.Item>
                </Col>

                <Col xs={24} md={12}>
                  <Form.Item
                    name="email"
                    label="邮箱"
                    rules={[{ type: 'email', message: '请输入正确的邮箱地址。' }]}
                  >
                    <Input maxLength={120} placeholder="请输入联系邮箱" />
                  </Form.Item>
                </Col>

                <Col xs={24} md={12}>
                  <Form.Item name="mobile" label="手机号">
                    <Input maxLength={30} placeholder="请输入手机号" />
                  </Form.Item>
                </Col>

                <Col xs={24} md={12}>
                  <Form.Item
                    name="password"
                    label="新密码"
                    extra="留空则保持当前密码不变。"
                  >
                    <Password maxLength={64} placeholder="至少 8 位字符" />
                  </Form.Item>
                </Col>
              </Row>

              <Space>
                <Button onClick={loadPanel}>重新加载</Button>
                <Button type="primary" htmlType="submit" loading={saving}>
                  保存账号
                </Button>
              </Space>
            </Form>
          </Card>
        </Col>

        <Col xs={24} xl={10}>
          <Card bordered={false} title="最近登录记录">
            <Alert
              type="info"
              showIcon
              message="以下列表仅展示当前账号最近的登录尝试记录。"
              style={{ marginBottom: 16 }}
            />

            <List
              dataSource={loginLogs}
              locale={{ emptyText: '暂无登录记录。' }}
              renderItem={(item) => (
                <List.Item>
                  <List.Item.Meta
                    title={
                      <Space wrap>
                        <Text strong>{item.created_at || '-'}</Text>
                        {Number(item.is_success) === 1 ? (
                          <Tag color="success">成功</Tag>
                        ) : (
                          <Tag color="error">失败</Tag>
                        )}
                      </Space>
                    }
                    description={
                      <Space direction="vertical" size={0}>
                        <Text type="secondary">IP：{item.login_ip || '-'}</Text>
                        <Text type="secondary">原因：{item.reason || '-'}</Text>
                      </Space>
                    }
                  />
                </List.Item>
              )}
            />
          </Card>
        </Col>
      </Row>
    </Space>
  );
}
