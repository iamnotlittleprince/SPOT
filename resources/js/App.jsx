import React, { useEffect, useRef, useState } from "react";
import InventoryApp from "./InventoryApp";
import {
  AppWindow,
  ArrowDownUp,
  Bell,
  CalendarDays,
  CalendarRange,
  Camera,
  Check,
  CheckCircle2,
  ChevronDown,
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
  UserRound,
  X,
} from "lucide-react";

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
  const pageByLabel = {
    Home: "home",
    "Meus Projetos": "projects",
    "Novo Projeto": "projects",
    "Minhas tarefas": "tasks",
  };

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
            {group.items.map(([Icon, label]) => {
              const page = pageByLabel[label];
              return (
              <button
                className={page && activePage === page ? "active" : ""}
                type="button"
                key={label}
                onClick={() => {
                  if (page) onNavigate(page);
                  onClose();
                }}
                onMouseEnter={() => label === "Caixa de entrada" && onInboxEnter("sidebar")}
                onMouseLeave={() => label === "Caixa de entrada" && onInboxLeave()}
              >
                <Icon size={18} strokeWidth={1.6} />
                <span>{label}</span>
              </button>
              );
            })}
            <small>Mostrar menos</small>
            {groupIndex === 0 && <div className="sidebar-rule" />}
          </nav>
        ))}

        <div className="quick-access">
          <FolderClock size={21} />
          <span>Acesso rápido</span>
        </div>
        <small className="show-more">Mostrar mais</small>
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

