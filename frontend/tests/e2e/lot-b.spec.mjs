import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';

/**
 * Lot B — « zéro litige » : check-in / état des lieux d'entrée.
 *  - flux staff : brouillon → 4 photos d'entrée → signature client → PDF figé (hash)
 *  - gardes workflow : transition 'reception' bloquée sans EDL signé (toggle atelier)
 *  - exposition client : portail (bloc + PDF, isolation), suivi public tokenisé
 *  - restitution : comparatif entrée/sortie + signalement de litige (notification staff)
 *  - email « travaux terminés » : lien EDL, jamais de placeholder littéral
 *  - KPI pilote : origine des RDV (booking web), litiges restitution
 * Compte staff : admin@atelier.local — compte client : jean.moreau@email.fr (client 1).
 * Données préfixées E2E-LOTB, seeds idempotents (purge au démarrage, pattern lot-a).
 */

const PORTAL = 'http://localhost:81/client';
const API = 'http://localhost:81';
const MAILHOG = 'http://localhost:8025';
const CLIENT_EMAIL = 'jean.moreau@email.fr';
const CLIENT_PASSWORD = process.env.CLIENT_TEST_PASSWORD || 'ClientTest123!';
const CLIENT_ID = 1; // jean.moreau
const ATELIER_ID = 1;
const PSQL = 'docker compose exec -T db psql -U atelier -d atelier_moto -tA -c';

// Pixel transparent : dataURL de signature valide + buffer PNG pour les uploads
const SIGNATURE_DATA_URL =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
const PNG_BUFFER = Buffer.from(SIGNATURE_DATA_URL.split(',')[1], 'base64');

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

/* ───────────────────────── Seeds SQL (idempotents) ───────────────────────── */

let vehiculeId;
// RDVs seedés : { id, token } par scénario
const rdv = {};

function newToken() {
  return 'e2elotb' + randomBytes(24).toString('hex').slice(0, 40);
}

function seedRdv(label, { statut = 'confirme', clientId = CLIENT_ID, heure = '09:00' } = {}) {
  const token = newToken();
  // INSERT puis SELECT séparés : avec RETURNING, psql -tA pollue la sortie du tag
  sql(
    `INSERT INTO rendez_vous (atelier_id, date_rdv, heure_rdv, type_intervention, statut, token_suivi, client_id, vehicule_id, temps_estime, created_at)
     VALUES (${ATELIER_ID}, CURRENT_DATE, '${heure}', 'E2E-LOTB ${label}', '${statut}', '${token}', ${clientId}, ${vehiculeId}, 60, NOW())`,
  );
  return { id: Number(sql(`SELECT id FROM rendez_vous WHERE token_suivi = '${token}'`)), token };
}

/** Photos SQL sans fichier disque (suffisant tant qu'on n'affiche pas le binaire). */
function seedPhotos(rdvId, type, count, prefix) {
  for (let i = 1; i <= count; i++) {
    sql(
      `INSERT INTO photos_intervention (rendez_vous_id, atelier_id, filename, type, created_at, taken_at)
       VALUES (${rdvId}, ${ATELIER_ID}, 'e2e-lotb-${prefix}-${i}.png', '${type}', NOW(), NOW())`,
    );
  }
}

/** OR initial avec signatures de réception posées (garde ② de la transition reception). */
function seedOrReceptionSignee(rdvId, numero) {
  sql(
    `INSERT INTO ordres_reparation (numero_or, type_or, statut, mechanic_checkup, rendez_vous_id, signature_client, signature_atelier_reception)
     VALUES ('${numero}', 'initial', 'reception_signee', '{}', ${rdvId}, '${SIGNATURE_DATA_URL}', '${SIGNATURE_DATA_URL}')`,
  );
}

/** OR prêt pour la restitution publique (statut intervention_signee + rapport). */
function seedOrInterventionSignee(rdvId, numero) {
  sql(
    `INSERT INTO ordres_reparation (numero_or, type_or, statut, mechanic_checkup, rendez_vous_id, signature_client, signature_atelier_reception, signature_mecanicien, signe_mecanicien_at, travaux_realises, kilometrage_restitution)
     VALUES ('${numero}', 'initial', 'intervention_signee', '{}', ${rdvId}, '${SIGNATURE_DATA_URL}', '${SIGNATURE_DATA_URL}', '${SIGNATURE_DATA_URL}', NOW(), 'E2E-LOTB vidange moteur et plaquettes avant', 25150)`,
  );
}

