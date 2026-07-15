import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';

/**
 * Canal téléphone — décision travaux supp enregistrée par le staff + lien de
 * confirmation de signature :
 *  1. accord tél staff (UI) → statut accepte / canal staff_telephone, OR
 *     complémentaire figé NON signé (en_attente_signature), email MailHog avec
 *     lien, notification staff « signature en attente »
 *  2. page publique en mode confirmation → signature → OR signé + notif
 *     « signature confirmée » ; re-visite : état « accord confirmé »
 *  3. refus par téléphone → statut refuse, pas d'OR, pas d'email
 *  4. gardes : décision tél sur demande déjà décidée → 409 ; refus en ligne
 *     après accord tél → 409 ; accord tél sur demande non chiffrée → 409 ;
 *     signature manquante à la confirmation → 400
 *  5. portail client : encart confirmation + signature OK ; isolation
 *     inter-clients intacte
 *  6. KPI pilote : staff_telephone dans le par-canal + compteur
 *     accords_telephone_en_attente_signature
 * Comptes : staff admin@atelier.local — client jean.moreau@email.fr (client 1).
 * Données préfixées E2E-TEL, RDV seedés dédiés (jamais les RDV 1/269 ni l'OR 1).
 */

const PORTAL = 'http://localhost:81/client';
const API = 'http://localhost:81';
const MAILHOG = 'http://localhost:8025';
const CLIENT_EMAIL = 'jean.moreau@email.fr';
const CLIENT_PASSWORD = process.env.CLIENT_TEST_PASSWORD || 'ClientTest123!';
const CLIENT_ID = 1; // jean.moreau
const ATELIER_ID = 1;
const PSQL = 'docker compose exec -T db psql -U atelier -d atelier_moto -tA -c';

