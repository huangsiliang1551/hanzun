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
      return '已停用';
    case 'preparing':
      return '准备中';
    default:
      return status || '未设置';
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
      message.error(error.message || '加载语言设置失败。');
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
      message.error('至少保留一种语言。');
      return;
    }

    const enabledItems = items.filter((item) => Boolean(item.is_enabled));
    if (enabledItems.length === 0) {
      message.error('至少启用一种语言。');
      return;
    }

    const defaultItems = items.filter((item) => Number(item.is_default || 0) === 1);
    if (defaultItems.length !== 1) {
      message.error('必须且只能有一个默认语言。');
      return;
    }

    const codes = items.map((item) => String(item.code || '').trim()).filter(Boolean);
    if (new Set(codes).size !== codes.length) {
      message.error('语言代码不能重复。');
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
      message.success('语言设置已更新。');
      await loadPanel();
    } catch (error) {
      message.error(error.message || '保存语言设置失败。');
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
            <Statistic title="已启用" value={Number(summary.enabled || 0)} />
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
            {(fields, { add, remove }) => (
              <Space direction="vertical" size={12} style={{ width: '100%' }}>
                {fields.map((field, index) => {
                  const row = form.getFieldValue(['items', index]) || {};
                  const translationStats = row.translation_stats || {};
                  const phraseSummary = row.phrase_summary || {};
                  const isDefault = Number(row.is_default || 0) === 1;
                  const currentCode = String(row.code || '').trim();

                  return (
                    <Card
                      key={field.key}
                      size="small"
                      title={row.name || row.english_name || `语言 ${index + 1}`}
                      extra={(
                        <Space wrap>
                          <Tag color={statusColor(row.status)}>{statusText(row.status)}</Tag>
                          <Button
                            type={isDefault ? 'primary' : 'default'}
                            size="small"
                            onClick={() => setDefaultIndex(index)}
                          >
                            {isDefault ? '默认语言' : '设为默认'}
                          </Button>
                          <Popconfirm
                            title="确认删除这条语言记录吗？"
                            okText="删除"
                            cancelText="取消"
                            onConfirm={() => remove(field.name)}
                            disabled={isDefault}
                          >
                            <Button size="small" danger disabled={isDefault}>
                              删除
                            </Button>
                          </Popconfirm>
                        </Space>
                      )}
                    >
                      <Row gutter={[12, 12]}>
                        <Col xs={24} xl={12}>
                          <Form.Item
                            {...field}
                            name={[field.name, 'preset_code']}
                            label="选择语言"
                            rules={[{ required: true, message: '请选择语言。' }]}
                          >
                            <Select
                              showSearch
                              placeholder="请选择语言"
                              optionFilterProp="label"
                              filterOption={filterLocaleOption}
                              options={languageOptions.filter(
                                (option) => option.value === currentCode || !usedCodes.includes(option.value),
                              )}
                              onChange={(value) => handlePresetChange(index, value)}
                            />
                          </Form.Item>
                        </Col>
                        <Col xs={24} xl={12}>
                          <Form.Item
                            {...field}
                            name={[field.name, 'name']}
                            label="前台显示名称"
                            rules={[{ required: true, message: '请输入前台显示名称。' }]}
                          >
                            <Input placeholder="建议使用该语言本地写法" />
                          </Form.Item>
                        </Col>
                        <Col xs={24} md={12} xl={8}>
                          <Form.Item
                            {...field}
                            name={[field.name, 'zh_name']}
                            label="中文备注"
                          >
                            <Input placeholder="方便后台识别" />
                          </Form.Item>
                        </Col>
                        <Col xs={24} md={12} xl={8}>
                          <Form.Item
                            {...field}
                            name={[field.name, 'english_name']}
                            label="英文名称"
                          >
                            <Input placeholder="English name" />
                          </Form.Item>
                        </Col>
                        <Col xs={12} md={8} xl={4}>
                          <Form.Item
                            {...field}
                            name={[field.name, 'sort']}
                            label="排序"
                          >
                            <InputNumber style={{ width: '100%' }} />
                          </Form.Item>
                        </Col>
                        <Col xs={12} md={8} xl={4}>
                          <Form.Item
                            {...field}
                            name={[field.name, 'is_enabled']}
                            label="启用"
                            valuePropName="checked"
                          >
                            <Switch checkedChildren="开" unCheckedChildren="关" disabled={isDefault} />
                          </Form.Item>
                        </Col>
                      </Row>

                      <Space wrap style={{ marginBottom: 12 }}>
                        <Tag color="blue">{currentCode ? currentCode.toUpperCase() : '未设置代码'}</Tag>
                        {isDefault ? <Tag color="gold">默认语言</Tag> : null}
                        <Tag>词条 {Number(phraseSummary.total || 0)}</Tag>
                        <Tag>内容任务 {Number(translationStats.total || 0)}</Tag>
                      </Space>

                      <Row gutter={[16, 12]}>
                        <Col xs={24} xl={12}>
                          <Space direction="vertical" size={4} style={{ width: '100%' }}>
                            <Text strong>固定文案完成度</Text>
                            <Progress
                              percent={Number(phraseSummary.completion_percent || 0)}
                              size="small"
                              status={row.status === 'paused' ? 'normal' : 'active'}
                            />
                            <Text type="secondary">
                              已完成 {Number(phraseSummary.completed || 0)} / {Number(phraseSummary.total || 0)}，
                              待处理 {Number(phraseSummary.pending || 0)}
                            </Text>
                          </Space>
                        </Col>
                        <Col xs={24} xl={12}>
                          <Space direction="vertical" size={4} style={{ width: '100%' }}>
                            <Text strong>内容翻译任务</Text>
                            <Progress
                              percent={Number(row.content_completion_percent || 0)}
                              size="small"
                              status={row.status === 'paused' ? 'normal' : 'active'}
                            />
                            <Text type="secondary">
                              已完成 {Number(translationStats.completed || 0)}，
                              待处理 {Number(translationStats.pending || 0) + Number(translationStats.review_required || 0)}，
                              失败 {Number(translationStats.failed || 0)}
                            </Text>
                          </Space>
                        </Col>
                      </Row>
                    </Card>
                  );
                })}

                <Space wrap>
                  <Button onClick={() => add(createEmptyLanguage((watchedItems?.length || 0) + 1))}>新增语言</Button>
                  <Button onClick={loadPanel}>重新加载</Button>
                  <Button type="primary" htmlType="submit" loading={saving}>
                    保存语言设置
                  </Button>
                </Space>
              </Space>
            )}
          </Form.List>
        </Form>
      </Card>
    </Space>
  );
}
