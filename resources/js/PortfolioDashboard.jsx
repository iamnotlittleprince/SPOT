import React, { useState } from 'react';
import PortfolioChart, { chartTypes } from './PortfolioChart';
import { buildReports, groupProjects, portfolioDimensions, portfolioMetrics } from './portfolioReports';

export default function PortfolioDashboard({ project, financial, portfolio, Ring }) {
  const storageKey = `spot.portfolio.dashboard.${project?.id || 'overview'}`;
  const [layout, setLayout] = useState(() => {
    try { const saved = JSON.parse(localStorage.getItem(storageKey)); return saved && typeof saved === 'object' && !Array.isArray(saved) ? saved : {}; } catch { return {}; }
  });
  const [editing, setEditing] = useState(false);
  const [notice, setNotice] = useState('');
  const [search, setSearch] = useState('');
  const definitions = buildReports(project, financial, portfolio);
  if (portfolio) {
    const custom = layout.custom || {};
    const metric = portfolioMetrics.find(([id]) => id === custom.metric) || portfolioMetrics[0];
    definitions.push({ id: 'custom', title: 'Levantamento personalizado', unit: metric[2], visible: false, items: groupProjects(portfolio.projects || [], custom.dimension || 'client', metric[0], metric[0] === 'count' ? 'sum' : custom.aggregation || (metric[0] === 'progress' ? 'average' : 'sum')) });
  }
  function save(next) {
    setLayout(next);
    try { localStorage.setItem(storageKey, JSON.stringify(next)); setNotice('Painel salvo neste navegador.'); }
    catch { setNotice('Não foi possível salvar as preferências neste navegador.'); }
  }
  function update(id, changes) { save({ ...layout, [id]: { ...layout[id], ...changes } }); }
  const visible = item => layout[item.id]?.visible ?? item.visible;
  const ordered = [...definitions].sort((a, b) => (layout[a.id]?.order ?? definitions.indexOf(a)) - (layout[b.id]?.order ?? definitions.indexOf(b)));
  function move(id, direction) {
    const shown = ordered.filter(visible);
    const shownIndex = shown.findIndex(item => item.id === id);
    const neighbor = shown[shownIndex + direction];
    if (!neighbor) return;
    const index = ordered.findIndex(item => item.id === id);
    const target = ordered.findIndex(item => item.id === neighbor.id);
    [ordered[index], ordered[target]] = [ordered[target], ordered[index]];
    const next = { ...layout };
    ordered.forEach((item, order) => { next[item.id] = { ...next[item.id], order }; });
    save(next);
  }
  function exportCsv(item, items) {
    const quote = value => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const csv = '\uFEFF' + [['Indicador', 'Valor', 'Unidade'], ...items.map(entry => [/^[=+@\-]/.test(String(entry.label)) ? `'${entry.label}` : entry.label, entry.value, item.unit])].map(row => row.map(quote).join(';')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a'); link.href = url; link.download = `${item.id}.csv`; link.click(); URL.revokeObjectURL(url);
  }
  return <section className="portfolio-builder" aria-label={portfolio ? 'Dashboard do portfólio' : 'Dashboard do projeto'}>
    <header className="portfolio-builder-heading"><div><h3>{portfolio ? 'Dashboard do portfólio' : 'Dashboard do projeto'}</h3><p>Escolha os indicadores e a visualização de cada gráfico.</p></div><button type="button" aria-expanded={editing} onClick={() => setEditing(!editing)}>{editing ? 'Concluir edição' : 'Personalizar painel'}</button></header>
    {editing && <fieldset className="portfolio-widget-picker"><legend>Indicadores do painel</legend><div className="portfolio-catalog-actions"><input aria-label="Buscar levantamento" placeholder="Buscar levantamento" value={search} onChange={event => setSearch(event.target.value)} /><button type="button" onClick={() => save(Object.fromEntries([...Object.entries(layout), ...definitions.map(item => [item.id, { ...layout[item.id], visible: true }])]))}>Mostrar todos</button><button type="button" onClick={() => save(Object.fromEntries([...Object.entries(layout), ...definitions.map(item => [item.id, { ...layout[item.id], visible: false }])]))}>Ocultar todos</button><button type="button" onClick={() => save({})}>Restaurar padrão</button></div>{definitions.filter(item => item.title.toLocaleLowerCase().includes(search.toLocaleLowerCase())).map(item => <label key={item.id}><input type="checkbox" checked={Boolean(visible(item))} onChange={event => update(item.id, { visible: event.target.checked })} />{item.title}</label>)}</fieldset>}
    {notice && <p className="chart-caption" role="status">{notice}</p>}
    <div className="portfolio-widget-grid">{ordered.filter(visible).map(item => {
      const config = layout[item.id] || {};
      let items = item.items && [...item.items];
      if (items && config.sort === 'alphabetical') items.sort((a, b) => String(a.label).localeCompare(String(b.label)));
      if (items && config.sort === 'ascending') items.sort((a, b) => a.value - b.value);
      if (items && config.sort === 'descending') items.sort((a, b) => b.value - a.value);
      if (items && config.limit) items = items.slice(0, Number(config.limit));
      const type = config.type || (item.id === 'execution' ? 'ring' : item.id === 'monthly' ? 'line' : 'bars');
      const ringAvailable = item.unit === 'percent' && items?.length === 1;
      return <article className={`analytics-panel portfolio-widget ${config.wide ? 'portfolio-widget-wide' : ''}`} key={item.id}>
        <header><h3>{item.title}</h3><button type="button" className="portfolio-export" disabled={!items?.length} onClick={() => exportCsv(item, items)}>Exportar CSV</button></header>
        {editing && <div className="portfolio-chart-controls">
          {item.id === 'custom' && <><label>Agrupar por<select aria-label="Agrupar por" value={config.dimension || 'client'} onChange={event => update(item.id, { dimension: event.target.value })}>{portfolioDimensions.map(([id, label]) => <option value={id} key={id}>{label}</option>)}</select></label><label>Medida<select aria-label="Medida" value={config.metric || 'count'} onChange={event => update(item.id, { metric: event.target.value })}>{portfolioMetrics.map(([id, label]) => <option value={id} key={id}>{label}</option>)}</select></label><label>Agregação<select aria-label="Agregação" disabled={!config.metric || config.metric === 'count'} value={config.aggregation || (config.metric === 'progress' ? 'average' : 'sum')} onChange={event => update(item.id, { aggregation: event.target.value })}><option value="sum">Soma</option><option value="average">Média</option><option value="min">Mínimo</option><option value="max">Máximo</option></select></label></>}
          <label>Visualização<select aria-label={`Visualização: ${item.title}`} value={type === 'ring' && !ringAvailable ? 'bars' : type} onChange={event => update(item.id, { type: event.target.value })}>{chartTypes.map(([id, label]) => <option value={id} key={id}>{label}</option>)}{ringAvailable && <option value="ring">Indicador circular</option>}</select></label>
          <label>Ordenação<select aria-label="Ordenação" value={config.sort || 'original'} onChange={event => update(item.id, { sort: event.target.value })}><option value="original">Ordem original</option><option value="alphabetical">Ordem alfabética</option><option value="ascending">Menor para maior</option><option value="descending">Maior para menor</option></select></label>
          <label>Quantidade<select aria-label="Quantidade" value={config.limit || ''} onChange={event => update(item.id, { limit: event.target.value })}><option value="">Todos</option>{[5, 10, 20, 50].map(value => <option key={value} value={value}>{value}</option>)}</select></label>
          <label>Cores<select aria-label="Cores" value={config.palette || 'blue'} onChange={event => update(item.id, { palette: event.target.value })}><option value="blue">Azul</option><option value="green">Verde</option><option value="purple">Roxo</option></select></label>
          <label><input type="checkbox" checked={config.labels !== false} onChange={event => update(item.id, { labels: event.target.checked })} />Mostrar valores</label>
          <label><input type="checkbox" checked={Boolean(config.wide)} onChange={event => update(item.id, { wide: event.target.checked })} />Largura completa</label>
          <div className="portfolio-order-actions"><button type="button" aria-label={`Mover para cima: ${item.title}`} disabled={ordered.filter(visible)[0].id === item.id} onClick={() => move(item.id, -1)}>↑</button><button type="button" aria-label={`Mover para baixo: ${item.title}`} disabled={ordered.filter(visible).at(-1).id === item.id} onClick={() => move(item.id, 1)}>↓</button></div>
        </div>}
        {!items ? <p className="analytics-empty">{financial || portfolio ? 'Sem dados disponíveis para este levantamento.' : 'Aguardando detalhamento financeiro.'}</p> : type === 'ring' && ringAvailable && items[0].value == null ? <p className="analytics-empty">Sem base de cálculo</p> : type === 'ring' && ringAvailable ? <Ring value={items[0].value} label={item.title} detail={items[0].value == null ? 'Sem base de cálculo' : `${Math.round(items[0].value)}%`} /> : <PortfolioChart items={items} unit={item.unit} type={type === 'ring' ? 'bars' : type} palette={config.palette} labels={config.labels !== false} title={item.title} />}
      </article>;
    })}</div>
    {!definitions.some(visible) && <p className="analytics-empty">Selecione um indicador em Personalizar painel.</p>}
  </section>;
}
