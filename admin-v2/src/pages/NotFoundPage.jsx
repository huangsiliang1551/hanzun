import { Button, Result } from 'antd';
import { useNavigate } from 'react-router-dom';

export default function NotFoundPage() {
  const navigate = useNavigate();

  return (
    <Result
      status="404"
      title="页面未找到"
      subTitle="该路由暂未配置，返回后台继续操作。"
      extra={
        <Button type="primary" onClick={() => navigate('/dashboard')}>
          返回看板
        </Button>
      }
    />
  );
}
