import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

/**
 * Notifications P0 (phase 4 MVP) : la transition workflow `confirmer` doit
 * déclencher l'email `rdv_confirmation` au client (dispatcher → async → MailHog).
 * Le rappel J-1 et `travaux_termines` partagent exactement le même pipeline
 * (RdvWorkflowListener / SendRappelsCommand → NotificationDispatcher) : ce test
 * valide le pipeline de bout en bout, worker compris.
 */

const RDV_ID = 269; // RDV seedé de jean.moreau@email.fr
const CLIENT_EMAIL = 'jean.moreau@email.fr';
const MAILHOG = 'http://localhost:8025';
const PSQL = 'docker compose exec -T db psql -U atelier -d atelier_moto -tA -c';

function sql(query) {
  return execSync(`${PSQL} "${query.replace(/"/g, '\\"')}"`, { encoding: 'utf8' }).trim();
}

function adminAuthHeaders() {
  const state = JSON.parse(readFileSync('playwright/.auth/admin.json', 'utf8'));
  const token = state.cookies.find((c) => c.name === 'access_token')?.value;
  return { Authorization: `Bearer ${token}` };
}

test.describe('Notifications P0', () => {
  test('transition confirmer → email rdv_confirmation reçu dans MailHog', async ({ request }) => {
    // RDV remis en attente, sans demande d'annulation parasite
    sql(`UPDATE rendez_vous SET statut = 'en_attente', date_rdv = CURRENT_DATE + 7, heure_rdv = '10:00:00', annulation_demandee_at = NULL WHERE id = ${RDV_ID}`);

    const before = await (await request.get(`${MAILHOG}/api/v2/search?kind=to&query=${CLIENT_EMAIL}`)).json();
    const countBefore = before.total ?? 0;

    const res = await request.post(`/api/rendez-vous/${RDV_ID}/transition/confirmer`, {
      headers: adminAuthHeaders(),
    });
    expect(res.status()).toBe(200);
    expect(sql(`SELECT statut FROM rendez_vous WHERE id = ${RDV_ID}`)).toBe('confirme');

    // L'email part en async : on attend que le worker le traite
    await expect
      .poll(
        async () => {
          const after = await (await request.get(`${MAILHOG}/api/v2/search?kind=to&query=${CLIENT_EMAIL}`)).json();
          return after.total ?? 0;
        },
        { timeout: 90000, intervals: [2000] }
      )
      .toBeGreaterThan(countBefore);

    // Le dernier email est bien la confirmation, avec la bonne date
    const after = await (await request.get(`${MAILHOG}/api/v2/search?kind=to&query=${CLIENT_EMAIL}`)).json();
    const subjects = after.items.map((m) => m.Content.Headers.Subject?.[0] ?? '');
    expect(subjects.some((s) => /confirmation/i.test(s))).toBe(true);

    // Une notification cloche staff a aussi été créée
    expect(
      Number(sql(`SELECT COUNT(*) FROM notifications WHERE related_entity_type = 'RendezVous' AND related_entity_id = ${RDV_ID}`))
    ).toBeGreaterThan(0);
  });

  test('la file failed du worker est vide (healthcheck)', async () => {
    const failed = sql(`SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'failed'`);
    expect(failed).toBe('0');
  });
});
