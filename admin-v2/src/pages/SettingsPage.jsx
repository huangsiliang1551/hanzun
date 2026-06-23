import { lazy, useEffect, useMemo, useState } from 'react';
import { Button, Card, Col, Form, Image, Input, Row, Select, Space, Spin, Tabs, Tag, Typography, message } from 'antd';
import { useSearchParams } from 'react-router-dom';
import PagePlaceholder from '@/components/PagePlaceholder';
import MediaPickerModal from '@/components/MediaPickerModal';
import { getSiteBootstrap, updateSiteSettings } from '@/api/settings';
import { useAuth } from '@/providers/AuthProvider';
import { resolveAssetUrl } from '@/utils/media';
import { hasPermission } from '@/utils/rbac';

const AccountPanel = lazy(() => import('@/modules/settings/AccountPanel'));
const AdminUsersPanel = lazy(() => import('@/modules/settings/AdminUsersPanel'));
const AIPanel = lazy(() => import('@/modules/settings/AIPanel'));
const AuditLogsPanel = lazy(() => import('@/modules/settings/AuditLogsPanel'));
const LanguagesPanel = lazy(() => import('@/modules/settings/LanguagesPanel'));
const RolesPermissionsPanel = lazy(() => import('@/modules/settings/RolesPermissionsPanel'));
const SitePhrasesPanel = lazy(() => import('@/modules/settings/SitePhrasesPanel'));

const { Text, Title } = Typography;
const { TextArea } = Input;

const copy = {
  notSet: '\u672a\u8bbe\u7f6e',
  enabledLangs: '\u5df2\u542f\u7528\u8bed\u8a00',
  defaultLang: '\u9ed8\u8ba4\u8bed\u8a00',
  strategyUaFirst: '\u4f18\u5148\u8bbf\u5ba2\u8bed\u8a00',
  strategyDefaultFirst: '\u4f18\u5148\u9ed8\u8ba4\u8bed\u8a00',
  strategyLabel: '\u8bed\u8a00\u7b56\u7565',
  defaultLanguageLabel: '\u9ed8\u8ba4\u8bed\u8a00',
  siteBase: '\u7ad9\u70b9\u57fa\u7840',
  account: '\u8d26\u53f7',
  admins: '\u7ba1\u7406\u5458',
  permissions: '\u89d2\u8272\u6743\u9650',
  languages: '\u8bed\u8a00',
  phrases: '\u8bed\u8a00\u5305',
  ai: '\u667a\u80fd\u8bbe\u7f6e',
  audit: '\u5ba1\u8ba1\u65e5\u5fd7',
  brandInfo: '\u54c1\u724c\u4e0e\u57fa\u7840\u4fe1\u606f',
  mediaDisplay: '\u5a92\u4f53\u4e0e\u5c55\u793a',
  languageAndSocial: '\u8bed\u8a00\u4e0e\u793e\u5a92',
  saveBlockTitle: '\u4fdd\u5b58\u5f53\u524d\u7ad9\u70b9\u914d\u7f6e',
  reload: '\u91cd\u65b0\u52a0\u8f7d',
  save: '\u4fdd\u5b58\u8bbe\u7f6e',
  saveSuccess: '\u7ad9\u70b9\u8bbe\u7f6e\u5df2\u4fdd\u5b58\u3002',
  loadError: '\u52a0\u8f7d\u7ad9\u70b9\u8bbe\u7f6e\u5931\u8d25\u3002',
  saveError: '\u4fdd\u5b58\u7ad9\u70b9\u8bbe\u7f6e\u5931\u8d25\u3002',
  siteName: '\u7ad9\u70b9\u7b80\u79f0',
  companyName: '\u516c\u53f8\u540d\u79f0',
  companySubtitle: '\u516c\u53f8\u526f\u6807\u9898',
  siteTitle: '\u7ad9\u70b9\u6807\u9898',
  metaDescription: '\u9ed8\u8ba4 Meta \u63cf\u8ff0',
  footerText: '\u9875\u811a\u6587\u6848',
  logoUrl: 'Logo \u5730\u5740\u6216\u8d44\u6e90\u8def\u5f84',
  logoPreview: 'Logo \u9884\u89c8',
  logoAlt: '\u56fe\u7247\u66ff\u4ee3\u6587\u672c',
  videoUrl: '\u4f01\u4e1a\u89c6\u9891\u5730\u5740\u6216\u8d44\u6e90\u8def\u5f84',
  videoPreview: '\u4f01\u4e1a\u89c6\u9891\u9884\u89c8',
  chooseImage: '\u9009\u62e9\u56fe\u7247',
  replaceImage: '\u66ff\u6362\u56fe\u7247',
  chooseVideo: '\u9009\u62e9\u89c6\u9891',
  replaceVideo: '\u66ff\u6362\u89c6\u9891',
  clear: '\u6e05\u7a7a',
  noImage: '\u672a\u9009\u62e9\u56fe\u7247',
  noVideo: '\u672a\u9009\u62e9\u89c6\u9891',
  mediaHelp: '\u53ef\u4ece\u8d44\u6e90\u4e2d\u5fc3\u9009\u62e9\uff0c\u4e5f\u53ef\u7ee7\u7eed\u4f7f\u7528\u5916\u90e8\u94fe\u63a5\u3002',
  defaultLanguageHelp: '\u5f53\u8bbf\u5ba2\u8bed\u8a00\u65e0\u6cd5\u5339\u914d\u65f6\u4f7f\u7528\u3002',
  linkedin: 'LinkedIn \u9875\u9762\u94fe\u63a5',
  youtube: 'YouTube \u9891\u9053\u94fe\u63a5',
  logoReady: 'Logo \u5df2\u8bbe\u7f6e',
  logoPending: 'Logo \u672a\u8bbe\u7f6e',
  videoReady: '\u4f01\u4e1a\u89c6\u9891\u5df2\u8bbe\u7f6e',
  videoPending: '\u4f01\u4e1a\u89c6\u9891\u672a\u8bbe\u7f6e',
};

