import { ProjectWorkspace, ParametersWorkspace, FinancialResult } from './Management';
import { message, translateText, observeTranslations, getLanguage, getFormatLocale, setLanguage } from "./i18n";
import React, { useCallback, useEffect, useRef, useState } from "react";
import InventoryApp from "./InventoryApp";
import TasksPage from "./TasksPage";
import SpotTutorial from "./tutorial/SpotTutorial";
import { sessionRequest } from "./api";
import {
  Archive,
  AppWindow,
  ArrowDownUp,
  BarChart3,
  Bell,
  Building2,
  CalendarDays,
  Camera,
  Check,
  CheckCheck,
  CheckCircle2,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  CircleUserRound,
  Clock3,
  ClipboardList,
  Eye,
  EyeOff,
  Download,
  FilePlus2,
  Files,
  Filter,
  FolderClock,
  FolderKanban,
  History,
  Home as HomeIcon,
  LayoutDashboard,
  ListChecks,
  LogOut,
  LockKeyhole,
  Trash2,
  Upload,
  KeyRound,
  Laptop,
  Link2,
  Mail,
  Menu,
  MoreHorizontal,
  Plus,
  Search,
  Settings,
  ShieldCheck,
  SlidersHorizontal,
  Sun,
  TrendingUp,
  Users,
  Video,
  UserRound,
  Moon,
  X,
} from "lucide-react";

// Fonte única da navegação lateral. O segundo item de cada entrada é também
// usado pelo catálogo de traduções; altere os dois em conjunto ao renomeá-lo.
const navGroups = [
  {
    items: [
      [HomeIcon, "Home", true],
      [Bell, "Caixa de entrada"],
      [LayoutDashboard, "Portfólios"],
      [CalendarDays, "Agenda"],
    ],
  },
  {
    title: "Área de trabalho",
    items: [
      [FolderKanban, "Meus Projetos"],
      [Users, "Equipes"],
      [Building2, "Clientes"],
      [FilePlus2, "Novo Projeto"],
      [ListChecks, "Minhas tarefas"],
      [Files, "Meus documentos"],
      [History, "Histórico"],
    ],
  },
];

function Sidebar({ open, onClose, activePage, onNavigate, onInboxEnter, onInboxLeave, inboxPinned, canViewParameters }) {
  const [expandedGroups, setExpandedGroups] = useState(() => navGroups.map(() => true));
  const [quickExpanded, setQuickExpanded] = useState(false);
  const [recentItems, setRecentItems] = useState(() => {
    try { return JSON.parse(window.localStorage.getItem("spot.sidebar.recent") || "[]"); }
    catch { return []; }
  });
  const pageByLabel = {
    Home: "home",
    "Caixa de entrada": "inbox",
    "Portfólios": "analytics",
    Agenda: "calendar",
    "Meus Projetos": "projects",
    "Equipes": "teams",
    "Clientes": "clients",
    "Novo Projeto": "new-project",
    "Minhas tarefas": "tasks",
    "Meus documentos": "documents",
    "Histórico": "history",
  };
  const itemByLabel = Object.fromEntries(navGroups.flatMap((group) => group.items).map((item) => [item[1], item]));

  function remember(label) {
    setRecentItems((current) => {
      const updated = [label, ...current.filter((item) => item !== label)].slice(0, 8);
      window.localStorage.setItem("spot.sidebar.recent", JSON.stringify(updated));
      return updated;
    });
  }

  function activate(label) {
    remember(label);
    const page = pageByLabel[label];
    if (page) onNavigate(page);
    onClose();
  }

  return (
    <>
      <button className={`sidebar-scrim ${open ? "is-open" : ""}`} onClick={onClose} aria-label="Fechar menu" />
      <aside className={`dashboard-sidebar ${open ? "is-open" : ""} ${inboxPinned ? "inbox-pinned" : ""}`}>
        <div className="sidebar-brand">
          <img src="/computecnica-logo.png" alt="Computécnica" />
          <button type="button" onClick={onClose} aria-label="Fechar menu"><X size={22} /></button>
        </div>

        {navGroups.map((group, groupIndex) => (
          <nav className="nav-group" key={group.title || "principal"}>
            {group.title && <h3>{group.title}</h3>}
            {(expandedGroups[groupIndex] ? group.items.filter(([, label]) => label !== "Clientes" || canViewParameters) : group.items.filter(([, label]) => label !== "Clientes" || canViewParameters).slice(0, 3)).map(([Icon, label]) => {
              const page = pageByLabel[label];
              return (
              <button
                className={page && activePage === page ? "active" : ""}
                type="button"
                key={label}
                onClick={() => {
                  activate(label);
                }}
                onMouseEnter={() => label === "Caixa de entrada" && onInboxEnter("sidebar")}
                onMouseLeave={() => label === "Caixa de entrada" && onInboxLeave()}
              >
                <Icon size={18} strokeWidth={1.6} />
                <span>{label}</span>
              </button>
              );
            })}
            <button className="sidebar-toggle" type="button" onClick={() => setExpandedGroups((current) => current.map((value, index) => index === groupIndex ? !value : value))}>{expandedGroups[groupIndex] ? "Mostrar menos" : "Mostrar mais"}</button>
            {groupIndex === 0 && <div className="sidebar-rule" />}
          </nav>
        ))}

        <section className="quick-access-section">
          <div className="quick-access"><FolderClock size={21} /><span>Acesso rápido</span></div>
          <nav className="quick-access-list" aria-label="Acessos recentes">
            {(quickExpanded ? recentItems : recentItems.slice(0, 3)).map((label) => {
              const item = itemByLabel[label];
              if (!item || (label === "Clientes" && !canViewParameters)) return null;
              const [Icon] = item;
              return <button type="button" key={label} onClick={() => activate(label)} title={label}><Icon size={18} strokeWidth={1.6} /><span>{label}</span></button>;
            })}
            {!recentItems.length && <small className="quick-access-empty">Os últimos acessos aparecerão aqui.</small>}
          </nav>
          {!!recentItems.length && <button className="show-more" type="button" onClick={() => setQuickExpanded((value) => !value)}>{quickExpanded ? "Mostrar menos" : "Mostrar mais"}</button>}
        </section>
      </aside>
    </>
  );
}

function InboxPopover({ placement, onMouseEnter, onMouseLeave, onOpenInbox }) {
  const [items, setItems] = useState([]);
  const [filter, setFilter] = useState("all");
  const [menuOpen, setMenuOpen] = useState(false);
  const [itemMenu, setItemMenu] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    sessionRequest("/inbox").then(async (response) => {
      const data = await response.json();
      if (active && response.ok) setItems(data.items || []);
    }).finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, []);

  const unread = items.filter((item) => !item.read_at).length;
  const visibleItems = (filter === "unread" ? items.filter((item) => !item.read_at) : items).slice(0, 3);
  const typeIcons = { task: CheckCircle2, document: Files, mention: Users, update: Bell };

  async function markRead(item) {
    if (item.read_at) return;
    const response = await sessionRequest(`/inbox/${item.id}/read`, { method: "PATCH", body: "{}" });
    const data = await response.json();
    if (response.ok) setItems((current) => current.map((entry) => entry.id === item.id ? data.item : entry));
    setItemMenu(null);
  }

  async function markAllRead() {
    const response = await sessionRequest("/inbox/read-all", { method: "PATCH", body: "{}" });
    if (response.ok) {
      const now = new Date().toISOString();
      setItems((current) => current.map((item) => ({ ...item, read_at: item.read_at || now })));
    }
    setMenuOpen(false);
  }

  async function archivePreview(item) {
    const response = await sessionRequest(`/inbox/${item.id}`, { method: "DELETE", body: "{}" });
    if (response.ok) setItems((current) => current.filter((entry) => entry.id !== item.id));
    setItemMenu(null);
  }

  function relativeTime(value) {
    const difference = Math.max(0, Date.now() - new Date(value).getTime());
    const minutes = Math.floor(difference / 60000);
    if (minutes < 1) return "Agora";
    if (minutes < 60) return message(minutes === 1 ? "Há {count} minuto" : "Há {count} minutos", { count: minutes });
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return message(hours === 1 ? "Há {count} hora" : "Há {count} horas", { count: hours });
    const days = Math.floor(hours / 24);
    return message(days === 1 ? "Há {count} dia" : "Há {count} dias", { count: days });
  }

  return (
    <aside
      className={`inbox-popover ${placement === "top" ? "from-top" : "from-sidebar"}`}
      onMouseEnter={onMouseEnter}
      onMouseLeave={onMouseLeave}
      aria-label="Prévia da caixa de entrada"
    >
      <header>
        <div><span>Caixa de entrada</span><b>{unread}</b></div>
        <button type="button" aria-label="Opções da caixa de entrada" aria-expanded={menuOpen} onClick={() => setMenuOpen((open) => !open)}><MoreHorizontal size={19} /></button>
        {menuOpen && <div className="inbox-options-menu"><button type="button" disabled={!unread} onClick={markAllRead}><CheckCheck size={16} /> Marcar todas como lidas</button><button type="button" onClick={onOpenInbox}><Mail size={16} /> Abrir caixa de entrada</button></div>}
      </header>
      <div className="inbox-filter">
        <button className={filter === "all" ? "active" : ""} type="button" onClick={() => setFilter("all")}>Tudo</button>
        <button className={filter === "unread" ? "active" : ""} type="button" onClick={() => setFilter("unread")}>Não lidas <span>{unread}</span></button>
      </div>
      <div className="inbox-items">
        {loading && <p className="inbox-preview-empty">Carregando mensagens...</p>}
        {!loading && !visibleItems.length && <p className="inbox-preview-empty">{filter === "unread" ? "Nenhuma mensagem não lida." : "Sua caixa de entrada está vazia."}</p>}
        {visibleItems.map((item) => { const TypeIcon = typeIcons[item.type] || Bell; return <React.Fragment key={item.id}><article className={`inbox-item ${!item.read_at ? "unread" : ""}`} onClick={() => markRead(item)}>
          <div className={`inbox-item-icon ${item.type}`}><TypeIcon size={18} /></div>
          <div className="inbox-item-content"><strong translate="no">{item.title}</strong><span><CircleUserRound size={24} fill="#d3d9de" stroke="#fff" /> {item.project?.name || item.body || "Spot"}</span></div>
          <div className="inbox-item-actions"><button type="button" aria-label={`Opções de ${item.title}`} aria-expanded={itemMenu === item.id} onClick={(event) => { event.stopPropagation(); setItemMenu((current) => current === item.id ? null : item.id); }}><MoreHorizontal size={18} /></button>{itemMenu === item.id && <div className="inbox-item-menu">{!item.read_at && <button type="button" onClick={(event) => { event.stopPropagation(); markRead(item); }}><Check size={15} /> Marcar como lida</button>}<button type="button" onClick={(event) => { event.stopPropagation(); archivePreview(item); }}><Archive size={15} /> Arquivar</button></div>}</div>
        </article><small className="inbox-time">{relativeTime(item.created_at)}</small></React.Fragment>; })}
      </div>
      <button className="open-inbox-button" type="button" onClick={onOpenInbox}>Abrir caixa de entrada</button>
    </aside>
  );
}

function InboxPage() {
  const [items, setItems] = useState([]);
  const [selectedId, setSelectedId] = useState(null);
  const [filter, setFilter] = useState("all");
  const [search, setSearch] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(true);
  const [trashCount, setTrashCount] = useState(0);
  const [confirmClear, setConfirmClear] = useState(false);
  const trashMode = filter === "trash";

  async function load() {
    setLoading(true); setError("");
    const response = await sessionRequest(trashMode ? "/inbox?filter=trash" : "/inbox");
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || "Não foi possível carregar a caixa de entrada.");
    setItems(data.items);
    setTrashCount(data.trash_count || 0);
    setSelectedId((current) => current && data.items.some((item) => item.id === current) ? current : data.items[0]?.id || null);
    setLoading(false);
  }
  useEffect(() => { load().catch((requestError) => { setError(requestError.message); setLoading(false); }); }, [trashMode]);

  const filtered = items.filter((item) => trashMode || filter === "all" || (filter === "unread" ? !item.read_at : item.type === filter.slice(0, -1)))
    .filter((item) => `${item.title} ${item.body || ""} ${item.project?.name || ""}`.toLocaleLowerCase("pt-BR").includes(search.trim().toLocaleLowerCase("pt-BR")));
  const selected = items.find((item) => item.id === selectedId) || null;
  const unread = items.filter((item) => !item.read_at).length;
  const typeMeta = { task: [CheckCircle2, "Tarefa"], document: [Files, "Documento"], mention: [Users, "Menção"], update: [Bell, "Atualização"] };

  async function markRead(item) {
    if (item.read_at) return setSelectedId(item.id);
    const response = await sessionRequest(`/inbox/${item.id}/read`, { method: "PATCH", body: "{}" });
    const data = await response.json();
    if (!response.ok) return setError(data.message || "Não foi possível marcar como lida.");
    setItems((current) => current.map((entry) => entry.id === item.id ? data.item : entry)); setSelectedId(item.id);
  }
  async function markAllRead() {
    const response = await sessionRequest("/inbox/read-all", { method: "PATCH", body: "{}" });
    if (!response.ok) return setError("Não foi possível marcar todas como lidas.");
    const now = new Date().toISOString(); setItems((current) => current.map((item) => ({ ...item, read_at: item.read_at || now })));
  }
  async function archive(item) {
    const response = await sessionRequest(`/inbox/${item.id}`, { method: "DELETE", body: "{}" });
    if (!response.ok) return setError("Não foi possível arquivar a mensagem.");
    setItems((current) => current.filter((entry) => entry.id !== item.id)); setSelectedId(null);
    setTrashCount((current) => current + 1);
  }
  async function clearInbox() {
    const response = await sessionRequest("/inbox", { method: "DELETE", body: "{}" });
    const data = await response.json();
    if (!response.ok) return setError(data.message || "Não foi possível limpar a caixa de entrada.");
    setConfirmClear(false); setItems([]); setSelectedId(null);
    setTrashCount((current) => current + Number(data.archived_count || 0));
  }
  async function restore(item) {
    const response = await sessionRequest(`/inbox/${item.id}/restore`, { method: "PATCH", body: "{}" });
    const data = await response.json();
    if (!response.ok) return setError(data.message || "Não foi possível restaurar a mensagem.");
    setItems((current) => current.filter((entry) => entry.id !== item.id)); setSelectedId(null);
    setTrashCount((current) => Math.max(0, current - 1));
  }

  return <div className="inbox-page workspace-page">
    <header className="inbox-page-heading"><div><span className="workspace-eyebrow">COMUNICAÇÃO</span><h1>{trashMode ? "Lixeira" : "Caixa de entrada"}</h1><p>{trashMode ? "As mensagens são excluídas definitivamente após 30 dias." : "Acompanhe tarefas, menções, documentos e atualizações dos seus projetos."}</p></div><div className="inbox-heading-actions"><button className={`inbox-trash-button ${trashMode ? "active" : ""}`} type="button" onClick={() => setFilter(trashMode ? "all" : "trash")} aria-label={trashMode ? "Voltar para a caixa de entrada" : "Abrir lixeira"}><Trash2 size={17} />{trashMode ? "Voltar à caixa" : "Lixeira"}{trashCount > 0 && <span>{trashCount}</span>}</button>{!trashMode && <><button className="inbox-clear-button" type="button" disabled={!items.length} onClick={() => setConfirmClear(true)}><Trash2 size={17} /> Limpar caixa</button><button className="secondary-button" type="button" disabled={!unread} onClick={markAllRead}><CheckCheck size={17} /> Marcar todas como lidas</button></>}</div></header>
    {error && <p className="home-state error">{error}</p>}
    <div className="inbox-page-toolbar"><label><Search size={18} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar mensagens..." /></label>{!trashMode && <div>{[["all", "Todas"], ["unread", message("Não lidas ({count})", { count: unread })], ["tasks", "Tarefas"], ["documents", "Documentos"], ["mentions", "Menções"]].map(([key, label]) => <button className={filter === key ? "active" : ""} type="button" key={key} onClick={() => setFilter(key)}>{label}</button>)}</div>}</div>
    <div className="inbox-page-layout">
      <section className="inbox-message-list" aria-label="Mensagens">{loading && <p className="inbox-page-empty">Carregando mensagens...</p>}{!loading && !filtered.length && <p className="inbox-page-empty">{trashMode ? "A lixeira está vazia." : "Nenhuma mensagem encontrada."}</p>}{filtered.map((item) => { const [TypeIcon, typeLabel] = typeMeta[item.type] || typeMeta.update; return <button className={`${selectedId === item.id ? "selected" : ""} ${!item.read_at && !trashMode ? "unread" : ""}`} type="button" key={item.id} onClick={() => trashMode ? setSelectedId(item.id) : markRead(item)}><span className={`inbox-type-icon ${item.type}`}><TypeIcon size={18} /></span><span><strong translate="no">{item.title}</strong><small>{item.project?.name || typeLabel}</small><em translate="no">{item.body}</em></span><time>{new Date(item.created_at).toLocaleDateString(getFormatLocale(), { day: "2-digit", month: "short" })}</time></button>; })}</section>
      <section className="inbox-message-detail">{!selected ? <div className="inbox-detail-empty"><Mail size={35} /><strong>Selecione uma mensagem</strong><span>Os detalhes aparecerão aqui.</span></div> : (() => { const [TypeIcon, typeLabel] = typeMeta[selected.type] || typeMeta.update; return <><header><span className={`inbox-type-icon ${selected.type}`}><TypeIcon size={19} /></span><span><small>{typeLabel}</small><h2 translate="no">{selected.title}</h2></span>{trashMode ? <button type="button" title="Restaurar" aria-label="Restaurar mensagem" onClick={() => restore(selected)}><Archive size={19} /></button> : <button type="button" title="Mover para a lixeira" aria-label="Mover mensagem para a lixeira" onClick={() => archive(selected)}><Trash2 size={19} /></button>}</header><div className="inbox-detail-body"><p>{selected.body || "Sem informações adicionais."}</p>{trashMode && <p className="inbox-trash-retention">Esta mensagem será excluída definitivamente 30 dias após ter sido movida para a lixeira.</p>}<dl><div><dt>Projeto</dt><dd>{selected.project?.name || "Geral"}</dd></div><div><dt>Enviado por</dt><dd>{selected.actor_name || "Spot"}</dd></div><div><dt>Recebido em</dt><dd>{new Date(selected.created_at).toLocaleString(getFormatLocale())}</dd></div></dl></div></>; })()}</section>
    </div>
    {confirmClear && <div className="inbox-confirm-overlay" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setConfirmClear(false)}><section role="alertdialog" aria-modal="true" aria-labelledby="clear-inbox-title" className="inbox-confirm-dialog"><span className="inbox-confirm-icon"><Trash2 size={24} /></span><h2 id="clear-inbox-title">Limpar a caixa de entrada?</h2><p>Todas as mensagens serão movidas para a lixeira. Você poderá restaurá-las durante 30 dias.</p><footer><button className="secondary-button" type="button" onClick={() => setConfirmClear(false)}>Cancelar</button><button className="inbox-confirm-action" type="button" onClick={clearInbox}><Trash2 size={16} /> Sim, limpar caixa</button></footer></section></div>}
  </div>;
}

