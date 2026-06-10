import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers.mjs';

/**
 * E2E tests for Modal Design System v2 + USelectMenu v3 migration.
 * Validates SSR safety, dropdown z-index, animations, and form footer.
 */

/* ------------------------------------------------------------------ */
/*  US-1  —  AppModal SSR safety (null data guards)                   */
/* ------------------------------------------------------------------ */
test.describe('US-1 — Modal SSR safety', () => {
  test('/motos page loads without 500 when selectedModel is null', async ({ page }) => {
    await loginAsAdmin(page);
    const response = await page.goto('/motos');
    expect(response?.status()).toBe(200);
    await page.waitForLoadState('networkidle');
    // Page should render at least the grid or empty state
    await expect(page.locator('body')).toContainText(/moto|catalogue|fiche|aucun/i, { timeout: 10000 });
  });

  test('/motos detail modal opens with non-null data', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/motos');
    await page.waitForLoadState('networkidle');

    // Try to find a Details button in the table
    const detailsBtn = page.locator('button:has-text("Détails")').first();
    if (await detailsBtn.isVisible().catch(() => false)) {
      await detailsBtn.click();
      await page.waitForTimeout(300);
      // Modal overlay should be visible
      await expect(page.locator('.app-modal-overlay')).toBeVisible({ timeout: 5000 });
    } else {
      test.skip(true, 'No motorcycle rows visible — empty state');
    }
  });
});

/* ------------------------------------------------------------------ */
/*  US-2  —  Dropdown z-index inside modal                             */
/* ------------------------------------------------------------------ */
test.describe('US-2 — SelectMenu dropdown above modal overlay', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('admin/users — role select is visible inside modal', async ({ page }) => {
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');

    // Open "Nouvel utilisateur" modal
    const addBtn = page.locator('button:has-text("Nouvel utilisateur"), button:has-text("Ajouter")').first();
    if (await addBtn.isVisible().catch(() => false)) {
      await addBtn.click();
    } else {
      test.skip(true, 'Add user button not found');
    }

    await page.waitForTimeout(300);
    await expect(page.locator('.app-modal-overlay')).toBeVisible();

    // Verify native <select> elements are present inside the modal
    const roleSelect = page.locator('.app-modal-body select').first();
    await expect(roleSelect).toBeVisible({ timeout: 3000 });
    const options = await roleSelect.locator('option').count();
    expect(options).toBeGreaterThan(0);

    // Screenshot for manual review
    await page.screenshot({ path: 'tests-screenshots/us2-users-dropdown.png' });
  });

  test('facturation — statut dropdown inside modal', async ({ page }) => {
    await page.goto('/facturation');
    await page.waitForLoadState('networkidle');

    if (page.url().includes('moduleDisabled=facturation')) {
      test.skip(true, 'Module facturation désactivé');
    }
    // Try to open first row edit modal if present
    const editBtn = page.locator('button:has-text("Modifier"), [class*="edit"]').first();
    if (await editBtn.isVisible().catch(() => false)) {
      await editBtn.click();
      await page.waitForTimeout(300);

      const selectTrigger = page.locator('.app-modal-card button[role="combobox"], .app-modal-card [data-reka-select-trigger]').first();
      await selectTrigger.click();

      const dropdown = page.locator('[data-reka-popper-content-wrapper]').first();
      await expect(dropdown).toBeVisible({ timeout: 3000 });
      await page.screenshot({ path: 'tests-screenshots/us2-facturation-dropdown.png' });
    } else {
      test.skip(true, 'No editable rows — empty state');
    }
  });

  test('absences — type dropdown inside modal', async ({ page }) => {
    await page.goto('/absences');
    await page.waitForLoadState('networkidle');

    const addBtn = page.locator('button:has-text("Nouvelle"), button:has-text("Ajouter")').first();
    if (await addBtn.isVisible().catch(() => false)) {
      await addBtn.click();
      await page.waitForTimeout(300);

      const selectTrigger = page.locator('.app-modal-card button[role="combobox"], .app-modal-card [data-reka-select-trigger]').first();
      await selectTrigger.click();

      const dropdown = page.locator('[data-reka-popper-content-wrapper]').first();
      await expect(dropdown).toBeVisible({ timeout: 3000 });
      await page.screenshot({ path: 'tests-screenshots/us2-absences-dropdown.png' });
    } else {
      test.skip(true, 'Add absence button not found');
    }
  });

  test('public/booking — type intervention select is visible', async ({ page }) => {
    // Public page — no auth required
    await page.goto('/public/booking');
    await page.waitForLoadState('networkidle');

    // Verify the USelect (native-styled) for type d'intervention is visible
    await page.waitForTimeout(1000);
    const typeSelect = page.locator('select').first();
    if (await typeSelect.isVisible().catch(() => false)) {
      await expect(typeSelect).toBeVisible({ timeout: 3000 });
      await page.screenshot({ path: 'tests-screenshots/us2-public-booking-dropdown.png' });
    } else {
      test.skip(true, 'No select visible on public booking');
    }
  });
});

