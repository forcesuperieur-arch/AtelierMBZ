import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';

/**
 * Lot A — « Tuer les appels c'est prêt ? » :
 *  - historique d'états + timeline espace client en langage humain
 *  - notifications client à chaque étape + interrupteurs par étape (config atelier)
 *  - photos d'intervention servies par endpoint authentifié (isolation)
 *  - travaux supplémentaires : décision en ligne (signature), OR complémentaire,
 *    notification staff, relance H+4
 * Compte client : jean.moreau@email.fr — RDV seedé 269 (remis en état à chaque test).
 */
const PORTAL = 'http://localhost:81/client';
const API = 'http://localhost:81';
const MAILHOG = 'http://localhost:8025';
const CLIENT_EMAIL = 'jean.moreau@email.fr';
const CLIENT_PASSWORD = process.env.CLIENT_TEST_PASSWORD || 'ClientTest123!';
const RDV_ID = 269;
const PSQL = 'docker compose exec -T db psql -U atelier -d atelier_moto -tA -c';

function sql(query) {
  return execSync(`${PSQL} "${query.replace(/"/g, '\\"')}"`, { encoding: 'utf8' }).trim();
}

function adminAuthHeaders() {
  const state = JSON.parse(readFileSync('playwright/.auth/admin.json', 'utf8'));
  const token = state.cookies.find((c) => c.name === 'access_token')?.value;
  return { Authorization: `Bearer ${token}` };
}

async function loginAsClient(page) {
  await page.goto(`${PORTAL}/login`);
  await page.fill('input[type="email"]', CLIENT_EMAIL);
  await page.fill('input[type="password"]', CLIENT_PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/client\/?$/, { timeout: 15000 });
}

async function mailhogCount(request, query) {
  const res = await request.get(`${MAILHOG}/api/v2/search?kind=containing&query=${encodeURIComponent(query)}`);
  return (await res.json()).total;
}

