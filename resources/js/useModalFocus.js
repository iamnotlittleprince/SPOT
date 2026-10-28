import { useEffect, useRef } from 'react';

const modalStack = [];

// Opt-in: each dialog owns its close action and accessible name.
export default function useModalFocus(open, onClose) {
  const ref = useRef(null);
  const close = useRef(onClose);
  close.current = onClose;
  useEffect(() => {
    if (!open || !ref.current) return;
    const dialog = ref.current;
    const previous = document.activeElement;
    const controls = () => [...dialog.querySelectorAll('button, a[href], input, select, textarea, [tabindex]')]
      .filter(element => !element.disabled && element.tabIndex >= 0 && element.getClientRects().length && !element.closest('[inert]'));
    const focusFirst = () => (controls()[0] || dialog).focus();
    modalStack.push(dialog);
    focusFirst();
    const keydown = event => {
      if (modalStack.at(-1) !== dialog) return;
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        close.current();
      }
      if (event.key === 'Tab') {
        const items = controls();
        const index = items.indexOf(document.activeElement);
        if (!items.length || (event.shiftKey ? index <= 0 : index === items.length - 1 || index < 0)) {
          event.preventDefault();
          (event.shiftKey ? items.at(-1) || dialog : items[0] || dialog).focus();
        }
      }
    };
    const focusin = event => { if (modalStack.at(-1) === dialog && !dialog.contains(event.target)) focusFirst(); };
    document.addEventListener('keydown', keydown, true);
    document.addEventListener('focusin', focusin);
    return () => {
      document.removeEventListener('keydown', keydown, true);
      document.removeEventListener('focusin', focusin);
      const index = modalStack.lastIndexOf(dialog);
      if (index >= 0) modalStack.splice(index, 1);
      if (previous?.isConnected && (!modalStack.length || modalStack.at(-1).contains(previous))) previous.focus();
    };
  }, [open]);
  return ref;
}
