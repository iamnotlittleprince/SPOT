import fs from 'node:fs/promises';
const base = process.env.SPOT_BASE_URL || 'http://localhost:8000';
const manifest = JSON.parse(await fs.readFile('public/build/manifest.json', 'utf8'))['resources/js/app.jsx'];
export async function createBrowserSession(browser, { admin = false, email = 'ana@example.test', viewport = {width:1440,height:1000}, onError = () => {}, storage = {}, dismissNotice = false } = {}) {
 const requests = [];
 const context=await browser.newContext({viewport});
 const page=await context.newPage();page.setDefaultTimeout(7000);page.on('pageerror',e=>onError(e.message));
 const user={id:1,name:'Ana Teste',email,timezone:'America/Sao_Paulo',can_manage_identity:admin,can_create_projects:admin,can_view_financial:admin};
 await page.addInitScript(values => {
  if (!sessionStorage.getItem('fixtureStorageSeeded')) {
   for(const [key,value] of Object.entries(values)) localStorage.setItem(key,value);
   sessionStorage.setItem('fixtureStorageSeeded','1');
  }
 }, storage);
 await page.route(base+'/',r=>r.fulfill({contentType:'text/html; charset=utf-8',body:`<!doctype html><html lang="pt-BR"><head>${(manifest.css||[]).map(f=>`<link rel="stylesheet" href="/build/${f}">`).join('')}</head><body><div id="root"></div><script>window.__SPOT__=${JSON.stringify({authenticated:true,user})}</script><script type="module" src="/build/${manifest.file}"></script></body></html>`}));
 await page.route('**/api/**',async r=>{
  requests.push({method:r.request().method(),url:r.request().url()});
  const path=new URL(r.request().url()).pathname.replace('/api/v1','');
  const fixtures={
   '/home-dashboard':{updated_at:new Date().toISOString(),projects:[{id:1,name:'Planejamento',status:'planning',progress:10}],tasks:[],teams:[]},
   '/tasks':{tasks:[],projects:[],can_create:true},
   '/management/access':{'projects.create':admin,'parameters.view':true,'financial.view':admin}, '/projects':[], '/auth/me/projects':[], '/auth/timezones':[{id:'America/Sao_Paulo',label:'America/Sao_Paulo'}],
   '/inbox':{items:[],unread_count:0},'/documents':{documents:[]},'/history':{events:[]},
   '/calendar':{events:[],providers:{google:false,microsoft:false},errors:[]},
   '/parameters':{clients:[],users:[],project_statuses:[],project_situations:[]},
   '/admin/users':{users:[{...user,id:2,name:'Analista Teste',profiles:[{slug:'analista'}],account_status:'active',can_create_projects:false}],organizations:[],profiles:[]},
   '/security':{two_factor_enabled:false,providers:{google:{connected:false},microsoft:{connected:false}},sessions:[],events:[],preferences:{}},
   '/notification-preferences':{events:{assigned:{key:'assigned',group:'Projetos',label:'Projeto atribuído'}},timezone:'America/Sao_Paulo',preferences:{events:{assigned:{in_app:true,email:false}},digest:{frequency:'none',time:'09:00'},quiet_hours:{enabled:false,start:'22:00',end:'08:00'}}},
  };
  if(path==='/reports/project-analytics') {
   if(!admin)return r.fulfill({status:403,json:{message:'This action is unauthorized.'}});
   return r.fulfill({json:{projects:[{id:1,name:'Projeto Teste',client:'Cliente Teste',manager:'Ana Teste',status:'planning',progress:25,contract_value:12000,actual_revenue:1000,actual_cost:500,profit:500,worked_hours:10,estimated_hours:40}],summary:{total:1,in_progress:1,completed:0,on_time:1,overdue:0,frozen:0,clients:1,managers:1,contract_value:12000,revenue:1000,cost:500,profit:500},status:[{label:'Planejamento',value:1}],monthly:[{label:'2026-09',value:1}],clients:[{label:'Cliente Teste',value:1}],managers:[{label:'Ana Teste',value:1}],processor:'Python + Pandas'}});
  }
  if(!(path in fixtures)) onError('Unexpected API request: '+path);
  return r.fulfill({json:fixtures[path]||{}});
 });
 await page.goto(base+'/'); await page.locator('.dashboard-main .project-row').waitFor();
 if(dismissNotice) await page.locator('.tutorial-card').getByRole('button',{name:'Agora não',exact:true}).click();
 return {context,page,user,requests};
}
