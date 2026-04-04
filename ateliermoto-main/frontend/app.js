// app.js - Application principale SPA Atelier Moto
// Depend de api.js + utils.js (charges avant)

// ===== STATE GLOBAL =====
var APP = {
    currentSection: 'dashboard',
    ponts: [],
    mecaniciens: [],
    rdvs: [],
    interventionTypes: [],
    prestationsConfig: [],
    categories: [],
    currentUser: null,
    planningWeekOffset: 0,
    refreshInterval: null,
    selectedSlot: null,
    planningRdvs: [],
    planningMonday: null,
    _draggedEl: null,
    _lastTravauxSuppCount: 0,
    planningMecaFilters: [], // IDs des mecaniciens filtres (vide = tous)
    _mecaSelectedRdvId: null,
    _mecaLiveTimer: null,
    _mecaLastRdvs: [],
    planningSelectedAtelierSlug: null,
    adminSelectedAtelierId: null,
    rdvClientPrefill: null,
    roleSections: null,
    rolePermissions: null
};
APP._horairesByDay = {};
APP._horairesLoaded = false;
APP._planningLoadSeq = 0;
APP._planningBusyCells = {};
APP._planningSlotMinutes = 15;
APP._planningSlotPx = 8;
window.APP = APP;

function callModuleMethod(moduleName, methodName, argsLike, fallbackValue) {
    if (window.AtelierDebug && window.AtelierDebug.callModule) {
        return window.AtelierDebug.callModule(moduleName, methodName, argsLike, fallbackValue);
    }
    var moduleRef = window[moduleName];
    if (!moduleRef || typeof moduleRef[methodName] !== 'function') return fallbackValue;
    return moduleRef[methodName].apply(moduleRef, Array.prototype.slice.call(argsLike || []));
}

// ===== INITIALISATION =====
document.addEventListener('DOMContentLoaded', function() {
    // Le cookie access_token est HttpOnly: invisible a JS.
    // On valide la session en appelant directement /api/auth/me.
    apiGet('/api/auth/me').then(function(r) {
        if (!r.ok) throw new Error('not authenticated');
        return r.json();
    }).then(function(me) {
        setAuthRole(me.role);
        initApp();
    }).catch(function() {
        showLogin();
    });
    setupUiAccessibility();
});

function showLogin() { return callModuleMethod('AppCoreModule', 'showLogin', arguments); }
function hideLogin() { return callModuleMethod('AppCoreModule', 'hideLogin', arguments); }
function doLogin() { return callModuleMethod('AppCoreModule', 'doLogin', arguments); }
function logout() { return callModuleMethod('AppCoreModule', 'logout', arguments); }
function initApp() { return callModuleMethod('AppCoreModule', 'initApp', arguments); }
function loadBaseData() { return callModuleMethod('AppCoreModule', 'loadBaseData', arguments, Promise.resolve()); }

// ===== ROLE VISIBILITY =====
function hasPermission(permission) { return callModuleMethod('AppCoreModule', 'hasPermission', arguments, false); }
function canUseBilling() { return callModuleMethod('AppCoreModule', 'canUseBilling', arguments, false); }
function applyRoleVisibility(role) { return callModuleMethod('AppCoreModule', 'applyRoleVisibility', arguments); }
function formatRbacBadges(items, type) { return callModuleMethod('AppCoreModule', 'formatRbacBadges', arguments, ''); }
function getAllowedSections(role) { return callModuleMethod('AppCoreModule', 'getAllowedSections', arguments, []); }

// ===== NAVIGATION =====
function showSection(id) { return callModuleMethod('AppCoreModule', 'showSection', arguments); }
function switchTab(el, tabId) { return callModuleMethod('AppCoreModule', 'switchTab', arguments); }

// ===== HELPERS =====
function formatTime(timeStr) {
    if (!timeStr) return '';
    return timeStr.substring(0, 5);
}

function statusBadge(statut) {
    var map = {
        'reserve': { cls: 'amber', label: 'Reserve' },
        'planifie': { cls: 'blue', label: 'Planifie' },
        'confirme': { cls: 'blue', label: 'Confirme' },
        'reception': { cls: 'teal', label: 'Reception' },
        'en_cours': { cls: 'orange', label: 'En cours' },
        'en_attente': { cls: 'amber', label: 'En attente' },
        'termine': { cls: 'green', label: 'Termine' },
        'annule': { cls: 'red', label: 'Annule' },
        'non_presente': { cls: 'red', label: 'Non presente' },
        'facture': { cls: 'purple', label: 'Facturee' },
        'paye': { cls: 'green', label: 'Payee' }
    };
    var s = map[statut] || { cls: 'blue', label: statut || 'N/A' };
    return '<span class="badge ' + s.cls + '">' + s.label + '</span>';
}

function getMecaInitials(meca) {
    if (!meca) return '??';
    return (meca.prenom || '').charAt(0).toUpperCase() + (meca.nom || '').charAt(0).toUpperCase();
}

