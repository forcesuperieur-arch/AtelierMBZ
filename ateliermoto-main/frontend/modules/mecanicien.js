window.CHECKUP_POINTS = window.CHECKUP_POINTS || [
    { key: 'carrosserie', label: 'Carrosserie / visuel' },
    { key: 'freinage', label: 'Freinage' },
    { key: 'pneus', label: 'Pneus / pression' },
    { key: 'eclairage', label: 'Eclairage / clignotants' },
    { key: 'transmission', label: 'Transmission' },
    { key: 'niveau_huile', label: 'Niveau d\'huile' },
    { key: 'batterie', label: 'Batterie' },
    { key: 'chaine_courroie', label: 'Chaine / Courroie' },
    { key: 'liquide_refroidissement', label: 'Liquide refroidissement' },
    { key: 'filtre_air', label: 'Filtre a air' }
];

window.MecanicienModule = window.MecanicienModule || {
    hasGeneratedOr: function(rdv) {
        if (!rdv) return false;
        if (Array.isArray(rdv.ordres_reparation) && rdv.ordres_reparation.length > 0) return true;
        if (rdv.or_id || rdv.or_numero || rdv.has_or) return true;
        if (window.OrModule && typeof window.OrModule.getLatestOrdreInfo === 'function') {
            return !!window.OrModule.getLatestOrdreInfo(rdv);
        }
        return false;
    },

    ouvrirCheckup: function(rdvId) {
        var rdvRequest = apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).catch(function() {
            return { id: rdvId };
        });
        var rapportRequest = apiGet('/api/rendez-vous/' + rdvId + '/rapport-technicien').then(function(r) { return r.json(); }).catch(function() {
            return null;
        });

        Promise.all([rdvRequest, rapportRequest]).then(function(results) {
            window.MecanicienModule.renderCheckupModal(rdvId, results[1], results[0]);
        });
    },

    renderCheckupModal: function(rdvId, rapport, rdv) {
        var points = (rapport && rapport.points_controle) ? rapport.points_controle : {};
        var alertes = (rapport && rapport.alertes) ? rapport.alertes : '';
        var recommandations = (rapport && rapport.recommandations) ? rapport.recommandations : '';
        var travaux = (rapport && rapport.travaux_realises) ? rapport.travaux_realises : '';
        var canCloseIntervention = !!(rdv && rdv.statut === 'en_cours');
        var saveButtonLabel = canCloseIntervention ? 'Sauvegarder (en cours)' : 'Sauvegarder le rapport';
        var finishButtonHtml = canCloseIntervention
            ? '<button class="btn btn-primary" style="flex:1;background:var(--green)" onclick="sauverCheckup(' + rdvId + ',\'termine\')">Terminer l\'intervention</button>'
            : '';

        window._checkupPoints = {};
        window.CHECKUP_POINTS.forEach(function(pt) {
            window._checkupPoints[pt.key] = points[pt.key] || 'non_verifie';
        });

        var html = '<div style="margin-bottom:16px;font-size:12px;color:#888">RDV #' + rdvId + ' - Points de controle</div>';
        html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">';
        window.CHECKUP_POINTS.forEach(function(pt) {
            var val = points[pt.key] || 'non_verifie';
            var okSel = val === 'ok' ? 'background:var(--green);color:white;' : '';
            var nokSel = val === 'nok' ? 'background:var(--red);color:white;' : '';
            var nvSel = val === 'non_verifie' ? 'background:#444;color:white;' : '';
            html += '<div style="background:var(--dark3);border:1px solid rgba(255,255,255,.07);border-radius:8px;padding:8px 12px;display:flex;align-items:center;justify-content:space-between">' +
                '<span style="font-size:13px;color:#ccc">' + pt.label + '</span>' +
                '<div style="display:flex;gap:4px">' +
                    '<button onclick="setCheckpoint(\'' + pt.key + '\',\'ok\',this)" style="border:none;border-radius:4px;padding:3px 8px;cursor:pointer;font-size:11px;font-weight:600;' + okSel + '">OK</button>' +
                    '<button onclick="setCheckpoint(\'' + pt.key + '\',\'nok\',this)" style="border:none;border-radius:4px;padding:3px 8px;cursor:pointer;font-size:11px;font-weight:600;' + nokSel + '">NOK</button>' +
                    '<button onclick="setCheckpoint(\'' + pt.key + '\',\'non_verifie\',this)" style="border:none;border-radius:4px;padding:3px 8px;cursor:pointer;font-size:11px;font-weight:600;' + nvSel + '">-</button>' +
                '</div>' +
            '</div>';
        });
        html += '</div>';

        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Alertes / Problemes detectes</label>' +
            '<textarea id="checkup-alertes" class="form-input" rows="2" placeholder="Problemes constates...">' + alertes + '</textarea></div>';
        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Recommandations</label>' +
            '<textarea id="checkup-recommandations" class="form-input" rows="2" placeholder="Recommandations client...">' + recommandations + '</textarea></div>';
        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Travaux effectues</label>' +
            '<textarea id="checkup-travaux" class="form-input" rows="2" placeholder="Detail des travaux...">' + travaux + '</textarea></div>';
        html += '<div style="display:flex;gap:8px;margin-top:16px">' +
            '<button class="btn btn-primary" style="flex:1" onclick="sauverCheckup(' + rdvId + ',\'en_cours\')">' + saveButtonLabel + '</button>' +
            finishButtonHtml +
            '</div>';

        showModal('Rapport Technicien - Checkup', html, '650px');
    },

    setCheckpoint: function(key, value, btn) {
        var container = btn.parentElement;
        var buttons = container.querySelectorAll('button');
        buttons.forEach(function(b) { b.style.background = ''; b.style.color = ''; });
        if (value === 'ok') { btn.style.background = 'var(--green)'; btn.style.color = 'white'; }
        else if (value === 'nok') { btn.style.background = 'var(--red)'; btn.style.color = 'white'; }
        else { btn.style.background = '#444'; btn.style.color = 'white'; }

        if (!window._checkupPoints) window._checkupPoints = {};
        window._checkupPoints[key] = value;
    },

    sauverCheckup: function(rdvId, statut) {
        var data = {
            points_controle: window._checkupPoints || {},
            alertes: document.getElementById('checkup-alertes') ? document.getElementById('checkup-alertes').value : '',
            recommandations: document.getElementById('checkup-recommandations') ? document.getElementById('checkup-recommandations').value : '',
            travaux_realises: document.getElementById('checkup-travaux') ? document.getElementById('checkup-travaux').value : '',
            statut: statut
        };
        var requestPath = statut === 'termine'
            ? '/api/rendez-vous/' + rdvId + '/terminer-avec-rapport'
            : '/api/rendez-vous/' + rdvId + '/rapport-technicien';
        var successMessage = statut === 'termine'
            ? 'Intervention terminee et rapport sauvegarde'
            : 'Rapport sauvegarde avec succes';

        return apiPost(requestPath, data).then(function(r) { return r.json(); }).then(function() {
            closeModal();
            window._checkupPoints = {};
            alert(successMessage);
            refreshCurrentSection();
        }).catch(function(e) {
            alert('Erreur sauvegarde: ' + e.message);
        });
    },

    loadEspaceMeca: function() {
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
            if (!meca) {
                var emptyHeader = document.getElementById('espace-meca-header');
                var emptyContainer = document.getElementById('meca-rdv-list');
                if (emptyHeader) {
                    emptyHeader.innerHTML = '<div style="flex:1"><div class="meca-greeting">Profil technicien introuvable</div><div class="meca-sub">Associez ce compte a un mecanicien pour utiliser l\'espace atelier.</div></div>';
                }
                if (emptyContainer) {
                    emptyContainer.innerHTML = '<div class="meca-empty-state"><div style="font-size:42px;margin-bottom:10px">🧰</div><div style="font-size:16px;color:#e5e7eb">Aucun profil mecanicien lie a ce compte</div><div class="meca-helper-text" style="margin-top:8px">Un admin peut rattacher l\'utilisateur a une fiche technicien depuis la gestion atelier.</div></div>';
                }
                return;
            }
            APP._currentMeca = meca;
            APP._mecaCheckupData = null;
            window.MecanicienModule.renderEspaceMeca(meca, rdvs);

            var isAdmin = APP.currentUser && (APP.currentUser.role === 'admin' || APP.currentUser.role === 'manager');
            var visibleRdvs = isAdmin ? (rdvs || []) : (rdvs || []).filter(function(r) { return r.mecanicien_id === meca.id; });
            var activeRdv = visibleRdvs.find(function(r) { return r.statut === 'en_cours'; });
            if (activeRdv) {
                apiGet('/api/rendez-vous/' + activeRdv.id + '/rapport-technicien').then(function(r) { return r.json(); }).then(function(rapport) {
                    APP._mecaCheckupData = rapport;
                    window.MecanicienModule.renderEspaceMeca(meca, rdvs);
                }).catch(function() {});
            }
        }).catch(function(e) { console.error('Erreur espace meca:', e); });
    },

    renderEspaceMeca: function(meca, allRdvs) {
        var color = meca.couleur || '#8b5cf6';
        var initials = getMecaInitials(meca);
        var isAdmin = APP.currentUser && (APP.currentUser.role === 'admin' || APP.currentUser.role === 'manager');
        var mecaRdvs = isAdmin ? allRdvs : allRdvs.filter(function(r) { return r.mecanicien_id === meca.id; });
        var todoPriority = { reception: 0, confirme: 1, reserve: 2, en_attente: 2 };
        mecaRdvs.sort(function(a, b) {
            var pa = Object.prototype.hasOwnProperty.call(todoPriority, a.statut) ? todoPriority[a.statut] : 9;
            var pb = Object.prototype.hasOwnProperty.call(todoPriority, b.statut) ? todoPriority[b.statut] : 9;
            if (pa !== pb) return pa - pb;
            return (a.heure_rdv || '').localeCompare(b.heure_rdv || '');
        });
        var now = new Date();
        var activeRdv = mecaRdvs.find(function(r) { return r.statut === 'en_cours'; });
        var aFaire = mecaRdvs.filter(function(r) { return r.statut === 'reserve' || r.statut === 'confirme' || r.statut === 'reception' || r.statut === 'en_attente'; });
        var termines = mecaRdvs.filter(function(r) { return r.statut === 'termine' || r.statut === 'restitue' || r.statut === 'facture' || r.statut === 'paye'; });
        var enRetard = aFaire.filter(function(r) {
            var hm = String(r.heure_rdv || '').split(':');
            if (hm.length < 2) return false;
            var d = new Date(now.getFullYear(), now.getMonth(), now.getDate(), parseInt(hm[0], 10) || 0, parseInt(hm[1], 10) || 0, 0, 0);
            return d.getTime() + 10 * 60 * 1000 < now.getTime();
        });
        aFaire.sort(function(a, b) {
            var aReady = a.statut === 'reception' ? 1 : 0;
            var bReady = b.statut === 'reception' ? 1 : 0;
            if (aReady !== bReady) return bReady - aReady;
            var aLate = enRetard.indexOf(a) !== -1 ? 1 : 0;
            var bLate = enRetard.indexOf(b) !== -1 ? 1 : 0;
            if (aLate !== bLate) return bLate - aLate;
            return (a.heure_rdv || '').localeCompare(b.heure_rdv || '');
        });
        var nextAction = aFaire.find(function(r) { return r.statut === 'reception'; }) || (aFaire.length ? aFaire[0] : null);
        var inProgressCount = mecaRdvs.filter(function(r) { return r.statut === 'en_cours'; }).length;
        var readyCount = aFaire.filter(function(r) { return r.statut === 'reception'; }).length;
        var waitingCount = Math.max(0, aFaire.length - readyCount);
        var completionRate = mecaRdvs.length ? Math.round((termines.length / mecaRdvs.length) * 100) : 0;
        var focusRdv = activeRdv || nextAction;
        var focusVehicule = focusRdv && focusRdv.vehicule
            ? ((focusRdv.vehicule.marque || '') + ' ' + (focusRdv.vehicule.modele || '')).trim()
            : '';
        var focusClient = focusRdv && focusRdv.client
            ? ((focusRdv.client.prenom || '') + ' ' + (focusRdv.client.nom || '')).trim()
            : '';
        var focusTone = activeRdv ? 'live' : (focusRdv && focusRdv.statut === 'reception' ? 'ready' : 'pending');
        var focusTitle = activeRdv
            ? 'Intervention en cours'
            : (focusRdv ? (focusRdv.statut === 'reception' ? 'Vehicule pret a lancer' : 'Priorite reception') : 'Atelier calme');

        var header = document.getElementById('espace-meca-header');
        if (header) {
            header.innerHTML = '<div class="meca-av-big" style="background:' + hexToRgba(color, 0.3) + ';color:' + color + '">' + initials + '</div>' +
                '<div style="flex:1"><div class="meca-greeting">Cockpit atelier • ' + escapeHtml(meca.prenom) + '</div>' +
                '<div class="meca-sub">' + mecaRdvs.length + ' interventions aujourd\'hui • ' + enRetard.length + ' en retard</div>' +
                '<div class="meca-header-pills">' +
                    '<span class="meca-mini-pill">▶ ' + inProgressCount + ' en cours</span>' +
                    '<span class="meca-mini-pill">🟢 ' + readyCount + ' prets</span>' +
                    '<span class="meca-mini-pill">✅ ' + termines.length + ' termines</span>' +
                '</div></div>';
        }

        var container = document.getElementById('meca-rdv-list');
        if (!container) return;
        var html = '<div class="meca-overview-card">' +
            '<div class="meca-overview-main">' +
                '<div class="meca-status-row"><span class="meca-status-chip ' + focusTone + '">' + focusTitle + '</span>' +
                '<span class="meca-status-chip ' + (enRetard.length ? 'pending' : 'ready') + '">' + (enRetard.length ? (enRetard.length + ' en retard') : 'Atelier sous controle') + '</span></div>' +
                '<div class="meca-overview-title">Vue atelier mobile</div>' +
                '<div class="meca-helper-text">' +
                    (focusRdv
                        ? (escapeHtml(formatTime(focusRdv.heure_rdv || '') || '--:--') + ' • ' + escapeHtml(focusVehicule || 'Vehicule') + (focusClient ? ' • ' + escapeHtml(focusClient) : ''))
                        : 'Aucune intervention affectee pour le moment.') +
                '</div>' +
            '</div>' +
            '<div class="meca-kpi-grid">' +
                '<div class="meca-kpi-card"><div class="meca-kpi-value">' + inProgressCount + '</div><div class="meca-kpi-label">En cours</div></div>' +
                '<div class="meca-kpi-card"><div class="meca-kpi-value">' + readyCount + '</div><div class="meca-kpi-label">A lancer</div></div>' +
                '<div class="meca-kpi-card"><div class="meca-kpi-value">' + waitingCount + '</div><div class="meca-kpi-label">A receptionner</div></div>' +
                '<div class="meca-kpi-card accent"><div class="meca-kpi-value">' + completionRate + '%</div><div class="meca-kpi-label">Journee bouclee</div></div>' +
            '</div>' +
        '</div>';

        if (!activeRdv && nextAction) {
            var nextClient = nextAction.client || {};
            var nextNeedsReception = nextAction.statut !== 'reception';
            var nextHasOr = window.MecanicienModule.hasGeneratedOr(nextAction);
            var nextVehicule = ((nextAction.vehicule && nextAction.vehicule.marque) || '') + ' ' + ((nextAction.vehicule && nextAction.vehicule.modele) || '');
            var nextClientName = ((nextClient.prenom || '') + ' ' + (nextClient.nom || '')).trim();
            var nextCallBtn = nextClient.telephone
                ? '<a class="meca-big-btn info" href="tel:' + escapeHtml(nextClient.telephone) + '">📞 Appeler client</a>'
                : '';
            var nextOrBtn = nextHasOr
                ? '<button class="meca-big-btn ghost" onclick="telechargerOR(' + nextAction.id + ')">📄 Voir OR</button>'
                : '';
            var nextPrimaryAction = nextNeedsReception
                ? '<div class="meca-helper-text">Passe d\'abord par la reception pour la prise en charge et la signature OR.</div><div class="meca-inline-actions">' + nextCallBtn + nextOrBtn + '</div>'
                : '<div class="meca-helper-text">Tout est pret, tu peux lancer le chrono atelier.</div><div class="meca-inline-actions"><button class="meca-big-btn start" onclick="demarrerTravail(' + nextAction.id + ')">▶ DEMARRER</button>' + nextCallBtn + nextOrBtn + '</div>';
            html += '<div class="meca-priority-card ' + (nextNeedsReception ? 'needs-reception' : 'ready') + '">' +
                '<div class="meca-priority-eyebrow">Priorite atelier</div>' +
                '<div class="meca-priority-title">' + escapeHtml(formatTime(nextAction.heure_rdv) || '--:--') + ' • ' + escapeHtml(nextVehicule.trim() || 'Vehicule') + '</div>' +
                '<div class="meca-priority-meta">' + escapeHtml(nextAction.type_intervention || '-') + (nextClientName ? ' • ' + escapeHtml(nextClientName) : '') + '</div>' +
                '<div class="meca-status-row" style="margin-top:10px"><span class="meca-status-chip ' + (nextNeedsReception ? 'pending' : 'ready') + '">' + (nextNeedsReception ? 'Reception requise' : 'Pret a demarrer') + '</span>' +
                    (enRetard.indexOf(nextAction) !== -1 ? '<span class="meca-card-badge late">Retard atelier</span>' : '<span class="meca-card-badge">Flux normal</span>') +
                '</div>' +
                nextPrimaryAction +
            '</div>';
        }

        if (activeRdv) {
            html += window.MecanicienModule.renderMecaActivePanel(activeRdv);
        }
        if (aFaire.length) {
            html += '<div class="meca-section-title">▶ A faire (' + aFaire.length + ')</div>';
            aFaire.forEach(function(rdv) { html += window.MecanicienModule.renderMecaCard(rdv, 'todo'); });
        }
        if (termines.length) {
            html += '<div class="meca-section-title">✅ Termines (' + termines.length + ')</div>';
            termines.forEach(function(rdv) { html += window.MecanicienModule.renderMecaCard(rdv, 'done'); });
        }
        if (!activeRdv && !aFaire.length && !termines.length) {
            html = '<div style="text-align:center;padding:40px 16px;color:#666">' +
                '<div style="font-size:48px;margin-bottom:12px">🏍️</div>' +
                '<div style="font-size:16px">Pas d\'intervention aujourd\'hui</div></div>';
        }

        container.innerHTML = html;
        if (activeRdv) window.MecanicienModule.startMecaLiveTimer(activeRdv);
        else window.MecanicienModule.cleanupMecaTimer();
    },

    renderMecaActivePanel: function(rdv) {
        var v = rdv.vehicule || {};
        var c = rdv.client || {};
        var pont = APP.ponts.find(function(p) { return p.id === rdv.pont_id; });
        var rapport = APP._mecaCheckupData;
        var points = (rapport && rapport.points_controle) ? rapport.points_controle : {};

        window._checkupPoints = {};
        window.CHECKUP_POINTS.forEach(function(pt) { window._checkupPoints[pt.key] = points[pt.key] || 'non_verifie'; });
        var checked = window.CHECKUP_POINTS.filter(function(pt) { return points[pt.key] && points[pt.key] !== 'non_verifie'; }).length;

        var dur = window.PlanningUtils.getRdvDurationMinutes(rdv);
        var durStr = dur >= 60 ? (Math.round(dur / 6) / 10) + 'h' : dur + 'min';
        var startedRaw = rdv && rdv.heure_debut_travail ? String(rdv.heure_debut_travail).split('T').pop() : (rdv.heure_rdv || '');
        var startedLabel = formatTime(startedRaw || '') || '--:--';
        var clientPhone = c.telephone ? String(c.telephone) : '';
        var hasOr = window.MecanicienModule.hasGeneratedOr(rdv);
        var html = '<div class="meca-active-panel" id="meca-active-panel">';
        html += '<div class="meca-chrono">' +
            '<div id="meca-live-clock" class="meca-chrono-time">00:00:00</div>' +
            '<div id="meca-live-eta" class="meca-chrono-eta">Chrono en attente...</div>' +
            '<div class="meca-chrono-bar"><div id="meca-progress-bar" class="meca-chrono-fill" style="width:0%"></div></div></div>';
        html += '<div class="meca-info-block">' +
            '<div class="meca-status-row"><span class="meca-status-chip live">En cours</span>' + (clientPhone ? '<a class="meca-status-link" href="tel:' + escapeHtml(clientPhone) + '">📞 ' + escapeHtml(clientPhone) + '</a>' : '') + '</div>' +
            '<div class="meca-info-vehicle">🏍️ ' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div>' +
            '<div class="meca-info-client">👤 ' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + (pont ? ' • ' + escapeHtml(pont.nom) : '') + '</div>' +
            '<div class="meca-info-type">🔧 ' + (escapeHtml(rdv.type_intervention) || '') + '</div>' +
            '<div class="meca-info-grid">' +
                '<div class="meca-info-stat"><span>Temps prevu</span><strong>' + durStr + '</strong></div>' +
                '<div class="meca-info-stat"><span>Debut</span><strong>' + escapeHtml(startedLabel) + '</strong></div>' +
                '<div class="meca-info-stat"><span>Pont</span><strong>' + escapeHtml(pont ? (pont.nom || '-') : 'A definir') + '</strong></div>' +
                '<div class="meca-info-stat"><span>Client</span><strong>' + escapeHtml(clientPhone || 'Sans numero') + '</strong></div>' +
            '</div>' +
            '<div class="meca-quick-note">Passe en travaux supp des qu\'un point est NOK pour garder le client et la reception alignes.</div>' +
            (rdv.notes ? '<div class="meca-info-notes">📝 ' + escapeHtml(rdv.notes) + '</div>' : '') + '</div>';
        html += '<div class="meca-checkup-block">' +
            '<div class="meca-checkup-header" onclick="toggleMecaCheckup()">' +
            '<span>☑️ Checkup express (' + checked + '/' + window.CHECKUP_POINTS.length + ')</span>' +
            '<span id="meca-checkup-arrow" class="meca-checkup-arrow">▼</span></div>' +
            '<div id="meca-checkup-body" class="meca-checkup-body">' +
            '<div class="meca-checkup-tip">Tapote OK / NOK au fil du diag, puis note uniquement l\'essentiel pour aller vite en atelier.</div>';

        window.CHECKUP_POINTS.forEach(function(pt) {
            var val = points[pt.key] || 'non_verifie';
            html += '<div class="meca-check-item">' +
                '<span class="meca-check-label">' + pt.label + '</span>' +
                '<div class="meca-check-btns">' +
                '<button class="meca-check-btn' + (val === 'ok' ? ' ok' : '') + '" onclick="setMecaCheck(\'' + pt.key + '\',\'ok\',this)">OK</button>' +
                '<button class="meca-check-btn' + (val === 'nok' ? ' nok' : '') + '" onclick="setMecaCheck(\'' + pt.key + '\',\'nok\',this)">NOK</button></div></div>';
        });

        html += '<div style="margin-top:10px"><textarea id="meca-notes" class="meca-notes-input" rows="4" placeholder="Notes atelier, alerte client, piece a commander..." style="font-size:16px;min-height:80px">' +
            escapeHtml((rapport && rapport.alertes) || '') + '</textarea></div></div></div>';
        html += '<div class="meca-action-zone-title">Raccourcis atelier</div>' +
            '<div class="meca-actions">' +
            '<button class="meca-big-btn ghost" onclick="ouvrirPhotoCapture(' + rdv.id + ')">📷 Photo</button>' +
            '<button class="meca-big-btn ghost" onclick="ouvrirPiecesUtilisees(' + rdv.id + ')">🔩 Pièces utilisées</button>' +
            (clientPhone ? '<a class="meca-big-btn info" href="tel:' + escapeHtml(clientPhone) + '">📞 Appeler client</a>' : '') +
            (hasOr ? '<button class="meca-big-btn ghost" onclick="telechargerOR(' + rdv.id + ')">📄 Voir OR</button>' : '') +
            '<button class="meca-big-btn warning" onclick="ouvrirDemandeTravauxSupp(' + rdv.id + ')">⚠️ Travaux supp / blocage</button>' +
            '<button class="meca-big-btn success" onclick="terminerTravail(' + rdv.id + ')">✅ TERMINER</button></div>';
        html += '</div>';
        return html;
    },

    renderMecaCard: function(rdv, type) {
        var v = rdv.vehicule || {};
        var c = rdv.client || {};
        var pont = APP.ponts.find(function(p) { return p.id === rdv.pont_id; });
        var dur = window.PlanningUtils.getRdvDurationMinutes(rdv);
        var durStr = dur >= 60 ? (Math.round(dur / 6) / 10) + 'h' : dur + 'min';
        var todoMeta = { label: 'Reception requise', tone: 'pending', helper: 'OR signe + prise en charge reception avant demarrage.', canStart: false };
        if (rdv.statut === 'reception') {
            todoMeta = { label: 'Pret a demarrer', tone: 'ready', helper: 'Vehicule receptionne, lancement atelier autorise.', canStart: true };
        } else if (rdv.statut === 'reserve' || rdv.statut === 'en_attente') {
            todoMeta = { label: 'A confirmer', tone: 'pending', helper: 'Le RDV doit encore etre confirme puis receptionne.', canStart: false };
        }
        var now = new Date();
        var hm = String(rdv.heure_rdv || '').split(':');
        var scheduledAt = hm.length >= 2 ? new Date(now.getFullYear(), now.getMonth(), now.getDate(), parseInt(hm[0], 10) || 0, parseInt(hm[1], 10) || 0, 0, 0) : null;
        var isLate = type === 'todo' && scheduledAt && (scheduledAt.getTime() + 10 * 60 * 1000 < now.getTime());
        var callBtn = c.telephone ? '<a class="meca-sm-btn" href="tel:' + escapeHtml(c.telephone) + '">Appeler</a>' : '';
        var hasOr = window.MecanicienModule.hasGeneratedOr(rdv);
        var doneLabel = rdv.statut === 'restitue' ? 'Restitue' : ((rdv.statut === 'facture' || rdv.statut === 'paye') ? 'Cloture' : 'Termine');

        var html = '<div class="meca-rdv-card ' + type + (isLate ? ' late' : '') + '">';
        html += '<div class="meca-card-time">' + formatTime(rdv.heure_rdv) + '</div>';
        html += '<div class="meca-card-body">' +
            '<div class="meca-card-meta-row">' +
                '<span class="meca-status-chip ' + (type === 'todo' ? todoMeta.tone : 'ready') + '">' + (type === 'todo' ? todoMeta.label : doneLabel) + '</span>' +
                '<span class="meca-card-badge' + (isLate ? ' late' : '') + '">' + (isLate ? 'Retard atelier' : (type === 'todo' ? 'Planifie' : 'Archive du jour')) + '</span>' +
            '</div>' +
            '<div class="meca-card-vehicle">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div>' +
            '<div class="meca-card-client">' + (escapeHtml(c.prenom) || '').charAt(0) + '. ' + (escapeHtml(c.nom) || '') + (pont ? ' • ' + escapeHtml(pont.nom) : '') + '</div>' +
            '<div class="meca-card-type">' + (escapeHtml(rdv.type_intervention) || '') + ' • ' + durStr + '</div></div>';

        if (type === 'todo') {
            html += '<div class="meca-card-side">' +
                '<div class="meca-card-next">' + todoMeta.helper + '</div>' +
                '<div class="meca-card-actions todo">' +
                    (todoMeta.canStart ? '<button class="meca-big-btn start" onclick="event.stopPropagation();demarrerTravail(' + rdv.id + ')">▶ DEMARRER</button>' : '') +
                    callBtn +
                    (hasOr ? '<button class="meca-sm-btn" onclick="event.stopPropagation();telechargerOR(' + rdv.id + ')">OR</button>' : '') +
                '</div></div>';
        } else {
            html += '<div class="meca-card-side">' +
                '<div class="meca-card-next">Rapport accessible et intervention archivee pour la journee.</div>' +
                '<div class="meca-card-actions">' +
                    (hasOr ? '<button class="meca-sm-btn" onclick="telechargerOR(' + rdv.id + ')">OR</button>' : '') +
                    '<button class="meca-sm-btn" onclick="ouvrirCheckup(' + rdv.id + ')">Rapport</button>' +
                '</div></div>';
        }
        html += '</div>';
        return html;
    },

    toggleMecaCheckup: function() {
        var body = document.getElementById('meca-checkup-body');
        var arrow = document.getElementById('meca-checkup-arrow');
        if (!body) return;
        var show = body.style.display === 'none';
        body.style.display = show ? 'block' : 'none';
        if (arrow) arrow.textContent = show ? '▲' : '▼';
    },

    setMecaCheck: function(key, value, btn) {
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
        window.CHECKUP_POINTS.forEach(function(pt) {
            if (window._checkupPoints[pt.key] && window._checkupPoints[pt.key] !== 'non_verifie') checked++;
        });
        var hdr = document.querySelector('.meca-checkup-header span');
        if (hdr) hdr.textContent = '☑️ Checkup (' + checked + '/' + window.CHECKUP_POINTS.length + ')';
    },

    startMecaLiveTimer: function(rdv) {
        window.MecanicienModule.cleanupMecaTimer();
        function tick() {
            var clockEl = document.getElementById('meca-live-clock');
            var etaEl = document.getElementById('meca-live-eta');
            var barEl = document.getElementById('meca-progress-bar');
            if (!clockEl) return;
            var startAt = window.PlanningUtils.parseUTCDate(rdv && rdv.heure_debut_travail ? rdv.heure_debut_travail : null);
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
            var totalMin = window.PlanningUtils.getRdvDurationMinutes(rdv);
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
    },

    cleanupMecaTimer: function() {
        if (APP._mecaLiveTimer) { clearInterval(APP._mecaLiveTimer); APP._mecaLiveTimer = null; }
    },

    // ===== PHOTO CAPTURE =====
    ouvrirPhotoCapture: function(rdvId) {
        var html = '<div id="photo-capture-container">' +
            '<div style="margin-bottom:12px">' +
            '<input type="file" id="photo-file-input" accept="image/*" capture="environment" style="display:none" onchange="handleMecaPhotoSelected(this,' + rdvId + ')">' +
            '<button class="btn btn-primary" style="width:100%;margin-bottom:8px" onclick="document.getElementById(\'photo-file-input\').click()">📷 Prendre une photo</button>' +
            '</div>' +
            '<div id="photo-preview" style="display:none;margin-bottom:12px;text-align:center">' +
            '<canvas id="photo-canvas" style="max-width:100%;border-radius:8px;border:1px solid rgba(255,255,255,.08)"></canvas>' +
            '<div style="margin-top:8px;display:flex;gap:6px;justify-content:center">' +
            '<button class="btn btn-ghost" onclick="clearPhotoAnnotation()" style="font-size:12px">Effacer annotations</button>' +
            '<select id="photo-pen-color" style="background:#333;color:#eee;border:1px solid #555;border-radius:4px;padding:4px">' +
            '<option value="#ef4444">Rouge</option><option value="#fbbf24">Jaune</option><option value="#22c55e">Vert</option><option value="#3b82f6">Bleu</option><option value="#ffffff">Blanc</option></select>' +
            '</div></div>' +
            '<textarea id="photo-description" class="form-input" rows="2" placeholder="Description / commentaire..." style="margin-bottom:8px"></textarea>' +
            '<button class="btn btn-primary" style="width:100%" onclick="uploadMecaPhoto(' + rdvId + ')">Envoyer la photo</button>' +
            '<div style="margin-top:16px"><div class="card-title" style="color:#888;font-size:12px;margin-bottom:8px">Photos existantes</div>' +
            '<div id="existing-photos-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px"></div></div>' +
            '</div>';
        showModal('📷 Photos intervention #' + rdvId, html, '480px');
        window.MecanicienModule._loadExistingPhotos(rdvId);
    },

    _loadExistingPhotos: function(rdvId) {
        apiGet('/api/rendez-vous/' + rdvId + '/photos').then(function(r) { return r.json(); }).then(function(photos) {
            var grid = document.getElementById('existing-photos-grid');
            if (!grid) return;
            if (!photos || !photos.length) { grid.innerHTML = '<div style="color:#666;font-size:12px">Aucune photo</div>'; return; }
            grid.innerHTML = photos.map(function(p) {
                return '<div style="position:relative;aspect-ratio:1;border-radius:6px;overflow:hidden;border:1px solid rgba(255,255,255,.07)">' +
                    '<img src="' + p.url + '" style="width:100%;height:100%;object-fit:cover" loading="lazy">' +
                    '<button onclick="deleteMecaPhoto(' + p.id + ',' + rdvId + ')" style="position:absolute;top:2px;right:2px;background:rgba(239,68,68,0.8);border:none;color:white;width:20px;height:20px;border-radius:50%;cursor:pointer;font-size:11px">×</button>' +
                    (p.description ? '<div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,0.6);color:#eee;font-size:10px;padding:3px 5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escapeHtml(p.description) + '</div>' : '') +
                    '</div>';
            }).join('');
        }).catch(function() {});
    },

    _initPhotoCanvas: function(img) {
        var canvas = document.getElementById('photo-canvas');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var maxW = 460;
        var scale = Math.min(1, maxW / img.width);
        canvas.width = img.width * scale;
        canvas.height = img.height * scale;
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        window._photoBaseImage = img;
        window._photoAnnotations = [];
        window._photoDrawing = false;

        function getPos(e) {
            var rect = canvas.getBoundingClientRect();
            var touch = e.touches ? e.touches[0] : e;
            return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
        }

        canvas.onmousedown = canvas.ontouchstart = function(e) {
            e.preventDefault();
            window._photoDrawing = true;
            window._photoLastPos = getPos(e);
        };
        canvas.onmousemove = canvas.ontouchmove = function(e) {
            if (!window._photoDrawing) return;
            e.preventDefault();
            var pos = getPos(e);
            var color = document.getElementById('photo-pen-color');
            ctx.beginPath();
            ctx.moveTo(window._photoLastPos.x, window._photoLastPos.y);
            ctx.lineTo(pos.x, pos.y);
            ctx.strokeStyle = color ? color.value : '#ef4444';
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.stroke();
            window._photoAnnotations.push({ from: window._photoLastPos, to: pos, color: color ? color.value : '#ef4444' });
            window._photoLastPos = pos;
        };
        canvas.onmouseup = canvas.ontouchend = function() { window._photoDrawing = false; };
    },

    // ===== PIECES UTILISEES =====
    ouvrirPiecesUtilisees: function(rdvId) {
        var html = '<div id="pieces-container">' +
            '<div style="margin-bottom:12px">' +
            '<input class="form-input" id="piece-search" placeholder="Rechercher une piece..." oninput="searchPieceMeca(this.value)" style="margin-bottom:6px">' +
            '<div id="piece-search-results" style="max-height:150px;overflow-y:auto;background:#1a1a2a;border-radius:6px"></div>' +
            '</div>' +
            '<div style="margin-top:12px"><div class="card-title" style="color:#888;font-size:12px;margin-bottom:8px">Pièces ajoutées</div>' +
            '<div id="pieces-list"></div></div>' +
            '</div>';
        showModal('🔩 Pièces utilisées — RDV #' + rdvId, html, '480px');
        window.MecanicienModule._loadExistingPieces(rdvId);
    },

    _loadExistingPieces: function(rdvId) {
        apiGet('/api/rendez-vous/' + rdvId + '/pieces').then(function(r) { return r.json(); }).then(function(data) {
            var pieces = data.pieces || [];
            var list = document.getElementById('pieces-list');
            if (!list) return;
            if (!pieces || !pieces.length) { list.innerHTML = '<div style="color:#666;font-size:12px">Aucune pièce ajoutée</div>'; return; }
            list.innerHTML = pieces.map(function(pu) {
                return '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px;background:#1a1a2a;border-radius:6px;margin-bottom:4px">' +
                    '<div><div style="color:#eee;font-size:13px">' + escapeHtml(pu.nom || 'Pièce #' + pu.piece_id) + '</div>' +
                    '<div style="color:#888;font-size:11px">Qté: ' + pu.quantite + (pu.prix_vente_unitaire ? ' × ' + parseFloat(pu.prix_vente_unitaire).toFixed(2) + '€' : '') + '</div></div>' +
                    '<button class="btn btn-ghost" style="font-size:11px;color:#ef4444" onclick="deletePieceUtilisee(' + pu.id + ',' + rdvId + ')">Retirer</button></div>';
            }).join('');
        }).catch(function() {});
    }
};

// ===== GLOBAL FUNCTIONS FOR PHOTO & PIECES =====
function ouvrirPhotoCapture(rdvId) { return window.MecanicienModule.ouvrirPhotoCapture(rdvId); }
function ouvrirPiecesUtilisees(rdvId) { return window.MecanicienModule.ouvrirPiecesUtilisees(rdvId); }

function handleMecaPhotoSelected(input, rdvId) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    window._mecaPhotoFile = file;
    var reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('photo-preview').style.display = 'block';
        var img = new Image();
        img.onload = function() { window.MecanicienModule._initPhotoCanvas(img); };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

function clearPhotoAnnotation() {
    if (!window._photoBaseImage) return;
    window._photoAnnotations = [];
    window.MecanicienModule._initPhotoCanvas(window._photoBaseImage);
}

function uploadMecaPhoto(rdvId) {
    var canvas = document.getElementById('photo-canvas');
    var desc = document.getElementById('photo-description');

    if (!window._mecaPhotoFile) { showToast('Veuillez sélectionner une photo', 'error'); return; }

    var formData = new FormData();

    // If annotations, send the canvas as the file (with annotations baked in)
    if (window._photoAnnotations && window._photoAnnotations.length > 0 && canvas) {
        canvas.toBlob(function(blob) {
            formData.append('file', blob, window._mecaPhotoFile.name || 'photo.jpg');
            formData.append('description', (desc && desc.value) || '');
            formData.append('annotation_json', JSON.stringify(window._photoAnnotations));
            _doUploadPhoto(formData, rdvId);
        }, 'image/jpeg', 0.85);
    } else {
        formData.append('file', window._mecaPhotoFile);
        formData.append('description', (desc && desc.value) || '');
        _doUploadPhoto(formData, rdvId);
    }
}

function _doUploadPhoto(formData, rdvId) {
    fetch('/api/rendez-vous/' + rdvId + '/photos', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + (localStorage.getItem('token') || '') },
        body: formData
    }).then(function(r) {
        if (!r.ok) throw new Error('Erreur upload');
        return r.json();
    }).then(function() {
        showToast('Photo envoyée !', 'success');
        window._mecaPhotoFile = null;
        var preview = document.getElementById('photo-preview');
        if (preview) preview.style.display = 'none';
        var descEl = document.getElementById('photo-description');
        if (descEl) descEl.value = '';
        window.MecanicienModule._loadExistingPhotos(rdvId);
    }).catch(function(e) { showToast(e.message || 'Erreur upload', 'error'); });
}