const languageStrategyOptions = [
  { label: copy.strategyUaFirst, value: 'ua-first' },
  { label: copy.strategyDefaultFirst, value: 'default-first' },
];

const placeholderText = {
  siteName: '\u6db5\u5c0a',
  companyName: '\u6db5\u5c0a\uff08\u6606\u5c71\uff09\u7cbe\u5bc6\u673a\u68b0\u5236\u9020\u6709\u9650\u516c\u53f8',
  companySubtitle: '\u70d8\u7119\u4e0e\u98df\u54c1\u751f\u4ea7\u7ebf\u8bbe\u5907\u4e13\u5bb6',
  siteTitle: '\u6db5\u5c0a\u7cbe\u5bc6\u673a\u68b0 | \u70d8\u7119\u4e0e\u98df\u54c1\u8bbe\u5907\u65b9\u6848',
  metaDescription: '\u586b\u5199\u7ad9\u70b9\u9ed8\u8ba4\u63cf\u8ff0\uff0c\u7528\u4e8e\u9996\u9875\u548c\u672a\u5355\u72ec\u914d\u7f6e SEO \u7684\u9875\u9762\u3002',
  footerText: 'Copyright \u00a9 2026 Hanzun (Kunshan) Precision Machinery Manufacturing Co., Ltd. All rights reserved.',
  logoUrl: '/uploads/site/logo.png',
  logoAlt: '\u6db5\u5c0a\u54c1\u724c Logo',
  videoUrl: '/uploads/site/factory-video.mp4',
  linkedin: 'https://www.linkedin.com/company/hanzun',
  youtube: 'https://www.youtube.com/@hanzun',
};

function normalizeSiteConfig(payload) {
  return payload?.config || {};
}

function normalizeLanguageItems(payload) {
  return Array.isArray(payload?.items) ? payload.items : [];
}

