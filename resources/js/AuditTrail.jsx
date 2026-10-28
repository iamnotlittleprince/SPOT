import React, { useState } from 'react';
import { sessionRequest } from './api';
import { getFormatLocale, translateText } from './i18n';
import { projectStatusLabel } from './projectStatus';
import '../css/audit.css';

export const auditActionLabels = {
  'project.created': 'Projeto criado', 'project.updated': 'Projeto atualizado',
  'project.deleted': 'Projeto excluído', 'project.finalized': 'Projeto finalizado', 'project.reopened': 'Projeto reaberto',
  'project.team_updated': 'Equipe alterada', 'project.member_assigned': 'Pessoa atribuída', 'project.member_removed': 'Pessoa removida',
  'project.rate_created': 'Tarifa cadastrada', 'project.rate_updated': 'Tarifa alterada', 'project.rate_deleted': 'Tarifa excluída',
  'project.taxes_replaced': 'Impostos alterados',
  'task.created': 'Tarefa criada.', 'task.updated': 'Tarefa atualizada.', 'task.deleted': 'Tarefa excluída.',
  'task.status_changed': 'Status da tarefa alterado', 'task.priority_changed': 'Prioridade da tarefa alterada', 'task.costed': 'Custo da tarefa calculado',
  'expense.created': 'Despesa registrada', 'expense.updated': 'Despesa alterada', 'expense.deleted': 'Despesa excluída', 'expense.approved': 'Despesa aprovada', 'expense.rejected': 'Despesa rejeitada',
  'work_log.created': 'Horas registradas', 'invitation.created': 'Convite criado', 'invitation.accepted': 'Convite aceito', 'invitation.revoked': 'Convite revogado',
  'document.created': 'Documento enviado', 'document.updated': 'Vínculo do documento atualizado.', 'document.downloaded': 'Documento baixado', 'document.deleted': 'Documento excluído',
};
const fields = {
  name: 'Nome', title: 'Título', description: 'Descrição', status: 'Status', priority: 'Prioridade',
  project_id: 'Projeto', user_id: 'Responsável', created_by: 'Criado por', participant_ids: 'Participantes',
  due_date: 'Prazo', worked_on: 'Data da tarefa', duration_minutes: 'Duração (minutos)', activity_type_id: 'Tipo de tarefa', is_overtime: 'Hora extra?',
  progress: 'Progresso', position: 'Posição', client_id: 'Cliente', client_name: 'Cliente', proposal_number: 'Número da proposta',
  account_manager_id: 'Gerente de contas', project_manager_id: 'Gerente do projeto', project_status_id: 'Status', project_situation_id: 'Situação',
  start_date: 'Data de início', end_date: 'Data de término', proposal_date: 'Data da proposta', billing_date: 'Data de faturamento',
  contract_value: 'Valor do contrato (R$)', commission_rate: 'Comissão (%)', commission_basis: 'Base da comissão',
  estimated_labor_cost: 'Custo estimado do analista', estimated_additional_cost: 'Custo adicional estimado', estimated_minutes: 'Tempo estimado (minutos)',
  cost_rate_snapshot: 'Custo/hora registrado', sale_rate_snapshot: 'Valor/hora registrado', normal_cost_rate: 'Custo/hora normal', overtime_cost_rate: 'Custo/hora extra', overtime_sale_rate: 'Venda/hora extra',
  finalized_at: 'Finalizado em', finalized_by: 'Finalizado por', finalization_reason: 'Justificativa da finalização', deleted_by: 'Excluído por', deletion_reason: 'Justificativa da exclusão', reason: 'Justificativa',
  taxes: 'Impostos', members: 'Integrantes', team_name: 'Nome do time', role: 'Função', active: 'Ativo',
  amount: 'Valor', expense_date: 'Data da despesa', expense_type_id: 'Tipo de despesa', analyst_id: 'Analista',
  effective_from: 'Vigência inicial', effective_until: 'Vigência final', permissions: 'Permissões', relationship_type: 'Vínculo',
};
function display(value, key) {
  if (value == null || value === '') return '—';
  if (typeof value === 'boolean') return translateText(value ? 'Sim' : 'Não');
  if (typeof value === 'object') return JSON.stringify(value, null, 2);
  if (key === 'status') return translateText(value === 'todo' ? 'A fazer' : projectStatusLabel(value));
  if (key === 'priority') return translateText({ low: 'Baixa', medium: 'Média', high: 'Alta' }[value] || value);
  return String(value);
}
export function AuditChanges({ event }) {
  if (event.new_values === undefined && event.old_values === undefined) return null;
  const before = event.old_values || {}, after = event.new_values || {};
  const keys = [...new Set([...Object.keys(before), ...Object.keys(after)])].filter(key => !['id', 'created_at', 'updated_at', '_context'].includes(key) && JSON.stringify(before[key] ?? null) !== JSON.stringify(after[key] ?? null));
  if (!keys.length) return null;
  return <details className="audit-changes"><summary>Detalhes da alteração</summary><div className="audit-change-scroll"><table><thead><tr><th>Campo</th><th>Antes</th><th>Depois</th></tr></thead><tbody>{keys.map(key => <tr key={key}><th>{fields[key] || key.replaceAll('_', ' ')}</th><td><pre translate="no">{display(before[key], key)}</pre></td><td><pre translate="no">{display(after[key], key)}</pre></td></tr>)}</tbody></table></div></details>;
}
export default function AuditTrail({ projectId, taskId }) {
  const [open, setOpen] = useState(false), [events, setEvents] = useState([]), [next, setNext] = useState(null), [loading, setLoading] = useState(false), [error, setError] = useState('');
  async function load(more = false) {
    if (!projectId) return;
    setLoading(true); setError('');
    try {
      const query = new URLSearchParams({ project_id: projectId });
      if (taskId) query.set('task_id', taskId);
      if (more && next) query.set('before', next);
      const response = await sessionRequest(`/history?${query}`);
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Não foi possível carregar o histórico.');
      setEvents(current => more ? [...current, ...data.events] : data.events); setNext(data.next);
    } catch (failure) { setError(failure.message); } finally { setLoading(false); }
  }
  return <section className="entity-audit-trail"><button type="button" className="audit-toggle" aria-expanded={open} onClick={() => { setOpen(!open); if (!open) load(); }}>Histórico de alterações</button>{open && <div className="entity-audit-content">
    <p>Registros de alterações conforme suas permissões de acesso.</p><button type="button" disabled={loading} onClick={() => load()}>Atualizar histórico</button>
    {error && <p role="alert">{error}</p>}{loading && <p role="status">Carregando atividades...</p>}
    {!loading && !error && !events.length && <p>Nenhuma atividade encontrada</p>}
    <ol>{events.map(event => <li key={event.id}><header><strong>{auditActionLabels[event.action] || event.action}</strong><time>{new Date(event.created_at).toLocaleString(getFormatLocale())}</time></header><span translate="no">{event.context?.actor_name || event.user?.name || 'Spot'}</span>{event.context?.subject_name && <p translate="no">{event.context.subject_name}</p>}<AuditChanges event={event} /></li>)}</ol>
    {next && <button type="button" disabled={loading} onClick={() => load(true)}>Carregar mais</button>}
  </div>}</section>;
}