function deleteMecaPhoto(photoId, rdvId) {
    if (!confirm('Supprimer cette photo ?')) return;
    fetch('/api/photos/' + photoId, { method: 'DELETE', headers: { 'Authorization': 'Bearer ' + (localStorage.getItem('token') || '') } })
        .then(function(r) { if (!r.ok) throw new Error('Erreur'); return r.json(); })
        .then(function() { showToast('Photo supprimée', 'info'); window.MecanicienModule._loadExistingPhotos(rdvId); })
        .catch(function() { showToast('Erreur suppression', 'error'); });
}

function searchPieceMeca(val) {
    var container = document.getElementById('piece-search-results');
    if (!container) return;
    if (!val || val.length < 2) { container.innerHTML = ''; return; }
    apiGet('/api/pieces?search=' + encodeURIComponent(val) + '&limit=10').then(function(r) { return r.json(); }).then(function(pieces) {
        if (!pieces || !pieces.length) { container.innerHTML = '<div style="color:#666;padding:8px;font-size:12px">Aucune pièce trouvée</div>'; return; }
        container.innerHTML = pieces.map(function(p) {
            return '<div style="padding:8px;cursor:pointer;border-bottom:1px solid #2a2a3a;display:flex;justify-content:space-between;align-items:center" onclick="ajouterPieceMeca(' + p.id + ',\'' + escapeHtml(p.nom || '') + '\',' + (p.prix_vente_ht || 0) + ')">' +
                '<div><div style="color:#eee;font-size:13px">' + escapeHtml(p.nom || 'Pièce #' + p.id) + '</div>' +
                '<div style="color:#888;font-size:11px">' + (p.reference || '') + '</div></div>' +
                '<div style="color:#FB923C;font-weight:600">' + (p.prix_vente_ht ? parseFloat(p.prix_vente_ht).toFixed(2) + '€' : '-') + '</div></div>';
        }).join('');
    }).catch(function() {});
}

