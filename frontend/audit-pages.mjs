import { chromium } from 'playwright'
import fs from 'fs'

const BASE_URL = 'http://localhost:3000'
const REPORT_FILE = '/home/cmoreau/projects/mon-projet/AtelierMBZ/var/audit-pages.json'
const SCREENSHOT_DIR = '/home/cmoreau/projects/mon-projet/AtelierMBZ/var/screenshots'

const PUBLIC_PAGES = [
  '/login', '/2fa', '/cover',
  '/public/booking', '/public/mentions-legales', '/public/politique-confidentialite',
  '/public/suivi',
]

const AUTH_PAGES = [
  '/',
  '/planning', '/workshop', '/mecanicien',
  '/clients', '/clients/123',
  '/motos', '/stock', '/tarifs',
  '/devis', '/devis/123',
  '/facturation',
  '/ordres', '/ordres/123',
  '/rdv', '/rdv/123', '/rdv/new',
  '/suivi',
  '/vo', '/vo/depots', '/vo/rachats', '/vo/documents', '/vo/factures',
  '/profile', '/params',
  '/admin', '/admin/users', '/admin/config', '/admin/ponts',
  '/admin/prestations', '/admin/roles', '/admin/ateliers',
  '/admin/notifications', '/admin/notifications/providers',
]

fs.mkdirSync(SCREENSHOT_DIR, { recursive: true })

async function audit() {
  const browser = await chromium.launch({ headless: true })
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } })
  const page = await context.newPage()

  const results = []
  const errors = []

  page.on('pageerror', err => errors.push({ type: 'pageerror', message: err.message, stack: err.stack?.slice(0, 500) }))
  page.on('console', msg => {
    if (msg.type() === 'error' || msg.type() === 'warning') {
      errors.push({ type: msg.type(), text: msg.text().slice(0, 500) })
    }
  })

  // Login
  console.log('Logging in...')
  await page.goto(`${BASE_URL}/login`)
  await page.waitForTimeout(2000)
  const emailInput = page.locator('input[type="email"]').first()
  const passInput = page.locator('input[type="password"]').first()
  if (await emailInput.isVisible().catch(() => false)) {
    await emailInput.fill('admin@atelier.local')
    await passInput.fill('Admin123!')
    await page.locator('button[type="submit"]').first().click()
    await page.waitForTimeout(5000)
    if (page.url().includes('/2fa')) {
      await page.goto(`${BASE_URL}/`)
      await page.waitForTimeout(3000)
    }
  }
  // Verify we're logged in by checking for dashboard element
  const sidebar = page.locator('.pit-sidebar, nav').first()
  if (!await sidebar.isVisible().catch(() => false)) {
    console.log('Warning: may not be fully logged in, continuing anyway...')
  }

  const allPages = [...PUBLIC_PAGES, ...AUTH_PAGES]

  for (const path of allPages) {
    const url = `${BASE_URL}${path}`
    const pageErrors = []
    const pageWarnings = []
    let status = 'ok'
    let screenshotPath = ''

    const page2 = await context.newPage()
    page2.on('pageerror', err => pageErrors.push(err.message.slice(0, 300)))
    page2.on('console', msg => {
      const t = msg.type()
      const text = msg.text().slice(0, 300)
      if (t === 'error') pageErrors.push(text)
      if (t === 'warning') pageWarnings.push(text)
    })

    try {
      console.log(`Testing ${path}...`)
      const resp = await page2.goto(url, { waitUntil: 'networkidle', timeout: 15000 })
      await page2.waitForTimeout(1000)

      const title = await page2.title().catch(() => 'no title')
      const bodyText = await page2.locator('body').innerText({ timeout: 3000 }).catch(() => '')

      // Check for obvious error states
      if (bodyText.includes('500') && bodyText.includes('Internal Server Error')) {
        status = 'error_500'
      } else if (bodyText.includes('404') && bodyText.includes('Not Found')) {
        status = 'error_404'
      } else if (bodyText.trim().length < 50) {
        status = 'nearly_empty'
      } else if (pageErrors.length > 0) {
        status = 'js_errors'
      }

      const safeName = path.replace(/[^a-zA-Z0-9]/g, '_') || 'index'
      screenshotPath = `${SCREENSHOT_DIR}/${safeName}.png`
      await page2.screenshot({ path: screenshotPath, fullPage: false })

      results.push({
        path,
        url,
        status,
        httpStatus: resp?.status() || 0,
        title,
        bodyLength: bodyText.length,
        errors: [...new Set(pageErrors)].slice(0, 10),
        warnings: [...new Set(pageWarnings)].slice(0, 5),
        screenshot: screenshotPath,
      })
    } catch (e) {
      results.push({
        path,
        url,
        status: 'nav_error',
        error: String(e).slice(0, 300),
        errors: [...new Set(pageErrors)].slice(0, 10),
        warnings: [...new Set(pageWarnings)].slice(0, 5),
      })
    } finally {
      await page2.close()
    }
  }

  await browser.close()

  const summary = {
    total: results.length,
    ok: results.filter(r => r.status === 'ok').length,
    errors: results.filter(r => r.status !== 'ok').map(r => ({ path: r.path, status: r.status, errors: r.errors })),
    details: results,
  }

  fs.writeFileSync(REPORT_FILE, JSON.stringify(summary, null, 2))
  console.log(`\nAudit complete. ${summary.ok}/${summary.total} pages OK.`)
  console.log(`Report: ${REPORT_FILE}`)
  console.log(`Screenshots: ${SCREENSHOT_DIR}`)
  if (summary.errors.length) {
    console.log('\nFailed pages:')
    summary.errors.forEach(e => console.log(`  ${e.path}: ${e.status}`))
  }
}

audit().catch(e => {
  console.error('Audit failed:', e)
  process.exit(1)
})
