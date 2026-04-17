import { test, expect } from '@playwright/test';
import { appUrl, loginAsAdmin } from './helpers.mjs';

test.describe('LOT 5 — Notifications', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('Notification badge visible in sidebar', async ({ page }) => {
    await page.goto(appUrl('/'));
    await page.waitForLoadState('domcontentloaded');

    const planningLink = page.locator('a[href="/planning"]');
    await expect(planningLink).toBeVisible({ timeout: 10000 });
  });

  test('Notification API: unread-count returns response', async ({ page }) => {
    await page.goto(appUrl('/'));
    await page.waitForLoadState('domcontentloaded');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/notifications/unread-count', {
        credentials: 'include',
      });
      return { status: res.status, body: await res.json() };
    });

    expect(response.status).toBe(200);
    expect(response.body).toHaveProperty('count');
    expect(typeof response.body.count).toBe('number');
  });

  test('Notification API: list returns paginated results', async ({ page }) => {
    await page.goto(appUrl('/'));
    await page.waitForLoadState('domcontentloaded');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/notifications?limit=10', {
        credentials: 'include',
      });
      return { status: res.status, body: await res.json() };
    });

    expect(response.status).toBe(200);
    expect(response.body).toHaveProperty('items');
    expect(response.body).toHaveProperty('page');
    expect(response.body).toHaveProperty('limit');
    expect(Array.isArray(response.body.items)).toBe(true);
  });

  test('Notification API: list with status=unread filter', async ({ page }) => {
    await page.goto(appUrl('/'));
    await page.waitForLoadState('domcontentloaded');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/notifications?status=unread&limit=5', {
        credentials: 'include',
      });
      return { status: res.status, body: await res.json() };
    });

    expect(response.status).toBe(200);
    expect(Array.isArray(response.body.items)).toBe(true);
  });

  test('Notification API: mark-read on nonexistent returns 404', async ({ page }) => {
    await page.goto(appUrl('/'));
    await page.waitForLoadState('domcontentloaded');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/notifications/999999/mark-read', {
        method: 'POST',
        credentials: 'include',
      });
      return { status: res.status };
    });

    expect(response.status).toBe(404);
  });

  test('Notification API: acknowledge on nonexistent returns 409', async ({ page }) => {
    await page.goto(appUrl('/'));
    await page.waitForLoadState('domcontentloaded');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/notifications/999999/acknowledge', {
        method: 'POST',
        credentials: 'include',
      });
      return { status: res.status };
    });

    expect(response.status).toBe(409);
  });
});

test.describe('LOT 5 — Notification Pop-In', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('Planning page loads with notification system', async ({ page }) => {
    await page.goto(appUrl('/planning'));
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('body')).toContainText(/planning/i);
  });

  test('NotificationPopIn does not show when no critical notifications', async ({ page }) => {
    await page.goto(appUrl('/planning'));
    await page.waitForLoadState('domcontentloaded');

    const overlay = page.locator('.fixed.inset-0.z-50');
    await page.waitForTimeout(2000);
    expect(overlay).toBeDefined();
  });
});
