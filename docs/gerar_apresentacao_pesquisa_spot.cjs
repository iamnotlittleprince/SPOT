const pptxgen = require(process.env.PPTXGENJS_PATH || 'pptxgenjs');

const pptx = new pptxgen();
pptx.layout = 'LAYOUT_WIDE';
pptx.author = 'Equipe Spot';
pptx.company = 'Computécnica';
pptx.subject = 'Pesquisa, solução e arquitetura do Spot';
pptx.title = 'Spot — da pesquisa à solução';
pptx.lang = 'pt-BR';
pptx.theme = { headFontFace: 'Aptos Display', bodyFontFace: 'Aptos', lang: 'pt-BR' };

const OUT = `${__dirname}/Spot-pesquisa-e-solucao.pptx`;
const LOGO = `${__dirname}/../public/spot-logo.png`;
const S = pptx.ShapeType;
const C = {
  navy: '102A43', blue: '147DAD', cyan: '19B5D1', pale: 'E5F7FA',
  ink: '172B3A', muted: '60758A', white: 'FFFFFF', green: '2E9B69',
  amber: 'E8A23B', purple: '7467D8', red: 'D95D5D', line: 'D7E1EA',
  soft: 'F4F7FA', darkSoft: 'DCE6EE'
};

pptx.defineSlideMaster({
  title: 'MASTER',
  background: { color: 'F6F8FB' },
  objects: [
    { rect: { x: 0, y: 0, w: 13.333, h: 0.08, fill: { color: C.cyan }, line: { color: C.cyan } } },
    { text: { text: 'SPOT  •  PESQUISA E SOLUÇÃO', options: { x: .55, y: 7.13, w: 5.3, h: .16, fontSize: 8, bold: true, color: '758395', charSpacing: 1.2, margin: 0 } } },
  ],
  slideNumber: { x: 12.45, y: 7.1, w: .35, h: .18, fontSize: 8, color: '758395', align: 'right' }
});

function title(slide, kicker, heading, subtitle) {
  slide.addText(kicker.toUpperCase(), { x: .65, y: .4, w: 4.5, h: .2, fontSize: 10, bold: true, color: C.cyan, charSpacing: 1.5, margin: 0 });
  slide.addText(heading, { x: .65, y: .72, w: 11.9, h: .5, fontSize: 27, bold: true, color: C.navy, margin: 0, fit: 'shrink' });
  if (subtitle) slide.addText(subtitle, { x: .66, y: 1.34, w: 11.7, h: .35, fontSize: 13, color: C.muted, margin: 0, fit: 'shrink' });
}
function box(slide, x, y, w, h, fill = C.white, line = C.line) {
  slide.addShape(S.roundRect, { x, y, w, h, rectRadius: .1, fill: { color: fill }, line: { color: line, width: 1 } });
}
function pill(slide, text, x, y, w, fill = C.pale, color = C.blue) {
  slide.addShape(S.roundRect, { x, y, w, h: .3, rectRadius: .15, fill: { color: fill }, line: { color: fill } });
  slide.addText(text, { x: x + .08, y: y + .06, w: w - .16, h: .15, fontSize: 9, bold: true, color, align: 'center', margin: 0, fit: 'shrink' });
}
function metric(slide, x, y, w, value, label, color) {
  box(slide, x, y, w, 1.38);
  slide.addShape(S.rect, { x, y, w: .08, h: 1.38, fill: { color }, line: { color } });
  slide.addText(value, { x: x + .25, y: y + .18, w: w - .4, h: .46, fontSize: 27, bold: true, color: C.navy, margin: 0, fit: 'shrink' });
  slide.addText(label, { x: x + .25, y: y + .76, w: w - .4, h: .38, fontSize: 11.5, color: C.muted, margin: 0, fit: 'shrink' });
}
function bullets(slide, items, x, y, w, h, size = 14, color = C.ink) {
  const runs = [];
  items.forEach((item, i) => runs.push({ text: item, options: { bullet: { indent: 15 }, hanging: 4, breakLine: i < items.length - 1 } }));
  slide.addText(runs, { x, y, w, h, fontSize: size, color, margin: .03, paraSpaceAfterPt: 12, fit: 'shrink', valign: 'mid' });
}
function notes(slide, lines) { if (slide.addNotes) slide.addNotes(lines); }

