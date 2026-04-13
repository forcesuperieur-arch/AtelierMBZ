import { test, expect } from '@playwright/test';

test.describe('Public UI smoke', () => {
  test('shows the SPA login screen', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('#login-screen')).toBeVisible();
    await expect(page.locator('#login-user')).toBeVisible();
    await expect(page.locator('#login-pass')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Connexion' })).toBeVisible();
  });

  test('serves the public rendez-vous page', async ({ page }) => {
    await page.goto('/rendez-vous.html');

    await expect(page.locator('body')).toContainText(/rendez|atelier|prestation/i);
  });
});
