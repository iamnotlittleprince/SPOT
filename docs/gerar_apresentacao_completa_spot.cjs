const pptxgen = require('pptxgenjs');
const path = require('path');

const pptx = new pptxgen();
pptx.layout = 'LAYOUT_WIDE';
pptx.author = 'Equipe Spot';
pptx.company = 'Computécnica';
pptx.subject = 'Visão completa do produto, arquitetura e posicionamento do Spot';
pptx.title = 'Spot — produto, tecnologia e mercado';
pptx.lang = 'pt-BR';
pptx.theme = { headFontFace: 'Aptos Display', bodyFontFace: 'Aptos', lang: 'pt-BR' };

const OUT = path.join(__dirname, 'Spot-apresentacao-15-min.pptx');
const SCRIPT = path.join(__dirname, 'Spot-roteiro-apresentacao-15-min.md');
const LOGO = path.join(__dirname, '../public/spot-logo.png');
const S = pptx.ShapeType;
const C = { navy:'102A43', blue:'147DAD', cyan:'19B5D1', pale:'E5F7FA', ink:'172B3A', muted:'60758A', white:'FFFFFF', green:'2E9B69', amber:'E8A23B', purple:'7467D8', red:'D95D5D', line:'D7E1EA', soft:'F4F7FA', bg:'F6F8FB' };

pptx.defineSlideMaster({
  title:'MASTER', background:{color:C.bg},
  objects:[
    {rect:{x:0,y:0,w:13.333,h:.08,fill:{color:C.cyan},line:{color:C.cyan}}},
    {text:{text:'SPOT  •  VISÃO DO PRODUTO',options:{x:.55,y:7.13,w:5.6,h:.16,fontSize:8,bold:true,color:'758395',charSpacing:1.1,margin:0}}},
  ],
  slideNumber:{x:12.45,y:7.1,w:.35,h:.18,fontSize:8,color:'758395',align:'right'}
});

function box(slide,x,y,w,h,fill=C.white,line=C.line){slide.addShape(S.roundRect,{x,y,w,h,rectRadius:.12,fill:{color:fill},line:{color:line,width:1}})}
function pill(slide,text,x,y,w,fill=C.pale,color=C.blue){slide.addShape(S.roundRect,{x,y,w,h:.3,rectRadius:.15,fill:{color:fill},line:{color:fill}});slide.addText(text,{x:x+.08,y:y+.06,w:w-.16,h:.15,fontSize:9,bold:true,color,align:'center',margin:0,fit:'shrink'})}
function title(slide,kicker,heading,sub=''){slide.addText(kicker.toUpperCase(),{x:.65,y:.38,w:5.0,h:.2,fontSize:10,bold:true,color:C.cyan,charSpacing:1.5,margin:0});slide.addText(heading,{x:.65,y:.7,w:12.0,h:.52,fontSize:27,bold:true,color:C.navy,margin:0,fit:'shrink'});if(sub)slide.addText(sub,{x:.67,y:1.32,w:11.8,h:.36,fontSize:13,color:C.muted,margin:0,fit:'shrink'})}
function bullets(slide,items,x,y,w,h,size=14,color=C.ink){const runs=[];items.forEach((t,i)=>runs.push({text:t,options:{bullet:{indent:size},hanging:4,breakLine:i<items.length-1}}));slide.addText(runs,{x,y,w,h,fontSize:size,color,margin:.03,paraSpaceAfterPt:11,fit:'shrink',valign:'mid'})}
function notes(slide,mins,text){slide.addNotes([`Duração prevista: ${mins}.`,text]);}
function card(slide,{x,y,w,h,kicker,heading,body,color=C.blue,fill=C.white}){box(slide,x,y,w,h,fill);slide.addShape(S.rect,{x,y,w:.07,h,fill:{color},line:{color}});slide.addText(kicker.toUpperCase(),{x:x+.24,y:y+.2,w:w-.42,h:.16,fontSize:9,bold:true,color,charSpacing:1.0,margin:0,fit:'shrink'});slide.addText(heading,{x:x+.24,y:y+.52,w:w-.45,h:.35,fontSize:17,bold:true,color:C.navy,margin:0,fit:'shrink'});slide.addText(body,{x:x+.24,y:y+1.0,w:w-.48,h:h-1.16,fontSize:12.2,color:C.muted,margin:0,fit:'shrink',valign:'top'})}
const scriptSections=[];
function addScript(n,heading,time,text){scriptSections.push(`## ${String(n).padStart(2,'0')}. ${heading} — ${time}\n\n${text}`)}

