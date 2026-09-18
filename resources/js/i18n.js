import { interfaceTranslations } from './translations.js';
export { interfaceTranslations };

export function getLanguage() {
  const language = window.localStorage.getItem('spot.locale');
  return ['pt', 'en', 'es'].includes(language) ? language : 'pt';
}
export function getFormatLocale() {
  return { pt: 'pt-BR', en: 'en-US', es: 'es-ES' }[getLanguage()];
}
export function setLanguage(language) {
  window.localStorage.setItem('spot.locale', ['pt', 'en', 'es'].includes(language) ? language : 'pt');
  window.dispatchEvent(new Event('spot:language'));
}
export function translateText(text, language = getLanguage()) {
  if (typeof text !== 'string' || language === 'pt') return text;
  const source = text.trim();
  const translation = interfaceTranslations[language]?.[source];
  if (translation) return text.replace(source, translation);
  return text;
}

export function message(source, values = {}) {
  return translateText(source).replace(/\{(\w+)\}/g, (match, key) => values[key] ?? match);
}

const sourceText = new WeakMap();
const sourceAttributes = new WeakMap();
const attributes = ['placeholder', 'title', 'aria-label', 'alt'];

export function translateInterface(language, root = document.getElementById('root')) {
  if (!root) return;
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  let node;
  while ((node = walker.nextNode())) {
    if (!node.textContent.trim() || node.parentElement?.closest('[translate="no"],script,style,textarea,[contenteditable="true"]')) continue;
    const current = node.textContent;
    const stored = sourceText.get(node);
    const source = stored && current === stored.rendered ? stored.source : current;
    const rendered = translateText(source, language);
    if (current !== rendered) node.textContent = rendered;
    sourceText.set(node, { source, rendered });
  }
  root.querySelectorAll(attributes.map((attribute) => `[${attribute}]`).join(',')).forEach((element) => {
    if (element.closest('[translate="no"]')) return;
    const storedAttributes = sourceAttributes.get(element) || {};
    for (const attribute of attributes) {
      const current = element.getAttribute(attribute);
      if (current === null) { delete storedAttributes[attribute]; continue; }
      const stored = storedAttributes[attribute];
      const source = stored && current === stored.rendered ? stored.source : current;
      const rendered = translateText(source, language);
      if (current !== rendered) element.setAttribute(attribute, rendered);
      storedAttributes[attribute] = { source, rendered };
    }
    sourceAttributes.set(element, storedAttributes);
  });
  document.documentElement.lang = { pt: 'pt-BR', en: 'en', es: 'es' }[language] || 'pt-BR';
}

export function observeTranslations(language) {
  const root = document.getElementById('root');
  if (!root) return () => {};
  const options = { childList: true, characterData: true, subtree: true, attributes: true, attributeFilter: attributes };
  // Pause observation during our writes, so translated text does not trigger a loop.
  const observer = new MutationObserver(() => {
    observer.disconnect();
    translateInterface(language, root);
    observer.observe(root, options);
  });
  translateInterface(language, root);
  observer.observe(root, options);
  return () => observer.disconnect();
}
