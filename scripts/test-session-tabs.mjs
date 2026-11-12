// Real browser + PHP + PostgreSQL test. Uses a new schema, synthetic accounts,
// and array mail transport. No application data or external email is touched.
import assert from 'node:assert/strict';
import { execFileSync, spawn } from 'node:child_process';
import { once } from 'node:events';
import net from 'node:net';
import { randomBytes } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const root = fileURLToPath(new URL('../', import.meta.url));
const schema = `spot_tabs_${randomBytes(6).toString('hex')}`;
const listener = net.createServer();
listener.listen(0, '127.0.0.1');
await once(listener, 'listening');
const port = listener.address().port;
await new Promise(resolve => listener.close(resolve));
const base = `http://localhost:${port}`;
const password = 'Spot-browser-test-2026!';
const env = { ...process.env, APP_ENV: 'testing', APP_URL: base,
  APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
  JWT_SECRET: randomBytes(32).toString('hex'), DB_URL: '', DB_SEARCH_PATH: schema,
  SESSION_DRIVER: 'database', SESSION_SECURE_COOKIE: 'false', SESSION_DOMAIN: 'null',
  SANCTUM_STATEFUL_DOMAINS: `localhost:${port}`, CACHE_STORE: 'array',
  MAIL_MAILER: 'array', QUEUE_CONNECTION: 'database', SPOT_ACTIVITY_EMAIL_ENABLED: 'false',
  INITIAL_ADMIN_EMAIL: 'admin@computecnica.com.br', INITIAL_ADMIN_PASSWORD: password };
