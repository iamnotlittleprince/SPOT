import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
import { getLessons, chapters, localize } from '../resources/js/tutorial/content.js';

const browser = await chromium.launch({ headless: true });
const errors = [];
let checks = 0;
const check = (value, message) => { assert.ok(value, message); checks++; };
const keyFor = email => `spot.tutorial.v1:${encodeURIComponent(email)}`;
const setup = options => createBrowserSession(browser, { onError: error => errors.push(error), ...options });
const dialog = page => page.locator('.tutorial-card');
async function dismiss(page, name = 'Agora não') {
  await dialog(page).getByRole('button', { name, exact: true }).click();
  await page.locator('.tutorial-card').waitFor({ state: 'detached' });
}
async function saved(page, email) { return page.evaluate(key => JSON.parse(localStorage.getItem(key)), keyFor(email)); }
async function help(page, locale = 'pt') {
  await page.locator('[data-tour="help"]').click();
  await dialog(page).waitFor();
}
async function ensureStep(page, step) {
  await page.locator(`[data-tutorial-step="${step.id}"]`).waitFor();
  await page.waitForFunction(selector => {
    const target = document.querySelector(selector);
    return target && target.getBoundingClientRect().width > 0 && document.querySelector('.tutorial-highlight');
  }, step.target, { timeout: 6000 });
  const bounds = await dialog(page).boundingBox();
  const size = page.viewportSize();
  check(bounds.x >= 0 && bounds.y >= 0 && bounds.x + bounds.width <= size.width + 1 && bounds.y + bounds.height <= size.height + 1, `Card fits viewport at ${step.id}`);
  check(await page.evaluate(() => document.querySelector('.dashboard-shell').inert), 'Page controls remain inert');
}

