import { useState } from 'react';
import { Alert, Button, Card, Form, Input, Space, Typography } from 'antd';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '@/providers/AuthProvider';

const { Paragraph, Text, Title } = Typography;
const loginBrand = '\u6db5\u5c0a\u540e\u53f0';
const loginSystemTitle = '\u6db5\u5c0a\u540e\u53f0\u7ba1\u7406\u7cfb\u7edf';
const loginKicker = '\u540e\u53f0\u767b\u5f55';
const loginTitle = '\u767b\u5f55\u540e\u53f0';
const usernameLabel = '\u8d26\u53f7';
const usernamePlaceholder = '\u8bf7\u8f93\u5165\u8d26\u53f7';
const usernameRule = '\u8bf7\u8f93\u5165\u767b\u5f55\u8d26\u53f7\u3002';
const passwordLabel = '\u5bc6\u7801';
const passwordPlaceholder = '\u8bf7\u8f93\u5165\u5bc6\u7801';
const passwordRule = '\u8bf7\u8f93\u5165\u767b\u5f55\u5bc6\u7801\u3002';
const submitLabel = '\u767b \u5f55';

export default function LoginPage() {
  const [form] = Form.useForm();
  const [submitting, setSubmitting] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const redirectTo = location.state?.from?.pathname || '/dashboard';

  async function handleSubmit(values) {
    setSubmitting(true);
    setErrorMessage('');

    try {
      await login(values);
      navigate(redirectTo, { replace: true });
    } catch (error) {
      setErrorMessage(error.message || '\u767b\u5f55\u5931\u8d25\uff0c\u8bf7\u68c0\u67e5\u8d26\u53f7\u6216\u5bc6\u7801\u540e\u91cd\u8bd5\u3002');
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
              面向内容、资源、询盘与多语言运营的统一后台。
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
