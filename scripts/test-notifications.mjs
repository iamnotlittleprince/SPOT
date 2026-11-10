import assert from 'node:assert/strict';
import { chromium } from 'playwright';
import { createBrowserSession } from './browser-fixtures.mjs';
const browser = await chromium.launch();
const errors = [];
try {
  for (const width of [320, 1440]) {
    const {page,context} = await createBrowserSession(browser, { dismissNotice:true, viewport:{width,height:900}, storage:{'spot.reading-settings':JSON.stringify({scale:120,font:'verdana'})},onError:e=>errors.push(e)});
    let items = [{id:91,title:'Nova tarefa recebida',created_at:new Date().toISOString()}];
    let count = 2;
    await page.route('**/api/v1/inbox',r=>r.fulfill({json:{items,unread_count:count,new_items:items}}));
    await page.goto('http://localhost:8000/');
    const notice = page.locator('.notification-arrival');
    await notice.waitFor();
    assert.equal(await page.locator('.notification-wrap .notification-dot').count(),1);
    assert.equal(await page.locator('.dashboard-sidebar .notification-dot').count(),1);
    if (width === 1440) {
      await page.mouse.move(1400, 850);
      await page.locator('.topbar .profile-button').focus();
      await page.waitForTimeout(300);
      for (const icon of await page.locator('.dashboard-sidebar .nav-group .notification-icon').all()) {
        assert.equal(await icon.evaluate(el => getComputedStyle(el).opacity), '1', 'Collapsed sidebar icons remain visible');
        const box = await icon.boundingBox();
        assert.ok(box.width > 0 && box.height > 0);
      }
      assert.equal(await page.locator('.dashboard-sidebar .notification-dot').first().evaluate(el => getComputedStyle(el.parentElement).opacity), '1');
      await page.locator('.dashboard-sidebar .nav-group button').first().focus();
      await page.waitForTimeout(350);
      assert.equal(await page.locator('.dashboard-sidebar .notification-icon').first().evaluate(el => getComputedStyle(el).opacity), '1');
      await page.locator('.topbar .profile-button').focus();
    }
    const bounds=await notice.boundingBox();
    assert.ok(bounds.x>=0 && bounds.x+bounds.width<=width+1);
    assert.ok(bounds.y+bounds.height<=900);
    await page.keyboard.press('Escape');
    await notice.waitFor({state:'detached'});
    await page.evaluate(()=>window.dispatchEvent(new Event('spot:data-changed')));
    await page.waitForTimeout(150);
    assert.equal(await notice.count(),0);
    items=[{id:92,title:'Outra notificação'}];
    await page.evaluate(()=>window.dispatchEvent(new Event('spot:data-changed')));
    await notice.waitFor();
    await notice.getByRole('button',{name:'Fechar aviso de notificação'}).click();
    items=[]; count=0;
    await page.evaluate(()=>window.dispatchEvent(new Event('spot:data-changed')));
    await page.locator('.notification-wrap .notification-dot').waitFor({state:'detached'});
    await context.close();
  }
  assert.deepEqual(errors,[]);
  console.log('Notificações: indicadores, chegada, Escape, repetição, leitura e limites em 320/1440px a 120% passaram.');
} finally {await browser.close();}
