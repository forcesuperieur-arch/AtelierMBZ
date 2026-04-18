import { test, expect } from '@playwright/test';

import { loginAsAdmin } from './helpers.mjs';

const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0FYAAAAASUVORK5CYII=';

function createUpload(name) {
  return {
    name,
    mimeType: 'image/png',
    buffer: Buffer.from(PNG_BASE64, 'base64'),
  };
}

function buildPlate(seed, suffix) {
  const letters = `${seed}${suffix}`.toUpperCase().replace(/[^A-Z]/g, 'A').padEnd(4, 'A');
  const left = letters.slice(0, 2);
  const right = letters.slice(2, 4);
  const number = String((Date.now() + String(suffix).length) % 900 + 100).slice(-3);
  return `${left}-${number}-${right}`;
}

function buildVin(seed, suffix) {
  return `VINCOMP${seed}${suffix}`.toUpperCase().replace(/[^A-Z0-9]/g, '0').padEnd(17, '0').slice(0, 17);
}

async function readJson(response) {
  const text = await response.text();
  return text ? JSON.parse(text) : {};
}

async function createPartyClient(request, seed, prefix, index) {
  const response = await request.post('/api/clients', {
    data: {
      prenom: prefix,
      nom: `Companion-${seed}-${index}`,
      telephone: `0602${String(index).padStart(6, '0')}`,
      email: `${prefix.toLowerCase()}-${seed}-${index}@example.test`,
      adresse: `${index} rue du compagnon, Paris`,
      consentDate: new Date().toISOString(),
      consentSource: 'playwright_vo_companion',
    },
  });

  expect(response.ok(), await response.text()).toBeTruthy();
  return readJson(response);
}

async function fetchFullRecord(request, kind, draftId) {
  const path = kind === 'purchase'
    ? `/api/vo/purchases/${draftId}/full`
    : `/api/vo/depots/${draftId}/full`;

  const response = await request.get(path);
  expect(response.ok(), await response.text()).toBeTruthy();
  return readJson(response);
}

async function openDraftCompanion(page, kind) {
  const path = kind === 'purchase' ? '/vo/rachats/new' : '/vo/depots/new';
  const endpoint = kind === 'purchase' ? '/api/vo/purchases' : '/api/vo/depots';

  const creationResponse = page.waitForResponse((response) => {
    return response.request().method() === 'POST' && response.url().includes(endpoint);
  });

  await page.goto(path);
  const payload = await readJson(await creationResponse);
  const link = page.getByRole('link', { name: 'Ouvrir le PDA' }).first();

  await expect(link).toBeVisible();

  const href = await link.getAttribute('href');

  expect(payload.id).toBeTruthy();
  expect(href).toBeTruthy();

  return {
    draftId: Number(payload.id),
    publicUrl: new URL(String(href), page.url()).toString(),
  };
}

async function completeSellerStep(publicPage, roleLabel, seed) {
  await expect(publicPage.getByRole('heading', { name: roleLabel })).toBeVisible();
  await publicPage.getByLabel('Type de pièce').fill('Carte nationale');
  await publicPage.getByLabel('Numéro de pièce').fill(`CI-${seed}`);
  await publicPage.getByLabel('Date du document').fill('2025-05-12');
  await publicPage.locator('label.vo-public-upload-box:has-text("Pièce d\'identité") input[type="file"]').setInputFiles(createUpload(`identite-${seed}.png`));
  await publicPage.getByRole('button', { name: 'Valider le vendeur' }).click();
  await expect(publicPage.getByRole('heading', { name: 'Véhicule' })).toBeVisible();
}