function isActive(obj) {
    if (obj.is_active !== undefined) return !!obj.is_active;
    if (obj.actif !== undefined) return !!obj.actif;
    return true;
}

function hexToRgba(color, alpha) {
    if (!color) return 'rgba(59,130,246,' + alpha + ')';
    if (color.startsWith('var(')) return color;
    if (color.startsWith('#')) {
        var r = parseInt(color.slice(1, 3), 16);
        var g = parseInt(color.slice(3, 5), 16);
        var b = parseInt(color.slice(5, 7), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }
    return color;
}

// Modale generique
function closeModal() {
    var m = document.getElementById('app-modal');
    if (m) m.remove();
}

function showModal(title, content, width) {
    closeModal();
    var w = width || '500px';
    var overlay = document.createElement('div');
    overlay.id = 'app-modal';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.6);z-index:1000;display:flex;align-items:center;justify-content:center';
    overlay.onclick = function(e) { if (e.target === overlay) closeModal(); };
    overlay.innerHTML = '<div style="background:#1e1e1e;border:1px solid #444;border-radius:12px;padding:24px;width:' + w + ';max-width:95vw;max-height:90vh;overflow-y:auto">' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px"><h3 style="font-family:Barlow Condensed,sans-serif;font-size:20px;color:#eee">' + title + '</h3><button onclick="closeModal()" style="background:none;border:none;color:#888;cursor:pointer;font-size:20px">&times;</button></div>' +
        '<div id="modal-body">' + content + '</div></div>';
    document.body.appendChild(overlay);
}

function toggleSidebar() {
    var app = document.getElementById('app-container');
    if (!app) return;
    var willOpen = !app.classList.contains('sidebar-open');
    app.classList.toggle('sidebar-open');
    var toggle = document.getElementById('sidebar-toggle');
    if (toggle) toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
}

function closeSidebar() {
    var app = document.getElementById('app-container');
    if (!app || !app.classList.contains('sidebar-open')) return;
    app.classList.remove('sidebar-open');
    var toggle = document.getElementById('sidebar-toggle');
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
}

function setupUiAccessibility() {
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSidebar();
    });
    var avatar = document.getElementById('user-avatar');
    if (avatar) {
        avatar.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                showSection('espace-meca');
            }
        });
    }
    document.addEventListener('keydown', function(e) {
        var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
        var inInput = tag === 'input' || tag === 'textarea' || tag === 'select';
        if (!inInput && (e.key === 'n' || e.key === 'N')) {
            e.preventDefault();
            showSection('rdv');
        }
        if (!inInput && (e.key === 'r' || e.key === 'R')) {
            e.preventDefault();
            showSection('clients');
            var search = document.getElementById('clients-search');
            if (search) search.focus();
        }
    });
}

function openConfirmDialog(message, onConfirm) {
    var html = '<div style="color:#e5e7eb;margin-bottom:16px">' + escapeHtml(message) + '</div>' +
        '<div style="display:flex;gap:8px">' +
        '<button class="btn btn-ghost" style="flex:1" onclick="closeModal()">Annuler</button>' +
        '<button class="btn btn-primary" style="flex:1" onclick="window.__modalConfirmAction()">Confirmer</button>' +
        '</div>';
    window.__modalConfirmAction = function() {
        closeModal();
        if (typeof onConfirm === 'function') onConfirm();
        window.__modalConfirmAction = null;
    };
    showModal('Confirmation', html, '380px');
}

function setLoadingState(el, isLoading, loadingText) {
    if (!el) return;
    if (isLoading) {
        el.innerHTML = '<div style="text-align:center;color:#999;padding:14px"><span class="loading-spinner"></span>' + escapeHtml(loadingText || 'Chargement...') + '</div>';
    }
}

function updateLiveRegion(message) {
    var region = document.getElementById('app-live-region');
    if (!region) return;
    region.textContent = '';
    setTimeout(function() { region.textContent = String(message || ''); }, 10);
}

function showAlert(message, type) {
    showToast(message, type || 'info');
    updateLiveRegion(message);
}

// ===== RDV STATUS ACTIONS =====
function confirmerRdv(rdvId) { return callModuleMethod('RdvActionsModule', 'confirmerRdv', arguments); }
function demarrerTravail(rdvId) { return callModuleMethod('RdvActionsModule', 'demarrerTravail', arguments); }
function terminerTravail(rdvId) { return callModuleMethod('RdvActionsModule', 'terminerTravail', arguments); }
function annulerRdv(rdvId) { return callModuleMethod('RdvActionsModule', 'annulerRdv', arguments); }
function toggleCancelOtherReason() { return callModuleMethod('RdvActionsModule', 'toggleCancelOtherReason', arguments); }
function confirmCancelRdv(rdvId) { return callModuleMethod('RdvActionsModule', 'confirmCancelRdv', arguments); }

function telechargerOR(rdvId) {
    return callModuleMethod('OrModule', 'telechargerOR', arguments);
}

function telechargerFacture(rdvId) { return callModuleMethod('AppCoreModule', 'telechargerFacture', arguments); }

