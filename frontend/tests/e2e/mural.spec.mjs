import { test, expect } from '@playwright/test';
import { appUrl, loginAsAdmin } from './helpers.mjs';

/**
 * Recette de l'affichage mural — maquette 47a.
 *
 * Ce que le tour 47a impose et qu'aucune relecture humaine ne rattrape :
 * l'écran se lit de loin, donc rien sous 20 px ; il n'a aucune interaction,
 * donc rien de cliquable ; et il affiche l'heure de son rafraîchissement,
 * parce qu'« un écran figé ressemble à un écran à jour ».
 */
test.describe('Affichage mural (47a)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto(appUrl('/mural'));
    await page.waitForLoadState('networkidle');
  });

  test('aucun texte visible sous 20 px', async ({ page }) => {
    const trop = await page.evaluate(() => {
      const petits = [];
      for (const el of document.querySelectorAll('.mural *')) {
        if (!el.textContent?.trim()) continue;
        // Seuls les nœuds qui portent DIRECTEMENT du texte comptent.
        const porteDuTexte = [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim());
        if (!porteDuTexte) continue;
        const taille = parseFloat(getComputedStyle(el).fontSize);
        if (taille < 20) petits.push(`${el.className || el.tagName} @ ${taille}px : ${el.textContent.trim().slice(0, 40)}`);
      }
      return petits;
    });
    expect(trop, `Le mural se lit de loin : 47a interdit tout texte sous 20 px.\n${trop.join('\n')}`).toEqual([]);
  });

  test("l'écran ne porte aucune interaction", async ({ page }) => {
    const cliquables = await page.locator('.mural button, .mural a, .mural input, .mural select, .mural [role="button"]').count();
    expect(cliquables, "47a : « aucune interaction » — le mural n'a ni bouton, ni lien, ni champ.").toBe(0);
  });

  test("l'état de fraîcheur est affiché", async ({ page }) => {
    await expect(page.locator('.mural-fraicheur')).toBeVisible();
    await expect(page.locator('.mural-heure')).toBeVisible();
  });

  test('les trois colonnes de 47a sont là', async ({ page }) => {
    const libelles = await page.locator('.mural-libelle').allInnerTexts();
    expect(libelles.map(t => t.toLowerCase())).toEqual(
      expect.arrayContaining([expect.stringContaining('sortent'), expect.stringContaining('ponts'), expect.stringContaining('attente')]),
    );
  });

  test('aucun temps nominatif sur les ponts (règle 6)', async ({ page }) => {
    // 47a affiche « Karim · 1 h 40 / 2 h ». L'option A du tour 51c retire le
    // rapport vendu/pointé : le prénom dit qui appeler, le ratio dirait qui est
    // lent — et un mur se lit de tout l'atelier.
    const texte = (await page.locator('.mural-pont-pied').allInnerTexts()).join(' ');
    expect(texte, 'Règle 6 : aucun écran de pilotage n\'attribue un temps nominativement.').not.toMatch(/\d+\s*h\s*\d*\s*\/\s*\d/);
  });
});
