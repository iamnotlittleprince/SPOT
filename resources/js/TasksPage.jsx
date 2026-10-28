import TaskDetails from './TaskDetails';
import React, { useEffect, useRef, useState } from 'react';
import { CalendarDays, CalendarRange, Columns3, GripVertical, Pencil, Plus, Search, Table2, Trash2, X } from 'lucide-react';
import { getFormatLocale, message, translateText } from './i18n';
import '../css/tasks.css';

const statuses = { todo: 'A fazer', in_progress: 'Em andamento', review: 'Em revisão', done: 'Concluído' };
const priorities = { low: 'Baixa', medium: 'Média', high: 'Alta' };
const tones = { todo: 'blue', in_progress: 'orange', review: 'purple', done: 'green' };
const blank = { participant_ids: [], title: '', project_id: '', status: 'todo', priority: 'medium', due_date: '', analyst_id: '', worked_on: '', hours: '1', minutes: '0', activity_type_id: '', description: '', is_overtime: false };

export default function TasksPage({ request, editTaskId = null, onClose, onSaved }) {
  const [data, setData] = useState({ tasks: [], projects: [], can_create: false });
  const [view, setView] = useState('board');
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [projectFilter, setProjectFilter] = useState('');
  const [yearFilter, setYearFilter] = useState('');
  const [clientFilter, setClientFilter] = useState('');
  const [error, setError] = useState('');
  const [feedback, setFeedback] = useState('');
  const [editor, setEditor] = useState(null);
  const [peopleSearch, setPeopleSearch] = useState('');
  const [assignment, setAssignment] = useState('self');
  const [form, setForm] = useState(blank);
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [pendingDelete, setPendingDelete] = useState(null);
  const modal = useRef(null);
  const openedInitialTask = useRef(false);
  const [selectedTask, setSelectedTask] = useState(null);
  const [dragging, setDragging] = useState(null);
  const [dropStatus, setDropStatus] = useState(null);
  const [moving, setMoving] = useState(null);
  const dragTask = useRef(null);
  const touchDrag = useRef(null);
  const movePending = useRef(false);
  const suppressClick = useRef(0);
  const restoreStatusFocus = useRef(null);
  useEffect(() => {
    if (restoreStatusFocus.current !== null && moving === null) {
      document.querySelector(`.kanban-card[data-task-id="${restoreStatusFocus.current}"] select`)?.focus();
      restoreStatusFocus.current = null;
    }
  }, [data.tasks, moving]);
  function clearDrag() { dragTask.current = null; touchDrag.current = null; setDragging(null); setDropStatus(null); }
  async function moveTask(task, status) {
    clearDrag();
    if (!task?.can_edit || task.status === status || movePending.current) return;
    movePending.current = true; setMoving(task.id); setError(''); setFeedback('Salvando status...');
    setData(current => ({...current, tasks: current.tasks.map(item => item.id === task.id ? {...item, status} : item)}));
    try {
      const response = await request(`/tasks/${task.id}/status`, {method: 'PATCH', body: JSON.stringify({status})});
      const result = await response.json();
      if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || translateText('Não foi possível mudar o status. Tente novamente.'));
      setData(current => ({...current, tasks: current.tasks.map(item => item.id === task.id ? result : item)}));
      setFeedback(message('Tarefa movida para {status}.', {status: translateText(statuses[result.status])}));
    } catch (failure) {
      setData(current => ({...current, tasks: current.tasks.map(item => item.id === task.id ? task : item)}));
      setFeedback(''); setError(failure.message);
    } finally { movePending.current = false; setMoving(null); }
  }
  function startDrag(event, task) {
    if (!task.can_edit || movePending.current || event.target.closest('select, input, a, button:not(.task-drag-handle)')) { event.preventDefault(); return; }
    dragTask.current = task; setDragging(task.id);
    event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', String(task.id));
  }
  function touchStart(event, task) {
    if (event.pointerType === 'mouse' || !task.can_edit || movePending.current) return;
    event.currentTarget.setPointerCapture(event.pointerId);
    touchDrag.current = {task, x: event.clientX, y: event.clientY, active: false};
  }
  function touchMove(event) {
    const touch = touchDrag.current;
    if (!touch) return;
    if (!touch.active && Math.hypot(event.clientX - touch.x, event.clientY - touch.y) < 8) return;
    touch.active = true; dragTask.current = touch.task; setDragging(touch.task.id);
    const column = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-drop-status]');
    setDropStatus(column?.dataset.dropStatus || null);
    const board = event.currentTarget.closest('.kanban-board');
    const bounds = board?.getBoundingClientRect();
    if (bounds && event.clientX > bounds.right - 40) board.scrollBy({left: 35});
    if (bounds && event.clientX < bounds.left + 40) board.scrollBy({left: -35});
  }
  function touchEnd(event) {
    const touch = touchDrag.current;
    const column = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-drop-status]');
    if (touch?.active) { suppressClick.current = Date.now() + 400; if (column) moveTask(touch.task, column.dataset.dropStatus); }
    clearDrag();
  }
  function openCard(event, task) {
    if (Date.now() >= suppressClick.current && !event.target.closest('button, a, input, select')) setSelectedTask(task);
  }

  useEffect(() => {
    let active = true;
    request('/tasks').then(async response => {
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Não foi possível carregar as tarefas.');
      if (active) setData(result);
    }).catch(error => { if (active) setError(error.message); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [request]);

  useEffect(() => {
    if (editor && !modal.current.open) modal.current.showModal();
    else if (!editor && modal.current?.open) modal.current.close();
  }, [editor]);

  useEffect(() => {
    if (loading || !editTaskId || openedInitialTask.current) return;
    openedInitialTask.current = true;
    const task = data.tasks.find(item => item.id === editTaskId);
    if (!task?.can_edit) { setError('Tarefa indisponível para edição.'); return; }
    openEditor(task);
  }, [loading, editTaskId, data.tasks]);
  function closeEditor() { setEditor(null); onClose?.(); }
  function openEditor(task = null, status = 'todo') {
    setForm(task ? { participant_ids: (task.participants || []).map(person => person.id), analyst_id: String(task.user_id), worked_on: task.worked_on || '', hours: String(Math.floor((task.duration_minutes || 60) / 60)), minutes: String((task.duration_minutes || 60) % 60), activity_type_id: String(task.activity_type_id || ''), description: task.description || task.title, is_overtime: Boolean(task.is_overtime), title: task.title, project_id: String(task.project_id), status: task.status, priority: task.priority, due_date: task.due_date || '' }
      : { ...blank, analyst_id: String(data.current_user?.id || ''), worked_on: new Date().toLocaleDateString('en-CA'), status, project_id: data.projects.length === 1 ? String(data.projects[0].id) : '' });
    setPeopleSearch('');
    setAssignment(task && task.user_id !== data.current_user?.id ? 'other' : 'self');
    setEditor(task || { id: null }); setFormError(''); setFeedback('');
  }
  function change(field, value) { setForm(current => ({ ...current, [field]: value })); }
  async function save(event) {
    event.preventDefault();
    if (saving) return;
    setSaving(true); setFormError('');
    try {
      const response = await request(editor.id ? `/tasks/${editor.id}` : '/tasks', {
        method: editor.id ? 'PUT' : 'POST',
        body: JSON.stringify({ ...form, title: form.description.trim().slice(0, 180), description: form.description.trim(), analyst_id: Number(form.analyst_id), duration_minutes: Number(form.hours) * 60 + Number(form.minutes), activity_type_id: Number(form.activity_type_id), project_id: Number(form.project_id), due_date: form.due_date || null }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Não foi possível salvar a tarefa.');
      setData(current => ({ ...current, tasks: (editor.id ? current.tasks.map(task => task.id === result.id ? result : task) : [result, ...current.tasks]).filter(task => task.can_view !== false) }));
      setFeedback(editor.id ? 'Tarefa atualizada.' : 'Tarefa criada.');
      setEditor(null); onSaved?.(result); setSearch(''); setStatusFilter(''); setProjectFilter(''); setYearFilter(''); setClientFilter(''); setError('');
    } catch (error) { setFormError(error.message); }
    finally { setSaving(false); }
  }
  async function remove(task) {
    if (deleting !== null) return;
    setDeleting(task.id); setError(''); setFeedback('');
    try {
      const response = await request(`/tasks/${task.id}`, { method: 'DELETE' });
      if (!response.ok) {
        const result = await response.json();
        throw new Error(result.message || 'Não foi possível excluir a tarefa.');
      }
      setData(current => ({ ...current, tasks: current.tasks.filter(item => item.id !== task.id) }));
      setFeedback('Tarefa excluída.');
      setPendingDelete(null);
    } catch (error) { setError(error.message); }
    finally { setDeleting(null); }
  }
  const projectPeople = [...new Map((data.project_people || []).filter(person => String(person.project_id) === form.project_id && String(person.id) !== form.analyst_id).map(person => [person.id, person])).values()];
  const matchingPeople = projectPeople.filter(person => person.name.toLocaleLowerCase(getFormatLocale()).includes(peopleSearch.trim().toLocaleLowerCase(getFormatLocale())));
  const selectedPeople = projectPeople.filter(person => form.participant_ids.includes(person.id));
  const query = search.trim().toLocaleLowerCase(getFormatLocale());
  const tasks = data.tasks.filter(task => (!query || `${task.title} ${task.project?.name || ''}`.toLocaleLowerCase(getFormatLocale()).includes(query))
    && (!yearFilter || task.worked_on?.startsWith(yearFilter)) && (!clientFilter || String(task.project?.client_id) === clientFilter)
    && (!statusFilter || task.status === statusFilter) && (!projectFilter || String(task.project_id) === projectFilter));
  const groups = tasks.reduce((result, task) => { (result[task.worked_on || task.due_date || ''] ||= []).push(task); return result; }, {});
  const dateLabel = value => value ? new Date(`${value}T12:00:00`).toLocaleDateString(getFormatLocale()) : translateText('Sem prazo');
  const incompleteLegacyTask = Boolean(editor?.id && (!form.worked_on || !form.activity_type_id));
  const canSave = Boolean(form.description.trim() && form.worked_on && form.activity_type_id && form.analyst_id && Number(form.hours) * 60 + Number(form.minutes) >= 1 && Number(form.hours) * 60 + Number(form.minutes) <= 1440);
  const actions = task => <div className="task-crud-actions">
    {task.can_edit && <button type="button" aria-label="Editar tarefa" title="Editar tarefa" disabled={moving !== null} onClick={() => openEditor(task)}><Pencil size={16} /></button>}
    {task.can_delete && <button type="button" aria-label="Excluir tarefa" title="Excluir tarefa" disabled={deleting !== null || moving !== null} onClick={() => setPendingDelete(task)}><Trash2 size={16} /></button>}
    {!task.can_edit && !task.can_delete && <small>Somente leitura</small>}
  </div>;

  return <div className={editTaskId ? "home-task-editor" : "workspace-page tasks-workspace"}>
    {!editTaskId && <>
    <header className="workspace-heading"><div><span className="workspace-eyebrow">Meu trabalho</span><h1>Gestão de tarefas</h1><p>Priorize, organize e mova o trabalho pelo seu fluxo.</p></div>
      <button className="primary-action" type="button" disabled={loading} onClick={() => openEditor()}><Plus size={17} />Nova tarefa</button>
    </header>
    <div className="workspace-toolbar task-toolbar">
      <div className="view-switch">{[['board', Columns3, 'Quadro'], ['list', Table2, 'Lista'], ['calendar', CalendarRange, 'Calendário']].map(([value, Icon, label]) => <button type="button" key={value} className={view === value ? 'active' : ''} onClick={() => setView(value)}><Icon size={17} />{label}</button>)}</div>
      <div className="toolbar-actions task-crud-filters">
        <select aria-label="Filtrar por ano" value={yearFilter} onChange={e=>setYearFilter(e.target.value)}><option value="">Todos os anos</option>{[...new Set(data.tasks.map(t=>t.worked_on?.slice(0,4)).filter(Boolean))].sort().reverse().map(year=><option key={year} value={year}>{year}</option>)}</select>
        <select aria-label="Filtrar por cliente" value={clientFilter} onChange={e=>setClientFilter(e.target.value)}><option value="">Todos os clientes</option>{(data.clients||[]).map(client=><option key={client.id} value={client.id} translate="no">{client.name}</option>)}</select>
        <select aria-label="Filtrar por status" value={statusFilter} onChange={event => setStatusFilter(event.target.value)}><option value="">Todos os status</option>{Object.entries(statuses).map(([value,label]) => <option key={value} value={value}>{label}</option>)}</select>
        <select aria-label="Filtrar por projeto" value={projectFilter} onChange={event => setProjectFilter(event.target.value)}><option value="">Todos os projetos</option>{[...new Map([...data.projects, ...data.tasks.map(task => task.project).filter(Boolean)].map(project => [project.id,project])).values()].map(project => <option key={project.id} value={project.id} translate="no">{project.name}</option>)}</select>
        <label><Search size={17} /><input placeholder="Buscar tarefa..." value={search} onChange={event => setSearch(event.target.value)} /></label>
      </div>
    </div>
    {error && <p className="home-state error" role="alert">{error}</p>}
    {feedback && <p className="task-crud-success" role="status">{feedback}</p>}
    {loading && <p role="status">Carregando tarefas...</p>}
    {!loading && !tasks.length && <p className="task-crud-empty">Nenhuma tarefa encontrada.</p>}
    {view === 'board' && <p className="kanban-help" id="kanban-help">Arraste os cartões entre as colunas ou use o seletor de status em cada tarefa.</p>}
    {view === 'board' && <div className="kanban-board">
      {Object.entries(statuses).map(([status,label]) => <section className={`kanban-column ${dropStatus === status ? 'is-drop-target' : ''}`} data-drop-status={status} key={status}
        onDragOver={event => { if (dragTask.current && !movePending.current) { event.preventDefault(); event.dataTransfer.dropEffect = 'move'; setDropStatus(status); } }}
        onDragLeave={event => { if (!event.currentTarget.contains(event.relatedTarget)) setDropStatus(null); }}
        onDrop={event => { event.preventDefault(); const task = dragTask.current; suppressClick.current = Date.now() + 400; moveTask(task, status); }}>
        <header><span><i className={tones[status]} /><span>{label}</span><b>{tasks.filter(task => task.status === status).length}</b></span></header>
        <div className="kanban-cards">{tasks.filter(task => task.status === status).map(task => <article className={`kanban-card task-open-card ${dragging === task.id ? 'is-dragging' : ''} ${moving === task.id ? 'is-saving' : ''}`} draggable={Boolean(task.can_edit && moving === null)} aria-busy={moving === task.id || undefined} onDragStart={event => startDrag(event, task)} onDragEnd={clearDrag} key={task.id} data-task-id={task.id} onClick={event => openCard(event, task)}>
          <div className="card-project">{task.can_edit && <button className="task-drag-handle" type="button" aria-label="Arrastar tarefa" aria-describedby="kanban-help" disabled={moving !== null} onPointerDown={event => touchStart(event, task)} onPointerMove={touchMove} onPointerUp={touchEnd} onPointerCancel={clearDrag}><GripVertical size={18} aria-hidden="true" /></button>}<span translate="no">{task.project?.name}</span>{actions(task)}</div>
          <h2><button className="task-open-button" type="button" onClick={() => setSelectedTask(task)} translate="no">{task.description || task.title}</button></h2><p className="task-activity-summary"><span translate="no">{task.activity_type_name || '—'}</span> · {task.duration_minutes == null ? '—' : `${Math.floor(task.duration_minutes / 60)}h ${task.duration_minutes % 60}min`}{task.is_overtime && <span> · Hora extra</span>}</p>
          <div className="card-tags"><span className={`priority ${task.priority}`}>{priorities[task.priority]}</span><span><CalendarDays size={13} />{dateLabel(task.worked_on || task.due_date)}</span></div>
          {task.can_edit && <label className="kanban-status-control"><span>Status</span><select aria-label={message('Status de {name}', {name: task.title})} value={task.status} disabled={moving !== null} onChange={event => { restoreStatusFocus.current = task.id; moveTask(task, event.target.value); }}>{Object.entries(statuses).map(([value,label]) => <option value={value} key={value}>{label}</option>)}</select></label>}
          <footer className="task-owner"><span>Responsável</span><strong translate="no">{task.user?.name || '—'}</strong></footer>
        </article>)}
        {data.can_create && <button className="add-task-card" type="button" onClick={() => openEditor(null, status)}><Plus size={17} />Adicionar tarefa</button>}</div>
      </section>)}
    </div>}
    {view === 'list' && <section className="task-register-table"><table><thead><tr>{['Nome do projeto', 'Analista', 'Data da tarefa', 'Duração', 'Tipo de tarefa', 'Descrição', 'Hora extra?', 'Ações'].map(label => <th key={label}>{label}</th>)}</tr></thead><tbody>
      {tasks.map(task => <tr key={task.id} data-task-id={task.id}><td translate="no">{task.project?.name}</td><td translate="no">{task.user?.name}</td><td>{task.worked_on ? dateLabel(task.worked_on) : '—'}</td><td>{task.duration_minutes == null ? '—' : `${Math.floor(task.duration_minutes / 60)}h ${task.duration_minutes % 60}min`}</td><td translate="no">{task.activity_type_name || '—'}</td><td><button className="task-open-button" type="button" onClick={() => setSelectedTask(task)} translate="no">{task.description || task.title}</button></td><td>{task.is_overtime ? 'Sim' : 'Não'}</td><td>{actions(task)}</td></tr>)}
    </tbody></table></section>}
    {view === 'calendar' && <section className="task-calendar-view"><header><div><CalendarRange size={19} /><span><strong>Calendário de tarefas</strong><small>Tarefas organizadas pela data da atividade</small></span></div></header>
      <div>{Object.entries(groups).sort(([a],[b]) => (a || '9999').localeCompare(b || '9999')).map(([due,entries]) => <section key={due}><header><CalendarDays size={16} /><strong>{dateLabel(due)}</strong><span>{entries.length}</span></header><div>{entries.map(task => <article className="task-open-card" key={task.id} data-task-id={task.id} onClick={event => openCard(event, task)}><i className={tones[task.status]} /><span><strong><button className="task-open-button" type="button" onClick={() => setSelectedTask(task)} translate="no">{task.title}</button></strong><small translate="no">{task.project?.name}</small></span>{actions(task)}</article>)}</div></section>)}</div>
    </section>}
    </>}
    {editTaskId && loading && <p role="status">Carregando tarefa...</p>}
    {editTaskId && error && <div role="alert"><p>{error}</p><button type="button" className="secondary-button" onClick={onClose}>Fechar</button></div>}
    <TaskDetails task={selectedTask} onClose={() => setSelectedTask(null)} onEdit={openEditor} />
    <dialog ref={modal} className="task-editor-dialog" aria-labelledby="task-editor-title" onCancel={event => { event.preventDefault(); if (!saving) closeEditor(); }}>
      {editor && !data.can_create && <div><header><h2 id="task-editor-title">Área restrita</h2><button type="button" aria-label="Fechar" onClick={closeEditor}><X size={20} /></button></header><div className="task-editor-fields"><p>Caso necessário acesso, falar com gerência</p></div><footer><button type="button" className="primary-action" onClick={closeEditor}>Fechar</button></footer></div>}
      {editor && data.can_create && <form onSubmit={save}><header><h2 id="task-editor-title">{editor.id ? 'Editar tarefa' : 'Nova tarefa'}</h2><button type="button" aria-label="Fechar" disabled={saving} onClick={closeEditor}><X size={20} /></button></header>
        <div className="task-editor-fields">
          {incompleteLegacyTask && <div className="task-legacy-notice task-field-wide" role="status"><strong>Complete os dados da tarefa</strong><span>Esta tarefa foi criada antes desses campos se tornarem obrigatórios. Informe os itens destacados para salvar as alterações.</span></div>}
          <label className="task-field-wide" htmlFor="task-project_id">Nome do projeto<select id="task-project_id" aria-label="Nome do projeto" autoFocus required value={form.project_id} onChange={event => { change('project_id', event.target.value); change('analyst_id', String(data.current_user?.id || '')); change('participant_ids', []); setAssignment('self'); }}><option value="">Selecione um projeto</option>{data.projects.map(project => <option value={project.id} key={project.id} translate="no">{project.name}</option>)}</select></label>
          {!data.projects.length && <p role="status">Nenhum projeto disponível para cadastrar tarefas.</p>}
          <fieldset className="task-field-wide task-participants-editor task-assignment-editor">
            <legend>Responsabilidade da tarefa</legend>
            <div className="task-assignment-options">
              <label><input type="radio" name="task-assignment" value="self" checked={assignment === 'self'} onChange={() => { setAssignment('self'); change('analyst_id', String(data.current_user?.id || '')); change('participant_ids', form.participant_ids.filter(id => id !== data.current_user?.id)); }} /><span>Ficar como responsável</span></label>
              <label><input type="radio" name="task-assignment" value="other" checked={assignment === 'other'} onChange={() => { setAssignment('other'); change('analyst_id', ''); change('participant_ids', form.participant_ids.filter(id => id !== data.current_user?.id)); }} /><span>Atribuir a outra pessoa</span></label>
            </div>
            {assignment === 'other' ? <label htmlFor="task-analyst">Novo responsável<select id="task-analyst" aria-label="Analista" required value={form.analyst_id} onChange={event => { change('analyst_id', event.target.value); change('participant_ids', form.participant_ids.filter(id => String(id) !== event.target.value)); }}>
              <option value="">Selecione uma pessoa do projeto</option>
              {[...new Map((data.project_people || data.analysts || []).filter(person => String(person.project_id) === form.project_id && person.id !== data.current_user?.id && person.can_be_responsible !== false).map(person => [person.id, person])).values()].map(person => <option key={person.id} value={person.id} translate="no">{person.name}</option>)}
            </select></label> : <p>Você permanece responsável por esta tarefa.</p>}
            {assignment === 'other' && <p>Ao salvar, a tarefa será transferida e você deixará de ser responsável. A autoria permanece no histórico.</p>}
          </fieldset>
          <fieldset className="task-field-wide task-participants-editor">
            <legend>Colaboradores do projeto</legend>
            <p>Adicione pessoas para colaborar sem mudar o responsável pela tarefa.</p>
            {!form.project_id ? <small>Selecione um projeto para escolher as pessoas.</small> : <>
              <label className="task-people-search"><Search size={17} aria-hidden="true" /><input type="search" aria-label="Buscar colaborador no projeto" placeholder="Buscar pessoa pelo nome..." value={peopleSearch} onChange={event => setPeopleSearch(event.target.value)} /><span>{selectedPeople.length} {selectedPeople.length === 1 ? 'selecionado' : 'selecionados'}</span></label>
              {selectedPeople.length > 0 && <div className="task-selected-people" aria-label="Colaboradores selecionados">{selectedPeople.map(person => <button type="button" key={person.id} aria-label={`Remover colaborador ${person.name}`} onClick={() => change('participant_ids', form.participant_ids.filter(id => id !== person.id))}><span translate="no">{person.name}</span><X size={14} aria-hidden="true" /></button>)}</div>}
              <div className="task-participants-options">
                {matchingPeople.map(person => <label key={person.id} className={form.participant_ids.includes(person.id) ? 'is-selected' : ''}><input type="checkbox" aria-label={person.name} checked={form.participant_ids.includes(person.id)} onChange={event => change('participant_ids', event.target.checked ? [...form.participant_ids, person.id] : form.participant_ids.filter(id => id !== person.id))} /><span className="task-person-initials" aria-hidden="true">{person.name.split(/\s+/).slice(0, 2).map(word => word[0]).join('')}</span><span className="task-person-name" translate="no">{person.name}</span></label>)}
                {!matchingPeople.length && <small>{projectPeople.length ? 'Nenhuma pessoa encontrada para esta busca.' : 'Nenhum outro integrante disponível neste projeto.'}</small>}
              </div>
            </>}

          </fieldset>
          <label htmlFor="task-worked_on" className={`task-schedule-field ${!form.worked_on ? 'task-required-missing' : ''}`}><span>Data da tarefa {!form.worked_on && <em>Obrigatório</em>}</span><input id="task-worked_on" aria-label="Data da tarefa" type="date" required value={form.worked_on} onChange={event => change('worked_on', event.target.value)} /><small className={!form.worked_on ? '' : 'is-placeholder'}>{!form.worked_on ? 'Selecione a data em que a atividade foi realizada.' : '\u00a0'}</small></label>
          <label htmlFor="task-type" className={`task-schedule-field ${!form.activity_type_id ? 'task-required-missing' : ''}`}><span>Tipo de tarefa {!form.activity_type_id && <em>Obrigatório</em>}</span><select id="task-type" aria-label="Tipo de tarefa" required value={form.activity_type_id} onChange={event => change('activity_type_id', event.target.value)}><option value="">Selecione um tipo</option>{(data.activity_types || []).map(type => <option key={type.id} value={type.id} translate="no">{type.name}</option>)}</select><small className={!form.activity_type_id ? '' : 'is-placeholder'}>{!form.activity_type_id ? 'Escolha um tipo ativo para continuar.' : '\u00a0'}</small></label>
          <label htmlFor="task-description" className="task-field-wide">Descrição<textarea aria-label="Descrição" id="task-description" required maxLength={2000} rows={3} value={form.description} onChange={event => change('description', event.target.value)} /></label>
          <div className="task-time-fields task-field-wide" role="group" aria-label="Tempo da tarefa"><label htmlFor="task-overtime">Hora extra?<select id="task-overtime" aria-label="Hora extra?" value={String(form.is_overtime)} onChange={event => change('is_overtime', event.target.value === 'true')}><option value="false">Não</option><option value="true">Sim</option></select></label><label htmlFor="task-hours">Horas<input id="task-hours" type="number" min="0" max="24" required value={form.hours} onChange={event => change('hours', event.target.value)} /></label><label htmlFor="task-minutes">Minutos<input id="task-minutes" type="number" min="0" max="59" required value={form.minutes} onChange={event => change('minutes', event.target.value)} /></label></div>
          <details className="task-field-wide"><summary>Status e prioridade</summary><div className="task-extra-fields"><label htmlFor="task-status">Status<select id="task-status" aria-label="Status" value={form.status} onChange={event => change('status',event.target.value)}>{Object.entries(statuses).map(([value,label]) => <option value={value} key={value}>{label}</option>)}</select></label><label htmlFor="task-priority">Prioridade<select id="task-priority" aria-label="Prioridade" value={form.priority} onChange={event => change('priority',event.target.value)}>{Object.entries(priorities).map(([value,label]) => <option value={value} key={value}>{label}</option>)}</select></label></div></details>
          {formError && <p role="alert" className="new-project-error">{formError}</p>}
        </div>
        <footer><button type="button" className="secondary-button" disabled={saving} onClick={closeEditor}>Cancelar</button><button type="submit" className="primary-action" disabled={saving || !data.projects.length || !canSave}>{saving ? 'Salvando...' : 'Salvar tarefa'}</button></footer>
      </form>}
    </dialog>
    {pendingDelete && <div className="task-delete-backdrop" role="presentation" onMouseDown={event => event.target === event.currentTarget && deleting === null && setPendingDelete(null)}>
      <section className="task-delete-dialog" role="alertdialog" aria-modal="true" aria-labelledby="task-delete-title" aria-describedby="task-delete-description">
        <button className="task-delete-close" type="button" aria-label="Fechar" disabled={deleting !== null} onClick={() => setPendingDelete(null)}><X size={19} /></button>
        <span className="task-delete-icon"><Trash2 size={23} /></span>
        <h2 id="task-delete-title">Excluir tarefa?</h2>
        <p id="task-delete-description">Você está prestes a excluir <strong translate="no">{pendingDelete.description || pendingDelete.title}</strong>. Essa ação não pode ser desfeita.</p>
        <footer><button type="button" className="secondary-button" disabled={deleting !== null} onClick={() => setPendingDelete(null)}>Cancelar</button><button type="button" className="task-delete-confirm" disabled={deleting !== null} onClick={() => remove(pendingDelete)}>{deleting !== null ? 'Excluindo...' : 'Excluir tarefa'}</button></footer>
      </section>
    </div>}
  </div>;
}