// 1. Capa
{
  const s=pptx.addSlide(); s.background={color:C.navy};
  s.addShape(S.rect,{x:8.75,y:0,w:4.58,h:7.5,fill:{color:C.blue,transparency:5},line:{color:C.blue}});
  s.addShape(S.ellipse,{x:9.8,y:.65,w:2.4,h:2.4,fill:{color:C.cyan,transparency:18},line:{color:C.cyan,transparency:100}});
  s.addShape(S.ellipse,{x:10.6,y:4.7,w:2.3,h:2.3,fill:{color:C.cyan,transparency:40},line:{color:C.cyan,transparency:100}});
  s.addImage({path:LOGO,x:.72,y:.58,w:1.24,h:.82,sizing:'contain'});
  pill(s,'PRODUTO  •  TECNOLOGIA  •  MERCADO',.74,1.7,3.35,C.cyan,C.navy);
  s.addText('Spot',{x:.72,y:2.28,w:6.8,h:.86,fontSize:51,bold:true,color:C.white,margin:0});
  s.addText('Projetos conectados à execução e ao resultado do negócio.',{x:.76,y:3.26,w:7.2,h:1.15,fontSize:25,color:'D8E8F2',margin:0,fit:'shrink'});
  s.addText('Visão completa do sistema  •  apresentação de 15 minutos',{x:.76,y:5.85,w:7.3,h:.3,fontSize:13,bold:true,color:C.cyan,margin:0});
  s.addText('Computécnica  |  Outubro de 2026',{x:.76,y:6.5,w:5.0,h:.24,fontSize:11,color:'A9C0D1',margin:0});
  const t='Olá. Em quinze minutos vou apresentar o Spot como produto e como sistema: que problema ele resolve, como um projeto percorre a plataforma, quais regras sustentam o resultado financeiro, como a solução foi construída e onde ela se posiciona diante de outras ferramentas. A ideia é sair com uma visão útil tanto para quem avalia o produto quanto para quem precisa operá-lo, mantê-lo ou evoluí-lo.';
  notes(s,'0:30',t); addScript(1,'Abertura','0:30',t);
}

// 2. Resumo executivo
{
  const s=pptx.addSlide('MASTER'); title(s,'Em uma frase','Uma plataforma para gerir o ciclo completo do projeto','O Spot aproxima trabalho diário, governança e leitura financeira em uma única aplicação.');
  const cols=[['PLANEJAR','Projetos, clientes, escopo, prazos e responsáveis.',C.blue],['EXECUTAR','Equipes, tarefas, horas, documentos e despesas.',C.cyan],['CONTROLAR','Aprovações, permissões, auditoria e ciclo de vida.',C.purple],['DECIDIR','Portfólio, indicadores e resultado planejado × realizado.',C.green]];
  cols.forEach((c,i)=>{const x=.68+i*3.08;card(s,{x,y:2.02,w:2.75,h:2.65,kicker:c[0],heading:['Planejar','Executar','Controlar','Decidir'][i],body:c[1],color:c[2]})});
  box(s,.72,5.12,11.85,.82,C.navy,C.navy); s.addText('Foco do produto: projetos de serviços em que horas, custos e margem importam.',{x:1.0,y:5.38,w:11.25,h:.25,fontSize:17,bold:true,color:C.white,align:'center',margin:0,fit:'shrink'});
  const t='O Spot é uma plataforma de gestão de projetos orientada a operações de serviços. Ele combina quatro momentos que normalmente acabam espalhados entre planilhas e ferramentas: planejar um projeto, executar o trabalho, controlar mudanças e despesas, e entender o resultado. O diferencial de foco é ligar a atividade da equipe à visão de custo e margem. Isso não significa que ele seja um ERP completo ou uma ferramenta universal de colaboração; seu núcleo é o projeto e sua execução econômica.';
  notes(s,'1:00',t); addScript(2,'O produto em uma frase','1:00',t);
}

