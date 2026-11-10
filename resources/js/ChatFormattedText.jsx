import React from 'react';
import { safeLink } from './chatFormatting';

// Render only the agreed document nodes as React elements; no user HTML is injected.
function nodeView(node, key, depth = 0) {
  if (!node || depth > 12) return null;
  if (node.type === 'text') {
    let text = node.text || '';
    for (const mark of node.marks || []) {
      const props = { key: `${key}:${mark.type}` };
      if (mark.type === 'bold') text = <strong {...props}>{text}</strong>;
      else if (mark.type === 'italic') text = <em {...props}>{text}</em>;
      else if (mark.type === 'underline') text = <u {...props}>{text}</u>;
      else if (mark.type === 'strike') text = <s {...props}>{text}</s>;
      else if (mark.type === 'code') text = <code {...props}>{text}</code>;
      else if (mark.type === 'highlight') text = <mark {...props}>{text}</mark>;
      else if (mark.type === 'link' && safeLink(mark.attrs?.href)) text = <a {...props} href={safeLink(mark.attrs.href)} target="_blank" rel="noopener noreferrer">{text}</a>;
      else if (mark.type === 'textStyle') {
        const color = ['purple', 'red', 'green', 'blue'].includes(mark.attrs?.color) ? mark.attrs.color : undefined;
        const size = ['normal', 'large', 'larger'].includes(mark.attrs?.fontSize) ? mark.attrs.fontSize : undefined;
        text = <span {...props} data-chat-color={color} data-chat-size={size}>{text}</span>;
      }
    }
    return <React.Fragment key={key}>{text}</React.Fragment>;
  }
  const children = (node.content || []).map((child, index) => nodeView(child, `${key}.${index}`, depth + 1));
  const props = { key };
  switch (node.type) {
    case 'doc': return <React.Fragment {...props}>{children}</React.Fragment>;
    case 'paragraph': return <p {...props}>{children.length ? children : <br />}</p>;
    case 'heading': { const Tag = { 1: 'h3', 2: 'h4', 3: 'h5' }[node.attrs?.level] || 'h3'; return <Tag {...props}>{children}</Tag>; }
    case 'hardBreak': return <br {...props} />;
    case 'blockquote': return <blockquote {...props}>{children}</blockquote>;
    case 'bulletList': return <ul {...props}>{children}</ul>;
    case 'orderedList': return <ol {...props} start={Number.isInteger(node.attrs?.start) ? node.attrs.start : 1}>{children}</ol>;
    case 'listItem': return <li {...props}>{children}</li>;
    case 'codeBlock': return <pre {...props}><code>{children}</code></pre>;
    case 'horizontalRule': return <hr {...props} />;
    default: return null;
  }
}
export default function ChatFormattedText({ body, format, className = '', ...props }) {
  if (!format) return <p className={className} translate="no" {...props}>{body}</p>;
  return <div className={`chat-format ${className}`} translate="no" {...props}>{nodeView(format, 'doc')}</div>;
}
