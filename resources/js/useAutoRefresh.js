import { useEffect, useRef } from 'react';

// Mantém os filtros atuais e evita consultas em abas ocultas ou sobrepostas.
export default function useAutoRefresh(callback, interval = 30000) {
  const latest = useRef(callback);
  latest.current = callback;
  useEffect(() => {
    let running = false;
    let active = true;
    const refresh = async () => {
      if (!active || running || document.visibilityState === 'hidden') return;
      running = true;
      try { await latest.current(); } catch { /* A tela mantém seu tratamento de erro. */ }
      finally { running = false; }
    };
    const onStorage = event => { if (event.key === 'spot.data.changed') refresh(); };
    const timer = window.setInterval(refresh, interval);
    window.addEventListener('focus', refresh);
    window.addEventListener('spot:data-changed', refresh);
    window.addEventListener('storage', onStorage);
    document.addEventListener('visibilitychange', refresh);
    return () => {
      active = false;
      window.clearInterval(timer);
      window.removeEventListener('focus', refresh);
      window.removeEventListener('spot:data-changed', refresh);
      window.removeEventListener('storage', onStorage);
      document.removeEventListener('visibilitychange', refresh);
    };
  }, [interval]);
}
