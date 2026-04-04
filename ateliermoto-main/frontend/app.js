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
    if (window.DashboardModule && window.DashboardModule.loadDashboard) {
        return window.DashboardModule.loadDashboard();
    }
}

function renderDashboardStats(rdvs, ponts, facturationStats) {
    if (window.DashboardModule && window.DashboardModule.renderDashboardStats) {
        return window.DashboardModule.renderDashboardStats(rdvs, ponts, facturationStats);
    }
}

function renderDashboardPonts(ponts, rdvs) {
    if (window.DashboardModule && window.DashboardModule.renderDashboardPonts) {
        return window.DashboardModule.renderDashboardPonts(ponts, rdvs);
    }
}

function renderDashboardRdv(rdvs) {
    if (window.DashboardModule && window.DashboardModule.renderDashboardRdv) {
        return window.DashboardModule.renderDashboardRdv(rdvs);
    }
}

// ===== PRISE DE RDV =====
function loadRdvForm() { if (window.RdvModule && window.RdvModule.loadRdvForm) return window.RdvModule.loadRdvForm(); }
function searchMotoRdv(val) { if (window.RdvModule && window.RdvModule.searchMotoRdv) return window.RdvModule.searchMotoRdv(val); }
function updateDurationRdv() { if (window.RdvModule && window.RdvModule.updateDurationRdv) return window.RdvModule.updateDurationRdv(); }
function loadCreneaux() { if (window.RdvModule && window.RdvModule.loadCreneaux) return window.RdvModule.loadCreneaux(); }
function renderCreneaux(creneaux) { if (window.RdvModule && window.RdvModule.renderCreneaux) return window.RdvModule.renderCreneaux(creneaux); }
function selectSlotRdv(el, time) { if (window.RdvModule && window.RdvModule.selectSlotRdv) return window.RdvModule.selectSlotRdv(el, time); }
function confirmRdv() { if (window.RdvModule && window.RdvModule.confirmRdv) return window.RdvModule.confirmRdv(); }
function searchClientRdvEmbed(val) { if (window.RdvModule && window.RdvModule.searchClientRdvEmbed) return window.RdvModule.searchClientRdvEmbed(val); }
function selectClientRdvEmbed(clientId) { if (window.RdvModule && window.RdvModule.selectClientRdvEmbed) return window.RdvModule.selectClientRdvEmbed(clientId); }
function pushRdvPrefillToIframe() { if (window.RdvModule && window.RdvModule.pushRdvPrefillToIframe) return window.RdvModule.pushRdvPrefillToIframe(); }
function ouvrirRdvPublicModal() { if (window.RdvModule && window.RdvModule.ouvrirRdvPublicModal) return window.RdvModule.ouvrirRdvPublicModal(); }
function getCurrentAtelierSlug() { if (window.RdvModule && window.RdvModule.getCurrentAtelierSlug) return window.RdvModule.getCurrentAtelierSlug(); return 'default'; }
function populateRdvAtelierSelect() { if (window.RdvModule && window.RdvModule.populateRdvAtelierSelect) return window.RdvModule.populateRdvAtelierSelect(); }
function onRdvAtelierChange(slug) { if (window.RdvModule && window.RdvModule.onRdvAtelierChange) return window.RdvModule.onRdvAtelierChange(slug); }
function getRdvState() { if (window.RdvModule && window.RdvModule.getRdvState) return window.RdvModule.getRdvState(); return APP.rdvWizard; }
function goStep(n) { if (window.RdvModule && window.RdvModule.goStep) return window.RdvModule.goStep(n); }
function rechercherVehicule() { if (window.RdvModule && window.RdvModule.rechercherVehicule) return window.RdvModule.rechercherVehicule(); }
function validerVehiculeManuel() { if (window.RdvModule && window.RdvModule.validerVehiculeManuel) return window.RdvModule.validerVehiculeManuel(); }
function onTypeMotoFoundSelect(val) { if (window.RdvModule && window.RdvModule.onTypeMotoFoundSelect) return window.RdvModule.onTypeMotoFoundSelect(val); }
function continuerVehiculeFound() { if (window.RdvModule && window.RdvModule.continuerVehiculeFound) return window.RdvModule.continuerVehiculeFound(); }
function getPrestaTarif(it) { if (window.RdvModule && window.RdvModule.getPrestaTarif) return window.RdvModule.getPrestaTarif(it); return { prix_ttc: 0, temps_minutes: 30 }; }
function chargerPrestations() { if (window.RdvModule && window.RdvModule.chargerPrestations) return window.RdvModule.chargerPrestations(); }
function renderPrestations() { if (window.RdvModule && window.RdvModule.renderPrestations) return window.RdvModule.renderPrestations(); }
function togglePresta(id) { if (window.RdvModule && window.RdvModule.togglePresta) return window.RdvModule.togglePresta(id); }
function updateRecap() { if (window.RdvModule && window.RdvModule.updateRecap) return window.RdvModule.updateRecap(); }
function chargerDelaiIntervention() { if (window.RdvModule && window.RdvModule.chargerDelaiIntervention) return window.RdvModule.chargerDelaiIntervention(); return Promise.resolve(); }
function _rdvDateToStr(d) { if (window.RdvModule && window.RdvModule._rdvDateToStr) return window.RdvModule._rdvDateToStr(d); return ''; }
function _rdvWeekDays(offset, delayDays) { if (window.RdvModule && window.RdvModule._rdvWeekDays) return window.RdvModule._rdvWeekDays(offset, delayDays); return []; }
function changeWeek(dir) { if (window.RdvModule && window.RdvModule.changeWeek) return window.RdvModule.changeWeek(dir); }
function chargerSemaine() { if (window.RdvModule && window.RdvModule.chargerSemaine) return window.RdvModule.chargerSemaine(); }
function chargerJour(dateStr) { if (window.RdvModule && window.RdvModule.chargerJour) return window.RdvModule.chargerJour(dateStr); }
function renderJour(dateStr, data) { if (window.RdvModule && window.RdvModule.renderJour) return window.RdvModule.renderJour(dateStr, data); }
function _rdvFormatDate(dateStr) { if (window.RdvModule && window.RdvModule._rdvFormatDate) return window.RdvModule._rdvFormatDate(dateStr); return dateStr || '-'; }
function selectWeekSlot(el, dateStr, heure) { if (window.RdvModule && window.RdvModule.selectWeekSlot) return window.RdvModule.selectWeekSlot(el, dateStr, heure); }
function afficherRecap() { if (window.RdvModule && window.RdvModule.afficherRecap) return window.RdvModule.afficherRecap(); }
function confirmerRDV() { if (window.RdvModule && window.RdvModule.confirmerRDV) return window.RdvModule.confirmerRDV(); }

