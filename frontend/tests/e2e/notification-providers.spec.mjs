import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers.mjs';

test.describe('LOT 11 — Multi-provider SMS/Email', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  // ─── Admin navigation ───

  test('Admin hub shows Notifications card', async ({ page }) => {
    await page.goto('/admin', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    const card = page.locator('.admin-card-label', { hasText: 'Notifications' });
    await expect(card).toBeVisible({ timeout: 10000 });
  });

  test('Admin Notifications card navigates to providers page', async ({ page }) => {
    await page.goto('/admin', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    await page.locator('.admin-card', { hasText: 'Notifications' }).click();
    await page.waitForURL('**/admin/notifications/providers');
    await expect(page).toHaveURL(/\/admin\/notifications\/providers/);
  });

  // ─── Providers page ───

  test('Providers page loads correctly', async ({ page }) => {
    await page.goto('/admin/notifications/providers', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    // Page should load without error (200 status)
    await expect(page.locator('body')).toContainText(/provider|notification|SMS|Email/i, { timeout: 10000 });
  });

  test('Provider action buttons open their modals', async ({ page }) => {
    await page.goto('/admin/notifications/providers', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    await page.getByRole('button', { name: /ajouter un provider/i }).click();
    await expect(page.locator('.app-modal-overlay')).toBeVisible();
    await expect(page.locator('text=Canal')).toBeVisible();
    await page.getByLabel('Fermer la modale').click();

    await page.locator('.provider-row .btn', { hasText: '⚙️' }).first().click();
    await expect(page.locator('.app-modal-overlay')).toBeVisible();
    await expect(page.locator('text=Modifier le provider')).toBeVisible();
    await page.getByLabel('Fermer la modale').click();

    await page.locator('.provider-row .btn', { hasText: '🧪' }).first().click();
    await expect(page.locator('text=Envoyer le test')).toBeVisible();
  });

  test('Templates tab shows default notification templates', async ({ page }) => {
    await page.goto('/admin/notifications/providers', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    await page.getByRole('button', { name: /templates/i }).click();
    await expect(page.locator('text=Aucun template configuré')).toHaveCount(0);
    await expect(page.locator('table')).toContainText('rdv_confirmation');
    await expect(page.locator('table')).toContainText('travaux_termines');
  });

  // ─── API endpoints ───

  test('API: list providers returns array', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/admin/notification-providers', {
        credentials: 'include',
      });
      return { status: res.status, body: await res.json() };
    });

    expect(response.status).toBe(200);
    expect(Array.isArray(response.body)).toBe(true);
  });

  test('API: list templates returns array', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/admin/notification-templates', {
        credentials: 'include',
      });
      return { status: res.status, body: await res.json() };
    });

    expect(response.status).toBe(200);
    expect(Array.isArray(response.body)).toBe(true);
  });

  test('API: list logs returns paginated result', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/admin/notification-logs', {
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

  // ─── Webhooks (public) ───

  test('Webhook: twilio endpoint accepts POST', async ({ page }) => {
    const response = await page.evaluate(async () => {
      const params = new URLSearchParams();
      params.append('MessageSid', 'SM_TEST_123');
      params.append('MessageStatus', 'delivered');
      const res = await fetch('/api/webhooks/notifications/twilio', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString(),
      });
      return { status: res.status, body: await res.json() };
    });

    expect(response.status).toBe(200);
    expect(response.body.received).toBe(true);
  });

  test('Webhook: ovh endpoint accepts POST', async ({ page }) => {
    const response = await page.evaluate(async () => {
      const res = await fetch('/api/webhooks/notifications/ovh', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: 'test-123', deliveryReceipt: 1 }),
      });
      return { status: res.status, body: await res.json() };
    });

    expect(response.status).toBe(200);
    expect(response.body.received).toBe(true);
  });

  test('Webhook: mailgun endpoint accepts POST', async ({ page }) => {
    const response = await page.evaluate(async () => {
      const res = await fetch('/api/webhooks/notifications/mailgun', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          signature: { timestamp: Date.now(), token: 'test', signature: 'test' },
          'event-data': { event: 'delivered', message: { headers: { 'message-id': 'test-msg' } } },
        }),
      });
      return { status: res.status, body: await res.json() };
    });

    expect(response.status).toBe(200);
    expect(response.body.received).toBe(true);
  });

  test('Webhook: unknown provider returns 400', async ({ page }) => {
    const response = await page.evaluate(async () => {
      const res = await fetch('/api/webhooks/notifications/unknown', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}',
      });
      return { status: res.status };
    });

    expect(response.status).toBe(400);
  });

  // ─── Create provider (CRUD) ───

  test('API: create or update provider', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle');

    const response = await page.evaluate(async () => {
      const res = await fetch('/api/admin/notification-providers', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          channel: 'sms',
          provider: 'twilio',
          isPrimary: true,
          priority: 1,
          config: {
            account_sid: 'AC_TEST_' + Date.now(),
            auth_token: 'test_token',
            from: '+33600000000',
          },
        }),
      });
      return { status: res.status, body: await res.json() };
    });

    // 201 if new, 409 if already exists — both are valid
    expect([201, 409]).toContain(response.status);
    if (response.status === 201) {
      expect(response.body).toHaveProperty('id');
    }
  });
});
