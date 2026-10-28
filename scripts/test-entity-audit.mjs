import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
const browser = await chromium.launch({ headless: true });
const errors = [], queries = [];
try {
  const { page, context, user } = await createBrowserSession(browser, { admin: true, dismissNotice: true, onError: error => errors.push(error) });
  const project = { id: 1, name: 'Projeto com histórico', status: 'planning', client_name: 'Cliente' };
  const task = { id: 5, project_id: 1, project, user, title: 'Tarefa auditada', status: 'todo', priority: 'medium', can_edit: true, participants: [] };
  const event = { id: 'event-1', action: 'task.priority_changed', created_at: '2026-10-26T12:00:00Z', user: { name: 'Maria Auditora' }, context: { subject_name: 'Tarefa auditada' }, old_values: { priority: 'medium' }, new_values: { priority: 'high' } };
  await page.route('**/api/v1/**', async route => {
    const url = new URL(route.request().url()), path = url.pathname.replace('/api/v1', '');
    if (path === '/projects') return route.fulfill({ json: [project] });
    if (path === '/projects/1/configuration') return route.fulfill({ json: { members: [], rates: [], taxes: [] } });
    if (path === '/projects/1/expenses') return route.fulfill({ json: [] });
    if (path === '/projects/1/financial-result') return route.fulfill({ json: { actual: {}, estimated: {}, by_analyst: [], by_activity_type: [], expenses: [] } });
    if (path === '/tasks') return route.fulfill({ json: { tasks: [task], projects: [project], can_create: true, current_user: user } });
    if (path === '/history') {
      queries.push(Object.fromEntries(url.searchParams));
      return route.fulfill({ json: { events: [{ ...event, id: url.searchParams.has('before') ? 'event-2' : 'event-1' }], next: url.searchParams.has('before') ? null : 'cursor-1' } });
    }
    return route.fallback();
  });
  async function navigate(name) {
    await page.locator('.home-label > button').last().click();
    await page.getByRole('menuitem', { name, exact: true }).click();
  }
  await navigate('Projetos');
  await page.getByRole('button', { name: 'Detalhes', exact: true }).click();
  const projectTrail = page.locator('.management-project-detail .entity-audit-trail');
  await projectTrail.getByRole('button', { name: 'Histórico de alterações', exact: true }).click();
  await projectTrail.getByText('Maria Auditora', { exact: true }).waitFor();
  assert.deepEqual(queries.at(-1), { project_id: '1' });
  await projectTrail.getByText('Detalhes da alteração', { exact: true }).click();
  assert.match(await projectTrail.locator('tbody').innerText(), /Prioridade.*Média.*Alta/s);
  await projectTrail.getByRole('button', { name: 'Carregar mais', exact: true }).click();
  await projectTrail.locator('li').nth(1).waitFor();
  assert.deepEqual(queries.at(-1), { project_id: '1', before: 'cursor-1' });
  await navigate('Tarefas');
  await page.locator('.task-open-button').filter({ hasText: task.title }).first().click();
  const dialog = page.getByRole('dialog', { name: 'Detalhes da tarefa', exact: true });
  await dialog.getByRole('button', { name: 'Histórico de alterações', exact: true }).click();
  await dialog.getByText('Maria Auditora', { exact: true }).waitFor();
  assert.deepEqual(queries.at(-1), { project_id: '1', task_id: '5' });
  await dialog.getByRole('button', { name: 'Atualizar histórico', exact: true }).click();
  assert.equal(await dialog.locator('.entity-audit-trail li').count(), 1);
  assert.deepEqual(errors, []);
  await context.close();
  console.log('Entity audit browser passed: project and task history, actor, readable before/after, pagination and refresh.');
} finally { await browser.close(); }
