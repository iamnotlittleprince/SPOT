import { createBrowserSession } from './browser-fixtures.mjs';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { chromium } from 'playwright';
import { interfaceTranslations } from '../resources/js/translations.js';
assert.deepEqual(Object.keys(interfaceTranslations.en).sort(), Object.keys(interfaceTranslations.es).sort());
const base = process.env.SPOT_BASE_URL || 'http://localhost:8000';
const browser = await chromium.launch({headless:true});
const failures=[]; const missing=new Set(); let checks=0;
const names={pt:'Português',en:'English',es:'Español'};
const tr=(text,lang)=>interfaceTranslations[lang]?.[text]||text;
async function language(page,lang){await page.getByRole('button',{name:names[lang],exact:true}).click();await page.waitForFunction(lang=>document.documentElement.lang===(lang==='pt'?'pt-BR':lang),lang); checks++;}
async function navigate(page,label,lang){
 await page.locator('.home-label > button').last().click();
 await page.getByRole('menuitem',{name:tr(label,lang),exact:true}).click();
 await page.waitForTimeout(120);
}
async function audit(page,lang){
 if(lang==='pt')return;
 const texts=await page.evaluate(()=>{
  const walker=document.createTreeWalker(document.getElementById('root'),NodeFilter.SHOW_TEXT);const out=[];let n;
  while(n=walker.nextNode())if(n.parentElement?.getClientRects().length&&!n.parentElement.closest('[translate="no"],script,style'))out.push(n.textContent.trim());
  for(const el of document.querySelectorAll('[placeholder],[title],[aria-label]'))if(el.getClientRects().length&&!el.closest('[translate="no"]'))for(const attr of ['placeholder','title','aria-label'])if(el.hasAttribute(attr))out.push(el.getAttribute(attr));
  return out;
 });
 for(const text of texts){if(interfaceTranslations[lang][text]&&interfaceTranslations[lang][text]!==text&&!Object.values(interfaceTranslations[lang]).includes(text))failures.push(`Untranslated (${lang}): ${text}`);else if(/[ãõç]|\b(?:Nenhum|Nenhuma|Carregando|projeto|tarefas|concluído)\b/.test(text))missing.add(`${lang}: ${text}`);}
 checks++;
}
async function checkTranslator() {
 const page = await browser.newPage();
 await page.route(base + '/__language-test', route => route.fulfill({contentType:'text/html; charset=utf-8', body:'<div id="root"><h1>Novo projeto</h1><button title="Mostrar senha">Mostrar senha</button><input placeholder="Nome" value="Área restrita"><span translate="no">Planejamento</span></div>'}));
 for (const file of ['i18n.js', 'translations.js']) {
  await page.route(base + '/__language-test/' + file, async route => route.fulfill({contentType:'text/javascript', body:await fs.readFile('resources/js/' + file,'utf8')}));
 }
 await page.goto(base + '/__language-test');
 const results = await page.evaluate(async () => {
  const { observeTranslations, setLanguage, getLanguage, getFormatLocale } = await import('/__language-test/i18n.js');
  const wait = () => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
  const results=[];
  setLanguage('en'); let stop=observeTranslations('en');
  const button=document.querySelector('button'), input=document.querySelector('input');
  results.push(document.querySelector('h1').textContent==='New project',button.title==='Show password');
  button.textContent='Ocultar senha'; button.title='Ocultar senha'; input.placeholder='Buscar documentos...';
  await wait();
  results.push(button.textContent==='Hide password',button.title==='Hide password',input.placeholder==='Search documents...');
  stop();setLanguage('es');stop=observeTranslations('es');await wait();
  results.push(button.title==='Ocultar contraseña',input.placeholder==='Buscar documentos...',getFormatLocale()==='es-ES');
  stop();setLanguage('pt');stop=observeTranslations('pt');await wait();
  results.push(button.title==='Ocultar senha',input.placeholder==='Buscar documentos...',input.value==='Área restrita',document.querySelector('[translate="no"]').textContent==='Planejamento');
  stop();setLanguage('invalid');results.push(getLanguage()==='pt');
  return results;
 });
 assert.ok(results.every(Boolean), 'Dynamic text, attributes, values and language restoration: '+JSON.stringify(results)); checks+=results.length;
 await page.close();
}
try {
 await checkTranslator();
 for(const admin of [false,true]){
  const {page,context}=await createBrowserSession(browser, { admin, dismissNotice:true, onError: error => failures.push(error) });
  for(const lang of ['en','es','pt','en']){
   await language(page,lang);
   for(const label of ['Home','Portfólios','Novo projeto','Projetos','Tarefas','Agenda','Meus documentos','Histórico','Caixa de entrada','Meu perfil',...(admin?['Usuários e acessos']:[])]){
    await navigate(page,label,lang);await audit(page,lang);
    if(label==='Novo projeto'&&!admin){assert.equal(await page.locator('#restricted-project-title').innerText(),tr('Área restrita',lang));assert.equal(await page.locator('.new-project-form').count(),0);checks++;}
    if(label==='Portfólios'&&!admin){assert.equal(await page.locator('#restricted-portfolio-title').innerText(),tr('Área restrita',lang));checks++;}
    if(label==='Meu perfil'){
     await page.getByRole('button',{name:tr('Convidar pessoa',lang),exact:true}).click();await page.locator('.invitation-dialog').waitFor();await page.waitForTimeout(100);await audit(page,lang);
     await page.locator('.invitation-dialog').getByRole('button',{name:tr('Fechar',lang),exact:true}).click();
     for(const tab of ['Segurança','Notificações','Informações pessoais']){await page.locator('.profile-tabs').getByRole('button',{name:tr(tab,lang),exact:true}).click();await page.waitForTimeout(100);await audit(page,lang);}
    }
    if(label==='Agenda'){
      const expected=new Intl.DateTimeFormat({pt:'pt-BR',en:'en-US',es:'es-ES'}[lang],{month:'long',year:'numeric'}).format(new Date());
      assert.equal(await page.locator('.calendar-shell > header h2').textContent(),expected); checks++;
    }
    if(label==='Novo projeto'&&admin){assert.equal(await page.locator('.new-project-form').count(),1);checks++;}
   }
  }
  if(admin){
    await navigate(page,'Novo projeto','en');const input=page.locator('.new-project-form input').first();await input.fill('Plano e ação');
    for(const lang of ['es','pt','en']){await language(page,lang);assert.equal(await input.inputValue(),'Plano e ação');await audit(page,lang);checks++;}
  }
  const search=page.getByRole('textbox',{name:tr('Pesquisar no Spot','en'),exact:true});await search.fill('New project');await page.getByRole('option').first().waitFor();checks++;await search.fill('');
  await navigate(page,'Home','en');
  assert.equal(await page.locator('.project-row [translate="no"]').innerText(),'Planejamento');checks++;
  await page.reload();await page.waitForFunction(()=>document.documentElement.lang==='en');
  await page.locator('.tutorial-card').getByRole('button',{name:'Got it',exact:true}).click();checks++;
  await context.close();
 }
 console.log(JSON.stringify({checks,failures:[...new Set(failures)],review:[...missing]},null,2));
 assert.equal(failures.length,0,'Language checks failed');
} finally {await browser.close();}
