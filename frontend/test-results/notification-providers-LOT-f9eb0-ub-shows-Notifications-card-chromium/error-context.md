# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: notification-providers.spec.mjs >> LOT 11 — Multi-provider SMS/Email >> Admin hub shows Notifications card
- Location: tests/e2e/notification-providers.spec.mjs:11:3

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('.admin-card-label').filter({ hasText: 'Notifications' })
Expected: visible
Timeout: 10000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" with timeout 10000ms
  - waiting for locator('.admin-card-label').filter({ hasText: 'Notifications' })

```

# Page snapshot

```yaml
- generic [ref=e4]:
  - navigation [ref=e5]:
    - button "Seclin" [ref=e6] [cursor=pointer]:
      - img "Seclin" [ref=e7]
    - link "📊" [ref=e8] [cursor=pointer]:
      - /url: /
      - generic [ref=e9]: 📊
    - link "📅" [ref=e10] [cursor=pointer]:
      - /url: /rdv
      - generic [ref=e11]: 📅
    - link "🗓" [ref=e12] [cursor=pointer]:
      - /url: /planning
      - generic [ref=e13]: 🗓
    - link "🔧" [ref=e14] [cursor=pointer]:
      - /url: /workshop
      - generic [ref=e15]: 🔧
    - link "👁" [ref=e16] [cursor=pointer]:
      - /url: /suivi
      - generic [ref=e17]: 👁
    - link "👥" [ref=e18] [cursor=pointer]:
      - /url: /clients
      - generic [ref=e19]: 👥
    - link "📋" [ref=e20] [cursor=pointer]:
      - /url: /ordres
      - generic [ref=e21]: 📋
    - link "📝" [ref=e22] [cursor=pointer]:
      - /url: /devis
      - generic [ref=e23]: 📝
    - link "🏷️" [ref=e24] [cursor=pointer]:
      - /url: /vo
      - generic [ref=e25]: 🏷️
    - link "⚙" [ref=e26] [cursor=pointer]:
      - /url: /admin
      - generic [ref=e27]: ⚙
    - generic [ref=e29] [cursor=pointer]: U
    - button "⏻" [ref=e30] [cursor=pointer]:
      - generic [ref=e31]: ⏻
  - generic [ref=e32]:
    - banner [ref=e33]:
      - generic [ref=e34]:
        - img "Seclin" [ref=e35]
        - generic [ref=e36]: Seclin
      - generic [ref=e37]: Administration
      - generic [ref=e39]: LIVE
      - link "+ Nouveau RDV" [ref=e40] [cursor=pointer]:
        - /url: /rdv/new
    - main [ref=e41]:
      - generic [ref=e42]:
        - generic [ref=e44]: Administration
        - generic [ref=e45]:
          - generic [ref=e46] [cursor=pointer]:
            - generic [ref=e47]: 👥
            - generic [ref=e48]: Utilisateurs
            - generic [ref=e49]: Gérer les comptes et rôles
          - generic [ref=e50] [cursor=pointer]:
            - generic [ref=e51]: ⚙️
            - generic [ref=e52]: Configuration
            - generic [ref=e53]: Paramètres de l'atelier
          - generic [ref=e54] [cursor=pointer]:
            - generic [ref=e55]: 📅
            - generic [ref=e56]: Absences
            - generic [ref=e57]: Gérer les congés mécaniciens
          - generic [ref=e58] [cursor=pointer]:
            - generic [ref=e59]: 🔧
            - generic [ref=e60]: Ponts
            - generic [ref=e61]: Gérer les postes de travail
          - generic [ref=e62] [cursor=pointer]:
            - generic [ref=e63]: 📋
            - generic [ref=e64]: Prestations
            - generic [ref=e65]: Tarifs et grilles
          - generic [ref=e66] [cursor=pointer]:
            - generic [ref=e67]: 🛡️
            - generic [ref=e68]: Rôles
            - generic [ref=e69]: CRUD des rôles et permissions
          - generic [ref=e70] [cursor=pointer]:
            - generic [ref=e71]: 🔍
            - generic [ref=e72]: Audit
            - generic [ref=e73]: Journal des actions
