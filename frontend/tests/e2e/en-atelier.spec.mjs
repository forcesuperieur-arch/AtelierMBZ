import { test, expect } from '@playwright/test';
import { appUrl, loginAsAdmin } from './helpers.mjs';

/**
 * Onglet « En atelier » : suivi des motos présentes, ancienneté en heures ouvrées,
 * alerte au-delà du seuil et fiche actionnable.
 *
 * Ces tests sont volontairement NON MUTANTS (aucune transition, aucune affectation,
 * aucune relance envoyée) pour ne pas polluer la base partagée avec les autres specs.
 */
test.describe('Suivi des motos en atelier', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('API séjour: la liste expose ancienneté ouvrée et drapeau de dépassement', async ({ page }) => {
    await page.goto(appUrl('/'));
    await page.waitForLoadState('domcontentloaded');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/sejour-atelier/motos', { credentials: 'include' });
      return { status: res.status, body: await res.json() };
    });

    expect(response.status).toBe(200);
    expect(response.body).toHaveProperty('total');
    expect(response.body).toHaveProperty('total_depassement');
    expect(response.body).toHaveProperty('seuil_heures', 72);
    expect(Array.isArray(response.body.motos)).toBe(true);

    if (response.body.motos.length > 0) {
      const moto = response.body.motos[0];
      expect(moto).toHaveProperty('heures_ouvrees');
      expect(moto).toHaveProperty('en_depassement');
      expect(moto).toHaveProperty('recu_le');
      // Tri : du séjour le plus long au plus court
      const heures = response.body.motos.map((m) => m.heures_ouvrees);
      expect([...heures].sort((a, b) => b - a)).toEqual(heures);
      // Seuls des statuts « moto physiquement présente »
      const statutsAttendus = ['reception', 'en_cours', 'en_pause', 'en_attente_pieces', 'en_attente_reprise', 'en_gardiennage'];
      for (const m of response.body.motos) {
        expect(statutsAttendus).toContain(m.statut);
      }
    }
  });

  test('API alertes: ne renvoie que les dépassements du seuil demandé', async ({ page }) => {
    await page.goto(appUrl('/'));
    await page.waitForLoadState('domcontentloaded');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/sejour-atelier/alertes?seuil=48', { credentials: 'include' });
      return { status: res.status, body: await res.json() };
    });

    expect(response.status).toBe(200);
    expect(response.body.seuil_heures).toBe(48);
    for (const moto of response.body.motos) {
      expect(moto.heures_ouvrees).toBeGreaterThan(48);
    }
  });

  test('Relance refusée proprement sur un RDV inexistant', async ({ page }) => {
    await page.goto(appUrl('/'));
    await page.waitForLoadState('domcontentloaded');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/sejour-atelier/99999999/relancer', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: 'test' }),
      });
      return { status: res.status, body: await res.json() };
    });

    expect(response.status).toBe(404);
    expect(response.body.error).toBe('RDV_NOT_FOUND');
  });

  test('Onglet accessible depuis la barre latérale', async ({ page }) => {
    await page.goto(appUrl('/'));
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('a[href="/en-atelier"]').first()).toHaveCount(1);
  });

  test('La page affiche les KPI et le tableau de suivi', async ({ page }) => {
    await page.goto(appUrl('/en-atelier'));
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('.page-title')).toContainText('Motos en atelier');
    await expect(page.locator('.kpi-card').first()).toBeVisible({ timeout: 15000 });

    const total = Number(
      (await page.locator('.kpi-card').first().locator('.kpi-value').innerText()).trim(),
    );
    expect(Number.isNaN(total)).toBe(false);

    if (total > 0) {
      await expect(page.locator('.table-atelier thead')).toBeVisible();
      const lignes = await page.locator('.table-atelier tbody tr').count();
      expect(lignes).toBeGreaterThan(0);
    }
  });

  test('Le filtre « seulement les dépassements » restreint la liste', async ({ page }) => {
    await page.goto(appUrl('/en-atelier'));
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.kpi-card').first()).toBeVisible({ timeout: 15000 });

    const lignesAvant = await page.locator('.table-atelier tbody tr').count();
    if (lignesAvant === 0) test.skip(true, 'Aucune moto en atelier dans cet environnement');

    await page.locator('.puce', { hasText: 'Seulement les dépassements' }).click();
    await page.waitForTimeout(500);

    const lignesApres = await page.locator('.table-atelier tbody tr').count();
    expect(lignesApres).toBeLessThanOrEqual(lignesAvant);
    // Chaque ligne restante doit être marquée en dépassement
    if (lignesApres > 0) {
      expect(await page.locator('.table-atelier tbody tr.ligne--depassement').count()).toBe(lignesApres);
    }
  });

  test('La fiche « Traiter » propose les actions métier', async ({ page }) => {
    await page.goto(appUrl('/en-atelier'));
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.kpi-card').first()).toBeVisible({ timeout: 15000 });

    const lignes = await page.locator('.table-atelier tbody tr').count();
    if (lignes === 0) test.skip(true, 'Aucune moto en atelier dans cet environnement');

    await page.locator('.lien-action', { hasText: 'Traiter' }).first().click();

    await expect(page.locator('.bloc-titre', { hasText: 'Relancer le client' })).toBeVisible({ timeout: 10000 });
    await expect(page.locator('.bloc-titre', { hasText: 'Mécanicien' })).toBeVisible();
    await expect(page.locator('.bloc-titre', { hasText: 'Faire avancer le dossier' })).toBeVisible();
    // Les actions de statut viennent du serveur : soit des boutons, soit un message explicite
    const boutonsStatut = await page
      .locator('.bloc', { hasText: 'Faire avancer le dossier' })
      .locator('button')
      .count();
    if (boutonsStatut === 0) {
      await expect(page.locator('.bloc', { hasText: 'Faire avancer le dossier' })).toContainText('Aucune action');
    }
  });
});
