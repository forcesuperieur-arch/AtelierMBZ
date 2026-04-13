window.RdvActionsModule = window.RdvActionsModule || {
    hasGeneratedOr: function(rdv) {
        if (!rdv) return false;
        if (Array.isArray(rdv.ordres_reparation) && rdv.ordres_reparation.length > 0) return true;
        if (rdv.or_id || rdv.or_numero || rdv.has_or) return true;
        if (window.OrModule && typeof window.OrModule.getLatestOrdreInfo === 'function') {
            return !!window.OrModule.getLatestOrdreInfo(rdv);
        }
        return false;
    },

    syncRdvState: function(rdvId, patch) {
        function applyTo(list) {
            if (!Array.isArray(list)) return;
            for (var i = 0; i < list.length; i++) {
                if (list[i] && list[i].id === rdvId) {
                    Object.assign(list[i], patch || {});
                }
            }
        }
        applyTo(APP.rdvs);
        applyTo(APP.planningRdvs);
        applyTo(APP._mecaLastRdvs);
    },

    applyImmediateRefresh: function(rdvId, patch, message, options) {
        window.RdvActionsModule.syncRdvState(rdvId, patch || {});
        if (!(options && options.keepModalOpen)) closeModal();
        if (message && typeof showNotificationToast === 'function') showNotificationToast(message);
        refreshCurrentSection();
        setTimeout(function() {
            refreshCurrentSection();
        }, 250);
    },

    confirmerRdv: function(rdvId) {
        apiPut('/api/rendez-vous/' + rdvId, { statut: 'confirme' }).then(function() {
            window.RdvActionsModule.applyImmediateRefresh(rdvId, { statut: 'confirme' }, 'RDV confirme');
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    demarrerTravail: function(rdvId) {
        APP._mecaSelectedRdvId = rdvId;
        apiPost('/api/rendez-vous/' + rdvId + '/demarrer-travail', {}).then(function() {
            window.RdvActionsModule.applyImmediateRefresh(rdvId, { statut: 'en_cours' }, 'Intervention demarree');
            setTimeout(function() {
                var panel = document.getElementById('meca-active-panel');
                if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 400);
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    terminerTravail: function(rdvId) {
        var points = window._checkupPoints || {};
        var hasCheckedPoint = Object.keys(points).some(function(k) { return points[k] !== 'non_verifie'; });
        var notes = ((document.getElementById('meca-notes') || {}).value || '').trim();

        if (!hasCheckedPoint && !notes) {
            var confirmed = window.confirm("Aucun point de checkup ni note n'a ete saisi. Terminer quand meme ?");
            if (!confirmed) return;
        }

        apiPost('/api/rendez-vous/' + rdvId + '/terminer-avec-rapport', {
            points_controle: points,
            alertes: notes,
            recommandations: '',
            travaux_realises: '',
            statut: 'termine'
        }).then(function(r) {
            return r.json();
        }).then(function(result) {
            window._checkupPoints = {};
            APP._mecaCheckupData = result && result.rapport ? result.rapport : null;
            window.RdvActionsModule.applyImmediateRefresh(
                rdvId,
                {
                    statut: 'termine',
                    heure_fin_travail: result ? result.heure_fin : null,
                    temps_effectif_minutes: result ? result.temps_effectif_minutes : null
                },
                'Intervention terminee'
            );
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    restituerRdv: function(rdvId) {
        return apiPost('/api/rendez-vous/' + rdvId + '/restituer', {}).then(function(r) {
            return r.json();
        }).then(function() {
            window.RdvActionsModule.applyImmediateRefresh(rdvId, { statut: 'restitue' }, 'RDV cloture apres restitution');
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    annulerRdv: function(rdvId) {
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
    },

    toggleCancelOtherReason: function() {
        var reason = (document.getElementById('cancel-reason') || {}).value || '';
        var wrap = document.getElementById('cancel-other-wrap');
        if (wrap) wrap.style.display = reason === 'autre' ? '' : 'none';
    },

    confirmCancelRdv: function(rdvId) {
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
            window.RdvActionsModule.applyImmediateRefresh(
                rdvId,
                { statut: finalStatus, commentaire: commentaire },
                reason === 'non_presente' ? 'RDV marque non presente' : 'RDV annule'
            );
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    actionButtons: function(rdv, compact, options) {
        var s = rdv.statut;
        var sz = compact ? 'font-size:11px;padding:4px 8px;' : 'font-size:12px;padding:5px 10px;';
        var html = '<div style="display:flex;gap:4px;flex-wrap:wrap">';
        var canEditRdv = hasPermission('rdv.edit');
        var canManageWorkflow = hasPermission('workflow.manage') || hasPermission('or.manage') || hasPermission('workshop.manage');
        var hasOr = window.RdvActionsModule.hasGeneratedOr(rdv);

        if (canEditRdv && s !== 'annule' && s !== 'non_presente' && s !== 'paye' && s !== 'restitue') {
            html += '<button class="btn btn-ghost" style="' + sz + 'color:var(--teal)" onclick="ouvrirDetailRdv(' + rdv.id + ')">Editer</button>';
        }

        if (canManageWorkflow && s !== 'annule' && s !== 'non_presente' && s !== 'termine' && s !== 'restitue' && s !== 'facture' && s !== 'paye') {
            html += '<button class="btn btn-ghost" style="' + sz + 'color:var(--purple)" onclick="ouvrirAssignation(' + rdv.id + ')">' + (rdv.mecanicien_id && rdv.pont_id ? 'Reassigner' : 'Assigner') + '</button>';
        }

        if (canManageWorkflow && (s === 'reserve' || s === 'en_attente')) {
            html += '<button class="btn btn-primary" style="' + sz + '" onclick="confirmerRdv(' + rdv.id + ')">Confirmer</button>';
            html += '<button class="btn btn-ghost" style="' + sz + '" onclick="annulerRdv(' + rdv.id + ')">Annuler</button>';
        } else if (canManageWorkflow && s === 'confirme') {
            html += '<button class="btn btn-primary" style="' + sz + 'background:var(--teal)" onclick="ouvrirReception(' + rdv.id + ')">Reception</button>';
            html += '<button class="btn btn-ghost" style="' + sz + '" onclick="annulerRdv(' + rdv.id + ')">Annuler</button>';
        } else if (canManageWorkflow && s === 'reception') {
            html += '<button class="btn btn-primary" style="' + sz + 'background:var(--green)" onclick="demarrerTravail(' + rdv.id + ')">Demarrer</button>';
        } else if (canManageWorkflow && s === 'en_cours') {
            html += '<button class="btn btn-primary" style="' + sz + 'background:var(--green)" onclick="terminerTravail(' + rdv.id + ')">Terminer</button>';
            html += '<button class="btn btn-ghost" style="' + sz + '" onclick="ouvrirCheckup(' + rdv.id + ')">Checkup</button>';
        } else if (s === 'termine') {
            html += '<button class="btn btn-primary" style="' + sz + 'background:var(--teal)" onclick="restituerRdv(' + rdv.id + ')">Cloturer</button>';
            if (hasOr) {
                html += '<button class="btn btn-ghost" style="' + sz + '" onclick="telechargerOR(' + rdv.id + ')">OR PDF</button>';
            }
        } else if (s === 'restitue' || s === 'facture' || s === 'paye') {
            if (hasOr) {
                html += '<button class="btn btn-ghost" style="' + sz + '" onclick="telechargerOR(' + rdv.id + ')">OR</button>';
            }
        }

        if ((s === 'confirme' || s === 'reception' || s === 'en_cours') && hasOr) {
            html += '<button class="btn btn-ghost" style="' + sz + '" onclick="telechargerOR(' + rdv.id + ')">OR</button>';
        }

        // Rappel email — visible dès que le RDV est confirmé et pas clos
        if (canEditRdv && ['confirme', 'reception', 'en_cours', 'reserve', 'en_attente'].indexOf(s) !== -1) {
            html += '<button class="btn btn-ghost" style="' + sz + 'color:#3b82f6" onclick="envoyerRappelRdv(' + rdv.id + ')" title="Envoyer un rappel email au client">✉ Rappel</button>';
        }

        html += '</div>';
        return html;
    },

    ouvrirAssignation: function(rdvId) {
        apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
            window.RdvActionsModule.renderAssignationModal(rdvId, rdv);
        }).catch(function() {
            window.RdvActionsModule.renderAssignationModal(rdvId, null);
        });
    },

    renderAssignationModal: function(rdvId, rdv) {
        var html = '';
        var isFinalized = !!(rdv && ['termine', 'restitue', 'facture', 'paye'].indexOf((rdv.statut || '')) !== -1);
        var disabled = isFinalized ? ' disabled' : '';
        var note = isFinalized
            ? '<div style="font-size:12px;color:#fbbf24;margin-bottom:8px">RDV finalise: l\'historique pont/technicien est verrouille.</div>'
            : '<div style="font-size:12px;color:#9ca3af;margin-bottom:8px">Si vous changez le technicien, le pont se met automatiquement sur son pont affecte.</div>';
        html += note;

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
    },

    sauverAssignation: function(rdvId) {
        var mecaId = document.getElementById('assign-meca').value;
        var data = {};
        data.mecanicien_id = mecaId ? parseInt(mecaId, 10) : null;

        apiPut('/api/rendez-vous/' + rdvId, data).then(function() {
            window.RdvActionsModule.applyImmediateRefresh(rdvId, data, 'Assignation mise a jour');
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    }
};