// 3. Problema e contexto
{
  const s=pptx.addSlide('MASTER'); title(s,'Problema que o Spot endereça','Quando os dados do projeto ficam espalhados, a margem vira surpresa','A dor operacional é a falta de ligação entre planejamento, execução e leitura financeira.');
  const stages=[['1','Planejamento','Proposta, escopo e valor contratado'],['2','Execução','Horas e despesas registradas em outros lugares'],['3','Fechamento','Consolidação tardia e pouca rastreabilidade']];
  stages.forEach((a,i)=>{const x=.82+i*4.15;box(s,x,2.15,3.55,2.42,'FFFFFF');pill(s,a[0],x+.25,2.43,.42,[C.blue,C.amber,C.red][i],C.white);s.addText(a[1],{x:x+.82,y:2.43,w:2.3,h:.34,fontSize:18,bold:true,color:C.navy,margin:0});s.addText(a[2],{x:x+.3,y:3.25,w:2.95,h:.75,fontSize:15,color:C.muted,margin:0,fit:'shrink',align:'center'});if(i<2)s.addShape(S.chevron,{x:x+3.67,y:3.0,w:.38,h:.58,fill:{color:C.cyan},line:{color:C.cyan}})});
  box(s,1.55,5.17,10.2,.75,C.pale,C.pale);s.addText('Resposta do Spot: uma fonte operacional com regras explícitas e histórico consultável.',{x:1.85,y:5.39,w:9.6,h:.24,fontSize:16,bold:true,color:C.blue,align:'center',margin:0,fit:'shrink'});
  const t='O problema que o Spot procura resolver não é simplesmente “falta de tarefas”. É a fragmentação do contexto: a proposta tem um valor, as horas são apontadas em outro lugar, as despesas chegam depois e o gestor só descobre o desvio quando consolida os dados. O Spot tenta encurtar essa distância mantendo cliente, equipe, tarifa, horas, despesas e situação do projeto no mesmo contexto. A pesquisa de mercado existente no repositório reforça temas como organização, usabilidade, integração e visibilidade financeira; os percentuais publicados nessa pesquisa devem ser apresentados sempre com seu tamanho e método de amostra, não como estatística geral do mercado.';
  notes(s,'1:05',t); addScript(3,'O problema','1:05',t);
}

// 4. Ciclo
{
  const s=pptx.addSlide('MASTER'); title(s,'Jornada do projeto','Do cadastro à decisão, com contexto preservado','Cada etapa prepara dados para a próxima e define quem pode agir.');
  const a=[['01','Cadastrar','Cliente • proposta • contrato'],['02','Configurar','Equipe • tarifas • impostos'],['03','Executar','Tarefas • horas • despesas'],['04','Governar','Aprovar • finalizar • reabrir'],['05','Analisar','Resultado • portfólio • histórico']];
  s.addShape(S.line,{x:1.0,y:3.12,w:11.15,h:0,line:{color:C.cyan,width:4,endArrowType:'triangle'}});
  a.forEach((v,i)=>{const x=.72+i*2.48;s.addShape(S.ellipse,{x:x+.62,y:2.73,w:.72,h:.72,fill:{color:i===4?C.green:C.blue},line:{color:C.white,width:3}});s.addText(v[0],{x:x+.76,y:2.97,w:.44,h:.18,fontSize:12,bold:true,color:C.white,align:'center',margin:0});s.addText(v[1],{x:x+.05,y:3.72,w:1.9,h:.27,fontSize:16,bold:true,color:C.navy,align:'center',margin:0});s.addText(v[2],{x:x-.03,y:4.15,w:2.08,h:.66,fontSize:11.5,color:C.muted,align:'center',margin:0,fit:'shrink'});});
  pill(s,'O HISTÓRICO MANTÉM O CONTEXTO DE COMO O RESULTADO FOI FORMADO',2.55,5.62,8.3,C.pale,C.blue);
  const t='A jornada começa com o projeto comercial: cliente, proposta, valor contratado, datas e responsáveis. Antes da execução, configura-se a equipe, as funções, as tarifas com períodos de vigência e a composição tributária. Durante o trabalho, tarefas, horas extras e despesas alimentam a operação. Despesas podem passar por aprovação. A governança permite finalizar, reabrir ou excluir com justificativas e permissões. No fim, gestores analisam resultado do projeto e carteira. É importante notar que os dados não são só etapas de formulário: as decisões de acesso e os registros de auditoria acompanham os fluxos.';
  notes(s,'1:10',t); addScript(4,'Ciclo do projeto','1:10',t);
}

