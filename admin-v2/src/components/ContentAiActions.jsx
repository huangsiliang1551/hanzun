import { ReloadOutlined, RobotOutlined, TranslationOutlined } from '@ant-design/icons';
import { Button, Space, message } from 'antd';
import { useState } from 'react';
import { aiGenerateSeo, triggerEntityTranslation } from '@/api/seoAdmin';

function stripHtml(value) {
  return String(value || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

function pickFirstText(...values) {
  for (const value of values) {
    const text = String(value || '').trim();
    if (text) {
      return text;
    }
  }

  return '';
}

export default function ContentAiActions({
  form,
  entityLabel,
  entityType,
  entityId = null,
  buttons = ['content', 'seo', 'translation'],
  titleField = 'title_zh',
  summaryField = 'summary_zh',
  contentField = 'content_zh',
  seoTitleField = 'seo_title',
  seoKeywordsField = 'seo_keywords',
  seoDescriptionField = 'seo_description',
  variant = 'default',
}) {
  const [generatingSeo, setGeneratingSeo] = useState(false);
  const [triggeringTranslation, setTriggeringTranslation] = useState(false);
  const buttonSet = new Set(Array.isArray(buttons) ? buttons : []);
  const isInline = variant === 'inline';

  async function handleGenerateSeo() {
    const title = String(form.getFieldValue(titleField) || '').trim();
    const summary = String(form.getFieldValue(summaryField) || '').trim();
    const content = String(form.getFieldValue(contentField) || '').trim();
    const sourceText = [title, summary, stripHtml(content)].filter(Boolean).join('\n\n');

    if (stripHtml(sourceText).length < 20) {
      message.warning('请先完善标题、摘要或正文，再生成 SEO。');
      return;
    }

    setGeneratingSeo(true);
    try {
      const currentSeoTitle = String(form.getFieldValue(seoTitleField) || '');
      const currentSeoKeywords = String(form.getFieldValue(seoKeywordsField) || '');
      const currentSeoDescription = String(form.getFieldValue(seoDescriptionField) || '');
      const result = await aiGenerateSeo({
        entity_name: entityLabel,
        lang: 'zh',
        content: sourceText,
      });
      form.setFieldsValue({
        [seoTitleField]: pickFirstText(result?.seo_title, result?.title, currentSeoTitle),
        [seoKeywordsField]: pickFirstText(result?.seo_keywords, result?.keywords, currentSeoKeywords),
        [seoDescriptionField]: pickFirstText(
          result?.seo_description,
          result?.description,
          currentSeoDescription,
        ),
      });
      message.success('SEO 内容已生成。');
    } catch (error) {
      message.error(error.message || 'SEO 生成失败。');
    } finally {
      setGeneratingSeo(false);
    }
  }

  async function handleTriggerTranslation() {
    if (!entityId) {
      message.warning('请先保存内容，再生成翻译任务。');
      return;
    }

    setTriggeringTranslation(true);
    try {
      await triggerEntityTranslation(entityType, entityId);
      message.success('翻译任务已重新生成。');
    } catch (error) {
      message.error(error.message || '翻译任务生成失败。');
    } finally {
      setTriggeringTranslation(false);
    }
  }

  const nodes = [];

  if (buttonSet.has('seo')) {
    nodes.push(
      <Button
        key="seo"
        size="small"
        type={isInline ? 'link' : 'default'}
        icon={<RobotOutlined />}
        loading={generatingSeo}
        onClick={handleGenerateSeo}
        className={isInline ? 'content-ai-button-inline' : undefined}
      >
        AI 生成 SEO
      </Button>,
    );
  }

  if (buttonSet.has('translation')) {
    nodes.push(
      <Button
        key="translation"
        size="small"
        icon={<TranslationOutlined />}
        loading={triggeringTranslation}
        onClick={handleTriggerTranslation}
      >
        更新翻译任务
      </Button>,
    );
  }

  if (buttonSet.has('content') && !isInline) {
    nodes.push(
      <Button key="content-tip" size="small" type="dashed" icon={<ReloadOutlined />} disabled>
        正文 AI 入口在编辑器工具栏
      </Button>,
    );
  }

  if (!nodes.length) {
    return null;
  }

  return (
    <Space wrap size={isInline ? 0 : 8} className={`content-ai-actions content-ai-actions-${variant}`}>
      {nodes}
    </Space>
  );
}