function refreshCurrentSection() { return callModuleMethod('AppCoreModule', 'refreshCurrentSection', arguments); }

// ===== FACTURATION =====
function ouvrirFacturation(rdvId) {
    return callModuleMethod('BillingModule', 'ouvrirFacturation', arguments);
}

function renderFactureTotaux(data, remisePct) {
    return callModuleMethod('BillingModule', 'renderFactureTotaux', arguments, '');
}

function recalcFacturePreview() {
    return callModuleMethod('BillingModule', 'recalcFacturePreview', arguments);
}

function confirmerFacturation(rdvId) {
    return callModuleMethod('BillingModule', 'confirmerFacturation', arguments);
}

// ===== ENCAISSEMENT =====
function ouvrirEncaissement(rdvId) {
    return callModuleMethod('BillingModule', 'ouvrirEncaissement', arguments);
}

function toggleEncReference() {
    return callModuleMethod('BillingModule', 'toggleEncReference', arguments);
}

function confirmerEncaissement(factureId) {
    return callModuleMethod('BillingModule', 'confirmerEncaissement', arguments);
}

// Boutons d'action selon le statut
function actionButtons(rdv, compact, options) {
    return callModuleMethod('RdvActionsModule', 'actionButtons', arguments, '');
}

// Assignation mecanicien + pont
function ouvrirAssignation(rdvId) {
    return callModuleMethod('RdvActionsModule', 'ouvrirAssignation', arguments);
}

function renderAssignationModal(rdvId, rdv) {
    return callModuleMethod('RdvActionsModule', 'renderAssignationModal', arguments);
}

function sauverAssignation(rdvId) {
    return callModuleMethod('RdvActionsModule', 'sauverAssignation', arguments);
}

// ===== DASHBOARD =====
function loadDashboard() { return callModuleMethod('DashboardModule', 'loadDashboard', arguments); }
function renderDashboardStats(rdvs, ponts, facturationStats) { return callModuleMethod('DashboardModule', 'renderDashboardStats', arguments); }
function renderDashboardPonts(ponts, rdvs) { return callModuleMethod('DashboardModule', 'renderDashboardPonts', arguments); }
function renderDashboardRdv(rdvs) { return callModuleMethod('DashboardModule', 'renderDashboardRdv', arguments); }

// ===== PRISE DE RDV =====
function loadRdvForm() { return callModuleMethod('RdvModule', 'loadRdvForm', arguments); }
function searchMotoRdv(val) { return callModuleMethod('RdvModule', 'searchMotoRdv', arguments); }
function updateDurationRdv() { return callModuleMethod('RdvModule', 'updateDurationRdv', arguments); }
function loadCreneaux() { return callModuleMethod('RdvModule', 'loadCreneaux', arguments); }
function renderCreneaux(creneaux) { return callModuleMethod('RdvModule', 'renderCreneaux', arguments); }
function selectSlotRdv(el, time) { return callModuleMethod('RdvModule', 'selectSlotRdv', arguments); }
function confirmRdv() { return callModuleMethod('RdvModule', 'confirmRdv', arguments); }
function searchClientRdvEmbed(val) { return callModuleMethod('RdvModule', 'searchClientRdvEmbed', arguments); }
function selectClientRdvEmbed(clientId) { return callModuleMethod('RdvModule', 'selectClientRdvEmbed', arguments); }
function pushRdvPrefillToIframe() { return callModuleMethod('RdvModule', 'pushRdvPrefillToIframe', arguments); }
function ouvrirRdvPublicModal() { return callModuleMethod('RdvModule', 'ouvrirRdvPublicModal', arguments); }
function getCurrentAtelierSlug() { return callModuleMethod('RdvModule', 'getCurrentAtelierSlug', arguments, 'default'); }
function populateRdvAtelierSelect() { return callModuleMethod('RdvModule', 'populateRdvAtelierSelect', arguments); }
function onRdvAtelierChange(slug) { return callModuleMethod('RdvModule', 'onRdvAtelierChange', arguments); }
function getRdvState() { return callModuleMethod('RdvModule', 'getRdvState', arguments, APP.rdvWizard); }
function goStep(n) { return callModuleMethod('RdvModule', 'goStep', arguments); }
function rechercherVehicule() { return callModuleMethod('RdvModule', 'rechercherVehicule', arguments); }
function validerVehiculeManuel() { return callModuleMethod('RdvModule', 'validerVehiculeManuel', arguments); }
function onTypeMotoFoundSelect(val) { return callModuleMethod('RdvModule', 'onTypeMotoFoundSelect', arguments); }
function continuerVehiculeFound() { return callModuleMethod('RdvModule', 'continuerVehiculeFound', arguments); }
function getPrestaTarif(it) { return callModuleMethod('RdvModule', 'getPrestaTarif', arguments, { prix_ttc: 0, temps_minutes: 30 }); }
function chargerPrestations() { return callModuleMethod('RdvModule', 'chargerPrestations', arguments); }
function renderPrestations() { return callModuleMethod('RdvModule', 'renderPrestations', arguments); }
function togglePresta(id) { return callModuleMethod('RdvModule', 'togglePresta', arguments); }
function updateRecap() { return callModuleMethod('RdvModule', 'updateRecap', arguments); }
function chargerDelaiIntervention() { return callModuleMethod('RdvModule', 'chargerDelaiIntervention', arguments, Promise.resolve()); }
function _rdvDateToStr(d) { return callModuleMethod('RdvModule', '_rdvDateToStr', arguments, ''); }
function _rdvWeekDays(offset, delayDays) { return callModuleMethod('RdvModule', '_rdvWeekDays', arguments, []); }
function changeWeek(dir) { return callModuleMethod('RdvModule', 'changeWeek', arguments); }
function chargerSemaine() { return callModuleMethod('RdvModule', 'chargerSemaine', arguments); }
function chargerJour(dateStr) { return callModuleMethod('RdvModule', 'chargerJour', arguments); }
function renderJour(dateStr, data) { return callModuleMethod('RdvModule', 'renderJour', arguments); }
function _rdvFormatDate(dateStr) { return callModuleMethod('RdvModule', '_rdvFormatDate', arguments, dateStr || '-'); }
function selectWeekSlot(el, dateStr, heure) { return callModuleMethod('RdvModule', 'selectWeekSlot', arguments); }
function afficherRecap() { return callModuleMethod('RdvModule', 'afficherRecap', arguments); }
function confirmerRDV() { return callModuleMethod('RdvModule', 'confirmerRDV', arguments); }

