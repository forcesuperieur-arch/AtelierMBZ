var _qcSearchTimer = null;

window.PlanningModule = window.PlanningModule || {
    loadPlanning: function() {
        window.PlanningModule.populatePlanningAtelierSelect();
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
            headerHtml += '<div class="planning-day' + (isToday ? ' today' : '') + '">' + joursNoms[d] + ' ' + jour.getDate() + (isToday ? '<br><span style="font-size:10px;color:var(--orange)">Aujourd\'hui</span>' : '') + '</div>';
        }
        if (headerEl) headerEl.innerHTML = headerHtml;

        window.PlanningModule.renderMecaFilters();

        var dateDebut = _rdvDateToStr(monday);
        var dimanche = new Date(monday);
        dimanche.setDate(dimanche.getDate() + 6);
        var dateFin = _rdvDateToStr(dimanche);

        var weekLabel = document.getElementById('planning-week-label');
        if (weekLabel) weekLabel.textContent = 'Semaine ' + window.PlanningModule.getWeekNumber(monday);

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
            window.PlanningModule.renderPlanningGrid(rdvList, monday);
            APP.planningRdvs = rdvList;
            APP.planningMonday = monday;
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
        var start = window.PlanningUtils.timeToMinutes(hour);
        var closeAt = window.PlanningUtils.timeToMinutes(adminFmtTime(h.heure_fermeture));
        var duration = parseInt(durationMinutes || 0, 10);
        if (!duration || duration <= 0) duration = 60;
        return (start + duration) <= closeAt;
    },

    getPlanningHoraireForDate: function(dateStr) {
        if (!APP._horairesLoaded) return null;
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return null;
        var jsDay = d.getDay();
        var jour = jsDay === 0 ? 6 : jsDay - 1;
        return APP._horairesByDay[jour] || null;
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

    buildPlanningBusyCells: function(rdvs) {
        var busy = {};
        (rdvs || []).forEach(function(rdv) {
            var day = rdv.date_rdv || '';
            var start = window.PlanningUtils.timeToMinutes(formatTime(rdv.heure_rdv || ''));
            if (!day || start < 0) return;
            var dur = window.PlanningUtils.getRdvDurationMinutes(rdv);
            var segments = window.PlanningModule.splitRdvSegments(day, start, dur);
            segments.forEach(function(seg) {
                var step = APP._planningSlotMinutes || 15;
                var first = Math.floor(seg.start / step) * step;
                var last = Math.max(first, Math.ceil(seg.end / step) * step - step);
                for (var t = first; t <= last; t += step) {
                    var key = day + '|' + window.PlanningUtils.minutesToTimeLabel(t);
                    busy[key] = true;
                }
            });
        });
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
        APP.mecaniciens.forEach(function(meca) {
            var color = meca.couleur || '#3b82f6';
            var isFiltered = APP.planningMecaFilters.length > 0 && APP.planningMecaFilters.indexOf(meca.id) === -1;
            var opacity = isFiltered ? '0.35' : '1';
            html += '<div data-meca-id="' + meca.id + '" onclick="toggleMecaFilter(' + meca.id + ')" style="display:flex;align-items:center;gap:6px;background:' + hexToRgba(color, 0.12) + ';border:1px solid ' + hexToRgba(color, 0.3) + ';border-radius:20px;padding:4px 12px;cursor:pointer;opacity:' + opacity + ';transition:opacity .2s"><div style="width:8px;height:8px;border-radius:50%;background:' + color + '"></div><span style="font-size:12px;font-weight:600;color:' + color + '">' + escapeHtml(meca.prenom.charAt(0)) + '. ' + escapeHtml(meca.nom) + '</span></div>';
        });
        container.innerHTML = html;
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

        APP._planningBusyCells = window.PlanningModule.buildPlanningBusyCells(filteredRdvs);
        var conflictPairsById = {};
        var conflictCellKeys = {};
        var visualOverlapGroupsById = {};
        var conflictCount = 0;

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

        var html = '';
        hours.forEach(function(h) {
            html += '<div class="time-label">' + (h.slice(3) === '00' ? h : '') + '</div>';
            for (var d = 0; d < 7; d++) {
                var jour = new Date(monday);
                jour.setDate(jour.getDate() + d);
                var jourStr = _rdvDateToStr(jour);
                var isToday = jourStr === todayStr;
                var cellKey = jourStr + '|' + h;
                var isConflictCell = !!conflictCellKeys[cellKey];
                var isClosedCell = !window.PlanningModule.isPlanningSlotOpen(jourStr, h);
                var isBusyCell = window.PlanningModule.isPlanningCellBusy(jourStr, h);
                html += '<div class="planning-cell' + (isConflictCell ? ' has-conflict' : '') + (isClosedCell ? ' is-closed' : '') + (isBusyCell ? ' is-busy' : '') + '" data-date="' + jourStr + '" data-hour="' + h + '"'
                    + ' onclick="onPlanningCellClick(event,\'' + jourStr + '\',\'' + h + '\')"'
                    + ' ondragover="onCellDragOver(event)"'
                    + ' ondragleave="onCellDragLeave(event)"'
                    + ' ondrop="onCellDrop(event,\'' + jourStr + '\',\'' + h + '\')"'
                    + ' style="' + (isToday ? 'background:rgba(232,72,10,.03)' : '') + '">';
                filteredRdvs.forEach(function(rdv) {
                    if ((rdv.date_rdv || '') !== jourStr) return;
                    var rdvTime = formatTime(rdv.heure_rdv || '');
                    if (!rdvTime) return;
                    var rdvStart = window.PlanningUtils.timeToMinutes(rdvTime);
                    var segments = window.PlanningModule.splitRdvSegments(jourStr, rdvStart, window.PlanningUtils.getRdvDurationMinutes(rdv));
                    segments.forEach(function(seg) {
                        var slotMin = window.PlanningUtils.timeToMinutes(h);
                        var segStartSlot = Math.floor(seg.start / APP._planningSlotMinutes) * APP._planningSlotMinutes;
                        if (segStartSlot !== slotMin) return;
                        var meca = rdv.mecanicien || APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
                        var color = (meca && meca.couleur) ? meca.couleur : '#3b82f6';
                        var segDuration = Math.max(1, seg.end - seg.start);
                        var pxPerMin = APP._planningSlotPx / APP._planningSlotMinutes;
                        var height = Math.max(10, Math.ceil(segDuration * pxPerMin) - 2);
                        var rdvMin = seg.start % 60;
                        var topOffset = Math.round((seg.start - segStartSlot) * pxPerMin);
                        var timeLabel = (seg.continuation ? '↳ ' : '') + (Math.floor(seg.start / 60) < 10 ? '0' + Math.floor(seg.start / 60) : Math.floor(seg.start / 60)) + ':' + (rdvMin < 10 ? '0' + rdvMin : rdvMin);
                        var c = rdv.client || {};
                        var clientName = c.prenom ? (escapeHtml(c.prenom).charAt(0) + '. ' + (escapeHtml(c.nom) || '')) : (escapeHtml(c.nom) || '');
                        var overlapMeta = visualOverlapGroupsById[rdv.id] || { index: 0, count: 1 };
                        var overlapIndex = overlapMeta.index;
                        var overlapCount = overlapMeta.count;
                        var widthPct = overlapCount > 1 ? (100 / overlapCount) : 100;
                        var leftPct = overlapIndex * widthPct;
                        var isConflict = !!conflictPairsById[rdv.id];
                        html += '<div class="rdv-block' + (isConflict ? ' conflict' : '') + '" draggable="true" data-rdv-id="' + rdv.id + '"'
                            + ' onclick="event.stopPropagation();onPlanningRdvClick(' + rdv.id + ')"'
                            + ' ondragstart="onRdvDragStart(event,' + rdv.id + ')"'
                            + ' ondragend="onRdvDragEnd(event)"'
                            + ' title="' + escapeHtml(timeLabel + ' - ' + (escapeHtml(rdv.type_intervention) || '') + ' - ' + clientName) + '"'
                            + ' style="background:' + color + ';' + (seg.continuation ? 'opacity:.82;border:1px dashed rgba(255,255,255,.75);' : '') + 'height:' + height + 'px;color:white;top:' + topOffset + 'px;left:calc(' + leftPct + '% + 2px);width:calc(' + widthPct + '% - 4px);right:auto;z-index:' + (2 + overlapIndex) + '">'
                            + '<div style="font-size:10px;font-weight:600;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'
                            + timeLabel + ' ' + escapeHtml((rdv.type_intervention || '').substring(0, 14)) + '</div>';
                        if (height >= 18) {
                            html += '<div style="font-size:9px;opacity:.8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + escapeHtml(clientName) + '</div>';
                        }
                        if (isConflict) {
                            html += '<div style="font-size:9px;font-weight:700;color:#fecaca;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Conflit</div>';
                        }
                        html += '</div>';
                    });
                });
                html += '</div>';
            }
        });
        grid.innerHTML = html;
        window.PlanningUtils.renderPlanningNowLine(grid, monday);
        if (summaryEl) {
            if (conflictCount > 0) {
                summaryEl.innerHTML = '<span class="planning-conflict-dot"></span><strong>' + conflictCount + ' conflit(s) detecte(s)</strong> : chevauchements de RDV sur meme ressource (pont/mecanicien).';
            } else {
                summaryEl.innerHTML = '<span class="planning-conflict-dot" style="background:var(--green)"></span><span style="color:#86efac">Aucun conflit detecte sur la semaine affichee.</span>';
            }
        }
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

    onRdvDragStart: function(event, rdvId) {
        event.dataTransfer.setData('text/plain', String(rdvId));
        event.dataTransfer.effectAllowed = 'move';
        event.target.style.opacity = '0.4';
        APP._draggedEl = event.target;
    },

    onRdvDragEnd: function() {
        if (APP._draggedEl) {
            APP._draggedEl.style.opacity = '';
            APP._draggedEl = null;
        }
        var cells = document.querySelectorAll('.planning-cell.drag-over');
        for (var i = 0; i < cells.length; i++) {
            cells[i].classList.remove('drag-over');
        }
    },

    onCellDragOver: function(event) {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        event.currentTarget.classList.add('drag-over');
    },

    onCellDragLeave: function(event) {
        event.currentTarget.classList.remove('drag-over');
    },

    onCellDrop: function(event, dateStr, hour) {
        event.preventDefault();
        event.currentTarget.classList.remove('drag-over');
        var rdvId = parseInt(event.dataTransfer.getData('text/plain'), 10);
        if (!rdvId) return;
        if (!window.PlanningModule.isPlanningSlotOpen(dateStr, hour)) {
            showAlert('Creneau ferme: deplacement impossible', 'warning');
            return;
        }
        if (window.PlanningModule.isPlanningCellBusy(dateStr, hour)) {
            showAlert('Creneau deja occupe', 'warning');
            return;
        }
        var rdv = APP.planningRdvs.find(function(r) { return r.id === rdvId; });
        if (rdv && !window.PlanningModule.isPlanningSlotValidForDuration(dateStr, hour, window.PlanningUtils.getRdvDurationMinutes(rdv))) {
            showAlert('Duree RDV depasse l\'heure de fermeture', 'warning');
            return;
        }
        var oldInfo = rdv ? (rdv.date_rdv + ' ' + formatTime(rdv.heure_rdv)) : '';
        openConfirmDialog('Deplacer le RDV #' + rdvId + ' de ' + oldInfo + ' vers ' + dateStr + ' ' + hour + ' ?', function() {
            apiPut('/api/rendez-vous/' + rdvId, { date_rdv: dateStr, heure_rdv: hour }).then(function() {
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

    onPlanningRdvClick: function(rdvId) {
        var rdv = APP.planningRdvs.find(function(r) { return r.id === rdvId; });
        if (!rdv) return;
        var c = rdv.client || {};
        var v = rdv.vehicule || {};
        var meca = rdv.mecanicien;
        var pont = rdv.pont;
        var duree = rdv.temps_estime ? Math.round(rdv.temps_estime / 60 * 10) / 10 + 'h' : '-';

        var html = '<div style="display:flex;gap:16px;margin-bottom:16px">'
            + '<div style="flex:1">'
            + '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px">Client</div>'
            + '<div style="font-size:15px;font-weight:600;color:#eee">' + escapeHtml(c.prenom || '') + ' ' + escapeHtml(c.nom || '') + '</div>'
            + '<div style="font-size:12px;color:#888">' + escapeHtml(c.telephone || '') + '</div>'
            + '</div>'
            + '<div style="flex:1">'
            + '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px">Vehicule</div>'
            + '<div style="font-size:15px;font-weight:600;color:#eee">' + escapeHtml(v.marque || '') + ' ' + escapeHtml(v.modele || '') + '</div>'
            + '<div style="font-size:12px;color:#888">' + escapeHtml(v.plaque || '') + '</div>'
            + '</div></div>';

        html += '<div style="display:flex;gap:16px;margin-bottom:16px">'
            + '<div style="flex:1">'
            + '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px">Intervention</div>'
            + '<div style="font-size:14px;color:#eee">' + escapeHtml(rdv.type_intervention || '-') + '</div>'
            + '<div style="font-size:12px;color:#888">Duree: ' + duree + '</div>'
            + '</div>'
            + '<div style="flex:1">'
            + '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px">Statut</div>'
            + '<div style="margin-top:4px">' + statusBadge(rdv.statut) + '</div>'
            + '</div></div>';

        html += '<div style="display:flex;gap:16px;margin-bottom:16px">'
            + '<div style="flex:1">'
            + '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px">Mecanicien</div>'
            + '<div style="font-size:14px;color:#eee">' + (meca ? escapeHtml(meca.prenom) + ' ' + escapeHtml(meca.nom) : 'Non assigne') + '</div>'
            + '</div>'
            + '<div style="flex:1">'
            + '<div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:.5px">Pont</div>'
            + '<div style="font-size:14px;color:#eee">' + (pont ? escapeHtml(pont.nom) : 'Non assigne') + '</div>'
            + '</div></div>';

        html += '<div style="margin-top:12px">' + actionButtons(rdv, false) + '</div>';

        showModal('RDV #' + rdv.id + ' - ' + formatTime(rdv.heure_rdv) + ' ' + (rdv.date_rdv || ''), html, '550px');
    },

    onPlanningCellClick: function(event, dateStr, hour) {
        if (event.target.closest && event.target.closest('.rdv-block')) return;
        if (!window.PlanningModule.isPlanningSlotOpen(dateStr, hour)) {
            showAlert('Atelier ferme sur ce creneau', 'warning');
            return;
        }
        if (window.PlanningModule.isPlanningCellBusy(dateStr, hour)) {
            showAlert('Creneau deja occupe', 'warning');
            return;
        }
        window.PlanningModule.ouvrirQuickCreateRdv(dateStr, hour);
    },

    ouvrirQuickCreateRdv: function(dateStr, hour) {
        var html = '';
        html += '<div style="display:flex;gap:12px;margin-bottom:16px">'
            + '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Date</label>'
            + '<input type="date" id="qc-date" class="form-input" value="' + dateStr + '"></div>'
            + '<div class="form-group" style="flex:1"><label class="form-label" style="color:#ccc">Heure</label>'
            + '<input type="time" id="qc-hour" class="form-input" value="' + hour + '"></div></div>';

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
        html += '<div id="qc-vehicule-info" style="display:none;background:#1e1e1e;border:1px solid #444;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:12px;color:#aaa"></div>';

        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Type d\'intervention</label>'
            + '<select class="form-select" id="qc-intervention"><option value="">Selectionner...</option>';
        APP.interventionTypes.forEach(function(it) {
            html += '<option value="' + escapeHtml(it.nom) + '">' + it.nom + '</option>';
        });
        html += '</select></div>';

        html += '<button class="btn btn-primary" style="width:100%;margin-top:12px" onclick="submitQuickCreateRdv()">Creer le RDV</button>';
        showModal('Nouveau RDV - ' + dateStr + ' a ' + hour, html, '500px');
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
        if (window.PlanningModule.isPlanningCellBusy(dateVal, hourVal)) {
            showAlert('Creneau deja occupe', 'warning');
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

        apiPost('/api/rendez-vous', {
            client: { nom: nom, prenom: prenom || '', telephone: tel || '0000000000' },
            vehicule: { plaque: plaque || 'XX-000-XX' },
            date_rdv: dateVal,
            heure_rdv: hourVal + ':00',
            type_intervention: intervention
        }).then(function(r) { return r.json(); }).then(function() {
            closeModal();
            window.PlanningModule.loadPlanning();
        }).catch(function(e) { alert('Erreur creation RDV: ' + e.message); });
    }
};
