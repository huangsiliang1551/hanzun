import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, __dirname, '');
  const proxyTarget = (env.VITE_DEV_PROXY_TARGET || 'http://127.0.0.1:8080').replace(/\/$/, '');

  const proxyEntry = {
    target: proxyTarget,
    changeOrigin: true,
  };

  return {
    base: '/admin-app/',
    plugins: [react()],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, './src'),
      },
    },
    build: {
      outDir: path.resolve(__dirname, '../admin-app'),
      emptyOutDir: true,
      rollupOptions: {
        output: {
          manualChunks(id) {
            const normalized = id.split(path.sep).join('/');

            if (normalized.includes('/node_modules/suneditor/') || normalized.includes('/node_modules/suneditor-react/')) {
              return 'editor-vendor';
            }

            if (normalized.includes('/node_modules/react/') || normalized.includes('/node_modules/react-dom/')) {
              return 'react-vendor';
            }

            if (normalized.includes('/node_modules/react-router/') || normalized.includes('/node_modules/react-router-dom/')) {
              return 'router-vendor';
            }

            if (
              normalized.includes('/node_modules/@ant-design/icons/')
            ) {
              return 'ant-icons';
            }

            return undefined;
          },
        },
      },
    },
    server: {
      host: '0.0.0.0',
      port: 5174,
      proxy: {
        '^/admin(?!-app)(?:/|$)': proxyEntry,
        '/health': proxyEntry,
        '/uploads': proxyEntry,
        '/storage': proxyEntry,
        '/assets/images': proxyEntry,
        '/assets/videos': proxyEntry,
        '/assets/files': proxyEntry,
      },
    },
  };
});
