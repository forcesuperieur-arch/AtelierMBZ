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
    ouvrirCheckup: function(rdvId) {
        apiGet('/api/rendez-vous/' + rdvId + '/rapport-technicien').then(function(r) { return r.json(); }).then(function(rapport) {
            window.MecanicienModule.renderCheckupModal(rdvId, rapport);
        }).catch(function() {
            window.MecanicienModule.renderCheckupModal(rdvId, null);
        });
    },

    renderCheckupModal: function(rdvId, rapport) {
        var points = (rapport && rapport.points_controle) ? rapport.points_controle : {};
        var alertes = (rapport && rapport.alertes) ? rapport.alertes : '';
        var recommandations = (rapport && rapport.recommandations) ? rapport.recommandations : '';
        var travaux = (rapport && rapport.travaux_realises) ? rapport.travaux_realises : '';

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

        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Alertes / Problemes detectes</label>' +
            '<textarea id="checkup-alertes" class="form-input" rows="2" placeholder="Problemes constates...">' + alertes + '</textarea></div>';
        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Recommandations</label>' +
            '<textarea id="checkup-recommandations" class="form-input" rows="2" placeholder="Recommandations client...">' + recommandations + '</textarea></div>';
        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Travaux effectues</label>' +
            '<textarea id="checkup-travaux" class="form-input" rows="2" placeholder="Detail des travaux...">' + travaux + '</textarea></div>';
        html += '<div style="display:flex;gap:8px;margin-top:16px">' +
            '<button class="btn btn-primary" style="flex:1" onclick="sauverCheckup(' + rdvId + ',\'en_cours\')">Sauvegarder (en cours)</button>' +
            '<button class="btn btn-primary" style="flex:1;background:var(--green)" onclick="sauverCheckup(' + rdvId + ',\'termine\')">Terminer le rapport</button>' +
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

        apiPost('/api/rendez-vous/' + rdvId + '/rapport-technicien', data).then(function(r) { return r.json(); }).then(function() {
            closeModal();
            window._checkupPoints = {};
            alert('Rapport sauvegarde avec succes');
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
            if (!meca) return;
            APP._currentMeca = meca;
            APP._mecaCheckupData = null;
            window.MecanicienModule.renderEspaceMeca(meca, rdvs);
        }).catch(function(e) { console.error('Erreur espace meca:', e); });
    },

    renderEspaceMeca: function(meca, allRdvs) {
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

        var html = '<div class="meca-active-panel" id="meca-active-panel">';
        html += '<div class="meca-chrono">' +
            '<div id="meca-live-clock" class="meca-chrono-time">00:00:00</div>' +
            '<div id="meca-live-eta" class="meca-chrono-eta">Chrono en attente...</div>' +
            '<div class="meca-chrono-bar"><div id="meca-progress-bar" class="meca-chrono-fill" style="width:0%"></div></div></div>';
        html += '<div class="meca-info-block">' +
            '<div class="meca-info-vehicle">🏍️ ' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div>' +
            '<div class="meca-info-client">👤 ' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + (pont ? ' • ' + escapeHtml(pont.nom) : '') + '</div>' +
            '<div class="meca-info-type">🔧 ' + (escapeHtml(rdv.type_intervention) || '') + '</div>' +
            (rdv.notes ? '<div class="meca-info-notes">📝 ' + escapeHtml(rdv.notes) + '</div>' : '') + '</div>';
        html += '<div class="meca-checkup-block">' +
            '<div class="meca-checkup-header" onclick="toggleMecaCheckup()">' +
            '<span>☑️ Checkup (' + checked + '/' + window.CHECKUP_POINTS.length + ')</span>' +
            '<span id="meca-checkup-arrow" class="meca-checkup-arrow">▼</span></div>' +
            '<div id="meca-checkup-body" class="meca-checkup-body" style="display:none">';

        window.CHECKUP_POINTS.forEach(function(pt) {
            var val = points[pt.key] || 'non_verifie';
            html += '<div class="meca-check-item">' +
                '<span class="meca-check-label">' + pt.label + '</span>' +
                '<div class="meca-check-btns">' +
                '<button class="meca-check-btn' + (val === 'ok' ? ' ok' : '') + '" onclick="setMecaCheck(\'' + pt.key + '\',\'ok\',this)">OK</button>' +
                '<button class="meca-check-btn' + (val === 'nok' ? ' nok' : '') + '" onclick="setMecaCheck(\'' + pt.key + '\',\'nok\',this)">NOK</button></div></div>';
        });

        html += '<div style="margin-top:10px"><textarea id="meca-notes" class="meca-notes-input" rows="2" placeholder="Notes, alertes...">' +
            escapeHtml((rapport && rapport.alertes) || '') + '</textarea></div></div></div>';
        html += '<div class="meca-actions">' +
            '<button class="meca-big-btn warning" onclick="ouvrirDemandeTravauxSupp(' + rdv.id + ')">⚠️ Signaler probleme</button>' +
            '<button class="meca-big-btn ghost" onclick="telechargerOR(' + rdv.id + ')">📄 Voir OR</button>' +
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

        var html = '<div class="meca-rdv-card ' + type + '">';
        html += '<div class="meca-card-time">' + formatTime(rdv.heure_rdv) + '</div>';
        html += '<div class="meca-card-body">' +
            '<div class="meca-card-vehicle">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div>' +
            '<div class="meca-card-client">' + (escapeHtml(c.prenom) || '').charAt(0) + '. ' + (escapeHtml(c.nom) || '') + (pont ? ' • ' + escapeHtml(pont.nom) : '') + '</div>' +
            '<div class="meca-card-type">' + (escapeHtml(rdv.type_intervention) || '') + ' • ' + durStr + '</div></div>';

        if (type === 'todo') {
            html += '<button class="meca-big-btn start" onclick="event.stopPropagation();demarrerTravail(' + rdv.id + ')">▶ DEMARRER</button>';
        } else {
            html += '<div class="meca-card-actions">' +
                '<button class="meca-sm-btn" onclick="telechargerOR(' + rdv.id + ')">OR</button>' +
                '<button class="meca-sm-btn" onclick="ouvrirCheckup(' + rdv.id + ')">Rapport</button></div>';
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
    }
};