// 5. Capacidades
{
  const s=pptx.addSlide('MASTER'); title(s,'O que existe no produto','Seis áreas trabalham ao redor do projeto','O menu combina operação cotidiana, administração e uma área complementar de estoque.');
  const items=[['Projetos','Cadastro, busca, ciclo de vida e detalhes',C.blue],['Tarefas e equipes','Kanban/lista/calendário, atribuições e filtros',C.cyan],['Financeiro e portfólio','Tarifas, impostos, despesas, resultado e análises',C.green],['Colaboração','Agenda, inbox, convites e documentos',C.purple],['Administração','Usuários, parâmetros, permissões e diretório',C.amber],['Estoque','Produtos, categorias e movimentações',C.red]];
  items.forEach((v,i)=>{const col=i%3,row=Math.floor(i/3),x=.75+col*4.12,y=1.95+row*2.12;card(s,{x,y,w:3.68,h:1.78,kicker:`ÁREA 0${i+1}`,heading:v[0],body:v[1],color:v[2]})});
  const t='A experiência está organizada em áreas. Projetos concentram os dados comerciais e o ciclo de vida. Tarefas e equipes conectam pessoas ao trabalho e permitem diferentes formas de visualizar a execução. O financeiro e o portfólio mostram custos e indicadores agregados. A colaboração inclui agenda, caixa de entrada, convites e documentos. A administração cuida de usuários, parâmetros, permissões e integração de diretório. O estoque mantém cadastro de produtos e histórico de movimentações. Esse módulo é importante para conhecer a abrangência do repositório, mas é um domínio distinto e não deve ser confundido com o núcleo de gestão de projetos.';
  notes(s,'1:10',t); addScript(5,'Capacidades do produto','1:10',t);
}

// 6. Comportamento/regras
{
  const s=pptx.addSlide('MASTER'); title(s,'Como o sistema se comporta','Regras de negócio protegem o ciclo e a consistência dos dados','A interface oferece os fluxos; a API aplica as permissões e validações.');
  const rows=[['Acesso','Contas provisionadas; projetos isolados por empresa e vínculo.'],['Tarefas','Acesso ao projeto; terceiros dependem de permissão específica.'],['Tarifas','Vigência sem sobreposição; tarifa histórica capturada no registro.'],['Despesas','Somente as aprovadas entram no resultado realizado.'],['Finalização','Bloqueia alterações; reabertura e exclusão exigem justificativa.'],['Auditoria','Operações relevantes deixam registros para consulta.']];
  rows.forEach((r,i)=>{const y=1.9+i*.72;box(s,.78,y,11.72,.56,i%2?C.white:'EDF5F8',i%2?C.white:'EDF5F8');s.addText(r[0],{x:1.03,y:y+.17,w:1.7,h:.2,fontSize:13.5,bold:true,color:[C.blue,C.cyan,C.purple,C.green,C.red,C.amber][i],margin:0});s.addText(r[1],{x:2.85,y:y+.15,w:9.2,h:.24,fontSize:12.5,color:C.ink,margin:0,fit:'shrink'});});
  const t='O comportamento do Spot depende de regras que vão além do que a tela mostra. Não há cadastro público: uma conta é provisionada e ativada por link de uso único. Os acessos respeitam empresa e vínculos com projeto. Em tarefas, permissões para ver, editar ou excluir trabalho de terceiros são tratadas separadamente. Tarifas têm vigência e não devem se sobrepor; o valor usado fica fotografado no registro de horas para preservar o histórico. Despesas pendentes ou rejeitadas não compõem o realizado. Um projeto finalizado torna-se somente leitura, e ações de finalização, reabertura e exclusão exigem justificativa. Essas regras precisam ser respeitadas em qualquer novo endpoint ou integração.';
  notes(s,'1:20',t); addScript(6,'Regras de comportamento','1:20',t);
}

