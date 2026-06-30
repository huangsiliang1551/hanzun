import { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  App,
  Button,
  Card,
  Col,
  Collapse,
  Form,
  Input,
  InputNumber,
  List,
  Row,
  Select,
  Space,
  Switch,
  Tag,
  Typography,
} from 'antd';
import {
  getAIBootstrap,
  getAIModels,
  normalizeAILogs,
  normalizeAISettings,
  testAISettingsConnection,
  updateAISettings,
} from '@/api/settingsAdmin';

const { Paragraph, Text, Title } = Typography;
const { TextArea } = Input;

const DEFAULT_BASE_URL = 'https://dashscope.aliyuncs.com/compatible-mode/v1';
const DEFAULT_MODEL = 'qwen-plus';
const MODEL_LIST_HEIGHT = 320;
const DEFAULT_TIMEOUT_SECONDS = 180;

const DEFAULT_PROMPTS = {
  chat: '你是制造业企业网站的 AI 助手。请用清晰、准确、专业的方式回答用户问题。',
  'chat.rag': '你是带知识库增强的 AI 助手。请优先参考已索引资料，并尽量基于现有上下文回答。',
  translation: '请高准确度地完成翻译，保留原意、语气、产品术语和专有名词。',
  seo: '请在保留原意、可读性和转化意图的前提下，优化网站内容的 SEO 表现。',
  cms_polish_content: '请在不改变原意的前提下，优化正文内容的表达、语法、结构和可读性。',
  cms_polish_summary: '请为当前内容生成一段简洁、自然的摘要。',
};

function getModelValue(rawValue) {
  if (typeof rawValue === 'object' && rawValue !== null) {
    return String(rawValue.id || rawValue.value || '').trim();
  }

  return String(rawValue || '').trim();
}

function dedupeModels(payload) {
  if (!Array.isArray(payload)) {
    return [];
  }

  const modelMap = new Map();

  payload.forEach((item) => {
    const value = getModelValue(item?.id ?? item);
    if (!value) {
      return;
    }

    const key = value.toLowerCase();
    if (modelMap.has(key)) {
      return;
    }

    modelMap.set(key, item);
  });

  return Array.from(modelMap.values());
}

function normalizeModelItems(payload) {
  if (Array.isArray(payload?.items)) {
    return dedupeModels(payload.items);
  }

  if (Array.isArray(payload)) {
    return dedupeModels(payload);
  }

  return [];
}

function normalizeModelMeta(payload) {
  return {
    source: String(payload?.source || '').trim() || 'unknown',
    fallbackReason: String(payload?.fallback_reason || '').trim(),
    message: String(payload?.message || '').trim(),
    total: Number(payload?.total || 0),
  };
}

function normalizeTestMessage(rawMessage = '') {
  const messageText = String(rawMessage || '').trim();
  const normalized = messageText.toLowerCase();

  if (!messageText) {
    return '';
  }

  if (normalized.includes('invalid_api_key') || normalized.includes('incorrect api key')) {
    return 'API Key 无效，请检查密钥内容和服务权限。';
  }
  if (normalized.includes('401') || normalized.includes('unauthorized')) {
    return '接口返回 401，通常表示 API Key 无效、已过期，或当前服务不可用。';
  }
  if (normalized.includes('403') || normalized.includes('forbidden')) {
    return '接口返回 403，当前 API Key 没有访问该能力的权限。';
  }
  if (normalized.includes('model') && normalized.includes('not found')) {
    return '模型不存在，请从模型列表中选择可用模型。';
  }
  if (normalized.includes('permission')) {
    return '权限不足，请确认 API Key 的授权范围和账号访问策略。';
  }
  if (normalized.includes('ssl certificate') || normalized.includes('issuer certificate')) {
    return '连接 AI 服务时 SSL 证书校验失败。';
  }
  if (normalized.includes('missing_api_key') || normalized.includes('api key missing')) {
    return '尚未填写 API Key，请先填写后再测试或保存。';
  }

  return messageText;
}