function SiteSettingsPanel() {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [languages, setLanguages] = useState([]);
  const [logoPickerOpen, setLogoPickerOpen] = useState(false);
  const [videoPickerOpen, setVideoPickerOpen] = useState(false);
  const [logoAsset, setLogoAsset] = useState(null);
  const [videoAsset, setVideoAsset] = useState(null);
  const logoUrl = Form.useWatch('logo_url', form);
  const enterpriseVideoUrl = Form.useWatch('enterprise_video_url', form);

  const languageOptions = useMemo(
    () =>
      languages
        .filter((item) => Number(item.is_enabled || 0) === 1)
        .map((item) => ({
          label: `${String(item.name || '').trim()} (${String(item.code || '').toUpperCase()})`,
          value: item.code,
        })),
    [languages],
  );
  const enabledLanguageCount = useMemo(
    () => languages.filter((item) => Number(item.is_enabled || 0) === 1).length,
    [languages],
  );
  const defaultLanguageCode = Form.useWatch('default_language', form);
  const languageStrategy = Form.useWatch('language_strategy', form) || 'ua-first';
  const defaultLanguageLabel =
    languageOptions.find((item) => item.value === defaultLanguageCode)?.label || copy.notSet;

  async function loadSettings() {
    setLoading(true);

    try {
      const payload = await getSiteBootstrap();
      const config = normalizeSiteConfig(payload);
      const languageItems = normalizeLanguageItems(payload?.languages);

      setLanguages(languageItems);
      setLogoAsset(null);
      setVideoAsset(null);
      form.setFieldsValue({
        site_name: config.site_name || '',
        site_title: config.site_title || '',
        logo_url: config.logo_url || '',
        logo_alt: config.logo_alt || '',
        company_name: config.company_name || '',
        company_subtitle: config.company_subtitle || '',
        meta_description: config.meta_description || '',
        footer_text: config.footer_text || '',
        language_strategy: config.language_strategy || 'ua-first',
        default_language: config.default_language || undefined,
        social_linkedin: config.social_linkedin || '',
        social_youtube: config.social_youtube || '',
        enterprise_video_url: config.enterprise_video_url || '',
      });
    } catch (error) {
      message.error(error.message || copy.loadError);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadSettings();
  }, []);

  async function handleSubmit(values) {
    setSaving(true);

    try {
      const payload = {
        site_name: String(values.site_name || '').trim(),
        site_title: String(values.site_title || '').trim(),
        logo_url: String(values.logo_url || '').trim(),
        logo_alt: String(values.logo_alt || '').trim(),
        company_name: String(values.company_name || '').trim(),
        company_subtitle: String(values.company_subtitle || '').trim(),
        meta_description: String(values.meta_description || '').trim(),
        footer_text: String(values.footer_text || '').trim(),
        language_strategy: values.language_strategy,
        default_language: values.default_language,
        social_linkedin: String(values.social_linkedin || '').trim(),
        social_youtube: String(values.social_youtube || '').trim(),
        enterprise_video_url: String(values.enterprise_video_url || '').trim(),
      };

      const result = await updateSiteSettings(payload);
      const config = normalizeSiteConfig(result);
      form.setFieldsValue({
        ...payload,
        default_language: config.default_language || payload.default_language,
      });
      message.success(copy.saveSuccess);
    } catch (error) {
      message.error(error.message || copy.saveError);
    } finally {
      setSaving(false);
    }
  }

  return (
    <>
      <PagePlaceholder
        hideHeader
        compact
        tags={[`${copy.enabledLangs} ${enabledLanguageCount}`, `${copy.defaultLang} ${defaultLanguageLabel}`]}
      >
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
          <Spin spinning={loading}>
            <Form form={form} layout="vertical" onFinish={handleSubmit} disabled={loading || saving}>
              <Space size={[8, 8]} wrap style={{ marginBottom: 16 }}>
                <Tag color="purple">
                  {languageStrategy === 'ua-first' ? copy.strategyUaFirst : copy.strategyDefaultFirst}
                </Tag>
                {logoUrl ? <Tag color="success">{copy.logoReady}</Tag> : <Tag>{copy.logoPending}</Tag>}
                {enterpriseVideoUrl ? <Tag color="success">{copy.videoReady}</Tag> : <Tag>{copy.videoPending}</Tag>}
              </Space>

              <Row gutter={[16, 16]}>
                <Col xs={24} xl={14}>
                  <Card className="page-card settings-card" title={copy.brandInfo} bordered={false}>
                    <div className="form-two-column">
                      <Form.Item
                        name="site_name"
                        label={copy.siteName}
                        rules={[{ required: true, message: '\u8bf7\u8f93\u5165\u7ad9\u70b9\u7b80\u79f0\u3002' }]}
                      >
                        <Input placeholder={placeholderText.siteName} maxLength={120} />
                      </Form.Item>

                      <Form.Item
                        name="company_name"
                        label={copy.companyName}
                        rules={[{ required: true, message: '\u8bf7\u8f93\u5165\u516c\u53f8\u540d\u79f0\u3002' }]}
                      >
                        <Input placeholder={placeholderText.companyName} maxLength={180} />
                      </Form.Item>

                      <Form.Item name="company_subtitle" label={copy.companySubtitle}>
                        <Input placeholder={placeholderText.companySubtitle} maxLength={180} />
                      </Form.Item>
                    </div>

                    <Form.Item
                      name="site_title"
                      label={copy.siteTitle}
                      rules={[{ required: true, message: '\u8bf7\u8f93\u5165\u7ad9\u70b9\u6807\u9898\u3002' }]}
                    >
                      <Input placeholder={placeholderText.siteTitle} maxLength={180} />
                    </Form.Item>

                    <Form.Item name="meta_description" label={copy.metaDescription}>
                      <TextArea rows={4} maxLength={255} placeholder={placeholderText.metaDescription} />
                    </Form.Item>

                    <Form.Item name="footer_text" label={copy.footerText}>
                      <TextArea rows={3} maxLength={255} placeholder={placeholderText.footerText} />
                    </Form.Item>
                  </Card>
                </Col>

                <Col xs={24} xl={10}>
                  <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Card className="page-card settings-card" title={copy.mediaDisplay} bordered={false}>
                      <Form.Item name="logo_url" label={copy.logoUrl}>
                        <Input placeholder={placeholderText.logoUrl} />
                      </Form.Item>

                      <Form.Item label={copy.logoPreview}>
                        <div className="media-field-shell">
                          <div className="media-field-preview">
                            {logoUrl ? (
                              <Image
                                src={resolveAssetUrl(logoUrl)}
                                alt={form.getFieldValue('logo_alt') || copy.logoPreview}
                                width={120}
                                height={120}
                                style={{ objectFit: 'contain', borderRadius: 12, background: '#fff' }}
                                placeholder
                              />
                            ) : (
                              <div className="media-field-empty">{copy.noImage}</div>
                            )}
                          </div>

                          <div className="media-field-meta">
                            <Space wrap>
                              <Button type="primary" onClick={() => setLogoPickerOpen(true)}>
                                {logoUrl ? copy.replaceImage : copy.chooseImage}
                              </Button>
                              {logoUrl ? (
                                <Button
                                  onClick={() => {
                                    form.setFieldValue('logo_url', '');
                                    setLogoAsset(null);
                                  }}
                                >
                                  {copy.clear}
                                </Button>
                              ) : null}
                            </Space>

                            {logoAsset?.file_name ? (
                              <Space direction="vertical" size={2}>
                                <Text strong>{logoAsset.file_name}</Text>
                                <Text type="secondary">{logoAsset.file_path}</Text>
                              </Space>
                            ) : (
                              <Text type="secondary">{copy.mediaHelp}</Text>
                            )}
                          </div>
                        </div>
                      </Form.Item>

                      <Form.Item
                        name="logo_alt"
                        label={copy.logoAlt}
                        rules={[{ required: true, message: '\u8bf7\u8f93\u5165\u56fe\u7247\u66ff\u4ee3\u6587\u672c\u3002' }]}
                      >
                        <Input placeholder={placeholderText.logoAlt} maxLength={120} />
                      </Form.Item>

                      <Form.Item name="enterprise_video_url" label={copy.videoUrl}>
                        <Input placeholder={placeholderText.videoUrl} />
                      </Form.Item>

                      <Form.Item label={copy.videoPreview}>
                        <div className="media-field-shell media-field-shell-wide">
                          <div className="media-field-preview media-field-preview-wide">
                            {enterpriseVideoUrl ? (
                              <video
                                controls
                                preload="metadata"
                                src={resolveAssetUrl(enterpriseVideoUrl)}
                                style={{ width: 180, height: 118, objectFit: 'cover', borderRadius: 12 }}
                              />
                            ) : (
                              <div className="media-field-empty media-field-empty-wide">{copy.noVideo}</div>
                            )}
                          </div>

                          <div className="media-field-meta">
                            <Space wrap>
                              <Button type="primary" onClick={() => setVideoPickerOpen(true)}>
                                {enterpriseVideoUrl ? copy.replaceVideo : copy.chooseVideo}
                              </Button>
                              {enterpriseVideoUrl ? (
                                <Button
                                  onClick={() => {
                                    form.setFieldValue('enterprise_video_url', '');
                                    setVideoAsset(null);
                                  }}
                                >
                                  {copy.clear}
                                </Button>
                              ) : null}
                            </Space>

                            {videoAsset?.file_name ? (
                              <Space direction="vertical" size={2}>
                                <Text strong>{videoAsset.file_name}</Text>
                                <Text type="secondary">{videoAsset.file_path}</Text>
                              </Space>
                            ) : (
                              <Text type="secondary">{copy.mediaHelp}</Text>
                            )}
                          </div>
                        </div>
                      </Form.Item>
                    </Card>

                    <Card className="page-card settings-card" title={copy.languageAndSocial} bordered={false}>
                      <div className="form-two-column">
                        <Form.Item
                          name="language_strategy"
                          label={copy.strategyLabel}
                          rules={[{ required: true, message: '\u8bf7\u9009\u62e9\u8bed\u8a00\u7b56\u7565\u3002' }]}
                        >
                          <Select options={languageStrategyOptions} />
                        </Form.Item>

                        <Form.Item
                          name="default_language"
                          label={copy.defaultLanguageLabel}
                          extra={copy.defaultLanguageHelp}
                          rules={[{ required: true, message: '\u8bf7\u9009\u62e9\u9ed8\u8ba4\u8bed\u8a00\u3002' }]}
                        >
                          <Select options={languageOptions} placeholder="\u8bf7\u9009\u62e9\u5df2\u542f\u7528\u8bed\u8a00" />
                        </Form.Item>
                      </div>

                      <Form.Item name="social_linkedin" label={copy.linkedin}>
                        <Input placeholder={placeholderText.linkedin} />
                      </Form.Item>

                      <Form.Item name="social_youtube" label={copy.youtube}>
                        <Input placeholder={placeholderText.youtube} />
                      </Form.Item>
                    </Card>
                  </Space>
                </Col>
              </Row>

              <Card className="page-card settings-card settings-submit-card" bordered={false}>
                <div className="settings-submit-bar">
                  <div>
                    <Title level={5} style={{ margin: 0 }}>
                      {copy.saveBlockTitle}
                    </Title>
                  </div>

                  <Space>
                    <Button onClick={loadSettings}>{copy.reload}</Button>
                    <Button type="primary" htmlType="submit" loading={saving}>
                      {copy.save}
                    </Button>
                  </Space>
                </div>
              </Card>
            </Form>
          </Spin>

          <MediaPickerModal
            open={logoPickerOpen}
            title="\u9009\u62e9 Logo \u56fe\u7247"
            assetType="image"
            onCancel={() => setLogoPickerOpen(false)}
            onSelect={(asset) => {
              form.setFieldValue('logo_url', asset.file_path || '');
              setLogoAsset(asset);
              setLogoPickerOpen(false);
            }}
          />

          <MediaPickerModal
            open={videoPickerOpen}
            title="\u9009\u62e9\u4f01\u4e1a\u89c6\u9891"
            assetType="video"
            onCancel={() => setVideoPickerOpen(false)}
            onSelect={(asset) => {
              form.setFieldValue('enterprise_video_url', asset.file_path || '');
              setVideoAsset(asset);
              setVideoPickerOpen(false);
            }}
          />
        </Space>
      </PagePlaceholder>
    </>
  );
}

