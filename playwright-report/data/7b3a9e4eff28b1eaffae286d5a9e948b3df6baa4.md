# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: notification-providers.spec.mjs >> LOT 11 — Multi-provider SMS/Email >> Admin Notifications card navigates to providers page
- Location: tests/e2e/notification-providers.spec.mjs:19:3

# Error details

```
Test timeout of 60000ms exceeded.
```

```
Error: page.click: Test timeout of 60000ms exceeded.
Call log:
  - waiting for locator('text=Notifications')

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
  15  |     const card = page.locator('text=Notifications');
  16  |     await expect(card).toBeVisible({ timeout: 10000 });
  17  |   });
  18  | 
  19  |   test('Admin Notifications card navigates to providers page', async ({ page }) => {
  20  |     await page.goto('/admin');
  21  |     await page.waitForLoadState('networkidle');
  22  | 
> 23  |     await page.click('text=Notifications');
      |                ^ Error: page.click: Test timeout of 60000ms exceeded.
  24  |     await page.waitForURL('**/admin/notifications/providers');
  25  |     await expect(page).toHaveURL(/\/admin\/notifications\/providers/);
  26  |   });
  27  | 
  28  |   // ─── Providers page ───
  29  | 
  30  |   test('Providers page loads with tabs', async ({ page }) => {
  31  |     await page.goto('/admin/notifications/providers');
  32  |     await page.waitForLoadState('networkidle');
  33  | 
  34  |     // Should have the 3 tabs
  35  |     await expect(page.locator('text=Providers')).toBeVisible({ timeout: 10000 });
  36  |     await expect(page.locator('text=Templates')).toBeVisible();
  37  |     await expect(page.locator('text=Historique')).toBeVisible();
  38  |   });
  39  | 
  40  |   // ─── API endpoints ───
  41  | 
  42  |   test('API: list providers returns array', async ({ page }) => {
  43  |     await page.goto('/');
  44  |     await page.waitForLoadState('networkidle');
  45  | 
  46  |     const response = await page.evaluate(async () => {
  47  |       const res = await fetch('/api/admin/notification-providers', {
  48  |         credentials: 'include',
  49  |       });
  50  |       return { status: res.status, body: await res.json() };
  51  |     });
  52  | 
  53  |     expect(response.status).toBe(200);
  54  |     expect(Array.isArray(response.body)).toBe(true);
  55  |   });
  56  | 
  57  |   test('API: list templates returns array', async ({ page }) => {
  58  |     await page.goto('/');
  59  |     await page.waitForLoadState('networkidle');
  60  | 
  61  |     const response = await page.evaluate(async () => {
  62  |       const res = await fetch('/api/admin/notification-templates', {
  63  |         credentials: 'include',
  64  |       });
  65  |       return { status: res.status, body: await res.json() };
  66  |     });
  67  | 
  68  |     expect(response.status).toBe(200);
  69  |     expect(Array.isArray(response.body)).toBe(true);
  70  |   });
  71  | 
  72  |   test('API: list logs returns paginated result', async ({ page }) => {
  73  |     await page.goto('/');
  74  |     await page.waitForLoadState('networkidle');
  75  | 
  76  |     const response = await page.evaluate(async () => {
  77  |       const res = await fetch('/api/admin/notification-logs', {
  78  |         credentials: 'include',
  79  |       });
  80  |       return { status: res.status, body: await res.json() };
  81  |     });
  82  | 
  83  |     expect(response.status).toBe(200);
  84  |     expect(response.body).toHaveProperty('items');
  85  |     expect(response.body).toHaveProperty('page');
  86  |     expect(response.body).toHaveProperty('total');
  87  |     expect(Array.isArray(response.body.items)).toBe(true);
  88  |   });
  89  | 
  90  |   // ─── Webhooks (public) ───
  91  | 
  92  |   test('Webhook: twilio endpoint accepts POST', async ({ page }) => {
  93  |     const response = await page.evaluate(async () => {
  94  |       const params = new URLSearchParams();
  95  |       params.append('MessageSid', 'SM_TEST_123');
  96  |       params.append('MessageStatus', 'delivered');
  97  |       const res = await fetch('/api/webhooks/notifications/twilio', {
  98  |         method: 'POST',
  99  |         headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  100 |         body: params.toString(),
  101 |       });
  102 |       return { status: res.status, body: await res.json() };
  103 |     });
  104 | 
  105 |     expect(response.status).toBe(200);
  106 |     expect(response.body.received).toBe(true);
  107 |   });
  108 | 
  109 |   test('Webhook: ovh endpoint accepts POST', async ({ page }) => {
  110 |     const response = await page.evaluate(async () => {
  111 |       const res = await fetch('/api/webhooks/notifications/ovh', {
  112 |         method: 'POST',
  113 |         headers: { 'Content-Type': 'application/json' },
  114 |         body: JSON.stringify({ id: 'test-123', deliveryReceipt: 1 }),
  115 |       });
  116 |       return { status: res.status, body: await res.json() };
  117 |     });
  118 | 
  119 |     expect(response.status).toBe(200);
  120 |     expect(response.body.received).toBe(true);
  121 |   });
  122 | 
  123 |   test('Webhook: mailgun endpoint accepts POST', async ({ page }) => {
```