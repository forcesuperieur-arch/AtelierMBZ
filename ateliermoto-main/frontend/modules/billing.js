window.BillingModule = window.BillingModule || {
    _facturePreview: null,

    asNumber: function(value) {
        var num = Number(value);
        return isNaN(num) ? 0 : num;
    },

    ouvrirFacturation: function(rdvId) {
        if (!canUseBilling()) {
            showAlert('Facturation desactivee pour le role service client', 'warning');
            return;
        }
        apiGet('/api/rendez-vous/' + rdvId + '/preview-facture').then(function(r) {
            return r.json();
        }).then(function(data) {
            var html = '<div style="background:#1e1e1e;border-radius:8px;padding:16px">';
            var heures = (window.BillingModule.asNumber(data.temps_facture_minutes) / 60).toFixed(2);

            html += '<div style="margin-bottom:12px"><div style="color:#9ca3af;font-size:11px;margin-bottom:4px">MAIN D\'OEUVRE</div>';
            if (data.is_forfait) {
                html += '<div style="display:flex;justify-content:space-between;color:#e5e7eb">';
                html += '<span>Forfait - ' + (escapeHtml(data.forfait_designation || data.type_intervention) || '-') + '</span>';
                html += '<span style="font-weight:bold">' + window.BillingModule.asNumber(data.total_mo_ht).toFixed(2) + ' EUR HT</span></div>';
            } else {
                html += '<div style="display:flex;justify-content:space-between;color:#e5e7eb">';
                html += '<span>' + window.BillingModule.asNumber(data.temps_facture_minutes) + ' min (' + heures + 'h) x ' + window.BillingModule.asNumber(data.taux_horaire).toFixed(2) + ' EUR/h</span>';
                html += '<span style="font-weight:bold">' + window.BillingModule.asNumber(data.total_mo_ht).toFixed(2) + ' EUR HT</span></div>';
            }
            html += '</div>';

            if (data.pieces && data.pieces.length > 0) {
                html += '<div style="margin-bottom:12px"><div style="color:#9ca3af;font-size:11px;margin-bottom:4px">PIECES</div>';
                data.pieces.forEach(function(p) {
                    html += '<div style="display:flex;justify-content:space-between;color:#e5e7eb;font-size:13px">';
                    html += '<span>' + (escapeHtml(p.nom) || '-') + (p.reference ? ' (' + escapeHtml(p.reference) + ')' : '') + ' x' + window.BillingModule.asNumber(p.quantite) + '</span>';
                    html += '<span>' + window.BillingModule.asNumber(p.total_ht).toFixed(2) + ' EUR HT</span></div>';
                });
                html += '<div style="display:flex;justify-content:space-between;color:#e5e7eb;font-weight:bold;margin-top:4px;border-top:1px solid #333;padding-top:4px">';
                html += '<span>Total pieces</span><span>' + window.BillingModule.asNumber(data.total_pieces_ht).toFixed(2) + ' EUR HT</span></div></div>';
            }

            html += '<div style="border-top:1px solid #444;margin:12px 0"></div>';
            html += '<div class="form-group" style="margin-bottom:12px"><label class="form-label" style="color:#9ca3af">Remise (%)</label>';
            html += '<input type="number" class="form-input" id="facture-remise" value="0" min="0" max="100" step="0.5" onchange="recalcFacturePreview()" oninput="recalcFacturePreview()" style="width:100px"></div>';
            html += '<div id="facture-totaux">' + window.BillingModule.renderFactureTotaux(data, 0) + '</div>';
            html += '<div class="form-group" style="margin-top:12px"><label class="form-label" style="color:#9ca3af">Notes (optionnel)</label>';
            html += '<textarea class="form-input" id="facture-notes" rows="2" placeholder="Notes internes..."></textarea></div>';
            html += '</div>';
            html += '<div style="display:flex;gap:8px;margin-top:16px">';
            html += '<button class="btn btn-ghost" style="flex:1" onclick="closeModal()">Annuler</button>';
            html += '<button class="btn btn-primary" style="flex:1;background:#8B5CF6" onclick="confirmerFacturation(' + rdvId + ')">Generer la facture</button>';
            html += '</div>';

            window.BillingModule._facturePreview = data;
            showModal('Facturer - ' + (escapeHtml(data.type_intervention) || ('RDV #' + rdvId)), html, '550px');
        }).catch(function(e) {
            showAlert('Erreur: ' + e.message, 'error');
        });
    },

    renderFactureTotaux: function(data, remisePct) {
        var totalHt = window.BillingModule.asNumber(data.total_ht);
        var tvaMoBase = window.BillingModule.asNumber(data.tva_mo);
        var tvaPiecesBase = window.BillingModule.asNumber(data.tva_pieces);
        var remise = totalHt * (window.BillingModule.asNumber(remisePct) / 100);
        var totalHtRemise = totalHt - remise;
        var ratio = totalHt > 0 ? totalHtRemise / totalHt : 1;
        var tvaMo = tvaMoBase * ratio;
        var tvaPieces = tvaPiecesBase * ratio;
        var totalTtc = totalHtRemise + tvaMo + tvaPieces;

        var html = '<div style="background:#111;border-radius:6px;padding:12px">';
        html += '<div style="display:flex;justify-content:space-between;color:#9ca3af;font-size:13px"><span>Total HT</span><span>' + totalHt.toFixed(2) + ' EUR</span></div>';
        if (remise > 0) {
            html += '<div style="display:flex;justify-content:space-between;color:#22C55E;font-size:13px"><span>Remise (' + window.BillingModule.asNumber(remisePct).toFixed(1) + '%)</span><span>-' + remise.toFixed(2) + ' EUR</span></div>';
            html += '<div style="display:flex;justify-content:space-between;color:#e5e7eb;font-size:13px;font-weight:bold"><span>Total HT remise</span><span>' + totalHtRemise.toFixed(2) + ' EUR</span></div>';
        }
        html += '<div style="display:flex;justify-content:space-between;color:#9ca3af;font-size:12px"><span>TVA MO (' + window.BillingModule.asNumber(data.tva_mo_taux) + '%)</span><span>' + tvaMo.toFixed(2) + ' EUR</span></div>';
        if (tvaPiecesBase > 0) {
            html += '<div style="display:flex;justify-content:space-between;color:#9ca3af;font-size:12px"><span>TVA Pieces (' + window.BillingModule.asNumber(data.tva_pieces_taux) + '%)</span><span>' + tvaPieces.toFixed(2) + ' EUR</span></div>';
        }
        html += '<div style="display:flex;justify-content:space-between;color:#E8480A;font-size:16px;font-weight:bold;margin-top:8px;border-top:1px solid #333;padding-top:8px"><span>TOTAL TTC</span><span>' + totalTtc.toFixed(2) + ' EUR</span></div>';
        html += '</div>';
        return html;
    },

    recalcFacturePreview: function() {
        var remiseEl = document.getElementById('facture-remise');
        var totalsEl = document.getElementById('facture-totaux');
        if (!totalsEl || !window.BillingModule._facturePreview) return;
        var remise = remiseEl ? parseFloat(remiseEl.value) || 0 : 0;
        totalsEl.innerHTML = window.BillingModule.renderFactureTotaux(window.BillingModule._facturePreview, remise);
    },

    confirmerFacturation: function(rdvId) {
        if (!canUseBilling()) {
            showAlert('Facturation desactivee pour le role service client', 'warning');
            return;
        }
        var remiseEl = document.getElementById('facture-remise');
        var notesEl = document.getElementById('facture-notes');
        var remise = remiseEl ? parseFloat(remiseEl.value) || 0 : 0;
        var notes = notesEl ? notesEl.value : '';
        apiPost('/api/rendez-vous/' + rdvId + '/facturer', {
            remise_pourcentage: remise,
            notes: notes || null
        }).then(function(r) {
            return r.json();
        }).then(function(data) {
            closeModal();
            showAlert('Facture ' + data.numero_facture + ' creee - ' + window.BillingModule.asNumber(data.total_ttc).toFixed(2) + ' EUR TTC');
            refreshCurrentSection();
        }).catch(function(e) {
            showAlert('Erreur: ' + e.message, 'error');
        });
    },

    ouvrirEncaissement: function(rdvId) {
        if (!canUseBilling()) {
            showAlert('Encaissement desactive pour le role service client', 'warning');
            return;
        }
        apiGet('/api/factures/par-rdv/' + rdvId).then(function(r) {
            return r.json();
        }).then(function(facture) {
            var totalTtc = window.BillingModule.asNumber(facture.total_ttc);
            var montantPaye = window.BillingModule.asNumber(facture.montant_paye);
            var montantRestant = window.BillingModule.asNumber(facture.montant_restant);
            var html = '<div style="background:#1e1e1e;border-radius:8px;padding:16px">';

            html += '<div style="display:flex;justify-content:space-between;margin-bottom:16px">';
            html += '<div><div style="color:#9ca3af;font-size:11px">FACTURE</div><div style="color:#e5e7eb;font-size:16px;font-weight:bold">' + (escapeHtml(facture.numero_facture) || '-') + '</div></div>';
            html += '<div style="text-align:right"><div style="color:#9ca3af;font-size:11px">TOTAL TTC</div><div style="color:#E8480A;font-size:16px;font-weight:bold">' + totalTtc.toFixed(2) + ' EUR</div></div>';
            html += '</div>';

            if (montantPaye > 0) {
                html += '<div style="background:#111;border-radius:6px;padding:10px;margin-bottom:12px">';
                html += '<div style="display:flex;justify-content:space-between;color:#22C55E;font-size:13px"><span>Deja paye</span><span>' + montantPaye.toFixed(2) + ' EUR</span></div>';
                html += '<div style="display:flex;justify-content:space-between;color:#EF4444;font-size:14px;font-weight:bold;margin-top:4px"><span>Reste a payer</span><span>' + montantRestant.toFixed(2) + ' EUR</span></div>';
                html += '</div>';
            }

            html += '<div class="form-group"><label class="form-label" style="color:#9ca3af">Mode de paiement</label>';
            html += '<select class="form-select" id="enc-mode" onchange="toggleEncReference()">';
            html += '<option value="cb">Carte bancaire</option>';
            html += '<option value="especes">Especes</option>';
            html += '<option value="cheque">Cheque</option>';
            html += '<option value="virement">Virement bancaire</option>';
            html += '<option value="differe">Paiement differe</option>';
            html += '</select></div>';

            html += '<div class="form-group"><label class="form-label" style="color:#9ca3af">Montant</label>';
            html += '<input type="number" class="form-input" id="enc-montant" value="' + montantRestant.toFixed(2) + '" min="0.01" max="' + montantRestant.toFixed(2) + '" step="0.01"></div>';

            html += '<div class="form-group" id="enc-ref-group" style="display:none"><label class="form-label" style="color:#9ca3af">Reference (n cheque / ref virement)</label>';
            html += '<input type="text" class="form-input" id="enc-reference" placeholder="N° cheque ou reference virement"></div>';

            html += '<div class="form-group"><label class="form-label" style="color:#9ca3af">Notes (optionnel)</label>';
            html += '<input type="text" class="form-input" id="enc-notes" placeholder="Notes..."></div>';
            html += '</div>';
            html += '<div style="display:flex;gap:8px;margin-top:16px">';
            html += '<button class="btn btn-ghost" style="flex:1" onclick="closeModal()">Annuler</button>';
            html += '<button class="btn btn-primary" style="flex:1;background:var(--green)" onclick="confirmerEncaissement(' + facture.id + ')">Enregistrer le paiement</button>';
            html += '</div>';

            showModal('Encaisser - ' + (escapeHtml(facture.numero_facture) || ('Facture #' + facture.id)), html, '500px');
        }).catch(function(e) {
            showAlert('Erreur: ' + e.message, 'error');
        });
    },

    toggleEncReference: function() {
        var modeEl = document.getElementById('enc-mode');
        var groupEl = document.getElementById('enc-ref-group');
        if (!modeEl || !groupEl) return;
        var mode = modeEl.value;
        groupEl.style.display = (mode === 'cheque' || mode === 'virement') ? 'block' : 'none';
    },

    confirmerEncaissement: function(factureId) {
        if (!canUseBilling()) {
            showAlert('Encaissement desactive pour le role service client', 'warning');
            return;
        }
        var montantEl = document.getElementById('enc-montant');
        var modeEl = document.getElementById('enc-mode');
        var referenceEl = document.getElementById('enc-reference');
        var notesEl = document.getElementById('enc-notes');
        var montant = montantEl ? parseFloat(montantEl.value) : 0;
        var mode = modeEl ? modeEl.value : 'cb';
        var reference = referenceEl ? referenceEl.value : '';
        var notes = notesEl ? notesEl.value : '';

        if (!montant || montant <= 0) {
            showAlert('Montant invalide', 'error');
            return;
        }

        apiPost('/api/factures/' + factureId + '/encaisser', {
            montant: montant,
            mode_paiement: mode,
            reference: reference || null,
            notes: notes || null
        }).then(function(r) {
            return r.json();
        }).then(function(data) {
            closeModal();
            var msg = data.statut === 'payee'
                ? 'Paiement enregistre - Facture soldee'
                : 'Paiement enregistre - Reste: ' + window.BillingModule.asNumber(data.montant_restant).toFixed(2) + ' EUR';
            showAlert(msg);
            refreshCurrentSection();
        }).catch(function(e) {
            showAlert('Erreur: ' + e.message, 'error');
        });
    }
};
