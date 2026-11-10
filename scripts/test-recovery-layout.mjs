import assert from 'node:assert/strict';
import { chromium } from 'playwright';
const browser = await chromium.launch();
try {
  for (const [width, height] of [[1920, 968], [1440, 900], [768, 1024], [320, 740], [844, 390]]) {
    const page = await browser.newPage({ viewport: { width, height } });
    await page.route('**/forgot-password', route => route.request().method() === 'POST'
      ? route.fulfill({ json: { message: 'Se existir uma conta com esse e-mail, enviaremos um link de recuperação.' } })
      : route.continue());
    await page.goto('http://localhost:8000/forgot-password');
    await page.locator('input[name=email]').fill('long.email.address.for.layout@example.invalid');
    await page.locator('form button[type=submit]').click();
    await page.getByRole('status').waitFor();
    for (const zoom of [1, 1.2]) {
      await page.evaluate(value => document.documentElement.style.setProperty('--spot-reading-zoom', value), String(zoom));
      const samples = [];
      for (const [label, locale] of [['Português', 'pt-BR'], ['English', 'en'], ['Español', 'es']]) {
        await page.getByRole('button', { name: label, exact: true }).click();
        await page.waitForFunction(value => document.documentElement.lang === value, locale);
        await page.evaluate(() => document.fonts.ready);
        await page.waitForTimeout(150);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${width}/${locale}: page overflow`);
        const boxes = await page.evaluate(() => ['.forgot-password-card', '.auth-accessibility-bar', '.recovery-heading', 'form', '.back-to-login', '.recovery-illustration'].map(selector => {
          const node = document.querySelector(selector), rect = node.getBoundingClientRect();
          return { y: rect.y, height: rect.height, width: rect.width };
        }));
        samples.push(boxes);
      }
      for (const sample of samples.slice(1)) sample.forEach((box, index) => {
        for (const dimension of ['y', 'height', 'width']) assert.ok(Math.abs(box[dimension] - samples[0][index][dimension]) < 2, `${width}/${zoom}: ${index} ${dimension} changed across languages: ${JSON.stringify(samples)}`);
      });
    }
    await page.close();
  }
  console.log('Recovery layout: pt/en/es aligned at five viewport sizes, including landscape and 120% reading.');
} finally { await browser.close(); }
