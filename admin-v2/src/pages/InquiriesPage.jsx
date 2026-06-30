import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button, Form, Input, message, Modal, Pagination, Select, Space, Table, Tag, Typography } from 'antd';
import { CheckCircleOutlined, EyeOutlined, MessageOutlined, RobotOutlined } from '@ant-design/icons';
import { useLocation } from 'react-router-dom';
import { addInquiryFollowUp, batchUpdateWorkbenchArchiveStatus, batchTranslateMessages, convertAiConversation, getInquiryLookups, getInquiryWorkbench, getInquiryWorkbenchDetail, getProductOptions, getSolutionOptions, updateInquiry, updateInquiryStatus, updateWorkbenchArchiveStatus } from '@/api/inquiries';
import PagePlaceholder from '@/components/PagePlaceholder';
import { getCountryMeta } from '@/utils/localeMeta';

const { Paragraph, Text } = Typography;
const { TextArea } = Input;
const PAGE_SIZE = 15;

const RECORD_TYPE_OPTIONS = [{ label: '全部', value: '' }, { label: '询盘', value: 'inquiry' }, { label: 'AI 对话', value: 'conversation' }];
const INQUIRY_STATUS_OPTIONS = [{ label: '全部状态', value: '' }, { label: '新建', value: 'new' }, { label: '已联系', value: 'contacted' }, { label: '报价中', value: 'quoting' }, { label: '已成交', value: 'won' }, { label: '已关闭', value: 'closed' }];
const ARCHIVE_OPTIONS = [{ label: '全部', value: '' }, { label: '正常', value: 'active' }, { label: '已归档', value: 'archived' }];

const CONTACT_ICONS = {
  email: <svg viewBox="0 0 24 24" width="20" height="20" fill="#1677ff"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/></svg>,
  phone: <span style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 20, height: 20, background: '#52c41a', color: '#fff', borderRadius: '50%', fontSize: 13 }}>☎</span>,
  whatsapp: <svg viewBox="0 0 24 24" width="20" height="20" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>,
  wechat: <svg viewBox="0 0 24 24" width="20" height="20" fill="#07C160"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.047c.134 0 .24-.111.24-.247 0-.06-.023-.12-.038-.177l-.327-1.233a.582.582 0 0 1-.023-.156.49.49 0 0 1 .201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.952-7.062-6.122zm-2.18 3.05c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.844 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982z"/></svg>,
};

function statusTag(s) {
  const m = { new: ['default', '新建'], contacted: ['blue', '已联系'], quoting: ['gold', '报价中'], won: ['success', '已成交'], closed: ['default', '已关闭'] };
  return <Tag color={m[s] ? m[s][0] : 'default'}>{m[s] ? m[s][1] : (s || '-')}</Tag>;
}
function convStatusTag(r) {
  return Number(r.inquiry_id || 0) > 0 ? <Tag color="success" icon={<CheckCircleOutlined />}>已转询盘</Tag> : <Tag color="processing" icon={<RobotOutlined />}>待转询盘</Tag>;
}
function typeIcon(t) { return t === 'conversation' ? <RobotOutlined style={{ color: '#1677ff', fontSize: 16 }} /> : <MessageOutlined style={{ color: '#faad14', fontSize: 16 }} />; }
function translatedMsg(msg) {
  const o = String(msg?.content || '').trim();
  const t = String(msg?.translated_text || '').trim();
  const show = t && t !== o && String(msg?.message_language || '').trim().toLowerCase() !== 'zh';
  return <Space direction="vertical" size={4} style={{ width: '100%' }}><Paragraph style={{ margin: 0, whiteSpace: 'pre-wrap' }}>{o || '-'}</Paragraph>{show && <Paragraph style={{ margin: 0, whiteSpace: 'pre-wrap', color: '#888' }}>{t}</Paragraph>}</Space>;
}

let _mm = new Map();

