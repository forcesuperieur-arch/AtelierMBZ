import { test, expect } from '@playwright/test';

import { loginAsAdmin } from './helpers.mjs';

function extractCollection(data) {
  return data?.['hydra:member'] ?? data?.items ?? data?.member ?? (Array.isArray(data) ? data : []);
}

function formatEuro(value) {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
  }).format(Number.parseFloat(String(value ?? 0)) || 0);
}

function toHt(priceTtc) {
  return (Number(priceTtc) / 1.2).toFixed(2);
}

function buildPlate(seed, index) {
  const letters = `${seed}${index}`.toUpperCase().replace(/[^A-Z]/g, 'A').padEnd(4, 'A');
  const left = letters.slice(0, 2);
  const right = letters.slice(2, 4);
  const number = String((Date.now() + index) % 900 + 100).slice(-3);
  return `${left}-${number}-${right}`;
}

function buildVin(seed, index) {
  return `VINPRIX${seed}${index}`.toUpperCase().replace(/[^A-Z0-9]/g, '0').padEnd(17, '0').slice(0, 17);
}

async function readJson(response) {
  const text = await response.text();
  return text ? JSON.parse(text) : {};
}

async function createClient(request, seed, index) {
  const response = await request.post('/api/clients', {
    data: {
      prenom: `Prix${index}`,
      nom: `Vo${seed}`,
      telephone: `0600000${String(index).padStart(3, '0')}`,
      email: `prix-${seed}-${index}@example.test`,
      adresse: `${index} rue des grilles, Paris`,
      consentDate: new Date().toISOString(),
      consentSource: 'playwright_vo_pricing',
    },
  });

  expect(response.ok(), await response.text()).toBeTruthy();
  return readJson(response);
}

async function createPrestation(request, seed) {
  const response = await request.post('/api/prestations', {
    data: {
      code: `VO-PRIX-${seed}`,
      nom: `Prestation VO prix ${seed}`,
      description: 'Prestation créée par Playwright pour vérifier les écarts de grille catégorie.',
      categorie: 'vo_test',
      prix_base_ht: '90.00',
      prix_base_ttc: '108.00',
      temps_estime_minutes: 45,
      type_tarif: 'forfait',
      is_active: 1,
    },
  });

  expect(response.ok(), await response.text()).toBeTruthy();
  return readJson(response);
}

async function createGrilleTarifaire(request, prestationId, categoryId, priceTtc, minutes = 45) {
  const response = await request.post('/api/grille_tarifaires', {
    data: {
      prestation: `/api/prestations/${prestationId}`,
      categorie_moto: `/api/motos/categories/${categoryId}`,
      type_vehicule: 'tous',
      prix_ht: toHt(priceTtc),
      prix_ttc: Number(priceTtc).toFixed(2),
      temps_minutes: minutes,
      type_tarif: 'forfait',
      delai_jours: 1,
      is_active: 1,
    },
  });

  expect(response.ok(), await response.text()).toBeTruthy();
  return readJson(response);
}

async function createVehicle(request, seed, index, clientId, categoryId) {
  const response = await request.post('/api/vehicules', {
    data: {
      client: `/api/clients/${clientId}`,
      plaque: buildPlate(seed, index),
      vin: buildVin(seed, index),
      marque: 'Yamaha',
      modele: 'MT-07',
      categorie: `/api/motos/categories/${categoryId}`,
      typeMoto: 'moto',
      cylindree: '689',
      annee: 2024,
      mileage: 1500 + index,
      couleur: 'Noir',
      datePremiereMiseEnCirculation: '2024-03-10',
    },
  });

  expect(response.ok(), await response.text()).toBeTruthy();
  return readJson(response);
}

async function createPurchase(request, seed, index, sellerId, vehicleId) {
  const response = await request.post('/api/vo/purchases', {
    data: {
      sellerId,
      vehiculeId: vehicleId,
      purchasePrice: '6500.00',
      targetSalePrice: '8900.00',
      purchaseDate: '2026-04-18',
      sellerIdType: 'carte_identite',
      sellerIdNumber: `CI-${seed}-${index}`,
      sellerIdDate: '2025-06-01',
      nonGageDate: '2026-04-18',
      controleTechniqueOk: true,
      status: 'brouillon',
    },
  });

  expect(response.ok(), await response.text()).toBeTruthy();
  return readJson(response);
}

