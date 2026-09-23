import React, { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ArrowLeft, ArrowRight, BookOpen, CheckCircle2, List, Pause, Play, RotateCcw, X } from 'lucide-react';
import { chapters, getLessons, localize } from './content';
import '../../css/tutorial.css';

const words = {
  help: ['Tutorial do Spot', 'Spot tutorial', 'Tutorial de Spot'],
  welcome: ['Bem-vindo ao Spot', 'Welcome to Spot', 'Bienvenido a Spot'],
  reminder: ['O tutorial está no botão ?', 'The tutorial is in the ? button', 'El tutorial está en el botón ?'],
  recommended: ['Opcional, mas recomendado', 'Optional, but recommended', 'Opcional, pero recomendado'],
  introduction: ['Conheça as principais funções em um passeio guiado, no seu ritmo e de acordo com as permissões da sua conta.', 'Explore the main features with a guided tour, at your pace and according to your account permissions.', 'Conozca las principales funciones con una visita guiada, a su ritmo y según los permisos de su cuenta.'],
  reminderBody: ['Sempre que precisar, clique no símbolo ? no topo da tela para abrir o tutorial. Você pode consultar um assunto ou continuar de onde parou.', 'Whenever you need help, click the ? symbol at the top to open the tutorial. Explore a topic or continue where you left off.', 'Cuando necesite ayuda, haga clic en el símbolo ? de la parte superior para abrir el tutorial. Consulte un tema o continúe donde lo dejó.'],
  optional: ['Você pode deixar para depois e usar o Spot normalmente.', 'You can leave it for later and use Spot normally.', 'Puede dejarlo para después y usar Spot normalmente.'],
  start: ['Iniciar tutorial', 'Start tutorial', 'Iniciar tutorial'],
  resume: ['Continuar tutorial', 'Resume tutorial', 'Continuar tutorial'],
  later: ['Agora não', 'Not now', 'Ahora no'],
  understood: ['Entendi', 'Got it', 'Entendido'],
  restart: ['Começar do início', 'Start from the beginning', 'Empezar desde el principio'],
  restartQuestion: ['Quer começar o tutorial do início?', 'Start the tutorial from the beginning?', '¿Quiere empezar el tutorial desde el principio?'],
  restartBody: ['Seu progresso atual será substituído pela primeira etapa.', 'Your current progress will be replaced by the first step.', 'Su progreso actual será sustituido por el primer paso.'],
  confirmRestart: ['Sim, começar do início', 'Yes, start from the beginning', 'Sí, empezar desde el principio'],
  keepGoing: ['Não, continuar de onde parei', 'No, resume where I left off', 'No, continuar donde lo dejé'],
  cancelRestart: ['Não, cancelar', 'No, cancel', 'No, cancelar'],
  close: ['Fechar tutorial', 'Close tutorial', 'Cerrar tutorial'],
  contents: ['Escolher um assunto', 'Choose a topic', 'Elegir un tema'],
  next: ['Próximo', 'Next', 'Siguiente'],
  back: ['Voltar', 'Back', 'Volver'],
  pause: ['Pausar tutorial', 'Pause tutorial', 'Pausar tutorial'],
  finish: ['Concluir tutorial', 'Finish tutorial', 'Finalizar tutorial'],
  progress: ['Etapa', 'Step', 'Paso'],
  of: ['de', 'of', 'de'],
  tip: ['Para lembrar', 'Remember', 'Para recordar'],
  completion: ['Você concluiu o tutorial!', 'You completed the tutorial!', '¡Completó el tutorial!'],
  completionBody: ['Agora você conhece os principais caminhos do Spot. O botão ? continua disponível para revisar qualquer assunto quando precisar.', 'You now know your way around Spot. The ? button remains available to revisit any topic whenever you need it.', 'Ahora conoce las principales áreas de Spot. El botón ? sigue disponible para repasar cualquier tema cuando lo necesite.'],
  done: ['Explorar o Spot', 'Explore Spot', 'Explorar Spot'],
  saved: ['Seu progresso fica salvo para esta conta neste navegador.', 'Your progress is saved for this account in this browser.', 'Su progreso se guarda para esta cuenta en este navegador.'],
  storageUnavailable: ['O navegador não permitiu salvar o progresso. Você ainda pode usar todo o tutorial.', 'Your browser did not allow progress to be saved. You can still use the entire tutorial.', 'El navegador no permitió guardar el progreso. Aun así, puede utilizar todo el tutorial.'],
  navigationWarning: ['O passeio muda de página. Salve alterações pendentes antes de começar. Nenhum formulário será enviado pelo tutorial.', 'The tour changes pages. Save any pending changes before starting. The tutorial will not submit any forms.', 'El recorrido cambia de página. Guarde los cambios pendientes antes de empezar. El tutorial no enviará ningún formulario.'],
  missing: ['O destaque ainda não está disponível nesta tela. Você pode continuar lendo e avançar normalmente.', 'The highlight is not available on this screen yet. You can continue reading and move on normally.', 'El elemento destacado aún no está disponible en esta pantalla. Puede seguir leyendo y avanzar normalmente.'],
  language: ['Idioma do tutorial', 'Tutorial language', 'Idioma del tutorial'],
  duration: ['Cerca de 8 minutos • pause quando quiser', 'About 8 minutes • pause anytime', 'Unos 8 minutos • pause cuando quiera'],
  keyboard: ['Tab para navegar • Esc para pausar', 'Tab to navigate • Esc to pause', 'Tab para navegar • Esc para pausar'],
};
const emptyProgress = { status: 'new', stepId: null };
export function progressKey(user) {
  return `spot.tutorial.v1:${encodeURIComponent(user?.email?.toLowerCase() || String(user?.id || 'guest'))}`;
}
function readProgress(key) {
  try {
    const saved = JSON.parse(window.localStorage.getItem(key));
    return saved && ['new', 'skipped', 'in_progress', 'completed'].includes(saved.status) ? saved : emptyProgress;
  } catch { return emptyProgress; }
}

