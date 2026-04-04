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

function showLogin() {
    document.getElementById('login-screen').style.display = 'flex';
    document.getElementById('app-container').style.display = 'none';
}

function hideLogin() {
    document.getElementById('login-screen').style.display = 'none';
    document.getElementById('app-container').style.display = 'flex';
}

function doLogin() {
    var username = document.getElementById('login-user').value;
    var password = document.getElementById('login-pass').value;
    var errEl = document.getElementById('login-error');
    errEl.textContent = '';

    var formData = new URLSearchParams();
    formData.append('username', username);
    formData.append('password', password);

    fetch(window.API_URL + '/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    }).then(function(resp) {
        if (!resp.ok) { errEl.textContent = 'Identifiants incorrects'; throw new Error('bad'); }
        return resp.json();
    }).then(function(data) {
        setAuthRole(data.role);
        initApp();
    }).catch(function(e) {
        if (errEl.textContent === '') errEl.textContent = 'Erreur de connexion';
    });
}

function logout() {
    apiPost('/api/auth/logout', {}).finally(function() {
        clearAuthState();
        showLogin();
    });
}

function initApp() {
    hideLogin();
    apiGet('/api/auth/me').then(function(r) { return r.json(); }).then(function(me) {
        APP.currentUser = me;
        APP.roleSections = me.sections || null;
        APP.rolePermissions = me.permissions || null;
        var avatar = document.getElementById('user-avatar');
        if (avatar) avatar.textContent = (me.username || 'U').substring(0, 2).toUpperCase();
        applyRoleVisibility(me.role);
    }).catch(function() {});

    loadBaseData().then(function() {
        var role = getAuthRole();
        var allowed = getAllowedSections(role || '');
        var preferred = role === 'mecanicien' ? 'espace-meca' : 'dashboard';
        showSection(allowed.indexOf(preferred) !== -1 ? preferred : (allowed[0] || 'dashboard'));
    });

    if (APP.refreshInterval) clearInterval(APP.refreshInterval);
    APP.refreshInterval = setInterval(function() {
        if (APP.currentSection === 'dashboard') loadDashboard();
        if (APP.currentSection === 'suivi') loadSuiviLive();
        var role = APP.currentUser ? APP.currentUser.role : '';
        if (role === 'admin' || role === 'super_admin' || role === 'receptionnaire' || role === 'service_client') pollTravauxSupp();
    }, 30000);
}

function loadBaseData() {
    return Promise.all([
        apiGet('/api/ponts').then(function(r) { return r.json(); }).catch(function() { return []; }),
        apiGet('/api/mecaniciens').then(function(r) { return r.json(); }).catch(function() { return []; }),
        apiGet('/api/interventions').then(function(r) { return r.json(); }).catch(function() { return []; }),
        apiGet('/api/motos/categories').then(function(r) { return r.json(); }).catch(function() { return []; }),
        apiGet('/api/config/prestations').then(function(r) { return r.json(); }).catch(function() { return []; })
    ]).then(function(results) {
        APP.ponts = results[0];
        APP.mecaniciens = results[1];
        APP.interventionTypes = results[2];
        APP.categories = results[3];
        APP.prestationsConfig = results[4];
    });
}

// ===== ROLE VISIBILITY =====
function hasPermission(permission) {
    if (!APP.currentUser) return false;
    if (APP.currentUser.role === 'super_admin') return true;
    // Compat legacy: admin/manager gardent l'acces config meme si RBAC incomplet.
    if (permission === 'config.manage' && (APP.currentUser.role === 'admin' || APP.currentUser.role === 'manager')) return true;
    if (APP.rolePermissions && APP.rolePermissions.indexOf(permission) !== -1) return true;
    return false;
}

function canUseBilling() {
    return hasPermission('billing.view') || hasPermission('billing.edit') || hasPermission('billing.pay') || hasPermission('billing.pdf');
}

function applyRoleVisibility(role) {
    var allowed = getAllowedSections(role);
    var sectionToNav = {
        dashboard: 'nav-dashboard',
        rdv: 'nav-rdv',
        planning: 'nav-planning',
        ponts: 'nav-ponts',
        or: 'nav-or',
        suivi: 'nav-suivi',
        clients: 'nav-clients',
        admin: 'nav-admin'
    };
    Object.keys(sectionToNav).forEach(function(section) {
        var nav = document.getElementById(sectionToNav[section]);
        if (nav) nav.style.display = allowed.indexOf(section) !== -1 ? '' : 'none';
    });
    var btnNouveauRdv = document.getElementById('btn-nouveau-rdv');
    if (btnNouveauRdv) btnNouveauRdv.style.display = allowed.indexOf('rdv') !== -1 ? '' : 'none';
    var navFactures = document.getElementById('nav-factures');
    if (navFactures) navFactures.style.display = canUseBilling() ? '' : 'none';
}

var ROLE_SECTIONS = {
    mecanicien: ['dashboard', 'planning', 'or', 'espace-meca'],
    receptionnaire: ['dashboard', 'rdv', 'planning', 'ponts', 'or', 'suivi', 'clients', 'espace-meca'],
    service_client: ['dashboard', 'rdv', 'planning', 'ponts', 'or', 'suivi', 'clients', 'espace-meca'],
    admin: ['dashboard', 'rdv', 'planning', 'ponts', 'or', 'suivi', 'clients', 'espace-meca', 'admin'],
    super_admin: ['dashboard', 'rdv', 'planning', 'ponts', 'or', 'suivi', 'clients', 'espace-meca', 'admin']
};

var RBAC_SECTION_LABELS = {
    'dashboard': 'Dashboard',
    'rdv': 'Prise de RDV',
    'planning': 'Planning',
    'ponts': 'Ponts & mecaniciens',
    'or': 'Ordres de reparation',
    'suivi': 'Suivi live',
    'clients': 'Clients',
    'espace-meca': 'Espace mecanicien',
    'admin': 'Administration'
};

var RBAC_PERMISSION_LABELS = {
    'billing.view': 'Voir factures',
    'billing.edit': 'Modifier facturation',
    'billing.pay': 'Encaissement',
    'billing.pdf': 'Generer PDF facture',
    'travaux_supp.review': 'Valider travaux supplementaires',
    'rdv.select_atelier': 'Choix atelier (multi-site)',
    'rdv.edit': 'Modifier les rendez-vous',
    'users.manage': 'Gerer utilisateurs',
    'ateliers.manage': 'Gerer ateliers',
    'roles.manage': 'Gerer roles & droits',
    'config.manage': 'Gerer configuration',
    'prestations.manage': 'Gerer prestations',
    'equipements.manage': 'Gerer equipements'
};

function formatRbacBadges(items, type) {
    var list = Array.isArray(items) ? items : [];
    if (!list.length) return '<span style="color:#666">Aucun</span>';
    var labels = type === 'section' ? RBAC_SECTION_LABELS : RBAC_PERMISSION_LABELS;
    return list.map(function(code) {
        var label = labels[code] || code;
        return '<span class="badge blue" title="' + escapeAttr(code) + '" style="margin:2px 4px 2px 0">' + escapeHtml(label) + '</span>';
    }).join('');
}

function getAllowedSections(role) {
    if (APP.roleSections && APP.roleSections.length) return APP.roleSections;
    return ROLE_SECTIONS[role] || ROLE_SECTIONS.admin;
}

// ===== NAVIGATION =====
function showSection(id) {
    // Role guard
    var role = APP.currentUser ? APP.currentUser.role : (getAuthRole() || 'admin');
    var allowed = getAllowedSections(role);
    if (allowed.indexOf(id) === -1) {
        var preferred = role === 'mecanicien' ? 'espace-meca' : 'dashboard';
        id = allowed.indexOf(preferred) !== -1 ? preferred : (allowed[0] || 'dashboard');
    }

    APP.currentSection = id;
    cleanupMecaTimer();
    document.querySelectorAll('.section').forEach(function(s) { s.classList.remove('active'); });
    document.querySelectorAll('.nav-btn').forEach(function(b) { b.classList.remove('active'); });

    var el = document.getElementById('s-' + id);
    if (el) el.classList.add('active');

    var titles = {
        'dashboard': 'Dashboard',
        'rdv': 'Prise de RDV',
        'planning': 'Planning',
        'ponts': 'Ponts & Mecaniciens',
        'or': 'Ordres de Reparation',
        'suivi': 'Suivi Live',
        'clients': 'Clients',
        'espace-meca': 'Espace Mecanicien',
        'admin': 'Administration'
    };
    document.getElementById('page-title').textContent = titles[id] || id;

    var idx = { 'dashboard': 0, 'rdv': 1, 'planning': 2, 'ponts': 3, 'or': 4, 'suivi': 5, 'clients': 6, 'admin': 8 };
    var btns = document.querySelectorAll('.nav-btn');
    if (idx[id] !== undefined && btns[idx[id]]) btns[idx[id]].classList.add('active');

    if (id === 'dashboard') loadDashboard();
    if (id === 'rdv') loadRdvForm();
    if (id === 'planning') loadPlanning();
    if (id === 'ponts') loadPontsMecas();
    if (id === 'or') loadOrdresReparation();
    if (id === 'suivi') loadSuiviLive();
    if (id === 'clients') loadClients();
    if (id === 'espace-meca') loadEspaceMeca();
    if (id === 'admin') {
        var navAdmin = document.getElementById('nav-admin');
        if (navAdmin) navAdmin.classList.add('active');
        loadAdminAteliers();
        switchAdminTab('ateliers');
    }
    closeSidebar();
    var mainContent = document.getElementById('main-content');
    if (mainContent) mainContent.focus();
}