/** Essai routier valide (garde de la transition terminer). */
function seedEssaiValide(rdvId) {
  sql(
    `INSERT INTO essai_routier (rendez_vous_id, atelier_id, statut, km_debut, km_fin, validated_at, created_at)
     VALUES (${rdvId}, ${ATELIER_ID}, 'valide', 25100, 25110, NOW(), NOW())`,
  );
}

/** Purge complète des données E2E-LOTB (RDV seedés + RDV du booking KPI). */
function cleanE2EData() {
  const mine = `SELECT id FROM rendez_vous WHERE type_intervention LIKE 'E2E-LOTB%' OR client_id IN (SELECT id FROM clients WHERE email LIKE 'e2e-lotb%')`;
  sql(`DELETE FROM notifications WHERE related_entity_type = 'RendezVous' AND related_entity_id IN (${mine})`);
  sql(`DELETE FROM analytics_rdv_facts WHERE rdv_id IN (${mine})`);
  sql(`DELETE FROM photos_intervention WHERE rendez_vous_id IN (${mine})`);
  sql(`DELETE FROM etat_des_lieux WHERE rendez_vous_id IN (${mine})`);
  sql(`DELETE FROM ordres_reparation WHERE rendez_vous_id IN (${mine})`);
  sql(`DELETE FROM essai_routier WHERE rendez_vous_id IN (${mine})`);
  sql(`DELETE FROM rendez_vous WHERE id IN (${mine})`);
  sql(`DELETE FROM vehicules WHERE plaque LIKE 'E2E-LOTB%'`);
  sql(`DELETE FROM clients WHERE email LIKE 'e2e-lotb%'`);
}

/* ───────────────────────── Helpers API / MailHog ───────────────────────── */

async function postDraft(request, rdvId, data) {
  return request.post(`/api/rendez-vous/${rdvId}/etat-des-lieux`, { headers: adminAuthHeaders(), data });
}

async function postSign(request, rdvId) {
  return request.post(`/api/rendez-vous/${rdvId}/etat-des-lieux/sign`, {
    headers: adminAuthHeaders(),
    data: { signature: SIGNATURE_DATA_URL },
  });
}

/** Brouillon + signature, tolérant au re-run (DEJA_SIGNE) : chaque test reste autonome. */
async function ensureEdlSigned(request, rdvId, data) {
  const draft = await postDraft(request, rdvId, data);
  if (![200, 201].includes(draft.status())) {
    expect((await draft.json()).code).toBe('DEJA_SIGNE');
  }
  const sign = await postSign(request, rdvId);
  if (sign.status() !== 200) {
    expect((await sign.json()).code).toBe('DEJA_SIGNE');
  }
}

async function mailhogTo(request, recipient) {
  const res = await request.get(`${MAILHOG}/api/v2/search?kind=to&query=${encodeURIComponent(recipient)}&limit=50`);
  return (await res.json()).items ?? [];
}

/** Décodage quoted-printable (corps) : suffisant pour chercher des marqueurs ASCII. */
function decodeQP(raw) {
  return String(raw ?? '')
    .replace(/=\r?\n/g, '')
    .replace(/=([0-9A-Fa-f]{2})/g, (_, h) => String.fromCharCode(parseInt(h, 16)));
}

/** Décodage RFC 2047 des sujets (=?utf-8?B?…?= / =?utf-8?Q?…?=). */
function decodeSubject(value) {
  return String(value ?? '').replace(/=\?utf-8\?([bq])\?([^?]*)\?=/gi, (_, enc, payload) =>
    enc.toLowerCase() === 'b' ? Buffer.from(payload, 'base64').toString('utf8') : decodeQP(payload).replace(/_/g, ' '),
  );
}

async function findMailWithBodyMarker(request, marker) {
  const items = await mailhogTo(request, CLIENT_EMAIL);
  return items.find((m) => decodeQP(m.Content?.Body).includes(marker)) ?? null;
}