const settingsTabDefinitions = [
  {
    key: 'site',
    label: copy.siteBase,
    requiredPermissions: ['system.site.view'],
    children: <SiteSettingsPanel />,
  },
  {
    key: 'account',
    label: copy.account,
    requiredPermissions: [],
    children: <AccountPanel />,
  },
  {
    key: 'admins',
    label: copy.admins,
    requiredPermissions: ['system.admin_user.view'],
    children: <AdminUsersPanel />,
  },
  {
    key: 'permissions',
    label: copy.permissions,
    requiredPermissions: ['system.role.view', 'system.permission.view'],
    children: <RolesPermissionsPanel />,
  },
  {
    key: 'languages',
    label: copy.languages,
    requiredPermissions: ['system.languages.view'],
    children: <LanguagesPanel />,
  },
  {
    key: 'phrases',
    label: copy.phrases,
    requiredPermissions: ['system.site.view'],
    children: <SitePhrasesPanel />,
  },
  {
    key: 'ai',
    label: copy.ai,
    requiredPermissions: ['system.deepseek.view'],
    children: <AIPanel />,
  },
  {
    key: 'audit',
    label: copy.audit,
    requiredPermissions: ['system.logs.view'],
    children: <AuditLogsPanel />,
  },
];

export default function SettingsPage() {
  const { profile } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();
  const requestedTab = String(searchParams.get('tab') || '').trim();
  const tabAliases = {
    'admin-users': 'admins',
    'admin-user': 'admins',
    roles: 'permissions',
    'role-permissions': 'permissions',
    logs: 'audit',
  };
  const normalizedRequestedTab = tabAliases[requestedTab] || requestedTab;

  const settingsTabItems = useMemo(
    () =>
      settingsTabDefinitions.filter((item) =>
        hasPermission(profile?.permissions || [], item.requiredPermissions),
      ),
    [profile?.permissions],
  );
  const settingsTabKeySet = useMemo(
    () => new Set(settingsTabItems.map((item) => item.key)),
    [settingsTabItems],
  );
  const activeTab = settingsTabKeySet.has(normalizedRequestedTab)
    ? normalizedRequestedTab
    : settingsTabItems[0]?.key || 'account';

  function handleTabChange(nextKey) {
    const nextParams = new URLSearchParams(searchParams);
    nextParams.set('tab', nextKey);
    setSearchParams(nextParams, { replace: true });
  }

  return (
    <Card className="page-card settings-tabs-shell" bordered={false}>
      <Tabs activeKey={activeTab} className="settings-tabs" items={settingsTabItems} onChange={handleTabChange} />
    </Card>
  );
}
