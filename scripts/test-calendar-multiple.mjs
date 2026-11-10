import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch();const errors=[];
try {
for(const width of [320,1440]) {
const {page,context}=await createBrowserSession(browser,{dismissNotice:true,viewport:{width,height:900},storage:{'spot.reading-settings':JSON.stringify({scale:120,font:'verdana'})},onError:e=>errors.push(e)});
const sent=[];
await page.route('**/api/v1/calendar**',async route=>{
if(route.request().method()==='POST') {
sent.push(route.request().postDataJSON());
if(sent.length===1) return route.fulfill({status:207,json:{complete:false,results:[{provider:'spot',status:'saved'},{provider:'google',status:'failed'}]}});
assert.deepEqual(sent[1],sent[0],'Retry must preserve original payload and request key');
return route.fulfill({status:201,json:{complete:true,results:[{provider:'spot',status:'saved'},{provider:'google',status:'saved'}]}});
}
return route.fulfill({json:{events:[],providers:{google:true,microsoft:true},projects:[],tasks:[],errors:[]}});
});
await page.goto('http://localhost:8000/');
await page.locator('.home-label > button').last().click();
await page.getByRole('menuitem',{name:'Agenda',exact:true}).click();
await page.getByRole('button',{name:'Novo evento',exact:true}).click();
const dialog=page.getByRole('dialog',{name:'Novo evento',exact:true});
await dialog.getByLabel('Título',{exact:true}).fill('Reunião em duas agendas');
await dialog.getByLabel('Início',{exact:true}).fill('2026-11-15T10:00');
await dialog.getByLabel('Fim',{exact:true}).fill('2026-11-15T11:00');
await dialog.getByRole('checkbox',{name:'Spot',exact:true}).uncheck();
await dialog.getByRole('button',{name:'Criar evento',exact:true}).click();
await dialog.locator('#event-providers-error').waitFor();
assert.equal(sent.length,0);
const spot=dialog.getByRole('checkbox',{name:'Spot',exact:true});
await spot.focus();await page.keyboard.press('Space');
assert.equal(await spot.isChecked(),true);
await dialog.getByRole('checkbox',{name:'Google Calendar',exact:true}).check();
await dialog.getByRole('button',{name:'Criar evento',exact:true}).click();
await dialog.locator('.calendar-delivery-results').waitFor();
await dialog.getByText('Gravado',{exact:true}).waitFor();
await dialog.getByText('Não gravado. Confira a conexão em Apps e tente novamente.',{exact:true}).waitFor();
assert.deepEqual(sent[0].providers,['spot','google']);
assert.equal(await dialog.getByLabel('Título',{exact:true}).isDisabled(),true);
assert.equal(await spot.isDisabled(),true);
const bounds=await dialog.boundingBox();assert.ok(bounds.x>=0&&bounds.x+bounds.width<=width+1);
await dialog.getByRole('button',{name:'Tentar novamente',exact:true}).click();
await dialog.waitFor({state:'detached'});
await page.getByText('Evento criado nas agendas selecionadas.',{exact:true}).waitFor();
assert.equal(sent.length,2);
await page.getByRole('button',{name:'Novo evento',exact:true}).click();
assert.equal(await dialog.getByLabel('Título',{exact:true}).isDisabled(),false);
assert.equal(await spot.isChecked(),true);
assert.equal(await dialog.getByRole('checkbox',{name:'Google Calendar',exact:true}).isChecked(),false);
await page.keyboard.press('Escape');
await dialog.waitFor({state:'detached'});
await context.close();
}
assert.deepEqual(errors,[]);
console.log('Múltiplas agendas: seleção por teclado, vazio, envio, resultado parcial, repetição segura e 320/1440px a 120% passaram.');
} finally {await browser.close();}
