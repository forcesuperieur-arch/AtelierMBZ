import { chromium } from '@playwright/test';

const BASE_URL = 'http://localhost';
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
  await page.waitForTimeout(3000);

  const pages = [
    { path: '/mecanicien', name: 'mecano-final', fullPage: true },
    { path: '/vo', name: 'vo-final', fullPage: true },
    { path: '/login', name: 'login-final', fullPage: true },
    { path: '/rdv', name: 'rdv-final', fullPage: true },
    { path: '/public/booking', name: 'booking-final', fullPage: true },
  ];

  for (const p of pages) {
    try {
      await page.goto(`${BASE_URL}${p.path}`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(2000);
      await page.screenshot({ path: `${OUTDIR}/${p.name}.png`, fullPage: p.fullPage });
      console.log(`✅ ${p.name}`);
    } catch (e) {
      console.log(`❌ ${p.name}: ${e.message}`);
    }
  }

  await browser.close();
  console.log('Done.');
})();
