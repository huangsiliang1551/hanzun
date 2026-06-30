import React from 'react';
import ReactDOM from 'react-dom/client';
import { App as AntdApp, ConfigProvider } from 'antd';
import zhCN from 'antd/locale/zh_CN';
import { HashRouter } from 'react-router-dom';
import App from './App';
import AppErrorBoundary from '@/components/AppErrorBoundary';
import { AuthProvider } from '@/providers/AuthProvider';
import { SiteBuildProvider } from '@/providers/SiteBuildProvider';
import 'antd/dist/reset.css';
import './styles.css';

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <ConfigProvider
      locale={zhCN}
      theme={{
        token: {
          colorPrimary: '#1f6feb',
          borderRadius: 10,
          colorBgLayout: '#f3f6fb',
        },
      }}
    >
      <AntdApp>
        <AppErrorBoundary>
          <AuthProvider>
            <HashRouter
              future={{
                v7_startTransition: true,
                v7_relativeSplatPath: true,
              }}
            >
              <SiteBuildProvider>
                <App />
              </SiteBuildProvider>
            </HashRouter>
          </AuthProvider>
        </AppErrorBoundary>
      </AntdApp>
    </ConfigProvider>
  </React.StrictMode>,
);
