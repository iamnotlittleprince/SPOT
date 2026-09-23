const pptxgen = require(process.env.PPTXGENJS_PATH || 'pptxgenjs');

const pptx = new pptxgen();
pptx.layout = 'LAYOUT_WIDE';
pptx.author = 'Equipe Spot';
pptx.subject = 'Visão atual do sistema Spot';
pptx.title = 'Spot — visão do sistema até agora';
pptx.company = 'Computécnica';
pptx.lang = 'pt-BR';
pptx.theme = {
  headFontFace: 'Aptos Display', bodyFontFace: 'Aptos', lang: 'pt-BR'
};
pptx.defineSlideMaster({
  title: 'MASTER',
  background: { color: 'F6F8FB' },
  objects: [
    { rect: { x: 0, y: 0, w: 13.333, h: 0.09, fill: { color: '19B5D1' }, line: { color: '19B5D1' } } },
    { text: { text: 'SPOT  •  VISÃO ATUAL DO SISTEMA', options: { x: 0.55, y: 7.13, w: 5.5, h: 0.18, fontFace: 'Aptos', fontSize: 8, color: '758395', bold: true, charSpacing: 1.2, margin: 0 } } },
    { text: { text: '11 SET 2026', options: { x: 11.65, y: 7.13, w: 1.1, h: 0.18, fontFace: 'Aptos', fontSize: 8, color: '758395', bold: true, align: 'right', margin: 0 } } },
  ],
  slideNumber: { x: 12.84, y: 7.12, color: '758395', fontSize: 8 },
});

const C = { navy:'102A43', blue:'147DAD', cyan:'19B5D1', pale:'DDF5F8', ink:'172B3A', muted:'60758A', white:'FFFFFF', green:'2E9B69', amber:'E8A23B', red:'D95D5D', line:'D7E1EA', soft:'EDF2F6', purple:'7467D8' };
const logo = '/home/alexander/Documentos/Spot/public/spot-logo.png';
const S = pptx.ShapeType;

function addTitle(slide, kicker, title, subtitle) {
  slide.addText(kicker.toUpperCase(), { x:.62,y:.38,w:4.7,h:.22,fontSize:10,bold:true,color:C.cyan,charSpacing:1.6,margin:0 });
  slide.addText(title, { x:.62,y:.7,w:11.9,h:.55,fontSize:28,bold:true,color:C.navy,margin:0,breakLine:false,fit:'shrink' });
  if (subtitle) slide.addText(subtitle, { x:.64,y:1.36,w:11.6,h:.38,fontSize:13,color:C.muted,margin:0,fit:'shrink' });
}
function box(slide,x,y,w,h,fill='FFFFFF',radius=.12,line=C.line){
  slide.addShape(S.roundRect,{x,y,w,h,rectRadius:radius,fill:{color:fill},line:{color:line,width:1}});
}
function pill(slide,text,x,y,w,fill,color=C.navy){
  slide.addShape(S.roundRect,{x,y,w,h:.3,rectRadius:.15,fill:{color:fill},line:{color:fill}});
  slide.addText(text,{x:x+.08,y:y+.06,w:w-.16,h:.15,fontSize:9,bold:true,color,align:'center',margin:0,fit:'shrink'});
}
function metric(slide,x,y,w,label,value,color=C.blue){
  box(slide,x,y,w,1.0,'FFFFFF');
  slide.addShape(S.rect,{x,y,w:.07,h:1,fill:{color},line:{color}});
  slide.addText(value,{x:x+.24,y:y+.14,w:w-.36,h:.37,fontSize:24,bold:true,color:C.navy,margin:0,fit:'shrink'});
  slide.addText(label,{x:x+.25,y:y+.6,w:w-.35,h:.2,fontSize:10,color:C.muted,margin:0,fit:'shrink'});
}
function addBullets(slide, items, x,y,w,h, color=C.ink, size=15){
  const runs=[]; items.forEach((t,i)=>{runs.push({text:t,options:{bullet:{indent:14},breakLine:i<items.length-1,hanging:4}})});
  slide.addText(runs,{x,y,w,h,fontSize:size,color,breakLine:false,paraSpaceAfterPt:13,margin:0.05,fit:'shrink',valign:'mid'});
}
function iconCircle(slide, text, x,y,color=C.blue){
  slide.addShape(S.ellipse,{x,y,w:.48,h:.48,fill:{color},line:{color}});
  slide.addText(text,{x:x+.05,y:y+.1,w:.38,h:.2,fontSize:12,bold:true,color:C.white,align:'center',margin:0});
}

