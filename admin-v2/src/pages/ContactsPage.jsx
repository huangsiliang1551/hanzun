import { useEffect, useMemo, useState } from 'react';
import {
  Alert,
  Button,
  Card,
  Form,
  Input,
  InputNumber,
  Modal,
  Popconfirm,
  Select,
  Space,
  Switch,
  Table,
  Tag,
  Typography,
  message,
} from 'antd';
import PagePlaceholder from '@/components/PagePlaceholder';
import {
  createContactFieldType,
  createContactItem,
  deleteContactFieldType,
  deleteContactItem,
  getContactCenterData,
  getContactItemDetail,
  updateContactFieldType,
  updateContactItem,
} from '@/api/contactCenter';

const { Text, Paragraph } = Typography;
const { TextArea } = Input;

const FIELD_TYPE_TEMPLATES = [
  { key: 'email', label: '邮箱', field_key: 'email', name_zh: '邮箱', icon: 'mail', validation_rule: 'email' },
  { key: 'phone', label: '电话', field_key: 'phone', name_zh: '电话', icon: 'phone', validation_rule: 'phone' },
  {
    key: 'whatsapp',
    label: 'WhatsApp',
    field_key: 'whatsapp',
    name_zh: 'WhatsApp',
    icon: 'message',
    validation_rule: 'mobile',
  },
  {
    key: 'address',
    label: '地址',
    field_key: 'address',
    name_zh: '地址',
    icon: 'environment',
    validation_rule: 'text',
  },
];

const CONTACT_ITEM_TEMPLATES = [
  {
    key: 'business_email',
    label: '商务邮箱',
    field_key: 'email',
    label_zh: '商务邮箱',
    display_scope: 'footer',
    value: '',
    description_zh: '',
  },
  {
    key: 'main_phone',
    label: '主联系电话',
    field_key: 'phone',
    label_zh: '主联系电话',
    display_scope: 'footer',
    value: '',
    description_zh: '',
  },
  {
    key: 'sales_whatsapp',
    label: '销售 WhatsApp',
    field_key: 'whatsapp',
    label_zh: '销售 WhatsApp',
    display_scope: 'floating_contact',
    value: '',
    description_zh: '',
  },
  {
    key: 'factory_address',
    label: '工厂地址',
    field_key: 'address',
    label_zh: '工厂地址',
    display_scope: 'contact_page',
    value: '',
    description_zh: '',
  },
];

const VALIDATION_OPTIONS = [
  { label: '文本', value: 'text' },
  { label: '邮箱', value: 'email' },
  { label: '电话', value: 'phone' },
  { label: '手机号', value: 'mobile' },
  { label: '网址', value: 'url' },
];

const FIELD_LABEL_MAP = {
  email: '邮箱',
  phone: '电话',
  whatsapp: 'WhatsApp',
  address: '地址',
};

const SCOPE_LABEL_MAP = {
  footer: '页脚',
  floating_contact: '悬浮联系方式',
  contact_page: '联系页面',
  bottom_dock: '底部浮动条',
  ai_quick_reply: 'AI 快捷回复',
};

const VALIDATION_LABEL_MAP = {
  text: '文本',
  email: '邮箱',
  phone: '电话',
  mobile: '手机号',
  url: '网址',
};

function normalizeList(value) {
  return Array.isArray(value) ? value : [];
}

function toSwitchChecked(value) {
  return Number(value || 0) === 1;
}

