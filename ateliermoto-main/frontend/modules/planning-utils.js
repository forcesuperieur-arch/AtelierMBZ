window.PlanningUtils = window.PlanningUtils || {
    timeToMinutes: function(hhmm) {
        if (!hhmm || hhmm.indexOf(':') === -1) return -1;
        var parts = hhmm.split(':');
        var h = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10);
        if (isNaN(h) || isNaN(m)) return -1;
        return (h * 60) + m;
    },

    parseUTCDate: function(isoStr) {
        if (!isoStr) return null;
        var s = String(isoStr);
        if (!s.endsWith('Z') && !s.includes('+')) s += 'Z';
        var d = new Date(s);
        return isNaN(d.getTime()) ? null : d;
    },

    minutesToTimeLabel: function(totalMin) {
        var h = Math.floor(totalMin / 60);
        var m = totalMin % 60;
        return (h < 10 ? '0' + h : '' + h) + ':' + (m < 10 ? '0' + m : '' + m);
    },

    getPlanningBounds: function() {
        if (!APP._horairesLoaded || !APP._horairesByDay) return { start: '08:00', end: '18:00' };
        var minOpen = 24 * 60, maxClose = 0, found = false;
        for (var j = 0; j < 7; j++) {
            var h = APP._horairesByDay[j];
            if (!h || !h.is_ouvert) continue;
            var o = window.PlanningUtils.timeToMinutes(adminFmtTime(h.heure_ouverture));
            var c = window.PlanningUtils.timeToMinutes(adminFmtTime(h.heure_fermeture));
            if (o >= 0 && c > 0) {
                found = true;
                if (o < minOpen) minOpen = o;
                if (c > maxClose) maxClose = c;
            }
        }
        if (!found) return { start: '08:00', end: '18:00' };
        return { start: window.PlanningUtils.minutesToTimeLabel(minOpen), end: window.PlanningUtils.minutesToTimeLabel(maxClose) };
    },

    buildPlanningSlots: function(startTime, endTime, stepMin) {
        var start = window.PlanningUtils.timeToMinutes(startTime);
        var end = window.PlanningUtils.timeToMinutes(endTime);
        var step = Math.max(5, parseInt(stepMin || 15, 10));
        var out = [];
        for (var t = start; t < end; t += step) out.push(window.PlanningUtils.minutesToTimeLabel(t));
        return out;
    },

    markConflictCells: function(cellMap, dayKey, startMin, endMin) {
        var step = APP._planningSlotMinutes || 15;
        var slotStart = Math.floor(startMin / step) * step;
        var slotEnd = Math.max(slotStart, Math.ceil(endMin / step) * step - step);
        for (var t = slotStart; t <= slotEnd; t += step) {
            cellMap[dayKey + '|' + window.PlanningUtils.minutesToTimeLabel(t)] = true;
        }
    },

    buildVisualOverlapGroups: function(dayList, byId) {
        for (var i = 0; i < dayList.length; i++) {
            var current = dayList[i];
            var cStart = window.PlanningUtils.timeToMinutes(formatTime(current.heure_rdv || ''));
            if (cStart < 0) continue;
            var cEnd = cStart + getRdvDurationMinutes(current);
            var overlaps = [];
            for (var j = 0; j < dayList.length; j++) {
                var other = dayList[j];
                var oStart = window.PlanningUtils.timeToMinutes(formatTime(other.heure_rdv || ''));
                if (oStart < 0) continue;
                var oEnd = oStart + getRdvDurationMinutes(other);
                if (cStart < oEnd && oStart < cEnd) overlaps.push(other);
            }
            overlaps.sort(function(a, b) {
                var aStart = window.PlanningUtils.timeToMinutes(formatTime(a.heure_rdv || ''));
                var bStart = window.PlanningUtils.timeToMinutes(formatTime(b.heure_rdv || ''));
                if (aStart !== bStart) return aStart - bStart;
                return (a.id || 0) - (b.id || 0);
            });
            var index = 0;
            for (var k = 0; k < overlaps.length; k++) {
                if (overlaps[k].id === current.id) { index = k; break; }
            }
            byId[current.id] = { index: index, count: overlaps.length || 1 };
        }
    },

    renderPlanningNowLine: function(grid, monday) {
        if (!grid || !monday) return;
        var old = grid.querySelector('.planning-now-line');
        if (old) old.remove();
        var now = new Date();
        var today = _rdvDateToStr(now);
        var mondayDay = new Date(monday);
        var mondayStr = _rdvDateToStr(mondayDay);
        var sunday = new Date(mondayDay);
        sunday.setDate(sunday.getDate() + 6);
        var sundayStr = _rdvDateToStr(sunday);
        if (today < mondayStr || today > sundayStr) return;

        var dayOffset = Math.floor((new Date(today) - new Date(mondayStr)) / 86400000);
        if (dayOffset < 0 || dayOffset > 6) return;
        var minutes = now.getHours() * 60 + now.getMinutes();
        var bounds = window.PlanningUtils.getPlanningBounds();
        var startMin = window.PlanningUtils.timeToMinutes(bounds.start);
        var endMin = window.PlanningUtils.timeToMinutes(bounds.end);
        if (minutes < startMin || minutes > endMin) return;

        var bodyStyles = window.getComputedStyle(grid);
        var cols = bodyStyles.gridTemplateColumns.split(' ').filter(Boolean);
        if (cols.length < 8) return;
        var timeColWidth = parseFloat(cols[0]) || 80;
        var dayColWidth = (grid.clientWidth - timeColWidth) / 7;
        var y = Math.max(0, Math.round(((minutes - startMin) / APP._planningSlotMinutes) * APP._planningSlotPx));
        var x = timeColWidth + (dayOffset * dayColWidth);

        var line = document.createElement('div');
        line.className = 'planning-now-line';
        line.style.top = y + 'px';
        line.style.left = x + 'px';
        line.style.width = dayColWidth + 'px';
        line.innerHTML = '<span class="planning-now-time">' + formatTime(now.toTimeString()) + '</span>';
        grid.appendChild(line);
    },

    getRdvDurationMinutes: function(rdv) {
        if (!rdv) return 60;
        var candidates = [
            rdv.temps_estime_minutes,
            rdv.duree_minutes,
            rdv.temps_estime,
            rdv.duration_minutes,
            rdv.duration
        ];
        for (var i = 0; i < candidates.length; i++) {
            var minutes = window.PlanningUtils.parseDurationToMinutes(candidates[i]);
            if (minutes > 0) return Math.max(30, minutes);
        }
        return 60;
    },

    parseDurationToMinutes: function(value) {
        if (value === null || value === undefined) return 0;
        if (typeof value === 'number' && isFinite(value)) {
            if (value <= 0) return 0;
            return value <= 12 ? Math.round(value * 60) : Math.round(value);
        }
        var raw = String(value).trim().toLowerCase();
        if (!raw) return 0;
        if (/^\d{1,2}:\d{2}$/.test(raw)) {
            var hm = raw.split(':');
            return parseInt(hm[0], 10) * 60 + parseInt(hm[1], 10);
        }
        var hMatch = raw.match(/(\d+(?:[.,]\d+)?)\s*h/);
        var mMatch = raw.match(/(\d+(?:[.,]\d+)?)\s*m/);
        if (hMatch || mMatch) {
            var h = hMatch ? parseFloat(hMatch[1].replace(',', '.')) : 0;
            var m = mMatch ? parseFloat(mMatch[1].replace(',', '.')) : 0;
            return Math.round((h * 60) + m);
        }
        var num = parseFloat(raw.replace(',', '.'));
        if (!isNaN(num) && num > 0) {
            return num <= 12 ? Math.round(num * 60) : Math.round(num);
        }
        return 0;
    }
};
