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
  chat:
    '你是涵尊官网的烘焙设备销售助理。请根据客户问题给出简洁、专业、利于成交的回复。必须只返回 JSON，字段固定为：reply、intent_code、contains_contact_info、contact_name、company_name、email、phone、whatsapp、country_code、product_interest、solution_interest、requirement_summary。',
  'chat.rag':
    '你是涵尊国际业务销售助理。当系统附带知识库片段时，优先依据片段回答，不要编造价格、交期、电压、认证、运费、MOQ 或未出现的技术参数；如果资料不足，明确说明仍需哪些关键信息，并引导客户留下联系方式。必须只返回约定 JSON。',
  translation:
    '你是工业设备网站多语言翻译助手。请将中文内容准确翻译到目标语言，保留 HTML 标签、换行、字段结构、变量占位符、型号、单位、联系方式和品牌术语。必须只返回 JSON。',
  seo:
    '你是工业设备网站 SEO 编辑。请根据标题、摘要和正文生成精炼的 SEO 标题、关键词、描述和 slug，避免堆砌关键词，保证语义清晰。必须只返回 JSON。',
  cms_polish_content:
    '你是中文工业网站内容编辑。请在不新增事实、不改动关键信息的前提下，对正文进行结构与表达优化，保留原有 HTML 结构和技术语义。只返回润色后的正文内容。',
  cms_polish_summary:
    '你是中文工业网站内容编辑。请在不改变原意和事实的前提下，对摘要进行精炼润色，使其更自然、专业、面向客户。只返回润色后的文本。',
};