```

# Test source

```ts
  1   | import { test, expect } from '@playwright/test';
  2   | import { loginAsAdmin } from './helpers.mjs';
  3   | 
  4   | test.describe('LOT 11 — Multi-provider SMS/Email', () => {
  5   |   test.beforeEach(async ({ page }) => {
  6   |     await loginAsAdmin(page);
  7   |   });
  8   | 
  9   |   // ─── Admin navigation ───
  10  | 
  11  |   test('Admin hub shows Notifications card', async ({ page }) => {
  12  |     await page.goto('/admin');
  13  |     await page.waitForLoadState('networkidle');
  14  | 
  15  |     const card = page.locator('.admin-card-label', { hasText: 'Notifications' });
> 16  |     await expect(card).toBeVisible({ timeout: 10000 });
      |                        ^ Error: expect(locator).toBeVisible() failed
  17  |   });
  18  | 
  19  |   test('Admin Notifications card navigates to providers page', async ({ page }) => {
  20  |     await page.goto('/admin');
  21  |     await page.waitForLoadState('networkidle');
  22  | 
  23  |     await page.locator('.admin-card', { hasText: 'Notifications' }).click();
  24  |     await page.waitForURL('**/admin/notifications/providers');
  25  |     await expect(page).toHaveURL(/\/admin\/notifications\/providers/);
  26  |   });
  27  | 
  28  |   // ─── Providers page ───
  29  | 
  30  |   test('Providers page loads correctly', async ({ page }) => {
  31  |     await page.goto('/admin/notifications/providers');
  32  |     await page.waitForLoadState('networkidle');
  33  | 
  34  |     // Page should load without error (200 status)
  35  |     await expect(page.locator('body')).toContainText(/provider|notification|SMS|Email/i, { timeout: 10000 });
  36  |   });
  37  | 
  38  |   // ─── API endpoints ───
  39  | 
  40  |   test('API: list providers returns array', async ({ page }) => {
  41  |     await page.goto('/');
  42  |     await page.waitForLoadState('networkidle');
  43  | 
  44  |     const response = await page.evaluate(async () => {
  45  |       const res = await fetch('/api/admin/notification-providers', {
  46  |         credentials: 'include',
  47  |       });
  48  |       return { status: res.status, body: await res.json() };
  49  |     });
  50  | 
  51  |     expect(response.status).toBe(200);
  52  |     expect(Array.isArray(response.body)).toBe(true);
  53  |   });
  54  | 
  55  |   test('API: list templates returns array', async ({ page }) => {
  56  |     await page.goto('/');
  57  |     await page.waitForLoadState('networkidle');
  58  | 
  59  |     const response = await page.evaluate(async () => {
  60  |       const res = await fetch('/api/admin/notification-templates', {
  61  |         credentials: 'include',
  62  |       });
  63  |       return { status: res.status, body: await res.json() };
  64  |     });
  65  | 
  66  |     expect(response.status).toBe(200);
  67  |     expect(Array.isArray(response.body)).toBe(true);
  68  |   });
  69  | 
  70  |   test('API: list logs returns paginated result', async ({ page }) => {
  71  |     await page.goto('/');
  72  |     await page.waitForLoadState('networkidle');
  73  | 
  74  |     const response = await page.evaluate(async () => {
  75  |       const res = await fetch('/api/admin/notification-logs', {
  76  |         credentials: 'include',
  77  |       });
  78  |       return { status: res.status, body: await res.json() };
  79  |     });
  80  | 
  81  |     expect(response.status).toBe(200);
  82  |     expect(response.body).toHaveProperty('items');
  83  |     expect(response.body).toHaveProperty('page');
  84  |     expect(response.body).toHaveProperty('limit');
  85  |     expect(Array.isArray(response.body.items)).toBe(true);
  86  |   });
  87  | 
  88  |   // ─── Webhooks (public) ───
  89  | 
  90  |   test('Webhook: twilio endpoint accepts POST', async ({ page }) => {
  91  |     const response = await page.evaluate(async () => {
  92  |       const params = new URLSearchParams();
  93  |       params.append('MessageSid', 'SM_TEST_123');
  94  |       params.append('MessageStatus', 'delivered');
  95  |       const res = await fetch('/api/webhooks/notifications/twilio', {
  96  |         method: 'POST',
  97  |         headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  98  |         body: params.toString(),
  99  |       });
  100 |       return { status: res.status, body: await res.json() };
  101 |     });
  102 | 
  103 |     expect(response.status).toBe(200);
  104 |     expect(response.body.received).toBe(true);
  105 |   });
  106 | 
  107 |   test('Webhook: ovh endpoint accepts POST', async ({ page }) => {
  108 |     const response = await page.evaluate(async () => {
  109 |       const res = await fetch('/api/webhooks/notifications/ovh', {
  110 |         method: 'POST',
  111 |         headers: { 'Content-Type': 'application/json' },
  112 |         body: JSON.stringify({ id: 'test-123', deliveryReceipt: 1 }),
  113 |       });
  114 |       return { status: res.status, body: await res.json() };
  115 |     });
  116 | 
```