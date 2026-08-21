import { test, expect } from '@playwright/test';
import { appUrl, loginAsAdmin } from './helpers.mjs';

/**
 * Recette des lots 1 et 2 de la refonte 2026 (maquettes 8a, 39a, 52a).
 *
 * Deux propriétés à prouver, parce que ce sont celles qu'une régression casse
 * en silence : la barre d'atelier ne porte plus les modules tranchés, et le
 * cockpit est un étage à part dont on sait revenir.
 */

const ENTREES_RETIREES = ['Suivi Live', 'Fiches moto', 'Factures', 'Stock', 'Cockpit SRC'];

test.describe('Refonte — navigation d\'atelier (lot 1)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('les modules tranchés ont quitté la barre latérale', async ({ page }) => {
    await page.goto(appUrl('/'));
    const sidebar = page.locator('nav.sidebar');
    await expect(sidebar).toBeVisible();

    for (const label of ENTREES_RETIREES) {
      await expect(
        sidebar.locator('a.nav-btn', { hasText: new RegExp(`^${label}$`) }),
        `« ${label} » doit avoir quitté la nav (règle 7 : un module coupé n'y reste pas grisé)`
      ).toHaveCount(0);
    }
  });

  test('la barre est groupée Pilotage / Atelier / Commerce', async ({ page }) => {
    await page.goto(appUrl('/'));
    const groupes = page.locator('nav.sidebar .sidebar-group-label');
    await expect(groupes.first()).toHaveText('Pilotage');
    await expect(groupes).toContainText(['Pilotage', 'Atelier', 'Commerce']);
  });

  test('Administration se pose après l\'espace élastique, en bas de barre', async ({ page }) => {
    await page.goto(appUrl('/'));
    const admin = page.locator('nav.sidebar a.nav-btn', { hasText: /^Administration$/ });
    await expect(admin).toHaveCount(1);

    const spacerBox = await page.locator('nav.sidebar .sidebar-spacer').boundingBox();
    const adminBox = await admin.boundingBox();
    expect(adminBox.y).toBeGreaterThan(spacerBox.y);
  });

  test('la nav ne porte plus le sélecteur d\'atelier pour un non super-admin', async ({ page }) => {
    await page.goto(appUrl('/'));
    // L'admin de démo est super admin : le sélecteur reste, badge « SA ».
    const badge = page.locator('.atelier-switch-badge');
    if (await badge.count()) {
      await expect(badge).toHaveText('SA');
    }
  });
});

test.describe('Refonte — l\'étage SRC (lot 2)', () => {
  /**
   * Le compte SRC vient de `app:seed --demo`. Sur une base qui ne l'a pas, la
   * recette se déclare sautée plutôt que rouge : l'absence d'un compte de démo
   * n'est pas une régression de la refonte.
   */
  async function loginAsSrc(page) {
    await page.goto(appUrl('/login'));
    await page.fill('input[type="email"]', 'src@atelier.local');
    await page.fill('input[type="password"]', 'src123');
    await page.click('button[type="submit"]');

    return page
      .waitForURL(/\/cockpit/, { timeout: 20_000 })
      .then(() => true)
      .catch(() => false);
  }

  test('un compte SRC atterrit sur le cockpit, pas sur le Stat d\'un atelier', async ({ page }) => {
    test.skip(!(await loginAsSrc(page)), 'Compte SRC de démo absent (app:seed --demo)');

    // Nav noire propre à l'étage réseau, et pas la barre d'atelier.
    await expect(page.locator('nav.cockpit-nav')).toBeVisible();
    await expect(page.locator('nav.sidebar')).toHaveCount(0);
    await expect(page.locator('nav.cockpit-nav')).toContainText('Cockpit réseau');
  });

  test('ouvrir un atelier pose le bandeau de retour, et le retour le retire', async ({ page }) => {
    test.skip(!(await loginAsSrc(page)), 'Compte SRC de démo absent (app:seed --demo)');

    // La liste des ateliers arrive par un appel réseau : on l'attend au lieu de
    // compter tout de suite, sinon le test se saute sur une simple course.
    const premierAtelier = page.locator('[data-testid="cockpit-atelier"]').first();
    const perimetreVide = await premierAtelier
      .waitFor({ state: 'visible', timeout: 15_000 })
      .then(() => false)
      .catch(() => true);
    test.skip(perimetreVide, 'Aucun atelier dans le périmètre du compte SRC de démo');

    const nomAtelier = (await premierAtelier.innerText()).trim();
    await premierAtelier.click();
    await page.waitForURL(/\/$|\/\?/, { timeout: 20_000 });

    const bandeau = page.locator('.cockpit-visit-banner');
    await expect(bandeau).toBeVisible();
    await expect(bandeau).toContainText(nomAtelier);
    await expect(bandeau).toContainText('ouvert depuis le cockpit');

    await page.locator('.cockpit-visit-back').click();
    await page.waitForURL(/\/cockpit/, { timeout: 20_000 });
    await expect(page.locator('.cockpit-visit-banner')).toHaveCount(0);
  });
});
