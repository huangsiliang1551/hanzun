import React from 'react';
import { Button, Result } from 'antd';

export default class AppErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidCatch(error) {
    // eslint-disable-next-line no-console
    console.error(error);
  }

  handleReload = () => {
    window.location.reload();
  };

  render() {
    if (this.state.hasError) {
      return (
        <div className="app-error-shell">
          <Result
            status="error"
            title="\u9875\u9762\u52a0\u8f7d\u5931\u8d25"
            subTitle="\u53d1\u751f\u4e86\u672a\u5904\u7406\u5f02\u5e38\uff0c\u8bf7\u5237\u65b0\u540e\u91cd\u8bd5\u3002"
            extra={
              <Button type="primary" onClick={this.handleReload}>
                {'\u5237\u65b0\u9875\u9762'}
              </Button>
            }
          />
        </div>
      );
    }

    return this.props.children;
  }
}
