import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const modulePath = path.resolve(__dirname, '../modules/clients.js');
const moduleSource = fs.readFileSync(modulePath, 'utf8');

function escape(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function createElement(id) {
  return {
    id,
    innerHTML: '',
    textContent: '',
    style: {},
    value: '',
    scrollIntoView() {},
  };
}

function flushPromises() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

function loadClientsModule(fixtures) {
  const elements = new Map();
  const ensureElement = (id) => {
    if (!elements.has(id)) elements.set(id, createElement(id));
    return elements.get(id);
  };

  global.window = {};
  global.APP = {
    currentSection: 'clients',
    _pendingClientFocusId: null,
    mecaniciens: [{ id: 7, prenom: 'Léo', nom: 'Martin' }],
    ponts: [{ id: 3, nom: 'Pont A' }],
  };
  global.document = {
    getElementById: ensureElement,
  };
  global.escapeHtml = escape;
  global.escapeAttr = escape;
  global.formatTime = (value) => String(value || '').slice(0, 5);
  global.statusBadge = (status) => `<span class="badge">${escape(status)}</span>`;
  global.hasPermission = () => true;
  global.setLoadingState = () => {};
  global.updateLiveRegion = () => {};
  global.showModal = () => {};
  global.showAlert = () => {};
  global.showNotificationToast = () => {};
  global.openConfirmDialog = (_message, onConfirm) => { if (onConfirm) onConfirm(); };
  global.refreshCurrentSection = () => {};
  global.showOrDetail = () => {};
  global.showSection = (section) => {
    APP.currentSection = section;
  };
  global.apiGet = (url) => {
    if (!(url in fixtures)) {
      return Promise.reject(new Error(`Missing fixture for ${url}`));
    }
    return Promise.resolve({ json: () => Promise.resolve(fixtures[url]) });
  };
  global.apiPost = () => Promise.resolve({ json: () => Promise.resolve({}) });
  global.apiPut = () => Promise.resolve({ json: () => Promise.resolve({}) });
  global.apiDelete = () => Promise.resolve({ json: () => Promise.resolve({}) });
  global.console = console;

  vm.runInThisContext(moduleSource, { filename: modulePath });
  return { clientsModule: window.ClientsModule, elements };
}

test('client detail highlights vehicle history and secondary dossier access', async () => {
  const { clientsModule, elements } = loadClientsModule({
    '/api/clients/7': {
      id: 7,
      prenom: 'Alice',
      nom: 'Durand',
      telephone: '0601020304',
      email: 'alice@example.com',
      vehicules: [
        { id: 11, marque: 'Yamaha', modele: 'Tracer 9 GT', plaque: 'AB-123-CD', annee: 2024, cylindree: '890', type_moto: 'Trail' },
        { id: 12, marque: 'Honda', modele: 'CB650R', plaque: 'CD-456-EF', annee: 2023, cylindree: '649', type_moto: 'Roadster' },
      ],
      historique: [
        {
          id: 101,
          date_rdv: '2026-04-02',
          heure_rdv: '09:30:00',
          statut: 'en_cours',
          type_intervention: 'Revision des 10 000 km',
          prix_estime: 289,
          vehicule: { id: 11, marque: 'Yamaha', modele: 'Tracer 9 GT', plaque: 'AB-123-CD' },
          pont: { nom: 'Pont A' },
          ordres_reparation: [{ id: 501, type_or: 'initial' }],
        },
        {
          id: 102,
          date_rdv: '2026-02-18',
          heure_rdv: '14:00:00',
          statut: 'termine',
          type_intervention: 'Pneus et geometrie',
          prix_final: 420,
          rapport: true,
          vehicule: { id: 11, marque: 'Yamaha', modele: 'Tracer 9 GT', plaque: 'AB-123-CD' },
        },
        {
          id: 103,
          date_rdv: '2025-12-10',
          heure_rdv: '11:00:00',
          statut: 'confirme',
          type_intervention: 'Controle freinage',
          vehicule: { id: 12, marque: 'Honda', modele: 'CB650R', plaque: 'CD-456-EF' },
          ordres_reparation: [],
        },
      ],
      ca_total: 709,
      notes: 'Cliente reguliere piste et route.',
    },
  });

  clientsModule.showClientDetail(7);
  await flushPromises();
  await flushPromises();

  const vehicleHtml = elements.get('client-detail-vehicules').innerHTML;
  const historyHtml = elements.get('client-detail-historique').innerHTML;

  assert.match(vehicleHtml, /Carnet moto/);
  assert.match(vehicleHtml, /Planifier un RDV/);
  assert.match(vehicleHtml, /Historique de cette moto/);
  assert.match(vehicleHtml, /Page dediee/);
  assert.match(historyHtml, /Historique par vehicule/);
  assert.match(historyHtml, /Tracer 9 GT/);
  assert.match(historyHtml, /2 passages/);
  assert.match(historyHtml, /Voir dossier atelier/);
  assert.match(historyHtml, /Pas de dossier actif/);

  clientsModule.openVehiculeHistoryPage(7, 11);
  await flushPromises();

  const dedicatedPageHtml = elements.get('vehicule-history-root').innerHTML;
  assert.equal(APP.currentSection, 'vehicule-history');
  assert.match(dedicatedPageHtml, /Historique complet du vehicule/);
  assert.match(dedicatedPageHtml, /Tracer 9 GT/);
  assert.match(dedicatedPageHtml, /Revision des 10 000 km/);
  assert.doesNotMatch(dedicatedPageHtml, /Controle freinage/);
});