export default function InquiriesPage() {
  const location = useLocation();
  const [f, setF] = useState({ record_type: '', keyword: '', status: '', archive_status: '', country_code: '', language_code: '', assigned_to: '', page: 1, page_size: PAGE_SIZE });
  const [loading, setLoading] = useState(false);
  const [items, setItems] = useState([]);
  const [stats, setStats] = useState({});
  const [pgn, setPgn] = useState({ page: 1, page_size: PAGE_SIZE, total: 0 });
  const [selKeys, setSelKeys] = useState([]);
  const [mmReady, setMmReady] = useState(false);
  const [prodOpts, setProdOpts] = useState([]);
  const [soluOpts, setSoluOpts] = useState([]);
  const [mo, setMo] = useState(false);
  const [ml, setMl] = useState(false);
  const [fuSaving, setFuSaving] = useState(false);
  const [tlSaving, setTlSaving] = useState(false);
  const [cur, setCur] = useState(null);
  const [det, setDet] = useState(null);
  const [fuForm] = Form.useForm();

  useEffect(() => {
    if (_mm.size > 0) { setMmReady(true); } else {
      getInquiryLookups().then((r) => {
        const members = Array.isArray(r?.team_members?.items) ? r.team_members.items : Array.isArray(r?.team_members) ? r.team_members : [];
        const m = new Map();
        members.forEach((mem) => { if (mem.id) m.set(String(mem.id), mem.name_zh || mem.name || String(mem.id)); });
        _mm = m; setMmReady(true);
      }).catch(() => setMmReady(true));
    }
    getProductOptions().then(r => setProdOpts(Array.isArray(r?.items) ? r.items.map(p => ({ label: p.name_zh || p.name || String(p.id), value: p.name_zh || p.name || String(p.id) })) : [])).catch(() => {});
    getSolutionOptions().then(r => setSoluOpts(Array.isArray(r?.items) ? r.items.map(s => ({ label: s.name_zh || s.name || String(s.id), value: s.name_zh || s.name || String(s.id) })) : [])).catch(() => {});
  }, []);

  /** 显示负责人名称：优先 DB 存储的名字 → Map查找 → 兜底ID */
  const resolveName = useCallback((assignedToName, id) => {
    if (assignedToName && assignedToName !== id) return assignedToName;
    if (!id && id !== 0) return '';
    return _mm.get(String(id)) || String(id);
  }, [mmReady]);
  const aOpts = useMemo(() => { const a = []; _mm.forEach((n, id) => a.push({ label: n, value: id })); return a; }, [mmReady]);
  function aOptsFull(cv, cvName) {
    const b = [{ label: '全部负责人', value: '' }];
    if (!cv) return [...b, ...aOpts];
    if (_mm.has(String(cv))) return [...b, ...aOpts];
    const fallbackLabel = cvName && cvName !== String(cv) ? cvName : String(cv);
    return [...b, { label: fallbackLabel, value: String(cv) }, ...aOpts];
  }

  const loadList = useCallback(async (flt = f) => {
    setLoading(true); setItems([]);
    try {
      const d = await getInquiryWorkbench(flt);
      setItems(Array.isArray(d.items) ? d.items : []);
      setPgn(d.pagination || { page: 1, page_size: PAGE_SIZE, total: 0 });
      setStats(d.stats || {}); setSelKeys([]);
    } catch (e) { message.error(e.message || '加载失败'); }
    finally { setLoading(false); }
  }, [f]);

  const loadDetail = useCallback(async (rec) => {
    if (!rec) return;
    const tid = rec.record_type === 'conversation' ? Number(rec.session_id || 0) : Number(rec.id || 0);
    if (!tid) return; setMl(true);
    try { setDet(await getInquiryWorkbenchDetail(rec.record_type, tid)); }
    catch (e) { message.error(e.message || '加载详情失败'); }
    finally { setMl(false); }
  }, []);

  const refresh = useCallback(async () => { await loadList(f); if (cur) await loadDetail(cur); }, [f, cur, loadList, loadDetail]);
  useEffect(() => { loadList(f); }, [f, loadList]);
  useEffect(() => {
    const p = new URLSearchParams(location.search || '');
    const rt = String(p.get('record_type') || '').trim().toLowerCase();
    if (rt === 'inquiry' || rt === 'conversation') setF((pr) => pr.record_type === rt ? pr : { ...pr, record_type: rt, page: 1 });
  }, [location.search]);

  const openModal = (rec) => { setCur(rec); setMo(true); loadDetail(rec); };
  const sm = det?.summary || {};
  const msgs = Array.isArray(det?.chat_messages) ? det.chat_messages : Array.isArray(det?.messages) ? det.messages : [];
  const fus = Array.isArray(det?.follow_ups) ? det.follow_ups : [];
  const snaps = Array.isArray(det?.snapshots) ? det.snapshots : [];
  const isConv = cur?.record_type === 'conversation';
  const isConverted = isConv && (Number(sm.inquiry_id || 0) > 0 || Number(cur?.inquiry_id || 0) > 0);

  const handleStatus = async (s) => { if (!cur || isConv) return; try { await updateInquiryStatus(cur.id, s); message.success('状态已更新'); await refresh(); } catch (e) { message.error(e.message || '操作失败'); } };
  const handleFU = async (v) => { if (!cur || isConv) return; setFuSaving(true); try { await addInquiryFollowUp(cur.id, v.content.trim()); message.success('跟进已添加'); fuForm.resetFields(); await refresh(); } catch (e) { message.error(e.message || '添加失败'); } finally { setFuSaving(false); } };
  const handleArchive = async () => {
    if (!cur || !det) return;
    const ac = det.archive_status || det.summary?.archive_status || 'active';
    try { await updateWorkbenchArchiveStatus(cur.record_type, isConv ? Number(cur.session_id) : Number(cur.id), ac === 'archived' ? 'active' : 'archived'); message.success(ac === 'archived' ? '已恢复' : '已归档'); await refresh(); }
    catch (e) { message.error(e.message || '操作失败'); }
  };
  const handleConvert = async () => { if (!cur || !isConv) return; try { await convertAiConversation(cur.session_id, { country_code: String(sm.country_code || '').trim().toUpperCase(), language_code: String(sm.language_code || sm.language || '').trim().toLowerCase(), product_interest: String(sm.product_interest || '').trim(), solution_interest: String(sm.solution_interest || '').trim() }); message.success('已转为询盘'); await refresh(); } catch (e) { message.error(e.message || '转换失败'); } };
  const handleBatch = async (s) => { if (!selKeys.length) return; try { await batchUpdateWorkbenchArchiveStatus(f.record_type || 'inquiry', selKeys, s); await loadList(f); } catch (e) { message.error(e.message || '批量操作失败'); } };
  const handleTranslate = async () => {
    if (!cur || !cur.session_id) return; setTlSaving(true);
    try { const r = await batchTranslateMessages(cur.session_id); if (r?.messages) setDet((p) => ({ ...p, chat_messages: r.messages })); message.success('已翻译'); }
    catch (e) { message.error(e.message || '翻译失败'); }
    finally { setTlSaving(false); }
  };

  const statCards = useMemo(() => {
    const sc = stats?.status_counts || {};
    return [
      { l: '总计', v: Number(stats?.total || 0), bg: '#1677ff', fg: '#fff' },
      ...(f.record_type === 'conversation' ? [] : [
        { l: '新建', v: Number(sc.new || 0), bg: '#f5f5f5', fg: '#595959' },
        { l: '已联系', v: Number(sc.contacted || 0), bg: '#e6f4ff', fg: '#1677ff' },
        { l: '报价中', v: Number(sc.quoting || 0), bg: '#fffbe6', fg: '#d48806' },
        { l: '已成交', v: Number(sc.won || 0), bg: '#f6ffed', fg: '#389e0d' },
        { l: '已关闭', v: Number(sc.closed || 0), bg: '#fff1f0', fg: '#cf1322' },
      ]),
      { l: 'AI待转', v: Number(sc.pending_conversion || 0), bg: '#e6f4ff', fg: '#1677ff' },
    ];
  }, [stats, f.record_type]);

  const cols = useMemo(() => [
    { title: '', dataIndex: 'record_type', width: 40, render: typeIcon },
    { title: 'ID', width: 70, render: (_, r) => <Text code type="secondary">{r.record_type === 'conversation' ? `AI#${r.session_id}` : `#${r.id}`}</Text> },
    { title: '客户名称', width: 120, ellipsis: true, render: (_, r) => r.customer_name || r.company_name || r.email || r.primary_contact_value || '-' },
    { title: '联系方式', width: 60, render: (_, r) => { const pt = String(r.primary_contact_type || '').trim(); const pv = String(r.primary_contact_value || '').trim(); if (!pv) return '-'; return <span style={{ cursor: 'pointer' }} onClick={() => { navigator.clipboard.writeText(pv); message.success('已复制'); }} title={pv}>{CONTACT_ICONS[pt] || <MessageOutlined style={{ color: '#1677ff', fontSize: 18 }} />}</span>; }},
    { title: '国家', width: 130, render: (_, r) => { const c = String(r.country_code || '').trim(); if (!c) return '-'; const m = getCountryMeta(c); return <Space size={4} align="center">{m.flagUrl && <img src={m.flagUrl} alt={c} style={{ width: 20, height: 14, borderRadius: 2, objectFit: 'cover' }} onError={(e) => { e.currentTarget.style.display = 'none'; }} />}<Text style={{ fontSize: 13 }}>{m.zhName || m.name || c.toUpperCase()}</Text></Space>; }},
    { title: '负责人', width: 80, ellipsis: true, render: (_, r) => resolveName(r.assigned_to_name, r.assigned_to) || '-' },
    { title: '产品/方案', width: 120, ellipsis: true, render: (_, r) => r.product_interest || r.solution_interest || '-' },
    { title: '创建时间', width: 120, render: (_, r) => { const t = r.created_at || r.first_message_at || ''; return t ? <Text type="secondary" style={{ fontSize: 12 }}>{t.replace('T', ' ').substring(0, 16)}</Text> : '-'; }},
    { title: '状态', width: 100, render: (_, r) => r.record_type === 'conversation' ? convStatusTag(r) : statusTag(r.status) },
    { title: '操作', width: 70, fixed: 'right', render: (_, r) => <Button type="link" size="small" icon={<EyeOutlined />} onClick={() => openModal(r)}>详情</Button> },
  ], [resolveName]);

  const rSel = { selectedRowKeys: selKeys, onChange: setSelKeys, getCheckboxProps: (r) => ({ disabled: r.record_type === 'conversation' && f.record_type === 'inquiry' }) };
  const ac = det?.archive_status || det?.summary?.archive_status || 'active';
  const isArchived = ac === 'archived';
  const contactType = String(sm.primary_contact_type || '').trim();
  const contactValue = String(sm.primary_contact_value || '').trim();

  return (
    <PagePlaceholder hideHeader compact>
      <Space direction="vertical" size={16} style={{ width: '100%' }}>
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          {statCards.map((c) => (<div key={c.l} style={{ flex: '1 0 auto', minWidth: 90, maxWidth: 160, background: c.bg, borderRadius: 8, padding: '8px 12px', textAlign: 'center', border: c.l === '总计' ? `2px solid ${c.bg}` : '1px solid #e8e8e8' }}><div style={{ fontSize: 24, fontWeight: 700, color: c.fg, lineHeight: 1.2 }}>{c.v}</div><div style={{ fontSize: 12, color: c.fg, opacity: 0.75, marginTop: 2 }}>{c.l}</div></div>))}
        </div>

        <div className="toolbar-surface" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 8 }}>
          <Space size={8}>
            <Select style={{ width: 100 }} options={RECORD_TYPE_OPTIONS} value={f.record_type} onChange={(v) => setF((p) => ({ ...p, record_type: v, status: '', page: 1 }))} />
            {f.record_type !== 'conversation' && <Select style={{ width: 120 }} allowClear placeholder="负责人" value={f.assigned_to || undefined} onChange={(v) => setF((p) => ({ ...p, assigned_to: v || '', page: 1 }))} options={aOptsFull(f.assigned_to, null)} />}
            {f.record_type !== 'conversation' && <Select style={{ width: 100 }} allowClear placeholder="状态" options={INQUIRY_STATUS_OPTIONS} value={f.status} onChange={(v) => setF((p) => ({ ...p, status: v, page: 1 }))} />}
            <Select style={{ width: 100 }} allowClear placeholder="归档" options={ARCHIVE_OPTIONS} value={f.archive_status} onChange={(v) => setF((p) => ({ ...p, archive_status: v, page: 1 }))} />
          </Space>
          <Space size={8}>
            <Input.Search allowClear placeholder="搜索" style={{ width: 220 }} onSearch={(v) => setF((p) => ({ ...p, keyword: v, page: 1 }))} />
            <Button size="small" onClick={() => setF((p) => ({ ...p, keyword: '', status: '', archive_status: '', record_type: '', assigned_to: '', page: 1 }))}>重置</Button>
          </Space>
        </div>

        <Table rowKey="workbench_id" columns={cols} dataSource={items} loading={loading} rowSelection={rSel} scroll={{ x: 900 }} size="small" pagination={false} locale={{ emptyText: '暂无数据' }} />

        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <Space>
            <Button size="small" type="text" onClick={() => setSelKeys(selKeys.length === items.length ? [] : items.map((it) => it.workbench_id))}>{selKeys.length === items.length && items.length > 0 ? '取消全选' : '全选当前页'}</Button>
            {selKeys.length > 0 && <><Text strong>已选 {selKeys.length} 项</Text><Button size="small" onClick={() => handleBatch('archived')}>批量归档</Button><Button size="small" onClick={() => handleBatch('active')}>批量恢复</Button></>}
          </Space>
          <Space><Text type="secondary">共 <Text strong>{pgn.total || 0}</Text> 条，第 <Text strong>{pgn.page}</Text>/<Text strong>{Math.ceil((pgn.total || 0) / (pgn.page_size || PAGE_SIZE)) || 1}</Text> 页</Text><Pagination simple current={pgn.page} pageSize={pgn.page_size} total={pgn.total} onChange={(page) => setF((p) => ({ ...p, page }))} /></Space>
        </div>
      </Space>

      <Modal open={mo} onCancel={() => { setMo(false); setDet(null); setCur(null); }} footer={null} width="90%" style={{ top: 20 }} bodyStyle={{ maxHeight: 'calc(100vh - 120px)', overflow: 'hidden', padding: '16px 24px', display: 'flex', flexDirection: 'column' }} destroyOnClose title={<Space>{cur && typeIcon(cur.record_type)}<span>{cur?.record_type === 'conversation' ? `AI 对话 #${cur?.session_id || '-'}` : `询盘详情 #${cur?.id || '-'}`}</span>{isConv ? (isConverted ? <Tag color="success">已转询盘 #{sm.inquiry_id || cur?.inquiry_id}</Tag> : <Tag color="processing">待转询盘</Tag>) : statusTag(cur?.status)}</Space>}>
        {ml ? <div style={{ textAlign: 'center', padding: 40 }}>加载中...</div> : det ? (
          <div style={{ display: 'flex', gap: 24, flex: 1, overflow: 'hidden', minHeight: 0 }}>
            <div style={{ flex: '0 0 400px', display: 'flex', flexDirection: 'column', gap: 12, overflowY: 'auto', paddingRight: 4, minHeight: 0 }}>
              <div style={{ background: '#fafafa', borderRadius: 8, padding: 14, flexShrink: 0 }}>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px 20px' }}>
                  <div><Text type="secondary" style={{ fontSize: 12 }}>客户名称</Text><br /><Text strong>{sm.customer_name || sm.company_name || '-'}</Text></div>
                  <div>
                    <Text type="secondary" style={{ fontSize: 12 }}>联系方式</Text><br />
                    {contactValue ? <Text style={{ fontSize: 13 }}>{contactType}: {contactValue}</Text> : <Text>-</Text>}
                  </div>
                  <div><Text type="secondary" style={{ fontSize: 12 }}>国家/语言</Text><br />{(() => { const cc = String(sm.country_code || '').trim(); const lc = sm.language_code || sm.language || ''; if (!cc && !lc) return <Text>-</Text>; const m = cc ? getCountryMeta(cc) : null; return <Space size={4}>{m?.flagUrl && <img src={m.flagUrl} alt={cc} style={{ width: 18, height: 12, borderRadius: 2 }} onError={(e) => e.currentTarget.style.display = 'none'} />}<Text style={{ fontSize: 13 }}>{(m?.zhName || cc.toUpperCase())}{lc ? ' / ' + lc : ''}</Text></Space>; })()}</div>
                  <div><Text type="secondary" style={{ fontSize: 12 }}>产品意向</Text><Select size="small" style={{ width: '100%', marginTop: 4 }} allowClear showSearch placeholder="选择产品" filterOption={(inp, opt) => String(opt?.label || '').toLowerCase().includes(inp.toLowerCase())} value={sm.product_interest || undefined} onChange={async (v) => { const ns = { ...sm, product_interest: v || '' }; setDet((p) => ({ ...p, summary: ns })); if (!cur || isConv) return; try { await updateInquiry(cur.id, { product_interest: ns.product_interest, country_code: String(ns.country_code || '').trim().toUpperCase(), language_code: String(ns.language_code || ns.language || '').trim().toLowerCase(), solution_interest: String(ns.solution_interest || '').trim(), status: ns.status, assigned_to: ns.assigned_to }); message.success('询盘信息已保存'); } catch (e) { message.error(e.message || '保存询盘信息失败'); } }} options={prodOpts} /></div>
                  <div><Text type="secondary" style={{ fontSize: 12 }}>方案意向</Text><Select size="small" style={{ width: '100%', marginTop: 4 }} allowClear showSearch placeholder="选择方案" filterOption={(inp, opt) => String(opt?.label || '').toLowerCase().includes(inp.toLowerCase())} value={sm.solution_interest || undefined} onChange={async (v) => { const ns = { ...sm, solution_interest: v || '' }; setDet((p) => ({ ...p, summary: ns })); if (!cur || isConv) return; try { await updateInquiry(cur.id, { solution_interest: ns.solution_interest, country_code: String(ns.country_code || '').trim().toUpperCase(), language_code: String(ns.language_code || ns.language || '').trim().toLowerCase(), product_interest: String(ns.product_interest || '').trim(), status: ns.status, assigned_to: ns.assigned_to }); message.success('询盘信息已保存'); } catch (e) { message.error(e.message || '保存询盘信息失败'); } }} options={soluOpts} /></div>
                  {!isConv && <div><Text type="secondary" style={{ fontSize: 12 }}>归档状态</Text><br />{isArchived ? <Tag color="warning">已归档</Tag> : <Tag color="success">正常</Tag>}</div>}
                  {sm.inquiry_score != null && <div><Text type="secondary" style={{ fontSize: 12 }}>AI意图评分</Text><br /><Text strong style={{ fontSize: 16, color: sm.inquiry_score > 50 ? '#52c41a' : '#faad14' }}>{sm.inquiry_score}</Text><Text type="secondary" style={{ fontSize: 11, marginLeft: 6 }}>(置信度)</Text></div>}
                </div>
              </div>
              {!isConv && <div style={{ flexShrink: 0 }}><Text type="secondary" style={{ fontSize: 12 }}>负责人</Text><Select size="small" style={{ width: '100%', marginTop: 4 }} allowClear showSearch placeholder="选择负责人" filterOption={(inp, opt) => String(opt?.label || '').toLowerCase().includes(inp.toLowerCase())} value={sm.assigned_to != null ? String(sm.assigned_to) : undefined} onChange={async (v) => { const ns = { ...sm, assigned_to: v || '' }; setDet((p) => ({ ...p, summary: ns })); try { await updateInquiry(cur.id, { assigned_to: ns.assigned_to, country_code: String(ns.country_code || '').trim().toUpperCase(), language_code: String(ns.language_code || ns.language || '').trim().toLowerCase(), product_interest: String(ns.product_interest || '').trim(), solution_interest: String(ns.solution_interest || '').trim(), status: ns.status }); message.success('负责人已更新'); await loadList(f); } catch (e) { message.error(e.message || '保存询盘信息失败'); } }} options={aOptsFull(sm.assigned_to, sm.assigned_to_name)} /></div>}
              {!isConv && (<div style={{ background: '#fafafa', padding: 12, borderRadius: 8 }}><div style={{ marginBottom: 8 }}><Text strong>快速改状态</Text></div><Space wrap size={4}>{INQUIRY_STATUS_OPTIONS.filter((s) => s.value).map((s) => (<Button key={s.value} size="small" type={sm.status === s.value ? 'primary' : 'default'} onClick={() => handleStatus(s.value)}>{s.label}</Button>))}</Space><div style={{ marginTop: 10 }}><Button size="small" danger={!isArchived} onClick={handleArchive}>{isArchived ? '恢复' : '归档'}</Button></div></div>)}
              {isConv && !isConverted && (<div style={{ background: '#e6f4ff', padding: 12, borderRadius: 8 }}><Text strong style={{ display: 'block', marginBottom: 8 }}>此对话尚未转为询盘</Text><Space><Button type="primary" onClick={handleConvert}>转为询盘</Button><Button size="small" onClick={handleArchive}>{isArchived ? '恢复' : '归档'}</Button></Space></div>)}
              {isConv && isConverted && (<div style={{ background: '#f6ffed', padding: 12, borderRadius: 8 }}><Space><CheckCircleOutlined style={{ color: '#52c41a' }} /><Text>已转询盘 #{sm.inquiry_id || cur?.inquiry_id}</Text><Button size="small" onClick={handleArchive}>{isArchived ? '恢复' : '归档'}</Button></Space></div>)}
              {!isConv && (<div style={{ flex: 1, display: 'flex', flexDirection: 'column', minHeight: 0, overflow: 'hidden' }}><Text strong style={{ marginBottom: 8, flexShrink: 0 }}>跟进记录 ({fus.length})</Text><div style={{ flex: 1, overflowY: 'auto', border: '1px solid #f0f0f0', borderRadius: 6, padding: 8, background: '#fff' }}>{fus.length ? fus.map((fu, i) => (<div key={i} style={{ padding: '6px 8px', background: '#fffbe6', borderRadius: 4, marginBottom: 6, border: '1px solid #ffe58f' }}><Text type="secondary" style={{ fontSize: 11 }}>{fu.created_by || '系统'} · {fu.created_at || ''}</Text><Paragraph style={{ margin: '2px 0 0', fontSize: 13, whiteSpace: 'pre-wrap' }}>{fu.content || '-'}</Paragraph></div>)) : <Text type="secondary" style={{ fontSize: 13 }}>暂无</Text>}</div><Form form={fuForm} onFinish={handleFU} style={{ marginTop: 8, flexShrink: 0 }}><Form.Item name="content" rules={[{ required: true, message: '请输入' }]} style={{ margin: 0 }}><TextArea rows={2} placeholder="输入跟进内容..." /></Form.Item><Button type="primary" htmlType="submit" loading={fuSaving} size="small" style={{ marginTop: 6 }}>添加跟进</Button></Form></div>)}
              {isConv && snaps.length > 0 && (<div style={{ flex: 1, overflowY: 'auto', maxHeight: 200 }}><Text strong>快照 ({snaps.length})</Text>{snaps.map((s, i) => (<div key={i} style={{ padding: '6px 8px', background: '#f0f0f0', borderRadius: 4, marginTop: 6 }}><Text type="secondary" style={{ fontSize: 11 }}>{s.created_at || ''}</Text><Paragraph style={{ margin: '2px 0 0', fontSize: 13 }}>{s.summary || s.content || '-'}</Paragraph></div>))}</div>)}
            </div>

            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                <Text strong>{isConv || msgs.length > 0 ? `对话记录 (${msgs.length})` : '客户原始消息'}</Text>
                {(isConv || msgs.length > 0) && cur?.session_id && <Button size="small" type="link" loading={tlSaving} onClick={handleTranslate}>翻译内容</Button>}
              </div>
              <div style={{ flex: 1, overflowY: 'auto', border: '1px solid #f0f0f0', borderRadius: 8, padding: '12px 16px', background: '#fafafa' }}>
                {msgs.length ? <Space direction="vertical" size={12} style={{ width: '100%' }}>{msgs.map((m, i) => { const role = m.role || m.message_role || 'user'; const isUserR = role === 'user'; const uname = isUserR ? (sm.customer_name || sm.company_name || '客户') : 'AI'; return (<div key={i} style={{ display: 'flex', flexDirection: isUserR ? 'row' : 'row-reverse', alignItems: 'flex-start', gap: 8 }}><div style={{ flexShrink: 0, width: 32, height: 32, borderRadius: '50%', background: isUserR ? '#e6f4ff' : '#f6ffed', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 12, fontWeight: 700, color: isUserR ? '#1677ff' : '#52c41a' }}>{isUserR ? (sm.customer_name || '客').charAt(0) : 'AI'}</div><div style={{ maxWidth: '70%' }}><Text type="secondary" style={{ fontSize: 11 }}>{uname} · {m.created_at || ''}</Text><div style={{ padding: '10px 14px', borderRadius: 12, marginTop: 4, background: isUserR ? '#fff' : '#dcf8c6', border: isUserR ? '1px solid #e8e8e8' : '1px solid #b7eb8f' }}>{translatedMsg(m)}</div></div></div>); })}</Space> : (sm.requirement_summary ? translatedMsg({ content: sm.requirement_summary }) : <Text type="secondary">暂无消息数据</Text>)}
              </div>
            </div>
          </div>
        ) : null}
      </Modal>
    </PagePlaceholder>
  );
}