// 1 — Capa
{
  const s=pptx.addSlide(); s.background={color:C.navy};
  s.addShape(S.rect,{x:8.55,y:0,w:4.78,h:7.5,fill:{color:C.blue,transparency:10},line:{color:C.blue}});
  s.addShape(S.arc,{x:8.35,y:.65,w:4.0,h:4.0,adjustPoint:.3,rotate:20,fill:{color:C.cyan,transparency:10},line:{color:C.cyan,transparency:100}});
  s.addShape(S.ellipse,{x:10.55,y:4.7,w:2.1,h:2.1,fill:{color:C.cyan,transparency:18},line:{color:C.cyan,transparency:100}});
  s.addImage({path:logo,x:.72,y:.68,w:1.35,h:.58,transparency:0});
  pill(s,'VISÃO ATUAL  •  SETEMBRO 2026',.72,1.62,2.65,C.cyan,C.navy);
  s.addText('Spot',{x:.7,y:2.1,w:6.8,h:1.0,fontSize:52,bold:true,color:C.white,margin:0});
  s.addText('Gestão de projetos com operação, governança e resultado em uma única plataforma.',{x:.75,y:3.15,w:6.8,h:1.15,fontSize:24,color:'D8E8F2',bold:false,margin:0,breakLine:false,fit:'shrink'});
  s.addText('O que já existe • como funciona • próximos passos',{x:.75,y:5.45,w:6.9,h:.35,fontSize:15,color:C.cyan,bold:true,margin:0});
  s.addText('Computécnica',{x:.75,y:6.62,w:3,h:.24,fontSize:11,color:'A9C0D1',margin:0});
}

// 2 — Resumo executivo
{
  const s=pptx.addSlide('MASTER'); addTitle(s,'Resumo executivo','Um núcleo operacional já funcional','O Spot conecta execução diária, gestão financeira e controles de acesso em uma experiência web única.');
  metric(s,.65,2.02,2.7,'rotas de API mapeadas','92',C.cyan);
  metric(s,3.55,2.02,2.7,'testes automatizados aprovados','52 / 52',C.green);
  metric(s,6.45,2.02,2.7,'asserções validadas','262',C.blue);
  metric(s,9.35,2.02,2.7,'modelos de domínio','23',C.purple);
  box(s,.65,3.42,11.4,2.55,'FFFFFF');
  s.addText('O que isso significa',{x:.95,y:3.72,w:3.2,h:.32,fontSize:19,bold:true,color:C.navy,margin:0});
  addBullets(s,[
    'Fluxos centrais de projetos, tarefas, equipes, horas e despesas já estão representados no backend.',
    'A interface React entrega painel, portfólio, agenda, inbox, projetos, tarefas e perfil.',
    'Autenticação por sessão, permissões e auditoria protegem operações sensíveis.',
    'A solução já compila e passa integralmente pela suíte automatizada no estado analisado.'
  ],4.15,3.62,7.4,1.95,C.ink,14);
}

// 3 — mapa de capacidades
{
  const s=pptx.addSlide('MASTER'); addTitle(s,'Produto','O mapa de capacidades do Spot','A plataforma foi desenhada em torno do ciclo completo do projeto — da entrada ao resultado.');
  const cards=[
    ['01','Projetos','Cadastro, responsáveis, prazos, escopo e ciclo de vida.',C.blue],
    ['02','Execução','Tarefas, quadro Kanban, horas e equipes ativas.',C.cyan],
    ['03','Financeiro','Contratos, tarifas, impostos, despesas e lucro.',C.green],
    ['04','Governança','Finalizar, reabrir, excluir e revisar com justificativa.',C.purple],
    ['05','Colaboração','Inbox, convites, agenda e integrações Google/Microsoft.',C.amber],
    ['06','Estoque','Categorias, produtos e movimentações atômicas via API.',C.red],
  ];
  cards.forEach((c,i)=>{const col=i%3,row=Math.floor(i/3),x=.65+col*4.08,y=2.0+row*2.05; box(s,x,y,3.68,1.63,'FFFFFF'); iconCircle(s,c[0],x+.25,y+.28,c[4]); s.addText(c[1],{x:x+.9,y:y+.26,w:2.45,h:.28,fontSize:18,bold:true,color:C.navy,margin:0}); s.addText(c[2],{x:x+.25,y:y+.85,w:3.13,h:.48,fontSize:11.5,color:C.muted,margin:0,fit:'shrink'});});
}

