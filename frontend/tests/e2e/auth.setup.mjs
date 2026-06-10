import { test as setup } from '@playwright/test';
import { loginAsAdmin } from './mvp-helpers.mjs';

const adminFile = 'playwright/.auth/admin.json';

setup('authenticate as admin', async ({ page }) => {
  await loginAsAdmin(page);
  await page.context().storageState({ path: adminFile });
});