/* ═════════════════════════════ Tests ═════════════════════════════ */

test.describe('Lot B — check-in / état des lieux', () => {
  test.beforeAll(() => {
    cleanE2EData();
    sql(`UPDATE config_atelier SET checkin_obligatoire = true WHERE atelier_id = ${ATELIER_ID}`);

    sql(`INSERT INTO vehicules (atelier_id, plaque, marque, modele, client_id) VALUES (${ATELIER_ID}, 'E2E-LOTB-V1', 'Yamaha', 'MT-07', ${CLIENT_ID})`);
    vehiculeId = Number(sql(`SELECT id FROM vehicules WHERE plaque = 'E2E-LOTB-V1'`));

    // Client tiers pour le test d'isolation (aucun mot de passe nécessaire)
    sql(`INSERT INTO clients (atelier_id, nom, prenom, telephone, email, created_at) VALUES (${ATELIER_ID}, 'Isolation', 'E2eLotb', '0600000001', 'e2e-lotb-other@example.fr', NOW())`);
    const otherClientId = Number(sql(`SELECT id FROM clients WHERE email = 'e2e-lotb-other@example.fr'`));

    rdv.fluxApi = seedRdv('flux API');
    rdv.validations = seedRdv('validations');

    rdv.garde = seedRdv('garde reception');
    seedOrReceptionSignee(rdv.garde.id, 'E2E-LOTB-OR-GARDE');
    seedPhotos(rdv.garde.id, 'reception', 4, 'garde');

    rdv.toggle = seedRdv('toggle config');
    seedOrReceptionSignee(rdv.toggle.id, 'E2E-LOTB-OR-TOGGLE');
    seedPhotos(rdv.toggle.id, 'reception', 4, 'toggle');

    rdv.accueil = seedRdv('accueil du jour', { heure: '10:30' });

    rdv.portail = seedRdv('portail EDL', { statut: 'reception' });
    seedPhotos(rdv.portail.id, 'checkin', 4, 'portail');

    rdv.isolation = seedRdv('isolation autre client', { statut: 'reception', clientId: otherClientId });

    rdv.restitution = seedRdv('restitution litige', { statut: 'termine' });
    seedOrInterventionSignee(rdv.restitution.id, 'E2E-LOTB-OR-RESTIT');
    seedPhotos(rdv.restitution.id, 'checkin', 4, 'restit-in');
    seedPhotos(rdv.restitution.id, 'restitution', 2, 'restit-out');

    rdv.restitutionSansEdl = seedRdv('restitution sans EDL', { statut: 'termine' });
    seedOrInterventionSignee(rdv.restitutionSansEdl.id, 'E2E-LOTB-OR-RESTIT2');

    rdv.emailAvecEdl = seedRdv('email avec EDL', { statut: 'en_cours' });
    seedPhotos(rdv.emailAvecEdl.id, 'checkin', 4, 'mail-in');
    seedPhotos(rdv.emailAvecEdl.id, 'apres_travaux', 2, 'mail-post');
    seedEssaiValide(rdv.emailAvecEdl.id);

    rdv.emailSansEdl = seedRdv('email sans EDL', { statut: 'en_cours' });
    seedPhotos(rdv.emailSansEdl.id, 'apres_travaux', 2, 'mail2-post');
    seedEssaiValide(rdv.emailSansEdl.id);
  });

  test.afterAll(() => {
    // Filet de sécurité : le toggle atelier ne doit JAMAIS rester désactivé
    sql(`UPDATE config_atelier SET checkin_obligatoire = true WHERE atelier_id = ${ATELIER_ID}`);
  });

  /* ────────────── 1. API staff : flux check-in complet ────────────── */

  test('flux check-in API : brouillon → photos → signature → PDF figé → intégrité', async ({ request }) => {
    const id = rdv.fluxApi.id;

    // État vierge
    const initial = await request.get(`/api/rendez-vous/${id}/etat-des-lieux`, { headers: adminAuthHeaders() });
    expect(initial.status()).toBe(200);
    expect(await initial.json()).toMatchObject({ exists: false, signe: false, photos_entree_count: 0 });

    // Brouillon créé (201) puis mis à jour (200)
    const created = await postDraft(request, id, { kilometrage: 25100, niveau_carburant: 'moitie', observations: 'E2E-LOTB rayure reservoir cote droit' });
    expect(created.status()).toBe(201);
    expect(await created.json()).toMatchObject({ exists: true, signe: false, kilometrage: 25100, niveau_carburant: 'moitie' });

    const updated = await postDraft(request, id, { observations: 'E2E-LOTB rayure reservoir cote droit + retro gauche raye' });
    expect(updated.status()).toBe(200);

    // Signature refusée sans photos d'entrée
    const noPhotos = await postSign(request, id);
    expect(noPhotos.status()).toBe(400);
    expect(await noPhotos.json()).toMatchObject({ code: 'PHOTOS_MANQUANTES', missing: 4 });

    // 4 vrais uploads type checkin (multipart)
    for (let i = 1; i <= 4; i++) {
      const upload = await request.post('/api/photos/upload', {
        headers: adminAuthHeaders(),
        multipart: {
          photo: { name: `e2e-lotb-upload-${i}.png`, mimeType: 'image/png', buffer: PNG_BUFFER },
          rendez_vous_id: String(id),
          type: 'checkin',
        },
      });
      expect(upload.status()).toBe(201);
    }
    const withPhotos = await request.get(`/api/rendez-vous/${id}/etat-des-lieux`, { headers: adminAuthHeaders() });
    expect((await withPhotos.json()).photos_entree_count).toBe(4);

    // Signature OK : hash figé + PDF archivé
    const signed = await postSign(request, id);
    expect(signed.status()).toBe(200);
    const signedBody = await signed.json();
    expect(signedBody.success).toBe(true);
    expect(signedBody.signed_hash).toMatch(/^[0-9a-f]{64}$/);
    expect(signedBody).toMatchObject({ signe: true, pdf_disponible: true });
    const edlId = signedBody.id;

    // PDF servi depuis l'archive
    const pdf = await request.get(`/api/etat-des-lieux/${edlId}/pdf`, { headers: adminAuthHeaders() });
    expect(pdf.status()).toBe(200);
    expect(pdf.headers()['content-type']).toContain('application/pdf');

    // Intégrité du document figé
    const integrity = await request.get(`/api/etat-des-lieux/${edlId}/verify-integrity`, { headers: adminAuthHeaders() });
    expect(integrity.status()).toBe(200);
    const integrityBody = await integrity.json();
    expect(integrityBody).toMatchObject({ signed: true, valid: true });
    expect(integrityBody.computed_hash).toBe(integrityBody.stored_hash);

    // Document gelé : plus aucune écriture possible
    const reSign = await postSign(request, id);
    expect(reSign.status()).toBe(400);
    expect((await reSign.json()).code).toBe('DEJA_SIGNE');
    const reDraft = await postDraft(request, id, { kilometrage: 999 });
    expect(reDraft.status()).toBe(400);
    expect((await reDraft.json()).code).toBe('DEJA_SIGNE');

    // Persistance + trace d'audit
    expect(sql(`SELECT signed_hash IS NOT NULL AND pdf_filename IS NOT NULL FROM etat_des_lieux WHERE rendez_vous_id = ${id}`)).toBe('t');
    expect(Number(sql(`SELECT COUNT(*) FROM audit_logs WHERE action = 'sign_etat_des_lieux' AND entity_id = ${edlId}`))).toBeGreaterThanOrEqual(1);
  });

  test('validations : kilométrage négatif, carburant inconnu, signature sans données', async ({ request }) => {
    const id = rdv.validations.id;

    // Signature sans brouillon → données incomplètes
    const noDraft = await postSign(request, id);
    expect(noDraft.status()).toBe(400);
    expect((await noDraft.json()).code).toBe('DONNEES_INCOMPLETES');

    const kmNegatif = await postDraft(request, id, { kilometrage: -1, niveau_carburant: 'plein' });
    expect(kmNegatif.status()).toBe(400);
    expect((await kmNegatif.json()).code).toBe('KILOMETRAGE_INVALIDE');

    const carburantInvalide = await postDraft(request, id, { kilometrage: 100, niveau_carburant: 'diesel' });
    expect(carburantInvalide.status()).toBe(400);
    expect((await carburantInvalide.json()).code).toBe('CARBURANT_INVALIDE');

    // Brouillon partiel accepté (km seul)… mais signature refusée tant que le carburant manque
    const partiel = await postDraft(request, id, { kilometrage: 100 });
    expect([200, 201]).toContain(partiel.status());
    const signPartiel = await postSign(request, id);
    expect(signPartiel.status()).toBe(400);
    expect((await signPartiel.json()).code).toBe('DONNEES_INCOMPLETES');
  });

  /* ────────────── 2. Gardes workflow ────────────── */

  test('transition reception : bloquée sans EDL signé (ETAT_DES_LIEUX_REQUIS), passe après signature', async ({ request }) => {
    const id = rdv.garde.id;

    // OR réception signé + 4 photos, mais pas d'EDL → blocage garde ③
    const blocked = await request.post(`/api/rendez-vous/${id}/transition/reception`, { headers: adminAuthHeaders() });
    expect(blocked.status()).toBe(400);
    expect((await blocked.json()).code).toBe('ETAT_DES_LIEUX_REQUIS');
    expect(sql(`SELECT statut FROM rendez_vous WHERE id = ${id}`)).toBe('confirme');

    // EDL signé (les 4 photos type reception comptent comme photos d'entrée)
    await ensureEdlSigned(request, id, { kilometrage: 41200, niveau_carburant: 'quart' });

    const allowed = await request.post(`/api/rendez-vous/${id}/transition/reception`, { headers: adminAuthHeaders() });
    expect(allowed.status()).toBe(200);
    expect((await allowed.json()).statut).toBe('reception');
    expect(sql(`SELECT statut FROM rendez_vous WHERE id = ${id}`)).toBe('reception');
  });

  test('toggle checkin_obligatoire=false : la réception passe sans EDL (puis remis à true)', async ({ request }) => {
    const id = rdv.toggle.id;

    const disable = await request.put('/api/config', { headers: adminAuthHeaders(), data: { checkin_obligatoire: false } });
    expect(disable.status()).toBe(200);
    try {
      expect(sql(`SELECT checkin_obligatoire FROM config_atelier WHERE atelier_id = ${ATELIER_ID}`)).toBe('f');

      const res = await request.post(`/api/rendez-vous/${id}/transition/reception`, { headers: adminAuthHeaders() });
      expect(res.status()).toBe(200);
      expect(sql(`SELECT statut FROM rendez_vous WHERE id = ${id}`)).toBe('reception');
      expect(sql(`SELECT COUNT(*) FROM etat_des_lieux WHERE rendez_vous_id = ${id}`)).toBe('0');
    } finally {
      const restore = await request.put('/api/config', { headers: adminAuthHeaders(), data: { checkin_obligatoire: true } });
      expect(restore.status()).toBe(200);
    }
    expect(sql(`SELECT checkin_obligatoire FROM config_atelier WHERE atelier_id = ${ATELIER_ID}`)).toBe('t');
  });

  /* ────────────── 3. UI staff /reception ────────────── */

  test.describe('page staff /reception', () => {
    test.use({ storageState: 'playwright/.auth/admin.json' });

    test('liste les RDV du jour avec badge état des lieux « À faire »', async ({ page }) => {
      await page.goto('/reception');
      await expect(page.getByTestId('reception-page')).toBeVisible({ timeout: 15000 });

      const card = page.locator('[data-testid="reception-rdv-card"]').filter({ hasText: 'E2E-LOTB accueil du jour' });
      await expect(card).toBeVisible({ timeout: 15000 });
      await expect(card.getByTestId('edl-badge')).toHaveText(/À faire/);
      await expect(card.getByTestId('btn-checkin')).toBeVisible();
    });
  });

  /* ────────────── 4. Portail client ────────────── */

  test('portail client : bloc état des lieux (km, carburant, observations) + PDF téléchargeable', async ({ page, request }) => {
    await ensureEdlSigned(request, rdv.portail.id, {
      kilometrage: 25100,
      niveau_carburant: 'moitie',
      observations: 'E2E-LOTB rayure reservoir cote droit',
    });

    await loginAsClient(page);
    await page.goto(`${PORTAL}/rdvs/${rdv.portail.id}`);

    const bloc = page.getByTestId('rdv-etat-des-lieux');
    await expect(bloc).toBeVisible({ timeout: 15000 });
    await expect(bloc).toContainText('25 100 km');
    await expect(bloc).toContainText('1/2');
    await expect(bloc).toContainText('E2E-LOTB rayure reservoir cote droit');
    await expect(bloc.locator('a.pdf-btn.edl-pdf')).toBeVisible();

    const pdf = await page.request.get(`${API}/api/client/rdvs/${rdv.portail.id}/etat-des-lieux/pdf`);
    expect(pdf.status()).toBe(200);
    expect(pdf.headers()['content-type']).toContain('application/pdf');
  });

  test('portail client : pas de bloc sans EDL, et 404 sur le document d\'un autre client', async ({ page }) => {
    await loginAsClient(page);

    // RDV du client sans état des lieux → aucun bloc (on attend le détail chargé)
    await page.goto(`${PORTAL}/rdvs/${rdv.accueil.id}`);
    await expect(page.locator('.rdv-detail')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('.rdv-detail')).toContainText('E2E-LOTB accueil du jour');
    await expect(page.getByTestId('rdv-etat-des-lieux')).toHaveCount(0);

    // Isolation : le RDV d'un autre client est invisible, PDF compris
    const foreignRdv = await page.request.get(`${API}/api/client/rdvs/${rdv.isolation.id}`);
    expect(foreignRdv.status()).toBe(404);
    const foreignPdf = await page.request.get(`${API}/api/client/rdvs/${rdv.isolation.id}/etat-des-lieux/pdf`);
    expect(foreignPdf.status()).toBe(404);
  });

  /* ────────────── 5. Restitution publique ────────────── */

  test('restitution : payload complet + comparatif entrée/sortie affiché', async ({ page, request }) => {
    await ensureEdlSigned(request, rdv.restitution.id, {
      kilometrage: 25100,
      niveau_carburant: 'trois_quarts',
      observations: 'E2E-LOTB retro gauche raye au depot',
    });

    const res = await request.get(`/api/public/restitution/${rdv.restitution.token}`);
    expect(res.status()).toBe(200);
    const data = await res.json();
    expect(data.etat_des_lieux).toMatchObject({ signe: true, kilometrage: 25100, niveau_carburant_label: '3/4' });
    expect(data.etat_des_lieux.photos).toHaveLength(4);
    expect(data.photos_restitution).toHaveLength(2);

    await page.goto(`/restitution/${rdv.restitution.token}`);
    await expect(page.getByTestId('comparatif-edl')).toBeVisible({ timeout: 15000 });
    await expect(page.getByTestId('comparatif-entree')).toBeVisible();
    await expect(page.getByTestId('comparatif-sortie')).toBeVisible();
    // php -S sert /api/public/photos/*.png comme fichiers statiques : on compte
    // les miniatures (img OU fallback), jamais leur chargement binaire.
    await expect(page.locator('[data-testid="comparatif-entree"] .mini-thumb')).toHaveCount(4);
    await expect(page.locator('[data-testid="comparatif-sortie"] .mini-thumb')).toHaveCount(2);
    await expect(page.getByTestId('comparatif-entree')).toContainText('E2E-LOTB retro gauche raye au depot');
  });

  test('restitution : signature avec litige → persistance, notification staff, confirmation UI', async ({ page }) => {
    const id = rdv.restitution.id;

    await page.goto(`/restitution/${rdv.restitution.token}`);
    await expect(page.getByTestId('litige-checkbox')).toBeVisible({ timeout: 15000 });

    await page.getByTestId('litige-checkbox').check();
    await page.getByTestId('litige-commentaire').fill('E2E-LOTB rayure aile avant constatee au retrait');

    const canvas = page.locator('canvas').first();
    await canvas.scrollIntoViewIfNeeded();
    const box = await canvas.boundingBox();
    await page.mouse.move(box.x + 30, box.y + 60);
    await page.mouse.down();
    await page.mouse.move(box.x + 150, box.y + 90, { steps: 12 });
    await page.mouse.move(box.x + 260, box.y + 50, { steps: 12 });
    await page.mouse.up();

    const signResponse = page.waitForResponse(
      (res) => res.url().includes('/restitution/') && res.url().endsWith('/sign') && res.request().method() === 'POST',
    );
    await page.getByRole('button', { name: /valider|signer/i }).click();
    expect((await signResponse).status()).toBe(200);

    await expect(page.getByTestId('litige-confirmation')).toBeVisible({ timeout: 10000 });

    expect(sql(`SELECT litige_signale FROM rendez_vous WHERE id = ${id}`)).toBe('t');
    expect(sql(`SELECT litige_commentaire FROM rendez_vous WHERE id = ${id}`)).toContain('E2E-LOTB rayure aile avant');
    expect(
      Number(sql(`SELECT COUNT(*) FROM notifications WHERE type = 'litige_restitution' AND related_entity_type = 'RendezVous' AND related_entity_id = ${id}`)),
    ).toBe(1);
    expect(Number(sql(`SELECT COUNT(*) FROM audit_logs WHERE action = 'litige_restitution_signale' AND entity_id = ${id}`))).toBeGreaterThanOrEqual(1);
  });

  test('restitution : token sans EDL → pas de comparatif', async ({ page, request }) => {
    const res = await request.get(`/api/public/restitution/${rdv.restitutionSansEdl.token}`);
    expect(res.status()).toBe(200);
    expect((await res.json()).etat_des_lieux).toBeNull();

    await page.goto(`/restitution/${rdv.restitutionSansEdl.token}`);
    await expect(page.getByRole('heading', { name: /restitution du véhicule/i })).toBeVisible({ timeout: 15000 });
    await expect(page.getByTestId('comparatif-edl')).toHaveCount(0);
  });

  /* ────────────── 6. Suivi public tokenisé ────────────── */

  test('suivi public : l\'état des lieux signé est exposé avec ses photos', async ({ request }) => {
    await ensureEdlSigned(request, rdv.portail.id, { kilometrage: 25100, niveau_carburant: 'moitie' });

    const res = await request.get(`/api/public/suivi/token/${rdv.portail.token}`);
    expect(res.status()).toBe(200);
    const data = await res.json();
    expect(data.etat_des_lieux).toMatchObject({ signe: true, kilometrage: 25100, niveau_carburant: 'moitie' });
    expect(data.etat_des_lieux.photos).toHaveLength(4);
  });

  /* ────────────── 7. Email « travaux terminés » ────────────── */

  test('email travaux terminés avec EDL signé : lien espace client, pas de placeholder', async ({ request }) => {
    test.setTimeout(150000);
    const id = rdv.emailAvecEdl.id;
    await ensureEdlSigned(request, id, { kilometrage: 25100, niveau_carburant: 'plein' });

    const logsBefore = Number(sql(`SELECT COUNT(*) FROM notification_logs WHERE template_code = 'travaux_termines' AND channel = 'email' AND related_entity_id = ${id}`));

    const res = await request.post(`/api/rendez-vous/${id}/transition/terminer`, { headers: adminAuthHeaders() });
    expect(res.status()).toBe(200);

    // Dispatch synchrone tracé (déterministe), délivrance async ensuite
    expect(Number(sql(`SELECT COUNT(*) FROM notification_logs WHERE template_code = 'travaux_termines' AND channel = 'email' AND related_entity_id = ${id}`))).toBe(logsBefore + 1);

    const marker = `/client/rdvs/${id}`;
    await expect
      .poll(async () => (await findMailWithBodyMarker(request, marker)) !== null, { timeout: 90000, intervals: [2000] })
      .toBe(true);

    const mail = await findMailWithBodyMarker(request, marker);
    const body = decodeQP(mail.Content.Body);
    expect(body).toMatch(/tat des lieux/i); // « état des lieux » (é encodé UTF-8)
    expect(body).toContain(marker);
    expect(body).not.toContain('etat_des_lieux_bloc');
  });

  test('email travaux terminés sans EDL : jamais de placeholder littéral', async ({ request }) => {
    test.setTimeout(150000);
    const id = rdv.emailSansEdl.id;

    const beforeIds = new Set((await mailhogTo(request, CLIENT_EMAIL)).map((m) => m.ID));

    const res = await request.post(`/api/rendez-vous/${id}/transition/terminer`, { headers: adminAuthHeaders() });
    expect(res.status()).toBe(200);
    expect(Number(sql(`SELECT COUNT(*) FROM notification_logs WHERE template_code = 'travaux_termines' AND channel = 'email' AND related_entity_id = ${id}`))).toBe(1);

    // Le nouvel email « Votre moto est prête » de ce RDV (les emails des tests
    // précédents sont exclus par snapshot d'IDs + sujet)
    const findNewMail = async () => {
      const items = await mailhogTo(request, CLIENT_EMAIL);
      return items.find((m) => !beforeIds.has(m.ID) && /moto est pr/i.test(decodeSubject(m.Content?.Headers?.Subject?.[0]))) ?? null;
    };
    await expect.poll(async () => (await findNewMail()) !== null, { timeout: 90000, intervals: [2000] }).toBe(true);

    const body = decodeQP((await findNewMail()).Content.Body);
    expect(body).not.toContain('etat_des_lieux_bloc');
    expect(body).not.toContain('/client/rdvs/');
  });

  /* ────────────── 8. KPI pilote ────────────── */

  test('KPI : un booking public crée un RDV origine=web', async ({ request }) => {
    const today = new Date();
    const dateDebut = today.toISOString().slice(0, 10);
    const dateFin = new Date(today.getTime() + 20 * 24 * 3600 * 1000).toISOString().slice(0, 10);

    const slotsRes = await request.get(`/api/public/slots?atelier_id=${ATELIER_ID}&date_debut=${dateDebut}&date_fin=${dateFin}&temps_minutes=60`);
    expect(slotsRes.status()).toBe(200);
    const { slots } = await slotsRes.json();

    let picked = null;
    for (const date of Object.keys(slots).sort()) {
      const slot = (slots[date] ?? []).find((s) => s.disponible);
      if (slot) {
        picked = { date, heure: slot.heure };
        break;
      }
    }
    expect(picked, 'aucun créneau public disponible sur 20 jours').not.toBeNull();

    const booking = await request.post('/api/public/booking', {
      data: {
        nom: 'Kpi',
        prenom: 'E2eLotb',
        telephone: '0612345678',
        email: 'e2e-lotb-kpi@example.fr',
        date_rdv: picked.date,
        heure_rdv: picked.heure,
        type_intervention: 'E2E-LOTB booking KPI',
        duree_estimee: 60,
        atelier_id: ATELIER_ID,
      },
    });
    expect([200, 201]).toContain(booking.status());

    expect(sql(`SELECT origine FROM rendez_vous WHERE type_intervention = 'E2E-LOTB booking KPI'`)).toBe('web');
    expect(sql(`SELECT token_suivi <> '' FROM rendez_vous WHERE type_intervention = 'E2E-LOTB booking KPI'`)).toBe('t');
  });

  test('KPI : le dashboard expose la section pilote et compte le litige restitution', async ({ request }) => {
    test.setTimeout(150000);

    const res = await request.get('/api/analytics/dashboard', { headers: adminAuthHeaders() });
    expect(res.status()).toBe(200);
    const pilote = (await res.json()).pilote;
    expect(pilote).toBeDefined();
    for (const key of ['rdv_par_origine', 'pct_rdv_en_ligne', 'litiges_restitution', 'decisions_travaux_supp_par_canal', 'delai_decision_moyen_minutes']) {
      expect(pilote, `clé pilote.${key} manquante`).toHaveProperty(key);
    }
    expect(pilote.rdv_par_origine).toHaveProperty('web');

    // Le litige du test restitution est synchronisé en async (worker → fact table)
    await expect
      .poll(
        async () => {
          const dash = await request.get('/api/analytics/dashboard', { headers: adminAuthHeaders() });
          return (await dash.json()).pilote?.litiges_restitution ?? 0;
        },
        { timeout: 90000, intervals: [3000] },
      )
      .toBeGreaterThanOrEqual(1);
  });
});