// 7. Financeiro
{
  const s=pptx.addSlide('MASTER'); title(s,'Motor financeiro','O realizado é montado a partir de horas e eventos aprovados','A apuração diferencia previsão, receita, custos e itens ainda incompletos.');
  box(s,.78,2.0,4.45,3.72,C.navy,C.navy);s.addText('Resultado do projeto',{x:1.12,y:2.32,w:3.8,h:.34,fontSize:21,bold:true,color:C.white,margin:0});
  s.addText('Receita realizada\n− custos de mão de obra\n− impostos e comissão\n− despesas aprovadas',{x:1.12,y:3.02,w:3.55,h:1.45,fontSize:19,color:'D8E8F2',breakLine:false,margin:0,paraSpaceAfterPt:12});s.addShape(S.line,{x:1.12,y:4.78,w:3.55,h:0,line:{color:C.cyan,width:2}});s.addText('= lucro e margem',{x:1.12,y:5.03,w:3.55,h:.35,fontSize:22,bold:true,color:C.cyan,margin:0});
  const checks=[['Tarifas históricas','Reajustes futuros não reescrevem horas antigas.'],['Hora extra','Pode usar tarifas de custo e faturamento próprias.'],['Sem tarifa','O resultado sinaliza que está parcial.'],['Sem duplicidade','Tarefas vinculadas a apontamentos não contam duas vezes.']];
  checks.forEach((v,i)=>{const y=2.05+i*.93;s.addShape(S.ellipse,{x:5.75,y:y+.06,w:.4,h:.4,fill:{color:[C.blue,C.purple,C.amber,C.green][i]},line:{color:[C.blue,C.purple,C.amber,C.green][i]}});s.addText('✓',{x:5.83,y:y+.15,w:.24,h:.17,fontSize:12,bold:true,color:C.white,align:'center',margin:0});s.addText(v[0],{x:6.35,y:y+.03,w:2.1,h:.24,fontSize:15,bold:true,color:C.navy,margin:0});s.addText(v[1],{x:8.55,y:y+.02,w:3.55,h:.42,fontSize:12,color:C.muted,margin:0,fit:'shrink'});});
  const t='O motor financeiro calcula o projeto a partir do contrato, das horas e de eventos governados. Em linhas gerais, considera receita realizada, custos de mão de obra calculados com as tarifas capturadas, impostos, comissão e despesas aprovadas. Horas extras podem ter tarifas distintas de custo e faturamento. Se uma hora não possui tarifa aplicável, ela não desaparece: aparece como pendência e o resultado é sinalizado como parcial. Há ainda uma salvaguarda contra dupla contagem de tarefas que já estejam cobertas por apontamentos vinculados. Para o profissional de finanças, isso significa que é essencial validar as regras de tarifa, impostos e comissão na configuração e entender o estado de completude do resultado antes de usá-lo em decisão.';
  notes(s,'1:20',t); addScript(7,'Resultado financeiro','1:20',t);
}

// 8. Arquitetura
{
  const s=pptx.addSlide('MASTER'); title(s,'Tecnologia','Laravel organiza o domínio; React entrega a experiência','Uma aplicação web com API, persistência relacional e apoio analítico.');
  const layers=[['INTERFACE','React 18 + Vite','SPA responsiva • JavaScript • CSS',C.cyan],['API / APLICAÇÃO','PHP 8.3 + Laravel 13','REST • Sanctum • validação • sessão e CSRF',C.blue],['DOMÍNIO','Actions • Models • Controllers','Projetos • horas • financeiro • auditoria • estoque',C.purple],['DADOS / RELATÓRIOS','PostgreSQL + Python/Pandas','Persistência relacional • consolidação analítica',C.green]];
  layers.forEach((v,i)=>{const y=1.94+i*1.07;box(s,1.05,y,11.25,.78,i%2?C.white:'EEF7F9');s.addShape(S.rect,{x:1.05,y,w:.1,h:.78,fill:{color:v[3]},line:{color:v[3]}});s.addText(v[0],{x:1.42,y:y+.18,w:2.0,h:.18,fontSize:9.5,bold:true,color:v[3],charSpacing:.8,margin:0});s.addText(v[1],{x:3.47,y:y+.16,w:3.1,h:.25,fontSize:16,bold:true,color:C.navy,margin:0,fit:'shrink'});s.addText(v[2],{x:6.72,y:y+.17,w:5.2,h:.26,fontSize:11.5,color:C.muted,margin:0,fit:'shrink'});});
  pill(s,'FILA LARAVEL • E-MAILS ASSÍNCRONOS • SCHEDULER PARA ROTINAS',2.58,6.35,8.22,C.pale,C.blue);
  const t='A interface usa React 18 e Vite, mas é servida pela aplicação Laravel; não é necessário assumir dois produtos independentes para implantá-la. O backend usa PHP 8.3 e Laravel 13, com controllers, validações, modelos e ações de domínio. O banco documentado é PostgreSQL. Python com Pandas consolida indicadores de portfólio. O fluxo web principal usa sessão Laravel Sanctum e proteção CSRF. Socialite integra provedores OAuth; JWT permanece em rotas de compatibilidade. O processamento assíncrono de e-mails usa a fila do Laravel, e o scheduler executa rotinas. Em implantação, portanto, a equipe precisa cuidar de aplicação, banco, worker, agendador, SMTP e armazenamento compartilhado de sessão se houver múltiplas instâncias.';
  notes(s,'1:00',t); addScript(8,'Arquitetura e stack','1:00',t);
}