async function createCampaign(request, purchaseId) {
  const response = await request.post(`/api/vo/purchases/${purchaseId}/remises-en-etat`, { data: {} });
  expect(response.ok(), await response.text()).toBeTruthy();
  return readJson(response);
}

async function fetchApplicablePrestations(request, campaignId) {
  const response = await request.get(`/api/vo/remises-en-etat/${campaignId}/prestations-applicables`);
  expect(response.ok(), await response.text()).toBeTruthy();
  const payload = await readJson(response);
  return payload.items || [];
}

async function getUiOptionLabel(page, purchaseId, prestationName) {
  await page.goto(`/vo/rachats/${purchaseId}`);
  await expect(page.getByText('Remise en etat VO', { exact: true }).first()).toBeVisible();

  await expect.poll(async () => {
    const optionTexts = await page.locator('.vo-inline-form--3 select option').allTextContents();
    return optionTexts.map(text => text.trim()).find(text => text.includes(prestationName)) || null;
  }, {
    timeout: 15000,
  }).toBeTruthy();

  const optionTexts = await page.locator('.vo-inline-form--3 select option').allTextContents();
  return optionTexts.map(text => text.trim()).find(text => text.includes(prestationName)) || null;
}

test('VO refurbishment prices differ between two tariff categories', async ({ page }) => {
  await loginAsAdmin(page);

  const request = page.context().request;
  const categoryResponse = await request.get('/api/motos/categories?itemsPerPage=20');
  expect(categoryResponse.ok(), await categoryResponse.text()).toBeTruthy();

  const categories = extractCollection(await readJson(categoryResponse))
    .filter(category => Number(category?.is_active ?? category?.isActive ?? 1) === 1)
    .slice(0, 2);

  expect(categories.length).toBeGreaterThan(1);

  const seed = Date.now().toString(36).slice(-6).toUpperCase();
  const prestation = await createPrestation(request, seed);

  const leftCategory = categories[0];
  const rightCategory = categories[1];
  const leftPriceTtc = 132.0;
  const rightPriceTtc = 198.0;

  await createGrilleTarifaire(request, prestation.id, leftCategory.id, leftPriceTtc, 40);
  await createGrilleTarifaire(request, prestation.id, rightCategory.id, rightPriceTtc, 55);

  const leftClient = await createClient(request, seed, 1);
  const rightClient = await createClient(request, seed, 2);
  const leftVehicle = await createVehicle(request, seed, 1, leftClient.id, leftCategory.id);
  const rightVehicle = await createVehicle(request, seed, 2, rightClient.id, rightCategory.id);
  const leftPurchase = await createPurchase(request, seed, 1, leftClient.id, leftVehicle.id);
  const rightPurchase = await createPurchase(request, seed, 2, rightClient.id, rightVehicle.id);
  const leftCampaign = await createCampaign(request, leftPurchase.id);
  const rightCampaign = await createCampaign(request, rightPurchase.id);

  const leftItems = await fetchApplicablePrestations(request, leftCampaign.id);
  const rightItems = await fetchApplicablePrestations(request, rightCampaign.id);
  const leftItem = leftItems.find(item => item.prestationId === prestation.id);
  const rightItem = rightItems.find(item => item.prestationId === prestation.id);

  expect(leftItem, 'La prestation dédiée n\'est pas remontée côté catégorie gauche.').toBeTruthy();
  expect(rightItem, 'La prestation dédiée n\'est pas remontée côté catégorie droite.').toBeTruthy();
  expect(String(leftItem.prixHt)).toBe(toHt(leftPriceTtc));
  expect(String(rightItem.prixHt)).toBe(toHt(rightPriceTtc));
  expect(String(leftItem.prixHt)).not.toBe(String(rightItem.prixHt));

  const leftLabel = await getUiOptionLabel(page, leftPurchase.id, prestation.nom);
  const rightLabel = await getUiOptionLabel(page, rightPurchase.id, prestation.nom);

  expect(leftLabel).toBeTruthy();
  expect(rightLabel).toBeTruthy();
  expect(leftLabel).toContain(formatEuro(toHt(leftPriceTtc)));
  expect(rightLabel).toContain(formatEuro(toHt(rightPriceTtc)));
  expect(leftLabel).not.toBe(rightLabel);
});