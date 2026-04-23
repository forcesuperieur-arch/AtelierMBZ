import { test, expect } from '@playwright/test';

const BASE_URL = (process.env.PLAYWRIGHT_BASE_URL || 'http://localhost').replace(/\/$/, '');
const SECURE_BASE_URL = BASE_URL.startsWith('https://') ? BASE_URL : BASE_URL.replace(/^http:\/\//, 'https://');

export function appUrl(path = '') {
  return `${BASE_URL}${path}`;
}

/**
 * Helper: login as admin and return authenticated page
 */
export async function loginAsAdmin(page) {
  const passwords = [...new Set(['Admin123!', process.env.ADMIN_PASSWORD, 'admin123'].filter(Boolean))];

  await page.goto(appUrl('/login'), { waitUntil: 'domcontentloaded' });

  for (const password of passwords) {
    await page.fill('input[type="email"]', 'admin@atelier.local');
    await page.fill('input[type="password"]', password);
    await page.click('button[type="submit"]');

    try {
      await page.waitForURL(/\/($|\?)/, { timeout: 15000 });
      await expect(page.locator('body')).toContainText(/dashboard|tableau|rdv|pont/i, { timeout: 15000 });
      return;
    } catch {
      await page.goto(appUrl('/login'), { waitUntil: 'domcontentloaded' });
    }
  }

  try {
    await page.goto(`${SECURE_BASE_URL}/api/auth/google/dev-simulate?email=admin@atelier.local&mode=login`, { waitUntil: 'domcontentloaded' });
    await page.goto(`${SECURE_BASE_URL}/`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).toContainText(/dashboard|tableau|rdv|pont/i, { timeout: 15000 });
    return;
  } catch {
    // Continue to the explicit failure below.
  }

  throw new Error('Admin login failed with all configured passwords');
}
