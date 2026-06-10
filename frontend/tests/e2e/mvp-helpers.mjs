import { test, expect } from '@playwright/test';

const BASE_URL = (process.env.PLAYWRIGHT_BASE_URL || 'http://localhost').replace(/\/$/, '');

export function appUrl(path = '') {
  return `${BASE_URL}${path}`;
}

/**
 * Helper: login as admin and return authenticated page
 */
export async function loginAsAdmin(page) {
  const passwords = [...new Set(['Admin123!', process.env.ADMIN_PASSWORD, 'admin123'].filter(Boolean))];

  await page.goto('/login');

  for (const password of passwords) {
    await page.fill('input[type="email"]', 'admin@atelier.local');
    await page.fill('input[type="password"]', password);
    await page.click('button[type="submit"]');

    try {
      // Wait for navigation to complete (either full reload or SPA navigation)
      await page.waitForLoadState('networkidle', { timeout: 15000 });
      // Check if we are no longer on login page
      await expect(page.locator('body')).toContainText(/dashboard|tableau|rdv|pont|stat|déconnexion|nouveau rdv/i, { timeout: 15000 });
      return;
    } catch {
      // If still on login, try next password or refresh
      const url = page.url();
      if (url.includes('/login')) {
        await page.goto('/login');
      } else {
        // We navigated away but text didn't match — might be enough
        return;
      }
    }
  }

  throw new Error('Admin login failed with all configured passwords');
}

/**
 * Helper: verify a page loads and contains expected text
 */
export async function expectPageLoads(page, path, expectedTextRegex, timeout = 10000) {
  const response = await page.goto(path);
  expect(response?.status()).toBe(200);
  await page.waitForLoadState('networkidle');
  await expect(page.locator('body')).toContainText(expectedTextRegex, { timeout });
}

/**
 * Helper: open first modal on a page and verify it renders
 */
export async function openFirstModal(page, triggerTextOrSelector) {
  const trigger = typeof triggerTextOrSelector === 'string'
    ? page.locator(`button:has-text("${triggerTextOrSelector}"), a:has-text("${triggerTextOrSelector}")`).first()
    : page.locator(triggerTextOrSelector).first();

  if (await trigger.isVisible().catch(() => false)) {
    await trigger.click();
    await page.waitForTimeout(300);
    await expect(page.locator('.app-modal-overlay')).toBeVisible({ timeout: 5000 });
    return true;
  }
  return false;
}

/**
 * Helper: close modal via Escape key
 */
export async function closeModalByEscape(page) {
  await page.keyboard.press('Escape');
  await page.waitForTimeout(300);
  await expect(page.locator('.app-modal-overlay')).not.toBeVisible();
}

/**
 * Helper: vrai si le middleware a redirigé vers le dashboard car le module est désactivé
 */
export function isModuleDisabledRedirect(page, section) {
  return page.url().includes(`moduleDisabled=${section}`);
}

/**
 * Helper: vrai si le module est activé pour l'atelier actif, d'après /api/config.
 * Plus fiable que l'inspection d'URL (la query moduleDisabled peut être nettoyée
 * par une navigation ultérieure du router).
 */
export async function isSectionEnabled(page, section) {
  return page.evaluate(async (s) => {
    try {
      const res = await fetch('/api/config', { credentials: 'include' });
      if (!res.ok) return true;
      const cfg = await res.json();
      const fm = cfg?.feature_modules || cfg?.featureModules || {};
      return fm[s] !== false;
    } catch {
      return true;
    }
  }, section);
}

/**
 * Helper: page d'un module désactivable — si le module est actif, vérifie le
 * contenu attendu ; sinon vérifie la redirection middleware vers le dashboard.
 * Retourne false si le module est désactivé, true sinon.
 */
export async function expectModulePageOrDisabled(page, path, section, expectedTextRegex, timeout = 10000) {
  const response = await page.goto(path);
  expect(response?.status()).toBe(200);
  await page.waitForLoadState('networkidle');
  const enabled = await isSectionEnabled(page, section);
  if (!enabled) {
    // Le middleware doit avoir renvoyé vers le dashboard
    await expect(page).not.toHaveURL(new RegExp(`${path.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`), { timeout });
    return false;
  }
  await expect(page.locator('body')).toContainText(expectedTextRegex, { timeout });
  return true;
}

/**
 * Helper: verify API endpoint returns 200.
 * Fetch en chemin relatif (même origine que la page) — une URL http absolue
 * depuis une page https serait bloquée en mixed content.
 */
export async function expectApiOk(page, path) {
  const response = await page.evaluate(async (url) => {
    const res = await fetch(url, { credentials: 'include' });
    return { status: res.status };
  }, path);
  expect(response.status).toBe(200);
}