// 9. Segurança e operação
{
  const s=pptx.addSlide('MASTER'); title(s,'Segurança e operação','Identidade, permissão e rastreabilidade são parte do produto','A segurança também depende de configuração e operação corretas.');
  const cards=[['IDENTIDADE','Provisionamento administrativo • ativação de uso único • Google/Microsoft • 2FA',C.blue],['AUTORIZAÇÃO','Perfis • permissões por operação • escopo de projeto • proteção de dados financeiros',C.purple],['RASTREABILIDADE','Auditoria de operações • justificativas • histórico • retenção configurável',C.green]];
  cards.forEach((v,i)=>card(s,{x:.75+i*4.12,y:2.1,w:3.68,h:3.35,kicker:v[0],heading:['Quem entra','O que pode fazer','O que fica registrado'][i],body:v[1],color:v[2]}));
  box(s,.9,5.82,11.5,.53,'FFF5E6','F0D7A7');s.addText('A integração com Entra ID exige credenciais e consentimento administrativo do tenant.',{x:1.15,y:5.99,w:11.0,h:.18,fontSize:12,bold:true,color:'8A5B16',align:'center',margin:0,fit:'shrink'});
  const t='A segurança começa com o provisionamento controlado e continua em cada requisição. Perfis e permissões limitam operações, projetos e dados financeiros. Há controle de sessões, opção de autenticação em duas etapas e login por Google ou Microsoft para contas já autorizadas. Os convites são escopados ao projeto e os tokens não são armazenados em texto puro. Auditoria registra ações importantes, não cada clique da interface. A retenção é configurável e depende do scheduler ativo. Um ponto operacional importante: Microsoft Entra ID exige credenciais e consentimento administrativo para consultar o diretório; ter login Microsoft configurado não habilita automaticamente a importação de usuários.';
  notes(s,'1:10',t); addScript(9,'Segurança e operação','1:10',t);
}

// 10. Mercado
{
  const s=pptx.addSlide('MASTER'); title(s,'Referências de mercado','O Spot cruza gestão de trabalho e rentabilidade de serviços','Comparação de posicionamento com base em funcionalidades divulgadas pelos próprios fornecedores.');
  const x0=.72,y0=1.92, widths=[2.15,3.05,3.05,3.05], headers=['DIMENSÃO','SPOT','monday.com','Teamwork.com'];
  let x=x0;headers.forEach((h,i)=>{s.addShape(S.rect,{x,y:y0,w:widths[i],h:.53,fill:{color:i===0?C.navy:[C.blue,C.purple,C.green][i-1]},line:{color:C.white,width:1}});s.addText(h,{x:x+.1,y:y0+.17,w:widths[i]-.2,h:.16,fontSize:10,bold:true,color:C.white,align:i===0?'left':'center',margin:0,fit:'shrink'});x+=widths[i]});
  const rows=[['Ênfase','Projeto + controle financeiro','Plataforma configurável de trabalho e portfólio','Serviços a clientes e rentabilidade'],['Execução','Tarefas, equipe, horas e despesas','Boards, visões, workload e dashboards','Horas faturáveis, workload e budgets'],['Finanças','Tarifas, impostos, comissão e resultado','Dashboards e dados de custo conforme solução/plano','Rentabilidade de projeto/cliente e tempo faturável'],['Natureza','Sistema customizável interno','Produto SaaS horizontal','SaaS especializado em client work']];
  rows.forEach((r,ri)=>{let xx=x0;const yy=y0+.53+ri*.86;r.forEach((v,ci)=>{const fill=ri%2?C.white:'EDF3F7';s.addShape(S.rect,{x:xx,y:yy,w:widths[ci],h:.86,fill:{color:fill},line:{color:C.line,width:.7}});s.addText(v,{x:xx+.12,y:yy+.12,w:widths[ci]-.24,h:.62,fontSize:ci===0?11.5:10.5,bold:ci===0,color:ci===0?C.navy:C.ink,align:ci===0?'left':'center',valign:'mid',margin:0,fit:'shrink'});xx+=widths[ci]})});
  s.addText('Fontes de funcionalidades: monday.com Projects / Portfolio; Teamwork.com Agencies. Consulte planos e disponibilidade atuais.',{x:.83,y:6.18,w:11.6,h:.32,fontSize:9,color:C.muted,margin:0,fit:'shrink'});
  const t='No mercado, a referência mais próxima é a categoria de gestão de trabalho e portfólio. O monday.com enfatiza flexibilidade, quadros, dashboards, carga de trabalho e soluções de portfólio; algumas capacidades avançadas dependem de planos específicos. O Teamwork.com se posiciona mais diretamente para agências e serviços a clientes, destacando horas faturáveis, orçamento, gestão de capacidade e rentabilidade. O Spot combina gestão de projeto com regras financeiras e governança ajustadas à operação interna. Esta é uma comparação funcional baseada em páginas oficiais, não uma avaliação de qualidade, preço ou escala. A oportunidade para o Spot está na aderência às regras locais e à integração entre execução e apuração; os desafios são competir com a maturidade, integrações, escalabilidade, ecossistemas e investimento contínuo dos SaaS estabelecidos.';
  notes(s,'1:20',t); addScript(10,'Posicionamento de mercado','1:20',t);
}

