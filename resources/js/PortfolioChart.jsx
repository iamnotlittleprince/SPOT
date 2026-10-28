import React from 'react';
import { getFormatLocale } from './i18n';

export const chartTypes = [
  ['bars', 'Barras horizontais'], ['columns', 'Colunas'], ['line', 'Linhas'],
  ['area', 'Área'], ['pie', 'Pizza'], ['donut', 'Rosca'], ['radar', 'Radar'],
  ['treemap', 'Mapa de árvore'], ['funnel', 'Funil'], ['cards', 'Cartões'], ['table', 'Tabela'],
];
const palettes = {
  blue: ['#168ff0', '#26b8b0', '#7c68d8', '#efa63c', '#df617a', '#557b94'],
  green: ['#168573', '#49b990', '#86cba4', '#b8dd91', '#637f41', '#d5b956'],
  purple: ['#7957cf', '#ae76d8', '#d18fca', '#6587d4', '#a4b6e8', '#d66e9b'],
};

export default function PortfolioChart({ items, type, unit = '', palette = 'blue', labels = true, title }) {
  const colors = palettes[palette] || palettes.blue;
  const format = value => Number(value).toLocaleString(getFormatLocale(), unit === 'money'
    ? { style: 'currency', currency: 'BRL', maximumFractionDigits: 2 }
    : { maximumFractionDigits: 2 }) + (unit === 'hours' ? ' h' : unit === 'percent' ? '%' : '');
  const data = (items || []).filter(item => item.value != null && Number.isFinite(Number(item.value))).map(item => ({ ...item, value: Number(item.value) }));
  if (!data.length) return <p className="analytics-empty">Sem dados disponíveis para este levantamento.</p>;
  if (type === 'table') return <div className="portfolio-table-scroll"><table className="portfolio-data-table"><thead><tr><th>Indicador</th><th>Valor</th></tr></thead><tbody>{data.map((item, index) => <tr key={index}><td>{item.label}</td><td>{format(item.value)}</td></tr>)}</tbody></table></div>;
  if (type === 'cards') return <div className="portfolio-value-cards">{data.map((item, index) => <div key={index}><span>{item.label}</span><strong style={{ color: colors[index % colors.length] }}>{format(item.value)}</strong></div>)}</div>;
  if (['pie', 'donut', 'treemap', 'funnel', 'radar'].includes(type) && data.some(item => item.value < 0)) return <div><p className="chart-caption">Esta visualização exige valores positivos. Use barras, linhas ou tabela para mostrar prejuízos.</p><PortfolioChart {...{ items: data, unit, title, palette, labels }} type="table" /></div>;
  const magnitude = Math.max(...data.map(item => Math.abs(item.value)), 1);
  const total = data.reduce((sum, item) => sum + item.value, 0);
  const legend = <div className="portfolio-chart-legend">{data.map((item, index) => <span key={index}><i style={{ background: colors[index % colors.length] }} />{item.label}{labels && <strong>{format(item.value)}</strong>}</span>)}</div>;
  const tip = item => `${item.label}: ${format(item.value)}`;
  if (['pie', 'donut'].includes(type)) {
    if (total <= 0) return <p className="analytics-empty">Sem valores positivos para compor o gráfico.</p>;
    let cursor = 0;
    const gradient = data.map((item, index) => { const start = cursor; cursor += item.value / total * 100; return `${colors[index % colors.length]} ${start}% ${cursor}%`; }).join(',');
    return <div className="portfolio-share-chart"><div role="img" aria-label={title} className={`portfolio-pie ${type}`} style={{ background: `conic-gradient(${gradient})` }}>{type === 'donut' && <span>{format(total)}</span>}</div>{legend}</div>;
  }
  if (type === 'treemap') return <><div className="portfolio-treemap" role="img" aria-label={title}>{data.filter(item => item.value > 0).map((item, index) => <div key={index} title={tip(item)} style={{ flexGrow: item.value / (total || 1) * 100, background: colors[index % colors.length] }}><span>{item.label}</span>{labels && <strong>{format(item.value)}</strong>}</div>)}</div>{total === 0 && <p className="analytics-empty">Sem valores positivos para compor o gráfico.</p>}</>;
  if (type === 'funnel') return <div className="portfolio-funnel">{data.map((item, index) => <div key={index} title={tip(item)} style={{ width: `${Math.max(item.value / magnitude * 100, 8)}%`, background: colors[index % colors.length] }}><span>{item.label}</span>{labels && <strong>{format(item.value)}</strong>}</div>)}</div>;
  if (type === 'bars') return <div className="portfolio-signed-bars">{data.map((item, index) => <div key={index}><span>{item.label}</span><div className="portfolio-bar-track"><i title={tip(item)} style={{ width: `${Math.abs(item.value) / magnitude * 50}%`, left: item.value < 0 ? `${50 - Math.abs(item.value) / magnitude * 50}%` : '50%', background: item.value < 0 ? '#d94b64' : colors[index % colors.length] }} /></div>{labels && <strong>{format(item.value)}</strong>}</div>)}</div>;
  if (type === 'radar') {
    if (data.length < 3) return <p className="analytics-empty">O radar precisa de pelo menos três indicadores.</p>;
    const point = (i, scale) => { const angle = i / data.length * Math.PI * 2 - Math.PI / 2; return [280 + Math.cos(angle) * 100 * scale, 140 + Math.sin(angle) * 100 * scale]; };
    return <><svg viewBox="0 0 560 280" role="img" aria-label={title}>{[0.25, 0.5, 0.75, 1].map(scale => <polygon key={scale} points={data.map((_, i) => point(i, scale).join(',')).join(' ')} fill="none" stroke="currentColor" opacity=".15" />)}<polygon points={data.map((item, i) => point(i, item.value / magnitude).join(',')).join(' ')} fill={colors[0]} fillOpacity=".2" stroke={colors[0]} />{data.map((item, i) => { const [x, y] = point(i, 1.2); return <text key={i} x={x} y={y} textAnchor="middle" fontSize="10" fill="currentColor">{item.label.slice(0, 22)}</text>; })}</svg>{legend}</>;
  }
  const min = Math.min(0, ...data.map(item => item.value));
  const max = Math.max(0, ...data.map(item => item.value));
  const y = value => 210 - (value - min) / (max - min || 1) * 175;
  const x = index => 45 + (index + 0.5) / data.length * 470;
  const points = data.map((item, index) => `${x(index)},${y(item.value)}`).join(' ');
  return <><svg viewBox="0 0 560 260" role="img" aria-label={title} className="portfolio-cartesian"><line x1="45" x2="530" y1={y(0)} y2={y(0)} stroke="currentColor" opacity=".3" />{[min, max].map((v, i) => <text key={i} x="5" y={y(v)} fill="currentColor" fontSize="9">{Number(v).toLocaleString(getFormatLocale(), { notation: 'compact' })}</text>)}{type === 'area' && <polygon points={`${x(0)},${y(0)} ${points} ${x(data.length - 1)},${y(0)}`} fill={colors[0]} fillOpacity=".18" />}{['line', 'area'].includes(type) && <polyline points={points} fill="none" stroke={colors[0]} strokeWidth="3" />}{data.map((item, index) => <g key={index}><title>{tip(item)}</title>{type === 'columns' ? <rect x={x(index) - 140 / data.length} y={Math.min(y(0), y(item.value))} width={280 / data.length} height={Math.abs(y(item.value) - y(0))} fill={item.value < 0 ? '#d94b64' : colors[index % colors.length]} /> : <circle cx={x(index)} cy={y(item.value)} r="4" fill={colors[0]} />}{labels && <text x={x(index)} y={y(item.value) - 8} textAnchor="middle" fill="currentColor" fontSize="9">{format(item.value)}</text>}<text x={x(index)} y="237" textAnchor="middle" fill="currentColor" fontSize="9">{item.label.slice(0, Math.max(4, Math.floor(65 / data.length)))}</text></g>)}</svg>{legend}</>;
}