function switchTab(el, tabId) {
    el.parentElement.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
    el.classList.add('active');
    var section = el.closest('.section');
    if (section) {
        section.querySelectorAll('.tab-content').forEach(function(tc) { tc.style.display = 'none'; });
    }
    var target = document.getElementById(tabId);
    if (target) target.style.display = 'block';
}

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
function confirmerRdv(rdvId) {
    apiPut('/api/rendez-vous/' + rdvId, { statut: 'confirme' }).then(function() {
        refreshCurrentSection();
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function demarrerTravail(rdvId) {
    APP._mecaSelectedRdvId = rdvId;
    apiPost('/api/rendez-vous/' + rdvId + '/demarrer-travail', {}).then(function() {
        showNotificationToast('Intervention demarree');
        refreshCurrentSection();
        setTimeout(function() {
            var panel = document.getElementById('meca-active-panel');
            if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 400);
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function terminerTravail(rdvId) {
    var points = window._checkupPoints || {};
    var hasData = Object.keys(points).some(function(k) { return points[k] !== 'non_verifie'; });
    var notes = (document.getElementById('meca-notes') || {}).value || '';
    var saveP = hasData ? apiPost('/api/rendez-vous/' + rdvId + '/rapport-technicien', {
        points_controle: points, alertes: notes, recommandations: '', travaux_realises: '', statut: 'termine'
    }).catch(function() {}) : Promise.resolve();
    saveP.then(function() {
        return apiPost('/api/rendez-vous/' + rdvId + '/terminer-travail', {});
    }).then(function() {
        window._checkupPoints = {};
        showNotificationToast('Intervention terminee');
        refreshCurrentSection();
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function annulerRdv(rdvId) {
    var html = '<div style="font-size:12px;color:#aaa;margin-bottom:10px">Motif d\'annulation (obligatoire)</div>' +
        '<div class="form-group">' +
        '<select id="cancel-reason" class="form-select" onchange="toggleCancelOtherReason()">' +
        '<option value="">Selectionner un motif...</option>' +
        '<option value="client_indisponible">Client indisponible</option>' +
        '<option value="atelier_indisponible">Atelier indisponible</option>' +
        '<option value="piece_non_disponible">Piece non disponible</option>' +
        '<option value="non_presente">Client non presente</option>' +
        '<option value="doublon_saisie">Doublon de saisie</option>' +
        '<option value="autre">Autre</option>' +
        '</select></div>' +
        '<div class="form-group" id="cancel-other-wrap" style="display:none">' +
        '<input id="cancel-other-reason" class="form-input" placeholder="Preciser le motif..."></div>' +
        '<div style="display:flex;gap:8px;margin-top:12px">' +
        '<button class="btn btn-ghost" style="flex:1" onclick="closeModal()">Retour</button>' +
        '<button class="btn btn-primary" style="flex:1" onclick="confirmCancelRdv(' + rdvId + ')">Confirmer annulation</button>' +
        '</div>';
    showModal('Annuler le RDV #' + rdvId, html, '460px');
}

function toggleCancelOtherReason() {
    var reason = (document.getElementById('cancel-reason') || {}).value || '';
    var wrap = document.getElementById('cancel-other-wrap');
    if (wrap) wrap.style.display = reason === 'autre' ? '' : 'none';
}

function confirmCancelRdv(rdvId) {
    var reason = (document.getElementById('cancel-reason') || {}).value || '';
    if (!reason) {
        showAlert('Selectionnez un motif d\'annulation', 'error');
        return;
    }
    var other = (document.getElementById('cancel-other-reason') || {}).value || '';
    if (reason === 'autre' && !other.trim()) {
        showAlert('Precisez le motif "Autre"', 'error');
        return;
    }
    var commentaire = '[ANNULATION] motif=' + reason + (reason === 'autre' ? (' | detail=' + other.trim()) : '');
    var finalStatus = reason === 'non_presente' ? 'non_presente' : 'annule';
    apiPut('/api/rendez-vous/' + rdvId, { statut: finalStatus, commentaire: commentaire }).then(function() {
        closeModal();
        refreshCurrentSection();
        showNotificationToast(reason === 'non_presente' ? 'RDV marque non presente' : 'RDV annule');
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function telechargerOR(rdvId) {
    return window.OrModule.telechargerOR(rdvId);
}

function telechargerFacture(rdvId) {
    if (!canUseBilling()) {
        showAlert('Facturation desactivee pour le role service client', 'warning');
        return;
    }
    window.open(window.API_URL + '/api/rendez-vous/' + rdvId + '/facture-pdf', '_blank');
}

function refreshCurrentSection() {
    if (APP.currentSection === 'dashboard') loadDashboard();
    else if (APP.currentSection === 'or') loadOrdresReparation();
    else if (APP.currentSection === 'suivi') loadSuiviLive();
    else if (APP.currentSection === 'espace-meca') loadEspaceMeca();
    else if (APP.currentSection === 'clients') loadClients();
    else if (APP.currentSection === 'ponts') loadPontsMecas();
    else if (APP.currentSection === 'planning') loadPlanning();
}

// ===== FACTURATION =====
function ouvrirFacturation(rdvId) {
    return window.BillingModule.ouvrirFacturation(rdvId);
}

function renderFactureTotaux(data, remisePct) {
    return window.BillingModule.renderFactureTotaux(data, remisePct);
}

function recalcFacturePreview() {
    return window.BillingModule.recalcFacturePreview();
}

function confirmerFacturation(rdvId) {
    return window.BillingModule.confirmerFacturation(rdvId);
}

// ===== ENCAISSEMENT =====
function ouvrirEncaissement(rdvId) {
    return window.BillingModule.ouvrirEncaissement(rdvId);
}

function toggleEncReference() {
    return window.BillingModule.toggleEncReference();
}

function confirmerEncaissement(factureId) {
    return window.BillingModule.confirmerEncaissement(factureId);
}

// Boutons d'action selon le statut
function actionButtons(rdv, compact, options) {
    var s = rdv.statut;
    var sz = compact ? 'font-size:11px;padding:4px 8px;' : 'font-size:12px;padding:5px 10px;';
    var html = '<div style="display:flex;gap:4px;flex-wrap:wrap">';
    var showBillingActions = !(options && options.hideBillingActions === true);

    var canEditRdv = hasPermission('rdv.edit');

    // Editer RDV (client, vehicule, infos)
    if (canEditRdv && s !== 'annule' && s !== 'non_presente' && s !== 'paye') {
        html += '<button class="btn btn-ghost" style="' + sz + 'color:var(--teal)" onclick="ouvrirDetailRdv(' + rdv.id + ')">Editer</button>';
    }

    // Assigner technicien
    if (canEditRdv && s !== 'annule' && s !== 'non_presente' && s !== 'termine' && s !== 'facture' && s !== 'paye') {
        html += '<button class="btn btn-ghost" style="' + sz + 'color:var(--purple)" onclick="ouvrirAssignation(' + rdv.id + ')">' + (rdv.mecanicien_id && rdv.pont_id ? 'Reassigner' : 'Assigner') + '</button>';
    }

    if (canEditRdv && (s === 'reserve' || s === 'en_attente')) {
        html += '<button class="btn btn-primary" style="' + sz + '" onclick="confirmerRdv(' + rdv.id + ')">Confirmer</button>';
        html += '<button class="btn btn-ghost" style="' + sz + '" onclick="annulerRdv(' + rdv.id + ')">Annuler</button>';
    } else if (canEditRdv && s === 'confirme') {
        html += '<button class="btn btn-primary" style="' + sz + 'background:var(--teal)" onclick="ouvrirReception(' + rdv.id + ')">Reception</button>';
        html += '<button class="btn btn-ghost" style="' + sz + '" onclick="annulerRdv(' + rdv.id + ')">Annuler</button>';
    } else if (canEditRdv && s === 'reception') {
        html += '<button class="btn btn-primary" style="' + sz + 'background:var(--green)" onclick="demarrerTravail(' + rdv.id + ')">Demarrer</button>';
    } else if (canEditRdv && s === 'en_cours') {
        html += '<button class="btn btn-primary" style="' + sz + 'background:var(--green)" onclick="terminerTravail(' + rdv.id + ')">Terminer</button>';
        html += '<button class="btn btn-ghost" style="' + sz + '" onclick="ouvrirCheckup(' + rdv.id + ')">Checkup</button>';
    } else if (s === 'termine') {
        if (showBillingActions && canUseBilling()) {
            html += '<button class="btn btn-primary" style="' + sz + 'background:#8B5CF6" onclick="ouvrirFacturation(' + rdv.id + ')">Facturer</button>';
        }
        html += '<button class="btn btn-ghost" style="' + sz + '" onclick="telechargerOR(' + rdv.id + ')">OR PDF</button>';
    } else if (s === 'facture') {
        if (showBillingActions && canUseBilling()) {
            html += '<button class="btn btn-primary" style="' + sz + 'background:var(--green)" onclick="ouvrirEncaissement(' + rdv.id + ')">Encaisser</button>';
            html += '<button class="btn btn-ghost" style="' + sz + '" onclick="telechargerFacture(' + rdv.id + ')">Facture PDF</button>';
        }
        html += '<button class="btn btn-ghost" style="' + sz + '" onclick="telechargerOR(' + rdv.id + ')">OR</button>';
    } else if (s === 'paye') {
        if (showBillingActions && canUseBilling()) {
            html += '<button class="btn btn-ghost" style="' + sz + '" onclick="telechargerFacture(' + rdv.id + ')">Facture PDF</button>';
        }
        html += '<button class="btn btn-ghost" style="' + sz + '" onclick="telechargerOR(' + rdv.id + ')">OR</button>';
    }

    // OR PDF pour statuts intermédiaires (confirme, reception, en_cours)
    if (s === 'confirme' || s === 'reception' || s === 'en_cours') {
        html += '<button class="btn btn-ghost" style="' + sz + '" onclick="telechargerOR(' + rdv.id + ')">OR</button>';
    }

    html += '</div>';
    return html;
}

// Assignation mecanicien + pont
function ouvrirAssignation(rdvId) {
    apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
        renderAssignationModal(rdvId, rdv);
    }).catch(function() {
        renderAssignationModal(rdvId, null);
    });
}

function renderAssignationModal(rdvId, rdv) {
    var html = '';
    var isFinalized = !!(rdv && ['termine', 'facture', 'paye'].indexOf((rdv.statut || '')) !== -1);
    var disabled = isFinalized ? ' disabled' : '';
    var note = isFinalized
        ? '<div style="font-size:12px;color:#fbbf24;margin-bottom:8px">RDV finalise: l\'historique pont/technicien est verrouille.</div>'
        : '<div style="font-size:12px;color:#9ca3af;margin-bottom:8px">Si vous changez le technicien, le pont se met automatiquement sur son pont affecte.</div>';
    html += note;

    // Select technicien (source de verite)
    html += '<div class="form-group"><label class="form-label" style="color:#ccc">Technicien</label><select id="assign-meca" class="form-select"' + disabled + '>';
    html += '<option value="">-- Non assigne --</option>';
    APP.mecaniciens.forEach(function(m) {
        if (!isActive(m)) return;
        var sel = (rdv && rdv.mecanicien_id === m.id) ? ' selected' : '';
        html += '<option value="' + m.id + '"' + sel + '>' + escapeHtml(m.prenom) + ' ' + escapeHtml(m.nom) + '</option>';
    });
    html += '</select></div>';

    html += '<button class="btn btn-primary" style="width:100%;margin-top:12px"' + disabled + ' onclick="sauverAssignation(' + rdvId + ')">Sauvegarder</button>';

    showModal('Assigner RDV #' + rdvId, html, '400px');
}

function sauverAssignation(rdvId) {
    var mecaId = document.getElementById('assign-meca').value;
    var data = {};
    data.mecanicien_id = mecaId ? parseInt(mecaId, 10) : null;

    apiPut('/api/rendez-vous/' + rdvId, data).then(function() {
        closeModal();
        refreshCurrentSection();
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

// ===== DASHBOARD =====
function loadDashboard() {
    var today = new Date().toISOString().split('T')[0];
    var isSrc = APP.currentUser && APP.currentUser.role === 'service_client';
    Promise.all([
        apiGet('/api/rendez-vous?date=' + today).then(function(r) { return r.json(); }).catch(function() { return []; }),
        apiGet('/api/ponts/status').then(function(r) { return r.json(); }).catch(function() { return []; }),
        isSrc ? Promise.resolve(null) : apiGet('/api/factures/stats').then(function(r) { return r.json(); }).catch(function() { return null; })
    ]).then(function(results) {
        var rdvs = results[0];
        var pontsStatus = results[1];
        var facturationStats = results[2];
        APP.rdvs = rdvs;
        if (pontsStatus.length) APP.ponts = pontsStatus;
        renderDashboardStats(rdvs, pontsStatus, facturationStats);
        renderDashboardPonts(pontsStatus, rdvs);
        renderDashboardRdv(rdvs);
    });
}

function renderDashboardStats(rdvs, ponts, facturationStats) {
    var container = document.getElementById('dashboard-stats');
    var nbRdv = rdvs.length;
    var enCours = rdvs.filter(function(r) { return r.statut === 'en_cours'; }).length;
    var termines = rdvs.filter(function(r) { return r.statut === 'termine'; }).length;
    var enAttente = rdvs.filter(function(r) { return r.statut === 'en_attente' || r.statut === 'reserve'; }).length;
    var pontsActifs = ponts.filter(function(p) { return p.actif; }).length;
    var pontsOccupes = ponts.filter(function(p) { return p.status === 'occupe'; }).length;
    var tauxOcc = pontsActifs > 0 ? Math.round((pontsOccupes / pontsActifs) * 100) : 0;
    var orOuverts = rdvs.filter(function(r) { return r.statut !== 'termine' && r.statut !== 'facture' && r.statut !== 'paye' && r.statut !== 'annule' && r.statut !== 'non_presente'; }).length;
    var caEncaisse = facturationStats && typeof facturationStats.ca_encaisse_mois === 'number'
        ? Number(facturationStats.ca_encaisse_mois)
        : 0;
    var caFormatted = caEncaisse >= 1000 ? (Math.round(caEncaisse / 100) / 10) + 'k' : Math.round(caEncaisse);

    container.innerHTML =
        '<div class="stat-card"><div class="stat-icon" style="float:right;font-size:24px;opacity:.4">&#127949;</div><div class="stat-label">RDV AUJOURD\'HUI</div><div class="stat-value">' + nbRdv + '</div><div class="stat-delta" style="color:var(--green)">' + termines + ' termines | ' + enAttente + ' en attente</div><div class="stat-bar"><div class="stat-bar-fill" style="width:' + Math.min(100, nbRdv * 8) + '%;background:var(--orange)"></div></div></div>' +
        '<div class="stat-card"><div class="stat-icon" style="float:right;font-size:24px;opacity:.4">&#128295;</div><div class="stat-label">OR OUVERTS</div><div class="stat-value">' + orOuverts + '</div><div class="stat-delta" style="color:var(--amber)">' + enCours + ' en cours</div><div class="stat-bar"><div class="stat-bar-fill" style="width:' + Math.min(100, orOuverts * 12) + '%;background:var(--amber)"></div></div></div>' +
        '<div class="stat-card"><div class="stat-icon" style="float:right;font-size:24px;opacity:.4">&#9889;</div><div class="stat-label">TAUX D\'OCCUPATION</div><div class="stat-value">' + tauxOcc + '<span style="font-size:18px">%</span></div><div class="stat-delta" style="color:var(--green)">Ponts ' + pontsOccupes + '/' + pontsActifs + ' occupes</div><div class="stat-bar"><div class="stat-bar-fill" style="width:' + tauxOcc + '%;background:var(--green)"></div></div></div>' +
        '<div class="stat-card"><div class="stat-icon" style="float:right;font-size:24px;opacity:.4">&#128182;</div><div class="stat-label">CA ENCAISSE</div><div class="stat-value">' + caFormatted + '<span style="font-size:16px">\u20AC</span></div><div class="stat-delta" style="color:var(--green)">Mois en cours</div><div class="stat-bar"><div class="stat-bar-fill" style="width:' + Math.min(100, caEncaisse / 50) + '%;background:var(--teal)"></div></div></div>';
}

function renderDashboardPonts(ponts, rdvs) {
    var container = document.getElementById('dashboard-ponts');
    var html = '';
    ponts.forEach(function(pont) {
        var statusClass, statusLabel, statusColor, pontContent = '';

        if (pont.status === 'maintenance' || !pont.actif) {
            statusClass = 'maintenance'; statusLabel = 'Maintenance'; statusColor = 'var(--amber)';
            pontContent = '<div style="font-size:12px;color:#888;margin-top:4px">Indisponible</div>' +
                '<div class="pont-progress"><div class="pont-progress-fill" style="width:100%;background:var(--amber)"></div></div>' +
                '<div style="font-size:10px;color:#666;margin-top:4px">Indisponible</div>';
        } else if (pont.status === 'sans_mecanicien') {
            statusClass = 'maintenance'; statusLabel = 'Sans mecano'; statusColor = '#ef4444';
            pontContent = '<div style="font-size:12px;color:#ef4444;margin-top:4px">Aucun mecanicien assigne</div>' +
                '<div class="pont-progress"><div class="pont-progress-fill" style="width:100%;background:#ef4444"></div></div>' +
                '<div style="font-size:10px;color:#666;margin-top:4px">Indisponible pour RDV</div>';
        } else if (pont.status === 'mecanicien_absent') {
            statusClass = 'maintenance'; statusLabel = 'Absent'; statusColor = '#f59e0b';
            var mecaNomAbs = pont.mecanicien ? escapeHtml(pont.mecanicien.prenom) + ' ' + escapeHtml(pont.mecanicien.nom) : '';
            pontContent = '<div style="font-size:12px;color:#f59e0b;margin-top:4px">' + mecaNomAbs + ' absent(e)</div>' +
                '<div class="pont-progress"><div class="pont-progress-fill" style="width:100%;background:#f59e0b"></div></div>' +
                '<div style="font-size:10px;color:#666;margin-top:4px">Indisponible aujourd\'hui</div>';
        } else if (pont.status === 'occupe' && pont.rdv_en_cours) {
            statusClass = 'occupe'; statusLabel = 'En cours'; statusColor = 'var(--orange)';
            var rc = pont.rdv_en_cours;
            var veh = rc.vehicule ? escapeHtml(rc.vehicule.marque || '') + ' ' + escapeHtml(rc.vehicule.modele || '') : '';
            var mecaNom = rc.mecanicien ? escapeHtml(rc.mecanicien.prenom.charAt(0)) + '. ' + escapeHtml(rc.mecanicien.nom) : '';
            var prog = pont.progression || 50;
            var progColor = prog >= 80 ? 'var(--amber)' : 'var(--orange)';
            pontContent = '<div class="pont-moto">' + (veh ? '\u{1F3CD}\uFE0F ' + veh : '') + '</div>' +
                '<div class="pont-meca">\u{1F464} ' + mecaNom + ' \u2022 ' + escapeHtml(rc.type_intervention || '') + '</div>' +
                '<div class="pont-progress"><div class="pont-progress-fill" style="width:' + prog + '%;background:' + progColor + '"></div></div>' +
                '<div style="font-size:10px;color:#777;margin-top:4px">' + prog + '%' + (pont.heure_fin_estimee ? ' \u2022 Fin estimee ' + pont.heure_fin_estimee : '') + '</div>';
        } else {
            statusClass = 'libre'; statusLabel = 'Libre'; statusColor = 'var(--green)';
            pontContent = '<div style="font-size:12px;color:#666;margin-top:4px">Disponible</div>';
            if (pont.prochain_rdv) {
                pontContent += '<div style="font-size:11px;color:#555;margin-top:2px">Prochain RDV : ' + formatTime(pont.prochain_rdv.heure_rdv) + '</div>';
            }
            pontContent += '<div class="pont-progress"><div class="pont-progress-fill" style="width:0%;background:var(--green)"></div></div>' +
                '<div style="font-size:10px;color:#444;margin-top:4px">\u2014 Libre maintenant</div>';
        }

        var badgeClass = statusClass === 'libre' ? 'green' : (statusClass === 'occupe' ? 'orange' : 'amber');
        html += '<div class="pont-card ' + statusClass + '">' +
            '<div style="display:flex;align-items:center;gap:8px">' +
                '<span class="pont-status-dot" style="background:' + statusColor + '"></span>' +
                '<span class="pont-name">' + escapeHtml(pont.nom || 'Pont ' + pont.id) + '</span>' +
                '<span class="badge ' + badgeClass + '" style="margin-left:auto;font-size:10px">' + statusLabel + '</span>' +
            '</div>' +
            '<div class="pont-num" style="color:' + statusColor + '">P' + pont.id + '</div>' +
            pontContent +
        '</div>';
    });
    container.innerHTML = html || '<div style="color:#666;padding:20px">Aucun pont configure</div>';
}

function renderDashboardRdv(rdvs) {
    var container = document.getElementById('dashboard-rdv-table');
    document.getElementById('dashboard-rdv-count').textContent = rdvs.length + ' rendez-vous';
    if (!rdvs.length) {
        container.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#666;padding:20px">Aucun RDV aujourd\'hui</td></tr>';
        return;
    }
    rdvs.sort(function(a, b) { return (a.heure_rdv || '').localeCompare(b.heure_rdv || ''); });
    var html = '';
    rdvs.forEach(function(rdv) {
        var meca = rdv.mecanicien || APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
        var mecaNom = meca ? ((escapeHtml((meca.prenom || '').charAt(0)) + '. ' + escapeHtml(meca.nom || '')).trim()) : '-';
        var pont = rdv.pont || APP.ponts.find(function(p) { return p.id === rdv.pont_id; });
        var pontNom = pont ? escapeHtml(pont.nom || ('P' + pont.id)) : '-';
        var duree = rdv.temps_estime ? Math.round(rdv.temps_estime / 60 * 10) / 10 + 'h' : '-';
        var v = rdv.vehicule || {};
        var c = rdv.client || {};
        html += '<tr>' +
            '<td><b style="color:var(--orange)">' + formatTime(rdv.heure_rdv) + '</b></td>' +
            '<td><div class="moto-cell"><div class="moto-icon" style="background:#1a2a2a">&#127949;</div>' + escapeHtml(v.marque || '') + ' ' + escapeHtml(v.modele || '') + '</div></td>' +
            '<td>' + escapeHtml((c.prenom || '').charAt(0)) + '. ' + escapeHtml(c.nom || '') + '</td>' +
            '<td>' + escapeHtml(rdv.type_intervention || '-') + '</td>' +
            '<td>' + duree + '</td>' +
            '<td>' + pontNom + '</td>' +
            '<td>' + mecaNom + '</td>' +
            '<td>' + statusBadge(rdv.statut) + '</td>' +
            '<td>' + actionButtons(rdv, true) + '</td>' +
            '</tr>';
    });
    container.innerHTML = html;
}

// ===== PRISE DE RDV =====
function loadRdvForm() {
    APP.rdvClientPrefill = null;
    APP.rdvWizard = {
        step: 1,
        vehicule: null,
        motoType: '',
        interventions: [],
        selected: [],
        totalPrix: 0,
        totalTemps: 0,
        delaiInterventionJours: 1,
        selectedDate: null,
        selectedHeure: null,
        selectedPont: null,
        selectedMeca: null,
        weekOffset: 0,
        weekData: {},
        selectedAtelierSlug: getCurrentAtelierSlug()
    };
    populateRdvAtelierSelect();
    var res = document.getElementById('rdv-client-search-results');
    if (res) res.innerHTML = '<div style="font-size:12px;color:#888">Tapez au moins 2 caracteres pour rechercher un client existant.</div>';
    ['pub-prenom', 'pub-nom', 'pub-tel', 'pub-email', 'pub-comment', 'pub-plaque'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
    });
    var r1 = document.getElementById('pub-veh-result'); if (r1) r1.style.display = 'none';
    var r2 = document.getElementById('pub-veh-notfound'); if (r2) r2.style.display = 'none';
    goStep(1);
}

function searchMotoRdv(val) {
    var result = document.getElementById('rdv-moto-result');
    if (val.length < 3) { result.style.display = 'none'; return; }
    var plaque = val.replace(/[\s-]/g, '').toUpperCase();
    apiGet('/api/vehicule/' + encodeURIComponent(plaque)).then(function(r) { return r.json(); }).then(function(data) {
        if (data && !data.not_found) {
            result.style.display = 'block';
            document.getElementById('rdv-moto-name').textContent = (escapeHtml(data.marque) || '') + ' ' + (escapeHtml(data.modele) || '') + (escapeHtml(data.annee) ? ' (' + data.annee + ')' : '');
            document.getElementById('rdv-moto-detail').textContent = (data.type_moto || '') + ' | ' + (data.cylindree || '') + ' | ' + (data.plaque || '');
            if (data.client) {
                document.getElementById('rdv-client-name').value = data.client.nom + ' ' + data.client.prenom;
                document.getElementById('rdv-client-tel').value = data.client.telephone || '';
            }
        } else { result.style.display = 'none'; }
    }).catch(function() { result.style.display = 'none'; });
}

function updateDurationRdv() {
    var intSelect = document.getElementById('rdv-intervention-type');
    var opt = intSelect.options[intSelect.selectedIndex];
    var durMin = parseInt(opt.getAttribute('data-dur') || '0');
    var catId = document.getElementById('rdv-moto-type').value;
    var block = document.getElementById('rdv-duration-block');
    var val = document.getElementById('rdv-duration-val');
    if (catId && durMin > 0) {
        block.style.display = 'block';
        var h = Math.floor(durMin / 60); var m = durMin % 60;
        val.textContent = (h > 0 ? h + 'h' : '') + (m > 0 ? m + 'min' : '') + ' estimees';
    } else { block.style.display = 'none'; }
}

function loadCreneaux() {
    var dateVal = document.getElementById('rdv-date').value;
    if (!dateVal) return;
    var intSelect = document.getElementById('rdv-intervention-type');
    var opt = intSelect.options[intSelect.selectedIndex];
    var durMin = parseInt(opt.getAttribute('data-dur') || '60');

    var creneauxContainer = document.getElementById('rdv-creneaux-grid');
    setLoadingState(creneauxContainer, true, 'Chargement des creneaux...');
    apiGet('/api/creneaux/disponibles?date_str=' + dateVal + '&duree_minutes=' + durMin).then(function(r) { return r.json(); }).then(function(creneaux) {
        renderCreneaux(creneaux);
    }).catch(function() {
        var fb = getPlanningBounds();
        var slots = buildPlanningSlots(fb.start, fb.end, 15);
        renderCreneaux(slots.map(function(h) { return { heure: h, disponible: true }; }));
    });
}

function renderCreneaux(creneaux) {
    var container = document.getElementById('rdv-creneaux-grid');
    var html = '';
    if (Array.isArray(creneaux)) {
        creneaux.forEach(function(c) {
            var heure = c.heure || c;
            var dispo = c.disponible !== false;
            if (dispo) html += '<div class="dispo-slot available" onclick="selectSlotRdv(this,\'' + escapeAttr(heure) + '\')">' + escapeHtml(heure) + '</div>';
            else html += '<div class="dispo-slot full">' + heure + '</div>';
        });
    }
    container.innerHTML = html || '<div style="color:#666;padding:10px">Aucun creneau disponible</div>';
    updateLiveRegion(html ? 'Creneaux charges' : 'Aucun creneau disponible');
}

function selectSlotRdv(el, time) {
    document.querySelectorAll('#rdv-creneaux-grid .dispo-slot.available').forEach(function(s) { s.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('rdv-selected-slot').style.display = 'block';
    document.getElementById('rdv-slot-time').textContent = time;
    APP.selectedSlot = time;
}

function confirmRdv() {
    var clientName = document.getElementById('rdv-client-name').value;
    var clientTel = document.getElementById('rdv-client-tel').value;
    var plaque = document.getElementById('rdv-vin-input').value;
    var dateVal = document.getElementById('rdv-date').value;
    var intSelect = document.getElementById('rdv-intervention-type');
    var intOpt = intSelect.options[intSelect.selectedIndex];
    var typeIntervention = intOpt ? intOpt.textContent : '';

    var hasError = false;
    var nameErr = document.getElementById('rdv-client-name-error');
    var telErr = document.getElementById('rdv-client-tel-error');
    if (nameErr) nameErr.textContent = '';
    if (telErr) telErr.textContent = '';
    if (!clientName || clientName.trim().length < 2) {
        if (nameErr) nameErr.textContent = 'Nom client obligatoire';
        hasError = true;
    }
    if (!clientTel || clientTel.replace(/\D/g, '').length < 10) {
        if (telErr) telErr.textContent = 'Telephone invalide (10 chiffres min)';
        hasError = true;
    }
    if (!dateVal || !APP.selectedSlot || !intSelect.value) {
        showAlert('Veuillez remplir tous les champs obligatoires', 'warning');
        hasError = true;
    }
    if (hasError) return;

    var parts = clientName.trim().split(/\s+/);
    var nom = parts[0] || ''; var prenom = parts.slice(1).join(' ') || '';

    apiPost('/api/rendez-vous', {
        client: { nom: nom, prenom: prenom, telephone: clientTel || '0000000000' },
        vehicule: { plaque: plaque || 'XX-000-XX' },
        date_rdv: dateVal,
        heure_rdv: APP.selectedSlot + ':00',
        type_intervention: typeIntervention
    }).then(function(r) { return r.json(); }).then(function(data) {
        var confirmEl = document.getElementById('rdv-confirm');
        confirmEl.style.display = 'block';
        document.getElementById('rdv-confirm-msg').textContent = 'RDV #' + (data.id || '') + ' cree avec succes - Statut: Reserve';
        updateLiveRegion('Rendez-vous cree avec succes');
        // Reset form
        APP.selectedSlot = null;
    }).catch(function(e) {
        alert('Erreur creation RDV: ' + e.message);
    });
}

var _rdvClientSearchTimer = null;
function searchClientRdvEmbed(val) {
    var container = document.getElementById('rdv-client-search-results');
    if (!container) return;
    var q = (val || '').trim();
    if (_rdvClientSearchTimer) clearTimeout(_rdvClientSearchTimer);
    if (q.length < 2) {
        container.innerHTML = '<div style="font-size:12px;color:#888">Tapez au moins 2 caracteres pour rechercher un client existant.</div>';
        return;
    }
    _rdvClientSearchTimer = setTimeout(function() {
        apiGet('/api/clients?search=' + encodeURIComponent(q)).then(function(r) { return r.json(); }).then(function(clients) {
            if (!clients || !clients.length) {
                container.innerHTML = '<div style="font-size:12px;color:#888">Aucun client trouve. Saisie manuelle possible dans le formulaire.</div>';
                return;
            }
            var html = '';
            clients.slice(0, 8).forEach(function(c) {
                html += '<div style="padding:8px;border-bottom:1px solid #2a2a33;cursor:pointer" onclick="selectClientRdvEmbed(' + c.id + ')">' +
                    '<div style="color:#eee;font-weight:600">' + escapeHtml((c.prenom || '') + ' ' + (c.nom || '')) + '</div>' +
                    '<div style="font-size:12px;color:#888">' + escapeHtml(c.telephone || '-') + ' • ' + escapeHtml(c.email || '-') + '</div>' +
                    '</div>';
            });
            container.innerHTML = html;
        }).catch(function(e) {
            container.innerHTML = '<div style="font-size:12px;color:#ef4444">Erreur: ' + escapeHtml(e.message || 'recherche client') + '</div>';
        });
    }, 220);
}

function selectClientRdvEmbed(clientId) {
    apiGet('/api/clients/' + clientId).then(function(r) { return r.json(); }).then(function(c) {
        APP.rdvClientPrefill = {
            nom: c.nom || '',
            prenom: c.prenom || '',
            telephone: c.telephone || '',
            email: c.email || ''
        };
        var container = document.getElementById('rdv-client-search-results');
        if (container) {
            container.innerHTML = '<div style="font-size:12px;color:#22c55e">Client selectionne: ' + escapeHtml((c.prenom || '') + ' ' + (c.nom || '')) + '</div>';
        }
        ['pub-prenom', 'pub-nom', 'pub-tel', 'pub-email'].forEach(function(id, i) {
            var el = document.getElementById(id);
            if (!el) return;
            if (i === 0) el.value = APP.rdvClientPrefill.prenom || '';
            if (i === 1) el.value = APP.rdvClientPrefill.nom || '';
            if (i === 2) el.value = APP.rdvClientPrefill.telephone || '';
            if (i === 3) el.value = APP.rdvClientPrefill.email || '';
        });
    }).catch(function(e) { showAlert('Erreur client: ' + e.message, 'error'); });
}

function pushRdvPrefillToIframe() {
    // Legacy no-op: iframe integration removed in favor of native wizard
}

function ouvrirRdvPublicModal() {
    // Legacy no-op: modal integration removed in favor of native wizard
}

function getCurrentAtelierSlug() {
    var RDV = APP.rdvWizard;
    if (RDV && RDV.selectedAtelierSlug) return RDV.selectedAtelierSlug;
    if (APP.currentUser && APP.currentUser.atelier_slug) return APP.currentUser.atelier_slug;
    if (APP.currentUser && APP.currentUser.ateliers && APP.currentUser.ateliers.length) {
        for (var i = 0; i < APP.currentUser.ateliers.length; i++) {
            if (APP.currentUser.ateliers[i].atelier_id === APP.currentUser.atelier_id) return APP.currentUser.ateliers[i].slug || null;
        }
    }
    return 'default';
}

function populateRdvAtelierSelect() {
    var selectTop = document.getElementById('rdv-atelier-select');
    if (!selectTop) return;
    var canSelectAtelier = hasPermission('rdv.select_atelier');
    var wrap = selectTop.closest('.form-group') || selectTop.parentElement;
    if (wrap) wrap.style.display = canSelectAtelier ? '' : 'none';
    function renderSelects(ateliers) {
        ateliers = Array.isArray(ateliers) ? ateliers.filter(function(a) { return !!(a && a.slug); }) : [];
        if (!ateliers.length) {
            var fallbackSlug = getCurrentAtelierSlug() || 'default';
            selectTop.innerHTML = '<option value="' + escapeAttr(fallbackSlug) + '">' + escapeHtml(fallbackSlug) + '</option>';
            selectTop.value = fallbackSlug;
            selectTop.disabled = true;
            return;
        }
        var html = '';
        ateliers.forEach(function(a) {
            var slug = a.slug || '';
            var aid = a.atelier_id || a.id || '';
            var label = (a.nom || slug || ('Atelier #' + aid));
            html += '<option value="' + escapeAttr(slug) + '">' + escapeHtml(label) + '</option>';
        });
        selectTop.innerHTML = html;
        var RDV = getRdvState();
        if (!RDV.selectedAtelierSlug) RDV.selectedAtelierSlug = getCurrentAtelierSlug();
        var exists = ateliers.some(function(a) { return a.slug === RDV.selectedAtelierSlug; });
        if (!exists) RDV.selectedAtelierSlug = ateliers[0].slug || RDV.selectedAtelierSlug;
        if (!canSelectAtelier) {
            var own = ateliers.find(function(a) {
                var aid = a.atelier_id || a.id;
                return aid === (APP.currentUser ? APP.currentUser.atelier_id : null);
            });
            RDV.selectedAtelierSlug = (own && own.slug) ? own.slug : RDV.selectedAtelierSlug;
        }
        selectTop.value = RDV.selectedAtelierSlug;
        selectTop.disabled = !canSelectAtelier;
    }

    if (canSelectAtelier) {
        apiGet('/api/ateliers/public').then(function(r) { return r.json(); }).then(function(items) {
            renderSelects(items);
        }).catch(function() {
            renderSelects((APP.currentUser && Array.isArray(APP.currentUser.ateliers)) ? APP.currentUser.ateliers : []);
        });
        return;
    }
    renderSelects((APP.currentUser && Array.isArray(APP.currentUser.ateliers)) ? APP.currentUser.ateliers : []);
}

function onRdvAtelierChange(slug) {
    var RDV = getRdvState();
    var nextSlug = (slug || '').trim().toLowerCase();
    if (!nextSlug || nextSlug === RDV.selectedAtelierSlug) return;
    RDV.selectedAtelierSlug = nextSlug;
    RDV.interventions = [];
    RDV.selected = [];
    RDV.totalPrix = 0;
    RDV.totalTemps = 0;
    RDV.weekData = {};
    RDV.selectedDate = null;
    RDV.selectedHeure = null;
    RDV.selectedPont = null;
    RDV.selectedMeca = null;
    var presta = document.getElementById('pub-prestations');
    if (presta) presta.innerHTML = '<div style="color:#888">Choisissez vos prestations pour cet atelier.</div>';
    var recap = document.getElementById('pub-recap');
    if (recap) recap.style.display = 'none';
    var dur = document.getElementById('pub-duration');
    if (dur) dur.style.display = 'none';
    var b3 = document.getElementById('pub-btn-step3');
    if (b3) b3.disabled = true;
    if (RDV.step >= 2) chargerPrestations();
    if (RDV.step >= 3) chargerSemaine();
}

function getRdvState() {
    if (!APP.rdvWizard) loadRdvForm();
    return APP.rdvWizard;
}

function goStep(n) {
    var RDV = getRdvState();
    RDV.step = n;
    var steps = document.querySelectorAll('#rdvw-root .wizard-step');
    for (var i = 0; i < steps.length; i++) steps[i].classList.remove('active');
    var target = document.getElementById('step-' + n);
    if (target) target.classList.add('active');
    for (var s = 1; s <= 4; s++) {
        var dot = document.getElementById('sd-' + s);
        var line = document.getElementById('sl-' + s);
        if (dot) {
            dot.className = 'step-dot' + (s < n ? ' done' : (s === n ? ' active' : ''));
            dot.textContent = s < n ? '✓' : String(s);
        }
        if (line) line.className = 'step-line' + (s < n ? ' done' : '');
    }
    if (n === 2) chargerPrestations();
    if (n === 3) chargerDelaiIntervention().then(chargerSemaine).catch(chargerSemaine);
    if (n === 4) afficherRecap();
}

function rechercherVehicule() {
    var RDV = getRdvState();
    var plaque = (document.getElementById('pub-plaque').value || '').replace(/[\s-]/g, '').toUpperCase();
    if (plaque.length < 3) { alert('Entrez une plaque valide'); return; }
    var btn = document.getElementById('btn-search');
    if (btn) { btn.innerHTML = '<span class="loading-spinner"></span> Recherche...'; btn.disabled = true; }
    apiGet('/api/vehicule/' + encodeURIComponent(plaque)).then(function(r) { return r.json(); }).then(function(data) {
        if (btn) { btn.textContent = 'Rechercher mon vehicule'; btn.disabled = false; }
        if (data && !data.not_found) {
            RDV.vehicule = data;
            RDV.motoType = data.type_moto || '';
            document.getElementById('pub-v-marque').textContent = data.marque || '-';
            document.getElementById('pub-v-modele').textContent = data.modele || '-';
            document.getElementById('pub-v-annee').textContent = data.annee || '-';
            document.getElementById('pub-v-cylindree').textContent = data.cylindree || '-';
            document.getElementById('pub-v-type').textContent = data.type_moto || 'Non defini';
            document.getElementById('pub-v-plaque').textContent = data.plaque || plaque;
            document.getElementById('pub-v-type-missing').style.display = data.type_moto ? 'none' : 'block';
            document.getElementById('pub-veh-result').style.display = 'block';
            document.getElementById('pub-veh-notfound').style.display = 'none';
        } else {
            RDV.vehicule = { plaque: plaque };
            document.getElementById('pub-veh-result').style.display = 'none';
            document.getElementById('pub-veh-notfound').style.display = 'block';
        }
    }).catch(function() {
        if (btn) { btn.textContent = 'Rechercher mon vehicule'; btn.disabled = false; }
    });
}

function validerVehiculeManuel() {
    var RDV = getRdvState();
    var marque = (document.getElementById('pub-man-marque').value || '').trim();
    var modele = (document.getElementById('pub-man-modele').value || '').trim();
    var annee = (document.getElementById('pub-man-annee').value || '').trim();
    var cylindree = (document.getElementById('pub-man-cylindree').value || '').trim();
    var typeMoto = (document.getElementById('pub-moto-type-manual').value || '').trim();
    if (!marque || !modele || !annee || !typeMoto) { alert('Veuillez remplir les champs obligatoires'); return; }
    var plaque = (document.getElementById('pub-plaque').value || '').replace(/[\s-]/g, '').toUpperCase();
    RDV.vehicule = { plaque: plaque || 'XX-000-XX', marque: marque.toUpperCase(), modele: modele, annee: parseInt(annee, 10), cylindree: cylindree || null, type_moto: typeMoto };
    RDV.motoType = typeMoto;
    goStep(2);
}

function onTypeMotoFoundSelect(val) {
    var RDV = getRdvState();
    if (!val) return;
    RDV.motoType = val;
    if (RDV.vehicule) RDV.vehicule.type_moto = val;
    document.getElementById('pub-v-type').textContent = val;
}

function continuerVehiculeFound() {
    var RDV = getRdvState();
    if (!RDV.motoType) { alert('Veuillez selectionner le type de moto'); return; }
    goStep(2);
}

function getPrestaTarif(it) {
    var RDV = getRdvState();
    var t = it.tarifs && it.tarifs[RDV.motoType];
    if (t) return { prix_ttc: t.prix_ttc, temps_minutes: t.temps_minutes };
    return { prix_ttc: it.prix_base_ttc || 0, temps_minutes: it.temps_estime_minutes || 30 };
}

function chargerPrestations() {
    var RDV = getRdvState();
    if (RDV.interventions.length > 0) { renderPrestations(); return; }
    var atelierSlug = getCurrentAtelierSlug();
    apiGet('/api/prestations/public?atelier_slug=' + encodeURIComponent(atelierSlug)).then(function(r) { return r.json(); }).then(function(data) {
        RDV.interventions = (Array.isArray(data) ? data : []).map(function(it) {
            return { id: it.id, nom: it.nom, description: it.description || '', prix_base_ttc: it.prix_base_ttc != null ? it.prix_base_ttc : (it.prix_base || 0), temps_estime_minutes: it.temps_estime_minutes != null ? it.temps_estime_minutes : (it.temps_estime || 30), tarifs: it.grille || it.tarifs || {}, delai_intervention_jours: it.delai_intervention_jours != null ? it.delai_intervention_jours : 1 };
        });
        renderPrestations();
    }).catch(function() {
        document.getElementById('pub-prestations').innerHTML = '<div style="color:#666">Erreur chargement des prestations</div>';
    });
}

function renderPrestations() {
    var RDV = getRdvState();
    var container = document.getElementById('pub-prestations');
    if (!container) return;
    var html = '';
    RDV.interventions.forEach(function(it) {
        var selected = RDV.selected.indexOf(it.id) !== -1;
        var tarif = getPrestaTarif(it);
        html += '<div class="presta-card' + (selected ? ' selected' : '') + '" onclick="togglePresta(' + it.id + ')"><div class="presta-check">' + (selected ? '✓' : '') + '</div><div class="presta-info"><div class="presta-name">' + escapeHtml(it.nom) + '</div><div class="presta-detail">' + escapeHtml(it.description || '') + '</div></div><div class="presta-price">' + Number(tarif.prix_ttc || 0).toFixed(2) + ' EUR</div></div>';
    });
    container.innerHTML = html;
    updateRecap();
}

function togglePresta(id) {
    var RDV = getRdvState();
    var idx = RDV.selected.indexOf(id);
    if (idx === -1) RDV.selected.push(id); else RDV.selected.splice(idx, 1);
    renderPrestations();
    var btn = document.getElementById('pub-btn-step3');
    if (btn) btn.disabled = RDV.selected.length === 0;
}

function updateRecap() {
    var RDV = getRdvState();
    var recap = document.getElementById('pub-recap');
    var lines = document.getElementById('pub-recap-lines');
    var total = document.getElementById('pub-recap-total');
    var durBlock = document.getElementById('pub-duration');
    if (!recap || !lines || !total || !durBlock) return;
    if (RDV.selected.length === 0) { recap.style.display = 'none'; durBlock.style.display = 'none'; return; }
    recap.style.display = 'block';
    durBlock.style.display = 'block';
    var prix = 0; var temps = 0; var html = '';
    RDV.selected.forEach(function(id) {
        var it = RDV.interventions.find(function(x) { return x.id === id; });
        if (!it) return;
        var tarif = getPrestaTarif(it);
        var p = Number(tarif.prix_ttc || 0);
        var t = parseInt(tarif.temps_minutes || 0, 10) || 0;
        prix += p; temps += t;
        html += '<div class="recap-row"><span>' + escapeHtml(it.nom) + '</span><span>' + p.toFixed(2) + ' EUR</span></div>';
    });
    lines.innerHTML = html;
    total.textContent = prix.toFixed(2) + ' EUR';
    RDV.totalPrix = prix; RDV.totalTemps = temps;
    var h = Math.floor(temps / 60); var m = temps % 60;
    document.getElementById('pub-dur-value').textContent = (h > 0 ? h + 'h' : '') + (m > 0 ? m + 'min' : '');
    document.getElementById('pub-dur-note').textContent = RDV.motoType ? ('Tarif ' + RDV.motoType) : '';
}

function chargerDelaiIntervention() {
    var RDV = getRdvState();
    if (!RDV.selected.length) { RDV.delaiInterventionJours = 1; return Promise.resolve(); }
    var atelierSlug = getCurrentAtelierSlug();
    return apiGet('/api/tarifs/delais?prestation_ids=' + encodeURIComponent(RDV.selected.join(',')) + '&atelier_slug=' + encodeURIComponent(atelierSlug)).then(function(r) { return r.json(); }).then(function(data) {
        var d = parseInt(data.delai_total_jours, 10);
        RDV.delaiInterventionJours = (isFinite(d) && d > 0) ? d : 1;
    }).catch(function() { RDV.delaiInterventionJours = 1; });
}

function _rdvDateToStr(d) {
    var y = d.getFullYear();
    var m = String(d.getMonth() + 1).padStart(2, '0');
    var dd = String(d.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + dd;
}

function _rdvWeekDays(offset, delayDays) {
    var now = new Date();
    var earliest = new Date(now);
    earliest.setDate(now.getDate() + (Math.max(1, parseInt(delayDays || 1, 10)) - 1));
    earliest.setHours(0, 0, 0, 0);
    var day = earliest.getDay();
    var diff = (day === 0 ? -6 : 1 - day);
    var monday = new Date(earliest);
    monday.setDate(earliest.getDate() + diff + (offset * 7));
    var days = [];
    for (var i = 0; i < 5; i++) { var d = new Date(monday); d.setDate(monday.getDate() + i); days.push(d); }
    return days;
}

function changeWeek(dir) {
    var RDV = getRdvState();
    RDV.weekOffset += dir;
    if (RDV.weekOffset < 0) RDV.weekOffset = 0;
    chargerSemaine();
}

function chargerSemaine() {
    var RDV = getRdvState();
    var days = _rdvWeekDays(RDV.weekOffset, RDV.delaiInterventionJours);
    var monday = days[0], friday = days[4];
    var MOIS = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aou', 'Sep', 'Oct', 'Nov', 'Dec'];
    var JOURS_COURTS = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    var weekLabel = document.getElementById('pub-week-label');
    if (weekLabel) weekLabel.textContent = 'Semaine du ' + monday.getDate() + ' ' + MOIS[monday.getMonth()] + ' au ' + friday.getDate() + ' ' + MOIS[friday.getMonth()] + ' ' + friday.getFullYear();
    var grid = document.getElementById('pub-week-grid');
    if (!grid) return;
    var now = new Date();
    var earliest = new Date(now);
    earliest.setDate(now.getDate() + (Math.max(1, parseInt(RDV.delaiInterventionJours || 1, 10)) - 1));
    earliest.setHours(0, 0, 0, 0);
    var earliestStr = _rdvDateToStr(earliest);
    var todayStr = _rdvDateToStr(now);
    var html = '';
    days.forEach(function(d) {
        var ds = _rdvDateToStr(d);
        var isPast = ds < earliestStr;
        var isToday = ds === todayStr;
        html += '<div class="week-day' + (isToday ? ' today' : '') + (isPast ? ' past' : '') + '"><div class="week-day-header"><div class="week-day-name">' + JOURS_COURTS[d.getDay()] + '</div><div class="week-day-date">' + d.getDate() + ' ' + MOIS[d.getMonth()] + '</div></div><div class="week-day-slots" id="ws-' + ds + '"><div style="text-align:center;padding:12px;color:#555;font-size:11px">' + (isPast ? 'Passe' : '<span class="loading-spinner"></span>') + '</div></div></div>';
    });
    grid.innerHTML = html;
    days.forEach(function(d) {
        var ds = _rdvDateToStr(d);
        if (ds < earliestStr) return;
        chargerJour(ds);
    });
    var selected = document.getElementById('pub-selected-slot'); if (selected) selected.style.display = 'none';
    var assign = document.getElementById('pub-assign'); if (assign) assign.style.display = 'none';
    var btn = document.getElementById('pub-btn-step4'); if (btn) btn.disabled = true;
}

function chargerJour(dateStr) {
    var RDV = getRdvState();
    if (RDV.weekData[dateStr]) return renderJour(dateStr, RDV.weekData[dateStr]);
    var atelierSlug = getCurrentAtelierSlug();
    var duree = Math.max(30, parseInt(RDV.totalTemps || 60, 10));
    apiGet('/api/creneaux/avec-ponts?date_str=' + dateStr + '&duree_minutes=' + encodeURIComponent(duree) + '&atelier_slug=' + encodeURIComponent(atelierSlug)).then(function(r) { return r.json(); }).then(function(data) {
        RDV.weekData[dateStr] = data || {};
        renderJour(dateStr, RDV.weekData[dateStr]);
    }).catch(function() {
        var c = document.getElementById('ws-' + dateStr);
        if (c) c.innerHTML = '<div style="text-align:center;padding:12px;color:#555;font-size:11px">Erreur</div>';
    });
}

function renderJour(dateStr, data) {
    var RDV = getRdvState();
    var container = document.getElementById('ws-' + dateStr);
    if (!container) return;
    var creneaux = (data && data.creneaux) ? data.creneaux : [];
    if (!creneaux.length) { container.innerHTML = '<div style="text-align:center;padding:12px;color:#444;font-size:11px">Ferme</div>'; return; }
    var todayStr = _rdvDateToStr(new Date());
    var isToday = dateStr === todayStr;
    var now = new Date();
    var nowMins = now.getHours() * 60 + now.getMinutes();
    var html = '';
    creneaux.forEach(function(c) {
        var hm = (c.heure || '').split(':');
        var slotMins = (parseInt(hm[0], 10) || 0) * 60 + (parseInt(hm[1], 10) || 0);
        if (isToday && slotMins <= nowMins) return;
        var isSel = (RDV.selectedDate === dateStr && RDV.selectedHeure === c.heure);
        if (c.disponible) html += '<div class="wslot available' + (isSel ? ' selected' : '') + '" onclick="selectWeekSlot(this,\'' + dateStr + '\',\'' + escapeAttr(c.heure) + '\')">' + escapeHtml(c.heure) + '<div class="wslot-places">' + (c.nb_ponts_libres || 0) + ' pont(s)</div></div>';
        else html += '<div class="wslot full">' + escapeHtml(c.heure) + '<div class="wslot-places">Complet</div></div>';
    });
    container.innerHTML = html || '<div style="text-align:center;padding:12px;color:#444;font-size:11px">Plus de creneaux</div>';
}

function _rdvFormatDate(dateStr) {
    if (!dateStr) return '-';
    var p = dateStr.split('-');
    if (p.length !== 3) return dateStr;
    var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
    var JOURS_LONGS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    return JOURS_LONGS[d.getDay()] + ' ' + p[2] + '/' + p[1] + '/' + p[0];
}

function selectWeekSlot(el, dateStr, heure) {
    var RDV = getRdvState();
    var all = document.querySelectorAll('#rdvw-root .wslot');
    for (var i = 0; i < all.length; i++) all[i].classList.remove('selected');
    el.classList.add('selected');
    RDV.selectedDate = dateStr;
    RDV.selectedHeure = heure;
    document.getElementById('pub-selected-slot').style.display = 'block';
    document.getElementById('pub-slot-label').textContent = _rdvFormatDate(dateStr) + ' a ' + heure;
    document.getElementById('pub-btn-step4').disabled = false;
    RDV.selectedPont = null; RDV.selectedMeca = null;
    var dayData = RDV.weekData[dateStr];
    var assignDiv = document.getElementById('pub-assign');
    if (dayData && dayData.creneaux) {
        var c = dayData.creneaux.find(function(x) { return x.heure === heure; });
        if (c && c.ponts_disponibles && c.ponts_disponibles.length) {
            var p = c.ponts_disponibles[0];
            RDV.selectedPont = p;
            RDV.selectedMeca = p.mecanicien || null;
            document.getElementById('pub-assign-pont').textContent = p.nom || '-';
            document.getElementById('pub-assign-meca').textContent = p.mecanicien || 'Auto-assigne';
            assignDiv.style.display = 'block';
            return;
        }
    }
    assignDiv.style.display = 'none';
}

function afficherRecap() {
    var RDV = getRdvState();
    var v = RDV.vehicule || {};
    document.getElementById('pub-final-veh').textContent = ((v.marque || '') + ' ' + (v.modele || '')).trim() + (v.plaque ? (' (' + v.plaque + ')') : '');
    var prestaNames = [];
    RDV.selected.forEach(function(id) {
        var it = RDV.interventions.find(function(x) { return x.id === id; });
        if (it) prestaNames.push(it.nom);
    });
    document.getElementById('pub-final-presta').textContent = prestaNames.join(', ');
    document.getElementById('pub-final-date').textContent = _rdvFormatDate(RDV.selectedDate) + ' a ' + RDV.selectedHeure;
    document.getElementById('pub-final-pont').textContent = (RDV.selectedPont ? RDV.selectedPont.nom : '-') + ' / ' + (RDV.selectedMeca || '-');
    var h = Math.floor((RDV.totalTemps || 0) / 60), m = (RDV.totalTemps || 0) % 60;
    document.getElementById('pub-final-duree').textContent = (h > 0 ? h + 'h' : '') + (m > 0 ? m + 'min' : '');
    document.getElementById('pub-final-total').textContent = Number(RDV.totalPrix || 0).toFixed(2) + ' EUR';
    if (APP.rdvClientPrefill) {
        document.getElementById('pub-prenom').value = APP.rdvClientPrefill.prenom || '';
        document.getElementById('pub-nom').value = APP.rdvClientPrefill.nom || '';
        document.getElementById('pub-tel').value = APP.rdvClientPrefill.telephone || '';
        document.getElementById('pub-email').value = APP.rdvClientPrefill.email || '';
    }
}

function confirmerRDV() {
    var RDV = getRdvState();
    var prenom = (document.getElementById('pub-prenom').value || '').trim();
    var nom = (document.getElementById('pub-nom').value || '').trim();
    var tel = (document.getElementById('pub-tel').value || '').trim();
    var email = (document.getElementById('pub-email').value || '').trim();
    var comment = (document.getElementById('pub-comment').value || '').trim();
    if (!prenom || !nom || !tel) { alert('Prenom, nom et telephone obligatoires'); return; }
    var atelierSlug = getCurrentAtelierSlug();
    var btn = document.getElementById('btn-confirm');
    if (btn) { btn.innerHTML = '<span class="loading-spinner"></span> Confirmation...'; btn.disabled = true; }
    var v = RDV.vehicule || {};
    var body = {
        client: { nom: nom, prenom: prenom, telephone: tel, email: email || null },
        vehicule: { plaque: v.plaque || 'XX-000-XX', marque: v.marque || null, modele: v.modele || null, annee: v.annee || null, cylindree: v.cylindree || null, type_moto: RDV.motoType || v.type_moto || null, categorie_id: null },
        prestations: RDV.selected,
        date_heure: RDV.selectedDate + 'T' + RDV.selectedHeure + ':00',
        montant_estime: RDV.totalPrix || 0,
        commentaires: comment || null,
        pont_id: RDV.selectedPont ? RDV.selectedPont.id : null,
        atelier_slug: atelierSlug
    };
    apiPost('/api/rendez-vous/public', body).then(function(r) { return r.json(); }).then(function(data) {
        document.getElementById('pub-rdv-num').textContent = '#RDV-' + String(data.id || '').padStart(4, '0');
        var detailsHtml = '';
        detailsHtml += '<div class="sd-row"><span>Vehicule</span><span>' + escapeHtml(((v.marque || '') + ' ' + (v.modele || '')).trim()) + '</span></div>';
        detailsHtml += '<div class="sd-row"><span>Date</span><span>' + _rdvFormatDate(RDV.selectedDate) + ' a ' + escapeHtml(RDV.selectedHeure || '-') + '</span></div>';
        if (RDV.selectedPont) detailsHtml += '<div class="sd-row"><span>Pont</span><span>' + escapeHtml(RDV.selectedPont.nom || '-') + '</span></div>';
        if (RDV.selectedMeca) detailsHtml += '<div class="sd-row"><span>Mecanicien</span><span>' + escapeHtml(RDV.selectedMeca) + '</span></div>';
        document.getElementById('pub-success-details').innerHTML = detailsHtml;
        var steps = document.querySelectorAll('#rdvw-root .wizard-step');
        for (var i = 0; i < steps.length; i++) steps[i].classList.remove('active');
        document.getElementById('step-success').classList.add('active');
        for (var s = 1; s <= 4; s++) {
            var dot = document.getElementById('sd-' + s);
            var line = document.getElementById('sl-' + s);
            if (dot) { dot.className = 'step-dot done'; dot.textContent = '✓'; }
            if (line) line.className = 'step-line done';
        }
    }).catch(function(e) {
        if (btn) { btn.textContent = 'Confirmer le RDV + Generer OR'; btn.disabled = false; }
        alert('Erreur lors de la creation du RDV: ' + e.message);
    });
}

// ===== PLANNING =====
function loadPlanning() {
    populatePlanningAtelierSelect();
    var planningSlug = getPlanningAtelierSlug();
    var loadSeq = ++APP._planningLoadSeq;
    var today = new Date();
    today.setDate(today.getDate() + (APP.planningWeekOffset * 7));
    var monday = new Date(today);
    monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));

    var headerEl = document.getElementById('planning-header');
    var joursNoms = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    var todayStr = _rdvDateToStr(new Date());
    var headerHtml = '<div class="planning-day"></div>';

    for (var d = 0; d < 7; d++) {
        var jour = new Date(monday); jour.setDate(jour.getDate() + d);
        var jourStr = _rdvDateToStr(jour);
        var isToday = jourStr === todayStr;
        headerHtml += '<div class="planning-day' + (isToday ? ' today' : '') + '">' + joursNoms[d] + ' ' + jour.getDate() + (isToday ? '<br><span style="font-size:10px;color:var(--orange)">Aujourd\'hui</span>' : '') + '</div>';
    }
    headerEl.innerHTML = headerHtml;

    renderMecaFilters();

    var dateDebut = _rdvDateToStr(monday);
    var dimanche = new Date(monday); dimanche.setDate(dimanche.getDate() + 6);
    var dateFin = _rdvDateToStr(dimanche);

    var weekLabel = document.getElementById('planning-week-label');
    if (weekLabel) weekLabel.textContent = 'Semaine ' + getWeekNumber(monday);

    var planningUrl = '/api/planning/semaine?date_debut=' + dateDebut + '&date_fin=' + dateFin;
    if (hasPermission('rdv.select_atelier') && planningSlug) {
        planningUrl += '&atelier_slug=' + encodeURIComponent(planningSlug);
    }
    APP._horairesByDay = {};
    APP._horairesLoaded = false;
    Promise.all([
        apiGet(planningUrl).then(function(r) { return r.json(); }),
        apiGet('/api/config/horaires' + (planningSlug ? '?atelier_slug=' + encodeURIComponent(planningSlug) : '')).then(function(r) { return r.json(); }).catch(function() { return []; })
    ]).then(function(results) {
        if (loadSeq !== APP._planningLoadSeq) return;
        var data = results[0] || {};
        var horaires = results[1] || [];
        APP._horairesByDay = {};
        APP._horairesLoaded = Array.isArray(horaires) && horaires.length > 0;
        horaires.forEach(function(h) { APP._horairesByDay[h.jour_semaine] = h; });
        // API returns { jours: { "2026-03-30": [...], ... } } - flatten to array
        var rdvList = [];
        if (data && data.jours) {
            Object.keys(data.jours).forEach(function(dateKey) {
                var dayRdvs = data.jours[dateKey];
                if (Array.isArray(dayRdvs)) {
                    dayRdvs.forEach(function(rdv) {
                        if (!rdv.date_rdv) rdv.date_rdv = dateKey;
                        rdvList.push(rdv);
                    });
                }
            });
        } else if (Array.isArray(data)) {
            rdvList = data;
        }
        renderPlanningGrid(rdvList, monday);
        APP.planningRdvs = rdvList;
        APP.planningMonday = monday;
    }).catch(function() {
        if (loadSeq !== APP._planningLoadSeq) return;
        renderPlanningGrid([], monday);
    });

    if (APP._planningNowTimer) clearInterval(APP._planningNowTimer);
    APP._planningNowTimer = setInterval(function() {
        if (APP.currentSection === 'planning' && APP.planningMonday) {
            renderPlanningNowLine(document.getElementById('planning-grid'), APP.planningMonday);
        }
    }, 60000);
}

function isPlanningSlotOpen(dateStr, hour) {
    if (!APP._horairesLoaded) return true;
    var d = new Date(dateStr + 'T00:00:00');
    if (isNaN(d.getTime())) return true;
    var jsDay = d.getDay();
    var jour = jsDay === 0 ? 6 : jsDay - 1; // 0=lundi ... 6=dimanche
    var h = APP._horairesByDay[jour];
    if (!h || !h.is_ouvert) return false;
    if (!h.heure_ouverture || !h.heure_fermeture) return false;
    var mins = timeToMinutes(hour);
    if (mins < timeToMinutes(adminFmtTime(h.heure_ouverture)) || mins >= timeToMinutes(adminFmtTime(h.heure_fermeture))) return false;
    if (h.pause_debut && h.pause_fin) {
        var p1 = timeToMinutes(adminFmtTime(h.pause_debut));
        var p2 = timeToMinutes(adminFmtTime(h.pause_fin));
        if (mins >= p1 && mins < p2) return false;
    }
    return true;
}

function isPlanningSlotValidForDuration(dateStr, hour, durationMinutes) {
    if (!isPlanningSlotOpen(dateStr, hour)) return false;
    if (!APP._horairesLoaded) return true;
    var d = new Date(dateStr + 'T00:00:00');
    if (isNaN(d.getTime())) return true;
    var jsDay = d.getDay();
    var jour = jsDay === 0 ? 6 : jsDay - 1; // 0=lundi ... 6=dimanche
    var h = APP._horairesByDay[jour];
    if (!h || !h.is_ouvert || !h.heure_fermeture) return true;
    var start = timeToMinutes(hour);
    var closeAt = timeToMinutes(adminFmtTime(h.heure_fermeture));
    var duration = parseInt(durationMinutes || 0, 10);
    if (!duration || duration <= 0) duration = 60;
    // Pause midi autorisee (split), fermeture non.
    return (start + duration) <= closeAt;
}

function getPlanningHoraireForDate(dateStr) {
    if (!APP._horairesLoaded) return null;
    var d = new Date(dateStr + 'T00:00:00');
    if (isNaN(d.getTime())) return null;
    var jsDay = d.getDay();
    var jour = jsDay === 0 ? 6 : jsDay - 1; // 0=lundi ... 6=dimanche
    return APP._horairesByDay[jour] || null;
}

function splitRdvSegments(dateStr, startMin, durationMin) {
    var h = getPlanningHoraireForDate(dateStr);
    var totalEnd = startMin + durationMin;
    if (!h || !h.pause_debut || !h.pause_fin) {
        return [{ start: startMin, end: totalEnd, continuation: false }];
    }
    var pauseStart = timeToMinutes(adminFmtTime(h.pause_debut));
    var pauseEnd = timeToMinutes(adminFmtTime(h.pause_fin));
    if (pauseStart < 0 || pauseEnd < 0 || pauseEnd <= pauseStart) {
        return [{ start: startMin, end: totalEnd, continuation: false }];
    }
    // Split logique uniquement si le RDV commence avant la pause et la traverse.
    if (startMin < pauseStart && totalEnd > pauseStart) {
        var firstEnd = pauseStart;
        var remaining = totalEnd - pauseStart;
        return [
            { start: startMin, end: firstEnd, continuation: false },
            { start: pauseEnd, end: pauseEnd + remaining, continuation: true }
        ];
    }
    return [{ start: startMin, end: totalEnd, continuation: false }];
}

function buildPlanningBusyCells(rdvs) {
    var busy = {};
    (rdvs || []).forEach(function(rdv) {
        var day = rdv.date_rdv || '';
        var start = timeToMinutes(formatTime(rdv.heure_rdv || ''));
        if (!day || start < 0) return;
        var dur = getRdvDurationMinutes(rdv);
        var segments = splitRdvSegments(day, start, dur);
        segments.forEach(function(seg) {
            var step = APP._planningSlotMinutes || 15;
            var first = Math.floor(seg.start / step) * step;
            var last = Math.max(first, Math.ceil(seg.end / step) * step - step);
            for (var t = first; t <= last; t += step) {
                var key = day + '|' + minutesToTimeLabel(t);
                busy[key] = true;
            }
        });
    });
    return busy;
}

function isPlanningCellBusy(dateStr, hour) {
    return !!APP._planningBusyCells[dateStr + '|' + hour];
}

function getPlanningAtelierSlug() {
    if (APP.planningSelectedAtelierSlug) return APP.planningSelectedAtelierSlug;
    if (APP.currentUser && APP.currentUser.atelier_slug) return APP.currentUser.atelier_slug;
    if (APP.currentUser && Array.isArray(APP.currentUser.ateliers)) {
        var own = APP.currentUser.ateliers.find(function(a) { return a.atelier_id === APP.currentUser.atelier_id; });
        if (own && own.slug) return own.slug;
    }
    return 'default';
}

function populatePlanningAtelierSelect() {
    var select = document.getElementById('planning-atelier-select');
    if (!select) return;
    var canSelectAtelier = hasPermission('rdv.select_atelier');
    var selectWrap = select.parentElement;
    if (selectWrap) selectWrap.style.display = canSelectAtelier ? 'flex' : 'none';

    function render(items) {
        var ateliers = Array.isArray(items) ? items.filter(function(a) { return a && a.slug; }) : [];
        if (!ateliers.length) {
            var fallback = getPlanningAtelierSlug() || 'default';
            select.innerHTML = '<option value="' + escapeAttr(fallback) + '">' + escapeHtml(fallback) + '</option>';
            select.value = fallback;
            select.disabled = true;
            return;
        }
        var html = '';
        ateliers.forEach(function(a) {
            var aid = a.id || a.atelier_id || '';
            var label = a.nom || a.slug || ('Atelier #' + aid);
            html += '<option value="' + escapeAttr(a.slug) + '">' + escapeHtml(label) + '</option>';
        });
        select.innerHTML = html;
        if (!APP.planningSelectedAtelierSlug) {
            var own = ateliers.find(function(a) {
                var aid = a.id || a.atelier_id;
                return aid === (APP.currentUser ? APP.currentUser.atelier_id : null);
            });
            APP.planningSelectedAtelierSlug = (own && own.slug) ? own.slug : getPlanningAtelierSlug();
        }
        var exists = ateliers.some(function(a) { return a.slug === APP.planningSelectedAtelierSlug; });
        if (!exists) APP.planningSelectedAtelierSlug = ateliers[0].slug;
        if (!canSelectAtelier) {
            var own = ateliers.find(function(a) {
                var aid = a.id || a.atelier_id;
                return aid === (APP.currentUser ? APP.currentUser.atelier_id : null);
            });
            APP.planningSelectedAtelierSlug = (own && own.slug) ? own.slug : APP.planningSelectedAtelierSlug;
        }
        select.value = APP.planningSelectedAtelierSlug;
        select.disabled = !canSelectAtelier;
    }

    if (canSelectAtelier) {
        apiGet('/api/ateliers/public').then(function(r) { return r.json(); }).then(render).catch(function() {
            render(APP.currentUser && APP.currentUser.ateliers ? APP.currentUser.ateliers : []);
        });
    } else {
        render(APP.currentUser && APP.currentUser.ateliers ? APP.currentUser.ateliers : []);
    }
}

function onPlanningAtelierChange(slug) {
    var next = (slug || '').trim().toLowerCase();
    if (!next || next === APP.planningSelectedAtelierSlug) return;
    APP.planningSelectedAtelierSlug = next;
    APP.planningMecaFilters = [];
    loadPlanning();
}

function renderMecaFilters() {
    var container = document.getElementById('planning-meca-filters');
    var html = '';
    APP.mecaniciens.forEach(function(meca) {
        var color = meca.couleur || '#3b82f6';
        var isFiltered = APP.planningMecaFilters.length > 0 && APP.planningMecaFilters.indexOf(meca.id) === -1;
        var opacity = isFiltered ? '0.35' : '1';
        html += '<div data-meca-id="' + meca.id + '" onclick="toggleMecaFilter(' + meca.id + ')" style="display:flex;align-items:center;gap:6px;background:' + hexToRgba(color, 0.12) + ';border:1px solid ' + hexToRgba(color, 0.3) + ';border-radius:20px;padding:4px 12px;cursor:pointer;opacity:' + opacity + ';transition:opacity .2s"><div style="width:8px;height:8px;border-radius:50%;background:' + color + '"></div><span style="font-size:12px;font-weight:600;color:' + color + '">' + escapeHtml(meca.prenom.charAt(0)) + '. ' + escapeHtml(meca.nom) + '</span></div>';
    });
    container.innerHTML = html;
}

function toggleMecaFilter(mecaId) {
    var idx = APP.planningMecaFilters.indexOf(mecaId);
    if (APP.planningMecaFilters.length === 0) {
        // Premier clic: filtrer pour montrer SEULEMENT ce mecano
        APP.planningMecaFilters = [mecaId];
    } else if (idx !== -1 && APP.planningMecaFilters.length === 1) {
        // Clic sur le seul filtre actif: reset (montrer tous)
        APP.planningMecaFilters = [];
    } else if (idx !== -1) {
        // Retirer ce mecano du filtre
        APP.planningMecaFilters.splice(idx, 1);
    } else {
        // Ajouter ce mecano au filtre
        APP.planningMecaFilters.push(mecaId);
    }
    renderMecaFilters();
    renderPlanningGrid(APP.planningRdvs, APP.planningMonday);
}

function renderPlanningGrid(rdvs, monday) {
    var grid = document.getElementById('planning-grid');
    var planningSection = document.getElementById('s-planning');
    var summaryEl = document.getElementById('planning-conflict-summary');
    if (!summaryEl && planningSection) {
        summaryEl = document.createElement('div');
        summaryEl.id = 'planning-conflict-summary';
        summaryEl.className = 'planning-conflict-summary';
        var card = planningSection.querySelector('.card');
        if (card && card.parentNode === planningSection) {
            planningSection.insertBefore(summaryEl, card);
        }
    }
    var bounds = getPlanningBounds();
    var hours = buildPlanningSlots(bounds.start, bounds.end, APP._planningSlotMinutes);
    var todayStr = _rdvDateToStr(new Date());
    var rdvList = Array.isArray(rdvs) ? rdvs : [];

    // Appliquer filtre mecaniciens
    var filteredRdvs = rdvList;
    if (APP.planningMecaFilters.length > 0) {
        filteredRdvs = rdvList.filter(function(r) {
            return APP.planningMecaFilters.indexOf(r.mecanicien_id) !== -1 || !r.mecanicien_id;
        });
    }

    var byDay = {};
    filteredRdvs.forEach(function(rdv) {
        var dayKey = rdv.date_rdv || '';
        if (!dayKey) return;
        if (!byDay[dayKey]) byDay[dayKey] = [];
        byDay[dayKey].push(rdv);
    });

    APP._planningBusyCells = buildPlanningBusyCells(filteredRdvs);
    var conflictPairsById = {};
    var conflictCellKeys = {};
    var visualOverlapGroupsById = {};
    var conflictCount = 0;

    Object.keys(byDay).forEach(function(dayKey) {
        var dayList = byDay[dayKey].slice().sort(function(a, b) {
            return (formatTime(a.heure_rdv || '')).localeCompare(formatTime(b.heure_rdv || ''));
        });
        buildVisualOverlapGroups(dayList, visualOverlapGroupsById);
        for (var i = 0; i < dayList.length; i++) {
            for (var j = i + 1; j < dayList.length; j++) {
                var a = dayList[i];
                var b = dayList[j];
                if (a.id === b.id) continue;
                if (a.pont_id && b.pont_id && a.pont_id !== b.pont_id) continue;
                if (a.mecanicien_id && b.mecanicien_id && a.mecanicien_id !== b.mecanicien_id) continue;
                var aStart = timeToMinutes(formatTime(a.heure_rdv || ''));
                var bStart = timeToMinutes(formatTime(b.heure_rdv || ''));
                if (aStart < 0 || bStart < 0) continue;
                var aDur = getRdvDurationMinutes(a);
                var bDur = getRdvDurationMinutes(b);
                var aEnd = aStart + aDur;
                var bEnd = bStart + bDur;
                if (aStart < bEnd && bStart < aEnd) {
                    conflictPairsById[a.id] = true;
                    conflictPairsById[b.id] = true;
                    conflictCount++;
                    markConflictCells(conflictCellKeys, dayKey, aStart, aEnd);
                    markConflictCells(conflictCellKeys, dayKey, bStart, bEnd);
                }
            }
        }
    });

    var html = '';

    hours.forEach(function(h) {
        html += '<div class="time-label">' + (h.slice(3) === '00' ? h : '') + '</div>';
        for (var d = 0; d < 7; d++) {
            var jour = new Date(monday); jour.setDate(jour.getDate() + d);
            var jourStr = _rdvDateToStr(jour);
            var isToday = jourStr === todayStr;
            var cellKey = jourStr + '|' + h;
            var isConflictCell = !!conflictCellKeys[cellKey];
            var isClosedCell = !isPlanningSlotOpen(jourStr, h);
            var isBusyCell = isPlanningCellBusy(jourStr, h);
            html += '<div class="planning-cell' + (isConflictCell ? ' has-conflict' : '') + (isClosedCell ? ' is-closed' : '') + (isBusyCell ? ' is-busy' : '') + '" data-date="' + jourStr + '" data-hour="' + h + '"'
                + ' onclick="onPlanningCellClick(event,\'' + jourStr + '\',\'' + h + '\')"'
                + ' ondragover="onCellDragOver(event)"'
                + ' ondragleave="onCellDragLeave(event)"'
                + ' ondrop="onCellDrop(event,\'' + jourStr + '\',\'' + h + '\')"'
                + ' style="' + (isToday ? 'background:rgba(232,72,10,.03)' : '') + '">';
            filteredRdvs.forEach(function(rdv) {
                if ((rdv.date_rdv || '') !== jourStr) return;
                var rdvTime = formatTime(rdv.heure_rdv || '');
                if (!rdvTime) return;
                var rdvStart = timeToMinutes(rdvTime);
                var segments = splitRdvSegments(jourStr, rdvStart, getRdvDurationMinutes(rdv));
                segments.forEach(function(seg) {
                    var slotMin = timeToMinutes(h);
                    var segStartSlot = Math.floor(seg.start / APP._planningSlotMinutes) * APP._planningSlotMinutes;
                    if (segStartSlot !== slotMin) return;
                    var meca = rdv.mecanicien || APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
                    var color = (meca && meca.couleur) ? meca.couleur : '#3b82f6';
                    var segDuration = Math.max(1, seg.end - seg.start);
                    var pxPerMin = APP._planningSlotPx / APP._planningSlotMinutes;
                    var height = Math.max(10, Math.ceil(segDuration * pxPerMin) - 2);
                    var rdvMin = seg.start % 60;
                    var topOffset = Math.round((seg.start - segStartSlot) * pxPerMin);
                    var timeLabel = (seg.continuation ? '↳ ' : '') + (Math.floor(seg.start / 60) < 10 ? '0' + Math.floor(seg.start / 60) : Math.floor(seg.start / 60)) + ':' + (rdvMin < 10 ? '0' + rdvMin : rdvMin);
                    var c = rdv.client || {};
                    var clientName = c.prenom ? (escapeHtml(c.prenom).charAt(0) + '. ' + (escapeHtml(c.nom) || '')) : (escapeHtml(c.nom) || '');
                    var overlapMeta = visualOverlapGroupsById[rdv.id] || { index: 0, count: 1 };
                    var overlapIndex = overlapMeta.index;
                    var overlapCount = overlapMeta.count;
                    var widthPct = overlapCount > 1 ? (100 / overlapCount) : 100;
                    var leftPct = overlapIndex * widthPct;
                    var isConflict = !!conflictPairsById[rdv.id];
                    html += '<div class="rdv-block' + (isConflict ? ' conflict' : '') + '" draggable="true" data-rdv-id="' + rdv.id + '"'
                        + ' onclick="event.stopPropagation();onPlanningRdvClick(' + rdv.id + ')"'
                        + ' ondragstart="onRdvDragStart(event,' + rdv.id + ')"'
                        + ' ondragend="onRdvDragEnd(event)"'
                        + ' title="' + escapeHtml(timeLabel + ' - ' + (escapeHtml(rdv.type_intervention) || '') + ' - ' + clientName) + '"'
                        + ' style="background:' + color + ';' + (seg.continuation ? 'opacity:.82;border:1px dashed rgba(255,255,255,.75);' : '') + 'height:' + height + 'px;color:white;top:' + topOffset + 'px;left:calc(' + leftPct + '% + 2px);width:calc(' + widthPct + '% - 4px);right:auto;z-index:' + (2 + overlapIndex) + '">'
                        + '<div style="font-size:10px;font-weight:600;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'
                        + timeLabel + ' ' + escapeHtml((rdv.type_intervention || '').substring(0, 14)) + '</div>';
                    if (height >= 18) {
                        html += '<div style="font-size:9px;opacity:.8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escapeHtml(clientName) + '</div>';
                    }
                    if (isConflict) {
                        html += '<div style="font-size:9px;font-weight:700;color:#fecaca;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Conflit</div>';
                    }
                    html += '</div>';
                });
            });
            html += '</div>';
        }
    });
    grid.innerHTML = html;
    renderPlanningNowLine(grid, monday);
    if (summaryEl) {
        if (conflictCount > 0) {
            summaryEl.innerHTML = '<span class="planning-conflict-dot"></span><strong>' + conflictCount + ' conflit(s) detecte(s)</strong> : chevauchements de RDV sur meme ressource (pont/mecanicien).';
        } else {
            summaryEl.innerHTML = '<span class="planning-conflict-dot" style="background:var(--green)"></span><span style="color:#86efac">Aucun conflit detecte sur la semaine affichee.</span>';
        }
    }
}

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

function getWeekNumber(d) {
    d = new Date(d); d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() + 3 - (d.getDay() + 6) % 7);
    var w1 = new Date(d.getFullYear(), 0, 4);
    return 1 + Math.round(((d - w1) / 86400000 - 3 + (w1.getDay() + 6) % 7) / 7);
}

function planningPrev() { APP.planningWeekOffset--; loadPlanning(); }
function planningNext() { APP.planningWeekOffset++; loadPlanning(); }

// ===== DRAG & DROP PLANNING =====
function onRdvDragStart(event, rdvId) {
    event.dataTransfer.setData('text/plain', String(rdvId));
    event.dataTransfer.effectAllowed = 'move';
    event.target.style.opacity = '0.4';
    APP._draggedEl = event.target;
}

function onRdvDragEnd(event) {
    if (APP._draggedEl) {
        APP._draggedEl.style.opacity = '';
        APP._draggedEl = null;
    }
    var cells = document.querySelectorAll('.planning-cell.drag-over');
    for (var i = 0; i < cells.length; i++) { cells[i].classList.remove('drag-over'); }
}

function onCellDragOver(event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    event.currentTarget.classList.add('drag-over');
}

function onCellDragLeave(event) {
    event.currentTarget.classList.remove('drag-over');
}

function onCellDrop(event, dateStr, hour) {
    event.preventDefault();
    event.currentTarget.classList.remove('drag-over');
    var rdvId = parseInt(event.dataTransfer.getData('text/plain'));
    if (!rdvId) return;
    if (!isPlanningSlotOpen(dateStr, hour)) {
        showAlert('Creneau ferme: deplacement impossible', 'warning');
        return;
    }
    if (isPlanningCellBusy(dateStr, hour)) {
        showAlert('Creneau deja occupe', 'warning');
        return;
    }
    var rdv = APP.planningRdvs.find(function(r) { return r.id === rdvId; });
    if (rdv && !isPlanningSlotValidForDuration(dateStr, hour, getRdvDurationMinutes(rdv))) {
        showAlert('Duree RDV depasse l\'heure de fermeture', 'warning');
        return;
    }
    var oldInfo = rdv ? (rdv.date_rdv + ' ' + formatTime(rdv.heure_rdv)) : '';
    openConfirmDialog('Deplacer le RDV #' + rdvId + ' de ' + oldInfo + ' vers ' + dateStr + ' ' + hour + ' ?', function() {
        apiPut('/api/rendez-vous/' + rdvId, { date_rdv: dateStr, heure_rdv: hour }).then(function() {
            loadPlanning();
            showNotificationToast('RDV #' + rdvId + ' deplace');
        }).catch(function(e) {
            if ((e.message || '').indexOf('Conflit planning') !== -1) {
                showAlert(e.message, 'warning');
                return;
            }
            alert('Erreur deplacement: ' + e.message);
        });
    });
}

// ===== CLIC SUR RDV PLANNING =====
function onPlanningRdvClick(rdvId) {
    var rdv = APP.planningRdvs.find(function(r) { return r.id === rdvId; });
    if (!rdv) return;
    var c = rdv.client || {};
    var v = rdv.vehicule || {};
    var meca = rdv.mecanicien;
    var pont = rdv.pont;
    var duree = rdv.temps_estime ? Math.round(rdv.temps_estime / 60 * 10) / 10 + 'h' : '-';

    var html = '<div style="display:flex;gap:16px;margin-bottom:16px">'
        + '<div style="flex:1">'
        + '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px">Client</div>'
        + '<div style="font-size:15px;font-weight:600;color:#eee">' + escapeHtml(c.prenom || '') + ' ' + escapeHtml(c.nom || '') + '</div>'
        + '<div style="font-size:12px;color:#888">' + escapeHtml(c.telephone || '') + '</div>'
        + '</div>'
        + '<div style="flex:1">'
        + '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px">Vehicule</div>'
        + '<div style="font-size:15px;font-weight:600;color:#eee">' + escapeHtml(v.marque || '') + ' ' + escapeHtml(v.modele || '') + '</div>'
        + '<div style="font-size:12px;color:#888">' + escapeHtml(v.plaque || '') + '</div>'
        + '</div></div>';

    html += '<div style="display:flex;gap:16px;margin-bottom:16px">'
        + '<div style="flex:1">'
        + '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px">Intervention</div>'
        + '<div style="font-size:14px;color:#eee">' + escapeHtml(rdv.type_intervention || '-') + '</div>'
        + '<div style="font-size:12px;color:#888">Duree: ' + duree + '</div>'
        + '</div>'
        + '<div style="flex:1">'
        + '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px">Statut</div>'
        + '<div style="margin-top:4px">' + statusBadge(rdv.statut) + '</div>'
        + '</div></div>';

    html += '<div style="display:flex;gap:16px;margin-bottom:16px">'
        + '<div style="flex:1">'
        + '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px">Mecanicien</div>'
        + '<div style="font-size:14px;color:#eee">' + (meca ? escapeHtml(meca.prenom) + ' ' + escapeHtml(meca.nom) : 'Non assigne') + '</div>'
        + '</div>'
        + '<div style="flex:1">'
        + '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px">Pont</div>'
        + '<div style="font-size:14px;color:#eee">' + (pont ? escapeHtml(pont.nom) : 'Non assigne') + '</div>'
        + '</div></div>';

    html += '<div style="margin-top:12px">' + actionButtons(rdv, false) + '</div>';

    showModal('RDV #' + rdv.id + ' - ' + formatTime(rdv.heure_rdv) + ' ' + (rdv.date_rdv || ''), html, '550px');
}

// ===== CLIC CELLULE VIDE - QUICK CREATE =====
function onPlanningCellClick(event, dateStr, hour) {
    if (event.target.closest && event.target.closest('.rdv-block')) return;
    if (!isPlanningSlotOpen(dateStr, hour)) {
        showAlert('Atelier ferme sur ce creneau', 'warning');
        return;
    }
    if (isPlanningCellBusy(dateStr, hour)) {
        showAlert('Creneau deja occupe', 'warning');
        return;
    }
    ouvrirQuickCreateRdv(dateStr, hour);
}

function ouvrirQuickCreateRdv(dateStr, hour) {
    var html = '';

    html += '<div style="display:flex;gap:12px;margin-bottom:16px">'
        + '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Date</label>'
        + '<input type="date" id="qc-date" class="form-input" value="' + dateStr + '"></div>'
        + '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Heure</label>'
        + '<input type="time" id="qc-hour" class="form-input" value="' + hour + '"></div></div>';

    html += '<div class="form-group"><label class="form-label" style="color:#ccc">Client (recherche)</label>'
        + '<input class="form-input" id="qc-client-search" placeholder="Nom, prenom ou telephone..." oninput="searchClientQuickCreate(this.value)">'
        + '<div id="qc-client-results" style="max-height:120px;overflow-y:auto;margin-top:4px"></div></div>';

    html += '<div style="display:flex;gap:12px">'
        + '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Nom</label>'
        + '<input class="form-input" id="qc-client-nom" placeholder="Nom"></div>'
        + '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Prenom</label>'
        + '<input class="form-input" id="qc-client-prenom" placeholder="Prenom"></div></div>';

    html += '<div class="form-group"><label class="form-label" style="color:#ccc">Telephone</label>'
        + '<input class="form-input" id="qc-client-tel" placeholder="06 XX XX XX XX"></div>';

    html += '<div class="form-group"><label class="form-label" style="color:#ccc">Plaque immatriculation</label>'
        + '<input class="form-input" id="qc-plaque" placeholder="AB-123-CD" oninput="searchVehiculeQuickCreate(this.value)"></div>';
    html += '<div id="qc-vehicule-info" style="display:none;background:#1e1e1e;border:1px solid #444;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:12px;color:#aaa"></div>';

    html += '<div class="form-group"><label class="form-label" style="color:#ccc">Type d\'intervention</label>'
        + '<select class="form-select" id="qc-intervention"><option value="">Selectionner...</option>';
    APP.interventionTypes.forEach(function(it) {
        html += '<option value="' + escapeHtml(it.nom) + '">' + it.nom + '</option>';
    });
    html += '</select></div>';

    html += '<button class="btn btn-primary" style="width:100%;margin-top:12px" onclick="submitQuickCreateRdv()">Creer le RDV</button>';

    showModal('Nouveau RDV - ' + dateStr + ' a ' + hour, html, '500px');
}

var _qcSearchTimer = null;
function searchClientQuickCreate(val) {
    if (_qcSearchTimer) clearTimeout(_qcSearchTimer);
    if (val.length < 2) { document.getElementById('qc-client-results').innerHTML = ''; return; }
    _qcSearchTimer = setTimeout(function() {
        apiGet('/api/clients?search=' + encodeURIComponent(val)).then(function(r) { return r.json(); }).then(function(clients) {
            var container = document.getElementById('qc-client-results');
            if (!clients || !clients.length) {
                container.innerHTML = '<div style="font-size:11px;color:#666;padding:4px">Nouveau client - saisir manuellement</div>';
                return;
            }
            var h = '';
            clients.slice(0, 5).forEach(function(c) {
                h += '<div style="padding:6px 8px;cursor:pointer;border-bottom:1px solid #333;font-size:12px;color:#ccc" '
                    + 'onmouseover="this.style.background=\'#333\'" onmouseout="this.style.background=\'\'" '
                    + 'onclick="selectClientQuickCreate(\'' + escapeAttr(c.nom || '') + '\',\'' + escapeAttr(c.prenom || '') + '\',\'' + escapeAttr(c.telephone || '') + '\')">'
                    + '<b>' + escapeHtml(c.prenom || '') + ' ' + escapeHtml(c.nom || '') + '</b> - ' + escapeHtml(c.telephone || '') + '</div>';
            });
            container.innerHTML = h;
        }).catch(function() {});
    }, 300);
}

function selectClientQuickCreate(nom, prenom, tel) {
    document.getElementById('qc-client-nom').value = nom;
    document.getElementById('qc-client-prenom').value = prenom;
    document.getElementById('qc-client-tel').value = tel;
    document.getElementById('qc-client-results').innerHTML = '<div style="font-size:11px;color:var(--green);padding:4px">Client selectionne: ' + escapeHtml(prenom) + ' ' + escapeHtml(nom) + '</div>';
}

function searchVehiculeQuickCreate(val) {
    var infoEl = document.getElementById('qc-vehicule-info');
    if (val.length < 3) { infoEl.style.display = 'none'; return; }
    var plaque = val.replace(/[\s-]/g, '').toUpperCase();
    apiGet('/api/vehicule/' + encodeURIComponent(plaque)).then(function(r) { return r.json(); }).then(function(data) {
        if (data && !data.not_found && !data.detail) {
            infoEl.style.display = 'block';
            infoEl.innerHTML = 'Vehicule trouve: <b style="color:#eee">' + escapeHtml(data.marque || '') + ' ' + escapeHtml(data.modele || '') + '</b> ' + (escapeHtml(data.annee) ? '(' + data.annee + ')' : '');
            if (data.client) {
                document.getElementById('qc-client-nom').value = data.client.nom || '';
                document.getElementById('qc-client-prenom').value = data.client.prenom || '';
                document.getElementById('qc-client-tel').value = data.client.telephone || '';
            }
        } else { infoEl.style.display = 'none'; }
    }).catch(function() { infoEl.style.display = 'none'; });
}

function submitQuickCreateRdv() {
    var dateVal = document.getElementById('qc-date').value;
    var hourVal = document.getElementById('qc-hour').value;
    var nom = document.getElementById('qc-client-nom').value;
    var prenom = document.getElementById('qc-client-prenom').value;
    var tel = document.getElementById('qc-client-tel').value;
    var plaque = document.getElementById('qc-plaque').value;
    var intervention = document.getElementById('qc-intervention').value;

    if (!dateVal || !hourVal || !nom || !intervention) {
        alert('Veuillez remplir: date, heure, nom client et type d\'intervention');
        return;
    }
    if (!isPlanningSlotOpen(dateVal, hourVal)) {
        showAlert('Creneau ferme: creation RDV impossible', 'warning');
        return;
    }
    if (isPlanningCellBusy(dateVal, hourVal)) {
        showAlert('Creneau deja occupe', 'warning');
        return;
    }
    var selectedIntervention = null;
    for (var i = 0; i < APP.interventionTypes.length; i++) {
        if (APP.interventionTypes[i].nom === intervention) { selectedIntervention = APP.interventionTypes[i]; break; }
    }
    var duration = selectedIntervention ? (selectedIntervention.temps_estime_minutes || selectedIntervention.temps_estime || 60) : 60;
    if (!isPlanningSlotValidForDuration(dateVal, hourVal, duration)) {
        showAlert('Duree RDV depasse l\'heure de fermeture', 'warning');
        return;
    }

    apiPost('/api/rendez-vous', {
        client: { nom: nom, prenom: prenom || '', telephone: tel || '0000000000' },
        vehicule: { plaque: plaque || 'XX-000-XX' },
        date_rdv: dateVal,
        heure_rdv: hourVal + ':00',
        type_intervention: intervention
    }).then(function(r) { return r.json(); }).then(function() {
        closeModal();
        loadPlanning();
    }).catch(function(e) { alert('Erreur creation RDV: ' + e.message); });
}

// ===== PONTS & MECANICIENS =====
function loadPontsMecas() {
    loadBaseData().then(function() {
        Promise.all([
            apiGet('/api/ponts/status').then(function(r) { return r.json(); }).catch(function() { return []; }),
            apiGet('/api/rendez-vous').then(function(r) { return r.json(); }).catch(function() { return []; }),
            apiGet('/api/absences').then(function(r) { return r.json(); }).catch(function() { return []; })
        ]).then(function(results) {
            var pontsStatus = results[0];
            var rdvs = results[1];
            var absences = results[2];
            if (Array.isArray(pontsStatus) && pontsStatus.length) APP.ponts = pontsStatus;
            APP.rdvs = Array.isArray(rdvs) ? rdvs : [];
            APP._absences = Array.isArray(absences) ? absences : [];
            renderPontsManagerKpis(APP.ponts, APP.rdvs, APP._absences);
            renderPontsTab();
            renderMecasTab();
            renderTempsTab();
            renderAbsencesTable(APP._absences);
        });
    });
}

function renderPontsTab() {
    var container = document.getElementById('ponts-grid');
    var now = new Date();
    var today = now.toISOString().split('T')[0];
    var rdvsToday = (APP.rdvs || []).filter(function(r) { return (r.date_rdv || '') === today; });
    var html = '';
    APP.ponts.forEach(function(pont) {
        var pontActif = isActive(pont);
        var actif = pontActif ? 'green' : 'amber';
        var meca = pont.mecanicien_id ? APP.mecaniciens.find(function(m) { return m.id === pont.mecanicien_id; }) : null;
        var pontRdvs = rdvsToday.filter(function(r) { return r.pont_id === pont.id; });
        var enCours = pontRdvs.find(function(r) { return r.statut === 'en_cours' || r.statut === 'reception' || r.statut === 'confirme'; });
        var prochain = pontRdvs.slice().sort(function(a, b) { return (a.heure_rdv || '').localeCompare(b.heure_rdv || ''); })[0];
        var charge = pontRdvs.reduce(function(sum, r) { return sum + getRdvDurationMinutes(r); }, 0);
        var chargePct = Math.min(100, Math.round((charge / 600) * 100));
        var statusBadge = enCours ? '<span class="badge orange">Occupe</span>' : (pontActif ? '<span class="badge green">Libre</span>' : '<span class="badge amber">Inactif</span>');
        var chargeLabel = (Math.floor(charge / 60) > 0 ? (Math.floor(charge / 60) + 'h') : '') + ((charge % 60) > 0 ? ((Math.floor(charge / 60) > 0 ? ' ' : '') + (charge % 60) + 'min') : (charge > 0 ? '' : '0min'));
        html += '<div class="meca-card" style="border-color:rgba(' + (pontActif ? '34,197,94' : '245,158,11') + ',.3)"><div class="meca-header"><div class="meca-av" style="background:rgba(' + (pontActif ? '34,197,94,.15' : '245,158,11,.15') + ');color:var(--' + actif + ')">P' + pont.id + '</div><div class="meca-info"><div class="meca-name">' + (escapeHtml(pont.nom) || 'Pont ' + pont.id) + '</div><div class="meca-role">' + (escapeHtml(pont.type_pont) || 'moto') + ' | ' + (pont.capacite_kg || 0) + 'kg</div></div>' + statusBadge + '</div>' +
            (meca ? '<div style="font-size:12px;color:#888;margin-bottom:6px">Technicien assigne: <b style="color:#ccc">' + escapeHtml(meca.prenom) + ' ' + escapeHtml(meca.nom) + '</b></div>' : '<div style="font-size:12px;color:#666;margin-bottom:6px">Aucun technicien assigne</div>') +
            '<div style="font-size:12px;color:#9ca3af;margin-bottom:3px">Rendez-vous aujourd\'hui: <b style="color:#e5e7eb">' + pontRdvs.length + '</b></div>' +
            '<div style="font-size:12px;color:#9ca3af;margin-bottom:3px">Temps planifie: <b style="color:#e5e7eb">' + chargeLabel + '</b></div>' +
            (prochain ? '<div style="font-size:11px;color:#888;margin-bottom:3px">Prochain passage: ' + formatTime(prochain.heure_rdv) + ' - ' + escapeHtml(prochain.type_intervention || '-') + '</div>' : '<div style="font-size:11px;color:#666;margin-bottom:3px">Aucun rendez-vous planifie</div>') +
            '<div class="stat-bar" style="margin:8px 0 10px"><div class="stat-bar-fill" style="width:' + chargePct + '%;background:' + (chargePct > 80 ? 'var(--amber)' : 'var(--teal)') + '"></div></div>' +
            '<button class="btn btn-ghost" style="font-size:11px;padding:3px 10px;color:var(--purple)" onclick="ouvrirAttribuerPont(' + pont.id + ')">' + (meca ? 'Modifier technicien' : 'Affecter technicien') + '</button></div>';
    });
    container.innerHTML = html || '<div style="color:#666;padding:20px">Aucun pont</div>';
    document.getElementById('ponts-count').textContent = APP.ponts.length;
}

function renderMecasTab() {
    var container = document.getElementById('mecas-grid');
    var now = new Date();
    var today = now.toISOString().split('T')[0];
    var rdvsToday = (APP.rdvs || []).filter(function(r) { return (r.date_rdv || '') === today; });
    var absences = APP._absences || [];
    var html = '';
    APP.mecaniciens.forEach(function(meca) {
        var color = meca.couleur || '#3b82f6';
        var initials = getMecaInitials(meca);
        var specsRaw = meca.specialites || '';
        var specs = specsRaw;
        try { var arr = JSON.parse(specsRaw); if (Array.isArray(arr)) specs = arr.join(', '); } catch(e) {}
        var mecaRdvs = rdvsToday.filter(function(r) { return r.mecanicien_id === meca.id; });
        var enCours = mecaRdvs.find(function(r) { return r.statut === 'en_cours'; });
        var hasAbsence = absences.some(function(a) { return a.mecanicien_id === meca.id && a.date_debut <= today && a.date_fin >= today; });
        var totalMin = mecaRdvs.reduce(function(sum, r) { return sum + getRdvDurationMinutes(r); }, 0);
        var statusPart = hasAbsence ? '<span class="badge amber">Absent</span>' : (isActive(meca) ? (enCours ? '<span class="badge orange">En intervention</span>' : '<span class="badge green">Disponible</span>') : '<span class="badge amber">Inactif</span>');
        html += '<div class="meca-card"><div class="meca-header"><div class="meca-av" style="background:' + hexToRgba(color, 0.2) + ';color:' + color + '">' + initials + '</div><div class="meca-info"><div class="meca-name">' + escapeHtml(meca.prenom) + ' ' + escapeHtml(meca.nom) + '</div><div class="meca-role">' + specs + '</div></div>' + statusPart + '</div>' +
            (specs ? '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">' + specs.split(',').map(function(s) { return '<span class="badge" style="background:' + hexToRgba(color, 0.12) + ';color:' + color + '">' + s.trim() + '</span>'; }).join('') + '</div>' : '') +
            '<div style="font-size:12px;color:#9ca3af">Rendez-vous aujourd\'hui: <b style="color:#e5e7eb">' + mecaRdvs.length + '</b></div>' +
            '<div style="font-size:12px;color:#9ca3af">Temps planifie: <b style="color:#e5e7eb">' + totalMin + ' min</b></div>' +
            (enCours ? '<div style="font-size:11px;color:#fcd34d;margin-top:3px">Intervention en cours: ' + formatTime(enCours.heure_rdv) + ' - ' + escapeHtml(enCours.type_intervention || '-') + '</div>' : '<div style="font-size:11px;color:#777;margin-top:3px">Aucune intervention en cours</div>') +
            '</div>';
    });
    container.innerHTML = html || '<div style="color:#666;padding:20px">Aucun mecanicien</div>';
    document.getElementById('mecas-count').textContent = APP.mecaniciens.length;
}

function renderPontsManagerKpis(ponts, rdvs, absences) {
    var container = document.getElementById('ponts-manager-kpis');
    if (!container) return;
    var now = new Date();
    var today = now.toISOString().split('T')[0];
    var rdvsToday = (rdvs || []).filter(function(r) { return (r.date_rdv || '') === today; });
    var pontsActifs = (ponts || []).filter(function(p) { return isActive(p); }).length;
    var pontsOccupes = (ponts || []).filter(function(p) { return p.status === 'occupe'; }).length;
    var mecasActifs = (APP.mecaniciens || []).filter(function(m) { return isActive(m); }).length;
    var absToday = (absences || []).filter(function(a) { return a.date_debut <= today && a.date_fin >= today; }).length;
    var conflicts = countManagerConflicts(rdvsToday);
    var chargeTotal = rdvsToday.reduce(function(sum, r) { return sum + getRdvDurationMinutes(r); }, 0);
    container.innerHTML =
        '<div class="manager-kpi"><div class="manager-kpi-label">Occupation ponts</div><div class="manager-kpi-value">' + pontsOccupes + '/' + pontsActifs + '</div><div class="manager-kpi-sub">' + (pontsActifs ? Math.round((pontsOccupes / pontsActifs) * 100) : 0) + '% en charge</div></div>' +
        '<div class="manager-kpi"><div class="manager-kpi-label">Rendez-vous du jour</div><div class="manager-kpi-value">' + rdvsToday.length + '</div><div class="manager-kpi-sub">' + chargeTotal + ' min au total</div></div>' +
        '<div class="manager-kpi"><div class="manager-kpi-label">Techniciens actifs</div><div class="manager-kpi-value">' + mecasActifs + '</div><div class="manager-kpi-sub">' + absToday + ' absent(s) aujourd\'hui</div></div>' +
        '<div class="manager-kpi"><div class="manager-kpi-label">Conflits a traiter</div><div class="manager-kpi-value" style="color:' + (conflicts > 0 ? '#fca5a5' : '#86efac') + '">' + conflicts + '</div><div class="manager-kpi-sub">' + (conflicts > 0 ? 'Attention requise' : 'Aucun conflit') + '</div></div>';
}

function countManagerConflicts(rdvs) {
    var list = Array.isArray(rdvs) ? rdvs : [];
    var count = 0;
    for (var i = 0; i < list.length; i++) {
        for (var j = i + 1; j < list.length; j++) {
            var a = list[i];
            var b = list[j];
            var samePont = !!(a.pont_id && b.pont_id && a.pont_id === b.pont_id);
            var sameMeca = !!(a.mecanicien_id && b.mecanicien_id && a.mecanicien_id === b.mecanicien_id);
            if (!samePont && !sameMeca) continue;
            var aStart = timeToMinutes(formatTime(a.heure_rdv || ''));
            var bStart = timeToMinutes(formatTime(b.heure_rdv || ''));
            if (aStart < 0 || bStart < 0) continue;
            var aEnd = aStart + getRdvDurationMinutes(a);
            var bEnd = bStart + getRdvDurationMinutes(b);
            if (aStart < bEnd && bStart < aEnd) count++;
        }
    }
    return count;
}

function renderTempsTab() {
    var container = document.getElementById('temps-table');
    if (!container) return;

    function formatMinutes(mins) {
        var total = parseInt(mins || 0, 10);
        var h = Math.floor(total / 60);
        var m = total % 60;
        if (!total) return '-';
        return (h > 0 ? h + 'h' : '') + (m > 0 ? m + 'min' : (h > 0 ? '00' : ''));
    }

    var html = '<thead><tr><th>Prestation</th><th>Type</th><th>Categorie moto</th><th>Temps</th><th>Prix TTC</th></tr></thead><tbody>';
    var prestations = (APP.prestationsConfig || []).filter(function(p) {
        return isActive(p);
    });

    prestations.forEach(function(p) {
        var grille = p.grille || {};
        var categories = Object.keys(grille);
        var typeTarif = p.is_forfait === 1 ? 'Forfait' : (p.type_tarif || '-');

        if (categories.length > 0) {
            categories.forEach(function(cat, idx) {
                var g = grille[cat] || {};
                html += '<tr>' +
                    '<td>' + (idx === 0 ? '<b>' + escapeHtml(p.nom) + '</b>' : '') + '</td>' +
                    '<td>' + (idx === 0 ? escapeHtml(typeTarif) : '') + '</td>' +
                    '<td>' + escapeHtml(cat) + '</td>' +
                    '<td>' + formatMinutes(g.temps_minutes || p.temps_estime_minutes) + '</td>' +
                    '<td>' + Number(g.prix_ttc || p.prix_base_ttc || 0).toFixed(2) + ' €</td>' +
                    '</tr>';
            });
        } else {
            html += '<tr>' +
                '<td><b>' + escapeHtml(p.nom) + '</b></td>' +
                '<td>' + escapeHtml(typeTarif) + '</td>' +
                '<td>Toutes</td>' +
                '<td>' + formatMinutes(p.temps_estime_minutes) + '</td>' +
                '<td>' + Number(p.prix_base_ttc || 0).toFixed(2) + ' €</td>' +
                '</tr>';
        }
    });

    if (!prestations.length) {
        html += '<tr><td colspan="5" style="text-align:center;color:#666;padding:16px">Aucune prestation configurée</td></tr>';
    }
    html += '</tbody>';
    container.innerHTML = html;
}

// ===== ORDRES DE REPARATION =====
function loadOrdresReparation() {
    return window.OrModule.loadOrdresReparation();
}

function renderOrdresReparation(rdvs) {
    return window.OrModule.renderOrdresReparation(rdvs);
}

function getEtapeIndex(statut) {
    return window.OrModule.getEtapeIndex(statut);
}

function showOrDetail(rdvId) {
    return window.OrModule.showOrDetail(rdvId);
}

function planifierRdvSuite(rdvId) {
    return window.OrModule.planifierRdvSuite(rdvId);
}

// ===== RECEPTION VEHICULE =====
var ETAT_VEHICULE_POINTS = window.ETAT_VEHICULE_POINTS || [];

function ouvrirReception(rdvId) {
    return window.OrModule.ouvrirReception(rdvId);
}

function initReceptionSignaturePad() {
    return window.OrModule.initReceptionSignaturePad();
}

function getCanvasCoords(canvas, event) {
    return window.OrModule.getCanvasCoords(canvas, event);
}

function clearReceptionSignature() {
    return window.OrModule.clearReceptionSignature();
}

function getReceptionSignatureBase64() {
    return window.OrModule.getReceptionSignatureBase64();
}

function validerReception(rdvId) {
    return window.OrModule.validerReception(rdvId);
}

// ===== TRAVAUX SUPPLEMENTAIRES =====
function ouvrirDemandeTravauxSupp(rdvId) {
    return window.OrModule.ouvrirDemandeTravauxSupp(rdvId);
}

function toggleTsPrestation(id, code, nom) {
    return window.OrModule.toggleTsPrestation(id, code, nom);
}

function updateTsSelectedCount() {
    return window.OrModule.updateTsSelectedCount();
}

function setTsUrgence(val) {
    return window.OrModule.setTsUrgence(val);
}

function envoyerDemandeTravauxSupp(rdvId) {
    return window.OrModule.envoyerDemandeTravauxSupp(rdvId);
}

// ===== POLLING TRAVAUX SUPP =====
function pollTravauxSupp() {
    return window.OrModule.pollTravauxSupp();
}

function playAlertSound() {
    return window.OrModule.playAlertSound();
}

function showTravauxSuppAlert(demande) {
    return window.OrModule.showTravauxSuppAlert(demande);
}

function traiterAlertTravaux(demandeId, statut, btn) {
    return window.OrModule.traiterAlertTravaux(demandeId, statut, btn);
}

// ===== SIGNATURE TRAVAUX SUPP =====
function ouvrirSignatureTravauxSupp(demandeId, notes, prixDevis, tempsDevis) {
    return window.OrModule.ouvrirSignatureTravauxSupp(demandeId, notes, prixDevis, tempsDevis);
}

function initTsSignaturePad() {
    return window.OrModule.initTsSignaturePad();
}

function clearTsSignature() {
    return window.OrModule.clearTsSignature();
}

function confirmerTravauxSuppAvecSignature(demandeId, prixDevis, tempsDevis, notes) {
    return window.OrModule.confirmerTravauxSuppAvecSignature(demandeId, prixDevis, tempsDevis, notes);
}

function showNotificationToast(message) {
    return window.OrModule.showNotificationToast(message);
}

// ===== APPROBATION TRAVAUX SUPP (dans section OR) =====
function renderTravauxSuppPanel() {
    return window.OrModule.renderTravauxSuppPanel();
}

function approuverTravauxSupp(demandeId) {
    return window.OrModule.approuverTravauxSupp(demandeId);
}

function confirmerApprouverTravauxSupp(demandeId) {
    return window.OrModule.confirmerApprouverTravauxSupp(demandeId);
}

function refuserTravauxSupp(demandeId) {
    return window.OrModule.refuserTravauxSupp(demandeId);
}

function confirmerRefuserTravauxSupp(demandeId) {
    return window.OrModule.confirmerRefuserTravauxSupp(demandeId);
}

// ===== SUIVI LIVE =====
function loadSuiviLive() {
    return window.SuiviModule.loadSuiviLive();
}

function renderSuiviLive(rdvs) {
    return window.SuiviModule.renderSuiviLive(rdvs);
}

function getRdvProgressInfo(rdv, now) {
    return window.SuiviModule.getRdvProgressInfo(rdv, now);
}

function getRdvDelayInfo(rdv, now) {
    return window.SuiviModule.getRdvDelayInfo(rdv, now);
}

// ===== CHECKUP / RAPPORT TECHNICIEN =====
var CHECKUP_POINTS = window.CHECKUP_POINTS || [];

function ouvrirCheckup(rdvId) {
    return window.MecanicienModule.ouvrirCheckup(rdvId);
}

function renderCheckupModal(rdvId, rapport) {
    return window.MecanicienModule.renderCheckupModal(rdvId, rapport);
}

function setCheckpoint(key, value, btn) {
    return window.MecanicienModule.setCheckpoint(key, value, btn);
}

function sauverCheckup(rdvId, statut) {
    return window.MecanicienModule.sauverCheckup(rdvId, statut);
}

// ===== ESPACE MECANICIEN =====

function loadEspaceMeca() {
    return window.MecanicienModule.loadEspaceMeca();
}

function renderEspaceMeca(meca, allRdvs) {
    return window.MecanicienModule.renderEspaceMeca(meca, allRdvs);
}

function renderMecaActivePanel(rdv) {
    return window.MecanicienModule.renderMecaActivePanel(rdv);
}

function renderMecaCard(rdv, type) {
    return window.MecanicienModule.renderMecaCard(rdv, type);
}

function toggleMecaCheckup() {
    return window.MecanicienModule.toggleMecaCheckup();
}

function setMecaCheck(key, value, btn) {
    return window.MecanicienModule.setMecaCheck(key, value, btn);
}

function startMecaLiveTimer(rdv) {
    return window.MecanicienModule.startMecaLiveTimer(rdv);
}

function cleanupMecaTimer() {








    return window.MecanicienModule.cleanupMecaTimer();
}

// ===== GESTION ABSENCES =====
function loadAbsences() {
    return window.AbsencesModule.loadAbsences();
}

function renderAbsencesTable(absences) {
    return window.AbsencesModule.renderAbsencesTable(absences);
}

function ouvrirModalAbsence() {
    return window.AbsencesModule.ouvrirModalAbsence();
}

function sauverAbsence() {
    return window.AbsencesModule.sauverAbsence();
}

function ouvrirModalEditAbsence(absenceId) {
    return window.AbsencesModule.ouvrirModalEditAbsence(absenceId);
}

function sauverEditAbsence(absenceId) {
    return window.AbsencesModule.sauverEditAbsence(absenceId);
}

function supprimerAbsence(id) {
    return window.AbsencesModule.supprimerAbsence(id);
}

// ===== GESTION CLIENTS =====
function rechercherClients(val) {
    return window.ClientsModule.rechercherClients(val);
}

function loadClients(search, page) {
    return window.ClientsModule.loadClients(search, page);
}

function loadClientsStats() {
    return window.ClientsModule.loadClientsStats();
}

function renderClientsPagination(currentPage, totalPages) {
    return window.ClientsModule.renderClientsPagination(currentPage, totalPages);
}

function ouvrirNouveauClient() {
    return window.ClientsModule.ouvrirNouveauClient();
}

function creerNouveauClient() {
    return window.ClientsModule.creerNouveauClient();
}

function supprimerVehicule(vehiculeId, clientId) {
    return window.ClientsModule.supprimerVehicule(vehiculeId, clientId);
}

function showClientDetail(clientId) {
    return window.ClientsModule.showClientDetail(clientId);
}

// ===== DETAIL RDV DEPUIS HISTORIQUE CLIENT =====
function ouvrirDetailHistoriqueRdv(rdvId, clientId) {
    return window.ClientsModule.ouvrirDetailHistoriqueRdv(rdvId, clientId);
}

// ===== AJOUTER VEHICULE A UN CLIENT =====
function ouvrirAjouterVehicule(clientId) {
    return window.ClientsModule.ouvrirAjouterVehicule(clientId);
}

function sauverNouveauVehicule(clientId) {
    return window.ClientsModule.sauverNouveauVehicule(clientId);
}

function ouvrirModalEditClient(clientId) {
    return window.ClientsModule.ouvrirModalEditClient(clientId);
}

function sauverClient(clientId) {
    return window.ClientsModule.sauverClient(clientId);
}

// ===== EDIT VEHICULE MODAL =====
function ouvrirModalEditVehicule(vehiculeId) {
    return window.ClientsModule.ouvrirModalEditVehicule(vehiculeId);
}

function sauverVehicule(vehiculeId) {
    return window.ClientsModule.sauverVehicule(vehiculeId);
}

// ===== DETAIL RDV MODAL (receptionnaire) =====
function ouvrirDetailRdv(rdvId) {
    apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
        var c = rdv.client || {};
        var v = rdv.vehicule || {};
        var meca = APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
        var pont = APP.ponts.find(function(p) { return p.id === rdv.pont_id; });
        var html = '';

        // Info client
        html += '<div style="margin-bottom:16px"><div style="font-size:13px;font-weight:700;color:var(--orange);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Client</div>' +
            '<div style="background:#16161a;border-radius:8px;padding:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">' +
            '<div><span style="color:#666">Nom:</span> <span style="color:#eee">' + (escapeHtml(c.nom) || '-') + '</span></div>' +
            '<div><span style="color:#666">Prenom:</span> <span style="color:#eee">' + (escapeHtml(c.prenom) || '-') + '</span></div>' +
            '<div><span style="color:#666">Tel:</span> <span style="color:#eee">' + (escapeHtml(c.telephone) || '-') + '</span></div>' +
            '<div><span style="color:#666">Email:</span> <span style="color:#eee">' + (escapeHtml(c.email) || '-') + '</span></div>' +
            '</div>' +
            '<button class="btn btn-ghost" style="margin-top:6px;font-size:11px;color:var(--teal)" onclick="closeModal();ouvrirModalEditClient(' + c.id + ')">Modifier client</button>' +
            '</div>';

        // Info vehicule
        html += '<div style="margin-bottom:16px"><div style="font-size:13px;font-weight:700;color:var(--orange);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Vehicule</div>' +
            '<div style="background:#16161a;border-radius:8px;padding:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">' +
            '<div><span style="color:#666">Plaque:</span> <span style="color:#eee">' + (escapeHtml(v.plaque) || '-') + '</span></div>' +
            '<div><span style="color:#666">Marque:</span> <span style="color:#eee">' + (escapeHtml(v.marque) || '-') + '</span></div>' +
            '<div><span style="color:#666">Modele:</span> <span style="color:#eee">' + (escapeHtml(v.modele) || '-') + '</span></div>' +
            '<div><span style="color:#666">Annee:</span> <span style="color:#eee">' + (escapeHtml(v.annee) || '-') + '</span></div>' +
            '<div><span style="color:#666">Type:</span> <span style="color:#eee">' + (escapeHtml(v.type_moto) || '-') + '</span></div>' +
            '<div><span style="color:#666">Cylindree:</span> <span style="color:#eee">' + (escapeHtml(v.cylindree) || '-') + '</span></div>' +
            '</div>' +
            '<button class="btn btn-ghost" style="margin-top:6px;font-size:11px;color:var(--teal)" onclick="closeModal();ouvrirModalEditVehicule(' + v.id + ')">Modifier vehicule</button>' +
            '</div>';

        // Info RDV
        html += '<div style="margin-bottom:16px"><div style="font-size:13px;font-weight:700;color:var(--orange);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Rendez-vous</div>' +
            '<div style="background:#16161a;border-radius:8px;padding:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">' +
            '<div><span style="color:#666">Date:</span> <span style="color:#eee">' + (rdv.date_rdv || '-') + '</span></div>' +
            '<div><span style="color:#666">Heure:</span> <span style="color:#eee">' + formatTime(rdv.heure_rdv) + '</span></div>' +
            '<div><span style="color:#666">Type:</span> <span style="color:#eee">' + (escapeHtml(rdv.type_intervention) || '-') + '</span></div>' +
            '<div><span style="color:#666">Statut:</span> ' + statusBadge(rdv.statut) + '</div>' +
            '<div><span style="color:#666">Pont:</span> <span style="color:#eee">' + (pont ? pont.nom : 'Non assigne') + '</span></div>' +
            '<div><span style="color:#666">Meca:</span> <span style="color:#eee">' + (meca ? meca.prenom + ' ' + escapeHtml(meca.nom) : 'Non assigne') + '</span></div>' +
            '</div></div>';

        // Commentaire editable
        html += '<div style="margin-bottom:16px">' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Commentaire</label>' +
            '<textarea id="edit-rdv-comment" class="form-input" rows="2" placeholder="Notes...">' + (escapeHtml(rdv.commentaire) || '') + '</textarea></div></div>';

        // Actions
        html += '<div style="display:flex;gap:8px">' +
            '<button class="btn btn-primary" style="flex:1" onclick="sauverCommentaireRdv(' + rdv.id + ')">Enregistrer</button>' +
            '<button class="btn btn-ghost" style="flex:1" onclick="closeModal();ouvrirAssignation(' + rdv.id + ')">Reassigner</button>' +
            '</div>';

        showModal('RDV #' + rdv.id + ' - ' + formatTime(rdv.heure_rdv) + ' ' + (rdv.date_rdv || ''), html, '550px');
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function sauverCommentaireRdv(rdvId) {
    var comment = document.getElementById('edit-rdv-comment').value;
    apiPut('/api/rendez-vous/' + rdvId, { commentaire: comment }).then(function() {
        closeModal();
        showNotificationToast('RDV mis a jour');
        refreshCurrentSection();
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

// ===== EDIT VEHICULE MODAL =====
function ouvrirModalEditVehicule(vehiculeId) {
    apiGet('/api/vehicules/' + vehiculeId).then(function(r) { return r.json(); }).then(function(v) {
        var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Plaque</label><input id="edit-veh-plaque" class="form-input" value="' + (escapeHtml(v.plaque) || '') + '"></div>' +
            '<div style="display:flex;gap:12px"><div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Marque</label><input id="edit-veh-marque" class="form-input" value="' + (escapeHtml(v.marque) || '') + '"></div>' +
            '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Modele</label><input id="edit-veh-modele" class="form-input" value="' + (escapeHtml(v.modele) || '') + '"></div></div>' +
            '<div style="display:flex;gap:12px"><div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Annee</label><input id="edit-veh-annee" class="form-input" type="number" value="' + (escapeHtml(v.annee) || '') + '"></div>' +
            '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Cylindree</label><input id="edit-veh-cylindree" class="form-input" value="' + (escapeHtml(v.cylindree) || '') + '"></div></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Type de moto</label><select id="edit-veh-type" class="form-select">' +
            '<option value="">--</option>' +
            ['Roadster','Sportive','Trail','Custom','Scooter','Enduro'].map(function(t) { return '<option value="' + t + '"' + (escapeHtml(v.type_moto) === t ? ' selected' : '') + '>' + t + '</option>'; }).join('') +
            '</select></div>' +
            '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="sauverVehicule(' + vehiculeId + ')">Enregistrer</button>';
        showModal('Modifier vehicule', html, '450px');
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function sauverVehicule(vehiculeId) {
    var data = {
        plaque: document.getElementById('edit-veh-plaque').value || null,
        marque: document.getElementById('edit-veh-marque').value || null,
        modele: document.getElementById('edit-veh-modele').value || null,
        annee: document.getElementById('edit-veh-annee').value ? parseInt(document.getElementById('edit-veh-annee').value) : null,
        cylindree: document.getElementById('edit-veh-cylindree').value || null,
        type_moto: document.getElementById('edit-veh-type').value || null
    };
    apiPut('/api/vehicules/' + vehiculeId, data).then(function() {
        closeModal();
        showNotificationToast('Vehicule mis a jour');
        refreshCurrentSection();
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

// ===== ATTRIBUTION PONT -> MECANICIEN =====
function ouvrirAttribuerPont(pontId) {
    var pont = APP.ponts.find(function(p) { return p.id === pontId; });
    var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Mecanicien attribue</label><select id="attr-pont-meca" class="form-select">';
    html += '<option value="">-- Aucun --</option>';
    APP.mecaniciens.forEach(function(m) {
        if (!isActive(m)) return;
        var sel = (pont && pont.mecanicien_id === m.id) ? ' selected' : '';
        html += '<option value="' + m.id + '"' + sel + '>' + escapeHtml(m.prenom) + ' ' + escapeHtml(m.nom) + '</option>';
    });
    html += '</select></div>';
    html += '<button class="btn btn-primary" style="width:100%;margin-top:12px" onclick="sauverAttribuerPont(' + pontId + ')">Enregistrer</button>';
    showModal('Attribuer ' + (pont ? pont.nom : 'Pont') + ' a un mecanicien', html, '400px');
}

function sauverAttribuerPont(pontId) {
    var mecaId = document.getElementById('attr-pont-meca').value;
    var pont = APP.ponts.find(function(p) { return p.id === pontId; });
    if (!pont) return;
    apiPut('/api/ponts/' + pontId, {
        nom: pont.nom,
        type_pont: pont.type_pont || 'moto',
        capacite_kg: pont.capacite_kg || 500,
        is_active: pont.actif ? 1 : 0,
        mecanicien_id: mecaId ? parseInt(mecaId) : null
    }).then(function() {
        closeModal();
        showNotificationToast('Pont attribue');
        loadBaseData().then(function() { loadPontsMecas(); });
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function loadAdminAteliers() {
    return window.AdminModule.loadAdminAteliers();
}

function selectAdminAtelier(atelierId) {
    return window.AdminModule.selectAdminAtelier(atelierId);
}

function loadAdminUsers() {
    return window.AdminModule.loadAdminUsers();
}

function renderAdminUsers(users) {
    return window.AdminModule.renderAdminUsers(users);
}

function switchAtelier(atelierId) {
    return window.AdminModule.switchAtelier(atelierId);
}

function ouvrirNouveauAtelier() {
    return window.AdminModule.ouvrirNouveauAtelier();
}

function creerAtelier() {
    return window.AdminModule.creerAtelier();
}

function ouvrirEditAtelier(id, nom) {
    return window.AdminModule.ouvrirEditAtelier(id, nom);
}

function sauverAtelier(id) {
    return window.AdminModule.sauverAtelier(id);
}

function ouvrirNouvelUtilisateurAtelier() {
    return window.AdminModule.ouvrirNouvelUtilisateurAtelier();
}

function renderCreateUserModal(roleOptionsHtml) {
    return window.AdminModule.renderCreateUserModal(roleOptionsHtml);
}

function toggleCreateUserMecaFields() {
    return window.AdminModule.toggleCreateUserMecaFields();
}

function creerUtilisateurAtelier() {
    return window.AdminModule.creerUtilisateurAtelier();
}

function ouvrirEditionUtilisateurAtelier(userId) {
    return window.AdminModule.ouvrirEditionUtilisateurAtelier(userId);
}

function toggleEditUserMecaFields() {
    return window.AdminModule.toggleEditUserMecaFields();
}

function sauverEditionUtilisateurAtelier(userId) {
    return window.AdminModule.sauverEditionUtilisateurAtelier(userId);
}

function supprimerUtilisateurAtelier(userId) {
    return window.AdminModule.supprimerUtilisateurAtelier(userId);
}

function switchAdminTab(tabId) {
    return window.AdminModule.switchAdminTab(tabId);
}

function loadAdminRoles() {
    return window.AdminModule.loadAdminRoles();
}

function openRoleEditor(roleName) {
    return window.AdminModule.openRoleEditor(roleName);
}

function saveRolePermission() {
    return window.AdminModule.saveRolePermission();
}

function normalizeRoleSlug(value) {
    return window.AdminModule.normalizeRoleSlug(value);
}

function deleteRolePermission(role) {
    return window.AdminModule.deleteRolePermission(role);
}

function loadAdminConfig() {
    return window.AdminModule.loadAdminConfig();
}

function saveAdminConfig(e) {
    return window.AdminModule.saveAdminConfig(e);
}

function loadAdminCategoriesMoto() {
    return window.AdminModule.loadAdminCategoriesMoto();
}

function toggleAdminCategorieMoto(categorieId) {
    return window.AdminModule.toggleAdminCategorieMoto(categorieId);
}

function adminFmtTime(value) {
    return window.AdminModule.adminFmtTime(value);
}

function loadAdminHoraires() {
    return window.AdminModule.loadAdminHoraires();
}

function saveAdminHoraire(jour) {
    return window.AdminModule.saveAdminHoraire(jour);
}

function toggleAdminMidi(jour) {
    return window.AdminModule.toggleAdminMidi(jour);
}

function adminFormatMinutes(mins) {
    return window.AdminModule.adminFormatMinutes(mins);
}

function loadAdminPrestations() {
    return window.AdminModule.loadAdminPrestations();
}

function openAdminPrestationModal(id) {
    return window.AdminModule.openAdminPrestationModal(id);
}

function saveAdminPrestation(id, tvaMo) {
    return window.AdminModule.saveAdminPrestation(id, tvaMo);
}

function toggleAdminPrestation(id, state) {
    return window.AdminModule.toggleAdminPrestation(id, state);
}

function openAdminGrilleModal(prestationId) {
    return window.AdminModule.openAdminGrilleModal(prestationId);
}

function saveAdminGrille(prestationId, tvaMo) {
    return window.AdminModule.saveAdminGrille(prestationId, tvaMo);
}

function loadAdminWorkshop() {
    return window.AdminModule.loadAdminWorkshop();
}

function renderAdminWorkshopPonts(ponts, mecas) {
    return window.AdminModule.renderAdminWorkshopPonts(ponts, mecas);
}

function renderAdminWorkshopMecas(mecas, users) {
    return window.AdminModule.renderAdminWorkshopMecas(mecas, users);
}

function openAdminPontModal(pontId) {
    return window.AdminModule.openAdminPontModal(pontId);
}

function saveAdminPont(pontId) {
    return window.AdminModule.saveAdminPont(pontId);
}

function deleteAdminPont(pontId) {
    return window.AdminModule.deleteAdminPont(pontId);
}

function deleteAdminMecanicien(mecanicienId) {
    return window.AdminModule.deleteAdminMecanicien(mecanicienId);
}

function loadAdminEquipements() {
    return window.AdminModule.loadAdminEquipements();
}

function openAdminEquipementModal() {
    return window.AdminModule.openAdminEquipementModal();
}

function saveAdminEquipement() {
    return window.AdminModule.saveAdminEquipement();
}

function deleteAdminEquipement(id) {
    return window.AdminModule.deleteAdminEquipement(id);
}

console.log('app.js loaded');
