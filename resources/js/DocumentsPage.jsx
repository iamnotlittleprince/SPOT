import React, { useEffect, useId, useRef, useState } from "react";
import { Search, Upload, Download, Trash2, Files, Link2, X } from "lucide-react";
import { sessionRequest } from "./api";
import { message, getFormatLocale } from "./i18n";
import useModalFocus from "./useModalFocus";

function AssignmentFields({projects, tasks, projectId, taskId, onChange, disabled, errors = {}}) {
  const id = useId();
  return <div className="document-assignment-fields">
    <div><label htmlFor={`${id}-project`}>Projeto</label><select id={`${id}-project`} aria-invalid={errors.project_id ? true : undefined} aria-describedby={errors.project_id ? `${id}-project-error` : undefined} disabled={disabled} value={projectId} onChange={event => onChange(event.target.value, "")}>
      <option value="">Sem projeto</option>{projects.map(project => <option key={project.id} value={project.id} translate="no">{project.name}</option>)}
    </select>{errors.project_id && <small id={`${id}-project-error`}>{errors.project_id.join(" ")}</small>}</div>
    <div><label htmlFor={`${id}-task`}>Tarefa</label><select id={`${id}-task`} aria-invalid={errors.task_id ? true : undefined} aria-describedby={errors.task_id ? `${id}-task-error` : undefined} disabled={disabled} value={taskId} onChange={event => { const task = tasks.find(item => String(item.id) === event.target.value); onChange(task ? String(task.project_id) : projectId, event.target.value); }}>
      <option value="">Sem tarefa</option>{tasks.filter(task => !projectId || String(task.project_id) === String(projectId)).map(task => <option key={task.id} value={task.id} translate="no">{task.title}</option>)}
    </select>{errors.task_id && <small id={`${id}-task-error`}>{errors.task_id.join(" ")}</small>}</div>
  </div>;
}

function AssignmentDialog({document, projects, tasks, onClose, onSaved}) {
  const [projectId, setProjectId] = useState(String(document.project_id || ""));
  const [taskId, setTaskId] = useState(String(document.task_id || ""));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [errors, setErrors] = useState({});
  const ref = useModalFocus(true, () => { if (!saving) onClose(); });
  // Keep an existing association visible even if access to its destination was removed.
  const projectOptions = projects.some(item => String(item.id) === String(document.project_id)) || !document.project_id ? projects : [...projects, {id: document.project_id, name: document.project?.name || message("Projeto indisponível")}];
  const taskOptions = tasks.some(item => String(item.id) === String(document.task_id)) || !document.task_id ? tasks : [...tasks, {id: document.task_id, project_id: document.project_id, title: document.task?.title || message("Tarefa indisponível")}];
  async function save(event) {
    event.preventDefault(); setSaving(true); setError(""); setErrors({});
    try {
      const response = await sessionRequest(`/documents/${document.id}`, {method: "PATCH", body: JSON.stringify({project_id: projectId ? Number(projectId) : null, task_id: taskId ? Number(taskId) : null})});
      const data = await response.json();
      if (!response.ok) { setErrors(data.errors || {}); throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || message("Não foi possível atualizar o vínculo.")); }
      onSaved(data);
    } catch (failure) { setError(failure.message); } finally { setSaving(false); }
  }
  return <div className="document-dialog-backdrop"><form className="document-dialog" ref={ref} role="dialog" aria-modal="true" aria-labelledby="document-assignment-title" aria-describedby="document-assignment-help" tabIndex={-1} onSubmit={save}>
    <header><h2 id="document-assignment-title">Atribuir documento</h2><button type="button" aria-label="Fechar" disabled={saving} onClick={onClose}><X size={20} /></button></header>
    <p translate="no">{document.name}</p><p id="document-assignment-help">Escolha um projeto ou tarefa. O documento continua privado.</p>
    <AssignmentFields projects={projectOptions} tasks={taskOptions} projectId={projectId} taskId={taskId} errors={errors} disabled={saving} onChange={(project, task) => {setProjectId(project); setTaskId(task);}} />
    {error && <p role="alert" className="documents-feedback error">{error}</p>}
    <footer><button className="secondary-button" type="button" disabled={saving} onClick={onClose}>Cancelar</button><button className="primary-action" disabled={saving} type="submit">{saving ? "Salvando..." : "Salvar vínculo"}</button></footer>
  </form></div>;
}