function ajouterPieceMeca(pieceId, nom, prix) {
    var rdvId = APP._mecaSelectedRdvId || (APP._mecaLastRdvs || []).find(function(r) { return r.statut === 'en_cours'; });
    if (rdvId && typeof rdvId === 'object') rdvId = rdvId.id;
    if (!rdvId) { showToast('Aucune intervention active', 'error'); return; }
    var qtyStr = prompt('Quantité pour "' + nom + '" :', '1');
    var qty = parseInt(qtyStr, 10);
    if (!qty || qty < 1) return;
    apiPost('/api/rendez-vous/' + rdvId + '/pieces', { piece_id: pieceId, quantite: qty, prix_vente_unitaire: prix || null })
        .then(function(r) { if (!r.ok) throw new Error('Erreur'); return r.json(); })
        .then(function() { showToast('Pièce ajoutée', 'success'); window.MecanicienModule._loadExistingPieces(rdvId); })
        .catch(function() { showToast('Erreur ajout pièce', 'error'); });
}

function deletePieceUtilisee(puId, rdvId) {
    if (!confirm('Retirer cette pièce ?')) return;
    fetch('/api/rendez-vous/' + rdvId + '/pieces/' + puId, { method: 'DELETE', headers: { 'Authorization': 'Bearer ' + (localStorage.getItem('token') || '') } })
        .then(function(r) { if (!r.ok) throw new Error(); return r.json(); })
        .then(function() { showToast('Pièce retirée', 'info'); window.MecanicienModule._loadExistingPieces(rdvId); })
        .catch(function() { showToast('Erreur', 'error'); });
}