function getPromptValue(config, feature, key = 'system') {
  return String(config?.prompts?.[feature]?.[key] || '');
}

function getPromptInitialValue(config, feature, key = 'system') {
  const currentValue = getPromptValue(config, feature, key);
  if (currentValue) {
    return currentValue;
  }

  if (feature === 'cms_polish' && key === 'polish_content') {
    return DEFAULT_PROMPTS.cms_polish_content;
  }
  if (feature === 'cms_polish' && key === 'polish_summary') {
    return DEFAULT_PROMPTS.cms_polish_summary;
  }

  return DEFAULT_PROMPTS[feature] || '';
}

function buildPromptPayload(values) {
  return {
    chat: { system: String(values.prompt_chat_system || '').trim() },
    'chat.rag': { system: String(values.prompt_chat_rag_system || '').trim() },
    translation: { system: String(values.prompt_translation_system || '').trim() },
    seo: { system: String(values.prompt_seo_system || '').trim() },
    cms_polish: {
      polish_content: String(values.prompt_cms_polish_content || '').trim(),
      polish_summary: String(values.prompt_cms_polish_summary || '').trim(),
    },
  };
}

function buildPromptItems() {
  return [
    {
      key: 'prompts',
      label: '提示词设置',
      children: (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <Alert
            type="info"
            showIcon
            message="AI 提示词模板"
            description="这里用于控制聊天、知识库增强、翻译、SEO 和内容润色等能力的输出风格。"
          />
          <Row gutter={[16, 0]}>
            <Col xs={24}>
              <Form.Item name="prompt_chat_system" label="公开聊天主提示词">
                <TextArea rows={6} placeholder="请输入公开聊天主提示词" />
              </Form.Item>
            </Col>
            <Col xs={24}>
              <Form.Item name="prompt_chat_rag_system" label="知识库增强提示词">
                <TextArea rows={6} placeholder="请输入知识库增强提示词" />
              </Form.Item>
            </Col>
            <Col xs={24}>
              <Form.Item name="prompt_translation_system" label="翻译提示词">
                <TextArea rows={5} placeholder="请输入翻译提示词" />
              </Form.Item>
            </Col>
            <Col xs={24}>
              <Form.Item name="prompt_seo_system" label="SEO 生成提示词">
                <TextArea rows={5} placeholder="请输入 SEO 生成提示词" />
              </Form.Item>
            </Col>
            <Col xs={24} md={12}>
              <Form.Item name="prompt_cms_polish_summary" label="摘要润色提示词">
                <TextArea rows={4} placeholder="请输入摘要润色提示词" />
              </Form.Item>
            </Col>
            <Col xs={24} md={12}>
              <Form.Item name="prompt_cms_polish_content" label="正文润色提示词">
                <TextArea rows={4} placeholder="请输入正文润色提示词" />
              </Form.Item>
            </Col>
          </Row>
        </Space>
      ),
    },
  ];
}

function getConnectionStatusTag(status) {
  const normalized = String(status || '').trim().toLowerCase();
  if (normalized === 'success') {
    return <Tag color="success">连接正常</Tag>;
  }
  if (normalized === 'failed') {
    return <Tag color="error">最近测试失败</Tag>;
  }
  if (normalized) {
    return <Tag color="processing">{normalized}</Tag>;
  }

  return <Tag>尚未测试</Tag>;
}

