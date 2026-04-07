window.ROLE_SECTIONS = window.ROLE_SECTIONS || {
    mecanicien: ['dashboard', 'planning', 'or', 'motos', 'espace-meca'],
    receptionnaire: ['dashboard', 'rdv', 'planning', 'ponts', 'or', 'suivi', 'motos', 'clients', 'espace-meca'],
    service_client: ['dashboard', 'rdv', 'planning', 'ponts', 'or', 'suivi', 'motos', 'clients', 'espace-meca'],
    manager: ['dashboard', 'rdv', 'planning', 'ponts', 'or', 'suivi', 'motos', 'clients', 'espace-meca', 'admin'],
    admin: ['dashboard', 'rdv', 'planning', 'ponts', 'or', 'suivi', 'motos', 'clients', 'espace-meca', 'admin'],
    super_admin: ['dashboard', 'rdv', 'planning', 'ponts', 'or', 'suivi', 'motos', 'clients', 'espace-meca', 'admin']
};
var ROLE_SECTIONS = window.ROLE_SECTIONS;

window.RBAC_SECTION_LABELS = window.RBAC_SECTION_LABELS || {
    'dashboard': 'Dashboard',
    'rdv': 'Prise de RDV',
    'planning': 'Planning',
    'ponts': 'Ponts & mecaniciens',
    'or': 'Ordres de reparation',
    'suivi': 'Suivi live',
    'motos': 'Fiches moto',
    'clients': 'Clients',
    'espace-meca': 'Espace mecanicien',
    'admin': 'Administration'
};
var RBAC_SECTION_LABELS = window.RBAC_SECTION_LABELS;

window.RBAC_PERMISSION_LABELS = window.RBAC_PERMISSION_LABELS || {
    'billing.view': 'Voir factures',
    'billing.edit': 'Modifier facturation',
    'billing.pay': 'Encaissement',
    'billing.pdf': 'Generer PDF facture',
    'travaux_supp.review': 'Valider travaux supplementaires',
    'rdv.select_atelier': 'Choix atelier (multi-site)',
    'rdv.edit': 'Modifier les rendez-vous',
    'workflow.manage': 'Piloter le workflow atelier',
    'or.manage': 'Gerer les OR et la reception',
    'workshop.manage': 'Gerer ponts & mecaniciens',
    'motos.manage': 'Gerer la base moto',
    'horaires.manage': 'Gerer les horaires',
    'clients.edit': 'Modifier clients & vehicules',
    'stats.view': 'Voir les statistiques',
    'users.manage': 'Gerer utilisateurs',
    'ateliers.manage': 'Gerer ateliers',
    'roles.manage': 'Gerer roles & droits',
    'config.manage': 'Gerer configuration',
    'prestations.manage': 'Gerer prestations'
};
var RBAC_PERMISSION_LABELS = window.RBAC_PERMISSION_LABELS;

