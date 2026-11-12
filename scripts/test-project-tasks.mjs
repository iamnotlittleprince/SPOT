import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch({headless:true});
try {
 for(const width of [320,1440]) {
  const errors=[];
  const {page,context,user}=await createBrowserSession(browser,{admin:true,dismissNotice:true,viewport:{width,height:1000},storage:{'spot.theme':width===320?'light':'dark','spot.reading-settings':JSON.stringify({scale:120,font:'verdana'})},onError:e=>errors.push(e)});
  const project={id:1,name:'Memorial de projeto com nome extenso para acompanhar tarefas',status:'planning'};
  let tasks=[{id:1,title:'Revisar documentação completa',description:'Descrição completa e extensa com todos os critérios de aceite, preservada nos detalhes.',project_id:1,user_id:user.id,user,project,status:'todo',priority:'medium',can_edit:true,can_delete:true,duration_minutes:60,activity_type_id:1,activity_type_name:'Reunião',worked_on:'2026-11-12'}, {id:2,title:'👩🏽‍💻 Revisão de acessibilidade com acentos',description:'Descrição distinta do título.',project_id:1,user_id:user.id,user,project,status:'todo',priority:'medium',can_edit:true,can_delete:true}];
  const mutations=[];let readOnly=false;
  await page.route('**/sanctum/csrf-cookie',r=>r.fulfill({status:204}));
  await page.route('**/api/v1/projects',r=>r.fulfill({json:[project]}));
  await page.route('**/api/v1/management/access',r=>r.fulfill({json:{}}));
  await page.route('**/api/v1/projects/1/expenses',r=>r.fulfill({json:[]}));
  await page.route('**/api/v1/projects/1/resources',r=>r.fulfill({json:{tasks,documents:[]}}));
  await page.route('**/api/v1/tasks?project_id=*',r=>{
   assert.equal(new URL(r.request().url()).searchParams.get('project_id'),'1');
   return r.fulfill({json:{tasks:readOnly?tasks.map(task=>({...task,can_edit:false,can_delete:false})):tasks,projects:readOnly?[]:[project],can_create:true,current_user:user,project_people:[],activity_types:[{id:1,name:'Reunião'}]}});
  });
  await page.route('**/api/v1/tasks',r=>{
   assert.equal(r.request().method(),'POST');const body=r.request().postDataJSON();mutations.push(body);assert.equal(body.project_id,1);
   const task={...body,id:3,user_id:user.id,user,project,can_edit:true,can_delete:true,activity_type_name:'Reunião'};tasks.push(task);return r.fulfill({status:201,json:task});
  });
  await page.route('**/api/v1/tasks/3',r=>{
   if(r.request().method()==='DELETE'){tasks=tasks.filter(t=>t.id!==3);return r.fulfill({status:204});}
   const body=r.request().postDataJSON();assert.equal(body.project_id,1);mutations.push(body);tasks=tasks.map(t=>t.id===3?{...t,...body}:t);return r.fulfill({json:tasks.find(t=>t.id===3)});
  });
  await page.locator('.home-label > button').last().click();await page.getByRole('menuitem',{name:'Projetos',exact:true}).click();
  await page.getByRole('button',{name:project.name,exact:true}).click();await page.getByRole('button',{name:'Gerenciar tarefas',exact:true}).click();
  const manager=page.locator('#project-tasks-manager');await manager.locator('.kanban-card').first().waitFor();
  for(const task of tasks){
   const button=manager.locator(`[data-task-id="${task.id}"] h2 button`);const visible=await button.innerText();
   assert.equal([...new Intl.Segmenter(undefined,{granularity:'grapheme'}).segment(visible)].length,15);
   assert.equal(await button.getAttribute('aria-label'),task.title);assert.equal(await button.getAttribute('title'),task.title);
  }
  assert.equal(await manager.getByRole('combobox',{name:'Filtrar por projeto',exact:true}).count(),0);
  await manager.getByRole('button',{name:'Revisar documentação completa',exact:true}).click();
  const details=page.getByRole('dialog',{name:'Detalhes da tarefa',exact:true});await details.getByText(tasks[0].description,{exact:true}).waitFor();await page.keyboard.press('Escape');
  await manager.getByRole('button',{name:'Nova tarefa',exact:true}).click();
  const editor=page.getByRole('dialog',{name:'Nova tarefa',exact:true});const field=editor.getByLabel('Nome do projeto',{exact:true});
  assert.equal(await field.inputValue(),'1');assert.equal(await field.isDisabled(),true);
  await editor.getByLabel('Descrição',{exact:true}).fill('Nova atividade do memorial com título comprido');await editor.getByLabel('Tipo de tarefa',{exact:true}).selectOption('1');
  await editor.getByRole('button',{name:'Salvar tarefa',exact:true}).click();await editor.waitFor({state:'hidden'});
  const created=manager.locator('[data-task-id="3"]');await created.waitFor();assert.equal(mutations[0].project_id,1);
  await created.getByRole('button',{name:'Editar tarefa',exact:true}).click();const edit=page.getByRole('dialog',{name:'Editar tarefa',exact:true});await edit.getByLabel('Descrição',{exact:true}).fill('Atividade revisada e vinculada ao projeto');await edit.getByRole('button',{name:'Salvar tarefa',exact:true}).click();await edit.waitFor({state:'hidden'});
  assert.equal(tasks.find(t=>t.id===3).project_id,1);assert.equal(tasks.find(t=>t.id===3).description,'Atividade revisada e vinculada ao projeto');
  await created.getByRole('button',{name:'Excluir tarefa',exact:true}).click();const confirmation=page.getByRole('alertdialog',{name:'Excluir tarefa?',exact:true});await confirmation.waitFor();assert.equal(await confirmation.evaluate(el=>el.contains(document.activeElement)),true);await page.keyboard.press('Escape');assert.equal(await confirmation.count(),0);assert.equal(await created.getByRole('button',{name:'Excluir tarefa',exact:true}).evaluate(el=>el===document.activeElement),true);
  await created.getByRole('button',{name:'Excluir tarefa',exact:true}).click();
  await page.getByRole('alertdialog',{name:'Excluir tarefa?',exact:true}).getByRole('button',{name:'Excluir tarefa',exact:true}).click();await created.waitFor({state:'detached'});
  assert.equal(tasks.some(task=>task.id===3),false);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true);
  await page.getByRole('button',{name:'Fechar quadro de tarefas',exact:true}).click();await page.getByRole('searchbox',{name:'Buscar tarefa no projeto',exact:true}).waitFor();
  readOnly=true;await page.getByRole('button',{name:'Gerenciar tarefas',exact:true}).click();await manager.locator('.kanban-card').first().waitFor();
  assert.equal(await manager.getByRole('button',{name:'Nova tarefa',exact:true}).isDisabled(),true);
  assert.equal(await manager.getByRole('button',{name:'Editar tarefa',exact:true}).count(),0);
  assert.equal(await manager.locator('.add-task-card').count(),0);
  if(width===1440) {
  await page.getByRole('button',{name:'English',exact:true}).click();await page.getByRole('button',{name:'Close task board',exact:true}).waitFor();
  await page.getByRole('button',{name:'Español',exact:true}).click();await page.getByRole('button',{name:'Cerrar tablero de tareas',exact:true}).waitFor();
  await page.getByRole('button',{name:'Português',exact:true}).click();
  }
  await page.screenshot({path:`/tmp/spot-project-board-${width}.png`,fullPage:true});
  assert.deepEqual(errors,[]);await context.close();
 }
 console.log('OK: quadro por projeto, títulos de 15 caracteres com Unicode, detalhes completos, criação/edição vinculadas, confirmação por teclado e 320/1440px a 120%.');
}finally{await browser.close();}