export default function AIPanel() {
  const { message, notification } = App.useApp();
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [testing, setTesting] = useState(false);
  const [models, setModels] = useState([]);
  const [logs, setLogs] = useState([]);
  const [summary, setSummary] = useState({});
  const [balanceInfo, setBalanceInfo] = useState(null);
  const [config, setConfig] = useState({});
  const [hasEditedApiKey, setHasEditedApiKey] = useState(false);
  const [hasEditedModel, setHasEditedModel] = useState(false);
  const [modelMeta, setModelMeta] = useState({
    source: 'unknown',
    fallbackReason: '',
    message: '',
    total: 0,
  });

  const modelOptions = useMemo(() => {
    const optionMap = new Map();

    models.forEach((item) => {
      const value = getModelValue(item);
      if (!value) {
        return;
      }

      const key = value.toLowerCase();
      if (optionMap.has(key)) {
        return;
      }

      optionMap.set(key, {
        label: item.name ? `${item.name} (${value})` : value,
        value,
      });
    });

    const currentModel = getModelValue(config.model);
    const currentModelKey = currentModel.toLowerCase();
    if (currentModel && !optionMap.has(currentModelKey)) {
      optionMap.set(currentModelKey, {
        label: currentModel,
        value: currentModel,
      });
    }

    return Array.from(optionMap.values());
  }, [config.model, models]);

  const modelSourceLabel = modelMeta.source === 'live' ? '实时来源' : '默认来源';
  const displayedModelCount = models.length > 0 ? models.length : modelOptions.length;
  const connectionStatus = String(config.last_test_status || '').trim();

  async function loadPanel() {
    setLoading(true);
    try {
      const bootstrapPayload = (await getAIBootstrap()) || {};
      const nextConfig = normalizeAISettings({ config: bootstrapPayload.config || {} });
      const normalizedLogs = normalizeAILogs(bootstrapPayload.logs || { items: [], summary: {} });

      setConfig(nextConfig);
      setModels(normalizeModelItems(bootstrapPayload.models || []));
      setLogs(normalizedLogs.items.slice(0, 8));
      setSummary(normalizedLogs.summary || {});
      setBalanceInfo(bootstrapPayload.balance || null);
      setModelMeta(normalizeModelMeta(bootstrapPayload.models || {}));

      form.setFieldsValue({
        base_url: nextConfig.base_url || DEFAULT_BASE_URL,
        model: nextConfig.model || DEFAULT_MODEL,
        api_key: '',
        timeout_seconds: Number(nextConfig.timeout_seconds || DEFAULT_TIMEOUT_SECONDS),
        retry_times: Number(nextConfig.retry_times || 2),
        chat_enabled: Number(nextConfig.chat_enabled || 0) === 1,
        translation_enabled: Number(nextConfig.translation_enabled || 0) === 1,
        seo_enabled: Number(nextConfig.seo_enabled || 0) === 1,
        knowledge_enabled: Number(nextConfig.knowledge_enabled || 0) === 1,
        knowledge_top_k: Number(nextConfig.knowledge_top_k || 5),
        knowledge_max_chars: Number(nextConfig.knowledge_max_chars || 128000),
        knowledge_auto_sync_cms: Number(nextConfig.knowledge_auto_sync_cms || 0) === 1,
        chat_max_history_messages: Number(nextConfig.chat_max_history_messages || 6),
        prompt_chat_system: getPromptInitialValue(nextConfig, 'chat'),
        prompt_chat_rag_system: getPromptInitialValue(nextConfig, 'chat.rag'),
        prompt_translation_system: getPromptInitialValue(nextConfig, 'translation'),
        prompt_seo_system: getPromptInitialValue(nextConfig, 'seo'),
        prompt_cms_polish_content: getPromptInitialValue(nextConfig, 'cms_polish', 'polish_content'),
        prompt_cms_polish_summary: getPromptInitialValue(nextConfig, 'cms_polish', 'polish_summary'),
      });
      setHasEditedApiKey(false);
      setHasEditedModel(false);
    } catch (error) {
      message.error(error.message || '加载 AI 设置失败。');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadPanel();
  }, []);

  function buildPayload(values, currentConfig = config) {
    const shouldUseRawApiKey = hasEditedApiKey || Boolean(form?.isFieldTouched?.('api_key'));
    const shouldUseModel = hasEditedModel || Boolean(form?.isFieldTouched?.('model'));
    const rawApiKey = String(values.api_key || '').trim();

    const payload = {
      base_url: String(values.base_url || '').trim(),
      model: shouldUseModel
        ? String(values.model || '').trim() || String(currentConfig.model || '').trim() || DEFAULT_MODEL
        : String(currentConfig.model || '').trim() || DEFAULT_MODEL,
      timeout_seconds: Number(values.timeout_seconds || DEFAULT_TIMEOUT_SECONDS),
      retry_times: Number(values.retry_times || 0),
      chat_enabled: values.chat_enabled ? 1 : 0,
      translation_enabled: values.translation_enabled ? 1 : 0,
      seo_enabled: values.seo_enabled ? 1 : 0,
      knowledge_enabled: values.knowledge_enabled ? 1 : 0,
      knowledge_top_k: Number(values.knowledge_top_k || 5),
      knowledge_max_chars: Number(values.knowledge_max_chars || 128000),
      knowledge_auto_sync_cms: values.knowledge_auto_sync_cms ? 1 : 0,
      chat_max_history_messages: Number(values.chat_max_history_messages || 6),
      prompts: buildPromptPayload(values),
    };

    if (shouldUseRawApiKey && rawApiKey) {
      payload.api_key = rawApiKey;
    } else if (currentConfig.api_key_masked) {
      payload.api_key_masked = currentConfig.api_key_masked;
    }

    return payload;
  }

  function handleApiKeyChange() {
    setHasEditedApiKey(true);
  }

  async function handleSubmit(values) {
    setSaving(true);
    try {
      const result = await updateAISettings(buildPayload(values));
      setConfig(normalizeAISettings(result));
      message.success('AI 设置已保存');
      setHasEditedApiKey(false);
      setHasEditedModel(false);
      await loadPanel();
    } catch (error) {
      message.error(error.message || '保存 AI 设置失败');
    } finally {
      setSaving(false);
    }
  }

  async function handleTest() {
    setTesting(true);
    try {
      const values = await form.validateFields();
      const result = await testAISettingsConnection(buildPayload(values));

      if (result?.config) {
        setConfig(normalizeAISettings(result));
      }

      const friendlyMessage = normalizeTestMessage(result?.message || '');
      if (result?.status === 'failed') {
        message.error(friendlyMessage || 'AI 连接测试失败。');
      } else {
        notification.success({
          message: 'AI 连接测试成功',
          description: friendlyMessage || 'AI 服务可正常访问，当前配置看起来有效。',
        });
      }

      await loadPanel();
    } catch (error) {
      message.error(normalizeTestMessage(error.message || '') || 'AI 连接测试失败。');
    } finally {
      setTesting(false);
    }
  }

  async function handleFetchModels() {
    try {
      const result = await getAIModels();
      const nextModels = normalizeModelItems(result);
      const nextModelMeta = normalizeModelMeta(result);

      setModels(nextModels);
      setModelMeta(nextModelMeta);

      if (nextModelMeta.source === 'live') {
        notification.success({
          message: '模型列表已刷新',
          description: `已从实时服务获取 ${nextModels.length} 个模型。`,
        });
      } else {
        notification.warning({
          message: '当前使用默认模型列表',
          description:
            normalizeTestMessage(nextModelMeta.message) ||
            '暂时无法获取实时模型列表，因此正在使用默认数据。',
        });
      }
    } catch (error) {
      message.error(normalizeTestMessage(error.message || '') || '获取模型列表失败。');
    }
  }

  return (
    <Space direction="vertical" size={16} style={{ display: 'flex' }}>
      <Row gutter={[16, 16]}>
        <Col xs={24} xl={15}>
          <Card
            variant="borderless"
            loading={loading}
            title="智能设置"
            extra={getConnectionStatusTag(connectionStatus)}
          >
            <Form form={form} layout="vertical" onFinish={handleSubmit}>
              <Row gutter={[16, 0]}>
                <Col xs={24} md={12}>
                  <Form.Item
                    name="base_url"
                    label="接口地址"
                    extra="默认使用阿里云百炼 OpenAI 兼容地址，也可以切换到你自己的兼容服务。"
                  >
                    <Input placeholder="请输入接口地址" autoComplete="off" />
                  </Form.Item>
                </Col>

                <Col xs={24} md={12}>
                  <Form.Item
                    name="model"
                    label="默认模型"
                    extra={`当前模型来源：${modelSourceLabel}`}
                  >
                    <Select
                      showSearch
                      listHeight={MODEL_LIST_HEIGHT}
                      options={modelOptions}
                      placeholder="请选择默认模型"
                      optionFilterProp="label"
                      autoComplete="off"
                      onChange={() => setHasEditedModel(true)}
                      filterOption={(input, option) =>
                        String(option?.label || '')
                          .toLowerCase()
                          .includes(String(input || '').toLowerCase())
                      }
                    />
                  </Form.Item>
                </Col>

                <Col xs={24}>
                  <Space wrap style={{ marginBottom: 16 }}>
                    <Button onClick={handleFetchModels}>获取全部支持模型</Button>
                    <Button onClick={handleTest} loading={testing}>
                      测试连接
                    </Button>
                    <Tag color="blue">{`模型数：${displayedModelCount}`}</Tag>
                    {modelMeta.fallbackReason ? <Tag color="gold">{modelMeta.fallbackReason}</Tag> : null}
                  </Space>
                </Col>

                <Col xs={24}>
                  <Form.Item
                    name="api_key"
                    label="API Key"
                    extra={
                      config.api_key_masked
                        ? `当前服务器已保存：${config.api_key_masked}。如果不想更换密钥，保持留空即可。`
                        : '请输入阿里云百炼 DashScope API Key。该字段已规避浏览器自动填充。'
                    }
                  >
                    <Input
                      type="text"
                      autoComplete="new-password"
                      name="ai_api_key_field"
                      placeholder="请输入 API Key"
                      readOnly
                      onChange={handleApiKeyChange}
                      onFocus={(event) => event.currentTarget.removeAttribute('readonly')}
                    />
                  </Form.Item>
                </Col>

                <Col xs={24} md={8}>
                  <Form.Item name="timeout_seconds" label="超时时间（秒）">
                    <InputNumber min={5} max={300} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>

                <Col xs={24} md={8}>
                  <Form.Item name="retry_times" label="重试次数">
                    <InputNumber min={0} max={10} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>

                <Col xs={24} md={8}>
                  <Form.Item name="chat_max_history_messages" label="对话上下文条数">
                    <InputNumber min={0} max={30} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>

                <Col xs={24} md={12}>
                  <Form.Item name="knowledge_top_k" label="知识库召回条数">
                    <InputNumber min={1} max={20} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>

                <Col xs={24} md={12}>
                  <Form.Item name="knowledge_max_chars" label="知识库最大拼接字数">
                    <InputNumber min={500} max={128000} style={{ width: '100%' }} />
                  </Form.Item>
                </Col>
              </Row>

              <Space wrap size="large" style={{ marginBottom: 16 }}>
                <Form.Item name="chat_enabled" valuePropName="checked" label="AI 对话" style={{ marginBottom: 0 }}>
                  <Switch />
                </Form.Item>
                <Form.Item name="translation_enabled" valuePropName="checked" label="AI 翻译" style={{ marginBottom: 0 }}>
                  <Switch />
                </Form.Item>
                <Form.Item name="seo_enabled" valuePropName="checked" label="AI SEO" style={{ marginBottom: 0 }}>
                  <Switch />
                </Form.Item>
                <Form.Item name="knowledge_enabled" valuePropName="checked" label="知识库召回" style={{ marginBottom: 0 }}>
                  <Switch />
                </Form.Item>
                <Form.Item
                  name="knowledge_auto_sync_cms"
                  valuePropName="checked"
                  label="自动同步 CMS 到知识库"
                  style={{ marginBottom: 0 }}
                >
                  <Switch />
                </Form.Item>
              </Space>

              <Collapse defaultActiveKey={['prompts']} style={{ marginBottom: 16 }} items={buildPromptItems()} />

              <Space wrap>
                <Button onClick={loadPanel}>重新加载</Button>
                <Button type="primary" htmlType="submit" loading={saving}>
                  保存设置
                </Button>
              </Space>
            </Form>
          </Card>
        </Col>

        <Col xs={24} xl={9}>
          <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card variant="borderless" loading={loading} title="今日请求概览">
              <Space wrap>
                <Tag color="blue">{`总请求 ${Number(summary.today_total || 0)}`}</Tag>
                <Tag color="green">{`对话 ${Number(summary.today_chat || 0)}`}</Tag>
                <Tag color="purple">{`SEO ${Number(summary.today_seo || 0)}`}</Tag>
                <Tag color="gold">{`翻译 ${Number(summary.today_translation || 0)}`}</Tag>
                <Tag color="red">{`失败 ${Number(summary.failed_count || 0)}`}</Tag>
              </Space>

              {balanceInfo ? (
                <Alert
                  style={{ marginTop: 16 }}
                  type="warning"
                  showIcon
                  message={
                    normalizeTestMessage(balanceInfo.message || '') ||
                    '已返回账户余额或计费相关信息。'
                  }
                />
              ) : null}

              <div style={{ marginTop: 16 }}>
                <Paragraph type="secondary" style={{ marginBottom: 8 }}>
                  {`当前默认模型：${String(config.model || DEFAULT_MODEL)}`}
                </Paragraph>
                <Paragraph type="secondary" style={{ marginBottom: 0 }}>
                  {`当前接口地址：${String(config.base_url || DEFAULT_BASE_URL)}`}
                </Paragraph>
              </div>
            </Card>

            <Card variant="borderless" loading={loading} title="最近请求日志">
              <List
                dataSource={logs}
                locale={{ emptyText: '暂无 AI 请求日志。' }}
                renderItem={(item) => (
                  <List.Item>
                    <List.Item.Meta
                      title={
                        <Space wrap>
                          <Text strong>{item.feature_name || item.feature_code || 'AI'}</Text>
                          {Number(item.is_success) === 1 ? (
                            <Tag color="success">成功</Tag>
                          ) : (
                            <Tag color="error">失败</Tag>
                          )}
                        </Space>
                      }
                      description={
                        <Space direction="vertical" size={0}>
                          <Text type="secondary">{`模型：${item.model || '-'}`}</Text>
                          <Text type="secondary">{`耗时：${Number(item.duration_ms || 0)} ms`}</Text>
                          <Text type="secondary">{`时间：${item.created_at || '-'}`}</Text>
                          {item.error_message ? (
                            <Text type="danger">{normalizeTestMessage(item.error_message)}</Text>
                          ) : null}
                        </Space>
                      }
                    />
                  </List.Item>
                )}
              />
            </Card>

            <Card variant="borderless" loading={loading} title="使用建议">
              <Space direction="vertical" size={8} style={{ width: '100%' }}>
                <Title level={5} style={{ margin: 0 }}>
                  推荐配置顺序
                </Title>
                <Text type="secondary">
                  建议先完成 API Key、模型和超时参数配置，再执行连接测试并获取完整模型列表。
                </Text>
                <Text type="secondary">
                  如果切换到其他模型，建议先获取全部支持模型，再从下拉列表中搜索选择。
                </Text>
              </Space>
            </Card>
          </Space>
        </Col>
      </Row>
    </Space>
  );
}
