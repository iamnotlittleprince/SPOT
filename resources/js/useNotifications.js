import { useEffect, useRef, useState } from 'react';
import { sessionRequest } from './api';
import useAutoRefresh from './useAutoRefresh';

// The server supplies the cutoff; storage only prevents repeated announcements.
export default function useNotifications(userId) {
  const [unread, setUnread] = useState(0);
  const [notice, setNotice] = useState(null);
  const seen = useRef(new Set());
  useEffect(() => {
    try { seen.current = new Set(JSON.parse(localStorage.getItem('spot.notifications.seen.' + userId) || '[]')); }
    catch { seen.current = new Set(); }
  }, [userId]);
  async function refresh(active = () => true) {
    const response = await sessionRequest('/inbox');
    if (!response.ok) return;
    const data = await response.json();
    if (!active()) return;
    setUnread(data.unread_count || 0);
    const fresh = (data.new_items || []).filter(item => !seen.current.has(item.id));
    if (fresh.length) {
      fresh.forEach(item => seen.current.add(item.id));
      try { localStorage.setItem('spot.notifications.seen.' + userId, JSON.stringify([...seen.current].slice(-500))); } catch {}
      setNotice(fresh[0]);
    } else {
      setNotice(current => current && (data.new_items || []).some(item => item.id === current.id) ? current : null);
    }
  }
  useEffect(() => {
    let active = true;
    refresh(() => active).catch(() => {});
    return () => { active = false; };
  }, [userId]);
  useAutoRefresh(refresh, 15000);
  return { unread, notice, dismiss: () => setNotice(null) };
}