export default function SpotTutorial({ user, locale, onNavigate, onExit, onLanguageChange }) {
  const key = progressKey(user);
  const steps = useMemo(() => getLessons(user), [user?.can_create_projects, user?.can_manage_identity, user?.can_view_financial, user?.can_view_parameters]);
  const [progress, setProgress] = useState(() => readProgress(key));
  const [mode, setMode] = useState('notice');
  const [index, setIndex] = useState(0);
  const [rect, setRect] = useState(null);
  const [locating, setLocating] = useState(false);
  const [storageError, setStorageError] = useState(false);
  const [cardSize, setCardSize] = useState({ width: 440, height: 470 });
  const [viewport, setViewport] = useState({ width: window.innerWidth, height: window.innerHeight });
  const dialog = useRef(null);
  const helpButton = useRef(null);
  const focusBeforeOpen = useRef(null);
  const step = steps[Math.min(index, steps.length - 1)];
  const open = mode !== 'closed';
  const running = mode === 'tour';
  const firstVisit = progress.status === 'new';
  const canResume = progress.status === 'in_progress' && steps.some(item => item.id === progress.stepId);
  const w = name => words[name][{ pt: 0, en: 1, es: 2 }[locale] ?? 0];
  const t = value => localize(value, locale);

  function persist(status, stepId = progress.stepId) {
    const next = { status, stepId, updatedAt: new Date().toISOString() };
    setProgress(next);
    try { window.localStorage.setItem(key, JSON.stringify(next)); }
    catch { setStorageError(true); }
  }
  function close() {
    if (running) persist('in_progress', step.id);
    else if (firstVisit) persist('skipped');
    if (running) onExit();
    setMode('closed');
  }
  function goTo(nextIndex) {
    const safeIndex = Math.max(0, Math.min(nextIndex, steps.length - 1));
    setRect(null); setLocating(true); setIndex(safeIndex);
    persist('in_progress', steps[safeIndex].id);
    setMode('tour'); onNavigate(steps[safeIndex]);
  }
  function start() {
    goTo(canResume ? steps.findIndex(item => item.id === progress.stepId) : 0);
  }
  function openHelp() {
    if (canResume) start();
    else setMode('menu');
  }
  function askToRestart() {
    setMode('restart');
  }
  function cancelRestart() {
    if (progress.status === 'completed') setMode('complete');
    else start();
  }
  function next() {
    if (index < steps.length - 1) goTo(index + 1);
    else { persist('completed', step.id); onExit(); setMode('complete'); }
  }

  // Keep the page visible, but prevent forms and links from receiving accidental
  // focus or clicks while the modal tour explains them. The portal stays outside it.
  useEffect(() => {
    if (!open) return;
    focusBeforeOpen.current = document.activeElement;
    const shell = document.querySelector('.dashboard-shell');
    const wasInert = shell?.inert;
    const previousOverflow = document.body.style.overflow;
    if (shell) shell.inert = true;
    document.body.style.overflow = 'hidden';
    return () => {
      if (shell) shell.inert = wasInert;
      document.body.style.overflow = previousOverflow;
      const previous = focusBeforeOpen.current;
      (previous?.isConnected && previous !== document.body ? previous : helpButton.current)?.focus({ preventScroll: true });
    };
  }, [open]);

  useLayoutEffect(() => {
    if (!open) return;
    dialog.current?.querySelector('[data-tutorial-heading]')?.focus({ preventScroll: true });
    const resize = new ResizeObserver(() => {
      const bounds = dialog.current?.getBoundingClientRect();
      if (bounds) setCardSize(previous => previous.width === bounds.width && previous.height === bounds.height ? previous : { width: bounds.width, height: bounds.height });
    });
    if (dialog.current) resize.observe(dialog.current);
    return () => resize.disconnect();
  }, [mode, index, locale]);

  useEffect(() => {
    if (!running) return;
    let scrolledElement = null;
    let frame;
    let finishedWaiting = false;
    const measure = () => {
      const element = document.querySelector(step.target);
      const bounds = element?.getBoundingClientRect();
      if (element && bounds?.width && bounds?.height) {
        if (scrolledElement !== element) {
          scrolledElement = element;
          element.scrollIntoView({ block: window.innerWidth < 760 ? 'start' : 'center', inline: 'nearest', behavior: 'instant' });
          if (window.innerWidth < 760) window.scrollBy(0, -20);
        }
        const box = element.getBoundingClientRect();
        const newRect = { top: Math.max(8, box.top - 6), left: Math.max(8, box.left - 6), right: Math.min(window.innerWidth - 8, box.right + 6), bottom: Math.min(window.innerHeight - 8, box.bottom + 6) };
        setRect(old => JSON.stringify(old) === JSON.stringify(newRect) ? old : newRect);
        setLocating(false);
      } else {
        setRect(null);
        if (finishedWaiting) setLocating(false);
      }
      setViewport(old => old.width === window.innerWidth && old.height === window.innerHeight ? old : { width: window.innerWidth, height: window.innerHeight });
    };
    const schedule = () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(measure); };
    const observer = new MutationObserver(schedule);
    const shell = document.querySelector('.dashboard-shell');
    if (shell) observer.observe(shell, { childList: true, subtree: true });
    window.addEventListener('resize', schedule);
    window.addEventListener('scroll', schedule, true);
    const timeout = window.setTimeout(() => { finishedWaiting = true; schedule(); }, 1800);
    schedule();
    return () => { observer.disconnect(); clearTimeout(timeout); cancelAnimationFrame(frame); window.removeEventListener('resize', schedule); window.removeEventListener('scroll', schedule, true); };
  }, [running, step.id, step.target]);

  function onKeyDown(event) {
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); close(); }
    if (event.key !== 'Tab') return;
    const focusable = [...dialog.current.querySelectorAll('button:not(:disabled),select,a[href],summary')].filter(element => element.getClientRects().length);
    const first = focusable[0], last = focusable.at(-1);
    if (event.shiftKey && (document.activeElement === first || !focusable.includes(document.activeElement))) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && (document.activeElement === last || !focusable.includes(document.activeElement))) { event.preventDefault(); first?.focus(); }
  }

  let position = {};
  if (running && rect && viewport.width >= 760) {
    const gap = 18;
    const width = cardSize.width, height = cardSize.height;
    let left = viewport.width - width - gap;
    let top = viewport.height - height - gap;
    if (rect.right + width + gap * 2 <= viewport.width) { left = rect.right + gap; top = rect.top; }
    else if (rect.left >= width + gap * 2) { left = rect.left - width - gap; top = rect.top; }
    else if (rect.bottom + height + gap * 2 <= viewport.height) { left = rect.left; top = rect.bottom + gap; }
    else if (rect.top >= height + gap * 2) { left = rect.left; top = rect.top - height - gap; }
    position = { left: Math.max(gap, Math.min(left, viewport.width - width - gap)), top: Math.max(gap, Math.min(top, viewport.height - height - gap)), transform: 'none' };
  }

  const indexList = <details className="tutorial-index">
    <summary><List size={16} />{w('contents')}</summary>
    <div className="tutorial-index-list">
      {Object.entries(chapters).map(([chapter, name]) => {
        const entries = steps.map((item, itemIndex) => ({ ...item, itemIndex })).filter(item => item.chapter === chapter);
        return entries.length ? <section key={chapter}><h3>{t(name)}</h3>{entries.map(item => <button type="button" key={item.id} aria-current={running && step.id === item.id ? 'step' : undefined} onClick={() => goTo(item.itemIndex)}><span>{String(item.itemIndex + 1).padStart(2, '0')}</span>{t(item.title)}</button>)}</section> : null;
      })}
    </div>
  </details>;

  return <>
    <button ref={helpButton} type="button" className="tutorial-help-button" data-tour="help" aria-label={w('help')} title={w('help')} aria-haspopup="dialog" onClick={openHelp} translate="no">?</button>
    {open && createPortal(<div className={`tutorial-screen ${running ? 'is-running' : ''} ${rect && running ? 'has-highlight' : ''}`} onKeyDown={onKeyDown} translate="no">
      <div className="tutorial-backdrop" aria-hidden="true" />
      {running && rect && <div className="tutorial-highlight" aria-hidden="true" style={{ top: rect.top, left: rect.left, width: Math.max(0, rect.right - rect.left), height: Math.max(0, rect.bottom - rect.top) }} />}
      <section ref={dialog} role="dialog" aria-modal="true" aria-labelledby="spot-tutorial-title" aria-describedby="spot-tutorial-description" className={`tutorial-card ${running ? 'tutorial-step-card' : ''}`} style={position} data-tutorial-step={running ? step.id : mode}>
        <header className="tutorial-card-header">
          <span><BookOpen size={17} />{w('help')}</span>
          <div><select aria-label={w('language')} value={locale} onChange={event => onLanguageChange(event.target.value)}><option value="pt">Português</option><option value="en">English</option><option value="es">Español</option></select><button type="button" className="tutorial-icon-button" onClick={close} aria-label={w('close')}><X size={20} /></button></div>
        </header>
        <div className="tutorial-card-body">
          {running ? <>
            <div className="tutorial-step-meta"><span>{t(chapters[step.chapter])}</span><span>{w('progress')} {index + 1} {w('of')} {steps.length}</span></div>
            <progress className="tutorial-progress" value={index + 1} max={steps.length} aria-label={w('progress')} />
            <h2 id="spot-tutorial-title" data-tutorial-heading tabIndex={-1}>{t(step.title)}</h2>
            <p id="spot-tutorial-description">{t(step.body)}</p>
            <aside className="tutorial-tip"><strong>{w('tip')}</strong><p>{t(step.tip)}</p></aside>
            {!rect && !locating && <p className="tutorial-muted" role="status">{w('missing')}</p>}
            {indexList}
          </> : mode === 'complete' ? <>
            <div className="tutorial-emblem"><CheckCircle2 size={40} /></div>
            <h2 id="spot-tutorial-title" data-tutorial-heading tabIndex={-1}>{w('completion')}</h2>
            <p id="spot-tutorial-description">{w('completionBody')}</p>
            {indexList}
          </> : mode === 'restart' ? <>
            <div className="tutorial-welcome"><span className="tutorial-emblem"><RotateCcw size={34} /></span></div>
            <h2 id="spot-tutorial-title" data-tutorial-heading tabIndex={-1}>{w('restartQuestion')}</h2>
            <p id="spot-tutorial-description">{w('restartBody')}</p>
          </> : <>
            <div className="tutorial-welcome"><span className="tutorial-emblem">?</span><span className="tutorial-recommended">{w('recommended')}</span></div>
            <h2 id="spot-tutorial-title" data-tutorial-heading tabIndex={-1}>{mode === 'notice' ? w(firstVisit ? 'welcome' : 'reminder') : w('help')}</h2>
            <p id="spot-tutorial-description">{w(firstVisit ? 'introduction' : 'reminderBody')}</p>
            {firstVisit && <p>{w('reminderBody')}</p>}
            <p className="tutorial-muted">{w('optional')}</p>
            <p className="tutorial-duration">{steps.length} {w('progress').toLowerCase()}s · {w('duration')}</p>
            <aside className="tutorial-tip"><p>{w('navigationWarning')}</p></aside>
            {indexList}
          </>}
          <p className="tutorial-storage" role={storageError ? 'status' : undefined}>{w(storageError ? 'storageUnavailable' : 'saved')}</p>
        </div>
        <footer className="tutorial-card-footer">
          {running ? <>
            <div><button type="button" className="tutorial-link-button" onClick={close}><Pause size={15} />{w('pause')}</button><button type="button" className="tutorial-link-button" onClick={askToRestart}><RotateCcw size={15} />{w('restart')}</button></div>
            <div><button type="button" className="tutorial-secondary" onClick={() => goTo(index - 1)} disabled={index === 0}><ArrowLeft size={16} />{w('back')}</button><button type="button" className="tutorial-primary" onClick={next}>{w(index === steps.length - 1 ? 'finish' : 'next')}<ArrowRight size={16} /></button></div>
          </> : mode === 'complete' ? <>
            <button type="button" className="tutorial-link-button" onClick={askToRestart}><RotateCcw size={15} />{w('restart')}</button><button type="button" className="tutorial-primary" onClick={close}>{w('done')}</button>
          </> : mode === 'restart' ? <>
            <button type="button" className="tutorial-secondary" onClick={cancelRestart}>{w(progress.status === 'completed' ? 'cancelRestart' : 'keepGoing')}</button>
            <button type="button" className="tutorial-primary" onClick={() => goTo(0)}><RotateCcw size={15} />{w('confirmRestart')}</button>
          </> : <>
            <button type="button" className="tutorial-secondary" onClick={close}>{w(firstVisit ? 'later' : 'understood')}</button>
            <button type="button" className="tutorial-primary" onClick={start}><Play size={16} />{w(canResume ? 'resume' : 'start')}</button>
            {canResume && <button type="button" className="tutorial-link-button" onClick={askToRestart}><RotateCcw size={15} />{w('restart')}</button>}
          </>}
        </footer>
        {running && <p className="tutorial-keyboard">{w('keyboard')}</p>}
      </section>
    </div>, document.body)}
  </>;
}
