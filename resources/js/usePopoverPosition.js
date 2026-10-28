import { useEffect } from 'react';

// Anchor to the trigger, clamp to the viewport and account for reading zoom.
export default function usePopoverPosition(open, container, selector) {
  useEffect(() => {
    if (!open) return;
    const button = container.current?.querySelector(':scope > button');
    const panel = container.current?.querySelector(selector);
    if (!button || !panel) return;
    const position = () => {
      const zoom = parseFloat(getComputedStyle(document.getElementById('root')).zoom) || 1;
      const bounds = button.getBoundingClientRect();
      const width = panel.getBoundingClientRect().width;
      const left = Math.max(12, Math.min(bounds.left, document.documentElement.clientWidth - width - 12));
      const below = Math.max(0, innerHeight - bounds.bottom - 22);
      const above = Math.max(0, bounds.top - 22);
      const flip = below < 180 && above > below;
      const available = flip ? above : below;
      const top = flip ? bounds.top - 10 - Math.min(panel.scrollHeight * zoom, available) : bounds.bottom + 10;
      Object.assign(panel.style, {position:'fixed', right:'auto', left:`${left / zoom}px`, top:`${Math.max(12, top) / zoom}px`, maxHeight:`${available / zoom}px`});
    };
    position();
    const observer = new ResizeObserver(position);
    observer.observe(button);
    observer.observe(panel);
    window.addEventListener('resize', position);
    window.addEventListener('scroll', position, true);
    return () => {
      observer.disconnect();
      window.removeEventListener('resize', position);
      window.removeEventListener('scroll', position, true);
    };
  }, [open, container, selector]);
}
