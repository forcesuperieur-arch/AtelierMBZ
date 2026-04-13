import { test, expect } from '@playwright/test';

async function login(page) {
  await page.goto('/');
  await page.fill('#login-user', 'admin');
  await page.fill('#login-pass', 'Admin123!');
  await page.click('button:has-text("Connexion")');
  await page.waitForSelector('#s-dashboard', { state: 'visible', timeout: 10000 });
}

test('wizard: Mon Atelier tab is visible and loads data', async ({ page }) => {
  await login(page);
  // Navigate to Admin
  await page.click('#nav-admin');
  await page.waitForSelector('#admin-tab-assistant', { timeout: 5000 });

  // The wizard tab should be visible
  const tab = page.locator('#admin-tab-assistant');
  await expect(tab).toBeVisible();
  await expect(tab).toContainText('Mon Atelier');

  // Click it
  await tab.click();
  await page.waitForSelector('#wizard-step-1', { timeout: 3000 });

  // Wizard step 1 should be visible with pre-filled data
  await expect(page.locator('#wiz-nom')).toBeVisible();
  // The name should be pre-filled from the DB
  const nom = await page.locator('#wiz-nom').inputValue();
  console.log('Wizard loaded nom:', nom);
  expect(nom.length).toBeGreaterThan(0);
});

test('wizard: navigate through all 4 steps', async ({ page }) => {
  await login(page);
  await page.click('#nav-admin');
  await page.waitForSelector('#admin-tab-assistant', { timeout: 5000 });
  await page.click('#admin-tab-assistant');
  await page.waitForSelector('#wizard-step-1', { timeout: 3000 });

  // Step 1 → 2
  await page.click('button:has-text("Suivant →")');
  await expect(page.locator('#wizard-step-2')).toBeVisible();
  await expect(page.locator('#wiz-logo-file')).toBeAttached();

  // Step 2 → 3
  await page.click('#wizard-step-2 button:has-text("Suivant →")');
  await expect(page.locator('#wizard-step-3')).toBeVisible();
  await expect(page.locator('#wiz-email-provider')).toBeVisible();

  // Select OVH provider
  await page.selectOption('#wiz-email-provider', 'ovh');
  await expect(page.locator('#wiz-email-fields')).toBeVisible();
  await expect(page.locator('#wiz-provider-help')).toContainText('OVH');

  // Step 3 → 4
  await page.click('#wizard-step-3 button:has-text("Suivant →")');
  await expect(page.locator('#wizard-step-4')).toBeVisible();
  await expect(page.locator('#wiz-summary')).toBeVisible();

  // Summary should contain the atelier name
  const summary = await page.locator('#wiz-summary').textContent();
  console.log('Wizard summary content:', summary.substring(0, 200));
  expect(summary).toContain('Nom');
});

test('wizard: provider presets fill correct SMTP fields', async ({ page }) => {
  await login(page);
  await page.click('#nav-admin');
  await page.click('#admin-tab-assistant');
  await page.waitForSelector('#wizard-step-1', { timeout: 3000 });

  // Go to step 3
  await page.click('button:has-text("Suivant →")');
  await page.click('#wizard-step-2 button:has-text("Suivant →")');
  await page.waitForSelector('#wizard-step-3', { timeout: 3000 });

  // Select OVH
  await page.selectOption('#wiz-email-provider', 'ovh');
  const host = await page.locator('#wiz-smtp-host').inputValue();
  const port = await page.locator('#wiz-smtp-port').inputValue();
  console.log('OVH preset: host=' + host + ' port=' + port);
  expect(host).toBe('ssl0.ovh.net');
  expect(port).toBe('465');

  // Select Gmail
  await page.selectOption('#wiz-email-provider', 'gmail');
  const host2 = await page.locator('#wiz-smtp-host').inputValue();
  const port2 = await page.locator('#wiz-smtp-port').inputValue();
  console.log('Gmail preset: host=' + host2 + ' port=' + port2);
  expect(host2).toBe('smtp.gmail.com');
  expect(port2).toBe('587');
});
