import { chromium } from 'playwright';
import { createBrowserSession } from '../../scripts/browser-fixtures.mjs';
const browser = await chromium.launch({headless:true});
const errors=[];
try {
 const {page,context,user}=await createBrowserSession(browser,{admin:true,viewport:{width:1600,height:1000},dismissNotice:true,onError:e=>errors.push(e)});
 const projects=[
 {id:1,name:'Modernização de infraestrutura',client:'Cliente Alfa',manager:'Ana Teste',account_manager:'Gestor Comercial',status:'Em andamento',progress:65,contract_value:50000,actual_revenue:52000,actual_cost:33000,profit:19000,worked_hours:240,estimated_hours:400,unpriced_tasks:0},
 {id:2,name:'Portal de atendimento',client:'Cliente Beta',manager:'Ana Teste',account_manager:'Gestor Comercial',status:'Planejamento',progress:25,contract_value:30000,actual_revenue:30000,actual_cost:14500,profit:15500,worked_hours:120,estimated_hours:300,unpriced_tasks:0},
 {id:3,name:'Migração de dados',client:'Cliente Alfa',manager:'Gestor de Projetos',account_manager:'Gestor Comercial',status:'Concluídos',progress:100,contract_value:22000,actual_revenue:22000,actual_cost:18000,profit:4000,worked_hours:200,estimated_hours:180,unpriced_tasks:0}
 ];
 const tasks=[
 {id:1,project_id:1,project:{id:1,name:projects[0].name,client_id:1},user:{id:1,name:'Ana Teste'},participants:[{id:2,name:'Analista de infraestrutura'}],creator:{id:1,name:'Ana Teste'},title:'Validar ambiente e requisitos',description:'Conferir escopo e requisitos com a equipe.',status:'in_progress',priority:'high',due_date:'2026-10-28',worked_on:'2026-10-26',duration_minutes:120,is_overtime:false,activity_type_id:1,activity_type_name:'Análise',can_edit:true,can_delete:true},
 {id:2,project_id:1,project:{id:1,name:projects[0].name,client_id:1},user:{id:1,name:'Ana Teste'},participants:[],title:'Documentar plano de implantação',description:'Registrar etapas, responsáveis e critérios de aceite.',status:'todo',priority:'medium',due_date:'2026-10-29',worked_on:'2026-10-26',duration_minutes:60,is_overtime:false,activity_type_name:'Documentação',can_edit:true,can_delete:true},
 {id:3,project_id:2,project:{id:2,name:projects[1].name,client_id:2},user:{id:1,name:'Ana Teste'},participants:[],title:'Revisar protótipo com a equipe',description:'Consolidar sugestões para o portal de atendimento.',status:'review',priority:'medium',due_date:'2026-10-30',worked_on:'2026-10-26',duration_minutes:90,is_overtime:false,activity_type_name:'Revisão',can_edit:true,can_delete:true},
 {id:4,project_id:3,project:{id:3,name:projects[2].name,client_id:1},user:{id:1,name:'Ana Teste'},participants:[],title:'Conferir migração e registrar evidências',description:'Verificar os dados e registrar a conclusão.',status:'done',priority:'low',due_date:'2026-10-25',worked_on:'2026-10-25',duration_minutes:180,is_overtime:false,activity_type_name:'Validação',can_edit:true,can_delete:true}
 ];
 await page.route('**/api/v1/home-dashboard',r=>r.fulfill({json:{updated_at:'2026-10-26T12:00:00Z',projects:projects.map(p=>({...p,tasks_count:4,members_count:3})),tasks:tasks.map(t=>({...t,project_name:t.project.name})),teams:[{project_id:1,name:'Equipe de infraestrutura',count:3,members:[user,{id:2,name:'Analista de infraestrutura'}]}]}}));
 await page.route('**/api/v1/tasks',r=>r.fulfill({json:{tasks,projects:projects.map(p=>({id:p.id,name:p.name})),can_create:true,clients:[{id:1,name:'Cliente Alfa'},{id:2,name:'Cliente Beta'}],current_user:user,can_assign_others:true,analysts:[],project_people:[],activity_types:[{id:1,name:'Análise'}]}}));
 await page.route('**/api/v1/reports/project-analytics*',r=>r.fulfill({json:{projects,summary:{total:3,in_progress:1,completed:1,on_time:3,overdue:0,frozen:0,clients:2,managers:2,contract_value:102000,revenue:104000,cost:65500,profit:38500},status:[{label:'Em andamento',value:1},{label:'Planejamento',value:1},{label:'Concluídos',value:1}],clients:[{label:'Cliente Alfa',value:2},{label:'Cliente Beta',value:1}],managers:[{label:'Ana Teste',value:2},{label:'Gestor de Projetos',value:1}],monthly:[{label:'ago/26',value:1},{label:'set/26',value:1},{label:'out/26',value:1}],processor:'Python + Pandas'}}));
 await page.route('**/api/v1/projects/1/financial-result',r=>r.fulfill({json:{complete:true,unpriced_tasks:0,actual:{contract_revenue:50000,overtime_revenue:2000,total_revenue:52000,taxes:5200,commission:2600,labor_cost:22000,approved_expenses:3200,operating_costs:25200,total_deductions:33000,profit:19000,margin_percent:36.54,worked_minutes:14400,estimated_minutes_consumed_percent:60,tax_composition:[{name:'Impostos',amount:5200}]},estimated:{revenue:50000,taxes:5000,commission:2500,labor_cost:24000,additional_cost:3500,operating_costs:27500,profit:15000,margin_percent:30,minutes:24000},by_analyst:[{name:'Ana Teste',cost:12000,minutes:8000},{name:'Analista de infraestrutura',cost:10000,minutes:6400}],by_activity_type:[{name:'Análise',cost:7000,minutes:4000},{name:'Implantação',cost:15000,minutes:10400}],expenses:[{description:'Deslocamento',amount:3200}]}}));
 await page.reload();await page.locator('.dashboard-main .project-row').first().waitFor();
 await page.screenshot({path:'docs/tcc/assets/tela-home.png'});
 await page.locator('.dashboard-sidebar').getByRole('button',{name:'Minhas tarefas',exact:true}).click();
 await page.getByText('Conferir escopo e requisitos com a equipe.',{exact:true}).first().waitFor();
 await page.screenshot({path:'docs/tcc/assets/tela-tarefas.png'});
 await page.locator('.dashboard-sidebar').getByRole('button',{name:'Portfólios',exact:true}).click();
 await page.locator('.portfolio-project-cards button').first().waitFor();
 await page.screenshot({path:'docs/tcc/assets/tela-portfolio.png'});
 await page.locator('.portfolio-project-cards button').first().click();
 await page.locator('.portfolio-builder').waitFor();
 await page.screenshot({path:'docs/tcc/assets/tela-financeiro.png'});
 await context.close();
 console.log(JSON.stringify({screenshots:4,errors,data:'Dados demonstrativos; API simulada; interface real do build local.'}));
}finally{await browser.close();}
