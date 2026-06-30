import { Suspense, lazy } from 'react';
import { Spin } from 'antd';
import AppErrorBoundary from '@/components/AppErrorBoundary';

const RichTextEditor = lazy(() => import('@/components/RichTextEditor'));

export default function LazyRichTextEditor({ active = false, minHeight = 260, ...props }) {
  if (!active) {
    return (
      <div
        style={{
          minHeight,
          border: '1px dashed #d9d9d9',
          borderRadius: 8,
          background: '#fafafa',
        }}
      />
    );
  }

  return (
    <AppErrorBoundary>
      <Suspense
        fallback={
          <div
            style={{
              minHeight,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              border: '1px dashed #d9d9d9',
              borderRadius: 8,
              background: '#fafafa',
            }}
          >
            <Spin size="small" />
          </div>
        }
      >
        <RichTextEditor minHeight={minHeight} {...props} />
      </Suspense>
    </AppErrorBoundary>
  );
}
