import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from '@playwright/test';

const BASE_URL = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000';
const USERNAME = process.env.E2E_USERNAME || 'admin';
const PASSWORD = process.env.E2E_PASSWORD || 'Admin123!';
const SUIVI_TOKEN = process.env.SUIVI_TOKEN || 'j3kTfbIXGvynjs8jV-XY6-YNhAcuglhhvHPwXeN7YYA';
const OUTPUT_DIR = path.resolve('docs/assets/guide');

async function ensureDir() {
  await fs.mkdir(OUTPUT_DIR, { recursive: true });
}

async function cleanOutputDir() {
  const entries = await fs.readdir(OUTPUT_DIR, { withFileTypes: true }).catch(() => []);
  await Promise.all(
    entries
      .filter((entry) => entry.isFile() && entry.name.endsWith('.png'))
      .map((entry) => fs.unlink(path.join(OUTPUT_DIR, entry.name))),
  );
}

async function waitForSettled(page, timeout = 1000) {
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(timeout);
}

async function dismissBlockingOverlays(page) {
  await page.evaluate(() => {
    const selectors = ['.travaux-alert-overlay', '.modal-overlay', '#app-modal'];
    selectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((node) => node.remove());
    });
  });
}

async function screenshotElement(page, selector, filename) {
  const target = page.locator(selector);
  await target.waitFor({ state: 'visible', timeout: 15000 });
  await page.evaluate(() => window.scrollTo(0, 0));
  await target.screenshot({
    path: path.join(OUTPUT_DIR, filename),
    animations: 'disabled',
  });
}

