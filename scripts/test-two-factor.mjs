import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import { createBrowserSession } from './browser-fixtures.mjs';
const browser = await chromium.launch();
try {
  for (const width of [320, 1440]) {
    const {page, context} = await createBrowserSession(browser, {admin:true,dismissNotice:true,viewport:{width,height:1000},storage:{'spot.reading-settings':JSON.stringify({scale:120,font:'verdana'})}});
    let enabled = false;
    const secret = 'TESTKEYFORLAYOUTONLY';
    await page.route('**/api/v1/security', r => r.fulfill({json:{two_factor_enabled:enabled,providers:{google:{},microsoft:{}},sessions:[],events:[],preferences:{}}}));
    await page.route('**/api/v1/security/two-factor/setup', r => {
      const body=r.request().postDataJSON();
      return r.fulfill(body.password==='test-password'?{json:{secret,qr_code:'data:image/svg+xml;base64,'+Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240"><rect width="240" height="240" fill="white"/></svg>').toString('base64')}}:{status:422,json:{errors:{password:['Senha incorreta.']}}});
    });
    await page.route('**/api/v1/security/two-factor/confirm', r => {
      if(r.request().postDataJSON().code!=='123456')return r.fulfill({status:422,json:{errors:{code:['Código de autenticação inválido.']}}});
      enabled=true;return r.fulfill({json:{recovery_codes:Array.from({length:8},(_,i)=>`TEST${i}-CODE${i}`)}});
    });
    await page.route('**/api/v1/security/two-factor',r=>{enabled=false;return r.fulfill({json:{message:'2FA desativado'}});});
    await page.locator('.home-label > button').last().click();
    await page.getByRole('menuitem',{name:'Meu perfil',exact:true}).click();
    await page.getByRole('button',{name:'Segurança',exact:true}).click();
    const card=page.locator('.two-factor-card');
    await card.getByRole('button',{name:'Configurar 2FA',exact:true}).click();
    const password=card.locator('#two-factor-password');
    assert.equal(await password.getAttribute('type'),'password');
    await password.fill('wrong');await card.getByRole('button',{name:'Continuar',exact:true}).click();
    await card.getByRole('alert').waitFor();assert.equal(await password.getAttribute('aria-invalid'),'true');
    await password.fill('test-password');await card.getByRole('button',{name:'Continuar',exact:true}).click();
    const code=card.locator('#two-factor-code');await code.waitFor();await code.focus();
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true, 'Setup must fit at 120% reading');
    assert.equal(await card.locator('.two-factor-setup code').getAttribute('translate'),'no');
    if (width === 1440) await card.screenshot({path:'/tmp/spot-two-factor-setup.png'});
    await code.fill('000000');await card.getByRole('button',{name:'Confirmar ativação',exact:true}).click();await card.getByRole('alert').waitFor();
    assert.equal(await code.inputValue(),'000000');
    await code.fill('123456');await card.getByRole('button',{name:'Confirmar ativação',exact:true}).click();await card.locator('.recovery-codes').waitFor();
    assert.equal(await card.locator('.recovery-codes code').count(),8);
    assert.equal(await card.locator('.two-factor-setup').count(),0);
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
    await card.getByRole('button',{name:'Desativar 2FA',exact:true}).click();
    await card.locator('#two-factor-password').fill('test-password');
    await card.getByRole('button',{name:'Cancelar',exact:true}).click();
    assert.equal(await card.getByRole('button',{name:'Desativar 2FA',exact:true}).evaluate(el=>el===document.activeElement),true);
    await card.getByRole('button',{name:'Desativar 2FA',exact:true}).click();
    await card.locator('#two-factor-password').fill('test-password');await card.getByRole('button',{name:'Desativar 2FA',exact:true}).click();
    await card.getByRole('button',{name:'Configurar 2FA',exact:true}).waitFor();assert.equal(await card.locator('.recovery-codes').count(),0);
    if (width === 1440) {
      for (const [language, configure, label, cancel] of [['English','Set up 2FA','Current password','Cancel'],['Español','Configurar 2FA','Contraseña actual','Cancelar']]) {
        await page.getByRole('button',{name:language,exact:true}).click();
        await card.getByRole('button',{name:configure,exact:true}).click();
        await card.getByLabel(label,{exact:true}).waitFor();
        await card.getByRole('button',{name:cancel,exact:true}).click();
      }
    }
    await context.close();
  }
  const manifest = JSON.parse(await fs.readFile('public/build/manifest.json', 'utf8'))['resources/js/app.jsx'];
  const context = await browser.newContext({viewport:{width:320,height:800}});
  const page = await context.newPage();
  await page.route('http://localhost:8000/?two_factor=required', r=>r.fulfill({contentType:'text/html',body:`<!doctype html><html lang="pt-BR"><head><meta name="viewport" content="width=device-width, initial-scale=1">${manifest.css.map(f=>`<link rel="stylesheet" href="/build/${f}">`).join('')}</head><body><div id="root"></div><script>window.__SPOT__={authenticated:false,two_factor_challenge:true}</script><script type="module" src="/build/${manifest.file}"></script></body></html>`}));
  await page.route('**/api/v1/auth/two-factor/challenge',r=>r.fulfill({status:422,json:{message:'Código de autenticação inválido.'}}));
  await page.goto('http://localhost:8000/?two_factor=required');
  await page.getByLabel('Código de autenticação',{exact:true}).fill('bad-code');
  await page.getByRole('button',{name:'Continuar',exact:true}).click();
  await page.getByRole('alert').waitFor();
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
  await context.close();
  console.log('OK: 2FA setup, field errors, confirmation, recovery codes, password masking, cancellation/focus and disabling at 320/1440px with 120% reading.');
} finally { await browser.close(); }