// 11. Profissionais
{
  const s=pptx.addSlide('MASTER'); title(s,'Para quem trabalha no Spot','Um sistema compartilhado por execução, gestão e administração','A mesma informação atende perguntas diferentes conforme o papel.');
  const roles=[['ANALISTA','O que preciso fazer? Onde registrar meu tempo?','Tarefas • agenda • projetos atribuídos',C.blue],['GESTOR','Como está o prazo, a equipe e a margem?','Portfólio • equipe • despesas • resultado',C.green],['ADMINISTRADOR','Quem acessa e como o sistema está configurado?','Usuários • parâmetros • permissões • auditoria',C.purple],['PROFISSIONAL TÉCNICO','Como mantenho e opero com segurança?','Rotas • migrations • filas • scheduler • backups',C.amber]];
  roles.forEach((r,i)=>{const col=i%2,row=Math.floor(i/2),x=.78+col*6.05,y=2.02+row*2.1;box(s,x,y,5.55,1.78,C.white,C.line);pill(s,r[0],x+.25,y+.22,1.85,r[3],C.white);s.addText(r[1],{x:x+.25,y:y+.7,w:5.0,h:.3,fontSize:14,bold:true,color:C.navy,margin:0,fit:'shrink'});s.addText(r[2],{x:x+.25,y:y+1.2,w:5.0,h:.25,fontSize:11,color:C.muted,margin:0,fit:'shrink'});});
  const t='O valor da plataforma aparece quando cada papel encontra informação útil sem receber acesso desnecessário. O analista precisa saber o que está atribuído e registrar a atividade. O gestor precisa entender andamento, capacidade, despesas e resultado. O administrador provisiona pessoas, configura parâmetros e ajusta permissões. Já o profissional técnico precisa conhecer rotas, migrações, jobs, scheduler, credenciais externas, logs e backups. Essa separação também orienta treinamento: não é preciso ensinar toda a plataforma a todos; ensina-se o fluxo e as decisões relevantes para cada papel.';
  notes(s,'1:15',t); addScript(11,'Públicos profissionais','1:15',t);
}

// 12. Limites / maturidade
{
  const s=pptx.addSlide('MASTER'); title(s,'Maturidade e limites','O código mostra um núcleo amplo; produção exige disciplina operacional','Separar capacidade implementada, integração configurada e escala comprovada.');
  const left=[['No repositório','CRUD e regras de projetos, tarefas, governança, horas, despesas, financeiro, estoque, usuários e auditoria.'],['Depende de ambiente','SMTP, OAuth, Microsoft Graph, PostgreSQL produtivo, worker, scheduler, storage e HTTPS.'],['Precisa comprovação em uso','Adoção, desempenho, disponibilidade, qualidade de dados, recuperação e valor percebido.']];
  left.forEach((v,i)=>{const y=1.98+i*1.34;const col=[C.green,C.amber,C.purple][i];pill(s,v[0].toUpperCase(),.82,y,2.35,col,C.white);s.addText(v[1],{x:3.42,y:y-.02,w:8.75,h:.68,fontSize:14,color:C.ink,margin:0,fit:'shrink',valign:'mid'});if(i<2)s.addShape(S.line,{x:.85,y:y+.96,w:11.3,h:0,line:{color:C.line,width:1}})});
  box(s,.85,6.13,11.45,.45,'FFF0EF','F3CECA');s.addText('O estado descrito nos documentos é um retrato do repositório; não substitui validação de produção.',{x:1.05,y:6.27,w:11.0,h:.16,fontSize:11,bold:true,color:'A2433A',align:'center',margin:0,fit:'shrink'});
  const t='É importante comunicar maturidade com precisão. O repositório contém fluxos implementados e testes automatizados, mas testes não comprovam sozinhos disponibilidade, segurança operacional, capacidade em escala ou adoção. E-mail depende de SMTP e worker. Diretório depende de tenant e consentimento. Em produção são necessários HTTPS, PostgreSQL operado, backups testados, monitoramento, gestão de segredos, política de atualização e processos supervisionados. Também existe JWT legado que deve ser acompanhado como compatibilidade. Portanto, a descrição correta é: núcleo funcional amplo e verificável no código, com uma etapa de validação e operação de produção que precisa ser tratada explicitamente.';
  notes(s,'1:15',t); addScript(12,'Maturidade e limites','1:15',t);
}

