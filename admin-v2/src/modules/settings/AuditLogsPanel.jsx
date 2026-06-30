import { useEffect, useRef, useState } from 'react';
import { Button, Input, Select, Space, Table, Tabs, Tag, message } from 'antd';
import {
  getLogsBootstrap,
  getLoginLogs,
  getOperationLogs,
  normalizeActionPointItems,
  normalizePaginatedLogs,
} from '@/api/settingsAdmin';

function successTag(flag) {
  return Number(flag) === 1 ? <Tag color="success">成功</Tag> : <Tag color="error">失败</Tag>;
}

export default function AuditLogsPanel() {
  const [operationFilters, setOperationFilters] = useState({
    page: 1,
    page_size: 10,
    module: '',
    action_point: '',
    operator_name: '',
  });
  const [loginFilters, setLoginFilters] = useState({
    page: 1,
    page_size: 10,
    username: '',
  });
  const [operationLoading, setOperationLoading] = useState(true);
  const [loginLoading, setLoginLoading] = useState(true);
  const [operationData, setOperationData] = useState({ items: [], total: 0, page: 1, page_size: 10 });
  const [loginData, setLoginData] = useState({ items: [], total: 0, page: 1, page_size: 10 });
  const [actionOptions, setActionOptions] = useState([]);
  const didBootstrapRef = useRef(false);

  async function loadBootstrap() {
    setOperationLoading(true);
    setLoginLoading(true);

    try {
      const result = await getLogsBootstrap({
        operation_page: operationFilters.page,
        operation_page_size: operationFilters.page_size,
        module: operationFilters.module,
        action_point: operationFilters.action_point,
        operator_name: operationFilters.operator_name,
        login_page: loginFilters.page,
        login_page_size: loginFilters.page_size,
        username: loginFilters.username,
      });

      setActionOptions(
        normalizeActionPointItems(result?.action_points).map((item) => ({
          label: `${item.name} (${item.code})`,
          value: item.code,
        })),
      );
      setOperationData(normalizePaginatedLogs(result?.operation_logs));
      setLoginData(normalizePaginatedLogs(result?.login_logs));
    } catch (error) {
      message.error(error.message || '加载日志数据失败');
    } finally {
      setOperationLoading(false);
      setLoginLoading(false);
    }
  }

  async function loadOperationLogs(nextFilters = operationFilters) {
    setOperationLoading(true);

    try {
      const result = await getOperationLogs(nextFilters);
      setOperationData(normalizePaginatedLogs(result));
    } catch (error) {
      message.error(error.message || '加载操作日志失败');
    } finally {
      setOperationLoading(false);
    }
  }

  async function loadLoginLogs(nextFilters = loginFilters) {
    setLoginLoading(true);

    try {
      const result = await getLoginLogs(nextFilters);
      setLoginData(normalizePaginatedLogs(result));
    } catch (error) {
      message.error(error.message || '加载登录日志失败');
    } finally {
      setLoginLoading(false);
    }
  }

  useEffect(() => {
    loadBootstrap().finally(() => {
      didBootstrapRef.current = true;
    });
  }, []);

  useEffect(() => {
    if (!didBootstrapRef.current) {
      return;
    }
    loadOperationLogs(operationFilters);
  }, [operationFilters]);

  useEffect(() => {
    if (!didBootstrapRef.current) {
      return;
    }
    loadLoginLogs(loginFilters);
  }, [loginFilters]);

  const operationColumns = [
    {
      title: '时间',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 180,
      render: (value) => value || '-',
    },
    {
      title: '操作人',
      dataIndex: 'operator_name',
      key: 'operator_name',
      width: 150,
      render: (value) => value || '-',
    },
    {
      title: '模块',
      dataIndex: 'module',
      key: 'module',
      width: 120,
    },
    {
      title: '操作点',
      dataIndex: 'action_point',
      key: 'action_point',
      width: 220,
    },
    {
      title: '目标对象',
      key: 'target',
      render: (_, record) => `${record.target_type || '-'} / ${record.target_id || '-'}`,
    },
    {
      title: '结果',
      key: 'result',
      width: 140,
      render: (_, record) => successTag(record.is_success),
    },
  ];

  const loginColumns = [
    {
      title: '时间',
      dataIndex: 'created_at',
      key: 'created_at',
      width: 180,
    },
    {
      title: '账号',
      dataIndex: 'username',
      key: 'username',
      width: 160,
    },
    {
      title: 'IP',
      dataIndex: 'login_ip',
      key: 'login_ip',
      width: 160,
    },
    {
      title: '状态',
      key: 'status',
      width: 120,
      render: (_, record) => successTag(record.is_success),
    },
    {
      title: '原因',
      dataIndex: 'reason',
      key: 'reason',
      render: (value) => value || '-',
    },
  ];

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Tabs
        className="page-tabs-compact"
        items={[
          {
            key: 'operations',
            label: '操作日志',
            children: (
              <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <Space wrap>
                  <Input
                    placeholder="操作人姓名"
                    value={operationFilters.operator_name}
                    onChange={(event) =>
                      setOperationFilters((current) => ({
                        ...current,
                        page: 1,
                        operator_name: event.target.value,
                      }))
                    }
                    style={{ width: 220 }}
                  />
                  <Input
                    placeholder="模块"
                    value={operationFilters.module}
                    onChange={(event) =>
                      setOperationFilters((current) => ({
                        ...current,
                        page: 1,
                        module: event.target.value,
                      }))
                    }
                    style={{ width: 180 }}
                  />
                  <Select
                    allowClear
                    showSearch
                    placeholder="操作点"
                    value={operationFilters.action_point || undefined}
                    options={actionOptions}
                    optionFilterProp="label"
                    style={{ width: 280 }}
                    onChange={(value) =>
                      setOperationFilters((current) => ({
                        ...current,
                        page: 1,
                        action_point: value || '',
                      }))
                    }
                  />
                  <Button onClick={() => loadOperationLogs(operationFilters)}>重新加载</Button>
                </Space>

                <Table
                  rowKey="id"
                  loading={operationLoading}
                  columns={operationColumns}
                  dataSource={operationData.items}
                  pagination={{
                    current: operationData.page,
                    pageSize: operationData.page_size,
                    total: operationData.total,
                    showSizeChanger: false,
                    onChange: (page) =>
                      setOperationFilters((current) => ({
                        ...current,
                        page,
                      })),
                  }}
                  scroll={{ x: 980 }}
                />
              </Space>
            ),
          },
          {
            key: 'login',
            label: '登录日志',
            children: (
              <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <Space wrap>
                  <Input
                    placeholder="登录账号"
                    value={loginFilters.username}
                    onChange={(event) =>
                      setLoginFilters((current) => ({
                        ...current,
                        page: 1,
                        username: event.target.value,
                      }))
                    }
                    style={{ width: 220 }}
                  />
                  <Button onClick={() => loadLoginLogs(loginFilters)}>重新加载</Button>
                </Space>

                <Table
                  rowKey="id"
                  loading={loginLoading}
                  columns={loginColumns}
                  dataSource={loginData.items}
                  pagination={{
                    current: loginData.page,
                    pageSize: loginData.page_size,
                    total: loginData.total,
                    showSizeChanger: false,
                    onChange: (page) =>
                      setLoginFilters((current) => ({
                        ...current,
                        page,
                      })),
                  }}
                  scroll={{ x: 860 }}
                />
              </Space>
            ),
          },
        ]}
      />
    </Space>
  );
}
