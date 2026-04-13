import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const modulePath = path.resolve(__dirname, '../modules/rdv-actions.js');
const moduleSource = fs.readFileSync(modulePath, 'utf8');

function loadRdvActionsModule(overrides = {}) {
  global.window = {};
  global.APP = {
    rdvs: [],
    planningRdvs: [],
    _mecaLastRdvs: [],
    mecaniciens: [],
  };
  global.document = overrides.document || {
    getElementById: () => null,
  };
  global.closeModal = overrides.closeModal || (() => {});
  global.showNotificationToast = overrides.showNotificationToast || (() => {});
  global.refreshCurrentSection = overrides.refreshCurrentSection || (() => {});
  global.apiPut = overrides.apiPut || (() => Promise.reject(new Error('not used in unit test')));
  global.apiPost = overrides.apiPost || (() => Promise.reject(new Error('not used in unit test')));
  global.hasPermission = overrides.hasPermission || ((permission) => permission === 'rdv.edit' || permission === 'workflow.manage');
  global.canUseBilling = overrides.canUseBilling || (() => true);
  global.alert = overrides.alert || (() => {});
  global.showModal = overrides.showModal || (() => {});
  global.telechargerOR = () => {};
  global.ouvrirDetailRdv = () => {};
  global.ouvrirCheckup = () => {};
  global.ouvrirReception = () => {};
  global.ouvrirAssignation = () => {};
  global.annulerRdv = () => {};
  global.confirmerRdv = () => {};
  global.demarrerTravail = () => {};
  global.terminerTravail = () => {};

  vm.runInThisContext(moduleSource, { filename: modulePath });
  return window.RdvActionsModule;
}

test('workflow actions switch from billing to restitution once work is finished', () => {
  const rdvActions = loadRdvActionsModule();

  const html = rdvActions.actionButtons({
    id: 12,
    statut: 'termine',
    ordres_reparation: [{ id: 3, type_or: 'initial' }],
  }, false);

  assert.match(html, /Cloturer/);
  assert.match(html, /OR PDF/);
  assert.doesNotMatch(html, /Facturer/);
  assert.doesNotMatch(html, /Encaisser/);
  assert.doesNotMatch(html, /Facture PDF/);
});

test('restitution action targets the dedicated closure endpoint', async () => {
  const calls = [];
  const refreshes = [];
  const rdvActions = loadRdvActionsModule({
    apiPost: (url) => {
      calls.push(url);
      return Promise.resolve({ json: () => Promise.resolve({ statut: 'restitue' }) });
    },
  });

  rdvActions.applyImmediateRefresh = (rdvId, patch, message) => {
    refreshes.push({ rdvId, patch, message });
  };

  await rdvActions.restituerRdv(44);

  assert.deepEqual(calls, ['/api/rendez-vous/44/restituer']);
  assert.equal(refreshes.length, 1);
  assert.equal(refreshes[0].rdvId, 44);
  assert.equal(refreshes[0].message, 'RDV cloture apres restitution');
  assert.equal(refreshes[0].patch.statut, 'restitue');
});