// ===== PLANNING =====
function loadPlanning() { return callModuleMethod('PlanningModule', 'loadPlanning', arguments); }
function isPlanningSlotOpen(dateStr, hour) { return callModuleMethod('PlanningModule', 'isPlanningSlotOpen', arguments, true); }
function isPlanningSlotValidForDuration(dateStr, hour, durationMinutes) { return callModuleMethod('PlanningModule', 'isPlanningSlotValidForDuration', arguments, true); }
function getPlanningHoraireForDate(dateStr) { return callModuleMethod('PlanningModule', 'getPlanningHoraireForDate', arguments, null); }
function splitRdvSegments(dateStr, startMin, durationMin) { return callModuleMethod('PlanningModule', 'splitRdvSegments', arguments, [{ start: startMin, end: startMin + durationMin, continuation: false }]); }
function buildPlanningBusyCells(rdvs) { return callModuleMethod('PlanningModule', 'buildPlanningBusyCells', arguments, {}); }
function isPlanningCellBusy(dateStr, hour) { return callModuleMethod('PlanningModule', 'isPlanningCellBusy', arguments, false); }
function getPlanningAtelierSlug() { return callModuleMethod('PlanningModule', 'getPlanningAtelierSlug', arguments, 'default'); }
function populatePlanningAtelierSelect() { return callModuleMethod('PlanningModule', 'populatePlanningAtelierSelect', arguments); }
function onPlanningAtelierChange(slug) { return callModuleMethod('PlanningModule', 'onPlanningAtelierChange', arguments); }
function renderMecaFilters() { return callModuleMethod('PlanningModule', 'renderMecaFilters', arguments); }
function toggleMecaFilter(mecaId) { return callModuleMethod('PlanningModule', 'toggleMecaFilter', arguments); }
function renderPlanningGrid(rdvs, monday) { return callModuleMethod('PlanningModule', 'renderPlanningGrid', arguments); }

function timeToMinutes(hhmm) {
    return window.PlanningUtils.timeToMinutes(hhmm);
}

// Parse ISO datetime as UTC (server stores UTC without 'Z' suffix)
function parseUTCDate(isoStr) {
    return window.PlanningUtils.parseUTCDate(isoStr);
}

function minutesToTimeLabel(totalMin) {
    return window.PlanningUtils.minutesToTimeLabel(totalMin);
}

function getPlanningBounds() {
    return window.PlanningUtils.getPlanningBounds();
}

function buildPlanningSlots(startTime, endTime, stepMin) {
    return window.PlanningUtils.buildPlanningSlots(startTime, endTime, stepMin);
}

function markConflictCells(cellMap, dayKey, startMin, endMin) {
    return window.PlanningUtils.markConflictCells(cellMap, dayKey, startMin, endMin);
}

function buildVisualOverlapGroups(dayList, byId) {
    return window.PlanningUtils.buildVisualOverlapGroups(dayList, byId);
}

function renderPlanningNowLine(grid, monday) {
    return window.PlanningUtils.renderPlanningNowLine(grid, monday);
}

function getRdvDurationMinutes(rdv) {
    return window.PlanningUtils.getRdvDurationMinutes(rdv);
}

function parseDurationToMinutes(value) {
    return window.PlanningUtils.parseDurationToMinutes(value);
}

function getWeekNumber(d) { return callModuleMethod('PlanningModule', 'getWeekNumber', arguments, 1); }