export default function DocumentsPage() {
  const [documents, setDocuments] = useState([]);
  const [projects, setProjects] = useState([]);
  const [tasks, setTasks] = useState([]);
  const [uploadTask, setUploadTask] = useState("");
  const [editing, setEditing] = useState(null);
  const [search, setSearch] = useState("");
  const [projectFilter, setProjectFilter] = useState("all");
  const [uploadProject, setUploadProject] = useState("");
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [feedback, setFeedback] = useState(null);
  const fileInput = useRef(null);

  async function load() {
    setLoading(true);
    const documentResponse = await sessionRequest("/documents");
    const documentData = await documentResponse.json();
    if (!documentResponse.ok) throw new Error(documentData.message || "Não foi possível carregar os documentos.");
    setDocuments(documentData.documents || []);
    setProjects(documentData.projects || []);
    setTasks(documentData.tasks || []);
    setLoading(false);
  }

  useEffect(() => { load().catch((error) => { setFeedback({ type: "error", text: error.message }); setLoading(false); }); }, []);

  async function uploadDocument(event) {
    const file = event.target.files?.[0];
    event.target.value = "";
    if (!file) return;
    setUploading(true); setFeedback(null);
    const body = new FormData();
    body.append("file", file);
    if (uploadProject) body.append("project_id", uploadProject);
    if (uploadTask) body.append("task_id", uploadTask);
    try {
      const response = await sessionRequest("/documents", { method: "POST", body });
      const data = await response.json();
      if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || "Não foi possível enviar o arquivo.");
      setDocuments((current) => [data, ...current]);
      setFeedback({ type: "success", text: message("{name} foi enviado.", { name: file.name }) });
    } catch (error) { setFeedback({ type: "error", text: error.message }); }
    finally { setUploading(false); }
  }

  async function removeDocument(document) {
    if (!window.confirm(message("Excluir “{name}”?", { name: document.name }))) return;
    const response = await sessionRequest(`/documents/${document.id}`, { method: "DELETE", body: "{}" });
    if (response.ok) setDocuments((current) => current.filter((item) => item.id !== document.id));
    else setFeedback({ type: "error", text: "Não foi possível excluir o documento." });
  }

  const filtered = documents.filter((document) => projectFilter === "all" || String(document.project_id || "none") === projectFilter)
    .filter((document) => `${document.name} ${document.project?.name || ""} ${document.task?.title || ""}`.toLocaleLowerCase("pt-BR").includes(search.trim().toLocaleLowerCase("pt-BR")));
  const formatSize = (bytes) => bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
  const extension = (name) => name.includes(".") ? name.split(".").pop().toUpperCase() : "ARQ";

  return <div className="documents-page workspace-page">
    <div className="workspace-heading"><div><span className="workspace-eyebrow">ARQUIVOS</span><h1>Meus documentos</h1><p>Organize documentos e vincule a um projeto ou tarefa.</p></div></div>
    <section className="documents-upload-card" aria-labelledby="document-upload-title">
      <div className="documents-upload-intro"><span className="documents-upload-icon" aria-hidden="true"><Upload size={22} /></span><div><h2 id="document-upload-title">Novo documento</h2><p>Escolha o vínculo e envie seu arquivo.</p></div></div>
      <div className="documents-upload-actions"><AssignmentFields projects={projects} tasks={tasks} projectId={uploadProject} taskId={uploadTask} disabled={uploading || loading} onChange={(project, task) => { setUploadProject(project); setUploadTask(task); }} /><button className="primary-action" disabled={uploading || loading} type="button" onClick={() => fileInput.current?.click()}><Upload size={17} /> {uploading ? "Enviando..." : "Enviar arquivo"}</button><input ref={fileInput} hidden type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.zip" onChange={uploadDocument} /></div>
      <small className="documents-hint">Arquivos permitidos: PDF, Office, texto, imagens e ZIP · limite de 10 MB.</small>
    </section>
    {feedback && <p role={feedback.type === "error" ? "alert" : "status"} className={`documents-feedback ${feedback.type}`}>{feedback.text}</p>}
    <div className="documents-toolbar"><label><Search size={18} /><input value={search} onChange={(event) => setSearch(event.target.value)} aria-label="Buscar documentos" placeholder="Buscar documentos..." /></label><select aria-label="Filtrar por projeto" value={projectFilter} onChange={(event) => setProjectFilter(event.target.value)}><option value="all">Todos os projetos</option><option value="none">Sem projeto</option>{projects.map((project) => <option value={project.id} key={project.id} translate="no">{project.name}</option>)}</select><span>{filtered.length} arquivo{filtered.length === 1 ? "" : "s"}</span></div>
    <section className="documents-panel">
      <header><span>Nome</span><span>Projeto / tarefa</span><span>Tamanho</span><span>Enviado em</span><span>Ações</span></header>
      {loading ? <p className="documents-empty">Carregando documentos...</p> : !filtered.length ? <div className="documents-empty"><Files size={38} /><strong>Nenhum documento encontrado</strong><span>Envie um arquivo para começar.</span></div> : filtered.map((document) => <article key={document.id}><span className="document-name"><i>{extension(document.name)}</i><strong title={document.name} translate="no">{document.name}</strong></span><span className="document-assignment"><span translate={document.project ? "no" : undefined}>{document.project?.name || "Sem projeto"}</span>{document.task && <small translate="no">{document.task.title}</small>}</span><span>{formatSize(document.size)}</span><span>{new Date(document.created_at).toLocaleDateString(getFormatLocale())}</span><span className="document-actions"><button type="button" title="Atribuir documento" aria-label={message("Atribuir {name}", { name: document.name })} onClick={() => setEditing(document)}><Link2 size={18} /></button><a href={`/api/v1/documents/${document.id}/download`} title="Baixar documento"><Download size={18} /></a><button type="button" title="Excluir documento" onClick={() => removeDocument(document)}><Trash2 size={18} /></button></span></article>)}
    </section>
    {editing && <AssignmentDialog document={editing} projects={projects} tasks={tasks} onClose={() => setEditing(null)} onSaved={(saved) => { setDocuments(current => current.map(item => item.id === saved.id ? saved : item)); setEditing(null); setFeedback({type: "success", text: "Vínculo do documento atualizado."}); }} />}
  </div>;
}

