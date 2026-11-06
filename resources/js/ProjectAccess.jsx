import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { sessionRequest } from './api';
import useModalFocus from './useModalFocus';
import { getFormatLocale } from './i18n';
import '../css/project-access.css';

export const accessModes = { view: 'Somente visualizar', visual: 'Editar visual e indicadores dos gráficos', data: 'Editar dados do projeto e gráficos' };
export const accessDurations = { meeting: 'Somente durante reunião Teams', '24h': '24 horas', '48h': '48 horas', '72h': '72 horas', week: '1 semana', month: '1 mês', project: 'Até encerrar o projeto ou revogar' };
const effectiveStatuses = { active: 'Acesso ativo', scheduled: 'Aguardando início', expired: 'Prazo encerrado', unavailable: 'Acesso indisponível' };
const statuses = { pending: 'Pendente', approved: 'Aprovada', rejected: 'Recusada', revoked: 'Revogada' };
async function api(path, options) {
  const response = await sessionRequest(path, { handledStatuses: [403, 409, 422], ...options });
  const data = await response.json();
  if (!response.ok) { const e = new Error(data.message || 'Não foi possível concluir a operação.'); e.fields = data.errors || {}; throw e; }
  return data;
}
const time = value => value ? new Date(value).toLocaleString(getFormatLocale()) : '';

