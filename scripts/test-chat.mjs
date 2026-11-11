import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';

const browser = await chromium.launch();
let checks = 0;
const errors = [];
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jK1sAAAAASUVORK5CYII=', 'base64');
const check = (actual, expected) => { assert.deepEqual(actual, expected); checks++; };
try {
  for (const width of [320, 1440]) {
    const { page, context } = await createBrowserSession(browser, {
      dismissNotice: true, viewport: { width, height: 1000 }, userOverrides: { can_chat: true, ...(width === 320 ? {id:undefined} : {}) },
      storage: { 'spot.theme': 'dark', 'spot.reading-settings': JSON.stringify({ scale: width === 320 ? 120 : 100, font: 'verdana' }) },
      onError: error => errors.push(error),
    });
    const teammate = { id: 2, name: 'Gerente do time com um nome bastante longo para testar leitura', can_chat: true, same_team: true, pending: false };
    const outsider = { id: 3, name: 'Pessoa de outra equipe', can_chat: false, same_team: false, pending: false };
    const now = new Date();
    let messages = [
      { id: 1, sender_id: 2, body: 'Olá, equipe! <b>Texto sem HTML</b>', version: 1, created_at: now.toISOString(), can_edit: false },
      { id: 2, sender_id: '1', is_mine: true, sender_name: 'Ana Teste', body: 'Texto antigo', version: 1, created_at: new Date(now - 25 * 3600000).toISOString(), editable_until: new Date(now - 3600000).toISOString(), can_edit: false },
    ];
    let requests = [];
    let canReview = false;
    let canStartWithoutApproval = false;
    let outsiderMessages = [];
    let translationAvailable = true;
    let reviewersAvailable = true;
    let chatDenied = false;
    const originals = {};
    page.on('dialog', dialog => { errors.push('Unexpected native dialog: ' + dialog.message()); dialog.dismiss(); });
    await page.route('**/api/v1/chat**', async route => {
      const request = route.request();
      const url = new URL(request.url());
      const path = url.pathname.replace('/api/v1/chat', '');
      const raw = request.postDataBuffer()?.toString('utf8') || '';
      const multipart = request.headers()['content-type']?.includes('multipart/form-data');
      const body = request.method() === 'GET' ? {} : multipart ? { descriptions: Object.fromEntries([...raw.matchAll(/name="attachment_descriptions\[(\d+)\]"\r\n\r\n([\s\S]*?)\r\n--/g)].map(match=>[match[1],match[2]])), body_format: JSON.parse(raw.match(/name="body_format"\r\n\r\n([\s\S]*?)\r\n--/)?.[1] || 'null'), body: raw.match(/name="body"\r\n\r\n([\s\S]*?)\r\n--/)?.[1] || '', files: [...raw.matchAll(/name="attachments\[\]"; filename="([^"]+)"/g)].map(match => match[1]) } : request.postDataJSON();
      if (path.match(/^\/attachments\/\d+\/preview$/)) return route.fulfill({body:png,contentType:'image/png'});
      if (path.match(/^\/attachments\/\d+\/download$/)) return route.fulfill({body:'Arquivo de teste',contentType:'application/octet-stream',headers:{'Content-Disposition':'attachment; filename="entrega.txt"'}});
      if (path === '' && chatDenied) return route.fulfill({ status: 403, json: { message: 'Seu perfil não tem acesso ao chat.' } });
      if (path === '') return route.fulfill({ json: { contacts: [teammate, outsider].map(person => ({...person, can_chat: canStartWithoutApproval || person.can_chat, administrative_access: canStartWithoutApproval})), requests, can_review: canReview, can_start_without_approval: canStartWithoutApproval, reviewers_available: reviewersAvailable, reviewers: reviewersAvailable ? [{id:9,name:'Administrador escolhido'},{id:10,name:'Gerente administrador'}] : [], translation_available: translationAvailable } });
      if (path.match(/^\/users\/\d+\/messages$/) && request.method() === 'GET') {
        return route.fulfill({ json: { messages: path.includes('/2/') ? messages : outsiderMessages, has_more: false } });
      }
      if (['/users/2/messages','/users/3/messages'].includes(path) && request.method() === 'POST') {
        const targetMessages = path.includes('/2/') ? messages : outsiderMessages;
        const item = { id: 10 + targetMessages.length + (path.includes('/3/') ? 100 : 0), sender_id: '1', is_mine: true, sender_name: 'Ana Teste', attachments: (body.files || []).map((name,index) => ({id:(name.endsWith('.png')?700:500)+index,name,size:16,mime_type:name.endsWith('.png')?'image/png':'text/plain',alt_text:body.descriptions?.[index] || null,preview_url:name.endsWith('.png')?`/api/v1/chat/attachments/${700+index}/preview`:null,download_url:`/api/v1/chat/attachments/${(name.endsWith('.png')?700:500)+index}/download`})), body: body.body, body_format: body.body_format || null, version: 1, created_at: now.toISOString(), editable_until: new Date(now.getTime() + 86400000).toISOString(), can_edit: true };
        originals[item.id] = item.body;
        targetMessages.push(item);
        return route.fulfill({ status: 201, json: { message: item } });
      }
      if (path.match(/^\/messages\/\d+$/) && request.method() === 'PATCH') {
        const item = messages.find(message => message.id === Number(path.split('/')[2]));
        check(body.version, item.version);
        item.body = body.body; item.body_format = body.body_format || null; item.version++; item.edited_at = now.toISOString();
        return route.fulfill({ json: { message: item } });
      }
      if (path.endsWith('/translate')) return route.fulfill({ json: { text: 'Automatic translation <script> is plain text', target: body.target, version: messages.find(item => item.id === Number(path.split('/')[2])).version } });
      if (path.endsWith('/revisions')) {
        const id = Number(path.split('/')[2]);
        return route.fulfill({ json: { revisions: [{ version: 1, body: originals[id], created_at: now.toISOString() }, { version: 2, body: messages.find(item => item.id === id).body, created_at: now.toISOString() }] } });
      }
      if (path === '/users/3/requests') {
        check(body.reviewer_id, 9);
        outsider.pending = true;
        requests = [{ id: 7, requester_id: 1, requester_name: 'Ana Teste', first_name: 'Ana Teste', second_name: outsider.name, status: 'pending', assigned_reviewer_id: body.reviewer_id, assigned_reviewer_name: 'Administrador escolhido', reason: body.reason, created_at: now.toISOString(), can_decide: false }];
        return route.fulfill({ status: 201, json: { request_id: 7 } });
      }
      if (path === '/requests/7/decision') {
        check(body.decision, 'approve');
        requests[0] = { ...requests[0], status: 'approved', can_decide: false, can_revoke: true };
        outsider.can_chat = true; outsider.pending = false;
        return route.fulfill({ json: { status: 'approved' } });
      }
      if (path === '/requests/7/revoke') {
        requests[0] = { ...requests[0], status: 'revoked', can_revoke: false };
        outsider.can_chat = false;
        return route.fulfill({ json: { status: 'revoked' } });
      }
      errors.push(`Unexpected chat request ${request.method()} ${path}`);
      return route.fulfill({ status: 404, json: {} });
    });
    // The page menu keeps the Chat entry available at every viewport size.
    await page.locator('.home-label > button').last().click();
    await page.getByRole('menuitem', { name: 'Chat', exact: true }).click();
    await page.getByRole('heading', { name: 'Chat', exact: true }).waitFor();
    await page.getByRole('button', { name: teammate.name + ' Seu time', exact: true }).click();
    const log = page.getByRole('log', { name: 'Mensagens da conversa' });
    await log.getByText(messages[0].body, { exact: true }).waitFor();
    check(await log.locator('b,script').count(), 0);
    check(await log.getByRole('button', { name: 'Editar mensagem', exact: true }).count(), 0);
    await page.getByLabel('Mensagem', { exact: true }).fill('Olá! Mensagem enviada pelo chat.');
    check(await page.locator('#chat-body').evaluate(element => getComputedStyle(element).outlineStyle), 'none');
    check(await page.locator('.chat-composer-input').evaluate(element => getComputedStyle(element).outlineStyle), 'solid');
    await page.getByRole('button', { name: 'Enviar mensagem', exact: true }).click();
    await log.getByText('Olá! Mensagem enviada pelo chat.', { exact: true }).waitFor();
    check((await page.getByLabel('Mensagem', { exact: true }).innerText()).trim(), '');
    const mine = log.locator('.chat-message').last();
    check(await mine.locator('.chat-message-meta strong').innerText(), 'Ana Teste');
    check(await mine.evaluate(element => getComputedStyle(element).alignSelf), 'flex-end');
    check(await log.locator('.chat-message').first().evaluate(element => getComputedStyle(element).alignSelf), 'flex-start');
    check(await mine.evaluate(element => element.querySelector('.chat-message-actions').getBoundingClientRect().bottom <= element.querySelector('.chat-message-body').getBoundingClientRect().top), true);
    await mine.getByRole('button', { name: 'Editar mensagem', exact: true }).click();
    check(await page.getByRole('textbox', { name: 'Editar mensagem', exact: true }).evaluate(element => element === document.activeElement), true);
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => document.activeElement?.id.startsWith('chat-edit-') && document.activeElement.tagName === 'BUTTON');
    check(await mine.getByRole('button', { name: 'Editar mensagem', exact: true }).evaluate(element => element === document.activeElement), true);
    await mine.getByRole('button', { name: 'Editar mensagem', exact: true }).click();
    await page.getByRole('textbox', { name: 'Editar mensagem', exact: true }).fill('Texto corrigido com acentuação.');
    await page.getByRole('button', { name: 'Salvar edição', exact: true }).click();
    await mine.locator('.chat-message-body').filter({hasText:'Texto corrigido com acentuação.'}).waitFor();
    await mine.getByText('Editada', { exact: true }).waitFor();
    check(await mine.getByText('Editada', { exact: true }).count(), 1);
    await mine.getByText('Histórico de edições', { exact: true }).click();
    await mine.getByText('Olá! Mensagem enviada pelo chat.', { exact: true }).waitFor();
    const translationLanguages = page.getByRole('group', { name: 'Traduzir para', exact: true });
    check(await translationLanguages.getByRole('button').count(), 3);
    check(await translationLanguages.evaluate(node => Boolean(node.closest('.chat-conversation-heading'))), true);
    check(await page.locator('.chat-conversation-tools .language-switcher').count(), 0);
    const spanishTranslation = translationLanguages.getByRole('button', { name: 'Español', exact: true });
    await spanishTranslation.focus();
    await page.keyboard.press('Space');
    check(await spanishTranslation.getAttribute('aria-pressed'), 'true');
    check(await translationLanguages.getByRole('button', { name: 'Português', exact: true }).getAttribute('aria-pressed'), 'false');
    check(await page.locator('html').getAttribute('lang'), 'pt-BR');
    await mine.getByRole('button', { name: 'Traduzir mensagem', exact: true }).click();
    await mine.locator('[lang="es"]').waitFor();
    check(await mine.locator('[lang="es"]').innerText(), 'Automatic translation <script> is plain text');
    check(await mine.locator('script').count(), 0);
    check(await mine.locator('.chat-message-body').innerText(), 'Automatic translation <script> is plain text');
    check(messages[2].body, 'Texto corrigido com acentuação.');
    await mine.getByRole('button',{name:'Ver original',exact:true}).click();
    check(await mine.locator('.chat-message-body').innerText(), 'Texto corrigido com acentuação.');
    check(await mine.locator('.chat-message-body').getAttribute('lang'), null);
    await mine.getByRole('button',{name:'Ver tradução',exact:true}).click();
    check(await mine.locator('.chat-message-body').innerText(), 'Automatic translation <script> is plain text');
    await mine.getByRole('button',{name:'Editar mensagem',exact:true}).click();
    check(await page.getByRole('textbox',{name:'Editar mensagem',exact:true}).innerText(), 'Texto corrigido com acentuação.');
    await page.getByRole('button',{name:'Cancelar',exact:true}).click();
    await mine.getByText('Histórico de edições', {exact:true}).click();
    check(await mine.locator('.chat-translation').count(), 0);
    await page.getByText('Limites e edição',{exact:true}).click();
    check(await page.getByText('Você pode editar suas mensagens por até 24 horas após o envio. Mensagens não podem ser excluídas.',{exact:true}).isVisible(), true);
    await page.getByText('Limites e edição',{exact:true}).click();
    await page.screenshot({path:`/tmp/spot-chat-inline-translation-${width}.png`,fullPage:true});
    check(await page.locator('.chat-page').getByRole('button', { name: /excluir|deletar|apagar/i }).count(), 0);
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
    check(await page.locator('.chat-page button:not(:disabled)').evaluateAll(elements => elements.every(element => { const rect = element.getBoundingClientRect(); return rect.width >= 24 && rect.height >= ((element.closest('.chat-message-actions, .chat-translation-note, .chat-format-toolbar') || element.matches('.chat-format-toggle')) ? 32 : 44); })), true);
    const composer = page.getByLabel('Mensagem', { exact: true });
    check(await composer.evaluate(element => { const field = element.getBoundingClientRect(); const frame = element.closest('.chat-composer-input').getBoundingClientRect(); return field.width >= frame.width * (innerWidth <= 480 ? .85 : .4); }), true);
    await composer.fill('Primeira linha');
    await composer.press('Control+Shift+Enter');
    await composer.type('Segunda linha');
    check(await composer.innerText(), 'Primeira linha\nSegunda linha');
    const beforeSend = messages.length;
    await composer.dispatchEvent('compositionstart');
    await composer.dispatchEvent('keydown', {key:'Enter',isComposing:true,keyCode:229});
    await composer.dispatchEvent('compositionend');
    await page.waitForTimeout(550);
    check(await composer.innerText(), 'Primeira linha\nSegunda linha');
    check(messages.length, beforeSend);
    await composer.press('Enter');
    await log.locator('.chat-message').last().locator('.chat-message-body').filter({hasText:'Primeira linha'}).waitFor();
    const multilineBody = log.locator('.chat-message').last().locator('.chat-message-body');
    check(await multilineBody.innerText(), 'Primeira linha\nSegunda linha');
    check(messages.length, beforeSend + 1);
    check((await composer.innerText()).trim(), '');
    const upload = {name:'entrega.txt',mimeType:'text/plain',buffer:Buffer.from('Arquivo de teste')};
    await page.locator('#chat-file').setInputFiles(upload);
    await page.getByRole('button',{name:'Remover anexo selecionado entrega.txt',exact:true}).click();
    check(await page.locator('.chat-selected-files li').count(), 0);
    await page.locator('#chat-file').setInputFiles(upload);
    await page.getByRole('button',{name:'Enviar mensagem',exact:true}).click();
    const downloadLink = log.getByRole('link',{name:'Baixar entrega.txt',exact:true});
    await downloadLink.waitFor();
    const downloaded = page.waitForEvent('download');
    await downloadLink.click();
    const download = await downloaded;
    check(download.suggestedFilename(), 'entrega.txt');
    check(await log.locator('.chat-message').last().getByRole('button',{name:'Traduzir mensagem',exact:true}).isDisabled(), true);
    await page.locator('#chat-file').setInputFiles({name:'grande.txt',mimeType:'text/plain',buffer:Buffer.alloc(2*1024*1024+1)});
    await page.getByRole('alert').filter({hasText:'Cada arquivo deve ter no máximo 2 MB.'}).waitFor();
    check(await page.locator('.chat-selected-files li').count(), 0);
    await page.locator('#chat-file').setInputFiles(upload);
    await page.getByRole('button',{name:'Remover anexo selecionado entrega.txt',exact:true}).click();
    const chooserPromise = page.waitForEvent('filechooser');
    await page.getByRole('button',{name:'Enviar imagem',exact:true}).click();
    await (await chooserPromise).setFiles({name:'foto.png',mimeType:'image/png',buffer:png});
    await page.locator('.chat-selected-files img').waitFor();
    await page.getByRole('textbox',{name:'Descrição da imagem (opcional): foto.png',exact:true}).fill('Árvore na praça.');
    check(await page.locator('.chat-selected-files img').getAttribute('alt'),'Árvore na praça.');
    await page.getByRole('button',{name:'Enviar mensagem',exact:true}).click();
    const imageMessage=log.locator('.chat-message').last();
    await imageMessage.getByRole('img',{name:'Árvore na praça.',exact:true}).waitFor();
    await page.waitForFunction(()=>[...document.querySelectorAll('.chat-messages img')].some(image=>image.complete&&image.naturalWidth>0));
    check(await imageMessage.getByRole('link',{name:'Baixar foto.png',exact:true}).count(),1);
    check(await page.locator('.chat-selected-files li').count(),0);
    check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
    if (width === 1440) {
      for (const viewport of [{width:1440,height:1000},{width:1100,height:720},{width:1920,height:1200}]) {
        await page.setViewportSize(viewport);
        for (const zoom of [1,1.2]) {
          await page.evaluate(zoom=>document.documentElement.style.setProperty('--spot-reading-zoom',zoom),zoom);
          check(await page.locator('.chat-layout').evaluate(element=>{const box=element.getBoundingClientRect(),main=document.getElementById('main-content').getBoundingClientRect();return Math.abs(box.bottom-innerHeight)<=2&&Math.abs(box.left-main.left)<=2&&Math.abs(box.right-main.right)<=2;}),true);
          check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1&&document.documentElement.scrollHeight<=innerHeight+1),true);
          const sendImage=page.getByRole('button',{name:'Enviar imagem',exact:true});await sendImage.evaluate(element=>element.blur());await sendImage.focus();
          check(await sendImage.evaluate(element=>{const box=element.getBoundingClientRect();return box.top>=0&&box.bottom<=innerHeight+1;}),true);
        }
      }
      await page.setViewportSize({width:1440,height:1000});
      await page.evaluate(()=>document.documentElement.style.setProperty('--spot-reading-zoom',1));
    }
    await page.screenshot({ path: `/tmp/spot-chat-${width}.png`, fullPage: true });
    if (width === 1440) {
      await page.getByRole('button', {name:'Ativar modo claro',exact:true}).click();
      check(await page.getByRole('heading',{name:'Chat',exact:true}).evaluate(element => getComputedStyle(element).color), 'rgb(32, 55, 71)');
      await page.screenshot({path:'/tmp/spot-chat-light.png',fullPage:true});
    }
    await page.emulateMedia({forcedColors:'active',reducedMotion:'reduce'});
    check(await page.locator('.chat-page').evaluate(element => getComputedStyle(element).getPropertyValue('--chat-accent').trim()), 'LinkText');
    await page.emulateMedia({forcedColors:'none',reducedMotion:'no-preference'});
    await page.getByLabel('Buscar pessoa', { exact: true }).fill('outra');
    check(await page.locator('.chat-contacts li').count(), 1);
    await page.locator('.chat-contacts li button').click();
    check(await page.getByLabel('Mensagem', { exact: true }).count(), 0);
    await page.getByRole('button', { name: 'Solicitações de chat', exact: true }).click();
    const requestMenu = page.locator('.chat-new-request');
    check(await requestMenu.locator('details').count(), 0);
    await page.getByRole('heading', { name: 'Acompanhar solicitações', exact: true }).waitFor();
    const requestForm = page.getByRole('form', { name: 'Nova solicitação de chat', exact: true });
    await requestForm.waitFor();
    check(await requestForm.getByRole('button', { name: 'Enviar solicitação de acesso' }).isDisabled(), true);
    check(await requestForm.locator('#chat-request-person option').count(), 2);
    check(await requestForm.locator('#chat-request-person option[value="2"]').count(), 0);
    await requestForm.getByLabel('Pessoa para conversar', { exact: true }).selectOption('3');
    await requestForm.getByLabel('Enviar aprovação para', {exact:true}).selectOption('9');
    await requestForm.getByLabel('Motivo da solicitação', { exact: true }).fill('Preciso alinhar a entrega com esta equipe.');
    await page.screenshot({path:`/tmp/spot-chat-new-request-${width}.png`,fullPage:true});
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
    await requestForm.getByRole('button', { name: 'Enviar solicitação de acesso', exact: true }).click();
    await page.getByRole('status').filter({hasText:'Solicitação enviada ao aprovador selecionado.'}).waitFor();
    await requestMenu.getByText('Não há pessoas disponíveis para uma nova solicitação. Conversas liberadas ou com pedido pendente não aparecem nesta lista.', {exact:true}).waitFor();
    check(await requestForm.count(), 1);
    check(await requestForm.getByRole('button', {name:'Enviar solicitação de acesso',exact:true}).isDisabled(), true);
    await page.getByText('Preciso alinhar a entrega com esta equipe.', { exact: true }).waitFor();
    check(await page.getByRole('button', { name: 'Aprovar conversa', exact: true }).count(), 0);
    await page.getByLabel('Filtrar por situação', {exact:true}).selectOption('approved');
    await page.getByText('Nenhuma solicitação nesta situação.', {exact:true}).waitFor();
    check(await page.locator('.chat-request-history > article').count(), 0);
    await page.getByLabel('Filtrar por situação', {exact:true}).selectOption('pending');
    await page.getByText('Preciso alinhar a entrega com esta equipe.', {exact:true}).waitFor();
    await page.getByLabel('Filtrar por situação', {exact:true}).selectOption('all');

    // Simulate a refreshed session for the reviewer without sending real notifications.
    canReview = true; canStartWithoutApproval = true; requests[0].can_decide = true;
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await page.getByRole('button', { name: 'Aprovar conversa', exact: true }).waitFor();
    await page.getByRole('button', { name: 'Aprovar conversa', exact: true }).click();
    await page.locator('.chat-request-history > article').getByText('Aprovada', { exact: true }).waitFor();
    await page.getByRole('button', { name: 'Revogar autorização', exact: true }).click();
    await page.locator('.chat-request-history > article').getByText('Revogada', { exact: true }).waitFor();
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
    canReview = false; canStartWithoutApproval = false;
    reviewersAvailable = false;
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await requestMenu.getByText('Não há administrador ou gerente administrador disponível. Peça à administração para verificar os perfis.', {exact:true}).waitFor();
    check(await requestForm.count(), 1);
    check(await requestForm.getByRole('button', {name:'Enviar solicitação de acesso',exact:true}).isDisabled(), true);
    await requestForm.getByLabel('Pessoa para conversar', {exact:true}).selectOption('3');
    await requestForm.getByLabel('Motivo da solicitação', {exact:true}).fill('Novo alinhamento com outra equipe.');
    check(await requestForm.getByRole('button', {name:'Enviar solicitação de acesso',exact:true}).isDisabled(), true);
    await page.screenshot({path:`/tmp/spot-chat-access-panels-${width}.png`,fullPage:true});
    reviewersAvailable = true; canReview = true; canStartWithoutApproval = true;
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    // Interface changes language; personal content remains exactly as entered.
    for (const [locale, title] of [['en', 'Chat approvals'], ['es', 'Aprobaciones de chat']]) {
      await page.evaluate(locale => { localStorage.setItem('spot.locale', locale); window.dispatchEvent(new Event('spot:language')); }, locale);
      await page.getByRole('heading', { name: title, exact: true }).waitFor();
      await requestMenu.getByText(locale === 'en' ? 'Conversations without requests' : 'Conversaciones sin solicitud', {exact:true}).waitFor();
      check(await page.getByText('Preciso alinhar a entrega com esta equipe.', { exact: true }).innerText(), 'Preciso alinhar a entrega com esta equipe.');
    }
    await page.evaluate(() => { localStorage.setItem('spot.locale', 'pt'); window.dispatchEvent(new Event('spot:language')); });
    await page.getByRole('button', { name: 'Ir para conversas', exact: true }).click();
    await page.waitForFunction(()=>document.activeElement?.id==='chat-search');
    check(await page.getByLabel('Buscar pessoa', { exact: true }).evaluate(el=>el===document.activeElement),true);
    await page.getByLabel('Buscar pessoa', { exact: true }).fill('');
    await page.getByRole('button', { name: outsider.name + ' Conversa direta', exact: true }).click();
    await page.getByLabel('Mensagem', {exact:true}).waitFor();
    check(await page.getByLabel('Motivo da solicitação', {exact:true}).count(),0);
    await page.getByLabel('Mensagem', {exact:true}).fill('Contato direto da administração.');
    await page.getByRole('button', {name:'Enviar mensagem',exact:true}).click();
    await page.getByText('Contato direto da administração.', {exact:true}).waitFor();
    await page.screenshot({path:`/tmp/spot-chat-admin-direct-${width}.png`,fullPage:true});
    canReview = false; canStartWithoutApproval = false;
    await page.getByRole('button', { name: teammate.name + ' Seu time', exact: true }).click();
    translationAvailable = false;
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await page.getByText('A tradução de mensagens aguarda configuração pela administração.', { exact: true }).waitFor();
    check(await page.getByRole('button', { name: 'Traduzir mensagem', exact: true }).first().isDisabled(), true);
    teammate.can_chat = false; teammate.same_team = false;
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await page.getByLabel('Motivo da solicitação', { exact: true }).waitFor();
    check(await page.getByRole('log', { name: 'Mensagens da conversa' }).count(), 0);
    check(await page.getByLabel('Mensagem', { exact: true }).count(), 0);
    // The inbox button opens the intended chat or approval queue.
    let inboxData = { chat_user_id: 3 };
    await page.route('**/api/v1/inbox', route => route.fulfill({ json: { items: [{ id: 80, type: 'update', title: 'Aviso de chat', body: 'Abra o chat para ler e responder.', actor_name: 'Pessoa', created_at: now.toISOString(), read_at: now.toISOString(), data: inboxData }], unread_count: 0 } }));
    await page.locator('.home-label > button').last().click();
    await page.getByRole('menuitem', { name: 'Caixa de entrada', exact: true }).click();
    await page.getByRole('button', { name: 'Abrir chat e solicitações', exact: true }).click();
    await page.getByRole('heading', { name: outsider.name, exact: true }).waitFor();
    check(await page.getByLabel('Motivo da solicitação', { exact: true }).count(), 1);
    inboxData = { chat_request_id: 7 }; canReview = true; canStartWithoutApproval = true;
    await page.locator('.home-label > button').last().click();
    await page.getByRole('menuitem', { name: 'Caixa de entrada', exact: true }).click();
    await page.getByRole('button', { name: 'Abrir chat e solicitações', exact: true }).click();
    await page.getByRole('heading', { name: 'Aprovações de chat', exact: true }).waitFor();
    check(await page.locator('.chat-request-history > article').getByText('Revogada', { exact: true }).count(), 1);
    chatDenied = true;
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));
    await page.getByRole('alert').filter({hasText:'Seu perfil não tem acesso ao chat.'}).waitFor();
    check(await page.locator('.chat-contacts').count(), 0);
    check(await page.getByText('Carregando chat...', {exact:true}).count(), 0);
    await context.close();
  }
  const { page, context } = await createBrowserSession(browser, { dismissNotice: true, userOverrides: { can_chat: false }, onError: error => errors.push(error) });
  check(await page.locator('.dashboard-sidebar').getByRole('button', { name: 'Chat', exact: true }).count(), 0);
  await page.locator('.home-label > button').last().click();
  check(await page.getByRole('menuitem', { name: 'Chat', exact: true }).count(), 0);
  await page.evaluate(() => { history.pushState(null, '', '?page=chat'); window.dispatchEvent(new PopStateEvent('popstate')); });
  check(await page.getByRole('heading', { name: 'Chat', exact: true }).count(), 0);
  await context.close();
  assert.deepEqual(errors, []);
  console.log(JSON.stringify({ checks, errors }));
} finally { await browser.close(); }
