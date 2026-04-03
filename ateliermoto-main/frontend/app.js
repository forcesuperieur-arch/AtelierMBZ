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
    window.open(window.API_URL + '/api/rendez-vous/' + rdvId + '/ordre-reparation', '_blank');
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
    if (!canUseBilling()) {
        showAlert('Facturation desactivee pour le role service client', 'warning');
        return;
    }
    apiGet('/api/rendez-vous/' + rdvId + '/preview-facture').then(function(r) { return r.json(); }).then(function(data) {
        var html = '<div style="background:#1e1e1e;border-radius:8px;padding:16px">';

        // Recap MO
        var heures = (data.temps_facture_minutes / 60).toFixed(2);
        html += '<div style="margin-bottom:12px"><div style="color:#9ca3af;font-size:11px;margin-bottom:4px">MAIN D\'OEUVRE</div>';
        if (data.is_forfait) {
            // Mode forfait : afficher le prix fixe convenu
            html += '<div style="display:flex;justify-content:space-between;color:#e5e7eb">';
            html += '<span>Forfait - ' + (data.forfait_designation || data.type_intervention) + '</span>';
            html += '<span style="font-weight:bold">' + data.total_mo_ht.toFixed(2) + ' EUR HT</span></div>';
        } else {
            // Mode horaire : affichage classique
            html += '<div style="display:flex;justify-content:space-between;color:#e5e7eb">';
            html += '<span>' + data.temps_facture_minutes + ' min (' + heures + 'h) x ' + data.taux_horaire.toFixed(2) + ' EUR/h</span>';
            html += '<span style="font-weight:bold">' + data.total_mo_ht.toFixed(2) + ' EUR HT</span></div>';
        }
        html += '</div>';

        // Recap Pieces
        if (data.pieces && data.pieces.length > 0) {
            html += '<div style="margin-bottom:12px"><div style="color:#9ca3af;font-size:11px;margin-bottom:4px">PIECES</div>';
            data.pieces.forEach(function(p) {
                html += '<div style="display:flex;justify-content:space-between;color:#e5e7eb;font-size:13px">';
                html += '<span>' + escapeHtml(p.nom) + (p.reference ? ' (' + p.reference + ')' : '') + ' x' + p.quantite + '</span>';
                html += '<span>' + p.total_ht.toFixed(2) + ' EUR HT</span></div>';
            });
            html += '<div style="display:flex;justify-content:space-between;color:#e5e7eb;font-weight:bold;margin-top:4px;border-top:1px solid #333;padding-top:4px">';
            html += '<span>Total pieces</span><span>' + data.total_pieces_ht.toFixed(2) + ' EUR HT</span></div></div>';
        }

        // Separator
        html += '<div style="border-top:1px solid #444;margin:12px 0"></div>';

        // Remise
        html += '<div class="form-group" style="margin-bottom:12px"><label class="form-label" style="color:#9ca3af">Remise (%)</label>';
        html += '<input type="number" class="form-input" id="facture-remise" value="0" min="0" max="100" step="0.5" onchange="recalcFacturePreview()" oninput="recalcFacturePreview()" style="width:100px"></div>';

        // Totaux
        html += '<div id="facture-totaux">';
        html += renderFactureTotaux(data, 0);
        html += '</div>';

        // Notes
        html += '<div class="form-group" style="margin-top:12px"><label class="form-label" style="color:#9ca3af">Notes (optionnel)</label>';
        html += '<textarea class="form-input" id="facture-notes" rows="2" placeholder="Notes internes..."></textarea></div>';

        html += '</div>';
        html += '<div style="display:flex;gap:8px;margin-top:16px">';
        html += '<button class="btn btn-ghost" style="flex:1" onclick="closeModal()">Annuler</button>';
        html += '<button class="btn btn-primary" style="flex:1;background:#8B5CF6" onclick="confirmerFacturation(' + rdvId + ')">Generer la facture</button>';
        html += '</div>';

        // Stocker en mémoire pour recalc
        window._facturePreview = data;

        showModal('Facturer - ' + (data.type_intervention || 'RDV #' + rdvId), html, '550px');
    }).catch(function(e) { showAlert('Erreur: ' + e.message, 'error'); });
}

function renderFactureTotaux(data, remisePct) {
    var remise = data.total_ht * (remisePct / 100);
    var totalHtRemise = data.total_ht - remise;
    var ratio = data.total_ht > 0 ? totalHtRemise / data.total_ht : 1;
    var tvaMo = data.tva_mo * ratio;
    var tvaPieces = data.tva_pieces * ratio;
    var totalTtc = totalHtRemise + tvaMo + tvaPieces;

    var html = '<div style="background:#111;border-radius:6px;padding:12px">';
    html += '<div style="display:flex;justify-content:space-between;color:#9ca3af;font-size:13px"><span>Total HT</span><span>' + data.total_ht.toFixed(2) + ' EUR</span></div>';
    if (remise > 0) {
        html += '<div style="display:flex;justify-content:space-between;color:#22C55E;font-size:13px"><span>Remise (' + remisePct.toFixed(1) + '%)</span><span>-' + remise.toFixed(2) + ' EUR</span></div>';
        html += '<div style="display:flex;justify-content:space-between;color:#e5e7eb;font-size:13px;font-weight:bold"><span>Total HT remise</span><span>' + totalHtRemise.toFixed(2) + ' EUR</span></div>';
    }
    html += '<div style="display:flex;justify-content:space-between;color:#9ca3af;font-size:12px"><span>TVA MO (' + data.tva_mo_taux + '%)</span><span>' + tvaMo.toFixed(2) + ' EUR</span></div>';
    if (data.tva_pieces > 0) {
        html += '<div style="display:flex;justify-content:space-between;color:#9ca3af;font-size:12px"><span>TVA Pieces (' + data.tva_pieces_taux + '%)</span><span>' + tvaPieces.toFixed(2) + ' EUR</span></div>';
    }
    html += '<div style="display:flex;justify-content:space-between;color:#E8480A;font-size:16px;font-weight:bold;margin-top:8px;border-top:1px solid #333;padding-top:8px"><span>TOTAL TTC</span><span>' + totalTtc.toFixed(2) + ' EUR</span></div>';
    html += '</div>';
    return html;
}

function recalcFacturePreview() {
    var remise = parseFloat(document.getElementById('facture-remise').value) || 0;
    document.getElementById('facture-totaux').innerHTML = renderFactureTotaux(window._facturePreview, remise);
}

function confirmerFacturation(rdvId) {
    if (!canUseBilling()) {
        showAlert('Facturation desactivee pour le role service client', 'warning');
        return;
    }
    var remise = parseFloat(document.getElementById('facture-remise').value) || 0;
    var notes = document.getElementById('facture-notes').value;
    apiPost('/api/rendez-vous/' + rdvId + '/facturer', {
        remise_pourcentage: remise,
        notes: notes || null
    }).then(function(r) { return r.json(); }).then(function(data) {
        closeModal();
        showAlert('Facture ' + data.numero_facture + ' creee - ' + data.total_ttc.toFixed(2) + ' EUR TTC');
        refreshCurrentSection();
    }).catch(function(e) { showAlert('Erreur: ' + e.message, 'error'); });
}

// ===== ENCAISSEMENT =====
function ouvrirEncaissement(rdvId) {
    if (!canUseBilling()) {
        showAlert('Encaissement desactive pour le role service client', 'warning');
        return;
    }
    apiGet('/api/factures/par-rdv/' + rdvId).then(function(r) { return r.json(); }).then(function(facture) {
        var html = '<div style="background:#1e1e1e;border-radius:8px;padding:16px">';

        // Info facture
        html += '<div style="display:flex;justify-content:space-between;margin-bottom:16px">';
        html += '<div><div style="color:#9ca3af;font-size:11px">FACTURE</div><div style="color:#e5e7eb;font-size:16px;font-weight:bold">' + facture.numero_facture + '</div></div>';
        html += '<div style="text-align:right"><div style="color:#9ca3af;font-size:11px">TOTAL TTC</div><div style="color:#E8480A;font-size:16px;font-weight:bold">' + facture.total_ttc.toFixed(2) + ' EUR</div></div>';
        html += '</div>';

        // Etat paiement
        if (facture.montant_paye > 0) {
            html += '<div style="background:#111;border-radius:6px;padding:10px;margin-bottom:12px">';
            html += '<div style="display:flex;justify-content:space-between;color:#22C55E;font-size:13px"><span>Deja paye</span><span>' + facture.montant_paye.toFixed(2) + ' EUR</span></div>';
            html += '<div style="display:flex;justify-content:space-between;color:#EF4444;font-size:14px;font-weight:bold;margin-top:4px"><span>Reste a payer</span><span>' + facture.montant_restant.toFixed(2) + ' EUR</span></div>';
            html += '</div>';
        }

        // Mode paiement
        html += '<div class="form-group"><label class="form-label" style="color:#9ca3af">Mode de paiement</label>';
        html += '<select class="form-select" id="enc-mode" onchange="toggleEncReference()">';
        html += '<option value="cb">Carte bancaire</option>';
        html += '<option value="especes">Especes</option>';
        html += '<option value="cheque">Cheque</option>';
        html += '<option value="virement">Virement bancaire</option>';
        html += '<option value="differe">Paiement differe</option>';
        html += '</select></div>';

        // Montant
        html += '<div class="form-group"><label class="form-label" style="color:#9ca3af">Montant</label>';
        html += '<input type="number" class="form-input" id="enc-montant" value="' + facture.montant_restant.toFixed(2) + '" min="0.01" max="' + facture.montant_restant.toFixed(2) + '" step="0.01"></div>';

        // Reference (conditionnelle)
        html += '<div class="form-group" id="enc-ref-group" style="display:none"><label class="form-label" style="color:#9ca3af">Reference (n cheque / ref virement)</label>';
        html += '<input type="text" class="form-input" id="enc-reference" placeholder="N° cheque ou reference virement"></div>';

        // Notes
        html += '<div class="form-group"><label class="form-label" style="color:#9ca3af">Notes (optionnel)</label>';
        html += '<input type="text" class="form-input" id="enc-notes" placeholder="Notes..."></div>';

        html += '</div>';
        html += '<div style="display:flex;gap:8px;margin-top:16px">';
        html += '<button class="btn btn-ghost" style="flex:1" onclick="closeModal()">Annuler</button>';
        html += '<button class="btn btn-primary" style="flex:1;background:var(--green)" onclick="confirmerEncaissement(' + facture.id + ')">Enregistrer le paiement</button>';
        html += '</div>';

        showModal('Encaisser - ' + facture.numero_facture, html, '500px');
    }).catch(function(e) { showAlert('Erreur: ' + e.message, 'error'); });
}

function toggleEncReference() {
    var mode = document.getElementById('enc-mode').value;
    document.getElementById('enc-ref-group').style.display = (mode === 'cheque' || mode === 'virement') ? 'block' : 'none';
}

function confirmerEncaissement(factureId) {
    if (!canUseBilling()) {
        showAlert('Encaissement desactive pour le role service client', 'warning');
        return;
    }
    var montant = parseFloat(document.getElementById('enc-montant').value);
    var mode = document.getElementById('enc-mode').value;
    var reference = document.getElementById('enc-reference') ? document.getElementById('enc-reference').value : '';
    var notes = document.getElementById('enc-notes').value;

    if (!montant || montant <= 0) { showAlert('Montant invalide', 'error'); return; }

    apiPost('/api/factures/' + factureId + '/encaisser', {
        montant: montant,
        mode_paiement: mode,
        reference: reference || null,
        notes: notes || null
    }).then(function(r) { return r.json(); }).then(function(data) {
        closeModal();
        var msg = data.statut === 'payee' ? 'Paiement enregistre - Facture soldee' : 'Paiement enregistre - Reste: ' + data.montant_restant.toFixed(2) + ' EUR';
        showAlert(msg);
        refreshCurrentSection();
    }).catch(function(e) { showAlert('Erreur: ' + e.message, 'error'); });
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
    if (!hhmm || hhmm.indexOf(':') === -1) return -1;
    var parts = hhmm.split(':');
    var h = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10);
    if (isNaN(h) || isNaN(m)) return -1;
    return (h * 60) + m;
}

// Parse ISO datetime as UTC (server stores UTC without 'Z' suffix)
function parseUTCDate(isoStr) {
    if (!isoStr) return null;
    var s = String(isoStr);
    if (!s.endsWith('Z') && !s.includes('+')) s += 'Z';
    var d = new Date(s);
    return isNaN(d.getTime()) ? null : d;
}

function minutesToTimeLabel(totalMin) {
    var h = Math.floor(totalMin / 60);
    var m = totalMin % 60;
    return (h < 10 ? '0' + h : '' + h) + ':' + (m < 10 ? '0' + m : '' + m);
}

function getPlanningBounds() {
    if (!APP._horairesLoaded || !APP._horairesByDay) return { start: '08:00', end: '18:00' };
    var minOpen = 24 * 60, maxClose = 0, found = false;
    for (var j = 0; j < 7; j++) {
        var h = APP._horairesByDay[j];
        if (!h || !h.is_ouvert) continue;
        var o = timeToMinutes(adminFmtTime(h.heure_ouverture));
        var c = timeToMinutes(adminFmtTime(h.heure_fermeture));
        if (o >= 0 && c > 0) {
            found = true;
            if (o < minOpen) minOpen = o;
            if (c > maxClose) maxClose = c;
        }
    }
    if (!found) return { start: '08:00', end: '18:00' };
    return { start: minutesToTimeLabel(minOpen), end: minutesToTimeLabel(maxClose) };
}

function buildPlanningSlots(startTime, endTime, stepMin) {
    var start = timeToMinutes(startTime);
    var end = timeToMinutes(endTime);
    var step = Math.max(5, parseInt(stepMin || 15, 10));
    var out = [];
    for (var t = start; t < end; t += step) out.push(minutesToTimeLabel(t));
    return out;
}

function markConflictCells(cellMap, dayKey, startMin, endMin) {
    var step = APP._planningSlotMinutes || 15;
    var slotStart = Math.floor(startMin / step) * step;
    var slotEnd = Math.max(slotStart, Math.ceil(endMin / step) * step - step);
    for (var t = slotStart; t <= slotEnd; t += step) {
        cellMap[dayKey + '|' + minutesToTimeLabel(t)] = true;
    }
}

function buildVisualOverlapGroups(dayList, byId) {
    for (var i = 0; i < dayList.length; i++) {
        var current = dayList[i];
        var cStart = timeToMinutes(formatTime(current.heure_rdv || ''));
        if (cStart < 0) continue;
        var cEnd = cStart + getRdvDurationMinutes(current);
        var overlaps = [];
        for (var j = 0; j < dayList.length; j++) {
            var other = dayList[j];
            var oStart = timeToMinutes(formatTime(other.heure_rdv || ''));
            if (oStart < 0) continue;
            var oEnd = oStart + getRdvDurationMinutes(other);
            if (cStart < oEnd && oStart < cEnd) overlaps.push(other);
        }
        overlaps.sort(function(a, b) {
            var aStart = timeToMinutes(formatTime(a.heure_rdv || ''));
            var bStart = timeToMinutes(formatTime(b.heure_rdv || ''));
            if (aStart !== bStart) return aStart - bStart;
            return (a.id || 0) - (b.id || 0);
        });
        var index = 0;
        for (var k = 0; k < overlaps.length; k++) {
            if (overlaps[k].id === current.id) { index = k; break; }
        }
        byId[current.id] = { index: index, count: overlaps.length || 1 };
    }
}

function renderPlanningNowLine(grid, monday) {
    if (!grid || !monday) return;
    var old = grid.querySelector('.planning-now-line');
    if (old) old.remove();
    var now = new Date();
    var today = _rdvDateToStr(now);
    var mondayDay = new Date(monday);
    var mondayStr = _rdvDateToStr(mondayDay);
    var sunday = new Date(mondayDay);
    sunday.setDate(sunday.getDate() + 6);
    var sundayStr = _rdvDateToStr(sunday);
    if (today < mondayStr || today > sundayStr) return;

    var dayOffset = Math.floor((new Date(today) - new Date(mondayStr)) / 86400000);
    if (dayOffset < 0 || dayOffset > 6) return;
    var minutes = now.getHours() * 60 + now.getMinutes();
    var bounds = getPlanningBounds();
    var startMin = timeToMinutes(bounds.start);
    var endMin = timeToMinutes(bounds.end);
    if (minutes < startMin || minutes > endMin) return;

    var bodyStyles = window.getComputedStyle(grid);
    var cols = bodyStyles.gridTemplateColumns.split(' ').filter(Boolean);
    if (cols.length < 8) return;
    var timeColWidth = parseFloat(cols[0]) || 80;
    var dayColWidth = (grid.clientWidth - timeColWidth) / 7;
    var y = Math.max(0, Math.round(((minutes - startMin) / APP._planningSlotMinutes) * APP._planningSlotPx));
    var x = timeColWidth + (dayOffset * dayColWidth);

    var line = document.createElement('div');
    line.className = 'planning-now-line';
    line.style.top = y + 'px';
    line.style.left = x + 'px';
    line.style.width = dayColWidth + 'px';
    line.innerHTML = '<span class="planning-now-time">' + formatTime(now.toTimeString()) + '</span>';
    grid.appendChild(line);
}

function getRdvDurationMinutes(rdv) {
    if (!rdv) return 60;
    var candidates = [
        rdv.temps_estime_minutes,
        rdv.duree_minutes,
        rdv.temps_estime,
        rdv.duration_minutes,
        rdv.duration
    ];
    for (var i = 0; i < candidates.length; i++) {
        var minutes = parseDurationToMinutes(candidates[i]);
        if (minutes > 0) return Math.max(30, minutes);
    }
    return 60;
}

function parseDurationToMinutes(value) {
    if (value === null || value === undefined) return 0;
    if (typeof value === 'number' && isFinite(value)) {
        if (value <= 0) return 0;
        // Heuristic: small values are likely hours, larger values are minutes.
        return value <= 12 ? Math.round(value * 60) : Math.round(value);
    }
    var raw = String(value).trim().toLowerCase();
    if (!raw) return 0;
    if (/^\d{1,2}:\d{2}$/.test(raw)) {
        var hm = raw.split(':');
        return parseInt(hm[0], 10) * 60 + parseInt(hm[1], 10);
    }
    var hMatch = raw.match(/(\d+(?:[.,]\d+)?)\s*h/);
    var mMatch = raw.match(/(\d+(?:[.,]\d+)?)\s*m/);
    if (hMatch || mMatch) {
        var h = hMatch ? parseFloat(hMatch[1].replace(',', '.')) : 0;
        var m = mMatch ? parseFloat(mMatch[1].replace(',', '.')) : 0;
        return Math.round((h * 60) + m);
    }
    var num = parseFloat(raw.replace(',', '.'));
    if (!isNaN(num) && num > 0) {
        return num <= 12 ? Math.round(num * 60) : Math.round(num);
    }
    return 0;
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
    apiGet('/api/rendez-vous').then(function(r) { return r.json(); }).then(function(rdvs) {
        renderOrdresReparation(rdvs);
    }).catch(function(e) { console.error('Erreur OR:', e); });
    pollTravauxSupp();
    setTimeout(function() { renderTravauxSuppPanel(); }, 500);
}

