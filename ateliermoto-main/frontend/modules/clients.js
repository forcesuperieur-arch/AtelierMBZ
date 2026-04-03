var _clientSearchTimer = null;

window.ClientsModule = window.ClientsModule || {
    rechercherClients: function(val) {
        if (_clientSearchTimer) clearTimeout(_clientSearchTimer);
        _clientSearchTimer = setTimeout(function() {
            window.ClientsModule.loadClients(val, 1);
        }, 300);
    },

    loadClients: function(search, page) {
        page = page || 1;
        APP._clientsPage = page;
        var url = '/api/clients?page=' + page + '&limit=50';
        if (search) url += '&search=' + encodeURIComponent(search);
        var container = document.getElementById('clients-list');
        if (!container) return;
        setLoadingState(container, true, 'Chargement des clients...');
        apiGet(url).then(function(r) {
            return r.json();
        }).then(function(data) {
            var clients = data.items || data;
            var total = data.total || clients.length;
            var pages = data.pages || 1;
            var countEl = document.getElementById('clients-total-count');
            if (countEl) countEl.textContent = total + ' clients';
            if (!clients.length) {
                container.innerHTML = '<div style="color:#666;padding:20px;text-align:center">Aucun client trouve</div>';
                window.ClientsModule.renderClientsPagination(0, 0);
                updateLiveRegion('Aucun client trouve');
                return;
            }
            var html = '';
            clients.forEach(function(c) {
                var prenom = escapeHtml(c.prenom) || '';
                var nom = escapeHtml(c.nom) || '';
                var telephone = escapeHtml(c.telephone) || '-';
                var email = escapeHtml(c.email) || '';
                html += '<div style="background:#252525;border:1px solid #333;border-radius:8px;padding:12px;margin-bottom:6px;cursor:pointer;transition:border-color .15s" onmouseover="this.style.borderColor=\'var(--orange)\'" onmouseout="this.style.borderColor=\'#333\'" onclick="showClientDetail(' + c.id + ')">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center">' +
                        '<div><div style="font-weight:600;color:#eee">' + prenom + ' ' + nom + '</div>' +
                        '<div style="font-size:12px;color:#888">' + telephone + (email ? ' | ' + email : '') + '</div></div>' +
                        '<div style="text-align:right"><span class="badge blue">' + (c.nb_rdv || 0) + ' RDV</span>' +
                        '<span class="badge" style="background:rgba(20,184,166,0.15);color:#14b8a6;margin-left:4px">' + (c.nb_vehicules || 0) + ' moto' + ((c.nb_vehicules || 0) > 1 ? 's' : '') + '</span>' +
                        (c.dernier_rdv ? '<div style="font-size:11px;color:#666;margin-top:4px">' + new Date(c.dernier_rdv).toLocaleDateString('fr-FR') + '</div>' : '') +
                        '</div></div></div>';
            });
            container.innerHTML = html;
            window.ClientsModule.renderClientsPagination(page, pages);
            updateLiveRegion(clients.length + ' clients affiches');
        }).catch(function() {
            container.innerHTML = '<div style="color:#666;padding:20px">Erreur chargement</div>';
        });
        window.ClientsModule.loadClientsStats();
    },

    loadClientsStats: function() {
        apiGet('/api/clients/stats').then(function(r) {
            return r.json();
        }).then(function(stats) {
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
    },

    renderClientsPagination: function(currentPage, totalPages) {
        var container = document.getElementById('clients-pagination');
        if (!container || totalPages <= 1) {
            if (container) container.innerHTML = '';
            return;
        }
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
    },

    ouvrirNouveauClient: function() {
        var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Nom *</label><input id="new-client-nom" class="form-input" placeholder="Nom de famille"></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Prenom *</label><input id="new-client-prenom" class="form-input" placeholder="Prenom"></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Telephone *</label><input id="new-client-tel" class="form-input" placeholder="06 12 34 56 78"></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Email</label><input id="new-client-email" class="form-input" placeholder="email@exemple.com"></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Adresse</label><input id="new-client-adresse" class="form-input" placeholder="Adresse complete"></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Notes</label><textarea id="new-client-notes" class="form-input" rows="2" placeholder="Notes internes..."></textarea></div>' +
            '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="creerNouveauClient()">Creer le client</button>';
        showModal('Nouveau client', html, '450px');
    },

    creerNouveauClient: function() {
        var nom = (document.getElementById('new-client-nom') || {}).value || '';
        var prenom = (document.getElementById('new-client-prenom') || {}).value || '';
        var tel = (document.getElementById('new-client-tel') || {}).value || '';
        nom = nom.trim();
        prenom = prenom.trim();
        tel = tel.trim();
        if (!nom || !prenom || !tel) {
            alert('Nom, prenom et telephone sont obligatoires');
            return;
        }
        apiPost('/api/clients', {
            nom: nom,
            prenom: prenom,
            telephone: tel,
            email: ((document.getElementById('new-client-email') || {}).value || '').trim() || null,
            adresse: ((document.getElementById('new-client-adresse') || {}).value || '').trim() || null,
            notes: ((document.getElementById('new-client-notes') || {}).value || '').trim() || null
        }).then(function(r) {
            return r.json();
        }).then(function(data) {
            closeModal();
            showNotificationToast('Client cree');
            window.ClientsModule.loadClients(null, 1);
            if (data.id) window.ClientsModule.showClientDetail(data.id);
        }).catch(function(e) {
            alert('Erreur: ' + e.message);
        });
    },

    supprimerVehicule: function(vehiculeId, clientId) {
        openConfirmDialog('Supprimer ce vehicule ?', function() {
            apiDelete('/api/vehicules/' + vehiculeId).then(function(r) {
                return r.json();
            }).then(function() {
                showNotificationToast('Vehicule supprime');
                window.ClientsModule.showClientDetail(clientId);
                window.ClientsModule.loadClientsStats();
            }).catch(function(e) {
                alert('Erreur: ' + e.message);
            });
        });
    },

    showClientDetail: function(clientId) {
        apiGet('/api/clients/' + clientId).then(function(r) {
            return r.json();
        }).then(function(c) {
            var panel = document.getElementById('client-detail-panel');
            var infoEl = document.getElementById('client-detail-info');
            var vehEl = document.getElementById('client-detail-vehicules');
            var histEl = document.getElementById('client-detail-historique');
            if (!panel || !infoEl || !vehEl || !histEl) return;

            panel.style.display = 'block';
            APP._currentClientId = clientId;

            var nbRdv = (c.historique || []).length;
            var totalCA = Number(c.ca_total || 0);
            var email = escapeHtml(c.email) || '';
            var adresse = escapeHtml(c.adresse) || '';
            var notes = escapeHtml(c.notes) || '';

            infoEl.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px">' +
                '<div><div style="font-size:18px;font-weight:700;color:#eee">' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + '</div>' +
                '<div style="font-size:13px;color:#888;margin-top:4px">' + (escapeHtml(c.telephone) || '-') + '</div>' +
                (email ? '<div style="font-size:13px;color:#888">' + email + '</div>' : '') +
                (adresse ? '<div style="font-size:12px;color:#666;margin-top:4px">' + adresse + '</div>' : '') +
                '</div>' +
                '<button class="btn btn-ghost" style="font-size:12px" onclick="ouvrirModalEditClient(' + c.id + ')">Modifier</button>' +
                '</div>' +
                '<div style="display:flex;gap:12px;margin-bottom:8px">' +
                '<div style="background:#1e1e1e;border-radius:6px;padding:8px 14px;flex:1;text-align:center"><div style="font-size:18px;font-weight:700;color:var(--orange)">' + nbRdv + '</div><div style="font-size:10px;color:#666;text-transform:uppercase">Visites</div></div>' +
                '<div style="background:#1e1e1e;border-radius:6px;padding:8px 14px;flex:1;text-align:center"><div style="font-size:18px;font-weight:700;color:var(--green)">' + (totalCA > 0 ? Math.round(totalCA) + '\u20AC' : '-') + '</div><div style="font-size:10px;color:#666;text-transform:uppercase">CA total</div></div>' +
                '<div style="background:#1e1e1e;border-radius:6px;padding:8px 14px;flex:1;text-align:center"><div style="font-size:18px;font-weight:700;color:var(--teal)">' + (c.vehicules || []).length + '</div><div style="font-size:10px;color:#666;text-transform:uppercase">Motos</div></div>' +
                '</div>' +
                (notes ? '<div style="font-size:12px;color:#888;background:#1e1e1e;padding:8px 12px;border-radius:6px;margin-top:8px">' + notes + '</div>' : '');

            var vehicules = c.vehicules || [];
            var vHtml = '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px"><div class="card-title">Vehicules (' + vehicules.length + ')</div>' +
                '<button class="btn btn-ghost" style="font-size:11px;padding:3px 10px;color:var(--green)" onclick="ouvrirAjouterVehicule(' + c.id + ')">+ Ajouter</button></div>';
            if (vehicules.length) {
                vehicules.forEach(function(v) {
                    vHtml += '<div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #2a2a2a">' +
                        '<div style="font-size:20px">&#127949;</div>' +
                        '<div style="flex:1"><div style="font-weight:600;color:#eee">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div>' +
                        '<div style="font-size:12px;color:#888">' + (escapeHtml(v.plaque) || '') + (v.annee ? ' | ' + escapeHtml(v.annee) : '') + (v.cylindree ? ' | ' + escapeHtml(v.cylindree) : '') + (v.type_moto ? ' | ' + escapeHtml(v.type_moto) : '') + '</div></div>' +
                        '<button class="btn btn-ghost" style="font-size:11px;padding:3px 8px;color:var(--teal)" onclick="ouvrirModalEditVehicule(' + v.id + ')">\u270E</button>' +
                        '<button class="btn btn-ghost" style="font-size:11px;padding:3px 8px;color:var(--red)" onclick="supprimerVehicule(' + v.id + ',' + c.id + ')">\u2716</button>' +
                        '</div>';
                });
            } else {
                vHtml += '<div style="color:#666;font-size:13px">Aucun vehicule enregistre</div>';
            }
            vehEl.innerHTML = vHtml;

            var historique = c.historique || [];
            var hHtml = '<div class="card-title" style="margin-bottom:10px">Historique atelier (' + historique.length + ')</div>';
            if (historique.length) {
                historique.forEach(function(rdv) {
                    var v = rdv.vehicule || {};
                    var meca = rdv.mecanicien;
                    var prix = rdv.prix_final || rdv.prix_estime || null;
                    var tempsEff = rdv.temps_effectif_minutes;
                    var commentaire = escapeHtml(rdv.commentaire || '');
                    var borderColor = rdv.statut === 'termine' || rdv.statut === 'facture' || rdv.statut === 'paye'
                        ? 'var(--green)'
                        : (rdv.statut === 'en_cours' ? 'var(--orange)' : ((rdv.statut === 'annule' || rdv.statut === 'non_presente') ? 'var(--red)' : '#444'));
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
                            (meca ? '<span>&#128100; ' + (escapeHtml(meca.prenom) || '') + ' ' + (escapeHtml(meca.nom) || '') + '</span>' : '') +
                            (rdv.pont ? '<span>&#128295; ' + escapeHtml(rdv.pont.nom) + '</span>' : '') +
                            (tempsEff ? '<span>&#9201; ' + tempsEff + ' min</span>' : '') +
                            (prix ? '<span>&#128182; ' + Math.round(prix) + '\u20AC</span>' : '') +
                            (rdv.kilometrage ? '<span>&#128663; ' + rdv.kilometrage + ' km</span>' : '') +
                        '</div>' +
                        (commentaire ? '<div style="font-size:11px;color:#666;margin-top:4px;font-style:italic">' + commentaire.substring(0, 80) + (commentaire.length > 80 ? '...' : '') + '</div>' : '') +
                        (rdv.rapport ? '<div style="margin-top:4px"><span class="badge blue" style="font-size:9px">Rapport technicien</span></div>' : '') +
                    '</div>';
                });
            } else {
                hHtml += '<div style="color:#666;font-size:13px">Aucun historique</div>';
            }
            histEl.innerHTML = hHtml;
        }).catch(function(e) {
            console.error('Erreur client detail:', e);
        });
    },

    ouvrirDetailHistoriqueRdv: function(rdvId, clientId) {
        apiGet('/api/rendez-vous/' + rdvId).then(function(r) {
            return r.json();
        }).then(function(rdv) {
            var c = rdv.client || {};
            var v = rdv.vehicule || {};
            var meca = APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
            var pont = APP.ponts.find(function(p) { return p.id === rdv.pont_id; });

            var html = '';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">' +
                '<div>' + statusBadge(rdv.statut) + '</div>' +
                '<div style="font-size:12px;color:#888">RDV #' + rdv.id + '</div></div>';

            html += '<div style="background:#16161a;border-radius:8px;padding:12px;margin-bottom:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">' +
                '<div><span style="color:#666">Date:</span> <span style="color:#eee">' + (rdv.date_rdv || '-') + '</span></div>' +
                '<div><span style="color:#666">Heure:</span> <span style="color:#eee">' + formatTime(rdv.heure_rdv) + '</span></div>' +
                '<div><span style="color:#666">Intervention:</span> <span style="color:#eee">' + (escapeHtml(rdv.type_intervention) || '-') + '</span></div>' +
                '<div><span style="color:#666">Vehicule:</span> <span style="color:#eee">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</span></div>' +
                '<div><span style="color:#666">Mecanicien:</span> <span style="color:#eee">' + (meca ? (escapeHtml(meca.prenom) || '') + ' ' + (escapeHtml(meca.nom) || '') : '-') + '</span></div>' +
                '<div><span style="color:#666">Pont:</span> <span style="color:#eee">' + (pont ? escapeHtml(pont.nom) : '-') + '</span></div>' +
                '<div><span style="color:#666">Kilometrage:</span> <span style="color:#eee">' + (rdv.kilometrage || '-') + '</span></div>' +
                '<div><span style="color:#666">Prix:</span> <span style="color:#eee">' + (rdv.prix_estime ? Math.round(rdv.prix_estime) + '\u20AC' : '-') + '</span></div>' +
                '</div>';

            if (rdv.commentaire) {
                html += '<div style="margin-bottom:12px"><div style="font-size:11px;font-weight:600;color:var(--orange);text-transform:uppercase;margin-bottom:4px">Commentaire</div>' +
                    '<div style="background:#16161a;border-radius:6px;padding:10px;font-size:13px;color:#ccc">' + escapeHtml(rdv.commentaire) + '</div></div>';
            }

            html += '<div id="hist-rdv-temps" style="margin-bottom:12px"></div>';
            html += '<div id="hist-rdv-rapport"></div>';
            showModal((escapeHtml(rdv.type_intervention) || 'RDV') + ' - ' + (rdv.date_rdv || ''), html, '600px');

            apiGet('/api/rendez-vous/' + rdvId + '/temps-travail').then(function(r) {
                return r.json();
            }).then(function(t) {
                var tEl = document.getElementById('hist-rdv-temps');
                if (!tEl || (!t.heure_debut_travail && !t.heure_fin_travail)) {
                    if (tEl) tEl.innerHTML = '';
                    return;
                }
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

            apiGet('/api/rendez-vous/' + rdvId + '/rapport-technicien').then(function(r) {
                return r.json();
            }).then(function(rap) {
                var rEl = document.getElementById('hist-rdv-rapport');
                if (!rEl) return;
                var piecesUtilisees = '';
                if (Array.isArray(rap.pieces_utilisees) && rap.pieces_utilisees.length) {
                    piecesUtilisees = rap.pieces_utilisees.map(function(piece) { return escapeHtml(piece); }).join(', ');
                } else if (rap.pieces_utilisees) {
                    piecesUtilisees = escapeHtml(rap.pieces_utilisees);
                }
                var rHtml = '<div style="font-size:11px;font-weight:600;color:var(--purple);text-transform:uppercase;margin-bottom:4px">Rapport technicien</div>' +
                    '<div style="background:#16161a;border-radius:6px;padding:10px;font-size:13px">';
                if (rap.travaux_realises) rHtml += '<div style="margin-bottom:8px"><span style="color:#666">Travaux realises:</span><div style="color:#ccc;margin-top:2px">' + escapeHtml(rap.travaux_realises) + '</div></div>';
                if (rap.alertes) rHtml += '<div style="margin-bottom:8px"><span style="color:var(--red)">Alertes:</span><div style="color:#ccc;margin-top:2px">' + escapeHtml(rap.alertes) + '</div></div>';
                if (rap.recommandations) rHtml += '<div style="margin-bottom:8px"><span style="color:var(--amber)">Recommandations:</span><div style="color:#ccc;margin-top:2px">' + escapeHtml(rap.recommandations) + '</div></div>';
                if (piecesUtilisees) {
                    rHtml += '<div><span style="color:#666">Pieces utilisees:</span><div style="color:#ccc;margin-top:2px">' + piecesUtilisees + '</div></div>';
                }
                rHtml += '</div>';
                rEl.innerHTML = rHtml;
            }).catch(function() {
                var rEl = document.getElementById('hist-rdv-rapport');
                if (rEl) rEl.innerHTML = '';
            });
        }).catch(function(e) {
            alert('Erreur chargement RDV: ' + e.message);
        });
    },

    ouvrirAjouterVehicule: function(clientId) {
        var html = '<div style="display:flex;gap:12px"><div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Marque *</label><input id="add-veh-marque" class="form-input" placeholder="Ex: Yamaha"></div>' +
            '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Modele *</label><input id="add-veh-modele" class="form-input" placeholder="Ex: MT-07"></div></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Plaque *</label><input id="add-veh-plaque" class="form-input" placeholder="AB-123-CD" style="text-transform:uppercase"></div>' +
            '<div style="display:flex;gap:12px"><div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Annee</label><input id="add-veh-annee" class="form-input" type="number" placeholder="2024"></div>' +
            '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Cylindree</label><input id="add-veh-cylindree" class="form-input" placeholder="689cc"></div></div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Type de moto</label><select id="add-veh-type" class="form-select">' +
            '<option value="">--</option><option value="Roadster">Roadster</option><option value="Sportive">Sportive</option><option value="Trail">Trail</option><option value="Custom">Custom</option><option value="Scooter">Scooter</option><option value="Enduro">Enduro</option></select></div>' +
            '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="sauverNouveauVehicule(' + clientId + ')">Ajouter le vehicule</button>';
        showModal('Ajouter un vehicule', html, '450px');
    },

    sauverNouveauVehicule: function(clientId) {
        var marque = ((document.getElementById('add-veh-marque') || {}).value || '').trim();
        var modele = ((document.getElementById('add-veh-modele') || {}).value || '').trim();
        var plaque = ((document.getElementById('add-veh-plaque') || {}).value || '').trim().toUpperCase().replace(/[\s-]/g, '');
        if (!marque || !modele || !plaque) {
            alert('Marque, modele et plaque sont obligatoires');
            return;
        }
        apiPost('/api/clients/' + clientId + '/vehicules', {
            plaque: plaque,
            marque: marque,
            modele: modele,
            annee: (document.getElementById('add-veh-annee') || {}).value ? parseInt(document.getElementById('add-veh-annee').value, 10) : null,
            cylindree: (document.getElementById('add-veh-cylindree') || {}).value || null,
            type_moto: (document.getElementById('add-veh-type') || {}).value || null
        }).then(function() {
            closeModal();
            showNotificationToast('Vehicule ajoute');
            window.ClientsModule.showClientDetail(clientId);
        }).catch(function(e) {
            alert('Erreur: ' + e.message);
        });
    },

    ouvrirModalEditClient: function(clientId) {
        apiGet('/api/clients/' + clientId).then(function(r) {
            return r.json();
        }).then(function(c) {
            var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Nom</label><input id="edit-client-nom" class="form-input" value="' + (escapeHtml(c.nom) || '') + '"></div>' +
                '<div class="form-group"><label class="form-label" style="color:#ccc">Prenom</label><input id="edit-client-prenom" class="form-input" value="' + (escapeHtml(c.prenom) || '') + '"></div>' +
                '<div class="form-group"><label class="form-label" style="color:#ccc">Telephone</label><input id="edit-client-tel" class="form-input" value="' + (escapeHtml(c.telephone) || '') + '"></div>' +
                '<div class="form-group"><label class="form-label" style="color:#ccc">Email</label><input id="edit-client-email" class="form-input" value="' + (escapeHtml(c.email) || '') + '"></div>' +
                '<div class="form-group"><label class="form-label" style="color:#ccc">Adresse</label><input id="edit-client-adresse" class="form-input" value="' + (escapeHtml(c.adresse) || '') + '"></div>' +
                '<div class="form-group"><label class="form-label" style="color:#ccc">Notes</label><textarea id="edit-client-notes" class="form-input" rows="2">' + (escapeHtml(c.notes) || '') + '</textarea></div>' +
                '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="sauverClient(' + clientId + ')">Enregistrer</button>';
            showModal('Modifier client', html, '450px');
        }).catch(function(e) {
            alert('Erreur: ' + e.message);
        });
    },

    sauverClient: function(clientId) {
        var data = {
            nom: (document.getElementById('edit-client-nom') || {}).value,
            prenom: (document.getElementById('edit-client-prenom') || {}).value,
            telephone: (document.getElementById('edit-client-tel') || {}).value,
            email: ((document.getElementById('edit-client-email') || {}).value || '') || null,
            adresse: ((document.getElementById('edit-client-adresse') || {}).value || '') || null,
            notes: ((document.getElementById('edit-client-notes') || {}).value || '') || null
        };
        apiPut('/api/clients/' + clientId, data).then(function() {
            closeModal();
            if (APP.currentSection === 'clients') {
                window.ClientsModule.showClientDetail(clientId);
                window.ClientsModule.loadClients();
            } else {
                refreshCurrentSection();
            }
        }).catch(function(e) {
            alert('Erreur: ' + e.message);
        });
    },

    ouvrirModalEditVehicule: function(vehiculeId) {
        apiGet('/api/vehicules/' + vehiculeId).then(function(r) {
            return r.json();
        }).then(function(v) {
            var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Plaque</label><input id="edit-veh-plaque" class="form-input" value="' + (escapeHtml(v.plaque) || '') + '"></div>' +
                '<div style="display:flex;gap:12px"><div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Marque</label><input id="edit-veh-marque" class="form-input" value="' + (escapeHtml(v.marque) || '') + '"></div>' +
                '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Modele</label><input id="edit-veh-modele" class="form-input" value="' + (escapeHtml(v.modele) || '') + '"></div></div>' +
                '<div style="display:flex;gap:12px"><div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Annee</label><input id="edit-veh-annee" class="form-input" type="number" value="' + (escapeHtml(v.annee) || '') + '"></div>' +
                '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Cylindree</label><input id="edit-veh-cylindree" class="form-input" value="' + (escapeHtml(v.cylindree) || '') + '"></div></div>' +
                '<div class="form-group"><label class="form-label" style="color:#ccc">Type de moto</label><select id="edit-veh-type" class="form-select">' +
                '<option value="">--</option>' +
                ['Roadster', 'Sportive', 'Trail', 'Custom', 'Scooter', 'Enduro'].map(function(t) {
                    return '<option value="' + t + '"' + ((escapeHtml(v.type_moto) === t) ? ' selected' : '') + '>' + t + '</option>';
                }).join('') +
                '</select></div>' +
                '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="sauverVehicule(' + vehiculeId + ')">Enregistrer</button>';
            showModal('Modifier vehicule', html, '450px');
        }).catch(function(e) {
            alert('Erreur: ' + e.message);
        });
    },

    sauverVehicule: function(vehiculeId) {
        var data = {
            plaque: (document.getElementById('edit-veh-plaque') || {}).value || null,
            marque: (document.getElementById('edit-veh-marque') || {}).value || null,
            modele: (document.getElementById('edit-veh-modele') || {}).value || null,
            annee: (document.getElementById('edit-veh-annee') || {}).value ? parseInt(document.getElementById('edit-veh-annee').value, 10) : null,
            cylindree: (document.getElementById('edit-veh-cylindree') || {}).value || null,
            type_moto: (document.getElementById('edit-veh-type') || {}).value || null
        };
        apiPut('/api/vehicules/' + vehiculeId, data).then(function() {
            closeModal();
            showNotificationToast('Vehicule mis a jour');
            refreshCurrentSection();
        }).catch(function(e) {
            alert('Erreur: ' + e.message);
        });
    }
};