window.AppCoreModule = window.AppCoreModule || {
    showLogin: function() {
        document.getElementById('login-screen').style.display = 'flex';
        document.getElementById('app-container').style.display = 'none';
    },

    hideLogin: function() {
        document.getElementById('login-screen').style.display = 'none';
        document.getElementById('app-container').style.display = 'flex';
    },

    doLogin: function() {
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
            if (!resp.ok) {
                errEl.textContent = 'Identifiants incorrects';
                throw new Error('bad');
            }
            return resp.json();
        }).then(function(data) {
            setAuthRole(data.role);
            window.AppCoreModule.initApp();
        }).catch(function() {
            if (errEl.textContent === '') errEl.textContent = 'Erreur de connexion';
        });
    },

    logout: function() {
        apiPost('/api/auth/logout', {}).finally(function() {
            clearAuthState();
            window.AppCoreModule.showLogin();
        });
    },

    initApp: function() {
        window.AppCoreModule.hideLogin();
        apiGet('/api/auth/me').then(function(r) { return r.json(); }).then(function(me) {
            APP.currentUser = me;
            APP.roleSections = me.sections || null;
            APP.rolePermissions = me.permissions || null;
            var avatar = document.getElementById('user-avatar');
            if (avatar) avatar.textContent = (me.username || 'U').substring(0, 2).toUpperCase();
            window.AppCoreModule.applyRoleVisibility(me.role);
        }).catch(function() {});

        window.AppCoreModule.loadBaseData().then(function() {
            var role = getAuthRole();
            var allowed = window.AppCoreModule.getAllowedSections(role || '');
            var preferred = role === 'mecanicien' ? 'espace-meca' : 'dashboard';
            window.AppCoreModule.showSection(allowed.indexOf(preferred) !== -1 ? preferred : (allowed[0] || 'dashboard'));
        });

        if (APP.refreshInterval) clearInterval(APP.refreshInterval);
        APP.refreshInterval = setInterval(function() {
            if (APP.currentSection === 'dashboard') loadDashboard();
            if (APP.currentSection === 'suivi') loadSuiviLive();
            if (window.AppCoreModule.hasPermission('travaux_supp.review') || window.AppCoreModule.hasPermission('workflow.manage')) {
                pollTravauxSupp();
            }
        }, 30000);
    },

    loadBaseData: function() {
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
    },

    hasPermission: function(permission) {
        if (!APP.currentUser) return false;
        if (APP.currentUser.role === 'super_admin') return true;
        if (APP.rolePermissions && APP.rolePermissions.indexOf(permission) !== -1) return true;

        var legacyPermissions = {
            admin: ['config.manage', 'prestations.manage', 'rdv.edit', 'workflow.manage', 'or.manage', 'workshop.manage', 'motos.manage', 'horaires.manage', 'clients.edit', 'stats.view', 'billing.view', 'billing.edit', 'billing.pay', 'billing.pdf', 'travaux_supp.review', 'users.manage', 'ateliers.manage', 'roles.manage'],
            manager: ['config.manage', 'prestations.manage', 'rdv.edit', 'workflow.manage', 'or.manage', 'workshop.manage', 'motos.manage', 'horaires.manage', 'clients.edit', 'stats.view', 'billing.view', 'billing.edit', 'billing.pay', 'billing.pdf', 'travaux_supp.review', 'users.manage'],
            receptionnaire: ['rdv.edit', 'workflow.manage', 'or.manage', 'clients.edit', 'stats.view', 'travaux_supp.review', 'billing.view'],
            service_client: ['rdv.edit', 'clients.edit'],
            mecanicien: []
        };
        var role = APP.currentUser.role || '';
        return (legacyPermissions[role] || []).indexOf(permission) !== -1;
    },

    canUseBilling: function() {
        return window.AppCoreModule.hasPermission('billing.view') || window.AppCoreModule.hasPermission('billing.edit') || window.AppCoreModule.hasPermission('billing.pay') || window.AppCoreModule.hasPermission('billing.pdf');
    },

    applyRoleVisibility: function(role) {
        var allowed = window.AppCoreModule.getAllowedSections(role);
        var sectionToNav = {
            dashboard: 'nav-dashboard',
            rdv: 'nav-rdv',
            planning: 'nav-planning',
            ponts: 'nav-ponts',
            or: 'nav-or',
            suivi: 'nav-suivi',
            motos: 'nav-motos',
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
        if (navFactures) navFactures.style.display = window.AppCoreModule.canUseBilling() ? '' : 'none';
    },

    formatRbacBadges: function(items, type) {
        var list = Array.isArray(items) ? items : [];
        if (!list.length) return '<span style="color:#666">Aucun</span>';
        var labels = type === 'section' ? RBAC_SECTION_LABELS : RBAC_PERMISSION_LABELS;
        return list.map(function(code) {
            var label = labels[code] || code;
            return '<span class="badge blue" title="' + escapeAttr(code) + '" style="margin:2px 4px 2px 0">' + escapeHtml(label) + '</span>';
        }).join('');
    },

    getAllowedSections: function(role) {
        if (APP.roleSections && APP.roleSections.length) return APP.roleSections;
        return ROLE_SECTIONS[role] || ROLE_SECTIONS.admin;
    },

    showSection: function(id) {
        var role = APP.currentUser ? APP.currentUser.role : (getAuthRole() || 'admin');
        var allowed = window.AppCoreModule.getAllowedSections(role);
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
            'motos': 'Fiches moto',
            'clients': 'Clients',
            'espace-meca': 'Espace Mecanicien',
            'admin': 'Administration'
        };
        document.getElementById('page-title').textContent = titles[id] || id;

        var sectionToNav = {
            dashboard: 'nav-dashboard',
            rdv: 'nav-rdv',
            planning: 'nav-planning',
            ponts: 'nav-ponts',
            or: 'nav-or',
            suivi: 'nav-suivi',
            motos: 'nav-motos',
            clients: 'nav-clients',
            admin: 'nav-admin'
        };
        var activeNav = sectionToNav[id];
        if (activeNav) {
            var activeBtn = document.getElementById(activeNav);
            if (activeBtn) activeBtn.classList.add('active');
        }

        if (id === 'dashboard') loadDashboard();
        if (id === 'rdv') loadRdvForm();
        if (id === 'planning') loadPlanning();
        if (id === 'ponts') loadPontsMecas();
        if (id === 'or') loadOrdresReparation();
        if (id === 'suivi') loadSuiviLive();
        if (id === 'motos') loadMotoTechExplorer();
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
    },

    switchTab: function(el, tabId) {
        el.parentElement.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
        el.classList.add('active');
        var section = el.closest('.section');
        if (section) {
            section.querySelectorAll('.tab-content').forEach(function(tc) { tc.style.display = 'none'; });
        }
        var target = document.getElementById(tabId);
        if (target) target.style.display = 'block';
    },

    telechargerFacture: function(rdvId) {
        if (!window.AppCoreModule.canUseBilling()) {
            showAlert('Facturation desactivee pour le role service client', 'warning');
            return;
        }
        return openProtectedDocument('/api/rendez-vous/' + rdvId + '/facture-pdf', 'facture-' + rdvId + '.pdf').catch(function(error) {
            console.error('Erreur ouverture PDF facture:', error);
            showAlert('Impossible d\'ouvrir la facture PDF.', 'error');
            throw error;
        });
    },

    refreshCurrentSection: function() {
        if (APP.currentSection === 'dashboard') loadDashboard();
        else if (APP.currentSection === 'or') loadOrdresReparation();
        else if (APP.currentSection === 'suivi') loadSuiviLive();
        else if (APP.currentSection === 'espace-meca') loadEspaceMeca();
        else if (APP.currentSection === 'clients') loadClients();
        else if (APP.currentSection === 'motos') loadMotoTechExplorer();
        else if (APP.currentSection === 'ponts') loadPontsMecas();
        else if (APP.currentSection === 'planning') loadPlanning();
    }
};
