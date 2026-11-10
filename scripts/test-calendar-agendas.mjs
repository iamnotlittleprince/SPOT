import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch();const errors=[];
try {
for(const width of [320,1440]) {
const {page,context}=await createBrowserSession(browser,{dismissNotice:true,viewport:{width,height:900},storage:{'spot.theme':'dark','spot.reading-settings':JSON.stringify({scale:120,font:'verdana'})},onError:e=>errors.push(e)});
const today=new Date();const date=today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-15';
let item={id:'spot:1',edit_id:1,provider:'spot',providers:['spot','google'],title:'Evento vinculado',start:date+'T13:00:00Z',end:date+'T14:00:00Z'};
const sent=[];
await page.route('**/api/v1/calendar**',async route=>{
if(route.request().method()==='PATCH') {
assert.ok(route.request().url().endsWith('/events/1/agendas'));
sent.push(route.request().postDataJSON());
if(sent.length===1)return route.fulfill({status:207,json:{complete:false,results:[{provider:'google',action:'update',status:'saved'},{provider:'microsoft',action:'add',status:'failed'},{provider:'spot',action:'remove',status:'waiting'}]}});
assert.deepEqual(sent[1],sent[0]);
item={...item,id:'google:existing',edit_id:2,provider:'google',providers:['google','microsoft']};
return route.fulfill({json:{complete:true,event:item,results:[{provider:'google',status:'saved'},{provider:'microsoft',status:'saved'},{provider:'spot',status:'removed'}]}});
}
return route.fulfill({json:{events:[item],providers:{google:true,microsoft:true},projects:[],tasks:[],errors:[]}});
});
await page.goto('http://localhost:8000/');
await page.locator('.home-label > button').last().click();
await page.getByRole('menuitem',{name:'Agenda',exact:true}).click();
await page.locator('.calendar-grid button, .calendar-mobile-agenda button').filter({hasText:'Evento vinculado'}).locator('visible=true').first().click();
await page.getByRole('button',{name:'Editar evento',exact:true}).click();
const dialog=page.getByRole('dialog',{name:'Editar evento',exact:true});
const spot=dialog.getByRole('checkbox',{name:'Spot',exact:true});
const google=dialog.getByRole('checkbox',{name:'Google Calendar',exact:true});
const outlook=dialog.getByRole('checkbox',{name:'Outlook',exact:true});
assert.equal(await spot.isEnabled(),true);assert.equal(await spot.isChecked(),true);assert.equal(await google.isChecked(),true);
await spot.focus();await page.keyboard.press('Space');
await google.uncheck();
await dialog.getByRole('button',{name:'Salvar alterações',exact:true}).click();
await dialog.locator('#event-providers-error').waitFor();assert.equal(sent.length,0);
await google.check();await outlook.check();
await dialog.getByRole('button',{name:'Salvar alterações',exact:true}).click();
await dialog.getByText('Aguardando a gravação nas agendas selecionadas.',{exact:true}).waitFor();
assert.deepEqual(sent[0].providers,['google','microsoft']);
assert.equal(await outlook.isDisabled(),true);
await dialog.getByRole('button',{name:'Tentar novamente',exact:true}).click();
await dialog.waitFor({state:'detached'});
await page.getByText('Evento atualizado.',{exact:true}).waitFor();
await page.getByRole('button',{name:'Editar evento',exact:true}).click();
assert.equal(await spot.isChecked(),false);assert.equal(await google.isChecked(),true);assert.equal(await outlook.isChecked(),true);
await page.keyboard.press('Escape');
await dialog.waitFor({state:'detached'});
assert.equal(sent.length,2);
await context.close();
}
assert.deepEqual(errors,[]);
console.log('Agendas na edição: opções habilitadas, cópias pré-selecionadas, teclado, validação, falha parcial/repetição e 320/1440px a 120% passaram.');
} finally {await browser.close();}
