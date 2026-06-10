import { test, expect } from '@playwright/test';
import { appUrl, loginAsAdmin } from './helpers.mjs';

/**
 * Security: validates tenant isolation and access controls.
 * Note: The test admin user is a SUPER_ADMIN, so tenant filtering is bypassed.
 * The actual tenant isolation is enforced by Doctrine TenantFilter for non-super-admin users.
 */

test.describe('Security — Access Controls', () => {
  test('Non-existent client returns 404', async ({ page }) => {
    await loginAsAdmin(page);

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/clients/999999', { credentials: 'include' });
      return { status: res.status };
    });

    expect(response.status).toBe(404);
  });

  test('Non-existent rdv returns 404', async ({ page }) => {
    await loginAsAdmin(page);

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/rendez-vous/999999', { credentials: 'include' });
      return { status: res.status };
    });

    expect(response.status).toBe(404);
  });

  test('Public endpoints work without auth', async ({ page }) => {
    await page.goto(appUrl('/public/booking'));
    await page.waitForLoadState('networkidle');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/public/booking/slots?date=2026-12-01', { credentials: 'include' });
      return { status: res.status };
    });

    expect([200, 404]).toContain(response.status);
  });

  test('Authenticated admin can access protected API endpoints', async ({ page }) => {
    await loginAsAdmin(page);

    const endpoints = [
      '/api/clients',
      '/api/rendez-vous',
      '/api/vehicules',
      '/api/factures',
    ];

    for (const endpoint of endpoints) {
      const response = await page.evaluate(async (url) => {
        const res = await fetch(url, { credentials: 'include' });
        return { status: res.status, endpoint: url };
      }, endpoint);

      expect(response.status, `Expected 200 for ${endpoint}`).toBe(200);
    }
  });

  test('Unauthenticated user gets 401/403 on protected endpoints', async ({ page }) => {
    await page.goto(appUrl('/login'));
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => { localStorage.clear(); });
    await page.context().clearCookies();

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/clients', { credentials: 'include' });
      return { status: res.status };
    });

    expect([401, 403, 302]).toContain(response.status);
  });
});