// Signature data-URI valide pour les confirmations passées par API : PNG
// grayscale 96×40 incompressible, 3 948 octets décodés (le pixel 1×1 est
// refusé comme signature — même fixture que lot-b.spec).
const SIGNATURE_PNG_BASE64 =
  'iVBORw0KGgoAAAANSUhEUgAAAGAAAAAoCAAAAAAk+KDlAAAPM0lEQVR42gEoD9fwAPKGep7ACcrlGAco3ElFQswlmENu7i+C' +
  'IgfIOQnTlkPeqMJem7u8kEdb0opy/sE0RtYzzDAMXSyDwTUpmIPrL1XRq24c0sNPf3O2DUtv5Za/DswUApn0ySvJJSV1sH8p' +
  'ZADraI8GFptPjsmtaOqm0xQkPNPBorhaiJW3IOY8tZz+sNszrXE6FiHmp0nXhdCBHVc3zoIDL0vZrgs+qj0QZEDV51u1qpVY' +
  'l3BArBgGPbjmKjdUB0xn1XLXJi0yfGTLRmEAu0KaMiXbyIWnj3AKYxhmZtoSA9htXEvlTy7EsnmYKNVmX1S+h2wQ99U9puPZ' +
  'ElvIIskwN/WXoSOwGClJOWjHqVs+3DgBLA0IpZUGmF7sQwFyTkoqU5H2TVdTr2GyrMJHAHF7MLt6kKNv2Atf4tHBY7SWiRKp' +
  'gqkPlDfeKNAYpoEO5ctUmH5i+FkSqAQvN3/Derl3TN8ek/SfJkvoHMFwLU9U9E5WPL54ZNwFzqG56C/gbSnBKmtW8ofevsqB' +
  'J/W1UQDD0CmsiRXSo6JVGA6kkjdlqcM9Hk5Mm9n98ivw6/bKTplO8ojcLviftFjA/2nnL37DfpCCUCXElgY07AzOB+J2oHG9' +
  'CVAgIVNGaSy0nSUulXumkE+f5IU0YGZ0LriPOOoACU+hh6Zayi5xckId918SBPKdFbYN4DycU/2rYLPMysBmE7aIQpywEzHy' +
  'buFWe3ocrNb8GRDFiiwQBP9k7s1aBK/8Hcsr+9ZaZv6fWbkzyiOT2yQQwpFUtacXYqy7d2yzAD9c+D8N4YXO1D1JlEYUK1D0' +
  'SXJk4JjCfA9pSWGSgU3B1ptsCzgLRq8l7GNSRoKHz4DP0BXMgUI8I7+uXw14CRKWMqaGn4npsOPYagskspDBTjIpVo1HhSCg' +
  'cw4267xzgwAHsNM+28KB+YBnXOhzr7k71Uxujcs5gMwpcmspD4Eh4BS5JfQnbmUkUI+9x+ZWOkX0CZNW1PprU0U/SjCAz23u' +
  'DQLUnGOaMEKK3ZQ6V1oP2+xpkoCbgsVAXe61+GiDdGEApFYZXxKowdVMcG6HwkH57mGBZg21G06QwSk54BZMGeTylDSbXU07' +
  '1pX7WahIZXtiXnoRNFxlCrShL+n8/a4CF3GvZ+EPkQSvSsjqSi713hBPkZZgblNEkhTRWBW0A5mKAACt+fSY0slAM7A3z9zx' +
  'LcMFFf4xbCyIgxZzoqX1dgzF5Kkzzw3CeN38JN1O4DI4PbdtVnl8jKZVgxFj3aAGTUPC0mWdI2VETDYn+pDqYJQx/Q+uPmhg' +
  'tUkfOL2htokScACnauHAOBSkyVVRMxTP+plJ1YocvJ7uDRSPCVaJYKfVsQLI/s5MfVMEsc6uC4hSYiCgfrZjL8tM3146V+Dn' +
  '29RbplYVjQGhUSCGXRhbMY/PRyKfmtPS41FJ+hRup7t2EbgAypSG/aDV37X2U5+dDKmGQoa26ebgdUNjtXbLkW6cUwcJFLRN' +
  'FMKGzQOW+CF8cOyKWZ/HpVcUirHH0SYHtkUie2R8BOLuZRHolqJa5wyGMIuMTFxP2afTLkgrtiNwPc46ADyH4VZhEI36fYeB' +
  'pmdhQKZ0wdVXq2oQRzcdOreZJWheWgS6dUVnT2lm6avIX0vRL8sXY2W37HXdd9T0RlRwxvbp1QJAFWTtE+b/nWroYlturno5' +
  'U4wPe0XNFKgI6WeCBAB48i7s8lRDRXWVnl4amBmgnSuSL1oM4ErlMKHowSb7fvplueKf12/DcQt6KzSzDfV/f6g++GykJ0wU' +
  'rIhsnVJPxksjv4Tk1cUYVIAKlU75kp3evfXfEA2QdDwaOp2QbVYAmdrtVK3EG/aP+ITpw9dmj6TFFQEtKqOptbvBBiqY92SQ' +
  'VZ2lyRMtdd8S3GplkKD4pcT5PqRCNAxINfqqDR9dQDAawOwtwjnW9FNm40qPt84VgbbCqjPRxUvlpJNnaNKlAGCV4pXQGLMh' +
  'nv2AXmG/fwbPs5vRSSvOV8GYH+d5hUtBakqXQk2sUtCS6Q2ZwNyQhg4p/egur8W3kLBRfapgg7b/0HTH5mwQ02eDdgWfekYt' +
  'dcE1tquUkk+CIqPhhrP4mwAx0BYufJotjprJpslb/8HLCHGfHLUHV/tGeQRTuxDrengKGrKZyivXi08MvnWl5iMyQp0z54d5' +
  'DW7R5Mcb2mLRr1EixWjm0/t/PpxzkXpb7r5QkkR/5qFKcN065Z1KKBQAFYvVDbkrLrieUc0peWCO2uDM5tBeTbvspuJ+CF1s' +
  'y6hOst5hACmMQ/TXnNYZDa2FKvsIiAo1+aFPuq9BmR+d52HW7M9Tx4BCcbSHJIEMr4SYNGZUrW7RiY3TTVS0GbMhALoYsJdw' +
  'PeHR6WCScei6SWGH5nZOFBz6O2ctX7g14emYJrHiL7YYyX8W5kXPpUv4l7eQsMaxtXK/xl9tmcXT24Hb6TtAKTqZUJPnqgQ1' +
  'w/2WTceQPpsFepN5k4bYNSDrCABuIXumcNfzu+CVU4o5/VzE1DOYbooql6gyhjsId8tBStzKZHLXer2sYrhRjneo23g5k0tA' +
  '3ZmSZIeJoXGircZbhGy86U7+JIJirT7y1UUI/8y1M9OWF3ig84NSD/BzJ0EAKKBOhGqWlhAKYzNOYyozmkN/23pawZqp1O1s' +
  'ksCb2PXwFunxYMXGnWxc0elPgm98uOrVu3IFjUJs1JBdlnsOZ2iQXg8PZ6vmfLI8w+9Mlc6nAbRTzqcrn+Lb44kXOsJ5AH/m' +
  'hvT0p4AZEhAajLxYQK3y5xIw/r2PakA3D+IP1bQBiAA56TQGxNvrtJisU03RKd7OinQ5tBzAdLJC6G0cZck631dql1s7Qqd0' +
  'ZTx0pmtGFQG+dldJdjJQqS7NQrIbkQCwlcIpic7q2cm3tAoEsff9o9xSw9iRh8aKDgZ7xxHgCmtIYQoZ3B+hu3g9lwyPITr6' +
  'z+8X3PF69r5nho5VhILbTDQeJvtXZgzIRjsauDdY3fBeQGcqJ8KvaczrVGwrk54AnKboy4djlAIhR299WnLRvL0k99kqQBRS' +
  '7u3206/7auIGArF5uHm/39oxG11n4ISe/8zIyOygZ7SSC+3B47wgKZi+cOUvaENy7E8HDXbzLT+3FmzDIf37IID7mYv0CZPq' +
  'AMdlH/gvUL79M4J/kkXwS1FL2Z6NH2ROUscmSVHxVGaOapW9z6CmEzpuPlKmtPEjeIP8IR3eNCh7WIvM5wzf/nW7AU26DjNq' +
  'UkOJ07fJ5b5LjXu3nf4ej0IKvHrUncWG8ABbcNRAqBUu5jkA2+esjuZY+2kqbsAs08CY3StVHe/qR0u9XhdDvA0JvdLEDaqC' +
  'LB/B6EcgCreCgZ+pPFktH9+z9db5L1bv+0IgxG+wMyzkFDgy3XxlbnSIoW2RhI7p2WMAIr24pv3FLoyUKz8R3cgmoB+UwX3/' +
  'VsBKBguNLye2EnkDirHT96wiWDP1GyFha84elm/NUKrGxLEX+RxlA8CJDCpn/j6pZpaI+qFuhJr5feAjNtxa3WcgGAM/qeVS' +
  'GAAnAI+SvqUaB410x0Ipl4otlSqvc84xrzq7U9t9JyVlo/zGjV8U9/b5TedggL9mVpVANYfBiaDhkrynl+MLS7MZyN7jNCZa' +
  'jfhiIW2XKLNGk2grXdP1ptIVkcwQN0802AVxVAC1jB8n0heb1XpX3/THXb8vRWz/dYrA6vEF03BwksjLA4r1LuteuQsr9yLj' +
  'U2r+uFW5ZpZ1seG+thikcELDlmq5TRKEZmyZCL058riu5FSMpJessEGWG0Qxzy3Uo3R4qTkATplYjtzDL5x4UWWXDRA2GiBA' +
  'RqcqZfvslIKmPs1JpTg/VuWLMpleStBfd1Tjunam+DVSqwMMouS3TnNzH7lCYjknaruVz7fAQs/834Wq0C0ptteDW6w/imjN' +
  'TVfcTSRVALb/KK7RcKNoseuJ5ToQjYgh/tmaDzscxMDTyrCaXbajleNnKVfXyyLmjDTJae24g7FeYI+j/8TrtsQD/BUVJ9Ma' +
  '8HElObYgQo4jmqCgGCLeDjt4NjqTCs0rmpVpK3VnXwDtU5TPMBPTjjiz2TWOOl5O0A0zlJ/nAqvg4qLc4FEttRhQJIiXRl1C' +
  'WtSWBQrTxXv3YqXlQD0EOXy/0O1ZUvM4Cjx25W79eA5KsvdBCzNyAePZ5pgvLa+jiOZI6OL0+D4AmIPlr1o5JBVGDanRr4JG' +
  'clgoE08houSFc6G3zOmGPBL4o9DioU+i7m432k43ueRRgRZM4nDbxvCQzVBNAi2H8sUwmbAw53ulxRk/BgamthbrgbZcGmvA' +
  'axXcx9Ti4mEQAADOpn2WAHm5NS8P+6bs5S+HWnr7wTl+7hvUV3xlbxqTCjpj4gfrqx6KhwbixAFk+6qlwzKqgfHmok68FYp2' +
  'w5asQj6tWkxlO3PGYiYwL+XQDXrEqMDRTzaLeDkjc2szJgARyKjeDRw964Yj6OTgleH0zwavOY8NEDOdE5LeZ5UCRsfDGqk+' +
  'qg5+OWrg7+sjlKZvi77yS23ypYJ2EjLREZDtwz4NebGW+Lfr+xPMkKH+FNRcLRjIevXLyIqGvkDQ/gQAXFgA6s7UXs7bydO1' +
  'LaniZUfjPCCAFV5V4sw/12aUM2tMQnXMobDmbi1c85hJqMmvdZsxtpFwt2omThOFGvfY9LNF9CXaxHu26dDKGI3hAABvFlO4' +
  'vhLNo863W4RYZFxlABW5Bi7LBE06/dMzicRqlVeq+fA6a9iwDfk/9kI9HPB4WA85U26zzwI3q4/24C5dqgD7WIOi77YpqLRE' +
  '5Q0zitzJySI2Xmu3iE0uwB316vHgr+reDBLbeOzDeCtELpDlNAAVfVWq2xkBvNbIL289MKvWVqbdhgx00sISgBTsK/F/FVHW' +
  'brrI/+wBUHrHExNorF39JLDThuTmhUZX7huz5ZkL9JMLoq2x+jAZ1ffj+srZOQtsuAsaqhmJ7xopxpnROpMA2YbP07YZ9JN4' +
  'A7NslWXZIk6cWXcEmxOThHq8ldPsLCA/l2Dys3Pj6ZO/c/CtGxfE+OP8lyncwttgvR/kfUP8GMq/adgXUhZmd1gxZTCjxcGe' +
  'hw4eDOlXEMC9UcLUtv3WAIENmJL7mSKzFLJudS2G16w54P/y15FHUsjn0vQ9+USoz6SfYBqD3Og/RS1/2iMDDiZbQy9a90s3' +
  'fT6p8T2UrdGEuza41JxhmLAc9pOQq8dkJ3lUJASTYMnJVl7mf+TUh1QljNNecICkAAAAAElFTkSuQmCC';
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

