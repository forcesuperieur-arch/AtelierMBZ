import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

/**
 * Espace client séparé (client-frontend, servi sur le port 81 via Caddy).
 * Couvre la phase 2 (auth fiable au F5) et la phase 3 (demande d'annulation).
 * Compte de test : jean.moreau@email.fr / ClientTest123! (cf. seed dev).
 */
const PORTAL = 'http://localhost:81/client';
const CLIENT_EMAIL = 'jean.moreau@email.fr';
const CLIENT_PASSWORD = process.env.CLIENT_TEST_PASSWORD || 'ClientTest123!';

// RDV seedé du client de test, réutilisé par le flux d'annulation (remis en état avant le test)
const ANNULATION_RDV_ID = 269;
const PSQL = 'docker compose exec -T db psql -U atelier -d atelier_moto -tA -c';

function sql(query) {
  return execSync(`${PSQL} "${query.replace(/"/g, '\\"')}"`, { encoding: 'utf8' }).trim();
}

/** JWT staff extrait du storage state généré par auth.setup.mjs. */
function adminAuthHeaders() {
  const state = JSON.parse(readFileSync('playwright/.auth/admin.json', 'utf8'));
  const token = state.cookies.find((c) => c.name === 'access_token')?.value;
  return { Authorization: `Bearer ${token}` };
}

async function loginAsClient(page) {
  await page.goto(`${PORTAL}/login`);
  await page.fill('input[type="email"]', CLIENT_EMAIL);
  await page.fill('input[type="password"]', CLIENT_PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/client\/?$/, { timeout: 15000 });
}

test.describe('Espace client', () => {
  test('login affiche le tableau de bord', async ({ page }) => {
    await loginAsClient(page);
    await expect(page.locator('body')).toContainText(/tableau de bord|mes rdv|mes motos/i);
  });

  test('F5 ne déconnecte pas (réhydratation cookie)', async ({ page }) => {
    await loginAsClient(page);
    await page.reload();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(2500);
    expect(page.url()).not.toContain('/login');
  });

  test('access token expiré → refresh silencieux', async ({ page, context }) => {
    await loginAsClient(page);
    await context.clearCookies({ name: 'client_access_token' });
    await page.goto(`${PORTAL}/rdvs`);
    await page.waitForTimeout(3000);
    expect(page.url()).not.toContain('/login');
    const cookies = await context.cookies();
    expect(cookies.some(c => c.name === 'client_access_token')).toBe(true);
  });

  test('pages protégées inaccessibles sans session', async ({ page }) => {
    await page.goto(`${PORTAL}/motos`);
    await page.waitForURL(/\/client\/login/, { timeout: 15000 });
    expect(page.url()).toContain('/login');
  });

  test('mes RDV : statuts lisibles, navigation détail', async ({ page }) => {
    await loginAsClient(page);
    await page.goto(`${PORTAL}/rdvs`);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(2000);

    const firstCard = page.locator('.rdv-card').first();
    if (!(await firstCard.isVisible().catch(() => false))) {
      test.skip(true, 'Aucun RDV pour le client de test');
    }
    // Les statuts bruts (snake_case) ne doivent pas apparaître
    await expect(page.locator('body')).not.toContainText(/en_attente|pret_restitution|travaux_supp/);

    await firstCard.click();
    await page.waitForURL(/\/client\/rdvs\/\d+/, { timeout: 10000 });
    await expect(page.locator('body')).toContainText(/détail du rendez-vous/i);
  });

  test('demande d\'annulation : flux client complet (bouton → confirmation → bannière)', async ({ page }) => {
    // RDV de test remis dans un état annulable (futur, confirmé, sans demande)
    sql(`UPDATE rendez_vous SET statut = 'confirme', date_rdv = CURRENT_DATE + 7, heure_rdv = '10:00:00', annulation_demandee_at = NULL WHERE id = ${ANNULATION_RDV_ID}`);

    await loginAsClient(page);
    await page.goto(`${PORTAL}/rdvs/${ANNULATION_RDV_ID}`);

    const btn = page.getByRole('button', { name: /demander l'annulation/i });
    await expect(btn).toBeVisible({ timeout: 15000 });

    // Garde-fou : on peut renoncer
    await btn.click();
    await expect(page.locator('.annulation-confirm')).toBeVisible();
    await page.getByRole('button', { name: /garder mon rdv/i }).click();
    await expect(page.locator('.annulation-confirm')).not.toBeVisible();

    // Envoi réel de la demande
    await btn.click();
    const postResponse = page.waitForResponse(
      (res) => res.url().includes('/demande-annulation') && res.request().method() === 'POST'
    );
    await page.getByRole('button', { name: /oui, demander/i }).click();
    expect((await postResponse).status()).toBe(200);

    await expect(page.locator('.annulation-banner')).toBeVisible({ timeout: 10000 });
    expect(sql(`SELECT annulation_demandee_at IS NOT NULL FROM rendez_vous WHERE id = ${ANNULATION_RDV_ID}`)).toBe('t');

    // Une seconde demande est refusée tant que la première est en cours
    const second = await page.request.post(`${PORTAL.replace('/client', '')}/api/client/rdvs/${ANNULATION_RDV_ID}/demande-annulation`);
    expect(second.status()).toBe(409);
  });

  test('demande d\'annulation : le staff peut refuser, le flag est levé', async ({ request }) => {
    expect(sql(`SELECT annulation_demandee_at IS NOT NULL FROM rendez_vous WHERE id = ${ANNULATION_RDV_ID}`)).toBe('t');

    const headers = adminAuthHeaders();
    const res = await request.post(`/api/rendez-vous/${ANNULATION_RDV_ID}/demande-annulation/refuser`, { headers });
    expect(res.status()).toBe(200);

    expect(sql(`SELECT annulation_demandee_at IS NOT NULL FROM rendez_vous WHERE id = ${ANNULATION_RDV_ID}`)).toBe('f');

    // Refus répété → 409 (aucune demande en cours)
    const again = await request.post(`/api/rendez-vous/${ANNULATION_RDV_ID}/demande-annulation/refuser`, { headers });
    expect(again.status()).toBe(409);
  });

  test('isolation : un client ne voit jamais les RDV d\'un autre client', async ({ page }) => {
    // Le RDV 2 appartient à un autre client (seed) — toutes les routes de
    // ressources doivent répondre 404, jamais les données d'autrui.
    const FOREIGN_RDV_ID = 2;
    await loginAsClient(page);

    const detail = await page.request.get(`${PORTAL.replace('/client', '')}/api/client/rdvs/${FOREIGN_RDV_ID}`);
    expect(detail.status()).toBe(404);

    const annulation = await page.request.post(`${PORTAL.replace('/client', '')}/api/client/rdvs/${FOREIGN_RDV_ID}/demande-annulation`);
    expect(annulation.status()).toBe(404);

    const pdf = await page.request.get(`${PORTAL.replace('/client', '')}/api/client/rdvs/${FOREIGN_RDV_ID}/or/1/pdf`);
    expect(pdf.status()).toBe(404);
  });

  test('logout ferme la session', async ({ page }) => {
    await loginAsClient(page);
    const logoutBtn = page.locator('button:has-text("Déconnexion"), a:has-text("Déconnexion")').first();
    await logoutBtn.click();
    await page.waitForURL(/\/client\/login/, { timeout: 10000 });
    await page.goto(`${PORTAL}/motos`);
    await page.waitForURL(/\/client\/login/, { timeout: 15000 });
  });
});
