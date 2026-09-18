import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
const browser=await chromium.launch({headless:true});const errors=[];
try {
 const {page,context,user}=await createBrowserSession(browser,{admin:true,dismissNotice:true,onError:e=>errors.push(e)});
 let projects=[],clients=[],rates=[],taxes=[];const mutations=[];
 const access=Object.fromEntries(['projects.create','projects.update','projects.delete','projects.finalize','projects.reopen','financial.view','financial.manage','parameters.view','parameters.create','parameters.update','expenses.create'].map(k=>[k,true]));
 const params={users:[user],clients,project_statuses:[{id:1,name:'Planejamento'}],project_situations:[{id:1,name:'No prazo'}],activity_types:[{id:1,name:'Reunião'}],expense_types:[{id:1,name:'Transporte'}],tax_types:[{id:1,name:'ISS'}],field_access:{}};
 await page.route('**/sanctum/csrf-cookie',r=>r.fulfill({status:204}));
 await page.route('**/api/v1/**',async r=>{
  const q=r.request(),path=new URL(q.url()).pathname.replace('/api/v1',''),method=q.method();
  if(method!=='GET')mutations.push(path);
  if(path==='/management/access')return r.fulfill({json:access});
  if(path==='/parameters')return r.fulfill({json:{...params,clients}});
  if(path==='/projects'&&method==='POST'){projects=[{...q.postDataJSON(),id:1,tasks_count:0,status:'planning',finalized_at:null}];return r.fulfill({status:201,json:projects[0]});}
  if(path==='/projects')return r.fulfill({json:projects});
  if(path==='/projects/1'&&method==='PUT'){projects=[{...projects[0],...q.postDataJSON()}];return r.fulfill({json:projects[0]});}
  if(path==='/projects/1/finalize'){projects[0].finalized_at='2026-09-16';return r.fulfill({json:projects[0]});}
  if(path==='/projects/1/reopen'){projects[0].finalized_at=null;return r.fulfill({json:projects[0]});}
  if(path==='/projects/1/configuration')return r.fulfill({json:{...projects[0],members:[{id:1,user_id:user.id,active:true}],rates,taxes}});
  if(path==='/projects/1/expenses')return r.fulfill({json:[]});
  if(path==='/projects/1/rates'){rates=[{...q.postDataJSON(),id:1,user_id:Number(q.postDataJSON().user_id)}];return r.fulfill({status:201,json:rates[0]});}
  if(path==='/projects/1/financial-result')return r.fulfill({json:{actual:{profit:900,worked_minutes:90},estimated:{profit:800,minutes:120},by_analyst:[],by_activity_type:[],expenses:[],complete:true}});
  if(path.startsWith('/management/parameters/clients')){
   if(method==='POST'){clients=[{...q.postDataJSON(),id:1}];return r.fulfill({status:201,json:clients[0]});}
   if(method==='PUT'){clients=[{...q.postDataJSON(),id:1}];return r.fulfill({json:clients[0]});}
   if(method==='DELETE'){clients[0].active=false;return r.fulfill({status:204});}
   return r.fulfill({json:clients});
  }
  return r.fallback();
 });
 async function go(label){await page.locator('.home-label > button').last().click();await page.getByRole('menuitem',{name:label,exact:true}).click();}
 await go('Novo projeto');
 await page.getByLabel('Nome do projeto',{exact:true}).fill('Projeto memorial navegador');
 await page.getByLabel('Número da proposta',{exact:true}).fill('MEM-001');
 await page.getByLabel('Horas estimadas',{exact:true}).fill('2');await page.getByLabel('Minutos estimados',{exact:true}).fill('30');
 await page.getByRole('button',{name:'Criar projeto',exact:true}).click();
 await page.getByRole('button',{name:'Projeto memorial navegador',exact:true}).waitFor();assert.equal(projects[0].estimated_minutes,150);
 await page.getByRole('button',{name:'Editar',exact:true}).click();
 let dialog=page.getByRole('dialog');await dialog.getByLabel('Nome do projeto',{exact:true}).fill('Projeto editado');await dialog.getByRole('button',{name:'Salvar',exact:true}).click();await dialog.waitFor({state:'hidden'});
 await page.getByRole('button',{name:'Finalizar',exact:true}).click();await dialog.getByLabel('Justificativa',{exact:true}).fill('Entrega aprovada pelo cliente.');await dialog.getByRole('button',{name:'Salvar',exact:true}).click();await dialog.waitFor({state:'hidden'});await page.locator('.project-finalized').waitFor();assert.equal(await page.getByRole('button',{name:'Editar',exact:true}).count(),0);
 await page.getByRole('button',{name:'Reabrir',exact:true}).click();await dialog.getByLabel('Justificativa',{exact:true}).fill('Cliente solicitou novo ajuste.');await dialog.getByRole('button',{name:'Salvar',exact:true}).click();await dialog.waitFor({state:'hidden'});
 await page.getByRole('button',{name:'Detalhes',exact:true}).click();await page.getByRole('button',{name:'Adicionar custo/hora',exact:true}).click();await dialog.getByLabel('Analista',{exact:true}).selectOption('1');await dialog.getByLabel('Custo/hora normal',{exact:true}).fill('100');await dialog.getByLabel('Vigência inicial',{exact:true}).fill('2026-01-01');await dialog.getByRole('button',{name:'Salvar',exact:true}).click();await dialog.waitFor({state:'hidden'});assert.equal(rates[0].normal_cost_rate,'100');
 await page.evaluate(()=>document.documentElement.dataset.theme='dark');await page.screenshot({path:'/tmp/spot-management-dark.png',fullPage:true});
 await go('Cadastros e configurações');await page.getByRole('button',{name:'Adicionar',exact:true}).click();await dialog.getByLabel('Nome',{exact:true}).fill('Cliente novo');await dialog.getByRole('button',{name:'Salvar',exact:true}).click();await dialog.waitFor({state:'hidden'});await page.getByText('Cliente novo',{exact:true}).waitFor();
 page.once('dialog',d=>d.accept());await page.getByRole('button',{name:'Arquivar',exact:true}).click();await page.getByText('Inativo',{exact:true}).waitFor();
 assert.ok(mutations.includes('/projects'));assert.ok(mutations.includes('/projects/1/rates'));assert.deepEqual(errors,[]);await context.close();console.log('Management browser passed: create/edit project, finalize/reopen, rates and client CRUD.');
}finally{await browser.close();}