async function completeVehicleStep(publicPage, seed, suffix) {
  const plate = buildPlate(seed, suffix);
  const vin = buildVin(seed, suffix);

  await expect(publicPage.getByRole('heading', { name: 'Véhicule' })).toBeVisible();
  await publicPage.locator('label.vo-public-upload-box:has-text("Carte grise") input[type="file"]').setInputFiles(createUpload(`carte-grise-${suffix}.png`));
  await publicPage.locator('label.vo-public-upload-box:has-text("Photos du véhicule") input[type="file"]').setInputFiles(createUpload(`photo-${suffix}.png`));
  await publicPage.getByLabel('Plaque (A)').fill(plate);
  await publicPage.getByLabel('Marque (D.1)').fill('Yamaha');
  await publicPage.getByLabel('Modele (D.2)').fill('Tracer 9');
  await publicPage.getByLabel('VIN (E)').fill(vin);
  await publicPage.getByLabel('Mise en circulation (B)').fill('2022');
  await publicPage.getByLabel('Cylindree (P.1)').fill('890');
  await publicPage.getByLabel('Type (J.1)').fill('Trail');
  await publicPage.getByRole('button', { name: 'Valider le véhicule' }).click();

  return { plate, vin };
}

async function addRequiredDocument(publicPage, type, fileName, finalStep = false) {
  await expect(publicPage.getByRole('heading', { name: 'Documents' })).toBeVisible();
  await publicPage.getByLabel('Type de document').selectOption(type);
  await publicPage.locator('label.vo-public-upload-box:has-text("Document à ajouter") input[type="file"]').setInputFiles(createUpload(fileName));
  const saveResponse = publicPage.waitForResponse((response) => {
    return response.request().method() === 'POST' && response.url().includes('/api/public/vo-companion/') && response.url().endsWith('/document');
  });
  await publicPage.getByRole('button', { name: 'Valider les documents' }).click();
  const payload = await readJson(await saveResponse);

  expect(payload?.steps?.documents?.missing || []).not.toContain(type);

  if (finalStep) {
    expect(payload?.steps?.documents?.completed).toBeTruthy();
    await publicPage.locator('.vo-public-step-tab').filter({ hasText: 'Signature' }).click();
    await expect(publicPage.getByRole('heading', { name: 'Signature client' })).toBeVisible();
    return;
  }

  await expect(publicPage.getByRole('heading', { name: 'Documents' })).toBeVisible();
}

async function signCompanion(publicPage) {
  const canvas = publicPage.locator('canvas.vo-signature-canvas');

  await expect(publicPage.getByRole('heading', { name: 'Signature client' })).toBeVisible();
  await expect(canvas).toBeVisible();

  const box = await canvas.boundingBox();
  if (!box) {
    throw new Error('Signature canvas not available');
  }

  await publicPage.mouse.move(box.x + 40, box.y + box.height / 2);
  await publicPage.mouse.down();
  await publicPage.mouse.move(box.x + box.width / 2, box.y + 24, { steps: 8 });
  await publicPage.mouse.move(box.x + box.width - 40, box.y + box.height - 24, { steps: 8 });
  await publicPage.mouse.up();

  await expect(publicPage.getByRole('button', { name: 'Valider la signature' })).toBeEnabled();
  await publicPage.getByRole('button', { name: 'Valider la signature' }).click();
  await expect(publicPage.getByText('Signature déjà enregistrée. Le dossier est désormais verrouillé sur le PDA.')).toBeVisible();
  await expect(publicPage.getByText('Dossier terminé. Les documents auto-générés sont maintenant confirmés avec la signature client.')).toBeVisible();
}

async function assertLockedCompanion(publicPage, roleLabel) {
  await publicPage.reload();
  await publicPage.waitForLoadState('networkidle');
  await expect(publicPage.getByText('Signature déjà enregistrée. Le dossier est désormais verrouillé sur le PDA.')).toBeVisible();
  await expect(publicPage.getByText('4/4 étapes validées')).toBeVisible();

  await expect(publicPage.locator('.vo-public-step-tab').first()).toBeVisible();
  await publicPage.locator('.vo-public-step-tab').first().click();
  await expect(publicPage.getByRole('heading', { name: roleLabel })).toBeVisible();
  await expect(publicPage.getByLabel('Type de pièce')).toBeDisabled();
  await expect(publicPage.getByRole('button', { name: 'Valider le vendeur' })).toBeDisabled();
}

