import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
import { sessionRequest } from '../resources/js/api.js';

// Logout must not tell mounted dashboards (or other tabs) to reload private data.
const originalFetch = globalThis.fetch;
const events = [];
globalThis.document = { cookie: 'XSRF-TOKEN=test' };
globalThis.window = {
  dispatchEvent: event => events.push(event.type),
  setTimeout,
  localStorage: { setItem: () => events.push('storage') },
};
try {
  globalThis.fetch = async () => new Response('{}', { status: 200 });
  await sessionRequest('/auth/logout', { method: 'POST' });
  await new Promise(resolve => setTimeout(resolve, 20));
  assert.deepEqual(events, []);
  await sessionRequest('/tasks', { method: 'POST' });
  await new Promise(resolve => setTimeout(resolve, 20));
  assert.deepEqual(events, ['spot:data-changed', 'storage']);
} finally {
  globalThis.fetch = originalFetch;
  delete globalThis.document;
  delete globalThis.window;
}

const browser = await chromium.launch({ headless: true });
try {
  const { page, context } = await createBrowserSession(browser, { dismissNotice: true });
  // The initial dashboard is simulated; the destination uses the real guest page.
  await page.route('**/sanctum/csrf-cookie', route => route.fulfill({ status: 204 }));
  await page.route('**/api/v1/auth/logout', route => route.fulfill({ json: { message: 'Logout realizado com sucesso.' } }));
  await page.goto(new URL('/?home', page.url()).href);
  await page.route(url => url.pathname === '/' && url.search === '', route => route.continue());
  await page.getByRole('button', { name: 'Abrir menu do perfil', exact: true }).click();
  await page.getByRole('button', { name: 'Sair da conta', exact: true }).click();
  await page.waitForURL(url => url.pathname === '/' && url.search === '');
  await page.getByRole('heading', { name: 'Seja bem-vindo ao Spot' }).waitFor();
  assert.equal(await page.getByRole('alertdialog').count(), 0);
  await page.reload();
  await page.getByRole('heading', { name: 'Seja bem-vindo ao Spot' }).waitFor();
  await context.close();
  console.log('OK: logout opens login, clears ?home, survives reload and emits no data refresh.');
} finally {
  await browser.close();
}
