const row = (label, value) => ({ label, value: value == null ? null : Number(value) });
const report = (id, title, items, unit = '', visible = false) => ({ id, title, items, unit, visible });
export const portfolioDimensions = [['project', 'Projeto'], ['client', 'Cliente'], ['manager', 'Gerente de projetos'], ['account_manager', 'Gerente de contas'], ['status', 'Situação']];
export const portfolioMetrics = [['count', 'Quantidade de projetos', ''], ['contract_value', 'Valor dos contratos', 'money'], ['actual_revenue', 'Receita', 'money'], ['actual_cost', 'Custos', 'money'], ['profit', 'Lucro', 'money'], ['worked_hours', 'Horas realizadas', 'hours'], ['estimated_hours', 'Horas estimadas', 'hours'], ['progress', 'Progresso', 'percent'], ['unpriced_tasks', 'Atividades sem tarifa', '']];
export function groupProjects(projects, dimension, metric, aggregation) {
  const groups = new Map();
  for (const project of projects) {
    const label = dimension === 'project' ? project.name : project[dimension] || 'Não informado';
    const key = dimension === 'project' ? project.id : label;
    const group = groups.get(key) || { label, values: [] };
    const value = metric === 'count' ? 1 : project[metric];
    if (value != null && Number.isFinite(Number(value))) group.values.push(Number(value));
    groups.set(key, group);
  }
  return [...groups.values()].map(({ label, values }) => row(label, !values.length ? null : aggregation === 'average' ? values.reduce((a, b) => a + b, 0) / values.length : aggregation === 'min' ? Math.min(...values) : aggregation === 'max' ? Math.max(...values) : values.reduce((a, b) => a + b, 0)));
}
export function buildReports(project, financial, portfolio) {
  if (portfolio) {
    const projects = portfolio.projects || [];
    return [
      report('status', 'Situação dos projetos', portfolio.status || [], '', true),
      report('deadlines', 'Situação dos prazos', [row('No prazo', portfolio.summary.on_time), row('Fora do prazo', portfolio.summary.overdue), row('Congelados', portfolio.summary.frozen)]),
      report('monthly', 'Iniciados por mês', portfolio.monthly || [], '', true),
      ...portfolioDimensions.filter(([id]) => id !== 'status').map(([id, title]) => report(`by-${id}`, `Quantidade por ${title.toLowerCase()}`, groupProjects(projects, id, 'count', 'sum'), '', ['client', 'manager', 'account_manager'].includes(id))),
      report('financial', 'Resultado do portfólio', [row('Contratos', portfolio.summary.contract_value), row('Receita', portfolio.summary.revenue), row('Custos', portfolio.summary.cost), row('Lucro', portfolio.summary.profit)], 'money', true),
      ...portfolioMetrics.filter(([id]) => id !== 'count').map(([id, title, unit]) => report(`metric-${id}`, `${title} por projeto`, groupProjects(projects, 'project', id, 'sum'), unit)),
    ];
  }
  const actual = financial?.actual;
  const estimated = financial?.estimated;
  const split = (items, key, hours = false) => items?.map(item => row(item.name, hours ? Number(item[key]) / 60 : item[key]));
  const compare = (a, e) => financial ? [row('Planejado', estimated[e]), row('Realizado', actual[a])] : null;
  return [
    report('financial', 'Visão financeira', [row('Contrato', project.contract_value), row('Receita', project.actual_revenue), row('Custos', project.actual_cost), row('Lucro', project.profit)], 'money', true),
    report('hours', 'Consumo de horas', [row('Estimadas', project.estimated_hours), row('Realizadas', project.worked_hours)], 'hours', true),
    report('execution', 'Execução', [row('Progresso (%)', project.progress)], 'percent', true),
    report('costs', 'Composição dos custos', actual && [row('Impostos', actual.taxes), row('Comissão', actual.commission), row('Mão de obra', actual.labor_cost), row('Despesas', actual.approved_expenses)], 'money', true),
    report('analysts', 'Custo por analista', split(financial?.by_analyst, 'cost'), 'money', true),
    report('activities', 'Horas por atividade', split(financial?.by_activity_type, 'minutes', true), 'hours', true),
    report('analyst-hours', 'Horas por analista', split(financial?.by_analyst, 'minutes', true), 'hours'),
    report('activity-costs', 'Custo por atividade', split(financial?.by_activity_type, 'cost'), 'money'),
    report('taxes', 'Composição dos impostos', actual?.tax_composition?.map(item => row(item.name, item.amount)), 'money'),
    report('expenses', 'Despesas aprovadas', financial?.expenses?.map(item => row(item.description, item.amount)), 'money'),
    report('revenue-sources', 'Composição da receita', actual && [row('Contrato', actual.contract_revenue), row('Horas extras', actual.overtime_revenue)], 'money'),
    ...[['revenue-plan', 'Receita planejada e realizada', 'total_revenue', 'revenue', 'money'], ['profit-plan', 'Lucro planejado e realizado', 'profit', 'profit', 'money'], ['tax-plan', 'Impostos planejados e realizados', 'taxes', 'taxes', 'money'], ['commission-plan', 'Comissão planejada e realizada', 'commission', 'commission', 'money'], ['labor-plan', 'Mão de obra planejada e realizada', 'labor_cost', 'labor_cost', 'money'], ['expenses-plan', 'Despesas planejadas e realizadas', 'approved_expenses', 'additional_cost', 'money'], ['cost-plan', 'Custos operacionais planejados e realizados', 'operating_costs', 'operating_costs', 'money'], ['margin-plan', 'Margem planejada e realizada', 'margin_percent', 'margin_percent', 'percent']].map(([id, title, a, e, unit]) => report(id, title, compare(a, e), unit)),
    report('margin', 'Margem do projeto', [row('Margem', project.actual_revenue > 0 ? project.profit / project.actual_revenue * 100 : null)], 'percent'),
    report('revenue-rate', 'Receita em relação ao contrato', [row('Receita sobre contrato', project.contract_value > 0 ? project.actual_revenue / project.contract_value * 100 : null)], 'percent'),
    report('hours-rate', 'Percentual de horas consumidas', [row('Horas consumidas', project.estimated_hours > 0 ? project.worked_hours / project.estimated_hours * 100 : null)], 'percent'),
    report('budget-rate', 'Percentual do contrato em custos', [row('Contrato consumido', project.contract_value > 0 ? project.actual_cost / project.contract_value * 100 : null)], 'percent'),
    report('pending-costs', 'Atividades com custo pendente', [row('Atividades sem tarifa', project.unpriced_tasks ?? financial?.unpriced_tasks)]),
  ];
}
