import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const modulePath = path.resolve(__dirname, '../modules/or.js');
const moduleSource = fs.readFileSync(modulePath, 'utf8');

function escape(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function loadOrModule() {
  const elements = new Map();
  const ensureElement = (id) => {
    if (!elements.has(id)) {
      elements.set(id, { id, innerHTML: '', textContent: '', style: {} });
    }
    return elements.get(id);
  };

  global.window = {
    RdvActionsModule: {
      actionButtons: (rdv) => `<div class="workflow-probe">FLOW-${rdv?.statut || 'unknown'}</div>`,
    },
  };
  global.APP = {
    mecaniciens: [{ id: 7, prenom: 'Léo', nom: 'Martin' }],
    ponts: [{ id: 3, nom: 'Pont A' }],
  };
  global.document = {
    getElementById: ensureElement,
    createElement: () => ({ style: {}, remove() {}, closest() { return null; } }),
    body: { appendChild() {} },
  };
  global.escapeHtml = escape;
  global.formatTime = (value) => String(value || '').slice(0, 5);
  global.showAlert = () => {};
  global.openProtectedDocument = () => Promise.resolve();
  global.apiGet = () => Promise.reject(new Error('not used in unit test'));
  global.console = console;

  vm.runInThisContext(moduleSource, { filename: modulePath });
  return { orModule: window.OrModule, elements };
}

test('OR dashboard hides PDF action when no generated OR exists', () => {
  const { orModule, elements } = loadOrModule();

  orModule.renderOrdresReparation([
    {
      id: 31,
      statut: 'confirme',
      date_rdv: '2026-04-08',
      heure_rdv: '09:30:00',
      temps_estime: 60,
      type_intervention: 'Révision',
      pont_id: 3,
      mecanicien_id: 7,
      client: { prenom: 'Alice', nom: 'Durand', telephone: '0601020304' },
      vehicule: { marque: 'Yamaha', modele: 'MT-07', plaque: 'AB-123-CD' },
      ordres_reparation: [],
    },
  ]);

  const html = elements.get('or-list').innerHTML;
  assert.doesNotMatch(html, />PDF</);
  assert.match(html, /Tour atelier OR/);
  assert.match(html, /Action recommandee/);
  assert.match(html, /FLOW-confirme/);
  assert.match(html, /Passer en reception/);
  assert.match(html, /Apercu master/);
});

test('OR dashboard shows PDF action once an OR has been generated', () => {
  const { orModule, elements } = loadOrModule();

  orModule.renderOrdresReparation([
    {
      id: 32,
      statut: 'termine',
      date_rdv: '2026-04-08',
      heure_rdv: '13:30:00',
      temps_estime: 90,
      type_intervention: 'Diagnostic',
      pont_id: 3,
      mecanicien_id: 7,
      client: { prenom: 'Nina', nom: 'Roux', telephone: '0611223344' },
      vehicule: { marque: 'Honda', modele: 'CB650R', plaque: 'CD-456-EF' },
      ordres_reparation: [{ id: 99, type_or: 'initial', created_at: '2026-04-08T10:00:00' }],
    },
  ]);

  const html = elements.get('or-list').innerHTML;
  assert.match(html, />PDF</);
  assert.match(html, /telechargerOR\(32,99\)/);
  assert.match(html, /FLOW-termine/);
  assert.match(html, /Restituer le vehicule/);
});

test('OR sheet keeps the original booking price when supplementary work updates the RDV total', () => {
  const { orModule } = loadOrModule();

  const html = orModule.getOrSheetHtml({
    id: 33,
    statut: 'en_cours',
    date_rdv: '2026-04-08',
    heure_rdv: '15:00:00',
    temps_estime: 120,
    type_intervention: 'Revision atelier',
    prix_estime: 300,
    etat_vehicule: JSON.stringify({
      booking_price: 120,
      estimate_rows: [{ label: 'Revision atelier', qty: 1, amount: 120 }],
    }),
    pont_id: 3,
    mecanicien_id: 7,
    client: { prenom: 'Marc', nom: 'Petit', telephone: '0600112233' },
    vehicule: { marque: 'Triumph', modele: 'Street Triple', plaque: 'EF-789-GH' },
    ordres_reparation: [
      {
        id: 201,
        type_or: 'initial',
        etat_vehicule: JSON.stringify({
          booking_price: 120,
          estimate_rows: [{ label: 'Revision atelier', qty: 1, amount: 120 }],
        }),
        created_at: '2026-04-08T09:00:00',
      },
      { id: 202, type_or: 'supplementaire', travaux: 'Kit chaine', montant_estime: 180, created_at: '2026-04-08T11:00:00' },
    ],
  });

  assert.match(html, /120,00 €/);
  assert.doesNotMatch(html, /180,00 €/);
  assert.doesNotMatch(html, /300,00 €/);
  assert.doesNotMatch(html, /480,00 €/);
});
