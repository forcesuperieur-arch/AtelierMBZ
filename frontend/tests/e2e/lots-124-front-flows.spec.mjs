import { test, expect } from '@playwright/test';
import { loginAsAdmin, appUrl } from './helpers.mjs';

const today = new Date();
const todayStr = today.toISOString().slice(0, 10);
const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
const tomorrowStr = tomorrow.toISOString().slice(0, 10);

/* ── API helpers (run inside the browser so cookies/JWT are sent) ── */

async function apiPost(page, endpoint, body) {
  return page.evaluate(async ({ url, body }) => {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    return { status: res.status, data };
  }, { url: `/api${endpoint}`, body });
}

async function apiPatch(page, endpoint, body) {
  return page.evaluate(async ({ url, body }) => {
    const res = await fetch(url, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/merge-patch+json' },
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    return { status: res.status, data };
  }, { url: `/api${endpoint}`, body });
}

async function apiDelete(page, endpoint) {
  return page.evaluate(async ({ url }) => {
    const res = await fetch(url, { method: 'DELETE' });
    return { status: res.status };
  }, { url: `/api${endpoint}` });
}

async function apiGet(page, endpoint) {
  return page.evaluate(async ({ url }) => {
    const res = await fetch(url);
    const data = await res.json().catch(() => ({}));
    return { status: res.status, data };
  }, { url: `/api${endpoint}` });
}

/* ═══════════════════════════════════════════════════════════════════
   LOT 1
   ═══════════════════════════════════════════════════════════════════ */

test.describe('Lot 1 — Front flows', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('Admin prestations: inactive prestation is greyed out with Inactif badge', async ({ page }) => {
    const suffix = Date.now();

    // 1. Create inactive prestation
    const createRes = await apiPost(page, '/prestations', {
      nom: `Inactive E2E ${suffix}`,
      code: `INACTIVE_${suffix}`,
      prix_base_ht: '100.00',
      temps_estime_minutes: 60,
      is_active: 0,
    });
    expect(createRes.status).toBe(201);
    const prestaId = createRes.data.id;

    // 2. Navigate to admin prestations
    await page.goto(appUrl('/admin/prestations'));
    await page.waitForLoadState('networkidle');

    // 3. Badge "Inactif" must be visible
    await expect(page.locator('body')).toContainText('Inactif');

    // 4. Row must have opacity-50 class
    const row = page.locator('tr', { hasText: `Inactive E2E ${suffix}` }).first();
    await expect(row).toHaveClass(/opacity-50/);

    // Cleanup
    await apiDelete(page, `/prestations/${prestaId}`);
  });

  test('RDV list: clicking detail opens modal instead of navigating to /rdv/[id]', async ({ page }) => {
    const suffix = Date.now();

    const rdvRes = await apiPost(page, '/rendez-vous', {
      client_nom: 'Modal',
      client_prenom: `Test ${suffix}`,
      client_telephone: '0612345678',
      date_rdv: todayStr,
      heure_debut: '10:00',
      type_intervention: 'revision',
    });
    expect(rdvRes.status).toBe(201);
    const rdvId = rdvRes.data.id;

    await page.goto(appUrl('/rdv'));
    await page.waitForLoadState('networkidle');

    // Click "Détail →" on the RDV card we created
    const rdvCard = page.locator('.rdv-card', { hasText: `Test ${suffix} Modal` }).first();
    const detailBtn = rdvCard.locator('button', { hasText: 'Détail →' }).first();
    await expect(detailBtn).toBeVisible();
    await detailBtn.click();

    // Modal must open with RDV number
    await expect(page.locator('body')).toContainText(`RDV #${rdvId}`);

    // URL must NOT contain /rdv/[id]
    expect(page.url()).not.toMatch(/\/rdv\/\d+/);

    // Cleanup
    await apiDelete(page, `/rendez-vous/${rdvId}`);
  });

  // FIXME(phase 3 MVP) : depuis l'ajout du sélecteur d'atelier sur /public/booking,
  // le verrou ne s'affiche que pour l'atelier sélectionné — le test patche configs[0]
  // sans garantir que c'est l'atelier affiché. À réaligner avec le flux booking.
  test.fixme('Public booking: disabled feature flag shows lock message', async ({ page }) => {
    // 1. Fetch current config
    const listRes = await apiGet(page, '/config_ateliers');
    const configs = listRes.data['hydra:member'] || listRes.data.member || [];
    const config = configs[0];
    expect(config?.id).toBeTruthy();

    try {
      // 2. Disable public_booking
      await apiPatch(page, `/config_ateliers/${config.id}`, {
        feature_modules: { ...config.feature_modules, public_booking: false },
      });

      // 3. Visit public booking
      await page.goto(appUrl('/public/booking'));
      await page.waitForLoadState('networkidle');

      // 4. Lock message must appear
      await expect(page.locator('body')).toContainText('Prise de rendez-vous désactivée');
    } finally {
      // 5. Re-enable (cleanup) — toujours exécuté, sinon le flag pollue les runs suivants
      await apiPatch(page, `/config_ateliers/${config.id}`, {
        feature_modules: { ...config.feature_modules, public_booking: true },
      });
    }
  });
});

