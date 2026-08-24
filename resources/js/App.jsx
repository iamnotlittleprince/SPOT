import React, { useEffect, useRef, useState } from "react";
import InventoryApp from "./InventoryApp";
import {
  Archive,
  AppWindow,
  ArrowDownUp,
  Bell,
  CalendarDays,
  CalendarRange,
  Camera,
  Check,
  CheckCheck,
  CheckCircle2,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  CircleUserRound,
  ClipboardList,
  Columns3,
  Eye,
  EyeOff,
  FilePlus2,
  Files,
  Filter,
  Flag,
  FolderClock,
  FolderKanban,
  Gauge,
  History,
  Home as HomeIcon,
  LayoutDashboard,
  ListChecks,
  LogOut,
  LockKeyhole,
  MapPin,
  KeyRound,
  Laptop,
  Link2,
  Mail,
  Menu,
  MoreHorizontal,
  MessageCircle,
  Paperclip,
  Plus,
  Search,
  Settings,
  ShieldCheck,
  SlidersHorizontal,
  Table2,
  Users,
  Video,
  UserRound,
  X,
} from "lucide-react";

function xsrfToken() {
  return decodeURIComponent(document.cookie.split("; ").find((item) => item.startsWith("XSRF-TOKEN="))?.split("=").slice(1).join("=") || "");
}

async function sessionRequest(path, options = {}) {
  const method = (options.method || "GET").toUpperCase();
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
  return fetch(`/api/v1${path}`, {
    ...options,
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(!['GET', 'HEAD', 'OPTIONS'].includes(method) ? { 'X-XSRF-TOKEN': xsrfToken() } : {}),
      ...options.headers,
    },
  });
}

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
      [FilePlus2, "Novo Projeto"],
      [ListChecks, "Minhas tarefas"],
      [Files, "Meus documentos"],
      [History, "Histórico"],
    ],
  },
];

const projects = [
  ["Projeto - Google", "Feito", "done"],
  ["Projeto - Microsoft", "Andamento", "progress"],
  ["Projeto - Apple", "Parado", "stopped"],
];

const tasks = [
  ["Alinhar com cliente - Proposta Final", "10 de maio", "orange"],
  ["Desenvolver proposta", "12 de maio", "green"],
  ["Fechar Projeto", "12 de maio", "red"],
];

const teams = [
  ["Time - Projeto Google", 3],
  ["Time - Projeto Microsoft", 2],
  ["Time - Projeto Apple", 4],
];

