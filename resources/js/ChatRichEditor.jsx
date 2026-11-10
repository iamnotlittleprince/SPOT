import React, { forwardRef, useEffect, useImperativeHandle, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useEditor, useEditorState, EditorContent } from '@tiptap/react';
import { Extension } from '@tiptap/core';
import { Plugin } from '@tiptap/pm/state';
import StarterKit from '@tiptap/starter-kit';
import { TextStyle } from '@tiptap/extension-text-style';
import Highlight from '@tiptap/extension-highlight';
import Placeholder from '@tiptap/extension-placeholder';
import { Bold, Italic, Underline, Strikethrough, List, ListOrdered, Highlighter, Quote, Link2, Code, CodeXml, MoreHorizontal, Undo2, Redo2, RemoveFormatting, Minus, IndentIncrease, IndentDecrease, Type, X } from 'lucide-react';
import { translateText, message, getLanguage } from './i18n';
import { documentText, plainDocument, safeLink } from './chatFormatting';
import useModalFocus from './useModalFocus';

const ChatTextStyle = TextStyle.extend({
  parseHTML() { return [{ tag: 'span[data-chat-color]' }, { tag: 'span[data-chat-size]' }]; },
  addAttributes() {
    return {
      color: { default: null, parseHTML: element => ['purple', 'red', 'green', 'blue'].includes(element.dataset.chatColor) ? element.dataset.chatColor : null,
        renderHTML: attrs => attrs.color ? { 'data-chat-color': attrs.color } : {} },
      fontSize: { default: null, parseHTML: element => ['normal', 'large', 'larger'].includes(element.dataset.chatSize) ? element.dataset.chatSize : null,
        renderHTML: attrs => attrs.fontSize ? { 'data-chat-size': attrs.fontSize } : {} },
    };
  },
});
const TextLimit = Extension.create({
  name: 'chatTextLimit',
  addProseMirrorPlugins() {
    return [new Plugin({ filterTransaction: transaction => !transaction.docChanged || [...documentText(transaction.doc.toJSON())].length <= 5000 })];
  },
});

function LinkDialog({ initial, onSave, onClose }) {
  const [url, setUrl] = useState(initial.url || 'https://');
  const [text, setText] = useState(initial.text || '');
  const [error, setError] = useState(null);
  const ref = useModalFocus(true, onClose);
  return createPortal(<div className="chat-page chat-link-backdrop"><section ref={ref} role="dialog" aria-modal="true" aria-labelledby="chat-link-title" className="chat-link-dialog" tabIndex={-1}>
    <h3 id="chat-link-title">{translateText('Inserir ou editar link')}</h3>
    <form onSubmit={event => { event.preventDefault(); event.stopPropagation(); const href = safeLink(url); if (!href) { setError({message:'Use um endereço completo iniciado por https:// ou http://.',field:'url'}); return; } const problem = onSave({ href, text: text || href }); if (problem) setError({message:problem,field:'text'}); }}>
      <label htmlFor="chat-link-url">{translateText('Endereço do link')}<input id="chat-link-url" type="url" required maxLength={2048} value={url} onChange={event => setUrl(event.target.value)} autoComplete="url" aria-invalid={error?.field === 'url'} aria-describedby={error?.field === 'url' ? 'chat-link-error' : undefined} /></label>
      <label htmlFor="chat-link-text">{translateText('Texto do link')}<input id="chat-link-text" aria-invalid={error?.field === 'text'} aria-describedby={error?.field === 'text' ? 'chat-link-error' : undefined} maxLength={5000} value={text} onChange={event => setText(event.target.value)} /></label>
      {error && <p role="alert" className="chat-error" id="chat-link-error">{translateText(error.message)}</p>}
      <footer><button type="button" onClick={onClose}>{translateText('Cancelar')}</button>{initial.url && <button type="button" onClick={() => onSave({ remove: true })}>{translateText('Remover link')}</button>}<button type="submit">{translateText('Aplicar link')}</button></footer>
    </form>
  </section></div>, document.body);
}

