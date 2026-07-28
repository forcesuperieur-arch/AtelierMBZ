import { test, expect } from '@playwright/test';
import { appUrl, loginAsAdmin } from './helpers.mjs';

/**
 * Administration en onglets : le menu de cartes a été remplacé par une barre
 * d'onglets persistante (routes imbriquées Nuxt), chaque onglet gardant son URL.
 */
test.describe('Administration — navigation par onglets', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('/admin ouvre directement le premier onglet', async ({ page }) => {
    await page.goto(appUrl('/admin'));
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/\/admin\/config/);
    await expect(page.locator('.tabstrip')).toBeVisible();
  });

  test('la barre d’onglets est présente sur chaque section', async ({ page }) => {
    for (const chemin of ['/admin/config', '/admin/users', '/admin/audit']) {
      await page.goto(appUrl(chemin));
      await page.waitForLoadState('networkidle');
      await expect(page.locator('.tabstrip')).toBeVisible();
      await expect(page.locator('.tab--active')).toHaveCount(1);
    }
  });

  test('cliquer un onglet change de section sans quitter le cadre', async ({ page }) => {
    await page.goto(appUrl('/admin/config'));
    await page.waitForLoadState('networkidle');

    await page.locator('[data-testid="admin-tab-users"]').click();
    await expect(page).toHaveURL(/\/admin\/users/);
    await expect(page.locator('.tabstrip')).toBeVisible();
    await expect(page.locator('body')).toContainText(/utilisateur/i);

    await page.locator('[data-testid="admin-tab-audit"]').click();
    await expect(page).toHaveURL(/\/admin\/audit/);
    await expect(page.locator('body')).toContainText(/audit/i);
  });

  test('l’onglet actif suit l’URL, y compris sur une sous-page', async ({ page }) => {
    await page.goto(appUrl('/admin/notifications/providers'));
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.tab--active')).toContainText('Notifications');
  });

  test('les onglets réservés au super admin sont présents pour un super admin', async ({ page }) => {
    await page.goto(appUrl('/admin/config'));
    await page.waitForLoadState('networkidle');

    // Le compte de test est super admin : les deux onglets réservés doivent exister.
    await expect(page.locator('[data-testid="admin-tab-ateliers"]')).toHaveCount(1);
    await expect(page.locator('[data-testid="admin-tab-roles"]')).toHaveCount(1);
  });

  test('le réglage du seuil de séjour reste accessible dans Configuration', async ({ page }) => {
    await page.goto(appUrl('/admin/config'));
    await page.waitForLoadState('networkidle');

    // Plus d'assistant par étapes : toutes les sections sont sur la même page.
    await page.locator('.sommaire-lien', { hasText: 'Horaires' }).click();
    await expect(page.locator('[data-testid="seuil-sejour-atelier"]')).toBeVisible();
    await expect(page.locator('[data-testid="toggle-alerte-sejour"]')).toBeVisible();
  });
});
