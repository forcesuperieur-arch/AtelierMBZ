window.AbsencesModule = window.AbsencesModule || {
    loadAbsences: function() {
        apiGet('/api/absences').then(function(r) {
            return r.json();
        }).then(function(absences) {
            APP._absences = absences || [];
            window.AbsencesModule.renderAbsencesTable(APP._absences);
        }).catch(function() {
            var container = document.getElementById('absences-table');
            if (container) {
                container.innerHTML = '<tr><td colspan="6" style="color:#666">Erreur chargement</td></tr>';
            }
        });
    },

    renderAbsencesTable: function(absences) {
        var container = document.getElementById('absences-table');
        var countEl = document.getElementById('absences-count');
        var items = absences || [];
        if (countEl) countEl.textContent = items.length;
        if (!container) return;
        if (!items.length) {
            container.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#666;padding:20px">Aucune absence planifiee</td></tr>';
            return;
        }
        var html = '';
        items.forEach(function(a) {
            var motifMap = {
                'conge': { cls: 'green', label: 'Conge' },
                'maladie': { cls: 'red', label: 'Maladie' },
                'formation': { cls: 'blue', label: 'Formation' },
                'autre': { cls: 'amber', label: 'Autre' }
            };
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
    },

    ouvrirModalAbsence: function() {
        var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Mecanicien</label><select id="abs-meca" class="form-select">';
        (APP.mecaniciens || []).forEach(function(m) {
            if (!isActive(m)) return;
            html += '<option value="' + m.id + '">' + (escapeHtml(m.prenom) || '') + ' ' + (escapeHtml(m.nom) || '') + '</option>';
        });
        html += '</select></div>' +
            '<div style="display:flex;gap:12px"><div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Date debut</label><input type="date" id="abs-debut" class="form-input"></div>' +
            '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Date fin</label><input type="date" id="abs-fin" class="form-input"></div></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Motif</label><select id="abs-motif" class="form-select"><option value="conge">Conge</option><option value="maladie">Maladie</option><option value="formation">Formation</option><option value="autre">Autre</option></select></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Notes</label><textarea id="abs-notes" class="form-input" rows="2" placeholder="Optionnel..."></textarea></div>' +
            '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="sauverAbsence()">Enregistrer l\'absence</button>';
        showModal('Ajouter une absence', html, '450px');
    },

    sauverAbsence: function() {
        var data = {
            mecanicien_id: parseInt((document.getElementById('abs-meca') || {}).value, 10),
            date_debut: (document.getElementById('abs-debut') || {}).value,
            date_fin: (document.getElementById('abs-fin') || {}).value,
            motif: (document.getElementById('abs-motif') || {}).value,
            notes: ((document.getElementById('abs-notes') || {}).value || '') || null
        };
        if (!data.date_debut || !data.date_fin) {
            alert('Dates obligatoires');
            return;
        }
        apiPost('/api/absences', data).then(function(r) {
            return r.json();
        }).then(function() {
            closeModal();
            window.AbsencesModule.loadAbsences();
        }).catch(function(e) {
            alert('Erreur: ' + e.message);
        });
    },

    ouvrirModalEditAbsence: function(absenceId) {
        var abs = (APP._absences || []).find(function(a) { return a.id === absenceId; });
        if (!abs) {
            showAlert('Absence introuvable', 'error');
            return;
        }
        var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Mecanicien</label><select id="abs-edit-meca" class="form-select">';
        (APP.mecaniciens || []).forEach(function(m) {
            if (!isActive(m)) return;
            html += '<option value="' + m.id + '"' + (m.id === abs.mecanicien_id ? ' selected' : '') + '>' + (escapeHtml(m.prenom) || '') + ' ' + (escapeHtml(m.nom) || '') + '</option>';
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
    },

    sauverEditAbsence: function(absenceId) {
        var data = {
            mecanicien_id: parseInt((document.getElementById('abs-edit-meca') || {}).value, 10),
            date_debut: (document.getElementById('abs-edit-debut') || {}).value,
            date_fin: (document.getElementById('abs-edit-fin') || {}).value,
            motif: (document.getElementById('abs-edit-motif') || {}).value,
            notes: ((document.getElementById('abs-edit-notes') || {}).value || '') || null
        };
        if (!data.date_debut || !data.date_fin) {
            showAlert('Dates obligatoires', 'warning');
            return;
        }
        apiPut('/api/absences/' + absenceId, data).then(function(r) {
            return r.json();
        }).then(function() {
            closeModal();
            showNotificationToast('Absence modifiee');
            window.AbsencesModule.loadAbsences();
            if (APP.currentSection === 'ponts') loadPontsMecas();
        }).catch(function(e) {
            alert('Erreur: ' + e.message);
        });
    },

    supprimerAbsence: function(id) {
        openConfirmDialog('Supprimer cette absence ?', function() {
            apiDelete('/api/absences/' + id).then(function() {
                window.AbsencesModule.loadAbsences();
            }).catch(function(e) {
                alert('Erreur: ' + e.message);
            });
        });
    }
};
