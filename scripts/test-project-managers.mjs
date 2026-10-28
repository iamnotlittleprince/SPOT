import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch({headless:true});
try {
 const errors=[];
 const {page,context,user}=await createBrowserSession(browser,{admin:true,dismissNotice:true,onError:e=>errors.push(e)});
 const managers=[{id:1,name:'Administrador global'},{id:3,name:'Gestor'},{id:4,name:'Gestor administrador'}];
 const users=[...managers,{id:2,name:'Analista'}];
 const params={users,managers,clients:[],project_statuses:[{id:1,name:'Planejamento'}],project_situations:[{id:1,name:'No prazo'}],field_access:{}};
 let project=null;
 await page.route('**/sanctum/csrf-cookie',r=>r.fulfill({status:204}));
 await page.route('**/api/v1/parameters',r=>r.fulfill({json:params}));
 await page.route('**/api/v1/management/access',r=>r.fulfill({json:{'parameters.view':true,'projects.create':true,'projects.update':true,'financial.manage':true}}));
 await page.route('**/api/v1/projects{,/*}',r=>{
  if(r.request().method()==='POST'||r.request().method()==='PUT') {project={...project,...r.request().postDataJSON(),id:1,status:'planning',tasks_count:0};return r.fulfill({status:r.request().method()==='POST'?201:200,json:project});}
  return r.fulfill({json:project?[project]:[]});
 });
 const go=async label=>{await page.locator('.home-label > button').last().click();await page.getByRole('menuitem',{name:label,exact:true}).click();};
 async function options(root){for(const label of ['Gerente de contas','Gerente do projeto'])assert.deepEqual(await root.getByLabel(label,{exact:true}).locator('option').evaluateAll(options=>options.map(option=>option.value)),['','1','3','4']);}
 await go('Novo projeto');
 await page.getByLabel('Gerente de contas',{exact:true}).locator('option[value="3"]').waitFor({state:'attached'});
 await options(page);
 await page.getByLabel('Nome do projeto',{exact:true}).fill('Gestão por perfis');
 await page.getByLabel('Número da proposta',{exact:true}).fill('GESTAO-001');
 await page.getByLabel('Status',{exact:true}).selectOption('1');
 await page.getByLabel('Situação',{exact:true}).selectOption('1');
 await page.getByLabel('Gerente de contas',{exact:true}).selectOption('3');
 await page.getByLabel('Gerente do projeto',{exact:true}).selectOption('4');
 await page.getByRole('button',{name:'Criar projeto',exact:true}).click();
 await page.getByRole('button',{name:'Editar',exact:true}).waitFor();
 assert.equal(Number(project.account_manager_id),3);assert.equal(Number(project.project_manager_id),4);
 await page.getByRole('button',{name:'Editar',exact:true}).click();
 const editor=page.getByRole('dialog');await editor.waitFor();await options(editor);
 await editor.getByLabel('Gerente de contas',{exact:true}).selectOption('1');
 await editor.getByRole('button',{name:'Salvar',exact:true}).click();
 await editor.waitFor({state:'hidden'});assert.equal(Number(project.account_manager_id),1);
 assert.deepEqual(errors,[]);await context.close();
 console.log('OK: criação e edição oferecem apenas os três perfis de gerência e enviam os IDs selecionados.');
} finally {await browser.close();}