function planningPrev() { return callModuleMethod('PlanningModule', 'planningPrev', arguments); }
function planningNext() { return callModuleMethod('PlanningModule', 'planningNext', arguments); }

// ===== DRAG & DROP PLANNING =====
function onRdvDragStart(event, rdvId) { return callModuleMethod('PlanningModule', 'onRdvDragStart', arguments); }
function onRdvDragEnd(event) { return callModuleMethod('PlanningModule', 'onRdvDragEnd', arguments); }
function onCellDragOver(event) { return callModuleMethod('PlanningModule', 'onCellDragOver', arguments); }
function onCellDragLeave(event) { return callModuleMethod('PlanningModule', 'onCellDragLeave', arguments); }
function onCellDrop(event, dateStr, hour) { return callModuleMethod('PlanningModule', 'onCellDrop', arguments); }

// ===== CLIC SUR RDV PLANNING =====
function onPlanningRdvClick(rdvId) { return callModuleMethod('PlanningModule', 'onPlanningRdvClick', arguments); }

// ===== CLIC CELLULE VIDE - QUICK CREATE =====
function onPlanningCellClick(event, dateStr, hour) { return callModuleMethod('PlanningModule', 'onPlanningCellClick', arguments); }
function ouvrirQuickCreateRdv(dateStr, hour) { return callModuleMethod('PlanningModule', 'ouvrirQuickCreateRdv', arguments); }
function searchClientQuickCreate(val) { return callModuleMethod('PlanningModule', 'searchClientQuickCreate', arguments); }
function selectClientQuickCreate(nom, prenom, tel) { return callModuleMethod('PlanningModule', 'selectClientQuickCreate', arguments); }
function searchVehiculeQuickCreate(val) { return callModuleMethod('PlanningModule', 'searchVehiculeQuickCreate', arguments); }
function submitQuickCreateRdv() { return callModuleMethod('PlanningModule', 'submitQuickCreateRdv', arguments); }


// ===== PONTS & MECANICIENS =====
function loadPontsMecas() { return callModuleMethod('WorkshopModule', 'loadPontsMecas', arguments); }
function renderPontsTab() { return callModuleMethod('WorkshopModule', 'renderPontsTab', arguments); }
function renderMecasTab() { return callModuleMethod('WorkshopModule', 'renderMecasTab', arguments); }
function renderPontsManagerKpis(ponts, rdvs, absences) { return callModuleMethod('WorkshopModule', 'renderPontsManagerKpis', arguments); }
function countManagerConflicts(rdvs) { return callModuleMethod('WorkshopModule', 'countManagerConflicts', arguments, 0); }
function renderTempsTab() { return callModuleMethod('WorkshopModule', 'renderTempsTab', arguments); }

// ===== ORDRES DE REPARATION =====
function loadOrdresReparation() {
    return callModuleMethod('OrModule', 'loadOrdresReparation', arguments);
}

function renderOrdresReparation(rdvs) {
    return callModuleMethod('OrModule', 'renderOrdresReparation', arguments);
}

function getEtapeIndex(statut) {
    return callModuleMethod('OrModule', 'getEtapeIndex', arguments);
}

function showOrDetail(rdvId) {
    return callModuleMethod('OrModule', 'showOrDetail', arguments);
}

function planifierRdvSuite(rdvId) {
    return callModuleMethod('OrModule', 'planifierRdvSuite', arguments);
}

// ===== RECEPTION VEHICULE =====
var ETAT_VEHICULE_POINTS = window.ETAT_VEHICULE_POINTS || [];

function ouvrirReception(rdvId) {
    return callModuleMethod('OrModule', 'ouvrirReception', arguments);
}

function initReceptionSignaturePad() {
    return callModuleMethod('OrModule', 'initReceptionSignaturePad', arguments);
}

function getCanvasCoords(canvas, event) {
    return callModuleMethod('OrModule', 'getCanvasCoords', arguments);
}

function clearReceptionSignature() {
    return callModuleMethod('OrModule', 'clearReceptionSignature', arguments);
}

function getReceptionSignatureBase64() {
    return callModuleMethod('OrModule', 'getReceptionSignatureBase64', arguments);
}

function validerReception(rdvId) {
    return callModuleMethod('OrModule', 'validerReception', arguments);
}

// ===== TRAVAUX SUPPLEMENTAIRES =====
function ouvrirDemandeTravauxSupp(rdvId) {
    return callModuleMethod('OrModule', 'ouvrirDemandeTravauxSupp', arguments);
}

function toggleTsPrestation(id, code, nom) {
    return callModuleMethod('OrModule', 'toggleTsPrestation', arguments);
}

function updateTsSelectedCount() {
    return callModuleMethod('OrModule', 'updateTsSelectedCount', arguments);
}

function setTsUrgence(val) {
    return callModuleMethod('OrModule', 'setTsUrgence', arguments);
}

function envoyerDemandeTravauxSupp(rdvId) {
    return callModuleMethod('OrModule', 'envoyerDemandeTravauxSupp', arguments);
}