function renderOrdresReparation(rdvs) {
    var container = document.getElementById('or-list');
    var enCours = rdvs.filter(function(r) { return r.statut !== 'annule' && r.statut !== 'non_presente'; });
    document.getElementById('or-ouverts').textContent = enCours.filter(function(r) { return r.statut !== 'termine' && r.statut !== 'facture' && r.statut !== 'paye'; }).length + ' ouverts';
    document.getElementById('or-termines').textContent = enCours.filter(function(r) { return r.statut === 'termine' || r.statut === 'facture' || r.statut === 'paye'; }).length + ' termines';

    enCours.sort(function(a, b) {
        var order = { 'en_cours': 0, 'reception': 1, 'confirme': 2, 'reserve': 3, 'en_attente': 4, 'termine': 5, 'facture': 6, 'paye': 7, 'non_presente': 8 };
        return (order[a.statut] || 9) - (order[b.statut] || 9);
    });

    var etapes = ['Reception', 'Diagnostic', 'Intervention', 'Controle QC', 'Livraison'];
    var html = '';
    enCours.slice(0, 30).forEach(function(rdv) {
        var meca = rdv.mecanicien || APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
        var mecaNom = meca ? meca.prenom + ' ' + escapeHtml(meca.nom) : '-';
        var mecaCouleur = meca ? meca.couleur : '#666';
        var pont = rdv.pont || APP.ponts.find(function(p) { return p.id === rdv.pont_id; });
        var v = rdv.vehicule || {}; var c = rdv.client || {};
        var duree = rdv.temps_estime ? Math.round(rdv.temps_estime / 60 * 10) / 10 + 'h' : '-';
        var isTermine = rdv.statut === 'termine' || rdv.statut === 'facture' || rdv.statut === 'paye';
        var currentIdx = getEtapeIndex(rdv.statut);
        var dateCreation = rdv.date_rdv || '';
        var year = dateCreation ? dateCreation.substring(0, 4) : new Date().getFullYear();
        var orNum = 'OR-' + year + '-' + String(rdv.id).padStart(3, '0');

        // Progress bar 5 etapes
        var stepsHtml = '<div style="display:flex;gap:4px;margin-top:12px">';
        etapes.forEach(function(label, i) {
            var bg, txtCol;
            if (i < currentIdx) { bg = '#22C55E'; txtCol = '#fff'; }
            else if (i === currentIdx && !isTermine) { bg = '#E8480A'; txtCol = '#fff'; }
            else if (isTermine) { bg = '#22C55E'; txtCol = '#fff'; }
            else { bg = '#2a2a2e'; txtCol = '#666'; }
            var radius = '';
            if (i === 0) radius = 'border-radius:6px 0 0 6px;';
            if (i === etapes.length - 1) radius = 'border-radius:0 6px 6px 0;';
            stepsHtml += '<div style="flex:1;text-align:center;padding:6px 4px;font-size:10px;font-weight:600;letter-spacing:.3px;background:' + bg + ';color:' + txtCol + ';' + radius + '">' + label + '</div>';
        });
        stepsHtml += '</div>';

        // OR Card redesigned
        html += '<div class="or-card" style="cursor:pointer;' + (isTermine ? 'opacity:.65' : '') + '" onclick="showOrDetail(' + rdv.id + ')">' +
            '<div class="or-header" style="display:flex;align-items:center;gap:12px;margin-bottom:10px">' +
                '<div class="or-num" style="font-family:Barlow Condensed,sans-serif;font-size:20px;font-weight:700;letter-spacing:.5px">' + orNum + '</div>' +
                statusBadge(rdv.statut) +
                '<div style="margin-left:auto;display:flex;align-items:center;gap:8px">' +
                    '<span style="font-size:12px;color:#777">' + dateCreation + ' ' + formatTime(rdv.heure_rdv) + '</span>' +
                    actionButtons(rdv, true, { hideBillingActions: true }) +
                '</div>' +
            '</div>' +
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;font-size:13px;color:#ccc">' +
                '<div style="display:flex;align-items:center;gap:6px"><span style="font-size:16px">&#128690;</span> <b style="color:#fff">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</b> <span style="color:#888">' + (escapeHtml(v.plaque) || '') + '</span></div>' +
                '<div style="display:flex;align-items:center;gap:6px"><span style="font-size:16px">&#128100;</span> ' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + (escapeHtml(c.telephone) ? ' <span style="color:#888">- ' + c.telephone + '</span>' : '') + '</div>' +
                '<div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:50%;background:' + mecaCouleur + ';display:inline-block"></span> ' + mecaNom + '</div>' +
                '<div style="display:flex;align-items:center;gap:6px"><span style="font-size:16px">&#128295;</span> ' + (escapeHtml(rdv.type_intervention) || '-') + ' <span style="color:#888">(' + duree + ')</span></div>' +
            '</div>' +
            stepsHtml +
        '</div>';
    });
    container.innerHTML = html || '<div style="color:#666;padding:20px">Aucun OR</div>';
}

function getEtapeIndex(statut) {
    // 5 etapes: Reception(0), Diagnostic(1), Intervention(2), Controle QC(3), Livraison(4)
    var map = { 'reserve': 0, 'en_attente': 0, 'confirme': 0, 'reception': 0, 'en_cours': 2, 'termine': 4, 'facture': 4, 'paye': 4, 'non_presente': 4 };
    return map[statut] !== undefined ? map[statut] : 0;
}

function showOrDetail(rdvId) {
    apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
        var v = rdv.vehicule || {}; var c = rdv.client || {};
        var meca = rdv.mecanicien || APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
        var mecaNom = meca ? meca.prenom + ' ' + escapeHtml(meca.nom) : '-';
        var pont = rdv.pont || APP.ponts.find(function(p) { return p.id === rdv.pont_id; });
        var duree = rdv.temps_estime ? Math.round(rdv.temps_estime / 60 * 10) / 10 + 'h' : '-';
        var year = rdv.date_rdv ? rdv.date_rdv.substring(0, 4) : new Date().getFullYear();
        var orNum = 'OR-' + year + '-' + String(rdv.id).padStart(3, '0');
        var currentIdx = getEtapeIndex(rdv.statut);
        var isTermine = rdv.statut === 'termine' || rdv.statut === 'facture' || rdv.statut === 'paye';
        var etapes = ['Reception', 'Diagnostic', 'Intervention', 'Controle QC', 'Livraison'];

        var stepsHtml = '<div style="display:flex;gap:4px;margin:16px 0">';
        etapes.forEach(function(label, i) {
            var bg, txtCol;
            if (i < currentIdx) { bg = '#22C55E'; txtCol = '#fff'; }
            else if (i === currentIdx && !isTermine) { bg = '#E8480A'; txtCol = '#fff'; }
            else if (isTermine) { bg = '#22C55E'; txtCol = '#fff'; }
            else { bg = '#2a2a2e'; txtCol = '#666'; }
            var radius = '';
            if (i === 0) radius = 'border-radius:6px 0 0 6px;';
            if (i === etapes.length - 1) radius = 'border-radius:0 6px 6px 0;';
            stepsHtml += '<div style="flex:1;text-align:center;padding:8px 4px;font-size:11px;font-weight:600;background:' + bg + ';color:' + txtCol + ';' + radius + '">' + label + '</div>';
        });
        stepsHtml += '</div>';

        // OR supplementaires
        var orSuppHtml = '';
        if (rdv.ordres_reparation && rdv.ordres_reparation.length > 0) {
            orSuppHtml = '<div style="margin-top:16px;border-top:1px solid #333;padding-top:12px"><div style="font-weight:600;margin-bottom:8px">Ordres de reparation associes</div>';
            rdv.ordres_reparation.forEach(function(or) {
                var badge = or.type_or === 'supplementaire' ? '<span style="background:#F26524;color:#fff;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:600;margin-left:6px">Supp.</span>' : '';
                orSuppHtml += '<div style="padding:8px;background:#1a1a1d;border-radius:8px;margin-bottom:6px">' +
                    '<div style="font-weight:600">' + (escapeHtml(or.numero_or) || orNum) + badge + '</div>' +
                    (escapeHtml(or.travaux) ? '<div style="font-size:12px;color:#aaa;margin-top:4px">' + or.travaux + '</div>' : '') +
                '</div>';
            });
            orSuppHtml += '</div>';
        }

        var overlay = document.createElement('div');
        overlay.className = 'modal-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;z-index:1000';
        overlay.onclick = function(e) { if (e.target === overlay) overlay.remove(); };
        overlay.innerHTML =
            '<div style="background:#1e1e22;border:1px solid #333;border-radius:16px;padding:28px;width:600px;max-width:90vw;max-height:85vh;overflow-y:auto;color:#eee">' +
                '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">' +
                    '<div style="font-family:Barlow Condensed,sans-serif;font-size:26px;font-weight:700">' + orNum + '</div>' +
                    statusBadge(rdv.statut) +
                    '<button onclick="this.closest(\'.modal-overlay\').remove()" style="background:none;border:none;color:#888;font-size:22px;cursor:pointer">&times;</button>' +
                '</div>' +
                stepsHtml +
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;font-size:14px">' +
                    '<div style="background:#16161a;border-radius:10px;padding:14px">' +
                        '<div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:6px;font-weight:600">Vehicule</div>' +
                        '<div style="font-weight:600">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div>' +
                        '<div style="color:#aaa">' + (escapeHtml(v.plaque) || '') + '</div>' +
                    '</div>' +
                    '<div style="background:#16161a;border-radius:10px;padding:14px">' +
                        '<div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:6px;font-weight:600">Client</div>' +
                        '<div style="font-weight:600">' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + '</div>' +
                        '<div style="color:#aaa">' + (escapeHtml(c.telephone) || '') + '</div>' +
                    '</div>' +
                    '<div style="background:#16161a;border-radius:10px;padding:14px">' +
                        '<div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:6px;font-weight:600">Intervention</div>' +
                        '<div style="font-weight:600">' + (escapeHtml(rdv.type_intervention) || '-') + '</div>' +
                        '<div style="color:#aaa">Duree estimee: ' + duree + '</div>' +
                        '<div style="color:#ffd700;font-weight:600;margin-top:4px">' + (rdv.prix_final || rdv.prix_estime || 0) + ' EUR</div>' +
                    '</div>' +
                    '<div style="background:#16161a;border-radius:10px;padding:14px">' +
                        '<div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:6px;font-weight:600">Assignation</div>' +
                        '<div style="font-weight:600">' + mecaNom + '</div>' +
                        '<div style="color:#aaa">' + (pont ? pont.nom : '-') + '</div>' +
                    '</div>' +
                '</div>' +
                (escapeHtml(rdv.notes) ? '<div style="margin-top:14px;padding:12px;background:#16161a;border-radius:10px;font-size:13px;color:#aaa"><span style="font-weight:600;color:#ccc">Notes: </span>' + rdv.notes + '</div>' : '') +
                orSuppHtml +
                '<div style="display:flex;gap:8px;margin-top:20px">' +
                    '<button onclick="telechargerOR(' + rdv.id + ')" style="flex:1;padding:10px;background:#E8480A;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:Barlow,sans-serif">Telecharger PDF</button>' +
                    '<button onclick="this.closest(\'.modal-overlay\').remove();planifierRdvSuite(' + rdv.id + ')" style="flex:1;padding:10px;background:#3B82F6;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:Barlow,sans-serif">Planifier RDV de suite</button>' +
                    '<button onclick="this.closest(\'.modal-overlay\').remove()" style="flex:1;padding:10px;background:#333;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:Barlow,sans-serif">Fermer</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);
    }).catch(function(e) { console.error('Erreur detail OR:', e); });
}

function planifierRdvSuite(rdvId) {
    apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
        var v = rdv.vehicule || {}; var c = rdv.client || {};
        showSection('rdv');
        setTimeout(function() {
            var plaqueInput = document.getElementById('rdv-plaque');
            if (plaqueInput) {
                plaqueInput.value = v.plaque || '';
                searchMotoRdv(v.plaque || '');
            }
            var commentInput = document.getElementById('rdv-comment');
            if (commentInput) commentInput.value = 'Suite du RDV #' + rdvId + ' - ';
            var clientName = document.getElementById('rdv-client-name');
            if (clientName) clientName.value = (escapeHtml(c.nom) || '') + ' ' + (escapeHtml(c.prenom) || '');
            var clientTel = document.getElementById('rdv-client-tel');
            if (clientTel) clientTel.value = c.telephone || '';
        }, 300);
    }).catch(function(e) { console.error('Erreur RDV suite:', e); });
}

// ===== RECEPTION VEHICULE =====
var ETAT_VEHICULE_POINTS = [
    { key: 'carrosserie_ok', label: 'Carrosserie OK' },
    { key: 'rayures', label: 'Rayures visibles' },
    { key: 'bosses', label: 'Bosses / chocs' },
    { key: 'freins_ok', label: 'Freins (impression)' },
    { key: 'pneus_av_ok', label: 'Pneu avant OK' },
    { key: 'pneus_ar_ok', label: 'Pneu arriere OK' },
    { key: 'eclairage_ok', label: 'Eclairage fonctionne' },
    { key: 'retros_ok', label: 'Retroviseurs OK' },
    { key: 'clignotants_ok', label: 'Clignotants OK' },
    { key: 'compteur_ok', label: 'Tableau de bord OK' },
    { key: 'fuite_visible', label: 'Fuite visible' },
    { key: 'accessoires', label: 'Accessoires notes' }
];

var _receptionSignatureCtx = null;
var _receptionSignatureDrawing = false;
var _receptionSignatureHasData = false;

function ouvrirReception(rdvId) {
    apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
        var html = '';
        var v = rdv.vehicule || {};
        var c = rdv.client || {};

        html += '<div style="background:#1e1e1e;border:1px solid #333;border-radius:8px;padding:12px;margin-bottom:16px;display:flex;gap:16px">'
            + '<div><div style="font-size:11px;color:#666">Client</div><div style="color:#eee;font-weight:600">' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + '</div></div>'
            + '<div><div style="font-size:11px;color:#666">Vehicule</div><div style="color:#eee;font-weight:600">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div></div>'
            + '<div><div style="font-size:11px;color:#666">Plaque</div><div style="color:#eee;font-weight:600">' + (escapeHtml(v.plaque) || '') + '</div></div>'
            + '</div>';

        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Kilometrage *</label>'
            + '<input type="number" id="reception-km" class="form-input" placeholder="Ex: 15000" value="' + (rdv.kilometrage || '') + '"></div>';

        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Etat du vehicule</label>'
            + '<div class="reception-check-grid" id="reception-etat-checks">';
        ETAT_VEHICULE_POINTS.forEach(function(pt) {
            html += '<label class="reception-check-item"><input type="checkbox" value="' + pt.key + '"> ' + pt.label + '</label>';
        });
        html += '</div></div>';

        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Observations</label>'
            + '<textarea id="reception-obs" class="form-input" rows="3" placeholder="Notes sur l\'etat general..."></textarea></div>';

        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Signature client *</label>'
            + '<canvas id="reception-signature-canvas" width="400" height="150" style="border:1px solid #444;border-radius:6px;background:#fff;cursor:crosshair;display:block;width:100%;touch-action:none"></canvas>'
            + '<button class="btn btn-ghost" style="margin-top:6px;font-size:11px" onclick="clearReceptionSignature()">Effacer signature</button></div>';

        html += '<button class="btn btn-primary" style="width:100%;margin-top:12px;background:var(--teal)" onclick="validerReception(' + rdvId + ')">Valider la reception</button>';

        showModal('Reception - OR-' + String(rdvId).padStart(6, '0'), html, '600px');
        setTimeout(function() { initReceptionSignaturePad(); }, 100);
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function initReceptionSignaturePad() {
    var canvas = document.getElementById('reception-signature-canvas');
    if (!canvas) return;
    var rect = canvas.getBoundingClientRect();
    var dpr = window.devicePixelRatio || 1;
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    var ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, rect.width, rect.height);
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    _receptionSignatureCtx = ctx;
    _receptionSignatureHasData = false;

    canvas.addEventListener('mousedown', function(e) {
        _receptionSignatureDrawing = true;
        var coords = getCanvasCoords(canvas, e);
        ctx.beginPath(); ctx.moveTo(coords.x, coords.y);
    });
    canvas.addEventListener('mousemove', function(e) {
        if (!_receptionSignatureDrawing) return;
        var coords = getCanvasCoords(canvas, e);
        ctx.lineTo(coords.x, coords.y); ctx.stroke();
        _receptionSignatureHasData = true;
    });
    canvas.addEventListener('mouseup', function() { _receptionSignatureDrawing = false; });
    canvas.addEventListener('mouseout', function() { _receptionSignatureDrawing = false; });
    canvas.addEventListener('touchstart', function(e) {
        e.preventDefault(); _receptionSignatureDrawing = true;
        var coords = getCanvasCoords(canvas, e.touches[0]);
        ctx.beginPath(); ctx.moveTo(coords.x, coords.y);
    });
    canvas.addEventListener('touchmove', function(e) {
        e.preventDefault();
        if (!_receptionSignatureDrawing) return;
        var coords = getCanvasCoords(canvas, e.touches[0]);
        ctx.lineTo(coords.x, coords.y); ctx.stroke();
        _receptionSignatureHasData = true;
    });
    canvas.addEventListener('touchend', function() { _receptionSignatureDrawing = false; });
}

function getCanvasCoords(canvas, event) {
    var rect = canvas.getBoundingClientRect();
    return { x: event.clientX - rect.left, y: event.clientY - rect.top };
}

function clearReceptionSignature() {
    var canvas = document.getElementById('reception-signature-canvas');
    if (!canvas) return;
    var rect = canvas.getBoundingClientRect();
    _receptionSignatureCtx.fillStyle = '#ffffff';
    _receptionSignatureCtx.fillRect(0, 0, rect.width, rect.height);
    _receptionSignatureHasData = false;
}

function getReceptionSignatureBase64() {
    if (!_receptionSignatureHasData) return null;
    var canvas = document.getElementById('reception-signature-canvas');
    return canvas ? canvas.toDataURL('image/png') : null;
}

