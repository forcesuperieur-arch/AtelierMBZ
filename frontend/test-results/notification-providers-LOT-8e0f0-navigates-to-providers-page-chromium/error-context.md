# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: notification-providers.spec.mjs >> LOT 11 — Multi-provider SMS/Email >> Admin Notifications card navigates to providers page
- Location: tests/e2e/notification-providers.spec.mjs:19:3

# Error details

```
Error: Channel closed
```

```
Error: locator.click: Target page, context or browser has been closed
Call log:
  - waiting for locator('.admin-card').filter({ hasText: 'Notifications' })

```

```
Error: browserContext.close: Target page, context or browser has been closed
```