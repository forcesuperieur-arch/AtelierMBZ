window.DashboardModule = window.DashboardModule || {
    loadDashboard: function() {
        var today = new Date().toISOString().split('T')[0];
        Promise.all([
            apiGet('/api/rendez-vous?date=' + today).then(function(r) { return r.json(); }).catch(function() { return []; }),
            apiGet('/api/ponts/status').then(function(r) { return r.json(); }).catch(function() { return []; })
        ]).then(function(results) {
            var rdvs = results[0];
            var pontsStatus = results[1];
            APP.rdvs = rdvs;
            if (pontsStatus.length) APP.ponts = pontsStatus;
            window.DashboardModule.renderDashboardAlerts(rdvs);
            window.DashboardModule.renderDashboardStats(rdvs, pontsStatus);
            window.DashboardModule.renderDashboardPonts(pontsStatus, rdvs);
            window.DashboardModule.renderDashboardRdv(rdvs);
        });
    },

    renderDashboardAlerts: function(rdvs) {
        var container = document.getElementById('dashboard-alerts');
        if (!container) return;
        var now = new Date();
        var nowMin = now.getHours() * 60 + now.getMinutes();
        var todayStr = now.toISOString().split('T')[0];
        var alerts = [];
        rdvs.forEach(function(rdv) {
            if (rdv.date_rdv !== todayStr) return;
            var hm = formatTime(rdv.heure_rdv || '');
            if (!hm) return;
            var parts = hm.split(':');
            var rdvMin = parseInt(parts[0]) * 60 + parseInt(parts[1]);
            var c = rdv.client || {};
            var clientName = ((c.prenom || '').charAt(0) + '. ' + (c.nom || '')).trim();
            if ((rdv.statut === 'confirme' || rdv.statut === 'reserve' || rdv.statut === 'en_attente') && nowMin > rdvMin + 15) {
                alerts.push({type: 'danger', text: hm + ' — ' + clientName + ' non presente (retard ' + (nowMin - rdvMin) + ' min)'});
            }
            if (rdv.statut === 'en_cours') {
                var dur = rdv.temps_estime ? rdv.temps_estime / 60 : 60;
                var expectedEnd = rdvMin + dur;
                if (nowMin > expectedEnd + 10) {
                    alerts.push({type: 'warning', text: hm + ' — ' + clientName + ' depasse temps estime de ' + Math.round(nowMin - expectedEnd) + ' min'});
                }
            }
        });
        if (!alerts.length) {
            container.innerHTML = '';
            return;
        }
        var html = '<div class="suivi-alert-strip" style="margin-bottom:14px">';
        alerts.forEach(function(a) {
            html += '<span class="chip ' + a.type + '">' + (a.type === 'danger' ? '⚠ ' : '⏰ ') + escapeHtml(a.text) + '</span>';
        });
        html += '</div>';
        container.innerHTML = html;
    },

    renderDashboardStats: function(rdvs, ponts) {
        var container = document.getElementById('dashboard-stats');
        if (!container) return;
        var nbRdv = rdvs.length;
        var enCours = rdvs.filter(function(r) { return r.statut === 'en_cours'; }).length;
        var termines = rdvs.filter(function(r) { return r.statut === 'termine'; }).length;
        var restitues = rdvs.filter(function(r) { return r.statut === 'restitue' || r.statut === 'facture' || r.statut === 'paye'; }).length;
        var enAttente = rdvs.filter(function(r) { return r.statut === 'en_attente' || r.statut === 'reserve'; }).length;
        var pontsActifs = ponts.filter(function(p) { return p.actif; }).length;
        var pontsOccupes = ponts.filter(function(p) { return p.status === 'occupe'; }).length;
        var tauxOcc = pontsActifs > 0 ? Math.round((pontsOccupes / pontsActifs) * 100) : 0;
        var orOuverts = rdvs.filter(function(r) { return r.statut !== 'termine' && r.statut !== 'restitue' && r.statut !== 'facture' && r.statut !== 'paye' && r.statut !== 'annule' && r.statut !== 'non_presente'; }).length;

        container.innerHTML =
            '<div class="stat-card"><div class="stat-icon" style="float:right;font-size:24px;opacity:.4">&#127949;</div><div class="stat-label">RDV AUJOURD\'HUI</div><div class="stat-value">' + nbRdv + '</div><div class="stat-delta" style="color:var(--green)">' + termines + ' termines | ' + enAttente + ' en attente</div><div class="stat-bar"><div class="stat-bar-fill" style="width:' + Math.min(100, nbRdv * 8) + '%;background:var(--orange)"></div></div></div>' +
            '<div class="stat-card"><div class="stat-icon" style="float:right;font-size:24px;opacity:.4">&#128295;</div><div class="stat-label">OR OUVERTS</div><div class="stat-value">' + orOuverts + '</div><div class="stat-delta" style="color:var(--amber)">' + enCours + ' en cours</div><div class="stat-bar"><div class="stat-bar-fill" style="width:' + Math.min(100, orOuverts * 12) + '%;background:var(--amber)"></div></div></div>' +
            '<div class="stat-card"><div class="stat-icon" style="float:right;font-size:24px;opacity:.4">&#9889;</div><div class="stat-label">TAUX D\'OCCUPATION</div><div class="stat-value">' + tauxOcc + '<span style="font-size:18px">%</span></div><div class="stat-delta" style="color:var(--green)">Ponts ' + pontsOccupes + '/' + pontsActifs + ' occupes</div><div class="stat-bar"><div class="stat-bar-fill" style="width:' + tauxOcc + '%;background:var(--green)"></div></div></div>' +
            '<div class="stat-card"><div class="stat-icon" style="float:right;font-size:24px;opacity:.4">&#128230;</div><div class="stat-label">RESTITUTIONS</div><div class="stat-value">' + restitues + '</div><div class="stat-delta" style="color:var(--green)">' + termines + ' prets a remettre</div><div class="stat-bar"><div class="stat-bar-fill" style="width:' + Math.min(100, (restitues + termines) * 12) + '%;background:var(--teal)"></div></div></div>';
    },

    renderDashboardPonts: function(ponts, rdvs) {
        var container = document.getElementById('dashboard-ponts');
        if (!container) return;
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
                pontContent = '<div class="pont-moto">\u{1F3CD}\uFE0F ' + veh + '</div>' +
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
    },

    renderDashboardRdv: function(rdvs) {
        var container = document.getElementById('dashboard-rdv-table');
        var countEl = document.getElementById('dashboard-rdv-count');
        if (countEl) countEl.textContent = rdvs.length + ' rendez-vous';
        if (!container) return;
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
};