function normalizeModelItems(payload) {
  if (Array.isArray(payload?.items)) {
    return payload.items;
  }

  if (Array.isArray(payload)) {
    return payload;
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
    return '当前 API Key 无效，或未开通对应的 DashScope 模型服务。';
  }
  if (normalized.includes('401') || normalized.includes('unauthorized')) {
    return '接口返回 401，通常表示 API Key 无效或当前账号未授权。';
  }
  if (normalized.includes('403') || normalized.includes('forbidden')) {
    return '接口返回 403，当前账号没有访问该模型或接口的权限。';
  }
  if (normalized.includes('model') && normalized.includes('not found')) {
    return '模型名称不可用，请确认模型已开通且名称填写正确。';
  }
  if (normalized.includes('permission')) {
    return '当前账号没有访问该模型或接口的权限，请检查百炼控制台授权。';
  }
  if (normalized.includes('ssl certificate') || normalized.includes('issuer certificate')) {
    return '当前环境访问 DashScope 时 SSL 证书校验失败，请检查 PHP 或 cURL 证书链配置。';
  }
  if (normalized.includes('missing_api_key') || normalized.includes('api key missing')) {
    return '尚未配置 API Key，请先填写并保存。';
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
            message="按功能单独维护提示词"
            description="建议分别维护公开聊天、知识库增强、翻译、SEO、正文润色等提示词。保存后会立即影响对应能力的输出结果。"
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
  const [modelMeta, setModelMeta] = useState({
    source: 'unknown',
    fallbackReason: '',
    message: '',
    total: 0,
  });

  const modelOptions = useMemo(() => {
    const optionMap = new Map();

    models.forEach((item) => {
      const value = String(item.id || '').trim();
      if (!value) {
        return;
      }

      optionMap.set(value, {
        label: item.name ? `${item.name} · ${value}` : value,
        value,
      });
    });

    const currentModel = String(config.model || '').trim();
    if (currentModel && !optionMap.has(currentModel)) {
      optionMap.set(currentModel, {
        label: currentModel,
        value: currentModel,
      });
    }

    return Array.from(optionMap.values());
  }, [config.model, models]);

  const modelCountLabel = Math.max(modelMeta.total || 0, modelOptions.length);
  const modelSourceLabel = modelMeta.source === 'live' ? '实时模型列表' : '默认模型列表';
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
        prompt_cms_polish_content: getPromptInitialValue(
          nextConfig,
          'cms_polish',
          'polish_content',
        ),
        prompt_cms_polish_summary: getPromptInitialValue(
          nextConfig,
          'cms_polish',
          'polish_summary',
        ),
      });
    } catch (error) {
      message.error(error.message || '加载 AI 设置失败');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadPanel();
  }, []);

  function buildPayload(values, currentConfig = config) {
    const payload = {
      base_url: String(values.base_url || '').trim(),
      model: values.model,
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

    const apiKey = String(values.api_key || '').trim();
    if (apiKey) {
      payload.api_key = apiKey;
    } else if (currentConfig.api_key_masked) {
      payload.api_key_masked = currentConfig.api_key_masked;
    }

    return payload;
  }

  async function handleSubmit(values) {
    setSaving(true);
    try {
      const result = await updateAISettings(buildPayload(values));
      setConfig(normalizeAISettings(result));
      message.success('AI 设置已保存');
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
        message.error(friendlyMessage || 'AI 连接测试失败');
      } else {
        notification.success({
          message: '连接测试通过',
          description: friendlyMessage || '当前 API Key、模型和接口地址可正常使用。',
        });
      }

      await loadPanel();
    } catch (error) {
      message.error(normalizeTestMessage(error.message || '') || '测试 AI 连接失败');
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
          message: '模型列表获取成功',
          description: `当前共获取到 ${nextModels.length} 个可用模型。`,
        });
      } else {
        notification.warning({
          message: '当前使用默认模型列表',
          description:
            normalizeTestMessage(nextModelMeta.message) || '暂未获取到实时模型列表。',
        });
      }
    } catch (error) {
      message.error(normalizeTestMessage(error.message || '') || '获取模型列表失败');
    }
  }

  const connectionAlertType =
    connectionStatus === 'success' ? 'success' : connectionStatus === 'failed' ? 'error' : 'info';

  return (
    <Space direction="vertical" size={16} style={{ width: '100%' }}>
      <Row gutter={[16, 16]}>
        <Col xs={24} xl={15}>
          <Card variant="borderless" loading={loading} title="模型连接与运行参数">
            <Alert
              type={connectionAlertType}
              showIcon
              style={{ marginBottom: 16 }}
              message={normalizeTestMessage(config.connection_label || '') || '尚未执行连接测试'}
              description={
                config.has_api_key
                  ? `当前已保存 API Key：${config.api_key_masked || '已配置'}`
                  : '当前尚未保存 API Key，请先填写后再测试。'
              }
            />

            <Form form={form} layout="vertical" onFinish={handleSubmit}>
              <Row gutter={[16, 0]}>
                <Col xs={24} md={14}>
                  <Form.Item
                    name="base_url"
                    label="接口地址"
                    rules={[{ required: true, message: '请输入接口地址' }]}
                  >
                    <Input placeholder={DEFAULT_BASE_URL} />
                  </Form.Item>
                </Col>

                <Col xs={24} md={10}>
                  <Form.Item
                    name="model"
                    label="默认模型"
                    rules={[{ required: true, message: '请选择默认模型' }]}
                  >
                    <Select
                      showSearch
                      virtual={false}
                      listHeight={MODEL_LIST_HEIGHT}
                      style={{ width: '100%' }}
                      options={modelOptions}
                      placeholder={DEFAULT_MODEL}
                      optionFilterProp="label"
                      dropdownStyle={{ maxHeight: 520 }}
                      popupClassName="ai-model-dropdown-popup"
                      dropdownRender={(menu) => (
                        <div className="ai-model-dropdown-shell">
                          <div className="ai-model-dropdown-meta">
                            <div className="ai-model-dropdown-title">{`${modelSourceLabel} · 共 ${modelCountLabel} 个`}</div>
                          </div>
                          <div className="ai-model-dropdown-scroll">{menu}</div>
                        </div>
                      )}
                      filterOption={(inputValue, option) =>
                        String(option?.value || '')
                          .toLowerCase()
                          .includes(String(inputValue || '').toLowerCase()) ||
                        String(option?.label || '')
                          .toLowerCase()
                          .includes(String(inputValue || '').toLowerCase())
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
                    <Tag color="blue">{`模型数 ${displayedModelCount}`}</Tag>
                    {modelMeta.fallbackReason ? <Tag color="gold">{modelMeta.fallbackReason}</Tag> : null}
                  </Space>
                </Col>

                <Col xs={24}>
                  <Form.Item
                    name="api_key"
                    label="API Key"
                    extra={
                      config.api_key_masked
                        ? `当前已保存：${config.api_key_masked}。留空表示继续沿用现有密钥。`
                        : '请输入阿里云百炼 DashScope API Key。'
                    }
                  >
                    <Input.Password placeholder="请输入新的 API Key，留空则保持不变" />
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
            <Card variant="borderless" loading={loading} title="今日调用概览">
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
                  先完成 API Key、模型和超时参数配置，再测试连接并获取完整模型列表。
                </Text>
                <Text type="secondary">
                  如果切换到其他千问模型，建议先获取全部支持模型，再从下拉列表中搜索选择。
                </Text>
              </Space>
            </Card>
          </Space>
        </Col>
      </Row>
    </Space>
  );
}
