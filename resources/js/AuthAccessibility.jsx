import React, {useEffect,useState} from 'react';
import {createPortal} from 'react-dom';
import {Accessibility,X} from 'lucide-react';
import ReadingSettings from './ReadingSettings';
import LanguageSwitcher from './LanguageSwitcher';
import {getLanguage,setLanguage} from './i18n';
import useModalFocus from './useModalFocus';

export default function AuthAccessibility() {
  const [open,setOpen]=useState(false);
  const [locale,setLocale]=useState(getLanguage);
  const [theme,setTheme]=useState(()=>{try{const value=localStorage.getItem('spot.theme');return ['light','dark'].includes(value)?value:'system';}catch{return 'system';}});
  const ref=useModalFocus(open,()=>setOpen(false));
  useEffect(()=>{
    const update=()=>setLocale(getLanguage());
    window.addEventListener('spot:language',update);
    return ()=>window.removeEventListener('spot:language',update);
  },[]);
  useEffect(()=>{
    const media=matchMedia('(prefers-color-scheme: dark)');
    const apply=()=>{document.documentElement.dataset.theme=theme==='system'?(media.matches?'dark':'light'):theme;};
    apply();media.addEventListener('change',apply);
    try{localStorage.setItem('spot.theme',theme);}catch{}
    return ()=>media.removeEventListener('change',apply);
  },[theme]);
  return <div className="auth-accessibility-bar">
    <div className="auth-language-control"><span>Idioma</span><LanguageSwitcher locale={locale} onChange={setLanguage}/></div>
    <button type="button" aria-haspopup="dialog" aria-expanded={open} onClick={()=>setOpen(true)}><Accessibility size={20} aria-hidden="true"/><span>Acessibilidade</span></button>
    {open && createPortal(<div className="public-auth auth-accessibility-portal"><div className="auth-accessibility-overlay" onMouseDown={event=>event.target===event.currentTarget&&setOpen(false)}>
      <section ref={ref} className="auth-accessibility-dialog" role="dialog" aria-modal="true" aria-labelledby="auth-accessibility-title" tabIndex={-1}>
        <header><h2 id="auth-accessibility-title">Acessibilidade e aparência</h2><button type="button" aria-label="Fechar ajustes" onClick={()=>setOpen(false)}><X size={20} aria-hidden="true"/></button></header>
        <p>Personalize a leitura antes de entrar. Suas escolhas ficam salvas neste navegador.</p>
        <label className="auth-theme-control"><span id="auth-theme-label">Tema</span><select aria-labelledby="auth-theme-label" value={theme} onChange={event=>setTheme(event.target.value)}><option value="system">Usar configuração do sistema</option><option value="light">Claro</option><option value="dark">Escuro</option></select></label>
        <ReadingSettings extended onReset={()=>setTheme('system')}/>
        <p className="auth-accessibility-hint">Para ampliar mais, use o zoom do navegador. Os ajustes de movimento e cores do sistema também são respeitados.</p>
      </section>
    </div></div>, document.getElementById('root'))}
  </div>;
}
