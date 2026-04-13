import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const modulePath = path.resolve(__dirname, '../modules/planning.js');
const moduleSource = fs.readFileSync(modulePath, 'utf8');

function createNode(id) {
  return {
    id,
    innerHTML: '',
    textContent: '',
    style: {},
    clientWidth: id === 'planning-grid' ? 1280 : 0,
    querySelector() { return null; },
    appendChild() {},
  };
}

function timeToMinutes(value) {
  const [hours, minutes] = String(value || '00:00').split(':').map((part) => Number.parseInt(part, 10) || 0);
  return (hours * 60) + minutes;
}

function loadPlanningModule() {
  const elements = new Map();
  const ensure = (id) => {
    if (!elements.has(id)) elements.set(id, createNode(id));
    return elements.get(id);
  };

  global.window = {};
  global.APP = {
    planningMecaFilters: [],
    _planningAbsences: [],
    _planningSlotPx: 48,
    _planningSlotMinutes: 60,
  };
  global.document = {
    getElementById: ensure,
    createElement: (tag) => createNode(tag),
  };
  global.escapeHtml = (value) => String(value ?? '');
  global.escapeAttr = (value) => String(value ?? '');
  global.formatTime = (value) => String(value || '').slice(0, 5);
  global.hexToRgba = () => 'rgba(59,130,246,.14)';
  global._rdvDateToStr = (value) => {
    if (typeof value === 'string') return value.slice(0, 10);
    return new Date(value).toISOString().slice(0, 10);
  };
  global.window.PlanningUtils = {
    getPlanningBounds: () => ({ start: '08:00', end: '09:00' }),
    buildPlanningSlots: () => ['08:00'],
    renderPlanningNowLine: () => {},
    buildVisualOverlapGroups: () => {},
    markConflictCells: () => {},
    timeToMinutes,
    getRdvDurationMinutes: (rdv) => Number.parseInt(rdv?.temps_estime || 60, 10) || 60,
  };

  vm.runInThisContext(moduleSource, { filename: modulePath });

  window.PlanningModule.buildPlanningBusyCells = () => ({});
  window.PlanningModule.isPlanningSlotOpen = () => true;
  window.PlanningModule.getSlotResourceAvailability = () => ({ hasCapacity: true, reason: 'Disponible', summary: 'Disponible' });
  window.PlanningModule.renderPlanningEventLayer = () => {};
  window.PlanningModule.getPlanningResources = () => ({ mecaniciens: [], ponts: [] });
  window.PlanningModule.formatPlanningRange = () => '06 avr - 12 avr';
  window.PlanningModule.getPlanningDisplayName = () => 'Atelier Test';

  return { planningModule: window.PlanningModule, elements };
}

test('planning summary keeps a single status legend in the header area', () => {
  const { planningModule, elements } = loadPlanningModule();

  planningModule.renderPlanningGrid([], new Date('2026-04-06T00:00:00Z'));

  const summaryHtml = elements.get('planning-conflict-summary').innerHTML;
  assert.match(summaryHtml, /Semaine propre/);
  assert.doesNotMatch(summaryHtml, /planning-status-legend/);
  assert.doesNotMatch(summaryHtml, /Arrivee \/ reception/);
});
