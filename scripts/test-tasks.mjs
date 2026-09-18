import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
const browser = await chromium.launch({ headless: true });
const errors = [];
try {
 const { page, context, user } = await createBrowserSession(browser, { admin: true, dismissNotice: true, onError: e => errors.push(e) });
 let tasks = [], reject = false, canCreate = true;
 const project = { id: 1, name: 'Projeto CRUD' };
 await page.route('**/sanctum/csrf-cookie', r => r.fulfill({ status: 204 }));
 await page.route('**/api/v1/tasks{,/*}', async route => {
  const req = route.request(), method = req.method();
  if (method === 'GET') return route.fulfill({ json: { tasks, projects: [project], can_create: canCreate, current_user: user, analysts: [], can_assign_others: false, activity_types: [{ id: 1, name: 'Reunião' }] } });
  if (method === 'DELETE') { tasks = []; return route.fulfill({ status: 204 }); }
  if (reject) return route.fulfill({ status: 422, json: { errors: { title: ['Título inválido.'] } } });
  const task = { ...req.postDataJSON(), user_id: user.id, activity_type_name: 'Reunião', id: 1, user, project, can_edit: true, can_delete: true };
  tasks = [task];
  return route.fulfill({ status: method === 'POST' ? 201 : 200, json: task });
 });
 async function navigate() {
  await page.locator('.home-label > button').last().click();
  await page.getByRole('menuitem', { name: 'Tarefas', exact: true }).click();
  await page.getByRole('button', { name: 'Nova tarefa', exact: true }).waitFor();
 }
 await navigate();
 await page.getByRole('button', { name: 'Nova tarefa', exact: true }).click();
 const dialog = page.getByRole('dialog');
 await dialog.getByLabel('Descrição', { exact: true }).fill('Tarefa persistida');
 await dialog.locator('details').evaluate(el => el.open = true);
 await dialog.getByLabel('Status', { exact: true }).selectOption('in_progress');
 await dialog.getByLabel('Prioridade', { exact: true }).selectOption('high');
 await dialog.getByLabel('Data da tarefa', { exact: true }).fill('2026-10-10');
 await dialog.getByLabel('Tipo de tarefa', { exact: true }).selectOption('1');
 await dialog.getByLabel('Minutos', { exact: true }).fill('30');
 await dialog.getByLabel('Hora extra?', { exact: true }).selectOption('true');
 reject = true;
 await dialog.getByRole('button', { name: 'Salvar tarefa' }).click();
 await dialog.getByRole('alert').waitFor();
 assert.equal(await dialog.getByLabel('Descrição', { exact: true }).inputValue(), 'Tarefa persistida');
 reject = false;
 await dialog.getByRole('button', { name: 'Salvar tarefa' }).click();
 await dialog.waitFor({ state: 'hidden' });
 assert.equal(tasks[0].priority, 'high');
 assert.equal(tasks[0].duration_minutes, 90);
 assert.equal(tasks[0].is_overtime, true);
 assert.equal(tasks[0].worked_on, '2026-10-10');
 assert.equal(tasks[0].project_id, 1);
 for (const name of ['Lista', 'Calendário', 'Quadro']) {
  await page.locator('.view-switch').getByRole('button', { name, exact: true }).click();
  await page.locator('[data-task-id="1"]').waitFor();
 }
 await page.reload();
 await page.locator('.tutorial-card').getByRole('button', { name: 'Entendi', exact: true }).click();
 await navigate();
 await page.locator('[data-task-id="1"]').waitFor();
 await page.getByRole('button', { name: 'Editar tarefa', exact: true }).click();
 await dialog.getByLabel('Descrição', { exact: true }).fill('Tarefa editada');
 await dialog.locator('details').evaluate(el => el.open = true);
 await dialog.getByLabel('Status', { exact: true }).selectOption('done');
 await dialog.getByRole('button', { name: 'Salvar tarefa' }).click();
 await dialog.waitFor({ state: 'hidden' });
 assert.equal(tasks[0].title, 'Tarefa editada');
 assert.equal(tasks[0].status, 'done');
 await page.getByRole('button', { name: 'Excluir tarefa', exact: true }).click();
 const deleteDialog = page.getByRole('alertdialog');
 await deleteDialog.getByRole('heading', { name: 'Excluir tarefa?' }).waitFor();
 await deleteDialog.getByRole('button', { name: 'Excluir tarefa', exact: true }).click();
 await page.locator('[data-task-id="1"]').waitFor({ state: 'hidden' });
 assert.equal(tasks.length, 0);
 canCreate = false;
 await page.reload();
 await page.locator('.tutorial-card').getByRole('button', { name: 'Entendi', exact: true }).click();
 await navigate();
 await page.getByRole('button', { name: 'Nova tarefa', exact: true }).click();
 await dialog.getByRole('heading', { name: 'Área restrita' }).waitFor();
 assert.equal(await dialog.locator('input, select').count(), 0);
 assert.ok(await dialog.getByText('Caso necessário acesso, falar com gerência', { exact: true }).isVisible());
 assert.deepEqual(errors, []);
 await context.close();
 console.log('Task browser CRUD passed: validation, create, three views, reload, edit and delete.');
} finally { await browser.close(); }
