var _rdvClientSearchTimer = null;
var _rdvMotoBrandTimer = null;
var _rdvMotoModelTimer = null;

window.RdvModule = window.RdvModule || {
    loadRdvForm: function() {
        APP.rdvClientPrefill = null;
        APP.rdvMotoLookup = { marques: [], modeles: [], selectedModele: null };
        APP.rdvWizard = {
            step: 1,
            vehicule: null,
            motoType: '',
            interventions: [],
            selected: [],
            totalPrix: 0,
            totalTemps: 0,
            delaiInterventionJours: 1,
            selectedDate: null,
            selectedHeure: null,
            selectedPont: null,
            selectedMeca: null,
            weekOffset: 0,
            weekData: {},
            selectedAtelierSlug: window.RdvModule.getCurrentAtelierSlug()
        };
        window.RdvModule.populateRdvAtelierSelect();
        var res = document.getElementById('rdv-client-search-results');
        if (res) res.innerHTML = '<div style="font-size:12px;color:#888">Tapez au moins 2 caracteres pour rechercher un client existant.</div>';
        ['pub-prenom', 'pub-nom', 'pub-tel', 'pub-email', 'pub-comment', 'pub-plaque', 'pub-man-marque', 'pub-man-modele', 'pub-man-annee', 'pub-man-cylindree'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        var manualType = document.getElementById('pub-moto-type-manual');
        if (manualType) manualType.value = '';
        var yearInput = document.getElementById('pub-man-annee');
        if (yearInput) yearInput.placeholder = 'Ex: 2021';
        var r1 = document.getElementById('pub-veh-result'); if (r1) r1.style.display = 'none';
        var r2 = document.getElementById('pub-veh-notfound'); if (r2) r2.style.display = 'none';
        window.RdvModule.loadMotoReferenceData();
        window.RdvModule.goStep(1);
    },

    searchMotoRdv: function(val) {
        var result = document.getElementById('rdv-moto-result');
        if (!result) return;
        if (val.length < 3) { result.style.display = 'none'; return; }
        var plaque = val.replace(/[\s-]/g, '').toUpperCase();
        apiGet('/api/vehicule/' + encodeURIComponent(plaque)).then(function(r) { return r.json(); }).then(function(data) {
            if (data && !data.not_found) {
                result.style.display = 'block';
                document.getElementById('rdv-moto-name').textContent = (escapeHtml(data.marque) || '') + ' ' + (escapeHtml(data.modele) || '') + (escapeHtml(data.annee) ? ' (' + data.annee + ')' : '');
                document.getElementById('rdv-moto-detail').textContent = (data.type_moto || '') + ' | ' + (data.cylindree || '') + ' | ' + (data.plaque || '');
                if (data.client) {
                    document.getElementById('rdv-client-name').value = data.client.nom + ' ' + data.client.prenom;
                    document.getElementById('rdv-client-tel').value = data.client.telephone || '';
                }
            } else {
                result.style.display = 'none';
            }
        }).catch(function() { result.style.display = 'none'; });
    },

    updateDurationRdv: function() {
        var intSelect = document.getElementById('rdv-intervention-type');
        if (!intSelect) return;
        var opt = intSelect.options[intSelect.selectedIndex];
        var durMin = parseInt(opt.getAttribute('data-dur') || '0', 10);
        var catId = document.getElementById('rdv-moto-type').value;
        var block = document.getElementById('rdv-duration-block');
        var val = document.getElementById('rdv-duration-val');
        if (!block || !val) return;
        if (catId && durMin > 0) {
            block.style.display = 'block';
            var h = Math.floor(durMin / 60);
            var m = durMin % 60;
            val.textContent = (h > 0 ? h + 'h' : '') + (m > 0 ? m + 'min' : '') + ' estimees';
        } else {
            block.style.display = 'none';
        }
    },

    loadCreneaux: function() {
        var dateEl = document.getElementById('rdv-date');
        if (!dateEl) return;
        var dateVal = dateEl.value;
        if (!dateVal) return;
        var intSelect = document.getElementById('rdv-intervention-type');
        if (!intSelect) return;
        var opt = intSelect.options[intSelect.selectedIndex];
        var durMin = parseInt(opt.getAttribute('data-dur') || '60', 10);

        var creneauxContainer = document.getElementById('rdv-creneaux-grid');
        if (!creneauxContainer) return;
        setLoadingState(creneauxContainer, true, 'Chargement des creneaux...');
        apiGet('/api/creneaux/disponibles?date_str=' + dateVal + '&duree_minutes=' + durMin).then(function(r) { return r.json(); }).then(function(creneaux) {
            window.RdvModule.renderCreneaux(creneaux);
        }).catch(function() {
            var fb = getPlanningBounds();
            var slots = buildPlanningSlots(fb.start, fb.end, 15);
            window.RdvModule.renderCreneaux(slots.map(function(h) { return { heure: h, disponible: true }; }));
        });
    },

    renderCreneaux: function(creneaux) {
        var container = document.getElementById('rdv-creneaux-grid');
        if (!container) return;
        var html = '';
        if (Array.isArray(creneaux)) {
            creneaux.forEach(function(c) {
                var heure = c.heure || c;
                var dispo = c.disponible !== false;
                if (dispo) html += '<div class="dispo-slot available" onclick="selectSlotRdv(this,\'' + escapeAttr(heure) + '\')">' + escapeHtml(heure) + '</div>';
                else html += '<div class="dispo-slot full">' + heure + '</div>';
            });
        }
        container.innerHTML = html || '<div style="color:#666;padding:10px">Aucun creneau disponible</div>';
        updateLiveRegion(html ? 'Creneaux charges' : 'Aucun creneau disponible');
    },

    selectSlotRdv: function(el, time) {
        document.querySelectorAll('#rdv-creneaux-grid .dispo-slot.available').forEach(function(s) { s.classList.remove('selected'); });
        el.classList.add('selected');
        document.getElementById('rdv-selected-slot').style.display = 'block';
        document.getElementById('rdv-slot-time').textContent = time;
        APP.selectedSlot = time;
    },

    confirmRdv: function() {
        var clientName = document.getElementById('rdv-client-name').value;
        var clientTel = document.getElementById('rdv-client-tel').value;
        var plaque = document.getElementById('rdv-vin-input').value;
        var dateVal = document.getElementById('rdv-date').value;
        var intSelect = document.getElementById('rdv-intervention-type');
        var intOpt = intSelect.options[intSelect.selectedIndex];
        var typeIntervention = intOpt ? intOpt.textContent : '';

        var hasError = false;
        var nameErr = document.getElementById('rdv-client-name-error');
        var telErr = document.getElementById('rdv-client-tel-error');
        if (nameErr) nameErr.textContent = '';
        if (telErr) telErr.textContent = '';
        if (!clientName || clientName.trim().length < 2) {
            if (nameErr) nameErr.textContent = 'Nom client obligatoire';
            hasError = true;
        }
        if (!clientTel || clientTel.replace(/\D/g, '').length < 10) {
            if (telErr) telErr.textContent = 'Telephone invalide (10 chiffres min)';
            hasError = true;
        }
        if (!dateVal || !APP.selectedSlot || !intSelect.value) {
            showAlert('Veuillez remplir tous les champs obligatoires', 'warning');
            hasError = true;
        }
        if (hasError) return;

        var parts = clientName.trim().split(/\s+/);
        var nom = parts[0] || '';
        var prenom = parts.slice(1).join(' ') || '';

        apiPost('/api/rendez-vous', {
            client: { nom: nom, prenom: prenom, telephone: clientTel || '0000000000' },
            vehicule: { plaque: plaque || 'XX-000-XX' },
            date_rdv: dateVal,
            heure_rdv: APP.selectedSlot + ':00',
            type_intervention: typeIntervention
        }).then(function(r) { return r.json(); }).then(function(data) {
            var confirmEl = document.getElementById('rdv-confirm');
            confirmEl.style.display = 'block';
            document.getElementById('rdv-confirm-msg').textContent = 'RDV #' + (data.id || '') + ' cree avec succes - Statut: Reserve';
            updateLiveRegion('Rendez-vous cree avec succes');
            APP.selectedSlot = null;
        }).catch(function(e) {
            alert('Erreur creation RDV: ' + e.message);
        });
    },

    searchClientRdvEmbed: function(val) {
        var container = document.getElementById('rdv-client-search-results');
        if (!container) return;
        var q = (val || '').trim();
        if (_rdvClientSearchTimer) clearTimeout(_rdvClientSearchTimer);
        if (q.length < 2) {
            container.innerHTML = '<div style="font-size:12px;color:#888">Tapez au moins 2 caracteres pour rechercher un client existant.</div>';
            return;
        }
        _rdvClientSearchTimer = setTimeout(function() {
            apiGet('/api/clients?search=' + encodeURIComponent(q)).then(function(r) { return r.json(); }).then(function(clients) {
                if (!clients || !clients.length) {
                    container.innerHTML = '<div style="font-size:12px;color:#888">Aucun client trouve. Saisie manuelle possible dans le formulaire.</div>';
                    return;
                }
                var html = '';
                clients.slice(0, 8).forEach(function(c) {
                    html += '<div style="padding:8px;border-bottom:1px solid #2a2a33;cursor:pointer" onclick="selectClientRdvEmbed(' + c.id + ')">' +
                        '<div style="color:#eee;font-weight:600">' + escapeHtml((c.prenom || '') + ' ' + (c.nom || '')) + '</div>' +
                        '<div style="font-size:12px;color:#888">' + escapeHtml(c.telephone || '-') + ' • ' + escapeHtml(c.email || '-') + '</div>' +
                        '</div>';
                });
                container.innerHTML = html;
            }).catch(function(e) {
                container.innerHTML = '<div style="font-size:12px;color:#ef4444">Erreur: ' + escapeHtml(e.message || 'recherche client') + '</div>';
            });
        }, 220);
    },

    selectClientRdvEmbed: function(clientId) {
        apiGet('/api/clients/' + clientId).then(function(r) { return r.json(); }).then(function(c) {
            APP.rdvClientPrefill = {
                nom: c.nom || '',
                prenom: c.prenom || '',
                telephone: c.telephone || '',
                email: c.email || ''
            };
            var container = document.getElementById('rdv-client-search-results');
            if (container) {
                container.innerHTML = '<div style="font-size:12px;color:#22c55e">Client selectionne: ' + escapeHtml((c.prenom || '') + ' ' + (c.nom || '')) + '</div>';
            }
            ['pub-prenom', 'pub-nom', 'pub-tel', 'pub-email'].forEach(function(id, i) {
                var el = document.getElementById(id);
                if (!el) return;
                if (i === 0) el.value = APP.rdvClientPrefill.prenom || '';
                if (i === 1) el.value = APP.rdvClientPrefill.nom || '';
                if (i === 2) el.value = APP.rdvClientPrefill.telephone || '';
                if (i === 3) el.value = APP.rdvClientPrefill.email || '';
            });
        }).catch(function(e) { showAlert('Erreur client: ' + e.message, 'error'); });
    },

    pushRdvPrefillToIframe: function() {
        // Legacy no-op: iframe integration removed in favor of native wizard
    },

    ouvrirRdvPublicModal: function() {
        // Legacy no-op: modal integration removed in favor of native wizard
    },

    getCurrentAtelierSlug: function() {
        var RDV = APP.rdvWizard;
        if (RDV && RDV.selectedAtelierSlug) return RDV.selectedAtelierSlug;
        if (APP.currentUser && APP.currentUser.atelier_slug) return APP.currentUser.atelier_slug;
        if (APP.currentUser && APP.currentUser.ateliers && APP.currentUser.ateliers.length) {
            for (var i = 0; i < APP.currentUser.ateliers.length; i++) {
                if (APP.currentUser.ateliers[i].atelier_id === APP.currentUser.atelier_id) return APP.currentUser.ateliers[i].slug || null;
            }
        }
        return 'default';
    },

    populateRdvAtelierSelect: function() {
        var selectTop = document.getElementById('rdv-atelier-select');
        if (!selectTop) return;
        var canSelectAtelier = hasPermission('rdv.select_atelier');
        var wrap = selectTop.closest('.form-group') || selectTop.parentElement;
        if (wrap) wrap.style.display = canSelectAtelier ? '' : 'none';
        function renderSelects(ateliers) {
            ateliers = Array.isArray(ateliers) ? ateliers.filter(function(a) { return !!(a && a.slug); }) : [];
            if (!ateliers.length) {
                var fallbackSlug = window.RdvModule.getCurrentAtelierSlug() || 'default';
                selectTop.innerHTML = '<option value="' + escapeAttr(fallbackSlug) + '">' + escapeHtml(fallbackSlug) + '</option>';
                selectTop.value = fallbackSlug;
                selectTop.disabled = true;
                return;
            }
            var html = '';
            ateliers.forEach(function(a) {
                var slug = a.slug || '';
                var aid = a.atelier_id || a.id || '';
                var label = (a.nom || slug || ('Atelier #' + aid));
                html += '<option value="' + escapeAttr(slug) + '">' + escapeHtml(label) + '</option>';
            });
            selectTop.innerHTML = html;
            var RDV = window.RdvModule.getRdvState();
            if (!RDV.selectedAtelierSlug) RDV.selectedAtelierSlug = window.RdvModule.getCurrentAtelierSlug();
            var exists = ateliers.some(function(a) { return a.slug === RDV.selectedAtelierSlug; });
            if (!exists) RDV.selectedAtelierSlug = ateliers[0].slug || RDV.selectedAtelierSlug;
            if (!canSelectAtelier) {
                var own = ateliers.find(function(a) {
                    var aid = a.atelier_id || a.id;
                    return aid === (APP.currentUser ? APP.currentUser.atelier_id : null);
                });
                RDV.selectedAtelierSlug = (own && own.slug) ? own.slug : RDV.selectedAtelierSlug;
            }
            selectTop.value = RDV.selectedAtelierSlug;
            selectTop.disabled = !canSelectAtelier;
        }

        if (canSelectAtelier) {
            apiGet('/api/ateliers/public').then(function(r) { return r.json(); }).then(function(items) {
                renderSelects(items);
            }).catch(function() {
                renderSelects((APP.currentUser && Array.isArray(APP.currentUser.ateliers)) ? APP.currentUser.ateliers : []);
            });
            return;
        }
        renderSelects((APP.currentUser && Array.isArray(APP.currentUser.ateliers)) ? APP.currentUser.ateliers : []);
    },

    onRdvAtelierChange: function(slug) {
        var RDV = window.RdvModule.getRdvState();
        var nextSlug = (slug || '').trim().toLowerCase();
        if (!nextSlug || nextSlug === RDV.selectedAtelierSlug) return;
        RDV.selectedAtelierSlug = nextSlug;
        RDV.interventions = [];
        RDV.selected = [];
        RDV.totalPrix = 0;
        RDV.totalTemps = 0;
        RDV.weekData = {};
        RDV.selectedDate = null;
        RDV.selectedHeure = null;
        RDV.selectedPont = null;
        RDV.selectedMeca = null;
        var presta = document.getElementById('pub-prestations');
        if (presta) presta.innerHTML = '<div style="color:#888">Choisissez vos prestations pour cet atelier.</div>';
        var recap = document.getElementById('pub-recap');
        if (recap) recap.style.display = 'none';
        var dur = document.getElementById('pub-duration');
        if (dur) dur.style.display = 'none';
        var b3 = document.getElementById('pub-btn-step3');
        if (b3) b3.disabled = true;
        if (RDV.step >= 2) window.RdvModule.chargerPrestations();
        if (RDV.step >= 3) window.RdvModule.chargerSemaine();
    },

    getRdvState: function() {
        if (!APP.rdvWizard) window.RdvModule.loadRdvForm();
        return APP.rdvWizard;
    },

    loadMotoReferenceData: function() {
        APP.rdvMotoLookup = APP.rdvMotoLookup || { marques: [], modeles: [], selectedModele: null };
        window.RdvModule.renderMotoDatalist('pub-modele-suggestions', []);
        window.RdvModule.setManualMotoHelp('La base moto propose des suggestions pendant la saisie.', '');
        apiGet('/api/motos/marques').then(function(r) { return r.json(); }).then(function(marques) {
            APP.rdvMotoLookup.marques = Array.isArray(marques) ? marques : [];
            window.RdvModule.renderMotoDatalist('pub-marque-suggestions', APP.rdvMotoLookup.marques);
        }).catch(function() {
            window.RdvModule.renderMotoDatalist('pub-marque-suggestions', []);
        });
    },

    setManualMotoHelp: function(message, tone) {
        var help = document.getElementById('pub-man-moto-help');
        if (!help) return;
        help.textContent = message || 'La base moto propose des suggestions pendant la saisie.';
        help.style.color = tone === 'error' ? '#fca5a5' : (tone === 'success' ? '#86efac' : '#9ca3af');
    },

    renderMotoDatalist: function(listId, values, formatter) {
        var list = document.getElementById(listId);
        if (!list) return;
        var html = '';
        (values || []).forEach(function(item) {
            var value = formatter ? formatter(item) : item;
            if (!value) return;
            html += '<option value="' + escapeAttr(String(value)) + '"></option>';
        });
        list.innerHTML = html;
    },

    searchManualMotoBrand: function(val) {
        APP.rdvMotoLookup = APP.rdvMotoLookup || { marques: [], modeles: [], selectedModele: null };
        APP.rdvMotoLookup.selectedModele = null;
        if (APP.rdvMotoLookup.marques && APP.rdvMotoLookup.marques.length) {
            window.RdvModule.renderMotoDatalist('pub-marque-suggestions', APP.rdvMotoLookup.marques);
        }
        if (_rdvMotoBrandTimer) clearTimeout(_rdvMotoBrandTimer);
        _rdvMotoBrandTimer = setTimeout(function() {
            var brandInput = document.getElementById('pub-man-marque');
            var typedBrand = (val || '').trim().toUpperCase();
            var knownBrands = APP.rdvMotoLookup.marques || [];
            var exactBrand = knownBrands.find(function(item) { return String(item || '').trim().toUpperCase() === typedBrand; });
            var prefixBrands = knownBrands.filter(function(item) { return String(item || '').trim().toUpperCase().indexOf(typedBrand) === 0; });
            if (brandInput && !exactBrand && typedBrand && prefixBrands.length === 1) {
                brandInput.value = prefixBrands[0];
            }
            var modelValue = (document.getElementById('pub-man-modele') || {}).value || '';
            if (!val) {
                window.RdvModule.renderMotoDatalist('pub-modele-suggestions', []);
                window.RdvModule.setManualMotoHelp('La base moto propose des suggestions pendant la saisie.', '');
                return;
            }
            window.RdvModule.searchManualMotoModel(modelValue);
        }, 120);
    },

    searchManualMotoModel: function(val) {
        var marque = ((document.getElementById('pub-man-marque') || {}).value || '').trim().toUpperCase();
        var query = (val || '').trim();
        APP.rdvMotoLookup = APP.rdvMotoLookup || { marques: [], modeles: [], selectedModele: null };
        APP.rdvMotoLookup.selectedModele = null;
        if (_rdvMotoModelTimer) clearTimeout(_rdvMotoModelTimer);
        if (!marque && query.length < 2) {
            window.RdvModule.renderMotoDatalist('pub-modele-suggestions', []);
            return;
        }
        _rdvMotoModelTimer = setTimeout(function() {
            var params = ['limit=10'];
            if (marque) params.push('marque=' + encodeURIComponent(marque));
            if (query) params.push('query=' + encodeURIComponent(query));
            apiGet('/api/motos/autocomplete?' + params.join('&')).then(function(r) { return r.json(); }).then(function(data) {
                APP.rdvMotoLookup.marques = Array.isArray(data.marques) && data.marques.length ? data.marques : (APP.rdvMotoLookup.marques || []);
                APP.rdvMotoLookup.modeles = Array.isArray(data.modeles) ? data.modeles : [];
                window.RdvModule.renderMotoDatalist('pub-marque-suggestions', APP.rdvMotoLookup.marques);
                window.RdvModule.renderMotoDatalist('pub-modele-suggestions', APP.rdvMotoLookup.modeles, function(item) { return item.modele; });

                var normalized = query.trim().toUpperCase();
                var exact = APP.rdvMotoLookup.modeles.find(function(item) {
                    return ((item.modele || '').trim().toUpperCase() === normalized);
                });
                var prefixMatches = normalized ? APP.rdvMotoLookup.modeles.filter(function(item) {
                    return ((item.modele || '').trim().toUpperCase().indexOf(normalized) === 0);
                }) : [];
                var selected = exact || (prefixMatches.length === 1 ? prefixMatches[0] : null) || ((!normalized || normalized.length < 2) && APP.rdvMotoLookup.modeles.length === 1 ? APP.rdvMotoLookup.modeles[0] : null);

                if (selected) {
                    window.RdvModule.applyManualMotoBaseSelection(selected);
                } else if (APP.rdvMotoLookup.modeles.length) {
                    window.RdvModule.setManualMotoHelp(APP.rdvMotoLookup.modeles.length + ' suggestion(s) trouvee(s) dans la base moto. Continuez a taper ou choisissez un modele propose.', '');
                } else {
                    window.RdvModule.setManualMotoHelp('Aucun modele correspondant dans la base moto. Saisie libre possible.', 'error');
                }
            }).catch(function() {
                window.RdvModule.setManualMotoHelp('Base moto indisponible pour le moment. Saisie libre possible.', 'error');
            });
        }, 180);
    },

    applyManualMotoBaseSelection: function(item) {
        if (!item) return;
        APP.rdvMotoLookup = APP.rdvMotoLookup || { marques: [], modeles: [], selectedModele: null };
        APP.rdvMotoLookup.selectedModele = item;
        var RDV = window.RdvModule.getRdvState();
        var marqueInput = document.getElementById('pub-man-marque');
        var modeleInput = document.getElementById('pub-man-modele');
        var anneeInput = document.getElementById('pub-man-annee');
        var cylindreeInput = document.getElementById('pub-man-cylindree');
        var typeSelect = document.getElementById('pub-moto-type-manual');
        if (marqueInput) marqueInput.value = item.marque || marqueInput.value;
        if (modeleInput) modeleInput.value = item.modele || modeleInput.value;
        if (cylindreeInput && item.cylindree_display && !cylindreeInput.value) cylindreeInput.value = item.cylindree_display;
        if (anneeInput) {
            if (!anneeInput.value) {
                anneeInput.value = item.annee_fin || item.annee_debut || '';
            }
            if (item.annees_display) anneeInput.placeholder = 'Ex: ' + item.annees_display;
        }
        if (typeSelect && item.categorie_nom) typeSelect.value = item.categorie_nom;
        if (RDV && RDV.vehicule) {
            RDV.vehicule.marque = item.marque || RDV.vehicule.marque || null;
            RDV.vehicule.modele = item.modele || RDV.vehicule.modele || null;
            RDV.vehicule.cylindree = item.cylindree_display || RDV.vehicule.cylindree || null;
            RDV.vehicule.type_moto = item.categorie_nom || RDV.vehicule.type_moto || null;
            RDV.vehicule.categorie_id = item.categorie_id || null;
            RDV.vehicule.modele_id = item.id || null;
        }
        window.RdvModule.setManualMotoHelp('Base moto: ' + [item.marque, item.modele, item.categorie_nom, item.cylindree_display].filter(Boolean).join(' • '), 'success');
    },

    goStep: function(n) {
        var RDV = window.RdvModule.getRdvState();
        RDV.step = n;
        var steps = document.querySelectorAll('#rdvw-root .wizard-step');
        for (var i = 0; i < steps.length; i++) steps[i].classList.remove('active');
        var target = document.getElementById('step-' + n);
        if (target) target.classList.add('active');
        for (var s = 1; s <= 4; s++) {
            var dot = document.getElementById('sd-' + s);
            var line = document.getElementById('sl-' + s);
            if (dot) {
                dot.className = 'step-dot' + (s < n ? ' done' : (s === n ? ' active' : ''));
                dot.textContent = s < n ? '✓' : String(s);
            }
            if (line) line.className = 'step-line' + (s < n ? ' done' : '');
        }
        if (n === 2) window.RdvModule.chargerPrestations();
        if (n === 3) window.RdvModule.chargerDelaiIntervention().then(window.RdvModule.chargerSemaine).catch(window.RdvModule.chargerSemaine);
        if (n === 4) window.RdvModule.afficherRecap();
    },

    rechercherVehicule: function() {
        var RDV = window.RdvModule.getRdvState();
        var plaque = (document.getElementById('pub-plaque').value || '').replace(/[\s-]/g, '').toUpperCase();
        if (plaque.length < 3) { alert('Entrez une plaque valide'); return; }
        var btn = document.getElementById('btn-search');
        if (btn) { btn.innerHTML = '<span class="loading-spinner"></span> Recherche...'; btn.disabled = true; }
        apiGet('/api/vehicule/' + encodeURIComponent(plaque)).then(function(r) { return r.json(); }).then(function(data) {
            if (btn) { btn.textContent = 'Rechercher mon vehicule'; btn.disabled = false; }
            if (data && !data.not_found) {
                RDV.vehicule = data;
                RDV.motoType = data.type_moto || '';
                document.getElementById('pub-v-marque').textContent = data.marque || '-';
                document.getElementById('pub-v-modele').textContent = data.modele || '-';
                document.getElementById('pub-v-annee').textContent = data.annee || '-';
                document.getElementById('pub-v-cylindree').textContent = data.cylindree || '-';
                document.getElementById('pub-v-type').textContent = data.type_moto || 'Non defini';
                document.getElementById('pub-v-plaque').textContent = data.plaque || plaque;
                document.getElementById('pub-v-type-missing').style.display = data.type_moto ? 'none' : 'block';
                document.getElementById('pub-veh-result').style.display = 'block';
                document.getElementById('pub-veh-notfound').style.display = 'none';
            } else {
                RDV.vehicule = { plaque: plaque };
                document.getElementById('pub-veh-result').style.display = 'none';
                document.getElementById('pub-veh-notfound').style.display = 'block';
            }
        }).catch(function() {
            if (btn) { btn.textContent = 'Rechercher mon vehicule'; btn.disabled = false; }
        });
    },

    validerVehiculeManuel: function() {
        var RDV = window.RdvModule.getRdvState();
        var marque = (document.getElementById('pub-man-marque').value || '').trim();
        var modele = (document.getElementById('pub-man-modele').value || '').trim();
        var annee = (document.getElementById('pub-man-annee').value || '').trim();
        var cylindree = (document.getElementById('pub-man-cylindree').value || '').trim();
        var typeMoto = (document.getElementById('pub-moto-type-manual').value || '').trim();
        if (!marque || !modele || !annee || !typeMoto) { alert('Veuillez remplir les champs obligatoires'); return; }
        var selectedModele = APP.rdvMotoLookup && APP.rdvMotoLookup.selectedModele ? APP.rdvMotoLookup.selectedModele : null;
        if (selectedModele) {
            var sameBrand = (selectedModele.marque || '').trim().toUpperCase() === marque.toUpperCase();
            var sameModele = (selectedModele.modele || '').trim().toUpperCase() === modele.toUpperCase();
            if (!sameBrand || !sameModele) selectedModele = null;
        }
        var plaque = (document.getElementById('pub-plaque').value || '').replace(/[\s-]/g, '').toUpperCase();
        RDV.vehicule = {
            plaque: plaque || 'XX-000-XX',
            marque: marque.toUpperCase(),
            modele: modele,
            annee: parseInt(annee, 10),
            cylindree: cylindree || null,
            type_moto: typeMoto,
            categorie_id: selectedModele ? (selectedModele.categorie_id || null) : null,
            modele_id: selectedModele ? (selectedModele.id || null) : null
        };
        RDV.motoType = typeMoto;
        window.RdvModule.goStep(2);
    },

    onTypeMotoFoundSelect: function(val) {
        var RDV = window.RdvModule.getRdvState();
        if (!val) return;
        RDV.motoType = val;
        if (RDV.vehicule) RDV.vehicule.type_moto = val;
        document.getElementById('pub-v-type').textContent = val;
    },

    continuerVehiculeFound: function() {
        var RDV = window.RdvModule.getRdvState();
        if (!RDV.motoType) { alert('Veuillez selectionner le type de moto'); return; }
        window.RdvModule.goStep(2);
    },

    getPrestaTarif: function(it) {
        var RDV = window.RdvModule.getRdvState();
        var t = it.tarifs && it.tarifs[RDV.motoType];
        if (t) return { prix_ttc: t.prix_ttc, temps_minutes: t.temps_minutes };
        return { prix_ttc: it.prix_base_ttc || 0, temps_minutes: it.temps_estime_minutes || 30 };
    },

    chargerPrestations: function() {
        var RDV = window.RdvModule.getRdvState();
        if (RDV.interventions.length > 0) { window.RdvModule.renderPrestations(); return; }
        var atelierSlug = window.RdvModule.getCurrentAtelierSlug();
        apiGet('/api/prestations/public?atelier_slug=' + encodeURIComponent(atelierSlug)).then(function(r) { return r.json(); }).then(function(data) {
            RDV.interventions = (Array.isArray(data) ? data : []).map(function(it) {
                return { id: it.id, nom: it.nom, description: it.description || '', prix_base_ttc: it.prix_base_ttc != null ? it.prix_base_ttc : (it.prix_base || 0), temps_estime_minutes: it.temps_estime_minutes != null ? it.temps_estime_minutes : (it.temps_estime || 30), tarifs: it.grille || it.tarifs || {}, delai_intervention_jours: it.delai_intervention_jours != null ? it.delai_intervention_jours : 1 };
            });
            window.RdvModule.renderPrestations();
        }).catch(function() {
            document.getElementById('pub-prestations').innerHTML = '<div style="color:#666">Erreur chargement des prestations</div>';
        });
    },

    renderPrestations: function() {
        var RDV = window.RdvModule.getRdvState();
        var container = document.getElementById('pub-prestations');
        if (!container) return;
        var html = '';
        RDV.interventions.forEach(function(it) {
            var selected = RDV.selected.indexOf(it.id) !== -1;
            var tarif = window.RdvModule.getPrestaTarif(it);
            html += '<div class="presta-card' + (selected ? ' selected' : '') + '" onclick="togglePresta(' + it.id + ')"><div class="presta-check">' + (selected ? '✓' : '') + '</div><div class="presta-info"><div class="presta-name">' + escapeHtml(it.nom) + '</div><div class="presta-detail">' + escapeHtml(it.description || '') + '</div></div><div class="presta-price">' + Number(tarif.prix_ttc || 0).toFixed(2) + ' EUR</div></div>';
        });
        container.innerHTML = html;
        window.RdvModule.updateRecap();
    },

    togglePresta: function(id) {
        var RDV = window.RdvModule.getRdvState();
        var idx = RDV.selected.indexOf(id);
        if (idx === -1) RDV.selected.push(id); else RDV.selected.splice(idx, 1);
        RDV.weekData = {};
        RDV.selectedDate = null;
        RDV.selectedHeure = null;
        RDV.selectedPont = null;
        RDV.selectedMeca = null;
        window.RdvModule.renderPrestations();
        var selected = document.getElementById('pub-selected-slot');
        if (selected) selected.style.display = 'none';
        var assign = document.getElementById('pub-assign');
        if (assign) assign.style.display = 'none';
        var btnStep4 = document.getElementById('pub-btn-step4');
        if (btnStep4) btnStep4.disabled = true;
        var btn = document.getElementById('pub-btn-step3');
        if (btn) btn.disabled = RDV.selected.length === 0;
    },

    updateRecap: function() {
        var RDV = window.RdvModule.getRdvState();
        var recap = document.getElementById('pub-recap');
        var lines = document.getElementById('pub-recap-lines');
        var total = document.getElementById('pub-recap-total');
        var durBlock = document.getElementById('pub-duration');
        if (!recap || !lines || !total || !durBlock) return;
        if (RDV.selected.length === 0) { recap.style.display = 'none'; durBlock.style.display = 'none'; return; }
        recap.style.display = 'block';
        durBlock.style.display = 'block';
        var prix = 0; var temps = 0; var html = '';
        RDV.selected.forEach(function(id) {
            var it = RDV.interventions.find(function(x) { return x.id === id; });
            if (!it) return;
            var tarif = window.RdvModule.getPrestaTarif(it);
            var p = Number(tarif.prix_ttc || 0);
            var t = parseInt(tarif.temps_minutes || 0, 10) || 0;
            prix += p; temps += t;
            html += '<div class="recap-row"><span>' + escapeHtml(it.nom) + '</span><span>' + p.toFixed(2) + ' EUR</span></div>';
        });
        lines.innerHTML = html;
        total.textContent = prix.toFixed(2) + ' EUR';
        RDV.totalPrix = prix; RDV.totalTemps = temps;
        var h = Math.floor(temps / 60); var m = temps % 60;
        document.getElementById('pub-dur-value').textContent = (h > 0 ? h + 'h' : '') + (m > 0 ? m + 'min' : '');
        document.getElementById('pub-dur-note').textContent = RDV.motoType ? ('Tarif ' + RDV.motoType) : '';
    },

    chargerDelaiIntervention: function() {
        var RDV = window.RdvModule.getRdvState();
        if (!RDV.selected.length) { RDV.delaiInterventionJours = 1; return Promise.resolve(); }
        var atelierSlug = window.RdvModule.getCurrentAtelierSlug();
        return apiGet('/api/tarifs/delais?prestation_ids=' + encodeURIComponent(RDV.selected.join(',')) + '&atelier_slug=' + encodeURIComponent(atelierSlug)).then(function(r) { return r.json(); }).then(function(data) {
            var d = parseInt(data.delai_total_jours, 10);
            RDV.delaiInterventionJours = (isFinite(d) && d > 0) ? d : 1;
        }).catch(function() { RDV.delaiInterventionJours = 1; });
    },

    _rdvDateToStr: function(d) {
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var dd = String(d.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + dd;
    },

    _rdvWeekDays: function(offset, delayDays) {
        var now = new Date();
        var earliest = new Date(now);
        earliest.setDate(now.getDate() + (Math.max(1, parseInt(delayDays || 1, 10)) - 1));
        earliest.setHours(0, 0, 0, 0);
        var day = earliest.getDay();
        var diff = (day === 0 ? -6 : 1 - day);
        var monday = new Date(earliest);
        monday.setDate(earliest.getDate() + diff + (offset * 7));
        var days = [];
        for (var i = 0; i < 5; i++) {
            var d = new Date(monday);
            d.setDate(monday.getDate() + i);
            days.push(d);
        }
        return days;
    },

    changeWeek: function(dir) {
        var RDV = window.RdvModule.getRdvState();
        RDV.weekOffset += dir;
        if (RDV.weekOffset < 0) RDV.weekOffset = 0;
        window.RdvModule.chargerSemaine();
    },

    chargerSemaine: function() {
        var RDV = window.RdvModule.getRdvState();
        RDV.weekData = {};
        var days = window.RdvModule._rdvWeekDays(RDV.weekOffset, RDV.delaiInterventionJours);
        var monday = days[0], friday = days[4];
        var MOIS = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aou', 'Sep', 'Oct', 'Nov', 'Dec'];
        var JOURS_COURTS = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        var weekLabel = document.getElementById('pub-week-label');
        if (weekLabel) weekLabel.textContent = 'Semaine du ' + monday.getDate() + ' ' + MOIS[monday.getMonth()] + ' au ' + friday.getDate() + ' ' + MOIS[friday.getMonth()] + ' ' + friday.getFullYear();
        var grid = document.getElementById('pub-week-grid');
        if (!grid) return;
        var now = new Date();
        var earliest = new Date(now);
        earliest.setDate(now.getDate() + (Math.max(1, parseInt(RDV.delaiInterventionJours || 1, 10)) - 1));
        earliest.setHours(0, 0, 0, 0);
        var earliestStr = window.RdvModule._rdvDateToStr(earliest);
        var todayStr = window.RdvModule._rdvDateToStr(now);
        var html = '';
        days.forEach(function(d) {
            var ds = window.RdvModule._rdvDateToStr(d);
            var isPast = ds < earliestStr;
            var isToday = ds === todayStr;
            html += '<div class="week-day' + (isToday ? ' today' : '') + (isPast ? ' past' : '') + '"><div class="week-day-header"><div class="week-day-name">' + JOURS_COURTS[d.getDay()] + '</div><div class="week-day-date">' + d.getDate() + ' ' + MOIS[d.getMonth()] + '</div></div><div class="week-day-slots" id="ws-' + ds + '"><div style="text-align:center;padding:12px;color:#555;font-size:11px">' + (isPast ? 'Passe' : '<span class="loading-spinner"></span>') + '</div></div></div>';
        });
        grid.innerHTML = html;
        days.forEach(function(d) {
            var ds = window.RdvModule._rdvDateToStr(d);
            if (ds < earliestStr) return;
            window.RdvModule.chargerJour(ds);
        });
        var selected = document.getElementById('pub-selected-slot'); if (selected) selected.style.display = 'none';
        var assign = document.getElementById('pub-assign'); if (assign) assign.style.display = 'none';
        var btn = document.getElementById('pub-btn-step4'); if (btn) btn.disabled = true;
    },

    chargerJour: function(dateStr) {
        var RDV = window.RdvModule.getRdvState();
        if (RDV.weekData[dateStr]) return window.RdvModule.renderJour(dateStr, RDV.weekData[dateStr]);
        var atelierSlug = window.RdvModule.getCurrentAtelierSlug();
        var duree = Math.max(30, parseInt(RDV.totalTemps || 60, 10));
        apiGet('/api/creneaux/avec-ponts?date_str=' + dateStr + '&duree_minutes=' + encodeURIComponent(duree) + '&atelier_slug=' + encodeURIComponent(atelierSlug)).then(function(r) { return r.json(); }).then(function(data) {
            RDV.weekData[dateStr] = data || {};
            window.RdvModule.renderJour(dateStr, RDV.weekData[dateStr]);
        }).catch(function() {
            var c = document.getElementById('ws-' + dateStr);
            if (c) c.innerHTML = '<div style="text-align:center;padding:12px;color:#555;font-size:11px">Erreur</div>';
        });
    },

    renderJour: function(dateStr, data) {
        var RDV = window.RdvModule.getRdvState();
        var container = document.getElementById('ws-' + dateStr);
        if (!container) return;
        var creneaux = (data && data.creneaux) ? data.creneaux : [];
        if (!creneaux.length) { container.innerHTML = '<div style="text-align:center;padding:12px;color:#444;font-size:11px">Ferme</div>'; return; }
        var todayStr = window.RdvModule._rdvDateToStr(new Date());
        var isToday = dateStr === todayStr;
        var now = new Date();
        var nowMins = now.getHours() * 60 + now.getMinutes();
        var html = '';
        creneaux.forEach(function(c) {
            var hm = (c.heure || '').split(':');
            var slotMins = (parseInt(hm[0], 10) || 0) * 60 + (parseInt(hm[1], 10) || 0);
            if (isToday && slotMins <= nowMins) return;
            var isSel = (RDV.selectedDate === dateStr && RDV.selectedHeure === c.heure);
            if (c.disponible) html += '<div class="wslot available' + (isSel ? ' selected' : '') + '" onclick="selectWeekSlot(this,\'' + dateStr + '\',\'' + escapeAttr(c.heure) + '\')">' + escapeHtml(c.heure) + '<div class="wslot-places">' + (c.nb_ponts_libres || 0) + ' pont(s)</div></div>';
            else html += '<div class="wslot full">' + escapeHtml(c.heure) + '<div class="wslot-places">Complet</div></div>';
        });
        container.innerHTML = html || '<div style="text-align:center;padding:12px;color:#444;font-size:11px">Plus de creneaux</div>';
    },

    _rdvFormatDate: function(dateStr) {
        if (!dateStr) return '-';
        var p = dateStr.split('-');
        if (p.length !== 3) return dateStr;
        var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
        var JOURS_LONGS = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        return JOURS_LONGS[d.getDay()] + ' ' + p[2] + '/' + p[1] + '/' + p[0];
    },

    selectWeekSlot: function(el, dateStr, heure) {
        var RDV = window.RdvModule.getRdvState();
        var all = document.querySelectorAll('#rdvw-root .wslot');
        for (var i = 0; i < all.length; i++) all[i].classList.remove('selected');
        el.classList.add('selected');
        RDV.selectedDate = dateStr;
        RDV.selectedHeure = heure;
        document.getElementById('pub-selected-slot').style.display = 'block';
        document.getElementById('pub-slot-label').textContent = window.RdvModule._rdvFormatDate(dateStr) + ' a ' + heure;
        document.getElementById('pub-btn-step4').disabled = false;
        RDV.selectedPont = null;
        RDV.selectedMeca = null;
        var dayData = RDV.weekData[dateStr];
        var assignDiv = document.getElementById('pub-assign');
        if (dayData && dayData.creneaux) {
            var c = dayData.creneaux.find(function(x) { return x.heure === heure; });
            if (c && c.ponts_disponibles && c.ponts_disponibles.length) {
                var p = c.ponts_disponibles[0];
                RDV.selectedPont = p;
                RDV.selectedMeca = p.mecanicien || null;
                document.getElementById('pub-assign-pont').textContent = p.nom || '-';
                document.getElementById('pub-assign-meca').textContent = p.mecanicien || 'Auto-assigne';
                assignDiv.style.display = 'block';
                return;
            }
        }
        assignDiv.style.display = 'none';
    },

    afficherRecap: function() {
        var RDV = window.RdvModule.getRdvState();
        var v = RDV.vehicule || {};
        document.getElementById('pub-final-veh').textContent = ((v.marque || '') + ' ' + (v.modele || '')).trim() + (v.plaque ? (' (' + v.plaque + ')') : '');
        var prestaNames = [];
        RDV.selected.forEach(function(id) {
            var it = RDV.interventions.find(function(x) { return x.id === id; });
            if (it) prestaNames.push(it.nom);
        });
        document.getElementById('pub-final-presta').textContent = prestaNames.join(', ');
        document.getElementById('pub-final-date').textContent = window.RdvModule._rdvFormatDate(RDV.selectedDate) + ' a ' + RDV.selectedHeure;
        document.getElementById('pub-final-pont').textContent = (RDV.selectedPont ? RDV.selectedPont.nom : '-') + ' / ' + (RDV.selectedMeca || '-');
        var h = Math.floor((RDV.totalTemps || 0) / 60), m = (RDV.totalTemps || 0) % 60;
        document.getElementById('pub-final-duree').textContent = (h > 0 ? h + 'h' : '') + (m > 0 ? m + 'min' : '');
        document.getElementById('pub-final-total').textContent = Number(RDV.totalPrix || 0).toFixed(2) + ' EUR';
        if (APP.rdvClientPrefill) {
            document.getElementById('pub-prenom').value = APP.rdvClientPrefill.prenom || '';
            document.getElementById('pub-nom').value = APP.rdvClientPrefill.nom || '';
            document.getElementById('pub-tel').value = APP.rdvClientPrefill.telephone || '';
            document.getElementById('pub-email').value = APP.rdvClientPrefill.email || '';
        }
    },

    confirmerRDV: function() {
        var RDV = window.RdvModule.getRdvState();
        var prenom = (document.getElementById('pub-prenom').value || '').trim();
        var nom = (document.getElementById('pub-nom').value || '').trim();
        var tel = (document.getElementById('pub-tel').value || '').trim();
        var email = (document.getElementById('pub-email').value || '').trim();
        var comment = (document.getElementById('pub-comment').value || '').trim();
        if (!prenom || !nom || !tel) { alert('Prenom, nom et telephone obligatoires'); return; }
        var atelierSlug = window.RdvModule.getCurrentAtelierSlug();
        var btn = document.getElementById('btn-confirm');
        if (btn) { btn.innerHTML = '<span class="loading-spinner"></span> Confirmation...'; btn.disabled = true; }
        var v = RDV.vehicule || {};
        var body = {
            client: { nom: nom, prenom: prenom, telephone: tel, email: email || null },
            vehicule: {
                plaque: v.plaque || 'XX-000-XX',
                marque: v.marque || null,
                modele: v.modele || null,
                annee: v.annee || null,
                cylindree: v.cylindree || null,
                type_moto: RDV.motoType || v.type_moto || null,
                categorie_id: v.categorie_id || null,
                modele_id: v.modele_id || null
            },
            prestations: RDV.selected,
            date_heure: RDV.selectedDate + 'T' + RDV.selectedHeure + ':00',
            montant_estime: RDV.totalPrix || 0,
            commentaires: comment || null,
            pont_id: RDV.selectedPont ? RDV.selectedPont.id : null,
            atelier_slug: atelierSlug
        };
        apiPost('/api/rendez-vous/public', body).then(function(r) { return r.json(); }).then(function(data) {
            document.getElementById('pub-rdv-num').textContent = '#RDV-' + String(data.id || '').padStart(4, '0');
            var detailsHtml = '';
            detailsHtml += '<div class="sd-row"><span>Vehicule</span><span>' + escapeHtml(((v.marque || '') + ' ' + (v.modele || '')).trim()) + '</span></div>';
            detailsHtml += '<div class="sd-row"><span>Date</span><span>' + window.RdvModule._rdvFormatDate(RDV.selectedDate) + ' a ' + escapeHtml(RDV.selectedHeure || '-') + '</span></div>';
            if (RDV.selectedPont) detailsHtml += '<div class="sd-row"><span>Pont</span><span>' + escapeHtml(RDV.selectedPont.nom || '-') + '</span></div>';
            if (RDV.selectedMeca) detailsHtml += '<div class="sd-row"><span>Mecanicien</span><span>' + escapeHtml(RDV.selectedMeca) + '</span></div>';
            document.getElementById('pub-success-details').innerHTML = detailsHtml;
            var steps = document.querySelectorAll('#rdvw-root .wizard-step');
            for (var i = 0; i < steps.length; i++) steps[i].classList.remove('active');
            document.getElementById('step-success').classList.add('active');
            for (var s = 1; s <= 4; s++) {
                var dot = document.getElementById('sd-' + s);
                var line = document.getElementById('sl-' + s);
                if (dot) { dot.className = 'step-dot done'; dot.textContent = '✓'; }
                if (line) line.className = 'step-line done';
            }
        }).catch(function(e) {
            if (btn) { btn.textContent = 'Confirmer le RDV + Generer OR'; btn.disabled = false; }
            alert('Erreur lors de la creation du RDV: ' + e.message);
        });
    }
};
