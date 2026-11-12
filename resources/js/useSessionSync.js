import { useEffect, useState } from 'react';
import { sessionChangeKey } from './sessionSync';

export default function useSessionSync(userId, authenticated) {
  const [changed, setChanged] = useState(false);
  useEffect(() => {
    if (!authenticated || userId == null) return;
    let active = true;
    let running = false;
    const markChanged = () => { if (active) setChanged(true); };
    const verify = async () => {
      if (running || !active) return;
      running = true;
      try {
        const response = await fetch('/api/v1/auth/me', {
          credentials: 'same-origin', headers: { Accept: 'application/json' },
        });
        if (!active) return;
        if (response.status === 401) markChanged();
        else if (response.ok && String((await response.json()).id) !== String(userId)) markChanged();
      } catch { /* A network failure does not prove that identity changed. */ }
      finally { running = false; }
    };
    const storage = event => { if (event.key === sessionChangeKey) verify(); };
    const visible = () => { if (document.visibilityState === 'visible') verify(); };
    window.addEventListener('storage', storage);
    window.addEventListener('focus', verify);
    window.addEventListener('spot:session-changed', markChanged);
    document.addEventListener('visibilitychange', visible);
    return () => {
      active = false;
      window.removeEventListener('storage', storage);
      window.removeEventListener('focus', verify);
      window.removeEventListener('spot:session-changed', markChanged);
      document.removeEventListener('visibilitychange', visible);
    };
  }, [userId, authenticated]);
  return changed;
}
