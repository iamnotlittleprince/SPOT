import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';

const browser = await chromium.launch({headless: true});
const errors = [];
try {
  const { page, context } = await createBrowserSession(browser, { admin: true, dismissNotice: true, onError: e => errors.push(e) });
  let calls = 0;
  await page.route('**/api/v1/reports/project-analytics*', route => {
    calls++;
    return route.fulfill({json: {
      generated_at: new Date().toISOString(), processor: 'Python + Pandas',
      projects: [{id:1,name:'Projeto atualizado',client:'Cliente',manager:'Ana',progress:20,contract_value:1000,actual_revenue:1000,actual_cost:100,profit:900,worked_hours:1,estimated_hours:10}],
      summary:{total:calls,in_progress:1,completed:0,on_time:1,overdue:0,frozen:0,cancelled:0,clients:1,managers:1,contract_value:1000,revenue:1000,cost:100,profit:900},
      status:[],monthly:[],clients:[],managers:[],account_managers:[]
    }});
  });
  await page.clock.install();
  await page.locator('.dashboard-sidebar').getByRole('button',{name:'Portfólios',exact:true}).click();
  await page.locator('.analytics-kpis').waitFor();
  const initial = calls;
  await page.locator('.analytics-filters input').first().fill('2026-01-01');
  await page.clock.fastForward(30010);
  await page.waitForFunction(n => document.querySelector('.analytics-kpis')?.textContent.includes(String(n)), initial + 1);
  assert.equal(calls, initial + 1);
  assert.equal(await page.locator('.analytics-filters input').first().inputValue(), '2026-01-01');
  // O filtro ainda não aplicado não pode ser adotado silenciosamente pelo timer.
  const urls = [];
  page.on('request', r => { if (r.url().includes('/reports/project-analytics')) urls.push(r.url()); });
  const nextRequest = page.waitForRequest(r => r.url().includes('/reports/project-analytics'));
  await page.evaluate(() => window.dispatchEvent(new Event('spot:data-changed')));
  await nextRequest;
  assert.equal(new URL(urls.at(-1)).search, '');
  const beforeStorage = calls;
  await page.evaluate(() => window.dispatchEvent(new StorageEvent('storage', {key:'spot.data.changed',newValue:'test'})));
  await page.waitForFunction(n => document.querySelector('.analytics-kpis')?.textContent.includes(String(n)), beforeStorage + 1);
  assert.equal(calls, beforeStorage + 1);
  // Ao sair da tela, seu timer deve ser desmontado.
  await page.locator('.dashboard-sidebar').getByRole('button',{name:'Home',exact:true}).click();
  await page.locator('.dashboard-main .project-row').first().waitFor();
  const afterNavigation = calls;
  await page.clock.fastForward(60010);
  assert.equal(calls, afterNavigation);
  assert.deepEqual(errors, []);
  await context.close();
  console.log('Live updates passed: timed refresh, cross-tab and mutation events, unapplied filters preserved and timer cleanup.');
} finally { await browser.close(); }