async function getJson(page, url) {
  return page.evaluate(async (requestUrl) => {
    const response = await fetch(requestUrl, { credentials: 'include' });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status} for ${requestUrl}`);
    }
    return response.json();
  }, url);
}

async function clickAndCapture(page, config) {
  await dismissBlockingOverlays(page);
  if (config.click) {
    await page.locator(config.click).evaluate((node) => node.click());
  }
  if (config.ready) {
    await page.locator(config.ready).waitFor({ state: 'visible', timeout: 15000 });
  }
  await waitForSettled(page, config.pause || 1200);
  await screenshotElement(page, config.shotSelector || '#app-container', config.file);
}

async function login(page) {
  await page.goto(`${BASE_URL}/`, { waitUntil: 'domcontentloaded' });
  await page.locator('#login-screen').waitFor({ state: 'visible', timeout: 15000 });
  await screenshotElement(page, '#login-screen', '01-connexion.png');

  await page.fill('#login-user', USERNAME);
  await page.fill('#login-pass', PASSWORD);
  await page.getByRole('button', { name: 'Connexion' }).click();
  await page.locator('#s-dashboard').waitFor({ state: 'visible', timeout: 15000 });
  await waitForSettled(page, 1800);
}

async function capturePublicPages(browser) {
  const page = await browser.newPage({ viewport: { width: 1440, height: 1180 } });

  await page.goto(`${BASE_URL}/tarifs.html`, { waitUntil: 'domcontentloaded' });
  await page.locator('.container').waitFor({ state: 'visible', timeout: 15000 });
  await waitForSettled(page, 1000);
  await screenshotElement(page, 'body', '13-rdv-public.png');

  await page.goto(`${BASE_URL}/suivi.html?token=${encodeURIComponent(SUIVI_TOKEN)}`, { waitUntil: 'domcontentloaded' });
  await page.locator('#content').waitFor({ state: 'visible', timeout: 15000 });
  await waitForSettled(page, 1000);
  await screenshotElement(page, 'body', '14-suivi-public.png');

  await page.close();
}

async function enrichClientsCapture(page) {
  const clientsPayload = await getJson(page, '/api/clients?page=1&limit=50');
  const clients = Array.isArray(clientsPayload) ? clientsPayload : (clientsPayload.items || []);
  const preferredClient = clients.find((client) => client.nom === 'Moreau' || client.prenom === 'Colin')
    || clients.sort((left, right) => (right.nb_rdv || 0) - (left.nb_rdv || 0))[0];
  if (!preferredClient) return;

  await page.evaluate((clientId) => {
    window.showClientDetail(clientId);
  }, preferredClient.id);
  await page.locator('#client-detail-panel').waitFor({ state: 'visible', timeout: 15000 });
  await waitForSettled(page, 1200);
  await screenshotElement(page, '#s-clients', '08-clients-vehicules.png');
}

async function enrichMotoCapture(page) {
  await page.fill('#moto-tech-marque', 'KAWASAKI');
  await page.fill('#moto-tech-modele', 'AR 125');
  await page.fill('#moto-tech-annee', '1990');
  await page.evaluate(() => {
    window.lookupMotoTechnicalSpec();
  });
  await page.locator('#moto-tech-result-badge').waitFor({ state: 'visible', timeout: 15000 });
  await page.waitForFunction(() => {
    const badge = document.getElementById('moto-tech-result-badge');
    return badge && /Fiche trouvee/i.test(badge.textContent || '');
  }, { timeout: 15000 });
  await waitForSettled(page, 1000);
  await screenshotElement(page, '#s-motos', '09-fiches-moto.png');
}

async function enrichDevisCapture(page) {
  await page.evaluate(() => {
    window.ouvrirCreerDevis();
  });
  await page.locator('#app-modal').waitFor({ state: 'visible', timeout: 15000 });
  await waitForSettled(page, 600);
  await screenshotElement(page, 'body', '10-devis.png');
}

async function enrichMecanicienCapture(page) {
  const candidateDates = ['2026-04-13', '2026-04-14', '2026-04-09', '2026-04-08'];
  let rdv = null;

  for (const date of candidateDates) {
    try {
      const rdvs = await getJson(page, `/api/rendez-vous?date=${date}`);
      if (Array.isArray(rdvs) && rdvs.length) {
        rdv = rdvs.find((item) => item.id) || rdvs[0];
        if (rdv) break;
      }
    } catch {
      // Try the next date.
    }
  }

  if (!rdv || !rdv.id) {
    await screenshotElement(page, '#s-espace-meca', '11-espace-mecanicien.png');
    return;
  }

  let rapport = null;
  try {
    rapport = await getJson(page, `/api/rendez-vous/${rdv.id}/rapport-technicien`);
  } catch {
    rapport = null;
  }

  await page.evaluate(({ rdvId, rdvData, rapportData }) => {
    window.MecanicienModule.renderCheckupModal(rdvId, rapportData, rdvData);
  }, { rdvId: rdv.id, rdvData: rdv, rapportData: rapport });
  await page.locator('#app-modal').waitFor({ state: 'visible', timeout: 15000 });
  await waitForSettled(page, 600);
  await screenshotElement(page, 'body', '11-espace-mecanicien.png');
}

async function main() {
  await ensureDir();
  await cleanOutputDir();

  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1600, height: 1180 } });

  try {
    await login(page);

    const captures = [
      { file: '02-dashboard.png', click: '#nav-dashboard', ready: '#s-dashboard.active' },
      { file: '03-prise-rdv.png', click: '#nav-rdv', ready: '#s-rdv.active' },
      { file: '04-planning.png', click: '#nav-planning', ready: '#s-planning.active', pause: 1800 },
      { file: '05-ponts-mecaniciens.png', click: '#nav-ponts', ready: '#s-ponts.active', pause: 1500 },
      { file: '06-dossiers-atelier.png', click: '#nav-or', ready: '#s-or.active', pause: 1500 },
      { file: '07-suivi-live.png', click: '#nav-suivi', ready: '#s-suivi.active', pause: 1500 },
      { file: '12-administration.png', click: '#nav-admin', ready: '#s-admin.active', pause: 1500 },
    ];

    for (const capture of captures) {
      await clickAndCapture(page, capture);
    }

    await clickAndCapture(page, { file: '08-clients-vehicules.png', click: '#nav-clients', ready: '#s-clients.active', pause: 1200 });
    await enrichClientsCapture(page);

    await clickAndCapture(page, { file: '09-fiches-moto.png', click: '#nav-motos', ready: '#s-motos.active', pause: 1000 });
    await enrichMotoCapture(page);

    await clickAndCapture(page, { file: '10-devis.png', click: '#nav-devis', ready: '#s-devis.active', pause: 1000 });
    await enrichDevisCapture(page);

    await dismissBlockingOverlays(page);
    await clickAndCapture(page, { file: '11-espace-mecanicien.png', click: '#user-avatar', ready: '#s-espace-meca.active', pause: 1000 });
    await enrichMecanicienCapture(page);

    await capturePublicPages(browser);
  } finally {
    await page.close().catch(() => {});
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});