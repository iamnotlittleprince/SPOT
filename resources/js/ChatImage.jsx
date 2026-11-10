import React, { useEffect, useState } from 'react';
import { message } from './i18n';
export function isChatImage(file) { return ['image/jpeg', 'image/png', 'image/webp'].includes(file?.type); }
export default function ChatImage({ file, src, description, onLoad }) {
  const [localUrl, setLocalUrl] = useState(null);
  const [failed, setFailed] = useState(false);
  useEffect(() => {
    setFailed(false);
    if (!file) return;
    const url = URL.createObjectURL(file); setLocalUrl(url);
    return () => URL.revokeObjectURL(url);
  }, [file, src]);
  if (failed) return <small className="chat-image-unavailable">Prévia indisponível.</small>;
  if (!src && !localUrl) return null;
  return <img className="chat-image-preview" src={src || localUrl} alt={description || message('Imagem anexada: {name}', {name:file?.name || ''})} translate="no" loading="lazy" decoding="async" onLoad={onLoad} onError={() => setFailed(true)} />;
}
