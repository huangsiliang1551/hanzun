import { useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  Col,
  Form,
  Input,
  InputNumber,
  Popconfirm,
  Progress,
  Row,
  Table,
  Select,
  Space,
  Statistic,
  Switch,
  Tag,
  Typography,
  message,
} from 'antd';
import { getLanguageSettings, normalizeLanguagePayload, updateLanguageSettings } from '@/api/settingsAdmin';
import { buildLanguageOptions, filterLocaleOption, getLanguageMeta } from '@/utils/localeMeta';

const { Text } = Typography;

function createEmptyLanguage(sort = 0) {
  return {
    id: undefined,
    code: '',
    preset_code: undefined,
    name: '',
    zh_name: '',
    english_name: '',
    is_default: 0,
    is_enabled: true,
    sort,
    status: 'preparing',
    translation_stats: {
      completed: 0,
      pending: 0,
      failed: 0,
      review_required: 0,
      total: 0,
    },
    phrase_summary: {
      total: 0,
      completed: 0,
      pending: 0,
      completion_percent: 0,
    },
  };
}

function statusColor(status) {
  switch (status) {
    case 'ready':
      return 'success';
    case 'paused':
      return 'default';
    case 'preparing':
      return 'processing';
    default:
      return 'blue';
  }
}

function statusText(status) {
  switch (status) {
    case 'ready':
      return '已就绪';
    case 'paused':
      return '已暂停';
    case 'preparing':
      return '准备中';
    default:
      return status || '未知';
  }
}