export default function ProjectAccess({ onChanged, initialRequestId }) {
  const [open, setOpen] = useState(false);
  return <><button className="project-access-launch" type="button" onClick={() => setOpen(true)}>Solicitações de acesso</button>{open && createPortal(<AccessDialog onClose={() => setOpen(false)} onChanged={onChanged} initialRequestId={initialRequestId} />, document.getElementById('root'))}</>;
}
function AccessDialog({ onClose, onChanged, initialRequestId }) {
  const ref = useModalFocus(true, onClose);
  useEffect(() => {
    const previous = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => { document.body.style.overflow = previous; };
  }, []);
  const [data, setData] = useState(null);
  const [project, setProject] = useState('');
  const [reviewer, setReviewer] = useState('');
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [fields, setFields] = useState({});
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [fieldOwner, setFieldOwner] = useState(null);
  async function load() { setData(await api('/project-access')); }
  useEffect(() => { load().catch(e => setError(e.message)); }, []);
  async function run(action, owner = null) {
    setFieldOwner(owner);
    setBusy(true); setError(''); setFields({}); setNotice('');
    try { await action(); await load(); setNotice(owner === null && !reviewMode ? 'Solicitação enviada. Você receberá a resposta na caixa de entrada.' : 'Operação concluída.'); onChanged?.(); }
    catch (e) { setError(e.message); setFields(e.fields || {}); }
    finally { setBusy(false); }
  }
  const reviewMode = Boolean(initialRequestId);
  const projects = !reviewMode && data?.can_request ? data.projects || [] : [];
  const chosen = projects.find(p => String(p.id) === project);
  const requests = reviewMode ? (data?.requests || []).filter(entry => entry.id === initialRequestId) : [];
  const fieldError = name => fieldOwner === null && fields[name] ? <span className="access-field-error" id={`access-${name}-error`}>{fields[name][0]}</span> : null;
  return <div className="project-access-overlay"><section ref={ref} role="dialog" aria-modal="true" aria-labelledby="project-access-title" tabIndex={-1} className="project-access-dialog">
    <header><h2 id="project-access-title">Solicitações de acesso</h2><button type="button" onClick={onClose}>Fechar</button></header>

    {error && <p role="alert" className="access-field-error">{error}</p>}<p role="status">{notice}</p>
    {!data && !error && <p role="status">Carregando solicitações...</p>}
    {projects.length > 0 && <form onSubmit={e => { e.preventDefault(); run(async () => { await api('/project-access', { method: 'POST', body: JSON.stringify({ project_id: Number(project), reviewer_id: Number(reviewer), message }) }); setMessage(''); }); }}>
      <h3>Pedir acesso a um projeto</h3>
      <label><span id="access-project-label">Projeto</span><select aria-labelledby="access-project-label" required value={project} onChange={e => { const next = projects.find(p => String(p.id) === e.target.value); setProject(e.target.value); setReviewer(next?.reviewers.length === 1 ? String(next.reviewers[0].id) : ''); }} aria-invalid={Boolean(fields.project_id)} aria-describedby={fields.project_id ? 'access-project_id-error' : undefined}><option value="">Selecione um projeto</option>{projects.map(p => <option translate="no" key={p.id} value={p.id}>{p.name}</option>)}</select>{fieldError('project_id')}</label>
      {chosen?.reviewers.length > 1 && <label><span id="access-reviewer-label">Destinatário</span><select aria-labelledby="access-reviewer-label" required value={reviewer} onChange={e => setReviewer(e.target.value)} aria-invalid={Boolean(fields.reviewer_id)} aria-describedby={fields.reviewer_id ? 'access-reviewer_id-error' : undefined}><option value="">Selecione um gerente</option>{chosen?.reviewers.map(r => <option translate="no" key={r.id} value={r.id}>{r.name}</option>)}</select>{fieldError('reviewer_id')}</label>}
      {chosen?.reviewers.length === 1 && <p><span>Enviar para</span>: <strong translate="no">{chosen.reviewers[0].name}</strong></p>}
      {chosen && !chosen.reviewers.length && <p role="status">Este projeto ainda não tem um gerente disponível para receber sua solicitação. Peça à administração para conferir os responsáveis e sua participação no projeto.</p>}
      <label>Mensagem<textarea required maxLength={2000} value={message} onChange={e => setMessage(e.target.value)} aria-invalid={Boolean(fields.message)} aria-describedby={fields.message ? 'access-message-error' : undefined} />{fieldError('message')}</label>
      <button type="submit" disabled={busy || !project || !reviewer}>Enviar solicitação</button>
    </form>}
    {data?.can_request && !reviewMode && !projects.length && <p>Nenhum projeto aberto foi encontrado para sua conta. Peça à administração para conferir sua empresa e seus projetos.</p>}
    {reviewMode && !requests.length && data && <p>Esta solicitação não está disponível.</p>}
    {requests.map(entry => <article key={entry.id} className="project-access-entry"><h4 translate="no">{entry.project?.name || 'Projeto indisponível'}</h4>
      <p><span>Solicitante</span>: <strong translate="no">{entry.requester?.name}</strong> · <span>Destinatário</span>: <strong translate="no">{entry.reviewer?.name}</strong></p>
      <p translate="no">{entry.message}</p><p><strong>{statuses[entry.status]}</strong>{effectiveStatuses[entry.effective_status] && <> · <span>{effectiveStatuses[entry.effective_status]}</span></>}{entry.access_mode && <> · <span>{accessModes[entry.access_mode]}</span> · <span>{accessDurations[entry.duration]}</span></>}</p>
      {entry.starts_at && <p><span>Início</span>: {time(entry.effective_starts_at || entry.starts_at)}{entry.expires_at && <> · <span>Fim</span>: {time(entry.effective_expires_at || entry.expires_at)}</>}</p>}
      {entry.can_decide && entry.status === 'pending' && <Decision entry={entry} busy={busy} run={action => run(action, entry.id)} fields={fieldOwner === entry.id ? fields : {}} />}
      {(entry.can_revoke ?? entry.can_decide) && entry.status === 'approved' && <button type="button" disabled={busy} onClick={() => run(() => api(`/project-access/${entry.id}/revoke`, { method: 'POST', body: '{}' }))}>Revogar acesso</button>}
    </article>)}
  </section></div>;
}
function Decision({ entry, busy, run, fields }) {
  const [mode, setMode] = useState('view');
  const [duration, setDuration] = useState('24h');
  const [meeting, setMeeting] = useState('');
  const [meetings, setMeetings] = useState([]);
  const [meetingError, setMeetingError] = useState('');
  const [loading, setLoading] = useState(false);
  useEffect(() => {
    if (duration !== 'meeting') return;
    let active = true; setLoading(true); setMeetingError('');
    api('/project-access/meetings').then(data => { if (active) setMeetings(data.meetings); }).catch(e => { if (active) setMeetingError(e.message); }).finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [duration]);
  return <form onSubmit={e => { e.preventDefault(); run(() => api(`/project-access/${entry.id}/decision`, { method: 'POST', body: JSON.stringify({ decision: 'approve', access_mode: mode, duration, ...(duration === 'meeting' ? { teams_event_id: meeting } : {}) }) })); }}>
    <label><span id={`access-mode-label-${entry.id}`}>Tipo de acesso</span><select aria-labelledby={`access-mode-label-${entry.id}`} aria-invalid={Boolean(fields.access_mode)} aria-describedby={fields.access_mode ? `mode-error-${entry.id}` : undefined} value={mode} onChange={e => setMode(e.target.value)}>{Object.entries(accessModes).map(([id, label]) => <option key={id} value={id}>{label}</option>)}</select>{fields.access_mode && <span id={`mode-error-${entry.id}`}>{fields.access_mode[0]}</span>}</label>
    <label><span id={`access-duration-label-${entry.id}`}>Validade</span><select aria-labelledby={`access-duration-label-${entry.id}`} aria-invalid={Boolean(fields.duration)} aria-describedby={fields.duration ? `duration-error-${entry.id}` : undefined} value={duration} onChange={e => setDuration(e.target.value)}>{Object.entries(accessDurations).map(([id, label]) => <option key={id} value={id}>{label}</option>)}</select>{fields.duration && <span id={`duration-error-${entry.id}`}>{fields.duration[0]}</span>}</label>
    {duration === 'meeting' && <><label><span id={`access-meeting-label-${entry.id}`}>Reunião Teams</span><select aria-labelledby={`access-meeting-label-${entry.id}`} aria-invalid={Boolean(fields.teams_event_id)} aria-describedby={fields.teams_event_id ? `meeting-error-${entry.id}` : undefined} required value={meeting} disabled={loading} onChange={e => setMeeting(e.target.value)}><option value="">Selecione uma reunião</option>{meetings.map(m => <option translate="no" key={m.id} value={m.id}>{m.title} · {time(m.start)} – {time(m.end)}</option>)}</select>{fields.teams_event_id && <span id={`meeting-error-${entry.id}`}>{fields.teams_event_id[0]}</span>}</label><p>Reuniões do calendário Microsoft do responsável, de hoje até um mês, limitadas aos primeiros 100 eventos.</p>{loading && <p role="status">Consultando Teams...</p>}{!loading && !meetingError && !meetings.length && <p role="status">Nenhuma reunião Teams disponível neste período.</p>}{meetingError && <p role="alert">{meetingError}</p>}</>}
    <div className="access-actions"><button type="submit" disabled={busy || loading}>Conceder acesso</button><button type="button" disabled={busy} onClick={() => run(() => api(`/project-access/${entry.id}/decision`, { method: 'POST', body: JSON.stringify({ decision: 'reject' }) }))}>Recusar</button></div>
  </form>;
}

export function ReportDataEditor({ project, onSaved }) {
  const [values, setValues] = useState({ progress: project.progress ?? 0, contract_value: project.contract_value ?? 0, start_date: project.start_date || '', end_date: project.end_date || '' });
  const [error, setError] = useState('');
  const [saved, setSaved] = useState(false);
  const [fields, setFields] = useState({});
  const [busy, setBusy] = useState(false);
  return <details className="report-data-editor"><summary>Editar dados do projeto</summary><p>Altera progresso, valor contratado e datas. Os gráficos serão recalculados a partir desses dados.</p><form onSubmit={async e => { e.preventDefault(); setBusy(true); setSaved(false); setError(''); setFields({}); try { await api(`/project-access/projects/${project.id}/data`, { method: 'PATCH', body: JSON.stringify({ ...values, start_date: values.start_date || null, end_date: values.end_date || null }) }); setSaved(true); onSaved(); } catch (e) { setError(e.message); setFields(e.fields || {}); } finally { setBusy(false); } }}>
    {Object.entries({ progress: 'Progresso (%)', contract_value: 'Valor contratado', start_date: 'Data inicial', end_date: 'Data final' }).map(([name, label]) => <label key={name}>{label}<input type={name.includes('date') ? 'date' : 'number'} min={name.includes('date') ? undefined : 0} max={name === 'progress' ? 100 : undefined} step={name === 'contract_value' ? '.01' : undefined} required={!name.includes('date')} value={values[name]} onChange={e => setValues({ ...values, [name]: e.target.value })} aria-invalid={Boolean(fields[name])} aria-describedby={fields[name] ? `report-${name}-error` : undefined} />{fields[name] && <span id={`report-${name}-error`}>{fields[name][0]}</span>}</label>)}
    {error && <p role="alert">{error}</p>}{saved && <p role="status">Dados do projeto atualizados.</p>}<button type="submit" disabled={busy}>Salvar dados</button>
  </form></details>;
}
