import { test, expect } from '@playwright/test';

test.describe('New features smoke tests', () => {

  test('SPA loads all JS modules without console errors', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    // Login screen should appear
    await expect(page.locator('#login-screen')).toBeVisible();

    // Check no JS errors
    expect(errors).toEqual([]);
  });

  test('login and navigate to devis section', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await page.goto('/');
    await page.fill('#login-user', 'admin');
    await page.fill('#login-pass', 'Admin123!');
    await page.click('button:has-text("Connexion")');

    // Wait for app to load
    await page.waitForSelector('#s-dashboard', { state: 'visible', timeout: 10000 });

    // Navigate to devis section
    await page.click('#nav-devis');
    await page.waitForSelector('#s-devis', { state: 'visible', timeout: 5000 });

    // Devis section should have the header
    await expect(page.locator('#s-devis .page-title')).toContainText('Devis');

    // Table should be visible
    await expect(page.locator('#devis-table-body')).toBeVisible();

    expect(errors).toEqual([]);
  });

  test('login and navigate to admin SMTP tab', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await page.goto('/');
    await page.fill('#login-user', 'admin');
    await page.fill('#login-pass', 'Admin123!');
    await page.click('button:has-text("Connexion")');

    await page.waitForSelector('#s-dashboard', { state: 'visible', timeout: 10000 });

    // Navigate to admin
    await page.click('#nav-admin');
    await page.waitForSelector('#s-admin', { state: 'visible', timeout: 5000 });

    // Click SMTP tab
    await page.click('#admin-tab-smtp');
    await page.waitForSelector('#admin-panel-smtp', { state: 'visible', timeout: 5000 });

    // SMTP form should load
    await expect(page.locator('#smtp-host')).toBeVisible();
    await expect(page.locator('#smtp-port')).toBeVisible();

    expect(errors).toEqual([]);
  });

  test('login and navigate to admin templates tab', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await page.goto('/');
    await page.fill('#login-user', 'admin');
    await page.fill('#login-pass', 'Admin123!');
    await page.click('button:has-text("Connexion")');

    await page.waitForSelector('#s-dashboard', { state: 'visible', timeout: 10000 });

    await page.click('#nav-admin');
    await page.waitForSelector('#s-admin', { state: 'visible', timeout: 5000 });

    // Click templates tab
    await page.click('#admin-tab-templates');
    await page.waitForSelector('#admin-panel-templates', { state: 'visible', timeout: 5000 });

    await expect(page.locator('#email-templates-list')).toBeVisible();

    expect(errors).toEqual([]);
  });

  test('login and navigate to admin backup tab', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));

    await page.goto('/');
    await page.fill('#login-user', 'admin');
    await page.fill('#login-pass', 'Admin123!');
    await page.click('button:has-text("Connexion")');

    await page.waitForSelector('#s-dashboard', { state: 'visible', timeout: 10000 });

    await page.click('#nav-admin');
    await page.waitForSelector('#s-admin', { state: 'visible', timeout: 5000 });

    // Click backup tab
    await page.click('#admin-tab-backup');
    await page.waitForSelector('#admin-panel-backup', { state: 'visible', timeout: 5000 });

    await expect(page.locator('#backups-list')).toBeVisible();

    expect(errors).toEqual([]);
  });

  test('suivi public page loads with valid token', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (err) => errors.push(err.message));

    // Get a valid token from the API first
    const resp = await page.request.get('/api/suivi/j3kTfbIXGvynjs8jV-XY6-YNhAcuglhhvHPwXeN7YYA');
    expect(resp.ok()).toBeTruthy();

    await page.goto('/suivi.html?token=j3kTfbIXGvynjs8jV-XY6-YNhAcuglhhvHPwXeN7YYA');
    await page.waitForLoadState('networkidle');

    // Content should show (not error)
    await expect(page.locator('#content')).toBeVisible({ timeout: 10000 });

    // Status badge should show
    await expect(page.locator('#statut-badge')).toBeVisible();

    expect(errors).toEqual([]);
  });

  test('suivi public page shows error for invalid token', async ({ page }) => {
    await page.goto('/suivi.html?token=invalid_token_test');
    await page.waitForLoadState('networkidle');

    // Should show error message
    await expect(page.locator('#error')).toBeVisible({ timeout: 10000 });
  });
});
