import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';

const browser = await chromium.launch({ headless: true });
try {
  const { page, context } = await createBrowserSession(browser, { dismissNotice: true });
  await page.getByRole('button', { name: 'apps', exact: true }).click();
  await page.getByRole('button', { name: 'Gerenciar integrações', exact: true }).click();
  const dialog = page.getByRole('dialog', { name: 'Gerenciar integrações', exact: true });
  await dialog.waitFor();
  assert.ok(await dialog.evaluate(el => el.contains(document.activeElement)));
  await page.keyboard.press('Escape');
  assert.equal(await dialog.count(), 0);
  await page.getByRole('button', { name: 'apps', exact: true }).click();
  await page.getByRole('button', { name: 'Gerenciar integrações', exact: true }).click();
  await page.route('**/integrations/microsoft/connect', route => route.fulfill({ contentType: 'text/html', body: '<p>OAuth connection endpoint</p>' }));
  await dialog.locator('.integration-provider').filter({ has: page.getByText('Microsoft 365', { exact: true }) })
    .getByRole('button', { name: 'Conectar conta', exact: true }).click();
  await page.waitForURL('**/integrations/microsoft/connect');
  await context.close();
  console.log('OK: Microsoft integration uses the authenticated connection route; modal focus and Escape preserved.');
} finally {
  await browser.close();
}
