import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch();
const errors=[];
try {
for(const width of [320,1440]) {
const {page,context}=await createBrowserSession(browser,{dismissNotice:true,viewport:{width,height:900},storage:{'spot.reading-settings':JSON.stringify({scale:120,font:'verdana'})},onError:e=>errors.push(e)});
let saved;
const projects=[{id:1,name:'Projeto com nome extenso para planejamento da equipe'},{id:2,name:'Outro projeto'}];
const tasks=[{id:3,title:'Preparar reunião',project_id:1},{id:4,title:'Outra tarefa',project_id:2}];
await page.route('**/api/v1/calendar**',async route=>{
if(route.request().method()==='POST') {
const payload=route.request().postDataJSON();
assert.equal(payload.color,'#8b5cc7');assert.equal(payload.project_id,'1');assert.equal(payload.task_id,'3');assert.equal(payload.meeting_url,'https://meet.example.com/sala');
saved={id:'spot:1',title:payload.title,description:payload.description,provider:'spot',start:new Date(payload.starts_at).toISOString(),end:new Date(payload.ends_at).toISOString(),color:payload.color,url:payload.meeting_url,project:projects[0],task:tasks[0]};
return route.fulfill({status:201,json:{event:saved}});
}
return route.fulfill({json:{events:saved?[saved]:[],projects,tasks,providers:{},errors:[]}});
});
await page.goto('http://localhost:8000/');
await page.locator('.home-label > button').last().click();
await page.getByRole('menuitem',{name:'Agenda',exact:true}).click();
const trigger=page.getByRole('button',{name:'Novo evento',exact:true});
await trigger.click();
const dialog=page.getByRole('dialog',{name:'Novo evento',exact:true});
await dialog.getByLabel('Título',{exact:true}).fill('Reunião colorida');
const day=new Date();const date=day.getFullYear()+'-'+String(day.getMonth()+1).padStart(2,'0')+'-15';
await dialog.getByLabel('Início',{exact:true}).fill(date+'T10:00');
await dialog.getByLabel('Fim',{exact:true}).fill(date+'T11:00');
await dialog.getByLabel('Cor do evento',{exact:true}).selectOption('#8b5cc7');
await dialog.getByLabel('Link da reunião (opcional)',{exact:true}).fill('https://meet.example.com/sala');
await dialog.getByLabel('Tarefa (opcional)',{exact:true}).selectOption('3');
assert.equal(await dialog.getByLabel('Projeto (opcional)',{exact:true}).inputValue(),'1');
assert.equal(await dialog.getByLabel('Tarefa (opcional)',{exact:true}).locator('option').count(),2);
await dialog.getByLabel('Projeto (opcional)',{exact:true}).selectOption('2');
assert.equal(await dialog.getByLabel('Tarefa (opcional)',{exact:true}).inputValue(),'');
await dialog.getByLabel('Projeto (opcional)',{exact:true}).selectOption('1');
await dialog.getByLabel('Tarefa (opcional)',{exact:true}).selectOption('3');
await dialog.getByRole('button',{name:'Criar evento',exact:true}).scrollIntoViewIfNeeded();
const bounds=await dialog.boundingBox();
assert.ok(bounds.x>=0 && bounds.x+bounds.width<=width+1);
await dialog.getByRole('button',{name:'Criar evento',exact:true}).click();
await dialog.waitFor({state:'detached'});
const events=page.locator('.calendar-grid button, .calendar-mobile-agenda button').filter({hasText:'Reunião colorida'});
await events.locator('visible=true').first().click();
const detail=page.locator('.calendar-event-popover');
await detail.getByText('Preparar reunião',{exact:true}).waitFor();
assert.equal(await detail.getByRole('link').getAttribute('href'),'https://meet.example.com/sala');
assert.ok(await detail.getByText('Roxo',{exact:true}).isVisible());
await detail.getByRole('button',{name:'Fechar evento'}).click();
await trigger.click();await page.keyboard.press('Escape');
await dialog.waitFor({state:'detached'});
assert.equal(await trigger.evaluate(el=>el===document.activeElement),true);
await context.close();
}
assert.deepEqual(errors,[]);
console.log('Agenda: criação, cor, link, projeto/tarefa, filtros, Escape/foco e 320/1440px a 120% passaram.');
} finally {await browser.close();}