/* ═══════════════════════════════════════════════════════════════════
   LOT 2
   ═══════════════════════════════════════════════════════════════════ */

test.describe('Lot 2 — Front flows', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('Admin ponts: assigning same mechanic to two ponts shows error', async ({ page }) => {
    const suffix = Date.now();

    // 1. Create mechanic
    const mecaRes = await apiPost(page, '/mecaniciens', {
      prenom: 'Jean',
      nom: `E2E ${suffix}`,
    });
    expect(mecaRes.status).toBe(201);
    const mecaId = mecaRes.data.id;

    // 2. Create pont 1 with mechanic
    const p1Res = await apiPost(page, '/ponts', {
      nom: `Pont 1 E2E ${suffix}`,
      atelier_id: 1,
      is_active: 1,
      mecanicien: `/api/mecaniciens/${mecaId}`,
    });
    expect(p1Res.status).toBe(201);
    const p1Id = p1Res.data.id;

    // 3. Create pont 2 without mechanic
    const p2Res = await apiPost(page, '/ponts', {
      nom: `Pont 2 E2E ${suffix}`,
      atelier_id: 1,
      is_active: 1,
    });
    expect(p2Res.status).toBe(201);
    const p2Id = p2Res.data.id;

    // 4. Navigate to admin/ponts
    await page.goto(appUrl('/admin/ponts'));
    await page.waitForLoadState('networkidle');

    // 5. Open edit modal for pont 2
    const p2Row = page.locator('tr', { hasText: `Pont 2 E2E ${suffix}` }).first();
    await p2Row.scrollIntoViewIfNeeded();
    await p2Row.locator('button', { hasText: /Modifier/i }).click();

    // 6. Select the same mechanic in the modal
    const modal = page.locator('.app-modal-overlay').last();
    await modal.locator('select.form-input').last().selectOption(String(mecaId));

    // 7. Submit
    await modal.locator('button:has-text("Modifier")').click();

    // 8. Error toast must appear
    await expect(page.locator('body')).toContainText('déjà assigné', { timeout: 10000 });

    // Cleanup
    await apiDelete(page, `/ponts/${p1Id}`);
    await apiDelete(page, `/ponts/${p2Id}`);
    await apiDelete(page, `/mecaniciens/${mecaId}`);
  });

  test('Planning grid: displays mechanic name on pont toolbar', async ({ page }) => {
    const suffix = Date.now();

    const mecaRes = await apiPost(page, '/mecaniciens', {
      prenom: 'Paul',
      nom: `Plan ${suffix}`,
    });
    expect(mecaRes.status).toBe(201);
    const mecaId = mecaRes.data.id;

    const p1Res = await apiPost(page, '/ponts', {
      nom: `Pont Plan ${suffix}`,
      atelier_id: 1,
      is_active: 1,
      mecanicien: `/api/mecaniciens/${mecaId}`,
    });
    expect(p1Res.status).toBe(201);
    const p1Id = p1Res.data.id;

    // Create an RDV for today on this pont so it appears in the planning grid
    const rdvRes = await apiPost(page, '/rendez-vous', {
      client_nom: 'Plan',
      client_prenom: `Test ${suffix}`,
      client_telephone: '0612345678',
      date_rdv: todayStr,
      heure_debut: '10:00',
      type_intervention: 'revision',
      pont: `/api/ponts/${p1Id}`,
    });
    expect(rdvRes.status).toBe(201);
    const rdvId = rdvRes.data.id;

    await page.goto(appUrl('/planning'));
    await page.waitForLoadState('networkidle');

    // Toolbar must show "Pont Plan X — Paul"
    await expect(page.locator('body')).toContainText(`Pont Plan ${suffix}`);
    await expect(page.locator('body')).toContainText('Paul');

    // Cleanup
    await apiDelete(page, `/rendez-vous/${rdvId}`);
    await apiDelete(page, `/ponts/${p1Id}`);
    await apiDelete(page, `/mecaniciens/${mecaId}`);
  });
});

