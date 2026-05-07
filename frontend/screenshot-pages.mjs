import { chromium } from 'playwright-core';
import fs from 'fs';

const BASE_URL = 'http://localhost:3002';
const OUT_DIR = '/home/cmoreau/projects/mon-projet/AtelierMBZ/var/screenshots';

if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

const pages = [
  { path: '/login', name: 'login' },
  { path: '/cover', name: 'cover' },
  { path: '/2fa', name: '2fa' },
  { path: '/public/booking', name: 'booking' },
  { path: '/', name: 'dashboard' },
  { path: '/workshop', name: 'workshop' },
  { path: '/planning', name: 'planning' },
  { path: '/clients', name: 'clients' },
  { path: '/devis', name: 'devis' },
  { path: '/vo', name: 'vo' },
  { path: '/profile', name: 'profile' },
  { path: '/suivi', name: 'suivi' },
  { path: '/params', name: 'params' },
  { path: '/tarifs', name: 'tarifs' },
  { path: '/mecanicien', name: 'mecanicien' },
  { path: '/motos', name: 'motos' },
];

const browser = await chromium.launch({
  executablePath: '/home/cmoreau/.cache/ms-playwright/chromium-1217/chrome-linux64/chrome',
  headless: true
});

const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();

for (const p of pages) {
  try {
    await page.goto(`${BASE_URL}${p.path}`, { waitUntil: 'networkidle', timeout: 15000 });
    await page.waitForTimeout(1500);
    await page.screenshot({ path: `${OUT_DIR}/${p.name}.png`, fullPage: true });
    console.log(`✅ ${p.name}`);
  } catch (e) {
    console.log(`❌ ${p.name}: ${e.message}`);
  }
}

await browser.close();
