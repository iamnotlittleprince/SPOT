import ChatImage, { isChatImage } from './ChatImage';
import ChatRichEditor from './ChatRichEditor';
import LanguageSwitcher from './LanguageSwitcher';
import ChatFormattedText from './ChatFormattedText';
import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { MessageSquare, Send, Pencil, Languages, ShieldCheck, Search, LockKeyhole, Paperclip, Download, ImagePlus, X } from 'lucide-react';
import { sessionRequest } from './api';
import { getFormatLocale, getLanguage, message, translateText } from './i18n';
import useAutoRefresh from './useAutoRefresh';
import '../css/chat.css';

async function chatApi(path, method = 'GET', body, signal) {
  const response = await sessionRequest(`/chat${path}`, {
    method, signal, handledStatuses: [403, 404, 409, 422, 429, 503],
    ...(body ? { body: body instanceof FormData ? body : JSON.stringify(body) } : {}),
  });
  if (response.status === 413) throw new Error('Os anexos excedem o limite de envio. Anexe até 3 arquivos de 2 MB cada.');
  const data = await response.json();
  if (!response.ok) {
    const error = new Error(data.message || 'Não foi possível carregar o chat. Tente novamente.');
    error.fields = data.errors || {};
    error.status = response.status;
    throw error;
  }
  return data;
}
const statusLabels = { pending: 'Pendente', approved: 'Aprovada', rejected: 'Recusada', revoked: 'Revogada' };
const mergeMessages = (old, incoming) => [...new Map([...old, ...incoming].map(item => [item.id, item])).values()].sort((a, b) => a.id - b.id);

