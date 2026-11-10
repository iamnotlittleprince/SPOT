import React, {useEffect, useRef, useState} from 'react';
import {CheckCheck, Download, Files, ListChecks, Search} from 'lucide-react';
import {getFormatLocale, message} from './i18n';
import useAutoRefresh from './useAutoRefresh';

const statuses = {todo:'A fazer', in_progress:'Em andamento', review:'Em revisão', done:'Concluído'};
export default function ProjectResources({projectId, request}) {
  const [data,setData] = useState({tasks:[],documents:[]});
  const [loading,setLoading] = useState(true);
  const [error,setError] = useState('');
  const [taskSearch,setTaskSearch] = useState('');
  const [documentSearch,setDocumentSearch] = useState('');
  const generation = useRef(0);
  async function load(background=false, version=generation.current) {
    if (!background) setLoading(true);
    try {
      const response=await request('/projects/'+projectId+'/resources',{handledStatuses:[403,404,500,502,503,504]});
      const result=await response.json();
      if (!response.ok) throw new Error([403,404].includes(response.status) ? 'O projeto não está disponível para sua conta.' : result.message || 'Não foi possível carregar tarefas e documentos do projeto.');
      if (version!==generation.current) return;
      setData({tasks:result.tasks || [],documents:result.documents || []});setError('');
    } catch (failure) { if(version===generation.current) setError(failure instanceof SyntaxError || failure instanceof TypeError ? 'Não foi possível carregar tarefas e documentos do projeto.' : failure.message); }
    finally {if(version===generation.current) setLoading(false);}
  }
  useEffect(()=>{
    const version=++generation.current;
    setData({tasks:[],documents:[]});setTaskSearch('');setDocumentSearch('');
    load(false,version);
    return ()=>{generation.current++;};
  },[projectId]);
  useAutoRefresh(()=>load(true));
  const includes=(value,search)=>String(value || '').toLocaleLowerCase().includes(search.trim().toLocaleLowerCase());
  const tasks=data.tasks.filter(task=>includes(task.title+' '+(task.user?.name || ''),taskSearch));
  const documents=data.documents.filter(document=>includes(document.name,documentSearch));
  const completed=data.tasks.filter(task=>task.status==='done').length;
  const size=bytes=>new Intl.NumberFormat(getFormatLocale(),{maximumFractionDigits:1}).format(bytes/(bytes>=1048576?1048576:1024))+(bytes>=1048576?' MB':' KB');
  const date=value=>value?new Date(String(value).slice(0,10)+'T12:00:00').toLocaleDateString(getFormatLocale()):'—';
  return <div className="project-resources">
    <p className="project-resources-announcement" role="status">{!loading && !error ? message("Tarefas disponíveis: {tasks}. Documentos disponíveis: {documents}.",{tasks:data.tasks.length,documents:data.documents.length}) : ""}</p>
    {loading && <p role="status" className="project-resources-state">Carregando tarefas e documentos...</p>}
    {error && <div className="project-resources-state" role="alert"><p>{error}</p><button type="button" onClick={()=>load()}>Tentar novamente</button></div>}
    <div className="project-resource-grid" aria-busy={loading}>
      <section className="project-resource-card" aria-labelledby="project-tasks-title">
        <header><div className="project-resource-heading"><ListChecks size={21} aria-hidden="true"/><div><h3 id="project-tasks-title">Tarefas do projeto</h3><p>Tarefas vinculadas disponíveis para você.</p></div></div><span className="project-resource-count">{data.tasks.length}</span></header>
        {!loading && !error && <><div className="project-task-progress"><span><CheckCheck size={16} aria-hidden="true"/>{message('{done} de {total} concluídas',{done:completed,total:data.tasks.length})}</span><progress max={Math.max(1,data.tasks.length)} value={completed} aria-label="Tarefas concluídas"/></div>
        <label className="project-resource-search"><span>Buscar tarefa no projeto</span><div><Search size={16} aria-hidden="true"/><input type="search" value={taskSearch} onChange={event=>setTaskSearch(event.target.value)}/></div></label>
        {tasks.length ? <ul className="project-resource-list">{tasks.map(task=><li key={task.id}><div className="project-task-row"><strong translate="no">{task.title}</strong><span className={'project-task-status status-'+task.status}>{statuses[task.status] || task.status}</span></div><dl className="project-resource-meta"><div><dt>Responsável</dt><dd translate={task.user?.name?'no':undefined}>{task.user?.name || 'Não informado'}</dd></div><div><dt>Prazo</dt><dd>{date(task.due_date)}</dd></div></dl>{task.description && <details><summary>Descrição da tarefa</summary><p translate="no">{task.description}</p></details>}</li>)}</ul>:<div className="management-empty compact"><strong>{data.tasks.length?'Nenhuma tarefa corresponde à busca.':'Nenhuma tarefa vinculada ao projeto.'}</strong></div>}</>}
      </section>
      <section className="project-resource-card" aria-labelledby="project-documents-title">
        <header><div className="project-resource-heading"><Files size={21} aria-hidden="true"/><div><h3 id="project-documents-title">Documentos do projeto</h3><p>Arquivos vinculados disponíveis para você.</p></div></div><span className="project-resource-count">{data.documents.length}</span></header>
        {!loading && !error && <><label className="project-resource-search"><span>Buscar documento no projeto</span><div><Search size={16} aria-hidden="true"/><input type="search" value={documentSearch} onChange={event=>setDocumentSearch(event.target.value)}/></div></label>
        {documents.length ? <ul className="project-resource-list">{documents.map(document=><li key={document.id} className="project-document-row"><div><strong translate="no">{document.name}</strong><p>{size(document.size)} · <time dateTime={document.created_at}>{date(document.created_at)}</time></p></div><a href={'/api/v1/documents/'+document.id+'/download'} aria-label={message('Baixar {name}',{name:document.name})}><Download size={16} aria-hidden="true"/><span>Baixar</span></a></li>)}</ul>:<div className="management-empty compact"><strong>{data.documents.length?'Nenhum documento corresponde à busca.':'Nenhum documento vinculado ao projeto.'}</strong></div>}</>}
      </section>
    </div>
  </div>;
}
