import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const modulePath = path.resolve(__dirname, '../modules/planning-ui.js');
const moduleSource = fs.readFileSync(modulePath, 'utf8');

function escape(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function timeToMinutes(value) {
  const [hours, minutes] = String(value || '00:00').split(':').map((part) => Number.parseInt(part, 10) || 0);
  return (hours * 60) + minutes;
}

function minutesToTimeLabel(totalMinutes) {
  const safe = Math.max(0, Number.parseInt(totalMinutes, 10) || 0);
  const hours = String(Math.floor(safe / 60)).padStart(2, '0');
  const minutes = String(safe % 60).padStart(2, '0');
  return `${hours}:${minutes}`;
}

function loadPlanningUiModule() {
  global.window = {};
  global.APP = { planningRdvs: [] };
  global.escapeHtml = escape;
  global.escapeAttr = escape;
  global.formatTime = (value) => String(value || '').slice(0, 5);
  global.statusBadge = (status) => `<span class="badge">${escape(status)}</span>`;
  global.actionButtons = () => '<button class="btn">Actions</button>';
  global.closeModal = () => {};
  global.focusClientFromAnywhere = () => {};

  window.PlanningUtils = {
    timeToMinutes,
    getRdvDurationMinutes: (rdv) => Number.parseInt(rdv?.temps_estime || rdv?.temps_estime_minutes || 60, 10) || 60,
    minutesToTimeLabel,
  };

  window.PlanningModule = {
    isMecanicienAbsentOn: () => false,
    getPlanningResources: () => ({
      ponts: [{ id: 3, nom: 'Pont A' }],
      mecaniciens: [{ id: 7, prenom: 'Léo', nom: 'Martin' }],
    }),
    computePlanningEffectiveEndMinutes: (_date, startLabel, durationMinutes) => timeToMinutes(startLabel) + durationMinutes,
  };

  vm.runInThisContext(moduleSource, { filename: modulePath });
  return window.PlanningUiModule;
}

test('planning modal rendering keeps the client deeplink and workflow summary', () => {
  const planningUi = loadPlanningUiModule();
  const html = planningUi.renderPlanningRdvModal({
    id: 15,
    date_rdv: '2026-04-08',
    heure_rdv: '09:30:00',
    temps_estime: 90,
    statut: 'confirme',
    type_intervention: 'Vidange complète',
    client: {
      id: 42,
      prenom: 'Alice',
      nom: 'Durand',
      telephone: '0601020304',
      email: 'alice@example.com',
    },
    vehicule: {
      marque: 'Yamaha',
      modele: 'MT-07',
      plaque: 'AB-123-CD',
    },
    mecanicien_id: 7,
    pont_id: 3,
    workflow_history: [
      {
        from_status: 'reserve',
        to_status: 'confirme',
        by: 'atelier',
        role: 'reception',
        at: '2026-04-08T08:15:00',
      },
    ],
    commentaire: 'Prévoir contrôle transmission.',
  });

  assert.match(html, /Voir la fiche client/);
  assert.match(html, /focusClientFromAnywhere\(42\)/);
  assert.match(html, /Vidange complète/);
  assert.match(html, /Historique workflow/);
});

test('planning helpers keep stable status and duration formatting', () => {
  const planningUi = loadPlanningUiModule();

  assert.equal(planningUi.formatDurationLabel(90), '1h30');
  assert.deepEqual(planningUi.getPlanningStatusMeta('reception'), {
    icon: '📥',
    label: 'Receptionne',
    tone: 'warning',
  });

  const warnings = planningUi.getPlanningRdvWarnings({
    id: 18,
    date_rdv: '2026-04-08',
    heure_rdv: '10:00:00',
    temps_estime: 60,
    statut: 'reserve',
    mecanicien_id: null,
    pont_id: null,
  });

  assert.ok(warnings.length >= 2);
  assert.equal(warnings[0].title, 'Prochaine etape');
});
