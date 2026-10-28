import React, { createContext, useContext, useEffect, useState } from 'react';
const ReadingContext = createContext(null);

const key = 'spot.reading-settings';
const fonts = { default: '', arial: 'Arial, Helvetica, sans-serif', verdana: 'Verdana, Geneva, sans-serif', georgia: 'Georgia, serif' };
function saved() {
  try {
    const value = JSON.parse(localStorage.getItem(key));
    return { scale: [90, 100, 110, 120].includes(value?.scale) ? value.scale : 100, font: Object.hasOwn(fonts, value?.font) ? value.font : 'default' };
  } catch { return { scale: 100, font: 'default' }; }
}
export function ReadingSettingsProvider({ children }) {
  const [settings, setSettings] = useState(saved);
  useEffect(() => {
    document.documentElement.style.setProperty('--spot-reading-zoom', settings.scale / 100);
    document.documentElement.dataset.readingFont = settings.font;
    document.documentElement.style.setProperty('--spot-reading-font', fonts[settings.font]);
    try { localStorage.setItem(key, JSON.stringify(settings)); } catch { /* Settings still work without storage. */ }
  }, [settings]);
  return <ReadingContext.Provider value={{ settings, setSettings }}>{children}</ReadingContext.Provider>;
}
export default function ReadingSettings({ onReset }) {
  const { settings, setSettings } = useContext(ReadingContext);
  return <div className="reading-controls">
      <p id="reading-size-label">Tamanho do texto e da interface</p>
      <div className="reading-size" role="group" aria-labelledby="reading-size-label"><button type="button" aria-label="Diminuir texto" disabled={settings.scale === 90} onClick={() => setSettings(value => ({ ...value, scale: value.scale - 10 }))}>A−</button><output aria-live="polite" aria-atomic="true">{settings.scale}%</output><button type="button" aria-label="Aumentar texto" disabled={settings.scale === 120} onClick={() => setSettings(value => ({ ...value, scale: value.scale + 10 }))}>A+</button></div>
      <label htmlFor="reading-font"><span>Fonte do site</span><select id="reading-font" value={settings.font} onChange={event => setSettings(value => ({ ...value, font: event.target.value }))}><option value="default">Padrão do site</option><option value="arial">Arial</option><option value="verdana">Verdana</option><option value="georgia">Georgia</option></select></label>
      <button className="reading-reset" type="button" onClick={() => { setSettings({ scale: 100, font: 'default' }); onReset?.(); }}>Restaurar padrão</button>
  </div>;
}
