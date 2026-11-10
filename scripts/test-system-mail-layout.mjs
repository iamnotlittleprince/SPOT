import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const directory = await fs.mkdtemp(path.join(os.tmpdir(), 'spot-system-mail-'));
const browser = await chromium.launch();
let checks = 0;
try {
  execFileSync('./php', ['scripts/render-system-mail-previews.php', directory]);
  for (const width of [320, 700]) {
    const page = await browser.newPage({ viewport: { width, height: 1000 } });
    for (const file of await fs.readdir(directory)) {
      await page.goto('file://' + path.join(directory, file));
      assert.equal(await page.locator('h1').count(), 1, file);
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${file}: overflow at ${width}px`);
      assert.ok(await page.locator('a').first().evaluate(node => node.getBoundingClientRect().height >= 44), `${file}: action height`);
      checks++;
    }
    await page.close();
  }
  console.log(`${checks} mail previews passed: pt/en/es, 320/700px, long content and action height. No email sent.`);
} finally {
  await browser.close();
  await fs.rm(directory, { recursive: true, force: true });
}
