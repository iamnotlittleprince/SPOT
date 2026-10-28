import React, { useEffect, useRef } from 'react';
import { CalendarDays, Clock3, FolderKanban, ListChecks, Pencil, UserRound, X } from 'lucide-react';
import { getFormatLocale, translateText } from './i18n';
import '../css/tasks.css';
import AuditTrail from './AuditTrail';
const statuses = { todo: 'A fazer', in_progress: 'Em andamento', review: 'Em revisão', done: 'Concluído' };
const priorities = { low: 'Baixa', medium: 'Média', high: 'Alta' };
export default function TaskDetails({ task, onClose, onEdit }) {
  const modal = useRef(null);
  useEffect(() => {
    if (task && !modal.current.open) modal.current.showModal();
    else if (!task && modal.current?.open) modal.current.close();
  }, [task]);
  const dateLabel = value => value ? new Date(`${value}T12:00:00`).toLocaleDateString(getFormatLocale()) : translateText('Sem prazo');
  return (
    <dialog ref={modal} className="task-editor-dialog task-detail-dialog" aria-labelledby="task-detail-title" onCancel={event => { event.preventDefault(); onClose(); }} onClick={event => { if (event.target === event.currentTarget) { const bounds = event.currentTarget.getBoundingClientRect(); if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) onClose(); } }}>
      {task && <div><header><h2 id="task-detail-title">Detalhes da tarefa</h2><button type="button" aria-label="Fechar detalhes" onClick={() => onClose()} autoFocus><X size={20} /></button></header>
        <div className="task-editor-fields task-detail-fields">
          <div className="task-field-wide task-detail-summary">
            <div className="task-detail-badges"><span className={`task-detail-status status-${task.status}`}>{statuses[task.status] || '—'}</span><span className={`task-detail-priority priority-${task.priority}`}>{priorities[task.priority] || '—'}</span></div>
            <h3 translate="no">{task.title}</h3>
          </div>
          <dl className="task-field-wide task-detail-grid">
            {[
              ['Projeto', task.project?.name || '—', FolderKanban],
              ['Responsável', task.user?.name || '—', UserRound],
              ['Data da tarefa', task.worked_on ? dateLabel(task.worked_on) : '—', CalendarDays],
              ['Prazo', dateLabel(task.due_date), CalendarDays],
              ['Tipo de tarefa', task.activity_type_name || '—', ListChecks],
              ['Duração', task.duration_minutes == null ? '—' : `${Math.floor(task.duration_minutes / 60)}h ${task.duration_minutes % 60}min`, Clock3],
              ['Hora extra?', task.is_overtime ? 'Sim' : 'Não', Clock3],
            ].map(([label, value, Icon]) => <div key={label}><dt><Icon size={15} aria-hidden="true" />{label}</dt><dd translate={['Projeto', 'Responsável', 'Tipo de tarefa'].includes(label) ? 'no' : undefined}>{value}</dd></div>)}
          </dl>
          <section className="task-field-wide task-detail-people">
            <div className="task-detail-people-heading"><strong>Colaboradores do projeto</strong>{task.can_edit && onEdit && <button type="button" onClick={() => { onClose(); onEdit(task); }}>Atribuir ou adicionar colaborador</button>}</div>
            {task.participants?.length ? <ul>{task.participants.map(person => <li key={person.id}><UserRound size={16} aria-hidden="true" /><span translate="no">{person.name}</span></li>)}</ul> : <p>Nenhum colaborador adicionado.</p>}
          </section>
          <AuditTrail key={task.id} projectId={task.project_id || task.project?.id} taskId={task.id} />
          <div className="task-field-wide task-detail-description"><strong>Descrição</strong><p translate="no">{task.description || task.title}</p></div>
        </div>
        <footer><button className="secondary-button" type="button" onClick={() => onClose()}>Fechar</button>{task.can_edit && onEdit && <button className="primary-action" type="button" onClick={() => { onClose(); onEdit(task); }}><Pencil size={16} />Editar tarefa</button>}</footer>
      </div>}
    </dialog>
  );
}
