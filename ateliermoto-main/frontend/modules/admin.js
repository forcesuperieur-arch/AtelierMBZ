window.AdminModule = window.AdminModule || {
    loadAdminAteliers: function() {
        var canManageMotoBase = hasPermission('motos.manage');
        var motoTab = document.getElementById('admin-tab-moto-base');
        if (motoTab) motoTab.style.display = canManageMotoBase ? '' : 'none';
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
    },

    selectAdminAtelier: function(atelierId) {
        APP.adminSelectedAtelierId = atelierId;
        loadAdminAteliers();
    },

    loadAdminUsers: function() {
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
    },

    renderAdminUsers: function(users) {
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
    },

    switchAtelier: function(atelierId) {
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
    },

    ouvrirNouveauAtelier: function() {
        var html = '<div class="form-group"><label class="form-label">Nom</label><input id="new-atelier-nom" class="form-input"></div>' +
            '<div class="form-group"><label class="form-label">Slug</label><input id="new-atelier-slug" class="form-input"></div>' +
            '<div class="form-group"><label class="form-label">Ville</label><input id="new-atelier-ville" class="form-input"></div>' +
            '<button class="btn btn-primary" style="width:100%" onclick="creerAtelier()">Creer</button>';
        showModal('Nouvel atelier', html, '420px');
    },

    creerAtelier: function() {
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
    },

    ouvrirEditAtelier: function(id, nom) {
        var html = '<div class="form-group"><label class="form-label">Nom</label><input id="edit-atelier-nom" class="form-input" value="' + (nom || '') + '"></div>' +
            '<button class="btn btn-primary" style="width:100%" onclick="sauverAtelier(' + id + ')">Enregistrer</button>';
        showModal('Modifier atelier', html, '420px');
    },

    sauverAtelier: function(id) {
        apiPut('/api/ateliers/' + id, { nom: document.getElementById('edit-atelier-nom').value }).then(function() {
            closeModal();
            loadAdminAteliers();
            showNotificationToast('Atelier mis a jour');
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    ouvrirNouvelUtilisateurAtelier: function() {
        if (!hasPermission('users.manage')) {
            showAlert('Action non autorisee', 'error');
            return;
        }
        if (!APP.adminSelectedAtelierId) {
            alert('Selectionnez un atelier');
            return;
        }
        var roleOptions = '<option value="service_client">service_client (SRC)</option><option value="receptionnaire">receptionnaire</option><option value="mecanicien">mecanicien</option><option value="manager">manager</option><option value="admin">admin</option>' +
            (APP.currentUser && APP.currentUser.role === 'super_admin' ? '<option value="super_admin">super_admin</option>' : '');
        if (hasPermission('roles.manage')) {
            apiGet('/api/roles/permissions').then(function(r) { return r.json(); }).then(function(roles) {
                roleOptions = '';
                (roles || []).forEach(function(roleCfg) {
                    if (roleCfg.role === 'super_admin' && !(APP.currentUser && APP.currentUser.role === 'super_admin')) return;
                    roleOptions += '<option value="' + escapeHtml(roleCfg.role) + '">' + escapeHtml(roleCfg.role) + '</option>';
                });
                renderCreateUserModal(roleOptions);
            }).catch(function() {
                renderCreateUserModal(roleOptions);
            });
            return;
        }
        renderCreateUserModal(roleOptions);
    },

    renderCreateUserModal: function(roleOptionsHtml) {
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
    },

    toggleCreateUserMecaFields: function() {
        var role = (document.getElementById('new-user-role') || {}).value;
        var box = document.getElementById('new-user-meca-fields');
        if (box) box.style.display = role === 'mecanicien' ? '' : 'none';
    },

    creerUtilisateurAtelier: function() {
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
    },

    ouvrirEditionUtilisateurAtelier: function(userId) {
        var userRequest = apiGet('/api/users/' + userId).then(function(r) { return r.json(); });
        var rolesRequest = hasPermission('roles.manage')
            ? apiGet('/api/roles/permissions').then(function(r) { return r.json(); }).catch(function() { return null; })
            : Promise.resolve(null);

        Promise.all([userRequest, rolesRequest]).then(function(results) {
            var u = results[0];
            var roles = results[1];
            var roleOptions = '';
            if (Array.isArray(roles) && roles.length) {
                roles.forEach(function(roleCfg) {
                    if (roleCfg.role === 'super_admin' && !(APP.currentUser && APP.currentUser.role === 'super_admin')) return;
                    roleOptions += '<option value="' + escapeHtml(roleCfg.role) + '"' + (u.role === roleCfg.role ? ' selected' : '') + '>' + escapeHtml(roleCfg.role) + '</option>';
                });
            } else {
                roleOptions = '<option value="service_client"' + (u.role === 'service_client' ? ' selected' : '') + '>service_client (SRC)</option>' +
                    '<option value="receptionnaire"' + (u.role === 'receptionnaire' ? ' selected' : '') + '>receptionnaire</option>' +
                    '<option value="mecanicien"' + (u.role === 'mecanicien' ? ' selected' : '') + '>mecanicien</option>' +
                    '<option value="manager"' + (u.role === 'manager' ? ' selected' : '') + '>manager</option>' +
                    '<option value="admin"' + (u.role === 'admin' ? ' selected' : '') + '>admin</option>' +
                    (APP.currentUser && APP.currentUser.role === 'super_admin' ? '<option value="super_admin"' + (u.role === 'super_admin' ? ' selected' : '') + '>super_admin</option>' : '');
            }
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
    },

    toggleEditUserMecaFields: function() {
        var role = (document.getElementById('edit-user-role') || {}).value;
        var box = document.getElementById('edit-user-meca-fields');
        if (box) box.style.display = role === 'mecanicien' ? '' : 'none';
    },

    sauverEditionUtilisateurAtelier: function(userId) {
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
    },

    supprimerUtilisateurAtelier: function(userId) {
        if (!confirm('Supprimer ce login ?')) return;
        apiDelete('/api/users/' + userId).then(function() {
            loadAdminUsers();
            showNotificationToast('Utilisateur supprime');
        }).catch(function(e) { showAlert('Erreur: ' + e.message, 'error'); });
    },

    switchAdminTab: function(tabId) {
        var allowedTabs = {
            ateliers: hasPermission('ateliers.manage') || hasPermission('users.manage'),
            workshop: hasPermission('workshop.manage'),
            config: hasPermission('config.manage'),
            'moto-base': hasPermission('motos.manage'),
            horaires: hasPermission('horaires.manage') || hasPermission('config.manage'),
            prestations: hasPermission('prestations.manage'),
            roles: hasPermission('roles.manage')
        };
        var visibleTabs = Object.keys(allowedTabs).filter(function(id) { return allowedTabs[id]; });
        if (!allowedTabs[tabId]) tabId = visibleTabs[0] || 'ateliers';

        ['ateliers', 'workshop', 'config', 'moto-base', 'horaires', 'prestations', 'roles'].forEach(function(id) {
            var tab = document.getElementById('admin-tab-' + id);
            var panel = document.getElementById('admin-panel-' + id);
            var isAllowed = !!allowedTabs[id];
            if (tab) {
                tab.classList.toggle('active', isAllowed && id === tabId);
                tab.style.display = isAllowed ? '' : 'none';
            }
            if (panel) panel.style.display = isAllowed && id === tabId ? '' : 'none';
        });
        if (tabId === 'workshop') loadAdminWorkshop();
        if (tabId === 'config') loadAdminConfig();
        if (tabId === 'moto-base') loadAdminMotoBase();
        if (tabId === 'horaires') loadAdminHoraires();
        if (tabId === 'prestations') loadAdminPrestations();
        if (tabId === 'roles') loadAdminRoles();
    },

    loadAdminRoles: function() {
        var container = document.getElementById('admin-roles-content');
        if (!container) return;
        if (!hasPermission('roles.manage')) {
            container.innerHTML = '<div style="color:#888">Permission roles.manage requise.</div>';
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
    },

    openRoleEditor: function(roleName) {
        if (!hasPermission('roles.manage')) return;
        apiGet('/api/roles/permissions').then(function(r) { return r.json(); }).then(function(roles) {
            var existing = (roles || []).find(function(x) { return x.role === roleName; }) || null;
            if (existing && existing.role === 'super_admin') {
                showAlert('Le role super_admin est fige et non modifiable', 'warning');
                return;
            }
            var sections = ['dashboard', 'rdv', 'planning', 'ponts', 'or', 'suivi', 'motos', 'clients', 'espace-meca', 'admin'];
            var perms = ['billing.view', 'billing.edit', 'billing.pay', 'billing.pdf', 'travaux_supp.review', 'rdv.select_atelier', 'rdv.edit', 'workflow.manage', 'or.manage', 'workshop.manage', 'motos.manage', 'horaires.manage', 'clients.edit', 'stats.view', 'users.manage', 'ateliers.manage', 'roles.manage', 'config.manage', 'prestations.manage'];
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
    },

    saveRolePermission: function() {
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
    },

    normalizeRoleSlug: function(value) {
        return String(value || '').trim().toLowerCase().replace(/[\s-]+/g, '_').replace(/[^a-z0-9_]/g, '');
    },

    deleteRolePermission: function(role) {
        if (!confirm('Supprimer le role ' + role + ' ?')) return;
        apiDelete('/api/roles/permissions/' + role).then(function() {
            loadAdminRoles();
            showNotificationToast('Role supprime');
        }).catch(function(e) { showAlert('Erreur: ' + e.message, 'error'); });
    },

    loadAdminConfig: function() {
        if (!hasPermission('config.manage')) {
            showAlert('Action non autorisee', 'error');
            return;
        }
        var canManageMotoBase = hasPermission('motos.manage');
        var motoTab = document.getElementById('admin-tab-moto-base');
        if (motoTab) motoTab.style.display = canManageMotoBase ? '' : 'none';
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
    },

    saveAdminConfig: function(e) {
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
    },

    loadAdminCategoriesMoto: function() {
        var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
        var container = document.getElementById('admin-categories-moto-list');
        var canManageMoto = hasPermission('motos.manage');
        if (!container) return;
        apiGet('/api/config/categories-moto' + qs).then(function(r) { return r.json(); }).then(function(cats) {
            if (!cats || cats.length === 0) {
                container.innerHTML = '<p style="color:#666">Aucune categorie trouvee</p>';
                return;
            }
            var html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px">';
            cats.forEach(function(cat) {
                var activeClass = cat.is_active ? 'background:#10b981;color:white' : 'background:#374151;color:#9ca3af';
                html += '<div style="display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:8px;border:1px solid #374151;cursor:' + (canManageMoto ? 'pointer' : 'default') + '"' +
                    (canManageMoto ? ' onclick="toggleAdminCategorieMoto(' + cat.id + ')"' : '') + '>' +
                    '<span style="display:inline-block;width:12px;height:12px;border-radius:50%;' + activeClass + ';flex-shrink:0"></span>' +
                    '<span style="font-size:14px">' + escapeHtml(cat.nom) + '</span>' +
                    '</div>';
            });
            html += '</div>';
            container.innerHTML = html;
        }).catch(function() {
            container.innerHTML = '<p style="color:#ef4444">Erreur chargement</p>';
        });
    },

    toggleAdminCategorieMoto: function(categorieId) {
        if (!hasPermission('motos.manage')) {
            showAlert('Permission motos.manage requise', 'error');
            return;
        }
        var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
        apiPut('/api/config/categories-moto/' + categorieId + '/toggle' + qs, {}).then(function(r) { return r.json(); }).then(function(d) {
            showNotificationToast(d.is_active ? 'Type moto active' : 'Type moto desactive');
            loadAdminCategoriesMoto();
        }).catch(function(e) { showAlert('Erreur: ' + (e.message || 'toggle'), 'error'); });
    },

    loadAdminMotoBase: function() {
        var card = document.getElementById('admin-moto-base-card');
        var tbody = document.getElementById('admin-moto-base-tbody');
        var count = document.getElementById('admin-moto-base-count');
        if (!card || !tbody) return;
        if (!hasPermission('motos.manage')) {
            card.style.display = 'none';
            return;
        }
        card.style.display = '';
        var marque = (document.getElementById('admin-moto-base-marque').value || '').trim();
        var search = (document.getElementById('admin-moto-base-search').value || '').trim();
        tbody.innerHTML = '<tr><td colspan="6" style="color:#888">Chargement...</td></tr>';
        var params = [];
        if (marque) params.push('marque=' + encodeURIComponent(marque));
        if (search) params.push('search=' + encodeURIComponent(search));
        var url = '/api/motos/modeles' + (params.length ? '?' + params.join('&') : '');
        apiGet(url).then(function(r) { return r.json(); }).then(function(items) {
            var rows = Array.isArray(items) ? items.slice(0, 80) : [];
            if (count) count.textContent = (Array.isArray(items) ? items.length : 0) + ' modèle(s)';
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="color:#888">Aucun modèle trouvé.</td></tr>';
                return;
            }
            var html = '';
            rows.forEach(function(item) {
                html += '<tr>' +
                    '<td>' + escapeHtml(item.marque || '-') + '</td>' +
                    '<td><b>' + escapeHtml(item.modele || '-') + '</b></td>' +
                    '<td>' + escapeHtml(item.categorie_nom || '-') + '</td>' +
                    '<td>' + escapeHtml(item.cylindree_display || '-') + '</td>' +
                    '<td>' + escapeHtml(item.annees_display || '-') + '</td>' +
                    '<td><button class="btn btn-ghost" onclick="openAdminMotoModeleModal(' + item.id + ')">Editer</button> <button class="btn btn-ghost" onclick="deleteAdminMotoModele(' + item.id + ')">Supprimer</button></td>' +
                    '</tr>';
            });
            tbody.innerHTML = html;
        }).catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="6" style="color:#ef4444">Erreur: ' + escapeHtml(e.message || 'chargement') + '</td></tr>';
        });
    },

    openAdminMotoModeleModal: function(modeleId) {
        if (!hasPermission('motos.manage')) {
            showAlert('Permission motos.manage requise', 'error');
            return;
        }
        Promise.all([
            apiGet('/api/motos/categories').then(function(r) { return r.json(); }).catch(function() { return []; }),
            modeleId ? apiGet('/api/motos/modeles/' + modeleId).then(function(r) { return r.json(); }) : Promise.resolve(null)
        ]).then(function(results) {
            var categories = Array.isArray(results[0]) ? results[0] : [];
            var modele = results[1] || null;
            var catOptions = categories.map(function(cat) {
                var selected = modele && Number(modele.categorie_id) === Number(cat.id) ? ' selected' : '';
                return '<option value="' + cat.id + '"' + selected + '>' + escapeHtml(cat.nom) + '</option>';
            }).join('');
            var html = '' +
                '<div class="form-group"><label class="form-label">Marque</label><input id="adm-moto-marque" class="form-input" value="' + escapeAttr((modele && modele.marque) || '') + '"></div>' +
                '<div class="form-group"><label class="form-label">Modèle</label><input id="adm-moto-modele" class="form-input" value="' + escapeAttr((modele && modele.modele) || '') + '"></div>' +
                '<div class="form-group"><label class="form-label">Catégorie</label><select id="adm-moto-categorie" class="form-select"><option value="">-- Sélectionnez --</option>' + catOptions + '</select></div>' +
                '<div class="form-grid">' +
                    '<div class="form-group"><label class="form-label">Cylindrée min</label><input id="adm-moto-cyl-min" type="number" class="form-input" value="' + escapeAttr(String((modele && modele.cylindree_min) || '')) + '"></div>' +
                    '<div class="form-group"><label class="form-label">Cylindrée max</label><input id="adm-moto-cyl-max" type="number" class="form-input" value="' + escapeAttr(String((modele && modele.cylindree_max) || '')) + '"></div>' +
                    '<div class="form-group"><label class="form-label">Année début</label><input id="adm-moto-annee-debut" type="number" class="form-input" value="' + escapeAttr(String((modele && modele.annee_debut) || '')) + '"></div>' +
                    '<div class="form-group"><label class="form-label">Année fin</label><input id="adm-moto-annee-fin" type="number" class="form-input" value="' + escapeAttr(String((modele && modele.annee_fin) || '')) + '"></div>' +
                '</div>' +
                '<button class="btn btn-primary" style="width:100%" onclick="saveAdminMotoModele(' + (modeleId || 'null') + ')">' + (modeleId ? 'Enregistrer' : 'Créer le modèle') + '</button>';
            showModal(modeleId ? 'Editer modèle moto' : 'Ajouter modèle moto', html, '560px');
        }).catch(function(e) {
            showAlert('Erreur base moto: ' + (e.message || 'chargement'), 'error');
        });
    },

    saveAdminMotoModele: function(modeleId) {
        var payload = {
            marque: (document.getElementById('adm-moto-marque').value || '').trim().toUpperCase(),
            modele: (document.getElementById('adm-moto-modele').value || '').trim(),
            categorie_id: parseInt(document.getElementById('adm-moto-categorie').value || '0', 10),
            cylindree_min: parseInt(document.getElementById('adm-moto-cyl-min').value || '0', 10) || null,
            cylindree_max: parseInt(document.getElementById('adm-moto-cyl-max').value || '0', 10) || null,
            annee_debut: parseInt(document.getElementById('adm-moto-annee-debut').value || '0', 10) || null,
            annee_fin: parseInt(document.getElementById('adm-moto-annee-fin').value || '0', 10) || null
        };
        if (!payload.marque || !payload.modele || !payload.categorie_id) {
            showAlert('Marque, modèle et catégorie sont obligatoires', 'error');
            return;
        }
        var request = modeleId ? apiPut('/api/motos/modeles/' + modeleId, payload) : apiPost('/api/motos/modeles', payload);
        request.then(function(r) { return r.json(); }).then(function() {
            closeModal();
            loadAdminMotoBase();
            showNotificationToast(modeleId ? 'Modèle mis à jour' : 'Modèle créé');
        }).catch(function(e) {
            showAlert('Erreur base moto: ' + (e.message || 'sauvegarde'), 'error');
        });
    },

    deleteAdminMotoModele: function(modeleId) {
        if (!confirm('Supprimer ce modèle de la base moto ?')) return;
        apiDelete('/api/motos/modeles/' + modeleId).then(function(r) { return r.json(); }).then(function() {
            loadAdminMotoBase();
            showNotificationToast('Modèle supprimé');
        }).catch(function(e) {
            showAlert('Erreur base moto: ' + (e.message || 'suppression'), 'error');
        });
    },

    adminFmtTime: function(value) {
        if (!value) return '';
        return String(value).substring(0, 5);
    },

    loadAdminHoraires: function() {
        if (!(hasPermission('horaires.manage') || hasPermission('config.manage'))) {
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
    },

    saveAdminHoraire: function(jour) {
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
    },

    toggleAdminMidi: function(jour) {
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
    },

    adminFormatMinutes: function(mins) {
        var total = parseInt(mins || 0, 10);
        if (!total) return '-';
        var h = Math.floor(total / 60);
        var m = total % 60;
        return (h > 0 ? h + 'h' : '') + (m > 0 ? m + 'min' : (h > 0 ? '00' : ''));
    },

    loadAdminPrestations: function() {
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
    },

    openAdminPrestationModal: function(id) {
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
    },

    saveAdminPrestation: function(id, tvaMo) {
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
    },

    toggleAdminPrestation: function(id, state) {
        var qs = APP.adminSelectedAtelierId ? ('?atelier_id=' + encodeURIComponent(APP.adminSelectedAtelierId)) : '';
        apiPut('/api/config/prestations/' + id + qs, { is_active: state }).then(function() {
            loadAdminPrestations();
            showNotificationToast(state ? 'Prestation reactivee' : 'Prestation desactivee');
        }).catch(function(e) { showAlert('Erreur: ' + (e.message || 'maj'), 'error'); });
    },

    openAdminGrilleModal: function(prestationId) {
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
    },

    saveAdminGrille: function(prestationId, tvaMo) {
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
    },

    loadAdminWorkshop: function() {
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
    },

    renderAdminWorkshopPonts: function(ponts, mecas) {
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
    },

    renderAdminWorkshopMecas: function(mecas, users) {
        var tbody = document.getElementById('admin-workshop-mecas-tbody');
        if (!tbody) return;
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
    },

    openAdminPontModal: function(pontId) {
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
    },

    saveAdminPont: function(pontId) {
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
    },

    deleteAdminPont: function(pontId) {
        openConfirmDialog('Supprimer ce pont ?', function() {
            apiDelete('/api/ponts/' + pontId).then(function() {
                loadBaseData().then(function() { loadAdminWorkshop(); });
                showNotificationToast('Pont supprime');
            }).catch(function(e) { showAlert('Erreur suppression pont: ' + (e.message || 'suppression'), 'error'); });
        });
    },

    deleteAdminMecanicien: function(mecanicienId) {
        openConfirmDialog('Supprimer ce technicien ?', function() {
            apiDelete('/api/mecaniciens/' + mecanicienId).then(function() {
                loadBaseData().then(function() { loadAdminWorkshop(); });
                showNotificationToast('Technicien supprime');
            }).catch(function(e) { showAlert('Erreur suppression technicien: ' + (e.message || 'suppression'), 'error'); });
        });
    },

    
};
