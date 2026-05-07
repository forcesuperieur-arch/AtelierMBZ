import { chromium } from '@playwright/test';

const BASE_URL = 'http://localhost:3000';
const OUTDIR = '/home/cmoreau/projects/mon-projet/AtelierMBZ/var/screenshots';

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  // Login via form
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[type="email"]', 'admin@atelier.local');
  await page.fill('input[type="password"]', 'Admin123!');
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/$/, { timeout: 15000 });
  await page.waitForTimeout(1000);

  const pages = [
    { path: '/mecanicien', name: 'mecano-fixed2', fullPage: true },
    { path: '/vo', name: 'vo-fixed2', fullPage: true },
    { path: '/login', name: 'login-fixed2', fullPage: true },
    { path: '/rdv', name: 'rdv-fixed2', fullPage: true },
  ];

  for (const p of pages) {
    try {
      await page.goto(`${BASE_URL}${p.path}`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(2000);
      // Hard refresh to avoid cache
      await page.reload({ waitUntil: 'networkidle' });
      await page.waitForTimeout(1000);
      await page.screenshot({ path: `${OUTDIR}/${p.name}.png`, fullPage: p.fullPage });
      console.log(`✅ ${p.name}`);
    } catch (e) {
      console.log(`❌ ${p.name}: ${e.message}`);
    }
  }

  await browser.close();
  console.log('Done.');
})();
