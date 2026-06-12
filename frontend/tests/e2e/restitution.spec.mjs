import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

/**
 * Flux public de restitution (signature client au retrait du véhicule).
 * Non-régression des deux bugs qui rendaient le flux totalement mort :
 *  - alias DQL réservé `or` dans PublicSuiviController (QueryException sur GET et POST)
 *  - liste blanche du OrdreReparationFreezeListener sans les champs de restitution
 *    (DomainException sur chaque signature)
 *
 * Setup : un OR seedé est remis en `intervention_signee` directement en base
 * (idempotent — le test re-signe le même OR à chaque run).
 */

const PSQL = 'docker compose exec -T db psql -U atelier -d atelier_moto -tA -c';
const OR_ID = 1;

// Pixel transparent — il faut juste un dataURL image valide pour l'API
const SIGNATURE_DATA_URL =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function sql(query) {
  return execSync(`${PSQL} "${query.replace(/"/g, '\\"')}"`, { encoding: 'utf8' }).trim();
}

let token;

test.describe('Restitution publique', () => {
  test.beforeAll(() => {
    sql(
      `UPDATE ordres_reparation SET statut = 'intervention_signee', ` +
      `signature_mecanicien = COALESCE(signature_mecanicien, '${SIGNATURE_DATA_URL}'), ` +
      `signature_client_restitution = NULL, signe_client_restitution_at = NULL WHERE id = ${OR_ID}`
    );
    token = sql(`SELECT r.token_suivi FROM rendez_vous r JOIN ordres_reparation o ON o.rendez_vous_id = r.id WHERE o.id = ${OR_ID}`);
    expect(token.length).toBeGreaterThanOrEqual(16);
  });

  test('GET restitution : les données sont servies (régression alias DQL)', async ({ request }) => {
    const res = await request.get(`/api/public/restitution/${token}`);
    expect(res.status()).toBe(200);
    const data = await res.json();
    expect(data.ordre.id).toBe(OR_ID);
    expect(data.ordre.signature_mecanicien).toBe(true);
    expect(data.ordre.signature_client_restitution).toBe(false);
  });

  test('token inconnu : 404 propre, pas de 500', async ({ request }) => {
    const res = await request.get('/api/public/restitution/token-inexistant-0123456789');
    expect(res.status()).toBe(404);
  });

  test('la page est accessible sans authentification (régression middleware)', async ({ page }) => {
    await page.goto(`/restitution/${token}`);
    await expect(page.getByRole('heading', { name: /restitution du véhicule/i })).toBeVisible({ timeout: 15000 });
    expect(page.url()).not.toContain('/login');
  });

  test('signature du client : flux complet via le canvas', async ({ page }) => {
    await page.goto(`/restitution/${token}`);
    const canvas = page.locator('canvas');
    await expect(canvas).toBeVisible({ timeout: 15000 });

    // Trace un trait sur le canvas de signature
    const box = await canvas.boundingBox();
    await page.mouse.move(box.x + 30, box.y + 60);
    await page.mouse.down();
    await page.mouse.move(box.x + 150, box.y + 90, { steps: 12 });
    await page.mouse.move(box.x + 260, box.y + 50, { steps: 12 });
    await page.mouse.up();

    const signResponse = page.waitForResponse(
      (res) => res.url().includes('/restitution/') && res.url().endsWith('/sign') && res.request().method() === 'POST'
    );
    await page.getByRole('button', { name: /valider|signer/i }).click();
    const response = await signResponse;

    // Régression freeze listener : la signature ne doit plus jeter de DomainException (500)
    expect(response.status()).toBe(200);
    const body = await response.json();
    expect(body.success).toBe(true);
    expect(body.statut).toBe('signe');

    await expect(page.getByRole('heading', { name: /restitution signée/i })).toBeVisible({ timeout: 10000 });
  });

  test('après signature : l\'état est persisté et le re-sign est refusé', async ({ request }) => {
    const statut = sql(`SELECT statut FROM ordres_reparation WHERE id = ${OR_ID}`);
    expect(statut).toBe('signe');

    const res = await request.post(`/api/public/restitution/${token}/sign`, {
      data: { signature: SIGNATURE_DATA_URL },
    });
    expect(res.status()).toBe(400); // NOT_READY : déjà signé
  });
});
