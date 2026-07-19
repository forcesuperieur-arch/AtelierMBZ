import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';

/**
 * Lot B — « zéro litige » : check-in / état des lieux d'entrée.
 *  - flux staff : brouillon → 4 photos d'entrée → signature client → PDF figé (hash)
 *  - gel du document (revue Lot B) : signature data-URI bornée [800 o ; 2 Mo],
 *    statuts RDV compatibles {en_attente, reserve, confirme, reception},
 *    photos d'entrée verrouillées post-signature, payload public = snapshot figé
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

// Pixel transparent 1×1 : décodé < 800 octets → REFUSÉ comme signature EDL
// depuis la revue Lot B. Sert de fixture négative (SIGNATURE_INVALIDE), de
// buffer PNG des uploads et de signature SQL des seeds d'OR (non validés).
const PIXEL_DATA_URL =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
const PNG_BUFFER = Buffer.from(PIXEL_DATA_URL.split(',')[1], 'base64');

// Signature valide pour POST /etat-des-lieux/sign : PNG grayscale 96×40
// déterministe (bruit LCG seed 'LOTB', incompressible), 3 948 octets décodés —
// dans la fenêtre exigée [800 octets ; 2 Mo], vérifié décodable par GD/dompdf.
const SIGNATURE_PNG_BASE64 =
  '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';
const SIGNATURE_DATA_URL = `data:image/png;base64,${SIGNATURE_PNG_BASE64}`;

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
    `INSERT INTO ordres_reparation (atelier_id, numero_or, type_or, statut, mechanic_checkup, rendez_vous_id, signature_client, signature_atelier_reception)
     VALUES (${ATELIER_ID}, '${numero}', 'initial', 'reception_signee', '{}', ${rdvId}, '${PIXEL_DATA_URL}', '${PIXEL_DATA_URL}')`,
  );
}

/** OR prêt pour la restitution publique (statut intervention_signee + rapport). */
function seedOrInterventionSignee(
  rdvId,
  numero,
  { typeOr = 'initial', travaux = 'E2E-LOTB vidange moteur et plaquettes avant' } = {},
) {
  sql(
    `INSERT INTO ordres_reparation (atelier_id, numero_or, type_or, statut, mechanic_checkup, rendez_vous_id, signature_client, signature_atelier_reception, signature_mecanicien, signe_mecanicien_at, travaux_realises, kilometrage_restitution)
     VALUES (${ATELIER_ID}, '${numero}', '${typeOr}', 'intervention_signee', '{}', ${rdvId}, '${PIXEL_DATA_URL}', '${PIXEL_DATA_URL}', '${PIXEL_DATA_URL}', NOW(), '${travaux}', 25150)`,
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

async function postSign(request, rdvId, signature = SIGNATURE_DATA_URL) {
  return request.post(`/api/rendez-vous/${rdvId}/etat-des-lieux/sign`, {
    headers: adminAuthHeaders(),
    data: { signature },
  });
}

async function uploadPhoto(request, rdvId, type, name) {
  return request.post('/api/photos/upload', {
    headers: adminAuthHeaders(),
    multipart: {
      photo: { name, mimeType: 'image/png', buffer: PNG_BUFFER },
      rendez_vous_id: String(rdvId),
      type,
    },
  });
}

/** Brouillon + signature, idempotent (GET d'abord) : chaque test reste autonome. */
async function ensureEdlSigned(request, rdvId, data) {
  const state = await request.get(`/api/rendez-vous/${rdvId}/etat-des-lieux`, { headers: adminAuthHeaders() });
  if ((await state.json()).signe) return;

  const draft = await postDraft(request, rdvId, data);
  expect([200, 201]).toContain(draft.status());
  const sign = await postSign(request, rdvId);
  expect(sign.status()).toBe(200);
}

/**
 * Création/signature d'EDL désormais refusées hors {en_attente, reserve,
 * confirme, reception} (STATUT_RDV_INCOMPATIBLE) : les scénarios « RDV déjà
 * terminé/en cours » signent d'abord sur le statut compatible du seed, PUIS
 * posent le statut final en SQL (le document signé reste gelé et exposable).
 */
async function ensureEdlSignedThenStatut(request, rdvId, data, statutFinal) {
  await ensureEdlSigned(request, rdvId, data);
  sql(`UPDATE rendez_vous SET statut = '${statutFinal}' WHERE id = ${rdvId}`);
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

    // Seedé en 'reception' (statut EDL-compatible) : les tests signent via
    // l'API puis figent 'termine' en SQL (ensureEdlSignedThenStatut).
    rdv.restitution = seedRdv('restitution litige', { statut: 'reception' });
    seedOrInterventionSignee(rdv.restitution.id, 'E2E-LOTB-OR-RESTIT');
    seedPhotos(rdv.restitution.id, 'checkin', 4, 'restit-in');
    seedPhotos(rdv.restitution.id, 'restitution', 2, 'restit-out');

    rdv.restitutionSansEdl = seedRdv('restitution sans EDL', { statut: 'termine' });
    seedOrInterventionSignee(rdv.restitutionSansEdl.id, 'E2E-LOTB-OR-RESTIT2');

    // Même contrainte : signature EDL sur 'reception', puis 'en_cours' en SQL
    // avant la transition terminer.
    rdv.emailAvecEdl = seedRdv('email avec EDL', { statut: 'reception' });
    seedPhotos(rdv.emailAvecEdl.id, 'checkin', 4, 'mail-in');
    seedPhotos(rdv.emailAvecEdl.id, 'apres_travaux', 2, 'mail-post');
    seedEssaiValide(rdv.emailAvecEdl.id);

    rdv.emailSansEdl = seedRdv('email sans EDL', { statut: 'en_cours' });
    seedPhotos(rdv.emailSansEdl.id, 'apres_travaux', 2, 'mail2-post');
    seedEssaiValide(rdv.emailSansEdl.id);

    /* ── Seeds des tests de la revue Lot B (gel du document) ── */

    rdv.sigInvalide = seedRdv('signature pixel invalide');

    rdv.statutIncompatible = seedRdv('statut incompatible', { statut: 'termine' });

    rdv.verrou = seedRdv('verrou photos', { statut: 'reception' });
    seedPhotos(rdv.verrou.id, 'checkin', 4, 'verrou');

    // Le complémentaire est inséré AVANT l'initial (id plus petit) : un tri
    // « id ASC » sans discriminant type_or le choisirait — le test prouve que
    // la restitution sélectionne bien l'OR initial.
    rdv.orComplementaire = seedRdv('OR complementaire', { statut: 'termine' });
    seedOrInterventionSignee(rdv.orComplementaire.id, 'E2E-LOTB-OR-COMP', {
      typeOr: 'complementaire',
      travaux: 'E2E-LOTB travaux complementaires acceptes en ligne',
    });
    seedOrInterventionSignee(rdv.orComplementaire.id, 'E2E-LOTB-OR-COMP-INIT', {
      travaux: 'E2E-LOTB travaux initiaux vidange',
    });

    // RDV du jour prêt à signer (brouillon complet + 4 photos) pour le test
    // UI « tap seul sur le canvas ».
    rdv.tapCanvas = seedRdv('tap canvas', { heure: '11:15' });
    sql(
      `INSERT INTO etat_des_lieux (rendez_vous_id, atelier_id, kilometrage, niveau_carburant, observations, created_at)
       VALUES (${rdv.tapCanvas.id}, ${ATELIER_ID}, 18400, 'moitie', 'E2E-LOTB tap canvas brouillon', NOW())`,
    );
    seedPhotos(rdv.tapCanvas.id, 'checkin', 4, 'tap');

    // RDV terminé avec litige restitution déjà posé (SQL) pour le test UI planning.
    rdv.litigeUi = seedRdv('planning litige UI', { statut: 'termine', heure: '14:00' });
    sql(
      `UPDATE rendez_vous SET litige_signale = true, litige_commentaire = 'E2E-LOTB rayure carter constatee par le client' WHERE id = ${rdv.litigeUi.id}`,
    );
  });

  test.afterAll(() => {
    // Filet de sécurité : le toggle atelier ne doit JAMAIS rester désactivé
    sql(`UPDATE config_atelier SET checkin_obligatoire = true WHERE atelier_id = ${ATELIER_ID}`);
  });

  /* ────────────── 1. API staff : flux check-in complet ────────────── */

  test('isolation atelier : un OR d\'un autre atelier est invisible pour le staff (document légal)', async ({ request }) => {
    const r = seedRdv('iso-or', { statut: 'reception' });
    seedOrReceptionSignee(r.id, 'E2E-LOTB-OR-ISO');
    const oid = Number(sql(`SELECT id FROM ordres_reparation WHERE numero_or = 'E2E-LOTB-OR-ISO'`));
    const H = { headers: adminAuthHeaders() };

    // Visible dans son propre atelier (1)
    expect((await request.get(`/api/or/${oid}`, H)).status()).toBe(200);

    // Rattaché à un autre atelier → hors du périmètre du staff courant
    sql(`UPDATE ordres_reparation SET atelier_id = 999999 WHERE id = ${oid}`);
    expect((await request.get(`/api/or/${oid}`, H)).status()).toBe(404);
    expect((await request.get(`/api/ordres-reparation/${oid}/pdf`, H)).status()).toBe(404);

    // Restauré dans son atelier → de nouveau visible
    sql(`UPDATE ordres_reparation SET atelier_id = 1 WHERE id = ${oid}`);
    expect((await request.get(`/api/or/${oid}`, H)).status()).toBe(200);
  });

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
      const upload = await uploadPhoto(request, id, 'checkin', `e2e-lotb-upload-${i}.png`);
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

  test('signature : le pixel 1×1 (décodé < 800 octets) est refusée → SIGNATURE_INVALIDE', async ({ request }) => {
    const id = rdv.sigInvalide.id;

    const draft = await postDraft(request, id, { kilometrage: 12000, niveau_carburant: 'plein' });
    expect([200, 201]).toContain(draft.status());

    const res = await postSign(request, id, PIXEL_DATA_URL);
    expect(res.status()).toBe(400);
    expect((await res.json()).code).toBe('SIGNATURE_INVALIDE');

    // Rien n'a été signé : le document reste un brouillon
    expect(sql(`SELECT COUNT(*) FROM etat_des_lieux WHERE rendez_vous_id = ${id} AND signed_at IS NOT NULL`)).toBe('0');
  });

  test('brouillon sur RDV terminé : refusé → STATUT_RDV_INCOMPATIBLE (signature idem)', async ({ request }) => {
    const id = rdv.statutIncompatible.id;

    const draft = await postDraft(request, id, { kilometrage: 500, niveau_carburant: 'plein' });
    expect(draft.status()).toBe(400);
    expect((await draft.json()).code).toBe('STATUT_RDV_INCOMPATIBLE');

    const sign = await postSign(request, id);
    expect(sign.status()).toBe(400);
    expect((await sign.json()).code).toBe('STATUT_RDV_INCOMPATIBLE');

    // Aucun document créé en douce
    expect(sql(`SELECT COUNT(*) FROM etat_des_lieux WHERE rendez_vous_id = ${id}`)).toBe('0');
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

  /* ────────────── 2bis. Document gelé après signature ────────────── */

  test('upload staff type checkin refusé après signature → EDL_SIGNE_PHOTOS_VERROUILLEES', async ({ request }) => {
    const id = rdv.verrou.id;
    await ensureEdlSigned(request, id, { kilometrage: 30500, niveau_carburant: 'quart' });

    const upload = await uploadPhoto(request, id, 'checkin', 'e2e-lotb-verrou-late.png');
    expect(upload.status()).toBe(400);
    expect((await upload.json()).code).toBe('EDL_SIGNE_PHOTOS_VERROUILLEES');

    // Un type hors photos d'entrée reste accepté (le gel ne vise que checkin/reception)
    const enCours = await uploadPhoto(request, id, 'en_cours', 'e2e-lotb-verrou-encours.png');
    expect(enCours.status()).toBe(201);
  });

  test('photo checkin insérée en SQL après signature : le payload public reste le snapshot', async ({ request }) => {
    const id = rdv.verrou.id;
    await ensureEdlSigned(request, id, { kilometrage: 30500, niveau_carburant: 'quart' });

    const before = await request.get(`/api/public/suivi/token/${rdv.verrou.token}`);
    expect(before.status()).toBe(200);
    expect((await before.json()).etat_des_lieux.photos).toHaveLength(4);

    // Contournement du verrou d'upload : insertion SQL directe post-signature
    seedPhotos(id, 'checkin', 1, 'verrou-sql-late');
    expect(Number(sql(`SELECT COUNT(*) FROM photos_intervention WHERE rendez_vous_id = ${id} AND type = 'checkin'`))).toBe(5);

    // Le document public sert le SNAPSHOT figé : toujours 4 photos
    const after = await request.get(`/api/public/suivi/token/${rdv.verrou.token}`);
    expect(after.status()).toBe(200);
    expect((await after.json()).etat_des_lieux.photos).toHaveLength(4);

    // Même invariant côté staff : le compte vient du snapshot signé
    const staff = await request.get(`/api/rendez-vous/${id}/etat-des-lieux`, { headers: adminAuthHeaders() });
    expect((await staff.json()).photos_entree_count).toBe(4);
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

    test('signature : un tap seul sur le canvas laisse « Valider et signer » désactivé', async ({ page }) => {
      await page.goto('/reception');
      const card = page.locator('[data-testid="reception-rdv-card"]').filter({ hasText: 'E2E-LOTB tap canvas' });
      await expect(card).toBeVisible({ timeout: 15000 });
      await card.getByTestId('btn-checkin').click();

      // Brouillon complet + 4 photos seedés : le bouton s'active après l'hydratation
      const openSign = page.getByTestId('btn-faire-signer');
      await expect(openSign).toBeEnabled({ timeout: 15000 });
      await openSign.click();

      // SignatureModal téléportée dans body, au-dessus de l'AppModal check-in
      const canvas = page.locator('.sig-canvas');
      await expect(canvas).toBeVisible({ timeout: 10000 });

      const valider = page.locator('.sig-modal').getByRole('button', { name: /valider et signer/i });
      await expect(valider).toBeDisabled();

      // Tap seul (pointerdown + pointerup sans tracé) : toujours désactivé
      await canvas.click({ position: { x: 60, y: 40 } });
      await expect(valider).toBeDisabled();

      // Un vrai tracé (> distance minimale) active enfin le bouton
      const box = await canvas.boundingBox();
      await page.mouse.move(box.x + 30, box.y + 50);
      await page.mouse.down();
      await page.mouse.move(box.x + 160, box.y + 90, { steps: 10 });
      await page.mouse.up();
      await expect(valider).toBeEnabled();
    });
  });

  /* ────────────── 3bis. UI staff /planning : litige restitution ────────────── */

  test.describe('page staff /planning', () => {
    test.use({ storageState: 'playwright/.auth/admin.json' });

    test('détail d\'un RDV avec litige restitution : alerte, badge et commentaire visibles', async ({ page }) => {
      await page.goto('/planning');

      // RDV terminé → liste « Historique figé » sous la grille
      const histEntry = page.locator('button').filter({ hasText: 'E2E-LOTB planning litige UI' });
      await expect(histEntry.first()).toBeVisible({ timeout: 15000 });
      await histEntry.first().click();

      // L'alerte n'apparaît qu'après le rechargement du RDV complet (litige_signale)
      await expect(page.getByTestId('rdv-litige-alert')).toBeVisible({ timeout: 15000 });
      await expect(page.getByTestId('rdv-litige-badge')).toContainText(/litige restitution/i);
      await expect(page.getByTestId('rdv-litige-commentaire')).toContainText('E2E-LOTB rayure carter constatee par le client');
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
    // Signature sur statut compatible ('reception' du seed) PUIS statut final en SQL
    await ensureEdlSignedThenStatut(request, rdv.restitution.id, {
      kilometrage: 25100,
      niveau_carburant: 'trois_quarts',
      observations: 'E2E-LOTB retro gauche raye au depot',
    }, 'termine');

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

  test('restitution : signature avec litige → persistance, notification staff, confirmation UI', async ({ page, request }) => {
    const id = rdv.restitution.id;
    // Autonomie du test : EDL signé + statut terminal, idempotent si déjà fait
    await ensureEdlSignedThenStatut(request, id, {
      kilometrage: 25100,
      niveau_carburant: 'trois_quarts',
      observations: 'E2E-LOTB retro gauche raye au depot',
    }, 'termine');

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

  test('restitution : l\'OR complémentaire n\'usurpe jamais l\'OR initial du payload', async ({ request }) => {
    // Seed : OR complémentaire inséré AVANT l'initial (id plus petit)
    const initialId = Number(sql(`SELECT id FROM ordres_reparation WHERE numero_or = 'E2E-LOTB-OR-COMP-INIT'`));
    const compId = Number(sql(`SELECT id FROM ordres_reparation WHERE numero_or = 'E2E-LOTB-OR-COMP'`));
    expect(compId).toBeLessThan(initialId);

    const res = await request.get(`/api/public/restitution/${rdv.orComplementaire.token}`);
    expect(res.status()).toBe(200);
    const { ordre } = await res.json();
    expect(ordre.id).toBe(initialId);
    expect(ordre.travaux_realises).toContain('E2E-LOTB travaux initiaux');
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
    // Signature sur 'reception' (seed) puis 'en_cours' en SQL : la transition
    // terminer part du même statut qu'avant les correctifs.
    await ensureEdlSignedThenStatut(request, id, { kilometrage: 25100, niveau_carburant: 'plein' }, 'en_cours');

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
