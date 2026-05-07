import { chromium } from '@playwright/test';

const BASE_URL = 'http://localhost:3000';
const OUTDIR = '/home/cmoreau/projects/mon-projet/AtelierMBZ/var/screenshots';

const pages = [
  { path: '/login', name: 'login-fixed', fullPage: true },
  { path: '/mecanicien', name: 'mecano-fixed', fullPage: true },
  { path: '/public/booking', name: 'booking-fixed', fullPage: true },
  { path: '/vo', name: 'vo-fixed', fullPage: true },
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();

  // Login via dev simulate
  await page.goto(`${BASE_URL}/api/auth/google/dev-simulate?email=admin@atelier.local&mode=login`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);

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
