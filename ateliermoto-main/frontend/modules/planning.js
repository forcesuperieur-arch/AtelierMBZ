var _qcSearchTimer = null;

window.PlanningModule = window.PlanningModule || {
    loadPlanning: function() {
        window.PlanningModule.populatePlanningAtelierSelect();
        window.PlanningModule.renderPlanningLegend();
        var planningSlug = window.PlanningModule.getPlanningAtelierSlug();
        var loadSeq = ++APP._planningLoadSeq;
        var today = new Date();
        today.setDate(today.getDate() + (APP.planningWeekOffset * 7));
        var monday = new Date(today);
        monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));

        var headerEl = document.getElementById('planning-header');
        var joursNoms = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        var todayStr = _rdvDateToStr(new Date());
        var headerHtml = '<div class="planning-day"></div>';

        for (var d = 0; d < 7; d++) {
            var jour = new Date(monday);
            jour.setDate(jour.getDate() + d);
            var jourStr = _rdvDateToStr(jour);
            var isToday = jourStr === todayStr;
            headerHtml += '<div class="planning-day' + (isToday ? ' today' : '') + '">' + '<div class="planning-day-name">' + joursNoms[d] + '</div>' + '<div class="planning-day-date">' + jour.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' }).replace('.', '') + '</div>' + (isToday ? '<span class="planning-day-today">Aujourd\'hui</span>' : '') + '</div>';
        }
        if (headerEl) headerEl.innerHTML = headerHtml;

        window.PlanningModule.renderMecaFilters();

        var dateDebut = _rdvDateToStr(monday);
        var dimanche = new Date(monday);
        dimanche.setDate(dimanche.getDate() + 6);
        var dateFin = _rdvDateToStr(dimanche);

        var weekLabel = document.getElementById('planning-week-label');
        if (weekLabel) weekLabel.textContent = 'Semaine ' + window.PlanningModule.getWeekNumber(monday);
        var weekRangeEl = document.getElementById('planning-week-range');
        if (weekRangeEl) weekRangeEl.textContent = window.PlanningModule.formatPlanningRange(monday);
        var planningSub = document.getElementById('planning-sub');
        if (planningSub) {
            planningSub.textContent = window.PlanningModule.getPlanningDisplayName() + ' - semaine ' + window.PlanningModule.getWeekNumber(monday) + ' - charge, affectations et tension atelier.';
        }

        var planningUrl = '/api/planning/semaine?date_debut=' + dateDebut + '&date_fin=' + dateFin;
        if (hasPermission('rdv.select_atelier') && planningSlug) {
            planningUrl += '&atelier_slug=' + encodeURIComponent(planningSlug);
        }
        APP._horairesByDay = {};
        APP._horairesLoaded = false;
        Promise.all([
            apiGet(planningUrl).then(function(r) { return r.json(); }),
            apiGet('/api/config/horaires' + (planningSlug ? '?atelier_slug=' + encodeURIComponent(planningSlug) : '')).then(function(r) { return r.json(); }).catch(function() { return []; })
        ]).then(function(results) {
            if (loadSeq !== APP._planningLoadSeq) return;
            var data = results[0] || {};
            var horaires = results[1] || [];
            APP._horairesByDay = {};
            APP._horairesLoaded = Array.isArray(horaires) && horaires.length > 0;
            horaires.forEach(function(h) { APP._horairesByDay[h.jour_semaine] = h; });
            APP._planningPonts = Array.isArray(data.ponts) ? data.ponts : [];
            APP._planningMecaniciens = Array.isArray(data.mecaniciens) ? data.mecaniciens : [];
            APP._planningAbsences = Array.isArray(data.absences) ? data.absences : [];
            window.PlanningModule.renderMecaFilters();

            var rdvList = [];
            if (data && data.jours) {
                Object.keys(data.jours).forEach(function(dateKey) {
                    var dayRdvs = data.jours[dateKey];
                    if (Array.isArray(dayRdvs)) {
                        dayRdvs.forEach(function(rdv) {
                            if (!rdv.date_rdv) rdv.date_rdv = dateKey;
                            rdvList.push(rdv);
                        });
                    }
                });
            } else if (Array.isArray(data)) {
                rdvList = data;
            }
            APP.planningRdvs = rdvList;
            APP.planningMonday = monday;
            window.PlanningModule.renderPlanningGrid(rdvList, monday);
        }).catch(function() {
            if (loadSeq !== APP._planningLoadSeq) return;
            window.PlanningModule.renderPlanningGrid([], monday);
        });

        if (APP._planningNowTimer) clearInterval(APP._planningNowTimer);
        APP._planningNowTimer = setInterval(function() {
            if (APP.currentSection === 'planning' && APP.planningMonday) {
                window.PlanningUtils.renderPlanningNowLine(document.getElementById('planning-grid'), APP.planningMonday);
            }
        }, 60000);
    },

    isPlanningSlotOpen: function(dateStr, hour) {
        if (!APP._horairesLoaded) return true;
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return true;
        var jsDay = d.getDay();
        var jour = jsDay === 0 ? 6 : jsDay - 1;
        var h = APP._horairesByDay[jour];
        if (!h || !h.is_ouvert) return false;
        if (!h.heure_ouverture || !h.heure_fermeture) return false;
        var mins = window.PlanningUtils.timeToMinutes(hour);
        if (mins < window.PlanningUtils.timeToMinutes(adminFmtTime(h.heure_ouverture)) || mins >= window.PlanningUtils.timeToMinutes(adminFmtTime(h.heure_fermeture))) return false;
        if (h.pause_debut && h.pause_fin) {
            var p1 = window.PlanningUtils.timeToMinutes(adminFmtTime(h.pause_debut));
            var p2 = window.PlanningUtils.timeToMinutes(adminFmtTime(h.pause_fin));
            if (mins >= p1 && mins < p2) return false;
        }
        return true;
    },

    isPlanningSlotValidForDuration: function(dateStr, hour, durationMinutes) {
        if (!window.PlanningModule.isPlanningSlotOpen(dateStr, hour)) return false;
        if (!APP._horairesLoaded) return true;
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return true;
        var jsDay = d.getDay();
        var jour = jsDay === 0 ? 6 : jsDay - 1;
        var h = APP._horairesByDay[jour];
        if (!h || !h.is_ouvert || !h.heure_fermeture) return true;
        var closeAt = window.PlanningUtils.timeToMinutes(adminFmtTime(h.heure_fermeture));
        var effectiveEnd = window.PlanningModule.computePlanningEffectiveEndMinutes(dateStr, hour, durationMinutes);
        return effectiveEnd >= 0 && effectiveEnd <= closeAt;
    },

    getPlanningHoraireForDate: function(dateStr) {
        if (!APP._horairesLoaded) return null;
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return null;
        var jsDay = d.getDay();
        var jour = jsDay === 0 ? 6 : jsDay - 1;
        return APP._horairesByDay[jour] || null;
    },

    getPlanningResources: function() {
        var pontsSource = (Array.isArray(APP._planningPonts) && APP._planningPonts.length) ? APP._planningPonts : (APP.ponts || []);
        var mecasSource = (Array.isArray(APP._planningMecaniciens) && APP._planningMecaniciens.length) ? APP._planningMecaniciens : (APP.mecaniciens || []);
        var absences = Array.isArray(APP._planningAbsences) ? APP._planningAbsences : [];
        var ponts = pontsSource.filter(function(p) { return p && (p.actif === true || p.is_active === 1 || p.is_active === true); });
        var mecaniciens = mecasSource.filter(function(m) {
            return m && (typeof isActive === 'function' ? isActive(m) : (m.actif === true || m.is_active === 1 || m.is_active === true));
        });
        return { ponts: ponts, mecaniciens: mecaniciens, absences: absences };
    },

    isMecanicienAbsentOn: function(mecanicienId, dateStr) {
        if (!mecanicienId || !dateStr) return false;
        var absences = window.PlanningModule.getPlanningResources().absences;
        return absences.some(function(a) {
            return a && a.mecanicien_id === mecanicienId && a.date_debut <= dateStr && a.date_fin >= dateStr;
        });
    },

    computePlanningEffectiveEndMinutes: function(dateStr, hour, durationMinutes) {
        var start = window.PlanningUtils.timeToMinutes(hour);
        var duration = parseInt(durationMinutes || 0, 10);
        if (start < 0) return -1;
        if (!duration || duration <= 0) duration = 60;
        var end = start + duration;
        var h = window.PlanningModule.getPlanningHoraireForDate(dateStr);
        if (h && h.pause_debut && h.pause_fin) {
            var pauseStart = window.PlanningUtils.timeToMinutes(adminFmtTime(h.pause_debut));
            var pauseEnd = window.PlanningUtils.timeToMinutes(adminFmtTime(h.pause_fin));
            if (pauseStart >= 0 && pauseEnd > pauseStart && start < pauseStart && end > pauseStart) {
                end += (pauseEnd - pauseStart);
            }
        }
        return end;
    },

    getSlotResourceAvailability: function(dateStr, hour, durationMinutes, options) {
        var startMin = window.PlanningUtils.timeToMinutes(hour);
        var duration = parseInt(durationMinutes || 0, 10);
        if (startMin < 0) return { hasCapacity: false, reason: 'Heure invalide', freePonts: [], freeMecaniciens: [] };
        if (!duration || duration <= 0) duration = 60;
        if (!window.PlanningModule.isPlanningSlotValidForDuration(dateStr, hour, duration)) {
            return { hasCapacity: false, reason: 'Duree RDV depasse l\'heure de fermeture', freePonts: [], freeMecaniciens: [] };
        }

        var resources = window.PlanningModule.getPlanningResources();
        var ponts = resources.ponts || [];
        var mecaniciens = resources.mecaniciens || [];
        var rdvs = options && Array.isArray(options.rdvs) ? options.rdvs : (APP.planningRdvs || []);
        var ignoreRdvId = options && options.ignoreRdvId ? options.ignoreRdvId : null;
        var requiredPontId = options && options.requiredPontId ? options.requiredPontId : null;
        var requiredMecaId = options && options.requiredMecanicienId ? options.requiredMecanicienId : null;
        var targetSegments = window.PlanningModule.splitRdvSegments(dateStr, startMin, duration);
        var busyPontIds = {};
        var busyMecaIds = {};
        var absentMecaIds = {};

        function overlapsAny(segmentsA, segmentsB) {
            for (var i = 0; i < segmentsA.length; i++) {
                for (var j = 0; j < segmentsB.length; j++) {
                    if (segmentsA[i].start < segmentsB[j].end && segmentsA[i].end > segmentsB[j].start) return true;
                }
            }
            return false;
        }

        mecaniciens.forEach(function(meca) {
            if (window.PlanningModule.isMecanicienAbsentOn(meca.id, dateStr)) absentMecaIds[meca.id] = true;
        });

        (rdvs || []).forEach(function(rdv) {
            if (!rdv || rdv.id === ignoreRdvId || (rdv.date_rdv || '') !== dateStr) return;
            if (rdv.statut === 'annule' || rdv.statut === 'non_presente') return;
            var otherStart = window.PlanningUtils.timeToMinutes(formatTime(rdv.heure_rdv || ''));
            if (otherStart < 0) return;
            var otherSegments = window.PlanningModule.splitRdvSegments(dateStr, otherStart, window.PlanningUtils.getRdvDurationMinutes(rdv));
            if (!overlapsAny(targetSegments, otherSegments)) return;
            if (rdv.pont_id) busyPontIds[rdv.pont_id] = true;
            if (rdv.mecanicien_id) busyMecaIds[rdv.mecanicien_id] = true;
        });

        var freeMecaniciens = mecaniciens.filter(function(meca) {
            return !busyMecaIds[meca.id] && !absentMecaIds[meca.id];
        });
        var freePonts = ponts.filter(function(pont) {
            if (busyPontIds[pont.id]) return false;
            if (pont.mecanicien_id && absentMecaIds[pont.mecanicien_id]) return false;
            return true;
        });

        var defaultMecaId = freeMecaniciens.length ? freeMecaniciens[0].id : null;
        var defaultPontId = null;
        if (defaultMecaId) {
            var linkedPont = freePonts.find(function(p) { return p.mecanicien_id === defaultMecaId; });
            if (linkedPont) defaultPontId = linkedPont.id;
        }
        if (!defaultPontId && freePonts.length) defaultPontId = freePonts[0].id;

        var absentCount = Object.keys(absentMecaIds).length;
        var hasCapacity = false;
        var reason = '';
        if (requiredMecaId || requiredPontId) {
            var mecaOk = !requiredMecaId || freeMecaniciens.some(function(m) { return m.id === requiredMecaId; });
            var pontOk = !requiredPontId || freePonts.some(function(p) { return p.id === requiredPontId; });
            hasCapacity = mecaOk && pontOk;
            if (!hasCapacity) {
                if (requiredMecaId && absentMecaIds[requiredMecaId]) reason = 'Technicien absent sur cette journee';
                else if (requiredMecaId && busyMecaIds[requiredMecaId]) reason = 'Technicien deja occupe sur ce creneau';
                else if (requiredPontId && busyPontIds[requiredPontId]) reason = 'Pont deja occupe sur ce creneau';
                else reason = 'Ressource deja occupee ou indisponible sur ce creneau';
            }
        } else {
            hasCapacity = freeMecaniciens.length > 0 || freePonts.length > 0 || (!mecaniciens.length && !ponts.length);
            if (!hasCapacity) {
                if (mecaniciens.length > 0 && absentCount === mecaniciens.length) reason = 'Tous les techniciens sont absents';
                else if (mecaniciens.length > 0 && freeMecaniciens.length === 0) reason = 'Tous les techniciens sont occupes';
                else if (ponts.length > 0 && freePonts.length === 0) reason = 'Tous les ponts sont occupes';
                else reason = 'Aucune ressource libre sur ce creneau';
            }
        }

        var summaryParts = [];
        if (freeMecaniciens.length || mecaniciens.length) summaryParts.push(freeMecaniciens.length + ' technicien(s) libre(s)');
        if (absentCount) summaryParts.push(absentCount + ' absent(s)');
        if (freePonts.length || ponts.length) summaryParts.push(freePonts.length + ' pont(s) libre(s)');

        return {
            hasCapacity: hasCapacity,
            reason: reason,
            summary: summaryParts.join(' • '),
            freePonts: freePonts,
            freeMecaniciens: freeMecaniciens,
            defaultPontId: defaultPontId,
            defaultMecanicienId: defaultMecaId
        };
    },

    formatAlternativeSlotLabel: function(dateStr, hour) {
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return dateStr + ' ' + hour;
        var joursNoms = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        return joursNoms[d.getDay()] + ' ' + String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + ' • ' + hour;
    },

    findAlternativeSlots: function(dateStr, durationMinutes, options) {
        var limit = Math.max(1, parseInt((options && options.limit) || 4, 10));
        var ignoreRdvId = options && options.ignoreRdvId ? options.ignoreRdvId : null;
        var requiredPontId = options && options.requiredPontId ? options.requiredPontId : null;
        var requiredMecaId = options && options.requiredMecanicienId ? options.requiredMecanicienId : null;
        var skipDate = options && options.skipDate ? options.skipDate : null;
        var skipHour = options && options.skipHour ? options.skipHour : null;
        var startDate = new Date((dateStr || _rdvDateToStr(new Date())) + 'T00:00:00');
        if (isNaN(startDate.getTime())) startDate = new Date();
        var weekBase = APP.planningMonday ? new Date(_rdvDateToStr(APP.planningMonday) + 'T00:00:00') : new Date(startDate);
        var hours = window.PlanningUtils.buildPlanningSlots(window.PlanningUtils.getPlanningBounds().start, window.PlanningUtils.getPlanningBounds().end, APP._planningSlotMinutes);
        var alternatives = [];

        for (var d = 0; d < 7; d++) {
            var day = new Date(weekBase);
            day.setDate(weekBase.getDate() + d);
            if (day < startDate) continue;
            var dayStr = _rdvDateToStr(day);
            for (var i = 0; i < hours.length; i++) {
                var hour = hours[i];
                if (skipDate === dayStr && skipHour === hour) continue;
                if (dayStr === dateStr && skipHour && window.PlanningUtils.timeToMinutes(hour) < window.PlanningUtils.timeToMinutes(skipHour)) continue;
                if (!window.PlanningModule.isPlanningSlotOpen(dayStr, hour)) continue;
                var availability = window.PlanningModule.getSlotResourceAvailability(dayStr, hour, durationMinutes, {
                    ignoreRdvId: ignoreRdvId,
                    requiredPontId: requiredPontId,
                    requiredMecanicienId: requiredMecaId
                });
                if (!availability.hasCapacity) continue;
                alternatives.push({
                    date: dayStr,
                    hour: hour,
                    label: window.PlanningModule.formatAlternativeSlotLabel(dayStr, hour),
                    mecanicien_id: availability.defaultMecanicienId || null,
                    pont_id: availability.defaultPontId || null
                });
                if (alternatives.length >= limit) return alternatives;
            }
        }

        return alternatives;
    },

    splitRdvSegments: function(dateStr, startMin, durationMin) {
        var h = window.PlanningModule.getPlanningHoraireForDate(dateStr);
        var totalEnd = startMin + durationMin;
        if (!h || !h.pause_debut || !h.pause_fin) {
            return [{ start: startMin, end: totalEnd, continuation: false }];
        }
        var pauseStart = window.PlanningUtils.timeToMinutes(adminFmtTime(h.pause_debut));
        var pauseEnd = window.PlanningUtils.timeToMinutes(adminFmtTime(h.pause_fin));
        if (pauseStart < 0 || pauseEnd < 0 || pauseEnd <= pauseStart) {
            return [{ start: startMin, end: totalEnd, continuation: false }];
        }
        if (startMin < pauseStart && totalEnd > pauseStart) {
            var firstEnd = pauseStart;
            var remaining = totalEnd - pauseStart;
            return [
                { start: startMin, end: firstEnd, continuation: false },
                { start: pauseEnd, end: pauseEnd + remaining, continuation: true }
            ];
        }
        return [{ start: startMin, end: totalEnd, continuation: false }];
    },

    buildPlanningBusyCells: function(rdvs, monday) {
        var busy = {};
        if (!monday) return busy;
        var bounds = window.PlanningUtils.getPlanningBounds();
        var hours = window.PlanningUtils.buildPlanningSlots(bounds.start, bounds.end, APP._planningSlotMinutes);
        for (var d = 0; d < 7; d++) {
            var jour = new Date(monday);
            jour.setDate(jour.getDate() + d);
            var jourStr = _rdvDateToStr(jour);
            for (var i = 0; i < hours.length; i++) {
                var hour = hours[i];
                var info = window.PlanningModule.getSlotResourceAvailability(jourStr, hour, 60, { rdvs: rdvs });
                if (!info.hasCapacity) busy[jourStr + '|' + hour] = true;
            }
        }
        return busy;
    },

    isPlanningCellBusy: function(dateStr, hour) {
        return !!APP._planningBusyCells[dateStr + '|' + hour];
    },

    getPlanningAtelierSlug: function() {
        if (APP.planningSelectedAtelierSlug) return APP.planningSelectedAtelierSlug;
        if (APP.currentUser && APP.currentUser.atelier_slug) return APP.currentUser.atelier_slug;
        if (APP.currentUser && Array.isArray(APP.currentUser.ateliers)) {
            var own = APP.currentUser.ateliers.find(function(a) { return a.atelier_id === APP.currentUser.atelier_id; });
            if (own && own.slug) return own.slug;
        }
        return 'default';
    },

    getPlanningDisplayName: function() {
        var select = document.getElementById('planning-atelier-select');
        if (select && select.options && select.selectedIndex >= 0 && select.options[select.selectedIndex]) {
            return String(select.options[select.selectedIndex].text || '').trim() || 'Atelier';
        }
        if (APP.currentUser && APP.currentUser.atelier_nom) return APP.currentUser.atelier_nom;
        return 'Atelier';
    },

    formatPlanningRange: function(monday) {
        if (!monday) return '-';
        var sunday = new Date(monday);
        sunday.setDate(sunday.getDate() + 6);
        function fmt(date) {
            return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' }).replace('.', '');
        }
        return fmt(monday) + ' - ' + fmt(sunday);
    },

    populatePlanningAtelierSelect: function() {
        var select = document.getElementById('planning-atelier-select');
        if (!select) return;
        var canSelectAtelier = hasPermission('rdv.select_atelier');
        var selectWrap = select.parentElement;
        if (selectWrap) selectWrap.style.display = canSelectAtelier ? 'flex' : 'none';

        function render(items) {
            var ateliers = Array.isArray(items) ? items.filter(function(a) { return a && a.slug; }) : [];
            if (!ateliers.length) {
                var fallback = window.PlanningModule.getPlanningAtelierSlug() || 'default';
                select.innerHTML = '<option value="' + escapeAttr(fallback) + '">' + escapeHtml(fallback) + '</option>';
                select.value = fallback;
                select.disabled = true;
                return;
            }
            var html = '';
            ateliers.forEach(function(a) {
                var aid = a.id || a.atelier_id || '';
                var label = a.nom || a.slug || ('Atelier #' + aid);
                html += '<option value="' + escapeAttr(a.slug) + '">' + escapeHtml(label) + '</option>';
            });
            select.innerHTML = html;
            if (!APP.planningSelectedAtelierSlug) {
                var own = ateliers.find(function(a) {
                    var aid = a.id || a.atelier_id;
                    return aid === (APP.currentUser ? APP.currentUser.atelier_id : null);
                });
                APP.planningSelectedAtelierSlug = (own && own.slug) ? own.slug : window.PlanningModule.getPlanningAtelierSlug();
            }
            var exists = ateliers.some(function(a) { return a.slug === APP.planningSelectedAtelierSlug; });
            if (!exists) APP.planningSelectedAtelierSlug = ateliers[0].slug;
            if (!canSelectAtelier) {
                var ownAtelier = ateliers.find(function(a) {
                    var aid = a.id || a.atelier_id;
                    return aid === (APP.currentUser ? APP.currentUser.atelier_id : null);
                });
                APP.planningSelectedAtelierSlug = (ownAtelier && ownAtelier.slug) ? ownAtelier.slug : APP.planningSelectedAtelierSlug;
            }
            select.value = APP.planningSelectedAtelierSlug;
            select.disabled = !canSelectAtelier;
        }

        if (canSelectAtelier) {
            apiGet('/api/ateliers/public').then(function(r) { return r.json(); }).then(render).catch(function() {
                render(APP.currentUser && APP.currentUser.ateliers ? APP.currentUser.ateliers : []);
            });
        } else {
            render(APP.currentUser && APP.currentUser.ateliers ? APP.currentUser.ateliers : []);
        }
    },

    onPlanningAtelierChange: function(slug) {
        var next = (slug || '').trim().toLowerCase();
        if (!next || next === APP.planningSelectedAtelierSlug) return;
        APP.planningSelectedAtelierSlug = next;
        APP.planningMecaFilters = [];
        window.PlanningModule.loadPlanning();
    },

    renderMecaFilters: function() {
        var container = document.getElementById('planning-meca-filters');
        if (!container) return;
        var html = '';
        var mecaList = window.PlanningModule.getPlanningResources().mecaniciens;
        if (!mecaList.length) {
            container.innerHTML = '<div class="planning-filter-empty">Aucun mecanicien actif sur l\'atelier selectionne.</div>';
            return;
        }
        mecaList.forEach(function(meca) {
            var color = meca.couleur || '#3b82f6';
            var isFiltered = APP.planningMecaFilters.length > 0 && APP.planningMecaFilters.indexOf(meca.id) === -1;
            html += '<button type="button" class="planning-meca-chip' + (isFiltered ? ' is-muted' : '') + '" data-meca-id="' + meca.id + '" onclick="toggleMecaFilter(' + meca.id + ')" style="--meca-color:' + escapeAttr(color) + ';--meca-soft:' + escapeAttr(hexToRgba(color, 0.14)) + ';--meca-border:' + escapeAttr(hexToRgba(color, 0.3)) + '"><span class="planning-meca-dot"></span><span>' + escapeHtml(meca.prenom.charAt(0)) + '. ' + escapeHtml(meca.nom) + '</span></button>';
        });
        container.innerHTML = html;
    },

    renderPlanningLegend: function() {
        var el = document.getElementById('planning-legend');
        if (!el) return;
        el.innerHTML = '<span class="planning-status-legend-item"><span class="ico">📥</span>Reception</span>'
            + '<span class="planning-status-legend-item"><span class="ico">🛠️</span>En cours</span>'
            + '<span class="planning-status-legend-item"><span class="ico">✅</span>Termine</span>'
            + '<span class="planning-status-legend-item"><span class="ico">📤</span>Restitue</span>'
            + '<span class="planning-status-legend-item"><span class="ico">⏰</span>Retard</span>'
            + '<span class="planning-status-legend-item" style="border-color:rgba(239,68,68,.35)"><span class="ico" style="color:#fca5a5">⚠</span><span style="color:#fca5a5">Conflit</span></span>';
    },

    renderPlanningHeader: function(monday, byDay, conflictPairsById) {
        var headerEl = document.getElementById('planning-header');
        if (!headerEl || !monday) return;
        var joursNoms = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
        var todayStr = _rdvDateToStr(new Date());
        var headerHtml = '<div class="planning-day"></div>';

        for (var d = 0; d < 7; d++) {
            var jour = new Date(monday);
            jour.setDate(jour.getDate() + d);
            var jourStr = _rdvDateToStr(jour);
            var isToday = jourStr === todayStr;
            var dayRdvs = Array.isArray(byDay[jourStr]) ? byDay[jourStr] : [];
            var dayAbsences = (APP._planningAbsences || []).filter(function(abs) {
                return abs && abs.date_debut <= jourStr && abs.date_fin >= jourStr;
            });
            var dayConflicts = dayRdvs.filter(function(item) { return !!conflictPairsById[item.id]; }).length;
            var metaHtml = '<div class="planning-day-meta">';
            metaHtml += '<span class="planning-day-badge neutral">' + dayRdvs.length + ' RDV</span>';
            if (dayAbsences.length) metaHtml += '<span class="planning-day-badge danger">' + dayAbsences.length + ' absent' + (dayAbsences.length > 1 ? 's' : '') + '</span>';
            if (dayConflicts) metaHtml += '<span class="planning-day-badge warning">' + dayConflicts + ' conflit' + (dayConflicts > 1 ? 's' : '') + '</span>';
            metaHtml += '</div>';

            var caption = '';
            if (dayAbsences.length) {
                var labels = dayAbsences.slice(0, 2).map(function(abs) {
                    return ((abs.mecanicien_prenom || '') + ' ' + (abs.mecanicien_nom || '')).trim();
                }).join(', ');
                if (dayAbsences.length > 2) labels += ' +' + (dayAbsences.length - 2);
                caption = '<div class="planning-day-caption">' + escapeHtml(labels) + '</div>';
            }

            headerHtml += '<div class="planning-day' + (isToday ? ' today' : '') + '">' + '<div class="planning-day-name">' + joursNoms[d] + '</div>'
                + '<div class="planning-day-date">' + jour.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' }).replace('.', '') + '</div>'
                + (isToday ? '<span class="planning-day-today">Aujourd\'hui</span>' : '')
                + metaHtml + caption + '</div>';
        }

        headerEl.innerHTML = headerHtml;
    },

    countLatePlanningRdvs: function(rdvs) {
        var today = new Date();
        var todayStr = _rdvDateToStr(today);
        var nowMin = (today.getHours() * 60) + today.getMinutes();
        return (Array.isArray(rdvs) ? rdvs : []).filter(function(rdv) {
            if (!rdv || rdv.date_rdv !== todayStr || rdv.statut === 'annule' || rdv.statut === 'non_presente') return false;
            var startMin = window.PlanningUtils.timeToMinutes(formatTime(rdv.heure_rdv || ''));
            if (startMin < 0) return false;
            if ((rdv.statut === 'confirme' || rdv.statut === 'reserve' || rdv.statut === 'en_attente') && nowMin > (startMin + 10)) return true;
            if (rdv.statut === 'reception' && nowMin > (startMin + 20)) return true;
            if (rdv.statut === 'en_cours') {
                return nowMin > (startMin + window.PlanningUtils.getRdvDurationMinutes(rdv) + 10);
            }
            return false;
        }).length;
    },

    renderPlanningInsights: function(context) {
        var filteredRdvs = context && Array.isArray(context.filteredRdvs) ? context.filteredRdvs : [];
        var byDay = context && context.byDay ? context.byDay : {};
        var monday = context ? context.monday : null;
        var conflictPairsById = context && context.conflictPairsById ? context.conflictPairsById : {};
        var absenceCount = context ? (context.absenceCount || 0) : 0;
        var total = filteredRdvs.length;
        var conflictRdvCount = Object.keys(conflictPairsById).length;
        var unassignedCount = filteredRdvs.filter(function(rdv) {
            return !rdv || !rdv.pont_id || !rdv.mecanicien_id;
        }).length;
        var lateCount = window.PlanningModule.countLatePlanningRdvs(filteredRdvs);
        var range = window.PlanningModule.formatPlanningRange(monday);
        var atelierName = window.PlanningModule.getPlanningDisplayName();
        var resources = window.PlanningModule.getPlanningResources();
        var visibleMecas = APP.planningMecaFilters.length ? APP.planningMecaFilters.length : (resources.mecaniciens || []).length;
        var busiestDayKey = '';
        var busiestDayCount = 0;

        Object.keys(byDay).forEach(function(dayKey) {
            var count = Array.isArray(byDay[dayKey]) ? byDay[dayKey].length : 0;
            if (count > busiestDayCount) {
                busiestDayKey = dayKey;
                busiestDayCount = count;
            }
        });

        function setText(id, value) {
            var el = document.getElementById(id);
            if (el) el.textContent = value;
        }

        setText('planning-kpi-total', String(total));
        setText('planning-kpi-total-meta', total ? (Math.round((total / 7) * 10) / 10) + ' RDV / jour en moyenne' : 'Aucun RDV visible sur la semaine');
        setText('planning-kpi-conflicts', String(conflictRdvCount));
        setText('planning-kpi-conflicts-meta', conflictRdvCount ? conflictRdvCount + ' RDV a replanifier ou arbitrer' : 'Aucun conflit detecte');
        setText('planning-kpi-unassigned', String(unassignedCount));
        setText('planning-kpi-unassigned-meta', unassignedCount ? unassignedCount + ' RDV sans pont ou mecanicien' : 'Tous les RDV ont une ressource');
        setText('planning-kpi-late', String(lateCount));
        setText('planning-kpi-late-meta', lateCount ? lateCount + ' RDV en retard aujourd\'hui' : 'Flux ponctuel sur la journee');

        setText('planning-week-range', range);
        setText('planning-focus-title', atelierName + ' - ' + range);

        var focusCopy = total
            ? total + ' RDV visibles cette semaine avec ' + visibleMecas + ' mecanicien(s) dans le champ.'
            : 'Aucun rendez-vous visible sur cette semaine. Le planning peut servir de base propre pour ouvrir de nouveaux creneaux.';
        if (busiestDayKey) {
            focusCopy += ' Pic de charge le ' + new Date(busiestDayKey + 'T00:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: '2-digit', month: 'short' }).replace('.', '') + ' avec ' + busiestDayCount + ' RDV.';
        }
        setText('planning-focus-copy', focusCopy);

        var assignedCount = total - unassignedCount;
        var balance = visibleMecas + ' mecanicien(s) visibles';
        balance += total ? (' - ' + assignedCount + '/' + total + ' RDV totalement affectes') : ' - aucun RDV visible';
        if (absenceCount) balance += ' - ' + absenceCount + ' absence(s) planifiee(s)';
        setText('planning-focus-balance', balance);

        var watchlist = document.getElementById('planning-watchlist');
        if (!watchlist) return;

        var items = [];
        if (conflictRdvCount) items.push({ tone: 'danger', text: conflictRdvCount + ' RDV se chevauchent sur une meme ressource.' });
        if (unassignedCount) items.push({ tone: 'warning', text: unassignedCount + ' RDV restent sans pont ou sans mecanicien.' });
        if (absenceCount) items.push({ tone: 'warning', text: absenceCount + ' absence(s) influencent la semaine en cours.' });
        if (lateCount) items.push({ tone: 'danger', text: lateCount + ' RDV sont deja en retard aujourd\'hui.' });
        if (APP.planningMecaFilters.length) items.push({ tone: 'info', text: 'Filtre actif sur ' + APP.planningMecaFilters.length + ' mecanicien(s).' });
        if (!items.length) items.push({ tone: 'success', text: 'Semaine propre: pas de conflit, pas de retard, pas d\'affectation manquante.' });

        watchlist.innerHTML = items.map(function(item) {
            return '<div class="planning-watch-item ' + escapeAttr(item.tone) + '">' + escapeHtml(item.text) + '</div>';
        }).join('');
    },

    toggleMecaFilter: function(mecaId) {
        var idx = APP.planningMecaFilters.indexOf(mecaId);
        if (APP.planningMecaFilters.length === 0) {
            APP.planningMecaFilters = [mecaId];
        } else if (idx !== -1 && APP.planningMecaFilters.length === 1) {
            APP.planningMecaFilters = [];
        } else if (idx !== -1) {
            APP.planningMecaFilters.splice(idx, 1);
        } else {
            APP.planningMecaFilters.push(mecaId);
        }
        window.PlanningModule.renderMecaFilters();
        window.PlanningModule.renderPlanningGrid(APP.planningRdvs, APP.planningMonday);
    },

    renderPlanningGrid: function(rdvs, monday) {
        var grid = document.getElementById('planning-grid');
        if (!grid) return;
        var planningSection = document.getElementById('s-planning');
        var summaryEl = document.getElementById('planning-conflict-summary');
        if (!summaryEl && planningSection) {
            summaryEl = document.createElement('div');
            summaryEl.id = 'planning-conflict-summary';
            summaryEl.className = 'planning-conflict-summary';
            var card = planningSection.querySelector('.card');
            if (card && card.parentNode === planningSection) {
                planningSection.insertBefore(summaryEl, card);
            }
        }
        var bounds = window.PlanningUtils.getPlanningBounds();
        var hours = window.PlanningUtils.buildPlanningSlots(bounds.start, bounds.end, APP._planningSlotMinutes);
        var todayStr = _rdvDateToStr(new Date());
        var rdvList = Array.isArray(rdvs) ? rdvs : [];

        var filteredRdvs = rdvList;
        if (APP.planningMecaFilters.length > 0) {
            filteredRdvs = rdvList.filter(function(r) {
                return APP.planningMecaFilters.indexOf(r.mecanicien_id) !== -1 || !r.mecanicien_id;
            });
        }

        var byDay = {};
        filteredRdvs.forEach(function(rdv) {
            var dayKey = rdv.date_rdv || '';
            if (!dayKey) return;
            if (!byDay[dayKey]) byDay[dayKey] = [];
            byDay[dayKey].push(rdv);
        });

        APP._planningBusyCells = window.PlanningModule.buildPlanningBusyCells(rdvList, monday);
        var conflictPairsById = {};
        var conflictCellKeys = {};
        var visualOverlapGroupsById = {};
        var conflictCount = 0;
        var absenceCount = Array.isArray(APP._planningAbsences) ? APP._planningAbsences.length : 0;

        Object.keys(byDay).forEach(function(dayKey) {
            var dayList = byDay[dayKey].slice().sort(function(a, b) {
                return (formatTime(a.heure_rdv || '')).localeCompare(formatTime(b.heure_rdv || ''));
            });
            window.PlanningUtils.buildVisualOverlapGroups(dayList, visualOverlapGroupsById);
            for (var i = 0; i < dayList.length; i++) {
                for (var j = i + 1; j < dayList.length; j++) {
                    var a = dayList[i];
                    var b = dayList[j];
                    if (a.id === b.id) continue;
                    if (a.pont_id && b.pont_id && a.pont_id !== b.pont_id) continue;
                    if (a.mecanicien_id && b.mecanicien_id && a.mecanicien_id !== b.mecanicien_id) continue;
                    var aStart = window.PlanningUtils.timeToMinutes(formatTime(a.heure_rdv || ''));
                    var bStart = window.PlanningUtils.timeToMinutes(formatTime(b.heure_rdv || ''));
                    if (aStart < 0 || bStart < 0) continue;
                    var aDur = window.PlanningUtils.getRdvDurationMinutes(a);
                    var bDur = window.PlanningUtils.getRdvDurationMinutes(b);
                    var aEnd = aStart + aDur;
                    var bEnd = bStart + bDur;
                    if (aStart < bEnd && bStart < aEnd) {
                        conflictPairsById[a.id] = true;
                        conflictPairsById[b.id] = true;
                        conflictCount++;
                        window.PlanningUtils.markConflictCells(conflictCellKeys, dayKey, aStart, aEnd);
                        window.PlanningUtils.markConflictCells(conflictCellKeys, dayKey, bStart, bEnd);
                    }
                }
            }
        });

        window.PlanningModule.renderPlanningHeader(monday, byDay, conflictPairsById);

        var html = '';
        hours.forEach(function(h) {
            html += '<div class="time-label" style="height:' + APP._planningSlotPx + 'px;line-height:' + APP._planningSlotPx + 'px">' + (h.slice(3) === '00' ? h : '') + '</div>';
            for (var d = 0; d < 7; d++) {
                var jour = new Date(monday);
                jour.setDate(jour.getDate() + d);
                var jourStr = _rdvDateToStr(jour);
                var isToday = jourStr === todayStr;
                var cellKey = jourStr + '|' + h;
                var isConflictCell = !!conflictCellKeys[cellKey];
                var isClosedCell = !window.PlanningModule.isPlanningSlotOpen(jourStr, h);
                var capacityInfo = window.PlanningModule.getSlotResourceAvailability(jourStr, h, 60, { rdvs: rdvList });
                var isBusyCell = !capacityInfo.hasCapacity;
                var cellTitle = isClosedCell ? 'Atelier ferme' : (capacityInfo.reason || capacityInfo.summary || 'Creneau disponible');
                html += '<div class="planning-cell' + (isConflictCell ? ' has-conflict' : '') + (isClosedCell ? ' is-closed' : '') + (isBusyCell ? ' is-busy' : '') + '" data-date="' + jourStr + '" data-hour="' + h + '" title="' + escapeAttr(cellTitle) + '"'
                    + ' onclick="onPlanningCellClick(event,\'' + jourStr + '\',\'' + h + '\')"'
                    + ' ondragover="onCellDragOver(event)"'
                    + ' ondragleave="onCellDragLeave(event)"'
                    + ' ondrop="onCellDrop(event,\'' + jourStr + '\',\'' + h + '\')"'
                    + ' style="height:' + APP._planningSlotPx + 'px;' + (isToday ? 'background:rgba(232,72,10,.03)' : '') + '"></div>';
            }
        });
        grid.innerHTML = html;
        window.PlanningModule.renderPlanningEventLayer(grid, filteredRdvs, monday, visualOverlapGroupsById, conflictPairsById);
        window.PlanningUtils.renderPlanningNowLine(grid, monday);
        window.PlanningModule.renderPlanningInsights({
            monday: monday,
            rdvList: rdvList,
            filteredRdvs: filteredRdvs,
            byDay: byDay,
            conflictPairsById: conflictPairsById,
            conflictCount: conflictCount,
            absenceCount: absenceCount
        });
        if (summaryEl) {
            if (conflictCount > 0 || absenceCount > 0) {
                summaryEl.innerHTML = '<span class="planning-conflict-dot"></span><strong>' + conflictCount + ' conflit(s)</strong>'
                    + ' <span style="color:#94a3b8">•</span> '
                    + '<span style="color:' + (absenceCount ? '#fde68a' : '#86efac') + '">' + absenceCount + ' absence(s) sur la semaine</span>'
                    + '<span style="color:#94a3b8">•</span><span style="color:#cbd5e1">Priorite aux badges jour, aux retards et aux RDV sans ressource.</span>';
            } else {
                summaryEl.innerHTML = '<span class="planning-conflict-dot" style="background:var(--green)"></span><span style="color:#86efac">Semaine propre: aucun conflit ni absence bloquante sur la vue affichee.</span>';
            }
        }
    },

    renderPlanningEventLayer: function(grid, rdvs, monday, visualOverlapGroupsById, conflictPairsById) {
        if (!grid || !monday) return;

        var existingLayer = grid.querySelector('.planning-events-layer');
        if (existingLayer) existingLayer.remove();

        var bounds = window.PlanningUtils.getPlanningBounds();
        var startMin = window.PlanningUtils.timeToMinutes(bounds.start);
        var pxPerMin = APP._planningSlotPx / APP._planningSlotMinutes;
        var bodyStyles = window.getComputedStyle(grid);
        var cols = bodyStyles.gridTemplateColumns.split(' ').filter(Boolean);
        var timeColWidth = parseFloat(cols[0]) || 80;
        var dayColWidth = (grid.clientWidth - timeColWidth) / 7;

        var layer = document.createElement('div');
        layer.className = 'planning-events-layer';
        layer.style.cssText = 'position:absolute;inset:0;pointer-events:none;z-index:6;';

        var resources = window.PlanningModule.getPlanningResources();
        var planningMecas = resources.mecaniciens || [];
        var planningPonts = resources.ponts || [];

        (Array.isArray(rdvs) ? rdvs : []).forEach(function(rdv) {
            var dayKey = rdv.date_rdv || '';
            if (!dayKey) return;
            var dayOffset = Math.floor((new Date(dayKey) - new Date(_rdvDateToStr(monday))) / 86400000);
            if (dayOffset < 0 || dayOffset > 6) return;

            var rdvTime = formatTime(rdv.heure_rdv || '');
            if (!rdvTime) return;

            var rdvStart = window.PlanningUtils.timeToMinutes(rdvTime);
            if (rdvStart < 0) return;

            var duration = window.PlanningUtils.getRdvDurationMinutes(rdv);
            var segments = window.PlanningModule.splitRdvSegments(dayKey, rdvStart, duration);
            var meca = rdv.mecanicien || planningMecas.find(function(m) { return m.id === rdv.mecanicien_id; });
            var pont = rdv.pont || planningPonts.find(function(p) { return p.id === rdv.pont_id; });
            var color = (meca && meca.couleur) ? meca.couleur : '#3b82f6';
            var c = rdv.client || {};
            var clientName = c.prenom ? (String(c.prenom || '').charAt(0) + '. ' + String(c.nom || '')) : String(c.nom || '');
            var overlapMeta = visualOverlapGroupsById[rdv.id] || { index: 0, count: 1 };
            var overlapIndex = overlapMeta.index;
            var overlapCount = Math.max(1, overlapMeta.count || 1);
            var laneWidth = dayColWidth / overlapCount;
            var baseLeft = timeColWidth + (dayOffset * dayColWidth) + (overlapIndex * laneWidth) + 2;
            var width = Math.max(20, laneWidth - 4);
            var isConflict = !!conflictPairsById[rdv.id];
            var statusMeta = window.PlanningModule.getPlanningStatusMeta(rdv.statut);
            var now = new Date();
            var nowDateStr = _rdvDateToStr(now);
            var nowMin = (now.getHours() * 60) + now.getMinutes();
            var rdvStartForLate = window.PlanningUtils.timeToMinutes(rdvTime);
            var isLate = false;
            if (rdv.date_rdv === nowDateStr && rdvStartForLate >= 0) {
                if ((rdv.statut === 'confirme' || rdv.statut === 'reserve' || rdv.statut === 'en_attente') && nowMin > (rdvStartForLate + 10)) {
                    isLate = true;
                } else if (rdv.statut === 'reception' && nowMin > (rdvStartForLate + 20)) {
                    isLate = true;
                } else if (rdv.statut === 'en_cours') {
                    var expectedEnd = rdvStartForLate + duration;
                    if (nowMin > (expectedEnd + 10)) isLate = true;
                }
            }

            segments.forEach(function(seg) {
                var segDuration = Math.max(APP._planningSlotMinutes, seg.end - seg.start);
                var top = Math.round((seg.start - startMin) * pxPerMin);
                var height = Math.max(14, Math.ceil(segDuration * pxPerMin) - 2);
                var rdvMin = seg.start % 60;
                var timeLabel = (seg.continuation ? '↳ ' : '') + (Math.floor(seg.start / 60) < 10 ? '0' + Math.floor(seg.start / 60) : Math.floor(seg.start / 60)) + ':' + (rdvMin < 10 ? '0' + rdvMin : rdvMin);

                var block = document.createElement('div');
                var sizeClass = height >= 92 ? ' size-xl' : (height >= 68 ? ' size-l' : (height >= 34 ? ' size-m' : ' size-s'));
                block.className = 'rdv-block status-' + escapeAttr(String(rdv.statut || 'reserve').replace(/[^a-z0-9_-]/gi, '').toLowerCase()) + sizeClass + (isConflict ? ' conflict' : '') + (isLate ? ' is-late' : '');
                block.setAttribute('draggable', 'true');
                block.setAttribute('data-rdv-id', String(rdv.id));
                block.setAttribute('title', timeLabel + ' - ' + (rdv.type_intervention || '') + ' - ' + clientName);
                block.style.cssText = 'background:linear-gradient(160deg,' + hexToRgba(color, 0.96) + ',' + hexToRgba(color, 0.76) + ');' +
                    (seg.continuation ? 'opacity:.82;border:1px dashed rgba(255,255,255,.75);' : '') +
                    'height:' + height + 'px;color:white;top:' + top + 'px;left:' + baseLeft + 'px;width:' + width + 'px;right:auto;z-index:' + (6 + overlapIndex) + ';pointer-events:auto;';
                block.onclick = function(event) {
                    event.stopPropagation();
                    window.PlanningModule.onPlanningRdvClick(rdv.id);
                };
                block.ondragstart = function(event) {
                    window.PlanningModule.onRdvDragStart(event, rdv.id);
                };
                block.ondragend = function(event) {
                    window.PlanningModule.onRdvDragEnd(event);
                };

                var durationLabel = Math.round(segDuration) + ' min';
                if (segDuration >= 60) {
                    var hPart = Math.floor(segDuration / 60);
                    var mPart = segDuration % 60;
                    durationLabel = hPart + 'h' + (mPart ? (mPart < 10 ? '0' + mPart : mPart) : '');
                }
                var markerTone = isLate ? 'late' : (statusMeta.tone || 'neutral');
                var markerIcon = isLate ? '⏰' : (statusMeta.icon || '•');
                var markerLabel = isLate ? ('Retard • ' + (statusMeta.label || 'RDV')) : (statusMeta.label || '');
                var inner = '<div class="planning-status-marker ' + escapeAttr(markerTone) + '" title="' + escapeAttr(markerLabel) + '">' + escapeHtml(markerIcon) + '</div>'
                    + '<div class="planning-rdv-time">' + escapeHtml(timeLabel) + '</div>'
                    + '<div class="planning-rdv-type">' + escapeHtml((rdv.type_intervention || 'RDV').substring(0, 42)) + '</div>'
                    + '<div class="planning-rdv-client">' + escapeHtml(clientName || 'Client') + '</div>'
                    + '<div class="planning-rdv-meta"><span class="planning-rdv-foot-pill">' + escapeHtml(durationLabel) + '</span>'
                    + (pont ? '<span class="planning-rdv-foot-pill">' + escapeHtml(pont.nom || ('Pont #' + pont.id)) + '</span>' : '')
                    + '</div>';
                if (height >= 52) {
                    inner += '<div class="planning-rdv-flags">';
                    if (rdv.statut) {
                        inner += '<div class="planning-inline-badge ' + escapeAttr(statusMeta.tone || 'status') + '">' + escapeHtml((statusMeta.icon || '') + ' ' + (statusMeta.label || String(rdv.statut).replace(/_/g, ' '))) + '</div>';
                    }
                    if (window.PlanningModule.isMecanicienAbsentOn(rdv.mecanicien_id, dayKey)) {
                        inner += '<div class="planning-inline-badge warning">Absence tech</div>';
                    }
                    if (isLate) {
                        inner += '<div class="planning-inline-badge danger">Retard</div>';
                    }
                    if (isConflict) {
                        inner += '<div class="planning-inline-badge danger">Conflit ressource</div>';
                    }
                    inner += '</div>';
                }
                block.innerHTML = inner;
                layer.appendChild(block);
            });
        });

        grid.appendChild(layer);
    },

    getWeekNumber: function(d) {
        d = new Date(d);
        d.setHours(0, 0, 0, 0);
        d.setDate(d.getDate() + 3 - (d.getDay() + 6) % 7);
        var w1 = new Date(d.getFullYear(), 0, 4);
        return 1 + Math.round(((d - w1) / 86400000 - 3 + (w1.getDay() + 6) % 7) / 7);
    },

    planningPrev: function() {
        APP.planningWeekOffset--;
        window.PlanningModule.loadPlanning();
    },

    planningNext: function() {
        APP.planningWeekOffset++;
        window.PlanningModule.loadPlanning();
    },

    planningToday: function() {
        APP.planningWeekOffset = 0;
        window.PlanningModule.loadPlanning();
    },

    onRdvDragStart: function(event, rdvId) {
        APP._draggedRdvId = rdvId;
        event.dataTransfer.setData('text/plain', String(rdvId));
        event.dataTransfer.effectAllowed = 'move';
        event.target.style.opacity = '0.4';
        APP._draggedEl = event.target;
    },

    clearDragPreview: function() {
        var previewNodes = document.querySelectorAll('.planning-drag-preview');
        for (var i = 0; i < previewNodes.length; i++) {
            previewNodes[i].remove();
        }
        var cells = document.querySelectorAll('.planning-cell.drag-over');
        for (var j = 0; j < cells.length; j++) {
            cells[j].classList.remove('drag-over');
        }
    },

    renderDragPreview: function(dateStr, hour) {
        window.PlanningModule.clearDragPreview();
        var rdvId = APP._draggedRdvId;
        if (!rdvId || !APP.planningMonday) return;
        var rdv = (APP.planningRdvs || []).find(function(item) { return item && item.id === rdvId; });
        var grid = document.getElementById('planning-grid');
        if (!rdv || !grid) return;

        var startMin = window.PlanningUtils.timeToMinutes(hour);
        if (startMin < 0) return;
        var duration = window.PlanningUtils.getRdvDurationMinutes(rdv);
        var bounds = window.PlanningUtils.getPlanningBounds();
        var boundsStart = window.PlanningUtils.timeToMinutes(bounds.start);
        var pxPerMin = APP._planningSlotPx / APP._planningSlotMinutes;
        var bodyStyles = window.getComputedStyle(grid);
        var cols = bodyStyles.gridTemplateColumns.split(' ').filter(Boolean);
        var timeColWidth = parseFloat(cols[0]) || 80;
        var dayColWidth = (grid.clientWidth - timeColWidth) / 7;
        var dayOffset = Math.floor((new Date(dateStr) - new Date(_rdvDateToStr(APP.planningMonday))) / 86400000);
        if (dayOffset < 0 || dayOffset > 6) return;

        var segments = window.PlanningModule.splitRdvSegments(dateStr, startMin, duration);
        segments.forEach(function(seg) {
            var segDuration = Math.max(APP._planningSlotMinutes, seg.end - seg.start);
            var top = Math.round((seg.start - boundsStart) * pxPerMin);
            var height = Math.max(14, Math.ceil(segDuration * pxPerMin) - 2);
            var preview = document.createElement('div');
            preview.className = 'planning-drag-preview';
            preview.style.cssText = 'position:absolute;pointer-events:none;left:' + (timeColWidth + (dayOffset * dayColWidth) + 2) + 'px;top:' + top + 'px;width:' + Math.max(20, dayColWidth - 4) + 'px;height:' + height + 'px;border:2px dashed #facc15;background:rgba(250,204,21,.12);border-radius:4px;z-index:9;box-sizing:border-box;';
            grid.appendChild(preview);
        });
    },

    onRdvDragEnd: function() {
        if (APP._draggedEl) {
            APP._draggedEl.style.opacity = '';
            APP._draggedEl = null;
        }
        APP._draggedRdvId = null;
        window.PlanningModule.clearDragPreview();
    },

    onCellDragOver: function(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        event.currentTarget.classList.add('drag-over');
        var dateStr = event.currentTarget.getAttribute('data-date');
        var hour = event.currentTarget.getAttribute('data-hour');
        if (dateStr && hour) window.PlanningModule.renderDragPreview(dateStr, hour);
    },

    onCellDragLeave: function() {
        // Le preview est maintenu jusqu'au prochain survol, drop ou dragend.
    },

    onCellDrop: function(event, dateStr, hour) {
        event.preventDefault();
        window.PlanningModule.clearDragPreview();
        var rdvId = parseInt(event.dataTransfer.getData('text/plain'), 10);
        if (!rdvId) return;
        if (!window.PlanningModule.isPlanningSlotOpen(dateStr, hour)) {
            showAlert('Creneau ferme: deplacement impossible', 'warning');
            return;
        }
        var rdv = APP.planningRdvs.find(function(r) { return r.id === rdvId; });
        var duration = rdv ? window.PlanningUtils.getRdvDurationMinutes(rdv) : 60;
        if (!window.PlanningModule.isPlanningSlotValidForDuration(dateStr, hour, duration)) {
            showAlert('Duree RDV depasse l\'heure de fermeture', 'warning');
            return;
        }
        var availability = window.PlanningModule.getSlotResourceAvailability(dateStr, hour, duration, {
            ignoreRdvId: rdvId,
            requiredPontId: rdv && rdv.pont_id ? rdv.pont_id : null,
            requiredMecanicienId: rdv && rdv.mecanicien_id ? rdv.mecanicien_id : null
        });
        if (!availability.hasCapacity) {
            var dragSuggestions = window.PlanningModule.findAlternativeSlots(dateStr, duration, {
                limit: 3,
                ignoreRdvId: rdvId,
                requiredPontId: rdv && rdv.pont_id ? rdv.pont_id : null,
                requiredMecanicienId: rdv && rdv.mecanicien_id ? rdv.mecanicien_id : null,
                skipDate: dateStr,
                skipHour: hour
            });
            var dragSuggestionText = dragSuggestions.length ? ' Prochains creneaux: ' + dragSuggestions.map(function(item) { return item.label; }).join(', ') : '';
            showAlert((availability.reason || 'Aucune ressource libre sur ce creneau') + dragSuggestionText, 'warning');
            return;
        }
        var oldInfo = rdv ? (rdv.date_rdv + ' ' + formatTime(rdv.heure_rdv)) : '';
        openConfirmDialog('Deplacer le RDV #' + rdvId + ' de ' + oldInfo + ' vers ' + dateStr + ' ' + hour + ' ?', function() {
            var payload = { date_rdv: dateStr, heure_rdv: hour };
            if (rdv && !rdv.mecanicien_id && availability.defaultMecanicienId) payload.mecanicien_id = availability.defaultMecanicienId;
            if (rdv && !rdv.pont_id && availability.defaultPontId) payload.pont_id = availability.defaultPontId;
            apiPut('/api/rendez-vous/' + rdvId, payload).then(function() {
                window.PlanningModule.loadPlanning();
                showNotificationToast('RDV #' + rdvId + ' deplace');
            }).catch(function(e) {
                if ((e.message || '').indexOf('Conflit planning') !== -1) {
                    showAlert(e.message, 'warning');
                    return;
                }
                alert('Erreur deplacement: ' + e.message);
            });
        });
    },

    formatPlanningModalDate: function(dateStr) {
        return window.PlanningUiModule.formatPlanningModalDate(dateStr);
    },

    getPlanningStatusMeta: function(status) {
        return window.PlanningUiModule.getPlanningStatusMeta(status);
    },

    formatDurationLabel: function(durationMinutes) {
        return window.PlanningUiModule.formatDurationLabel(durationMinutes);
    },

    getPlanningRdvWarnings: function(rdv) {
        return window.PlanningUiModule.getPlanningRdvWarnings(rdv);
    },

    renderPlanningWorkflowHistory: function(rdv) {
        return window.PlanningUiModule.renderPlanningWorkflowHistory(rdv);
    },

    renderPlanningRdvModal: function(rdv) {
        return window.PlanningUiModule.renderPlanningRdvModal(rdv);
    },

    onPlanningRdvClick: function(rdvId) {
        var fallback = APP.planningRdvs.find(function(r) { return r.id === rdvId; });
        if (!fallback) return;
        showModal('RDV #' + fallback.id + ' - chargement...', '<div class="planning-rdv-card"><div class="planning-rdv-muted">Chargement des details du rendez-vous...</div></div>', '780px');
        apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
            showModal('RDV #' + rdv.id + ' • ' + formatTime(rdv.heure_rdv) + ' • ' + (rdv.date_rdv || ''), window.PlanningModule.renderPlanningRdvModal(rdv), '780px');
        }).catch(function() {
            showModal('RDV #' + fallback.id + ' • ' + formatTime(fallback.heure_rdv) + ' • ' + (fallback.date_rdv || ''), window.PlanningModule.renderPlanningRdvModal(fallback), '780px');
        });
    },

    onPlanningCellClick: function(event, dateStr, hour) {
        if (event.target.closest && event.target.closest('.rdv-block')) return;
        if (!window.PlanningModule.isPlanningSlotOpen(dateStr, hour)) {
            showAlert('Atelier ferme sur ce creneau', 'warning');
            return;
        }
        var availability = window.PlanningModule.getSlotResourceAvailability(dateStr, hour, 60);
        if (!availability.hasCapacity) {
            var clickSuggestions = window.PlanningModule.findAlternativeSlots(dateStr, 60, { limit: 3, skipDate: dateStr, skipHour: hour });
            var clickSuggestionText = clickSuggestions.length ? ' Prochains creneaux: ' + clickSuggestions.map(function(item) { return item.label; }).join(', ') : '';
            showAlert((availability.reason || 'Aucune ressource libre sur ce creneau') + clickSuggestionText, 'warning');
            return;
        }
        window.PlanningModule.ouvrirQuickCreateRdv(dateStr, hour);
    },

    ouvrirQuickCreateRdv: function(dateStr, hour) {
        var html = '';
        html += '<div style="display:flex;gap:12px;margin-bottom:16px">'
            + '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Date</label>'
            + '<input type="date" id="qc-date" class="form-input" value="' + dateStr + '" onchange="refreshQuickCreateAvailability()"></div>'
            + '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Heure</label>'
            + '<input type="time" id="qc-hour" class="form-input" value="' + hour + '" onchange="refreshQuickCreateAvailability()"></div></div>';
        html += '<div id="qc-slot-meta" style="background:var(--dark3);border:1px solid #2d3748;border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:12px;color:#cbd5e1"></div>';

        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Client (recherche)</label>'
            + '<input class="form-input" id="qc-client-search" placeholder="Nom, prenom ou telephone..." oninput="searchClientQuickCreate(this.value)">'
            + '<div id="qc-client-results" style="max-height:120px;overflow-y:auto;margin-top:4px"></div></div>';

        html += '<div style="display:flex;gap:12px">'
            + '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Nom</label>'
            + '<input class="form-input" id="qc-client-nom" placeholder="Nom"></div>'
            + '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Prenom</label>'
            + '<input class="form-input" id="qc-client-prenom" placeholder="Prenom"></div></div>';

        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Telephone</label>'
            + '<input class="form-input" id="qc-client-tel" placeholder="06 XX XX XX XX"></div>';

        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Plaque immatriculation</label>'
            + '<input class="form-input" id="qc-plaque" placeholder="AB-123-CD" oninput="searchVehiculeQuickCreate(this.value)"></div>';
        html += '<div id="qc-vehicule-info" style="display:none;background:var(--dark3);border:1px solid rgba(255,255,255,.08);border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:12px;color:#aaa"></div>';

        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Type d\'intervention</label>'
            + '<select class="form-select" id="qc-intervention" onchange="refreshQuickCreateAvailability()"><option value="">Selectionner...</option>';
        APP.interventionTypes.forEach(function(it) {
            html += '<option value="' + escapeHtml(it.nom) + '">' + it.nom + '</option>';
        });
        html += '</select></div>';

        html += '<div style="display:flex;gap:12px">'
            + '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Technicien libre</label>'
            + '<select class="form-select" id="qc-meca"><option value="">Auto / non assigne</option></select></div>'
            + '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Pont libre</label>'
            + '<select class="form-select" id="qc-pont"><option value="">Auto / non assigne</option></select></div></div>';

        html += '<button id="qc-submit-btn" class="btn btn-primary" style="width:100%;margin-top:12px" onclick="submitQuickCreateRdv()">Creer le RDV</button>';
        showModal('Nouveau RDV - ' + dateStr + ' a ' + hour, html, '500px');
        window.PlanningModule.refreshQuickCreateAvailability();
    },

    refreshQuickCreateAvailability: function(preserveSelection) {
        var dateInput = document.getElementById('qc-date');
        var hourInput = document.getElementById('qc-hour');
        var interventionInput = document.getElementById('qc-intervention');
        var metaEl = document.getElementById('qc-slot-meta');
        var mecaEl = document.getElementById('qc-meca');
        var pontEl = document.getElementById('qc-pont');
        var submitBtn = document.getElementById('qc-submit-btn');
        if (!dateInput || !hourInput || !metaEl) return;

        var dateVal = dateInput.value;
        var hourVal = hourInput.value;
        var intervention = interventionInput ? interventionInput.value : '';
        var selectedIntervention = null;
        for (var i = 0; i < APP.interventionTypes.length; i++) {
            if (APP.interventionTypes[i].nom === intervention) {
                selectedIntervention = APP.interventionTypes[i];
                break;
            }
        }
        var duration = selectedIntervention ? (selectedIntervention.temps_estime_minutes || selectedIntervention.temps_estime || 60) : 60;
        var endMinutes = window.PlanningModule.computePlanningEffectiveEndMinutes(dateVal, hourVal, duration);
        var endLabel = endMinutes >= 0 ? window.PlanningUtils.minutesToTimeLabel(endMinutes) : '--:--';
        var availability = window.PlanningModule.getSlotResourceAvailability(dateVal, hourVal, duration);

        var suggestions = window.PlanningModule.findAlternativeSlots(dateVal, duration, { limit: 4, skipDate: dateVal, skipHour: hourVal });
        var suggestionsHtml = '';
        if (suggestions.length) {
            suggestionsHtml = '<div class="planning-suggestion-list">' + suggestions.map(function(item) {
                return '<button type="button" class="planning-suggestion-chip" onclick="applyQuickCreateSuggestion(\'' + item.date + '\',\'' + item.hour + '\',' + (item.mecanicien_id || 'null') + ',' + (item.pont_id || 'null') + ')">' + escapeHtml(item.label) + '</button>';
            }).join('') + '</div>';
        }
        metaEl.innerHTML = '<div style="font-weight:700;color:#f8fafc;margin-bottom:4px">Creneau ' + escapeHtml(hourVal || '--:--') + ' → ' + escapeHtml(endLabel) + '</div>'
            + '<div style="color:' + (availability.hasCapacity ? '#86efac' : '#fca5a5') + '">' + escapeHtml(availability.reason || availability.summary || 'Disponibilite en cours...') + '</div>'
            + '<div style="font-size:11px;color:#94a3b8;margin-top:4px">Duree estimee: ' + escapeHtml(String(duration)) + ' min</div>'
            + (suggestions.length ? '<div style="font-size:11px;color:#cbd5e1;margin-top:8px">Creneaux conseilles</div>' + suggestionsHtml : '');

        if (mecaEl) {
            var previousMeca = preserveSelection === false ? '' : (mecaEl.value || '');
            var mecaOptions = '<option value="">Auto / non assigne</option>';
            availability.freeMecaniciens.forEach(function(meca) {
                mecaOptions += '<option value="' + meca.id + '">' + escapeHtml((meca.prenom || '') + ' ' + (meca.nom || '')) + '</option>';
            });
            mecaEl.innerHTML = mecaOptions;
            if (previousMeca && availability.freeMecaniciens.some(function(m) { return String(m.id) === String(previousMeca); })) {
                mecaEl.value = previousMeca;
            } else if (availability.defaultMecanicienId) {
                mecaEl.value = String(availability.defaultMecanicienId);
            }
        }

        if (pontEl) {
            var previousPont = preserveSelection === false ? '' : (pontEl.value || '');
            var pontOptions = '<option value="">Auto / non assigne</option>';
            availability.freePonts.forEach(function(pont) {
                pontOptions += '<option value="' + pont.id + '">' + escapeHtml(pont.nom || ('Pont #' + pont.id)) + '</option>';
            });
            pontEl.innerHTML = pontOptions;
            if (previousPont && availability.freePonts.some(function(p) { return String(p.id) === String(previousPont); })) {
                pontEl.value = previousPont;
            } else if (availability.defaultPontId) {
                pontEl.value = String(availability.defaultPontId);
            }
        }

        if (submitBtn) submitBtn.disabled = !availability.hasCapacity;
    },

    applyQuickCreateSuggestion: function(dateStr, hour, mecanicienId, pontId) {
        var dateInput = document.getElementById('qc-date');
        var hourInput = document.getElementById('qc-hour');
        if (dateInput) dateInput.value = dateStr;
        if (hourInput) hourInput.value = hour;
        window.PlanningModule.refreshQuickCreateAvailability(false);
        var mecaEl = document.getElementById('qc-meca');
        var pontEl = document.getElementById('qc-pont');
        if (mecaEl && mecanicienId !== null && mecanicienId !== undefined && mecanicienId !== 'null') mecaEl.value = String(mecanicienId);
        if (pontEl && pontId !== null && pontId !== undefined && pontId !== 'null') pontEl.value = String(pontId);
    },

    searchClientQuickCreate: function(val) {
        if (_qcSearchTimer) clearTimeout(_qcSearchTimer);
        var resultsEl = document.getElementById('qc-client-results');
        if (val.length < 2) {
            if (resultsEl) resultsEl.innerHTML = '';
            return;
        }
        _qcSearchTimer = setTimeout(function() {
            apiGet('/api/clients?search=' + encodeURIComponent(val)).then(function(r) { return r.json(); }).then(function(clients) {
                var container = document.getElementById('qc-client-results');
                if (!container) return;
                if (!clients || !clients.length) {
                    container.innerHTML = '<div style="font-size:11px;color:#666;padding:4px">Nouveau client - saisir manuellement</div>';
                    return;
                }
                var h = '';
                clients.slice(0, 5).forEach(function(c) {
                    h += '<div style="padding:6px 8px;cursor:pointer;border-bottom:1px solid #333;font-size:12px;color:#ccc" '
                        + 'onmouseover="this.style.background=\'#333\'" onmouseout="this.style.background=\'\'" '
                        + 'onclick="selectClientQuickCreate(\'' + escapeAttr(c.nom || '') + '\',\'' + escapeAttr(c.prenom || '') + '\',\'' + escapeAttr(c.telephone || '') + '\')">'
                        + '<b>' + escapeHtml(c.prenom || '') + ' ' + escapeHtml(c.nom || '') + '</b> - ' + escapeHtml(c.telephone || '') + '</div>';
                });
                container.innerHTML = h;
            }).catch(function() {});
        }, 300);
    },

    selectClientQuickCreate: function(nom, prenom, tel) {
        document.getElementById('qc-client-nom').value = nom;
        document.getElementById('qc-client-prenom').value = prenom;
        document.getElementById('qc-client-tel').value = tel;
        document.getElementById('qc-client-results').innerHTML = '<div style="font-size:11px;color:var(--green);padding:4px">Client selectionne: ' + escapeHtml(prenom) + ' ' + escapeHtml(nom) + '</div>';
    },

    searchVehiculeQuickCreate: function(val) {
        var infoEl = document.getElementById('qc-vehicule-info');
        if (!infoEl) return;
        if (val.length < 3) { infoEl.style.display = 'none'; return; }
        var plaque = val.replace(/[\s-]/g, '').toUpperCase();
        apiGet('/api/vehicule/' + encodeURIComponent(plaque)).then(function(r) { return r.json(); }).then(function(data) {
            if (data && !data.not_found && !data.detail) {
                infoEl.style.display = 'block';
                infoEl.innerHTML = 'Vehicule trouve: <b style="color:#eee">' + escapeHtml(data.marque || '') + ' ' + escapeHtml(data.modele || '') + '</b> ' + (escapeHtml(data.annee) ? '(' + data.annee + ')' : '');
                if (data.client) {
                    document.getElementById('qc-client-nom').value = data.client.nom || '';
                    document.getElementById('qc-client-prenom').value = data.client.prenom || '';
                    document.getElementById('qc-client-tel').value = data.client.telephone || '';
                }
            } else {
                infoEl.style.display = 'none';
            }
        }).catch(function() { infoEl.style.display = 'none'; });
    },

    submitQuickCreateRdv: function() {
        var dateVal = document.getElementById('qc-date').value;
        var hourVal = document.getElementById('qc-hour').value;
        var nom = document.getElementById('qc-client-nom').value;
        var prenom = document.getElementById('qc-client-prenom').value;
        var tel = document.getElementById('qc-client-tel').value;
        var plaque = document.getElementById('qc-plaque').value;
        var intervention = document.getElementById('qc-intervention').value;

        if (!dateVal || !hourVal || !nom || !intervention) {
            alert('Veuillez remplir: date, heure, nom client et type d\'intervention');
            return;
        }
        if (!window.PlanningModule.isPlanningSlotOpen(dateVal, hourVal)) {
            showAlert('Creneau ferme: creation RDV impossible', 'warning');
            return;
        }
        var selectedIntervention = null;
        for (var i = 0; i < APP.interventionTypes.length; i++) {
            if (APP.interventionTypes[i].nom === intervention) {
                selectedIntervention = APP.interventionTypes[i];
                break;
            }
        }
        var duration = selectedIntervention ? (selectedIntervention.temps_estime_minutes || selectedIntervention.temps_estime || 60) : 60;
        if (!window.PlanningModule.isPlanningSlotValidForDuration(dateVal, hourVal, duration)) {
            showAlert('Duree RDV depasse l\'heure de fermeture', 'warning');
            return;
        }

        var availability = window.PlanningModule.getSlotResourceAvailability(dateVal, hourVal, duration);
        if (!availability.hasCapacity) {
            var submitSuggestions = window.PlanningModule.findAlternativeSlots(dateVal, duration, { limit: 3, skipDate: dateVal, skipHour: hourVal });
            var submitSuggestionText = submitSuggestions.length ? ' Prochains creneaux: ' + submitSuggestions.map(function(item) { return item.label; }).join(', ') : '';
            showAlert((availability.reason || 'Aucune ressource libre sur ce creneau') + submitSuggestionText, 'warning');
            return;
        }

        var mecaValue = (document.getElementById('qc-meca') || {}).value || '';
        var pontValue = (document.getElementById('qc-pont') || {}).value || '';
        var payload = {
            client: { nom: nom, prenom: prenom || '', telephone: tel || '0000000000' },
            vehicule: { plaque: plaque || 'XX-000-XX' },
            date_rdv: dateVal,
            heure_rdv: hourVal + ':00',
            type_intervention: intervention,
            mecanicien_id: mecaValue ? parseInt(mecaValue, 10) : (availability.defaultMecanicienId || null),
            pont_id: pontValue ? parseInt(pontValue, 10) : (availability.defaultPontId || null)
        };

        apiPost('/api/rendez-vous', payload).then(function(r) { return r.json(); }).then(function() {
            closeModal();
            window.PlanningModule.loadPlanning();
        }).catch(function(e) { alert('Erreur creation RDV: ' + e.message); });
    }
};
