/* ========== DEVIS MODULE ========== */
window.DevisModule = {

    _devisStatutBadge: function(statut) {
        var colors = {
            brouillon: '#6b7280', envoye: '#3b82f6', accepte: '#22c55e',
            refuse: '#ef4444', expire: '#f59e0b', converti: '#8b5cf6'
        };
        var labels = {
            brouillon: 'Brouillon', envoye: 'Envoyé', accepte: 'Accepté',
            refuse: 'Refusé', expire: 'Expiré', converti: 'Converti'
        };
        var c = colors[statut] || '#6b7280';
        return '<span style="background:' + c + '22;color:' + c + ';padding:2px 8px;border-radius:4px;font-size:12px;font-weight:600">' + (labels[statut] || statut) + '</span>';
    },

    loadDevisList: function() {
        var tbody = document.getElementById('devis-table-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#888;padding:20px">Chargement...</td></tr>';
        var statut = (document.getElementById('devis-filter-statut') || {}).value || '';
        var search = (document.getElementById('devis-search') || {}).value || '';
        var url = '/api/devis';
        if (statut) url += '?statut=' + encodeURIComponent(statut);
        apiGet(url).then(function(r) { return r.json(); }).then(function(devisList) {
            if (search) {
                var q = search.toLowerCase();
                devisList = devisList.filter(function(d) {
                    return (d.numero_devis || '').toLowerCase().indexOf(q) !== -1 ||
                        ((d.client && d.client.nom) || '').toLowerCase().indexOf(q) !== -1 ||
                        ((d.client && d.client.prenom) || '').toLowerCase().indexOf(q) !== -1;
                });
            }
            if (!devisList.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#888;padding:20px">Aucun devis</td></tr>';
                return;
            }
            var html = '';
            devisList.forEach(function(d) {
                var client = d.client ? (escapeHtml(d.client.prenom || '') + ' ' + escapeHtml(d.client.nom || '')).trim() : '-';
                var vehicule = d.vehicule ? escapeHtml((d.vehicule.marque || '') + ' ' + (d.vehicule.modele || '')).trim() : '-';
                var dateStr = d.date_creation ? new Date(d.date_creation).toLocaleDateString('fr-FR') : '-';
                var total = d.total_ttc != null ? d.total_ttc.toFixed(2) + ' €' : '-';
                html += '<tr>' +
                    '<td><strong style="color:#60a5fa">' + escapeHtml(d.numero_devis || '') + '</strong></td>' +
                    '<td>' + client + '</td>' +
                    '<td style="color:#aaa">' + vehicule + '</td>' +
                    '<td style="color:#aaa">' + dateStr + '</td>' +
                    '<td style="font-weight:600">' + total + '</td>' +
                    '<td>' + DevisModule._devisStatutBadge(d.statut) + '</td>' +
                    '<td>' +
                    '<button class="btn btn-ghost" style="font-size:11px;padding:2px 8px" onclick="ouvrirDetailDevis(' + d.id + ')">Voir</button> ' +
                    (d.statut === 'brouillon' ? '<button class="btn btn-ghost" style="font-size:11px;padding:2px 8px;color:#3b82f6" onclick="envoyerDevis(' + d.id + ')">Envoyer</button> ' : '') +
                    (d.statut === 'accepte' ? '<button class="btn btn-ghost" style="font-size:11px;padding:2px 8px;color:#22c55e" onclick="ouvrirConvertirDevis(' + d.id + ')">→ RDV</button> ' : '') +
                    (d.statut === 'brouillon' ? '<button class="btn btn-ghost" style="font-size:11px;padding:2px 8px;color:#ef4444" onclick="supprimerDevis(' + d.id + ')">Suppr.</button>' : '') +
                    '</td></tr>';
            });
            tbody.innerHTML = html;
        }).catch(function() {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#ef4444;padding:20px">Erreur chargement</td></tr>';
        });
    },

    ouvrirCreerDevis: function() {
        var html = '<form id="devis-create-form" onsubmit="creerDevisSubmit(event)">' +
            '<div class="form-group"><label class="form-label">Client *</label>' +
            '<input id="devis-client-search" class="form-input" placeholder="Rechercher un client..." oninput="rechercherClientDevis(this.value)" autocomplete="off">' +
            '<input type="hidden" id="devis-client-id">' +
            '<div id="devis-client-results" style="max-height:150px;overflow-y:auto;margin-top:4px"></div></div>' +
            '<div class="form-group"><label class="form-label">Véhicule</label>' +
            '<select id="devis-vehicule-id" class="form-select"><option value="">-- Choisir après sélection client --</option></select></div>' +
            '<div class="form-group"><label class="form-label">Kilométrage</label>' +
            '<input id="devis-km" class="form-input" type="number" placeholder="km"></div>' +
            '<div class="form-group"><label class="form-label">Notes client</label>' +
            '<textarea id="devis-notes-client" class="form-input" rows="2" placeholder="Notes visibles sur le devis"></textarea></div>' +
            '<div class="form-group"><label class="form-label">Notes internes</label>' +
            '<textarea id="devis-notes-internes" class="form-input" rows="2" placeholder="Notes internes (non visibles)"></textarea></div>' +
            '<hr style="border-color:#333;margin:12px 0">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">' +
            '<strong style="color:#eee">Lignes du devis</strong>' +
            '<button type="button" class="btn btn-ghost" style="font-size:12px" onclick="ajouterLigneDevis()">+ Ajouter ligne</button></div>' +
            '<div id="devis-lignes-container"></div>' +
            '<div class="form-group" style="margin-top:8px"><label class="form-label">Remise (%)</label>' +
            '<input id="devis-remise" class="form-input" type="number" step="0.1" value="0" style="max-width:100px" onchange="recalculerDevis()"></div>' +
            '<div id="devis-totaux" style="text-align:right;color:#aaa;font-size:13px;margin:8px 0"></div>' +
            '<button class="btn btn-primary" type="submit" style="width:100%;margin-top:12px">Créer le devis</button>' +
            '</form>';
        showModal('Nouveau devis', html, '700px');
        DevisModule._ligneIndex = 0;
        DevisModule.ajouterLigneDevis();
    },

    _ligneIndex: 0,

    ajouterLigneDevis: function() {
        var container = document.getElementById('devis-lignes-container');
        if (!container) return;
        var i = DevisModule._ligneIndex++;
        var div = document.createElement('div');
        div.id = 'devis-ligne-' + i;
        div.style.cssText = 'border:1px solid #333;border-radius:8px;padding:10px;margin-bottom:8px;background:#1a1a2e';
        div.innerHTML =
            '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">' +
            '<div class="form-group" style="flex:0 0 140px"><label class="form-label" style="font-size:11px">Type</label>' +
            '<select class="form-select devis-ligne-type" data-idx="' + i + '" onchange="changeLigneTypeDevis(' + i + ')">' +
            '<option value="forfait_mo">Forfait MO</option><option value="piece">Pièce</option><option value="main_oeuvre_libre">MO libre</option></select></div>' +
            '<div class="form-group" style="flex:1"><label class="form-label" style="font-size:11px">Désignation</label>' +
            '<input class="form-input devis-ligne-designation" data-idx="' + i + '" placeholder="Description"></div>' +
            '<div class="form-group" style="flex:0 0 60px"><label class="form-label" style="font-size:11px">Qté</label>' +
            '<input class="form-input devis-ligne-qte" data-idx="' + i + '" type="number" value="1" min="1" onchange="recalculerDevis()"></div>' +
            '<div class="form-group" style="flex:0 0 100px"><label class="form-label" style="font-size:11px">Prix HT</label>' +
            '<input class="form-input devis-ligne-prix" data-idx="' + i + '" type="number" step="0.01" value="0" onchange="recalculerDevis()"></div>' +
            '<div class="form-group" style="flex:0 0 70px"><label class="form-label" style="font-size:11px">TVA %</label>' +
            '<input class="form-input devis-ligne-tva" data-idx="' + i + '" type="number" step="0.1" value="20"></div>' +
            '<button type="button" class="btn btn-ghost" style="color:#ef4444;padding:4px 8px" onclick="supprimerLigneDevis(' + i + ')">✕</button>' +
            '</div>';
        container.appendChild(div);
        DevisModule.recalculerDevis();
    },

    supprimerLigneDevis: function(idx) {
        var el = document.getElementById('devis-ligne-' + idx);
        if (el) el.remove();
        DevisModule.recalculerDevis();
    },

    changeLigneTypeDevis: function(idx) {
        // placeholder for future prestation lookup
    },

    recalculerDevis: function() {
        var container = document.getElementById('devis-lignes-container');
        var totauxEl = document.getElementById('devis-totaux');
        if (!container || !totauxEl) return;
        var totalHt = 0;
        container.querySelectorAll('[id^="devis-ligne-"]').forEach(function(div) {
            var qte = parseFloat(div.querySelector('.devis-ligne-qte').value) || 0;
            var prix = parseFloat(div.querySelector('.devis-ligne-prix').value) || 0;
            totalHt += qte * prix;
        });
        var remise = parseFloat((document.getElementById('devis-remise') || {}).value) || 0;
        var remiseMontant = totalHt * (remise / 100);
        var totalAfterRemise = totalHt - remiseMontant;
        var totalTtc = totalAfterRemise * 1.2;
        totauxEl.innerHTML = 'Total HT: <strong>' + totalHt.toFixed(2) + ' €</strong>' +
            (remise > 0 ? ' | Remise: -' + remiseMontant.toFixed(2) + ' €' : '') +
            ' | <strong style="color:#22c55e">Total TTC: ' + totalTtc.toFixed(2) + ' €</strong>';
    },

    rechercherClientDevis: function(q) {
        var results = document.getElementById('devis-client-results');
        if (!results) return;
        if (!q || q.length < 2) { results.innerHTML = ''; return; }
        apiGet('/api/clients?search=' + encodeURIComponent(q)).then(function(r) { return r.json(); }).then(function(clients) {
            if (!clients.length) { results.innerHTML = '<div style="color:#888;padding:4px">Aucun client</div>'; return; }
            var html = '';
            clients.slice(0, 8).forEach(function(c) {
                html += '<div style="padding:6px 8px;cursor:pointer;border-bottom:1px solid #333;color:#ccc" onmouseover="this.style.background=\'#1e293b\'" onmouseout="this.style.background=\'\'" onclick="selectionnerClientDevis(' + c.id + ', \'' + escapeHtml((c.prenom || '') + ' ' + (c.nom || '')).replace(/'/g, "\\'") + '\')">' +
                    escapeHtml((c.prenom || '') + ' ' + (c.nom || '')) + ' <span style="color:#888">' + escapeHtml(c.telephone || '') + '</span></div>';
            });
            results.innerHTML = html;
        });
    },

    selectionnerClientDevis: function(clientId, label) {
        document.getElementById('devis-client-id').value = clientId;
        document.getElementById('devis-client-search').value = label;
        document.getElementById('devis-client-results').innerHTML = '';
        // Load client vehicles
        apiGet('/api/clients/' + clientId).then(function(r) { return r.json(); }).then(function(c) {
            var sel = document.getElementById('devis-vehicule-id');
            if (!sel) return;
            var opts = '<option value="">-- Sans véhicule --</option>';
            (c.vehicules || []).forEach(function(v) {
                opts += '<option value="' + v.id + '">' + escapeHtml((v.marque || '') + ' ' + (v.modele || '') + ' - ' + (v.plaque || '')) + '</option>';
            });
            sel.innerHTML = opts;
        });
    },

    _collectLignes: function() {
        var container = document.getElementById('devis-lignes-container');
        if (!container) return [];
        var lignes = [];
        container.querySelectorAll('[id^="devis-ligne-"]').forEach(function(div) {
            lignes.push({
                type_ligne: div.querySelector('.devis-ligne-type').value,
                designation: div.querySelector('.devis-ligne-designation').value || 'Ligne',
                quantite: parseInt(div.querySelector('.devis-ligne-qte').value) || 1,
                prix_unitaire_ht: parseFloat(div.querySelector('.devis-ligne-prix').value) || 0,
                taux_tva: parseFloat(div.querySelector('.devis-ligne-tva').value) || 20
            });
        });
        return lignes;
    },

    creerDevisSubmit: function(e) {
        if (e) e.preventDefault();
        var clientId = parseInt((document.getElementById('devis-client-id') || {}).value);
        if (!clientId) { alert('Veuillez sélectionner un client'); return; }
        var lignes = DevisModule._collectLignes();
        if (!lignes.length) { alert('Ajoutez au moins une ligne'); return; }
        var payload = {
            client_id: clientId,
            vehicule_id: parseInt((document.getElementById('devis-vehicule-id') || {}).value) || null,
            kilometrage: parseInt((document.getElementById('devis-km') || {}).value) || null,
            notes_client: (document.getElementById('devis-notes-client') || {}).value || null,
            notes_internes: (document.getElementById('devis-notes-internes') || {}).value || null,
            lignes: lignes,
            remise_pourcentage: parseFloat((document.getElementById('devis-remise') || {}).value) || 0
        };
        apiPost('/api/devis', payload).then(function(r) { return r.json(); }).then(function(data) {
            showNotificationToast(data.message || 'Devis créé: ' + (data.numero || ''));
            closeModal();
            DevisModule.loadDevisList();
        }).catch(function(e) { alert('Erreur: ' + (e.message || 'création devis')); });
    },

    ouvrirDetailDevis: function(devisId) {
        apiGet('/api/devis/' + devisId).then(function(r) { return r.json(); }).then(function(d) {
            var client = d.client ? escapeHtml((d.client.prenom || '') + ' ' + (d.client.nom || '')) : '-';
            var vehicule = d.vehicule ? escapeHtml((d.vehicule.marque || '') + ' ' + (d.vehicule.modele || '') + ' - ' + (d.vehicule.plaque || '')) : '-';
            var html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">' +
                '<div><div style="color:#888;font-size:11px">N° Devis</div><strong style="color:#60a5fa">' + escapeHtml(d.numero_devis || '') + '</strong></div>' +
                '<div><div style="color:#888;font-size:11px">Statut</div>' + DevisModule._devisStatutBadge(d.statut) + '</div>' +
                '<div><div style="color:#888;font-size:11px">Client</div><span style="color:#eee">' + client + '</span></div>' +
                '<div><div style="color:#888;font-size:11px">Véhicule</div><span style="color:#eee">' + vehicule + '</span></div>' +
                '<div><div style="color:#888;font-size:11px">Date création</div><span style="color:#aaa">' + (d.date_creation ? new Date(d.date_creation).toLocaleDateString('fr-FR') : '-') + '</span></div>' +
                '<div><div style="color:#888;font-size:11px">Validité</div><span style="color:#aaa">' + (d.date_validite ? new Date(d.date_validite).toLocaleDateString('fr-FR') : '-') + '</span></div>' +
                '</div>';

            if (d.notes_client) html += '<div style="background:#1e293b;padding:8px;border-radius:6px;margin-bottom:8px;color:#ccc;font-size:13px"><strong style="color:#888">Notes client:</strong> ' + escapeHtml(d.notes_client) + '</div>';
            if (d.notes_internes) html += '<div style="background:#1a1a2e;padding:8px;border-radius:6px;margin-bottom:8px;color:#f59e0b;font-size:13px"><strong style="color:#888">Notes internes:</strong> ' + escapeHtml(d.notes_internes) + '</div>';

            // Lignes table
            html += '<table style="width:100%;border-collapse:collapse;margin:12px 0"><thead><tr>' +
                '<th style="padding:6px;text-align:left;border-bottom:1px solid #333;color:#888;font-size:12px">Désignation</th>' +
                '<th style="padding:6px;text-align:center;border-bottom:1px solid #333;color:#888;font-size:12px">Qté</th>' +
                '<th style="padding:6px;text-align:right;border-bottom:1px solid #333;color:#888;font-size:12px">PU HT</th>' +
                '<th style="padding:6px;text-align:right;border-bottom:1px solid #333;color:#888;font-size:12px">TVA</th>' +
                '<th style="padding:6px;text-align:right;border-bottom:1px solid #333;color:#888;font-size:12px">Total HT</th>' +
                '</tr></thead><tbody>';
            (d.lignes || []).forEach(function(l) {
                html += '<tr>' +
                    '<td style="padding:4px 6px;color:#ccc;font-size:13px">' + escapeHtml(l.designation || '') + '</td>' +
                    '<td style="padding:4px 6px;text-align:center;color:#aaa">' + l.quantite + '</td>' +
                    '<td style="padding:4px 6px;text-align:right;color:#aaa">' + (l.prix_unitaire_ht || 0).toFixed(2) + ' €</td>' +
                    '<td style="padding:4px 6px;text-align:right;color:#aaa">' + (l.taux_tva || 0) + '%</td>' +
                    '<td style="padding:4px 6px;text-align:right;color:#eee;font-weight:600">' + (l.total_ligne_ht || 0).toFixed(2) + ' €</td>' +
                    '</tr>';
            });
            html += '</tbody></table>';

            // Totaux
            html += '<div style="text-align:right;border-top:1px solid #333;padding-top:8px">';
            if (d.remise_pourcentage > 0) {
                html += '<div style="color:#aaa;font-size:13px">Remise: -' + (d.remise_montant || 0).toFixed(2) + ' € (' + d.remise_pourcentage + '%)</div>';
            }
            html += '<div style="color:#aaa;font-size:13px">Total HT: ' + (d.total_ht || 0).toFixed(2) + ' €</div>';
            html += '<div style="color:#22c55e;font-size:16px;font-weight:700;margin-top:4px">Total TTC: ' + (d.total_ttc || 0).toFixed(2) + ' €</div>';
            html += '</div>';

            // Actions
            html += '<div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap">';
            if (d.statut === 'brouillon') {
                html += '<button class="btn btn-primary" onclick="envoyerDevis(' + d.id + ')">📤 Envoyer au client</button>';
            }
            if (d.statut === 'envoye') {
                html += '<button class="btn btn-primary" style="background:#22c55e" onclick="accepterDevis(' + d.id + ')">✅ Marquer accepté</button>';
                html += '<button class="btn btn-ghost" style="color:#ef4444" onclick="refuserDevis(' + d.id + ')">❌ Marquer refusé</button>';
            }
            if (d.statut === 'accepte') {
                html += '<button class="btn btn-primary" style="background:#8b5cf6" onclick="ouvrirConvertirDevis(' + d.id + ')">🔄 Convertir en RDV</button>';
            }
            if (d.statut === 'brouillon') {
                html += '<button class="btn btn-ghost" style="color:#ef4444" onclick="supprimerDevis(' + d.id + ')">🗑 Supprimer</button>';
            }
            html += '<button class="btn btn-ghost" onclick="telechargerDevisPdf(' + d.id + ')">📄 PDF</button>';
            html += '</div>';

            showModal('Devis ' + escapeHtml(d.numero_devis || ''), html, '700px');
        }).catch(function() { alert('Erreur chargement devis'); });
    },

    envoyerDevis: function(devisId) {
        openConfirmDialog('Envoyer ce devis au client ?', function() {
            apiPut('/api/devis/' + devisId, { statut: 'envoye' }).then(function(r) { return r.json(); }).then(function() {
                showNotificationToast('Devis envoyé');
                closeModal();
                DevisModule.loadDevisList();
            }).catch(function(e) { alert('Erreur: ' + (e.message || 'envoi')); });
        });
    },

    accepterDevis: function(devisId) {
        apiPut('/api/devis/' + devisId, { statut: 'accepte' }).then(function(r) { return r.json(); }).then(function() {
            showNotificationToast('Devis accepté');
            closeModal();
            DevisModule.loadDevisList();
        }).catch(function(e) { alert('Erreur: ' + (e.message || 'acceptation')); });
    },

    refuserDevis: function(devisId) {
        openConfirmDialog('Marquer ce devis comme refusé ?', function() {
            apiPut('/api/devis/' + devisId, { statut: 'refuse' }).then(function(r) { return r.json(); }).then(function() {
                showNotificationToast('Devis refusé');
                closeModal();
                DevisModule.loadDevisList();
            }).catch(function(e) { alert('Erreur: ' + (e.message || 'refus')); });
        });
    },

    supprimerDevis: function(devisId) {
        openConfirmDialog('Supprimer ce devis ?', function() {
            apiDelete('/api/devis/' + devisId).then(function() {
                showNotificationToast('Devis supprimé');
                closeModal();
                DevisModule.loadDevisList();
            }).catch(function(e) { alert('Erreur: ' + (e.message || 'suppression')); });
        });
    },

    ouvrirConvertirDevis: function(devisId) {
        var today = new Date().toISOString().split('T')[0];
        var html = '<form onsubmit="convertirDevisSubmit(event, ' + devisId + ')">' +
            '<div class="form-group"><label class="form-label">Date du RDV *</label>' +
            '<input id="convertir-date" class="form-input" type="date" value="' + today + '" required></div>' +
            '<div class="form-group"><label class="form-label">Heure *</label>' +
            '<input id="convertir-heure" class="form-input" type="time" value="09:00" required></div>' +
            '<button class="btn btn-primary" type="submit" style="width:100%;margin-top:12px">Convertir en RDV</button>' +
            '</form>';
        showModal('Convertir devis en RDV', html, '400px');
    },

    convertirDevisSubmit: function(e, devisId) {
        if (e) e.preventDefault();
        var dateVal = (document.getElementById('convertir-date') || {}).value;
        var heureVal = (document.getElementById('convertir-heure') || {}).value;
        if (!dateVal || !heureVal) { alert('Date et heure requises'); return; }
        apiPost('/api/devis/' + devisId + '/convertir-rdv?date_rdv=' + dateVal + '&heure_rdv=' + heureVal + ':00', {})
            .then(function(r) { return r.json(); })
            .then(function(data) {
                showNotificationToast(data.message || 'Devis converti en RDV');
                closeModal();
                DevisModule.loadDevisList();
            }).catch(function(e) { alert('Erreur: ' + (e.message || 'conversion')); });
    },

    telechargerDevisPdf: function(devisId) {
        window.open('/api/devis/' + devisId + '/pdf', '_blank');
    }
};
