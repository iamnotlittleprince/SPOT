import React, { useState } from 'react';
import { Search, Users, X } from 'lucide-react';
import { message } from './i18n';
import '../css/project-team.css';

const normalize = value => String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');

export default function ProjectTeamPicker({ users, selected, onChange, disabled, loading }) {
  const [search, setSearch] = useState('');
  const term = normalize(search.trim());
  const visible = users.filter(user => normalize(`${user.name} ${user.email}`).includes(term));
  const remove = id => onChange(selected.filter(value => value !== id));
  return <fieldset className="new-project-team" disabled={disabled} aria-describedby="project-team-help">
    <legend><Users size={18} aria-hidden="true" /> Equipe do projeto</legend>
    <p id="project-team-help">Selecione os usuários que irão trabalhar neste projeto. Você poderá ajustar a equipe depois em Equipes.</p>
    <label className="new-project-team-search"><span>Buscar usuários</span><div><Search size={18} aria-hidden="true" /><input type="search" value={search} placeholder="Buscar por nome ou e-mail" onChange={event => setSearch(event.target.value)} /></div></label>
    <p className="new-project-team-count" role="status" aria-atomic="true">{message('Selecionados: {count}', { count: selected.length })}</p>
    {selected.length > 0 && <div className="new-project-team-selected">{users.filter(user => selected.includes(user.id)).map(user => <button type="button" key={user.id} aria-label={message('Remover {name} da equipe', {name:user.name})} onClick={() => remove(user.id)}><span translate="no">{user.name}</span><X size={16} aria-hidden="true" /></button>)}</div>}
    <div className="new-project-team-list">
      {loading ? <p>Carregando usuários...</p> : visible.length ? visible.map(user => <label key={user.id}><input type="checkbox" checked={selected.includes(user.id)} onChange={event => event.target.checked ? onChange([...selected, user.id]) : remove(user.id)} /><span translate="no"><strong>{user.name}</strong><small>{user.email}</small></span></label>) : <p>{users.length ? 'Nenhum usuário encontrado para esta busca.' : 'Não há usuários disponíveis para adicionar.'}</p>}
    </div>
  </fieldset>;
}