try {
  // Authored copy, stable IDs and permission-aware lesson selection.
  const full = getLessons({ can_create_projects: true, can_manage_identity: true, can_view_financial: true });
  check(new Set(full.map(s => s.id)).size === full.length, 'Unique step IDs');
  for (const step of getLessons().concat(full)) for (const locale of ['pt', 'en', 'es']) {
    check(Boolean(localize(step.title, locale) && localize(step.body, locale) && localize(step.tip, locale) && chapters[step.chapter][locale]), 'Complete translated lesson');
  }
  for (const admin of [false, true]) {
    const { page, context, user, requests } = await setup({ admin });
    check((await dialog(page).innerText()).includes('Bem-vindo ao Spot'), 'First visit offers tutorial');
    check((await dialog(page).innerText()).includes('Opcional, mas recomendado'), 'Tutorial is optional and recommended');
    await dismiss(page);
    check((await saved(page, user.email)).status === 'skipped', 'Dismissal is saved');
    check(!await page.evaluate(() => document.querySelector('.dashboard-shell').inert), 'Dismissal unlocks page');
    await page.reload();
    await dialog(page).waitFor();
    check((await dialog(page).innerText()).includes('O tutorial está no botão ?'), 'Every opening explains help symbol');
    await dismiss(page, 'Entendi');
    await help(page);
    await dialog(page).getByRole('button', { name: 'Iniciar tutorial', exact: true }).click();
    const steps = getLessons(user);
    for (let index = 0; index < steps.length; index++) {
      const step = steps[index];
      await ensureStep(page, step);
      check((await page.locator('#spot-tutorial-title').textContent()) === step.title.pt, 'Correct lesson heading');
      if (index === 2) {
        await page.keyboard.press('Escape');
        check((await saved(page, user.email)).stepId === step.id, 'Escape pauses and saves current lesson');
        await help(page);
        await dialog(page).getByRole('button', { name: 'Continuar tutorial', exact: true }).click();
        await ensureStep(page, step);
        await dialog(page).getByRole('button', { name: 'Voltar', exact: true }).click();
        await ensureStep(page, steps[index - 1]);
        await dialog(page).getByRole('button', { name: 'Próximo', exact: true }).click();
        await ensureStep(page, step);
      }
      await dialog(page).getByRole('button', { name: index === steps.length - 1 ? 'Concluir tutorial' : 'Próximo', exact: true }).click();
    }
    check((await page.locator('#spot-tutorial-title').innerText()) === 'Você concluiu o tutorial!', 'Completion is explicit');
    check((await saved(page, user.email)).status === 'completed', 'Completion persists');
    await dismiss(page, 'Explorar o Spot');
    check(await page.locator('[data-tour="help"]').evaluate(element => element === document.activeElement), 'Focus returns to help');
    await page.reload();
    await dialog(page).waitFor();
    check((await dialog(page).innerText()).includes('O tutorial está no botão ?'), 'Reminder remains after completion');
    await dismiss(page, 'Entendi');
    check(requests.every(request => request.method === 'GET'), 'Tour never writes to APIs');
    if (!admin) check(!requests.some(request => /admin\/users/.test(request.url)), 'Analyst tour does not visit administration');
    await context.close();
  }
  // Mobile, localization, chapter jumps, keyboard focus and saved-step recovery.
  const { page, context, user, requests } = await setup({ admin: true, viewport: { width: 390, height: 844 }, storage: { 'spot.locale': 'es', 'spot.theme': 'dark' } });
  check((await page.locator('#spot-tutorial-title').innerText()) === 'Bienvenido a Spot', 'Popup follows saved Spanish language');
  await dialog(page).locator('select').selectOption('en');
  await page.waitForFunction(() => document.querySelector('#spot-tutorial-title').textContent === 'Welcome to Spot');
  await dialog(page).getByRole('button', { name: 'Start tutorial', exact: true }).click();
  const steps = getLessons(user);
  await ensureStep(page, steps[0]);
  for (let i = 0; i < 12; i++) {
    await page.keyboard.press(i % 2 ? 'Shift+Tab' : 'Tab');
    check(await page.evaluate(() => document.querySelector('.tutorial-card').contains(document.activeElement)), 'Keyboard focus stays in modal');
  }
  await dialog(page).locator('summary').click();
  const permissions = steps.find(step => step.id === 'admin-permissions');
  await dialog(page).getByRole('button', { name: /Review roles, status and permissions/ }).click();
  await ensureStep(page, permissions);
  await dialog(page).locator('select').selectOption('es');
  await page.waitForFunction(() => document.querySelector('#spot-tutorial-title').textContent === 'Revise perfiles, estados y permisos');
  await page.keyboard.press('Escape');
  await page.reload();
  await dialog(page).getByRole('button', { name: 'Continuar tutorial', exact: true }).click();
  await ensureStep(page, permissions);
  check(requests.every(request => request.method === 'GET'), 'Mobile tour also remains read-only');
  await page.screenshot({ path: '/tmp/spot-tutorial-mobile.png' });
  await page.keyboard.press('Escape');
  check(await page.locator('[data-tour="help"]').isVisible(), 'Help button stays visible on mobile');
  const helpBounds = await page.locator('[data-tour="help"]').boundingBox();
  check(helpBounds.x >= 0 && helpBounds.x + helpBounds.width <= 390, 'Mobile help button fits viewport');
  await help(page);
  await dialog(page).getByRole('button', { name: 'Empezar desde el principio', exact: true }).click();
  await ensureStep(page, steps[0]);
  await context.close();

  // A different account must never inherit another account’s progress.
  const other = await setup({ email: 'other@example.test', storage: { [keyFor('ana@example.test')]: JSON.stringify({ status: 'completed', stepId: 'help' }) } });
  check((await other.page.locator('#spot-tutorial-title').innerText()) === 'Bem-vindo ao Spot', 'Progress is isolated per account');
  await other.context.close();
  const corrupt = await setup({ storage: { [keyFor('ana@example.test')]: '{broken-json' } });
  check((await corrupt.page.locator('#spot-tutorial-title').innerText()) === 'Bem-vindo ao Spot', 'Corrupt saved progress falls back safely');
  await corrupt.page.screenshot({ path: '/tmp/spot-tutorial-welcome.png' });
  await corrupt.context.close();
  const unavailable = await setup({});
  await unavailable.page.evaluate(() => {
    const original = Storage.prototype.setItem;
    Storage.prototype.setItem = function(key, value) {
      if (key.startsWith('spot.tutorial.')) throw new DOMException('Storage blocked', 'QuotaExceededError');
      return original.call(this, key, value);
    };
  });
  await dialog(unavailable.page).getByRole('button', { name: 'Iniciar tutorial', exact: true }).click();
  await ensureStep(unavailable.page, getLessons()[0]);
  check((await dialog(unavailable.page).innerText()).includes('O navegador não permitiu salvar o progresso'), 'Storage failure does not prevent the tutorial');
  await unavailable.context.close();

  const missing = await setup({});
  await missing.page.route('**/api/v1/security', route => route.fulfill({ status:503, json:{message:'Serviço temporariamente indisponível.'} }));
  await dialog(missing.page).locator('summary').click();
  await dialog(missing.page).getByRole('button', { name: /Proteja sua senha/ }).click();
  await dialog(missing.page).getByText('O destaque ainda não está disponível nesta tela. Você pode continuar lendo e avançar normalmente.', {exact:true}).waitFor();
  await dialog(missing.page).getByRole('button', { name:'Próximo',exact:true }).click();
  check((await missing.page.locator('#spot-tutorial-title').textContent()) === 'Ative uma proteção adicional', 'Unavailable target does not trap navigation');
  await missing.context.close();

  const revoked = await setup({storage:{[keyFor('ana@example.test')]:JSON.stringify({status:'in_progress',stepId:'admin-permissions'})}});
  await dialog(revoked.page).getByRole('button',{name:'Iniciar tutorial',exact:true}).click();
  await ensureStep(revoked.page,getLessons()[0]);
  check(!revoked.requests.some(request=>/admin\/users/.test(request.url)), 'Resume respects revoked permissions');
  await revoked.context.close();
  check(errors.length === 0, JSON.stringify(errors));
  console.log(JSON.stringify({ checks, errors, lessons: { analyst: getLessons().length, administrator: full.length } }, null, 2));
} finally { await browser.close(); }