/** Demande de travaux supp prête pour décision client, envoyée il y a 5 h. */
function seedDemande(description) {
  const token = 'e2e' + randomBytes(31).toString('hex').slice(0, 61);
  const prestations = JSON.stringify([
    { designation: 'Remplacement plaquettes avant', prix_ttc: '89.00', temps_minutes: 30 },
  ]).replace(/'/g, "''");
  // INSERT puis SELECT séparés : avec RETURNING, psql -tA ajoute le tag « INSERT 0 1 » à la sortie
  sql(
    `INSERT INTO demandes_travaux_supp (rendez_vous_id, description, urgence, statut, prestations_choisies, prix_estime, temps_estime, token_validation, created_at, sent_at)
     VALUES (${RDV_ID}, '${description}', 'normal', 'en_attente_decision_client', '${prestations}', 89.00, 30, '${token}', NOW(), NOW() - interval '5 hours')`,
  );
  return sql(`SELECT id FROM demandes_travaux_supp WHERE token_validation = '${token}'`);
}

/** Détache puis supprime les demandes E2E (l'OR complémentaire les référence). */
function cleanDemandes() {
  sql(`DELETE FROM notifications WHERE type = 'demande_decision_client' AND related_entity_id IN (SELECT id FROM demandes_travaux_supp WHERE description LIKE 'E2E-LOTA%')`);
  sql(`UPDATE demandes_travaux_supp SET or_complementaire_id = NULL WHERE description LIKE 'E2E-LOTA%'`);
  sql(`DELETE FROM ordres_reparation WHERE demande_travaux_supp_id IN (SELECT id FROM demandes_travaux_supp WHERE description LIKE 'E2E-LOTA%')`);
  sql(`DELETE FROM demandes_travaux_supp WHERE description LIKE 'E2E-LOTA%'`);
}

test.describe('Lot A — timeline, notifications par étape, travaux supp en ligne', () => {
  test.beforeAll(() => {
    // Données résiduelles d'exécutions précédentes
    cleanDemandes();
    sql(`DELETE FROM photos_intervention WHERE filename LIKE 'e2e-lota%'`);
  });

  test('une transition workflow trace l\'historique et notifie le client par email', async ({ request }) => {
    sql(`UPDATE rendez_vous SET statut = 'en_attente_pieces', annulation_demandee_at = NULL WHERE id = ${RDV_ID}`);
    sql(`DELETE FROM rdv_statut_historique WHERE rendez_vous_id = ${RDV_ID}`);
    const logsBefore = Number(sql(`SELECT COUNT(*) FROM notification_logs WHERE template_code = 'travaux_demarres' AND channel = 'email'`));
    const mailsBefore = await mailhogCount(request, 'sur le pont');

    const res = await request.post(`/api/rendez-vous/${RDV_ID}/transition/reprendre_apres_pieces`, {
      headers: adminAuthHeaders(),
    });
    expect(res.status()).toBe(200);

    // Historique écrit avec le bon statut d'arrivée
    expect(sql(`SELECT statut FROM rdv_statut_historique WHERE rendez_vous_id = ${RDV_ID} AND transition = 'reprendre_apres_pieces'`)).toBe('en_cours');

    // Dispatch synchrone tracé…
    expect(Number(sql(`SELECT COUNT(*) FROM notification_logs WHERE template_code = 'travaux_demarres' AND channel = 'email'`))).toBe(logsBefore + 1);

    // …et email réellement délivré (worker async lent en dev : même fenêtre
    // de 90 s que notifications-p0.spec)
    await expect.poll(() => mailhogCount(request, 'sur le pont'), { timeout: 90000, intervals: [2000] }).toBeGreaterThan(mailsBefore);
  });

  test('interrupteur par étape : email coupé, historique conservé', async ({ request }) => {
    sql(`UPDATE config_atelier SET notifications_etapes = '{"travaux_demarres": false}' WHERE atelier_id = 1`);
    sql(`UPDATE rendez_vous SET statut = 'en_attente_pieces' WHERE id = ${RDV_ID}`);
    const logsBefore = Number(sql(`SELECT COUNT(*) FROM notification_logs WHERE template_code = 'travaux_demarres'`));
    const histoBefore = Number(sql(`SELECT COUNT(*) FROM rdv_statut_historique WHERE rendez_vous_id = ${RDV_ID}`));

    try {
      const res = await request.post(`/api/rendez-vous/${RDV_ID}/transition/reprendre_apres_pieces`, {
        headers: adminAuthHeaders(),
      });
      expect(res.status()).toBe(200);

      // L'historique avance toujours…
      expect(Number(sql(`SELECT COUNT(*) FROM rdv_statut_historique WHERE rendez_vous_id = ${RDV_ID}`))).toBe(histoBefore + 1);

      // …mais aucun dispatch pour cette étape (le log est écrit en synchrone)
      expect(Number(sql(`SELECT COUNT(*) FROM notification_logs WHERE template_code = 'travaux_demarres'`))).toBe(logsBefore);
    } finally {
      sql(`UPDATE config_atelier SET notifications_etapes = NULL WHERE atelier_id = 1`);
    }
  });

  test('timeline espace client : étapes en langage humain, jamais de jargon', async ({ page }) => {
    // L'historique des deux tests précédents existe (reprendre_apres_pieces ×2)
    await loginAsClient(page);
    await page.goto(`${PORTAL}/rdvs/${RDV_ID}`);

    const timeline = page.getByTestId('rdv-timeline');
    await expect(timeline).toBeVisible({ timeout: 15000 });
    await expect(timeline).toContainText('Demande enregistrée');
    await expect(timeline).toContainText('Travaux en cours');
    await expect(timeline).not.toContainText(/en_cours|reprendre_apres_pieces|en_attente_pieces/);
  });

  test('photos : servies au propriétaire via endpoint authentifié, 404 pour autrui', async ({ page }) => {
    execSync(`docker compose exec -T php sh -c "mkdir -p var/photos && echo fake-jpg > var/photos/e2e-lota.jpg"`);
    sql(`INSERT INTO photos_intervention (rendez_vous_id, atelier_id, filename, created_at) VALUES (${RDV_ID}, 1, 'e2e-lota.jpg', NOW())`);
    sql(`INSERT INTO photos_intervention (rendez_vous_id, atelier_id, filename, created_at) VALUES (2, 1, 'e2e-lota-foreign.jpg', NOW())`);
    const ownPhotoId = sql(`SELECT id FROM photos_intervention WHERE filename = 'e2e-lota.jpg'`);
    const foreignPhotoId = sql(`SELECT id FROM photos_intervention WHERE filename = 'e2e-lota-foreign.jpg'`);

    try {
      await loginAsClient(page);

      const own = await page.request.get(`${API}/api/client/photos/${ownPhotoId}`);
      expect(own.status()).toBe(200);

      const foreign = await page.request.get(`${API}/api/client/photos/${foreignPhotoId}`);
      expect(foreign.status()).toBe(404);

      // La page détail affiche la photo via l'endpoint authentifié (plus de /uploads brut)
      await page.goto(`${PORTAL}/rdvs/${RDV_ID}`);
      const img = page.locator('[data-testid="rdv-photos"] img').first();
      await expect(img).toBeVisible({ timeout: 15000 });
      expect(await img.getAttribute('src')).toContain('/api/client/photos/');
    } finally {
      sql(`DELETE FROM photos_intervention WHERE id IN (${ownPhotoId}, ${foreignPhotoId})`);
    }
  });

  test('travaux supp : acceptation en ligne avec signature → OR complémentaire + notification staff', async ({ page }) => {
    const demandeId = seedDemande('E2E-LOTA accept — plaquettes avant HS');

    await loginAsClient(page);
    await page.goto(`${PORTAL}/rdvs/${RDV_ID}`);

    const bloc = page.getByTestId('demandes-travaux');
    await expect(bloc).toBeVisible({ timeout: 15000 });
    await expect(bloc).toContainText('En attente de votre décision');
    await expect(bloc).toContainText('89.00');

    // Ouvre la modale et signe au canvas
    await page.getByTestId('btn-accepter-travaux').first().click();
    const canvas = page.locator('.sig-canvas');
    await expect(canvas).toBeVisible();
    const box = await canvas.boundingBox();
    await page.mouse.move(box.x + 40, box.y + 60);
    await page.mouse.down();
    await page.mouse.move(box.x + 180, box.y + 110, { steps: 12 });
    await page.mouse.move(box.x + 320, box.y + 70, { steps: 12 });
    await page.mouse.up();

    const postResponse = page.waitForResponse(
      (res) => res.url().includes(`/demandes-travaux-supp/${demandeId}/decision`) && res.request().method() === 'POST',
    );
    await page.locator('.sig-modal').getByRole('button', { name: /accepter et signer/i }).click();
    expect((await postResponse).status()).toBe(200);

    // Persistance : statut, signature horodatée, OR complémentaire créé
    expect(sql(`SELECT statut FROM demandes_travaux_supp WHERE id = ${demandeId}`)).toBe('accepte');
    expect(sql(`SELECT signed_at IS NOT NULL AND decision_ip IS NOT NULL FROM demandes_travaux_supp WHERE id = ${demandeId}`)).toBe('t');
    expect(sql(`SELECT or_complementaire_id IS NOT NULL FROM demandes_travaux_supp WHERE id = ${demandeId}`)).toBe('t');

    // Le staff est notifié de la décision
    expect(Number(sql(`SELECT COUNT(*) FROM notifications WHERE type = 'demande_decision_client' AND related_entity_id = ${demandeId}`))).toBe(1);

    // L'UI reflète la décision
    await expect(bloc).toContainText(/acceptés/i, { timeout: 10000 });
  });

  test('travaux supp : refus en ligne sans signature', async ({ page }) => {
    const demandeId = seedDemande('E2E-LOTA refus — pneu arrière');

    await loginAsClient(page);
    await page.goto(`${PORTAL}/rdvs/${RDV_ID}`);
    await expect(page.getByTestId('demandes-travaux')).toBeVisible({ timeout: 15000 });

    page.on('dialog', (dialog) => dialog.accept());
    const postResponse = page.waitForResponse(
      (res) => res.url().includes(`/demandes-travaux-supp/${demandeId}/decision`) && res.request().method() === 'POST',
    );
    await page.getByTestId('btn-refuser-travaux').first().click();
    expect((await postResponse).status()).toBe(200);

    expect(sql(`SELECT statut FROM demandes_travaux_supp WHERE id = ${demandeId}`)).toBe('refuse');
    expect(sql(`SELECT or_complementaire_id IS NULL FROM demandes_travaux_supp WHERE id = ${demandeId}`)).toBe('t');
    expect(Number(sql(`SELECT COUNT(*) FROM notifications WHERE type = 'demande_decision_client' AND related_entity_id = ${demandeId}`))).toBe(1);
  });

  test('isolation : décision impossible sur la demande d\'un autre client', async ({ page }) => {
    const demandeId = seedDemande('E2E-LOTA isolation');
    sql(`UPDATE demandes_travaux_supp SET rendez_vous_id = 2 WHERE id = ${demandeId}`);

    await loginAsClient(page);
    const res = await page.request.post(`${API}/api/client/demandes-travaux-supp/${demandeId}/decision`, {
      data: { decision: 'refuse' },
    });
    expect(res.status()).toBe(404);
    expect(sql(`SELECT statut FROM demandes_travaux_supp WHERE id = ${demandeId}`)).toBe('en_attente_decision_client');
  });

  test('relance H+4 : une seule relance par demande, email envoyé', async ({ request }) => {
    const hour = new Date().getHours();
    test.skip(hour < 8 || hour >= 19, 'Hors fenêtre d\'envoi 8h-19h (comportement voulu de la commande)');

    const demandeId = seedDemande('E2E-LOTA relance');

    const out1 = execSync('docker compose exec -T php php bin/console app:relance-demandes-travaux', { encoding: 'utf8' });
    expect(out1).toMatch(/[1-9]\d* relance\(s\) envoyée/);
    expect(sql(`SELECT relance_at IS NOT NULL FROM demandes_travaux_supp WHERE id = ${demandeId}`)).toBe('t');
    // Dispatch tracé en synchrone, ciblé sur CETTE demande (d'autres demandes
    // en attente peuvent légitimement être relancées par le même passage)
    expect(Number(sql(`SELECT COUNT(*) FROM notification_logs WHERE template_code = 'demande_relance' AND channel = 'email' AND related_entity_id = ${demandeId}`))).toBe(1);

    // Une demande déjà relancée ne l'est jamais deux fois
    const out2 = execSync('docker compose exec -T php php bin/console app:relance-demandes-travaux', { encoding: 'utf8' });
    expect(out2).toContain('0 relance(s)');
  });

  test.afterAll(() => {
    cleanDemandes();
    sql(`UPDATE rendez_vous SET statut = 'confirme' WHERE id = ${RDV_ID}`);
  });
});
