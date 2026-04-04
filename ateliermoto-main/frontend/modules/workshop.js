window.WorkshopModule = window.WorkshopModule || {
    loadPontsMecas: function() {
        loadBaseData().then(function() {
            Promise.all([
                apiGet('/api/ponts/status').then(function(r) { return r.json(); }).catch(function() { return []; }),
                apiGet('/api/rendez-vous').then(function(r) { return r.json(); }).catch(function() { return []; }),
                apiGet('/api/absences').then(function(r) { return r.json(); }).catch(function() { return []; })
            ]).then(function(results) {
                var pontsStatus = results[0];
                var rdvs = results[1];
                var absences = results[2];
                if (Array.isArray(pontsStatus) && pontsStatus.length) APP.ponts = pontsStatus;
                APP.rdvs = Array.isArray(rdvs) ? rdvs : [];
                APP._absences = Array.isArray(absences) ? absences : [];
                window.WorkshopModule.renderPontsManagerKpis(APP.ponts, APP.rdvs, APP._absences);
                window.WorkshopModule.renderPontsTab();
                window.WorkshopModule.renderMecasTab();
                window.WorkshopModule.renderTempsTab();
                renderAbsencesTable(APP._absences);
            });
        });
    },

    renderPontsTab: function() {
        var container = document.getElementById('ponts-grid');
        if (!container) return;
        var now = new Date();
        var today = now.toISOString().split('T')[0];
        var rdvsToday = (APP.rdvs || []).filter(function(r) { return (r.date_rdv || '') === today; });
        var html = '';
        APP.ponts.forEach(function(pont) {
            var pontActif = isActive(pont);
            var actif = pontActif ? 'green' : 'amber';
            var meca = pont.mecanicien_id ? APP.mecaniciens.find(function(m) { return m.id === pont.mecanicien_id; }) : null;
            var pontRdvs = rdvsToday.filter(function(r) { return r.pont_id === pont.id; });
            var enCours = pontRdvs.find(function(r) { return r.statut === 'en_cours' || r.statut === 'reception' || r.statut === 'confirme'; });
            var prochain = pontRdvs.slice().sort(function(a, b) { return (a.heure_rdv || '').localeCompare(b.heure_rdv || ''); })[0];
            var charge = pontRdvs.reduce(function(sum, r) { return sum + getRdvDurationMinutes(r); }, 0);
            var chargePct = Math.min(100, Math.round((charge / 600) * 100));
            var statusBadge = enCours ? '<span class="badge orange">Occupe</span>' : (pontActif ? '<span class="badge green">Libre</span>' : '<span class="badge amber">Inactif</span>');
            var chargeLabel = (Math.floor(charge / 60) > 0 ? (Math.floor(charge / 60) + 'h') : '') + ((charge % 60) > 0 ? ((Math.floor(charge / 60) > 0 ? ' ' : '') + (charge % 60) + 'min') : (charge > 0 ? '' : '0min'));
            html += '<div class="meca-card" style="border-color:rgba(' + (pontActif ? '34,197,94' : '245,158,11') + ',.3)"><div class="meca-header"><div class="meca-av" style="background:rgba(' + (pontActif ? '34,197,94,.15' : '245,158,11,.15') + ');color:var(--' + actif + ')">P' + pont.id + '</div><div class="meca-info"><div class="meca-name">' + (escapeHtml(pont.nom) || 'Pont ' + pont.id) + '</div><div class="meca-role">' + (escapeHtml(pont.type_pont) || 'moto') + ' | ' + (pont.capacite_kg || 0) + 'kg</div></div>' + statusBadge + '</div>' +
                (meca ? '<div style="font-size:12px;color:#888;margin-bottom:6px">Technicien assigne: <b style="color:#ccc">' + escapeHtml(meca.prenom) + ' ' + escapeHtml(meca.nom) + '</b></div>' : '<div style="font-size:12px;color:#666;margin-bottom:6px">Aucun technicien assigne</div>') +
                '<div style="font-size:12px;color:#9ca3af;margin-bottom:3px">Rendez-vous aujourd\'hui: <b style="color:#e5e7eb">' + pontRdvs.length + '</b></div>' +
                '<div style="font-size:12px;color:#9ca3af;margin-bottom:3px">Temps planifie: <b style="color:#e5e7eb">' + chargeLabel + '</b></div>' +
                (prochain ? '<div style="font-size:11px;color:#888;margin-bottom:3px">Prochain passage: ' + formatTime(prochain.heure_rdv) + ' - ' + escapeHtml(prochain.type_intervention || '-') + '</div>' : '<div style="font-size:11px;color:#666;margin-bottom:3px">Aucun rendez-vous planifie</div>') +
                '<div class="stat-bar" style="margin:8px 0 10px"><div class="stat-bar-fill" style="width:' + chargePct + '%;background:' + (chargePct > 80 ? 'var(--amber)' : 'var(--teal)') + '"></div></div>' +
                '<button class="btn btn-ghost" style="font-size:11px;padding:3px 10px;color:var(--purple)" onclick="ouvrirAttribuerPont(' + pont.id + ')">' + (meca ? 'Modifier technicien' : 'Affecter technicien') + '</button></div>';
        });
        container.innerHTML = html || '<div style="color:#666;padding:20px">Aucun pont</div>';
        document.getElementById('ponts-count').textContent = APP.ponts.length;
    },

    renderMecasTab: function() {
        var container = document.getElementById('mecas-grid');
        if (!container) return;
        var now = new Date();
        var today = now.toISOString().split('T')[0];
        var rdvsToday = (APP.rdvs || []).filter(function(r) { return (r.date_rdv || '') === today; });
        var absences = APP._absences || [];
        var html = '';
        APP.mecaniciens.forEach(function(meca) {
            var color = meca.couleur || '#3b82f6';
            var initials = getMecaInitials(meca);
            var specsRaw = meca.specialites || '';
            var specs = specsRaw;
            try {
                var arr = JSON.parse(specsRaw);
                if (Array.isArray(arr)) specs = arr.join(', ');
            } catch (e) {}
            var mecaRdvs = rdvsToday.filter(function(r) { return r.mecanicien_id === meca.id; });
            var enCours = mecaRdvs.find(function(r) { return r.statut === 'en_cours'; });
            var hasAbsence = absences.some(function(a) { return a.mecanicien_id === meca.id && a.date_debut <= today && a.date_fin >= today; });
            var totalMin = mecaRdvs.reduce(function(sum, r) { return sum + getRdvDurationMinutes(r); }, 0);
            var statusPart = hasAbsence ? '<span class="badge amber">Absent</span>' : (isActive(meca) ? (enCours ? '<span class="badge orange">En intervention</span>' : '<span class="badge green">Disponible</span>') : '<span class="badge amber">Inactif</span>');
            html += '<div class="meca-card"><div class="meca-header"><div class="meca-av" style="background:' + hexToRgba(color, 0.2) + ';color:' + color + '">' + initials + '</div><div class="meca-info"><div class="meca-name">' + escapeHtml(meca.prenom) + ' ' + escapeHtml(meca.nom) + '</div><div class="meca-role">' + specs + '</div></div>' + statusPart + '</div>' +
                (specs ? '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px">' + specs.split(',').map(function(s) { return '<span class="badge" style="background:' + hexToRgba(color, 0.12) + ';color:' + color + '">' + s.trim() + '</span>'; }).join('') + '</div>' : '') +
                '<div style="font-size:12px;color:#9ca3af">Rendez-vous aujourd\'hui: <b style="color:#e5e7eb">' + mecaRdvs.length + '</b></div>' +
                '<div style="font-size:12px;color:#9ca3af">Temps planifie: <b style="color:#e5e7eb">' + totalMin + ' min</b></div>' +
                (enCours ? '<div style="font-size:11px;color:#fcd34d;margin-top:3px">Intervention en cours: ' + formatTime(enCours.heure_rdv) + ' - ' + escapeHtml(enCours.type_intervention || '-') + '</div>' : '<div style="font-size:11px;color:#777;margin-top:3px">Aucune intervention en cours</div>') +
                '</div>';
        });
        container.innerHTML = html || '<div style="color:#666;padding:20px">Aucun mecanicien</div>';
        document.getElementById('mecas-count').textContent = APP.mecaniciens.length;
    },

    renderPontsManagerKpis: function(ponts, rdvs, absences) {
        var container = document.getElementById('ponts-manager-kpis');
        if (!container) return;
        var now = new Date();
        var today = now.toISOString().split('T')[0];
        var rdvsToday = (rdvs || []).filter(function(r) { return (r.date_rdv || '') === today; });
        var pontsActifs = (ponts || []).filter(function(p) { return isActive(p); }).length;
        var pontsOccupes = (ponts || []).filter(function(p) { return p.status === 'occupe'; }).length;
        var mecasActifs = (APP.mecaniciens || []).filter(function(m) { return isActive(m); }).length;
        var absToday = (absences || []).filter(function(a) { return a.date_debut <= today && a.date_fin >= today; }).length;
        var conflicts = window.WorkshopModule.countManagerConflicts(rdvsToday);
        var chargeTotal = rdvsToday.reduce(function(sum, r) { return sum + getRdvDurationMinutes(r); }, 0);
        container.innerHTML =
            '<div class="manager-kpi"><div class="manager-kpi-label">Occupation ponts</div><div class="manager-kpi-value">' + pontsOccupes + '/' + pontsActifs + '</div><div class="manager-kpi-sub">' + (pontsActifs ? Math.round((pontsOccupes / pontsActifs) * 100) : 0) + '% en charge</div></div>' +
            '<div class="manager-kpi"><div class="manager-kpi-label">Rendez-vous du jour</div><div class="manager-kpi-value">' + rdvsToday.length + '</div><div class="manager-kpi-sub">' + chargeTotal + ' min au total</div></div>' +
            '<div class="manager-kpi"><div class="manager-kpi-label">Techniciens actifs</div><div class="manager-kpi-value">' + mecasActifs + '</div><div class="manager-kpi-sub">' + absToday + ' absent(s) aujourd\'hui</div></div>' +
            '<div class="manager-kpi"><div class="manager-kpi-label">Conflits a traiter</div><div class="manager-kpi-value" style="color:' + (conflicts > 0 ? '#fca5a5' : '#86efac') + '">' + conflicts + '</div><div class="manager-kpi-sub">' + (conflicts > 0 ? 'Attention requise' : 'Aucun conflit') + '</div></div>';
    },

    countManagerConflicts: function(rdvs) {
        var list = Array.isArray(rdvs) ? rdvs : [];
        var count = 0;
        for (var i = 0; i < list.length; i++) {
            for (var j = i + 1; j < list.length; j++) {
                var a = list[i];
                var b = list[j];
                var samePont = !!(a.pont_id && b.pont_id && a.pont_id === b.pont_id);
                var sameMeca = !!(a.mecanicien_id && b.mecanicien_id && a.mecanicien_id === b.mecanicien_id);
                if (!samePont && !sameMeca) continue;
                var aStart = timeToMinutes(formatTime(a.heure_rdv || ''));
                var bStart = timeToMinutes(formatTime(b.heure_rdv || ''));
                if (aStart < 0 || bStart < 0) continue;
                var aEnd = aStart + getRdvDurationMinutes(a);
                var bEnd = bStart + getRdvDurationMinutes(b);
                if (aStart < bEnd && bStart < aEnd) count++;
            }
        }
        return count;
    },

    renderTempsTab: function() {
        var container = document.getElementById('temps-table');
        if (!container) return;

        function formatMinutes(mins) {
            var total = parseInt(mins || 0, 10);
            var h = Math.floor(total / 60);
            var m = total % 60;
            if (!total) return '-';
            return (h > 0 ? h + 'h' : '') + (m > 0 ? m + 'min' : (h > 0 ? '00' : ''));
        }

        var html = '<thead><tr><th>Prestation</th><th>Type</th><th>Categorie moto</th><th>Temps</th><th>Prix TTC</th></tr></thead><tbody>';
        var prestations = (APP.prestationsConfig || []).filter(function(p) {
            return isActive(p);
        });

        prestations.forEach(function(p) {
            var grille = p.grille || {};
            var categories = Object.keys(grille);
            var typeTarif = p.is_forfait === 1 ? 'Forfait' : (p.type_tarif || '-');

            if (categories.length > 0) {
                categories.forEach(function(cat, idx) {
                    var g = grille[cat] || {};
                    html += '<tr>' +
                        '<td>' + (idx === 0 ? '<b>' + escapeHtml(p.nom) + '</b>' : '') + '</td>' +
                        '<td>' + (idx === 0 ? escapeHtml(typeTarif) : '') + '</td>' +
                        '<td>' + escapeHtml(cat) + '</td>' +
                        '<td>' + formatMinutes(g.temps_minutes || p.temps_estime_minutes) + '</td>' +
                        '<td>' + Number(g.prix_ttc || p.prix_base_ttc || 0).toFixed(2) + ' €</td>' +
                        '</tr>';
                });
            } else {
                html += '<tr>' +
                    '<td><b>' + escapeHtml(p.nom) + '</b></td>' +
                    '<td>' + escapeHtml(typeTarif) + '</td>' +
                    '<td>Toutes</td>' +
                    '<td>' + formatMinutes(p.temps_estime_minutes) + '</td>' +
                    '<td>' + Number(p.prix_base_ttc || 0).toFixed(2) + ' €</td>' +
                    '</tr>';
            }
        });

        if (!prestations.length) {
            html += '<tr><td colspan="5" style="text-align:center;color:#666;padding:16px">Aucune prestation configurée</td></tr>';
        }
        html += '</tbody>';
        container.innerHTML = html;
    },

    ouvrirDetailRdv: function(rdvId) {
        apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
            var c = rdv.client || {};
            var v = rdv.vehicule || {};
            var meca = APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
            var pont = APP.ponts.find(function(p) { return p.id === rdv.pont_id; });
            var html = '';

            html += '<div style="margin-bottom:16px"><div style="font-size:13px;font-weight:700;color:var(--orange);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Client</div>' +
                '<div style="background:#16161a;border-radius:8px;padding:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">' +
                '<div><span style="color:#666">Nom:</span> <span style="color:#eee">' + (escapeHtml(c.nom) || '-') + '</span></div>' +
                '<div><span style="color:#666">Prenom:</span> <span style="color:#eee">' + (escapeHtml(c.prenom) || '-') + '</span></div>' +
                '<div><span style="color:#666">Tel:</span> <span style="color:#eee">' + (escapeHtml(c.telephone) || '-') + '</span></div>' +
                '<div><span style="color:#666">Email:</span> <span style="color:#eee">' + (escapeHtml(c.email) || '-') + '</span></div>' +
                '</div>' +
                '<button class="btn btn-ghost" style="margin-top:6px;font-size:11px;color:var(--teal)" onclick="closeModal();ouvrirModalEditClient(' + c.id + ')">Modifier client</button>' +
                '</div>';

            html += '<div style="margin-bottom:16px"><div style="font-size:13px;font-weight:700;color:var(--orange);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Vehicule</div>' +
                '<div style="background:#16161a;border-radius:8px;padding:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">' +
                '<div><span style="color:#666">Plaque:</span> <span style="color:#eee">' + (escapeHtml(v.plaque) || '-') + '</span></div>' +
                '<div><span style="color:#666">Marque:</span> <span style="color:#eee">' + (escapeHtml(v.marque) || '-') + '</span></div>' +
                '<div><span style="color:#666">Modele:</span> <span style="color:#eee">' + (escapeHtml(v.modele) || '-') + '</span></div>' +
                '<div><span style="color:#666">Annee:</span> <span style="color:#eee">' + (escapeHtml(v.annee) || '-') + '</span></div>' +
                '<div><span style="color:#666">Type:</span> <span style="color:#eee">' + (escapeHtml(v.type_moto) || '-') + '</span></div>' +
                '<div><span style="color:#666">Cylindree:</span> <span style="color:#eee">' + (escapeHtml(v.cylindree) || '-') + '</span></div>' +
                '</div>' +
                '<button class="btn btn-ghost" style="margin-top:6px;font-size:11px;color:var(--teal)" onclick="closeModal();ouvrirModalEditVehicule(' + v.id + ')">Modifier vehicule</button>' +
                '</div>';

            html += '<div style="margin-bottom:16px"><div style="font-size:13px;font-weight:700;color:var(--orange);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Rendez-vous</div>' +
                '<div style="background:#16161a;border-radius:8px;padding:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px">' +
                '<div><span style="color:#666">Date:</span> <span style="color:#eee">' + (rdv.date_rdv || '-') + '</span></div>' +
                '<div><span style="color:#666">Heure:</span> <span style="color:#eee">' + formatTime(rdv.heure_rdv) + '</span></div>' +
                '<div><span style="color:#666">Type:</span> <span style="color:#eee">' + (escapeHtml(rdv.type_intervention) || '-') + '</span></div>' +
                '<div><span style="color:#666">Statut:</span> ' + statusBadge(rdv.statut) + '</div>' +
                '<div><span style="color:#666">Pont:</span> <span style="color:#eee">' + (pont ? pont.nom : 'Non assigne') + '</span></div>' +
                '<div><span style="color:#666">Meca:</span> <span style="color:#eee">' + (meca ? meca.prenom + ' ' + escapeHtml(meca.nom) : 'Non assigne') + '</span></div>' +
                '</div></div>';

            html += '<div style="margin-bottom:16px">' +
                '<div class="form-group"><label class="form-label" style="color:#ccc">Commentaire</label>' +
                '<textarea id="edit-rdv-comment" class="form-input" rows="2" placeholder="Notes...">' + (escapeHtml(rdv.commentaire) || '') + '</textarea></div></div>';

            html += '<div style="display:flex;gap:8px">' +
                '<button class="btn btn-primary" style="flex:1" onclick="sauverCommentaireRdv(' + rdv.id + ')">Enregistrer</button>' +
                '<button class="btn btn-ghost" style="flex:1" onclick="closeModal();ouvrirAssignation(' + rdv.id + ')">Reassigner</button>' +
                '</div>';

            showModal('RDV #' + rdv.id + ' - ' + formatTime(rdv.heure_rdv) + ' ' + (rdv.date_rdv || ''), html, '550px');
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    sauverCommentaireRdv: function(rdvId) {
        var comment = document.getElementById('edit-rdv-comment').value;
        apiPut('/api/rendez-vous/' + rdvId, { commentaire: comment }).then(function() {
            closeModal();
            showNotificationToast('RDV mis a jour');
            refreshCurrentSection();
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    ouvrirAttribuerPont: function(pontId) {
        var pont = APP.ponts.find(function(p) { return p.id === pontId; });
        var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Mecanicien attribue</label><select id="attr-pont-meca" class="form-select">';
        html += '<option value="">-- Aucun --</option>';
        APP.mecaniciens.forEach(function(m) {
            if (!isActive(m)) return;
            var sel = (pont && pont.mecanicien_id === m.id) ? ' selected' : '';
            html += '<option value="' + m.id + '"' + sel + '>' + escapeHtml(m.prenom) + ' ' + escapeHtml(m.nom) + '</option>';
        });
        html += '</select></div>';
        html += '<button class="btn btn-primary" style="width:100%;margin-top:12px" onclick="sauverAttribuerPont(' + pontId + ')">Enregistrer</button>';
        showModal('Attribuer ' + (pont ? pont.nom : 'Pont') + ' a un mecanicien', html, '400px');
    },

    sauverAttribuerPont: function(pontId) {
        var mecaId = document.getElementById('attr-pont-meca').value;
        var pont = APP.ponts.find(function(p) { return p.id === pontId; });
        if (!pont) return;
        apiPut('/api/ponts/' + pontId, {
            nom: pont.nom,
            type_pont: pont.type_pont || 'moto',
            capacite_kg: pont.capacite_kg || 500,
            is_active: pont.actif ? 1 : 0,
            mecanicien_id: mecaId ? parseInt(mecaId, 10) : null
        }).then(function() {
            closeModal();
            showNotificationToast('Pont attribue');
            loadBaseData().then(function() { window.WorkshopModule.loadPontsMecas(); });
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    }
};