function CalendarPage() {
  const [month, setMonth] = useState(() => new Date(new Date().getFullYear(), new Date().getMonth(), 1));
  const [data, setData] = useState({ events: [], providers: {}, errors: [] });
  const [selected, setSelected] = useState(null);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState("");
  const [createError, setCreateError] = useState("");
  const [createFieldErrors, setCreateFieldErrors] = useState({});
  const monthEnd = new Date(month.getFullYear(), month.getMonth() + 1, 1);
  async function load() {
    const params = new URLSearchParams({ start: month.toISOString(), end: monthEnd.toISOString() });
    const response = await sessionRequest(`/calendar?${params}`); const result = await response.json();
    if (!response.ok) throw new Error(result.message || "Não foi possível carregar a agenda."); setData(result);
  }
  useEffect(() => { load().catch((loadError) => setError(loadError.message)); }, [month.getTime()]);
  const gridStart = new Date(month); gridStart.setDate(1 - gridStart.getDay());
  const days = Array.from({ length: 42 }, (_, index) => { const day = new Date(gridStart); day.setDate(gridStart.getDate() + index); return day; });
  const eventsFor = (day) => data.events.filter((event) => new Date(event.start).toDateString() === day.toDateString());
  async function createEvent(event) {
    event.preventDefault(); setError(""); setCreateError(""); setCreateFieldErrors({}); const form = new FormData(event.currentTarget);
    const response = await sessionRequest('/calendar/events', { method: 'POST', body: JSON.stringify(Object.fromEntries(form)) }); const result = await response.json();
    if (!response.ok) {
      const errors = result.errors || {};
      setCreateFieldErrors(Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages])));
      return setCreateError(Object.values(errors).flat()[0] || result.message || 'Não foi possível criar o evento.');
    }
    setCreating(false); await load();
  }
  const providerLabel = { spot: 'Spot', google: 'Google', microsoft: 'Outlook', teams: 'Teams' };
  return <div className="calendar-page workspace-page">
    <header className="calendar-heading"><div><span className="workspace-eyebrow">PLANEJAMENTO</span><h1>Agenda</h1><p>Seus compromissos do Spot, Google Calendar, Outlook e Teams em um só lugar.</p></div><button className="primary-action" type="button" onClick={() => { setCreateError(""); setCreateFieldErrors({}); setCreating(true); }}><Plus size={17} /> Novo evento</button></header>
    <div className="calendar-connections"><span><i className="spot" /> Spot</span><span className={data.providers.google ? 'connected' : ''}><i className="google" /> Google Calendar {data.providers.google ? 'conectado' : 'desconectado'}</span><span className={data.providers.microsoft ? 'connected' : ''}><i className="microsoft" /> Microsoft/Teams {data.providers.microsoft ? 'conectado' : 'desconectado'}</span>{(!data.providers.google || !data.providers.microsoft) && <button type="button" onClick={() => window.scrollTo(0,0)}>Conectar em Apps</button>}</div>
    {(error || data.errors?.length > 0) && <p className="home-state error">{error || data.errors.join(' ')}</p>}
    <section className="calendar-shell"><header><div><button type="button" onClick={() => setMonth(new Date())}>Hoje</button><button type="button" aria-label="Mês anterior" onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth()-1,1))}><ChevronLeft size={18}/></button><button type="button" aria-label="Próximo mês" onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth()+1,1))}><ChevronRight size={18}/></button></div><h2>{month.toLocaleDateString(getFormatLocale(),{month:'long',year:'numeric'})}</h2><span>{data.events.length} eventos</span></header><div className="calendar-weekdays">{['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'].map(day=><span key={day}>{day}</span>)}</div><div className="calendar-grid">{days.map(day=><div className={`${day.getMonth()!==month.getMonth()?'outside ':''}${day.toDateString()===new Date().toDateString()?'today':''}`} key={day.toISOString()}><strong>{day.getDate()}</strong><div>{eventsFor(day).slice(0,3).map(event=><button className={event.provider} type="button" key={event.id} onClick={()=>setSelected(event)} title={event.title}><time>{new Date(event.start).toLocaleTimeString(getFormatLocale(),{hour:'2-digit',minute:'2-digit'})}</time><span translate="no">{event.title}</span></button>)}{eventsFor(day).length>3&&<small>+{eventsFor(day).length-3} eventos</small>}</div></div>)}</div></section>
    {selected && <div className="calendar-event-popover"><button type="button" onClick={()=>setSelected(null)}><X size={18}/></button><em className={selected.provider}>{providerLabel[selected.provider]}</em><h3 translate="no">{selected.title}</h3><p>{selected.description||'Sem descrição.'}</p><span><CalendarDays size={16}/>{new Date(selected.start).toLocaleString(getFormatLocale())} – {new Date(selected.end).toLocaleTimeString(getFormatLocale(),{hour:'2-digit',minute:'2-digit'})}</span>{selected.url&&<a href={selected.url} target="_blank" rel="noreferrer"><Video size={16}/> Abrir reunião ou evento</a>}</div>}
    {creating && <div className="calendar-modal" onMouseDown={event=>event.target===event.currentTarget&&setCreating(false)}><form onSubmit={createEvent}><header><div><h2>Novo evento</h2><p>Escolha em qual agenda o compromisso será criado.</p></div><button type="button" onClick={()=>setCreating(false)}><X size={19}/></button></header>{createError&&<p className="calendar-form-error" role="alert">{createError}</p>}<label>Título<input name="title" required maxLength="150" aria-invalid={Boolean(createFieldErrors.title)} />{createFieldErrors.title&&<small>{createFieldErrors.title}</small>}</label><div><label>Início<input name="starts_at" type="datetime-local" required aria-invalid={Boolean(createFieldErrors.starts_at)} />{createFieldErrors.starts_at&&<small>{createFieldErrors.starts_at}</small>}</label><label>Fim<input name="ends_at" type="datetime-local" required aria-invalid={Boolean(createFieldErrors.ends_at)} />{createFieldErrors.ends_at&&<small>{createFieldErrors.ends_at}</small>}</label></div><label>Agenda<select name="provider" required aria-invalid={Boolean(createFieldErrors.provider)}><option value="spot">Spot</option><option value="google" disabled={!data.providers.google}>Google Calendar{!data.providers.google?' — conecte a conta':''}</option><option value="microsoft" disabled={!data.providers.microsoft}>Outlook{!data.providers.microsoft?' — conecte a conta':''}</option><option value="teams" disabled={!data.providers.microsoft}>Reunião do Teams{!data.providers.microsoft?' — conecte a conta':''}</option></select>{createFieldErrors.provider&&<small>{createFieldErrors.provider}</small>}</label><label>Local<input name="location" placeholder="Sala ou endereço" aria-invalid={Boolean(createFieldErrors.location)} />{createFieldErrors.location&&<small>{createFieldErrors.location}</small>}</label><label>Descrição<textarea name="description" rows="4" aria-invalid={Boolean(createFieldErrors.description)} />{createFieldErrors.description&&<small>{createFieldErrors.description}</small>}</label><footer><button className="secondary-button" type="button" onClick={()=>setCreating(false)}>Cancelar</button><button className="primary-action" type="submit">Criar evento</button></footer></form></div>}
  </div>;
}

function AppsPopover({ onManage }) {
  const googleApps = [
    ["google-drive", "Drive", "https://drive.google.com/"], ["gmail", "Gmail", "https://mail.google.com/"],
    ["google-meet", "Meet", "https://meet.google.com/"], ["google-calendar", "Agenda", "https://calendar.google.com/"],
    ["google-docs", "Docs", "https://docs.google.com/document/"], ["google-sheets", "Planilhas", "https://docs.google.com/spreadsheets/"],
    ["google-slides", "Apresentações", "https://docs.google.com/presentation/"], ["google-chat", "Chat", "https://chat.google.com/"],
    ["google-forms", "Formulários", "https://docs.google.com/forms/"], ["google-keep", "Keep", "https://keep.google.com/"],
    ["google-sites", "Sites", "https://sites.google.com/"], ["google-tasks", "Tarefas", "https://tasks.google.com/"],
  ];
  const microsoftApps = [
    ["microsoft-outlook", "Outlook", "https://outlook.office.com/mail/"], ["microsoft-teams", "Teams", "https://teams.microsoft.com/"],
    ["microsoft-onedrive", "OneDrive", "https://www.microsoft365.com/launch/onedrive"], ["microsoft-word", "Word", "https://www.microsoft365.com/launch/word"],
    ["microsoft-excel", "Excel", "https://www.microsoft365.com/launch/excel"], ["microsoft-powerpoint", "PowerPoint", "https://www.microsoft365.com/launch/powerpoint"],
    ["microsoft-onenote", "OneNote", "https://www.microsoft365.com/launch/onenote"], ["microsoft-sharepoint", "SharePoint", "https://www.microsoft365.com/launch/sharepoint"],
    ["microsoft-planner", "Planner", "https://planner.cloud.microsoft/"], ["microsoft-forms", "Forms", "https://forms.office.com/"],
    ["microsoft-todo", "To Do", "https://to-do.office.com/tasks/"], ["microsoft-loop", "Loop", "https://loop.cloud.microsoft/"],
  ];

  return (
    <aside className="apps-popover" aria-label="Aplicativos integrados">
      <header><span>Apps</span><small>24 atalhos</small></header>
      <section>
        <div className="app-suite-title">
          <span><img src="/google-logo.svg" alt="" />Google Workspace</span>
          <small>12 aplicativos</small>
        </div>
        <div className="app-shortcuts">
          {googleApps.map(([icon, label, url]) => (
            <button type="button" key={label} title={`Abrir ${label}`} aria-label={`Abrir ${label}`} onClick={() => window.open(url, "_blank", "noopener,noreferrer")}>
              <span className="app-tile"><img src={`/apps/integrations/${icon}.svg?v=2`} alt="" /></span>
            </button>
          ))}
        </div>
      </section>
      <section>
        <div className="app-suite-title">
          <span><img src="/microsoft-logo.png" alt="" />Microsoft 365</span>
          <small>12 aplicativos</small>
        </div>
        <div className="app-shortcuts">
          {microsoftApps.map(([icon, label, url]) => (
            <button type="button" key={label} title={`Abrir ${label}`} aria-label={`Abrir ${label}`} onClick={() => window.open(url, "_blank", "noopener,noreferrer")}>
              <span className="app-tile"><img src={`/apps/integrations/${icon}.svg?v=2`} alt="" /></span>
            </button>
          ))}
        </div>
      </section>
      <button className="manage-apps" type="button" onClick={onManage}><span>Gerenciar integrações</span><span aria-hidden="true">→</span></button>
    </aside>
  );
}

function IntegrationsModal({ onClose }) {
  const [providers, setProviders] = useState(null);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState("");
  const providerMeta = {
    google: { name: "Google Workspace", logo: "/google-logo.svg", auth: "/auth/google", description: "Drive, Gmail, Agenda, Meet e ferramentas de produtividade.", services: ["Drive", "Gmail", "Agenda", "Meet", "Docs", "Planilhas", "Tarefas"] },
    microsoft: { name: "Microsoft 365", logo: "/microsoft-logo.png", auth: "/auth/microsoft", description: "Outlook, Teams, OneDrive e aplicativos do Microsoft 365.", services: ["Outlook", "Teams", "OneDrive", "Word", "Excel", "Planner", "To Do"] },
  };

  async function loadProviders() {
    setError("");
    const response = await sessionRequest("/security");
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || "Não foi possível carregar as integrações.");
    setProviders(data.providers);
  }

  useEffect(() => { loadProviders().catch((loadError) => setError(loadError.message)); }, []);
  useEffect(() => {
    const closeOnEscape = (event) => { if (event.key === "Escape") onClose(); };
    window.addEventListener("keydown", closeOnEscape);
    return () => window.removeEventListener("keydown", closeOnEscape);
  }, [onClose]);

  async function disconnect(provider) {
    if (!window.confirm(message("Desconectar sua conta {provider}?", { provider: providerMeta[provider].name }))) return;
    setBusy(provider); setError("");
    try {
      const response = await sessionRequest(`/security/providers/${provider}`, { method: "DELETE", body: "{}" });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || "Não foi possível desconectar a integração.");
      await loadProviders();
    } catch (disconnectError) {
      setError(disconnectError.message);
    } finally {
      setBusy("");
    }
  }

  return <div className="integrations-modal" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
    <section className="integrations-dialog" role="dialog" aria-modal="true" aria-labelledby="integrations-title">
      <header><div><span className="integration-heading-icon"><Link2 size={20} /></span><span><h2 id="integrations-title">Gerenciar integrações</h2><p>Conecte suas contas e controle o acesso do Spot aos aplicativos.</p></span></div><button type="button" onClick={onClose} aria-label="Fechar"><X size={21} /></button></header>
      {error && <p className="integration-feedback">{error}</p>}
      <div className="integration-provider-list">
        {Object.entries(providerMeta).map(([key, meta]) => {
          const provider = providers?.[key];
          const connected = Boolean(provider?.connected);
          return <article className="integration-provider" key={key}>
            <div className="integration-provider-main"><img src={meta.logo} alt="" /><span><strong translate="no">{meta.name}</strong><small>{meta.description}</small></span><em className={connected ? "connected" : ""}>{connected ? "Conectada" : "Não conectada"}</em></div>
            <div className="integration-services">{meta.services.map((service) => <span key={service}>{service}</span>)}</div>
            <footer><span>{connected ? <>Conta: <strong translate="no">{provider.email}</strong></> : "Conecte sua conta para ativar os aplicativos."}</span>{connected ? <button className="disconnect-integration" type="button" disabled={busy === key} onClick={() => disconnect(key)}>{busy === key ? "Desconectando..." : "Desconectar"}</button> : <button className="connect-integration" type="button" onClick={() => window.location.assign(meta.auth)}>Conectar conta</button>}</footer>
          </article>;
        })}
      </div>
      <aside className="integration-security-note"><ShieldCheck size={18} /><span><strong>Conexão protegida por OAuth 2.0</strong><small>O Spot não recebe nem armazena a senha das suas contas externas. As permissões serão solicitadas pelo próprio provedor.</small></span></aside>
    </section>
  </div>;
}



function PageHeading({ eyebrow, title, description, action, onAction }) {
  return (
    <header className="workspace-heading">
      <div>
        <span className="workspace-eyebrow">{eyebrow}</span>
        <h1>{title}</h1>
        <p>{description}</p>
      </div>
      {action && <button className="primary-action" type="button" onClick={onAction}><Plus size={18} /> {action}</button>}
    </header>
  );
}

const analyticsColors = ["#168ff0", "#65b9f7", "#0b5f9d", "#9bd4fb", "#f2a640", "#6d7f8b"];

function AnalyticsBars({ items, horizontal = false, money = false }) {
  const max = Math.max(...items.map((item) => Number(item.value)), 1);
  if (!items.length) return <p className="analytics-empty">Sem dados no período.</p>;
  if (horizontal) return <div className="analytics-horizontal-bars">{items.map((item) => <div key={item.label}><span title={item.label}>{item.label}</span><i><b style={{ width: `${Math.max((Number(item.value) / max) * 100, 3)}%` }} /></i><strong>{money ? Number(item.value).toLocaleString(getFormatLocale(), { style: "currency", currency: "BRL", maximumFractionDigits: 0 }) : item.value}</strong></div>)}</div>;
  return <div className="analytics-columns">{items.map((item) => <div key={item.label}><strong>{item.value}</strong><i style={{ height: `${Math.max((Number(item.value) / max) * 100, 5)}%` }} /><span title={item.label}>{item.label}</span></div>)}</div>;
}

function AnalyticsLine({ items }) {
  if (!items.length) return <p className="analytics-empty">Sem projetos iniciados no período.</p>;
  const width = 560; const height = 145; const max = Math.max(...items.map((item) => Number(item.value)), 1);
  const points = items.map((item, index) => `${items.length === 1 ? width / 2 : 20 + index * ((width - 40) / (items.length - 1))},${height - 25 - (Number(item.value) / max) * 95}`).join(" ");
  return <div className="analytics-line"><svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label="Projetos iniciados por mês"><line x1="20" y1="120" x2="540" y2="120" /><polygon points={`20,120 ${points} 540,120`} /><polyline points={points} />{points.split(" ").map((point, index) => { const [x, y] = point.split(","); return <g key={items[index].label}><circle cx={x} cy={y} r="4" /><text x={x} y={Number(y) - 10}>{items[index].value}</text><text className="axis-label" x={x} y="140">{items[index].label}</text></g>; })}</svg></div>;
}

function AnalyticsRing({ value, label, tone = "blue", detail }) {
  const normalized = Math.max(0, Math.min(Number(value) || 0, 100));
  return <div className={`analytics-metric-ring ${tone}`} style={{ "--metric-progress": `${normalized * 3.6}deg` }}>
    <span><strong>{Math.round(Number(value) || 0)}%</strong><small>{label}</small></span>
    {detail && <em>{detail}</em>}
  </div>;
}

