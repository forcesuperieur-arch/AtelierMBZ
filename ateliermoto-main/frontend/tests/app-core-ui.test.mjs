import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const modulePath = path.resolve(__dirname, '../modules/app-core.js');
const moduleSource = fs.readFileSync(modulePath, 'utf8');

function createNode(id) {
  return {
    id,
    style: {},
    textContent: '',
    classList: {
      add() {},
      remove() {},
      contains() { return false; },
    },
    focus() {},
  };
}

function loadAppCoreModule() {
  const elements = new Map();
  const ensure = (id) => {
    if (!elements.has(id)) elements.set(id, createNode(id));
    return elements.get(id);
  };

  global.window = {};
  global.APP = {
    currentUser: { role: 'receptionnaire' },
    roleSections: ['dashboard', 'clients'],
    rolePermissions: [],
  };
  global.document = {
    getElementById: ensure,
    querySelectorAll: () => ({ forEach() {} }),
  };
  global.cleanupMecaTimer = () => {};
  global.closeSidebar = () => {};
  global.loadDashboard = () => {};
  global.loadRdvForm = () => {};
  global.loadPlanning = () => {};
  global.loadPontsMecas = () => {};
  global.loadOrdresReparation = () => {};
  global.loadSuiviLive = () => {};
  global.loadMotoTechExplorer = () => {};
  global.loadClients = () => {};
  global.loadEspaceMeca = () => {};
  global.loadAdminAteliers = () => {};
  global.switchAdminTab = () => {};
  global.getAuthRole = () => 'receptionnaire';
  global.escapeHtml = (value) => String(value ?? '');
  global.escapeAttr = (value) => String(value ?? '');

  vm.runInThisContext(moduleSource, { filename: modulePath });
  return { appCore: window.AppCoreModule, elements };
}

test('vehicle history subpage stays accessible when clients section is allowed', () => {
  const { appCore, elements } = loadAppCoreModule();

  appCore.showSection('vehicule-history');

  assert.equal(APP.currentSection, 'vehicule-history');
  assert.equal(elements.get('page-title').textContent, 'Historique vehicule');
});

test('billing nav stays hidden from the operational shell', () => {
  const { appCore, elements } = loadAppCoreModule();

  appCore.applyRoleVisibility('admin');

  assert.equal(elements.get('nav-factures').style.display, 'none');
});