// 4 — jornada
{
  const s=pptx.addSlide('MASTER'); addTitle(s,'Fluxo principal','Da oportunidade ao resultado','O dado nasce no cadastro, ganha contexto operacional e termina em indicadores de decisão.');
  const steps=[['1','Cadastrar','Proposta, cliente, responsáveis, prazo e contrato'],['2','Configurar','Equipe, função, tarifa vigente e composição tributária'],['3','Executar','Tarefas, horas, horas extras e despesas'],['4','Governar','Aprovar despesas, finalizar ou reabrir com justificativa'],['5','Analisar','Receita, custo, lucro, prazo e evolução do portfólio']];
  s.addShape(S.line,{x:1.05,y:3.11,w:10.95,h:0,line:{color:C.cyan,width:4,beginArrowType:'none',endArrowType:'triangle'}});
  steps.forEach((st,i)=>{const x=.67+i*2.45; s.addShape(S.ellipse,{x:x+.63,y:2.72,w:.76,h:.76,fill:{color:i===4?C.green:C.blue},line:{color:C.white,width:3}}); s.addText(st[0],{x:x+.83,y:2.94,w:.36,h:.18,fontSize:13,bold:true,color:C.white,align:'center',margin:0}); s.addText(st[1],{x,y:3.72,w:2.0,h:.3,fontSize:17,bold:true,color:C.navy,align:'center',margin:0}); s.addText(st[2],{x,y:4.17,w:2.0,h:.95,fontSize:11.5,color:C.muted,align:'center',valign:'top',margin:0.02,fit:'shrink'});});
  pill(s,'HISTÓRICO FINANCEIRO PRESERVADO POR “FOTOGRAFIA” DAS TARIFAS',3.25,5.78,6.82,C.pale,C.blue);
}

// 5 — financeiro
{
  const s=pptx.addSlide('MASTER'); addTitle(s,'Gestão financeira','Resultado planejado e realizado','A lógica financeira evita distorções históricas e considera apenas eventos governados.');
  box(s,.66,1.95,5.0,4.35,C.navy,C.navy,C.navy);
  s.addText('Motor de resultado',{x:1.0,y:2.27,w:3.8,h:.34,fontSize:21,bold:true,color:C.white,margin:0});
  s.addText('Receita realizada',{x:1.0,y:3.05,w:2.4,h:.25,fontSize:14,color:'B8D7E8',margin:0});
  s.addText('–  custos de horas\n–  despesas aprovadas\n–  impostos e comissão',{x:1.0,y:3.5,w:3.35,h:1.2,fontSize:18,color:C.white,breakLine:false,margin:0,paraSpaceAfterPt:11});
  s.addShape(S.line,{x:1.0,y:5.06,w:3.9,h:0,line:{color:C.cyan,width:2}});
  s.addText('=  resultado do projeto',{x:1.0,y:5.32,w:3.8,h:.36,fontSize:21,bold:true,color:C.cyan,margin:0});
  const items=[['Tarifas com vigência','Sem sobreposição; custo e venda separados.'],['Horas extras','Custos e faturamento podem ter valores distintos.'],['Despesas aprovadas','Pendências e rejeições não contaminam o realizado.'],['Visibilidade por permissão','Campos financeiros são omitidos quando necessário.']];
  items.forEach((it,i)=>{const y=2.02+i*1.08; iconCircle(s,String(i+1),6.15,y,i===2?C.green:C.blue); s.addText(it[0],{x:6.82,y:y+.02,w:2.5,h:.25,fontSize:16,bold:true,color:C.navy,margin:0}); s.addText(it[1],{x:9.15,y:y,w:3.0,h:.45,fontSize:11.5,color:C.muted,margin:0,fit:'shrink'}); if(i<3)s.addShape(S.line,{x:6.82,y:y+.77,w:5.18,h:0,line:{color:C.line,width:1}})});
}