function ProjectAnalyticsPage() {
  const [financialDetail,setFinancialDetail]=useState(null);
  const [data, setData] = useState(null);
  const [error, setError] = useState("");
  const [restricted, setRestricted] = useState(false);
  const [loading, setLoading] = useState(true);
  const [selectedProject, setSelectedProject] = useState("");
  const [range, setRange] = useState({ from: "", to: "" });

  async function load() {
    setLoading(true); setError(""); setRestricted(false);
    try {
      const query = new URLSearchParams(Object.entries(range).filter(([, value]) => value));
      const response = await sessionRequest(`/reports/project-analytics${query.size ? `?${query}` : ""}`);
      if (response.status === 403) {
        setData(null);
        setRestricted(true);
        return;
      }
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || "Não foi possível carregar os gráficos.");
      setData(result);
      if (selectedProject && !result.projects.some((project) => String(project.id) === selectedProject)) setSelectedProject("");
    } catch (requestError) { setError(requestError.message); }
    finally { setLoading(false); }
  }

  useEffect(() => { load(); }, []);
  useEffect(()=>{let active=true;setFinancialDetail(null);if(selectedProject)sessionRequest(`/projects/${selectedProject}/financial-result`).then(async r=>{const result=await r.json();if(!r.ok)throw new Error(result.message);if(active)setFinancialDetail(result);}).catch(e=>{if(active)setError(e.message);});return()=>{active=false;};},[selectedProject,data]);
  const detail = data?.projects.find((project) => String(project.id) === selectedProject);
  const projectsForCharts = detail ? [detail] : data?.projects || [];
  const summary = data?.summary;
  const currency = (value) => Number(value || 0).toLocaleString(getFormatLocale(), { style: "currency", currency: "BRL", maximumFractionDigits: 0 });
  const statusTotal = data?.status.reduce((sum, item) => sum + item.value, 0) || 1;
  let statusCursor = 0;
  const statusGradient = data?.status.map((item, index) => { const start = statusCursor; statusCursor += item.value / statusTotal * 100; return `${analyticsColors[index % analyticsColors.length]} ${start}% ${statusCursor}%`; }).join(", ");

  return <div className="analytics-page">
    <header className="analytics-heading"><div><span>LEVANTAMENTO DE PROJETOS</span><h1>{detail ? detail.name : "Visão de portfólio"}</h1><p>{detail ? `${detail.client} · ${detail.manager}` : "Indicadores consolidados de prazo, clientes, responsáveis e resultado financeiro."}</p></div><small><BarChart3 size={17} /> {data?.processor || "Python + Pandas"}</small></header>
    <div className="analytics-filters"><label>Data inicial<input type="date" value={range.from} onChange={(event) => setRange({ ...range, from: event.target.value })} /></label><label>Data final<input type="date" value={range.to} onChange={(event) => setRange({ ...range, to: event.target.value })} /></label><label className="analytics-project-filter">Projeto<select value={selectedProject} onChange={(event) => setSelectedProject(event.target.value)}><option value="">Todos os projetos</option>{data?.projects.map((project) => <option key={project.id} value={project.id} translate="no">{project.name}</option>)}</select></label><button type="button" onClick={load} disabled={loading}>{loading ? "Atualizando..." : "Aplicar período"}</button></div>
    {restricted && <section className="analytics-error restricted-project-access" aria-labelledby="restricted-portfolio-title">
      <LockKeyhole size={28} aria-hidden="true" />
      <h2 id="restricted-portfolio-title">Área restrita</h2>
      <p>Caso necessário acesso, falar com gerência</p>
    </section>}
    {error && <p className="analytics-error">{error}</p>}
    {loading && !data && <p className="analytics-loading">Processando levantamento com Pandas...</p>}
    {data && <>
      {!detail && <>
      <section className="analytics-kpis">{[['Em andamento', summary.in_progress, 'blue'], ['Concluídos', summary.completed, 'slate'], ['Cancelados', summary.cancelled || 0, 'red'], ['No prazo', summary.on_time, 'green'], ['Fora do prazo', summary.overdue, 'red'], ['Congelados', summary.frozen, 'purple'], ['Total de projetos', summary.total, 'strong']].map(([label, value, tone]) => <article className={tone} key={label}><span>{label}</span><strong>{value}</strong></article>)}</section>
      <section className="analytics-grid top-row"><article className="analytics-panel status-panel"><header><h2>Situação dos projetos</h2><small>{summary.total} projetos</small></header><div className="status-chart"><div className="status-donut" style={{ background: `conic-gradient(${statusGradient || "#e5edf2 0 100%"})` }}><span><strong>{summary.total}</strong><small>Total</small></span></div><div className="status-legend">{data.status.map((item, index) => <span key={item.label}><i style={{ background: analyticsColors[index % analyticsColors.length] }} />{item.label}<strong>{item.value}</strong></span>)}</div></div></article><article className="analytics-panel monthly-panel"><header><h2>Iniciados por mês</h2><small>Evolução no período</small></header><AnalyticsLine items={data.monthly} /></article></section>
      {summary.unpriced_tasks > 0 && <p role="alert">Resultado parcial: existem atividades sem tarifa vigente. Cadastre o custo/hora dos analistas.</p>}<section className="analytics-grid bottom-row"><article className="analytics-panel"><header><h2>Quantidade por cliente</h2><small>{summary.clients} clientes</small></header><AnalyticsBars items={data.clients} /></article><article className="analytics-panel"><header><h2>Quantidade por gerente de contas</h2><small>{summary.managers} responsáveis</small></header><AnalyticsBars items={data.account_managers || data.managers} /></article><article className="analytics-panel"><header><h2>Quantidade por gerente de projetos</h2></header><AnalyticsBars items={data.managers} /></article><article className="analytics-panel financial-panel"><header><h2>Resultado do portfólio</h2><small>Valores consolidados</small></header><AnalyticsBars horizontal money items={[{ label: "Contratos", value: summary.contract_value }, { label: "Receita", value: summary.revenue }, { label: "Custos", value: summary.cost }, { label: "Lucro", value: summary.profit }]} /></article></section>
      </>}
      <section className="project-charts-section">
        <header><div><span>PAINEL GRÁFICO</span><h2>{detail ? "Indicadores do projeto" : "Indicadores de todos os projetos"}</h2><p>{detail ? "Finanças, esforço, execução e resultado do projeto selecionado." : "Uma visão gráfica completa para cada projeto da carteira."}</p></div><strong>{projectsForCharts.length} {projectsForCharts.length === 1 ? "projeto" : "projetos"}</strong></header>
        <div className="project-charts-list">
          {projectsForCharts.map((project) => {
            const revenueRate = project.contract_value > 0 ? project.actual_revenue / project.contract_value * 100 : 0;
            const budgetRate = project.contract_value > 0 ? project.actual_cost / project.contract_value * 100 : 0;
            const hoursRate = project.estimated_hours > 0 ? project.worked_hours / project.estimated_hours * 100 : 0;
            const margin = project.actual_revenue > 0 ? project.profit / project.actual_revenue * 100 : 0;
            return <article className="project-detail-analytics" key={project.id}>
            <header><div><span>PROJETO #{project.id}</span><h2 translate="no">{project.name}</h2><p>{project.client} · {project.manager}</p></div><em>{project.status}</em></header>
            <div className="detail-kpis"><article><TrendingUp size={20} /><span>Valor do projeto<strong>{currency(project.contract_value)}</strong></span></article><article><Clock3 size={20} /><span>Horas estimadas<strong>{project.estimated_hours}h</strong></span></article><article><Clock3 size={20} /><span>Horas realizadas<strong>{project.worked_hours}h</strong></span></article><article><BarChart3 size={20} /><span>Progresso<strong>{project.progress}%</strong></span></article></div>
            <div className="detail-comparison">
              <article className="detail-chart-wide"><h3>Visão financeira</h3><AnalyticsBars horizontal money items={[{ label: "Contrato", value: project.contract_value }, { label: "Receita", value: project.actual_revenue }, { label: "Custos", value: project.actual_cost }]} /></article>
              <article className="detail-chart-wide"><h3>Consumo de horas</h3><AnalyticsBars horizontal items={[{ label: "Estimadas", value: project.estimated_hours }, { label: "Realizadas", value: project.worked_hours }]} /><small className={hoursRate > 100 ? "chart-alert" : "chart-caption"}>{Math.round(hoursRate)}% das horas planejadas consumidas</small></article>
              <article><h3>Execução</h3><AnalyticsRing value={project.progress} label="concluído" detail={`${project.progress}% do projeto`} /></article>
              <article><h3>Receita realizada</h3><AnalyticsRing value={revenueRate} label="do contrato" tone="cyan" detail={`${currency(project.actual_revenue)} recebidos`} /></article>
              <article><h3>Orçamento consumido</h3><AnalyticsRing value={budgetRate} label="em custos" tone={budgetRate > 100 ? "red" : "amber"} detail={`${currency(project.actual_cost)} utilizados`} /></article>
              <article className={project.profit >= 0 ? "result-chart profit-positive" : "result-chart profit-negative"}><h3>Resultado e margem</h3><strong>{currency(project.profit)}</strong><AnalyticsRing value={Math.abs(margin)} label={margin >= 0 ? "de margem" : "de perda"} tone={margin >= 0 ? "green" : "red"} detail={project.profit >= 0 ? "Lucro apurado" : "Prejuízo apurado"} /></article>
            </div>
          </article>;
          })}
          {!projectsForCharts.length && <p className="analytics-empty">Nenhum projeto disponível para gerar os gráficos.</p>}
        </div>
      </section>
      {financialDetail && <section className="analytics-grid financial-breakdown-charts" aria-label="Detalhamento gráfico do projeto">
        <article className="analytics-panel"><header><h2>Composição dos custos</h2><small>Valores realizados</small></header><AnalyticsBars horizontal money items={[
          { label: "Impostos", value: financialDetail.actual.taxes },
          { label: "Comissão", value: financialDetail.actual.commission },
          { label: "Mão de obra", value: financialDetail.actual.labor_cost },
          { label: "Despesas", value: financialDetail.actual.approved_expenses },
        ]} /></article>
        <article className="analytics-panel"><header><h2>Custo por analista</h2><small>{financialDetail.by_analyst.length} participantes</small></header><AnalyticsBars items={financialDetail.by_analyst.map((item) => ({ label: item.name, value: Number(item.cost) }))} /></article>
        <article className="analytics-panel"><header><h2>Horas por atividade</h2><small>Distribuição do esforço</small></header><AnalyticsBars items={financialDetail.by_activity_type.map((item) => ({ label: item.name, value: Math.round(Number(item.minutes) / 60 * 10) / 10 }))} /></article>
      </section>}
      {financialDetail && <div className="management-workspace"><FinancialResult data={financialDetail}/></div>}
    </>}
  </div>;
}

function NewProjectPage({ onCreated, onCancel }) {
  const [parameters,setParameters]=useState(null),[form,setForm]=useState({name:'',proposal_number:'',project_status_id:'',project_situation_id:'',client_id:'',account_manager_id:'',project_manager_id:'',proposal_date:'',start_date:'',end_date:'',billing_date:'',contract_value:0,commission_rate:0,estimated_labor_cost:0,estimated_additional_cost:0,estimated_hours:0,estimated_extra_minutes:0,cpt_scope:'',tax_type_id:'',tax_rate:0,tax_fixed:0,tax_mode:'percentage'}),[error,setError]=useState(''),[saving,setSaving]=useState(false);
  useEffect(()=>{sessionRequest('/parameters').then(async r=>{const data=await r.json();if(!r.ok)throw new Error(data.message);setParameters(data);setForm(f=>({...f,project_status_id:data.project_statuses[0]?.id||'',project_situation_id:data.project_situations[0]?.id||''}));}).catch(e=>setError(e.message));},[]);
  const access=key=>parameters?.field_access?.[key]||{view:true,edit:true};
  function field(key,label,type='text',options=null,required=false,max){const permissionKey=key.startsWith('estimated_')&&['estimated_hours','estimated_extra_minutes'].includes(key)?'estimated_minutes':key;const rights=access(permissionKey);if(!rights.view)return null;return <label key={key}><span>{label}</span>{options?<select aria-label={label} disabled={!rights.edit} required={required} value={form[key]} onChange={e=>setForm({...form,[key]:e.target.value})}><option value="">Selecione</option>{options.map(o=><option key={o.id} value={o.id} translate="no">{o.name}</option>)}</select>:<input aria-label={label} disabled={!rights.edit} required={required} type={type} min={type==='number'?0:undefined} max={max} step={type==='number'?(permissionKey==='estimated_minutes'?'1':'0.01'):undefined} value={form[key]} onChange={e=>setForm({...form,[key]:e.target.value})}/>}</label>;}
  async function submit(e){e.preventDefault();setSaving(true);setError('');try{const payload={...form,estimated_minutes:Number(form.estimated_hours)*60+Number(form.estimated_extra_minutes)};if(form.tax_type_id&&access('taxes').edit)payload.taxes=[{tax_type_id:Number(form.tax_type_id),calculation_type:form.tax_mode,rate:Number(form.tax_rate),fixed_amount:Number(form.tax_fixed),calculation_basis:'contract_value'}];for(const [key,value] of Object.entries(payload)){if(!access(key).edit)delete payload[key];else if(value==='')payload[key]=null;}const r=await sessionRequest('/projects',{method:'POST',body:JSON.stringify(payload)});const result=await r.json();if(!r.ok)throw new Error(Object.values(result.errors||{}).flat()[0]||result.message);onCreated();}catch(e){setError(e.message);}finally{setSaving(false);}}
  return <div className="workspace-page new-project-page"><PageHeading eyebrow="Área de trabalho" title="Novo projeto" description="Cadastre as informações gerais, responsáveis, prazo e planejamento financeiro."/><form className="new-project-form" onSubmit={submit}>
    <section><header><span>01</span><div><h2>Identificação</h2><p>Dados principais para identificar o projeto.</p></div></header><div className="new-project-fields">{field('name','Nome do projeto','text',null,true)}{field('proposal_number','Número da proposta','text',null,true)}{field('client_id','Cliente','text',parameters?.clients||[])}{field('project_status_id','Status','text',parameters?.project_statuses||[],true)}{field('project_situation_id','Situação','text',parameters?.project_situations||[],true)}</div></section>
    <section><header><span>02</span><div><h2>Responsáveis e período</h2><p>Defina a gestão e as datas planejadas.</p></div></header><div className="new-project-fields">{field('account_manager_id','Gerente de contas','text',parameters?.users||[])}{field('project_manager_id','Gerente do projeto','text',parameters?.users||[])}{field('proposal_date','Data da proposta','date')}{field('start_date','Data de início','date')}{field('end_date','Data de término','date')}{field('billing_date','Data de faturamento','date')}</div></section>
    <section><header><span>03</span><div><h2>Planejamento</h2><p>Informe orçamento, esforço e escopo inicial.</p></div></header><div className="new-project-fields">{field('contract_value','Valor do contrato (R$)','number',null,true)}{field('commission_rate','Comissão (%)','number',null,false,100)}{field('estimated_labor_cost','Custo estimado do analista','number')}{field('estimated_additional_cost','Custo adicional estimado','number')}{field('estimated_hours','Horas estimadas','number')}{field('estimated_extra_minutes','Minutos estimados','number',null,false,59)}{access('taxes').view&&<><label>Imposto<select disabled={!access('taxes').edit} value={form.tax_type_id} onChange={e=>setForm({...form,tax_type_id:e.target.value})}><option value="">Sem imposto</option>{parameters?.tax_types?.map(t=><option key={t.id} value={t.id} translate="no">{t.name}</option>)}</select></label><label>Cálculo do imposto<select disabled={!access('taxes').edit} value={form.tax_mode} onChange={e=>setForm({...form,tax_mode:e.target.value})}><option value="percentage">Percentual</option><option value="fixed">Valor fixo</option></select></label>{field(form.tax_mode==='fixed'?'tax_fixed':'tax_rate',form.tax_mode==='fixed'?'Valor do imposto':'Imposto (%)','number',null,false,form.tax_mode==='percentage'?100:undefined)}</>}<label className="full">Escopo<textarea value={form.cpt_scope} rows={4} maxLength={5000} onChange={e=>setForm({...form,cpt_scope:e.target.value})}/></label></div></section>
    {error&&<p role="alert" className="new-project-error">{error}</p>}<footer><button type="button" className="secondary-button" onClick={onCancel}>Cancelar</button><button className="primary-action" disabled={saving||!parameters}>{saving?'Criando projeto...':'Criar projeto'}</button></footer>
  </form></div>;
}

function UserAvatar({ user, size, className }) {
  if (user?.avatar_url) {
    return <img className={className} src={user.avatar_url} alt={`Foto de ${user.name}`} width={size} height={size} />;
  }

  const name = user?.name?.trim() || "Usuário";
  const words = name.split(/\s+/).filter(Boolean);
  const connectors = new Set(["da", "das", "de", "do", "dos", "e"]);
  const significantWords = words.filter((word) => !connectors.has(word.toLocaleLowerCase("pt-BR")));
  const parts = significantWords.length ? significantWords : words;
  const characters = Array.from(parts[0] || "U");
  const initials = parts.length > 1
    ? `${Array.from(parts[0])[0]}${Array.from(parts.at(-1))[0]}`
    : characters.slice(0, 2).join("");
  const hue = Array.from(name).reduce((total, character) => total + character.codePointAt(0), 0) % 360;

  return (
    <span
      className={`user-avatar-initials${className ? ` ${className}` : ""}`}
      style={{ width: size, height: size, fontSize: Math.round(size * 0.38), "--avatar-hue": hue }}
      role="img"
      aria-label={`Avatar de ${name}`}
    >
      {initials.toLocaleUpperCase("pt-BR")}
    </span>
  );
}

const invitationPermissions = [
  ["tasks.view", "Visualizar tarefas"],
  ["tasks.comment", "Comentar nas tarefas"],
  ["files.view", "Visualizar arquivos"],
  ["files.upload", "Enviar arquivos"],
];

function ProjectInvitationModal({ onClose, canManageIdentity }) {
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [acceptUrl, setAcceptUrl] = useState("");
  const [copied, setCopied] = useState(false);
  const [form, setForm] = useState({
    project_id: "", email: "", project_role: "guest",
    relationship_type: "guest_client", organization_name: "", job_title: "", department: "", expires_in_hours: "72",
    requires_password_creation: true,
    permissions: ["tasks.view", "files.view"],
  });

  useEffect(() => {
    let active = true;
    sessionRequest("/projects")
      .then(async (response) => {
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || "Não foi possível carregar os projetos.");
        if (!active) return;
        setProjects(data);
        if (data.length) setForm((current) => ({ ...current, project_id: String(data[0].id) }));
      })
      .catch((requestError) => active && setError(requestError.message))
      .finally(() => active && setLoading(false));
    return () => { active = false; };
  }, []);

  function togglePermission(permission) {
    setForm((current) => ({
      ...current,
      permissions: current.permissions.includes(permission)
        ? current.permissions.filter((item) => item !== permission)
        : [...current.permissions, permission],
    }));
  }

  async function submitInvitation(event) {
    event.preventDefault();
    setError("");
    setSubmitting(true);
    try {
      const response = await sessionRequest(`/projects/${form.project_id}/invitations`, {
        method: "POST",
        body: JSON.stringify({
          email: form.email,
          project_role: form.project_role,
          relationship_type: form.relationship_type,
          organization_name: form.relationship_type === "external_analyst" ? form.organization_name : null,
          ...(canManageIdentity && form.project_role !== "guest" ? { job_title: form.job_title || null, department: form.department || null } : {}),
          expires_in_hours: Number(form.expires_in_hours),
          requires_password_creation: form.requires_password_creation,
          permissions: form.permissions,
        }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || "Não foi possível criar o convite.");
      setAcceptUrl(data.accept_url);
    } catch (requestError) {
      setError(requestError.message);
    } finally {
      setSubmitting(false);
    }
  }

  async function copyInvitation() {
    await navigator.clipboard.writeText(acceptUrl);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 1800);
  }

  return (
    <div className="invitation-modal" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <section className="invitation-dialog" role="dialog" aria-modal="true" aria-labelledby="invitation-title">
        <header><div><h2 id="invitation-title">Convidar pessoa para um projeto</h2><p>O acesso ficará limitado ao projeto e às permissões selecionadas.</p></div><button type="button" onClick={onClose} aria-label="Fechar"><X size={20} /></button></header>
        {acceptUrl ? (
          <div className="invitation-result">
            <span className="invitation-success"><CheckCircle2 size={19} /> Convite criado</span>
            <p>O convite será enviado em breve para <strong translate="no">{form.email}</strong>. Como alternativa, você também pode copiar o link abaixo. Ele expira no prazo definido e pode ser utilizado uma única vez.{form.requires_password_creation ? " A criação de uma nova senha será obrigatória no aceite." : ""}</p>
            <div className="invitation-link"><input readOnly value={acceptUrl} /><button type="button" onClick={copyInvitation}>{copied ? "Copiado" : "Copiar link"}</button></div>
          </div>
        ) : (
          <form onSubmit={submitInvitation}>
            {loading ? <p className="invitation-loading">Carregando projetos...</p> : <>
              <label>Projeto<select required value={form.project_id} onChange={(event) => setForm({ ...form, project_id: event.target.value })}><option value="">Selecione um projeto</option>{projects.map((project) => <option key={project.id} value={project.id} translate="no">{project.name}</option>)}</select></label>
              {!projects.length && <p className="invitation-warning">Nenhum projeto disponível. Cadastre um projeto antes de criar o convite.</p>}
              <label>E-mail da pessoa<input required type="email" placeholder="pessoa@empresa.com" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label>
              {canManageIdentity && form.project_role !== "guest" && <div className="invitation-grid"><label>Cargo<input maxLength={120} placeholder="Ex.: Analista de projetos" value={form.job_title} onChange={(event) => setForm({ ...form, job_title: event.target.value })} /></label><label>Departamento<input maxLength={120} placeholder="Ex.: Projetos" value={form.department} onChange={(event) => setForm({ ...form, department: event.target.value })} /></label></div>}
              <div className="invitation-grid">
                <label>Papel no projeto<select value={form.project_role} onChange={(event) => { const role = event.target.value; setForm({ ...form, project_role: role, relationship_type: role === "analyst" ? "cpt_internal_analyst" : role === "manager" ? "responsible_manager" : "guest_client", permissions: role === "guest" ? form.permissions.filter((permission) => ["tasks.view", "files.view"].includes(permission)) : form.permissions }); }}><option value="analyst">Analista</option><option value="manager">Gestor</option><option value="guest">Convidado</option></select></label>
                <label>Vínculo<select value={form.relationship_type} onChange={(event) => setForm({ ...form, relationship_type: event.target.value })}>{form.project_role === "analyst" && <><option value="cpt_internal_analyst">Analista interno CPT</option><option value="external_analyst">Analista externo</option></>}{form.project_role === "manager" && <option value="responsible_manager">Gestor responsável</option>}{form.project_role === "guest" && <><option value="guest_client">Cliente convidado</option><option value="guest_analyst">Analista convidado</option><option value="guest_manager">Gestor convidado</option></>}</select></label>
              </div>
              {form.relationship_type === "external_analyst" && <label>Empresa do analista externo<input required maxLength={160} placeholder="Nome da empresa" value={form.organization_name} onChange={(event) => setForm({ ...form, organization_name: event.target.value })} /></label>}
              <label>Validade do convite<select value={form.expires_in_hours} onChange={(event) => setForm({ ...form, expires_in_hours: event.target.value })}><option value="24">24 horas</option><option value="72">3 dias</option><option value="168">7 dias</option></select><small>Após o aceite, o acesso permanece até a finalização do projeto.</small></label>
              <label className="invitation-password-policy"><input type="checkbox" checked={form.requires_password_creation} onChange={(event) => setForm({ ...form, requires_password_creation: event.target.checked })} /><span><strong>Criação de senha obrigatória</strong><small>Ao aceitar, a pessoa deverá definir uma nova senha. Novas contas sempre precisam criar uma senha.</small></span></label>
              <fieldset><legend>{form.project_role === "guest" ? "Áreas que o convidado poderá visualizar" : "Permissões no projeto"}</legend>{invitationPermissions.filter(([value]) => form.project_role !== "guest" || ["tasks.view", "files.view"].includes(value)).map(([value, label]) => <label className="invitation-check" key={value}><input type="checkbox" checked={form.permissions.includes(value)} onChange={() => togglePermission(value)} /><span>{label}</span></label>)}</fieldset>
            </>}
            {error && <p className="invitation-error">{error}</p>}
            <footer><button className="secondary-button" type="button" onClick={onClose}>Cancelar</button><button className="primary-action" type="submit" disabled={loading || submitting || !projects.length}>{submitting ? "Criando..." : "Gerar convite"}</button></footer>
          </form>
        )}
      </section>
    </div>
  );
}