/* ───────────────────────── Seeds SQL (idempotents) ───────────────────────── */

let vehiculeId;
let otherClientId;
// RDVs seedés : { id, token } — demandes seedées : { id, token }
const rdv = {};
const dem = {};

function newRdvToken() {
  return 'e2etelr' + randomBytes(24).toString('hex').slice(0, 40);
}

function seedRdv(label, { clientId = CLIENT_ID } = {}) {
  const token = newRdvToken();
  // INSERT puis SELECT séparés : avec RETURNING, psql -tA pollue la sortie du tag
  sql(
    `INSERT INTO rendez_vous (atelier_id, date_rdv, heure_rdv, type_intervention, statut, token_suivi, client_id, vehicule_id, temps_estime, created_at)
     VALUES (${ATELIER_ID}, CURRENT_DATE, '09:00', 'E2E-TEL ${label}', 'confirme', '${token}', ${clientId}, ${vehiculeId}, 60, NOW())`,
  );
  return { id: Number(sql(`SELECT id FROM rendez_vous WHERE token_suivi = '${token}'`)), token };
}

/**
 * Demande de travaux supp prête pour une décision (chiffrée par défaut).
 * `chiffree: false` : ni prestations ni prix → la garde 409 doit refuser
 * l'accord téléphonique.
 */
