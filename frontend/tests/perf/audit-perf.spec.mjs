import { test, expect } from '@playwright/test';
import { appUrl, loginAsAdmin } from '../e2e/helpers.mjs';

const PAGES = [
  { path: '/login', name: 'Login', needsAuth: false },
  { path: '/', name: 'Dashboard', needsAuth: true },
  { path: '/planning', name: 'Planning', needsAuth: true },
  { path: '/workshop', name: 'Workshop', needsAuth: true },
  { path: '/public/booking', name: 'Public Booking', needsAuth: false },
];

async function collectWebVitals(page) {
  return page.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0];
    const paint = performance.getEntriesByType('paint');
    const lcpEntries = performance.getEntriesByType('largest-contentful-paint');
    const clsEntries = performance.getEntriesByType('layout-shift');

    const fcp = paint.find(p => p.name === 'first-contentful-paint')?.startTime;
    const lcp = lcpEntries.length ? lcpEntries[lcpEntries.length - 1].startTime : null;
    const cls = clsEntries.reduce((sum, e) => sum + (e.hadRecentInput ? 0 : e.value), 0);

    return {
      ttfb: nav?.responseStart,
      fcp,
      lcp,
      cls,
      domContentLoaded: nav?.domContentLoadedEventEnd,
      loadComplete: nav?.loadEventEnd,
      transferSize: nav?.transferSize,
      encodedBodySize: nav?.encodedBodySize,
      resourceCount: performance.getEntriesByType('resource').length,
    };
  });
}

for (const { path, name, needsAuth } of PAGES) {
  test(`Performance audit: ${name} (${path})`, async ({ page }) => {
    if (needsAuth) await loginAsAdmin(page);

    await page.goto(appUrl(path));
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500); // let LCP settle

    const vitals = await collectWebVitals(page);

    // Soft assertions — log warnings rather than fail
    console.log(`\n=== ${name} (${path}) ===`);
    console.log(`  TTFB:              ${vitals.ttfb?.toFixed(0)} ms`);
    console.log(`  FCP:               ${vitals.fcp?.toFixed(0)} ms`);
    console.log(`  LCP:               ${vitals.lcp?.toFixed(0)} ms`);
    console.log(`  CLS:               ${vitals.cls?.toFixed(4)}`);
    console.log(`  DOMContentLoaded:  ${vitals.domContentLoaded?.toFixed(0)} ms`);
    console.log(`  LoadComplete:      ${vitals.loadComplete?.toFixed(0)} ms`);
    console.log(`  TransferSize:      ${(vitals.transferSize / 1024).toFixed(1)} KB`);
    console.log(`  EncodedBodySize:   ${(vitals.encodedBodySize / 1024).toFixed(1)} KB`);
    console.log(`  ResourceCount:     ${vitals.resourceCount}`);

    // Hard thresholds for critical issues
    if (vitals.lcp && vitals.lcp > 4000) {
      console.warn(`  ⚠️ LCP > 4s: ${vitals.lcp.toFixed(0)}ms`);
    }
    if (vitals.cls && vitals.cls > 0.25) {
      console.warn(`  ⚠️ CLS > 0.25: ${vitals.cls.toFixed(4)}`);
    }
    if (vitals.transferSize && vitals.transferSize > 5 * 1024 * 1024) {
      console.warn(`  ⚠️ Transfer > 5MB: ${(vitals.transferSize / 1024 / 1024).toFixed(1)}MB`);
    }

    expect(vitals.lcp || 0).toBeLessThan(5000);
    expect(vitals.cls || 0).toBeLessThan(0.5);
  });
}
