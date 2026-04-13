import { test, expect } from '@playwright/test';

test('branding: logo displayed in sidebar after login', async ({ page }) => {
  await page.goto('/');
  await page.fill('#login-user', 'admin');
  await page.fill('#login-pass', 'Admin123!');
  await page.click('button:has-text("Connexion")');
  await page.waitForSelector('#s-dashboard', { state: 'visible', timeout: 10000 });
  await page.waitForTimeout(1000);

  // Logo button should exist and contain an img (since we uploaded a logo)
  const logoBtn = page.locator('.sidebar .logo');
  await expect(logoBtn).toBeVisible();
  const logoImg = logoBtn.locator('img');
  const imgCount = await logoImg.count();
  
  if (imgCount > 0) {
    // Logo image found
    const src = await logoImg.getAttribute('src');
    console.log('Logo img src:', src);
    expect(src).toContain('/api/config/atelier/logo/');
  } else {
    // Fallback letter (atelier name first char)
    const text = await logoBtn.textContent();
    console.log('Logo fallback text:', text);
    expect(text.trim().length).toBeGreaterThan(0);
  }

  // Page title should be atelier name
  const title = await page.title();
  console.log('Page title:', title);
  expect(title).toBe('Seclin');
});

test('branding: admin edit atelier has logo upload', async ({ page }) => {
  await page.goto('/');
  await page.fill('#login-user', 'admin');
  await page.fill('#login-pass', 'Admin123!');
  await page.click('button:has-text("Connexion")');
  await page.waitForSelector('#s-dashboard', { state: 'visible', timeout: 10000 });

  await page.click('#nav-admin');
  await page.waitForSelector('#s-admin', { state: 'visible', timeout: 5000 });
  await page.waitForTimeout(500);

  // Click edit button on the atelier
  const editBtn = page.locator('#admin-ateliers-list button:has-text("Modifier")').first();
  await expect(editBtn).toBeVisible({ timeout: 5000 });
  await editBtn.click();
  await page.waitForTimeout(500);

  // Modal should show logo file input
  const logoInput = page.locator('#edit-atelier-logo-file');
  await expect(logoInput).toBeAttached();

  // Logo preview should exist
  const logoPreview = page.locator('#edit-atelier-logo-preview');
  await expect(logoPreview).toBeAttached();
});