// ===== POLLING TRAVAUX SUPP =====
function pollTravauxSupp() {
    return callModuleMethod('OrModule', 'pollTravauxSupp', arguments);
}

function playAlertSound() {
    return callModuleMethod('OrModule', 'playAlertSound', arguments);
}

function showTravauxSuppAlert(demande) {
    return callModuleMethod('OrModule', 'showTravauxSuppAlert', arguments);
}

function traiterAlertTravaux(demandeId, statut, btn) {
    return callModuleMethod('OrModule', 'traiterAlertTravaux', arguments);
}

// ===== SIGNATURE TRAVAUX SUPP =====
function ouvrirSignatureTravauxSupp(demandeId, notes, prixDevis, tempsDevis) {
    return callModuleMethod('OrModule', 'ouvrirSignatureTravauxSupp', arguments);
}

function initTsSignaturePad() {
    return callModuleMethod('OrModule', 'initTsSignaturePad', arguments);
}

function clearTsSignature() {
    return callModuleMethod('OrModule', 'clearTsSignature', arguments);
}

function confirmerTravauxSuppAvecSignature(demandeId, prixDevis, tempsDevis, notes) {
    return callModuleMethod('OrModule', 'confirmerTravauxSuppAvecSignature', arguments);
}

function showNotificationToast(message) {
    return callModuleMethod('OrModule', 'showNotificationToast', arguments);
}

// ===== APPROBATION TRAVAUX SUPP (dans section OR) =====
function renderTravauxSuppPanel() {
    return callModuleMethod('OrModule', 'renderTravauxSuppPanel', arguments);
}

function approuverTravauxSupp(demandeId) {
    return callModuleMethod('OrModule', 'approuverTravauxSupp', arguments);
}

function confirmerApprouverTravauxSupp(demandeId) {
    return callModuleMethod('OrModule', 'confirmerApprouverTravauxSupp', arguments);
}

function refuserTravauxSupp(demandeId) {
    return callModuleMethod('OrModule', 'refuserTravauxSupp', arguments);
}

function confirmerRefuserTravauxSupp(demandeId) {
    return callModuleMethod('OrModule', 'confirmerRefuserTravauxSupp', arguments);
}

// ===== SUIVI LIVE =====
function loadSuiviLive() {
    return callModuleMethod('SuiviModule', 'loadSuiviLive', arguments);
}

function renderSuiviLive(rdvs) {
    return callModuleMethod('SuiviModule', 'renderSuiviLive', arguments);
}

function getRdvProgressInfo(rdv, now) {
    return callModuleMethod('SuiviModule', 'getRdvProgressInfo', arguments);
}

function getRdvDelayInfo(rdv, now) {
    return callModuleMethod('SuiviModule', 'getRdvDelayInfo', arguments);
}

// ===== CHECKUP / RAPPORT TECHNICIEN =====
var CHECKUP_POINTS = window.CHECKUP_POINTS || [];

function ouvrirCheckup(rdvId) {
    return callModuleMethod('MecanicienModule', 'ouvrirCheckup', arguments);
}

function renderCheckupModal(rdvId, rapport) {
    return callModuleMethod('MecanicienModule', 'renderCheckupModal', arguments);
}

function setCheckpoint(key, value, btn) {
    return callModuleMethod('MecanicienModule', 'setCheckpoint', arguments);
}

function sauverCheckup(rdvId, statut) {
    return callModuleMethod('MecanicienModule', 'sauverCheckup', arguments);
}

// ===== ESPACE MECANICIEN =====

function loadEspaceMeca() {
    return callModuleMethod('MecanicienModule', 'loadEspaceMeca', arguments);
}

function renderEspaceMeca(meca, allRdvs) {
    return callModuleMethod('MecanicienModule', 'renderEspaceMeca', arguments);
}

function renderMecaActivePanel(rdv) {
    return callModuleMethod('MecanicienModule', 'renderMecaActivePanel', arguments);
}

function renderMecaCard(rdv, type) {
    return callModuleMethod('MecanicienModule', 'renderMecaCard', arguments);
}

function toggleMecaCheckup() {
    return callModuleMethod('MecanicienModule', 'toggleMecaCheckup', arguments);
}

function setMecaCheck(key, value, btn) {
    return callModuleMethod('MecanicienModule', 'setMecaCheck', arguments);
}

function startMecaLiveTimer(rdv) {
    return callModuleMethod('MecanicienModule', 'startMecaLiveTimer', arguments);
}

function cleanupMecaTimer() {
    return callModuleMethod('MecanicienModule', 'cleanupMecaTimer', arguments);
}

// ===== GESTION ABSENCES =====
function loadAbsences() {
    return callModuleMethod('AbsencesModule', 'loadAbsences', arguments);
}

function renderAbsencesTable(absences) {
    return callModuleMethod('AbsencesModule', 'renderAbsencesTable', arguments);
}

function ouvrirModalAbsence() {
    return callModuleMethod('AbsencesModule', 'ouvrirModalAbsence', arguments);
}

