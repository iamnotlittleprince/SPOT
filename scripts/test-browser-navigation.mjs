import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
const browser = await chromium.launch({headless:true});
try {
  const errors=[];
  const {page,context}=await createBrowserSession(browser,{admin:true,dismissNotice:true,onError:e=>errors.push(e)});
  async function navigate(label) {
    await page.locator('.home-label > button').last().click();
    await page.getByRole('menuitem',{name:label,exact:true}).click();
  }
  async function expectPage(name, title) {
    await page.waitForFunction(name=>new URL(location.href).searchParams.get('page')===name,name);
    await page.waitForFunction(title=>document.title===`Spot · ${title}`,title);
  }
  const initial=await page.evaluate(()=>history.length);
  await navigate('Agenda');
  await expectPage('calendar','Agenda');
  await navigate('Meus documentos');
  await expectPage('documents','Meus documentos');
  await navigate('Meus documentos');
  assert.equal(await page.evaluate(()=>history.length),initial+2);
  await page.goBack();
  await expectPage('calendar','Agenda');
  await page.getByRole('button',{name:'Novo evento',exact:true}).waitFor();
  await page.goBack();
  await expectPage(null,'Home');
  await page.goForward();
  await expectPage('calendar','Agenda');
  await page.getByRole('button',{name:'Ajustes rápidos',exact:true}).click();
  await page.goForward();
  await expectPage('documents','Meus documentos');
  assert.equal(await page.locator('.quick-settings-popover').count(),0);
  await page.reload();
  await expectPage('documents','Meus documentos');
  await page.goBack();
  await expectPage('calendar','Agenda');
  await navigate('Histórico');
  await expectPage('history','Histórico');
  assert.equal(await page.evaluate(()=>new URL(location.href).searchParams.get('page')),'history');
  await page.goBack();
  await expectPage('calendar','Agenda');
  await page.goForward();
  await expectPage('history','Histórico');
  // Unknown page IDs fall back to Home.
  await page.evaluate(()=>{history.pushState(null,'','?page=unknown');dispatchEvent(new PopStateEvent('popstate'));});
  await page.waitForFunction(()=>document.title==='Spot · Home');
  assert.deepEqual(errors,[]);
  await context.close();
  const analyst = await createBrowserSession(browser,{dismissNotice:true,onError:e=>errors.push(e)});
  await analyst.page.evaluate(()=>{history.pushState(null,'','?page=admin-users');dispatchEvent(new PopStateEvent('popstate'));});
  await analyst.page.waitForFunction(()=>document.title==='Spot · Home');
  assert.equal(await analyst.page.getByRole('heading',{name:'Usuários e acessos',exact:true}).count(),0);
  assert.deepEqual(errors,[]);
  await analyst.context.close();
  console.log('OK: URLs, voltar/avançar, recarga, navegação repetida, ramificação do histórico, fechamento de popovers e página desconhecida.');
} finally { await browser.close(); }
