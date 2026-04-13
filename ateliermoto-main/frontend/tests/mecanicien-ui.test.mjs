import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const modulePath = path.resolve(__dirname, '../modules/mecanicien.js');
const moduleSource = fs.readFileSync(modulePath, 'utf8');

function escape(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function loadMecanicienModule(overrides = {}) {
  global.window = {};
  global.APP = {
    ponts: [{ id: 3, nom: 'Pont A' }],
    _mecaCheckupData: null,
  };
  global.escapeHtml = escape;
  global.formatTime = (value) => String(value || '').slice(0, 5);
  global.getMecaInitials = (meca) => `${String(meca?.prenom || 'M').charAt(0)}${String(meca?.nom || 'C').charAt(0)}`;
  global.hexToRgba = () => 'rgba(139,92,246,0.3)';
  global.document = overrides.document || {
    getElementById: () => null,
    querySelector: () => null,
  };
  global.showModal = overrides.showModal || (() => {});
  global.apiGet = overrides.apiGet || (() => Promise.reject(new Error('not used in unit test')));
  global.apiPost = overrides.apiPost || (() => Promise.reject(new Error('not used in unit test')));
  global.closeModal = overrides.closeModal || (() => {});
  global.refreshCurrentSection = overrides.refreshCurrentSection || (() => {});
  global.alert = overrides.alert || (() => {});

  window.PlanningUtils = {
    getRdvDurationMinutes: (rdv) => Number.parseInt(rdv?.temps_estime || 60, 10) || 60,
    parseUTCDate: (value) => new Date(value),
  };

  vm.runInThisContext(moduleSource, { filename: modulePath });
  return window.MecanicienModule;
}

test('todo card keeps pre-reception jobs non-startable', () => {
  const mecanicien = loadMecanicienModule();
  const html = mecanicien.renderMecaCard({
    id: 17,
    statut: 'confirme',
    heure_rdv: '09:30:00',
    temps_estime: 60,
    type_intervention: 'Révision',
    pont_id: 3,
    client: { prenom: 'Alice', nom: 'Durand', telephone: '0601020304' },
    vehicule: { marque: 'Yamaha', modele: 'MT-07' },
  }, 'todo');

  assert.match(html, /Reception requise/i);
  assert.doesNotMatch(html, /DEMARRER/);
});

test('todo card exposes the start action only after reception', () => {
  const mecanicien = loadMecanicienModule();
  const html = mecanicien.renderMecaCard({
    id: 18,
    statut: 'reception',
    heure_rdv: '10:00:00',
    temps_estime: 45,
    type_intervention: 'Diagnostic',
    pont_id: 3,
    client: { prenom: 'Léo', nom: 'Martin', telephone: '0603040506' },
    vehicule: { marque: 'Honda', modele: 'CB650R' },
  }, 'todo');

  assert.match(html, /DEMARRER/);
});

test('active intervention panel offers a direct client call action', () => {
  const mecanicien = loadMecanicienModule();
  const html = mecanicien.renderMecaActivePanel({
    id: 19,
    statut: 'en_cours',
    heure_debut_travail: '2026-04-08T09:00:00',
    temps_estime: 90,
    type_intervention: 'Vidange complète',
    pont_id: 3,
    client: { prenom: 'Nina', nom: 'Roux', telephone: '0611223344' },
    vehicule: { marque: 'Triumph', modele: 'Street Triple' },
  });

  assert.match(html, /Appeler client/);
  assert.match(html, /tel:0611223344/);
  assert.match(html, /Raccourcis atelier/);
  assert.match(html, /Temps prevu/);
});

test('OR actions stay hidden until an order is actually generated', () => {
  const mecanicien = loadMecanicienModule();

  const withoutOr = mecanicien.renderMecaCard({
    id: 20,
    statut: 'confirme',
    heure_rdv: '11:00:00',
    temps_estime: 60,
    type_intervention: 'Controle',
    pont_id: 3,
    client: { prenom: 'Mila', nom: 'Noir', telephone: '0600000000' },
    vehicule: { marque: 'BMW', modele: 'F900R' },
    ordres_reparation: [],
  }, 'todo');

  const withOr = mecanicien.renderMecaCard({
    id: 21,
    statut: 'termine',
    heure_rdv: '13:30:00',
    temps_estime: 60,
    type_intervention: 'Revision',
    pont_id: 3,
    client: { prenom: 'Mila', nom: 'Noir', telephone: '0600000000' },
    vehicule: { marque: 'BMW', modele: 'F900R' },
    ordres_reparation: [{ id: 4, type_or: 'initial', created_at: '2026-04-08T10:00:00' }],
  }, 'done');

  assert.doesNotMatch(withoutOr, />OR</);
  assert.match(withOr, />OR</);
});

test('checkup modal only offers intervention closure while work is in progress', () => {
  let activeModalHtml = '';
  let doneModalHtml = '';
  const mecanicien = loadMecanicienModule({
    showModal: (_title, html) => {
      if (!activeModalHtml) {
        activeModalHtml = html;
        return;
      }
      doneModalHtml = html;
    },
  });

  mecanicien.renderCheckupModal(30, null, { statut: 'en_cours' });
  mecanicien.renderCheckupModal(31, null, { statut: 'termine' });

  assert.match(activeModalHtml, /Terminer l'intervention/);
  assert.match(activeModalHtml, /Sauvegarder \(en cours\)/);
  assert.doesNotMatch(doneModalHtml, /Terminer l'intervention/);
  assert.match(doneModalHtml, /Sauvegarder le rapport/);
});

test('saving checkup targets the atomic finish endpoint only for intervention closure', async () => {
  const calls = [];
  const documentStub = {
    getElementById(id) {
      const elements = {
        'checkup-alertes': { value: 'Batterie faible' },
        'checkup-recommandations': { value: 'Controle charge' },
        'checkup-travaux': { value: 'Essai et mesure' },
      };
      return elements[id] || null;
    },
    querySelector() {
      return null;
    },
  };

  const mecanicien = loadMecanicienModule({
    document: documentStub,
    apiPost: (url, data) => {
      calls.push({ url, data });
      return Promise.resolve({
        json: () => Promise.resolve({ rapport: data }),
      });
    },
  });

  await mecanicien.sauverCheckup(41, 'en_cours');
  await mecanicien.sauverCheckup(41, 'termine');

  assert.equal(calls[0].url, '/api/rendez-vous/41/rapport-technicien');
  assert.equal(calls[1].url, '/api/rendez-vous/41/terminer-avec-rapport');
  assert.equal(calls[1].data.statut, 'termine');
});
