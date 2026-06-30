import { Suspense } from 'react';
import { Spin } from 'antd';
import { useRoutes } from 'react-router-dom';
import { routes } from '@/router';

export default function App() {
  const element = useRoutes(routes);

  return (
    <Suspense
      fallback={
        <div className="route-loading-shell">
          <Spin size="large" />
        </div>
      }
    >
      {element}
    </Suspense>
  );
}
