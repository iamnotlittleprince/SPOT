import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
import { documentText } from '../resources/js/chatFormatting.js';
const browser = await chromium.launch();
let checks = 0; const errors = [];
const check = (actual, expected) => { assert.deepEqual(actual, expected); checks++; };
try {
  for (const width of [320, 1440]) {
    const {page, context} = await createBrowserSession(browser, {dismissNotice:true,viewport:{width,height:1000},userOverrides:{can_chat:true},storage:{'spot.theme':'dark','spot.reading-settings':JSON.stringify({scale:width===320?120:100,font:'verdana'})},onError:error=>errors.push(error)});
    const peer = {id:2,name:'Colega da equipe',can_chat:true,same_team:true};
    const messages = [], history = new Map();
    await page.route('**/api/v1/chat**', route => {
      const request=route.request(), path=new URL(request.url()).pathname.replace('/api/v1/chat','');
      const data=request.method()==='GET'?{}:request.postDataJSON();
      if(path==='')return route.fulfill({json:{contacts:[peer],requests:[],translation_available:true,reviewers_available:true}});
      if(path==='/users/2/messages'&&request.method()==='GET')return route.fulfill({json:{messages,has_more:false}});
      if(path==='/users/2/messages'&&request.method()==='POST') {
        check(data.body,documentText(data.body_format));
        const item={id:messages.length+1,sender_id:1,sender_name:'Ana Teste',is_mine:true,body:data.body,body_format:data.body_format,version:1,can_edit:true,created_at:new Date().toISOString(),editable_until:new Date(Date.now()+86400000).toISOString()};
        messages.push(item);history.set(item.id,[structuredClone(item)]);return route.fulfill({status:201,json:{message:item}});
      }
      if(/^\/messages\/\d+$/.test(path)) {
        const item=messages.find(item=>item.id===Number(path.split('/')[2]));item.body=data.body;item.body_format=data.body_format;item.version++;item.edited_at=new Date().toISOString();history.get(item.id).push(structuredClone(item));return route.fulfill({json:{message:item}});
      }
      if(path.endsWith('/revisions'))return route.fulfill({json:{revisions:history.get(Number(path.split('/')[2]))}});
      if(path.endsWith('/translate'))return route.fulfill({json:{text:'Translated message',target:data.target,version:messages.find(item=>item.id===Number(path.split('/')[2])).version}});
      errors.push('Unexpected formatting request '+path);return route.fulfill({status:404,json:{}});
    });
    await page.locator('.home-label > button').last().click();await page.getByRole('menuitem',{name:'Chat',exact:true}).click();
    await page.getByRole('button',{name:'Colega da equipe Seu time',exact:true}).click();
    const form=page.locator('.chat-composer'), input=page.getByRole('textbox',{name:'Mensagem',exact:true});
    await input.fill('Texto formatado');await input.press('Control+a');
    await form.getByRole('button',{name:'Formatar texto',exact:true}).click();
    const toolbar=form.locator('.chat-format-toolbar');
    for(const name of ['Negrito','Itálico','Sublinhado','Tachado','Destacar texto'])await toolbar.getByRole('button',{name,exact:true}).click();
    await toolbar.getByRole('combobox',{name:'Cor do texto',exact:true}).selectOption('purple');
    await toolbar.getByRole('combobox',{name:'Tamanho do texto',exact:true}).selectOption('large');
    for(const tag of ['strong','em','u','s','mark'])check(await input.locator(tag).count(),1);
    check(await input.locator('[data-chat-color="purple"][data-chat-size="large"]').count(),1);
    const linkButton=toolbar.getByRole('button',{name:'Inserir ou editar link',exact:true});
    await linkButton.click();
    let dialog=page.getByRole('dialog',{name:'Inserir ou editar link',exact:true});
    check(await dialog.locator('..').evaluate(element=>Math.abs(element.getBoundingClientRect().width-innerWidth)<=1),true);
    check(await dialog.getByLabel('Endereço do link',{exact:true}).evaluate(element=>element===document.activeElement),true);
    await dialog.getByRole('button',{name:'Aplicar link',exact:true}).focus();await page.keyboard.press('Tab');
    check(await dialog.getByLabel('Endereço do link',{exact:true}).evaluate(element=>element===document.activeElement),true);
    await dialog.getByLabel('Endereço do link',{exact:true}).fill('javascript:alert(1)');await dialog.getByRole('button',{name:'Aplicar link',exact:true}).click();
    await dialog.getByRole('alert').waitFor();check(messages.length,0);
    await page.keyboard.press('Escape');check(await dialog.count(),0);
    check(await linkButton.evaluate(element=>element===document.activeElement),true);
    await linkButton.click();dialog=page.getByRole('dialog',{name:'Inserir ou editar link',exact:true});
    await dialog.getByLabel('Endereço do link',{exact:true}).fill('https://example.test/');await dialog.getByRole('button',{name:'Aplicar link',exact:true}).click();
    await input.locator('a').waitFor();check(await input.locator('strong').count(),1);check(messages.length,0);
    await input.press('Enter');
    const log=page.getByRole('log',{name:'Mensagens da conversa'});
    await log.getByText('Texto formatado',{exact:true}).waitFor();
    const first=log.locator('.chat-message').first();
    for(const tag of ['strong','em','u','s','mark'])check(await first.locator('.chat-message-body').locator(tag).count(),1);
    check(await first.locator('.chat-message-body a').getAttribute('rel'),'noopener noreferrer');
    check(await first.locator('.chat-message-body a').getAttribute('href'),'https://example.test/');
    await first.getByRole('button',{name:'Editar mensagem',exact:true}).click();
    const editForm=page.locator('.chat-edit-form'), editInput=page.getByRole('textbox',{name:'Editar mensagem',exact:true});
    await editInput.press('Control+a');await editForm.getByRole('button',{name:'Negrito',exact:true}).click();
    await editForm.getByRole('button',{name:'Salvar edição',exact:true}).click();
    await first.getByText('Editada',{exact:true}).waitFor();check(await first.locator('.chat-message-body strong').count(),0);
    await first.getByText('Histórico de edições',{exact:true}).click();await first.locator('.chat-revision strong').waitFor();
    check(await first.locator('.chat-revision').count(),2);await first.getByText('Histórico de edições',{exact:true}).click();
    const more=toolbar.locator('.chat-format-extras > summary');
    await input.fill('Item A');await input.press('Control+a');await toolbar.getByRole('button',{name:'Lista com marcadores',exact:true}).click();
    await input.locator('ul').waitFor();
    await input.press('ArrowRight');await input.press('Control+Shift+Enter');await input.type('Item B');
    check(await input.locator('ul > li').count(),2);
    await more.click();await toolbar.getByRole('button',{name:'Aumentar recuo da lista',exact:true}).click();check(await input.locator('ul ul').count(),1);
    await more.click();await toolbar.getByRole('button',{name:'Diminuir recuo da lista',exact:true}).click();check(await input.locator('ul ul').count(),0);
    await input.press('Enter');await log.locator('.chat-message-body ul').waitFor();check(await log.locator('.chat-message-body ul > li').count(),2);
    await input.fill('Primeiro');await input.press('Control+a');await toolbar.getByRole('button',{name:'Lista numerada',exact:true}).click();check(await input.locator('ol').count(),1);
    await input.press('Enter');await log.locator('.chat-message-body ol').waitFor();
    await input.fill('Trecho citado');await input.press('Control+a');await toolbar.getByRole('button',{name:'Citação',exact:true}).click();await input.press('Enter');await log.locator('.chat-message-body blockquote').waitFor();checks++;
    await input.fill('<script>texto sem executar</script>');await input.press('Control+a');await toolbar.getByRole('button',{name:'Bloco de código',exact:true}).click();await input.press('Enter');await log.locator('.chat-message-body pre code').waitFor();
    check(await log.locator('script').count(),0);
    await input.fill('Exemplo');await input.press('Control+a');await more.click();await toolbar.getByRole('button',{name:'Código em linha',exact:true}).click();check(await input.locator('code').count(),1);
    await more.click();await toolbar.getByRole('button',{name:'Limpar formatação',exact:true}).click();check(await input.locator('code').count(),0);
    await more.click();await toolbar.getByRole('button',{name:'Linha divisória',exact:true}).click();check(await input.locator('hr').count(),1);
    await more.click();await toolbar.getByRole('button',{name:'Desfazer',exact:true}).click();check(await input.locator('hr').count(),0);
    await more.click();await toolbar.getByRole('button',{name:'Refazer',exact:true}).click();check(await input.locator('hr').count(),1);
    await input.fill('Atalho');await input.press('Control+a');
    await input.press('Control+b');await input.press('Control+i');await input.press('Control+u');
    for(const tag of ['strong','em','u'])check(await input.locator(tag).count(),1);
    await input.fill('😀'.repeat(5000));await input.type('x');
    check(await input.evaluate(element=>[...element.textContent].length),5000);
    await input.fill('');
    await input.evaluate(element=>{const transfer=new DataTransfer();transfer.setData('text/html','<p><strong>Texto seguro</strong><a href="javascript:alert(1)">link</a><script>bad()</script><img src="data:image/png;base64,aaaa" /></p>');transfer.setData('text/plain','Texto seguro link');element.dispatchEvent(new ClipboardEvent('paste',{clipboardData:transfer,bubbles:true,cancelable:true}));});
    check(await input.locator('img,script,[onclick],a[href^="javascript:"]').count(),0);
    check(await input.locator('strong').count(),1);
    check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
    await page.screenshot({path:`/tmp/spot-chat-formatting-${width}.png`,fullPage:true});
    if(width===1440){await page.getByRole('button',{name:'Ativar modo claro',exact:true}).click();await page.screenshot({path:'/tmp/spot-chat-formatting-light.png',fullPage:true});}
    await page.emulateMedia({forcedColors:'active',reducedMotion:'reduce'});
    check(await form.evaluate(element=>getComputedStyle(element).getPropertyValue('--chat-ink-purple').trim()),'CanvasText');
    await page.emulateMedia({forcedColors:'none',reducedMotion:'no-preference'});
    for(const [locale,title,urlLabel]of[['en','Insert or edit link','Link address'],['es','Insertar o editar enlace','Dirección del enlace']]) {
      await page.evaluate(locale=>{localStorage.setItem('spot.locale',locale);window.dispatchEvent(new Event('spot:language'));},locale);
      await toolbar.getByRole('button',{name:title,exact:true}).click();const translatedDialog=page.getByRole('dialog',{name:title,exact:true});await translatedDialog.getByLabel(urlLabel,{exact:true}).waitFor();checks++;await page.keyboard.press('Escape');
    }
    await context.close();
  }
  assert.deepEqual(errors,[]);console.log(JSON.stringify({checks,errors}));
} finally { await browser.close(); }
