import { test, expect } from '@playwright/test';

test('admin SMTP: form loads with current config', async ({ page }) => {
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));

  await page.goto('/');
  await page.fill('#login-user', 'admin');
  await page.fill('#login-pass', 'Admin123!');
  await page.click('button:has-text("Connexion")');
  await page.waitForSelector('#s-dashboard', { state: 'visible', timeout: 10000 });

  await page.click('#nav-admin');
  await page.waitForSelector('#s-admin', { state: 'visible', timeout: 5000 });
  await page.click('#admin-tab-smtp');
  await page.waitForSelector('#admin-panel-smtp', { state: 'visible', timeout: 5000 });

  // Wait for SMTP config to load
  await page.waitForTimeout(1000);

  // Check form has values populated
  const host = await page.inputValue('#smtp-host');
  const port = await page.inputValue('#smtp-port');
  const from = await page.inputValue('#smtp-from');
  console.log('SMTP host:', host, 'port:', port, 'from:', from);

  expect(host).toBe('mailhog');
  expect(port).toBe('1025');
  expect(from).toContain('@');

  expect(errors).toEqual([]);
});

test('admin SMTP: save and test buttons work', async ({ page }) => {
  const errors = [];
  const requests = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('request', r => {
    if (r.url().includes('/api/admin/smtp')) requests.push(r.method() + ' ' + r.url());
  });

  await page.goto('/');
  await page.fill('#login-user', 'admin');
  await page.fill('#login-pass', 'Admin123!');
  await page.click('button:has-text("Connexion")');
  await page.waitForSelector('#s-dashboard', { state: 'visible', timeout: 10000 });

  await page.click('#nav-admin');
  await page.waitForSelector('#s-admin', { state: 'visible', timeout: 5000 });
  await page.click('#admin-tab-smtp');
  await page.waitForSelector('#admin-panel-smtp', { state: 'visible', timeout: 5000 });
  await page.waitForTimeout(500);

  // Click save
  await page.click('#smtp-config-form button[type="submit"]');
  await page.waitForTimeout(500);

  // Click test
  await page.click('button:has-text("Tester la connexion")');
  await page.waitForTimeout(1000);

  console.log('SMTP requests:', requests);
  // Should have GET (load), PUT (save), POST (test)
  expect(requests.some(r => r.startsWith('GET'))).toBeTruthy();
  expect(requests.some(r => r.startsWith('PUT'))).toBeTruthy();
  expect(requests.some(r => r.includes('/test'))).toBeTruthy();

  expect(errors).toEqual([]);
});

test('admin templates: list and create template', async ({ page }) => {
  const errors = [];
  page.on('pageerror', e => errors.push(e.message));

  await page.goto('/');
  await page.fill('#login-user', 'admin');
  await page.fill('#login-pass', 'Admin123!');
  await page.click('button:has-text("Connexion")');
  await page.waitForSelector('#s-dashboard', { state: 'visible', timeout: 10000 });

  await page.click('#nav-admin');
  await page.waitForSelector('#s-admin', { state: 'visible', timeout: 5000 });
  await page.click('#admin-tab-templates');
  await page.waitForSelector('#admin-panel-templates', { state: 'visible', timeout: 5000 });
  await page.waitForTimeout(500);

  // List should load
  await expect(page.locator('#email-templates-list')).toBeVisible();

  // Click create template  
  await page.click('button:has-text("Nouveau template")');
  await page.waitForTimeout(500);

  // Modal should appear with form fields
  await expect(page.locator('#tpl-code')).toBeVisible();
  await expect(page.locator('#tpl-nom')).toBeVisible();
  await expect(page.locator('#tpl-sujet')).toBeVisible();
  await expect(page.locator('#tpl-corps-html')).toBeVisible();

  // Fill and submit
  await page.fill('#tpl-code', 'test_rappel');
  await page.fill('#tpl-nom', 'Rappel test');
  await page.fill('#tpl-sujet', 'Rappel RDV {date_rdv}');
  await page.fill('#tpl-corps-html', '<p>Bonjour {client_prenom}</p>');

  // Submit the form in the modal
  await page.locator('#modal-body button[type="submit"]').click();
  await page.waitForTimeout(1000);

  expect(errors).toEqual([]);
});
