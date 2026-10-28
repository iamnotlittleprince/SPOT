import { useCallback, useEffect, useRef, useState } from 'react';

// URL is the source of truth for dashboard pages, including browser traversal.
export default function useBrowserPage(pages) {
  const allowed = useRef(pages);
  allowed.current = pages;
  const read = () => {
    const page = new URL(window.location.href).searchParams.get('page');
    return allowed.current.includes(page) ? page : 'home';
  };
  const [page, updatePage] = useState(read);
  const current = useRef(page);
  useEffect(() => {
    const restore = () => {
      const next = read();
      current.current = next;
      updatePage(next);
    };
    window.addEventListener('popstate', restore);
    return () => window.removeEventListener('popstate', restore);
  }, []);
  const navigate = useCallback(next => {
    const target = typeof next === 'function' ? next(current.current) : next;
    if (!allowed.current.includes(target) || target === current.current) return;
    const url = new URL(window.location.href);
    if (target === 'home') url.searchParams.delete('page');
    else url.searchParams.set('page', target);
    window.history.pushState(null, '', url);
    current.current = target;
    updatePage(target);
    window.dispatchEvent(new Event("spot:navigated"));
  }, []);
  return [page, navigate];
}
