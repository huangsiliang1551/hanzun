import { useState } from 'react';
import { Alert, Button, Card, Form, Input, Space, Typography } from 'antd';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '@/providers/AuthProvider';

const { Paragraph, Text, Title } = Typography;
const loginBrand = '涵尊后台';
const loginSystemTitle = '涵尊后台管理系统';
const loginKicker = '后台登录';
const loginTitle = '登录后台';
const usernameLabel = '账号';
const usernamePlaceholder = '请输入账号';
const usernameRule = '请输入登录账号。';
const passwordLabel = '密码';
const passwordPlaceholder = '请输入密码';
const passwordRule = '请输入登录密码。';
const submitLabel = '登 录';
const defaultRedirectPath = '/dashboard';

export default function LoginPage() {
  const [form] = Form.useForm();
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const requestedPath = String(location.state?.from?.pathname || '').trim();
  const redirectTo =
    requestedPath !== '' && requestedPath !== '/login' && requestedPath !== '/settings'
      ? requestedPath
      : defaultRedirectPath;

  async function handleSubmit(values) {
    setSubmitting(true);
    setErrorMessage('');

    try {
      await login(values);
      navigate(redirectTo, { replace: true });
    } catch (error) {
      setErrorMessage(error.message || '登录失败，请检查账号或密码后重试。');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="login-page-shell">
      <div className="login-page-grid">
        <Card className="login-hero-card" variant="borderless">
          <Space direction="vertical" size={10} style={{ width: '100%' }}>
            <div>
              <Text className="login-eyebrow">{loginBrand}</Text>
              <Title level={2} style={{ marginBottom: 0, marginTop: 8 }}>
                {loginSystemTitle}
              </Title>
            </div>

            <Paragraph className="login-hero-copy" style={{ marginBottom: 0 }}>
              面向内容、资源、询盘与多语言运营的一体化后台管理系统。
            </Paragraph>
          </Space>
        </Card>

        <Card className="login-form-card" variant="borderless">
          <Space direction="vertical" size={18} style={{ width: '100%' }}>
            <div className="login-form-header">
              <Text className="login-form-kicker">{loginKicker}</Text>
              <Title level={3} style={{ marginBottom: 0 }}>
                {loginTitle}
              </Title>
            </div>

            {errorMessage ? <Alert type="error" showIcon message={errorMessage} /> : null}

            <Form form={form} layout="vertical" onFinish={handleSubmit}>
              <Form.Item
                name="username"
                label={usernameLabel}
                rules={[{ required: true, message: usernameRule }]}
              >
                <Input placeholder={usernamePlaceholder} autoComplete="username" />
              </Form.Item>

              <Form.Item
                name="password"
                label={passwordLabel}
                rules={[{ required: true, message: passwordRule }]}
              >
                <Input.Password placeholder={passwordPlaceholder} autoComplete="current-password" />
              </Form.Item>

              <Button type="primary" htmlType="submit" block size="large" loading={submitting}>
                {submitLabel}
              </Button>
            </Form>
          </Space>
        </Card>
      </div>
    </div>
  );
}