// 6 — governança e segurança
{
  const s=pptx.addSlide('MASTER'); addTitle(s,'Confiança','Governança e segurança por desenho','Operações críticas exigem identidade válida, permissão explícita e deixam rastros.');
  const columns=[
    ['IDENTIDADE',['Sem cadastro público','Primeiro acesso por link único','Login Google e Microsoft autorizado','2FA e gestão de sessões'],C.blue],
    ['AUTORIZAÇÃO',['Perfis e permissões granulares','Escopo limitado para convidados','Proteção do último administrador','Dados financeiros por privilégio'],C.purple],
    ['RASTREABILIDADE',['Justificativa em ações críticas','Audit logs de mudanças','Convites armazenados por hash','Projetos finalizados ficam bloqueados'],C.green]
  ];
  columns.forEach((col,i)=>{const x=.67+i*4.11; box(s,x,2.0,3.7,4.25,'FFFFFF'); pill(s,col[0],x+.28,2.32,1.7,col[2],C.white); addBullets(s,col[1],x+.3,3.04,3.12,2.65,C.ink,13.5);});
}

// 7 — arquitetura
{
  const s=pptx.addSlide('MASTER'); addTitle(s,'Arquitetura','Uma SPA moderna sobre um domínio Laravel','Separação clara entre experiência, API, regras de negócio, persistência e analytics.');
  const layers=[
    ['EXPERIÊNCIA','React 18 + Vite','Painel, portfólio, projetos, tarefas, agenda, inbox e perfil',C.cyan],
    ['API PRINCIPAL','Laravel 13 + Sanctum','REST /api/v1, sessão, CSRF, throttling e validação',C.blue],
    ['DOMÍNIO','Actions + Models','Projetos, despesas, horas, convites, auditoria e estoque',C.purple],
    ['DADOS & ANÁLISE','PostgreSQL + Python/Pandas','Persistência relacional e consolidação de indicadores gráficos',C.green]
  ];
  layers.forEach((l,i)=>{const y=1.91+i*1.13; box(s,1.08,y,11.0,.84,i%2?'FFFFFF':'F0F8FA'); s.addShape(S.rect,{x:1.08,y,w:.12,h:.84,fill:{color:l[3]},line:{color:l[3]}}); s.addText(l[0],{x:1.48,y:y+.15,w:1.7,h:.19,fontSize:10,bold:true,color:l[3],charSpacing:1,margin:0}); s.addText(l[1],{x:3.18,y:y+.12,w:2.65,h:.27,fontSize:17,bold:true,color:C.navy,margin:0}); s.addText(l[2],{x:6.0,y:y+.15,w:5.48,h:.32,fontSize:11.5,color:C.muted,margin:0,fit:'shrink'}); if(i<3)s.addShape(S.chevron,{x:6.25,y:y+.83,w:.55,h:.3,rotate:90,fill:{color:C.line},line:{color:C.line}})});
  pill(s,'JWT permanece apenas como camada temporária de compatibilidade',4.0,6.52,5.35,'FFF1D9','9A651B');
}

