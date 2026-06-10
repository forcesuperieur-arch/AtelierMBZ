import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers.mjs';

test.describe('Navigation Smoke Tests', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  const routes = [
    { path: '/', name: 'Dashboard', text: /dashboard|tableau|ponts|rdv/i },
    { path: '/planning', name: 'Planning', text: /planning/i },
    { path: '/rdv', name: 'RDV Liste', text: /rendez-vous|rdv/i },
    { path: '/rdv/new', name: 'Nouveau RDV', text: /nouveau|véhicule|rendez-vous/i },
    { path: '/clients', name: 'Clients', text: /client/i },
    { path: '/workshop', name: 'Atelier', text: /atelier|pont|mécanicien/i },
    { path: '/mecanicien', name: 'Espace Mécanicien', text: /mécanicien|intervention/i },
    { path: '/facturation', name: 'Facturation', text: /factur/i },
    { path: '/stock', name: 'Stock', text: /stock|pièce/i },
    { path: '/motos', name: 'Motos', text: /moto|catalogue/i },
    { path: '/suivi', name: 'Suivi Live', text: /suivi|live/i },
    { path: '/tarifs', name: 'Tarifs', text: /tarif|prestation/i },
    { path: '/devis', name: 'Devis', text: /devis/i },
    { path: '/admin', name: 'Admin', text: /admin|configuration/i },
    { path: '/admin/users', name: 'Admin Utilisateurs', text: /utilisateur/i },
    { path: '/admin/config', name: 'Admin Config', text: /config/i },
    { path: '/admin/ponts', name: 'Admin Ponts', text: /pont/i },
    { path: '/admin/prestations', name: 'Admin Prestations', text: /prestation/i },
    { path: '/admin/absences', name: 'Admin Absences', text: /absence/i },
    { path: '/admin/audit', name: 'Admin Audit', text: /audit|log/i },
  ];

  for (const route of routes) {
    test(`${route.name} (${route.path}) loads without errors`, async ({ page }) => {
      const errors = [];
      page.on('pageerror', (err) => errors.push(err.message));

      const response = await page.goto(route.path);
      expect(response.status()).toBeLessThan(500);

      await page.waitForLoadState('networkidle');
      // Module désactivé : le middleware redirige vers /?moduleDisabled=<section>
      if (!page.url().includes('moduleDisabled=')) {
        await expect(page.locator('body')).toContainText(route.text, { timeout: 10000 });
      }

      // Erreur connue : un admin sans profil mécanicien lié déclenche ce throw sur /mecanicien
      const knownErrors = [/Aucun profil mécanicien lié/];
      expect(errors.filter(e => !knownErrors.some(re => re.test(e)))).toEqual([]);
    });
  }
});