function SecurityPanel() {
  const [security, setSecurity] = useState(null);
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [twoFactor, setTwoFactor] = useState(null);
  const [recoveryCodes, setRecoveryCodes] = useState([]);

  const loadSecurity = async () => {
    const response = await sessionRequest("/security");
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || "Não foi possível carregar a segurança da conta.");
    setSecurity(data);
  };

  useEffect(() => { loadSecurity().catch((requestError) => setError(requestError.message)); }, []);

  async function request(path, options) {
    setError(""); setMessage("");
    const response = await sessionRequest(path, options);
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || "Não foi possível concluir a operação.");
    return data;
  }

  async function changePassword(event) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      const data = await request("/security/password", { method: "PUT", body: JSON.stringify(Object.fromEntries(form)) });
      event.currentTarget.reset(); setMessage(data.message); await loadSecurity();
    } catch (requestError) { setError(requestError.message); }
  }

  async function beginTwoFactor() {
    try { setTwoFactor(await request("/security/two-factor/setup", { method: "POST", body: "{}" })); }
    catch (requestError) { setError(requestError.message); }
  }

  async function confirmTwoFactor(event) {
    event.preventDefault();
    const code = new FormData(event.currentTarget).get("code");
    try { const data = await request("/security/two-factor/confirm", { method: "POST", body: JSON.stringify({ code }) }); setRecoveryCodes(data.recovery_codes); setTwoFactor(null); await loadSecurity(); }
    catch (requestError) { setError(requestError.message); }
  }

  async function disableTwoFactor() {
    const password = window.prompt(translateText("Confirme sua senha para desativar o 2FA:"));
    if (!password) return;
    try { const data = await request("/security/two-factor", { method: "DELETE", body: JSON.stringify({ password }) }); setMessage(data.message); await loadSecurity(); }
    catch (requestError) { setError(requestError.message); }
  }

  async function revokeSession(id) {
    try { await request(`/security/sessions/${encodeURIComponent(id)}`, { method: "DELETE", body: "{}" }); await loadSecurity(); }
    catch (requestError) { setError(requestError.message); }
  }

  async function savePreferences(preferences) {
    setSecurity((current) => ({ ...current, preferences }));
    try { await request("/security/preferences", { method: "PUT", body: JSON.stringify(preferences) }); }
    catch (requestError) { setError(requestError.message); await loadSecurity(); }
  }

  if (!security) return <div className="security-panel"><p>{error || "Carregando segurança da conta..."}</p></div>;
  return <div className="security-panel">
    <div className="form-section-heading"><div><h2>Segurança da conta</h2><p>Proteja seu acesso, conexões e sessões ativas.</p></div><span>{security.two_factor_enabled ? "2FA ativo" : "Conta protegida"}</span></div>
    {(error || message) && <p className={`security-feedback ${error ? "error" : "success"}`}>{error || message}</p>}
    <section className="security-card"><header><span><KeyRound size={18} /><strong>Alterar senha</strong></span></header><form className="security-password-form" onSubmit={changePassword}><input name="current_password" type="password" required placeholder="Senha atual" autoComplete="current-password" /><input name="password" type="password" required minLength={10} placeholder="Nova senha" autoComplete="new-password" /><input name="password_confirmation" type="password" required minLength={10} placeholder="Confirmar nova senha" autoComplete="new-password" /><button className="primary-action" type="submit">Atualizar senha</button></form><small>Mínimo de 10 caracteres, com letras maiúsculas, minúsculas e números.</small></section>
    <section className="security-card"><header><span><ShieldCheck size={18} /><strong>Autenticação em dois fatores</strong></span><em className={security.two_factor_enabled ? "enabled" : ""}>{security.two_factor_enabled ? "Ativa" : "Desativada"}</em></header>{!security.two_factor_enabled && !twoFactor && <><p>Use um aplicativo autenticador para gerar um código adicional no login.</p><button className="secondary-button" type="button" onClick={beginTwoFactor}>Configurar 2FA</button></>}{twoFactor && <div className="two-factor-setup"><img src={twoFactor.qr_code} alt="QR Code para configurar autenticação em dois fatores" /><div><p>Escaneie o QR Code ou informe esta chave:</p><code>{twoFactor.secret}</code><form onSubmit={confirmTwoFactor}><input name="code" inputMode="numeric" pattern="[0-9]{6}" required placeholder="Código de 6 dígitos" /><button className="primary-action" type="submit">Confirmar ativação</button></form></div></div>}{security.two_factor_enabled && <button className="secondary-button danger" type="button" onClick={disableTwoFactor}>Desativar 2FA</button>}{!!recoveryCodes.length && <div className="recovery-codes"><strong>Salve estes códigos de recuperação agora:</strong>{recoveryCodes.map((code) => <code key={code}>{code}</code>)}</div>}</section>
    <section className="security-card"><header><span><Link2 size={18} /><strong>Contas conectadas</strong></span></header><div className="provider-list"><div><img src="/google-logo.svg" alt="" /><span><strong>Google</strong><small>{security.providers.google.email || "Nenhuma conta vinculada"}</small></span><em>{security.providers.google.connected ? "Conectada" : "Não conectada"}</em></div><div><img src="/microsoft-logo.png" alt="" /><span><strong>Microsoft</strong><small>{security.providers.microsoft.email || "Nenhuma conta vinculada"}</small></span><em>{security.providers.microsoft.connected ? "Conectada" : "Não conectada"}</em></div></div></section>
    <section className="security-card"><header><span><Laptop size={18} /><strong>Sessões ativas</strong></span><button className="text-button" type="button" onClick={async () => { try { await request("/security/sessions", { method: "DELETE", body: "{}" }); await loadSecurity(); } catch (requestError) { setError(requestError.message); } }}>Encerrar outras</button></header><div className="session-list">{security.sessions.map((session) => <div key={session.id}><Laptop size={18} /><span><strong>{session.current ? "Esta sessão" : "Navegador conectado"}</strong><small>{session.ip_address || "IP não identificado"} · {new Date(session.last_activity).toLocaleString(getFormatLocale())}</small></span>{!session.current && <button type="button" onClick={() => revokeSession(session.id)}>Encerrar</button>}</div>)}</div></section>
    <section className="security-card"><header><span><Bell size={18} /><strong>Alertas de segurança</strong></span></header><div className="security-preferences">{[["notify_new_login", "Novo login"], ["notify_password_change", "Alteração de senha"], ["notify_provider_link", "Nova conta conectada"]].map(([key, label]) => <label key={key}><span>{label}</span><input type="checkbox" checked={Boolean(security.preferences[key])} onChange={(event) => savePreferences({ ...security.preferences, [key]: event.target.checked })} /></label>)}</div></section>
    <section className="security-card"><header><span><History size={18} /><strong>Histórico de segurança</strong></span></header>{!security.events.length ? <p>Nenhuma alteração de segurança registrada.</p> : <div className="session-list">{security.events.map((event) => <div key={event.id}><ShieldCheck size={18} /><span><strong>{{ "security.password_changed": "Senha alterada", "security.2fa_enabled": "2FA ativado", "security.2fa_disabled": "2FA desativado", "security.sessions_revoked": "Sessões encerradas", "security.provider_disconnected.google": "Conta Google desconectada", "security.provider_disconnected.microsoft": "Conta Microsoft desconectada" }[event.action] || event.action}</strong><small>{event.ip_address || "IP não identificado"} · {new Date(event.created_at).toLocaleString(getFormatLocale())}</small></span></div>)}</div>}</section>
  </div>;
}

function NotificationPanel() {
  const [data, setData] = useState(null);
  const [error, setError] = useState("");
  const [saved, setSaved] = useState(false);

  async function load() {
    const response = await sessionRequest("/notification-preferences");
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || "Não foi possível carregar as notificações.");
    setData(result);
  }
  useEffect(() => { load().catch((requestError) => setError(requestError.message)); }, []);

  function setPreference(path, value) {
    setData((current) => {
      const preferences = structuredClone(current.preferences);
      if (path.length === 3) preferences[path[0]][path[1]][path[2]] = value;
      else preferences[path[0]][path[1]] = value;
      return { ...current, preferences };
    });
  }

  async function save() {
    setError("");
    const response = await sessionRequest("/notification-preferences", { method: "PUT", body: JSON.stringify(data.preferences) });
    const result = await response.json();
    if (!response.ok) return setError(result.message || Object.values(result.errors || {}).flat()[0] || "Não foi possível salvar.");
    setSaved(true); window.setTimeout(() => setSaved(false), 2000);
  }

  async function reset() {
    const response = await sessionRequest("/notification-preferences", { method: "DELETE", body: "{}" });
    const result = await response.json();
    if (!response.ok) return setError(result.message || "Não foi possível restaurar os padrões.");
    setData(result); setSaved(true); window.setTimeout(() => setSaved(false), 2000);
  }

  if (!data) return <div className="notification-panel"><p>{error || "Carregando preferências..."}</p></div>;
  const groups = Object.values(data.events).reduce((result, event) => ({ ...result, [event.group]: [...(result[event.group] || []), event] }), {});
  return <div className="notification-panel">
    <div className="form-section-heading"><div><h2>Preferências de notificações</h2><p>Escolha quais eventos receber no Spot e por e-mail.</p></div><span>Fuso: {data.timezone}</span></div>
    {error && <p className="security-feedback error">{error}</p>}
    <section className="notification-matrix"><header><strong>Evento</strong><span>Spot</span><span>E-mail</span></header>{Object.entries(groups).map(([group, events]) => <div className="notification-group" key={group}><h3>{group}</h3>{events.map((event) => <div className="notification-row" key={event.key}><span>{event.label}</span><input aria-label={message("{event} no Spot", { event: translateText(event.label) })} type="checkbox" checked={Boolean(data.preferences.events[event.key]?.in_app)} onChange={(e) => setPreference(["events", event.key, "in_app"], e.target.checked)} /><input aria-label={message("{event} por e-mail", { event: translateText(event.label) })} type="checkbox" checked={Boolean(data.preferences.events[event.key]?.email)} onChange={(e) => setPreference(["events", event.key, "email"], e.target.checked)} /></div>)}</div>)}</section>
    <div className="notification-settings-grid"><section className="security-card"><header><span><Mail size={18} /><strong>Resumo de atividades</strong></span></header><label>Frequência<select value={data.preferences.digest.frequency} onChange={(e) => setPreference(["digest", "frequency"], e.target.value)}><option value="none">Não enviar</option><option value="daily">Diário</option><option value="weekly">Semanal</option></select></label><label>Horário<input type="time" value={data.preferences.digest.time} onChange={(e) => setPreference(["digest", "time"], e.target.value)} /></label></section><section className="security-card"><header><span><Bell size={18} /><strong>Horário silencioso</strong></span><label className="inline-switch"><input type="checkbox" checked={data.preferences.quiet_hours.enabled} onChange={(e) => setPreference(["quiet_hours", "enabled"], e.target.checked)} /> Ativar</label></header><label>Início<input type="time" value={data.preferences.quiet_hours.start} onChange={(e) => setPreference(["quiet_hours", "start"], e.target.value)} /></label><label>Fim<input type="time" value={data.preferences.quiet_hours.end} onChange={(e) => setPreference(["quiet_hours", "end"], e.target.value)} /></label></section></div>
    <div className="notification-actions">{saved && <span><CheckCircle2 size={16} /> Preferências salvas</span>}<button className="secondary-button" type="button" onClick={reset}>Restaurar padrões</button><button className="primary-action" type="button" onClick={save}>Salvar preferências</button></div>
  </div>;
}

function ProfilePage({ user, onUserUpdate, tutorialTab }) {
  const [saved, setSaved] = useState(false);
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const [avatarMenuOpen, setAvatarMenuOpen] = useState(false);
  const [cameraOpen, setCameraOpen] = useState(false);
  const [cameraError, setCameraError] = useState("");
  const [inviteOpen, setInviteOpen] = useState(false);
  const [activeProfileTab, setActiveProfileTab] = useState("personal");
  useEffect(() => { if (tutorialTab?.tab) setActiveProfileTab(tutorialTab.tab); }, [tutorialTab]);
  const [assignedProjects, setAssignedProjects] = useState([]);
  const [projectsLoading, setProjectsLoading] = useState(true);
  const [projectsError, setProjectsError] = useState("");
  const [timezones, setTimezones] = useState([]);
  const avatarInput = useRef(null);
  const videoRef = useRef(null);
  const streamRef = useRef(null);

  useEffect(() => () => {
    streamRef.current?.getTracks().forEach((track) => track.stop());
  }, []);

  useEffect(() => {
    let active = true;
    sessionRequest("/auth/me/projects")
      .then(async (response) => {
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || "Não foi possível carregar os projetos atribuídos.");
        if (active) setAssignedProjects(data);
      })
      .catch((error) => active && setProjectsError(error.message))
      .finally(() => active && setProjectsLoading(false));
    return () => { active = false; };
  }, []);

  useEffect(() => {
    let active = true;
    sessionRequest("/auth/timezones")
      .then(async (response) => {
        const data = await response.json();
        if (!response.ok) throw new Error("Não foi possível carregar os fusos horários.");
        if (active) setTimezones(data);
      })
      .catch(() => {
        if (active) setTimezones([{ id: "America/Sao_Paulo", label: "(UTC-03:00) America/Sao_Paulo" }]);
      });
    return () => { active = false; };
  }, []);

  async function saveProfile(event) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);

    try {
      const response = await fetch("/profile", {
        method: "PATCH",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "",
        },
        body: JSON.stringify({
          first_name: data.get("first_name"),
          last_name: data.get("last_name"),
          timezone: data.get("timezone"),
          ...(user?.can_manage_identity ? { job_title: data.get("job_title"), department: data.get("department") } : {}),
        }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || "Não foi possível salvar o perfil.");

      onUserUpdate(result);
      setSaved(true);
      window.setTimeout(() => setSaved(false), 2400);
    } catch (error) {
      window.alert(error.message);
    }
  }

  const nameParts = (user?.name || "Usuário").trim().split(/\s+/);
  const firstName = nameParts.shift() || "";
  const lastName = nameParts.join(" ");

  async function uploadAvatarFile(file) {
    if (!file) return;

    const data = new FormData();
    data.append("avatar", file);
    setUploadingAvatar(true);

    try {
      const response = await fetch("/profile/avatar", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "",
        },
        body: data,
      });

      const result = await response.json();
      if (!response.ok) throw new Error(result.message || "Não foi possível enviar a imagem.");

      onUserUpdate({ ...user, avatar_url: result.avatar_url });
    } catch (error) {
      window.alert(error.message);
    } finally {
      setUploadingAvatar(false);
    }
  }

  async function uploadAvatar(event) {
    await uploadAvatarFile(event.target.files?.[0]);
    event.target.value = "";
  }

  async function openCamera() {
    setAvatarMenuOpen(false);
    setCameraError("");
    setCameraOpen(true);

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false });
      streamRef.current = stream;
      window.requestAnimationFrame(() => {
        if (videoRef.current) videoRef.current.srcObject = stream;
      });
    } catch {
      setCameraError("Não foi possível acessar a câmera. Verifique a permissão do navegador.");
    }
  }

  function closeCamera() {
    streamRef.current?.getTracks().forEach((track) => track.stop());
    streamRef.current = null;
    setCameraOpen(false);
  }

  function capturePhoto() {
    const video = videoRef.current;
    if (!video?.videoWidth) return;

    const size = Math.min(video.videoWidth, video.videoHeight);
    const canvas = document.createElement("canvas");
    canvas.width = 720;
    canvas.height = 720;
    const context = canvas.getContext("2d");
    const offsetX = (video.videoWidth - size) / 2;
    const offsetY = (video.videoHeight - size) / 2;
    context.drawImage(video, offsetX, offsetY, size, size, 0, 0, 720, 720);
    canvas.toBlob(async (blob) => {
      if (!blob) return;
      closeCamera();
      await uploadAvatarFile(new File([blob], "foto-perfil.jpg", { type: "image/jpeg" }));
    }, "image/jpeg", 0.9);
  }

  async function resetAvatar() {
    setAvatarMenuOpen(false);
    setUploadingAvatar(true);
    try {
      const response = await fetch("/profile/avatar", {
        method: "DELETE",
        headers: {
          Accept: "application/json",
          "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "",
        },
      });
      if (!response.ok) throw new Error("Não foi possível restaurar a imagem padrão.");
      onUserUpdate({ ...user, avatar_url: null });
    } catch (error) {
      window.alert(error.message);
    } finally {
      setUploadingAvatar(false);
    }
  }

  return (
    <div className="workspace-page profile-workspace">
      <PageHeading eyebrow="Minha conta" title="Meu perfil" description="Gerencie seus dados pessoais e preferências da conta." action="Convidar pessoa" onAction={() => setInviteOpen(true)} />
      {inviteOpen && <ProjectInvitationModal onClose={() => setInviteOpen(false)} canManageIdentity={Boolean(user?.can_manage_identity)} />}
      <div className="profile-layout">
        <aside className="profile-summary-card">
          <div className="profile-cover" />
          <div className="profile-avatar-large">
            <UserAvatar user={user} size={84} />
            <input
              ref={avatarInput}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              hidden
              onChange={uploadAvatar}
            />
            <button
              className="profile-avatar-trigger"
              type="button"
              aria-label="Alterar foto"
              title="Escolher foto do computador"
              disabled={uploadingAvatar}
              onClick={() => setAvatarMenuOpen((open) => !open)}
            ><Camera size={16} /></button>
            {avatarMenuOpen && (
              <div className="avatar-action-menu">
                <button type="button" onClick={() => { setAvatarMenuOpen(false); avatarInput.current?.click(); }}>Enviar imagem</button>
                <button type="button" onClick={openCamera}>Tirar foto</button>
                <button type="button" onClick={resetAvatar}>Usar imagem padrão</button>
              </div>
            )}
          </div>
          <h2 translate="no">{user?.name || user?.email || "—"}</h2>
          <p className="profile-role">{user?.spot_role || "Perfil não definido"}</p>
          <span className="online-badge"><i /> Disponível</span>
          <div className="profile-stats">
            <span><strong>12</strong>Projetos</span>
            <span><strong>48</strong>Tarefas</span>
            <span><strong>6</strong>Equipes</span>
          </div>
          <div className="profile-completion">
            <span>Perfil completo <strong>85%</strong></span>
            <div><i /></div>
            <small>Adicione um telefone para completar seu perfil.</small>
          </div>
        </aside>

        <section className="profile-settings-card">
          <nav className="profile-tabs">
            <button className={activeProfileTab === "personal" ? "active" : ""} type="button" onClick={() => setActiveProfileTab("personal")}><UserRound size={17} /> Informações pessoais</button>
            <button className={activeProfileTab === "security" ? "active" : ""} type="button" onClick={() => setActiveProfileTab("security")}><ShieldCheck size={17} /> Segurança</button>
            <button className={activeProfileTab === "notifications" ? "active" : ""} type="button" onClick={() => setActiveProfileTab("notifications")}><Bell size={17} /> Notificações</button>
          </nav>
          {activeProfileTab === "security" ? <SecurityPanel /> : activeProfileTab === "notifications" ? <NotificationPanel /> : <form className="profile-form" onSubmit={saveProfile}>
            <div className="form-section-heading">
              <div><h2>Informações pessoais</h2><p>Essas informações serão exibidas no seu perfil e nos projetos.</p></div>
              <span>Conta ativa</span>
            </div>
            <div className="profile-form-grid">
              <label><span>Nome</span><input key={`first-${user?.name}`} name="first_name" defaultValue={firstName} /></label>
              <label><span>Sobrenome</span><input key={`last-${user?.name}`} name="last_name" defaultValue={lastName} /></label>
              <label className="full-field"><span>E-mail</span><div className="verified-input"><input type="email" value={user?.email || ""} readOnly /><CheckCircle2 size={18} /></div><small>E-mail verificado</small></label>
              <label><span>Cargo</span><input name="job_title" value={user?.job_title || ""} readOnly={!user?.can_manage_identity} onChange={(event) => onUserUpdate({ ...user, job_title: event.target.value })} className={!user?.can_manage_identity ? "administrative-readonly" : ""} placeholder="Não informado" /></label>
              <label><span>Telefone</span><input placeholder="+55 (11) 99999-9999" /></label>
              <label><span>Departamento</span><input name="department" value={user?.department || ""} readOnly={!user?.can_manage_identity} onChange={(event) => onUserUpdate({ ...user, department: event.target.value })} className={!user?.can_manage_identity ? "administrative-readonly" : ""} placeholder="Não informado" title={!user?.can_manage_identity ? "Alteração permitida somente a administradores" : "Campo administrativo"} /></label>
              <label><span>Fuso horário</span><select name="timezone" value={user?.timezone || "America/Sao_Paulo"} onChange={(event) => onUserUpdate({ ...user, timezone: event.target.value })}>{!timezones.length && <option value={user?.timezone || "America/Sao_Paulo"}>Carregando fusos horários...</option>}{timezones.map((timezone) => <option key={timezone.id} value={timezone.id}>{timezone.label}</option>)}</select></label>
              <section className="assigned-projects full-field" aria-labelledby="assigned-projects-title">
                <div className="assigned-projects-heading"><span><FolderKanban size={17} /><strong id="assigned-projects-title">Projetos atribuídos</strong></span><small>{assignedProjects.length} {assignedProjects.length === 1 ? "projeto" : "projetos"}</small></div>
                {projectsLoading && <p className="assigned-projects-state">Carregando projetos...</p>}
                {projectsError && <p className="assigned-projects-state error">{projectsError}</p>}
                {!projectsLoading && !projectsError && !assignedProjects.length && <p className="assigned-projects-state">Nenhum projeto atribuído a este perfil.</p>}
                {!!assignedProjects.length && <div className="assigned-project-list">{assignedProjects.map((project) => {
                  const progress = Math.min(100, Math.max(0, Number(project.progress) || 0));
                  return <article className="assigned-project-row" key={project.id}><div className="assigned-project-info"><span className="project-icon blue"><FolderKanban size={16} /></span><span><strong translate="no">{project.name}</strong><small>{project.finalized_at ? "Finalizado" : project.status === "in_progress" || project.status === "in-progress" ? "Em andamento" : project.status || "Planejamento"}</small></span></div><div className="assigned-project-progress"><span><small>Progresso</small><strong>{progress}%</strong></span><div><i style={{ width: `${progress}%` }} /></div></div></article>;
                })}</div>}
              </section>
            </div>
            <div className="profile-form-actions">
              {saved && <span><CheckCircle2 size={17} /> Alterações salvas</span>}
              <button className="secondary-button" type="button">Cancelar</button>
              <button className="primary-action" type="submit">Salvar alterações</button>
            </div>
          </form>}
        </section>
      </div>
      {cameraOpen && (
        <div className="camera-modal" role="dialog" aria-modal="true" aria-label="Tirar foto de perfil">
          <div className="camera-dialog">
            <header><h2>Tirar foto de perfil</h2><button type="button" onClick={closeCamera}><X size={20} /></button></header>
            {cameraError ? <p className="camera-error">{cameraError}</p> : <video ref={videoRef} autoPlay muted playsInline />}
            <footer>
              <button className="secondary-button" type="button" onClick={closeCamera}>Cancelar</button>
              {!cameraError && <button className="primary-action" type="button" onClick={capturePhoto}><Camera size={17} /> Tirar foto</button>}
            </footer>
          </div>
        </div>
      )}
    </div>
  );
}

