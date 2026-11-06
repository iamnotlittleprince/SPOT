import React, { useState } from 'react';
import { ErrorScreen } from './ErrorScreen';
import messages from '../errors/messages.json';

// Local review page: uses the actual shared screen without triggering failures.
export default function ErrorPreview() {
  const initial = new URLSearchParams(window.location.search).get('code');
  const [code, setCode] = useState(Object.hasOwn(messages, initial) ? initial : '429');
  const [theme, setTheme] = useState(document.documentElement.dataset.theme || 'light');
  function selectCode(value) {
    setCode(value);
    window.history.replaceState(null, '', `?code=${encodeURIComponent(value)}`);
  }
  return <div className="spot-error-preview">
    <nav className="spot-error-preview-toolbar" aria-label="Prévia das telas de erro">
      <label>Tela de erro ({Object.keys(messages).length})
        <select value={code} onChange={event => selectCode(event.target.value)}>
          {Object.entries(messages).map(([value, [title]]) => <option key={value} value={value}>{value} · {title}</option>)}
        </select>
      </label>
      <label>Tema
        <select value={theme} onChange={event => {
          setTheme(event.target.value);
          document.documentElement.dataset.theme = event.target.value;
        }}><option value="light">Claro</option><option value="dark">Escuro</option></select>
      </label>
      <a href="/">Voltar ao Spot</a>
    </nav>
    <ErrorScreen key={code} code={code} />
  </div>;
}
