import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch({headless:true});
try {
 for(const width of [1440,320]) {
  const errors=[];
  const {page,context}=await createBrowserSession(browser,{admin:true,dismissNotice:true,mobile:width===320,viewport:{width,height:800},storage:{'spot.reading-settings':JSON.stringify({scale:120,font:'verdana'})},onError:e=>errors.push(e)});
  const projects=[{id:1,name:'Projeto com um nome longo para testar a atribuição de documentos'},{id:2,name:'Outro projeto'}];
  const tasks=[{id:11,project_id:1,title:'Revisar documentação técnica'},{id:22,project_id:2,title:'Entrega final'}];
  let doc={id:1,name:'relatório.txt',size:20,project_id:null,task_id:null,project:null,task:null,created_at:new Date().toISOString()};
  let reject=false,uploadBody='';
  await page.route('**/sanctum/csrf-cookie',r=>r.fulfill({status:204}));
  await page.route('**/api/v1/documents',r=>{
   if(r.request().method()==='POST') {
    uploadBody=r.request().postData();
    return r.fulfill({status:201,json:{...doc,id:2,name:'novo.txt',project_id:1,task_id:11,project:projects[0],task:tasks[0]}});
   }
   return r.fulfill({json:{documents:[doc],projects,tasks}});
  });
  await page.route('**/api/v1/documents/1',r=>{
   if(reject)return r.fulfill({status:422,json:{errors:{task_id:['A tarefa deve pertencer ao projeto selecionado.']}}});
   const data=r.request().postDataJSON();doc={...doc,...data,project:projects.find(p=>p.id===data.project_id)||null,task:tasks.find(t=>t.id===data.task_id)||null};return r.fulfill({json:doc});
  });
  await page.locator('.home-label > button').last().click();await page.getByRole('menuitem',{name:'Meus documentos',exact:true}).click();
  const section=page.locator('.documents-page');
  await section.getByLabel('Tarefa',{exact:true}).selectOption('11');
  assert.equal(await section.getByLabel('Projeto',{exact:true}).inputValue(),'1');
  assert.equal(await section.getByLabel('Tarefa',{exact:true}).locator('option').count(),2);
  await section.locator('input[type=file]').setInputFiles({name:'novo.txt',mimeType:'text/plain',buffer:Buffer.from('Documento teste')});
  await section.getByRole('status').getByText('novo.txt foi enviado.',{exact:true}).waitFor();
  assert.match(uploadBody,/name="project_id"\r\n\r\n1/);assert.match(uploadBody,/name="task_id"\r\n\r\n11/);
  await section.getByRole('button',{name:'Atribuir relatório.txt',exact:true}).click();
  let dialog=page.getByRole('dialog',{name:'Atribuir documento',exact:true});
  assert.equal(await dialog.evaluate(el=>el.scrollWidth<=el.clientWidth+2),true);
  const bounds=await dialog.boundingBox();assert.ok(bounds.x>=0 && bounds.x+bounds.width<=width+2);
  await page.screenshot({path:`/tmp/spot-document-assignment-${width}.png`});
  await dialog.getByLabel('Tarefa',{exact:true}).selectOption('22');
  assert.equal(await dialog.getByLabel('Projeto',{exact:true}).inputValue(),'2');
  reject=true;await dialog.getByRole('button',{name:'Salvar vínculo',exact:true}).click();
  await dialog.getByRole('alert').waitFor();assert.equal(await dialog.getByLabel('Tarefa',{exact:true}).inputValue(),'22');
  reject=false;await dialog.getByRole('button',{name:'Salvar vínculo',exact:true}).click();await dialog.waitFor({state:'hidden'});
  assert.equal(doc.task_id,22);await section.getByText('Entrega final',{exact:true}).waitFor();
  await section.getByRole('button',{name:'Atribuir relatório.txt',exact:true}).click();
  dialog=page.getByRole('dialog',{name:'Atribuir documento',exact:true});
  await dialog.getByLabel('Projeto',{exact:true}).selectOption('');assert.equal(await dialog.getByLabel('Tarefa',{exact:true}).inputValue(),'');
  await dialog.getByRole('button',{name:'Salvar vínculo',exact:true}).click();await dialog.waitFor({state:'hidden'});assert.equal(doc.project_id,null);assert.equal(doc.task_id,null);
  const trigger=section.getByRole('button',{name:'Atribuir relatório.txt',exact:true});await trigger.click();
  await page.keyboard.press('Shift+Tab');assert.equal(await page.evaluate(()=>document.activeElement.textContent.trim()),'Salvar vínculo');
  await page.keyboard.press('Escape');await dialog.waitFor({state:'hidden'});assert.equal(await trigger.evaluate(el=>el===document.activeElement),true);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true);
  assert.deepEqual(errors,[]);await context.close();
 }
 console.log('OK: envio vinculado, seleção automática do projeto, alteração, remoção, erro recuperável, teclado e mobile a 120%.');
}finally{await browser.close();}
