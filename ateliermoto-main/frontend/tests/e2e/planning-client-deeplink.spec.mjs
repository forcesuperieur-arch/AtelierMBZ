import { test, expect } from '@playwright/test';

const E2E_USERNAME = process.env.E2E_USERNAME || process.env.ADMIN_USERNAME || '';
const E2E_PASSWORD = process.env.E2E_PASSWORD || process.env.ADMIN_PASSWORD || '';

function getCurrentWeekMonday() {
  const date = new Date();
  const day = date.getDay();
  const diff = day === 0 ? -6 : 1 - day;
  date.setDate(date.getDate() + diff);
  date.setHours(12, 0, 0, 0);
  return date;
}

function normalizeHour(value) {
  const raw = String(value || '09:00').trim();
  return raw.length >= 5 ? `${raw.slice(0, 5)}:00` : '09:00:00';
}

async function choosePlanningSlot(request, accessToken) {
  const fallbackDate = getCurrentWeekMonday().toISOString().slice(0, 10);
  const fallback = { date: fallbackDate, heure: '10:00:00' };

  const response = await request.get('/api/config/horaires', {
    headers: {
      Authorization: `Bearer ${accessToken}`,
    },
  });
  if (!response.ok()) return fallback;

  const horaires = await response.json();
  const openByWeekday = new Map(
    (Array.isArray(horaires) ? horaires : [])
      .filter((item) => item && (item.is_ouvert === 1 || item.is_ouvert === true))
      .map((item) => [Number(item.jour_semaine), item]),
  );

  const monday = getCurrentWeekMonday();
  for (let offset = 0; offset < 7; offset += 1) {
    const date = new Date(monday);
    date.setDate(monday.getDate() + offset);
    const pythonWeekday = offset;
    const horaire = openByWeekday.get(pythonWeekday);
    if (!horaire) continue;
    return {
      date: date.toISOString().slice(0, 10),
      heure: normalizeHour(horaire.heure_ouverture),
    };
  }

  return fallback;
}

async function fetchAuthToken(request) {
  const response = await request.post('/api/auth/login', {
    form: {
      username: E2E_USERNAME,
      password: E2E_PASSWORD,
    },
  });

  expect(response.ok()).toBeTruthy();
  const payload = await response.json();
  return payload.access_token;
}

async function loginThroughUi(page) {
  await page.goto('/');
  await expect(page.locator('#login-screen')).toBeVisible();

  await page.locator('#login-user').fill(E2E_USERNAME);
  await page.locator('#login-pass').fill(E2E_PASSWORD);
  await page.getByRole('button', { name: 'Connexion' }).click();

  await expect(page.locator('#login-screen')).toBeHidden({ timeout: 15_000 });
  await expect(page.locator('#app-container')).toBeVisible();
}

test.describe('Planning client deeplink', () => {
  test.skip(!E2E_USERNAME || !E2E_PASSWORD, 'Define E2E_USERNAME and E2E_PASSWORD to run authenticated planning tests.');

  test('opens the linked client sheet from a planning rendez-vous modal', async ({ page, request }) => {
    const accessToken = await fetchAuthToken(request);
    const suffix = String(Date.now()).slice(-6);
    const clientNom = `E2E-${suffix}`;
    const clientPrenom = 'Planning';
    const intervention = `E2E Deeplink ${suffix}`;
    const plaque = `E2E${suffix}`;
    const slot = await choosePlanningSlot(request, accessToken);

    const creationResponse = await request.post('/api/rendez-vous', {
      headers: {
        Authorization: `Bearer ${accessToken}`,
        'Content-Type': 'application/json',
      },
      data: {
        client: {
          nom: clientNom,
          prenom: clientPrenom,
          telephone: `06${suffix.padStart(8, '0')}`,
        },
        vehicule: {
          plaque,
          marque: 'Yamaha',
          modele: 'MT-07',
        },
        date_rdv: slot.date,
        heure_rdv: slot.heure,
        type_intervention: intervention,
        commentaire: 'Smoke test Playwright planning -> client',
      },
    });

    const creationBody = await creationResponse.text();
    expect(creationResponse.ok(), `RDV creation failed: ${creationResponse.status()} ${creationBody}`).toBeTruthy();

    await loginThroughUi(page);
    await page.getByRole('button', { name: /planning/i }).click();
    await expect(page.locator('#s-planning')).toBeVisible();

    const rdvBlock = page.locator(`.rdv-block[title*="${intervention}"]`).first();
    await expect(rdvBlock).toBeVisible({ timeout: 20_000 });
    await rdvBlock.click();

    const clientButton = page.getByRole('button', { name: /Voir la fiche client/i });
    await expect(clientButton).toBeVisible();
    await clientButton.click();

    const detailPanel = page.locator('#client-detail-panel');
    await expect(page.locator('#s-clients')).toBeVisible({ timeout: 10_000 });
    await expect(detailPanel).toBeVisible({ timeout: 10_000 });
    await expect(detailPanel).toContainText(clientNom);
  });
});
