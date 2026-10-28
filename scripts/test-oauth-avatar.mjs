import assert from 'node:assert/strict';
import {chromium} from 'playwright';
import {createBrowserSession} from './browser-fixtures.mjs';
const browser=await chromium.launch({headless:true});
try {
 for(const width of [1440,390]) {
  const errors=[];
  const {page,context,user}=await createBrowserSession(browser,{dismissNotice:true,viewport:{width,height:900},onError:e=>errors.push(e)});
  user.avatar_url='/storage/avatars/providers/1/photo.png';
  await page.route('**/storage/avatars/providers/1/photo.png',r=>r.fulfill({contentType:'image/png',body:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=','base64')}));
  await page.reload();
  const image=page.getByRole('img',{name:'Foto de Ana Teste',exact:true}).first();await image.waitFor();
  await image.evaluate(el=>el.decode());assert.equal(await image.getAttribute('referrerpolicy'),'no-referrer');
  user.avatar_url='/storage/avatars/providers/1/missing.png';
  await page.route('**/storage/avatars/providers/1/missing.png',r=>r.fulfill({status:404,body:''}));
  await page.reload();await page.getByRole('img',{name:'Avatar de Ana Teste',exact:true}).first().waitFor();
  assert.equal(await page.getByRole('img',{name:'Foto de Ana Teste',exact:true}).count(),0);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),true);
  assert.deepEqual(errors,[]);await context.close();
 }
 console.log('OK: foto local e retorno às iniciais quando a imagem falha, em desktop e mobile.');
}finally{await browser.close();}
