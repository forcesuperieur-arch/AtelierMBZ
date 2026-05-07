import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { defineConfig, devices } = require('./frontend/node_modules/@playwright/test');

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'https://localhost';

export default defineConfig({
  testDir: './frontend/tests/e2e',
  fullyParallel: false,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: [
    ['list'],
    ['html', { open: 'never', outputFolder: 'playwright-report' }],
  ],
  use: {
    baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: true,
    viewport: { width: 1440, height: 1000 },
  },
  webServer: {
    command: 'cd frontend && npm run build && npm run preview',
    url: 'http://localhost:3000',
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
