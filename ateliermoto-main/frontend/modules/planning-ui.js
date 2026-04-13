window.PlanningUiModule = window.PlanningUiModule || {
    formatPlanningModalDate: function(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('fr-FR', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
    },

    getPlanningStatusMeta: function(status) {
        var key = String(status || '').trim().toLowerCase();
        var map = {
            reserve: { icon: '🕒', label: 'Reserve', tone: 'neutral' },
            en_attente: { icon: '🕒', label: 'En attente', tone: 'neutral' },
            confirme: { icon: '📌', label: 'Confirme', tone: 'info' },
            reception: { icon: '📥', label: 'Receptionne', tone: 'warning' },
            en_cours: { icon: '🛠️', label: 'En cours', tone: 'teal' },
            termine: { icon: '✅', label: 'Termine', tone: 'success' },
            restitue: { icon: '📤', label: 'Restitue', tone: 'success' },
            facture: { icon: '📤', label: 'Cloture', tone: 'success' },
            paye: { icon: '📤', label: 'Cloture', tone: 'success' },
            annule: { icon: '❌', label: 'Annule', tone: 'danger' },
            non_presente: { icon: '🚫', label: 'Non presente', tone: 'danger' }
        };
        return map[key] || { icon: '•', label: key || 'RDV', tone: 'neutral' };
    },

    formatDurationLabel: function(durationMinutes) {
        var total = parseInt(durationMinutes || 0, 10);
        if (!total || total <= 0) total = 60;
        var h = Math.floor(total / 60);
        var m = total % 60;
        if (h && m) return h + 'h' + (m < 10 ? '0' + m : m);
        if (h) return h + 'h';
        return total + ' min';
    },

    getPlanningRdvWarnings: function(rdv) {
        var warnings = [];
        if (!rdv) return warnings;
        var statusTips = {
            reserve: 'RDV a confirmer par la reception.',
            en_attente: 'Un retour client ou atelier est encore attendu.',
            confirme: 'Reception du vehicule a faire a l\'arrivee.',
            reception: 'Le vehicule est receptionne, l\'intervention peut etre demarree.',
            en_cours: 'Intervention en cours : surveiller l\'avancement atelier.',
            termine: 'Travaux termines : vehicule pret pour restitution.',
            restitue: 'Dossier atelier cloture apres remise du vehicule au client.'
        };
        if (statusTips[rdv.statut]) {
            warnings.push({ tone: 'info', title: 'Prochaine etape', body: statusTips[rdv.statut] });
        }
        if (!rdv.mecanicien_id || !rdv.pont_id) {
            warnings.push({ tone: 'warning', title: 'Affectation incomplete', body: 'Pense a verifier le pont et le technicien avant le passage atelier.' });
        }
        if (window.PlanningModule && window.PlanningModule.isMecanicienAbsentOn(rdv.mecanicien_id, rdv.date_rdv)) {
            warnings.push({ tone: 'warning', title: 'Technicien absent', body: 'Le technicien assigne est marque absent sur cette journee.' });
        }
        var overlaps = (APP.planningRdvs || []).filter(function(other) {
            if (!other || other.id === rdv.id || (other.date_rdv || '') !== (rdv.date_rdv || '')) return false;
            if (other.statut === 'annule' || other.statut === 'non_presente') return false;
            var samePont = !!(rdv.pont_id && other.pont_id && rdv.pont_id === other.pont_id);
            var sameMeca = !!(rdv.mecanicien_id && other.mecanicien_id && rdv.mecanicien_id === other.mecanicien_id);
            if (!samePont && !sameMeca) return false;
            var aStart = window.PlanningUtils.timeToMinutes(formatTime(rdv.heure_rdv || ''));
            var bStart = window.PlanningUtils.timeToMinutes(formatTime(other.heure_rdv || ''));
            if (aStart < 0 || bStart < 0) return false;
            var aEnd = aStart + window.PlanningUtils.getRdvDurationMinutes(rdv);
            var bEnd = bStart + window.PlanningUtils.getRdvDurationMinutes(other);
            return aStart < bEnd && bStart < aEnd;
        });
        if (overlaps.length) {
            warnings.push({ tone: 'danger', title: 'Conflit planning', body: overlaps.length + ' chevauchement(s) detecte(s) sur la meme ressource.' });
        }
        return warnings;
    },

    renderPlanningWorkflowHistory: function(rdv) {
        var workflowHistory = Array.isArray(rdv.workflow_history) ? rdv.workflow_history.slice().reverse().slice(0, 4) : [];
        if (!workflowHistory.length) {
            return '<div class="planning-rdv-muted">Aucun historique workflow enregistre pour le moment.</div>';
        }
        return '<div class="planning-rdv-history">' + workflowHistory.map(function(entry) {
            var when = entry && entry.at ? String(entry.at) : '';
            try {
                when = when ? new Date(when).toLocaleString('fr-FR') : '-';
            } catch (e) {}
            var transition = escapeHtml((entry && entry.from_status) || 'creation') + ' → ' + escapeHtml((entry && entry.to_status) || (entry && entry.action) || 'mise a jour');
            var meta = escapeHtml((entry && entry.by) || 'system') + ((entry && entry.role) ? ' • ' + escapeHtml(entry.role) : '');
            var note = entry && entry.note ? '<div class="planning-rdv-history-note">' + escapeHtml(entry.note) + '</div>' : '';
            return '<div class="planning-rdv-history-item"><div class="planning-rdv-history-title">' + transition + '</div><div class="planning-rdv-history-meta">' + meta + ' • ' + escapeHtml(when) + '</div>' + note + '</div>';
        }).join('') + '</div>';
    },

    renderPlanningRdvModal: function(rdv) {
        var resources = window.PlanningModule ? window.PlanningModule.getPlanningResources() : { ponts: [], mecaniciens: [] };
        var c = rdv.client || {};
        var v = rdv.vehicule || {};
        var meca = rdv.mecanicien || (resources.mecaniciens || []).find(function(m) { return m.id === rdv.mecanicien_id; });
        var pont = rdv.pont || (resources.ponts || []).find(function(p) { return p.id === rdv.pont_id; });
        var durationMinutes = window.PlanningUtils.getRdvDurationMinutes(rdv);
        var startLabel = formatTime(rdv.heure_rdv || '') || '--:--';
        var endMin = (rdv.date_rdv && startLabel && window.PlanningModule)
            ? window.PlanningModule.computePlanningEffectiveEndMinutes(rdv.date_rdv, startLabel, durationMinutes)
            : -1;
        var endLabel = endMin >= 0 ? window.PlanningUtils.minutesToTimeLabel(endMin) : '--:--';
        var warnings = window.PlanningUiModule.getPlanningRdvWarnings(rdv);
        var warningHtml = warnings.length
            ? '<div class="planning-rdv-alert-list">' + warnings.map(function(item) {
                return '<div class="planning-rdv-alert ' + escapeAttr(item.tone || 'info') + '"><strong>' + escapeHtml(item.title || 'Info') + '</strong>' + escapeHtml(item.body || '') + '</div>';
            }).join('') + '</div>'
            : '<div class="planning-rdv-alert info"><strong>RAS</strong>Aucun point de vigilance detecte pour ce rendez-vous.</div>';

        var extraActions = '<div class="planning-rdv-extra-actions">';
        if (c.telephone) {
            extraActions += '<a class="btn btn-ghost" href="tel:' + escapeAttr(c.telephone) + '" style="text-decoration:none">Appeler client</a>';
        }
        if (rdv.client && rdv.client.id) {
            extraActions += '<button class="btn btn-ghost" onclick="closeModal();focusClientFromAnywhere(' + rdv.client.id + ')">Voir la fiche client</button>';
        }
        extraActions += '</div>';

        var commentHtml = rdv.commentaire
            ? '<div class="planning-rdv-alert info"><strong>Notes atelier</strong>' + escapeHtml(rdv.commentaire) + '</div>'
            : '<div class="planning-rdv-muted">Aucune note atelier sur ce RDV.</div>';

        return '<div class="planning-rdv-modal">'
            + '<div class="planning-rdv-hero">'
                + '<div class="planning-rdv-hero-main">'
                    + '<div class="planning-rdv-eyebrow">Rendez-vous atelier</div>'
                    + '<div class="planning-rdv-title">' + escapeHtml(rdv.type_intervention || ('RDV #' + rdv.id)) + '</div>'
                    + '<div class="planning-rdv-subline">'
                        + '<span>' + escapeHtml(window.PlanningUiModule.formatPlanningModalDate(rdv.date_rdv)) + '</span>'
                        + '<span>' + escapeHtml(startLabel) + ' → ' + escapeHtml(endLabel) + '</span>'
                        + '<span>Durée ' + escapeHtml(window.PlanningUiModule.formatDurationLabel(durationMinutes)) + '</span>'
                    + '</div>'
                + '</div>'
                + '<div>' + statusBadge(rdv.statut) + '</div>'
            + '</div>'
            + '<div class="planning-rdv-actions">' + actionButtons(rdv, false) + extraActions + '</div>'
            + '<div class="planning-rdv-grid">'
                + '<div class="planning-rdv-card">'
                    + '<div class="planning-rdv-card-title">Client</div>'
                    + '<div style="font-size:18px;font-weight:700;color:#f8fafc">' + escapeHtml((c.prenom || '') + ' ' + (c.nom || '')) + '</div>'
                    + '<div class="planning-rdv-muted" style="margin-top:4px">' + escapeHtml(c.telephone || 'Telephone non renseigne') + '</div>'
                    + '<div class="planning-rdv-muted">' + escapeHtml(c.email || 'Email non renseigne') + '</div>'
                + '</div>'
                + '<div class="planning-rdv-card">'
                    + '<div class="planning-rdv-card-title">Vehicule</div>'
                    + '<div style="font-size:18px;font-weight:700;color:#f8fafc">' + escapeHtml((v.marque || '') + ' ' + (v.modele || '')) + '</div>'
                    + '<div class="planning-rdv-muted" style="margin-top:4px">Plaque : ' + escapeHtml(v.plaque || '-') + '</div>'
                    + '<div class="planning-rdv-muted">Kilometrage : ' + escapeHtml(String(rdv.kilometrage || '-')) + '</div>'
                + '</div>'
                + '<div class="planning-rdv-card">'
                    + '<div class="planning-rdv-card-title">Organisation atelier</div>'
                    + '<div class="planning-rdv-kv">'
                        + '<div class="planning-rdv-kpi"><div class="planning-rdv-kpi-label">Technicien</div><div class="planning-rdv-kpi-value">' + escapeHtml(meca ? ((meca.prenom || '') + ' ' + (meca.nom || '')) : 'Non assigne') + '</div></div>'
                        + '<div class="planning-rdv-kpi"><div class="planning-rdv-kpi-label">Pont</div><div class="planning-rdv-kpi-value">' + escapeHtml(pont ? (pont.nom || ('Pont #' + pont.id)) : 'Non assigne') + '</div></div>'
                        + '<div class="planning-rdv-kpi"><div class="planning-rdv-kpi-label">Debut</div><div class="planning-rdv-kpi-value">' + escapeHtml(startLabel) + '</div></div>'
                        + '<div class="planning-rdv-kpi"><div class="planning-rdv-kpi-label">Fin estimee</div><div class="planning-rdv-kpi-value">' + escapeHtml(endLabel) + '</div></div>'
                    + '</div>'
                + '</div>'
                + '<div class="planning-rdv-card">'
                    + '<div class="planning-rdv-card-title">Points de vigilance</div>'
                    + warningHtml
                + '</div>'
            + '</div>'
            + '<div class="planning-rdv-grid">'
                + '<div class="planning-rdv-card"><div class="planning-rdv-card-title">Historique workflow</div>' + window.PlanningUiModule.renderPlanningWorkflowHistory(rdv) + '</div>'
                + '<div class="planning-rdv-card"><div class="planning-rdv-card-title">Commentaire</div>' + commentHtml + '</div>'
            + '</div>'
        + '</div>';
    }
};
