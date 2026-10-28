import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
import { projectStatus, projectStatusLabel } from '../resources/js/projectStatus.js';
const browser = await chromium.launch({ headless: true });
const errors = [];
try {
  const { page, context } = await createBrowserSession(browser, { admin: true, dismissNotice: true, onError: error => errors.push(error) });
  const slugs = ['in_progress', 'planning', 'stopped', 'review', 'completed', 'cancelled', 'frozen', 'in-progress', 'planning', 'custom', 'planning'];
  const projects = slugs.map((status, index) => ({ id: index + 1, name: `Projeto ${index + 1}`, client_name: 'Cliente', status, project_status_id: index === 9 ? '10' : index === 10 ? 11 : null, finalized_at: index === 8 ? '2026-10-26' : null }));
  await page.route('**/api/v1/projects', route => route.fulfill({ json: projects }));
  await page.route('**/api/v1/parameters', route => route.fulfill({ json: { project_statuses: [{ id: 10, name: 'Etapa especial', slug: 'special' }, { id: 11, name: 'in_progress', slug: 'in-progress' }], project_situations: [], clients: [], users: [] } }));
  await page.locator('.home-label > button').last().click();
  await page.getByRole('menuitem', { name: 'Projetos', exact: true }).click();
  await page.locator('.projects-table-wrap tbody tr').first().waitFor();
  const expected = {
    pt: ['Em andamento', 'Planejamento', 'Parado', 'Em revisão', 'Concluído', 'Cancelado', 'Congelado', 'Em andamento', 'Finalizado', 'Etapa especial', 'Em andamento'],
    en: ['In progress', 'Planning', 'On hold', 'In review', 'Completed', 'Canceled', 'Frozen', 'In progress', 'Finalized', 'Etapa especial', 'In progress'],
    es: ['En curso', 'Planificación', 'En pausa', 'En revisión', 'Completado', 'Cancelado', 'Congelado', 'En curso', 'Finalizado', 'Etapa especial', 'En curso'],
  };
  for (const [locale, label] of [['pt', 'Português'], ['en', 'English'], ['es', 'Español'], ['pt', 'Português'], ['en', 'English']]) {
    await page.getByRole('button', { name: label, exact: true }).click();
    await page.waitForFunction(values => JSON.stringify([...document.querySelectorAll('.projects-table-wrap tbody tr')].map(row => row.cells[2].textContent)) === JSON.stringify(values), expected[locale]);
  }
  assert.equal(projectStatusLabel('paused'), 'Congelado');
  assert.equal(projectStatusLabel('done'), 'Concluído');
  assert.equal(projectStatus({ status: 'planning', finalized_at: '2026-10-26' }), 'Finalizado');
  assert.equal(projectStatusLabel('Status personalizado'), 'Status personalizado');
  assert.deepEqual(errors, []);
  await context.close();
  console.log('Project statuses passed: Portuguese, English, Spanish, live language switching, legacy codes, configured status, custom labels and finalized projects.');
} finally { await browser.close(); }