test.describe('VO companion full flow', () => {
  test('purchase draft can be signed on PDA then archived on admin finalization', async ({ page }) => {
    await loginAsAdmin(page);
    const request = page.context().request;
    const seed = Date.now().toString(36).slice(-6).toUpperCase();
    const { draftId, publicUrl } = await openDraftCompanion(page, 'purchase');
    const publicPage = await page.context().newPage();

    await publicPage.goto(publicUrl);
    await completeSellerStep(publicPage, 'Vendeur', `${seed}-ACHAT`);
    const vehicle = await completeVehicleStep(publicPage, seed, 'ACHAT');
    await addRequiredDocument(publicPage, 'non_gage', `non-gage-${seed}.png`);
    await addRequiredDocument(publicPage, 'cerfa_cession_achat', `cerfa-${seed}.png`, true);
    await signCompanion(publicPage);
    await assertLockedCompanion(publicPage, 'Vendeur');

    const seller = await createPartyClient(request, seed, 'Vendeur', 1);
    const updateResponse = await request.patch(`/api/vo/purchases/${draftId}`, {
      data: {
        sellerId: seller.id,
        purchasePrice: '6450.00',
        targetSalePrice: '8990.00',
        purchaseDate: '2026-04-18',
        nonGageDate: '2026-04-18',
        controleTechniqueOk: true,
      },
    });

    expect(updateResponse.ok(), await updateResponse.text()).toBeTruthy();

    await expect.poll(async () => {
      const full = await fetchFullRecord(request, 'purchase', draftId);
      return full.companion?.steps?.allComplete === true
        && Array.isArray(full.documents)
        && full.documents.some((document) => document.type === 'pv_rachat')
        && full.vehicule?.plaque === vehicle.plate
        && full.vehicule?.vin === vehicle.vin;
    }, {
      timeout: 15000,
    }).toBeTruthy();

    await page.goto(`/vo/rachats/${draftId}?companion=1`);
    await expect(page.locator('#vo-companion-zone')).toContainText('4/4 étapes');
    await expect(page.getByText('PV de rachat auto-rempli')).toBeVisible();
    await expect(page.getByText(/Signé le/)).toBeVisible();

    await publicPage.close();
  });

  test('depot draft can be signed on PDA then archived on finalization', async ({ page }) => {
    await loginAsAdmin(page);
    const request = page.context().request;
    const seed = Date.now().toString(36).slice(-6).toUpperCase();
    const { draftId, publicUrl } = await openDraftCompanion(page, 'depot');
    const publicPage = await page.context().newPage();

    await publicPage.goto(publicUrl);
    await completeSellerStep(publicPage, 'Déposant', `${seed}-DEPOT`);
    const vehicle = await completeVehicleStep(publicPage, seed, 'DEPOT');
    await expect(publicPage.getByRole('heading', { name: 'Signature client' })).toBeVisible();
    await signCompanion(publicPage);
    await assertLockedCompanion(publicPage, 'Déposant');

    const deposant = await createPartyClient(request, seed, 'Deposant', 2);
    const updateResponse = await request.patch(`/api/vo/depots/${draftId}`, {
      data: {
        deposantId: deposant.id,
        prixVenteSouhaite: '11900.00',
        commissionType: 'pourcentage',
        commissionValeur: '10.00',
        dateDebut: '2026-04-18',
        dureeMandat: 90,
        status: 'actif',
        finalizeCompanionDraft: true,
      },
    });

    expect(updateResponse.ok(), await updateResponse.text()).toBeTruthy();

    await expect.poll(async () => {
      const full = await fetchFullRecord(request, 'depot', draftId);
      return full.status === 'actif'
        && full.companion?.steps?.allComplete === true
        && Array.isArray(full.documents)
        && full.documents.some((document) => document.type === 'contrat_depot_vente')
        && full.vehicule?.plaque === vehicle.plate
        && full.vehicule?.vin === vehicle.vin;
    }, {
      timeout: 15000,
    }).toBeTruthy();

    await page.goto(`/vo/depots/${draftId}?companion=1`);
    await expect(page.locator('#vo-companion-zone')).toContainText('4/4 étapes');
    await expect(page.getByText('Contrat depot-vente auto-rempli')).toBeVisible();
    await expect(page.getByText(/Signé le/)).toBeVisible();

    await publicPage.close();
  });
});