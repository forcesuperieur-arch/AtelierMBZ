window.SuiviModule = window.SuiviModule || {
    loadSuiviLive: function() {
        var today = new Date().toISOString().split('T')[0];
        apiGet('/api/rendez-vous?date=' + today).then(function(r) {
            return r.json();
        }).then(function(rdvs) {
            window.SuiviModule.renderSuiviLive(rdvs || []);
        }).catch(function(e) {
            console.error('Erreur suivi:', e);
        });
    },

    getRdvProgressInfo: function(rdv, now) {
        var currentTime = now || new Date();
        var estimated = getRdvDurationMinutes(rdv);
        var elapsedMin = 0;
        if (rdv && rdv.heure_debut_travail) {
            var start = parseUTCDate(rdv.heure_debut_travail);
            if (start && !isNaN(start.getTime())) {
                elapsedMin = Math.max(0, Math.floor((currentTime - start) / 60000));
            }
        }
        var progress = estimated > 0 ? Math.round((elapsedMin / estimated) * 100) : 0;
        if (rdv && (rdv.statut === 'termine' || rdv.statut === 'facture' || rdv.statut === 'paye')) {
            progress = 100;
        }
        var overrun = !!rdv && rdv.statut === 'en_cours' && elapsedMin > estimated;
        return {
            estimated: estimated,
            elapsedMin: elapsedMin,
            progress: Math.max(0, Math.min(130, progress)),
            overrun: overrun,
            remaining: Math.max(0, estimated - elapsedMin)
        };
    },

    getRdvDelayInfo: function(rdv, now) {
        var currentTime = now || new Date();
        var s = rdv ? rdv.statut : '';
        var startMin = timeToMinutes(formatTime((rdv && rdv.heure_rdv) || ''));
        var nowMin = currentTime.getHours() * 60 + currentTime.getMinutes();
        var started = !!(rdv && rdv.heure_debut_travail) || s === 'en_cours' || s === 'termine' || s === 'facture' || s === 'paye';
        if (startMin >= 0 && !started) {
            if (nowMin > startMin + 10) return { level: 'delay', minutes: nowMin - startMin };
            if (nowMin >= startMin - 10 && nowMin <= startMin + 10) return { level: 'soon', minutes: Math.abs(startMin - nowMin) };
        }
        return null;
    },

    renderSuiviLive: function(rdvs) {
        var container = document.getElementById('suivi-grid');
        var alertStrip = document.getElementById('suivi-alert-strip');
        var byMeca = {};
        var now = new Date();
        var delayCount = 0;
        var soonCount = 0;
        var enCoursCount = 0;
        var delayItems = [];

        (rdvs || []).forEach(function(rdv) {
            var mid = rdv.mecanicien_id || 0;
            if (!byMeca[mid]) byMeca[mid] = [];
            byMeca[mid].push(rdv);

            var d = window.SuiviModule.getRdvDelayInfo(rdv, now);
            if (d && d.level === 'delay') {
                delayCount += 1;
                var c = rdv.client || {};
                var v = rdv.vehicule || {};
                delayItems.push(formatTime(rdv.heure_rdv) + ' - ' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + ' (' + (escapeHtml(c.nom) || 'Client') + ') +' + d.minutes + 'm');
            } else if (d && d.level === 'soon') {
                soonCount += 1;
            }
            if (rdv.statut === 'en_cours') enCoursCount += 1;
        });

        if (alertStrip) {
            var stripHtml = '<span class="chip">En cours: ' + enCoursCount + '</span>' +
                '<span class="chip orange">Demarrages imminents: ' + soonCount + '</span>' +
                '<span class="chip red">Retards: ' + delayCount + '</span>';
            if (delayItems.length) {
                stripHtml += '<div class="suivi-alert-list"><strong>Alertes retard RDV:</strong><br>' + delayItems.slice(0, 4).join('<br>') + (delayItems.length > 4 ? '<br>... +' + (delayItems.length - 4) + ' autre(s)' : '') + '</div>';
            }
            alertStrip.innerHTML = stripHtml;
        }

        if (!container) return;
        var html = '';

        if (byMeca[0] && byMeca[0].length > 0) {
            html += '<div class="card"><div class="meca-header" style="margin-bottom:10px"><div class="meca-av" style="background:rgba(245,158,11,.15);color:var(--amber);width:36px;height:36px;font-size:13px">?</div><div class="meca-info"><div class="meca-name">Non assigne</div><div class="meca-role">' + byMeca[0].length + ' RDV(s)</div></div><span class="badge amber">A assigner</span></div><div class="timeline">';
            byMeca[0].sort(function(a, b) { return (a.heure_rdv || '').localeCompare(b.heure_rdv || ''); });
            byMeca[0].forEach(function(rdv) {
                var dotCls = rdv.statut === 'termine' ? 'done' : (rdv.statut === 'en_cours' ? 'active' : '');
                html += '<div class="tl-item"><div class="tl-dot ' + dotCls + '"></div><div class="tl-content"><div style="display:flex;justify-content:space-between;align-items:center"><div class="tl-title">' + (escapeHtml(rdv.type_intervention) || '') + '</div>' + actionButtons(rdv, true) + '</div><div class="tl-meta">' + formatTime(rdv.heure_rdv) + ' - ' + statusBadge(rdv.statut) + '</div></div></div>';
            });
            html += '</div></div>';
        }

        Object.keys(byMeca).forEach(function(mecaId) {
            if (parseInt(mecaId, 10) === 0) return;
            var mecaRdvs = byMeca[mecaId];
            var meca = (APP.mecaniciens || []).find(function(m) { return m.id === parseInt(mecaId, 10); });
            if (!meca) return;
            var color = meca.couleur || '#3b82f6';
            var initials = getMecaInitials(meca);
            var currentRdv = mecaRdvs.find(function(r) { return r.statut === 'en_cours'; });
            var pont = currentRdv ? (APP.ponts || []).find(function(p) { return p.id === currentRdv.pont_id; }) : null;
            var currentVeh = currentRdv && currentRdv.vehicule ? ((currentRdv.vehicule.marque || '') + ' ' + (currentRdv.vehicule.modele || '')) : '';
            var roleText = (pont ? pont.nom : '') + (currentVeh ? ' • ' + currentVeh : '');

            html += '<div class="card"><div class="meca-header" style="margin-bottom:10px"><div class="meca-av" style="background:' + hexToRgba(color, 0.2) + ';color:' + color + ';width:36px;height:36px;font-size:13px">' + initials + '</div><div class="meca-info"><div class="meca-name">' + (escapeHtml(meca.prenom) || '').charAt(0) + '. ' + (escapeHtml(meca.nom) || '') + '</div><div class="meca-role">' + roleText + '</div></div>' + (currentRdv ? '<span class="badge orange">En cours</span>' : '<span class="badge green">Disponible</span>') + '</div>';

            if (currentRdv) {
                var info = window.SuiviModule.getRdvProgressInfo(currentRdv, now);
                var progressColor = info.overrun ? '#ef4444' : color;
                var progressLabel = info.overrun ? ('Retard +' + (info.elapsedMin - info.estimated) + ' min') : (info.remaining + ' min restantes');
                html += '<div style="margin-bottom:10px"><div style="display:flex;justify-content:space-between;font-size:11px;color:#777;margin-bottom:4px"><span>' + (escapeHtml(currentRdv.type_intervention) || '') + '</span><span>' + Math.min(100, info.progress) + '%</span></div><div class="pont-progress"><div class="pont-progress-fill" style="width:' + Math.min(100, info.progress) + '%;background:' + progressColor + '"></div></div><div style="font-size:11px;color:' + (info.overrun ? '#fca5a5' : '#9ca3af') + ';margin-top:4px">Temps reel: ' + info.elapsedMin + 'm / estime ' + info.estimated + 'm - ' + progressLabel + '</div></div>';
            }

            html += '<div class="timeline">';
            mecaRdvs.sort(function(a, b) { return (a.heure_rdv || '').localeCompare(b.heure_rdv || ''); });
            mecaRdvs.forEach(function(rdv) {
                var dotCls = rdv.statut === 'termine' ? 'done' : (rdv.statut === 'en_cours' ? 'active' : '');
                var v = rdv.vehicule || {};
                var vName = ((escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '')).trim();
                var delay = window.SuiviModule.getRdvDelayInfo(rdv, now);
                var delayBadge = '';
                if (delay && delay.level === 'delay') delayBadge = ' <span class="badge red">Retard +' + delay.minutes + 'm</span>';
                else if (delay && delay.level === 'soon') delayBadge = ' <span class="badge amber">Demarrage imminent</span>';
                html += '<div class="tl-item"><div class="tl-dot ' + dotCls + '"></div><div class="tl-content"><div style="display:flex;justify-content:space-between;align-items:center"><div class="tl-title">' + (escapeHtml(rdv.type_intervention) || '') + delayBadge + '</div>' + actionButtons(rdv, true) + '</div><div class="tl-meta">' + formatTime(rdv.heure_rdv) + ' • ' + vName + ' • ' + statusBadge(rdv.statut) + '</div></div></div>';
            });
            html += '</div></div>';
        });

        container.innerHTML = html || '<div style="color:#666;padding:20px">Aucune intervention en cours</div>';
    }
};