export default function LanguagesPanel() {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [summary, setSummary] = useState({
    total: 0,
    enabled: 0,
    paused: 0,
    preparing: 0,
    ready: 0,
  });
  const watchedItems = Form.useWatch('items', form) || [];

  const usedCodes = useMemo(
    () => watchedItems.map((item) => String(item?.code || '').trim()).filter(Boolean),
    [watchedItems],
  );
  const languageOptions = useMemo(() => buildLanguageOptions(watchedItems), [watchedItems]);

  async function loadPanel() {
    setLoading(true);
    try {
      const result = normalizeLanguagePayload(await getLanguageSettings());
      setSummary(result.summary || {});
      const items = Array.isArray(result.items) && result.items.length > 0
        ? result.items.map((item, index) => {
            const meta = getLanguageMeta(item.code, result.items);
            return {
              ...item,
              preset_code: item.code || undefined,
              name: String(item.name || meta.nativeName || '').trim(),
              zh_name: String(item.zh_name || meta.zhName || '').trim(),
              english_name: String(item.english_name || meta.englishName || '').trim(),
              is_enabled: Number(item.is_enabled || 0) === 1,
              sort: Number(item.sort ?? result.items.length - index),
            };
          })
        : [createEmptyLanguage()];
      form.setFieldsValue({ items });
    } catch (error) {
      message.error(error.message || '加载语言设置失败');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadPanel();
  }, []);

  function setDefaultIndex(targetIndex) {
    const items = form.getFieldValue('items') || [];
    form.setFieldValue(
      'items',
      items.map((item, index) => ({
        ...item,
        is_default: index === targetIndex ? 1 : 0,
        is_enabled: index === targetIndex ? true : Boolean(item.is_enabled),
      })),
    );
  }

  function handlePresetChange(index, code) {
    const items = form.getFieldValue('items') || [];
    const meta = getLanguageMeta(code, items);
    form.setFieldValue(
      'items',
      items.map((item, rowIndex) => {
        if (rowIndex !== index) {
          return item;
        }

        return {
          ...item,
          preset_code: code,
          code,
          name: String(item.name || '').trim() || meta.nativeName,
          zh_name: String(item.zh_name || '').trim() || meta.zhName,
          english_name: String(item.english_name || '').trim() || meta.englishName,
        };
      }),
    );
  }

  async function handleSubmit(values) {
    const items = Array.isArray(values.items) ? values.items : [];
    if (items.length === 0) {
      message.error('至少需要保留一条语言配置');
      return;
    }

    const enabledItems = items.filter((item) => Boolean(item.is_enabled));
    if (enabledItems.length === 0) {
      message.error('至少需要启用一种语言');
      return;
    }

    const defaultItems = items.filter((item) => Number(item.is_default || 0) === 1);
    if (defaultItems.length !== 1) {
      message.error('必须且只能设置一种默认语言');
      return;
    }

    const codes = items.map((item) => String(item.code || '').trim()).filter(Boolean);
    if (new Set(codes).size !== codes.length) {
      message.error('语言代码不能重复');
      return;
    }

    setSaving(true);
    try {
      await updateLanguageSettings({
        items: items.map((item, index) => ({
          id: item.id,
          code: String(item.code || '').trim(),
          name: String(item.name || '').trim(),
          zh_name: String(item.zh_name || '').trim(),
          english_name: String(item.english_name || '').trim(),
          is_default: Number(item.is_default || 0) === 1 ? 1 : 0,
          is_enabled: item.is_enabled ? 1 : 0,
          sort: Number(item.sort ?? items.length - index),
        })),
      });
      message.success('语言设置已保存');
      await loadPanel();
    } catch (error) {
      message.error(error.message || '保存语言设置失败');
    } finally {
      setSaving(false);
    }
  }

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Row gutter={[12, 12]}>
        <Col xs={12} xl={6}>
          <Card className="stat-card" bordered={false} size="small" loading={loading}>
            <Statistic title="语言总数" value={Number(summary.total || 0)} />
          </Card>
        </Col>
        <Col xs={12} xl={6}>
          <Card className="stat-card" bordered={false} size="small" loading={loading}>
            <Statistic title="启用语言" value={Number(summary.enabled || 0)} />
          </Card>
        </Col>
        <Col xs={12} xl={6}>
          <Card className="stat-card" bordered={false} size="small" loading={loading}>
            <Statistic title="准备中" value={Number(summary.preparing || 0)} />
          </Card>
        </Col>
        <Col xs={12} xl={6}>
          <Card className="stat-card" bordered={false} size="small" loading={loading}>
            <Statistic title="已就绪" value={Number(summary.ready || 0)} />
          </Card>
        </Col>
      </Row>

      <Card bordered={false} loading={loading}>
        <Form form={form} layout="vertical" onFinish={handleSubmit}>
                    <Form.List name="items">
            {(fields, { add, remove }) => {
              const listData = fields.map((field, index) => ({
                ...field,
                index,
                row: watchedItems[index] || {},
              }));

              const columns = [
                {
                  title: '语言',
                  width: 220,
                  fixed: 'left',
                  render: (_value, record) => {
                    const currentCode = String((record.row?.code || '').trim());
                    return (
                      <Form.Item
                        name={[record.name, 'preset_code']}
                        rules={[{ required: true, message: '请选择语言' }]}
                        noStyle
                      >
                        <Select
                          showSearch
                          size="small"
                          placeholder="请选择语言"
                          optionFilterProp="label"
                          filterOption={filterLocaleOption}
                          options={languageOptions.filter(
                            (option) => option.value === currentCode || !usedCodes.includes(option.value),
                          )}
                          onChange={(value) => handlePresetChange(record.index, value)}
                        />
                      </Form.Item>
                    );
                  },
                },
                {
                  title: '显示名称',
                  width: 110,
                  render: (_value, record) => (
                    <Form.Item
                      name={[record.name, 'name']}
                      rules={[{ required: true, message: '请输入显示名称' }]}
                      noStyle
                    >
                      <Input size="small" placeholder="本地名称" />
                    </Form.Item>
                  ),
                },
                {
                  title: '中文备注',
                  width: 90,
                  render: (_value, record) => (
                    <Form.Item name={[record.name, 'zh_name']} noStyle>
                      <Input size="small" placeholder="中文备注" />
                    </Form.Item>
                  ),
                },
                {
                  title: '英文名称',
                  width: 90,
                  render: (_value, record) => (
                    <Form.Item name={[record.name, 'english_name']} noStyle>
                      <Input size="small" placeholder="English name" />
                    </Form.Item>
                  ),
                },
                {
                  title: '排序',
                  width: 50,
                  render: (_value, record) => (
                    <Form.Item name={[record.name, 'sort']} noStyle>
                      <InputNumber style={{ width: '100%' }} size="small" />
                    </Form.Item>
                  ),
                },
                {
                  title: '启用',
                  width: 90,
                  render: (_value, record) => {
                    const isDefault = Number((record.row?.is_default || 0)) === 1;
                    return (
                      <Form.Item
                        name={[record.name, 'is_enabled']}
                        valuePropName="checked"
                        noStyle
                      >
                        <Switch checkedChildren="开" unCheckedChildren="关" disabled={isDefault} />
                      </Form.Item>
                    );
                  },
                },
                {
                  title: '状态',
                  width: 240,
                  render: (_value, record) => {
                    const row = record.row || {};
                    const phraseSummary = row.phrase_summary || {};
                    return (
                      <Space direction="vertical" size={4} style={{ width: '100%' }}>
                        <Space wrap size={4}>
                          <Tag color={statusColor(row.status)}>{statusText(row.status)}</Tag>
                          {Number((row.is_default || 0)) === 1 ? <Tag color="gold">默认语言</Tag> : null}
                          <Tag>{String(row.code || '').toUpperCase() || 'N/A'}</Tag>
                        </Space>
                        <Progress
                          percent={Number(phraseSummary.completion_percent || 0)}
                          size="small"
                          status={row.status === 'paused' ? 'normal' : 'active'}
                        />
                        <Text type="secondary" style={{ fontSize: 12 }}>
                          已译词条 {Number(phraseSummary.completed || 0)} / {Number(phraseSummary.total || 0)}
                        </Text>
                      </Space>
                    );
                  },
                },
                {
                  title: '操作',
                  width: 150,
                  fixed: 'right',
                  render: (_value, record) => {
                    const isDefault = Number((record.row?.is_default || 0)) === 1;
                    return (
                      <Space wrap size={4}>
                        <Button
                          type={isDefault ? 'primary' : 'default'}
                          size="small"
                          onClick={() => setDefaultIndex(record.index)}
                        >
                          {isDefault ? '默认语言' : '设为默认'}
                        </Button>
                        <Popconfirm
                          title="确认删除该条记录吗？"
                          okText="删除"
                          cancelText="取消"
                          onConfirm={() => remove(record.name)}
                          disabled={isDefault}
                        >
                          <Button size="small" danger disabled={isDefault}>
                            删除
                          </Button>
                        </Popconfirm>
                      </Space>
                    );
                  },
                },
              ];

              return (
                <>
                  <Table
                    className="language-table-compact"
                    dataSource={listData}
                    columns={columns}
                    rowKey="key"
                    size="small"
                    bordered
                    scroll={{ x: 1280 }}
                    rowClassName={(_, index) => (index % 2 === 0 ? 'language-table-compact-even' : 'language-table-compact-odd')}
                    pagination={false}
                  />
                  <Space style={{ marginTop: 12 }} wrap>
                    <Button onClick={() => add(createEmptyLanguage((watchedItems?.length || 0) + 1))}>新增语言</Button>
                    <Button onClick={loadPanel}>重新加载</Button>
                    <Button type="primary" htmlType="submit" loading={saving}>
                      保存语言设置
                    </Button>
                  </Space>
                </>
              );
            }}
          </Form.List>
        </Form>
      </Card>
    </Space>
  );
}