// ===== PLANNING =====
function loadPlanning() { if (window.PlanningModule && window.PlanningModule.loadPlanning) return window.PlanningModule.loadPlanning(); }
function isPlanningSlotOpen(dateStr, hour) { if (window.PlanningModule && window.PlanningModule.isPlanningSlotOpen) return window.PlanningModule.isPlanningSlotOpen(dateStr, hour); return true; }
function isPlanningSlotValidForDuration(dateStr, hour, durationMinutes) { if (window.PlanningModule && window.PlanningModule.isPlanningSlotValidForDuration) return window.PlanningModule.isPlanningSlotValidForDuration(dateStr, hour, durationMinutes); return true; }
function getPlanningHoraireForDate(dateStr) { if (window.PlanningModule && window.PlanningModule.getPlanningHoraireForDate) return window.PlanningModule.getPlanningHoraireForDate(dateStr); return null; }
function splitRdvSegments(dateStr, startMin, durationMin) { if (window.PlanningModule && window.PlanningModule.splitRdvSegments) return window.PlanningModule.splitRdvSegments(dateStr, startMin, durationMin); return [{ start: startMin, end: startMin + durationMin, continuation: false }]; }
function buildPlanningBusyCells(rdvs) { if (window.PlanningModule && window.PlanningModule.buildPlanningBusyCells) return window.PlanningModule.buildPlanningBusyCells(rdvs); return {}; }
function isPlanningCellBusy(dateStr, hour) { if (window.PlanningModule && window.PlanningModule.isPlanningCellBusy) return window.PlanningModule.isPlanningCellBusy(dateStr, hour); return false; }
function getPlanningAtelierSlug() { if (window.PlanningModule && window.PlanningModule.getPlanningAtelierSlug) return window.PlanningModule.getPlanningAtelierSlug(); return 'default'; }
function populatePlanningAtelierSelect() { if (window.PlanningModule && window.PlanningModule.populatePlanningAtelierSelect) return window.PlanningModule.populatePlanningAtelierSelect(); }
function onPlanningAtelierChange(slug) { if (window.PlanningModule && window.PlanningModule.onPlanningAtelierChange) return window.PlanningModule.onPlanningAtelierChange(slug); }
function renderMecaFilters() { if (window.PlanningModule && window.PlanningModule.renderMecaFilters) return window.PlanningModule.renderMecaFilters(); }
function toggleMecaFilter(mecaId) { if (window.PlanningModule && window.PlanningModule.toggleMecaFilter) return window.PlanningModule.toggleMecaFilter(mecaId); }
function renderPlanningGrid(rdvs, monday) { if (window.PlanningModule && window.PlanningModule.renderPlanningGrid) return window.PlanningModule.renderPlanningGrid(rdvs, monday); }

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

function getWeekNumber(d) { if (window.PlanningModule && window.PlanningModule.getWeekNumber) return window.PlanningModule.getWeekNumber(d); return 1; }