// 1 — Capa
{
  const s = pptx.addSlide();
  s.background = { color: C.navy };
  s.addShape(S.rect, { x: 9.15, y: 0, w: 4.2, h: 7.5, fill: { color: C.blue }, line: { color: C.blue } });
  s.addShape(S.ellipse, { x: 10.0, y: .75, w: 2.4, h: 2.4, fill: { color: C.cyan, transparency: 18 }, line: { color: C.cyan, transparency: 100 } });
  s.addShape(S.ellipse, { x: 8.65, y: 4.45, w: 3.2, h: 3.2, fill: { color: C.cyan, transparency: 48 }, line: { color: C.cyan, transparency: 100 } });
  s.addImage({ path: LOGO, x: .73, y: .62, w: 1.3, h: .87, sizing: 'contain' });
  pill(s, 'PESQUISA  →  PRODUTO', .74, 1.62, 2.28, C.cyan, C.navy);
  s.addText('Spot', { x: .72, y: 2.18, w: 6.7, h: .82, fontSize: 48, bold: true, color: C.white, margin: 0 });
  s.addText('Da dor do mercado a uma solução própria para gestão de projetos.', { x: .76, y: 3.18, w: 7.15, h: 1.05, fontSize: 24, color: 'D8E8F2', margin: 0, fit: 'shrink' });
  s.addText('Computécnica  •  apresentação de 15 minutos', { x: .76, y: 6.45, w: 5.5, h: .28, fontSize: 12, color: 'A9C0D1', margin: 0 });
  notes(s, ['Tempo: 20 segundos.', 'Abertura: “O Spot nasceu de um problema real da Computécnica e foi orientado pelos resultados de uma pesquisa com o mercado.”']);
}

// 2 — Evidências
{
  const s = pptx.addSlide('MASTER');
  title(s, 'Pesquisa', 'O problema é recorrente — e afeta o negócio', 'Os resultados mostram uma lacuna entre reconhecer a importância da gestão e possuir uma solução adequada.');
  metric(s, .67, 2.0, 3.58, '63,8%', 'já enfrentaram dificuldade de organização em projetos', C.red);
  metric(s, 4.55, 2.0, 3.58, '88,3%', 'reconhecem impacto direto no faturamento', C.green);
  metric(s, 8.43, 2.0, 3.58, '63,1%', 'não possuem sistema especializado de gestão', C.blue);
  box(s, .67, 3.78, 11.34, 1.82, C.navy, C.navy);
  s.addText('Mesmo entre quem já usa software, a satisfação é apenas moderada.', { x: 1.02, y: 4.12, w: 10.65, h: .38, fontSize: 21, bold: true, color: C.white, align: 'center', margin: 0, fit: 'shrink' });
  s.addText('A oportunidade não é apenas digitalizar: é substituir ferramentas ruins ou inadequadas.', { x: 1.1, y: 4.78, w: 10.48, h: .3, fontSize: 14, color: C.cyan, align: 'center', margin: 0 });
  pill(s, 'DOR PRINCIPAL: ORGANIZAÇÃO', 4.27, 6.03, 4.18, C.pale, C.blue);
  notes(s, ['Tempo: 50 segundos.', 'Destaque os três percentuais, sem explicar metodologia se ninguém perguntar.', 'Transição: “A pergunta aberta mostrou como essa solução deveria ser.”']);
}

// 3 — O que o mercado pede
{
  const s = pptx.addSlide('MASTER');
  title(s, 'Expectativa', 'A prioridade é simplicidade com visão de negócio', 'Na pergunta aberta, usabilidade apareceu à frente de recursos técnicos avançados.');
  box(s, .68, 1.95, 4.0, 4.35, C.navy, C.navy);
  s.addText('12 respostas', { x: 1.02, y: 2.45, w: 3.3, h: .6, fontSize: 31, bold: true, color: C.cyan, align: 'center', margin: 0 });
  s.addText('citaram interface intuitiva e fácil de usar', { x: 1.05, y: 3.35, w: 3.25, h: .85, fontSize: 20, bold: true, color: C.white, align: 'center', margin: 0, fit: 'shrink' });
  s.addText('Usabilidade não é acabamento; é requisito central do produto.', { x: 1.15, y: 5.05, w: 3.05, h: .6, fontSize: 13, color: 'C6DBE8', align: 'center', margin: 0, fit: 'shrink' });
  const asks = [
    ['Integração', 'Microsoft 365 e ferramentas já adotadas', C.blue],
    ['Visibilidade', 'Dashboards e indicadores em tempo real', C.cyan],
    ['Financeiro', 'Custos, horas e comparação planejado × realizado', C.green],
    ['Automação', 'IA como evolução, em menor escala', C.purple]
  ];
  asks.forEach((a, i) => {
    const y = 2.02 + i * 1.08;
    s.addShape(S.ellipse, { x: 5.18, y: y + .04, w: .46, h: .46, fill: { color: a[2] }, line: { color: a[2] } });
    s.addText(String(i + 1), { x: 5.29, y: y + .15, w: .24, h: .17, fontSize: 11, bold: true, color: C.white, align: 'center', margin: 0 });
    s.addText(a[0], { x: 5.9, y, w: 1.75, h: .26, fontSize: 17, bold: true, color: C.navy, margin: 0 });
    s.addText(a[1], { x: 7.65, y: y + .01, w: 4.25, h: .35, fontSize: 12.5, color: C.muted, margin: 0, fit: 'shrink' });
    if (i < 3) s.addShape(S.line, { x: 5.9, y: y + .72, w: 5.9, h: 0, line: { color: C.line, width: 1 } });
  });
  pill(s, 'SIMPLES  •  VISUAL  •  INTEGRADO  •  FINANCEIRO', 6.15, 6.12, 5.35, C.pale, C.blue);
  notes(s, ['Tempo: 55 segundos.', 'Frase-chave: “O mercado não pediu mais um sistema; pediu um sistema simples, visual, integrado e com controle financeiro.”']);
}

