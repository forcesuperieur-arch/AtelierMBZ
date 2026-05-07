const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({
    executablePath: '/home/cmoreau/.cache/ms-playwright/chromium-1217/chrome-linux64/chrome'
  });
  const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
  
  await page.goto('http://localhost:3000/login', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: '/home/cmoreau/projects/mon-projet/AtelierMBZ/screenshot-login.png', fullPage: true });
  
  await page.goto('http://localhost:3000/', { waitUntil: 'networkidle' });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: '/home/cmoreau/projects/mon-projet/AtelierMBZ/screenshot-dashboard.png', fullPage: true });
  
  await browser.close();
  console.log('Screenshots saved');
})();