function AvatarStack({ members = [], count = members.length, max = 4 }) {
  const visibleMembers = members.slice(0, max);
  const displayedCount = visibleMembers.length || Math.min(count, max);
  const hiddenCount = Math.max(0, count - displayedCount);
  return (
    <div className="avatar-stack" aria-label={`${count} participantes`}>
      {visibleMembers.length ? visibleMembers.map((member) => (
        <UserAvatar key={member.id ?? member.user_id} user={member} size={32} className="dashboard-team-avatar" />
      )) : Array.from({ length: Math.min(count, max) }, (_, index) => (
        <span className="dashboard-team-avatar avatar-placeholder" key={index}><CircleUserRound size={30} /></span>
      ))}
      {hiddenCount > 0 && <span className="avatar-overflow" aria-label={`Mais ${hiddenCount} participantes`}>+{hiddenCount}</span>}
    </div>
  );
}

const administrativeProfileLabels = {
  administrador: "Administrador",
  "gestor-administrador": "Gestor administrador",
  gestor: "Gestor",
  analista: "Analista",
};

const accountStatusLabels = {
  pending_activation: "Aguardando ativação",
  active: "Ativo",
  suspended: "Suspenso",
  disabled: "Desativado",
};

function AdminUsersPage() {
  const emptyForm = { first_name: "", last_name: "", email: "", profile: "analista", organization_id: "", job_title: "", department: "" };
  const [users, setUsers] = useState([]);
  const [organizations, setOrganizations] = useState([]);
  const [form, setForm] = useState(emptyForm);
  const [showForm, setShowForm] = useState(false);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [feedback, setFeedback] = useState(null);
  const [activationUrl, setActivationUrl] = useState("");
  const [permissionUser, setPermissionUser] = useState(null);
  const [permissionDraft, setPermissionDraft] = useState(false);

  async function loadUsers() {
    setLoading(true);
    const response = await sessionRequest("/admin/users");
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || "Não foi possível carregar os usuários.");
    setUsers(data.users || []);
    setOrganizations(data.organizations || []);
    setForm((current) => ({ ...current, organization_id: current.organization_id || String(data.organizations?.[0]?.id || "") }));
    setLoading(false);
  }

  useEffect(() => { loadUsers().catch((error) => { setFeedback({ type: "error", text: error.message }); setLoading(false); }); }, []);

  async function createUser(event) {
    event.preventDefault();
    setSubmitting(true); setFeedback(null); setActivationUrl("");
    try {
      const payload = { ...form, name: `${form.first_name} ${form.last_name}`.trim(), organization_id: Number(form.organization_id) };
      delete payload.first_name;
      delete payload.last_name;
      const response = await sessionRequest("/admin/users", { method: "POST", body: JSON.stringify(payload) });
      const data = await response.json();
      if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || "Não foi possível cadastrar o usuário.");
      setActivationUrl(data.activation_url || "");
      setFeedback({ type: "success", text: "Usuário cadastrado. Compartilhe o link de primeiro acesso." });
      setForm({ ...emptyForm, organization_id: form.organization_id });
      await loadUsers();
    } catch (error) { setFeedback({ type: "error", text: error.message }); }
    finally { setSubmitting(false); }
  }

  async function updateAccess(user, changes) {
    setFeedback(null);
    const payload = {
      profile: changes.profile || user.profiles?.[0]?.slug || "analista",
      account_status: changes.account_status || user.account_status,
      job_title: user.job_title,
      department: user.department,
      ...(changes.can_create_projects !== undefined ? { can_create_projects: changes.can_create_projects } : {}),
    };
    try {
      const response = await sessionRequest("/admin/users/" + user.id, { method: "PATCH", body: JSON.stringify(payload) });
      const data = await response.json();
      if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || "Não foi possível atualizar o acesso.");
      setFeedback({ type: "success", text: message("Acesso de {name} atualizado.", { name: user.name }) });
      await loadUsers();
      return true;
    } catch (error) { setFeedback({ type: "error", text: error.message }); return false; }
  }

  async function deactivateUser(user) {
    if (!window.confirm(`Desativar o acesso de ${user.name}? O histórico será preservado.`)) return;
    await updateAccess(user, { account_status: "disabled" });
  }

  async function deletePendingUser(user) {
    if (!window.confirm(`Excluir definitivamente o cadastro pendente de ${user.name}?`)) return;
    setFeedback(null);
    try {
      const response = await sessionRequest(`/admin/users/${user.id}`, { method: "DELETE" });
      if (!response.ok) {
        const data = await response.json();
        throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || "Não foi possível excluir o cadastro.");
      }
      setActivationUrl("");
      setFeedback({ type: "success", text: `Cadastro de ${user.name} excluído.` });
      await loadUsers();
    } catch (error) {
      setFeedback({ type: "error", text: error.message });
    }
  }

  function openPermissions(user) {
    setPermissionUser(user);
    setPermissionDraft(Boolean(user.can_create_projects));
  }

  async function savePermissions() {
    if (!permissionUser) return;
    const updated = await updateAccess(permissionUser, { can_create_projects: permissionDraft });
    if (updated) setPermissionUser(null);
  }

  async function copyActivationLink() {
    await navigator.clipboard.writeText(activationUrl);
    setFeedback({ type: "success", text: "Link de primeiro acesso copiado." });
  }

  return <div className="workspace-page admin-users-page">
    <div className="workspace-heading"><div><span className="workspace-eyebrow">Administração</span><h1>Usuários e acessos</h1><p>Cadastre pessoas, defina perfis e controle quem pode entrar no Spot.</p></div><button className="primary-action" type="button" onClick={() => setShowForm((value) => !value)}><Plus size={17} /> {showForm ? "Fechar cadastro" : "Novo usuário"}</button></div>
    {feedback && <p className={"admin-feedback " + feedback.type}>{feedback.text}</p>}
    {activationUrl && <div className="activation-link"><div><strong>Link de primeiro acesso</strong><small>O usuário deve abrir este endereço para criar a senha.</small></div><input readOnly value={activationUrl} /><button type="button" onClick={copyActivationLink}>Copiar link</button></div>}
    {showForm && <form className="admin-user-form" onSubmit={createUser}>
      <header><div><h2>Cadastrar usuário</h2><p>O acesso ficará pendente até a criação da senha.</p></div></header>
      <div className="admin-user-form-grid">
        <label><span>Nome</span><input required autoComplete="given-name" value={form.first_name} onChange={(e) => setForm({ ...form, first_name: e.target.value })} /></label>
        <label><span>Sobrenome</span><input required autoComplete="family-name" value={form.last_name} onChange={(e) => setForm({ ...form, last_name: e.target.value })} /></label>
        <label><span>E-mail principal</span><input required type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label>
        <label><span>Perfil</span><select value={form.profile} onChange={(e) => setForm({ ...form, profile: e.target.value })}>{Object.entries(administrativeProfileLabels).map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label>
        <label><span>Organização</span><select required value={form.organization_id} onChange={(e) => setForm({ ...form, organization_id: e.target.value })}><option value="">Selecione</option>{organizations.map((organization) => <option value={organization.id} key={organization.id} translate="no">{organization.name}</option>)}</select></label>
        <label><span>Cargo</span><input required value={form.job_title} onChange={(e) => setForm({ ...form, job_title: e.target.value })} /></label>
        <label><span>Departamento</span><input required value={form.department} onChange={(e) => setForm({ ...form, department: e.target.value })} /></label>
      </div>
      <footer><button className="primary-action" disabled={submitting} type="submit">{submitting ? "Cadastrando..." : "Cadastrar e gerar acesso"}</button></footer>
    </form>}
    <section className="admin-users-panel">
      <header className="admin-users-panel-header">
        <div className="admin-users-panel-title"><span className="admin-users-panel-icon"><Users size={19} /></span><div><h2>Usuários cadastrados</h2><p>{message(users.length === 1 ? "{count} conta encontrada" : "{count} contas encontradas", { count: users.length })}</p></div></div>
        {!loading && users.length > 0 && <span className="admin-users-total">{users.length}</span>}
      </header>
      {loading ? <p className="admin-empty">Carregando usuários...</p> : !users.length ? <p className="admin-empty">Nenhum usuário cadastrado.</p> : <div className="admin-users-list">
        {users.map((listedUser) => <article className="admin-user-row" key={listedUser.id}>
          <div className="admin-user-person">
            <UserAvatar user={listedUser} size={44} />
            <div className="admin-user-identity"><div className="admin-user-name-line"><strong translate="no">{listedUser.name}</strong><span className={"admin-user-status " + listedUser.account_status}>{accountStatusLabels[listedUser.account_status] || listedUser.account_status}</span></div><small translate="no">{listedUser.email}</small><span>{listedUser.job_title || "Cargo não informado"} · {listedUser.department || "Departamento não informado"}</span></div>
          </div>
          <div className="admin-user-controls">
            <label><span><ShieldCheck size={13} /> Perfil</span><select value={listedUser.profiles?.[0]?.slug || "analista"} onChange={(e) => updateAccess(listedUser, { profile: e.target.value })}>{Object.entries(administrativeProfileLabels).map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label>
            <label><span><CheckCircle2 size={13} /> Situação</span><select value={listedUser.account_status} onChange={(e) => updateAccess(listedUser, { account_status: e.target.value })}>{Object.entries(accountStatusLabels).map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label>
            {listedUser.profiles?.[0]?.slug === "analista" && <div className="manage-permissions-field"><span><KeyRound size={13} /> Permissões</span><button type="button" onClick={() => openPermissions(listedUser)}><SlidersHorizontal size={15} /> Gerenciar permissões</button></div>}
            <div className="admin-user-actions">
              <span><Trash2 size={13} /> Ações</span>
              {listedUser.account_status === "pending_activation"
                ? <button className="danger" type="button" onClick={() => deletePendingUser(listedUser)}><Trash2 size={15} /> Excluir cadastro</button>
                : listedUser.account_status !== "disabled" && <button className="danger" type="button" onClick={() => deactivateUser(listedUser)}><LockKeyhole size={15} /> Desativar usuário</button>}
            </div>
          </div>
        </article>)}
      </div>}
    </section>
    {permissionUser && <div className="permissions-modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setPermissionUser(null)}>
      <section className="permissions-modal" role="dialog" aria-modal="true" aria-labelledby="permissions-modal-title">
        <header><div className="permissions-modal-heading"><span><KeyRound size={19} /></span><div><h2 id="permissions-modal-title">Gerenciar permissões</h2><p translate="no">{permissionUser.name} · {permissionUser.email}</p></div></div><button className="permissions-modal-close" type="button" aria-label="Fechar" onClick={() => setPermissionUser(null)}><X size={19} /></button></header>
        <div className="permissions-modal-body">
          <div className="permission-setting"><div><span className="permission-setting-icon"><FolderKanban size={18} /></span><div><strong>Criar projetos</strong><small>Permite cadastrar e configurar novos projetos no Spot.</small></div></div><label className="permission-switch"><input type="checkbox" checked={permissionDraft} onChange={(event) => setPermissionDraft(event.target.checked)} /><span aria-hidden="true" /></label></div>
        </div>
        <footer><button className="secondary-button" type="button" onClick={() => setPermissionUser(null)}>Cancelar</button><button className="primary-action" type="button" onClick={savePermissions}>Salvar permissões</button></footer>
      </section>
    </div>}
  </div>;
}

const statusPresentation = {
  done: ["Feito", "done"], completed: ["Feito", "done"],
  in_progress: ["Andamento", "progress"], "in-progress": ["Andamento", "progress"],
  planning: ["Planejamento", "progress"], stopped: ["Parado", "stopped"],
  cancelled: ["Cancelado", "stopped"],
};

function HomeDashboard({ search, incompleteOnly, filterMode, sortAscending, onNavigate, onEditTeam, onUpdated }) {
  const [data, setData] = useState(null);
  const [error, setError] = useState("");

  async function load() {
    const response = await sessionRequest("/home-dashboard");
    const result = await response.json();
    if (!response.ok) throw new Error(result.message || "Não foi possível carregar o painel.");
    setData(result);
  }
  useEffect(() => { load().catch((requestError) => setError(requestError.message)); }, []);
  useEffect(() => { if (data?.updated_at) onUpdated?.(data.updated_at); }, [data?.updated_at, onUpdated]);

  async function toggleTask(task) {
    if (!task.mutable) return;
    const response = await sessionRequest(`/home-dashboard/tasks/${task.id}/toggle`, { method: "PATCH", body: "{}" });
    const result = await response.json();
    if (!response.ok) return setError(result.message || "Não foi possível atualizar a tarefa.");
    setData((current) => ({ ...current, tasks: current.tasks.map((item) => item.id === task.id ? { ...item, status: result.status } : item) }));
  }

  if (error && !data) return <div className="dashboard-main"><h1>Painel</h1><p className="home-state error">{error}</p></div>;
  if (!data) return <div className="dashboard-main"><h1>Painel</h1><p className="home-state">Carregando painel...</p></div>;
  const term = search.trim().toLocaleLowerCase("pt-BR");
  const matches = (value) => !term || value.toLocaleLowerCase("pt-BR").includes(term);
  const sorter = (a, b) => (sortAscending ? 1 : -1) * a.name.localeCompare(b.name, "pt-BR");
  const projects = data.projects.filter((project) => matches(project.name)).sort(sorter);
  const tasks = data.tasks.filter((task) => matches(`${task.title} ${task.project_name}`))
    .filter((task) => !incompleteOnly || task.status !== "done")
    .filter((task) => filterMode === "high" ? task.priority === "high" : filterMode === "overdue" ? task.due_date && task.status !== "done" && new Date(`${task.due_date}T23:59:59`) < new Date() : true)
    .sort((a, b) => (sortAscending ? 1 : -1) * a.title.localeCompare(b.title, "pt-BR"));
  const teams = data.teams.filter((team) => matches(team.name)).sort(sorter);
  const formatDate = (date) => date ? new Intl.DateTimeFormat(getFormatLocale(), { day: "2-digit", month: "short" }).format(new Date(`${date}T12:00:00`)) : "Sem prazo";

  return <div className="dashboard-main">
    <div className="home-heading"><h1>Painel</h1></div>
    {error && <p className="home-state error">{error}</p>}
    <div className="dashboard-grid">
      <section className="board-column status-column"><div className="column-title"><span><SlidersHorizontal size={20} /> status</span><span className="column-count">{projects.length}</span></div><div className="column-surface">{projects.map((project) => { const [label, tone] = project.finalized ? ["Feito", "done"] : statusPresentation[project.status] || [project.status || "Planejamento", "progress"]; return <button className="project-row" type="button" key={project.id} onClick={() => onNavigate("projects")}><ClipboardList size={31} strokeWidth={1.4} /><span><span translate="no">{project.name}</span><small>{message("{count}% concluído", { count: project.progress })}</small></span><strong className={tone}>{label}</strong></button>; })}{!projects.length && <p className="column-empty">Nenhum projeto encontrado.</p>}</div></section>
      <section className="board-column tasks-column"><div className="column-title"><span><ListChecks size={20} /> tarefas</span><span className="column-count">{tasks.length}</span></div><div className={`task-list ${!tasks.length ? "empty-surface" : ""}`}>{tasks.map((task) => <article className={`task-card ${task.status === "done" ? "is-done" : ""}`} key={task.id}><button className="task-name" type="button" disabled={!task.mutable} onClick={() => toggleTask(task)} title={task.mutable ? "Alternar conclusão" : "Tarefa somente para visualização"}><Check size={17} /><span><span translate="no">{task.title}</span><small><span translate="no">{task.project_name}</span></small></span></button><div className="task-meta"><span><CircleUserRound size={31} fill="#c9c9c9" stroke="#fff" />{formatDate(task.due_date)}</span><span><i className={`priority-dot ${task.priority}`} /><MoreHorizontal size={24} /></span></div></article>)}{!tasks.length && <p className="column-empty">Nenhuma tarefa encontrada.</p>}</div></section>
      <section className="board-column teams-column"><div className="column-title"><span><Users size={20} /> Times ativos</span><span className="column-count">{teams.length}</span></div><div className="column-surface team-surface">{teams.map((team) => <button className="team-row" type="button" key={team.project_id} onClick={() => onEditTeam(team.project_id)}><span className="team-row-icon"><Users size={24} /></span><AvatarStack members={team.members || []} count={team.count} /><span className="team-row-copy"><strong translate="no">{team.name}</strong><small>{team.count} {team.count === 1 ? "pessoa" : "pessoas"}</small></span><ChevronRight className="team-row-arrow" size={18} /></button>)}{!teams.length && <p className="column-empty">Nenhum time ativo.</p>}</div></section>
    </div>
  </div>;
}

function TeamsPage({ initialProjectId }) {
  const [teams, setTeams] = useState([]);
  const [selectedId, setSelectedId] = useState(initialProjectId || null);
  const [detail, setDetail] = useState(null);
  const [draft, setDraft] = useState({ name: "", members: [] });
  const [search, setSearch] = useState("");
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  async function loadTeams(preferredId = selectedId) {
    const response = await sessionRequest("/teams");
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || "Não foi possível carregar as equipes.");
    setTeams(data);
    const nextId = preferredId && data.some((team) => team.project_id === preferredId) ? preferredId : data[0]?.project_id;
    setSelectedId(nextId || null);
    if (!nextId) { setDetail(null); setLoading(false); }
    return nextId;
  }

  async function openTeam(projectId) {
    if (!projectId) { setDetail(null); return; }
    setError(""); setNotice(""); setLoading(true);
    try {
      const response = await sessionRequest(`/teams/${projectId}`);
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || "Não foi possível carregar o time.");
      setDetail(data);
      setDraft({ name: data.name, members: data.members.map((member) => ({ user_id: member.user_id, role: member.role })) });
    } catch (requestError) { setError(requestError.message); }
    finally { setLoading(false); }
  }

  useEffect(() => { loadTeams(initialProjectId).catch((requestError) => { setError(requestError.message); setLoading(false); }); }, []);
  useEffect(() => { if (selectedId && detail?.project_id !== selectedId) openTeam(selectedId); }, [selectedId]);

  const selectedMembers = new Map(draft.members.map((member) => [member.user_id, member]));
  const visibleUsers = (detail?.available_users || []).filter((person) => `${person.name} ${person.email}`.toLocaleLowerCase("pt-BR").includes(search.toLocaleLowerCase("pt-BR")));
  function toggleMember(person) {
    setDraft((current) => ({ ...current, members: current.members.some((member) => member.user_id === person.id)
      ? current.members.filter((member) => member.user_id !== person.id)
      : [...current.members, { user_id: person.id, role: "analyst" }] }));
  }
  function changeRole(userId, role) {
    setDraft((current) => ({ ...current, members: current.members.map((member) => member.user_id === userId ? { ...member, role } : member) }));
  }
  async function save(event) {
    event.preventDefault(); setSaving(true); setError(""); setNotice("");
    try {
      const response = await sessionRequest(`/teams/${detail.project_id}`, { method: "PUT", body: JSON.stringify(draft) });
      const data = await response.json();
      if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || "Não foi possível salvar o time.");
      setDetail(data); setDraft({ name: data.name, members: data.members.map((member) => ({ user_id: member.user_id, role: member.role })) });
      await loadTeams(data.project_id); setNotice("Time atualizado com sucesso.");
    } catch (requestError) { setError(requestError.message); }
    finally { setSaving(false); }
  }

  return <div className="workspace-page teams-workspace">
    <PageHeading eyebrow="Área de trabalho" title="Equipes" description="Edite os nomes, integrantes e responsabilidades dos times de projeto." />
    {(error || notice) && <p className={`teams-feedback ${error ? "error" : "success"}`} role={error ? "alert" : "status"}>{error || notice}</p>}
    <div className="teams-layout">
      <aside className="teams-list"><header><span><Users size={19} /> Times ativos</span><small>{teams.length}</small></header>{teams.map((team) => <button type="button" className={selectedId === team.project_id ? "active" : ""} key={team.project_id} onClick={() => setSelectedId(team.project_id)}><AvatarStack members={team.members} count={team.members.length} /><span><strong translate="no">{team.name}</strong><small>{team.members.length} {team.members.length === 1 ? "pessoa" : "pessoas"}</small></span><ChevronRight size={17} /></button>)}</aside>
      <section className="team-editor">{loading ? <p>Carregando time...</p> : detail ? <form onSubmit={save}>
        <header><div><span>Time do projeto</span><h2 translate="no">{detail.project_name}</h2></div>{detail.finalized && <em>Projeto finalizado</em>}</header>
        <label className="team-name-field"><span>Nome do time</span><input required maxLength={160} disabled={!detail.can_edit} value={draft.name} onChange={(event) => setDraft({ ...draft, name: event.target.value })} /></label>
        <div className="team-members-heading"><div><h3>Integrantes</h3><p>Selecione as pessoas e defina o papel de cada uma no projeto.</p></div><strong>{draft.members.length}</strong></div>
        <label className="team-search"><Search size={18} /><input aria-label="Buscar pessoa" placeholder="Buscar por nome ou e-mail..." value={search} onChange={(event) => setSearch(event.target.value)} /></label>
        <div className="team-member-list">{visibleUsers.map((person) => { const selected = selectedMembers.get(person.id); return <article className={selected ? "selected" : ""} key={person.id}><button type="button" disabled={!detail.can_edit} aria-pressed={Boolean(selected)} onClick={() => toggleMember(person)}><UserAvatar user={person} size={38} className="team-member-avatar" /><span className="team-member-identity"><strong translate="no">{person.name}</strong><small translate="no">{person.email}</small></span><i>{selected ? <Check size={15} /> : <Plus size={15} />}</i></button>{selected && <select aria-label={`Papel de ${person.name}`} disabled={!detail.can_edit} value={selected.role} onChange={(event) => changeRole(person.id, event.target.value)}><option value="manager">Gestor</option><option value="analyst">Analista</option><option value="guest">Convidado</option></select>}</article>; })}</div>
        {detail.can_edit ? <footer><button className="primary-action" disabled={saving}>{saving ? "Salvando..." : "Salvar alterações"}</button></footer> : <p className="team-readonly">Este time está disponível somente para consulta.</p>}
      </form> : <p>Nenhum time disponível.</p>}</section>
    </div>
  </div>;
}

