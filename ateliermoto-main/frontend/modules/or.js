window.ETAT_VEHICULE_POINTS = window.ETAT_VEHICULE_POINTS || [
    { key: 'carrosserie_ok', label: 'Carrosserie OK' },
    { key: 'rayures', label: 'Rayures visibles' },
    { key: 'bosses', label: 'Bosses / chocs' },
    { key: 'freins_ok', label: 'Freins (impression)' },
    { key: 'pneus_av_ok', label: 'Pneu avant OK' },
    { key: 'pneus_ar_ok', label: 'Pneu arriere OK' },
    { key: 'eclairage_ok', label: 'Eclairage fonctionne' },
    { key: 'retros_ok', label: 'Retroviseurs OK' },
    { key: 'clignotants_ok', label: 'Clignotants OK' },
    { key: 'compteur_ok', label: 'Tableau de bord OK' },
    { key: 'fuite_visible', label: 'Fuite visible' },
    { key: 'accessoires', label: 'Accessoires notes' }
];

var _receptionSignatureCtx = null;
var _receptionSignatureDrawing = false;
var _receptionSignatureHasData = false;
var _tsSignatureCtx = null;
var _tsSignatureDrawing = false;
var _tsSignatureHasData = false;

window.OrModule = window.OrModule || {
    telechargerOR: function(rdvId) {
        window.open(window.API_URL + '/api/rendez-vous/' + rdvId + '/ordre-reparation', '_blank');
    },

    loadOrdresReparation: function() {
        apiGet('/api/rendez-vous').then(function(r) { return r.json(); }).then(function(rdvs) {
            window.OrModule.renderOrdresReparation(rdvs);
        }).catch(function(e) { console.error('Erreur OR:', e); });
        window.OrModule.pollTravauxSupp();
        setTimeout(function() { window.OrModule.renderTravauxSuppPanel(); }, 500);
    },

    renderOrdresReparation: function(rdvs) {
        var container = document.getElementById('or-list');
        var enCours = rdvs.filter(function(r) { return r.statut !== 'annule' && r.statut !== 'non_presente'; });
        document.getElementById('or-ouverts').textContent = enCours.filter(function(r) { return r.statut !== 'termine' && r.statut !== 'facture' && r.statut !== 'paye'; }).length + ' ouverts';
        document.getElementById('or-termines').textContent = enCours.filter(function(r) { return r.statut === 'termine' || r.statut === 'facture' || r.statut === 'paye'; }).length + ' termines';

        enCours.sort(function(a, b) {
            var order = { 'en_cours': 0, 'reception': 1, 'confirme': 2, 'reserve': 3, 'en_attente': 4, 'termine': 5, 'facture': 6, 'paye': 7, 'non_presente': 8 };
            return (order[a.statut] || 9) - (order[b.statut] || 9);
        });

        var etapes = ['Reception', 'Diagnostic', 'Intervention', 'Controle QC', 'Livraison'];
        var html = '';
        enCours.slice(0, 30).forEach(function(rdv) {
            var meca = rdv.mecanicien || APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
            var mecaNom = meca ? meca.prenom + ' ' + escapeHtml(meca.nom) : '-';
            var mecaCouleur = meca ? meca.couleur : '#666';
            var v = rdv.vehicule || {};
            var c = rdv.client || {};
            var duree = rdv.temps_estime ? Math.round(rdv.temps_estime / 60 * 10) / 10 + 'h' : '-';
            var isTermine = rdv.statut === 'termine' || rdv.statut === 'facture' || rdv.statut === 'paye';
            var currentIdx = window.OrModule.getEtapeIndex(rdv.statut);
            var dateCreation = rdv.date_rdv || '';
            var year = dateCreation ? dateCreation.substring(0, 4) : new Date().getFullYear();
            var orNum = 'OR-' + year + '-' + String(rdv.id).padStart(3, '0');

            var stepsHtml = '<div style="display:flex;gap:4px;margin-top:12px">';
            etapes.forEach(function(label, i) {
                var bg, txtCol;
                if (i < currentIdx) { bg = '#22C55E'; txtCol = '#fff'; }
                else if (i === currentIdx && !isTermine) { bg = '#E8480A'; txtCol = '#fff'; }
                else if (isTermine) { bg = '#22C55E'; txtCol = '#fff'; }
                else { bg = '#2a2a2e'; txtCol = '#666'; }
                var radius = '';
                if (i === 0) radius = 'border-radius:6px 0 0 6px;';
                if (i === etapes.length - 1) radius = 'border-radius:0 6px 6px 0;';
                stepsHtml += '<div style="flex:1;text-align:center;padding:6px 4px;font-size:10px;font-weight:600;letter-spacing:.3px;background:' + bg + ';color:' + txtCol + ';' + radius + '">' + label + '</div>';
            });
            stepsHtml += '</div>';

            html += '<div class="or-card" style="cursor:pointer;' + (isTermine ? 'opacity:.65' : '') + '" onclick="showOrDetail(' + rdv.id + ')">' +
                '<div class="or-header" style="display:flex;align-items:center;gap:12px;margin-bottom:10px">' +
                    '<div class="or-num" style="font-family:Barlow Condensed,sans-serif;font-size:20px;font-weight:700;letter-spacing:.5px">' + orNum + '</div>' +
                    statusBadge(rdv.statut) +
                    '<div style="margin-left:auto;display:flex;align-items:center;gap:8px">' +
                        '<span style="font-size:12px;color:#777">' + dateCreation + ' ' + formatTime(rdv.heure_rdv) + '</span>' +
                        actionButtons(rdv, true, { hideBillingActions: true }) +
                    '</div>' +
                '</div>' +
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;font-size:13px;color:#ccc">' +
                    '<div style="display:flex;align-items:center;gap:6px"><span style="font-size:16px">&#128690;</span> <b style="color:#fff">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</b> <span style="color:#888">' + (escapeHtml(v.plaque) || '') + '</span></div>' +
                    '<div style="display:flex;align-items:center;gap:6px"><span style="font-size:16px">&#128100;</span> ' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + (escapeHtml(c.telephone) ? ' <span style="color:#888">- ' + c.telephone + '</span>' : '') + '</div>' +
                    '<div style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:50%;background:' + mecaCouleur + ';display:inline-block"></span> ' + mecaNom + '</div>' +
                    '<div style="display:flex;align-items:center;gap:6px"><span style="font-size:16px">&#128295;</span> ' + (escapeHtml(rdv.type_intervention) || '-') + ' <span style="color:#888">(' + duree + ')</span></div>' +
                '</div>' +
                stepsHtml +
            '</div>';
        });
        container.innerHTML = html || '<div style="color:#666;padding:20px">Aucun OR</div>';
    },

    getEtapeIndex: function(statut) {
        var map = { 'reserve': 0, 'en_attente': 0, 'confirme': 0, 'reception': 0, 'en_cours': 2, 'termine': 4, 'facture': 4, 'paye': 4, 'non_presente': 4 };
        return map[statut] !== undefined ? map[statut] : 0;
    },

    showOrDetail: function(rdvId) {
        apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
            var v = rdv.vehicule || {};
            var c = rdv.client || {};
            var meca = rdv.mecanicien || APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
            var mecaNom = meca ? meca.prenom + ' ' + escapeHtml(meca.nom) : '-';
            var pont = rdv.pont || APP.ponts.find(function(p) { return p.id === rdv.pont_id; });
            var duree = rdv.temps_estime ? Math.round(rdv.temps_estime / 60 * 10) / 10 + 'h' : '-';
            var year = rdv.date_rdv ? rdv.date_rdv.substring(0, 4) : new Date().getFullYear();
            var orNum = 'OR-' + year + '-' + String(rdv.id).padStart(3, '0');
            var currentIdx = window.OrModule.getEtapeIndex(rdv.statut);
            var isTermine = rdv.statut === 'termine' || rdv.statut === 'facture' || rdv.statut === 'paye';
            var etapes = ['Reception', 'Diagnostic', 'Intervention', 'Controle QC', 'Livraison'];

            var stepsHtml = '<div style="display:flex;gap:4px;margin:16px 0">';
            etapes.forEach(function(label, i) {
                var bg, txtCol;
                if (i < currentIdx) { bg = '#22C55E'; txtCol = '#fff'; }
                else if (i === currentIdx && !isTermine) { bg = '#E8480A'; txtCol = '#fff'; }
                else if (isTermine) { bg = '#22C55E'; txtCol = '#fff'; }
                else { bg = '#2a2a2e'; txtCol = '#666'; }
                var radius = '';
                if (i === 0) radius = 'border-radius:6px 0 0 6px;';
                if (i === etapes.length - 1) radius = 'border-radius:0 6px 6px 0;';
                stepsHtml += '<div style="flex:1;text-align:center;padding:8px 4px;font-size:11px;font-weight:600;background:' + bg + ';color:' + txtCol + ';' + radius + '">' + label + '</div>';
            });
            stepsHtml += '</div>';

            var orSuppHtml = '';
            if (rdv.ordres_reparation && rdv.ordres_reparation.length > 0) {
                orSuppHtml = '<div style="margin-top:16px;border-top:1px solid #333;padding-top:12px"><div style="font-weight:600;margin-bottom:8px">Ordres de reparation associes</div>';
                rdv.ordres_reparation.forEach(function(or) {
                    var badge = or.type_or === 'supplementaire' ? '<span style="background:#F26524;color:#fff;padding:1px 8px;border-radius:10px;font-size:10px;font-weight:600;margin-left:6px">Supp.</span>' : '';
                    orSuppHtml += '<div style="padding:8px;background:#1a1a1d;border-radius:8px;margin-bottom:6px">' +
                        '<div style="font-weight:600">' + (escapeHtml(or.numero_or) || orNum) + badge + '</div>' +
                        (escapeHtml(or.travaux) ? '<div style="font-size:12px;color:#aaa;margin-top:4px">' + or.travaux + '</div>' : '') +
                    '</div>';
                });
                orSuppHtml += '</div>';
            }

            var overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.7);display:flex;align-items:center;justify-content:center;z-index:1000';
            overlay.onclick = function(e) { if (e.target === overlay) overlay.remove(); };
            overlay.innerHTML =
                '<div style="background:#1e1e22;border:1px solid #333;border-radius:16px;padding:28px;width:600px;max-width:90vw;max-height:85vh;overflow-y:auto;color:#eee">' +
                    '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">' +
                        '<div style="font-family:Barlow Condensed,sans-serif;font-size:26px;font-weight:700">' + orNum + '</div>' +
                        statusBadge(rdv.statut) +
                        '<button onclick="this.closest(\'.modal-overlay\').remove()" style="background:none;border:none;color:#888;font-size:22px;cursor:pointer">&times;</button>' +
                    '</div>' +
                    stepsHtml +
                    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;font-size:14px">' +
                        '<div style="background:#16161a;border-radius:10px;padding:14px">' +
                            '<div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:6px;font-weight:600">Vehicule</div>' +
                            '<div style="font-weight:600">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div>' +
                            '<div style="color:#aaa">' + (escapeHtml(v.plaque) || '') + '</div>' +
                        '</div>' +
                        '<div style="background:#16161a;border-radius:10px;padding:14px">' +
                            '<div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:6px;font-weight:600">Client</div>' +
                            '<div style="font-weight:600">' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + '</div>' +
                            '<div style="color:#aaa">' + (escapeHtml(c.telephone) || '') + '</div>' +
                        '</div>' +
                        '<div style="background:#16161a;border-radius:10px;padding:14px">' +
                            '<div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:6px;font-weight:600">Intervention</div>' +
                            '<div style="font-weight:600">' + (escapeHtml(rdv.type_intervention) || '-') + '</div>' +
                            '<div style="color:#aaa">Duree estimee: ' + duree + '</div>' +
                            '<div style="color:#ffd700;font-weight:600;margin-top:4px">' + (rdv.prix_final || rdv.prix_estime || 0) + ' EUR</div>' +
                        '</div>' +
                        '<div style="background:#16161a;border-radius:10px;padding:14px">' +
                            '<div style="font-size:11px;text-transform:uppercase;color:#888;margin-bottom:6px;font-weight:600">Assignation</div>' +
                            '<div style="font-weight:600">' + mecaNom + '</div>' +
                            '<div style="color:#aaa">' + (pont ? pont.nom : '-') + '</div>' +
                        '</div>' +
                    '</div>' +
                    (escapeHtml(rdv.notes) ? '<div style="margin-top:14px;padding:12px;background:#16161a;border-radius:10px;font-size:13px;color:#aaa"><span style="font-weight:600;color:#ccc">Notes: </span>' + rdv.notes + '</div>' : '') +
                    orSuppHtml +
                    '<div style="display:flex;gap:8px;margin-top:20px">' +
                        '<button onclick="telechargerOR(' + rdv.id + ')" style="flex:1;padding:10px;background:#E8480A;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:Barlow,sans-serif">Telecharger PDF</button>' +
                        '<button onclick="this.closest(\'.modal-overlay\').remove();planifierRdvSuite(' + rdv.id + ')" style="flex:1;padding:10px;background:#3B82F6;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:Barlow,sans-serif">Planifier RDV de suite</button>' +
                        '<button onclick="this.closest(\'.modal-overlay\').remove()" style="flex:1;padding:10px;background:#333;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:Barlow,sans-serif">Fermer</button>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(overlay);
        }).catch(function(e) { console.error('Erreur detail OR:', e); });
    },

    planifierRdvSuite: function(rdvId) {
        apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
            var v = rdv.vehicule || {};
            var c = rdv.client || {};
            showSection('rdv');
            setTimeout(function() {
                var plaqueInput = document.getElementById('rdv-plaque');
                if (plaqueInput) {
                    plaqueInput.value = v.plaque || '';
                    searchMotoRdv(v.plaque || '');
                }
                var commentInput = document.getElementById('rdv-comment');
                if (commentInput) commentInput.value = 'Suite du RDV #' + rdvId + ' - ';
                var clientName = document.getElementById('rdv-client-name');
                if (clientName) clientName.value = (escapeHtml(c.nom) || '') + ' ' + (escapeHtml(c.prenom) || '');
                var clientTel = document.getElementById('rdv-client-tel');
                if (clientTel) clientTel.value = c.telephone || '';
            }, 300);
        }).catch(function(e) { console.error('Erreur RDV suite:', e); });
    },

    ouvrirReception: function(rdvId) {
        apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
            var html = '';
            var v = rdv.vehicule || {};
            var c = rdv.client || {};

            html += '<div style="background:#1e1e1e;border:1px solid #333;border-radius:8px;padding:12px;margin-bottom:16px;display:flex;gap:16px">'
                + '<div><div style="font-size:11px;color:#666">Client</div><div style="color:#eee;font-weight:600">' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + '</div></div>'
                + '<div><div style="font-size:11px;color:#666">Vehicule</div><div style="color:#eee;font-weight:600">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div></div>'
                + '<div><div style="font-size:11px;color:#666">Plaque</div><div style="color:#eee;font-weight:600">' + (escapeHtml(v.plaque) || '') + '</div></div>'
                + '</div>';

            html += '<div class="form-group"><label class="form-label" style="color:#ccc">Kilometrage *</label>'
                + '<input type="number" id="reception-km" class="form-input" placeholder="Ex: 15000" value="' + (rdv.kilometrage || '') + '"></div>';

            html += '<div class="form-group"><label class="form-label" style="color:#ccc">Etat du vehicule</label>'
                + '<div class="reception-check-grid" id="reception-etat-checks">';
            window.ETAT_VEHICULE_POINTS.forEach(function(pt) {
                html += '<label class="reception-check-item"><input type="checkbox" value="' + pt.key + '"> ' + pt.label + '</label>';
            });
            html += '</div></div>';

            html += '<div class="form-group"><label class="form-label" style="color:#ccc">Observations</label>'
                + '<textarea id="reception-obs" class="form-input" rows="3" placeholder="Notes sur l\'etat general..."></textarea></div>';

            html += '<div class="form-group"><label class="form-label" style="color:#ccc">Signature client *</label>'
                + '<canvas id="reception-signature-canvas" width="400" height="150" style="border:1px solid #444;border-radius:6px;background:#fff;cursor:crosshair;display:block;width:100%;touch-action:none"></canvas>'
                + '<button class="btn btn-ghost" style="margin-top:6px;font-size:11px" onclick="clearReceptionSignature()">Effacer signature</button></div>';

            html += '<button class="btn btn-primary" style="width:100%;margin-top:12px;background:var(--teal)" onclick="validerReception(' + rdvId + ')">Valider la reception</button>';

            showModal('Reception - OR-' + String(rdvId).padStart(6, '0'), html, '600px');
            setTimeout(function() { window.OrModule.initReceptionSignaturePad(); }, 100);
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    initReceptionSignaturePad: function() {
        var canvas = document.getElementById('reception-signature-canvas');
        if (!canvas) return;
        var rect = canvas.getBoundingClientRect();
        var dpr = window.devicePixelRatio || 1;
        canvas.width = rect.width * dpr;
        canvas.height = rect.height * dpr;
        var ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, rect.width, rect.height);
        ctx.strokeStyle = '#000000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        _receptionSignatureCtx = ctx;
        _receptionSignatureHasData = false;

        canvas.addEventListener('mousedown', function(e) {
            _receptionSignatureDrawing = true;
            var coords = window.OrModule.getCanvasCoords(canvas, e);
            ctx.beginPath();
            ctx.moveTo(coords.x, coords.y);
        });
        canvas.addEventListener('mousemove', function(e) {
            if (!_receptionSignatureDrawing) return;
            var coords = window.OrModule.getCanvasCoords(canvas, e);
            ctx.lineTo(coords.x, coords.y);
            ctx.stroke();
            _receptionSignatureHasData = true;
        });
        canvas.addEventListener('mouseup', function() { _receptionSignatureDrawing = false; });
        canvas.addEventListener('mouseout', function() { _receptionSignatureDrawing = false; });
        canvas.addEventListener('touchstart', function(e) {
            e.preventDefault();
            _receptionSignatureDrawing = true;
            var coords = window.OrModule.getCanvasCoords(canvas, e.touches[0]);
            ctx.beginPath();
            ctx.moveTo(coords.x, coords.y);
        });
        canvas.addEventListener('touchmove', function(e) {
            e.preventDefault();
            if (!_receptionSignatureDrawing) return;
            var coords = window.OrModule.getCanvasCoords(canvas, e.touches[0]);
            ctx.lineTo(coords.x, coords.y);
            ctx.stroke();
            _receptionSignatureHasData = true;
        });
        canvas.addEventListener('touchend', function() { _receptionSignatureDrawing = false; });
    },

    getCanvasCoords: function(canvas, event) {
        var rect = canvas.getBoundingClientRect();
        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    },

    clearReceptionSignature: function() {
        var canvas = document.getElementById('reception-signature-canvas');
        if (!canvas || !_receptionSignatureCtx) return;
        var rect = canvas.getBoundingClientRect();
        _receptionSignatureCtx.fillStyle = '#ffffff';
        _receptionSignatureCtx.fillRect(0, 0, rect.width, rect.height);
        _receptionSignatureHasData = false;
    },

    getReceptionSignatureBase64: function() {
        if (!_receptionSignatureHasData) return null;
        var canvas = document.getElementById('reception-signature-canvas');
        return canvas ? canvas.toDataURL('image/png') : null;
    },

    validerReception: function(rdvId) {
        var km = document.getElementById('reception-km').value;
        if (!km) { alert('Kilometrage obligatoire'); return; }

        var etatItems = [];
        var checkboxes = document.querySelectorAll('#reception-etat-checks input[type="checkbox"]');
        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) etatItems.push(checkboxes[i].value);
        }
        var observations = document.getElementById('reception-obs').value || '';
        var etatVehicule = JSON.stringify({ points: etatItems, observations: observations });

        var signatureData = window.OrModule.getReceptionSignatureBase64();
        if (!signatureData) { alert('Signature client obligatoire'); return; }

        apiPost('/api/rendez-vous/' + rdvId + '/ordre-reparation/save', {
            kilometrage: parseInt(km, 10),
            etat_vehicule: etatVehicule,
            travaux: observations,
            signature: signatureData
        }).then(function(r) {
            return r.json();
        }).then(function() {
            return apiPost('/api/rendez-vous/' + rdvId + '/reception', {});
        }).then(function() {
            closeModal();
            window.OrModule.showNotificationToast('Reception validee - OR disponible');
            refreshCurrentSection();
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    ouvrirDemandeTravauxSupp: function(rdvId) {
        window._tsSelectedPrestations = [];
        var prestations = APP.prestationsConfig || [];
        var categories = {};
        prestations.forEach(function(p) {
            if (!p.is_active) return;
            var cat = p.categorie || 'Autre';
            if (!categories[cat]) categories[cat] = [];
            categories[cat].push(p);
        });

        var html = '<div style="margin-bottom:14px;font-size:13px;color:#aaa">Selectionnez les interventions necessaires. Le receptionniste fera le devis.</div>';
        html += '<div style="max-height:45vh;overflow-y:auto;margin-bottom:14px">';
        Object.keys(categories).sort().forEach(function(cat) {
            html += '<div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.6px;font-weight:700;margin:10px 0 6px;padding:0 2px">' + escapeHtml(cat) + '</div>';
            categories[cat].forEach(function(p) {
                html += '<label id="ts-presta-' + p.id + '" style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#1a1a22;border:1px solid #333;border-radius:8px;margin-bottom:6px;cursor:pointer;-webkit-tap-highlight-color:transparent" onclick="toggleTsPrestation(' + p.id + ',\'' + escapeHtml(p.code || '') + '\',\'' + escapeHtml((p.nom || '').replace(/'/g, '')) + '\')">' +
                    '<div id="ts-check-' + p.id + '" style="width:22px;height:22px;border:2px solid #555;border-radius:4px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:14px;color:#22c55e"></div>' +
                    '<div style="flex:1;min-width:0"><div style="font-size:14px;color:#eee;font-weight:500">' + escapeHtml(p.nom) + '</div>' +
                    '<div style="font-size:12px;color:#777">' + escapeHtml(p.code || '') + (p.temps_estime_minutes ? ' • ~' + p.temps_estime_minutes + ' min' : '') + '</div></div>' +
                    '</label>';
            });
        });
        html += '</div>';

        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Notes / Description du probleme</label>'
            + '<textarea id="travaux-supp-desc" class="form-input" rows="2" placeholder="Decrire ce que vous avez constate..." style="font-size:14px"></textarea></div>';
        html += '<div class="form-group"><label class="form-label" style="color:#ccc">Urgence</label>'
            + '<div style="display:flex;gap:8px">'
            + '<button id="ts-urg-normal" class="btn btn-ghost" style="flex:1;padding:10px;font-size:13px;border-color:var(--green);color:var(--green)" onclick="setTsUrgence(\'normal\')">Normal</button>'
            + '<button id="ts-urg-urgent" class="btn btn-ghost" style="flex:1;padding:10px;font-size:13px" onclick="setTsUrgence(\'urgent\')">Urgent</button>'
            + '<button id="ts-urg-critique" class="btn btn-ghost" style="flex:1;padding:10px;font-size:13px" onclick="setTsUrgence(\'critique\')">Critique</button>'
            + '</div></div>';
        html += '<div id="ts-selected-count" style="font-size:13px;color:#888;margin-bottom:8px"></div>';
        html += '<button class="btn btn-primary" style="width:100%;padding:14px;font-size:16px;font-weight:700;margin-top:4px" onclick="envoyerDemandeTravauxSupp(' + rdvId + ')">Envoyer au receptionniste</button>';

        window._tsUrgence = 'normal';
        showModal('Signaler un probleme - OR #' + rdvId, html, '520px');
        window.OrModule.updateTsSelectedCount();
    },

    toggleTsPrestation: function(id, code, nom) {
        if (!window._tsSelectedPrestations) window._tsSelectedPrestations = [];
        var idx = window._tsSelectedPrestations.findIndex(function(p) { return p.prestation_id === id; });
        var checkEl = document.getElementById('ts-check-' + id);
        var labelEl = document.getElementById('ts-presta-' + id);
        if (idx >= 0) {
            window._tsSelectedPrestations.splice(idx, 1);
            if (checkEl) checkEl.textContent = '';
            if (labelEl) labelEl.style.borderColor = '#333';
        } else {
            window._tsSelectedPrestations.push({ prestation_id: id, code: code, nom: nom });
            if (checkEl) checkEl.textContent = '✓';
            if (labelEl) labelEl.style.borderColor = 'var(--green)';
        }
        window.OrModule.updateTsSelectedCount();
    },

    updateTsSelectedCount: function() {
        var el = document.getElementById('ts-selected-count');
        var n = (window._tsSelectedPrestations || []).length;
        if (el) el.textContent = n > 0 ? n + ' prestation' + (n > 1 ? 's' : '') + ' selectionnee' + (n > 1 ? 's' : '') : '';
    },

    setTsUrgence: function(val) {
        window._tsUrgence = val;
        ['normal', 'urgent', 'critique'].forEach(function(u) {
            var btn = document.getElementById('ts-urg-' + u);
            if (!btn) return;
            if (u === val) {
                var colors = { normal: 'var(--green)', urgent: 'var(--amber)', critique: 'var(--red)' };
                btn.style.borderColor = colors[u];
                btn.style.color = colors[u];
            } else {
                btn.style.borderColor = '#444';
                btn.style.color = '#888';
            }
        });
    },

    envoyerDemandeTravauxSupp: function(rdvId) {
        var selected = window._tsSelectedPrestations || [];
        var desc = (document.getElementById('travaux-supp-desc') || {}).value || '';
        if (!selected.length && !desc.trim()) { alert('Selectionnez au moins une prestation ou decrivez le probleme'); return; }
        var data = {
            prestations_demandees: selected,
            description: desc,
            urgence: window._tsUrgence || 'normal'
        };
        apiPost('/api/rendez-vous/' + rdvId + '/travaux-supplementaires', data).then(function(r) {
            return r.json();
        }).then(function() {
            closeModal();
            window.OrModule.showNotificationToast('Demande envoyee au receptionniste');
            refreshCurrentSection();
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    pollTravauxSupp: function() {
        var role = APP.currentUser ? APP.currentUser.role : '';
        if (role !== 'admin' && role !== 'receptionnaire') return;

        apiGet('/api/travaux-supplementaires/en-attente').then(function(r) { return r.json(); }).then(function(demandes) {
            var count = Array.isArray(demandes) ? demandes.length : 0;
            var badge = document.getElementById('travaux-supp-badge');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'flex';
                    badge.style.animation = 'pulse-badge 1s infinite';
                } else {
                    badge.style.display = 'none';
                    badge.style.animation = '';
                }
            }
            if (count > APP._lastTravauxSuppCount && APP._lastTravauxSuppCount >= 0) {
                var newDemandes = demandes.slice(0, count - APP._lastTravauxSuppCount);
                newDemandes.forEach(function(d) {
                    window.OrModule.showTravauxSuppAlert(d);
                });
                window.OrModule.playAlertSound();
            }
            APP._lastTravauxSuppCount = count;
            APP._pendingTravauxSupp = demandes;
        }).catch(function(e) {
            if (e && /403|401/.test(String(e.message || ''))) return;
        });
    },

    playAlertSound: function() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 800;
            osc.type = 'sine';
            gain.gain.value = 0.3;
            osc.start();
            osc.stop(ctx.currentTime + 0.15);
            setTimeout(function() {
                var osc2 = ctx.createOscillator();
                var gain2 = ctx.createGain();
                osc2.connect(gain2);
                gain2.connect(ctx.destination);
                osc2.frequency.value = 1000;
                osc2.type = 'sine';
                gain2.gain.value = 0.3;
                osc2.start();
                osc2.stop(ctx.currentTime + 0.2);
            }, 200);
        } catch (e) {}
    },

    showTravauxSuppAlert: function(demande) {
        var d = demande;
        var c = d.client || {};
        var v = d.vehicule || {};
        var prestas = d.prestations_demandees || [];
        var overlay = document.createElement('div');
        overlay.className = 'travaux-alert-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.75);display:flex;align-items:center;justify-content:center;z-index:10000;animation:fadeIn .2s;padding:16px';

        var prestaHtml = '';
        if (prestas.length) {
            prestaHtml = '<div style="margin-bottom:12px"><div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;font-weight:700">Prestations demandees par le technicien</div>';
            prestas.forEach(function(p) {
                var presta = (APP.prestationsConfig || []).find(function(x) { return x.id === p.prestation_id; });
                var prix = presta ? (presta.prix_base_ttc || 0) : 0;
                var temps = presta ? (presta.temps_estime_minutes || 0) : 0;
                prestaHtml += '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;background:#1a1a22;border:1px solid #333;border-radius:6px;margin-bottom:4px">' +
                    '<div><div style="font-size:13px;color:#eee;font-weight:500">' + escapeHtml(p.nom || '') + '</div>' +
                    '<div style="font-size:11px;color:#777">' + escapeHtml(p.code || '') + (temps ? ' • ~' + temps + ' min' : '') + '</div></div>' +
                    '<div style="font-size:14px;font-weight:700;color:var(--orange)">' + (prix > 0 ? prix.toFixed(2) + ' €' : '-') + '</div></div>';
            });
            prestaHtml += '</div>';
        }

        overlay.innerHTML =
            '<div style="background:#1e1e22;border:2px solid #E8480A;border-radius:16px;padding:24px;width:560px;max-width:95vw;color:#eee;box-shadow:0 20px 60px rgba(232,72,10,.3);max-height:90vh;overflow-y:auto">' +
                '<div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">' +
                    '<div style="width:40px;height:40px;border-radius:50%;background:rgba(232,72,10,.15);display:flex;align-items:center;justify-content:center;font-size:20px">&#9888;</div>' +
                    '<div><div style="font-family:Barlow Condensed,sans-serif;font-size:20px;font-weight:700">Demande travaux supplementaires</div>' +
                    '<div style="font-size:12px;color:#888">' + (escapeHtml(d.or_numero) || 'OR #' + d.rendez_vous_id) + '</div></div>' +
                    '<span class="badge ' + (d.urgence === 'critique' ? 'red' : d.urgence === 'urgent' ? 'amber' : 'blue') + '" style="font-size:12px;padding:3px 12px;margin-left:auto">' + (d.urgence || 'normal') + '</span>' +
                '</div>' +
                '<div style="display:flex;gap:12px;font-size:13px;color:#aaa;margin-bottom:14px;flex-wrap:wrap">' +
                    '<div>&#128100; ' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + '</div>' +
                    '<div>&#128690; ' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div>' +
                '</div>' +
                prestaHtml +
                (d.description ? '<div style="background:#16161a;border-radius:8px;padding:12px;margin-bottom:14px;font-size:13px;color:#ddd;line-height:1.5"><div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;font-weight:700">Notes du technicien</div>' + escapeHtml(d.description) + '</div>' : '') +
                '<div style="background:#16161a;border-radius:8px;padding:14px;margin-bottom:14px">' +
                    '<div style="font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;font-weight:700">Devis receptionniste</div>' +
                    '<div style="display:flex;gap:10px;margin-bottom:10px">' +
                        '<div style="flex:1"><label style="font-size:12px;color:#888;display:block;margin-bottom:3px">Prix TTC</label>' +
                        '<input type="number" id="ts-devis-prix-' + d.id + '" class="form-input" step="0.01" placeholder="0.00" style="font-size:16px;padding:10px"></div>' +
                        '<div style="flex:1"><label style="font-size:12px;color:#888;display:block;margin-bottom:3px">Temps (min)</label>' +
                        '<input type="number" id="ts-devis-temps-' + d.id + '" class="form-input" placeholder="60" style="font-size:16px;padding:10px"></div>' +
                    '</div>' +
                    '<label style="font-size:12px;color:#888;display:block;margin-bottom:3px">Notes pour le client</label>' +
                    '<input type="text" id="travaux-alert-notes-' + d.id + '" class="form-input" placeholder="Explication pour le client..." style="font-size:14px;padding:10px">' +
                '</div>' +
                '<div style="display:flex;gap:10px">' +
                    '<button onclick="traiterAlertTravaux(' + d.id + ', \'approuve\', this)" style="flex:1;padding:14px;background:#22C55E;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:15px;cursor:pointer;font-family:Barlow,sans-serif">Faire signer le client</button>' +
                    '<button onclick="traiterAlertTravaux(' + d.id + ', \'refuse\', this)" style="flex:1;padding:14px;background:#EF4444;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:15px;cursor:pointer;font-family:Barlow,sans-serif">Refuser</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(overlay);
    },

    traiterAlertTravaux: function(demandeId, statut, btn) {
        var notes = document.getElementById('travaux-alert-notes-' + demandeId);
        var notesVal = notes ? notes.value : '';
        var prixEl = document.getElementById('ts-devis-prix-' + demandeId);
        var tempsEl = document.getElementById('ts-devis-temps-' + demandeId);
        var prixDevis = prixEl ? parseFloat(prixEl.value) || null : null;
        var tempsDevis = tempsEl ? parseInt(tempsEl.value, 10) || null : null;
        btn.disabled = true;
        btn.textContent = '...';
        if (statut === 'approuve') {
            var overlay = btn.closest('.travaux-alert-overlay');
            if (overlay) overlay.remove();
            window.OrModule.ouvrirSignatureTravauxSupp(demandeId, notesVal, prixDevis, tempsDevis);
        } else {
            apiPut('/api/travaux-supplementaires/' + demandeId, { statut: statut, notes_receptionniste: notesVal }).then(function() {
                var overlayRef = btn.closest('.travaux-alert-overlay');
                if (overlayRef) overlayRef.remove();
                window.OrModule.showNotificationToast('Demande refusee');
                window.OrModule.pollTravauxSupp();
                setTimeout(function() { window.OrModule.renderTravauxSuppPanel(); }, 500);
            }).catch(function(e) {
                btn.disabled = false;
                btn.textContent = 'Refuser';
                alert('Erreur: ' + e.message);
            });
        }
    },

    ouvrirSignatureTravauxSupp: function(demandeId, notes, prixDevis, tempsDevis) {
        var safeNotes = (notes || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        var html = '<div style="margin-bottom:16px;font-size:13px;color:#aaa">Le client doit signer pour approuver les travaux supplementaires.</div>' +
            '<div class="form-group"><label class="form-label" style="color:#ccc">Signature client *</label>' +
            '<canvas id="ts-signature-canvas" width="400" height="150" style="border:1px solid #444;border-radius:6px;background:#fff;cursor:crosshair;display:block;width:100%;touch-action:none"></canvas>' +
            '<button class="btn btn-ghost" style="margin-top:6px;font-size:11px" onclick="clearTsSignature()">Effacer signature</button></div>' +
            '<button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="confirmerTravauxSuppAvecSignature(' + demandeId + ',' + (prixDevis || 'null') + ',' + (tempsDevis || 'null') + ',\'' + safeNotes + '\')">Confirmer et approuver</button>';
        showModal('Signature client - Travaux supplementaires', html, '480px');
        setTimeout(function() { window.OrModule.initTsSignaturePad(); }, 100);
    },

    initTsSignaturePad: function() {
        var canvas = document.getElementById('ts-signature-canvas');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, rect.width, rect.height);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        _tsSignatureCtx = ctx;
        _tsSignatureHasData = false;
        canvas.addEventListener('mousedown', function(e) { _tsSignatureDrawing = true; ctx.beginPath(); ctx.moveTo(e.offsetX, e.offsetY); });
        canvas.addEventListener('mousemove', function(e) { if (!_tsSignatureDrawing) return; ctx.lineTo(e.offsetX, e.offsetY); ctx.stroke(); _tsSignatureHasData = true; });
        canvas.addEventListener('mouseup', function() { _tsSignatureDrawing = false; });
        canvas.addEventListener('mouseout', function() { _tsSignatureDrawing = false; });
        canvas.addEventListener('touchstart', function(e) { e.preventDefault(); _tsSignatureDrawing = true; var t = e.touches[0]; var r = canvas.getBoundingClientRect(); ctx.beginPath(); ctx.moveTo(t.clientX - r.left, t.clientY - r.top); });
        canvas.addEventListener('touchmove', function(e) { e.preventDefault(); if (!_tsSignatureDrawing) return; var t = e.touches[0]; var r = canvas.getBoundingClientRect(); ctx.lineTo(t.clientX - r.left, t.clientY - r.top); ctx.stroke(); _tsSignatureHasData = true; });
        canvas.addEventListener('touchend', function() { _tsSignatureDrawing = false; });
    },

    clearTsSignature: function() {
        var canvas = document.getElementById('ts-signature-canvas');
        if (!canvas || !_tsSignatureCtx) return;
        var rect = canvas.getBoundingClientRect();
        _tsSignatureCtx.fillStyle = '#ffffff';
        _tsSignatureCtx.fillRect(0, 0, rect.width, rect.height);
        _tsSignatureHasData = false;
    },

    confirmerTravauxSuppAvecSignature: function(demandeId, prixDevis, tempsDevis, notes) {
        if (!_tsSignatureHasData) { alert('Signature client obligatoire'); return; }
        var canvas = document.getElementById('ts-signature-canvas');
        var signatureData = canvas ? canvas.toDataURL('image/png') : null;

        apiPut('/api/travaux-supplementaires/' + demandeId, {
            statut: 'approuve',
            notes_receptionniste: notes,
            prix_estime: prixDevis,
            temps_estime: tempsDevis,
            signature: signatureData
        }).then(function() {
            closeModal();
            window.OrModule.showNotificationToast('Travaux approuves avec signature - OR supplementaire cree');
            window.OrModule.pollTravauxSupp();
            setTimeout(function() { window.OrModule.renderTravauxSuppPanel(); }, 500);
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    showNotificationToast: function(message) {
        showToast(message, 'success');
        updateLiveRegion(message);
    },

    renderTravauxSuppPanel: function() {
        var container = document.getElementById('travaux-supp-panel');
        if (!container) return;
        var demandes = APP._pendingTravauxSupp || [];
        if (demandes.length === 0) {
            container.innerHTML = '';
            container.style.display = 'none';
            return;
        }
        container.style.display = 'block';
        var html = '<div style="font-size:14px;font-weight:600;color:var(--orange);margin-bottom:12px">Demandes de travaux en attente (' + demandes.length + ')</div>';
        demandes.forEach(function(d) {
            var c = d.client || {};
            var v = d.vehicule || {};
            var urgCls = d.urgence === 'critique' ? ' critique' : '';
            html += '<div class="travaux-supp-card' + urgCls + '">'
                + '<div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">'
                + '<div><span class="badge ' + (escapeHtml(d.urgence) === 'critique' ? 'red' : d.urgence === 'urgent' ? 'amber' : 'blue') + '">' + d.urgence + '</span> '
                + '<span style="font-size:13px;color:var(--orange);font-weight:600">' + (escapeHtml(d.or_numero) || '') + '</span></div>'
                + '<span style="font-size:11px;color:#666">' + (d.created_at || '').substring(0, 16).replace('T', ' ') + '</span></div>'
                + '<div style="font-size:13px;color:#eee;margin-bottom:8px">' + (escapeHtml(d.description) || '') + '</div>'
                + '<div style="display:flex;gap:12px;font-size:12px;color:#888;margin-bottom:10px">'
                + '<span>' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + '</span>'
                + '<span>' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</span>';
            if (d.temps_estime) html += '<span>' + d.temps_estime + ' min</span>';
            if (d.prix_estime) html += '<span>' + d.prix_estime + ' EUR</span>';
            html += '</div>'
                + '<div style="display:flex;gap:8px">'
                + '<button class="btn btn-primary" style="font-size:11px;padding:4px 10px;background:var(--green)" onclick="approuverTravauxSupp(' + d.id + ')">Approuver</button>'
                + '<button class="btn btn-ghost" style="font-size:11px;padding:4px 10px;color:var(--red)" onclick="refuserTravauxSupp(' + d.id + ')">Refuser</button>'
                + '</div></div>';
        });
        container.innerHTML = html;
    },

    approuverTravauxSupp: function(demandeId) {
        var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Notes pour le client (optionnel)</label>' +
            '<textarea id="travaux-supp-notes" class="form-input" rows="3" placeholder="Notes..."></textarea></div>' +
            '<div style="display:flex;gap:8px;margin-top:10px">' +
            '<button class="btn btn-ghost" style="flex:1" onclick="closeModal()">Annuler</button>' +
            '<button class="btn btn-primary" style="flex:1;background:var(--green)" onclick="confirmerApprouverTravauxSupp(' + demandeId + ')">Approuver</button>' +
            '</div>';
        showModal('Approuver travaux supplementaires', html, '450px');
    },

    confirmerApprouverTravauxSupp: function(demandeId) {
        var notesEl = document.getElementById('travaux-supp-notes');
        var notes = notesEl ? notesEl.value : '';
        apiPut('/api/travaux-supplementaires/' + demandeId, { statut: 'approuve', notes_receptionniste: notes || null }).then(function(r) {
            return r.json();
        }).then(function() {
            closeModal();
            window.OrModule.showNotificationToast('Travaux supplementaires approuves - OR supplementaire cree');
            window.OrModule.pollTravauxSupp();
            setTimeout(function() { window.OrModule.renderTravauxSuppPanel(); }, 500);
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    refuserTravauxSupp: function(demandeId) {
        var html = '<div class="form-group"><label class="form-label" style="color:#ccc">Raison du refus</label>' +
            '<textarea id="travaux-supp-refus" class="form-input" rows="3" placeholder="Saisir une raison..."></textarea></div>' +
            '<div style="display:flex;gap:8px;margin-top:10px">' +
            '<button class="btn btn-ghost" style="flex:1" onclick="closeModal()">Annuler</button>' +
            '<button class="btn btn-primary" style="flex:1;background:var(--red)" onclick="confirmerRefuserTravauxSupp(' + demandeId + ')">Refuser</button>' +
            '</div>';
        showModal('Refuser travaux supplementaires', html, '450px');
    },

    confirmerRefuserTravauxSupp: function(demandeId) {
        var notesEl = document.getElementById('travaux-supp-refus');
        var notes = notesEl ? notesEl.value : '';
        if (!notes || !notes.trim()) {
            showAlert('Veuillez saisir une raison de refus', 'warning');
            return;
        }
        apiPut('/api/travaux-supplementaires/' + demandeId, { statut: 'refuse', notes_receptionniste: notes || null }).then(function(r) {
            return r.json();
        }).then(function() {
            closeModal();
            window.OrModule.showNotificationToast('Demande refusee');
            window.OrModule.pollTravauxSupp();
            setTimeout(function() { window.OrModule.renderTravauxSuppPanel(); }, 500);
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    }
};
