import { chromium } from 'playwright-core';

const browser = await chromium.launch({
  executablePath: '/home/cmoreau/.cache/ms-playwright/chromium-1217/chrome-linux64/chrome',
  headless: true,
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu']
});

const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();

page.on('console', msg => console.log('CONSOLE:', msg.type(), msg.text()));
page.on('pageerror', err => console.log('PAGEERROR:', err.message));

await page.goto('http://localhost:3002/login', { waitUntil: 'networkidle', timeout: 30000 });
await page.waitForTimeout(3000);

const html = await page.content();
console.log('HTML length:', html.length);
console.log('Body text:', await page.evaluate(() => document.body.innerText.slice(0, 500)));

await page.screenshot({ path: '/home/cmoreau/projects/mon-projet/AtelierMBZ/var/screenshots/login-debug.png', fullPage: true });
await browser.close();