function ClientsPage() {
  const emptyClient = { name: "", legal_name: "", document: "", email: "", phone: "", active: true };
  const [clients, setClients] = useState([]);
  const [access, setAccess] = useState({});
  const [search, setSearch] = useState("");
  const [editor, setEditor] = useState(false);
  const [form, setForm] = useState(emptyClient);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  async function load() {
    setLoading(true); setError("");
    try {
      const [clientsResponse, accessResponse] = await Promise.all([sessionRequest("/management/parameters/clients"), sessionRequest("/management/access")]);
      const clientsData = await clientsResponse.json(); const accessData = await accessResponse.json();
      if (!clientsResponse.ok) throw new Error(clientsData.message || "Não foi possível carregar os clientes.");
      setClients(clientsData); setAccess(accessResponse.ok ? accessData : {});
    } catch (requestError) { setError(requestError.message); }
    finally { setLoading(false); }
  }
  useEffect(() => { load(); }, []);

  const digits = (value) => value.replace(/\D/g, "");
  const cnpj = (value) => digits(value || "").replace(/^(\d{2})(\d)/, "$1.$2").replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3").replace(/\.(\d{3})(\d)/, ".$1/$2").replace(/(\d{4})(\d)/, "$1-$2").slice(0, 18);
  const visible = clients.filter((client) => `${client.name} ${client.legal_name || ""} ${client.document || ""} ${client.email || ""}`.toLocaleLowerCase("pt-BR").includes(search.trim().toLocaleLowerCase("pt-BR")));
  function open(client = null) {
    setEditor(client || {}); setError(""); setNotice("");
    setForm(client ? { name: client.name || "", legal_name: client.legal_name || client.name || "", document: cnpj(client.document), email: client.email || "", phone: client.phone || "", active: Boolean(client.active) } : emptyClient);
  }
  async function save(event) {
    event.preventDefault(); setSaving(true); setError(""); setNotice("");
    try {
      const response = await sessionRequest(`/management/parameters/clients${editor?.id ? `/${editor.id}` : ""}`, { method: editor?.id ? "PUT" : "POST", body: JSON.stringify({ ...form, document: digits(form.document) || null }) });
      const data = await response.json();
      if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || "Não foi possível salvar o cliente.");
      setEditor(false); setForm(emptyClient); setNotice(editor?.id ? "Cliente atualizado com sucesso." : "Cliente cadastrado com sucesso."); await load();
    } catch (requestError) { setError(requestError.message); }
    finally { setSaving(false); }
  }
  async function archive(client) {
    if (!window.confirm(`Arquivar o cliente ${client.name}? Os projetos existentes continuarão vinculados.`)) return;
    const response = await sessionRequest(`/management/parameters/clients/${client.id}`, { method: "DELETE", body: "{}" });
    if (!response.ok) return setError("Não foi possível arquivar o cliente.");
    setNotice("Cliente arquivado com sucesso."); await load();
  }

  return <div className="workspace-page clients-workspace">
    <PageHeading eyebrow="Relacionamento" title="Clientes" description="Cadastre e mantenha as empresas atendidas nos projetos." action={access["parameters.create"] ? "Nova empresa" : null} onAction={() => open()} />
    {(error || notice) && <p className={`clients-feedback ${error ? "error" : "success"}`} role={error ? "alert" : "status"}>{error || notice}</p>}
    <section className="clients-summary"><article><Building2 size={22} /><span><strong>{clients.length}</strong> empresas cadastradas</span></article><article><CheckCircle2 size={22} /><span><strong>{clients.filter((client) => client.active).length}</strong> clientes ativos</span></article></section>
    <div className="clients-toolbar"><label><Search size={18} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar por empresa, CNPJ ou e-mail..." /></label><span>{visible.length} {visible.length === 1 ? "resultado" : "resultados"}</span></div>
    <section className="clients-panel">
      <div className="clients-table-head"><span>Empresa</span><span>CNPJ</span><span>Contato</span><span>Situação</span><span>Ações</span></div>
      {loading ? <p className="clients-empty">Carregando clientes...</p> : visible.map((client) => <article key={client.id}><span className="client-company"><i><Building2 size={20} /></i><span><strong translate="no">{client.name}</strong><small translate="no">{client.legal_name || "Razão social não informada"}</small></span></span><span>{client.document ? cnpj(client.document) : "Não informado"}</span><span className="client-contact"><strong translate="no">{client.email || "Sem e-mail"}</strong><small translate="no">{client.phone || "Sem telefone"}</small></span><span><em className={client.active ? "active" : "inactive"}>{client.active ? "Ativo" : "Inativo"}</em></span><span className="client-actions">{access["parameters.update"] && <><button type="button" onClick={() => open(client)}>Editar</button>{client.active && <button className="archive" type="button" onClick={() => archive(client)}>Arquivar</button>}</>}</span></article>)}
      {!loading && !visible.length && <div className="clients-empty"><Building2 size={38} /><strong>Nenhuma empresa encontrada</strong><span>Cadastre um cliente ou altere os termos da busca.</span></div>}
    </section>
    {editor !== false && <div className="clients-modal" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && setEditor(false)}><form onSubmit={save} role="dialog" aria-modal="true" aria-labelledby="client-editor-title"><header><div><span><Building2 size={20} /></span><div><h2 id="client-editor-title">{editor?.id ? "Editar cliente" : "Cadastrar empresa cliente"}</h2><p>Informe os dados empresariais usados nos projetos.</p></div></div><button type="button" aria-label="Fechar" onClick={() => setEditor(false)}><X size={19} /></button></header><div className="client-form-grid"><label><span>Nome fantasia</span><input required maxLength={150} value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></label><label><span>Razão social</span><input required maxLength={180} value={form.legal_name} onChange={(event) => setForm({ ...form, legal_name: event.target.value })} /></label><label><span>CNPJ</span><input required inputMode="numeric" placeholder="00.000.000/0000-00" value={form.document} onChange={(event) => setForm({ ...form, document: cnpj(event.target.value) })} /></label><label><span>E-mail corporativo</span><input type="email" maxLength={255} value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label><label><span>Telefone</span><input maxLength={30} value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} /></label><label><span>Situação</span><select value={form.active ? "1" : "0"} onChange={(event) => setForm({ ...form, active: event.target.value === "1" })}><option value="1">Ativo</option><option value="0">Inativo</option></select></label></div><footer><button className="secondary-button" type="button" onClick={() => setEditor(false)}>Cancelar</button><button className="primary-action" disabled={saving}>{saving ? "Salvando..." : "Salvar cliente"}</button></footer></form></div>}
  </div>;
}