// 8 — experiência
{
  const s=pptx.addSlide('MASTER'); addTitle(s,'Experiência','A interface organiza o trabalho em três ritmos','Visão rápida para o dia, profundidade para a operação e consolidação para a gestão.');
  const cards=[
    ['AGORA','Painel e inbox','Projetos, tarefas, times ativos, busca, filtros e mensagens em um só ponto.',C.cyan],
    ['EXECUÇÃO','Projetos e tarefas','Lista de projetos, novo projeto em etapas e quadro Kanban para priorização.',C.blue],
    ['DECISÃO','Portfólio e agenda','KPIs, gráficos por período/projeto e compromissos Spot, Google e Microsoft.',C.green]
  ];
  cards.forEach((c,i)=>{const x=.67+i*4.12; box(s,x,2.05,3.72,3.8,'FFFFFF'); s.addShape(S.rect,{x,y:2.05,w:3.72,h:.12,fill:{color:c[3]},line:{color:c[3]}}); s.addText(c[0],{x:x+.3,y:2.5,w:2.5,h:.2,fontSize:10,bold:true,color:c[3],charSpacing:1.4,margin:0}); s.addText(c[1],{x:x+.3,y:2.92,w:3.0,h:.52,fontSize:23,bold:true,color:C.navy,margin:0,fit:'shrink'}); s.addText(c[2],{x:x+.3,y:3.78,w:3.03,h:1.05,fontSize:14,color:C.muted,margin:0,fit:'shrink'}); pill(s,i===0?'OPERACIONAL':i===1?'COLABORATIVO':'GERENCIAL',x+.3,5.15,1.42,C.soft,C.ink)});
}

// 9 — estado atual
{
  const s=pptx.addSlide('MASTER'); addTitle(s,'Estado atual','O que já está comprovado — e o que pede consolidação','Leitura baseada no código, nas rotas e nos testes existentes em 11/09/2026.');
  box(s,.67,1.95,5.75,4.45,'F2FBF7','F2FBF7','BFE5D3');
  pill(s,'COMPROVADO',.98,2.28,1.45,C.green,C.white);
  addBullets(s,['Build de produção concluído','52 testes / 262 asserções aprovados','API principal protegida por sessão e CSRF','Fluxos financeiros e governados cobertos por testes','Interface responsiva com principais áreas navegáveis'],1.02,2.92,4.9,2.7,C.ink,14);
  box(s,6.78,1.95,5.55,4.45,'FFF9EE','FFF9EE','F0D7A7');
  pill(s,'A CONSOLIDAR',7.08,2.28,1.62,C.amber,C.white);
  addBullets(s,['Concluir telas ainda demonstrativas ou parcialmente conectadas','Ampliar testes do frontend e de ponta a ponta','Hospedar PostgreSQL e sessão em infraestrutura de produção','Retirar gradualmente a compatibilidade JWT legada','Formalizar observabilidade, backup e operação'],7.12,2.92,4.65,2.7,C.ink,14);
}

// 10 — próximos passos
{
  const s=pptx.addSlide('MASTER'); addTitle(s,'Próximos passos','Uma rota pragmática para a próxima versão','Priorizar confiabilidade operacional antes de ampliar o escopo funcional.');
  const phases=[
    ['01','FECHAR O NÚCLEO','Conectar e validar integralmente projetos, tarefas, horas, despesas e configurações na interface.','0–30 dias',C.blue],
    ['02','ENDURECER','Adicionar E2E, observabilidade, política de backup, Redis e banco de produção.','30–60 dias',C.purple],
    ['03','ESCALAR ADOÇÃO','Piloto com usuários, métricas de uso, ajustes de UX e desativação planejada do JWT legado.','60–90 dias',C.green]
  ];
  phases.forEach((p,i)=>{const y=1.95+i*1.42; iconCircle(s,p[0],.78,y+.13,p[4]); s.addText(p[1],{x:1.48,y:y+.08,w:2.25,h:.25,fontSize:15,bold:true,color:C.navy,margin:0}); s.addText(p[2],{x:3.75,y:y+.02,w:6.5,h:.65,fontSize:13,color:C.muted,margin:0,fit:'shrink'}); pill(s,p[3],10.8,y+.13,1.25,C.soft,C.ink); if(i<2)s.addShape(S.line,{x:1.02,y:y+.61,w:0,h:.88,line:{color:C.line,width:2,dash:'dash'}})});
  box(s,.72,6.13,11.55,.65,C.navy,C.navy,C.navy);
  s.addText('Resultado esperado: uma base estável, auditável e pronta para adoção controlada.',{x:1.05,y:6.34,w:10.9,h:.24,fontSize:15,bold:true,color:C.white,align:'center',margin:0});
}

pptx.writeFile({ fileName: '/home/alexander/Documentos/Spot/docs/Spot-visao-atual-do-sistema.pptx' });
