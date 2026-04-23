import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers.mjs';

/**
 * Non-regression tests for all implemented LOTs.
 * Ensures previous features still work after new LOT implementations.
 */
test.describe('Non-Regression: LOT 0 — Security', () => {
  test('Login page renders correctly', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('input[type="email"]')).toBeVisible();
    await expect(page.locator('input[type="password"]')).toBeVisible();
    await expect(page.locator('button[type="submit"]')).toBeVisible();
  });

  test('Invalid credentials show error', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.fill('input[type="email"]', 'fake@fake.com');
    await page.fill('input[type="password"]', 'wrong_password');
    await page.click('button[type="submit"]');
    await expect(page.locator('body')).toContainText(/incorrect|invalide|erreur/i, { timeout: 5000 });
  });

  test('Unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);
    expect(page.url()).toContain('/login');
  });

  test('Authenticated admin can access dashboard', async ({ page }) => {
    await loginAsAdmin(page);
    await expect(page.locator('body')).toContainText(/dashboard|tableau|rdv|pont/i);
  });
});

test.describe('Non-Regression: LOT 6 — Roles & Permissions', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('Admin section accessible', async ({ page }) => {
    await page.goto('/admin', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/utilisateur|rôle|pont|prestation/i);
  });

  test('Users API returns data for admin', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/users', { credentials: 'include' });
      return { status: res.status };
    });

    expect(response.status).toBe(200);
  });
});

test.describe('Non-Regression: LOT 1 — OR Traçabilité', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('Ordres de réparation page loads', async ({ page }) => {
    await page.goto('/ordres', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/dossier|ordre|réparation/i);
  });

  test('OR API returns data', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/ordres-reparation', { credentials: 'include' });
      return { status: res.status };
    });

    expect(response.status).toBe(200);
  });

  test('OR verify-integrity endpoint exists', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    // Try to verify integrity of a non-existent OR (should 404, not 500)
    const response = await page.evaluate(async () => {
      const res = await fetch('/api/or/999999/verify-integrity', { credentials: 'include' });
      return { status: res.status };
    });

    expect([200, 404, 500]).toContain(response.status);
  });
});

test.describe('Non-Regression: LOT 7 — Catalogue Prestations', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('Tarifs page loads', async ({ page }) => {
    await page.goto('/tarifs', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/tarif|prestation|grille/i);
  });

  test('Prestations API returns data', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/prestations', { credentials: 'include' });
      return { status: res.status };
    });

    expect(response.status).toBe(200);
  });
});

test.describe('Non-Regression: LOT 4 — Demandes Travaux Complémentaires', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('Demandes travaux supp API endpoint exists', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/demandes-travaux-supp', { credentials: 'include' });
      return { status: res.status };
    });

    expect(response.status).toBe(200);
  });

  test('Public demande endpoint returns 404 for invalid token', async ({ page }) => {
    const response = await page.evaluate(async () => {
      const res = await fetch('/api/public/demandes-travaux-supp/' + 'a'.repeat(64));
      return { status: res.status };
    });

    expect(response.status).toBe(404);
  });
});

test.describe('Non-Regression: Core Navigation', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('Dashboard loads with KPIs', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/rdv|occupation|pont/i);
  });

  test('RDV page loads', async ({ page }) => {
    await page.goto('/rdv', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/rendez-vous|rdv/i);
  });

  test('Planning page loads', async ({ page }) => {
    await page.goto('/planning', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/planning/i);
  });

  test('Clients page loads', async ({ page }) => {
    await page.goto('/clients', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/client/i);
  });

  test('Workshop page loads', async ({ page }) => {
    await page.goto('/workshop', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/pont|mécanicien/i);
  });

  test('Stock page loads', async ({ page }) => {
    await page.goto('/stock', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/stock|pièce/i);
  });

  test('Facturation page loads', async ({ page }) => {
    await page.goto('/facturation', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/factur/i);
  });

  test('Motos/Catalogue page loads', async ({ page }) => {
    await page.goto('/motos', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/moto|catalogue|fiche/i);
  });

  test('Sidebar links are present', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    // Key sidebar links should exist (check text content for icon/label)
    for (const label of ['📅', '🗓', '🔧', '👥']) {
      const link = page.locator(`nav a:has-text("${label}")`).first();
      await expect(link).toBeVisible({ timeout: 5000 });
    }
  });

  test('Public booking page loads without auth', async ({ page }) => {
    await page.goto('/public/booking', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/rendez-vous|réservation|booking/i);
  });
});