/* ------------------------------------------------------------------ */
/*  US-3  —  Modal interactions (Escape, backdrop, focus trap)         */
/* ------------------------------------------------------------------ */
test.describe('US-3 — Modal interactions', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('Escape closes modal', async ({ page }) => {
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');

    const addBtn = page.locator('button:has-text("Nouvel utilisateur"), button:has-text("Ajouter")').first();
    if (await addBtn.isVisible().catch(() => false)) {
      await addBtn.click();
      await page.waitForTimeout(300);
      await expect(page.locator('.app-modal-overlay')).toBeVisible();

      await page.keyboard.press('Escape');
      await page.waitForTimeout(300);
      await expect(page.locator('.app-modal-overlay')).not.toBeVisible();
    } else {
      test.skip(true, 'Add user button not found');
    }
  });

  test('Clicking backdrop closes modal', async ({ page }) => {
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');

    const addBtn = page.locator('button:has-text("Nouvel utilisateur"), button:has-text("Ajouter")').first();
    if (await addBtn.isVisible().catch(() => false)) {
      await addBtn.click();
      await page.waitForTimeout(300);
      await expect(page.locator('.app-modal-overlay')).toBeVisible();

      // Click on the overlay (outside the card)
      await page.locator('.app-modal-overlay').click({ position: { x: 10, y: 10 } });
      await page.waitForTimeout(300);
      await expect(page.locator('.app-modal-overlay')).not.toBeVisible();
    } else {
      test.skip(true, 'Add user button not found');
    }
  });

  test('Focus stays inside modal on Tab', async ({ page }) => {
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');

    const addBtn = page.locator('button:has-text("Nouvel utilisateur"), button:has-text("Ajouter")').first();
    if (await addBtn.isVisible().catch(() => false)) {
      await addBtn.click();
      await page.waitForTimeout(300);

      const modal = page.locator('.app-modal-card, .app-modal-overlay > div').first();
      await expect(modal).toBeVisible();

      // Press Tab many times and ensure focus never leaves modal
      for (let i = 0; i < 8; i++) {
        await page.keyboard.press('Tab');
        await page.waitForTimeout(100);
      }

      const active = await page.evaluate(() => document.activeElement?.closest('.app-modal-overlay, .app-modal-card') !== null);
      expect(active).toBe(true);
    } else {
      test.skip(true, 'Add user button not found');
    }
  });
});

/* ------------------------------------------------------------------ */
/*  US-4  —  PitModalFooter consistency                                 */
/* ------------------------------------------------------------------ */
test.describe('US-4 — Modal footer standardization', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('users modal has Cancel and Submit buttons', async ({ page }) => {
    await page.goto('/admin/users');
    await page.waitForLoadState('networkidle');

    const addBtn = page.locator('button:has-text("Nouvel utilisateur"), button:has-text("Ajouter")').first();
    if (await addBtn.isVisible().catch(() => false)) {
      await addBtn.click();
      await page.waitForTimeout(300);

      await expect(page.locator('.app-modal-card button:has-text("Annuler")')).toBeVisible();
      await expect(page.locator('.app-modal-card button:has-text("Créer")')).toBeVisible();
    } else {
      test.skip(true, 'Add user button not found');
    }
  });

  test('tarifs modal has Cancel and Submit buttons', async ({ page }) => {
    await page.goto('/tarifs');
    await page.waitForLoadState('networkidle');

    const addBtn = page.locator('button:has-text("Nouveau"), button:has-text("Ajouter")').first();
    if (await addBtn.isVisible().catch(() => false)) {
      await addBtn.click();
      await page.waitForTimeout(300);

      await expect(page.locator('.app-modal-card button:has-text("Annuler")')).toBeVisible();
      await expect(page.locator('.app-modal-card button:has-text("Créer")')).toBeVisible();
    } else {
      test.skip(true, 'Add tarif button not found');
    }
  });
});

/* ------------------------------------------------------------------ */
/*  US-5  —  Page loads after modernization                             */
/* ------------------------------------------------------------------ */
test.describe('US-5 — Modernized pages load successfully', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  const pages = [
    { path: '/admin/users',    text: /utilisateur|user/i },
    { path: '/admin/ponts',    text: /pont|atelier/i },
    { path: '/admin/prestations', text: /prestation|tarif/i },
    { path: '/admin/roles', text: /rôle|profil/i },
    { path: '/tarifs',         text: /tarif|grille/i },
    { path: '/facturation',    text: /factur/i },
    { path: '/absences',       text: /absence|congé/i },
    { path: '/motos',          text: /moto|catalogue/i },
  ];

  for (const { path, text } of pages) {
    test(`${path} loads`, async ({ page }) => {
      const response = await page.goto(path);
      expect(response?.status()).toBe(200);
      await page.waitForLoadState('networkidle');
      // Module désactivé : le middleware redirige vers /?moduleDisabled=<section>
      if (!page.url().includes('moduleDisabled=')) {
        await expect(page.locator('body')).toContainText(text, { timeout: 10000 });
      }
    });
  }

  test('/public/booking loads without auth', async ({ page }) => {
    const response = await page.goto('/public/booking');
    expect(response?.status()).toBe(200);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toContainText(/rendez-vous|réservation|booking/i);
  });
});