const ChatRichEditor = forwardRef(function ChatRichEditor({ id, value = '', format = null, onChange, disabled, onSend, describedBy, invalid, label, labelledBy, autoFocus = false, onEscape }, ref) {
  const language = getLanguage();
  const callbacks = useRef({ onChange, onSend, onEscape });
  callbacks.current = { onChange, onSend, onEscape };
  const [expanded, setExpanded] = useState(Boolean(format));
  const [link, setLink] = useState(null);
  const pendingLink = useRef(null);
  const extras = useRef(null);
  const editorRef = useRef(null);
  const editor = useEditor({
    extensions: [
      StarterKit.configure({ heading: { levels: [1, 2, 3] }, trailingNode: false, link: { openOnClick: false, autolink: false, linkOnPaste: true, protocols: ['http', 'https'], isAllowedUri: url => Boolean(safeLink(url)) } }),
      ChatTextStyle, Highlight, TextLimit, Placeholder.configure({ placeholder: () => translateText('Escreva uma mensagem') }),
    ],
    content: format || plainDocument(value),
    immediatelyRender: false,
    editorProps: {
      attributes: { id, role: 'textbox', 'aria-multiline': 'true', 'aria-label': translateText(label), 'aria-labelledby': labelledBy, 'aria-describedby': describedBy, 'aria-invalid': String(Boolean(invalid)), translate: 'no', class: 'chat-rich-content' },
      handleKeyDown: (view, event) => {
        if (event.key === 'Escape' && callbacks.current.onEscape) { event.preventDefault(); callbacks.current.onEscape(); return true; }
        if (event.key !== 'Enter' || event.isComposing || event.keyCode === 229) return false;
        if (event.ctrlKey && event.shiftKey && !event.altKey && !event.metaKey) {
          event.preventDefault();
          editorRef.current?.commands.first(({ commands }) => [() => commands.newlineInCode(), () => commands.splitListItem('listItem'), () => commands.setHardBreak()]);
          return true;
        }
        if (callbacks.current.onSend && !event.ctrlKey && !event.shiftKey && !event.altKey && !event.metaKey) {
          event.preventDefault(); if (!event.repeat) view.dom.closest('form')?.requestSubmit(); return true;
        }
        return false;
      },
    },
    onUpdate: ({ editor }) => callbacks.current.onChange({ text: documentText(editor.getJSON()), format: editor.getJSON() }),
  });
  editorRef.current = editor;
  useEditorState({ editor, selector: ({ editor }) => editor?.state });
  useImperativeHandle(ref, () => ({ focus: () => { if (editor && !editor.isDestroyed) editor.view.focus(); }, getJSON: () => editor?.getJSON() }), [editor]);
  useEffect(() => { editor?.setEditable(!disabled); }, [editor, disabled]);
  useEffect(() => {
    if (!editor) return;
    editor.setOptions({ editorProps: { ...editor.options.editorProps, attributes: { ...editor.options.editorProps.attributes, 'aria-invalid': String(Boolean(invalid)), 'aria-describedby': describedBy, 'aria-label': translateText(label), 'aria-labelledby': labelledBy } } });
    editor.view.dispatch(editor.state.tr.setMeta('addToHistory', false));
  }, [editor, invalid, describedBy, label, labelledBy, language]);
  useLayoutEffect(() => {
    const desired = format || plainDocument(value);
    if (editor && JSON.stringify(editor.getJSON()) !== JSON.stringify(desired)) editor.commands.setContent(desired, { emitUpdate: false });
  }, [editor, value, format]);
  useLayoutEffect(() => {
    if (editor && autoFocus) { editor.commands.setTextSelection(Math.max(1, editor.state.doc.content.size - 1)); editor.view.focus(); }
  }, [editor, autoFocus]);
  useEffect(() => {
    if (link || !pendingLink.current || !editor) return;
    const action = pendingLink.current; pendingLink.current = null;
    const chain = editor.chain().focus().setTextSelection(action.selection);
    if (action.remove) chain.unsetLink().run();
    else if (action.selection.from !== action.selection.to && action.text === editor.state.doc.textBetween(action.selection.from, action.selection.to, ' ')) chain.setLink({ href: action.href }).run();
    else chain.insertContent({ type: 'text', text: action.text, marks: [...(editor.state.storedMarks || editor.state.selection.$from.marks()).filter(mark => mark.type.name !== 'link').map(mark => mark.toJSON()), { type: 'link', attrs: { href: action.href } }] }).run();
  }, [link, editor]);
  if (!editor) return <div className="chat-editor-loading" role="status">Carregando editor...</div>;
  const run = command => { editor.view.focus(); command(editor.chain()).run(); if (extras.current) extras.current.open = false; };
  const tool = (name, Icon, command, active = false, unavailable = false) => <button key={name} type="button" title={translateText(name)} aria-label={translateText(name)} aria-pressed={active} disabled={disabled || unavailable} onMouseDown={event => event.preventDefault()} onClick={() => run(command)}><Icon size={16} aria-hidden="true" /></button>;
  const showLink = event => {
    editor.commands.extendMarkRange('link');
    const { from, to } = editor.state.selection;
    event.currentTarget.focus();
    setLink({ selection: { from, to }, url: editor.getAttributes('link').href || '', text: editor.state.doc.textBetween(from, to, ' ') });
  };
  return <div className="chat-editor-slot">
    <button className="chat-format-toggle" type="button" disabled={disabled} aria-expanded={expanded} aria-controls={`${id}-formatting`} onClick={() => setExpanded(current => !current)}><Type size={15} aria-hidden="true" />{expanded ? 'Ocultar formatação' : 'Formatar texto'}</button>
    {expanded && <div id={`${id}-formatting`} className="chat-format-toolbar" role="group" aria-label={translateText('Formatação da mensagem')}>
      {tool('Negrito', Bold, chain => chain.toggleBold(), editor.isActive('bold'))}
      {tool('Itálico', Italic, chain => chain.toggleItalic(), editor.isActive('italic'))}
      {tool('Sublinhado', Underline, chain => chain.toggleUnderline(), editor.isActive('underline'))}
      {tool('Tachado', Strikethrough, chain => chain.toggleStrike(), editor.isActive('strike'))}
      <span className="chat-format-divider" aria-hidden="true" />
      {tool('Lista com marcadores', List, chain => chain.toggleBulletList(), editor.isActive('bulletList'))}
      {tool('Lista numerada', ListOrdered, chain => chain.toggleOrderedList(), editor.isActive('orderedList'))}
      {tool('Destacar texto', Highlighter, chain => chain.toggleHighlight(), editor.isActive('highlight'))}
      <label>Cor<select aria-label={translateText('Cor do texto')} disabled={disabled} value={editor.getAttributes('textStyle').color || ''} onChange={event => run(chain => chain.setMark('textStyle', { color: event.target.value || null }))}><option value="">Automática</option><option value="purple">Roxo</option><option value="red">Vermelho</option><option value="green">Verde</option><option value="blue">Azul</option></select></label>
      <label>Tamanho<select aria-label={translateText('Tamanho do texto')} disabled={disabled} value={editor.getAttributes('textStyle').fontSize || 'normal'} onChange={event => run(chain => chain.setMark('textStyle', { fontSize: event.target.value }))}><option value="normal">Normal</option><option value="large">Grande</option><option value="larger">Maior</option></select></label>
      {tool('Citação', Quote, chain => chain.toggleBlockquote(), editor.isActive('blockquote'))}
      <button type="button" title={translateText('Inserir ou editar link')} aria-label={translateText('Inserir ou editar link')} disabled={disabled} onMouseDown={event => event.preventDefault()} onClick={showLink}><Link2 size={16} aria-hidden="true" /></button>
      {tool('Bloco de código', CodeXml, chain => chain.toggleCodeBlock(), editor.isActive('codeBlock'))}
      <details ref={extras} className="chat-format-extras" onKeyDown={event => { if (event.key === 'Escape') { event.preventDefault(); event.currentTarget.open = false; event.currentTarget.querySelector('summary')?.focus(); } }}><summary aria-label={translateText('Mais opções de formatação')} title={translateText('Mais opções de formatação')}><MoreHorizontal size={17} aria-hidden="true" /></summary><div>
        <button type="button" disabled={disabled || !editor.can().undo()} onClick={() => run(chain => chain.undo())}><Undo2 size={15} aria-hidden="true" />Desfazer</button>
        <button type="button" disabled={disabled || !editor.can().redo()} onClick={() => run(chain => chain.redo())}><Redo2 size={15} aria-hidden="true" />Refazer</button>
        <button type="button" disabled={disabled} onClick={() => run(chain => chain.unsetAllMarks().clearNodes())}><RemoveFormatting size={15} aria-hidden="true" />Limpar formatação</button>
        <button type="button" disabled={disabled} onClick={() => run(chain => chain.toggleCode())}><Code size={15} aria-hidden="true" />Código em linha</button>
        <button type="button" disabled={disabled} onClick={() => run(chain => chain.setHorizontalRule())}><Minus size={15} aria-hidden="true" />Linha divisória</button>
        <button type="button" disabled={disabled || !editor.can().sinkListItem('listItem')} onClick={() => run(chain => chain.sinkListItem('listItem'))}><IndentIncrease size={15} aria-hidden="true" />Aumentar recuo da lista</button>
        <button type="button" disabled={disabled || !editor.can().liftListItem('listItem')} onClick={() => run(chain => chain.liftListItem('listItem'))}><IndentDecrease size={15} aria-hidden="true" />Diminuir recuo da lista</button>
      </div></details>
    </div>}
    <EditorContent editor={editor} />
    {(expanded || [...documentText(editor.getJSON())].length > 4500) && <small className="chat-character-count" translate="no">{message('{count} de 5.000 caracteres', { count: [...documentText(editor.getJSON())].length })}</small>}
    {link && <LinkDialog initial={link} onClose={() => setLink(null)} onSave={action => { if (!action.remove && [...documentText(editor.getJSON())].length - [...editor.state.doc.textBetween(link.selection.from, link.selection.to, '\n', '\n')].length + [...action.text].length > 5000) return 'Use no máximo 5.000 caracteres na mensagem.'; pendingLink.current = { ...action, selection: link.selection }; setLink(null); }} />}
  </div>;
});
export default ChatRichEditor;
