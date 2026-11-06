import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
const browser = await chromium.launch();
let checks = 0;
const errors = [];
const check = (value, message) => { assert.ok(value, message); checks++; };
const openPortfolio = async page => { await page.locator('.home-label > button').last().click(); await page.getByRole('menuitem', { name: 'Portfólios', exact: true }).click(); };
const entry = { id: 1, status: 'pending', project: { id: 1, name: 'Projeto de teste com nome longo para leitura e apresentação' }, requester: { id: 1, name: 'Analista Teste' }, reviewer: { id: 2, name: 'Gestor Teste' }, message: 'Preciso apresentar os indicadores.', can_decide: false };
try {
 for (const width of [320, 1440]) {
  const { page, context } = await createBrowserSession(browser, { dismissNotice: true, viewport: { width, height: 900 }, storage: { 'spot.reading-settings': JSON.stringify({ scale: width === 320 ? 120 : 100, font: 'verdana' }) }, onError: e => errors.push(e) });
  let records = [];
  await page.route('**/api/v1/reports/project-analytics*', r => r.fulfill({ status: 403, json: { message: 'Área restrita', can_request_access: true } }));
  await page.route('**/api/v1/project-access', async route => {
   if (route.request().method() === 'POST') {
    const data = route.request().postDataJSON();
    check(data.project_id === 1 && data.reviewer_id === 2 && data.message === entry.message, 'Request payload matches selected project and manager');
    records = [entry]; return route.fulfill({ status: 201, json: { request: entry } });
   }
   return route.fulfill({ json: { can_request: true, projects: [{ id: 1, name: entry.project.name, reviewers: [{ id: 2, name: 'Gestor Teste' }, ...(width === 1440 ? [{ id: 3, name: 'Outro Gestor' }] : [])] }, { id: 4, name: 'Projeto sem responsável disponível', reviewers: [] }], requests: records } });
  });
  await openPortfolio(page);
  const launcher = page.getByRole('button', { name: 'Solicitações de acesso', exact: true });
  await launcher.click();
  const dialog = page.getByRole('dialog', { name: 'Solicitações de acesso', exact: true });
  await dialog.getByRole('heading', { name: 'Pedir acesso a um projeto' }).waitFor();
  check(await dialog.evaluate(el => el.contains(document.activeElement)), 'Initial focus inside dialog');
  for (let i = 0; i < 12; i++) { await page.keyboard.press(i % 2 ? 'Shift+Tab' : 'Tab'); check(await dialog.evaluate(el => el.contains(document.activeElement)), 'Focus contained'); }
  await dialog.getByLabel('Projeto', { exact: true }).selectOption('4');
  check(await dialog.getByRole('button', { name: 'Enviar solicitação', exact: true }).isDisabled(), 'Project without an eligible recipient cannot be sent');
  await dialog.getByRole('status').filter({ hasText: 'Este projeto ainda não tem um gerente disponível' }).waitFor();
  await dialog.getByLabel('Projeto', { exact: true }).selectOption('1');
  check(await dialog.getByText('O gerente concede acesso apenas', { exact: false }).count() === 0, 'Business rules are absent from request form');
  if (width === 1440) await dialog.getByLabel('Destinatário', { exact: true }).selectOption('2');
  else { check(await dialog.getByLabel('Destinatário', { exact: true }).count() === 0, 'Single manager is selected automatically'); await dialog.getByText('Enviar para', { exact: true }).waitFor(); }
  await dialog.getByLabel('Mensagem', { exact: true }).fill(entry.message);
  await dialog.getByRole('button', { name: 'Enviar solicitação', exact: true }).click();
  await dialog.getByText('Solicitação enviada. Você receberá a resposta na caixa de entrada.', { exact: true }).waitFor();
  check(await dialog.locator('.project-access-entry').count() === 0, 'Request modal does not display history');
  const bounds = await dialog.boundingBox();
  check(bounds.x >= 0 && bounds.x + bounds.width <= width, 'Dialog fits viewport');
  check(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth + 1), 'No horizontal overflow');
  if (width === 320) { await dialog.evaluate(el => { el.scrollTop = 0; }); await dialog.screenshot({ path: '/tmp/spot-access-mobile.png' }); }
  await page.keyboard.press('Escape');
  check(await launcher.evaluate(el => el === document.activeElement), 'Escape restores focus');
  // Reviewer decisions and Teams selection.
  records = [{ ...entry, can_decide: true }];
  await page.route('**/api/v1/project-access/meetings', r => r.fulfill({ json: { meetings: [{ id: 'teams-event', title: 'Reunião Teams', start: '2026-11-06T12:00:00Z', end: '2026-11-06T13:00:00Z' }] } }));
  await page.route('**/api/v1/project-access/1/decision', async r => {
   const data = r.request().postDataJSON();
   check(data.access_mode === 'visual' && data.duration === 'meeting' && data.teams_event_id === 'teams-event', 'Reviewer selects visual permission and Teams event');
   records = [{ ...records[0], status: 'approved', access_mode: 'visual', duration: 'meeting' }];
   return r.fulfill({ json: { request: records[0] } });
  });
  await page.route('**/api/v1/project-access/1/revoke', r => { records = [{ ...records[0], status: 'revoked' }]; return r.fulfill({ json: { request: records[0] } }); });
  await page.route('**/api/v1/inbox', r => r.fulfill({ json: { items: [{ id: 1, title: 'Acesso solicitado', body: entry.message, type: 'update', data: { access_request_id: 1 }, created_at: new Date().toISOString(), read_at: new Date().toISOString() }], unread_count: 0 } }));
  await page.locator('.home-label > button').last().click();
  await page.getByRole('menuitem', { name: 'Caixa de entrada', exact: true }).click();
  await page.locator('.inbox-message-detail .project-access-launch').click();
  check(await dialog.getByRole('button', { name: 'Enviar solicitação', exact: true }).count() === 0, 'Inbox review does not mix analyst request form');
  await dialog.getByLabel('Tipo de acesso', { exact: true }).selectOption('visual');
  await dialog.getByLabel('Validade', { exact: true }).selectOption('meeting');
  await dialog.getByLabel('Reunião Teams', { exact: true }).selectOption('teams-event');
  await dialog.getByRole('button', { name: 'Conceder acesso', exact: true }).click();
  await dialog.getByText('Aprovada', { exact: true }).waitFor();
  await dialog.getByRole('button', { name: 'Revogar acesso', exact: true }).click();
  await dialog.getByText('Revogada', { exact: true }).waitFor();
  await page.keyboard.press('Escape');
  // Translation of the modal (portal stays inside the translated root).
  for (const [locale, title] of [['en', 'Access requests'], ['es', 'Solicitudes de acceso']]) {
   await page.evaluate(locale => { localStorage.setItem('spot.locale', locale); window.dispatchEvent(new Event('spot:language')); }, locale);
   await page.locator('.project-access-launch').click();
   await page.getByRole('dialog', { name: title, exact: true }).waitFor();
   await page.keyboard.press('Escape');
   checks++;
  }
  await context.close();
 }
 for (const canRequest of [false, true]) {
  const { page, context } = await createBrowserSession(browser, { dismissNotice: true, admin: !canRequest, onError: e => errors.push(e) });
  await page.route('**/api/v1/reports/project-analytics*', r => r.fulfill({ status: 403, json: { message: 'Área restrita', can_request_access: canRequest } }));
  await openPortfolio(page);
  await page.locator('#restricted-portfolio-title').waitFor();
  check(await page.locator('.portfolio-access-integrated .project-access-launch').count() === Number(canRequest), 'Request action follows server eligibility, not administration/manager privileges');
  await context.close();
 }
 for (const mode of ['view', 'visual', 'data']) {
  const { page, context } = await createBrowserSession(browser, { dismissNotice: true, onError: e => errors.push(e) });
  const project = { id: 1, name: 'Projeto autorizado', client: 'Cliente', manager: 'Gestor', status: 'in_progress', progress: 10, contract_value: 100, actual_revenue: 100, actual_cost: 20, profit: 80, worked_hours: 1, estimated_hours: 10, unpriced_tasks: 0 };
  const report = { projects: [project], summary: { total: 1, in_progress: 1, completed: 0, on_time: 1, overdue: 0, frozen: 0, contract_value: 100, revenue: 100, cost: 20, profit: 80 }, status: [], monthly: [], access: { full_access: false, visual_project_ids: mode === 'view' ? [] : [1], data_project_ids: mode === 'data' ? [1] : [] } };
  await page.route('**/api/v1/reports/project-analytics*', r => r.fulfill({ json: report }));
  await page.route('**/api/v1/projects/1/financial-result', r => r.fulfill({ json: { actual: {}, estimated: {}, by_analyst: [], by_activity_type: [], expenses: [] } }));
  await openPortfolio(page);
  await page.locator('.analytics-project-filter select').selectOption('1');
  const personalize = page.getByRole('button', { name: 'Personalizar painel', exact: true });
  if (mode === 'view') {
   check(await personalize.count() === 0, 'View grant cannot customize charts');
   check(await page.locator('.report-data-editor').count() === 0, 'View grant cannot edit data');
  } else {
   await personalize.click();
   check(await page.getByRole('group', { name: 'Indicadores do painel' }).isVisible(), 'Visual and data grants can select indicators');
   if (mode === 'visual') check(await page.locator('.report-data-editor').count() === 0, 'Visual grant cannot edit underlying data');
   if (mode === 'data') {
    await page.route('**/api/v1/project-access/projects/1/data', r => {
     const values = r.request().postDataJSON();
     check(values.progress === '70', 'Data edit sends changed progress');
     project.progress = 70;
     return r.fulfill({ json: { message: 'Dados do projeto atualizados.' } });
    });
    const editor = page.locator('.report-data-editor');
    await editor.locator('summary').click();
    await editor.getByLabel('Progresso (%)', { exact: true }).fill('70');
    await editor.getByRole('button', { name: 'Salvar dados', exact: true }).click();
    await editor.getByRole('status').getByText('Dados do projeto atualizados.', { exact: true }).waitFor();
    await page.waitForFunction(() => document.querySelector('.detail-kpis')?.textContent.includes('70%'));
    checks++;
   }
  }
  await context.close();
 }
 check(errors.length === 0, JSON.stringify(errors));
 console.log(JSON.stringify({ checks, errors }));
} finally { await browser.close(); }
