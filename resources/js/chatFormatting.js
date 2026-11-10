export const emptyDocument = () => ({ type: 'doc', content: [{ type: 'paragraph' }] });
export function plainDocument(text = '') {
  return { type: 'doc', content: text.split('\n').map(line => ({ type: 'paragraph', ...(line ? { content: [{ type: 'text', text: line }] } : {}) })) };
}
export function documentText(node) {
  if (!node) return '';
  if (node.type === 'text') return node.text || '';
  if (node.type === 'hardBreak') return '\n';
  const separator = ['doc', 'blockquote', 'bulletList', 'orderedList', 'listItem'].includes(node.type) ? '\n' : '';
  return (node.content || []).map(documentText).join(separator);
}
export function safeLink(value) {
  try {
    if (typeof value !== 'string' || /[\s\x00-\x1f\x7f]/.test(value) || value.length > 2048) return null;
    const url = new URL(value);
    return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password ? url.href : null;
  } catch { return null; }
}