function AppsPopover() {
  const googleApps = [
    ["/apps/google-drive.svg", "Drive"],
    ["/apps/gmail.svg", "Gmail"],
    ["/apps/google-meet.svg", "Meet"],
    ["/apps/google-calendar.svg", "Agenda"],
    ["/apps/google-docs.svg", "Docs"],
  ];
  const microsoftApps = [
    ["/apps/microsoft-outlook.svg", "Outlook"],
    ["/apps/microsoft-teams.svg", "Teams"],
    ["/apps/microsoft-onedrive.svg", "OneDrive"],
    ["/apps/microsoft-word.svg", "Word"],
    ["/apps/microsoft-excel.svg", "Excel"],
  ];

  return (
    <aside className="apps-popover" aria-label="Aplicativos integrados">
      <header><span>Apps</span></header>
      <section>
        <div className="app-suite-title"><img src="/google-logo.png" alt="" /><span>Google Workspace</span></div>
        <div className="app-shortcuts">
          {googleApps.map(([src, label]) => (
            <button type="button" key={label}><span className="app-tile"><img src={src} alt="" /></span><small>{label}</small></button>
          ))}
        </div>
      </section>
      <section>
        <div className="app-suite-title"><img src="/microsoft-logo.png" alt="" /><span>Microsoft 365</span></div>
        <div className="app-shortcuts">
          {microsoftApps.map(([src, label]) => (
            <button type="button" key={label}><span className="app-tile"><img src={src} alt="" /></span><small>{label}</small></button>
          ))}
        </div>
      </section>
      <button className="manage-apps" type="button">Gerenciar integrações</button>
    </aside>
  );
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

function PageHeading({ eyebrow, title, description, action }) {
  return (
    <header className="workspace-heading">
      <div>
        <span className="workspace-eyebrow">{eyebrow}</span>
        <h1>{title}</h1>
        <p>{description}</p>
      </div>
      <button className="primary-action" type="button"><Plus size={18} /> {action}</button>
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

function ProfilePage({ user, onUserUpdate }) {
  const [saved, setSaved] = useState(false);
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const [avatarMenuOpen, setAvatarMenuOpen] = useState(false);
  const [cameraOpen, setCameraOpen] = useState(false);
  const [cameraError, setCameraError] = useState("");
  const avatarInput = useRef(null);
  const videoRef = useRef(null);
  const streamRef = useRef(null);

  useEffect(() => () => {
    streamRef.current?.getTracks().forEach((track) => track.stop());
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
      <PageHeading eyebrow="Minha conta" title="Meu perfil" description="Gerencie seus dados pessoais e preferências da conta." action="Convidar pessoa" />
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
          <p>Administrador de projetos</p>
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
            <button className="active" type="button"><UserRound size={17} /> Informações pessoais</button>
            <button type="button"><ShieldCheck size={17} /> Segurança</button>
            <button type="button"><Bell size={17} /> Notificações</button>
          </nav>
          <form className="profile-form" onSubmit={saveProfile}>
            <div className="form-section-heading">
              <div><h2>Informações pessoais</h2><p>Essas informações serão exibidas no seu perfil e nos projetos.</p></div>
              <span>Conta ativa</span>
            </div>
            <div className="profile-form-grid">
              <label><span>Nome</span><input key={`first-${user?.name}`} name="first_name" defaultValue={firstName} /></label>
              <label><span>Sobrenome</span><input key={`last-${user?.name}`} name="last_name" defaultValue={lastName} /></label>
              <label className="full-field"><span>E-mail</span><div className="verified-input"><input type="email" value={user?.email || ""} readOnly /><CheckCircle2 size={18} /></div><small>E-mail verificado</small></label>
              <label><span>Cargo</span><input defaultValue="Administrador de projetos" /></label>
              <label><span>Telefone</span><input placeholder="+55 (11) 99999-9999" /></label>
              <label><span>Departamento</span><select defaultValue="Projetos"><option>Projetos</option><option>Tecnologia</option><option>Comercial</option></select></label>
              <label><span>Fuso horário</span><select defaultValue="São Paulo"><option>São Paulo</option><option>Brasília</option><option>Lisboa</option></select></label>
              <label className="full-field"><span>Sobre mim</span><textarea defaultValue="Gerencio equipes e projetos de transformação digital na Computécnica." rows="4" /></label>
            </div>
            <div className="profile-form-actions">
              {saved && <span><CheckCircle2 size={17} /> Alterações salvas</span>}
              <button className="secondary-button" type="button">Cancelar</button>
              <button className="primary-action" type="submit">Salvar alterações</button>
            </div>
          </form>
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

function Dashboard({ onLogout, user, onUserUpdate }) {
  const [menuOpen, setMenuOpen] = useState(false);
  const [activePage, setActivePage] = useState("home");
  const [profileOpen, setProfileOpen] = useState(false);
  const [inboxPlacement, setInboxPlacement] = useState(null);
  const inboxTimer = useRef(null);
  const pageMeta = {
    home: [HomeIcon, "Home"],
    projects: [FolderKanban, "Projetos"],
    tasks: [ListChecks, "Tarefas"],
    profile: [UserRound, "Meu perfil"],
  };
  const [PageIcon, pageLabel] = pageMeta[activePage];

  function showInbox(placement) {
    window.clearTimeout(inboxTimer.current);
    setInboxPlacement(placement);
  }

  function scheduleInboxClose() {
    window.clearTimeout(inboxTimer.current);
    inboxTimer.current = window.setTimeout(() => setInboxPlacement(null), 55);
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
            <button className="add-button" type="button" aria-label="Adicionar"><Plus size={22} /></button>
            <label className="search-box">
              <Search size={27} />
              <input aria-label="Pesquisar" />
            </label>
          </div>

          <div className="account-tools">
            <button type="button" aria-label="Configurações"><Gauge size={31} /></button>
            <button
              type="button"
              aria-label="Notificações"
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
            onOpenInbox={() => setInboxPlacement(null)}
          />
        )}

        <div className={`actionbar ${activePage !== "home" ? "workspace-actionbar" : ""}`}>
          <span>Última atualização ontem às 23:45</span>
          <div>
            <button type="button"><CheckCircle2 size={20} fill="#242424" /> tarefas incompletas</button>
            <button type="button"><Filter size={21} /> filtrar</button>
            <button type="button"><ArrowDownUp size={21} /> organizar</button>
            <div className="apps-hover-wrap">
              <button type="button"><AppWindow size={22} /> apps</button>
              <AppsPopover />
            </div>
          </div>
        </div>

        {activePage === "home" && <div className="dashboard-main">
          <h1>Painel</h1>
          <div className="dashboard-grid">
            <section className="board-column status-column">
              <div className="column-title">
                <span><SlidersHorizontal size={26} /> status</span>
                <div><Filter size={20} /><ArrowDownUp size={20} /></div>
              </div>
              <div className="column-surface">
                {projects.map(([name, status, type]) => (
                  <article className="project-row" key={name}>
                    <ClipboardList size={31} strokeWidth={1.4} />
                    <span>{name}</span>
                    <strong className={type}>{status}</strong>
                  </article>
                ))}
              </div>
            </section>

            <section className="board-column tasks-column">
              <div className="column-title">
                <span><ListChecks size={26} /> tarefas</span>
                <div><Files size={22} /><Plus size={23} /></div>
              </div>
              <div className="task-list">
                {tasks.map(([name, date, color]) => (
                  <article className="task-card" key={name}>
                    <div className="task-name"><Check size={17} /><span>{name}</span></div>
                    <div className="task-meta">
                      <span><CircleUserRound size={31} fill="#c9c9c9" stroke="#fff" />{date}</span>
                      <span><Paperclip className={color} size={23} fill="currentColor" /><MoreHorizontal size={24} /></span>
                    </div>
                  </article>
                ))}
              </div>
            </section>

            <section className="board-column teams-column">
              <div className="column-title">
                <span><Users size={29} /> Times ativos</span>
              </div>
              <div className="column-surface team-surface">
                {teams.map(([name, count]) => (
                  <article className="team-row" key={name}>
                    <Users size={32} fill="#000" />
                    <AvatarStack count={count} />
                    <span>{name}</span>
                  </article>
                ))}
              </div>
            </section>
          </div>
        </div>}
        {activePage === "projects" && <ProjectsPage />}
        {activePage === "tasks" && <TasksPage />}
        {activePage === "profile" && <ProfilePage user={user} onUserUpdate={onUserUpdate} />}
      </section>
    </main>
  );
}

function LegacySpotApp() {
  if (window.location.pathname.startsWith("/estoque")) return <InventoryApp />;
  const [showPassword, setShowPassword] = useState(false);
  const [user, setUser] = useState(() => window.__SPOT__?.user || null);
  const [authenticated, setAuthenticated] = useState(
    () => Boolean(window.__SPOT__?.authenticated || new URLSearchParams(window.location.search).has("home")),
  );
  const [loginError, setLoginError] = useState("");

  async function handleSubmit(event) {
    event.preventDefault();
    setLoginError("");
    const form = new FormData(event.currentTarget);
    try {
      const response = await fetch("/api/auth/login", {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        body: JSON.stringify({ email: form.get("email"), password: form.get("password") }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || "Não foi possível entrar.");
      localStorage.setItem("spot_api_token", data.access_token);
      setUser(data.user);
      setAuthenticated(true);
    } catch (error) {
      setLoginError(error.message);
    }
  }

  async function handleLogout() {
    const token = localStorage.getItem("spot_api_token");
    if (token) {
      await fetch("/api/auth/logout", { method: "POST", headers: { Authorization: `Bearer ${token}`, Accept: "application/json" } });
      localStorage.removeItem("spot_api_token");
    }
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