function sauverAbsence() {
    return callModuleMethod('AbsencesModule', 'sauverAbsence', arguments);
}

function ouvrirModalEditAbsence(absenceId) {
    return callModuleMethod('AbsencesModule', 'ouvrirModalEditAbsence', arguments);
}

function sauverEditAbsence(absenceId) {
    return callModuleMethod('AbsencesModule', 'sauverEditAbsence', arguments);
}

function supprimerAbsence(id) {
    return callModuleMethod('AbsencesModule', 'supprimerAbsence', arguments);
}

// ===== GESTION CLIENTS =====
function rechercherClients(val) {
    return callModuleMethod('ClientsModule', 'rechercherClients', arguments);
}

function loadClients(search, page) {
    return callModuleMethod('ClientsModule', 'loadClients', arguments);
}

function loadClientsStats() {
    return callModuleMethod('ClientsModule', 'loadClientsStats', arguments);
}

function renderClientsPagination(currentPage, totalPages) {
    return callModuleMethod('ClientsModule', 'renderClientsPagination', arguments);
}

function ouvrirNouveauClient() {
    return callModuleMethod('ClientsModule', 'ouvrirNouveauClient', arguments);
}

function creerNouveauClient() {
    return callModuleMethod('ClientsModule', 'creerNouveauClient', arguments);
}

function supprimerVehicule(vehiculeId, clientId) {
    return callModuleMethod('ClientsModule', 'supprimerVehicule', arguments);
}

function showClientDetail(clientId) {
    return callModuleMethod('ClientsModule', 'showClientDetail', arguments);
}

// ===== DETAIL RDV DEPUIS HISTORIQUE CLIENT =====
function ouvrirDetailHistoriqueRdv(rdvId, clientId) {
    return callModuleMethod('ClientsModule', 'ouvrirDetailHistoriqueRdv', arguments);
}

// ===== AJOUTER VEHICULE A UN CLIENT =====
function ouvrirAjouterVehicule(clientId) {
    return callModuleMethod('ClientsModule', 'ouvrirAjouterVehicule', arguments);
}

function sauverNouveauVehicule(clientId) {
    return callModuleMethod('ClientsModule', 'sauverNouveauVehicule', arguments);
}

function ouvrirModalEditClient(clientId) {
    return callModuleMethod('ClientsModule', 'ouvrirModalEditClient', arguments);
}

function sauverClient(clientId) {
    return callModuleMethod('ClientsModule', 'sauverClient', arguments);
}

// ===== EDIT VEHICULE MODAL =====
function ouvrirModalEditVehicule(vehiculeId) {
    return callModuleMethod('ClientsModule', 'ouvrirModalEditVehicule', arguments);
}

function sauverVehicule(vehiculeId) {
    return callModuleMethod('ClientsModule', 'sauverVehicule', arguments);
}

// ===== DETAIL RDV MODAL (receptionnaire) =====
function ouvrirDetailRdv(rdvId) { return callModuleMethod('WorkshopModule', 'ouvrirDetailRdv', arguments); }
function sauverCommentaireRdv(rdvId) { return callModuleMethod('WorkshopModule', 'sauverCommentaireRdv', arguments); }

// ===== ATTRIBUTION PONT -> MECANICIEN =====
function ouvrirAttribuerPont(pontId) { return callModuleMethod('WorkshopModule', 'ouvrirAttribuerPont', arguments); }
function sauverAttribuerPont(pontId) { return callModuleMethod('WorkshopModule', 'sauverAttribuerPont', arguments); }

function loadAdminAteliers() {
    return callModuleMethod('AdminModule', 'loadAdminAteliers', arguments);
}

function selectAdminAtelier(atelierId) {
    return callModuleMethod('AdminModule', 'selectAdminAtelier', arguments);
}

function loadAdminUsers() {
    return callModuleMethod('AdminModule', 'loadAdminUsers', arguments);
}

function renderAdminUsers(users) {
    return callModuleMethod('AdminModule', 'renderAdminUsers', arguments);
}

function switchAtelier(atelierId) {
    return callModuleMethod('AdminModule', 'switchAtelier', arguments);
}

function ouvrirNouveauAtelier() {
    return callModuleMethod('AdminModule', 'ouvrirNouveauAtelier', arguments);
}

function creerAtelier() {
    return callModuleMethod('AdminModule', 'creerAtelier', arguments);
}

function ouvrirEditAtelier(id, nom) {
    return callModuleMethod('AdminModule', 'ouvrirEditAtelier', arguments);
}

function sauverAtelier(id) {
    return callModuleMethod('AdminModule', 'sauverAtelier', arguments);
}

function ouvrirNouvelUtilisateurAtelier() {
    return callModuleMethod('AdminModule', 'ouvrirNouvelUtilisateurAtelier', arguments);
}

function renderCreateUserModal(roleOptionsHtml) {
    return callModuleMethod('AdminModule', 'renderCreateUserModal', arguments);
}