/* ═══════════════════════════════════════════════════════════════════
   LOT 4
   ═══════════════════════════════════════════════════════════════════ */

test.describe('Lot 4 — Front flows', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('RDV list: command numbers appear as tags on cards', async ({ page }) => {
    const suffix = Date.now();

    // 1. Create RDV
    const rdvRes = await apiPost(page, '/rendez-vous', {
      client_nom: 'Commandes',
      client_prenom: `Test ${suffix}`,
      client_telephone: '0612345678',
      date_rdv: todayStr,
      heure_debut: '11:00',
      type_intervention: 'revision',
    });
    expect(rdvRes.status).toBe(201);
    const rdvId = rdvRes.data.id;

    // 2. Add command numbers
    const cmdRes = await apiPost(page, `/rendez-vous/${rdvId}/commandes`, {
      commandes: ['CMD-E2E-001', 'CMD-E2E-002'],
    });
    expect(cmdRes.status).toBe(200);

    // 3. Navigate to RDV list
    await page.goto(appUrl('/rdv'));
    await page.waitForLoadState('networkidle');

    // 4. Tags must be visible
    await expect(page.locator('body')).toContainText('CMD-E2E-001');
    await expect(page.locator('body')).toContainText('CMD-E2E-002');

    // Cleanup
    await apiDelete(page, `/rendez-vous/${rdvId}`);
  });

  test('Public suivi: email + phone tracking shows RDV', async ({ page }) => {
    const suffix = Date.now();
    const email = `suivi.${suffix}@example.com`;
    const telephone = '0699887766';

    // 1. Create RDV with email + phone
    const rdvRes = await apiPost(page, '/rendez-vous', {
      client_nom: 'Suivi',
      client_prenom: `Test ${suffix}`,
      client_telephone: telephone,
      client_email: email,
      date_rdv: tomorrowStr,
      heure_debut: '14:00',
      type_intervention: 'revision',
    });
    expect(rdvRes.status).toBe(201);
    const rdvId = rdvRes.data.id;

    // 2. Go to public tracking page (no auth)
    await page.goto(appUrl('/public/suivi'));
    await page.waitForLoadState('networkidle');

    // 3. Fill form
    await page.fill('input[type="email"]', email);
    await page.fill('input[placeholder*="06 12 34 56 78"]', telephone);
    await page.click('button:has-text("Rechercher")');

    // 4. RDV must be displayed
    await expect(page.locator('body')).toContainText('revision', { timeout: 10000 });
    await expect(page.locator('body')).toContainText(tomorrowStr);

    // Cleanup
    await apiDelete(page, `/rendez-vous/${rdvId}`);
  });
});
