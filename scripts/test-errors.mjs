import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
const browser=await chromium.launch({headless:true});
try {
  const {page,context}=await createBrowserSession(browser,{admin:true,dismissNotice:true});
  async function agenda() {
    await page.locator('.home-label > button').last().click();
    await page.getByRole('menuitem',{name:'Agenda',exact:true}).click();
  }
  async function home() {
    await page.locator('.home-label > button').last().click();
    await page.getByRole('menuitem',{name:'Home',exact:true}).click();
  }
  for(const status of [401,403,404,405,419,429,500,502,503,504]) {
    await page.route('**/api/v1/calendar?*',route=>route.fulfill({status,json:{message:'Failure'}}));
    await agenda();
    const error=page.getByRole('alertdialog');
    await error.waitFor();
    assert.ok((await error.innerText()).includes(`Erro ${status}`));
    assert.equal(await error.evaluate(el=>el.contains(document.activeElement)),true);
    await page.keyboard.press('Escape');
    assert.equal(await error.count(),0);
    await home();
    await page.unroute('**/api/v1/calendar?*');
  }
  // A failure inside an existing dialog must not create competing focus traps.
  await agenda();
  await page.getByRole('button',{name:'Novo evento',exact:true}).click();
  const eventDialog=page.getByRole('dialog',{name:'Novo evento',exact:true});
  await eventDialog.getByLabel('Título',{exact:true}).fill('Evento de teste');
  await eventDialog.getByLabel('Início',{exact:true}).fill('2026-11-02T10:00');
  await eventDialog.getByLabel('Fim',{exact:true}).fill('2026-11-02T11:00');
  let writes=0;
  page.on('request',request=>{if(request.method()==='POST'&&new URL(request.url()).pathname==='/api/v1/calendar/events') writes++;});
  await page.route('**/sanctum/csrf-cookie',route=>route.fulfill({status:419,json:{message:'Expired'}}));
  await eventDialog.getByRole('button',{name:'Criar evento',exact:true}).click();
  const nestedError=page.getByRole('alertdialog');
  await nestedError.waitFor();
  assert.equal(await nestedError.evaluate(el=>el.contains(document.activeElement)),true);
  await page.keyboard.press('Escape');
  assert.equal(await eventDialog.getByLabel('Título',{exact:true}).inputValue(),'Evento de teste');
  assert.equal(writes,0);
  await page.unroute('**/sanctum/csrf-cookie');
  await page.keyboard.press('Escape');
  await home();
  // The shared error screen also follows the selected language.
  for(const [language,heading,back] of [
    ['English','We could not complete this right now','Return to the screen'],
    ['Español','No se pudo completar ahora','Volver a la pantalla']
  ]) {
    await page.getByRole('button',{name:language,exact:true}).click();
    await page.route('**/api/v1/calendar?*',route=>route.fulfill({status:500,json:{message:'Failure'}}));
    await page.locator('.dashboard-sidebar .nav-group').getByRole('button',{name:language==='English'?'Calendar':'Agenda',exact:true}).click();
    await page.getByRole('heading',{name:heading,exact:true}).waitFor();
    await page.getByRole('button',{name:back,exact:true}).click();
    await page.locator('.dashboard-sidebar .nav-group').getByRole('button',{name:language==='English'?'Home':'Inicio',exact:true}).click();
    await page.unroute('**/api/v1/calendar?*');
  }
  await page.getByRole('button',{name:'Português',exact:true}).click();
  await page.route('**/api/v1/calendar?*',route=>route.abort('internetdisconnected'));
  await agenda();
  await page.getByRole('heading',{name:'Não foi possível conectar ao Spot',exact:true}).waitFor();
  await page.getByRole('button',{name:'Voltar para a tela',exact:true}).click();
  await page.unroute('**/api/v1/calendar?*');
  // React render errors are contained and offer a controlled recovery screen.
  await page.route('**/api/v1/home-dashboard',route=>route.fulfill({json:{projects:[{id:1,name:{invalid:true},status:'planning',progress:10}],tasks:[],teams:[]}}));
  await home();
  await page.getByRole('heading',{name:'Esta tela encontrou um problema',exact:true}).waitFor();
  await page.getByRole('button',{name:'Recarregar página',exact:true}).waitFor();
  await page.unroute('**/api/v1/home-dashboard');
  await page.goto(new URL('/?auth_error=google',page.url()).href);
  await page.getByRole('heading',{name:'Não foi possível conectar sua conta',exact:true}).waitFor();
  await context.close();
  const mobile=await createBrowserSession(browser,{admin:true,dismissNotice:true,viewport:{width:320,height:800},storage:{'spot.reading-settings':JSON.stringify({scale:120,font:'verdana'})}});
  await mobile.page.route('**/api/v1/calendar?*',route=>route.fulfill({status:503,json:{message:'Unavailable'}}));
  await mobile.page.locator('.home-label > button').last().click();
  await mobile.page.getByRole('menuitem',{name:'Agenda',exact:true}).click();
  const mobileError=mobile.page.getByRole('alertdialog');
  await mobileError.waitFor();
  const mobileBounds=await mobileError.boundingBox();
  assert.ok(mobileBounds.x>=0&&mobileBounds.x+mobileBounds.width<=321);
  await mobileError.getByRole('button',{name:'Voltar para a tela',exact:true}).click();
  await mobile.context.close();
  // Actual server-rendered error pages work without JavaScript.
  const staticContext=await browser.newContext({javaScriptEnabled:false,viewport:{width:320,height:800}});
  const staticPage=await staticContext.newPage();
  const response=await staticPage.goto((process.env.SPOT_BASE_URL||'http://localhost:8000')+'/missing-error-test');
  assert.equal(response.status(),404);
  await staticPage.getByRole('heading',{name:'Página ou item não encontrado'}).waitFor();
  assert.ok(await staticPage.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await staticPage.screenshot({path:'/tmp/spot-error-mobile.png',fullPage:true});
  await staticContext.close();
  console.log('OK: HTTP API recovery screens, network, React boundary, OAuth return and actual 404 without JavaScript at 320px.');
} finally { await browser.close(); }
