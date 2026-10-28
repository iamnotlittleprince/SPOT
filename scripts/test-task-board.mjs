import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch({headless:true});
try {
 for(const width of [1440,390]) {
  const errors=[];
  const {page,context,user}=await createBrowserSession(browser,{admin:true,dismissNotice:true,mobile:width===390,viewport:{width,height:900},storage:{'spot.reading-settings':JSON.stringify({scale:120,font:'verdana'})},onError:e=>errors.push(e)});
  const project={id:1,name:'Projeto com nome longo para testar o quadro'};
  let task={id:1,title:'Mover entrega',description:'Mover entrega',project_id:1,project,user,status:'todo',priority:'medium',can_edit:true,can_delete:true};
  const readOnly={...task,id:2,title:'Consulta',description:'Consulta',can_edit:false,can_delete:false};
  let reject=false,requests=0;
  await page.route('**/sanctum/csrf-cookie',r=>r.fulfill({status:204}));
  await page.route('**/api/v1/tasks',r=>r.fulfill({json:{tasks:[task,readOnly],projects:[project],can_create:true}}));
  await page.route('**/api/v1/tasks/1/status',async r=>{
   requests++;assert.equal(r.request().method(),'PATCH');assert.deepEqual(Object.keys(r.request().postDataJSON()),['status']);
   if(reject)return r.fulfill({status:422,json:{message:'Não foi possível mudar o status. Tente novamente.'}});
   task={...task,...r.request().postDataJSON()};return r.fulfill({json:task});
  });
  async function navigate(){await page.locator('.home-label > button').last().click();await page.getByRole('menuitem',{name:'Tarefas',exact:true}).click();await page.locator('.kanban-card[data-task-id="1"]').waitFor();}
  await navigate();
  const card=()=>page.locator('.kanban-card[data-task-id="1"]');
  const column=status=>page.locator(`[data-drop-status="${status}"]`);
  assert.equal(await page.locator('.kanban-card[data-task-id="2"]').getAttribute('draggable'),'false');
  assert.equal(await page.locator('.kanban-card[data-task-id="2"] select').count(),0);
  if(width===1440){
   await card().dragTo(column('in_progress').locator('header').first());
  }else{
   const handle=card().getByRole('button',{name:'Arrastar tarefa',exact:true});await handle.scrollIntoViewIfNeeded();
   const bounds=await handle.boundingBox();const x=bounds.x+bounds.width/2,y=bounds.y+bounds.height/2;
   const cdp=await context.newCDPSession(page);
   await cdp.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x,y}]});
   await cdp.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:x+20,y}]});
   await page.locator('.kanban-board').evaluate(el=>{el.style.scrollSnapType='none';el.scrollLeft=el.querySelectorAll('section')[1].offsetLeft-el.querySelector('section').offsetLeft;});
   const target=await column('in_progress').boundingBox();
   await cdp.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:target.x+target.width/2,y:target.y+70}]});
   await cdp.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});await cdp.detach();
  }
  await column('in_progress').locator('[data-task-id="1"]').waitFor();await page.getByRole('status').getByText('Tarefa movida para Em andamento.',{exact:true}).waitFor();assert.equal(task.status,'in_progress');
  const before=requests;
  if(width===1440)await card().dragTo(column('in_progress').locator('header').first());
  assert.equal(requests,before);
  const select=card().getByRole('combobox',{name:'Status de Mover entrega',exact:true});
  await select.selectOption('review');await page.getByRole('status').getByText('Tarefa movida para Em revisão.',{exact:true}).waitFor();
  assert.equal(await card().getByRole('combobox').evaluate(el=>el===document.activeElement),true);
  reject=true;await card().getByRole('combobox').selectOption('done');await page.getByRole('alert').waitFor();await column('review').locator('[data-task-id="1"]').waitFor();assert.equal(task.status,'review');
  reject=false;await card().getByRole('combobox').selectOption('done');await page.getByRole('status').getByText('Tarefa movida para Concluído.',{exact:true}).waitFor();
  assert.equal(task.status,'done');await page.reload();await page.locator('[data-drop-status="done"] [data-task-id="1"]').waitFor();
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true);
  assert.deepEqual(errors,[]);await context.close();
 }
 console.log('OK: arraste com mouse e toque, colunas vazias, seletor acessível, persistência, rollback e permissões no quadro desktop/mobile a 120%.');
}finally{await browser.close();}
