import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';

const base = process.env.SPOT_BASE_URL || 'http://localhost:8000';
const entry = JSON.parse(await fs.readFile('public/build/manifest.json', 'utf8'))['resources/js/app.jsx'];
const publicHtml = `<!doctype html><html lang="pt-BR"><head>${(entry.css || []).map(file => `<link rel="stylesheet" href="/build/${file}">`).join('')}</head><body><div id="root"></div><script>window.__SPOT__={authenticated:false,user:null}</script><script type="module" src="/build/${entry.file}"></script></body></html>`;
const browser = await chromium.launch({ headless: true });
try {
  const context = await browser.newContext();
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.route(url => url.origin === new URL(base).origin && ['/', '/first-access/test-token'].includes(url.pathname), route => route.fulfill({ contentType: 'text/html', body: publicHtml }));
  await page.route('**/sanctum/csrf-cookie', route => route.fulfill({ status: 204 }));
  const requests = [];
  await page.route('**/api/v1/**', route => {
    const path = new URL(route.request().url()).pathname;
    requests.push(path);
    if (path === '/api/v1/auth/login') return route.fulfill({ json: { user: { id: 1, name: 'Teste' } } });
    if (path === '/api/v1/first-access/test-token') return route.fulfill({ json: { user: { id: 1 } } });
    return route.fulfill({ status: 401, json: { message: 'Sua identificação não está disponível. Entre novamente para acessar esta área.' } });
  });

  await page.goto(`${base}/?home`);
  await page.getByRole('button', { name: 'Entrar', exact: true }).waitFor();
  assert.equal(await page.locator('.dashboard-main').count(), 0);
  assert.deepEqual(requests, []);
  await page.locator('input[name="email"]').fill('teste@example.test');
  await page.locator('input[name="password"]').fill('senha-de-teste');
  await page.getByRole('button', { name: 'Entrar', exact: true }).click();
  await page.getByRole('alert').filter({ hasText: 'Sua identificação' }).waitFor();
  assert.ok(requests.includes('/api/v1/auth/me'));
  assert.equal(await page.locator('.dashboard-main, .spot-error-overlay').count(), 0);

  await page.goto(`${base}/first-access/test-token`);
  await page.locator('input[name="name"]').fill('Teste');
  await page.locator('input[name="email"]').fill('teste@example.test');
  await page.locator('input[name="password"]').fill('senha-de-teste');
  await page.locator('input[name="password_confirmation"]').fill('senha-de-teste');
  await page.locator('button[type="submit"]').click();
  await page.waitForURL(`${base}/`);
  await page.getByRole('button', { name: 'Entrar', exact: true }).waitFor();
  assert.equal(await page.locator('.dashboard-main').count(), 0);
  assert.deepEqual(errors, []);
  await context.close();

  const session = await createBrowserSession(browser, { admin: true, dismissNotice: true });
  await session.page.route('**/sanctum/csrf-cookie', route => route.fulfill({ status: 204 }));
  await session.page.route('**/api/v1/auth/logout', route => route.fulfill({ status: 401, json: { message: 'Sessão expirada' } }));
  await session.page.route(url => url.origin === new URL(base).origin && url.pathname === '/', route => route.fulfill({ contentType: 'text/html', body: publicHtml }));
  await session.page.getByRole('button', { name: 'Abrir menu do perfil' }).click();
  await session.page.getByRole('button', { name: 'Sair da conta', exact: true }).click();
  await session.page.getByRole('button', { name: 'Entrar', exact: true }).waitFor();
  assert.equal(await session.page.locator('.spot-error-overlay').count(), 0);
  await session.context.close();
  console.log('OK: URL não autentica, login confirma sessão, ativação consulta identidade do servidor e logout aceita sessão expirada. APIs simuladas; nenhum cadastro ou e-mail real.');
} finally {
  await browser.close();
}