function validerReception(rdvId) {
    var km = document.getElementById('reception-km').value;
    if (!km) { alert('Kilometrage obligatoire'); return; }

    var etatItems = [];
    var checkboxes = document.querySelectorAll('#reception-etat-checks input[type="checkbox"]');
    for (var i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) etatItems.push(checkboxes[i].value);
    }
    var observations = document.getElementById('reception-obs').value || '';
    var etatVehicule = JSON.stringify({ points: etatItems, observations: observations });

    var signatureData = getReceptionSignatureBase64();
    if (!signatureData) { alert('Signature client obligatoire'); return; }

    apiPost('/api/rendez-vous/' + rdvId + '/ordre-reparation/save', {
        kilometrage: parseInt(km),
        etat_vehicule: etatVehicule,
        travaux: observations,
        signature: signatureData
    }).then(function(r) {
        return r.json();
    }).then(function() {
        return apiPost('/api/rendez-vous/' + rdvId + '/reception', {});
    }).then(function() {
        closeModal();
        showNotificationToast('Reception validee - OR disponible');
        refreshCurrentSection();
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

// ===== TRAVAUX SUPPLEMENTAIRES =====
function ouvrirDemandeTravauxSupp(rdvId) {
    window._tsSelectedPrestations = [];
    var prestations = APP.prestationsConfig || [];
    var categories = {};
    prestations.forEach(function(p) {
        if (!p.is_active) return;
        var cat = p.categorie || 'Autre';
        if (!categories[cat]) categories[cat] = [];
        categories[cat].push(p);
    });

    var html = '<div style="margin-bottom:14px;font-size:13px;color:#aaa">Selectionnez les interventions necessaires. Le receptionniste fera le devis.</div>';

    html += '<div style="max-height:45vh;overflow-y:auto;margin-bottom:14px">';
    Object.keys(categories).sort().forEach(function(cat) {
        html += '<div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.6px;font-weight:700;margin:10px 0 6px;padding:0 2px">' + escapeHtml(cat) + '</div>';
        categories[cat].forEach(function(p) {
            html += '<label id="ts-presta-' + p.id + '" style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#1a1a22;border:1px solid #333;border-radius:8px;margin-bottom:6px;cursor:pointer;-webkit-tap-highlight-color:transparent" onclick="toggleTsPrestation(' + p.id + ',\'' + escapeHtml(p.code || '') + '\',\'' + escapeHtml((p.nom || '').replace(/'/g, "")) + '\')">' +
                '<div id="ts-check-' + p.id + '" style="width:22px;height:22px;border:2px solid #555;border-radius:4px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:14px;color:#22c55e"></div>' +
                '<div style="flex:1;min-width:0"><div style="font-size:14px;color:#eee;font-weight:500">' + escapeHtml(p.nom) + '</div>' +
                '<div style="font-size:12px;color:#777">' + escapeHtml(p.code || '') + (p.temps_estime_minutes ? ' \u2022 ~' + p.temps_estime_minutes + ' min' : '') + '</div></div>' +
                '</label>';
        });
    });
    html += '</div>';

    html += '<div class="form-group"><label class="form-label" style="color:#ccc">Notes / Description du probleme</label>'
        + '<textarea id="travaux-supp-desc" class="form-input" rows="2" placeholder="Decrire ce que vous avez constate..." style="font-size:14px"></textarea></div>';

    html += '<div class="form-group"><label class="form-label" style="color:#ccc">Urgence</label>'
        + '<div style="display:flex;gap:8px">'
        + '<button id="ts-urg-normal" class="btn btn-ghost" style="flex:1;padding:10px;font-size:13px;border-color:var(--green);color:var(--green)" onclick="setTsUrgence(\'normal\')">Normal</button>'
        + '<button id="ts-urg-urgent" class="btn btn-ghost" style="flex:1;padding:10px;font-size:13px" onclick="setTsUrgence(\'urgent\')">Urgent</button>'
        + '<button id="ts-urg-critique" class="btn btn-ghost" style="flex:1;padding:10px;font-size:13px" onclick="setTsUrgence(\'critique\')">Critique</button>'
        + '</div></div>';

    html += '<div id="ts-selected-count" style="font-size:13px;color:#888;margin-bottom:8px"></div>';
    html += '<button class="btn btn-primary" style="width:100%;padding:14px;font-size:16px;font-weight:700;margin-top:4px" onclick="envoyerDemandeTravauxSupp(' + rdvId + ')">Envoyer au receptionniste</button>';

    window._tsUrgence = 'normal';
    showModal('Signaler un probleme - OR #' + rdvId, html, '520px');
    updateTsSelectedCount();
}

function toggleTsPrestation(id, code, nom) {
    if (!window._tsSelectedPrestations) window._tsSelectedPrestations = [];
    var idx = window._tsSelectedPrestations.findIndex(function(p) { return p.prestation_id === id; });
    var checkEl = document.getElementById('ts-check-' + id);
    var labelEl = document.getElementById('ts-presta-' + id);
    if (idx >= 0) {
        window._tsSelectedPrestations.splice(idx, 1);
        if (checkEl) checkEl.textContent = '';
        if (labelEl) labelEl.style.borderColor = '#333';
    } else {
        window._tsSelectedPrestations.push({ prestation_id: id, code: code, nom: nom });
        if (checkEl) checkEl.textContent = '\u2713';
        if (labelEl) labelEl.style.borderColor = 'var(--green)';
    }
    updateTsSelectedCount();
}

function updateTsSelectedCount() {
    var el = document.getElementById('ts-selected-count');
    var n = (window._tsSelectedPrestations || []).length;
    if (el) el.textContent = n > 0 ? n + ' prestation' + (n > 1 ? 's' : '') + ' selectionnee' + (n > 1 ? 's' : '') : '';
}

function setTsUrgence(val) {
    window._tsUrgence = val;
    ['normal','urgent','critique'].forEach(function(u) {
        var btn = document.getElementById('ts-urg-' + u);
        if (!btn) return;
        if (u === val) {
            var colors = { normal: 'var(--green)', urgent: 'var(--amber)', critique: 'var(--red)' };
            btn.style.borderColor = colors[u]; btn.style.color = colors[u];
        } else {
            btn.style.borderColor = '#444'; btn.style.color = '#888';
        }
    });
}

function envoyerDemandeTravauxSupp(rdvId) {
    var selected = window._tsSelectedPrestations || [];
    var desc = (document.getElementById('travaux-supp-desc') || {}).value || '';
    if (!selected.length && !desc.trim()) { alert('Selectionnez au moins une prestation ou decrivez le probleme'); return; }
    var data = {
        prestations_demandees: selected,
        description: desc,
        urgence: window._tsUrgence || 'normal'
    };
    apiPost('/api/rendez-vous/' + rdvId + '/travaux-supplementaires', data).then(function(r) {
        return r.json();
    }).then(function() {
        closeModal();
        showNotificationToast('Demande envoyee au receptionniste');
        refreshCurrentSection();
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

// ===== POLLING TRAVAUX SUPP =====
function pollTravauxSupp() {
    var role = APP.currentUser ? APP.currentUser.role : '';
    if (role !== 'admin' && role !== 'receptionnaire') return;

    apiGet('/api/travaux-supplementaires/en-attente').then(function(r) { return r.json(); }).then(function(demandes) {
        var count = Array.isArray(demandes) ? demandes.length : 0;
        var badge = document.getElementById('travaux-supp-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'flex';
                badge.style.animation = 'pulse-badge 1s infinite';
            } else {
                badge.style.display = 'none';
                badge.style.animation = '';
            }
        }
        if (count > APP._lastTravauxSuppCount && APP._lastTravauxSuppCount >= 0) {
            // Show modal alert for new demandes
            var newDemandes = demandes.slice(0, count - APP._lastTravauxSuppCount);
            newDemandes.forEach(function(d) {
                showTravauxSuppAlert(d);
            });
            playAlertSound();
        }
        APP._lastTravauxSuppCount = count;
        APP._pendingTravauxSupp = demandes;
    }).catch(function(e) {
        if (e && /403|401/.test(String(e.message || ''))) return;
    });
}

function playAlertSound() {
    try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 800;
        osc.type = 'sine';
        gain.gain.value = 0.3;
        osc.start();
        osc.stop(ctx.currentTime + 0.15);
        setTimeout(function() {
            var osc2 = ctx.createOscillator();
            var gain2 = ctx.createGain();
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.frequency.value = 1000;
            osc2.type = 'sine';
            gain2.gain.value = 0.3;
            osc2.start();
            osc2.stop(ctx.currentTime + 0.2);
        }, 200);
    } catch(e) {}
}

function showTravauxSuppAlert(demande) {
    var d = demande;
    var c = d.client || {};
    var v = d.vehicule || {};
    var prestas = d.prestations_demandees || [];
    var overlay = document.createElement('div');
    overlay.className = 'travaux-alert-overlay';
    overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.75);display:flex;align-items:center;justify-content:center;z-index:10000;animation:fadeIn .2s;padding:16px';

    var prestaHtml = '';
    if (prestas.length) {
        prestaHtml = '<div style="margin-bottom:12px"><div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;font-weight:700">Prestations demandees par le technicien</div>';
        prestas.forEach(function(p) {
            var presta = (APP.prestationsConfig || []).find(function(x) { return x.id === p.prestation_id; });
            var prix = presta ? (presta.prix_base_ttc || 0) : 0;
            var temps = presta ? (presta.temps_estime_minutes || 0) : 0;
            prestaHtml += '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:#1a1a22;border:1px solid #333;border-radius:6px;margin-bottom:4px">' +
                '<div><div style="font-size:13px;color:#eee;font-weight:500">' + escapeHtml(p.nom || '') + '</div>' +
                '<div style="font-size:11px;color:#777">' + escapeHtml(p.code || '') + (temps ? ' \u2022 ~' + temps + ' min' : '') + '</div></div>' +
                '<div style="font-size:14px;font-weight:700;color:var(--orange)">' + (prix > 0 ? prix.toFixed(2) + ' \u20AC' : '-') + '</div></div>';
        });
        prestaHtml += '</div>';
    }

    overlay.innerHTML =
        '<div style="background:#1e1e22;border:2px solid #E8480A;border-radius:16px;padding:24px;width:560px;max-width:95vw;color:#eee;box-shadow:0 20px 60px rgba(232,72,10,.3);max-height:90vh;overflow-y:auto">' +
            '<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">' +
                '<div style="width:40px;height:40px;border-radius:50%;background:rgba(232,72,10,.15);display:flex;align-items:center;justify-content:center;font-size:20px">&#9888;</div>' +
                '<div><div style="font-family:Barlow Condensed,sans-serif;font-size:20px;font-weight:700">Demande travaux supplementaires</div>' +
                '<div style="font-size:12px;color:#888">' + (escapeHtml(d.or_numero) || 'OR #' + d.rendez_vous_id) + '</div></div>' +
                '<span class="badge ' + (d.urgence === 'critique' ? 'red' : d.urgence === 'urgent' ? 'amber' : 'blue') + '" style="font-size:12px;padding:3px 12px;margin-left:auto">' + (d.urgence || 'normal') + '</span>' +
            '</div>' +
            '<div style="display:flex;gap:12px;font-size:13px;color:#aaa;margin-bottom:14px;flex-wrap:wrap">' +
                '<div>&#128100; ' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + '</div>' +
                '<div>&#128690; ' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div>' +
            '</div>' +
            prestaHtml +
            (d.description ? '<div style="background:#16161a;border-radius:8px;padding:12px;margin-bottom:14px;font-size:13px;color:#ddd;line-height:1.5"><div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;font-weight:700">Notes du technicien</div>' + escapeHtml(d.description) + '</div>' : '') +
            '<div style="background:#16161a;border-radius:8px;padding:14px;margin-bottom:14px">' +
                '<div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;font-weight:700">Devis receptionniste</div>' +
                '<div style="display:flex;gap:10px;margin-bottom:10px">' +
                    '<div style="flex:1"><label style="font-size:12px;color:#888;display:block;margin-bottom:3px">Prix TTC</label>' +
                    '<input type="number" id="ts-devis-prix-' + d.id + '" class="form-input" step="0.01" placeholder="0.00" style="font-size:16px;padding:10px"></div>' +
                    '<div style="flex:1"><label style="font-size:12px;color:#888;display:block;margin-bottom:3px">Temps (min)</label>' +
                    '<input type="number" id="ts-devis-temps-' + d.id + '" class="form-input" placeholder="60" style="font-size:16px;padding:10px"></div>' +
                '</div>' +
                '<label style="font-size:12px;color:#888;display:block;margin-bottom:3px">Notes pour le client</label>' +
                '<input type="text" id="travaux-alert-notes-' + d.id + '" class="form-input" placeholder="Explication pour le client..." style="font-size:14px;padding:10px">' +
            '</div>' +
            '<div style="display:flex;gap:10px">' +
                '<button onclick="traiterAlertTravaux(' + d.id + ', \'approuve\', this)" style="flex:1;padding:14px;background:#22C55E;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:15px;cursor:pointer;font-family:Barlow,sans-serif">Faire signer le client</button>' +
                '<button onclick="traiterAlertTravaux(' + d.id + ', \'refuse\', this)" style="flex:1;padding:14px;background:#EF4444;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:15px;cursor:pointer;font-family:Barlow,sans-serif">Refuser</button>' +
            '</div>' +
        '</div>';
    document.body.appendChild(overlay);
}

function traiterAlertTravaux(demandeId, statut, btn) {
    var notes = document.getElementById('travaux-alert-notes-' + demandeId);
    var notesVal = notes ? notes.value : '';
    var prixEl = document.getElementById('ts-devis-prix-' + demandeId);
    var tempsEl = document.getElementById('ts-devis-temps-' + demandeId);
    var prixDevis = prixEl ? parseFloat(prixEl.value) || null : null;
    var tempsDevis = tempsEl ? parseInt(tempsEl.value) || null : null;
    btn.disabled = true;
    btn.textContent = '...';
    if (statut === 'approuve') {
        var overlay = btn.closest('.travaux-alert-overlay');
        if (overlay) overlay.remove();
        ouvrirSignatureTravauxSupp(demandeId, notesVal, prixDevis, tempsDevis);
    } else {
        apiPut('/api/travaux-supplementaires/' + demandeId, { statut: statut, notes_receptionniste: notesVal }).then(function() {
            var overlay = btn.closest('.travaux-alert-overlay');
            if (overlay) overlay.remove();
            showNotificationToast('Demande refusee');
            pollTravauxSupp();
            setTimeout(function() { renderTravauxSuppPanel(); }, 500);
        }).catch(function(e) {
            btn.disabled = false;
            btn.textContent = 'Refuser';
            alert('Erreur: ' + e.message);
        });
    }
}

// ===== SIGNATURE TRAVAUX SUPP =====
var _tsSignatureCtx = null;
var _tsSignatureDrawing = false;
var _tsSignatureHasData = false;

function ouvrirSignatureTravauxSupp(demandeId, notes, prixDevis, tempsDevis) {
    var html = '<div style="margin-bottom:16px;font-size:13px;color:#aaa">Le client doit signer pour approuver les travaux supplementaires.</div>' +
        '<div class="form-group"><label class="form-label" style="color:#ccc">Signature client *</label>' +
        '<canvas id="ts-signature-canvas" width="400" height="150" style="border:1px solid #444;border-radius:6px;background:#fff;cursor:crosshair;display:block;width:100%;touch-action:none"></canvas>' +
        '<button class="btn btn-ghost" style="margin-top:6px;font-size:11px" onclick="clearTsSignature()">Effacer signature</button></div>' +
        '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="confirmerTravauxSuppAvecSignature(' + demandeId + ',' + (prixDevis || 'null') + ',' + (tempsDevis || 'null') + ',\'' + (notes || '').replace(/'/g, "\\'") + '\')">Confirmer et approuver</button>';
    showModal('Signature client - Travaux supplementaires', html, '480px');
    setTimeout(function() { initTsSignaturePad(); }, 100);
}

function initTsSignaturePad() {
    var canvas = document.getElementById('ts-signature-canvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var rect = canvas.getBoundingClientRect();
    canvas.width = rect.width; canvas.height = rect.height;
    ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, rect.width, rect.height);
    ctx.strokeStyle = '#000'; ctx.lineWidth = 2; ctx.lineCap = 'round';
    _tsSignatureCtx = ctx; _tsSignatureHasData = false;
    canvas.addEventListener('mousedown', function(e) { _tsSignatureDrawing = true; ctx.beginPath(); ctx.moveTo(e.offsetX, e.offsetY); });
    canvas.addEventListener('mousemove', function(e) { if (!_tsSignatureDrawing) return; ctx.lineTo(e.offsetX, e.offsetY); ctx.stroke(); _tsSignatureHasData = true; });
    canvas.addEventListener('mouseup', function() { _tsSignatureDrawing = false; });
    canvas.addEventListener('mouseout', function() { _tsSignatureDrawing = false; });
    canvas.addEventListener('touchstart', function(e) { e.preventDefault(); _tsSignatureDrawing = true; var t = e.touches[0]; var r = canvas.getBoundingClientRect(); ctx.beginPath(); ctx.moveTo(t.clientX - r.left, t.clientY - r.top); });
    canvas.addEventListener('touchmove', function(e) { e.preventDefault(); if (!_tsSignatureDrawing) return; var t = e.touches[0]; var r = canvas.getBoundingClientRect(); ctx.lineTo(t.clientX - r.left, t.clientY - r.top); ctx.stroke(); _tsSignatureHasData = true; });
    canvas.addEventListener('touchend', function() { _tsSignatureDrawing = false; });
}

function clearTsSignature() {
    var canvas = document.getElementById('ts-signature-canvas');
    if (!canvas || !_tsSignatureCtx) return;
    var rect = canvas.getBoundingClientRect();
    _tsSignatureCtx.fillStyle = '#ffffff';
    _tsSignatureCtx.fillRect(0, 0, rect.width, rect.height);
    _tsSignatureHasData = false;
}

function confirmerTravauxSuppAvecSignature(demandeId, prixDevis, tempsDevis, notes) {
    if (!_tsSignatureHasData) { alert('Signature client obligatoire'); return; }
    var canvas = document.getElementById('ts-signature-canvas');
    var signatureData = canvas ? canvas.toDataURL('image/png') : null;

    apiPut('/api/travaux-supplementaires/' + demandeId, {
        statut: 'approuve',
        notes_receptionniste: notes,
        prix_estime: prixDevis,
        temps_estime: tempsDevis,
        signature: signatureData
    }).then(function() {
        closeModal();
        showNotificationToast('Travaux approuves avec signature - OR supplementaire cree');
        pollTravauxSupp();
        setTimeout(function() { renderTravauxSuppPanel(); }, 500);
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function showNotificationToast(message) {
    showToast(message, 'success');
    updateLiveRegion(message);
}

// ===== APPROBATION TRAVAUX SUPP (dans section OR) =====
function renderTravauxSuppPanel() {
    var container = document.getElementById('travaux-supp-panel');
    if (!container) return;
    var demandes = APP._pendingTravauxSupp || [];
    if (demandes.length === 0) {
        container.innerHTML = '';
        container.style.display = 'none';
        return;
    }
    container.style.display = 'block';
    var html = '<div style="font-size:14px;font-weight:600;color:var(--orange);margin-bottom:12px">Demandes de travaux en attente (' + demandes.length + ')</div>';
    demandes.forEach(function(d) {
        var c = d.client || {};
        var v = d.vehicule || {};
        var urgCls = d.urgence === 'critique' ? ' critique' : '';
        html += '<div class="travaux-supp-card' + urgCls + '">'
            + '<div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">'
            + '<div><span class="badge ' + (escapeHtml(d.urgence) === 'critique' ? 'red' : d.urgence === 'urgent' ? 'amber' : 'blue') + '">' + d.urgence + '</span> '
            + '<span style="font-size:13px;color:var(--orange);font-weight:600">' + (escapeHtml(d.or_numero) || '') + '</span></div>'
            + '<span style="font-size:11px;color:#666">' + (d.created_at || '').substring(0, 16).replace('T', ' ') + '</span></div>'
            + '<div style="font-size:13px;color:#eee;margin-bottom:8px">' + (escapeHtml(d.description) || '') + '</div>'
            + '<div style="display:flex;gap:12px;font-size:12px;color:#888;margin-bottom:10px">'
            + '<span>' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + '</span>'
            + '<span>' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</span>';
        if (d.temps_estime) html += '<span>' + d.temps_estime + ' min</span>';
        if (d.prix_estime) html += '<span>' + d.prix_estime + ' EUR</span>';
        html += '</div>'
            + '<div style="display:flex;gap:8px">'
            + '<button class="btn btn-primary" style="font-size:11px;padding:4px 10px;background:var(--green)" onclick="approuverTravauxSupp(' + d.id + ')">Approuver</button>'
            + '<button class="btn btn-ghost" style="font-size:11px;padding:4px 10px;color:var(--red)" onclick="refuserTravauxSupp(' + d.id + ')">Refuser</button>'
            + '</div></div>';
    });
    container.innerHTML = html;
}

function approuverTravauxSupp(demandeId) {
    var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Notes pour le client (optionnel)</label>' +
        '<textarea id="travaux-supp-notes" class="form-input" rows="3" placeholder="Notes..."></textarea></div>' +
        '<div style="display:flex;gap:8px;margin-top:10px">' +
        '<button class="btn btn-ghost" style="flex:1" onclick="closeModal()">Annuler</button>' +
        '<button class="btn btn-primary" style="flex:1;background:var(--green)" onclick="confirmerApprouverTravauxSupp(' + demandeId + ')">Approuver</button>' +
        '</div>';
    showModal('Approuver travaux supplementaires', html, '450px');
}

function confirmerApprouverTravauxSupp(demandeId) {
    var notesEl = document.getElementById('travaux-supp-notes');
    var notes = notesEl ? notesEl.value : '';
    apiPut('/api/travaux-supplementaires/' + demandeId, { statut: 'approuve', notes_receptionniste: notes || null }).then(function(r) {
        return r.json();
    }).then(function() {
        closeModal();
        showNotificationToast('Travaux supplementaires approuves - OR supplementaire cree');
        pollTravauxSupp();
        setTimeout(function() { renderTravauxSuppPanel(); }, 500);
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function refuserTravauxSupp(demandeId) {
    var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Raison du refus</label>' +
        '<textarea id="travaux-supp-refus" class="form-input" rows="3" placeholder="Saisir une raison..."></textarea></div>' +
        '<div style="display:flex;gap:8px;margin-top:10px">' +
        '<button class="btn btn-ghost" style="flex:1" onclick="closeModal()">Annuler</button>' +
        '<button class="btn btn-primary" style="flex:1;background:var(--red)" onclick="confirmerRefuserTravauxSupp(' + demandeId + ')">Refuser</button>' +
        '</div>';
    showModal('Refuser travaux supplementaires', html, '450px');
}

function confirmerRefuserTravauxSupp(demandeId) {
    var notesEl = document.getElementById('travaux-supp-refus');
    var notes = notesEl ? notesEl.value : '';
    if (!notes || !notes.trim()) {
        showAlert('Veuillez saisir une raison de refus', 'warning');
        return;
    }
    apiPut('/api/travaux-supplementaires/' + demandeId, { statut: 'refuse', notes_receptionniste: notes || null }).then(function(r) {
        return r.json();
    }).then(function() {
        closeModal();
        showNotificationToast('Demande refusee');
        pollTravauxSupp();
        setTimeout(function() { renderTravauxSuppPanel(); }, 500);
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

// ===== SUIVI LIVE =====
function loadSuiviLive() {
    var today = new Date().toISOString().split('T')[0];
    apiGet('/api/rendez-vous?date=' + today).then(function(r) { return r.json(); }).then(function(rdvs) {
        renderSuiviLive(rdvs);
    }).catch(function(e) { console.error('Erreur suivi:', e); });
}

function renderSuiviLive(rdvs) {
    var container = document.getElementById('suivi-grid');
    var alertStrip = document.getElementById('suivi-alert-strip');
    var byMeca = {};
    var now = new Date();
    var delayCount = 0;
    var soonCount = 0;
    var enCoursCount = 0;
    var delayItems = [];

    function getRdvProgressInfo(rdv) {
        var estimated = getRdvDurationMinutes(rdv);
        var elapsedMin = 0;
        if (rdv.heure_debut_travail) {
            var start = parseUTCDate(rdv.heure_debut_travail);
            if (!isNaN(start.getTime())) elapsedMin = Math.max(0, Math.floor((now - start) / 60000));
        }
        var progress = estimated > 0 ? Math.round((elapsedMin / estimated) * 100) : 0;
        if (rdv.statut === 'termine' || rdv.statut === 'facture' || rdv.statut === 'paye') progress = 100;
        var overrun = rdv.statut === 'en_cours' && elapsedMin > estimated;
        return {
            estimated: estimated,
            elapsedMin: elapsedMin,
            progress: Math.max(0, Math.min(130, progress)),
            overrun: overrun,
            remaining: Math.max(0, estimated - elapsedMin)
        };
    }

    function getRdvDelayInfo(rdv) {
        var s = rdv.statut;
        var startMin = timeToMinutes(formatTime(rdv.heure_rdv || ''));
        var nowMin = now.getHours() * 60 + now.getMinutes();
        var started = !!rdv.heure_debut_travail || s === 'en_cours' || s === 'termine' || s === 'facture' || s === 'paye';
        if (startMin >= 0 && !started) {
            if (nowMin > startMin + 10) return { level: 'delay', minutes: nowMin - startMin };
            if (nowMin >= startMin - 10 && nowMin <= startMin + 10) return { level: 'soon', minutes: Math.abs(startMin - nowMin) };
        }
        return null;
    }

    rdvs.forEach(function(rdv) {
        var mid = rdv.mecanicien_id || 0;
        if (!byMeca[mid]) byMeca[mid] = [];
        byMeca[mid].push(rdv);

        var d = getRdvDelayInfo(rdv);
        if (d && d.level === 'delay') {
            delayCount += 1;
            var c = rdv.client || {};
            var v = rdv.vehicule || {};
            delayItems.push(formatTime(rdv.heure_rdv) + ' - ' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + ' (' + (escapeHtml(c.nom) || 'Client') + ') +' + d.minutes + 'm');
        } else if (d && d.level === 'soon') {
            soonCount += 1;
        }
        if (rdv.statut === 'en_cours') enCoursCount += 1;
    });

    if (alertStrip) {
        var stripHtml = '<span class="chip">En cours: ' + enCoursCount + '</span>' +
            '<span class="chip orange">Demarrages imminents: ' + soonCount + '</span>' +
            '<span class="chip red">Retards: ' + delayCount + '</span>';
        if (delayItems.length) {
            stripHtml += '<div class="suivi-alert-list"><strong>Alertes retard RDV:</strong><br>' + delayItems.slice(0, 4).join('<br>') + (delayItems.length > 4 ? '<br>... +' + (delayItems.length - 4) + ' autre(s)' : '') + '</div>';
        }
        alertStrip.innerHTML = stripHtml;
    }

    var html = '';

    if (byMeca[0] && byMeca[0].length > 0) {
        html += '<div class="card"><div class="meca-header" style="margin-bottom:10px"><div class="meca-av" style="background:rgba(245,158,11,.15);color:var(--amber);width:36px;height:36px;font-size:13px">?</div><div class="meca-info"><div class="meca-name">Non assigne</div><div class="meca-role">' + byMeca[0].length + ' RDV(s)</div></div><span class="badge amber">A assigner</span></div><div class="timeline">';
        byMeca[0].sort(function(a, b) { return (a.heure_rdv || '').localeCompare(b.heure_rdv || ''); });
        byMeca[0].forEach(function(rdv) {
            var dotCls = rdv.statut === 'termine' ? 'done' : (rdv.statut === 'en_cours' ? 'active' : '');
            html += '<div class="tl-item"><div class="tl-dot ' + dotCls + '"></div><div class="tl-content"><div style="display:flex;justify-content:space-between;align-items:center"><div class="tl-title">' + (escapeHtml(rdv.type_intervention) || '') + '</div>' + actionButtons(rdv, true) + '</div><div class="tl-meta">' + formatTime(rdv.heure_rdv) + ' - ' + statusBadge(rdv.statut) + '</div></div></div>';
        });
        html += '</div></div>';
    }

    Object.keys(byMeca).forEach(function(mecaId) {
        if (parseInt(mecaId) === 0) return;
        var mecaRdvs = byMeca[mecaId];
        var meca = APP.mecaniciens.find(function(m) { return m.id === parseInt(mecaId); });
        if (!meca) return;
        var color = meca.couleur || '#3b82f6';
        var initials = getMecaInitials(meca);
        var currentRdv = mecaRdvs.find(function(r) { return r.statut === 'en_cours'; });
        var pont = currentRdv ? APP.ponts.find(function(p) { return p.id === currentRdv.pont_id; }) : null;
        var currentVeh = currentRdv && currentRdv.vehicule ? (currentRdv.vehicule.marque || '') + ' ' + (currentRdv.vehicule.modele || '') : '';
        var roleText = (pont ? pont.nom : '') + (currentVeh ? ' • ' + currentVeh : '');

        html += '<div class="card"><div class="meca-header" style="margin-bottom:10px"><div class="meca-av" style="background:' + hexToRgba(color, 0.2) + ';color:' + color + ';width:36px;height:36px;font-size:13px">' + initials + '</div><div class="meca-info"><div class="meca-name">' + escapeHtml(meca.prenom).charAt(0) + '. ' + escapeHtml(meca.nom) + '</div><div class="meca-role">' + roleText + '</div></div>' + (currentRdv ? '<span class="badge orange">En cours</span>' : '<span class="badge green">Disponible</span>') + '</div>';

        if (currentRdv) {
            var info = getRdvProgressInfo(currentRdv);
            var progressColor = info.overrun ? '#ef4444' : color;
            var progressLabel = info.overrun ? ('Retard +' + (info.elapsedMin - info.estimated) + ' min') : (info.remaining + ' min restantes');
            html += '<div style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;font-size:11px;color:#777;margin-bottom:4px"><span>' + (currentRdv.type_intervention || '') + '</span><span>' + Math.min(100, info.progress) + '%</span></div><div class="pont-progress"><div class="pont-progress-fill" style="width:' + Math.min(100, info.progress) + '%;background:' + progressColor + '"></div></div><div style="font-size:11px;color:' + (info.overrun ? '#fca5a5' : '#9ca3af') + ';margin-top:4px">Temps reel: ' + info.elapsedMin + 'm / estime ' + info.estimated + 'm - ' + progressLabel + '</div></div>';
        }

        html += '<div class="timeline">';
        mecaRdvs.sort(function(a, b) { return (a.heure_rdv || '').localeCompare(b.heure_rdv || ''); });
        mecaRdvs.forEach(function(rdv) {
            var dotCls = rdv.statut === 'termine' ? 'done' : (rdv.statut === 'en_cours' ? 'active' : '');
            var v = rdv.vehicule || {};
            var vName = (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '');
            var delay = getRdvDelayInfo(rdv);
            var delayBadge = '';
            if (delay && delay.level === 'delay') delayBadge = ' <span class="badge red">Retard +' + delay.minutes + 'm</span>';
            else if (delay && delay.level === 'soon') delayBadge = ' <span class="badge amber">Demarrage imminent</span>';
            html += '<div class="tl-item"><div class="tl-dot ' + dotCls + '"></div><div class="tl-content"><div style="display:flex;justify-content:space-between;align-items:center"><div class="tl-title">' + (escapeHtml(rdv.type_intervention) || '') + delayBadge + '</div>' + actionButtons(rdv, true) + '</div><div class="tl-meta">' + formatTime(rdv.heure_rdv) + ' • ' + vName.trim() + ' • ' + statusBadge(rdv.statut) + '</div></div></div>';
        });
        html += '</div></div>';
    });

    container.innerHTML = html || '<div style="color:#666;padding:20px">Aucune intervention en cours</div>';
}

// ===== CHECKUP / RAPPORT TECHNICIEN =====
var CHECKUP_POINTS = [
    { key: 'niveau_huile', label: 'Niveau huile' },
    { key: 'pression_pneus', label: 'Pression pneus' },
    { key: 'freins_avant', label: 'Freins avant' },
    { key: 'freins_arriere', label: 'Freins arriere' },
    { key: 'eclairage', label: 'Eclairage' },
    { key: 'clignotants', label: 'Clignotants' },
    { key: 'batterie', label: 'Batterie' },
    { key: 'chaine_courroie', label: 'Chaine / Courroie' },
    { key: 'liquide_refroidissement', label: 'Liquide refroidissement' },
    { key: 'filtre_air', label: 'Filtre a air' }
];

function ouvrirCheckup(rdvId) {
    // Charger rapport existant
    apiGet('/api/rendez-vous/' + rdvId + '/rapport-technicien').then(function(r) { return r.json(); }).then(function(rapport) {
        renderCheckupModal(rdvId, rapport);
    }).catch(function() {
        renderCheckupModal(rdvId, null);
    });
}

function renderCheckupModal(rdvId, rapport) {
    var points = (rapport && rapport.points_controle) ? rapport.points_controle : {};
    var alertes = (rapport && rapport.alertes) ? rapport.alertes : '';
    var recommandations = (rapport && rapport.recommandations) ? rapport.recommandations : '';
    var travaux = (rapport && rapport.travaux_realises) ? rapport.travaux_realises : '';

    // Init checkup points from existing rapport
    window._checkupPoints = {};
    CHECKUP_POINTS.forEach(function(pt) {
        window._checkupPoints[pt.key] = points[pt.key] || 'non_verifie';
    });

    var html = '<div style="margin-bottom:16px;font-size:12px;color:#888">RDV #' + rdvId + ' - Points de controle</div>';

    // Points de controle
    html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">';
    CHECKUP_POINTS.forEach(function(pt) {
        var val = points[pt.key] || 'non_verifie';
        var okSel = val === 'ok' ? 'background:var(--green);color:white;' : '';
        var nokSel = val === 'nok' ? 'background:var(--red);color:white;' : '';
        var nvSel = val === 'non_verifie' ? 'background:#444;color:white;' : '';
        html += '<div style="background:#252525;border:1px solid #333;border-radius:8px;padding:8px 12px;display:flex;align-items:center;justify-content:space-between">' +
            '<span style="font-size:13px;color:#ccc">' + pt.label + '</span>' +
            '<div style="display:flex;gap:4px">' +
                '<button onclick="setCheckpoint(\'' + pt.key + '\',\'ok\',this)" style="border:none;border-radius:4px;padding:3px 8px;cursor:pointer;font-size:11px;font-weight:600;' + okSel + '">OK</button>' +
                '<button onclick="setCheckpoint(\'' + pt.key + '\',\'nok\',this)" style="border:none;border-radius:4px;padding:3px 8px;cursor:pointer;font-size:11px;font-weight:600;' + nokSel + '">NOK</button>' +
                '<button onclick="setCheckpoint(\'' + pt.key + '\',\'non_verifie\',this)" style="border:none;border-radius:4px;padding:3px 8px;cursor:pointer;font-size:11px;font-weight:600;' + nvSel + '">-</button>' +
            '</div>' +
        '</div>';
    });
    html += '</div>';

    // Alertes
    html += '<div class="form-group"><label class="form-label" style="color:#ccc">Alertes / Problemes detectes</label>' +
        '<textarea id="checkup-alertes" class="form-input" rows="2" placeholder="Problemes constates...">' + alertes + '</textarea></div>';

    // Recommandations
    html += '<div class="form-group"><label class="form-label" style="color:#ccc">Recommandations</label>' +
        '<textarea id="checkup-recommandations" class="form-input" rows="2" placeholder="Recommandations client...">' + recommandations + '</textarea></div>';

    // Travaux
    html += '<div class="form-group"><label class="form-label" style="color:#ccc">Travaux effectues</label>' +
        '<textarea id="checkup-travaux" class="form-input" rows="2" placeholder="Detail des travaux...">' + travaux + '</textarea></div>';

    // Boutons
    html += '<div style="display:flex;gap:8px;margin-top:16px">' +
        '<button class="btn btn-primary" style="flex:1" onclick="sauverCheckup(' + rdvId + ',\'en_cours\')">Sauvegarder (en cours)</button>' +
        '<button class="btn btn-primary" style="flex:1;background:var(--green)" onclick="sauverCheckup(' + rdvId + ',\'termine\')">Terminer le rapport</button>' +
        '</div>';

    showModal('Rapport Technicien - Checkup', html, '650px');
}

function setCheckpoint(key, value, btn) {
    // Update visual state
    var container = btn.parentElement;
    var buttons = container.querySelectorAll('button');
    buttons.forEach(function(b) { b.style.background = ''; b.style.color = ''; });
    if (value === 'ok') { btn.style.background = 'var(--green)'; btn.style.color = 'white'; }
    else if (value === 'nok') { btn.style.background = 'var(--red)'; btn.style.color = 'white'; }
    else { btn.style.background = '#444'; btn.style.color = 'white'; }

    // Store in data attribute
    if (!window._checkupPoints) window._checkupPoints = {};
    window._checkupPoints[key] = value;
}

function sauverCheckup(rdvId, statut) {
    var points = window._checkupPoints || {};

    var data = {
        points_controle: points,
        alertes: document.getElementById('checkup-alertes') ? document.getElementById('checkup-alertes').value : '',
        recommandations: document.getElementById('checkup-recommandations') ? document.getElementById('checkup-recommandations').value : '',
        travaux_realises: document.getElementById('checkup-travaux') ? document.getElementById('checkup-travaux').value : '',
        statut: statut
    };

    apiPost('/api/rendez-vous/' + rdvId + '/rapport-technicien', data).then(function(r) { return r.json(); }).then(function() {
        closeModal();
        window._checkupPoints = {};
        alert('Rapport sauvegarde avec succes');
        refreshCurrentSection();
    }).catch(function(e) {
        alert('Erreur sauvegarde: ' + e.message);
    });
}

// ===== ESPACE MECANICIEN =====

function loadEspaceMeca() {
    var today = new Date().toISOString().split('T')[0];
    apiGet('/api/rendez-vous?date=' + today).then(function(r) { return r.json(); }).then(function(rdvs) {
        APP._mecaLastRdvs = rdvs || [];
        var meca = null;
        if (APP.currentUser) {
            meca = APP.mecaniciens.find(function(m) { return m.user_id && m.user_id === APP.currentUser.id; }) || null;
            if (!meca) {
                var username = (APP.currentUser.username || '').toLowerCase();
                meca = APP.mecaniciens.find(function(m) {
                    var full = ((m.prenom || '') + '.' + (m.nom || '')).toLowerCase();
                    return full === username;
                }) || null;
            }
        }
        if (!meca && APP.currentUser && APP.currentUser.role !== 'mecanicien') meca = APP.mecaniciens[0] || null;
        if (!meca) return;
        APP._currentMeca = meca;
        var isAdmin = APP.currentUser && (APP.currentUser.role === 'admin' || APP.currentUser.role === 'manager');
        var mecaRdvs = isAdmin ? rdvs : rdvs.filter(function(r) { return r.mecanicien_id === meca.id; });
        APP._mecaCheckupData = null;
        renderEspaceMeca(meca, rdvs);
    }).catch(function(e) { console.error('Erreur espace meca:', e); });
}

function renderEspaceMeca(meca, allRdvs) {
    var color = meca.couleur || '#8b5cf6';
    var initials = getMecaInitials(meca);
    var isAdmin = APP.currentUser && (APP.currentUser.role === 'admin' || APP.currentUser.role === 'manager');
    var mecaRdvs = isAdmin ? allRdvs : allRdvs.filter(function(r) { return r.mecanicien_id === meca.id; });
    mecaRdvs.sort(function(a, b) { return (a.heure_rdv || '').localeCompare(b.heure_rdv || ''); });
    var now = new Date();
    var activeRdv = mecaRdvs.find(function(r) { return r.statut === 'en_cours'; });
    var aFaire = mecaRdvs.filter(function(r) { return r.statut === 'reserve' || r.statut === 'confirme' || r.statut === 'reception'; });
    var termines = mecaRdvs.filter(function(r) { return r.statut === 'termine' || r.statut === 'facture' || r.statut === 'paye'; });
    var enRetard = aFaire.filter(function(r) {
        var hm = String(r.heure_rdv || '').split(':');
        if (hm.length < 2) return false;
        var d = new Date(now.getFullYear(), now.getMonth(), now.getDate(), parseInt(hm[0], 10) || 0, parseInt(hm[1], 10) || 0, 0, 0);
        return d.getTime() + 10 * 60 * 1000 < now.getTime();
    });
    aFaire.sort(function(a, b) {
        var aLate = enRetard.indexOf(a) !== -1 ? 1 : 0;
        var bLate = enRetard.indexOf(b) !== -1 ? 1 : 0;
        if (aLate !== bLate) return bLate - aLate;
        return (a.heure_rdv || '').localeCompare(b.heure_rdv || '');
    });
    var nextAction = aFaire.length ? aFaire[0] : null;

    var header = document.getElementById('espace-meca-header');
    if (header) {
        header.innerHTML = '<div class="meca-av-big" style="background:' + hexToRgba(color, 0.3) + ';color:' + color + '">' + initials + '</div>' +
            '<div style="flex:1"><div class="meca-greeting">Bonjour ' + escapeHtml(meca.prenom) + '</div>' +
            '<div class="meca-sub">' + mecaRdvs.length + ' interventions aujourd\'hui • ' + enRetard.length + ' en retard</div></div>';
    }

    var container = document.getElementById('meca-rdv-list');
    if (!container) return;
    var html = '';

    if (!activeRdv && nextAction) {
        html += '<div class="meca-active-panel" style="border:1px solid rgba(245,158,11,.5);margin-bottom:12px">' +
            '<div style="font-size:12px;color:#fbbf24;font-weight:700;margin-bottom:6px">A FAIRE MAINTENANT</div>' +
            '<div style="display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap">' +
            '<div><div style="font-size:16px;font-weight:700;color:#fff">' + formatTime(nextAction.heure_rdv) + ' - ' + escapeHtml((nextAction.vehicule && nextAction.vehicule.marque) || '') + ' ' + escapeHtml((nextAction.vehicule && nextAction.vehicule.modele) || '') + '</div>' +
            '<div style="font-size:12px;color:#aaa">' + escapeHtml(nextAction.type_intervention || '-') + '</div></div>' +
            '<button class="meca-big-btn start" onclick="demarrerTravail(' + nextAction.id + ')">▶ DEMARRER</button></div></div>';
    }

    if (activeRdv) {
        html += renderMecaActivePanel(activeRdv);
    }

    if (aFaire.length) {
        html += '<div class="meca-section-title">\u25B6 A faire (' + aFaire.length + ')</div>';
        aFaire.forEach(function(rdv) { html += renderMecaCard(rdv, 'todo'); });
    }

    if (termines.length) {
        html += '<div class="meca-section-title">\u2705 Termines (' + termines.length + ')</div>';
        termines.forEach(function(rdv) { html += renderMecaCard(rdv, 'done'); });
    }

    if (!activeRdv && !aFaire.length && !termines.length) {
        html = '<div style="text-align:center;padding:40px 16px;color:#666">' +
            '<div style="font-size:48px;margin-bottom:12px">\uD83C\uDFCD\uFE0F</div>' +
            '<div style="font-size:16px">Pas d\'intervention aujourd\'hui</div></div>';
    }

    container.innerHTML = html;
    if (activeRdv) startMecaLiveTimer(activeRdv);
    else cleanupMecaTimer();
}

function renderMecaActivePanel(rdv) {
    var v = rdv.vehicule || {};
    var c = rdv.client || {};
    var pont = APP.ponts.find(function(p) { return p.id === rdv.pont_id; });
    var rapport = APP._mecaCheckupData;
    var points = (rapport && rapport.points_controle) ? rapport.points_controle : {};

    window._checkupPoints = {};
    CHECKUP_POINTS.forEach(function(pt) { window._checkupPoints[pt.key] = points[pt.key] || 'non_verifie'; });
    var checked = CHECKUP_POINTS.filter(function(pt) { return points[pt.key] && points[pt.key] !== 'non_verifie'; }).length;

    var html = '<div class="meca-active-panel" id="meca-active-panel">';

    // Big chrono
    html += '<div class="meca-chrono">' +
        '<div id="meca-live-clock" class="meca-chrono-time">00:00:00</div>' +
        '<div id="meca-live-eta" class="meca-chrono-eta">Chrono en attente...</div>' +
        '<div class="meca-chrono-bar"><div id="meca-progress-bar" class="meca-chrono-fill" style="width:0%"></div></div></div>';

    // Vehicle + client info
    html += '<div class="meca-info-block">' +
        '<div class="meca-info-vehicle">\uD83C\uDFCD\uFE0F ' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div>' +
        '<div class="meca-info-client">\uD83D\uDC64 ' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + (pont ? ' \u2022 ' + escapeHtml(pont.nom) : '') + '</div>' +
        '<div class="meca-info-type">\uD83D\uDD27 ' + (escapeHtml(rdv.type_intervention) || '') + '</div>' +
        (rdv.notes ? '<div class="meca-info-notes">\uD83D\uDCDD ' + escapeHtml(rdv.notes) + '</div>' : '') + '</div>';

    // Inline checkup (collapsible)
    html += '<div class="meca-checkup-block">' +
        '<div class="meca-checkup-header" onclick="toggleMecaCheckup()">' +
        '<span>\u2611\uFE0F Checkup (' + checked + '/' + CHECKUP_POINTS.length + ')</span>' +
        '<span id="meca-checkup-arrow" class="meca-checkup-arrow">\u25BC</span></div>' +
        '<div id="meca-checkup-body" class="meca-checkup-body" style="display:none">';

    CHECKUP_POINTS.forEach(function(pt) {
        var val = points[pt.key] || 'non_verifie';
        html += '<div class="meca-check-item">' +
            '<span class="meca-check-label">' + pt.label + '</span>' +
            '<div class="meca-check-btns">' +
            '<button class="meca-check-btn' + (val === 'ok' ? ' ok' : '') + '" onclick="setMecaCheck(\'' + pt.key + '\',\'ok\',this)">OK</button>' +
            '<button class="meca-check-btn' + (val === 'nok' ? ' nok' : '') + '" onclick="setMecaCheck(\'' + pt.key + '\',\'nok\',this)">NOK</button></div></div>';
    });

    html += '<div style="margin-top:10px"><textarea id="meca-notes" class="meca-notes-input" rows="2" placeholder="Notes, alertes...">' +
        escapeHtml((rapport && rapport.alertes) || '') + '</textarea></div></div></div>';

    // Big action buttons
    html += '<div class="meca-actions">' +
        '<button class="meca-big-btn warning" onclick="ouvrirDemandeTravauxSupp(' + rdv.id + ')">\u26A0\uFE0F Signaler probleme</button>' +
        '<button class="meca-big-btn ghost" onclick="telechargerOR(' + rdv.id + ')">\uD83D\uDCC4 Voir OR</button>' +
        '<button class="meca-big-btn success" onclick="terminerTravail(' + rdv.id + ')">\u2705 TERMINER</button></div>';

    html += '</div>';
    return html;
}

function renderMecaCard(rdv, type) {
    var v = rdv.vehicule || {};
    var c = rdv.client || {};
    var pont = APP.ponts.find(function(p) { return p.id === rdv.pont_id; });
    var dur = getRdvDurationMinutes(rdv);
    var durStr = dur >= 60 ? (Math.round(dur / 6) / 10) + 'h' : dur + 'min';

    var html = '<div class="meca-rdv-card ' + type + '">';
    html += '<div class="meca-card-time">' + formatTime(rdv.heure_rdv) + '</div>';
    html += '<div class="meca-card-body">' +
        '<div class="meca-card-vehicle">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div>' +
        '<div class="meca-card-client">' + (escapeHtml(c.prenom) || '').charAt(0) + '. ' + (escapeHtml(c.nom) || '') + (pont ? ' \u2022 ' + escapeHtml(pont.nom) : '') + '</div>' +
        '<div class="meca-card-type">' + (escapeHtml(rdv.type_intervention) || '') + ' \u2022 ' + durStr + '</div></div>';

    if (type === 'todo') {
        html += '<button class="meca-big-btn start" onclick="event.stopPropagation();demarrerTravail(' + rdv.id + ')">\u25B6 DEMARRER</button>';
    } else {
        html += '<div class="meca-card-actions">' +
            '<button class="meca-sm-btn" onclick="telechargerOR(' + rdv.id + ')">OR</button>' +
            '<button class="meca-sm-btn" onclick="ouvrirCheckup(' + rdv.id + ')">Rapport</button></div>';
    }
    html += '</div>';
    return html;
}

function toggleMecaCheckup() {
    var body = document.getElementById('meca-checkup-body');
    var arrow = document.getElementById('meca-checkup-arrow');
    if (!body) return;
    var show = body.style.display === 'none';
    body.style.display = show ? 'block' : 'none';
    if (arrow) arrow.textContent = show ? '\u25B2' : '\u25BC';
}

function setMecaCheck(key, value, btn) {
    if (!window._checkupPoints) window._checkupPoints = {};
    if (window._checkupPoints[key] === value) {
        window._checkupPoints[key] = 'non_verifie';
        btn.classList.remove('ok', 'nok');
    } else {
        window._checkupPoints[key] = value;
        var btns = btn.parentElement.querySelectorAll('.meca-check-btn');
        btns.forEach(function(b) { b.classList.remove('ok', 'nok'); });
        btn.classList.add(value);
    }
    var checked = 0;
    CHECKUP_POINTS.forEach(function(pt) {
        if (window._checkupPoints[pt.key] && window._checkupPoints[pt.key] !== 'non_verifie') checked++;
    });
    var hdr = document.querySelector('.meca-checkup-header span');
    if (hdr) hdr.textContent = '\u2611\uFE0F Checkup (' + checked + '/' + CHECKUP_POINTS.length + ')';
}

function startMecaLiveTimer(rdv) {
    cleanupMecaTimer();
    function tick() {
        var clockEl = document.getElementById('meca-live-clock');
        var etaEl = document.getElementById('meca-live-eta');
        var barEl = document.getElementById('meca-progress-bar');
        if (!clockEl) return;
        var startAt = parseUTCDate(rdv && rdv.heure_debut_travail ? rdv.heure_debut_travail : null);
        if (!startAt || isNaN(startAt.getTime())) {
            clockEl.textContent = '00:00:00';
            if (etaEl) etaEl.textContent = 'En attente de demarrage';
            return;
        }
        var elapsed = Math.max(0, Math.floor((Date.now() - startAt.getTime()) / 1000));
        var h = Math.floor(elapsed / 3600);
        var m = Math.floor((elapsed % 3600) / 60);
        var s = elapsed % 60;
        clockEl.textContent = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        var totalMin = getRdvDurationMinutes(rdv);
        var elapsedMin = Math.floor(elapsed / 60);
        var remaining = totalMin - elapsedMin;
        var pct = totalMin > 0 ? Math.min(100, Math.round((elapsedMin / totalMin) * 100)) : 0;
        if (etaEl) {
            if (remaining > 0) {
                etaEl.textContent = '~ ' + remaining + ' min restantes';
                etaEl.style.color = pct > 80 ? '#fbbf24' : '#9ca3af';
            } else {
                etaEl.textContent = 'Depassement +' + Math.abs(remaining) + ' min';
                etaEl.style.color = '#ef4444';
            }
        }
        if (barEl) {
            barEl.style.width = Math.min(pct, 100) + '%';
            barEl.style.background = pct > 100 ? '#ef4444' : (pct > 80 ? '#fbbf24' : '#22c55e');
        }
    }
    tick();
    APP._mecaLiveTimer = setInterval(tick, 1000);
}

function cleanupMecaTimer() {
    if (APP._mecaLiveTimer) { clearInterval(APP._mecaLiveTimer); APP._mecaLiveTimer = null; }
}

// ===== GESTION ABSENCES =====
function loadAbsences() {
    apiGet('/api/absences').then(function(r) { return r.json(); }).then(function(absences) {
        APP._absences = absences;
        renderAbsencesTable(absences);
    }).catch(function() {
        var container = document.getElementById('absences-table');
        if (container) container.innerHTML = '<tr><td colspan="6" style="color:#666">Erreur chargement</td></tr>';
    });
}

function renderAbsencesTable(absences) {
    var container = document.getElementById('absences-table');
    var countEl = document.getElementById('absences-count');
    if (countEl) countEl.textContent = absences.length;
    if (!container) return;
    if (!absences.length) {
        container.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#666;padding:20px">Aucune absence planifiee</td></tr>';
        return;
    }
    var html = '';
    absences.forEach(function(a) {
        var motifMap = { 'conge': { cls: 'green', label: 'Conge' }, 'maladie': { cls: 'red', label: 'Maladie' }, 'formation': { cls: 'blue', label: 'Formation' }, 'autre': { cls: 'amber', label: 'Autre' } };
        var motif = motifMap[a.motif] || { cls: 'blue', label: a.motif };
        html += '<tr>' +
            '<td><b>' + (escapeHtml(a.mecanicien_prenom) || '') + ' ' + (escapeHtml(a.mecanicien_nom) || '') + '</b></td>' +
            '<td>' + a.date_debut + '</td>' +
            '<td>' + a.date_fin + '</td>' +
            '<td><span class="badge ' + motif.cls + '">' + motif.label + '</span></td>' +
            '<td style="color:#888">' + (escapeHtml(a.notes) || '-') + '</td>' +
            '<td><div style="display:flex;gap:6px"><button class="btn btn-ghost" style="font-size:11px;padding:3px 8px;color:var(--teal)" onclick="ouvrirModalEditAbsence(' + a.id + ')">Editer</button><button class="btn btn-ghost" style="font-size:11px;padding:3px 8px;color:var(--red)" onclick="supprimerAbsence(' + a.id + ')">Supprimer</button></div></td>' +
            '</tr>';
    });
    container.innerHTML = html;
}

function ouvrirModalAbsence() {
    var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Mecanicien</label><select id="abs-meca" class="form-select">';
    APP.mecaniciens.forEach(function(m) {
        if (!isActive(m)) return;
        html += '<option value="' + m.id + '">' + escapeHtml(m.prenom) + ' ' + escapeHtml(m.nom) + '</option>';
    });
    html += '</select></div>' +
        '<div style="display:flex;gap:12px"><div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Date debut</label><input type="date" id="abs-debut" class="form-input"></div>' +
        '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Date fin</label><input type="date" id="abs-fin" class="form-input"></div></div>' +
        '<div class="form-group"><label class="form-label" style="color:#ccc">Motif</label><select id="abs-motif" class="form-select"><option value="conge">Conge</option><option value="maladie">Maladie</option><option value="formation">Formation</option><option value="autre">Autre</option></select></div>' +
        '<div class="form-group"><label class="form-label" style="color:#ccc">Notes</label><textarea id="abs-notes" class="form-input" rows="2" placeholder="Optionnel..."></textarea></div>' +
        '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="sauverAbsence()">Enregistrer l\'absence</button>';
    showModal('Ajouter une absence', html, '450px');
}

function sauverAbsence() {
    var data = {
        mecanicien_id: parseInt(document.getElementById('abs-meca').value),
        date_debut: document.getElementById('abs-debut').value,
        date_fin: document.getElementById('abs-fin').value,
        motif: document.getElementById('abs-motif').value,
        notes: document.getElementById('abs-notes').value || null
    };
    if (!data.date_debut || !data.date_fin) { alert('Dates obligatoires'); return; }
    apiPost('/api/absences', data).then(function(r) { return r.json(); }).then(function() {
        closeModal();
        loadAbsences();
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function ouvrirModalEditAbsence(absenceId) {
    var abs = (APP._absences || []).find(function(a) { return a.id === absenceId; });
    if (!abs) {
        showAlert('Absence introuvable', 'error');
        return;
    }
    var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Mecanicien</label><select id="abs-edit-meca" class="form-select">';
    APP.mecaniciens.forEach(function(m) {
        if (!isActive(m)) return;
        html += '<option value="' + m.id + '"' + (m.id === abs.mecanicien_id ? ' selected' : '') + '>' + escapeHtml(m.prenom) + ' ' + escapeHtml(m.nom) + '</option>';
    });
    html += '</select></div>' +
        '<div style="display:flex;gap:12px"><div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Date debut</label><input type="date" id="abs-edit-debut" class="form-input" value="' + abs.date_debut + '"></div>' +
        '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Date fin</label><input type="date" id="abs-edit-fin" class="form-input" value="' + abs.date_fin + '"></div></div>' +
        '<div class="form-group"><label class="form-label" style="color:#ccc">Motif</label><select id="abs-edit-motif" class="form-select">' +
        '<option value="conge"' + (abs.motif === 'conge' ? ' selected' : '') + '>Conge</option>' +
        '<option value="maladie"' + (abs.motif === 'maladie' ? ' selected' : '') + '>Maladie</option>' +
        '<option value="formation"' + (abs.motif === 'formation' ? ' selected' : '') + '>Formation</option>' +
        '<option value="autre"' + (abs.motif === 'autre' ? ' selected' : '') + '>Autre</option></select></div>' +
        '<div class="form-group"><label class="form-label" style="color:#ccc">Notes</label><textarea id="abs-edit-notes" class="form-input" rows="2" placeholder="Optionnel...">' + (escapeHtml(abs.notes) || '') + '</textarea></div>' +
        '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="sauverEditAbsence(' + absenceId + ')">Mettre a jour</button>';
    showModal('Modifier absence', html, '450px');
}

function sauverEditAbsence(absenceId) {
    var data = {
        mecanicien_id: parseInt(document.getElementById('abs-edit-meca').value),
        date_debut: document.getElementById('abs-edit-debut').value,
        date_fin: document.getElementById('abs-edit-fin').value,
        motif: document.getElementById('abs-edit-motif').value,
        notes: document.getElementById('abs-edit-notes').value || null
    };
    if (!data.date_debut || !data.date_fin) {
        showAlert('Dates obligatoires', 'warning');
        return;
    }
    apiPut('/api/absences/' + absenceId, data).then(function(r) { return r.json(); }).then(function() {
        closeModal();
        showNotificationToast('Absence modifiee');
        loadAbsences();
        if (APP.currentSection === 'ponts') loadPontsMecas();
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function supprimerAbsence(id) {
    openConfirmDialog('Supprimer cette absence ?', function() {
        apiDelete('/api/absences/' + id).then(function() {
            loadAbsences();
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    });
}

// ===== GESTION CLIENTS =====
var _clientSearchTimer = null;

function rechercherClients(val) {
    if (_clientSearchTimer) clearTimeout(_clientSearchTimer);
    _clientSearchTimer = setTimeout(function() { loadClients(val, 1); }, 300);
}

function loadClients(search, page) {
    page = page || 1;
    APP._clientsPage = page;
    var url = '/api/clients?page=' + page + '&limit=50';
    if (search) url += '&search=' + encodeURIComponent(search);
    var container = document.getElementById('clients-list');
    setLoadingState(container, true, 'Chargement des clients...');
    apiGet(url).then(function(r) { return r.json(); }).then(function(data) {
        var clients = data.items || data;
        var total = data.total || clients.length;
        var pages = data.pages || 1;
        var countEl = document.getElementById('clients-total-count');
        if (countEl) countEl.textContent = total + ' clients';
        if (!clients.length) {
            container.innerHTML = '<div style="color:#666;padding:20px;text-align:center">Aucun client trouve</div>';
            renderClientsPagination(0, 0);
            updateLiveRegion('Aucun client trouve');
            return;
        }
        var html = '';
        clients.forEach(function(c) {
            html += '<div style="background:#252525;border:1px solid #333;border-radius:8px;padding:12px;margin-bottom:6px;cursor:pointer;transition:border-color .15s" onmouseover="this.style.borderColor=\'var(--orange)\'" onmouseout="this.style.borderColor=\'#333\'" onclick="showClientDetail(' + c.id + ')">' +
                '<div style="display:flex;justify-content:space-between;align-items:center">' +
                    '<div><div style="font-weight:600;color:#eee">' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + '</div>' +
                    '<div style="font-size:12px;color:#888">' + (escapeHtml(c.telephone) || '-') + (escapeHtml(c.email) ? ' | ' + c.email : '') + '</div></div>' +
                    '<div style="text-align:right"><span class="badge blue">' + (c.nb_rdv || 0) + ' RDV</span>' +
                    '<span class="badge" style="background:rgba(20,184,166,0.15);color:#14b8a6;margin-left:4px">' + (c.nb_vehicules || 0) + ' moto' + ((c.nb_vehicules || 0) > 1 ? 's' : '') + '</span>' +
                    (c.dernier_rdv ? '<div style="font-size:11px;color:#666;margin-top:4px">' + new Date(c.dernier_rdv).toLocaleDateString('fr-FR') + '</div>' : '') +
                    '</div></div></div>';
        });
        container.innerHTML = html;
        renderClientsPagination(page, pages);
        updateLiveRegion(clients.length + ' clients affiches');
    }).catch(function() {
        var container = document.getElementById('clients-list');
        if (container) container.innerHTML = '<div style="color:#666;padding:20px">Erreur chargement</div>';
    });
    loadClientsStats();
}

function loadClientsStats() {
    apiGet('/api/clients/stats').then(function(r) { return r.json(); }).then(function(stats) {
        var el;
        el = document.getElementById('stat-total-clients');
        if (el) el.textContent = stats.total || 0;
        el = document.getElementById('stat-clients-avec-rdv');
        if (el) el.textContent = stats.avec_rdv || 0;
        el = document.getElementById('stat-total-vehicules');
        if (el) el.textContent = stats.vehicules || 0;
        el = document.getElementById('stat-ca-clients');
        if (el) el.textContent = stats.ca_total ? Math.round(stats.ca_total) + '\u20AC' : '0\u20AC';
    }).catch(function() {});
}

function renderClientsPagination(currentPage, totalPages) {
    var container = document.getElementById('clients-pagination');
    if (!container || totalPages <= 1) { if (container) container.innerHTML = ''; return; }
    var html = '';
    if (currentPage > 1) {
        html += '<button class="btn btn-ghost" style="font-size:12px" onclick="loadClients(document.getElementById(\'clients-search\').value,' + (currentPage - 1) + ')">\u00AB Prec</button>';
    }
    var start = Math.max(1, currentPage - 2);
    var end = Math.min(totalPages, currentPage + 2);
    for (var i = start; i <= end; i++) {
        var active = (i === currentPage) ? 'background:var(--orange);color:white;' : '';
        html += '<button class="btn btn-ghost" style="font-size:12px;padding:4px 10px;' + active + '" onclick="loadClients(document.getElementById(\'clients-search\').value,' + i + ')">' + i + '</button>';
    }
    if (currentPage < totalPages) {
        html += '<button class="btn btn-ghost" style="font-size:12px" onclick="loadClients(document.getElementById(\'clients-search\').value,' + (currentPage + 1) + ')">Suiv \u00BB</button>';
    }
    container.innerHTML = html;
}

function ouvrirNouveauClient() {
    var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Nom *</label><input id="new-client-nom" class="form-input" placeholder="Nom de famille"></div>' +
        '<div class="form-group"><label class="form-label" style="color:#ccc">Prenom *</label><input id="new-client-prenom" class="form-input" placeholder="Prenom"></div>' +
        '<div class="form-group"><label class="form-label" style="color:#ccc">Telephone *</label><input id="new-client-tel" class="form-input" placeholder="06 12 34 56 78"></div>' +
        '<div class="form-group"><label class="form-label" style="color:#ccc">Email</label><input id="new-client-email" class="form-input" placeholder="email@exemple.com"></div>' +
        '<div class="form-group"><label class="form-label" style="color:#ccc">Adresse</label><input id="new-client-adresse" class="form-input" placeholder="Adresse complete"></div>' +
        '<div class="form-group"><label class="form-label" style="color:#ccc">Notes</label><textarea id="new-client-notes" class="form-input" rows="2" placeholder="Notes internes..."></textarea></div>' +
        '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="creerNouveauClient()">Creer le client</button>';
    showModal('Nouveau client', html, '450px');
}

function creerNouveauClient() {
    var nom = document.getElementById('new-client-nom').value.trim();
    var prenom = document.getElementById('new-client-prenom').value.trim();
    var tel = document.getElementById('new-client-tel').value.trim();
    if (!nom || !prenom || !tel) { alert('Nom, prenom et telephone sont obligatoires'); return; }
    apiPost('/api/clients', {
        nom: nom, prenom: prenom, telephone: tel,
        email: document.getElementById('new-client-email').value.trim() || null,
        adresse: document.getElementById('new-client-adresse').value.trim() || null,
        notes: document.getElementById('new-client-notes').value.trim() || null
    }).then(function(r) { return r.json(); }).then(function(data) {
        closeModal();
        showNotificationToast('Client cree');
        loadClients(null, 1);
        if (data.id) showClientDetail(data.id);
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function supprimerVehicule(vehiculeId, clientId) {
    openConfirmDialog('Supprimer ce vehicule ?', function() {
        apiDelete('/api/vehicules/' + vehiculeId).then(function(r) { return r.json(); }).then(function() {
            showNotificationToast('Vehicule supprime');
            showClientDetail(clientId);
            loadClientsStats();
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    });
}

function showClientDetail(clientId) {
    apiGet('/api/clients/' + clientId).then(function(r) { return r.json(); }).then(function(c) {
        var panel = document.getElementById('client-detail-panel');
        panel.style.display = 'block';
        APP._currentClientId = clientId;

        // Info card
        var infoEl = document.getElementById('client-detail-info');
        var nbRdv = (c.historique || []).length;
        // CA total from backend
        var totalCA = c.ca_total || 0;
        infoEl.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">' +
            '<div><div style="font-size:18px;font-weight:700;color:#eee">' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + '</div>' +
            '<div style="font-size:13px;color:#888;margin-top:4px">' + (escapeHtml(c.telephone) || '-') + '</div>' +
            (escapeHtml(c.email) ? '<div style="font-size:13px;color:#888">' + c.email + '</div>' : '') +
            (escapeHtml(c.adresse) ? '<div style="font-size:12px;color:#666;margin-top:4px">' + c.adresse + '</div>' : '') +
            '</div>' +
            '<button class="btn btn-ghost" style="font-size:12px" onclick="ouvrirModalEditClient(' + c.id + ')">Modifier</button>' +
            '</div>' +
            '<div style="display:flex;gap:12px;margin-bottom:8px">' +
            '<div style="background:#1e1e1e;border-radius:6px;padding:8px 14px;flex:1;text-align:center"><div style="font-size:18px;font-weight:700;color:var(--orange)">' + nbRdv + '</div><div style="font-size:10px;color:#666;text-transform:uppercase">Visites</div></div>' +
            '<div style="background:#1e1e1e;border-radius:6px;padding:8px 14px;flex:1;text-align:center"><div style="font-size:18px;font-weight:700;color:var(--green)">' + (totalCA > 0 ? Math.round(totalCA) + '\u20AC' : '-') + '</div><div style="font-size:10px;color:#666;text-transform:uppercase">CA total</div></div>' +
            '<div style="background:#1e1e1e;border-radius:6px;padding:8px 14px;flex:1;text-align:center"><div style="font-size:18px;font-weight:700;color:var(--teal)">' + (c.vehicules || []).length + '</div><div style="font-size:10px;color:#666;text-transform:uppercase">Motos</div></div>' +
            '</div>' +
            (escapeHtml(c.notes) ? '<div style="font-size:12px;color:#888;background:#1e1e1e;padding:8px 12px;border-radius:6px;margin-top:8px">' + c.notes + '</div>' : '');

        // Vehicules
        var vehEl = document.getElementById('client-detail-vehicules');
        var vehicules = c.vehicules || [];
        var vHtml = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px"><div class="card-title">Vehicules (' + vehicules.length + ')</div>' +
            '<button class="btn btn-ghost" style="font-size:11px;padding:3px 10px;color:var(--green)" onclick="ouvrirAjouterVehicule(' + c.id + ')">+ Ajouter</button></div>';
        if (vehicules.length) {
            vehicules.forEach(function(v) {
                vHtml += '<div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #2a2a2a">' +
                    '<div style="font-size:20px">&#127949;</div>' +
                    '<div style="flex:1"><div style="font-weight:600;color:#eee">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div>' +
                    '<div style="font-size:12px;color:#888">' + (escapeHtml(v.plaque) || '') + (escapeHtml(v.annee) ? ' | ' + v.annee : '') + (escapeHtml(v.cylindree) ? ' | ' + v.cylindree : '') + (escapeHtml(v.type_moto) ? ' | ' + v.type_moto : '') + '</div></div>' +
                    '<button class="btn btn-ghost" style="font-size:11px;padding:3px 8px;color:var(--teal)" onclick="ouvrirModalEditVehicule(' + v.id + ')">\u270E</button>' +
                    '<button class="btn btn-ghost" style="font-size:11px;padding:3px 8px;color:var(--red)" onclick="supprimerVehicule(' + v.id + ',' + c.id + ')">\u2716</button>' +
                    '</div>';
            });
        } else {
            vHtml += '<div style="color:#666;font-size:13px">Aucun vehicule enregistre</div>';
        }
        vehEl.innerHTML = vHtml;

        // Historique
        var histEl = document.getElementById('client-detail-historique');
        var historique = c.historique || [];
        var hHtml = '<div class="card-title" style="margin-bottom:10px">Historique atelier (' + historique.length + ')</div>';
        if (historique.length) {
            historique.forEach(function(rdv) {
                var v = rdv.vehicule || {};
                var meca = rdv.mecanicien;
                var prix = rdv.prix_final || rdv.prix_estime || null;
                var tempsEff = rdv.temps_effectif_minutes;
                var borderColor = rdv.statut === 'termine' || rdv.statut === 'facture' || rdv.statut === 'paye' ? 'var(--green)' : (rdv.statut === 'en_cours' ? 'var(--orange)' : ((rdv.statut === 'annule' || rdv.statut === 'non_presente') ? 'var(--red)' : '#444'));
                hHtml += '<div style="background:#1a1a1e;border-left:3px solid ' + borderColor + ';border-radius:6px;padding:10px 14px;margin-bottom:8px;cursor:pointer;transition:background .15s" onmouseover="this.style.background=\'#222\'" onmouseout="this.style.background=\'#1a1a1e\'" onclick="ouvrirDetailHistoriqueRdv(' + rdv.id + ',' + clientId + ')">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">' +
                        '<div style="display:flex;align-items:center;gap:8px">' +
                            '<span style="font-weight:700;color:#eee;font-size:13px">' + (rdv.date_rdv || '') + '</span>' +
                            '<span style="font-size:12px;color:#888">' + formatTime(rdv.heure_rdv) + '</span>' +
                        '</div>' +
                        statusBadge(rdv.statut) +
                    '</div>' +
                    '<div style="font-size:13px;color:#ccc;margin-bottom:4px">' + (escapeHtml(rdv.type_intervention) || '-') + '</div>' +
                    '<div style="display:flex;gap:12px;font-size:11px;color:#777;flex-wrap:wrap">' +
                        '<span>&#127949; ' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</span>' +
                        (meca ? '<span>&#128100; ' + escapeHtml(meca.prenom) + ' ' + escapeHtml(meca.nom) + '</span>' : '') +
                        (rdv.pont ? '<span>&#128295; ' + rdv.pont.nom + '</span>' : '') +
                        (tempsEff ? '<span>&#9201; ' + tempsEff + ' min</span>' : '') +
                        (prix ? '<span>&#128182; ' + Math.round(prix) + '\u20AC</span>' : '') +
                        (rdv.kilometrage ? '<span>&#128663; ' + rdv.kilometrage + ' km</span>' : '') +
                    '</div>' +
                    (escapeHtml(rdv.commentaire) ? '<div style="font-size:11px;color:#666;margin-top:4px;font-style:italic">' + rdv.commentaire.substring(0, 80) + (rdv.commentaire.length > 80 ? '...' : '') + '</div>' : '') +
                    (rdv.rapport ? '<div style="margin-top:4px"><span class="badge blue" style="font-size:9px">Rapport technicien</span></div>' : '') +
                '</div>';
            });
        } else {
            hHtml += '<div style="color:#666;font-size:13px">Aucun historique</div>';
        }
        histEl.innerHTML = hHtml;
    }).catch(function(e) { console.error('Erreur client detail:', e); });
}

// ===== DETAIL RDV DEPUIS HISTORIQUE CLIENT =====
function ouvrirDetailHistoriqueRdv(rdvId, clientId) {
    apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
        var c = rdv.client || {};
        var v = rdv.vehicule || {};
        var meca = APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
        var pont = APP.ponts.find(function(p) { return p.id === rdv.pont_id; });

        var html = '';
        // Header
        html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">' +
            '<div>' + statusBadge(rdv.statut) + '</div>' +
            '<div style="font-size:12px;color:#888">RDV #' + rdv.id + '</div></div>';

        // Infos generales
        html += '<div style="background:#16161a;border-radius:8px;padding:12px;margin-bottom:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">' +
            '<div><span style="color:#666">Date:</span> <span style="color:#eee">' + (rdv.date_rdv || '-') + '</span></div>' +
            '<div><span style="color:#666">Heure:</span> <span style="color:#eee">' + formatTime(rdv.heure_rdv) + '</span></div>' +
            '<div><span style="color:#666">Intervention:</span> <span style="color:#eee">' + (escapeHtml(rdv.type_intervention) || '-') + '</span></div>' +
            '<div><span style="color:#666">Vehicule:</span> <span style="color:#eee">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</span></div>' +
            '<div><span style="color:#666">Mecanicien:</span> <span style="color:#eee">' + (meca ? meca.prenom + ' ' + escapeHtml(meca.nom) : '-') + '</span></div>' +
            '<div><span style="color:#666">Pont:</span> <span style="color:#eee">' + (pont ? pont.nom : '-') + '</span></div>' +
            '<div><span style="color:#666">Kilometrage:</span> <span style="color:#eee">' + (rdv.kilometrage || '-') + '</span></div>' +
            '<div><span style="color:#666">Prix:</span> <span style="color:#eee">' + (rdv.prix_estime ? Math.round(rdv.prix_estime) + '\u20AC' : '-') + '</span></div>' +
            '</div>';

        // Commentaire
        if (rdv.commentaire) {
            html += '<div style="margin-bottom:12px"><div style="font-size:11px;font-weight:600;color:var(--orange);text-transform:uppercase;margin-bottom:4px">Commentaire</div>' +
                '<div style="background:#16161a;border-radius:6px;padding:10px;font-size:13px;color:#ccc">' + escapeHtml(rdv.commentaire) + '</div></div>';
        }

        // Temps de travail
        html += '<div id="hist-rdv-temps" style="margin-bottom:12px"></div>';
        // Rapport technicien
        html += '<div id="hist-rdv-rapport"></div>';

        showModal(escapeHtml(rdv.type_intervention) + ' - ' + (rdv.date_rdv || ''), html, '600px');

        // Charger temps de travail
        apiGet('/api/rendez-vous/' + rdvId + '/temps-travail').then(function(r) { return r.json(); }).then(function(t) {
            var tEl = document.getElementById('hist-rdv-temps');
            if (!t.heure_debut_travail && !t.heure_fin_travail) { tEl.innerHTML = ''; return; }
            var debut = t.heure_debut_travail ? (parseUTCDate(t.heure_debut_travail) || new Date()).toLocaleString('fr-FR') : '-';
            var fin = t.heure_fin_travail ? (parseUTCDate(t.heure_fin_travail) || new Date()).toLocaleString('fr-FR') : 'En cours...';
            tEl.innerHTML = '<div style="font-size:11px;font-weight:600;color:var(--teal);text-transform:uppercase;margin-bottom:4px">Temps d\'intervention</div>' +
                '<div style="background:#16161a;border-radius:6px;padding:10px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">' +
                '<div><span style="color:#666">Debut:</span> <span style="color:#eee">' + debut + '</span></div>' +
                '<div><span style="color:#666">Fin:</span> <span style="color:#eee">' + fin + '</span></div>' +
                '<div><span style="color:#666">Temps effectif:</span> <span style="color:var(--green);font-weight:700">' + (t.temps_effectif_minutes ? t.temps_effectif_minutes + ' min' : '-') + '</span></div>' +
                '<div><span style="color:#666">Statut:</span> <span style="color:' + (t.en_cours ? 'var(--orange)' : 'var(--green)') + '">' + (t.en_cours ? 'En cours' : 'Termine') + '</span></div>' +
                '</div>';
        }).catch(function() {});

        // Charger rapport technicien
        apiGet('/api/rendez-vous/' + rdvId + '/rapport-technicien').then(function(r) { return r.json(); }).then(function(rap) {
            var rEl = document.getElementById('hist-rdv-rapport');
            var rHtml = '<div style="font-size:11px;font-weight:600;color:var(--purple);text-transform:uppercase;margin-bottom:4px">Rapport technicien</div>' +
                '<div style="background:#16161a;border-radius:6px;padding:10px;font-size:13px">';
            if (escapeHtml(rap.travaux_realises)) rHtml += '<div style="margin-bottom:8px"><span style="color:#666">Travaux realises:</span><div style="color:#ccc;margin-top:2px">' + rap.travaux_realises + '</div></div>';
            if (escapeHtml(rap.alertes)) rHtml += '<div style="margin-bottom:8px"><span style="color:var(--red)">Alertes:</span><div style="color:#ccc;margin-top:2px">' + rap.alertes + '</div></div>';
            if (escapeHtml(rap.recommandations)) rHtml += '<div style="margin-bottom:8px"><span style="color:var(--amber)">Recommandations:</span><div style="color:#ccc;margin-top:2px">' + rap.recommandations + '</div></div>';
            if (rap.pieces_utilisees && rap.pieces_utilisees.length) {
                rHtml += '<div><span style="color:#666">Pieces utilisees:</span><div style="color:#ccc;margin-top:2px">' + escapeHtml(rap.pieces_utilisees).join(', ') + '</div></div>';
            }
            rHtml += '</div>';
            rEl.innerHTML = rHtml;
        }).catch(function() {
            var rEl = document.getElementById('hist-rdv-rapport');
            if (rEl) rEl.innerHTML = '';
        });
    }).catch(function(e) { alert('Erreur chargement RDV: ' + e.message); });
}

// ===== AJOUTER VEHICULE A UN CLIENT =====
function ouvrirAjouterVehicule(clientId) {
    var html = '<div style="display:flex;gap:12px"><div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Marque *</label><input id="add-veh-marque" class="form-input" placeholder="Ex: Yamaha"></div>' +
        '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Modele *</label><input id="add-veh-modele" class="form-input" placeholder="Ex: MT-07"></div></div>' +
        '<div class="form-group"><label class="form-label" style="color:#ccc">Plaque *</label><input id="add-veh-plaque" class="form-input" placeholder="AB-123-CD" style="text-transform:uppercase"></div>' +
        '<div style="display:flex;gap:12px"><div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Annee</label><input id="add-veh-annee" class="form-input" type="number" placeholder="2024"></div>' +
        '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Cylindree</label><input id="add-veh-cylindree" class="form-input" placeholder="689cc"></div></div>' +
        '<div class="form-group"><label class="form-label" style="color:#ccc">Type de moto</label><select id="add-veh-type" class="form-select">' +
        '<option value="">--</option><option value="Roadster">Roadster</option><option value="Sportive">Sportive</option><option value="Trail">Trail</option><option value="Custom">Custom</option><option value="Scooter">Scooter</option><option value="Enduro">Enduro</option></select></div>' +
        '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="sauverNouveauVehicule(' + clientId + ')">Ajouter le vehicule</button>';
    showModal('Ajouter un vehicule', html, '450px');
}

function sauverNouveauVehicule(clientId) {
    var marque = document.getElementById('add-veh-marque').value.trim();
    var modele = document.getElementById('add-veh-modele').value.trim();
    var plaque = document.getElementById('add-veh-plaque').value.trim().toUpperCase().replace(/[\s-]/g, '');
    if (!marque || !modele || !plaque) { alert('Marque, modele et plaque sont obligatoires'); return; }
    apiPost('/api/clients/' + clientId + '/vehicules', {
        plaque: plaque,
        marque: marque,
        modele: modele,
        annee: document.getElementById('add-veh-annee').value ? parseInt(document.getElementById('add-veh-annee').value) : null,
        cylindree: document.getElementById('add-veh-cylindree').value || null,
        type_moto: document.getElementById('add-veh-type').value || null
    }).then(function() {
        closeModal();
        showNotificationToast('Vehicule ajoute');
        showClientDetail(clientId);
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function ouvrirModalEditClient(clientId) {
    apiGet('/api/clients/' + clientId).then(function(r) { return r.json(); }).then(function(c) {
        var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Nom</label><input id="edit-client-nom" class="form-input" value="' + (escapeHtml(c.nom) || '') + '"></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Prenom</label><input id="edit-client-prenom" class="form-input" value="' + (escapeHtml(c.prenom) || '') + '"></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Telephone</label><input id="edit-client-tel" class="form-input" value="' + (escapeHtml(c.telephone) || '') + '"></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Email</label><input id="edit-client-email" class="form-input" value="' + (escapeHtml(c.email) || '') + '"></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Adresse</label><input id="edit-client-adresse" class="form-input" value="' + (escapeHtml(c.adresse) || '') + '"></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Notes</label><textarea id="edit-client-notes" class="form-input" rows="2">' + (escapeHtml(c.notes) || '') + '</textarea></div>' +
            '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="sauverClient(' + clientId + ')">Enregistrer</button>';
        showModal('Modifier client', html, '450px');
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function sauverClient(clientId) {
    var data = {
        nom: document.getElementById('edit-client-nom').value,
        prenom: document.getElementById('edit-client-prenom').value,
        telephone: document.getElementById('edit-client-tel').value,
        email: document.getElementById('edit-client-email').value || null,
        adresse: document.getElementById('edit-client-adresse').value || null,
        notes: document.getElementById('edit-client-notes').value || null
    };
    apiPut('/api/clients/' + clientId, data).then(function() {
        closeModal();
        if (APP.currentSection === 'clients') {
            showClientDetail(clientId);
            loadClients();
        } else {
            refreshCurrentSection();
        }
    }).catch(function(e) { alert('Erreur: ' + e.message); });
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
    apiGet('/api/ateliers').then(function(r) { return r.json(); }).then(function(items) {
        var list = document.getElementById('admin-ateliers-list');
        if (!list) return;
        var btn = document.getElementById('btn-admin-new-atelier');
        if (btn) btn.style.display = hasPermission('ateliers.manage') ? '' : 'none';
        var btnUser = document.getElementById('btn-admin-new-user');
        if (btnUser) btnUser.style.display = hasPermission('users.manage') ? '' : 'none';
        if (!items || !items.length) {
            list.innerHTML = '<div style="padding:18px;color:#888">Aucun atelier</div>';
            renderAdminUsers([]);
            return;
        }
        if (!APP.adminSelectedAtelierId) APP.adminSelectedAtelierId = (APP.currentUser && APP.currentUser.atelier_id) ? APP.currentUser.atelier_id : items[0].id;
        var html = '';
        items.forEach(function(a) {
            var selected = APP.adminSelectedAtelierId === a.id;
            html += '<div style="padding:12px 14px;border-bottom:1px solid #2a2a33;display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap">' +
                '<div><div style="font-weight:700;color:#eee">' + escapeHtml(a.nom || '') + ' <span class="badge ' + (a.actif ? 'green' : 'red') + '">' + (a.actif ? 'actif' : 'inactif') + '</span></div>' +
                '<div style="font-size:12px;color:#888">' + escapeHtml(a.slug || '') + ' • ' + escapeHtml(a.ville || '-') + ' • plan ' + escapeHtml(a.plan || '-') + '</div></div>' +
                '<div style="display:flex;gap:8px"><button class="btn ' + (selected ? 'btn-primary' : 'btn-ghost') + '" onclick="selectAdminAtelier(' + a.id + ')">' + (selected ? 'Selectionne' : 'Selectionner') + '</button>' +
                '<button class="btn btn-ghost" onclick="ouvrirEditAtelier(' + a.id + ', \'' + escapeHtml(a.nom || '').replace(/'/g, "\\'") + '\')">Modifier</button>' +
                '<button class="btn btn-primary" onclick="switchAtelier(' + a.id + ')">Basculer</button></div></div>';
        });
        list.innerHTML = html;
        loadAdminUsers();
    }).catch(function(e) {
        var list = document.getElementById('admin-ateliers-list');
        if (list) list.innerHTML = '<div style="padding:18px;color:#ef4444">Erreur: ' + escapeHtml(e.message || 'chargement') + '</div>';
    });
}

function selectAdminAtelier(atelierId) {
    APP.adminSelectedAtelierId = atelierId;
    loadAdminAteliers();
}

function loadAdminUsers() {
    var list = document.getElementById('admin-users-list');
    if (!list) return;
    if (!APP.adminSelectedAtelierId) {
        list.innerHTML = '<div style="padding:10px;color:#888">Selectionnez un atelier.</div>';
        return;
    }
    apiGet('/api/ateliers/' + APP.adminSelectedAtelierId + '/users').then(function(r) { return r.json(); }).then(function(users) {
        renderAdminUsers(users || []);
        if (document.getElementById('admin-panel-workshop') && document.getElementById('admin-panel-workshop').style.display !== 'none') {
            loadAdminWorkshop();
        }
    }).catch(function(e) {
        list.innerHTML = '<div style="padding:10px;color:#ef4444">Erreur: ' + escapeHtml(e.message || 'chargement users') + '</div>';
    });
}

function renderAdminUsers(users) {
    var list = document.getElementById('admin-users-list');
    if (!list) return;
    if (!users || !users.length) {
        list.innerHTML = '<div style="padding:10px;color:#888">Aucun compte pour cet atelier.</div>';
        return;
    }
    var canManageUsers = hasPermission('users.manage');
    var html = '<div class="table-wrap"><table><thead><tr><th>Login</th><th>Email</th><th>Role</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
    users.forEach(function(u) {
        html += '<tr><td>' + escapeHtml(u.username || '') + '</td><td>' + escapeHtml(u.email || '-') + '</td><td>' + escapeHtml(u.role || '-') + '</td><td>' + (u.is_active ? '<span class="badge green">actif</span>' : '<span class="badge red">inactif</span>') + '</td><td>' +
            (canManageUsers ? '<button class="btn btn-ghost" onclick="ouvrirEditionUtilisateurAtelier(' + u.id + ')">Editer</button> <button class="btn btn-ghost" onclick="supprimerUtilisateurAtelier(' + u.id + ')">Supprimer</button>' : '-') +
            '</td></tr>';
    });
    html += '</tbody></table></div>';
    list.innerHTML = html;
}

function switchAtelier(atelierId) {
    apiPost('/api/auth/switch-atelier', { atelier_id: atelierId }).then(function(r) { return r.json(); }).then(function(data) {
        showNotificationToast('Atelier actif mis a jour');
        if (data && data.role) setAuthRole(data.role);
        if (data && data.sections) APP.roleSections = data.sections;
        if (data && data.permissions) APP.rolePermissions = data.permissions;
        apiGet('/api/auth/me').then(function(r) { return r.json(); }).then(function(me) {
            APP.currentUser = me;
            APP.roleSections = me.sections || null;
            APP.rolePermissions = me.permissions || null;
            setAuthRole(me.role);
            initApp();
        }).catch(function() { initApp(); });
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function ouvrirNouveauAtelier() {
    var html = '<div class="form-group"><label class="form-label">Nom</label><input id="new-atelier-nom" class="form-input"></div>' +
        '<div class="form-group"><label class="form-label">Slug</label><input id="new-atelier-slug" class="form-input"></div>' +
        '<div class="form-group"><label class="form-label">Ville</label><input id="new-atelier-ville" class="form-input"></div>' +
        '<button class="btn btn-primary" style="width:100%" onclick="creerAtelier()">Creer</button>';
    showModal('Nouvel atelier', html, '420px');
}

function creerAtelier() {
    var nom = (document.getElementById('new-atelier-nom').value || '').trim();
    var rawSlug = (document.getElementById('new-atelier-slug').value || '').trim().toLowerCase();
    var slug = rawSlug || nom.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    if (!nom) { alert('Nom atelier obligatoire'); return; }
    var payload = {
        nom: nom,
        slug: slug || null,
        ville: document.getElementById('new-atelier-ville').value || null
    };
    apiPost('/api/ateliers', payload).then(function() {
        closeModal();
        loadAdminAteliers();
        showNotificationToast('Atelier cree');
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function ouvrirEditAtelier(id, nom) {
    var html = '<div class="form-group"><label class="form-label">Nom</label><input id="edit-atelier-nom" class="form-input" value="' + (nom || '') + '"></div>' +
        '<button class="btn btn-primary" style="width:100%" onclick="sauverAtelier(' + id + ')">Enregistrer</button>';
    showModal('Modifier atelier', html, '420px');
}

function sauverAtelier(id) {
    apiPut('/api/ateliers/' + id, { nom: document.getElementById('edit-atelier-nom').value }).then(function() {
        closeModal();
        loadAdminAteliers();
        showNotificationToast('Atelier mis a jour');
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function ouvrirNouvelUtilisateurAtelier() {
    if (!hasPermission('users.manage')) {
        showAlert('Action non autorisee', 'error');
        return;
    }
    if (!APP.adminSelectedAtelierId) {
        alert('Selectionnez un atelier');
        return;
    }
    var roleOptions = '<option value="service_client">service_client (SRC)</option><option value="receptionnaire">receptionnaire</option><option value="mecanicien">mecanicien</option><option value="admin">admin</option>' +
        (APP.currentUser && APP.currentUser.role === 'super_admin' ? '<option value="super_admin">super_admin</option>' : '');
    if (APP.currentUser && APP.currentUser.role === 'super_admin') {
        apiGet('/api/roles/permissions').then(function(r) { return r.json(); }).then(function(roles) {
            roleOptions = '';
            (roles || []).forEach(function(roleCfg) {
                roleOptions += '<option value="' + escapeHtml(roleCfg.role) + '">' + escapeHtml(roleCfg.role) + '</option>';
            });
            renderCreateUserModal(roleOptions);
        }).catch(function() {
            renderCreateUserModal(roleOptions);
        });
        return;
    }
    renderCreateUserModal(roleOptions);
}

function renderCreateUserModal(roleOptionsHtml) {
    var html = '<div class="form-group"><label class="form-label">Username</label><input id="new-user-username" class="form-input"></div>' +
        '<div class="form-group"><label class="form-label">Email (obligatoire)</label><input id="new-user-email" class="form-input" placeholder="ex: user@atelier.fr"></div>' +
        '<div class="form-group"><label class="form-label">Mot de passe</label><input id="new-user-password" class="form-input" type="password"></div>' +
        '<div class="form-group"><label class="form-label">Role</label><select id="new-user-role" class="form-select">' +
        roleOptionsHtml +
        '</select></div>' +
        '<div id="new-user-meca-fields" style="display:none">' +
        '<div class="form-group"><label class="form-label">Nom technicien</label><input id="new-user-meca-nom" class="form-input"></div>' +
        '<div class="form-group"><label class="form-label">Prenom technicien</label><input id="new-user-meca-prenom" class="form-input"></div>' +
        '<div class="form-group"><label class="form-label">Specialites (texte libre)</label><input id="new-user-meca-specialites" class="form-input" placeholder="ex: moteur, diagnostic"></div>' +
        '<div class="form-group"><label class="form-label">Couleur planning</label><input id="new-user-meca-couleur" class="form-input" type="color" value="#3b82f6"></div>' +
        '</div>' +
        '<button class="btn btn-primary" style="width:100%" onclick="creerUtilisateurAtelier()">Creer le login</button>';
    showModal('Nouveau login atelier', html, '440px');
    var roleEl = document.getElementById('new-user-role');
    if (roleEl) {
        roleEl.onchange = toggleCreateUserMecaFields;
        toggleCreateUserMecaFields();
    }
}

function toggleCreateUserMecaFields() {
    var role = (document.getElementById('new-user-role') || {}).value;
    var box = document.getElementById('new-user-meca-fields');
    if (box) box.style.display = role === 'mecanicien' ? '' : 'none';
}

function creerUtilisateurAtelier() {
    var email = (document.getElementById('new-user-email').value || '').trim();
    if (!email) {
        showAlert('Email obligatoire pour creer un login', 'error');
        return;
    }
    var payload = {
        username: document.getElementById('new-user-username').value,
        email: email,
        role: document.getElementById('new-user-role').value,
        atelier_id: APP.adminSelectedAtelierId,
        password: document.getElementById('new-user-password').value
    };
    if (payload.role === 'mecanicien') {
        payload.mecanicien_nom = (document.getElementById('new-user-meca-nom').value || '').trim();
        payload.mecanicien_prenom = (document.getElementById('new-user-meca-prenom').value || '').trim();
        payload.mecanicien_specialites = (document.getElementById('new-user-meca-specialites').value || '').trim();
        payload.mecanicien_couleur = (document.getElementById('new-user-meca-couleur').value || '').trim();
        if (!payload.mecanicien_nom) {
            showAlert('Nom technicien obligatoire', 'error');
            return;
        }
    }
    apiPost('/api/users/invite', payload).then(function() {
        closeModal();
        loadAdminUsers();
        showNotificationToast('Login cree');
    }).catch(function(e) { alert('Erreur: ' + e.message); });
}

function ouvrirEditionUtilisateurAtelier(userId) {
    apiGet('/api/users/' + userId).then(function(r) { return r.json(); }).then(function(u) {
        var roleOptions = '<option value="service_client"' + (u.role === 'service_client' ? ' selected' : '') + '>service_client (SRC)</option>' +
            '<option value="receptionnaire"' + (u.role === 'receptionnaire' ? ' selected' : '') + '>receptionnaire</option>' +
            '<option value="mecanicien"' + (u.role === 'mecanicien' ? ' selected' : '') + '>mecanicien</option>' +
            '<option value="admin"' + (u.role === 'admin' ? ' selected' : '') + '>admin</option>' +
            (APP.currentUser && APP.currentUser.role === 'super_admin' ? '<option value="super_admin"' + (u.role === 'super_admin' ? ' selected' : '') + '>super_admin</option>' : '');
        var html = '<div class="form-group"><label class="form-label">Username</label><input id="edit-user-username" class="form-input" value="' + escapeHtml(u.username || '') + '"></div>' +
            '<div class="form-group"><label class="form-label">Email (obligatoire)</label><input id="edit-user-email" class="form-input" value="' + escapeHtml(u.email || '') + '"></div>' +
            '<div class="form-group"><label class="form-label">Mot de passe (laisser vide pour ne pas changer)</label><input id="edit-user-password" class="form-input" type="password"></div>' +
            '<div class="form-group"><label class="form-label">Role</label><select id="edit-user-role" class="form-select">' + roleOptions + '</select></div>' +
            '<div id="edit-user-meca-fields" style="display:none">' +
            '<div class="form-group"><label class="form-label">Nom technicien</label><input id="edit-user-meca-nom" class="form-input" value="' + escapeHtml((u.mecanicien && u.mecanicien.nom) || '') + '"></div>' +
            '<div class="form-group"><label class="form-label">Prenom technicien</label><input id="edit-user-meca-prenom" class="form-input" value="' + escapeHtml((u.mecanicien && u.mecanicien.prenom) || '') + '"></div>' +
            '<div class="form-group"><label class="form-label">Specialites</label><input id="edit-user-meca-specialites" class="form-input" value="' + escapeHtml((u.mecanicien && u.mecanicien.specialites) || '') + '"></div>' +
            '<div class="form-group"><label class="form-label">Couleur planning</label><input id="edit-user-meca-couleur" class="form-input" type="color" value="' + escapeAttr((u.mecanicien && u.mecanicien.couleur) || '#3b82f6') + '"></div>' +
            '</div>' +
            '<div class="form-group"><label style="display:flex;gap:6px;align-items:center;color:#ddd"><input type="checkbox" id="edit-user-active" ' + (u.is_active ? 'checked' : '') + '> Actif</label></div>' +
            '<button class="btn btn-primary" style="width:100%" onclick="sauverEditionUtilisateurAtelier(' + u.id + ')">Enregistrer</button>';
        showModal('Editer utilisateur', html, '460px');
        var roleEl = document.getElementById('edit-user-role');
        if (roleEl) {
            roleEl.onchange = toggleEditUserMecaFields;
            toggleEditUserMecaFields();
        }
    }).catch(function(e) { showAlert('Erreur: ' + e.message, 'error'); });
}

function toggleEditUserMecaFields() {
    var role = (document.getElementById('edit-user-role') || {}).value;
    var box = document.getElementById('edit-user-meca-fields');
    if (box) box.style.display = role === 'mecanicien' ? '' : 'none';
}

function sauverEditionUtilisateurAtelier(userId) {
    var email = (document.getElementById('edit-user-email').value || '').trim();
    if (!email) {
        showAlert('Email obligatoire', 'error');
        return;
    }
    var payload = {
        username: document.getElementById('edit-user-username').value,
        email: email,
        role: document.getElementById('edit-user-role').value,
        is_active: document.getElementById('edit-user-active').checked
    };
    if (payload.role === 'mecanicien') {
        payload.mecanicien_nom = (document.getElementById('edit-user-meca-nom').value || '').trim();
        payload.mecanicien_prenom = (document.getElementById('edit-user-meca-prenom').value || '').trim();
        payload.mecanicien_specialites = (document.getElementById('edit-user-meca-specialites').value || '').trim();
        payload.mecanicien_couleur = (document.getElementById('edit-user-meca-couleur').value || '').trim();
        if (!payload.mecanicien_nom) {
            showAlert('Nom technicien obligatoire', 'error');
            return;
        }
    }
    var pwd = (document.getElementById('edit-user-password').value || '').trim();
    if (pwd) payload.password = pwd;
    apiPut('/api/users/' + userId, payload).then(function() {
        closeModal();
        loadAdminUsers();
        showNotificationToast('Utilisateur mis a jour');
    }).catch(function(e) { showAlert('Erreur: ' + e.message, 'error'); });
}

function supprimerUtilisateurAtelier(userId) {
    if (!confirm('Supprimer ce login ?')) return;
    apiDelete('/api/users/' + userId).then(function() {
        loadAdminUsers();
        showNotificationToast('Utilisateur supprime');
    }).catch(function(e) { showAlert('Erreur: ' + e.message, 'error'); });
}

function switchAdminTab(tabId) {
    ['ateliers', 'workshop', 'config', 'horaires', 'prestations', 'equipements', 'roles'].forEach(function(id) {
        var tab = document.getElementById('admin-tab-' + id);
        var panel = document.getElementById('admin-panel-' + id);
        if (tab) tab.classList.toggle('active', id === tabId);
        if (panel) panel.style.display = id === tabId ? '' : 'none';
    });
    if (tabId === 'workshop') loadAdminWorkshop();
    if (tabId === 'config') loadAdminConfig();
    if (tabId === 'horaires') loadAdminHoraires();
    if (tabId === 'prestations') loadAdminPrestations();
    if (tabId === 'equipements') loadAdminEquipements();
    if (tabId === 'roles') loadAdminRoles();
}

function loadAdminRoles() {
    var container = document.getElementById('admin-roles-content');
    if (!container) return;
    if (!APP.currentUser || APP.currentUser.role !== 'super_admin') {
        container.innerHTML = '<div style="color:#888">Reserve super_admin.</div>';
        return;
    }
    apiGet('/api/roles/permissions').then(function(r) { return r.json(); }).then(function(roles) {
        var html = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px"><div style="color:#ddd;font-weight:700">Roles & permissions</div><button class="btn btn-primary" onclick="openRoleEditor()">Nouveau role</button></div>';
        html += '<div class="table-wrap"><table><thead><tr><th>Role</th><th>Visibilite</th><th>Actions autorisees</th><th>Actions</th></tr></thead><tbody>';
        (roles || []).forEach(function(rp) {
            var isSuperAdmin = rp.role === 'super_admin';
            html += '<tr><td><b>' + escapeHtml(rp.role) + '</b>' + (rp.is_system ? ' <span class="badge">systeme</span>' : '') + '</td>' +
                '<td style="font-size:12px;color:#bbb">' + formatRbacBadges(rp.sections, 'section') + '</td>' +
                '<td style="font-size:12px;color:#bbb">' + formatRbacBadges(rp.permissions, 'permission') + '</td>' +
                '<td>' + (isSuperAdmin ? '<span style="font-size:12px;color:#888">Role fige</span>' : '<button class="btn btn-ghost" onclick="openRoleEditor(\'' + escapeHtml(rp.role).replace(/'/g, "\\'") + '\')">Editer</button>') +
                (rp.is_system ? '' : ' <button class="btn btn-ghost" onclick="deleteRolePermission(\'' + escapeHtml(rp.role).replace(/'/g, "\\'") + '\')">Supprimer</button>') +
                '</td></tr>';
        });
        html += '</tbody></table></div>';
        html += '<div class="card" style="margin-top:12px;background:#1f1f22;border-color:#3a3a42">' +
            '<div class="card-title" style="margin-bottom:8px">Lexique RBAC (sections & permissions)</div>' +
            '<div style="font-size:12px;color:#bbb;line-height:1.55">' +
            '<div style="margin-bottom:8px"><b style="color:#eee">Sections:</b> ce que le role voit dans le menu.</div>' +
            '<div style="margin-bottom:8px"><b style="color:#eee">Permissions:</b> ce que le role peut faire (actions/metiers).</div>' +
            '<div><b style="color:#eee">Règles:</b> si une section n\'est pas cochée elle est masquée. Si <code>rdv.select_atelier</code> est cochée, l\'utilisateur peut choisir l\'atelier en RDV et Planning.</div>' +
            '</div></div>';
        container.innerHTML = html;
    }).catch(function(e) {
        container.innerHTML = '<div style="color:#ef4444">Erreur: ' + escapeHtml(e.message || 'chargement roles') + '</div>';
    });
}

function openRoleEditor(roleName) {
    if (!APP.currentUser || APP.currentUser.role !== 'super_admin') return;
    apiGet('/api/roles/permissions').then(function(r) { return r.json(); }).then(function(roles) {
        var existing = (roles || []).find(function(x) { return x.role === roleName; }) || null;
        if (existing && existing.role === 'super_admin') {
            showAlert('Le role super_admin est fige et non modifiable', 'warning');
            return;
        }
        var sections = ['dashboard', 'rdv', 'planning', 'ponts', 'or', 'suivi', 'clients', 'espace-meca', 'admin'];
        var perms = ['billing.view', 'billing.edit', 'billing.pay', 'billing.pdf', 'travaux_supp.review', 'rdv.select_atelier', 'rdv.edit', 'users.manage', 'ateliers.manage', 'roles.manage', 'config.manage', 'prestations.manage', 'equipements.manage'];
        var html = '<div class="form-group"><label class="form-label">Role (slug)</label><input id="rbac-role" class="form-input" ' + (existing ? 'readonly' : '') + ' value="' + escapeHtml(existing ? existing.role : '') + '"></div>' +
            '<div class="form-group"><label class="form-label">Label</label><input id="rbac-label" class="form-input" value="' + escapeHtml(existing ? (existing.label || '') : '') + '"></div>' +
            '<div class="form-group"><label class="form-label">Description</label><input id="rbac-desc" class="form-input" value="' + escapeHtml(existing ? (existing.description || '') : '') + '"></div>' +
            '<div class="form-group"><label class="form-label">Sections visibles</label><div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px">';
        sections.forEach(function(s) {
            var checked = existing && existing.sections && existing.sections.indexOf(s) !== -1 ? 'checked' : '';
            html += '<label style="display:flex;gap:6px;align-items:center;color:#ddd"><input type="checkbox" class="rbac-section" value="' + s + '" ' + checked + '> ' + escapeHtml(RBAC_SECTION_LABELS[s] || s) + ' <span style="color:#777">(' + s + ')</span></label>';
        });
        html += '</div></div><div class="form-group"><label class="form-label">Permissions actions</label><div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px">';
        perms.forEach(function(p) {
            var checked = existing && existing.permissions && existing.permissions.indexOf(p) !== -1 ? 'checked' : '';
            html += '<label style="display:flex;gap:6px;align-items:center;color:#ddd"><input type="checkbox" class="rbac-perm" value="' + p + '" ' + checked + '> ' + escapeHtml(RBAC_PERMISSION_LABELS[p] || p) + ' <span style="color:#777">(' + p + ')</span></label>';
        });
        html += '</div></div><button class="btn btn-primary" style="width:100%" onclick="saveRolePermission()">Enregistrer</button>';
        showModal(existing ? 'Editer role' : 'Nouveau role', html, '640px');
    });
}

function saveRolePermission() {
    var role = normalizeRoleSlug((document.getElementById('rbac-role').value || '').trim().toLowerCase());
    var label = (document.getElementById('rbac-label').value || '').trim();
    if (!role || !label) { showAlert('Role et label obligatoires', 'error'); return; }
    if (role === 'super_admin') {
        showAlert('Le role super_admin est fige et non modifiable', 'warning');
        return;
    }
    var sections = [];
    document.querySelectorAll('.rbac-section:checked').forEach(function(el) { sections.push(el.value); });
    var permissions = [];
    document.querySelectorAll('.rbac-perm:checked').forEach(function(el) { permissions.push(el.value); });
    apiPost('/api/roles/permissions', {
        role: role,
        label: label,
        description: document.getElementById('rbac-desc').value || null,
        sections: sections,
        permissions: permissions
    }).then(function() {
        closeModal();
        loadAdminRoles();
        showNotificationToast('Role enregistre');
    }).catch(function(e) { showAlert('Erreur: ' + e.message, 'error'); });
}

function normalizeRoleSlug(value) {
    return String(value || '').trim().toLowerCase().replace(/[\s-]+/g, '_').replace(/[^a-z0-9_]/g, '');
}

function deleteRolePermission(role) {
    if (!confirm('Supprimer le role ' + role + ' ?')) return;
    apiDelete('/api/roles/permissions/' + role).then(function() {
        loadAdminRoles();
        showNotificationToast('Role supprime');
    }).catch(function(e) { showAlert('Erreur: ' + e.message, 'error'); });
}

function loadAdminConfig() {
    if (!hasPermission('config.manage')) {
        showAlert('Action non autorisee', 'error');
        return;
    }
    var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
    apiGet('/api/config/atelier' + qs).then(function(r) { return r.json(); }).then(function(d) {
        document.getElementById('cfg-taux-std').value = d.taux_horaire_mo_standard || 0;
        document.getElementById('cfg-taux-cpx').value = d.taux_horaire_mo_complexe || 0;
        document.getElementById('cfg-taux-exp').value = d.taux_horaire_mo_expert || 0;
        document.getElementById('cfg-forfait-min').value = d.forfait_mo_minimum || 0;
        document.getElementById('cfg-marge-std').value = d.marge_pieces_standard || 0;
        document.getElementById('cfg-marge-conso').value = d.marge_pieces_consommable || 0;
        document.getElementById('cfg-marge-pneu').value = d.marge_pieces_pneumatique || 0;
        document.getElementById('cfg-tva-mo').value = d.tva_mo_taux || 20;
        document.getElementById('cfg-tva-pieces').value = d.tva_pieces_taux || 20;
        document.getElementById('cfg-validite').value = d.validite_devis_jours || 30;
        document.getElementById('cfg-accompte').value = d.accompte_pourcentage || 0;
    }).catch(function(e) { showAlert('Erreur config: ' + (e.message || 'chargement'), 'error'); });
    loadAdminCategoriesMoto();
}

function saveAdminConfig(e) {
    e.preventDefault();
    var payload = {
        taux_horaire_mo_standard: parseFloat(document.getElementById('cfg-taux-std').value || '0'),
        taux_horaire_mo_complexe: parseFloat(document.getElementById('cfg-taux-cpx').value || '0'),
        taux_horaire_mo_expert: parseFloat(document.getElementById('cfg-taux-exp').value || '0'),
        forfait_mo_minimum: parseFloat(document.getElementById('cfg-forfait-min').value || '0'),
        marge_pieces_standard: parseFloat(document.getElementById('cfg-marge-std').value || '0'),
        marge_pieces_consommable: parseFloat(document.getElementById('cfg-marge-conso').value || '0'),
        marge_pieces_pneumatique: parseFloat(document.getElementById('cfg-marge-pneu').value || '0'),
        tva_mo_taux: parseFloat(document.getElementById('cfg-tva-mo').value || '20'),
        tva_pieces_taux: parseFloat(document.getElementById('cfg-tva-pieces').value || '20'),
        validite_devis_jours: parseInt(document.getElementById('cfg-validite').value || '30', 10),
        accompte_pourcentage: parseFloat(document.getElementById('cfg-accompte').value || '0')
    };
    var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
    apiPut('/api/config/atelier' + qs, payload).then(function() {
        showNotificationToast('Configuration enregistree');
        loadBaseData();
    }).catch(function(e2) { showAlert('Erreur: ' + (e2.message || 'sauvegarde'), 'error'); });
}

function loadAdminCategoriesMoto() {
    var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
    var container = document.getElementById('admin-categories-moto-list');
    if (!container) return;
    apiGet('/api/config/categories-moto' + qs).then(function(r) { return r.json(); }).then(function(cats) {
        if (!cats || cats.length === 0) {
            container.innerHTML = '<p style="color:#666">Aucune categorie trouvee</p>';
            return;
        }
        var html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px">';
        cats.forEach(function(cat) {
            var activeClass = cat.is_active ? 'background:#10b981;color:white' : 'background:#374151;color:#9ca3af';
            html += '<div style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;border:1px solid #374151;cursor:pointer" onclick="toggleAdminCategorieMoto(' + cat.id + ')">' +
                '<span style="display:inline-block;width:12px;height:12px;border-radius:50%;' + activeClass + ';flex-shrink:0"></span>' +
                '<span style="font-size:14px">' + escapeHtml(cat.nom) + '</span>' +
                '</div>';
        });
        html += '</div>';
        container.innerHTML = html;
    }).catch(function() {
        container.innerHTML = '<p style="color:#ef4444">Erreur chargement</p>';
    });
}

function toggleAdminCategorieMoto(categorieId) {
    var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
    apiPut('/api/config/categories-moto/' + categorieId + '/toggle' + qs, {}).then(function(r) { return r.json(); }).then(function(d) {
        showNotificationToast(d.is_active ? 'Type moto active' : 'Type moto desactive');
        loadAdminCategoriesMoto();
    }).catch(function(e) { showAlert('Erreur: ' + (e.message || 'toggle'), 'error'); });
}

function adminFmtTime(value) {
    if (!value) return '';
    return String(value).substring(0, 5);
}

function loadAdminHoraires() {
    if (!hasPermission('config.manage')) {
        showAlert('Action non autorisee', 'error');
        return;
    }
    var container = document.getElementById('admin-horaires-content');
    if (!container) return;
    var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
    apiGet('/api/config/horaires' + qs).then(function(r) { return r.json(); }).then(function(horaires) {
        var jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        var html = '';
        horaires.forEach(function(h) {
            html += '<div style="padding:10px 0;border-bottom:1px solid #2a2a33;display:flex;gap:10px;align-items:end;flex-wrap:wrap">' +
                '<div style="min-width:90px;font-weight:700;color:#eee">' + jours[h.jour_semaine] + '</div>' +
                '<div><label class="form-label">Ouverture</label><input type="time" class="form-input" id="h-ouv-' + h.jour_semaine + '" value="' + adminFmtTime(h.heure_ouverture) + '"></div>' +
                '<div><label class="form-label">Fermeture</label><input type="time" class="form-input" id="h-fer-' + h.jour_semaine + '" value="' + adminFmtTime(h.heure_fermeture) + '"></div>' +
                '<div><label class="form-label">Pause debut (midi)</label><input type="time" class="form-input" id="h-pd-' + h.jour_semaine + '" value="' + adminFmtTime(h.pause_debut) + '"></div>' +
                '<div><label class="form-label">Pause fin (midi)</label><input type="time" class="form-input" id="h-pf-' + h.jour_semaine + '" value="' + adminFmtTime(h.pause_fin) + '"></div>' +
                '<label style="display:flex;gap:6px;align-items:center;color:#ddd"><input type="checkbox" id="h-midi-open-' + h.jour_semaine + '" ' + ((!h.pause_debut || !h.pause_fin) ? 'checked' : '') + ' onchange="toggleAdminMidi(' + h.jour_semaine + ')"> Ouvert le midi</label>' +
                '<label style="display:flex;gap:6px;align-items:center;color:#ddd"><input type="checkbox" id="h-ouvert-' + h.jour_semaine + '" ' + (h.is_ouvert ? 'checked' : '') + '> Ouvert</label>' +
                '<button class="btn btn-ghost" onclick="saveAdminHoraire(' + h.jour_semaine + ')">Sauver</button>' +
                '</div>';
        });
        container.innerHTML = html || '<div style="color:#888">Aucun horaire</div>';
        horaires.forEach(function(h) { toggleAdminMidi(h.jour_semaine); });
    }).catch(function(e) {
        container.innerHTML = '<div style="color:#ef4444">Erreur: ' + escapeHtml(e.message || 'chargement') + '</div>';
    });
}

function saveAdminHoraire(jour) {
    var midiOpen = document.getElementById('h-midi-open-' + jour).checked;
    var pauseDebut = document.getElementById('h-pd-' + jour).value || null;
    var pauseFin = document.getElementById('h-pf-' + jour).value || null;
    if (midiOpen) {
        pauseDebut = null;
        pauseFin = null;
    }
    var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
    apiPut('/api/config/horaires/' + jour + qs, {
        heure_ouverture: document.getElementById('h-ouv-' + jour).value || null,
        heure_fermeture: document.getElementById('h-fer-' + jour).value || null,
        pause_debut: pauseDebut,
        pause_fin: pauseFin,
        is_ouvert: document.getElementById('h-ouvert-' + jour).checked ? 1 : 0
    }).then(function() {
        showNotificationToast('Horaire enregistre');
    }).catch(function(e) { showAlert('Erreur horaire: ' + (e.message || 'sauvegarde'), 'error'); });
}

function toggleAdminMidi(jour) {
    var openMidi = document.getElementById('h-midi-open-' + jour).checked;
    var pd = document.getElementById('h-pd-' + jour);
    var pf = document.getElementById('h-pf-' + jour);
    if (!pd || !pf) return;
    pd.disabled = openMidi;
    pf.disabled = openMidi;
    if (openMidi) {
        pd.value = '';
        pf.value = '';
    }
}

function adminFormatMinutes(mins) {
    var total = parseInt(mins || 0, 10);
    if (!total) return '-';
    var h = Math.floor(total / 60);
    var m = total % 60;
    return (h > 0 ? h + 'h' : '') + (m > 0 ? m + 'min' : (h > 0 ? '00' : ''));
}

function loadAdminPrestations() {
    if (!hasPermission('prestations.manage')) {
        showAlert('Action non autorisee', 'error');
        return;
    }
    var tbody = document.getElementById('admin-prestations-tbody');
    if (!tbody) return;
    var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
    apiGet('/api/config/prestations' + qs).then(function(r) { return r.json(); }).then(function(prestas) {
        APP.prestationsConfig = prestas || [];
        var labels = { entretien: 'Entretien', reparation: 'Reparation', diagnostic: 'Diagnostic', personnalisation: 'Personnalisation' };
        var tariff = { forfait: 'Forfait', horaire: 'Horaire', devis: 'Sur devis' };
        var html = '';
        APP.prestationsConfig.forEach(function(p) {
            html += '<tr' + (isActive(p) ? '' : ' style="opacity:.55"') + '>' +
                '<td><code>' + escapeHtml(p.code || '') + '</code></td>' +
                '<td><b>' + escapeHtml(p.nom || '') + '</b></td>' +
                '<td>' + escapeHtml(labels[p.categorie] || p.categorie || '-') + '</td>' +
                '<td>' + escapeHtml(tariff[p.type_tarif] || p.type_tarif || '-') + '</td>' +
                '<td>' + Number(p.prix_base_ttc || 0).toFixed(2) + ' EUR</td>' +
                '<td>' + adminFormatMinutes(p.temps_estime_minutes) + '</td>' +
                '<td>' + (isActive(p) ? '<span class="badge green">Actif</span>' : '<span class="badge red">Inactif</span>') + '</td>' +
                '<td style="display:flex;gap:6px"><button class="btn btn-ghost" onclick="openAdminGrilleModal(' + p.id + ')">Grille prix</button><button class="btn btn-ghost" onclick="openAdminPrestationModal(' + p.id + ')">Editer</button>' +
                '<button class="btn btn-ghost" onclick="toggleAdminPrestation(' + p.id + ',' + (isActive(p) ? 0 : 1) + ')">' + (isActive(p) ? 'Desactiver' : 'Reactiver') + '</button></td>' +
                '</tr>';
        });
        tbody.innerHTML = html || '<tr><td colspan="8" style="text-align:center;color:#888;padding:16px">Aucune prestation</td></tr>';
    }).catch(function(e) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#ef4444;padding:16px">Erreur: ' + escapeHtml(e.message || 'chargement') + '</td></tr>';
    });
}

function openAdminPrestationModal(id) {
    var p = id ? (APP.prestationsConfig || []).find(function(x) { return x.id === id; }) : null;
    var tvaMo = parseFloat(document.getElementById('cfg-tva-mo') ? document.getElementById('cfg-tva-mo').value : '20') || 20;
    var html = '<div class="form-group"><label class="form-label">Code</label><input id="ap-code" class="form-input" value="' + (p ? escapeHtml(p.code) : '') + '"' + (p ? ' readonly' : '') + '></div>' +
        '<div class="form-group"><label class="form-label">Nom</label><input id="ap-nom" class="form-input" value="' + (p ? escapeHtml(p.nom) : '') + '"></div>' +
        '<div class="form-group"><label class="form-label">Description</label><input id="ap-desc" class="form-input" value="' + (p && p.description ? escapeHtml(p.description) : '') + '"></div>' +
        '<div class="form-group"><label class="form-label">Categorie</label><select id="ap-cat" class="form-select">' +
        '<option value="entretien">Entretien</option><option value="reparation">Reparation</option><option value="diagnostic">Diagnostic</option><option value="personnalisation">Personnalisation</option></select></div>' +
        '<div class="form-group"><label class="form-label">Type tarif</label><select id="ap-type" class="form-select"><option value="forfait">Forfait</option><option value="horaire">Horaire</option><option value="devis">Sur devis</option></select></div>' +
        '<div class="form-group"><label class="form-label">Prix base HT (EUR)</label><input type="number" step="0.01" id="ap-ht" class="form-input" value="' + (p ? Number(p.prix_base_ht || 0).toFixed(2) : '0.00') + '"></div>' +
        '<div class="form-group"><label class="form-label">Temps estime (minutes)</label><input type="number" id="ap-min" class="form-input" value="' + (p ? (p.temps_estime_minutes || 30) : 30) + '"></div>' +
        '<button class="btn btn-primary" style="width:100%" onclick="saveAdminPrestation(' + (id || 'null') + ',' + tvaMo + ')">' + (id ? 'Enregistrer' : 'Ajouter') + '</button>';
    showModal(id ? 'Modifier prestation' : 'Nouvelle prestation', html, '520px');
    if (p) {
        document.getElementById('ap-cat').value = p.categorie || 'entretien';
        document.getElementById('ap-type').value = p.type_tarif || 'forfait';
    }
}

function saveAdminPrestation(id, tvaMo) {
    var code = document.getElementById('ap-code').value.trim();
    var nom = document.getElementById('ap-nom').value.trim();
    if (!code || !nom) { showAlert('Code et nom obligatoires', 'error'); return; }
    var ht = parseFloat(document.getElementById('ap-ht').value || '0');
    var payload = {
        code: code,
        nom: nom,
        description: document.getElementById('ap-desc').value || null,
        categorie: document.getElementById('ap-cat').value,
        type_tarif: document.getElementById('ap-type').value,
        prix_base_ht: ht,
        prix_base_ttc: parseFloat((ht * (1 + (tvaMo || 20) / 100)).toFixed(2)),
        temps_estime_minutes: parseInt(document.getElementById('ap-min').value || '30', 10)
    };
    var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
    var req = id ? apiPut('/api/config/prestations/' + id + qs, payload) : apiPost('/api/config/prestations' + qs, payload);
    req.then(function() {
        closeModal();
        loadAdminPrestations();
        showNotificationToast(id ? 'Prestation modifiee' : 'Prestation ajoutee');
    }).catch(function(e) { showAlert('Erreur prestation: ' + (e.message || 'sauvegarde'), 'error'); });
}

function toggleAdminPrestation(id, state) {
    var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
    apiPut('/api/config/prestations/' + id + qs, { is_active: state }).then(function() {
        loadAdminPrestations();
        showNotificationToast(state ? 'Prestation reactivee' : 'Prestation desactivee');
    }).catch(function(e) { showAlert('Erreur: ' + (e.message || 'maj'), 'error'); });
}

function openAdminGrilleModal(prestationId) {
    var presta = (APP.prestationsConfig || []).find(function(p) { return p.id === prestationId; });
    if (!presta) return;
    var grille = presta.grille || {};
    var tvaMo = parseFloat(document.getElementById('cfg-tva-mo') ? document.getElementById('cfg-tva-mo').value : '20') || 20;

    var html = '<p style="font-size:13px;color:#9ca3af;margin-bottom:12px">Prix TTC et temps par type de moto.</p>' +
        '<table><thead><tr><th>Type moto</th><th>Prix TTC (EUR)</th><th>Temps (min)</th></tr></thead><tbody>';

    (APP.categories || []).forEach(function(cat) {
        var entry = grille[cat.nom] || {};
        html += '<tr>' +
            '<td><b>' + escapeHtml(cat.nom) + '</b></td>' +
            '<td><input type="number" step="0.01" class="form-input" style="width:120px" id="agr-ttc-' + cat.id + '" value="' + Number(entry.prix_ttc || presta.prix_base_ttc || 0).toFixed(2) + '"></td>' +
            '<td><input type="number" class="form-input" style="width:95px" id="agr-min-' + cat.id + '" value="' + (entry.temps_minutes || presta.temps_estime_minutes || 30) + '"></td>' +
            '</tr>';
    });
    html += '</tbody></table>' +
        '<button class="btn btn-primary" style="width:100%;margin-top:12px" onclick="saveAdminGrille(' + prestationId + ',' + tvaMo + ')">Sauver la grille</button>';
    showModal('Grille prix - ' + escapeHtml(presta.nom || ''), html, '620px');
}

function saveAdminGrille(prestationId, tvaMo) {
    var entries = [];
    (APP.categories || []).forEach(function(cat) {
        var ttc = parseFloat((document.getElementById('agr-ttc-' + cat.id) || {}).value || '0');
        var mins = parseInt((document.getElementById('agr-min-' + cat.id) || {}).value || '30', 10);
        entries.push({
            categorie_moto_id: cat.id,
            prix_ttc: ttc,
            prix_ht: parseFloat((ttc / (1 + (tvaMo || 20) / 100)).toFixed(2)),
            temps_minutes: mins
        });
    });
    var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
    apiPut('/api/config/prestations/' + prestationId + '/grille' + qs, { entries: entries }).then(function() {
        closeModal();
        loadAdminPrestations();
        showNotificationToast('Grille tarifaire enregistree');
    }).catch(function(e) { showAlert('Erreur grille: ' + (e.message || 'sauvegarde'), 'error'); });
}

function loadAdminWorkshop() {
    if (!APP.adminSelectedAtelierId) {
        var pT = document.getElementById('admin-workshop-ponts-tbody');
        var mT = document.getElementById('admin-workshop-mecas-tbody');
        if (pT) pT.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#888;padding:14px">Selectionnez un atelier.</td></tr>';
        if (mT) mT.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#888;padding:14px">Selectionnez un atelier.</td></tr>';
        return;
    }
    Promise.all([
        apiGet('/api/ponts?tous=true').then(function(r) { return r.json(); }).catch(function() { return []; }),
        apiGet('/api/mecaniciens?tous=true').then(function(r) { return r.json(); }).catch(function() { return []; }),
        apiGet('/api/ateliers/' + APP.adminSelectedAtelierId + '/users').then(function(r) { return r.json(); }).catch(function() { return []; })
    ]).then(function(results) {
        renderAdminWorkshopPonts(results[0] || [], results[1] || []);
        renderAdminWorkshopMecas(results[1] || [], results[2] || []);
    });
}

function renderAdminWorkshopPonts(ponts, mecas) {
    var tbody = document.getElementById('admin-workshop-ponts-tbody');
    if (!tbody) return;
    var mecaById = {};
    (mecas || []).forEach(function(m) { mecaById[m.id] = m; });
    var html = '';
    (ponts || []).forEach(function(p) {
        var m = p.mecanicien_id ? mecaById[p.mecanicien_id] : null;
        html += '<tr><td>' + escapeHtml(p.nom || '-') + '</td><td>' + escapeHtml(p.type_pont || '-') + '</td><td>' + escapeHtml(String(p.capacite_kg || '-')) + ' kg</td>' +
            '<td>' + (m ? (escapeHtml(m.prenom || '') + ' ' + escapeHtml(m.nom || '')) : '-') + '</td>' +
            '<td>' + (p.actif ? '<span class="badge green">actif</span>' : '<span class="badge red">inactif</span>') + '</td>' +
            '<td><button class="btn btn-ghost" onclick="openAdminPontModal(' + p.id + ')">Editer</button> <button class="btn btn-danger" onclick="deleteAdminPont(' + p.id + ')">Supprimer</button></td></tr>';
    });
    tbody.innerHTML = html || '<tr><td colspan="6" style="text-align:center;color:#888;padding:14px">Aucun pont.</td></tr>';
}

function renderAdminWorkshopMecas(mecas, users) {
    var tbody = document.getElementById('admin-workshop-mecas-tbody');
    if (!tbody) return;
    var userByUsername = {};
    (users || []).forEach(function(u) { userByUsername[(u.username || '').toLowerCase()] = u; });
    var html = '';
    (mecas || []).forEach(function(m) {
        var linkedUser = null;
        for (var i = 0; i < (users || []).length; i++) {
            var u = users[i];
            if (u.role !== 'mecanicien') continue;
            var um = u.mecanicien || {};
            if ((um.id && um.id === m.id) || (um.nom === m.nom && um.prenom === m.prenom)) { linkedUser = u; break; }
        }
        html += '<tr><td>' + escapeHtml(m.nom || '-') + '</td><td>' + escapeHtml(m.prenom || '-') + '</td><td>' + escapeHtml(m.specialites || '-') + '</td>' +
            '<td><span style="display:inline-block;width:12px;height:12px;border-radius:999px;background:' + escapeAttr(m.couleur || '#3b82f6') + ';margin-right:6px"></span>' + escapeHtml(m.couleur || '#3b82f6') + '</td>' +
            '<td>' + escapeHtml((linkedUser && linkedUser.username) || '-') + '</td>' +
            '<td>' + (m.actif ? '<span class="badge green">actif</span>' : '<span class="badge red">inactif</span>') + '</td>' +
            '<td><button class="btn btn-danger" onclick="deleteAdminMecanicien(' + m.id + ')">Supprimer</button></td></tr>';
    });
    tbody.innerHTML = html || '<tr><td colspan="7" style="text-align:center;color:#888;padding:14px">Aucun technicien.</td></tr>';
}

function openAdminPontModal(pontId) {
    var isEdit = !!pontId;
    var pont = isEdit ? (APP.ponts || []).find(function(x) { return x.id === pontId; }) : null;
    var mecas = (APP.mecaniciens || []).filter(function(m) { return isActive(m); });
    var mecaOpts = '<option value="">-- Aucun --</option>';
    mecas.forEach(function(m) {
        mecaOpts += '<option value="' + m.id + '"' + (pont && pont.mecanicien_id === m.id ? ' selected' : '') + '>' + escapeHtml(m.prenom || '') + ' ' + escapeHtml(m.nom || '') + '</option>';
    });
    var html = '<div class="form-group"><label class="form-label">Nom</label><input id="adm-pont-nom" class="form-input" value="' + escapeAttr((pont && pont.nom) || '') + '"></div>' +
        '<div class="form-group"><label class="form-label">Type</label><input id="adm-pont-type" class="form-input" value="' + escapeAttr((pont && pont.type_pont) || 'moto') + '"></div>' +
        '<div class="form-group"><label class="form-label">Capacite (kg)</label><input id="adm-pont-cap" class="form-input" type="number" value="' + escapeAttr(String((pont && pont.capacite_kg) || 500)) + '"></div>' +
        '<div class="form-group"><label class="form-label">Technicien assigne</label><select id="adm-pont-meca" class="form-select">' + mecaOpts + '</select></div>' +
        '<button class="btn btn-primary" style="width:100%" onclick="saveAdminPont(' + (pontId || 0) + ')">' + (isEdit ? 'Enregistrer' : 'Ajouter') + '</button>';
    showModal(isEdit ? 'Editer pont' : 'Ajouter pont', html, '460px');
}

function saveAdminPont(pontId) {
    var payload = {
        nom: (document.getElementById('adm-pont-nom').value || '').trim(),
        type_pont: (document.getElementById('adm-pont-type').value || 'moto').trim(),
        capacite_kg: parseInt(document.getElementById('adm-pont-cap').value || '500', 10),
        is_active: 1,
        mecanicien_id: parseInt(document.getElementById('adm-pont-meca').value || '0', 10) || null
    };
    if (!payload.nom) { showAlert('Nom pont obligatoire', 'error'); return; }
    var req = pontId ? apiPut('/api/ponts/' + pontId, payload) : apiPost('/api/ponts', payload);
    req.then(function() {
        closeModal();
        loadBaseData().then(function() { loadAdminWorkshop(); });
        showNotificationToast('Pont enregistre');
    }).catch(function(e) { showAlert('Erreur pont: ' + (e.message || 'sauvegarde'), 'error'); });
}

function deleteAdminPont(pontId) {
    openConfirmDialog('Supprimer ce pont ?', function() {
        apiDelete('/api/ponts/' + pontId).then(function() {
            loadBaseData().then(function() { loadAdminWorkshop(); });
            showNotificationToast('Pont supprime');
        }).catch(function(e) { showAlert('Erreur suppression pont: ' + (e.message || 'suppression'), 'error'); });
    });
}

function deleteAdminMecanicien(mecanicienId) {
    openConfirmDialog('Supprimer ce technicien ?', function() {
        apiDelete('/api/mecaniciens/' + mecanicienId).then(function() {
            loadBaseData().then(function() { loadAdminWorkshop(); });
            showNotificationToast('Technicien supprime');
        }).catch(function(e) { showAlert('Erreur suppression technicien: ' + (e.message || 'suppression'), 'error'); });
    });
}

function loadAdminEquipements() {
    if (!hasPermission('equipements.manage')) {
        showAlert('Action non autorisee', 'error');
        return;
    }
    var tbody = document.getElementById('admin-equipements-tbody');
    if (!tbody) return;
    var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
    apiGet('/api/config/pont-equipements' + qs).then(function(r) { return r.json(); }).then(function(items) {
        var html = '';
        (items || []).forEach(function(eq) {
            var pont = (APP.ponts || []).find(function(p) { return p.id === eq.pont_id; });
            html += '<tr><td>' + escapeHtml((pont && pont.nom) || ('Pont #' + eq.pont_id)) + '</td>' +
                '<td><b>' + escapeHtml(eq.nom || '') + '</b></td>' +
                '<td>' + escapeHtml(eq.description || '-') + '</td>' +
                '<td>' + (eq.is_present ? '<span class="badge green">Oui</span>' : '<span class="badge amber">Non</span>') + '</td>' +
                '<td><button class="btn btn-danger" onclick="deleteAdminEquipement(' + eq.id + ')">Supprimer</button></td></tr>';
        });
        tbody.innerHTML = html || '<tr><td colspan="5" style="text-align:center;color:#888;padding:16px">Aucun equipement</td></tr>';
    }).catch(function(e) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#ef4444;padding:16px">Erreur: ' + escapeHtml(e.message || 'chargement') + '</td></tr>';
    });
}

function openAdminEquipementModal() {
    var opts = '<option value="">Selectionner un pont...</option>';
    (APP.ponts || []).forEach(function(p) {
        opts += '<option value="' + p.id + '">' + escapeHtml(p.nom || ('Pont #' + p.id)) + '</option>';
    });
    var html = '<div class="form-group"><label class="form-label">Pont</label><select id="ae-pont" class="form-select">' + opts + '</select></div>' +
        '<div class="form-group"><label class="form-label">Nom equipement</label><input id="ae-nom" class="form-input"></div>' +
        '<div class="form-group"><label class="form-label">Description</label><input id="ae-desc" class="form-input"></div>' +
        '<label style="display:flex;align-items:center;gap:6px;color:#ddd;margin-bottom:10px"><input type="checkbox" id="ae-present" checked> Present</label>' +
        '<button class="btn btn-primary" style="width:100%" onclick="saveAdminEquipement()">Ajouter</button>';
    showModal('Nouvel equipement pont', html, '440px');
}

function saveAdminEquipement() {
    var pontId = parseInt(document.getElementById('ae-pont').value || '0', 10);
    var nom = document.getElementById('ae-nom').value.trim();
    if (!pontId || !nom) { showAlert('Pont et nom obligatoires', 'error'); return; }
    var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
    apiPost('/api/config/pont-equipements' + qs, {
        pont_id: pontId,
        nom: nom,
        description: document.getElementById('ae-desc').value || null,
        is_present: document.getElementById('ae-present').checked ? 1 : 0
    }).then(function() {
        closeModal();
        loadAdminEquipements();
        showNotificationToast('Equipement ajoute');
    }).catch(function(e) { showAlert('Erreur equipement: ' + (e.message || 'creation'), 'error'); });
}

function deleteAdminEquipement(id) {
    openConfirmDialog('Supprimer cet equipement ?', function() {
        var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
        apiDelete('/api/config/pont-equipements/' + id + qs).then(function() {
            loadAdminEquipements();
            showNotificationToast('Equipement supprime');
        }).catch(function(e) { showAlert('Erreur suppression: ' + (e.message || 'suppression'), 'error'); });
    });
}

console.log('app.js loaded');