// 13. Encerramento
{
  const s=pptx.addSlide();s.background={color:C.navy};
  s.addShape(S.rect,{x:9.15,y:0,w:4.18,h:7.5,fill:{color:C.blue},line:{color:C.blue}});
  s.addShape(S.ellipse,{x:10.0,y:.72,w:2.45,h:2.45,fill:{color:C.cyan,transparency:18},line:{color:C.cyan,transparency:100}});
  pill(s,'SÍNTESE',.78,.75,1.35,C.cyan,C.navy);
  s.addText('Um projeto.\nUma história\nde execução.\nUm resultado.',{x:.78,y:1.45,w:7.6,h:2.7,fontSize:34,bold:true,color:C.white,breakLine:false,margin:0,fit:'shrink'});
  s.addText('O Spot conecta gestão operacional e leitura financeira, com governança explícita.',{x:.82,y:4.63,w:7.2,h:.85,fontSize:20,color:'D8E8F2',margin:0,fit:'shrink'});
  s.addText('Próximo passo: validar com usuários, medir operação e priorizar evoluções.',{x:.82,y:6.15,w:7.45,h:.55,fontSize:14,bold:true,color:C.cyan,margin:0,fit:'shrink'});
  const t='Para concluir: o Spot une o cadastro, a execução e a análise do projeto; preserva regras financeiras e de acesso; e oferece uma arquitetura web conhecida, baseada em Laravel, React e PostgreSQL. Seu posicionamento é mais específico que o de uma ferramenta genérica de tarefas, mas ainda precisa demonstrar valor e confiabilidade em uso real frente a soluções maduras. A evolução deve começar com piloto controlado, feedback dos diferentes perfis, indicadores de uso, monitoramento e rotina de backup e recuperação. Com isso, o Spot pode transformar uma necessidade operacional em uma plataforma consistente para acompanhar trabalho e resultado. Obrigado. Agora podemos aprofundar dúvidas sobre produto, arquitetura ou próximos passos.';
  notes(s,'1:25',t); addScript(13,'Conclusão','1:25',t);
}

const total='0:30 + 1:00 + 1:05 + 1:10 + 1:10 + 1:20 + 1:20 + 1:00 + 1:10 + 1:20 + 1:15 + 1:15 + 1:25 = 15:00';
const script=`# Spot — roteiro de apresentação de 15 minutos\n\n**Duração total prevista:** 15:00. O roteiro está incluído nas notas de apresentador de cada slide do PowerPoint. Fale em ritmo natural e use os tempos como referência; os slides não foram feitos para serem lidos.\n\n${scriptSections.join('\n\n')}\n\n## Referências para o slide de mercado\n\n- monday.com, recursos de projetos: https://monday.com/projects/features\n- monday.com, solução de portfólio: https://support.monday.com/hc/en-us/articles/13337066797202-The-portfolio-solution\n- Teamwork.com, gestão para agências: https://www.teamwork.com/teams/agencies/\n\n## Referências do projeto\n\n- README e instruções: ` + '`README.md`' + `\n- Mapa do código: ` + '`docs/MAPA-DO-CODIGO.md`' + `\n- Adequação ao memorial: ` + '`docs/memorial/levantamento.md`' + `\n- Rotas API: ` + '`routes/api.php`' + `\n\n**Observação:** comparações de mercado resumem funcionalidades divulgadas por seus fornecedores. O estado do Spot descreve o código e os documentos deste repositório; não é certificação de produção nem avaliação independente de desempenho.\n`;
require('fs').writeFileSync(SCRIPT,script,'utf8');
pptx.writeFile({fileName:OUT});