function Sidebar({ open, onClose, activePage, onNavigate, onInboxEnter, onInboxLeave, inboxPinned }) {
  const [expandedGroups, setExpandedGroups] = useState(() => navGroups.map(() => true));
  const [quickExpanded, setQuickExpanded] = useState(false);
  const [recentItems, setRecentItems] = useState(() => {
    try { return JSON.parse(window.localStorage.getItem("spot.sidebar.recent") || "[]"); }
    catch { return []; }
  });
  const pageByLabel = {
    Home: "home",
    "Caixa de entrada": "inbox",
    Agenda: "calendar",
    "Meus Projetos": "projects",
    "Novo Projeto": "projects",
    "Minhas tarefas": "tasks",
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
            {(expandedGroups[groupIndex] ? group.items : group.items.slice(0, 3)).map(([Icon, label]) => {
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
              if (!item) return null;
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
  return (
    <aside
      className={`inbox-popover ${placement === "top" ? "from-top" : "from-sidebar"}`}
      onMouseEnter={onMouseEnter}
      onMouseLeave={onMouseLeave}
      aria-label="Prévia da caixa de entrada"
    >
      <header>
        <div><span>Caixa de entrada</span><b>3</b></div>
        <button type="button" aria-label="Opções"><MoreHorizontal size={19} /></button>
      </header>
      <div className="inbox-filter">
        <button className="active" type="button">Tudo</button>
        <button type="button">Não lidas <span>2</span></button>
      </div>
      <div className="inbox-items">
        <article className="inbox-item unread">
          <div className="inbox-item-icon"><CheckCircle2 size={18} /></div>
          <div className="inbox-item-content">
            <strong>Alinhar com cliente – Proposta Final</strong>
            <span><CircleUserRound size={24} fill="#d3d9de" stroke="#fff" /> vence hoje</span>
          </div>
          <div className="inbox-item-actions"><Paperclip size={16} /><MoreHorizontal size={18} /></div>
        </article>
        <small className="inbox-time">Há 10 minutos</small>
        <article className="inbox-item">
          <div className="inbox-item-icon document"><Files size={17} /></div>
          <div className="inbox-item-content">
            <strong>Aline enviou um documento</strong>
            <span><AvatarStack count={2} /> Projeto Microsoft</span>
          </div>
          <div className="inbox-item-actions"><Paperclip size={16} /><MoreHorizontal size={18} /></div>
        </article>
        <small className="inbox-time">Há 1 dia</small>
        <article className="inbox-item">
          <div className="inbox-item-icon mention"><Users size={17} /></div>
          <div className="inbox-item-content">
            <strong>Você foi mencionado em uma tarefa</strong>
            <span><CircleUserRound size={24} fill="#d3d9de" stroke="#fff" /> Portal do Colaborador</span>
          </div>
          <div className="inbox-item-actions"><MoreHorizontal size={18} /></div>
        </article>
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

  async function load() {
    setLoading(true); setError("");
    const response = await sessionRequest("/inbox");
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || "Não foi possível carregar a caixa de entrada.");
    setItems(data.items);
    setSelectedId((current) => current && data.items.some((item) => item.id === current) ? current : data.items[0]?.id || null);
    setLoading(false);
  }
  useEffect(() => { load().catch((requestError) => { setError(requestError.message); setLoading(false); }); }, []);

  const filtered = items.filter((item) => filter === "all" || (filter === "unread" ? !item.read_at : item.type === filter.slice(0, -1)))
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
  }

  return <div className="inbox-page workspace-page">
    <header className="inbox-page-heading"><div><span className="workspace-eyebrow">COMUNICAÇÃO</span><h1>Caixa de entrada</h1><p>Acompanhe tarefas, menções, documentos e atualizações dos seus projetos.</p></div><button className="secondary-button" type="button" disabled={!unread} onClick={markAllRead}><CheckCheck size={17} /> Marcar todas como lidas</button></header>
    {error && <p className="home-state error">{error}</p>}
    <div className="inbox-page-toolbar"><label><Search size={18} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Buscar mensagens..." /></label><div>{[["all", "Todas"], ["unread", `Não lidas (${unread})`], ["tasks", "Tarefas"], ["documents", "Documentos"], ["mentions", "Menções"]].map(([key, label]) => <button className={filter === key ? "active" : ""} type="button" key={key} onClick={() => setFilter(key)}>{label}</button>)}</div></div>
    <div className="inbox-page-layout">
      <section className="inbox-message-list" aria-label="Mensagens">{loading && <p className="inbox-page-empty">Carregando mensagens...</p>}{!loading && !filtered.length && <p className="inbox-page-empty">Nenhuma mensagem encontrada.</p>}{filtered.map((item) => { const [TypeIcon, typeLabel] = typeMeta[item.type] || typeMeta.update; return <button className={`${selectedId === item.id ? "selected" : ""} ${!item.read_at ? "unread" : ""}`} type="button" key={item.id} onClick={() => markRead(item)}><span className={`inbox-type-icon ${item.type}`}><TypeIcon size={18} /></span><span><strong>{item.title}</strong><small>{item.project?.name || typeLabel}</small><em>{item.body}</em></span><time>{new Date(item.created_at).toLocaleDateString("pt-BR", { day: "2-digit", month: "short" })}</time></button>; })}</section>
      <section className="inbox-message-detail">{!selected ? <div className="inbox-detail-empty"><Mail size={35} /><strong>Selecione uma mensagem</strong><span>Os detalhes aparecerão aqui.</span></div> : (() => { const [TypeIcon, typeLabel] = typeMeta[selected.type] || typeMeta.update; return <><header><span className={`inbox-type-icon ${selected.type}`}><TypeIcon size={19} /></span><span><small>{typeLabel}</small><h2>{selected.title}</h2></span><button type="button" title="Arquivar" aria-label="Arquivar mensagem" onClick={() => archive(selected)}><Archive size={19} /></button></header><div className="inbox-detail-body"><p>{selected.body || "Sem informações adicionais."}</p><dl><div><dt>Projeto</dt><dd>{selected.project?.name || "Geral"}</dd></div><div><dt>Enviado por</dt><dd>{selected.actor_name || "Spot"}</dd></div><div><dt>Recebido em</dt><dd>{new Date(selected.created_at).toLocaleString("pt-BR")}</dd></div></dl></div></>; })()}</section>
    </div>
  </div>;
}

function CalendarPage() {
  const [month, setMonth] = useState(() => new Date(new Date().getFullYear(), new Date().getMonth(), 1));
  const [data, setData] = useState({ events: [], providers: {}, errors: [] });
  const [selected, setSelected] = useState(null);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState("");
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
    event.preventDefault(); setError(""); const form = new FormData(event.currentTarget);
    const response = await sessionRequest('/calendar/events', { method: 'POST', body: JSON.stringify(Object.fromEntries(form)) }); const result = await response.json();
    if (!response.ok) return setError(result.message || Object.values(result.errors || {}).flat()[0] || 'Não foi possível criar o evento.');
    setCreating(false); await load();
  }
  const providerLabel = { spot: 'Spot', google: 'Google', microsoft: 'Outlook', teams: 'Teams' };
  return <div className="calendar-page workspace-page">
    <header className="calendar-heading"><div><span className="workspace-eyebrow">PLANEJAMENTO</span><h1>Agenda</h1><p>Seus compromissos do Spot, Google Calendar, Outlook e Teams em um só lugar.</p></div><button className="primary-action" type="button" onClick={() => setCreating(true)}><Plus size={17} /> Novo evento</button></header>
    <div className="calendar-connections"><span><i className="spot" /> Spot</span><span className={data.providers.google ? 'connected' : ''}><i className="google" /> Google Calendar {data.providers.google ? 'conectado' : 'desconectado'}</span><span className={data.providers.microsoft ? 'connected' : ''}><i className="microsoft" /> Microsoft/Teams {data.providers.microsoft ? 'conectado' : 'desconectado'}</span>{(!data.providers.google || !data.providers.microsoft) && <button type="button" onClick={() => window.scrollTo(0,0)}>Conectar em Apps</button>}</div>
    {(error || data.errors?.length > 0) && <p className="home-state error">{error || data.errors.join(' ')}</p>}
    <section className="calendar-shell"><header><div><button type="button" onClick={() => setMonth(new Date())}>Hoje</button><button type="button" aria-label="Mês anterior" onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth()-1,1))}><ChevronLeft size={18}/></button><button type="button" aria-label="Próximo mês" onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth()+1,1))}><ChevronRight size={18}/></button></div><h2>{month.toLocaleDateString('pt-BR',{month:'long',year:'numeric'})}</h2><span>{data.events.length} eventos</span></header><div className="calendar-weekdays">{['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'].map(day=><span key={day}>{day}</span>)}</div><div className="calendar-grid">{days.map(day=><div className={`${day.getMonth()!==month.getMonth()?'outside ':''}${day.toDateString()===new Date().toDateString()?'today':''}`} key={day.toISOString()}><strong>{day.getDate()}</strong><div>{eventsFor(day).slice(0,3).map(event=><button className={event.provider} type="button" key={event.id} onClick={()=>setSelected(event)} title={event.title}><time>{new Date(event.start).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'})}</time>{event.title}</button>)}{eventsFor(day).length>3&&<small>+{eventsFor(day).length-3} eventos</small>}</div></div>)}</div></section>
    {selected && <div className="calendar-event-popover"><button type="button" onClick={()=>setSelected(null)}><X size={18}/></button><em className={selected.provider}>{providerLabel[selected.provider]}</em><h3>{selected.title}</h3><p>{selected.description||'Sem descrição.'}</p><span><CalendarDays size={16}/>{new Date(selected.start).toLocaleString('pt-BR')} – {new Date(selected.end).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'})}</span>{selected.url&&<a href={selected.url} target="_blank" rel="noreferrer"><Video size={16}/> Abrir reunião ou evento</a>}</div>}
    {creating && <div className="calendar-modal" onMouseDown={event=>event.target===event.currentTarget&&setCreating(false)}><form onSubmit={createEvent}><header><div><h2>Novo evento</h2><p>Escolha em qual agenda o compromisso será criado.</p></div><button type="button" onClick={()=>setCreating(false)}><X size={19}/></button></header><label>Título<input name="title" required maxLength="150" /></label><div><label>Início<input name="starts_at" type="datetime-local" required /></label><label>Fim<input name="ends_at" type="datetime-local" required /></label></div><label>Agenda<select name="provider" required><option value="spot">Spot</option><option value="google" disabled={!data.providers.google}>Google Calendar{!data.providers.google?' — conecte a conta':''}</option><option value="microsoft" disabled={!data.providers.microsoft}>Outlook{!data.providers.microsoft?' — conecte a conta':''}</option><option value="teams" disabled={!data.providers.microsoft}>Reunião do Teams{!data.providers.microsoft?' — conecte a conta':''}</option></select></label><label>Local<input name="location" placeholder="Sala ou endereço" /></label><label>Descrição<textarea name="description" rows="4" /></label><footer><button className="secondary-button" type="button" onClick={()=>setCreating(false)}>Cancelar</button><button className="primary-action" type="submit">Criar evento</button></footer></form></div>}
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
              <span className="app-tile"><img src={`/apps/integrations/${icon}.svg`} alt="" /></span>
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
              <span className="app-tile"><img src={`/apps/integrations/${icon}.svg`} alt="" /></span>
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
    if (!window.confirm(`Desconectar sua conta ${providerMeta[provider].name}?`)) return;
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
            <div className="integration-provider-main"><img src={meta.logo} alt="" /><span><strong>{meta.name}</strong><small>{meta.description}</small></span><em className={connected ? "connected" : ""}>{connected ? "Conectada" : "Não conectada"}</em></div>
            <div className="integration-services">{meta.services.map((service) => <span key={service}>{service}</span>)}</div>
            <footer><span>{connected ? <>Conta: <strong>{provider.email}</strong></> : "Conecte sua conta para ativar os aplicativos."}</span>{connected ? <button className="disconnect-integration" type="button" disabled={busy === key} onClick={() => disconnect(key)}>{busy === key ? "Desconectando..." : "Desconectar"}</button> : <button className="connect-integration" type="button" onClick={() => window.location.assign(meta.auth)}>Conectar conta</button>}</footer>
          </article>;
        })}
      </div>
      <aside className="integration-security-note"><ShieldCheck size={18} /><span><strong>Conexão protegida por OAuth 2.0</strong><small>O Spot não recebe nem armazena a senha das suas contas externas. As permissões serão solicitadas pelo próprio provedor.</small></span></aside>
    </section>
  </div>;
}

const managedProjects = [
  { name: "Website Institucional", client: "Google Brasil", status: "Em andamento", tone: "blue", progress: 72, due: "18 mai", team: 4, tasks: "18/25" },
  { name: "Migração para Cloud", client: "Microsoft", status: "Em revisão", tone: "purple", progress: 88, due: "22 mai", team: 3, tasks: "31/36" },
  { name: "Aplicativo Mobile", client: "Apple", status: "Planejamento", tone: "yellow", progress: 24, due: "05 jun", team: 5, tasks: "7/29" },
  { name: "Portal do Colaborador", client: "Computécnica", status: "Concluído", tone: "green", progress: 100, due: "10 mai", team: 3, tasks: "42/42" },
];

const taskColumns = [
  {
    title: "A fazer", tone: "slate",
    cards: [
      { title: "Mapear jornada do usuário", project: "Website Institucional", priority: "Alta", due: "Hoje", comments: 4, files: 2 },
      { title: "Definir arquitetura da API", project: "Aplicativo Mobile", priority: "Média", due: "16 mai", comments: 2, files: 1 },
      { title: "Revisar escopo com cliente", project: "Migração para Cloud", priority: "Alta", due: "17 mai", comments: 6, files: 0 },
    ],
  },
  {
    title: "Em andamento", tone: "blue",
    cards: [
      { title: "Criar protótipo navegável", project: "Website Institucional", priority: "Alta", due: "Hoje", comments: 8, files: 3 },
      { title: "Configurar ambiente staging", project: "Migração para Cloud", priority: "Média", due: "18 mai", comments: 3, files: 2 },
    ],
  },
  {
    title: "Em revisão", tone: "yellow",
    cards: [
      { title: "Validar identidade visual", project: "Website Institucional", priority: "Baixa", due: "19 mai", comments: 5, files: 4 },
      { title: "Testes de segurança", project: "Portal do Colaborador", priority: "Alta", due: "20 mai", comments: 7, files: 1 },
    ],
  },
  {
    title: "Concluído", tone: "green",
    cards: [
      { title: "Kickoff do projeto", project: "Aplicativo Mobile", priority: "Média", due: "12 mai", comments: 2, files: 3 },
      { title: "Aprovar proposta comercial", project: "Migração para Cloud", priority: "Baixa", due: "10 mai", comments: 1, files: 1 },
    ],
  },
];

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

function ProjectsPage() {
  const [view, setView] = useState("table");

  return (
    <div className="workspace-page">
      <PageHeading eyebrow="Área de trabalho" title="Gestão de projetos" description="Acompanhe entregas, responsáveis e prazos em um só lugar." action="Novo projeto" />
      <div className="workspace-toolbar">
        <div className="view-switch">
          <button className={view === "table" ? "active" : ""} onClick={() => setView("table")} type="button"><Table2 size={17} /> Tabela</button>
          <button className={view === "cards" ? "active" : ""} onClick={() => setView("cards")} type="button"><Columns3 size={17} /> Cards</button>
        </div>
        <div className="toolbar-actions">
          <button type="button"><Filter size={17} /> Filtrar</button>
          <button type="button"><ArrowDownUp size={17} /> Ordenar</button>
          <label><Search size={17} /><input placeholder="Buscar projeto..." /></label>
        </div>
      </div>

      {view === "table" ? (
        <div className="projects-table-wrap">
          <div className="projects-table projects-table-head">
            <span>Projeto</span><span>Status</span><span>Progresso</span><span>Responsáveis</span><span>Prazo</span><span>Tarefas</span><span />
          </div>
          {managedProjects.map((project) => (
            <article className="projects-table project-table-row" key={project.name}>
              <div className="project-identity"><span className={`project-icon ${project.tone}`}><FolderKanban size={18} /></span><span><strong>{project.name}</strong><small>{project.client}</small></span></div>
              <span><em className={`status-pill ${project.tone}`}>{project.status}</em></span>
              <div className="progress-cell"><div><i style={{ width: `${project.progress}%` }} /></div><strong>{project.progress}%</strong></div>
              <AvatarStack count={project.team} />
              <span className="due-cell"><CalendarDays size={16} />{project.due}</span>
              <span className="task-count">{project.tasks}</span>
              <button className="row-menu" type="button"><MoreHorizontal size={20} /></button>
            </article>
          ))}
          <button className="add-row" type="button"><Plus size={17} /> Adicionar projeto</button>
        </div>
      ) : (
        <div className="project-card-grid">
          {managedProjects.map((project) => (
            <article className="project-overview-card" key={project.name}>
              <div className="project-card-top"><span className={`project-icon ${project.tone}`}><FolderKanban size={20} /></span><MoreHorizontal size={20} /></div>
              <small>{project.client}</small><h2>{project.name}</h2>
              <em className={`status-pill ${project.tone}`}>{project.status}</em>
              <div className="project-card-progress"><span>Progresso <strong>{project.progress}%</strong></span><div><i style={{ width: `${project.progress}%` }} /></div></div>
              <footer><AvatarStack count={project.team} /><span><CalendarDays size={15} /> {project.due}</span></footer>
            </article>
          ))}
        </div>
      )}
    </div>
  );
}

function TasksPage() {
  return (
    <div className="workspace-page tasks-workspace">
      <PageHeading eyebrow="Meu trabalho" title="Gestão de tarefas" description="Priorize, organize e mova o trabalho pelo seu fluxo." action="Nova tarefa" />
      <div className="workspace-toolbar task-toolbar">
        <div className="view-switch">
          <button className="active" type="button"><Columns3 size={17} /> Quadro</button>
          <button type="button"><Table2 size={17} /> Lista</button>
          <button type="button"><CalendarRange size={17} /> Calendário</button>
        </div>
        <div className="toolbar-actions">
          <button type="button"><Filter size={17} /> Filtrar</button>
          <button type="button"><Users size={17} /> Pessoa</button>
          <label><Search size={17} /><input placeholder="Buscar tarefa..." /></label>
        </div>
      </div>
      <div className="kanban-board">
        {taskColumns.map((column) => (
          <section className="kanban-column" key={column.title}>
            <header>
              <span><i className={column.tone} />{column.title}<b>{column.cards.length}</b></span>
              <div><Plus size={18} /><MoreHorizontal size={19} /></div>
            </header>
            <div className="kanban-cards">
              {column.cards.map((card) => (
                <article className="kanban-card" key={card.title}>
                  <div className="card-project"><span>{card.project}</span><MoreHorizontal size={18} /></div>
                  <h2>{card.title}</h2>
                  <div className="card-tags">
                    <span className={`priority ${card.priority.toLowerCase().replace("é", "e")}`}><Flag size={12} />{card.priority}</span>
                    <span className={card.due === "Hoje" ? "today" : ""}><CalendarDays size={13} />{card.due}</span>
                  </div>
                  <div className="card-footer">
                    <CircleUserRound size={29} fill="#d9e2ea" stroke="#8795a1" />
                    <div><span><MessageCircle size={14} />{card.comments}</span>{card.files > 0 && <span><Paperclip size={14} />{card.files}</span>}</div>
                  </div>
                </article>
              ))}
              <button className="add-task-card" type="button"><Plus size={17} /> Adicionar tarefa</button>
            </div>
          </section>
        ))}
      </div>
    </div>
  );
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
            <span className="invitation-success"><CheckCircle2 size={19} /> Convite enviado por e-mail</span>
            <p>O convite foi enviado para <strong>{form.email}</strong>. Como alternativa, você também pode copiar o link abaixo. Ele expira no prazo definido e pode ser utilizado uma única vez.</p>
            <div className="invitation-link"><input readOnly value={acceptUrl} /><button type="button" onClick={copyInvitation}>{copied ? "Copiado" : "Copiar link"}</button></div>
          </div>
        ) : (
          <form onSubmit={submitInvitation}>
            {loading ? <p className="invitation-loading">Carregando projetos...</p> : <>
              <label>Projeto<select required value={form.project_id} onChange={(event) => setForm({ ...form, project_id: event.target.value })}><option value="">Selecione um projeto</option>{projects.map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}</select></label>
              {!projects.length && <p className="invitation-warning">Nenhum projeto disponível. Cadastre um projeto antes de criar o convite.</p>}
              <label>E-mail da pessoa<input required type="email" placeholder="pessoa@empresa.com" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label>
              {canManageIdentity && form.project_role !== "guest" && <div className="invitation-grid"><label>Cargo<input maxLength={120} placeholder="Ex.: Analista de projetos" value={form.job_title} onChange={(event) => setForm({ ...form, job_title: event.target.value })} /></label><label>Departamento<input maxLength={120} placeholder="Ex.: Projetos" value={form.department} onChange={(event) => setForm({ ...form, department: event.target.value })} /></label></div>}
              <div className="invitation-grid">
                <label>Papel no projeto<select value={form.project_role} onChange={(event) => { const role = event.target.value; setForm({ ...form, project_role: role, relationship_type: role === "analyst" ? "cpt_internal_analyst" : role === "manager" ? "responsible_manager" : "guest_client", permissions: role === "guest" ? form.permissions.filter((permission) => ["tasks.view", "files.view"].includes(permission)) : form.permissions }); }}><option value="analyst">Analista</option><option value="manager">Gestor</option><option value="guest">Convidado</option></select></label>
                <label>Vínculo<select value={form.relationship_type} onChange={(event) => setForm({ ...form, relationship_type: event.target.value })}>{form.project_role === "analyst" && <><option value="cpt_internal_analyst">Analista interno CPT</option><option value="external_analyst">Analista externo</option></>}{form.project_role === "manager" && <option value="responsible_manager">Gestor responsável</option>}{form.project_role === "guest" && <><option value="guest_client">Cliente convidado</option><option value="guest_analyst">Analista convidado</option><option value="guest_manager">Gestor convidado</option></>}</select></label>
              </div>
              {form.relationship_type === "external_analyst" && <label>Empresa do analista externo<input required maxLength={160} placeholder="Nome da empresa" value={form.organization_name} onChange={(event) => setForm({ ...form, organization_name: event.target.value })} /></label>}
              <label>Validade do convite<select value={form.expires_in_hours} onChange={(event) => setForm({ ...form, expires_in_hours: event.target.value })}><option value="24">24 horas</option><option value="72">3 dias</option><option value="168">7 dias</option></select><small>Após o aceite, o acesso permanece até a finalização do projeto.</small></label>
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
    const password = window.prompt("Confirme sua senha para desativar o 2FA:");
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
    <section className="security-card"><header><span><Laptop size={18} /><strong>Sessões ativas</strong></span><button className="text-button" type="button" onClick={async () => { try { await request("/security/sessions", { method: "DELETE", body: "{}" }); await loadSecurity(); } catch (requestError) { setError(requestError.message); } }}>Encerrar outras</button></header><div className="session-list">{security.sessions.map((session) => <div key={session.id}><Laptop size={18} /><span><strong>{session.current ? "Esta sessão" : "Navegador conectado"}</strong><small>{session.ip_address || "IP não identificado"} · {new Date(session.last_activity).toLocaleString("pt-BR")}</small></span>{!session.current && <button type="button" onClick={() => revokeSession(session.id)}>Encerrar</button>}</div>)}</div></section>
    <section className="security-card"><header><span><Bell size={18} /><strong>Alertas de segurança</strong></span></header><div className="security-preferences">{[["notify_new_login", "Novo login"], ["notify_password_change", "Alteração de senha"], ["notify_provider_link", "Nova conta conectada"]].map(([key, label]) => <label key={key}><span>{label}</span><input type="checkbox" checked={Boolean(security.preferences[key])} onChange={(event) => savePreferences({ ...security.preferences, [key]: event.target.checked })} /></label>)}</div></section>
    <section className="security-card"><header><span><History size={18} /><strong>Histórico de segurança</strong></span></header>{!security.events.length ? <p>Nenhuma alteração de segurança registrada.</p> : <div className="session-list">{security.events.map((event) => <div key={event.id}><ShieldCheck size={18} /><span><strong>{{ "security.password_changed": "Senha alterada", "security.2fa_enabled": "2FA ativado", "security.2fa_disabled": "2FA desativado", "security.sessions_revoked": "Sessões encerradas", "security.provider_disconnected.google": "Conta Google desconectada", "security.provider_disconnected.microsoft": "Conta Microsoft desconectada" }[event.action] || event.action}</strong><small>{event.ip_address || "IP não identificado"} · {new Date(event.created_at).toLocaleString("pt-BR")}</small></span></div>)}</div>}</section>
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
    <section className="notification-matrix"><header><strong>Evento</strong><span>Spot</span><span>E-mail</span></header>{Object.entries(groups).map(([group, events]) => <div className="notification-group" key={group}><h3>{group}</h3>{events.map((event) => <div className="notification-row" key={event.key}><span>{event.label}</span><input aria-label={`${event.label} no Spot`} type="checkbox" checked={Boolean(data.preferences.events[event.key]?.in_app)} onChange={(e) => setPreference(["events", event.key, "in_app"], e.target.checked)} /><input aria-label={`${event.label} por e-mail`} type="checkbox" checked={Boolean(data.preferences.events[event.key]?.email)} onChange={(e) => setPreference(["events", event.key, "email"], e.target.checked)} /></div>)}</div>)}</section>
    <div className="notification-settings-grid"><section className="security-card"><header><span><Mail size={18} /><strong>Resumo de atividades</strong></span></header><label>Frequência<select value={data.preferences.digest.frequency} onChange={(e) => setPreference(["digest", "frequency"], e.target.value)}><option value="none">Não enviar</option><option value="daily">Diário</option><option value="weekly">Semanal</option></select></label><label>Horário<input type="time" value={data.preferences.digest.time} onChange={(e) => setPreference(["digest", "time"], e.target.value)} /></label></section><section className="security-card"><header><span><Bell size={18} /><strong>Horário silencioso</strong></span><label className="inline-switch"><input type="checkbox" checked={data.preferences.quiet_hours.enabled} onChange={(e) => setPreference(["quiet_hours", "enabled"], e.target.checked)} /> Ativar</label></header><label>Início<input type="time" value={data.preferences.quiet_hours.start} onChange={(e) => setPreference(["quiet_hours", "start"], e.target.value)} /></label><label>Fim<input type="time" value={data.preferences.quiet_hours.end} onChange={(e) => setPreference(["quiet_hours", "end"], e.target.value)} /></label></section></div>
    <div className="notification-actions">{saved && <span><CheckCircle2 size={16} /> Preferências salvas</span>}<button className="secondary-button" type="button" onClick={reset}>Restaurar padrões</button><button className="primary-action" type="button" onClick={save}>Salvar preferências</button></div>
  </div>;
}

function ProfilePage({ user, onUserUpdate }) {
  const [saved, setSaved] = useState(false);
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const [avatarMenuOpen, setAvatarMenuOpen] = useState(false);
  const [cameraOpen, setCameraOpen] = useState(false);
  const [cameraError, setCameraError] = useState("");
  const [inviteOpen, setInviteOpen] = useState(false);
  const [activeProfileTab, setActiveProfileTab] = useState("personal");
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
          <h2>{user?.name || "Alexandre Silva"}</h2>
          <p>{user?.job_title || "Cargo não informado"}</p>
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
                  return <article className="assigned-project-row" key={project.id}><div className="assigned-project-info"><span className="project-icon blue"><FolderKanban size={16} /></span><span><strong>{project.name}</strong><small>{project.finalized_at ? "Finalizado" : project.status === "in_progress" || project.status === "in-progress" ? "Em andamento" : project.status || "Planejamento"}</small></span></div><div className="assigned-project-progress"><span><small>Progresso</small><strong>{progress}%</strong></span><div><i style={{ width: `${progress}%` }} /></div></div></article>;
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

function AvatarStack({ count }) {
  return (
    <div className="avatar-stack" aria-label={`${count} participantes`}>
      {Array.from({ length: count }, (_, index) => (
        <span key={index}><CircleUserRound size={31} fill="#fff" strokeWidth={1.2} /></span>
      ))}
    </div>
  );
}

const statusPresentation = {
  done: ["Feito", "done"], completed: ["Feito", "done"],
  in_progress: ["Andamento", "progress"], "in-progress": ["Andamento", "progress"],
  planning: ["Planejamento", "progress"], stopped: ["Parado", "stopped"],
  cancelled: ["Cancelado", "stopped"],
};

function HomeDashboard({ search, incompleteOnly, filterMode, sortAscending, onNavigate, onUpdated }) {
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
  const formatDate = (date) => date ? new Intl.DateTimeFormat("pt-BR", { day: "2-digit", month: "short" }).format(new Date(`${date}T12:00:00`)) : "Sem prazo";

  return <div className="dashboard-main">
    <div className="home-heading"><h1>Painel</h1></div>
    {error && <p className="home-state error">{error}</p>}
    <div className="dashboard-grid">
      <section className="board-column status-column"><div className="column-title"><span><SlidersHorizontal size={20} /> status</span><span className="column-count">{projects.length}</span></div><div className="column-surface">{projects.map((project) => { const [label, tone] = project.finalized ? ["Feito", "done"] : statusPresentation[project.status] || [project.status || "Planejamento", "progress"]; return <button className="project-row" type="button" key={project.id} onClick={() => onNavigate("projects")}><ClipboardList size={31} strokeWidth={1.4} /><span>{project.name}<small>{project.progress}% concluído</small></span><strong className={tone}>{label}</strong></button>; })}{!projects.length && <p className="column-empty">Nenhum projeto encontrado.</p>}</div></section>
      <section className="board-column tasks-column"><div className="column-title"><span><ListChecks size={20} /> tarefas</span><span className="column-count">{tasks.length}</span></div><div className={`task-list ${!tasks.length ? "empty-surface" : ""}`}>{tasks.map((task) => <article className={`task-card ${task.status === "done" ? "is-done" : ""}`} key={task.id}><button className="task-name" type="button" disabled={!task.mutable} onClick={() => toggleTask(task)} title={task.mutable ? "Alternar conclusão" : "Tarefa somente para visualização"}><Check size={17} /><span>{task.title}<small>{task.project_name}</small></span></button><div className="task-meta"><span><CircleUserRound size={31} fill="#c9c9c9" stroke="#fff" />{formatDate(task.due_date)}</span><span><i className={`priority-dot ${task.priority}`} /><MoreHorizontal size={24} /></span></div></article>)}{!tasks.length && <p className="column-empty">Nenhuma tarefa encontrada.</p>}</div></section>
      <section className="board-column teams-column"><div className="column-title"><span><Users size={20} /> Times ativos</span><span className="column-count">{teams.length}</span></div><div className="column-surface team-surface">{teams.map((team) => <button className="team-row" type="button" key={team.project_id} onClick={() => onNavigate("projects")}><Users size={32} fill="#000" /><AvatarStack count={Math.min(team.count, 5)} /><span>{team.name}<small>{team.count} {team.count === 1 ? "pessoa" : "pessoas"}</small></span></button>)}{!teams.length && <p className="column-empty">Nenhum time ativo.</p>}</div></section>
    </div>
  </div>;
}

function Dashboard({ onLogout, user, onUserUpdate }) {
  const [menuOpen, setMenuOpen] = useState(false);
  const [activePage, setActivePage] = useState("home");
  const [profileOpen, setProfileOpen] = useState(false);
  const [inboxPlacement, setInboxPlacement] = useState(null);
  const [homeSearch, setHomeSearch] = useState("");
  const [incompleteOnly, setIncompleteOnly] = useState(true);
  const [homeFilter, setHomeFilter] = useState("all");
  const [sortAscending, setSortAscending] = useState(true);
  const [integrationsOpen, setIntegrationsOpen] = useState(false);
  const [appsOpen, setAppsOpen] = useState(false);
  const [appsPinned, setAppsPinned] = useState(false);
  const [lastUpdatedAt, setLastUpdatedAt] = useState(null);
  const inboxTimer = useRef(null);
  const appsTimer = useRef(null);
  const appsWrapRef = useRef(null);
  const pageMeta = {
    home: [HomeIcon, "Home"],
    inbox: [Mail, "Caixa de entrada"],
    calendar: [CalendarDays, "Agenda"],
    projects: [FolderKanban, "Projetos"],
    tasks: [ListChecks, "Tarefas"],
    profile: [UserRound, "Meu perfil"],
  };
  const [PageIcon, pageLabel] = pageMeta[activePage];

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
    const date = sameDay ? "hoje" : updated.toLocaleDateString("pt-BR");
    return `Atualizado pela última vez ${date} às ${updated.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" })}`;
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
      />

      <section className="dashboard-content">
        <header className="topbar">
          <div className="home-label">
            <button className="mobile-menu" type="button" onClick={() => setMenuOpen(true)} aria-label="Abrir menu">
              <Menu size={25} />
            </button>
            <PageIcon className="home-mark" size={34} />
            <span>{pageLabel}</span>
            <ChevronDown size={22} />
          </div>

          <div className="topbar-tools">
            <button type="button" aria-label="Ajustes rápidos"><SlidersHorizontal size={29} /></button>
            <label className="search-box">
              <Search size={27} />
              <input aria-label="Pesquisar" value={homeSearch} onChange={(event) => setHomeSearch(event.target.value)} placeholder="Pesquisar..." />
            </label>
          </div>

          <div className="account-tools">
            <button type="button" aria-label="Configurações"><Gauge size={31} /></button>
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
                <button type="button" onClick={() => { setActivePage("profile"); setProfileOpen(false); }}><UserRound size={17} /><span>Meu perfil</span></button>
                <button type="button"><Settings size={17} /><span>Configurações</span></button>
                <button type="button"><ShieldCheck size={17} /><span>Privacidade e segurança</span></button>
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

        <div className={`actionbar ${activePage !== "home" ? "workspace-actionbar" : ""}`}>
          <span>{activePage === "home" ? updatedLabel() : "Área de trabalho"}</span>
          <div>
            <button className={incompleteOnly ? "active" : ""} type="button" onClick={() => setIncompleteOnly((value) => !value)}><CheckCircle2 size={20} fill="#242424" /> {incompleteOnly ? "tarefas incompletas" : "todas as tarefas"}</button>
            <button type="button" onClick={() => setHomeFilter((value) => value === "all" ? "high" : value === "high" ? "overdue" : "all")}><Filter size={21} /> {homeFilter === "high" ? "prioridade alta" : homeFilter === "overdue" ? "atrasadas" : "filtrar"}</button>
            <button type="button" onClick={() => setSortAscending((value) => !value)}><ArrowDownUp size={21} /> {sortAscending ? "A–Z" : "Z–A"}</button>
            <div ref={appsWrapRef} className={`apps-hover-wrap ${appsOpen ? "is-open" : ""} ${appsPinned ? "is-pinned" : ""}`} onMouseEnter={showApps} onMouseLeave={scheduleAppsClose}>
              <button type="button" aria-expanded={appsOpen} aria-haspopup="dialog" onClick={toggleAppsPin}><AppWindow size={22} /> apps</button>
              <AppsPopover onManage={() => { setIntegrationsOpen(true); setAppsOpen(false); setAppsPinned(false); }} />
            </div>
          </div>
        </div>

        {activePage === "home" && <HomeDashboard search={homeSearch} incompleteOnly={incompleteOnly} filterMode={homeFilter} sortAscending={sortAscending} onNavigate={setActivePage} onUpdated={setLastUpdatedAt} />}
        {activePage === "inbox" && <InboxPage />}
        {activePage === "calendar" && <CalendarPage />}
        {activePage === "projects" && <ProjectsPage />}
        {activePage === "tasks" && <TasksPage />}
        {activePage === "profile" && <ProfilePage user={user} onUserUpdate={onUserUpdate} />}
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
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  async function accept(event) {
    event.preventDefault();
    setError(""); setSubmitting(true);
    try {
      const response = await sessionRequest(`/invitations/${encodeURIComponent(token)}/accept`, {
        method: "POST",
        body: JSON.stringify(alreadyAuthenticated ? {} : form),
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
    {!alreadyAuthenticated && <><label className="field"><span>Nome e sobrenome</span><div className="input-wrap"><UserRound size={19} /><input required value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></div></label><label className="field"><span>Crie uma senha</span><div className="input-wrap"><LockKeyhole size={19} /><input required minLength={8} type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} /></div></label><label className="field"><span>Confirme a senha</span><div className="input-wrap"><LockKeyhole size={19} /><input required minLength={8} type="password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} /></div></label></>}
    {alreadyAuthenticated && <p>Você está conectado. Confirme para adicionar este projeto à sua conta.</p>}
    {error && <p className="login-error">{error}</p>}<button className="submit-button" type="submit" disabled={submitting}>{submitting ? "Aceitando..." : "Aceitar convite"}</button>
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