export default function ChatPage({ user, initialUserId, initialRequests = false }) {
  const [catalog, setCatalog] = useState(null);
  const [selectedId, setSelectedId] = useState(initialUserId || null);
  const [view, setView] = useState(initialRequests ? 'requests' : 'conversations');
  const [search, setSearch] = useState('');
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(false);
  const [hasMore, setHasMore] = useState(false);
  const [body, setBody] = useState('');
  const [bodyFormat, setBodyFormat] = useState(null);
  const [files, setFiles] = useState([]);
  const [imageDescriptions, setImageDescriptions] = useState(new Map());
  const [reason, setReason] = useState('');
  const [requestPeer, setRequestPeer] = useState('');
  const [requestReviewer, setRequestReviewer] = useState('');
  const [requestReason, setRequestReason] = useState('');
  const [requestStatus, setRequestStatus] = useState('all');
  const [editing, setEditing] = useState(null);
  const [translations, setTranslations] = useState({});
  const [histories, setHistories] = useState({});
  const [target, setTarget] = useState(getLanguage);
  const [notes, setNotes] = useState({});
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [fields, setFields] = useState({});
  const [notice, setNotice] = useState('');
  const selectedRef = useRef(selectedId);
  selectedRef.current = selectedId;
  const loadedPeer = useRef(null);
  const catalogVersion = useRef(0);
  const composerRef = useRef(null);
  const searchRef = useRef(null);
  const fileRef = useRef(null);
  const imageRef = useRef(null);
  const sendingRef = useRef(false);
  const editRef = useRef(null);
  const logRef = useRef(null);
  const following = useRef(true);
  const scrollAnchor = useRef(null);
  useLayoutEffect(() => {
    const log = logRef.current;
    if (!log) return;
    if (scrollAnchor.current) {
      log.scrollTop = log.scrollHeight - scrollAnchor.current.height + scrollAnchor.current.top;
      scrollAnchor.current = null;
    } else if (following.current) log.scrollTop = log.scrollHeight;
  }, [messages]);
  const contact = catalog?.contacts.find(person => person.id === selectedId);
  const stamp = value => new Date(value).toLocaleString(getFormatLocale(), { timeZone: user.timezone || undefined, dateStyle: 'short', timeStyle: 'short' });

  function showError(failure) {
    if (failure.name === 'AbortError') return;
    setError(failure.message);
    setFields(failure.fields || {});
  }
  async function loadCatalog(signal) {
    const loadId = ++catalogVersion.current;
    let data;
    try { data = await chatApi('', 'GET', undefined, signal); }
    catch (failure) {
      if (failure.status === 403 && !signal?.aborted && loadId === catalogVersion.current) { setCatalog(null); setMessages([]); setSelectedId(null); }
      throw failure;
    }
    if (!signal?.aborted && loadId === catalogVersion.current) setCatalog(data);
    return data;
  }
  useEffect(() => {
    const controller = new AbortController();
    loadCatalog(controller.signal).catch(showError);
    return () => controller.abort();
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    loadedPeer.current = null; following.current = true; scrollAnchor.current = null;
    setMessages([]); setHasMore(false); setBody(''); setBodyFormat(null); setFiles([]); setImageDescriptions(new Map()); setEditing(null); setReason('');
    setTranslations({}); setHistories({});
    if (catalog) { setError(''); setFields({}); setNotice(''); }
    if (!contact?.can_chat) { setLoading(false); return () => controller.abort(); }
    setLoading(true);
    chatApi(`/users/${selectedId}/messages`, 'GET', undefined, controller.signal).then(data => {
      if (controller.signal.aborted) return;
      setMessages(data.messages); setHasMore(data.has_more); loadedPeer.current = selectedId;
    }).catch(showError).finally(() => { if (!controller.signal.aborted) setLoading(false); });
    return () => controller.abort();
  }, [selectedId, contact?.can_chat]);

  useEffect(() => { if (editing) editRef.current?.focus(); }, [editing?.id]);
  useAutoRefresh(async () => {
    try {
      const next = await loadCatalog();
      const peerId = selectedRef.current;
      if (view !== 'conversations' || loadedPeer.current !== peerId || !next.contacts.some(person => person.id === peerId && person.can_chat)) return;
      const data = await chatApi(`/users/${peerId}/messages`);
      if (selectedRef.current === peerId) setMessages(current => mergeMessages(current, data.messages));
    } catch (failure) { showError(failure); }
  }, 8000);

  async function act(work, success) {
    setBusy(true); setError(''); setFields({}); setNotice('');
    try { await work(); if (success) setNotice(success); }
    catch (failure) { showError(failure); }
    finally { setBusy(false); }
  }
  function endEdit(id) {
    setEditing(null);
    requestAnimationFrame(() => (document.getElementById(`chat-edit-${id}`) || composerRef.current)?.focus());
  }
  async function send(event) {
    event.preventDefault();
    if (sendingRef.current || busy || loading || !contact?.can_chat || (!body.trim() && !files.length)) return;
    sendingRef.current = true;
    const peerId = selectedId;
    let payload = { body, body_format: bodyFormat };
    if (files.length) {
      payload = new FormData();
      payload.append('body', body);
      if (bodyFormat) payload.append('body_format', JSON.stringify(bodyFormat));
      files.forEach((file,index) => {
        payload.append('attachments[]', file);
        if (isChatImage(file) && imageDescriptions.get(file)) payload.append(`attachment_descriptions[${index}]`, imageDescriptions.get(file));
      });
    }
    try {
      await act(async () => {
        const data = await chatApi(`/users/${peerId}/messages`, 'POST', payload);
        if (selectedRef.current === peerId) {
          following.current = true; setMessages(current => mergeMessages(current, [data.message])); setBody(''); setBodyFormat(null); setFiles([]); setImageDescriptions(new Map());
          requestAnimationFrame(() => composerRef.current?.focus());
        }
      }, 'Mensagem enviada.');
    } finally { sendingRef.current = false; }
  }
  function selectFiles(event) {
    const incoming = Array.from(event.target.files || []);
    const imageOnly = event.target.id === 'chat-image-file';
    const next = [...files, ...incoming];
    event.target.value = '';
    setFields({}); setError('');
    const problem = imageOnly && incoming.some(file => !isChatImage(file)) ? 'Escolha uma imagem JPG, PNG ou WebP.' : next.length > 3 ? 'Anexe até 3 arquivos por mensagem.' : next.some(file => file.size > 2 * 1024 * 1024) ? 'Cada arquivo deve ter no máximo 2 MB.' : '';
    if (problem) { setFields({ attachments: [problem] }); setError(problem); return; }
    setFiles(next);
  }
  async function downloadAttachment(file) {
    await act(async () => {
      const response = await sessionRequest(file.download_url.replace('/api/v1', ''), { handledStatuses: [403, 404, 503] });
      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Não foi possível baixar o anexo. Tente novamente.');
      }
      const url = URL.createObjectURL(await response.blob());
      const link = document.createElement('a');
      link.href = url; link.download = file.name;
      document.body.appendChild(link); link.click(); link.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
  }
  async function saveEdit(event) {
    event.preventDefault();
    const peerId = selectedId;
    await act(async () => {
      const data = await chatApi(`/messages/${editing.id}`, 'PATCH', { body: editing.body, body_format: editing.body_format || null, version: editing.version });
      if (selectedRef.current === peerId) {
        setMessages(current => mergeMessages(current, [data.message]));
        setHistories(current => { const next = { ...current }; delete next[data.message.id]; return next; });
        endEdit(data.message.id);
      }
    }, 'Mensagem editada.');
  }
  async function older() {
    const peerId = selectedId;
    await act(async () => {
      const data = await chatApi(`/users/${peerId}/messages?before=${messages[0].id}`);
      if (selectedRef.current === peerId) {
        if (logRef.current) scrollAnchor.current = { height: logRef.current.scrollHeight, top: logRef.current.scrollTop };
        setMessages(current => mergeMessages(data.messages, current)); setHasMore(data.has_more);
      }
    });
  }
  const contacts = (catalog?.contacts || []).filter(person => person.name.toLocaleLowerCase(getFormatLocale()).includes(search.toLocaleLowerCase(getFormatLocale())));
  const canStartWithoutApproval = Boolean(catalog?.can_start_without_approval);
  const conversationLabel = person => person.can_chat ? (person.same_team ? 'Seu time' : person.administrative_access ? 'Conversa direta' : 'Conversa autorizada') : person.pending ? 'Solicitação pendente' : 'Requer autorização';
  const requestCandidates = (catalog?.contacts || []).filter(person => !person.can_chat && !person.pending);
  const validReviewer = (catalog?.reviewers || []).some(person => String(person.id) === requestReviewer);
  const canRequest = Boolean(validReviewer && catalog?.reviewers_available && requestCandidates.some(person => String(person.id) === requestPeer));
  const visibleRequests = (catalog?.requests || []).filter(entry => requestStatus === 'all' || entry.status === requestStatus);
  const pendingCount = catalog?.requests.filter(entry => entry.can_decide).length || 0;
  const fieldError = key => fields[key]?.[0] || (key === 'body' ? fields.body_format?.[0] : undefined);
  const reviewerField = id => <div className="chat-reviewer-field">
    <label htmlFor={id}>Enviar aprovação para</label>
    <select id={id} required value={requestReviewer} disabled={busy || !catalog?.reviewers?.length} onChange={event => setRequestReviewer(event.target.value)} aria-invalid={Boolean(fieldError('reviewer_id'))} aria-describedby={fieldError('reviewer_id') ? `${id}-error` : undefined}>
      <option value="">Selecione um aprovador</option>
      {(catalog?.reviewers || []).map(person => <option key={person.id} value={person.id} translate="no">{person.name}</option>)}
    </select>
    {fieldError('reviewer_id') && <p id={`${id}-error`} className="chat-error">{translateText(fieldError('reviewer_id'))}</p>}
  </div>;
  const attachmentError = Object.entries(fields).filter(([key]) => key === 'attachments' || key.startsWith('attachments.')).flatMap(([, values]) => values)[0];
  const translationFor = item => translations[item.id]?.version === item.version ? translations[item.id] : null;
  const translated = item => Boolean(translationFor(item) && !translationFor(item).showOriginal);
  const isMine = item => item.is_mine ?? (user.id != null && String(item.sender_id) === String(user.id));
  const fileSize = size => new Intl.NumberFormat(getFormatLocale(), { style: 'unit', unit: 'kilobyte', maximumFractionDigits: 0 }).format(Math.ceil(size / 1024));

  return <div className="workspace-page chat-page">
    <div className="chat-toolbar">
    <header className="chat-heading"><div><h1><MessageSquare aria-hidden="true" size={26} />Chat</h1><p>Converse com seu time e acompanhe suas solicitações.</p></div></header>
    <div className="chat-view-switch" aria-label="Áreas do chat">
      <button type="button" aria-pressed={view === 'conversations'} onClick={() => setView('conversations')}>Conversas</button>
      <button type="button" aria-pressed={view === 'requests'} onClick={() => setView('requests')}><ShieldCheck aria-hidden="true" size={18} />{catalog?.can_review ? 'Aprovações de chat' : 'Solicitações de chat'}{pendingCount > 0 && <span aria-label={message('{count} solicitações pendentes', { count: pendingCount })}>{pendingCount}</span>}</button>
    </div>
    </div>
    {error && <p className="chat-error" role="alert">{translateText(error)}</p>}
    <p className="chat-status" role="status">{translateText(notice)}</p>
    {!catalog && !error && <p>Carregando chat...</p>}
    {!catalog && error && <button type="button" onClick={() => act(() => loadCatalog())}>Tentar novamente</button>}
    {catalog && view === 'conversations' && <div className="chat-layout">
      <aside className="chat-contacts" aria-label="Pessoas no chat">
        <div className="chat-contacts-heading"><h2>Conversas</h2><MessageSquare size={20} aria-hidden="true" /></div>
        <label htmlFor="chat-search">Buscar pessoa</label><div className="chat-search-field"><Search size={17} aria-hidden="true" /><input ref={searchRef} id="chat-search" type="search" value={search} onChange={event => setSearch(event.target.value)} /></div>
        <p>{canStartWithoutApproval ? 'Seu perfil permite conversar diretamente com pessoas desta empresa. Pesquise um nome e envie sua mensagem.' : 'Fora do seu time, solicite autorização a um administrador ou gerente administrador.'}</p>
        <ul>{contacts.map(person => <li key={person.id}><button type="button" disabled={busy} aria-current={selectedId === person.id ? 'true' : undefined} onClick={() => setSelectedId(person.id)}>
          <span className="chat-avatar" translate="no" aria-hidden="true">{person.avatar_url ? <img src={person.avatar_url} alt="" /> : person.name.trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toLocaleUpperCase()}</span><span><strong translate="no">{person.name}</strong><small>{conversationLabel(person)}</small></span>
        </button></li>)}</ul>
        {!contacts.length && <p>Nenhuma pessoa encontrada.</p>}
      </aside>
      <section className="chat-conversation" aria-labelledby="chat-conversation-title" aria-busy={loading}>
        <header className="chat-conversation-heading">
          <span className="chat-avatar chat-header-avatar" translate="no" aria-hidden="true">{contact ? (contact.avatar_url ? <img src={contact.avatar_url} alt="" /> : contact.name.trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toLocaleUpperCase()) : <MessageSquare size={22} />}</span>
          <div className="chat-contact-identity"><h2 id="chat-conversation-title">{contact ? <span translate="no">{contact.name}</span> : 'Selecione uma pessoa'}</h2>{contact && <small>{conversationLabel(contact)}</small>}</div>
          {contact?.can_chat && <div className="chat-target-field"><span id="chat-target-label" className="chat-translation-label">Traduzir para</span><LanguageSwitcher locale={target} onChange={setTarget} labelledBy="chat-target-label" /></div>}
        </header>
        {!contact && <div className="chat-empty-state"><span aria-hidden="true"><MessageSquare size={42} strokeWidth={1.3} /></span><h3>Conversas</h3><p>{canStartWithoutApproval ? 'Pesquise um nome e escolha uma pessoa para conversar.' : 'Escolha uma pessoa para conversar ou solicitar autorização.'}</p></div>}
        {contact && !contact.can_chat && <div className="chat-access-form">
          <span className="chat-access-icon" aria-hidden="true"><LockKeyhole size={27} /></span><p>Esta conversa precisa de autorização de um administrador ou gerente administrador.</p>
          {contact.pending ? <p role="status">Solicitação pendente. Aguarde a análise de um administrador ou gerente administrador.</p> : !catalog.reviewers_available ? <p>Não há administrador ou gerente administrador disponível. Peça à administração para verificar os perfis.</p> : <form onSubmit={event => { event.preventDefault(); act(async () => {
            await chatApi(`/users/${selectedId}/requests`, 'POST', { reason, reviewer_id: Number(requestReviewer) }); await loadCatalog(); setReason(''); setRequestReviewer('');
          }, 'Solicitação enviada ao aprovador selecionado.'); }}>
            {reviewerField('chat-conversation-reviewer')}
            <label htmlFor="chat-reason">Motivo da solicitação</label><textarea id="chat-reason" translate="no" required maxLength={1000} disabled={busy} value={reason} onChange={event => setReason(event.target.value)} aria-invalid={Boolean(fieldError('reason'))} aria-describedby={fieldError('reason') ? 'chat-reason-error' : undefined} />
            {fieldError('reason') && <p id="chat-reason-error" className="chat-error">{translateText(fieldError('reason'))}</p>}
            <button type="submit" disabled={busy || !reason.trim() || !validReviewer}>Solicitar autorização</button>
          </form>}
        </div>}
        {contact?.can_chat && <>
          {!catalog.translation_available && <div className="chat-conversation-tools"><small>A tradução de mensagens aguarda configuração pela administração.</small></div>}
          {hasMore && <button type="button" disabled={busy || loading} onClick={older}>Carregar mensagens anteriores</button>}
          <ol ref={logRef} onScroll={event => { const log = event.currentTarget; following.current = log.scrollHeight - log.scrollTop - log.clientHeight < 40; }} className="chat-messages" tabIndex={0} role="log" aria-label="Mensagens da conversa" aria-live="polite" aria-relevant="additions text">
            {messages.map(item => <li key={item.id} className={isMine(item) ? 'chat-message mine' : 'chat-message'}>
              <div className="chat-message-top"><div className="chat-message-meta"><strong translate="no">{item.sender_name || (isMine(item) ? user.name : contact.name)}</strong><time dateTime={item.created_at}>{stamp(item.created_at)}</time>{item.edited_at && <span>Editada</span>}</div>
              <div className="chat-message-actions">
                {item.can_edit && new Date(item.editable_until).getTime() >= Date.now() && <button id={`chat-edit-${item.id}`} aria-label="Editar mensagem" type="button" disabled={busy} onClick={() => { setFields({}); setError(''); setEditing({ ...item }); }}><Pencil size={13} aria-hidden="true" />Editar</button>}
                <button type="button" disabled={busy || !catalog.translation_available || !item.body?.trim()} aria-label="Traduzir mensagem" onClick={() => { const peerId = selectedId; act(async () => {
                  const data = await chatApi(`/messages/${item.id}/translate`, 'POST', { target });
                  if (selectedRef.current === peerId) setTranslations(current => ({ ...current, [item.id]: data }));
                }, 'Tradução concluída.'); }}><Languages size={13} aria-hidden="true" />Traduzir</button>
              </div>
              </div>
              <div className="chat-message-content">
              {editing?.id === item.id ? <form className="chat-edit-form" onSubmit={saveEdit} onKeyDown={event => { if (event.key === 'Escape') { event.stopPropagation(); endEdit(item.id); } }}>
                <span id="chat-edit-label" className="chat-editor-label">Editar mensagem</span><ChatRichEditor ref={editRef} key={item.id} autoFocus id="chat-edit-body" label="Editar mensagem" labelledBy="chat-edit-label" value={editing.body} format={editing.body_format} disabled={busy} onChange={({text,format}) => setEditing(current => ({...current,body:text,body_format:format}))} invalid={Boolean(fieldError('body'))} describedBy="chat-edit-help chat-edit-error" onEscape={() => endEdit(item.id)} />
                <span id="chat-edit-error" className="chat-error">{translateText(fieldError('body'))}</span><small id="chat-edit-help">{message('Você pode editar até {date}.', { date: stamp(item.editable_until) })}</small>
                <div><button disabled={busy || (!editing.body.trim() && !item.attachments?.length)} type="submit">Salvar edição</button><button disabled={busy} type="button" onClick={() => endEdit(item.id)}>Cancelar</button></div>
              </form> : item.body ? <ChatFormattedText body={translated(item) ? translationFor(item).text : item.body} format={translated(item) ? null : item.body_format} lang={translated(item) ? translationFor(item).target : undefined} className="chat-message-body" /> : null}

              {Boolean(item.attachments?.length) && <ul className="chat-attachments" aria-label="Anexos da mensagem">{item.attachments.map(file => <li key={file.id}>
                {file.preview_url && <ChatImage src={file.preview_url} description={file.alt_text || message('Imagem anexada: {name}', {name:file.name})} onLoad={() => { if (following.current && logRef.current) logRef.current.scrollTop = logRef.current.scrollHeight; }} />}
                <a href={file.download_url} download={file.name} aria-disabled={busy} onClick={event => { event.preventDefault(); if (!busy) downloadAttachment(file); }} aria-label={message('Baixar {name}', { name: file.name })}><Download size={16} aria-hidden="true" /><span><strong translate="no">{file.name}</strong><small>{fileSize(file.size)}</small></span></a>
              </li>)}</ul>}
              {translationFor(item) && editing?.id !== item.id && <div className="chat-translation-note"><small>{translated(item) ? 'Tradução automática' : 'Mensagem original'}</small><button type="button" disabled={busy} onClick={() => setTranslations(current => ({ ...current, [item.id]: { ...current[item.id], showOriginal: !current[item.id].showOriginal } }))}>{translated(item) ? 'Ver original' : 'Ver tradução'}</button></div>}
              {item.version > 1 && <details onToggle={event => {
                if (!event.currentTarget.open || histories[item.id]?.version === item.version) return;
                const peerId = selectedId;
                chatApi(`/messages/${item.id}/revisions`).then(data => { if (selectedRef.current === peerId) setHistories(current => ({ ...current, [item.id]: { version: item.version, items: data.revisions } })); }).catch(showError);
              }}><summary>Histórico de edições</summary>{histories[item.id]?.items.map(revision => <div className="chat-revision" key={revision.version}><small>{message('Versão {version}', { version: revision.version })}</small><ChatFormattedText body={revision.body} format={revision.body_format} /></div>)}</details>}
              </div>
            </li>)}
          </ol>
          {!loading && !messages.length && <p className="chat-empty-messages">Nenhuma mensagem nesta conversa.</p>}
          <form className="chat-composer" onSubmit={send}>
            <span id="chat-body-label" className="chat-editor-label">Mensagem</span>
            <div className="chat-composer-input">
              <ChatRichEditor key={selectedId} ref={composerRef} id="chat-body" label="Mensagem" labelledBy="chat-body-label" value={body} format={bodyFormat} disabled={busy || loading} onSend={send} onChange={({text,format}) => {setBody(text);setBodyFormat(format);}} invalid={!editing && Boolean(fieldError('body'))} describedBy="chat-body-help chat-body-error chat-keyboard-help" />
              <input ref={fileRef} id="chat-file" type="file" hidden multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.webp,.zip" disabled={busy || loading} onChange={selectFiles} />
              <input ref={imageRef} id="chat-image-file" type="file" hidden multiple accept="image/jpeg,image/png,image/webp" disabled={busy || loading} onChange={selectFiles} />
              <button type="button" className="chat-image-button" disabled={busy || loading} aria-describedby="chat-attachment-help chat-attachment-error" onClick={() => imageRef.current?.click()}><ImagePlus size={18} aria-hidden="true" />Enviar imagem</button>
              <button type="button" className="chat-attach-button" disabled={busy || loading} aria-describedby="chat-attachment-help chat-attachment-error" onClick={() => fileRef.current?.click()}><Paperclip size={18} aria-hidden="true" />Anexar arquivos</button>
              <button type="submit" disabled={busy || loading || (!body.trim() && !files.length)}><Send size={18} aria-hidden="true" />Enviar mensagem</button>
            </div>
            {files.length > 0 && <ul className="chat-selected-files" aria-label="Arquivos selecionados">{files.map((file, index) => <li key={`${file.name}:${index}`}>
              <div className="chat-selected-file-row"><Paperclip size={15} aria-hidden="true" /><span translate="no">{file.name}</span><small>{fileSize(file.size)}</small><button type="button" disabled={busy} aria-label={message('Remover anexo selecionado {name}', { name: file.name })} onClick={() => { setFiles(current => current.filter((_, position) => position !== index)); composerRef.current?.focus(); }}><X size={15} aria-hidden="true" /></button></div>
              {isChatImage(file) && <><ChatImage file={file} description={imageDescriptions.get(file) || message('Imagem anexada: {name}',{name:file.name})} /><label htmlFor={`chat-image-description-${index}`}>Descrição da imagem (opcional)<input id={`chat-image-description-${index}`} aria-label={`${translateText('Descrição da imagem (opcional)')}: ${file.name}`} translate="no" maxLength={500} value={imageDescriptions.get(file) || ''} disabled={busy} aria-invalid={Boolean(fieldError(`attachment_descriptions.${index}`))} aria-describedby={fieldError(`attachment_descriptions.${index}`) ? `chat-image-description-error-${index}` : undefined} onChange={event => {const value=event.target.value;setImageDescriptions(current=>new Map(current).set(file,value));}} /></label>{fieldError(`attachment_descriptions.${index}`) && <p id={`chat-image-description-error-${index}`} className="chat-error">{translateText(fieldError(`attachment_descriptions.${index}`))}</p>}</>}
            </li>)}</ul>}
            <div className="chat-composer-footer">
              <small id="chat-keyboard-help">Enter envia. Ctrl + Shift + Enter insere uma nova linha.</small>
              <details className="chat-composer-help"><summary>Limites e edição</summary><div>
                <small id="chat-attachment-help">Até 3 arquivos de 2 MB cada: PDF, Office, TXT, CSV, JPG, PNG, WebP ou ZIP.</small>
                <small id="chat-body-help">Você pode editar suas mensagens por até 24 horas após o envio. Mensagens não podem ser excluídas.</small>
              </div></details>
            </div>
            <span id="chat-attachment-error" className="chat-error">{translateText(attachmentError)}</span>
            <span id="chat-body-error" className="chat-error">{!editing && translateText(fieldError('body'))}</span>
          </form>
        </>}
      </section>
    </div>}
    {catalog && view === 'requests' && <section className="chat-requests" aria-label="Solicitações de chat">
      <h2>{catalog.can_review ? 'Aprovações de chat' : 'Solicitações de chat'}</h2>
      <p>{catalog.can_review ? 'Analise os pedidos para conversar fora dos times. A autorização vale apenas para as duas pessoas indicadas.' : 'Acompanhe a autorização para conversar fora dos seus times.'}</p>
      <div className="chat-request-layout">
      <section className="chat-new-request" aria-labelledby="chat-new-request-title">
        {canStartWithoutApproval ? <>
        <h3 id="chat-new-request-title">Conversas sem solicitação</h3>
        <p>Administradores e gerentes administradores podem iniciar conversas diretamente. As solicitações são necessárias para analistas e gerentes fora dos projetos em comum.</p>
        <button type="button" onClick={() => { setView('conversations'); requestAnimationFrame(() => searchRef.current?.focus()); }}>Ir para conversas</button>
      </> : <>
        <h3 id="chat-new-request-title">Solicitar acesso ao chat</h3>
        <p id="chat-request-rules">Pessoas do mesmo time já podem conversar. Para conversar fora do time, escolha uma pessoa e explique o motivo. Um administrador ou gerente administrador da empresa, mesmo fora do projeto, analisará o pedido; ninguém pode aprovar a própria solicitação.</p>
        {!catalog.reviewers_available && <p id="chat-request-unavailable" className="chat-request-warning" role="status">Não há administrador ou gerente administrador disponível. Peça à administração para verificar os perfis.</p>}
        {!requestCandidates.length && <p id="chat-request-empty" role="status">Não há pessoas disponíveis para uma nova solicitação. Conversas liberadas ou com pedido pendente não aparecem nesta lista.</p>}
        <form aria-label="Nova solicitação de chat" aria-describedby="chat-request-rules" aria-busy={busy} onSubmit={event => {
          event.preventDefault();
          if (busy || !canRequest || !requestReason.trim()) return;
          act(async () => {
            await chatApi(`/users/${requestPeer}/requests`, 'POST', { reason: requestReason.trim(), reviewer_id: Number(requestReviewer) });
            setRequestPeer(''); setRequestReason(''); setRequestReviewer('');
            await loadCatalog();
          }, 'Solicitação enviada ao aprovador selecionado.');
        }}>
          <label htmlFor="chat-request-person">Pessoa para conversar</label>
          <select id="chat-request-person" required disabled={busy || !requestCandidates.length} value={requestPeer} onChange={event => setRequestPeer(event.target.value)}>
            <option value="">Selecione uma pessoa</option>
            {requestCandidates.map(person => <option key={person.id} value={person.id} translate="no">{person.name}</option>)}
          </select>
          {reviewerField('chat-request-reviewer')}
          <label htmlFor="chat-request-reason">Motivo da solicitação</label>
          <textarea id="chat-request-reason" translate="no" required maxLength={1000} disabled={busy || !requestCandidates.length} value={requestReason} onChange={event => setRequestReason(event.target.value)} aria-invalid={Boolean(fieldError('reason'))} aria-describedby={fieldError('reason') ? 'chat-request-reason-error' : undefined} />
          {fieldError('reason') && <p id="chat-request-reason-error" className="chat-error">{translateText(fieldError('reason'))}</p>}
          <div><button type="submit" aria-describedby={!catalog.reviewers_available ? "chat-request-unavailable" : !requestCandidates.length ? "chat-request-empty" : undefined} disabled={busy || !canRequest || !requestReason.trim()}>Enviar solicitação de acesso</button></div>
        </form>
      </>}
      </section>
      <section className="chat-request-history" aria-labelledby="chat-request-history-title">
      <div className="chat-request-tracking">
        <h3 id="chat-request-history-title">Acompanhar solicitações</h3>
        <div className="chat-request-filter"><label htmlFor="chat-request-status">Filtrar por situação</label>
          <select id="chat-request-status" value={requestStatus} onChange={event => setRequestStatus(event.target.value)}>
            <option value="all">Todas as situações</option>
            {Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
        </div>
      </div>
      {!visibleRequests.length && <p role="status">{catalog.requests.length ? 'Nenhuma solicitação nesta situação.' : 'Nenhuma solicitação de chat.'}</p>}
      {visibleRequests.map(entry => <article key={entry.id}><header><h3><span translate="no">{entry.first_name}</span><span aria-hidden="true"> ↔ </span><span translate="no">{entry.second_name}</span></h3><strong>{statusLabels[entry.status] || entry.status}</strong></header>
        <p><span>Solicitante</span>: <span translate="no">{entry.requester_name}</span></p><p translate="no">{entry.reason}</p><time dateTime={entry.created_at}>{stamp(entry.created_at)}</time>
        {entry.decision_note && <p translate="no">{entry.decision_note}</p>}
        {entry.assigned_reviewer_name && <p><span>Enviado para</span>: <span translate="no">{entry.assigned_reviewer_name}</span></p>}
        {entry.reviewer_name && <p><span>Analisado por</span>: <span translate="no">{entry.reviewer_name}</span></p>}
        {entry.can_decide && <form onSubmit={event => { event.preventDefault(); const decision = event.nativeEvent.submitter?.value; if (!decision) return; act(async () => {
          await chatApi(`/requests/${entry.id}/decision`, 'POST', { decision, note: notes[entry.id] || '' }); await loadCatalog();
        }, 'Solicitação respondida.'); }}>
          <label htmlFor={`chat-note-${entry.id}`}>Observação (opcional)</label><textarea id={`chat-note-${entry.id}`} translate="no" maxLength={1000} disabled={busy} value={notes[entry.id] || ''} onChange={event => setNotes(current => ({ ...current, [entry.id]: event.target.value }))} />
          <div><button type="submit" value="approve" disabled={busy}>Aprovar conversa</button><button type="submit" value="reject" disabled={busy}>Recusar conversa</button></div>
        </form>}
        {entry.can_revoke && <button type="button" disabled={busy} onClick={() => act(async () => { await chatApi(`/requests/${entry.id}/revoke`, 'POST', {}); await loadCatalog(); }, 'Autorização de chat revogada.')}>Revogar autorização</button>}
      </article>)}
      </section>
      </div>
    </section>}
  </div>;
}
