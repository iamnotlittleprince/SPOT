import React, { useEffect, useState } from 'react';
import messages from '../errors/messages.json';
import useModalFocus from './useModalFocus';
import { message as translateMessage } from './i18n';
import '../../public/spot-errors.css';
function message(source) {
  try { return translateMessage(source); } catch { return source; }
}

export function ErrorScreen({ code = 'application', onDismiss }) {
  const [title, description] = messages[code] || messages[Number(code) >= 500 ? '5xx' : '4xx'];
  const ref = useModalFocus(Boolean(onDismiss), onDismiss || (() => {}));
  useEffect(() => {
    const previous = document.title;
    document.title = `Spot · ${message(title)}`;
    return () => { document.title = previous; };
  }, [title]);
  return <main ref={ref} tabIndex={-1} className={`spot-error-shell${onDismiss ? ' spot-error-overlay' : ''}`} role={onDismiss ? 'alertdialog' : undefined} aria-modal={onDismiss ? true : undefined} aria-labelledby="spot-error-title" aria-describedby="spot-error-description">
    <section className="spot-error-card"><div>
      <img className="spot-error-brand" src="/spot-logo.png" alt="Spot" />
      <span className="spot-error-code">{Number.isInteger(Number(code)) ? `${message('Erro')} ${code}` : message('Precisamos de sua atenção')}</span>
      <h1 id="spot-error-title">{message(title)}</h1><p id="spot-error-description">{message(description)}</p>
      <div className="spot-error-actions">
        {onDismiss && <button type="button" onClick={onDismiss}>{message('Voltar para a tela')}</button>}
        <a className={onDismiss ? 'spot-error-secondary' : ''} href="/">{[401, 419].includes(Number(code)) ? message('Entrar novamente') : message('Ir para o início')}</a>
        {code === 'application' && <button className="spot-error-secondary" type="button" onClick={() => window.location.reload()}>{message('Recarregar página')}</button>}
      </div>
      <p className="spot-error-footnote">{message('Se uma ação foi interrompida, confira o resultado antes de enviá-la novamente.')}</p>
    </div><div className="spot-error-art" aria-hidden="true"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" strokeWidth="5" strokeLinecap="round" strokeLinejoin="round"><rect x="18" y="26" width="84" height="64" rx="12"/><path d="M43 105h34M60 90v15M60 43v18"/><circle cx="60" cy="74" r="3" fill="currentColor" stroke="none"/><path d="m12 12 7 7m89-7-7 7"/></svg></div></section>
  </main>;
}

export class SpotErrorBoundary extends React.Component {
  state = { failed: false };
  static getDerivedStateFromError() { return { failed: true }; }
  componentDidCatch(error, info) { console.error('Spot: falha ao renderizar a tela.', error, info); }
  render() { return this.state.failed ? <ErrorScreen /> : this.props.children; }
}

export function RequestErrorHost() {
  const [error, setError] = useState(null);
  useEffect(() => {
    const seen = new Set();
    const show = event => {
      const { code } = event.detail;
      if (seen.has(code)) return;
      seen.add(code);
      setError(current => current || code);
    };
    const reset = () => { seen.clear(); };
    const unexpected = event => {
      if (event.reason?.name === 'AbortError') return;
      show({detail:{code:'application'}});
    };
    window.addEventListener('spot:request-error', show);
    window.addEventListener('popstate', reset);
    window.addEventListener('spot:navigated', reset);
    window.addEventListener('error', unexpected);
    window.addEventListener('unhandledrejection', unexpected);
    return () => {
      window.removeEventListener('spot:request-error', show);
      window.removeEventListener('popstate', reset);
      window.removeEventListener('spot:navigated', reset);
      window.removeEventListener('error', unexpected);
      window.removeEventListener('unhandledrejection', unexpected);
    };
  }, []);
  return error ? <ErrorScreen code={error} onDismiss={() => setError(null)} /> : null;
}