export default function ContactsPage() {
  const [loading, setLoading] = useState(true);
  const [fieldTypes, setFieldTypes] = useState([]);
  const [items, setItems] = useState([]);
  const [scopes, setScopes] = useState([]);
  const [fieldTypeModalOpen, setFieldTypeModalOpen] = useState(false);
  const [itemModalOpen, setItemModalOpen] = useState(false);
  const [fieldTypeSaving, setFieldTypeSaving] = useState(false);
  const [itemSaving, setItemSaving] = useState(false);
  const [editingFieldType, setEditingFieldType] = useState(null);
  const [editingItem, setEditingItem] = useState(null);
  const [fieldTypeForm] = Form.useForm();
  const [itemForm] = Form.useForm();
  const currentFieldTypeId = Form.useWatch('field_type_id', itemForm);

  const activeFieldTypeCount = useMemo(
    () => fieldTypes.filter((item) => Number(item.is_enabled || 0) === 1).length,
    [fieldTypes],
  );
  const activeItemCount = useMemo(
    () => items.filter((item) => Number(item.is_enabled || 0) === 1).length,
    [items],
  );

  const fieldTypeOptions = useMemo(
    () =>
      fieldTypes.map((item) => ({
        label: `${item.name_zh || '-'} / ${FIELD_LABEL_MAP[item.field_key] || item.field_key || '-'}`,
        value: Number(item.id),
      })),
    [fieldTypes],
  );

  const scopeOptions = useMemo(
    () =>
      scopes.map((scope) => ({
        label: SCOPE_LABEL_MAP[scope] || scope,
        value: scope,
      })),
    [scopes],
  );

  const selectedFieldType = useMemo(
    () => fieldTypes.find((item) => Number(item.id) === Number(currentFieldTypeId || 0)) || null,
    [fieldTypes, currentFieldTypeId],
  );

  async function loadContactCenter() {
    setLoading(true);
    try {
      const payload = await getContactCenterData();
      setFieldTypes(normalizeList(payload?.field_types));
      setItems(normalizeList(payload?.items));
      setScopes(normalizeList(payload?.scopes));
    } catch (error) {
      message.error(error.message || '加载联系工厂配置失败');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadContactCenter();
  }, []);

  function resetFieldTypeForm() {
    fieldTypeForm.resetFields();
    fieldTypeForm.setFieldsValue({
      template_key: undefined,
      field_key: '',
      name_zh: '',
      icon: '',
      validation_rule: 'text',
      sort: 100,
      is_enabled: true,
    });
  }

  function resetItemForm() {
    itemForm.resetFields();
    itemForm.setFieldsValue({
      template_key: undefined,
      field_type_id: fieldTypeOptions[0]?.value,
      label_zh: '',
      value: '',
      description_zh: '',
      display_scope: scopeOptions[0]?.value || 'contact_page',
      sort: 100,
      is_enabled: true,
    });
  }

  function openCreateFieldType() {
    setEditingFieldType(null);
    resetFieldTypeForm();
    setFieldTypeModalOpen(true);
  }

  function openEditFieldType(record) {
    setEditingFieldType(record);
    fieldTypeForm.resetFields();
    fieldTypeForm.setFieldsValue({
      template_key: undefined,
      field_key: record.field_key || '',
      name_zh: record.name_zh || '',
      icon: record.icon || '',
      validation_rule: record.validation_rule || 'text',
      sort: Number(record.sort || 0),
      is_enabled: toSwitchChecked(record.is_enabled),
    });
    setFieldTypeModalOpen(true);
  }

  function applyFieldTypeTemplate(templateKey) {
    const template = FIELD_TYPE_TEMPLATES.find((item) => item.key === templateKey);
    if (!template) {
      return;
    }

    fieldTypeForm.setFieldsValue({
      template_key: template.key,
      field_key: template.field_key,
      name_zh: template.name_zh,
      icon: template.icon,
      validation_rule: template.validation_rule,
    });
  }

  function openCreateItem() {
    setEditingItem(null);
    resetItemForm();
    setItemModalOpen(true);
  }

  async function openEditItem(record) {
    setEditingItem(record);
    setItemModalOpen(true);
    itemForm.resetFields();

    try {
      const detail = await getContactItemDetail(record.id);
      itemForm.setFieldsValue({
        template_key: undefined,
        field_type_id: Number(detail.field_type_id || 0) || undefined,
        label_zh: detail.label_zh || '',
        value: detail.value || '',
        description_zh: detail.description_zh || '',
        display_scope: detail.display_scope || 'contact_page',
        sort: Number(detail.sort || 0),
        is_enabled: toSwitchChecked(detail.is_enabled),
      });
    } catch (error) {
      message.error(error.message || '加载联系方式详情失败');
      setItemModalOpen(false);
      setEditingItem(null);
    }
  }

  function applyItemTemplate(templateKey) {
    const template = CONTACT_ITEM_TEMPLATES.find((item) => item.key === templateKey);
    if (!template) {
      return;
    }

    const matchedFieldType = fieldTypes.find((item) => item.field_key === template.field_key);
    itemForm.setFieldsValue({
      template_key: template.key,
      field_type_id: matchedFieldType ? Number(matchedFieldType.id) : undefined,
      label_zh: template.label_zh,
      value: template.value,
      description_zh: template.description_zh,
      display_scope: template.display_scope,
    });
  }

  async function handleSubmitFieldType(values) {
    setFieldTypeSaving(true);
    const payload = {
      field_key: String(values.field_key || '').trim(),
      name_zh: String(values.name_zh || '').trim(),
      icon: String(values.icon || '').trim(),
      validation_rule: values.validation_rule || 'text',
      sort: Number(values.sort || 0),
      is_enabled: values.is_enabled ? 1 : 0,
    };

    try {
      if (editingFieldType?.id) {
        await updateContactFieldType(editingFieldType.id, payload);
        message.success('字段类型已更新');
      } else {
        await createContactFieldType(payload);
        message.success('字段类型已创建');
      }
      setFieldTypeModalOpen(false);
      setEditingFieldType(null);
      await loadContactCenter();
    } catch (error) {
      message.error(error.message || '保存字段类型失败');
    } finally {
      setFieldTypeSaving(false);
    }
  }

  async function handleSubmitItem(values) {
    setItemSaving(true);
    const payload = {
      field_type_id: Number(values.field_type_id || 0),
      label_zh: String(values.label_zh || '').trim(),
      value: String(values.value || '').trim(),
      description_zh: String(values.description_zh || '').trim(),
      display_scope: values.display_scope || 'contact_page',
      sort: Number(values.sort || 0),
      is_enabled: values.is_enabled ? 1 : 0,
    };

    try {
      if (editingItem?.id) {
        await updateContactItem(editingItem.id, payload);
        message.success('联系方式已更新');
      } else {
        await createContactItem(payload);
        message.success('联系方式已创建');
      }
      setItemModalOpen(false);
      setEditingItem(null);
      await loadContactCenter();
    } catch (error) {
      message.error(error.message || '保存联系方式失败');
    } finally {
      setItemSaving(false);
    }
  }

  async function handleDeleteFieldType(record) {
    try {
      await deleteContactFieldType(record.id);
      message.success('字段类型已删除');
      await loadContactCenter();
    } catch (error) {
      message.error(error.message || '删除字段类型失败');
    }
  }

  async function handleDeleteItem(record) {
    try {
      await deleteContactItem(record.id);
      message.success('联系方式已删除');
      await loadContactCenter();
    } catch (error) {
      message.error(error.message || '删除联系方式失败');
    }
  }

  const fieldTypeColumns = [
    {
      title: '字段类型',
      dataIndex: 'name_zh',
      width: 180,
      render: (_, record) => (
        <Space direction="vertical" size={0}>
          <Text strong>{record.name_zh || '-'}</Text>
          <Text type="secondary">{record.field_key || '-'}</Text>
        </Space>
      ),
    },
    {
      title: '校验规则',
      dataIndex: 'validation_rule',
      width: 120,
      render: (value) => VALIDATION_LABEL_MAP[value] || value || '-',
    },
    {
      title: '图标标识',
      dataIndex: 'icon',
      width: 110,
      render: (value) => value || '-',
    },
    {
      title: '排序',
      dataIndex: 'sort',
      width: 80,
    },
    {
      title: '状态',
      dataIndex: 'is_enabled',
      width: 100,
      render: (value) => (
        <Tag color={Number(value || 0) === 1 ? 'success' : 'default'}>
          {Number(value || 0) === 1 ? '启用' : '停用'}
        </Tag>
      ),
    },
    {
      title: '操作',
      key: 'actions',
      width: 150,
      render: (_, record) => (
        <Space size={4} wrap>
          <Button size="small" onClick={() => openEditFieldType(record)}>
            编辑
          </Button>
          <Popconfirm
            title="删除后将无法恢复，确认删除该字段类型吗？"
            okText="删除"
            cancelText="取消"
            onConfirm={() => handleDeleteFieldType(record)}
          >
            <Button size="small" danger>
              删除
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  const itemColumns = [
    {
      title: '展示名称',
      dataIndex: 'label_zh',
      width: 220,
      render: (_, record) => (
        <Space direction="vertical" size={0}>
          <Text strong>{record.label_zh || '-'}</Text>
          <Text type="secondary">{record.value || '-'}</Text>
        </Space>
      ),
    },
    {
      title: '字段类型',
      dataIndex: 'field_name',
      width: 150,
      render: (_, record) => record.field_name || FIELD_LABEL_MAP[record.field_key] || '-',
    },
    {
      title: '显示位置',
      dataIndex: 'display_scope',
      width: 150,
      render: (value) => SCOPE_LABEL_MAP[value] || value || '-',
    },
    {
      title: '说明',
      dataIndex: 'description_zh',
      render: (value) => (
        <Text type="secondary" ellipsis={{ tooltip: value || '' }}>
          {value || '-'}
        </Text>
      ),
    },
    {
      title: '排序',
      dataIndex: 'sort',
      width: 80,
    },
    {
      title: '状态',
      dataIndex: 'is_enabled',
      width: 100,
      render: (value) => (
        <Tag color={Number(value || 0) === 1 ? 'success' : 'default'}>
          {Number(value || 0) === 1 ? '启用' : '停用'}
        </Tag>
      ),
    },
    {
      title: '操作',
      key: 'actions',
      width: 150,
      render: (_, record) => (
        <Space size={4} wrap>
          <Button size="small" onClick={() => openEditItem(record)}>
            编辑
          </Button>
          <Popconfirm
            title="删除后将无法恢复，确认删除该联系方式吗？"
            okText="删除"
            cancelText="取消"
            onConfirm={() => handleDeleteItem(record)}
          >
            <Button size="small" danger>
              删除
            </Button>
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <>
      <PagePlaceholder hideHeader compact>
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <Card className="page-card" bordered={false} size="small">
            <Space direction="vertical" size={12} style={{ width: '100%' }}>
              <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                <Space wrap size={[8, 8]}>
                  <Tag color="blue">字段类型 {fieldTypes.length}</Tag>
                  <Tag color="success">已启用字段 {activeFieldTypeCount}</Tag>
                  <Tag color="purple">联系方式 {items.length}</Tag>
                  <Tag color="success">已启用项 {activeItemCount}</Tag>
                </Space>
                <Space wrap size={8}>
                  <Button onClick={loadContactCenter}>刷新</Button>
                  <Button onClick={openCreateFieldType}>新增字段类型</Button>
                  <Button type="primary" onClick={openCreateItem}>
                    新增联系方式
                  </Button>
                </Space>
              </Space>

            </Space>
          </Card>

          <div className="split-panels split-panels-equal">
            <Card
              className="page-card"
              bordered={false}
              size="small"
              title={`字段类型（${fieldTypes.length}）`}
            >
              <Table
                rowKey="id"
                size="small"
                loading={loading}
                columns={fieldTypeColumns}
                dataSource={fieldTypes}
                pagination={false}
                locale={{
                  emptyText: (
                    <Space direction="vertical" size={12}>
                      <Text type="secondary">还没有字段类型，先新增邮箱、电话、WhatsApp 等基础类型。</Text>
                      <Button onClick={openCreateFieldType}>新增字段类型</Button>
                    </Space>
                  ),
                }}
                scroll={{ x: 760 }}
              />
            </Card>

            <Card className="page-card" bordered={false} size="small" title={`联系方式（${items.length}）`}>
              <Table
                rowKey="id"
                size="small"
                loading={loading}
                columns={itemColumns}
                dataSource={items}
                pagination={false}
                locale={{
                  emptyText: (
                    <Space direction="vertical" size={12}>
                      <Text type="secondary">还没有联系方式内容，新增后即可在页脚、联系页面或悬浮栏调用。</Text>
                      <Button type="primary" onClick={openCreateItem}>
                        新增联系方式
                      </Button>
                    </Space>
                  ),
                }}
                scroll={{ x: 860 }}
              />
            </Card>
          </div>
        </Space>
      </PagePlaceholder>

      <Modal
        title={editingFieldType?.id ? '编辑字段类型' : '新增字段类型'}
        open={fieldTypeModalOpen}
        onCancel={() => {
          setFieldTypeModalOpen(false);
          setEditingFieldType(null);
        }}
        onOk={() => fieldTypeForm.submit()}
        okText="保存"
        cancelText="取消"
        okButtonProps={{ loading: fieldTypeSaving }}
        destroyOnHidden
      >
        <Form form={fieldTypeForm} layout="vertical" onFinish={handleSubmitFieldType}>
          {!editingFieldType?.id ? (
            <Form.Item name="template_key" label="快速模板">
              <Select
                allowClear
                placeholder="选择预设模板后自动填充字段"
                options={FIELD_TYPE_TEMPLATES.map((item) => ({
                  label: item.label,
                  value: item.key,
                }))}
                onChange={applyFieldTypeTemplate}
              />
            </Form.Item>
          ) : null}

          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 16 }}
            message="字段类型定义的是联系方式的基础规则"
            description="建议优先创建邮箱、电话、WhatsApp、地址这几类字段，便于页脚、联系页与 AI 留资统一复用。"
          />

          <Form.Item
            name="field_key"
            label="字段键名"
            extra="仅支持小写字母、数字和下划线，例如 email、phone、whatsapp。"
            rules={[{ required: true, message: '请输入字段键名' }]}
          >
            <Input placeholder="例如 email / phone / whatsapp" />
          </Form.Item>

          <Form.Item
            name="name_zh"
            label="中文名称"
            extra="用于后台识别和下拉选择。"
            rules={[{ required: true, message: '请输入中文名称' }]}
          >
            <Input placeholder="例如 邮箱 / 电话 / WhatsApp" />
          </Form.Item>

          <div className="form-two-column">
            <Form.Item name="icon" label="图标标识">
              <Input placeholder="例如 mail / phone / message" />
            </Form.Item>
            <Form.Item name="validation_rule" label="校验规则">
              <Select options={VALIDATION_OPTIONS} />
            </Form.Item>
          </div>

          <div className="form-two-column">
            <Form.Item name="sort" label="排序值">
              <InputNumber min={0} precision={0} style={{ width: '100%' }} />
            </Form.Item>
            <Form.Item name="is_enabled" label="是否启用" valuePropName="checked">
              <Switch />
            </Form.Item>
          </div>
        </Form>
      </Modal>

      <Modal
        title={editingItem?.id ? '编辑联系方式' : '新增联系方式'}
        open={itemModalOpen}
        onCancel={() => {
          setItemModalOpen(false);
          setEditingItem(null);
        }}
        onOk={() => itemForm.submit()}
        okText="保存"
        cancelText="取消"
        okButtonProps={{ loading: itemSaving }}
        width={720}
        destroyOnHidden
      >
        <Form form={itemForm} layout="vertical" onFinish={handleSubmitItem}>
          {!editingItem?.id ? (
            <Form.Item name="template_key" label="快速模板">
              <Select
                allowClear
                placeholder="选择预设联系方式模板"
                options={CONTACT_ITEM_TEMPLATES.map((item) => ({
                  label: item.label,
                  value: item.key,
                }))}
                onChange={applyItemTemplate}
              />
            </Form.Item>
          ) : null}

          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 16 }}
            message="联系方式内容将直接供前台模块调用"
            description={
              selectedFieldType ? (
                <span>{`当前字段类型：${selectedFieldType.name_zh}，校验规则：${VALIDATION_LABEL_MAP[selectedFieldType.validation_rule] || selectedFieldType.validation_rule}`}</span>
              ) : (
                '请先选择字段类型，再填写展示名称和值。'
              )
            }
          />

          <div className="form-two-column">
            <Form.Item
              name="field_type_id"
              label="字段类型"
              rules={[{ required: true, message: '请选择字段类型' }]}
            >
              <Select options={fieldTypeOptions} placeholder="请选择字段类型" />
            </Form.Item>
            <Form.Item
              name="display_scope"
              label="显示位置"
              rules={[{ required: true, message: '请选择显示位置' }]}
            >
              <Select options={scopeOptions} placeholder="请选择显示位置" />
            </Form.Item>
          </div>

          <Form.Item
            name="label_zh"
            label="展示名称"
            extra="例如 商务邮箱、工厂总机、销售 WhatsApp。"
            rules={[{ required: true, message: '请输入展示名称' }]}
          >
            <Input placeholder="例如 商务邮箱 / 主联系电话 / 销售 WhatsApp" />
          </Form.Item>

          <Form.Item
            name="value"
            label="实际内容"
            extra="这里填写真实邮箱、电话、WhatsApp 或地址，前台和 AI 留资会直接读取。"
            rules={[{ required: true, message: '请输入实际内容' }]}
          >
            <Input placeholder="请输入实际联系方式内容" />
          </Form.Item>

          <Form.Item name="description_zh" label="补充说明">
            <TextArea rows={4} placeholder="可填写适用场景、值班时间或显示备注" />
          </Form.Item>

          <div className="form-two-column">
            <Form.Item name="sort" label="排序值">
              <InputNumber min={0} precision={0} style={{ width: '100%' }} />
            </Form.Item>
            <Form.Item name="is_enabled" label="是否启用" valuePropName="checked">
              <Switch />
            </Form.Item>
          </div>
        </Form>
      </Modal>
    </>
  );
}
