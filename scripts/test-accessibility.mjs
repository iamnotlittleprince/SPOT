import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
const browser = await chromium.launch({headless:true});
try {
  const errors = [];
  const {page, context} = await createBrowserSession(browser, {admin:true, dismissNotice:true, onError:e=>errors.push(e)});
  await page.locator('.skip-link').focus();
  await page.keyboard.press('Enter');
  assert.equal(await page.evaluate(()=>document.activeElement.id), 'main-content');
  assert.equal(await page.locator('#main-content .dashboard-sidebar').count(), 0);
  await page.locator('.dashboard-sidebar').getByRole('button',{name:'Agenda',exact:true}).click();
  const trigger = page.getByRole('button',{name:'Novo evento',exact:true});
  await trigger.click();
  const dialog = page.getByRole('dialog',{name:'Novo evento'});
  await dialog.waitFor();
  assert.equal(await dialog.evaluate(el=>el.contains(document.activeElement)),true);
  const first = dialog.getByRole('button',{name:'Fechar',exact:true});
  await first.focus();
  await page.keyboard.press('Shift+Tab');
  assert.equal(await dialog.getByRole('button',{name:'Criar evento'}).evaluate(el=>el===document.activeElement),true);
  await page.keyboard.press('Tab');
  assert.equal(await first.evaluate(el=>el===document.activeElement),true);
  await page.keyboard.press('Escape');
  assert.equal(await dialog.count(),0);
  assert.equal(await trigger.evaluate(el=>el===document.activeElement),true);
  await page.emulateMedia({reducedMotion:'reduce'});
  assert.equal(await trigger.evaluate(el=>getComputedStyle(el).transitionDuration),'1e-05s');
  const reading = page.getByRole('button', {name:'Ajustes rápidos',exact:true});
  await reading.click();
  const panel = page.getByRole('dialog',{name:'Ajustes rápidos do trabalho',exact:true});
  for (const width of [1440, 390, 320]) {
    await page.setViewportSize({width,height:900});
    for (const font of ['arial','verdana','georgia','default']) {
      await panel.getByLabel('Fonte do site').selectOption(font);
      for (let i=0;i<3;i++) {
        const increase=panel.getByRole('button',{name:'Aumentar texto',exact:true});
        if(await increase.isEnabled()) await increase.click();
      }
      await page.waitForFunction(() => {
        const trigger = document.querySelector('.quick-settings-wrap > button').getBoundingClientRect();
        const panel = document.querySelector('.quick-settings-popover').getBoundingClientRect();
        return Math.abs(panel.top - trigger.bottom - 10) < 2;
      });
      const bounds = await panel.boundingBox();
      const triggerBounds = await reading.boundingBox();
      assert.ok(Math.abs(bounds.y - triggerBounds.y - triggerBounds.height - 10) < 2);
      if(width === 1440) assert.ok(Math.abs(bounds.x - triggerBounds.x) < 2);
      assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width + 1);
      assert.equal(await page.evaluate(()=>document.documentElement.dataset.readingFont),font);
    }
  }
  assert.equal(await panel.getByRole('button',{name:'Aumentar texto',exact:true}).isDisabled(),true);
  for(let i=0;i<3;i++) await panel.getByRole('button',{name:'Diminuir texto',exact:true}).click();
  assert.equal(await panel.getByRole('button',{name:'Diminuir texto',exact:true}).isDisabled(),true);
  await panel.getByLabel('Fonte do site').selectOption('verdana');
  assert.ok(await page.locator('#main-content').evaluate(el=>getComputedStyle(el).fontFamily.includes('Verdana')));
  for(let i=0;i<3;i++) await panel.getByRole('button',{name:'Aumentar texto',exact:true}).click();
  await page.keyboard.press('Escape');
  assert.equal(await reading.evaluate(el=>el===document.activeElement),true);
  await page.reload();
  assert.equal(await page.evaluate(()=>JSON.parse(localStorage.getItem('spot.reading-settings')).scale),120);
  await reading.click();
  await panel.getByRole('button',{name:'Restaurar padrão'}).click();
  assert.equal(await page.evaluate(()=>document.documentElement.style.getPropertyValue('--spot-reading-zoom')),'1');
  assert.equal(await page.evaluate(()=>document.documentElement.dataset.readingFont),'default');
  assert.deepEqual(errors,[]);
  await context.close();
  console.log('OK: pular navegação, foco inicial, Tab/Shift+Tab, Escape, restauração de foco, movimento reduzido e ajustes de leitura (fontes, limites, persistência, restauração e painel responsivo).');
} finally { await browser.close(); }
