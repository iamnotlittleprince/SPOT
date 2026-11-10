import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch();const errors=[];
try {
for(const width of [320,1440]) {
const {page,context}=await createBrowserSession(browser,{dismissNotice:true,viewport:{width,height:900},storage:{'spot.theme':'dark','spot.reading-settings':JSON.stringify({scale:120,font:'verdana'})},onError:e=>errors.push(e)});
const today=new Date();const date=today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-15';
let event={id:'spot:1',edit_id:1,provider:'spot',title:'Reunião original',description:'Descrição original',start:date+'T13:00:30Z',end:date+'T14:00:30Z',color:'#328052',meeting_url:'https://meet.example.com/original',url:'https://meet.example.com/original',location:'Sala original',project:{id:1,name:'Projeto original'},task:{id:3,title:'Tarefa original',project_id:1}};
let patches=0;
await page.route('**/api/v1/calendar**',async route=>{
if(route.request().method()==='PATCH') {
patches++;assert.ok(route.request().url().endsWith('/events/1/agendas'));
const payload=route.request().postDataJSON();
assert.equal(payload.title,'Reunião alterada');
assert.equal(payload.project_id,'1');assert.equal(payload.task_id,'3');
assert.equal(payload.meeting_url,'https://meet.example.com/original');
if(patches===1)return route.fulfill({status:422,json:{errors:{ends_at:['O horário de término deve ser posterior ao horário de início.']}}});
event={...event,title:payload.title,color:payload.color,description:payload.description,start:date+'T15:00:00Z',end:date+'T16:00:00Z'};
return route.fulfill({json:{event}});
}
assert.equal(route.request().method(),'GET','Editing must not create another event');
return route.fulfill({json:{events:[event],providers:{},projects:[event.project],tasks:[event.task],errors:[]}});
});
await page.goto('http://localhost:8000/');
await page.locator('.home-label > button').last().click();
await page.getByRole('menuitem',{name:'Agenda',exact:true}).click();
await page.locator('.calendar-grid button, .calendar-mobile-agenda button').filter({hasText:'Reunião original'}).locator('visible=true').first().click();
const edit=page.getByRole('button',{name:'Editar evento',exact:true});
await edit.click();
const dialog=page.getByRole('dialog',{name:'Editar evento',exact:true});
assert.equal(await dialog.getByLabel('Título',{exact:true}).inputValue(),'Reunião original');
assert.equal(await dialog.getByLabel('Início',{exact:true}).inputValue(),date+'T10:00:30');
assert.equal(await dialog.getByLabel('Projeto (opcional)',{exact:true}).inputValue(),'1');
assert.equal(await dialog.getByLabel('Tarefa (opcional)',{exact:true}).inputValue(),'3');
assert.equal(await dialog.getByRole('checkbox',{name:'Spot',exact:true}).isDisabled(),false);
await dialog.getByLabel('Título',{exact:true}).fill('Reunião alterada');
await dialog.getByLabel('Início',{exact:true}).fill(date+'T12:00');
await dialog.getByLabel('Fim',{exact:true}).fill(date+'T13:00');
await dialog.getByLabel('Cor do evento',{exact:true}).selectOption('#8b5cc7');
await dialog.getByRole('button',{name:'Salvar alterações',exact:true}).click();
await dialog.locator('#event-ends_at-error').waitFor();
assert.equal(await dialog.getByLabel('Fim',{exact:true}).getAttribute('aria-describedby'),'event-ends_at-error');
assert.equal(await dialog.getByLabel('Título',{exact:true}).inputValue(),'Reunião alterada');
await dialog.getByRole('button',{name:'Salvar alterações',exact:true}).click();
await dialog.waitFor({state:'detached'});
await page.getByText('Evento atualizado.',{exact:true}).waitFor();
await page.locator('.calendar-event-popover h3').filter({hasText:'Reunião alterada'}).waitFor();
assert.equal(patches,2);
await edit.click();
await page.keyboard.press('Escape');
await dialog.waitFor({state:'detached'});
assert.equal(await edit.evaluate(el=>el===document.activeElement),true);
await page.getByRole('button',{name:'Novo evento',exact:true}).click();
assert.equal(await page.getByRole('dialog',{name:'Novo evento',exact:true}).getByLabel('Título',{exact:true}).inputValue(),'');
await context.close();
}
assert.deepEqual(errors,[]);
console.log('Edição: dados preenchidos, fuso, erro associado, PATCH, preservação de vínculos, Escape/foco e 320/1440px a 120% passaram.');
} finally {await browser.close();}
