import { test, expect } from '@playwright/test';
import { appUrl, loginAsAdmin } from './helpers.mjs';

/**
 * Recette de l'état des lieux photo — maquette 47b.
 *
 * Trois propriétés que la maquette impose et qui se perdent en premier :
 * il s'ouvre en PANNEAU et non en modale (règle 4), les deux séries suivent
 * le MÊME ORDRE d'angles — c'est ce qui fait la comparaison, donc la preuve —
 * et une série de sortie incomplète n'empêche PAS la restitution.
 */
test.describe('État des lieux photo (47b)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto(appUrl('/reception'));
    await page.waitForLoadState('networkidle');
  });

  test("s'ouvre en panneau à droite, pas en modale", async ({ page }) => {
    const declencheur = page.locator('[data-testid="btn-edl-panneau"]').first();
    test.skip(await declencheur.count() === 0, 'Aucune moto attendue aujourd\'hui sur cette base');

    await declencheur.click();
    const panneau = page.locator('.pk-panel');
    await expect(panneau).toBeVisible();

    // Un panneau est ANCRÉ À DROITE et ne voile pas le contenu : le design
    // system interdit le fond assombri comme le flou. La tolérance couvre la
    // barre de défilement, qui n'a rien à voir avec la propriété testée.
    const boite = await panneau.boundingBox();
    const largeur = page.viewportSize().width;
    expect(largeur - (boite.x + boite.width), 'le panneau doit être collé au bord droit').toBeLessThanOrEqual(20);
    expect(boite.x, 'le panneau occupe la droite, pas tout l\'écran').toBeGreaterThan(largeur / 2);
    await expect(page.locator('.app-modal-overlay')).toHaveCount(0);
  });

  test('les deux séries portent les mêmes angles, dans le même ordre', async ({ page }) => {
    const declencheur = page.locator('[data-testid="btn-edl-panneau"]').first();
    test.skip(await declencheur.count() === 0, 'Aucune moto attendue aujourd\'hui sur cette base');

    await declencheur.click();
    await expect(page.locator('.pk-panel')).toBeVisible();

    const series = page.locator('.edl-serie');
    await expect(series).toHaveCount(2);
    const entree = await series.nth(0).locator('.edl-angle').allInnerTexts();
    const sortie = await series.nth(1).locator('.edl-angle').allInnerTexts();
    expect(entree.length).toBe(6);
    expect(sortie, "47b : « même ordre à l'entrée et à la sortie, c'est la comparaison qui fait preuve »").toEqual(entree);
  });

  test("dit que la restitution reste possible sans bloquer", async ({ page }) => {
    const declencheur = page.locator('[data-testid="btn-edl-panneau"]').first();
    test.skip(await declencheur.count() === 0, 'Aucune moto attendue aujourd\'hui sur cette base');

    await declencheur.click();
    await expect(page.locator('.edl-avis-texte')).toContainText('reste possible');
    await expect(page.locator('.edl-avis-texte')).toContainText('photos de sortie sur 6');
  });

  test('Échap ferme le panneau', async ({ page }) => {
    const declencheur = page.locator('[data-testid="btn-edl-panneau"]').first();
    test.skip(await declencheur.count() === 0, 'Aucune moto attendue aujourd\'hui sur cette base');

    await declencheur.click();
    await expect(page.locator('.pk-panel')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('.pk-panel')).toHaveCount(0);
  });
});