// 4 — Contexto Computécnica
{
  const s = pptx.addSlide('MASTER');
  title(s, 'Contexto', 'Um problema real da Computécnica', 'O projeto substitui uma ferramenta antiga por uma solução própria, moderna e sem licenciamento externo.');
  const cols = [
    ['PROBLEMA', ['Telas pouco integradas', 'Gráficos pouco interativos', 'Sem acesso por cargo', 'Custos e mão de obra difíceis de acompanhar'], C.red],
    ['OBJETIVO', ['Cadastrar e organizar projetos', 'Planejar horas e finanças', 'Registrar despesas e execução', 'Comparar planejado × realizado'], C.blue],
    ['JUSTIFICATIVA', ['Atacar uma necessidade interna real', 'Melhorar comunicação entre áreas', 'Dar previsibilidade financeira', 'Elevar satisfação e controle'], C.green]
  ];
  cols.forEach((c, i) => {
    const x = .68 + i * 4.1;
    box(s, x, 2.0, 3.68, 4.12);
    pill(s, c[0], x + .25, 2.3, 1.58, c[2], C.white);
    bullets(s, c[1], x + .28, 3.0, 3.05, 2.55, 13.5);
  });
  notes(s, ['Tempo: 55 segundos.', 'Mostre que pesquisa e contexto interno apontam para a mesma direção.', 'Não leia todos os itens; use um exemplo de cada coluna.']);
}

// 5 — Pesquisa para produto
{
  const s = pptx.addSlide('MASTER');
  title(s, 'Decisões de projeto', 'Cada dor virou uma resposta concreta no Spot', 'A pesquisa foi traduzida em regras, módulos e escolhas de experiência.');
  const rows = [
    ['Organização', 'Status e situação claros; projetos finalizados bloqueiam edições'],
    ['Controle financeiro', 'Despesas, valor/hora e planejado × executado'],
    ['Baixa usabilidade', 'Interface moderna, visual e orientada à tarefa'],
    ['Projetos distintos', 'Fluxos interno/externo; cliente e gestores definidos'],
    ['Acesso por cargo', 'Perfis e permissões específicas por função']
  ];
  s.addShape(S.roundRect, { x: .75, y: 1.95, w: 11.8, h: .62, rectRadius: .08, fill: { color: C.navy }, line: { color: C.navy } });
  s.addText('DOR IDENTIFICADA', { x: 1.04, y: 2.16, w: 3.0, h: .18, fontSize: 10, bold: true, color: C.cyan, charSpacing: 1, margin: 0 });
  s.addText('RESPOSTA NO SISTEMA', { x: 4.65, y: 2.16, w: 4.0, h: .18, fontSize: 10, bold: true, color: C.cyan, charSpacing: 1, margin: 0 });
  rows.forEach((r, i) => {
    const y = 2.68 + i * .72;
    const fill = i % 2 ? C.white : 'EDF3F7';
    s.addShape(S.roundRect, { x: .75, y, w: 11.8, h: .58, rectRadius: .05, fill: { color: fill }, line: { color: fill } });
    s.addText(r[0], { x: 1.04, y: y + .16, w: 3.0, h: .2, fontSize: 14, bold: true, color: C.navy, margin: 0 });
    s.addText(r[1], { x: 4.65, y: y + .13, w: 7.25, h: .27, fontSize: 12.5, color: C.ink, margin: 0, fit: 'shrink' });
  });
  notes(s, ['Tempo: 1 minuto e 10 segundos.', 'Este é o slide central: explique duas ou três relações e deixe as demais visíveis.', 'Transição: “Para sustentar essas decisões, estruturamos o sistema em camadas.”']);
}

