import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch({headless:true});
try {
 for(const [width,scale] of [[1440,100],[320,120]]) {
  const errors=[];
  const {page,context,user}=await createBrowserSession(browser,{admin:true,dismissNotice:true,mobile:width===320,viewport:{width,height:800},storage:{'spot.reading-settings':JSON.stringify({scale,font:'verdana'})},onError:e=>errors.push(e)});
  const workers=[user,{id:2,name:"João d’Ávila",email:'joao@example.test'},{id:3,name:'Maria com um nome longo para testar a seleção da equipe no celular',email:'maria.com.um.email.longo@example.test'}];
  const params={users:workers,project_workers:workers,managers:[user],clients:[],project_statuses:[{id:1,name:'Planejamento'}],project_situations:[{id:1,name:'No prazo'}],field_access:{}};
  let reject=true,project=null;
  await page.route('**/sanctum/csrf-cookie',r=>r.fulfill({status:204}));
  await page.route('**/api/v1/parameters',r=>r.fulfill({json:params}));
  await page.route('**/api/v1/projects',r=>{
   if(r.request().method()==='POST') {
    if(reject)return r.fulfill({status:422,json:{errors:{member_ids:['Revise a equipe antes de salvar.']}}});
    project={...r.request().postDataJSON(),id:1,status:'planning',tasks_count:0};return r.fulfill({status:201,json:project});
   }
   return r.fulfill({json:project?[project]:[]});
  });
  await page.locator('.home-label > button').last().click();await page.getByRole('menuitem',{name:'Novo projeto',exact:true}).click();
  const picker=page.locator('.new-project-team');
  const search=picker.getByRole('searchbox',{name:'Buscar usuários',exact:true});
  await search.fill('joao');
  await picker.getByRole('checkbox',{name:/João/}).check();
  await search.fill('inexistente');
  await picker.getByText('Nenhum usuário encontrado para esta busca.',{exact:true}).waitFor();
  await picker.getByRole('button',{name:'Remover João d’Ávila da equipe',exact:true}).click();
  await picker.getByRole('status').getByText('Selecionados: 0',{exact:true}).waitFor();
  await search.fill('');
  await picker.getByRole('checkbox',{name:/João/}).check();
  await picker.getByRole('checkbox',{name:/Maria com um nome longo/}).check();
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true);
  assert.equal(await picker.evaluate(el=>el.scrollWidth<=el.clientWidth+2),true);
  await page.getByLabel('Nome do projeto',{exact:true}).fill('Projeto com equipe');
  await page.getByLabel('Número da proposta',{exact:true}).fill('EQUIPE-TESTE');
  await page.getByRole('button',{name:'Criar projeto',exact:true}).click();
  await page.getByRole('alert').getByText('Revise a equipe antes de salvar.',{exact:true}).waitFor();
  assert.equal(await picker.getByRole('checkbox',{name:/João/}).isChecked(),true);
  assert.equal(await picker.getByRole('checkbox',{name:/Maria com um nome longo/}).isChecked(),true);
  await picker.scrollIntoViewIfNeeded();
  await page.screenshot({path:`/tmp/spot-project-team-${width}.png`,fullPage:true});
  reject=false;
  await page.getByRole('button',{name:'Criar projeto',exact:true}).click();
  await page.locator('.management-table').waitFor();assert.deepEqual(project.member_ids,[2,3]);
  assert.deepEqual(errors,[]);await context.close();
 }
 console.log('OK: busca sem acentos, seleção múltipla, remoção, preservação após erro e envio da equipe em desktop e mobile a 120%.');
}finally{await browser.close();}