const php = args => execFileSync('./php', args, { cwd: root, env, stdio: 'pipe', timeout: 60000 });
const tinker = code => php(['artisan', 'tinker', `--execute=${code}`]);
let server, browser;
try {
  tinker(`Illuminate\\Support\\Facades\\DB::statement('CREATE SCHEMA ${schema}');`.replaceAll('\\\\', '\\'));
  php(['artisan', 'migrate', '--force']);
  php(['artisan', 'db:seed', '--force']);
  tinker(`$admin=App\\Models\\User::where('email','admin@computecnica.com.br')->firstOrFail();
    $user=App\\Models\\User::factory()->create(['email'=>'tabs@example.test','password'=>null,'active'=>false,'account_status'=>'pending_activation','current_company_id'=>$admin->current_company_id,'organization_id'=>$admin->organization_id]);
    $user->profiles()->attach(App\\Models\\Profile::where('slug','analista')->value('id'), ['company_id'=>$admin->current_company_id]);
    App\\Models\\UserActivationToken::create(['user_id'=>$user->id,'created_by'=>$admin->id,'token_hash'=>hash('sha256','tabs-test-token'),'expires_at'=>now()->addHour()]);`.replaceAll('\\\\', '\\'));
  server = spawn('.runtime/bin/frankenphp', ['php-server', '--root', 'public', '--listen', `127.0.0.1:${port}`], { cwd: root, env, stdio: 'ignore' });
  for (let attempt = 0; attempt < 80; attempt++) {
    if (await fetch(`${base}/up`).then(response => response.ok).catch(() => false)) break;
    if (attempt === 79) throw new Error('Isolated PHP server did not start.');
    await new Promise(resolve => setTimeout(resolve, 100));
  }
  browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const adminTab = await context.newPage();
  const errors = [];
  adminTab.on('pageerror', error => errors.push(error.message));
  await adminTab.goto(base);
  await adminTab.locator('input[name=email]').fill('admin@computecnica.com.br');
  await adminTab.locator('input[name=password]').fill(password);
  await adminTab.getByRole('button', { name: 'Entrar', exact: true }).click();
  await adminTab.locator('.dashboard-main').waitFor();
  const identity = page => page.evaluate(async () => {
    const response = await fetch('/api/v1/auth/me', { headers: { Accept: 'application/json' } });
    return { status: response.status, user: await response.json() };
  });
  const administrator = (await identity(adminTab)).user;
  assert.ok(administrator.can_manage_identity);

  // Same account in two tabs is valid and must not invalidate either tab.
  const activationTab = await context.newPage();
  activationTab.on('pageerror', error => errors.push(error.message));
  await activationTab.goto(base);
  await activationTab.locator('.dashboard-main').waitFor();
  assert.equal((await identity(activationTab)).user.id, administrator.id);
  assert.equal(await adminTab.getByRole('heading', { name: 'Sessão alterada', exact: true }).count(), 0);

  await activationTab.goto(`${base}/first-access/tabs-test-token`);
  for (const [field, value] of Object.entries({ name: 'Pessoa Teste', email: 'tabs@example.test', password, password_confirmation: password })) {
    await activationTab.locator(`input[name=${field}]`).fill(value);
  }
  await activationTab.locator('button[type=submit]').click();
  await activationTab.waitForURL(`${base}/`);
  await activationTab.locator('.dashboard-main').waitFor();
  const activated = await identity(activationTab);
  assert.equal(activated.status, 200);
  assert.equal(activated.user.email, 'tabs@example.test');
  assert.equal(activated.user.can_manage_identity, false);
  await adminTab.getByRole('heading', { name: 'Sessão alterada', exact: true }).waitFor();
  assert.equal(await adminTab.locator('.dashboard-main').count(), 0);
  assert.equal(await activationTab.locator('.spot-error-overlay').count(), 0);

  for (const width of [320, 390, 1440]) {
    await adminTab.setViewportSize({ width, height: 900 });
    assert.ok(await adminTab.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
  }
  await adminTab.getByRole('link', { name: 'Reabrir o Spot', exact: true }).focus();
  assert.equal(await adminTab.evaluate(() => document.activeElement.textContent), 'Reabrir o Spot');
  const untrustedRequest = await context.request.post(`${base}/api/v1/auth/logout`, {
    headers: { Accept: 'application/json', Origin: 'https://untrusted.example' },
  });
  assert.equal(untrustedRequest.status(), 419);
  assert.equal((await identity(activationTab)).user.id, activated.user.id);

  // A stale request from the old tab must not sign the newly activated user out.
  const staleStatus = await adminTab.evaluate(async id => {
    await fetch('/sanctum/csrf-cookie');
    const token = decodeURIComponent(document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN=')).split('=').slice(1).join('='));
    return (await fetch('/api/v1/auth/logout', { method: 'POST', headers: {
      Accept: 'application/json', 'X-XSRF-TOKEN': token, 'X-Spot-User': String(id),
    } })).status;
  }, administrator.id);
  assert.equal(staleStatus, 409);
  assert.equal((await identity(activationTab)).user.id, activated.user.id);
  await activationTab.reload();
  await activationTab.locator('.dashboard-main').waitFor();
  assert.equal((await identity(activationTab)).status, 200);
  await activationTab.getByRole('button', { name: 'Abrir menu do perfil' }).click();
  await activationTab.getByRole('button', { name: 'Sair da conta', exact: true }).click();
  await activationTab.getByRole('button', { name: 'Entrar', exact: true }).waitFor();
  await adminTab.getByRole('link', { name: 'Reabrir o Spot', exact: true }).click();
  await adminTab.getByRole('button', { name: 'Entrar', exact: true }).waitFor();
  assert.deepEqual(errors, []);
  console.log('OK: sessão real em PostgreSQL, duas abas, ativação, isolamento de identidade, recarga e logout. Schema descartável; nenhum e-mail real.');
} finally {
  await browser?.close();
  if (server && server.exitCode === null) {
    const stopped = once(server, 'exit');
    server.kill('SIGTERM');
    await stopped;
  }
  tinker(`Illuminate\\Support\\Facades\\DB::statement('DROP SCHEMA IF EXISTS ${schema} CASCADE');`.replaceAll('\\\\', '\\'));
}
