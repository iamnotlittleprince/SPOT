import React, { createContext, useContext, useEffect, useState } from 'react';
import { readingFonts } from './readingFonts';
import '../css/reading-fonts.css';
const ReadingContext = createContext(null);

const key = 'spot.reading-settings';
const defaults = {scale:100,font:'default',contrast:false,reducedMotion:false,underlineLinks:false};
function saved() {
  try {
    const value = JSON.parse(localStorage.getItem(key));
    return { scale: [90, 100, 110, 120].includes(value?.scale) ? value.scale : 100, font: Object.hasOwn(readingFonts, value?.font) ? value.font : 'default', contrast: value?.contrast === true, reducedMotion: value?.reducedMotion === true, underlineLinks: value?.underlineLinks === true };
  } catch { return {...defaults}; }
}
export function ReadingSettingsProvider({ children }) {
  const [settings, setSettings] = useState(saved);
  useEffect(() => {
    document.documentElement.style.setProperty('--spot-reading-zoom', settings.scale / 100);
    document.documentElement.dataset.readingFont = settings.font;
    document.documentElement.style.setProperty('--spot-reading-font', readingFonts[settings.font].family);
    document.documentElement.dataset.spotContrast = settings.contrast ? 'high' : 'default';
    document.documentElement.dataset.spotMotion = settings.reducedMotion ? 'reduce' : 'system';
    document.documentElement.dataset.spotLinks = settings.underlineLinks ? 'underlined' : 'default';
    try { localStorage.setItem(key, JSON.stringify(settings)); } catch { /* Settings still work without storage. */ }
  }, [settings]);
  return <ReadingContext.Provider value={{ settings, setSettings }}>{children}</ReadingContext.Provider>;
}
export default function ReadingSettings({ onReset, extended = false }) {
  const { settings, setSettings } = useContext(ReadingContext);
  return <div className="reading-controls">
      <p id="reading-size-label">Tamanho do texto e da interface</p>
      <div className="reading-size" role="group" aria-labelledby="reading-size-label"><button type="button" aria-label="Diminuir texto" disabled={settings.scale === 90} onClick={() => setSettings(value => ({ ...value, scale: value.scale - 10 }))}>A−</button><output aria-live="polite" aria-atomic="true">{settings.scale}%</output><button type="button" aria-label="Aumentar texto" disabled={settings.scale === 120} onClick={() => setSettings(value => ({ ...value, scale: value.scale + 10 }))}>A+</button></div>
      <label htmlFor="reading-font"><span>Fonte do site</span><select id="reading-font" value={settings.font} onChange={event => setSettings(value => ({ ...value, font: event.target.value }))}>{Object.entries(readingFonts).map(([value, font]) => <option key={value} value={value} translate={value === 'default' ? undefined : 'no'}>{font.label}</option>)}</select></label>
      {extended && <div className="reading-preferences">{[['contrast','Alto contraste'],['reducedMotion','Reduzir movimento'],['underlineLinks','Sublinhar links']].map(([key,label]) => <label key={key}><input type="checkbox" checked={settings[key]} onChange={event => setSettings(current => ({...current,[key]:event.target.checked}))}/><span>{label}</span></label>)}</div>}
      <button className="reading-reset" type="button" onClick={() => { setSettings({...defaults}); onReset?.(); }}>Restaurar padrão</button>
  </div>;
}
