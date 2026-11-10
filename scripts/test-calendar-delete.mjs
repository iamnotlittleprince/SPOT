import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch();const errors=[];
try {
for(const width of [320,1440]) {
const {page,context}=await createBrowserSession(browser,{dismissNotice:true,viewport:{width,height:900},storage:{'spot.theme':'dark','spot.reading-settings':JSON.stringify({scale:120,font:'verdana'})},onError:e=>errors.push(e)});
const today=new Date();const date=today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-15';
let events=[1,2].map(id=>({id:'spot:'+id,edit_id:id,provider:'spot',title:'Evento '+id,start:date+'T13:00:00Z',end:date+'T14:00:00Z'}));
let deletes=0;
await page.route('**/api/v1/calendar**',async route=>{
if(route.request().method()==='DELETE') {
deletes++;assert.ok(route.request().url().endsWith('/events/1'));
if(deletes===1) return route.fulfill({status:422,json:{message:'Não foi possível excluir o evento. Tente novamente.'}});
events=events.filter(event=>event.edit_id!==1);
return route.fulfill({json:{message:'Evento excluído.'}});
}
assert.equal(route.request().method(),'GET');
return route.fulfill({json:{events,providers:{},projects:[],tasks:[],errors:[]}});
});
await page.goto('http://localhost:8000/');
await page.locator('.home-label > button').last().click();
await page.getByRole('menuitem',{name:'Agenda',exact:true}).click();
await page.locator('.calendar-grid button, .calendar-mobile-agenda button').filter({hasText:'Evento 1'}).locator('visible=true').first().click();
const trigger=page.getByRole('button',{name:'Excluir evento',exact:true});
await trigger.click();
const dialog=page.getByRole('alertdialog',{name:'Excluir evento?',exact:true});
await dialog.waitFor();
const cancel=dialog.getByRole('button',{name:'Cancelar',exact:true});
assert.equal(await cancel.evaluate(el=>el===document.activeElement),true);
await page.keyboard.press('Shift+Tab');
assert.equal(await dialog.getByRole('button',{name:'Sim, excluir evento',exact:true}).evaluate(el=>el===document.activeElement),true);
await page.keyboard.press('Escape');
await dialog.waitFor({state:'detached'});
assert.equal(await trigger.evaluate(el=>el===document.activeElement),true);
assert.equal(deletes,0);
await trigger.click();await cancel.click();assert.equal(deletes,0);
await trigger.click();
const bounds=await dialog.boundingBox();assert.ok(bounds.x>=0&&bounds.x+bounds.width<=width+1&&bounds.y>=0&&bounds.y+bounds.height<=900);
await dialog.getByRole('button',{name:'Sim, excluir evento',exact:true}).click();
await dialog.getByRole('alert').waitFor();
assert.equal(deletes,1);
await dialog.getByRole('button',{name:'Sim, excluir evento',exact:true}).click();
await dialog.waitFor({state:'detached'});
await page.getByText('Evento excluído.',{exact:true}).waitFor();
assert.equal(await page.locator('.calendar-shell button').filter({hasText:'Evento 1'}).count(),0);
assert.ok(await page.locator('.calendar-shell button').filter({hasText:'Evento 2'}).count()>0);
await page.waitForFunction(() => document.activeElement?.textContent.trim() === 'Novo evento');
assert.equal(await page.getByRole('button',{name:'Novo evento',exact:true}).evaluate(el=>el===document.activeElement),true);
assert.equal(deletes,2);
await context.close();
}
assert.deepEqual(errors,[]);
console.log('Exclusão: confirmação, Cancelar/Escape, foco inicial/retorno, erro/repetição, preservação de outras cópias e 320/1440px a 120% passaram.');
} finally {await browser.close();}
