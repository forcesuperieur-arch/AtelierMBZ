# Playwright E2E Testing — Skill Projet

## Config
- `playwright.config.mjs` dans `frontend/`
- `testDir: './tests/e2e'`
- Browser : Chromium uniquement
- `baseURL: 'http://localhost'` (overridable via `PLAYWRIGHT_BASE_URL`)
- Navigation : `domcontentloaded` (pas `load` à cause du HMR Nuxt)

## Patterns de test

### Structure de base
```javascript
import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers.mjs';

test.describe('Module X', () => {
  test('should do something', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/route', { waitUntil: 'domcontentloaded' });
    await expect(page.getByText('...')).toBeVisible();
  });
});
```

### Helpers disponibles
- `loginAsAdmin(page)` — Login via `/login` avec retry et fallback Google dev
- `appUrl(path)` — Construit une URL absolue

### Sélecteurs préférés (ordre de fiabilité)
1. `page.getByRole('button', { name: /Texte/i })`
2. `page.getByLabel(/Label/i)`
3. `page.getByText(/Texte/i)`
4. `page.locator('[data-testid="..."]').first()` — dernier recours

### Interactions API directes
```javascript
const request = page.context().request;
const response = await request.post('/api/endpoint', { data: { ... } });
expect(response.ok()).toBeTruthy();
const payload = await response.json();
```

### Upload de fichiers
```javascript
const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0FYAAAAASUVORK5CYII=';
await page.locator('input[type="file"]').setInputFiles({
  name: 'file.png',
  mimeType: 'image/png',
  buffer: Buffer.from(PNG_BASE64, 'base64'),
});
```

### Attentes pollées
```javascript
await expect.poll(async () => {
  const res = await request.get('/api/endpoint');
  const data = await res.json();
  return data.someCondition;
}, { timeout: 15000 }).toBeTruthy();
```

## Bonnes pratiques
- Toujours `loginAsAdmin(page)` en premier
- Utiliser `Date.now()` ou `bin2hex(random_bytes(4))` pour les données uniques
- Tester le happy path + une erreur visible (toast rouge, message d'erreur)
- `page.waitForTimeout(300)` entre les clicks rapides si le DOM est instable
- Fermer les pages secondaires : `await publicPage.close()`

## Lancer les tests
```bash
# Frontend directory
cd frontend
npx playwright test tests/e2e/stock.spec.mjs
npx playwright test --headed          # Mode visible
npx playwright test --debug           # Mode debug
```