function planningPrev() { if (window.PlanningModule && window.PlanningModule.planningPrev) return window.PlanningModule.planningPrev(); }
function planningNext() { if (window.PlanningModule && window.PlanningModule.planningNext) return window.PlanningModule.planningNext(); }

// ===== DRAG & DROP PLANNING =====
function onRdvDragStart(event, rdvId) { if (window.PlanningModule && window.PlanningModule.onRdvDragStart) return window.PlanningModule.onRdvDragStart(event, rdvId); }
function onRdvDragEnd(event) { if (window.PlanningModule && window.PlanningModule.onRdvDragEnd) return window.PlanningModule.onRdvDragEnd(event); }
function onCellDragOver(event) { if (window.PlanningModule && window.PlanningModule.onCellDragOver) return window.PlanningModule.onCellDragOver(event); }
function onCellDragLeave(event) { if (window.PlanningModule && window.PlanningModule.onCellDragLeave) return window.PlanningModule.onCellDragLeave(event); }
function onCellDrop(event, dateStr, hour) { if (window.PlanningModule && window.PlanningModule.onCellDrop) return window.PlanningModule.onCellDrop(event, dateStr, hour); }

// ===== CLIC SUR RDV PLANNING =====
function onPlanningRdvClick(rdvId) { if (window.PlanningModule && window.PlanningModule.onPlanningRdvClick) return window.PlanningModule.onPlanningRdvClick(rdvId); }

// ===== CLIC CELLULE VIDE - QUICK CREATE =====
function onPlanningCellClick(event, dateStr, hour) { if (window.PlanningModule && window.PlanningModule.onPlanningCellClick) return window.PlanningModule.onPlanningCellClick(event, dateStr, hour); }
function ouvrirQuickCreateRdv(dateStr, hour) { if (window.PlanningModule && window.PlanningModule.ouvrirQuickCreateRdv) return window.PlanningModule.ouvrirQuickCreateRdv(dateStr, hour); }
function searchClientQuickCreate(val) { if (window.PlanningModule && window.PlanningModule.searchClientQuickCreate) return window.PlanningModule.searchClientQuickCreate(val); }
function selectClientQuickCreate(nom, prenom, tel) { if (window.PlanningModule && window.PlanningModule.selectClientQuickCreate) return window.PlanningModule.selectClientQuickCreate(nom, prenom, tel); }
function searchVehiculeQuickCreate(val) { if (window.PlanningModule && window.PlanningModule.searchVehiculeQuickCreate) return window.PlanningModule.searchVehiculeQuickCreate(val); }
function submitQuickCreateRdv() { if (window.PlanningModule && window.PlanningModule.submitQuickCreateRdv) return window.PlanningModule.submitQuickCreateRdv(); }


// ===== PONTS & MECANICIENS =====
function loadPontsMecas() { if (window.WorkshopModule && window.WorkshopModule.loadPontsMecas) return window.WorkshopModule.loadPontsMecas(); }
function renderPontsTab() { if (window.WorkshopModule && window.WorkshopModule.renderPontsTab) return window.WorkshopModule.renderPontsTab(); }
function renderMecasTab() { if (window.WorkshopModule && window.WorkshopModule.renderMecasTab) return window.WorkshopModule.renderMecasTab(); }
function renderPontsManagerKpis(ponts, rdvs, absences) { if (window.WorkshopModule && window.WorkshopModule.renderPontsManagerKpis) return window.WorkshopModule.renderPontsManagerKpis(ponts, rdvs, absences); }
function countManagerConflicts(rdvs) { if (window.WorkshopModule && window.WorkshopModule.countManagerConflicts) return window.WorkshopModule.countManagerConflicts(rdvs); return 0; }
function renderTempsTab() { if (window.WorkshopModule && window.WorkshopModule.renderTempsTab) return window.WorkshopModule.renderTempsTab(); }

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
function ouvrirDetailRdv(rdvId) { if (window.WorkshopModule && window.WorkshopModule.ouvrirDetailRdv) return window.WorkshopModule.ouvrirDetailRdv(rdvId); }
function sauverCommentaireRdv(rdvId) { if (window.WorkshopModule && window.WorkshopModule.sauverCommentaireRdv) return window.WorkshopModule.sauverCommentaireRdv(rdvId); }

// ===== ATTRIBUTION PONT -> MECANICIEN =====
function ouvrirAttribuerPont(pontId) { if (window.WorkshopModule && window.WorkshopModule.ouvrirAttribuerPont) return window.WorkshopModule.ouvrirAttribuerPont(pontId); }
function sauverAttribuerPont(pontId) { if (window.WorkshopModule && window.WorkshopModule.sauverAttribuerPont) return window.WorkshopModule.sauverAttribuerPont(pontId); }

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