function DocumentsPage() {
  const [documents, setDocuments] = useState([]);
  const [projects, setProjects] = useState([]);
  const [search, setSearch] = useState("");
  const [projectFilter, setProjectFilter] = useState("all");
  const [uploadProject, setUploadProject] = useState("");
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [feedback, setFeedback] = useState(null);
  const fileInput = useRef(null);

  async function load() {
    setLoading(true);
    const [documentResponse, projectResponse] = await Promise.all([sessionRequest("/documents"), sessionRequest("/projects")]);
    const documentData = await documentResponse.json();
    const projectData = await projectResponse.json();
    if (!documentResponse.ok) throw new Error(documentData.message || "Não foi possível carregar os documentos.");
    setDocuments(documentData.documents || []);
    if (projectResponse.ok) setProjects(Array.isArray(projectData) ? projectData : []);
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
    try {
      await fetch("/sanctum/csrf-cookie", { credentials: "same-origin" });
      const response = await fetch("/api/v1/documents", { method: "POST", credentials: "same-origin", headers: { Accept: "application/json", "X-XSRF-TOKEN": xsrfToken() }, body });
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
    .filter((document) => `${document.name} ${document.project?.name || ""}`.toLocaleLowerCase("pt-BR").includes(search.trim().toLocaleLowerCase("pt-BR")));
  const formatSize = (bytes) => bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
  const extension = (name) => name.includes(".") ? name.split(".").pop().toUpperCase() : "ARQ";

  return <div className="documents-page workspace-page">
    <div className="workspace-heading"><div><span className="workspace-eyebrow">ARQUIVOS</span><h1>Meus documentos</h1><p>Organize e encontre os arquivos vinculados aos seus projetos.</p></div><div className="documents-upload-actions"><select aria-label="Projeto do novo documento" value={uploadProject} onChange={(event) => setUploadProject(event.target.value)}><option value="">Sem projeto</option>{projects.map((project) => <option value={project.id} key={project.id} translate="no">{project.name}</option>)}</select><button className="primary-action" disabled={uploading} type="button" onClick={() => fileInput.current?.click()}><Upload size={17} /> {uploading ? "Enviando..." : "Enviar arquivo"}</button><input ref={fileInput} hidden type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.zip" onChange={uploadDocument} /></div></div>
    {feedback && <p className={`documents-feedback ${feedback.type}`}>{feedback.text}</p>}
    <div className="documents-toolbar"><label><Search size={18} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar documentos..." /></label><select value={projectFilter} onChange={(event) => setProjectFilter(event.target.value)}><option value="all">Todos os projetos</option><option value="none">Sem projeto</option>{projects.map((project) => <option value={project.id} key={project.id} translate="no">{project.name}</option>)}</select><span>{filtered.length} arquivo{filtered.length === 1 ? "" : "s"}</span></div>
    <section className="documents-panel">
      <header><span>Nome</span><span>Projeto</span><span>Tamanho</span><span>Enviado em</span><span>Ações</span></header>
      {loading ? <p className="documents-empty">Carregando documentos...</p> : !filtered.length ? <div className="documents-empty"><Files size={38} /><strong>Nenhum documento encontrado</strong><span>Envie um arquivo para começar.</span></div> : filtered.map((document) => <article key={document.id}><span className="document-name"><i>{extension(document.name)}</i><strong title={document.name} translate="no">{document.name}</strong></span><span>{document.project?.name || "Sem projeto"}</span><span>{formatSize(document.size)}</span><span>{new Date(document.created_at).toLocaleDateString(getFormatLocale())}</span><span className="document-actions"><a href={`/api/v1/documents/${document.id}/download`} title="Baixar documento"><Download size={18} /></a><button type="button" title="Excluir documento" onClick={() => removeDocument(document)}><Trash2 size={18} /></button></span></article>)}
    </section>
    <small className="documents-hint">Arquivos permitidos: PDF, Office, texto, imagens e ZIP · limite de 10 MB.</small>
  </div>;
}

function HistoryPage() {
  const [events, setEvents] = useState([]);
  const [filter, setFilter] = useState("all");
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [nextPage,setNextPage]=useState(null);
  async function loadMore(){setError("");try{const response=await sessionRequest(`/history?before=${encodeURIComponent(nextPage)}`);const data=await response.json();if(!response.ok)throw new Error(data.message);setEvents(current=>[...current,...data.events]);setNextPage(data.next);}catch(e){setError(e.message);}}
  const actionMeta = {
    "task.created": ["Tarefa criada.", "Uma tarefa foi cadastrada no projeto.", ListChecks, "work"],
    "task.updated": ["Tarefa atualizada.", "Os dados de uma tarefa foram alterados.", ListChecks, "work"],
    "task.deleted": ["Tarefa excluída.", "Uma tarefa foi removida do projeto.", ListChecks, "work"],
    "project.created": ["Projeto criado", "Um novo projeto foi cadastrado", FolderKanban, "project"],
    "project.updated": ["Projeto atualizado", "As informações do projeto foram alteradas", FolderKanban, "project"],
    "project.finalized": ["Projeto finalizado", "O projeto foi marcado como concluído", CheckCircle2, "project"],
    "project.reopened": ["Projeto reaberto", "O projeto voltou a ficar ativo", History, "project"],
    "project.deleted": ["Projeto excluído", "O projeto foi removido", Trash2, "project"],
    "project.member_assigned": ["Pessoa atribuída", "Um integrante foi adicionado ao projeto", Users, "project"],
    "expense.created": ["Despesa registrada", "Uma despesa foi adicionada ao projeto", Files, "financial"],
    "expense.approved": ["Despesa aprovada", "Uma despesa foi aprovada", Check, "financial"],
    "expense.rejected": ["Despesa rejeitada", "Uma despesa foi rejeitada", X, "financial"],
    "work_log.created": ["Horas registradas", "Um apontamento de trabalho foi criado", Clock3, "work"],
    "invitation.created": ["Convite criado", "Uma pessoa foi convidada para o projeto", Mail, "access"],
    "invitation.accepted": ["Convite aceito", "Uma pessoa entrou no projeto", UserRound, "access"],
    "invitation.revoked": ["Convite revogado", "O acesso do convite foi cancelado", LockKeyhole, "access"],
    "user.activation_created": ["Acesso criado", "Um link de primeiro acesso foi gerado", KeyRound, "access"],
    "user.activated": ["Conta ativada", "O primeiro acesso foi concluído", UserRound, "access"],
    "user.access_updated": ["Acesso atualizado", "Perfil ou situação de uma conta foi alterado", ShieldCheck, "access"],
    "security.password_changed": ["Senha alterada", "A senha da conta foi atualizada", KeyRound, "security"],
    "security.sessions_revoked": ["Sessões encerradas", "Outras sessões da conta foram encerradas", Laptop, "security"],
    "security.2fa_enabled": ["Verificação em duas etapas ativada", "A proteção adicional foi configurada", ShieldCheck, "security"],
    "security.2fa_disabled": ["Verificação em duas etapas desativada", "A proteção adicional foi removida", ShieldCheck, "security"],
  };

  useEffect(() => {
    sessionRequest("/history").then(async (response) => {
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || "Não foi possível carregar o histórico.");
      setEvents(data.events || []);setNextPage(data.next);
    }).catch((requestError) => setError(requestError.message)).finally(() => setLoading(false));
  }, []);

  const metadata = (event) => actionMeta[event.action] || [event.action.replaceAll(".", " · "), "Atividade registrada no Spot", History, "other"];
  const visible = events.filter((event) => filter === "all" || metadata(event)[3] === filter)
    .filter((event) => `${metadata(event)[0]} ${metadata(event)[1]} ${event.user?.name || ""}`.toLocaleLowerCase("pt-BR").includes(search.trim().toLocaleLowerCase("pt-BR")));
  const grouped = visible.reduce((result, event) => {
    const date = new Date(event.created_at);
    const today = new Date();
    const yesterday = new Date(); yesterday.setDate(today.getDate() - 1);
    const key = date.toDateString() === today.toDateString() ? "Hoje" : date.toDateString() === yesterday.toDateString() ? "Ontem" : date.toLocaleDateString(getFormatLocale(), { day: "2-digit", month: "long", year: "numeric" });
    (result[key] ||= []).push(event); return result;
  }, {});

  return <div className="history-page workspace-page">
    <div className="workspace-heading"><div><span className="workspace-eyebrow">ATIVIDADES</span><h1>Histórico</h1><p>Acompanhe alterações, acessos e acontecimentos importantes da sua conta.</p></div></div>
    <div className="history-toolbar"><label><Search size={18} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar no histórico..." /></label><div>{[["all", "Tudo"], ["project", "Projetos"], ["work", "Trabalho"], ["access", "Acessos"], ["security", "Segurança"]].map(([value, label]) => <button className={filter === value ? "active" : ""} type="button" key={value} onClick={() => setFilter(value)}>{label}</button>)}</div></div>
    {error && <p className="home-state error">{error}</p>}
    <section className="history-panel">
      {loading ? <p className="history-empty">Carregando atividades...</p> : !visible.length ? <div className="history-empty"><History size={40} /><strong>Nenhuma atividade encontrada</strong><span>Os acontecimentos importantes aparecerão aqui.</span></div> : Object.entries(grouped).map(([date, dateEvents]) => <div className="history-day" key={date}><h2>{date}</h2><div>{dateEvents.map((event) => { const [title, description, EventIcon, tone] = metadata(event); return <article key={event.id}><span className={`history-icon ${tone}`}><EventIcon size={18} /></span><span><strong>{title}</strong><p>{description}</p>{event.new_values !== undefined && <details><summary>Detalhes da alteração</summary><pre translate="no">{JSON.stringify({antes:event.old_values,depois:event.new_values},null,2)}</pre></details>}<small>{event.user?.name || "Spot"}{event.ip_address ? ` · IP ${event.ip_address}` : ""}</small></span><time>{new Date(event.created_at).toLocaleTimeString(getFormatLocale(), { hour: "2-digit", minute: "2-digit" })}</time></article>; })}</div></div>)}
    </section>{nextPage&&<button className="secondary-button" onClick={loadMore}>Carregar mais</button>}
  </div>;
}

function Dashboard({ onLogout, user, onUserUpdate }) {
  const [menuOpen, setMenuOpen] = useState(false);
  const [activePage, setActivePage] = useState("home");
  const [selectedTeamId, setSelectedTeamId] = useState(null);
  const [tutorialProfileTab, setTutorialProfileTab] = useState(null);
  const [pageMenuOpen, setPageMenuOpen] = useState(false);
  const [profileOpen, setProfileOpen] = useState(false);
  const [inboxPlacement, setInboxPlacement] = useState(null);
  const [homeSearch, setHomeSearch] = useState("");
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchIndex, setSearchIndex] = useState(null);
  const [searchLoading, setSearchLoading] = useState(false);
  const [incompleteOnly, setIncompleteOnly] = useState(true);
  const [homeFilter, setHomeFilter] = useState("all");
  const [sortAscending, setSortAscending] = useState(true);
  const [quickSettingsOpen, setQuickSettingsOpen] = useState(false);
  const [integrationsOpen, setIntegrationsOpen] = useState(false);
  const [appsOpen, setAppsOpen] = useState(false);
  const [appsPinned, setAppsPinned] = useState(false);
  const [lastUpdatedAt, setLastUpdatedAt] = useState(null);
  const [theme, setTheme] = useState(() => {
    const saved = window.localStorage.getItem("spot.theme");
    if (saved === "dark" || saved === "light") return saved;
    return window.matchMedia?.("(prefers-color-scheme: dark)").matches ? "dark" : "light";
  });
  const [locale, setLocale] = useState(getLanguage);
  const inboxTimer = useRef(null);
  const appsTimer = useRef(null);
  const appsWrapRef = useRef(null);
  const pageMenuRef = useRef(null);
  const searchRef = useRef(null);
  const quickSettingsRef = useRef(null);
  const pageMeta = {
    home: [HomeIcon, "Home"],
    inbox: [Mail, "Caixa de entrada"],
    calendar: [CalendarDays, "Agenda"],
    projects: [FolderKanban, "Projetos"],
    teams: [Users, "Equipes"],
    ...(user?.can_view_parameters || user?.can_manage_identity ? { clients: [Building2, "Clientes"] } : {}),
    tasks: [ListChecks, "Tarefas"],
    documents: [Files, "Meus documentos"],
    history: [History, "Histórico"],
    analytics: [BarChart3, "Portfólios"],
    "new-project": [FilePlus2, "Novo projeto"],
    profile: [UserRound, "Meu perfil"],
    ...(user?.can_view_parameters || user?.can_manage_identity ? { "parameters": [Settings, "Cadastros e configurações"] } : {}),
    ...(user?.can_manage_identity ? { "admin-users": [Users, "Usuários e acessos"] } : {}),
  };
  const [PageIcon, pageLabel] = pageMeta[activePage] || pageMeta.home;
  const normalizedSearch = homeSearch.trim().toLocaleLowerCase("pt-BR");
  const pageSearchResults = normalizedSearch ? Object.entries(pageMeta)
    .filter(([, [, label]]) => translateText(label, locale).toLocaleLowerCase(getFormatLocale()).includes(normalizedSearch))
    .map(([page, [Icon, label]]) => ({ id: `page-${page}`, type: "Página", label, page, Icon })) : [];
  const dataSearchResults = normalizedSearch && searchIndex ? [
    ...(searchIndex.projects || []).filter((item) => item.name.toLocaleLowerCase("pt-BR").includes(normalizedSearch)).map((item) => ({ id: `project-${item.id}`, type: "Projeto", label: item.name, detail: message("{count}% concluído", { count: item.progress }), page: "projects", Icon: FolderKanban })),
    ...(searchIndex.tasks || []).filter((item) => `${item.title} ${item.project_name}`.toLocaleLowerCase("pt-BR").includes(normalizedSearch)).map((item) => ({ id: `task-${item.id}`, type: "Tarefa", label: item.title, detail: item.project_name, page: "tasks", Icon: ListChecks })),
    ...(searchIndex.teams || []).filter((item) => item.name.toLocaleLowerCase("pt-BR").includes(normalizedSearch)).map((item) => ({ id: `team-${item.project_id}`, type: "Time", label: item.name, detail: message(item.count === 1 ? "{count} pessoa" : "{count} pessoas", { count: item.count }), page: "teams", projectId: item.project_id, Icon: Users })),
  ].slice(0, 8) : [];
  const globalSearchResults = [...pageSearchResults, ...dataSearchResults].slice(0, 8);

  async function loadSearchIndex() {
    if (searchIndex || searchLoading) return;
    setSearchLoading(true);
    try {
      const response = await sessionRequest("/home-dashboard");
      const result = await response.json();
      if (response.ok) setSearchIndex(result);
    } finally {
      setSearchLoading(false);
    }
  }

  function openSearchResult(result) {
    if (result.projectId) setSelectedTeamId(result.projectId);
    setActivePage(result.page);
    setHomeSearch("");
    setSearchOpen(false);
  }

  useEffect(() => {
    document.documentElement.dataset.theme = theme;
    document.documentElement.style.colorScheme = theme;
    window.localStorage.setItem("spot.theme", theme);
    return () => {
      delete document.documentElement.dataset.theme;
      document.documentElement.style.colorScheme = "";
    };
  }, [theme]);

  useEffect(() => {
    const translatedPageLabel = translateText(pageLabel, locale);
    document.title = `Spot · ${translatedPageLabel}`;
  }, [pageLabel, locale]);


  useEffect(() => {
    if (!pageMenuOpen) return undefined;
    const closePageMenu = (event) => {
      if (event.type === "keydown" && event.key !== "Escape") return;
      if (event.type === "mousedown" && pageMenuRef.current?.contains(event.target)) return;
      setPageMenuOpen(false);
    };
    document.addEventListener("mousedown", closePageMenu);
    document.addEventListener("keydown", closePageMenu);
    return () => {
      document.removeEventListener("mousedown", closePageMenu);
      document.removeEventListener("keydown", closePageMenu);
    };
  }, [pageMenuOpen]);

  useEffect(() => {
    if (!searchOpen) return undefined;
    const closeSearch = (event) => {
      if (event.type === "keydown" && event.key === "Escape") return setSearchOpen(false);
      if (event.type === "mousedown" && !searchRef.current?.contains(event.target)) setSearchOpen(false);
    };
    document.addEventListener("mousedown", closeSearch);
    document.addEventListener("keydown", closeSearch);
    return () => {
      document.removeEventListener("mousedown", closeSearch);
      document.removeEventListener("keydown", closeSearch);
    };
  }, [searchOpen]);

  useEffect(() => {
    if (!quickSettingsOpen) return undefined;
    const closeQuickSettings = (event) => {
      if (event.type === "keydown" && event.key === "Escape") return setQuickSettingsOpen(false);
      if (event.type === "mousedown" && !quickSettingsRef.current?.contains(event.target)) setQuickSettingsOpen(false);
    };
    document.addEventListener("mousedown", closeQuickSettings);
    document.addEventListener("keydown", closeQuickSettings);
    return () => {
      document.removeEventListener("mousedown", closeQuickSettings);
      document.removeEventListener("keydown", closeQuickSettings);
    };
  }, [quickSettingsOpen]);

  const navigateTutorial = useCallback((step) => {
    setActivePage(step.page);
    setTutorialProfileTab(step.tab ? { tab: step.tab, step: step.id } : null);
    setMenuOpen(false);
    setPageMenuOpen(false);
    setProfileOpen(false);
    setSearchOpen(false);
    setHomeSearch("");
    setInboxPlacement(null);
    setQuickSettingsOpen(false);
    setAppsOpen(false);
    setAppsPinned(false);
    setIntegrationsOpen(step.panel === 'integrations');
    window.clearTimeout(inboxTimer.current);
    window.clearTimeout(appsTimer.current);
  }, []);
  const exitTutorial = useCallback(() => {
    setIntegrationsOpen(false);
    setTutorialProfileTab(null);
  }, []);

  function navigateFromPageMenu(page) {
    setActivePage(page);
    setPageMenuOpen(false);
  }

  useEffect(() => {
    if (!appsPinned) return undefined;
    const closePinnedApps = (event) => {
      if (!appsWrapRef.current?.contains(event.target)) {
        setAppsPinned(false);
        setAppsOpen(false);
      }
    };
    document.addEventListener("mousedown", closePinnedApps);
    return () => document.removeEventListener("mousedown", closePinnedApps);
  }, [appsPinned]);

  function showInbox(placement) {
    window.clearTimeout(inboxTimer.current);
    setInboxPlacement(placement);
  }

  function scheduleInboxClose() {
    window.clearTimeout(inboxTimer.current);
    inboxTimer.current = window.setTimeout(() => setInboxPlacement(null), 55);
  }

  function showApps() {
    window.clearTimeout(appsTimer.current);
    setAppsOpen(true);
  }

  function scheduleAppsClose() {
    window.clearTimeout(appsTimer.current);
    if (!appsPinned) appsTimer.current = window.setTimeout(() => setAppsOpen(false), 220);
  }

  function toggleAppsPin() {
    window.clearTimeout(appsTimer.current);
    setAppsPinned((pinned) => {
      setAppsOpen(!pinned);
      return !pinned;
    });
  }

  function updatedLabel() {
    if (!lastUpdatedAt) return "Carregando última atualização...";
    const updated = new Date(lastUpdatedAt);
    const today = new Date();
    const sameDay = updated.toDateString() === today.toDateString();
    const date = sameDay ? translateText("hoje") : updated.toLocaleDateString(getFormatLocale());
    return message("Atualizado pela última vez {date} às {time}", { date, time: updated.toLocaleTimeString(getFormatLocale(), { hour: "2-digit", minute: "2-digit" }) });
  }

  return (
    <main className="dashboard-shell">
      <Sidebar
        open={menuOpen}
        onClose={() => setMenuOpen(false)}
        activePage={activePage}
        onNavigate={setActivePage}
        onInboxEnter={showInbox}
        onInboxLeave={scheduleInboxClose}
        inboxPinned={inboxPlacement === "sidebar"}
        canViewParameters={user?.can_view_parameters || user?.can_manage_identity}
      />

      <section className="dashboard-content">
        <header className="topbar">
          <div className="home-label" ref={pageMenuRef}>
            <button className="mobile-menu" type="button" onClick={() => setMenuOpen(true)} aria-label="Abrir menu">
              <Menu size={25} />
            </button>
            <button
              className={`page-menu-trigger ${pageMenuOpen ? "is-open" : ""}`}
              type="button"
              aria-label="Selecionar página"
              aria-haspopup="menu"
              aria-expanded={pageMenuOpen}
              onClick={() => setPageMenuOpen((open) => !open)}
            >
              <PageIcon className="home-mark" size={34} />
              <span>{pageLabel}</span>
              <ChevronDown size={22} />
            </button>
            {pageMenuOpen && (
              <div className="page-menu" role="menu" aria-label="Páginas">
                {Object.entries(pageMeta).map(([page, [Icon, label]]) => (
                  <button
                    className={activePage === page ? "active" : ""}
                    type="button"
                    role="menuitem"
                    key={page}
                    onClick={() => navigateFromPageMenu(page)}
                  >
                    <Icon size={19} strokeWidth={1.7} />
                    <span>{label}</span>
                    {activePage === page && <Check size={17} aria-hidden="true" />}
                  </button>
                ))}
              </div>
            )}
          </div>

          <div className="topbar-tools">
            <div className="quick-settings-wrap" ref={quickSettingsRef}>
              <button className={quickSettingsOpen ? "active" : ""} type="button" aria-label="Ajustes rápidos" aria-expanded={quickSettingsOpen} aria-haspopup="dialog" onClick={() => setQuickSettingsOpen((open) => !open)}><SlidersHorizontal size={29} /></button>
              {quickSettingsOpen && <div className="quick-settings-popover" role="dialog" aria-label="Ajustes rápidos do trabalho">
                <header><span><SlidersHorizontal size={18} /> Ajustes rápidos</span><button type="button" aria-label="Fechar ajustes" onClick={() => setQuickSettingsOpen(false)}><X size={17} /></button></header>
                <label className="quick-settings-check"><span><strong>Somente pendentes</strong><small>Oculta tarefas concluídas</small></span><input type="checkbox" checked={incompleteOnly} onChange={(event) => setIncompleteOnly(event.target.checked)} /></label>
                <label><span>Filtro de tarefas</span><select value={homeFilter} onChange={(event) => setHomeFilter(event.target.value)}><option value="all">Todas</option><option value="high">Prioridade alta</option><option value="overdue">Atrasadas</option></select></label>
                <label><span>Ordenação</span><select value={sortAscending ? "asc" : "desc"} onChange={(event) => setSortAscending(event.target.value === "asc")}><option value="asc">A–Z</option><option value="desc">Z–A</option></select></label>
                <button className="quick-settings-reset" type="button" onClick={() => { setIncompleteOnly(true); setHomeFilter("all"); setSortAscending(true); }}>Restaurar padrão</button>
              </div>}
            </div>
            <label className={`search-box ${searchOpen ? "is-open" : ""}`} ref={searchRef}>
              <Search size={27} />
              <input
                aria-label="Pesquisar no Spot"
                aria-expanded={searchOpen}
                aria-controls="global-search-results"
                value={homeSearch}
                onFocus={() => { setSearchOpen(true); loadSearchIndex(); }}
                onChange={(event) => { setHomeSearch(event.target.value); setSearchOpen(true); loadSearchIndex(); }}
                onKeyDown={(event) => {
                  if (event.key === "Enter" && globalSearchResults[0]) openSearchResult(globalSearchResults[0]);
                }}
                placeholder="Pesquisar..."
              />
              {homeSearch && <button className="search-clear" type="button" aria-label="Limpar pesquisa" onClick={() => setHomeSearch("")}><X size={17} /></button>}
              {searchOpen && normalizedSearch && <div className="global-search-results" id="global-search-results" role="listbox">
                {searchLoading && !searchIndex ? <p>Buscando...</p> : globalSearchResults.length ? globalSearchResults.map((result) => <button type="button" role="option" key={result.id} onClick={() => openSearchResult(result)}>
                  <result.Icon size={19} />
                  <span><strong>{result.label}</strong><small>{result.type}{result.detail ? ` · ${result.detail}` : ""}</small></span>
                  <ChevronRight size={17} />
                </button>) : <p>Nenhum resultado encontrado.</p>}
              </div>}
            </label>
          </div>

          <div className="account-tools">
            <SpotTutorial key={user?.email || 'guest'} user={user} locale={locale} onNavigate={navigateTutorial} onExit={exitTutorial} onLanguageChange={(code) => { setLanguage(code); setLocale(code); }} />
            <div className="language-switcher" role="group" aria-label="Idioma">
              {[
                ["pt", "🇧🇷", "Português"],
                ["en", "🇺🇸", "English"],
                ["es", "🇪🇸", "Español"],
              ].map(([code, flag, label]) => (
                <button
                  className={locale === code ? "active" : ""}
                  type="button"
                  key={code}
                  title={label}
                  aria-label={label}
                  aria-pressed={locale === code}
                  onClick={() => { setLanguage(code); setLocale(code); }}
                >
                  <span aria-hidden="true">{flag}</span>
                </button>
              ))}
            </div>
            <button
              className="theme-toggle"
              type="button"
              aria-label={theme === "dark" ? "Ativar modo claro" : "Ativar modo escuro"}
              title={theme === "dark" ? "Modo claro" : "Modo escuro"}
              aria-pressed={theme === "dark"}
              onClick={() => setTheme((current) => current === "dark" ? "light" : "dark")}
            >
              {theme === "dark" ? <Sun size={28} /> : <Moon size={28} />}
            </button>
            <button
              type="button"
              aria-label="Notificações"
              onClick={() => { setActivePage("inbox"); setInboxPlacement(null); }}
              onMouseEnter={() => showInbox("top")}
              onMouseLeave={scheduleInboxClose}
            ><Bell size={29} /></button>
            <button
              className={`profile-button ${profileOpen ? "active" : ""}`}
              type="button"
              aria-label="Abrir menu do perfil"
              onClick={() => setProfileOpen((open) => !open)}
            >
              <UserAvatar user={user} size={52} />
            </button>
            {profileOpen && (
              <div className="profile-menu">
                <div className="profile-menu-user">
                  <UserAvatar user={user} size={43} />
                  <span><strong>{user?.name || "Alexandre Silva"}</strong><small>{user?.email || "alexandre@computecnica.com.br"}</small></span>
                </div>
                <button type="button" onClick={() => { setTutorialProfileTab({ tab: "personal", source: "profile-menu" }); setActivePage("profile"); setProfileOpen(false); }}><UserRound size={17} /><span>Meu perfil</span></button>
                {user?.can_manage_identity && <button type="button" onClick={() => { setActivePage("admin-users"); setProfileOpen(false); }}><Settings size={17} /><span>Usuários e acessos</span></button>}
                <button type="button" onClick={() => { setTutorialProfileTab({ tab: "security", source: "profile-menu" }); setActivePage("profile"); setProfileOpen(false); }}><ShieldCheck size={17} /><span>Privacidade e segurança</span></button>
                <div className="profile-menu-rule" />
                <button className="logout-item" type="button" onClick={onLogout}><LogOut size={17} /><span>Sair da conta</span></button>
              </div>
            )}
          </div>
        </header>

        {inboxPlacement && (
          <InboxPopover
            placement={inboxPlacement}
            onMouseEnter={() => showInbox(inboxPlacement)}
            onMouseLeave={scheduleInboxClose}
            onOpenInbox={() => { setInboxPlacement(null); setActivePage("inbox"); }}
          />
        )}

        <div className={`actionbar ${activePage !== "home" ? "workspace-actionbar" : ""} ${["analytics", "new-project"].includes(activePage) ? "analytics-actionbar" : ""}`}>
          <span>{activePage === "home" ? updatedLabel() : activePage === "analytics" ? "Relatórios e indicadores" : activePage === "new-project" ? "Cadastro de projeto" : "Área de trabalho"}</span>
          {!["analytics", "new-project"].includes(activePage) && <div>
            <button className={incompleteOnly ? "active" : ""} type="button" onClick={() => setIncompleteOnly((value) => !value)}><CheckCircle2 size={20} fill="#242424" /> {incompleteOnly ? "tarefas incompletas" : "todas as tarefas"}</button>
            <button type="button" onClick={() => setHomeFilter((value) => value === "all" ? "high" : value === "high" ? "overdue" : "all")}><Filter size={21} /> {homeFilter === "high" ? "prioridade alta" : homeFilter === "overdue" ? "atrasadas" : "filtrar"}</button>
            <button type="button" onClick={() => setSortAscending((value) => !value)}><ArrowDownUp size={21} /> {sortAscending ? "A–Z" : "Z–A"}</button>
            <div ref={appsWrapRef} className={`apps-hover-wrap ${appsOpen ? "is-open" : ""} ${appsPinned ? "is-pinned" : ""}`} onMouseEnter={showApps} onMouseLeave={scheduleAppsClose}>
              <button type="button" aria-expanded={appsOpen} aria-haspopup="dialog" onClick={toggleAppsPin}><AppWindow size={22} /> apps</button>
              <AppsPopover onManage={() => { setIntegrationsOpen(true); setAppsOpen(false); setAppsPinned(false); }} />
            </div>
          </div>}
        </div>

        {activePage === "home" && <HomeDashboard search={homeSearch} incompleteOnly={incompleteOnly} filterMode={homeFilter} sortAscending={sortAscending} onNavigate={setActivePage} onEditTeam={(projectId) => { setSelectedTeamId(projectId); setActivePage("teams"); }} onUpdated={setLastUpdatedAt} />}
        {activePage === "inbox" && <InboxPage />}
        {activePage === "calendar" && <CalendarPage />}
        {activePage === "projects" && <ProjectWorkspace request={sessionRequest} onNewProject={user?.can_create_projects ? () => setActivePage("new-project") : undefined} />}
        {activePage === "teams" && <TeamsPage initialProjectId={selectedTeamId} />}
        {activePage === "clients" && (user?.can_view_parameters || user?.can_manage_identity) && <ClientsPage />}
        {activePage === "new-project" && (user?.can_create_projects
          ? <NewProjectPage onCreated={() => setActivePage("projects")} onCancel={() => setActivePage("projects")} />
          : <div className="workspace-page new-project-page">
              <PageHeading eyebrow="Área de trabalho" title="Novo projeto" description="Cadastro de projetos" />
              <section className="analytics-error restricted-project-access" aria-labelledby="restricted-project-title">
                <LockKeyhole size={28} aria-hidden="true" />
                <h2 id="restricted-project-title">Área restrita</h2>
                <p>Caso necessário acesso, falar com gerência</p>
              </section>
            </div>)}
        {activePage === "tasks" && <TasksPage request={sessionRequest} />}
        {activePage === "documents" && <DocumentsPage />}
        {activePage === "history" && <HistoryPage />}
        {activePage === "analytics" && <ProjectAnalyticsPage />}
        {activePage === "profile" && <ProfilePage user={user} onUserUpdate={onUserUpdate} tutorialTab={tutorialProfileTab} />}
        {activePage === "parameters" && <ParametersWorkspace request={sessionRequest} onUsers={() => setActivePage("admin-users")} />}
        {activePage === "admin-users" && user?.can_manage_identity && <AdminUsersPage />}
      </section>
      {integrationsOpen && <IntegrationsModal onClose={() => setIntegrationsOpen(false)} />}
    </main>
  );
}