// 6 — Stack e estrutura
{
  const s = pptx.addSlide('MASTER');
  title(s, 'Tecnologia', 'Stack moderna e arquitetura em camadas', 'Frontend, API, domínio e dados permanecem separados para facilitar evolução e manutenção.');
  const layers = [
    ['INTERFACE', 'React 18 + Vite', 'SPA responsiva, componentes e navegação', C.cyan],
    ['API & SEGURANÇA', 'Laravel 13 • PHP 8.3 • Sanctum', 'REST /api/v1, sessão, CSRF, OAuth e permissões', C.blue],
    ['REGRAS DE NEGÓCIO', 'Controllers • Requests • Domain Actions', 'Projetos, horas, despesas, convites e auditoria', C.purple],
    ['DADOS & INDICADORES', 'PostgreSQL • Python + Pandas', 'Persistência, relatórios e consolidação analítica', C.green]
  ];
  layers.forEach((l, i) => {
    const y = 1.92 + i * 1.05;
    box(s, .75, y, 11.75, .82, i % 2 ? C.white : 'EEF7F9');
    s.addShape(S.rect, { x: .75, y, w: .1, h: .82, fill: { color: l[3] }, line: { color: l[3] } });
    s.addText(l[0], { x: 1.08, y: y + .17, w: 2.05, h: .19, fontSize: 10, bold: true, color: l[3], charSpacing: 1, margin: 0 });
    s.addText(l[1], { x: 3.0, y: y + .14, w: 3.3, h: .25, fontSize: 16, bold: true, color: C.navy, margin: 0, fit: 'shrink' });
    s.addText(l[2], { x: 6.55, y: y + .15, w: 5.35, h: .3, fontSize: 11.5, color: C.muted, margin: 0, fit: 'shrink' });
  });
  pill(s, 'ESTRUTURA: FRONTEND  →  API  →  DOMÍNIO  →  BANCO / ANALYTICS', 3.15, 6.36, 7.05, C.pale, C.blue);
  notes(s, ['Tempo: 1 minuto.', 'Explique a arquitetura de cima para baixo.', 'Se perguntarem: desenvolvimento, testes e produção usam PostgreSQL; os testes locais usam um schema isolado.']);
}

// 7 — Encerramento e demo
{
  const s = pptx.addSlide('MASTER');
  title(s, 'Conclusão', 'Pesquisa validada. Solução traduzida. Agora, o sistema.', 'A demonstração conecta as evidências da pesquisa aos fluxos implementados.');
  box(s, .7, 1.95, 4.05, 4.45, C.navy, C.navy);
  s.addText('Síntese', { x: 1.05, y: 2.32, w: 3.2, h: .35, fontSize: 22, bold: true, color: C.white, margin: 0 });
  s.addText('O Spot não é apenas “mais uma ferramenta”.', { x: 1.06, y: 3.0, w: 3.25, h: .72, fontSize: 20, bold: true, color: C.cyan, margin: 0, fit: 'shrink' });
  s.addText('É uma resposta simples, visual e integrada para organizar projetos e transformar execução em controle financeiro.', { x: 1.06, y: 4.08, w: 3.15, h: 1.18, fontSize: 15, color: C.white, margin: 0, fit: 'shrink' });
  pill(s, 'PRÓXIMO: DEMONSTRAÇÃO', 1.15, 5.72, 3.0, C.cyan, C.navy);
  const demo = [
    ['1', 'Tutorial guiado', 'Mostre como o usuário aprende a navegar'],
    ['2', 'Projeto', 'Cadastro, status, responsáveis e fluxo'],
    ['3', 'Execução', 'Tarefas, horas e despesas'],
    ['4', 'Resultado', 'Planejado × realizado e indicadores'],
    ['5', 'Governança', 'Permissões e bloqueio após finalização']
  ];
  demo.forEach((d, i) => {
    const y = 2.02 + i * .83;
    s.addShape(S.ellipse, { x: 5.25, y: y + .02, w: .44, h: .44, fill: { color: i === 4 ? C.green : C.blue }, line: { color: i === 4 ? C.green : C.blue } });
    s.addText(d[0], { x: 5.36, y: y + .13, w: .22, h: .16, fontSize: 10, bold: true, color: C.white, align: 'center', margin: 0 });
    s.addText(d[1], { x: 5.95, y, w: 2.15, h: .25, fontSize: 16, bold: true, color: C.navy, margin: 0 });
    s.addText(d[2], { x: 8.15, y: y + .01, w: 4.0, h: .28, fontSize: 12, color: C.muted, margin: 0, fit: 'shrink' });
  });
  s.addText('6 min slides  •  8 min demonstração  •  1 min de margem', { x: 5.3, y: 6.37, w: 6.75, h: .25, fontSize: 12, bold: true, color: C.blue, align: 'center', margin: 0 });
  notes(s, ['Tempo: 50 segundos + transição para a demo.', 'Roteiro da demo: tutorial → projeto → tarefa/horas/despesa → resultado → finalizar e mostrar bloqueio.', 'Deixe este slide aberto caso a demonstração demore a iniciar.']);
}

pptx.writeFile({ fileName: OUT });
