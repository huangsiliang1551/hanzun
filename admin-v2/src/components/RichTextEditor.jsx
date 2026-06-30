import { useEffect, useMemo, useRef, useState } from 'react';
import { message } from 'antd';
import SunEditor from 'suneditor-react';
import zhCn from 'suneditor/src/lang/zh_cn';
import 'suneditor/dist/css/suneditor.min.css';
import MediaPickerModal from '@/components/MediaPickerModal';
import { aiPolishContent } from '@/api/seoAdmin';
import { getAssetDisplayName, isVideoAsset, resolveAssetUrl } from '@/utils/media';

const SIMPLE_BUTTON_LIST = [
  ['undo', 'redo'],
  ['formatBlock'],
  ['bold', 'italic', 'underline'],
  ['align', 'list', 'link'],
  ['removeFormat'],
];

function stripHtml(value) {
  return String(value || '')
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function escapeHtml(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function buildImageHtml(asset) {
  const src = resolveAssetUrl(asset?.file_path || asset?.url || '');
  if (!src) {
    return '';
  }

  const alt = escapeHtml(
    String(asset?.alt_text_zh || asset?.alt_text || getAssetDisplayName(asset) || '').trim(),
  );
  const title = escapeHtml(getAssetDisplayName(asset) || '');
  return `<p><img src="${src}" alt="${alt}" data-file-name="${title}" style="max-width:100%;height:auto;" /></p><p><br></p>`;
}

function buildVideoHtml(asset) {
  const src = resolveAssetUrl(asset?.file_path || asset?.url || '');
  if (!src) {
    return '';
  }

  const title = escapeHtml(getAssetDisplayName(asset) || '');
  const poster = resolveAssetUrl(asset?.thumbnail_url || '');
  const posterAttr = poster ? ` poster="${poster}"` : '';
  return `<p><video controls preload="metadata" src="${src}"${posterAttr} data-file-name="${title}" style="max-width:100%;height:auto;"></video></p><p><br></p>`;
}

function buildAssetHtml(asset) {
  return isVideoAsset(asset) ? buildVideoHtml(asset) : buildImageHtml(asset);
}

function getEditorCore(editor) {
  return editor?.core || editor?.editor || editor || null;
}

function insertManagedImage(editor, asset) {
  const core = getEditorCore(editor);
  const src = resolveAssetUrl(asset?.file_path || asset?.url || '');
  if (!src || !core?.plugins?.image?.create_image) {
    return false;
  }

  const alt = String(
    asset?.alt_text_zh || asset?.alt_text || getAssetDisplayName(asset) || '',
  ).trim();
  core.focus?.();
  core.plugins.image.create_image.call(core, src, null, '', '', 'center', null, alt);
  return true;
}

function insertManagedVideo(editor, asset) {
  const core = getEditorCore(editor);
  const src = resolveAssetUrl(asset?.file_path || asset?.url || '');
  if (!src || !core?.plugins?.video?.create_video || !core?.plugins?.video?.createVideoTag) {
    return false;
  }

  core.focus?.();
  const videoTag = core.plugins.video.createVideoTag.call(core);
  core.plugins.video.create_video.call(core, videoTag, src, '', '', 'center', null, false);
  return true;
}

function normalizeInsertedMedia(editor) {
  const core = getEditorCore(editor);
  const editable = core?.context?.element?.wysiwyg;
  if (!editable?.querySelectorAll) {
    return;
  }

  editable.querySelectorAll('img,video').forEach((node) => {
    node.removeAttribute?.('style');
    node.style.maxWidth = '100%';
    node.style.height = 'auto';
    node.style.border = '0';
    node.style.boxShadow = 'none';
    node.style.background = 'transparent';
  });
}

export default function RichTextEditor({
  value,
  onChange,
  placeholder = '请输入内容。',
  minHeight = 260,
  simple = false,
}) {
  const editorRef = useRef(null);
  const syncingRef = useRef(false);
  const polishHandlerRef = useRef(null);
  const polishingRef = useRef(false);
  const [pickerState, setPickerState] = useState({ open: false, assetType: 'image' });
  const [polishing, setPolishing] = useState(false);
  const [editorHtml, setEditorHtml] = useState(String(value || ''));

  function openPicker(assetType) {
    setPickerState({ open: true, assetType });
  }

  function closePicker() {
    setPickerState((current) => ({ ...current, open: false }));
  }

  function syncAiToolbarButton(instance = editorRef.current, loading = polishingRef.current) {
    const core = getEditorCore(instance);
    const button = core?.context?.element?.toolbar?.querySelector(
      '[data-rich-editor-action="ai-polish"]',
    );
    if (!button) {
      return;
    }

    button.disabled = loading;
    button.classList.toggle('is-loading', loading);
    button.setAttribute('title', loading ? 'AI 正在润色正文' : 'AI 润色正文');
    button.setAttribute('aria-label', loading ? 'AI 正在润色正文' : 'AI 润色正文');

    const label = button.querySelector('.rich-editor-ai-label');
    if (label) {
      label.textContent = loading ? '润色中...' : 'AI 润色';
    }
  }

  function resizeEditor(instance = editorRef.current) {
    const core = getEditorCore(instance);
    const editable = core?.context?.element?.wysiwyg;
    if (!editable) {
      return;
    }

    window.requestAnimationFrame(() => {
      const minEditableHeight = Math.max(180, minHeight - 56);
      editable.style.minHeight = `${minEditableHeight}px`;
      editable.style.height = 'auto';
      const nextHeight = Math.max(minEditableHeight, editable.scrollHeight + 4);
      editable.style.height = `${nextHeight}px`;

      const wrapperInner = editable.closest('.se-wrapper-inner');
      if (wrapperInner) {
        wrapperInner.style.height = 'auto';
        wrapperInner.style.minHeight = `${nextHeight + 20}px`;
      }

      const wysiwygWrapper = editable.closest('.se-wrapper-wysiwyg');
      if (wysiwygWrapper) {
        wysiwygWrapper.style.height = 'auto';
      }
    });
  }

  async function syncEditorContent(editor = editorRef.current) {
    await new Promise((resolve) => window.requestAnimationFrame(resolve));
    const nextValue = String(editor?.getContents?.(true) || editor?.getContents?.() || editorHtml);
    syncingRef.current = true;
    setEditorHtml(nextValue);
    onChange?.(nextValue);
    resizeEditor(editor);
  }

  async function handleInsertAssets(assets) {
    const editor = editorRef.current;
    const list = Array.isArray(assets) ? assets : [assets];
    if (!editor || list.length === 0) {
      message.error('当前素材插入失败，请重试。');
      return;
    }

    const htmlParts = list.map(buildAssetHtml).filter(Boolean);
    if (htmlParts.length === 0) {
      message.error('当前素材插入失败，请重试。');
      return;
    }

    try {
      // 始终用 insertHTML 在当前光标位置插入，避免 create_image 把光标重置到末尾
      editor.focus?.();
      editor.insertHTML(htmlParts.join(''), true, true);

      window.requestAnimationFrame(() => {
        normalizeInsertedMedia(editor);
        resizeEditor(editor);
      });
      await syncEditorContent(editor);
      closePicker();
      message.success(`已插入 ${list.length} 个素材到正文。`);
    } catch (error) {
      message.error(error?.message || '素材插入失败，请重试。');
    }
  }

  function insertToolbarMedia(assetType) {
    openPicker(assetType);
  }

  async function handlePolishContent() {
    const plainText = stripHtml(editorHtml);
    if (plainText.length < 10) {
      message.warning('请先填写正文内容，再执行 AI 润色。');
      return;
    }

    const messageKey = 'rich-text-ai-polish';
    setPolishing(true);
    message.open({
      key: messageKey,
      type: 'loading',
      content: 'AI 正在润色正文，请稍候...',
      duration: 0,
    });

    try {
      const result = await aiPolishContent({
        content: editorHtml,
        field_type: 'content_zh',
      });
      const nextValue = String(result?.polished || result?.content || editorHtml);
      syncingRef.current = true;
      setEditorHtml(nextValue);
      editorRef.current?.setContents?.(nextValue);
      onChange?.(nextValue);
      window.requestAnimationFrame(() => {
        normalizeInsertedMedia(editorRef.current);
        resizeEditor(editorRef.current);
      });
      message.open({
        key: messageKey,
        type: 'success',
        content: '正文已完成 AI 润色。',
        duration: 2,
      });
    } catch (error) {
      message.open({
        key: messageKey,
        type: 'error',
        content: error?.message || 'AI 润色失败。',
        duration: 3,
      });
    } finally {
      setPolishing(false);
    }
  }

  polishHandlerRef.current = handlePolishContent;
  polishingRef.current = polishing;

  const buttonList = useMemo(() => {
    if (simple) {
      return SIMPLE_BUTTON_LIST;
    }

    return [
      ['undo', 'redo'],
      ['formatBlock', 'paragraphStyle'],
      ['bold', 'italic', 'underline', 'strike'],
      ['fontColor', 'hiliteColor'],
      ['align', 'list', 'outdent', 'indent'],
      ['link', 'table', 'image', 'video'],
      ['removeFormat', 'showBlocks', 'codeView'],
    ];
  }, [simple]);

  useEffect(() => {
    const nextValue = String(value || '');
    if (nextValue === editorHtml) {
      return;
    }

    syncingRef.current = true;
    setEditorHtml(nextValue);
  }, [editorHtml, value]);

  useEffect(() => {
    syncAiToolbarButton(editorRef.current, polishing);
  }, [polishing]);

  function handleEditorChange(nextValue) {
    const normalized = String(nextValue || '');
    setEditorHtml(normalized);

    if (syncingRef.current) {
      syncingRef.current = false;
      resizeEditor(editorRef.current);
      return;
    }

    onChange?.(normalized);
    resizeEditor(editorRef.current);
  }

  function mountDialogAssetEntry(instance, assetType) {
    const core = getEditorCore(instance);
    const dialog = core?.context?.[assetType]?.modal;
    const footer = dialog?.querySelector('.se-dialog-footer');
    if (!dialog || !footer || dialog.querySelector(`[data-library-entry="${assetType}"]`)) {
      return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'rich-editor-dialog-asset-entry';
    wrapper.setAttribute('data-library-entry', assetType);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'se-btn rich-editor-dialog-asset-button';
    button.textContent = assetType === 'video' ? '从资源库插入视频' : '从资源库插入图片';
    button.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      core.plugins.dialog?.close?.call(core);
      openPicker(assetType);
    });

    wrapper.appendChild(button);
    footer.insertBefore(wrapper, footer.firstChild);
  }

  function mountAiToolbarButton(instance) {
    const core = getEditorCore(instance);
    const toolbar = core?.context?.element?.toolbar;
    const tray = toolbar?.querySelector('.se-btn-tray');
    if (!tray || toolbar.querySelector('[data-rich-editor-action="ai-polish"]')) {
      return;
    }

    const module = document.createElement('div');
    module.className = 'se-btn-module se-btn-module-border rich-editor-toolbar-module';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'se-btn se-btn-tool-ai-polish';
    button.setAttribute('data-rich-editor-action', 'ai-polish');
    button.setAttribute('title', 'AI 润色正文');
    button.setAttribute('aria-label', 'AI 润色正文');
    button.innerHTML =
      '<span class="se-custom-command-label">' +
      '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
      '<path d="M12 2l1.9 5.1L19 9l-5.1 1.9L12 16l-1.9-5.1L5 9l5.1-1.9L12 2zm7 11l.9 2.6L22.5 16l-2.6.9L19 19.5l-.9-2.6L15.5 16l2.6-.4L19 13zm-12 2l1.1 3 3 .9-3 .9L7 23l-.9-3-3-.9 3-.9L7 15z"></path>' +
      '</svg>' +
      '<span class="rich-editor-ai-label">AI 润色</span>' +
      '</span>';
    button.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (!polishingRef.current) {
        polishHandlerRef.current?.();
      }
    });

    module.appendChild(button);
    tray.appendChild(module);
    syncAiToolbarButton(instance, polishingRef.current);
  }

  function bindToolbarDialogEntry(instance) {
    const core = getEditorCore(instance);
    const toolbar = core?.context?.element?.toolbar;
    if (!toolbar) {
      return;
    }

    [
      { command: 'image', assetType: 'image' },
      { command: 'video', assetType: 'video' },
    ].forEach(({ command, assetType }) => {
      const button = toolbar.querySelector(`[data-command="${command}"]`);
      if (!button || button.dataset.libraryEntryBound === '1') {
        return;
      }

      button.dataset.libraryEntryBound = '1';
      button.addEventListener('click', () => {
        window.setTimeout(() => {
          mountDialogAssetEntry(editorRef.current, assetType);
        }, 0);
      });
    });
  }

  function handleEditorLoad() {
    mountAiToolbarButton(editorRef.current);
    bindToolbarDialogEntry(editorRef.current);
    mountDialogAssetEntry(editorRef.current, 'image');
    mountDialogAssetEntry(editorRef.current, 'video');
    window.requestAnimationFrame(() => {
      normalizeInsertedMedia(editorRef.current);
      resizeEditor(editorRef.current);
    });
  }

  return (
    <div
      className={`rich-text-editor${simple ? ' rich-text-editor-simple' : ''}`}
      style={{ '--editor-min-height': `${minHeight}px` }}
    >
      <SunEditor
        lang={zhCn}
        setContents={editorHtml}
        placeholder={placeholder}
        onChange={handleEditorChange}
        getSunEditorInstance={(instance) => {
          editorRef.current = instance;
        }}
        setOptions={{
          buttonList,
          height: 'auto',
          minHeight: `${minHeight}px`,
          defaultStyle: 'font-size: 14px; line-height: 1.75;',
          imageResizing: true,
          imageAlignShow: true,
          imageHeightShow: true,
          imageRotateShow: false,
          imageRotation: false,
          imageFileInput: false,
          imageUrlInput: false,
          imageSizeOnlyPercentage: false,
          videoResizing: true,
          videoAlignShow: true,
          videoHeightShow: true,
          videoRotateShow: false,
          videoFileInput: false,
          videoUrlInput: false,
          resizingBar: true,
          showPathLabel: false,
          strictHTMLValidation: false,
          mediaAutoSelect: true,
          formats: ['p', 'div', 'h2', 'h3', 'blockquote'],
          attributesWhitelist: {
            all: 'style|class|contenteditable|data-file-name|data-origin|data-size|data-align|data-proportion|data-rotate|data-rotateX|data-rotateY|origin-size|poster|controls|preload|data-resize',
          },
          tagsBlacklist: 'script',
        }}
        onLoad={handleEditorLoad}
      />

      <MediaPickerModal
        open={pickerState.open}
        title={pickerState.assetType === 'video' ? '选择视频素材' : '选择图片素材'}
        assetType={pickerState.assetType}
        multiple
        onCancel={closePicker}
        onSelect={handleInsertAssets}
      />
    </div>
  );
}