function seedDemande(rdvId, description, { chiffree = true } = {}) {
  const token = 'e2etel' + randomBytes(29).toString('hex');
  const prestations = chiffree
    ? JSON.stringify([{ designation: 'Remplacement plaquettes avant', prix_ttc: '89.00', temps_minutes: 30 }]).replace(/'/g, "''")
    : '[]';
  sql(
    `INSERT INTO demandes_travaux_supp (atelier_id, rendez_vous_id, description, urgence, statut, prestations_choisies, prix_estime, temps_estime, token_validation, created_at, sent_at)
     VALUES (${ATELIER_ID}, ${rdvId}, '${description}', 'normal', '${chiffree ? 'en_attente_decision_client' : 'en_attente_validation'}', '${prestations}',
             ${chiffree ? '89.00' : 'NULL'}, ${chiffree ? 30 : 'NULL'}, '${token}', NOW(), ${chiffree ? "NOW() - interval '5 hours'" : 'NULL'})`,
  );
  return { id: Number(sql(`SELECT id FROM demandes_travaux_supp WHERE token_validation = '${token}'`)), token };
}

/** Purge complète des données E2E-TEL (demandes, ORs, RDVs, client tiers). */
function cleanE2EData() {
  const mine = `SELECT id FROM demandes_travaux_supp WHERE description LIKE 'E2E-TEL%'`;
  const rdvs = `SELECT id FROM rendez_vous WHERE type_intervention LIKE 'E2E-TEL%'`;
  sql(`DELETE FROM notifications WHERE related_entity_type = 'DemandeTravauxSupp' AND related_entity_id IN (${mine})`);
  sql(`DELETE FROM notification_logs WHERE template_code = 'demande_confirmation_telephone' AND related_entity_id IN (${mine})`);
  sql(`UPDATE demandes_travaux_supp SET or_complementaire_id = NULL WHERE description LIKE 'E2E-TEL%'`);
  sql(`DELETE FROM ordres_reparation WHERE demande_travaux_supp_id IN (${mine})`);
  sql(`DELETE FROM demandes_travaux_supp WHERE description LIKE 'E2E-TEL%'`);
  sql(`DELETE FROM notifications WHERE related_entity_type = 'RendezVous' AND related_entity_id IN (${rdvs})`);
  sql(`DELETE FROM analytics_rdv_facts WHERE rdv_id IN (${rdvs})`);
  sql(`DELETE FROM rendez_vous WHERE type_intervention LIKE 'E2E-TEL%'`);
  sql(`DELETE FROM vehicules WHERE plaque LIKE 'E2E-TEL%'`);
  sql(`DELETE FROM clients WHERE email LIKE 'e2e-tel%'`);
}

/* ───────────────────────── Helpers UI / API ───────────────────────── */

/**
 * Ligne de la liste staff /demandes-travaux-supp contenant la demande.
 * `btn-decision-telephone` & co existent sur CHAQUE ligne ET dans le modal
 * détail : on isole la div de ligne (la plus profonde qui contient à la fois
 * le libellé « #id — » et le bouton ancre).
 */
function demandeRow(page, demandeId, anchorTestId) {
  return page
    .locator('div')
    .filter({ hasText: `#${demandeId} — ` })
    .filter({ has: page.getByTestId(anchorTestId) })
    .last();
}

/** Trace un trait réaliste sur un canvas de signature (souris/pointeur). */
async function drawSignature(page, canvas) {
  await canvas.scrollIntoViewIfNeeded();
  const box = await canvas.boundingBox();
  await page.mouse.move(box.x + 40, box.y + 60);
  await page.mouse.down();
  await page.mouse.move(box.x + 180, box.y + 110, { steps: 12 });
  await page.mouse.move(box.x + 320, box.y + 70, { steps: 12 });
  await page.mouse.up();
}

/** Accord téléphonique enregistré par API staff (canal email). */
async function accordTelephoneApi(request, demandeId, extra = {}) {
  const res = await request.post(`/api/demandes-travaux-supp/${demandeId}/decision-telephone`, {
    headers: adminAuthHeaders(),
    data: { decision: 'accepte', canal_envoi: 'email', ...extra },
  });
  expect(res.status()).toBe(200);
  return res.json();
}

/**
 * Autonomie des tests : après un échec, Playwright redémarre le worker et
 * rejoue beforeAll (données réensemencées vierges). Les tests qui dépendent
 * d'une décision posée par un test précédent la reposent par API si besoin.
 */
async function ensureDecisionTelephone(request, demande, decision) {
  const statut = sql(`SELECT statut FROM demandes_travaux_supp WHERE id = ${demande.id}`);
  if (statut === 'accepte' || statut === 'refuse') return;
  const res = await request.post(`/api/demandes-travaux-supp/${demande.id}/decision-telephone`, {
    headers: adminAuthHeaders(),
    data: { decision, ...(decision === 'accepte' ? { canal_envoi: 'email' } : {}) },
  });
  expect(res.status()).toBe(200);
}

/* ═════════════════════════════ Tests ═════════════════════════════ */

test.describe('Canal téléphone — accord/refus staff + confirmation de signature', () => {
  test.beforeAll(() => {
    cleanE2EData();

    sql(`INSERT INTO vehicules (atelier_id, plaque, marque, modele, client_id) VALUES (${ATELIER_ID}, 'E2E-TEL-V1', 'Honda', 'CB650R', ${CLIENT_ID})`);
    vehiculeId = Number(sql(`SELECT id FROM vehicules WHERE plaque = 'E2E-TEL-V1'`));

    // Client tiers pour l'isolation inter-clients (aucun mot de passe nécessaire)
    sql(`INSERT INTO clients (atelier_id, nom, prenom, telephone, email, created_at) VALUES (${ATELIER_ID}, 'Isolation', 'E2eTel', '0600000002', 'e2e-tel-other@example.fr', NOW())`);
    otherClientId = Number(sql(`SELECT id FROM clients WHERE email = 'e2e-tel-other@example.fr'`));

    rdv.staff = seedRdv('decisions staff');
    rdv.portail = seedRdv('portail confirmation');
    rdv.isolation = seedRdv('isolation autre client', { clientId: otherClientId });
    rdv.kpi = seedRdv('kpi pilote');

    dem.accord = seedDemande(rdv.staff.id, 'E2E-TEL accord tel plaquettes avant');
    dem.refus = seedDemande(rdv.staff.id, 'E2E-TEL refus tel pneu arriere');
    dem.garde = seedDemande(rdv.staff.id, 'E2E-TEL garde refus apres accord');
    dem.nonChiffree = seedDemande(rdv.staff.id, 'E2E-TEL demande non chiffree', { chiffree: false });
    dem.portail = seedDemande(rdv.portail.id, 'E2E-TEL portail confirmation signature');
    dem.isolation = seedDemande(rdv.isolation.id, 'E2E-TEL isolation inter-clients');
    dem.kpi = seedDemande(rdv.kpi.id, 'E2E-TEL kpi accord telephone');
  });

  test.afterAll(() => {
    cleanE2EData();
  });

  /* ────────────── 1 & 3. UI staff : décision téléphonique ────────────── */

  test.describe('UI staff /demandes-travaux-supp', () => {
    test.use({ storageState: 'playwright/.auth/admin.json' });

    test('accord tél (email) : accepte non signé, OR figé en_attente_signature, email + notif', async ({ page, request }) => {
      test.setTimeout(150000);
      const { id, token } = dem.accord;

      await page.goto('/demandes-travaux-supp');
      const row = demandeRow(page, id, 'btn-decision-telephone');
      await expect(row).toBeVisible({ timeout: 15000 });
      await row.getByTestId('btn-decision-telephone').click();

      const modal = page.getByTestId('modal-decision-telephone');
      await expect(modal).toBeVisible();

      // Le canal d'envoi n'apparaît qu'une fois « Accepté » coché (défaut email)
      await expect(page.getByTestId('select-tel-canal')).toBeHidden();
      await modal.getByTestId('radio-tel-accepte').check();
      await expect(page.getByTestId('select-tel-canal')).toBeVisible();
      await expect(page.getByTestId('select-tel-canal')).toHaveValue('email');
      await modal.getByTestId('input-tel-commentaire').fill('E2E-TEL accord donne par M. Moreau, rappeler avant piece supplementaire');

      // Confirmation native avant envoi
      page.on('dialog', (dialog) => dialog.accept());
      const postResponse = page.waitForResponse(
        (res) => res.url().includes(`/demandes-travaux-supp/${id}/decision-telephone`) && res.request().method() === 'POST',
      );
      await page.getByTestId('btn-tel-confirmer').click();
      const res = await postResponse;
      expect(res.status()).toBe(200);
      const body = await res.json();
      expect(body.envoye).toBe(true);
      expect(body.destinataire).toBe(CLIENT_EMAIL);
      expect(body.lien_client).toBe(`/public/demande/${token}`);
      expect(body.decision_canal).toBe('staff_telephone');

      // Persistance : accepté par téléphone, NON signé, trace du staff + commentaire
      expect(sql(`SELECT statut FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('accepte');
      expect(sql(`SELECT decision_canal FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('staff_telephone');
      expect(sql(`SELECT signature_client IS NULL AND signed_at IS NULL FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('t');
      expect(sql(`SELECT decision_enregistree_par_id IS NOT NULL AND decision_client_at IS NOT NULL FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('t');
      expect(sql(`SELECT notes_receptionniste FROM demandes_travaux_supp WHERE id = ${id}`)).toContain('E2E-TEL accord donne par M. Moreau');
      expect(sql(`SELECT notes_receptionniste LIKE '%[Accord t%' FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('t');

      // OR complémentaire créé, figé mais PAS signé
      expect(sql(`SELECT or_complementaire_id IS NOT NULL FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('t');
      expect(
        sql(`SELECT o.type_or || '|' || o.statut FROM ordres_reparation o JOIN demandes_travaux_supp d ON d.or_complementaire_id = o.id WHERE d.id = ${id}`),
      ).toBe('complementaire|en_attente_signature');
      expect(
        sql(`SELECT o.signature_client IS NULL FROM ordres_reparation o JOIN demandes_travaux_supp d ON d.or_complementaire_id = o.id WHERE d.id = ${id}`),
      ).toBe('t');

      // Notification staff « signature en attente » (severity warning, pas success)
      expect(
        Number(sql(`SELECT COUNT(*) FROM notifications WHERE type = 'demande_decision_client' AND related_entity_id = ${id} AND severity = 'warning' AND title LIKE '%signature en attente%'`)),
      ).toBe(1);

      // Dispatch email tracé en synchrone…
      expect(
        Number(sql(`SELECT COUNT(*) FROM notification_logs WHERE template_code = 'demande_confirmation_telephone' AND channel = 'email' AND related_entity_id = ${id}`)),
      ).toBe(1);

      // …et réellement délivré avec le lien de signature (worker async lent en dev)
      const findMail = async () => (await mailhogTo(request, CLIENT_EMAIL)).find((m) => decodeQP(m.Content?.Body).includes(token)) ?? null;
      await expect.poll(async () => (await findMail()) !== null, { timeout: 90000, intervals: [2000] }).toBe(true);
      const mailBody = decodeQP((await findMail()).Content.Body);
      expect(mailBody).toContain(`/public/demande/${token}`);
      expect(mailBody).not.toContain('{{lien}}');

      // L'UI reflète l'état dérivé : badge + repli comptoir sur la ligne
      const rowAfter = demandeRow(page, id, 'btn-faire-signer-comptoir');
      await expect(rowAfter.getByTestId('badge-signature-attente')).toBeVisible({ timeout: 15000 });
      await expect(rowAfter.getByTestId('btn-faire-signer-comptoir')).toBeVisible();
      await expect(rowAfter.getByTestId('btn-decision-telephone')).toHaveCount(0);
    });

    test('refus tél : statut refuse, aucun OR, aucun email de confirmation', async ({ page }) => {
      const { id } = dem.refus;

      await page.goto('/demandes-travaux-supp');
      const row = demandeRow(page, id, 'btn-decision-telephone');
      await expect(row).toBeVisible({ timeout: 15000 });
      await row.getByTestId('btn-decision-telephone').click();

      const modal = page.getByTestId('modal-decision-telephone');
      await expect(modal).toBeVisible();
      await modal.getByTestId('radio-tel-refuse').check();
      // Pas d'envoi de lien pour un refus : le canal reste masqué
      await expect(page.getByTestId('select-tel-canal')).toBeHidden();

      page.on('dialog', (dialog) => dialog.accept());
      const postResponse = page.waitForResponse(
        (res) => res.url().includes(`/demandes-travaux-supp/${id}/decision-telephone`) && res.request().method() === 'POST',
      );
      await page.getByTestId('btn-tel-confirmer').click();
      expect((await postResponse).status()).toBe(200);

      expect(sql(`SELECT statut FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('refuse');
      expect(sql(`SELECT decision_canal FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('staff_telephone');
      expect(sql(`SELECT or_complementaire_id IS NULL FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('t');
      expect(sql(`SELECT decision_enregistree_par_id IS NOT NULL FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('t');

      // Notification staff du refus, mais AUCUN envoi de lien de confirmation
      expect(
        Number(sql(`SELECT COUNT(*) FROM notifications WHERE type = 'demande_decision_client' AND related_entity_id = ${id} AND title LIKE '%par téléphone%'`)),
      ).toBe(1);
      expect(
        Number(sql(`SELECT COUNT(*) FROM notification_logs WHERE template_code = 'demande_confirmation_telephone' AND related_entity_id = ${id}`)),
      ).toBe(0);
    });
  });

  /* ────────────── 2. Page publique : confirmation de signature ────────────── */

  test('page publique en mode confirmation : signature → OR signé, re-visite « accord confirmé »', async ({ page, request }) => {
    const { id, token } = dem.accord; // accord tél posé par le test UI précédent
    await ensureDecisionTelephone(request, dem.accord, 'accepte');

    await page.goto(`/public/demande/${token}`);
    await expect(page.getByRole('heading', { name: /confirmez votre accord/i })).toBeVisible({ timeout: 15000 });

    const bloc = page.getByTestId('bloc-confirmation-telephone');
    await expect(bloc).toBeVisible();
    await expect(bloc).toContainText(/accord par téléphone/i);

    // Le bouton reste désactivé tant que le canvas est vierge
    const confirmer = page.getByTestId('btn-confirmer-signature');
    await expect(confirmer).toBeDisabled();
    await drawSignature(page, bloc.locator('canvas'));
    await expect(confirmer).toBeEnabled();

    const postResponse = page.waitForResponse(
      (res) => res.url().includes(`/public/demandes-travaux-supp/${token}/decision`) && res.request().method() === 'POST',
    );
    await confirmer.click();
    expect((await postResponse).status()).toBe(200);

    await expect(page.getByTestId('etat-accord-confirme')).toBeVisible({ timeout: 10000 });

    // Persistance : demande signée, canal INCHANGÉ (la décision a eu lieu au tél)
    expect(sql(`SELECT statut FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('accepte');
    expect(sql(`SELECT decision_canal FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('staff_telephone');
    expect(sql(`SELECT signature_client IS NOT NULL AND signed_at IS NOT NULL FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('t');

    // L'OR complémentaire figé est maintenant signé (hash posé)
    expect(
      sql(`SELECT o.statut FROM ordres_reparation o JOIN demandes_travaux_supp d ON d.or_complementaire_id = o.id WHERE d.id = ${id}`),
    ).toBe('signe');
    expect(
      sql(`SELECT o.signed_hash IS NOT NULL AND o.signature_client IS NOT NULL FROM ordres_reparation o JOIN demandes_travaux_supp d ON d.or_complementaire_id = o.id WHERE d.id = ${id}`),
    ).toBe('t');

    // Notification staff « signature en ligne confirmée » (success)
    expect(
      Number(sql(`SELECT COUNT(*) FROM notifications WHERE type = 'demande_decision_client' AND related_entity_id = ${id} AND severity = 'success' AND title LIKE 'Signature en ligne%'`)),
    ).toBe(1);

    // Re-visite : état « accord confirmé », plus aucun canvas ni bouton
    await page.goto(`/public/demande/${token}`);
    await expect(page.getByTestId('etat-accord-confirme')).toBeVisible({ timeout: 15000 });
    await expect(page.getByTestId('bloc-confirmation-telephone')).toHaveCount(0);
    await expect(page.getByTestId('btn-confirmer-signature')).toHaveCount(0);
  });

  /* ────────────── 4. Gardes 409/400 ────────────── */

  test('gardes : demande déjà décidée, refus après accord tél, demande non chiffrée, signature manquante', async ({ request }) => {
    await ensureDecisionTelephone(request, dem.refus, 'refuse');
    await ensureDecisionTelephone(request, dem.accord, 'accepte');

    // Décision tél sur une demande déjà refusée → 409
    const dejaRefusee = await request.post(`/api/demandes-travaux-supp/${dem.refus.id}/decision-telephone`, {
      headers: adminAuthHeaders(),
      data: { decision: 'accepte', canal_envoi: 'email' },
    });
    expect(dejaRefusee.status()).toBe(409);
    expect((await dejaRefusee.json()).error).toContain('Décision déjà enregistrée');

    // Décision tél sur une demande déjà acceptée (et signée) → 409
    const dejaAcceptee = await request.post(`/api/demandes-travaux-supp/${dem.accord.id}/decision-telephone`, {
      headers: adminAuthHeaders(),
      data: { decision: 'refuse' },
    });
    expect(dejaAcceptee.status()).toBe(409);

    // Accord tél sur une demande non chiffrée → 409, statut intact
    const nonChiffree = await request.post(`/api/demandes-travaux-supp/${dem.nonChiffree.id}/decision-telephone`, {
      headers: adminAuthHeaders(),
      data: { decision: 'accepte' },
    });
    expect(nonChiffree.status()).toBe(409);
    expect((await nonChiffree.json()).error).toContain('sans prestations chiffrées');
    expect(sql(`SELECT statut FROM demandes_travaux_supp WHERE id = ${dem.nonChiffree.id}`)).toBe('en_attente_validation');

    // Accord tél posé sur dem.garde, puis tentative de REFUS en ligne → 409
    await accordTelephoneApi(request, dem.garde.id);
    const refusEnLigne = await request.post(`/api/public/demandes-travaux-supp/${dem.garde.token}/decision`, {
      data: { decision: 'refuse' },
    });
    expect(refusEnLigne.status()).toBe(409);
    expect((await refusEnLigne.json()).error).toContain('Accord déjà enregistré par téléphone');

    // Confirmation sans signature → 400
    const sansSignature = await request.post(`/api/public/demandes-travaux-supp/${dem.garde.token}/decision`, {
      data: { decision: 'accepte' },
    });
    expect(sansSignature.status()).toBe(400);
    expect((await sansSignature.json()).error).toContain('Signature requise');

    // Rien n'a bougé : l'accord tél reste enregistré, toujours non signé
    expect(sql(`SELECT statut FROM demandes_travaux_supp WHERE id = ${dem.garde.id}`)).toBe('accepte');
    expect(sql(`SELECT decision_canal FROM demandes_travaux_supp WHERE id = ${dem.garde.id}`)).toBe('staff_telephone');
    expect(sql(`SELECT signed_at IS NULL FROM demandes_travaux_supp WHERE id = ${dem.garde.id}`)).toBe('t');
    expect(
      sql(`SELECT o.statut FROM ordres_reparation o JOIN demandes_travaux_supp d ON d.or_complementaire_id = o.id WHERE d.id = ${dem.garde.id}`),
    ).toBe('en_attente_signature');
  });

  /* ────────────── 5. Portail client ────────────── */

  test('portail client : encart confirmation, refus impossible (409), signature OK', async ({ page, request }) => {
    const { id } = dem.portail;
    await accordTelephoneApi(request, id);

    await loginAsClient(page);
    await page.goto(`${PORTAL}/rdvs/${rdv.portail.id}`);

    const blocDemandes = page.getByTestId('demandes-travaux');
    await expect(blocDemandes).toBeVisible({ timeout: 15000 });
    const encart = blocDemandes.getByTestId('bloc-confirmation-telephone');
    await expect(encart).toBeVisible();
    await expect(encart).toContainText(/accord par téléphone/i);
    // Plus de boutons accepter/refuser classiques : la décision est déjà prise
    await expect(blocDemandes.getByTestId('btn-accepter-travaux')).toHaveCount(0);
    await expect(blocDemandes.getByTestId('btn-refuser-travaux')).toHaveCount(0);

    // Garde côté portail : un refus après accord tél est rejeté
    const refus = await page.request.post(`${API}/api/client/demandes-travaux-supp/${id}/decision`, {
      data: { decision: 'refuse' },
    });
    expect(refus.status()).toBe(409);

    // Confirmation par signature via le SignatureModal existant
    await encart.getByTestId('btn-confirmer-travaux-tel').click();
    const canvas = page.locator('.sig-canvas');
    await expect(canvas).toBeVisible();
    await drawSignature(page, canvas);

    const postResponse = page.waitForResponse(
      (res) => res.url().includes(`/demandes-travaux-supp/${id}/decision`) && res.request().method() === 'POST',
    );
    await page.locator('.sig-modal').getByRole('button', { name: /confirmer et signer/i }).click();
    expect((await postResponse).status()).toBe(200);

    // Persistance + OR signé, canal inchangé
    expect(sql(`SELECT signed_at IS NOT NULL FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('t');
    expect(sql(`SELECT decision_canal FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('staff_telephone');
    expect(
      sql(`SELECT o.statut FROM ordres_reparation o JOIN demandes_travaux_supp d ON d.or_complementaire_id = o.id WHERE d.id = ${id}`),
    ).toBe('signe');

    // L'UI bascule sur l'état confirmé
    await expect(blocDemandes.getByTestId('etat-accord-confirme')).toBeVisible({ timeout: 10000 });
    await expect(blocDemandes.getByTestId('bloc-confirmation-telephone')).toHaveCount(0);
  });

  test('isolation inter-clients : la demande en attente de confirmation d\'un autre client est inaccessible', async ({ page, request }) => {
    const { id } = dem.isolation;
    await accordTelephoneApi(request, id); // client tiers sans email → décision enregistrée quand même
    expect(sql(`SELECT statut FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('accepte');

    await loginAsClient(page);

    // Le RDV de l'autre client est invisible
    const foreignRdv = await page.request.get(`${API}/api/client/rdvs/${rdv.isolation.id}`);
    expect(foreignRdv.status()).toBe(404);

    // Et sa demande ne peut pas être signée depuis le compte jean.moreau
    const foreignSign = await page.request.post(`${API}/api/client/demandes-travaux-supp/${id}/decision`, {
      data: { decision: 'accepte', signature: SIGNATURE_DATA_URL },
    });
    expect(foreignSign.status()).toBe(404);
    expect(sql(`SELECT signed_at IS NULL FROM demandes_travaux_supp WHERE id = ${id}`)).toBe('t');
  });

  /* ────────────── 6. KPI pilote ────────────── */

  test('KPI : staff_telephone dans le par-canal + compteur accords en attente de signature', async ({ request }) => {
    const getPilote = async () => {
      const res = await request.get('/api/analytics/dashboard', { headers: adminAuthHeaders() });
      expect(res.status()).toBe(200);
      return (await res.json()).pilote;
    };

    const before = await getPilote();
    expect(before.decisions_travaux_supp_par_canal).toHaveProperty('staff_telephone');
    expect(before).toHaveProperty('accords_telephone_en_attente_signature');
    const canalBefore = before.decisions_travaux_supp_par_canal.staff_telephone;
    const attenteBefore = before.accords_telephone_en_attente_signature;

    // Un accord tél de plus → +1 au par-canal ET +1 au stock en attente
    await accordTelephoneApi(request, dem.kpi.id);
    const after = await getPilote();
    expect(after.decisions_travaux_supp_par_canal.staff_telephone).toBe(canalBefore + 1);
    expect(after.accords_telephone_en_attente_signature).toBe(attenteBefore + 1);
    // Le délai moyen de décision reste réservé aux canaux en ligne : il ne
    // devient jamais négatif à cause d'un accord tél
    expect(after.delai_decision_moyen_minutes === null || after.delai_decision_moyen_minutes >= 0).toBe(true);

    // La signature de confirmation sort la demande du stock en attente,
    // sans toucher au par-canal (la décision reste téléphonique)
    const sign = await request.post(`/api/public/demandes-travaux-supp/${dem.kpi.token}/decision`, {
      data: { decision: 'accepte', signature: SIGNATURE_DATA_URL },
    });
    expect(sign.status()).toBe(200);

    const final = await getPilote();
    expect(final.decisions_travaux_supp_par_canal.staff_telephone).toBe(canalBefore + 1);
    expect(final.accords_telephone_en_attente_signature).toBe(attenteBefore);
  });
});
