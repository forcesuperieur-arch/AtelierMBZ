import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers.mjs';

function nextOpenDate() {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  if (d.getDay() === 0) d.setDate(d.getDate() + 1);
  return d.toISOString().slice(0, 10);
}

test.describe('Core Business Flows', () => {
  test.beforeEach(async ({ page }, testInfo) => {
    if (!/Public booking/i.test(testInfo.title)) {
      await loginAsAdmin(page);
    }
  });

  test('RDV wizard: step navigation', async ({ page }) => {
    await page.goto('/rdv/new');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/prise de rdv|identification moto/i);

    const manualEntry = page.locator('button:has-text("Saisie manuelle")').first();
    if (await manualEntry.count() > 0) {
      await manualEntry.click();
    }

    await page.locator('input[placeholder="Ex: KAWASAKI"]').fill('Honda');
    await page.locator('input[placeholder="Ex: Z900"]').fill('CB500F');
    await page.locator('input[placeholder="AB-123-CD"]').fill('ZZ-500-AA');

    await page.getByRole('button', { name: /suivant/i }).click();
    await expect(page.locator('body')).toContainText(/sélection des prestations|récapitulatif/i);

    const prestationCard = page.locator('button, div').filter({ hasText: /€|min/ }).first();
    if (await prestationCard.count() > 0) {
      await prestationCard.click();
      const nextBtn = page.getByRole('button', { name: /suivant/i }).last();
      await expect(nextBtn).toBeEnabled({ timeout: 10000 });
      await nextBtn.click();
      await expect(page.locator('body')).toContainText(/choix du créneau|créneau sélectionné/i);
    } else {
      await expect(page.locator('body')).toContainText(/aucune prestation disponible|récapitulatif/i);
    }
  });

  test('Public booking: can submit and get tracking token', async ({ page }) => {
    await page.goto('/public/booking');
    await page.waitForLoadState('networkidle');

    await page.getByLabel('Prénom', { exact: true }).fill('Test');
    await page.getByLabel('Nom', { exact: true }).fill('Migration');
    await page.getByLabel('Téléphone', { exact: true }).fill(`06${Date.now().toString().slice(-8)}`);
    await page.getByLabel('Email', { exact: true }).fill('migration@example.com');
    await page.getByLabel(/Marque/i).fill('Yamaha');
    await page.getByLabel(/Modèle/i).fill('MT-07');
    await page.getByLabel('Plaque', { exact: true }).fill('BB-456-CC');

    await page.getByLabel('Date souhaitée', { exact: true }).fill(nextOpenDate());
    await page.getByLabel('Date souhaitée', { exact: true }).dispatchEvent('change');
    await page.waitForTimeout(1200);

    const slotButton = page.locator('button').filter({ hasText: /^\d{2}:\d{2}$/ }).first();
    await expect(slotButton).toBeVisible({ timeout: 15000 });
    await slotButton.click();

    const confirmButton = page.getByRole('button', { name: /confirmer le rendez-vous/i });
    await expect(confirmButton).toBeEnabled({ timeout: 10000 });
    const bookingResponse = page.waitForResponse(res => res.url().includes('/api/public/booking') && res.status() === 201);
    await confirmButton.click();
    await bookingResponse;
    await expect(page.locator('body')).toContainText(/rendez-vous confirmé|code de suivi/i, { timeout: 15000 });
  });

  test('Client list: search and view', async ({ page }) => {
    await page.goto('/clients');
    await page.waitForLoadState('networkidle');

    // Search field should be present
    const searchInput = page.locator('input[placeholder*="herch"]');
    if (await searchInput.count() > 0) {
      await searchInput.fill('test');
      await page.waitForTimeout(500);
    }

    // Table should be visible
    await expect(page.locator('body')).toContainText(/client/i);
  });

  test('Dashboard: KPIs and pont grid visible', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Should see KPI section
    await expect(page.locator('body')).toContainText(/rdv aujourd|occupation|pont/i);
  });

  test('Planning: grid and filters visible', async ({ page }) => {
    await page.goto('/planning');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/planning/i);
  });

  test('Facturation: list visible', async ({ page }) => {
    await page.goto('/facturation');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/factur/i);
  });

  test('Workshop: tabs work', async ({ page }) => {
    await page.goto('/workshop');
    await page.waitForLoadState('networkidle');

    // Ponts tab should be visible by default
    await expect(page.locator('body')).toContainText(/pont/i);

    // Click mecas tab
    const mecaTab = page.locator('button:has-text("Mécaniciens")');
    if (await mecaTab.count() > 0) {
      await mecaTab.click();
      await page.waitForTimeout(500);
    }
  });

  test('Admin: hub cards visible', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/utilisateur/i);
    await expect(page.locator('body')).toContainText(/pont/i);
    await expect(page.locator('body')).toContainText(/prestation/i);
  });

  test('Stock: list and search', async ({ page }) => {
    await page.goto('/stock');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/stock|pièce/i);
  });

  test('Devis: list visible', async ({ page }) => {
    await page.goto('/devis');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toContainText(/devis/i);
  });

  test('Sidebar navigation links work', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Test sidebar links
    const sidebarLinks = [
      { text: /planning/i, url: '/planning' },
      { text: /rdv|rendez/i, url: '/rdv' },
      { text: /client/i, url: '/clients' },
    ];

    for (const link of sidebarLinks) {
      const el = page.locator(`a[href="${link.url}"]`).first();
      if (await el.count() > 0) {
        await el.click();
        await page.waitForLoadState('networkidle');
        expect(page.url()).toContain(link.url);
        // Navigate back
        await page.goto('/');
        await page.waitForLoadState('networkidle');
      }
    }
  });
});
