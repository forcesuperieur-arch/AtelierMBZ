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
var _receptionDamagePoints = [];
var _receptionDamagePhotos = [];
var _tsSignatureCtx = null;
var _tsSignatureDrawing = false;
var _tsSignatureHasData = false;

window.OrModule = window.OrModule || {
    telechargerOR: function(rdvId, orId) {
        var url = orId
            ? ('/api/ordres-reparation/' + orId + '/pdf')
            : ('/api/rendez-vous/' + rdvId + '/ordre-reparation');
        var fileName = 'ordre-reparation-' + (orId || rdvId || 'document') + '.pdf';
        return openProtectedDocument(url, fileName).catch(function(error) {
            console.error('Erreur ouverture PDF OR:', error);
            showAlert("Impossible d'ouvrir le PDF de l'ordre de réparation.", 'error');
            throw error;
        });
    },

    loadOrdresReparation: function() {
        apiGet('/api/rendez-vous').then(function(r) { return r.json(); }).then(function(rdvs) {
            window.OrModule.renderOrdresReparation(rdvs);
        }).catch(function(e) { console.error('Erreur OR:', e); });
        window.OrModule.pollTravauxSupp();
        setTimeout(function() { window.OrModule.renderTravauxSuppPanel(); }, 500);
    },

    getOrNumber: function(rdv) {
        if (!rdv) return 'OR-0000-000';
        var year = rdv.date_rdv ? String(rdv.date_rdv).substring(0, 4) : String(new Date().getFullYear());
        return 'OR-' + year + '-' + String(rdv.id || 0).padStart(3, '0');
    },

    getOrStatusMeta: function(statut) {
        var map = {
            reserve: { label: 'A planifier', tone: '#f59e0b', soft: 'rgba(245,158,11,.14)' },
            confirme: { label: 'Confirme', tone: '#3b82f6', soft: 'rgba(59,130,246,.14)' },
            reception: { label: 'Reception', tone: '#14b8a6', soft: 'rgba(20,184,166,.14)' },
            en_cours: { label: 'En atelier', tone: '#f97316', soft: 'rgba(249,115,22,.15)' },
            en_attente: { label: 'En attente', tone: '#f59e0b', soft: 'rgba(245,158,11,.14)' },
            termine: { label: 'Termine', tone: '#22c55e', soft: 'rgba(34,197,94,.14)' },
            facture: { label: 'Facture', tone: '#8b5cf6', soft: 'rgba(139,92,246,.16)' },
            paye: { label: 'Paye', tone: '#16a34a', soft: 'rgba(22,163,74,.14)' },
            annule: { label: 'Annule', tone: '#ef4444', soft: 'rgba(239,68,68,.14)' },
            non_presente: { label: 'Non presente', tone: '#ef4444', soft: 'rgba(239,68,68,.14)' }
        };
        return map[statut] || { label: statut || 'En attente', tone: '#64748b', soft: 'rgba(100,116,139,.14)' };
    },

    formatEuro: function(value) {
        var num = parseFloat(value);
        if (isNaN(num)) return 'A chiffrer';
        return num.toFixed(2).replace('.', ',') + ' €';
    },

    getLatestOrdreInfo: function(rdv) {
        var list = Array.isArray(rdv && rdv.ordres_reparation) ? rdv.ordres_reparation.slice() : [];
        if (!list.length) return null;
        list.sort(function(a, b) {
            return new Date(b.created_at || 0) - new Date(a.created_at || 0);
        });
        for (var i = 0; i < list.length; i++) {
            if (list[i].type_or === 'initial') return list[i];
        }
        return list[0];
    },

    parseEtatVehicule: function(raw) {
        var out = {
            points: [],
            pointKeys: [],
            observations: '',
            priority: 'standard',
            fuel_level: null,
            body_damages: [],
            schema_notes: '',
            estimate_rows: [],
            photos: []
        };
        if (!raw) return out;
        try {
            var data = typeof raw === 'string' ? JSON.parse(raw) : raw;
            if (data && Array.isArray(data.points)) {
                out.pointKeys = data.points.slice().map(function(pt) {
                    return typeof pt === 'string' ? pt : (pt && (pt.key || pt.code || pt.label || pt.nom) ? (pt.key || pt.code || pt.label || pt.nom) : '');
                }).filter(Boolean);
                out.points = data.points.map(function(pt) {
                    if (typeof pt === 'string') {
                        var match = ETAT_VEHICULE_POINTS.find(function(item) { return item.key === pt; });
                        return match ? match.label : pt;
                    }
                    return pt && (pt.label || pt.nom || pt.key || pt.code) ? (pt.label || pt.nom || pt.key || pt.code) : '';
                }).filter(Boolean);
            }
            out.observations = data && data.observations ? String(data.observations) : '';
            out.priority = (data && (data.priority || data.priorite)) ? String(data.priority || data.priorite) : 'standard';
            if (data && (data.fuel_level !== undefined || data.niveau_carburant !== undefined)) {
                out.fuel_level = parseInt(data.fuel_level !== undefined ? data.fuel_level : data.niveau_carburant, 10);
                if (isNaN(out.fuel_level)) out.fuel_level = null;
            }
            out.body_damages = data && Array.isArray(data.body_damages) ? data.body_damages.slice() : [];
            out.schema_notes = data && data.schema_notes ? String(data.schema_notes) : '';
            out.estimate_rows = data && Array.isArray(data.estimate_rows) ? data.estimate_rows.slice() : [];
            out.photos = data && Array.isArray(data.photos) ? data.photos.slice() : [];
            return out;
        } catch (e) {
            out.observations = String(raw || '');
            return out;
        }
    },

    renderFuelGauge: function(level) {
        var normalized = parseInt(level, 10);
        var html = '<div style="display:flex;align-items:center;gap:4px">';
        for (var i = 1; i <= 4; i++) {
            var filled = !isNaN(normalized) && i <= normalized;
            html += '<span style="width:20px;height:12px;border:1px solid #cbd5e1;border-radius:3px;display:inline-block;background:' + (filled ? '#f59e0b' : '#fff') + '"></span>';
        }
        html += '<span style="font-size:10px;font-weight:700;color:#64748b;margin-left:4px">' + (!isNaN(normalized) ? normalized + '/4' : 'NR') + '</span></div>';
        return html;
    },

    buildOrEstimateRows: function(rdv, etatMeta) {
        var rows = [];
        if (etatMeta && Array.isArray(etatMeta.estimate_rows) && etatMeta.estimate_rows.length) {
            rows = etatMeta.estimate_rows.map(function(row) {
                return {
                    label: row.label || row.designation || 'Operation atelier',
                    qty: row.qty || row.quantite || 1,
                    amount: row.amount != null ? row.amount : (row.montant != null ? row.montant : null)
                };
            }).filter(function(row) { return row.label; });
        }
        if (rows.length) return rows;
        if (rdv && (rdv.type_intervention || rdv.prix_estime || rdv.prix_final)) {
            rows.push({
                label: rdv.type_intervention || 'Intervention atelier',
                qty: 1,
                amount: rdv.prix_final != null ? rdv.prix_final : rdv.prix_estime
            });
        }
        (rdv.ordres_reparation || []).forEach(function(orItem) {
            if (orItem.type_or !== 'supplementaire') return;
            rows.push({
                label: orItem.travaux || 'Travaux supplementaires',
                qty: 1,
                amount: orItem.montant_estime != null ? orItem.montant_estime : (orItem.prix_estime != null ? orItem.prix_estime : null)
            });
        });
        if (!rows.length) rows.push({ label: 'Diagnostic atelier', qty: 1, amount: null });
        return rows;
    },

    getOrSheetHtml: function(rdv, options) {
        options = options || {};
        var compact = !!options.compact;
        var outerPad = compact ? 18 : 28;
        var v = rdv.vehicule || {};
        var c = rdv.client || {};
        var meca = rdv.mecanicien || APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
        var pont = rdv.pont || APP.ponts.find(function(p) { return p.id === rdv.pont_id; });
        var orMeta = window.OrModule.getOrStatusMeta(rdv.statut);
        var orInfo = window.OrModule.getLatestOrdreInfo(rdv) || {};
        var etat = window.OrModule.parseEtatVehicule(orInfo.etat_vehicule || rdv.etat_vehicule);
        var observations = orInfo.travaux || rdv.notes || rdv.commentaire || etat.observations || '';
        var rows = window.OrModule.buildOrEstimateRows(rdv, etat);
        var clientName = escapeHtml(((c.prenom || '') + ' ' + (c.nom || '')).trim() || '-');
        var motoName = escapeHtml(((v.marque || '') + ' ' + (v.modele || '')).trim() || '-');
        var atelierName = escapeHtml((rdv.atelier && (rdv.atelier.nom || rdv.atelier.name)) || (APP.currentUser && APP.currentUser.atelier_nom) || 'Atelier Moto Pro');
        var immat = escapeHtml(v.plaque || '-');
        var km = rdv.kilometrage || orInfo.kilometrage || '-';
        var serial = escapeHtml(v.numero_serie || v.vin || '-');
        var orNum = window.OrModule.getOrNumber(rdv);
        var totalFromRows = rows.reduce(function(sum, row) {
            var amount = parseFloat(row.amount);
            return isNaN(amount) ? sum : (sum + amount * (parseFloat(row.qty) || 1));
        }, 0);
        var totalTtc = totalFromRows || (rdv.prix_final != null ? rdv.prix_final : rdv.prix_estime);
        var totalDisplay = window.OrModule.formatEuro(totalTtc);
        var dateLabel = rdv.date_rdv || '-';
        var hourLabel = formatTime(rdv.heure_rdv || '') || '--:--';
        var duree = rdv.temps_estime ? Math.round(rdv.temps_estime / 60 * 10) / 10 + ' h' : 'A estimer';
        var priorityMap = { basse: 'Basse', low: 'Basse', standard: 'Standard', normal: 'Standard', urgent: 'Flash / Urgent', critique: 'Critique' };
        var priorityKey = String(etat.priority || '').toLowerCase();
        var priorityLabel = priorityMap[priorityKey] || (rdv.statut === 'en_cours' ? 'Flash / Urgent' : (rdv.statut === 'reserve' ? 'Basse' : 'Standard'));
        var fuelLevel = etat.fuel_level;
        var pointsHtml = etat.points.length ? etat.points.map(function(item) {
            return '<span style="display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:11px;font-weight:700">✓ ' + escapeHtml(item) + '</span>';
        }).join('') : '<span style="font-size:12px;color:#64748b">Reception a completer lors de la prise en charge.</span>';
        var damageLabels = {
            avant: 'Avant', reservoir: 'Reservoir', flanc_gauche: 'Flanc gauche', flanc_droit: 'Flanc droit', arriere: 'Arriere', roue_av: 'Roue AV', roue_ar: 'Roue AR', selle: 'Selle'
        };
        var damagesHtml = etat.body_damages.length ? etat.body_damages.map(function(key) {
            return '<span style="display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;background:#fee2e2;color:#b91c1c;font-size:10px;font-weight:800">' + escapeHtml(damageLabels[key] || key) + '</span>';
        }).join('') : '<span style="font-size:11px;color:#94a3b8">Aucun impact note.</span>';
        var schemaNotesHtml = etat.schema_notes ? '<div style="margin-top:8px;font-size:11px;color:#7c2d12"><strong>Notes schema :</strong> ' + escapeHtml(etat.schema_notes) + '</div>' : '';
        var photos = Array.isArray(rdv.photos_etat) ? rdv.photos_etat : (etat.photos || []);
        var photosHtml = photos.length ? '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">' + photos.slice(0, 4).map(function(src) {
            return '<img src="' + src + '" alt="Photo etat" style="width:74px;height:74px;object-fit:cover;border-radius:10px;border:1px solid #cbd5e1;background:#fff">';
        }).join('') + '</div>' : '';
        var notesHtml = escapeHtml(observations || 'Notez ici les bruits, fuites ou comportements anormaux signales par le client.').replace(/\n/g, '<br>');
        var sigClient = orInfo.signature_client ? '<img src="' + orInfo.signature_client + '" alt="Signature client" style="max-width:100%;max-height:72px;object-fit:contain">' : '<div style="height:72px"></div>';
        var rowHtml = rows.map(function(row) {
            return '<tr>' +
                '<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:600">' + escapeHtml(row.label || '-') + '</td>' +
                '<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;text-align:center;color:#334155">' + (row.qty || 1) + '</td>' +
                '<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;text-align:right;color:#0f172a;font-weight:700">' + window.OrModule.formatEuro(row.amount) + '</td>' +
            '</tr>';
        }).join('');

        return '<div style="font-family:Inter,Segoe UI,Arial,sans-serif;background:#ffffff;color:#0f172a;border:1px solid #e2e8f0;border-radius:26px;overflow:hidden;box-shadow:0 18px 40px rgba(15,23,42,.12)">' +
            '<div style="background:#0f172a;color:#ffffff;padding:' + outerPad + 'px;position:relative;overflow:hidden">' +
                '<div style="position:absolute;top:-60px;right:-40px;width:180px;height:180px;border-radius:999px;background:rgba(249,115,22,.12)"></div>' +
                '<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;position:relative">' +
                    '<div>' +
                        '<div style="font-size:11px;letter-spacing:.28em;text-transform:uppercase;font-weight:800;color:#94a3b8">Engineering & Performance Division</div>' +
                        '<div style="font-size:' + (compact ? 28 : 34) + 'px;font-weight:900;line-height:1.05;letter-spacing:-.04em;margin-top:6px">PRO <span style="color:#f97316">MOTO</span> WORKSHOP</div>' +
                        '<div style="font-size:12px;color:#cbd5e1;margin-top:8px">' + atelierName + '</div>' +
                    '</div>' +
                    '<div style="min-width:220px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:18px;padding:14px 16px;text-align:right">' +
                        '<div style="font-size:11px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#fb923c">Ordre de reparation</div>' +
                        '<div style="font-size:28px;font-weight:900;letter-spacing:-.04em;margin-top:4px">' + orNum + '</div>' +
                        '<div style="font-size:11px;color:#cbd5e1;margin-top:6px">Date: ' + escapeHtml(dateLabel) + ' • Heure: ' + escapeHtml(hourLabel) + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div style="padding:' + outerPad + 'px;display:grid;gap:18px">' +
                '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px">' +
                    '<div style="border-left:4px solid #f97316;padding-left:12px">' +
                        '<div style="font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#0f172a">Fiche client</div>' +
                        '<div style="display:grid;gap:8px;margin-top:12px;font-size:13px;color:#334155">' +
                            '<div><strong>Nom :</strong> ' + clientName + '</div>' +
                            '<div><strong>Mobile :</strong> ' + escapeHtml(c.telephone || '-') + '</div>' +
                            '<div><strong>Email :</strong> ' + escapeHtml(c.email || '-') + '</div>' +
                            '<div><strong>Adresse :</strong> ' + escapeHtml(c.adresse || '-') + '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div style="border-left:4px solid #0f172a;padding-left:12px">' +
                        '<div style="font-size:11px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#0f172a">Donnees machine</div>' +
                        '<div style="display:grid;gap:8px;margin-top:12px;font-size:13px;color:#334155">' +
                            '<div style="display:flex;justify-content:space-between;gap:10px"><span><strong>Modele :</strong> ' + motoName + '</span><span><strong>Annee :</strong> ' + escapeHtml(v.annee || '-') + '</span></div>' +
                            '<div style="display:flex;justify-content:space-between;gap:10px"><span><strong>Immat :</strong> ' + immat + '</span><span><strong>Kilometrage :</strong> ' + escapeHtml(String(km)) + '</span></div>' +
                            '<div><strong>N° de serie :</strong> ' + serial + '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px 16px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">' +
                    '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><span style="font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#64748b">Priorite :</span><span style="padding:6px 10px;border-radius:999px;background:' + orMeta.soft + ';color:' + orMeta.tone + ';font-size:11px;font-weight:800">' + priorityLabel + '</span><span style="padding:6px 10px;border-radius:999px;background:' + orMeta.soft + ';color:' + orMeta.tone + ';font-size:11px;font-weight:800">' + orMeta.label + '</span></div>' +
                    '<div style="display:flex;align-items:center;gap:10px"><span style="font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#64748b">Niveau carburant :</span>' + window.OrModule.renderFuelGauge(fuelLevel) + '</div>' +
                '</div>' +
                '<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:18px">' +
                    '<div>' +
                        '<div style="font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;color:#ea580c;border-bottom:1px solid #fed7aa;padding-bottom:8px">Interventions programmees</div>' +
                        '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:12px">' +
                            rows.map(function(row) {
                                return '<div style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;color:#334155"><span style="width:18px;height:18px;border-radius:4px;border:2px solid #0f172a;display:inline-flex;align-items:center;justify-content:center;font-size:11px;background:#fff">✓</span>' + escapeHtml(row.label || '-') + '</div>';
                            }).join('') +
                            '<div style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;color:#334155"><span style="width:18px;height:18px;border-radius:4px;border:2px solid #0f172a;display:inline-flex;align-items:center;justify-content:center;font-size:11px;background:#fff">' + (etat.points.length ? '✓' : '') + '</span> Controle reception</div>' +
                            '<div style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;color:#334155"><span style="width:18px;height:18px;border-radius:4px;border:2px solid #0f172a;display:inline-flex;align-items:center;justify-content:center;font-size:11px;background:#fff">' + ((rdv.ordres_reparation || []).some(function(orItem) { return orItem.type_or === 'supplementaire'; }) ? '✓' : '') + '</span> Travaux supplementaires</div>' +
                        '</div>' +
                        '<div style="margin-top:16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:12px">' +
                            '<div style="font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#9a3412;margin-bottom:8px">Controle & observations</div>' +
                            '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">' + pointsHtml + '</div>' +
                            '<div style="min-height:98px;border:2px dashed #fdba74;border-radius:12px;padding:12px;background:rgba(255,255,255,.55);font-size:13px;color:#7c2d12;line-height:1.5">' + notesHtml + '</div>' +
                            schemaNotesHtml +
                        '</div>' +
                    '</div>' +
                    '<div>' +
                        '<div style="font-size:11px;font-weight:900;letter-spacing:.16em;text-transform:uppercase;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:8px;text-align:right">Controle carrosserie</div>' +
                        '<div style="background:#ffffff;border:2px solid #e2e8f0;border-radius:22px;box-shadow:inset 0 1px 12px rgba(15,23,42,.04);height:240px;position:relative;overflow:hidden;margin-top:12px">' +
                            '<div style="position:absolute;top:10px;right:14px;font-size:9px;font-weight:800;color:#94a3b8;letter-spacing:.08em;text-transform:uppercase">Vue profil</div>' +
                            '<svg viewBox="0 0 512 320" style="width:100%;height:100%;display:block" fill="none" stroke="#334155" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' +
                                '<circle cx="110" cy="230" r="65"></circle><circle cx="110" cy="230" r="55" stroke-dasharray="4 4"></circle><circle cx="400" cy="230" r="65"></circle><circle cx="400" cy="230" r="55" stroke-dasharray="4 4"></circle><circle cx="110" cy="230" r="30" opacity=".5"></circle><path d="M110 230 L180 80 L200 85 L130 235 Z"></path><path d="M180 120 L300 120 L350 180 L350 230 L220 230 Z"></path><rect x="220" y="160" width="80" height="60" rx="5"></rect><path d="M180 120 Q220 70 320 90 Q340 100 340 130 L300 130 Z" fill="#f8fafc"></path><path d="M340 130 Q380 120 440 140 L430 160 Q380 150 340 160 Z" fill="#f8fafc"></path><path d="M300 200 L400 230" stroke-width="3"></path><path d="M250 220 L380 260 L420 240" stroke-width="4"></path><path d="M180 80 L160 70 L200 65"></path><circle cx="165" cy="100" r="12"></circle></svg>' +
                        '</div>' +
                        '<div style="margin-top:10px;background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:12px;font-size:11px;color:#9a3412;font-weight:700;line-height:1.45">Marquer ici les impacts, rayures ou fissures constates avant intervention pour fiabiliser la reception.<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">' + damagesHtml + '</div>' + photosHtml + '</div>' +
                    '</div>' +
                '</div>' +
                '<div style="overflow:hidden;border:2px solid #0f172a;border-radius:18px">' +
                    '<table style="width:100%;border-collapse:collapse;font-size:12px;background:#fff">' +
                        '<thead style="background:#0f172a;color:#fff;text-transform:uppercase;letter-spacing:.12em">' +
                            '<tr><th style="padding:12px 14px;text-align:left">Operation / Reference</th><th style="padding:12px 14px;text-align:center;width:72px">Qte</th><th style="padding:12px 14px;text-align:right;width:150px">Montant est.</th></tr>' +
                        '</thead>' +
                        '<tbody>' + rowHtml +
                            '<tr style="background:#0f172a;color:#fff"><td colspan="2" style="padding:12px 14px;text-align:right;font-size:11px;font-weight:900;text-transform:uppercase">Total estimatif TTC</td><td style="padding:12px 14px;text-align:right;font-size:18px;font-weight:900;color:#fb923c">' + totalDisplay + '</td></tr>' +
                        '</tbody>' +
                    '</table>' +
                '</div>' +
                '<div style="display:grid;grid-template-columns:1.1fr 1fr;gap:18px;align-items:stretch">' +
                    '<div style="font-size:11px;color:#475569;line-height:1.55">' +
                        '<div style="font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#0f172a;border-bottom:1px solid #e2e8f0;padding-bottom:8px;margin-bottom:10px">Engagement & decharge</div>' +
                        '<div style="display:grid;gap:6px"><div>• Le client autorise le diagnostic et les essais necessaires a l’intervention.</div><div>• Les objets personnels doivent etre retires avant remise du vehicule.</div><div>• Toute piece non reclamee peut etre recyclee apres restitution.</div><div>• Mecanicien assigne : <strong>' + escapeHtml(meca ? (meca.prenom + ' ' + meca.nom) : 'Non assigne') + '</strong>' + (pont ? ' • Pont : <strong>' + escapeHtml(pont.nom || '-') + '</strong>' : '') + '</div></div>' +
                    '</div>' +
                    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">' +
                        '<div style="border:2px solid #e2e8f0;border-radius:16px;padding:12px;text-align:center;background:#f8fafc">' +
                            '<div style="font-size:10px;font-weight:900;text-transform:uppercase;color:#94a3b8;margin-bottom:10px">Le client</div>' + sigClient + '<div style="font-size:10px;color:#94a3b8;margin-top:10px">Bon pour accord</div>' +
                        '</div>' +
                        '<div style="border:2px solid #e2e8f0;border-radius:16px;padding:12px;text-align:center;background:#f8fafc">' +
                            '<div style="font-size:10px;font-weight:900;text-transform:uppercase;color:#94a3b8;margin-bottom:10px">Atelier / Expert</div><div style="height:72px"></div><div style="font-size:10px;color:#94a3b8;margin-top:10px">Validation atelier</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div style="background:#0f172a;color:#fff;padding:14px ' + outerPad + 'px;border-top:4px solid #ea580c;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">' +
                '<div><div style="font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase">PRO MOTO SERVICE</div><div style="font-size:10px;color:#94a3b8">SIRET 123 456 789 0001 • APE 4540Z</div></div>' +
                '<div style="display:flex;gap:20px;flex-wrap:wrap;font-size:11px;color:#e2e8f0"><span>📍 12 Rue du Circuit, Le Mans</span><span>📞 02 43 00 00 00</span></div>' +
            '</div>' +
        '</div>';
    },

    openOrPrintWindow: function(rdvId, options) {
        options = options || {};
        var popup = window.open('', '_blank');
        if (!popup) {
            showAlert('Autorisez les pop-ups pour ouvrir la fiche OR.', 'error');
            return;
        }
        popup.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Chargement OR...</title></head><body style="font-family:Arial,sans-serif;padding:24px">Chargement de la fiche OR...</body></html>');
        popup.document.close();

        apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
            var titlePrefix = options.titlePrefix || 'Impression';
            var primaryLabel = options.primaryLabel || 'Imprimer l\'ordre';
            var autoPrint = !!options.autoPrint;
            var printScript = autoPrint
                ? '<script>window.addEventListener("load", function(){ setTimeout(function(){ try { window.focus(); window.print(); } catch(e) {} }, 700); });<\/script>'
                : '';
            var html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' + window.OrModule.getOrNumber(rdv) + '</title>' +
                '<style>*{-webkit-print-color-adjust:exact;print-color-adjust:exact} body{margin:0;padding:24px;background:#f1f5f9} @page{size:A4;margin:10mm} @media print{body{background:#fff;padding:0}.no-print{display:none}}</style></head><body>' +
                '<div class="no-print" style="max-width:980px;margin:0 auto 12px;font-family:Inter,Segoe UI,Arial,sans-serif;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#475569">' + escapeHtml(titlePrefix) + ' • ' + window.OrModule.getOrNumber(rdv) + '</div>' +
                window.OrModule.getOrSheetHtml(rdv) +
                '<div class="no-print" style="max-width:980px;margin:18px auto 0;display:flex;justify-content:center;gap:10px;flex-wrap:wrap">' +
                    '<button onclick="window.print()" style="border:none;border-radius:14px;padding:14px 20px;background:#0f172a;color:#fff;font-weight:800;cursor:pointer">' + escapeHtml(primaryLabel) + '</button>' +
                    '<button onclick="window.close()" style="border:1px solid #cbd5e1;border-radius:14px;padding:14px 20px;background:#fff;color:#334155;font-weight:700;cursor:pointer">Fermer</button>' +
                '</div>' + printScript + '</body></html>';
            popup.document.open();
            popup.document.write(html);
            popup.document.close();
            popup.focus();
        }).catch(function(e) {
            popup.document.body.innerHTML = '<div style="font-family:Arial,sans-serif;padding:24px;color:#b91c1c">Erreur de chargement OR : ' + escapeHtml(e.message || 'inconnue') + '</div>';
        });
    },

    imprimerOR: function(rdvId) {
        return window.OrModule.openOrPrintWindow(rdvId, {
            autoPrint: false,
            primaryLabel: 'Imprimer l\'ordre',
            titlePrefix: 'Impression'
        });
    },

    renderOrdresReparation: function(rdvs) {
        var container = document.getElementById('or-list');
        if (!container) return;
        var enCours = (rdvs || []).filter(function(r) { return r.statut !== 'annule' && r.statut !== 'non_presente'; });
        document.getElementById('or-ouverts').textContent = enCours.filter(function(r) { return r.statut !== 'termine' && r.statut !== 'facture' && r.statut !== 'paye'; }).length + ' ouverts';
        document.getElementById('or-termines').textContent = enCours.filter(function(r) { return r.statut === 'termine' || r.statut === 'facture' || r.statut === 'paye'; }).length + ' termines';

        enCours.sort(function(a, b) {
            var order = { 'en_cours': 0, 'reception': 1, 'confirme': 2, 'reserve': 3, 'en_attente': 4, 'termine': 5, 'facture': 6, 'paye': 7, 'non_presente': 8 };
            return (order[a.statut] || 9) - (order[b.statut] || 9);
        });

        if (!enCours.length) {
            container.innerHTML = '<div class="card" style="padding:24px;text-align:center;color:#94a3b8">Aucun ordre de reparation actif pour le moment.</div>';
            return;
        }

        var etapes = ['Reception', 'Diagnostic', 'Intervention', 'Controle QC', 'Livraison'];
        var html = '';
        enCours.slice(0, 30).forEach(function(rdv) {
            var meca = rdv.mecanicien || APP.mecaniciens.find(function(m) { return m.id === rdv.mecanicien_id; });
            var pont = rdv.pont || APP.ponts.find(function(p) { return p.id === rdv.pont_id; });
            var v = rdv.vehicule || {};
            var c = rdv.client || {};
            var duree = rdv.temps_estime ? Math.round(rdv.temps_estime / 60 * 10) / 10 + 'h' : 'A estimer';
            var isTermine = rdv.statut === 'termine' || rdv.statut === 'facture' || rdv.statut === 'paye';
            var currentIdx = window.OrModule.getEtapeIndex(rdv.statut);
            var orNum = window.OrModule.getOrNumber(rdv);
            var orMeta = window.OrModule.getOrStatusMeta(rdv.statut);
            var totalDisplay = window.OrModule.formatEuro(rdv.prix_final != null ? rdv.prix_final : rdv.prix_estime);
            var currentOr = window.OrModule.getLatestOrdreInfo(rdv);
            var currentOrId = currentOr ? currentOr.id : null;
            var stepsHtml = '<div style="display:flex;gap:4px;margin-top:14px">';
            etapes.forEach(function(label, i) {
                var bg = '#1f2937';
                var txtCol = '#94a3b8';
                if (i < currentIdx || isTermine) { bg = '#22c55e'; txtCol = '#fff'; }
                else if (i === currentIdx && !isTermine) { bg = '#f97316'; txtCol = '#fff'; }
                stepsHtml += '<div style="flex:1;text-align:center;padding:7px 4px;font-size:10px;font-weight:800;letter-spacing:.03em;background:' + bg + ';color:' + txtCol + ';border-radius:6px">' + label + '</div>';
            });
            stepsHtml += '</div>';

            html += '<div class="or-card" style="margin-bottom:16px;padding:0;overflow:hidden;border-radius:20px;border:1px solid #334155;background:linear-gradient(180deg,#0f172a 0%, #111827 100%);box-shadow:0 16px 32px rgba(15,23,42,.22)">' +
                '<div style="padding:16px 18px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap">' +
                    '<div>' +
                        '<div style="font-size:11px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#94a3b8">Ordre de reparation</div>' +
                        '<div style="font-family:Barlow Condensed,sans-serif;font-size:28px;line-height:1;color:#fff;font-weight:700">' + orNum + '</div>' +
                        '<div style="margin-top:6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">' +
                            '<span style="padding:5px 10px;border-radius:999px;background:' + orMeta.soft + ';color:' + orMeta.tone + ';font-size:11px;font-weight:800">' + orMeta.label + '</span>' +
                            '<span style="font-size:12px;color:#94a3b8">' + escapeHtml(rdv.date_rdv || '-') + ' • ' + escapeHtml(formatTime(rdv.heure_rdv || '') || '--:--') + '</span>' +
                        '</div>' +
                    '</div>' +
                    '<div style="text-align:right">' +
                        '<div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.12em">Montant estimatif</div>' +
                        '<div style="font-size:24px;font-weight:900;color:#fb923c">' + totalDisplay + '</div>' +
                        '<div style="font-size:11px;color:#94a3b8">Temps: ' + duree + '</div>' +
                    '</div>' +
                '</div>' +
                '<div style="padding:16px 18px">' +
                    '<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px">' +
                        '<div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:10px"><div style="font-size:10px;color:#94a3b8;text-transform:uppercase">Client</div><div style="font-size:13px;font-weight:700;color:#fff;margin-top:4px">' + escapeHtml(((c.prenom || '') + ' ' + (c.nom || '')).trim() || '-') + '</div><div style="font-size:12px;color:#94a3b8">' + escapeHtml(c.telephone || '-') + '</div></div>' +
                        '<div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:10px"><div style="font-size:10px;color:#94a3b8;text-transform:uppercase">Vehicule</div><div style="font-size:13px;font-weight:700;color:#fff;margin-top:4px">' + escapeHtml(((v.marque || '') + ' ' + (v.modele || '')).trim() || '-') + '</div><div style="font-size:12px;color:#94a3b8">' + escapeHtml(v.plaque || '-') + '</div></div>' +
                        '<div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:10px"><div style="font-size:10px;color:#94a3b8;text-transform:uppercase">Mecanicien</div><div style="font-size:13px;font-weight:700;color:#fff;margin-top:4px">' + escapeHtml(meca ? (meca.prenom + ' ' + meca.nom) : 'Non assigne') + '</div><div style="font-size:12px;color:#94a3b8">' + escapeHtml(pont ? (pont.nom || '-') : 'Pont non defini') + '</div></div>' +
                        '<div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);border-radius:12px;padding:10px"><div style="font-size:10px;color:#94a3b8;text-transform:uppercase">Intervention</div><div style="font-size:13px;font-weight:700;color:#fff;margin-top:4px">' + escapeHtml(rdv.type_intervention || '-') + '</div><div style="font-size:12px;color:#94a3b8">Statut atelier: ' + orMeta.label + '</div></div>' +
                    '</div>' +
                    stepsHtml +
                    '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">' +
                        '<button class="btn btn-primary" onclick="event.stopPropagation();showOrDetail(' + rdv.id + ')" style="background:#f97316;color:#111">Apercu master</button>' +
                        '<button class="btn btn-ghost" onclick="event.stopPropagation();imprimerOR(' + rdv.id + ')">Imprimer</button>' +
                        '<button class="btn btn-ghost" onclick="event.stopPropagation();telechargerOR(' + rdv.id + ',' + (currentOrId || 'null') + ')">PDF</button>' +
                        '<button class="btn btn-ghost" onclick="event.stopPropagation();ouvrirReception(' + rdv.id + ')">Reception</button>' +
                        '<button class="btn btn-ghost" onclick="event.stopPropagation();ouvrirDemandeTravauxSupp(' + rdv.id + ')">Travaux supp</button>' +
                        '<button class="btn btn-ghost" onclick="event.stopPropagation();planifierRdvSuite(' + rdv.id + ')">RDV suite</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        });
        container.innerHTML = html;
    },

    getEtapeIndex: function(statut) {
        var map = { 'reserve': 0, 'en_attente': 0, 'confirme': 0, 'reception': 0, 'en_cours': 2, 'termine': 4, 'facture': 4, 'paye': 4, 'non_presente': 4 };
        return map[statut] !== undefined ? map[statut] : 0;
    },

    showOrDetail: function(rdvId) {
        apiGet('/api/rendez-vous/' + rdvId).then(function(r) { return r.json(); }).then(function(rdv) {
            var overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(2,6,23,.82);display:flex;align-items:center;justify-content:center;z-index:1000;padding:18px';
            overlay.onclick = function(e) { if (e.target === overlay) overlay.remove(); };
            overlay.innerHTML =
                '<div style="width:min(1180px,96vw);max-height:92vh;overflow:auto;border-radius:24px">' +
                    '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px;padding:0 4px">' +
                        '<div style="font-size:12px;color:#cbd5e1;font-weight:700;letter-spacing:.12em;text-transform:uppercase">Apercu OR master</div>' +
                        '<div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">' +
                            '<button class="btn btn-primary" onclick="imprimerOR(' + rdv.id + ')" style="background:#f97316;color:#111">Imprimer</button>' +
                            '<button class="btn btn-ghost" onclick="telechargerOR(' + rdv.id + ',' + ((window.OrModule.getLatestOrdreInfo(rdv) || {}).id || 'null') + ')">PDF</button>' +
                            '<button class="btn btn-ghost" onclick="ouvrirReception(' + rdv.id + ')">Reception</button>' +
                            '<button class="btn btn-ghost" onclick="ouvrirDemandeTravauxSupp(' + rdv.id + ')">Travaux supp</button>' +
                            '<button class="btn btn-ghost" onclick="this.closest(\'.modal-overlay\').remove();planifierRdvSuite(' + rdv.id + ')">RDV suite</button>' +
                            '<button class="btn btn-ghost" onclick="this.closest(\'.modal-overlay\').remove()">Fermer</button>' +
                        '</div>' +
                    '</div>' +
                    window.OrModule.getOrSheetHtml(rdv, { compact: true }) +
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
            var existingEtat = window.OrModule.parseEtatVehicule(rdv.etat_vehicule);
            _receptionDamagePoints = (existingEtat.body_damages || []).slice();
            _receptionDamagePhotos = (Array.isArray(rdv.photos_etat) && rdv.photos_etat.length ? rdv.photos_etat : existingEtat.photos || []).slice();

            html += '<div style="background:#1e1e1e;border:1px solid #333;border-radius:10px;padding:12px;margin-bottom:16px;display:flex;gap:16px;flex-wrap:wrap">'
                + '<div><div style="font-size:11px;color:#666">Client</div><div style="color:#eee;font-weight:600">' + (escapeHtml(c.prenom) || '') + ' ' + (escapeHtml(c.nom) || '') + '</div></div>'
                + '<div><div style="font-size:11px;color:#666">Vehicule</div><div style="color:#eee;font-weight:600">' + (escapeHtml(v.marque) || '') + ' ' + (escapeHtml(v.modele) || '') + '</div></div>'
                + '<div><div style="font-size:11px;color:#666">Plaque</div><div style="color:#eee;font-weight:600">' + (escapeHtml(v.plaque) || '') + '</div></div>'
                + '</div>';

            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">'
                + '<div class="form-group"><label class="form-label" style="color:#ccc">Kilometrage *</label>'
                + '<input type="number" id="reception-km" class="form-input" placeholder="Ex: 15000" value="' + (rdv.kilometrage || '') + '"></div>'
                + '<div class="form-group"><label class="form-label" style="color:#ccc">Priorite</label>'
                + '<select id="reception-priority" class="form-input"><option value="basse">Basse</option><option value="standard">Standard</option><option value="urgent">Flash / Urgent</option><option value="critique">Critique</option></select></div>'
                + '</div>';

            html += '<div class="form-group"><label class="form-label" style="color:#ccc">Niveau carburant</label>'
                + '<select id="reception-fuel" class="form-input"><option value="">Non renseigne</option><option value="1">1/4</option><option value="2">2/4</option><option value="3">3/4</option><option value="4">Plein</option></select></div>';

            html += '<div class="form-group"><label class="form-label" style="color:#ccc">Etat du vehicule</label>'
                + '<div class="reception-check-grid" id="reception-etat-checks">';
            window.ETAT_VEHICULE_POINTS.forEach(function(pt) {
                var checked = existingEtat.pointKeys.indexOf(pt.key) !== -1 ? ' checked' : '';
                html += '<label class="reception-check-item"><input type="checkbox" value="' + pt.key + '"' + checked + '> ' + pt.label + '</label>';
            });
            html += '</div></div>';

            html += '<div class="form-group"><label class="form-label" style="color:#ccc">Annotations carrosserie (clic sur le schema)</label>'
                + window.OrModule.getReceptionDamageSchemaHtml()
                + '<textarea id="reception-schema-notes" class="form-input" rows="2" placeholder="Ex: rayure flanc droit, impact reservoir..." style="margin-top:10px">' + escapeHtml(existingEtat.schema_notes || '') + '</textarea></div>';

            html += '<div class="form-group"><label class="form-label" style="color:#ccc">Photos etat vehicule</label>'
                + '<input id="reception-photo-input" type="file" accept="image/*" multiple class="form-input" onchange="handleReceptionPhotoUpload(event)">'
                + '<div id="reception-photo-list" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px"></div></div>';

            html += '<div class="form-group"><div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px"><label class="form-label" style="color:#ccc;margin-bottom:0">Lignes d\'estimation</label><button class="btn btn-ghost" type="button" onclick="addReceptionEstimateRow()">+ Ajouter ligne</button></div>'
                + '<div id="reception-estimate-rows"></div></div>';

            html += '<div class="form-group"><label class="form-label" style="color:#ccc">Observations</label>'
                + '<textarea id="reception-obs" class="form-input" rows="3" placeholder="Notes sur l\'etat general...">' + escapeHtml(existingEtat.observations || rdv.commentaire || '') + '</textarea></div>';

            html += '<div class="form-group"><label class="form-label" style="color:#ccc">Signature client *</label>'
                + '<canvas id="reception-signature-canvas" width="400" height="150" style="border:1px solid #444;border-radius:6px;background:#fff;cursor:crosshair;display:block;width:100%;touch-action:none"></canvas>'
                + '<button class="btn btn-ghost" style="margin-top:6px;font-size:11px" onclick="clearReceptionSignature()">Effacer signature</button></div>';

            html += '<button class="btn btn-primary" style="width:100%;margin-top:12px;background:var(--teal)" onclick="validerReception(' + rdvId + ')">Valider la reception</button>';

            showModal('Reception - ' + window.OrModule.getOrNumber(rdv), html, '900px');
            setTimeout(function() {
                var priorityEl = document.getElementById('reception-priority');
                if (priorityEl) priorityEl.value = existingEtat.priority || 'standard';
                var fuelEl = document.getElementById('reception-fuel');
                if (fuelEl && existingEtat.fuel_level != null) fuelEl.value = String(existingEtat.fuel_level);
                window.OrModule.initReceptionSignaturePad();
                window.OrModule.syncReceptionDamageButtons();
                window.OrModule.renderReceptionPhotoList();
                var estimateRows = (existingEtat.estimate_rows && existingEtat.estimate_rows.length) ? existingEtat.estimate_rows : [{ label: rdv.type_intervention || 'Intervention atelier', qty: 1, amount: rdv.prix_final != null ? rdv.prix_final : rdv.prix_estime }];
                estimateRows.forEach(function(row) { window.OrModule.addReceptionEstimateRow(row); });
            }, 100);
        }).catch(function(e) { alert('Erreur: ' + e.message); });
    },

    getReceptionDamageSchemaHtml: function() {
        var points = [
            { key: 'avant', label: 'Avant', top: '30%', left: '24%' },
            { key: 'reservoir', label: 'Reservoir', top: '28%', left: '49%' },
            { key: 'flanc_gauche', label: 'Flanc G', top: '52%', left: '42%' },
            { key: 'flanc_droit', label: 'Flanc D', top: '52%', left: '62%' },
            { key: 'arriere', label: 'Arriere', top: '33%', left: '78%' },
            { key: 'roue_av', label: 'Roue AV', top: '77%', left: '21%' },
            { key: 'roue_ar', label: 'Roue AR', top: '77%', left: '78%' }
        ];
        var svg = '<svg viewBox="0 0 512 320" style="width:100%;height:100%;display:block" fill="none" stroke="#334155" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'
            + '<circle cx="110" cy="230" r="65"></circle><circle cx="110" cy="230" r="55" stroke-dasharray="4 4"></circle><circle cx="400" cy="230" r="65"></circle><circle cx="400" cy="230" r="55" stroke-dasharray="4 4"></circle><circle cx="110" cy="230" r="30" opacity=".5"></circle><path d="M110 230 L180 80 L200 85 L130 235 Z"></path><path d="M180 120 L300 120 L350 180 L350 230 L220 230 Z"></path><rect x="220" y="160" width="80" height="60" rx="5"></rect><path d="M180 120 Q220 70 320 90 Q340 100 340 130 L300 130 Z" fill="#f8fafc"></path><path d="M340 130 Q380 120 440 140 L430 160 Q380 150 340 160 Z" fill="#f8fafc"></path><path d="M300 200 L400 230" stroke-width="3"></path><path d="M250 220 L380 260 L420 240" stroke-width="4"></path><path d="M180 80 L160 70 L200 65"></path><circle cx="165" cy="100" r="12"></circle></svg>';
        var buttons = points.map(function(pt) {
            return '<button type="button" data-damage-key="' + pt.key + '" onclick="toggleBodyDamage(\'' + pt.key + '\')" style="position:absolute;top:' + pt.top + ';left:' + pt.left + ';transform:translate(-50%,-50%);border:1px solid #cbd5e1;background:rgba(255,255,255,.92);color:#0f172a;border-radius:999px;padding:4px 8px;font-size:10px;font-weight:700;cursor:pointer">' + pt.label + '</button>';
        }).join('');
        return '<div style="position:relative;height:240px;border:2px solid #e2e8f0;border-radius:16px;background:#fff;overflow:hidden">' + svg + buttons + '</div>' +
            '<div style="font-size:11px;color:#94a3b8;margin-top:8px">Cliquez sur les zones du schema pour enregistrer les impacts visibles.</div>';
    },

    toggleBodyDamage: function(key) {
        var idx = _receptionDamagePoints.indexOf(key);
        if (idx >= 0) _receptionDamagePoints.splice(idx, 1);
        else _receptionDamagePoints.push(key);
        window.OrModule.syncReceptionDamageButtons();
    },

    syncReceptionDamageButtons: function() {
        var buttons = document.querySelectorAll('[data-damage-key]');
        for (var i = 0; i < buttons.length; i++) {
            var key = buttons[i].getAttribute('data-damage-key');
            var active = _receptionDamagePoints.indexOf(key) !== -1;
            buttons[i].style.background = active ? '#fb923c' : 'rgba(255,255,255,.92)';
            buttons[i].style.color = active ? '#111827' : '#0f172a';
            buttons[i].style.borderColor = active ? '#ea580c' : '#cbd5e1';
        }
    },

    handleReceptionPhotoUpload: function(event) {
        var files = Array.prototype.slice.call((event && event.target && event.target.files) || []);
        if (!files.length) return;
        files.forEach(function(file) {
            if (!file || !file.type || file.type.indexOf('image/') !== 0) return;
            var reader = new FileReader();
            reader.onload = function(loadEvent) {
                if (loadEvent && loadEvent.target && loadEvent.target.result) {
                    _receptionDamagePhotos.push(loadEvent.target.result);
                    window.OrModule.renderReceptionPhotoList();
                }
            };
            reader.readAsDataURL(file);
        });
        if (event && event.target) event.target.value = '';
    },

    renderReceptionPhotoList: function() {
        var container = document.getElementById('reception-photo-list');
        if (!container) return;
        if (!_receptionDamagePhotos.length) {
            container.innerHTML = '<div style="font-size:12px;color:#777">Aucune photo ajoutee.</div>';
            return;
        }
        container.innerHTML = _receptionDamagePhotos.map(function(src, index) {
            return '<div style="position:relative"><img src="' + src + '" alt="Photo etat" style="width:88px;height:88px;object-fit:cover;border-radius:10px;border:1px solid #444">' +
                '<button type="button" onclick="removeReceptionPhoto(' + index + ')" style="position:absolute;top:-6px;right:-6px;width:22px;height:22px;border:none;border-radius:50%;background:#ef4444;color:#fff;cursor:pointer">×</button></div>';
        }).join('');
    },

    removeReceptionPhoto: function(index) {
        _receptionDamagePhotos.splice(index, 1);
        window.OrModule.renderReceptionPhotoList();
    },

    addReceptionEstimateRow: function(row) {
        var container = document.getElementById('reception-estimate-rows');
        if (!container) return;
        row = row || {};
        var wrap = document.createElement('div');
        wrap.className = 'reception-estimate-row';
        wrap.style.cssText = 'display:grid;grid-template-columns:1.7fr .6fr .8fr auto;gap:8px;margin-bottom:8px';
        wrap.innerHTML = '<input class="form-input reception-estimate-label" placeholder="Operation / reference" value="' + escapeHtml(row.label || row.designation || '') + '">' +
            '<input class="form-input reception-estimate-qty" type="number" min="1" step="1" value="' + escapeHtml(String(row.qty || row.quantite || 1)) + '">' +
            '<input class="form-input reception-estimate-amount" type="number" min="0" step="0.01" placeholder="0.00" value="' + (row.amount != null ? escapeHtml(String(row.amount)) : (row.montant != null ? escapeHtml(String(row.montant)) : '')) + '">' +
            '<button class="btn btn-ghost" type="button" onclick="this.parentElement.remove()">Suppr.</button>';
        container.appendChild(wrap);
    },

    collectReceptionEstimateRows: function() {
        var rows = [];
        var items = document.querySelectorAll('.reception-estimate-row');
        for (var i = 0; i < items.length; i++) {
            var labelEl = items[i].querySelector('.reception-estimate-label');
            var qtyEl = items[i].querySelector('.reception-estimate-qty');
            var amountEl = items[i].querySelector('.reception-estimate-amount');
            var label = labelEl ? labelEl.value.trim() : '';
            if (!label) continue;
            rows.push({
                label: label,
                qty: qtyEl ? parseFloat(qtyEl.value || '1') || 1 : 1,
                amount: amountEl && amountEl.value !== '' ? (parseFloat(amountEl.value) || 0) : null
            });
        }
        return rows;
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
        var priority = (document.getElementById('reception-priority') || {}).value || 'standard';
        var fuelValue = parseInt((document.getElementById('reception-fuel') || {}).value || '', 10);
        var schemaNotes = (document.getElementById('reception-schema-notes') || {}).value || '';
        var estimateRows = window.OrModule.collectReceptionEstimateRows();
        var etatVehicule = JSON.stringify({
            points: etatItems,
            observations: observations,
            priority: priority,
            fuel_level: isNaN(fuelValue) ? null : fuelValue,
            body_damages: _receptionDamagePoints.slice(),
            schema_notes: schemaNotes,
            estimate_rows: estimateRows,
            photos: _receptionDamagePhotos.slice()
        });

        var signatureData = window.OrModule.getReceptionSignatureBase64();
        if (!signatureData) { alert('Signature client obligatoire'); return; }

        apiPost('/api/rendez-vous/' + rdvId + '/ordre-reparation/save', {
            kilometrage: parseInt(km, 10),
            etat_vehicule: etatVehicule,
            travaux: observations,
            signature: signatureData,
            priorite: priority,
            niveau_carburant: isNaN(fuelValue) ? null : fuelValue,
            dommages_carrosserie: _receptionDamagePoints.slice(),
            notes_schema: schemaNotes,
            lignes_estimation: estimateRows,
            photos: _receptionDamagePhotos.slice()
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
            setTimeout(function() {
                window.OrModule.renderTravauxSuppPanel();
                if (typeof refreshCurrentSection === 'function') refreshCurrentSection();
            }, 500);
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
            setTimeout(function() {
                window.OrModule.renderTravauxSuppPanel();
                if (typeof refreshCurrentSection === 'function') refreshCurrentSection();
            }, 500);
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
