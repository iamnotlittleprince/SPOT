import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch({headless:true});
try {
 for(const width of [1440,390]) {
  const errors=[];
  const {page,context}=await createBrowserSession(browser,{dismissNotice:true,viewport:{width,height:900},onError:e=>errors.push(e)});
  let outcome='no_photo';
  const absent='A Microsoft não disponibilizou uma foto para esta conta. Você pode enviar uma imagem pelo perfil.';
  const reconnect='Reconecte sua conta Microsoft em Apps e tente buscar a foto novamente.';
  await page.route('**/profile/avatar/microsoft',r=>r.fulfill({status:outcome==='reconnect'?422:200,json:{status:outcome,message:outcome==='no_photo'?absent:outcome==='reconnect'?reconnect:'Foto da Microsoft atualizada.',avatar_url:outcome==='updated'?'/storage/avatars/providers/1/microsoft.png':null}}));
  await page.route('**/storage/avatars/providers/1/microsoft.png',r=>r.fulfill({contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=','base64')}));
  await page.locator('.home-label > button').last().click();await page.getByRole('menuitem',{name:'Meu perfil',exact:true}).click();
  async function sync(){await page.getByRole('button',{name:'Alterar foto',exact:true}).click();const button=page.getByRole('button',{name:'Buscar foto da Microsoft',exact:true});const box=await button.boundingBox();assert.ok(box.x>=0&&box.x+box.width<=width+2);await button.click();}
  await sync();await page.getByRole('status').getByText(absent,{exact:true}).waitFor();
  outcome='reconnect';await sync();await page.getByRole('alert').getByText(reconnect,{exact:true}).waitFor();
  outcome='updated';await sync();await page.getByRole('status').getByText('Foto da Microsoft atualizada.',{exact:true}).waitFor();
  await page.locator('.profile-avatar-large img').evaluate(el=>el.decode());
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true);
  assert.deepEqual(errors,[]);await context.close();
 }
 console.log('OK: buscar foto Microsoft, ausência de foto, reconexão e atualização no perfil desktop/mobile.');
}finally{await browser.close();}
