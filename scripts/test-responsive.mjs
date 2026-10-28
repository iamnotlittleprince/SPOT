import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
const browser=await chromium.launch({headless:true});
const errors=[];
let checks=0;
const long='Planejamento e acompanhamento de projetos com equipe e atendimento regional';
async function fit(page, label) {
 const result=await page.evaluate(()=>{
  const width=document.documentElement.clientWidth;
  const failures=[];
  const visible=el=>{
   if(!el.getClientRects().length)return false;
   for(let p=el;p&&p!==document.body;p=p.parentElement){const css=getComputedStyle(p);if(css.visibility==='hidden'||css.display==='none'||Number(css.opacity)===0)return false;}
   return true;
  };
  const scrollable=el=>{for(let p=el.parentElement;p&&p!==document.body;p=p.parentElement){if(/auto|scroll/.test(getComputedStyle(p).overflowX)&&p.scrollWidth>p.clientWidth+1)return true;}return false;};
  for(const el of document.querySelectorAll('#main-content button, .topbar button, .topbar input')){
   if(!visible(el)||scrollable(el))continue;
   const r=el.getBoundingClientRect();
   if(r.left< -2||r.right>width+2)failures.push({text:el.textContent.slice(0,60),cls:el.className,left:r.left,right:r.right});
  }
  return {width,scroll:document.documentElement.scrollWidth,failures, ...(document.documentElement.scrollWidth>width+2?{overflowNodes:[...document.querySelectorAll('#root *')].filter(el=>el.getClientRects().length&&el.getBoundingClientRect().right>width+2).slice(0,15).map(el=>({tag:el.tagName,cls:el.className,text:el.textContent.slice(0,65),w:el.getBoundingClientRect().width}))}:{})};
 });
 assert.ok(result.scroll<=result.width+2,`${label}: page overflow ${JSON.stringify(result)}`);
 assert.deepEqual(result.failures,[],`${label}: clipped controls`);checks++;
}
async function navigate(page,label){await page.locator('.home-label > button').last().click();await page.getByRole('menuitem',{name:label,exact:true}).click();await page.waitForTimeout(90);}
async function dialogFits(page,dialog,label){
 await dialog.waitFor();
 await dialog.evaluate(el=>Promise.all(el.getAnimations().map(animation=>animation.finished.catch(()=>{}))));
 const r=await dialog.boundingBox(),v=page.viewportSize();
 assert.ok(r.x>=-1&&r.y>=-1&&r.x+r.width<=v.width+2&&r.y+r.height<=v.height+2,`${label}: dialog outside viewport ${JSON.stringify(r)}`);
 assert.equal(await dialog.evaluate(el=>el.scrollWidth<=el.clientWidth+2),true,`${label}: dialog contents overflow`);checks++;
}
try {
 for(const [width,height,scale] of [[320,740,120],[390,420,120],[320,740,100],[390,844,100],[768,1024,100],[1024,768,100],[1440,900,100],[844,390,100],[740,320,120],[1280,900,120],[1440,900,120]]){
  const {page,context,user}=await createBrowserSession(browser,{admin:true,dismissNotice:true,mobile:Math.min(width,height)<=390,viewport:{width,height},storage:{'spot.reading-settings':JSON.stringify({scale,font:'verdana'})},onError:e=>errors.push(e)});
  await page.route('**/api/v1/home-dashboard',r=>r.fulfill({json:{projects:[{id:1,name:long,status:'planning',progress:10}],tasks:[],teams:[{project_id:1,name:long,count:1,members:[user]}]}}));
  const project={id:1,name:long,status:'planning',client_name:long,manager_name:user.name,progress:10};
  await page.route('**/api/v1/projects',r=>r.fulfill({json:[project]}));
  await page.route('**/api/v1/tasks',r=>r.fulfill({json:{tasks:[{id:1,title:long,description:long,status:'planned',priority:'high',project,user,can_edit:true,can_delete:true}],projects:[project],can_create:true,current_user:user,analysts:[user],activity_types:[],project_people:[]}}));
  const now=new Date();const start=new Date(now.getFullYear(),now.getMonth(),15,10).toISOString();
  await page.route('**/api/v1/calendar?*',r=>r.fulfill({json:{events:[{id:'spot-1',provider:'spot',title:long,start,end:start,description:long,location:long}],providers:{google:false,microsoft:false},errors:[]}}));
  await page.route('**/api/v1/documents',r=>r.fulfill({json:{documents:[{id:1,name:long+'.pdf',original_name:long+'.pdf',project,uploaded_by:user,size:1024,created_at:start}]}}));
  for(const label of ['Agenda','Home','Portfólios','Novo projeto','Projetos','Tarefas','Meus documentos','Histórico','Equipes','Clientes','Meu perfil','Usuários e acessos','Cadastros e configurações','Caixa de entrada']){
   await navigate(page,label);await fit(page,`${width}x${height} @${scale}% ${label}`);
  }
  if(width<=390){
   await page.getByRole('textbox',{name:'Pesquisar no Spot',exact:true}).fill('Agenda');
   await page.getByRole('option').filter({hasText:'Agenda'}).first().click();
   await page.getByRole('heading',{name:'Agenda',exact:true}).waitFor();checks++;
   await page.getByRole('button',{name:'Abrir menu',exact:true}).click();
   const nav=page.getByRole('dialog',{name:'Navegação principal'});
   await dialogFits(page,nav,'mobile navigation');
   assert.equal(await nav.evaluate(el=>el.contains(document.activeElement)),true);
   await page.keyboard.press('Escape');
   await page.getByRole('button',{name:'Ajustes rápidos',exact:true}).click();
   await dialogFits(page,page.getByRole('dialog',{name:'Ajustes rápidos do trabalho'}),'quick settings');
   await page.getByRole('button',{name:'Ativar modo escuro',exact:true}).click();
   assert.equal(await page.evaluate(()=>document.documentElement.dataset.theme),'dark');
   await page.keyboard.press('Escape');
   await navigate(page,'Home');
   await page.locator('.apps-hover-wrap > button').click();
   await dialogFits(page,page.locator('.apps-popover'),'apps');
   await page.getByRole('button',{name:'Gerenciar integrações',exact:true}).click();
   await dialogFits(page,page.getByRole('dialog',{name:'Gerenciar integrações',exact:true}),'integrations');
   await page.keyboard.press('Escape');
   await navigate(page,'Agenda');
   assert.equal(await page.locator('.calendar-mobile-agenda button').count(),1);
   await page.locator('.calendar-mobile-agenda button').click();
   await dialogFits(page,page.locator('.calendar-event-popover'),'event details');
   await page.locator('.calendar-event-popover > button').click();
   await page.getByRole('button',{name:'Novo evento',exact:true}).click();
   await dialogFits(page,page.getByRole('dialog',{name:'Novo evento'}),'new event');
   await page.keyboard.press('Escape');
   await navigate(page,'Tarefas');
   await page.getByRole('button',{name:'Nova tarefa',exact:true}).click();
   await dialogFits(page,page.locator('.task-editor-dialog[open]'),'task editor');
   await page.keyboard.press('Escape');
   await navigate(page,'Clientes');
   await page.getByRole('button',{name:'Nova empresa',exact:true}).click();
   await dialogFits(page,page.locator('.clients-modal > form'),'client editor');
   await page.locator('.clients-modal').getByRole('button',{name:'Fechar',exact:true}).click();
   await navigate(page,'Portfólios');
   await page.getByRole('button',{name:'Personalizar painel',exact:true}).click();
   await fit(page,'Portfolio configuration');
   await page.getByRole('button',{name:'Mostrar todos',exact:true}).click();
   await fit(page,'Portfolio all widgets');
   await navigate(page,'Meu perfil');
   for(const tab of ['Segurança','Notificações','Informações pessoais']){
    await page.locator('.profile-tabs').getByRole('button',{name:tab,exact:true}).click();await page.waitForTimeout(80);await fit(page,tab);
   }
   await page.getByRole('button',{name:'Convidar pessoa',exact:true}).click();
   await dialogFits(page,page.locator('.invitation-dialog'),'invitation');
   await page.locator('.invitation-dialog').getByRole('button',{name:'Fechar',exact:true}).click();
   await navigate(page,'Home');
   await page.screenshot({path:`/tmp/spot-responsive-${width}-${height}-${scale}.png`,fullPage:true});
  }
  if(width>390 && height<=390){
   await navigate(page,'Agenda');
   await page.getByRole('button',{name:'Novo evento',exact:true}).click();
   await dialogFits(page,page.getByRole('dialog',{name:'Novo evento'}),'landscape event');
   await page.keyboard.press('Escape');
   await navigate(page,'Tarefas');
   await page.getByRole('button',{name:'Nova tarefa',exact:true}).click();
   await dialogFits(page,page.locator('.task-editor-dialog[open]'),'landscape task');
   await page.keyboard.press('Escape');
   await navigate(page,'Home');
   await page.screenshot({path:`/tmp/spot-responsive-${width}-${height}-${scale}.png`,fullPage:true});
  }
  await context.close();
 }
 // Public screens must reflow independently of a logged-in session.
 const publicContext=await browser.newContext();
 const publicPage=await publicContext.newPage();
 for(const [width,height] of [[320,740],[390,420],[768,1024]]){
  await publicPage.setViewportSize({width,height});
  for(const path of ['/','/first-access','/first-access/sample-token','/accept-invitation/sample-token','/forgot-password','/reset-password/sample-token','/estoque']){
   await publicPage.goto((process.env.SPOT_BASE_URL||'http://localhost:8000')+path);
   await publicPage.locator('#root main').first().waitFor();
   assert.equal(await publicPage.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true,`Public ${path} at ${width}`);checks++;
  }
 }
 await publicContext.close();
 assert.deepEqual(errors,[]);
 console.log(JSON.stringify({checks,errors,viewports:'320–1440px, portrait/landscape, 100% and 120%, public pages and populated dashboard'}));
} finally {await browser.close();}