function toggleCreateUserMecaFields() {
    return callModuleMethod('AdminModule', 'toggleCreateUserMecaFields', arguments);
}

function creerUtilisateurAtelier() {
    return callModuleMethod('AdminModule', 'creerUtilisateurAtelier', arguments);
}

function ouvrirEditionUtilisateurAtelier(userId) {
    return callModuleMethod('AdminModule', 'ouvrirEditionUtilisateurAtelier', arguments);
}

function toggleEditUserMecaFields() {
    return callModuleMethod('AdminModule', 'toggleEditUserMecaFields', arguments);
}

function sauverEditionUtilisateurAtelier(userId) {
    return callModuleMethod('AdminModule', 'sauverEditionUtilisateurAtelier', arguments);
}

function supprimerUtilisateurAtelier(userId) {
    return callModuleMethod('AdminModule', 'supprimerUtilisateurAtelier', arguments);
}

function switchAdminTab(tabId) {
    return callModuleMethod('AdminModule', 'switchAdminTab', arguments);
}

function loadAdminRoles() {
    return callModuleMethod('AdminModule', 'loadAdminRoles', arguments);
}

function openRoleEditor(roleName) {
    return callModuleMethod('AdminModule', 'openRoleEditor', arguments);
}

function saveRolePermission() {
    return callModuleMethod('AdminModule', 'saveRolePermission', arguments);
}

function normalizeRoleSlug(value) {
    return callModuleMethod('AdminModule', 'normalizeRoleSlug', arguments);
}

function deleteRolePermission(role) {
    return callModuleMethod('AdminModule', 'deleteRolePermission', arguments);
}

function loadAdminConfig() {
    return callModuleMethod('AdminModule', 'loadAdminConfig', arguments);
}

function saveAdminConfig(e) {
    return callModuleMethod('AdminModule', 'saveAdminConfig', arguments);
}

function loadAdminCategoriesMoto() {
    return callModuleMethod('AdminModule', 'loadAdminCategoriesMoto', arguments);
}

function toggleAdminCategorieMoto(categorieId) {
    return callModuleMethod('AdminModule', 'toggleAdminCategorieMoto', arguments);
}

function adminFmtTime(value) {
    return callModuleMethod('AdminModule', 'adminFmtTime', arguments);
}

function loadAdminHoraires() {
    return callModuleMethod('AdminModule', 'loadAdminHoraires', arguments);
}

function saveAdminHoraire(jour) {
    return callModuleMethod('AdminModule', 'saveAdminHoraire', arguments);
}

function toggleAdminMidi(jour) {
    return callModuleMethod('AdminModule', 'toggleAdminMidi', arguments);
}

function adminFormatMinutes(mins) {
    return callModuleMethod('AdminModule', 'adminFormatMinutes', arguments);
}

function loadAdminPrestations() {
    return callModuleMethod('AdminModule', 'loadAdminPrestations', arguments);
}

function openAdminPrestationModal(id) {
    return callModuleMethod('AdminModule', 'openAdminPrestationModal', arguments);
}

function saveAdminPrestation(id, tvaMo) {
    return callModuleMethod('AdminModule', 'saveAdminPrestation', arguments);
}

function toggleAdminPrestation(id, state) {
    return callModuleMethod('AdminModule', 'toggleAdminPrestation', arguments);
}

function openAdminGrilleModal(prestationId) {
    return callModuleMethod('AdminModule', 'openAdminGrilleModal', arguments);
}

function saveAdminGrille(prestationId, tvaMo) {
    return callModuleMethod('AdminModule', 'saveAdminGrille', arguments);
}

function loadAdminWorkshop() {
    return callModuleMethod('AdminModule', 'loadAdminWorkshop', arguments);
}

function renderAdminWorkshopPonts(ponts, mecas) {
    return callModuleMethod('AdminModule', 'renderAdminWorkshopPonts', arguments);
}

function renderAdminWorkshopMecas(mecas, users) {
    return callModuleMethod('AdminModule', 'renderAdminWorkshopMecas', arguments);
}

function openAdminPontModal(pontId) {
    return callModuleMethod('AdminModule', 'openAdminPontModal', arguments);
}

function saveAdminPont(pontId) {
    return callModuleMethod('AdminModule', 'saveAdminPont', arguments);
}

function deleteAdminPont(pontId) {
    return callModuleMethod('AdminModule', 'deleteAdminPont', arguments);
}

function deleteAdminMecanicien(mecanicienId) {
    return callModuleMethod('AdminModule', 'deleteAdminMecanicien', arguments);
}

function loadAdminEquipements() {
    return callModuleMethod('AdminModule', 'loadAdminEquipements', arguments);
}

function openAdminEquipementModal() {
    return callModuleMethod('AdminModule', 'openAdminEquipementModal', arguments);
}

function saveAdminEquipement() {
    return callModuleMethod('AdminModule', 'saveAdminEquipement', arguments);
}

function deleteAdminEquipement(id) {
    return callModuleMethod('AdminModule', 'deleteAdminEquipement', arguments);
}

console.log('app.js loaded');
