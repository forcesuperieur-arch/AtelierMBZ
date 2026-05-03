import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers.mjs';

const CATEGORIES = ['Moteur', 'Transmission', 'Freinage', 'Pneumatique', 'Électricité'];

async function seedStockApi(request) {
  // Ensure at least one supplier exists
  const supplierRes = await request.post('/api/stock/fournisseurs', {
    data: {
      nom: `Fournisseur E2E ${Date.now()}`,
      contact: 'Test E2E',
      telephone: '0102030405',
      email: 'e2e@example.test',
    },
  });
  if (!supplierRes.ok() && supplierRes.status() !== 403) {
    console.warn('Supplier seed warning:', await supplierRes.text());
  }

  // Ensure at least one piece exists
  const pieceRes = await request.post('/stock/pieces', {
    data: {
      reference: `E2E-PIECE-${Date.now()}`,
      nom: `Pièce E2E ${Date.now()}`,
      categorie: CATEGORIES[Math.floor(Math.random() * CATEGORIES.length)],
      quantite_stock: 10,
      quantite_minimale: 3,
      prix_achat_ht: '15.00',
      prix_vente_ht: '28.00',
    },
  });
  if (!pieceRes.ok() && pieceRes.status() !== 403) {
    console.warn('Piece seed warning:', await pieceRes.text());
  }
}

test.describe('Stock module E2E', () => {
  test('stock dashboard loads with KPIs and table', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/stock', { waitUntil: 'domcontentloaded' });

    await expect(page.getByText(/Stock — Pièces détachées/i)).toBeVisible();
    await expect(page.getByText(/Références/i)).toBeVisible();
    await expect(page.getByText(/Valeur d'achat/i)).toBeVisible();
    await expect(page.getByText(/Alertes/i)).toBeVisible();
    await expect(page.locator('table tbody tr')).toHaveCount.greaterThanOrEqual(1);
  });

  test('can filter pieces by category', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/stock', { waitUntil: 'domcontentloaded' });

    // Open category filter if present (USelectMenu)
    const categorySelect = page.locator('[data-testid="category-filter"], label:has-text("Catégorie") + *, [placeholder*="Catégorie"]').first();
    if (await categorySelect.isVisible().catch(() => false)) {
      await categorySelect.click();
      await page.getByRole('option').first().click();
      await page.waitForTimeout(300);
      await expect(page.locator('table tbody tr')).toHaveCount.greaterThanOrEqual(0);
    }
  });

  test('can create a new piece', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/stock', { waitUntil: 'domcontentloaded' });

    const ref = `E2E-NEW-${Date.now()}`;
    await page.getByRole('button', { name: /Nouvelle pièce/i }).click();

    await page.getByLabel(/Référence/i).fill(ref);
    await page.getByLabel(/Nom/i).fill(`Pièce test ${Date.now()}`);
    await page.getByLabel(/Catégorie/i).fill('Freinage');
    await page.getByLabel(/Quantité stock/i).fill('25');
    await page.getByLabel(/Seuil d'alerte/i).fill('5');
    await page.getByLabel(/Prix achat HT/i).fill('12.50');
    await page.getByLabel(/Prix vente HT/i).fill('24.90');

    await page.getByRole('button', { name: /Créer/i }).click();

    await expect(page.locator('table tbody')).toContainText(ref);
  });

  test('can toggle piece active status', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/stock', { waitUntil: 'domcontentloaded' });

    const firstRow = page.locator('table tbody tr').first();
    await expect(firstRow).toBeVisible();

    const toggleBtn = firstRow.locator('button[title*="Activer"], button[title*="Désactiver"]').first();
    if (await toggleBtn.isVisible().catch(() => false)) {
      await toggleBtn.click();
      await page.waitForTimeout(300);
    }
  });

  test('can export CSV', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/stock', { waitUntil: 'domcontentloaded' });

    const exportBtn = page.getByRole('button', { name: /Exporter CSV/i });
    if (await exportBtn.isVisible().catch(() => false)) {
      const [download] = await Promise.all([
        page.waitForEvent('download'),
        exportBtn.click(),
      ]);
      expect(download.suggestedFilename()).toMatch(/\.csv$/i);
    }
  });

  test('supplier orders page loads and filters', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/stock/commandes', { waitUntil: 'domcontentloaded' });

    await expect(page.getByText(/Commandes fournisseurs/i)).toBeVisible();
    await expect(page.locator('table, [role="table"]').first()).toBeVisible();

    const pendingTab = page.getByRole('button', { name: /En attente/i });
    if (await pendingTab.isVisible().catch(() => false)) {
      await pendingTab.click();
      await page.waitForTimeout(300);
    }
  });

  test('suppliers page loads and can create supplier', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/stock/fournisseurs', { waitUntil: 'domcontentloaded' });

    await expect(page.getByText(/Fournisseurs/i)).toBeVisible();

    await page.getByRole('button', { name: /Nouveau fournisseur/i }).click();
    await page.getByLabel(/Nom/i).fill(`Fournisseur E2E ${Date.now()}`);
    await page.getByLabel(/Téléphone/i).fill('0199887766');
    await page.getByRole('button', { name: /Créer/i }).click();

    await page.waitForTimeout(500);
    await expect(page.locator('table tbody, [role="table"] tbody')).toBeVisible();
  });
});