function FirstAccessPage() {
  const token = window.location.pathname.split('/').filter(Boolean).at(-1);
  const requesting = token === 'first-access';
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
  const [error, setError] = useState('');
  const submit = async (event) => {
    event.preventDefault(); setError('');
    const response = await sessionRequest(requesting ? '/first-access' : `/first-access/${encodeURIComponent(token)}`, { method: 'POST', body: JSON.stringify(form) });
    const data = await response.json();
    if (!response.ok) return setError(data.message || Object.values(data.errors || {}).flat()[0] || 'Não foi possível ativar a conta.');
    if (requesting) return setError(data.message);
    window.location.assign('/?home');
  };
  return <main className="page-shell"><section className="login-card"><div className="login-panel"><div className="form-wrap"><header><h1>Primeiro acesso ao <strong>Spot</strong></h1><p>Confirme os dados cadastrados pelo administrador e crie sua senha.</p></header><form onSubmit={submit}>
    <label>Nome e sobrenome<input required value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label>
    <label>E-mail<input required type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label>
    {!requesting && <><label>Nova senha<input required minLength={8} type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></label>
    <label>Confirmar senha<input required minLength={8} type="password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} /></label></>}
    {error && <p className="login-error">{error}</p>}<button type="submit" className="login-button">{requesting ? 'Enviar link de ativação' : 'Ativar minha conta'}</button>
  </form></div></div></section></main>;
}

function AcceptInvitationPage() {
  const token = window.location.pathname.split("/").filter(Boolean).at(-1);
  const alreadyAuthenticated = Boolean(window.__SPOT__?.authenticated);
  const [form, setForm] = useState({ name: "", password: "", password_confirmation: "" });
  const [invitation, setInvitation] = useState(null);
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const requiresPassword = !alreadyAuthenticated || Boolean(invitation?.requires_password_creation);

  useEffect(() => {
    sessionRequest(`/invitations/${encodeURIComponent(token)}`)
      .then(async (response) => {
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || "Este convite não está mais disponível.");
        setInvitation(data);
      })
      .catch((requestError) => setError(requestError.message));
  }, [token]);

  async function accept(event) {
    event.preventDefault();
    setError(""); setSubmitting(true);
    try {
      const response = await sessionRequest(`/invitations/${encodeURIComponent(token)}/accept`, {
        method: "POST",
        body: JSON.stringify({
          ...(!alreadyAuthenticated ? { name: form.name } : {}),
          ...(requiresPassword ? { password: form.password, password_confirmation: form.password_confirmation } : {}),
        }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || "Este convite não pôde ser aceito.");
      window.location.assign("/?home");
    } catch (requestError) {
      setError(requestError.message);
      setSubmitting(false);
    }
  }

  return <main className="page-shell"><section className="login-card"><div className="login-panel"><div className="form-wrap"><header><h1>Aceitar convite do <strong>Spot</strong></h1><p>Este acesso será válido somente para o projeto e as permissões concedidas.</p></header><form onSubmit={accept}>
    {!alreadyAuthenticated && <label className="field"><span>Nome e sobrenome</span><div className="input-wrap"><UserRound size={19} /><input required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></div></label>}
    {alreadyAuthenticated && <p>Você está conectado. Confirme para adicionar este projeto à sua conta.</p>}
    {requiresPassword && <><p><strong>Criação de senha obrigatória</strong><br /><small>Use no mínimo 10 caracteres, com letras maiúsculas, minúsculas e números.</small></p><label className="field"><span>Crie uma nova senha</span><div className="input-wrap"><LockKeyhole size={19} /><input required minLength={10} type="password" autoComplete="new-password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} /></div></label><label className="field"><span>Confirme a nova senha</span><div className="input-wrap"><LockKeyhole size={19} /><input required minLength={10} type="password" autoComplete="new-password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} /></div></label></>}
    {error && <p className="login-error">{error}</p>}<button className="submit-button" type="submit" disabled={submitting || !invitation}>{submitting ? "Aceitando..." : "Aceitar convite"}</button>
  </form></div></div></section></main>;
}

function LegacySpotApp() {
  if (window.location.pathname.startsWith("/estoque")) return <InventoryApp />;
  if (window.location.pathname.startsWith("/accept-invitation/")) return <AcceptInvitationPage />;
  const [showPassword, setShowPassword] = useState(false);
  const [user, setUser] = useState(() => window.__SPOT__?.user || null);
  const [authenticated, setAuthenticated] = useState(
    () => Boolean(window.__SPOT__?.authenticated || new URLSearchParams(window.location.search).has("home")),
  );
  const [loginError, setLoginError] = useState("");
  const [twoFactorRequired, setTwoFactorRequired] = useState(false);

  if (window.location.pathname.startsWith("/first-access/")) return <FirstAccessPage />;

  async function handleSubmit(event) {
    event.preventDefault();
    setLoginError("");
    const form = new FormData(event.currentTarget);
    try {
      const response = await sessionRequest("/auth/login", {
        method: "POST",
        body: JSON.stringify({ email: form.get("email"), password: form.get("password"), two_factor_code: form.get("two_factor_code") || null }),
      });
      const data = await response.json();
      if (!response.ok) {
        if (data.two_factor_required) setTwoFactorRequired(true);
        throw new Error(data.message || "Não foi possível entrar.");
      }
      setUser(data.user);
      setAuthenticated(true);
    } catch (error) {
      setLoginError(error.message);
    }
  }

  async function handleLogout() {
    await sessionRequest("/auth/logout", { method: "POST" });
    setUser(null);
    setAuthenticated(false);
  }

  if (authenticated) return <Dashboard onLogout={handleLogout} user={user} onUserUpdate={setUser} />;

  if (window.location.pathname === "/forgot-password") return <ForgotPasswordPage />;
  if (window.location.pathname.startsWith("/reset-password/")) return <ResetPasswordPage />;

  return (
    <main className="page-shell">
      <div className="decor decor-one" />
      <div className="decor decor-two" />
      <div className="decor decor-three" />
      <div className="decor decor-four" />
      <div className="decor decor-five" />
      <div className="decor decor-six" />

      <section className="login-card" aria-label="Acesso ao Spot">
        <div className="login-panel">
          <div className="form-wrap">
            <header>
              <h1>Seja bem-vindo ao <strong>Spot</strong></h1>
            </header>

            <form onSubmit={handleSubmit}>
              <label className="field">
                <span>E-mail</span>
                <div className="input-wrap">
                  <Mail size={19} aria-hidden="true" />
                  <input type="email" name="email" placeholder="seu@email.com" required />
                </div>
              </label>

              <label className="field">
                <span>Senha</span>
                <div className="input-wrap">
                  <LockKeyhole size={19} aria-hidden="true" />
                  <input
                    type={showPassword ? "text" : "password"}
                    name="password"
                    placeholder="Digite sua senha"
                    required
                  />
                  <button
                    className="password-toggle"
                    type="button"
                    onClick={() => setShowPassword((value) => !value)}
                    aria-label={showPassword ? "Ocultar senha" : "Mostrar senha"}
                    title={showPassword ? "Ocultar senha" : "Mostrar senha"}
                  >
                    {showPassword ? <EyeOff size={19} /> : <Eye size={19} />}
                  </button>
                </div>
              </label>

              {twoFactorRequired && <label className="field"><span>Código de autenticação</span><div className="input-wrap"><ShieldCheck size={19} aria-hidden="true" /><input name="two_factor_code" autoComplete="one-time-code" placeholder="6 dígitos ou código de recuperação" required /></div></label>}

              <div className="form-options">
                <label className="remember">
                  <input type="checkbox" />
                  <span>Lembre-se de mim</span>
                </label>
                <a href="/forgot-password">Esqueci minha senha</a>
              </div>

              <button className="submit-button" type="submit">Entrar</button>
              <div className="status">{loginError}</div>
            </form>

            <div className="divider"><span>ou continue com</span></div>

            <div className="social-actions">
              <button type="button" onClick={() => window.location.assign("/auth/google")}>
                <img src="/google-logo.svg" alt="" />
                Google
              </button>
              <button type="button" onClick={() => window.location.assign("/auth/microsoft")}>
                <img src="/microsoft-logo.png" alt="" />
                Microsoft
              </button>
            </div>
            <a className="first-access-link" href="/first-access">Primeiro acesso</a>

            <div className="brand">
              <img src="/spot-logo.png" alt="SPOT - Organize, planeje, execute" />
            </div>
          </div>
        </div>

        <aside className="illustration-panel" aria-label="Ilustração de acesso digital">
          <img src="/login-illustration.png" alt="" />
        </aside>
      </section>

      <footer>
        <img src="/computecnica-logo.png" alt="Computécnica" />
      </footer>
    </main>
  );
}

// A V2 passa a ser a experiência principal do Spot. A implementação anterior
// permanece isolada acima apenas para facilitar uma eventual consulta/migração.
export default function App() {
  const [language, updateLanguage] = useState(getLanguage);
  useEffect(() => {
    const refreshLanguage = () => updateLanguage(getLanguage());
    window.addEventListener('spot:language', refreshLanguage);
    return () => window.removeEventListener('spot:language', refreshLanguage);
  }, []);
  useEffect(() => observeTranslations(language), [language]);
  return <LegacySpotApp />;
}

function AuthShell({ children }) {
  return (
    <main className="page-shell recovery-page">
      <div className="decor decor-one" /><div className="decor decor-two" /><div className="decor decor-three" />
      <div className="decor decor-four" /><div className="decor decor-five" /><div className="decor decor-six" />
      <section className="login-card recovery-card">
        <div className="login-panel"><div className="form-wrap">{children}</div></div>
        <aside className="illustration-panel recovery-illustration"><img src="/password-recovery-illustration-v2.png" alt="Ilustração de recuperação segura de acesso" /></aside>
      </section>
      <footer><img src="/computecnica-logo.png" alt="Computécnica" /></footer>
    </main>
  );
}

function ForgotPasswordPage() {
  const [message, setMessage] = useState("");
  const [loading, setLoading] = useState(false);

  async function submit(event) {
    event.preventDefault();
    setLoading(true);
    setMessage("");
    const email = new FormData(event.currentTarget).get("email");
    try {
      const response = await fetch("/forgot-password", {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "" },
        body: JSON.stringify({ email }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || "Não foi possível enviar o link.");
      setMessage(result.message);
    } catch (error) { setMessage(error.message); } finally { setLoading(false); }
  }

  return <AuthShell>
    <header className="recovery-heading"><span className="recovery-icon"><LockKeyhole size={24} /></span><h1>Recupere sua senha</h1><p>Informe seu e-mail e enviaremos um link seguro para criar uma nova senha.</p></header>
    <form onSubmit={submit}>
      <label className="field"><span>E-mail</span><div className="input-wrap"><Mail size={19} /><input type="email" name="email" placeholder="seu@email.com" required autoFocus /></div></label>
      <button className="submit-button" type="submit" disabled={loading}>{loading ? "Enviando..." : "Enviar link de recuperação"}</button>
      {message && <div className="recovery-message">{message}</div>}
    </form>
    <a className="back-to-login" href="/">← Voltar para o login</a>
    <div className="brand"><img src="/spot-logo.png" alt="SPOT" /></div>
  </AuthShell>;
}

function ResetPasswordPage() {
  const [message, setMessage] = useState("");
  const [success, setSuccess] = useState(false);
  const token = decodeURIComponent(window.location.pathname.split("/").pop());
  const email = new URLSearchParams(window.location.search).get("email") || "";

  async function submit(event) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    try {
      const response = await fetch("/reset-password", {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "" },
        body: JSON.stringify({ token, email, password: data.get("password"), password_confirmation: data.get("password_confirmation") }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || "Não foi possível redefinir a senha.");
      setSuccess(true); setMessage(result.message);
    } catch (error) { setMessage(error.message); }
  }

  return <AuthShell>
    <header className="recovery-heading"><span className="recovery-icon"><ShieldCheck size={24} /></span><h1>Crie uma nova senha</h1><p>Use pelo menos 8 caracteres e escolha uma senha que você não utiliza em outros serviços.</p></header>
    {success ? <><div className="recovery-message success">{message}</div><a className="submit-button recovery-login-button" href="/">Entrar no Spot</a></> : <form onSubmit={submit}>
      <label className="field"><span>Nova senha</span><div className="input-wrap"><LockKeyhole size={19} /><input type="password" name="password" minLength="8" required /></div></label>
      <label className="field"><span>Confirmar nova senha</span><div className="input-wrap"><LockKeyhole size={19} /><input type="password" name="password_confirmation" minLength="8" required /></div></label>
      <button className="submit-button" type="submit">Redefinir senha</button>
      {message && <div className="recovery-message error">{message}</div>}
    </form>}
    <a className="back-to-login" href="/">← Voltar para o login</a>
  </AuthShell>;
}
